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

// Only supported activity types.
$supported = ['assign', 'forum'];
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
        new moodle_url('/mod/forum/view.php', ['id' => $cm->id]),
    );
    $PAGE->navbar->add(get_string('view_feedback', 'local_unifiedgrader'));

    // Format grade display.
    $gradedisplay = '';
    if ($gradedata['grade'] !== null) {
        $gradedisplay = round($gradedata['grade'], 2) . ' / ' . round($activityinfo['maxgrade'], 2);
    }

    // Parse rubric/guide data for template.
    $gradingdefinition = null;
    $rubricdata = null;
    $hasrubric = false;
    $hasguide = false;
    $rubriccriteria = [];
    $guidecriteria = [];
    $rubrictotal = 0;
    $guidetotal = 0;
    $guidemaxtotal = 0;

    if (!empty($gradedata['gradingdefinition'])) {
        $gradingdefinition = json_decode($gradedata['gradingdefinition'], true);
    }
    if (!empty($gradedata['rubricdata'])) {
        $rubricdata = json_decode($gradedata['rubricdata'], true);
    }

    if ($gradingdefinition && !empty($gradingdefinition['criteria'])) {
        if ($gradingdefinition['method'] === 'rubric') {
            $hasrubric = true;
            // Build fill map from rubricdata (criterionid => selected levelid).
            $fillmap = [];
            if ($rubricdata && !empty($rubricdata['criteria'])) {
                foreach ($rubricdata['criteria'] as $critid => $critdata) {
                    if (!empty($critdata['levelid'])) {
                        $fillmap[(int) $critid] = (int) $critdata['levelid'];
                    }
                }
            }
            foreach ($gradingdefinition['criteria'] as $criterion) {
                $levels = [];
                $selectedscore = null;
                foreach ($criterion['levels'] as $level) {
                    $isselected = isset($fillmap[$criterion['id']])
                        && $fillmap[$criterion['id']] === $level['id'];
                    $levels[] = [
                        'score' => $level['score'],
                        'definition' => $level['definition'],
                        'selected' => $isselected,
                    ];
                    if ($isselected) {
                        $selectedscore = $level['score'];
                    }
                }
                $rubriccriteria[] = [
                    'description' => $criterion['description'],
                    'levels' => $levels,
                    'selectedscore' => $selectedscore,
                    'hasselection' => $selectedscore !== null,
                ];
                if ($selectedscore !== null) {
                    $rubrictotal += $selectedscore;
                }
            }
        } else if ($gradingdefinition['method'] === 'guide') {
            $hasguide = true;
            $fillmap = [];
            if ($rubricdata && !empty($rubricdata['criteria'])) {
                foreach ($rubricdata['criteria'] as $critid => $critdata) {
                    $fillmap[(int) $critid] = [
                        'score' => $critdata['score'] ?? '',
                        'remark' => $critdata['remark'] ?? '',
                    ];
                }
            }
            foreach ($gradingdefinition['criteria'] as $criterion) {
                $fill = $fillmap[$criterion['id']] ?? ['score' => '', 'remark' => ''];
                $score = $fill['score'] !== '' ? (float) $fill['score'] : null;
                $guidecriteria[] = [
                    'shortname' => $criterion['shortname'],
                    'description' => $criterion['description'] ?? '',
                    'maxscore' => $criterion['maxscore'],
                    'score' => $score,
                    'hasscore' => $score !== null,
                    'remark' => $fill['remark'],
                    'hasremark' => !empty($fill['remark']),
                ];
                if ($score !== null) {
                    $guidetotal += $score;
                }
                $guidemaxtotal += (float) $criterion['maxscore'];
            }
        }
    }

    // Feedback text (from gradebook for forums).
    $feedback = $gradedata['feedback'] ?? '';
    if (!empty($feedback)) {
        $feedback = format_text($feedback, $gradedata['feedbackformat'] ?? FORMAT_HTML, ['context' => $context]);
    }

    $showrightcolumn = !empty($feedback) || $hasrubric || $hasguide;

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
        'hasrubric' => $hasrubric,
        'rubriccriteria' => $rubriccriteria,
        'rubrictotal' => $rubrictotal,
        'hasguide' => $hasguide,
        'guidecriteria' => $guidecriteria,
        'guidetotal' => round($guidetotal, 2),
        'guidemaxtotal' => round($guidemaxtotal, 2),
        'hasadvancedgrading' => $hasrubric || $hasguide,
        'gradingmethodname' => $hasrubric
            ? get_string('rubric', 'local_unifiedgrader')
            : ($hasguide ? get_string('markingguide', 'local_unifiedgrader') : ''),
        'showrightcolumn' => $showrightcolumn,
    ];

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_unifiedgrader/feedback_view_forum', $templatedata);
    echo $OUTPUT->footer();
    exit;
}

// ──────────────────────────────────────────────
//  Assignment feedback view.
// ──────────────────────────────────────────────

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

// Always use the original PDF in the interactive viewer (annotations are
// rendered as a Fabric.js overlay with hover tooltips for comments).
// The flattened annotated PDF is only used for the download button.
$selectedpdfurl = $selectedfile['previewurl'];
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

// Create assign object (needed for feedback file rewriting and submission comments).
$assign = new \assign($context, $cm, $course);

// Rewrite @@PLUGINFILE@@ URLs in feedback text so embedded files (videos, images) load.
$feedback = $gradedata['feedback'];
if (!empty($feedback)) {
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

// Check for feedback files (assignfeedback_file plugin).
$feedbackfiles = [];
$hasfeedbackfiles = false;
$grade = $assign->get_user_grade($userid, false) ?: null;
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
            'iconurl' => $OUTPUT->image_url(file_file_icon($file, 24))->out(false),
        ];
    }
    $hasfeedbackfiles = !empty($feedbackfiles);
}

// Check for submission comments feature.
$hascommentsfeature = false;
$commentcount = 0;
$canpostcomments = false;
$submission = $assign->get_user_submission($userid, false);
if ($submission) {
    foreach ($assign->get_submission_plugins() as $plugin) {
        if ($plugin->get_type() === 'comments' && $plugin->is_enabled()) {
            $hascommentsfeature = true;
            require_once($CFG->dirroot . '/comment/lib.php');
            $commentoptions = new \stdClass();
            $commentoptions->context = $context;
            $commentoptions->component = 'assignsubmission_comments';
            $commentoptions->itemid = $submission->id;
            $commentoptions->area = 'submission_comments';
            $commentoptions->course = $course;
            $commentoptions->cm = $cm;
            $commentobj = new \comment($commentoptions);
            $commentcount = $commentobj->count();
            $canpostcomments = $commentobj->can_post();
            break;
        }
    }
}

$showrightcolumn = !empty($feedback) || $hasfeedbackfiles || $hascommentsfeature;

// Prepare template context.
$templatedata = [
    'cmid' => $cmid,
    'activityname' => $activityinfo['name'],
    'activityurl' => (new moodle_url('/mod/assign/view.php', ['id' => $cm->id]))->out(false),
    'gradedisplay' => $gradedisplay,
    'feedback' => $feedback,
    'hasfeedback' => !empty($feedback),
    'selectedfileid' => $selectedfile['fileid'],
    'selectedfileurl' => $selectedpdfurl,
    'selectedfilename' => $selectedfile['filename'],
    'pdffiles' => $pdffiles,
    'hasmultiplefiles' => count($pdffiles) > 1,
    'pdffilesjson' => json_encode($pdffiles),
    'hasannotatedpdf' => $hasannotatedpdf,
    'annotatedpdfurl' => $hasannotatedpdf ? $annotatedpdfmap[$selectedfile['fileid']] : '',
    'annotatedpdfmapjson' => json_encode($annotatedpdfmap),
    'userid' => $userid,
    'hasfeedbackfiles' => $hasfeedbackfiles,
    'feedbackfiles' => $feedbackfiles,
    'hascommentsfeature' => $hascommentsfeature,
    'commentcount' => $commentcount,
    'canpostcomments' => $canpostcomments,
    'showrightcolumn' => $showrightcolumn,
];

// Output.
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_unifiedgrader/feedback_view', $templatedata);
echo $OUTPUT->footer();
