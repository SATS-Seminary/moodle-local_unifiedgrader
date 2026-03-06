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
 * Helper for extracting course codes from Moodle course short names.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader;

defined('MOODLE_INTERNAL') || die();

/**
 * Extracts course codes from course short names using a configurable regex.
 */
class course_code_helper {

    /**
     * Extract a course code from a course short name using the admin-configured regex.
     *
     * If the regex is empty or doesn't match, the full short name is returned.
     *
     * @param string $shortname The course short name.
     * @return string The extracted course code or the full short name.
     */
    public static function extract_code(string $shortname): string {
        $regex = get_config('local_unifiedgrader', 'coursecode_regex');

        if (empty($regex)) {
            return $shortname;
        }

        if (@preg_match($regex, $shortname, $matches)) {
            // Return the first captured group if available, otherwise the full match.
            return $matches[1] ?? $matches[0];
        }

        return $shortname;
    }

    /**
     * Extract a course code from a course ID.
     *
     * Looks up the course short name and applies the regex.
     *
     * @param int $courseid The Moodle course ID.
     * @return string The extracted course code or the full short name.
     */
    public static function extract_code_from_courseid(int $courseid): string {
        global $DB;

        $shortname = $DB->get_field('course', 'shortname', ['id' => $courseid]);
        if ($shortname === false) {
            return '';
        }

        return self::extract_code($shortname);
    }
}
