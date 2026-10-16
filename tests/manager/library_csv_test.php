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

namespace local_unifiedgrader;

/**
 * Tests for CSV export/import of the comment library.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\library_csv
 */
final class library_csv_test extends \advanced_testcase {
    /**
     * Parse a CSV string into an array of associative rows keyed by header.
     *
     * @param string $csv
     * @return array
     */
    private function parse(string $csv): array {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $csv);
        rewind($fh);
        $header = fgetcsv($fh, null, ',', '"', '\\');
        $rows = [];
        while (($row = fgetcsv($fh, null, ',', '"', '\\')) !== false) {
            $rows[] = array_combine($header, $row);
        }
        fclose($fh);
        return $rows;
    }

    /**
     * A single-owner export has no ownerid column and round-trips content,
     * course code, shared flag and tags.
     */
    public function test_export_for_owner(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $plugingen = $generator->get_plugin_generator('local_unifiedgrader');

        $teacher = $generator->create_user();
        $comment = $plugingen->create_library_comment([
            'userid' => $teacher->id,
            'coursecode' => 'BIB3129',
            'content' => 'Good structure',
            'shared' => 1,
        ]);
        $tag = $plugingen->create_library_tag(['userid' => $teacher->id, 'name' => 'Structure']);
        $plugingen->create_tag_mapping(['commentid' => $comment->id, 'tagid' => $tag->id]);

        $csv = library_csv::export_for_owner($teacher->id);
        $rows = $this->parse($csv);

        $this->assertCount(1, $rows);
        $this->assertArrayNotHasKey('ownerid', $rows[0]);
        $this->assertSame('BIB3129', $rows[0]['coursecode']);
        $this->assertSame('1', $rows[0]['shared']);
        $this->assertSame('Structure', $rows[0]['tags']);
        $this->assertSame('Good structure', $rows[0]['content']);
    }

    /**
     * A cross-owner export adds ownerid/ownername columns.
     */
    public function test_export_for_filters_includes_owner_columns(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $plugingen = $generator->get_plugin_generator('local_unifiedgrader');

        $teacher = $generator->create_user(['firstname' => 'Jane', 'lastname' => 'Doe']);
        $plugingen->create_library_comment([
            'userid' => $teacher->id,
            'coursecode' => 'BIB3129',
            'content' => 'Nicely argued',
        ]);

        $csv = library_csv::export_for_filters();
        $rows = $this->parse($csv);

        $this->assertCount(1, $rows);
        $this->assertSame((string) $teacher->id, $rows[0]['ownerid']);
        $this->assertSame('Jane Doe', $rows[0]['ownername']);
        $this->assertSame('BIB3129', $rows[0]['coursecode']);
    }

    /**
     * export_for_filters() respects the same owner/code filters the
     * moderation page's inventory table uses.
     */
    public function test_export_for_filters_respects_filters(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $plugingen = $generator->get_plugin_generator('local_unifiedgrader');

        $one = $generator->create_user();
        $two = $generator->create_user();
        $plugingen->create_library_comment(['userid' => $one->id, 'coursecode' => 'BIB3129', 'content' => 'A']);
        $plugingen->create_library_comment(['userid' => $two->id, 'coursecode' => 'BIB3129', 'content' => 'B']);

        $rows = $this->parse(library_csv::export_for_filters($one->id));

        $this->assertCount(1, $rows);
        $this->assertSame((string) $one->id, $rows[0]['ownerid']);
    }

    /**
     * Content is the only required column; coursecode, shared and tags all
     * fall back sensibly when absent.
     */
    public function test_import_minimal_file(): void {
        global $DB;
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();

        $csv = "content\nJust a comment\n";
        $result = library_csv::import($csv, $teacher->id);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame([], $result['errors']);

        $record = $DB->get_record('local_unifiedgrader_clib', ['userid' => $teacher->id]);
        $this->assertSame('Just a comment', $record->content);
        $this->assertSame('', $record->coursecode);
        $this->assertSame(0, (int) $record->shared);
    }

    /**
     * A full-shape file sets course code, shared and tags, creating tags
     * that don't yet exist for this owner.
     */
    public function test_import_full_file_creates_tags(): void {
        global $DB;
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();

        $csv = "coursecode,shared,tags,content\n"
            . "BIB3129,1,Grammar|Structure,\"Watch your, commas\"\n";
        $result = library_csv::import($csv, $teacher->id);

        $this->assertSame(1, $result['imported']);
        $record = $DB->get_record('local_unifiedgrader_clib', ['userid' => $teacher->id]);
        $this->assertSame('BIB3129', $record->coursecode);
        $this->assertSame(1, (int) $record->shared);
        $this->assertSame('Watch your, commas', $record->content);

        $tagnames = $DB->get_fieldset_sql(
            "SELECT t.name
               FROM {local_unifiedgrader_clmap} m
               JOIN {local_unifiedgrader_cltag} t ON t.id = m.tagid
              WHERE m.commentid = ?
           ORDER BY t.name ASC",
            [$record->id],
        );
        $this->assertSame(['Grammar', 'Structure'], $tagnames);
    }

    /**
     * Importing a row that already exists for this owner/scope is skipped,
     * not duplicated — the same additive-and-idempotent contract as
     * library_audit::import_legacy().
     */
    public function test_import_skips_existing_duplicate(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $teacher = $generator->create_user();
        $generator->get_plugin_generator('local_unifiedgrader')->create_library_comment([
            'userid' => $teacher->id,
            'coursecode' => 'BIB3129',
            'content' => 'Already here',
        ]);

        $csv = "coursecode,content\nBIB3129,Already here\n";
        $result = library_csv::import($csv, $teacher->id);

        $this->assertSame(0, $result['imported']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, $DB->count_records('local_unifiedgrader_clib', ['userid' => $teacher->id]));
    }

    /**
     * A blank content cell is skipped rather than imported as an empty comment.
     */
    public function test_import_skips_blank_content(): void {
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();

        // A genuinely blank line (no columns at all) is a formatting
        // artifact and is ignored outright; a row with a present-but-empty
        // content cell is what actually exercises the "skipped" count.
        $csv = "coursecode,content\nBIB3129,\nBIB3129,Real comment\n";
        $result = library_csv::import($csv, $teacher->id);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, $result['skipped']);
    }

    /**
     * A file with no header (or an empty file) is reported as an error,
     * not silently imported as zero rows.
     */
    public function test_import_empty_file_reports_error(): void {
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();

        $result = library_csv::import('', $teacher->id);

        $this->assertSame(0, $result['imported']);
        $this->assertNotEmpty($result['errors']);
    }

    /**
     * A file missing the required "content" column is rejected outright.
     */
    public function test_import_missing_content_column_reports_error(): void {
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();

        $result = library_csv::import("coursecode,tags\nBIB3129,Foo\n", $teacher->id);

        $this->assertSame(0, $result['imported']);
        $this->assertNotEmpty($result['errors']);
    }

    /**
     * With $allowownercolumn=true, a per-row "ownerid" column overrides the
     * default owner — this is what lets an admin re-import a cross-owner
     * export.
     */
    public function test_import_ownercolumn_overrides_default_when_allowed(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $target = $generator->create_user();
        $fallback = $generator->create_user();

        $csv = "ownerid,content\n{$target->id},Routed by owner column\n";
        $result = library_csv::import($csv, $fallback->id, null, true);

        $this->assertSame(1, $result['imported']);
        $recordrows = $DB->get_records('local_unifiedgrader_clib', ['userid' => $target->id]);
        $record = reset($recordrows);
        $this->assertSame((int) $target->id, (int) $record->userid);
    }

    /**
     * With $allowownercolumn=false (the teacher self-service path), an
     * "ownerid" column in the file is ignored — a teacher cannot import
     * rows into someone else's library even by doctoring the CSV.
     */
    public function test_import_ownercolumn_ignored_when_not_allowed(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $teacher = $generator->create_user();
        $someoneelse = $generator->create_user();

        $csv = "ownerid,content\n{$someoneelse->id},Should stay with me\n";
        $result = library_csv::import($csv, $teacher->id, null, false);

        $this->assertSame(1, $result['imported']);
        $recordrows = $DB->get_records('local_unifiedgrader_clib', ['userid' => $teacher->id]);
        $record = reset($recordrows);
        $this->assertSame((int) $teacher->id, (int) $record->userid);
    }

    /**
     * An ownerid that doesn't match an existing, active user is reported
     * as a per-row error rather than silently falling back to the default
     * owner or the system bucket.
     */
    public function test_import_invalid_ownercolumn_reports_error(): void {
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();

        $csv = "ownerid,content\n999999,Orphaned row\n";
        $result = library_csv::import($csv, $teacher->id, null, true);

        $this->assertSame(0, $result['imported']);
        $this->assertNotEmpty($result['errors']);
    }

    /**
     * $forcecoursecode=true (bucket-scoped import) ignores any coursecode
     * column in the file and always uses the target bucket's code.
     */
    public function test_import_forces_coursecode_when_requested(): void {
        global $DB;
        $this->resetAfterTest();
        $teacher = $this->getDataGenerator()->create_user();

        $csv = "coursecode,content\nWRONGCODE,Filed into the bucket\n";
        $result = library_csv::import($csv, $teacher->id, 'BIB3129', false, true);

        $this->assertSame(1, $result['imported']);
        $recordrows = $DB->get_records('local_unifiedgrader_clib', ['userid' => $teacher->id]);
        $record = reset($recordrows);
        $this->assertSame('BIB3129', $record->coursecode);
    }

    /**
     * An existing personal tag is reused rather than creating a second tag
     * with the same name.
     */
    public function test_import_reuses_existing_tag_case_insensitively(): void {
        global $DB;
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $plugingen = $generator->get_plugin_generator('local_unifiedgrader');
        $teacher = $generator->create_user();
        $tag = $plugingen->create_library_tag(['userid' => $teacher->id, 'name' => 'Grammar']);

        $csv = "tags,content\ngrammar,Uses the existing tag\n";
        library_csv::import($csv, $teacher->id);

        $this->assertSame(1, $DB->count_records('local_unifiedgrader_cltag', [
            'userid' => $teacher->id,
            'name' => 'Grammar',
        ]));
        $commentrows = $DB->get_records('local_unifiedgrader_clib', ['userid' => $teacher->id]);
        $comment = reset($commentrows);
        $this->assertTrue($DB->record_exists('local_unifiedgrader_clmap', [
            'commentid' => $comment->id,
            'tagid' => $tag->id,
        ]));
    }
}
