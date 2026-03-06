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
 * External function: get penalties.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_unifiedgrader\adapter\adapter_factory;
use local_unifiedgrader\penalty_manager;

/**
 * Returns grade penalties for a student in an activity.
 */
class get_penalties extends external_api {

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
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);

        // Auto-sync late penalty for forums before returning penalties.
        $cm = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        if ($cm->modname === 'forum') {
            $adapter = adapter_factory::create($params['cmid']);
            $lateinfo = $adapter->calculate_late_penalty($params['userid']);
            penalty_manager::sync_late_penalty(
                $params['cmid'],
                $params['userid'],
                $lateinfo['percentage'] ?? null,
                $lateinfo['dayslate'] ?? 0,
            );
        }

        return penalty_manager::get_penalties($params['cmid'], $params['userid']);
    }

    /**
     * Return definition.
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Penalty ID'),
                'cmid' => new external_value(PARAM_INT, 'Course module ID'),
                'userid' => new external_value(PARAM_INT, 'Student user ID'),
                'authorid' => new external_value(PARAM_INT, 'Author user ID'),
                'category' => new external_value(PARAM_ALPHANUMEXT, 'Penalty category'),
                'label' => new external_value(PARAM_TEXT, 'Custom label'),
                'percentage' => new external_value(PARAM_INT, 'Penalty percentage'),
                'timecreated' => new external_value(PARAM_INT, 'Time created'),
                'timemodified' => new external_value(PARAM_INT, 'Time modified'),
            ]),
        );
    }
}
