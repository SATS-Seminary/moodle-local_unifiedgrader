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

namespace local_unifiedgrader\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

/**
 * Tests for the privacy provider.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\privacy\provider
 */
final class provider_test extends provider_testcase {

    /**
     * Test that metadata declares all tables.
     */
    public function test_get_metadata(): void {
        $collection = new collection('local_unifiedgrader');
        $collection = provider::get_metadata($collection);

        $items = $collection->get_collection();
        $tables = array_map(fn($item) => $item->get_name(), $items);

        $this->assertContains('local_unifiedgrader_notes', $tables);
        $this->assertContains('local_unifiedgrader_comments', $tables);
        $this->assertContains('local_unifiedgrader_annot', $tables);
        $this->assertContains('local_unifiedgrader_prefs', $tables);
        $this->assertContains('local_unifiedgrader_clib', $tables);
        $this->assertContains('local_unifiedgrader_cltag', $tables);
    }

    /**
     * Test get_contexts_for_userid when user is a note subject.
     */
    public function test_get_contexts_for_userid_notes_subject(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $plugingen = $gen->get_plugin_generator('local_unifiedgrader');

        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $student = $gen->create_user();

        $plugingen->create_note([
            'cmid' => $cm->id,
            'userid' => $student->id,
            'authorid' => $teacher->id,
            'content' => 'Note about student',
        ]);

        $contextlist = provider::get_contexts_for_userid($student->id);
        $contextids = array_map('intval', $contextlist->get_contextids());
        $this->assertContains($context->id, $contextids);
    }

    /**
     * Test get_contexts_for_userid when user is a note author.
     */
    public function test_get_contexts_for_userid_notes_author(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $plugingen = $gen->get_plugin_generator('local_unifiedgrader');

        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $student = $gen->create_user();

        $plugingen->create_note([
            'cmid' => $cm->id,
            'userid' => $student->id,
            'authorid' => $teacher->id,
        ]);

        $contextlist = provider::get_contexts_for_userid($teacher->id);
        $contextids = array_map('intval', $contextlist->get_contextids());
        $this->assertContains($context->id, $contextids);
    }

    /**
     * Test get_contexts_for_userid for annotation data.
     */
    public function test_get_contexts_for_userid_annotations(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $plugingen = $gen->get_plugin_generator('local_unifiedgrader');

        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $student = $gen->create_user();

        $plugingen->create_annotation([
            'cmid' => $cm->id,
            'userid' => $student->id,
            'authorid' => $teacher->id,
            'fileid' => 100,
        ]);

        $contextlist = provider::get_contexts_for_userid($student->id);
        $contextids = array_map('intval', $contextlist->get_contextids());
        $this->assertContains($context->id, $contextids);
    }

    /**
     * Test get_users_in_context returns all users.
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $plugingen = $gen->get_plugin_generator('local_unifiedgrader');

        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $student = $gen->create_user();

        $plugingen->create_note([
            'cmid' => $cm->id,
            'userid' => $student->id,
            'authorid' => $teacher->id,
        ]);

        $userlist = new userlist($context, 'local_unifiedgrader');
        provider::get_users_in_context($userlist);

        $userids = $userlist->get_userids();
        $this->assertContains((int) $student->id, $userids);
        $this->assertContains((int) $teacher->id, $userids);
    }

    /**
     * Test export_user_data exports notes.
     */
    public function test_export_user_data_notes(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $plugingen = $gen->get_plugin_generator('local_unifiedgrader');

        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $student = $gen->create_user();

        $plugingen->create_note([
            'cmid' => $cm->id,
            'userid' => $student->id,
            'authorid' => $teacher->id,
            'content' => 'Private note about student',
        ]);

        $contextlist = new approved_contextlist($student, 'local_unifiedgrader', [$context->id]);
        provider::export_user_data($contextlist);

        $data = writer::with_context($context)->get_data([
            get_string('notes', 'local_unifiedgrader'),
        ]);
        $this->assertNotEmpty($data);
        $this->assertNotEmpty($data->notes);
        $this->assertEquals('Private note about student', $data->notes[0]['content']);
    }

    /**
     * Test export_user_data exports comment library v2.
     */
    public function test_export_user_data_clib(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $plugingen = $gen->get_plugin_generator('local_unifiedgrader');

        $user = $gen->create_user();
        $plugingen->create_library_comment([
            'userid' => $user->id,
            'coursecode' => 'BIB101',
            'content' => 'Reusable comment',
        ]);

        // Need at least one module context for the approved contextlist, even
        // though clib is exported at system context.
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $context = \context_module::instance($cm->id);

        // Also create a note to produce a valid context.
        $plugingen->create_note([
            'cmid' => $cm->id,
            'userid' => $user->id,
            'authorid' => $user->id,
        ]);

        $contextlist = new approved_contextlist($user, 'local_unifiedgrader', [$context->id]);
        provider::export_user_data($contextlist);

        $syscontext = \context_system::instance();
        $data = writer::with_context($syscontext)->get_data([
            get_string('clib_title', 'local_unifiedgrader'),
        ]);
        $this->assertNotEmpty($data);
        $this->assertNotEmpty($data->comments);
    }

    /**
     * Test delete_data_for_all_users_in_context deletes notes and annotations.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $plugingen = $gen->get_plugin_generator('local_unifiedgrader');

        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $student1 = $gen->create_user();
        $student2 = $gen->create_user();

        $plugingen->create_note(['cmid' => $cm->id, 'userid' => $student1->id, 'authorid' => $teacher->id]);
        $plugingen->create_note(['cmid' => $cm->id, 'userid' => $student2->id, 'authorid' => $teacher->id]);
        $plugingen->create_annotation(['cmid' => $cm->id, 'userid' => $student1->id, 'authorid' => $teacher->id, 'fileid' => 1]);

        $this->assertEquals(2, $DB->count_records('local_unifiedgrader_notes', ['cmid' => $cm->id]));
        $this->assertEquals(1, $DB->count_records('local_unifiedgrader_annot', ['cmid' => $cm->id]));

        provider::delete_data_for_all_users_in_context($context);

        $this->assertEquals(0, $DB->count_records('local_unifiedgrader_notes', ['cmid' => $cm->id]));
        $this->assertEquals(0, $DB->count_records('local_unifiedgrader_annot', ['cmid' => $cm->id]));
    }

    /**
     * Test delete_data_for_user deletes only that user's data.
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $plugingen = $gen->get_plugin_generator('local_unifiedgrader');

        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $student1 = $gen->create_user();
        $student2 = $gen->create_user();

        $plugingen->create_note(['cmid' => $cm->id, 'userid' => $student1->id, 'authorid' => $teacher->id]);
        $plugingen->create_note(['cmid' => $cm->id, 'userid' => $student2->id, 'authorid' => $teacher->id]);
        $plugingen->create_library_comment(['userid' => $student1->id, 'content' => 'Student1 comment']);
        $plugingen->create_preference(['userid' => $student1->id]);

        $contextlist = new approved_contextlist($student1, 'local_unifiedgrader', [$context->id]);
        provider::delete_data_for_user($contextlist);

        // Student1's note should be gone.
        $this->assertEquals(0, $DB->count_records('local_unifiedgrader_notes', ['userid' => $student1->id]));
        // Student2's note should remain.
        $this->assertEquals(1, $DB->count_records('local_unifiedgrader_notes', ['userid' => $student2->id]));
        // Student1's library data should be gone.
        $this->assertEquals(0, $DB->count_records('local_unifiedgrader_clib', ['userid' => $student1->id]));
        $this->assertEquals(0, $DB->count_records('local_unifiedgrader_prefs', ['userid' => $student1->id]));
    }

    /**
     * Test delete_data_for_users deletes multiple users' data.
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $plugingen = $gen->get_plugin_generator('local_unifiedgrader');

        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $student1 = $gen->create_user();
        $student2 = $gen->create_user();
        $student3 = $gen->create_user();

        $plugingen->create_note(['cmid' => $cm->id, 'userid' => $student1->id, 'authorid' => $teacher->id]);
        $plugingen->create_note(['cmid' => $cm->id, 'userid' => $student2->id, 'authorid' => $teacher->id]);
        $plugingen->create_note(['cmid' => $cm->id, 'userid' => $student3->id, 'authorid' => $teacher->id]);

        $userlist = new approved_userlist(
            $context,
            'local_unifiedgrader',
            [$student1->id, $student2->id],
        );
        provider::delete_data_for_users($userlist);

        $this->assertEquals(0, $DB->count_records('local_unifiedgrader_notes', ['userid' => $student1->id]));
        $this->assertEquals(0, $DB->count_records('local_unifiedgrader_notes', ['userid' => $student2->id]));
        // Student3 should be untouched.
        $this->assertEquals(1, $DB->count_records('local_unifiedgrader_notes', ['userid' => $student3->id]));
    }
}
