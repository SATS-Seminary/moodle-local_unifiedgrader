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
 * AJAX endpoint to recalculate late penalties after an extension or override change.
 *
 * Called via fetch() from the grader JS when the teacher confirms penalty
 * recalculation after granting an extension on a graded assignment.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/mod/assign/lib.php');

$cmid = required_param('cmid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);

[$course, $cm] = get_course_and_cm_from_cmid($cmid, 'assign');
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('local/unifiedgrader:grade', $context);
require_sesskey();

$assign = new assign($context, $cm, $course);
$instance = clone $assign->get_instance();
$instance->cmidnumber = $cm->idnumber;

// Full gradebook recalculation. This resets the raw grade from assign_grades and
// then applies the late penalty exactly once: assign_update_grades() ends up in
// assign_grade_item_update(), which itself calls
// \mod_assign\penalty\helper::apply_penalty_to_user() for each user.
//
// Do NOT call apply_penalty_to_user() again here. gradepenalty_duedate deducts a
// FIXED number of marks (max grade × penalty%, see its penalty_calculator), and
// core has no "already penalised" guard — is_penalty_enabled_for_grade() only
// checks for minimum/overridden/locked grades. A second call therefore subtracts
// the full penalty a second time: a 35% rule took 70% off the grade.
assign_update_grades($instance, $userid);

header('Content-Type: application/json');
echo json_encode(['success' => true]);
