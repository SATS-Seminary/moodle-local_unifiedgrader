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
 * PSR-14 hook callback implementations for local_unifiedgrader.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader;

defined('MOODLE_INTERNAL') || die();

class hook_callbacks {

    /**
     * Inject a visible "View Annotated Feedback" banner for students on
     * graded assignment pages.
     *
     * Uses a PSR-14 output hook to load a JS module that creates a Bootstrap
     * card at the top of the main content area. Students don't have a secondary
     * navigation bar on the assignment view, so this banner is the primary way
     * they discover the annotated feedback viewer.
     *
     * NOTE: Do NOT use isset($PAGE->context) — moodle_page lacks __isset(),
     * so isset() always returns false for magic properties. Use try/catch.
     *
     * @param \core\hook\output\before_standard_top_of_body_html_generation $hook
     */
    public static function before_standard_top_of_body_html(
        \core\hook\output\before_standard_top_of_body_html_generation $hook,
    ): void {
        global $PAGE, $USER;

        // Access context via __get() with try/catch.
        try {
            $context = $PAGE->context;
        } catch (\Throwable $e) {
            return;
        }

        // Only act on module pages (loose comparison — contextlevel may be string).
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        try {
            $cm = $PAGE->cm;
        } catch (\Throwable $e) {
            return;
        }

        if (!$cm || $cm->modname !== 'assign') {
            return;
        }

        if (!get_config('local_unifiedgrader', 'enable_assign')) {
            return;
        }

        $cangrade = has_capability('local/unifiedgrader:grade', $context);
        $canviewfeedback = has_capability('local/unifiedgrader:viewfeedback', $context);

        if ($cangrade || !$canviewfeedback) {
            return;
        }

        try {
            $adapter = \local_unifiedgrader\adapter\adapter_factory::create($cm->id);
            if (!$adapter->is_grade_released((int) $USER->id)) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        $url = new \moodle_url('/local/unifiedgrader/view_feedback.php', ['cmid' => $cm->id]);
        $PAGE->requires->js_call_amd('local_unifiedgrader/feedback_banner', 'init', [
            $url->out(false),
            get_string('view_annotated_feedback', 'local_unifiedgrader'),
        ]);
    }
}
