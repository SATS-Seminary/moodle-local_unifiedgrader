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
 * Tests for the engagement service caching layer + adapter fallback.
 *
 * Skipped when mod_bigbluebuttonbn is not installed.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\bbb\engagement_service
 */
final class engagement_service_test extends \advanced_testcase {
    /**
     * Skip when BBB is not installed.
     */
    protected function setUp(): void {
        parent::setUp();
        if (!class_exists('\mod_bigbluebuttonbn\instance')) {
            $this->markTestSkipped('mod_bigbluebuttonbn not installed');
        }
    }

    /**
     * Test name normalisation handles whitespace, case, and punctuation.
     */
    public function test_normalise_name_collapses_variants(): void {
        $this->assertSame(
            engagement_service::normalise_name('  Jane   DOE '),
            engagement_service::normalise_name('jane doe'),
        );
        $this->assertSame(
            engagement_service::normalise_name('María-José Pérez'),
            engagement_service::normalise_name('maría josé pérez'),
        );
    }

    /**
     * Test get_user_totals aggregates across multiple cached recording rows.
     */
    public function test_user_totals_aggregate_across_recordings(): void {
        global $DB;
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('bigbluebuttonbn');
        $userid = $scenario->students[0]->id;
        $cmid = $scenario->cm->id;

        $now = time();
        $DB->insert_record('local_unifiedgrader_bbbeng', (object) [
            'cmid' => $cmid, 'recordingid' => 'rec-1', 'userid' => $userid,
            'fullname' => 'Test One', 'duration' => 600, 'talks' => 30,
            'chats' => 2, 'raisehand' => 1, 'polls' => 0, 'emojis' => 3,
            'timefetched' => $now,
        ]);
        $DB->insert_record('local_unifiedgrader_bbbeng', (object) [
            'cmid' => $cmid, 'recordingid' => 'rec-2', 'userid' => $userid,
            'fullname' => 'Test One', 'duration' => 1200, 'talks' => 60,
            'chats' => 4, 'raisehand' => 0, 'polls' => 1, 'emojis' => 0,
            'timefetched' => $now,
        ]);

        $totals = engagement_service::get_user_totals($cmid, $userid);
        $this->assertTrue($totals['hasdata']);
        $this->assertEquals(2, $totals['sessioncount']);
        $this->assertEquals(1800, $totals['duration']);
        $this->assertEquals(90, $totals['talks']);
        $this->assertEquals(6, $totals['chats']);
        $this->assertEquals(1, $totals['raisehand']);
        $this->assertEquals(1, $totals['polls']);
        $this->assertEquals(3, $totals['emojis']);
    }

    /**
     * Test that adapter pulls from scraped data when no Summary log exists.
     */
    public function test_adapter_falls_back_to_scraped_data(): void {
        global $DB;
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('bigbluebuttonbn');
        $userid = $scenario->students[0]->id;
        $cmid = $scenario->cm->id;

        // No bigbluebuttonbn_logs rows are written. Insert scraped engagement directly.
        $DB->insert_record('local_unifiedgrader_bbbeng', (object) [
            'cmid' => $cmid, 'recordingid' => 'rec-1', 'userid' => $userid,
            'fullname' => 'Test One', 'duration' => 900, 'talks' => 45,
            'chats' => 5, 'raisehand' => 2, 'polls' => 1, 'emojis' => 4,
            'timefetched' => time(),
        ]);

        $this->setUser($scenario->teacher);
        $adapter = \local_unifiedgrader\adapter\adapter_factory::create($cmid);

        // Participants: scraped attendance counts as submitted.
        $participants = $adapter->get_participants();
        $entry = array_values(array_filter($participants, fn($p) => $p['id'] == $userid));
        $this->assertCount(1, $entry);
        $this->assertEquals('submitted', $entry[0]['status']);

        // Submission data: hascontent + engagement totals match the scraped row.
        $data = $adapter->get_submission_data($userid);
        $this->assertEquals('submitted', $data['status']);
        $this->assertTrue($data['hascontent']);
        $this->assertStringContainsString('Activity Points', $data['content']);
    }
}
