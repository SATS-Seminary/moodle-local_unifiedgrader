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

/**
 * An AutoProctor API that answers from memory and counts the calls.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_autoproctor_api implements autoproctor_api {
    /** @var array[] Each call made: the test attempt IDs asked for. */
    public array $calls = [];

    /**
     * Constructor.
     *
     * @param array $bodies Test attempt ID => response body. An ID left out fails.
     */
    public function __construct(
        /** @var array Test attempt ID => response body. */
        public array $bodies = [],
    ) {
    }

    /**
     * Record the call and give the canned answers.
     *
     * @param string[] $testattemptids
     * @return array
     */
    public function attempts(array $testattemptids): array {
        $this->calls[] = array_values($testattemptids);
        $results = [];
        foreach ($testattemptids as $id) {
            $results[$id] = $this->bodies[$id] ?? null;
        }
        return $results;
    }
}
