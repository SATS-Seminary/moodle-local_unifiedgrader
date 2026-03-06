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
 * Tests for penalty-related web service external functions.
 *
 * Covers get_penalties, save_penalty, and delete_penalty.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\external\get_penalties
 * @covers \local_unifiedgrader\external\save_penalty
 * @covers \local_unifiedgrader\external\delete_penalty
 */
final class penalty_webservices_test extends \advanced_testcase {

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
    // get_penalties tests.
    // -------------------------------------------------------------------------

    /**
     * Test get_penalties returns penalties created via save_penalty.
     */
    public function test_get_penalties_happy_path(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        save_penalty::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'wordcount',
            '',
            10,
        );

        $result = get_penalties::execute($scenario->cm->id, $scenario->students[0]->id);

        $this->assertCount(1, $result);
        $this->assertEquals('wordcount', $result[0]['category']);
        $this->assertEquals(10, $result[0]['percentage']);
    }

    /**
     * Test get_penalties returns empty array when no penalties exist.
     */
    public function test_get_penalties_empty(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $result = get_penalties::execute($scenario->cm->id, $scenario->students[0]->id);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test get_penalties throws when user lacks the grade capability.
     */
    public function test_get_penalties_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $this->setUser($scenario->students[0]);

        $this->expectException(\required_capability_exception::class);
        get_penalties::execute($scenario->cm->id, $scenario->students[0]->id);
    }

    /**
     * Test get_penalties return value passes clean_returnvalue validation.
     */
    public function test_get_penalties_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        save_penalty::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'wordcount',
            '',
            10,
        );

        $result = get_penalties::execute($scenario->cm->id, $scenario->students[0]->id);

        $cleaned = external_api::clean_returnvalue(
            get_penalties::execute_returns(),
            $result,
        );

        $this->assertCount(1, $cleaned);
        $this->assertIsInt($cleaned[0]['id']);
        $this->assertIsInt($cleaned[0]['cmid']);
        $this->assertIsInt($cleaned[0]['userid']);
        $this->assertIsInt($cleaned[0]['authorid']);
        $this->assertIsString($cleaned[0]['category']);
        $this->assertIsString($cleaned[0]['label']);
        $this->assertIsInt($cleaned[0]['percentage']);
        $this->assertIsInt($cleaned[0]['timecreated']);
        $this->assertIsInt($cleaned[0]['timemodified']);
    }

    // -------------------------------------------------------------------------
    // save_penalty tests.
    // -------------------------------------------------------------------------

    /**
     * Test save_penalty creates a new wordcount penalty.
     */
    public function test_save_penalty_wordcount(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $result = save_penalty::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'wordcount',
            '',
            10,
        );

        $this->assertArrayHasKey('penaltyid', $result);
        $this->assertGreaterThan(0, $result['penaltyid']);
        $this->assertCount(1, $result['penalties']);
        $this->assertEquals('wordcount', $result['penalties'][0]['category']);
    }

    /**
     * Test save_penalty creates an 'other' penalty with label.
     */
    public function test_save_penalty_other_with_label(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $result = save_penalty::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'other',
            'Formatting',
            5,
        );

        $this->assertGreaterThan(0, $result['penaltyid']);
        $this->assertCount(1, $result['penalties']);
        $this->assertEquals('other', $result['penalties'][0]['category']);
        $this->assertEquals('Formatting', $result['penalties'][0]['label']);
    }

    /**
     * Test save_penalty throws for invalid category.
     */
    public function test_save_penalty_invalid_category(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $this->expectException(\invalid_parameter_exception::class);
        save_penalty::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'invalid',
            '',
            10,
        );
    }

    /**
     * Test save_penalty throws for percentage out of range.
     */
    public function test_save_penalty_invalid_percentage(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $this->expectException(\invalid_parameter_exception::class);
        save_penalty::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'wordcount',
            '',
            0,
        );
    }

    /**
     * Test save_penalty throws when 'other' has no label.
     */
    public function test_save_penalty_other_requires_label(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $this->expectException(\invalid_parameter_exception::class);
        save_penalty::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'other',
            '',
            10,
        );
    }

    /**
     * Test save_penalty throws when user lacks the grade capability.
     */
    public function test_save_penalty_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();
        $this->setUser($scenario->students[0]);

        $this->expectException(\required_capability_exception::class);
        save_penalty::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'wordcount',
            '',
            10,
        );
    }

    /**
     * Test save_penalty return value passes clean_returnvalue validation.
     */
    public function test_save_penalty_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $result = save_penalty::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'wordcount',
            '',
            10,
        );

        $cleaned = external_api::clean_returnvalue(
            save_penalty::execute_returns(),
            $result,
        );

        $this->assertIsInt($cleaned['penaltyid']);
        $this->assertIsArray($cleaned['penalties']);
    }

    /**
     * Test save_penalty wordcount upsert replaces existing.
     */
    public function test_save_penalty_wordcount_upsert(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        save_penalty::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'wordcount',
            '',
            5,
        );

        $result = save_penalty::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'wordcount',
            '',
            15,
        );

        $this->assertCount(1, $result['penalties']);
        $this->assertEquals(15, $result['penalties'][0]['percentage']);
    }

    // -------------------------------------------------------------------------
    // delete_penalty tests.
    // -------------------------------------------------------------------------

    /**
     * Test delete_penalty removes a penalty.
     */
    public function test_delete_penalty_happy_path(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $saveresult = save_penalty::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'wordcount',
            '',
            10,
        );

        $result = delete_penalty::execute($scenario->cm->id, $saveresult['penaltyid']);
        $this->assertTrue($result['success']);

        $penalties = get_penalties::execute($scenario->cm->id, $scenario->students[0]->id);
        $this->assertEmpty($penalties);
    }

    /**
     * Test delete_penalty throws when user lacks the grade capability.
     */
    public function test_delete_penalty_no_capability(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $saveresult = save_penalty::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'wordcount',
            '',
            10,
        );

        $this->setUser($scenario->students[0]);

        $this->expectException(\required_capability_exception::class);
        delete_penalty::execute($scenario->cm->id, $saveresult['penaltyid']);
    }

    /**
     * Test delete_penalty return value passes clean_returnvalue validation.
     */
    public function test_delete_penalty_return_validation(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $saveresult = save_penalty::execute(
            $scenario->cm->id,
            $scenario->students[0]->id,
            'wordcount',
            '',
            10,
        );

        $result = delete_penalty::execute($scenario->cm->id, $saveresult['penaltyid']);

        $cleaned = external_api::clean_returnvalue(
            delete_penalty::execute_returns(),
            $result,
        );

        $this->assertIsBool($cleaned['success']);
        $this->assertTrue($cleaned['success']);
    }

    /**
     * Test delete_penalty does not throw for nonexistent penalty.
     */
    public function test_delete_penalty_nonexistent(): void {
        $this->resetAfterTest();

        $scenario = $this->create_scenario();

        $result = delete_penalty::execute($scenario->cm->id, 99999);
        $this->assertTrue($result['success']);
    }
}
