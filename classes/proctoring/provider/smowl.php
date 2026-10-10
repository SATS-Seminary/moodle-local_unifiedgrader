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

/**
 * SMOWL proctoring results for a quiz.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\proctoring\provider;

use local_unifiedgrader\proctoring\provider;
use local_unifiedgrader\proctoring\results_api;
use local_unifiedgrader\proctoring\rule;
use local_unifiedgrader\proctoring\smowl_client;

/**
 * Reads a quiz's SMOWL results and gives each student a review priority.
 *
 * SMOWL is brought into Moodle by its own plugin, block_smowl, and everything
 * here goes by it: the entity name and API key are the ones saved in the
 * block's settings, and a quiz and a student are known to SMOWL by the IDs the
 * block sends when it monitors an attempt. Nothing about the connection is
 * configured twice.
 *
 * The results stay with SMOWL. Its Results API is asked: one call returns
 * every student who sat the quiz under monitoring, and the answer is kept for
 * fifteen minutes (see db/caches.php). SMOWL keeps one result per student per
 * quiz, not per attempt.
 *
 * Two things make a quiz proctored by SMOWL. The course must have the SMOWL
 * block. And the quiz must be switched on in the SMOWL panel, which only
 * SMOWL knows, so it is asked. A course without the block is never asked
 * about. In one that has it, a quiz that is not switched on says so: a
 * teacher cannot otherwise tell without opening the panel.
 *
 * What is cached is SMOWL's answer, not the colours, so a change to the rule
 * on the settings page shows on the next page load.
 */
class smowl extends provider {
    /** @var string[] Alarm categories SMOWL documents, for the settings page. */
    const CATEGORIES = [
        rule::NOT_LAUNCHED,
        'INCORRECT_USER',
        'MORE_THAN_ONE',
        'NOBODY',
        'BANNED_ELEMENTS',
        'SUSPICIOUS_BEHAVIOUR',
        'WEBCAM_COVERED',
        'OTHER_TAB',
        'WRONG_LIGHT_POSING',
        'WEBCAM_POSITION',
        'STUDENT_POSITION',
        'MIN_IMAGES_REQUIRED',
        'WEBCAM_REJECTED',
        'WEBCAM_BLOCKED',
        'NO_WEBCAM',
        'CONFIGURATION_PROBLEM',
        'UNSUPPORTED_BROWSER',
        'NETWORK_PROBLEM',
        'INCORRECT_AUDIO',
        'MICROPHONE_REJECTED',
        'MICROPHONE_BLOCKED',
        'NO_MICROPHONE',
        'OPENED_PROGRAM',
        'PROGRAM_INTERACTION',
        'WEB_NAVIGATION',
        'COMMANDS',
        'PERIPHERALS',
        'VIRTUAL_MACHINE',
        'EARLY_CLOSE',
        'EXAM_LEAVE',
        'EXPEL',
        'SHARED_IP',
    ];

    /** @var string[] Categories that are red until an administrator says otherwise. */
    const DEFAULT_RED = [
        rule::NOT_LAUNCHED,
        'INCORRECT_USER',
        'MORE_THAN_ONE',
        'BANNED_ELEMENTS',
        'SUSPICIOUS_BEHAVIOUR',
        'OPENED_PROGRAM',
        'PROGRAM_INTERACTION',
        'VIRTUAL_MACHINE',
    ];

    /** @var results_api|null A client every new service uses, in tests only. */
    private static ?results_api $testapi = null;

    /** @var bool|null Whether block_smowl is taken to be installed, in tests only. */
    private static ?bool $testblock = null;

    /**
     * Decide whether SMOWL's Moodle plugin counts as installed, for tests.
     *
     * @param bool|null $installed Null to go by what the site has.
     */
    public static function assume_block_for_testing(?bool $installed): void {
        if (!PHPUNIT_TEST) {
            throw new \coding_exception('assume_block_for_testing() is for tests only.');
        }
        self::$testblock = $installed;
    }

    /**
     * Whether SMOWL's own Moodle plugin, block_smowl, is installed.
     *
     * @return bool
     */
    public static function block_installed(): bool {
        return self::$testblock ?? (\core_component::get_plugin_directory('block', 'smowl') !== null);
    }

    /**
     * Whether the quiz's course uses SMOWL: it has the SMOWL block, on the course or on the quiz's own pages.
     *
     * @return bool
     */
    public function course_uses_smowl(): bool {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal(
            [\context_course::instance($this->cm->course)->id, $this->cm->context->id],
            SQL_PARAMS_NAMED,
        );
        return $DB->record_exists_select('block_instances', "blockname = :blockname AND parentcontextid $insql", [
            'blockname' => 'smowl',
        ] + $params);
    }

    /** @var results_api The API client. */
    private results_api $api;

    /** @var rule The colour rule. */
    private rule $rule;

    /**
     * Constructor.
     *
     * @param \cm_info $cm The quiz.
     * @param results_api|null $api A client to use instead of the configured one.
     * @param rule|null $rule A rule to use instead of the configured one.
     */
    public function __construct(\cm_info $cm, ?results_api $api = null, ?rule $rule = null) {
        parent::__construct($cm);
        $this->api = $api ?? self::$testapi ?? new smowl_client(
            trim((string) get_config('block_smowl', 'entity')),
            trim((string) get_config('block_smowl', 'apikey')),
        );
        $this->rule = $rule ?? rule::from_config('smowl', self::DEFAULT_RED);
    }

    /**
     * Answer from a stand-in client instead of the network, for tests.
     *
     * @param results_api|null $api The stand-in, or null to stop.
     */
    public static function use_api_for_testing(?results_api $api): void {
        if (!PHPUNIT_TEST && !defined('BEHAT_SITE_RUNNING')) {
            throw new \coding_exception('use_api_for_testing() is for tests only.');
        }
        self::$testapi = $api;
    }

    /**
     * The service's short name.
     *
     * @return string
     */
    public static function key(): string {
        return 'smowl';
    }

    /**
     * Whether the SMOWL block is installed and has its entity name and API key.
     *
     * @return bool
     */
    public static function installed(): bool {
        return self::block_installed()
            && trim((string) get_config('block_smowl', 'entity')) !== ''
            && trim((string) get_config('block_smowl', 'apikey')) !== '';
    }

    /**
     * Whether it is worth asking SMOWL about the quiz: only in a course that has the SMOWL block.
     *
     * @return bool
     */
    public function covers_quiz(): bool {
        return $this->course_uses_smowl();
    }

    /**
     * How SMOWL knows this quiz.
     *
     * SMOWL keys results by the activity type and ID it was given when the
     * student was monitored, and returns them joined together ("quiz" + 42 =
     * "quiz42"). block_smowl sends the module name and the quiz's own ID (see
     * get_iframe_student() in its renderer).
     *
     * With the block's "attempt tracking" setting on, it sends the quiz ID and
     * the attempt ID joined instead, and SMOWL files each attempt apart. That
     * is not read here: such a quiz shows as having no results.
     *
     * @return array{0: string, 1: int} Activity type and activity ID.
     */
    public function activity_ref(): array {
        return [$this->cm->modname, (int) $this->cm->instance];
    }

    /**
     * The Moodle user SMOWL means by one of its user IDs.
     *
     * block_smowl sends the Moodle user ID, so they are the same number.
     *
     * @param string $smowluserid The userId in an API response.
     * @return int Moodle user ID, or 0 when it is not one.
     */
    protected function moodle_userid(string $smowluserid): int {
        return ctype_digit($smowluserid) ? (int) $smowluserid : 0;
    }

    /**
     * Where a teacher reviews the evidence: the block's SMOWL panel for the course.
     *
     * @return \moodle_url|null Null when the user may not open it.
     */
    public function report_url(): ?\moodle_url {
        $coursecontext = \context_course::instance($this->cm->course);
        $canmanage = get_capability_info('block/smowl:manageactivities') !== null
            && has_capability('block/smowl:manageactivities', $coursecontext);
        return $canmanage
            ? new \moodle_url('/blocks/smowl/manage.php', ['id' => $this->cm->course, 'view' => 'ltimanagesmowl'])
            : null;
    }

    /**
     * The quiz's proctoring results, ready for the grader.
     *
     * @param bool $refresh Ask SMOWL again instead of using the cached answer.
     * @return array{state: string, fetchedat: int, reporturl: string, users: array}
     *         users is keyed by Moodle user ID; each has level, provisional and tools.
     */
    public function load(bool $refresh = false): array {
        $raw = $this->fetch($refresh);
        $reporturl = $this->report_url();
        $expected = $this->course_uses_smowl();

        // A quiz SMOWL was never told about is silence in a course that does
        // not use SMOWL, and worth pointing out in one that does.
        $state = match ($raw['state']) {
            'ok' => self::STATE_OK,
            'inactive' => $expected ? self::STATE_NOTCONFIGURED : self::STATE_NONE,
            default => $expected ? self::STATE_UNAVAILABLE : self::STATE_NONE,
        };

        $users = [];
        if ($state === self::STATE_OK) {
            foreach ($raw['users'] as $userid => $tools) {
                $users[$userid] = $this->describe($this->rule->evaluate($tools));
            }
        }

        return [
            'state' => $state,
            'fetchedat' => (int) $raw['fetchedat'],
            'reporturl' => $reporturl ? $reporturl->out(false) : '',
            'users' => $users,
        ];
    }

    /**
     * Put a student's result into words.
     *
     * @param array $result From rule::evaluate().
     * @return array A user entry as provider::load() describes it.
     */
    private function describe(array $result): array {
        $tools = [];
        foreach ($result['tools'] as $tool) {
            $issues = [];
            foreach ($tool['issues'] as $issue) {
                if ($issue['level'] !== rule::IGNORE) {
                    $issues[] = [
                        'label' => self::label('proctoring_cat_', $issue['key']),
                        'count' => $issue['count'],
                        'level' => $issue['level'],
                    ];
                }
            }
            $tools[] = [
                'label' => self::label('proctoring_tool_', $tool['name']),
                'status' => \core_text::strtolower($tool['status']),
                'statuslabel' => self::label('proctoring_status_', $tool['status']),
                'issues' => $issues,
            ];
        }
        return [
            'level' => $result['level'],
            'provisional' => $result['provisional'],
            'reporturl' => '',
            'tools' => $tools,
            'attempts' => [],
        ];
    }

    /**
     * SMOWL's answer for this quiz, from the cache when it is fresh.
     *
     * @param bool $refresh Skip the cache.
     * @return array{state: string, fetchedat: int, users: array} state is ok (switched on,
     *         with or without results yet), inactive or error.
     */
    private function fetch(bool $refresh): array {
        $cached = $refresh ? null : $this->cached();
        if ($cached !== null) {
            return $cached;
        }

        [$type, $id] = $this->activity_ref();
        $response = $this->api->reasons($type, $id);
        $result = ['state' => 'error', 'fetchedat' => time(), 'users' => []];

        $noresults = $response['code'] === 204 || $response['code'] === 400;
        if ($response['code'] === 200 && is_array($response['body'])) {
            $result['users'] = $this->normalise($response['body'], $type . $id);
            $noresults = !$result['users'];
        }

        if ($result['users']) {
            $result['state'] = 'ok';
        } else if ($noresults) {
            // Nobody monitored yet, or a quiz that is not switched on? Only SMOWL's registry can say.
            // An entry with "enabled": false is a quiz that was switched on once and has been switched off again.
            $registry = $this->api->active_services($type, $id);
            $enabled = ($registry['body'][$type . $id]['enabled'] ?? true) !== false;
            $result['state'] = match ($registry['code']) {
                200 => $enabled ? 'ok' : 'inactive',
                400 => 'inactive',
                default => 'error',
            };
        }

        $this->remember($result);
        return $result;
    }

    /**
     * Reduce a results/reasons response to this quiz's tools per student.
     *
     * The response can carry a student's other activities too; only the one
     * keyed by this quiz is kept. A student with no entry for it is left out.
     *
     * @param array $body The decoded response.
     * @param string $activitykey Activity type and ID joined, e.g. "quiz42".
     * @return array Moodle user ID => [tool name => ['status' => string, 'issues' => [category => count], 'pending' => int]].
     */
    private function normalise(array $body, string $activitykey): array {
        $users = [];
        foreach ($body['users'] ?? [] as $user) {
            $userid = $this->moodle_userid((string) ($user['userId'] ?? ''));
            $activity = $user['activities'][$activitykey] ?? null;
            if (!$userid || !is_array($activity) || !$activity) {
                continue;
            }
            $tools = [];
            foreach ($activity as $toolname => $tool) {
                if (!is_array($tool) || !isset($tool['status'])) {
                    continue;
                }
                $issues = [];
                foreach (is_array($tool['issues'] ?? null) ? $tool['issues'] : [] as $category => $detail) {
                    $issues[(string) $category] = (int) ($detail['captures_total'] ?? $detail['warnings_total'] ?? 0);
                }
                $tools[(string) $toolname] = [
                    'status' => (string) $tool['status'],
                    'issues' => $issues,
                    // Captures SMOWL has yet to validate; a tool can have passed so far and still have some.
                    'pending' => (int) ($tool['pending'] ?? 0),
                ];
            }
            if ($tools) {
                $users[$userid] = $tools;
            }
        }
        return $users;
    }
}
