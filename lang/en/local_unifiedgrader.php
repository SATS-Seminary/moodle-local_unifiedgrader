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
 * Language strings for local_unifiedgrader.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// General.
$string['pluginname'] = 'Unified Grader';
$string['grading_interface'] = 'Unified Grader';
$string['nopermission'] = 'You do not have permission to use the Unified Grader.';
$string['invalidactivitytype'] = 'This activity type is not supported by the Unified Grader.';

// Capabilities.
$string['unifiedgrader:grade'] = 'Use the Unified Grader to grade';
$string['unifiedgrader:viewall'] = 'View all students in the Unified Grader';
$string['unifiedgrader:viewnotes'] = 'View private teacher notes';
$string['unifiedgrader:managenotes'] = 'Create and edit private teacher notes';

// Settings.
$string['setting_enable_assign'] = 'Enable for Assignments';
$string['setting_enable_assign_desc'] = 'Allow the Unified Grader to be used for assignment activities.';
$string['setting_enable_forum'] = 'Enable for Forums';
$string['setting_enable_forum_desc'] = 'Allow the Unified Grader to be used for forum activities.';
$string['setting_enable_quiz'] = 'Enable for Quizzes';
$string['setting_enable_quiz_desc'] = 'Allow the Unified Grader to be used for quiz activities.';

// Grading interface.
$string['grade'] = 'Grade';
$string['savegrade'] = 'Save grade';
$string['savinggrade'] = 'Saving grade...';
$string['gradesaved'] = 'Grade saved';
$string['error_saving'] = 'Error saving grade.';
$string['feedback'] = 'Feedback';
$string['maxgrade'] = '/ {$a}';

// Submissions.
$string['submission'] = 'Submission';
$string['nosubmission'] = 'No submission';
$string['previewpanel'] = 'Submission preview';
$string['markingpanel'] = 'Grading panel';
$string['onlinetext'] = 'Online text';
$string['submittedfiles'] = 'Submitted files';
$string['viewfile'] = 'View file';

// Participants.
$string['participants'] = 'Participants';
$string['search'] = 'Search participants...';
$string['sortby'] = 'Sort by';
$string['sortby_fullname'] = 'Full name';
$string['sortby_submittedat'] = 'Submission date';
$string['sortby_status'] = 'Status';
$string['filter_all'] = 'All participants';
$string['filter_submitted'] = 'Submitted';
$string['filter_needsgrading'] = 'Needs grading';
$string['filter_notsubmitted'] = 'Not submitted';
$string['filter_graded'] = 'Graded';
$string['studentcount'] = '{$a->current} of {$a->total}';

// Statuses.
$string['status_draft'] = 'Draft';
$string['status_submitted'] = 'Submitted';
$string['status_graded'] = 'Graded';
$string['status_nosubmission'] = 'No submission';
$string['status_needsgrading'] = 'Needs grading';
$string['status_new'] = 'Not submitted';

// Teacher notes.
$string['notes'] = 'Teacher notes';
$string['notes_desc'] = 'Private notes visible only to teachers and moderators.';
$string['savenote'] = 'Save note';
$string['deletenote'] = 'Delete';
$string['addnote'] = 'Add note';
$string['nonotes'] = 'No notes yet.';
$string['confirmdelete_note'] = 'Are you sure you want to delete this note?';

// Comment library.
$string['commentlibrary'] = 'Comment library';
$string['savecomment'] = 'Save to library';
$string['insertcomment'] = 'Insert';
$string['deletecomment'] = 'Remove';
$string['newcomment'] = 'New comment...';
$string['nocomments'] = 'No saved comments.';

// UI.
$string['loading'] = 'Loading...';
$string['saving'] = 'Saving...';
$string['saved'] = 'Saved';
$string['previousstudent'] = 'Previous student';
$string['nextstudent'] = 'Next student';
$string['expandfilters'] = 'Show filters';
$string['collapsefilters'] = 'Hide filters';
$string['backtocourse'] = 'Back to course';
$string['rubric'] = 'Rubric';
$string['markingguide'] = 'Marking guide';
$string['criterion'] = 'Criterion';
$string['score'] = 'Score';
$string['remark'] = 'Remark';
$string['total'] = 'Total: {$a}';
$string['viewallsubmissions'] = 'View all submissions';
$string['layout_both'] = 'Split view';
$string['layout_preview'] = 'Preview only';
$string['layout_grade'] = 'Grading only';

// Privacy.
$string['privacy:metadata:notes'] = 'Private teacher notes stored per student per activity in the Unified Grader.';
$string['privacy:metadata:notes:cmid'] = 'The course module ID the note relates to.';
$string['privacy:metadata:notes:userid'] = 'The student the note is about.';
$string['privacy:metadata:notes:authorid'] = 'The teacher who wrote the note.';
$string['privacy:metadata:notes:content'] = 'The content of the note.';
$string['privacy:metadata:comments'] = 'Reusable comment library entries in the Unified Grader.';
$string['privacy:metadata:comments:userid'] = 'The teacher who owns the comment.';
$string['privacy:metadata:comments:content'] = 'The content of the comment.';
$string['privacy:metadata:preferences'] = 'User preferences for the Unified Grader interface.';
$string['privacy:metadata:preferences:userid'] = 'The user the preferences belong to.';
$string['privacy:metadata:preferences:data'] = 'The JSON-encoded preferences data.';
$string['privacy:metadata:annotations'] = 'Document annotations stored in the Unified Grader.';
$string['privacy:metadata:annotations:cmid'] = 'The course module ID the annotation relates to.';
$string['privacy:metadata:annotations:userid'] = 'The student whose submission is annotated.';
$string['privacy:metadata:annotations:authorid'] = 'The teacher who created the annotation.';
$string['privacy:metadata:annotations:data'] = 'The annotation data (Fabric.js JSON).';
$string['annotations'] = 'Annotations';

// PDF viewer.
$string['pdf_prevpage'] = 'Previous page';
$string['pdf_nextpage'] = 'Next page';
$string['pdf_zoomin'] = 'Zoom in';
$string['pdf_zoomout'] = 'Zoom out';
$string['pdf_zoomfit'] = 'Fit to width';

// Annotation tools.
$string['annotate_tools'] = 'Annotation tools';
$string['annotate_select'] = 'Select';
$string['annotate_comment'] = 'Comment';
$string['annotate_highlight'] = 'Highlight';
$string['annotate_pen'] = 'Pen';
$string['annotate_stamps'] = 'Stamps';
$string['annotate_stamp_check'] = 'Checkmark stamp';
$string['annotate_stamp_cross'] = 'Cross stamp';
$string['annotate_stamp_question'] = 'Question stamp';
$string['annotate_red'] = 'Red';
$string['annotate_yellow'] = 'Yellow';
$string['annotate_green'] = 'Green';
$string['annotate_blue'] = 'Blue';
$string['annotate_black'] = 'Black';
$string['annotate_undo'] = 'Undo';
$string['annotate_redo'] = 'Redo';
$string['annotate_delete'] = 'Delete selected';
$string['annotate_clearall'] = 'Clear all';
