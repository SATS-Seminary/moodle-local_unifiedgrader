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

/**
 * Marking a quiz question must raise the same event Moodle's own grading page raises.
 *
 * The grader writes the question usage directly, which is quicker than going
 * through mod_quiz's grading report but bypasses \mod_quiz\event\question_manually_graded.
 * Other plugins hang grading behaviour off that event — a late-penalty rule pins
 * the gradebook cell with an override (which is what stops the quiz module
 * overwriting the penalised mark) and only lifts it, recalculates and re-applies
 * the penalty when the event arrives. Without it, everything marked in the grader
 * stayed out of the gradebook with no error shown.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\adapter\quiz_adapter
 */
final class quiz_manual_grading_event_test extends \advanced_testcase {
    use \mod_quiz\tests\question_helper_test_trait;

    /**
     * Marking a question raises question_manually_graded with the expected payload.
     */
    public function test_manual_grading_raises_question_manually_graded(): void {
        $this->resetAfterTest();
        set_config('enable_quiz', 1, 'local_unifiedgrader');

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz = $this->create_test_quiz($course);
        $questiongenerator = $gen->get_plugin_generator('core_question');
        $this->add_two_regular_questions($questiongenerator, $quiz);

        $teacher = $gen->create_user();
        $student = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $gen->enrol_user($student->id, $course->id, 'student');

        // A finished attempt to mark.
        $this->attempt_quiz($quiz, $student);
        $this->setUser($teacher);

        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $adapter = adapter_factory::create($cm->id);

        $sink = $this->redirectEvents();
        $adapter->save_grade(
            (int) $student->id,
            null,
            '',
            FORMAT_HTML,
            [
                'method' => 'quizmanual',
                'questions' => [
                    1 => ['mark' => 1.0, 'comment' => 'Good reasoning.'],
                ],
            ],
        );
        $events = $sink->get_events();
        $sink->close();

        $raised = array_values(array_filter(
            $events,
            static fn($e) => $e instanceof \mod_quiz\event\question_manually_graded
        ));

        $this->assertNotEmpty(
            $raised,
            'Marking a question must raise question_manually_graded, or plugins that '
                . 'own grading behaviour (e.g. a late-penalty rule holding a gradebook '
                . 'override) never get told to recalculate.'
        );

        // The payload has to be complete: the event validates these and throws
        // without them, so an empty or partial one would break the listeners.
        $event = reset($raised);
        $this->assertSame((int) $quiz->id, (int) $event->other['quizid']);
        $this->assertArrayHasKey('attemptid', $event->other);
        $this->assertNotEmpty($event->other['attemptid']);
        $this->assertSame(1, (int) $event->other['slot']);
        $this->assertNotEmpty($event->objectid, 'The graded question id must be set.');
    }

    /**
     * Saving feedback without marking any question raises no grading event.
     *
     * The event means "a question was marked"; raising it for a feedback-only save
     * would make listeners recalculate — and a late-penalty rule would churn the
     * gradebook — for a save that changed no marks.
     */
    public function test_feedback_only_save_raises_no_grading_event(): void {
        $this->resetAfterTest();
        set_config('enable_quiz', 1, 'local_unifiedgrader');

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz = $this->create_test_quiz($course);
        $questiongenerator = $gen->get_plugin_generator('core_question');
        $this->add_two_regular_questions($questiongenerator, $quiz);

        $teacher = $gen->create_user();
        $student = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $gen->enrol_user($student->id, $course->id, 'student');

        $this->attempt_quiz($quiz, $student);
        $this->setUser($teacher);

        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $adapter = adapter_factory::create($cm->id);

        $sink = $this->redirectEvents();
        $adapter->save_grade((int) $student->id, null, 'Overall feedback only.', FORMAT_HTML);
        $events = $sink->get_events();
        $sink->close();

        $raised = array_filter(
            $events,
            static fn($e) => $e instanceof \mod_quiz\event\question_manually_graded
        );

        $this->assertEmpty($raised, 'A feedback-only save marks no question.');
    }
}
