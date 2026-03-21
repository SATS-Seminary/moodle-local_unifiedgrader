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
 * Tests for submission comment web service external functions.
 *
 * Covers get_submission_comments, add_submission_comment, and delete_submission_comment.
 * Tests comments across assign, forum, and quiz activity types, plus notifications.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\external\get_submission_comments
 * @covers \local_unifiedgrader\external\add_submission_comment
 * @covers \local_unifiedgrader\external\delete_submission_comment
 */
final class submission_comment_webservices_test extends \advanced_testcase {
    /**
     * Helper: create a grading scenario and set teacher as current user.
     *
     * @param string $type Activity type (assign, forum, quiz).
     * @param array $options Options passed to create_grading_scenario.
     * @return \stdClass Scenario object with course, activity, cm, context, teacher, students.
     */
    private function create_scenario(string $type = 'assign', array $options = []): \stdClass {
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario($type, $options);
        $this->setUser($scenario->teacher);
        return $scenario;
    }

    // Get_submission_comments tests.

    /**
     * Test get_submission_comments returns comments after adding one.
     */
    public function test_get_submission_comments_happy_path(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        // Add a comment via the web service.
        add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Test comment from teacher',
        );

        $result = get_submission_comments::execute($scenario->cm->id, $scenario->students[0]->id);

        $this->assertArrayHasKey('comments', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertArrayHasKey('canpost', $result);
        $this->assertEquals(1, $result['count']);
        $this->assertTrue($result['canpost']);

        $found = false;
        foreach ($result['comments'] as $comment) {
            if (strpos($comment['content'], 'Test comment from teacher') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'The added comment should appear in the comment list');
    }

    /**
     * Test get_submission_comments works for forum activities.
     */
    public function test_get_submission_comments_forum(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario('forum');

        add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Forum comment',
        );

        $result = get_submission_comments::execute($scenario->cm->id, $scenario->students[0]->id);

        $this->assertEquals(1, $result['count']);
        $this->assertStringContainsString('Forum comment', $result['comments'][0]['content']);
    }

    /**
     * Test get_submission_comments works for quiz activities.
     */
    public function test_get_submission_comments_quiz(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario('quiz');

        add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Quiz comment',
        );

        $result = get_submission_comments::execute($scenario->cm->id, $scenario->students[0]->id);

        $this->assertEquals(1, $result['count']);
        $this->assertStringContainsString('Quiz comment', $result['comments'][0]['content']);
    }

    /**
     * Test get_submission_comments throws when user lacks both grade and viewfeedback capabilities.
     */
    public function test_get_submission_comments_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $gen = $this->getDataGenerator();
        $norole = $gen->create_user(['username' => 'noroleuser']);
        $gen->enrol_user($norole->id, $scenario->course->id, 'guest');

        $roles = get_archetype_roles('guest');
        $guestroleobj = reset($roles);
        if ($guestroleobj) {
            assign_capability(
                'local/unifiedgrader:viewfeedback',
                CAP_PROHIBIT,
                $guestroleobj->id,
                $scenario->context->id,
            );
        }

        $this->setUser($norole);

        $this->expectException(\required_capability_exception::class);
        get_submission_comments::execute($scenario->cm->id, $scenario->students[0]->id);
    }

    /**
     * Test get_submission_comments return value passes clean_returnvalue validation.
     */
    public function test_get_submission_comments_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $result = get_submission_comments::execute($scenario->cm->id, $scenario->students[0]->id);

        $cleaned = external_api::clean_returnvalue(
            get_submission_comments::execute_returns(),
            $result,
        );

        $this->assertIsArray($cleaned['comments']);
        $this->assertIsInt($cleaned['count']);
        $this->assertIsBool($cleaned['canpost']);
    }

    /**
     * Test get_submission_comments returns empty when no comments exist.
     */
    public function test_get_submission_comments_empty(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $result = get_submission_comments::execute($scenario->cm->id, $scenario->students[0]->id);

        $this->assertEmpty($result['comments']);
        $this->assertEquals(0, $result['count']);
    }

    /**
     * Test student can view their own comments via viewfeedback capability.
     */
    public function test_student_can_view_own_comments(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        // Teacher adds a comment.
        add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Teacher feedback',
        );

        // Switch to student.
        $this->setUser($scenario->students[0]);

        $result = get_submission_comments::execute($scenario->cm->id, $scenario->students[0]->id);

        $this->assertEquals(1, $result['count']);
        $this->assertTrue($result['canpost']);
    }

    /**
     * Test student cannot view another student's comments.
     */
    public function test_student_cannot_view_other_comments(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        // Switch to student 0, try to view student 1's comments.
        $this->setUser($scenario->students[0]);

        $this->expectException(\moodle_exception::class);
        get_submission_comments::execute($scenario->cm->id, $scenario->students[1]->id);
    }

    // Add_submission_comment tests.

    /**
     * Test add_submission_comment creates a comment successfully.
     */
    public function test_add_submission_comment_happy_path(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $result = add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'A teacher comment on the submission',
        );

        $this->assertArrayHasKey('id', $result);
        $this->assertGreaterThan(0, $result['id']);
        $this->assertStringContainsString('A teacher comment on the submission', $result['content']);
        $this->assertEquals(1, $result['count']);
    }

    /**
     * Test add_submission_comment throws when user lacks both grade and viewfeedback capabilities.
     */
    public function test_add_submission_comment_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $gen = $this->getDataGenerator();
        $norole = $gen->create_user(['username' => 'noroleuser']);
        $gen->enrol_user($norole->id, $scenario->course->id, 'guest');

        $roles = get_archetype_roles('guest');
        $guestroleobj = reset($roles);
        if ($guestroleobj) {
            assign_capability(
                'local/unifiedgrader:viewfeedback',
                CAP_PROHIBIT,
                $guestroleobj->id,
                $scenario->context->id,
            );
        }

        $this->setUser($norole);

        $this->expectException(\required_capability_exception::class);
        add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Should not be saved',
        );
    }

    /**
     * Test add_submission_comment return value passes clean_returnvalue validation.
     */
    public function test_add_submission_comment_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $result = add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Validated comment',
        );

        $cleaned = external_api::clean_returnvalue(
            add_submission_comment::execute_returns(),
            $result,
        );

        $this->assertIsInt($cleaned['id']);
        $this->assertIsString($cleaned['content']);
        $this->assertIsString($cleaned['fullname']);
        $this->assertIsString($cleaned['time']);
        $this->assertIsInt($cleaned['count']);
    }

    /**
     * Test student can post comment on their own submission.
     */
    public function test_student_can_post_own_comment(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $this->setUser($scenario->students[0]);

        $result = add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Student question about feedback',
        );

        $this->assertGreaterThan(0, $result['id']);
        $this->assertEquals(1, $result['count']);
    }

    /**
     * Test student cannot post comment on another student's submission.
     */
    public function test_student_cannot_post_on_other(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $this->setUser($scenario->students[0]);

        $this->expectException(\moodle_exception::class);
        add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[1]->id,
            'Should not be allowed',
        );
    }

    // Delete_submission_comment tests.

    /**
     * Test delete_submission_comment removes a comment successfully.
     */
    public function test_delete_submission_comment_happy_path(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $addresult = add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Comment to delete',
        );

        $result = delete_submission_comment::execute($scenario->cm->id, $addresult['id']);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['count']);
    }

    /**
     * Test delete_submission_comment throws when user lacks both grade and viewfeedback capabilities.
     */
    public function test_delete_submission_comment_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $addresult = add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Comment to try to delete',
        );

        $gen = $this->getDataGenerator();
        $norole = $gen->create_user(['username' => 'noroleuser']);
        $gen->enrol_user($norole->id, $scenario->course->id, 'guest');

        $roles = get_archetype_roles('guest');
        $guestroleobj = reset($roles);
        if ($guestroleobj) {
            assign_capability(
                'local/unifiedgrader:viewfeedback',
                CAP_PROHIBIT,
                $guestroleobj->id,
                $scenario->context->id,
            );
        }

        $this->setUser($norole);

        $this->expectException(\required_capability_exception::class);
        delete_submission_comment::execute($scenario->cm->id, $addresult['id']);
    }

    /**
     * Test delete_submission_comment return value passes clean_returnvalue validation.
     */
    public function test_delete_submission_comment_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $addresult = add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'For return validation',
        );

        $result = delete_submission_comment::execute($scenario->cm->id, $addresult['id']);

        $cleaned = external_api::clean_returnvalue(
            delete_submission_comment::execute_returns(),
            $result,
        );

        $this->assertIsBool($cleaned['success']);
        $this->assertTrue($cleaned['success']);
        $this->assertIsInt($cleaned['count']);
    }

    /**
     * Test delete_submission_comment throws for a nonexistent comment ID.
     */
    public function test_delete_submission_comment_nonexistent(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $this->expectException(\moodle_exception::class);
        delete_submission_comment::execute($scenario->cm->id, 99999);
    }

    /**
     * Test teacher can delete any comment, student can only delete own.
     */
    public function test_delete_permission_rules(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        // Student posts a comment.
        $this->setUser($scenario->students[0]);
        $studentcomment = add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Student comment',
        );

        // Teacher posts a comment.
        $this->setUser($scenario->teacher);
        $teachercomment = add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Teacher comment',
        );

        // Student cannot delete teacher's comment.
        $this->setUser($scenario->students[0]);
        $this->expectException(\moodle_exception::class);
        delete_submission_comment::execute($scenario->cm->id, $teachercomment['id']);
    }

    /**
     * Test teacher can delete a student's comment.
     */
    public function test_teacher_can_delete_student_comment(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        // Student posts a comment.
        $this->setUser($scenario->students[0]);
        $studentcomment = add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Student comment to delete',
        );

        // Teacher deletes it.
        $this->setUser($scenario->teacher);
        $result = delete_submission_comment::execute($scenario->cm->id, $studentcomment['id']);
        $this->assertTrue($result['success']);
    }

    // Notification tests.

    /**
     * Test that a notification is sent to the student when a teacher posts a comment.
     */
    public function test_notification_sent_to_student_on_teacher_comment(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $sink = $this->redirectMessages();

        add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Please review my feedback',
        );

        $messages = $sink->get_messages();
        $sink->close();

        // Find the notification sent to the student.
        $found = false;
        foreach ($messages as $msg) {
            if (
                (int) $msg->useridto === (int) $scenario->students[0]->id
                && $msg->component === 'local_unifiedgrader'
                && $msg->eventtype === 'submission_comment'
            ) {
                $found = true;
                $this->assertStringContainsString('Please review my feedback', $msg->fullmessagehtml);
                $this->assertStringContainsString(fullname($scenario->teacher), $msg->fullmessagehtml);
                break;
            }
        }
        $this->assertTrue($found, 'Student should receive a notification when teacher comments');
    }

    /**
     * Test that a notification is sent to the teacher when a student posts a comment.
     */
    public function test_notification_sent_to_teacher_on_student_comment(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $sink = $this->redirectMessages();

        $this->setUser($scenario->students[0]);
        add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'I have a question about my grade',
        );

        $messages = $sink->get_messages();
        $sink->close();

        // Find the notification sent to the teacher.
        $found = false;
        foreach ($messages as $msg) {
            if (
                (int) $msg->useridto === (int) $scenario->teacher->id
                && $msg->component === 'local_unifiedgrader'
                && $msg->eventtype === 'submission_comment'
            ) {
                $found = true;
                $this->assertStringContainsString('I have a question about my grade', $msg->fullmessagehtml);
                break;
            }
        }
        $this->assertTrue($found, 'Teacher should receive a notification when student comments');
    }
}
