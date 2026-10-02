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
 * External function: delete note.
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
use local_unifiedgrader\notes_manager;

/**
 * Deletes a private teacher note.
 */
class delete_note extends external_api {
    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID (for capability check)'),
            'noteid' => new external_value(PARAM_INT, 'Note ID to delete'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @param int $noteid
     * @return array
     */
    public static function execute(int $cmid, int $noteid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'noteid' => $noteid,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:managenotes', $context);

        // Release the PHP session lock so concurrent AJAX from the same
        // teacher does not serialize behind this request. This handler
        // does not write to $SESSION.
        \core\session\manager::write_close();

        // The note must belong to this activity: the capability was checked here, not elsewhere.
        global $DB;
        $note = $DB->get_record('local_unifiedgrader_notes', ['id' => $params['noteid']], 'id, cmid, userid');
        if (!$note) {
            // Already gone: nothing to do.
            return ['success' => true];
        }
        if ((int) $note->cmid !== (int) $params['cmid']) {
            throw new \invalid_parameter_exception('Note does not belong to the given activity');
        }
        \local_unifiedgrader\access::require_student_access($context, (int) $note->userid);

        notes_manager::delete_note($params['noteid']);

        return ['success' => true];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the note was deleted'),
        ]);
    }
}
