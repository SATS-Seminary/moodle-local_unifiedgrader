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
 * External function: save penalty.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_unifiedgrader\penalty_manager;

/**
 * Saves a grade penalty.
 */
class save_penalty extends external_api {

    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
            'category' => new external_value(PARAM_ALPHANUMEXT, 'Penalty category: wordcount or other'),
            'label' => new external_value(PARAM_TEXT, 'Custom label for other penalties', VALUE_DEFAULT, ''),
            'percentage' => new external_value(PARAM_INT, 'Penalty percentage (1-100)'),
            'penaltyid' => new external_value(PARAM_INT, 'Existing penalty ID (0 for new)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @param int $userid
     * @param string $category
     * @param string $label
     * @param int $percentage
     * @param int $penaltyid
     * @return array
     */
    public static function execute(
        int $cmid,
        int $userid,
        string $category,
        string $label,
        int $percentage,
        int $penaltyid = 0,
    ): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
            'category' => $category,
            'label' => $label,
            'percentage' => $percentage,
            'penaltyid' => $penaltyid,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);

        // Validate category.
        if (!in_array($params['category'], ['wordcount', 'other'])) {
            throw new \invalid_parameter_exception('Invalid penalty category: ' . $params['category']);
        }

        // Validate percentage.
        if ($params['percentage'] < 1 || $params['percentage'] > 100) {
            throw new \invalid_parameter_exception('Percentage must be between 1 and 100');
        }

        // For 'other', label is required.
        if ($params['category'] === 'other' && trim($params['label']) === '') {
            throw new \invalid_parameter_exception('Label is required for other penalties');
        }

        $id = penalty_manager::save_penalty(
            $params['cmid'],
            $params['userid'],
            $USER->id,
            $params['category'],
            $params['label'],
            $params['percentage'],
            $params['penaltyid'],
        );

        // Return the saved ID and the full updated list.
        return [
            'penaltyid' => $id,
            'penalties' => penalty_manager::get_penalties($params['cmid'], $params['userid']),
        ];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'penaltyid' => new external_value(PARAM_INT, 'The saved penalty ID'),
            'penalties' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Penalty ID'),
                    'cmid' => new external_value(PARAM_INT, 'Course module ID'),
                    'userid' => new external_value(PARAM_INT, 'Student user ID'),
                    'authorid' => new external_value(PARAM_INT, 'Author user ID'),
                    'category' => new external_value(PARAM_ALPHANUMEXT, 'Penalty category'),
                    'label' => new external_value(PARAM_TEXT, 'Custom label'),
                    'percentage' => new external_value(PARAM_INT, 'Penalty percentage'),
                    'timecreated' => new external_value(PARAM_INT, 'Time created'),
                    'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                ]),
            ),
        ]);
    }
}
