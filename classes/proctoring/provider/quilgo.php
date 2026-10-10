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
 * Quilgo proctoring results for a quiz.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\proctoring\provider;

use local_unifiedgrader\proctoring\provider;
use local_unifiedgrader\proctoring\rule;

/**
 * Reads the quizaccess_quilgo plugin's reports.
 *
 * Quilgo gives its own verdict, a confidence level per attempt, and that is
 * what is shown: high confidence is green, moderate yellow, low red. The
 * plugin works it out from the report's trust score (100, 50, anything else;
 * see its utils::define_confidence_level()) and keeps the report in
 * quizaccess_quilgo_reports.stat once a teacher has opened its report page.
 *
 * For attempts nobody has looked at yet, Quilgo is asked through the plugin's
 * own API client, which holds the site's credentials. Those answers are kept
 * in this plugin's cache; Quilgo's tables are never written to.
 */
class quilgo extends provider {
    /** @var int generate_status of a report Quilgo has finished. */
    const STATUS_READY = 1;

    /** @var int The most reports asked for in one request. */
    const BATCH = 50;

    /** @var object|null Stands in for the Quilgo plugin's API client, in tests only. */
    private static ?object $testapi = null;

    /**
     * Answer from a stand-in for the Quilgo plugin's client, for tests.
     *
     * @param object|null $api Anything with fetch_multiple_reports(string): array, or null to stop.
     */
    public static function use_api_for_testing(?object $api): void {
        if (!PHPUNIT_TEST) {
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
        return 'quilgo';
    }

    /**
     * Whether the Quilgo access rule is installed.
     *
     * @return bool
     */
    public static function installed(): bool {
        return self::access_rule_installed('quilgo', ['quizaccess_quilgo_settings', 'quizaccess_quilgo_reports']);
    }

    /**
     * Whether Quilgo is on for the quiz, or was when attempts were made.
     *
     * @return bool
     */
    public function covers_quiz(): bool {
        global $DB;
        return $DB->record_exists('quizaccess_quilgo_settings', ['quizid' => $this->cm->instance, 'proctoring_enabled' => 1])
            || $DB->record_exists_sql(
                "SELECT 1
                   FROM {quizaccess_quilgo_reports} r
                   JOIN {quiz_attempts} qa ON qa.id = r.attemptid
                  WHERE qa.quiz = :quizid AND qa.preview = 0 AND r.proctoring_enabled = 1",
                ['quizid' => $this->cm->instance],
            );
    }

    /**
     * The quiz's results.
     *
     * @param bool $refresh Ask Quilgo again for reports not yet stored.
     * @return array
     */
    public function load(bool $refresh = false): array {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT r.id, r.plasmsessionid, r.generate_status, r.stat, qa.userid, qa.attempt, qa.state
               FROM {quizaccess_quilgo_reports} r
               JOIN {quiz_attempts} qa ON qa.id = r.attemptid
              WHERE qa.quiz = :quizid AND qa.preview = 0 AND r.proctoring_enabled = 1",
            ['quizid' => $this->cm->instance],
        );

        // Reports Quilgo's own page has not stored yet.
        $missing = [];
        foreach ($rows as $row) {
            if (empty($row->stat) && !empty($row->plasmsessionid) && $row->state === \mod_quiz\quiz_attempt::FINISHED) {
                $missing[] = $row->plasmsessionid;
            }
        }
        $fetched = $missing ? $this->fetch_missing($missing, $refresh) : ['stats' => [], 'fetchedat' => time()];

        $attempts = [];
        foreach ($rows as $row) {
            $stat = !empty($row->stat) ? json_decode($row->stat) : ($fetched['stats'][$row->plasmsessionid] ?? null);
            $attempts[(int) $row->userid][(int) $row->attempt] = $this->describe(is_object($stat) ? $stat : null);
        }

        $users = [];
        foreach ($attempts as $userid => $userattempts) {
            $users[$userid] = $this->across_attempts($userattempts);
        }

        return [
            'state' => self::STATE_OK,
            'fetchedat' => (int) $fetched['fetchedat'],
            'reporturl' => $this->report_url(),
            'users' => $users,
        ];
    }

    /**
     * Quilgo's report list for the quiz, if the user may open it.
     *
     * @return string
     */
    private function report_url(): string {
        if (!$this->can('quizaccess/quilgo:viewreport')) {
            return '';
        }
        return (new \moodle_url('/mod/quiz/accessrule/quilgo/report.php', [
            'cid' => $this->cm->course,
            'cmid' => $this->cm->id,
            'qid' => $this->cm->instance,
        ]))->out(false);
    }

    /**
     * Ask Quilgo for reports its plugin has not stored, through the plugin's client.
     *
     * The client is the plugin's own class, so it may change under us: any
     * failure leaves those attempts as "awaiting review" and nothing more.
     *
     * @param string[] $sessionids Quilgo session IDs.
     * @param bool $refresh Skip the cache.
     * @return array{stats: array, fetchedat: int} stats is keyed by session ID.
     */
    private function fetch_missing(array $sessionids, bool $refresh): array {
        $cached = $refresh ? null : $this->cached();
        if ($cached !== null && !array_diff($sessionids, $cached['asked'] ?? [])) {
            return $cached;
        }

        $answer = ['state' => 'ok', 'fetchedat' => time(), 'asked' => $sessionids, 'stats' => []];
        try {
            $api = self::$testapi ?? (class_exists(\quizaccess_quilgo\plasmapi::class) ? new \quizaccess_quilgo\plasmapi() : null);
            if (!$api) {
                throw new \coding_exception('quizaccess_quilgo has no API client.');
            }
            foreach (array_chunk($sessionids, self::BATCH) as $batch) {
                foreach ((array) $api->fetch_multiple_reports(implode(',', $batch)) as $report) {
                    // Quilgo sends the stat as a JSON string (seen live on 2026-10-09).
                    $stat = $report->stat ?? null;
                    $stat = is_string($stat) ? json_decode($stat) : $stat;
                    if (isset($report->uuid) && is_object($stat)) {
                        $answer['stats'][$report->uuid] = $stat;
                    }
                }
            }
        } catch (\Throwable $e) {
            debugging('Quilgo reports could not be fetched: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $answer['state'] = 'error';
        }

        $this->remember($answer);
        return $answer;
    }

    /**
     * One attempt's result from Quilgo's report.
     *
     * @param \stdClass|null $stat The report's stat; null when there is none yet.
     * @return array
     */
    private function describe(?\stdClass $stat): array {
        if (!$stat || !isset($stat->trustScore)) {
            return [
                'level' => rule::PENDING,
                'provisional' => false,
                'reporturl' => '',
                'tools' => [[
                    'label' => self::name(),
                    'status' => 'pending',
                    'statuslabel' => get_string('proctoring_quilgo_notready', 'local_unifiedgrader'),
                    'issues' => [],
                ]],
            ];
        }

        // The same three steps as the Quilgo plugin's own report.
        [$level, $confidence] = match ((int) $stat->trustScore) {
            100 => [rule::GREEN, 'high'],
            50 => [rule::YELLOW, 'moderate'],
            default => [rule::RED, 'low'],
        };

        $details = [];
        foreach (['facesPresence' => 'facespresence', 'faceMatch' => 'facematch'] as $field => $string) {
            if (isset($stat->$field) && is_numeric($stat->$field)) {
                $details[] = get_string('proctoring_quilgo_' . $string, 'local_unifiedgrader', (int) round($stat->$field * 100));
            }
        }
        if (!empty($stat->maxFacesPerFrame) && $stat->maxFacesPerFrame > 1) {
            $details[] = get_string('proctoring_quilgo_multiplefaces', 'local_unifiedgrader', (int) $stat->maxFacesPerFrame);
        }
        if (!empty($stat->pageUnfocusedCount)) {
            $details[] = get_string(
                $stat->pageUnfocusedCount == 1 ? 'proctoring_quilgo_unfocusedonce' : 'proctoring_quilgo_unfocused',
                'local_unifiedgrader',
                (int) $stat->pageUnfocusedCount,
            );
        }
        // What Quilgo calls patterns: things the student did that it finds suspicious, by kind.
        $patterns = [];
        foreach (is_array($stat->patterns ?? null) ? $stat->patterns : [] as $pattern) {
            if (isset($pattern->type) && is_string($pattern->type)) {
                $patterns[$pattern->type] = ($patterns[$pattern->type] ?? 0) + 1;
            }
        }
        if (isset($stat->referenceFaceMatch)) {
            $details[] = get_string(
                $stat->referenceFaceMatch ? 'proctoring_quilgo_idmatched' : 'proctoring_quilgo_idnotmatched',
                'local_unifiedgrader',
            );
        }

        return [
            'level' => $level,
            'provisional' => false,
            'reporturl' => '',
            'tools' => [[
                'label' => self::name(),
                'status' => $confidence,
                'statuslabel' => get_string('proctoring_quilgo_confidence_' . $confidence, 'local_unifiedgrader'),
                'issues' => array_merge(
                    array_map(fn($text) => ['label' => $text, 'count' => 0, 'level' => 'info'], $details),
                    array_map(
                        fn($type, $count) => [
                            'label' => self::label('proctoring_quilgo_pattern_', $type),
                            'count' => $count,
                            'level' => 'info',
                        ],
                        array_keys($patterns),
                        $patterns,
                    ),
                ),
            ]],
        ];
    }
}
