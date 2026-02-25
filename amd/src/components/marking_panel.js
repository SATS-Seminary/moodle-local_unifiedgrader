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
import CommentLibraryPopout from 'local_unifiedgrader/components/comment_library_popout';
import PenaltyPopout from 'local_unifiedgrader/components/penalty_popout';

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
            SCALE_GRADE: '[data-region="scale-grade"]',
            SCALE_INPUT: '[data-action="scale-input"]',
            ADVANCED_GRADING: '[data-region="advanced-grading"]',
            FEEDBACK_INPUT: '[data-action="feedback-input"]',
            SAVE_GRADE_BTN: '[data-action="save-grade"]',
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
            GRADE_PERCENTAGE: '[data-region="grade-percentage"]',
            OVERALL_FEEDBACK_SECTION: '[data-region="overall-feedback-section"]',
            FEEDBACK_COLLAPSE: '[data-region="feedback-collapse"]',
            TOGGLE_PENALTIES: '[data-action="toggle-penalties"]',
            PENALTY_BADGES: '[data-region="penalty-badges"]',
            FINAL_GRADE_DISPLAY: '[data-region="final-grade-display"]',
            FINAL_GRADE_VALUE: '[data-region="final-grade-value"]',
            FINAL_GRADE_MAX: '[data-region="final-grade-max"]',
            FINAL_GRADE_PERCENTAGE: '[data-region="final-grade-percentage"]',
            ATTEMPT_SELECTOR: '[data-region="attempt-selector"]',
            ATTEMPT_SELECT: '[data-action="attempt-select"]',
        };
        this._editingFeedback = false;
        this._penaltyPopout = null;
        this._gradingDefinition = null;
        this._rubricSelections = {};
        this._guideScores = {};
        this._guideRemarks = {};
        this._lastFocusedField = null;
        this._clibPopout = null;
        this._autoSaveTimer = null;
        this._suppressAutoSave = false;
        this._reportButtonLabel = 'Report academic impropriety';
        getString('report_impropriety', 'local_unifiedgrader').then(str => {
            this._reportButtonLabel = str;
        }).catch(() => {});
    }

    /**
     * Register state watchers.
     *
     * @return {Array}
     */
    getWatchers() {
        return [
            {watch: 'submission:updated', handler: this._renderAttemptSelector},
            {watch: 'submission:updated', handler: this._renderLateIndicator},
            {watch: 'submission:updated', handler: this._renderPlagiarism},
            {watch: 'grade:updated', handler: this._renderGrade},
            {watch: 'state.notes:updated', handler: this._renderNotes},
            {watch: 'state.penalties:updated', handler: this._renderPenalties},
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

        // Listen for grade input changes to update percentage and final grade in real time.
        const gradeInput = this.getElement(this.selectors.GRADE_INPUT);
        if (gradeInput) {
            gradeInput.addEventListener('input', () => {
                this._updatePercentage();
                this._updateFinalGradeDisplay();
            });
        }

        if (state.grade) {
            this._renderGrade({state});
        }

        // Attempt selector change handler.
        const attemptSelect = this.getElement(this.selectors.ATTEMPT_SELECT);
        if (attemptSelect) {
            attemptSelect.addEventListener('change', (e) => {
                const attemptnumber = parseInt(e.target.value, 10);
                const currentState = this.reactive.state;
                if (attemptnumber !== currentState.submission.attemptnumber) {
                    this.reactive.dispatch(
                        'loadAttempt',
                        currentState.activity.cmid,
                        currentState.currentUser.id,
                        attemptnumber,
                    );
                }
            });
        }

        // Initialise comment library popout.
        const coursecode = state.activity?.coursecode || '';
        this._clibPopout = new CommentLibraryPopout(coursecode, () => this._lastFocusedField);

        // Attach toggle handlers to all comment library icon buttons.
        this.element.querySelectorAll('[data-action="toggle-comment-library"]').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                e.preventDefault();
                this._clibPopout.toggle(btn);
            });
        });

        // Initialise penalty popout.
        this._penaltyPopout = new PenaltyPopout(
            (category, label, percentage) => {
                const cmid = state.activity.cmid;
                const userid = state.currentUser.id;
                this.reactive.dispatch('savePenalty', cmid, userid, category, label, percentage);
            },
            (penaltyId) => {
                const cmid = state.activity.cmid;
                const userid = state.currentUser.id;
                this.reactive.dispatch('deletePenalty', cmid, userid, penaltyId);
            },
        );

        const penaltyBtn = this.getElement(this.selectors.TOGGLE_PENALTIES);
        if (penaltyBtn) {
            penaltyBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                e.preventDefault();
                const penalties = this._getPenaltiesArray();
                this._penaltyPopout.toggle(penaltyBtn, penalties);
            });
        }

        // Track the last-focused remark textarea or score input inside the rubric/guide.
        const rubricBody = this.getElement(this.selectors.RUBRIC_BODY);
        if (rubricBody) {
            rubricBody.addEventListener('focusin', (e) => {
                if (e.target.matches('textarea, input[type="number"]')) {
                    this._lastFocusedField = e.target;
                }
            });

            // Auto-save when focus leaves the rubric/guide entirely.
            // Skip if focus moves to another rubric field or the comment library popout.
            rubricBody.addEventListener('focusout', (e) => {
                const next = e.relatedTarget;
                // Stay quiet if focus stays inside the rubric body.
                if (next && rubricBody.contains(next)) {
                    return;
                }
                // Stay quiet if focus moves to the comment library popout.
                if (next && next.closest('.local-unifiedgrader-clib-popout')) {
                    return;
                }
                // Stay quiet if focus moves to a comment library toggle button.
                if (next && next.closest('[data-action="toggle-comment-library"]')) {
                    return;
                }
                this._debouncedAutoSave();
            });
        }

        // Track TinyMCE (overall feedback) focus so the comment library can insert there.
        const feedbackTextarea = this.getElement(this.selectors.FEEDBACK_INPUT);
        if (feedbackTextarea) {
            this._setupTinyMCEFocusTracking(feedbackTextarea);
        }
    }

    /**
     * Register a focus handler on the TinyMCE editor for the given textarea.
     * Polls until the editor is ready since TinyMCE initialises asynchronously.
     *
     * @param {HTMLElement} textarea The underlying textarea element.
     */
    _setupTinyMCEFocusTracking(textarea) {
        const tryRegister = () => {
            const editor = getInstanceForElementId(textarea.id);
            if (editor) {
                editor.on('focus', () => {
                    this._lastFocusedField = textarea;
                });
            } else {
                setTimeout(tryRegister, 500);
            }
        };
        setTimeout(tryRegister, 1000);
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

        const usescale = state.activity?.usescale || false;

        if (usescale) {
            // Scale: set the dropdown value.
            const scaleInput = this.getElement(this.selectors.SCALE_INPUT);
            if (scaleInput && state.grade) {
                scaleInput.value = state.grade.grade !== null ? String(Math.round(state.grade.grade)) : '';
            }
        } else {
            // Points: set the numeric input value.
            // The grade from Moodle is the adjusted (post-penalty) value.
            // Reverse-calculate the raw (pre-penalty) grade for display.
            const gradeInput = this.getElement(this.selectors.GRADE_INPUT);
            if (gradeInput && state.grade) {
                let displayGrade = state.grade.grade;
                if (displayGrade !== null) {
                    const totalDeduction = this._getTotalPenaltyDeduction(state);
                    if (totalDeduction > 0) {
                        displayGrade = parseFloat(displayGrade) + totalDeduction;
                        // Round to avoid floating point artifacts.
                        displayGrade = Math.round(displayGrade * 100) / 100;
                    }
                }
                gradeInput.value = displayGrade !== null ? displayGrade : '';

                // When advanced grading is active and manual override is not allowed,
                // make the grade input readonly so teachers must use the rubric/guide.
                const hasAdvancedGrading = this._gradingDefinition !== null;
                const allowOverride = state.ui?.allowmanualgradeoverride !== false;
                gradeInput.readOnly = hasAdvancedGrading && !allowOverride;
            }
        }

        // Update the percentage display and final grade after penalties.
        this._updatePercentage();
        this._updateFinalGradeDisplay();

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

        // Auto-expand the overall feedback section if feedback exists, collapse if not.
        const feedbackHtml = state.grade?.feedbackdraft || state.grade?.feedback || '';
        this._setFeedbackSectionExpanded(this._hasMeaningfulFeedback(feedbackHtml));
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

        // Ensure the feedback section is expanded.
        this._setFeedbackSectionExpanded(true);

        const display = this.getElement(this.selectors.FEEDBACK_DISPLAY);
        const editorWrapper = this.getElement(this.selectors.FEEDBACK_EDITOR_WRAPPER);

        if (display) {
            display.classList.add('d-none');
        }
        if (editorWrapper) {
            editorWrapper.classList.remove('d-none');
        }

        // Re-populate the editor from state — TinyMCE may lose content set while hidden.
        const state = this.reactive.state;
        const feedbackHtml = state.grade?.feedbackdraft || state.grade?.feedback || '';
        this._updateFeedbackContent(feedbackHtml);

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
        const simpleGrade = this.getElement(this.selectors.SIMPLE_GRADE);
        const scaleGrade = this.getElement(this.selectors.SCALE_GRADE);
        const usescale = state.activity?.usescale || false;

        if (usescale && scaleGrade) {
            // Scale-based grading: show dropdown, hide numeric input.
            if (simpleGrade) {
                simpleGrade.classList.add('d-none');
            }
            scaleGrade.classList.remove('d-none');

            // Populate the scale dropdown if not already done.
            const scaleInput = this.getElement(this.selectors.SCALE_INPUT);
            if (scaleInput && scaleInput.options.length <= 1) {
                scaleInput.innerHTML = '';
                const emptyOpt = document.createElement('option');
                emptyOpt.value = '';
                emptyOpt.textContent = '---';
                scaleInput.appendChild(emptyOpt);
                const items = state.activity.scaleitems || [];
                for (const item of items) {
                    const opt = document.createElement('option');
                    opt.value = item.value;
                    opt.textContent = item.label;
                    scaleInput.appendChild(opt);
                }
                scaleInput.addEventListener('change', () => this._debouncedAutoSave());
            }
        } else {
            // Point-based grading: show numeric input, hide dropdown.
            if (scaleGrade) {
                scaleGrade.classList.add('d-none');
            }
            if (simpleGrade) {
                simpleGrade.classList.remove('d-none');
            }
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
    }

    /**
     * Update the percentage display next to the grade input.
     */
    _updatePercentage() {
        const percentEl = this.getElement(this.selectors.GRADE_PERCENTAGE);
        if (!percentEl) {
            return;
        }

        // Percentages don't apply to scale-based grading.
        if (this.reactive.state.activity?.usescale) {
            percentEl.textContent = '';
            return;
        }

        const gradeInput = this.getElement(this.selectors.GRADE_INPUT);
        const rawGrade = gradeInput ? parseFloat(gradeInput.value) : NaN;
        const maxgrade = parseFloat(gradeInput?.max) || 100;

        if (isNaN(rawGrade) || rawGrade < 0) {
            percentEl.textContent = '';
            return;
        }

        // Show the raw grade percentage (before penalties).
        const pct = Math.round((rawGrade / maxgrade) * 100);
        percentEl.textContent = '(' + pct + '%)';
    }

    /**
     * Update the "Final grade after penalties" display below the penalty badges.
     * Visible only when penalties exist and a grade has been entered.
     */
    _updateFinalGradeDisplay() {
        const displayEl = this.getElement(this.selectors.FINAL_GRADE_DISPLAY);
        if (!displayEl) {
            return;
        }

        // Not applicable for scale-based grading.
        if (this.reactive.state.activity?.usescale) {
            displayEl.classList.add('d-none');
            return;
        }

        const penalties = this._getPenaltiesArray();
        const gradeInput = this.getElement(this.selectors.GRADE_INPUT);
        const rawGrade = gradeInput ? parseFloat(gradeInput.value) : NaN;
        const maxgrade = parseFloat(gradeInput?.max) || 100;

        // Hide if no penalties or no grade entered.
        if (!penalties.length || isNaN(rawGrade) || rawGrade < 0) {
            displayEl.classList.add('d-none');
            return;
        }

        const totalDeduction = this._getTotalPenaltyDeduction(this.reactive.state);
        const finalGrade = Math.max(0, Math.round((rawGrade - totalDeduction) * 100) / 100);
        const finalPct = Math.round((finalGrade / maxgrade) * 100);

        const valueEl = this.getElement(this.selectors.FINAL_GRADE_VALUE);
        const maxEl = this.getElement(this.selectors.FINAL_GRADE_MAX);
        const pctEl = this.getElement(this.selectors.FINAL_GRADE_PERCENTAGE);

        if (valueEl) {
            valueEl.textContent = finalGrade;
        }
        if (maxEl) {
            maxEl.textContent = maxgrade;
        }
        if (pctEl) {
            pctEl.textContent = '(' + finalPct + '%)';
        }

        displayEl.classList.remove('d-none');
    }

    /**
     * Get penalties as a plain array from the reactive state (StateMap → Array).
     *
     * @return {Array} Array of penalty objects.
     */
    _getPenaltiesArray() {
        const penaltiesState = this.reactive.state.penalties;
        if (!penaltiesState) {
            return [];
        }
        // StateMap: convert to array via values().
        if (typeof penaltiesState.values === 'function') {
            return [...penaltiesState.values()];
        }
        // Fallback: already an array.
        if (Array.isArray(penaltiesState)) {
            return penaltiesState;
        }
        return [];
    }

    /**
     * Calculate total penalty deduction in marks.
     *
     * @param {object} state Current reactive state.
     * @return {number} Total marks to deduct.
     */
    _getTotalPenaltyDeduction(state) {
        const penalties = this._getPenaltiesArray();
        if (!penalties.length) {
            return 0;
        }
        const maxgrade = parseFloat(state.ui?.maxgrade || state.activity?.maxgrade) || 100;
        let totalPct = 0;
        penalties.forEach((p) => {
            totalPct += parseInt(p.percentage, 10) || 0;
        });
        return (totalPct / 100) * maxgrade;
    }

    /**
     * Render penalty badges below the grade input and update the popout.
     *
     * @param {object} args Watcher args.
     * @param {object} args.state Current state.
     */
    _renderPenalties() {
        const badgesEl = this.getElement(this.selectors.PENALTY_BADGES);
        if (!badgesEl) {
            return;
        }

        const penalties = this._getPenaltiesArray();
        badgesEl.innerHTML = '';

        penalties.forEach((p) => {
            const badge = document.createElement('span');
            badge.className = 'badge bg-warning text-dark local-unifiedgrader-penalty-badge';
            const displayLabel = p.category === 'wordcount' ? 'Word count' : (p.label || 'Other');
            badge.textContent = '-' + p.percentage + '% ' + displayLabel;
            badgesEl.appendChild(badge);
        });

        // Update the popout if it's open.
        if (this._penaltyPopout) {
            this._penaltyPopout.updatePenalties(penalties);
        }

        // Recalculate percentage and final grade displays with updated penalties.
        this._updatePercentage();
        this._updateFinalGradeDisplay();

        // Update the penalties button to indicate active penalties.
        const penaltyBtn = this.getElement(this.selectors.TOGGLE_PENALTIES);
        if (penaltyBtn) {
            if (penalties.length > 0) {
                penaltyBtn.classList.remove('btn-outline-secondary');
                penaltyBtn.classList.add('btn-warning', 'text-dark');
            } else {
                penaltyBtn.classList.remove('btn-warning', 'text-dark');
                penaltyBtn.classList.add('btn-outline-secondary');
            }
        }
    }

    /**
     * Expand or collapse the overall feedback section.
     *
     * @param {boolean} expand True to expand, false to collapse.
     */
    _setFeedbackSectionExpanded(expand) {
        const section = this.getElement(this.selectors.OVERALL_FEEDBACK_SECTION);
        if (!section) {
            return;
        }
        const header = section.querySelector('[data-bs-toggle="collapse"]');
        const collapseEl = this.getElement(this.selectors.FEEDBACK_COLLAPSE);
        if (!header || !collapseEl) {
            return;
        }

        if (expand) {
            collapseEl.classList.add('show');
            header.classList.remove('collapsed');
            header.setAttribute('aria-expanded', 'true');
        } else {
            collapseEl.classList.remove('show');
            header.classList.add('collapsed');
            header.setAttribute('aria-expanded', 'false');
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

        // Suppress focusout auto-save during re-rendering. Without this,
        // destroying a focused textarea via innerHTML triggers focusout →
        // auto-save → re-render → focusout loop.
        this._suppressAutoSave = true;
        if (this._autoSaveTimer) {
            clearTimeout(this._autoSaveTimer);
            this._autoSaveTimer = null;
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
            this._suppressAutoSave = false;
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
        this._suppressAutoSave = false;
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

            // Comment library icon for this criterion.
            const clibBtn = document.createElement('button');
            clibBtn.type = 'button';
            clibBtn.className = 'btn btn-link btn-sm p-0 text-muted mt-1';
            clibBtn.dataset.action = 'toggle-comment-library';
            clibBtn.title = 'Comment Library';
            clibBtn.innerHTML = '<i class="fa fa-commenting" aria-hidden="true"></i>';
            clibBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                e.preventDefault();
                this._clibPopout.toggle(clibBtn);
            });

            row.appendChild(levelContainer);
            row.appendChild(clibBtn);
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
        this._debouncedAutoSave();
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

        // Sync total into the simple grade input and update percentage.
        const gradeInput = this.getElement(this.selectors.GRADE_INPUT);
        if (gradeInput) {
            gradeInput.value = total;
        }
        this._updatePercentage();
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

            // Description for markers (sanitized server-side via format_text in the adapter).
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

            // Comment library icon for this criterion.
            const clibBtn = document.createElement('button');
            clibBtn.type = 'button';
            clibBtn.className = 'btn btn-link btn-sm p-0 text-muted align-self-start mt-1';
            clibBtn.dataset.action = 'toggle-comment-library';
            clibBtn.title = 'Comment Library';
            clibBtn.innerHTML = '<i class="fa fa-commenting" aria-hidden="true"></i>';
            clibBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                e.preventDefault();
                // Set last focused field to this criterion's remark textarea.
                this._lastFocusedField = remarkInput;
                this._clibPopout.toggle(clibBtn);
            });

            controls.appendChild(scoreInput);
            controls.appendChild(remarkInput);
            controls.appendChild(clibBtn);
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

        // Sync total into the simple grade input and update percentage.
        const gradeInput = this.getElement(this.selectors.GRADE_INPUT);
        if (gradeInput) {
            gradeInput.value = total;
        }
        this._updatePercentage();
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
     * Debounced auto-save — waits briefly so rapid field switches don't fire multiple saves.
     * Skips if a save is already in progress to prevent re-render → focusout → save loops.
     */
    _debouncedAutoSave() {
        if (this._suppressAutoSave || this.reactive.state.ui.saving) {
            return;
        }
        if (this._autoSaveTimer) {
            clearTimeout(this._autoSaveTimer);
        }
        this._autoSaveTimer = setTimeout(() => {
            this._autoSaveTimer = null;
            if (!this._suppressAutoSave && !this.reactive.state.ui.saving) {
                this._handleSaveGrade();
            }
        }, 600);
    }

    /**
     * Handle save grade action.
     */
    _handleSaveGrade() {
        // Cancel any pending auto-save to prevent double saves.
        if (this._autoSaveTimer) {
            clearTimeout(this._autoSaveTimer);
            this._autoSaveTimer = null;
        }
        const state = this.reactive.state;

        // Read grade from the appropriate input (scale dropdown or numeric input).
        let grade = '';
        if (state.activity?.usescale) {
            const scaleInput = this.getElement(this.selectors.SCALE_INPUT);
            grade = scaleInput ? scaleInput.value : '';
        } else {
            const gradeInput = this.getElement(this.selectors.GRADE_INPUT);
            grade = gradeInput ? gradeInput.value : '';
        }
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
     * Render the attempt selector dropdown.
     *
     * Shows the dropdown only when the assignment supports multiple attempts
     * and the student has more than one attempt.
     *
     * @param {object} args Watcher args.
     * @param {object} args.state Current state.
     */
    _renderAttemptSelector({state}) {
        const wrapper = this.getElement(this.selectors.ATTEMPT_SELECTOR);
        const select = this.getElement(this.selectors.ATTEMPT_SELECT);
        if (!wrapper || !select) {
            return;
        }

        const maxattempts = state.activity?.maxattempts ?? 1;

        // Get attempts list — may be a StateMap (has .values()) or an array.
        let attemptList = [];
        const attempts = state.submission?.attempts;
        if (attempts) {
            if (typeof attempts.values === 'function') {
                attemptList = [...attempts.values()];
            } else if (Array.isArray(attempts)) {
                attemptList = attempts;
            }
        }

        // Hide if single-attempt assignment or only one attempt exists.
        if (maxattempts === 1 || attemptList.length <= 1) {
            wrapper.classList.add('d-none');
            return;
        }

        wrapper.classList.remove('d-none');

        // Populate the dropdown options.
        const currentAttempt = state.submission.attemptnumber;
        select.innerHTML = '';

        attemptList.forEach((attempt) => {
            const option = document.createElement('option');
            option.value = attempt.attemptnumber;
            const num = attempt.attemptnumber + 1;
            const statusLabel = attempt.graded ? 'graded' : attempt.status;
            option.textContent = `Attempt ${num} (${statusLabel})`;
            option.selected = attempt.attemptnumber === currentAttempt;
            select.appendChild(option);
        });
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

        // Academic impropriety report button.
        if (state.ui.enableReportForm && state.ui.reportFormUrl) {
            const reportUrl = this._buildReportUrl(state);
            html += '<div class="mt-2 pt-2 border-top">';
            html += '<a href="' + this._escapeHtml(reportUrl) + '" target="_blank" rel="noopener"'
                + ' class="btn btn-sm btn-outline-danger w-100">';
            html += '<i class="fa fa-flag me-1"></i>';
            html += this._escapeHtml(this._reportButtonLabel);
            html += '</a>';
            html += '</div>';
        }

        body.innerHTML = html;
        section.classList.remove('d-none');
    }

    /**
     * Build the academic impropriety report URL with placeholders replaced.
     *
     * @param {object} state Current reactive state.
     * @returns {string} The fully resolved URL.
     */
    _buildReportUrl(state) {
        const participants = [...state.participants.values()];
        const student = participants.find(p => p.id === state.submission.userid);
        const studentName = student ? student.fullname : '';
        const graderUrl = M.cfg.wwwroot + '/local/unifiedgrader/grade.php?cmid='
            + state.activity.cmid + '&userid=' + state.submission.userid;

        let url = state.ui.reportFormUrl;
        // Decode URL-encoded braces so placeholder patterns match (browsers/MS Forms encode { } as %7B %7D).
        url = url.replace(/%7B/gi, '{').replace(/%7D/gi, '}');
        url = url.replace(/\{studentname\}/gi, encodeURIComponent(studentName));
        url = url.replace(/\{coursecode\}/gi, encodeURIComponent(state.activity.coursecode || ''));
        url = url.replace(/\{coursename\}/gi, encodeURIComponent(state.ui.coursefullname || ''));
        url = url.replace(/\{activityname\}/gi, encodeURIComponent(state.activity.name || ''));
        url = url.replace(/\{activitytype\}/gi, encodeURIComponent(state.activity.type || ''));
        url = url.replace(/\{studentid\}/gi, encodeURIComponent(String(state.submission.userid || '')));
        url = url.replace(/\{gradername\}/gi, encodeURIComponent(state.ui.graderFullname || ''));
        url = url.replace(/\{graderurl\}/gi, encodeURIComponent(graderUrl));
        return url;
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
