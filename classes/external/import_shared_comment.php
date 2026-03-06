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
 * External function: import a shared comment into own library.
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
use local_unifiedgrader\comment_library_manager;

/**
 * Copies a shared comment into the teacher's own library.
 */
class import_shared_comment extends external_api {

    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'commentid' => new external_value(PARAM_INT, 'Shared comment ID to import'),
            'coursecode' => new external_value(PARAM_TEXT, 'Course code to assign'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $commentid
     * @param string $coursecode
     * @return array
     */
    public static function execute(int $commentid, string $coursecode): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'commentid' => $commentid,
            'coursecode' => $coursecode,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        $newid = comment_library_manager::import_shared_comment(
            $params['commentid'],
            $USER->id,
            $params['coursecode'],
        );

        return ['commentid' => $newid];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'commentid' => new external_value(PARAM_INT, 'The new comment ID'),
        ]);
    }
}
