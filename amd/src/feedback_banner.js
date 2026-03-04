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
 * Inject a visible "View Annotated Feedback" banner into the assignment page
 * for students whose grade has been released and whose submission has annotations.
 *
 * This module is loaded via js_call_amd from the PSR-14 output hook when a
 * student views a graded assignment. It creates a Bootstrap card and inserts
 * it at the top of the main content area.
 *
 * @module     local_unifiedgrader/feedback_banner
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {get_string as getString} from 'core/str';

/**
 * Initialise the feedback banner.
 *
 * @param {string} url The URL to the feedback viewer page.
 * @param {string} label The button label text.
 * @param {string} [description] Optional banner body text.
 */
export const init = async(url, label, description) => {
    // Don't create duplicates.
    if (document.getElementById('ug-feedback-banner')) {
        return;
    }

    const bannerText = description || await getString('feedback_banner_default', 'local_unifiedgrader');

    // Build the banner using DOM API to avoid innerHTML with parameter data.
    const banner = document.createElement('div');
    banner.id = 'ug-feedback-banner';
    banner.className = 'card border-primary mb-3';

    const body = document.createElement('div');
    body.className = 'card-body d-flex align-items-center justify-content-between py-2 px-3';

    const textSpan = document.createElement('span');
    textSpan.className = 'd-flex align-items-center gap-2';

    const icon = document.createElement('i');
    icon.className = 'fa fa-file-text-o text-primary';
    icon.setAttribute('aria-hidden', 'true');

    const textEl = document.createElement('span');
    textEl.textContent = bannerText;

    textSpan.appendChild(icon);
    textSpan.appendChild(textEl);

    const link = document.createElement('a');
    link.href = url;
    link.className = 'btn btn-primary btn-sm text-nowrap';

    const btnIcon = document.createElement('i');
    btnIcon.className = 'fa fa-eye me-1';
    btnIcon.setAttribute('aria-hidden', 'true');

    link.appendChild(btnIcon);
    link.appendChild(document.createTextNode(label));

    body.appendChild(textSpan);
    body.appendChild(link);
    banner.appendChild(body);

    // Find the best insertion point — look for the activity content area.
    const target = document.getElementById('region-main-box')
        || document.getElementById('region-main')
        || document.querySelector('[role="main"]');

    if (target) {
        target.insertBefore(banner, target.firstChild);
    }
};
