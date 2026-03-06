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
 * Embedded quiz extension form page for the unified grader.
 *
 * Renders a date/time picker for granting or editing a quiz due date extension
 * via the quizaccess_duedate plugin. Uses embedded layout (no chrome) for use
 * inside an iframe modal. Communicates results back via postMessage.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

global $DB, $PAGE, $OUTPUT;

$cmid = required_param('cmid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'quiz');
$context = context_module::instance($cm->id);

require_login($course, false, $cm);

// Verify the duedate plugin is installed.
if (!class_exists('\quizaccess_duedate\override_manager')) {
    throw new moodle_exception('quiz_extension_plugin_missing', 'local_unifiedgrader');
}

require_capability('quizaccess/duedate:manageoverrides', $context);

$PAGE->set_pagelayout('embedded');
$PAGE->set_url(new moodle_url('/local/unifiedgrader/quiz_extension.php', [
    'cmid' => $cmid,
    'userid' => $userid,
]));
$PAGE->set_title(get_string('action_grant_extension', 'local_unifiedgrader'));

// Load the quiz adapter to access extension methods.
$adapter = new \local_unifiedgrader\adapter\quiz_adapter($cm, $context, $course);

// Get the quiz-level duedate for reference and validation.
$quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
$settings = $DB->get_record('quizaccess_duedate_instances', ['quizid' => $quiz->id]);
$quizduedate = $settings ? (int) $settings->duedate : 0;

// Load existing extension (if any) for pre-populating the form.
$existing = $adapter->get_duedate_extension($userid);

$url = new moodle_url('/local/unifiedgrader/quiz_extension.php', [
    'cmid' => $cmid,
    'userid' => $userid,
]);

$existingduedate = $existing ? (int) $existing['duedate'] : 0;
$mform = new \local_unifiedgrader\form\quiz_extension_form($url, [
    'duedate' => $quizduedate,
    'existingextension' => $existingduedate,
]);

// Pre-populate form data.
$data = new stdClass();
$data->cmid = $cmid;
$data->userid = $userid;
$data->extensionduedate = $existing ? $existing['duedate'] : $quizduedate;
$mform->set_data($data);

if ($mform->is_cancelled()) {
    echo $OUTPUT->header();
    echo '<script>window.parent.postMessage({type: "extension_cancelled"}, "*");</script>';
    echo $OUTPUT->footer();
    exit;
}

$fromform = $mform->get_data();
if ($fromform) {
    $adapter->save_duedate_extension($userid, (int) $fromform->extensionduedate);

    echo $OUTPUT->header();
    echo '<script>window.parent.postMessage({type: "extension_saved"}, "*");</script>';
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
