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
use local_unifiedgrader\proctoring\provider\smowl;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/unifiedgrader/tests/fixtures/fake_results_api.php');

/**
 * Tests for SMOWL proctoring results: the provider, and the web service through it.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\proctoring\provider\smowl
 * @covers \local_unifiedgrader\proctoring\service
 * @covers \local_unifiedgrader\external\get_proctoring
 */
final class smowl_test extends \advanced_testcase {
    /**
     * Stop answering from the stand-in client.
     */
    protected function tearDown(): void {
        smowl::use_api_for_testing(null);
        smowl::assume_block_for_testing(null);
        parent::tearDown();
    }

    /**
     * A quiz with a teacher and students, on a site where the SMOWL block is installed and set up.
     *
     * @param bool $withblock Whether the course has the SMOWL block.
     * @return \stdClass
     */
    private function create_scenario(bool $withblock = true): \stdClass {
        global $DB;
        $this->resetAfterTest();
        smowl::assume_block_for_testing(true);
        set_config('proctoring_services', 'smowl', 'local_unifiedgrader');
        // The entity name and key are the block's own settings.
        set_config('entity', 'TESTENTITY', 'block_smowl');
        set_config('apikey', 'secret', 'block_smowl');

        $scenario = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader')->create_grading_scenario('quiz');
        if ($withblock) {
            $DB->insert_record('block_instances', [
                'blockname' => 'smowl',
                'parentcontextid' => \context_course::instance($scenario->course->id)->id,
                'showinsubcontexts' => 0,
                'pagetypepattern' => 'course-view-*',
                'defaultregion' => 'side-pre',
                'defaultweight' => 0,
                'configdata' => '',
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }
        $this->setUser($scenario->teacher);
        return $scenario;
    }

    /**
     * The block's SMOWL panel is linked only where the user holds the block's own capability.
     *
     * A test site need not have the block, in which case the capability does not exist.
     *
     * @param \stdClass $scenario
     * @param string $reporturl The link a load returned.
     */
    private function assert_report_link(\stdClass $scenario, string $reporturl): void {
        if (get_capability_info('block/smowl:manageactivities') === null) {
            $this->assertSame('', $reporturl);
        } else {
            $this->assertStringContainsString('/blocks/smowl/manage.php?id=' . $scenario->course->id, $reporturl);
        }
    }

    /**
     * Each student gets a priority from this quiz's entry, and no other activity's.
     */
    public function test_load_gives_each_student_a_priority(): void {
        $scenario = $this->create_scenario();
        [$clean, $flagged, $absent] = $scenario->students;

        $api = new fake_results_api(200, fake_results_api::body('quiz' . $scenario->activity->id, [
            $clean->id => [
                'FrontCamera' => ['status' => 'SUCCESSFUL', 'issues' => []],
                'IpAnalysis' => ['status' => 'SUCCESSFUL', 'issues' => ['SHARED_IP' => ['warnings_total' => 1]]],
            ],
            $flagged->id => [
                'FrontCamera' => ['status' => 'PENDING', 'pending' => '3', 'issues' => []],
                'ComputerMonitoring' => ['status' => 'UNSUCCESSFUL', 'issues' => [
                    'WEB_NAVIGATION' => ['captures_total' => 93],
                    'OPENED_PROGRAM' => ['captures_total' => '6'],
                ]],
            ],
            // A user ID that is not a Moodle one is dropped.
            'abc' => ['FrontCamera' => ['status' => 'UNSUCCESSFUL', 'issues' => []]],
        ]));

        $data = (new smowl($scenario->cm, $api))->load();

        $this->assertSame([['quiz', (int) $scenario->activity->id]], $api->calls);
        $this->assertSame(provider::STATE_OK, $data['state']);
        $this->assert_report_link($scenario, $data['reporturl']);
        $this->assertSame([(int) $clean->id, (int) $flagged->id], array_keys($data['users']));
        $this->assertArrayNotHasKey($absent->id, $data['users']);

        $this->assertSame(rule::GREEN, $data['users'][$clean->id]['level']);
        $this->assertFalse($data['users'][$clean->id]['provisional']);

        $this->assertSame(rule::RED, $data['users'][$flagged->id]['level']);
        $this->assertTrue($data['users'][$flagged->id]['provisional']);
        $this->assertSame(
            ['label' => 'Opened a program that is not allowed', 'count' => 6, 'level' => rule::RED],
            $data['users'][$flagged->id]['tools'][1]['issues'][1],
        );
        $this->assertSame([], $data['users'][$flagged->id]['attempts']);
    }

    /**
     * SMOWL knows a quiz the way the SMOWL block names it: the module name and the quiz's own ID.
     */
    public function test_activity_reference_is_the_blocks(): void {
        $scenario = $this->create_scenario();
        $this->assertSame(
            ['quiz', (int) $scenario->activity->id],
            (new smowl($scenario->cm, new fake_results_api()))->activity_ref(),
        );
    }

    /**
     * SMOWL is asked once; the refresh asks again.
     */
    public function test_answer_is_cached_until_refreshed(): void {
        $scenario = $this->create_scenario();
        $student = $scenario->students[0];
        $api = new fake_results_api(200, fake_results_api::body('quiz' . $scenario->activity->id, [
            $student->id => ['FrontCamera' => ['status' => 'SUCCESSFUL', 'issues' => []]],
        ]));

        (new smowl($scenario->cm, $api))->load();
        $api->body = fake_results_api::body('quiz' . $scenario->activity->id, [
            $student->id => [
                'FrontCamera' => ['status' => 'UNSUCCESSFUL', 'issues' => ['MORE_THAN_ONE' => ['captures_total' => 2]]],
            ],
        ]);

        $cached = (new smowl($scenario->cm, $api))->load();
        $this->assertCount(1, $api->calls);
        $this->assertSame(rule::GREEN, $cached['users'][$student->id]['level']);

        $fresh = (new smowl($scenario->cm, $api))->load(true);
        $this->assertCount(2, $api->calls);
        $this->assertSame(rule::RED, $fresh['users'][$student->id]['level']);
    }

    /**
     * A change to the rule recolours a cached answer.
     */
    public function test_rule_change_applies_to_cached_answer(): void {
        $scenario = $this->create_scenario();
        $student = $scenario->students[0];
        $api = new fake_results_api(200, fake_results_api::body('quiz' . $scenario->activity->id, [
            $student->id => [
                'ComputerMonitoring' => ['status' => 'UNSUCCESSFUL', 'issues' => ['WEB_NAVIGATION' => ['captures_total' => 9]]],
            ],
        ]));

        $this->assertSame(rule::YELLOW, (new smowl($scenario->cm, $api))->load()['users'][$student->id]['level']);
        set_config('smowl_red', 'WEB_NAVIGATION', 'local_unifiedgrader');
        $this->assertSame(rule::RED, (new smowl($scenario->cm, $api))->load()['users'][$student->id]['level']);
        $this->assertCount(1, $api->calls);
    }

    /**
     * A quiz SMOWL has not been told to monitor says so where the course has the SMOWL block.
     *
     * @dataProvider empty_answer_provider
     * @param int $code HTTP status.
     * @param array|null $body Response body.
     */
    public function test_no_results_for_the_quiz(int $code, ?array $body): void {
        $with = $this->create_scenario();
        $api = new fake_results_api($code, $body);
        $data = (new smowl($with->cm, $api))->load();
        $this->assertSame(provider::STATE_NOTCONFIGURED, $data['state']);
        $this->assertSame([], $data['users']);
        $this->assert_report_link($with, $data['reporturl']);
        $this->assertSame([['quiz', (int) $with->activity->id]], $api->activecalls);

        $without = $this->create_scenario(false);
        $data = (new smowl($without->cm, new fake_results_api($code, $body)))->load();
        $this->assertSame(provider::STATE_NONE, $data['state']);
    }

    /**
     * A quiz that is switched on in SMOWL, but that nobody has sat yet, is not "not active".
     */
    public function test_active_quiz_without_results_yet(): void {
        $scenario = $this->create_scenario();
        $api = new fake_results_api(400, null, 200);

        $data = (new smowl($scenario->cm, $api))->load();
        $this->assertSame(provider::STATE_OK, $data['state']);
        $this->assertSame([], $data['users']);

        // Both answers are cached together.
        (new smowl($scenario->cm, $api))->load();
        $this->assertCount(1, $api->calls);
        $this->assertCount(1, $api->activecalls);

        // Switched on once and since switched off: SMOWL still lists it, as not enabled.
        $data = (new smowl($scenario->cm, new fake_results_api(400, null, 200, false)))->load(true);
        $this->assertSame(provider::STATE_NOTCONFIGURED, $data['state']);
    }

    /**
     * When SMOWL cannot say whether the quiz is switched on, the results are unavailable, not "not active".
     */
    public function test_activity_registry_unreachable(): void {
        $scenario = $this->create_scenario();
        $data = (new smowl($scenario->cm, new fake_results_api(400, null, 500)))->load();
        $this->assertSame(provider::STATE_UNAVAILABLE, $data['state']);
    }

    /**
     * Ways SMOWL says it has nothing for a quiz.
     *
     * @return array[]
     */
    public static function empty_answer_provider(): array {
        return [
            'no content' => [204, null],
            'no data for the activity' => [400, ['status' => 400, 'error' => 400, 'messages' => ['activityName' => 'No data']]],
            'students, but none in this quiz' => [
                200,
                fake_results_api::body('quiz999999', [5 => ['FrontCamera' => ['status' => 'SUCCESSFUL']]]),
            ],
        ];
    }

    /**
     * A failed request reads as "unavailable" where the course has the tool, and is not hammered.
     *
     * @dataProvider failure_provider
     * @param int $code HTTP status; 0 for no answer at all.
     */
    public function test_smowl_unreachable(int $code): void {
        $with = $this->create_scenario();
        $api = new fake_results_api($code);
        $this->assertSame(provider::STATE_UNAVAILABLE, (new smowl($with->cm, $api))->load()['state']);
        (new smowl($with->cm, $api))->load();
        $this->assertCount(1, $api->calls);

        $without = $this->create_scenario(false);
        $this->assertSame(provider::STATE_NONE, (new smowl($without->cm, new fake_results_api($code)))->load()['state']);
    }

    /**
     * Ways a request fails.
     *
     * @return array[]
     */
    public static function failure_provider(): array {
        return ['timeout' => [0], 'bad credentials' => [401], 'server error' => [500]];
    }

    /**
     * The SMOWL block on the course is what says the course uses SMOWL.
     */
    public function test_block_on_the_course_decides_whether_smowl_is_asked(): void {
        global $DB;
        $scenario = $this->create_scenario(false);
        $api = new fake_results_api(400);

        // No block on the course: SMOWL is not even asked.
        $this->assertNull(service::provider_for($scenario->cm));
        $this->assertFalse((new smowl($scenario->cm, $api))->covers_quiz());

        // The block on the quiz's own pages counts as well as on the course.
        $DB->insert_record('block_instances', [
            'blockname' => 'smowl',
            'parentcontextid' => $scenario->context->id,
            'showinsubcontexts' => 0,
            'pagetypepattern' => 'mod-quiz-*',
            'defaultregion' => 'side-pre',
            'defaultweight' => 0,
            'configdata' => '',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $this->assertSame(smowl::class, get_class(service::provider_for($scenario->cm)));

        // The course uses SMOWL but this quiz is not switched on in the panel: the teacher is told.
        $data = (new smowl($scenario->cm, $api))->load();
        $this->assertSame(provider::STATE_NOTCONFIGURED, $data['state']);
        $this->assertSame([['quiz', (int) $scenario->activity->id]], $api->calls);
        $this->assertSame([['quiz', (int) $scenario->activity->id]], $api->activecalls);

        // Switched on: no longer "not active", even before anyone sits it.
        $data = (new smowl($scenario->cm, new fake_results_api(400, null, 200)))->load(true);
        $this->assertSame(provider::STATE_OK, $data['state']);
    }

    /**
     * SMOWL is available only through its block, with the entity name and key saved there.
     */
    public function test_installed_means_the_block_is_set_up(): void {
        $this->create_scenario();
        $this->assertTrue(smowl::installed());

        set_config('apikey', '', 'block_smowl');
        $this->assertFalse(smowl::installed());

        set_config('apikey', 'secret', 'block_smowl');
        smowl::assume_block_for_testing(false);
        $this->assertFalse(smowl::installed());
    }

    /**
     * The feature is dormant without credentials, off quizzes, and without the capability.
     */
    public function test_applies(): void {
        $scenario = $this->create_scenario();
        $this->assertTrue(service::applies($scenario->cm, $scenario->context));

        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $scenario->course->id]);
        $forumcm = get_fast_modinfo($scenario->course->id)->get_cm($forum->cmid);
        $this->assertFalse(service::applies($forumcm, \context_module::instance($forum->cmid)));

        $marker = $this->getDataGenerator()->create_and_enrol($scenario->course, 'teacher');
        $this->setUser($marker);
        $this->assertFalse(service::applies($scenario->cm, $scenario->context));

        $this->setUser($scenario->teacher);
        set_config('apikey', '', 'block_smowl');
        $this->assertFalse(service::applies($scenario->cm, $scenario->context));

        // Credentials alone do not switch it on.
        set_config('apikey', 'secret', 'block_smowl');
        set_config('proctoring_services', '', 'local_unifiedgrader');
        $this->assertFalse(service::applies($scenario->cm, $scenario->context));

        // Nor does ticking a service whose plugin is not installed. A development
        // site may really have one of them, so go by what is there.
        set_config('proctoring_services', 'quilgo,proctorlink,autoproctor', 'local_unifiedgrader');
        $installed = provider\quilgo::installed() || provider\proctorlink::installed() || provider\autoproctor::installed();
        $this->assertSame($installed, service::applies($scenario->cm, $scenario->context));
    }

    /**
     * A fresh site has every service off, and the web service says nothing without asking SMOWL.
     */
    public function test_off_by_default(): void {
        $scenario = $this->create_scenario();
        unset_config('proctoring_services', 'local_unifiedgrader');
        $api = new fake_results_api(200, fake_results_api::body('quiz' . $scenario->activity->id, [
            $scenario->students[0]->id => ['FrontCamera' => ['status' => 'UNSUCCESSFUL', 'issues' => []]],
        ]));
        smowl::use_api_for_testing($api);

        $this->assertSame([], service::enabled_providers());
        $result = get_proctoring::execute($scenario->cm->id);
        $this->assertSame('none', $result['state']);
        $this->assertSame([], $result['users']);
        $this->assertSame([], $api->calls);
    }

    /**
     * The web service names tools and alarms, and leaves ignored alarms out.
     */
    public function test_webservice_exports_labels(): void {
        $scenario = $this->create_scenario();
        $student = $scenario->students[0];
        set_config('smowl_ignore', 'WEB_NAVIGATION', 'local_unifiedgrader');
        smowl::use_api_for_testing(new fake_results_api(200, fake_results_api::body('quiz' . $scenario->activity->id, [
            $student->id => [
                'FrontCamera' => ['status' => 'NOT_LAUNCHED'],
                'ComputerMonitoring' => ['status' => 'UNSUCCESSFUL', 'issues' => [
                    'WEB_NAVIGATION' => ['captures_total' => 12],
                    'SOMETHING_NEW' => ['captures_total' => 2],
                ]],
            ],
        ])));

        $result = \core_external\external_api::clean_returnvalue(
            get_proctoring::execute_returns(),
            get_proctoring::execute($scenario->cm->id),
        );

        $this->assertSame('ok', $result['state']);
        $this->assertSame('SMOWL', $result['provider']);
        $this->assertCount(1, $result['users']);
        $user = $result['users'][0];
        $this->assertSame((int) $student->id, $user['id']);
        $this->assertSame('red', $user['level']);
        $this->assertSame('High review priority', $user['levellabel']);
        $this->assertSame('Front camera', $user['tools'][0]['label']);
        $this->assertSame('not_launched', $user['tools'][0]['status']);
        $this->assertSame('Not launched', $user['tools'][0]['statuslabel']);
        $this->assertSame(
            [['label' => 'Something new', 'count' => 2, 'level' => 'yellow']],
            $user['tools'][1]['issues'],
        );
    }

    /**
     * The web service needs the proctoring capability.
     */
    public function test_webservice_requires_capability(): void {
        $scenario = $this->create_scenario();
        smowl::use_api_for_testing(new fake_results_api(204));
        $marker = $this->getDataGenerator()->create_and_enrol($scenario->course, 'teacher');
        $this->setUser($marker);

        $this->expectException(\required_capability_exception::class);
        get_proctoring::execute($scenario->cm->id);
    }

    /**
     * In separate groups, a teacher sees only their own groups' students.
     */
    public function test_webservice_hides_students_outside_the_teachers_groups(): void {
        global $DB;
        $scenario = $this->create_scenario();
        [$mine, $theirs] = $scenario->students;
        $gen = $this->getDataGenerator();

        $DB->set_field('course_modules', 'groupmode', SEPARATEGROUPS, ['id' => $scenario->cm->id]);
        rebuild_course_cache($scenario->course->id, true);
        $group = $gen->create_group(['courseid' => $scenario->course->id]);
        $other = $gen->create_group(['courseid' => $scenario->course->id]);
        $gen->create_group_member(['groupid' => $group->id, 'userid' => $mine->id]);
        $gen->create_group_member(['groupid' => $group->id, 'userid' => $scenario->teacher->id]);
        $gen->create_group_member(['groupid' => $other->id, 'userid' => $theirs->id]);
        assign_capability(
            'moodle/site:accessallgroups',
            CAP_PROHIBIT,
            $DB->get_field('role', 'id', ['shortname' => 'editingteacher']),
            \context_course::instance($scenario->course->id),
        );

        $tools = ['FrontCamera' => ['status' => 'SUCCESSFUL', 'issues' => []]];
        smowl::use_api_for_testing(new fake_results_api(200, fake_results_api::body(
            'quiz' . $scenario->activity->id,
            [$mine->id => $tools, $theirs->id => $tools],
        )));

        $result = get_proctoring::execute($scenario->cm->id);
        $this->assertSame([(int) $mine->id], array_column($result['users'], 'id'));
    }
}
