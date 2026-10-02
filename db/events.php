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
 * Event observer registrations for local_unifiedgrader.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\mod_assign\event\submission_graded',
        'callback' => '\local_unifiedgrader\observer::handle_submission_graded',
    ],
    [
        'eventname' => '\core\event\user_graded',
        'callback' => '\local_unifiedgrader\observer::handle_user_graded',
    ],
    [
        'eventname' => '\mod_assign\event\submission_created',
        'callback' => '\local_unifiedgrader\observer::handle_submission_created',
    ],
    [
        'eventname' => '\mod_assign\event\submission_updated',
        'callback' => '\local_unifiedgrader\observer::handle_submission_updated',
    ],
    [
        'eventname' => '\mod_assign\event\submission_removed',
        'callback' => '\local_unifiedgrader\observer::handle_submission_removed',
    ],
    [
        'eventname' => '\core\event\course_module_deleted',
        'callback' => '\local_unifiedgrader\observer::handle_course_module_deleted',
    ],

    // Unified late penalties (Moodle 5.3+; the callbacks do nothing before).
    [
        'eventname' => '\core\event\course_module_updated',
        'callback' => '\local_unifiedgrader\observer::handle_course_module_updated',
    ],
    [
        'eventname' => '\mod_assign\event\extension_granted',
        'callback' => '\local_unifiedgrader\observer::handle_user_duedate_changed',
    ],
    [
        'eventname' => '\mod_assign\event\user_override_created',
        'callback' => '\local_unifiedgrader\observer::handle_user_duedate_changed',
    ],
    [
        'eventname' => '\mod_assign\event\user_override_updated',
        'callback' => '\local_unifiedgrader\observer::handle_user_duedate_changed',
    ],
    [
        'eventname' => '\mod_assign\event\user_override_deleted',
        'callback' => '\local_unifiedgrader\observer::handle_user_duedate_changed',
    ],
    [
        'eventname' => '\mod_assign\event\group_override_created',
        'callback' => '\local_unifiedgrader\observer::handle_group_override_changed',
    ],
    [
        'eventname' => '\mod_assign\event\group_override_updated',
        'callback' => '\local_unifiedgrader\observer::handle_group_override_changed',
    ],
    [
        'eventname' => '\mod_assign\event\group_override_deleted',
        'callback' => '\local_unifiedgrader\observer::handle_group_override_changed',
    ],
    [
        'eventname' => '\mod_quiz\event\user_override_created',
        'callback' => '\local_unifiedgrader\observer::handle_user_duedate_changed',
    ],
    [
        'eventname' => '\mod_quiz\event\user_override_updated',
        'callback' => '\local_unifiedgrader\observer::handle_user_duedate_changed',
    ],
    [
        'eventname' => '\mod_quiz\event\user_override_deleted',
        'callback' => '\local_unifiedgrader\observer::handle_user_duedate_changed',
    ],
    [
        'eventname' => '\mod_quiz\event\group_override_created',
        'callback' => '\local_unifiedgrader\observer::handle_group_override_changed',
    ],
    [
        'eventname' => '\mod_quiz\event\group_override_updated',
        'callback' => '\local_unifiedgrader\observer::handle_group_override_changed',
    ],
    [
        'eventname' => '\mod_quiz\event\group_override_deleted',
        'callback' => '\local_unifiedgrader\observer::handle_group_override_changed',
    ],
    [
        'eventname' => '\mod_quiz\event\attempt_submitted',
        'callback' => '\local_unifiedgrader\observer::handle_attempt_submitted',
    ],
    [
        'eventname' => '\core\event\grade_item_updated',
        'callback' => '\local_unifiedgrader\observer::handle_grade_item_updated',
    ],
];
