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

use local_unifiedgrader\proctoring\provider\smowl;

/**
 * Tests for the review-priority rule, with SMOWL's categories.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\proctoring\rule
 */
final class rule_test extends \advanced_testcase {
    /**
     * A tool entry as the service hands it to the rule.
     *
     * @param string $status SMOWL's status.
     * @param array $issues Category => count.
     * @return array
     */
    private function tool(string $status, array $issues = []): array {
        return ['status' => $status, 'issues' => $issues];
    }

    /**
     * The kind of alarm decides the colour, not how many there were.
     */
    public function test_kind_of_alarm_outranks_count(): void {
        $rule = new rule(smowl::DEFAULT_RED);

        $browsing = $rule->evaluate([
            'ComputerMonitoring' => $this->tool('UNSUCCESSFUL', ['WEB_NAVIGATION' => 93]),
        ]);
        $this->assertSame(rule::YELLOW, $browsing['level']);

        $program = $rule->evaluate([
            'FrontCamera' => $this->tool('SUCCESSFUL'),
            'ComputerMonitoring' => $this->tool('UNSUCCESSFUL', ['WEB_NAVIGATION' => 2, 'OPENED_PROGRAM' => 1]),
        ]);
        $this->assertSame(rule::RED, $program['level']);
        $this->assertSame(
            [
                ['key' => 'WEB_NAVIGATION', 'count' => 2, 'level' => rule::YELLOW],
                ['key' => 'OPENED_PROGRAM', 'count' => 1, 'level' => rule::RED],
            ],
            $program['tools'][1]['issues'],
        );
    }

    /**
     * Nothing tripped is green, and a passing tool's warnings do not count.
     */
    public function test_all_tools_passing_is_green(): void {
        $result = (new rule(smowl::DEFAULT_RED))->evaluate([
            'FrontCamera' => $this->tool('SUCCESSFUL'),
            'IpAnalysis' => $this->tool('SUCCESSFUL', ['SHARED_IP' => 1]),
        ]);
        $this->assertSame(rule::GREEN, $result['level']);
        $this->assertFalse($result['provisional']);
        $this->assertSame([], $result['tools'][1]['issues']);
    }

    /**
     * Green is only for a finished verdict: while evidence is still being reviewed, nothing against a student is "awaiting review".
     */
    public function test_pending_evidence_is_never_green(): void {
        $rule = new rule(smowl::DEFAULT_RED);

        $clean = $rule->evaluate([
            'FrontCamera' => $this->tool('PENDING'),
            'ComputerMonitoring' => $this->tool('SUCCESSFUL'),
        ]);
        $this->assertSame(rule::PENDING, $clean['level']);
        $this->assertFalse($clean['provisional']);

        // A tool can have passed so far and still have captures waiting.
        $partial = $rule->evaluate(['Audio' => ['status' => 'SUCCESSFUL', 'issues' => [], 'pending' => 3]]);
        $this->assertSame(rule::PENDING, $partial['level']);

        $waiting = $rule->evaluate(['FrontCamera' => $this->tool('PENDING')]);
        $this->assertSame(rule::PENDING, $waiting['level']);
        $this->assertFalse($waiting['provisional']);

        // Everything reviewed, nothing found: now it is green.
        $final = $rule->evaluate(['Audio' => ['status' => 'SUCCESSFUL', 'issues' => [], 'pending' => 0]]);
        $this->assertSame(rule::GREEN, $final['level']);
    }

    /**
     * An alarm already reported counts at once, and the result is provisional while review continues.
     */
    public function test_alarms_do_not_wait_for_review_to_finish(): void {
        $rule = new rule(smowl::DEFAULT_RED);

        $flagged = $rule->evaluate([
            'FrontCamera' => $this->tool('PENDING'),
            'ComputerMonitoring' => $this->tool('UNSUCCESSFUL', ['VIRTUAL_MACHINE' => 1]),
        ]);
        $this->assertSame(rule::RED, $flagged['level']);
        $this->assertTrue($flagged['provisional']);

        // What SMOWL sent for a real attempt: computer monitoring still pending, alarms already listed.
        $live = $rule->evaluate([
            'FrontCamera' => ['status' => 'PENDING', 'issues' => [], 'pending' => 3],
            'Audio' => ['status' => 'SUCCESSFUL', 'issues' => [], 'pending' => 3],
            'ComputerMonitoring' => $this->tool(
                'PENDING',
                ['WEB_NAVIGATION' => 10, 'OPENED_PROGRAM' => 2, 'PROGRAM_INTERACTION' => 3],
            ),
        ]);
        $this->assertSame(rule::RED, $live['level']);
        $this->assertTrue($live['provisional']);
        $this->assertSame(
            ['key' => 'OPENED_PROGRAM', 'count' => 2, 'level' => rule::RED],
            $live['tools'][2]['issues'][1],
        );

        // Only yellow kinds so far: yellow, and still provisional.
        $browsing = $rule->evaluate(['ComputerMonitoring' => $this->tool('PENDING', ['WEB_NAVIGATION' => 10])]);
        $this->assertSame(rule::YELLOW, $browsing['level']);
        $this->assertTrue($browsing['provisional']);
    }

    /**
     * A tool the student never started is red, unless the rule leaves it out.
     */
    public function test_tool_not_launched(): void {
        $tools = [
            'FrontCamera' => $this->tool('SUCCESSFUL'),
            'ComputerMonitoring' => $this->tool('NOT_LAUNCHED'),
        ];
        $this->assertSame(rule::RED, (new rule(smowl::DEFAULT_RED))->evaluate($tools)['level']);
        $leftout = new rule(array_diff(smowl::DEFAULT_RED, [rule::NOT_LAUNCHED]), [rule::NOT_LAUNCHED]);
        $this->assertSame(rule::GREEN, $leftout->evaluate($tools)['level']);

        // Left out, an unmonitored sitting must not come out green.
        $unmonitored = $leftout->evaluate([
            'FrontCamera' => $this->tool('NOT_LAUNCHED'),
        ]);
        $this->assertSame(rule::PENDING, $unmonitored['level']);
    }

    /**
     * A failed tool with no alarm listed, or an alarm the rule has never heard of, is yellow.
     */
    public function test_unexplained_and_unknown_alarms_are_yellow(): void {
        $rule = new rule(smowl::DEFAULT_RED);
        $this->assertSame(rule::YELLOW, $rule->evaluate(['IpAnalysis' => $this->tool('UNSUCCESSFUL')])['level']);
        $this->assertSame(
            rule::YELLOW,
            $rule->evaluate(['FrontCamera' => $this->tool('UNSUCCESSFUL', ['SOMETHING_NEW' => 4])])['level'],
        );
    }

    /**
     * An ignored alarm neither colours the student nor fails the tool on its own.
     */
    public function test_ignored_alarm_does_not_count(): void {
        $rule = new rule(smowl::DEFAULT_RED, ['WEB_NAVIGATION']);
        $result = $rule->evaluate([
            'FrontCamera' => $this->tool('SUCCESSFUL'),
            'ComputerMonitoring' => $this->tool('UNSUCCESSFUL', ['WEB_NAVIGATION' => 40]),
        ]);
        $this->assertSame(rule::GREEN, $result['level']);
    }

    /**
     * The site settings decide which alarms are red and which are left out.
     */
    public function test_from_config(): void {
        $this->resetAfterTest();

        // Never saved: the shipped defaults.
        unset_config('smowl_red', 'local_unifiedgrader');
        unset_config('smowl_ignore', 'local_unifiedgrader');
        $this->assertSame(rule::RED, rule::from_config('smowl', smowl::DEFAULT_RED)->category_level('MORE_THAN_ONE'));
        $this->assertSame(rule::YELLOW, rule::from_config('smowl', smowl::DEFAULT_RED)->category_level('WEB_NAVIGATION'));

        set_config('smowl_red', 'WEB_NAVIGATION,EXPEL', 'local_unifiedgrader');
        set_config('smowl_ignore', 'SHARED_IP', 'local_unifiedgrader');
        $rule = rule::from_config('smowl', smowl::DEFAULT_RED);
        $this->assertSame(rule::RED, $rule->category_level('WEB_NAVIGATION'));
        $this->assertSame(rule::YELLOW, $rule->category_level('MORE_THAN_ONE'));
        $this->assertSame(rule::IGNORE, $rule->category_level('SHARED_IP'));

        // Ticked in both lists by mistake: high priority wins, so the alarm is not hidden.
        set_config('smowl_ignore', 'SHARED_IP,EXPEL', 'local_unifiedgrader');
        $this->assertSame(rule::RED, rule::from_config('smowl', smowl::DEFAULT_RED)->category_level('EXPEL'));

        // Saved with nothing ticked: nothing is red.
        set_config('smowl_red', '', 'local_unifiedgrader');
        $this->assertSame(rule::YELLOW, rule::from_config('smowl', smowl::DEFAULT_RED)->category_level('MORE_THAN_ONE'));
    }
}
