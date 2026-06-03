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
 * External function: add a submission comment.
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
use local_unifiedgrader\submission_comment_manager;
use local_unifiedgrader\notification\submission_comment_notification;

/**
 * Adds a comment to a student's submission.
 */
class add_submission_comment extends external_api {
    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
            'content' => new external_value(PARAM_RAW, 'Comment content'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @param int $userid
     * @param string $content
     * @return array
     */
    public static function execute(int $cmid, int $userid, string $content): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
            'content' => $content,
        ]);

        global $USER;

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);

        // Allow teachers (grade) or students posting on their own submission (viewfeedback).
        $hasgrade = has_capability('local/unifiedgrader:grade', $context);
        $hasviewfeedback = has_capability('local/unifiedgrader:viewfeedback', $context);
        if (!$hasgrade && !$hasviewfeedback) {
            require_capability('local/unifiedgrader:grade', $context);
        }
        // Students can only post on their own submission.
        if (!$hasgrade && (int) $params['userid'] !== (int) $USER->id) {
            throw new \moodle_exception('nopermission', 'local_unifiedgrader');
        }

        // Release the PHP session lock so concurrent AJAX from the same
        // teacher does not serialize behind this request. This handler
        // does not write to $SESSION.
        \core\session\manager::write_close();
        // News / announcements forums are not graded; submission comments
        // do not apply. Refuse defensively in case the JS widget slipped
        // through the hook-callback guard (stale cache, etc).
        $cm = get_coursemodule_from_id(null, $params['cmid'], 0, false, MUST_EXIST);
        if (\local_unifiedgrader\forum_helper::is_news_forum($cm)) {
            throw new \moodle_exception('nopermission', 'local_unifiedgrader');
        }

        $record = submission_comment_manager::add_comment(
            $params['cmid'],
            $params['userid'],
            (int) $USER->id,
            $params['content']
        );

        $count = submission_comment_manager::count_comments($params['cmid'], $params['userid']);

        // Send notification asynchronously-safe (runs inline but is fast).
        submission_comment_notification::send(
            $params['cmid'],
            $params['userid'],
            (int) $USER->id,
            $params['content']
        );

        return [
            'id' => (int) $record->id,
            // Filter the just-stored content the same way get_submission_comments
            // does on read so the client renders sanitized HTML uniformly,
            // whether it just posted the comment or fetched it later.
            'content' => format_text(
                $record->content,
                FORMAT_MOODLE,
                ['context' => $context, 'para' => false]
            ),
            'fullname' => fullname($USER),
            'time' => userdate($record->timecreated),
            'count' => $count,
        ];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'New comment ID'),
            'content' => new external_value(PARAM_RAW, 'Comment content'),
            'fullname' => new external_value(PARAM_TEXT, 'Author full name'),
            'time' => new external_value(PARAM_TEXT, 'Human-readable time'),
            'count' => new external_value(PARAM_INT, 'Updated total comment count'),
        ]);
    }
}
