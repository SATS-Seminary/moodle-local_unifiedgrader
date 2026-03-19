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
 * External function: get submission comments.
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
use local_unifiedgrader\submission_comment_manager;

/**
 * Returns submission comments for a student in an activity.
 */
class get_submission_comments extends external_api {

    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @param int $userid
     * @return array
     */
    public static function execute(int $cmid, int $userid): array {
        global $USER, $OUTPUT, $PAGE;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);

        // Allow teachers (grade) or students viewing their own feedback (viewfeedback).
        $hasgrade = has_capability('local/unifiedgrader:grade', $context);
        $hasviewfeedback = has_capability('local/unifiedgrader:viewfeedback', $context);
        if (!$hasgrade && !$hasviewfeedback) {
            require_capability('local/unifiedgrader:grade', $context);
        }
        // Students can only view their own comments.
        if (!$hasgrade && (int) $params['userid'] !== (int) $USER->id) {
            throw new \moodle_exception('nopermission', 'local_unifiedgrader');
        }

        $comments = submission_comment_manager::get_comments($params['cmid'], $params['userid']);

        // Ensure PAGE has a context set for user_picture rendering.
        try {
            $PAGE->context;
        } catch (\Throwable $e) {
            $PAGE->set_context($context);
        }

        $result = [];
        foreach ($comments as $c) {
            $author = \core_user::get_user($c->authorid);
            $avatar = '';
            if ($author) {
                $avatar = $OUTPUT->user_picture($author, ['size' => 30, 'link' => false]);
            }
            $candelete = $hasgrade || ((int) $c->authorid === (int) $USER->id);

            $result[] = [
                'id' => (int) $c->id,
                'content' => $c->content,
                'fullname' => $author ? fullname($author) : '',
                'avatar' => $avatar,
                'time' => userdate($c->timecreated),
                'timecreated' => (int) $c->timecreated,
                'userid' => (int) $c->authorid,
                'candelete' => $candelete,
            ];
        }

        $canpost = $hasgrade || ($hasviewfeedback && (int) $params['userid'] === (int) $USER->id);

        return [
            'comments' => $result,
            'count' => count($result),
            'canpost' => $canpost,
        ];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'comments' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Comment ID'),
                    'content' => new external_value(PARAM_RAW, 'Comment content'),
                    'fullname' => new external_value(PARAM_TEXT, 'Author full name'),
                    'avatar' => new external_value(PARAM_RAW, 'Author avatar HTML'),
                    'time' => new external_value(PARAM_TEXT, 'Human-readable time'),
                    'timecreated' => new external_value(PARAM_INT, 'Timestamp'),
                    'userid' => new external_value(PARAM_INT, 'Author user ID'),
                    'candelete' => new external_value(PARAM_BOOL, 'Whether current user can delete this comment'),
                ]),
            ),
            'count' => new external_value(PARAM_INT, 'Total comment count'),
            'canpost' => new external_value(PARAM_BOOL, 'Whether the user can post comments'),
        ]);
    }
}
