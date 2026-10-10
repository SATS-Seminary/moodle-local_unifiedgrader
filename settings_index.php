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
 * The Unified Grader's settings index: a tile for each settings page.
 *
 * Moodle would otherwise render every settings page of the plugin's category
 * one after another on a single long page.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_unifiedgrader_index');

// Each tile is a settings page in the admin tree. One the user may not open is left out.
$pages = [
    'local_unifiedgrader' => ['general', 'fa-sliders'],
    'local_unifiedgrader_grading' => ['grading', 'fa-check-square-o'],
    'local_unifiedgrader_library' => ['library', 'fa-comments-o'],
    'local_unifiedgrader_friction' => ['friction', 'fa-hourglass-half'],
    'local_unifiedgrader_integrity' => ['integrity', 'fa-shield'],
];
$tiles = [];
foreach ($pages as $section => [$key, $icon]) {
    $page = admin_get_root()->locate($section);
    if (!$page || !$page->check_access()) {
        continue;
    }
    $tiles[] = [
        'key' => $key,
        'title' => $page->visiblename,
        'description' => get_string('settings_tile_' . $key, 'local_unifiedgrader'),
        'icon' => $icon,
        'url' => (new moodle_url('/admin/settings.php', ['section' => $section]))->out(false),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_unifiedgrader'));
echo $OUTPUT->render_from_template('local_unifiedgrader/settings_index', ['tiles' => $tiles]);
echo $OUTPUT->footer();
