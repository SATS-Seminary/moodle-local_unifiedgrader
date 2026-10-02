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
 * External function: submit a comment-library entry as a candidate
 * for system-default inclusion.
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
use local_unifiedgrader\comment_library_manager;

/**
 * Submit one of the current teacher's comments to the admin review queue.
 */
class submit_library_proposal extends external_api {
    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'commentid' => new external_value(PARAM_INT, 'Comment ID being proposed'),
            'rationale' => new external_value(
                PARAM_TEXT,
                'Optional reason shown to the admin reviewer',
                VALUE_DEFAULT,
                '',
            ),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $commentid
     * @param string $rationale
     * @return array
     */
    public static function execute(int $commentid, string $rationale = ''): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'commentid' => $commentid,
            'rationale' => $rationale,
        ]);

        $context = \context_system::instance();
        self::validate_context($context);
        // The comment library is for people who grade, not for every logged-in user.
        \local_unifiedgrader\access::require_library_access();

        // Release the PHP session lock so concurrent AJAX from the same
        // teacher does not serialize behind this request. This handler
        // does not write to $SESSION.
        \core\session\manager::write_close();
        if (isguestuser()) {
            throw new \moodle_exception('noguest');
        }
        // No new capability: any user with a personal library entry can
        // suggest it. Manager-level vetting still gates promotion.

        $proposalid = comment_library_manager::submit_proposal(
            $params['commentid'],
            $USER->id,
            $params['rationale'],
        );

        return ['proposalid' => $proposalid];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'proposalid' => new external_value(PARAM_INT, 'The new proposal ID'),
        ]);
    }
}
