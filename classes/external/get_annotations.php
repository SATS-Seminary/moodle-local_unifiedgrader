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
 * External function: get annotations for a student submission file.
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
use local_unifiedgrader\annotation_manager;

/**
 * Returns all annotations for a specific file in an activity for a student.
 */
class get_annotations extends external_api {

    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
            'fileid' => new external_value(PARAM_INT, 'File ID'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @param int $userid
     * @param int $fileid
     * @return array
     */
    public static function execute(int $cmid, int $userid, int $fileid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
            'fileid' => $fileid,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);

        return annotation_manager::get_annotations(
            $params['cmid'],
            $params['userid'],
            $params['fileid'],
        );
    }

    /**
     * Return definition.
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Annotation ID'),
                'cmid' => new external_value(PARAM_INT, 'Course module ID'),
                'userid' => new external_value(PARAM_INT, 'Student user ID'),
                'authorid' => new external_value(PARAM_INT, 'Author user ID'),
                'fileid' => new external_value(PARAM_INT, 'File ID'),
                'pagenum' => new external_value(PARAM_INT, 'Page number'),
                'annotationdata' => new external_value(PARAM_RAW, 'Fabric.js canvas JSON'),
                'timecreated' => new external_value(PARAM_INT, 'Time created'),
                'timemodified' => new external_value(PARAM_INT, 'Time modified'),
            ]),
        );
    }
}
