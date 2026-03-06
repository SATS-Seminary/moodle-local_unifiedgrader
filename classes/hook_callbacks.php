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
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader;

defined('MOODLE_INTERNAL') || die();

/**
 * PSR-14 hook callback implementations.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Inject grade button overrides for teachers and feedback banners for students.
     *
     * For teachers: loads a JS module that redirects the default "Grade" /
     * "Grade users" button to the Unified Grader instead of the legacy interface.
     *
     * For students: loads a JS module that creates a visible "View Annotated
     * Feedback" banner on graded assignment pages (students don't have a
     * secondary navigation bar, so this banner is how they discover the viewer).
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

        if (!$cm) {
            return;
        }

        $modname = $cm->modname;
        $supported = ['assign', 'forum', 'quiz'];
        if (!in_array($modname, $supported)) {
            return;
        }

        if (!get_config('local_unifiedgrader', 'enable_' . $modname)) {
            return;
        }

        // For quizzes, only act on the overview page — not attempt/review/summary pages.
        if ($modname === 'quiz') {
            try {
                $pagepath = $PAGE->url->get_path();
            } catch (\Throwable $e) {
                return;
            }
            if (strpos($pagepath, '/mod/quiz/view.php') === false) {
                return;
            }
        }

        $cangrade = has_capability('local/unifiedgrader:grade', $context);

        // Teacher: override the default Grade button to redirect to our grader.
        if ($cangrade) {
            $gradeurl = new \moodle_url('/local/unifiedgrader/grade.php', ['cmid' => $cm->id]);
            $PAGE->requires->js_call_amd(
                'local_unifiedgrader/grade_button_override',
                'init',
                [$gradeurl->out(false), $modname],
            );
            return;
        }

        // Student: override "View grades" button and show feedback banners.
        // Skip if already on a Unified Grader page.
        $pagepath = $PAGE->url->get_path();
        if (strpos($pagepath, '/local/unifiedgrader/') !== false) {
            return;
        }

        $canviewfeedback = has_capability('local/unifiedgrader:viewfeedback', $context);
        if (!$canviewfeedback) {
            return;
        }

        try {
            $adapter = \local_unifiedgrader\adapter\adapter_factory::create($cm->id);
        } catch (\Throwable $e) {
            return;
        }

        $feedbackurl = new \moodle_url('/local/unifiedgrader/view_feedback.php', ['cmid' => $cm->id]);
        $isgraded = $adapter->is_grade_released((int) $USER->id);

        if ($isgraded) {
            // Post-grading: override "View grades" button → "View feedback" → redirect.
            $PAGE->requires->js_call_amd('local_unifiedgrader/assessment_criteria', 'init', [
                null,
                $feedbackurl->out(false),
                get_string('view_feedback', 'local_unifiedgrader'),
                true,
            ]);

            // Also show the feedback banner for discovery.
            $labelmapping = [
                'assign' => 'view_annotated_feedback',
                'forum' => 'view_forum_feedback',
                'quiz' => 'view_quiz_feedback',
            ];
            $bannermapping = [
                'assign' => 'view_annotated_feedback',
                'forum' => 'forum_feedback_banner',
                'quiz' => 'quiz_feedback_banner',
            ];
            $labelkey = $labelmapping[$modname] ?? 'view_feedback';
            $bannertext = get_string(
                $bannermapping[$modname] ?? 'view_feedback',
                'local_unifiedgrader',
            );
            $PAGE->requires->js_call_amd('local_unifiedgrader/feedback_banner', 'init', [
                $feedbackurl->out(false),
                get_string($labelkey, 'local_unifiedgrader'),
                $bannertext,
            ]);
        } else {
            // Pre-grading: override "View grades" button → "Assessment criteria" → modal.
            $definition = $adapter->get_grading_definition();
            if ($definition) {
                $PAGE->requires->js_call_amd('local_unifiedgrader/assessment_criteria', 'init', [
                    $definition,
                    $feedbackurl->out(false),
                    get_string('assessment_criteria', 'local_unifiedgrader'),
                    false,
                ]);
            }
        }
    }
}
