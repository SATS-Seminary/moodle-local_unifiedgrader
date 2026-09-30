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
 * The "Dates & extensions" dialogue: one student's extension and overrides.
 *
 * Every date setting is listed with the class default beside the student's own
 * value, so defaults are always in view and an override is made in its row.
 * The due date changes through an extension (presets or a picked date), and
 * each change of extension asks the server what it means for the student's
 * lateness and late penalty before anything is saved.
 *
 * Dates travel as local date-times ("2026-08-17T23:59") in the teacher's Moodle
 * time zone; the server converts them, so the browser's own time zone never
 * matters.
 *
 * @module     local_unifiedgrader/overrides_extensions_modal
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Modal from 'core/modal';
import Notification from 'core/notification';
import Templates from 'core/templates';
import {getString} from 'core/str';

/** @var {number} Highest number of attempts offered in the attempts list. */
const MAX_ATTEMPTS = 10;

/**
 * Add days to a local date-time, keeping the wall-clock time.
 *
 * The string is read as if it were UTC purely to do calendar arithmetic, so
 * daylight saving never shifts the time of day.
 *
 * @param {string} local "YYYY-MM-DDTHH:MM"
 * @param {number} days Days to add.
 * @return {string}
 */
const addDays = (local, days) => {
    const [date, time] = local.split('T');
    const [y, m, d] = date.split('-').map(Number);
    const shifted = new Date(Date.UTC(y, m - 1, d + days));
    const pad = (n) => String(n).padStart(2, '0');
    return shifted.getUTCFullYear() + '-' + pad(shifted.getUTCMonth() + 1) + '-' + pad(shifted.getUTCDate()) + 'T' + time;
};

/**
 * The browser's current local date-time, a starting point for an empty date.
 *
 * @return {string} "YYYY-MM-DDTHH:MM"
 */
const nowLocal = () => {
    const now = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate())
        + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
};

/**
 * One open dialogue.
 */
class DatesDialogue {
    /**
     * @param {object} data From local_unifiedgrader_get_student_dates.
     * @param {Modal} modal The modal it lives in.
     */
    constructor(data, modal) {
        this.data = data;
        this.modal = modal;
        this.saved = false;
        this.askrecalc = false;
        this.previewseq = 0;

        this.ext = data.extension.value;
        this.overrides = {};
        data.settings.forEach((row) => {
            if (!row.isextension && row.overridden) {
                this.overrides[row.key] = row.value;
            }
        });
        this.preview = data.preview;
        this.initial = this.snapshot();
    }

    /**
     * The saved-or-not state, for counting changes.
     *
     * @return {object}
     */
    snapshot() {
        return {ext: this.ext, overrides: Object.assign({}, this.overrides)};
    }

    /**
     * How many settings differ from what was loaded.
     *
     * @return {number}
     */
    changeCount() {
        let count = this.ext !== this.initial.ext ? 1 : 0;
        const keys = new Set([...Object.keys(this.overrides), ...Object.keys(this.initial.overrides)]);
        keys.forEach((key) => {
            if (this.overrides[key] !== this.initial.overrides[key]) {
                count++;
            }
        });
        return count;
    }

    /**
     * The body template's context.
     *
     * @return {object}
     */
    bodyContext() {
        const d = this.data;
        const presets = d.extension.presets.map((p) => {
            const target = d.extension.classvalue ? addDays(d.extension.classvalue, p.days) : '';
            return {days: p.days, label: p.label, pressed: target !== '' && target === this.ext};
        });
        const moves = {};
        (this.preview.moves || []).forEach((m) => {
            moves[m.key] = m.text;
        });

        const rows = d.settings.map((row) => {
            const r = {
                key: row.key, label: row.label, classtext: row.classtext, note: row.note,
                inputid: 'ug-dates-' + row.key,
                changed: false, replaced: false, showtext: false, displayvalue: '', editing: false,
                isdatetime: row.type === 'datetime', isduration: row.type === 'duration', isattempts: row.type === 'attempts',
                inputvalue: '', attemptoptions: [],
                badgeextension: false, badgefollows: false, badgeoverride: false,
                same: false, canreset: false, canchange: false,
            };
            if (row.isextension) {
                if (this.ext) {
                    Object.assign(r, {changed: true, replaced: true, showtext: true, badgeextension: true,
                        displayvalue: this.preview.extensiontext || this.ext, canreset: d.extension.editable});
                } else {
                    r.same = d.extension.available;
                }
                return r;
            }
            if (Object.prototype.hasOwnProperty.call(this.overrides, row.key)) {
                Object.assign(r, {changed: true, replaced: true, editing: row.editable, badgeoverride: true,
                    canreset: row.editable, inputvalue: this.overrides[row.key]});
                if (!row.editable) {
                    Object.assign(r, {showtext: true, displayvalue: row.valuetext});
                }
                if (r.isattempts) {
                    r.attemptoptions = this.attemptOptions(this.overrides[row.key]);
                }
                if (row.follows && moves[row.key]) {
                    r.note = moves[row.key];
                }
                return r;
            }
            if (row.follows && moves[row.key]) {
                Object.assign(r, {changed: true, replaced: true, showtext: true, badgefollows: true,
                    displayvalue: moves[row.key]});
                return r;
            }
            r.same = !row.note;
            r.canchange = row.editable;
            return r;
        });

        const tone = this.preview.tone;
        return {
            fullname: d.fullname,
            profileimageurl: d.profileimageurl,
            activityname: d.activityname,
            status: {show: tone !== 'none' && this.preview.title !== '', tone: tone,
                title: this.preview.title, body: this.preview.body},
            extension: Object.assign({}, d.extension, {presets: presets, pickvalue: this.ext}),
            rows: rows,
        };
    }

    /**
     * The options of the attempts list.
     *
     * @param {string} selected The chosen value.
     * @return {object[]}
     */
    attemptOptions(selected) {
        const options = [{value: '0', label: this.strings.unlimited, selected: selected === '0'}];
        for (let i = 1; i <= MAX_ATTEMPTS; i++) {
            options.push({value: String(i), label: String(i), selected: selected === String(i)});
        }
        return options;
    }

    /**
     * Load the strings the dialogue builds in code.
     */
    async loadStrings() {
        const [unlimited, none, one, many] = await Promise.all([
            getString('unlimited'),
            getString('dates_changes_none', 'local_unifiedgrader'),
            getString('dates_changes_one', 'local_unifiedgrader', this.data.fullname),
            getString('dates_changes_many', 'local_unifiedgrader', {count: '{count}', student: this.data.fullname}),
        ]);
        this.strings = {unlimited, none, one, many};
    }

    /**
     * Draw the body again, keeping focus on the control that had it.
     */
    async renderBody() {
        const active = document.activeElement;
        const focusKey = active && active.dataset ? (active.dataset.action || '') + ':' + (active.dataset.key || '') : '';
        const {html, js} = await Templates.renderForPromise('local_unifiedgrader/dates_dialog_body', this.bodyContext());
        const body = this.modal.getBody()[0];
        Templates.replaceNodeContents(body, html, js);
        if (focusKey !== ':') {
            const [action, key] = focusKey.split(':');
            const selector = '[data-action="' + action + '"]' + (key ? '[data-key="' + key + '"]' : '');
            const target = body.querySelector(selector);
            if (target) {
                target.focus();
            }
        }
        this.renderFooter();
    }

    /**
     * Update the footer's summary and buttons without redrawing it.
     */
    renderFooter() {
        const footer = this.modal.getFooter()[0];
        const count = this.changeCount();
        const summary = footer.querySelector('[data-region="summary"]');
        if (summary) {
            summary.textContent = count === 0 ? this.strings.none
                : (count === 1 ? this.strings.one : this.strings.many.replace('{count}', String(count)));
        }
        const save = footer.querySelector('[data-action="save"]');
        if (save) {
            save.disabled = count === 0;
        }
        const removeall = footer.querySelector('[data-action="removeall"]');
        if (removeall) {
            removeall.disabled = !this.ext && Object.keys(this.overrides).length === 0;
        }
    }

    /**
     * Change the extension and ask what it means for the student.
     *
     * @param {string} ext Local date-time, or '' for none.
     */
    async setExtension(ext) {
        this.ext = ext;
        const seq = ++this.previewseq;
        try {
            const preview = await Ajax.call([{
                methodname: 'local_unifiedgrader_preview_student_dates',
                args: {cmid: this.data.cmid, userid: this.data.userid, extension: ext},
            }])[0];
            if (seq !== this.previewseq) {
                return; // A later change has already asked.
            }
            this.preview = preview;
        } catch (error) {
            Notification.exception(error);
        }
        await this.renderBody();
    }

    /**
     * Start overriding a setting, from its class default.
     *
     * @param {string} key Setting key.
     */
    startOverride(key) {
        const row = this.data.settings.find((s) => s.key === key);
        let value = row.classvalue;
        if (row.type === 'datetime' && !value) {
            value = nowLocal();
        } else if (row.type === 'duration') {
            value = String(Math.max(1, parseInt(row.classvalue, 10) || 60));
        } else if (row.type === 'attempts') {
            const attempts = parseInt(row.classvalue, 10) || 0;
            value = String(attempts === 0 ? 1 : Math.min(MAX_ATTEMPTS, attempts + 1));
        }
        this.overrides[key] = value;
        this.renderBody();
    }

    /**
     * Handle a click in the dialogue.
     *
     * @param {Event} e
     */
    async handleClick(e) {
        const button = e.target.closest('button[data-action]');
        if (!button || button.disabled) {
            return;
        }
        const {action, key} = button.dataset;
        if (action === 'preset') {
            const target = addDays(this.data.extension.classvalue, parseInt(button.dataset.days, 10));
            await this.setExtension(this.ext === target ? '' : target);
        } else if (action === 'change') {
            this.startOverride(key);
        } else if (action === 'reset') {
            if (key === 'duedate') {
                await this.setExtension('');
            } else {
                delete this.overrides[key];
                await this.renderBody();
            }
        } else if (action === 'removeall') {
            this.overrides = {};
            await this.setExtension('');
        } else if (action === 'cancel') {
            this.modal.destroy();
        } else if (action === 'save') {
            await this.save(button);
        }
    }

    /**
     * Handle an edited value.
     *
     * @param {Event} e
     */
    async handleChange(e) {
        const input = e.target.closest('[data-action]');
        if (!input) {
            return;
        }
        if (input.dataset.action === 'pick') {
            await this.setExtension(input.value);
        } else if (input.dataset.action === 'edit' && input.value !== '') {
            this.overrides[input.dataset.key] = input.value;
            this.renderFooter();
        }
    }

    /**
     * Save, then close.
     *
     * @param {HTMLButtonElement} button The Save button.
     */
    async save(button) {
        button.disabled = true;
        try {
            const result = await Ajax.call([{
                methodname: 'local_unifiedgrader_save_student_dates',
                args: {
                    cmid: this.data.cmid,
                    userid: this.data.userid,
                    extension: this.ext,
                    overrides: Object.entries(this.overrides).map(([key, value]) => ({key, value})),
                },
            }])[0];
            this.saved = true;
            this.askrecalc = result.askrecalc;
            this.modal.destroy();
        } catch (error) {
            button.disabled = false;
            Notification.exception(error);
        }
    }
}

/**
 * Open the "Dates & extensions" dialogue for a student.
 *
 * @param {number} cmid Course module ID.
 * @param {number} userid Student user ID.
 * @return {Promise<boolean>} Resolves when the dialogue closes: true if it saved.
 */
export const openOverridesExtensionsModal = async(cmid, userid) => {
    let data;
    try {
        data = await Ajax.call([{
            methodname: 'local_unifiedgrader_get_student_dates',
            args: {cmid, userid},
        }])[0];
    } catch (error) {
        Notification.exception(error);
        return false;
    }

    const footer = await Templates.render('local_unifiedgrader/dates_dialog_footer', {
        canremoveall: data.canremoveall, hasany: true, summary: '', changes: false,
    });
    const modal = await Modal.create({
        title: getString('dates_title', 'local_unifiedgrader'),
        body: '',
        footer: footer,
        large: true,
        removeOnClose: true,
    });
    // The body scrolls and the footer, with Save, stays in view.
    modal.getRoot()[0].querySelector('.modal-dialog')?.classList.add('modal-dialog-scrollable');

    const dialogue = new DatesDialogue(data, modal);
    await dialogue.loadStrings();

    const root = modal.getRoot()[0];
    root.addEventListener('click', (e) => dialogue.handleClick(e));
    root.addEventListener('change', (e) => dialogue.handleChange(e));
    root.addEventListener('input', (e) => {
        if (e.target.dataset && e.target.dataset.action === 'edit') {
            dialogue.handleChange(e);
        }
    });

    return new Promise((resolve) => {
        modal.getRoot().on('modal:hidden', () => {
            resolve(dialogue.saved);
            if (dialogue.askrecalc) {
                askRecalculatePenalty(cmid, userid);
            }
        });
        modal.show();
        dialogue.renderBody();
    });
};

/**
 * Offer to recalculate the late penalty after an extension, where core still owns it.
 *
 * Only for assignments core still penalises (Moodle before 5.3, or core's
 * assignment penalties left on). On 5.3 Unified Grader recalculates as it saves.
 *
 * @param {number} cmid Course module ID.
 * @param {number} userid Student user ID.
 */
const askRecalculatePenalty = async(cmid, userid) => {
    const message = await getString('recalculate_penalty_confirm', 'local_unifiedgrader');
    const title = await getString('recalculatepenalty', 'local_unifiedgrader');

    Notification.confirm(
        title,
        message,
        await getString('yes'),
        await getString('no'),
        async() => {
            try {
                const url = M.cfg.wwwroot + '/local/unifiedgrader/recalculate_penalty.php'
                    + '?cmid=' + cmid + '&userid=' + userid + '&sesskey=' + M.cfg.sesskey;
                const resp = await fetch(url, {method: 'POST'});
                if (resp.ok) {
                    // Reload the current student so the grader shows the new penalty.
                    document.dispatchEvent(new CustomEvent(
                        'unifiedgrader:penaltyrecalculated',
                        {detail: {cmid, userid}},
                    ));
                }
            } catch (err) {
                window.console.warn('[dates dialogue] Penalty recalculation failed:', err);
            }
        },
    );
};
