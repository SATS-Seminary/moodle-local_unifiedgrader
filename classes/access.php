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

namespace local_unifiedgrader;

/**
 * Access checks shared by the grader's pages and web services.
 *
 * A capability says what a teacher may do in an activity. These checks say
 * to whom: every endpoint that takes a student's user ID must also confirm
 * that the teacher may work with that student.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access {
    /**
     * Whether the current user may work with a student in an activity.
     *
     * The student must be enrolled in the course (a suspended enrolment
     * counts, so work already graded stays reachable). In separate groups
     * mode, a teacher who cannot access all groups must share one of the
     * activity's groups with the student, as on the activity's own pages.
     *
     * @param \context_module $context The activity's context.
     * @param int $userid The student.
     * @return bool
     */
    public static function can_access_student(\context_module $context, int $userid): bool {
        if ($userid <= 0 || !is_enrolled($context, $userid)) {
            return false;
        }
        [$course, $cm] = get_course_and_cm_from_cmid($context->instanceid);
        return groups_user_groups_visible($course, $userid, $cm);
    }

    /**
     * Stop unless the current user may work with a student in an activity.
     *
     * Call it after the capability check, in every page and web service
     * that reads or changes one student's data on a teacher's behalf.
     *
     * @param \context_module $context The activity's context.
     * @param int $userid The student.
     * @throws \moodle_exception If they may not.
     */
    public static function require_student_access(\context_module $context, int $userid): void {
        if (!self::can_access_student($context, $userid)) {
            throw new \moodle_exception('nopermission', 'local_unifiedgrader');
        }
    }

    /**
     * The groups a participant list may be drawn from.
     *
     * In separate groups mode, a teacher who cannot access all groups is
     * confined to their own: groups they ask for are narrowed to those, and
     * asking for everyone gives everyone in their groups.
     *
     * @param \cm_info|\stdClass $cm The activity.
     * @param \context_module $context Its context.
     * @param int[] $groupids The groups asked for; empty for everyone.
     * @return int[]|null Group IDs (empty for everyone), or null when the
     *         teacher may see nobody.
     */
    public static function visible_group_ids($cm, \context_module $context, array $groupids): ?array {
        global $USER;

        $groupids = array_values(array_unique(array_filter(array_map('intval', $groupids), fn($id) => $id > 0)));
        if (
            (int) groups_get_activity_groupmode($cm) !== SEPARATEGROUPS
            || has_capability('moodle/site:accessallgroups', $context)
        ) {
            return $groupids;
        }

        $own = array_map('intval', array_keys(groups_get_activity_allowed_groups($cm, $USER->id)));
        if (!$own) {
            return null;
        }
        if (!$groupids) {
            return $own;
        }
        $allowed = array_values(array_intersect($groupids, $own));
        return $allowed ?: null;
    }

    /**
     * Whether the current user may use the comment library.
     *
     * The library belongs to people who grade: anyone who holds the grading
     * capability in at least one course, or site-wide. The answer is kept
     * for ten minutes in the session (see db/caches.php), since the grader
     * asks several times each time it opens.
     *
     * @return bool
     */
    public static function can_use_library(): bool {
        global $USER;

        if (!isloggedin() || isguestuser()) {
            return false;
        }

        // Only a yes is kept, so someone newly made a teacher is not kept waiting.
        $cache = \cache::make('local_unifiedgrader', 'libraryaccess');
        if ($cache->get((int) $USER->id)) {
            return true;
        }

        $allowed = has_capability('local/unifiedgrader:grade', \context_system::instance())
            || !empty(get_user_capability_course('local/unifiedgrader:grade', null, true, '', '', 1));
        if ($allowed) {
            $cache->set((int) $USER->id, 1);
        }
        return $allowed;
    }

    /**
     * Stop unless the current user may use the comment library.
     *
     * @throws \moodle_exception If they may not.
     */
    public static function require_library_access(): void {
        if (!self::can_use_library()) {
            throw new \moodle_exception('nopermission', 'local_unifiedgrader');
        }
    }
}
