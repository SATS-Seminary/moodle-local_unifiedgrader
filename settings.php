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
    // One entry under "Local plugins": an index page with a tile for each
    // settings page and tool. The pages themselves live in a hidden category,
    // so they keep their own addresses and breadcrumbs without Moodle listing
    // them all, or rendering every one of them on a single category page.
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_unifiedgrader_index',
        get_string('pluginname', 'local_unifiedgrader'),
        new moodle_url('/local/unifiedgrader/settings_index.php'),
    ));

    $ADMIN->add('localplugins', new admin_category(
        'local_unifiedgrader_cat',
        get_string('pluginname', 'local_unifiedgrader'),
        true,
    ));

    // The way back to the index, shown at the top of every settings page.
    $backtoindex = fn(string $page) => new admin_setting_heading(
        'local_unifiedgrader/backtoindex_' . $page,
        '',
        html_writer::link(
            new moodle_url('/local/unifiedgrader/settings_index.php'),
            '&larr; ' . get_string('settings_backtoindex', 'local_unifiedgrader'),
        ),
    );

    // The settings are grouped over a few pages by what they are for. The
    // first keeps the section name the single page had, so existing links to
    // it still land somewhere sensible.

    // General: which activities the grader takes over.
    $general = new admin_settingpage(
        'local_unifiedgrader',
        get_string('settings_page_general', 'local_unifiedgrader'),
    );
    $general->add($backtoindex('general'));

    $general->add(new admin_setting_heading(
        'local_unifiedgrader/activities_heading',
        get_string('setting_activities_heading', 'local_unifiedgrader'),
        get_string('setting_activities_heading_desc', 'local_unifiedgrader'),
    ));

    $general->add(new admin_setting_configcheckbox(
        'local_unifiedgrader/enable_assign',
        get_string('setting_enable_assign', 'local_unifiedgrader'),
        get_string('setting_enable_assign_desc', 'local_unifiedgrader'),
        1
    ));

    $general->add(new admin_setting_configcheckbox(
        'local_unifiedgrader/enable_submission_comments',
        get_string('setting_enable_submission_comments', 'local_unifiedgrader'),
        get_string('setting_enable_submission_comments_desc', 'local_unifiedgrader'),
        1
    ));

    $general->add(new admin_setting_configcheckbox(
        'local_unifiedgrader/enable_forum',
        get_string('setting_enable_forum', 'local_unifiedgrader'),
        get_string('setting_enable_forum_desc', 'local_unifiedgrader'),
        0
    ));

    $general->add(new admin_setting_configcheckbox(
        'local_unifiedgrader/enable_quiz',
        get_string('setting_enable_quiz', 'local_unifiedgrader'),
        get_string('setting_enable_quiz_desc', 'local_unifiedgrader'),
        0
    ));

    $general->add(new admin_setting_configcheckbox(
        'local_unifiedgrader/enable_bigbluebuttonbn',
        get_string('setting_enable_bigbluebuttonbn', 'local_unifiedgrader'),
        get_string('setting_enable_bigbluebuttonbn_desc', 'local_unifiedgrader'),
        0
    ));

    $ADMIN->add('local_unifiedgrader_cat', $general);

    // Grading: how work is shown and marked, posting grades, and late penalties.
    $grading = new admin_settingpage(
        'local_unifiedgrader_grading',
        get_string('settings_page_grading', 'local_unifiedgrader'),
    );
    $grading->add($backtoindex('grading'));

    $grading->add(new admin_setting_heading(
        'local_unifiedgrader/grading_heading',
        get_string('setting_grading_heading', 'local_unifiedgrader'),
        get_string('setting_grading_heading_desc', 'local_unifiedgrader'),
    ));

    $grading->add(new admin_setting_configcheckbox(
        'local_unifiedgrader/onlinetext_as_pdf',
        get_string('setting_onlinetext_as_pdf', 'local_unifiedgrader'),
        get_string('setting_onlinetext_as_pdf_desc', 'local_unifiedgrader'),
        0
    ));

    $grading->add(new admin_setting_configcheckbox(
        'local_unifiedgrader/allow_manual_grade_override',
        get_string('setting_allow_manual_override', 'local_unifiedgrader'),
        get_string('setting_allow_manual_override_desc', 'local_unifiedgrader'),
        1
    ));

    $grading->add(new admin_setting_configcheckbox(
        'local_unifiedgrader/enable_quiz_post_grades',
        get_string('setting_enable_quiz_post_grades', 'local_unifiedgrader'),
        get_string('setting_enable_quiz_post_grades_desc', 'local_unifiedgrader'),
        0
    ));

    // Quick extension buttons in the Dates & extensions dialogue, in days.
    $grading->add(new admin_setting_configtext(
        'local_unifiedgrader/extensionpresets',
        get_string('setting_extensionpresets', 'local_unifiedgrader'),
        get_string('setting_extensionpresets_desc', 'local_unifiedgrader'),
        '1, 2, 3, 7',
        '/^\s*[1-9][0-9]{0,2}(\s*,\s*[1-9][0-9]{0,2})*\s*$/',
        20
    ));

    // Late penalties. From Moodle 5.3 Unified Grader owns them for
    // assignments, forums and quizzes; before that there is nothing to set.
    if (\local_unifiedgrader\penalty\compat::unified()) {
        $grading->add(new admin_setting_heading(
            'local_unifiedgrader/latepenalties',
            get_string('setting_latepenalties_heading', 'local_unifiedgrader'),
            get_string('setting_latepenalties_heading_desc', 'local_unifiedgrader'),
        ));

        // Core still penalising assignments means two systems deduct for the
        // same thing. Unified Grader stands back for those assignments, but the
        // administrator should switch core's off.
        if (\core_grades\penalty_manager::is_penalty_enabled_for_module('assign')) {
            $grading->add(new admin_setting_heading(
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

        $grading->add(new admin_setting_configtext(
            'local_unifiedgrader/quizgenuineattemptpct',
            get_string('setting_quizgenuineattemptpct', 'local_unifiedgrader'),
            get_string('setting_quizgenuineattemptpct_desc', 'local_unifiedgrader'),
            50,
            '/^(100|[1-9]?[0-9])$/',
            5
        ));
    }

    $ADMIN->add('local_unifiedgrader_cat', $grading);

    // Comment library: its settings, and the two tools that manage it.
    $library = new admin_settingpage(
        'local_unifiedgrader_library',
        get_string('settings_page_library', 'local_unifiedgrader'),
    );
    $library->add($backtoindex('library'));

    // The tools are pages of their own; this is where they are reached from.
    $librarytools = '';
    $librarytoolpages = [
        'manage_system_defaults' => ['/local/unifiedgrader/manage_system_defaults.php', 'local/unifiedgrader:managesystemdefaults'],
        'clibmod_pagename' => ['/local/unifiedgrader/moderate_libraries.php', 'local/unifiedgrader:moderatelibraries'],
    ];
    foreach ($librarytoolpages as $toolstring => [$toolurl, $toolcapability]) {
        if (has_capability($toolcapability, context_system::instance())) {
            $librarytools .= html_writer::link(
                new moodle_url($toolurl),
                get_string($toolstring, 'local_unifiedgrader'),
                ['class' => 'btn btn-secondary me-2 mb-2'],
            );
        }
    }
    if ($librarytools !== '') {
        $library->add(new admin_setting_heading(
            'local_unifiedgrader/librarytools_heading',
            get_string('setting_librarytools_heading', 'local_unifiedgrader'),
            html_writer::div(get_string('setting_librarytools_heading_desc', 'local_unifiedgrader'), 'mb-2') . $librarytools,
        ));
    }

    $library->add(new admin_setting_heading(
        'local_unifiedgrader/librarysettings_heading',
        get_string('setting_librarysettings_heading', 'local_unifiedgrader'),
        get_string('setting_librarysettings_heading_desc', 'local_unifiedgrader'),
    ));

    // Approval mode for teacher-proposed system defaults. Default ON
    // (admins must approve). Toggling OFF promotes proposals immediately
    // and skips the queue — useful for small trusted teams.
    $library->add(new admin_setting_configcheckbox(
        'local_unifiedgrader/require_systemdefault_approval',
        get_string('setting_require_systemdefault_approval', 'local_unifiedgrader'),
        get_string('setting_require_systemdefault_approval_desc', 'local_unifiedgrader'),
        1
    ));

    $library->add(new admin_setting_configtext(
        'local_unifiedgrader/coursecode_regex',
        get_string('setting_coursecode_regex', 'local_unifiedgrader'),
        get_string('setting_coursecode_regex_desc', 'local_unifiedgrader'),
        '',
        PARAM_RAW,
        40
    ));

    $ADMIN->add('local_unifiedgrader_cat', $library);

    // Friction feedback.
    $friction = new admin_settingpage(
        'local_unifiedgrader_friction',
        get_string('setting_friction_heading', 'local_unifiedgrader'),
    );
    $friction->add($backtoindex('friction'));

    $friction->add(new admin_setting_heading(
        'local_unifiedgrader/friction_heading',
        get_string('setting_friction_heading', 'local_unifiedgrader'),
        get_string('setting_friction_heading_desc', 'local_unifiedgrader'),
    ));

    $friction->add(new admin_setting_configcheckbox(
        'local_unifiedgrader/friction_enable',
        get_string('setting_friction_enable', 'local_unifiedgrader'),
        get_string('setting_friction_enable_desc', 'local_unifiedgrader'),
        0,
    ));

    $friction->add(new admin_setting_configtext(
        'local_unifiedgrader/friction_seconds',
        get_string('setting_friction_seconds', 'local_unifiedgrader'),
        get_string('setting_friction_seconds_desc', 'local_unifiedgrader'),
        20,
        '/^\d{1,4}$/',
        4,
    ));

    $friction->add(new admin_setting_configtext(
        'local_unifiedgrader/friction_daysbeforeend',
        get_string('setting_friction_daysbeforeend', 'local_unifiedgrader'),
        get_string('setting_friction_daysbeforeend_desc', 'local_unifiedgrader'),
        14,
        '/^\d{1,3}$/',
        3,
    ));

    $friction->add(new admin_setting_configtext(
        'local_unifiedgrader/friction_daysafterpost',
        get_string('setting_friction_daysafterpost', 'local_unifiedgrader'),
        get_string('setting_friction_daysafterpost_desc', 'local_unifiedgrader'),
        21,
        '/^\d{1,3}$/',
        3,
    ));

    $friction->add(new admin_setting_configtextarea(
        'local_unifiedgrader/friction_invitation',
        get_string('setting_friction_invitation', 'local_unifiedgrader'),
        get_string('setting_friction_invitation_desc', 'local_unifiedgrader'),
        '',
    ));

    $ADMIN->add('local_unifiedgrader_cat', $friction);

    // Academic integrity: plagiarism scanning, the impropriety report form, and proctoring.
    $integrity = new admin_settingpage(
        'local_unifiedgrader_integrity',
        get_string('settings_page_integrity', 'local_unifiedgrader'),
    );
    $integrity->add($backtoindex('integrity'));

    // Plagiarism scanning is Moodle's and the plagiarism plugin's to configure;
    // the grader only shows what they return. This says whether it is on and
    // where to change it.
    $integrity->add(new admin_setting_heading(
        'local_unifiedgrader/plagiarism_heading',
        get_string('setting_plagiarism_heading', 'local_unifiedgrader'),
        get_string(
            empty($CFG->enableplagiarism) ? 'setting_plagiarism_heading_off' : 'setting_plagiarism_heading_on',
            'local_unifiedgrader',
            [
                'features' => (new moodle_url('/admin/settings.php', ['section' => 'optionalsubsystems']))->out(false),
                'plugins' => (new moodle_url('/admin/plagiarism.php'))->out(false),
            ],
        ),
    ));

    $integrity->add(new admin_setting_heading(
        'local_unifiedgrader/reportform_heading',
        get_string('setting_reportform_heading', 'local_unifiedgrader'),
        get_string('setting_reportform_heading_desc', 'local_unifiedgrader'),
    ));

    $integrity->add(new admin_setting_configcheckbox(
        'local_unifiedgrader/enable_report_form',
        get_string('setting_enable_report_form', 'local_unifiedgrader'),
        get_string('setting_enable_report_form_desc', 'local_unifiedgrader'),
        0
    ));

    $integrity->add(new admin_setting_configtext(
        'local_unifiedgrader/report_form_url',
        get_string('setting_report_form_url', 'local_unifiedgrader'),
        get_string('setting_report_form_url_desc', 'local_unifiedgrader'),
        '',
        PARAM_RAW,
        60
    ));

    // Proctoring. Every service is off unless an administrator ticks it.

    $integrity->add(new admin_setting_heading(
        'local_unifiedgrader/proctoring_heading',
        get_string('setting_proctoring_heading', 'local_unifiedgrader'),
        get_string('setting_proctoring_heading_desc', 'local_unifiedgrader'),
    ));

    $proctoringservices = [];
    foreach (\local_unifiedgrader\proctoring\service::PROVIDERS as $providerkey => $providerclass) {
        $proctoringservices[$providerkey] = $providerclass::name();
    }
    $integrity->add(new admin_setting_configmulticheckbox(
        'local_unifiedgrader/proctoring_services',
        get_string('setting_proctoring_services', 'local_unifiedgrader'),
        get_string('setting_proctoring_services_desc', 'local_unifiedgrader'),
        [],
        $proctoringservices,
    ));

    // A service's own settings are shown only once it is ticked and saved:
    // the alarm lists are long, and of no interest to a site that does not
    // use that service. Until they have been saved once, the code falls back
    // on the same defaults these settings declare.
    $proctoringticked = array_filter(explode(',', (string) get_config('local_unifiedgrader', 'proctoring_services')));

    // SMOWL: its alarm lists. The connection itself is the SMOWL block's.
    if (in_array('smowl', $proctoringticked, true)) {
        $integrity->add(new admin_setting_heading(
            'local_unifiedgrader/smowl_heading',
            get_string('setting_smowl_heading', 'local_unifiedgrader'),
            get_string('setting_smowl_heading_desc', 'local_unifiedgrader'),
        ));

        $smowlcategories = [];
        foreach (\local_unifiedgrader\proctoring\provider\smowl::CATEGORIES as $category) {
            $smowlcategories[$category] = get_string(
                'proctoring_cat_' . strtolower(str_replace('_', '', $category)),
                'local_unifiedgrader',
            );
        }

        $integrity->add(new admin_setting_configmulticheckbox(
            'local_unifiedgrader/smowl_red',
            get_string('setting_smowl_red', 'local_unifiedgrader'),
            get_string('setting_smowl_red_desc', 'local_unifiedgrader'),
            array_fill_keys(\local_unifiedgrader\proctoring\provider\smowl::DEFAULT_RED, 1),
            $smowlcategories,
        ));

        $integrity->add(new admin_setting_configmulticheckbox(
            'local_unifiedgrader/smowl_ignore',
            get_string('setting_smowl_ignore', 'local_unifiedgrader'),
            get_string('setting_smowl_ignore_desc', 'local_unifiedgrader'),
            [],
            $smowlcategories,
        ));
    }

    // ProctorLink: it reports warnings but no verdict, so the colour rule is ours.
    if (in_array('proctorlink', $proctoringticked, true)) {
        $integrity->add(new admin_setting_heading(
            'local_unifiedgrader/proctorlink_heading',
            get_string('setting_proctorlink_heading', 'local_unifiedgrader'),
            get_string('setting_proctorlink_heading_desc', 'local_unifiedgrader'),
        ));

        $proctorlinkcategories = [];
        foreach (\local_unifiedgrader\proctoring\provider\proctorlink::CATEGORIES as $category) {
            $proctorlinkcategories[$category] = get_string('proctoring_plcat_' . $category, 'local_unifiedgrader');
        }

        $integrity->add(new admin_setting_configmulticheckbox(
            'local_unifiedgrader/proctorlink_red',
            get_string('setting_proctorlink_red', 'local_unifiedgrader'),
            get_string('setting_proctorlink_red_desc', 'local_unifiedgrader'),
            array_fill_keys(\local_unifiedgrader\proctoring\provider\proctorlink::DEFAULT_RED, 1),
            $proctorlinkcategories,
        ));

        $integrity->add(new admin_setting_configmulticheckbox(
            'local_unifiedgrader/proctorlink_ignore',
            get_string('setting_proctorlink_ignore', 'local_unifiedgrader'),
            get_string('setting_proctorlink_ignore_desc', 'local_unifiedgrader'),
            [],
            $proctorlinkcategories,
        ));
    }

    // AutoProctor: it gives a trust score, and these turn it into a colour.
    if (in_array('autoproctor', $proctoringticked, true)) {
        $integrity->add(new admin_setting_heading(
            'local_unifiedgrader/autoproctor_heading',
            get_string('setting_autoproctor_heading', 'local_unifiedgrader'),
            get_string('setting_autoproctor_heading_desc', 'local_unifiedgrader'),
        ));

        $integrity->add(new admin_setting_configtext(
            'local_unifiedgrader/autoproctor_green',
            get_string('setting_autoproctor_green', 'local_unifiedgrader'),
            get_string('setting_autoproctor_green_desc', 'local_unifiedgrader'),
            80,
            '/^(100|[1-9]?\d)$/',
            3,
        ));

        $integrity->add(new admin_setting_configtext(
            'local_unifiedgrader/autoproctor_yellow',
            get_string('setting_autoproctor_yellow', 'local_unifiedgrader'),
            get_string('setting_autoproctor_yellow_desc', 'local_unifiedgrader'),
            50,
            '/^(100|[1-9]?\d)$/',
            3,
        ));
    }

    $ADMIN->add('local_unifiedgrader_cat', $integrity);

    // External page: manage system-default tags and comments. Reached from
    // the Comment library settings page.
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
