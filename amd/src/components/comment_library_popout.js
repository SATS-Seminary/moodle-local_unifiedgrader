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
 * Comment library popout — a floating panel for browsing and inserting comments.
 *
 * This is a standalone class (not a BaseComponent) that manages its own DOM
 * and fetches data via core/ajax. It is instantiated by marking_panel.js.
 *
 * @module     local_unifiedgrader/components/comment_library_popout
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import CommentLibraryModal from 'local_unifiedgrader/comment_library_modal';
import {getInstanceForElementId} from 'editor_tiny/editor';
import * as OfflineCache from 'local_unifiedgrader/offline_cache';

/**
 * Handle AJAX or other errors gracefully.
 * Network errors (server unreachable) produce blank Notification.exception modals.
 *
 * @param {*} err Error object.
 */
const _handleError = (err) => {
    if (err && (err.errorcode || err.debuginfo)) {
        Notification.exception(err);
    } else {
        Notification.alert(getString('error'), getString('error_network', 'local_unifiedgrader'));
        window.console.warn('[comment_library_popout] Network error:', err);
    }
};

/** Color palette for tag badges (must match comment_library_modal.js). */
const TAG_COLORS = [
    {bg: '#6c5ce7', text: '#fff'},
    {bg: '#00b894', text: '#fff'},
    {bg: '#e17055', text: '#fff'},
    {bg: '#0984e3', text: '#fff'},
    {bg: '#e84393', text: '#fff'},
    {bg: '#00cec9', text: '#fff'},
    {bg: '#a29bfe', text: '#fff'},
    {bg: '#fdcb6e', text: '#333'},
];

const _hashString = (str) => {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        hash = ((hash << 5) - hash) + str.charCodeAt(i); // eslint-disable-line no-bitwise
        hash |= 0; // eslint-disable-line no-bitwise
    }
    return Math.abs(hash);
};

const _colorFor = (key, palette) => palette[_hashString(key) % palette.length];

const _applyColor = (el, color) => {
    el.style.backgroundColor = color.bg;
    el.style.color = color.text;
};

export default class CommentLibraryPopout {

    /**
     * @param {string} coursecode The current course code.
     * @param {Function} getLastFocusedField Returns the last-focused field element (or null).
     */
    constructor(coursecode, getLastFocusedField) {
        this._coursecode = coursecode;
        this._getLastFocusedField = getLastFocusedField;
        this._el = null;
        this._visible = false;
        this._comments = [];
        this._tags = [];
        this._activeTagId = 0; // 0 = all
        this._outsideClickHandler = null;
        this._offline = false;
    }

    /**
     * Toggle the popout, positioning it relative to the given anchor button.
     *
     * @param {HTMLElement} anchor The toggle button that was clicked.
     */
    toggle(anchor) {
        if (this._visible) {
            this.hide();
        } else {
            this.show(anchor);
        }
    }

    /**
     * Show the popout, positioned relative to the anchor.
     *
     * @param {HTMLElement} anchor The toggle button element.
     */
    async show(anchor) {
        if (!this._el) {
            this._el = this._buildDOM();
            document.body.appendChild(this._el);
        }

        // Position using fixed coordinates, clamped to viewport.
        const rect = anchor.getBoundingClientRect();
        const POPOUT_WIDTH = 480;
        const POPOUT_MAX_HEIGHT = 600;
        const MARGIN = 8;
        const vw = window.innerWidth;
        const vh = window.innerHeight;

        // Horizontal: right-align to anchor, clamp to viewport.
        let left = Math.max(MARGIN, rect.right - POPOUT_WIDTH);
        if (left + POPOUT_WIDTH > vw - MARGIN) {
            left = vw - POPOUT_WIDTH - MARGIN;
        }

        // Vertical: prefer below anchor, flip above if no room.
        let top = rect.bottom + 6;
        if (top + POPOUT_MAX_HEIGHT > vh - MARGIN) {
            // Try above the anchor.
            const above = rect.top - 6 - POPOUT_MAX_HEIGHT;
            if (above >= MARGIN) {
                top = above;
            } else {
                // Neither fits fully — clamp to bottom of viewport.
                top = Math.max(MARGIN, vh - POPOUT_MAX_HEIGHT - MARGIN);
            }
        }

        this._el.style.top = top + 'px';
        this._el.style.left = Math.max(MARGIN, left) + 'px';

        this._el.classList.remove('d-none');
        this._visible = true;

        // Load data.
        await this._loadData();

        // Outside-click dismissal (deferred to avoid catching the opening click).
        setTimeout(() => {
            this._outsideClickHandler = (e) => {
                if (!this._el.contains(e.target)) {
                    this.hide();
                }
            };
            document.addEventListener('click', this._outsideClickHandler, true);
        }, 0);
    }

    /**
     * Hide the popout.
     */
    hide() {
        if (this._el) {
            this._el.classList.add('d-none');
        }
        this._visible = false;
        if (this._outsideClickHandler) {
            document.removeEventListener('click', this._outsideClickHandler, true);
            this._outsideClickHandler = null;
        }
    }

    /**
     * Build the popout DOM structure imperatively.
     *
     * @return {HTMLElement} The root popout element.
     */
    _buildDOM() {
        const root = document.createElement('div');
        root.className = 'local-unifiedgrader-clib-popout d-none';

        // Header.
        const header = document.createElement('div');
        header.className = 'clib-header d-flex justify-content-between align-items-center';

        const title = document.createElement('span');
        title.className = 'fw-bold small';
        title.textContent = 'Comment Library';
        getString('clib_title', 'local_unifiedgrader').then((s) => {
            title.textContent = s;
            return s;
        }).catch(() => {});

        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'btn-close btn-close-sm';
        closeBtn.setAttribute('aria-label', 'Close');
        closeBtn.addEventListener('click', () => this.hide());

        header.appendChild(title);
        header.appendChild(closeBtn);
        root.appendChild(header);

        // Tag filter chips.
        this._tagContainer = document.createElement('div');
        this._tagContainer.className = 'clib-tags d-flex flex-wrap gap-1 mt-2 mb-2';
        root.appendChild(this._tagContainer);

        // Comment list (scrollable).
        this._listContainer = document.createElement('div');
        this._listContainer.className = 'clib-list';
        root.appendChild(this._listContainer);

        // Footer: quick-add + manage link.
        const footer = document.createElement('div');
        footer.className = 'clib-footer d-flex gap-2 align-items-center mt-2';

        this._quickInput = document.createElement('input');
        this._quickInput.type = 'text';
        this._quickInput.className = 'form-control form-control-sm flex-grow-1';
        this._quickInput.placeholder = 'Quick add...';
        getString('clib_quick_add', 'local_unifiedgrader').then((s) => {
            this._quickInput.placeholder = s;
            return s;
        }).catch(() => {});

        this._quickInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this._handleQuickAdd();
            }
        });

        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'btn btn-sm btn-primary';
        addBtn.innerHTML = '<i class="fa fa-plus" aria-hidden="true"></i>';
        addBtn.addEventListener('click', () => this._handleQuickAdd());

        const manageLink = document.createElement('button');
        manageLink.type = 'button';
        manageLink.className = 'btn btn-sm btn-link text-nowrap p-0';
        manageLink.textContent = 'Manage';
        getString('clib_manage', 'local_unifiedgrader').then((s) => {
            manageLink.textContent = s;
            return s;
        }).catch(() => {});
        manageLink.addEventListener('click', () => this._openManageModal());

        footer.appendChild(this._quickInput);
        footer.appendChild(addBtn);
        footer.appendChild(manageLink);
        root.appendChild(footer);

        return root;
    }

    /**
     * Load comments and tags via AJAX, falling back to IndexedDB cache when offline.
     */
    async _loadData() {
        try {
            const [commentsResult, tagsResult] = await Promise.all([
                this._ajaxCall('local_unifiedgrader_get_library_comments', {
                    coursecode: this._coursecode,
                    tagid: 0,
                }),
                this._ajaxCall('local_unifiedgrader_get_library_tags', {}),
            ]);
            this._comments = commentsResult;
            this._tags = tagsResult.sort((a, b) => a.name.localeCompare(b.name));
            this._offline = false;
            this._renderTags();
            this._renderComments();
            // Cache for offline fallback.
            OfflineCache.save(0, 0, 'clib_cc_' + this._coursecode, commentsResult);
            OfflineCache.save(0, 0, 'clib_tags', tagsResult);
        } catch (err) {
            // Try offline fallback from cache.
            const cached = await this._loadFromCache();
            if (cached) {
                this._comments = cached.comments;
                this._tags = cached.tags;
                this._offline = true;
                this._renderTags();
                this._renderComments();
            } else {
                window.console.warn('[comment_library_popout] Load failed, no cache available:', err);
            }
        }
    }

    /**
     * Try loading comment library data from IndexedDB cache.
     *
     * Falls back first to the course-specific cache, then to the full library
     * cache (written by the modal) filtered by course code.
     *
     * @return {Promise<{comments: Array, tags: Array}|null>}
     */
    async _loadFromCache() {
        if (!OfflineCache.isAvailable()) {
            return null;
        }
        // Try course-specific cache first.
        let commentsEntry = await OfflineCache.load(0, 0, 'clib_cc_' + this._coursecode);
        if (!commentsEntry) {
            // Fall back to the full library cache (written by the modal).
            const allEntry = await OfflineCache.load(0, 0, 'clib_all');
            if (allEntry) {
                commentsEntry = {
                    data: allEntry.data.filter(c => !this._coursecode || c.coursecode === this._coursecode),
                };
            }
        }
        const tagsEntry = await OfflineCache.load(0, 0, 'clib_tags');
        if (commentsEntry) {
            return {
                comments: commentsEntry.data,
                tags: (tagsEntry?.data || []).sort((a, b) => a.name.localeCompare(b.name)),
            };
        }
        return null;
    }

    /**
     * Make a single AJAX call and return the result.
     *
     * @param {string} methodname Web service function name.
     * @param {object} args Arguments.
     * @return {Promise<*>} The result.
     */
    _ajaxCall(methodname, args) {
        return Ajax.call([{methodname, args, failurealert: false}])[0];
    }

    /**
     * Render tag filter chips.
     */
    _renderTags() {
        this._tagContainer.innerHTML = '';

        // "All" chip.
        const allChip = this._createTagChip('All', 0);
        getString('clib_all', 'local_unifiedgrader').then((s) => {
            allChip.textContent = s;
            return s;
        }).catch(() => {});
        this._tagContainer.appendChild(allChip);

        this._tags.forEach((tag) => {
            this._tagContainer.appendChild(this._createTagChip(tag.name, tag.id));
        });
    }

    /**
     * Create a single tag filter chip element.
     *
     * @param {string} label Chip label text.
     * @param {number} tagid Tag ID (0 = all).
     * @return {HTMLElement} The chip button.
     */
    _createTagChip(label, tagid) {
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'badge rounded-pill '
            + (tagid === this._activeTagId ? 'bg-primary' : 'bg-light text-dark border');
        chip.textContent = label;
        chip.addEventListener('click', () => {
            this._activeTagId = tagid;
            this._renderTags();
            this._renderComments();
        });
        return chip;
    }

    /**
     * Render the filtered comment list.
     */
    _renderComments() {
        this._listContainer.innerHTML = '';

        // Show offline indicator when serving cached data.
        if (this._offline) {
            const offlineBanner = document.createElement('div');
            offlineBanner.className = 'alert alert-warning py-1 px-2 mb-2 small';
            offlineBanner.innerHTML = '<i class="fa fa-exclamation-triangle me-1"></i>'
                + 'Showing cached comments';
            getString('clib_offline_mode', 'local_unifiedgrader').then((s) => {
                offlineBanner.innerHTML = '<i class="fa fa-exclamation-triangle me-1"></i>' + s;
                return s;
            }).catch(() => {});
            this._listContainer.appendChild(offlineBanner);
        }

        let filtered = this._comments;
        if (this._activeTagId !== 0) {
            filtered = this._comments.filter(
                (c) => c.tagids && c.tagids.includes(this._activeTagId),
            );
        }

        if (filtered.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'text-muted small text-center py-3';
            empty.textContent = 'No comments yet';
            getString('clib_no_comments', 'local_unifiedgrader').then((s) => {
                empty.textContent = s;
                return s;
            }).catch(() => {});
            this._listContainer.appendChild(empty);
            return;
        }

        filtered.forEach((comment) => {
            const item = document.createElement('div');
            item.className = 'clib-comment-item';

            const text = document.createElement('div');
            text.className = 'clib-comment-text small';
            text.textContent = comment.content.length > 120
                ? comment.content.substring(0, 120) + '...'
                : comment.content;
            item.appendChild(text);

            // Tag pills.
            if (comment.tagids && comment.tagids.length > 0) {
                const pillRow = document.createElement('div');
                pillRow.className = 'd-flex flex-wrap gap-1 mt-1';
                comment.tagids.forEach((tid) => {
                    const tag = this._tags.find((t) => t.id === tid);
                    if (tag) {
                        const pill = document.createElement('span');
                        pill.className = 'badge';
                        pill.style.fontSize = '0.65rem';
                        pill.textContent = tag.name;
                        _applyColor(pill, _colorFor(tag.name, TAG_COLORS));
                        pillRow.appendChild(pill);
                    }
                });
                item.appendChild(pillRow);
            }

            item.addEventListener('click', () => this._insertComment(comment.content));
            this._listContainer.appendChild(item);
        });
    }

    /**
     * Insert comment text into the last-focused field or copy to clipboard.
     *
     * @param {string} content The comment text.
     */
    async _insertComment(content) {
        const field = this._getLastFocusedField();
        if (field && field.matches('textarea')) {
            // Check for a TinyMCE editor on this textarea.
            const editor = field.id ? getInstanceForElementId(field.id) : null;
            if (editor) {
                // Escape HTML and convert newlines for the rich-text editor.
                const html = content
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/\n/g, '<br>');
                editor.insertContent(html);
                editor.focus();
            } else {
                // Plain textarea — insert at cursor position.
                const start = field.selectionStart || 0;
                const end = field.selectionEnd || 0;
                const before = field.value.substring(0, start);
                const after = field.value.substring(end);
                field.value = before + content + after;
                field.selectionStart = field.selectionEnd = start + content.length;
                field.dispatchEvent(new Event('input', {bubbles: true}));
                field.focus();
            }
        } else {
            // Clipboard fallback.
            try {
                await navigator.clipboard.writeText(content);
                this._showToast(await getString('clib_copied', 'local_unifiedgrader'));
            } catch {
                // Ignore clipboard errors.
            }
        }
        this.hide();
    }

    /**
     * Handle the quick-add input: create a comment for the current course code.
     */
    async _handleQuickAdd() {
        if (this._offline) {
            Notification.alert(getString('error'), getString('error_offline_comments', 'local_unifiedgrader'));
            return;
        }
        const text = this._quickInput.value.trim();
        if (!text) {
            return;
        }
        try {
            await this._ajaxCall('local_unifiedgrader_save_library_comment', {
                coursecode: this._coursecode,
                content: text,
                tagids: [],
                shared: 0,
                commentid: 0,
            });
            this._quickInput.value = '';
            await this._loadData();
        } catch (err) {
            _handleError(err);
        }
    }

    /**
     * Open the full comment library management modal.
     */
    _openManageModal() {
        this.hide();
        CommentLibraryModal.open(this._coursecode, () => {
            // Refresh popout data after modal closes.
            this._loadData();
        });
    }

    /**
     * Show a brief toast notification.
     *
     * @param {string} message Toast message text.
     */
    _showToast(message) {
        const toast = document.createElement('div');
        toast.className = 'local-unifiedgrader-clib-toast';
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2000);
    }
}
