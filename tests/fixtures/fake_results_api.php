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

/**
 * A SMOWL Results API that answers from memory and counts the calls.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_results_api implements results_api {
    /** @var array[] Each call made, as [activity type, activity ID]. */
    public array $calls = [];

    /** @var array[] Each "is it switched on" call made. */
    public array $activecalls = [];

    /**
     * Constructor.
     *
     * @param int $code HTTP status to answer with.
     * @param array|null $body Decoded JSON body to answer with.
     * @param int $activecode HTTP status for "is this activity switched on".
     * @param bool $enabled Whether a quiz in the registry is still switched on.
     */
    public function __construct(
        /** @var int HTTP status to answer with. */
        public int $code = 200,
        /** @var array|null Decoded JSON body to answer with. */
        public ?array $body = null,
        /** @var int HTTP status for "is this activity switched on": 200 yes, 400 no. */
        public int $activecode = 400,
        /** @var bool Whether a quiz in the registry is still switched on. */
        public bool $enabled = true,
    ) {
    }

    /**
     * Say whether the activity is switched on.
     *
     * @param string $activitytype
     * @param int $activityid
     * @return array{code: int, body: ?array}
     */
    public function active_services(string $activitytype, int $activityid): array {
        $this->activecalls[] = [$activitytype, $activityid];
        // The shape the live API answers with for a quiz that is in its registry.
        $entry = [
            'activityType' => $activitytype,
            'activityId' => (string) $activityid,
            'FrontCamera' => true,
            'ComputerMonitoring' => true,
            'enabled' => $this->enabled,
        ];
        return ['code' => $this->activecode, 'body' => $this->activecode === 200 ? [$activitytype . $activityid => $entry] : null];
    }

    /**
     * Record the call and give the canned answer.
     *
     * @param string $activitytype
     * @param int $activityid
     * @return array{code: int, body: ?array}
     */
    public function reasons(string $activitytype, int $activityid): array {
        $this->calls[] = [$activitytype, $activityid];
        return ['code' => $this->code, 'body' => $this->body];
    }

    /**
     * A results/reasons body shaped like SMOWL's documented examples.
     *
     * Each student also carries another activity, as real responses do.
     *
     * @param string $activitykey Activity type and ID joined, e.g. "quiz42".
     * @param array $users SMOWL user ID => [tool name => tool object].
     * @return array
     */
    public static function body(string $activitykey, array $users): array {
        $out = [];
        foreach ($users as $userid => $tools) {
            $out[] = [
                'userId' => (string) $userid,
                'globalStatus' => 'UNSUCCESSFUL',
                'activities' => [
                    'quiz0001' => ['FrontCamera' => ['status' => 'NOT_LAUNCHED']],
                    $activitykey => $tools,
                ],
            ];
        }
        return [
            'users' => $out,
            'request_info' => ['status' => 'OK', 'error' => false, 'requestDateTime' => '2026-10-08 09:00:00', 'speed' => 0.1],
        ];
    }
}
