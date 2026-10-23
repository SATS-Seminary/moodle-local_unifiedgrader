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
 * The grader's Friction Feedback switch for this activity.
 *
 * @module     local_unifiedgrader/friction_mode
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {getString} from 'core/str';

/**
 * Show a mode on the button: the icon, its tooltip, and the ticked menu item.
 *
 * @param {HTMLElement} button The toggle button.
 * @param {string} mode inherit, on, or off
 */
const show = async(button, mode) => {
    const inherit = mode === 'inherit';
    const applies = inherit ? button.dataset.sitedefault === 'on' : mode === 'on';
    const title = await getString(
        'friction_title_' + (applies ? 'on' : 'off') + (inherit ? '_default' : ''),
        'local_unifiedgrader'
    );
    button.dataset.mode = mode;
    button.title = title;
    button.setAttribute('aria-label', title);
    button.querySelector('.fa-stairs').classList.toggle('ug-icon-struck', !applies);
    button.parentElement.querySelectorAll('[data-value]').forEach((item) => {
        const current = item.dataset.value === mode;
        item.setAttribute('aria-current', current ? 'true' : 'false');
        item.querySelector('.fa-check').classList.toggle('invisible', !current);
    });
};

/**
 * Save the mode when the teacher picks one from the menu.
 */
export const init = () => {
    const button = document.querySelector('[data-action="friction-mode"]');
    if (!button || button.dataset.bound) {
        return;
    }
    button.dataset.bound = '1';
    button.parentElement.addEventListener('click', async(e) => {
        const item = e.target.closest('[data-value]');
        if (!item || item.dataset.value === button.dataset.mode) {
            return;
        }
        const previous = button.dataset.mode;
        const mode = item.dataset.value;
        button.disabled = true;
        await show(button, mode);
        try {
            await Ajax.call([{
                methodname: 'local_unifiedgrader_set_friction_mode',
                args: {
                    cmid: Number(button.dataset.cmid),
                    mode: mode,
                },
            }])[0];
        } catch (error) {
            await show(button, previous);
            Notification.exception(error);
        } finally {
            button.disabled = false;
        }
    });
};
