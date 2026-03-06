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
 * Tests for annotated PDF web services.
 *
 * Tests save_annotated_pdf and delete_annotated_pdf, both of which
 * require the local/unifiedgrader:grade capability at CONTEXT_MODULE.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\external\save_annotated_pdf
 * @covers \local_unifiedgrader\external\delete_annotated_pdf
 */
final class annotated_pdf_webservices_test extends \advanced_testcase {

    /**
     * Helper: create a grading scenario for assignments.
     *
     * @return \stdClass Scenario with course, activity, cm, context, teacher, students.
     */
    private function create_assign_scenario(): \stdClass {
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        return $plugingen->create_grading_scenario('assign');
    }

    /**
     * Generate a minimal valid base64-encoded PDF string for testing.
     *
     * @return string Base64-encoded content.
     */
    private function make_base64_pdf(): string {
        // A minimal PDF-like content for testing purposes.
        $content = '%PDF-1.0 test content';
        return base64_encode($content);
    }

    // -------------------------------------------------------------------------
    // save_annotated_pdf tests.
    // -------------------------------------------------------------------------

    /**
     * Test save_annotated_pdf stores a file successfully.
     */
    public function test_save_annotated_pdf_happy_path(): void {
        $this->resetAfterTest();

        $scenario = $this->create_assign_scenario();
        $this->setUser($scenario->teacher);

        $student = $scenario->students[0];
        $fileid = 12345; // Simulated original file ID.
        $pdfdata = $this->make_base64_pdf();
        $filename = 'annotated_submission.pdf';

        $result = save_annotated_pdf::execute(
            $scenario->cm->id,
            $student->id,
            $fileid,
            $pdfdata,
            $filename,
        );

        $this->assertTrue($result['success']);

        // Verify the file was stored.
        $fs = get_file_storage();
        $file = $fs->get_file(
            $scenario->context->id,
            'local_unifiedgrader',
            'annotatedpdf',
            $fileid,
            '/' . $student->id . '/',
            $filename,
        );
        $this->assertNotFalse($file);
        $this->assertEquals($filename, $file->get_filename());
    }

    /**
     * Test save_annotated_pdf return value passes validation.
     */
    public function test_save_annotated_pdf_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_assign_scenario();
        $this->setUser($scenario->teacher);

        $result = save_annotated_pdf::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            99999,
            $this->make_base64_pdf(),
            'test.pdf',
        );
        $cleaned = external_api::clean_returnvalue(save_annotated_pdf::execute_returns(), $result);

        $this->assertArrayHasKey('success', $cleaned);
        $this->assertTrue($cleaned['success']);
    }

    /**
     * Test save_annotated_pdf without capability throws exception.
     */
    public function test_save_annotated_pdf_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_assign_scenario();
        // Login as a student who does not have the grade capability.
        $this->setUser($scenario->students[0]);

        $this->expectException(\required_capability_exception::class);
        save_annotated_pdf::execute(
            $scenario->cm->id,
            $scenario->students[1]->id,
            12345,
            $this->make_base64_pdf(),
            'test.pdf',
        );
    }

    /**
     * Test save_annotated_pdf with invalid base64 throws exception.
     */
    public function test_save_annotated_pdf_invalid_base64(): void {
        $this->resetAfterTest();

        $scenario = $this->create_assign_scenario();
        $this->setUser($scenario->teacher);

        $this->expectException(\invalid_parameter_exception::class);
        save_annotated_pdf::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            12345,
            '!!!not-valid-base64!!!',
            'test.pdf',
        );
    }

    /**
     * Test save_annotated_pdf overwrites an existing file.
     */
    public function test_save_annotated_pdf_overwrites_existing(): void {
        $this->resetAfterTest();

        $scenario = $this->create_assign_scenario();
        $this->setUser($scenario->teacher);

        $student = $scenario->students[0];
        $fileid = 12345;
        $filename = 'annotated.pdf';

        // Save first version.
        $pdfdata1 = base64_encode('Version 1');
        save_annotated_pdf::execute($scenario->cm->id, $student->id, $fileid, $pdfdata1, $filename);

        // Save second version (should overwrite).
        $pdfdata2 = base64_encode('Version 2');
        $result = save_annotated_pdf::execute($scenario->cm->id, $student->id, $fileid, $pdfdata2, $filename);
        $this->assertTrue($result['success']);

        // Verify only one file exists and it has the new content.
        $fs = get_file_storage();
        $file = $fs->get_file(
            $scenario->context->id,
            'local_unifiedgrader',
            'annotatedpdf',
            $fileid,
            '/' . $student->id . '/',
            $filename,
        );
        $this->assertNotFalse($file);
        $this->assertEquals('Version 2', $file->get_content());
    }

    // -------------------------------------------------------------------------
    // delete_annotated_pdf tests.
    // -------------------------------------------------------------------------

    /**
     * Test delete_annotated_pdf removes the stored file.
     */
    public function test_delete_annotated_pdf_happy_path(): void {
        $this->resetAfterTest();

        $scenario = $this->create_assign_scenario();
        $this->setUser($scenario->teacher);

        $student = $scenario->students[0];
        $fileid = 12345;
        $filename = 'annotated.pdf';

        // First save a file.
        save_annotated_pdf::execute(
            $scenario->cm->id,
            $student->id,
            $fileid,
            $this->make_base64_pdf(),
            $filename,
        );

        // Now delete it.
        $result = delete_annotated_pdf::execute($scenario->cm->id, $student->id, $fileid);
        $this->assertTrue($result['success']);

        // Verify the file is gone.
        $fs = get_file_storage();
        $file = $fs->get_file(
            $scenario->context->id,
            'local_unifiedgrader',
            'annotatedpdf',
            $fileid,
            '/' . $student->id . '/',
            $filename,
        );
        $this->assertFalse($file);
    }

    /**
     * Test delete_annotated_pdf return value passes validation.
     */
    public function test_delete_annotated_pdf_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_assign_scenario();
        $this->setUser($scenario->teacher);

        // Delete when nothing exists still succeeds.
        $result = delete_annotated_pdf::execute($scenario->cm->id, $scenario->students[0]->id, 99999);
        $cleaned = external_api::clean_returnvalue(delete_annotated_pdf::execute_returns(), $result);

        $this->assertArrayHasKey('success', $cleaned);
        $this->assertTrue($cleaned['success']);
    }

    /**
     * Test delete_annotated_pdf without capability throws exception.
     */
    public function test_delete_annotated_pdf_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_assign_scenario();
        // Login as a student.
        $this->setUser($scenario->students[0]);

        $this->expectException(\required_capability_exception::class);
        delete_annotated_pdf::execute($scenario->cm->id, $scenario->students[0]->id, 12345);
    }

    /**
     * Test delete_annotated_pdf with no existing file still succeeds.
     */
    public function test_delete_annotated_pdf_no_file_succeeds(): void {
        $this->resetAfterTest();

        $scenario = $this->create_assign_scenario();
        $this->setUser($scenario->teacher);

        // Deleting a file that does not exist should not throw.
        $result = delete_annotated_pdf::execute($scenario->cm->id, $scenario->students[0]->id, 99999);
        $this->assertTrue($result['success']);
    }

    /**
     * Test delete_annotated_pdf only deletes files for the specified student.
     */
    public function test_delete_annotated_pdf_student_isolation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_assign_scenario();
        $this->setUser($scenario->teacher);

        $student1 = $scenario->students[0];
        $student2 = $scenario->students[1];
        $fileid = 12345;
        $filename = 'annotated.pdf';

        // Save files for two students with the same fileid.
        save_annotated_pdf::execute($scenario->cm->id, $student1->id, $fileid, $this->make_base64_pdf(), $filename);
        save_annotated_pdf::execute($scenario->cm->id, $student2->id, $fileid, $this->make_base64_pdf(), $filename);

        // Delete only student1's file.
        delete_annotated_pdf::execute($scenario->cm->id, $student1->id, $fileid);

        $fs = get_file_storage();

        // Student1's file should be gone.
        $file1 = $fs->get_file(
            $scenario->context->id, 'local_unifiedgrader', 'annotatedpdf',
            $fileid, '/' . $student1->id . '/', $filename,
        );
        $this->assertFalse($file1);

        // Student2's file should still exist.
        $file2 = $fs->get_file(
            $scenario->context->id, 'local_unifiedgrader', 'annotatedpdf',
            $fileid, '/' . $student2->id . '/', $filename,
        );
        $this->assertNotFalse($file2);
    }
}
