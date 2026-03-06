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
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
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
        $this->assertContains('local_unifiedgrader_penalty', $tables);
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

    // -------------------------------------------------------------------------
    // Penalty-specific privacy tests.
    // -------------------------------------------------------------------------

    /**
     * Helper: create a penalty record directly in the DB.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @param int $authorid Teacher user ID.
     * @param string $category Penalty category.
     * @param string $label Penalty label.
     * @param int $percentage Penalty percentage.
     * @return \stdClass The created record.
     */
    private function create_penalty(
        int $cmid,
        int $userid,
        int $authorid,
        string $category = 'wordcount',
        string $label = '',
        int $percentage = 10,
    ): \stdClass {
        global $DB;
        $now = time();
        $record = (object) [
            'cmid' => $cmid,
            'userid' => $userid,
            'authorid' => $authorid,
            'category' => $category,
            'label' => $label,
            'percentage' => $percentage,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $record->id = $DB->insert_record('local_unifiedgrader_penalty', $record);
        return $record;
    }

    /**
     * Test get_contexts_for_userid returns contexts for penalty subjects.
     */
    public function test_get_contexts_for_userid_penalty_subject(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $student = $gen->create_user();

        $this->create_penalty($cm->id, $student->id, $teacher->id);

        $contextlist = provider::get_contexts_for_userid($student->id);
        $contextids = array_map('intval', $contextlist->get_contextids());
        $this->assertContains($context->id, $contextids);
    }

    /**
     * Test get_contexts_for_userid returns contexts for penalty authors.
     */
    public function test_get_contexts_for_userid_penalty_author(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $student = $gen->create_user();

        $this->create_penalty($cm->id, $student->id, $teacher->id);

        $contextlist = provider::get_contexts_for_userid($teacher->id);
        $contextids = array_map('intval', $contextlist->get_contextids());
        $this->assertContains($context->id, $contextids);
    }

    /**
     * Test get_users_in_context returns penalty subjects and authors.
     */
    public function test_get_users_in_context_penalties(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $student = $gen->create_user();

        $this->create_penalty($cm->id, $student->id, $teacher->id);

        $userlist = new userlist($context, 'local_unifiedgrader');
        provider::get_users_in_context($userlist);

        $userids = $userlist->get_userids();
        $this->assertContains((int) $student->id, $userids);
        $this->assertContains((int) $teacher->id, $userids);
    }

    /**
     * Test export_user_data exports penalties.
     */
    public function test_export_user_data_penalties(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $student = $gen->create_user();

        $this->create_penalty($cm->id, $student->id, $teacher->id, 'wordcount', '', 10);
        $this->create_penalty($cm->id, $student->id, $teacher->id, 'other', 'Late', 5);

        $contextlist = new approved_contextlist($student, 'local_unifiedgrader', [$context->id]);
        provider::export_user_data($contextlist);

        $data = writer::with_context($context)->get_data([
            get_string('penalties', 'local_unifiedgrader'),
        ]);
        $this->assertNotEmpty($data);
        $this->assertNotEmpty($data->penalties);
        $this->assertCount(2, $data->penalties);
    }

    /**
     * Test delete_data_for_all_users_in_context deletes penalties.
     */
    public function test_delete_data_for_all_users_in_context_penalties(): void {
        global $DB;
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $student1 = $gen->create_user();
        $student2 = $gen->create_user();

        $this->create_penalty($cm->id, $student1->id, $teacher->id);
        $this->create_penalty($cm->id, $student2->id, $teacher->id);

        $this->assertEquals(2, $DB->count_records('local_unifiedgrader_penalty', ['cmid' => $cm->id]));

        provider::delete_data_for_all_users_in_context($context);

        $this->assertEquals(0, $DB->count_records('local_unifiedgrader_penalty', ['cmid' => $cm->id]));
    }

    /**
     * Test delete_data_for_user deletes penalties for the target user only.
     */
    public function test_delete_data_for_user_penalties(): void {
        global $DB;
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $student1 = $gen->create_user();
        $student2 = $gen->create_user();

        $this->create_penalty($cm->id, $student1->id, $teacher->id);
        $this->create_penalty($cm->id, $student2->id, $teacher->id);

        $contextlist = new approved_contextlist($student1, 'local_unifiedgrader', [$context->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertEquals(0, $DB->count_records('local_unifiedgrader_penalty', ['userid' => $student1->id]));
        $this->assertEquals(1, $DB->count_records('local_unifiedgrader_penalty', ['userid' => $student2->id]));
    }

    /**
     * Test delete_data_for_user deletes penalties authored by the user.
     */
    public function test_delete_data_for_user_penalties_authored(): void {
        global $DB;
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $context = \context_module::instance($cm->id);

        $teacher1 = $gen->create_user();
        $teacher2 = $gen->create_user();
        $student = $gen->create_user();

        $this->create_penalty($cm->id, $student->id, $teacher1->id);
        $this->create_penalty($cm->id, $student->id, $teacher2->id, 'other', 'Late', 5);

        $contextlist = new approved_contextlist($teacher1, 'local_unifiedgrader', [$context->id]);
        provider::delete_data_for_user($contextlist);

        // Teacher1's authored penalty should be gone.
        $this->assertEquals(0, $DB->count_records('local_unifiedgrader_penalty', ['authorid' => $teacher1->id]));
        // Teacher2's penalty should remain.
        $this->assertEquals(1, $DB->count_records('local_unifiedgrader_penalty', ['authorid' => $teacher2->id]));
    }

    /**
     * Test delete_data_for_users deletes penalties for multiple users.
     */
    public function test_delete_data_for_users_penalties(): void {
        global $DB;
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $student1 = $gen->create_user();
        $student2 = $gen->create_user();
        $student3 = $gen->create_user();

        $this->create_penalty($cm->id, $student1->id, $teacher->id);
        $this->create_penalty($cm->id, $student2->id, $teacher->id);
        $this->create_penalty($cm->id, $student3->id, $teacher->id);

        $userlist = new approved_userlist(
            $context,
            'local_unifiedgrader',
            [$student1->id, $student2->id],
        );
        provider::delete_data_for_users($userlist);

        $this->assertEquals(0, $DB->count_records('local_unifiedgrader_penalty', ['userid' => $student1->id]));
        $this->assertEquals(0, $DB->count_records('local_unifiedgrader_penalty', ['userid' => $student2->id]));
        $this->assertEquals(1, $DB->count_records('local_unifiedgrader_penalty', ['userid' => $student3->id]));
    }

    // -------------------------------------------------------------------------
    // Per-attempt quiz feedback (qfb) privacy tests.
    // -------------------------------------------------------------------------

    /**
     * Helper: create a quiz feedback record directly in the DB.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @param int $grader Teacher user ID.
     * @param int $attemptnumber Attempt number.
     * @param string $feedback Feedback text.
     * @return \stdClass The created record.
     */
    private function create_qfb(
        int $cmid,
        int $userid,
        int $grader,
        int $attemptnumber = 1,
        string $feedback = '<p>Test feedback</p>',
    ): \stdClass {
        global $DB;
        $record = (object) [
            'cmid' => $cmid,
            'userid' => $userid,
            'attemptnumber' => $attemptnumber,
            'feedback' => $feedback,
            'feedbackformat' => FORMAT_HTML,
            'grader' => $grader,
            'timemodified' => time(),
        ];
        $record->id = $DB->insert_record('local_unifiedgrader_qfb', $record);
        return $record;
    }

    /**
     * Test get_contexts_for_userid returns contexts for qfb subjects.
     */
    public function test_get_contexts_for_userid_qfb_subject(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz = $gen->create_module('quiz', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $student = $gen->create_user();

        $this->create_qfb($cm->id, $student->id, $teacher->id);

        $contextlist = provider::get_contexts_for_userid($student->id);
        $contextids = array_map('intval', $contextlist->get_contextids());
        $this->assertContains($context->id, $contextids);
    }

    /**
     * Test get_contexts_for_userid returns contexts for qfb graders.
     */
    public function test_get_contexts_for_userid_qfb_grader(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz = $gen->create_module('quiz', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $student = $gen->create_user();

        $this->create_qfb($cm->id, $student->id, $teacher->id);

        $contextlist = provider::get_contexts_for_userid($teacher->id);
        $contextids = array_map('intval', $contextlist->get_contextids());
        $this->assertContains($context->id, $contextids);
    }

    /**
     * Test get_users_in_context returns qfb subjects and graders.
     */
    public function test_get_users_in_context_qfb(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz = $gen->create_module('quiz', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $student = $gen->create_user();

        $this->create_qfb($cm->id, $student->id, $teacher->id);

        $userlist = new userlist($context, 'local_unifiedgrader');
        provider::get_users_in_context($userlist);

        $userids = $userlist->get_userids();
        $this->assertContains((int) $student->id, $userids);
        $this->assertContains((int) $teacher->id, $userids);
    }

    /**
     * Test export_user_data exports quiz feedback.
     */
    public function test_export_user_data_qfb(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz = $gen->create_module('quiz', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $student = $gen->create_user();

        $this->create_qfb($cm->id, $student->id, $teacher->id, 1, '<p>Attempt 1</p>');
        $this->create_qfb($cm->id, $student->id, $teacher->id, 2, '<p>Attempt 2</p>');

        $contextlist = new approved_contextlist($student, 'local_unifiedgrader', [$context->id]);
        provider::export_user_data($contextlist);

        $data = writer::with_context($context)->get_data(['Quiz feedback']);
        $this->assertNotEmpty($data);
        $this->assertNotEmpty($data->quiz_feedback);
        $this->assertCount(2, $data->quiz_feedback);
    }

    /**
     * Test delete_data_for_all_users_in_context deletes quiz feedback.
     */
    public function test_delete_data_for_all_users_in_context_qfb(): void {
        global $DB;
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz = $gen->create_module('quiz', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $student1 = $gen->create_user();
        $student2 = $gen->create_user();

        $this->create_qfb($cm->id, $student1->id, $teacher->id);
        $this->create_qfb($cm->id, $student2->id, $teacher->id);

        $this->assertEquals(2, $DB->count_records('local_unifiedgrader_qfb', ['cmid' => $cm->id]));

        provider::delete_data_for_all_users_in_context($context);

        $this->assertEquals(0, $DB->count_records('local_unifiedgrader_qfb', ['cmid' => $cm->id]));
    }

    /**
     * Test delete_data_for_user deletes quiz feedback for the target user only.
     */
    public function test_delete_data_for_user_qfb(): void {
        global $DB;
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz = $gen->create_module('quiz', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $student1 = $gen->create_user();
        $student2 = $gen->create_user();

        $this->create_qfb($cm->id, $student1->id, $teacher->id);
        $this->create_qfb($cm->id, $student2->id, $teacher->id);

        $contextlist = new approved_contextlist($student1, 'local_unifiedgrader', [$context->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertEquals(0, $DB->count_records('local_unifiedgrader_qfb', ['userid' => $student1->id]));
        $this->assertEquals(1, $DB->count_records('local_unifiedgrader_qfb', ['userid' => $student2->id]));
    }

    /**
     * Test delete_data_for_users deletes quiz feedback for multiple users.
     */
    public function test_delete_data_for_users_qfb(): void {
        global $DB;
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz = $gen->create_module('quiz', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $student1 = $gen->create_user();
        $student2 = $gen->create_user();
        $student3 = $gen->create_user();

        $this->create_qfb($cm->id, $student1->id, $teacher->id);
        $this->create_qfb($cm->id, $student2->id, $teacher->id);
        $this->create_qfb($cm->id, $student3->id, $teacher->id);

        $userlist = new approved_userlist(
            $context,
            'local_unifiedgrader',
            [$student1->id, $student2->id],
        );
        provider::delete_data_for_users($userlist);

        $this->assertEquals(0, $DB->count_records('local_unifiedgrader_qfb', ['userid' => $student1->id]));
        $this->assertEquals(0, $DB->count_records('local_unifiedgrader_qfb', ['userid' => $student2->id]));
        $this->assertEquals(1, $DB->count_records('local_unifiedgrader_qfb', ['userid' => $student3->id]));
    }
}
