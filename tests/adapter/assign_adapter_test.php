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
 * Tests for the assign_adapter class.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\adapter\assign_adapter
 */
final class assign_adapter_test extends \advanced_testcase {

    /**
     * Helper: create a scenario and return the adapter.
     *
     * @param array $options Scenario options.
     * @return object{adapter: assign_adapter, scenario: \stdClass}
     */
    private function create_scenario(array $options = []): object {
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('assign', $options);
        $this->setUser($scenario->teacher);
        $adapter = adapter_factory::create($scenario->cm->id);
        return (object) ['adapter' => $adapter, 'scenario' => $scenario];
    }

    /**
     * Test get_activity_info returns correct basic info.
     */
    public function test_get_activity_info_basic(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $info = $s->adapter->get_activity_info();

        $this->assertEquals($s->scenario->cm->id, $info['id']);
        $this->assertEquals('assign', $info['type']);
        $this->assertArrayHasKey('name', $info);
        $this->assertArrayHasKey('duedate', $info);
        $this->assertArrayHasKey('cutoffdate', $info);
        $this->assertArrayHasKey('maxgrade', $info);
        $this->assertArrayHasKey('gradingmethod', $info);
        $this->assertArrayHasKey('teamsubmission', $info);
        $this->assertArrayHasKey('blindmarking', $info);
        $this->assertGreaterThan(0, $info['duedate']);
        $this->assertEquals('simple', $info['gradingmethod']);
    }

    /**
     * Test get_activity_info with blind marking.
     */
    public function test_get_activity_info_blind_marking(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario(['modparams' => ['blindmarking' => 1]]);
        $info = $s->adapter->get_activity_info();

        $this->assertTrue($info['blindmarking']);
    }

    /**
     * Test get_participants returns empty when no students enrolled.
     */
    public function test_get_participants_empty(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario(['studentcount' => 0]);
        $participants = $s->adapter->get_participants();

        $this->assertIsArray($participants);
        $this->assertEmpty($participants);
    }

    /**
     * Test get_participants returns students with correct submission status.
     */
    public function test_get_participants_with_submissions(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $this->create_scenario();

        // Submit for first student.
        $this->setUser($s->scenario->students[0]);
        $plugingen->create_assign_submission($s->scenario->activity, $s->scenario->students[0]->id);

        $this->setUser($s->scenario->teacher);
        $participants = $s->adapter->get_participants();

        $this->assertCount(3, $participants);

        // Find the submitted student.
        $submitted = array_values(array_filter($participants, fn($p) => $p['id'] == $s->scenario->students[0]->id));
        $this->assertCount(1, $submitted);
        $this->assertEquals('submitted', $submitted[0]['status']);

        // Others should have no submission.
        $notsubmitted = array_values(array_filter($participants, fn($p) => $p['id'] != $s->scenario->students[0]->id));
        foreach ($notsubmitted as $p) {
            $this->assertEquals('nosubmission', $p['status']);
        }
    }

    /**
     * Test get_participants status filter for submitted.
     */
    public function test_get_participants_filter_submitted(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $this->create_scenario();

        $this->setUser($s->scenario->students[0]);
        $plugingen->create_assign_submission($s->scenario->activity, $s->scenario->students[0]->id);

        $this->setUser($s->scenario->teacher);
        $participants = $s->adapter->get_participants(['status' => 'submitted']);

        $this->assertCount(1, $participants);
        $this->assertEquals($s->scenario->students[0]->id, $participants[0]['id']);
    }

    /**
     * Test get_participants status filter for notsubmitted.
     */
    public function test_get_participants_filter_notsubmitted(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $this->create_scenario();

        $this->setUser($s->scenario->students[0]);
        $plugingen->create_assign_submission($s->scenario->activity, $s->scenario->students[0]->id);

        $this->setUser($s->scenario->teacher);
        $participants = $s->adapter->get_participants(['status' => 'notsubmitted']);

        $this->assertCount(2, $participants);
    }

    /**
     * Test get_participants search filter.
     */
    public function test_get_participants_filter_search(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $plugingen = $gen->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('assign', ['studentcount' => 0]);

        // Create students with specific names.
        $alice = $gen->create_user(['firstname' => 'Alice', 'lastname' => 'Wonder']);
        $bob = $gen->create_user(['firstname' => 'Bob', 'lastname' => 'Builder']);
        $gen->enrol_user($alice->id, $scenario->course->id, 'student');
        $gen->enrol_user($bob->id, $scenario->course->id, 'student');

        $this->setUser($scenario->teacher);
        $adapter = adapter_factory::create($scenario->cm->id);

        $participants = $adapter->get_participants(['search' => 'alice']);
        $this->assertCount(1, $participants);
        $this->assertStringContainsString('Alice', $participants[0]['fullname']);
    }

    /**
     * Test get_participants default sort by fullname.
     */
    public function test_get_participants_sort_by_fullname(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $plugingen = $gen->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('assign', ['studentcount' => 0]);

        $charlie = $gen->create_user(['firstname' => 'Charlie', 'lastname' => 'Z']);
        $alice = $gen->create_user(['firstname' => 'Alice', 'lastname' => 'A']);
        $gen->enrol_user($charlie->id, $scenario->course->id, 'student');
        $gen->enrol_user($alice->id, $scenario->course->id, 'student');

        $this->setUser($scenario->teacher);
        $adapter = adapter_factory::create($scenario->cm->id);

        $participants = $adapter->get_participants(['sort' => 'fullname', 'sortdir' => 'asc']);
        $this->assertEquals($alice->id, $participants[0]['id']);
        $this->assertEquals($charlie->id, $participants[1]['id']);
    }

    /**
     * Test get_participants late detection.
     */
    public function test_get_participants_late_detection(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        // Due date is in the past.
        $s = $this->create_scenario(['modparams' => ['duedate' => time() - DAYSECS]]);

        // Submit after due date.
        $this->setUser($s->scenario->students[0]);
        $plugingen->create_assign_submission($s->scenario->activity, $s->scenario->students[0]->id);

        $this->setUser($s->scenario->teacher);
        $participants = $s->adapter->get_participants();

        $submitted = array_values(array_filter($participants, fn($p) => $p['id'] == $s->scenario->students[0]->id));
        $this->assertTrue($submitted[0]['islate']);
    }

    /**
     * Test get_submission_data with no submission.
     */
    public function test_get_submission_data_no_submission(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $data = $s->adapter->get_submission_data($s->scenario->students[0]->id);

        $this->assertEquals('nosubmission', $data['status']);
        $this->assertEquals($s->scenario->students[0]->id, $data['userid']);
        $this->assertEmpty($data['files']);
        $this->assertEquals(0, $data['timecreated']);
    }

    /**
     * Test get_submission_data with a submission.
     */
    public function test_get_submission_data_with_submission(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $this->create_scenario();

        $this->setUser($s->scenario->students[0]);
        $plugingen->create_assign_submission($s->scenario->activity, $s->scenario->students[0]->id, '<p>My answer</p>');

        $this->setUser($s->scenario->teacher);
        $data = $s->adapter->get_submission_data($s->scenario->students[0]->id);

        $this->assertEquals('submitted', $data['status']);
        $this->assertGreaterThan(0, $data['timemodified']);
        $this->assertEquals(0, $data['attemptnumber']);
    }

    /**
     * Test get_grade_data with no grade.
     */
    public function test_get_grade_data_no_grade(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $data = $s->adapter->get_grade_data($s->scenario->students[0]->id);

        $this->assertNull($data['grade']);
        $this->assertEquals(0, $data['timegraded']);
        $this->assertEquals(0, $data['grader']);
    }

    /**
     * Test save_grade and then read it back.
     */
    public function test_save_grade_simple(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $this->create_scenario();

        // Create a submission first.
        $this->setUser($s->scenario->students[0]);
        $plugingen->create_assign_submission($s->scenario->activity, $s->scenario->students[0]->id);

        $this->setUser($s->scenario->teacher);
        $result = $s->adapter->save_grade(
            $s->scenario->students[0]->id,
            85.0,
            '<p>Good work!</p>',
        );
        $this->assertTrue($result);

        // Read it back.
        $data = $s->adapter->get_grade_data($s->scenario->students[0]->id);
        $this->assertEquals(85.0, $data['grade']);
        $this->assertStringContainsString('Good work!', $data['feedback']);
        $this->assertGreaterThan(0, $data['timegraded']);
    }

    /**
     * Test is_grade_released with no grade returns false.
     */
    public function test_is_grade_released_no_grade(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $this->assertFalse($s->adapter->is_grade_released($s->scenario->students[0]->id));
    }

    /**
     * Test is_grade_released with hidden grade item returns false.
     */
    public function test_is_grade_released_hidden_item(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $this->create_scenario();

        // Create submission and grade.
        $this->setUser($s->scenario->students[0]);
        $plugingen->create_assign_submission($s->scenario->activity, $s->scenario->students[0]->id);
        $this->setUser($s->scenario->teacher);
        $s->adapter->save_grade($s->scenario->students[0]->id, 80.0, '');

        // Hide the grade item.
        $s->adapter->set_grades_posted(1);

        $this->assertFalse($s->adapter->is_grade_released($s->scenario->students[0]->id));
    }

    /**
     * Test is_grade_released with visible grade returns true.
     */
    public function test_is_grade_released_visible(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $this->create_scenario();

        // Create submission and grade.
        $this->setUser($s->scenario->students[0]);
        $plugingen->create_assign_submission($s->scenario->activity, $s->scenario->students[0]->id);
        $this->setUser($s->scenario->teacher);
        $s->adapter->save_grade($s->scenario->students[0]->id, 80.0, '');

        $this->assertTrue($s->adapter->is_grade_released($s->scenario->students[0]->id));
    }

    /**
     * Test get_effective_duedate returns global duedate when no override.
     */
    public function test_get_effective_duedate_global(): void {
        $this->resetAfterTest();

        $duedate = time() + DAYSECS * 7;
        $s = $this->create_scenario(['modparams' => ['duedate' => $duedate]]);

        $effective = $s->adapter->get_effective_duedate($s->scenario->students[0]->id);
        $this->assertEquals($duedate, $effective);
    }

    /**
     * Test get_effective_duedate returns override when set.
     */
    public function test_get_effective_duedate_override(): void {
        global $DB;
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $instance = $DB->get_record('assign', ['id' => $s->scenario->activity->id]);

        // Create user override.
        $overridedate = time() + DAYSECS * 14;
        $DB->insert_record('assign_overrides', (object) [
            'assignid' => $instance->id,
            'userid' => $s->scenario->students[0]->id,
            'duedate' => $overridedate,
        ]);

        $effective = $s->adapter->get_effective_duedate($s->scenario->students[0]->id);
        $this->assertEquals($overridedate, $effective);
    }

    /**
     * Test supports_feature for onlinetext.
     */
    public function test_supports_feature_onlinetext(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario(['modparams' => ['assignsubmission_onlinetext_enabled' => 1]]);
        $this->assertTrue($s->adapter->supports_feature('onlinetext'));
    }

    /**
     * Test perform_submission_action with invalid action throws.
     */
    public function test_perform_submission_action_invalid_throws(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();

        $this->expectException(\moodle_exception::class);
        $s->adapter->perform_submission_action($s->scenario->students[0]->id, 'nonexistent');
    }

    /**
     * Test get_user_override returns null when no override exists.
     */
    public function test_get_user_override_returns_null(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $this->assertNull($s->adapter->get_user_override($s->scenario->students[0]->id));
    }

    /**
     * Test get_user_override returns data when override exists.
     */
    public function test_get_user_override_returns_data(): void {
        global $DB;
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $instance = $DB->get_record('assign', ['id' => $s->scenario->activity->id]);

        $overridedate = time() + DAYSECS * 14;
        $DB->insert_record('assign_overrides', (object) [
            'assignid' => $instance->id,
            'userid' => $s->scenario->students[0]->id,
            'duedate' => $overridedate,
        ]);

        $override = $s->adapter->get_user_override($s->scenario->students[0]->id);
        $this->assertNotNull($override);
        $this->assertArrayHasKey('id', $override);
        $this->assertEquals($overridedate, $override['duedate']);
    }

    /**
     * Test are_grades_posted defaults to true (visible).
     */
    public function test_are_grades_posted_default(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $this->assertTrue($s->adapter->are_grades_posted());
    }

    /**
     * Test set_grades_posted and are_grades_posted.
     */
    public function test_set_grades_posted(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();

        // Hide grades.
        $s->adapter->set_grades_posted(1);
        $this->assertFalse($s->adapter->are_grades_posted());

        // Show grades.
        $s->adapter->set_grades_posted(0);
        $this->assertTrue($s->adapter->are_grades_posted());
    }

    /**
     * Test get_type returns 'assign'.
     */
    public function test_get_type(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $this->assertEquals('assign', $s->adapter->get_type());
    }

    /**
     * Test get_participants returns correct participant structure.
     */
    public function test_get_participants_structure(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario(['studentcount' => 1]);
        $participants = $s->adapter->get_participants();

        $this->assertCount(1, $participants);
        $p = $participants[0];

        $expectedkeys = ['id', 'fullname', 'email', 'profileimageurl', 'status',
            'submittedat', 'gradevalue', 'locked', 'hasoverride', 'hasextension', 'islate'];
        foreach ($expectedkeys as $key) {
            $this->assertArrayHasKey($key, $p, "Missing key: {$key}");
        }
    }
}
