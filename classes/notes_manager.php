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
 * CRUD operations for private teacher notes.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages private teacher notes stored per student per activity.
 *
 * These notes are visible only to users with the viewnotes capability
 * and are never shown to students.
 */
class notes_manager {

    /**
     * Get all notes for a student in a specific activity.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @return array List of note arrays.
     */
    public static function get_notes(int $cmid, int $userid): array {
        global $DB;

        $notes = $DB->get_records('local_unifiedgrader_notes', [
            'cmid' => $cmid,
            'userid' => $userid,
        ], 'timecreated DESC');

        return array_values(array_map(function ($note) {
            $author = \core_user::get_user($note->authorid);
            return [
                'id' => (int) $note->id,
                'cmid' => (int) $note->cmid,
                'userid' => (int) $note->userid,
                'authorid' => (int) $note->authorid,
                'authorname' => $author ? fullname($author) : '',
                'content' => format_text($note->content, FORMAT_HTML),
                'rawcontent' => $note->content,
                'timecreated' => (int) $note->timecreated,
                'timemodified' => (int) $note->timemodified,
            ];
        }, $notes));
    }

    /**
     * Save a note (create or update).
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @param int $authorid Author (teacher) user ID.
     * @param string $content Note content.
     * @param int $noteid Existing note ID to update, or 0 for new.
     * @return int The note ID.
     */
    public static function save_note(int $cmid, int $userid, int $authorid, string $content, int $noteid = 0): int {
        global $DB;

        $now = time();

        if ($noteid > 0) {
            $record = $DB->get_record('local_unifiedgrader_notes', ['id' => $noteid], '*', MUST_EXIST);
            $record->content = $content;
            $record->timemodified = $now;
            $DB->update_record('local_unifiedgrader_notes', $record);
            return $noteid;
        }

        $record = (object) [
            'cmid' => $cmid,
            'userid' => $userid,
            'authorid' => $authorid,
            'content' => $content,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        return $DB->insert_record('local_unifiedgrader_notes', $record);
    }

    /**
     * Delete a note.
     *
     * @param int $noteid The note ID.
     */
    public static function delete_note(int $noteid): void {
        global $DB;
        $DB->delete_records('local_unifiedgrader_notes', ['id' => $noteid]);
    }
}
