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
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
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
    'local_unifiedgrader_get_library_comments' => [
        'classname' => 'local_unifiedgrader\external\get_library_comments',
        'description' => 'Get comment library entries for the current teacher.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_unifiedgrader_save_library_comment' => [
        'classname' => 'local_unifiedgrader\external\save_library_comment',
        'description' => 'Create or update a comment library entry.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_unifiedgrader_delete_library_comment' => [
        'classname' => 'local_unifiedgrader\external\delete_library_comment',
        'description' => 'Delete a comment library entry.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_unifiedgrader_get_library_tags' => [
        'classname' => 'local_unifiedgrader\external\get_library_tags',
        'description' => 'Get comment library tags for the current teacher.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_unifiedgrader_save_library_tag' => [
        'classname' => 'local_unifiedgrader\external\save_library_tag',
        'description' => 'Create or update a comment library tag.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_unifiedgrader_delete_library_tag' => [
        'classname' => 'local_unifiedgrader\external\delete_library_tag',
        'description' => 'Delete a comment library tag.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_unifiedgrader_get_shared_library' => [
        'classname' => 'local_unifiedgrader\external\get_shared_library',
        'description' => 'Get instance-wide shared comments from other teachers.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_unifiedgrader_import_shared_comment' => [
        'classname' => 'local_unifiedgrader\external\import_shared_comment',
        'description' => 'Import a shared comment into own library.',
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
    'local_unifiedgrader_prepare_feedback_draft' => [
        'classname' => 'local_unifiedgrader\external\prepare_feedback_draft',
        'description' => 'Prepare draft area with feedback files for a student.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_unifiedgrader_get_submission_comments' => [
        'classname' => 'local_unifiedgrader\external\get_submission_comments',
        'description' => 'Get submission comments for a student.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_unifiedgrader_add_submission_comment' => [
        'classname' => 'local_unifiedgrader\external\add_submission_comment',
        'description' => 'Add a submission comment.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_unifiedgrader_delete_submission_comment' => [
        'classname' => 'local_unifiedgrader\external\delete_submission_comment',
        'description' => 'Delete a submission comment.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_unifiedgrader_prepare_feedback_files_draft' => [
        'classname' => 'local_unifiedgrader\external\prepare_feedback_files_draft',
        'description' => 'Prepare draft area with feedback files for a student.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_unifiedgrader_save_feedback_files' => [
        'classname' => 'local_unifiedgrader\external\save_feedback_files',
        'description' => 'Save feedback files from draft area to permanent storage.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_unifiedgrader_set_grades_posted' => [
        'classname' => 'local_unifiedgrader\external\set_grades_posted',
        'description' => 'Post or unpost grades for an activity (hide/unhide grade item).',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_unifiedgrader_submission_action' => [
        'classname' => 'local_unifiedgrader\external\submission_action',
        'description' => 'Perform a submission status action (revert to draft, remove, lock, unlock).',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_unifiedgrader_delete_user_override' => [
        'classname' => 'local_unifiedgrader\external\delete_user_override',
        'description' => 'Delete a user-level override for an activity.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_unifiedgrader_delete_duedate_extension' => [
        'classname' => 'local_unifiedgrader\external\delete_duedate_extension',
        'description' => 'Delete a quiz duedate extension for a user.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_unifiedgrader_delete_forum_extension' => [
        'classname' => 'local_unifiedgrader\external\delete_forum_extension',
        'description' => 'Delete a forum due date extension for a user.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_unifiedgrader_get_penalties' => [
        'classname' => 'local_unifiedgrader\external\get_penalties',
        'description' => 'Get grade penalties for a student in an activity.',
        'type' => 'read',
        'ajax' => true,
    ],
    'local_unifiedgrader_save_penalty' => [
        'classname' => 'local_unifiedgrader\external\save_penalty',
        'description' => 'Save a grade penalty for a student.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_unifiedgrader_delete_penalty' => [
        'classname' => 'local_unifiedgrader\external\delete_penalty',
        'description' => 'Delete a grade penalty.',
        'type' => 'write',
        'ajax' => true,
    ],
    'local_unifiedgrader_clear_all_overrides' => [
        'classname' => 'local_unifiedgrader\external\clear_all_overrides',
        'description' => 'Clear all user-level overrides and extensions for an activity.',
        'type' => 'write',
        'ajax' => true,
    ],
];
