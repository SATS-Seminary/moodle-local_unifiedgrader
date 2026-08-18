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
        $plugingen->create_bbb_summary_log(
            $s->scenario->activity,
            $student->id,
            ['chats' => 2, 'talks' => 5],
            600,
            1000,
        );
        $plugingen->create_bbb_summary_log(
            $s->scenario->activity,
            $student->id,
            ['chats' => 4, 'talks' => 8],
            1200,
            2000,
        );

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
     * Build an adapter whose recording list is stubbed with synthetic entries.
     *
     * This is the only way to exercise the multi-recording switcher in PHPUnit:
     * real recordings are fetched from the (mock) BBB server, which the test
     * harness can't reach, so recording::get_recordings_for_instance() always
     * comes back empty here. We stub bbb_adapter::get_recordings_for_user()
     * instead and let the rest of get_submission_data() run for real.
     *
     * @param \stdClass $scenario The grading scenario (cm, context, course).
     * @param array $recordings Synthetic recording rows to return.
     * @return bbb_adapter
     */
    private function adapter_with_recordings(\stdClass $scenario, array $recordings): bbb_adapter {
        $adapter = $this->getMockBuilder(bbb_adapter::class)
            ->setConstructorArgs([$scenario->cm, $scenario->context, $scenario->course])
            ->onlyMethods(['get_recordings_for_user'])
            ->getMock();
        $adapter->method('get_recordings_for_user')->willReturn($recordings);
        return $adapter;
    }

    /**
     * Build one synthetic recording row shaped like get_recordings_for_user() output.
     *
     * @param string $ref The BBB internal recording id (data-recordingref / overlay id).
     * @param string $url The playback URL that becomes activerecordingurl when selected.
     * @param string $label The session label shown on the pill.
     * @param int $id The Moodle recording entity id.
     * @return array
     */
    private function fake_recording(string $ref, string $url, string $label, int $id): array {
        return [
            'recordingid' => $id,
            'bbbrecordingid' => $ref,
            'name' => $label,
            'playbackurl' => $url,
            'statisticsurl' => '',
            'hasstatisticsurl' => false,
            'starttime' => 1000,
            'endtime' => 2000,
            'groupid' => 0,
            'sessionlabel' => $label,
        ];
    }

    /**
     * Assert whether the switcher pill carrying $attr="$value" is rendered active.
     *
     * The template puts the `active` class first in the <button> tag (in `class`),
     * ahead of the data-* attributes, so an active pill matches
     * `<button …active…$attr="$value"…>` within a single tag.
     *
     * @param string $content Rendered submission HTML.
     * @param string $attr The identifying attribute name (e.g. data-recordingref).
     * @param string $value The identifying attribute value.
     * @param bool $active Whether the pill is expected to be active.
     */
    private function assert_pill_active(string $content, string $attr, string $value, bool $active): void {
        $re = '/<button\b[^>]*\bactive\b[^>]*' . preg_quote($attr . '="' . $value . '"', '/') . '/s';
        $this->assertSame(
            $active ? 1 : 0,
            preg_match($re, $content),
            "Pill {$attr}=\"{$value}\" active state should be " . ($active ? 'true' : 'false'),
        );
    }

    /**
     * Regression: get_submission_data() must pin the player + annotation overlay
     * to the recording named by the ?recordingid switcher param.
     *
     * Before the switcher fix this param was ignored, so the overlay always fell
     * back to the first recording — which is why students (and graders) could not
     * see feedback on any recording but the earliest.
     */
    public function test_get_submission_data_pins_selection_to_recordingid_param(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $recordings = [
            $this->fake_recording('rec-alpha', 'https://bbb.test/play/ALPHA', 'Session A', 101),
            $this->fake_recording('rec-bravo', 'https://bbb.test/play/BRAVO', 'Session B', 102),
        ];
        $adapter = $this->adapter_with_recordings($s->scenario, $recordings);

        // The grader/student picked the SECOND recording; the switcher reload
        // carries its id as ?recordingid.
        $_POST['recordingid'] = 'rec-bravo';

        $content = $adapter->get_submission_data($s->scenario->students[0]->id)['content'];

        // The activerecordingurl (the "Open in new tab" href, rendered on both the
        // overlay and iframe paths) is the chosen recording's URL — the same id
        // handed to bbbext_advgrd_render_overlay(), so this proves both the player
        // and the annotation overlay pivot to the selection.
        $this->assertStringContainsString('href="https://bbb.test/play/BRAVO"', $content);
        $this->assertStringNotContainsString('href="https://bbb.test/play/ALPHA"', $content);

        // The second pill is highlighted; the first pill and "All sessions" are not.
        $this->assert_pill_active($content, 'data-recordingref', 'rec-bravo', true);
        $this->assert_pill_active($content, 'data-recordingref', 'rec-alpha', false);
        $this->assert_pill_active($content, 'data-action', 'bbb-show-aggregate', false);
    }

    /**
     * With no ?recordingid param, get_submission_data() defaults to the aggregate
     * "All sessions" view with the first recording loaded in the player.
     */
    public function test_get_submission_data_defaults_to_all_sessions(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $recordings = [
            $this->fake_recording('rec-alpha', 'https://bbb.test/play/ALPHA', 'Session A', 101),
            $this->fake_recording('rec-bravo', 'https://bbb.test/play/BRAVO', 'Session B', 102),
        ];
        $adapter = $this->adapter_with_recordings($s->scenario, $recordings);

        // No recordingid param set.
        $content = $adapter->get_submission_data($s->scenario->students[0]->id)['content'];

        // Player defaults to the first recording.
        $this->assertStringContainsString('href="https://bbb.test/play/ALPHA"', $content);
        // The "All sessions" pill is active; no individual recording is highlighted.
        $this->assert_pill_active($content, 'data-action', 'bbb-show-aggregate', true);
        $this->assert_pill_active($content, 'data-recordingref', 'rec-alpha', false);
        $this->assert_pill_active($content, 'data-recordingref', 'rec-bravo', false);
    }

    /**
     * An unrecognised ?recordingid (e.g. a recording since deleted) falls back to
     * the aggregate view rather than erroring or blanking the player.
     */
    public function test_get_submission_data_unknown_recordingid_falls_back(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $recordings = [
            $this->fake_recording('rec-alpha', 'https://bbb.test/play/ALPHA', 'Session A', 101),
            $this->fake_recording('rec-bravo', 'https://bbb.test/play/BRAVO', 'Session B', 102),
        ];
        $adapter = $this->adapter_with_recordings($s->scenario, $recordings);

        $_POST['recordingid'] = 'rec-deleted-999';

        $content = $adapter->get_submission_data($s->scenario->students[0]->id)['content'];

        // Falls back to the first recording + the "All sessions" aggregate pill.
        $this->assertStringContainsString('href="https://bbb.test/play/ALPHA"', $content);
        $this->assert_pill_active($content, 'data-action', 'bbb-show-aggregate', true);
        $this->assert_pill_active($content, 'data-recordingref', 'rec-bravo', false);
    }

    /**
     * Build an adapter with both the recording list and the feedback-recording-id
     * lookup stubbed.
     *
     * @param \stdClass $scenario
     * @param array $recordings Synthetic recording rows.
     * @param array $feedbackids Recording ids the target user has feedback on.
     * @return bbb_adapter
     */
    private function adapter_with_feedback(\stdClass $scenario, array $recordings, array $feedbackids): bbb_adapter {
        $adapter = $this->getMockBuilder(bbb_adapter::class)
            ->setConstructorArgs([$scenario->cm, $scenario->context, $scenario->course])
            ->onlyMethods(['get_recordings_for_user', 'get_feedback_recording_ids'])
            ->getMock();
        $adapter->method('get_recordings_for_user')->willReturn($recordings);
        $adapter->method('get_feedback_recording_ids')->willReturn($feedbackids);
        return $adapter;
    }

    /**
     * Regression: a student viewing their OWN feedback (no ?recordingid) must land
     * on the recording that carries their feedback, not the aggregate / first
     * recording. This is the separate-groups case — BBB hides the fed-back
     * recording from the student, so it's surfaced by feedback id and made the
     * default so their comments actually show.
     */
    public function test_get_submission_data_student_lands_on_feedback_recording(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $student = $s->scenario->students[0];
        $recordings = [
            $this->fake_recording('rec-alpha', 'https://bbb.test/play/ALPHA', 'Session A', 101),
            $this->fake_recording('rec-bravo', 'https://bbb.test/play/BRAVO', 'Session B', 102),
        ];
        // Their feedback lives on the SECOND recording only.
        $adapter = $this->adapter_with_feedback($s->scenario, $recordings, ['rec-bravo']);

        // Student is the current user viewing their own feedback; no recordingid.
        $this->setUser($student);
        $content = $adapter->get_submission_data($student->id)['content'];

        // Player + overlay default to the fed-back recording, and its pill is lit.
        $this->assertStringContainsString('href="https://bbb.test/play/BRAVO"', $content);
        $this->assert_pill_active($content, 'data-recordingref', 'rec-bravo', true);
        $this->assert_pill_active($content, 'data-action', 'bbb-show-aggregate', false);
    }

    /**
     * A grader viewing a student's submission is NOT forced onto the feedback
     * recording — they keep the aggregate default and can still pick any pill.
     * (The auto-land is scoped to a viewer looking at their own feedback.)
     */
    public function test_get_submission_data_grader_keeps_aggregate_default(): void {
        $this->resetAfterTest();

        // The create_scenario() helper sets the current user to the teacher.
        $s = $this->create_scenario();
        $student = $s->scenario->students[0];
        $recordings = [
            $this->fake_recording('rec-alpha', 'https://bbb.test/play/ALPHA', 'Session A', 101),
            $this->fake_recording('rec-bravo', 'https://bbb.test/play/BRAVO', 'Session B', 102),
        ];
        $adapter = $this->adapter_with_feedback($s->scenario, $recordings, ['rec-bravo']);

        // Teacher (current user) viewing the student's feedback.
        $content = $adapter->get_submission_data($student->id)['content'];

        // Aggregate default: first recording in the player, "All sessions" lit.
        $this->assertStringContainsString('href="https://bbb.test/play/ALPHA"', $content);
        $this->assert_pill_active($content, 'data-action', 'bbb-show-aggregate', true);
        $this->assert_pill_active($content, 'data-recordingref', 'rec-bravo', false);
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
        $plugingen->create_bbb_summary_log(
            $s->scenario->activity,
            $student->id,
            ['chats' => 2, 'talks' => 5, 'raisehand' => 1, 'pollvotes' => 0, 'emojis' => 3],
            1200,
        );
        $plugingen->create_bbb_summary_log(
            $s->scenario->activity,
            $student->id,
            ['chats' => 4, 'talks' => 8, 'raisehand' => 2, 'pollvotes' => 1, 'emojis' => 0],
            1800,
        );
        $plugingen->create_bbb_summary_log(
            $s->scenario->activity,
            $student->id,
            ['chats' => 1, 'talks' => 0, 'raisehand' => 0, 'pollvotes' => 2, 'emojis' => 5],
            600,
        );

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
        $instancesbefore = $DB->count_records_select(
            'grading_instances',
            'definitionid = :did AND raterid > 0 AND itemid = :uid',
            ['did' => $definition->id, 'uid' => $student->id],
        );

        $payload2 = ['criteria' => []];
        foreach ($definition->guide_criteria as $cid => $crit) {
            $payload2['criteria'][(int) $cid] = ['score' => '2', 'remark' => 'better'];
        }
        $s->adapter->save_grade($student->id, null, '', FORMAT_HTML, $payload2);

        $instancesafter = $DB->count_records_select(
            'grading_instances',
            'definitionid = :did AND raterid > 0 AND itemid = :uid',
            ['did' => $definition->id, 'uid' => $student->id],
        );
        $this->assertEquals(
            $instancesbefore,
            $instancesafter,
            'No new grading instance should be created on subsequent save',
        );

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

    /**
     * Two rostered sessions, the student was in one: only that one is offered.
     *
     * Attendance is the submission on BBB, so the pane must show the sessions
     * this student was in and not the whole activity's.
     */
    public function test_recordings_filtered_to_sessions_the_student_attended(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $target = $s->scenario->students[0];
        $other = $s->scenario->students[1];

        // Both sessions have a roster: another student was summarised on each.
        $gen->create_bbb_summary_log($s->scenario->activity, $other->id, [], 1800, null, 'rec-1');
        $gen->create_bbb_summary_log($s->scenario->activity, $other->id, [], 1800, null, 'rec-2');
        // The student under review only attended the first.
        $gen->create_bbb_summary_log($s->scenario->activity, $target->id, [], 1800, null, 'rec-1');

        $adapter = $this->adapter_with_recordings($s->scenario, [
            $this->fake_recording('rec-1', 'https://bbb.example.com/play/alpha', 'Session A', 1),
            $this->fake_recording('rec-2', 'https://bbb.example.com/play/bravo', 'Session B', 2),
        ]);
        $content = $adapter->get_submission_data($target->id)['content'];

        $this->assertStringContainsString('play/alpha', $content);
        $this->assertStringNotContainsString('play/bravo', $content);
    }

    /**
     * Guard 1: a session nobody has attendance data for stays visible.
     *
     * Recordings predating the analytics callback have no roster at all, so no
     * one can be shown to have missed them. Judged per recording — the presence
     * of a roster on one session must not imply absence from another.
     */
    public function test_recording_without_any_roster_is_still_offered(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $target = $s->scenario->students[0];
        $other = $s->scenario->students[1];

        // Only rec-1 was ever summarised; rec-2 has no roster from anybody.
        $gen->create_bbb_summary_log($s->scenario->activity, $other->id, [], 1800, null, 'rec-1');
        $gen->create_bbb_summary_log($s->scenario->activity, $target->id, [], 1800, null, 'rec-1');

        $adapter = $this->adapter_with_recordings($s->scenario, [
            $this->fake_recording('rec-1', 'https://bbb.example.com/play/alpha', 'Session A', 1),
            $this->fake_recording('rec-2', 'https://bbb.example.com/play/bravo', 'Session B', 2),
        ]);
        $content = $adapter->get_submission_data($target->id)['content'];

        $this->assertStringContainsString('play/alpha', $content);
        $this->assertStringContainsString('play/bravo', $content);
    }

    /**
     * Guard 2: a join log with no summary keeps every session on offer.
     *
     * EVENT_JOIN proves attendance but carries no recording id, so the sessions
     * cannot be attributed and hiding any of them would be a guess.
     */
    public function test_join_log_without_summary_keeps_all_recordings(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $target = $s->scenario->students[0];
        $other = $s->scenario->students[1];

        // Both sessions rostered, but this student only has an unattributable join.
        $gen->create_bbb_summary_log($s->scenario->activity, $other->id, [], 1800, null, 'rec-1');
        $gen->create_bbb_summary_log($s->scenario->activity, $other->id, [], 1800, null, 'rec-2');
        $gen->create_bbb_join_log($s->scenario->activity, $target->id);

        $adapter = $this->adapter_with_recordings($s->scenario, [
            $this->fake_recording('rec-1', 'https://bbb.example.com/play/alpha', 'Session A', 1),
            $this->fake_recording('rec-2', 'https://bbb.example.com/play/bravo', 'Session B', 2),
        ]);
        $content = $adapter->get_submission_data($target->id)['content'];

        $this->assertStringContainsString('play/alpha', $content);
        $this->assertStringContainsString('play/bravo', $content);
        $this->assertStringContainsString('without saying which session', $content);
    }

    /**
     * A student on no roster gets no sessions and is told so, not left with an
     * empty pane — and counts as a non-submitter.
     */
    public function test_student_on_no_roster_gets_did_not_attend(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $target = $s->scenario->students[0];
        $other = $s->scenario->students[1];

        $gen->create_bbb_summary_log($s->scenario->activity, $other->id, [], 1800, null, 'rec-1');
        $gen->create_bbb_summary_log($s->scenario->activity, $other->id, [], 1800, null, 'rec-2');

        $adapter = $this->adapter_with_recordings($s->scenario, [
            $this->fake_recording('rec-1', 'https://bbb.example.com/play/alpha', 'Session A', 1),
            $this->fake_recording('rec-2', 'https://bbb.example.com/play/bravo', 'Session B', 2),
        ]);
        $data = $adapter->get_submission_data($target->id);

        $this->assertStringNotContainsString('play/alpha', $data['content']);
        $this->assertStringNotContainsString('play/bravo', $data['content']);
        $this->assertStringContainsString('Did not attend', $data['content']);
        $this->assertStringContainsString('was not in any of them', $data['content']);
        $this->assertEquals('nosubmission', $data['status']);
    }

    /**
     * Guard 3: attendance that cannot be reconciled with any recording must not
     * read as absence.
     *
     * If the session ids and the recording ids fail to line up, every recording
     * looks rostered-but-unattended and the student loses the lot -- even though
     * we hold positive evidence they were there. Show the sessions instead.
     */
    public function test_unreconcilable_attendance_keeps_recordings(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $target = $s->scenario->students[0];
        $other = $s->scenario->students[1];

        // Both recordings carry rosters, so guard 1 cannot save them...
        $gen->create_bbb_summary_log($s->scenario->activity, $other->id, [], 1800, null, 'rec-1');
        $gen->create_bbb_summary_log($s->scenario->activity, $other->id, [], 1800, null, 'rec-2');
        // ...and this student attended, but under an id matching no recording.
        $gen->create_bbb_summary_log($s->scenario->activity, $target->id, [], 1800, null, 'some-other-id');

        $adapter = $this->adapter_with_recordings($s->scenario, [
            $this->fake_recording('rec-1', 'https://bbb.example.com/play/alpha', 'Session A', 1),
            $this->fake_recording('rec-2', 'https://bbb.example.com/play/bravo', 'Session B', 2),
        ]);
        $data = $adapter->get_submission_data($target->id);

        $this->assertStringContainsString('play/alpha', $data['content']);
        $this->assertStringContainsString('play/bravo', $data['content']);
        $this->assertStringNotContainsString('Did not attend', $data['content']);
        // The pane must say why it stood down, or an unfiltered list is
        // indistinguishable from a filter that never ran.
        $this->assertStringContainsString('does not correspond to any of the recordings', $data['content']);
    }

    /**
     * Attendance cached against a recording counts, even when the summary logs
     * point somewhere else entirely.
     *
     * This is the production shape: a site with the analytics callback on *and*
     * refreshed attendance. Rosters get built from the cached rows, which are
     * stamped with the recording id as the refresh walks each recording, while
     * the logs carry a `recordid` that does not correspond to any of them. Read
     * only the logs and the student matches nothing, so every recording reads as
     * attended-by-nobody and the whole list is hidden.
     */
    public function test_cached_attendance_counts_when_summary_logs_disagree(): void {
        global $DB;
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $target = $s->scenario->students[0];

        // The logs know this student attended, but under an unusable id.
        $gen->create_bbb_summary_log($s->scenario->activity, $target->id, [], 1800, null, 'internal-meeting-xyz');

        // The refresh recorded the same attendance against the real recording,
        // and gave the other recording a roster this student is absent from.
        foreach ([['rec-1', $target->id], ['rec-2', (int) $s->scenario->students[1]->id]] as [$recid, $userid]) {
            $DB->insert_record('local_unifiedgrader_bbbeng', (object) [
                'cmid' => $s->scenario->cm->id,
                'recordingid' => $recid,
                'userid' => $userid,
                'fullname' => 'Attendee',
                'duration' => 1800,
                'talks' => 0, 'chats' => 0, 'raisehand' => 0, 'polls' => 0, 'emojis' => 0,
                'timefetched' => time(),
            ]);
        }

        $adapter = $this->adapter_with_recordings($s->scenario, [
            $this->fake_recording('rec-1', 'https://bbb.example.com/play/alpha', 'Session A', 1),
            $this->fake_recording('rec-2', 'https://bbb.example.com/play/bravo', 'Session B', 2),
        ]);
        $content = $adapter->get_submission_data($target->id)['content'];

        $this->assertStringContainsString('play/alpha', $content, 'The session they attended must survive');
        $this->assertStringNotContainsString('play/bravo', $content, 'The session they missed must be filtered out');
        $this->assertStringNotContainsString('Showing every session', $content, 'No guard should have fired');
    }

    /**
     * The player must load the session the student attended, not the first one
     * on the activity.
     *
     * A student with a single attended session renders no switcher pills, so the
     * recording chosen for them on first paint is the only one they will ever
     * see. Picking it by position rather than by attendance means the teacher
     * marks the wrong video with nothing on screen to suggest it.
     */
    public function test_player_loads_the_attended_session_not_the_first(): void {
        global $DB;
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $target = $s->scenario->students[0];
        $other = $s->scenario->students[1];

        // Rosters on both, and this student was only in the later one.
        $gen->create_bbb_summary_log($s->scenario->activity, $other->id, [], 1800, null, 'rec-early');
        $gen->create_bbb_summary_log($s->scenario->activity, $target->id, [], 1800, null, 'rec-late');

        $adapter = $this->adapter_with_recordings($s->scenario, [
            $this->fake_recording('rec-early', 'https://bbb.example.com/play/early', 'Session 1', 1),
            $this->fake_recording('rec-late', 'https://bbb.example.com/play/late', 'Session 2', 2),
        ]);
        $content = $adapter->get_submission_data($target->id)['content'];

        // One session survives, so no pills are drawn — which is exactly why the
        // recording the player opens with has to be the right one.
        $this->assertStringNotContainsString('data-region="bbb-recording-switcher"', $content);
        $this->assertStringContainsString('play/late', $content);
        $this->assertStringNotContainsString('play/early', $content);
    }

    /**
     * Two attended sessions open on the longer one, not on the aggregate.
     *
     * A student who dropped into a session for ten minutes by mistake and did
     * the work in another should be marked against the second. "All sessions"
     * loads whichever recording sorts first, so defaulting to it puts the wrong
     * video in front of the teacher.
     */
    public function test_multiple_sessions_open_on_the_longest_attended(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $target = $s->scenario->students[0];

        // Ten minutes in the first session, nearly two hours in the second.
        $gen->create_bbb_summary_log($s->scenario->activity, $target->id, [], 600, null, 'rec-early');
        $gen->create_bbb_summary_log($s->scenario->activity, $target->id, [], 6480, null, 'rec-late');

        $adapter = $this->adapter_with_recordings($s->scenario, [
            $this->fake_recording('rec-early', 'https://bbb.example.com/play/early', 'Session 1', 1),
            $this->fake_recording('rec-late', 'https://bbb.example.com/play/late', 'Session 2', 2),
        ]);
        $content = $adapter->get_submission_data($target->id)['content'];

        // Both sessions stay reachable...
        $this->assertStringContainsString('data-region="bbb-recording-switcher"', $content);
        // ...but the long one is selected, and it is what the player loads.
        $this->assert_pill_active($content, 'data-recordingref', 'rec-late', true);
        $this->assert_pill_active($content, 'data-recordingref', 'rec-early', false);
        $this->assertStringContainsString('src="https://bbb.example.com/play/late"', $content);
    }
}
