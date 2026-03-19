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
 * Unified overrides and extensions page for the unified grader.
 *
 * A single embedded page that handles overrides and extensions for all
 * activity types (assign, quiz, forum). Uses embedded layout (no chrome)
 * for use inside an iframe modal. Communicates results back via postMessage.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

global $CFG, $DB, $PAGE, $OUTPUT, $USER;

require_once($CFG->dirroot . '/mod/assign/locallib.php');

$cmid = required_param('cmid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid);
$context = context_module::instance($cm->id);

require_login($course, false, $cm);
require_capability('local/unifiedgrader:grade', $context);

$modname = $cm->modname;

$PAGE->set_pagelayout('embedded');
$PAGE->set_url(new moodle_url('/local/unifiedgrader/overrides_extensions.php', [
    'cmid' => $cmid,
    'userid' => $userid,
]));
$PAGE->set_title(get_string('overrides_extensions', 'local_unifiedgrader'));

// Gather defaults and current overrides based on activity type.
$defaults = [];
$overrides = [];

if ($modname === 'assign') {
    $assign = new assign($context, $cm, $course);
    $instance = $assign->get_instance();

    $defaults = [
        'duedate' => (int) $instance->duedate,
        'cutoffdate' => (int) $instance->cutoffdate,
        'allowsubmissionsfromdate' => (int) $instance->allowsubmissionsfromdate,
        'timelimit' => (int) ($instance->timelimit ?? 0),
    ];

    // Load existing override.
    $adapter = \local_unifiedgrader\adapter\adapter_factory::create($cmid);
    $existing = $adapter->get_user_override($userid);
    if ($existing) {
        $overrides['duedate'] = $existing['duedate'] ?? 0;
        $overrides['cutoffdate'] = $existing['cutoffdate'] ?? 0;
        $overrides['allowsubmissionsfromdate'] = $existing['allowsubmissionsfromdate'] ?? 0;
        $overrides['timelimit'] = $existing['timelimit'] ?? 0;
        $overrides['overrideid'] = $existing['id'];
    }

    // Load existing extension.
    $flags = $DB->get_record('assign_user_flags', [
        'assignment' => $instance->id,
        'userid' => $userid,
    ]);
    if ($flags && (int) $flags->extensionduedate > 0) {
        $overrides['extensionduedate'] = (int) $flags->extensionduedate;
    }

} else if ($modname === 'quiz') {
    $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
    $hasduedateplugin = class_exists('\quizaccess_duedate\override_manager');

    $defaults = [
        'timeopen' => (int) $quiz->timeopen,
        'timeclose' => (int) $quiz->timeclose,
        'timelimit' => (int) $quiz->timelimit,
        'attempts' => (int) $quiz->attempts,
        'hasduedateplugin' => $hasduedateplugin,
        'duedate' => 0,
    ];

    // Get quiz-level duedate from plugin.
    if ($hasduedateplugin) {
        $settings = $DB->get_record('quizaccess_duedate_instances', ['quizid' => $quiz->id]);
        $defaults['duedate'] = $settings ? (int) $settings->duedate : 0;
    }

    // Load existing core override.
    $adapter = \local_unifiedgrader\adapter\adapter_factory::create($cmid);
    $existing = $adapter->get_user_override($userid);
    if ($existing) {
        $overrides['timeopen'] = $existing['timeopen'] ?? 0;
        $overrides['timeclose'] = $existing['timeclose'] ?? 0;
        $overrides['timelimit'] = $existing['timelimit'] ?? 0;
        $overrides['attempts'] = $existing['attempts'];
        $overrides['overrideid'] = $existing['id'];
    }

    // Load existing duedate extension.
    if ($hasduedateplugin) {
        $ext = $adapter->get_duedate_extension($userid);
        if ($ext) {
            $overrides['extensionduedate'] = (int) $ext['duedate'];
        }
    }

} else if ($modname === 'forum') {
    $forum = $DB->get_record('forum', ['id' => $cm->instance], '*', MUST_EXIST);

    $defaults = [
        'duedate' => (int) ($forum->duedate ?? 0),
        'cutoffdate' => (int) ($forum->cutoffdate ?? 0),
    ];

    // Load existing extension.
    $adapter = \local_unifiedgrader\adapter\adapter_factory::create($cmid);
    $ext = $adapter->get_forum_extension($userid);
    if ($ext) {
        $overrides['extensionduedate'] = (int) $ext['extensionduedate'];
    }

} else {
    throw new moodle_exception('invalidmodule', 'local_unifiedgrader');
}

// Build and display the form.
$url = new moodle_url('/local/unifiedgrader/overrides_extensions.php', [
    'cmid' => $cmid,
    'userid' => $userid,
]);

$mform = new \local_unifiedgrader\form\overrides_extensions_form($url, [
    'activitytype' => $modname,
    'defaults' => $defaults,
    'overrides' => $overrides,
]);

// Pre-populate hidden fields.
$formdata = new stdClass();
$formdata->cmid = $cmid;
$formdata->userid = $userid;
$mform->set_data($formdata);

if ($mform->is_cancelled()) {
    echo $OUTPUT->header();
    echo '<script>window.parent.postMessage({type: "overrides_cancelled"}, "*");</script>';
    echo $OUTPUT->footer();
    exit;
}

$fromform = $mform->get_data();
if ($fromform) {
    if ($modname === 'assign') {
        save_assign_overrides($assign, $context, $cm, $userid, $fromform, $defaults, $overrides);
    } else if ($modname === 'quiz') {
        save_quiz_overrides($adapter, $cm, $userid, $fromform, $defaults, $overrides);
    } else if ($modname === 'forum') {
        save_forum_overrides($adapter, $cm, $userid, $fromform, $defaults, $overrides);
    }

    echo $OUTPUT->header();
    echo '<script>window.parent.postMessage({type: "overrides_saved"}, "*");</script>';
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();

// ──────────────────────────────────────────────────────────────────────────────
// Save helpers.
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Save assignment overrides and extension.
 *
 * @param assign $assign
 * @param context_module $context
 * @param cm_info $cm
 * @param int $userid
 * @param stdClass $fromform
 * @param array $defaults
 * @param array $existingoverrides
 */
function save_assign_overrides($assign, $context, $cm, $userid, $fromform, $defaults, $existingoverrides) {
    global $DB;

    $instance = $assign->get_instance();
    $extensiondate = 0;

    // Handle extension.
    if (!empty($fromform->override_extensionduedate)) {
        $extensiondate = (int) $fromform->extensionduedate;
        $assign->save_user_extension($userid, $extensiondate);
    } else {
        // Clear extension if checkbox is unchecked.
        $assign->save_user_extension($userid, 0);
    }

    // Gather override fields (no duedate — extension handles that).
    $overridefields = ['cutoffdate', 'allowsubmissionsfromdate'];
    $overridedata = new stdClass();
    $overridedata->assignid = $instance->id;
    $overridedata->userid = $userid;
    $overridedata->duedate = null; // Never override duedate — use extension instead.
    $hasanyoverride = false;

    foreach ($overridefields as $field) {
        $checkbox = 'override_' . $field;
        if (!empty($fromform->{$checkbox})) {
            $overridedata->{$field} = (int) $fromform->{$field};
            $hasanyoverride = true;
        } else {
            $overridedata->{$field} = null;
        }
    }

    // Auto-adjust cutoff: if extension > effective cutoff, set cutoff override to match.
    $effectivecutoff = $overridedata->cutoffdate ?? (int) $instance->cutoffdate;
    if ($extensiondate > 0 && $effectivecutoff > 0 && $extensiondate > $effectivecutoff) {
        $overridedata->cutoffdate = $extensiondate;
        $hasanyoverride = true;
    }

    // Handle timelimit override.
    if (!empty($fromform->override_timelimit)) {
        $overridedata->timelimit = (int) $fromform->timelimit;
        $hasanyoverride = true;
    } else {
        $overridedata->timelimit = null;
    }

    $overrideid = $existingoverrides['overrideid'] ?? 0;

    if ($hasanyoverride) {
        $params = [
            'context' => $context,
            'other' => ['assignid' => $instance->id],
        ];

        if ($overrideid) {
            $overridedata->id = $overrideid;
            $DB->update_record('assign_overrides', $overridedata);

            $params['objectid'] = $overrideid;
            $params['relateduserid'] = $userid;
            \mod_assign\event\user_override_updated::create($params)->trigger();
        } else {
            $overridedata->id = $DB->insert_record('assign_overrides', $overridedata);

            $params['objectid'] = $overridedata->id;
            $params['relateduserid'] = $userid;
            \mod_assign\event\user_override_created::create($params)->trigger();
        }

        // Clear override cache.
        $cachekey = "{$instance->id}_u_{$userid}";
        \cache::make('mod_assign', 'overrides')->delete($cachekey);

        // Update calendar events.
        assign_update_events($assign, $overridedata);
    } else if ($overrideid) {
        // All override checkboxes unchecked — delete existing override.
        $assign->delete_override($overrideid);

        $cachekey = "{$instance->id}_u_{$userid}";
        \cache::make('mod_assign', 'overrides')->delete($cachekey);
    }

    // Recalculate penalties.
    if (class_exists('\mod_assign\penalty\helper')) {
        \mod_assign\penalty\helper::apply_penalty_to_user($cm->instance, $userid);
    }
}

/**
 * Save quiz overrides and duedate extension.
 *
 * @param \local_unifiedgrader\adapter\quiz_adapter $adapter
 * @param cm_info $cm
 * @param int $userid
 * @param stdClass $fromform
 * @param array $defaults
 * @param array $existingoverrides
 */
function save_quiz_overrides($adapter, $cm, $userid, $fromform, $defaults, $existingoverrides) {
    global $DB;

    $extensiondate = 0;

    // Handle duedate extension (via quizaccess_duedate plugin).
    if (!empty($defaults['hasduedateplugin'])) {
        if (!empty($fromform->override_extensionduedate)) {
            $extensiondate = (int) $fromform->extensionduedate;
            $adapter->save_duedate_extension($userid, $extensiondate);
        } else {
            // Clear extension.
            $adapter->delete_duedate_extension($userid);
        }
    }

    // Core quiz overrides.
    $overridefields = ['timeopen', 'timeclose'];
    $overridedata = [];
    $overridedata['quiz'] = $cm->instance;
    $overridedata['userid'] = $userid;
    $hasanyoverride = false;

    foreach ($overridefields as $field) {
        $checkbox = 'override_' . $field;
        if (!empty($fromform->{$checkbox})) {
            $overridedata[$field] = (int) $fromform->{$field};
            $hasanyoverride = true;
        } else {
            $overridedata[$field] = null;
        }
    }

    // Auto-adjust timeclose: if extension > effective timeclose, set timeclose override to match.
    $effectivetimeclose = $overridedata['timeclose'] ?? (int) ($defaults['timeclose'] ?? 0);
    if ($extensiondate > 0 && $effectivetimeclose > 0 && $extensiondate > $effectivetimeclose) {
        $overridedata['timeclose'] = $extensiondate;
        $hasanyoverride = true;
    }

    // Timelimit.
    if (!empty($fromform->override_timelimit)) {
        $overridedata['timelimit'] = (int) $fromform->timelimit;
        $hasanyoverride = true;
    } else {
        $overridedata['timelimit'] = null;
    }

    // Attempts.
    if (!empty($fromform->override_attempts)) {
        $overridedata['attempts'] = (int) $fromform->attempts;
        $hasanyoverride = true;
    } else {
        $overridedata['attempts'] = null;
    }

    // Password is not exposed in this form — preserve existing value.
    $overridedata['password'] = null;

    $overrideid = $existingoverrides['overrideid'] ?? 0;

    if ($hasanyoverride) {
        if ($overrideid) {
            $overridedata['id'] = $overrideid;
        }
        $quizobj = \mod_quiz\quiz_settings::create_for_cmid($cm->id);
        $quizobj->get_override_manager()->save_override($overridedata);
    } else if ($overrideid) {
        // All checkboxes unchecked — delete existing override.
        $adapter->delete_user_override($userid);
    }
}

/**
 * Save forum extension.
 *
 * @param \local_unifiedgrader\adapter\forum_adapter $adapter
 * @param cm_info $cm
 * @param int $userid
 * @param stdClass $fromform
 * @param array $defaults
 * @param array $existingoverrides
 */
function save_forum_overrides($adapter, $cm, $userid, $fromform, $defaults, $existingoverrides) {
    if (!empty($fromform->override_extensionduedate)) {
        $adapter->save_forum_extension($userid, (int) $fromform->extensionduedate);
    } else {
        // Clear extension.
        $adapter->delete_forum_extension($userid);
    }

    // Re-sync penalties.
    $lateinfo = $adapter->calculate_late_penalty($userid);
    \local_unifiedgrader\penalty_manager::sync_late_penalty(
        $cm->id,
        $userid,
        $lateinfo['percentage'] ?? null,
        $lateinfo['dayslate'] ?? 0,
    );
    $adapter->sync_gradebook_penalty($userid);
}
