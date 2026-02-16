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
            SCHEDULE_INPUT: '[data-action="schedule-date-input"]',
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
        const postNow = this.getElement(this.selectors.POST_NOW);
        const hideGrades = this.getElement(this.selectors.HIDE_GRADES);
        const scheduleBtn = this.getElement(this.selectors.SCHEDULE_BTN);

        if (postNow) {
            postNow.addEventListener('click', (e) => this._handlePostNow(e));
        }
        if (hideGrades) {
            hideGrades.addEventListener('click', (e) => this._handleHide(e));
        }
        if (scheduleBtn) {
            scheduleBtn.addEventListener('click', (e) => this._handleSchedule(e));
        }
    }

    /**
     * Handle "Post grades now" click.
     *
     * @param {Event} e Click event.
     */
    async _handlePostNow(e) {
        e.preventDefault();
        const state = this.reactive.state;
        if (state.ui.posting) {
            return;
        }

        const confirmMsg = await getString('confirm_post_grades', 'local_unifiedgrader');
        if (!window.confirm(confirmMsg)) {
            return;
        }

        this.reactive.dispatch('setGradesPosted', state.activity.cmid, 0);
    }

    /**
     * Handle "Hide grades" click.
     *
     * @param {Event} e Click event.
     */
    async _handleHide(e) {
        e.preventDefault();
        const state = this.reactive.state;
        if (state.ui.posting) {
            return;
        }

        const confirmMsg = await getString('confirm_unpost_grades', 'local_unifiedgrader');
        if (!window.confirm(confirmMsg)) {
            return;
        }

        this.reactive.dispatch('setGradesPosted', state.activity.cmid, 1);
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

        this.reactive.dispatch('setGradesPosted', state.activity.cmid, timestamp);
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

        if (state.ui.gradesPosted) {
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
            // Grades are hidden (hidden === 1).
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
