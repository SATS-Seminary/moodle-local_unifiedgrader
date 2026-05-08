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
 * Orchestrates BBB engagement-stats scraping: iterate recordings, parse pages,
 * match participant names to Moodle users, persist into local_unifiedgrader_bbbeng.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\bbb;

defined('MOODLE_INTERNAL') || die();

/**
 * Lazy fallback for missing analytics callbacks.
 *
 * Used by bbb_adapter when bigbluebuttonbn_logs has no Summary rows for a user.
 * Reads cached rows from local_unifiedgrader_bbbeng; if a cmid has never been
 * scraped, the caller can invoke refresh_for_cmid() to populate the cache.
 */
class engagement_service {
    /**
     * Read aggregated engagement totals for a user across all cached recordings.
     *
     * @param int $cmid
     * @param int $userid
     * @return array{sessioncount:int, duration:int, talks:int, chats:int,
     *               raisehand:int, polls:int, emojis:int, hasdata:bool}
     */
    public static function get_user_totals(int $cmid, int $userid): array {
        global $DB;

        $rows = $DB->get_records('local_unifiedgrader_bbbeng', [
            'cmid' => $cmid,
            'userid' => $userid,
        ]);

        $totals = [
            'sessioncount' => 0,
            'duration' => 0,
            'talks' => 0,
            'chats' => 0,
            'raisehand' => 0,
            'polls' => 0,
            'emojis' => 0,
            'hasdata' => false,
        ];
        foreach ($rows as $row) {
            $totals['sessioncount']++;
            $totals['duration']  += (int) $row->duration;
            $totals['talks']     += (int) $row->talks;
            $totals['chats']     += (int) $row->chats;
            $totals['raisehand'] += (int) $row->raisehand;
            $totals['polls']     += (int) $row->polls;
            $totals['emojis']    += (int) $row->emojis;
        }
        $totals['hasdata'] = $totals['sessioncount'] > 0;
        return $totals;
    }

    /**
     * Per-user attendance stats across all cached recordings for a cmid.
     *
     * Mirrors the shape of get_attendance_stats() in the adapter so the result
     * can be merged with the live bigbluebuttonbn_logs query.
     *
     * @param int $cmid
     * @return array<int, \stdClass> Keyed by userid → {sessioncount, lastattended}.
     */
    public static function get_attendance_stats(int $cmid): array {
        global $DB;

        $sql = "SELECT userid,
                       COUNT(*) AS sessioncount,
                       MAX(timefetched) AS lastattended
                  FROM {local_unifiedgrader_bbbeng}
                 WHERE cmid = :cmid AND userid > 0
              GROUP BY userid";
        return $DB->get_records_sql($sql, ['cmid' => $cmid]);
    }

    /**
     * Was this cmid ever successfully scraped?
     *
     * @param int $cmid
     * @return bool
     */
    public static function is_cached(int $cmid): bool {
        global $DB;
        return $DB->record_exists('local_unifiedgrader_bbbeng', ['cmid' => $cmid]);
    }

    /**
     * Refresh the cached engagement metrics for a BBB activity.
     *
     * Fetches every published recording's "statistics" playback URL, parses
     * the HTML, matches participants to enrolled users by fullname, and
     * upserts rows in local_unifiedgrader_bbbeng. Recordings that don't
     * expose a statistics playback type are skipped.
     *
     * @param int $cmid Course module id of the BBB activity.
     * @return array{recordings:int, parsed:int, matched:int, unmatched:int}
     */
    public static function refresh_for_cmid(int $cmid): array {
        global $DB;

        $cm = get_coursemodule_from_id('bigbluebuttonbn', $cmid, 0, false, MUST_EXIST);
        $instance = \mod_bigbluebuttonbn\instance::get_from_cmid($cmid);
        if (!$instance) {
            return ['recordings' => 0, 'parsed' => 0, 'matched' => 0, 'unmatched' => 0];
        }

        $recordings = \mod_bigbluebuttonbn\recording::get_recordings_for_instance($instance);
        $context = \context_module::instance($cmid);

        // Build name→userid index across users enrolled with the join capability,
        // so we only consider real participants. Names are normalised on both sides.
        $enrolled = get_enrolled_users($context, 'mod/bigbluebuttonbn:view', 0, 'u.id, u.firstname, u.lastname, u.alternatename, u.middlename, u.firstnamephonetic, u.lastnamephonetic');
        $namemap = [];
        foreach ($enrolled as $u) {
            $namemap[self::normalise_name(fullname($u))] = (int) $u->id;
        }

        $now = time();
        $stats = ['recordings' => 0, 'parsed' => 0, 'matched' => 0, 'unmatched' => 0];

        foreach ($recordings as $rec) {
            $stats['recordings']++;
            $statsurl = $rec->get_remote_playback_url('statistics');
            if (!$statsurl) {
                continue;
            }
            $parsed = stats_scraper::fetch_and_parse($statsurl);
            if (!$parsed || empty($parsed['participants'])) {
                continue;
            }
            $stats['parsed']++;

            $recordingid = (string) $rec->get('recordingid');

            foreach ($parsed['participants'] as $p) {
                $userid = $namemap[self::normalise_name($p['fullname'])] ?? 0;
                if ($userid > 0) {
                    $stats['matched']++;
                } else {
                    $stats['unmatched']++;
                }
                self::upsert_row($cmid, $recordingid, $userid, $p, $now);
            }
        }

        return $stats;
    }

    /**
     * Insert or update a single engagement row.
     *
     * @param int $cmid
     * @param string $recordingid
     * @param int $userid 0 when no Moodle user could be matched.
     * @param array $participant Decoded participant entry from stats_scraper.
     * @param int $now
     */
    private static function upsert_row(
        int $cmid,
        string $recordingid,
        int $userid,
        array $participant,
        int $now,
    ): void {
        global $DB;

        $row = (object) [
            'cmid'        => $cmid,
            'recordingid' => $recordingid,
            'userid'      => $userid,
            'bbbuid'      => $participant['bbbuid'] ?? null,
            'fullname'    => \core_text::substr($participant['fullname'] ?? '', 0, 255),
            'duration'    => (int) ($participant['duration'] ?? 0),
            'talks'       => (int) ($participant['talks'] ?? 0),
            'chats'       => (int) ($participant['chats'] ?? 0),
            'raisehand'   => (int) ($participant['raisehand'] ?? 0),
            'polls'       => (int) ($participant['polls'] ?? 0),
            'emojis'      => (int) ($participant['emojis'] ?? 0),
            'activityscore' => isset($participant['activityscore']) && $participant['activityscore'] !== null
                ? (float) $participant['activityscore']
                : null,
            'timefetched' => $now,
        ];

        $existing = $DB->get_record('local_unifiedgrader_bbbeng', [
            'cmid' => $cmid,
            'recordingid' => $recordingid,
            'userid' => $userid,
        ]);
        if ($existing) {
            $row->id = $existing->id;
            $DB->update_record('local_unifiedgrader_bbbeng', $row);
        } else {
            $DB->insert_record('local_unifiedgrader_bbbeng', $row);
        }
    }

    /**
     * Normalise a fullname for cross-source comparison (lowercase, collapse whitespace,
     * strip everything except letters/digits/spaces). BBB and Moodle use the same source
     * fullname when joining, so an exact match after normalisation is reliable in
     * common cases — locale-specific honorifics or punctuation drift will still slip
     * through, in which case the row lands as unmatched (userid=0) and is visible
     * to admins for manual reconciliation.
     *
     * @param string $name
     * @return string
     */
    public static function normalise_name(string $name): string {
        $name = \core_text::strtolower(trim($name));
        // Replace any non-letter/digit char (including hyphens, apostrophes,
        // commas) with a space, then collapse consecutive spaces. This means
        // "María-José" matches "María José".
        $name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name);
        $name = preg_replace('/\s+/u', ' ', $name);
        return trim($name ?? '');
    }
}
