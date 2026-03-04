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
 * Comment library management modal.
 *
 * Provides a full CRUD interface for the teacher's comment library,
 * organised by course code and tags, with a shared library tab for
 * importing comments from other teachers.
 *
 * @module     local_unifiedgrader/comment_library_modal
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';
import Templates from 'core/templates';
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';
import * as OfflineCache from 'local_unifiedgrader/offline_cache';

/**
 * Handle AJAX or other errors gracefully.
 * Network errors (server unreachable) produce blank Notification.exception modals
 * because they lack errorcode/message. This wrapper shows a friendly alert instead.
 *
 * @param {*} err Error object.
 */
const _handleError = (err) => {
    if (err && (err.errorcode || err.debuginfo)) {
        Notification.exception(err);
    } else {
        Notification.alert(getString('error'), getString('error_network', 'local_unifiedgrader'));
        window.console.warn('[comment_library_modal] Network error:', err);
    }
};

/** @type {string} The current course code for new comments. */
let _coursecode = '';

/** @type {Function|null} Callback to invoke when modal closes. */
let _onClose = null;

/** @type {object|null} The Moodle Modal instance. */
let _modal = null;

/** @type {HTMLElement|null} The modal root element (for querySelector). */
let _root = null;

/** @type {Array} Loaded comments (my library). */
let _comments = [];

/** @type {Array} Loaded tags. */
let _tags = [];

/** @type {Array} Loaded shared comments. */
let _sharedComments = [];

/** @type {string} Active course code filter ('' = all). */
let _activeCourse = '';

/** @type {number} Active tag filter for my library (0 = all). */
let _activeTag = 0;

/** @type {number} Active tag filter for shared library (0 = all). */
let _activeSharedTag = 0;

/** @type {number|null} Comment ID being edited (null = not editing). */
let _editingId = null;

/** @type {boolean} Whether the modal is showing cached (offline) data. */
let _offline = false;

/** Color palette for tag badges. */
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

/** Color palette for course code badges. */
const COURSE_COLORS = [
    {bg: '#2d3436', text: '#fff'},
    {bg: '#0984e3', text: '#fff'},
    {bg: '#00b894', text: '#fff'},
    {bg: '#6c5ce7', text: '#fff'},
    {bg: '#d63031', text: '#fff'},
    {bg: '#e17055', text: '#fff'},
    {bg: '#636e72', text: '#fff'},
    {bg: '#00cec9', text: '#fff'},
];

/**
 * Simple string hash for consistent color assignment.
 *
 * @param {string} str Input string.
 * @return {number} Non-negative integer hash.
 */
const _hashString = (str) => {
    let hash = 0;
    for (let i = 0; i < str.length; i++) {
        hash = ((hash << 5) - hash) + str.charCodeAt(i); // eslint-disable-line no-bitwise
        hash |= 0; // eslint-disable-line no-bitwise
    }
    return Math.abs(hash);
};

/**
 * Get a color from a palette based on a string key.
 *
 * @param {string} key The string to hash.
 * @param {Array} palette The color palette.
 * @return {{bg: string, text: string}} The color pair.
 */
const _colorFor = (key, palette) => palette[_hashString(key) % palette.length];

/**
 * Apply color styling to a badge element.
 *
 * @param {HTMLElement} el The badge element.
 * @param {{bg: string, text: string}} color The color pair.
 */
const _applyColor = (el, color) => {
    el.style.backgroundColor = color.bg;
    el.style.color = color.text;
};

/**
 * Helper: call a web service.
 *
 * @param {string} methodname Function name.
 * @param {object} args Arguments.
 * @return {Promise<*>} Result.
 */
const ajax = (methodname, args) => Ajax.call([{methodname, args, failurealert: false}])[0];

/**
 * Open the comment library modal.
 *
 * @param {string} coursecode Current course code.
 * @param {Function} [onClose] Called when the modal is closed.
 */
const open = async(coursecode, onClose) => {
    _coursecode = coursecode;
    _onClose = onClose || null;
    _activeCourse = '';
    _activeTag = 0;
    _activeSharedTag = 0;
    _editingId = null;
    _offline = false;

    const title = await getString('clib_title', 'local_unifiedgrader');
    const {html} = await Templates.renderForPromise('local_unifiedgrader/comment_library_modal', {});

    _modal = await Modal.create({
        title,
        body: html,
        large: true,
        removeOnClose: true,
    });

    _modal.getRoot().on('modal:hidden', () => {
        if (_onClose) {
            _onClose();
        }
        _modal = null;
        _root = null;
    });

    _modal.show();

    // Apply custom wider class to the modal dialog.
    const dialog = _modal.getRoot()[0].querySelector('.modal-dialog');
    if (dialog) {
        dialog.classList.add('local-unifiedgrader-clib-modal');
    }

    // Wait for DOM to settle, then grab root and wire events.
    setTimeout(() => {
        _root = _modal.getRoot()[0].querySelector('[data-region="clib-modal-root"]');
        if (_root) {
            _wireEvents();
            _loadAll();
        }
    }, 100);
};

/**
 * Wire up event listeners inside the modal body.
 */
const _wireEvents = () => {
    // New comment button.
    _root.querySelector('[data-action="clib-new-comment"]')
        ?.addEventListener('click', _showNewForm);

    // Save new / cancel new.
    _root.querySelector('[data-action="clib-save-new"]')
        ?.addEventListener('click', _handleSaveNew);
    _root.querySelector('[data-action="clib-cancel-new"]')
        ?.addEventListener('click', _hideNewForm);

    // Tag filter (my library).
    _root.querySelector('[data-action="clib-tag-filter"]')
        ?.addEventListener('change', (e) => {
            _activeTag = parseInt(e.target.value, 10);
            _renderComments();
        });

    // Shared tag filter.
    _root.querySelector('[data-action="clib-shared-tag-filter"]')
        ?.addEventListener('change', (e) => {
            _activeSharedTag = parseInt(e.target.value, 10);
            _renderShared();
        });

    // Manage tags.
    _root.querySelector('[data-action="clib-manage-tags"]')
        ?.addEventListener('click', _showTagManager);
};

/**
 * Load all data (comments, tags, shared) in parallel.
 * Caches results to IndexedDB for offline fallback.
 */
const _loadAll = async() => {
    try {
        const [comments, tags, shared] = await Promise.all([
            ajax('local_unifiedgrader_get_library_comments', {coursecode: '', tagid: 0}),
            ajax('local_unifiedgrader_get_library_tags', {}),
            ajax('local_unifiedgrader_get_shared_library', {tagid: 0}),
        ]);
        _comments = comments;
        _tags = tags.sort((a, b) => a.name.localeCompare(b.name));
        _sharedComments = shared;
        _offline = false;

        // Cache for offline fallback.
        OfflineCache.save(0, 0, 'clib_all', comments);
        OfflineCache.save(0, 0, 'clib_tags', tags);
        OfflineCache.save(0, 0, 'clib_shared', shared);

        _removeOfflineBanner();
        _renderCourseList();
        _populateTagFilters();
        _renderComments();
        _renderShared();
    } catch (err) {
        // Try offline fallback from cache.
        const cached = await _loadFromCache();
        if (cached) {
            _comments = cached.comments;
            _tags = cached.tags;
            _sharedComments = cached.shared;
            _offline = true;

            _renderCourseList();
            _populateTagFilters();
            _renderComments();
            _renderShared();
            _showOfflineBanner();
        } else {
            window.console.warn('[comment_library_modal] Load failed, no cache available:', err);
        }
    }
};

/**
 * Try loading comment library data from IndexedDB cache.
 *
 * @return {Promise<{comments: Array, tags: Array, shared: Array}|null>}
 */
const _loadFromCache = async() => {
    if (!OfflineCache.isAvailable()) {
        return null;
    }
    const [commentsEntry, tagsEntry, sharedEntry] = await Promise.all([
        OfflineCache.load(0, 0, 'clib_all'),
        OfflineCache.load(0, 0, 'clib_tags'),
        OfflineCache.load(0, 0, 'clib_shared'),
    ]);
    if (commentsEntry) {
        return {
            comments: commentsEntry.data,
            tags: (tagsEntry?.data || []).sort((a, b) => a.name.localeCompare(b.name)),
            shared: sharedEntry?.data || [],
        };
    }
    return null;
};

/**
 * Show a banner indicating the modal is displaying cached offline data.
 */
const _showOfflineBanner = () => {
    if (!_root || !_offline) {
        return;
    }
    if (_root.querySelector('[data-region="clib-offline-banner"]')) {
        return;
    }
    const banner = document.createElement('div');
    banner.dataset.region = 'clib-offline-banner';
    banner.className = 'alert alert-warning py-1 px-2 mb-2 small';
    banner.innerHTML = '<i class="fa fa-exclamation-triangle me-1"></i>'
        + 'Showing cached comments — editing is unavailable offline.';
    getString('clib_offline_mode', 'local_unifiedgrader').then((s) => {
        banner.innerHTML = '<i class="fa fa-exclamation-triangle me-1"></i>' + _escapeHtml(s);
        return s;
    }).catch(() => {});
    _root.insertBefore(banner, _root.firstChild);
};

/**
 * Remove the offline banner if present.
 */
const _removeOfflineBanner = () => {
    if (!_root) {
        return;
    }
    const banner = _root.querySelector('[data-region="clib-offline-banner"]');
    if (banner) {
        banner.remove();
    }
};

// ───────────────────────── Course sidebar ─────────────────────────

/**
 * Render the course code sidebar.
 */
const _renderCourseList = () => {
    const container = _root.querySelector('[data-region="clib-course-list"]');
    if (!container) {
        return;
    }
    container.innerHTML = '';

    // Build course → count map.
    const counts = {};
    _comments.forEach((c) => {
        const code = c.coursecode || '—';
        counts[code] = (counts[code] || 0) + 1;
    });

    // "All" entry.
    const allItem = _courseItem('All', '', _comments.length);
    getString('clib_all', 'local_unifiedgrader').then((s) => {
        allItem.querySelector('.clib-course-label').textContent = s;
        return s;
    }).catch(() => {});
    container.appendChild(allItem);

    // Individual course codes.
    const codes = Object.keys(counts).sort();
    codes.forEach((code) => {
        container.appendChild(_courseItem(code, code, counts[code]));
    });
};

/**
 * Create a course list item element.
 *
 * @param {string} label Display label.
 * @param {string} code Course code value ('' = all).
 * @param {number} count Number of comments.
 * @return {HTMLElement} The list item.
 */
const _courseItem = (label, code, count) => {
    const item = document.createElement('a');
    item.href = '#';
    item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-1 px-2'
        + (code === _activeCourse ? ' active' : '');
    item.addEventListener('click', (e) => {
        e.preventDefault();
        _activeCourse = code;
        _renderCourseList();
        _renderComments();
    });

    const labelSpan = document.createElement('span');
    labelSpan.className = 'clib-course-label text-truncate';
    labelSpan.textContent = label;

    const badge = document.createElement('span');
    badge.className = 'badge bg-secondary rounded-pill';
    badge.textContent = count;

    item.appendChild(labelSpan);
    item.appendChild(badge);
    return item;
};

// ───────────────────────── Tag filters ─────────────────────────

/**
 * Populate tag filter dropdowns.
 */
const _populateTagFilters = () => {
    [
        _root.querySelector('[data-action="clib-tag-filter"]'),
        _root.querySelector('[data-action="clib-shared-tag-filter"]'),
    ].forEach((select) => {
        if (!select) {
            return;
        }
        // Keep the first "All" option, remove the rest.
        while (select.options.length > 1) {
            select.remove(1);
        }
        _tags.forEach((tag) => {
            const opt = document.createElement('option');
            opt.value = tag.id;
            opt.textContent = tag.name;
            select.appendChild(opt);
        });
    });

    // Render tag chips inside the new-comment form.
    _renderNewFormTagChips();
};

// ───────────────────────── My Library comments ─────────────────────────

/**
 * Render the filtered comment list.
 */
const _renderComments = () => {
    const container = _root.querySelector('[data-region="clib-comment-list"]');
    if (!container) {
        return;
    }
    container.innerHTML = '';

    let filtered = _comments;
    if (_activeCourse) {
        filtered = filtered.filter((c) => (c.coursecode || '—') === _activeCourse);
    }
    if (_activeTag) {
        filtered = filtered.filter((c) => c.tagids && c.tagids.includes(_activeTag));
    }

    if (filtered.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'text-muted text-center py-4 small';
        empty.textContent = 'No comments';
        getString('clib_no_comments', 'local_unifiedgrader').then((s) => {
            empty.textContent = s;
            return s;
        }).catch(() => {});
        container.appendChild(empty);
        return;
    }

    filtered.forEach((comment) => {
        container.appendChild(_commentCard(comment));
    });
};

/**
 * Build a comment card element with edit/delete controls.
 *
 * @param {object} comment Comment data.
 * @return {HTMLElement} The card element.
 */
const _commentCard = (comment) => {
    const card = document.createElement('div');
    card.className = 'border rounded p-2 mb-2';

    // If editing this comment, show the edit form.
    if (_editingId === comment.id) {
        return _editForm(comment);
    }

    // Content.
    const content = document.createElement('div');
    content.className = 'small mb-1';
    content.style.whiteSpace = 'pre-wrap';
    content.textContent = comment.content;
    card.appendChild(content);

    // Tag pills + course code badge.
    const metaRow = document.createElement('div');
    metaRow.className = 'd-flex flex-wrap gap-1 align-items-center mb-1';

    if (comment.coursecode) {
        const codeBadge = document.createElement('span');
        codeBadge.className = 'badge';
        codeBadge.style.fontSize = '0.65rem';
        codeBadge.textContent = comment.coursecode;
        _applyColor(codeBadge, _colorFor(comment.coursecode, COURSE_COLORS));
        metaRow.appendChild(codeBadge);
    }

    if (comment.tagids) {
        comment.tagids.forEach((tid) => {
            const tag = _tags.find((t) => t.id === tid);
            if (tag) {
                const pill = document.createElement('span');
                pill.className = 'badge';
                pill.style.fontSize = '0.65rem';
                pill.textContent = tag.name;
                _applyColor(pill, _colorFor(tag.name, TAG_COLORS));
                metaRow.appendChild(pill);
            }
        });
    }

    if (comment.shared) {
        const sharedBadge = document.createElement('span');
        sharedBadge.className = 'badge bg-success';
        sharedBadge.style.fontSize = '0.65rem';
        sharedBadge.textContent = 'Shared';
        getString('clib_share', 'local_unifiedgrader').then((s) => {
            sharedBadge.textContent = s;
            return s;
        }).catch(() => {});
        metaRow.appendChild(sharedBadge);
    }

    card.appendChild(metaRow);

    // Action buttons.
    const actions = document.createElement('div');
    actions.className = 'd-flex gap-1';

    const editBtn = document.createElement('button');
    editBtn.type = 'button';
    editBtn.className = 'btn btn-sm btn-outline-secondary py-0 px-1';
    editBtn.innerHTML = '<i class="fa fa-pencil"></i>';
    editBtn.title = 'Edit';
    editBtn.addEventListener('click', () => {
        _editingId = comment.id;
        _renderComments();
    });

    const shareBtn = document.createElement('button');
    shareBtn.type = 'button';
    shareBtn.className = 'btn btn-sm py-0 px-1 '
        + (comment.shared ? 'btn-outline-success' : 'btn-outline-secondary');
    shareBtn.innerHTML = '<i class="fa fa-share-alt"></i>';
    shareBtn.title = comment.shared ? 'Unshare' : 'Share';
    shareBtn.addEventListener('click', () => _toggleShare(comment));

    const deleteBtn = document.createElement('button');
    deleteBtn.type = 'button';
    deleteBtn.className = 'btn btn-sm btn-outline-danger py-0 px-1';
    deleteBtn.innerHTML = '<i class="fa fa-trash"></i>';
    deleteBtn.title = 'Delete';
    deleteBtn.addEventListener('click', () => _handleDelete(comment.id));

    actions.appendChild(editBtn);
    actions.appendChild(shareBtn);
    actions.appendChild(deleteBtn);
    card.appendChild(actions);

    return card;
};

/**
 * Build an inline edit form for a comment.
 *
 * @param {object} comment Comment data.
 * @return {HTMLElement} The edit form element.
 */
const _editForm = (comment) => {
    const form = document.createElement('div');
    form.className = 'border rounded p-2 mb-2 bg-light';

    const textarea = document.createElement('textarea');
    textarea.className = 'form-control form-control-sm mb-2';
    textarea.rows = 3;
    textarea.value = comment.content;
    form.appendChild(textarea);

    // Tag chips (toggleable).
    const tagRow = document.createElement('div');
    tagRow.className = 'd-flex flex-wrap gap-1 mb-2';
    _tags.forEach((tag) => {
        const isActive = comment.tagids && comment.tagids.includes(tag.id);
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'badge rounded-pill '
            + (isActive ? 'bg-primary' : 'bg-light text-dark border');
        chip.textContent = tag.name;
        chip.dataset.tagid = tag.id;
        chip.addEventListener('click', () => {
            chip.classList.toggle('bg-primary');
            chip.classList.toggle('bg-light');
            chip.classList.toggle('text-dark');
            chip.classList.toggle('border');
        });
        tagRow.appendChild(chip);
    });
    form.appendChild(tagRow);

    // Course code selector + shared checkbox + buttons.
    const controls = document.createElement('div');
    controls.className = 'd-flex justify-content-between align-items-center gap-2';

    const courseInput = document.createElement('input');
    courseInput.type = 'text';
    courseInput.className = 'form-control form-control-sm';
    courseInput.style.width = '100px';
    courseInput.placeholder = 'Course';
    courseInput.value = comment.coursecode || '';

    const sharedCheck = document.createElement('label');
    sharedCheck.className = 'form-check form-check-inline small mb-0';
    const sharedInput = document.createElement('input');
    sharedInput.type = 'checkbox';
    sharedInput.className = 'form-check-input';
    sharedInput.checked = !!comment.shared;
    sharedCheck.appendChild(sharedInput);
    sharedCheck.appendChild(document.createTextNode(' Share'));

    const btnGroup = document.createElement('div');
    btnGroup.className = 'd-flex gap-1';

    const saveBtn = document.createElement('button');
    saveBtn.type = 'button';
    saveBtn.className = 'btn btn-sm btn-primary';
    saveBtn.textContent = 'Save';
    saveBtn.addEventListener('click', async() => {
        const selectedTags = [...tagRow.querySelectorAll('.bg-primary')]
            .map((el) => parseInt(el.dataset.tagid, 10));
        try {
            await ajax('local_unifiedgrader_save_library_comment', {
                coursecode: courseInput.value.trim(),
                content: textarea.value.trim(),
                tagids: selectedTags,
                shared: sharedInput.checked ? 1 : 0,
                commentid: comment.id,
            });
            _editingId = null;
            await _loadAll();
        } catch (err) {
            _handleError(err);
        }
    });

    const cancelBtn = document.createElement('button');
    cancelBtn.type = 'button';
    cancelBtn.className = 'btn btn-sm btn-outline-secondary';
    cancelBtn.textContent = 'Cancel';
    cancelBtn.addEventListener('click', () => {
        _editingId = null;
        _renderComments();
    });

    btnGroup.appendChild(saveBtn);
    btnGroup.appendChild(cancelBtn);

    controls.appendChild(courseInput);
    controls.appendChild(sharedCheck);
    controls.appendChild(btnGroup);
    form.appendChild(controls);

    return form;
};

// ───────────────────────── New comment form ─────────────────────────

/** Selected tag IDs for the new comment form. */
let _newFormSelectedTags = [];

/**
 * Show the inline new comment form.
 */
const _showNewForm = () => {
    const form = _root.querySelector('[data-region="clib-new-form"]');
    if (form) {
        form.classList.remove('d-none');
        const ta = form.querySelector('[data-input="clib-new-content"]');
        if (ta) {
            ta.value = '';
            ta.focus();
        }
        const shared = form.querySelector('[data-input="clib-new-shared"]');
        if (shared) {
            shared.checked = false;
        }
        _newFormSelectedTags = [];
        _renderNewFormTagChips();
    }
};

/**
 * Hide the inline new comment form.
 */
const _hideNewForm = () => {
    const form = _root.querySelector('[data-region="clib-new-form"]');
    if (form) {
        form.classList.add('d-none');
    }
};

/**
 * Render toggleable tag chips inside the new comment form.
 */
const _renderNewFormTagChips = () => {
    const container = _root?.querySelector('[data-region="clib-new-tag-chips"]');
    if (!container) {
        return;
    }
    container.innerHTML = '';
    _tags.forEach((tag) => {
        const isActive = _newFormSelectedTags.includes(tag.id);
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'badge rounded-pill '
            + (isActive ? 'bg-primary' : 'bg-light text-dark border');
        chip.style.fontSize = '0.7rem';
        chip.style.cursor = 'pointer';
        chip.textContent = tag.name;
        chip.addEventListener('click', () => {
            const idx = _newFormSelectedTags.indexOf(tag.id);
            if (idx >= 0) {
                _newFormSelectedTags.splice(idx, 1);
            } else {
                _newFormSelectedTags.push(tag.id);
            }
            _renderNewFormTagChips();
        });
        container.appendChild(chip);
    });
};

/**
 * Handle saving a new comment.
 */
const _handleSaveNew = async() => {
    const form = _root.querySelector('[data-region="clib-new-form"]');
    if (!form) {
        return;
    }
    const content = form.querySelector('[data-input="clib-new-content"]')?.value?.trim();
    if (!content) {
        return;
    }
    const shared = form.querySelector('[data-input="clib-new-shared"]')?.checked ? 1 : 0;
    try {
        await ajax('local_unifiedgrader_save_library_comment', {
            coursecode: _activeCourse || _coursecode,
            content,
            tagids: _newFormSelectedTags,
            shared,
            commentid: 0,
        });
        _hideNewForm();
        await _loadAll();
    } catch (err) {
        _handleError(err);
    }
};

// ───────────────────────── Delete / Share ─────────────────────────

/**
 * Delete a comment after confirmation.
 *
 * @param {number} commentid Comment ID.
 */
const _handleDelete = async(commentid) => {
    const msg = await getString('clib_confirm_delete', 'local_unifiedgrader');
    if (!window.confirm(msg)) {
        return;
    }
    try {
        await ajax('local_unifiedgrader_delete_library_comment', {commentid});
        await _loadAll();
    } catch (err) {
        _handleError(err);
    }
};

/**
 * Toggle the shared status of a comment.
 *
 * @param {object} comment Comment data.
 */
const _toggleShare = async(comment) => {
    try {
        await ajax('local_unifiedgrader_save_library_comment', {
            coursecode: comment.coursecode || '',
            content: comment.content,
            tagids: comment.tagids || [],
            shared: comment.shared ? 0 : 1,
            commentid: comment.id,
        });
        await _loadAll();
    } catch (err) {
        _handleError(err);
    }
};

// ───────────────────────── Shared Library tab ─────────────────────────

/**
 * Render the shared library list.
 */
const _renderShared = () => {
    const container = _root.querySelector('[data-region="clib-shared-list"]');
    if (!container) {
        return;
    }
    container.innerHTML = '';

    let filtered = _sharedComments;
    if (_activeSharedTag) {
        filtered = filtered.filter((c) => c.tagids && c.tagids.includes(_activeSharedTag));
    }

    if (filtered.length === 0) {
        const empty = document.createElement('div');
        empty.className = 'text-muted text-center py-4 small';
        empty.textContent = 'No shared comments';
        getString('clib_no_shared', 'local_unifiedgrader').then((s) => {
            empty.textContent = s;
            return s;
        }).catch(() => {});
        container.appendChild(empty);
        return;
    }

    filtered.forEach((comment) => {
        const card = document.createElement('div');
        card.className = 'border rounded p-2 mb-2';

        // Content.
        const content = document.createElement('div');
        content.className = 'small mb-1';
        content.style.whiteSpace = 'pre-wrap';
        content.textContent = comment.content;
        card.appendChild(content);

        // Meta: author + tags.
        const meta = document.createElement('div');
        meta.className = 'd-flex flex-wrap gap-1 align-items-center mb-1';

        if (comment.ownername) {
            const authorBadge = document.createElement('span');
            authorBadge.className = 'badge bg-secondary';
            authorBadge.style.fontSize = '0.65rem';
            authorBadge.textContent = comment.ownername;
            meta.appendChild(authorBadge);
        }
        if (comment.coursecode) {
            const codeBadge = document.createElement('span');
            codeBadge.className = 'badge';
            codeBadge.style.fontSize = '0.65rem';
            codeBadge.textContent = comment.coursecode;
            _applyColor(codeBadge, _colorFor(comment.coursecode, COURSE_COLORS));
            meta.appendChild(codeBadge);
        }
        if (comment.tagids) {
            comment.tagids.forEach((tid) => {
                const tag = _tags.find((t) => t.id === tid);
                if (tag) {
                    const pill = document.createElement('span');
                    pill.className = 'badge';
                    pill.style.fontSize = '0.65rem';
                    pill.textContent = tag.name;
                    _applyColor(pill, _colorFor(tag.name, TAG_COLORS));
                    meta.appendChild(pill);
                }
            });
        }
        card.appendChild(meta);

        // Import button.
        const importBtn = document.createElement('button');
        importBtn.type = 'button';
        importBtn.className = 'btn btn-sm btn-outline-primary py-0 px-2';
        importBtn.innerHTML = '<i class="fa fa-download me-1"></i>';
        importBtn.title = 'Import';
        getString('clib_import', 'local_unifiedgrader').then((s) => {
            importBtn.title = s;
            importBtn.innerHTML = '<i class="fa fa-download me-1"></i>' + _escapeHtml(s);
            return s;
        }).catch(() => {});
        importBtn.addEventListener('click', () => _handleImport(comment.id));
        card.appendChild(importBtn);

        container.appendChild(card);
    });
};

/**
 * Import a shared comment into the teacher's library.
 *
 * @param {number} commentid Shared comment ID.
 */
const _handleImport = async(commentid) => {
    try {
        await ajax('local_unifiedgrader_import_shared_comment', {
            commentid,
            coursecode: _coursecode,
        });
        const msg = await getString('clib_imported', 'local_unifiedgrader');
        _showToast(msg);
        await _loadAll();
    } catch (err) {
        _handleError(err);
    }
};

// ───────────────────────── Tag management ─────────────────────────

/**
 * Restore the sidebar to its default course-list view.
 * Recreates the inner structure that the template originally provided,
 * since _showTagManager clears it.
 */
const _restoreSidebar = () => {
    const sidebar = _root.querySelector('[data-region="clib-sidebar"]');
    if (!sidebar) {
        return;
    }
    sidebar.innerHTML = '';

    // Header.
    const header = document.createElement('div');
    header.className = 'fw-bold small mb-2';
    header.textContent = 'All Courses';
    getString('clib_all_courses', 'local_unifiedgrader').then((s) => {
        header.textContent = s;
        return s;
    }).catch(() => {});
    sidebar.appendChild(header);

    // Course list container (needed by _renderCourseList).
    const courseList = document.createElement('div');
    courseList.setAttribute('data-region', 'clib-course-list');
    courseList.className = 'list-group list-group-flush small';
    sidebar.appendChild(courseList);

    const hr = document.createElement('hr');
    hr.className = 'my-2';
    sidebar.appendChild(hr);

    // Manage tags button.
    const mgBtn = document.createElement('button');
    mgBtn.type = 'button';
    mgBtn.className = 'btn btn-sm btn-outline-secondary w-100';
    mgBtn.innerHTML = '<i class="fa fa-tags me-1"></i>';
    getString('clib_manage_tags', 'local_unifiedgrader').then((s) => {
        mgBtn.innerHTML = '<i class="fa fa-tags me-1"></i>' + _escapeHtml(s);
        return s;
    }).catch(() => {});
    mgBtn.addEventListener('click', _showTagManager);
    sidebar.appendChild(mgBtn);

    _renderCourseList();
};

/**
 * Show a tag management sub-view replacing the course sidebar content.
 */
const _showTagManager = () => {
    const sidebar = _root.querySelector('[data-region="clib-sidebar"]');
    if (!sidebar) {
        return;
    }

    // Replace sidebar contents with tag management UI.
    sidebar.innerHTML = '';

    const header = document.createElement('div');
    header.className = 'fw-bold small mb-2 d-flex justify-content-between align-items-center';

    const headerText = document.createElement('span');
    headerText.textContent = 'Tags';
    getString('clib_tags', 'local_unifiedgrader').then((s) => {
        headerText.textContent = s;
        return s;
    }).catch(() => {});

    const backBtn = document.createElement('button');
    backBtn.type = 'button';
    backBtn.className = 'btn btn-sm btn-link p-0';
    backBtn.innerHTML = '<i class="fa fa-arrow-left"></i>';
    backBtn.addEventListener('click', () => _restoreSidebar());

    header.appendChild(backBtn);
    header.appendChild(headerText);
    sidebar.appendChild(header);

    // Tag list.
    const tagList = document.createElement('div');
    tagList.className = 'list-group list-group-flush small';

    _tags.forEach((tag) => {
        const item = document.createElement('div');
        item.className = 'list-group-item d-flex justify-content-between align-items-center py-1 px-2';

        const name = document.createElement('span');
        name.className = 'text-truncate';
        name.textContent = tag.name;
        if (tag.issystem) {
            name.classList.add('text-muted');
        }

        const btns = document.createElement('div');
        btns.className = 'd-flex gap-1 flex-shrink-0';

        if (!tag.issystem) {
            const editTagBtn = document.createElement('button');
            editTagBtn.type = 'button';
            editTagBtn.className = 'btn btn-sm btn-link p-0';
            editTagBtn.innerHTML = '<i class="fa fa-pencil"></i>';
            editTagBtn.addEventListener('click', () => _editTag(tag, name));
            btns.appendChild(editTagBtn);

            const delTagBtn = document.createElement('button');
            delTagBtn.type = 'button';
            delTagBtn.className = 'btn btn-sm btn-link text-danger p-0';
            delTagBtn.innerHTML = '<i class="fa fa-trash"></i>';
            delTagBtn.addEventListener('click', async() => {
                const msg = await getString('clib_confirm_delete_tag', 'local_unifiedgrader');
                if (window.confirm(msg)) {
                    try {
                        await ajax('local_unifiedgrader_delete_library_tag', {tagid: tag.id});
                        await _loadAll();
                        _showTagManager();
                    } catch (err) {
                        _handleError(err);
                    }
                }
            });
            btns.appendChild(delTagBtn);
        } else {
            const sysLabel = document.createElement('span');
            sysLabel.className = 'badge bg-light text-muted border';
            sysLabel.style.fontSize = '0.6rem';
            sysLabel.textContent = 'System';
            getString('clib_system_tag', 'local_unifiedgrader').then((s) => {
                sysLabel.textContent = s;
                return s;
            }).catch(() => {});
            btns.appendChild(sysLabel);
        }

        item.appendChild(name);
        item.appendChild(btns);
        tagList.appendChild(item);
    });

    sidebar.appendChild(tagList);

    // New tag input.
    const newTagRow = document.createElement('div');
    newTagRow.className = 'd-flex gap-1 mt-2';

    const newTagInput = document.createElement('input');
    newTagInput.type = 'text';
    newTagInput.className = 'form-control form-control-sm';
    newTagInput.placeholder = 'New tag';
    getString('clib_new_tag', 'local_unifiedgrader').then((s) => {
        newTagInput.placeholder = s;
        return s;
    }).catch(() => {});

    const newTagBtn = document.createElement('button');
    newTagBtn.type = 'button';
    newTagBtn.className = 'btn btn-sm btn-primary';
    newTagBtn.innerHTML = '<i class="fa fa-plus"></i>';
    newTagBtn.addEventListener('click', async() => {
        const tagName = newTagInput.value.trim();
        if (!tagName) {
            return;
        }
        try {
            await ajax('local_unifiedgrader_save_library_tag', {name: tagName, tagid: 0});
            newTagInput.value = '';
            await _loadAll();
            _showTagManager();
        } catch (err) {
            _handleError(err);
        }
    });

    newTagInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            newTagBtn.click();
        }
    });

    newTagRow.appendChild(newTagInput);
    newTagRow.appendChild(newTagBtn);
    sidebar.appendChild(newTagRow);
};

/**
 * Inline-edit a tag name.
 *
 * @param {object} tag Tag data.
 * @param {HTMLElement} nameEl The name span element to replace.
 */
const _editTag = (tag, nameEl) => {
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control form-control-sm';
    input.value = tag.name;
    input.style.width = '80px';
    nameEl.replaceWith(input);
    input.focus();
    input.select();

    const save = async() => {
        const newName = input.value.trim();
        if (newName && newName !== tag.name) {
            try {
                await ajax('local_unifiedgrader_save_library_tag', {name: newName, tagid: tag.id});
                await _loadAll();
            } catch (err) {
                _handleError(err);
            }
        }
        _showTagManager();
    };

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            save();
        }
        if (e.key === 'Escape') {
            _showTagManager();
        }
    });
    input.addEventListener('blur', save);
};

// ───────────────────────── Utilities ─────────────────────────

/**
 * Escape HTML entities.
 *
 * @param {string} text Raw text.
 * @return {string} Escaped text.
 */
const _escapeHtml = (text) => {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
};

/**
 * Show a brief toast notification.
 *
 * @param {string} message Toast text.
 */
const _showToast = (message) => {
    const toast = document.createElement('div');
    toast.className = 'local-unifiedgrader-clib-toast';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2000);
};

export default {open};
