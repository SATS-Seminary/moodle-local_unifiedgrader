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
 * External function: set grades posted status.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_unifiedgrader\access;
use local_unifiedgrader\adapter\adapter_factory;
use local_unifiedgrader\grades\release;

/**
 * Posts or hides grades for an activity, for one student, for groups, or for the class.
 *
 * Callers that send only cmid and hidden keep the class-wide behaviour.
 *
 * The hidden parameter supports three modes:
 *   0 = post grades (visible to students immediately)
 *   1 = hide grades (permanently hidden until manually posted)
 *   >1 = Unix timestamp (hidden until that date, then auto-visible)
 */
class set_grades_posted extends external_api {
    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'hidden' => new external_value(
                PARAM_INT,
                '0 = post (visible), 1 = hide permanently, or Unix timestamp = hide until',
            ),
            'scope' => new external_value(
                PARAM_ALPHA,
                'class (default), user, or groups',
                VALUE_DEFAULT,
                'class',
            ),
            'userid' => new external_value(
                PARAM_INT,
                'Student user id when scope is user',
                VALUE_DEFAULT,
                0,
            ),
            'groupids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Group id'),
                'Group ids when scope is groups',
                VALUE_DEFAULT,
                [],
            ),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @param int $hidden
     * @param string $scope class, user, or groups
     * @param int $userid
     * @param int[] $groupids
     * @return array
     */
    public static function execute(
        int $cmid,
        int $hidden,
        string $scope = 'class',
        int $userid = 0,
        array $groupids = [],
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'hidden' => $hidden,
            'scope' => $scope,
            'userid' => $userid,
            'groupids' => $groupids,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);

        // Release the PHP session lock so concurrent AJAX from the same
        // teacher does not serialize behind this request. This handler
        // does not write to $SESSION.
        \core\session\manager::write_close();

        // Block quiz grade posting unless the admin setting is enabled.
        $cm = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        if ($cm->modname === 'quiz' && empty(get_config('local_unifiedgrader', 'enable_quiz_post_grades'))) {
            throw new \moodle_exception('quiz_post_grades_disabled', 'local_unifiedgrader');
        }

        // Validate: hidden must be 0, 1, or a future timestamp.
        $hidden = $params['hidden'];
        if ($hidden < 0) {
            $hidden = 1;
        }

        $adapter = adapter_factory::create($params['cmid']);
        $userids = self::resolve_userids($adapter, $cm, $context, $params['scope'], (int) $params['userid'], $params['groupids']);
        if ($userids === null) {
            // A group whose members include no student. Report the status and change nothing.
            return self::format_status($adapter->posting_status());
        }

        $status = $adapter->set_grades_posted($hidden, $userids);
        return self::format_status($status);
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation succeeded'),
            'posted' => new external_value(PARAM_BOOL, 'Whether every visible student has a released grade'),
            'hidden' => new external_value(
                PARAM_INT,
                'Raw hidden value of the grade item: 0 = visible, 1 = always hidden, >1 = hidden-until timestamp',
            ),
            'partial' => new external_value(PARAM_BOOL, 'Whether some, but not all, students have a released grade'),
            'postedcount' => new external_value(PARAM_INT, 'How many students have a released grade'),
            'total' => new external_value(PARAM_INT, 'How many students this user can post for'),
        ]);
    }

    /**
     * The students a scope names.
     *
     * An empty array is the whole class. Null means the request named groups
     * that contain no gradebook student, and the caller should change nothing.
     *
     * A separate-groups teacher who cannot access every group cannot post the
     * class. That check lives here, not in the adapter, so a test can still
     * post the class through the adapter directly.
     *
     * @param \local_unifiedgrader\adapter\base_adapter $adapter
     * @param \stdClass $cm
     * @param \context_module $context
     * @param string $scope
     * @param int $userid
     * @param array $groupids
     * @return int[]|null
     */
    private static function resolve_userids(
        $adapter,
        \stdClass $cm,
        \context_module $context,
        string $scope,
        int $userid,
        array $groupids,
    ): ?array {
        if ($scope === 'class') {
            if (
                (int) groups_get_activity_groupmode($cm) === SEPARATEGROUPS
                && !has_capability('moodle/site:accessallgroups', $context)
            ) {
                throw new \moodle_exception('post_grades_class_denied', 'local_unifiedgrader');
            }
            return [];
        }

        if ($scope === 'user') {
            access::require_student_access($context, $userid);
            if (!in_array($userid, release::student_ids($adapter), true)) {
                throw new \moodle_exception('nopermission', 'local_unifiedgrader');
            }
            return [$userid];
        }

        if ($scope !== 'groups') {
            throw new \moodle_exception('invalidparameter');
        }

        $requested = array_values(array_unique(array_filter(array_map('intval', $groupids), fn(int $id): bool => $id > 0)));
        if (!$requested) {
            throw new \moodle_exception('invalidparameter');
        }
        $visible = access::visible_group_ids($cm, $context, $requested);
        if ($visible === null) {
            throw new \moodle_exception('nopermission', 'local_unifiedgrader');
        }
        $userids = release::students_in_groups($adapter, $visible);
        return $userids ?: null;
    }

    /**
     * The web-service return, without the adapter's internal keys.
     *
     * @param array $status
     * @return array
     */
    private static function format_status(array $status): array {
        return [
            'success' => true,
            'posted' => (bool) $status['posted'],
            'hidden' => (int) $status['hidden'],
            'partial' => (bool) $status['partial'],
            'postedcount' => (int) $status['postedcount'],
            'total' => (int) $status['total'],
        ];
    }
}
