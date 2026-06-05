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
 * Custom Behat step definitions for the Unified Grader.
 *
 * Kept deliberately small. Where Moodle already ships a step (navigation,
 * forms, data generators, JS waits) we use it directly from feature files.
 * The steps here cover the handful of plugin-specific affordances —
 * mainly "open the grader for this cmid" and "wait for the marking panel
 * to settle" — that don't exist in core Behat.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Unified Grader steps.
 */
class behat_local_unifiedgrader extends behat_base {
    /**
     * Open the Unified Grader for the activity with the given name in the
     * current course. Resolves the cmid by name lookup so feature files
     * don't have to chase numeric IDs across scenarios.
     *
     * Example:
     *   Given I am on the Unified Grader for activity "Essay 1"
     *
     * @Given /^I am on the Unified Grader for activity "(?P<activityname>(?:[^"]|\\")*)"$/
     * @param string $activityname
     */
    public function i_am_on_the_unified_grader_for_activity(string $activityname): void {
        global $DB;
        $activityname = $this->unescape_argument($activityname);
        $cm = $DB->get_record_sql(
            "SELECT cm.id
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
              WHERE (
                   (m.name = 'assign' AND cm.instance IN (SELECT id FROM {assign} WHERE name = :n1))
                OR (m.name = 'forum'  AND cm.instance IN (SELECT id FROM {forum}  WHERE name = :n2))
                OR (m.name = 'quiz'   AND cm.instance IN (SELECT id FROM {quiz}   WHERE name = :n3))
              )",
            ['n1' => $activityname, 'n2' => $activityname, 'n3' => $activityname],
        );
        if (!$cm) {
            throw new Exception("No activity named '{$activityname}' found");
        }
        $url = new moodle_url('/local/unifiedgrader/grade.php', ['cmid' => $cm->id]);
        $this->execute('behat_general::i_visit', [$url]);
    }

    /**
     * Wait for the marking panel to finish its initial render. The panel
     * is reactive and hydrates after a few AJAX calls, so a brittle
     * "wait for a fixed selector to appear" is more reliable than a
     * blanket sleep.
     *
     * @Given /^the marking panel has loaded$/
     */
    public function the_marking_panel_has_loaded(): void {
        $this->execute('behat_general::wait_until_the_page_is_ready');
        // RUBRIC_BODY data-region exists in the DOM once _renderAdvancedGrading
        // has run — the latest render boundary.
        $this->execute(
            'behat_general::wait_until_exists',
            ['[data-region="rubric-body"], [data-action="grade-input"]', 'css_element'],
        );
    }

    /**
     * Type a value into the top-level grade input and trigger the focus-out
     * autosave by clicking elsewhere. Mirrors what a teacher actually does
     * so the override / dirty / reset code paths fire naturally.
     *
     * Example:
     *   When I enter "18" as the overall grade
     *
     * @When /^I enter "(?P<value>(?:[^"]|\\")*)" as the overall grade$/
     * @param string $value
     */
    public function i_enter_as_the_overall_grade(string $value): void {
        $value = $this->unescape_argument($value);
        $node = $this->find('css', '[data-action="grade-input"]');
        $node->setValue($value);
        // Force a focusout — most reliable cross-browser way is to focus
        // a different element. The save button is always present.
        $this->execute('behat_general::i_click_on', [
            '[data-region="marking-content"]', 'css_element',
        ]);
    }

    /**
     * Set the value of a marking-guide criterion score input by its
     * visible criterion shortname / heading. Useful for "fill the rubric
     * with some scores" steps without hardcoding criterion IDs.
     *
     * Example:
     *   When I set the rubric score for "Argumentation" to "3.5"
     *
     * @When /^I set the rubric score for "(?P<criterion>(?:[^"]|\\")*)" to "(?P<score>[^"]+)"$/
     * @param string $criterion
     * @param string $score
     */
    public function i_set_the_rubric_score_for(string $criterion, string $score): void {
        $criterion = $this->unescape_argument($criterion);
        // The criterion header is .fw-bold sibling to the score input.
        // Find the row containing the heading text, then the input within.
        $xpath = "//div[contains(@class,'border-bottom')"
            . " and .//div[contains(@class,'fw-bold') and normalize-space(text())="
            . behat_context_helper::escape($criterion)
            . "]]"
            . "//input[@data-criterionid and not(@data-levelid)]";
        $input = $this->find('xpath', $xpath);
        $input->setValue($score);
    }

    /**
     * Assert the *active annotation layer* reports the given tool. Reads the
     * `data-current-tool` attribute stamped on the canvas wrapper by
     * AnnotationLayer._notifyToolChange — which only fires when the layer
     * actually accepted the tool change, distinct from the toolbar button's
     * .active class which can drift away from the layer's state when a
     * propagation race silently no-ops the dispatch (the exact regression
     * v2.5.1 + v2.5.2 chased).
     *
     * The "active" layer is the page slot whose annotation wrapper is the
     * most recent one to receive a tool stamp. We pick the last wrapper in
     * document order that has the attribute set — matches the toolbar's
     * current binding after a normal scroll/zoom sequence.
     *
     * Example:
     *   Then the active annotation layer should report tool "pen"
     *
     * @Then /^the active annotation layer should report tool "(?P<tool>[a-z]+)"$/
     * @param string $tool Expected tool key (e.g. pen, highlight, texthighlight).
     */
    public function the_active_annotation_layer_should_report_tool(string $tool): void {
        $tool = $this->unescape_argument($tool);
        // Wait for the propagation tick to settle before reading.
        $this->execute('behat_general::wait_until_the_page_is_ready');
        // Any wrapper carrying the attribute will do — propagation keeps
        // every page in sync, so picking the first is sufficient.
        $node = $this->find('css', '[data-current-tool="' . $tool . '"]');
        if (!$node) {
            throw new Exception(
                "Expected an annotation layer reporting tool '{$tool}'; none found"
            );
        }
    }
}
