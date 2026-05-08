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
 * Unified Grader help / documentation page.
 *
 * Linked from the "?" icon in the grading interface toolbar. Renders a
 * single self-contained reference covering every adapter integration and
 * cross-cutting feature. Available to anyone logged in — there is no
 * tenant-specific data on the page.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();

$PAGE->set_url(new moodle_url('/local/unifiedgrader/help.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('help_page_title', 'local_unifiedgrader'));
$PAGE->set_heading(get_string('help_page_title', 'local_unifiedgrader'));
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('local-unifiedgrader-help-page');

$plugininfo = (object) [
    'release' => get_config('local_unifiedgrader', 'release') ?: '',
    'version' => get_config('local_unifiedgrader', 'version') ?: '',
];
// Fall back to the version.php values when the config cache is not yet
// populated (fresh install / immediately after upgrade).
if (empty($plugininfo->release)) {
    $pluginman = core_plugin_manager::instance()->get_plugin_info('local_unifiedgrader');
    if ($pluginman) {
        $plugininfo->release = $pluginman->release ?? '';
        $plugininfo->version = $pluginman->versiondisk ?? '';
    }
}

$templatedata = [
    'wwwroot' => $CFG->wwwroot,
    'pluginrelease' => $plugininfo->release,
    'pluginversion' => $plugininfo->version,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_unifiedgrader/help', $templatedata);
echo $OUTPUT->footer();
