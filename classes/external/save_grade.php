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
 * External function: save grade.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_unifiedgrader\adapter\adapter_factory;
use local_unifiedgrader\penalty_manager;

/**
 * Saves a grade and feedback for a student.
 */
class save_grade extends external_api {

    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'userid' => new external_value(PARAM_INT, 'User ID'),
            'grade' => new external_value(PARAM_FLOAT, 'Grade value (-1 for no grade)', VALUE_DEFAULT, -1),
            'feedback' => new external_value(PARAM_RAW, 'Feedback HTML', VALUE_DEFAULT, ''),
            'feedbackformat' => new external_value(PARAM_INT, 'Feedback format', VALUE_DEFAULT, FORMAT_HTML),
            'advancedgradingdata' => new external_value(PARAM_RAW, 'Advanced grading data (JSON)', VALUE_DEFAULT, ''),
            'draftitemid' => new external_value(PARAM_INT, 'Draft area item ID for feedback files', VALUE_DEFAULT, 0),
            'feedbackfilesdraftid' => new external_value(
                PARAM_INT, 'Draft area item ID for feedback files (assignfeedback_file)', VALUE_DEFAULT, 0,
            ),
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
     * @param float $grade
     * @param string $feedback
     * @param int $feedbackformat
     * @param string $advancedgradingdata
     * @return array
     */
    public static function execute(
        int $cmid,
        int $userid,
        float $grade = -1,
        string $feedback = '',
        int $feedbackformat = FORMAT_HTML,
        string $advancedgradingdata = '',
        int $draftitemid = 0,
        int $feedbackfilesdraftid = 0,
        int $attemptnumber = -1,
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
            'grade' => $grade,
            'feedback' => $feedback,
            'feedbackformat' => $feedbackformat,
            'advancedgradingdata' => $advancedgradingdata,
            'draftitemid' => $draftitemid,
            'feedbackfilesdraftid' => $feedbackfilesdraftid,
            'attemptnumber' => $attemptnumber,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);

        $adapter = adapter_factory::create($params['cmid']);

        $gradevalue = $params['grade'] >= 0 ? $params['grade'] : null;

        // Apply penalty deductions to numeric grades (not scale, not quiz).
        // Quiz grades are computed by the question engine; penalties are not applicable.
        if ($gradevalue !== null) {
            $activityinfo = $adapter->get_activity_info();
            if (empty($activityinfo['usescale']) && ($activityinfo['type'] ?? '') !== 'quiz') {
                $maxgrade = (float) ($activityinfo['maxgrade'] ?? 100);
                $deduction = penalty_manager::get_total_deduction(
                    $params['cmid'],
                    $params['userid'],
                    $maxgrade,
                );
                if ($deduction > 0) {
                    $gradevalue = max(0, $gradevalue - $deduction);
                }
            }
        }

        $advanceddata = [];
        if (!empty($params['advancedgradingdata'])) {
            $advanceddata = json_decode($params['advancedgradingdata'], true) ?: [];
        }

        $success = $adapter->save_grade(
            $params['userid'],
            $gradevalue,
            $params['feedback'],
            $params['feedbackformat'],
            $advanceddata,
            $params['draftitemid'],
            $params['feedbackfilesdraftid'],
            $params['attemptnumber'],
        );

        return ['success' => $success];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the grade was saved successfully'),
        ]);
    }
}
