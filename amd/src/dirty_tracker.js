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
 * Centralized dirty-state tracker for the grading interface.
 *
 * Tracks whether each data type (grade, feedback, annotations) has unsaved
 * changes. Provides a global beforeunload handler and subscriber mechanism
 * so other modules can react to dirty-state transitions.
 *
 * @module     local_unifiedgrader/dirty_tracker
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @type {Object<string, boolean>} Dirty flags per data type. */
const _dirtyFlags = {
    grade: false,
    feedback: false,
    annotations: false,
};

/** @type {Object<string, *>} Last-saved value snapshots for comparison. */
const _snapshots = {
    grade: null,
    feedback: '',
};

/** @type {Set<Function>} Listeners notified on dirty-state changes. */
const _listeners = new Set();

/** @type {Function|null} Bound beforeunload handler reference. */
let _beforeUnloadHandler = null;

/**
 * Notify all registered listeners of a dirty-state change.
 */
const _notifyListeners = () => {
    const anyDirty = isDirty();
    _listeners.forEach(fn => {
        try {
            fn(anyDirty);
        } catch (e) {
            window.console.warn('[dirty_tracker] Listener error:', e);
        }
    });
};

/**
 * Mark a data type as dirty (has unsaved changes).
 *
 * @param {string} type One of 'grade', 'feedback', 'annotations'.
 */
export const markDirty = (type) => {
    if (!(type in _dirtyFlags)) {
        return;
    }
    if (!_dirtyFlags[type]) {
        _dirtyFlags[type] = true;
        _notifyListeners();
    }
};

/**
 * Mark a data type as clean (all changes saved).
 *
 * @param {string} type One of 'grade', 'feedback', 'annotations'.
 */
export const markClean = (type) => {
    if (!(type in _dirtyFlags)) {
        return;
    }
    if (_dirtyFlags[type]) {
        _dirtyFlags[type] = false;
        _notifyListeners();
    }
};

/**
 * Check whether one or all data types have unsaved changes.
 *
 * @param {string} [type] Specific type to check, or omit for any.
 * @return {boolean} True if there are unsaved changes.
 */
export const isDirty = (type) => {
    if (type) {
        return !!_dirtyFlags[type];
    }
    return Object.values(_dirtyFlags).some(Boolean);
};

/**
 * Store a snapshot of the last-saved value for a data type.
 *
 * @param {string} type Data type key.
 * @param {*} value The value to snapshot.
 */
export const setSnapshot = (type, value) => {
    _snapshots[type] = value;
};

/**
 * Compare a current value against the stored snapshot.
 *
 * @param {string} type Data type key.
 * @param {*} currentValue The current value to compare.
 * @return {boolean} True if the value has changed from the snapshot.
 */
export const hasChanged = (type, currentValue) => {
    const snap = _snapshots[type];
    // Normalise: treat null/undefined/empty-string as equivalent.
    const a = (snap === null || snap === undefined) ? '' : String(snap);
    const b = (currentValue === null || currentValue === undefined) ? '' : String(currentValue);
    return a !== b;
};

/**
 * Register a listener that fires whenever the aggregate dirty state changes.
 *
 * @param {Function} callback Called with (anyDirty: boolean).
 * @return {Function} Unsubscribe function.
 */
export const onDirtyChange = (callback) => {
    _listeners.add(callback);
    return () => _listeners.delete(callback);
};

/**
 * Install the global beforeunload handler.
 * Shows the browser's native "Leave site?" dialog when any data type is dirty.
 */
export const install = () => {
    if (_beforeUnloadHandler) {
        return;
    }
    _beforeUnloadHandler = (e) => {
        if (isDirty()) {
            e.preventDefault();
            e.returnValue = '';
        }
    };
    window.addEventListener('beforeunload', _beforeUnloadHandler);
};
