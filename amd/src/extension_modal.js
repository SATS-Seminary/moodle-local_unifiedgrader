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
 * Extension modal — opens an iframe with Moodle's native extension form
 * and listens for postMessage events to detect save/cancel.
 *
 * @module     local_unifiedgrader/extension_modal
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';
import {getString} from 'core/str';

/**
 * Open the extension modal with an iframe containing the extension form.
 *
 * @param {number} cmid Course module ID.
 * @param {number} userid Student user ID.
 * @param {boolean} hasExtension Whether the user already has an extension.
 * @param {string} activityType Activity type ('assign' or 'quiz'). Defaults to 'assign'.
 * @return {Promise<boolean>} Resolves to true if extension was saved, false if cancelled.
 */
export const openExtensionModal = async(cmid, userid, hasExtension, activityType = 'assign') => {
    const title = await getString(
        hasExtension ? 'action_edit_extension' : 'action_grant_extension',
        'local_unifiedgrader',
    );

    let page;
    if (activityType === 'quiz') {
        page = 'quiz_extension.php';
    } else if (activityType === 'forum') {
        page = 'forum_extension.php';
    } else {
        page = 'extension.php';
    }
    const iframeUrl = M.cfg.wwwroot + '/local/unifiedgrader/' + page
        + '?cmid=' + cmid + '&userid=' + userid;

    const modal = await Modal.create({
        title: title,
        body: '<iframe src="' + iframeUrl + '" '
            + 'style="width:100%;height:400px;border:none;" '
            + 'id="unifiedgrader-extension-iframe"></iframe>',
        large: true,
        removeOnClose: true,
    });

    return new Promise((resolve) => {
        let saved = false;

        const messageHandler = (event) => {
            if (event.origin !== window.location.origin) {
                return;
            }
            if (!event.data || typeof event.data !== 'object') {
                return;
            }
            if (event.data.type === 'extension_saved') {
                saved = true;
                modal.destroy();
            } else if (event.data.type === 'extension_cancelled') {
                modal.destroy();
            }
        };

        window.addEventListener('message', messageHandler);

        // Clean up listener when modal is destroyed.
        modal.getRoot().on('modal:hidden', () => {
            window.removeEventListener('message', messageHandler);
            resolve(saved);
        });

        modal.show();
    });
};
