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
 * Post grades dropdown component - allows teachers to post, hide, or schedule grade visibility.
 *
 * The menu posts the open student, the groups in the current filter, or the
 * whole class. Group actions are hidden while the filter is the whole class.
 * Class actions are omitted from the page when this teacher cannot post the class.
 *
 * @module     local_unifiedgrader/components/post_grades_toggle
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent} from 'core/reactive';
import {get_string as getString} from 'core/str';

export default class extends BaseComponent {

    /**
     * Component creation hook.
     */
    create() {
        this.name = 'post_grades_toggle';
        this.selectors = {
            STATUS_BTN: '[data-action="post-grades-status"]',
            POST_NOW: '[data-action="post-grades-now"]',
            HIDE_GRADES: '[data-action="hide-grades"]',
            POST_STUDENT: '[data-action="post-grades-student"]',
            HIDE_STUDENT: '[data-action="hide-grades-student"]',
            POST_GROUPS: '[data-action="post-grades-groups"]',
            HIDE_GROUPS: '[data-action="hide-grades-groups"]',
            SCHEDULE_INPUT: '[data-action="schedule-date-input"]',
            SCHEDULE_SCOPE: '[data-action="schedule-scope"]',
            SCHEDULE_BTN: '[data-action="schedule-post"]',
            MENU: '[data-region="post-grades-menu"]',
        };
    }

    /**
     * Register state watchers.
     *
     * @return {Array}
     */
    getWatchers() {
        return [
            {watch: 'ui:updated', handler: this._updateStatus},
            {watch: 'filters:updated', handler: this._updateStatus},
        ];
    }

    /**
     * Called when state is first ready.
     *
     * @param {object} state Current state.
     */
    stateReady(state) {
        this._setupEventListeners();
        this._updateStatus({state});
    }

    /**
     * Set up DOM event listeners.
     */
    _setupEventListeners() {
        const menu = this.getElement(this.selectors.MENU);
        if (!menu) {
            return;
        }
        menu.addEventListener('click', (e) => {
            const action = e.target.closest('[data-action]')?.dataset.action;
            if (action === 'post-grades-now') {
                this._handlePostNow(e);
            } else if (action === 'hide-grades') {
                this._handleHide(e);
            } else if (action === 'post-grades-student') {
                this._confirmAndPost(e, 0, 'user');
            } else if (action === 'hide-grades-student') {
                this._confirmAndPost(e, 1, 'user');
            } else if (action === 'post-grades-groups') {
                this._confirmAndPost(e, 0, 'groups');
            } else if (action === 'hide-grades-groups') {
                this._confirmAndPost(e, 1, 'groups');
            } else if (action === 'schedule-post') {
                this._handleSchedule(e);
            }
        });
    }

    /**
     * Handle "Post the whole class" click.
     *
     * @param {Event} e Click event.
     */
    async _handlePostNow(e) {
        e.preventDefault();
        const state = this.reactive.state;
        if (state.ui.posting || !state.ui.canPostClass) {
            return;
        }

        // Quizzes get a more specific confirmation because the post / unpost
        // action there flips a narrow slice of the review-options matrix and
        // teachers (rightly) want to know exactly what changes vs what
        // stays as they configured it on the quiz Review options page.
        const stringkey = state.activity.type === 'quiz'
            ? 'confirm_post_grades_quiz'
            : 'confirm_post_grades';
        const confirmMsg = await getString(stringkey, 'local_unifiedgrader');
        if (!window.confirm(confirmMsg)) {
            return;
        }

        this._dispatch(0, 'class');
    }

    /**
     * Handle "Hide the whole class" click.
     *
     * @param {Event} e Click event.
     */
    async _handleHide(e) {
        e.preventDefault();
        const state = this.reactive.state;
        if (state.ui.posting || !state.ui.canPostClass) {
            return;
        }

        const stringkey = state.activity.type === 'quiz'
            ? 'confirm_unpost_grades_quiz'
            : 'confirm_unpost_grades';
        const confirmMsg = await getString(stringkey, 'local_unifiedgrader');
        if (!window.confirm(confirmMsg)) {
            return;
        }

        this._dispatch(1, 'class');
    }

    /**
     * Confirm and post or hide the open student, or the groups in the filter.
     *
     * @param {Event} e Click event.
     * @param {number} hidden 0 to post, 1 to hide.
     * @param {string} scope user or groups.
     */
    async _confirmAndPost(e, hidden, scope) {
        e.preventDefault();
        const state = this.reactive.state;
        if (state.ui.posting) {
            return;
        }
        if (scope === 'groups' && this._groupsInView(state).length === 0) {
            return;
        }
        if (scope === 'user' && !state.currentUser?.id) {
            return;
        }

        const quiz = state.activity.type === 'quiz';
        let stringkey;
        let param = null;
        if (scope === 'groups') {
            stringkey = hidden === 0 ? 'confirm_post_grades_groups' : 'confirm_unpost_grades_groups';
            if (quiz) {
                stringkey = hidden === 0 ? 'confirm_post_grades_quiz_partial' : 'confirm_unpost_grades_quiz_partial';
            }
            param = this._groupsInView(state).map((group) => group.name).join(', ');
        } else if (quiz) {
            stringkey = hidden === 0 ? 'confirm_post_grades_quiz_partial' : 'confirm_unpost_grades_quiz_partial';
        } else {
            stringkey = hidden === 0 ? 'confirm_post_grades_student' : 'confirm_unpost_grades_student';
        }
        const confirmMsg = await getString(stringkey, 'local_unifiedgrader', param);
        if (!window.confirm(confirmMsg)) {
            return;
        }
        this._dispatch(hidden, scope);
    }

    /**
     * Handle "Schedule" click.
     *
     * @param {Event} e Click event.
     */
    async _handleSchedule(e) {
        e.preventDefault();
        const state = this.reactive.state;
        if (state.ui.posting) {
            return;
        }

        const input = this.getElement(this.selectors.SCHEDULE_INPUT);
        if (!input || !input.value) {
            return;
        }

        const timestamp = Math.floor(new Date(input.value).getTime() / 1000);
        if (isNaN(timestamp) || timestamp <= Math.floor(Date.now() / 1000)) {
            const errorMsg = await getString('schedule_must_be_future', 'local_unifiedgrader');
            window.alert(errorMsg);
            return;
        }

        const scopeSelect = this.getElement(this.selectors.SCHEDULE_SCOPE);
        let scope = scopeSelect ? scopeSelect.value : 'class';
        if (scope === 'class' && !state.ui.canPostClass) {
            scope = 'user';
        }
        if (scope === 'groups' && this._groupsInView(state).length === 0) {
            scope = state.ui.canPostClass ? 'class' : 'user';
        }
        this._dispatch(timestamp, scope);
    }

    /**
     * Send the post to the server.
     *
     * @param {number} hidden
     * @param {string} scope class, user, or groups.
     */
    _dispatch(hidden, scope) {
        const state = this.reactive.state;
        const userid = scope === 'user' ? (state.currentUser?.id || 0) : 0;
        const groupids = scope === 'groups' ? this._groupsInView(state).map((group) => group.id) : [];
        this.reactive.dispatch('setGradesPosted', state.activity.cmid, hidden, scope, userid, groupids);
    }

    /**
     * The groups named by the current filter.
     *
     * The whole class ("0") names none, so the group actions stay hidden.
     * "All my groups" ("-1") names the teacher's own groups.
     *
     * @param {object} state
     * @return {Array}
     */
    _groupsInView(state) {
        const filter = String(state.filters?.group ?? '0');
        if (filter === '0' || filter === '') {
            return [];
        }
        const groups = [...state.groups.values()];
        if (filter === '-1') {
            const mine = (state.userGroupIds?.ids || []).map(String);
            return groups.filter((group) => mine.includes(String(group.id)));
        }
        const ids = filter.split(',').filter(Boolean);
        return groups.filter((group) => ids.includes(String(group.id)));
    }

    /**
     * Update the status button and menu items based on state.
     *
     * @param {object} args Watcher args.
     * @param {object} args.state Current state.
     */
    async _updateStatus({state}) {
        const btn = this.getElement(this.selectors.STATUS_BTN);
        if (!btn) {
            return;
        }

        const icon = btn.querySelector('.fa');
        const label = btn.querySelector('span');
        const postNow = this.getElement(this.selectors.POST_NOW);
        const hideGrades = this.getElement(this.selectors.HIDE_GRADES);
        const scheduleInput = this.getElement(this.selectors.SCHEDULE_INPUT);

        await this._updateGroupActions(state);
        this._updateScheduleScope(state);

        // Spinner while posting.
        if (state.ui.posting) {
            btn.disabled = true;
            btn.className = 'btn btn-sm btn-outline-secondary dropdown-toggle';
            if (icon) {
                icon.className = 'fa fa-spinner fa-spin';
            }
            return;
        }

        btn.disabled = false;
        const hidden = state.ui.gradesHidden;

        if (state.ui.gradesPartial) {
            btn.className = 'btn btn-sm btn-outline-warning dropdown-toggle';
            if (icon) {
                icon.className = 'fa fa-eye';
            }
            if (label) {
                label.textContent = await getString('grades_posted_count', 'local_unifiedgrader', {
                    posted: state.ui.postedCount,
                    total: state.ui.postedTotal,
                });
            }
            if (postNow) {
                postNow.classList.remove('disabled');
            }
            if (hideGrades) {
                hideGrades.classList.remove('disabled');
            }
        } else if (state.ui.gradesPosted) {
            // Grades are visible.
            btn.className = 'btn btn-sm btn-outline-success dropdown-toggle';
            if (icon) {
                icon.className = 'fa fa-eye';
            }
            if (label) {
                label.textContent = await getString('grades_posted', 'local_unifiedgrader');
            }
            // Disable "Post now" since already posted, enable "Hide".
            if (postNow) {
                postNow.classList.add('disabled');
            }
            if (hideGrades) {
                hideGrades.classList.remove('disabled');
            }
        } else if (hidden > 1) {
            // Scheduled — hidden until a timestamp.
            btn.className = 'btn btn-sm btn-outline-info dropdown-toggle';
            if (icon) {
                icon.className = 'fa fa-clock-o';
            }
            if (label) {
                const date = new Date(hidden * 1000);
                const formatted = date.toLocaleDateString(undefined, {
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                });
                const template = await getString('grades_scheduled', 'local_unifiedgrader');
                label.textContent = template.replace('{$a}', formatted);
            }
            // Pre-fill the schedule input with the current scheduled date.
            if (scheduleInput) {
                scheduleInput.value = this._toDatetimeLocalValue(hidden);
            }
            // Both "Post now" and "Hide" are available.
            if (postNow) {
                postNow.classList.remove('disabled');
            }
            if (hideGrades) {
                hideGrades.classList.remove('disabled');
            }
        } else {
            // Grades are hidden (hidden === 1, or nothing is released).
            btn.className = 'btn btn-sm btn-outline-warning dropdown-toggle';
            if (icon) {
                icon.className = 'fa fa-eye-slash';
            }
            if (label) {
                label.textContent = await getString('grades_hidden', 'local_unifiedgrader');
            }
            // Enable "Post now", disable "Hide".
            if (postNow) {
                postNow.classList.remove('disabled');
            }
            if (hideGrades) {
                hideGrades.classList.add('disabled');
            }
        }
    }

    /**
     * Show the group actions when the filter names groups, and label them.
     *
     * @param {object} state
     */
    async _updateGroupActions(state) {
        const groups = this._groupsInView(state);
        const menu = this.getElement(this.selectors.MENU);
        if (!menu) {
            return;
        }
        menu.querySelectorAll('[data-region="post-grades-group-actions"]').forEach((item) => {
            item.classList.toggle('d-none', groups.length === 0);
        });
        if (!groups.length) {
            return;
        }
        const names = groups.map((group) => group.name).join(', ');
        const postLabel = menu.querySelector('[data-region="group-post-label"]');
        const hideLabel = menu.querySelector('[data-region="group-hide-label"]');
        if (postLabel) {
            postLabel.textContent = await getString('post_grades_groups', 'local_unifiedgrader', names);
        }
        if (hideLabel) {
            hideLabel.textContent = await getString('unpost_grades_groups', 'local_unifiedgrader', names);
        }
    }

    /**
     * Offer a schedule scope only when that scope can be posted.
     *
     * @param {object} state
     */
    _updateScheduleScope(state) {
        const select = this.getElement(this.selectors.SCHEDULE_SCOPE);
        if (!select) {
            return;
        }
        const classOption = select.querySelector('option[value="class"]');
        const groupsOption = select.querySelector('option[value="groups"]');
        if (classOption) {
            classOption.hidden = !state.ui.canPostClass;
        }
        const groupsAvailable = this._groupsInView(state).length > 0;
        if (groupsOption) {
            groupsOption.hidden = !groupsAvailable;
        }
        const selected = select.options[select.selectedIndex];
        if (selected && selected.hidden) {
            select.value = state.ui.canPostClass ? 'class' : 'user';
        }
    }

    /**
     * Convert a Unix timestamp to a datetime-local input value string.
     *
     * @param {number} timestamp Unix timestamp in seconds.
     * @return {string} Value in YYYY-MM-DDThh:mm format.
     */
    _toDatetimeLocalValue(timestamp) {
        const date = new Date(timestamp * 1000);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${year}-${month}-${day}T${hours}:${minutes}`;
    }
}
