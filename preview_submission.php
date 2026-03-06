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
 * Render submission content in a full Moodle page context.
 *
 * Loaded in an iframe by the preview panel. This ensures that submission
 * plugin CSS, JS, and AMD modules load correctly (e.g. ytsubmission's
 * YouTube player and timestamped feedback interface).
 *
 * For assignments, submission plugins are rendered directly so that their
 * $PAGE->requires calls are captured and included in the page footer.
 * For other activity types, the adapter's content HTML is output within
 * the Moodle theme context for consistent styling.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');

use local_unifiedgrader\adapter\adapter_factory;
use mod_quiz\quiz_attempt;

$cmid = required_param('cmid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$attemptnum = optional_param('attempt', 0, PARAM_INT);

// Load course module and validate access.
[$course, $cm] = get_course_and_cm_from_cmid($cmid);
require_login($course, false, $cm);
$context = context_module::instance($cm->id);

// Teachers can preview any student's submission; students can only view their own.
$cangrade = has_capability('local/unifiedgrader:grade', $context);
$canviewfeedback = has_capability('local/unifiedgrader:viewfeedback', $context);
if (!$cangrade && !$canviewfeedback) {
    throw new required_capability_exception($context, 'local/unifiedgrader:grade', 'nopermissions', '');
}
if (!$cangrade && (int) $userid !== (int) $USER->id) {
    throw new moodle_exception('nopermissions', '', '', get_string('viewfeedback', 'local_unifiedgrader'));
}

// Set up a minimal embedded page (no navigation chrome).
$PAGE->set_url(new moodle_url('/local/unifiedgrader/preview_submission.php', [
    'cmid' => $cmid,
    'userid' => $userid,
]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('embedded');

echo $OUTPUT->header();

if ($cm->modname === 'assign') {
    // For assignments, render submission plugins directly in page context.
    // This allows plugins to use $PAGE->requires->js_call_amd() and CSS.
    $assign = new \assign($context, $cm, $course);
    $submission = $assign->get_user_submission($userid, false, $attemptnum >= 0 ? $attemptnum : -1);

    if ($submission) {
        foreach ($assign->get_submission_plugins() as $plugin) {
            if ($plugin->is_enabled() && $plugin->is_visible()) {
                $pluginhtml = $plugin->view($submission);
                if (!empty(trim($pluginhtml))) {
                    echo '<div class="submission-plugin mb-3">' . $pluginhtml . '</div>';
                }
            }
        }
    } else {
        echo html_writer::div(
            get_string('nosubmission', 'local_unifiedgrader'),
            'alert alert-warning'
        );
    }
} else if ($cm->modname === 'quiz') {
    // For quizzes, use Moodle's native question renderers so that all question
    // types (including audio responses, file uploads, drag-and-drop) render correctly.
    $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
    $attempts = quiz_get_user_attempts($quiz->id, $userid, 'finished');

    // Use specific attempt if requested, otherwise latest.
    $attempt = null;
    if ($attemptnum > 0) {
        foreach ($attempts as $a) {
            if ((int) $a->attempt === $attemptnum) {
                $attempt = $a;
                break;
            }
        }
    }
    if (!$attempt) {
        $attempt = $attempts ? end($attempts) : null;
    }

    if ($attempt) {
        $attemptobj = quiz_attempt::create($attempt->id);

        // Configure display options for a read-only review.
        $options = $attemptobj->get_display_options(true);
        $options->marks = \question_display_options::MARK_AND_MAX;
        $options->correctness = \question_display_options::VISIBLE;
        $options->feedback = \question_display_options::VISIBLE;
        $options->manualcomment = \question_display_options::VISIBLE;
        $options->history = \question_display_options::HIDDEN;
        $options->flags = \question_display_options::HIDDEN;
        $options->generalfeedback = \question_display_options::HIDDEN;
        $options->rightanswer = \question_display_options::HIDDEN;

        $renderer = $PAGE->get_renderer('mod_quiz');
        $slots = $attemptobj->get_slots();

        foreach ($slots as $slot) {
            echo $attemptobj->render_question($slot, true, $renderer);
        }
    } else {
        echo html_writer::div(
            get_string('nosubmission', 'local_unifiedgrader'),
            'alert alert-warning'
        );
    }
} else {
    // For forum and other activity types, use the adapter's content.
    $adapter = adapter_factory::create($cmid);
    $data = $adapter->get_submission_data($userid);

    if (!empty($data['content'])) {
        echo $data['content'];
    } else {
        echo html_writer::div(
            get_string('nosubmission', 'local_unifiedgrader'),
            'alert alert-warning'
        );
    }
}

echo $OUTPUT->footer();
