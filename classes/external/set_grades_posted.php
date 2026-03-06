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
 * External function: set grades posted status.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_unifiedgrader\adapter\adapter_factory;

/**
 * Posts or unposts grades for an activity (hides/unhides the grade item).
 *
 * The hidden parameter supports three modes:
 *   0 = post grades (visible to students immediately)
 *   1 = hide grades (permanently hidden until manually posted)
 *   >1 = Unix timestamp (hidden until that date, then auto-visible)
 */
class set_grades_posted extends external_api {

    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'hidden' => new external_value(
                PARAM_INT,
                '0 = post (visible), 1 = hide permanently, or Unix timestamp = hide until',
            ),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @param int $hidden
     * @return array
     */
    public static function execute(int $cmid, int $hidden): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'hidden' => $hidden,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);

        // Block quiz grade posting unless the admin setting is enabled.
        $cm = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        if ($cm->modname === 'quiz' && empty(get_config('local_unifiedgrader', 'enable_quiz_post_grades'))) {
            throw new \moodle_exception('quiz_post_grades_disabled', 'local_unifiedgrader');
        }

        // Validate: hidden must be 0, 1, or a future timestamp.
        $hidden = $params['hidden'];
        if ($hidden < 0) {
            $hidden = 1;
        }

        $adapter = adapter_factory::create($params['cmid']);
        $adapter->set_grades_posted($hidden);

        return [
            'success' => true,
            'posted' => $adapter->are_grades_posted(),
            'hidden' => $adapter->get_grades_hidden_value(),
        ];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the operation succeeded'),
            'posted' => new external_value(PARAM_BOOL, 'Whether grades are currently visible to students'),
            'hidden' => new external_value(
                PARAM_INT,
                'Raw hidden value: 0 = visible, 1 = always hidden, >1 = hidden-until timestamp',
            ),
        ]);
    }
}
