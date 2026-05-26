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

namespace local_unifiedgrader\notification;

/**
 * Tests for the submission_comment_notification class — specifically the
 * group-aware recipient routing when a student posts a comment.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\notification\submission_comment_notification
 */
final class submission_comment_notification_test extends \advanced_testcase {
    /**
     * Helper: build a course + assign + N teachers + 1 student, optionally
     * in groups, and return everything as a struct.
     *
     * @param int $groupmode NOGROUPS, SEPARATEGROUPS, or VISIBLEGROUPS.
     * @return object
     */
    private function build_scenario(int $groupmode): object {
        global $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['groupmode' => $groupmode, 'groupmodeforce' => 0]);
        $assign = $gen->create_module('assign', [
            'course' => $course->id,
            'groupmode' => $groupmode,
        ]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);

        $teachera = $gen->create_user();
        $teacherb = $gen->create_user();
        $student = $gen->create_user();
        $gen->enrol_user($teachera->id, $course->id, 'editingteacher');
        $gen->enrol_user($teacherb->id, $course->id, 'editingteacher');
        $gen->enrol_user($student->id, $course->id, 'student');

        $groupa = $gen->create_group(['courseid' => $course->id, 'name' => 'A']);
        $groupb = $gen->create_group(['courseid' => $course->id, 'name' => 'B']);

        // Teacher A owns group A, teacher B owns group B. Student is in group A.
        $gen->create_group_member(['groupid' => $groupa->id, 'userid' => $teachera->id]);
        $gen->create_group_member(['groupid' => $groupb->id, 'userid' => $teacherb->id]);
        $gen->create_group_member(['groupid' => $groupa->id, 'userid' => $student->id]);

        return (object) [
            'course' => $course,
            'cm' => $cm,
            'teachera' => $teachera,
            'teacherb' => $teacherb,
            'student' => $student,
            'groupa' => $groupa,
            'groupb' => $groupb,
        ];
    }

    /**
     * Extract recipient userids from a message sink, filtered to our component.
     *
     * @param \phpunit_message_sink $sink
     * @return int[]
     */
    private function recipient_ids(\phpunit_message_sink $sink): array {
        $ids = [];
        foreach ($sink->get_messages() as $m) {
            if (($m->component ?? '') === 'local_unifiedgrader') {
                $ids[] = (int) $m->useridto;
            }
        }
        sort($ids);
        return $ids;
    }

    /**
     * SEPARATEGROUPS: a student in group A should only notify teacher A,
     * not teacher B (who owns group B). Bug from the field — the course
     * teacher / group B teacher was getting pinged for every comment.
     */
    public function test_separategroups_scopes_recipients_to_group_teachers(): void {
        $this->resetAfterTest();
        $s = $this->build_scenario(SEPARATEGROUPS);

        $sink = $this->redirectMessages();
        submission_comment_notification::send(
            (int) $s->cm->id,
            (int) $s->student->id,
            (int) $s->student->id,
            'Hello, this is a question about my work.',
        );

        $this->assertSame([(int) $s->teachera->id], $this->recipient_ids($sink));
    }

    /**
     * VISIBLEGROUPS behaves the same — the group teacher is the right
     * recipient even though everyone can see all groups.
     */
    public function test_visiblegroups_scopes_recipients_to_group_teachers(): void {
        $this->resetAfterTest();
        $s = $this->build_scenario(VISIBLEGROUPS);

        $sink = $this->redirectMessages();
        submission_comment_notification::send(
            (int) $s->cm->id,
            (int) $s->student->id,
            (int) $s->student->id,
            'Question.',
        );

        $this->assertSame([(int) $s->teachera->id], $this->recipient_ids($sink));
    }

    /**
     * NOGROUPS mode: keep the legacy "notify everyone with grading
     * capability" behaviour. Group filtering only kicks in when the
     * activity is actually group-aware.
     */
    public function test_nogroups_notifies_all_teachers(): void {
        $this->resetAfterTest();
        $s = $this->build_scenario(NOGROUPS);

        $sink = $this->redirectMessages();
        submission_comment_notification::send(
            (int) $s->cm->id,
            (int) $s->student->id,
            (int) $s->student->id,
            'Question.',
        );

        $this->assertSame(
            [(int) $s->teachera->id, (int) $s->teacherb->id],
            $this->recipient_ids($sink),
        );
    }

    /**
     * Group mode is on but the student belongs to no groups (e.g. a
     * straggler the teacher hasn't assigned yet). Fall back to all
     * graders so the comment doesn't fall into a black hole.
     */
    public function test_groupmode_with_ungrouped_student_falls_back_to_all(): void {
        $this->resetAfterTest();
        $s = $this->build_scenario(SEPARATEGROUPS);

        // Remove the student from group A.
        groups_remove_member($s->groupa->id, $s->student->id);

        $sink = $this->redirectMessages();
        submission_comment_notification::send(
            (int) $s->cm->id,
            (int) $s->student->id,
            (int) $s->student->id,
            'Question from a groupless student.',
        );

        $this->assertSame(
            [(int) $s->teachera->id, (int) $s->teacherb->id],
            $this->recipient_ids($sink),
        );
    }

    /**
     * Student in a group with no teacher member — also fall back to all
     * graders so the comment doesn't get lost when an admin forgot to
     * add a teacher to the group.
     */
    public function test_groupmode_with_teacherless_group_falls_back_to_all(): void {
        $this->resetAfterTest();
        $s = $this->build_scenario(SEPARATEGROUPS);

        // Remove teacher A from group A — now group A has no teachers.
        groups_remove_member($s->groupa->id, $s->teachera->id);

        $sink = $this->redirectMessages();
        submission_comment_notification::send(
            (int) $s->cm->id,
            (int) $s->student->id,
            (int) $s->student->id,
            'Question from a student whose group has no teacher.',
        );

        // Both teachers get the message via the fallback path.
        $this->assertSame(
            [(int) $s->teachera->id, (int) $s->teacherb->id],
            $this->recipient_ids($sink),
        );
    }

    /**
     * Teacher posted a comment to a student — student gets notified
     * exactly once (no group filtering on the student side; they're
     * just the addressed recipient).
     */
    public function test_teacher_comment_notifies_student_only(): void {
        $this->resetAfterTest();
        $s = $this->build_scenario(SEPARATEGROUPS);

        $sink = $this->redirectMessages();
        submission_comment_notification::send(
            (int) $s->cm->id,
            (int) $s->student->id,
            (int) $s->teachera->id,
            'Thanks for asking.',
        );

        $this->assertSame([(int) $s->student->id], $this->recipient_ids($sink));
    }

    /**
     * The student who posted the comment shouldn't get notified about
     * their own message even when the WS receives a stray call where
     * authorid matches studentuserid. (The grader-branch already excludes
     * the author; this guards the student-branch.)
     */
    public function test_author_is_never_their_own_recipient(): void {
        $this->resetAfterTest();
        $s = $this->build_scenario(SEPARATEGROUPS);

        $sink = $this->redirectMessages();
        submission_comment_notification::send(
            (int) $s->cm->id,
            (int) $s->student->id,
            (int) $s->student->id,
            'Question.',
        );

        // Teacher A gets the message; student does not.
        $recipients = $this->recipient_ids($sink);
        $this->assertNotContains((int) $s->student->id, $recipients);
    }
}
