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
 * The SMOWL Results API, as far as the grader needs it.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\proctoring;

/**
 * A class-wide read of a monitored activity's results, and whether the activity is monitored at all.
 *
 * An interface so tests can answer from a fixture instead of the network.
 */
interface results_api {
    /**
     * Every student's tool statuses and tripped alarms for one activity.
     *
     * @param string $activitytype SMOWL's activity type, e.g. "quiz".
     * @param int $activityid SMOWL's activity ID.
     * @return array{code: int, body: ?array} The HTTP status (0 when the
     *         request never got an answer) and the decoded JSON body, if any.
     */
    public function reasons(string $activitytype, int $activityid): array;

    /**
     * The monitoring tools switched on for one activity, which is how SMOWL says the activity is active.
     *
     * @param string $activitytype SMOWL's activity type, e.g. "quiz".
     * @param int $activityid SMOWL's activity ID.
     * @return array{code: int, body: ?array} 200 for an active activity; 400
     *         for one SMOWL has not been told to monitor.
     */
    public function active_services(string $activitytype, int $activityid): array;
}
