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
 * External function: delete a submission comment.
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
use local_unifiedgrader\submission_comment_manager;

/**
 * Deletes a submission comment.
 */
class delete_submission_comment extends external_api {

    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'commentid' => new external_value(PARAM_INT, 'Comment ID to delete'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @param int $commentid
     * @return array
     */
    public static function execute(int $cmid, int $commentid): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'commentid' => $commentid,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);

        // Allow teachers (grade) or students (viewfeedback).
        $hasgrade = has_capability('local/unifiedgrader:grade', $context);
        $hasviewfeedback = has_capability('local/unifiedgrader:viewfeedback', $context);
        if (!$hasgrade && !$hasviewfeedback) {
            require_capability('local/unifiedgrader:grade', $context);
        }

        // Load the comment record.
        $record = submission_comment_manager::get_comment($params['commentid']);
        if (!$record) {
            throw new \moodle_exception('invalidcomment', 'local_unifiedgrader');
        }

        // Verify the comment belongs to this activity.
        if ((int) $record->cmid !== (int) $params['cmid']) {
            throw new \moodle_exception('invalidcomment', 'local_unifiedgrader');
        }

        // Permission: author can delete own, or teacher can delete any.
        if (!$hasgrade && (int) $record->authorid !== (int) $USER->id) {
            throw new \moodle_exception('nopermission', 'local_unifiedgrader');
        }

        submission_comment_manager::delete_comment($params['commentid']);

        $count = submission_comment_manager::count_comments((int) $record->cmid, (int) $record->userid);

        return [
            'success' => true,
            'count' => $count,
        ];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether deletion succeeded'),
            'count' => new external_value(PARAM_INT, 'Updated total comment count'),
        ]);
    }
}
