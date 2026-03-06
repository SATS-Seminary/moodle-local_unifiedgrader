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
 * Admin settings for local_unifiedgrader.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_unifiedgrader',
        get_string('pluginname', 'local_unifiedgrader')
    );

    $settings->add(new admin_setting_configcheckbox(
        'local_unifiedgrader/enable_assign',
        get_string('setting_enable_assign', 'local_unifiedgrader'),
        get_string('setting_enable_assign_desc', 'local_unifiedgrader'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_unifiedgrader/enable_forum',
        get_string('setting_enable_forum', 'local_unifiedgrader'),
        get_string('setting_enable_forum_desc', 'local_unifiedgrader'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_unifiedgrader/enable_quiz',
        get_string('setting_enable_quiz', 'local_unifiedgrader'),
        get_string('setting_enable_quiz_desc', 'local_unifiedgrader'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_unifiedgrader/enable_quiz_post_grades',
        get_string('setting_enable_quiz_post_grades', 'local_unifiedgrader'),
        get_string('setting_enable_quiz_post_grades_desc', 'local_unifiedgrader'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_unifiedgrader/allow_manual_grade_override',
        get_string('setting_allow_manual_override', 'local_unifiedgrader'),
        get_string('setting_allow_manual_override_desc', 'local_unifiedgrader'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'local_unifiedgrader/coursecode_regex',
        get_string('setting_coursecode_regex', 'local_unifiedgrader'),
        get_string('setting_coursecode_regex_desc', 'local_unifiedgrader'),
        '',
        PARAM_RAW,
        40
    ));

    // Academic impropriety report form.
    $settings->add(new admin_setting_configcheckbox(
        'local_unifiedgrader/enable_report_form',
        get_string('setting_enable_report_form', 'local_unifiedgrader'),
        get_string('setting_enable_report_form_desc', 'local_unifiedgrader'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_unifiedgrader/report_form_url',
        get_string('setting_report_form_url', 'local_unifiedgrader'),
        get_string('setting_report_form_url_desc', 'local_unifiedgrader'),
        '',
        PARAM_RAW,
        60
    ));

    $ADMIN->add('localplugins', $settings);
}
