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
 * External function: confirm the language of a student's submission.
 *
 * A grader confirms/overrides the detected submission language so local_nida can
 * translate feedback back into the correct language. Bridges to local_nida and
 * degrades gracefully (saved=false) when that plugin is absent.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Persists a grader-confirmed submission language via local_nida.
 */
class confirm_student_language extends external_api {
    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
            'attempt' => new external_value(PARAM_INT, 'Attempt number (0-based), -1 for latest'),
            'lang' => new external_value(PARAM_ALPHANUMEXT, 'Language code to confirm'),
        ]);
    }

    /**
     * Execute the function.
     *
     * The module context is resolved server-side from cmid and the grade
     * capability is required against it. The confirmed language is validated
     * against the course's configured target languages (plus English) before it
     * is handed to local_nida, which re-validates the (cmid, userid, attempt)
     * triple itself.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @param int $attempt Attempt number (0-based) or -1 for latest.
     * @param string $lang Language code to confirm.
     * @return array
     */
    public static function execute(int $cmid, int $userid, int $attempt, string $lang): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
            'attempt' => $attempt,
            'lang' => $lang,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);
        \local_unifiedgrader\access::require_student_access($context, (int) $params['userid']);

        \core\session\manager::write_close();

        // Degrade gracefully when local_nida is absent or predates the assign API.
        if (!class_exists('\local_nida\local\assign\submission_api')) {
            return ['saved' => false, 'resolvedlang' => ''];
        }

        // Validate the requested language against the allowed set: the course's
        // configured target languages plus English. Reject anything else so a
        // crafted request cannot store an arbitrary language code.
        $allowed = self::allowed_languages($context);
        if (!in_array($params['lang'], $allowed, true)) {
            throw new \moodle_exception('invalidlanguage', 'local_unifiedgrader');
        }

        try {
            \local_nida\local\assign\submission_api::confirm_language(
                (int) $params['cmid'],
                (int) $params['userid'],
                (int) $params['attempt'],
                (string) $params['lang'],
                (int) $USER->id,
            );
        } catch (\Throwable $e) {
            debugging(
                'local_nida submission_api::confirm_language failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER,
            );
            return ['saved' => false, 'resolvedlang' => ''];
        }

        return ['saved' => true, 'resolvedlang' => (string) $params['lang']];
    }

    /**
     * The languages a grader may confirm: the course target languages plus English.
     *
     * @param \context_module $context The module context.
     * @return string[] List of allowed language codes.
     */
    private static function allowed_languages(\context_module $context): array {
        $langs = ['en'];
        if (class_exists('\local_nida\local\enabled_courses')) {
            $courseid = (int) $context->get_course_context()->instanceid;
            try {
                $courselangs = (new \local_nida\local\enabled_courses())->languages_for($courseid);
                $langs = array_values(array_unique(array_merge($langs, $courselangs)));
            } catch (\Throwable $e) {
                debugging(
                    'local_nida enabled_courses::languages_for failed: ' . $e->getMessage(),
                    DEBUG_DEVELOPER,
                );
            }
        }
        return $langs;
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'saved' => new external_value(PARAM_BOOL, 'Whether the language was persisted'),
            'resolvedlang' => new external_value(PARAM_RAW, 'The resolved language code (empty when not saved)'),
        ]);
    }
}
