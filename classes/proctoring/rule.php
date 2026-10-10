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
 * Turns SMOWL's tool statuses and alarms into a review priority.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\proctoring;

/**
 * The red / yellow / green rule for services that report alarms, not a verdict.
 *
 * SMOWL and ProctorLink say what happened (a second person, a covered webcam)
 * but give no priority. The priority here follows the kind of alarm, not how
 * many there were: one alarm in a red category is red, any number of others is
 * yellow, and none is green. Which categories are red, and which are left out
 * altogether, are site settings, kept per service.
 */
class rule {
    /** @var string High review priority. */
    const RED = 'red';
    /** @var string Medium review priority. */
    const YELLOW = 'yellow';
    /** @var string Low review priority: nothing tripped. */
    const GREEN = 'green';
    /** @var string No tool has a result yet. */
    const PENDING = 'pending';
    /** @var string A category the rule leaves out. */
    const IGNORE = 'ignore';

    /** @var string The category for a tool the student never started. */
    const NOT_LAUNCHED = 'NOT_LAUNCHED';

    /** @var int[] How the levels compare: the higher, the more it needs a look. */
    const RANK = [self::PENDING => 0, self::GREEN => 1, self::YELLOW => 2, self::RED => 3];

    /**
     * Constructor.
     *
     * @param string[] $red Categories that make a student red.
     * @param string[] $ignored Categories that do not count at all.
     */
    public function __construct(
        /** @var string[] Categories that make a student red. */
        private array $red = [],
        /** @var string[] Categories that do not count at all. */
        private array $ignored = [],
    ) {
    }

    /**
     * A service's rule as the site has it configured.
     *
     * @param string $service The provider key; its settings are {service}_red and {service}_ignore.
     * @param string[] $defaultred Categories that are red until an administrator says otherwise.
     * @return self
     */
    public static function from_config(string $service, array $defaultred): self {
        $red = get_config('local_unifiedgrader', $service . '_red');
        $ignored = get_config('local_unifiedgrader', $service . '_ignore');
        return new self(
            $red === false ? $defaultred : array_filter(explode(',', (string) $red)),
            array_filter(explode(',', (string) $ignored)),
        );
    }

    /**
     * How one alarm category counts.
     *
     * A category the service adds later, and the settings do not list, is yellow: it
     * shows up for review without being called serious.
     *
     * @param string $category The alarm key, e.g. MORE_THAN_ONE.
     * @return string RED, YELLOW or IGNORE.
     */
    public function category_level(string $category): string {
        // Ticked as both high priority and left out, it is high priority: a
        // mistake on the settings page must not hide a serious alarm.
        if (in_array($category, $this->red, true)) {
            return self::RED;
        }
        return in_array($category, $this->ignored, true) ? self::IGNORE : self::YELLOW;
    }

    /**
     * Work out one student's priority for one activity.
     *
     * Statuses are SMOWL's, which the other services are mapped onto.
     *
     * Green is only ever a finished verdict. While any evidence is still
     * waiting on review (a tool that is PENDING, or one that passed so far
     * but has captures left to validate), a student with nothing against
     * them is "awaiting review", not low priority: saying low before the
     * evidence is in would be a false all-clear.
     *
     * Red and yellow do not wait. An alarm already reported counts at once,
     * even from a tool whose review is not finished, and the result is marked
     * provisional because more may follow. A tool that passed contributes
     * nothing even when it lists warnings, since they stayed under the alarm
     * threshold.
     *
     * @param array $tools Tool name => ['status' => string, 'issues' => [category => count], 'pending' => int].
     * @return array{level: string, provisional: bool, tools: array} The tools come
     *         back as a list, each issue carrying its own level.
     */
    public function evaluate(array $tools): array {
        $levels = [];
        $finished = 0;
        $waiting = false;
        $out = [];

        foreach ($tools as $name => $tool) {
            $status = (string) ($tool['status'] ?? '');
            $issues = [];

            if ($status === self::NOT_LAUNCHED) {
                // Left out by the rule, it says nothing either way: it must not
                // turn a student who was never monitored green.
                $level = $this->category_level(self::NOT_LAUNCHED);
                if ($level !== self::IGNORE) {
                    $finished++;
                    $levels[] = $level;
                }
            } else if ($status === 'UNSUCCESSFUL' || $status === 'PENDING') {
                if ($status === 'PENDING') {
                    $waiting = true;
                } else {
                    $finished++;
                }
                foreach ($tool['issues'] ?? [] as $category => $count) {
                    $level = $this->category_level((string) $category);
                    $issues[] = ['key' => (string) $category, 'count' => (int) $count, 'level' => $level];
                    $levels[] = $level;
                }
                // The tool failed without saying why: still worth a look.
                if ($status === 'UNSUCCESSFUL' && !$issues) {
                    $levels[] = self::YELLOW;
                }
            } else if ($status === 'SUCCESSFUL') {
                $finished++;
                // Passed so far, with captures still to be validated.
                $waiting = $waiting || !empty($tool['pending']);
            }

            $out[] = ['name' => (string) $name, 'status' => $status, 'issues' => $issues];
        }

        if (in_array(self::RED, $levels, true)) {
            $level = self::RED;
        } else if (in_array(self::YELLOW, $levels, true)) {
            $level = self::YELLOW;
        } else {
            $level = $finished && !$waiting ? self::GREEN : self::PENDING;
        }

        return ['level' => $level, 'provisional' => $waiting && $level !== self::PENDING, 'tools' => $out];
    }
}
