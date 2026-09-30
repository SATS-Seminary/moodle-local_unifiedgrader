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

namespace local_unifiedgrader\adapter;

use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;
use question_engine;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Quiz question comments are written in a rich-text editor, with files.
 *
 * The grader gives each manually marked question the editor Moodle's own manual
 * grading page gives it, so a teacher can format the comment and attach images
 * or recorded audio. Those files live in the comment's draft area while it is
 * edited and must end up with the grading step, where the quiz serves them.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\adapter\quiz_adapter
 */
final class quiz_comment_files_test extends \advanced_testcase {
    /**
     * A comment's draft files are saved with it and come back for the next edit.
     */
    public function test_comment_files_round_trip(): void {
        $this->resetAfterTest();
        set_config('enable_quiz', 1, 'local_unifiedgrader');

        [$quiz, $teacher, $student] = $this->setup_quiz_with_attempt();
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $context = \context_module::instance($cm->id);
        $this->setUser($teacher);
        $adapter = adapter_factory::create($cm->id);

        // The editor loads with an empty draft area for the unmarked essay.
        $drafts = $adapter->prepare_question_comment_drafts((int) $student->id);
        $this->assertCount(1, $drafts, 'Only the essay is manually marked.');
        $slot = $drafts[0]['slot'];
        $draftitemid = $drafts[0]['draftitemid'];
        $this->assertSame('', $drafts[0]['html']);

        // The teacher records audio into it, as the editor's recorder does.
        $usercontext = \context_user::instance($teacher->id);
        get_file_storage()->create_file_from_string([
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'feedback.mp3',
        ], 'audio bytes');
        $draftlink = \moodle_url::make_draftfile_url($draftitemid, '/', 'feedback.mp3')->out(false);
        $comment = '<p><strong>Well argued.</strong></p><p><audio controls><source src="' . $draftlink
            . '"></audio></p>';

        $questions = [$slot => ['mark' => 5.0, 'comment' => $comment, 'draftitemid' => $draftitemid]];
        $adapter->save_grade((int) $student->id, null, '', FORMAT_HTML, ['method' => 'quizmanual', 'questions' => $questions]);

        $qa = $this->load_question_attempt((int) $quiz->id, (int) $student->id, $slot);
        [$storedcomment, , $step] = $qa->get_manual_comment();
        $this->assertStringContainsString('<strong>Well argued.</strong>', $storedcomment);
        $this->assertStringContainsString('@@PLUGINFILE@@/feedback.mp3', $storedcomment);
        $this->assertStringNotContainsString('draftfile.php', $storedcomment);
        $files = get_file_storage()->get_area_files(
            $context->id,
            'question',
            'response_bf_comment',
            $step->get_id(),
            'filename',
            false,
        );
        $this->assertSame(['feedback.mp3'], array_values(array_map(fn($f) => $f->get_filename(), $files)));

        // Saving again with nothing changed adds no grading step.
        $stepsbefore = $qa->get_num_steps();
        $adapter->save_grade((int) $student->id, null, '', FORMAT_HTML, ['method' => 'quizmanual', 'questions' => $questions]);
        $qa = $this->load_question_attempt((int) $quiz->id, (int) $student->id, $slot);
        $this->assertSame($stepsbefore, $qa->get_num_steps());

        // The next student load copies the file into a fresh draft area for the editor.
        $drafts = $adapter->prepare_question_comment_drafts((int) $student->id);
        $this->assertNotSame($draftitemid, $drafts[0]['draftitemid']);
        $this->assertStringContainsString('draftfile.php', $drafts[0]['html']);
        $this->assertStringContainsString('<strong>Well argued.</strong>', $drafts[0]['html']);
        $draftfiles = get_file_storage()->get_area_files(
            $usercontext->id,
            'user',
            'draft',
            $drafts[0]['draftitemid'],
            'filename',
            false,
        );
        $this->assertCount(1, $draftfiles);
    }

    /**
     * Comments saved from the old plain textarea keep their line breaks in the editor.
     */
    public function test_plain_text_comment_keeps_line_breaks(): void {
        $this->resetAfterTest();
        set_config('enable_quiz', 1, 'local_unifiedgrader');

        [$quiz, $teacher, $student] = $this->setup_quiz_with_attempt();
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $this->setUser($teacher);
        $adapter = adapter_factory::create($cm->id);
        $slot = $adapter->prepare_question_comment_drafts((int) $student->id)[0]['slot'];

        // What the textarea sent: no draft area, plain text stored as FORMAT_HTML.
        $adapter->save_grade((int) $student->id, null, '', FORMAT_HTML, [
            'method' => 'quizmanual',
            'questions' => [$slot => ['mark' => 4.0, 'comment' => "First point.\nSecond point."]],
        ]);

        $html = $adapter->prepare_question_comment_drafts((int) $student->id)[0]['html'];
        $this->assertSame("First point.<br>\nSecond point.", $html);
    }

    /**
     * Load the student's question attempt for a slot, fresh from the database.
     *
     * @param int $quizid
     * @param int $userid
     * @param int $slot
     * @return \question_attempt
     */
    private function load_question_attempt(int $quizid, int $userid, int $slot): \question_attempt {
        $attempts = quiz_get_user_attempts($quizid, $userid, 'finished');
        $attempt = reset($attempts);
        return question_engine::load_questions_usage_by_activity($attempt->uniqueid)->get_question_attempt($slot);
    }

    /**
     * Build a quiz with one auto-marked and one manually-marked question, and a
     * finished attempt.
     *
     * @return array [$quiz, $teacher, $student]
     */
    private function setup_quiz_with_attempt(): array {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz = $gen->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $course->id,
            'questionsperpage' => 0,
            'grade' => 100.0,
            'sumgrades' => 0,
        ]);

        $qgen = $gen->get_plugin_generator('core_question');
        $cat = $qgen->create_question_category();
        $truefalse = $qgen->create_question('truefalse', null, ['category' => $cat->id]);
        quiz_add_quiz_question($truefalse->id, $quiz, 0, 4);
        $essay = $qgen->create_question('essay', null, ['category' => $cat->id]);
        quiz_add_quiz_question($essay->id, $quiz, 0, 6);
        \mod_quiz\grade_calculator::create(quiz_settings::create($quiz->id))->recompute_quiz_sumgrades();

        $teacher = $gen->create_user();
        $student = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $gen->enrol_user($student->id, $course->id, 'student');

        $this->setUser($student);
        $timenow = time();
        $quizobj = quiz_settings::create($quiz->id, $student->id);
        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
        $attempt = quiz_create_attempt($quizobj, 1, null, $timenow, false, (int) $student->id);
        quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);

        $attemptobj = quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, [
            1 => ['answer' => 1],
            2 => ['answer' => 'An essay for the teacher to mark.', 'answerformat' => FORMAT_HTML],
        ]);
        $attemptobj->process_submit($timenow, false);
        $attemptobj->process_grade_submission($timenow);
        $this->setUser();

        return [$quiz, $teacher, $student];
    }
}
