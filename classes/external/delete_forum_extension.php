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
 * External function: delete a forum due date extension.
 *
 * Removes the user's forum extension from the local_unifiedgrader_fext table.
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

/**
 * Deletes a forum due date extension for a user.
 */
class delete_forum_extension extends external_api {

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
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);

        $DB->delete_records('local_unifiedgrader_fext', [
            'cmid' => $params['cmid'],
            'userid' => $params['userid'],
        ]);

        // Re-sync the late penalty and gradebook grade now that the effective due date changed.
        $adapter = \local_unifiedgrader\adapter\adapter_factory::create($params['cmid']);
        $lateinfo = $adapter->calculate_late_penalty($params['userid']);
        \local_unifiedgrader\penalty_manager::sync_late_penalty(
            $params['cmid'],
            $params['userid'],
            $lateinfo['percentage'] ?? null,
            $lateinfo['dayslate'] ?? 0,
        );
        $adapter->sync_gradebook_penalty($params['userid']);

        return ['success' => true];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the delete succeeded'),
        ]);
    }
}
