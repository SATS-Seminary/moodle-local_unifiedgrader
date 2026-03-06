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
 * Embedded override form page for the unified grader.
 *
 * Renders Moodle's native override form with embedded layout (no chrome)
 * for use inside an iframe modal. Communicates results back to the parent
 * window via postMessage.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

global $CFG, $DB, $PAGE, $OUTPUT;

$cmid = required_param('cmid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$overrideid = optional_param('overrideid', 0, PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);
require_capability('local/unifiedgrader:grade', $context);

$PAGE->set_pagelayout('embedded');
$PAGE->set_url(new moodle_url('/local/unifiedgrader/override.php', [
    'cmid' => $cmid,
    'userid' => $userid,
    'overrideid' => $overrideid,
]));

$modname = $cm->modname;

if ($modname === 'assign') {
    handle_assign_override($cm, $context, $course, $userid, $overrideid);
} else if ($modname === 'quiz') {
    handle_quiz_override($cm, $context, $course, $userid, $overrideid);
} else {
    throw new moodle_exception('invalidmodule', 'local_unifiedgrader');
}

/**
 * Handle assign override form.
 *
 * @param cm_info $cm
 * @param context_module $context
 * @param stdClass $course
 * @param int $userid
 * @param int $overrideid
 */
function handle_assign_override($cm, $context, $course, $userid, $overrideid) {
    global $CFG, $DB, $PAGE, $OUTPUT;

    require_once($CFG->dirroot . '/mod/assign/locallib.php');
    require_once($CFG->dirroot . '/mod/assign/override_form.php');

    require_capability('mod/assign:manageoverrides', $context);

    $assign = new assign($context, $cm, $course);
    $instance = $assign->get_instance();
    $PAGE->set_title(get_string('override', 'local_unifiedgrader'));

    // Build override data for the form.
    $override = null;
    if ($overrideid) {
        $override = $DB->get_record('assign_overrides', ['id' => $overrideid], '*', MUST_EXIST);
    }

    // Prepare form defaults.
    $data = $override ? clone $override : new stdClass();
    $keys = ['duedate', 'cutoffdate', 'allowsubmissionsfromdate', 'timelimit'];
    foreach ($keys as $key) {
        if (!isset($data->{$key})) {
            $data->{$key} = $instance->{$key};
        }
    }
    // Pre-select user for new overrides.
    if (!$override && $userid) {
        $data->userid = $userid;
    }

    $url = new moodle_url('/local/unifiedgrader/override.php', [
        'cmid' => $cm->id,
        'userid' => $userid,
        'overrideid' => $overrideid,
    ]);

    $mform = new assign_override_form($url, $cm, $assign, $context, false, $override, $userid);
    $mform->set_data($data);

    if ($mform->is_cancelled()) {
        output_postmessage('override_cancelled');
        return;
    }

    $fromform = $mform->get_data();
    if ($fromform) {
        save_assign_override($assign, $instance, $context, $override, $fromform, $keys, $userid, $cm);
        output_postmessage('override_saved');
        return;
    }

    echo $OUTPUT->header();
    $mform->display();
    echo $OUTPUT->footer();
}

/**
 * Save an assign override (insert or update).
 *
 * Replicates the save logic from /mod/assign/overrideedit.php.
 *
 * @param assign $assign
 * @param stdClass $instance
 * @param context_module $context
 * @param stdClass|null $override Existing override record or null.
 * @param stdClass $fromform Form data.
 * @param array $keys Override field keys.
 * @param int $userid
 * @param cm_info $cm
 */
function save_assign_override($assign, $instance, $context, $override, $fromform, $keys, $userid, $cm) {
    global $DB;

    $fromform->assignid = $instance->id;

    // Replace unchanged values with null (Moodle convention: only store differences).
    foreach ($keys as $key) {
        if (!isset($fromform->{$key}) || $fromform->{$key} == $instance->{$key}) {
            $fromform->{$key} = null;
        }
    }

    // Check for existing user override (user may have changed in form — though we lock it).
    if (empty($override->id)) {
        $conditions = [
            'assignid' => $instance->id,
            'userid' => $fromform->userid ?? $userid,
        ];
        $oldoverride = $DB->get_record('assign_overrides', $conditions);
        if ($oldoverride) {
            // Merge new settings on top of old override.
            foreach ($keys as $key) {
                if (is_null($fromform->{$key})) {
                    $fromform->{$key} = $oldoverride->{$key};
                }
            }
            $assign->delete_override($oldoverride->id);
        }
    }

    $params = [
        'context' => $context,
        'other' => ['assignid' => $instance->id],
    ];

    if (!empty($override->id)) {
        // Update existing.
        $fromform->id = $override->id;
        $DB->update_record('assign_overrides', $fromform);

        $cachekey = "{$fromform->assignid}_u_{$fromform->userid}";
        cache::make('mod_assign', 'overrides')->delete($cachekey);

        $params['objectid'] = $override->id;
        $params['relateduserid'] = $fromform->userid;
        \mod_assign\event\user_override_updated::create($params)->trigger();
    } else {
        // Insert new.
        unset($fromform->id);
        $fromform->id = $DB->insert_record('assign_overrides', $fromform);

        $cachekey = "{$fromform->assignid}_u_{$fromform->userid}";
        cache::make('mod_assign', 'overrides')->delete($cachekey);

        $params['objectid'] = $fromform->id;
        $params['relateduserid'] = $fromform->userid;
        \mod_assign\event\user_override_created::create($params)->trigger();
    }

    // Recalculate penalties if requested.
    if (!empty($fromform->recalculatepenalty) && $fromform->recalculatepenalty === 'yes') {
        $assignintance = clone $assign->get_instance();
        $assignintance->cmidnumber = $cm->idnumber;
        assign_update_grades($assignintance, $fromform->userid);
    }

    // Update calendar events.
    assign_update_events($assign, $fromform);
}

/**
 * Handle quiz override form.
 *
 * @param cm_info $cm
 * @param context_module $context
 * @param stdClass $course
 * @param int $userid
 * @param int $overrideid
 */
function handle_quiz_override($cm, $context, $course, $userid, $overrideid) {
    global $CFG, $DB, $PAGE, $OUTPUT;

    require_once($CFG->dirroot . '/mod/quiz/lib.php');
    require_once($CFG->dirroot . '/mod/quiz/locallib.php');

    require_capability('mod/quiz:manageoverrides', $context);

    $quizobj = \mod_quiz\quiz_settings::create_for_cmid($cm->id);
    $quiz = $quizobj->get_quiz();
    $PAGE->set_title(get_string('override', 'local_unifiedgrader'));

    // Build override data for the form.
    $override = null;
    if ($overrideid) {
        $override = $DB->get_record('quiz_overrides', ['id' => $overrideid], '*', MUST_EXIST);
    }

    // Prepare form defaults.
    $data = $override ? clone $override : new stdClass();
    $keys = ['timeopen', 'timeclose', 'timelimit', 'attempts', 'password'];
    foreach ($keys as $key) {
        if (!isset($data->{$key})) {
            $data->{$key} = $quiz->{$key};
        }
    }
    // Pre-select user for new overrides.
    if (!$override && $userid) {
        $data->userid = $userid;
    }

    $url = new moodle_url('/local/unifiedgrader/override.php', [
        'cmid' => $cm->id,
        'userid' => $userid,
        'overrideid' => $overrideid,
    ]);

    $mform = new \mod_quiz\form\edit_override_form($url, $cm, $quiz, $context, false, $override);
    $mform->set_data($data);

    if ($mform->is_cancelled()) {
        output_postmessage('override_cancelled');
        return;
    }

    $fromform = $mform->get_data();
    if ($fromform) {
        // Include override ID for updates.
        if ($overrideid) {
            $fromform->id = $overrideid;
        }

        $manager = $quizobj->get_override_manager();
        $manager->save_override((array) $fromform);

        output_postmessage('override_saved');
        return;
    }

    echo $OUTPUT->header();
    $mform->display();
    echo $OUTPUT->footer();
}

/**
 * Output a minimal HTML page that posts a message to the parent window.
 *
 * @param string $type Message type ('override_saved' or 'override_cancelled').
 */
function output_postmessage(string $type) {
    global $OUTPUT;
    echo $OUTPUT->header();
    echo '<script>window.parent.postMessage({type: "' . $type . '"}, "*");</script>';
    echo $OUTPUT->footer();
}
