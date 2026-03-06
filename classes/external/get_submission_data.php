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
 * External function: get submission data.
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

/**
 * Returns submission content for a specific student.
 */
class get_submission_data extends external_api {

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

        // Use attempt-aware method when a specific attempt is requested.
        if ($params['attemptnumber'] >= 0) {
            $data = $adapter->get_submission_data_for_attempt($params['userid'], $params['attemptnumber']);
        } else {
            $data = $adapter->get_submission_data($params['userid']);
        }

        $data['plagiarismlinks'] = $adapter->get_plagiarism_links($params['userid']);

        // Add override info.
        $override = $adapter->get_user_override($params['userid']);
        $data['hasoverride'] = $override !== null;
        $data['overrideid'] = $override ? (int) $override['id'] : 0;

        // Effective due date for this user (accounts for overrides and extensions).
        $data['effectiveduedate'] = $adapter->get_effective_duedate($params['userid']);

        // Include the list of all attempts for this user.
        $data['attempts'] = $adapter->get_attempts($params['userid']);

        return $data;
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'userid' => new external_value(PARAM_INT, 'User ID'),
            'status' => new external_value(PARAM_TEXT, 'Submission status'),
            'content' => new external_value(PARAM_RAW, 'Rendered submission content HTML'),
            'hascontent' => new external_value(
                PARAM_BOOL, 'Whether non-file submission plugins produced content', VALUE_DEFAULT, false
            ),
            'files' => new external_multiple_structure(
                new external_single_structure([
                    'fileid' => new external_value(PARAM_INT, 'File ID'),
                    'filename' => new external_value(PARAM_TEXT, 'File name'),
                    'mimetype' => new external_value(PARAM_TEXT, 'MIME type'),
                    'filesize' => new external_value(PARAM_INT, 'File size in bytes'),
                    'url' => new external_value(PARAM_URL, 'Download URL'),
                    'previewurl' => new external_value(PARAM_URL, 'Inline preview URL'),
                    'convertible' => new external_value(PARAM_BOOL, 'Whether file can be converted to PDF'),
                ]),
            ),
            'onlinetext' => new external_value(PARAM_RAW, 'Online text submission'),
            'timecreated' => new external_value(PARAM_INT, 'Time created'),
            'timemodified' => new external_value(PARAM_INT, 'Time modified'),
            'attemptnumber' => new external_value(PARAM_INT, 'Attempt number'),
            'commentcount' => new external_value(PARAM_INT, 'Number of submission comments', VALUE_DEFAULT, 0),
            'locked' => new external_value(PARAM_BOOL, 'Whether submission changes are locked', VALUE_DEFAULT, false),
            'plagiarismlinks' => new external_multiple_structure(
                new external_single_structure([
                    'label' => new external_value(PARAM_TEXT, 'Label for the plagiarism link (filename or Online text)'),
                    'html' => new external_value(PARAM_RAW, 'Rendered plagiarism HTML from the plugin'),
                ]),
                'Plagiarism report links from enabled plagiarism plugins',
                VALUE_DEFAULT,
                [],
            ),
            'hasoverride' => new external_value(PARAM_BOOL, 'Whether user has an override', VALUE_DEFAULT, false),
            'overrideid' => new external_value(PARAM_INT, 'Override ID (0 if none)', VALUE_DEFAULT, 0),
            'effectiveduedate' => new external_value(
                PARAM_INT, 'Effective due date for this user (accounts for overrides/extensions)', VALUE_DEFAULT, 0
            ),
            'attempts' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Attempt ID (same as attemptnumber)'),
                    'attemptnumber' => new external_value(PARAM_INT, 'Attempt number (0-based)'),
                    'status' => new external_value(PARAM_TEXT, 'Submission status'),
                    'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                    'graded' => new external_value(PARAM_BOOL, 'Whether this attempt has been graded'),
                ]),
                'List of submission attempts',
                VALUE_DEFAULT,
                [],
            ),
        ]);
    }
}
