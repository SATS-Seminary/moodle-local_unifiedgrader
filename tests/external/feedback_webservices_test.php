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
 * Tests for feedback web services.
 *
 * Tests prepare_feedback_draft, prepare_feedback_files_draft, and save_feedback_files.
 * All three require the local/unifiedgrader:grade capability at CONTEXT_MODULE
 * and are assignment-only operations.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\external\prepare_feedback_draft
 * @covers \local_unifiedgrader\external\prepare_feedback_files_draft
 * @covers \local_unifiedgrader\external\save_feedback_files
 */
final class feedback_webservices_test extends \advanced_testcase {

    /**
     * Helper: create an assignment grading scenario and return the scenario object.
     *
     * @param array $options Additional options for the scenario.
     * @return \stdClass Scenario with course, activity, cm, context, teacher, students.
     */
    private function create_assign_scenario(array $options = []): \stdClass {
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        return $plugingen->create_grading_scenario('assign', $options);
    }

    /**
     * Helper: get a fresh unused draft item ID.
     *
     * @return int
     */
    private function get_draft_itemid(): int {
        return file_get_unused_draft_itemid();
    }

    // -------------------------------------------------------------------------
    // prepare_feedback_draft tests.
    // -------------------------------------------------------------------------

    /**
     * Test prepare_feedback_draft returns feedback HTML for an assignment student.
     */
    public function test_prepare_feedback_draft_happy_path(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $this->resetAfterTest();

        $scenario = $this->create_assign_scenario();
        $this->setUser($scenario->teacher);

        $student = $scenario->students[0];
        $draftitemid = $this->get_draft_itemid();

        $result = prepare_feedback_draft::execute($scenario->cm->id, $student->id, $draftitemid);

        $this->assertArrayHasKey('feedbackhtml', $result);
        // For a student with no prior feedback, the HTML should be empty or contain minimal content.
        $this->assertIsString($result['feedbackhtml']);
    }

    /**
     * Test prepare_feedback_draft return value passes validation.
     */
    public function test_prepare_feedback_draft_return_validation(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $this->resetAfterTest();

        $scenario = $this->create_assign_scenario();
        $this->setUser($scenario->teacher);

        $draftitemid = $this->get_draft_itemid();
        $result = prepare_feedback_draft::execute($scenario->cm->id, $scenario->students[0]->id, $draftitemid);
        $cleaned = external_api::clean_returnvalue(prepare_feedback_draft::execute_returns(), $result);

        $this->assertArrayHasKey('feedbackhtml', $cleaned);
        $this->assertIsString($cleaned['feedbackhtml']);
    }

    /**
     * Test prepare_feedback_draft without capability throws exception.
     */
    public function test_prepare_feedback_draft_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_assign_scenario();
        // Login as a student who does not have the grade capability.
        $this->setUser($scenario->students[0]);

        $this->expectException(\required_capability_exception::class);
        prepare_feedback_draft::execute($scenario->cm->id, $scenario->students[1]->id, $this->get_draft_itemid());
    }

    /**
     * Test prepare_feedback_draft returns existing feedback when student has been graded.
     */
    public function test_prepare_feedback_draft_with_existing_feedback(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $this->resetAfterTest();

        $scenario = $this->create_assign_scenario();
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');

        $student = $scenario->students[0];

        // Create a submission and grade it with feedback.
        $this->setUser($student);
        $plugingen->create_assign_submission($scenario->activity, $student->id);

        $this->setUser($scenario->teacher);
        $cm = get_coursemodule_from_instance('assign', $scenario->activity->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, null);

        $grade = $assign->get_user_grade($student->id, true);
        $grade->grade = 80.0;
        $grade->grader = $scenario->teacher->id;
        $assign->update_grade($grade);

        // Save feedback comment.
        global $DB;
        $feedbackrecord = $DB->get_record('assignfeedback_comments', ['grade' => $grade->id]);
        if ($feedbackrecord) {
            $feedbackrecord->commenttext = '<p>Well done on the assignment.</p>';
            $feedbackrecord->commentformat = FORMAT_HTML;
            $DB->update_record('assignfeedback_comments', $feedbackrecord);
        } else {
            $DB->insert_record('assignfeedback_comments', (object) [
                'grade' => $grade->id,
                'assignment' => $scenario->activity->id,
                'commenttext' => '<p>Well done on the assignment.</p>',
                'commentformat' => FORMAT_HTML,
            ]);
        }

        $draftitemid = $this->get_draft_itemid();
        $result = prepare_feedback_draft::execute($scenario->cm->id, $student->id, $draftitemid);

        $this->assertStringContainsString('Well done on the assignment', $result['feedbackhtml']);
    }

    // -------------------------------------------------------------------------
    // prepare_feedback_files_draft tests.
    // -------------------------------------------------------------------------

    /**
     * Test prepare_feedback_files_draft returns file count for an assignment student.
     */
    public function test_prepare_feedback_files_draft_happy_path(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $this->resetAfterTest();

        $scenario = $this->create_assign_scenario([
            'modparams' => ['assignfeedback_file_enabled' => 1],
        ]);
        $this->setUser($scenario->teacher);

        $student = $scenario->students[0];
        $draftitemid = $this->get_draft_itemid();

        $result = prepare_feedback_files_draft::execute($scenario->cm->id, $student->id, $draftitemid);

        $this->assertArrayHasKey('filecount', $result);
        // No feedback files exist yet, so count should be 0.
        $this->assertEquals(0, $result['filecount']);
    }

    /**
     * Test prepare_feedback_files_draft return value passes validation.
     */
    public function test_prepare_feedback_files_draft_return_validation(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $this->resetAfterTest();

        $scenario = $this->create_assign_scenario([
            'modparams' => ['assignfeedback_file_enabled' => 1],
        ]);
        $this->setUser($scenario->teacher);

        $draftitemid = $this->get_draft_itemid();
        $result = prepare_feedback_files_draft::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            $draftitemid,
        );
        $cleaned = external_api::clean_returnvalue(prepare_feedback_files_draft::execute_returns(), $result);

        $this->assertArrayHasKey('filecount', $cleaned);
        $this->assertIsInt($cleaned['filecount']);
    }

    /**
     * Test prepare_feedback_files_draft without capability throws exception.
     */
    public function test_prepare_feedback_files_draft_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_assign_scenario([
            'modparams' => ['assignfeedback_file_enabled' => 1],
        ]);
        // Login as a student.
        $this->setUser($scenario->students[0]);

        $this->expectException(\required_capability_exception::class);
        prepare_feedback_files_draft::execute($scenario->cm->id, $scenario->students[1]->id, $this->get_draft_itemid());
    }

    /**
     * Test prepare_feedback_files_draft for a student with no submission returns 0 files.
     */
    public function test_prepare_feedback_files_draft_no_submission(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $this->resetAfterTest();

        $scenario = $this->create_assign_scenario([
            'modparams' => ['assignfeedback_file_enabled' => 1],
        ]);
        $this->setUser($scenario->teacher);

        $draftitemid = $this->get_draft_itemid();
        $result = prepare_feedback_files_draft::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            $draftitemid,
        );

        $this->assertEquals(0, $result['filecount']);
    }

    // -------------------------------------------------------------------------
    // save_feedback_files tests.
    // -------------------------------------------------------------------------

    /**
     * Test save_feedback_files with an empty draft area returns 0 files.
     */
    public function test_save_feedback_files_happy_path(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $this->resetAfterTest();

        $scenario = $this->create_assign_scenario([
            'modparams' => ['assignfeedback_file_enabled' => 1],
        ]);
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');

        $student = $scenario->students[0];

        // Create a submission first so the adapter can find the grade record.
        $this->setUser($student);
        $plugingen->create_assign_submission($scenario->activity, $student->id);

        $this->setUser($scenario->teacher);

        // Grade the student so a grade record exists.
        $cm = get_coursemodule_from_instance('assign', $scenario->activity->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, null);
        $grade = $assign->get_user_grade($student->id, true);
        $grade->grade = 75.0;
        $grade->grader = $scenario->teacher->id;
        $assign->update_grade($grade);

        $draftitemid = $this->get_draft_itemid();

        $result = save_feedback_files::execute($scenario->cm->id, $student->id, $draftitemid);

        $this->assertArrayHasKey('filecount', $result);
        // Empty draft area should result in 0 files saved.
        $this->assertEquals(0, $result['filecount']);
    }

    /**
     * Test save_feedback_files return value passes validation.
     */
    public function test_save_feedback_files_return_validation(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $this->resetAfterTest();

        $scenario = $this->create_assign_scenario([
            'modparams' => ['assignfeedback_file_enabled' => 1],
        ]);
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');

        $student = $scenario->students[0];

        $this->setUser($student);
        $plugingen->create_assign_submission($scenario->activity, $student->id);

        $this->setUser($scenario->teacher);

        $cm = get_coursemodule_from_instance('assign', $scenario->activity->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, null);
        $grade = $assign->get_user_grade($student->id, true);
        $grade->grade = 75.0;
        $grade->grader = $scenario->teacher->id;
        $assign->update_grade($grade);

        $draftitemid = $this->get_draft_itemid();
        $result = save_feedback_files::execute($scenario->cm->id, $student->id, $draftitemid);
        $cleaned = external_api::clean_returnvalue(save_feedback_files::execute_returns(), $result);

        $this->assertArrayHasKey('filecount', $cleaned);
        $this->assertIsInt($cleaned['filecount']);
    }

    /**
     * Test save_feedback_files without capability throws exception.
     */
    public function test_save_feedback_files_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_assign_scenario([
            'modparams' => ['assignfeedback_file_enabled' => 1],
        ]);
        // Login as a student.
        $this->setUser($scenario->students[0]);

        $this->expectException(\required_capability_exception::class);
        save_feedback_files::execute($scenario->cm->id, $scenario->students[1]->id, $this->get_draft_itemid());
    }

    /**
     * Test save_feedback_files with a file in draft area saves the file.
     */
    public function test_save_feedback_files_with_draft_file(): void {
        global $CFG, $USER;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $this->resetAfterTest();

        $scenario = $this->create_assign_scenario([
            'modparams' => ['assignfeedback_file_enabled' => 1],
        ]);
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');

        $student = $scenario->students[0];

        // Create a submission.
        $this->setUser($student);
        $plugingen->create_assign_submission($scenario->activity, $student->id);

        $this->setUser($scenario->teacher);

        // Grade the student.
        $cm = get_coursemodule_from_instance('assign', $scenario->activity->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $assign = new \assign($context, $cm, null);
        $grade = $assign->get_user_grade($student->id, true);
        $grade->grade = 90.0;
        $grade->grader = $scenario->teacher->id;
        $assign->update_grade($grade);

        // Create a file in the draft area.
        $draftitemid = $this->get_draft_itemid();
        $fs = get_file_storage();
        $usercontext = \context_user::instance($scenario->teacher->id);
        $fs->create_file_from_string([
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'feedback_notes.txt',
        ], 'Feedback notes content');

        $result = save_feedback_files::execute($scenario->cm->id, $student->id, $draftitemid);

        $this->assertArrayHasKey('filecount', $result);
        $this->assertEquals(1, $result['filecount']);
    }
}
