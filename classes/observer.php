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
 * Event observer callbacks for local_unifiedgrader.
 *
 * These observers keep the plugin in sync when grades or submissions change
 * through native activity UIs. In Phase 1 they are stubs for future
 * cache invalidation and real-time update logic.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader;

/**
 * Event observer callbacks for keeping the plugin in sync with native activity UIs.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Handle assignment submission graded event.
     *
     * @param \mod_assign\event\submission_graded $event
     */
    public static function handle_submission_graded(\mod_assign\event\submission_graded $event): void {
        // Future: invalidate cached participant/grade data for this activity.
    }

    /**
     * Handle core user graded event.
     *
     * From Moodle 5.3, puts the student's penalty deduction back after an
     * activity pushes a new raw grade (which resets it in the gradebook). This
     * is what keeps penalties in place when a mark is saved outside the grader:
     * core's assignment grading page, quiz auto-grading, a regrade, manual
     * question grading, or whole-forum grading.
     *
     * @param \core\event\user_graded $event
     */
    public static function handle_user_graded(\core\event\user_graded $event): void {
        global $CFG;

        if (empty($event->relateduserid) || grades\release::is_sealing()) {
            return;
        }
        // Decided before the penalty resync. That resync writes another grade
        // history row and fires this event again, and a new cell only looks new
        // until then. The seal itself runs on Moodle 5.0 as well, where the
        // penalty resync below does not.
        $seal = grades\release::should_seal($event);

        if (penalty\compat::unified() && !penalty\service::is_syncing()) {
            require_once($CFG->libdir . '/gradelib.php');

            $grade = $event->get_grade();
            $gradeitem = \grade_item::fetch(['id' => $grade->itemid]);
            if ($gradeitem && $gradeitem->itemtype === 'mod' && penalty\activity_settings::supports($gradeitem->itemmodule)) {
                $cm = get_coursemodule_from_instance(
                    $gradeitem->itemmodule,
                    $gradeitem->iteminstance,
                    $gradeitem->courseid,
                );
                if ($cm) {
                    penalty\service::resync((int) $cm->id, (int) $event->relateduserid, false);
                }
            }
        }

        if ($seal) {
            grades\release::seal($event->get_grade());
        }
    }

    /**
     * Resync one student after something changed their effective due date.
     *
     * Registered for assignment extensions and user overrides on assignments
     * and quizzes. Moodle 5.3+ only.
     *
     * @param \core\event\base $event An event with a module context and a related user.
     */
    public static function handle_user_duedate_changed(\core\event\base $event): void {
        if (!penalty\compat::unified() || empty($event->relateduserid) || empty($event->contextinstanceid)) {
            return;
        }
        penalty\service::resync((int) $event->contextinstanceid, (int) $event->relateduserid);
    }

    /**
     * Work out the late penalty as soon as a quiz attempt is submitted.
     *
     * An attempt that still needs manual grading may not change the quiz grade,
     * so no user_graded event follows it; this makes sure its late penalty is
     * recorded all the same. The raw grade is not pushed: the quiz saves it
     * itself. Moodle 5.3+ only.
     *
     * @param \mod_quiz\event\attempt_submitted $event
     */
    public static function handle_attempt_submitted(\mod_quiz\event\attempt_submitted $event): void {
        if (!penalty\compat::unified() || empty($event->relateduserid) || empty($event->contextinstanceid)) {
            return;
        }
        penalty\service::resync((int) $event->contextinstanceid, (int) $event->relateduserid, false);
    }

    /**
     * Resync a group's members in the background after a group override changed.
     *
     * Moodle 5.3+ only.
     *
     * @param \core\event\base $event A group override event, with other['groupid'].
     */
    public static function handle_group_override_changed(\core\event\base $event): void {
        if (!penalty\compat::unified() || empty($event->contextinstanceid)) {
            return;
        }
        $groupid = (int) ($event->other['groupid'] ?? 0);
        $userids = $groupid ? array_keys(groups_get_members($groupid, 'u.id')) : [];
        if ($userids) {
            penalty\service::resync_later((int) $event->contextinstanceid, $userids);
        }
    }

    /**
     * Resync an activity's students in the background after its settings changed.
     *
     * A changed due date or penalty switch can change every student's penalty.
     * The resync is for Moodle 5.3+ only; the caches are cleared on any version.
     *
     * @param \core\event\course_module_updated $event
     */
    public static function handle_course_module_updated(\core\event\course_module_updated $event): void {
        // What is cached about an activity is read from its settings.
        penalty\activity_settings::invalidate((int) $event->objectid);
        if (($event->other['modulename'] ?? '') === 'forum') {
            forum_helper::invalidate((int) $event->objectid);
        }

        if (!penalty\compat::unified() || !penalty\activity_settings::supports($event->other['modulename'] ?? '')) {
            return;
        }
        penalty\service::resync_later((int) $event->objectid);
    }

    /**
     * Handle assignment submission created event.
     *
     * @param \mod_assign\event\submission_created $event
     */
    public static function handle_submission_created(\mod_assign\event\submission_created $event): void {
        // Future: invalidate cached participant data for this activity.
    }

    /**
     * Handle assignment submission updated event.
     *
     * @param \mod_assign\event\submission_updated $event
     */
    public static function handle_submission_updated(\mod_assign\event\submission_updated $event): void {
        // Future: invalidate cached submission data for this activity.
    }

    /**
     * Handle assignment submission removed event.
     *
     * Prunes the segment-anchored comments tied to the removed submission so a
     * retracted submission leaves no orphaned grader comments. A single batched
     * delete keyed by the submission id (no per-row queries).
     *
     * @param \mod_assign\event\submission_removed $event
     */
    public static function handle_submission_removed(\mod_assign\event\submission_removed $event): void {
        global $DB;

        $submissionid = (int) $event->objectid;
        if ($submissionid <= 0) {
            return;
        }
        $DB->delete_records('local_unifiedgrader_segcomment', ['submissionid' => $submissionid]);
    }

    /**
     * Handle course module deleted event.
     *
     * When an assignment module is deleted, prune its segment-anchored comments
     * (keyed by the course-module id) so they cannot linger. A single batched
     * delete (no per-row queries). Gated to assign modules; segcomment rows only
     * ever carry assign course-module ids.
     *
     * @param \core\event\course_module_deleted $event
     */
    public static function handle_course_module_deleted(\core\event\course_module_deleted $event): void {
        global $DB;

        $cmid = (int) $event->objectid;
        if ($cmid <= 0) {
            return;
        }

        // The late penalty switch is stored for any supported activity.
        penalty\activity_settings::delete($cmid);

        if (($event->other['modulename'] ?? '') !== 'assign') {
            return;
        }
        $DB->delete_records('local_unifiedgrader_segcomment', ['cmid' => $cmid]);
    }

    /**
     * Keep a quiz grade item visible once a feedback walk has revealed it.
     *
     * The quiz writes the item's hidden flag from its review options on every
     * grade sync. That flag covers every cell, so the gradebook would hide a
     * mark the student has already read. Review options are left alone. A
     * class hide closes the cells first, and this does not open them again.
     *
     * @param \core\event\grade_item_updated $event
     */
    public static function handle_grade_item_updated(\core\event\grade_item_updated $event): void {
        if (grades\release::is_sealing()) {
            return;
        }
        $item = $event->get_grade_item();
        if (!$item || $item->itemtype !== 'mod' || $item->itemmodule !== 'quiz' || !$item->is_hidden()) {
            return;
        }
        $released = friction\service::released_userids((int) $item->id);
        if (!$released) {
            return;
        }
        $cm = get_coursemodule_from_instance('quiz', $item->iteminstance, $item->courseid, false, IGNORE_MISSING);
        if (!$cm || !adapter\adapter_factory::is_supported('quiz')) {
            return;
        }
        grades\release::reveal_students(adapter\adapter_factory::create((int) $cm->id), $released);
    }
}
