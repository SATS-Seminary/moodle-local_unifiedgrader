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
            const [submissionData, gradeData, notes] = await Promise.all([
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
            ]);

            stateManager.setReadOnly(false);
            // Use Object.assign to update properties on the existing proxy.
            // This fires submission:updated / grade:updated events that watchers expect.
            // Replacing the whole object (state.X = newObj) would fire state.X:updated instead.
            Object.assign(stateManager.state.submission, submissionData);
            Object.assign(stateManager.state.grade, gradeData);
            // Notes is a StateMap (array with id fields) — must replace entirely.
            // Watcher uses state.notes:updated to catch this.
            stateManager.state.notes = notes;
            stateManager.state.ui.loading = false;
            stateManager.setReadOnly(true);
        } catch (error) {
            Notification.exception(error);
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
     * @param {string} advancedgradingdata JSON string of advanced grading data.
     */
    async saveGrade(stateManager, cmid, userid, grade, feedback, advancedgradingdata) {
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
                    advancedgradingdata: advancedgradingdata || '',
                },
            }])[0];

            // Refresh grade data and participant list after save.
            const [gradeData, participants] = await Promise.all([
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_grade_data',
                    args: {cmid, userid},
                }])[0],
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_participants',
                    args: {
                        cmid,
                        status: stateManager.state.filters.status,
                        group: stateManager.state.filters.group,
                        search: stateManager.state.filters.search,
                        sort: stateManager.state.filters.sort,
                        sortdir: stateManager.state.filters.sortdir,
                    },
                }])[0],
            ]);

            stateManager.setReadOnly(false);
            Object.assign(stateManager.state.grade, gradeData);
            stateManager.state.participants = participants;
            stateManager.state.ui.saving = false;
            stateManager.setReadOnly(true);
        } catch (error) {
            Notification.exception(error);
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
                    group: stateManager.state.filters.group,
                    search: stateManager.state.filters.search,
                    sort: stateManager.state.filters.sort,
                    sortdir: stateManager.state.filters.sortdir,
                },
            }])[0];

            stateManager.setReadOnly(false);
            stateManager.state.participants = participants;
            stateManager.setReadOnly(true);
        } catch (error) {
            Notification.exception(error);
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
            Notification.exception(error);
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
            Notification.exception(error);
        }
    }

    /**
     * Load the comment library for a course.
     *
     * @param {object} stateManager The reactive state manager.
     * @param {number} courseid Course ID.
     */
    async loadCommentLibrary(stateManager, courseid) {
        try {
            const comments = await Ajax.call([{
                methodname: 'local_unifiedgrader_get_comment_library',
                args: {courseid},
            }])[0];

            stateManager.setReadOnly(false);
            stateManager.state.commentLibrary = comments;
            stateManager.setReadOnly(true);
        } catch (error) {
            Notification.exception(error);
        }
    }

    /**
     * Save a comment to the library.
     *
     * @param {object} stateManager The reactive state manager.
     * @param {number} courseid Course ID.
     * @param {string} content Comment content.
     */
    async saveCommentToLibrary(stateManager, courseid, content) {
        try {
            await Ajax.call([{
                methodname: 'local_unifiedgrader_save_comment_to_library',
                args: {courseid, content, commentid: 0},
            }])[0];

            // Reload the library.
            const comments = await Ajax.call([{
                methodname: 'local_unifiedgrader_get_comment_library',
                args: {courseid},
            }])[0];

            stateManager.setReadOnly(false);
            stateManager.state.commentLibrary = comments;
            stateManager.setReadOnly(true);
        } catch (error) {
            Notification.exception(error);
        }
    }
}
