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
 * The AutoProctor REST API, as far as the grader needs it.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\proctoring;

/**
 * Reads test attempts' proctoring results.
 *
 * An interface so tests can answer from a fixture instead of the network.
 */
interface autoproctor_api {
    /**
     * The results of some test attempts.
     *
     * @param string[] $testattemptids AutoProctor's test attempt IDs.
     * @return array Test attempt ID => decoded response body; ['notfound' => true]
     *         for an attempt AutoProctor does not know; null where the request
     *         failed. An attempt there was no time to ask about is left out.
     */
    public function attempts(array $testattemptids): array;
}
