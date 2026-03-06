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
 * External function: get activity info.
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
 * Returns activity metadata for the grading interface.
 */
class get_activity_info extends external_api {

    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @return array
     */
    public static function execute(int $cmid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);
        $cmid = $params['cmid'];

        $context = \context_module::instance($cmid);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);

        $adapter = adapter_factory::create($cmid);
        return $adapter->get_activity_info();
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Course module ID'),
            'name' => new external_value(PARAM_TEXT, 'Activity name'),
            'type' => new external_value(PARAM_ALPHA, 'Activity type'),
            'duedate' => new external_value(PARAM_INT, 'Due date timestamp'),
            'cutoffdate' => new external_value(PARAM_INT, 'Cutoff date timestamp'),
            'maxgrade' => new external_value(PARAM_FLOAT, 'Maximum grade'),
            'intro' => new external_value(PARAM_RAW, 'Activity description HTML'),
            'gradingmethod' => new external_value(PARAM_TEXT, 'Grading method'),
            'gradingdisabled' => new external_value(
                PARAM_BOOL, 'Whole-forum grading disabled (grade type None)', VALUE_DEFAULT, false
            ),
            'teamsubmission' => new external_value(PARAM_BOOL, 'Team submission enabled'),
            'blindmarking' => new external_value(PARAM_BOOL, 'Blind marking enabled'),
            'canmanageoverrides' => new external_value(
                PARAM_BOOL, 'Whether teacher can manage overrides', VALUE_DEFAULT, false
            ),
            'hasduedateplugin' => new external_value(
                PARAM_BOOL, 'Whether quizaccess_duedate plugin is installed', VALUE_DEFAULT, false
            ),
            'canmanageextensions' => new external_value(
                PARAM_BOOL, 'Whether teacher can manage duedate extensions', VALUE_DEFAULT, false
            ),
            'maxattempts' => new external_value(
                PARAM_INT, 'Maximum attempts (-1=unlimited, 1=single)', VALUE_DEFAULT, 1
            ),
            'gradepenaltyenabled' => new external_value(
                PARAM_BOOL, 'Whether grade penalties are enabled for this activity', VALUE_DEFAULT, false
            ),
        ]);
    }
}
