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
 * HTTP client for the SMOWL Results API (v2).
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\proctoring;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');

/**
 * Talks to results-api.smowltech.net with the entity name and API key.
 *
 * The key never leaves the server: every call is made from PHP.
 */
class smowl_client implements results_api {
    /** @var string Base URL of every request. */
    const BASE_URL = 'https://results-api.smowltech.net/index.php/v2/';

    /**
     * Constructor.
     *
     * @param string $entity The entity name SMOWL assigned (Basic auth user).
     * @param string $apikey The API key (Basic auth password).
     */
    public function __construct(
        /** @var string The entity name. */
        private string $entity,
        /** @var string The API key. */
        private string $apikey,
    ) {
    }

    /**
     * Every student's tool statuses and tripped alarms for one activity.
     *
     * @param string $activitytype SMOWL's activity type, e.g. "quiz".
     * @param int $activityid SMOWL's activity ID.
     * @return array{code: int, body: ?array}
     */
    public function reasons(string $activitytype, int $activityid): array {
        return $this->post('results/reasons', $activitytype, $activityid);
    }

    /**
     * The monitoring tools switched on for one activity.
     *
     * The same registry SMOWL's Moodle block writes to when a quiz is
     * switched on (configs/activeServices/addActivity). Seen on the live API
     * on 2026-10-09: a quiz that was never switched on answers 400, "No data
     * available for the submitted activity Type and Id."; one that is answers
     * 200 with its entry keyed by type and ID ("quiz70"), listing each tool
     * as true or false and "enabled": true.
     *
     * @param string $activitytype SMOWL's activity type, e.g. "quiz".
     * @param int $activityid SMOWL's activity ID.
     * @return array{code: int, body: ?array}
     */
    public function active_services(string $activitytype, int $activityid): array {
        return $this->post('configs/activeServices/get', $activitytype, $activityid);
    }

    /**
     * Ask about one activity. SMOWL takes the two fields form-encoded.
     *
     * @param string $path Path under the API's base URL.
     * @param string $activitytype SMOWL's activity type.
     * @param int $activityid SMOWL's activity ID.
     * @return array{code: int, body: ?array} code is 0 when the request never got an answer.
     */
    private function post(string $path, string $activitytype, int $activityid): array {
        $curl = new \curl();
        $raw = $curl->post(
            self::BASE_URL . $path,
            http_build_query(['activityType' => $activitytype, 'activityId' => $activityid], '', '&'),
            [
                'CURLOPT_HTTPAUTH' => CURLAUTH_BASIC,
                'CURLOPT_USERPWD' => $this->entity . ':' . $this->apikey,
                'CURLOPT_CONNECTTIMEOUT' => 5,
                'CURLOPT_TIMEOUT' => 15,
            ],
        );
        if ($curl->get_errno()) {
            return ['code' => 0, 'body' => null];
        }
        $body = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        return [
            'code' => (int) ($curl->get_info()['http_code'] ?? 0),
            'body' => is_array($body) ? $body : null,
        ];
    }
}
