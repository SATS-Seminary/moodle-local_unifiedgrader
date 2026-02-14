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
 * Main grading controller - initialises the reactive state and components.
 *
 * @module     local_unifiedgrader/grader
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Reactive} from 'core/reactive';
import Mutations from 'local_unifiedgrader/mutations';
import PreviewPanel from 'local_unifiedgrader/components/preview_panel';
import MarkingPanel from 'local_unifiedgrader/components/marking_panel';
import StudentNavigator from 'local_unifiedgrader/components/student_navigator';

/** @type {Reactive|null} */
let reactiveInstance = null;

/**
 * Initialise the grading interface.
 *
 * @param {string} containerId The DOM element ID of the main container.
 */
export const init = (containerId) => {
    const container = document.getElementById(containerId);
    if (!container) {
        return;
    }

    // Read server-rendered initial data from data attributes.
    const cmid = parseInt(container.dataset.cmid, 10);
    const courseid = parseInt(container.dataset.courseid, 10);
    const userid = parseInt(container.dataset.userid, 10);
    const maxgrade = parseFloat(container.dataset.maxgrade) || 100;
    const gradingmethod = container.dataset.gradingmethod || 'simple';
    const canviewnotes = container.dataset.canviewnotes === '1';
    const canmanagenotes = container.dataset.canmanagenotes === '1';

    const hasGroupMode = container.dataset.hasgroupmode === '1';
    const currentGroup = parseInt(container.dataset.currentgroup, 10) || 0;

    let activityinfo = {};
    let participants = [];
    let groups = [];

    try {
        activityinfo = JSON.parse(container.dataset.activityinfo || '{}');
    } catch (e) {
        activityinfo = {};
    }

    try {
        participants = JSON.parse(container.dataset.participants || '[]');
    } catch (e) {
        participants = [];
    }

    try {
        groups = JSON.parse(container.dataset.groups || '[]');
    } catch (e) {
        groups = [];
    }

    // Create the reactive instance.
    const eventName = 'local_unifiedgrader:statechanged';
    const mutations = new Mutations();
    reactiveInstance = new Reactive({
        name: 'local_unifiedgrader',
        eventName: eventName,
        eventDispatch: (detail, target) => {
            (target ?? container).dispatchEvent(new CustomEvent(eventName, {
                bubbles: true,
                detail: detail,
            }));
        },
        target: container,
        mutations: mutations,
    });

    // Build initial state.
    // Moodle's reactive StateManager requires all root-level values to be
    // objects or "sets" (arrays of objects with mandatory `id` fields).
    // Primitives and null cannot be proxied.
    const initialState = {
        activity: Object.assign({cmid: cmid, courseid: courseid}, activityinfo),
        participants: participants,
        currentUser: {id: userid},
        submission: {
            userid: 0,
            status: '',
            content: '',
            files: [],
            onlinetext: '',
            timecreated: 0,
            timemodified: 0,
            attemptnumber: 0,
        },
        grade: {
            grade: null,
            feedback: '',
            feedbackformat: 1,
            rubricdata: '',
            gradingdefinition: '',
            timegraded: 0,
            grader: 0,
        },
        notes: [],
        commentLibrary: [],
        groups: groups,
        filters: {
            status: 'all',
            group: currentGroup,
            sort: 'submittedat',
            sortdir: 'asc',
            search: '',
            hasGroupMode: hasGroupMode,
        },
        ui: {
            loading: false,
            saving: false,
            maxgrade: maxgrade,
            gradingmethod: gradingmethod,
            canviewnotes: canviewnotes,
            canmanagenotes: canmanagenotes,
        },
    };

    reactiveInstance.setInitialState(initialState);

    // Register reactive components.
    const previewEl = container.querySelector('[data-region="preview-panel"]');
    if (previewEl) {
        new PreviewPanel({
            element: previewEl,
            reactive: reactiveInstance,
        });
    }

    const markingEl = container.querySelector('[data-region="marking-panel"]');
    if (markingEl) {
        new MarkingPanel({
            element: markingEl,
            reactive: reactiveInstance,
        });
    }

    const navEl = container.querySelector('[data-region="student-navigator"]');
    if (navEl) {
        new StudentNavigator({
            element: navEl,
            reactive: reactiveInstance,
        });
    }

    // Load the first student's data.
    if (userid) {
        reactiveInstance.dispatch('loadStudent', cmid, userid);
    }

    // Load comment library.
    reactiveInstance.dispatch('loadCommentLibrary', courseid);

    // Layout toggle handler.
    const layoutToggle = container.querySelector('[data-region="layout-toggle"]');
    if (layoutToggle) {
        layoutToggle.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-action]');
            if (!btn) {
                return;
            }
            const action = btn.dataset.action;

            // Remove existing layout classes.
            container.classList.remove('layout-preview-only', 'layout-grade-only');

            // Apply the selected layout.
            if (action === 'layout-preview') {
                container.classList.add('layout-preview-only');
            } else if (action === 'layout-grade') {
                container.classList.add('layout-grade-only');
            }

            // Update active button state.
            layoutToggle.querySelectorAll('.btn').forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');
        });
    }
};
