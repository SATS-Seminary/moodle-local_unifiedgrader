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
 * The student feedback walk. One step, a timer, then a tick.
 *
 * @module     local_unifiedgrader/friction_walk
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';

const RING = 2 * Math.PI * 28;

/**
 * Start the walk on the feedback page.
 */
export const init = () => {
    const region = document.querySelector('[data-region="friction-walk"]');
    if (!region || region.dataset.bound) {
        return;
    }
    region.dataset.bound = '1';

    const payload = region.querySelector('[data-region="friction-steps"]');
    const steps = payload ? JSON.parse(payload.textContent || '[]') : [];
    let index = Number(region.dataset.step) || 0;
    const seconds = Math.max(0, Number(region.dataset.seconds) || 0);
    const tickBtn = region.querySelector('[data-action="friction-tick"]');
    const ring = region.querySelector('[data-region="friction-ring-progress"]');
    const done = region.querySelector('[data-region="friction-done"]');

    let remaining = 0;
    let last = 0;
    let running = false;
    let frame = 0;

    if (ring) {
        ring.style.strokeDasharray = String(RING);
    }

    /**
     * Draw the ring. 1 is full, 0 is finished.
     *
     * @param {number} fraction
     */
    const paint = (fraction) => {
        if (!ring) {
            return;
        }
        const clamped = Math.min(1, Math.max(0, fraction));
        ring.style.strokeDashoffset = String(RING * (1 - clamped));
    };

    /**
     * Show the tick and the check in the ring.
     */
    const revealTick = () => {
        paint(0);
        done?.classList.remove('d-none');
        tickBtn?.classList.remove('d-none');
    };

    /**
     * Restart the wait for the current step.
     */
    const startTimer = () => {
        cancelAnimationFrame(frame);
        remaining = seconds * 1000;
        last = 0;
        running = seconds > 0;
        tickBtn?.classList.add('d-none');
        done?.classList.add('d-none');
        if (tickBtn) {
            tickBtn.disabled = false;
        }
        if (seconds <= 0) {
            revealTick();
            return;
        }
        paint(1);
        frame = requestAnimationFrame(loop);
    };

    /**
     * Count down while the tab is visible.
     *
     * @param {number} now
     */
    const loop = (now) => {
        if (!running) {
            return;
        }
        if (document.hidden) {
            last = 0;
            frame = requestAnimationFrame(loop);
            return;
        }
        if (!last) {
            last = now;
        }
        remaining -= now - last;
        last = now;
        const budget = seconds * 1000;
        if (remaining <= 0) {
            remaining = 0;
            running = false;
            revealTick();
            return;
        }
        paint(budget > 0 ? remaining / budget : 0);
        frame = requestAnimationFrame(loop);
    };

    /**
     * Render the step the server asked us to resume on.
     */
    const showStep = async() => {
        const step = steps[index];
        if (!step) {
            return;
        }
        const kicker = region.querySelector('[data-region="friction-kicker"]');
        if (kicker) {
            kicker.textContent = await getString('friction_progress', 'local_unifiedgrader', {
                n: index + 1,
                total: steps.length,
            });
        }
        const title = region.querySelector('[data-region="friction-title"]');
        if (title) {
            title.textContent = step.title || '';
        }
        const body = region.querySelector('[data-region="friction-body"]');
        if (body) {
            body.innerHTML = step.body || '';
        }
        const levels = region.querySelector('[data-region="friction-levels"]');
        if (levels) {
            levels.innerHTML = '';
            (step.levels || []).forEach((level) => {
                const item = document.createElement('li');
                item.className = 'list-group-item d-flex align-items-start gap-2';
                if (level.selected) {
                    item.classList.add('list-group-item-primary');
                    const icon = document.createElement('i');
                    icon.className = 'fa fa-check text-primary mt-1';
                    icon.setAttribute('aria-hidden', 'true');
                    item.appendChild(icon);
                } else {
                    item.classList.add('text-muted');
                }
                const text = document.createElement('div');
                text.innerHTML = level.definition || '';
                item.appendChild(text);
                levels.appendChild(item);
            });
            levels.classList.toggle('d-none', !(step.levels || []).length);
        }
        const remark = region.querySelector('[data-region="friction-remark"]');
        if (remark) {
            if (step.remark) {
                remark.innerHTML = step.remark;
                remark.classList.remove('d-none');
            } else {
                remark.innerHTML = '';
                remark.classList.add('d-none');
            }
        }
        startTimer();
    };

    tickBtn?.addEventListener('click', async() => {
        tickBtn.disabled = true;
        try {
            const result = await Ajax.call([{
                methodname: 'local_unifiedgrader_advance_friction',
                args: {cmid: Number(region.dataset.cmid)},
            }])[0];
            if (result.finished) {
                window.location.reload();
                return;
            }
            index = Number(result.step) || (index + 1);
            tickBtn.disabled = false;
            showStep();
        } catch (error) {
            tickBtn.disabled = false;
            Notification.exception(error);
        }
    });

    showStep();
};
