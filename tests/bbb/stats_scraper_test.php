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

namespace local_unifiedgrader\bbb;

/**
 * Unit tests for the BBB statistics-page parser.
 *
 * Runs against a real BBB statistics-page fixture captured from a SATS
 * production recording to keep the parser honest against actual BBB output.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\bbb\stats_scraper
 */
final class stats_scraper_test extends \basic_testcase {
    /**
     * Test parse_html against a captured real BBB statistics page.
     */
    public function test_parses_real_fixture(): void {
        $fixture = file_get_contents(__DIR__ . '/../fixtures/bbb_statistics_sample.html');
        $this->assertNotFalse($fixture, 'Fixture file should be readable');

        $parsed = stats_scraper::parse_html($fixture);
        $this->assertIsArray($parsed);
        $this->assertCount(2, $parsed['participants']);

        // Map by fullname so test order is independent of DOM iteration order.
        $byname = [];
        foreach ($parsed['participants'] as $p) {
            $byname[$p['fullname']] = $p;
        }

        $this->assertArrayHasKey('Admin User', $byname);
        $this->assertArrayHasKey('Teststudent 015', $byname);

        // Admin User: duration 00:04:27 = 267s, talk 00:00:45 = 45s, no engagement.
        $admin = $byname['Admin User'];
        $this->assertEquals(267, $admin['duration']);
        $this->assertEquals(45, $admin['talks']);
        $this->assertEquals(0, $admin['chats']);
        $this->assertEquals(0, $admin['emojis']);
        $this->assertEquals(0, $admin['raisehand']);
        $this->assertNull($admin['activityscore']); // "-" in the cell

        // Teststudent 015: duration 00:03:03 = 183s, talk 00:00:30 = 30s, chats 2, emojis 2, raisehand 3.
        $student = $byname['Teststudent 015'];
        $this->assertEquals(183, $student['duration']);
        $this->assertEquals(30, $student['talks']);
        $this->assertEquals(2, $student['chats']);
        $this->assertEquals(2, $student['emojis']);
        $this->assertEquals(3, $student['raisehand']);
        $this->assertEquals(10.0, $student['activityscore']);

        // Both have a BBB internal session id captured.
        $this->assertNotEmpty($admin['bbbuid']);
        $this->assertNotEmpty($student['bbbuid']);
        $this->assertNotEquals($admin['bbbuid'], $student['bbbuid']);

        // Meeting-level poll count: "Polling (0)" → 0.
        $this->assertEquals(0, $parsed['pollcount']);
    }

    /**
     * Test parse_hms_seconds covers HH:MM:SS, MM:SS, dash, and empty.
     */
    public function test_parse_hms_seconds(): void {
        $this->assertEquals(0, stats_scraper::parse_hms_seconds(''));
        $this->assertEquals(0, stats_scraper::parse_hms_seconds('-'));
        $this->assertEquals(45, stats_scraper::parse_hms_seconds('00:00:45'));
        $this->assertEquals(267, stats_scraper::parse_hms_seconds('00:04:27'));
        $this->assertEquals(3661, stats_scraper::parse_hms_seconds('01:01:01'));
        $this->assertEquals(125, stats_scraper::parse_hms_seconds('02:05')); // MM:SS form.
        $this->assertEquals(0, stats_scraper::parse_hms_seconds('garbage'));
    }

    /**
     * Test that an empty / non-statistics HTML returns null cleanly.
     */
    public function test_returns_null_for_empty_input(): void {
        $this->assertNull(stats_scraper::parse_html(''));
        $this->assertNull(stats_scraper::parse_html('<html><body><p>Not a stats page</p></body></html>'));
    }
}
