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
 * Tests for the adapter_factory class.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\adapter\adapter_factory
 */
final class adapter_factory_test extends \advanced_testcase {

    /**
     * Test creating an assign adapter.
     */
    public function test_create_assign_adapter(): void {
        $this->resetAfterTest();
        set_config('enable_assign', 1, 'local_unifiedgrader');

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);

        $adapter = adapter_factory::create($cm->id);
        $this->assertInstanceOf(assign_adapter::class, $adapter);
    }

    /**
     * Test creating a forum adapter.
     */
    public function test_create_forum_adapter(): void {
        $this->resetAfterTest();
        set_config('enable_forum', 1, 'local_unifiedgrader');

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $forum = $gen->create_module('forum', ['course' => $course->id, 'grade_forum' => 100]);
        $cm = get_coursemodule_from_instance('forum', $forum->id);

        $adapter = adapter_factory::create($cm->id);
        $this->assertInstanceOf(forum_adapter::class, $adapter);
    }

    /**
     * Test creating a quiz adapter.
     */
    public function test_create_quiz_adapter(): void {
        $this->resetAfterTest();
        set_config('enable_quiz', 1, 'local_unifiedgrader');

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $quiz = $gen->create_module('quiz', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);

        $adapter = adapter_factory::create($cm->id);
        $this->assertInstanceOf(quiz_adapter::class, $adapter);
    }

    /**
     * Test creating an adapter for an unsupported type throws.
     */
    public function test_create_unsupported_type_throws(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('page', $page->id);

        $this->expectException(\moodle_exception::class);
        adapter_factory::create($cm->id);
    }

    /**
     * Test creating an adapter when the type is disabled throws.
     */
    public function test_create_disabled_type_throws(): void {
        $this->resetAfterTest();
        set_config('enable_assign', 0, 'local_unifiedgrader');

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);

        $this->expectException(\moodle_exception::class);
        adapter_factory::create($cm->id);
    }

    /**
     * Test is_supported returns true when enabled.
     */
    public function test_is_supported_returns_true_when_enabled(): void {
        $this->resetAfterTest();
        set_config('enable_assign', 1, 'local_unifiedgrader');

        $this->assertTrue(adapter_factory::is_supported('assign'));
    }

    /**
     * Test is_supported returns false when disabled.
     */
    public function test_is_supported_returns_false_when_disabled(): void {
        $this->resetAfterTest();
        set_config('enable_assign', 0, 'local_unifiedgrader');

        $this->assertFalse(adapter_factory::is_supported('assign'));
    }

    /**
     * Test is_supported returns false for unknown types.
     */
    public function test_is_supported_returns_false_for_unknown(): void {
        $this->resetAfterTest();

        $this->assertFalse(adapter_factory::is_supported('page'));
        $this->assertFalse(adapter_factory::is_supported('nonexistent'));
    }
}
