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
 * The quiz grading pane must be able to show the whole-quiz grade while marking.
 *
 * The marking guide only lists the manually-graded questions, so the pane needs
 * the marks already earned on the other questions to display a running total.
 * It cannot read those from the stored quiz grade: an attempt with any question
 * still awaiting marking has sumgrades = NULL, so exactly the students being
 * marked have no stored grade at all. The adapter therefore ships the auto-marked
 * total (and the raw-to-gradebook scale) with the grading definition.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\adapter\quiz_adapter
 */
final class quiz_manual_grade_display_test extends \advanced_testcase {
    /**
     * The grading definition carries the auto-marked total and the grade scale.
     */
    public function test_grading_definition_carries_auto_marks_and_scale(): void {
        $this->resetAfterTest();
        set_config('enable_quiz', 1, 'local_unifiedgrader');

        [$quiz, $teacher, $student] = $this->setup_quiz_with_attempt();

        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $this->setUser($teacher);
        $adapter = adapter_factory::create($cm->id);
        $gradedata = $adapter->get_grade_data((int) $student->id);

        // The premise: nothing is stored yet, because the essay is unmarked.
        $this->assertNull(
            $gradedata['grade'],
            'An attempt with an unmarked question has no stored quiz grade — which is '
                . 'why the pane cannot derive the running total from it.'
        );

        $definition = json_decode($gradedata['gradingdefinition'], true);
        $this->assertSame('quizmanual', $definition['method']);
        // The true/false question was answered correctly for 4 of the 10 raw marks;
        // the remaining 6 belong to the essay the teacher is about to mark.
        $this->assertEqualsWithDelta(4.0, $definition['quizautomarks'], 0.001);
        $this->assertEqualsWithDelta(10.0, $definition['quizsummax'], 0.001);
        $this->assertEqualsWithDelta(100.0, $definition['quizmaxgrade'], 0.001);

        // Only the essay is shown for marking, so the guide total alone (0–6) would
        // read as the whole grade without the fields asserted above.
        $this->assertCount(1, $definition['criteria']);
        $this->assertEqualsWithDelta(6.0, (float) $definition['criteria'][0]['maxscore'], 0.001);
    }

    /**
     * Marking the essay produces the grade the pane computes from those fields:
     * (auto marks + manual marks) / raw total × maximum grade.
     */
    public function test_saved_grade_matches_the_scaled_running_total(): void {
        $this->resetAfterTest();
        set_config('enable_quiz', 1, 'local_unifiedgrader');

        [$quiz, $teacher, $student] = $this->setup_quiz_with_attempt();

        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $this->setUser($teacher);
        $adapter = adapter_factory::create($cm->id);
        $definition = json_decode($adapter->get_grade_data((int) $student->id)['gradingdefinition'], true);
        $essayslot = (int) $definition['criteria'][0]['id'];

        $adapter->save_grade(
            (int) $student->id,
            null,
            '',
            FORMAT_HTML,
            [
                'method' => 'quizmanual',
                'questions' => [
                    $essayslot => ['mark' => 3.0, 'comment' => 'Half marks.'],
                ],
            ],
        );

        // Four auto marks plus three manual, of ten raw, on a quiz out of 100 —
        // 70, which is what the pane shows live and what the quiz module stores
        // once the save completes.
        $expected = (($definition['quizautomarks'] + 3.0) / $definition['quizsummax'])
            * $definition['quizmaxgrade'];
        $this->assertEqualsWithDelta(70.0, $expected, 0.001);

        $stored = $adapter->get_grade_data((int) $student->id);
        $this->assertEqualsWithDelta($expected, (float) $stored['grade'], 0.001);
    }

    /**
     * Build a quiz with one auto-marked and one manually-marked question, and a
     * finished attempt that answers the auto-marked one correctly.
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
