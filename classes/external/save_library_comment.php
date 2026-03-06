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
 * External function: save library comment.
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
 * Creates or updates a comment library entry.
 */
class save_library_comment extends external_api {

    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'coursecode' => new external_value(PARAM_TEXT, 'Course code'),
            'content' => new external_value(PARAM_RAW, 'Comment content'),
            'tagids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Tag ID'),
                'Tag IDs to assign',
                VALUE_DEFAULT,
                [],
            ),
            'shared' => new external_value(PARAM_INT, '1 = shared, 0 = private', VALUE_DEFAULT, 0),
            'commentid' => new external_value(PARAM_INT, 'Existing comment ID (0 = new)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param string $coursecode
     * @param string $content
     * @param array $tagids
     * @param int $shared
     * @param int $commentid
     * @return array
     */
    public static function execute(
        string $coursecode,
        string $content,
        array $tagids = [],
        int $shared = 0,
        int $commentid = 0,
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'coursecode' => $coursecode,
            'content' => $content,
            'tagids' => $tagids,
            'shared' => $shared,
            'commentid' => $commentid,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        $id = comment_library_manager::save_comment(
            $USER->id,
            $params['coursecode'],
            $params['content'],
            $params['tagids'],
            $params['shared'],
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
