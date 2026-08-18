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

namespace local_unifiedgrader\bbb;

/**
 * Tests for the Learning Dashboard JSON parser.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\bbb\dashboard_client
 */
final class dashboard_client_test extends \advanced_testcase {
    /**
     * Build dashboard JSON shaped like the real payload.
     *
     * Timestamps are milliseconds, as BBB sends them.
     *
     * @param array $users Each: [extid, name, intids => [[start, end], ...], talkms, messages].
     * @param int $endedon Meeting end in ms.
     * @return string
     */
    private function dashboard_json(array $users, int $endedon = 1000000600000): string {
        $payload = [
            'extId' => 'meeting-1',
            'name' => 'Test meeting',
            'users' => [],
            'polls' => [],
            'createdOn' => 1000000000000,
            'endedOn' => $endedon,
        ];
        foreach ($users as $i => $u) {
            $intids = [];
            foreach ($u['intids'] as $j => $span) {
                $session = ['registeredOn' => $span[0]];
                // A null end models a session still open when the data was written.
                if ($span[1] !== null) {
                    $session['leftOn'] = $span[1];
                }
                $intids["w_user{$i}_{$j}"] = [
                    'intId' => "w_user{$i}_{$j}",
                    'sessions' => [$session],
                ];
            }
            $payload['users']["key{$i}"] = [
                'userKey' => "{$u['extid']}-1",
                'extId' => $u['extid'],
                'name' => $u['name'],
                'intIds' => $intids,
                'talk' => ['totalTime' => $u['talkms'] ?? 0],
                'reactions' => [],
                'raiseHand' => [],
                'answers' => [],
                'totalOfMessages' => $u['messages'] ?? 0,
            ];
        }
        return json_encode($payload);
    }

    /**
     * The Moodle user id travels in extId, and attendance is summed across
     * every session the user held — a rejoin makes a fresh internal id.
     */
    public function test_parses_userid_and_sums_sessions_across_rejoins(): void {
        $json = $this->dashboard_json([
            [
                'extid' => '928',
                'name' => 'Rejoining Student',
                // Ten minutes, then a rejoin for another twenty.
                'intids' => [[1000000000000, 1000000600000], [1000000700000, 1000001900000]],
                'talkms' => 90000,
                'messages' => 3,
            ],
        ]);

        $parsed = dashboard_client::parse_json($json);

        $this->assertCount(1, $parsed['participants']);
        $p = $parsed['participants'][0];
        $this->assertSame('928', $p['extuserid']);
        $this->assertSame(1800, $p['duration'], 'Both sessions should be counted');
        $this->assertSame(90, $p['talks'], 'Talk time is milliseconds in the payload, seconds in the row');
        $this->assertSame(3, $p['chats']);
        // The old statistics page carried this; the dashboard does not.
        $this->assertNull($p['activityscore']);
    }

    /**
     * A session with no leftOn is closed off at the meeting end, so a recording
     * written while the meeting was still up doesn't report zero attendance.
     */
    public function test_open_session_is_closed_at_meeting_end(): void {
        $json = $this->dashboard_json(
            [[
                'extid' => '77',
                'name' => 'Still Connected',
                'intids' => [[1000000000000, null]],
            ]],
            1000000900000,
        );

        $parsed = dashboard_client::parse_json($json);

        $this->assertSame(900, $parsed['participants'][0]['duration']);
    }

    /**
     * The React shell served at the statistics URL is not dashboard data, and
     * must be rejected rather than read as a meeting nobody attended — an empty
     * roster is what makes the session filter fall back to showing everything.
     */
    public function test_non_dashboard_payload_is_rejected(): void {
        $shell = '<!doctype html><html><head><title>Learning Dashboard</title></head>'
            . '<body><div id="root"></div></body></html>';

        $this->assertNull(dashboard_client::parse_json($shell));
        $this->assertNull(dashboard_client::parse_json('{"something":"else"}'));
        $this->assertNull(dashboard_client::parse_json(''));
    }

    /**
     * A guest joins without a numeric extId, so that participant falls back to
     * name matching rather than being attributed to a user id of zero outright.
     */
    public function test_guest_without_numeric_extid_falls_back_to_name(): void {
        $json = $this->dashboard_json([
            ['extid' => 'w_guest_abc', 'name' => 'Guest Visitor', 'intids' => [[1000000000000, 1000000600000]]],
        ]);

        $parsed = dashboard_client::parse_json($json);
        $p = $parsed['participants'][0];

        $this->assertSame('w_guest_abc', $p['extuserid']);
        $this->assertSame('Guest Visitor', $p['fullname']);

        // The decision is made by resolve_userid(), so assert that directly.
        $method = new \ReflectionMethod(engagement_service::class, 'resolve_userid');
        $method->setAccessible(true);
        $namemap = ['guest visitor' => 4242, 'rejoining student' => 99];

        $this->assertSame(4242, $method->invoke(null, $p, $namemap), 'Guest resolves by name');
        $this->assertSame(
            928,
            $method->invoke(null, ['extuserid' => '928', 'fullname' => 'Rejoining Student'], $namemap),
            'A numeric extId is authoritative and must beat the name map',
        );
    }
}
