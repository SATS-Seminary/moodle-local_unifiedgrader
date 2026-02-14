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
 * Student navigator component - handles student selection, filtering, and navigation.
 *
 * @module     local_unifiedgrader/components/student_navigator
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent} from 'core/reactive';

export default class extends BaseComponent {

    /**
     * Component creation hook.
     */
    create() {
        this.name = 'student_navigator';
        this.selectors = {
            CURRENT_NAME: '[data-region="current-student-name"]',
            COUNTER: '[data-region="student-counter"]',
            PREV_BTN: '[data-action="prev-student"]',
            NEXT_BTN: '[data-action="next-student"]',
            TOGGLE_FILTERS: '[data-action="toggle-filters"]',
            FILTER_CONTROLS: '[data-region="filter-controls"]',
            SEARCH_INPUT: '[data-action="search-participants"]',
            FILTER_STATUS: '[data-action="filter-status"]',
            SORT_FIELD: '[data-action="sort-field"]',
            PARTICIPANT_LIST: '[data-region="participant-list"]',
        };
        this._searchTimeout = null;
        this._filtersVisible = false;
        this._container = null;
    }

    /**
     * Register state watchers.
     *
     * @return {Array}
     */
    getWatchers() {
        return [
            {watch: 'state.participants:updated', handler: this._renderParticipants},
            {watch: 'currentUser:updated', handler: this._updateCurrentStudent},
        ];
    }

    /**
     * Called when state is first ready.
     *
     * @param {object} state Current state.
     */
    stateReady(state) {
        this._container = this.element.closest('.local-unifiedgrader-container');
        this._setupEventListeners();
        this._renderParticipants({state});
        this._updateCurrentStudent({state});
    }

    /**
     * Set up DOM event listeners.
     */
    _setupEventListeners() {
        // Prev/Next buttons.
        const prevBtn = this.getElement(this.selectors.PREV_BTN);
        if (prevBtn) {
            prevBtn.addEventListener('click', () => this._navigateStudent(-1));
        }
        const nextBtn = this.getElement(this.selectors.NEXT_BTN);
        if (nextBtn) {
            nextBtn.addEventListener('click', () => this._navigateStudent(1));
        }

        // Toggle filters.
        const toggleBtn = this.getElement(this.selectors.TOGGLE_FILTERS);
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                this._filtersVisible = !this._filtersVisible;
                const controls = this.getElement(this.selectors.FILTER_CONTROLS);
                if (controls) {
                    controls.classList.toggle('d-none', !this._filtersVisible);
                }
            });
        }

        // Search with debounce.
        const searchInput = this.getElement(this.selectors.SEARCH_INPUT);
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(this._searchTimeout);
                this._searchTimeout = setTimeout(() => {
                    this._applyFilters({search: searchInput.value});
                }, 300);
            });
        }

        // Status filter.
        const statusSelect = this.getElement(this.selectors.FILTER_STATUS);
        if (statusSelect) {
            statusSelect.addEventListener('change', () => {
                this._applyFilters({status: statusSelect.value});
            });
        }

        // Sort field.
        const sortSelect = this.getElement(this.selectors.SORT_FIELD);
        if (sortSelect) {
            sortSelect.addEventListener('change', () => {
                this._applyFilters({sort: sortSelect.value});
            });
        }

        // Keyboard navigation.
        document.addEventListener('keydown', (e) => {
            // Only handle if no input is focused.
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
                return;
            }
            if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                e.preventDefault();
                this._navigateStudent(-1);
            } else if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                e.preventDefault();
                this._navigateStudent(1);
            }
        });
    }

    /**
     * Render the participant list.
     *
     * @param {object} args Watcher args.
     * @param {object} args.state Current state.
     */
    _renderParticipants({state}) {
        const list = this.getElement(this.selectors.PARTICIPANT_LIST);
        if (!list) {
            return;
        }

        list.innerHTML = '';
        // State lists are StateMaps (extend Map), not arrays. Convert to array for iteration.
        const participants = [...state.participants.values()];
        const currentId = state.currentUser?.id;

        participants.forEach((p) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-1 px-2';
            if (p.id === currentId) {
                item.classList.add('active');
            }
            item.dataset.userid = p.id;

            const nameSpan = document.createElement('span');
            nameSpan.className = 'small';
            nameSpan.textContent = p.fullname;

            const statusBadge = document.createElement('span');
            statusBadge.className = 'badge ' + this._getStatusBadgeClass(p.status);
            statusBadge.textContent = this._getStatusShortLabel(p.status);

            item.appendChild(nameSpan);
            item.appendChild(statusBadge);

            item.addEventListener('click', () => {
                this._selectStudent(p.id);
            });

            list.appendChild(item);
        });

        // Update counter and header (status may have changed after grading).
        this._updateCounter(state);
        const current = participants.find(p => p.id === currentId);
        this._updateHeaderStudentInfo(current);
    }

    /**
     * Update the current student display.
     *
     * @param {object} args Watcher args.
     * @param {object} args.state Current state.
     */
    _updateCurrentStudent({state}) {
        const nameEl = this.getElement(this.selectors.CURRENT_NAME);
        const participants = [...state.participants.values()];
        const current = participants.find(p => p.id === state.currentUser?.id);

        if (nameEl) {
            nameEl.textContent = current ? current.fullname : '--';
        }

        // Update active state in participant list.
        const list = this.getElement(this.selectors.PARTICIPANT_LIST);
        if (list) {
            list.querySelectorAll('.list-group-item').forEach((item) => {
                item.classList.toggle('active', parseInt(item.dataset.userid, 10) === state.currentUser?.id);
            });
        }

        this._updateCounter(state);
        this._updateHeaderStudentInfo(current);
    }

    /**
     * Update the student info in the main header bar.
     *
     * @param {object|undefined} student Current participant data.
     */
    _updateHeaderStudentInfo(student) {
        if (!this._container) {
            return;
        }

        const avatar = this._container.querySelector('[data-region="student-avatar"]');
        const nameEl = this._container.querySelector('[data-region="student-name-header"]');
        const dateEl = this._container.querySelector('[data-region="student-submitted-date"]');
        const statusBadge = this._container.querySelector('[data-region="student-status-badge"]');

        if (nameEl) {
            nameEl.textContent = student ? student.fullname : '--';
        }

        if (avatar) {
            if (student && student.profileimageurl) {
                avatar.src = student.profileimageurl;
                avatar.alt = student.fullname;
                avatar.classList.remove('d-none');
            } else {
                avatar.classList.add('d-none');
            }
        }

        if (dateEl) {
            if (student && student.submittedat > 0) {
                const date = new Date(student.submittedat * 1000);
                dateEl.textContent = 'Submitted: ' + date.toLocaleString();
            } else {
                dateEl.textContent = '';
            }
        }

        if (statusBadge && student) {
            const statusMap = {
                submitted: {label: 'Submitted', cls: 'bg-success'},
                graded: {label: 'Graded', cls: 'bg-info'},
                draft: {label: 'Draft', cls: 'bg-warning'},
                nosubmission: {label: 'No submission', cls: 'bg-secondary'},
                new: {label: 'Not submitted', cls: 'bg-secondary'},
            };
            const info = statusMap[student.status] || {label: student.status, cls: 'bg-secondary'};
            statusBadge.textContent = info.label;
            statusBadge.className = 'badge ' + info.cls;
        } else if (statusBadge) {
            statusBadge.textContent = '';
            statusBadge.className = 'badge bg-secondary';
        }
    }

    /**
     * Update the student counter display.
     *
     * @param {object} state Current state.
     */
    _updateCounter(state) {
        const counterEl = this.getElement(this.selectors.COUNTER);
        if (!counterEl) {
            return;
        }

        const participants = [...state.participants.values()];
        const currentIndex = participants.findIndex(p => p.id === state.currentUser?.id);
        counterEl.textContent = (currentIndex >= 0 ? currentIndex + 1 : 0) + ' / ' + participants.length;
    }

    /**
     * Navigate to previous or next student.
     *
     * @param {number} direction -1 for previous, 1 for next.
     */
    _navigateStudent(direction) {
        const state = this.reactive.state;
        const participants = [...state.participants.values()];
        if (participants.length === 0) {
            return;
        }

        const currentIndex = participants.findIndex(p => p.id === state.currentUser?.id);
        let newIndex = currentIndex + direction;

        // Wrap around.
        if (newIndex < 0) {
            newIndex = participants.length - 1;
        } else if (newIndex >= participants.length) {
            newIndex = 0;
        }

        this._selectStudent(participants[newIndex].id);
    }

    /**
     * Select a student and load their data.
     *
     * @param {number} userid User ID to select.
     */
    _selectStudent(userid) {
        const state = this.reactive.state;
        if (userid === state.currentUser?.id) {
            return;
        }
        this.reactive.dispatch('loadStudent', state.activity.cmid, userid);

        // Collapse the filter/list panel after selection.
        this._collapseFilters();
    }

    /**
     * Collapse the filter controls panel.
     */
    _collapseFilters() {
        if (this._filtersVisible) {
            this._filtersVisible = false;
            const controls = this.getElement(this.selectors.FILTER_CONTROLS);
            if (controls) {
                controls.classList.add('d-none');
            }
        }
    }

    /**
     * Apply filter changes.
     *
     * @param {object} filterUpdates Partial filter object.
     */
    _applyFilters(filterUpdates) {
        const state = this.reactive.state;
        this.reactive.dispatch('updateFilters', state.activity.cmid, filterUpdates);
    }

    /**
     * Get CSS class for status badge.
     *
     * @param {string} status Submission status.
     * @return {string} CSS class.
     */
    _getStatusBadgeClass(status) {
        const map = {
            submitted: 'bg-success',
            graded: 'bg-info',
            draft: 'bg-warning',
            nosubmission: 'bg-secondary',
            new: 'bg-secondary',
        };
        return map[status] || 'bg-secondary';
    }

    /**
     * Get short label for status.
     *
     * @param {string} status Submission status.
     * @return {string} Short label.
     */
    _getStatusShortLabel(status) {
        const map = {
            submitted: 'Sub',
            graded: 'Grd',
            draft: 'Dft',
            nosubmission: '--',
            new: '--',
        };
        return map[status] || status;
    }
}
