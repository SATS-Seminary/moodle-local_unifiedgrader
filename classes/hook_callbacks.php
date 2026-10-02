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
        $supported = ['assign', 'forum', 'quiz', 'bigbluebuttonbn'];
        if (!in_array($modname, $supported)) {
            return;
        }

        if (!get_config('local_unifiedgrader', 'enable_' . $modname)) {
            return;
        }

        // For quiz, forum, and BBB activities, only act on the overview page — not subpages.
        // Forums also act on the discussion page: a student may open a discussion without
        // passing through the list of discussions, and would then never see the feedback
        // banner. The other scripts find nothing to attach to there and do nothing.
        if ($modname === 'quiz' || $modname === 'forum' || $modname === 'bigbluebuttonbn') {
            try {
                $pagepath = $PAGE->url->get_path();
            } catch (\Throwable $e) {
                return;
            }
            $onoverview = strpos($pagepath, '/mod/' . $modname . '/view.php') !== false;
            $ondiscussion = $modname === 'forum' && strpos($pagepath, '/mod/forum/discuss.php') !== false;
            if (!$onoverview && !$ondiscussion) {
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

        // Inject submission comments widget (pre- and post-grading). News /
        // announcements forums are not graded and never form part of the
        // teacher-student feedback loop, so the chat bubble has no place
        // there — skip the widget injection in that case.
        $isnewsforum = $modname === 'forum'
            && \local_unifiedgrader\forum_helper::is_news_forum($cm);
        if (get_config('local_unifiedgrader', 'enable_submission_comments') && !$isnewsforum) {
            if ($modname === 'assign') {
                // Assignment: replace core comments with inline widget.
                $PAGE->requires->js_call_amd(
                    'local_unifiedgrader/assignment_comments',
                    'init',
                    [$cm->id, (int) $USER->id],
                );
            } else if ($modname === 'quiz' || $modname === 'forum' || $modname === 'bigbluebuttonbn') {
                // Quiz/forum/BBB: non-intrusive chat bubble in activity-information region.
                $PAGE->requires->js_call_amd(
                    'local_unifiedgrader/activity_comments',
                    'init',
                    [$cm->id, (int) $USER->id],
                );
            }
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
                'bigbluebuttonbn' => 'view_bbb_feedback',
            ];
            $bannermapping = [
                'assign' => 'view_annotated_feedback',
                'forum' => 'forum_feedback_banner',
                'quiz' => 'quiz_feedback_banner',
                'bigbluebuttonbn' => 'bbb_feedback_banner',
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
                    self::definition_for_students($definition, $context),
                    $feedbackurl->out(false),
                    get_string('assessment_criteria', 'local_unifiedgrader'),
                    false,
                ]);
            }
        }
    }

    /**
     * Make a grading definition safe and fit to show to students.
     *
     * The assessment criteria dialogue prints the rubric or marking guide
     * descriptions as HTML, so each of them is cleaned here first; an adapter that
     * passed some of it through raw would otherwise let whoever wrote the
     * rubric run script in each student's browser. A marking guide's notes
     * for markers are for markers, and are left out altogether.
     *
     * @param array $definition From base_adapter::get_grading_definition().
     * @param \context $context The activity's context.
     * @return array The definition to send to the student's browser.
     */
    public static function definition_for_students(array $definition, \context $context): array {
        $clean = fn($text) => format_text((string) $text, FORMAT_HTML, ['context' => $context]);

        $definition['description'] = $clean($definition['description'] ?? '');

        foreach (($definition['criteria'] ?? []) as $i => $criterion) {
            unset($criterion['descriptionmarkers']);
            $criterion['description'] = $clean($criterion['description'] ?? '');
            foreach (($criterion['levels'] ?? []) as $j => $level) {
                $criterion['levels'][$j]['definition'] = $clean($level['definition'] ?? '');
            }
            $definition['criteria'][$i] = $criterion;
        }

        return $definition;
    }
}
