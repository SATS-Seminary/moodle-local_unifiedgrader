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

/**
 * Typing "--" in the grade field must clear a gradebook override.
 *
 * "--" is the deliberate-reset escape hatch: it clears the grade and lifts any
 * override pinning the gradebook cell (one is commonly left behind by the
 * penalty subsystem). Guarded here because the penalty work moved assignments to
 * raw-grade storage with a gradebook sync, and that sync must not resurrect an
 * override the reset just removed.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\adapter\assign_adapter::reset_grade_and_submission
 */
final class reset_override_test extends \advanced_testcase {
    /**
     * Build a graded assignment with an overridden gradebook cell.
     *
     * @return object {cm, adapter, teacher, student}
     */
    private function overridden_scenario(): object {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id, 'grade' => 12.0]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $teacher = $gen->create_user();
        $student = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $gen->enrol_user($student->id, $course->id, 'student');
        $this->setUser($teacher);

        $adapter = adapter_factory::create($cm->id);
        $adapter->save_grade($student->id, 8.0, '', FORMAT_HTML);

        // Pin the gradebook cell, as the gradebook spreadsheet or the penalty
        // subsystem would.
        $item = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'iteminstance' => $cm->instance,
            'itemnumber' => 0,
        ]);
        $gradegrade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $student->id]);
        $gradegrade->set_overridden(true, false);

        return (object) [
            'cm' => $cm,
            'adapter' => $adapter,
            'teacher' => $teacher,
            'student' => $student,
            'itemid' => $item->id,
        ];
    }

    /**
     * Whether the student's gradebook cell is currently overridden.
     *
     * @param object $s The scenario.
     * @return bool
     */
    private function is_overridden(object $s): bool {
        $gradegrade = \grade_grade::fetch(['itemid' => $s->itemid, 'userid' => $s->student->id]);
        return $gradegrade && !empty($gradegrade->overridden);
    }

    /**
     * The override is genuinely set up, or the test below proves nothing.
     */
    public function test_scenario_starts_overridden(): void {
        $this->resetAfterTest();
        $s = $this->overridden_scenario();

        $this->assertTrue($this->is_overridden($s), 'Precondition: the cell is overridden.');
    }

    /**
     * "--" through the web service clears the override.
     */
    public function test_double_dash_reset_clears_the_override(): void {
        $this->resetAfterTest();
        $s = $this->overridden_scenario();

        // What the panel sends when the teacher types "--": grade cleared, reset set.
        \local_unifiedgrader\external\save_grade::execute(
            (int) $s->cm->id,
            (int) $s->student->id,
            -1,
            '',
            FORMAT_HTML,
            '',
            0,
            0,
            -1,
            true,
        );

        $this->assertFalse($this->is_overridden($s), 'The override must be lifted.');
    }

    /**
     * "--" clears the override on a QUIZ.
     *
     * A quiz mark is computed from the attempt, so there is no stored grade of
     * ours to clear — but an overridden gradebook cell pins the mark regardless,
     * and lifting it is exactly what the teacher means by resetting. The quiz
     * adapter inherits the base reset, which used to return true without doing
     * anything: "--" reported success, changed nothing, and the override had to
     * be unticked by hand in the gradebook.
     */
    public function test_double_dash_reset_clears_a_quiz_override(): void {
        $this->resetAfterTest();
        set_config('enable_quiz', 1, 'local_unifiedgrader');

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz = $gen->create_module('quiz', ['course' => $course->id, 'grade' => 10.0]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $teacher = $gen->create_user();
        $student = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $gen->enrol_user($student->id, $course->id, 'student');
        $this->setUser($teacher);

        // Pin the gradebook cell, as manual gradebook editing does.
        $item = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $cm->instance,
            'itemnumber' => 0,
        ]);
        $gradegrade = new \grade_grade((object) [
            'itemid' => $item->id,
            'userid' => $student->id,
        ], false);
        $gradegrade->insert();
        $gradegrade->set_overridden(true, false);

        $before = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $student->id]);
        $this->assertNotEmpty($before->overridden, 'Precondition: the quiz cell is overridden.');

        \local_unifiedgrader\external\save_grade::execute(
            (int) $cm->id,
            (int) $student->id,
            -1,
            '',
            FORMAT_HTML,
            '',
            0,
            0,
            -1,
            true,
        );

        $after = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $student->id]);
        $this->assertEmpty(
            $after->overridden,
            '"--" must lift the override so the quiz mark recomputes from the attempt.'
        );
    }

    /**
     * The same, with a penalty in force — the case the raw-grade change touched.
     */
    public function test_double_dash_reset_clears_the_override_with_a_penalty(): void {
        $this->resetAfterTest();
        $s = $this->overridden_scenario();
        penalty_manager::save_penalty(
            $s->cm->id,
            $s->student->id,
            $s->teacher->id,
            'other',
            'plagiarism',
            100,
        );

        \local_unifiedgrader\external\save_grade::execute(
            (int) $s->cm->id,
            (int) $s->student->id,
            -1,
            '',
            FORMAT_HTML,
            '',
            0,
            0,
            -1,
            true,
        );

        $this->assertFalse(
            $this->is_overridden($s),
            'A penalty must not keep the override pinned after a reset.'
        );
    }
}
