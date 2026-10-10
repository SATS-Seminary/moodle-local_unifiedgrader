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
 * Small conveniences for the help page (help.php).
 *
 * The page works without this module: the navigation is plain anchors and each
 * screenshot links to its image. With it, the navigation marks the section in
 * view, a screenshot opens enlarged in a dialogue, a link into a collapsed
 * topic opens that topic, and printing opens every topic.
 *
 * @module     local_unifiedgrader/help
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @type {string} Class on the link of the part (Teachers, Students, …) in view. */
const ACTIVE_PART = 'local-unifiedgrader-help-nav-active';

/** @type {number} How far below the top of the window a heading counts as reached, in pixels. */
const REACHED = 150;

/**
 * Mark the navigation link of the section in view.
 *
 * Listens on the document in the capture phase, so it works whether the theme
 * scrolls the window or an inner element.
 *
 * @param {HTMLElement} root The help page container.
 */
const setupNavigation = (root) => {
    const nav = root.querySelector('[data-region="help-nav"]');
    if (!nav) {
        return;
    }
    const entries = [];
    nav.querySelectorAll('a[href^="#"]').forEach((link) => {
        const target = document.getElementById(link.getAttribute('href').slice(1));
        if (target) {
            entries.push({link, target});
        }
    });
    if (!entries.length) {
        return;
    }

    let current = null;
    const update = () => {
        // The last section whose top has passed the line is the one being read.
        let found = entries[0];
        entries.forEach((entry) => {
            if (entry.target.getBoundingClientRect().top <= REACHED) {
                found = entry;
            }
        });
        if (found === current) {
            return;
        }
        current = found;
        entries.forEach((entry) => entry.link.removeAttribute('aria-current'));
        nav.querySelectorAll('.' + ACTIVE_PART).forEach((link) => link.classList.remove(ACTIVE_PART));
        nav.querySelectorAll('li[data-active]').forEach((item) => item.removeAttribute('data-active'));
        found.link.setAttribute('aria-current', 'true');

        // A link in a sub-list also marks the part it belongs to.
        const sublist = found.link.closest('ul ul');
        const partitem = sublist ? sublist.parentElement : found.link.parentElement;
        partitem.setAttribute('data-active', 'true');
        const partlink = partitem.querySelector(':scope > a');
        if (partlink) {
            partlink.classList.add(ACTIVE_PART);
        }

        // Keep the marked link in sight inside the navigation's own scroll area.
        const navbox = nav.getBoundingClientRect();
        const linkbox = found.link.getBoundingClientRect();
        if (linkbox.top < navbox.top || linkbox.bottom > navbox.bottom) {
            nav.scrollTop += linkbox.top - navbox.top - navbox.height / 2;
        }
        if (linkbox.left < navbox.left || linkbox.right > navbox.right) {
            nav.scrollLeft += linkbox.left - navbox.left - navbox.width / 2;
        }
    };

    let queued = false;
    const schedule = () => {
        if (queued) {
            return;
        }
        queued = true;
        window.requestAnimationFrame(() => {
            queued = false;
            update();
        });
    };
    document.addEventListener('scroll', schedule, {capture: true, passive: true});
    window.addEventListener('resize', schedule, {passive: true});
    update();
};

/**
 * Open a screenshot enlarged in a dialogue when it is clicked.
 *
 * Without dialogue support the link simply opens the image.
 *
 * @param {HTMLElement} root The help page container.
 */
const setupZoom = (root) => {
    if (typeof HTMLDialogElement === 'undefined') {
        return;
    }
    let dialog = null;

    const build = () => {
        dialog = document.createElement('dialog');
        dialog.className = 'local-unifiedgrader-help-lightbox';
        dialog.setAttribute('aria-label', 'Screenshot');

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'btn btn-secondary btn-sm';
        close.textContent = 'Close';
        close.addEventListener('click', () => dialog.close());

        const bar = document.createElement('div');
        bar.className = 'local-unifiedgrader-help-lightbox-bar';
        const caption = document.createElement('p');
        bar.append(caption, close);

        const image = document.createElement('img');
        dialog.append(bar, image);

        // A click on the backdrop lands on the dialogue element itself.
        dialog.addEventListener('click', (e) => {
            if (e.target === dialog) {
                dialog.close();
            }
        });
        document.body.append(dialog);
    };

    root.addEventListener('click', (e) => {
        const link = e.target.closest('a[data-action="help-zoom"]');
        // Leave modified clicks alone, so a new tab can still be asked for.
        if (!link || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) {
            return;
        }
        e.preventDefault();
        if (!dialog) {
            build();
        }
        const thumb = link.querySelector('img');
        const figure = link.closest('figure');
        const caption = figure ? figure.querySelector('figcaption') : null;
        const image = dialog.querySelector('img');
        image.src = link.href;
        image.alt = thumb ? thumb.alt : '';
        dialog.querySelector('p').textContent = caption ? caption.textContent.replace(/\s+/g, ' ').trim() : '';
        dialog.showModal();
    });
};

/**
 * Open collapsed topics when a link points into one, and for printing.
 *
 * @param {HTMLElement} root The help page container.
 */
const setupTopics = (root) => {
    const reveal = () => {
        const id = decodeURIComponent(window.location.hash.slice(1));
        const target = id ? document.getElementById(id) : null;
        if (!target || !root.contains(target)) {
            return;
        }
        let opened = false;
        for (let el = target; el && el !== root; el = el.parentElement) {
            if (el.tagName === 'DETAILS' && !el.open) {
                el.open = true;
                opened = true;
            }
        }
        if (opened) {
            target.scrollIntoView();
        }
    };
    window.addEventListener('hashchange', reveal);
    reveal();

    let reopened = [];
    window.addEventListener('beforeprint', () => {
        reopened = [...root.querySelectorAll('details:not([open])')];
        reopened.forEach((el) => {
            el.open = true;
        });
    });
    window.addEventListener('afterprint', () => {
        reopened.forEach((el) => {
            el.open = false;
        });
        reopened = [];
    });
};

/**
 * Set the help page up.
 */
export const init = () => {
    const root = document.querySelector('[data-region="local-unifiedgrader-help"]');
    if (!root) {
        return;
    }
    setupNavigation(root);
    setupZoom(root);
    setupTopics(root);
};
