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
 * Non-intrusive submission comments chat bubble for activity overview pages.
 *
 * Injected via PSR-14 hook on quiz and forum view pages when the admin
 * setting enable_submission_comments is ON. Places a small chat icon with
 * badge inside the activity-information region. Clicking it opens a
 * messenger-style popout panel.
 *
 * @module     local_unifiedgrader/activity_comments
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';

/** @type {number} Current logged-in user ID. */
let currentUserId = 0;

/** @type {boolean} Whether the popout is visible. */
let popoutVisible = false;

/** @type {?Function} Outside-click handler reference for cleanup. */
let outsideClickHandler = null;

/** @type {boolean} Whether comments have been loaded at least once. */
let commentsLoaded = false;

/** @type {?HTMLElement} The container element (cached). */
let containerEl = null;

/**
 * Initialise: inject chat bubble into the activity-information region.
 *
 * @param {number} cmid Course module ID.
 * @param {number} userid Student user ID.
 */
export const init = async(cmid, userid) => {
    currentUserId = parseInt(M.cfg.userId, 10);

    const label = await getString('submissioncomments', 'local_unifiedgrader');

    // Find the activity-dates region (preferred) or fall back to activity-information.
    const activityDates = document.querySelector('[data-region="activity-dates"]');
    const activityInfo = activityDates || document.querySelector('[data-region="activity-information"]');
    if (!activityInfo) {
        return;
    }

    // Make the target a flex row so our icon sits to the right of the dates.
    if (activityDates) {
        activityDates.style.display = 'flex';
        activityDates.style.alignItems = 'center';
        activityDates.style.justifyContent = 'space-between';
    }

    // Load initial comment count.
    let initialCount = 0;
    try {
        const result = await Ajax.call([{
            methodname: 'local_unifiedgrader_get_submission_comments',
            args: {cmid, userid},
        }])[0];
        initialCount = result.count || 0;
    } catch {
        // Silently continue — badge will show 0.
    }

    // Build the chat bubble container.
    containerEl = document.createElement('div');
    containerEl.setAttribute('data-region', 'activity-comments');
    containerEl.className = 'local-unifiedgrader-activity-comments position-relative d-inline-flex align-items-center';

    // Chat icon button with badge.
    const iconWrapper = document.createElement('span');
    iconWrapper.className = 'position-relative d-inline-block';

    const toggleBtn = document.createElement('button');
    toggleBtn.type = 'button';
    toggleBtn.className = 'btn btn-link btn-sm p-0 text-muted';
    toggleBtn.title = label;
    toggleBtn.innerHTML = '<i class="fa fa-comments" style="font-size: 1.1rem;"></i>';

    const badge = document.createElement('span');
    badge.setAttribute('data-region', 'comment-count-badge');
    badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger'
        + (initialCount > 0 ? '' : ' d-none');
    badge.style.fontSize = '0.55rem';
    badge.textContent = initialCount > 0 ? String(initialCount) : '';

    iconWrapper.appendChild(toggleBtn);
    iconWrapper.appendChild(badge);

    // Popout container.
    const popout = document.createElement('div');
    popout.setAttribute('data-region', 'comments-popout');
    popout.className = 'd-none local-unifiedgrader-comments-popout';

    containerEl.appendChild(iconWrapper);
    containerEl.appendChild(popout);

    // Insert into the target region.
    activityInfo.appendChild(containerEl);

    // Wire toggle.
    toggleBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        togglePopout(cmid, userid);
    });
};

/**
 * Toggle the comments popout panel.
 *
 * @param {number} cmid Course module ID.
 * @param {number} userid Student user ID.
 */
function togglePopout(cmid, userid) {
    if (popoutVisible) {
        hidePopout();
    } else {
        showPopout(cmid, userid);
    }
}

/**
 * Show the comments popout and load comments if needed.
 *
 * @param {number} cmid Course module ID.
 * @param {number} userid Student user ID.
 */
function showPopout(cmid, userid) {
    const popout = containerEl.querySelector('[data-region="comments-popout"]');
    if (!popout) {
        return;
    }

    popoutVisible = true;
    popout.classList.remove('d-none');
    buildPopoutStructure(popout, cmid, userid);

    if (!commentsLoaded) {
        const listEl = popout.querySelector('[data-region="comments-list"]');
        loadComments(cmid, userid, listEl);
    }

    // Close popout when clicking outside — delayed to avoid current click.
    outsideClickHandler = (e) => {
        if (!containerEl.contains(e.target)) {
            hidePopout();
        }
    };
    setTimeout(() => {
        document.addEventListener('click', outsideClickHandler);
    }, 0);
}

/**
 * Hide the comments popout.
 */
function hidePopout() {
    const popout = containerEl.querySelector('[data-region="comments-popout"]');
    if (popout) {
        popout.classList.add('d-none');
    }
    popoutVisible = false;
    if (outsideClickHandler) {
        document.removeEventListener('click', outsideClickHandler);
        outsideClickHandler = null;
    }
}

/**
 * Build the popout's internal structure if not already present.
 *
 * @param {HTMLElement} popout The popout container element.
 * @param {number} cmid Course module ID.
 * @param {number} userid Student user ID.
 */
function buildPopoutStructure(popout, cmid, userid) {
    if (popout.querySelector('[data-region="comments-list"]')) {
        return;
    }

    const header = document.createElement('div');
    header.className = 'comments-header d-flex justify-content-between align-items-center';
    header.innerHTML =
        '<span class="fw-semibold small">Submission comments</span>' +
        '<button type="button" class="btn-close" data-action="close-comments"' +
        ' aria-label="Close" style="font-size: 0.6rem;"></button>';

    const listContainer = document.createElement('div');
    listContainer.setAttribute('data-region', 'comments-list');
    listContainer.innerHTML =
        '<div class="text-center text-muted py-4">' +
        '<div class="spinner-border spinner-border-sm" role="status"></div>' +
        '</div>';

    const formContainer = document.createElement('div');
    formContainer.setAttribute('data-region', 'comments-form');
    formContainer.innerHTML =
        '<div class="d-flex gap-2 align-items-end">' +
            '<textarea data-region="comment-input" rows="3"' +
                ' class="form-control form-control-sm" placeholder="Type a message..."' +
                ' style="resize: vertical; min-height: 4rem;"></textarea>' +
            '<button type="button" data-action="post-comment"' +
                ' class="btn btn-primary btn-sm rounded-circle' +
                ' d-flex align-items-center justify-content-center"' +
                ' style="width: 32px; height: 32px; flex-shrink: 0;" disabled>' +
                '<i class="fa fa-paper-plane" style="font-size: 0.75rem;"></i>' +
            '</button>' +
        '</div>';

    popout.innerHTML = '';
    popout.appendChild(header);
    popout.appendChild(listContainer);
    popout.appendChild(formContainer);

    // Wire close button.
    const closeBtn = header.querySelector('[data-action="close-comments"]');
    if (closeBtn) {
        closeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            hidePopout();
        });
    }

    // Wire up the post button and input.
    const input = formContainer.querySelector('[data-region="comment-input"]');
    const postBtn = formContainer.querySelector('[data-action="post-comment"]');

    input.addEventListener('input', () => {
        postBtn.disabled = !input.value.trim();
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey && input.value.trim()) {
            e.preventDefault();
            postComment(cmid, userid, input, postBtn, listContainer);
        }
    });

    postBtn.addEventListener('click', () => {
        postComment(cmid, userid, input, postBtn, listContainer);
    });
}

/**
 * Load comments from the server and render them.
 *
 * @param {number} cmid Course module ID.
 * @param {number} userid Student user ID.
 * @param {HTMLElement} listEl The comments list container element.
 */
async function loadComments(cmid, userid, listEl) {
    try {
        const result = await Ajax.call([{
            methodname: 'local_unifiedgrader_get_submission_comments',
            args: {cmid, userid},
        }])[0];

        renderComments(result.comments, listEl, cmid, userid);
        updateBadge(result.count);
        commentsLoaded = true;
    } catch (error) {
        Notification.exception(error);
    }
}

/**
 * Render the comments list.
 *
 * @param {Array} comments Array of comment objects.
 * @param {HTMLElement} listEl The comments list container element.
 * @param {number} cmid Course module ID.
 * @param {number} userid Student user ID.
 */
function renderComments(comments, listEl, cmid, userid) {
    if (!listEl) {
        return;
    }

    if (!comments || comments.length === 0) {
        listEl.innerHTML =
            '<div class="text-center text-muted py-4">' +
            '<i class="fa fa-comments-o d-block mb-2" style="font-size: 1.5rem;"></i>' +
            'No comments yet</div>';
        return;
    }

    listEl.innerHTML = '';
    comments.forEach((comment) => {
        listEl.appendChild(createBubble(comment, cmid, userid, listEl));
    });
    listEl.scrollTop = listEl.scrollHeight;
}

/**
 * Create a chat bubble element for a single comment.
 *
 * @param {object} comment Comment data object.
 * @param {number} cmid Course module ID.
 * @param {number} userid Student user ID.
 * @param {HTMLElement} listEl The comments list container element.
 * @return {HTMLElement} The bubble DOM element.
 */
function createBubble(comment, cmid, userid, listEl) {
    const isOwn = parseInt(comment.userid, 10) === currentUserId;

    const bubble = document.createElement('div');
    bubble.className = 'comment-bubble ' + (isOwn ? 'comment-bubble-own' : 'comment-bubble-other');

    // Meta row: name + time + delete.
    const meta = document.createElement('div');
    meta.className = 'comment-meta';

    const nameSpan = document.createElement('span');
    nameSpan.className = 'fw-semibold';
    nameSpan.textContent = isOwn ? 'You' : comment.fullname;

    const timeSpan = document.createElement('span');
    timeSpan.textContent = comment.time;

    if (isOwn) {
        meta.appendChild(timeSpan);
        meta.appendChild(nameSpan);
    } else {
        meta.appendChild(nameSpan);
        meta.appendChild(timeSpan);
    }

    if (comment.candelete) {
        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'btn btn-link btn-sm p-0 text-danger comment-delete-btn';
        deleteBtn.title = 'Delete';
        deleteBtn.innerHTML = '<i class="fa fa-trash-o"></i>';
        deleteBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            deleteComment(cmid, userid, comment.id, listEl);
        });
        meta.appendChild(deleteBtn);
    }

    bubble.appendChild(meta);

    // Content body bubble.
    const body = document.createElement('div');
    body.className = 'comment-body';
    // Trust boundary: comment.content is sanitized server-side by Moodle core's
    // comment API (format_text). innerHTML is intentional to preserve formatting.
    body.innerHTML = comment.content;
    bubble.appendChild(body);

    return bubble;
}

/**
 * Post a new comment.
 *
 * @param {number} cmid Course module ID.
 * @param {number} userid Student user ID.
 * @param {HTMLInputElement} input The text input element.
 * @param {HTMLButtonElement} postBtn The post button element.
 * @param {HTMLElement} listEl The comments list container element.
 */
async function postComment(cmid, userid, input, postBtn, listEl) {
    const content = input.value.trim();
    if (!content) {
        return;
    }

    input.value = '';
    postBtn.disabled = true;

    try {
        await Ajax.call([{
            methodname: 'local_unifiedgrader_add_submission_comment',
            args: {cmid, userid, content},
        }])[0];

        await loadComments(cmid, userid, listEl);
    } catch (error) {
        Notification.exception(error);
    }
}

/**
 * Delete a comment.
 *
 * @param {number} cmid Course module ID.
 * @param {number} userid Student user ID.
 * @param {number} commentid Comment ID to delete.
 * @param {HTMLElement} listEl The comments list container element.
 */
async function deleteComment(cmid, userid, commentid, listEl) {
    try {
        await Ajax.call([{
            methodname: 'local_unifiedgrader_delete_submission_comment',
            args: {cmid, commentid},
        }])[0];

        await loadComments(cmid, userid, listEl);
    } catch (error) {
        Notification.exception(error);
    }
}

/**
 * Update the comment count badge.
 *
 * @param {number} count Comment count.
 */
function updateBadge(count) {
    if (!containerEl) {
        return;
    }
    const badge = containerEl.querySelector('[data-region="comment-count-badge"]');
    if (!badge) {
        return;
    }
    if (count > 0) {
        badge.textContent = count;
        badge.classList.remove('d-none');
    } else {
        badge.classList.add('d-none');
    }
}
