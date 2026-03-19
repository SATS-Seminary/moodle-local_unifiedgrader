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
 * Promote the Unified Grader nav item into a visible button in secondary navigation.
 *
 * Both teacher and student views add a node to the settings navigation tree.
 * This module extracts that node from the standard tab/more-menu flow and
 * re-renders it as a standalone styled button in the secondary nav bar.
 *
 * If the secondary nav bar cannot be found (e.g. theme variation), a fallback
 * banner is injected into the main content region.
 *
 * @module     local_unifiedgrader/nav_promote
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Create a button element for the secondary nav bar.
 *
 * @param {string} navKey The data-key identifier.
 * @param {string} href The button URL.
 * @param {string} text The button label text.
 * @returns {HTMLElement} An <li> element containing the styled button.
 */
const createButton = (navKey, href, text) => {
    const icon = navKey === 'local_unifiedgrader_feedback' ? 'fa-eye' : 'fa-pencil-square-o';

    const btn = document.createElement('a');
    btn.href = href;
    btn.className = 'btn btn-sm btn-outline-primary d-flex align-items-center gap-1 ms-auto text-nowrap';
    btn.setAttribute('role', 'menuitem');
    const iconEl = document.createElement('i');
    iconEl.className = 'fa ' + icon;
    iconEl.setAttribute('aria-hidden', 'true');
    btn.appendChild(iconEl);
    btn.appendChild(document.createTextNode(' ' + text));

    const li = document.createElement('li');
    li.className = 'nav-item d-flex align-items-center ms-auto';
    li.dataset.key = navKey;
    li.dataset.forceintomoremenu = 'false';
    li.appendChild(btn);

    return li;
};

/**
 * Find the secondary navigation menu element on the page.
 * Tries multiple selectors for compatibility across Moodle themes.
 *
 * @returns {?HTMLElement} The navigation list element, or null.
 */
const findSecondaryNav = () => {
    return document.querySelector('.secondary-navigation nav.moremenu .more-nav')
        || document.querySelector('.secondary-navigation .nav')
        || document.querySelector('nav.moremenu .more-nav');
};

/**
 * Extract an existing nav item and re-render it as a promoted button.
 *
 * @param {string} navKey The data-key of the nav item to promote.
 * @returns {boolean} True if promotion succeeded.
 */
const promote = (navKey) => {
    const navItem = document.querySelector('li[data-key="' + navKey + '"]');
    if (!navItem) {
        return false;
    }

    // Get the link element and its href (works whether in tab bar or More dropdown).
    const link = navItem.querySelector('.nav-link, .dropdown-item');
    if (!link) {
        return false;
    }
    const href = link.getAttribute('href');
    const text = link.textContent.trim();

    // Find the secondary nav container.
    const menu = navItem.closest('.more-nav') || navItem.closest('.nav');
    if (!menu) {
        return false;
    }

    // Remove the original nav item from the tab/dropdown flow.
    navItem.remove();

    // If the More dropdown is now empty, hide the More button.
    const moreDropdown = menu.querySelector('[data-region="moredropdown"]');
    const moreButton = menu.querySelector('[data-region="morebutton"]');
    if (moreDropdown && moreButton && moreDropdown.children.length === 0) {
        moreButton.classList.add('d-none');
    }

    // Create and insert the promoted button.
    const li = createButton(navKey, href, text);
    if (moreButton) {
        menu.insertBefore(li, moreButton);
    } else {
        menu.appendChild(li);
    }

    // Recalculate overflow so other tabs adjust to the reduced space.
    window.dispatchEvent(new Event('resize'));
    return true;
};

/**
 * Inject a fallback banner into the main content region.
 * Used when the secondary navigation DOM cannot be found.
 *
 * @param {string} navKey The data-key identifier.
 * @param {string} href The button URL.
 * @param {string} text The button label text.
 */
const injectFallbackBanner = (navKey, href, text) => {
    // Don't duplicate.
    if (document.getElementById('ug-feedback-banner')) {
        return;
    }

    const icon = navKey === 'local_unifiedgrader_feedback' ? 'fa-eye' : 'fa-pencil-square-o';

    const banner = document.createElement('div');
    banner.id = 'ug-feedback-banner';
    banner.className = 'alert alert-info d-flex justify-content-between align-items-center mb-3';
    const spanEl = document.createElement('span');
    const spanIcon = document.createElement('i');
    spanIcon.className = 'fa ' + icon + ' me-2';
    spanIcon.setAttribute('aria-hidden', 'true');
    spanEl.appendChild(spanIcon);
    spanEl.appendChild(document.createTextNode(text));

    const linkEl = document.createElement('a');
    linkEl.href = href;
    linkEl.className = 'btn btn-primary btn-sm';
    linkEl.textContent = text;

    banner.appendChild(spanEl);
    banner.appendChild(linkEl);

    // Insert at the top of the main content region.
    const mainRegion = document.getElementById('region-main')
        || document.querySelector('[role="main"]')
        || document.getElementById('page-content');
    if (mainRegion) {
        mainRegion.insertBefore(banner, mainRegion.firstChild);
    }
};

/**
 * Inject a button directly into the secondary nav bar.
 *
 * @param {string} navKey The data-key identifier.
 * @param {string} url The button URL.
 * @param {string} label The button label text.
 * @returns {boolean} True if injection succeeded.
 */
const inject = (navKey, url, label) => {
    const menu = findSecondaryNav();
    if (!menu) {
        return false;
    }

    const li = createButton(navKey, url, label);
    const moreButton = menu.querySelector('[data-region="morebutton"]');
    if (moreButton) {
        menu.insertBefore(li, moreButton);
    } else {
        menu.appendChild(li);
    }

    window.dispatchEvent(new Event('resize'));
    return true;
};

/**
 * Initialise the nav promotion.
 *
 * @param {string} [key] The data-key of the nav item to promote.
 * @param {string} [fallbackUrl] URL for direct injection when no nav item exists.
 * @param {string} [fallbackLabel] Label for the injected button.
 */
export const init = (key, fallbackUrl, fallbackLabel) => {
    const navKey = key || 'local_unifiedgrader_grade';

    // Skip on format_simple — its cognav.js extracts secondary nav items into
    // a cog popover. If we promote (remove + recreate) the item first, cognav
    // can't find it because our replacement uses .btn instead of .nav-link.
    if (document.body.classList.contains('format-simple')) {
        return;
    }

    // Don't create duplicate buttons.
    if (document.querySelector('li[data-key="' + navKey + '"][data-forceintomoremenu]')) {
        const existing = document.querySelector('li[data-key="' + navKey + '"][data-forceintomoremenu] .btn');
        if (existing) {
            return;
        }
    }

    // Defer to ensure the core moremenu module has finished its initial setup.
    setTimeout(() => {
        // Try to promote an existing nav item first.
        const navItem = document.querySelector('li[data-key="' + navKey + '"]');
        if (navItem) {
            if (promote(navKey)) {
                return;
            }
        }

        // Fallback: inject directly into the secondary nav if URL provided.
        if (fallbackUrl && fallbackLabel) {
            if (inject(navKey, fallbackUrl, fallbackLabel)) {
                return;
            }
            // Last resort: inject a banner into the content area.
            injectFallbackBanner(navKey, fallbackUrl, fallbackLabel);
        }
    }, 0);
};
