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
 * Assignment comments kept in step with core's submission comments (issue #24).
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_unifiedgrader\assign_comment_sync
 * @covers     \local_unifiedgrader\submission_comment_manager
 * @covers     \local_unifiedgrader\observer::handle_assign_comment_created
 * @covers     \local_unifiedgrader\observer::handle_assign_comment_deleted
 */
final class assign_comment_sync_test extends \advanced_testcase {
    /**
     * An assignment with a teacher and students.
     *
     * @param array $modparams Extra assignment settings.
     * @return \stdClass Scenario from the plugin generator, with an assign object added.
     */
    private function scenario(array $modparams = []): \stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $s = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader')
            ->create_grading_scenario('assign', ['modparams' => $modparams]);
        [$course, $cm] = get_course_and_cm_from_cmid($s->cm->id, 'assign');
        $s->assign = new \assign(\context_module::instance($s->cm->id), $cm, $course);
        return $s;
    }

    /**
     * Post a comment the way core's submissions list and grader do.
     *
     * Does what \core_comment\manager::add() does once its permission checks
     * pass: write the row, then trigger the component's comment_created event.
     * Calling add() itself is unreliable across tests, because core's
     * permission callback keeps the last assignment it saw in a static
     * variable that outlives the database reset.
     *
     * @param \stdClass $s Scenario.
     * @param \stdClass $submission The assign_submission row.
     * @param \stdClass $author Who writes it.
     * @param string $text The comment.
     * @return \stdClass The new core comment.
     */
    private function core_comment(\stdClass $s, \stdClass $submission, \stdClass $author, string $text): \stdClass {
        global $DB;
        $this->setUser($author);
        $comment = (object) [
            'contextid' => $s->assign->get_context()->id,
            'component' => assign_comment_sync::COMPONENT,
            'commentarea' => assign_comment_sync::AREA,
            'itemid' => $submission->id,
            'content' => $text,
            'format' => FORMAT_MOODLE,
            'userid' => $author->id,
            'timecreated' => time(),
        ];
        $comment->id = $DB->insert_record('comments', $comment);
        \assignsubmission_comments\event\comment_created::create([
            'context' => $s->assign->get_context(),
            'objectid' => $comment->id,
            'other' => ['itemid' => $submission->id],
        ])->trigger();
        return $comment;
    }

    /**
     * Delete a comment the way core does: the row, then comment_deleted.
     *
     * @param \stdClass $s Scenario.
     * @param \stdClass $comment The core comment.
     */
    private function delete_core_comment(\stdClass $s, \stdClass $comment): void {
        global $DB;
        $DB->delete_records('comments', ['id' => $comment->id]);
        $event = \assignsubmission_comments\event\comment_deleted::create([
            'context' => $s->assign->get_context(),
            'objectid' => $comment->id,
            'other' => ['itemid' => $comment->itemid],
        ]);
        $event->add_record_snapshot('comments', $comment);
        $event->trigger();
    }

    /**
     * Core comments on a submission.
     *
     * @param \stdClass $s Scenario.
     * @param int $submissionid Submission ID.
     * @return array
     */
    private function core_rows(\stdClass $s, int $submissionid): array {
        global $DB;
        return array_values($DB->get_records('comments', [
            'contextid' => $s->assign->get_context()->id,
            'component' => assign_comment_sync::COMPONENT,
            'itemid' => $submissionid,
        ], 'id'));
    }

    /**
     * The issue: a teacher's comment in core's submissions list shows in Unified Grader, and notifies the student.
     */
    public function test_core_comment_reaches_unified_grader_and_notifies(): void {
        $this->resetAfterTest();
        $s = $this->scenario();
        $student = $s->students[0];
        $submission = $s->assign->get_user_submission($student->id, true);
        $sink = $this->redirectMessages();

        $core = $this->core_comment($s, $submission, $s->teacher, 'Look at section two again.');

        $comments = submission_comment_manager::get_comments((int) $s->cm->id, (int) $student->id);
        $this->assertCount(1, $comments);
        $this->assertSame('Look at section two again.', $comments[0]->content);
        $this->assertEquals($s->teacher->id, $comments[0]->authorid);
        $this->assertEquals($core->id, $comments[0]->corecommentid);

        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $this->assertEquals($student->id, $messages[0]->useridto);
        $sink->close();
    }

    /**
     * A student's comment in core notifies the teacher.
     */
    public function test_student_core_comment_notifies_teacher(): void {
        $this->resetAfterTest();
        $s = $this->scenario();
        $student = $s->students[0];
        $submission = $s->assign->get_user_submission($student->id, true);
        $sink = $this->redirectMessages();

        $this->core_comment($s, $submission, $student, 'Is the bibliography required?');

        $recipients = array_map(fn($m) => (int) $m->useridto, $sink->get_messages());
        $this->assertContains((int) $s->teacher->id, $recipients);
        $this->assertNotContains((int) $student->id, $recipients);
        $sink->close();
    }

    /**
     * A comment posted in Unified Grader is written to core once, and not copied back.
     */
    public function test_unified_grader_comment_reaches_core(): void {
        $this->resetAfterTest();
        $s = $this->scenario();
        $student = $s->students[0];
        $this->setUser($s->teacher);

        $cmid = (int) $s->cm->id;
        $record = submission_comment_manager::add_comment($cmid, (int) $student->id, (int) $s->teacher->id, 'Good start.');

        $submission = $s->assign->get_user_submission($student->id, false);
        $core = $this->core_rows($s, (int) $submission->id);
        $this->assertCount(1, $core);
        $this->assertSame('Good start.', $core[0]->content);
        $this->assertEquals($s->teacher->id, $core[0]->userid);
        $this->assertEquals($core[0]->id, $record->corecommentid);

        // Reading the thread does not import it a second time.
        $this->assertCount(1, submission_comment_manager::get_comments((int) $s->cm->id, (int) $student->id));
    }

    /**
     * Deleting in either place deletes in both.
     */
    public function test_delete_in_either_place(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->scenario();
        $student = $s->students[0];
        $this->setUser($s->teacher);
        $cmid = (int) $s->cm->id;

        // Deleted in Unified Grader.
        $record = submission_comment_manager::add_comment($cmid, (int) $student->id, (int) $s->teacher->id, 'First.');
        submission_comment_manager::delete_comment((int) $record->id);
        $this->assertFalse($DB->record_exists('comments', ['id' => $record->corecommentid]));

        // Deleted in core.
        $submission = $s->assign->get_user_submission($student->id, true);
        $core = $this->core_comment($s, $submission, $s->teacher, 'Second.');
        $this->assertTrue($DB->record_exists('local_unifiedgrader_scomm', ['corecommentid' => $core->id]));
        $this->delete_core_comment($s, $core);
        $this->assertSame(0, submission_comment_manager::count_comments($cmid, (int) $student->id));
    }

    /**
     * A core comment that arrived without an event (a restore, the plugin off) is picked up on read, without a notification.
     */
    public function test_missed_core_comment_is_imported_on_read(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->scenario();
        $student = $s->students[0];
        $submission = $s->assign->get_user_submission($student->id, true);
        $DB->insert_record('comments', (object) [
            'contextid' => $s->assign->get_context()->id,
            'component' => assign_comment_sync::COMPONENT,
            'commentarea' => assign_comment_sync::AREA,
            'itemid' => $submission->id,
            'content' => 'Restored comment.',
            'format' => FORMAT_MOODLE,
            'userid' => $s->teacher->id,
            'timecreated' => time() - DAYSECS,
        ]);
        $sink = $this->redirectMessages();

        $comments = submission_comment_manager::get_comments((int) $s->cm->id, (int) $student->id);

        $this->assertCount(1, $comments);
        $this->assertSame('Restored comment.', $comments[0]->content);
        $this->assertCount(0, $sink->get_messages());
        $sink->close();
    }

    /**
     * The upgrade links the copies the old one-off migration made, and pushes the rest, without duplicates.
     */
    public function test_reconcile_links_old_copies(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->scenario();
        $student = $s->students[0];
        $cmid = (int) $s->cm->id;
        $submission = $s->assign->get_user_submission($student->id, true);
        $when = time() - WEEKSECS;
        $coreid = $DB->insert_record('comments', (object) [
            'contextid' => $s->assign->get_context()->id,
            'component' => assign_comment_sync::COMPONENT,
            'commentarea' => assign_comment_sync::AREA,
            'itemid' => $submission->id,
            'content' => 'Copied in March.',
            'format' => FORMAT_MOODLE,
            'userid' => $s->teacher->id,
            'timecreated' => $when,
        ]);
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        // The old migration's copy, and a comment only Unified Grader has.
        $gen->create_submission_comment([
            'cmid' => $cmid, 'userid' => $student->id, 'authorid' => $s->teacher->id,
            'content' => 'Copied in March.', 'timecreated' => $when, 'timemodified' => $when,
        ]);
        $gen->create_submission_comment([
            'cmid' => $cmid, 'userid' => $student->id, 'authorid' => $s->teacher->id,
            'content' => 'Only in Unified Grader.',
        ]);

        $result = assign_comment_sync::reconcile_cm($cmid);

        $this->assertSame(['linked' => 1, 'imported' => 0, 'pushed' => 1], $result);
        $this->assertSame(2, submission_comment_manager::count_comments($cmid, (int) $student->id));
        $this->assertCount(2, $this->core_rows($s, (int) $submission->id));
        $this->assertTrue($DB->record_exists('local_unifiedgrader_scomm', ['corecommentid' => $coreid]));

        // Running it again changes nothing.
        $this->assertSame(['linked' => 0, 'imported' => 0, 'pushed' => 0], assign_comment_sync::reconcile_cm($cmid));
    }

    /**
     * On a team assignment a core comment goes into every member's thread.
     */
    public function test_team_comment_reaches_every_member(): void {
        $this->resetAfterTest();
        $s = $this->scenario(['teamsubmission' => 1]);
        [$a, $b] = [$s->students[0], $s->students[1]];
        $group = $this->getDataGenerator()->create_group(['courseid' => $s->course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $a->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $group->id, 'userid' => $b->id]);
        $submission = $s->assign->get_group_submission($a->id, 0, true);
        $sink = $this->redirectMessages();

        $this->core_comment($s, $submission, $s->teacher, 'For the whole group.');

        $cmid = (int) $s->cm->id;
        $this->assertSame(1, submission_comment_manager::count_comments($cmid, (int) $a->id));
        $this->assertSame(1, submission_comment_manager::count_comments($cmid, (int) $b->id));
        $this->assertCount(2, $sink->get_messages());
        $sink->close();

        // Deleting one member's copy in Unified Grader removes the core comment and the other copy.
        $this->setUser($s->teacher);
        $mine = submission_comment_manager::get_comments($cmid, (int) $a->id);
        submission_comment_manager::delete_comment((int) $mine[0]->id);
        $this->assertSame(0, submission_comment_manager::count_comments($cmid, (int) $b->id));
        $this->assertCount(0, $this->core_rows($s, (int) $submission->id));
    }

    /**
     * Other activity types have no core comments and are left alone.
     */
    public function test_forum_comments_stay_in_unified_grader(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader')->create_grading_scenario('forum');
        $before = $DB->count_records('comments');

        $record = submission_comment_manager::add_comment(
            (int) $s->cm->id,
            (int) $s->students[0]->id,
            (int) $s->teacher->id,
            'On the forum.',
        );

        $this->assertNull($record->corecommentid);
        $this->assertSame($before, $DB->count_records('comments'));
    }
}
