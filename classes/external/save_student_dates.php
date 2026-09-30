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
 * External function: save a student's extension and overrides.
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
 * Saves the "Dates & extensions" dialogue.
 */
class save_student_dates extends external_api {
    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
            'extension' => new external_value(PARAM_RAW, 'Extension as a local date-time (YYYY-MM-DDTHH:MM), or empty for none'),
            'overrides' => new external_multiple_structure(
                new external_single_structure([
                    'key' => new external_value(PARAM_ALPHA, 'Setting key'),
                    'value' => new external_value(PARAM_RAW, 'Local date-time, minutes, or number of attempts'),
                ]),
                'The student\'s own values; a setting left out goes back to the class default',
                VALUE_DEFAULT,
                [],
            ),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @param int $userid
     * @param string $extension
     * @param array $overrides
     * @return array
     */
    public static function execute(int $cmid, int $userid, string $extension, array $overrides = []): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid, 'userid' => $userid, 'extension' => $extension, 'overrides' => $overrides,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);

        $values = [];
        foreach ($params['overrides'] as $override) {
            $values[$override['key']] = $override['value'];
        }

        return (new student_dates($params['cmid'], $params['userid']))->save(trim($params['extension']), $values);
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'askrecalc' => new external_value(PARAM_BOOL, 'Whether to offer the teacher a penalty recalculation'),
        ]);
    }
}
