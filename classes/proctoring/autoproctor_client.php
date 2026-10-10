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
 * HTTP client for the AutoProctor REST API (v2).
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
 * Asks AutoProctor about test attempts, one request each.
 *
 * A request names the test attempt in the path and proves it may ask with
 * hashedTestAttemptId: the test attempt ID, HMAC-SHA256 with the client
 * secret, base64. That is how the quizaccess_autoproctor plugin signs its own
 * (see its loadreport.php). The secret never leaves the server.
 *
 * Checked against the live API on 2026-10-09: a wrong or missing hash is a
 * 403, an attempt AutoProctor does not know is a 404, and both come back as
 * JSON with "status": "error".
 *
 * The requests go one after another. Moodle's parallel helper
 * (curl::download()) writes each response to the output, which would land in
 * the web service's reply.
 */
class autoproctor_client implements autoproctor_api {
    /** @var int Seconds after which no further request is started. */
    const BUDGET = 20;

    /**
     * Constructor.
     *
     * @param string $clientid AutoProctor client ID.
     * @param string $clientsecret AutoProctor client secret.
     * @param string $domain The API's origin, without a trailing slash.
     */
    public function __construct(
        /** @var string AutoProctor client ID. */
        private string $clientid,
        /** @var string AutoProctor client secret. */
        private string $clientsecret,
        /** @var string The API's origin. */
        private string $domain = 'https://www.autoproctor.co',
    ) {
    }

    /**
     * The results of some test attempts.
     *
     * Stops starting requests once the time budget is spent; the attempts
     * not reached are left out, for the caller to ask about next time.
     *
     * @param string[] $testattemptids AutoProctor's test attempt IDs.
     * @return array Test attempt ID => decoded response body; ['notfound' => true]
     *         for an attempt AutoProctor does not know; null where the request failed.
     */
    public function attempts(array $testattemptids): array {
        $results = [];
        $deadline = time() + self::BUDGET;
        foreach ($testattemptids as $id) {
            if (time() >= $deadline) {
                break;
            }
            $curl = new \curl();
            $raw = $curl->get($this->request_url($id), null, ['CURLOPT_CONNECTTIMEOUT' => 5, 'CURLOPT_TIMEOUT' => 10]);
            $code = $curl->get_errno() ? 0 : (int) ($curl->get_info()['http_code'] ?? 0);
            $body = is_string($raw) ? json_decode($raw, true) : null;
            $results[$id] = match (true) {
                $code === 404 => ['notfound' => true],
                $code === 200 && is_array($body) => $body,
                default => null,
            };
        }
        return $results;
    }

    /**
     * The signed URL for one test attempt.
     *
     * @param string $testattemptid AutoProctor's test attempt ID.
     * @return string
     */
    public function request_url(string $testattemptid): string {
        return $this->domain . '/api/v2/test-attempts/' . rawurlencode($testattemptid) . '/?' . http_build_query([
            'clientId' => $this->clientid,
            'hashedTestAttemptId' => base64_encode(hash_hmac('sha256', $testattemptid, $this->clientsecret, true)),
        ], '', '&');
    }
}
