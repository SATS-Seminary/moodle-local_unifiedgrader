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
 * Tests for activity-related web service external functions.
 *
 * Covers get_activity_info, get_participants, get_submission_data, and get_grade_data.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\external\get_activity_info
 * @covers \local_unifiedgrader\external\get_participants
 * @covers \local_unifiedgrader\external\get_submission_data
 * @covers \local_unifiedgrader\external\get_grade_data
 */
final class activity_webservices_test extends \advanced_testcase {

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

    // -------------------------------------------------------------------------
    // get_activity_info tests.
    // -------------------------------------------------------------------------

    /**
     * Test get_activity_info returns correct data for a teacher.
     */
    public function test_get_activity_info_happy_path(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $result = get_activity_info::execute($scenario->cm->id);

        $this->assertEquals($scenario->cm->id, $result['id']);
        $this->assertEquals('assign', $result['type']);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('duedate', $result);
        $this->assertArrayHasKey('maxgrade', $result);
        $this->assertArrayHasKey('gradingmethod', $result);
    }

    /**
     * Test get_activity_info throws when user lacks the grade capability.
     */
    public function test_get_activity_info_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $this->setUser($scenario->students[0]);

        $this->expectException(\required_capability_exception::class);
        get_activity_info::execute($scenario->cm->id);
    }

    /**
     * Test get_activity_info return value passes clean_returnvalue validation.
     */
    public function test_get_activity_info_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $result = get_activity_info::execute($scenario->cm->id);

        $cleaned = external_api::clean_returnvalue(
            get_activity_info::execute_returns(),
            $result,
        );

        $this->assertEquals($scenario->cm->id, $cleaned['id']);
        $this->assertIsString($cleaned['type']);
        $this->assertIsBool($cleaned['teamsubmission']);
        $this->assertIsBool($cleaned['blindmarking']);
    }

    /**
     * Test get_activity_info with blind marking enabled returns the correct flag.
     */
    public function test_get_activity_info_blind_marking(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario(['modparams' => ['blindmarking' => 1]]);
        $result = get_activity_info::execute($scenario->cm->id);

        $cleaned = external_api::clean_returnvalue(
            get_activity_info::execute_returns(),
            $result,
        );

        $this->assertTrue($cleaned['blindmarking']);
    }

    // -------------------------------------------------------------------------
    // get_participants tests.
    // -------------------------------------------------------------------------

    /**
     * Test get_participants returns enrolled students for a teacher.
     */
    public function test_get_participants_happy_path(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario(['studentcount' => 3]);
        $result = get_participants::execute($scenario->cm->id);

        $this->assertCount(3, $result);
        $this->assertArrayHasKey('id', $result[0]);
        $this->assertArrayHasKey('fullname', $result[0]);
        $this->assertArrayHasKey('status', $result[0]);
    }

    /**
     * Test get_participants throws when user lacks the grade capability.
     */
    public function test_get_participants_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $this->setUser($scenario->students[0]);

        $this->expectException(\required_capability_exception::class);
        get_participants::execute($scenario->cm->id);
    }

    /**
     * Test get_participants return value passes clean_returnvalue validation.
     */
    public function test_get_participants_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario(['studentcount' => 2]);
        $result = get_participants::execute($scenario->cm->id);

        $cleaned = external_api::clean_returnvalue(
            get_participants::execute_returns(),
            $result,
        );

        $this->assertCount(2, $cleaned);
        $this->assertIsInt($cleaned[0]['id']);
        $this->assertIsString($cleaned[0]['fullname']);
        $this->assertIsString($cleaned[0]['status']);
    }

    /**
     * Test get_participants returns empty array when no students are enrolled.
     */
    public function test_get_participants_empty_course(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario(['studentcount' => 0]);
        $result = get_participants::execute($scenario->cm->id);

        $cleaned = external_api::clean_returnvalue(
            get_participants::execute_returns(),
            $result,
        );

        $this->assertIsArray($cleaned);
        $this->assertEmpty($cleaned);
    }

    // -------------------------------------------------------------------------
    // get_submission_data tests.
    // -------------------------------------------------------------------------

    /**
     * Test get_submission_data returns correct data for a student with a submission.
     */
    public function test_get_submission_data_happy_path(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $this->create_scenario();

        // Create a submission as the student.
        $this->setUser($scenario->students[0]);
        $plugingen->create_assign_submission($scenario->activity, $scenario->students[0]->id, '<p>My work</p>');

        $this->setUser($scenario->teacher);
        $result = get_submission_data::execute($scenario->cm->id, $scenario->students[0]->id);

        $this->assertEquals($scenario->students[0]->id, $result['userid']);
        $this->assertEquals('submitted', $result['status']);
        $this->assertGreaterThan(0, $result['timemodified']);
    }

    /**
     * Test get_submission_data throws when user lacks the grade capability.
     */
    public function test_get_submission_data_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $this->setUser($scenario->students[0]);

        $this->expectException(\required_capability_exception::class);
        get_submission_data::execute($scenario->cm->id, $scenario->students[1]->id);
    }

    /**
     * Test get_submission_data return value passes clean_returnvalue validation.
     */
    public function test_get_submission_data_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $result = get_submission_data::execute($scenario->cm->id, $scenario->students[0]->id);

        $cleaned = external_api::clean_returnvalue(
            get_submission_data::execute_returns(),
            $result,
        );

        $this->assertIsInt($cleaned['userid']);
        $this->assertIsString($cleaned['status']);
        $this->assertIsArray($cleaned['files']);
        $this->assertIsInt($cleaned['timecreated']);
        $this->assertIsInt($cleaned['attemptnumber']);
    }

    /**
     * Test get_submission_data returns nosubmission status when student has not submitted.
     */
    public function test_get_submission_data_no_submission(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $result = get_submission_data::execute($scenario->cm->id, $scenario->students[0]->id);

        $this->assertEquals('nosubmission', $result['status']);
        $this->assertEmpty($result['files']);
        $this->assertEquals(0, $result['timecreated']);
    }

    // -------------------------------------------------------------------------
    // get_grade_data tests.
    // -------------------------------------------------------------------------

    /**
     * Test get_grade_data returns correct data after grading.
     */
    public function test_get_grade_data_happy_path(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $this->create_scenario();

        // Create submission and grade it.
        $this->setUser($scenario->students[0]);
        $plugingen->create_assign_submission($scenario->activity, $scenario->students[0]->id);

        $this->setUser($scenario->teacher);
        $adapter = \local_unifiedgrader\adapter\adapter_factory::create($scenario->cm->id);
        $adapter->save_grade($scenario->students[0]->id, 85.0, '<p>Well done!</p>');

        $result = get_grade_data::execute($scenario->cm->id, $scenario->students[0]->id);

        $this->assertEquals(85.0, $result['grade']);
        $this->assertStringContainsString('Well done!', $result['feedback']);
        $this->assertGreaterThan(0, $result['timegraded']);
    }

    /**
     * Test get_grade_data throws when user lacks the grade capability.
     */
    public function test_get_grade_data_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $this->setUser($scenario->students[0]);

        $this->expectException(\required_capability_exception::class);
        get_grade_data::execute($scenario->cm->id, $scenario->students[0]->id);
    }

    /**
     * Test get_grade_data return value passes clean_returnvalue validation.
     */
    public function test_get_grade_data_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $result = get_grade_data::execute($scenario->cm->id, $scenario->students[0]->id);

        $cleaned = external_api::clean_returnvalue(
            get_grade_data::execute_returns(),
            $result,
        );

        $this->assertIsString($cleaned['feedback']);
        $this->assertIsInt($cleaned['feedbackformat']);
        $this->assertIsInt($cleaned['timegraded']);
        $this->assertIsInt($cleaned['grader']);
    }

    /**
     * Test get_grade_data returns null grade when student has not been graded.
     */
    public function test_get_grade_data_no_grade(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $result = get_grade_data::execute($scenario->cm->id, $scenario->students[0]->id);

        $this->assertNull($result['grade']);
        $this->assertEquals(0, $result['timegraded']);
        $this->assertEquals(0, $result['grader']);
    }
}
