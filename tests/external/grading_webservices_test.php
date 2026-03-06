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

namespace local_unifiedgrader\external;

use core_external\external_api;

/**
 * Tests for grading-related web service external functions.
 *
 * Covers save_grade, set_grades_posted, and submission_action.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\external\save_grade
 * @covers \local_unifiedgrader\external\set_grades_posted
 * @covers \local_unifiedgrader\external\submission_action
 */
final class grading_webservices_test extends \advanced_testcase {

    /**
     * Helper: create a grading scenario and set the teacher as current user.
     *
     * @param array $options Options passed to create_grading_scenario.
     * @return \stdClass Scenario object with course, activity, cm, context, teacher, students.
     */
    private function create_scenario(array $options = []): \stdClass {
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('assign', $options);
        $this->setUser($scenario->teacher);
        return $scenario;
    }

    /**
     * Helper: create a scenario with a submitted student.
     *
     * @param array $options Options passed to create_grading_scenario.
     * @return \stdClass Scenario object (same as create_scenario).
     */
    private function create_scenario_with_submission(array $options = []): \stdClass {
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('assign', $options);

        // Create submission as the first student.
        $this->setUser($scenario->students[0]);
        $plugingen->create_assign_submission($scenario->activity, $scenario->students[0]->id, '<p>My answer</p>');

        $this->setUser($scenario->teacher);
        return $scenario;
    }

    // -------------------------------------------------------------------------
    // save_grade tests.
    // -------------------------------------------------------------------------

    /**
     * Test save_grade successfully saves a grade for a submitted student.
     */
    public function test_save_grade_happy_path(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario_with_submission();

        $result = save_grade::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            85.0,
            '<p>Great work!</p>',
        );

        $this->assertTrue($result['success']);

        // Verify grade was persisted.
        $gradedata = get_grade_data::execute($scenario->cm->id, $scenario->students[0]->id);
        $this->assertEquals(85.0, $gradedata['grade']);
        $this->assertStringContainsString('Great work!', $gradedata['feedback']);
    }

    /**
     * Test save_grade throws when user lacks the grade capability.
     */
    public function test_save_grade_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario_with_submission();
        $this->setUser($scenario->students[0]);

        $this->expectException(\required_capability_exception::class);
        save_grade::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            50.0,
            'feedback',
        );
    }

    /**
     * Test save_grade return value passes clean_returnvalue validation.
     */
    public function test_save_grade_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario_with_submission();

        $result = save_grade::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            75.0,
            '<p>Good effort</p>',
        );

        $cleaned = external_api::clean_returnvalue(
            save_grade::execute_returns(),
            $result,
        );

        $this->assertIsBool($cleaned['success']);
        $this->assertTrue($cleaned['success']);
    }

    /**
     * Test save_grade with grade of -1 saves as no grade (null).
     */
    public function test_save_grade_negative_one_means_no_grade(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario_with_submission();

        $result = save_grade::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            -1,
            '<p>Feedback only</p>',
        );

        $this->assertTrue($result['success']);

        $gradedata = get_grade_data::execute($scenario->cm->id, $scenario->students[0]->id);
        // Grade should be null when -1 is sent (no grade).
        $this->assertNull($gradedata['grade']);
    }

    // -------------------------------------------------------------------------
    // set_grades_posted tests.
    // -------------------------------------------------------------------------

    /**
     * Test set_grades_posted hides and unhides grades successfully.
     */
    public function test_set_grades_posted_happy_path(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        // Hide grades.
        $result = set_grades_posted::execute($scenario->cm->id, 1);
        $this->assertTrue($result['success']);
        $this->assertFalse($result['posted']);

        // Show grades.
        $result = set_grades_posted::execute($scenario->cm->id, 0);
        $this->assertTrue($result['success']);
        $this->assertTrue($result['posted']);
    }

    /**
     * Test set_grades_posted throws when user lacks the grade capability.
     */
    public function test_set_grades_posted_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $this->setUser($scenario->students[0]);

        $this->expectException(\required_capability_exception::class);
        set_grades_posted::execute($scenario->cm->id, 1);
    }

    /**
     * Test set_grades_posted return value passes clean_returnvalue validation.
     */
    public function test_set_grades_posted_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $result = set_grades_posted::execute($scenario->cm->id, 0);

        $cleaned = external_api::clean_returnvalue(
            set_grades_posted::execute_returns(),
            $result,
        );

        $this->assertIsBool($cleaned['success']);
        $this->assertIsBool($cleaned['posted']);
        $this->assertIsInt($cleaned['hidden']);
    }

    /**
     * Test set_grades_posted with a future timestamp hides until that date.
     */
    public function test_set_grades_posted_hidden_until_timestamp(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $futuredate = time() + DAYSECS * 7;
        $result = set_grades_posted::execute($scenario->cm->id, $futuredate);

        $cleaned = external_api::clean_returnvalue(
            set_grades_posted::execute_returns(),
            $result,
        );

        $this->assertTrue($cleaned['success']);
        // Grades are hidden until the future date, so posted should be false.
        $this->assertFalse($cleaned['posted']);
        $this->assertEquals($futuredate, $cleaned['hidden']);
    }

    /**
     * Test set_grades_posted is blocked for quizzes when the setting is off (default).
     */
    public function test_set_grades_posted_blocked_for_quiz_by_default(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('quiz');
        $this->setUser($scenario->teacher);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('quiz_post_grades_disabled', 'local_unifiedgrader'));
        set_grades_posted::execute($scenario->cm->id, 0);
    }

    /**
     * Test set_grades_posted updates quiz review options when the setting is enabled.
     */
    public function test_set_grades_posted_updates_quiz_review_options(): void {
        global $DB;

        $this->resetAfterTest();

        set_config('enable_quiz_post_grades', 1, 'local_unifiedgrader');

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        // Create quiz with marks hidden (reviewmarks has no LATER_WHILE_OPEN or AFTER_CLOSE bits).
        $scenario = $plugingen->create_grading_scenario('quiz', [
            'modparams' => [
                'reviewmarks' => 0x11000,    // DURING + IMMEDIATELY_AFTER only.
                'reviewmaxmarks' => 0x11000, // DURING + IMMEDIATELY_AFTER only.
            ],
        ]);
        $this->setUser($scenario->teacher);

        // Post grades.
        $result = set_grades_posted::execute($scenario->cm->id, 0);
        $this->assertTrue($result['success']);
        $this->assertTrue($result['posted']);

        // Verify review options updated.
        $quiz = $DB->get_record('quiz', ['id' => $scenario->activity->id]);
        $laterwhileopen = 0x00100;
        $afterclose     = 0x00010;

        $this->assertNotEquals(0, $quiz->reviewmarks & $laterwhileopen, 'LATER_WHILE_OPEN bit should be set on reviewmarks');
        $this->assertNotEquals(0, $quiz->reviewmarks & $afterclose, 'AFTER_CLOSE bit should be set on reviewmarks');
        $this->assertNotEquals(0, $quiz->reviewmaxmarks & $laterwhileopen, 'LATER_WHILE_OPEN bit should be set on reviewmaxmarks');
        $this->assertNotEquals(0, $quiz->reviewmaxmarks & $afterclose, 'AFTER_CLOSE bit should be set on reviewmaxmarks');
        // DURING and IMMEDIATELY_AFTER bits should be preserved.
        $this->assertNotEquals(0, $quiz->reviewmarks & 0x10000, 'DURING bit should be preserved');
        $this->assertNotEquals(0, $quiz->reviewmarks & 0x01000, 'IMMEDIATELY_AFTER bit should be preserved');
    }

    /**
     * Test set_grades_posted unpost clears review option bits for quizzes.
     */
    public function test_set_grades_posted_unpost_clears_quiz_review_options(): void {
        global $DB;

        $this->resetAfterTest();

        set_config('enable_quiz_post_grades', 1, 'local_unifiedgrader');

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        // Create quiz with marks visible in all time periods.
        $scenario = $plugingen->create_grading_scenario('quiz', [
            'modparams' => [
                'reviewmarks' => 0x11110,    // All time periods.
                'reviewmaxmarks' => 0x11110, // All time periods.
            ],
        ]);
        $this->setUser($scenario->teacher);

        // Unpost grades.
        $result = set_grades_posted::execute($scenario->cm->id, 1);
        $this->assertTrue($result['success']);
        $this->assertFalse($result['posted']);

        // Verify LATER_WHILE_OPEN and AFTER_CLOSE bits are cleared.
        $quiz = $DB->get_record('quiz', ['id' => $scenario->activity->id]);
        $laterwhileopen = 0x00100;
        $afterclose     = 0x00010;

        $this->assertEquals(0, $quiz->reviewmarks & $laterwhileopen, 'LATER_WHILE_OPEN bit should be cleared');
        $this->assertEquals(0, $quiz->reviewmarks & $afterclose, 'AFTER_CLOSE bit should be cleared');
        // DURING and IMMEDIATELY_AFTER bits should be preserved.
        $this->assertNotEquals(0, $quiz->reviewmarks & 0x10000, 'DURING bit should be preserved');
        $this->assertNotEquals(0, $quiz->reviewmarks & 0x01000, 'IMMEDIATELY_AFTER bit should be preserved');
    }

    /**
     * Test set_grades_posted rejects scheduling for quizzes.
     */
    public function test_set_grades_posted_quiz_rejects_schedule(): void {
        $this->resetAfterTest();

        set_config('enable_quiz_post_grades', 1, 'local_unifiedgrader');

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('quiz');
        $this->setUser($scenario->teacher);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('quiz_post_grades_no_schedule', 'local_unifiedgrader'));
        set_grades_posted::execute($scenario->cm->id, time() + DAYSECS * 7);
    }

    // -------------------------------------------------------------------------
    // submission_action tests.
    // -------------------------------------------------------------------------

    /**
     * Test submission_action locks a student's submission successfully.
     */
    public function test_submission_action_happy_path(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario_with_submission();

        $result = submission_action::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'lock',
        );

        $this->assertTrue($result['success']);
    }

    /**
     * Test submission_action throws when user lacks the grade capability.
     */
    public function test_submission_action_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario_with_submission();
        $this->setUser($scenario->students[0]);

        $this->expectException(\required_capability_exception::class);
        submission_action::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'lock',
        );
    }

    /**
     * Test submission_action return value passes clean_returnvalue validation.
     */
    public function test_submission_action_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario_with_submission();

        $result = submission_action::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'lock',
        );

        $cleaned = external_api::clean_returnvalue(
            submission_action::execute_returns(),
            $result,
        );

        $this->assertIsBool($cleaned['success']);
        $this->assertTrue($cleaned['success']);
    }

    /**
     * Test submission_action throws for an invalid action name.
     */
    public function test_submission_action_invalid_action(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario_with_submission();

        $this->expectException(\moodle_exception::class);
        submission_action::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'nonexistent_action',
        );
    }
}
