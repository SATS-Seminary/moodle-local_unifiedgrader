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

namespace local_unifiedgrader\manager;

use local_unifiedgrader\penalty_manager;

/**
 * Tests for the penalty manager.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\penalty_manager
 */
final class penalty_manager_test extends \advanced_testcase {

    /**
     * Test saving and retrieving a wordcount penalty.
     */
    public function test_save_and_get_wordcount_penalty(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $teacher = $gen->create_user();
        $student = $gen->create_user();

        $id = penalty_manager::save_penalty(
            $cm->id, $student->id, $teacher->id, 'wordcount', '', 10,
        );

        $this->assertGreaterThan(0, $id);

        $penalties = penalty_manager::get_penalties($cm->id, $student->id);
        $this->assertCount(1, $penalties);
        $this->assertEquals('wordcount', $penalties[0]['category']);
        $this->assertEquals(10, $penalties[0]['percentage']);
        $this->assertEquals('', $penalties[0]['label']);
    }

    /**
     * Test saving an 'other' penalty with a label.
     */
    public function test_save_other_penalty_with_label(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $teacher = $gen->create_user();
        $student = $gen->create_user();

        $id = penalty_manager::save_penalty(
            $cm->id, $student->id, $teacher->id, 'other', 'Formatting', 5,
        );

        $penalties = penalty_manager::get_penalties($cm->id, $student->id);
        $this->assertCount(1, $penalties);
        $this->assertEquals('other', $penalties[0]['category']);
        $this->assertEquals('Formatting', $penalties[0]['label']);
        $this->assertEquals(5, $penalties[0]['percentage']);
    }

    /**
     * Test that wordcount upserts replace existing wordcount penalty.
     */
    public function test_wordcount_upsert_replaces(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $teacher = $gen->create_user();
        $student = $gen->create_user();

        penalty_manager::save_penalty($cm->id, $student->id, $teacher->id, 'wordcount', '', 5);
        penalty_manager::save_penalty($cm->id, $student->id, $teacher->id, 'wordcount', '', 15);

        $penalties = penalty_manager::get_penalties($cm->id, $student->id);
        $this->assertCount(1, $penalties);
        $this->assertEquals(15, $penalties[0]['percentage']);
    }

    /**
     * Test that 'other' penalties with same label upsert.
     */
    public function test_other_same_label_upserts(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $teacher = $gen->create_user();
        $student = $gen->create_user();

        penalty_manager::save_penalty($cm->id, $student->id, $teacher->id, 'other', 'Late', 5);
        penalty_manager::save_penalty($cm->id, $student->id, $teacher->id, 'other', 'Late', 20);

        $penalties = penalty_manager::get_penalties($cm->id, $student->id);
        $this->assertCount(1, $penalties);
        $this->assertEquals(20, $penalties[0]['percentage']);
    }

    /**
     * Test that 'other' penalties with different labels create separate records.
     */
    public function test_other_different_labels_stack(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $teacher = $gen->create_user();
        $student = $gen->create_user();

        penalty_manager::save_penalty($cm->id, $student->id, $teacher->id, 'other', 'Formatting', 5);
        penalty_manager::save_penalty($cm->id, $student->id, $teacher->id, 'other', 'References', 10);

        $penalties = penalty_manager::get_penalties($cm->id, $student->id);
        $this->assertCount(2, $penalties);
    }

    /**
     * Test that wordcount and other penalties can coexist.
     */
    public function test_wordcount_and_other_coexist(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $teacher = $gen->create_user();
        $student = $gen->create_user();

        penalty_manager::save_penalty($cm->id, $student->id, $teacher->id, 'wordcount', '', 10);
        penalty_manager::save_penalty($cm->id, $student->id, $teacher->id, 'other', 'Late', 5);

        $penalties = penalty_manager::get_penalties($cm->id, $student->id);
        $this->assertCount(2, $penalties);
    }

    /**
     * Test delete_penalty removes a single penalty.
     */
    public function test_delete_penalty(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $teacher = $gen->create_user();
        $student = $gen->create_user();

        $id = penalty_manager::save_penalty($cm->id, $student->id, $teacher->id, 'wordcount', '', 10);
        penalty_manager::delete_penalty($id);

        $penalties = penalty_manager::get_penalties($cm->id, $student->id);
        $this->assertEmpty($penalties);
    }

    /**
     * Test delete_all_penalties removes all penalties for a student.
     */
    public function test_delete_all_penalties(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $teacher = $gen->create_user();
        $student = $gen->create_user();

        penalty_manager::save_penalty($cm->id, $student->id, $teacher->id, 'wordcount', '', 10);
        penalty_manager::save_penalty($cm->id, $student->id, $teacher->id, 'other', 'Late', 5);

        penalty_manager::delete_all_penalties($cm->id, $student->id);

        $penalties = penalty_manager::get_penalties($cm->id, $student->id);
        $this->assertEmpty($penalties);
    }

    /**
     * Test get_total_deduction calculates correctly.
     */
    public function test_get_total_deduction(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $teacher = $gen->create_user();
        $student = $gen->create_user();

        penalty_manager::save_penalty($cm->id, $student->id, $teacher->id, 'wordcount', '', 10);
        penalty_manager::save_penalty($cm->id, $student->id, $teacher->id, 'other', 'Late', 5);

        // 15% of 100 = 15.
        $deduction = penalty_manager::get_total_deduction($cm->id, $student->id, 100.0);
        $this->assertEquals(15.0, $deduction);

        // 15% of 80 = 12.
        $deduction = penalty_manager::get_total_deduction($cm->id, $student->id, 80.0);
        $this->assertEquals(12.0, $deduction);
    }

    /**
     * Test get_total_percentage sums correctly.
     */
    public function test_get_total_percentage(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $teacher = $gen->create_user();
        $student = $gen->create_user();

        penalty_manager::save_penalty($cm->id, $student->id, $teacher->id, 'wordcount', '', 10);
        penalty_manager::save_penalty($cm->id, $student->id, $teacher->id, 'other', 'Late', 5);

        $this->assertEquals(15, penalty_manager::get_total_percentage($cm->id, $student->id));
    }

    /**
     * Test get_total_deduction returns 0 when no penalties exist.
     */
    public function test_get_total_deduction_empty(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $student = $gen->create_user();

        $deduction = penalty_manager::get_total_deduction($cm->id, $student->id, 100.0);
        $this->assertEquals(0.0, $deduction);
    }

    /**
     * Test get_penalties returns empty array when no penalties exist.
     */
    public function test_get_penalties_empty(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $student = $gen->create_user();

        $penalties = penalty_manager::get_penalties($cm->id, $student->id);
        $this->assertIsArray($penalties);
        $this->assertEmpty($penalties);
    }

    /**
     * Test penalties are scoped to the correct student.
     */
    public function test_penalties_scoped_to_student(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $teacher = $gen->create_user();
        $student1 = $gen->create_user();
        $student2 = $gen->create_user();

        penalty_manager::save_penalty($cm->id, $student1->id, $teacher->id, 'wordcount', '', 10);
        penalty_manager::save_penalty($cm->id, $student2->id, $teacher->id, 'wordcount', '', 20);

        $penalties1 = penalty_manager::get_penalties($cm->id, $student1->id);
        $this->assertCount(1, $penalties1);
        $this->assertEquals(10, $penalties1[0]['percentage']);

        $penalties2 = penalty_manager::get_penalties($cm->id, $student2->id);
        $this->assertCount(1, $penalties2);
        $this->assertEquals(20, $penalties2[0]['percentage']);
    }

    /**
     * Test update by explicit penalty ID.
     */
    public function test_update_by_penaltyid(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $teacher = $gen->create_user();
        $student = $gen->create_user();

        $id = penalty_manager::save_penalty($cm->id, $student->id, $teacher->id, 'wordcount', '', 5);

        // Update by explicit ID.
        $updatedid = penalty_manager::save_penalty(
            $cm->id, $student->id, $teacher->id, 'wordcount', '', 25, $id,
        );

        $this->assertEquals($id, $updatedid);

        $penalties = penalty_manager::get_penalties($cm->id, $student->id);
        $this->assertCount(1, $penalties);
        $this->assertEquals(25, $penalties[0]['percentage']);
    }

    /**
     * Test label is trimmed to 15 characters.
     */
    public function test_label_trimmed_to_max_length(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $teacher = $gen->create_user();
        $student = $gen->create_user();

        penalty_manager::save_penalty(
            $cm->id, $student->id, $teacher->id, 'other', 'This is a very long label', 5,
        );

        $penalties = penalty_manager::get_penalties($cm->id, $student->id);
        $this->assertCount(1, $penalties);
        $this->assertEquals(15, strlen($penalties[0]['label']));
        $this->assertEquals('This is a very ', $penalties[0]['label']);
    }

    /**
     * Test return structure has all expected keys.
     */
    public function test_get_penalties_return_structure(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $teacher = $gen->create_user();
        $student = $gen->create_user();

        penalty_manager::save_penalty($cm->id, $student->id, $teacher->id, 'wordcount', '', 10);

        $penalties = penalty_manager::get_penalties($cm->id, $student->id);
        $this->assertCount(1, $penalties);

        $expectedkeys = ['id', 'cmid', 'userid', 'authorid', 'category', 'label', 'percentage', 'timecreated', 'timemodified'];
        foreach ($expectedkeys as $key) {
            $this->assertArrayHasKey($key, $penalties[0]);
        }

        $this->assertIsInt($penalties[0]['id']);
        $this->assertIsInt($penalties[0]['cmid']);
        $this->assertIsInt($penalties[0]['userid']);
        $this->assertIsInt($penalties[0]['authorid']);
        $this->assertIsString($penalties[0]['category']);
        $this->assertIsString($penalties[0]['label']);
        $this->assertIsInt($penalties[0]['percentage']);
        $this->assertIsInt($penalties[0]['timecreated']);
        $this->assertIsInt($penalties[0]['timemodified']);
    }
}
