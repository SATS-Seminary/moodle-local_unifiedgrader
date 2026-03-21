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

namespace local_unifiedgrader\external;

use core_external\external_api;

/**
 * Tests for notes-related web service external functions.
 *
 * Covers get_notes, save_note, and delete_note.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\external\get_notes
 * @covers \local_unifiedgrader\external\save_note
 * @covers \local_unifiedgrader\external\delete_note
 */
final class notes_webservices_test extends \advanced_testcase {
    /**
     * Helper: create a grading scenario and set the teacher as current user.
     *
     * @param array $options Options passed to create_grading_scenario.
     * @return \stdClass Scenario object with course, activity, cm, context, teacher, students.
     */
    private function create_scenario(array $options = []): \stdClass {
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('assign', $options);
        $this->setUser($scenario->teacher);
        return $scenario;
    }

    // Get_notes tests.

    /**
     * Test get_notes returns notes created by the teacher.
     */
    public function test_get_notes_happy_path(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        // Create a note via save_note first.
        save_note::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            '<p>This is a note</p>',
        );

        $result = get_notes::execute($scenario->cm->id, $scenario->students[0]->id);

        $this->assertCount(1, $result);
        $this->assertEquals($scenario->cm->id, $result[0]['cmid']);
        $this->assertEquals($scenario->students[0]->id, $result[0]['userid']);
        $this->assertStringContainsString('This is a note', $result[0]['rawcontent']);
    }

    /**
     * Test get_notes throws when user lacks the viewnotes capability.
     */
    public function test_get_notes_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $this->setUser($scenario->students[0]);

        $this->expectException(\required_capability_exception::class);
        get_notes::execute($scenario->cm->id, $scenario->students[0]->id);
    }

    /**
     * Test get_notes return value passes clean_returnvalue validation.
     */
    public function test_get_notes_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        save_note::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Test note content',
        );

        $result = get_notes::execute($scenario->cm->id, $scenario->students[0]->id);

        $cleaned = external_api::clean_returnvalue(
            get_notes::execute_returns(),
            $result,
        );

        $this->assertCount(1, $cleaned);
        $this->assertIsInt($cleaned[0]['id']);
        $this->assertIsInt($cleaned[0]['cmid']);
        $this->assertIsInt($cleaned[0]['userid']);
        $this->assertIsInt($cleaned[0]['authorid']);
        $this->assertIsString($cleaned[0]['authorname']);
        $this->assertIsString($cleaned[0]['content']);
        $this->assertIsString($cleaned[0]['rawcontent']);
        $this->assertIsInt($cleaned[0]['timecreated']);
        $this->assertIsInt($cleaned[0]['timemodified']);
    }

    /**
     * Test get_notes returns empty array when no notes exist.
     */
    public function test_get_notes_empty(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $result = get_notes::execute($scenario->cm->id, $scenario->students[0]->id);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // Save_note tests.

    /**
     * Test save_note creates a new note and returns its ID.
     */
    public function test_save_note_happy_path(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $result = save_note::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            '<p>New note</p>',
        );

        $this->assertArrayHasKey('noteid', $result);
        $this->assertGreaterThan(0, $result['noteid']);

        // Verify the note exists via get_notes.
        $notes = get_notes::execute($scenario->cm->id, $scenario->students[0]->id);
        $this->assertCount(1, $notes);
        $this->assertEquals($result['noteid'], $notes[0]['id']);
    }

    /**
     * Test save_note throws when user lacks the managenotes capability.
     */
    public function test_save_note_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $this->setUser($scenario->students[0]);

        $this->expectException(\required_capability_exception::class);
        save_note::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Should not be saved',
        );
    }

    /**
     * Test save_note return value passes clean_returnvalue validation.
     */
    public function test_save_note_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $result = save_note::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Valid note',
        );

        $cleaned = external_api::clean_returnvalue(
            save_note::execute_returns(),
            $result,
        );

        $this->assertIsInt($cleaned['noteid']);
        $this->assertGreaterThan(0, $cleaned['noteid']);
    }

    /**
     * Test save_note updates an existing note when noteid is provided.
     */
    public function test_save_note_update_existing(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        // Create a note.
        $result = save_note::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Original content',
        );
        $noteid = $result['noteid'];

        // Update the note.
        $result = save_note::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Updated content',
            $noteid,
        );

        $this->assertEquals($noteid, $result['noteid']);

        // Verify content is updated.
        $notes = get_notes::execute($scenario->cm->id, $scenario->students[0]->id);
        $this->assertCount(1, $notes);
        $this->assertEquals('Updated content', $notes[0]['rawcontent']);
    }

    // Delete_note tests.

    /**
     * Test delete_note removes a note successfully.
     */
    public function test_delete_note_happy_path(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        // Create a note.
        $result = save_note::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'To be deleted',
        );
        $noteid = $result['noteid'];

        // Delete the note.
        $result = delete_note::execute($scenario->cm->id, $noteid);
        $this->assertTrue($result['success']);

        // Verify it is gone.
        $notes = get_notes::execute($scenario->cm->id, $scenario->students[0]->id);
        $this->assertEmpty($notes);
    }

    /**
     * Test delete_note throws when user lacks the managenotes capability.
     */
    public function test_delete_note_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        // Create a note as teacher.
        $result = save_note::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Note to delete',
        );
        $noteid = $result['noteid'];

        // Try to delete as student.
        $this->setUser($scenario->students[0]);

        $this->expectException(\required_capability_exception::class);
        delete_note::execute($scenario->cm->id, $noteid);
    }

    /**
     * Test delete_note return value passes clean_returnvalue validation.
     */
    public function test_delete_note_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $saveresult = save_note::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Note for validation',
        );

        $result = delete_note::execute($scenario->cm->id, $saveresult['noteid']);

        $cleaned = external_api::clean_returnvalue(
            delete_note::execute_returns(),
            $result,
        );

        $this->assertIsBool($cleaned['success']);
        $this->assertTrue($cleaned['success']);
    }

    /**
     * Test delete_note does not throw when deleting a nonexistent note.
     */
    public function test_delete_note_nonexistent(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        // Deleting a nonexistent note should succeed gracefully.
        $result = delete_note::execute($scenario->cm->id, 99999);
        $this->assertTrue($result['success']);
    }
}
