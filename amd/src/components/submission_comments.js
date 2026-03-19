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
 * Submission comments component - chat icon with popout for viewing/posting comments.
 *
 * @module     local_unifiedgrader/components/submission_comments
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent} from 'core/reactive';

export default class extends BaseComponent {

    /**
     * Component creation hook.
     */
    create() {
        this.name = 'submission_comments';
        this.selectors = {
            TOGGLE_BTN: '[data-action="toggle-comments"]',
            BADGE: '[data-region="comment-count-badge"]',
            POPOUT: '[data-region="comments-popout"]',
            COMMENTS_LIST: '[data-region="comments-list"]',
            COMMENT_INPUT: '[data-region="comment-input"]',
            POST_BTN: '[data-action="post-comment"]',
        };
        this._popoutVisible = false;
        this._outsideClickHandler = null;
    }

    /**
     * Register state watchers.
     *
     * @return {Array}
     */
    getWatchers() {
        return [
            {watch: 'submissionComments:updated', handler: this._onCommentsUpdated},
        ];
    }

    /**
     * Called when state is first ready.
     *
     * @param {object} state Current state.
     */
    stateReady(state) {
        this._setupEventListeners();
        this._updateBadge(state.submissionComments.count);
    }

    /**
     * Set up DOM event listeners.
     */
    _setupEventListeners() {
        const toggleBtn = this.getElement(this.selectors.TOGGLE_BTN);
        if (toggleBtn) {
            toggleBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this._togglePopout();
            });
        }
    }

    /**
     * Handle submissionComments state updates.
     *
     * @param {object} args Watcher args.
     * @param {object} args.state Current state.
     */
    _onCommentsUpdated({state}) {
        this._updateBadge(state.submissionComments.count);
        if (this._popoutVisible && state.submissionComments.loaded) {
            this._renderComments(state);
        }
    }

    /**
     * Update the comment count badge.
     *
     * @param {number} count Comment count.
     */
    _updateBadge(count) {
        const badge = this.getElement(this.selectors.BADGE);
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

    /**
     * Toggle the comments popout panel.
     */
    _togglePopout() {
        if (this._popoutVisible) {
            this._hidePopout();
        } else {
            this._showPopout();
        }
    }

    /**
     * Show the comments popout and load comments if needed.
     */
    _showPopout() {
        const popout = this.getElement(this.selectors.POPOUT);
        if (!popout) {
            window.console.warn('[submission_comments] _showPopout: popout element not found');
            return;
        }

        this._popoutVisible = true;
        popout.classList.remove('d-none');
        this._buildPopoutStructure(popout);

        const state = this.reactive.state;

        // Load comments if not already loaded for this student.
        if (!state.submissionComments.loaded) {
            this.reactive.dispatch(
                'loadSubmissionComments',
                state.activity.cmid,
                state.currentUser.id,
            );
        } else {
            this._renderComments(state);
        }

        // Close popout when clicking outside.
        this._outsideClickHandler = (e) => {
            if (!this.element.contains(e.target)) {
                this._hidePopout();
            }
        };
        // Delay attaching to avoid the current click triggering it.
        setTimeout(() => {
            document.addEventListener('click', this._outsideClickHandler);
        }, 0);
    }

    /**
     * Hide the comments popout.
     */
    _hidePopout() {
        const popout = this.getElement(this.selectors.POPOUT);
        if (popout) {
            popout.classList.add('d-none');
        }
        this._popoutVisible = false;
        if (this._outsideClickHandler) {
            document.removeEventListener('click', this._outsideClickHandler);
            this._outsideClickHandler = null;
        }
    }

    /**
     * Build the popout's internal structure if not already present.
     *
     * @param {HTMLElement} popout The popout container element.
     */
    _buildPopoutStructure(popout) {
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
                '<input type="text" data-region="comment-input"' +
                    ' class="form-control form-control-sm rounded-pill" placeholder="Type a message...">' +
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
                this._hidePopout();
            });
        }

        // Wire up the post button and input.
        const input = formContainer.querySelector('[data-region="comment-input"]');
        const postBtn = formContainer.querySelector('[data-action="post-comment"]');

        input.addEventListener('input', () => {
            postBtn.disabled = !input.value.trim();
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && input.value.trim()) {
                e.preventDefault();
                this._postComment(input);
            }
        });

        postBtn.addEventListener('click', () => {
            this._postComment(input);
        });
    }

    /**
     * Post a new comment.
     *
     * @param {HTMLInputElement} input The text input element.
     */
    _postComment(input) {
        const content = input.value.trim();
        if (!content) {
            return;
        }
        const state = this.reactive.state;
        input.value = '';
        const postBtn = this.element.querySelector('[data-action="post-comment"]');
        if (postBtn) {
            postBtn.disabled = true;
        }
        this.reactive.dispatch(
            'addSubmissionComment',
            state.activity.cmid,
            state.currentUser.id,
            content,
        );
    }

    /**
     * Render the comments list from state.
     *
     * @param {object} state Current reactive state.
     */
    _renderComments(state) {
        const listEl = this.element.querySelector('[data-region="comments-list"]');
        if (!listEl) {
            window.console.warn('[submission_comments] _renderComments: listEl not found');
            return;
        }

        // Convert StateMap to array.
        const comments = state.submissionComments.comments;
        const commentsList = comments && typeof comments.values === 'function'
            ? [...comments.values()]
            : (Array.isArray(comments) ? comments : []);

        if (commentsList.length === 0) {
            listEl.innerHTML =
                '<div class="text-center text-muted py-4">' +
                '<i class="fa fa-comments-o d-block mb-2" style="font-size: 1.5rem;"></i>' +
                'No comments yet</div>';
        } else {
            listEl.innerHTML = '';
            commentsList.forEach((comment) => {
                listEl.appendChild(this._createCommentElement(comment, state));
            });
            // Scroll to bottom to show latest.
            listEl.scrollTop = listEl.scrollHeight;
        }

        // Update form visibility based on canpost.
        const formEl = this.element.querySelector('[data-region="comments-form"]');
        if (formEl) {
            formEl.classList.toggle('d-none', !state.submissionComments.canpost);
        }
    }

    /**
     * Create a single comment DOM element as a chat bubble.
     *
     * @param {object} comment Comment data from state.
     * @param {object} state Current reactive state.
     * @return {HTMLElement}
     */
    _createCommentElement(comment, state) {
        const currentUserId = parseInt(M.cfg.userId, 10);
        const isOwn = parseInt(comment.userid, 10) === currentUserId;

        const bubble = document.createElement('div');
        bubble.className = 'comment-bubble ' + (isOwn ? 'comment-bubble-own' : 'comment-bubble-other');

        // Meta row: name + time (+ delete button on hover).
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
                this.reactive.dispatch(
                    'deleteSubmissionComment',
                    state.activity.cmid,
                    state.currentUser.id,
                    comment.id,
                );
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
}
