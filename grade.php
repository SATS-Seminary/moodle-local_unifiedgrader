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
 * Main grading interface entry point.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_unifiedgrader\adapter\adapter_factory;

$cmid = required_param('cmid', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

// Load course module and course.
[$course, $cm] = get_course_and_cm_from_cmid($cmid);

// Require login and check capabilities.
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('local/unifiedgrader:grade', $context);

// Create the adapter (validates activity type and enabled status).
$adapter = adapter_factory::create($cmid);

// Set up the page.
$PAGE->set_url(new moodle_url('/local/unifiedgrader/grade.php', ['cmid' => $cmid]));
$PAGE->set_context($context);
$PAGE->set_title(
    get_string('grading_interface', 'local_unifiedgrader') . ': ' .
    format_string($cm->name),
);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('embedded');
$PAGE->set_pagetype('local-unifiedgrader-grade');

// Navbar breadcrumb.
$PAGE->navbar->add(
    format_string($cm->name),
    new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]),
);
$PAGE->navbar->add(get_string('grading_interface', 'local_unifiedgrader'));

// Resolve group mode and available groups.
$groupmode = groups_get_activity_groupmode($cm, $course);
$availablegroups = [];
$currentgroup = 0;
if ($groupmode != NOGROUPS) {
    $aag = has_capability('moodle/site:accessallgroups', $context);
    $availablegroups = groups_get_all_groups($course->id, 0, $cm->groupingid, 'g.id, g.name');
    // If the user cannot access all groups, restrict to their own groups.
    if (!$aag) {
        global $USER;
        $usergroups = groups_get_all_groups($course->id, $USER->id, $cm->groupingid, 'g.id, g.name');
        $availablegroups = $usergroups;
        // Default to the user's first group.
        $currentgroup = !empty($usergroups) ? (int) reset($usergroups)->id : 0;
    }
}

// Load initial data server-side to avoid loading flash.
$activityinfo = $adapter->get_activity_info();
$participants = $adapter->get_participants([
    'sort' => 'submittedat',
    'sortdir' => 'asc',
    'group' => $currentgroup,
]);
$initialuserid = $userid ?: ($participants[0]['id'] ?? 0);

// Capability flags for the template.
$canviewall = has_capability('local/unifiedgrader:viewall', $context);
$canviewnotes = has_capability('local/unifiedgrader:viewnotes', $context);
$canmanagenotes = has_capability('local/unifiedgrader:managenotes', $context);
$coursecontext = context_course::instance($course->id);
$canloginas = has_capability('moodle/user:loginas', $coursecontext);

// Grade posting status.
$gradesposted = $adapter->are_grades_posted();
$gradeshidden = $adapter->get_grades_hidden_value();

// Quizzes: post grades toggle is hidden by default (quiz review options control visibility).
// When enabled via setting, the schedule option is still hidden (review options are state-based, not date-based).
$showpostgrades = true;
$showschedulepost = true;
if ($cm->modname === 'quiz') {
    $showpostgrades = !empty(get_config('local_unifiedgrader', 'enable_quiz_post_grades'));
    $showschedulepost = false;
}

// Prepare template context.
$templatedata = [
    'cmid' => $cmid,
    'courseid' => $course->id,
    'userid' => $initialuserid,
    'activityinfojson' => json_encode($activityinfo),
    'participantsjson' => json_encode($participants),
    'activityname' => $activityinfo['name'],
    'activitytype' => $activityinfo['type'],
    'maxgrade' => $activityinfo['maxgrade'],
    'gradingmethod' => $activityinfo['gradingmethod'],
    'canviewall' => $canviewall,
    'canviewnotes' => $canviewnotes,
    'canmanagenotes' => $canmanagenotes,
    'canloginas' => $canloginas,
    'issimplegrading' => $activityinfo['gradingmethod'] === 'simple',
    'courseshortname' => format_string($course->shortname),
    'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
    'activityurl' => (new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]))->out(false),
    'submissionsurl' => match ($cm->modname) {
        'assign' => (new moodle_url('/mod/assign/view.php', ['id' => $cm->id, 'action' => 'grading']))->out(false),
        'quiz' => (new moodle_url('/mod/quiz/report.php', ['id' => $cm->id, 'mode' => 'grading']))->out(false),
        default => (new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]))->out(false),
    },
    'uniqid' => uniqid(),
    'draftitemid' => 0,
    'hasgroupmode' => $groupmode != NOGROUPS,
    'groupsjson' => json_encode(array_values(array_map(function ($g) {
        return ['id' => (int) $g->id, 'name' => format_string($g->name)];
    }, $availablegroups))),
    'currentgroup' => $currentgroup,
    'allowmanualgradeoverride' => !empty(get_config('local_unifiedgrader', 'allow_manual_grade_override')),
    'gradesposted' => $gradesposted,
    'gradeshidden' => $gradeshidden,
    'showpostgrades' => $showpostgrades,
    'showschedulepost' => $showschedulepost,
    'coursecode' => \local_unifiedgrader\course_code_helper::extract_code($course->shortname),
    'coursefullname' => format_string($course->fullname),
    'enablereportform' => !empty(get_config('local_unifiedgrader', 'enable_report_form')),
    'reportformurl' => get_config('local_unifiedgrader', 'report_form_url') ?: '',
    'graderfullname' => fullname($USER),
];

// TinyMCE editor setup for the feedback textarea.
// The textarea is rendered in the static Mustache template, so use_editor() can find it.
$editor = editors_get_preferred_editor(FORMAT_HTML);
if ($editor instanceof \editor_tiny\editor) {
    global $CFG;
    require_once($CFG->dirroot . '/repository/lib.php');

    $draftitemid = file_get_unused_draft_itemid();
    $feedbackeditorid = 'feedback-editor-' . $templatedata['uniqid'];

    $editoroptions = [
        'context' => $context,
        'maxfiles' => EDITOR_UNLIMITED_FILES,
        'maxbytes' => $CFG->maxbytes,
        'noclean' => true,
        'subdirs' => true,
        'autosave' => false,
    ];

    $fpoptions = [];
    $fptypes = [
        'image' => ['image'],
        'media' => ['video', 'audio'],
        'link' => ['*'],
    ];
    foreach ($fptypes as $type => $accepted) {
        $args = new \stdClass();
        $args->accepted_types = $accepted;
        $args->return_types = FILE_INTERNAL;
        $args->context = $context;
        $args->env = 'editor';
        $fpoptions[$type] = initialise_filepicker($args);
        $fpoptions[$type]->itemid = $draftitemid;
        $fpoptions[$type]->context = $context;
        $fpoptions[$type]->client_id = uniqid();
    }

    $editor->use_editor($feedbackeditorid, $editoroptions, $fpoptions);
    $templatedata['draftitemid'] = $draftitemid;
}

// Feedback files filemanager setup (assignfeedback_file).
$feedbackfileshtml = '';
$feedbackfilesdraftid = 0;
$hasfeedbackfileplugin = false;
$feedbackfilesclientid = '';

if ($cm->modname === 'assign' && $adapter->has_feedback_plugin('file')) {
    $hasfeedbackfileplugin = true;
    $feedbackfilesdraftid = file_get_unused_draft_itemid();
    $feedbackfilesclientid = 'fbfiles_' . $cmid;

    global $CFG;
    require_once($CFG->libdir . '/form/filemanager.php');

    $fm = new \form_filemanager((object) [
        'client_id' => $feedbackfilesclientid,
        'itemid' => $feedbackfilesdraftid,
        'maxbytes' => $course->maxbytes,
        'maxfiles' => -1,
        'subdirs' => true,
        'accepted_types' => '*',
        'return_types' => FILE_INTERNAL,
        'context' => $context,
    ]);
    $filesrenderer = $PAGE->get_renderer('core', 'files');
    $feedbackfileshtml = $filesrenderer->render($fm);
}

$templatedata['hasfeedbackfileplugin'] = $hasfeedbackfileplugin;
$templatedata['feedbackfileshtml'] = $feedbackfileshtml;
$templatedata['feedbackfilesdraftid'] = $feedbackfilesdraftid;
$templatedata['feedbackfilesclientid'] = $feedbackfilesclientid;

// Output.
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_unifiedgrader/grading_interface', $templatedata);
echo $OUTPUT->footer();
