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
 * Web service declarations for local_unifiedgrader.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_unifiedgrader_get_activity_info' => [
        'classname' => 'local_unifiedgrader\external\get_activity_info',
        'description' => 'Get activity metadata for the grading interface.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_unifiedgrader_get_participants' => [
        'classname' => 'local_unifiedgrader\external\get_participants',
        'description' => 'Get filtered and sorted participant list with submission status.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_unifiedgrader_get_submission_data' => [
        'classname' => 'local_unifiedgrader\external\get_submission_data',
        'description' => 'Get submission content for a specific student.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_unifiedgrader_get_grade_data' => [
        'classname' => 'local_unifiedgrader\external\get_grade_data',
        'description' => 'Get current grade and feedback for a student.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_unifiedgrader_save_grade' => [
        'classname' => 'local_unifiedgrader\external\save_grade',
        'description' => 'Save a grade and feedback for a student.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_unifiedgrader_get_notes' => [
        'classname' => 'local_unifiedgrader\external\get_notes',
        'description' => 'Get private teacher notes for a student.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_unifiedgrader_save_note' => [
        'classname' => 'local_unifiedgrader\external\save_note',
        'description' => 'Save a private teacher note.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_unifiedgrader_delete_note' => [
        'classname' => 'local_unifiedgrader\external\delete_note',
        'description' => 'Delete a private teacher note.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_unifiedgrader_get_comment_library' => [
        'classname' => 'local_unifiedgrader\external\get_comment_library',
        'description' => 'Get reusable comments from the library.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_unifiedgrader_save_comment_to_library' => [
        'classname' => 'local_unifiedgrader\external\save_comment_to_library',
        'description' => 'Add or update a comment in the library.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_unifiedgrader_get_annotations' => [
        'classname' => 'local_unifiedgrader\external\get_annotations',
        'description' => 'Get annotations for a student submission file.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_unifiedgrader_save_annotations' => [
        'classname' => 'local_unifiedgrader\external\save_annotations',
        'description' => 'Save annotations for a student submission file (batch).',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_unifiedgrader_delete_annotations' => [
        'classname' => 'local_unifiedgrader\external\delete_annotations',
        'description' => 'Delete all annotations for a student submission file.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_unifiedgrader_get_student_annotations' => [
        'classname' => 'local_unifiedgrader\external\get_student_annotations',
        'description' => 'Get annotations for the current student\'s own submission file (read-only).',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_unifiedgrader_save_annotated_pdf' => [
        'classname' => 'local_unifiedgrader\external\save_annotated_pdf',
        'description' => 'Upload a flattened annotated PDF to file storage.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_unifiedgrader_delete_annotated_pdf' => [
        'classname' => 'local_unifiedgrader\external\delete_annotated_pdf',
        'description' => 'Delete a flattened annotated PDF from file storage.',
        'type' => 'write',
        'ajax' => true,
    ],
];
