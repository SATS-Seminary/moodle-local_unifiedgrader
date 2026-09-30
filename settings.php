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
    // Container category — gives the plugin its own folder under "Local
    // plugins" so the settings page and the "Manage system defaults" tool
    // sit together as siblings instead of being scattered.
    $ADMIN->add('localplugins', new admin_category(
        'local_unifiedgrader_cat',
        get_string('pluginname', 'local_unifiedgrader'),
    ));

    $settings = new admin_settingpage(
        'local_unifiedgrader',
        get_string('settings'),
    );

    $settings->add(new admin_setting_configcheckbox(
        'local_unifiedgrader/enable_assign',
        get_string('setting_enable_assign', 'local_unifiedgrader'),
        get_string('setting_enable_assign_desc', 'local_unifiedgrader'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_unifiedgrader/enable_submission_comments',
        get_string('setting_enable_submission_comments', 'local_unifiedgrader'),
        get_string('setting_enable_submission_comments_desc', 'local_unifiedgrader'),
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
        'local_unifiedgrader/enable_bigbluebuttonbn',
        get_string('setting_enable_bigbluebuttonbn', 'local_unifiedgrader'),
        get_string('setting_enable_bigbluebuttonbn_desc', 'local_unifiedgrader'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_unifiedgrader/onlinetext_as_pdf',
        get_string('setting_onlinetext_as_pdf', 'local_unifiedgrader'),
        get_string('setting_onlinetext_as_pdf_desc', 'local_unifiedgrader'),
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_unifiedgrader/allow_manual_grade_override',
        get_string('setting_allow_manual_override', 'local_unifiedgrader'),
        get_string('setting_allow_manual_override_desc', 'local_unifiedgrader'),
        1
    ));

    // Approval mode for teacher-proposed system defaults. Default ON
    // (admins must approve). Toggling OFF promotes proposals immediately
    // and skips the queue — useful for small trusted teams.
    $settings->add(new admin_setting_configcheckbox(
        'local_unifiedgrader/require_systemdefault_approval',
        get_string('setting_require_systemdefault_approval', 'local_unifiedgrader'),
        get_string('setting_require_systemdefault_approval_desc', 'local_unifiedgrader'),
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

    // Quick extension buttons in the Dates & extensions dialogue, in days.
    $settings->add(new admin_setting_configtext(
        'local_unifiedgrader/extensionpresets',
        get_string('setting_extensionpresets', 'local_unifiedgrader'),
        get_string('setting_extensionpresets_desc', 'local_unifiedgrader'),
        '1, 2, 3, 7',
        '/^\s*[1-9][0-9]{0,2}(\s*,\s*[1-9][0-9]{0,2})*\s*$/',
        20
    ));

    // Late penalties. From Moodle 5.3 Unified Grader owns them for
    // assignments, forums and quizzes.
    if (\local_unifiedgrader\penalty\compat::unified()) {
        $settings->add(new admin_setting_heading(
            'local_unifiedgrader/latepenalties',
            get_string('setting_latepenalties_heading', 'local_unifiedgrader'),
            get_string('setting_latepenalties_heading_desc', 'local_unifiedgrader'),
        ));

        // Core still penalising assignments means two systems deduct for the
        // same thing. Unified Grader stands back for those assignments, but the
        // administrator should switch core's off.
        if (\core_grades\penalty_manager::is_penalty_enabled_for_module('assign')) {
            $settings->add(new admin_setting_heading(
                'local_unifiedgrader/coreassignpenalty',
                '',
                html_writer::div(
                    get_string('setting_core_assign_penalty_warning', 'local_unifiedgrader', (new moodle_url(
                        '/admin/settings.php',
                        ['section' => 'supportedplugins'],
                    ))->out(false)),
                    'alert alert-warning',
                ),
            ));
        }

        $settings->add(new admin_setting_configtext(
            'local_unifiedgrader/quizgenuineattemptpct',
            get_string('setting_quizgenuineattemptpct', 'local_unifiedgrader'),
            get_string('setting_quizgenuineattemptpct_desc', 'local_unifiedgrader'),
            50,
            '/^(100|[1-9]?[0-9])$/',
            5
        ));
    }

    $ADMIN->add('local_unifiedgrader_cat', $settings);

    // External page: manage system-default tags and comments. Sibling of
    // the settings page under the Unified Grader category — where admins
    // expect plugin-specific tools to live.
    $ADMIN->add('local_unifiedgrader_cat', new admin_externalpage(
        'local_unifiedgrader_systemdefaults',
        get_string('manage_system_defaults', 'local_unifiedgrader'),
        new moodle_url('/local/unifiedgrader/manage_system_defaults.php'),
        'local/unifiedgrader:managesystemdefaults',
    ));

    // External page: audit and repair teachers' comment libraries. Comment
    // library entries are scoped by a free-text course code rather than a
    // foreign key, so this is the only place an admin can see entries that
    // have drifted out of reach of their owner.
    $ADMIN->add('local_unifiedgrader_cat', new admin_externalpage(
        'local_unifiedgrader_moderatelibraries',
        get_string('clibmod_pagename', 'local_unifiedgrader'),
        new moodle_url('/local/unifiedgrader/moderate_libraries.php'),
        'local/unifiedgrader:moderatelibraries',
    ));
}
