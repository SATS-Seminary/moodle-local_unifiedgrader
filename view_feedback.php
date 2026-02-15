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
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_unifiedgrader\adapter\adapter_factory;

$cmid = required_param('cmid', PARAM_INT);
$fileid = optional_param('fileid', 0, PARAM_INT);

// Load course module and course.
[$course, $cm] = get_course_and_cm_from_cmid($cmid);

// Require login and check capabilities.
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('local/unifiedgrader:viewfeedback', $context);

// Only assignment activities support annotations.
if ($cm->modname !== 'assign') {
    throw new moodle_exception('invalidactivitytype', 'local_unifiedgrader');
}

// Verify the plugin is enabled for this activity type.
if (!get_config('local_unifiedgrader', 'enable_assign')) {
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

// Load grade data and submission files.
$gradedata = $adapter->get_grade_data($userid);
$submissionfiles = $adapter->get_submission_files($userid);
$activityinfo = $adapter->get_activity_info();

// Filter to PDF files and files convertible to PDF (annotations apply to both).
$pdffiles = array_values(array_filter($submissionfiles, function ($f) {
    return $f['mimetype'] === 'application/pdf' || !empty($f['convertible']);
}));

if (empty($pdffiles)) {
    // No PDF files — show message.
    $PAGE->set_url(new moodle_url('/local/unifiedgrader/view_feedback.php', ['cmid' => $cmid]));
    $PAGE->set_context($context);
    $PAGE->set_title(get_string('view_feedback', 'local_unifiedgrader'));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->notification(
        get_string('no_annotated_files', 'local_unifiedgrader'),
        'info',
    );
    echo $OUTPUT->footer();
    exit;
}

// Determine which file to show.
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
$annotatedpdfmap = [];
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

// Use flattened PDF URL for the selected file if available.
$selectedpdfurl = $annotatedpdfmap[$selectedfile['fileid']] ?? $selectedfile['previewurl'];
$hasannotatedpdf = isset($annotatedpdfmap[$selectedfile['fileid']]);

// Set up the page.
$PAGE->set_url(new moodle_url('/local/unifiedgrader/view_feedback.php', ['cmid' => $cmid]));
$PAGE->set_context($context);
$PAGE->set_title(
    get_string('view_feedback', 'local_unifiedgrader') . ': ' .
    format_string($cm->name),
);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('standard');

// Navbar breadcrumb.
$PAGE->navbar->add(
    format_string($cm->name),
    new moodle_url('/mod/assign/view.php', ['id' => $cm->id]),
);
$PAGE->navbar->add(get_string('view_feedback', 'local_unifiedgrader'));

// Format grade display.
$gradedisplay = '';
if ($gradedata['grade'] !== null) {
    $gradedisplay = round($gradedata['grade'], 2) . ' / ' . round($activityinfo['maxgrade'], 2);
}

// Prepare template context.
$templatedata = [
    'cmid' => $cmid,
    'activityname' => $activityinfo['name'],
    'activityurl' => (new moodle_url('/mod/assign/view.php', ['id' => $cm->id]))->out(false),
    'gradedisplay' => $gradedisplay,
    'feedback' => $gradedata['feedback'],
    'hasfeedback' => !empty($gradedata['feedback']),
    'selectedfileid' => $selectedfile['fileid'],
    'selectedfileurl' => $selectedpdfurl,
    'selectedfilename' => $selectedfile['filename'],
    'pdffiles' => $pdffiles,
    'hasmultiplefiles' => count($pdffiles) > 1,
    'pdffilesjson' => json_encode($pdffiles),
    'hasannotatedpdf' => $hasannotatedpdf,
    'annotatedpdfurl' => $hasannotatedpdf ? $annotatedpdfmap[$selectedfile['fileid']] : '',
    'annotatedpdfmapjson' => json_encode($annotatedpdfmap),
];

// Output.
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_unifiedgrader/feedback_view', $templatedata);
echo $OUTPUT->footer();
