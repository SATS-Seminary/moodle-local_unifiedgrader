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
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
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
    // Use loose comparison — database drivers may return contextlevel as string.
    if ($context->contextlevel != CONTEXT_MODULE) {
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

    $cangrade = has_capability('local/unifiedgrader:grade', $context);

    // Student feedback is handled by the PSR-14 hook (feedback_banner.js +
    // assessment_criteria.js) — no secondary nav node needed.
    // Teacher grading tab — requires the 'modulesettings' node in the settings
    // navigation tree (always present for users with editing capabilities).
    if (!$cangrade) {
        return;
    }

    $modulesettings = $settingsnav->find('modulesettings', navigation_node::TYPE_SETTING);
    if (!$modulesettings) {
        return;
    }

    // Insert right after 'modedit' (Edit settings) so the tab appears early
    // in the secondary navigation bar, before the 5-tab overflow threshold.
    $keys = $modulesettings->get_children_key_list();
    $beforekey = null;
    $i = array_search('modedit', $keys);
    if ($i !== false && array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    } else if (!empty($keys)) {
        $beforekey = $keys[0];
    }

    $url = new moodle_url('/local/unifiedgrader/grade.php', ['cmid' => $cm->id]);
    $node = navigation_node::create(
        get_string('grading_interface', 'local_unifiedgrader'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'local_unifiedgrader_grade',
        new pix_icon('i/grades', ''),
    );
    $modulesettings->add_node($node, $beforekey);

    // Modules with a custom secondary navigation class (e.g. quiz) map their
    // own nodes into the first 5 visible tab slots. Our unmapped node ends up
    // in the "More" overflow regardless of its settings-tree position. Load a
    // small JS module that promotes our tab back into the visible area.
    $PAGE->requires->js_call_amd('local_unifiedgrader/nav_promote', 'init');
}

/**
 * Serve files from the local_unifiedgrader file areas.
 *
 * Called by Moodle's pluginfile.php when a URL with component=local_unifiedgrader
 * is requested. Currently supports the 'annotatedpdf' filearea for serving
 * flattened annotated PDFs to students and teachers.
 *
 * @param stdClass $course Course object.
 * @param stdClass $cm Course module object.
 * @param context $context Context object.
 * @param string $filearea File area name.
 * @param array $args Remaining URL path parts.
 * @param bool $forcedownload Whether to force download.
 * @param array $options Additional options.
 * @return bool False if file not found.
 */
function local_unifiedgrader_pluginfile(
    $course,
    $cm,
    $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options = [],
): bool {
    global $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    $validfileareas = ['annotatedpdf', 'forumfeedback', 'quizfeedback'];
    if (!in_array($filearea, $validfileareas)) {
        return false;
    }

    require_login($course, false, $cm);

    $isteacher = has_capability('local/unifiedgrader:grade', $context);
    $isstudent = !$isteacher && has_capability('local/unifiedgrader:viewfeedback', $context);

    if (!$isteacher && !$isstudent) {
        return false;
    }

    // Forum feedback files: itemid = grade_grades.id.
    if ($filearea === 'forumfeedback') {
        global $DB;

        $itemid = (int) array_shift($args);
        $filename = array_pop($args);
        $filepath = '/' . ($args ? implode('/', $args) . '/' : '');

        // Students: verify this grade_grade belongs to them and grade is released.
        if ($isstudent) {
            $gradegrade = $DB->get_record('grade_grades', ['id' => $itemid]);
            if (!$gradegrade || (int) $gradegrade->userid !== (int) $USER->id) {
                return false;
            }
            $adapter = \local_unifiedgrader\adapter\adapter_factory::create($cm->id);
            if (!$adapter->is_grade_released((int) $USER->id)) {
                return false;
            }
        }

        $fs = get_file_storage();
        $file = $fs->get_file($context->id, 'local_unifiedgrader', $filearea, $itemid, $filepath, $filename);
        if (!$file || $file->is_directory()) {
            return false;
        }

        send_stored_file($file, 0, 0, $forcedownload, $options);
        return true;
    }

    // Quiz feedback files: itemid = local_unifiedgrader_qfb.id (per-attempt),
    // with fallback to grade_grades.id for pre-migration files.
    if ($filearea === 'quizfeedback') {
        global $DB;

        $itemid = (int) array_shift($args);
        $filename = array_pop($args);
        $filepath = '/' . ($args ? implode('/', $args) . '/' : '');

        // Students: verify the record belongs to them and grade is released.
        if ($isstudent) {
            $allowed = false;
            // Try per-attempt feedback table first.
            $qfb = $DB->get_record('local_unifiedgrader_qfb', ['id' => $itemid]);
            if ($qfb && (int) $qfb->userid === (int) $USER->id) {
                $allowed = true;
            } else {
                // Fallback: check grade_grades for pre-migration files.
                $gradegrade = $DB->get_record('grade_grades', ['id' => $itemid]);
                if ($gradegrade && (int) $gradegrade->userid === (int) $USER->id) {
                    $allowed = true;
                }
            }
            if (!$allowed) {
                return false;
            }
            $adapter = \local_unifiedgrader\adapter\adapter_factory::create($cm->id);
            if (!$adapter->is_grade_released((int) $USER->id)) {
                return false;
            }
        }

        $fs = get_file_storage();
        $file = $fs->get_file($context->id, 'local_unifiedgrader', $filearea, $itemid, $filepath, $filename);
        if (!$file || $file->is_directory()) {
            return false;
        }

        send_stored_file($file, 0, 0, $forcedownload, $options);
        return true;
    }

    // Annotated PDF files: URL args = itemid (=fileid) / userid / filename.
    $itemid = (int) array_shift($args);
    $useridpath = (int) array_shift($args);
    $filename = array_shift($args);

    // Students can only access their own annotated PDFs.
    if ($isstudent) {
        if ($useridpath !== (int) $USER->id) {
            return false;
        }
        // Verify grade is released.
        $adapter = \local_unifiedgrader\adapter\adapter_factory::create($cm->id);
        if (!$adapter->is_grade_released((int) $USER->id)) {
            return false;
        }
    }

    $fs = get_file_storage();
    $file = $fs->get_file(
        $context->id,
        'local_unifiedgrader',
        'annotatedpdf',
        $itemid,
        '/' . $useridpath . '/',
        $filename,
    );

    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
    return true;
}
