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
 * CRUD operations for submission comments.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages submission comments stored per student per activity.
 *
 * These comments are visible to the student and all teachers with
 * grading capability in the activity context.
 */
class submission_comment_manager {

    /** @var string The database table name. */
    private const TABLE = 'local_unifiedgrader_scomm';

    /**
     * Get all comments for a student in a specific activity.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @return array List of comment records with author details.
     */
    public static function get_comments(int $cmid, int $userid): array {
        global $DB;

        $sql = "SELECT sc.*, u.firstname, u.lastname, u.firstnamephonetic,
                       u.lastnamephonetic, u.middlename, u.alternatename
                  FROM {" . self::TABLE . "} sc
                  JOIN {user} u ON u.id = sc.authorid
                 WHERE sc.cmid = :cmid AND sc.userid = :userid
              ORDER BY sc.timecreated ASC";

        return array_values($DB->get_records_sql($sql, [
            'cmid' => $cmid,
            'userid' => $userid,
        ]));
    }

    /**
     * Add a new comment.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @param int $authorid Author user ID.
     * @param string $content Comment content.
     * @return \stdClass The new comment record.
     */
    public static function add_comment(int $cmid, int $userid, int $authorid, string $content): \stdClass {
        global $DB;

        $now = time();
        $record = (object) [
            'cmid' => $cmid,
            'userid' => $userid,
            'authorid' => $authorid,
            'content' => $content,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record(self::TABLE, $record);

        return $record;
    }

    /**
     * Delete a comment by ID.
     *
     * @param int $commentid Comment ID.
     * @return bool True if deleted.
     */
    public static function delete_comment(int $commentid): bool {
        global $DB;
        return $DB->delete_records(self::TABLE, ['id' => $commentid]);
    }

    /**
     * Count comments for a student in a specific activity.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @return int Comment count.
     */
    public static function count_comments(int $cmid, int $userid): int {
        global $DB;
        return $DB->count_records(self::TABLE, [
            'cmid' => $cmid,
            'userid' => $userid,
        ]);
    }

    /**
     * Get a single comment by ID.
     *
     * @param int $commentid Comment ID.
     * @return \stdClass|false The comment record or false.
     */
    public static function get_comment(int $commentid) {
        global $DB;
        return $DB->get_record(self::TABLE, ['id' => $commentid]);
    }

    /**
     * Delete all comments for a given activity (cleanup).
     *
     * @param int $cmid Course module ID.
     */
    public static function delete_all_for_context(int $cmid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['cmid' => $cmid]);
    }

    /**
     * Get all comments authored by a specific user (for privacy provider).
     *
     * @param int $authorid Author user ID.
     * @return array List of comment records.
     */
    public static function get_comments_by_author(int $authorid): array {
        global $DB;
        return array_values($DB->get_records(self::TABLE, ['authorid' => $authorid], 'timecreated ASC'));
    }

    /**
     * Delete all comments authored by a specific user (for privacy provider).
     *
     * @param int $authorid Author user ID.
     */
    public static function delete_comments_by_author(int $authorid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['authorid' => $authorid]);
    }

    /**
     * Delete all comments about a specific user (for privacy provider).
     *
     * @param int $userid Student user ID.
     */
    public static function delete_comments_about_user(int $userid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['userid' => $userid]);
    }
}
