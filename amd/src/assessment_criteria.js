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
 * Assessment criteria button override for students.
 *
 * On forum/assignment pages, overrides the "View grades" button:
 * - Pre-grading: relabels to "Assessment criteria" and opens a modal
 *   showing the rubric or marking guide criteria with placeholder scores.
 * - Post-grading: relabels to "View feedback" and redirects to the
 *   unified grader feedback view.
 *
 * @module     local_unifiedgrader/assessment_criteria
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';
import * as Templates from 'core/templates';
import {getString} from 'core/str';

/**
 * Initialise the assessment criteria button override.
 *
 * @param {object|null} gradingDefinition Serialized grading definition from the adapter.
 * @param {string} feedbackUrl URL to the unified grader feedback view.
 * @param {string} buttonLabel New label for the button.
 * @param {boolean} isGraded Whether the student has been graded.
 */
export const init = (gradingDefinition, feedbackUrl, buttonLabel, isGraded) => {
    const btn = document.querySelector('[data-grade-action="view"]');
    if (!btn) {
        return;
    }

    if (isGraded) {
        // Post-grading: hide the nav button entirely — the feedback banner provides the link.
        const listItem = btn.closest('li');
        if (listItem) {
            listItem.remove();
        } else {
            btn.remove();
        }
        return;
    }

    // Pre-grading: relabel and intercept click to open criteria modal.
    btn.textContent = buttonLabel;

    btn.addEventListener('click', (e) => {
        e.stopImmediatePropagation();
        e.preventDefault();
        showCriteriaModal(gradingDefinition);
    }, {capture: true});
};

/**
 * Show the assessment criteria modal.
 *
 * @param {object|null} definition Grading definition data.
 */
const showCriteriaModal = async(definition) => {
    const templateContext = buildTemplateContext(definition);

    const body = await Templates.renderForPromise(
        'local_unifiedgrader/assessment_criteria_modal',
        templateContext,
    );

    const title = await getString('assessment_criteria', 'local_unifiedgrader');

    const modal = await Modal.create({
        title: title,
        body: body.html,
        large: true,
        show: true,
        removeOnClose: true,
    });

    // Execute any JS from the template.
    Templates.runTemplateJS(body.js);

    return modal;
};

/**
 * Build the Mustache template context from the grading definition.
 *
 * @param {object|null} definition Grading definition.
 * @return {object} Template context.
 */
const buildTemplateContext = (definition) => {
    if (!definition || !definition.criteria) {
        return {
            isRubric: false,
            isGuide: false,
            description: '',
            criteria: [],
        };
    }

    const method = definition.method;
    const context = {
        isRubric: method === 'rubric',
        isGuide: method === 'guide',
        description: definition.description || '',
        criteria: [],
    };

    if (method === 'rubric') {
        context.criteria = definition.criteria.map((criterion) => ({
            description: criterion.description,
            levels: (criterion.levels || []).map((level) => ({
                definition: level.definition,
                scoreDisplay: '\u2013 / ' + level.score,
            })),
        }));
    } else if (method === 'guide') {
        context.criteria = definition.criteria.map((criterion) => ({
            shortname: criterion.shortname,
            description: criterion.description,
            scoreDisplay: '\u2013 / ' + criterion.maxscore,
        }));
    }

    return context;
};
