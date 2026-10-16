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
 * CSV export and import for the comment library.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader;

/**
 * Turns comment library rows into a CSV file and back.
 *
 * Two export shapes:
 *  - Single-owner ("bucket" or "my library"): coursecode,shared,tags,content
 *  - Cross-owner (admin, spans teachers): ownerid,ownername,coursecode,shared,tags,content
 *
 * Import auto-detects the shape from the header row and is deliberately
 * forgiving: content is the only required column, everything else falls
 * back to a caller-supplied default. It is also additive and idempotent in
 * the same way as library_audit::import_legacy() — a row that already
 * exists for its target owner/scope/content is skipped rather than
 * duplicated, so re-importing the same file twice is harmless.
 */
class library_csv {
    /** @var string Separator between tag names within one CSV cell. */
    private const TAG_SEPARATOR = '|';

    /**
     * @var string Escape character for fputcsv() and fgetcsv().
     *
     * PHP's long-standing default, passed explicitly because PHP 8.4
     * deprecates leaving it out. Kept so files exported earlier read back
     * the same.
     */
    private const CSV_ESCAPE = '\\';

    /**
     * Export one owner's library (optionally restricted to one course code).
     *
     * @param int $userid The owner.
     * @param string|null $coursecode Restrict to this code; null = every code.
     * @return string CSV content.
     */
    public static function export_for_owner(int $userid, ?string $coursecode = null): string {
        global $DB;

        $where = 'userid = :userid';
        $params = ['userid' => $userid];
        if ($coursecode !== null) {
            $where .= ' AND coursecode = :coursecode';
            $params['coursecode'] = $coursecode;
        }

        $records = $DB->get_records_select(
            'local_unifiedgrader_clib',
            $where,
            $params,
            'coursecode ASC, timecreated ASC',
        );

        return self::build_csv($records, false);
    }

    /**
     * Export across owners, matching the same filters the moderation page uses.
     *
     * @param int $userid Restrict to one owner (0 = every owner).
     * @param string $codefilter Restrict to codes containing this substring.
     * @return string CSV content.
     */
    public static function export_for_filters(int $userid = 0, string $codefilter = ''): string {
        global $DB;

        $where = ['1 = 1'];
        $params = [];
        if ($userid > 0) {
            $where[] = 'c.userid = :userid';
            $params['userid'] = $userid;
        }
        if (trim($codefilter) !== '') {
            $where[] = $DB->sql_like('c.coursecode', ':codefilter', false);
            $params['codefilter'] = '%' . $DB->sql_like_escape(trim($codefilter)) . '%';
        }

        $sql = "SELECT c.*, u.firstname, u.lastname
                  FROM {local_unifiedgrader_clib} c
             LEFT JOIN {user} u ON u.id = c.userid
                 WHERE " . implode(' AND ', $where) . "
              ORDER BY c.userid ASC, c.coursecode ASC, c.timecreated ASC";

        $records = $DB->get_records_sql($sql, $params);

        return self::build_csv($records, true);
    }

    /**
     * Render a set of clib records (optionally joined to owner name fields)
     * as a CSV string.
     *
     * @param array $records Rows from local_unifiedgrader_clib, keyed by id.
     * @param bool $includeowner Whether to add ownerid/ownername columns.
     * @return string
     */
    private static function build_csv(array $records, bool $includeowner): string {
        $tagnames = self::tag_names_for(array_keys($records));

        $fh = fopen('php://temp', 'r+');
        fputcsv($fh, $includeowner
            ? ['ownerid', 'ownername', 'coursecode', 'shared', 'tags', 'content']
            : ['coursecode', 'shared', 'tags', 'content'], ',', '"', self::CSV_ESCAPE);

        foreach ($records as $r) {
            $tags = implode(self::TAG_SEPARATOR, $tagnames[(int) $r->id] ?? []);
            $row = [$r->coursecode, $r->shared, $tags, $r->content];
            if ($includeowner) {
                $ownername = isset($r->firstname) ? trim($r->firstname . ' ' . $r->lastname) : '';
                array_unshift($row, $r->userid, $ownername);
            }
            fputcsv($fh, $row, ',', '"', self::CSV_ESCAPE);
        }

        rewind($fh);
        $csv = stream_get_contents($fh);
        fclose($fh);

        return $csv;
    }

    /**
     * Import comments from CSV text.
     *
     * @param string $csvcontent Raw file content.
     * @param int $defaultuserid Owner to use for rows with no usable ownerid column.
     * @param string|null $defaultcoursecode Code to use for rows with no coursecode column
     *                                       (or when $forcecoursecode is true, for every row).
     * @param bool $allowownercolumn When true (admin only), an "ownerid" column in the
     *                               file may override $defaultuserid per row.
     * @param bool $forcecoursecode When true, every row is filed under $defaultcoursecode
     *                              regardless of any coursecode column present — used for
     *                              "import into this bucket".
     * @return array{imported:int, skipped:int, errors:string[]}
     */
    public static function import(
        string $csvcontent,
        int $defaultuserid,
        ?string $defaultcoursecode = null,
        bool $allowownercolumn = false,
        bool $forcecoursecode = false,
    ): array {
        global $DB;

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $csvcontent);
        rewind($fh);

        $header = fgetcsv($fh, null, ',', '"', self::CSV_ESCAPE);
        if ($header === false) {
            fclose($fh);
            return ['imported' => 0, 'skipped' => 0, 'errors' => [get_string('clibcsv_empty_file', 'local_unifiedgrader')]];
        }

        $header = array_map(fn($h) => strtolower(trim((string) $h)), $header);
        $colindex = array_flip($header);

        if (!isset($colindex['content'])) {
            fclose($fh);
            return [
                'imported' => 0,
                'skipped' => 0,
                'errors' => [get_string('clibcsv_missing_content_column', 'local_unifiedgrader')],
            ];
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $tagcache = [];
        $now = time();
        $rownum = 1;

        while (($row = fgetcsv($fh, null, ',', '"', self::CSV_ESCAPE)) !== false) {
            $rownum++;

            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue;
            }

            $content = trim((string) ($row[$colindex['content']] ?? ''));
            if ($content === '') {
                $skipped++;
                continue;
            }

            $userid = $defaultuserid;
            if ($allowownercolumn && isset($colindex['ownerid'])) {
                $ownerraw = trim((string) ($row[$colindex['ownerid']] ?? ''));
                if ($ownerraw !== '') {
                    $candidate = (int) $ownerraw;
                    if ($candidate > 0 && $DB->record_exists('user', ['id' => $candidate, 'deleted' => 0])) {
                        $userid = $candidate;
                    } else {
                        $errors[] = get_string('clibcsv_row_bad_owner', 'local_unifiedgrader', $rownum);
                        continue;
                    }
                }
            }
            if ($userid <= 0) {
                $errors[] = get_string('clibcsv_row_no_owner', 'local_unifiedgrader', $rownum);
                continue;
            }

            $coursecode = (string) ($defaultcoursecode ?? '');
            if (!$forcecoursecode && isset($colindex['coursecode'])) {
                $coursecode = trim((string) ($row[$colindex['coursecode']] ?? ''));
            }

            $shared = 0;
            if (isset($colindex['shared'])) {
                $rawshared = strtolower(trim((string) ($row[$colindex['shared']] ?? '')));
                $shared = in_array($rawshared, ['1', 'true', 'yes'], true) ? 1 : 0;
            }

            // Skip an exact duplicate for this owner/scope — importing must
            // not multiply a library that may already contain the row, and
            // makes re-running an import (e.g. after fixing earlier errors)
            // harmless rather than adding a second copy of everything that
            // already succeeded.
            $exists = $DB->record_exists_select(
                'local_unifiedgrader_clib',
                'userid = :userid AND coursecode = :coursecode AND ' .
                    $DB->sql_compare_text('content', 255) . ' = ' . $DB->sql_compare_text(':content', 255),
                ['userid' => $userid, 'coursecode' => $coursecode, 'content' => $content],
            );
            if ($exists) {
                $skipped++;
                continue;
            }

            $commentid = $DB->insert_record('local_unifiedgrader_clib', (object) [
                'userid' => $userid,
                'coursecode' => $coursecode,
                'content' => $content,
                'shared' => $shared,
                'sortorder' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);

            if (isset($colindex['tags'])) {
                $tagnames = array_filter(array_map(
                    'trim',
                    explode(self::TAG_SEPARATOR, (string) ($row[$colindex['tags']] ?? '')),
                ), fn($t) => $t !== '');

                foreach ($tagnames as $tagname) {
                    $cachekey = $userid . ':' . strtolower($tagname);
                    if (!isset($tagcache[$cachekey])) {
                        $tagcache[$cachekey] = self::find_or_create_tag($userid, $tagname);
                    }
                    $DB->insert_record('local_unifiedgrader_clmap', (object) [
                        'commentid' => $commentid,
                        'tagid' => $tagcache[$cachekey],
                    ]);
                }
            }

            $imported++;
        }

        fclose($fh);

        return ['imported' => $imported, 'skipped' => $skipped, 'errors' => $errors];
    }

    /**
     * Find an existing tag visible to this owner by name (their own tag
     * first, falling back to a system tag), or create a new personal tag.
     *
     * @param int $userid
     * @param string $name
     * @return int Tag id.
     */
    private static function find_or_create_tag(int $userid, string $name): int {
        global $DB;

        $matches = $DB->get_records_sql(
            "SELECT id, userid
               FROM {local_unifiedgrader_cltag}
              WHERE (userid = :userid OR userid = 0) AND " . $DB->sql_equal('name', ':name', false) . "
           ORDER BY userid DESC",
            ['userid' => $userid, 'name' => $name],
        );
        if (!empty($matches)) {
            return (int) reset($matches)->id;
        }

        return $DB->insert_record('local_unifiedgrader_cltag', (object) [
            'userid' => $userid,
            'name' => $name,
            'sortorder' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Batch-fetch tag names for a set of comment ids.
     *
     * @param int[] $commentids
     * @return array<int,string[]> commentid => list of tag names, in display order.
     */
    private static function tag_names_for(array $commentids): array {
        global $DB;

        if (empty($commentids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($commentids, SQL_PARAMS_NAMED, 'cid');
        $sql = "SELECT m.id, m.commentid, t.name
                  FROM {local_unifiedgrader_clmap} m
                  JOIN {local_unifiedgrader_cltag} t ON t.id = m.tagid
                 WHERE m.commentid {$insql}
              ORDER BY t.sortorder ASC, t.name ASC";

        $out = [];
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $out[(int) $row->commentid][] = $row->name;
        }

        return $out;
    }
}
