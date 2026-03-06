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
 * External function: get grade data.
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
 * Returns current grade and feedback for a student.
 */
class get_grade_data extends external_api {

    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'userid' => new external_value(PARAM_INT, 'User ID'),
            'attemptnumber' => new external_value(
                PARAM_INT, 'Attempt number (0-based), -1 for latest', VALUE_DEFAULT, -1
            ),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @param int $userid
     * @param int $attemptnumber
     * @return array
     */
    public static function execute(int $cmid, int $userid, int $attemptnumber = -1): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
            'attemptnumber' => $attemptnumber,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);

        $adapter = adapter_factory::create($params['cmid']);

        if ($params['attemptnumber'] >= 0) {
            return $adapter->get_grade_data_for_attempt($params['userid'], $params['attemptnumber']);
        }
        return $adapter->get_grade_data($params['userid']);
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'grade' => new external_value(PARAM_FLOAT, 'Current grade', VALUE_OPTIONAL),
            'feedback' => new external_value(PARAM_RAW, 'Feedback HTML'),
            'feedbackformat' => new external_value(PARAM_INT, 'Feedback format'),
            'rubricdata' => new external_value(PARAM_RAW, 'Rubric fill data (JSON)', VALUE_OPTIONAL),
            'gradingdefinition' => new external_value(PARAM_RAW, 'Grading definition (JSON)', VALUE_OPTIONAL),
            'timegraded' => new external_value(PARAM_INT, 'Time graded'),
            'grader' => new external_value(PARAM_INT, 'Grader user ID'),
            'latepenaltypct' => new external_value(PARAM_INT, 'Quiz late penalty percentage from duedate plugin', VALUE_OPTIONAL),
        ]);
    }
}
