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
 * Student navigator component - handles student selection, filtering,
 * navigation, and submission status actions dropdown.
 *
 * @module     local_unifiedgrader/components/student_navigator
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent} from 'core/reactive';
import {get_strings as getStrings} from 'core/str';

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
            GROUP_FILTER: '[data-region="group-filter"]',
            GROUP_SELECT: '[data-action="filter-group"]',
            PARTICIPANT_LIST: '[data-region="participant-list"]',
        };
        this._searchTimeout = null;
        this._filtersVisible = false;
        this._container = null;
        /** @type {?object} Prefetched lang strings for status actions. */
        this._strings = null;
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
            {watch: 'submission:updated', handler: this._onSubmissionUpdated},
        ];
    }

    /**
     * Called when state is first ready.
     *
     * @param {object} state Current state.
     */
    async stateReady(state) {
        this._container = this.element.closest('.local-unifiedgrader-container');
        this._setupEventListeners();
        this._initGroupSelector(state);

        // Prefetch action strings for assign and quiz activities.
        if (state.activity.type === 'assign' || state.activity.type === 'quiz') {
            await this._prefetchStrings();
        }

        this._renderParticipants({state});
        this._updateCurrentStudent({state});
    }

    /**
     * Prefetch lang strings for submission status action labels and confirmations.
     */
    async _prefetchStrings() {
        const keys = [
            'action_revert_to_draft',
            'action_remove_submission',
            'action_lock',
            'action_unlock',
            'action_edit_submission',
            'action_grant_extension',
            'action_edit_extension',
            'action_submit_for_grading',
            'confirm_revert_to_draft',
            'confirm_remove_submission',
            'confirm_lock_submission',
            'confirm_unlock_submission',
            'confirm_submit_for_grading',
            'action_add_override',
            'action_edit_override',
            'action_delete_override',
            'confirm_delete_override',
        ];
        try {
            const values = await getStrings(keys.map(key => ({key, component: 'local_unifiedgrader'})));
            this._strings = {};
            keys.forEach((key, i) => {
                this._strings[key] = values[i];
            });
        } catch {
            // Strings not available — dropdown will fall back to plain badge.
        }
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

        // Group filter.
        const groupSelect = this.getElement(this.selectors.GROUP_SELECT);
        if (groupSelect) {
            groupSelect.addEventListener('change', () => {
                this._applyFilters({group: parseInt(groupSelect.value, 10)});
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

        // Close status action dropdown on outside click.
        document.addEventListener('click', (e) => {
            if (!e.target.closest('[data-region="student-status-wrapper"]')) {
                const menu = this._container?.querySelector('.local-unifiedgrader-status-menu.show');
                if (menu) {
                    menu.classList.remove('show');
                }
            }
        });
    }

    /**
     * Populate the group selector dropdown from state data.
     *
     * @param {object} state Current state.
     */
    _initGroupSelector(state) {
        if (!state.filters.hasGroupMode) {
            return;
        }

        const wrapper = this.getElement(this.selectors.GROUP_FILTER);
        const select = this.getElement(this.selectors.GROUP_SELECT);
        if (!wrapper || !select) {
            return;
        }

        // Show the group filter row.
        wrapper.classList.remove('d-none');

        // Populate group options. The "All groups" option is already in the template.
        const groups = [...state.groups.values()];
        groups.forEach((group) => {
            const option = document.createElement('option');
            option.value = group.id;
            option.textContent = group.name;
            select.appendChild(option);
        });

        // Set current selection.
        select.value = state.filters.group;
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

            // Show padlock icon for locked submissions.
            if (p.locked) {
                const lockIcon = document.createElement('i');
                lockIcon.className = 'fa fa-lock ms-1';
                lockIcon.style.fontSize = '0.7em';
                statusBadge.appendChild(lockIcon);
            }

            item.appendChild(nameSpan);

            // Wrap optional late dot + status badge together.
            const statusWrapper = document.createElement('span');
            statusWrapper.className = 'd-flex align-items-center gap-1';

            // Show a red dot for late submissions (before the badge).
            if (p.islate) {
                const lateDot = document.createElement('span');
                lateDot.className = 'rounded-circle bg-danger d-inline-block';
                lateDot.style.cssText = 'width: 6px; height: 6px; flex-shrink: 0;';
                statusWrapper.appendChild(lateDot);
            }

            // Show a clock icon for users with an override.
            if (p.hasoverride) {
                const overrideIcon = document.createElement('i');
                overrideIcon.className = 'fa fa-clock-o text-danger';
                overrideIcon.style.fontSize = '0.7em';
                overrideIcon.title = 'Override active';
                statusWrapper.appendChild(overrideIcon);
            }

            statusWrapper.appendChild(statusBadge);
            item.appendChild(statusWrapper);

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
        const wrapper = this._container.querySelector('[data-region="student-status-wrapper"]');

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

        if (wrapper && student) {
            this._buildStatusDropdown(wrapper, student);
        } else if (wrapper) {
            wrapper.innerHTML = '<span class="badge bg-secondary"></span>';
        }

        // Show/hide override indicator next to the status badge.
        this._updateOverrideIndicator(student);
    }

    /**
     * Show or hide an override indicator badge next to the status area.
     *
     * @param {object|undefined} student Current participant data.
     */
    _updateOverrideIndicator(student) {
        if (!this._container) {
            return;
        }

        const wrapper = this._container.querySelector('[data-region="student-status-wrapper"]');
        if (!wrapper) {
            return;
        }

        // Remove any existing override indicator.
        const existing = wrapper.querySelector('.local-unifiedgrader-override-indicator');
        if (existing) {
            existing.remove();
        }

        const hasOverride = student?.hasoverride || this.reactive.state.submission?.hasoverride;
        if (!hasOverride) {
            return;
        }

        const indicator = document.createElement('span');
        indicator.className = 'local-unifiedgrader-override-indicator badge bg-danger ms-1';
        indicator.style.fontSize = '0.7em';
        indicator.innerHTML = '<i class="fa fa-clock-o me-1"></i>Override';
        wrapper.appendChild(indicator);
    }

    /**
     * React to submission data updates (e.g. after override add/delete).
     *
     * @param {object} args Watcher args.
     * @param {object} args.state Current state.
     */
    _onSubmissionUpdated({state}) {
        const participants = [...state.participants.values()];
        const current = participants.find(p => p.id === state.currentUser?.id);

        // Rebuild the status dropdown first (it clears the wrapper),
        // then add the override indicator after.
        if (current) {
            const merged = {...current, hasoverride: state.submission?.hasoverride ?? current.hasoverride};
            const wrapper = this._container?.querySelector('[data-region="student-status-wrapper"]');
            if (wrapper) {
                this._buildStatusDropdown(wrapper, merged);
            }
            this._updateOverrideIndicator(merged);
        }
    }

    // ──────────────────────────────────────────────
    //  Status actions dropdown
    // ──────────────────────────────────────────────

    /**
     * Build the status badge as a dropdown (for assign/quiz) or a plain badge.
     *
     * @param {HTMLElement} wrapper The status wrapper element.
     * @param {object} student Participant data with status and locked fields.
     */
    _buildStatusDropdown(wrapper, student) {
        const state = this.reactive.state;
        const actType = state.activity.type;
        const hasDropdown = actType === 'assign' || actType === 'quiz';
        const statusInfo = this._getStatusInfo(student.status);

        // For unsupported activities or if strings aren't loaded, show plain badge.
        if (!hasDropdown || !this._strings) {
            wrapper.innerHTML = '';
            const badge = document.createElement('span');
            badge.className = 'badge ' + statusInfo.cls;
            badge.textContent = statusInfo.label;
            wrapper.appendChild(badge);
            return;
        }

        const actions = this._getActionsForStatus(
            student.status, !!student.locked, !!student.hasoverride, !!student.hasextension,
        );
        if (actions.length === 0) {
            wrapper.innerHTML = '';
            const badge = document.createElement('span');
            badge.className = 'badge ' + statusInfo.cls;
            badge.textContent = statusInfo.label;
            wrapper.appendChild(badge);
            return;
        }

        // Build Bootstrap 5 dropdown.
        const dropdown = document.createElement('div');
        dropdown.className = 'dropdown d-inline-block';

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'badge border-0 ' + statusInfo.cls;
        toggle.style.cursor = 'pointer';

        // Badge text + optional lock icon + dropdown caret.
        const labelText = document.createTextNode(statusInfo.label + ' ');
        toggle.appendChild(labelText);
        if (student.locked) {
            const lockIcon = document.createElement('i');
            lockIcon.className = 'fa fa-lock me-1';
            toggle.appendChild(lockIcon);
        }
        const caret = document.createElement('i');
        caret.className = 'fa fa-caret-down';
        caret.style.fontSize = '0.8em';
        toggle.appendChild(caret);

        const menu = document.createElement('ul');
        menu.className = 'dropdown-menu local-unifiedgrader-status-menu';
        menu.style.minWidth = '220px';

        actions.forEach((action) => {
            const li = document.createElement('li');
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'dropdown-item small';

            const icon = document.createElement('i');
            icon.className = 'fa ' + action.icon + ' fa-fw me-1';
            btn.appendChild(icon);
            btn.appendChild(document.createTextNode(action.label));

            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                menu.classList.remove('show');
                this._handleStatusAction(action, student);
            });

            li.appendChild(btn);
            menu.appendChild(li);
        });

        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            menu.classList.toggle('show');
        });

        dropdown.appendChild(toggle);
        dropdown.appendChild(menu);

        wrapper.innerHTML = '';
        wrapper.appendChild(dropdown);
    }

    /**
     * Get status display info (label and CSS class).
     *
     * @param {string} status Submission status string.
     * @return {object} Object with label and cls properties.
     */
    _getStatusInfo(status) {
        const map = {
            submitted: {label: 'Submitted', cls: 'bg-success'},
            graded: {label: 'Graded', cls: 'bg-info'},
            draft: {label: 'Draft', cls: 'bg-warning'},
            nosubmission: {label: 'No submission', cls: 'bg-secondary'},
            new: {label: 'Not submitted', cls: 'bg-secondary'},
        };
        return map[status] || {label: status, cls: 'bg-secondary'};
    }

    /**
     * Get available actions for a given submission status.
     *
     * @param {string} status Submission status.
     * @param {boolean} locked Whether the submission is locked.
     * @param {boolean} hasOverride Whether the student has an active override.
     * @param {boolean} hasExtension Whether the student has an active extension.
     * @return {object[]} Array of action objects.
     */
    _getActionsForStatus(status, locked, hasOverride, hasExtension) {
        const s = this._strings;
        if (!s) {
            return [];
        }

        const state = this.reactive.state;
        const actType = state.activity.type;

        // Override actions (available for assign and quiz when teacher has capability).
        const overrideActions = [];
        if (state.activity.canmanageoverrides && (actType === 'assign' || actType === 'quiz')) {
            if (hasOverride) {
                overrideActions.push({
                    id: 'edit_override', label: s.action_edit_override,
                    icon: 'fa-clock-o', type: 'modal',
                });
                overrideActions.push({
                    id: 'delete_override', label: s.action_delete_override,
                    icon: 'fa-trash', type: 'ajax', confirm: s.confirm_delete_override,
                });
            } else {
                overrideActions.push({
                    id: 'add_override', label: s.action_add_override,
                    icon: 'fa-clock-o', type: 'modal',
                });
            }
        }

        // For quiz, only override actions are available (no submission management).
        if (actType === 'quiz') {
            return overrideActions;
        }

        const defs = {
            edit_submission: {
                id: 'edit_submission', label: s.action_edit_submission,
                icon: 'fa-pencil', type: 'redirect',
            },
            grant_extension: {
                id: 'grant_extension',
                label: hasExtension ? s.action_edit_extension : s.action_grant_extension,
                icon: 'fa-calendar-plus-o', type: 'modal',
            },
            submit_for_grading: {
                id: 'submit', label: s.action_submit_for_grading,
                icon: 'fa-check-circle', type: 'ajax', confirm: s.confirm_submit_for_grading,
            },
            revert_to_draft: {
                id: 'revert_to_draft', label: s.action_revert_to_draft,
                icon: 'fa-undo', type: 'ajax', confirm: s.confirm_revert_to_draft,
            },
            remove: {
                id: 'remove', label: s.action_remove_submission,
                icon: 'fa-trash', type: 'ajax', confirm: s.confirm_remove_submission,
            },
            lock: {
                id: 'lock', label: s.action_lock,
                icon: 'fa-lock', type: 'ajax', confirm: s.confirm_lock_submission,
            },
            unlock: {
                id: 'unlock', label: s.action_unlock,
                icon: 'fa-unlock', type: 'ajax', confirm: s.confirm_unlock_submission,
            },
        };

        let actions;
        switch (status) {
            case 'submitted':
                actions = [defs.grant_extension, defs.revert_to_draft, defs.remove];
                break;
            case 'draft':
                if (locked) {
                    actions = [defs.unlock, defs.grant_extension, defs.remove];
                } else {
                    actions = [
                        defs.edit_submission, defs.lock, defs.grant_extension,
                        defs.submit_for_grading, defs.remove,
                    ];
                }
                break;
            case 'nosubmission':
            case 'new':
                actions = [defs.edit_submission, defs.grant_extension];
                break;
            case 'graded':
                actions = [defs.revert_to_draft, defs.grant_extension, defs.remove];
                break;
            default:
                actions = [];
        }

        // Append override actions after assignment-specific actions.
        return actions.concat(overrideActions);
    }

    /**
     * Handle a status action (AJAX, redirect, or modal).
     *
     * @param {object} action Action definition.
     * @param {object} student Participant data.
     */
    _handleStatusAction(action, student) {
        if (action.type === 'redirect') {
            this._handleRedirectAction(action.id, student);
            return;
        }

        if (action.type === 'modal') {
            this._handleModalAction(action.id, student);
            return;
        }

        // AJAX action — confirm first.
        if (action.confirm && !window.confirm(action.confirm)) {
            return;
        }

        const state = this.reactive.state;

        // Delete override uses its own mutation.
        if (action.id === 'delete_override') {
            this.reactive.dispatch('deleteUserOverride', state.activity.cmid, student.id);
            return;
        }

        this.reactive.dispatch('submissionAction', state.activity.cmid, student.id, action.id);
    }

    /**
     * Handle a modal action (override add/edit, extension grant/edit).
     *
     * @param {string} actionId Action identifier.
     * @param {object} student Participant data.
     */
    async _handleModalAction(actionId, student) {
        const state = this.reactive.state;
        const cmid = state.activity.cmid;
        let saved = false;

        if (actionId === 'grant_extension') {
            const {openExtensionModal} = await import('local_unifiedgrader/extension_modal');
            saved = await openExtensionModal(cmid, student.id, !!student.hasextension);
        } else {
            // Override modal (add/edit).
            const overrideid = state.submission?.overrideid || 0;
            const {openOverrideModal} = await import('local_unifiedgrader/override_modal');
            saved = await openOverrideModal(cmid, student.id, overrideid);
        }

        if (saved) {
            // Refresh the student data and participant list to pick up changes.
            this.reactive.dispatch('loadStudent', cmid, student.id);
            this.reactive.dispatch('updateFilters', cmid, {});
        }
    }

    /**
     * Navigate to a Moodle assign page for redirect actions.
     *
     * @param {string} actionId Action identifier.
     * @param {object} student Participant data.
     */
    _handleRedirectAction(actionId, student) {
        const state = this.reactive.state;
        const cmid = state.activity.cmid;
        const userid = student.id;
        const base = M.cfg.wwwroot + '/mod/assign/view.php';

        let url;
        switch (actionId) {
            case 'edit_submission':
                url = base + '?id=' + cmid + '&userid=' + userid + '&action=editsubmission';
                break;
            default:
                return;
        }

        window.location.href = url;
    }

    // ──────────────────────────────────────────────
    //  Navigation and filtering
    // ──────────────────────────────────────────────

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
