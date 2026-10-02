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

use local_unifiedgrader\adapter\adapter_factory;
use local_unifiedgrader\external\delete_note;
use local_unifiedgrader\external\delete_penalty;
use local_unifiedgrader\external\get_grade_data;
use local_unifiedgrader\external\get_participants;
use local_unifiedgrader\external\get_shared_library;
use local_unifiedgrader\external\get_submission_data;
use local_unifiedgrader\external\retry_file_conversion;
use local_unifiedgrader\external\save_annotated_pdf;
use local_unifiedgrader\external\save_library_comment;
use local_unifiedgrader\external\save_note;
use local_unifiedgrader\external\save_penalty;

/**
 * Tests for the boundaries a capability check alone does not draw.
 *
 * Holding the grading capability in one activity must not reach into another
 * activity, into another group in separate groups mode, or to people who are
 * not in the course; and what is shown to students must be cleaned first.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\access
 * @covers \local_unifiedgrader\hook_callbacks::definition_for_students
 * @covers \local_unifiedgrader\external\delete_note
 * @covers \local_unifiedgrader\external\save_note
 * @covers \local_unifiedgrader\external\delete_penalty
 * @covers \local_unifiedgrader\external\save_penalty
 * @covers \local_unifiedgrader\external\retry_file_conversion
 * @covers \local_unifiedgrader\external\save_annotated_pdf
 */
final class security_test extends \advanced_testcase {
    /**
     * Create an assignment with students, as its teacher.
     *
     * @param array $options Options for create_grading_scenario().
     * @return \stdClass The grading scenario.
     */
    private function create_scenario(array $options = []): \stdClass {
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $gen->create_grading_scenario('assign', $options);
        $this->setUser($s->teacher);
        return $s;
    }

    /**
     * An assignment in separate groups mode, with a marker confined to the red group.
     *
     * The marker is a non-editing teacher who has been given the grading
     * capability but cannot access all groups. The first student is in the
     * red group with them, the second in the blue group.
     *
     * @return \stdClass The scenario, with marker, red and blue added.
     */
    private function create_separate_groups_scenario(): \stdClass {
        global $DB;

        $s = $this->create_scenario(['studentcount' => 2, 'modparams' => ['groupmode' => SEPARATEGROUPS]]);
        $gen = $this->getDataGenerator();

        $s->red = $gen->create_group(['courseid' => $s->course->id]);
        $s->blue = $gen->create_group(['courseid' => $s->course->id]);
        groups_add_member($s->red->id, $s->students[0]->id);
        groups_add_member($s->blue->id, $s->students[1]->id);

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('local/unifiedgrader:grade', CAP_ALLOW, $roleid, $s->context->id, true);
        $s->marker = $gen->create_user();
        $gen->enrol_user($s->marker->id, $s->course->id, 'teacher');
        groups_add_member($s->red->id, $s->marker->id);

        $this->setUser($s->marker);
        $this->assertFalse(has_capability('moodle/site:accessallgroups', $s->context));
        return $s;
    }

    /**
     * A note in one activity cannot be deleted through another.
     */
    public function test_note_cannot_be_deleted_through_another_activity(): void {
        global $DB;
        $this->resetAfterTest();

        $theirs = $this->create_scenario();
        $noteid = save_note::execute($theirs->cm->id, $theirs->students[0]->id, 'Their note')['noteid'];

        // A teacher of a different course holds the same capability, but only there.
        $mine = $this->create_scenario();
        try {
            delete_note::execute($mine->cm->id, $noteid);
            $this->fail('Deleting a note of another activity should be refused.');
        } catch (\invalid_parameter_exception $e) {
            $this->assertTrue($DB->record_exists('local_unifiedgrader_notes', ['id' => $noteid]));
        }
    }

    /**
     * A note in one activity cannot be overwritten through another.
     */
    public function test_note_cannot_be_overwritten_through_another_activity(): void {
        global $DB;
        $this->resetAfterTest();

        $theirs = $this->create_scenario();
        $noteid = save_note::execute($theirs->cm->id, $theirs->students[0]->id, 'Their note')['noteid'];

        $mine = $this->create_scenario();
        try {
            save_note::execute($mine->cm->id, $mine->students[0]->id, 'Overwritten', $noteid);
            $this->fail('Overwriting a note of another activity should be refused.');
        } catch (\invalid_parameter_exception $e) {
            $this->assertSame('Their note', $DB->get_field('local_unifiedgrader_notes', 'content', ['id' => $noteid]));
        }
    }

    /**
     * A penalty in one activity cannot be deleted or changed through another.
     */
    public function test_penalty_cannot_be_reached_through_another_activity(): void {
        global $DB;
        $this->resetAfterTest();

        $theirs = $this->create_scenario();
        $penaltyid = save_penalty::execute($theirs->cm->id, $theirs->students[0]->id, 'other', 'Theirs', 10)['penaltyid'];

        $mine = $this->create_scenario();
        try {
            delete_penalty::execute($mine->cm->id, $penaltyid);
            $this->fail('Deleting a penalty of another activity should be refused.');
        } catch (\invalid_parameter_exception $e) {
            $this->assertTrue($DB->record_exists('local_unifiedgrader_penalty', ['id' => $penaltyid]));
        }
        try {
            save_penalty::execute($mine->cm->id, $mine->students[0]->id, 'other', 'Mine', 90, $penaltyid);
            $this->fail('Changing a penalty of another activity should be refused.');
        } catch (\invalid_parameter_exception $e) {
            $this->assertEquals(10, $DB->get_field('local_unifiedgrader_penalty', 'percentage', ['id' => $penaltyid]));
        }
    }

    /**
     * The automatic late penalty cannot be rewritten by saving over its ID.
     */
    public function test_late_penalty_cannot_be_rewritten(): void {
        global $DB;
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $userid = (int) $s->students[0]->id;
        penalty_manager::sync_late_penalty((int) $s->cm->id, $userid, 20, 2);
        $late = $DB->get_record('local_unifiedgrader_penalty', ['cmid' => $s->cm->id, 'userid' => $userid], '*', MUST_EXIST);
        $this->assertSame('late', $late->category);

        try {
            save_penalty::execute($s->cm->id, $userid, 'other', 'Waived', 1, (int) $late->id);
            $this->fail('Saving over the late penalty should be refused.');
        } catch (\moodle_exception $e) {
            $after = $DB->get_record('local_unifiedgrader_penalty', ['id' => $late->id]);
            $this->assertSame('late', $after->category);
            $this->assertEquals(20, $after->percentage);
        }
    }

    /**
     * A student's data cannot be read for someone who is not in the course.
     */
    public function test_user_outside_the_course_is_refused(): void {
        $this->resetAfterTest();
        $s = $this->create_scenario();
        $outsider = $this->getDataGenerator()->create_user();

        $this->assertTrue(access::can_access_student($s->context, (int) $s->students[0]->id));
        $this->assertFalse(access::can_access_student($s->context, (int) $outsider->id));
        $this->assertFalse(access::can_access_student($s->context, 0));

        $this->expectException(\moodle_exception::class);
        get_submission_data::execute($s->cm->id, $outsider->id);
    }

    /**
     * In separate groups mode a confined marker is listed only their own group.
     */
    public function test_separate_groups_confine_the_participant_list(): void {
        $this->resetAfterTest();
        $s = $this->create_separate_groups_scenario();
        [$mine, $other] = [(int) $s->students[0]->id, (int) $s->students[1]->id];

        // Asking for everyone gives the marker's own group.
        $this->assertSame([$mine], array_column(get_participants::execute($s->cm->id), 'id'));
        // Asking for the other group gives nobody.
        $this->assertSame([], get_participants::execute($s->cm->id, 'all', (string) $s->blue->id));
        // Asking for both gives only theirs.
        $both = $s->red->id . ',' . $s->blue->id;
        $this->assertSame([$mine], array_column(get_participants::execute($s->cm->id, 'all', $both), 'id'));

        // The adapter enforces it too, whoever calls it.
        $adapter = adapter_factory::create((int) $s->cm->id);
        $this->assertSame([$mine], array_column($adapter->get_participants([]), 'id'));
        $this->assertSame([], $adapter->get_participants(['groups' => [(int) $s->blue->id]]));

        // The course's editing teacher can access all groups, and still sees everyone.
        $this->setUser($s->teacher);
        $ids = array_column(get_participants::execute($s->cm->id), 'id');
        sort($ids);
        $this->assertSame([$mine, $other], $ids);
    }

    /**
     * A marker with no group of their own in separate groups mode sees nobody.
     */
    public function test_separate_groups_marker_without_a_group_sees_nobody(): void {
        $this->resetAfterTest();
        $s = $this->create_separate_groups_scenario();
        groups_remove_member($s->red->id, $s->marker->id);

        $this->assertSame([], get_participants::execute($s->cm->id));
        $this->assertNull(access::visible_group_ids($s->cm, $s->context, []));
    }

    /**
     * In separate groups mode a confined marker cannot open another group's student.
     */
    public function test_separate_groups_confine_single_student_services(): void {
        $this->resetAfterTest();
        $s = $this->create_separate_groups_scenario();
        [$mine, $other] = [(int) $s->students[0]->id, (int) $s->students[1]->id];

        $this->assertTrue(access::can_access_student($s->context, $mine));
        $this->assertFalse(access::can_access_student($s->context, $other));

        // Their own student opens.
        $this->assertIsArray(get_submission_data::execute($s->cm->id, $mine));

        foreach (
            [
                fn() => get_submission_data::execute($s->cm->id, $other),
                fn() => get_grade_data::execute($s->cm->id, $other),
                fn() => save_penalty::execute($s->cm->id, $other, 'other', 'Reach', 50),
            ] as $call
        ) {
            try {
                $call();
                $this->fail('A student of another group should be refused.');
            } catch (\moodle_exception $e) {
                $this->assertSame('nopermission', $e->errorcode);
            }
        }
    }

    /**
     * Visible groups mode, and no groups, confine nobody.
     */
    public function test_other_group_modes_are_not_confined(): void {
        $this->resetAfterTest();
        $s = $this->create_scenario(['studentcount' => 2, 'modparams' => ['groupmode' => VISIBLEGROUPS]]);
        $this->assertSame([], access::visible_group_ids($s->cm, $s->context, []));
        $this->assertSame([7], access::visible_group_ids($s->cm, $s->context, [7, 0, 7]));
        $this->assertCount(2, get_participants::execute($s->cm->id));
    }

    /**
     * The comment library is closed to someone who grades nowhere.
     */
    public function test_comment_library_is_closed_to_students(): void {
        $this->resetAfterTest();
        $s = $this->create_scenario();

        // The teacher can save and read.
        $this->assertTrue(access::can_use_library());
        $commentid = save_library_comment::execute('TEST101', 'Shared remark', [], 1)['commentid'];
        $this->assertGreaterThan(0, $commentid);

        $this->setUser($s->students[0]);
        $this->assertFalse(access::can_use_library());
        try {
            get_shared_library::execute();
            $this->fail('A student should not read the shared library.');
        } catch (\moodle_exception $e) {
            $this->assertSame('nopermission', $e->errorcode);
        }
        try {
            save_library_comment::execute('TEST101', 'Planted by a student', [], 1);
            $this->fail('A student should not write to the library.');
        } catch (\moodle_exception $e) {
            $this->assertSame('nopermission', $e->errorcode);
        }

        $this->setGuestUser();
        $this->assertFalse(access::can_use_library());
    }

    /**
     * What students are shown of a rubric is cleaned, and notes for markers are left out.
     */
    public function test_definition_for_students_is_cleaned(): void {
        $this->resetAfterTest();
        $s = $this->create_scenario();

        $definition = [
            'method' => 'guide',
            'name' => 'Guide',
            'description' => 'Overall<script>alert(1)</script>',
            'criteria' => [
                [
                    'id' => 1,
                    'shortname' => 'Argument',
                    'description' => '<p onclick="alert(2)">Makes a case</p>',
                    'descriptionmarkers' => 'Be strict: the model answer is 42.',
                    'maxscore' => 10.0,
                    'levels' => [
                        ['id' => 1, 'score' => 1.0, 'definition' => 'Weak<img src=x onerror="alert(3)">'],
                    ],
                ],
            ],
        ];

        $clean = hook_callbacks::definition_for_students($definition, $s->context);
        $json = json_encode($clean);

        $this->assertStringNotContainsString('<script', $clean['description']);
        $this->assertStringContainsString('Overall', $clean['description']);
        $this->assertStringNotContainsString('onclick', $clean['criteria'][0]['description']);
        $this->assertStringContainsString('Makes a case', $clean['criteria'][0]['description']);
        $this->assertStringNotContainsString('onerror', $clean['criteria'][0]['levels'][0]['definition']);
        $this->assertArrayNotHasKey('descriptionmarkers', $clean['criteria'][0]);
        $this->assertStringNotContainsString('model answer', $json);
        $this->assertSame('Argument', $clean['criteria'][0]['shortname']);
        $this->assertSame(10.0, $clean['criteria'][0]['maxscore']);
    }

    /**
     * Only a PDF, named as one, is stored as an annotated PDF.
     */
    public function test_annotated_pdf_must_be_a_pdf(): void {
        $this->resetAfterTest();
        $s = $this->create_scenario();
        $userid = (int) $s->students[0]->id;

        $result = save_annotated_pdf::execute($s->cm->id, $userid, 123, base64_encode("%PDF-1.7\n..."), 'annotated.pdf');
        $this->assertTrue($result['success']);

        foreach (
            [
                [base64_encode('<html><script>alert(1)</script></html>'), 'annotated.pdf'],
                [base64_encode("%PDF-1.7\n..."), 'annotated.html'],
            ] as [$data, $filename]
        ) {
            try {
                save_annotated_pdf::execute($s->cm->id, $userid, 123, $data, $filename);
                $this->fail("{$filename} should be refused.");
            } catch (\invalid_parameter_exception $e) {
                $this->assertStringContainsString('PDF', $e->getMessage());
            }
        }
    }

    /**
     * A conversion cannot be reset for a file of another activity.
     */
    public function test_file_conversion_retry_is_confined_to_the_activity(): void {
        $this->resetAfterTest();

        $theirs = $this->create_scenario();
        $file = get_file_storage()->create_file_from_string([
            'contextid' => $theirs->context->id,
            'component' => 'local_unifiedgrader',
            'filearea' => 'onlinetextpdf',
            'itemid' => 1,
            'filepath' => '/',
            'filename' => 'text.pdf',
        ], '%PDF-1.7');

        $mine = $this->create_scenario();
        try {
            retry_file_conversion::execute($mine->cm->id, $file->get_id());
            $this->fail('A file of another activity should be refused.');
        } catch (\moodle_exception $e) {
            $this->assertNotFalse(get_file_storage()->get_file_by_id($file->get_id()));
        }

        // Its own teacher can.
        $this->setUser($theirs->teacher);
        $this->assertTrue(retry_file_conversion::execute($theirs->cm->id, $file->get_id())['success']);
    }
}
