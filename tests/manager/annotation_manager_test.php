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
 * Tests for the annotation_manager class.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\annotation_manager
 */
final class annotation_manager_test extends \advanced_testcase {

    /**
     * Test saving a new annotation.
     */
    public function test_save_new_annotation(): void {
        global $DB;
        $this->resetAfterTest();

        $data = '{"objects":[{"type":"Rect"}]}';
        $result = annotation_manager::save_annotations(1, 2, 3, 100, [
            ['pagenum' => 1, 'annotationdata' => $data],
        ]);

        $this->assertTrue($result);
        $record = $DB->get_record('local_unifiedgrader_annot', [
            'cmid' => 1, 'userid' => 2, 'authorid' => 3, 'fileid' => 100, 'pagenum' => 1,
        ]);
        $this->assertNotFalse($record);
        $this->assertEquals($data, $record->annotationdata);
    }

    /**
     * Test updating an existing annotation.
     */
    public function test_update_existing_annotation(): void {
        global $DB;
        $this->resetAfterTest();

        $original = '{"objects":[{"type":"Rect"}]}';
        annotation_manager::save_annotations(1, 2, 3, 100, [
            ['pagenum' => 1, 'annotationdata' => $original],
        ]);

        $updated = '{"objects":[{"type":"Circle"}]}';
        annotation_manager::save_annotations(1, 2, 3, 100, [
            ['pagenum' => 1, 'annotationdata' => $updated],
        ]);

        $records = $DB->get_records('local_unifiedgrader_annot', [
            'cmid' => 1, 'userid' => 2, 'fileid' => 100, 'pagenum' => 1,
        ]);
        $this->assertCount(1, $records);
        $this->assertEquals($updated, reset($records)->annotationdata);
    }

    /**
     * Test that saving empty data deletes an existing record.
     */
    public function test_save_empty_data_deletes_existing(): void {
        global $DB;
        $this->resetAfterTest();

        annotation_manager::save_annotations(1, 2, 3, 100, [
            ['pagenum' => 1, 'annotationdata' => '{"objects":[{"type":"Rect"}]}'],
        ]);
        $this->assertTrue($DB->record_exists('local_unifiedgrader_annot', [
            'cmid' => 1, 'userid' => 2, 'fileid' => 100,
        ]));

        // Save empty data.
        annotation_manager::save_annotations(1, 2, 3, 100, [
            ['pagenum' => 1, 'annotationdata' => '{}'],
        ]);
        $this->assertFalse($DB->record_exists('local_unifiedgrader_annot', [
            'cmid' => 1, 'userid' => 2, 'fileid' => 100,
        ]));
    }

    /**
     * Test that saving empty objects array deletes existing record.
     */
    public function test_save_empty_objects_deletes_existing(): void {
        global $DB;
        $this->resetAfterTest();

        annotation_manager::save_annotations(1, 2, 3, 100, [
            ['pagenum' => 1, 'annotationdata' => '{"objects":[{"type":"Rect"}]}'],
        ]);

        annotation_manager::save_annotations(1, 2, 3, 100, [
            ['pagenum' => 1, 'annotationdata' => '{"objects":[]}'],
        ]);
        $this->assertFalse($DB->record_exists('local_unifiedgrader_annot', [
            'cmid' => 1, 'userid' => 2, 'fileid' => 100,
        ]));
    }

    /**
     * Test that saving empty data for a nonexistent page does nothing.
     */
    public function test_save_new_empty_ignored(): void {
        global $DB;
        $this->resetAfterTest();

        annotation_manager::save_annotations(1, 2, 3, 100, [
            ['pagenum' => 5, 'annotationdata' => '{}'],
        ]);

        $this->assertFalse($DB->record_exists('local_unifiedgrader_annot', [
            'cmid' => 1, 'userid' => 2, 'fileid' => 100,
        ]));
    }

    /**
     * Test batch saving multiple pages.
     */
    public function test_batch_save_multiple_pages(): void {
        global $DB;
        $this->resetAfterTest();

        annotation_manager::save_annotations(1, 2, 3, 100, [
            ['pagenum' => 1, 'annotationdata' => '{"objects":[{"type":"Rect"}]}'],
            ['pagenum' => 2, 'annotationdata' => '{"objects":[{"type":"Circle"}]}'],
            ['pagenum' => 3, 'annotationdata' => '{"objects":[{"type":"FabricText"}]}'],
        ]);

        $count = $DB->count_records('local_unifiedgrader_annot', [
            'cmid' => 1, 'userid' => 2, 'fileid' => 100,
        ]);
        $this->assertEquals(3, $count);
    }

    /**
     * Test getting annotations returns ordered by pagenum.
     */
    public function test_get_annotations_returns_ordered_by_pagenum(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');

        // Insert in reverse order.
        $gen->create_annotation(['cmid' => 1, 'userid' => 2, 'authorid' => 3, 'fileid' => 100, 'pagenum' => 3]);
        $gen->create_annotation(['cmid' => 1, 'userid' => 2, 'authorid' => 3, 'fileid' => 100, 'pagenum' => 1]);
        $gen->create_annotation(['cmid' => 1, 'userid' => 2, 'authorid' => 3, 'fileid' => 100, 'pagenum' => 2]);

        $annots = annotation_manager::get_annotations(1, 2, 100);
        $this->assertCount(3, $annots);
        $this->assertEquals(1, $annots[0]['pagenum']);
        $this->assertEquals(2, $annots[1]['pagenum']);
        $this->assertEquals(3, $annots[2]['pagenum']);
    }

    /**
     * Test that get_annotations is scoped to the correct fileid.
     */
    public function test_get_annotations_scoped_to_file(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');

        $gen->create_annotation(['cmid' => 1, 'userid' => 2, 'authorid' => 3, 'fileid' => 100, 'pagenum' => 1]);
        $gen->create_annotation(['cmid' => 1, 'userid' => 2, 'authorid' => 3, 'fileid' => 200, 'pagenum' => 1]);

        $annots = annotation_manager::get_annotations(1, 2, 100);
        $this->assertCount(1, $annots);
        $this->assertEquals(100, $annots[0]['fileid']);
    }

    /**
     * Test get_annotations returns correct structure.
     */
    public function test_get_annotations_returns_correct_structure(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $gen->create_annotation([
            'cmid' => 1, 'userid' => 2, 'authorid' => 3, 'fileid' => 100,
            'pagenum' => 1, 'annotationdata' => '{"objects":[]}',
        ]);

        $annots = annotation_manager::get_annotations(1, 2, 100);
        $this->assertCount(1, $annots);

        $keys = ['id', 'cmid', 'userid', 'authorid', 'fileid', 'pagenum', 'annotationdata', 'timecreated', 'timemodified'];
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $annots[0]);
        }
    }

    /**
     * Test deleting annotations for a file.
     */
    public function test_delete_annotations(): void {
        global $DB;
        $this->resetAfterTest();

        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $gen->create_annotation(['cmid' => 1, 'userid' => 2, 'authorid' => 3, 'fileid' => 100, 'pagenum' => 1]);
        $gen->create_annotation(['cmid' => 1, 'userid' => 2, 'authorid' => 3, 'fileid' => 100, 'pagenum' => 2]);

        annotation_manager::delete_annotations(1, 2, 100);

        $count = $DB->count_records('local_unifiedgrader_annot', ['cmid' => 1, 'userid' => 2, 'fileid' => 100]);
        $this->assertEquals(0, $count);
    }

    /**
     * Test deleting annotations is scoped to the correct file.
     */
    public function test_delete_annotations_scoped_to_file(): void {
        global $DB;
        $this->resetAfterTest();

        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $gen->create_annotation(['cmid' => 1, 'userid' => 2, 'authorid' => 3, 'fileid' => 100, 'pagenum' => 1]);
        $gen->create_annotation(['cmid' => 1, 'userid' => 2, 'authorid' => 3, 'fileid' => 200, 'pagenum' => 1]);

        annotation_manager::delete_annotations(1, 2, 100);

        $this->assertFalse($DB->record_exists('local_unifiedgrader_annot', ['fileid' => 100]));
        $this->assertTrue($DB->record_exists('local_unifiedgrader_annot', ['fileid' => 200]));
    }
}
