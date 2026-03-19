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
 * Replaces Moodle's core submission comments widget on the student
 * assignment view with the Unified Grader's messenger-style comments.
 *
 * Injected via PSR-14 hook when the admin setting
 * enable_submission_comments is ON. Hides the core .commentscontainer
 * row and inserts an inline chat widget in its place.
 *
 * @module     local_unifiedgrader/assignment_comments
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';

/** @type {number} Current logged-in user ID. */
let currentUserId = 0;

/**
 * Initialise: hide core comment widget and inject replacement.
 *
 * @param {number} cmid Course module ID.
 * @param {number} userid Student user ID.
 */
export const init = async(cmid, userid) => {
    currentUserId = parseInt(M.cfg.userId, 10);

    const label = await getString('submissioncomments', 'local_unifiedgrader');

    // Find the core comments container and its parent <tr>.
    const coreContainer = document.querySelector('.commentscontainer');
    let targetTable = null;
    let insertRef = null;

    if (coreContainer) {
        const coreRow = coreContainer.closest('tr');
        if (coreRow) {
            coreRow.style.display = 'none';
            targetTable = coreRow.closest('table');
            insertRef = coreRow;
        }
    }

    // Fallback: if core comments plugin is disabled, find the submission status table.
    if (!targetTable) {
        const statusDiv = document.querySelector('.submissionstatustable');
        if (statusDiv) {
            targetTable = statusDiv.querySelector('table');
        }
    }

    if (!targetTable) {
        return;
    }

    // Build replacement row.
    const newRow = document.createElement('tr');

    const th = document.createElement('th');
    th.className = 'cell c0';
    th.scope = 'row';
    th.textContent = label;

    const td = document.createElement('td');
    td.className = 'cell c1 lastcol';

    newRow.appendChild(th);
    newRow.appendChild(td);

    // Insert after hidden row, or append to tbody.
    if (insertRef && insertRef.parentNode) {
        insertRef.parentNode.insertBefore(newRow, insertRef.nextSibling);
    } else {
        const tbody = targetTable.querySelector('tbody') || targetTable;
        tbody.appendChild(newRow);
    }

    // Build and populate the widget.
    const widget = buildWidget(cmid, userid);
    td.appendChild(widget);

    const listEl = widget.querySelector('[data-region="comments-list"]');
    loadComments(cmid, userid, listEl);
};

/**
 * Build the inline chat widget DOM.
 *
 * @param {number} cmid Course module ID.
 * @param {number} userid Student user ID.
 * @return {HTMLElement} The widget container element.
 */
function buildWidget(cmid, userid) {
    const container = document.createElement('div');
    container.className = 'local-unifiedgrader-inline-comments';

    // Comment list.
    const listEl = document.createElement('div');
    listEl.setAttribute('data-region', 'comments-list');
    listEl.innerHTML =
        '<div class="text-center text-muted py-4">' +
        '<div class="spinner-border spinner-border-sm" role="status"></div>' +
        '</div>';

    // Input form.
    const formEl = document.createElement('div');
    formEl.setAttribute('data-region', 'comments-form');
    formEl.innerHTML =
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

    container.appendChild(listEl);
    container.appendChild(formEl);

    // Wire input and post button.
    const input = formEl.querySelector('[data-region="comment-input"]');
    const postBtn = formEl.querySelector('[data-action="post-comment"]');

    input.addEventListener('input', () => {
        postBtn.disabled = !input.value.trim();
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey && input.value.trim()) {
            e.preventDefault();
            postComment(cmid, userid, input, postBtn, listEl);
        }
    });

    postBtn.addEventListener('click', () => {
        postComment(cmid, userid, input, postBtn, listEl);
    });

    return container;
}

/**
 * Load comments from the server.
 *
 * @param {number} cmid Course module ID.
 * @param {number} userid Student user ID.
 * @param {HTMLElement} listEl The comments list container.
 */
async function loadComments(cmid, userid, listEl) {
    try {
        const result = await Ajax.call([{
            methodname: 'local_unifiedgrader_get_submission_comments',
            args: {cmid, userid},
        }])[0];

        renderComments(result.comments, listEl, cmid, userid);
    } catch (error) {
        Notification.exception(error);
    }
}

/**
 * Render the comment list.
 *
 * @param {Array} comments Comment objects.
 * @param {HTMLElement} listEl The comments list container.
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
 * @param {object} comment Comment data.
 * @param {number} cmid Course module ID.
 * @param {number} userid Student user ID.
 * @param {HTMLElement} listEl The comments list container.
 * @return {HTMLElement} The bubble DOM element.
 */
function createBubble(comment, cmid, userid, listEl) {
    const isOwn = parseInt(comment.userid, 10) === currentUserId;

    const bubble = document.createElement('div');
    bubble.className = 'comment-bubble ' + (isOwn ? 'comment-bubble-own' : 'comment-bubble-other');

    // Meta row.
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

    const body = document.createElement('div');
    body.className = 'comment-body';
    // Trust boundary: content is sanitized server-side.
    body.innerHTML = comment.content;
    bubble.appendChild(body);

    return bubble;
}

/**
 * Post a new comment.
 *
 * @param {number} cmid Course module ID.
 * @param {number} userid Student user ID.
 * @param {HTMLInputElement} input The text input.
 * @param {HTMLButtonElement} postBtn The post button.
 * @param {HTMLElement} listEl The comments list container.
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
 * @param {number} commentid Comment ID.
 * @param {HTMLElement} listEl The comments list container.
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
