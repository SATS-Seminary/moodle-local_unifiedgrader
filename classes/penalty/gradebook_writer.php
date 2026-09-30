<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Writes penalty deductions to the gradebook the Moodle 5.3 way.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\penalty;

/**
 * Records a deduction in grade_grades.deductedmark.
 *
 * From Moodle 5.3 (MDL-88407) the gradebook keeps the deduction apart from the
 * raw grade: finalgrade is worked out from rawgrade − deductedmark, full
 * regrades keep it, and the gradebook shows its penalty icon next to the cell.
 * This does for any activity what core's own penalty_manager::apply_penalty()
 * does for assignments, so the raw grade stays the true mark (the teacher's
 * mark, or the quiz engine's total) and nothing needs pinning with an override.
 *
 * The one thing to know: when an activity pushes a new raw grade, the gradebook
 * resets deductedmark to 0. The user_graded observer puts it back.
 */
class gradebook_writer {
    /** @var string The deduction was written. */
    public const APPLIED = 'applied';

    /** @var string The cell already held this deduction. */
    public const UNCHANGED = 'unchanged';

    /** @var string Nothing to do: no grade, or the cell is locked or overridden. */
    public const SKIPPED = 'skipped';

    /**
     * The course still uses the pre-5.3 penalty representation.
     *
     * Its gradebook is frozen, and a deduction written here would be ignored
     * or doubled. The caller falls back to pushing a reduced raw grade.
     */
    public const LEGACY = 'legacy';

    /** @var string Source recorded in the grade history. */
    public const SOURCE = 'local/unifiedgrader';

    /**
     * Grade history sources whose overrides were penalty pins, not teacher edits.
     *
     * Before 5.3 the quizaccess_duedate rule, and this plugin's own quiz sync,
     * pinned a penalised quiz mark with a gradebook override so the quiz module
     * could not overwrite it.
     */
    private const LEGACY_PIN_SOURCES = ['quizaccess_duedate', self::SOURCE];

    /**
     * Record a deduction against a student's grade.
     *
     * Idempotent: the result depends only on the stored raw grade and the
     * deduction passed in, never on the previous final grade.
     *
     * A locked cell is an administrative decision, and an overridden cell is a
     * value a teacher typed into the gradebook on purpose; both are left alone.
     *
     * @param \grade_item $gradeitem The activity's grade item.
     * @param int $userid The student.
     * @param float $deduction Marks to deduct, in raw grade units (0 clears it).
     * @return string One of the class constants.
     */
    public static function apply(\grade_item $gradeitem, int $userid, float $deduction): string {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        if (\core_grades\penalty_manager::is_frozen_for_legacy_penalty((int) $gradeitem->courseid)) {
            return self::LEGACY;
        }

        $grade = \grade_grade::fetch(['itemid' => $gradeitem->id, 'userid' => $userid]);
        if (!$grade || $grade->rawgrade === null) {
            return self::SKIPPED;
        }
        if ($grade->is_locked()) {
            return self::SKIPPED;
        }
        if ($grade->is_overridden() && !self::release_legacy_pin($grade)) {
            return self::SKIPPED;
        }

        $deduction = max(0.0, $deduction);
        $penalisedraw = max((float) $gradeitem->grademin, (float) $grade->rawgrade - $deduction);
        $newfinal = \core_grades\penalty_manager::apply_grade_item_factors($penalisedraw, $gradeitem, $grade);

        $oldfinal = $grade->finalgrade;
        $samededuction = !grade_floats_different((float) $grade->deductedmark, $deduction);
        if ($samededuction && $oldfinal !== null && !grade_floats_different((float) $oldfinal, (float) $newfinal)) {
            return self::UNCHANGED;
        }

        $grade->deductedmark = $deduction;
        $grade->finalgrade = $newfinal;
        $grade->timemodified = time();
        $grade->update(self::SOURCE);

        if ($oldfinal === null || grade_floats_different((float) $oldfinal, (float) $newfinal)) {
            \core\event\user_graded::create_from_grade($grade)->trigger();
            self::regrade_parents($gradeitem, $userid);
        }

        return self::APPLIED;
    }

    /**
     * Lift an override that only existed to hold a pre-5.3 late penalty.
     *
     * A cell counts as such a pin when either:
     *
     * - its feedback is exactly the text quizaccess_duedate wrote with the pin
     *   ("Late penalty of 15% applied."), which survives a course backup and
     *   restore even though the grade history there only says "restore"; or
     * - the history entry that set the override came from quizaccess_duedate or
     *   from this plugin. That is the first entry of the current run of
     *   overridden entries, not the latest: the quiz keeps adding entries under
     *   an override every time it pushes its raw grade.
     *
     * Anything else is a teacher's override and stays. That auto-written
     * feedback is cleared with the pin, since it no longer describes the grade.
     * Released lazily, the first time a student's grade is resynced, so grades
     * in courses nobody touches again keep the penalty they were given.
     *
     * The raw grade underneath an override keeps following the activity, so
     * once the flag is cleared the caller can work the final grade out from it.
     *
     * @param \grade_grade $grade An overridden grade.
     * @return bool True if the override was lifted.
     */
    private static function release_legacy_pin(\grade_grade $grade): bool {
        $autofeedback = self::is_legacy_penalty_feedback((string) $grade->feedback);
        if (!$autofeedback && !in_array(self::pin_source($grade), self::LEGACY_PIN_SOURCES, true)) {
            return false;
        }

        if ($autofeedback) {
            $grade->feedback = null;
            $grade->feedbackformat = FORMAT_MOODLE;
        }
        $grade->set_overridden(false, false);
        return true;
    }

    /**
     * Whether gradebook feedback is the text quizaccess_duedate wrote with its penalty.
     *
     * That plugin ships in English only, so its wording is fixed.
     *
     * @param string $feedback The cell's feedback.
     * @return bool
     */
    public static function is_legacy_penalty_feedback(string $feedback): bool {
        $text = trim(html_entity_decode(strip_tags($feedback), ENT_QUOTES, 'UTF-8'));
        return (bool) preg_match('/^Late penalty of \d+(\.\d+)?% applied\.?$/', $text);
    }

    /**
     * The source of the history entry that set a cell's current override.
     *
     * @param \grade_grade $grade An overridden grade.
     * @return string The source, or '' when the history does not say.
     */
    private static function pin_source(\grade_grade $grade): string {
        global $DB;

        $history = $DB->get_records_sql(
            'SELECT id, source, overridden
               FROM {grade_grades_history}
              WHERE oldid = :oldid
           ORDER BY timemodified DESC, id DESC',
            ['oldid' => $grade->id],
            0,
            200,
        );
        $source = '';
        foreach ($history as $entry) {
            if (empty($entry->overridden)) {
                break;
            }
            $source = (string) $entry->source;
        }
        return $source;
    }

    /**
     * Bring category and course totals up to date after a cell changed.
     *
     * The same fast path core takes after applying a penalty; if it fails, the
     * parent is marked for a full regrade instead.
     *
     * @param \grade_item $gradeitem The activity's grade item.
     * @param int $userid The student.
     */
    private static function regrade_parents(\grade_item $gradeitem, int $userid): void {
        $courseitem = \grade_item::fetch_course_item($gradeitem->courseid);
        if ($gradeitem->needsupdate || $courseitem->needsupdate) {
            return;
        }
        $parent = \grade_item::fetch([
            'itemtype' => 'category',
            'iteminstance' => $gradeitem->categoryid,
            'courseid' => $gradeitem->courseid,
        ]) ?: $courseitem;
        if (grade_regrade_final_grades($gradeitem->courseid, $userid, $parent) !== true) {
            $parent->force_regrading();
        }
    }
}
