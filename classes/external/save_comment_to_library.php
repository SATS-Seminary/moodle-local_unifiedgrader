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
 * External function: save comment to library.
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
use local_unifiedgrader\comment_library;

/**
 * Adds or updates a comment in the library.
 */
class save_comment_to_library extends external_api {

    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID (0 for global)'),
            'content' => new external_value(PARAM_RAW, 'Comment content'),
            'commentid' => new external_value(PARAM_INT, 'Existing comment ID (0 for new)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $courseid
     * @param string $content
     * @param int $commentid
     * @return array
     */
    public static function execute(int $courseid, string $content, int $commentid = 0): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'content' => $content,
            'commentid' => $commentid,
        ]);

        if ($params['courseid'] > 0) {
            $context = \context_course::instance($params['courseid']);
            self::validate_context($context);
        }

        $id = comment_library::save_comment(
            $USER->id,
            $params['courseid'],
            $params['content'],
            $params['commentid'],
        );

        return ['commentid' => $id];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'commentid' => new external_value(PARAM_INT, 'The saved comment ID'),
        ]);
    }
}
