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
 * Marking panel component - handles grading, feedback, comments, and notes.
 *
 * @module     local_unifiedgrader/components/marking_panel
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent} from 'core/reactive';
import Templates from 'core/templates';
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';

export default class extends BaseComponent {

    /**
     * Component creation hook.
     */
    create() {
        this.name = 'marking_panel';
        this.selectors = {
            GRADE_INPUT: '[data-action="grade-input"]',
            MAX_GRADE: '[data-region="max-grade"]',
            SIMPLE_GRADE: '[data-region="simple-grade"]',
            ADVANCED_GRADING: '[data-region="advanced-grading"]',
            FEEDBACK_INPUT: '[data-action="feedback-input"]',
            SAVE_GRADE_BTN: '[data-action="save-grade"]',
            COMMENT_LIST: '[data-region="comment-list"]',
            NO_COMMENTS: '[data-region="no-comments"]',
            NEW_COMMENT_INPUT: '[data-action="new-comment-input"]',
            SAVE_COMMENT_BTN: '[data-action="save-comment"]',
            NOTES_LIST: '[data-region="notes-list"]',
            NO_NOTES: '[data-region="no-notes"]',
            NOTE_EDITOR: '[data-region="note-editor"]',
            NOTE_INPUT: '[data-action="note-input"]',
            ADD_NOTE_BTN: '[data-action="add-note"]',
            SAVE_NOTE_BTN: '[data-action="save-note"]',
            CANCEL_NOTE_BTN: '[data-action="cancel-note"]',
            RUBRIC_SECTION: '[data-region="rubric-section"]',
            RUBRIC_TITLE: '[data-region="rubric-title"]',
            RUBRIC_TOTAL: '[data-region="rubric-total"]',
            RUBRIC_BODY: '[data-region="rubric-body"]',
        };
        this._gradingDefinition = null;
        this._rubricSelections = {};
        this._guideScores = {};
        this._guideRemarks = {};
    }

    /**
     * Register state watchers.
     *
     * @return {Array}
     */
    getWatchers() {
        return [
            {watch: 'grade:updated', handler: this._renderGrade},
            {watch: 'state.notes:updated', handler: this._renderNotes},
            {watch: 'state.commentLibrary:updated', handler: this._renderCommentLibrary},
            {watch: 'ui:updated', handler: this._updateUI},
        ];
    }

    /**
     * Called when state is first ready.
     *
     * @param {object} state Current state.
     */
    stateReady(state) {
        this._setupEventListeners();
        this._updateMaxGrade(state);

        if (state.grade) {
            this._renderGrade({state});
        }
        if (state.commentLibrary) {
            this._renderCommentLibrary({state});
        }
    }

    /**
     * Set up DOM event listeners.
     */
    _setupEventListeners() {
        // Save grade button.
        const saveBtn = this.getElement(this.selectors.SAVE_GRADE_BTN);
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this._handleSaveGrade());
        }

        // Save comment to library.
        const saveCommentBtn = this.getElement(this.selectors.SAVE_COMMENT_BTN);
        if (saveCommentBtn) {
            saveCommentBtn.addEventListener('click', () => this._handleSaveComment());
        }

        // New comment input - save on Enter.
        const commentInput = this.getElement(this.selectors.NEW_COMMENT_INPUT);
        if (commentInput) {
            commentInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this._handleSaveComment();
                }
            });
        }

        // Add note button.
        const addNoteBtn = this.getElement(this.selectors.ADD_NOTE_BTN);
        if (addNoteBtn) {
            addNoteBtn.addEventListener('click', () => this._toggleNoteEditor(true));
        }

        // Save note button.
        const saveNoteBtn = this.getElement(this.selectors.SAVE_NOTE_BTN);
        if (saveNoteBtn) {
            saveNoteBtn.addEventListener('click', () => this._handleSaveNote());
        }

        // Cancel note button.
        const cancelNoteBtn = this.getElement(this.selectors.CANCEL_NOTE_BTN);
        if (cancelNoteBtn) {
            cancelNoteBtn.addEventListener('click', () => this._toggleNoteEditor(false));
        }
    }

    /**
     * Render grade data into the form.
     *
     * @param {object} args Watcher args.
     * @param {object} args.state Current state.
     */
    _renderGrade({state}) {
        const gradeInput = this.getElement(this.selectors.GRADE_INPUT);
        const feedbackInput = this.getElement(this.selectors.FEEDBACK_INPUT);

        if (gradeInput && state.grade) {
            gradeInput.value = state.grade.grade !== null ? state.grade.grade : '';
        }

        if (feedbackInput && state.grade) {
            feedbackInput.value = state.grade.feedback || '';
        }

        // Render advanced grading (rubric / marking guide).
        this._renderAdvancedGrading(state);
    }

    /**
     * Render the notes list.
     *
     * @param {object} args Watcher args.
     * @param {object} args.state Current state.
     */
    async _renderNotes({state}) {
        const notesList = this.getElement(this.selectors.NOTES_LIST);
        const noNotes = this.getElement(this.selectors.NO_NOTES);
        if (!notesList) {
            return;
        }

        // State lists are StateMaps (extend Map), not arrays. Convert to array.
        const notes = [...state.notes.values()];

        if (notes.length === 0) {
            // Clear any existing note elements but keep the no-notes message.
            notesList.querySelectorAll('.note-item').forEach(el => el.remove());
            if (noNotes) {
                noNotes.classList.remove('d-none');
            }
            return;
        }

        if (noNotes) {
            noNotes.classList.add('d-none');
        }

        // Clear existing notes.
        notesList.querySelectorAll('.note-item').forEach(el => el.remove());

        // Render each note using the template.
        for (const note of notes) {
            const date = new Date(note.timecreated * 1000);
            const context = {
                id: note.id,
                authorname: note.authorname,
                content: note.content,
                timecreated: date.toLocaleString(),
                canmanagenotes: state.ui.canmanagenotes,
            };

            try {
                const {html} = await Templates.renderForPromise('local_unifiedgrader/note_item', context);
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;
                const noteEl = tempDiv.firstElementChild;

                // Attach delete handler.
                const deleteBtn = noteEl.querySelector('[data-action="delete-note"]');
                if (deleteBtn) {
                    deleteBtn.addEventListener('click', () => {
                        this._handleDeleteNote(parseInt(deleteBtn.dataset.noteid, 10));
                    });
                }

                notesList.appendChild(noteEl);
            } catch (error) {
                Notification.exception(error);
            }
        }
    }

    /**
     * Render the comment library.
     *
     * @param {object} args Watcher args.
     * @param {object} args.state Current state.
     */
    _renderCommentLibrary({state}) {
        const commentList = this.getElement(this.selectors.COMMENT_LIST);
        const noComments = this.getElement(this.selectors.NO_COMMENTS);
        if (!commentList) {
            return;
        }

        // State lists are StateMaps (extend Map), not arrays. Convert to array.
        const comments = [...state.commentLibrary.values()];

        // Clear existing comment items (but not the no-comments message).
        commentList.querySelectorAll('.comment-item').forEach(el => el.remove());

        if (comments.length === 0) {
            if (noComments) {
                noComments.classList.remove('d-none');
            }
            return;
        }

        if (noComments) {
            noComments.classList.add('d-none');
        }

        comments.forEach((comment) => {
            const item = document.createElement('div');
            item.className = 'comment-item list-group-item list-group-item-action d-flex justify-content-between p-2';
            item.style.cursor = 'pointer';

            const textSpan = document.createElement('span');
            textSpan.className = 'small flex-grow-1';
            textSpan.textContent = comment.content.length > 80
                ? comment.content.substring(0, 80) + '...'
                : comment.content;

            item.appendChild(textSpan);

            // Click to insert into feedback.
            item.addEventListener('click', () => {
                const feedbackInput = this.getElement(this.selectors.FEEDBACK_INPUT);
                if (feedbackInput) {
                    const currentVal = feedbackInput.value;
                    feedbackInput.value = currentVal
                        ? currentVal + '\n' + comment.content
                        : comment.content;
                }
            });

            commentList.appendChild(item);
        });
    }

    /**
     * Update UI elements based on state changes.
     *
     * @param {object} args Watcher args.
     * @param {object} args.state Current state.
     */
    async _updateUI({state}) {
        const saveBtn = this.getElement(this.selectors.SAVE_GRADE_BTN);
        if (saveBtn) {
            if (state.ui.saving) {
                saveBtn.disabled = true;
                saveBtn.textContent = await getString('saving', 'local_unifiedgrader');
            } else {
                saveBtn.disabled = false;
                saveBtn.textContent = await getString('savegrade', 'local_unifiedgrader');
            }
        }
    }

    /**
     * Set the max grade display.
     *
     * @param {object} state Current state.
     */
    _updateMaxGrade(state) {
        const maxGradeEl = this.getElement(this.selectors.MAX_GRADE);
        if (maxGradeEl) {
            const maxgrade = state.ui.maxgrade || state.activity?.maxgrade || 100;
            maxGradeEl.textContent = '/ ' + maxgrade;
        }

        const gradeInput = this.getElement(this.selectors.GRADE_INPUT);
        if (gradeInput) {
            gradeInput.max = state.ui.maxgrade || state.activity?.maxgrade || 100;
        }
    }

    /**
     * Render the rubric or marking guide section.
     *
     * @param {object} state Current state.
     */
    _renderAdvancedGrading(state) {
        const section = this.getElement(this.selectors.RUBRIC_SECTION);
        if (!section) {
            return;
        }

        // Parse the grading definition.
        let definition = null;
        if (state.grade?.gradingdefinition) {
            try {
                definition = JSON.parse(state.grade.gradingdefinition);
            } catch {
                // Ignore parse errors.
            }
        }

        if (!definition || !definition.criteria || definition.criteria.length === 0) {
            section.classList.add('d-none');
            this._gradingDefinition = null;
            return;
        }

        this._gradingDefinition = definition;

        // Parse existing fill data.
        let fillData = null;
        if (state.grade?.rubricdata) {
            try {
                fillData = JSON.parse(state.grade.rubricdata);
            } catch {
                // Ignore parse errors.
            }
        }

        // Set title.
        const titleEl = this.getElement(this.selectors.RUBRIC_TITLE);
        if (titleEl) {
            titleEl.textContent = definition.method === 'rubric' ? 'Rubric' : 'Marking guide';
        }

        // Render based on method.
        if (definition.method === 'rubric') {
            this._renderRubric(definition, fillData);
        } else if (definition.method === 'guide') {
            this._renderGuide(definition, fillData);
        }

        section.classList.remove('d-none');
    }

    /**
     * Render a rubric with selectable levels.
     *
     * @param {object} definition Grading definition.
     * @param {object} fillData Current fill data.
     */
    _renderRubric(definition, fillData) {
        const body = this.getElement(this.selectors.RUBRIC_BODY);
        if (!body) {
            return;
        }
        body.innerHTML = '';
        this._rubricSelections = {};

        // Build a map of current selections from fill data.
        const currentFill = {};
        if (fillData?.criteria) {
            for (const [critId, critData] of Object.entries(fillData.criteria)) {
                if (critData.levelid) {
                    currentFill[critId] = parseInt(critData.levelid, 10);
                }
            }
        }

        definition.criteria.forEach((criterion) => {
            const row = document.createElement('div');
            row.className = 'border-bottom p-3';

            // Criterion description.
            const desc = document.createElement('div');
            desc.className = 'fw-bold small mb-2';
            desc.textContent = criterion.description;
            row.appendChild(desc);

            // Levels as selectable buttons.
            const levelContainer = document.createElement('div');
            levelContainer.className = 'd-flex flex-wrap gap-1';

            criterion.levels.forEach((level) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.dataset.criterionid = criterion.id;
                btn.dataset.levelid = level.id;
                btn.dataset.score = level.score;

                const isSelected = currentFill[criterion.id] === level.id;
                btn.className = 'btn btn-sm text-start p-2 border '
                    + (isSelected ? 'btn-primary' : 'btn-outline-secondary');

                if (isSelected) {
                    this._rubricSelections[criterion.id] = {levelid: level.id, score: level.score};
                }

                const scoreSpan = document.createElement('div');
                scoreSpan.className = 'fw-bold small';
                scoreSpan.textContent = level.score + ' pts';

                const defSpan = document.createElement('div');
                defSpan.className = 'small';
                defSpan.style.fontSize = '0.75rem';
                defSpan.textContent = level.definition;

                btn.appendChild(scoreSpan);
                btn.appendChild(defSpan);

                btn.addEventListener('click', () => {
                    this._selectRubricLevel(criterion.id, level.id, level.score, levelContainer);
                });

                levelContainer.appendChild(btn);
            });

            row.appendChild(levelContainer);
            body.appendChild(row);
        });

        this._updateRubricTotal();
    }

    /**
     * Handle clicking a rubric level.
     *
     * @param {number} criterionId Criterion ID.
     * @param {number} levelId Level ID.
     * @param {number} score Level score.
     * @param {HTMLElement} container The level button container.
     */
    _selectRubricLevel(criterionId, levelId, score, container) {
        this._rubricSelections[criterionId] = {levelid: levelId, score};

        // Update button styles in this criterion.
        container.querySelectorAll('button').forEach((btn) => {
            const isActive = parseInt(btn.dataset.levelid, 10) === levelId;
            btn.className = 'btn btn-sm text-start p-2 border '
                + (isActive ? 'btn-primary' : 'btn-outline-secondary');
        });

        this._updateRubricTotal();
    }

    /**
     * Update the rubric total score display.
     */
    _updateRubricTotal() {
        const totalEl = this.getElement(this.selectors.RUBRIC_TOTAL);

        let total = 0;
        let allSelected = true;
        const criteriaCount = this._gradingDefinition?.criteria?.length || 0;

        for (const sel of Object.values(this._rubricSelections)) {
            total += sel.score;
        }

        if (Object.keys(this._rubricSelections).length < criteriaCount) {
            allSelected = false;
        }

        if (totalEl) {
            totalEl.textContent = allSelected
                ? total + ' pts'
                : total + ' pts (incomplete)';
        }

        // Sync total into the simple grade input.
        const gradeInput = this.getElement(this.selectors.GRADE_INPUT);
        if (gradeInput) {
            gradeInput.value = total;
        }
    }

    /**
     * Render a marking guide with score inputs and remarks.
     *
     * @param {object} definition Grading definition.
     * @param {object} fillData Current fill data.
     */
    _renderGuide(definition, fillData) {
        const body = this.getElement(this.selectors.RUBRIC_BODY);
        if (!body) {
            return;
        }
        body.innerHTML = '';
        this._guideScores = {};
        this._guideRemarks = {};

        // Build fill map.
        const currentFill = {};
        if (fillData?.criteria) {
            for (const [critId, critData] of Object.entries(fillData.criteria)) {
                currentFill[critId] = {
                    score: critData.score ?? '',
                    remark: critData.remark ?? '',
                };
            }
        }

        definition.criteria.forEach((criterion) => {
            const row = document.createElement('div');
            row.className = 'border-bottom p-3';

            // Criterion header: shortname + max score.
            const header = document.createElement('div');
            header.className = 'd-flex justify-content-between align-items-start mb-1';

            const nameEl = document.createElement('div');
            nameEl.className = 'fw-bold small';
            nameEl.textContent = criterion.shortname;

            const maxEl = document.createElement('span');
            maxEl.className = 'badge bg-secondary';
            maxEl.textContent = 'Max: ' + criterion.maxscore;

            header.appendChild(nameEl);
            header.appendChild(maxEl);
            row.appendChild(header);

            // Description for markers.
            if (criterion.descriptionmarkers) {
                const markerDesc = document.createElement('div');
                markerDesc.className = 'small text-muted mb-2';
                markerDesc.textContent = criterion.descriptionmarkers;
                row.appendChild(markerDesc);
            }

            // Score input + remark row.
            const controls = document.createElement('div');
            controls.className = 'd-flex gap-2 align-items-start';

            const scoreInput = document.createElement('input');
            scoreInput.type = 'number';
            scoreInput.className = 'form-control form-control-sm';
            scoreInput.style.width = '80px';
            scoreInput.min = '0';
            scoreInput.max = String(criterion.maxscore);
            scoreInput.step = 'any';
            scoreInput.placeholder = 'Score';
            scoreInput.value = currentFill[criterion.id]?.score ?? '';
            scoreInput.dataset.criterionid = criterion.id;

            this._guideScores[criterion.id] = scoreInput.value;

            scoreInput.addEventListener('input', () => {
                this._guideScores[criterion.id] = scoreInput.value;
                this._updateGuideTotal();
            });

            const remarkInput = document.createElement('input');
            remarkInput.type = 'text';
            remarkInput.className = 'form-control form-control-sm flex-grow-1';
            remarkInput.placeholder = 'Remark';
            remarkInput.value = currentFill[criterion.id]?.remark ?? '';
            remarkInput.dataset.criterionid = criterion.id;

            this._guideRemarks[criterion.id] = remarkInput.value;

            remarkInput.addEventListener('input', () => {
                this._guideRemarks[criterion.id] = remarkInput.value;
            });

            controls.appendChild(scoreInput);
            controls.appendChild(remarkInput);
            row.appendChild(controls);
            body.appendChild(row);
        });

        this._updateGuideTotal();
    }

    /**
     * Update the marking guide total score display.
     */
    _updateGuideTotal() {
        const totalEl = this.getElement(this.selectors.RUBRIC_TOTAL);

        let total = 0;
        for (const val of Object.values(this._guideScores)) {
            const num = parseFloat(val);
            if (!isNaN(num)) {
                total += num;
            }
        }

        const maxTotal = this._gradingDefinition?.criteria?.reduce(
            (sum, c) => sum + (c.maxscore || 0), 0
        ) || 0;

        if (totalEl) {
            totalEl.textContent = total + ' / ' + maxTotal;
        }

        // Sync total into the simple grade input.
        const gradeInput = this.getElement(this.selectors.GRADE_INPUT);
        if (gradeInput) {
            gradeInput.value = total;
        }
    }

    /**
     * Collect advanced grading data for saving.
     *
     * @return {string} JSON string of advanced grading data, or empty string.
     */
    _collectAdvancedGradingData() {
        if (!this._gradingDefinition) {
            return '';
        }

        const method = this._gradingDefinition.method;

        if (method === 'rubric') {
            // Build the criteria data in the format Moodle expects.
            const criteria = {};
            for (const [critId, sel] of Object.entries(this._rubricSelections)) {
                criteria[critId] = {
                    levelid: sel.levelid,
                    remark: '',
                };
            }
            return JSON.stringify({criteria});
        }

        if (method === 'guide') {
            const criteria = {};
            for (const criterion of this._gradingDefinition.criteria) {
                const id = criterion.id;
                criteria[id] = {
                    score: this._guideScores[id] || '',
                    remark: this._guideRemarks[id] || '',
                };
            }
            return JSON.stringify({criteria});
        }

        return '';
    }

    /**
     * Handle save grade action.
     */
    _handleSaveGrade() {
        const state = this.reactive.state;
        const gradeInput = this.getElement(this.selectors.GRADE_INPUT);
        const feedbackInput = this.getElement(this.selectors.FEEDBACK_INPUT);

        const grade = gradeInput ? gradeInput.value : '';
        const feedback = feedbackInput ? feedbackInput.value : '';
        const advancedGradingData = this._collectAdvancedGradingData();

        this.reactive.dispatch(
            'saveGrade',
            state.activity.cmid,
            state.currentUser.id,
            grade,
            feedback,
            advancedGradingData,
        );
    }

    /**
     * Handle save comment to library.
     */
    _handleSaveComment() {
        const state = this.reactive.state;
        const input = this.getElement(this.selectors.NEW_COMMENT_INPUT);
        if (!input || !input.value.trim()) {
            return;
        }

        this.reactive.dispatch('saveCommentToLibrary', state.activity.courseid, input.value.trim());
        input.value = '';
    }

    /**
     * Toggle the note editor visibility.
     *
     * @param {boolean} show Whether to show.
     */
    _toggleNoteEditor(show) {
        const editor = this.getElement(this.selectors.NOTE_EDITOR);
        const input = this.getElement(this.selectors.NOTE_INPUT);
        if (editor) {
            editor.classList.toggle('d-none', !show);
        }
        if (input && show) {
            input.value = '';
            input.focus();
        }
    }

    /**
     * Handle save note action.
     */
    _handleSaveNote() {
        const state = this.reactive.state;
        const input = this.getElement(this.selectors.NOTE_INPUT);
        if (!input || !input.value.trim()) {
            return;
        }

        this.reactive.dispatch('saveNote', state.activity.cmid, state.currentUser.id, input.value.trim(), 0);
        this._toggleNoteEditor(false);
    }

    /**
     * Handle delete note action.
     *
     * @param {number} noteid Note ID to delete.
     */
    async _handleDeleteNote(noteid) {
        const confirmMsg = await getString('confirmdelete_note', 'local_unifiedgrader');
        if (!window.confirm(confirmMsg)) {
            return;
        }

        const state = this.reactive.state;
        this.reactive.dispatch('deleteNote', state.activity.cmid, state.currentUser.id, noteid);
    }
}
