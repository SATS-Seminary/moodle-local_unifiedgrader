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
import {get_string as getString} from 'core/str';
import Mutations from 'local_unifiedgrader/mutations';
import PreviewPanel from 'local_unifiedgrader/components/preview_panel';
import MarkingPanel from 'local_unifiedgrader/components/marking_panel';
import TranslationPanel from 'local_unifiedgrader/components/translation_panel';
import SegComments from 'local_unifiedgrader/components/seg_comments';
import StudentNavigator from 'local_unifiedgrader/components/student_navigator';
import SubmissionComments from 'local_unifiedgrader/components/submission_comments';
import PostGradesToggle from 'local_unifiedgrader/components/post_grades_toggle';
import Ajax from 'core/ajax';
import * as DirtyTracker from 'local_unifiedgrader/dirty_tracker';
import * as OfflineCache from 'local_unifiedgrader/offline_cache';
import * as SaveQueue from 'local_unifiedgrader/save_queue';
import {getInstanceForElementId} from 'editor_tiny/editor';

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
    const coursecode = container.dataset.coursecode || '';
    const userid = parseInt(container.dataset.userid, 10);
    const maxgrade = parseFloat(container.dataset.maxgrade) || 100;
    const gradingmethod = container.dataset.gradingmethod || 'simple';
    const canviewnotes = container.dataset.canviewnotes === '1';
    const canmanagenotes = container.dataset.canmanagenotes === '1';
    const canrefer = container.dataset.canrefer === '1';

    const hasGroupMode = container.dataset.hasgroupmode === '1';
    const currentGroup = container.dataset.currentgroup || '0';
    let userGroupIds = [];
    try {
        userGroupIds = JSON.parse(container.dataset.usergroupids || '[]');
    } catch (e) {
        userGroupIds = [];
    }
    const draftitemid = parseInt(container.dataset.draftitemid, 10) || 0;
    const feedbackfilesdraftid = parseInt(container.dataset.feedbackfilesdraftid, 10) || 0;
    const hasfeedbackfileplugin = container.dataset.hasfeedbackfileplugin === '1';
    const feedbackfilesclientid = container.dataset.feedbackfilesclientid || '';
    const allowmanualgradeoverride = container.dataset.allowmanualgradeoverride === '1';
    const gradesPosted = container.dataset.gradesposted === '1';
    const gradesHidden = parseInt(container.dataset.gradeshidden, 10) || 0;
    const canloginas = container.dataset.canloginas === '1';
    const hassatsmail = container.dataset.hassatsmail === '1';
    const enableReportForm = container.dataset.enablereportform === '1';
    const reportFormUrl = container.dataset.reportformurl || '';
    const graderFullname = container.dataset.graderfullname || '';
    const coursefullname = container.dataset.coursefullname || '';
    // Saved per teacher, per forum. Rating forums default to the paged
    // in-context view because that is the unit being graded; everything else
    // keeps the flat list it has always had.
    const forumviewmode = container.dataset.forumviewmode || 'flat';

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
        activity: Object.assign({cmid: cmid, courseid: courseid, coursecode: coursecode}, activityinfo),
        participants: participants,
        currentUser: {id: userid},
        submission: {
            userid: 0,
            status: '',
            content: '',
            hascontent: false,
            files: [],
            onlinetext: '',
            onlinetexthtml: '',
            timecreated: 0,
            timemodified: 0,
            attemptnumber: 0,
            attempts: [],
            plagiarismlinks: [],
        },
        grade: {
            grade: null,
            feedback: '',
            feedbackdraft: '',
            feedbackformat: 1,
            rubricdata: '',
            gradingdefinition: '',
            timegraded: 0,
            grader: 0,
        },
        notes: [],
        penalties: [],
        referrals: [],
        // Rating-graded forums only: one entry per post the student wrote,
        // plus the gradebook value mod_forum derived from them.
        postratings: {
            posts: [],
            gradebookgrade: null,
            gradebookdisplay: '',
            aggregatelabel: '',
            // Declared up front: the reactive proxy only tracks keys that
            // existed when the state was set, so a rejected rating written to a
            // missing key would never reach the watcher.
            error: '',
            loaded: false,
        },
        // The threaded discussion behind the student's posts, plus which post
        // is currently in focus. currentpostid is the single source of truth
        // shared by the preview pager and the marking-panel rating rows —
        // neither component talks to the other, they both watch this.
        forumcontext: {
            discussions: [],
            targetpostids: [],
            currentpostid: 0,
            mode: forumviewmode,
            loaded: false,
        },
        submissionComments: {
            count: 0,
            canpost: false,
            loaded: false,
            comments: [],
        },
        groups: groups,
        userGroupIds: {ids: userGroupIds},
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
            posting: false,
            gradesPosted: gradesPosted,
            gradesHidden: gradesHidden,
            draftitemid: draftitemid,
            feedbackfilesdraftid: feedbackfilesdraftid,
            hasfeedbackfileplugin: hasfeedbackfileplugin,
            feedbackfilesclientid: feedbackfilesclientid,
            maxgrade: maxgrade,
            gradingmethod: gradingmethod,
            allowmanualgradeoverride: allowmanualgradeoverride,
            canviewnotes: canviewnotes,
            canmanagenotes: canmanagenotes,
            canrefer: canrefer,
            canloginas: canloginas,
            hassatsmail: hassatsmail,
            enableReportForm: enableReportForm,
            reportFormUrl: reportFormUrl,
            graderFullname: graderFullname,
            coursefullname: coursefullname,
        },
    };

    reactiveInstance.setInitialState(initialState);

    // Install dirty-state tracker (beforeunload protection).
    DirtyTracker.install();

    // Initialize IndexedDB offline cache.
    OfflineCache.init();

    // Start the save retry queue.
    SaveQueue.start();

    // Proactively cache the comment library for offline resilience.
    _prewarmCommentLibraryCache(coursecode);

    // Wire up the save status indicator in the header bar.
    _setupSaveStatusIndicator(container);

    // Wire up connection lost / save queue banner.
    _setupConnectionBanner(container);

    // Register reactive components.
    const previewEl = container.querySelector('[data-region="preview-panel"]');
    let previewPanel = null;
    if (previewEl) {
        previewPanel = new PreviewPanel({
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

    // Grader translation toggle (assign + local_nida). Degrades silently when
    // local_nida is absent (the external reports status 'unavailable').
    const translationEl = container.querySelector('[data-region="translation-panel"]');
    if (translationEl) {
        new TranslationPanel({
            element: translationEl,
            reactive: reactiveInstance,
        });
    }

    // Segment-anchored comments (assign + local_nida). Watches the translation
    // view; highlights commented phrases inline and opens a floating composer over
    // the page at the grader's text selection. Renders no docked UI of its own.
    const segCommentsEl = container.querySelector('[data-region="seg-comments"]');
    if (segCommentsEl) {
        new SegComments({
            element: segCommentsEl,
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

    const commentsEl = container.querySelector('[data-region="submission-comments"]');
    if (commentsEl) {
        new SubmissionComments({
            element: commentsEl,
            reactive: reactiveInstance,
        });
    }

    const postGradesEl = container.querySelector('[data-region="post-grades-toggle"]');
    if (postGradesEl) {
        new PostGradesToggle({
            element: postGradesEl,
            reactive: reactiveInstance,
        });
    }

    // Load the first student's data, then check for cached recovery data.
    if (userid) {
        reactiveInstance.dispatch('loadStudent', cmid, userid);
        // Ensure the URL always includes the current userid so it's shareable.
        const pageUrl = new URL(window.location.href);
        if (!pageUrl.searchParams.has('userid') || pageUrl.searchParams.get('userid') !== String(userid)) {
            pageUrl.searchParams.set('userid', userid);
            window.history.replaceState(null, '', pageUrl.toString());
        }
        // Recovery check runs after a delay to let the initial load populate the state.
        setTimeout(() => _checkRecovery(container, reactiveInstance, cmid, userid), 2000);
    }

    // Layout toggle handler.
    const layoutToggle = container.querySelector('[data-region="layout-toggle"]');
    if (layoutToggle) {
        layoutToggle.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-action]');
            if (!btn) {
                return;
            }
            const action = btn.dataset.action;

            // Dual file view is a toggle that composes with the three layout
            // choices (it changes what the preview pane holds, not the page
            // layout), so it neither clears nor joins their active state.
            if (action === 'layout-multiview') {
                const on = btn.getAttribute('aria-pressed') !== 'true';
                btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                btn.classList.toggle('active', on);
                previewPanel?.setMultiview(on);
                return;
            }

            // Remove existing layout classes.
            container.classList.remove('layout-preview-only', 'layout-grade-only');

            // Apply the selected layout.
            if (action === 'layout-preview') {
                container.classList.add('layout-preview-only');
            } else if (action === 'layout-grade') {
                container.classList.add('layout-grade-only');
            }

            // Update active button state. The dual-file toggle is excluded: it is
            // an independent on/off, so picking a layout must not switch it off.
            layoutToggle.querySelectorAll('.btn:not([data-action="layout-multiview"])')
                .forEach((b) => b.classList.remove('active'));
            btn.classList.add('active');
        });
    }
};

/**
 * Set up the save status indicator in the existing [data-region="save-status"] element.
 *
 * @param {HTMLElement} container Main grading container.
 */
const _setupSaveStatusIndicator = (container) => {
    const statusEl = container.querySelector('[data-region="save-status"]');
    if (!statusEl) {
        return;
    }

    /** @type {boolean} Track previous saving state to detect transitions. */
    let wasSaving = false;

    // Prefetch strings.
    const strings = {};
    getString('allchangessaved', 'local_unifiedgrader').then(s => { strings.saved = s; }).catch(() => {});
    getString('editing', 'local_unifiedgrader').then(s => { strings.editing = s; }).catch(() => {});
    getString('saving', 'local_unifiedgrader').then(s => { strings.saving = s; }).catch(() => {});
    getString('offlinesavedlocally', 'local_unifiedgrader').then(s => { strings.offline = s; }).catch(() => {});

    /**
     * Set status indicator using DOM manipulation.
     *
     * @param {string} iconClass Font Awesome icon classes.
     * @param {string} colorClass Bootstrap text color class.
     * @param {string} text Status text to display.
     */
    const setStatus = (iconClass, colorClass, text) => {
        statusEl.textContent = '';
        const icon = document.createElement('i');
        icon.className = iconClass + ' ' + colorClass + ' me-1';
        const span = document.createElement('span');
        span.className = colorClass;
        span.textContent = text;
        statusEl.appendChild(icon);
        statusEl.appendChild(span);
    };

    const updateStatus = () => {
        const queueLength = SaveQueue.getQueueLength();
        const online = SaveQueue.isOnline();
        const dirty = DirtyTracker.isDirty();

        if (!online || queueLength > 0) {
            setStatus('fa fa-exclamation-triangle', 'text-warning', strings.offline || 'Offline — saved locally');
        } else if (wasSaving) {
            // Just finished saving — show "All saved" briefly.
            wasSaving = false;
            setStatus('fa fa-check', 'text-success', strings.saved || 'All changes saved');
        } else if (dirty) {
            setStatus('fa fa-pencil', 'text-muted', strings.editing || 'Editing...');
        } else {
            setStatus('fa fa-check', 'text-success', strings.saved || 'All changes saved');
        }
    };

    // Listen to reactive state changes for saving flag.
    container.addEventListener('local_unifiedgrader:statechanged', (e) => {
        const detail = e.detail;
        if (detail?.action === 'ui:updated') {
            const state = reactiveInstance?.state;
            if (state?.ui?.saving) {
                wasSaving = true;
                setStatus('fa fa-spinner fa-spin', 'text-primary', strings.saving || 'Saving...');
                return;
            }
        }
        updateStatus();
    });

    DirtyTracker.onDirtyChange(updateStatus);
    SaveQueue.onStatusChange(updateStatus);

    // Initial render.
    updateStatus();
};

/**
 * Set up the connection lost banner.
 *
 * @param {HTMLElement} container Main grading container.
 */
const _setupConnectionBanner = (container) => {
    SaveQueue.onStatusChange((queueLength, online) => {
        const existing = container.querySelector('[data-region="connection-banner"]');

        if (!online || queueLength > 0) {
            if (!existing) {
                getString('connectionlost', 'local_unifiedgrader').then(msg => {
                    // Re-check in case status changed during string fetch.
                    if (container.querySelector('[data-region="connection-banner"]')) {
                        return;
                    }
                    const banner = document.createElement('div');
                    banner.dataset.region = 'connection-banner';
                    banner.className = 'alert alert-warning alert-dismissible mb-0 rounded-0 py-2 px-3';
                    banner.innerHTML = '<i class="fa fa-exclamation-triangle me-2"></i>'
                        + '<span>' + msg + '</span>'
                        + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                    container.insertBefore(banner, container.firstChild);
                }).catch(() => {});
            }
        } else if (existing) {
            existing.remove();
        }
    });
};

/**
 * Pre-warm the IndexedDB comment library cache in the background.
 *
 * Fetches all comments, tags, and shared comments via AJAX and stores them
 * in IndexedDB. This ensures the cache is populated even if the teacher never
 * explicitly opens the comment library popout or modal before going offline.
 *
 * Runs fire-and-forget — errors are silently ignored.
 *
 * @param {string} coursecode The current course code.
 */
const _prewarmCommentLibraryCache = async(coursecode) => {
    if (!OfflineCache.isAvailable()) {
        return;
    }
    try {
        const [comments, tags, shared] = await Promise.all([
            Ajax.call([{
                methodname: 'local_unifiedgrader_get_library_comments',
                args: {coursecode: '', tagid: 0},
                failurealert: false,
            }])[0],
            Ajax.call([{
                methodname: 'local_unifiedgrader_get_library_tags',
                args: {},
                failurealert: false,
            }])[0],
            Ajax.call([{
                methodname: 'local_unifiedgrader_get_shared_library',
                args: {tagid: 0},
                failurealert: false,
            }])[0],
        ]);

        // Cache the full library (same keys as the modal).
        OfflineCache.save(0, 0, 'clib_all', comments);
        OfflineCache.save(0, 0, 'clib_tags', tags);
        OfflineCache.save(0, 0, 'clib_shared', shared);

        // Also cache the course-specific subset (same key as the popout).
        if (coursecode) {
            const courseComments = comments.filter(c => c.coursecode === coursecode);
            OfflineCache.save(0, 0, 'clib_cc_' + coursecode, courseComments);
        }

        window.console.info('[grader] Comment library cache pre-warmed');
    } catch (e) {
        // Silently ignore — the cache will be populated when the user opens a comment panel.
        window.console.info('[grader] Comment library cache pre-warm skipped (offline or error)');
    }
};

/**
 * Check IndexedDB for cached data newer than the server and offer recovery.
 *
 * @param {HTMLElement} container Main grading container.
 * @param {Reactive} reactive The reactive instance.
 * @param {number} cmid Course module ID.
 * @param {number} userid User ID.
 */
const _checkRecovery = async(container, reactive, cmid, userid) => {
    if (!OfflineCache.isAvailable()) {
        return;
    }

    const cached = await OfflineCache.loadAll(cmid, userid);
    if (cached.length === 0) {
        return;
    }

    // Compare against server timestamp (PHP seconds → JS milliseconds).
    const serverTimestamp = (reactive.state?.grade?.timegraded || 0) * 1000;
    const hasNewer = cached.some(entry => entry.timestamp > serverTimestamp);

    if (!hasNewer) {
        // Cache is stale — clean it up.
        await OfflineCache.removeAll(cmid, userid);
        return;
    }

    // Show recovery banner.
    const [msgText, restoreText, discardText] = await Promise.all([
        getString('recoveredunsavedchanges', 'local_unifiedgrader').catch(() => 'Recovered unsaved changes.'),
        getString('restore', 'local_unifiedgrader').catch(() => 'Restore'),
        getString('discard', 'local_unifiedgrader').catch(() => 'Discard'),
    ]);

    const banner = document.createElement('div');
    banner.dataset.region = 'recovery-banner';
    banner.className = 'alert alert-info mb-0 rounded-0 py-2 px-3 d-flex align-items-center justify-content-between';
    banner.innerHTML = '<div><i class="fa fa-info-circle me-2"></i><span>' + msgText + '</span></div>'
        + '<div class="d-flex gap-2">'
        + '<button type="button" class="btn btn-sm btn-primary" data-action="restore-cache">'
        + '<i class="fa fa-undo me-1"></i>' + restoreText + '</button>'
        + '<button type="button" class="btn btn-sm btn-outline-secondary" data-action="discard-cache">'
        + discardText + '</button>'
        + '</div>';

    banner.querySelector('[data-action="restore-cache"]').addEventListener('click', () => {
        _restoreFromCache(reactive, cached);
        banner.remove();
    });

    banner.querySelector('[data-action="discard-cache"]').addEventListener('click', async() => {
        await OfflineCache.removeAll(cmid, userid);
        banner.remove();
    });

    container.insertBefore(banner, container.firstChild);
};

/**
 * Restore cached data into the grading form.
 *
 * @param {Reactive} reactive The reactive instance.
 * @param {object[]} cached Array of cache entries to restore.
 */
const _restoreFromCache = (reactive, cached) => {
    for (const entry of cached) {
        switch (entry.type) {
            case 'grade': {
                const gradeInput = document.querySelector('[data-action="grade-input"]');
                const scaleInput = document.querySelector('[data-action="scale-input"]');
                if (gradeInput && entry.data?.value !== undefined) {
                    gradeInput.value = entry.data.value;
                    gradeInput.dispatchEvent(new Event('input'));
                } else if (scaleInput && entry.data?.value !== undefined) {
                    scaleInput.value = entry.data.value;
                    scaleInput.dispatchEvent(new Event('change'));
                }
                DirtyTracker.markDirty('grade');
                break;
            }
            case 'feedback': {
                const textarea = document.querySelector('[data-action="feedback-input"]');
                if (textarea && entry.data?.html !== undefined) {
                    const editor = getInstanceForElementId(textarea.id);
                    if (editor) {
                        editor.setContent(entry.data.html);
                    } else {
                        textarea.value = entry.data.html;
                    }
                }
                DirtyTracker.markDirty('feedback');
                break;
            }
            case 'annotations': {
                document.dispatchEvent(new CustomEvent('unifiedgrader:restoreannotations', {
                    detail: entry.data,
                }));
                DirtyTracker.markDirty('annotations');
                break;
            }
        }
    }
};
