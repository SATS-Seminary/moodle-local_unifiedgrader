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
import {getInstanceForElementId} from 'editor_tiny/editor';

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
            PLAGIARISM_SECTION: '[data-region="plagiarism-section"]',
            PLAGIARISM_BODY: '[data-region="plagiarism-body"]',
            FEEDBACK_DISPLAY: '[data-region="feedback-display"]',
            FEEDBACK_DISPLAY_CONTENT: '[data-region="feedback-display-content"]',
            FEEDBACK_EDITOR_WRAPPER: '[data-region="feedback-editor-wrapper"]',
            EDIT_FEEDBACK_BTN: '[data-action="edit-feedback"]',
            DELETE_FEEDBACK_BTN: '[data-action="delete-feedback"]',
            SAVE_FEEDBACK_FILES_BTN: '[data-action="save-feedback-files"]',
            LATE_INDICATOR: '[data-region="late-indicator"]',
            LATE_TEXT: '[data-region="late-text"]',
        };
        this._editingFeedback = false;
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
            {watch: 'submission:updated', handler: this._renderLateIndicator},
            {watch: 'submission:updated', handler: this._renderPlagiarism},
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

        // Edit feedback button.
        const editFeedbackBtn = this.getElement(this.selectors.EDIT_FEEDBACK_BTN);
        if (editFeedbackBtn) {
            editFeedbackBtn.addEventListener('click', () => this._handleEditFeedback());
        }

        // Delete feedback button.
        const deleteFeedbackBtn = this.getElement(this.selectors.DELETE_FEEDBACK_BTN);
        if (deleteFeedbackBtn) {
            deleteFeedbackBtn.addEventListener('click', () => this._handleDeleteFeedback());
        }

        // Save feedback files button.
        const saveFeedbackFilesBtn = this.getElement(this.selectors.SAVE_FEEDBACK_FILES_BTN);
        if (saveFeedbackFilesBtn) {
            saveFeedbackFilesBtn.addEventListener('click', () => this._handleSaveFeedbackFiles());
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
     * Get the current feedback content from TinyMCE or the textarea fallback.
     *
     * @return {string} The feedback HTML content.
     */
    _getEditorContent() {
        const textarea = this.getElement(this.selectors.FEEDBACK_INPUT);
        if (!textarea) {
            return '';
        }
        const editor = getInstanceForElementId(textarea.id);
        if (editor) {
            return editor.getContent();
        }
        return textarea.value;
    }

    /**
     * Set the feedback content in TinyMCE or the textarea fallback.
     *
     * @param {string} html The HTML content to set.
     */
    _updateFeedbackContent(html) {
        const textarea = this.getElement(this.selectors.FEEDBACK_INPUT);
        if (!textarea) {
            return;
        }
        const editor = getInstanceForElementId(textarea.id);
        if (editor) {
            editor.setContent(html || '');
        } else {
            textarea.value = html || '';
        }
    }

    /**
     * Render grade data into the form.
     *
     * @param {object} args Watcher args.
     * @param {object} args.state Current state.
     */
    _renderGrade({state}) {
        // Render advanced grading first — _renderRubric/_renderGuide call
        // _updateRubricTotal/_updateGuideTotal which sync a computed total
        // into the grade input. We then overwrite with the server-authoritative
        // grade value so manual overrides are not lost.
        this._renderAdvancedGrading(state);

        const gradeInput = this.getElement(this.selectors.GRADE_INPUT);
        if (gradeInput && state.grade) {
            gradeInput.value = state.grade.grade !== null ? state.grade.grade : '';

            // When advanced grading is active and manual override is not allowed,
            // make the grade input readonly so teachers must use the rubric/guide.
            const hasAdvancedGrading = this._gradingDefinition !== null;
            const allowOverride = state.ui?.allowmanualgradeoverride !== false;
            gradeInput.readOnly = hasAdvancedGrading && !allowOverride;
        }

        // Use draft-ready content (with rewritten file URLs) when available.
        if (state.grade && state.grade.feedbackdraft !== undefined) {
            this._updateFeedbackContent(state.grade.feedbackdraft);
        } else if (state.grade) {
            this._updateFeedbackContent(state.grade.feedback || '');
        }

        // Toggle feedback display/edit mode.
        // Reset editing flag — _renderGrade fires on student switch and after save.
        this._editingFeedback = false;
        this._toggleFeedbackMode(state);
    }

    /**
     * Toggle between feedback display (read-only banner) and editor mode.
     *
     * @param {object} state Current state.
     */
    _toggleFeedbackMode(state) {
        const display = this.getElement(this.selectors.FEEDBACK_DISPLAY);
        const editorWrapper = this.getElement(this.selectors.FEEDBACK_EDITOR_WRAPPER);
        const displayContent = this.getElement(this.selectors.FEEDBACK_DISPLAY_CONTENT);

        if (!display || !editorWrapper) {
            return;
        }

        const feedbackHtml = state.grade?.feedbackdraft || state.grade?.feedback || '';
        const hasFeedback = this._hasMeaningfulFeedback(feedbackHtml);

        if (hasFeedback && !this._editingFeedback) {
            // Display mode: show banner with saved feedback, hide editor.
            display.classList.remove('d-none');
            editorWrapper.classList.add('d-none');
            if (displayContent) {
                displayContent.innerHTML = feedbackHtml;
            }
        } else {
            // Edit mode: hide banner, show editor.
            display.classList.add('d-none');
            editorWrapper.classList.remove('d-none');
        }
    }

    /**
     * Check whether feedback HTML contains meaningful content.
     *
     * @param {string} html The feedback HTML.
     * @return {boolean} True if there is non-empty text content.
     */
    _hasMeaningfulFeedback(html) {
        if (!html) {
            return false;
        }
        // Strip HTML tags and check for non-whitespace content.
        const text = html.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim();
        return text.length > 0;
    }

    /**
     * Handle "Edit" button click on the feedback display banner.
     */
    _handleEditFeedback() {
        this._editingFeedback = true;

        const display = this.getElement(this.selectors.FEEDBACK_DISPLAY);
        const editorWrapper = this.getElement(this.selectors.FEEDBACK_EDITOR_WRAPPER);

        if (display) {
            display.classList.add('d-none');
        }
        if (editorWrapper) {
            editorWrapper.classList.remove('d-none');
        }

        // Focus the TinyMCE editor after a brief delay (needed after unhiding).
        const textarea = this.getElement(this.selectors.FEEDBACK_INPUT);
        if (textarea) {
            const editor = getInstanceForElementId(textarea.id);
            if (editor) {
                setTimeout(() => editor.focus(), 100);
            }
        }
    }

    /**
     * Handle "Delete" button click on the feedback display banner.
     */
    async _handleDeleteFeedback() {
        const confirmMsg = await getString('confirm_delete_feedback', 'local_unifiedgrader');
        if (!window.confirm(confirmMsg)) {
            return;
        }

        // Clear the editor content and save with empty feedback.
        this._updateFeedbackContent('');
        this._handleSaveGrade();
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
                const textarea = this.getElement(this.selectors.FEEDBACK_INPUT);
                const editor = textarea ? getInstanceForElementId(textarea.id) : null;
                if (editor) {
                    editor.insertContent('<p>' + this._escapeHtml(comment.content) + '</p>');
                } else if (textarea) {
                    const currentVal = textarea.value;
                    textarea.value = currentVal
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
                saveBtn.textContent = await getString('savefeedback', 'local_unifiedgrader');
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
        } else if (definition.method === 'guide' || definition.method === 'quizmanual') {
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

            // Description for markers (HTML sanitized server-side by format_text).
            if (criterion.descriptionmarkers) {
                const markerDesc = document.createElement('div');
                markerDesc.className = 'small text-muted mb-2';
                markerDesc.innerHTML = criterion.descriptionmarkers;
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

            const remarkInput = document.createElement('textarea');
            remarkInput.rows = 3;
            remarkInput.className = 'form-control form-control-sm flex-grow-1';
            remarkInput.placeholder = 'Remark';
            remarkInput.textContent = currentFill[criterion.id]?.remark ?? '';
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

        if (method === 'quizmanual') {
            const questions = {};
            for (const criterion of this._gradingDefinition.criteria) {
                const id = criterion.id;
                questions[id] = {
                    mark: this._guideScores[id] || '',
                    comment: this._guideRemarks[id] || '',
                };
            }
            return JSON.stringify({method: 'quizmanual', questions});
        }

        return '';
    }

    /**
     * Handle save grade action.
     */
    _handleSaveGrade() {
        const state = this.reactive.state;
        const gradeInput = this.getElement(this.selectors.GRADE_INPUT);

        const grade = gradeInput ? gradeInput.value : '';
        const feedback = this._getEditorContent();
        const advancedGradingData = this._collectAdvancedGradingData();

        this.reactive.dispatch(
            'saveGrade',
            state.activity.cmid,
            state.currentUser.id,
            grade,
            feedback,
            state.ui.draftitemid,
            advancedGradingData,
            state.ui.feedbackfilesdraftid,
        );
    }

    /**
     * Handle save feedback files action.
     */
    _handleSaveFeedbackFiles() {
        const state = this.reactive.state;
        const feedbackfilesdraftid = state.ui.feedbackfilesdraftid;
        if (!feedbackfilesdraftid) {
            return;
        }

        this.reactive.dispatch(
            'saveFeedbackFiles',
            state.activity.cmid,
            state.currentUser.id,
            feedbackfilesdraftid,
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

    /**
     * Render the late submission indicator.
     *
     * Uses the per-user effective due date (accounts for overrides and
     * extensions) to determine whether the submission was late.
     *
     * @param {object} args Watcher args.
     * @param {object} args.state Current state.
     */
    _renderLateIndicator({state}) {
        const indicator = this.getElement(this.selectors.LATE_INDICATOR);
        if (!indicator) {
            return;
        }

        // Use the per-user effective due date, falling back to the global activity due date.
        const duedate = state.submission.effectiveduedate || state.activity.duedate || 0;
        const submitted = state.submission.timemodified || 0;

        if (!duedate || !submitted || submitted <= duedate) {
            indicator.classList.add('d-none');
            return;
        }

        // Calculate the late duration against the effective due date.
        const diffSeconds = submitted - duedate;
        const days = Math.floor(diffSeconds / 86400);
        const hours = Math.floor((diffSeconds % 86400) / 3600);
        const minutes = Math.floor((diffSeconds % 3600) / 60);

        let parts = [];
        if (days > 0) {
            parts.push(days + (days === 1 ? ' day' : ' days'));
        }
        if (hours > 0) {
            parts.push(hours + (hours === 1 ? ' hour' : ' hours'));
        }
        // Show minutes only when less than 1 day late.
        if (days === 0 && minutes > 0) {
            parts.push(minutes + (minutes === 1 ? ' min' : ' mins'));
        }
        const durationText = parts.join(' ') || '< 1 min';

        const textEl = this.getElement(this.selectors.LATE_TEXT);
        if (textEl) {
            textEl.textContent = 'Late: ' + durationText;
        }
        indicator.classList.remove('d-none');
    }

    /**
     * Render plagiarism links into the plagiarism section.
     *
     * @param {object} args Watcher args.
     * @param {object} args.state Current state.
     */
    _renderPlagiarism({state}) {
        const section = this.getElement(this.selectors.PLAGIARISM_SECTION);
        const body = this.getElement(this.selectors.PLAGIARISM_BODY);
        if (!section || !body) {
            return;
        }

        // Forums show per-post plagiarism shields inline in the preview panel,
        // so the marking panel card is redundant.
        const links = state.activity.type === 'forum'
            ? []
            : (state.submission.plagiarismlinks || []);

        if (links.length === 0) {
            section.classList.add('d-none');
            body.innerHTML = '';
            return;
        }

        let html = '<div class="list-group list-group-flush">';
        for (const link of links) {
            html += '<div class="list-group-item px-0 py-2 border-0">';
            html += '<div class="small fw-bold text-truncate mb-1">' + this._escapeHtml(link.label) + '</div>';
            html += '<div class="small">' + link.html + '</div>';
            html += '</div>';
        }
        html += '</div>';

        body.innerHTML = html;
        section.classList.remove('d-none');
    }

    /**
     * Escape HTML special characters in a string.
     *
     * @param {string} text The text to escape.
     * @return {string} Escaped text.
     */
    _escapeHtml(text) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }
}
