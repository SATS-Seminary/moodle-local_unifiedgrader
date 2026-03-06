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
 * External function: save library tag.
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
 * Creates or updates a comment library tag.
 */
class save_library_tag extends external_api {

    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'name' => new external_value(PARAM_TEXT, 'Tag name'),
            'tagid' => new external_value(PARAM_INT, 'Existing tag ID (0 = new)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param string $name
     * @param int $tagid
     * @return array
     */
    public static function execute(string $name, int $tagid = 0): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'name' => $name,
            'tagid' => $tagid,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);

        $id = comment_library_manager::save_tag($USER->id, $params['name'], $params['tagid']);

        return ['tagid' => $id];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'tagid' => new external_value(PARAM_INT, 'The saved tag ID'),
        ]);
    }
}
