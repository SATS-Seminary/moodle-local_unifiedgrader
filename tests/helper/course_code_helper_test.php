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

namespace local_unifiedgrader;

/**
 * Tests for the course_code_helper class.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\course_code_helper
 */
final class course_code_helper_test extends \advanced_testcase {

    /**
     * Test extract_code with no regex configured returns full shortname.
     */
    public function test_extract_code_no_regex_configured(): void {
        $this->resetAfterTest();
        set_config('coursecode_regex', '', 'local_unifiedgrader');

        $result = course_code_helper::extract_code('BIB101-2026-S1');
        $this->assertEquals('BIB101-2026-S1', $result);
    }

    /**
     * Test extract_code with a capturing group returns the captured portion.
     */
    public function test_extract_code_with_capturing_group(): void {
        $this->resetAfterTest();
        set_config('coursecode_regex', '/^([A-Z]+\d+)/', 'local_unifiedgrader');

        $result = course_code_helper::extract_code('BIB101-2026-S1');
        $this->assertEquals('BIB101', $result);
    }

    /**
     * Test extract_code without a capturing group returns full match.
     */
    public function test_extract_code_full_match_no_group(): void {
        $this->resetAfterTest();
        set_config('coursecode_regex', '/^[A-Z]+\d+/', 'local_unifiedgrader');

        $result = course_code_helper::extract_code('BIB101-2026-S1');
        $this->assertEquals('BIB101', $result);
    }

    /**
     * Test extract_code with non-matching regex returns full shortname.
     */
    public function test_extract_code_no_match(): void {
        $this->resetAfterTest();
        set_config('coursecode_regex', '/^NOMATCH/', 'local_unifiedgrader');

        $result = course_code_helper::extract_code('BIB101-2026-S1');
        $this->assertEquals('BIB101-2026-S1', $result);
    }

    /**
     * Test extract_code with invalid regex returns full shortname.
     */
    public function test_extract_code_invalid_regex(): void {
        $this->resetAfterTest();
        set_config('coursecode_regex', '/[invalid', 'local_unifiedgrader');

        $result = course_code_helper::extract_code('BIB101-2026-S1');
        $this->assertEquals('BIB101-2026-S1', $result);
    }

    /**
     * Test extract_code_from_courseid looks up course and extracts code.
     */
    public function test_extract_code_from_courseid(): void {
        $this->resetAfterTest();
        set_config('coursecode_regex', '/^([A-Z]+\d+)/', 'local_unifiedgrader');

        $course = $this->getDataGenerator()->create_course(['shortname' => 'THE301-2026-S2']);
        $result = course_code_helper::extract_code_from_courseid($course->id);
        $this->assertEquals('THE301', $result);
    }

    /**
     * Test extract_code_from_courseid with nonexistent course returns empty string.
     */
    public function test_extract_code_from_invalid_courseid(): void {
        $this->resetAfterTest();

        $result = course_code_helper::extract_code_from_courseid(99999);
        $this->assertEquals('', $result);
    }

    /**
     * Test the SATS pattern: shortnames like BIB101-2026-S1, THE301-2026-S2.
     */
    public function test_extract_code_real_sats_pattern(): void {
        $this->resetAfterTest();
        set_config('coursecode_regex', '/^([A-Z]{2,5}\d{3})/', 'local_unifiedgrader');

        $this->assertEquals('BIB101', course_code_helper::extract_code('BIB101-2026-S1'));
        $this->assertEquals('THE301', course_code_helper::extract_code('THE301-2026-S2'));
        $this->assertEquals('GREEK201', course_code_helper::extract_code('GREEK201-2025-S1'));
    }
}
