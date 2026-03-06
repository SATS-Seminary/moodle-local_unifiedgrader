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
 * Tests for the notes_manager class.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\notes_manager
 */
final class notes_manager_test extends \advanced_testcase {

    /**
     * Test creating a new note.
     */
    public function test_create_note(): void {
        global $DB;
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $teacher = $gen->create_user();
        $student = $gen->create_user();

        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $noteid = notes_manager::save_note($cm->id, $student->id, $teacher->id, '<p>Good work!</p>');

        $this->assertGreaterThan(0, $noteid);

        $record = $DB->get_record('local_unifiedgrader_notes', ['id' => $noteid]);
        $this->assertNotFalse($record);
        $this->assertEquals($cm->id, $record->cmid);
        $this->assertEquals($student->id, $record->userid);
        $this->assertEquals($teacher->id, $record->authorid);
        $this->assertEquals('<p>Good work!</p>', $record->content);
    }

    /**
     * Test updating an existing note.
     */
    public function test_update_note(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $teacher = $gen->create_user();
        $student = $gen->create_user();

        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $noteid = notes_manager::save_note($cm->id, $student->id, $teacher->id, 'Original');

        // Update.
        $returnedid = notes_manager::save_note($cm->id, $student->id, $teacher->id, 'Updated', $noteid);
        $this->assertEquals($noteid, $returnedid);

        $notes = notes_manager::get_notes($cm->id, $student->id);
        $this->assertCount(1, $notes);
        $this->assertEquals('Updated', $notes[0]['rawcontent']);
    }

    /**
     * Test getting notes returns empty for no data.
     */
    public function test_get_notes_empty(): void {
        $this->resetAfterTest();

        $notes = notes_manager::get_notes(999, 999);
        $this->assertIsArray($notes);
        $this->assertEmpty($notes);
    }

    /**
     * Test notes return the correct structure.
     */
    public function test_get_notes_returns_correct_structure(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $teacher = $gen->create_user(['firstname' => 'John', 'lastname' => 'Teacher']);
        $student = $gen->create_user();

        $cm = get_coursemodule_from_instance('assign', $assign->id);
        notes_manager::save_note($cm->id, $student->id, $teacher->id, '<b>Note content</b>');

        $notes = notes_manager::get_notes($cm->id, $student->id);
        $this->assertCount(1, $notes);

        $note = $notes[0];
        $this->assertArrayHasKey('id', $note);
        $this->assertArrayHasKey('cmid', $note);
        $this->assertArrayHasKey('userid', $note);
        $this->assertArrayHasKey('authorid', $note);
        $this->assertArrayHasKey('authorname', $note);
        $this->assertArrayHasKey('content', $note);
        $this->assertArrayHasKey('rawcontent', $note);
        $this->assertArrayHasKey('timecreated', $note);
        $this->assertArrayHasKey('timemodified', $note);

        $this->assertEquals($cm->id, $note['cmid']);
        $this->assertEquals($student->id, $note['userid']);
        $this->assertEquals($teacher->id, $note['authorid']);
        $this->assertEquals('<b>Note content</b>', $note['rawcontent']);
        $this->assertStringContainsString('John Teacher', $note['authorname']);
    }

    /**
     * Test notes are ordered by timecreated DESC.
     */
    public function test_get_notes_ordered_by_timecreated_desc(): void {
        global $DB;
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $teacher = $gen->create_user();
        $student = $gen->create_user();
        $cm = get_coursemodule_from_instance('assign', $assign->id);

        // Insert notes with controlled timestamps.
        $plugingen = $gen->get_plugin_generator('local_unifiedgrader');
        $note1 = $plugingen->create_note([
            'cmid' => $cm->id, 'userid' => $student->id, 'authorid' => $teacher->id,
            'content' => 'First', 'timecreated' => 1000,
        ]);
        $note2 = $plugingen->create_note([
            'cmid' => $cm->id, 'userid' => $student->id, 'authorid' => $teacher->id,
            'content' => 'Second', 'timecreated' => 2000,
        ]);
        $note3 = $plugingen->create_note([
            'cmid' => $cm->id, 'userid' => $student->id, 'authorid' => $teacher->id,
            'content' => 'Third', 'timecreated' => 3000,
        ]);

        $notes = notes_manager::get_notes($cm->id, $student->id);
        $this->assertCount(3, $notes);
        $this->assertEquals('Third', $notes[0]['rawcontent']);
        $this->assertEquals('Second', $notes[1]['rawcontent']);
        $this->assertEquals('First', $notes[2]['rawcontent']);
    }

    /**
     * Test notes are scoped to the correct cmid and userid.
     */
    public function test_get_notes_scoped_to_cmid_and_userid(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign1 = $gen->create_module('assign', ['course' => $course->id]);
        $assign2 = $gen->create_module('assign', ['course' => $course->id]);
        $teacher = $gen->create_user();
        $student1 = $gen->create_user();
        $student2 = $gen->create_user();

        $cm1 = get_coursemodule_from_instance('assign', $assign1->id);
        $cm2 = get_coursemodule_from_instance('assign', $assign2->id);

        notes_manager::save_note($cm1->id, $student1->id, $teacher->id, 'Note for s1 in a1');
        notes_manager::save_note($cm1->id, $student2->id, $teacher->id, 'Note for s2 in a1');
        notes_manager::save_note($cm2->id, $student1->id, $teacher->id, 'Note for s1 in a2');

        $notes = notes_manager::get_notes($cm1->id, $student1->id);
        $this->assertCount(1, $notes);
        $this->assertEquals('Note for s1 in a1', $notes[0]['rawcontent']);
    }

    /**
     * Test deleting a note.
     */
    public function test_delete_note(): void {
        global $DB;
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $teacher = $gen->create_user();
        $student = $gen->create_user();
        $cm = get_coursemodule_from_instance('assign', $assign->id);

        $noteid = notes_manager::save_note($cm->id, $student->id, $teacher->id, 'To delete');
        $this->assertTrue($DB->record_exists('local_unifiedgrader_notes', ['id' => $noteid]));

        notes_manager::delete_note($noteid);
        $this->assertFalse($DB->record_exists('local_unifiedgrader_notes', ['id' => $noteid]));
    }

    /**
     * Test deleting a nonexistent note does not throw.
     */
    public function test_delete_nonexistent_note_no_error(): void {
        $this->resetAfterTest();

        // Should not throw.
        notes_manager::delete_note(99999);
        $this->assertTrue(true);
    }

    /**
     * Test that content is formatted via format_text.
     */
    public function test_get_notes_format_text_applied(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $teacher = $gen->create_user();
        $student = $gen->create_user();
        $cm = get_coursemodule_from_instance('assign', $assign->id);

        notes_manager::save_note($cm->id, $student->id, $teacher->id, '<script>alert("x")</script><p>Clean</p>');

        $notes = notes_manager::get_notes($cm->id, $student->id);
        // format_text should strip script tags.
        $this->assertStringNotContainsString('<script>', $notes[0]['content']);
        $this->assertStringContainsString('Clean', $notes[0]['content']);
        // Raw content should be preserved.
        $this->assertStringContainsString('<script>', $notes[0]['rawcontent']);
    }

    /**
     * Test that author name is correctly resolved.
     */
    public function test_get_notes_authorname_resolved(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $teacher = $gen->create_user(['firstname' => 'Jane', 'lastname' => 'Doe']);
        $student = $gen->create_user();
        $cm = get_coursemodule_from_instance('assign', $assign->id);

        notes_manager::save_note($cm->id, $student->id, $teacher->id, 'Test');

        $notes = notes_manager::get_notes($cm->id, $student->id);
        $this->assertStringContainsString('Jane', $notes[0]['authorname']);
        $this->assertStringContainsString('Doe', $notes[0]['authorname']);
    }
}
