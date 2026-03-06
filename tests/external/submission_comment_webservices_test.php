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
 * Tests for submission comment web service external functions.
 *
 * Covers get_submission_comments, add_submission_comment, and delete_submission_comment.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\external\get_submission_comments
 * @covers \local_unifiedgrader\external\add_submission_comment
 * @covers \local_unifiedgrader\external\delete_submission_comment
 */
final class submission_comment_webservices_test extends \advanced_testcase {

    /**
     * Helper: create a grading scenario with a submitted student and set teacher as current user.
     *
     * The assignsubmission_comments plugin must be enabled for submission comments
     * to work. We also enable the Moodle comment system globally.
     *
     * @param array $options Options passed to create_grading_scenario.
     * @return \stdClass Scenario object with course, activity, cm, context, teacher, students.
     */
    private function create_scenario_with_submission(array $options = []): \stdClass {
        global $CFG;

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $modparams = array_merge([
            'assignsubmission_comments_enabled' => 1,
        ], $options['modparams'] ?? []);
        $options['modparams'] = $modparams;

        $scenario = $plugingen->create_grading_scenario('assign', $options);

        // Enable comments globally.
        $CFG->usecomments = 1;

        // Create submission as the first student.
        $this->setUser($scenario->students[0]);
        $plugingen->create_assign_submission($scenario->activity, $scenario->students[0]->id, '<p>My submission</p>');

        $this->setUser($scenario->teacher);
        return $scenario;
    }

    // -------------------------------------------------------------------------
    // get_submission_comments tests.
    // -------------------------------------------------------------------------

    /**
     * Test get_submission_comments returns comments after adding one.
     */
    public function test_get_submission_comments_happy_path(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario_with_submission();

        // Add a comment.
        add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Test comment from teacher',
        );

        $result = get_submission_comments::execute($scenario->cm->id, $scenario->students[0]->id);

        $this->assertArrayHasKey('comments', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertArrayHasKey('canpost', $result);
        $this->assertGreaterThanOrEqual(1, $result['count']);

        // Find our comment in the list.
        $found = false;
        foreach ($result['comments'] as $comment) {
            if (strpos($comment['content'], 'Test comment from teacher') !== false) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'The added comment should appear in the comment list');
    }

    /**
     * Test get_submission_comments throws when user lacks both grade and viewfeedback capabilities.
     */
    public function test_get_submission_comments_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario_with_submission();

        // Create a user who is enrolled but has neither grade nor viewfeedback capability.
        $gen = $this->getDataGenerator();
        $norole = $gen->create_user(['username' => 'noroleuser']);
        $gen->enrol_user($norole->id, $scenario->course->id, 'guest');

        // Remove viewfeedback from guest.
        $roles = get_archetype_roles('guest');
        $guestroleobj = reset($roles);
        if ($guestroleobj) {
            assign_capability(
                'local/unifiedgrader:viewfeedback',
                CAP_PROHIBIT,
                $guestroleobj->id,
                $scenario->context->id,
            );
        }

        $this->setUser($norole);

        $this->expectException(\required_capability_exception::class);
        get_submission_comments::execute($scenario->cm->id, $scenario->students[0]->id);
    }

    /**
     * Test get_submission_comments return value passes clean_returnvalue validation.
     */
    public function test_get_submission_comments_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario_with_submission();

        $result = get_submission_comments::execute($scenario->cm->id, $scenario->students[0]->id);

        $cleaned = external_api::clean_returnvalue(
            get_submission_comments::execute_returns(),
            $result,
        );

        $this->assertIsArray($cleaned['comments']);
        $this->assertIsInt($cleaned['count']);
        $this->assertIsBool($cleaned['canpost']);
    }

    /**
     * Test get_submission_comments returns empty when no submission exists.
     */
    public function test_get_submission_comments_no_submission(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('assign');
        $this->setUser($scenario->teacher);

        // Student 1 has no submission in this scenario.
        $result = get_submission_comments::execute($scenario->cm->id, $scenario->students[1]->id);

        $this->assertEmpty($result['comments']);
        $this->assertEquals(0, $result['count']);
    }

    // -------------------------------------------------------------------------
    // add_submission_comment tests.
    // -------------------------------------------------------------------------

    /**
     * Test add_submission_comment creates a comment successfully.
     */
    public function test_add_submission_comment_happy_path(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario_with_submission();

        $result = add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'A teacher comment on the submission',
        );

        $this->assertArrayHasKey('id', $result);
        $this->assertGreaterThan(0, $result['id']);
        $this->assertStringContainsString('A teacher comment on the submission', $result['content']);
        $this->assertArrayHasKey('count', $result);
        $this->assertGreaterThanOrEqual(1, $result['count']);
    }

    /**
     * Test add_submission_comment throws when user lacks both grade and viewfeedback capabilities.
     */
    public function test_add_submission_comment_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario_with_submission();

        // Create a user who is enrolled but lacks the needed capabilities.
        $gen = $this->getDataGenerator();
        $norole = $gen->create_user(['username' => 'noroleuser']);
        $gen->enrol_user($norole->id, $scenario->course->id, 'guest');

        $roles = get_archetype_roles('guest');
        $guestroleobj = reset($roles);
        if ($guestroleobj) {
            assign_capability(
                'local/unifiedgrader:viewfeedback',
                CAP_PROHIBIT,
                $guestroleobj->id,
                $scenario->context->id,
            );
        }

        $this->setUser($norole);

        $this->expectException(\required_capability_exception::class);
        add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Should not be saved',
        );
    }

    /**
     * Test add_submission_comment return value passes clean_returnvalue validation.
     */
    public function test_add_submission_comment_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario_with_submission();

        $result = add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Validated comment',
        );

        $cleaned = external_api::clean_returnvalue(
            add_submission_comment::execute_returns(),
            $result,
        );

        $this->assertIsInt($cleaned['id']);
        $this->assertIsString($cleaned['content']);
        $this->assertIsString($cleaned['fullname']);
        $this->assertIsString($cleaned['time']);
        $this->assertIsInt($cleaned['count']);
    }

    /**
     * Test add_submission_comment throws when student has no submission.
     */
    public function test_add_submission_comment_no_submission(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('assign', [
            'modparams' => ['assignsubmission_comments_enabled' => 1],
        ]);
        $this->setUser($scenario->teacher);

        // Student 1 has no submission.
        $this->expectException(\moodle_exception::class);
        add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[1]->id,
            'Comment on nonexistent submission',
        );
    }

    // -------------------------------------------------------------------------
    // delete_submission_comment tests.
    // -------------------------------------------------------------------------

    /**
     * Test delete_submission_comment removes a comment successfully.
     */
    public function test_delete_submission_comment_happy_path(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario_with_submission();

        // Add a comment.
        $addresult = add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Comment to delete',
        );

        // Delete the comment.
        $result = delete_submission_comment::execute($scenario->cm->id, $addresult['id']);

        $this->assertTrue($result['success']);
        $this->assertIsInt($result['count']);
    }

    /**
     * Test delete_submission_comment throws when user lacks both grade and viewfeedback capabilities.
     */
    public function test_delete_submission_comment_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario_with_submission();

        // Add a comment as teacher.
        $addresult = add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'Comment to try to delete',
        );

        // Create a user who is enrolled but lacks the needed capabilities.
        $gen = $this->getDataGenerator();
        $norole = $gen->create_user(['username' => 'noroleuser']);
        $gen->enrol_user($norole->id, $scenario->course->id, 'guest');

        $roles = get_archetype_roles('guest');
        $guestroleobj = reset($roles);
        if ($guestroleobj) {
            assign_capability(
                'local/unifiedgrader:viewfeedback',
                CAP_PROHIBIT,
                $guestroleobj->id,
                $scenario->context->id,
            );
        }

        $this->setUser($norole);

        $this->expectException(\required_capability_exception::class);
        delete_submission_comment::execute($scenario->cm->id, $addresult['id']);
    }

    /**
     * Test delete_submission_comment return value passes clean_returnvalue validation.
     */
    public function test_delete_submission_comment_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario_with_submission();

        $addresult = add_submission_comment::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'For return validation',
        );

        $result = delete_submission_comment::execute($scenario->cm->id, $addresult['id']);

        $cleaned = external_api::clean_returnvalue(
            delete_submission_comment::execute_returns(),
            $result,
        );

        $this->assertIsBool($cleaned['success']);
        $this->assertTrue($cleaned['success']);
        $this->assertIsInt($cleaned['count']);
    }

    /**
     * Test delete_submission_comment throws for a nonexistent comment ID.
     */
    public function test_delete_submission_comment_nonexistent(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('assign');
        $this->setUser($scenario->teacher);

        $this->expectException(\dml_missing_record_exception::class);
        delete_submission_comment::execute($scenario->cm->id, 99999);
    }
}
