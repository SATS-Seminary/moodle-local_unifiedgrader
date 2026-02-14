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
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

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

// Load initial data server-side to avoid loading flash.
$activityinfo = $adapter->get_activity_info();
$participants = $adapter->get_participants(['sort' => 'submittedat', 'sortdir' => 'asc']);
$initialuserid = $userid ?: ($participants[0]['id'] ?? 0);

// Capability flags for the template.
$canviewall = has_capability('local/unifiedgrader:viewall', $context);
$canviewnotes = has_capability('local/unifiedgrader:viewnotes', $context);
$canmanagenotes = has_capability('local/unifiedgrader:managenotes', $context);

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
    'issimplegrading' => $activityinfo['gradingmethod'] === 'simple',
    'courseshortname' => format_string($course->shortname),
    'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
    'activityurl' => (new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]))->out(false),
    'submissionsurl' => (new moodle_url('/mod/' . $cm->modname . '/view.php', [
        'id' => $cm->id,
        'action' => 'grading',
    ]))->out(false),
    'uniqid' => uniqid(),
];

// Output.
echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_unifiedgrader/grading_interface', $templatedata);
echo $OUTPUT->footer();
