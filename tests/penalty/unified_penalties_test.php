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
use local_unifiedgrader\penalty\activity_settings;
use local_unifiedgrader\penalty\compat;
use local_unifiedgrader\penalty\gradebook_writer;
use local_unifiedgrader\penalty\service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * Unified late penalties on Moodle 5.3+.
 *
 * Every test here uses one site rule set: up to a day late costs 10%, anything
 * later 20%.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_unifiedgrader\penalty\service
 * @covers     \local_unifiedgrader\penalty\gradebook_writer
 * @covers     \local_unifiedgrader\penalty\activity_settings
 * @covers     \local_unifiedgrader\adapter\quiz_adapter
 */
final class unified_penalties_test extends \advanced_testcase {
    /** @var int A due date five days ago, which every scenario uses. */
    private int $duedate;

    protected function setUp(): void {
        global $DB;
        parent::setUp();

        compat::reset_cache();
        if (!compat::unified()) {
            $this->markTestSkipped('Unified late penalties need Moodle 5.3 or later.');
        }
        $this->resetAfterTest();

        $this->duedate = time() - 5 * DAYSECS;

        $systemid = \context_system::instance()->id;
        foreach ([[DAYSECS, 10], [2 * DAYSECS, 20]] as $i => [$overdueby, $penalty]) {
            $DB->insert_record('gradepenalty_duedate_rule', (object) [
                'contextid' => $systemid,
                'sortorder' => $i,
                'overdueby' => $overdueby,
                'penalty' => $penalty,
                'usermodified' => 0,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }
    }

    // Switch defaults.

    /**
     * Each activity type starts from the behaviour it had before.
     */
    public function test_switch_defaults(): void {
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');

        $assignon = $gen->create_grading_scenario('assign', ['modparams' => ['gradepenalty' => 1]]);
        $assignoff = $gen->create_grading_scenario('assign', ['modparams' => ['gradepenalty' => 0]]);
        $forum = $gen->create_grading_scenario('forum');
        $quiz = $gen->create_grading_scenario('quiz');

        $this->assertTrue(activity_settings::is_enabled($assignon->cm));
        $this->assertFalse(activity_settings::is_enabled($assignoff->cm));
        $this->assertTrue(activity_settings::is_enabled($forum->cm));
        $this->assertFalse(activity_settings::is_enabled($quiz->cm));

        activity_settings::set_enabled((int) $forum->cm->id, false);
        activity_settings::set_enabled((int) $quiz->cm->id, true);
        $this->assertFalse(activity_settings::is_enabled($forum->cm));
        $this->assertTrue(activity_settings::is_enabled($quiz->cm));
    }

    // Gradebook writer.

    /**
     * The deduction sits beside the raw grade, and writing it twice changes nothing.
     *
     * The deductions come from real penalty rows: a write the rows do not back
     * would be corrected by the user_graded observer straight away.
     */
    public function test_writer_records_deduction_beside_raw_grade(): void {
        [$item, $userid, $cmid] = $this->graded_assign_cell(70);
        $penaltyid = penalty_manager::save_penalty($cmid, $userid, 2, 'other', 'Test', 20);

        service::resync($cmid, $userid, false);
        $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
        $this->assertEqualsWithDelta(70, $grade->rawgrade, 0.001);
        $this->assertEqualsWithDelta(50, $grade->finalgrade, 0.001);
        $this->assertEqualsWithDelta(20, $grade->deductedmark, 0.001);

        $this->assertSame(gradebook_writer::UNCHANGED, gradebook_writer::apply($item, $userid, 20));

        penalty_manager::delete_penalty($penaltyid);
        service::resync($cmid, $userid, false);
        $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
        $this->assertEqualsWithDelta(70, $grade->finalgrade, 0.001);
        $this->assertEqualsWithDelta(0, $grade->deductedmark, 0.001);
    }

    /**
     * A mark a teacher typed into the gradebook is left alone.
     */
    public function test_writer_leaves_teacher_override(): void {
        [$item, $userid] = $this->graded_assign_cell(70);
        $item->update_final_grade($userid, 65, 'gradebook');

        $this->assertSame(gradebook_writer::SKIPPED, gradebook_writer::apply($item, $userid, 20));
        $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
        $this->assertEqualsWithDelta(65, $grade->finalgrade, 0.001);
    }

    /**
     * A cell pinned by the old quiz late penalty rule is released.
     */
    public function test_writer_releases_old_penalty_pin(): void {
        [$item, $userid, $cmid] = $this->graded_assign_cell(70);
        $item->update_final_grade($userid, 40, 'quizaccess_duedate');
        penalty_manager::save_penalty($cmid, $userid, 2, 'other', 'Test', 10);

        service::resync($cmid, $userid, false);
        $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
        $this->assertEmpty($grade->overridden);
        $this->assertEqualsWithDelta(60, $grade->finalgrade, 0.001);
    }

    /**
     * A restored pin is recognised by the access rule's feedback, and that feedback is cleared.
     *
     * A course backup and restore rewrites the grade history, so the only trace
     * of quizaccess_duedate left on the cell is the text it wrote with the pin.
     */
    public function test_writer_releases_restored_pin_by_its_feedback(): void {
        [$item, $userid, $cmid] = $this->graded_assign_cell(70);
        $item->update_final_grade($userid, 59.5, 'restore', 'Late penalty of 15% applied.', FORMAT_HTML);
        // The activity keeps pushing its raw grade under the override.
        $item->update_raw_grade($userid, 70, 'mod/quiz');

        service::resync($cmid, $userid, false);
        $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
        $this->assertEmpty($grade->overridden);
        $this->assertEqualsWithDelta(70, $grade->finalgrade, 0.001);
        $this->assertEmpty($grade->feedback);
    }

    /**
     * The entry that set the override decides, not the activity's later pushes.
     */
    public function test_writer_finds_who_set_the_override(): void {
        [$item, $userid, $cmid] = $this->graded_assign_cell(70);
        $item->update_final_grade($userid, 40, 'quizaccess_duedate', 'Penalised.', FORMAT_HTML);
        $item->update_raw_grade($userid, 70, 'mod/quiz');
        $item->update_raw_grade($userid, 72, 'mod/quiz');

        service::resync($cmid, $userid, false);
        $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
        $this->assertEmpty($grade->overridden);
        $this->assertEqualsWithDelta(72, $grade->finalgrade, 0.001);
        // Feedback the rule did not write is the marker's, and stays.
        $this->assertSame('Penalised.', $grade->feedback);
    }

    /**
     * A teacher's override stays, however many raw pushes came after it.
     */
    public function test_writer_keeps_teacher_override_under_later_pushes(): void {
        [$item, $userid, $cmid] = $this->graded_assign_cell(70);
        $item->update_final_grade($userid, 65, 'gradebook', 'Moderated.', FORMAT_HTML);
        $item->update_raw_grade($userid, 71, 'mod/quiz');

        service::resync($cmid, $userid, false);
        $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
        $this->assertNotEmpty($grade->overridden);
        $this->assertEqualsWithDelta(65, $grade->finalgrade, 0.001);
    }

    /**
     * Only the access rule's exact wording counts as its feedback.
     */
    public function test_legacy_penalty_feedback_detection(): void {
        $this->assertTrue(gradebook_writer::is_legacy_penalty_feedback('Late penalty of 15% applied.'));
        $this->assertTrue(gradebook_writer::is_legacy_penalty_feedback('<p>Late penalty of 7.5% applied.</p>'));
        $this->assertFalse(gradebook_writer::is_legacy_penalty_feedback('Late penalty of 15% applied. Good essay otherwise.'));
        $this->assertFalse(gradebook_writer::is_legacy_penalty_feedback(''));
    }

    // Quiz: the first genuine attempt decides.

    /**
     * An empty attempt made on time does not count; the first real one, late, does.
     */
    public function test_quiz_empty_on_time_attempt_is_ignored(): void {
        $s = $this->quiz_scenario();
        $userid = (int) $s->students[0]->id;

        $this->submit_quiz_attempt($s, $userid, [], $this->duedate - HOURSECS);
        $this->submit_quiz_attempt($s, $userid, [1 => 'True', 2 => 'True'], $this->duedate + DAYSECS + HOURSECS);

        $this->assertSame(20, $this->late_pct($s, $userid));
        $this->assertEqualsWithDelta(80, $this->final_grade($s, $userid), 0.01);
    }

    /**
     * A real attempt made on time leaves later attempts free of penalty.
     */
    public function test_quiz_genuine_on_time_attempt_frees_later_attempts(): void {
        $s = $this->quiz_scenario();
        $userid = (int) $s->students[0]->id;

        $this->submit_quiz_attempt($s, $userid, [1 => 'False', 2 => 'True'], $this->duedate - HOURSECS);
        $this->submit_quiz_attempt($s, $userid, [1 => 'True', 2 => 'True'], $this->duedate + 3 * DAYSECS);

        $this->assertNull($this->late_pct($s, $userid));
        $this->assertEqualsWithDelta(100, $this->final_grade($s, $userid), 0.01);
    }

    /**
     * A late first genuine attempt pegs the penalty: a later full mark is capped.
     */
    public function test_quiz_late_first_attempt_caps_later_attempts(): void {
        $s = $this->quiz_scenario();
        $userid = (int) $s->students[0]->id;

        // Half a day late: 10%. The second attempt is three days late, which on
        // its own would be 20%, but it does not decide the penalty.
        $this->submit_quiz_attempt($s, $userid, [1 => 'False', 2 => 'True'], $this->duedate + DAYSECS / 2);
        $this->submit_quiz_attempt($s, $userid, [1 => 'True', 2 => 'True'], $this->duedate + 3 * DAYSECS);

        $this->assertSame(10, $this->late_pct($s, $userid));
        $this->assertEqualsWithDelta(90, $this->final_grade($s, $userid), 0.01);
    }

    /**
     * Exactly the required share answered counts as a genuine attempt.
     */
    public function test_quiz_genuine_threshold_is_inclusive(): void {
        $s = $this->quiz_scenario();
        $userid = (int) $s->students[0]->id;
        set_config('quizgenuineattemptpct', 50, 'local_unifiedgrader');

        // One of two answered, on time: genuine, so no penalty later.
        $this->submit_quiz_attempt($s, $userid, [1 => 'True'], $this->duedate - HOURSECS);
        $this->submit_quiz_attempt($s, $userid, [1 => 'True', 2 => 'True'], $this->duedate + 3 * DAYSECS);

        $this->assertNull($this->late_pct($s, $userid));
    }

    /**
     * A quiz with its switch off is never penalised.
     */
    public function test_quiz_switch_off_means_no_penalty(): void {
        $s = $this->quiz_scenario(['enabled' => false]);
        $userid = (int) $s->students[0]->id;

        $this->submit_quiz_attempt($s, $userid, [1 => 'True', 2 => 'True'], $this->duedate + 3 * DAYSECS);

        $this->assertNull($this->late_pct($s, $userid));
        $this->assertEqualsWithDelta(100, $this->final_grade($s, $userid), 0.01);
    }

    /**
     * A quiz without a due date is never late; the close time is not a due date.
     */
    public function test_quiz_without_due_date_is_never_late(): void {
        $s = $this->quiz_scenario(['duedate' => 0]);
        $userid = (int) $s->students[0]->id;

        $this->submit_quiz_attempt($s, $userid, [1 => 'True', 2 => 'True'], time());

        $this->assertNull($this->late_pct($s, $userid));
    }

    /**
     * An extension covering the first genuine attempt removes the penalty.
     */
    public function test_quiz_extension_removes_penalty(): void {
        $s = $this->quiz_scenario();
        $userid = (int) $s->students[0]->id;
        $finish = $this->duedate + 3 * DAYSECS;
        $this->submit_quiz_attempt($s, $userid, [1 => 'True', 2 => 'True'], $finish);
        $this->assertSame(20, $this->late_pct($s, $userid));

        $this->setAdminUser();
        $adapter = adapter_factory::create((int) $s->cm->id);
        $adapter->save_duedate_extension($userid, $finish + HOURSECS);

        $this->assertSame($finish + HOURSECS, $adapter->get_duedate_extension($userid)['duedate']);
        $this->assertSame($finish + HOURSECS, $adapter->get_effective_duedate($userid));
        $this->assertNull($this->late_pct($s, $userid));
        $this->assertEqualsWithDelta(100, $this->final_grade($s, $userid), 0.01);

        // Removing it puts the penalty back, and removes the override it left empty.
        $adapter->delete_duedate_extension($userid);
        $this->assertSame(20, $this->late_pct($s, $userid));
    }

    // Assignments.

    /**
     * A late assignment graded on core's own page is penalised by Unified Grader.
     */
    public function test_assign_graded_outside_grader_is_penalised(): void {
        $s = $this->assign_scenario();
        $userid = (int) $s->students[0]->id;

        $this->grade_assign_natively($s, $userid, 80);

        // Submitted five days late: 20% of 100, beside the mark as given.
        $this->assertSame(20, $this->late_pct($s, $userid));
        $this->assertEqualsWithDelta(60, $this->final_grade($s, $userid), 0.01);
        $item = \grade_item::fetch([
            'itemtype' => 'mod', 'itemmodule' => 'assign', 'iteminstance' => $s->activity->id, 'itemnumber' => 0,
        ]);
        $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
        $this->assertEqualsWithDelta(80, $grade->rawgrade, 0.01);
        $this->assertEqualsWithDelta(20, $grade->deductedmark, 0.01);
    }

    /**
     * While core still penalises assignments, Unified Grader stays out of the way.
     */
    public function test_assign_left_to_core_while_core_penalties_are_on(): void {
        set_config('gradepenalty_enabledmodules', 'assign');
        $s = $this->assign_scenario();
        $userid = (int) $s->students[0]->id;

        $this->grade_assign_natively($s, $userid, 80);

        $this->assertNull($this->late_pct($s, $userid));
        $this->assertNull(adapter_factory::create((int) $s->cm->id)->calculate_late_penalty($userid));
    }

    // Forums.

    /**
     * A forum with its switch off has no late penalty.
     */
    public function test_forum_switch_off_means_no_penalty(): void {
        // Creating a forum with a due date writes a calendar event.
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $gen->create_grading_scenario('forum', ['modparams' => ['duedate' => $this->duedate]]);
        $userid = (int) $s->students[0]->id;
        $gen->create_forum_post($s->activity, $userid);

        $adapter = adapter_factory::create((int) $s->cm->id);
        $this->assertSame(20, $adapter->calculate_late_penalty($userid)['percentage']);

        activity_settings::set_enabled((int) $s->cm->id, false);
        $this->assertNull($adapter->calculate_late_penalty($userid));
    }

    // Helpers.

    /**
     * An assignment gradebook cell holding a raw grade.
     *
     * @param float $raw The raw grade.
     * @return array [grade_item, userid, cmid]
     */
    private function graded_assign_cell(float $raw): array {
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $gen->create_grading_scenario('assign', ['modparams' => ['grade' => 100]]);
        $userid = (int) $s->students[0]->id;
        $item = \grade_item::fetch([
            'itemtype' => 'mod', 'itemmodule' => 'assign', 'iteminstance' => $s->activity->id, 'itemnumber' => 0,
        ]);
        $item->update_raw_grade($userid, $raw);
        return [$item, $userid, (int) $s->cm->id];
    }

    /**
     * A quiz with two true/false questions, a due date five days ago, and unlimited attempts.
     *
     * @param array $options 'enabled' (penalty switch, default true), 'duedate' (default five days ago).
     * @return \stdClass The grading scenario.
     */
    private function quiz_scenario(array $options = []): \stdClass {
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $gen->create_grading_scenario('quiz', ['modparams' => [
            'duedate' => $options['duedate'] ?? $this->duedate,
            'timeclose' => 0,
            'attempts' => 0,
            'grademethod' => QUIZ_GRADEHIGHEST,
        ]]);

        $questiongen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongen->create_question_category();
        foreach ([1, 2] as $unused) {
            $question = $questiongen->create_question('truefalse', null, ['category' => $category->id]);
            quiz_add_quiz_question($question->id, $s->activity);
        }
        \mod_quiz\quiz_settings::create($s->activity->id)->get_grade_calculator()->recompute_quiz_sumgrades();

        activity_settings::set_enabled((int) $s->cm->id, $options['enabled'] ?? true);
        return $s;
    }

    /**
     * Start, answer and submit a quiz attempt as the student, as core does.
     *
     * @param \stdClass $s The quiz scenario.
     * @param int $userid The student.
     * @param array $responses slot => 'True' / 'False'; missing slots are left unanswered.
     * @param int $timefinish When the attempt was submitted.
     */
    private function submit_quiz_attempt(\stdClass $s, int $userid, array $responses, int $timefinish): void {
        $quizgen = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $this->setUser($userid);
        $attempt = $quizgen->create_attempt($s->activity->id, $userid);
        $quizgen->submit_responses($attempt->id, $responses, false, true, $timefinish);
    }

    /**
     * An assignment due five days ago, with a (late) submission from the first student.
     *
     * @return \stdClass The grading scenario.
     */
    private function assign_scenario(): \stdClass {
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $gen->create_grading_scenario('assign', ['modparams' => [
            'duedate' => $this->duedate,
            'gradepenalty' => 1,
            'grade' => 100,
        ]]);
        $gen->create_assign_submission($s->activity, (int) $s->students[0]->id);
        return $s;
    }

    /**
     * Grade an assignment through mod_assign's own API, as its grading page does.
     *
     * @param \stdClass $s The assignment scenario.
     * @param int $userid The student.
     * @param float $mark The mark.
     */
    private function grade_assign_natively(\stdClass $s, int $userid, float $mark): void {
        $this->setAdminUser();
        $assign = new \assign($s->context, $s->cm, $s->course);
        $grade = $assign->get_user_grade($userid, true);
        $grade->grade = $mark;
        $assign->update_grade($grade);
    }

    /**
     * The student's late penalty percentage, or null.
     *
     * @param \stdClass $s The scenario.
     * @param int $userid The student.
     * @return int|null
     */
    private function late_pct(\stdClass $s, int $userid): ?int {
        foreach (penalty_manager::get_penalties((int) $s->cm->id, $userid) as $penalty) {
            if ($penalty['category'] === 'late') {
                return $penalty['percentage'];
            }
        }
        return null;
    }

    /**
     * The student's final grade in the gradebook.
     *
     * @param \stdClass $s The scenario.
     * @param int $userid The student.
     * @return float|null
     */
    private function final_grade(\stdClass $s, int $userid): ?float {
        $item = \grade_item::fetch([
            'itemtype' => 'mod', 'itemmodule' => $s->cm->modname, 'iteminstance' => $s->activity->id, 'itemnumber' => 0,
        ]);
        $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
        return ($grade && $grade->finalgrade !== null) ? (float) $grade->finalgrade : null;
    }
}
