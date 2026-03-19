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
 * External function: clear all user-level overrides and extensions.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_unifiedgrader\adapter\adapter_factory;

/**
 * Clears all user-level overrides and extensions for an activity.
 */
class clear_all_overrides extends external_api {

    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @param int $userid
     * @return array
     */
    public static function execute(int $cmid, int $userid): array {
        global $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);

        $cm = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        $adapter = adapter_factory::create($params['cmid']);

        if ($cm->modname === 'assign') {
            require_once($CFG->dirroot . '/mod/assign/locallib.php');

            // Delete override.
            if (has_capability('mod/assign:manageoverrides', $context)) {
                $adapter->delete_user_override($params['userid']);
            }

            // Clear extension.
            if (has_capability('mod/assign:grantextension', $context)) {
                [$course, $cminfo] = get_course_and_cm_from_cmid($params['cmid'], 'assign');
                $assign = new \assign($context, $cminfo, $course);
                $assign->save_user_extension($params['userid'], 0);
            }

            // Recalculate penalties.
            if (class_exists('\mod_assign\penalty\helper')) {
                \mod_assign\penalty\helper::apply_penalty_to_user($cm->instance, $params['userid']);
            }

        } else if ($cm->modname === 'quiz') {
            // Delete core override.
            if (has_capability('mod/quiz:manageoverrides', $context)) {
                $adapter->delete_user_override($params['userid']);
            }

            // Delete duedate extension.
            if (method_exists($adapter, 'delete_duedate_extension')) {
                $adapter->delete_duedate_extension($params['userid']);
            }

        } else if ($cm->modname === 'forum') {
            // Delete forum extension.
            if (method_exists($adapter, 'delete_forum_extension')) {
                $adapter->delete_forum_extension($params['userid']);
            }

            // Re-sync penalties.
            $lateinfo = $adapter->calculate_late_penalty($params['userid']);
            \local_unifiedgrader\penalty_manager::sync_late_penalty(
                $params['cmid'],
                $params['userid'],
                $lateinfo['percentage'] ?? null,
                $lateinfo['dayslate'] ?? 0,
            );
            $adapter->sync_gradebook_penalty($params['userid']);
        }

        return ['success' => true];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the clear succeeded'),
        ]);
    }
}
