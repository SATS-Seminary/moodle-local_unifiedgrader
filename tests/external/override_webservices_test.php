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
 * Tests for override web services.
 *
 * Tests delete_user_override and delete_duedate_extension.
 * delete_user_override requires local/unifiedgrader:grade + mod/assign:manageoverrides
 * (or mod/quiz:manageoverrides for quiz activities).
 * delete_duedate_extension requires local/unifiedgrader:grade and the quizaccess_duedate plugin.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\external\delete_user_override
 * @covers \local_unifiedgrader\external\delete_duedate_extension
 */
final class override_webservices_test extends \advanced_testcase {
    // Delete_user_override tests (assignment).

    /**
     * Test delete_user_override removes an assignment override.
     */
    public function test_delete_user_override_assign_happy_path(): void {
        global $DB;
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('assign');
        $this->setUser($scenario->teacher);

        $student = $scenario->students[0];
        $instance = $DB->get_record('assign', ['id' => $scenario->activity->id]);

        // Create a user override.
        $overridedate = time() + DAYSECS * 14;
        $overrideid = $DB->insert_record('assign_overrides', (object) [
            'assignid' => $instance->id,
            'userid' => $student->id,
            'duedate' => $overridedate,
        ]);

        // Verify the override exists.
        $this->assertTrue($DB->record_exists('assign_overrides', ['id' => $overrideid]));

        $result = delete_user_override::execute($scenario->cm->id, $student->id);

        $this->assertTrue($result['success']);
        $this->assertFalse($DB->record_exists('assign_overrides', ['id' => $overrideid]));
    }

    /**
     * Test delete_user_override return value passes validation.
     */
    public function test_delete_user_override_return_validation(): void {
        global $DB;
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('assign');
        $this->setUser($scenario->teacher);

        $student = $scenario->students[0];
        $instance = $DB->get_record('assign', ['id' => $scenario->activity->id]);

        $DB->insert_record('assign_overrides', (object) [
            'assignid' => $instance->id,
            'userid' => $student->id,
            'duedate' => time() + DAYSECS * 14,
        ]);

        $result = delete_user_override::execute($scenario->cm->id, $student->id);
        $cleaned = external_api::clean_returnvalue(delete_user_override::execute_returns(), $result);

        $this->assertArrayHasKey('success', $cleaned);
        $this->assertTrue($cleaned['success']);
    }

    /**
     * Test delete_user_override without grade capability throws exception.
     */
    public function test_delete_user_override_no_grade_capability(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('assign');

        // Login as a student who lacks the grade capability.
        $this->setUser($scenario->students[0]);

        $this->expectException(\required_capability_exception::class);
        delete_user_override::execute($scenario->cm->id, $scenario->students[1]->id);
    }

    /**
     * Test delete_user_override without manageoverrides capability throws exception.
     */
    public function test_delete_user_override_no_manage_overrides_capability(): void {
        global $DB;
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('assign');

        // Create a non-editing teacher with grade capability but without manageoverrides.
        $gen = $this->getDataGenerator();
        $teacher2 = $gen->create_user(['username' => 'teacher2']);
        $gen->enrol_user($teacher2->id, $scenario->course->id, 'teacher');

        // Grant the grade capability but not manageoverrides.
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        assign_capability('local/unifiedgrader:grade', CAP_ALLOW, $roleid, $scenario->context->id);

        $this->setUser($teacher2);

        $this->expectException(\required_capability_exception::class);
        delete_user_override::execute($scenario->cm->id, $scenario->students[0]->id);
    }

    /**
     * Test delete_user_override with no existing override still succeeds.
     */
    public function test_delete_user_override_no_override_succeeds(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('assign');
        $this->setUser($scenario->teacher);

        // No override exists for this student.
        $result = delete_user_override::execute($scenario->cm->id, $scenario->students[0]->id);

        $this->assertTrue($result['success']);
    }

    /**
     * Test delete_user_override only deletes the specified user's override.
     */
    public function test_delete_user_override_user_isolation(): void {
        global $DB;
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('assign');
        $this->setUser($scenario->teacher);

        $instance = $DB->get_record('assign', ['id' => $scenario->activity->id]);

        // Create overrides for two students.
        $override1 = $DB->insert_record('assign_overrides', (object) [
            'assignid' => $instance->id,
            'userid' => $scenario->students[0]->id,
            'duedate' => time() + DAYSECS * 14,
        ]);
        $override2 = $DB->insert_record('assign_overrides', (object) [
            'assignid' => $instance->id,
            'userid' => $scenario->students[1]->id,
            'duedate' => time() + DAYSECS * 21,
        ]);

        // Delete only student 0's override.
        delete_user_override::execute($scenario->cm->id, $scenario->students[0]->id);

        $this->assertFalse($DB->record_exists('assign_overrides', ['id' => $override1]));
        $this->assertTrue($DB->record_exists('assign_overrides', ['id' => $override2]));
    }

    // Delete_duedate_extension tests.

    /**
     * Test delete_duedate_extension is skipped when the quizaccess_duedate plugin is not installed.
     */
    public function test_delete_duedate_extension_skip_if_plugin_missing(): void {
        if (!class_exists('\quizaccess_duedate\override_manager')) {
            $this->markTestSkipped('quizaccess_duedate plugin is not installed.');
        }

        // If we get here, the plugin exists. The test below will execute.
        $this->assertTrue(true);
    }

    /**
     * Test delete_duedate_extension happy path with quiz activity.
     */
    public function test_delete_duedate_extension_happy_path(): void {
        if (!class_exists('\quizaccess_duedate\override_manager')) {
            $this->markTestSkipped('quizaccess_duedate plugin is not installed.');
        }

        global $DB;
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('quiz');
        $this->setUser($scenario->teacher);

        $student = $scenario->students[0];

        // Create a duedate extension via the override manager.
        \quizaccess_duedate\override_manager::save_override((object) [
            'quizid' => $scenario->activity->id,
            'userid' => $student->id,
            'duedate' => time() + DAYSECS * 14,
        ]);

        // Grant the required capability.
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('quizaccess/duedate:manageoverrides', CAP_ALLOW, $roleid, $scenario->context->id);

        $result = delete_duedate_extension::execute($scenario->cm->id, $student->id);

        $this->assertTrue($result['success']);
    }

    /**
     * Test delete_duedate_extension return value passes validation.
     */
    public function test_delete_duedate_extension_return_validation(): void {
        if (!class_exists('\quizaccess_duedate\override_manager')) {
            $this->markTestSkipped('quizaccess_duedate plugin is not installed.');
        }

        global $DB;
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('quiz');
        $this->setUser($scenario->teacher);

        $student = $scenario->students[0];

        \quizaccess_duedate\override_manager::save_override((object) [
            'quizid' => $scenario->activity->id,
            'userid' => $student->id,
            'duedate' => time() + DAYSECS * 14,
        ]);

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        assign_capability('quizaccess/duedate:manageoverrides', CAP_ALLOW, $roleid, $scenario->context->id);

        $result = delete_duedate_extension::execute($scenario->cm->id, $student->id);
        $cleaned = external_api::clean_returnvalue(delete_duedate_extension::execute_returns(), $result);

        $this->assertArrayHasKey('success', $cleaned);
        $this->assertTrue($cleaned['success']);
    }

    /**
     * Test delete_duedate_extension without grade capability throws exception.
     */
    public function test_delete_duedate_extension_no_grade_capability(): void {
        if (!class_exists('\quizaccess_duedate\override_manager')) {
            $this->markTestSkipped('quizaccess_duedate plugin is not installed.');
        }

        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('quiz');

        // Login as a student who lacks the grade capability.
        $this->setUser($scenario->students[0]);

        $this->expectException(\required_capability_exception::class);
        delete_duedate_extension::execute($scenario->cm->id, $scenario->students[1]->id);
    }

    /**
     * Test delete_duedate_extension without duedate manageoverrides capability throws exception.
     */
    public function test_delete_duedate_extension_no_duedate_capability(): void {
        if (!class_exists('\quizaccess_duedate\override_manager')) {
            $this->markTestSkipped('quizaccess_duedate plugin is not installed.');
        }

        global $DB;
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('quiz');

        // Create a non-editing teacher with grade cap but not duedate manageoverrides.
        $gen = $this->getDataGenerator();
        $teacher2 = $gen->create_user();
        $gen->enrol_user($teacher2->id, $scenario->course->id, 'teacher');
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'teacher']);
        assign_capability('local/unifiedgrader:grade', CAP_ALLOW, $roleid, $scenario->context->id);

        $this->setUser($teacher2);

        $this->expectException(\required_capability_exception::class);
        delete_duedate_extension::execute($scenario->cm->id, $scenario->students[0]->id);
    }
}
