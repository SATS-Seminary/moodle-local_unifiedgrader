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
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// General.
$string['pluginname'] = 'Unified Grader';
$string['grading_interface'] = 'Unified Grader';
$string['nopermission'] = 'You do not have permission to use the Unified Grader.';
$string['invalidactivitytype'] = 'This activity type is not supported by the Unified Grader.';
$string['invalidmodule'] = 'Invalid activity module.';
$string['viewfeedback'] = 'View feedback';

// Attempts.
$string['attempt'] = 'Attempt';

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
$string['setting_enable_quiz_post_grades'] = 'Enable post grades for quizzes';
$string['setting_enable_quiz_post_grades_desc'] = 'Quiz grade visibility is normally managed by the quiz\'s review options. When enabled, the Unified Grader\'s "Post grades" toggle will update the quiz review options programmatically to show or hide marks. When disabled (default), the post grades toggle is hidden for quizzes.';
$string['setting_allow_manual_override'] = 'Allow manual grade override';
$string['setting_allow_manual_override_desc'] = 'When enabled, teachers can manually type a grade even when a rubric or marking guide is configured. When disabled, the grade is calculated exclusively from the rubric or marking guide criteria.';

// Grading interface.
$string['grade'] = 'Grade';
$string['savegrade'] = 'Save grade';
$string['savefeedback'] = 'Save feedback';
$string['savinggrade'] = 'Saving grade...';
$string['gradesaved'] = 'Grade saved';
$string['error_saving'] = 'Error saving grade.';
$string['error_network'] = 'Unable to connect to the server. Please check your connection and try again.';
$string['error_offline_comments'] = 'Cannot add comments while offline.';
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
$string['filter_needsgrading'] = 'Ungraded';
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
$string['plagiarism_pending'] = 'Plagiarism scan in progress';
$string['plagiarism_error'] = 'Plagiarism scan failed';

// Student feedback view.
$string['assessment_criteria'] = 'Assessment criteria';
$string['teacher_remark'] = 'Teacher feedback';
$string['view_feedback'] = 'View feedback';
$string['view_annotated_feedback'] = 'View Annotated Feedback';
$string['feedback_not_available'] = 'Your feedback is not yet available. Please check back after your submission has been graded and released.';
$string['no_annotated_files'] = 'There are no annotated PDF files for your submission.';
$string['feedback_banner_default'] = 'Your teacher has provided feedback on your submission.';

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
$string['annotate_clear_confirm'] = 'Are you sure you want to clear all annotations on this page? This cannot be undone.';

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
$string['forum_posts_pill'] = 'Posts';
$string['submission_content_pill'] = 'Submission';
$string['forum_tab_posts'] = 'Posts';
$string['forum_tab_files'] = 'Annotated Files';
$string['view_quiz_feedback'] = 'View Quiz Feedback';
$string['quiz_feedback_banner'] = 'Your teacher has provided feedback on your quiz.';
$string['quiz_your_attempt'] = 'Your Attempt';
$string['quiz_no_attempt'] = 'You have not completed any attempts for this quiz.';
$string['quiz_select_attempt'] = 'Select attempt';
$string['select_attempt'] = 'Select attempt';
$string['attempt_label'] = 'Attempt {$a}';

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
$string['quiz_post_grades_disabled'] = 'Post grades is not available for quizzes. Grade visibility is controlled by the quiz review options.';
$string['quiz_post_grades_no_schedule'] = 'Scheduling is not available for quizzes. Use Post or Unpost instead.';

// Submission status actions.
$string['action_revert_to_draft'] = 'Revert to draft';
$string['action_remove_submission'] = 'Remove submission';
$string['action_lock'] = 'Prevent submission changes';
$string['action_unlock'] = 'Allow submission changes';
$string['action_edit_submission'] = 'Edit submission';
$string['action_grant_extension'] = 'Grant extension';
$string['action_edit_extension'] = 'Edit extension';
$string['action_submit_for_grading'] = 'Submit for grading';
$string['confirm_revert_to_draft'] = 'Are you sure you want to revert this submission to draft status?';
$string['confirm_remove_submission'] = 'Are you sure you want to remove this submission? This cannot be undone.';
$string['confirm_lock_submission'] = 'Prevent this student from making submission changes?';
$string['confirm_unlock_submission'] = 'Allow this student to make submission changes?';
$string['confirm_submit_for_grading'] = 'Submit this draft on behalf of the student?';
$string['invalidaction'] = 'Invalid submission action.';

// Override actions.
$string['override'] = 'Override';
$string['action_add_override'] = 'Add override';
$string['action_edit_override'] = 'Edit override';
$string['action_delete_override'] = 'Delete override';
$string['confirm_delete_override'] = 'Are you sure you want to delete this user override?';
$string['override_saved'] = 'Override saved successfully.';

// Quiz duedate extensions.
$string['action_delete_extension'] = 'Delete extension';
$string['confirm_delete_extension'] = 'Are you sure you want to delete this due date extension?';
$string['quiz_extension_original_duedate'] = 'Original due date';
$string['quiz_extension_current_extension'] = 'Current extension';
$string['quiz_extension_new_duedate'] = 'Extension due date';
$string['quiz_extension_must_be_after_duedate'] = 'The extension date must be after the current due date.';
$string['quiz_extension_plugin_missing'] = 'The quizaccess_duedate plugin is required for quiz extensions but is not installed.';

// Forum extensions.
$string['forum_extension_original_duedate'] = 'Forum due date';
$string['forum_extension_current_extension'] = 'Current extension';
$string['forum_extension_new_duedate'] = 'Extension due date';
$string['forum_extension_must_be_after_duedate'] = 'The extension date must be after the forum due date.';

// Student profile popout.
$string['profile_view_full'] = 'View full profile';
$string['profile_login_as'] = 'Login as';
$string['profile_no_email'] = 'No email available';

// Settings: course code regex.
$string['setting_coursecode_regex'] = 'Course code regex';
$string['setting_coursecode_regex_desc'] = 'The Comment Library organises saved comments by course code, so teachers can reuse feedback across different offerings of the same course (e.g. semester to semester). This setting controls how course codes are extracted from Moodle course short names. Enter a PHP regex pattern that matches the code portion of your short names (e.g. <code>/[A-Z]{3}\\d{4}/</code> would extract <strong>THE2201</strong> from a short name like <em>THE2201-2026-S1</em>). Leave empty to use the full short name as the course code.';

// Settings: academic impropriety report form.
$string['setting_enable_report_form'] = 'Enable academic impropriety report form';
$string['setting_enable_report_form_desc'] = 'When enabled, a "Report academic impropriety" button appears in plagiarism sections, linking to an external reporting form.';
$string['setting_report_form_url'] = 'Report form URL template';
$string['setting_report_form_url_desc'] = 'URL for the academic impropriety report form. Supported placeholders: <code>{coursecode}</code>, <code>{coursename}</code>, <code>{studentname}</code>, <code>{activityname}</code>, <code>{activitytype}</code>, <code>{studentid}</code>, <code>{gradername}</code>, <code>{graderurl}</code>. These are replaced at runtime with URL-encoded values. For Microsoft Forms, use the "Get Pre-filled URL" feature to find parameter names.';
$string['report_impropriety'] = 'Report academic impropriety';

// Comment library v2.
$string['clib_title'] = 'Comment Library';
$string['clib_all'] = 'All';
$string['clib_quick_add'] = 'Quick add comment...';
$string['clib_manage'] = 'Manage Library';
$string['clib_no_comments'] = 'No comments yet.';
$string['clib_insert'] = 'Insert';
$string['clib_copied'] = 'Comment copied to clipboard';
$string['clib_my_library'] = 'My Library';
$string['clib_shared_library'] = 'Shared Library';
$string['clib_new_comment'] = 'New comment';
$string['clib_edit_comment'] = 'Edit comment';
$string['clib_delete_comment'] = 'Delete comment';
$string['clib_confirm_delete'] = 'Are you sure you want to delete this comment?';
$string['clib_share'] = 'Share';
$string['clib_unshare'] = 'Unshare';
$string['clib_import'] = 'Import';
$string['clib_imported'] = 'Comment imported to your library';
$string['clib_copy_to_course'] = 'Copy to course';
$string['clib_all_courses'] = 'All courses';
$string['clib_tags'] = 'Tags';
$string['clib_manage_tags'] = 'Manage tags';
$string['clib_new_tag'] = 'New tag';
$string['clib_edit_tag'] = 'Edit tag';
$string['clib_delete_tag'] = 'Delete tag';
$string['clib_confirm_delete_tag'] = 'Are you sure you want to delete this tag? It will be removed from all comments.';
$string['clib_system_tag'] = 'System default';
$string['clib_shared_by'] = 'Shared by {$a}';
$string['clib_no_shared'] = 'No shared comments available.';
$string['clib_picker_freetext'] = 'Or write your own...';
$string['clib_picker_loading'] = 'Loading comments...';
$string['clib_offline_mode'] = 'Showing cached comments — editing is unavailable offline.';
$string['unifiedgrader:sharecomments'] = 'Share comments in the library with other teachers';

// Privacy: comment library v2.
$string['privacy:metadata:clib'] = 'Comment library entries in the Unified Grader.';
$string['privacy:metadata:clib:userid'] = 'The teacher who owns the comment.';
$string['privacy:metadata:clib:coursecode'] = 'The course code the comment is associated with.';
$string['privacy:metadata:clib:content'] = 'The content of the comment.';
$string['privacy:metadata:cltag'] = 'Comment library tags in the Unified Grader.';
$string['privacy:metadata:cltag:userid'] = 'The teacher who owns the tag.';
$string['privacy:metadata:cltag:name'] = 'The tag name.';

// Penalties.
$string['penalties'] = 'Penalties';
$string['penalty_late'] = 'Late submission';
$string['penalty_late_days'] = '{$a} day(s) late';
$string['penalty_late_auto'] = 'Automatically calculated based on penalty rules';
$string['penalty_wordcount'] = 'Word count';
$string['penalty_other'] = 'Other';
$string['penalty_custom'] = 'Custom';
$string['penalty_label_placeholder'] = 'Label (max 15 chars)';
$string['penalty_active'] = 'Active penalties';
$string['penalty_badge'] = '-{$a->percentage}% {$a->label}';
$string['finalgradeafterpenalties'] = 'Final grade after penalties:';
$string['cannotdeleteautopenalty'] = 'Late penalties are automatically calculated and cannot be deleted.';

// Feedback summary PDF.
$string['download_feedback_pdf'] = 'Download feedback PDF';
$string['feedback_summary_overall_feedback'] = 'Overall Feedback';
$string['feedback_summary_graded_on'] = 'Graded on {$a}';
$string['feedback_summary_generated_by'] = 'Generated by Unified Grader';
$string['feedback_summary_media_note'] = 'Media content is available in the online feedback view.';
$string['feedback_summary_no_grade'] = 'N/A';
$string['feedback_summary_remark'] = 'Teacher Comment';
$string['feedback_summary_total'] = 'Total';
$string['levels'] = 'Levels';
$string['error_gs_not_configured'] = 'GhostScript is not configured on this Moodle server. The administrator must set the GhostScript path in Site administration > Plugins > Activity modules > Assignment > Feedback > Annotate PDF.';
$string['error_pdf_combine_failed'] = 'Failed to combine PDF files: {$a}';

// Privacy: penalties.
$string['privacy:metadata:penalty'] = 'Grade penalties applied by teachers in the Unified Grader.';
$string['privacy:metadata:penalty:userid'] = 'The student the penalty was applied to.';
$string['privacy:metadata:penalty:authorid'] = 'The teacher who applied the penalty.';
$string['privacy:metadata:penalty:category'] = 'The penalty category (word count or other).';
$string['privacy:metadata:penalty:label'] = 'The custom label for the penalty.';
$string['privacy:metadata:penalty:percentage'] = 'The penalty percentage.';
$string['privacy:metadata:fext'] = 'Forum due date extensions granted by teachers in the Unified Grader.';
$string['privacy:metadata:fext:userid'] = 'The student the extension was granted to.';
$string['privacy:metadata:fext:authorid'] = 'The teacher who granted the extension.';
$string['privacy:metadata:fext:extensionduedate'] = 'The extended due date.';
$string['privacy:metadata:qfb'] = 'Per-attempt quiz feedback stored by the Unified Grader.';
$string['privacy:metadata:qfb:userid'] = 'The student the feedback is for.';
$string['privacy:metadata:qfb:grader'] = 'The teacher who provided the feedback.';
$string['privacy:metadata:qfb:feedback'] = 'The feedback text.';
$string['privacy:metadata:qfb:attemptnumber'] = 'The quiz attempt number.';
$string['privacy_forum_extensions'] = 'Forum extensions';
$string['privacy_quiz_feedback'] = 'Quiz feedback';

// Offline cache and save status.
$string['allchangessaved'] = 'All changes saved';
$string['editing'] = 'Editing...';
$string['offlinesavedlocally'] = 'Offline — saved locally';
$string['connectionlost'] = 'Connection lost — your work is saved locally and will sync when reconnected.';
$string['recoveredunsavedchanges'] = 'Recovered unsaved changes from your last session.';
$string['restore'] = 'Restore';
$string['discard'] = 'Discard';
$string['mark_as_graded'] = 'Mark as graded';
