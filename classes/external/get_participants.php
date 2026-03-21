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
            'group' => new external_value(
                PARAM_TEXT,
                'Group filter: 0=all, -1=my groups, or comma-separated group IDs',
                VALUE_DEFAULT,
                '0',
            ),
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
     * @param string $group
     * @param string $search
     * @param string $sort
     * @param string $sortdir
     * @return array
     */
    public static function execute(
        int $cmid,
        string $status = 'all',
        string $group = '0',
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

        // Validate group parameter: must be comma-separated integers (including -1).
        $groupstr = trim($params['group']);
        if ($groupstr !== '' && !preg_match('/^-?[0-9]+(,-?[0-9]+)*$/', $groupstr)) {
            throw new \invalid_parameter_exception('group must be comma-separated integers');
        }

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);

        // Resolve group IDs.
        $groupids = self::resolve_group_ids($groupstr, $params['cmid'], $context);

        $adapter = adapter_factory::create($params['cmid']);
        return $adapter->get_participants([
            'status' => $params['status'],
            'groups' => $groupids,
            'search' => $params['search'],
            'sort' => $params['sort'],
            'sortdir' => $params['sortdir'],
        ]);
    }

    /**
     * Resolve group filter string into an array of group IDs.
     *
     * @param string $groupstr The raw group parameter: "0", "-1", or comma-separated IDs.
     * @param int $cmid Course module ID.
     * @param \context_module $context Module context.
     * @return int[] Array of group IDs. Empty array means "all groups" (no filter).
     */
    private static function resolve_group_ids(string $groupstr, int $cmid, \context_module $context): array {
        global $USER;

        // "0" or empty = all groups (no filter).
        if ($groupstr === '' || $groupstr === '0') {
            return [];
        }

        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);

        // "-1" = all my groups.
        if ($groupstr === '-1') {
            $usergroups = groups_get_all_groups($cm->course, $USER->id, $cm->groupingid, 'g.id');
            return array_map('intval', array_keys($usergroups));
        }

        // Comma-separated group IDs.
        $ids = array_map('intval', explode(',', $groupstr));
        return array_filter($ids, function ($id) {
            return $id > 0;
        });
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
                    PARAM_BOOL,
                    'Whether user has an override',
                    VALUE_DEFAULT,
                    false,
                ),
                'hasextension' => new external_value(
                    PARAM_BOOL,
                    'Whether user has an extension',
                    VALUE_DEFAULT,
                    false,
                ),
                'islate' => new external_value(
                    PARAM_BOOL,
                    'Whether submission is late (accounts for overrides/extensions)',
                    VALUE_DEFAULT,
                    false,
                ),
            ]),
        );
    }
}
