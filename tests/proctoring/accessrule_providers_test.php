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

namespace local_unifiedgrader\proctoring;

use local_unifiedgrader\external\get_proctoring;
use local_unifiedgrader\proctoring\provider\autoproctor;
use local_unifiedgrader\proctoring\provider\proctorlink;
use local_unifiedgrader\proctoring\provider\quilgo;
use mod_quiz\quiz_attempt;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/unifiedgrader/tests/fixtures/proctoring_tables.php');
require_once($CFG->dirroot . '/local/unifiedgrader/tests/fixtures/fake_autoproctor_api.php');

/**
 * Tests for the providers that read a quiz access rule plugin: Quilgo, ProctorLink and AutoProctor.
 *
 * None of those plugins is installed on a test site, so their tables are
 * stood in for (see fixtures/proctoring_tables.php) and their APIs faked.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\proctoring\provider
 * @covers \local_unifiedgrader\proctoring\provider\quilgo
 * @covers \local_unifiedgrader\proctoring\provider\proctorlink
 * @covers \local_unifiedgrader\proctoring\provider\autoproctor
 * @covers \local_unifiedgrader\proctoring\autoproctor_client
 * @covers \local_unifiedgrader\proctoring\service
 * @covers \local_unifiedgrader\external\get_proctoring
 */
final class accessrule_providers_test extends \advanced_testcase {
    /** @var int The last question usage ID handed to a made-up attempt. */
    private int $uniqueid = 900000;

    /**
     * Stand in for the access rule plugins.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        proctoring_tables::create();
        provider::assume_access_rules_for_testing(['quilgo', 'quizproctoring', 'autoproctor']);
        set_config('client_id', 'client', 'quizaccess_autoproctor');
        set_config('client_secret', 'secret', 'quizaccess_autoproctor');
    }

    /**
     * Remove the stand-ins.
     */
    protected function tearDown(): void {
        provider::assume_access_rules_for_testing([]);
        quilgo::use_api_for_testing(null);
        autoproctor::use_api_for_testing(null);
        proctoring_tables::drop();
        parent::tearDown();
    }

    /**
     * A quiz with a teacher and students, with the given services ticked.
     *
     * @param string $services Comma-separated provider keys.
     * @return \stdClass
     */
    private function create_scenario(string $services): \stdClass {
        set_config('proctoring_services', $services, 'local_unifiedgrader');
        $scenario = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader')->create_grading_scenario('quiz');
        $this->setUser($scenario->teacher);
        return $scenario;
    }

    /**
     * Record a quiz attempt without sitting it.
     *
     * @param \stdClass $scenario
     * @param \stdClass $student
     * @param int $number Attempt number.
     * @param string $state Attempt state.
     * @return int The attempt's ID.
     */
    private function attempt(\stdClass $scenario, \stdClass $student, int $number, string $state = quiz_attempt::FINISHED): int {
        global $DB;
        return (int) $DB->insert_record('quiz_attempts', [
            'quiz' => $scenario->activity->id,
            'userid' => $student->id,
            'attempt' => $number,
            'uniqueid' => ++$this->uniqueid,
            'layout' => '',
            'currentpage' => 0,
            'preview' => 0,
            'state' => $state,
            'timestart' => time() - HOURSECS,
            'timefinish' => $state === quiz_attempt::FINISHED ? time() : 0,
            'timemodified' => time(),
        ]);
    }

    /**
     * Quilgo's own confidence level is the colour, attempt by attempt, and the student takes their worst.
     */
    public function test_quilgo_shows_its_own_confidence_level(): void {
        global $DB;
        $scenario = $this->create_scenario('quilgo');
        [$retaker, $clean, $unseen] = $scenario->students;
        proctoring_tables::insert('quizaccess_quilgo_settings', ['quizid' => $scenario->activity->id, 'proctoring_enabled' => 1]);

        $report = fn(int $attemptid, string $session, ?array $stat) => proctoring_tables::insert('quizaccess_quilgo_reports', [
            'attemptid' => $attemptid,
            'plasmsessionid' => $session,
            'generate_status' => $stat ? quilgo::STATUS_READY : 0,
            'stat' => $stat ? json_encode($stat) : null,
            'proctoring_enabled' => 1,
        ]);
        $low = ['trustScore' => 0, 'facesPresence' => 0.42, 'pageUnfocusedCount' => 7];
        $high = ['trustScore' => 100, 'faceMatch' => 1, 'referenceFaceMatch' => true];
        $report($this->attempt($scenario, $retaker, 1), 's1', $low);
        $report($this->attempt($scenario, $retaker, 2), 's2', $high);
        // A real report from a live test: one suspicious pattern, one look away.
        $report($this->attempt($scenario, $clean, 1), 's3', [
            'trustScore' => 50,
            'facesPresence' => 1,
            'maxFacesPerFrame' => 1,
            'pageUnfocusedCount' => 1,
            'patterns' => [['type' => 'question-selection-context-menu-called', 'questionId' => 'question-3885-4']],
        ]);
        // Nobody has opened Quilgo's report page for this one: Quilgo is asked.
        $report($this->attempt($scenario, $unseen, 1), 's4', null);

        $api = new class {
            /** @var string[] What was asked for. */
            public array $asked = [];

            /**
             * Answer like the Quilgo plugin's client.
             *
             * @param string $uuids Comma-separated session IDs.
             * @return array
             */
            public function fetch_multiple_reports(string $uuids): array {
                $this->asked[] = $uuids;
                // The live service sends the stat as a JSON string.
                return [(object) ['uuid' => 's4', 'stat' => json_encode(['trustScore' => 100])]];
            }
        };
        quilgo::use_api_for_testing($api);

        $this->assertSame(quilgo::class, get_class(service::provider_for($scenario->cm)));
        $data = service::load($scenario->cm);

        $this->assertSame(provider::STATE_OK, $data['state']);
        $this->assertSame('Quilgo', $data['provider']);
        $this->assertSame(['s4'], $api->asked);

        $this->assertSame(rule::RED, $data['users'][$retaker->id]['level']);
        $this->assertSame([1, 2], array_keys($data['users'][$retaker->id]['attempts']));
        $this->assertSame(rule::GREEN, $data['users'][$retaker->id]['attempts'][2]['level']);
        $first = $data['users'][$retaker->id]['attempts'][1]['tools'][0];
        $this->assertSame('Low confidence', $first['statuslabel']);
        $this->assertSame(
            ['Face present in 42% of snapshots', 'Left the page 7 times'],
            array_column($first['issues'], 'label'),
        );
        $this->assertSame(
            ['Face matched in 100% of snapshots', 'Identity photo matched'],
            array_column($data['users'][$retaker->id]['attempts'][2]['tools'][0]['issues'], 'label'),
        );

        $this->assertSame(rule::YELLOW, $data['users'][$clean->id]['level']);
        $this->assertSame(
            [
                ['label' => 'Face present in 100% of snapshots', 'count' => 0, 'level' => 'info'],
                ['label' => 'Left the page once', 'count' => 0, 'level' => 'info'],
                ['label' => 'Opened the right-click menu on selected question text', 'count' => 1, 'level' => 'info'],
            ],
            $data['users'][$clean->id]['tools'][0]['issues'],
        );
        $this->assertSame(rule::GREEN, $data['users'][$unseen->id]['level']);

        // Asked once: the answer for the unstored report is cached.
        service::load($scenario->cm);
        $this->assertCount(1, $api->asked);
    }

    /**
     * A Quilgo report that is not ready, or cannot be fetched, is "awaiting review", never a colour.
     */
    public function test_quilgo_report_not_ready(): void {
        global $DB;
        $scenario = $this->create_scenario('quilgo');
        $student = $scenario->students[0];
        proctoring_tables::insert('quizaccess_quilgo_reports', [
            'attemptid' => $this->attempt($scenario, $student, 1),
            'plasmsessionid' => 's1',
            'generate_status' => 0,
            'stat' => null,
            'proctoring_enabled' => 1,
        ]);
        quilgo::use_api_for_testing(new class {
            /**
             * Fail like a client whose request was refused.
             *
             * @param string $uuids
             */
            public function fetch_multiple_reports(string $uuids): array {
                throw new \moodle_exception('error');
            }
        });

        $data = service::load($scenario->cm);
        $this->assertDebuggingCalled();
        $this->assertSame(rule::PENDING, $data['users'][$student->id]['level']);
        $this->assertSame('Report not ready', $data['users'][$student->id]['tools'][0]['statuslabel']);
    }

    /**
     * ProctorLink's warnings are coloured by kind, and an attempt it watched without warnings is green.
     */
    public function test_proctorlink_colours_by_kind_of_warning(): void {
        global $DB;
        $scenario = $this->create_scenario('proctorlink');
        [$flagged, $fidgety, $clean] = $scenario->students;
        $quizid = $scenario->activity->id;
        proctoring_tables::insert('quizaccess_quizproctoring', ['quizid' => $quizid, 'enableproctoring' => 1]);

        $watch = function (\stdClass $student, int $number, array $warnings, int $deleted = 0) use ($scenario, $quizid, $DB): void {
            $attemptid = $this->attempt($scenario, $student, $number);
            $row = ['userid' => $student->id, 'quizid' => $quizid, 'attemptid' => $attemptid, 'deleted' => $deleted];
            proctoring_tables::insert('quizaccess_quizproctoring_ma', $row + ['status' => '']);
            foreach ($warnings as $status) {
                proctoring_tables::insert('quizaccess_quizproctoring_da', $row + ['status' => $status]);
            }
        };
        $watch($flagged, 1, ['nofacedetected', 'multifacesdetected', 'multifacesdetected', '']);
        $watch($fidgety, 1, ['leftmovedetected', 'leftmovedetected', 'leftmovedetected', 'pendingprocessing']);
        $watch($clean, 1, ['']);
        // An attempt whose images were deleted is no longer a record of anything.
        $watch($clean, 2, ['facesnotmatched'], 1);

        $data = service::load($scenario->cm);

        $this->assertSame('ProctorLink', $data['provider']);
        // ProctorLink's own capability guards its report pages, and does not exist on a test site.
        $this->assertSame('', $data['reporturl']);
        $this->assertSame('', $data['users'][$flagged->id]['reporturl']);

        $this->assertSame(rule::RED, $data['users'][$flagged->id]['level']);
        $tool = $data['users'][$flagged->id]['tools'][0];
        $this->assertSame('3 warnings', $tool['statuslabel']);
        $issues = array_column($tool['issues'], null, 'label');
        $this->assertSame(['label' => 'More than one face', 'count' => 2, 'level' => rule::RED], $issues['More than one face']);
        $this->assertSame(rule::YELLOW, $issues['No face detected']['level']);

        $this->assertSame(rule::YELLOW, $data['users'][$fidgety->id]['level']);
        $this->assertTrue($data['users'][$fidgety->id]['provisional']);

        $this->assertSame(rule::GREEN, $data['users'][$clean->id]['level']);
        $this->assertSame('No warnings', $data['users'][$clean->id]['tools'][0]['statuslabel']);
        $this->assertSame([1], array_keys($data['users'][$clean->id]['attempts']));

        // The rule is the site's to change.
        set_config('proctorlink_red', 'leftmovedetected', 'local_unifiedgrader');
        set_config('proctorlink_ignore', 'multifacesdetected', 'local_unifiedgrader');
        $data = service::load($scenario->cm);
        $this->assertSame(rule::RED, $data['users'][$fidgety->id]['level']);
        $this->assertSame(rule::YELLOW, $data['users'][$flagged->id]['level']);
        $this->assertSame('1 warnings', $data['users'][$flagged->id]['tools'][0]['statuslabel']);
    }

    /**
     * AutoProctor's trust score becomes a colour by the site's two thresholds.
     */
    public function test_autoproctor_colours_by_trust_score(): void {
        global $DB;
        $scenario = $this->create_scenario('autoproctor');
        [$trusted, $doubtful, $busy] = $scenario->students;
        $quizid = $scenario->activity->id;
        proctoring_tables::insert('quizaccess_autoproctor', ['quiz_id' => $quizid, 'proctoring_enabled' => 1]);

        $session = fn(int $attemptid, string $id) => proctoring_tables::insert('quizaccess_autoproctor_sessions', [
            'quiz_id' => $quizid,
            'quiz_attempt_id' => $attemptid,
            'test_attempt_id' => $id,
        ]);
        $session($this->attempt($scenario, $trusted, 1), 't1');
        $session($this->attempt($scenario, $doubtful, 1), 'd1');
        $session($this->attempt($scenario, $doubtful, 2), 'd2');
        $session($this->attempt($scenario, $busy, 1), 'b1');
        $session($this->attempt($scenario, $busy, 2, quiz_attempt::IN_PROGRESS), 'b2');

        // The shape the live API answers with: everything under test_attempt.
        $answer = fn(?float $score, ?string $evidence) => ['test_attempt' => [
            'id' => 1,
            'trust_score' => $score,
            'evidence_status' => $evidence,
            'test_attempt_status' => $score === null ? 'started' : 'finished',
        ]];
        $api = new fake_autoproctor_api([
            't1' => $answer(0.93, 'processed'),
            'd1' => $answer(0.61, 'processed'),
            'd2' => $answer(0.23, 'processed'),
            'b1' => $answer(0.99, 'processing'),
        ]);
        autoproctor::use_api_for_testing($api);

        $data = service::load($scenario->cm);

        $this->assertSame('AutoProctor', $data['provider']);
        $this->assertSame(provider::STATE_OK, $data['state']);
        // Only finished attempts are asked about.
        $this->assertSame([['t1', 'd1', 'd2', 'b1']], $api->calls);

        $this->assertSame(rule::GREEN, $data['users'][$trusted->id]['level']);
        $this->assertSame('Trust score 93%', $data['users'][$trusted->id]['tools'][0]['statuslabel']);

        $this->assertSame(rule::RED, $data['users'][$doubtful->id]['level']);
        $this->assertSame(rule::YELLOW, $data['users'][$doubtful->id]['attempts'][1]['level']);
        $this->assertSame('Trust score 23%', $data['users'][$doubtful->id]['tools'][0]['statuslabel']);

        // Evidence still processing, and an attempt still in progress: no colour yet.
        $this->assertSame(rule::PENDING, $data['users'][$busy->id]['level']);
        $this->assertSame('Result not ready', $data['users'][$busy->id]['attempts'][2]['tools'][0]['statuslabel']);

        // Cached, then recoloured by a change of threshold without asking again.
        set_config('autoproctor_green', 95, 'local_unifiedgrader');
        set_config('autoproctor_yellow', 70, 'local_unifiedgrader');
        $data = service::load($scenario->cm);
        $this->assertCount(1, $api->calls);
        $this->assertSame(rule::YELLOW, $data['users'][$trusted->id]['level']);
        $this->assertSame(rule::RED, $data['users'][$doubtful->id]['attempts'][1]['level']);

        service::load($scenario->cm, true);
        $this->assertCount(2, $api->calls);
    }

    /**
     * A student with a clean attempt and another still being processed is awaiting review, not green.
     */
    public function test_a_pending_attempt_keeps_the_student_from_green(): void {
        global $DB;
        $scenario = $this->create_scenario('autoproctor');
        $student = $scenario->students[0];
        foreach (['a1', 'a2'] as $i => $id) {
            proctoring_tables::insert('quizaccess_autoproctor_sessions', [
                'quiz_id' => $scenario->activity->id,
                'quiz_attempt_id' => $this->attempt($scenario, $student, $i + 1),
                'test_attempt_id' => $id,
            ]);
        }
        autoproctor::use_api_for_testing(new fake_autoproctor_api([
            'a1' => ['test_attempt' => ['trust_score' => 0.95, 'evidence_status' => 'processed']],
            'a2' => ['test_attempt' => ['trust_score' => null, 'evidence_status' => null]],
        ]));

        $user = service::load($scenario->cm)['users'][$student->id];
        $this->assertSame(rule::PENDING, $user['level']);
        $this->assertFalse($user['provisional']);
        // The finished attempt keeps its own verdict.
        $this->assertSame(rule::GREEN, $user['attempts'][1]['level']);
    }

    /**
     * When every request to AutoProctor fails, the results are unavailable, not pending.
     */
    public function test_autoproctor_unreachable(): void {
        global $DB;
        $scenario = $this->create_scenario('autoproctor');
        proctoring_tables::insert('quizaccess_autoproctor_sessions', [
            'quiz_id' => $scenario->activity->id,
            'quiz_attempt_id' => $this->attempt($scenario, $scenario->students[0], 1),
            'test_attempt_id' => 't1',
        ]);
        autoproctor::use_api_for_testing(new fake_autoproctor_api([]));

        $data = service::load($scenario->cm);
        $this->assertSame(provider::STATE_UNAVAILABLE, $data['state']);
        $this->assertSame([], $data['users']);

        // An attempt AutoProctor has never heard of is an answer, not a failure.
        autoproctor::use_api_for_testing(new fake_autoproctor_api(['t1' => ['notfound' => true]]));
        $data = service::load($scenario->cm, true);
        $this->assertSame(provider::STATE_OK, $data['state']);
        $this->assertSame(rule::PENDING, $data['users'][$scenario->students[0]->id]['level']);
    }

    /**
     * Attempts the client had no time to ask about are asked about on the next load, and only those.
     */
    public function test_autoproctor_asks_again_only_for_what_is_missing(): void {
        global $DB;
        $scenario = $this->create_scenario('autoproctor');
        foreach (['t1', 't2'] as $i => $id) {
            proctoring_tables::insert('quizaccess_autoproctor_sessions', [
                'quiz_id' => $scenario->activity->id,
                'quiz_attempt_id' => $this->attempt($scenario, $scenario->students[$i], 1),
                'test_attempt_id' => $id,
            ]);
        }
        $api = new class implements autoproctor_api {
            /** @var array[] Each call made. */
            public array $calls = [];

            /**
             * Answer for the first attempt asked about and run out of time for the rest.
             *
             * @param string[] $testattemptids
             * @return array
             */
            public function attempts(array $testattemptids): array {
                $this->calls[] = $testattemptids;
                return [$testattemptids[0] => ['test_attempt' => ['trust_score' => 0.9, 'evidence_status' => 'processed']]];
            }
        };
        autoproctor::use_api_for_testing($api);

        $first = service::load($scenario->cm);
        $this->assertSame(rule::GREEN, $first['users'][$scenario->students[0]->id]['level']);
        $this->assertSame(rule::PENDING, $first['users'][$scenario->students[1]->id]['level']);

        $second = service::load($scenario->cm);
        $this->assertSame([['t1', 't2'], ['t2']], $api->calls);
        $this->assertSame(rule::GREEN, $second['users'][$scenario->students[1]->id]['level']);
    }

    /**
     * A request to AutoProctor names the attempt in the path and signs it in the query.
     *
     * This is the shape the live API accepted on 2026-10-09; with the hash in
     * the path it answers 403.
     */
    public function test_autoproctor_request_is_signed_in_the_query(): void {
        $client = new autoproctor_client('my client', 'secret', 'https://example.test');
        $this->assertSame(
            'https://example.test/api/v2/test-attempts/abc%2F1/?clientId=my+client&hashedTestAttemptId='
                . rawurlencode(base64_encode(hash_hmac('sha256', 'abc/1', 'secret', true))),
            $client->request_url('abc/1'),
        );
    }

    /**
     * A quiz gets the service that is switched on for it, and none when no ticked service is.
     */
    public function test_the_quizs_own_service_is_found(): void {
        global $DB;
        $scenario = $this->create_scenario('quilgo,proctorlink,autoproctor');
        $this->assertNull(service::provider_for($scenario->cm));
        $this->assertSame(provider::STATE_NONE, service::load($scenario->cm)['state']);

        proctoring_tables::insert('quizaccess_autoproctor', ['quiz_id' => $scenario->activity->id, 'proctoring_enabled' => 1]);
        $this->assertSame(autoproctor::class, get_class(service::provider_for($scenario->cm)));

        // Switched on for the quiz, but not ticked for the site.
        set_config('proctoring_services', 'quilgo', 'local_unifiedgrader');
        $this->assertNull(service::provider_for($scenario->cm));

        // Ticked, but the plugin is not there.
        set_config('proctoring_services', 'quilgo', 'local_unifiedgrader');
        provider::assume_access_rules_for_testing([]);
        $this->assertSame(
            \core_component::get_plugin_directory('quizaccess', 'quilgo') === null,
            service::enabled_providers() === [],
        );
    }

    /**
     * The web service hands the grader each attempt's result alongside the student's.
     */
    public function test_webservice_exports_attempts(): void {
        global $DB;
        $scenario = $this->create_scenario('quilgo');
        $student = $scenario->students[0];
        foreach ([1 => 100, 2 => 50] as $number => $score) {
            proctoring_tables::insert('quizaccess_quilgo_reports', [
                'attemptid' => $this->attempt($scenario, $student, $number),
                'plasmsessionid' => 's' . $number,
                'generate_status' => quilgo::STATUS_READY,
                'stat' => json_encode(['trustScore' => $score]),
                'proctoring_enabled' => 1,
            ]);
        }

        $result = \core_external\external_api::clean_returnvalue(
            get_proctoring::execute_returns(),
            get_proctoring::execute($scenario->cm->id),
        );

        $this->assertSame('Quilgo', $result['provider']);
        $user = $result['users'][0];
        $this->assertSame('yellow', $user['level']);
        $this->assertSame('Medium review priority', $user['levellabel']);
        $this->assertSame([1, 2], array_column($user['attempts'], 'attempt'));
        $this->assertSame(['green', 'yellow'], array_column($user['attempts'], 'level'));
        $this->assertSame('High confidence', $user['attempts'][0]['tools'][0]['statuslabel']);
    }
}
