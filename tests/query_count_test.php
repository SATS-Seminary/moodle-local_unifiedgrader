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
use local_unifiedgrader\penalty\compat;

/**
 * Guards against queries that multiply with the size of a class.
 *
 * The participant list is fetched when the grader opens and again after every
 * saved grade, so a query per student there is paid for constantly. These tests
 * build the same activity for a small and a large class and require both to
 * cost the same number of database reads, then check that the batched lookups
 * give the answers the one-at-a-time ones did.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\adapter\base_adapter
 * @covers \local_unifiedgrader\adapter\assign_adapter
 * @covers \local_unifiedgrader\adapter\forum_adapter
 * @covers \local_unifiedgrader\adapter\quiz_adapter
 * @covers \local_unifiedgrader\adapter\bbb_adapter
 */
final class query_count_test extends \advanced_testcase {
    /**
     * Create an activity with students who each have a profile picture and some work.
     *
     * @param string $modname Module name.
     * @param int $studentcount Number of students.
     * @param array $modparams Extra settings for the activity.
     * @return \stdClass The grading scenario, as the teacher.
     */
    private function create_scenario(string $modname, int $studentcount, array $modparams = []): \stdClass {
        global $DB;

        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $gen->create_grading_scenario($modname, ['studentcount' => $studentcount, 'modparams' => $modparams]);
        foreach ($s->students as $student) {
            // A picture is what makes user_picture want the user's context.
            $DB->set_field('user', 'picture', 1, ['id' => $student->id]);
            if ($modname === 'assign') {
                $gen->create_assign_submission($s->activity, (int) $student->id);
            } else if ($modname === 'forum') {
                $gen->create_forum_post($s->activity, (int) $student->id);
            }
        }
        $this->setUser($s->teacher);
        return $s;
    }

    /**
     * Count the database reads of listing an activity's participants, as a fresh request would.
     *
     * @param \stdClass $s The grading scenario.
     * @return int Number of reads.
     */
    private function count_participant_reads(\stdClass $s): int {
        global $DB;

        $adapter = adapter_factory::create((int) $s->cm->id);
        // Warm anything created on first use, such as blind marking IDs.
        $adapter->get_participants([]);

        // Contexts are cached for the life of a request; a new one starts without them.
        \core\context_helper::reset_caches();
        $adapter = adapter_factory::create((int) $s->cm->id);
        $before = $DB->perf_get_reads();
        $participants = $adapter->get_participants([]);
        $reads = $DB->perf_get_reads() - $before;

        $this->assertCount(count($s->students), $participants);
        return $reads;
    }

    /**
     * Activity types and settings whose participant lists are checked.
     *
     * @return array[]
     */
    public static function participant_list_provider(): array {
        return [
            'assignment' => ['assign', []],
            'assignment, blind marking' => ['assign', ['blindmarking' => 1]],
            'assignment, team submissions' => ['assign', ['teamsubmission' => 1]],
            'forum' => ['forum', []],
            'forum, rated' => ['forum', ['grade_forum' => 0, 'assessed' => 1, 'scale' => 100]],
            'quiz' => ['quiz', []],
            'BigBlueButton' => ['bigbluebuttonbn', []],
        ];
    }

    /**
     * Listing participants costs the same number of reads for 3 students as for 15.
     *
     * @dataProvider participant_list_provider
     * @param string $modname Module name.
     * @param array $modparams Extra settings for the activity.
     */
    public function test_participant_list_reads_do_not_grow_with_class_size(string $modname, array $modparams): void {
        $this->resetAfterTest();
        if (!\core_component::get_plugin_directory('mod', $modname)) {
            $this->markTestSkipped("mod_{$modname} is not installed.");
        }

        $small = $this->count_participant_reads($this->create_scenario($modname, 3, $modparams));
        $large = $this->count_participant_reads($this->create_scenario($modname, 15, $modparams));

        $this->assertLessThanOrEqual(
            $small + 1,
            $large,
            "Listing 15 participants took {$large} reads against {$small} for 3: a query per student has crept in.",
        );
    }

    /**
     * The picture URL built from a batch-loaded context is the one core builds alone.
     */
    public function test_participant_pictures_match_core(): void {
        global $DB, $PAGE;
        $this->resetAfterTest();
        $s = $this->create_scenario('forum', 3);

        $participants = adapter_factory::create((int) $s->cm->id)->get_participants([]);
        $this->assertCount(3, $participants);

        foreach ($participants as $participant) {
            $user = $DB->get_record('user', ['id' => $participant['id']], '*', MUST_EXIST);
            $picture = new \user_picture($user);
            $picture->size = 64;
            $this->assertSame($picture->get_url($PAGE)->out(false), $participant['profileimageurl']);
            $this->assertStringContainsString(
                '/' . \context_user::instance($user->id)->id . '/user/icon/',
                $participant['profileimageurl'],
            );
        }
    }

    /**
     * Under blind marking each student is listed under the anonymous ID core gives them.
     */
    public function test_blind_marking_ids_match_core(): void {
        $this->resetAfterTest();
        $s = $this->create_scenario('assign', 3, ['blindmarking' => 1]);

        $participants = adapter_factory::create((int) $s->cm->id)->get_participants([]);
        $this->assertCount(3, $participants);

        foreach ($participants as $participant) {
            $uniqueid = \assign::get_uniqueid_for_user_static((int) $s->activity->id, $participant['id']);
            $this->assertSame(get_string('hiddenuser', 'assign') . ' ' . $uniqueid, $participant['fullname']);
            $this->assertSame('', $participant['email']);
        }
    }

    /**
     * Team submissions: each student gets their group's submission and their own grade.
     *
     * Students in exactly one group share that group's submission. A student in
     * no group, or in two, belongs to the default group, as in core.
     */
    public function test_team_submissions_match_core(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $this->resetAfterTest();

        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $gen->create_grading_scenario('assign', [
            'studentcount' => 5,
            'modparams' => ['teamsubmission' => 1, 'grade' => 100],
        ]);
        [$alice, $bob, $carol, $dave, $erin] = array_map(fn($u) => (int) $u->id, $s->students);

        $red = $this->getDataGenerator()->create_group(['courseid' => $s->course->id]);
        $blue = $this->getDataGenerator()->create_group(['courseid' => $s->course->id]);
        groups_add_member($red->id, $alice);
        groups_add_member($red->id, $bob);
        groups_add_member($blue->id, $carol);
        // Dave is in no group, and Erin is in both.
        groups_add_member($red->id, $erin);
        groups_add_member($blue->id, $erin);

        // The red group submits, and Alice alone is graded.
        $this->setAdminUser();
        $assign = new \assign($s->context, $s->cm, $s->course);
        $submission = $assign->get_group_submission($alice, 0, true);
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $submission->timemodified = time() - HOURSECS;
        $DB->update_record('assign_submission', $submission);
        $grade = $assign->get_user_grade($alice, true);
        $grade->grade = 70;
        $assign->update_grade($grade);

        $this->setUser($s->teacher);
        $submissionsbefore = $DB->count_records('assign_submission', ['assignment' => $s->activity->id]);
        $participants = [];
        foreach (adapter_factory::create((int) $s->cm->id)->get_participants([]) as $participant) {
            $participants[$participant['id']] = $participant;
        }

        $this->assertSame('graded', $participants[$alice]['status']);
        $this->assertSame(70.0, $participants[$alice]['gradevalue']);
        $this->assertSame('submitted', $participants[$bob]['status']);
        $this->assertNull($participants[$bob]['gradevalue']);
        $this->assertSame((int) $submission->timemodified, $participants[$bob]['submittedat']);
        foreach ([$carol, $dave, $erin] as $userid) {
            $this->assertSame('nosubmission', $participants[$userid]['status']);
            $this->assertSame(0, $participants[$userid]['submittedat']);
        }

        // Listing the class creates no submission rows, which the core calls it replaced did.
        $this->assertSame(
            $submissionsbefore,
            $DB->count_records('assign_submission', ['assignment' => $s->activity->id]),
        );

        // Each student's submission is the one core finds for them.
        $assign = new \assign($s->context, $s->cm, $s->course);
        foreach ($participants as $userid => $participant) {
            $core = $assign->get_group_submission($userid, 0, false);
            $coresubmitted = ($core && $core->status !== 'new') ? (int) $core->timemodified : 0;
            $this->assertSame($coresubmitted, $participant['submittedat'], "Student {$userid}");
        }
    }

    /**
     * The batched effective due dates are the ones worked out student by student.
     *
     * Covers a student with their own override, students covered by one and by
     * two group overrides, and a student with none.
     */
    public function test_quiz_effective_duedates_match_single_lookups(): void {
        global $DB;
        $this->resetAfterTest();

        $unified = compat::unified();
        if (!$unified && !compat::use_quizaccess_duedate()) {
            $this->markTestSkipped('Needs Moodle 5.3, or the quizaccess_duedate rule.');
        }

        $due = time() + WEEKSECS;
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $gen->create_grading_scenario('quiz', [
            'studentcount' => 5,
            'modparams' => $unified ? ['duedate' => $due, 'timeclose' => 0] : [],
        ]);
        [$own, $onegroup, $twogroups, $none, $both] = array_map(fn($u) => (int) $u->id, $s->students);

        $red = $this->getDataGenerator()->create_group(['courseid' => $s->course->id]);
        $blue = $this->getDataGenerator()->create_group(['courseid' => $s->course->id]);
        groups_add_member($red->id, $onegroup);
        groups_add_member($red->id, $twogroups);
        groups_add_member($blue->id, $twogroups);
        // Their own override beats the group's.
        groups_add_member($red->id, $both);

        $overrides = [
            ['userid' => $own, 'groupid' => null, 'duedate' => $due + DAYSECS],
            ['userid' => $both, 'groupid' => null, 'duedate' => $due + 4 * DAYSECS],
            ['userid' => null, 'groupid' => $red->id, 'duedate' => $due + 2 * DAYSECS],
            ['userid' => null, 'groupid' => $blue->id, 'duedate' => $due + 3 * DAYSECS],
        ];
        foreach ($overrides as $override) {
            if ($unified) {
                $DB->insert_record('quiz_overrides', ['quiz' => $s->activity->id] + $override);
            } else {
                $DB->insert_record('quizaccess_duedate_overrides', [
                    'quizid' => $s->activity->id,
                    'timemodified' => time(),
                ] + $override);
            }
        }
        if (!$unified) {
            $settings = $DB->get_record('quizaccess_duedate_instances', ['quizid' => $s->activity->id]);
            if ($settings) {
                $DB->set_field('quizaccess_duedate_instances', 'duedate', $due, ['id' => $settings->id]);
            } else {
                $DB->insert_record('quizaccess_duedate_instances', ['quizid' => $s->activity->id, 'duedate' => $due]);
            }
        }

        $this->setUser($s->teacher);
        $adapter = adapter_factory::create((int) $s->cm->id);
        $userids = [$own, $onegroup, $twogroups, $none, $both];
        $batched = $adapter->get_effective_duedates($userids);

        $this->assertSame($due + DAYSECS, $batched[$own]);
        $this->assertSame($due + 2 * DAYSECS, $batched[$onegroup]);
        $this->assertSame($due + 3 * DAYSECS, $batched[$twogroups]);
        $this->assertSame($due, $batched[$none]);
        $this->assertSame($due + 4 * DAYSECS, $batched[$both]);

        foreach ($userids as $userid) {
            $this->assertSame($adapter->get_effective_duedate($userid), $batched[$userid], "Student {$userid}");
        }
    }

    /**
     * A student's forum attachments are all found, across several posts.
     */
    public function test_forum_attachments_across_posts(): void {
        global $DB;
        $this->resetAfterTest();

        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $gen->create_grading_scenario('forum', ['studentcount' => 2]);
        [$student, $classmate] = array_map(fn($u) => (int) $u->id, $s->students);

        $fs = get_file_storage();
        $attach = function (int $postid, string $filename) use ($fs, $s, $DB): void {
            $fs->create_file_from_string([
                'contextid' => $s->context->id,
                'component' => 'mod_forum',
                'filearea' => 'attachment',
                'itemid' => $postid,
                'filepath' => '/',
                'filename' => $filename,
            ], 'content');
            $DB->set_field('forum_posts', 'attachment', 1, ['id' => $postid]);
        };

        $first = $gen->create_forum_post($s->activity, $student);
        $second = $gen->create_forum_post($s->activity, $student);
        $gen->create_forum_post($s->activity, $student);
        $other = $gen->create_forum_post($s->activity, $classmate);
        $attach((int) $first->post->id, 'essay.txt');
        $attach((int) $first->post->id, 'appendix.txt');
        $attach((int) $second->post->id, 'notes.txt');
        $attach((int) $other->post->id, 'classmate.txt');

        $this->setUser($s->teacher);
        $adapter = adapter_factory::create((int) $s->cm->id);

        $before = $DB->perf_get_reads();
        $files = $adapter->get_submission_files($student);
        $reads = $DB->perf_get_reads() - $before;

        $names = array_column($files, 'filename');
        sort($names);
        $this->assertSame(['appendix.txt', 'essay.txt', 'notes.txt'], $names);
        // Discussions, posts and files: one query each, however many posts.
        $this->assertLessThanOrEqual(3, $reads);
    }
}
