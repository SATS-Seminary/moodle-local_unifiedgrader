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
 * External function: load a student's dates for the "Dates & extensions" dialogue.
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
use local_unifiedgrader\dates\student_dates;

/**
 * Loads a student's dates: class defaults, their extension and overrides, and lateness.
 */
class get_student_dates extends external_api {
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
        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'userid' => $userid]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);
        \core\session\manager::write_close();

        return (new student_dates($params['cmid'], $params['userid']))->load();
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
            'fullname' => new external_value(PARAM_TEXT, 'Student full name'),
            'profileimageurl' => new external_value(PARAM_URL, 'Student picture'),
            'activityname' => new external_value(PARAM_TEXT, 'Activity name'),
            'activitytype' => new external_value(PARAM_ALPHA, 'assign, quiz or forum'),
            'extension' => new external_single_structure([
                'available' => new external_value(PARAM_BOOL, 'Whether this activity has a due date to extend'),
                'editable' => new external_value(PARAM_BOOL, 'Whether the teacher may grant an extension'),
                'reason' => new external_value(PARAM_TEXT, 'Why extensions are unavailable, if they are'),
                'classvalue' => new external_value(PARAM_RAW, 'Class due date as a local date-time, or empty'),
                'classtext' => new external_value(PARAM_TEXT, 'Class due date for display'),
                'value' => new external_value(PARAM_RAW, 'Current extension as a local date-time, or empty'),
                'presets' => new external_multiple_structure(new external_single_structure([
                    'days' => new external_value(PARAM_INT, 'Days to extend by'),
                    'label' => new external_value(PARAM_TEXT, 'Button label'),
                ])),
            ]),
            'settings' => new external_multiple_structure(new external_single_structure([
                'key' => new external_value(PARAM_ALPHA, 'Setting key'),
                'label' => new external_value(PARAM_TEXT, 'Setting name'),
                'type' => new external_value(PARAM_ALPHA, 'datetime, duration or attempts'),
                'classtext' => new external_value(PARAM_TEXT, 'Class default for display'),
                'classvalue' => new external_value(PARAM_RAW, 'Class default as an input value'),
                'isextension' => new external_value(PARAM_BOOL, 'Whether this row is the due date, set by extension'),
                'overridden' => new external_value(PARAM_BOOL, 'Whether the student has their own value'),
                'value' => new external_value(PARAM_RAW, 'The student\'s value as an input value, or empty'),
                'valuetext' => new external_value(PARAM_TEXT, 'The student\'s value for display, or empty'),
                'editable' => new external_value(PARAM_BOOL, 'Whether the teacher may override it'),
                'follows' => new external_value(PARAM_BOOL, 'Whether it moves with an extension past it'),
                'note' => new external_value(PARAM_TEXT, 'A note shown instead of controls'),
            ])),
            'canremoveall' => new external_value(PARAM_BOOL, 'Whether the teacher may remove everything'),
            'preview' => self::preview_structure(),
        ]);
    }

    /**
     * The lateness preview, shared with preview_student_dates.
     *
     * @return external_single_structure
     */
    public static function preview_structure(): external_single_structure {
        return new external_single_structure([
            'tone' => new external_value(PARAM_ALPHA, 'success, warning, neutral or none'),
            'title' => new external_value(PARAM_TEXT, 'Headline'),
            'body' => new external_value(PARAM_TEXT, 'Explanation'),
            'extensiontext' => new external_value(PARAM_TEXT, 'The extension for display, or empty'),
            'moves' => new external_multiple_structure(new external_single_structure([
                'key' => new external_value(PARAM_ALPHA, 'Setting that moves with the extension'),
                'text' => new external_value(PARAM_TEXT, 'Its new date for display'),
            ])),
        ]);
    }
}
