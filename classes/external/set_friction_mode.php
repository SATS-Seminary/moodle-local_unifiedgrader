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
 * External function: set an activity's Friction Feedback mode.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_unifiedgrader\friction\service;

/**
 * Stores whether one activity follows the site, forces the walk on, or forces it off.
 */
class set_friction_mode extends external_api {
    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'mode' => new external_value(PARAM_ALPHA, 'inherit, on, or off'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @param string $mode
     * @return array{mode:string,enabled:bool}
     */
    public static function execute(int $cmid, string $mode): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'mode' => $mode,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);
        \core\session\manager::write_close();

        service::set_mode($params['cmid'], $params['mode']);

        return [
            'mode' => service::mode($params['cmid']),
            'enabled' => service::applies($params['cmid']),
        ];
    }

    /**
     * Return definition.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'mode' => new external_value(PARAM_ALPHA, 'inherit, on, or off'),
            'enabled' => new external_value(PARAM_BOOL, 'Whether a post on this activity holds the mark'),
        ]);
    }
}
