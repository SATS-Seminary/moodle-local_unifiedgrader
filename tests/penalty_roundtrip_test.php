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
 * The mark a teacher types must survive a penalty unchanged.
 *
 * Assignments store the RAW (typed) grade in assign_grades; the penalty deduction
 * is applied only on the way to the gradebook. Storing the reduced grade instead
 * made the typed mark unrecoverable for display: the panel added the deduction
 * back to reconstruct it, which inflated the field when a penalty was applied
 * after grading (6/12 with a 100% penalty displayed as 18) and erased it when the
 * penalty clamped the stored grade to zero.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\adapter\assign_adapter::sync_gradebook_penalty
 */
final class penalty_roundtrip_test extends \advanced_testcase {
    /** @var float The activity maximum used throughout. */
    private const MAXGRADE = 12.0;

    /**
     * Build a graded assignment scenario.
     *
     * @param float $maxgrade The activity maximum.
     * @return object {cm, adapter, teacher, student}
     */
    private function scenario(float $maxgrade = self::MAXGRADE): object {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id, 'grade' => $maxgrade]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $teacher = $gen->create_user();
        $student = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $gen->enrol_user($student->id, $course->id, 'student');
        $this->setUser($teacher);

        return (object) [
            'cm' => $cm,
            'adapter' => adapter_factory::create($cm->id),
            'teacher' => $teacher,
            'student' => $student,
        ];
    }

    /**
     * Add a penalty for the student.
     *
     * @param object $s The scenario.
     * @param int $pct The penalty percentage.
     * @param string $label The penalty label.
     */
    private function add_penalty(object $s, int $pct, string $label = 'plagiarism'): void {
        penalty_manager::save_penalty(
            $s->cm->id,
            $s->student->id,
            $s->teacher->id,
            'other',
            $label,
            $pct,
        );
    }

    /**
     * The grade the grader shows back to the teacher.
     *
     * Assignments store raw, so this is simply what is stored — no reconstruction.
     *
     * @param object $s The scenario.
     * @return float|null
     */
    private function displayed_grade(object $s): ?float {
        $grade = $s->adapter->get_grade_data($s->student->id)['grade'];
        return $grade === null ? null : (float) $grade;
    }

    /**
     * The value the gradebook holds for the student.
     *
     * @param object $s The scenario.
     * @return float|null
     */
    private function gradebook_grade(object $s): ?float {
        $grades = grade_get_grades(
            $s->cm->course,
            'mod',
            'assign',
            $s->cm->instance,
            [$s->student->id],
        );
        $item = reset($grades->items);
        if (!$item) {
            return null;
        }
        $g = $item->grades[$s->student->id]->grade ?? null;
        return $g === null ? null : (float) $g;
    }

    /**
     * The reported bug: mark the work, THEN apply a plagiarism penalty.
     *
     * The field must still read what the teacher typed. Previously it showed
     * 6 + 12 = 18, i.e. 150% of a 12-mark assignment.
     */
    public function test_penalty_applied_after_grading_leaves_the_mark_alone(): void {
        $this->resetAfterTest();
        $s = $this->scenario();

        $s->adapter->save_grade($s->student->id, 6.0, '', FORMAT_HTML);
        $this->add_penalty($s, 100);

        $this->assertEqualsWithDelta(6.0, $this->displayed_grade($s), 0.01);
    }

    /**
     * A 100% penalty must not destroy the typed mark.
     *
     * Previously the stored grade clamped to zero (6 - 12), so the mark could not
     * be recovered and the field showed the deduction itself.
     */
    public function test_full_penalty_preserves_the_typed_mark(): void {
        $this->resetAfterTest();
        $s = $this->scenario();

        $this->add_penalty($s, 100);
        $s->adapter->save_grade($s->student->id, 6.0, '', FORMAT_HTML);
        $s->adapter->sync_gradebook_penalty($s->student->id);

        $this->assertEqualsWithDelta(6.0, $this->displayed_grade($s), 0.01, 'Field shows the typed mark.');
        $this->assertEqualsWithDelta(0.0, $this->gradebook_grade($s), 0.01, 'Gradebook is fully penalised.');
    }

    /**
     * A partial penalty still reaches the gradebook, while the field stays raw.
     */
    public function test_partial_penalty_deducts_in_the_gradebook_only(): void {
        $this->resetAfterTest();
        $s = $this->scenario();

        $this->add_penalty($s, 25);
        $s->adapter->save_grade($s->student->id, 10.0, '', FORMAT_HTML);
        $s->adapter->sync_gradebook_penalty($s->student->id);

        // 25% of 12 = 3 marks off.
        $this->assertEqualsWithDelta(10.0, $this->displayed_grade($s), 0.01);
        $this->assertEqualsWithDelta(7.0, $this->gradebook_grade($s), 0.01);
    }

    /**
     * Several penalties accumulate against the gradebook value.
     */
    public function test_multiple_penalties_accumulate(): void {
        $this->resetAfterTest();
        $s = $this->scenario();

        $this->add_penalty($s, 25, 'plagiarism');
        $this->add_penalty($s, 25, 'wordcount');
        $s->adapter->save_grade($s->student->id, 12.0, '', FORMAT_HTML);
        $s->adapter->sync_gradebook_penalty($s->student->id);

        // 50% of 12 = 6 marks off.
        $this->assertEqualsWithDelta(12.0, $this->displayed_grade($s), 0.01);
        $this->assertEqualsWithDelta(6.0, $this->gradebook_grade($s), 0.01);
    }

    /**
     * Re-saving must be stable: the mark must not drift on each save.
     *
     * This is what made the original bug visible — the teacher saved feedback and
     * watched the grade field climb.
     */
    public function test_repeated_saves_do_not_drift(): void {
        $this->resetAfterTest();
        $s = $this->scenario();

        $this->add_penalty($s, 100);
        for ($i = 0; $i < 3; $i++) {
            // Each save re-sends what the field is showing.
            $shown = $this->displayed_grade($s) ?? 6.0;
            $s->adapter->save_grade($s->student->id, $shown, '', FORMAT_HTML);
            $s->adapter->sync_gradebook_penalty($s->student->id);
        }

        $this->assertEqualsWithDelta(6.0, $this->displayed_grade($s), 0.01);
    }

    /**
     * With no penalty the gradebook simply mirrors the mark.
     */
    public function test_no_penalty_is_unchanged(): void {
        $this->resetAfterTest();
        $s = $this->scenario();

        $s->adapter->save_grade($s->student->id, 9.0, '', FORMAT_HTML);
        $s->adapter->sync_gradebook_penalty($s->student->id);

        $this->assertEqualsWithDelta(9.0, $this->displayed_grade($s), 0.01);
        $this->assertEqualsWithDelta(9.0, $this->gradebook_grade($s), 0.01);
    }

    /**
     * Removing the penalty restores the full mark in the gradebook.
     */
    public function test_clearing_the_penalty_restores_the_gradebook(): void {
        $this->resetAfterTest();
        global $DB;
        $s = $this->scenario();

        $this->add_penalty($s, 50);
        $s->adapter->save_grade($s->student->id, 10.0, '', FORMAT_HTML);
        $s->adapter->sync_gradebook_penalty($s->student->id);
        $this->assertEqualsWithDelta(4.0, $this->gradebook_grade($s), 0.01);

        $DB->delete_records('local_unifiedgrader_penalty', [
            'cmid' => $s->cm->id,
            'userid' => $s->student->id,
        ]);
        $s->adapter->save_grade($s->student->id, 10.0, '', FORMAT_HTML);
        $s->adapter->sync_gradebook_penalty($s->student->id);

        $this->assertEqualsWithDelta(10.0, $this->displayed_grade($s), 0.01);
        $this->assertEqualsWithDelta(10.0, $this->gradebook_grade($s), 0.01);
    }

    /**
     * End-to-end through the web service the grader actually calls.
     *
     * The tests above drive the adapter directly; this one goes through
     * save_grade::execute(), which is where the decision to store raw and then
     * sync the gradebook lives. A regression in that branching would not show up
     * anywhere else.
     */
    public function test_save_grade_external_stores_raw_and_penalises_gradebook(): void {
        $this->resetAfterTest();
        $s = $this->scenario();
        $this->add_penalty($s, 100);

        \local_unifiedgrader\external\save_grade::execute(
            (int) $s->cm->id,
            (int) $s->student->id,
            6.0,
            'Well argued.',
        );

        $this->assertEqualsWithDelta(
            6.0,
            $this->displayed_grade($s),
            0.01,
            'The grade field must show the mark the teacher typed.'
        );
        $this->assertEqualsWithDelta(
            0.0,
            $this->gradebook_grade($s),
            0.01,
            'The gradebook must still carry the full 100% deduction.'
        );
    }

    /**
     * A forum grade keeps its existing behaviour: raw stored, gradebook penalised.
     *
     * Forums already used this model, so this guards against the assignment change
     * disturbing them.
     */
    public function test_forum_behaviour_is_unchanged(): void {
        $this->resetAfterTest();
        // The grader only handles activity types it is switched on for.
        set_config('enable_forum', 1, 'local_unifiedgrader');
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $forum = $gen->create_module('forum', [
            'course' => $course->id,
            'grade_forum' => 100,
        ]);
        $cm = get_coursemodule_from_instance('forum', $forum->id);
        $teacher = $gen->create_user();
        $student = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $gen->enrol_user($student->id, $course->id, 'student');
        $this->setUser($teacher);

        penalty_manager::save_penalty($cm->id, $student->id, $teacher->id, 'other', 'plagiarism', 25);

        $adapter = adapter_factory::create($cm->id);
        $adapter->save_grade($student->id, 80.0, '', FORMAT_HTML);
        $adapter->sync_gradebook_penalty($student->id);

        $this->assertEqualsWithDelta(
            80.0,
            (float) $adapter->get_grade_data($student->id)['grade'],
            0.01,
            'Forums store the raw mark, as before.'
        );
    }

    /**
     * Syncing with no grade recorded must not invent one.
     */
    public function test_sync_without_a_grade_is_a_noop(): void {
        $this->resetAfterTest();
        $s = $this->scenario();

        $this->add_penalty($s, 50);
        $s->adapter->sync_gradebook_penalty($s->student->id);

        $this->assertNull($this->displayed_grade($s));
    }
}
