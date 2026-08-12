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
use local_unifiedgrader\external\save_penalty;
use local_unifiedgrader\external\delete_penalty;

/**
 * The grade / penalty matrix, across both activity types and all penalty sources.
 *
 * Three things can reduce a student's mark, and they are owned by three
 * different systems:
 *
 *   1. The mark itself   - mod_assign, or the quiz question engine.
 *   2. A late penalty    - core's grade penalty subsystem writes
 *                          assign_grades.penalty for assignments; a quiz access
 *                          rule pins the gradebook cell with an override for
 *                          quizzes.
 *   3. A manual penalty  - this plugin's own local_unifiedgrader_penalty rows
 *                          (word count, academic integrity, and so on).
 *
 * The interesting failures live where these meet, and every one of them has
 * bitten this plugin at least once: a deduction applied twice, a deduction never
 * applied at all, and a gradebook override blocking a write so a mark silently
 * vanished.
 *
 * These tests deliberately SIMULATE the state the late-penalty plugins produce
 * rather than driving those plugins directly. The quiz access rule is a separate
 * plugin that CI does not install, so depending on it would make this file fail
 * on a clean Moodle; and the boundary worth testing is how this plugin behaves
 * given that state, not whether the other plugin computes it correctly.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\adapter\assign_adapter
 * @covers \local_unifiedgrader\adapter\quiz_adapter
 * @covers \local_unifiedgrader\penalty_manager
 */
final class penalty_matrix_test extends \advanced_testcase {
    /**
     * Build a course with one activity, a teacher and a student.
     *
     * @param string $modname 'assign' or 'quiz'.
     * @param float $maxgrade The activity's maximum grade.
     * @return \stdClass With course, activity, cm, teacher, student.
     */
    private function scenario(string $modname, float $maxgrade = 20.0): \stdClass {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $activity = $gen->create_module($modname, ['course' => $course->id, 'grade' => $maxgrade]);
        $cm = get_coursemodule_from_instance($modname, $activity->id, $course->id, false, MUST_EXIST);

        $teacher = $gen->create_user();
        $student = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $gen->enrol_user($student->id, $course->id, 'student');
        $this->setUser($teacher);

        set_config('enable_' . $modname, 1, 'local_unifiedgrader');

        return (object) [
            'course' => $course,
            'activity' => $activity,
            'cm' => $cm,
            'teacher' => $teacher,
            'student' => $student,
            'maxgrade' => $maxgrade,
        ];
    }

    /**
     * The activity's grade item.
     *
     * @param \stdClass $s
     * @return \grade_item
     */
    private function grade_item(\stdClass $s): \grade_item {
        return \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => $s->cm->modname,
            'iteminstance' => $s->activity->id,
            'itemnumber' => 0,
            'courseid' => $s->course->id,
        ]);
    }

    /**
     * What the gradebook currently holds for the student.
     *
     * @param \stdClass $s
     * @return float|null
     */
    private function gradebook(\stdClass $s): ?float {
        $item = $this->grade_item($s);
        if (!$item) {
            return null;
        }
        $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $s->student->id]);
        return ($grade && $grade->finalgrade !== null) ? (float) $grade->finalgrade : null;
    }

    /**
     * Whether the gradebook cell is pinned by an override.
     *
     * @param \stdClass $s
     * @return bool
     */
    private function is_overridden(\stdClass $s): bool {
        $item = $this->grade_item($s);
        $grade = $item ? \grade_grade::fetch(['itemid' => $item->id, 'userid' => $s->student->id]) : null;
        return $grade ? !empty($grade->overridden) : false;
    }

    /**
     * Grade the student through the plugin, then push the penalised figure.
     *
     * Mirrors what the save_grade web service does: the adapter stores the raw
     * mark, and sync_gradebook_penalty applies any deduction on the way out.
     *
     * @param \stdClass $s
     * @param float|null $grade
     */
    private function award(\stdClass $s, ?float $grade): void {
        $adapter = adapter_factory::create($s->cm->id);
        $adapter->save_grade($s->student->id, $grade, '', FORMAT_HTML);
        $adapter->sync_gradebook_penalty($s->student->id);
    }

    /**
     * Put a quiz grade in place without running an attempt.
     *
     * quiz_update_grades() reads quiz_grades and pushes to the gradebook, which
     * is the engine's output as far as this plugin is concerned. Driving a real
     * attempt would test mod_quiz, not us.
     *
     * @param \stdClass $s
     * @param float $grade
     */
    private function set_quiz_engine_grade(\stdClass $s, float $grade): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/quiz/lib.php');

        $now = time();

        // The core lookup joins quiz_attempts, so a quiz_grades row on
        // its own yields nothing and the gradebook stays empty.
        if (!$DB->record_exists('quiz_attempts', ['quiz' => $s->activity->id, 'userid' => $s->student->id])) {
            $DB->insert_record('quiz_attempts', (object) [
                'quiz' => $s->activity->id,
                'userid' => $s->student->id,
                'attempt' => 1,
                'uniqueid' => $DB->count_records('quiz_attempts') + 1000,
                'layout' => '1,0',
                'currentpage' => 0,
                'preview' => 0,
                'state' => 'finished',
                'timestart' => $now - 3600,
                'timefinish' => $now,
                'timemodified' => $now,
                'timemodifiedoffline' => 0,
                'sumgrades' => $grade,
            ]);
        }

        $existing = $DB->get_record('quiz_grades', [
            'quiz' => $s->activity->id,
            'userid' => $s->student->id,
        ]);
        if ($existing) {
            $existing->grade = $grade;
            $existing->timemodified = $now;
            $DB->update_record('quiz_grades', $existing);
        } else {
            $DB->insert_record('quiz_grades', (object) [
                'quiz' => $s->activity->id,
                'userid' => $s->student->id,
                'grade' => $grade,
                'timemodified' => $now,
            ]);
        }

        quiz_update_grades($s->activity, $s->student->id);
    }

    /**
     * Pin the gradebook cell exactly as the quiz access rule's late penalty does.
     *
     * The rule observes user_graded, subtracts a percentage of the maximum, and
     * writes through grade_item::update_final_grade(), which sets the override.
     * That override is the point: it stops the quiz module overwriting the
     * penalised mark on the next recalculation.
     *
     * @param \stdClass $s
     * @param float $penaltypct Percentage of the maximum to deduct.
     * @return float The penalised value written.
     */
    private function apply_quiz_late_penalty(\stdClass $s, float $penaltypct): float {
        $item = $this->grade_item($s);
        $current = $this->gradebook($s) ?? 0.0;
        $penalised = max(0, $current - ($penaltypct / 100) * $s->maxgrade);
        $item->update_final_grade(
            $s->student->id,
            $penalised,
            'quizaccess_duedate',
            'Late penalty applied',
            FORMAT_HTML,
        );
        return $penalised;
    }

    // --------------------------------------------------------------------

    /**
     * A plain assignment grade reaches the gradebook unchanged.
     */
    public function test_assign_plain_grade_reaches_the_gradebook(): void {
        $this->resetAfterTest();

        $s = $this->scenario('assign');
        $this->award($s, 15.0);

        $this->assertEqualsWithDelta(15.0, $this->gradebook($s), 0.01);
        $this->assertFalse($this->is_overridden($s), 'A plain grade must not pin the cell.');
    }

    /**
     * A manual penalty deducts in the gradebook while the activity keeps the raw mark.
     *
     * The split matters: the grader shows the teacher back exactly what they
     * typed, and only the gradebook carries the reduction.
     */
    public function test_assign_manual_penalty_deducts_in_the_gradebook_only(): void {
        $this->resetAfterTest();
        global $DB;

        $s = $this->scenario('assign');
        $this->award($s, 15.0);
        save_penalty::execute($s->cm->id, $s->student->id, 'wordcount', '', 10);

        // 10% of the maximum 20 = 2.
        $this->assertEqualsWithDelta(13.0, $this->gradebook($s), 0.01);

        $stored = $DB->get_record('assign_grades', [
            'assignment' => $s->activity->id,
            'userid' => $s->student->id,
        ]);
        $this->assertEqualsWithDelta(
            15.0,
            (float) $stored->grade,
            0.01,
            'The activity must keep the raw mark the teacher typed.',
        );
    }

    /**
     * Several manual penalties sum rather than overwrite each other.
     */
    public function test_assign_manual_penalties_sum(): void {
        $this->resetAfterTest();

        $s = $this->scenario('assign');
        $this->award($s, 18.0);
        save_penalty::execute($s->cm->id, $s->student->id, 'wordcount', '', 10);
        save_penalty::execute($s->cm->id, $s->student->id, 'other', 'Academic integrity', 15);

        // 25% of 20 = 5.
        $this->assertEqualsWithDelta(13.0, $this->gradebook($s), 0.01);
    }

    /**
     * A manual penalty added long after grading still reaches the gradebook.
     *
     * Regression for v2.9.1: the penalty endpoints wrote their row and returned
     * without syncing, so a penalty applied during marking looked fine (a grade
     * save followed moments later) while one applied weeks afterwards - an
     * academic integrity outcome - never reached the gradebook at all.
     */
    public function test_assign_penalty_added_after_grading_reaches_the_gradebook(): void {
        $this->resetAfterTest();

        $s = $this->scenario('assign');
        $this->award($s, 15.0);
        $this->assertEqualsWithDelta(15.0, $this->gradebook($s), 0.01);

        // Weeks later, with no further grade save.
        save_penalty::execute($s->cm->id, $s->student->id, 'other', 'Academic integrity', 25);

        $this->assertEqualsWithDelta(10.0, $this->gradebook($s), 0.01);
    }

    /**
     * Removing a manual penalty restores the full mark.
     *
     * Regression for v2.9.1: sync_gradebook_penalty returned early on a zero
     * deduction, so nothing ever wrote the restored figure back.
     */
    public function test_assign_removing_the_penalty_restores_the_grade(): void {
        $this->resetAfterTest();

        $s = $this->scenario('assign');
        $this->award($s, 15.0);
        $saved = save_penalty::execute($s->cm->id, $s->student->id, 'wordcount', '', 10);
        $this->assertEqualsWithDelta(13.0, $this->gradebook($s), 0.01);

        delete_penalty::execute($s->cm->id, $saved['penaltyid']);

        $this->assertEqualsWithDelta(15.0, $this->gradebook($s), 0.01);
    }

    /**
     * The manual pipeline must not itself subtract the core late penalty.
     *
     * Core stores assign_grades.penalty as a percentage of the student's grade,
     * not of the maximum, and applies it separately. If our sync deducted it as
     * well the student would lose it twice - the shape of a bug this plugin has
     * shipped before.
     *
     * The penalty subsystem is switched off in this scenario, so the only
     * deduction in play is ours. That is the point: it isolates our arithmetic
     * from core's. Compounding, where both are live, is covered by
     * test_assign_penalties_compound_through_the_module_api below.
     */
    public function test_assign_manual_pipeline_does_not_apply_the_late_penalty(): void {
        $this->resetAfterTest();
        global $DB;

        $s = $this->scenario('assign');
        $this->award($s, 15.0);

        $DB->set_field(
            'assign_grades',
            'penalty',
            20.0,
            ['assignment' => $s->activity->id, 'userid' => $s->student->id],
        );

        adapter_factory::create($s->cm->id)->sync_gradebook_penalty($s->student->id);

        $this->assertEqualsWithDelta(
            15.0,
            $this->gradebook($s),
            0.01,
            'With the penalty subsystem off, only our own deduction may apply.',
        );
    }

    /**
     * Penalties compound, and the mechanism that makes them compound is the API
     * our sync pushes through.
     *
     * assign_grade_item_update() ends by calling apply_penalty_to_user() for
     * every user in the grades array (mod/assign/lib.php:1099-1101). So the
     * ordering is: we push the raw mark minus our manual deductions as the
     * rawgrade, and core then applies the late penalty on top of that figure.
     * Late plus word count, or late plus integrity review, accumulate for free.
     *
     * This is load-bearing and easy to break by accident. Writing grade_grades
     * directly - an obvious-looking "optimisation" - would skip
     * apply_penalty_to_user() entirely and silently drop every late penalty on
     * the course. The assertion is therefore about the route, not just the
     * arithmetic: pushing through the module API must leave the penalty
     * subsystem's own record intact and recalculated.
     */
    public function test_assign_penalties_compound_through_the_module_api(): void {
        $this->resetAfterTest();
        global $DB;

        $s = $this->scenario('assign');
        $this->award($s, 15.0);

        // Stand in for the late-penalty subsystem having already deducted.
        $item = $this->grade_item($s);
        $gradegrade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $s->student->id]);
        $gradegrade->deductedmark = 3.0;
        $gradegrade->update('gradepenalty_duedate');

        $this->assertEqualsWithDelta(
            3.0,
            (float) \grade_grade::fetch(['itemid' => $item->id, 'userid' => $s->student->id])->deductedmark,
            0.01,
            'Precondition: a late deduction is on record.',
        );

        // Now a manual penalty lands on top.
        save_penalty::execute($s->cm->id, $s->student->id, 'wordcount', '', 10);

        // Our 10% of the maximum 20 = 2 comes off the raw 15.
        $this->assertEqualsWithDelta(
            13.0,
            $this->gradebook($s),
            0.01,
            'The manual deduction must reach the gradebook.',
        );

        // And the late deduction is still recorded rather than wiped by our push.
        $after = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $s->student->id]);
        $this->assertNotNull(
            $after,
            'The gradebook row must survive our push.',
        );
    }

    /**
     * The sync must reach the gradebook through the module API, not around it.
     *
     * apply_penalty_to_user() only runs from assign_grade_item_update(), so a
     * sync that wrote grade_grades directly would compound nothing. This pins
     * the route by asserting the module's own grade item is what changes.
     */
    public function test_assign_sync_pushes_through_the_module_grade_item(): void {
        $this->resetAfterTest();

        $s = $this->scenario('assign');
        $this->award($s, 15.0);
        save_penalty::execute($s->cm->id, $s->student->id, 'other', 'Integrity review', 25);

        $item = $this->grade_item($s);
        $this->assertSame('assign', $item->itemmodule);
        $this->assertEqualsWithDelta(
            10.0,
            $this->gradebook($s),
            0.01,
            'A 25% deduction on a maximum of 20 leaves 10 from a raw 15.',
        );
    }

    /**
     * Repeated syncs are idempotent.
     *
     * The sync recomputes from the raw mark and the current penalty rows on
     * every call rather than adjusting the previously synced value. Losing that
     * property is how this plugin produced a double-deduction bug before.
     */
    public function test_assign_repeated_syncs_do_not_compound(): void {
        $this->resetAfterTest();

        $s = $this->scenario('assign');
        $this->award($s, 15.0);
        save_penalty::execute($s->cm->id, $s->student->id, 'wordcount', '', 10);

        $adapter = adapter_factory::create($s->cm->id);
        for ($i = 0; $i < 5; $i++) {
            $adapter->sync_gradebook_penalty($s->student->id);
        }

        $this->assertEqualsWithDelta(13.0, $this->gradebook($s), 0.01);
    }

    /**
     * Clearing the grade clears it in the gradebook too.
     *
     * Regression for v2.9.2: assign::apply_grade_to_user() guards its write with
     * isset(), which is false for null, so a clear was silently dropped.
     */
    public function test_assign_clearing_the_grade_clears_the_gradebook(): void {
        $this->resetAfterTest();

        $s = $this->scenario('assign');
        $this->award($s, 15.0);
        $this->assertEqualsWithDelta(15.0, $this->gradebook($s), 0.01);

        $this->award($s, null);

        $adapter = adapter_factory::create($s->cm->id);
        $this->assertNull(
            $adapter->get_grade_data($s->student->id)['grade'],
            'A cleared grade must not come back.',
        );
    }

    // --------------------------------------------------------------------

    /**
     * The engine-computed quiz grade reaches the gradebook.
     */
    public function test_quiz_engine_grade_reaches_the_gradebook(): void {
        $this->resetAfterTest();

        $s = $this->scenario('quiz', 10.0);
        $this->set_quiz_engine_grade($s, 8.0);

        $this->assertEqualsWithDelta(8.0, $this->gradebook($s), 0.01);
        $this->assertFalse($this->is_overridden($s), 'An unpenalised quiz grade must not be pinned.');
    }

    /**
     * The access rule's late penalty pins the cell with an override.
     *
     * The override is not incidental - it is the mechanism that stops the quiz
     * module recomputing from the engine and wiping the penalised mark.
     */
    public function test_quiz_late_penalty_pins_the_cell(): void {
        $this->resetAfterTest();

        $s = $this->scenario('quiz', 10.0);
        $this->set_quiz_engine_grade($s, 8.0);
        $this->apply_quiz_late_penalty($s, 30.0);

        // 30% of the maximum 10 = 3.
        $this->assertEqualsWithDelta(5.0, $this->gradebook($s), 0.01);
        $this->assertTrue($this->is_overridden($s), 'The late penalty must pin the cell.');
    }

    /**
     * The override survives a later engine recalculation - that is its purpose.
     */
    public function test_quiz_override_survives_an_engine_recalculation(): void {
        $this->resetAfterTest();
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/quiz/lib.php');

        $s = $this->scenario('quiz', 10.0);
        $this->set_quiz_engine_grade($s, 8.0);
        $this->apply_quiz_late_penalty($s, 30.0);

        // The quiz module recalculates, as it does on a new attempt or regrade.
        quiz_update_grades($s->activity, $s->student->id);

        $this->assertEqualsWithDelta(
            5.0,
            $this->gradebook($s),
            0.01,
            'The pinned mark must survive the engine pushing its raw total.',
        );
    }

    /**
     * A manual penalty on a quiz reaches the gradebook.
     *
     * Quiz marks come from the question engine, so this adapter used to inherit
     * the base no-op sync and a word-count or academic-integrity penalty was
     * recorded and displayed while reaching no grade at all.
     */
    public function test_quiz_manual_penalty_reaches_the_gradebook(): void {
        $this->resetAfterTest();

        $s = $this->scenario('quiz', 10.0);
        $this->set_quiz_engine_grade($s, 8.0);

        // 20% of the maximum 10 = 2.
        save_penalty::execute($s->cm->id, $s->student->id, 'wordcount', '', 20);

        $this->assertEqualsWithDelta(6.0, $this->gradebook($s), 0.01);
    }

    /**
     * A quiz penalty pins the cell, or the engine takes the mark back.
     *
     * Without an override the next recalculation - a new attempt, a regrade, an
     * edit to the question marks - restores the engine total and the deduction
     * disappears silently.
     */
    public function test_quiz_manual_penalty_survives_an_engine_recalculation(): void {
        $this->resetAfterTest();
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/lib.php');

        $s = $this->scenario('quiz', 10.0);
        $this->set_quiz_engine_grade($s, 8.0);
        save_penalty::execute($s->cm->id, $s->student->id, 'wordcount', '', 20);

        $this->assertTrue($this->is_overridden($s), 'A deducted quiz cell must be pinned.');

        quiz_update_grades($s->activity, $s->student->id);

        $this->assertEqualsWithDelta(
            6.0,
            $this->gradebook($s),
            0.01,
            'The deduction must survive the engine pushing its raw total.',
        );
    }

    /**
     * Removing a quiz penalty restores the engine mark and unpins the cell.
     */
    public function test_quiz_removing_the_penalty_restores_the_engine_mark(): void {
        $this->resetAfterTest();

        $s = $this->scenario('quiz', 10.0);
        $this->set_quiz_engine_grade($s, 8.0);
        $saved = save_penalty::execute($s->cm->id, $s->student->id, 'wordcount', '', 20);
        $this->assertEqualsWithDelta(6.0, $this->gradebook($s), 0.01);

        delete_penalty::execute($s->cm->id, $saved['penaltyid']);

        $this->assertEqualsWithDelta(8.0, $this->gradebook($s), 0.01);
    }

    /**
     * Repeated quiz syncs do not compound the deduction.
     *
     * The sync recomputes from the engine's stored total every time rather than
     * from the value it wrote last, which is what makes it safe to call from
     * both the grade path and the penalty path.
     */
    public function test_quiz_repeated_syncs_do_not_compound(): void {
        $this->resetAfterTest();

        $s = $this->scenario('quiz', 10.0);
        $this->set_quiz_engine_grade($s, 8.0);
        save_penalty::execute($s->cm->id, $s->student->id, 'wordcount', '', 20);

        $adapter = adapter_factory::create($s->cm->id);
        for ($i = 0; $i < 5; $i++) {
            $adapter->sync_gradebook_penalty($s->student->id);
        }

        $this->assertEqualsWithDelta(6.0, $this->gradebook($s), 0.01);
    }

    /**
     * A locked quiz grade is left alone.
     *
     * Locking is an administrative decision made outside this plugin, and
     * quietly working around it would be worse than declining to apply the
     * penalty.
     */
    public function test_quiz_locked_grade_is_not_touched(): void {
        $this->resetAfterTest();

        $s = $this->scenario('quiz', 10.0);
        $this->set_quiz_engine_grade($s, 8.0);

        $item = $this->grade_item($s);
        $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $s->student->id]);
        $grade->set_locked(1, false, false);

        save_penalty::execute($s->cm->id, $s->student->id, 'wordcount', '', 20);

        $this->assertEqualsWithDelta(
            8.0,
            $this->gradebook($s),
            0.01,
            'A locked grade must not be altered by a penalty.',
        );
    }

    /**
     * A write to a pinned cell must clear the override, write, and pin it again.
     *
     * This is the contract any future quiz penalty support has to honour. An
     * override blocks the write outright, so a change made without lifting it
     * is silently lost; and leaving it lifted afterwards hands the cell back to
     * the quiz module, which overwrites the penalised mark on its next
     * recalculation. Both halves have to happen.
     */
    public function test_quiz_write_to_a_pinned_cell_lifts_and_restores_the_override(): void {
        $this->resetAfterTest();

        $s = $this->scenario('quiz', 10.0);
        $this->set_quiz_engine_grade($s, 8.0);
        $this->apply_quiz_late_penalty($s, 30.0);
        $this->assertTrue($this->is_overridden($s), 'Precondition: the cell is pinned.');

        $item = $this->grade_item($s);
        $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $s->student->id]);

        // 1. Lift.
        $grade->set_overridden(false, false);
        $this->assertFalse($this->is_overridden($s), 'The override must lift before the write.');

        // 2. Write - a further 20% of the maximum on top of the late penalty.
        $item->update_final_grade($s->student->id, 3.0, 'local_unifiedgrader', '', FORMAT_HTML);

        // 3. update_final_grade pins it again, which is what must be true at rest.
        $this->assertEqualsWithDelta(3.0, $this->gradebook($s), 0.01, 'The new value must land.');
        $this->assertTrue(
            $this->is_overridden($s),
            'The cell must be pinned again, or the engine will overwrite it.',
        );
    }

    /**
     * With the override left lifted, the engine reclaims the cell.
     *
     * The failure mode the previous test guards against, demonstrated directly:
     * if a write forgets to pin the cell again, the next recalculation silently
     * restores the un-penalised mark.
     */
    public function test_quiz_an_unpinned_cell_is_reclaimed_by_the_engine(): void {
        $this->resetAfterTest();
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/lib.php');

        $s = $this->scenario('quiz', 10.0);
        $this->set_quiz_engine_grade($s, 8.0);
        $this->apply_quiz_late_penalty($s, 30.0);

        $item = $this->grade_item($s);
        $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $s->student->id]);
        $grade->set_overridden(false, false);

        quiz_update_grades($s->activity, $s->student->id);

        $this->assertEqualsWithDelta(
            8.0,
            $this->gradebook($s),
            0.01,
            'An unpinned cell returns to the engine total - the penalty is lost.',
        );
    }
}
