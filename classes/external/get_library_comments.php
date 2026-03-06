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
 * External function: get library comments.
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
use local_unifiedgrader\comment_library_manager;

/**
 * Returns comment library entries for the current teacher.
 */
class get_library_comments extends external_api {

    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'coursecode' => new external_value(PARAM_TEXT, 'Course code filter (empty = all)', VALUE_DEFAULT, ''),
            'tagid' => new external_value(PARAM_INT, 'Tag ID filter (0 = all)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param string $coursecode
     * @param int $tagid
     * @return array
     */
    public static function execute(string $coursecode = '', int $tagid = 0): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'coursecode' => $coursecode,
            'tagid' => $tagid,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        return comment_library_manager::get_comments($USER->id, $params['coursecode'], $params['tagid']);
    }

    /**
     * Return definition.
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Comment ID'),
                'userid' => new external_value(PARAM_INT, 'Owner user ID'),
                'coursecode' => new external_value(PARAM_TEXT, 'Course code'),
                'content' => new external_value(PARAM_RAW, 'Comment content'),
                'shared' => new external_value(PARAM_INT, '1 = shared'),
                'sortorder' => new external_value(PARAM_INT, 'Sort order'),
                'timecreated' => new external_value(PARAM_INT, 'Time created'),
                'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                'tagids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'Tag ID'),
                ),
            ]),
        );
    }
}
