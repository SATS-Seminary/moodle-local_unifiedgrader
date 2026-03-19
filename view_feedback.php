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
 * Student feedback viewer — read-only view of annotated PDF submissions.
 *
 * Students can see their graded PDF with teacher annotations after the
 * grade has been released. Only supported for assignment activities.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_unifiedgrader\adapter\adapter_factory;
use local_unifiedgrader\feedback_data_helper;

$cmid = required_param('cmid', PARAM_INT);
$fileid = optional_param('fileid', 0, PARAM_INT);

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
    // Grade not yet released — show a message.
    $PAGE->set_url(new moodle_url('/local/unifiedgrader/view_feedback.php', ['cmid' => $cmid]));
    $PAGE->set_context($context);
    $PAGE->set_title(get_string('view_feedback', 'local_unifiedgrader'));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        get_string('feedback_not_available', 'local_unifiedgrader'),
        'info',
    );
    echo $OUTPUT->footer();
    exit;
}

// ──────────────────────────────────────────────
//  Forum feedback view.
// ──────────────────────────────────────────────
if ($cm->modname === 'forum') {
    $gradedata = $adapter->get_grade_data($userid);
    $submissiondata = $adapter->get_submission_data($userid);
    $activityinfo = $adapter->get_activity_info();

    // Get forum attachment files and filter to annotatable types (PDF + convertible).
    $submissionfiles = $adapter->get_submission_files($userid);
    $pdffiles = array_values(array_filter($submissionfiles, function ($f) {
        return $f['mimetype'] === 'application/pdf' || !empty($f['convertible']);
    }));
    $haspdffiles = !empty($pdffiles);

    $selectedfile = null;
    $selectedpdfurl = '';
    $hasannotatedpdf = false;
    $annotatedpdfmap = [];

    if ($haspdffiles) {
        $selectedfile = $pdffiles[0];
        if ($fileid) {
            foreach ($pdffiles as $pdf) {
                if ($pdf['fileid'] === $fileid) {
                    $selectedfile = $pdf;
                    break;
                }
            }
        }

        // Check for flattened annotated PDFs in file storage.
        $fs = get_file_storage();
        foreach ($pdffiles as $pdf) {
            $apdf = $fs->get_file(
                $context->id,
                'local_unifiedgrader',
                'annotatedpdf',
                $pdf['fileid'],
                '/' . $userid . '/',
                'annotated.pdf',
            );
            if ($apdf && !$apdf->is_directory()) {
                $annotatedpdfmap[$pdf['fileid']] = moodle_url::make_pluginfile_url(
                    $context->id,
                    'local_unifiedgrader',
                    'annotatedpdf',
                    $pdf['fileid'],
                    '/' . $userid . '/',
                    'annotated.pdf',
                )->out(false);
            }
        }

        $selectedpdfurl = $selectedfile['previewurl'];
        $hasannotatedpdf = isset($annotatedpdfmap[$selectedfile['fileid']]);
    }

    // Set up the page.
    $PAGE->set_url(new moodle_url('/local/unifiedgrader/view_feedback.php', ['cmid' => $cmid]));
    $PAGE->set_context($context);
    $PAGE->set_title(
        get_string('view_feedback', 'local_unifiedgrader') . ': ' .
        format_string($cm->name),
    );
    $PAGE->set_heading($course->fullname);
    $PAGE->set_pagelayout('standard');
    $PAGE->activityheader->disable();
    $PAGE->add_body_class('local-unifiedgrader-feedback-page');

    // Parse grade, penalties, and rubric/guide data via shared helper.
    $penaltyinfo = feedback_data_helper::format_penalties($cmid, $userid);
    $gradeinfo = feedback_data_helper::format_grade($gradedata, $activityinfo);
    $gradinginfo = feedback_data_helper::parse_grading_data($gradedata);
    $gradedisplay = $gradeinfo['gradedisplay'];

    // Feedback text (from gradebook for forums). Already formatted by get_grade_data()
    // with pluginfile.php URLs rewritten and format_text() applied.
    $feedback = $gradedata['feedback'] ?? '';

    $showrightcolumn = !empty($feedback) || $gradinginfo['hasadvancedgrading'];

    // Build feedback PDF download URL.
    $feedbackdownloadurl = (new moodle_url('/local/unifiedgrader/download_feedback.php', [
        'cmid' => $cmid,
        'fileid' => $selectedfile ? $selectedfile['fileid'] : 0,
    ]))->out(false);

    // Prepare template context.
    $templatedata = [
        'cmid' => $cmid,
        'activityname' => $activityinfo['name'],
        'activityurl' => (new moodle_url('/mod/forum/view.php', ['id' => $cm->id]))->out(false),
        'gradedisplay' => $gradedisplay,
        'feedback' => $feedback,
        'hasfeedback' => !empty($feedback),
        'postcontent' => $submissiondata['content'],
        'hasposts' => !empty($submissiondata['content']),
        'haspdffiles' => $haspdffiles,
        'selectedfileid' => $selectedfile ? $selectedfile['fileid'] : 0,
        'selectedfileurl' => $selectedpdfurl,
        'selectedfilename' => $selectedfile ? $selectedfile['filename'] : '',
        'pdffiles' => $pdffiles,
        'hasmultiplefiles' => count($pdffiles) > 1,
        'pdffilesjson' => json_encode($pdffiles),
        'hasannotatedpdf' => $hasannotatedpdf,
        'annotatedpdfurl' => $hasannotatedpdf ? $annotatedpdfmap[$selectedfile['fileid']] : '',
        'downloadfilename' => clean_filename($course->shortname . '-' . $activityinfo['name'] . '-feedback.pdf'),
        'annotatedpdfmapjson' => json_encode($annotatedpdfmap),
        'feedbackdownloadurl' => $feedbackdownloadurl,
        'userid' => $userid,
        'hasrubric' => $gradinginfo['hasrubric'],
        'rubriccriteria' => $gradinginfo['rubriccriteria'],
        'rubrictotal' => $gradinginfo['rubrictotal'],
        'hasguide' => $gradinginfo['hasguide'],
        'guidecriteria' => $gradinginfo['guidecriteria'],
        'guidetotal' => $gradinginfo['guidetotal'],
        'guidemaxtotal' => $gradinginfo['guidemaxtotal'],
        'hasadvancedgrading' => $gradinginfo['hasadvancedgrading'],
        'gradingmethodname' => $gradinginfo['gradingmethodname'],
        'showrightcolumn' => $showrightcolumn,
        'haspenalties' => $penaltyinfo['haspenalties'],
        'penalties' => $penaltyinfo['penalties'],
    ];

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_unifiedgrader/feedback_view_forum', $templatedata);
    echo $OUTPUT->footer();
    exit;
}

// ──────────────────────────────────────────────
//  Quiz feedback view.
// ──────────────────────────────────────────────
if ($cm->modname === 'quiz') {
    $attemptnum = optional_param('attempt', 0, PARAM_INT);
    $activityinfo = $adapter->get_activity_info();

    // Load all finished attempts for the attempt selector.
    $attempts = $adapter->get_attempts($userid);
    $hasmultipleattempts = count($attempts) > 1;

    // Determine which attempt to show (default to latest).
    $selectedattempt = 0;
    if ($attemptnum > 0) {
        $selectedattempt = $attemptnum;
    } else if (!empty($attempts)) {
        $selectedattempt = end($attempts)['attemptnumber'];
    }

    // Load data for the selected attempt.
    if ($selectedattempt > 0) {
        $gradedata = $adapter->get_grade_data_for_attempt($userid, $selectedattempt);
        $submissiondata = $adapter->get_submission_data_for_attempt($userid, $selectedattempt);
    } else {
        $gradedata = $adapter->get_grade_data($userid);
        $submissiondata = $adapter->get_submission_data($userid);
    }

    // Set up the page.
    $PAGE->set_url(new moodle_url('/local/unifiedgrader/view_feedback.php', ['cmid' => $cmid]));
    $PAGE->set_context($context);
    $PAGE->set_title(
        get_string('view_feedback', 'local_unifiedgrader') . ': ' .
        format_string($cm->name),
    );
    $PAGE->set_heading($course->fullname);
    $PAGE->set_pagelayout('standard');
    $PAGE->activityheader->disable();
    $PAGE->add_body_class('local-unifiedgrader-feedback-page');

    // Parse grade and penalties via shared helper.
    $penaltyinfo = feedback_data_helper::format_penalties($cmid, $userid);
    $gradeinfo = feedback_data_helper::format_grade($gradedata, $activityinfo);
    $gradedisplay = $gradeinfo['gradedisplay'];

    // Feedback text (from gradebook for quizzes). Already formatted by get_grade_data()
    // with pluginfile.php URLs rewritten and format_text() applied.
    $feedback = $gradedata['feedback'] ?? '';

    $showrightcolumn = !empty($feedback);

    // Build feedback PDF download URL (includes attempt number).
    $downloadparams = ['cmid' => $cmid];
    if ($selectedattempt > 0) {
        $downloadparams['attempt'] = $selectedattempt;
    }
    $feedbackdownloadurl = (new moodle_url('/local/unifiedgrader/download_feedback.php',
        $downloadparams))->out(false);

    // Build attempt selector data for the template.
    $attemptlist = [];
    foreach ($attempts as $att) {
        $attemptlist[] = [
            'attemptnumber' => $att['attemptnumber'],
            'label' => get_string('attempt', 'quiz', $att['attemptnumber']),
            'date' => userdate($att['timemodified'], get_string('strftimedatetimeshort')),
            'selected' => ($att['attemptnumber'] === $selectedattempt),
            'url' => (new moodle_url('/local/unifiedgrader/view_feedback.php', [
                'cmid' => $cmid,
                'attempt' => $att['attemptnumber'],
            ]))->out(false),
        ];
    }

    // Submission comments.
    $hascommentsfeature = (bool) get_config('local_unifiedgrader', 'enable_submission_comments');
    $commentcount = $hascommentsfeature
        ? \local_unifiedgrader\submission_comment_manager::count_comments($cmid, $userid)
        : 0;

    // Prepare template context.
    $templatedata = [
        'cmid' => $cmid,
        'activityname' => $activityinfo['name'],
        'activityurl' => (new moodle_url('/mod/quiz/view.php', ['id' => $cm->id]))->out(false),
        'gradedisplay' => $gradedisplay,
        'feedback' => $feedback,
        'hasfeedback' => !empty($feedback),
        'attemptcontent' => $submissiondata['content'] ?? '',
        'hasattempt' => !empty($submissiondata['content']),
        'feedbackdownloadurl' => $feedbackdownloadurl,
        'downloadfilename' => clean_filename($course->shortname . '-' . $activityinfo['name'] . '-feedback.pdf'),
        'userid' => $userid,
        'showrightcolumn' => $showrightcolumn,
        'haspenalties' => $penaltyinfo['haspenalties'],
        'penalties' => $penaltyinfo['penalties'],
        'hasmultipleattempts' => $hasmultipleattempts,
        'attempts' => $attemptlist,
        'selectedattempt' => $selectedattempt,
        'hascommentsfeature' => $hascommentsfeature,
        'commentcount' => $commentcount,
    ];

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_unifiedgrader/feedback_view_quiz', $templatedata);
    echo $OUTPUT->footer();
    exit;
}

// ──────────────────────────────────────────────
//  Assignment feedback view.
// ──────────────────────────────────────────────

// Load attempts and determine which one to show.
$attemptnum = optional_param('attempt', -1, PARAM_INT);
$activityinfo = $adapter->get_activity_info();
$attempts = $adapter->get_attempts($userid);
$hasmultipleattempts = count($attempts) > 1;

// Default to latest attempt (highest attemptnumber).
$selectedattempt = -1;
if ($attemptnum >= 0) {
    $selectedattempt = $attemptnum;
} else if (!empty($attempts)) {
    $selectedattempt = end($attempts)['attemptnumber'];
}

// Load grade data and submission files for the selected attempt.
$gradedata = $adapter->get_grade_data_for_attempt($userid, $selectedattempt);
$submissionfiles = $adapter->get_submission_files_for_attempt($userid, $selectedattempt);

// Filter to PDF files and files convertible to PDF (annotations apply to both).
$pdffiles = array_values(array_filter($submissionfiles, function ($f) {
    return $f['mimetype'] === 'application/pdf' || !empty($f['convertible']);
}));
$haspdffiles = !empty($pdffiles);

// Determine which file to show (for PDF submissions).
$selectedfile = null;
$selectedpdfurl = '';
$hasannotatedpdf = false;
$annotatedpdfmap = [];

if ($haspdffiles) {
    $selectedfile = $pdffiles[0];
    if ($fileid) {
        foreach ($pdffiles as $pdf) {
            if ($pdf['fileid'] === $fileid) {
                $selectedfile = $pdf;
                break;
            }
        }
    }

    // Check for flattened annotated PDFs in file storage.
    $fs = get_file_storage();
    foreach ($pdffiles as $pdf) {
        $apdf = $fs->get_file(
            $context->id,
            'local_unifiedgrader',
            'annotatedpdf',
            $pdf['fileid'],
            '/' . $userid . '/',
            'annotated.pdf',
        );
        if ($apdf && !$apdf->is_directory()) {
            $annotatedpdfmap[$pdf['fileid']] = moodle_url::make_pluginfile_url(
                $context->id,
                'local_unifiedgrader',
                'annotatedpdf',
                $pdf['fileid'],
                '/' . $userid . '/',
                'annotated.pdf',
            )->out(false);
        }
    }

    // Always use the original PDF in the interactive viewer (annotations are
    // rendered as a Fabric.js overlay with hover tooltips for comments).
    // The flattened annotated PDF is only used for the download button.
    $selectedpdfurl = $selectedfile['previewurl'];
    $hasannotatedpdf = isset($annotatedpdfmap[$selectedfile['fileid']]);
}

// For non-PDF submissions, build an iframe URL to preview_submission.php
// (same renderer used by the teacher's grading interface).
$submissionpreviewurl = '';
if (!$haspdffiles) {
    $submissionpreviewurl = (new moodle_url('/local/unifiedgrader/preview_submission.php', [
        'cmid' => $cmid,
        'userid' => $userid,
    ]))->out(false);
}

// Set up the page.
$PAGE->set_url(new moodle_url('/local/unifiedgrader/view_feedback.php', ['cmid' => $cmid]));
$PAGE->set_context($context);
$PAGE->set_title(
    get_string('view_feedback', 'local_unifiedgrader') . ': ' .
    format_string($cm->name),
);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('standard');
$PAGE->activityheader->disable();
$PAGE->add_body_class('local-unifiedgrader-feedback-page');

// Parse grade, penalties, and rubric/guide data via shared helper.
$penaltyinfo = feedback_data_helper::format_penalties($cmid, $userid);
$gradeinfo = feedback_data_helper::format_grade($gradedata, $activityinfo);
$gradinginfo = feedback_data_helper::parse_grading_data($gradedata);
$gradedisplay = $gradeinfo['gradedisplay'];

// Create assign object (needed for feedback file rewriting and submission comments).
$assign = new \assign($context, $cm, $course);

// Rewrite @@PLUGINFILE@@ URLs in feedback text so embedded files (videos, images) load.
$feedback = $gradedata['feedback'];
if (!empty($feedback)) {
    $grade = $assign->get_user_grade($userid, false, $selectedattempt);
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

// Check for feedback files (assignfeedback_file plugin).
$feedbackfiles = [];
$hasfeedbackfiles = false;
$grade = $assign->get_user_grade($userid, false, $selectedattempt) ?: null;
if ($grade && $adapter->has_feedback_plugin('file')) {
    $fs = get_file_storage();
    $files = $fs->get_area_files(
        $context->id,
        'assignfeedback_file',
        'feedback_files',
        (int) $grade->id,
        'sortorder, filename',
        false,
    );
    foreach ($files as $file) {
        $downloadurl = moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename(),
            true,
        );
        $feedbackfiles[] = [
            'filename' => $file->get_filename(),
            'filesize' => display_size($file->get_filesize()),
            'url' => $downloadurl->out(false),
            'iconurl' => $OUTPUT->image_url(file_file_icon($file))->out(false),
        ];
    }
    $hasfeedbackfiles = !empty($feedbackfiles);
}

// Submission comments via our custom table (works for all activity types).
$hascommentsfeature = true;
$commentcount = \local_unifiedgrader\submission_comment_manager::count_comments($cmid, $userid);
$canpostcomments = has_capability('local/unifiedgrader:viewfeedback', $context);

$showrightcolumn = !empty($feedback) || $gradinginfo['hasadvancedgrading'];

// Build feedback PDF download URL (include attempt number if set).
$downloadparams = [
    'cmid' => $cmid,
    'fileid' => $selectedfile ? $selectedfile['fileid'] : 0,
];
if ($selectedattempt >= 0) {
    $downloadparams['attempt'] = $selectedattempt;
}
$feedbackdownloadurl = (new moodle_url('/local/unifiedgrader/download_feedback.php',
    $downloadparams))->out(false);

// Build attempt selector data for the template.
$attemptlist = [];
foreach ($attempts as $att) {
    $attemptlist[] = [
        'attemptnumber' => $att['attemptnumber'],
        'label' => get_string('attempt_label', 'local_unifiedgrader', $att['attemptnumber'] + 1),
        'date' => userdate($att['timemodified'], get_string('strftimedatetimeshort')),
        'selected' => ($att['attemptnumber'] === $selectedattempt),
        'url' => (new moodle_url('/local/unifiedgrader/view_feedback.php', [
            'cmid' => $cmid,
            'attempt' => $att['attemptnumber'],
        ]))->out(false),
    ];
}

// Prepare template context.
$templatedata = [
    'cmid' => $cmid,
    'activityname' => $activityinfo['name'],
    'activityurl' => (new moodle_url('/mod/assign/view.php', ['id' => $cm->id]))->out(false),
    'gradedisplay' => $gradedisplay,
    'feedback' => $feedback,
    'hasfeedback' => !empty($feedback),
    'haspdffiles' => $haspdffiles,
    'selectedfileid' => $selectedfile ? $selectedfile['fileid'] : 0,
    'selectedfileurl' => $selectedpdfurl,
    'selectedfilename' => $selectedfile ? $selectedfile['filename'] : '',
    'pdffiles' => $pdffiles,
    'hasmultiplefiles' => count($pdffiles) > 1,
    'pdffilesjson' => json_encode($pdffiles),
    'hasannotatedpdf' => $hasannotatedpdf,
    'annotatedpdfurl' => $hasannotatedpdf ? $annotatedpdfmap[$selectedfile['fileid']] : '',
    'downloadfilename' => clean_filename($course->shortname . '-' . $activityinfo['name'] . '-feedback.pdf'),
    'annotatedpdfmapjson' => json_encode($annotatedpdfmap),
    'feedbackdownloadurl' => $feedbackdownloadurl,
    'submissionpreviewurl' => $submissionpreviewurl,
    'userid' => $userid,
    'hasfeedbackfiles' => $hasfeedbackfiles,
    'feedbackfiles' => $feedbackfiles,
    'feedbackfilecount' => count($feedbackfiles),
    'feedbackfilesjson' => json_encode($feedbackfiles),
    'hascommentsfeature' => $hascommentsfeature,
    'commentcount' => $commentcount,
    'canpostcomments' => $canpostcomments,
    'showrightcolumn' => $showrightcolumn,
    'hasrubric' => $gradinginfo['hasrubric'],
    'rubriccriteria' => $gradinginfo['rubriccriteria'],
    'rubrictotal' => $gradinginfo['rubrictotal'],
    'hasguide' => $gradinginfo['hasguide'],
    'guidecriteria' => $gradinginfo['guidecriteria'],
    'guidetotal' => $gradinginfo['guidetotal'],
    'guidemaxtotal' => $gradinginfo['guidemaxtotal'],
    'hasadvancedgrading' => $gradinginfo['hasadvancedgrading'],
    'gradingmethodname' => $gradinginfo['gradingmethodname'],
    'haspenalties' => $penaltyinfo['haspenalties'],
    'penalties' => $penaltyinfo['penalties'],
    'hasmultipleattempts' => $hasmultipleattempts,
    'attempts' => $attemptlist,
];

// Output.
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_unifiedgrader/feedback_view', $templatedata);
echo $OUTPUT->footer();
