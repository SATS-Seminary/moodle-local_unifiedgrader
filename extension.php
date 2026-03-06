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
 * Embedded extension form page for the unified grader.
 *
 * Renders Moodle's native assignment extension form with embedded layout
 * (no chrome) for use inside an iframe modal. Communicates results back
 * to the parent window via postMessage.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

global $CFG, $DB, $PAGE, $OUTPUT;

require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/assign/extensionform.php');

$cmid = required_param('cmid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'assign');
$context = context_module::instance($cm->id);

require_login($course, false, $cm);
require_capability('mod/assign:grantextension', $context);

$PAGE->set_pagelayout('embedded');
$PAGE->set_url(new moodle_url('/local/unifiedgrader/extension.php', [
    'cmid' => $cmid,
    'userid' => $userid,
]));
$PAGE->set_title(get_string('action_grant_extension', 'local_unifiedgrader'));

$assign = new assign($context, $cm, $course);
$instance = $assign->get_instance();

// Account for user overrides when setting date constraints.
$override = $assign->override_exists($userid);
$keys = ['duedate', 'cutoffdate', 'allowsubmissionsfromdate'];
foreach ($keys as $key) {
    if ($override->{$key}) {
        $instance->{$key} = $override->{$key};
    }
}

$formparams = [
    'instance' => $instance,
    'assign' => $assign,
    'userlist' => [$userid],
];

$url = new moodle_url('/local/unifiedgrader/extension.php', [
    'cmid' => $cmid,
    'userid' => $userid,
]);

$mform = new mod_assign_extension_form($url, $formparams);

// Pre-populate the form with required hidden fields.
$data = new stdClass();
$data->id = $cmid;
$data->userid = $userid;
$data->selectedusers = (string) $userid;
$mform->set_data($data);

if ($mform->is_cancelled()) {
    echo $OUTPUT->header();
    echo '<script>window.parent.postMessage({type: "extension_cancelled"}, "*");</script>';
    echo $OUTPUT->footer();
    exit;
}

$fromform = $mform->get_data();
if ($fromform) {
    $extensionduedate = $fromform->extensionduedate ?: 0;
    $result = $assign->save_user_extension($userid, $extensionduedate);

    if ($result) {
        echo $OUTPUT->header();
        echo '<script>window.parent.postMessage({type: "extension_saved"}, "*");</script>';
        echo $OUTPUT->footer();
    } else {
        // Extension date was invalid (before due date, etc.).
        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string('extensionnotafterduedate', 'assign'), 'error');
        echo '<script>
            setTimeout(function() {
                window.parent.postMessage({type: "extension_cancelled"}, "*");
            }, 2000);
        </script>';
        echo $OUTPUT->footer();
    }
    exit;
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
