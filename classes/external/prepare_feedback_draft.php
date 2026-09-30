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
 * External function: prepare feedback draft area for a student.
 *
 * Clears the shared draft area and repopulates it with the specified
 * student's existing feedback files, returning display-ready HTML
 * with rewritten draft URLs.
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
use local_unifiedgrader\adapter\adapter_factory;

/**
 * Prepares the draft area with a student's feedback files.
 */
class prepare_feedback_draft extends external_api {
    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
            'draftitemid' => new external_value(PARAM_INT, 'Draft area item ID'),
            'attemptnumber' => new external_value(
                PARAM_INT,
                'Attempt number (1-based for quiz), -1 for latest',
                VALUE_DEFAULT,
                -1,
            ),
            'questioncomments' => new external_value(
                PARAM_BOOL,
                'Also prepare a draft area for each manually marked quiz question comment',
                VALUE_DEFAULT,
                false,
            ),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @param int $userid
     * @param int $draftitemid
     * @param int $attemptnumber
     * @param bool $questioncomments
     * @return array
     */
    public static function execute(
        int $cmid,
        int $userid,
        int $draftitemid,
        int $attemptnumber = -1,
        bool $questioncomments = false,
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
            'draftitemid' => $draftitemid,
            'attemptnumber' => $attemptnumber,
            'questioncomments' => $questioncomments,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);

        // Release the PHP session lock so concurrent AJAX from the same
        // teacher does not serialize behind this request. This handler
        // does not write to $SESSION.
        \core\session\manager::write_close();

        $adapter = adapter_factory::create($params['cmid']);

        $result = $adapter->prepare_feedback_draft($params['userid'], $params['draftitemid'], $params['attemptnumber']);

        // Only requested when a student or attempt loads: each call makes new
        // draft areas, and the marking panel keeps its editors until then.
        if ($params['questioncomments'] && $adapter instanceof \local_unifiedgrader\adapter\quiz_adapter) {
            $result['questioncomments'] = $adapter->prepare_question_comment_drafts(
                $params['userid'],
                $params['attemptnumber'],
            );
        }

        return $result;
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'feedbackhtml' => new external_value(PARAM_RAW, 'Feedback HTML with draft URLs'),
            'questioncomments' => new external_multiple_structure(
                new external_single_structure([
                    'slot' => new external_value(PARAM_INT, 'Question slot'),
                    'draftitemid' => new external_value(PARAM_INT, 'Draft area item ID for the comment editor'),
                    'html' => new external_value(PARAM_RAW, 'Comment HTML with draft URLs'),
                ]),
                'Manually marked quiz question comments',
                VALUE_OPTIONAL,
            ),
        ]);
    }
}
