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
 * External function: get annotations for the current student's own submission file.
 *
 * This is the student-safe counterpart of get_annotations. It forces userid to
 * $USER->id, validates that the grade has been released, and that the file
 * belongs to the student's own submission.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_unifiedgrader\adapter\adapter_factory;
use local_unifiedgrader\annotation_manager;

/**
 * Returns all annotations for the current user's own submission file.
 */
class get_student_annotations extends external_api {
    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'fileid' => new external_value(PARAM_INT, 'File ID'),
            'attempt' => new external_value(
                PARAM_INT,
                'Attempt number the viewer is currently displaying, or -1 for latest',
                VALUE_DEFAULT,
                -1,
            ),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @param int $fileid
     * @param int $attempt Attempt number the feedback view is currently displaying, or -1 for latest.
     * @return array
     */
    public static function execute(int $cmid, int $fileid, int $attempt = -1): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'fileid' => $fileid,
            'attempt' => $attempt,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:viewfeedback', $context);

        // Release the PHP session lock so concurrent AJAX from the same
        // teacher does not serialize behind this request. This handler
        // does not write to $SESSION.
        \core\session\manager::write_close();

        // Force userid to the current user — students can only see their own annotations.
        $userid = (int) $USER->id;

        // Validate grade is released.
        $adapter = adapter_factory::create($params['cmid']);
        if (!$adapter->is_grade_released($userid)) {
            return [];
        }

        // Validate the file belongs to this student's submission in this activity.
        $fs = get_file_storage();
        $file = $fs->get_file_by_id($params['fileid']);
        if (!$file || $file->is_directory()) {
            return [];
        }

        // The file must belong to this activity's context.
        if ((int) $file->get_contextid() !== (int) $context->id) {
            return [];
        }

        // Verify the file is part of this student's submission — for the
        // specific attempt the feedback view is currently displaying, not
        // unconditionally the latest attempt. view_feedback.php may resolve
        // and display an older attempt (e.g. the latest *graded* one, when a
        // student resubmitted after grading), and the student's own attempt
        // selector can navigate to any attempt explicitly. Checking only
        // against get_submission_files() (always latest-attempt) would
        // silently reject a valid, currently-displayed file whose attempt
        // isn't the newest, dropping the annotations with no error.
        $submissionfiles = ($params['attempt'] >= 0 && method_exists($adapter, 'get_submission_files_for_attempt'))
            ? $adapter->get_submission_files_for_attempt($userid, $params['attempt'])
            : $adapter->get_submission_files($userid);
        $fileids = array_column($submissionfiles, 'fileid');
        if (!in_array($params['fileid'], $fileids)) {
            return [];
        }

        return annotation_manager::get_annotations(
            $params['cmid'],
            $userid,
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
