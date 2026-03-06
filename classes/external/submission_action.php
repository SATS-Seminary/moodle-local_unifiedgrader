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
 * External function: perform a submission status action.
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
use local_unifiedgrader\adapter\adapter_factory;

/**
 * Performs a submission status action (revert to draft, remove, lock, unlock).
 */
class submission_action extends external_api {

    /** @var string[] Allowed action identifiers. */
    private const ALLOWED_ACTIONS = ['revert_to_draft', 'remove', 'lock', 'unlock', 'submit'];

    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
            'action' => new external_value(PARAM_ALPHANUMEXT, 'Action to perform'),
        ]);
    }

    /**
     * Execute the submission action.
     *
     * @param int $cmid
     * @param int $userid
     * @param string $action
     * @return array
     */
    public static function execute(int $cmid, int $userid, string $action): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
            'action' => $action,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);

        if (!in_array($params['action'], self::ALLOWED_ACTIONS, true)) {
            throw new \moodle_exception('invalidaction', 'local_unifiedgrader');
        }

        $adapter = adapter_factory::create($params['cmid']);
        $adapter->perform_submission_action($params['userid'], $params['action']);

        return ['success' => true];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the action succeeded'),
        ]);
    }
}
