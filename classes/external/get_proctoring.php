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
 * External function: proctoring results for a quiz.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_unifiedgrader\access;
use local_unifiedgrader\proctoring\provider;
use local_unifiedgrader\proctoring\service;

/**
 * Loads each student's proctoring review priority and the findings behind it,
 * from whichever service proctors the quiz.
 *
 * The grader calls this after the page has drawn, so a slow or unreachable
 * service never holds up marking.
 */
class get_proctoring extends external_api {
    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'refresh' => new external_value(
                PARAM_BOOL,
                'Ask the service again instead of using the cached answer',
                VALUE_DEFAULT,
                false,
            ),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @param bool $refresh
     * @return array
     */
    public static function execute(int $cmid, bool $refresh = false): array {
        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid, 'refresh' => $refresh]);

        [, $cm] = get_course_and_cm_from_cmid($params['cmid']);
        $context = \context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);
        require_capability('local/unifiedgrader:viewproctoring', $context);
        \core\session\manager::write_close();

        $empty = ['state' => provider::STATE_NONE, 'provider' => '', 'updated' => '', 'reporturl' => '', 'users' => []];
        if (!service::applies($cm, $context)) {
            return $empty;
        }

        $data = service::load($cm, $params['refresh']);
        if ($data['state'] === provider::STATE_NONE) {
            return $empty;
        }

        $visible = self::visible_userids($cm, $context);
        $users = [];
        foreach ($data['users'] as $userid => $result) {
            if (isset($visible[$userid])) {
                $attempts = [];
                foreach ($result['attempts'] as $number => $attempt) {
                    $attempts[] = ['attempt' => $number] + self::export_result($attempt);
                }
                $users[] = ['id' => $userid, 'attempts' => $attempts] + self::export_result($result);
            }
        }

        return [
            'state' => $data['state'],
            'provider' => $data['provider'],
            'updated' => get_string(
                'proctoring_updated',
                'local_unifiedgrader',
                userdate($data['fetchedat'], get_string('strftimetime', 'langconfig')),
            ),
            'reporturl' => $data['reporturl'],
            'users' => $users,
        ];
    }

    /**
     * The students this teacher may see in the activity.
     *
     * @param \cm_info $cm The activity.
     * @param \context_module $context Its context.
     * @return array User ID => true.
     */
    private static function visible_userids(\cm_info $cm, \context_module $context): array {
        $groupids = access::visible_group_ids($cm, $context, []);
        if ($groupids === null) {
            return [];
        }
        $ids = [];
        foreach ($groupids ?: [0] as $groupid) {
            $ids += get_enrolled_users($context, '', $groupid, 'u.id');
        }
        return array_fill_keys(array_keys($ids), true);
    }

    /**
     * A result, for a student or one of their attempts, as the grader shows it.
     *
     * @param array $result level, provisional, reporturl and tools.
     * @return array
     */
    private static function export_result(array $result): array {
        return [
            'level' => $result['level'],
            'levellabel' => get_string('proctoring_level_' . $result['level'], 'local_unifiedgrader'),
            'provisional' => $result['provisional'],
            'reporturl' => $result['reporturl'],
            'tools' => $result['tools'],
        ];
    }

    /**
     * What a result is made of, shared by a student and each of their attempts.
     *
     * @return array
     */
    private static function result_structure(): array {
        return [
            'level' => new external_value(PARAM_ALPHA, 'red, yellow, green or pending'),
            'levellabel' => new external_value(PARAM_TEXT, 'The priority in words'),
            'provisional' => new external_value(PARAM_BOOL, 'Whether the service is still reviewing some evidence'),
            'reporturl' => new external_value(PARAM_URL, 'Where to review this evidence, or empty'),
            'tools' => new external_multiple_structure(new external_single_structure([
                'label' => new external_value(PARAM_TEXT, 'Monitoring tool, or the service itself'),
                'status' => new external_value(PARAM_ALPHAEXT, 'The tool\'s status, as a keyword'),
                'statuslabel' => new external_value(PARAM_TEXT, 'The status in words'),
                'issues' => new external_multiple_structure(new external_single_structure([
                    'label' => new external_value(PARAM_TEXT, 'Finding'),
                    'count' => new external_value(PARAM_INT, 'How many times, or 0 where a count means nothing'),
                    'level' => new external_value(PARAM_ALPHA, 'red, yellow or info'),
                ])),
            ])),
        ];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'state' => new external_value(PARAM_ALPHA, 'ok, notconfigured, unavailable or none'),
            'provider' => new external_value(PARAM_TEXT, 'The proctoring service\'s name'),
            'updated' => new external_value(PARAM_TEXT, 'When the service was last asked, for display'),
            'reporturl' => new external_value(PARAM_URL, 'Where to review the quiz\'s evidence, or empty', VALUE_DEFAULT, ''),
            'users' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Student user ID'),
                'attempts' => new external_multiple_structure(
                    new external_single_structure(
                        ['attempt' => new external_value(PARAM_INT, 'Quiz attempt number')] + self::result_structure(),
                    ),
                    'One per attempt, for a service that keeps results per attempt',
                ),
            ] + self::result_structure())),
        ]);
    }
}
