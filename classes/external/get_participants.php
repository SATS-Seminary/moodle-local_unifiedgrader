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
 * External function: get participants.
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
 * Returns a filtered and sorted participant list.
 */
class get_participants extends external_api {

    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'status' => new external_value(PARAM_ALPHA, 'Filter by status', VALUE_DEFAULT, 'all'),
            'group' => new external_value(PARAM_INT, 'Filter by group ID', VALUE_DEFAULT, 0),
            'search' => new external_value(PARAM_TEXT, 'Search string', VALUE_DEFAULT, ''),
            'sort' => new external_value(PARAM_ALPHA, 'Sort field', VALUE_DEFAULT, 'fullname'),
            'sortdir' => new external_value(PARAM_ALPHA, 'Sort direction (asc/desc)', VALUE_DEFAULT, 'asc'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @param string $status
     * @param int $group
     * @param string $search
     * @param string $sort
     * @param string $sortdir
     * @return array
     */
    public static function execute(
        int $cmid,
        string $status = 'all',
        int $group = 0,
        string $search = '',
        string $sort = 'fullname',
        string $sortdir = 'asc',
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'status' => $status,
            'group' => $group,
            'search' => $search,
            'sort' => $sort,
            'sortdir' => $sortdir,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);

        $adapter = adapter_factory::create($params['cmid']);
        return $adapter->get_participants([
            'status' => $params['status'],
            'group' => $params['group'],
            'search' => $params['search'],
            'sort' => $params['sort'],
            'sortdir' => $params['sortdir'],
        ]);
    }

    /**
     * Return definition.
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'User ID'),
                'fullname' => new external_value(PARAM_TEXT, 'Full name'),
                'email' => new external_value(PARAM_TEXT, 'Email address'),
                'profileimageurl' => new external_value(PARAM_URL, 'Profile image URL'),
                'status' => new external_value(PARAM_TEXT, 'Submission status'),
                'submittedat' => new external_value(PARAM_INT, 'Submission timestamp'),
                'gradevalue' => new external_value(PARAM_FLOAT, 'Current grade', VALUE_OPTIONAL),
                'locked' => new external_value(PARAM_BOOL, 'Whether submission changes are locked', VALUE_DEFAULT, false),
                'hasoverride' => new external_value(
                    PARAM_BOOL, 'Whether user has an override', VALUE_DEFAULT, false
                ),
                'hasextension' => new external_value(
                    PARAM_BOOL, 'Whether user has an extension', VALUE_DEFAULT, false
                ),
                'islate' => new external_value(
                    PARAM_BOOL, 'Whether submission is late (accounts for overrides/extensions)', VALUE_DEFAULT, false
                ),
            ]),
        );
    }
}
