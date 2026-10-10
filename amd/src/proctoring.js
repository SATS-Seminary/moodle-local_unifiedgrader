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
 * Proctoring in the grader: a review-priority marker on each student in the
 * list, and a card in the marking panel with the findings behind it. The
 * results come from whichever service proctors the quiz (SMOWL, Quilgo,
 * ProctorLink or AutoProctor); this module shows them all the same way.
 *
 * Loaded after the page has drawn, and kept out of the grader's reactive
 * state, so a slow or unreachable service never holds up marking.
 *
 * @module     local_unifiedgrader/proctoring
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {get_strings as getStrings} from 'core/str';

const SELECTORS = {
    CONTAINER: '.local-unifiedgrader-container',
    LIST: '[data-region="participant-list"]',
    SECTION: '[data-region="proctoring-section"]',
    BODY: '[data-region="proctoring-body"]',
    UPDATED: '[data-region="proctoring-updated"]',
    REFRESH: '[data-action="proctoring-refresh"]',
    DOT: '[data-region="proctoring-dot"]',
};

const STRING_KEYS = [
    'proctoring_attempt_other',
    'proctoring_issue',
    'proctoring_norecord',
    'proctoring_notconfigured',
    'proctoring_notconfigured_desc',
    'proctoring_openreport',
    'proctoring_provisional',
    'proctoring_unavailable',
];

/** @type {?object} The last answer from the web service. */
let data = null;
/** @type {Map<number, object>} Each student's result, by user ID. */
let users = new Map();
/** @type {number} The student open in the grader. */
let currentUserId = 0;
/** @type {number} The attempt open in the grader. */
let currentAttempt = 0;
/** @type {object} Language strings, by key. */
let strings = {};

/**
 * A language string with the proctoring service's name in it.
 *
 * @param {string} key
 * @return {string}
 */
const named = (key) => strings[key].replaceAll('{$a}', data?.provider ?? '');

/**
 * Build a priority marker.
 *
 * @param {object} user A student's result.
 * @return {HTMLElement}
 */
const buildDot = (user) => {
    const dot = document.createElement('span');
    dot.className = 'ug-proctor-dot';
    dot.dataset.level = user.level;
    dot.dataset.provisional = user.provisional ? '1' : '0';
    const label = user.provisional ? `${user.levellabel}. ${strings.proctoring_provisional}` : user.levellabel;
    dot.title = label;
    dot.setAttribute('role', 'img');
    dot.setAttribute('aria-label', label);
    return dot;
};

/**
 * Put each student's marker on their row of the participant list.
 *
 * The navigator rebuilds the rows whenever the list changes, so this runs
 * again each time it does.
 */
const decorateList = () => {
    const list = document.querySelector(SELECTORS.LIST);
    if (!list) {
        return;
    }
    list.querySelectorAll(SELECTORS.DOT).forEach((dot) => dot.remove());
    if (!data || data.state !== 'ok') {
        return;
    }
    list.querySelectorAll('[data-userid]').forEach((row) => {
        const user = users.get(Number(row.dataset.userid));
        const wrapper = row.lastElementChild;
        if (!user || !wrapper) {
            return;
        }
        const dot = buildDot(user);
        dot.dataset.region = 'proctoring-dot';
        const url = user.reporturl || data.reporturl;
        if (url) {
            // The row itself selects the student; the marker opens the report.
            dot.dataset.link = '1';
            dot.addEventListener('click', (e) => {
                e.stopPropagation();
                window.open(url, '_blank', 'noopener');
            });
        }
        wrapper.prepend(dot);
    });
};

/**
 * A paragraph of muted text.
 *
 * @param {string} text
 * @return {HTMLElement}
 */
const note = (text) => {
    const p = document.createElement('p');
    p.className = 'small text-muted mb-0';
    p.textContent = text;
    return p;
};

/**
 * A link to the evidence, opened in a new tab.
 *
 * @param {string} url
 * @param {string} className
 * @return {HTMLAnchorElement}
 */
const reportLink = (url, className) => {
    const link = document.createElement('a');
    link.href = url;
    link.target = '_blank';
    link.rel = 'noopener';
    link.className = className;
    return link;
};

/**
 * The result to show for the open student: the open attempt's, where the
 * service keeps one per attempt, and otherwise the student's own.
 *
 * @param {object} user The student's result.
 * @return {{result: object, other: number}} other is the attempt shown in
 *         place of the open one, or 0.
 */
const pickResult = (user) => {
    if (!user.attempts.length) {
        return {result: user, other: 0};
    }
    const open = user.attempts.find((attempt) => attempt.attempt === currentAttempt);
    if (open) {
        return {result: open, other: 0};
    }
    // The student's own result is their worst attempt's: say which that is.
    const worst = user.attempts.find((attempt) => attempt.level === user.level);
    return {result: user, other: worst ? worst.attempt : 0};
};

/**
 * Draw a priority and the findings behind it.
 *
 * @param {HTMLElement} body The card body.
 * @param {object} user The result: a student's, or one attempt's.
 * @return {string} Where this result's evidence is, or an empty string.
 */
const renderUser = (body, user) => {
    const url = user.reporturl || data.reporturl;
    const level = url ? reportLink(url, 'ug-proctor-level') : document.createElement('span');
    level.className = 'ug-proctor-level';
    level.dataset.region = 'proctoring-level';
    level.append(buildDot(user), document.createTextNode(user.levellabel));
    body.append(level);

    if (user.provisional) {
        body.append(note(strings.proctoring_provisional));
    }

    const tools = document.createElement('ul');
    tools.className = 'ug-proctor-tools';
    user.tools.forEach((tool) => {
        const item = document.createElement('li');
        const name = document.createElement('strong');
        name.textContent = tool.label;
        item.append(name, document.createTextNode(`: ${tool.statuslabel}`));

        if (tool.issues.length) {
            const issues = document.createElement('ul');
            tool.issues.forEach((issue) => {
                const line = document.createElement('li');
                // A plain detail (a percentage, say) carries no colour of its own.
                if (issue.level !== 'info') {
                    const dot = document.createElement('span');
                    dot.className = 'ug-proctor-dot';
                    dot.dataset.level = issue.level;
                    dot.setAttribute('aria-hidden', 'true');
                    line.append(dot);
                }
                const text = issue.count > 0
                    ? strings.proctoring_issue.replace('{$a->label}', issue.label).replace('{$a->count}', issue.count)
                    : issue.label;
                line.append(document.createTextNode(text));
                issues.append(line);
            });
            item.append(issues);
        }
        tools.append(item);
    });
    body.append(tools);
    return url;
};

/**
 * Draw the marking-panel card for the open student.
 */
const renderCard = () => {
    const section = document.querySelector(SELECTORS.SECTION);
    const body = section?.querySelector(SELECTORS.BODY);
    if (!section || !body) {
        return;
    }
    if (!data || data.state === 'none') {
        section.classList.add('d-none');
        return;
    }

    body.innerHTML = '';
    body.dataset.state = data.state;
    let url = data.reporturl;

    if (data.state === 'notconfigured') {
        const title = document.createElement('p');
        title.className = 'fw-bold mb-1';
        title.textContent = named('proctoring_notconfigured');
        body.append(title, note(named('proctoring_notconfigured_desc')));
    } else if (data.state === 'unavailable') {
        body.append(note(named('proctoring_unavailable')));
    } else {
        const user = users.get(currentUserId);
        if (user) {
            const {result, other} = pickResult(user);
            if (other) {
                body.append(note(strings.proctoring_attempt_other.replace('{$a}', other)));
            }
            url = renderUser(body, result);
        } else {
            body.append(note(named('proctoring_norecord')));
        }
    }

    if (url) {
        const link = reportLink(url, 'btn btn-sm btn-outline-secondary w-100 mt-2');
        link.textContent = named('proctoring_openreport');
        body.append(link);
    }

    const updated = section.querySelector(SELECTORS.UPDATED);
    if (updated) {
        updated.textContent = `${data.provider} · ${data.updated}`;
    }
    section.classList.remove('d-none');
};

/**
 * Ask the server for the quiz's results and redraw.
 *
 * A failure of the request itself is shown the same way as the service being
 * unreachable: marking carries on either way.
 *
 * @param {number} cmid
 * @param {boolean} refresh Ask the service again instead of using the cached answer.
 */
const load = async(cmid, refresh) => {
    const button = document.querySelector(SELECTORS.REFRESH);
    if (button) {
        button.disabled = true;
    }
    try {
        data = await Ajax.call([{
            methodname: 'local_unifiedgrader_get_proctoring',
            args: {cmid, refresh},
        }])[0];
    } catch (error) {
        // Before the first answer there is nothing to keep, and nothing to say.
        data = data ? {...data, state: 'unavailable', users: []} : null;
    }
    users = new Map((data?.users ?? []).map((user) => [user.id, user]));
    decorateList();
    renderCard();
    if (button) {
        button.disabled = false;
    }
};

/**
 * Start proctoring for the quiz open in the grader.
 *
 * @param {number} cmid Course module ID.
 */
export const init = async(cmid) => {
    const container = document.querySelector(SELECTORS.CONTAINER);
    if (!container || container.dataset.proctoringBound) {
        return;
    }
    container.dataset.proctoringBound = '1';
    currentUserId = Number(container.dataset.userid) || 0;

    const values = await getStrings(STRING_KEYS.map((key) => ({key, component: 'local_unifiedgrader'})));
    strings = Object.fromEntries(STRING_KEYS.map((key, i) => [key, values[i]]));

    // Follow the grader to whichever student and attempt are open.
    document.addEventListener('local_unifiedgrader:statechanged', (e) => {
        const id = Number(e.detail?.state?.currentUser?.id) || currentUserId;
        const attempt = Number(e.detail?.state?.submission?.attemptnumber) || 0;
        if (id !== currentUserId || attempt !== currentAttempt) {
            currentUserId = id;
            currentAttempt = attempt;
            renderCard();
        }
    });

    const list = document.querySelector(SELECTORS.LIST);
    if (list) {
        new MutationObserver(decorateList).observe(list, {childList: true});
    }

    document.querySelector(SELECTORS.REFRESH)?.addEventListener('click', () => load(cmid, true));

    await load(cmid, false);
};
