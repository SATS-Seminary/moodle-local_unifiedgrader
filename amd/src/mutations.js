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
 * State mutations for the Unified Grader.
 *
 * All state changes go through these mutations. Each mutation typically
 * makes an AJAX call and then updates the reactive state.
 *
 * @module     local_unifiedgrader/mutations
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';
import * as SaveQueue from 'local_unifiedgrader/save_queue';
import * as DirtyTracker from 'local_unifiedgrader/dirty_tracker';
import * as OfflineCache from 'local_unifiedgrader/offline_cache';

/**
 * Handle AJAX errors gracefully.
 * Network errors (server unreachable) lack Moodle error properties and produce
 * blank Notification.exception modals. This wrapper shows a friendly alert instead.
 *
 * @param {*} error Error object.
 */
const _handleError = (error) => {
    if (error?.errorcode) {
        Notification.exception(error);
    } else {
        Notification.alert(getString('error'), getString('error_network', 'local_unifiedgrader'));
        window.console.warn('[mutations] Network error:', error);
    }
};

export default class {

    /**
     * Load a student's submission and grade data.
     *
     * @param {object} stateManager The reactive state manager.
     * @param {number} cmid Course module ID.
     * @param {number} userid User ID to load.
     */
    async loadStudent(stateManager, cmid, userid) {
        stateManager.setReadOnly(false);
        stateManager.state.ui.loading = true;
        // Update property directly — replacing the whole object fires
        // state.currentUser:updated, but watchers listen for currentUser:updated.
        stateManager.state.currentUser.id = userid;
        stateManager.setReadOnly(true);

        try {
            const draftitemid = stateManager.state.ui.draftitemid;
            const feedbackfilesdraftid = stateManager.state.ui.feedbackfilesdraftid;
            const calls = [
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_submission_data',
                    args: {cmid, userid},
                }])[0],
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_grade_data',
                    args: {cmid, userid},
                }])[0],
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_notes',
                    args: {cmid, userid},
                }])[0],
                // Load penalties in the same batch to avoid a second await while state is writable.
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_penalties',
                    args: {cmid, userid},
                }])[0].catch(() => []),
            ];

            // Prepare the feedback draft area in parallel if a draftitemid exists.
            // Pass attemptnumber -1 to load the latest attempt's feedback.
            if (draftitemid) {
                calls.push(Ajax.call([{
                    methodname: 'local_unifiedgrader_prepare_feedback_draft',
                    args: {cmid, userid, draftitemid, attemptnumber: -1},
                }])[0]);
            }

            // Prepare feedback files draft area in parallel if enabled.
            if (feedbackfilesdraftid) {
                calls.push(Ajax.call([{
                    methodname: 'local_unifiedgrader_prepare_feedback_files_draft',
                    args: {cmid, userid, draftitemid: feedbackfilesdraftid},
                }])[0]);
            }

            const results = await Promise.all(calls);
            const [submissionData, gradeData, notes, penalties] = results;
            const feedbackDraft = (draftitemid ? results[4] : null) || {feedbackhtml: ''};

            stateManager.setReadOnly(false);
            // Use Object.assign to update properties on the existing proxy.
            // This fires submission:updated / grade:updated events that watchers expect.
            // Replacing the whole object (state.X = newObj) would fire state.X:updated instead.
            Object.assign(stateManager.state.submission, submissionData);
            Object.assign(stateManager.state.grade, gradeData);
            stateManager.state.grade.feedbackdraft = feedbackDraft.feedbackhtml;
            // Notes is a StateMap (array with id fields) — must replace entirely.
            // Watcher uses state.notes:updated to catch this.
            stateManager.state.notes = notes;
            stateManager.state.penalties = penalties;

            // Update submission comment count and reset loaded flag.
            stateManager.state.submissionComments.count = submissionData.commentcount || 0;
            stateManager.state.submissionComments.loaded = false;
            stateManager.state.ui.loading = false;
            stateManager.setReadOnly(true);

            // Refresh the filemanager widget after draft area has been re-prepared.
            this._refreshFileManager(stateManager);
        } catch (error) {
            _handleError(error);
            stateManager.setReadOnly(false);
            stateManager.state.ui.loading = false;
            stateManager.setReadOnly(true);
        }
    }

    /**
     * Load a specific submission attempt for the current student.
     *
     * Reloads submission data and grade data for the given attempt number.
     * Notes and penalties are per-user (not per-attempt) so they are not reloaded.
     *
     * @param {object} stateManager The reactive state manager.
     * @param {number} cmid Course module ID.
     * @param {number} userid User ID.
     * @param {number} attemptnumber Attempt number to load (0-based).
     */
    async loadAttempt(stateManager, cmid, userid, attemptnumber) {
        stateManager.setReadOnly(false);
        stateManager.state.ui.loading = true;
        stateManager.setReadOnly(true);

        try {
            const draftitemid = stateManager.state.ui.draftitemid;
            const feedbackfilesdraftid = stateManager.state.ui.feedbackfilesdraftid;
            const calls = [
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_submission_data',
                    args: {cmid, userid, attemptnumber},
                }])[0],
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_grade_data',
                    args: {cmid, userid, attemptnumber},
                }])[0],
            ];

            // Re-prepare the feedback draft area for this attempt's feedback.
            if (draftitemid) {
                calls.push(Ajax.call([{
                    methodname: 'local_unifiedgrader_prepare_feedback_draft',
                    args: {cmid, userid, draftitemid, attemptnumber},
                }])[0]);
            }

            if (feedbackfilesdraftid) {
                calls.push(Ajax.call([{
                    methodname: 'local_unifiedgrader_prepare_feedback_files_draft',
                    args: {cmid, userid, draftitemid: feedbackfilesdraftid},
                }])[0]);
            }

            const results = await Promise.all(calls);
            const [submissionData, gradeData] = results;
            const feedbackDraft = (draftitemid ? results[2] : null) || {feedbackhtml: ''};

            stateManager.setReadOnly(false);
            Object.assign(stateManager.state.submission, submissionData);
            Object.assign(stateManager.state.grade, gradeData);
            stateManager.state.grade.feedbackdraft = feedbackDraft.feedbackhtml;
            stateManager.state.submissionComments.count = submissionData.commentcount || 0;
            stateManager.state.submissionComments.loaded = false;
            stateManager.state.ui.loading = false;
            stateManager.setReadOnly(true);

            this._refreshFileManager(stateManager);
        } catch (error) {
            _handleError(error);
            stateManager.setReadOnly(false);
            stateManager.state.ui.loading = false;
            stateManager.setReadOnly(true);
        }
    }

    /**
     * Save grade and feedback for the current student.
     *
     * @param {object} stateManager The reactive state manager.
     * @param {number} cmid Course module ID.
     * @param {number} userid User ID.
     * @param {number|null} grade Grade value.
     * @param {string} feedback Feedback HTML.
     * @param {number} draftitemid Draft area item ID for feedback files.
     * @param {string} advancedgradingdata JSON string of advanced grading data.
     * @param {number} feedbackfilesdraftid Draft area item ID for feedback files (assignfeedback_file).
     */
    async saveGrade(stateManager, cmid, userid, grade, feedback, draftitemid,
        advancedgradingdata, feedbackfilesdraftid) {
        // Track the userid we are saving for — if the teacher navigates away
        // before the save completes, we must skip the post-save state refresh
        // to avoid overwriting the newly loaded student's data.
        const savedForUser = userid;

        stateManager.setReadOnly(false);
        stateManager.state.ui.saving = true;
        stateManager.setReadOnly(true);

        try {
            await Ajax.call([{
                methodname: 'local_unifiedgrader_save_grade',
                args: {
                    cmid,
                    userid,
                    grade: grade !== null && grade !== '' ? parseFloat(grade) : -1,
                    feedback: feedback || '',
                    feedbackformat: 1,
                    draftitemid: draftitemid || 0,
                    advancedgradingdata: advancedgradingdata || '',
                    feedbackfilesdraftid: feedbackfilesdraftid || 0,
                    attemptnumber: stateManager.state.submission.attemptnumber ?? -1,
                },
            }])[0];

            // If the teacher has already navigated to a different student,
            // skip the refresh — loadStudent will have already loaded the
            // correct data for the new student.
            if (stateManager.state.currentUser?.id !== savedForUser) {
                stateManager.setReadOnly(false);
                stateManager.state.ui.saving = false;
                stateManager.setReadOnly(true);
                return;
            }

            // Refresh grade data, participant list, submission data, and draft areas after save.
            // Fetch the LATEST submission (attemptnumber -1) to detect if Moodle reopened
            // the submission (created a new attempt) as part of the save.
            const currentAttempt = stateManager.state.submission.attemptnumber ?? -1;
            const refreshCalls = [
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_grade_data',
                    args: {cmid, userid, attemptnumber: currentAttempt},
                }])[0],
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_participants',
                    args: {
                        cmid,
                        status: stateManager.state.filters.status,
                        group: String(stateManager.state.filters.group),
                        search: stateManager.state.filters.search,
                        sort: stateManager.state.filters.sort,
                        sortdir: stateManager.state.filters.sortdir,
                    },
                }])[0],
                // Fetch latest submission to detect reopen (new attempt created).
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_submission_data',
                    args: {cmid, userid, attemptnumber: -1},
                }])[0],
            ];

            if (draftitemid) {
                refreshCalls.push(Ajax.call([{
                    methodname: 'local_unifiedgrader_prepare_feedback_draft',
                    args: {cmid, userid, draftitemid, attemptnumber: currentAttempt},
                }])[0]);
            }

            // Re-prepare feedback files draft after save.
            if (feedbackfilesdraftid) {
                refreshCalls.push(Ajax.call([{
                    methodname: 'local_unifiedgrader_prepare_feedback_files_draft',
                    args: {cmid, userid, draftitemid: feedbackfilesdraftid},
                }])[0]);
            }

            const results = await Promise.all(refreshCalls);

            // Check again after refresh — teacher may have navigated while refresh was in flight.
            if (stateManager.state.currentUser?.id !== savedForUser) {
                stateManager.setReadOnly(false);
                stateManager.state.ui.saving = false;
                stateManager.setReadOnly(true);
                return;
            }

            const [gradeData, participants, latestSubmission] = results;
            const feedbackDraft = (draftitemid ? results[3] : null) || {feedbackhtml: ''};

            stateManager.setReadOnly(false);
            Object.assign(stateManager.state.grade, gradeData);
            stateManager.state.grade.feedbackdraft = feedbackDraft.feedbackhtml;
            stateManager.state.participants = participants;

            // Detect if a reopen occurred: the latest submission's attempt number
            // is now higher than the attempt we just graded.
            if (latestSubmission && latestSubmission.attemptnumber > currentAttempt) {
                // A new attempt was created (e.g. "reopen until pass").
                // Update submission state to the new attempt so the UI reflects
                // the current reality and subsequent saves target the correct attempt.
                Object.assign(stateManager.state.submission, latestSubmission);
                stateManager.state.submissionComments.count = latestSubmission.commentcount || 0;
                stateManager.state.submissionComments.loaded = false;
            }

            stateManager.state.ui.saving = false;
            stateManager.setReadOnly(true);

            // Refresh the filemanager widget after draft area has been re-prepared.
            this._refreshFileManager(stateManager);
        } catch (error) {
            // Queue the save for retry on network errors; show full modal for server-side errors.
            if (!error?.errorcode) {
                SaveQueue.enqueue('saveGrade', () => Ajax.call([{
                    methodname: 'local_unifiedgrader_save_grade',
                    args: {
                        cmid, userid,
                        grade: grade !== null && grade !== '' ? parseFloat(grade) : -1,
                        feedback: feedback || '',
                        feedbackformat: 1,
                        draftitemid: draftitemid || 0,
                        advancedgradingdata: advancedgradingdata || '',
                        feedbackfilesdraftid: feedbackfilesdraftid || 0,
                        attemptnumber: stateManager.state.submission.attemptnumber ?? -1,
                    },
                }])[0], () => {
                    // Cleanup after successful retry.
                    DirtyTracker.markClean('grade');
                    DirtyTracker.markClean('feedback');
                    OfflineCache.removeAll(cmid, userid);
                });
                window.console.warn('[mutations] saveGrade failed, queued for retry:', error);
            } else {
                _handleError(error);
            }
            stateManager.setReadOnly(false);
            stateManager.state.ui.saving = false;
            stateManager.setReadOnly(true);
        }
    }

    /**
     * Update participant filters and reload the list.
     *
     * @param {object} stateManager The reactive state manager.
     * @param {number} cmid Course module ID.
     * @param {object} filters Filter values to apply.
     */
    async updateFilters(stateManager, cmid, filters) {
        stateManager.setReadOnly(false);
        Object.assign(stateManager.state.filters, filters);
        stateManager.setReadOnly(true);

        try {
            const participants = await Ajax.call([{
                methodname: 'local_unifiedgrader_get_participants',
                args: {
                    cmid,
                    status: stateManager.state.filters.status,
                    group: String(stateManager.state.filters.group),
                    search: stateManager.state.filters.search,
                    sort: stateManager.state.filters.sort,
                    sortdir: stateManager.state.filters.sortdir,
                },
            }])[0];

            stateManager.setReadOnly(false);
            stateManager.state.participants = participants;
            stateManager.setReadOnly(true);
        } catch (error) {
            _handleError(error);
        }
    }

    /**
     * Save a teacher note.
     *
     * @param {object} stateManager The reactive state manager.
     * @param {number} cmid Course module ID.
     * @param {number} userid Student user ID.
     * @param {string} content Note content.
     * @param {number} noteid Existing note ID (0 for new).
     */
    async saveNote(stateManager, cmid, userid, content, noteid) {
        try {
            await Ajax.call([{
                methodname: 'local_unifiedgrader_save_note',
                args: {cmid, userid, content, noteid: noteid || 0},
            }])[0];

            // Refresh notes list.
            const notes = await Ajax.call([{
                methodname: 'local_unifiedgrader_get_notes',
                args: {cmid, userid},
            }])[0];

            stateManager.setReadOnly(false);
            stateManager.state.notes = notes;
            stateManager.setReadOnly(true);
        } catch (error) {
            _handleError(error);
        }
    }

    /**
     * Delete a teacher note.
     *
     * @param {object} stateManager The reactive state manager.
     * @param {number} cmid Course module ID.
     * @param {number} userid Student user ID.
     * @param {number} noteid Note ID to delete.
     */
    async deleteNote(stateManager, cmid, userid, noteid) {
        try {
            await Ajax.call([{
                methodname: 'local_unifiedgrader_delete_note',
                args: {cmid, noteid},
            }])[0];

            // Refresh notes list.
            const notes = await Ajax.call([{
                methodname: 'local_unifiedgrader_get_notes',
                args: {cmid, userid},
            }])[0];

            stateManager.setReadOnly(false);
            stateManager.state.notes = notes;
            stateManager.setReadOnly(true);
        } catch (error) {
            _handleError(error);
        }
    }

    /**
     * Save a grade penalty and refresh the penalty list.
     *
     * @param {object} stateManager The reactive state manager.
     * @param {number} cmid Course module ID.
     * @param {number} userid Student user ID.
     * @param {string} category Penalty category ('wordcount' or 'other').
     * @param {string} label Custom label for 'other' penalties.
     * @param {number} percentage Penalty percentage (1-100).
     */
    async savePenalty(stateManager, cmid, userid, category, label, percentage) {
        try {
            const result = await Ajax.call([{
                methodname: 'local_unifiedgrader_save_penalty',
                args: {cmid, userid, category, label: label || '', percentage},
            }])[0];

            stateManager.setReadOnly(false);
            stateManager.state.penalties = result.penalties;
            stateManager.setReadOnly(true);
        } catch (error) {
            _handleError(error);
        }
    }

    /**
     * Delete a grade penalty and refresh the penalty list.
     *
     * @param {object} stateManager The reactive state manager.
     * @param {number} cmid Course module ID.
     * @param {number} userid Student user ID.
     * @param {number} penaltyid Penalty ID to delete.
     */
    async deletePenalty(stateManager, cmid, userid, penaltyid) {
        try {
            await Ajax.call([{
                methodname: 'local_unifiedgrader_delete_penalty',
                args: {cmid, penaltyid},
            }])[0];

            // Refresh penalty list.
            const penalties = await Ajax.call([{
                methodname: 'local_unifiedgrader_get_penalties',
                args: {cmid, userid},
            }])[0];

            stateManager.setReadOnly(false);
            stateManager.state.penalties = penalties;
            stateManager.setReadOnly(true);
        } catch (error) {
            _handleError(error);
        }
    }

    /**
     * Load submission comments for the current student.
     *
     * @param {object} stateManager The reactive state manager.
     * @param {number} cmid Course module ID.
     * @param {number} userid Student user ID.
     * @param {number} attemptnumber Attempt number (0-based), -1 for latest.
     */
    async loadSubmissionComments(stateManager, cmid, userid) {
        try {
            const result = await Ajax.call([{
                methodname: 'local_unifiedgrader_get_submission_comments',
                args: {cmid, userid},
            }])[0];

            stateManager.setReadOnly(false);
            stateManager.state.submissionComments.count = result.count;
            stateManager.state.submissionComments.canpost = result.canpost;
            stateManager.state.submissionComments.loaded = true;
            // Comments array uses id fields, so it becomes a StateMap.
            stateManager.state.submissionComments.comments = result.comments;
            stateManager.setReadOnly(true);
        } catch (error) {
            _handleError(error);
        }
    }

    /**
     * Add a submission comment.
     *
     * @param {object} stateManager The reactive state manager.
     * @param {number} cmid Course module ID.
     * @param {number} userid Student user ID.
     * @param {string} content Comment content.
     */
    async addSubmissionComment(stateManager, cmid, userid, content) {
        try {
            await Ajax.call([{
                methodname: 'local_unifiedgrader_add_submission_comment',
                args: {cmid, userid, content},
            }])[0];

            // Refresh the full comment list to get consistent data.
            const commentsResult = await Ajax.call([{
                methodname: 'local_unifiedgrader_get_submission_comments',
                args: {cmid, userid},
            }])[0];

            stateManager.setReadOnly(false);
            stateManager.state.submissionComments.count = commentsResult.count;
            stateManager.state.submissionComments.canpost = commentsResult.canpost;
            stateManager.state.submissionComments.loaded = true;
            stateManager.state.submissionComments.comments = commentsResult.comments;
            stateManager.setReadOnly(true);
        } catch (error) {
            _handleError(error);
        }
    }

    /**
     * Delete a submission comment.
     *
     * @param {object} stateManager The reactive state manager.
     * @param {number} cmid Course module ID.
     * @param {number} userid Student user ID.
     * @param {number} commentid Comment ID to delete.
     */
    async deleteSubmissionComment(stateManager, cmid, userid, commentid) {
        try {
            await Ajax.call([{
                methodname: 'local_unifiedgrader_delete_submission_comment',
                args: {cmid, commentid},
            }])[0];

            // Refresh the full comment list.
            const commentsResult = await Ajax.call([{
                methodname: 'local_unifiedgrader_get_submission_comments',
                args: {cmid, userid},
            }])[0];

            stateManager.setReadOnly(false);
            stateManager.state.submissionComments.count = commentsResult.count;
            stateManager.state.submissionComments.canpost = commentsResult.canpost;
            stateManager.state.submissionComments.loaded = true;
            stateManager.state.submissionComments.comments = commentsResult.comments;
            stateManager.setReadOnly(true);
        } catch (error) {
            _handleError(error);
        }
    }

    /**
     * Set grade visibility for the current activity.
     *
     * @param {object} stateManager The reactive state manager.
     * @param {number} cmid Course module ID.
     * @param {number} hidden 0 = post (visible), 1 = hide permanently, >1 = hide-until timestamp.
     */
    async setGradesPosted(stateManager, cmid, hidden) {
        stateManager.setReadOnly(false);
        stateManager.state.ui.posting = true;
        stateManager.setReadOnly(true);

        try {
            const result = await Ajax.call([{
                methodname: 'local_unifiedgrader_set_grades_posted',
                args: {cmid, hidden},
            }])[0];

            stateManager.setReadOnly(false);
            stateManager.state.ui.gradesPosted = result.posted;
            stateManager.state.ui.gradesHidden = result.hidden;
            stateManager.state.ui.posting = false;
            stateManager.setReadOnly(true);
        } catch (error) {
            _handleError(error);
            stateManager.setReadOnly(false);
            stateManager.state.ui.posting = false;
            stateManager.setReadOnly(true);
        }
    }

    /**
     * Perform a submission status action (revert to draft, remove, lock, unlock).
     *
     * @param {object} stateManager The reactive state manager.
     * @param {number} cmid Course module ID.
     * @param {number} userid Student user ID.
     * @param {string} action Action identifier.
     */
    async submissionAction(stateManager, cmid, userid, action) {
        stateManager.setReadOnly(false);
        stateManager.state.ui.loading = true;
        stateManager.setReadOnly(true);

        try {
            await Ajax.call([{
                methodname: 'local_unifiedgrader_submission_action',
                args: {cmid, userid, action},
            }])[0];

            // Refresh submission data, grade data, and participant list.
            const draftitemid = stateManager.state.ui.draftitemid;
            const feedbackfilesdraftid = stateManager.state.ui.feedbackfilesdraftid;
            const refreshCalls = [
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_submission_data',
                    args: {cmid, userid},
                }])[0],
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_grade_data',
                    args: {cmid, userid},
                }])[0],
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_participants',
                    args: {
                        cmid,
                        status: stateManager.state.filters.status,
                        group: String(stateManager.state.filters.group),
                        search: stateManager.state.filters.search,
                        sort: stateManager.state.filters.sort,
                        sortdir: stateManager.state.filters.sortdir,
                    },
                }])[0],
            ];

            if (draftitemid) {
                refreshCalls.push(Ajax.call([{
                    methodname: 'local_unifiedgrader_prepare_feedback_draft',
                    args: {cmid, userid, draftitemid},
                }])[0]);
            }

            if (feedbackfilesdraftid) {
                refreshCalls.push(Ajax.call([{
                    methodname: 'local_unifiedgrader_prepare_feedback_files_draft',
                    args: {cmid, userid, draftitemid: feedbackfilesdraftid},
                }])[0]);
            }

            const results = await Promise.all(refreshCalls);
            const [submissionData, gradeData, participants] = results;
            const feedbackDraft = (draftitemid ? results[3] : null) || {feedbackhtml: ''};

            stateManager.setReadOnly(false);
            Object.assign(stateManager.state.submission, submissionData);
            Object.assign(stateManager.state.grade, gradeData);
            stateManager.state.grade.feedbackdraft = feedbackDraft.feedbackhtml;
            stateManager.state.participants = participants;
            stateManager.state.submissionComments.count = submissionData.commentcount || 0;
            stateManager.state.submissionComments.loaded = false;
            stateManager.state.ui.loading = false;
            stateManager.setReadOnly(true);

            this._refreshFileManager(stateManager);
        } catch (error) {
            _handleError(error);
            stateManager.setReadOnly(false);
            stateManager.state.ui.loading = false;
            stateManager.setReadOnly(true);
        }
    }

    /**
     * Delete a user-level override and refresh submission data.
     *
     * @param {object} stateManager The reactive state manager.
     * @param {number} cmid Course module ID.
     * @param {number} userid Student user ID.
     */
    async deleteUserOverride(stateManager, cmid, userid) {
        stateManager.setReadOnly(false);
        stateManager.state.ui.loading = true;
        stateManager.setReadOnly(true);

        try {
            await Ajax.call([{
                methodname: 'local_unifiedgrader_delete_user_override',
                args: {cmid, userid},
            }])[0];

            // Refresh submission data and participant list.
            const refreshCalls = [
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_submission_data',
                    args: {cmid, userid},
                }])[0],
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_participants',
                    args: {
                        cmid,
                        status: stateManager.state.filters.status,
                        group: String(stateManager.state.filters.group),
                        search: stateManager.state.filters.search,
                        sort: stateManager.state.filters.sort,
                        sortdir: stateManager.state.filters.sortdir,
                    },
                }])[0],
            ];

            const [submissionData, participants] = await Promise.all(refreshCalls);

            stateManager.setReadOnly(false);
            Object.assign(stateManager.state.submission, submissionData);
            stateManager.state.participants = participants;
            stateManager.state.ui.loading = false;
            stateManager.setReadOnly(true);
        } catch (error) {
            _handleError(error);
            stateManager.setReadOnly(false);
            stateManager.state.ui.loading = false;
            stateManager.setReadOnly(true);
        }
    }

    /**
     * Delete a quiz duedate extension for a user.
     *
     * @param {object} stateManager The reactive state manager.
     * @param {number} cmid Course module ID.
     * @param {number} userid Student user ID.
     */
    async deleteDuedateExtension(stateManager, cmid, userid) {
        stateManager.setReadOnly(false);
        stateManager.state.ui.loading = true;
        stateManager.setReadOnly(true);

        try {
            await Ajax.call([{
                methodname: 'local_unifiedgrader_delete_duedate_extension',
                args: {cmid, userid},
            }])[0];

            // Refresh submission data and participant list.
            const refreshCalls = [
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_submission_data',
                    args: {cmid, userid},
                }])[0],
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_participants',
                    args: {
                        cmid,
                        status: stateManager.state.filters.status,
                        group: String(stateManager.state.filters.group),
                        search: stateManager.state.filters.search,
                        sort: stateManager.state.filters.sort,
                        sortdir: stateManager.state.filters.sortdir,
                    },
                }])[0],
            ];

            const [submissionData, participants] = await Promise.all(refreshCalls);

            stateManager.setReadOnly(false);
            Object.assign(stateManager.state.submission, submissionData);
            stateManager.state.participants = participants;
            stateManager.state.ui.loading = false;
            stateManager.setReadOnly(true);
        } catch (error) {
            _handleError(error);
            stateManager.setReadOnly(false);
            stateManager.state.ui.loading = false;
            stateManager.setReadOnly(true);
        }
    }

    /**
     * Delete a forum due date extension for a user.
     *
     * @param {object} stateManager The reactive state manager.
     * @param {number} cmid Course module ID.
     * @param {number} userid Student user ID.
     */
    async deleteForumExtension(stateManager, cmid, userid) {
        stateManager.setReadOnly(false);
        stateManager.state.ui.loading = true;
        stateManager.setReadOnly(true);

        try {
            // Delete the extension (also re-syncs late penalty and gradebook on the server).
            await Ajax.call([{
                methodname: 'local_unifiedgrader_delete_forum_extension',
                args: {cmid, userid},
            }])[0];

            // Refresh submission data, grade data, penalties, and participant list.
            const refreshCalls = [
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_submission_data',
                    args: {cmid, userid},
                }])[0],
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_grade_data',
                    args: {cmid, userid},
                }])[0],
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_penalties',
                    args: {cmid, userid},
                }])[0].catch(() => []),
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_participants',
                    args: {
                        cmid,
                        status: stateManager.state.filters.status,
                        group: String(stateManager.state.filters.group),
                        search: stateManager.state.filters.search,
                        sort: stateManager.state.filters.sort,
                        sortdir: stateManager.state.filters.sortdir,
                    },
                }])[0],
            ];

            const [submissionData, gradeData, penalties, participants] = await Promise.all(refreshCalls);

            stateManager.setReadOnly(false);
            Object.assign(stateManager.state.submission, submissionData);
            Object.assign(stateManager.state.grade, gradeData);
            stateManager.state.penalties = penalties;
            stateManager.state.participants = participants;
            stateManager.state.ui.loading = false;
            stateManager.setReadOnly(true);
        } catch (error) {
            _handleError(error);
            stateManager.setReadOnly(false);
            stateManager.state.ui.loading = false;
            stateManager.setReadOnly(true);
        }
    }

    /**
     * Clear all overrides and extensions for a user.
     *
     * @param {object} stateManager The reactive state manager.
     * @param {number} cmid Course module ID.
     * @param {number} userid Student user ID.
     */
    async clearAllOverrides(stateManager, cmid, userid) {
        stateManager.setReadOnly(false);
        stateManager.state.ui.loading = true;
        stateManager.setReadOnly(true);

        try {
            await Ajax.call([{
                methodname: 'local_unifiedgrader_clear_all_overrides',
                args: {cmid, userid},
            }])[0];

            // Refresh submission data, grade data, penalties, and participant list.
            const refreshCalls = [
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_submission_data',
                    args: {cmid, userid},
                }])[0],
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_grade_data',
                    args: {cmid, userid},
                }])[0],
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_penalties',
                    args: {cmid, userid},
                }])[0].catch(() => []),
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_participants',
                    args: {
                        cmid,
                        status: stateManager.state.filters.status,
                        group: String(stateManager.state.filters.group),
                        search: stateManager.state.filters.search,
                        sort: stateManager.state.filters.sort,
                        sortdir: stateManager.state.filters.sortdir,
                    },
                }])[0],
            ];

            const [submissionData, gradeData, penalties, participants] = await Promise.all(refreshCalls);

            stateManager.setReadOnly(false);
            Object.assign(stateManager.state.submission, submissionData);
            Object.assign(stateManager.state.grade, gradeData);
            stateManager.state.penalties = penalties;
            stateManager.state.participants = participants;
            stateManager.state.ui.loading = false;
            stateManager.setReadOnly(true);
        } catch (error) {
            _handleError(error);
            stateManager.setReadOnly(false);
            stateManager.state.ui.loading = false;
            stateManager.setReadOnly(true);
        }
    }

    /**
     * Save feedback files from the draft area to permanent storage.
     *
     * @param {object} stateManager The reactive state manager.
     * @param {number} cmid Course module ID.
     * @param {number} userid User ID.
     * @param {number} feedbackfilesdraftid Draft area item ID.
     */
    async saveFeedbackFiles(stateManager, cmid, userid, feedbackfilesdraftid) {
        stateManager.setReadOnly(false);
        stateManager.state.ui.savingFiles = true;
        stateManager.setReadOnly(true);

        try {
            await Ajax.call([{
                methodname: 'local_unifiedgrader_save_feedback_files',
                args: {cmid, userid, draftitemid: feedbackfilesdraftid},
            }])[0];

            // Re-prepare the draft area to refresh the filemanager widget.
            await Ajax.call([{
                methodname: 'local_unifiedgrader_prepare_feedback_files_draft',
                args: {cmid, userid, draftitemid: feedbackfilesdraftid},
            }])[0];

            stateManager.setReadOnly(false);
            stateManager.state.ui.savingFiles = false;
            stateManager.setReadOnly(true);

            this._refreshFileManager(stateManager);
        } catch (error) {
            _handleError(error);
            stateManager.setReadOnly(false);
            stateManager.state.ui.savingFiles = false;
            stateManager.setReadOnly(true);
        }
    }

    /**
     * Refresh the feedback files filemanager widget.
     *
     * Called after the draft area has been re-prepared (student switch or save).
     * Moodle 5.0 does not expose the YUI filemanager instance via M.form_filemanager.instances,
     * so we call the draft files AJAX API directly and update the DOM.
     *
     * @param {object} stateManager The reactive state manager.
     */
    _refreshFileManager(stateManager) {
        const clientId = stateManager.state.ui.feedbackfilesclientid;
        const draftItemId = stateManager.state.ui.feedbackfilesdraftid;
        if (!clientId || !draftItemId) {
            return;
        }

        const fmEl = document.getElementById('filemanager-' + clientId);
        if (!fmEl) {
            return;
        }

        // Call Moodle's draft files API to get the current file listing.
        const body = new URLSearchParams({
            action: 'list',
            filepath: '/',
            clientid: clientId,
            itemid: String(draftItemId),
            sesskey: window.M.cfg.sesskey,
        });

        fetch(window.M.cfg.wwwroot + '/repository/draftfiles_ajax.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body.toString(),
        })
        .then(r => r.json())
        .then(data => {
            const files = data.list || [];
            const hasFiles = files.length > 0;

            // Toggle container state classes (matches what the YUI widget does).
            fmEl.classList.toggle('fm-nofiles', !hasFiles);
            fmEl.classList.toggle('fm-noitems', !hasFiles);

            // Update the file listing inside .fp-content.
            const content = fmEl.querySelector('.fp-content');
            if (!content) {
                return;
            }
            content.innerHTML = '';

            // The YUI widget creates an .fp-iconview container inside .fp-content.
            // All CSS rules (e.g. .fp-iconview .fp-thumbnail) require this wrapper.
            const iconView = document.createElement('div');
            iconView.className = 'fp-iconview';

            files.forEach(file => {
                // Match Moodle's native file manager DOM structure (fm_js_template_iconfilename).
                const wrapper = document.createElement('div');
                wrapper.className = 'fp-file fp-hascontextmenu';
                wrapper.tabIndex = 0;

                const a = document.createElement('a');
                a.href = '#';
                a.className = 'd-block aabtn';

                // Position-relative container for thumbnail + ref icons (matches native template).
                const posWrap = document.createElement('div');
                posWrap.style.position = 'relative';

                const thumb = document.createElement('div');
                thumb.className = 'fp-thumbnail';

                const imgSrc = file.realthumbnail || file.thumbnail || file.icon || '';
                if (imgSrc) {
                    const img = document.createElement('img');
                    img.src = imgSrc;
                    img.alt = '';
                    // Match YUI widget sizing (filepicker.js lines 486-487).
                    const maxW = file.thumbnail_width || 90;
                    const maxH = file.thumbnail_height || 90;
                    img.style.maxWidth = maxW + 'px';
                    img.style.maxHeight = maxH + 'px';
                    thumb.appendChild(img);
                }

                // Green check badge to indicate the file is saved.
                const badge = document.createElement('i');
                badge.className = 'fa fa-check-circle fp-saved-badge';
                badge.setAttribute('aria-hidden', 'true');
                thumb.appendChild(badge);

                posWrap.appendChild(thumb);
                a.appendChild(posWrap);

                const fnField = document.createElement('div');
                fnField.className = 'fp-filename-field';
                const fnDiv = document.createElement('div');
                fnDiv.className = 'fp-filename text-truncate';
                fnDiv.textContent = file.fullname || file.filename || '';
                fnField.appendChild(fnDiv);
                a.appendChild(fnField);

                wrapper.appendChild(a);
                iconView.appendChild(wrapper);
            });

            content.appendChild(iconView);
        })
        .catch(() => {
            // Silently ignore — draft area listing failures are non-critical.
        });
    }
}
