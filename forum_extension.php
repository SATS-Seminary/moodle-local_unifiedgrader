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
 * Embedded forum extension form page for the unified grader.
 *
 * Renders a date/time picker for granting or editing a forum due date extension.
 * Stored in the local_unifiedgrader_fext table. Uses embedded layout (no chrome)
 * for use inside an iframe modal. Communicates results back via postMessage.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

global $DB, $PAGE, $OUTPUT, $USER;

$cmid = required_param('cmid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'forum');
$context = context_module::instance($cm->id);

require_login($course, false, $cm);
require_capability('local/unifiedgrader:grade', $context);

$PAGE->set_pagelayout('embedded');
$PAGE->set_url(new moodle_url('/local/unifiedgrader/forum_extension.php', [
    'cmid' => $cmid,
    'userid' => $userid,
]));
$PAGE->set_title(get_string('action_grant_extension', 'local_unifiedgrader'));

// Get the forum due date.
$forum = $DB->get_record('forum', ['id' => $cm->instance], '*', MUST_EXIST);
$forumduedate = (int) ($forum->duedate ?? 0);

// Load existing extension (if any).
$existing = $DB->get_record('local_unifiedgrader_fext', [
    'cmid' => $cmid,
    'userid' => $userid,
]);
$existingduedate = $existing ? (int) $existing->extensionduedate : 0;

$url = new moodle_url('/local/unifiedgrader/forum_extension.php', [
    'cmid' => $cmid,
    'userid' => $userid,
]);

$mform = new \local_unifiedgrader\form\forum_extension_form($url, [
    'duedate' => $forumduedate,
    'existingextension' => $existingduedate,
]);

// Pre-populate form data.
$data = new stdClass();
$data->cmid = $cmid;
$data->userid = $userid;
$data->extensionduedate = $existing ? $existing->extensionduedate : $forumduedate;
$mform->set_data($data);

if ($mform->is_cancelled()) {
    echo $OUTPUT->header();
    echo '<script>window.parent.postMessage({type: "extension_cancelled"}, "*");</script>';
    echo $OUTPUT->footer();
    exit;
}

$fromform = $mform->get_data();
if ($fromform) {
    $now = time();

    if ($existing) {
        $existing->extensionduedate = (int) $fromform->extensionduedate;
        $existing->timemodified = $now;
        $DB->update_record('local_unifiedgrader_fext', $existing);
    } else {
        $DB->insert_record('local_unifiedgrader_fext', (object) [
            'cmid' => $cmid,
            'userid' => $userid,
            'extensionduedate' => (int) $fromform->extensionduedate,
            'authorid' => (int) $USER->id,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    // Re-sync the late penalty and gradebook grade now that the effective due date changed.
    $adapter = \local_unifiedgrader\adapter\adapter_factory::create($cmid);
    $lateinfo = $adapter->calculate_late_penalty($userid);
    \local_unifiedgrader\penalty_manager::sync_late_penalty(
        $cmid,
        $userid,
        $lateinfo['percentage'] ?? null,
        $lateinfo['dayslate'] ?? 0,
    );
    $adapter->sync_gradebook_penalty($userid);

    echo $OUTPUT->header();
    echo '<script>window.parent.postMessage({type: "extension_saved"}, "*");</script>';
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
