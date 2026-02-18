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
$string['unifiedgrader:viewfeedback'] = 'View annotated feedback from the Unified Grader';

// Settings.
$string['setting_enable_assign'] = 'Enable for Assignments';
$string['setting_enable_assign_desc'] = 'Allow the Unified Grader to be used for assignment activities.';
$string['setting_enable_forum'] = 'Enable for Forums';
$string['setting_enable_forum_desc'] = 'Allow the Unified Grader to be used for forum activities.';
$string['setting_enable_quiz'] = 'Enable for Quizzes';
$string['setting_enable_quiz_desc'] = 'Allow the Unified Grader to be used for quiz activities.';
$string['setting_allow_manual_override'] = 'Allow manual grade override';
$string['setting_allow_manual_override_desc'] = 'When enabled, teachers can manually type a grade even when a rubric or marking guide is configured. When disabled, the grade is calculated exclusively from the rubric or marking guide criteria.';

// Grading interface.
$string['grade'] = 'Grade';
$string['savegrade'] = 'Save grade';
$string['savefeedback'] = 'Save feedback';
$string['savinggrade'] = 'Saving grade...';
$string['gradesaved'] = 'Grade saved';
$string['error_saving'] = 'Error saving grade.';
$string['feedback'] = 'Feedback';
$string['overall_feedback'] = 'Overall Feedback';
$string['feedback_saved'] = 'Feedback (saved)';
$string['edit_feedback'] = 'Edit';
$string['delete_feedback'] = 'Delete';
$string['confirm_delete_feedback'] = 'Are you sure you want to delete this feedback? The grade will be preserved.';
$string['maxgrade'] = '/ {$a}';
$string['expand'] = 'Expand';

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
$string['filter_late'] = 'Late';
$string['filter_allgroups'] = 'All groups';
$string['studentcount'] = '{$a->current} of {$a->total}';

// Statuses.
$string['status_draft'] = 'Draft';
$string['status_submitted'] = 'Submitted';
$string['status_graded'] = 'Graded';
$string['status_nosubmission'] = 'No submission';
$string['status_needsgrading'] = 'Needs grading';
$string['status_new'] = 'Not submitted';
$string['status_late'] = 'Late: {$a}';

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
$string['manualquestions'] = 'Manual questions';
$string['response'] = 'Response';
$string['teachercomment'] = 'Teacher comment';

// Submission comments.
$string['submissioncomments'] = 'Submission comments';
$string['nocommentsyet'] = 'No comments yet';
$string['addcomment'] = 'Add a comment...';
$string['postcomment'] = 'Post';
$string['deletesubmissioncomment'] = 'Delete comment';

// Feedback files.
$string['feedbackfiles'] = 'Feedback files';

// Plagiarism.
$string['plagiarism'] = 'Plagiarism';
$string['plagiarism_noresults'] = 'No plagiarism results available.';

// Student feedback view.
$string['assessment_criteria'] = 'Assessment criteria';
$string['view_feedback'] = 'View feedback';
$string['view_annotated_feedback'] = 'View Annotated Feedback';
$string['feedback_not_available'] = 'Your feedback is not yet available. Please check back after your submission has been graded and released.';
$string['no_annotated_files'] = 'There are no annotated PDF files for your submission.';

// Document conversion.
$string['conversion_failed'] = 'This file could not be converted to PDF for preview.';
$string['converting_file'] = 'Converting document to PDF...';
$string['conversion_timeout'] = 'Document conversion is taking too long. Please try again later.';
$string['download_annotated_pdf'] = 'Download annotated PDF';
$string['download_original_submission'] = 'Download original submission: {$a}';

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
$string['pdf_search'] = 'Search in document';

// Annotation tools.
$string['annotate_tools'] = 'Annotation tools';
$string['annotate_select'] = 'Select';
$string['annotate_textselect'] = 'Select text';
$string['annotate_comment'] = 'Comment';
$string['annotate_highlight'] = 'Highlight';
$string['annotate_pen'] = 'Pen';
$string['annotate_pen_fine'] = 'Fine';
$string['annotate_pen_medium'] = 'Medium';
$string['annotate_pen_thick'] = 'Thick';
$string['annotate_stamps'] = 'Stamps';
$string['annotate_stamp_check'] = 'Checkmark stamp';
$string['annotate_stamp_cross'] = 'Cross stamp';
$string['annotate_stamp_question'] = 'Question stamp';
$string['annotate_red'] = 'Red';
$string['annotate_yellow'] = 'Yellow';
$string['annotate_green'] = 'Green';
$string['annotate_blue'] = 'Blue';
$string['annotate_black'] = 'Black';
$string['annotate_shape'] = 'Shapes';
$string['annotate_shape_rect'] = 'Rectangle';
$string['annotate_shape_circle'] = 'Circle';
$string['annotate_shape_arrow'] = 'Arrow';
$string['annotate_shape_line'] = 'Line';
$string['annotate_undo'] = 'Undo';
$string['annotate_redo'] = 'Redo';
$string['annotate_delete'] = 'Delete selected';
$string['annotate_clearall'] = 'Clear all';

// Document info.
$string['docinfo'] = 'Document info';
$string['docinfo_filename'] = 'Filename';
$string['docinfo_filesize'] = 'File size';
$string['docinfo_pages'] = 'Pages';
$string['docinfo_wordcount'] = 'Word count';
$string['docinfo_author'] = 'Author';
$string['docinfo_creator'] = 'Creator';
$string['docinfo_created'] = 'Created';
$string['docinfo_modified'] = 'Modified';
$string['docinfo_calculating'] = 'Calculating...';

// Forum feedback view.
$string['view_forum_feedback'] = 'View Forum Feedback';
$string['forum_your_posts'] = 'Your forum posts';
$string['forum_no_posts'] = 'You have not made any posts in this forum.';
$string['forum_feedback_banner'] = 'Your teacher has graded your forum participation.';
$string['forum_wordcount'] = '{$a} words';

// Post grades.
$string['grades_posted'] = 'Grades posted';
$string['grades_hidden'] = 'Grades hidden';
$string['post_grades'] = 'Post grades';
$string['unpost_grades'] = 'Unpost grades';
$string['confirm_post_grades'] = 'Post all grades for this activity? Students will be able to see their grades and feedback.';
$string['confirm_unpost_grades'] = 'Unpost all grades for this activity? Students will no longer be able to see their grades and feedback.';
$string['schedule_post'] = 'Post on a date';
$string['schedule_post_btn'] = 'Schedule';
$string['grades_scheduled'] = 'Posting {$a}';
$string['schedule_must_be_future'] = 'The scheduled date must be in the future.';

// Submission status actions.
$string['action_revert_to_draft'] = 'Revert to draft';
$string['action_remove_submission'] = 'Remove submission';
$string['action_lock'] = 'Prevent submission changes';
$string['action_unlock'] = 'Allow submission changes';
$string['action_edit_submission'] = 'Edit submission';
$string['action_grant_extension'] = 'Grant extension';
$string['action_submit_for_grading'] = 'Submit for grading';
$string['confirm_revert_to_draft'] = 'Are you sure you want to revert this submission to draft status?';
$string['confirm_remove_submission'] = 'Are you sure you want to remove this submission? This cannot be undone.';
$string['confirm_lock_submission'] = 'Prevent this student from making submission changes?';
$string['confirm_unlock_submission'] = 'Allow this student to make submission changes?';
$string['confirm_submit_for_grading'] = 'Submit this draft on behalf of the student?';
$string['invalidaction'] = 'Invalid submission action.';
