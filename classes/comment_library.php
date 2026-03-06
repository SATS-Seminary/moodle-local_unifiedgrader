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
 * CRUD operations for the reusable comment library.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages reusable comment library entries.
 *
 * Comments can be scoped to a specific course or be global (courseid = 0).
 * Each teacher has their own library.
 */
class comment_library {

    /**
     * Get comments for a user, optionally filtered by course.
     *
     * Returns both course-specific and global comments.
     *
     * @param int $userid The teacher's user ID.
     * @param int $courseid The course ID (0 to get only global).
     * @return array List of comment arrays.
     */
    public static function get_comments(int $userid, int $courseid = 0): array {
        global $DB;

        // Get course-specific and global comments.
        $sql = "SELECT * FROM {local_unifiedgrader_comments}
                 WHERE userid = :userid AND (courseid = :courseid OR courseid = 0)
                 ORDER BY sortorder ASC, timecreated DESC";

        $comments = $DB->get_records_sql($sql, [
            'userid' => $userid,
            'courseid' => $courseid,
        ]);

        return array_values(array_map(function ($comment) {
            return [
                'id' => (int) $comment->id,
                'userid' => (int) $comment->userid,
                'courseid' => (int) $comment->courseid,
                'content' => $comment->content,
                'sortorder' => (int) $comment->sortorder,
                'timecreated' => (int) $comment->timecreated,
                'timemodified' => (int) $comment->timemodified,
            ];
        }, $comments));
    }

    /**
     * Save a comment (create or update).
     *
     * @param int $userid The teacher's user ID.
     * @param int $courseid The course ID (0 for global).
     * @param string $content Comment text.
     * @param int $commentid Existing comment ID to update, or 0 for new.
     * @return int The comment ID.
     */
    public static function save_comment(int $userid, int $courseid, string $content, int $commentid = 0): int {
        global $DB;

        $now = time();

        if ($commentid > 0) {
            $record = $DB->get_record('local_unifiedgrader_comments', ['id' => $commentid], '*', MUST_EXIST);
            $record->content = $content;
            $record->timemodified = $now;
            $DB->update_record('local_unifiedgrader_comments', $record);
            return $commentid;
        }

        $record = (object) [
            'userid' => $userid,
            'courseid' => $courseid,
            'content' => $content,
            'sortorder' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        return $DB->insert_record('local_unifiedgrader_comments', $record);
    }

    /**
     * Delete a comment.
     *
     * @param int $commentid The comment ID.
     */
    public static function delete_comment(int $commentid): void {
        global $DB;
        $DB->delete_records('local_unifiedgrader_comments', ['id' => $commentid]);
    }
}
