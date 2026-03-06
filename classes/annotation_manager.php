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
 * CRUD operations for PDF annotations.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages annotation data stored per page per file per student.
 *
 * Each row stores the full Fabric.js canvas JSON for a single page.
 * The composite key (cmid, userid, authorid, fileid, pagenum) identifies
 * a unique annotation record.
 */
class annotation_manager {

    /**
     * Get all annotations for a specific file.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @param int $fileid File being annotated.
     * @return array List of annotation arrays, ordered by pagenum.
     */
    public static function get_annotations(int $cmid, int $userid, int $fileid): array {
        global $DB;

        $records = $DB->get_records('local_unifiedgrader_annot', [
            'cmid' => $cmid,
            'userid' => $userid,
            'fileid' => $fileid,
        ], 'pagenum ASC');

        return array_values(array_map(function ($record) {
            return [
                'id' => (int) $record->id,
                'cmid' => (int) $record->cmid,
                'userid' => (int) $record->userid,
                'authorid' => (int) $record->authorid,
                'fileid' => (int) $record->fileid,
                'pagenum' => (int) $record->pagenum,
                'annotationdata' => $record->annotationdata,
                'timecreated' => (int) $record->timecreated,
                'timemodified' => (int) $record->timemodified,
            ];
        }, $records));
    }

    /**
     * Save annotations for multiple pages in a batch (upsert).
     *
     * For each page entry: if a row with matching (cmid, userid, authorid, fileid, pagenum)
     * exists, update it. Otherwise insert a new row. If annotationdata is empty or has
     * no objects, delete the row (page was cleared).
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @param int $authorid Teacher user ID.
     * @param int $fileid File being annotated.
     * @param array $pages Array of ['pagenum' => int, 'annotationdata' => string].
     * @return bool True on success.
     */
    public static function save_annotations(
        int $cmid,
        int $userid,
        int $authorid,
        int $fileid,
        array $pages,
    ): bool {
        global $DB;

        $now = time();

        foreach ($pages as $page) {
            $pagenum = (int) $page['pagenum'];
            $data = $page['annotationdata'];

            // Determine if page data is empty (no annotations).
            $isempty = empty($data) || $data === '{}';
            if (!$isempty) {
                $parsed = json_decode($data, true);
                $isempty = is_array($parsed) && empty($parsed['objects']);
            }

            // Look up existing record.
            $existing = $DB->get_record('local_unifiedgrader_annot', [
                'cmid' => $cmid,
                'userid' => $userid,
                'authorid' => $authorid,
                'fileid' => $fileid,
                'pagenum' => $pagenum,
            ]);

            if ($existing) {
                if ($isempty) {
                    $DB->delete_records('local_unifiedgrader_annot', ['id' => $existing->id]);
                } else {
                    $existing->annotationdata = $data;
                    $existing->timemodified = $now;
                    $DB->update_record('local_unifiedgrader_annot', $existing);
                }
            } else if (!$isempty) {
                $record = (object) [
                    'cmid' => $cmid,
                    'userid' => $userid,
                    'authorid' => $authorid,
                    'fileid' => $fileid,
                    'pagenum' => $pagenum,
                    'annotationdata' => $data,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ];
                $DB->insert_record('local_unifiedgrader_annot', $record);
            }
        }

        return true;
    }

    /**
     * Delete all annotations for a file.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @param int $fileid File ID.
     */
    public static function delete_annotations(int $cmid, int $userid, int $fileid): void {
        global $DB;

        $DB->delete_records('local_unifiedgrader_annot', [
            'cmid' => $cmid,
            'userid' => $userid,
            'fileid' => $fileid,
        ]);
    }
}
