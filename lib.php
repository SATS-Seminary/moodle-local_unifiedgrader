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
 * Library functions and navigation callbacks for local_unifiedgrader.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend the settings navigation to add a Unified Grader tab in the
 * activity secondary navigation bar.
 *
 * In Moodle 4.0+ the secondary navigation for activities is built from
 * the settings navigation tree. Nodes added as children of 'modulesettings'
 * appear as tabs (or in the "More" menu if there are already 5+ tabs).
 *
 * @param settings_navigation $settingsnav The settings navigation object.
 * @param context $context The current context.
 */
function local_unifiedgrader_extend_settings_navigation(
    settings_navigation $settingsnav,
    context $context,
): void {
    global $PAGE;

    // Only act in module context.
    if ($context->contextlevel !== CONTEXT_MODULE) {
        return;
    }

    $cm = $PAGE->cm;
    if (!$cm) {
        return;
    }

    // Only inject for supported activity types that are enabled.
    $modname = $cm->modname;
    $supported = ['assign', 'forum', 'quiz'];
    if (!in_array($modname, $supported)) {
        return;
    }

    if (!get_config('local_unifiedgrader', "enable_{$modname}")) {
        return;
    }

    // Only show to users who can grade.
    if (!has_capability('local/unifiedgrader:grade', $context)) {
        return;
    }

    // Find the module settings node in the settings navigation tree.
    $modulesettings = $settingsnav->find('modulesettings', navigation_node::TYPE_SETTING);
    if (!$modulesettings) {
        return;
    }

    // Add the Unified Grader tab.
    $url = new moodle_url('/local/unifiedgrader/grade.php', ['cmid' => $cm->id]);
    $node = navigation_node::create(
        get_string('grading_interface', 'local_unifiedgrader'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'local_unifiedgrader_grade',
        new pix_icon('i/grades', ''),
    );
    $modulesettings->add_node($node);
}
