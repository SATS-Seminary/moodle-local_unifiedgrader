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
 * AutoProctor proctoring results for a quiz.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\proctoring\provider;

use local_unifiedgrader\proctoring\autoproctor_api;
use local_unifiedgrader\proctoring\autoproctor_client;
use local_unifiedgrader\proctoring\provider;
use local_unifiedgrader\proctoring\rule;

/**
 * Reads AutoProctor's trust score for each attempt the quizaccess_autoproctor plugin started.
 *
 * The plugin keeps only which attempt is which AutoProctor session
 * (quizaccess_autoproctor_sessions); the result stays with AutoProctor. Its
 * REST API is asked, with the client ID and secret the plugin already holds,
 * and the answers are kept for fifteen minutes.
 *
 * AutoProctor gives a trust score between 0 and 1 and no verdict, so two site
 * settings turn it into a colour.
 */
class autoproctor extends provider {
    /** @var autoproctor_api|null A client every new provider uses, in tests only. */
    private static ?autoproctor_api $testapi = null;

    /**
     * Answer from a stand-in client instead of the network, for tests.
     *
     * @param autoproctor_api|null $api The stand-in, or null to stop.
     */
    public static function use_api_for_testing(?autoproctor_api $api): void {
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
        return 'autoproctor';
    }

    /**
     * Whether the AutoProctor access rule is installed and has its credentials.
     *
     * @return bool
     */
    public static function installed(): bool {
        return self::access_rule_installed('autoproctor', ['quizaccess_autoproctor', 'quizaccess_autoproctor_sessions'])
            && !empty(get_config('quizaccess_autoproctor', 'client_id'))
            && !empty(get_config('quizaccess_autoproctor', 'client_secret'));
    }

    /**
     * Whether AutoProctor is on for the quiz, or watched attempts at it.
     *
     * @return bool
     */
    public function covers_quiz(): bool {
        global $DB;
        return $DB->record_exists('quizaccess_autoproctor', ['quiz_id' => $this->cm->instance, 'proctoring_enabled' => 1])
            || $DB->record_exists('quizaccess_autoproctor_sessions', ['quiz_id' => $this->cm->instance]);
    }

    /**
     * The quiz's results.
     *
     * @param bool $refresh Ask AutoProctor again instead of using the cached answer.
     * @return array
     */
    public function load(bool $refresh = false): array {
        global $DB;

        $sessions = $DB->get_records_sql(
            "SELECT s.id, s.test_attempt_id, qa.userid, qa.attempt, qa.state
               FROM {quizaccess_autoproctor_sessions} s
               JOIN {quiz_attempts} qa ON qa.id = s.quiz_attempt_id
              WHERE s.quiz_id = :quizid AND qa.preview = 0",
            ['quizid' => $this->cm->instance],
        );

        $finished = [];
        foreach ($sessions as $session) {
            if ($session->state === \mod_quiz\quiz_attempt::FINISHED) {
                $finished[] = $session->test_attempt_id;
            }
        }
        $answer = $this->fetch($finished, $refresh);

        $canview = $this->can('quizaccess/autoproctor:viewreport');
        $attempts = [];
        foreach ($sessions as $session) {
            $result = $this->describe($answer['results'][$session->test_attempt_id] ?? null);
            if ($canview) {
                $result['reporturl'] = (new \moodle_url('/mod/quiz/accessrule/autoproctor/loadreport.php', [
                    'ap_attempt_id' => $session->test_attempt_id,
                ]))->out(false);
            }
            $attempts[(int) $session->userid][(int) $session->attempt] = $result;
        }

        $users = [];
        foreach ($attempts as $userid => $userattempts) {
            $users[$userid] = $this->across_attempts($userattempts);
        }

        // Every request failing is AutoProctor being unreachable, not sixty students awaiting review.
        $failed = $finished && !array_filter($answer['results']);

        return [
            'state' => $failed ? self::STATE_UNAVAILABLE : self::STATE_OK,
            'fetchedat' => (int) $answer['fetchedat'],
            'reporturl' => '',
            'users' => $failed ? [] : $users,
        ];
    }

    /**
     * AutoProctor's answers for the finished attempts, from the cache when it covers them.
     *
     * @param string[] $testattemptids Test attempt IDs.
     * @param bool $refresh Skip the cache.
     * @return array{results: array, fetchedat: int} results is keyed by test attempt ID.
     */
    private function fetch(array $testattemptids, bool $refresh): array {
        if (!$testattemptids) {
            return ['results' => [], 'fetchedat' => time()];
        }
        $cached = $refresh ? null : $this->cached();
        $known = $cached['results'] ?? [];
        $missing = array_values(array_diff($testattemptids, array_keys($known)));
        if ($cached !== null && !$missing) {
            return $cached;
        }

        global $CFG;
        $api = self::$testapi ?? new autoproctor_client(
            (string) get_config('quizaccess_autoproctor', 'client_id'),
            (string) get_config('quizaccess_autoproctor', 'client_secret'),
            // The same switch the AutoProctor plugin uses for its sandbox.
            empty($CFG->quizaccess_autoproctor_devmode) ? 'https://www.autoproctor.co' : 'https://dev.autoproctor.co',
        );
        // Attempts the client ran out of time for are asked about on the next load.
        // Only what the colour needs is kept: the full answer carries an access
        // token for the attempt, which has no business sitting in a cache.
        $results = $known;
        foreach ($api->attempts($missing) as $id => $body) {
            $attempt = is_array($body['test_attempt'] ?? null) ? $body['test_attempt'] : null;
            $results[$id] = match (true) {
                $body === null => null,
                $attempt === null => ['notfound' => true],
                default => ['test_attempt' => [
                    'trust_score' => $attempt['trust_score'] ?? null,
                    'evidence_status' => $attempt['evidence_status'] ?? null,
                ]],
            };
        }

        $answer = [
            'state' => array_filter($results) ? 'ok' : 'error',
            'fetchedat' => time(),
            'results' => $results,
        ];
        $this->remember($answer);
        return $answer;
    }

    /**
     * One attempt's result from AutoProctor's answer.
     *
     * The result sits under test_attempt. Seen on the live API on 2026-10-09:
     * trust_score and evidence_status are null while the attempt is running,
     * and a finished one came back with a trust_score of 0.77 and an
     * evidence_status of "processed".
     *
     * @param array|null $body The decoded answer; null when there is none.
     * @return array
     */
    private function describe(?array $body): array {
        $attempt = is_array($body['test_attempt'] ?? null) ? $body['test_attempt'] : [];
        $score = $attempt['trust_score'] ?? null;
        $evidence = $attempt['evidence_status'] ?? null;

        if (!is_numeric($score) || ($evidence !== null && $evidence !== 'processed')) {
            return [
                'level' => rule::PENDING,
                'provisional' => false,
                'reporturl' => '',
                'tools' => [[
                    'label' => self::name(),
                    'status' => 'pending',
                    'statuslabel' => get_string('proctoring_autoproctor_notready', 'local_unifiedgrader'),
                    'issues' => [],
                ]],
            ];
        }

        // A fraction of 1, shown as a percentage.
        $percent = (int) round(($score <= 1 ? $score * 100 : $score));
        $green = (int) (get_config('local_unifiedgrader', 'autoproctor_green') ?: 80);
        $yellow = (int) (get_config('local_unifiedgrader', 'autoproctor_yellow') ?: 50);
        $level = $percent >= $green ? rule::GREEN : ($percent >= $yellow ? rule::YELLOW : rule::RED);

        return [
            'level' => $level,
            'provisional' => false,
            'reporturl' => '',
            'tools' => [[
                'label' => self::name(),
                'status' => 'scored',
                'statuslabel' => get_string('proctoring_autoproctor_trustscore', 'local_unifiedgrader', $percent),
                'issues' => [],
            ]],
        ];
    }
}
