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
 * External function: resolve an integrity referral.
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
use local_unifiedgrader\referral_manager;

/**
 * Resolves an academic-integrity referral with an outcome.
 */
class resolve_referral extends external_api {
    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID (for capability check)'),
            'userid' => new external_value(PARAM_INT, 'Student user ID (for the refreshed list)'),
            'referralid' => new external_value(PARAM_INT, 'Referral ID to resolve'),
            'outcome' => new external_value(PARAM_ALPHANUMEXT, 'Resolution outcome', VALUE_DEFAULT, 'cleared'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @param int $userid
     * @param int $referralid
     * @param string $outcome
     * @return array
     */
    public static function execute(int $cmid, int $userid, int $referralid, string $outcome = 'cleared'): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
            'referralid' => $referralid,
            'outcome' => $outcome,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:refer', $context);
        \local_unifiedgrader\access::require_student_access($context, (int) $params['userid']);

        // Release the PHP session lock so concurrent AJAX from the same
        // teacher does not serialize behind this request. This handler
        // does not write to $SESSION.
        \core\session\manager::write_close();

        // Ensure the referral belongs to this activity and student before resolving.
        $record = $DB->get_record('local_unifiedgrader_referral', ['id' => $params['referralid']], '*', MUST_EXIST);
        if ((int) $record->cmid !== $params['cmid'] || (int) $record->userid !== $params['userid']) {
            throw new \invalid_parameter_exception('Referral does not belong to the given activity and student');
        }

        referral_manager::resolve($params['referralid'], $params['outcome'] !== '' ? $params['outcome'] : 'cleared');

        return [
            'success' => true,
            'referrals' => referral_manager::get_for_activity($params['cmid'], $params['userid']),
        ];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the referral was resolved'),
            'referrals' => new external_multiple_structure(refer::referral_structure()),
        ]);
    }
}
