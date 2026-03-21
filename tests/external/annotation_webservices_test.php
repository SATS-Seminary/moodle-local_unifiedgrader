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
 * Tests for annotation-related web service external functions.
 *
 * Covers get_annotations, save_annotations, delete_annotations, and get_student_annotations.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\external\get_annotations
 * @covers \local_unifiedgrader\external\save_annotations
 * @covers \local_unifiedgrader\external\delete_annotations
 * @covers \local_unifiedgrader\external\get_student_annotations
 */
final class annotation_webservices_test extends \advanced_testcase {
    /** @var int Fake file ID for testing (annotations reference file IDs but do not validate them). */
    private const FAKE_FILEID = 12345;

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

    // Get_annotations tests.

    /**
     * Test get_annotations returns annotations after saving them.
     */
    public function test_get_annotations_happy_path(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $studentid = $scenario->students[0]->id;

        // Save some annotations first.
        save_annotations::execute(
            $scenario->cm->id,
            $studentid,
            self::FAKE_FILEID,
            [
                ['pagenum' => 1, 'annotationdata' => '{"objects":[{"type":"rect"}]}'],
                ['pagenum' => 2, 'annotationdata' => '{"objects":[{"type":"circle"}]}'],
            ],
        );

        $result = get_annotations::execute($scenario->cm->id, $studentid, self::FAKE_FILEID);

        $this->assertCount(2, $result);

        // Verify the annotations contain the expected page data.
        $pages = array_column($result, 'pagenum');
        sort($pages);
        $this->assertEquals([1, 2], $pages);
    }

    /**
     * Test get_annotations throws when user lacks the grade capability.
     */
    public function test_get_annotations_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $this->setUser($scenario->students[0]);

        $this->expectException(\required_capability_exception::class);
        get_annotations::execute($scenario->cm->id, $scenario->students[0]->id, self::FAKE_FILEID);
    }

    /**
     * Test get_annotations return value passes clean_returnvalue validation.
     */
    public function test_get_annotations_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $studentid = $scenario->students[0]->id;

        save_annotations::execute(
            $scenario->cm->id,
            $studentid,
            self::FAKE_FILEID,
            [['pagenum' => 1, 'annotationdata' => '{"objects":[{"type":"rect","left":10,"top":10,"width":50,"height":50}]}']],
        );

        $result = get_annotations::execute($scenario->cm->id, $studentid, self::FAKE_FILEID);

        $cleaned = external_api::clean_returnvalue(
            get_annotations::execute_returns(),
            $result,
        );

        $this->assertCount(1, $cleaned);
        $this->assertIsInt($cleaned[0]['id']);
        $this->assertIsInt($cleaned[0]['cmid']);
        $this->assertIsInt($cleaned[0]['userid']);
        $this->assertIsInt($cleaned[0]['authorid']);
        $this->assertIsInt($cleaned[0]['fileid']);
        $this->assertIsInt($cleaned[0]['pagenum']);
        $this->assertIsString($cleaned[0]['annotationdata']);
        $this->assertIsInt($cleaned[0]['timecreated']);
        $this->assertIsInt($cleaned[0]['timemodified']);
    }

    /**
     * Test get_annotations returns empty array when no annotations exist.
     */
    public function test_get_annotations_empty(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $result = get_annotations::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            self::FAKE_FILEID,
        );

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // Save_annotations tests.

    /**
     * Test save_annotations saves page annotation data for a student.
     */
    public function test_save_annotations_happy_path(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $studentid = $scenario->students[0]->id;

        $result = save_annotations::execute(
            $scenario->cm->id,
            $studentid,
            self::FAKE_FILEID,
            [
                ['pagenum' => 1, 'annotationdata' => '{"objects":[{"type":"rect","left":10,"top":20}]}'],
            ],
        );

        $this->assertTrue($result['success']);

        // Verify persistence.
        $annotations = get_annotations::execute($scenario->cm->id, $studentid, self::FAKE_FILEID);
        $this->assertCount(1, $annotations);
        $this->assertEquals(1, $annotations[0]['pagenum']);
        $this->assertStringContainsString('rect', $annotations[0]['annotationdata']);
    }

    /**
     * Test save_annotations throws when user lacks the grade capability.
     */
    public function test_save_annotations_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $this->setUser($scenario->students[0]);

        $this->expectException(\required_capability_exception::class);
        save_annotations::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            self::FAKE_FILEID,
            [['pagenum' => 1, 'annotationdata' => '{"objects":[]}']],
        );
    }

    /**
     * Test save_annotations return value passes clean_returnvalue validation.
     */
    public function test_save_annotations_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $result = save_annotations::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            self::FAKE_FILEID,
            [['pagenum' => 1, 'annotationdata' => '{"objects":[]}']],
        );

        $cleaned = external_api::clean_returnvalue(
            save_annotations::execute_returns(),
            $result,
        );

        $this->assertIsBool($cleaned['success']);
        $this->assertTrue($cleaned['success']);
    }

    /**
     * Test save_annotations updates existing page annotations (upsert behaviour).
     */
    public function test_save_annotations_upsert(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $studentid = $scenario->students[0]->id;

        // Save initial annotation for page 1.
        save_annotations::execute(
            $scenario->cm->id,
            $studentid,
            self::FAKE_FILEID,
            [['pagenum' => 1, 'annotationdata' => '{"objects":[{"type":"rect"}]}']],
        );

        // Update annotation for page 1.
        save_annotations::execute(
            $scenario->cm->id,
            $studentid,
            self::FAKE_FILEID,
            [['pagenum' => 1, 'annotationdata' => '{"objects":[{"type":"circle"}]}']],
        );

        $annotations = get_annotations::execute($scenario->cm->id, $studentid, self::FAKE_FILEID);

        // Should still be 1 annotation for page 1 (updated, not duplicated).
        $page1 = array_filter($annotations, fn($a) => $a['pagenum'] == 1);
        $this->assertCount(1, $page1);
        $this->assertStringContainsString('circle', reset($page1)['annotationdata']);
    }

    // Delete_annotations tests.

    /**
     * Test delete_annotations removes all annotations for a file.
     */
    public function test_delete_annotations_happy_path(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $studentid = $scenario->students[0]->id;

        // Save annotations.
        save_annotations::execute(
            $scenario->cm->id,
            $studentid,
            self::FAKE_FILEID,
            [
                ['pagenum' => 1, 'annotationdata' => '{"objects":[{"type":"rect"}]}'],
                ['pagenum' => 2, 'annotationdata' => '{"objects":[{"type":"circle"}]}'],
            ],
        );

        // Delete all annotations for the file.
        $result = delete_annotations::execute($scenario->cm->id, $studentid, self::FAKE_FILEID);
        $this->assertTrue($result['success']);

        // Verify they are gone.
        $annotations = get_annotations::execute($scenario->cm->id, $studentid, self::FAKE_FILEID);
        $this->assertEmpty($annotations);
    }

    /**
     * Test delete_annotations throws when user lacks the grade capability.
     */
    public function test_delete_annotations_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $this->setUser($scenario->students[0]);

        $this->expectException(\required_capability_exception::class);
        delete_annotations::execute($scenario->cm->id, $scenario->students[0]->id, self::FAKE_FILEID);
    }

    /**
     * Test delete_annotations return value passes clean_returnvalue validation.
     */
    public function test_delete_annotations_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $result = delete_annotations::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            self::FAKE_FILEID,
        );

        $cleaned = external_api::clean_returnvalue(
            delete_annotations::execute_returns(),
            $result,
        );

        $this->assertIsBool($cleaned['success']);
        $this->assertTrue($cleaned['success']);
    }

    /**
     * Test delete_annotations succeeds even when no annotations exist.
     */
    public function test_delete_annotations_no_data(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        // Delete when nothing exists should succeed gracefully.
        $result = delete_annotations::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            self::FAKE_FILEID,
        );

        $this->assertTrue($result['success']);
    }

    // Get_student_annotations tests.

    /**
     * Test get_student_annotations returns empty when grade not released.
     *
     * The student-facing endpoint requires the grade to be released and the
     * file to belong to the student's submission. Since the grade has not been
     * released in this scenario, it should return an empty array.
     */
    public function test_get_student_annotations_grade_not_released(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $this->setUser($scenario->students[0]);

        $result = get_student_annotations::execute($scenario->cm->id, self::FAKE_FILEID);

        $cleaned = external_api::clean_returnvalue(
            get_student_annotations::execute_returns(),
            $result,
        );

        $this->assertIsArray($cleaned);
        $this->assertEmpty($cleaned);
    }

    /**
     * Test get_student_annotations throws when user lacks viewfeedback capability.
     *
     * We simulate a user without the viewfeedback capability by using the admin
     * to unassign the capability from the student role.
     */
    public function test_get_student_annotations_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        // Remove viewfeedback capability from the student role.
        $studentrole = $this->getDataGenerator()->create_role();
        role_assign($studentrole, $scenario->students[0]->id, $scenario->context->id);
        assign_capability(
            'local/unifiedgrader:viewfeedback',
            CAP_PROHIBIT,
            $studentrole,
            $scenario->context->id,
        );

        $this->setUser($scenario->students[0]);

        $this->expectException(\required_capability_exception::class);
        get_student_annotations::execute($scenario->cm->id, self::FAKE_FILEID);
    }

    /**
     * Test get_student_annotations return value passes clean_returnvalue validation.
     */
    public function test_get_student_annotations_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $this->setUser($scenario->students[0]);

        $result = get_student_annotations::execute($scenario->cm->id, self::FAKE_FILEID);

        $cleaned = external_api::clean_returnvalue(
            get_student_annotations::execute_returns(),
            $result,
        );

        // Regardless of content, the return should be a valid array structure.
        $this->assertIsArray($cleaned);
    }

    /**
     * Test get_student_annotations returns empty for invalid file ID.
     */
    public function test_get_student_annotations_invalid_file(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $this->setUser($scenario->students[0]);

        // A non-existent file ID should return empty (not throw).
        $result = get_student_annotations::execute($scenario->cm->id, 99999);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
