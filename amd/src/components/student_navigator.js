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
import {get_string as getString} from 'core/str';
import * as DirtyTracker from 'local_unifiedgrader/dirty_tracker';

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
            GROUP_DROPDOWN_TOGGLE: '[data-action="group-dropdown-toggle"]',
            GROUP_DROPDOWN_MENU: '[data-region="group-dropdown-menu"]',
            PARTICIPANT_LIST: '[data-region="participant-list"]',
            PROFILE_TOGGLE: '[data-action="toggle-profile-popout"]',
            PROFILE_POPOUT: '[data-region="profile-popout"]',
        };
        this._searchTimeout = null;
        this._filtersVisible = false;
        this._container = null;
        /** @type {?object} Prefetched lang strings for status actions. */
        this._strings = null;
        /** @type {boolean} Whether the profile popout is visible. */
        this._profileVisible = false;
        /** @type {?Function} Outside-click handler for profile popout dismissal. */
        this._profileOutsideClickHandler = null;
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
        this._setupProfilePopout();
        this._initGroupSelector(state);

        // Prefetch action strings for assign, quiz, and forum activities.
        if (state.activity.type === 'assign' || state.activity.type === 'quiz' || state.activity.type === 'forum') {
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
            'action_delete_extension',
            'confirm_delete_extension',
            'overrides_extensions',
            'action_clear_overrides',
            'confirm_clear_overrides',
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

        // Group filter is handled by _initGroupSelector (checkbox dropdown).

        // Keyboard navigation.
        const SCROLL_STEP = 80;
        document.addEventListener('keydown', (e) => {
            // Only handle if no input is focused.
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
                return;
            }
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                this._navigateStudent(-1);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                this._navigateStudent(1);
            } else if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
                // Scroll the preview pane content.
                const root = this._container?.closest('.local-unifiedgrader-container');
                if (!root) {
                    return;
                }
                // PDF viewer scroll container.
                const pdfContainer = root.querySelector('[data-region="pdf-page-container"]');
                if (pdfContainer && pdfContainer.offsetParent !== null) {
                    e.preventDefault();
                    pdfContainer.scrollBy({top: e.key === 'ArrowDown' ? SCROLL_STEP : -SCROLL_STEP});
                    return;
                }
                // Iframe preview (non-PDF submissions, forum, quiz).
                const iframe = root.querySelector('[data-region="preview-iframe"]');
                if (iframe && iframe.offsetParent !== null && iframe.contentDocument) {
                    e.preventDefault();
                    try {
                        iframe.contentDocument.documentElement.scrollBy(
                            {top: e.key === 'ArrowDown' ? SCROLL_STEP : -SCROLL_STEP}
                        );
                    } catch (_err) {
                        // Cross-origin iframe — cannot scroll programmatically.
                    }
                    return;
                }
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
    async _initGroupSelector(state) {
        if (!state.filters.hasGroupMode) {
            return;
        }

        const wrapper = this.getElement(this.selectors.GROUP_FILTER);
        const menu = this.getElement(this.selectors.GROUP_DROPDOWN_MENU);
        const toggle = this.getElement(this.selectors.GROUP_DROPDOWN_TOGGLE);
        if (!wrapper || !menu || !toggle) {
            return;
        }

        // Show the group filter row.
        wrapper.classList.remove('d-none');

        const groups = [...state.groups.values()];
        const userGroupIds = state.userGroupIds?.ids || [];
        const hasMyGroups = userGroupIds.length > 1;

        // Fetch localised labels.
        const strings = await getStrings([
            {key: 'filter_allgroups', component: 'local_unifiedgrader'},
            {key: 'filter_mygroups', component: 'local_unifiedgrader'},
        ]);
        const allGroupsLabel = strings[0];
        const myGroupsLabel = strings[1];

        // Build checkbox items.
        menu.innerHTML = '';

        // "All groups" option (value "0").
        this._addGroupCheckbox(menu, '0', allGroupsLabel);

        // "All my groups" option (value "-1") — only if teacher has 2+ groups.
        if (hasMyGroups) {
            this._addGroupCheckbox(menu, '-1', myGroupsLabel);
        }

        // Divider.
        if (groups.length > 0) {
            const divider = document.createElement('hr');
            divider.className = 'dropdown-divider my-1';
            menu.appendChild(divider);
        }

        // Individual groups.
        groups.forEach((group) => {
            this._addGroupCheckbox(menu, String(group.id), group.name);
        });

        // Set initial checked state from current filter.
        this._setGroupCheckState(state.filters.group);
        this._updateGroupButtonLabel(toggle, allGroupsLabel, myGroupsLabel, groups);

        // Event listener for checkbox changes.
        menu.addEventListener('change', (e) => {
            const checkbox = e.target;
            if (!checkbox.matches('input[type="checkbox"]')) {
                return;
            }
            const value = checkbox.dataset.groupValue;

            if (value === '0' || value === '-1') {
                // Meta-options: uncheck all individual groups.
                this._uncheckAllGroupCheckboxes(menu);
                checkbox.checked = true;
            } else {
                // Individual group: uncheck meta-options.
                const metaBoxes = menu.querySelectorAll('input[data-group-value="0"], input[data-group-value="-1"]');
                metaBoxes.forEach(cb => {
                    cb.checked = false;
                });
                // If nothing is checked, revert to "All groups".
                const anyChecked = menu.querySelectorAll('input[type="checkbox"]:checked');
                if (anyChecked.length === 0) {
                    const allBox = menu.querySelector('input[data-group-value="0"]');
                    if (allBox) {
                        allBox.checked = true;
                    }
                }
            }

            // Compute the group filter value.
            const groupValue = this._computeGroupFilterValue(menu);
            this._updateGroupButtonLabel(toggle, allGroupsLabel, myGroupsLabel, groups);
            this._applyFilters({group: groupValue});
        });
    }

    /**
     * Add a checkbox item to the group dropdown menu.
     *
     * @param {HTMLElement} menu The dropdown menu container.
     * @param {string} value The group value.
     * @param {string} label The display label.
     */
    _addGroupCheckbox(menu, value, label) {
        const item = document.createElement('label');
        item.className = 'dropdown-item d-flex align-items-center gap-2 py-1 px-2 user-select-none';
        item.style.cursor = 'pointer';

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.dataset.groupValue = value;
        checkbox.className = 'form-check-input mt-0';
        checkbox.style.cursor = 'pointer';

        const text = document.createElement('span');
        text.className = 'small text-truncate';
        text.textContent = label;

        item.appendChild(checkbox);
        item.appendChild(text);
        menu.appendChild(item);
    }

    /**
     * Set checkbox states from a group filter value string.
     *
     * @param {string} filterValue Current filter: "0", "-1", or comma-separated IDs.
     */
    _setGroupCheckState(filterValue) {
        const menu = this.getElement(this.selectors.GROUP_DROPDOWN_MENU);
        if (!menu) {
            return;
        }
        const val = String(filterValue);
        this._uncheckAllGroupCheckboxes(menu);

        if (val === '0' || val === '-1') {
            const cb = menu.querySelector(`input[data-group-value="${val}"]`);
            if (cb) {
                cb.checked = true;
            }
        } else {
            const ids = val.split(',');
            ids.forEach(id => {
                const cb = menu.querySelector(`input[data-group-value="${id}"]`);
                if (cb) {
                    cb.checked = true;
                }
            });
        }
    }

    /**
     * Uncheck all group checkboxes.
     *
     * @param {HTMLElement} menu The dropdown menu.
     */
    _uncheckAllGroupCheckboxes(menu) {
        menu.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            cb.checked = false;
        });
    }

    /**
     * Compute the group filter value from checked checkboxes.
     *
     * @param {HTMLElement} menu The dropdown menu.
     * @returns {string} Filter value: "0", "-1", or comma-separated group IDs.
     */
    _computeGroupFilterValue(menu) {
        // Check meta-options first.
        const allBox = menu.querySelector('input[data-group-value="0"]');
        if (allBox?.checked) {
            return '0';
        }
        const myBox = menu.querySelector('input[data-group-value="-1"]');
        if (myBox?.checked) {
            return '-1';
        }
        // Collect checked individual groups.
        const checked = [...menu.querySelectorAll('input[type="checkbox"]:checked')];
        const ids = checked.map(cb => cb.dataset.groupValue).filter(v => v !== '0' && v !== '-1');
        return ids.length > 0 ? ids.join(',') : '0';
    }

    /**
     * Update the dropdown toggle button text based on current selection.
     *
     * @param {HTMLElement} toggle The dropdown toggle button.
     * @param {string} allGroupsLabel Localised "All groups" text.
     * @param {string} myGroupsLabel Localised "All my groups" text.
     * @param {Array} groups Array of group objects with id and name.
     */
    _updateGroupButtonLabel(toggle, allGroupsLabel, myGroupsLabel, groups) {
        const menu = this.getElement(this.selectors.GROUP_DROPDOWN_MENU);
        if (!menu || !toggle) {
            return;
        }
        const value = this._computeGroupFilterValue(menu);
        if (value === '0') {
            toggle.textContent = allGroupsLabel;
            return;
        }
        if (value === '-1') {
            toggle.textContent = myGroupsLabel;
            return;
        }
        // Show selected group names.
        const ids = value.split(',');
        const names = ids.map(id => {
            const g = groups.find(gr => String(gr.id) === id);
            return g ? g.name : id;
        });
        toggle.textContent = names.join(', ');
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

            // Show a calendar icon for users with a due date extension.
            if (p.hasextension) {
                const extIcon = document.createElement('i');
                extIcon.className = 'fa fa-calendar-plus-o text-info';
                extIcon.style.fontSize = '0.7em';
                extIcon.title = 'Extension granted';
                statusWrapper.appendChild(extIcon);
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

        // Close the profile popout on student switch.
        this._hideProfilePopout();

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

        // Submission date is updated separately via _updateSubmittedDate()
        // which is called from the submission:updated watcher. On initial load,
        // fall back to the participant's submittedat if submission state isn't ready yet.
        if (dateEl) {
            const sub = this.reactive?.stateManager?.state?.submission;
            if (sub && sub.timemodified > 0) {
                this._updateSubmittedDate(this.reactive.stateManager.state);
            } else {
                const hasSubmission = student && student.submittedat > 0
                    && student.status !== 'new' && student.status !== 'nosubmission';
                if (hasSubmission) {
                    const date = new Date(student.submittedat * 1000);
                    dateEl.textContent = 'Submitted: ' + date.toLocaleString();
                } else {
                    dateEl.textContent = '';
                }
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

        // Update the submitted date from the current attempt's data.
        this._updateSubmittedDate(state);
    }

    /**
     * Update the "Submitted:" date from the current attempt's submission data.
     *
     * Uses state.submission.timemodified which is set per-attempt by
     * both loadStudent and loadAttempt, ensuring the date reflects
     * the currently viewed attempt rather than always the latest.
     *
     * @param {object} state Current state.
     */
    _updateSubmittedDate(state) {
        if (!this._container) {
            return;
        }
        const dateEl = this._container.querySelector('[data-region="student-submitted-date"]');
        if (!dateEl) {
            return;
        }
        const sub = state.submission;
        const hasSubmission = sub && sub.timemodified > 0
            && sub.status !== 'new' && sub.status !== 'nosubmission' && sub.status !== 'reopened';
        if (hasSubmission) {
            const date = new Date(sub.timemodified * 1000);
            dateEl.textContent = 'Submitted: ' + date.toLocaleString();
        } else {
            dateEl.textContent = '';
        }
    }

    // ──────────────────────────────────────────────
    //  Profile popout
    // ──────────────────────────────────────────────

    /**
     * Set up the profile popout toggle on the avatar/name area.
     */
    _setupProfilePopout() {
        if (!this._container) {
            return;
        }
        const toggle = this._container.querySelector('[data-action="toggle-profile-popout"]');
        if (toggle) {
            toggle.addEventListener('click', (e) => {
                // Don't toggle if the click is on a link inside the popout.
                if (e.target.closest('[data-region="profile-popout"]')) {
                    return;
                }
                e.stopPropagation();
                if (this._profileVisible) {
                    this._hideProfilePopout();
                } else {
                    this._showProfilePopout();
                }
            });
        }
    }

    /**
     * Show the profile popout with the current student's info.
     */
    async _showProfilePopout() {
        const popout = this._container?.querySelector('[data-region="profile-popout"]');
        if (!popout) {
            return;
        }

        const state = this.reactive.state;
        const participants = [...state.participants.values()];
        const student = participants.find(p => p.id === state.currentUser?.id);
        if (!student) {
            return;
        }

        // Build popout content.
        popout.innerHTML = '';

        // Top section: avatar + name + email.
        const top = document.createElement('div');
        top.className = 'd-flex align-items-center gap-3 p-3';

        if (student.profileimageurl) {
            const img = document.createElement('img');
            img.src = student.profileimageurl;
            img.alt = student.fullname;
            img.width = 80;
            img.height = 80;
            img.className = 'rounded-circle';
            img.style.objectFit = 'cover';
            top.appendChild(img);
        }

        const info = document.createElement('div');
        const nameEl = document.createElement('h6');
        nameEl.className = 'mb-1';
        nameEl.textContent = student.fullname;
        info.appendChild(nameEl);

        if (student.email) {
            const emailLink = document.createElement('a');
            emailLink.href = 'mailto:' + student.email;
            emailLink.className = 'small text-muted text-decoration-none';
            emailLink.textContent = student.email;
            info.appendChild(emailLink);
        } else {
            const noEmail = document.createElement('span');
            noEmail.className = 'small text-muted';
            noEmail.textContent = '—';
            getString('profile_no_email', 'local_unifiedgrader').then((s) => {
                noEmail.textContent = s;
                return s;
            }).catch(() => {
                // Ignore.
            });
            info.appendChild(noEmail);
        }

        top.appendChild(info);
        popout.appendChild(top);

        // Bottom section: action links.
        const actions = document.createElement('div');
        actions.className = 'px-3 pb-3 d-flex gap-2';

        const profileLink = document.createElement('a');
        profileLink.href = window.M.cfg.wwwroot + '/user/view.php?id=' + student.id
            + '&course=' + state.activity.courseid;
        profileLink.className = 'btn btn-sm btn-outline-secondary';
        profileLink.target = '_blank';
        profileLink.rel = 'noopener';
        profileLink.innerHTML = '<i class="fa fa-user me-1"></i>';
        getString('profile_view_full', 'local_unifiedgrader').then((s) => {
            profileLink.innerHTML = '<i class="fa fa-user me-1"></i>' + s;
            return s;
        }).catch(() => {
            // Ignore.
        });
        actions.appendChild(profileLink);

        if (state.ui.canloginas) {
            const loginLink = document.createElement('a');
            loginLink.href = window.M.cfg.wwwroot + '/course/loginas.php?id='
                + state.activity.courseid + '&user=' + student.id
                + '&sesskey=' + window.M.cfg.sesskey;
            loginLink.className = 'btn btn-sm btn-outline-secondary';
            loginLink.innerHTML = '<i class="fa fa-sign-in me-1"></i>';
            getString('profile_login_as', 'local_unifiedgrader').then((s) => {
                loginLink.innerHTML = '<i class="fa fa-sign-in me-1"></i>' + s;
                return s;
            }).catch(() => {
                // Ignore.
            });
            actions.appendChild(loginLink);
        }

        popout.appendChild(actions);

        // Show the popout and position with viewport clamping.
        popout.classList.remove('d-none');
        this._profileVisible = true;

        // Position using the toggle button's bounding rect.
        const toggle = this._container.querySelector('[data-action="toggle-profile-popout"]');
        if (toggle) {
            const rect = toggle.getBoundingClientRect();
            const POPOUT_WIDTH = 320;
            const MARGIN = 8;
            const vw = window.innerWidth;
            const vh = window.innerHeight;

            // Horizontal: align left with toggle, clamp to viewport.
            let left = rect.left;
            if (left + POPOUT_WIDTH > vw - MARGIN) {
                left = vw - POPOUT_WIDTH - MARGIN;
            }
            left = Math.max(MARGIN, left);

            // Vertical: prefer below toggle, flip above if no room.
            const popoutHeight = popout.offsetHeight || 200;
            let top = rect.bottom + 6;
            if (top + popoutHeight > vh - MARGIN) {
                const above = rect.top - 6 - popoutHeight;
                top = above >= MARGIN ? above : Math.max(MARGIN, vh - popoutHeight - MARGIN);
            }

            popout.style.top = top + 'px';
            popout.style.left = left + 'px';
        }

        // Outside-click dismissal (deferred to avoid catching the opening click).
        setTimeout(() => {
            this._profileOutsideClickHandler = (e) => {
                if (!popout.contains(e.target)
                    && !e.target.closest('[data-action="toggle-profile-popout"]')) {
                    this._hideProfilePopout();
                }
            };
            document.addEventListener('click', this._profileOutsideClickHandler, true);
        }, 0);
    }

    /**
     * Hide the profile popout.
     */
    _hideProfilePopout() {
        const popout = this._container?.querySelector('[data-region="profile-popout"]');
        if (popout) {
            popout.classList.add('d-none');
        }
        this._profileVisible = false;
        if (this._profileOutsideClickHandler) {
            document.removeEventListener('click', this._profileOutsideClickHandler, true);
            this._profileOutsideClickHandler = null;
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
        const hasDropdown = actType === 'assign' || actType === 'quiz' || actType === 'forum';
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

        // Unified "Overrides and Extensions" action — available for all activity types.
        const overridesExtensionsActions = [];
        const canManage = state.activity.canmanageoverrides || state.activity.canmanageextensions;
        if (canManage) {
            overridesExtensionsActions.push({
                id: 'overrides_extensions',
                label: s.overrides_extensions,
                icon: 'fa-clock-o', type: 'modal',
            });
            if (hasOverride || hasExtension) {
                overridesExtensionsActions.push({
                    id: 'clear_overrides',
                    label: s.action_clear_overrides,
                    icon: 'fa-trash', type: 'ajax', confirm: s.confirm_clear_overrides,
                });
            }
        }

        // For quiz and forum: only overrides/extensions actions (no submission actions).
        if (actType === 'quiz' || actType === 'forum') {
            return overridesExtensionsActions;
        }

        // Assignment-specific submission actions.
        const defs = {
            edit_submission: {
                id: 'edit_submission', label: s.action_edit_submission,
                icon: 'fa-pencil', type: 'redirect',
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
                actions = [defs.revert_to_draft, defs.remove];
                break;
            case 'draft':
                if (locked) {
                    actions = [defs.unlock, defs.remove];
                } else {
                    actions = [
                        defs.edit_submission, defs.lock,
                        defs.submit_for_grading, defs.remove,
                    ];
                }
                break;
            case 'nosubmission':
            case 'new':
                actions = [defs.edit_submission];
                break;
            case 'graded':
                actions = [defs.revert_to_draft, defs.remove];
                break;
            default:
                actions = [];
        }

        // Append overrides & extensions after assignment-specific actions.
        return actions.concat(overridesExtensionsActions);
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

        // Clear all overrides and extensions.
        if (action.id === 'clear_overrides') {
            this.reactive.dispatch('clearAllOverrides', state.activity.cmid, student.id);
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

        const {openOverridesExtensionsModal} =
            await import('local_unifiedgrader/overrides_extensions_modal');
        const saved = await openOverridesExtensionsModal(cmid, student.id);

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
    async _selectStudent(userid) {
        const state = this.reactive.state;
        if (userid === state.currentUser?.id) {
            return;
        }

        // Auto-save grade/feedback before switching if there are unsaved changes.
        if (DirtyTracker.isDirty('grade') || DirtyTracker.isDirty('feedback')) {
            this.element.dispatchEvent(new CustomEvent('unifiedgrader:requestsave', {
                bubbles: true,
            }));
            // Brief pause to let the save dispatch start.
            await new Promise(r => setTimeout(r, 100));
        }

        this.reactive.dispatch('loadStudent', state.activity.cmid, userid);

        // Update the URL so the current student is shareable/bookmarkable.
        const url = new URL(window.location.href);
        url.searchParams.set('userid', userid);
        window.history.replaceState(null, '', url.toString());

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
