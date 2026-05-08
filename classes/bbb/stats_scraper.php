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
 * Scrape per-user engagement metrics from a BigBlueButton "statistics"
 * playback page when the analytics callback never reached Moodle.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\bbb;

defined('MOODLE_INTERNAL') || die();

/**
 * Pure parser for the BBB statistics playback HTML.
 *
 * The page is a self-contained static HTML bundle generated per recording by
 * BBB's playback module — every metric is rendered inline. There is no JSON
 * sibling and the "Download CSV" button generates the CSV client-side from the
 * same DOM. So we parse the HTML directly.
 *
 * Two tables are extracted:
 *   #overview-table  — name, duration, joined, left, activity score
 *   #attention-table — name, talk-time, messages, emojis, hand-raises
 *
 * Polling counts are surfaced via the "Polling (N)" header but per-user poll
 * vote totals are not in the static HTML for every BBB version, so we expose
 * a meeting-level poll count separately and leave per-user polls at zero
 * unless detected.
 */
class stats_scraper {
    /**
     * Fetch and parse a statistics playback URL.
     *
     * @param string $url Public CloudFront URL from recording->get_remote_playback_url('statistics').
     * @param int $timeout Seconds.
     * @return array|null Array shape:
     *   ['participants' => [['bbbuid' => 'w_xxx', 'fullname' => 'Jane', 'duration' => 1800,
     *                        'talks' => 45, 'chats' => 3, 'emojis' => 1, 'raisehand' => 0,
     *                        'polls' => 0, 'activityscore' => 8.5], ...],
     *    'pollcount' => 0]
     *   or null on failure.
     */
    public static function fetch_and_parse(string $url, int $timeout = 15): ?array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl(['proxy' => true]);
        $curl->setopt([
            'CURLOPT_TIMEOUT' => $timeout,
            'CURLOPT_CONNECTTIMEOUT' => 5,
            'CURLOPT_FOLLOWLOCATION' => 1,
            'CURLOPT_MAXREDIRS' => 5,
        ]);
        $html = $curl->get($url);

        if ($curl->get_errno() || empty($html)) {
            return null;
        }
        $info = $curl->get_info();
        if (!empty($info['http_code']) && (int) $info['http_code'] >= 400) {
            return null;
        }

        return self::parse_html($html);
    }

    /**
     * Parse statistics HTML — split out so tests can run against fixtures
     * without a network call.
     *
     * @param string $html
     * @return array|null
     */
    public static function parse_html(string $html): ?array {
        if (empty($html) || stripos($html, '<table') === false) {
            return null;
        }

        // libxml emits warnings on imperfect HTML; suppress them and restore on exit.
        $previous = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $loaded = $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return null;
        }

        $xpath = new \DOMXPath($doc);

        $byuid = [];

        // OVERVIEW table — duration + activity score keyed by data-uid.
        $overviewrows = $xpath->query('//table[@id="overview-table"]//tr[contains(@class,"participant")]');
        foreach ($overviewrows as $row) {
            $namecell = $xpath->query('.//td[contains(@class,"name-cell")]', $row)->item(0);
            if (!$namecell) {
                continue;
            }
            $bbbuid = (string) $namecell->getAttribute('data-uid');
            if ($bbbuid === '') {
                continue;
            }
            $namenode = $xpath->query('.//h4[@id="name"]', $namecell)->item(0);
            $fullname = $namenode ? trim($namenode->textContent) : '';

            $cells = $xpath->query('./td', $row);
            // Cell order: name | duration | joined | left | activity.
            $duration = self::parse_hms_seconds(self::cell_text($cells, 1));
            $activityscoretxt = self::cell_text($cells, 4);
            $activityscore = is_numeric($activityscoretxt) ? (float) $activityscoretxt : null;

            $byuid[$bbbuid] = [
                'bbbuid' => $bbbuid,
                'fullname' => $fullname,
                'duration' => $duration,
                'talks' => 0,
                'chats' => 0,
                'emojis' => 0,
                'raisehand' => 0,
                'polls' => 0,
                'activityscore' => $activityscore,
            ];
        }

        // ATTENTION table — talk-time, messages, emojis, hand-raises.
        $attentionrows = $xpath->query('//table[@id="attention-table"]//tr[contains(@class,"participant")]');
        foreach ($attentionrows as $row) {
            $namecell = $xpath->query('.//td[contains(@class,"name-cell")]', $row)->item(0);
            if (!$namecell) {
                continue;
            }
            $bbbuid = (string) $namecell->getAttribute('data-uid');
            if ($bbbuid === '' || !isset($byuid[$bbbuid])) {
                continue;
            }
            $cells = $xpath->query('./td', $row);
            // Cell order: name | talk time | messages | emojis | hand raises.
            $byuid[$bbbuid]['talks']     = self::parse_hms_seconds(self::cell_text($cells, 1));
            $byuid[$bbbuid]['chats']     = (int) self::cell_text($cells, 2);
            $byuid[$bbbuid]['emojis']    = (int) self::cell_text($cells, 3);
            $byuid[$bbbuid]['raisehand'] = (int) self::cell_text($cells, 4);
        }

        // Meeting-level poll count, e.g. "Polling (3)".
        $pollheader = $xpath->query('//div[@id="polling"]//h1')->item(0);
        $pollcount = 0;
        if ($pollheader && preg_match('/\((\d+)\)/', $pollheader->textContent, $m)) {
            $pollcount = (int) $m[1];
        }

        return [
            'participants' => array_values($byuid),
            'pollcount' => $pollcount,
        ];
    }

    /**
     * Extract trimmed text from a table cell at $index in the cells nodelist.
     *
     * @param \DOMNodeList $cells
     * @param int $index
     * @return string
     */
    private static function cell_text(\DOMNodeList $cells, int $index): string {
        $node = $cells->item($index);
        if (!$node) {
            return '';
        }
        // The actual text usually lives inside a <p> child.
        $p = $node->getElementsByTagName('p')->item(0);
        return trim(($p ?? $node)->textContent);
    }

    /**
     * Convert "00:04:33" or "04:33" to seconds. Returns 0 for "-" or non-matching strings.
     *
     * @param string $value
     * @return int
     */
    public static function parse_hms_seconds(string $value): int {
        $value = trim($value);
        if ($value === '' || $value === '-') {
            return 0;
        }
        if (!preg_match('/^(?:(\d+):)?(\d+):(\d+)$/', $value, $m)) {
            return 0;
        }
        $hours = isset($m[1]) && $m[1] !== '' ? (int) $m[1] : 0;
        $minutes = (int) $m[2];
        $seconds = (int) $m[3];
        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }
}
