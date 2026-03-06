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
 * External function: delete penalty.
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
use local_unifiedgrader\penalty_manager;

/**
 * Deletes a grade penalty.
 */
class delete_penalty extends external_api {

    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID (for capability check)'),
            'penaltyid' => new external_value(PARAM_INT, 'Penalty ID to delete'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @param int $penaltyid
     * @return array
     */
    public static function execute(int $cmid, int $penaltyid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'penaltyid' => $penaltyid,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);

        // Prevent deletion of auto-managed late penalties.
        global $DB;
        $record = $DB->get_record('local_unifiedgrader_penalty', ['id' => $params['penaltyid']]);
        if ($record && $record->category === 'late') {
            throw new \moodle_exception('cannotdeleteautopenalty', 'local_unifiedgrader');
        }

        penalty_manager::delete_penalty($params['penaltyid']);

        return ['success' => true];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the penalty was deleted'),
        ]);
    }
}
