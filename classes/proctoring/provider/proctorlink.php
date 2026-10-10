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
 * ProctorLink proctoring results for a quiz.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\proctoring\provider;

use local_unifiedgrader\proctoring\provider;
use local_unifiedgrader\proctoring\rule;

/**
 * Reads the quizaccess_quizproctoring plugin's tables ("ProctorLink").
 *
 * ProctorLink keeps everything in Moodle: an attempt it watched has rows in
 * quizaccess_quizproctoring_ma, and each warning it raised is a row in
 * quizaccess_quizproctoring_da with the kind of warning in status. It counts
 * warnings but gives no verdict, so the colour comes from the same rule as
 * SMOWL's: the kind of warning decides, and which kinds are red is a setting.
 *
 * Nothing is fetched or cached; the tables are read each time.
 */
class proctorlink extends provider {
    /** @var string The status of an image ProctorLink has not analysed yet. */
    const PENDING = 'pendingprocessing';

    /** @var string[] Warning kinds ProctorLink raises, for the settings page. */
    const CATEGORIES = [
        'facesnotmatched',
        'multifacesdetected',
        'nofacedetected',
        'objectdetected',
        'facemaskdetected',
        'eyesnotopened',
        'leftmovedetected',
        'rightmovedetected',
        'minimizedetected',
        'nocameradetected',
        'nocameradisabled',
        'nomicrophonedisabled',
    ];

    /** @var string[] Kinds that are red until an administrator says otherwise. */
    const DEFAULT_RED = ['facesnotmatched', 'multifacesdetected', 'objectdetected'];

    /**
     * The service's short name.
     *
     * @return string
     */
    public static function key(): string {
        return 'proctorlink';
    }

    /**
     * Whether the ProctorLink access rule is installed.
     *
     * @return bool
     */
    public static function installed(): bool {
        return self::access_rule_installed('quizproctoring', [
            'quizaccess_quizproctoring',
            'quizaccess_quizproctoring_ma',
            'quizaccess_quizproctoring_da',
        ]);
    }

    /**
     * Whether ProctorLink is on for the quiz, or watched attempts at it.
     *
     * @return bool
     */
    public function covers_quiz(): bool {
        global $DB;
        return $DB->record_exists('quizaccess_quizproctoring', ['quizid' => $this->cm->instance, 'enableproctoring' => 1])
            || $DB->record_exists('quizaccess_quizproctoring_ma', ['quizid' => $this->cm->instance, 'deleted' => 0]);
    }

    /**
     * The quiz's results.
     *
     * @param bool $refresh Unused: the tables are always read afresh.
     * @return array
     */
    public function load(bool $refresh = false): array {
        global $DB;
        $rule = rule::from_config('proctorlink', self::DEFAULT_RED);
        $params = ['quizid' => $this->cm->instance];

        // Every attempt ProctorLink watched, warnings or not.
        $watched = $DB->get_records_sql(
            "SELECT qa.id, qa.userid, qa.attempt
               FROM {quiz_attempts} qa
              WHERE qa.quiz = :quizid AND qa.preview = 0
                    AND EXISTS (SELECT 1
                                  FROM {quizaccess_quizproctoring_ma} m
                                 WHERE m.attemptid = qa.id AND m.deleted = 0)",
            $params,
        );

        // Its warnings, counted by kind. An image still waiting to be analysed counts as pending.
        $counts = $DB->get_recordset_sql(
            "SELECT d.attemptid, d.status, COUNT(1) AS warnings
               FROM {quizaccess_quizproctoring_da} d
              WHERE d.quizid = :quizid AND d.deleted = 0 AND d.status <> ''
           GROUP BY d.attemptid, d.status",
            $params,
        );
        $warnings = [];
        foreach ($counts as $count) {
            $warnings[(int) $count->attemptid][$count->status] = (int) $count->warnings;
        }
        $counts->close();

        $canreview = $this->can('quizaccess/quizproctoring:quizproctoringoverallreport');

        $attempts = [];
        foreach ($watched as $attempt) {
            $issues = $warnings[(int) $attempt->id] ?? [];
            $pending = isset($issues[self::PENDING]);
            unset($issues[self::PENDING]);
            $attempts[(int) $attempt->userid][(int) $attempt->attempt] = $this->describe($rule, $issues, $pending);
        }

        $users = [];
        foreach ($attempts as $userid => $userattempts) {
            $users[$userid] = $this->across_attempts($userattempts);
            if ($canreview) {
                $users[$userid]['reporturl'] = (new \moodle_url('/mod/quiz/accessrule/quizproctoring/reviewattempts.php', [
                    'userid' => $userid,
                    'cmid' => $this->cm->id,
                    'quizid' => $this->cm->instance,
                ]))->out(false);
            }
        }

        return [
            'state' => self::STATE_OK,
            'fetchedat' => time(),
            'reporturl' => !$canreview ? '' : (new \moodle_url('/mod/quiz/accessrule/quizproctoring/proctoringreport.php', [
                'cmid' => $this->cm->id,
                'quizid' => $this->cm->instance,
            ]))->out(false),
            'users' => $users,
        ];
    }

    /**
     * One attempt's result from its warnings.
     *
     * @param rule $rule The colour rule.
     * @param int[] $issues Warning kind => how many.
     * @param bool $pending Whether some images are still to be analysed.
     * @return array
     */
    private function describe(rule $rule, array $issues, bool $pending): array {
        $result = $rule->evaluate([
            'warnings' => ['status' => $issues ? 'UNSUCCESSFUL' : 'SUCCESSFUL', 'issues' => $issues],
        ] + ($pending ? ['pending' => ['status' => 'PENDING']] : []));

        $shown = [];
        foreach ($result['tools'][0]['issues'] as $issue) {
            if ($issue['level'] !== rule::IGNORE) {
                $shown[] = [
                    'label' => self::label('proctoring_plcat_', $issue['key']),
                    'count' => $issue['count'],
                    'level' => $issue['level'],
                ];
            }
        }
        $total = array_sum(array_column($shown, 'count'));

        return [
            'level' => $result['level'],
            'provisional' => $result['provisional'],
            'reporturl' => '',
            'tools' => [[
                'label' => self::name(),
                'status' => $total ? 'unsuccessful' : 'successful',
                'statuslabel' => $total
                    ? get_string('proctoring_proctorlink_warnings', 'local_unifiedgrader', $total)
                    : get_string('proctoring_proctorlink_nowarnings', 'local_unifiedgrader'),
                'issues' => $shown,
            ]],
        ];
    }
}
