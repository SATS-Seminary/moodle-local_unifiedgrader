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
 * Download feedback PDF with summary cover page.
 *
 * Generates a feedback summary page (grade, feedback, rubric/guide) and
 * prepends it to the annotated submission PDF. If no annotated PDF exists,
 * the summary is served on its own.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_unifiedgrader\adapter\adapter_factory;
use local_unifiedgrader\feedback_data_helper;
use local_unifiedgrader\pdf\feedback_summary_pdf;
use local_unifiedgrader\pdf\combined_feedback_pdf;

$cmid = required_param('cmid', PARAM_INT);
$fileid = optional_param('fileid', 0, PARAM_INT);
$attemptnum = optional_param('attempt', 0, PARAM_INT);

// Load course module and course.
[$course, $cm] = get_course_and_cm_from_cmid($cmid);

// Require login and check capabilities.
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('local/unifiedgrader:viewfeedback', $context);

// Only supported activity types.
$supported = ['assign', 'forum', 'quiz'];
if (!in_array($cm->modname, $supported)) {
    throw new moodle_exception('invalidactivitytype', 'local_unifiedgrader');
}

// Verify the plugin is enabled for this activity type.
if (!get_config('local_unifiedgrader', 'enable_' . $cm->modname)) {
    throw new moodle_exception('invalidactivitytype', 'local_unifiedgrader');
}

// Create the adapter and check grade release.
$adapter = adapter_factory::create($cmid);
$userid = $USER->id;

if (!$adapter->is_grade_released($userid)) {
    throw new moodle_exception('feedback_not_available', 'local_unifiedgrader');
}

// Load all the data we need.
$gradedata = $adapter->get_grade_data($userid);
$activityinfo = $adapter->get_activity_info();

// Parse grade, penalties, and rubric/guide data via shared helper.
$gradeinfo = feedback_data_helper::format_grade($gradedata, $activityinfo);
$penaltyinfo = feedback_data_helper::format_penalties($cmid, $userid);
$gradinginfo = feedback_data_helper::parse_grading_data($gradedata);

// Get feedback text with rewritten pluginfile URLs.
$feedback = '';
if ($cm->modname === 'assign') {
    $feedback = $gradedata['feedback'];
    if (!empty($feedback)) {
        $assign = new \assign($context, $cm, $course);
        $grade = $assign->get_user_grade($userid, false);
        if ($grade) {
            $feedback = file_rewrite_pluginfile_urls(
                $feedback,
                'pluginfile.php',
                $context->id,
                'assignfeedback_comments',
                'feedback',
                (int) $grade->id,
            );
        }
    }
} else {
    // Forum and quiz feedback is already rewritten by get_grade_data().
    $feedback = $gradedata['feedback'] ?? '';
}

// Plagiarism report links (available for all activity types).
$plagiarismlinks = $adapter->get_plagiarism_links($userid);

// For quizzes and forums, get the rendered content to include after the summary.
$additionalcontent = '';
if ($cm->modname === 'quiz') {
    if ($attemptnum > 0) {
        $submissiondata = $adapter->get_submission_data_for_attempt($userid, $attemptnum);
    } else {
        $submissiondata = $adapter->get_submission_data($userid);
    }
    $additionalcontent = $submissiondata['content'] ?? '';
} else if ($cm->modname === 'forum') {
    $submissiondata = $adapter->get_submission_data($userid);
    $additionalcontent = $submissiondata['content'] ?? '';
}

// Build the summary data array.
$studentname = fullname(\core_user::get_user($userid));
$dategraded = !empty($gradedata['timegraded'])
    ? userdate($gradedata['timegraded'], get_string('strftimedatefull'))
    : '';

$summarydata = [
    'activityname' => format_string($activityinfo['name']),
    'coursename' => format_string($course->fullname),
    'studentname' => $studentname,
    'gradevalue' => $gradeinfo['gradevalue'],
    'maxgrade' => $gradeinfo['maxgrade'],
    'percentage' => $gradeinfo['percentage'],
    'feedback' => $feedback,
    'gradingmethod' => $gradinginfo['gradingmethod'],
    'rubriccriteria' => $gradinginfo['rubriccriteria'],
    'guidecriteria' => $gradinginfo['guidecriteria'],
    'penalties' => $penaltyinfo['penalties'],
    'dategraded' => $dategraded,
    'plagiarismlinks' => $plagiarismlinks,
    'additionalcontent' => $additionalcontent,
    'additionalcontenttitle' => match ($cm->modname) {
        'quiz' => get_string('quiz_your_attempt', 'local_unifiedgrader'),
        'forum' => get_string('forum_your_posts', 'local_unifiedgrader'),
        default => '',
    },
];

// Generate the summary PDF.
$summarypdf = new feedback_summary_pdf();
$summarybytes = $summarypdf->generate($summarydata);

// Find the submission PDF to append after the summary.
// Priority: annotated PDF > original submission file (or its PDF conversion).
$submissionpdfbytes = null;

if ($fileid > 0) {
    $fs = get_file_storage();

    // 1. Check for a flattened annotated PDF.
    $annotatedfile = $fs->get_file(
        $context->id,
        'local_unifiedgrader',
        'annotatedpdf',
        $fileid,
        '/' . $userid . '/',
        'annotated.pdf',
    );

    if ($annotatedfile && !$annotatedfile->is_directory()) {
        $submissionpdfbytes = $annotatedfile->get_content();
    } else {
        // 2. Fall back to the original submission file.
        $originalfile = $fs->get_file_by_id($fileid);
        if ($originalfile && !$originalfile->is_directory()) {
            if ($originalfile->get_mimetype() === 'application/pdf') {
                $submissionpdfbytes = $originalfile->get_content();
            } else {
                // 3. Try the converted-to-PDF version (for Word docs, etc.).
                $converter = new \core_files\converter();
                $conversion = $converter->start_conversion($originalfile, 'pdf');
                // Poll briefly for conversion to complete.
                $maxpolls = 5;
                $pollcount = 0;
                $status = $conversion->get('status');
                while (
                    $status !== \core_files\conversion::STATUS_COMPLETE
                    && $status !== \core_files\conversion::STATUS_FAILED
                    && $pollcount < $maxpolls
                ) {
                    sleep(1);
                    $converter->poll_conversion($conversion);
                    $status = $conversion->get('status');
                    $pollcount++;
                }
                if ($status === \core_files\conversion::STATUS_COMPLETE) {
                    $convertedfile = $conversion->get_destfile();
                    if ($convertedfile) {
                        $submissionpdfbytes = $convertedfile->get_content();
                    }
                }
            }
        }
    }
}

// Combine summary + submission PDF if we have one.
$finalpdf = $summarybytes;
if ($submissionpdfbytes) {
    try {
        $combiner = new combined_feedback_pdf();
        $finalpdf = $combiner->combine($summarybytes, $submissionpdfbytes);
    } catch (\Exception $e) {
        debugging('Failed to combine PDFs: ' . $e->getMessage(), DEBUG_DEVELOPER);
        $finalpdf = $summarybytes;
    }
}

// Stream the PDF to the browser.
$filename = clean_filename($course->shortname . '-' . $activityinfo['name'] . '-feedback.pdf');

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($finalpdf));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

echo $finalpdf;
exit;
