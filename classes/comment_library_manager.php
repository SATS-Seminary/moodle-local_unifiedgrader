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
 * Comment library v2 manager — CRUD for comments, tags, and mappings.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages comment library entries, tags, and tag mappings.
 */
class comment_library_manager {

    /**
     * Get comments for a teacher, optionally filtered by course code and/or tag.
     *
     * @param int $userid The teacher's user ID.
     * @param string $coursecode Filter by course code (empty = all).
     * @param int $tagid Filter by tag ID (0 = all).
     * @return array List of comment records with tags.
     */
    public static function get_comments(int $userid, string $coursecode = '', int $tagid = 0): array {
        global $DB;

        $params = ['userid' => $userid];
        $where = 'c.userid = :userid';

        if ($coursecode !== '') {
            $where .= ' AND c.coursecode = :coursecode';
            $params['coursecode'] = $coursecode;
        }

        if ($tagid > 0) {
            $where .= ' AND c.id IN (SELECT commentid FROM {local_unifiedgrader_clmap} WHERE tagid = :tagid)';
            $params['tagid'] = $tagid;
        }

        $sql = "SELECT c.*
                  FROM {local_unifiedgrader_clib} c
                 WHERE {$where}
                 ORDER BY c.sortorder ASC, c.timecreated DESC";

        $comments = $DB->get_records_sql($sql, $params);

        return array_values(array_map(function ($c) {
            return self::format_comment($c);
        }, $comments));
    }

    /**
     * Save (create or update) a comment.
     *
     * @param int $userid Owner teacher ID.
     * @param string $coursecode The course code.
     * @param string $content Comment text.
     * @param int[] $tagids Tag IDs to assign.
     * @param int $shared 1 = shared, 0 = private.
     * @param int $commentid Existing comment ID to update (0 = new).
     * @return int The comment ID.
     */
    public static function save_comment(
        int $userid,
        string $coursecode,
        string $content,
        array $tagids = [],
        int $shared = 0,
        int $commentid = 0,
    ): int {
        global $DB;

        $now = time();

        if ($commentid > 0) {
            $record = $DB->get_record('local_unifiedgrader_clib', ['id' => $commentid, 'userid' => $userid], '*', MUST_EXIST);
            $record->coursecode = $coursecode;
            $record->content = $content;
            $record->shared = $shared;
            $record->timemodified = $now;
            $DB->update_record('local_unifiedgrader_clib', $record);
        } else {
            $commentid = $DB->insert_record('local_unifiedgrader_clib', (object) [
                'userid' => $userid,
                'coursecode' => $coursecode,
                'content' => $content,
                'shared' => $shared,
                'sortorder' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        // Sync tag mappings.
        self::sync_comment_tags($commentid, $tagids);

        return $commentid;
    }

    /**
     * Delete a comment and its tag mappings.
     *
     * @param int $commentid The comment ID.
     * @param int $userid The owner — enforces ownership check.
     */
    public static function delete_comment(int $commentid, int $userid): void {
        global $DB;

        $DB->delete_records('local_unifiedgrader_clib', ['id' => $commentid, 'userid' => $userid]);
        $DB->delete_records('local_unifiedgrader_clmap', ['commentid' => $commentid]);
    }

    /**
     * Get tags visible to a teacher (own tags + system defaults).
     *
     * @param int $userid The teacher's user ID.
     * @return array List of tag records.
     */
    public static function get_tags(int $userid): array {
        global $DB;

        $sql = "SELECT *
                  FROM {local_unifiedgrader_cltag}
                 WHERE userid = :userid OR userid = 0
                 ORDER BY sortorder ASC, name ASC";

        $tags = $DB->get_records_sql($sql, ['userid' => $userid]);

        return array_values(array_map(function ($t) {
            return [
                'id' => (int) $t->id,
                'userid' => (int) $t->userid,
                'name' => $t->name,
                'sortorder' => (int) $t->sortorder,
                'issystem' => ((int) $t->userid === 0),
            ];
        }, $tags));
    }

    /**
     * Save (create or update) a tag.
     *
     * @param int $userid Owner teacher ID.
     * @param string $name Tag display name.
     * @param int $tagid Existing tag ID to update (0 = new).
     * @return int The tag ID.
     */
    public static function save_tag(int $userid, string $name, int $tagid = 0): int {
        global $DB;

        $now = time();

        if ($tagid > 0) {
            $record = $DB->get_record('local_unifiedgrader_cltag', ['id' => $tagid, 'userid' => $userid], '*', MUST_EXIST);
            $record->name = $name;
            $record->timemodified = $now;
            $DB->update_record('local_unifiedgrader_cltag', $record);
            return $tagid;
        }

        return $DB->insert_record('local_unifiedgrader_cltag', (object) [
            'userid' => $userid,
            'name' => $name,
            'sortorder' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Delete a tag and its mappings. Only non-system tags owned by the user.
     *
     * @param int $tagid The tag ID.
     * @param int $userid The owner — enforces ownership check.
     */
    public static function delete_tag(int $tagid, int $userid): void {
        global $DB;

        // Prevent deleting system defaults.
        $tag = $DB->get_record('local_unifiedgrader_cltag', ['id' => $tagid], '*', MUST_EXIST);
        if ((int) $tag->userid === 0) {
            throw new \moodle_exception('cannotdeletesystemtag', 'local_unifiedgrader');
        }
        if ((int) $tag->userid !== $userid) {
            throw new \moodle_exception('nopermission', 'local_unifiedgrader');
        }

        $DB->delete_records('local_unifiedgrader_cltag', ['id' => $tagid]);
        $DB->delete_records('local_unifiedgrader_clmap', ['tagid' => $tagid]);
    }

    /**
     * Get instance-wide shared comments, optionally filtered by tag.
     *
     * @param int $userid The requesting teacher's ID (excluded from results to avoid seeing own comments).
     * @param int $tagid Filter by tag ID (0 = all).
     * @return array List of comment records with tags and owner info.
     */
    public static function get_shared_comments(int $userid, int $tagid = 0): array {
        global $DB;

        $params = ['userid' => $userid];
        $where = 'c.shared = 1 AND c.userid != :userid';

        if ($tagid > 0) {
            $where .= ' AND c.id IN (SELECT commentid FROM {local_unifiedgrader_clmap} WHERE tagid = :tagid)';
            $params['tagid'] = $tagid;
        }

        $sql = "SELECT c.*, u.firstname, u.lastname
                  FROM {local_unifiedgrader_clib} c
                  JOIN {user} u ON u.id = c.userid
                 WHERE {$where}
                 ORDER BY c.timemodified DESC";

        $comments = $DB->get_records_sql($sql, $params);

        return array_values(array_map(function ($c) {
            $formatted = self::format_comment($c);
            $formatted['ownername'] = fullname($c);
            return $formatted;
        }, $comments));
    }

    /**
     * Import a shared comment into a teacher's own library.
     *
     * @param int $sourcecommentid The shared comment ID to copy.
     * @param int $userid The importing teacher's ID.
     * @param string $coursecode The course code to assign in the new copy.
     * @return int The new comment ID.
     */
    public static function import_shared_comment(int $sourcecommentid, int $userid, string $coursecode): int {
        global $DB;

        $source = $DB->get_record('local_unifiedgrader_clib', ['id' => $sourcecommentid, 'shared' => 1], '*', MUST_EXIST);

        $now = time();
        $newid = $DB->insert_record('local_unifiedgrader_clib', (object) [
            'userid' => $userid,
            'coursecode' => $coursecode,
            'content' => $source->content,
            'shared' => 0,
            'sortorder' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        // Copy tag mappings.
        $maps = $DB->get_records('local_unifiedgrader_clmap', ['commentid' => $sourcecommentid]);
        foreach ($maps as $map) {
            $DB->insert_record('local_unifiedgrader_clmap', (object) [
                'commentid' => $newid,
                'tagid' => $map->tagid,
            ]);
        }

        return $newid;
    }

    /**
     * Sync tag mappings for a comment (replace all).
     *
     * @param int $commentid
     * @param int[] $tagids
     */
    private static function sync_comment_tags(int $commentid, array $tagids): void {
        global $DB;

        $DB->delete_records('local_unifiedgrader_clmap', ['commentid' => $commentid]);

        foreach ($tagids as $tagid) {
            $DB->insert_record('local_unifiedgrader_clmap', (object) [
                'commentid' => $commentid,
                'tagid' => (int) $tagid,
            ]);
        }
    }

    /**
     * Format a comment record with its tags for API output.
     *
     * @param object $record The DB record.
     * @return array Formatted comment.
     */
    private static function format_comment(object $record): array {
        global $DB;

        $tagids = $DB->get_fieldset_select('local_unifiedgrader_clmap', 'tagid', 'commentid = ?', [$record->id]);

        return [
            'id' => (int) $record->id,
            'userid' => (int) $record->userid,
            'coursecode' => $record->coursecode,
            'content' => $record->content,
            'shared' => (int) $record->shared,
            'sortorder' => (int) $record->sortorder,
            'timecreated' => (int) $record->timecreated,
            'timemodified' => (int) $record->timemodified,
            'tagids' => array_map('intval', $tagids),
        ];
    }
}
