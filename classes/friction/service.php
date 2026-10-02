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
 * Friction Feedback.
 *
 * After a grade is posted, the gradebook cell stays hidden until the student
 * has read each part of the feedback, or until a fail-safe clock opens it.
 * A quiz item hidden by review options is shown for that student then. The
 * attempt review page is left on those options.
 * The stored course total still includes the hidden grade. Moodle's own user
 * report drops hidden items, which is why the clock exists.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\friction;

use local_unifiedgrader\adapter\adapter_factory;
use local_unifiedgrader\feedback_data_helper;
use local_unifiedgrader\grades\release;

/**
 * Decides when a posted mark is held, and walks the student through the feedback.
 */
class service {
    /** @var string Follow the site setting. Stored as the absence of a row. */
    public const MODE_INHERIT = 'inherit';

    /** @var string Force the walk on for this activity. */
    public const MODE_ON = 'on';

    /** @var string Force the walk off for this activity. */
    public const MODE_OFF = 'off';

    /** @var string The student has not finished this mark. */
    public const STATE_PENDING = 'pending';

    /** @var string The student ticked the last step. */
    public const STATE_COMPLETE = 'complete';

    /** @var string A fail-safe clock opened the cell. */
    public const STATE_WAIVED = 'waived';

    /**
     * Whether posting this activity should hold the mark.
     *
     * @param int $cmid
     * @return bool
     */
    public static function applies(int $cmid): bool {
        $mode = self::mode($cmid);
        if ($mode === self::MODE_ON) {
            return true;
        }
        if ($mode === self::MODE_OFF) {
            return false;
        }
        return (bool) get_config('local_unifiedgrader', 'friction_enable');
    }

    /**
     * The activity's saved mode, or inherit when it has no row.
     *
     * @param int $cmid
     * @return string inherit, on, or off
     */
    public static function mode(int $cmid): string {
        global $DB;

        $mode = $DB->get_field('local_unifiedgrader_frictioncfg', 'mode', ['cmid' => $cmid]);
        if ($mode === self::MODE_ON || $mode === self::MODE_OFF) {
            return $mode;
        }
        return self::MODE_INHERIT;
    }

    /**
     * Save the activity switch. Inherit removes the row.
     *
     * @param int $cmid
     * @param string $mode inherit, on, or off
     */
    public static function set_mode(int $cmid, string $mode): void {
        global $DB;

        if (!in_array($mode, [self::MODE_INHERIT, self::MODE_ON, self::MODE_OFF], true)) {
            throw new \invalid_parameter_exception('Unknown friction mode');
        }
        if ($mode === self::MODE_INHERIT) {
            $DB->delete_records('local_unifiedgrader_frictioncfg', ['cmid' => $cmid]);
            return;
        }

        $existing = $DB->get_record('local_unifiedgrader_frictioncfg', ['cmid' => $cmid]);
        if ($existing) {
            $existing->mode = $mode;
            $existing->timemodified = time();
            $DB->update_record('local_unifiedgrader_frictioncfg', $existing);
            return;
        }
        $DB->insert_record('local_unifiedgrader_frictioncfg', (object) [
            'cmid' => $cmid,
            'mode' => $mode,
            'timemodified' => time(),
        ]);
    }

    /**
     * Hold or release the walk for the students a post just changed.
     *
     * An empty user list is the whole class. Hiding deletes pending rows and
     * does not open those cells again. A finished walk for the same mark is
     * left finished.
     *
     * @param \local_unifiedgrader\adapter\base_adapter $adapter
     * @param int $hidden 0 when the post reveals, otherwise a hide or a schedule
     * @param int[] $userids Empty for the whole class
     */
    public static function settle($adapter, int $hidden, array $userids): void {
        global $DB;

        $item = $adapter->get_grade_item();
        if (!$item) {
            return;
        }
        $hold = $hidden === 0 && self::applies($adapter->get_cmid());
        if (!$hold && !$userids) {
            $DB->delete_records('local_unifiedgrader_friction', [
                'itemid' => $item->id,
                'state' => self::STATE_PENDING,
            ]);
            return;
        }

        $targets = $userids ?: release::student_ids($adapter);
        foreach ($targets as $userid) {
            self::settle_user($adapter, $item, (int) $userid, $hold);
        }
    }

    /**
     * Students with a walk still open on this grade item.
     *
     * @param int $itemid
     * @return int[]
     */
    public static function pending_userids(int $itemid): array {
        global $DB;

        if ($itemid <= 0) {
            return [];
        }
        return array_map('intval', $DB->get_fieldset_select(
            'local_unifiedgrader_friction',
            'userid',
            'itemid = :itemid AND state = :state',
            ['itemid' => $itemid, 'state' => self::STATE_PENDING],
        ));
    }

    /**
     * Students whose walk for the current mark has finished, and whose cell is open.
     *
     * The grade item may still be hidden. A quiz keeps that flag for the review
     * options, so this list is how a student post stays posted in the grader.
     *
     * @param int $itemid
     * @return int[]
     */
    public static function released_userids(int $itemid): array {
        global $DB;

        if ($itemid <= 0) {
            return [];
        }
        $sql = "SELECT f.userid
                  FROM {local_unifiedgrader_friction} f
                  JOIN {grade_grades} g ON g.itemid = f.itemid AND g.userid = f.userid
                 WHERE f.itemid = :itemid
                   AND f.state <> :pending
                   AND f.gradetimemodified = g.timemodified
                   AND (g.hidden = 0 OR (g.hidden > 1 AND g.hidden <= :now))";
        return array_map('intval', $DB->get_fieldset_sql($sql, [
            'itemid' => $itemid,
            'pending' => self::STATE_PENDING,
            'now' => time(),
        ]));
    }

    /**
     * Whether this student has finished the walk for the mark now on the cell.
     *
     * @param \local_unifiedgrader\adapter\base_adapter $adapter
     * @param int $userid
     * @return bool
     */
    public static function is_released_user($adapter, int $userid): bool {
        $item = $adapter->get_grade_item();
        if (!$item || $userid <= 0) {
            return false;
        }
        return in_array($userid, self::released_userids((int) $item->id), true);
    }

    /**
     * Whether this student still has to read the feedback before the mark shows.
     *
     * @param \local_unifiedgrader\adapter\base_adapter $adapter
     * @param int $userid
     * @return bool
     */
    public static function is_pending_user($adapter, int $userid): bool {
        $item = $adapter->get_grade_item();
        if (!$item) {
            return false;
        }
        return self::pending_row((int) $item->id, $userid) !== null;
    }

    /**
     * Record that the student has seen the current step.
     *
     * The last tick opens the cell. The caller reloads onto the normal feedback page.
     *
     * @param int $cmid
     * @param int $userid
     * @return array{finished:bool,step:int}
     */
    public static function advance(int $cmid, int $userid): array {
        global $DB, $USER;

        if ((int) $USER->id !== $userid || $userid <= 0) {
            throw new \moodle_exception('nopermissions', 'error', '', 'friction');
        }
        $row = self::pending_row_for_cm($cmid, $userid);
        if (!$row) {
            throw new \moodle_exception('feedback_not_available', 'local_unifiedgrader');
        }

        $adapter = adapter_factory::create($cmid);
        $steps = self::steps($adapter, $userid);
        $next = (int) $row->step + 1;
        if (!$steps || $next >= count($steps)) {
            self::finish($row, self::STATE_COMPLETE);
            return ['finished' => true, 'step' => $next];
        }

        $DB->set_field('local_unifiedgrader_friction', 'step', $next, ['id' => $row->id]);
        return ['finished' => false, 'step' => $next];
    }

    /**
     * Open every pending cell whose course-end clock or posting clock is due.
     *
     * A hidden grade item is left alone when the activity itself owns that flag,
     * which is a real hide. A quiz item stays hidden while its review options
     * hide marks, including during a posted walk, so that one is opened here.
     * Opening a cell does not send the assignment mail.
     *
     * @return int How many cells were opened
     */
    public static function open_due(): int {
        global $DB;

        $now = time();
        $beforeend = self::config_int('friction_daysbeforeend', 14) * DAYSECS;
        $afterpost = self::config_int('friction_daysafterpost', 21) * DAYSECS;
        $sql = "SELECT f.*
                  FROM {local_unifiedgrader_friction} f
                  JOIN {course_modules} cm ON cm.id = f.cmid
                  JOIN {course} c ON c.id = cm.course
                 WHERE f.state = :state
                   AND (
                        (c.enddate > 0 AND (c.enddate - :beforeend) <= :nowend)
                        OR (f.gradetimemodified > 0 AND f.gradetimemodified <= :postedbefore)
                   )";
        $rows = $DB->get_records_sql($sql, [
            'state' => self::STATE_PENDING,
            'beforeend' => $beforeend,
            'nowend' => $now,
            'postedbefore' => $now - $afterpost,
        ]);

        $opened = 0;
        foreach ($rows as $row) {
            $item = \grade_item::fetch(['id' => $row->itemid]);
            if (!$item) {
                continue;
            }
            if ($item->is_hidden()) {
                $cm = get_coursemodule_from_id('', (int) $row->cmid, 0, false, IGNORE_MISSING);
                if (!$cm || !adapter_factory::is_supported($cm->modname)) {
                    continue;
                }
                $adapter = adapter_factory::create((int) $row->cmid);
                if (!$adapter->release_outlives_hidden_item()) {
                    continue;
                }
            }
            self::finish($row, self::STATE_WAIVED);
            $opened++;
        }
        return $opened;
    }

    /**
     * Add the walk to a feedback page, and take the mark out of the HTML.
     *
     * The final mark is the page the student lands on after the last tick.
     * It is not one of the steps. A teacher looking at their own page is unchanged.
     *
     * @param \local_unifiedgrader\adapter\base_adapter $adapter
     * @param int $userid
     * @param array $data Template data already merged with the rubric and guide
     * @return array
     */
    public static function present($adapter, int $userid, array $data): array {
        global $USER;

        $data['frictionactive'] = false;
        if ((int) $USER->id !== $userid || !self::is_pending_user($adapter, $userid)) {
            return $data;
        }

        $item = $adapter->get_grade_item();
        $row = $item ? self::pending_row((int) $item->id, $userid) : null;
        if (!$row) {
            return $data;
        }

        $steps = self::steps($adapter, $userid);
        if (!$steps || (int) $row->step >= count($steps)) {
            self::finish($row, self::STATE_COMPLETE);
            return $data;
        }

        $data['frictionactive'] = true;
        $data['frictionseconds'] = self::seconds();
        $data['frictionstep'] = (int) $row->step;
        $data['frictioninvitation'] = self::invitation();
        $data['frictionstepsjson'] = json_encode($steps, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        // The mark, the points, and the download stay off this page until the walk ends.
        $data['gradedisplay'] = '';
        $data['haspenalties'] = false;
        $data['penalties'] = [];
        $data['feedbackdownloadurl'] = '';
        $data['feedback'] = '';
        $data['hasfeedback'] = false;
        $data['attemptcontent'] = '';
        $data['hasattempt'] = false;
        $data['hasrubric'] = false;
        $data['rubriccriteria'] = [];
        $data['hasguide'] = false;
        $data['guidecriteria'] = [];
        $data['hasadvancedgrading'] = false;
        $data['hasfeedbackfiles'] = false;
        $data['feedbackfiles'] = [];
        $data['hasaggregatelabel'] = false;
        $data['aggregatelabel'] = '';
        $data['showrightcolumn'] = false;
        return $data;
    }

    /**
     * The parts of the feedback the student reads, in order, with the points left off.
     *
     * A criterion with neither a selection nor a comment is skipped. An activity
     * with no steps does not hold the mark.
     *
     * @param \local_unifiedgrader\adapter\base_adapter $adapter
     * @param int $userid
     * @return array<int,array{kind:string,title:string,body:string,levels:array,remark:string}>
     */
    public static function steps($adapter, int $userid): array {
        $gradedata = $adapter->get_grade_data($userid);
        $parsed = feedback_data_helper::parse_grading_data($gradedata, $adapter->get_context());
        $steps = [];

        foreach ($parsed['rubriccriteria'] as $criterion) {
            if (empty($criterion['hasselection']) && empty($criterion['hasremark'])) {
                continue;
            }
            $levels = [];
            foreach ($criterion['levels'] as $level) {
                $levels[] = [
                    'definition' => (string) ($level['definition'] ?? ''),
                    'selected' => !empty($level['selected']),
                ];
            }
            $steps[] = self::step(
                'rubric',
                self::plain_title((string) ($criterion['description'] ?? '')),
                '',
                $levels,
                !empty($criterion['hasremark']) ? (string) $criterion['remark'] : '',
            );
        }

        foreach ($parsed['guidecriteria'] as $criterion) {
            if (empty($criterion['hasscore']) && empty($criterion['hasremark'])) {
                continue;
            }
            $steps[] = self::step(
                'guide',
                trim((string) ($criterion['shortname'] ?? '')) ?: get_string('friction_overall', 'local_unifiedgrader'),
                (string) ($criterion['description'] ?? ''),
                [],
                !empty($criterion['hasremark']) ? (string) $criterion['remark'] : '',
            );
        }

        $steps = array_merge($steps, self::question_steps($gradedata, $adapter->get_context()));

        $feedback = (string) ($gradedata['feedback'] ?? '');
        if (self::has_text($feedback)) {
            $steps[] = self::step('feedback', get_string('friction_overall', 'local_unifiedgrader'), $feedback, [], '');
        }
        return $steps;
    }

    /**
     * Hold one student's cell for the mark that was just posted, or clear a pending walk.
     *
     * @param \local_unifiedgrader\adapter\base_adapter $adapter
     * @param \grade_item $item
     * @param int $userid
     * @param bool $hold
     */
    private static function settle_user($adapter, \grade_item $item, int $userid, bool $hold): void {
        global $DB;

        if (!$hold) {
            $DB->delete_records('local_unifiedgrader_friction', [
                'itemid' => $item->id,
                'userid' => $userid,
                'state' => self::STATE_PENDING,
            ]);
            return;
        }

        $grade = $DB->get_record('grade_grades', ['itemid' => $item->id, 'userid' => $userid]);
        $stamp = $grade ? (int) $grade->timemodified : 0;
        $existing = $grade ? $DB->get_record('local_unifiedgrader_friction', [
            'itemid' => $item->id,
            'userid' => $userid,
            'gradetimemodified' => $stamp,
        ]) : null;

        if ($existing && $existing->state !== self::STATE_PENDING) {
            $DB->delete_records_select(
                'local_unifiedgrader_friction',
                'itemid = :itemid AND userid = :userid AND id <> :id AND state = :state',
                [
                    'itemid' => $item->id,
                    'userid' => $userid,
                    'id' => $existing->id,
                    'state' => self::STATE_PENDING,
                ],
            );
            // Posted again for the same mark. The walk stays finished, and the
            // gradebook mark is shown even when a quiz item is still hidden.
            release::reveal_students($adapter, [$userid]);
            return;
        }
        if ($existing && $existing->state === self::STATE_PENDING) {
            release::set_cell($item, $userid, 1);
            return;
        }

        $steps = self::steps($adapter, $userid);
        $DB->delete_records('local_unifiedgrader_friction', [
            'itemid' => $item->id,
            'userid' => $userid,
        ]);
        if (!$steps) {
            return;
        }

        if (!$grade) {
            release::set_cell($item, $userid, 1);
            $grade = $DB->get_record('grade_grades', ['itemid' => $item->id, 'userid' => $userid]);
            $stamp = $grade ? (int) $grade->timemodified : time();
        }

        $DB->insert_record('local_unifiedgrader_friction', (object) [
            'cmid' => $adapter->get_cmid(),
            'userid' => $userid,
            'itemid' => $item->id,
            'gradetimemodified' => $stamp,
            'state' => self::STATE_PENDING,
            'step' => 0,
            'timecreated' => time(),
            'timecompleted' => 0,
        ]);
        release::set_cell($item, $userid, 1);
    }

    /**
     * Manually marked quiz questions that have a mark or a comment.
     *
     * @param array $gradedata
     * @param \context $context
     * @return array
     */
    private static function question_steps(array $gradedata, \context $context): array {
        if (empty($gradedata['gradingdefinition'])) {
            return [];
        }
        $definition = json_decode($gradedata['gradingdefinition'], true);
        if (!is_array($definition) || ($definition['method'] ?? '') !== 'quizmanual') {
            return [];
        }
        $fill = [];
        if (!empty($gradedata['rubricdata'])) {
            $rubric = json_decode($gradedata['rubricdata'], true);
            if (is_array($rubric)) {
                foreach ($rubric['criteria'] ?? [] as $id => $data) {
                    $fill[(int) $id] = is_array($data) ? $data : [];
                }
            }
        }

        $steps = [];
        $formatopts = ['context' => $context];
        foreach ($definition['criteria'] ?? [] as $criterion) {
            if (!is_array($criterion)) {
                continue;
            }
            $mark = $fill[(int) ($criterion['id'] ?? 0)] ?? [];
            $score = $mark['score'] ?? '';
            $remark = (string) ($mark['remark'] ?? '');
            $hasscore = $score !== '' && $score !== null;
            if (!$hasscore && !self::has_text($remark)) {
                continue;
            }
            $steps[] = self::step(
                'question',
                trim((string) ($criterion['shortname'] ?? '')),
                format_text((string) ($criterion['description'] ?? ''), FORMAT_PLAIN, $formatopts),
                [],
                self::has_text($remark) ? format_text($remark, FORMAT_PLAIN, $formatopts) : '',
            );
        }
        return $steps;
    }

    /**
     * One step, with no points on it.
     *
     * @param string $kind rubric, guide, question, or feedback
     * @param string $title
     * @param string $body
     * @param array $levels
     * @param string $remark
     * @return array{kind:string,title:string,body:string,levels:array,remark:string}
     */
    private static function step(string $kind, string $title, string $body, array $levels, string $remark): array {
        return [
            'kind' => $kind,
            'title' => $title,
            'body' => $body,
            'levels' => $levels,
            'remark' => $remark,
        ];
    }

    /**
     * Open the gradebook mark and record how the walk ended.
     *
     * The cell is opened, and a hidden grade item is shown for this student
     * only. A quiz review option is what hid the item. It is left as it is, so
     * the attempt review page does not change. A teacher hide deletes the
     * pending row before this runs, so it does not reopen that student.
     *
     * @param \stdClass $row
     * @param string $state complete or waived
     */
    private static function finish(\stdClass $row, string $state): void {
        global $DB;

        $adapter = adapter_factory::create((int) $row->cmid);
        release::reveal_students($adapter, [(int) $row->userid]);
        $DB->update_record('local_unifiedgrader_friction', (object) [
            'id' => $row->id,
            'state' => $state,
            'timecompleted' => time(),
        ]);
    }

    /**
     * Show gradebook marks for walks that already finished under a hidden item.
     *
     * A finished walk used to open the cell and leave a quiz item hidden. The
     * gradebook still hid that mark. Rows a teacher has since hidden are left
     * hidden: the cell is no longer open, or the stamp no longer matches.
     * Called from the upgrade, so it uses the grade item and not the module
     * list. Moodle refuses that list while an upgrade is running.
     *
     * @return int How many students were shown
     */
    public static function reveal_completed_hidden(): int {
        global $DB;

        $rows = $DB->get_records_select(
            'local_unifiedgrader_friction',
            'state <> :pending',
            ['pending' => self::STATE_PENDING],
        );
        $byitem = [];
        foreach ($rows as $row) {
            $item = \grade_item::fetch(['id' => $row->itemid]);
            if (!$item || !$item->is_hidden() || $item->itemtype !== 'mod') {
                continue;
            }
            if (!adapter_factory::is_supported($item->itemmodule)) {
                continue;
            }
            $grade = $DB->get_record('grade_grades', [
                'itemid' => $row->itemid,
                'userid' => $row->userid,
            ], 'id, hidden, timemodified');
            if (!$grade || (int) $grade->timemodified !== (int) $row->gradetimemodified) {
                continue;
            }
            $hidden = (int) $grade->hidden;
            if ($hidden === 1 || ($hidden > 1 && $hidden > time())) {
                continue;
            }
            $byitem[(int) $item->id][] = (int) $row->userid;
        }

        $shown = 0;
        foreach ($byitem as $itemid => $userids) {
            $item = \grade_item::fetch(['id' => $itemid]);
            if (!$item) {
                continue;
            }
            release::reveal_item($item, $userids);
            $shown += count($userids);
        }
        return $shown;
    }

    /**
     * The open walk for a grade item, if there is one.
     *
     * @param int $itemid
     * @param int $userid
     * @return \stdClass|null
     */
    private static function pending_row(int $itemid, int $userid): ?\stdClass {
        global $DB;

        $rows = $DB->get_records('local_unifiedgrader_friction', [
            'itemid' => $itemid,
            'userid' => $userid,
            'state' => self::STATE_PENDING,
        ], 'id DESC', '*', 0, 1);
        if (!$rows) {
            return null;
        }
        return reset($rows);
    }

    /**
     * The open walk for an activity.
     *
     * @param int $cmid
     * @param int $userid
     * @return \stdClass|null
     */
    private static function pending_row_for_cm(int $cmid, int $userid): ?\stdClass {
        global $DB;

        $rows = $DB->get_records('local_unifiedgrader_friction', [
            'cmid' => $cmid,
            'userid' => $userid,
            'state' => self::STATE_PENDING,
        ], 'id DESC', '*', 0, 1);
        if (!$rows) {
            return null;
        }
        return reset($rows);
    }

    /**
     * A heading with the markup removed.
     *
     * @param string $html
     * @return string
     */
    private static function plain_title(string $html): string {
        $text = trim(preg_replace('/\s+/', ' ', html_to_text($html, 0, false)) ?? '');
        if ($text === '') {
            return get_string('friction_overall', 'local_unifiedgrader');
        }
        return \core_text::substr($text, 0, 180);
    }

    /**
     * Whether HTML still contains something to read.
     *
     * @param string $html
     * @return bool
     */
    private static function has_text(string $html): bool {
        $text = html_to_text($html, 0, false);
        $text = str_replace("\xc2\xa0", ' ', $text);
        return trim($text) !== '';
    }

    /**
     * Seconds a student waits on a step before the tick appears. Zero is immediate.
     *
     * @return int
     */
    private static function seconds(): int {
        return self::config_int('friction_seconds', 20);
    }

    /**
     * The one sentence at the top of the walk.
     *
     * @return string
     */
    private static function invitation(): string {
        $custom = trim((string) (get_config('local_unifiedgrader', 'friction_invitation') ?: ''));
        if ($custom !== '') {
            return $custom;
        }
        return get_string('friction_invitation_default', 'local_unifiedgrader');
    }

    /**
     * A non-negative integer setting. An unset value uses the default, and zero is kept.
     *
     * @param string $name
     * @param int $default
     * @return int
     */
    private static function config_int(string $name, int $default): int {
        $value = get_config('local_unifiedgrader', $name);
        if ($value === false || $value === null || $value === '') {
            return $default;
        }
        return max(0, (int) $value);
    }
}
