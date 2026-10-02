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

/**
 * Save the select when the teacher changes it.
 */
export const init = () => {
    const select = document.querySelector('[data-action="friction-mode"]');
    if (!select || select.dataset.bound) {
        return;
    }
    select.dataset.bound = '1';
    select.addEventListener('change', async() => {
        const previous = select.dataset.previous || select.value;
        select.dataset.previous = select.value;
        select.disabled = true;
        try {
            await Ajax.call([{
                methodname: 'local_unifiedgrader_set_friction_mode',
                args: {
                    cmid: Number(select.dataset.cmid),
                    mode: select.value,
                },
            }])[0];
        } catch (error) {
            select.value = previous;
            Notification.exception(error);
        } finally {
            select.disabled = false;
        }
    });
};
