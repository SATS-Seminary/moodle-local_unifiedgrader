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
 * Read per-user attendance from BigBlueButton's Learning Dashboard JSON.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\bbb;

/**
 * Client for a recording's learning_dashboard_data.json.
 *
 * Newer BigBlueButton builds serve the recording "statistics" URL as a React
 * app — a few hundred bytes of shell with no tables in it — so the HTML parser
 * in stats_scraper finds nothing there. The figures the dashboard displays come
 * from learning_dashboard_data.json alongside it, which is what we read instead.
 *
 * Two properties make this the better source, not merely a replacement:
 *
 *  - Participants carry `extId`, which mod_bigbluebuttonbn sets to the Moodle
 *    user id when it builds the join URL (see bigbluebutton_proxy::…, which
 *    sends `userID = $instance->get_user_id()`). Attendance therefore lands on
 *    the right user directly, rather than by matching display names — the
 *    weakness of the HTML path, where a student who joined under a shortened
 *    name was recorded as userid 0 and read as absent.
 *  - It exists for recordings already made, so attendance can be back-filled
 *    for past sessions. The analytics callback only ever helps going forward.
 *
 * Access is guarded by CloudFront signed cookies. Requesting the statistics
 * page itself sets them (scoped to that recording and time-limited), so the
 * fetch is two requests sharing a cookie jar — the JSON 403s on its own.
 */
class dashboard_client {
    /** @var string Filename the dashboard app fetches its data from. */
    private const DATA_FILE = 'learning_dashboard_data.json';

    /**
     * Fetch and parse one recording's dashboard data.
     *
     * @param string $statsurl Recording statistics URL, from
     *                         recording::get_remote_playback_url('statistics').
     * @param int $timeout Seconds, per request.
     * @return array|null Same shape as stats_scraper::fetch_and_parse(), with an
     *                    extra 'extuserid' per participant; null on any failure.
     */
    public static function fetch(string $statsurl, int $timeout = 15): ?array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        if (trim($statsurl) === '') {
            return null;
        }
        $base = rtrim(trim($statsurl), '/') . '/';

        // A cookie jar of our own rather than curl's shared default: the signed
        // cookies are scoped to one recording, and two recordings refreshed at
        // once would otherwise overwrite each other's credentials.
        $cookiefile = tempnam(make_temp_directory('local_unifiedgrader'), 'lad_');
        if ($cookiefile === false) {
            return null;
        }

        try {
            $curl = new \curl(['proxy' => true, 'cookie' => $cookiefile]);
            $curl->setopt([
                'CURLOPT_TIMEOUT' => $timeout,
                'CURLOPT_CONNECTTIMEOUT' => 5,
                'CURLOPT_FOLLOWLOCATION' => 1,
                'CURLOPT_MAXREDIRS' => 5,
            ]);

            // First request is for the signed cookies, not the body — the shell
            // HTML it returns is of no use to us.
            $curl->get($base);
            if ($curl->get_errno()) {
                return null;
            }

            $json = $curl->get($base . self::DATA_FILE);
            if ($curl->get_errno() || empty($json)) {
                return null;
            }
            $info = $curl->get_info();
            if (!empty($info['http_code']) && (int) $info['http_code'] >= 400) {
                return null;
            }

            return self::parse_json($json);
        } finally {
            @unlink($cookiefile);
        }
    }

    /**
     * Parse dashboard JSON — split out so tests run against fixtures.
     *
     * @param string $json
     * @return array|null {participants: array, pollcount: int}, or null when the
     *                    payload is not dashboard data.
     */
    public static function parse_json(string $json): ?array {
        $data = json_decode($json);
        if (!$data || !isset($data->users) || !is_object($data->users)) {
            return null;
        }

        // Meetings still running have no endedOn; sessions left open are closed
        // off at that point so a live meeting doesn't report a zero duration.
        $endedon = (int) ($data->endedOn ?? 0);

        $participants = [];
        foreach ($data->users as $user) {
            $participants[] = [
                'extuserid'   => (string) ($user->extId ?? ''),
                'bbbuid'      => self::first_internal_id($user),
                'fullname'    => (string) ($user->name ?? ''),
                'duration'    => self::sum_session_seconds($user, $endedon),
                // Talk time arrives in milliseconds; the column holds seconds.
                'talks'       => (int) round(((float) ($user->talk->totalTime ?? 0)) / 1000),
                'chats'       => (int) ($user->totalOfMessages ?? 0),
                'raisehand'   => is_array($user->raiseHand ?? null) ? count($user->raiseHand) : 0,
                'emojis'      => is_array($user->reactions ?? null) ? count($user->reactions) : 0,
                'polls'       => isset($user->answers) ? count((array) $user->answers) : 0,
                // The 0-10 Activity Score was computed by the old statistics page
                // and has no equivalent here — the dashboard shows "N/A" for it too.
                'activityscore' => null,
            ];
        }

        return [
            'participants' => $participants,
            'pollcount' => isset($data->polls) ? count((array) $data->polls) : 0,
        ];
    }

    /**
     * Total seconds a user was connected, across every session they held.
     *
     * A user gets a fresh internal id each time they rejoin, each with its own
     * session list, so attendance is the sum across all of them — which is what
     * the dashboard's own "Online time" column shows.
     *
     * @param \stdClass $user
     * @param int $endedon Meeting end in ms, used to close still-open sessions.
     * @return int Seconds.
     */
    private static function sum_session_seconds(\stdClass $user, int $endedon): int {
        $total = 0;
        foreach ((array) ($user->intIds ?? []) as $intid) {
            foreach ($intid->sessions ?? [] as $session) {
                $start = (int) ($session->registeredOn ?? 0);
                $end = (int) ($session->leftOn ?? 0);
                if ($end <= 0) {
                    $end = $endedon;
                }
                if ($start > 0 && $end > $start) {
                    $total += (int) (($end - $start) / 1000);
                }
            }
        }
        return $total;
    }

    /**
     * The user's first BBB internal id, kept for cross-referencing with the
     * statistics page.
     *
     * @param \stdClass $user
     * @return string|null
     */
    private static function first_internal_id(\stdClass $user): ?string {
        foreach ((array) ($user->intIds ?? []) as $key => $intid) {
            return (string) ($intid->intId ?? $key);
        }
        return null;
    }
}
