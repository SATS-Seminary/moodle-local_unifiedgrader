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
 * External function: refer a submission for an integrity review.
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
 * Creates an academic-integrity referral for a student's submission.
 */
class refer extends external_api {
    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
            'reason' => new external_value(PARAM_ALPHANUMEXT, 'Referral reason', VALUE_DEFAULT, 'integrity'),
            'note' => new external_value(PARAM_TEXT, 'Optional free-text note', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @param int $userid
     * @param string $reason
     * @param string $note
     * @return array
     */
    public static function execute(int $cmid, int $userid, string $reason = 'integrity', string $note = ''): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
            'reason' => $reason,
            'note' => $note,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:refer', $context);
        \local_unifiedgrader\access::require_student_access($context, (int) $params['userid']);

        // Release the PHP session lock so concurrent AJAX from the same
        // teacher does not serialize behind this request. This handler
        // does not write to $SESSION.
        \core\session\manager::write_close();

        $id = referral_manager::refer(
            $params['cmid'],
            $params['userid'],
            $USER->id,
            $params['reason'] !== '' ? $params['reason'] : 'integrity',
            $params['note'],
        );

        return [
            'referralid' => $id,
            'referrals' => referral_manager::get_for_activity($params['cmid'], $params['userid']),
        ];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'referralid' => new external_value(PARAM_INT, 'The referral ID'),
            'referrals' => new external_multiple_structure(self::referral_structure()),
        ]);
    }

    /**
     * Shared single-referral structure.
     *
     * @return external_single_structure
     */
    public static function referral_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Referral ID'),
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
            'authorid' => new external_value(PARAM_INT, 'Author user ID'),
            'reason' => new external_value(PARAM_ALPHANUMEXT, 'Referral reason'),
            'note' => new external_value(PARAM_TEXT, 'Free-text note'),
            'status' => new external_value(PARAM_ALPHA, 'Status: open or resolved'),
            'outcome' => new external_value(PARAM_ALPHANUMEXT, 'Resolution outcome'),
            'timereferred' => new external_value(PARAM_INT, 'Time referred'),
            'timeresolved' => new external_value(PARAM_INT, 'Time resolved'),
            'timemodified' => new external_value(PARAM_INT, 'Time modified'),
        ]);
    }
}
