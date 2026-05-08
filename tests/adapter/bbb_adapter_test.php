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
 * Tests for the bbb_adapter class.
 *
 * Skipped when mod_bigbluebuttonbn is not installed.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\adapter\bbb_adapter
 */
final class bbb_adapter_test extends \advanced_testcase {
    /**
     * Skip the entire suite when BBB is not installed in the test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        if (!class_exists('\mod_bigbluebuttonbn\instance')) {
            $this->markTestSkipped('mod_bigbluebuttonbn not installed');
        }
    }

    /**
     * Helper: create a BBB scenario and return the adapter + scenario.
     *
     * @param array $options Scenario options.
     * @return object{adapter: bbb_adapter, scenario: \stdClass}
     */
    private function create_scenario(array $options = []): object {
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('bigbluebuttonbn', $options);
        $this->setUser($scenario->teacher);
        $adapter = adapter_factory::create($scenario->cm->id);
        return (object) ['adapter' => $adapter, 'scenario' => $scenario];
    }

    /**
     * Test get_activity_info returns correct basic info.
     */
    public function test_get_activity_info_basic(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $info = $s->adapter->get_activity_info();

        $this->assertEquals($s->scenario->cm->id, $info['id']);
        $this->assertEquals('bigbluebuttonbn', $info['type']);
        $this->assertEquals(100.0, $info['maxgrade']);
        $this->assertEquals('simple', $info['gradingmethod']);
        $this->assertFalse($info['gradingdisabled']);
        $this->assertArrayHasKey('hasactivitypointsplugin', $info);
        // Until the future rubric plugin lands this should always be false.
        $this->assertFalse($info['hasactivitypointsplugin']);
    }

    /**
     * Test get_participants marks all users as nosubmission when nobody attended.
     */
    public function test_get_participants_no_attendance(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $participants = $s->adapter->get_participants();

        $this->assertCount(3, $participants);
        foreach ($participants as $p) {
            $this->assertEquals('nosubmission', $p['status']);
            $this->assertEquals(0, $p['submittedat']);
        }
    }

    /**
     * Test get_participants marks attendees as submitted with the correct submittedat.
     */
    public function test_get_participants_with_attendance(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $this->create_scenario();

        $now = time();
        $plugingen->create_bbb_summary_log(
            $s->scenario->activity,
            $s->scenario->students[0]->id,
            ['chats' => 4, 'talks' => 12],
            1800,
            $now - 100,
        );

        $participants = $s->adapter->get_participants();
        $attended = array_values(array_filter($participants, fn($p) => $p['id'] == $s->scenario->students[0]->id));
        $notattended = array_values(array_filter($participants, fn($p) => $p['id'] == $s->scenario->students[1]->id));

        $this->assertCount(1, $attended);
        $this->assertEquals('submitted', $attended[0]['status']);
        $this->assertGreaterThan(0, $attended[0]['submittedat']);

        $this->assertCount(1, $notattended);
        $this->assertEquals('nosubmission', $notattended[0]['status']);
    }

    /**
     * Test that get_submission_data exposes a per-session breakdown alongside
     * the aggregated totals, so the marking pane can pivot the Activity Points
     * card when the teacher selects a recording in the switcher.
     */
    public function test_get_submission_data_per_session_breakdown(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $this->create_scenario();
        $student = $s->scenario->students[0];

        // Two sessions with different metrics — explicit timestamps so order is deterministic.
        $plugingen->create_bbb_summary_log($s->scenario->activity, $student->id,
            ['chats' => 2, 'talks' => 5], 600, 1000);
        $plugingen->create_bbb_summary_log($s->scenario->activity, $student->id,
            ['chats' => 4, 'talks' => 8], 1200, 2000);

        $data = $s->adapter->get_submission_data($student->id);

        // The rendered content should include both per-session blocks marked
        // up with data-region="bbb-tiles-session", plus the aggregate block.
        $this->assertStringContainsString('data-region="bbb-tiles-aggregate"', $data['content']);
        $this->assertEquals(
            2,
            substr_count($data['content'], 'data-region="bbb-tiles-session"'),
            'Expected one per-session tile block per Summary log',
        );

        // The "All sessions" pill is rendered when there are multiple recordings.
        // Here there are 0 recordings (no remote BBB metadata in tests), but the
        // sessioncount should still reflect both Summary logs.
        $this->assertStringContainsString('Activity Points', $data['content']);
    }

    /**
     * Test get_submission_data aggregates engagement metrics across multiple sessions.
     */
    public function test_get_submission_data_aggregates_metrics(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $this->create_scenario();
        $student = $s->scenario->students[0];

        // Three sessions with overlapping engagement.
        $plugingen->create_bbb_summary_log($s->scenario->activity, $student->id,
            ['chats' => 2, 'talks' => 5, 'raisehand' => 1, 'pollvotes' => 0, 'emojis' => 3], 1200);
        $plugingen->create_bbb_summary_log($s->scenario->activity, $student->id,
            ['chats' => 4, 'talks' => 8, 'raisehand' => 2, 'pollvotes' => 1, 'emojis' => 0], 1800);
        $plugingen->create_bbb_summary_log($s->scenario->activity, $student->id,
            ['chats' => 1, 'talks' => 0, 'raisehand' => 0, 'pollvotes' => 2, 'emojis' => 5], 600);

        $data = $s->adapter->get_submission_data($student->id);

        $this->assertEquals('submitted', $data['status']);
        $this->assertTrue($data['hascontent']);
        $this->assertNotEmpty($data['content']);
        // Content includes the aggregated totals — sanity-check via keyword presence.
        $this->assertStringContainsString('Activity Points', $data['content']);
    }

    /**
     * Test get_participants treats join-only attendance (no Summary) as submitted.
     */
    public function test_get_participants_join_only_marks_submitted(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $this->create_scenario();

        // Student 0 joined but no Summary callback fired (typical when BBB
        // server's analytics callback isn't wired in dev environments).
        $plugingen->create_bbb_join_log($s->scenario->activity, $s->scenario->students[0]->id);

        $participants = $s->adapter->get_participants();
        $joined = array_values(array_filter($participants, fn($p) => $p['id'] == $s->scenario->students[0]->id));

        $this->assertCount(1, $joined);
        $this->assertEquals('submitted', $joined[0]['status']);
        $this->assertGreaterThan(0, $joined[0]['submittedat']);
    }

    /**
     * Test get_submission_data returns hascontent=true when student joined but no Summary log exists.
     */
    public function test_get_submission_data_join_only(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $this->create_scenario();

        $plugingen->create_bbb_join_log($s->scenario->activity, $s->scenario->students[0]->id);

        $data = $s->adapter->get_submission_data($s->scenario->students[0]->id);
        $this->assertEquals('submitted', $data['status']);
        $this->assertTrue($data['hascontent']);
        // Engagement-pending message should appear when joined but no Summary.
        $this->assertStringContainsString('engagement', strtolower($data['content']));
    }

    /**
     * Test get_submission_data returns hascontent=false for non-attendees with no recordings.
     */
    public function test_get_submission_data_didnotattend(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $data = $s->adapter->get_submission_data($s->scenario->students[0]->id);

        $this->assertEquals('nosubmission', $data['status']);
        $this->assertFalse($data['hascontent']);
    }

    /**
     * Test save_grade writes to the gradebook.
     */
    public function test_save_grade_writes_to_gradebook(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $student = $s->scenario->students[0];

        $ok = $s->adapter->save_grade($student->id, 85.0, 'Great participation', FORMAT_HTML);
        $this->assertTrue($ok);

        $data = $s->adapter->get_grade_data($student->id);
        $this->assertEquals(85.0, $data['grade']);
        $this->assertStringContainsString('Great participation', $data['feedback']);
    }

    /**
     * Test supports_feature reflects the rubric plugin gate.
     */
    public function test_supports_feature_rubric_gate(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        // The future gradingform_bbbactivitypoints plugin is not installed in tests,
        // so rubric support must be reported as false.
        $this->assertFalse($s->adapter->supports_feature('rubric'));
        $this->assertTrue($s->adapter->supports_feature('onlinetext'));
        $this->assertFalse($s->adapter->supports_feature('annotations'));
    }

    /**
     * Test that the advgrd extension flips the activity to rubric grading.
     */
    public function test_advgrd_rubric_method_surfaces_in_activity_info(): void {
        if (!class_exists('\\bbbext_advgrd\\local\\grader')) {
            $this->markTestSkipped('bbbext_advgrd not installed');
        }
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $advgrdgen = $this->getDataGenerator()->get_plugin_generator('bbbext_advgrd');
        $advgrdgen->create_config((int) $s->scenario->activity->id, ['gradingmethod' => 'rubric']);
        $advgrdgen->import_template((int) $s->scenario->activity->id, 'coi');

        $info = $s->adapter->get_activity_info();
        $this->assertEquals('rubric', $info['gradingmethod']);
        $this->assertTrue($info['hasactivitypointsplugin']);
        $this->assertTrue($s->adapter->supports_feature('rubric'));
        $this->assertFalse($s->adapter->supports_feature('markingguide'));
    }

    /**
     * Test get_grading_definition serialises the imported rubric for the marking pane.
     */
    public function test_advgrd_grading_definition_returned(): void {
        if (!class_exists('\\bbbext_advgrd\\local\\grader')) {
            $this->markTestSkipped('bbbext_advgrd not installed');
        }
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $advgrdgen = $this->getDataGenerator()->get_plugin_generator('bbbext_advgrd');
        $advgrdgen->create_config((int) $s->scenario->activity->id, ['gradingmethod' => 'rubric']);
        $advgrdgen->import_template((int) $s->scenario->activity->id, 'coi');

        $definition = $s->adapter->get_grading_definition();
        $this->assertIsArray($definition);
        $this->assertEquals('rubric', $definition['method']);
        $this->assertEquals('bbbext_advgrd/participation', $definition['area']);
        $this->assertNotEmpty($definition['criteria']);
        // Each criterion has at least one level.
        foreach ($definition['criteria'] as $criterion) {
            $this->assertNotEmpty($criterion['levels']);
        }
    }

    /**
     * Test that get_grade_data exposes the rubric definition alongside grade fields.
     */
    public function test_advgrd_grade_data_includes_definition(): void {
        if (!class_exists('\\bbbext_advgrd\\local\\grader')) {
            $this->markTestSkipped('bbbext_advgrd not installed');
        }
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $advgrdgen = $this->getDataGenerator()->get_plugin_generator('bbbext_advgrd');
        $advgrdgen->create_config((int) $s->scenario->activity->id, ['gradingmethod' => 'guide']);
        $advgrdgen->import_template((int) $s->scenario->activity->id, 'coi');

        $data = $s->adapter->get_grade_data($s->scenario->students[0]->id);
        $this->assertNotEmpty($data['gradingdefinition']);
        $decoded = json_decode($data['gradingdefinition'], true);
        $this->assertEquals('guide', $decoded['method']);
        // No rubric fill yet — student hasn't been graded.
        $this->assertSame('', $data['rubricdata']);
    }

    /**
     * Regression: save_grade with marking-guide criteria must persist the
     * fillings (not silently clear them).
     *
     * The earlier implementation unwrapped the {'criteria': {...}} payload
     * before calling gradingform_guide_instance::submit_and_get_grade(), which
     * made is_empty_form() return true and led to clear_attempt() being called
     * — wiping the values within the autosave window. This test asserts that
     * a non-empty payload writes a corresponding row into
     * gradingform_guide_fillings.
     */
    public function test_advgrd_guide_save_persists_fillings(): void {
        if (!class_exists('\\bbbext_advgrd\\local\\grader')) {
            $this->markTestSkipped('bbbext_advgrd not installed');
        }
        $this->resetAfterTest();

        global $DB;

        $s = $this->create_scenario();
        $advgrdgen = $this->getDataGenerator()->get_plugin_generator('bbbext_advgrd');
        $advgrdgen->create_config((int) $s->scenario->activity->id, ['gradingmethod' => 'guide']);
        $advgrdgen->import_template((int) $s->scenario->activity->id, 'coi');

        $student = $s->scenario->students[0];

        // Pull criterion ids from the imported guide definition so we can build
        // a realistic payload.
        $context = $s->scenario->context;
        $manager = get_grading_manager($context, 'bbbext_advgrd', 'participation');
        $controller = $manager->get_active_controller();
        $this->assertTrue($controller->is_form_defined());
        $definition = $controller->get_definition();
        $this->assertNotEmpty($definition->guide_criteria);

        // Score every criterion at 1.
        $criteria = [];
        foreach ($definition->guide_criteria as $cid => $crit) {
            $criteria[(int) $cid] = ['score' => '1', 'remark' => 'good'];
        }
        $payload = ['criteria' => $criteria];

        $ok = $s->adapter->save_grade(
            $student->id,
            null,
            '',
            FORMAT_HTML,
            $payload,
        );
        $this->assertTrue($ok);

        // Fillings should now exist for every criterion at score 1 and remark 'good'.
        $fillings = $DB->get_records('gradingform_guide_fillings');
        $this->assertNotEmpty($fillings, 'Expected guide fillings after save');
        foreach ($fillings as $row) {
            $this->assertEquals(1.0, (float) $row->score);
            $this->assertEquals('good', $row->remark);
        }

        // A second save with different scores must update — not orphan — the
        // existing grading instance. Without instance re-use, every save
        // creates a new grading_instances row and the fillings drift.
        $instancesbefore = $DB->count_records_select('grading_instances',
            'definitionid = :did AND raterid > 0 AND itemid = :uid',
            ['did' => $definition->id, 'uid' => $student->id]);

        $payload2 = ['criteria' => []];
        foreach ($definition->guide_criteria as $cid => $crit) {
            $payload2['criteria'][(int) $cid] = ['score' => '2', 'remark' => 'better'];
        }
        $s->adapter->save_grade($student->id, null, '', FORMAT_HTML, $payload2);

        $instancesafter = $DB->count_records_select('grading_instances',
            'definitionid = :did AND raterid > 0 AND itemid = :uid',
            ['did' => $definition->id, 'uid' => $student->id]);
        $this->assertEquals($instancesbefore, $instancesafter, 'No new grading instance should be created on subsequent save');

        $fillings2 = $DB->get_records('gradingform_guide_fillings');
        foreach ($fillings2 as $row) {
            $this->assertEquals(2.0, (float) $row->score);
            $this->assertEquals('better', $row->remark);
        }
    }

    /**
     * Test get_submission_files returns empty (BBB recordings are remote).
     */
    public function test_get_submission_files_empty(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $this->assertSame([], $s->adapter->get_submission_files($s->scenario->students[0]->id));
    }

    /**
     * Test is_grade_released only true after a grade is set and item not hidden.
     */
    public function test_is_grade_released(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $student = $s->scenario->students[0];

        $this->assertFalse($s->adapter->is_grade_released($student->id));

        $s->adapter->save_grade($student->id, 70.0, '', FORMAT_HTML);
        $this->assertTrue($s->adapter->is_grade_released($student->id));
    }
}
