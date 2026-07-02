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
import * as DirtyTracker from 'local_unifiedgrader/dirty_tracker';
import * as OfflineCache from 'local_unifiedgrader/offline_cache';

export default class extends BaseComponent {

    /**
     * Component creation hook.
     */
    create() {
        this.name = 'marking_panel';
        this.selectors = {
            GRADE_SECTION: '[data-region="grade-section"]',
            GRADE_INPUT: '[data-action="grade-input"]',
            GRADE_ERROR: '[data-region="grade-error"]',
            GRADE_OVERRIDE_INDICATOR: '[data-region="grade-override-indicator"]',
            GRADE_RUBRIC_VALUE: '[data-region="grade-rubric-value"]',
            GRADE_RESET_RUBRIC_BTN: '[data-action="grade-reset-rubric"]',
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
            PLAGIARISM_ACTIONS: '[data-region="plagiarism-actions"]',
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
            REFERRAL_SECTION: '[data-region="referral-section"]',
            REFERRAL_FORM: '[data-region="referral-form"]',
            REFERRAL_STATUS: '[data-region="referral-status"]',
            REFER_BTN: '[data-action="refer-integrity"]',
            REFER_LABEL: '[data-region="refer-label"]',
            REFERRAL_NOTE: '[data-action="referral-note"]',
            REFERRAL_CONFIRM: '[data-action="referral-confirm"]',
            REFERRAL_CANCEL: '[data-action="referral-cancel"]',
            REFERRAL_RESOLVE: '[data-action="referral-resolve"]',
            FINAL_GRADE_DISPLAY: '[data-region="final-grade-display"]',
            FINAL_GRADE_VALUE: '[data-region="final-grade-value"]',
            FINAL_GRADE_MAX: '[data-region="final-grade-max"]',
            FINAL_GRADE_PERCENTAGE: '[data-region="final-grade-percentage"]',
            ATTEMPT_SELECTOR: '[data-region="attempt-selector"]',
            ATTEMPT_SELECT: '[data-action="attempt-select"]',
            MARK_GRADED_SECTION: '[data-region="mark-graded-section"]',
            MARK_GRADED_TOGGLE: '[data-action="mark-graded-toggle"]',
        };
        this._editingFeedback = false;
        // One-shot: set when a save is dispatched while the teacher is
        // re-editing existing feedback, so the post-save render collapses the
        // editor back to the read-only "saved" card (see _handleSaveGrade /
        // _renderFeedbackAndSnapshot).
        this._collapseFeedbackAfterSave = false;
        this._penaltyPopout = null;
        this._gradingDefinition = null;
        this._rubricSelections = {};
        this._guideScores = {};
        this._guideRemarks = {};
        // The teacher owns the editable form. The server is authoritative for
        // *derived* display (penalty badges, percentage, late indicators) but
        // it must never reach into editable inputs after a save success — that
        // is the race the user kept hitting. Form-input overwrites are gated
        // on `_lastRenderedUserid`: we apply server values only on the
        // navigation boundaries (initial render, student switch). Every other
        // re-render keeps the DOM as the teacher last edited it.
        this._lastRenderedUserid = undefined;
        // Switching attempts is also a navigation boundary — same student,
        // but a different attempt's grade / feedback / rubric needs to
        // replace what's currently in the form. Track this alongside
        // _lastRenderedUserid so the override-lock gating treats attempt
        // changes as "fresh" too. Without this, after the user picks a
        // different attempt the WS returns new data and Object.assigns it
        // into state.grade, but _renderGrade sees the same userid as before
        // and skips the form-input write — so the panel stays on the old
        // attempt's values until the teacher navigates to a different
        // student and back.
        this._lastRenderedAttempt = undefined;
        // Set to true the moment the teacher types into the top-level grade
        // input. While this is true, _updateGuideTotal will not overwrite
        // gradeInput.value with the rubric-computed total — manual overrides
        // survive subsequent rubric edits. Cleared on student switch (fresh
        // render) so the next student starts in auto-sync mode.
        this._gradeManuallyOverridden = false;
        // Cache of the most recent rubric-implied grade so _updateOverrideIndicator
        // can be called after the displayed value has been restored (returning
        // teacher) without re-deriving from _guideScores.
        this._lastRubricGrade = null;
        this._guideBaseTotal = 0;
        this._quizBaseGrade = undefined;
        this._lastFocusedField = null;
        this._clibPopout = null;
        this._autoSaveTimer = null;
        this._suppressAutoSave = false;
        this._saveInFlight = false;
        this._reportButtonLabel = 'Report academic impropriety';
        // Default label for the standalone grade-pane referral button; captured
        // from the rendered span on init, with this English fallback for safety.
        this._referLabel = 'Refer for integrity review';
        // Confirm prompt for the combined plagiarism-pane button. Pre-fetched so
        // the click handler can stay fully synchronous — window.open() for the
        // report form must run inside the user gesture or the pop-up is blocked.
        this._referConfirmMsg = 'Flag this submission for an academic-integrity review and open the report form?';
        // Where the referral control currently lives: 'grade' (its template
        // home) or 'plagiarism' (relocated into the plagiarism card).
        this._referralHosted = 'grade';
        this._referralHomeParent = null;
        getString('report_impropriety', 'local_unifiedgrader').then(str => {
            this._reportButtonLabel = str;
        }).catch(() => {});
        getString('refer_report_confirm', 'local_unifiedgrader').then(str => {
            this._referConfirmMsg = str;
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
            {watch: 'state.referrals:updated', handler: this._renderReferral},
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

        // Listen for save requests from other components (e.g. student navigator before switch).
        document.addEventListener('unifiedgrader:requestsave', () => {
            if (DirtyTracker.isDirty('grade') || DirtyTracker.isDirty('feedback')) {
                this._handleSaveGrade();
            }
        });

        // Listen for grade input changes to update percentage and final grade in real time.
        const gradeInput = this.getElement(this.selectors.GRADE_INPUT);
        if (gradeInput) {
            gradeInput.addEventListener('input', () => {
                // Canonicalise comma → period so a teacher in a comma-decimal
                // locale can enter "3,5" and it round-trips correctly.
                if (gradeInput.value && gradeInput.value.indexOf(',') !== -1) {
                    gradeInput.value = gradeInput.value.replace(',', '.');
                }
                // Lock the grade input against future rubric-driven rewrites.
                // The flag stays set until the student changes (fresh render).
                this._gradeManuallyOverridden = true;
                this._validateGrade();
                this._updatePercentage();
                this._updateFinalGradeDisplay();
                // Refresh the override badge on every keystroke. _updateGuideTotal
                // is the simplest way to do this: it recomputes the rubric grade
                // and drives the indicator. The gradeInput write inside is a
                // no-op while the override flag is set.
                this._updateGuideTotal();
                if (DirtyTracker.hasChanged('grade', gradeInput.value)) {
                    DirtyTracker.markDirty('grade');
                    this._cacheGradeValue();
                }
            });

            // Autosave when the teacher tabs/clicks away from the grade
            // input. The rubric body uses a debounced (1.5s) save because
            // the teacher might be moving between rubric inputs and we
            // don't want to save mid-edit. The grade input is a single
            // value though — once the teacher leaves it they're done with
            // the grade — so we fire the save immediately. The debounce
            // would otherwise let a fast refresh / F5 cancel the save
            // before it actually goes out, which is exactly what users
            // were hitting when overriding the rubric-computed total.
            gradeInput.addEventListener('focusout', () => {
                if (DirtyTracker.isDirty('grade') && this._validateGrade()) {
                    this._handleSaveGrade();
                }
            });
        }

        // Reset-to-rubric button: clears the manual override flag and
        // re-syncs the grade input with the rubric / guide computed total.
        const resetBtn = this.getElement(this.selectors.GRADE_RESET_RUBRIC_BTN);
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                this._gradeManuallyOverridden = false;
                this._updateGuideTotal();
                // _updateGuideTotal wrote the rubric value back into the
                // grade input; mark dirty so the autosave persists the
                // reset, then trigger validation + autosave.
                if (gradeInput) {
                    DirtyTracker.markDirty('grade');
                    this._cacheGradeValue();
                    this._validateGrade();
                    this._debouncedAutoSave();
                }
            });
        }

        // Listen for scale dropdown changes to track dirty state.
        const scaleInput = this.getElement(this.selectors.SCALE_INPUT);
        if (scaleInput) {
            scaleInput.addEventListener('change', () => {
                if (DirtyTracker.hasChanged('grade', scaleInput.value)) {
                    DirtyTracker.markDirty('grade');
                    this._cacheGradeValue();
                }
            });
        }

        // "Mark as graded" toggle — save immediately on change.
        const markGradedToggle = this.getElement(this.selectors.MARK_GRADED_TOGGLE);
        if (markGradedToggle) {
            markGradedToggle.addEventListener('change', () => {
                DirtyTracker.markDirty('grade');
                this._handleSaveGrade();
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

        // Wire the academic-integrity referral controls (present only when the
        // teacher has the refer capability — the section is omitted otherwise).
        this._initReferralControls();

        // Track the last-focused remark textarea or score input inside the rubric/guide.
        const rubricBody = this.getElement(this.selectors.RUBRIC_BODY);
        if (rubricBody) {
            rubricBody.addEventListener('focusin', (e) => {
                if (e.target.matches('textarea, input[type="text"][data-criterionid], input[type="number"]')) {
                    this._lastFocusedField = e.target;
                }
            });

            // Mark grade dirty when rubric/guide fields are edited.
            rubricBody.addEventListener('input', (e) => {
                if (e.target.matches('textarea, input[type="text"][data-criterionid], input[type="number"]')) {
                    DirtyTracker.markDirty('grade');
                    this._cacheGradeValue();
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
                // Defer the decision: clicking a non-focusable element (e.g. our
                // comment-library button in Safari/WebKit, or a popout list item)
                // arrives here with relatedTarget=null even though the user has
                // not actually left the rubric — focus simply has nowhere to land.
                // Wait for focus to settle, then re-check before firing autosave;
                // this prevents the popout-open from triggering a save that races
                // with in-progress edits and visually resets the values.
                if (next === null) {
                    setTimeout(() => {
                        const active = document.activeElement;
                        if (active && rubricBody.contains(active)) {
                            return;
                        }
                        if (active && active.closest && (
                            active.closest('.local-unifiedgrader-clib-popout')
                            || active.closest('[data-action="toggle-comment-library"]')
                        )) {
                            return;
                        }
                        this._debouncedAutoSave();
                    }, 0);
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
                // Track feedback dirty state via TinyMCE content changes.
                editor.on('input change keyup Paste', () => {
                    if (DirtyTracker.hasChanged('feedback', editor.getContent())) {
                        DirtyTracker.markDirty('feedback');
                        this._cacheFeedbackValue(editor.getContent());
                    }
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
     * Callers from the navigation path (initial render / student switch) and
     * explicit user actions (toggle-to-edit, delete-feedback) pass force=true
     * so the editor body is replaced. The auto-save / state-refresh path
     * never calls this without force, by design: the teacher owns the editor
     * after the first paint.
     *
     * @param {string} html The HTML content to set.
     * @param {boolean} force Apply the content even if the editor currently has focus.
     */
    _updateFeedbackContent(html, force = false) {
        const textarea = this.getElement(this.selectors.FEEDBACK_INPUT);
        if (!textarea) {
            return;
        }
        if (!force) {
            // Belt-and-braces: refuse to overwrite when the editor has focus,
            // even if a future code path forgets to gate on isFreshRender.
            const editorForCheck = getInstanceForElementId(textarea.id);
            const editorFocused = editorForCheck
                && typeof editorForCheck.hasFocus === 'function'
                && editorForCheck.hasFocus();
            if (editorFocused || textarea === document.activeElement) {
                return;
            }
        }
        const editor = getInstanceForElementId(textarea.id);
        if (editor) {
            editor.setContent(html || '');
        } else {
            textarea.value = html || '';
        }
    }

    /**
     * Cache the current grade value to IndexedDB.
     */
    _cacheGradeValue() {
        const state = this.reactive.state;
        const cmid = state.activity?.cmid;
        const userid = state.currentUser?.id;
        if (!cmid || !userid) {
            return;
        }
        const gradeInput = this.getElement(this.selectors.GRADE_INPUT);
        const scaleInput = this.getElement(this.selectors.SCALE_INPUT);
        const value = gradeInput ? gradeInput.value : (scaleInput ? scaleInput.value : '');
        const advancedGradingData = this._collectAdvancedGradingData();
        OfflineCache.save(cmid, userid, 'grade', {value, advancedGradingData});
    }

    /**
     * Cache feedback HTML to IndexedDB.
     *
     * @param {string} html The feedback HTML content.
     */
    _cacheFeedbackValue(html) {
        const state = this.reactive.state;
        const cmid = state.activity?.cmid;
        const userid = state.currentUser?.id;
        if (!cmid || !userid) {
            return;
        }
        OfflineCache.save(cmid, userid, 'feedback', {html});
    }

    /**
     * Render grade data into the form.
     *
     * @param {object} args Watcher args.
     * @param {object} args.state Current state.
     */
    _renderGrade({state}) {
        // Navigation-boundary gate: form-input values may only be overwritten
        // from server state on the initial render or when the active student
        // changes. Every other invocation of this watcher (after a save, after
        // a penalty/extension mutation, etc.) skips the form-input writes and
        // lets the DOM reflect what the teacher is actually typing. Derived
        // displays (penalty badges, percentage, late indicator) update freely.
        const renderedUserid = this._lastRenderedUserid;
        const currentUserid = state.currentUser?.id;
        const currentAttempt = state.submission?.attemptnumber ?? null;
        const renderedAttempt = this._lastRenderedAttempt;
        const isFreshRender = renderedUserid === undefined
            || renderedUserid !== currentUserid
            || renderedAttempt !== currentAttempt;
        this._lastRenderedUserid = currentUserid;
        this._lastRenderedAttempt = currentAttempt;
        // New student OR different attempt of the same student → grade input
        // is no longer "manually overridden"; the saved grade/rubric for
        // this attempt should drive the display, and rubric edits should
        // resume auto-syncing the displayed total.
        if (isFreshRender) {
            this._gradeManuallyOverridden = false;
        }

        // When grading is disabled (forum grade type "None"), hide the entire
        // grade section and the rubric/marking guide — they are a non-sequitur.
        // Overall feedback is still shown and functional.
        const gradingDisabled = state.activity?.gradingdisabled || false;
        const gradeSection = this.getElement(this.selectors.GRADE_SECTION);
        const rubricSection = this.getElement(this.selectors.RUBRIC_SECTION);
        const markGradedSection = this.getElement(this.selectors.MARK_GRADED_SECTION);
        if (gradingDisabled) {
            if (gradeSection) {
                gradeSection.classList.add('d-none');
            }
            if (rubricSection) {
                rubricSection.classList.add('d-none');
            }
            // Show the "Mark as graded" toggle for feedback-only activities.
            if (markGradedSection) {
                markGradedSection.classList.remove('d-none');
                const toggle = this.getElement(this.selectors.MARK_GRADED_TOGGLE);
                if (toggle) {
                    toggle.checked = state.grade?.grade !== null
                        && state.grade?.grade !== undefined
                        && state.grade.grade >= 0;
                }
            }
            // Still render feedback content and expand the section.
            this._renderFeedbackAndSnapshot(state, true, isFreshRender);
            return;
        }
        // Hide the toggle when grading is enabled.
        if (markGradedSection) {
            markGradedSection.classList.add('d-none');
        }
        if (gradeSection) {
            gradeSection.classList.remove('d-none');
        }

        // Render advanced grading first — _renderRubric/_renderGuide call
        // _updateRubricTotal/_updateGuideTotal which sync a computed total
        // into the grade input. We then overwrite with the server-authoritative
        // grade value so manual overrides are not lost.
        //
        // For quiz manual grading the guide shows only the manually-marked
        // questions, so _updateGuideTotal computes the grade as a delta on the
        // full quiz grade held in _quizBaseGrade. That base must reflect the
        // CURRENT student here — before _renderAdvancedGrading computes the guide
        // total and the override indicator below — otherwise both are derived
        // from the previous student's grade, producing the spurious "Overridden"
        // badge and stale "Rubric total" teachers reported on quizzes.
        if (state.activity?.type === 'quiz' && state.grade) {
            if (state.grade.grade === null) {
                this._quizBaseGrade = 0;
            } else {
                const deduction = this._getTotalPenaltyDeduction(state);
                this._quizBaseGrade = deduction > 0
                    ? Math.round((parseFloat(state.grade.grade) + deduction) * 100) / 100
                    : parseFloat(state.grade.grade);
            }
        }
        this._renderAdvancedGrading(state, isFreshRender);

        const usescale = state.activity?.usescale || false;
        // Hide penalty UI for scale-based grading only.
        // Quizzes now show both the penalty button (for manual penalties) and the badge
        // container (for the quiz late penalty badge from the duedate plugin).
        const penaltyBtn = this.getElement(this.selectors.TOGGLE_PENALTIES);
        const badgesEl = this.getElement(this.selectors.PENALTY_BADGES);
        if (usescale) {
            if (penaltyBtn) {
                penaltyBtn.classList.add('d-none');
            }
            if (badgesEl) {
                badgesEl.classList.add('d-none');
            }
        } else {
            if (penaltyBtn) {
                penaltyBtn.classList.remove('d-none');
            }
            if (badgesEl) {
                badgesEl.classList.remove('d-none');
            }
        }

        if (usescale) {
            // Scale: set the dropdown value only on a fresh render (student
            // switch / initial load). Post-save refreshes leave the teacher's
            // selection alone.
            const scaleInput = this.getElement(this.selectors.SCALE_INPUT);
            if (scaleInput && state.grade) {
                // Same "fill a blank, untouched field from the server" recovery
                // as the numeric input below — covers the first-student-blank
                // case for scale-graded activities too.
                const fillBlank = !isFreshRender
                    && scaleInput.value === ''
                    && !DirtyTracker.isDirty('grade')
                    && state.grade.grade !== null;
                if (isFreshRender || fillBlank) {
                    scaleInput.value = state.grade.grade !== null ? String(Math.round(state.grade.grade)) : '';
                }
            }
        } else {
            // Points: set the numeric input value on a fresh render only.
            // Forums store the raw (teacher-given) grade — display as-is.
            // Other activities store the post-penalty grade — reverse-calculate for display.
            const gradeInput = this.getElement(this.selectors.GRADE_INPUT);
            if (gradeInput && state.grade) {
                let displayGrade = state.grade.grade;
                if (displayGrade !== null && state.activity?.type !== 'forum') {
                    const totalDeduction = this._getTotalPenaltyDeduction(state);
                    if (totalDeduction > 0) {
                        displayGrade = parseFloat(displayGrade) + totalDeduction;
                        // Round to avoid floating point artifacts.
                        displayGrade = Math.round(displayGrade * 100) / 100;
                    }
                }
                // Write the saved grade on a fresh render (student / attempt
                // switch). Also fill it when the input is blank and the teacher
                // hasn't touched it: this recovers the "first student shows blank
                // on open until you navigate away and back" case, where the
                // initial server load lands but the navigation-boundary gate has
                // already been consumed for this student, so this render counts
                // as non-fresh and the value write is skipped. Safe because it
                // only ever fills an EMPTY, CLEAN input — a value the teacher has
                // typed (dirty) or any existing value is never overwritten here,
                // so it cannot reintroduce the edit-clobbering the gate prevents.
                const fillBlankFromServer = !isFreshRender
                    && gradeInput.value === ''
                    && !DirtyTracker.isDirty('grade')
                    && displayGrade !== null;
                if (isFreshRender || fillBlankFromServer) {
                    gradeInput.value = displayGrade !== null ? String(displayGrade) : '';
                }
                if (isFreshRender) {
                    // If the saved grade differs from what the rubric/guide
                    // would compute, this is a returning teacher's prior
                    // override — restore the locked state and surface the
                    // indicator badge so the discrepancy is visible.
                    if (this._gradingDefinition && this._lastRubricGrade !== null
                            && displayGrade !== null
                            && Math.abs(parseFloat(displayGrade) - this._lastRubricGrade) > 0.005) {
                        this._gradeManuallyOverridden = true;
                    }
                    this._updateOverrideIndicator(this._lastRubricGrade);
                }

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

        // Render feedback, expand/collapse, late penalty badge, and dirty-tracking snapshot.
        // When there is no rubric/marking guide, always expand feedback by default.
        const hasAdvancedGradingSection = this._gradingDefinition !== null;
        this._renderFeedbackAndSnapshot(state, !hasAdvancedGradingSection, isFreshRender);
    }

    /**
     * Render feedback content, expand/collapse the section, show the late
     * penalty badge, and snapshot clean values for dirty tracking.
     *
     * Extracted so that both the normal grading path and the grading-disabled
     * early-return path can reuse the same logic.
     *
     * @param {object} state Current state.
     * @param {boolean} forceExpand Always expand the feedback section (e.g. when no rubric/guide).
     * @param {boolean} isFreshRender Whether this is a navigation-boundary render — only then is
     *                                it safe to overwrite the TinyMCE editor body. Post-save and
     *                                penalty/extension refreshes leave the editor alone.
     */
    _renderFeedbackAndSnapshot(state, forceExpand = false, isFreshRender = false) {
        // Use draft-ready content (with rewritten file URLs) when available.
        // Only push content into the editor on a fresh render — otherwise an
        // in-progress edit would be replaced with whatever the server last
        // saw, which is the bug class teachers were repeatedly hitting.
        if (isFreshRender) {
            if (state.grade && state.grade.feedbackdraft !== undefined) {
                this._updateFeedbackContent(state.grade.feedbackdraft, true);
            } else if (state.grade) {
                this._updateFeedbackContent(state.grade.feedback || '', true);
            }
        }

        // Toggle feedback display/edit mode.
        // Reset editing flag — _renderGrade fires on student switch and after save.
        if (isFreshRender) {
            this._editingFeedback = false;
        } else if (this._collapseFeedbackAfterSave && !this._isFeedbackEditorFocused()) {
            // A save the teacher started while re-editing existing feedback has
            // landed (state now holds the freshly-saved feedback) and they are
            // no longer typing — collapse the editor back to the read-only
            // "saved" card so a re-edit gets the same confirmation a first-time
            // save does. Without this the editor stayed open and the save looked
            // like it had failed. The focus guard keeps the editor open if a
            // background save (e.g. a debounced rubric save) lands while the
            // teacher is still typing feedback.
            this._editingFeedback = false;
        }
        // One-shot — consumed by the first render after the save dispatch.
        this._collapseFeedbackAfterSave = false;
        this._toggleFeedbackMode(state);

        // Auto-expand the overall feedback section:
        // - Always expanded when no rubric/marking guide is present (forceExpand).
        // - Otherwise expanded only if feedback content already exists.
        const feedbackHtml = state.grade?.feedbackdraft || state.grade?.feedback || '';
        this._setFeedbackSectionExpanded(forceExpand || this._hasMeaningfulFeedback(feedbackHtml));

        // Show late penalty badge extracted from external module feedback (e.g. quizaccess_duedate).
        this._renderLatePenaltyBadge(state);

        // Snapshot the "clean" values for dirty tracking after server data is loaded.
        const gradeInputEl = this.getElement(this.selectors.GRADE_INPUT);
        const scaleInputEl = this.getElement(this.selectors.SCALE_INPUT);
        const currentGradeValue = gradeInputEl ? gradeInputEl.value : (scaleInputEl ? scaleInputEl.value : '');
        DirtyTracker.setSnapshot('grade', currentGradeValue);
        DirtyTracker.setSnapshot('feedback', this._getEditorContent());
        DirtyTracker.markClean('grade');
        DirtyTracker.markClean('feedback');
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
                // Trust boundary: feedbackHtml is TinyMCE editor output processed through
                // format_text() server-side. innerHTML is intentional to preserve rich formatting.
                displayContent.innerHTML = feedbackHtml;
                // Force browsers to initialise media elements created via innerHTML.
                displayContent.querySelectorAll('audio, video').forEach(el => el.load());
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
     * @return {boolean} True if there is non-empty text or multimedia content.
     */
    _hasMeaningfulFeedback(html) {
        if (!html) {
            return false;
        }
        // Multimedia elements (audio/video recordings, images, embeds) count as meaningful.
        if (/<(audio|video|img|object|embed|iframe)\b/i.test(html)) {
            return true;
        }
        // Strip HTML tags and check for non-whitespace content.
        const text = html.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim();
        return text.length > 0;
    }

    /**
     * Whether the TinyMCE overall-feedback editor currently has focus. Used to
     * avoid collapsing the editor to the saved card while the teacher is still
     * typing (e.g. a background rubric save lands mid-edit).
     *
     * @return {boolean} True when the feedback editor is focused.
     */
    _isFeedbackEditorFocused() {
        const textarea = this.getElement(this.selectors.FEEDBACK_INPUT);
        if (!textarea) {
            return false;
        }
        const editor = getInstanceForElementId(textarea.id);
        return !!(editor && editor.hasFocus());
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
        // Force the update: this is an explicit user action (Edit button), not
        // a passive state refresh; we WANT the editor populated.
        const state = this.reactive.state;
        const feedbackHtml = state.grade?.feedbackdraft || state.grade?.feedback || '';
        this._updateFeedbackContent(feedbackHtml, true);

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

        // Clear the editor content and save with empty feedback. Force the
        // update: an explicit Delete action should always wipe the editor.
        this._updateFeedbackContent('', true);
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

                // Save cycle complete — allow future saves.
                this._saveInFlight = false;

                // When saving transitions to false, the save completed — mark clean.
                const gradeInputEl = this.getElement(this.selectors.GRADE_INPUT);
                const scaleInputEl = this.getElement(this.selectors.SCALE_INPUT);
                const currentGradeValue = gradeInputEl ? gradeInputEl.value
                    : (scaleInputEl ? scaleInputEl.value : '');
                DirtyTracker.setSnapshot('grade', currentGradeValue);
                DirtyTracker.setSnapshot('feedback', this._getEditorContent());
                DirtyTracker.markClean('grade');
                DirtyTracker.markClean('feedback');

                // Clear the IndexedDB cache after successful server save.
                const cmid = state.activity?.cmid;
                const userid = state.currentUser?.id;
                if (cmid && userid) {
                    OfflineCache.remove(cmid, userid, 'grade');
                    OfflineCache.remove(cmid, userid, 'feedback');
                }
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
     * Validate the manual grade input against the activity's maxgrade. Shows
     * an inline error and marks the input invalid when the entered value
     * exceeds the cap; clears the error state otherwise. Skipped for
     * scale-based grading (the dropdown can't produce an out-of-range value)
     * and when there is no numeric maxgrade.
     *
     * Returns true when the current value is acceptable, false when it
     * exceeds the cap. Callers use the return value to refuse save attempts.
     *
     * @return {boolean} Whether the grade is within the allowed range.
     */
    _validateGrade() {
        const gradeInput = this.getElement(this.selectors.GRADE_INPUT);
        const errorEl = this.getElement(this.selectors.GRADE_ERROR);
        if (!gradeInput) {
            return true;
        }
        // Scale-based grading uses the dropdown — nothing to validate here.
        if (this.reactive.state.activity?.usescale) {
            return true;
        }
        const maxgrade = parseFloat(gradeInput.max);
        if (!maxgrade || isNaN(maxgrade)) {
            return true;
        }
        const raw = parseFloat(gradeInput.value);
        // Empty input is fine — it means "no grade entered yet".
        if (isNaN(raw)) {
            gradeInput.classList.remove('is-invalid');
            if (errorEl) {
                errorEl.classList.add('d-none');
                errorEl.textContent = '';
            }
            return true;
        }
        if (raw > maxgrade) {
            gradeInput.classList.add('is-invalid');
            if (errorEl) {
                errorEl.classList.remove('d-none');
                // Re-fetch the localised string each call so a future change
                // to maxgrade picks up the new number — the string includes
                // {$a} for the cap.
                getString('error_grade_exceeds_max', 'local_unifiedgrader', maxgrade)
                    .then((s) => {
                        errorEl.textContent = s;
                        return s;
                    })
                    .catch(() => {
                        errorEl.textContent = 'Cannot exceed ' + maxgrade;
                    });
            }
            return false;
        }
        gradeInput.classList.remove('is-invalid');
        if (errorEl) {
            errorEl.classList.add('d-none');
            errorEl.textContent = '';
        }
        return true;
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

        // Include the external late penalty (quiz duedate plugin / assign penalty framework).
        const latePct = this._getLatePenaltyPct();

        // Calculate total deduction from our penalty table + external late penalty.
        const totalDeduction = this._getTotalPenaltyDeduction(this.reactive.state)
            + (latePct / 100) * maxgrade;

        // Hide if no penalties or no grade entered.
        if ((!penalties.length && !latePct) || isNaN(rawGrade) || rawGrade < 0) {
            displayEl.classList.add('d-none');
            return;
        }

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

        // Penalties are not applicable for scale-based grading.
        if (this.reactive.state.activity?.usescale) {
            return;
        }

        const penalties = this._getPenaltiesArray();
        badgesEl.innerHTML = '';

        penalties.forEach((p) => {
            const badge = document.createElement('span');
            if (p.category === 'late') {
                // Late penalties get a red badge — they are auto-managed and not editable.
                badge.className = 'badge bg-danger local-unifiedgrader-penalty-badge';
                badge.textContent = '-' + p.percentage + '% ' + (p.label || 'Late');
                getString('penalty_late_label', 'local_unifiedgrader').then((s) => {
                    badge.textContent = '-' + p.percentage + '% ' + (p.label || s);
                });
            } else {
                badge.className = 'badge bg-warning text-dark local-unifiedgrader-penalty-badge';
                if (p.category === 'wordcount') {
                    badge.textContent = '-' + p.percentage + '% …';
                    getString('penalty_wordcount', 'local_unifiedgrader').then((s) => {
                        badge.textContent = '-' + p.percentage + '% ' + s;
                    });
                } else {
                    badge.textContent = '-' + p.percentage + '% ' + (p.label || 'Other');
                    if (!p.label) {
                        getString('penalty_other', 'local_unifiedgrader').then((s) => {
                            badge.textContent = '-' + p.percentage + '% ' + s;
                        });
                    }
                }
            }
            badgesEl.appendChild(badge);
        });

        // Re-append the late penalty badge from external modules (e.g. quizaccess_duedate)
        // since innerHTML cleared it. This is separate from the 'late' category above.
        this._renderLatePenaltyBadge(this.reactive.state);

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
     * Wire up the academic-integrity referral controls.
     *
     * The whole section is server-gated on the refer capability, so all
     * elements are optional — bail quietly when the section is absent.
     */
    _initReferralControls() {
        const section = this.getElement(this.selectors.REFERRAL_SECTION);
        if (!section) {
            return;
        }

        const referBtn = this.getElement(this.selectors.REFER_BTN);
        const form = this.getElement(this.selectors.REFERRAL_FORM);
        const noteInput = this.getElement(this.selectors.REFERRAL_NOTE);
        const confirmBtn = this.getElement(this.selectors.REFERRAL_CONFIRM);
        const cancelBtn = this.getElement(this.selectors.REFERRAL_CANCEL);
        const resolveBtn = this.getElement(this.selectors.REFERRAL_RESOLVE);

        // Remember the grade-pane home so the control can be moved back when a
        // student has no plagiarism links (or the report form is disabled), and
        // capture the standalone button label for the same case. Guard the
        // capture so it records the true home even if _renderPlagiarism has
        // already relocated the node into the plagiarism actions host.
        const actions = this.getElement(this.selectors.PLAGIARISM_ACTIONS);
        if (!this._referralHomeParent && (!actions || section.parentNode !== actions)) {
            this._referralHomeParent = section.parentNode;
        }
        const labelEl = this.getElement(this.selectors.REFER_LABEL);
        if (labelEl) {
            this._referLabel = labelEl.textContent;
        }

        if (referBtn && form) {
            referBtn.addEventListener('click', (e) => {
                e.preventDefault();
                // In the plagiarism pane, when a report form is configured, this
                // is the single combined button: one click flags the submission
                // AND opens the report form, guarded by a confirm so an
                // accidental click can be cancelled. Without a report form (or in
                // its grade-pane home) it keeps the inline note → confirm flow so
                // a reason can still be recorded.
                const st = this.reactive.state;
                const formEnabled = !!(st.ui.enableReportForm && st.ui.reportFormUrl);
                if (this._referralHosted === 'plagiarism' && formEnabled) {
                    this._handleCombinedReferral();
                    return;
                }
                form.classList.remove('d-none');
                referBtn.classList.add('d-none');
                if (noteInput) {
                    noteInput.value = '';
                    noteInput.focus();
                }
            });
        }

        if (cancelBtn && form && referBtn) {
            cancelBtn.addEventListener('click', (e) => {
                e.preventDefault();
                form.classList.add('d-none');
                referBtn.classList.remove('d-none');
            });
        }

        if (confirmBtn) {
            confirmBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const state = this.reactive.state;
                const note = noteInput ? noteInput.value.trim() : '';
                // Set the review flag (pauses the grading-turnaround clock).
                this.reactive.dispatch('refer', state.activity.cmid, state.currentUser.id, note);
                // The referral does not replace the academic-impropriety report
                // form: when that form is enabled, also send the teacher to it with
                // the case details prefilled. Opened synchronously within the click
                // gesture (and before the async dispatch resolves) so it is not
                // treated as a blocked pop-up.
                if (state.ui.enableReportForm && state.ui.reportFormUrl) {
                    window.open(this._buildReportUrl(state), '_blank', 'noopener');
                }
            });
        }

        if (resolveBtn) {
            resolveBtn.addEventListener('click', (e) => {
                e.preventDefault();
                const open = this._getOpenReferral();
                if (!open) {
                    return;
                }
                const state = this.reactive.state;
                this.reactive.dispatch(
                    'resolveReferral',
                    state.activity.cmid,
                    state.currentUser.id,
                    open.id,
                    'cleared',
                );
            });
        }
    }

    /**
     * Render the integrity-referral control based on current referral state.
     *
     * Shows the "Mark resolved" status when an open referral exists, otherwise
     * the "Refer for integrity review" button.
     */
    _renderReferral() {
        const section = this.getElement(this.selectors.REFERRAL_SECTION);
        if (!section) {
            return;
        }

        const referBtn = this.getElement(this.selectors.REFER_BTN);
        const form = this.getElement(this.selectors.REFERRAL_FORM);
        const status = this.getElement(this.selectors.REFERRAL_STATUS);
        const open = this._getOpenReferral();

        // Collapse the inline note form on every state change.
        if (form) {
            form.classList.add('d-none');
        }

        if (open) {
            if (referBtn) {
                referBtn.classList.add('d-none');
            }
            if (status) {
                status.classList.remove('d-none');
            }
        } else {
            if (referBtn) {
                referBtn.classList.remove('d-none');
            }
            if (status) {
                status.classList.add('d-none');
            }
        }
    }

    /**
     * Combined academic-integrity action, used when the referral control is
     * hosted in the plagiarism pane: a single click flags the submission for
     * review (pausing the turnaround clock) AND opens the prefilled report
     * form. A confirm() guards against an accidental click so the teacher can
     * cancel before anything happens.
     *
     * Kept fully synchronous (the confirm string is pre-fetched in create())
     * so the window.open() for the report form runs inside the click gesture
     * and is not treated as a blocked pop-up.
     */
    _handleCombinedReferral() {
        if (!window.confirm(this._referConfirmMsg)) {
            return;
        }
        const state = this.reactive.state;
        // Flag for review. No inline note here — the report form captures the
        // case details, and the open-referral status still offers "Mark
        // resolved" as a follow-up undo.
        this.reactive.dispatch('refer', state.activity.cmid, state.currentUser.id, '');
        if (state.ui.enableReportForm && state.ui.reportFormUrl) {
            window.open(this._buildReportUrl(state), '_blank', 'noopener');
        }
    }

    /**
     * Move the academic-integrity referral control between its grade-pane home
     * and the plagiarism pane.
     *
     * When a submission has plagiarism links and the report form is enabled, a
     * single combined button is surfaced inside the plagiarism card so the
     * teacher sees one button (not a separate "report" link plus a "refer"
     * button). Otherwise the control lives in the grade pane with its
     * inline-note flow.
     *
     * The same DOM node is relocated (not re-created), so its wired listeners
     * and open/resolve lifecycle keep working in either location.
     *
     * @param {string} host Either 'plagiarism' or 'grade'.
     */
    _setReferralHost(host) {
        const section = this.getElement(this.selectors.REFERRAL_SECTION);
        if (!section) {
            // No refer capability — there is no control to relocate.
            return;
        }
        const actions = this.getElement(this.selectors.PLAGIARISM_ACTIONS);
        // Capture the original grade-pane parent the first time, before any move.
        if (!this._referralHomeParent && (!actions || section.parentNode !== actions)) {
            this._referralHomeParent = section.parentNode;
        }
        const labelEl = this.getElement(this.selectors.REFER_LABEL);
        const referBtn = this.getElement(this.selectors.REFER_BTN);

        if (host === 'plagiarism' && actions) {
            if (section.parentNode !== actions) {
                actions.appendChild(section);
            }
            this._referralHosted = 'plagiarism';
            // "Report academic impropriety" when a report form will open on
            // click; plain "Refer for integrity review" when there is no form
            // (the button only flags, via the inline-note flow).
            if (labelEl) {
                const st = this.reactive.state;
                const formEnabled = !!(st.ui.enableReportForm && st.ui.reportFormUrl);
                labelEl.textContent = formEnabled ? this._reportButtonLabel : this._referLabel;
            }
            // Full width to match the plagiarism card's report-button styling.
            if (referBtn) {
                referBtn.classList.add('w-100');
            }
        } else {
            if (this._referralHomeParent && section.parentNode !== this._referralHomeParent) {
                this._referralHomeParent.appendChild(section);
            }
            this._referralHosted = 'grade';
            if (labelEl) {
                labelEl.textContent = this._referLabel;
            }
            if (referBtn) {
                referBtn.classList.remove('w-100');
            }
        }
    }

    /**
     * Build the plain "report academic impropriety" link shown in the
     * plagiarism pane for teachers who can open the report form but lack the
     * refer capability (so they still get the form, just not the flag).
     *
     * @param {object} state Current reactive state.
     * @return {string} HTML for the report link.
     */
    _buildReportLinkHtml(state) {
        const reportUrl = this._buildReportUrl(state);
        return '<div class="mt-2 pt-2 border-top">'
            + '<a href="' + this._escapeHtml(reportUrl) + '" target="_blank" rel="noopener"'
            + ' class="btn btn-sm btn-outline-danger w-100">'
            + '<i class="fa fa-flag me-1"></i>'
            + this._escapeHtml(this._reportButtonLabel)
            + '</a></div>';
    }

    /**
     * Get referrals as a plain array from the reactive state (StateMap → Array).
     *
     * @return {Array} Array of referral objects.
     */
    _getReferralsArray() {
        const referralsState = this.reactive.state.referrals;
        if (!referralsState) {
            return [];
        }
        if (typeof referralsState.values === 'function') {
            return [...referralsState.values()];
        }
        if (Array.isArray(referralsState)) {
            return referralsState;
        }
        return [];
    }

    /**
     * Get the open referral for the current student, if any.
     *
     * @return {object|null}
     */
    _getOpenReferral() {
        return this._getReferralsArray().find((r) => r.status === 'open') || null;
    }

    /**
     * Show a read-only badge for late penalties applied externally.
     *
     * For quizzes: penalty from the quizaccess_duedate plugin.
     * For assignments: penalty from Moodle core's penalty framework (assign_grades.penalty).
     *
     * Uses the backend-provided latepenaltypct as the primary source.
     * Falls back to parsing the feedback text for the penalty format.
     *
     * @param {object} state Current state.
     */
    _renderLatePenaltyBadge(state) {
        const badgesEl = this.getElement(this.selectors.PENALTY_BADGES);
        if (!badgesEl) {
            return;
        }

        // Remove any existing late penalty badge before re-rendering.
        badgesEl.querySelectorAll('[data-penalty="late"]').forEach((el) => el.remove());

        // Not applicable for scale-based grading.
        if (state.activity?.usescale) {
            return;
        }

        const penaltyPct = this._getLatePenaltyPct();
        if (!penaltyPct) {
            return;
        }

        const badge = document.createElement('span');
        badge.className = 'badge bg-danger local-unifiedgrader-penalty-badge';
        badge.dataset.penalty = 'late';
        badge.textContent = '-' + penaltyPct + '% Late';
        badge.title = '';
        getString('penalty_late_label', 'local_unifiedgrader').then((s) => {
            badge.textContent = '-' + penaltyPct + '% ' + s;
        });
        getString('penalty_late_applied', 'local_unifiedgrader', penaltyPct).then((s) => {
            badge.title = s;
        });
        badgesEl.appendChild(badge);
    }

    /**
     * Get the late penalty percentage from the backend.
     *
     * For quizzes: calculated from the quizaccess_duedate plugin.
     * For assignments: read from Moodle core's penalty framework (assign_grades.penalty).
     *
     * Checks latepenaltypct from the backend first, then falls back to
     * parsing the gradebook feedback text for the penalty format.
     *
     * @return {number} Penalty percentage (0 if none).
     */
    _getLatePenaltyPct() {
        const state = this.reactive.state;

        // If the submission is no longer late (e.g. extension granted after submission),
        // suppress the penalty badge even if assign_grades.penalty is still set.
        // Use the canonical submittedat so this matches what the late
        // indicator badge above and the server-side islate flag agree on.
        const duedate = state.submission?.effectiveduedate || state.activity?.duedate || 0;
        const submitted = state.submission?.submittedat
            || state.submission?.timemodified
            || state.submission?.timecreated
            || 0;
        if (duedate && submitted && submitted <= duedate) {
            return 0;
        }

        let pct = parseInt(state.grade?.latepenaltypct, 10) || 0;
        if (!pct) {
            const feedback = state.grade?.feedback || '';
            if (feedback) {
                const match = feedback.match(/Late penalty of (\d+)% applied/i);
                if (match) {
                    pct = parseInt(match[1], 10);
                }
            }
        }
        return pct;
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
     * @param {boolean} isFreshRender Whether the active student just changed (or this is the
     *                                initial render). Only then do we apply server-side fill
     *                                values to existing inputs; otherwise the rubric/guide DOM
     *                                is left untouched so in-progress edits survive saves.
     */
    _renderAdvancedGrading(state, isFreshRender = false) {
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

        // Set title. Quizzes don't use a rubric/marking guide as such — the
        // pane just lists the manually-marked questions — so label it plainly
        // for quizmanual rather than the misleading "Marking guide".
        const titleEl = this.getElement(this.selectors.RUBRIC_TITLE);
        if (titleEl) {
            const titlekey = definition.method === 'rubric'
                ? 'rubric'
                : (definition.method === 'quizmanual' ? 'manuallymarkedquestions' : 'markingguide');
            const titlefallback = definition.method === 'rubric'
                ? 'Rubric'
                : (definition.method === 'quizmanual' ? 'Manually marked questions' : 'Marking guide');
            getString(titlekey, 'local_unifiedgrader')
                .then((s) => {
                    titleEl.textContent = s;
                    return s;
                })
                .catch(() => {
                    titleEl.textContent = titlefallback;
                });
        }

        // Render based on method.
        if (definition.method === 'rubric') {
            this._renderRubric(definition, fillData, isFreshRender);
        } else if (definition.method === 'guide' || definition.method === 'quizmanual') {
            this._renderGuide(definition, fillData, isFreshRender);
        }

        section.classList.remove('d-none');
        this._suppressAutoSave = false;
    }

    /**
     * Render a rubric with selectable levels.
     *
     * @param {object} definition Grading definition.
     * @param {object} fillData Current fill data.
     * @param {boolean} isFreshRender Initial render or student switch — only then are server-side
     *                                selections applied to the DOM. Other render triggers leave
     *                                already-rendered selection buttons alone.
     */
    _renderRubric(definition, fillData, isFreshRender = false) {
        const body = this.getElement(this.selectors.RUBRIC_BODY);
        if (!body) {
            return;
        }

        // Build a map of current selections from fill data.
        const currentFill = {};
        if (fillData?.criteria) {
            for (const [critId, critData] of Object.entries(fillData.criteria)) {
                if (critData.levelid) {
                    currentFill[critId] = parseInt(critData.levelid, 10);
                }
            }
        }

        // If the criterion set already matches the DOM, do nothing on non-fresh
        // renders. The teacher's selection lives in _rubricSelections and in
        // the DOM; the server view will catch up on the next autosave.
        const existingButtons = body.querySelectorAll('button[data-criterionid][data-levelid]');
        if (existingButtons.length > 0) {
            const existingCriterionIds = new Set(
                Array.from(existingButtons).map((el) => String(el.dataset.criterionid)),
            );
            const newCriterionIds = new Set(definition.criteria.map((c) => String(c.id)));
            const sameStructure = existingCriterionIds.size === newCriterionIds.size
                && [...existingCriterionIds].every((id) => newCriterionIds.has(id));
            if (sameStructure) {
                if (isFreshRender) {
                    this._updateRubricSelections(body, definition, currentFill);
                }
                return;
            }
        }

        // Full rebuild path.
        body.innerHTML = '';
        this._rubricSelections = {};

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
            getString('clib_title', 'local_unifiedgrader').then((s) => {
                clibBtn.title = s;
                return s;
            }).catch(() => {});
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
        DirtyTracker.markDirty('grade');
        this._debouncedAutoSave();
    }

    /**
     * Reconcile rubric level selections after a state refresh, without
     * rebuilding the level-button DOM. For each criterion the server-returned
     * selection is applied unless the in-memory selection differs from what
     * we last sent — in which case the teacher has clicked since the save
     * fired and we keep their selection. Mirrors _updateGuideValues.
     *
     * @param {HTMLElement} body The rubric body container.
     * @param {object} definition Grading definition.
     * @param {object} currentFill Map of criterionid → server-selected levelid.
     */
    _updateRubricSelections(body, definition, currentFill) {
        // Only called on a fresh render (student switch / initial load), so
        // it's safe to overwrite the DOM with the server-side selections.
        definition.criteria.forEach((criterion) => {
            const id = criterion.id;
            const idstr = String(id);
            const serverLevelId = currentFill[id] ?? null;
            let selection = null;
            if (serverLevelId !== null) {
                const lvl = criterion.levels.find((l) => l.id === serverLevelId);
                if (lvl) {
                    selection = {levelid: lvl.id, score: lvl.score};
                }
            }
            if (selection) {
                this._rubricSelections[id] = selection;
            } else {
                delete this._rubricSelections[id];
            }
            const buttons = body.querySelectorAll(
                'button[data-criterionid="' + idstr + '"][data-levelid]',
            );
            buttons.forEach((btn) => {
                const isSelected = selection
                    && parseInt(btn.dataset.levelid, 10) === selection.levelid;
                btn.className = 'btn btn-sm text-start p-2 border '
                    + (isSelected ? 'btn-primary' : 'btn-outline-secondary');
            });
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

        // The rubric's raw maximum is the sum of each criterion's highest level
        // score. Moodle scales that raw score onto the gradebook item's max
        // (a rubric out of 4 on an activity out of 100 → 4/4 maps to 100), so we
        // must scale here too. Without it a fully-selected rubric wrote its raw
        // total (e.g. 4) straight into the /100 grade field, reading as 4%
        // instead of 100%.
        const maxTotal = (this._gradingDefinition?.criteria || []).reduce((sum, c) => {
            const scores = (c.levels || []).map(l => parseFloat(l.score) || 0);
            return sum + (scores.length ? Math.max(...scores) : 0);
        }, 0);

        // Sync the scaled total into the simple grade input and update the
        // percentage — unless the teacher has manually overridden the grade, in
        // which case the rubric becomes a reference total only. Mirrors the
        // marking-guide path in _updateGuideTotal (including the override badge).
        const gradeInput = this.getElement(this.selectors.GRADE_INPUT);
        if (gradeInput) {
            const rubricGrade = this._computeRubricGrade(total, maxTotal, gradeInput);
            this._lastRubricGrade = rubricGrade;
            if (!this._gradeManuallyOverridden) {
                gradeInput.value = String(rubricGrade);
            }
            this._updateOverrideIndicator(rubricGrade);
        }
        this._updatePercentage();
    }

    /**
     * Render a marking guide with score inputs and remarks.
     *
     * @param {object} definition Grading definition.
     * @param {object} fillData Current fill data.
     * @param {boolean} isFreshRender Initial render or student switch — only then are server-side
     *                                fill values applied to the DOM. Other render triggers
     *                                (post-save, penalty save, extension grant) leave the
     *                                already-rendered inputs alone so in-progress edits survive.
     */
    _renderGuide(definition, fillData, isFreshRender = false) {
        const body = this.getElement(this.selectors.RUBRIC_BODY);
        if (!body) {
            return;
        }

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

        // If the criterion set already matches the DOM, do nothing on non-fresh
        // renders. The teacher's in-flight edits live in the DOM (and in
        // _guideScores/_guideRemarks); the server view will catch up on the
        // next autosave. On a fresh render we apply the server-side fill.
        // Score inputs are type="text" (with inputmode="decimal") so we can
        // accept locale-mismatched decimal separators — see _renderGuide.
        const existingInputs = body.querySelectorAll('input[data-criterionid]:not([data-levelid])');
        if (existingInputs.length > 0) {
            const existingIds = new Set(
                Array.from(existingInputs).map((el) => String(el.dataset.criterionid)),
            );
            const newIds = new Set(definition.criteria.map((c) => String(c.id)));
            const sameStructure = existingIds.size === newIds.size
                && [...existingIds].every((id) => newIds.has(id));
            if (sameStructure) {
                if (isFreshRender) {
                    this._updateGuideValues(body, definition, currentFill);
                }
                return;
            }
        }

        // Full rebuild path (first render, or definition changed).
        body.innerHTML = '';
        this._guideScores = {};
        this._guideRemarks = {};

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
            getString('maxgrade_prefix', 'local_unifiedgrader').then((s) => {
                maxEl.textContent = s + criterion.maxscore;
                return s;
            }).catch(() => {});

            header.appendChild(nameEl);
            header.appendChild(maxEl);
            row.appendChild(header);

            // Trust boundary: descriptionmarkers is sanitized server-side via format_text()
            // in the adapter. innerHTML is intentional to preserve rich formatting.
            if (criterion.descriptionmarkers) {
                const markerBox = document.createElement('div');
                markerBox.className = 'small mb-2 p-2 rounded';
                markerBox.style.backgroundColor = 'var(--bs-info-bg-subtle, #cff4fc)';
                markerBox.style.border = '1px solid var(--bs-info-border-subtle, #9eeaf9)';

                const markerLabel = document.createElement('div');
                markerLabel.className = 'fw-bold mb-1';
                const markerIcon = document.createElement('i');
                markerIcon.className = 'fa fa-info-circle me-1';
                markerIcon.setAttribute('aria-hidden', 'true');
                markerLabel.appendChild(markerIcon);
                getString('informationforgraders', 'local_unifiedgrader').then((s) => {
                    markerLabel.appendChild(document.createTextNode(s));
                    return s;
                }).catch(() => {
                    markerLabel.appendChild(document.createTextNode('Information for graders'));
                });
                markerBox.appendChild(markerLabel);

                const markerContent = document.createElement('div');
                markerContent.innerHTML = criterion.descriptionmarkers;
                markerBox.appendChild(markerContent);

                row.appendChild(markerBox);
            }

            // Score input + remark row.
            const controls = document.createElement('div');
            controls.className = 'd-flex gap-2 align-items-start';

            // Use type="text" + inputmode="decimal" rather than type="number"
            // because the latter rejects locale-mismatched decimal separators:
            // a teacher in a comma-locale (de/fr/es/…) typing "3.5" sees the
            // characters appear but `input.value` returns "" because the browser
            // refuses to accept a period as the decimal mark. That empty string
            // then gets persisted to the gradingform_guide_fillings.score column
            // as 0 (the column is NOT NULL number). The user-visible symptom is
            // "I typed 3.5, refresh, mark is 0." Capturing the raw text instead
            // and normalising comma → period on the way to the server avoids
            // the whole locale dance.
            const scoreInput = document.createElement('input');
            scoreInput.type = 'text';
            scoreInput.inputMode = 'decimal';
            scoreInput.autocomplete = 'off';
            scoreInput.className = 'form-control form-control-sm text-end';
            scoreInput.style.width = '80px';
            scoreInput.dataset.min = '0';
            scoreInput.dataset.max = String(criterion.maxscore);
            scoreInput.placeholder = 'Score';
            getString('score', 'local_unifiedgrader').then((s) => {
                scoreInput.placeholder = s;
                return s;
            }).catch(() => {});
            scoreInput.value = currentFill[criterion.id]?.score ?? '';
            scoreInput.dataset.criterionid = criterion.id;

            // Disable score input for zero-mark questions (e.g. information-only
            // essay items) — grading would cause a division by zero in the
            // question engine. Comments can still be entered.
            if (criterion.maxscore === 0) {
                scoreInput.disabled = true;
                scoreInput.value = '0';
                scoreInput.title = 'No marks available for this question';
                scoreInput.style.opacity = '0.5';
            }

            this._guideScores[criterion.id] = scoreInput.value;

            // Per-criterion validation message element. Hidden until the
            // teacher enters a score that's out of range (negative or above
            // the criterion's allocated maximum). Placed below the controls
            // row so it doesn't disturb the score+remark layout.
            const scoreError = document.createElement('div');
            scoreError.className = 'small text-danger mt-1 d-none';
            scoreError.dataset.scoreError = String(criterion.id);
            scoreError.setAttribute('role', 'alert');

            scoreInput.addEventListener('input', () => {
                // Canonicalise the decimal separator: accept either "3.5" or
                // "3,5" regardless of browser locale, store the period form.
                const canonical = (scoreInput.value || '').replace(',', '.');
                this._guideScores[criterion.id] = canonical;
                this._validateGuideScore(criterion, scoreInput, scoreError);
                this._updateGuideTotal();
            });

            const remarkInput = document.createElement('textarea');
            remarkInput.rows = 3;
            remarkInput.className = 'form-control form-control-sm flex-grow-1';
            remarkInput.placeholder = 'Remark';
            getString('remark', 'local_unifiedgrader').then((s) => {
                remarkInput.placeholder = s;
                return s;
            }).catch(() => {});
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
            getString('clib_title', 'local_unifiedgrader').then((s) => {
                clibBtn.title = s;
                return s;
            }).catch(() => {});
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
            row.appendChild(scoreError);

            // Validate any pre-filled value loaded from the server — flags
            // anomalies introduced by older grading paths that didn't cap.
            this._validateGuideScore(criterion, scoreInput, scoreError);

            // Attach autocomplete from comment library to the remark textarea.
            this._attachAutocomplete(remarkInput);

            body.appendChild(row);
        });

        // Capture the quizmanual base total BEFORE _updateGuideTotal so its
        // delta (current total − base) is zero at render — see the helper.
        this._recomputeGuideBaseTotal();
        this._updateGuideTotal();
    }

    /**
     * Apply server-side fill values to existing marking-guide inputs without
     * rebuilding the DOM. Only ever called on a fresh render (student switch
     * or initial load); other render triggers skip the update entirely so
     * in-progress edits survive.
     *
     * @param {HTMLElement} body The rubric body container.
     * @param {object} definition Grading definition (criteria list).
     * @param {object} currentFill Fill map keyed by criterion id.
     */
    _updateGuideValues(body, definition, currentFill) {
        definition.criteria.forEach((criterion) => {
            const id = String(criterion.id);
            const scoreInput = body.querySelector(
                'input[data-criterionid="' + id + '"]:not([data-levelid])',
            );
            const remarkInput = body.querySelector(
                'textarea[data-criterionid="' + id + '"]',
            );
            const newScore = String(currentFill[id]?.score ?? '');
            const newRemark = String(currentFill[id]?.remark ?? '');

            if (scoreInput) {
                scoreInput.value = newScore;
                this._guideScores[id] = newScore;
                const errorEl = body.querySelector('[data-score-error="' + id + '"]');
                this._validateGuideScore(criterion, scoreInput, errorEl);
            }
            if (remarkInput) {
                remarkInput.value = newRemark;
                this._guideRemarks[id] = newRemark;
            }
        });
        // Recompute the quizmanual base total on this fill-only fast path too,
        // so navigating between students of the same quiz recalculates the grade
        // without a hard refresh (the full-rebuild path in _renderGuide does the
        // same before its _updateGuideTotal).
        this._recomputeGuideBaseTotal();
        this._updateGuideTotal();
    }

    /**
     * Recompute the quizmanual base total — the sum of the manually-marked
     * question scores as loaded — so _updateGuideTotal's delta (current total −
     * base total) is zero at render. Must run on every fresh render, via both
     * _renderGuide (full rebuild) and _updateGuideValues (the same-structure
     * fast path used when navigating between students of one quiz). No-op for
     * non-quizmanual grading methods.
     */
    _recomputeGuideBaseTotal() {
        if (this._gradingDefinition?.method !== 'quizmanual') {
            return;
        }
        let baseTotal = 0;
        for (const val of Object.values(this._guideScores)) {
            const num = parseFloat(val);
            if (!isNaN(num)) {
                baseTotal += num;
            }
        }
        this._guideBaseTotal = baseTotal;
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
        // Snap to the gradebook's display precision (floored at 2dp) so the
        // marking-guide total never surfaces classic JS floating-point
        // summing artifacts (e.g. 4 + 3.8 + 4.1 + 2 → 13.899999999999999).
        // The floor at 2dp ensures fractional rubric scores don't get
        // silently swallowed even when the gradebook itself is set to
        // 0 decimal places.
        total = this._roundToGradePrecision(total);

        const maxTotal = this._gradingDefinition?.criteria?.reduce(
            (sum, c) => sum + (c.maxscore || 0), 0
        ) || 0;

        if (totalEl) {
            totalEl.textContent = total + ' / ' + maxTotal;
        }

        // Sync total into the simple grade input and update percentage —
        // unless the teacher has manually edited the grade input. Once
        // they have, the rubric becomes a reference total only; their
        // override survives further rubric tweaks. Cleared on student switch.
        const gradeInput = this.getElement(this.selectors.GRADE_INPUT);
        if (gradeInput) {
            const rubricGrade = this._computeRubricGrade(total, maxTotal, gradeInput);
            this._lastRubricGrade = rubricGrade;
            if (!this._gradeManuallyOverridden) {
                gradeInput.value = String(rubricGrade);
            }
            this._updateOverrideIndicator(rubricGrade);
        }
        this._updatePercentage();
    }

    /**
     * Validate a single marking-guide criterion score against its allocated
     * maximum and flag the input + error message inline. Used by both the
     * per-keystroke listener and the initial render to surface anomalies on
     * server-supplied fills.
     *
     * Returns true when the score is acceptable (empty, or a number in the
     * inclusive range [0, criterion.maxscore]); false otherwise.
     *
     * @param {object} criterion The criterion descriptor (id, maxscore, shortname).
     * @param {HTMLInputElement} scoreInput The score input element.
     * @param {HTMLElement} errorEl The hidden error message container for this row.
     * @return {boolean} Whether the score is within range.
     */
    _validateGuideScore(criterion, scoreInput, errorEl) {
        const maxscore = parseFloat(criterion.maxscore);
        // Information-only items (maxscore 0) are non-editable; nothing to validate.
        if (!isFinite(maxscore) || maxscore <= 0) {
            scoreInput.classList.remove('is-invalid');
            if (errorEl) {
                errorEl.classList.add('d-none');
                errorEl.textContent = '';
            }
            return true;
        }
        const raw = (scoreInput.value || '').replace(',', '.').trim();
        if (raw === '') {
            scoreInput.classList.remove('is-invalid');
            if (errorEl) {
                errorEl.classList.add('d-none');
                errorEl.textContent = '';
            }
            return true;
        }
        // Number() (not parseFloat) so a partly-numeric value like "5abc" is
        // rejected rather than leniently parsed to 5 — otherwise the raw string
        // round-trips to the server and hits a numeric DB column.
        const num = Number(raw);
        if (isNaN(num) || num < 0 || num > maxscore) {
            scoreInput.classList.add('is-invalid');
            if (errorEl) {
                errorEl.classList.remove('d-none');
                const params = {
                    max: maxscore,
                    criterion: criterion.shortname || '',
                };
                getString('error_guide_score_out_of_range', 'local_unifiedgrader', params)
                    .then((s) => {
                        errorEl.textContent = s;
                        return s;
                    })
                    .catch(() => {
                        errorEl.textContent = 'Score must be between 0 and ' + maxscore + '.';
                    });
            }
            return false;
        }
        scoreInput.classList.remove('is-invalid');
        if (errorEl) {
            errorEl.classList.add('d-none');
            errorEl.textContent = '';
        }
        return true;
    }

    /**
     * Validate every marking-guide criterion score currently rendered.
     * Returns false if any score is out of range so the save path can
     * short-circuit. Visible errors are left in place for the teacher to fix.
     *
     * @return {boolean} Whether all guide scores are within range.
     */
    _validateAllGuideScores() {
        if (!this._gradingDefinition || !Array.isArray(this._gradingDefinition.criteria)) {
            return true;
        }
        const method = this._gradingDefinition.method;
        if (method !== 'guide' && method !== 'quizmanual') {
            return true;
        }
        const body = this.getElement(this.selectors.RUBRIC_BODY);
        if (!body) {
            return true;
        }
        let allOk = true;
        for (const criterion of this._gradingDefinition.criteria) {
            const scoreInput = body.querySelector(
                'input[data-criterionid="' + criterion.id + '"]:not([data-levelid])',
            );
            const errorEl = body.querySelector('[data-score-error="' + criterion.id + '"]');
            if (!scoreInput) {
                continue;
            }
            if (!this._validateGuideScore(criterion, scoreInput, errorEl)) {
                allOk = false;
            }
        }
        return allOk;
    }

    /**
     * Compute what the grade input should display from the rubric / marking
     * guide totals — i.e. the value that auto-sync would push. Pulled out
     * of _updateGuideTotal so the override indicator can compare without
     * re-deriving the math.
     *
     * @param {number} total Sum of criterion scores from _guideScores.
     * @param {number} maxTotal Sum of criterion maxscores.
     * @param {HTMLInputElement} gradeInput The grade input element (for its max attr).
     * @return {number} The rubric-implied grade on the activity's scale.
     */
    _computeRubricGrade(total, maxTotal, gradeInput) {
        if (this._gradingDefinition?.method === 'quizmanual' && this._quizBaseGrade !== undefined) {
            // For quiz manual grading, the guide only shows manually-graded
            // questions. The displayed grade is the full quiz grade adjusted
            // by the delta between current manual scores and the initial manual total.
            const delta = total - (this._guideBaseTotal ?? 0);
            return Math.max(0, this._roundToGradePrecision(this._quizBaseGrade + delta));
        }
        // Normalize the guide total to the assignment's grade scale.
        // A marking guide may have a different max total than the activity
        // max grade (e.g. guide criteria sum to 13 but assignment is out of 10).
        const activityMax = parseFloat(gradeInput.max) || 0;
        if (maxTotal > 0 && activityMax > 0 && maxTotal !== activityMax) {
            return this._roundToGradePrecision((total / maxTotal) * activityMax);
        }
        return total;
    }

    /**
     * Round a number to the gradebook's display precision for this activity,
     * floored at 2 decimal places. The floor exists because fractional
     * rubric scores (e.g. 3.8, 4.1) would be silently swallowed if the
     * gradebook were configured at 0dp — the marking-guide UI is an
     * interim calculation surface and should always preserve enough
     * precision to be useful.
     *
     * @param {number} value
     * @return {number}
     */
    _roundToGradePrecision(value) {
        const decimals = Math.max(2, parseInt(this._gradingDefinition?.decimalpoints ?? 2, 10));
        const factor = Math.pow(10, decimals);
        return Math.round(value * factor) / factor;
    }

    /**
     * Show or hide the "Override" badge depending on whether the displayed
     * grade matches what the rubric / marking guide would compute. Also
     * exposes the rubric value to the indicator so a returning teacher
     * can see at a glance both what they assigned and what the rubric says.
     *
     * Tolerates floating-point noise: a 0.005 difference counts as equal.
     *
     * @param {number} rubricGrade The auto-computed rubric grade.
     */
    _updateOverrideIndicator(rubricGrade) {
        const indicator = this.getElement(this.selectors.GRADE_OVERRIDE_INDICATOR);
        const gradeInput = this.getElement(this.selectors.GRADE_INPUT);
        if (!indicator || !gradeInput) {
            return;
        }
        // No rubric / guide active → no override concept; always hidden.
        if (!this._gradingDefinition) {
            indicator.classList.add('d-none');
            return;
        }
        const current = parseFloat(gradeInput.value);
        if (isNaN(current) || gradeInput.value === '') {
            indicator.classList.add('d-none');
            return;
        }
        const isOverridden = Math.abs(current - rubricGrade) > 0.005;
        if (isOverridden) {
            indicator.classList.remove('d-none');
            indicator.classList.add('d-flex');
            const rubricEl = this.getElement(this.selectors.GRADE_RUBRIC_VALUE);
            if (rubricEl) {
                rubricEl.textContent = String(rubricGrade);
            }
        } else {
            indicator.classList.add('d-none');
            indicator.classList.remove('d-flex');
        }
    }

    /**
     * Attach autocomplete from the comment library to a plain textarea.
     *
     * Shows a dropdown of matching library comments as the user types (min 2 chars).
     * Supports keyboard navigation (ArrowUp/Down, Enter, Escape) and mouse selection.
     *
     * @param {HTMLTextAreaElement} textarea The textarea element.
     */
    _attachAutocomplete(textarea) {
        const wrapper = document.createElement('div');
        wrapper.style.position = 'relative';
        // Transfer flex-grow from textarea to wrapper so it fills the available space.
        if (textarea.classList.contains('flex-grow-1')) {
            textarea.classList.remove('flex-grow-1');
            wrapper.classList.add('flex-grow-1');
        }
        textarea.parentNode.insertBefore(wrapper, textarea);
        wrapper.appendChild(textarea);

        const dropdown = document.createElement('div');
        dropdown.className = 'ug-autocomplete-dropdown';
        wrapper.appendChild(dropdown);

        let acIndex = -1;
        let acVisible = false;
        let currentMatches = [];

        const close = () => {
            dropdown.innerHTML = '';
            dropdown.style.display = 'none';
            acIndex = -1;
            acVisible = false;
            currentMatches = [];
        };

        const show = async(query) => {
            if (!query || query.length < 2) {
                close();
                return;
            }
            const comments = await this._clibPopout.getComments();
            const lower = query.toLowerCase();
            const matches = comments.filter(
                (c) => c.content.toLowerCase().includes(lower)
            ).slice(0, 6);

            if (matches.length === 0) {
                close();
                return;
            }

            currentMatches = matches;
            dropdown.innerHTML = '';
            acIndex = -1;
            acVisible = true;
            dropdown.style.display = 'block';

            matches.forEach((comment, idx) => {
                const item = document.createElement('div');
                item.className = 'ug-ac-item';
                item.dataset.index = idx;

                const contentLower = comment.content.toLowerCase();
                const matchStart = contentLower.indexOf(lower);
                const displayText = comment.content.length > 100
                    ? comment.content.substring(0, 100) + '...' : comment.content;
                if (matchStart >= 0 && matchStart < displayText.length) {
                    const matchEnd = Math.min(matchStart + query.length, displayText.length);
                    item.appendChild(document.createTextNode(displayText.substring(0, matchStart)));
                    const strong = document.createElement('strong');
                    strong.textContent = displayText.substring(matchStart, matchEnd);
                    item.appendChild(strong);
                    item.appendChild(document.createTextNode(displayText.substring(matchEnd)));
                } else {
                    item.textContent = displayText;
                }

                item.addEventListener('mousedown', (ev) => {
                    ev.preventDefault();
                    textarea.value = comment.content;
                    textarea.dispatchEvent(new Event('input', {bubbles: true}));
                    close();
                    textarea.focus();
                });
                item.addEventListener('mouseenter', () => {
                    acIndex = idx;
                    dropdown.querySelectorAll('.ug-ac-item').forEach((el, i) => {
                        el.classList.toggle('active', i === idx);
                    });
                });
                dropdown.appendChild(item);
            });
        };

        textarea.addEventListener('input', () => {
            show(textarea.value.trim());
        });

        textarea.addEventListener('blur', () => {
            setTimeout(close, 150);
        });

        textarea.addEventListener('keydown', (e) => {
            if (!acVisible) {
                return;
            }
            const items = dropdown.querySelectorAll('.ug-ac-item');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                e.stopPropagation();
                acIndex = Math.min(acIndex + 1, items.length - 1);
                items.forEach((el, i) => el.classList.toggle('active', i === acIndex));
                return;
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                e.stopPropagation();
                acIndex = Math.max(acIndex - 1, 0);
                items.forEach((el, i) => el.classList.toggle('active', i === acIndex));
                return;
            }
            if (e.key === 'Enter' && !e.ctrlKey && !e.metaKey && acIndex >= 0) {
                e.preventDefault();
                e.stopPropagation();
                if (currentMatches[acIndex]) {
                    textarea.value = currentMatches[acIndex].content;
                    textarea.dispatchEvent(new Event('input', {bubbles: true}));
                }
                close();
                return;
            }
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                close();
            }
        });
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
        if (this._suppressAutoSave || this._saveInFlight || this.reactive.state.ui.saving) {
            return;
        }
        if (this._autoSaveTimer) {
            clearTimeout(this._autoSaveTimer);
        }
        this._autoSaveTimer = setTimeout(() => {
            this._autoSaveTimer = null;
            // Only auto-save if something actually changed and no save is in progress.
            // This prevents the save loop where a post-save state refresh triggers
            // another empty save that clears existing marking guide fillings.
            if (!this._suppressAutoSave && !this._saveInFlight
                && !this.reactive.state.ui.saving
                && (DirtyTracker.isDirty('grade') || DirtyTracker.isDirty('feedback'))) {
                this._handleSaveGrade();
            }
        }, 1500);
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

        // Refuse to save when the manual grade exceeds the activity max.
        // We don't support extra-credit yet; the inline error on the input
        // tells the teacher what's wrong, so silently skipping the save
        // is the right thing — they'll see the red border + message.
        if (!this._validateGrade()) {
            return;
        }
        // Same guard for marking-guide criterion scores: a score above the
        // criterion's allocated max would silently round-trip to the server
        // and break the gradebook total. Block the save and leave the
        // per-criterion red flag in place so the teacher can fix it.
        if (!this._validateAllGuideScores()) {
            return;
        }

        // Prevent overlapping saves — if a save is already in flight,
        // skip this one entirely to avoid stale data overwriting fresh data.
        if (this._saveInFlight) {
            return;
        }
        this._saveInFlight = true;

        const state = this.reactive.state;

        // Read grade from the appropriate input (scale dropdown or numeric input).
        let grade = '';
        const gradingDisabled = state.activity?.gradingdisabled || false;
        if (gradingDisabled) {
            // For feedback-only activities, the "Mark as graded" toggle determines
            // whether we save grade=0 (graded) or grade='' (becomes -1 = not graded).
            const toggle = this.getElement(this.selectors.MARK_GRADED_TOGGLE);
            grade = (toggle && toggle.checked) ? '0' : '';
        } else if (state.activity?.usescale) {
            const scaleInput = this.getElement(this.selectors.SCALE_INPUT);
            grade = scaleInput ? scaleInput.value : '';
        } else {
            const gradeInput = this.getElement(this.selectors.GRADE_INPUT);
            grade = gradeInput ? gradeInput.value : '';
            // Two reset escape hatches, matching the Moodle gradebook's
            // convention of accepting "-" to clear a cell:
            //   "--" → deliberate reset. Clears the grade AND removes any
            //          orphan submission row (status != 'submitted') created
            //          by accidental teacher interaction. Real submissions
            //          stay intact; only the grade goes.
            //   "-"  → light reset. Clears the grade only, leaving the
            //          submission row exactly as it was.
            // Any other non-numeric value is normalised to the light reset
            // so a stray character doesn't surface as a PARAM_FLOAT error.
            if (gradeInput && grade === '--') {
                this._fullResetRequested = true;
                grade = '';
                gradeInput.value = '';
                this._gradeManuallyOverridden = false;
                this._updatePercentage();
                this._updateFinalGradeDisplay();
                this._updateOverrideIndicator(this._lastRubricGrade);
            } else if (gradeInput && grade !== '' && !isFinite(parseFloat(grade))) {
                grade = '';
                gradeInput.value = '';
                this._gradeManuallyOverridden = false;
                this._updatePercentage();
                this._updateFinalGradeDisplay();
                this._updateOverrideIndicator(this._lastRubricGrade);
            }
        }
        const feedback = this._getEditorContent();
        const advancedGradingData = this._collectAdvancedGradingData();

        // _fullResetRequested is one-shot — consumed by this dispatch and
        // cleared so the next save reverts to normal behaviour.
        const reset = !!this._fullResetRequested;
        this._fullResetRequested = false;

        // If this save was started while re-editing existing feedback, arm the
        // post-save render to collapse the editor back to the saved card (see
        // _renderFeedbackAndSnapshot). Captured here, past every early return,
        // so only a genuinely dispatched save arms it.
        this._collapseFeedbackAfterSave = this._editingFeedback;

        this.reactive.dispatch(
            'saveGrade',
            state.activity.cmid,
            state.currentUser.id,
            grade,
            feedback,
            state.ui.draftitemid,
            advancedGradingData,
            state.ui.feedbackfilesdraftid,
            reset,
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

        // Hide if single-attempt activity or only one attempt exists.
        // maxattempts: 0 = unlimited (quiz), -1 = unlimited (assign), 1 = single attempt.
        if (maxattempts === 1 || attemptList.length <= 1) {
            wrapper.classList.add('d-none');
            return;
        }

        wrapper.classList.remove('d-none');

        // Populate the dropdown options.
        // Assignment attempts are 0-based (display as +1), quiz attempts are 1-based (display as-is).
        const isZeroBased = state.activity?.type === 'assign';
        const currentAttempt = state.submission.attemptnumber;
        select.innerHTML = '';

        attemptList.forEach((attempt) => {
            const option = document.createElement('option');
            option.value = attempt.attemptnumber;
            const num = isZeroBased ? attempt.attemptnumber + 1 : attempt.attemptnumber;
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
        // Use the canonical submittedat the adapter chose for this activity
        // type — assign = final submit, forum = first post, quiz = attempt
        // finish, bbb = n/a. The fallbacks preserve behaviour for older
        // server builds that don't surface the field yet, but on a current
        // server submittedat is always present.
        const submitted = state.submission.submittedat
            || state.submission.timemodified
            || state.submission.timecreated
            || 0;

        if (!duedate || !submitted || submitted <= duedate) {
            indicator.classList.add('d-none');
            return;
        }

        // Calculate the late duration against the effective due date.
        const diffSeconds = submitted - duedate;
        const days = Math.floor(diffSeconds / 86400);
        const hours = Math.floor((diffSeconds % 86400) / 3600);
        const minutes = Math.floor((diffSeconds % 3600) / 60);

        // Build duration string from translated parts.
        const textEl = this.getElement(this.selectors.LATE_TEXT);
        if (!textEl) {
            indicator.classList.remove('d-none');
            return;
        }

        const stringPromises = [];
        if (days > 0) {
            const key = days === 1 ? 'late_day' : 'late_days';
            stringPromises.push(getString(key, 'local_unifiedgrader', days));
        }
        if (hours > 0) {
            const key = hours === 1 ? 'late_hour' : 'late_hours';
            stringPromises.push(getString(key, 'local_unifiedgrader', hours));
        }
        // Show minutes only when less than 1 day late.
        if (days === 0 && minutes > 0) {
            const key = minutes === 1 ? 'late_min' : 'late_mins';
            stringPromises.push(getString(key, 'local_unifiedgrader', minutes));
        }

        if (stringPromises.length === 0) {
            getString('late_lessthanmin', 'local_unifiedgrader').then((lessThan) => {
                getString('status_late', 'local_unifiedgrader', lessThan).then((s) => {
                    textEl.textContent = s;
                });
            });
        } else {
            Promise.all(stringPromises).then((parts) => {
                const durationText = parts.join(' ');
                getString('status_late', 'local_unifiedgrader', durationText).then((s) => {
                    textEl.textContent = s;
                });
            });
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
        const actions = this.getElement(this.selectors.PLAGIARISM_ACTIONS);

        // Post-body plagiarism shields for forums are rendered inline in
        // the preview panel, but per-attachment shields aren't — so the
        // forum adapter returns attachment-only links here (post bodies
        // are excluded server-side). Render them the same way assignment
        // file shields are rendered.
        const links = state.submission.plagiarismlinks || [];

        if (links.length === 0) {
            // No plagiarism pane for this submission — re-home the referral
            // control in the grade pane before clearing the actions host (so the
            // referenced section node is not briefly orphaned).
            section.classList.add('d-none');
            body.innerHTML = '';
            this._setReferralHost('grade');
            if (actions) {
                actions.innerHTML = '';
            }
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

        // Academic-integrity action below the file shields. The action belongs
        // with the plagiarism report, so whenever this card is shown it hosts the
        // control (it only falls back to the grade card when there is no
        // plagiarism card — the links.length === 0 path above):
        //  - Can refer → relocate the referral control here (same node, so the
        //    grade card no longer shows it). With a report form configured it is
        //    the single combined button (one click flags AND opens the form);
        //    without one it flags via the inline-note flow.
        //  - Cannot refer but report form enabled → plain report link (form only),
        //    preserving the prior behaviour for non-referrers.
        //  - Cannot refer and no report form → nothing to show here.
        const formEnabled = !!(state.ui.enableReportForm && state.ui.reportFormUrl);
        const canRefer = !!state.ui.canrefer;
        if (actions) {
            if (canRefer) {
                this._setReferralHost('plagiarism');
            } else if (formEnabled) {
                this._setReferralHost('grade');
                actions.innerHTML = this._buildReportLinkHtml(state);
            } else {
                this._setReferralHost('grade');
                actions.innerHTML = '';
            }
        }

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
