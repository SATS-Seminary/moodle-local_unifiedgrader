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
 * External function: save note.
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
 * Saves a private teacher note.
 */
class save_note extends external_api {
    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
            'content' => new external_value(PARAM_RAW, 'Note content'),
            'noteid' => new external_value(PARAM_INT, 'Existing note ID (0 for new)', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @param int $userid
     * @param string $content
     * @param int $noteid
     * @return array
     */
    public static function execute(int $cmid, int $userid, string $content, int $noteid = 0): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
            'content' => $content,
            'noteid' => $noteid,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:managenotes', $context);
        \local_unifiedgrader\access::require_student_access($context, (int) $params['userid']);

        // Release the PHP session lock so concurrent AJAX from the same
        // teacher does not serialize behind this request. This handler
        // does not write to $SESSION.
        \core\session\manager::write_close();

        // An existing note must be this student's, in this activity.
        if ($params['noteid'] > 0) {
            global $DB;
            $note = $DB->get_record('local_unifiedgrader_notes', ['id' => $params['noteid']], 'id, cmid, userid');
            if (!$note || (int) $note->cmid !== (int) $params['cmid'] || (int) $note->userid !== (int) $params['userid']) {
                throw new \invalid_parameter_exception('Note does not belong to the given activity and student');
            }
        }

        $id = notes_manager::save_note(
            $params['cmid'],
            $params['userid'],
            $USER->id,
            $params['content'],
            $params['noteid'],
        );

        return ['noteid' => $id];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'noteid' => new external_value(PARAM_INT, 'The saved note ID'),
        ]);
    }
}
