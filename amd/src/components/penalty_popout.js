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
 * Penalty popout — a floating panel for applying grade penalties.
 *
 * Standalone class (not a BaseComponent) that manages its own DOM.
 * Instantiated by marking_panel.js.
 *
 * @module     local_unifiedgrader/components/penalty_popout
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {get_string as getString} from 'core/str';

const PRESETS = [5, 10, 15, 20];

export default class PenaltyPopout {

    /**
     * @param {Function} onSave Called with (category, label, percentage) when a penalty is applied.
     * @param {Function} onDelete Called with (penaltyId) when a penalty is removed.
     */
    constructor(onSave, onDelete) {
        this._onSave = onSave;
        this._onDelete = onDelete;
        this._el = null;
        this._visible = false;
        this._penalties = [];
        this._outsideClickHandler = null;
    }

    /**
     * Toggle the popout visibility.
     *
     * @param {HTMLElement} anchor The toggle button.
     * @param {Array} penalties Current active penalties.
     */
    toggle(anchor, penalties) {
        if (this._visible) {
            this.hide();
        } else {
            this.show(anchor, penalties);
        }
    }

    /**
     * Show the popout positioned relative to the anchor.
     *
     * @param {HTMLElement} anchor The toggle button.
     * @param {Array} penalties Current active penalties.
     */
    show(anchor, penalties) {
        this._penalties = penalties || [];

        if (!this._el) {
            this._el = this._buildDOM();
            document.body.appendChild(this._el);
        }

        this._updateActiveList();
        this._highlightActivePresets();

        // Position using fixed coordinates, clamped to viewport.
        const rect = anchor.getBoundingClientRect();
        const POPOUT_WIDTH = 340;
        const POPOUT_MAX_HEIGHT = 400;
        const MARGIN = 8;
        const vw = window.innerWidth;
        const vh = window.innerHeight;

        // Horizontal: left-align to anchor, clamp to viewport.
        let left = rect.left;
        if (left + POPOUT_WIDTH > vw - MARGIN) {
            left = vw - POPOUT_WIDTH - MARGIN;
        }
        left = Math.max(MARGIN, left);

        // Vertical: prefer below anchor, flip above if no room.
        let top = rect.bottom + 6;
        if (top + POPOUT_MAX_HEIGHT > vh - MARGIN) {
            const above = rect.top - 6 - POPOUT_MAX_HEIGHT;
            if (above >= MARGIN) {
                top = above;
            } else {
                top = Math.max(MARGIN, vh - POPOUT_MAX_HEIGHT - MARGIN);
            }
        }

        this._el.style.top = top + 'px';
        this._el.style.left = left + 'px';
        this._el.classList.remove('d-none');
        this._visible = true;

        // Outside-click dismissal (deferred to avoid catching the opening click).
        setTimeout(() => {
            this._outsideClickHandler = (e) => {
                if (!this._el.contains(e.target)) {
                    this.hide();
                }
            };
            document.addEventListener('click', this._outsideClickHandler, true);
        }, 0);
    }

    /**
     * Hide the popout.
     */
    hide() {
        if (this._el) {
            this._el.classList.add('d-none');
        }
        this._visible = false;
        if (this._outsideClickHandler) {
            document.removeEventListener('click', this._outsideClickHandler, true);
            this._outsideClickHandler = null;
        }
    }

    /**
     * Update the penalties list and re-render active section.
     *
     * @param {Array} penalties Updated penalties.
     */
    updatePenalties(penalties) {
        this._penalties = penalties || [];
        if (this._el) {
            this._updateActiveList();
            this._highlightActivePresets();
        }
    }

    /**
     * Build the popout DOM structure.
     *
     * @return {HTMLElement} The root element.
     */
    _buildDOM() {
        const root = document.createElement('div');
        root.className = 'local-unifiedgrader-penalty-popout d-none';

        // Header.
        const header = document.createElement('div');
        header.className = 'd-flex justify-content-between align-items-center mb-2';
        const title = document.createElement('span');
        title.className = 'fw-bold small';
        title.textContent = 'Penalties';
        getString('penalties', 'local_unifiedgrader').then((s) => {
            title.textContent = s;
        });
        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'btn-close btn-close-sm';
        closeBtn.setAttribute('aria-label', 'Close');
        closeBtn.addEventListener('click', () => this.hide());
        header.appendChild(title);
        header.appendChild(closeBtn);
        root.appendChild(header);

        // Divider.
        root.appendChild(this._createDivider());

        // Word count section.
        const wcSection = this._buildCategorySection('wordcount', 'Word count', 'penalty_wordcount');
        root.appendChild(wcSection);

        // Other section.
        const otherSection = this._buildOtherSection();
        root.appendChild(otherSection);

        // Active penalties section.
        const activeSection = document.createElement('div');
        activeSection.className = 'penalty-section';
        const activeTitle = document.createElement('div');
        activeTitle.className = 'small fw-bold text-muted mb-1';
        activeTitle.textContent = 'Active penalties';
        getString('penalty_active', 'local_unifiedgrader').then((s) => {
            activeTitle.textContent = s;
        });
        activeSection.appendChild(activeTitle);
        this._activeListEl = document.createElement('div');
        this._activeListEl.className = 'd-flex flex-wrap gap-1';
        activeSection.appendChild(this._activeListEl);
        root.appendChild(activeSection);

        return root;
    }

    /**
     * Build a preset-buttons section for a penalty category.
     *
     * @param {string} category 'wordcount' or 'other'.
     * @param {string} defaultLabel Fallback label.
     * @param {string} stringKey Lang string key.
     * @return {HTMLElement}
     */
    _buildCategorySection(category, defaultLabel, stringKey) {
        const section = document.createElement('div');
        section.className = 'penalty-section';

        const label = document.createElement('div');
        label.className = 'small fw-bold mb-1';
        label.textContent = defaultLabel;
        getString(stringKey, 'local_unifiedgrader').then((s) => {
            label.textContent = s;
        });
        section.appendChild(label);

        const btnRow = document.createElement('div');
        btnRow.className = 'd-flex flex-wrap gap-1';
        btnRow.dataset.category = category;

        PRESETS.forEach((pct) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-outline-secondary penalty-preset-btn';
            btn.textContent = pct + '%';
            btn.dataset.percentage = pct;
            btn.addEventListener('click', () => this._handlePresetClick(category, '', pct));
            btnRow.appendChild(btn);
        });

        // Custom button.
        const customBtn = document.createElement('button');
        customBtn.type = 'button';
        customBtn.className = 'btn btn-outline-secondary penalty-preset-btn';
        customBtn.textContent = 'Custom';
        getString('penalty_custom', 'local_unifiedgrader').then((s) => {
            customBtn.textContent = s;
        });
        customBtn.addEventListener('click', () => this._showCustomInput(btnRow, category, ''));
        btnRow.appendChild(customBtn);

        section.appendChild(btnRow);
        return section;
    }

    /**
     * Build the "Other" section with a label input.
     *
     * @return {HTMLElement}
     */
    _buildOtherSection() {
        const section = document.createElement('div');
        section.className = 'penalty-section';

        // Label row: "Other" label + text input.
        const labelRow = document.createElement('div');
        labelRow.className = 'd-flex align-items-center gap-2 mb-1';

        const label = document.createElement('span');
        label.className = 'small fw-bold';
        label.textContent = 'Other';
        getString('penalty_other', 'local_unifiedgrader').then((s) => {
            label.textContent = s;
        });
        labelRow.appendChild(label);

        this._otherLabelInput = document.createElement('input');
        this._otherLabelInput.type = 'text';
        this._otherLabelInput.className = 'form-control form-control-sm';
        this._otherLabelInput.maxLength = 15;
        this._otherLabelInput.style.maxWidth = '160px';
        this._otherLabelInput.placeholder = 'Label (max 15 chars)';
        getString('penalty_label_placeholder', 'local_unifiedgrader').then((s) => {
            this._otherLabelInput.placeholder = s;
        });
        labelRow.appendChild(this._otherLabelInput);
        section.appendChild(labelRow);

        // Preset buttons for "other".
        const btnRow = document.createElement('div');
        btnRow.className = 'd-flex flex-wrap gap-1';
        btnRow.dataset.category = 'other';

        PRESETS.forEach((pct) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-outline-secondary penalty-preset-btn';
            btn.textContent = pct + '%';
            btn.dataset.percentage = pct;
            btn.addEventListener('click', () => this._handleOtherPresetClick(pct));
            btnRow.appendChild(btn);
        });

        const customBtn = document.createElement('button');
        customBtn.type = 'button';
        customBtn.className = 'btn btn-outline-secondary penalty-preset-btn';
        customBtn.textContent = 'Custom';
        getString('penalty_custom', 'local_unifiedgrader').then((s) => {
            customBtn.textContent = s;
        });
        customBtn.addEventListener('click', () => this._showCustomInput(btnRow, 'other', null));
        btnRow.appendChild(customBtn);

        section.appendChild(btnRow);
        return section;
    }

    /**
     * Handle clicking a preset button for wordcount.
     *
     * @param {string} category
     * @param {string} label
     * @param {number} percentage
     */
    _handlePresetClick(category, label, percentage) {
        this._onSave(category, label, percentage);
    }

    /**
     * Handle clicking a preset button for "other" — reads the label input.
     *
     * @param {number} percentage
     */
    _handleOtherPresetClick(percentage) {
        const label = (this._otherLabelInput?.value || '').trim();
        if (!label) {
            this._otherLabelInput.classList.add('is-invalid');
            this._otherLabelInput.focus();
            // Remove invalid class on next input.
            this._otherLabelInput.addEventListener('input', () => {
                this._otherLabelInput.classList.remove('is-invalid');
            }, {once: true});
            return;
        }
        this._onSave('other', label, percentage);
    }

    /**
     * Show an inline custom percentage input.
     *
     * @param {HTMLElement} btnRow The button row to append to.
     * @param {string} category
     * @param {string|null} label Label for other, or null to read from input.
     */
    _showCustomInput(btnRow, category, label) {
        // Remove existing custom input if present.
        const existing = btnRow.querySelector('.penalty-custom-input');
        if (existing) {
            existing.remove();
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.className = 'penalty-custom-input d-flex align-items-center gap-1 mt-1 w-100';

        const input = document.createElement('input');
        input.type = 'number';
        input.className = 'form-control form-control-sm';
        input.style.width = '70px';
        input.min = 1;
        input.max = 100;
        input.placeholder = '%';

        const applyBtn = document.createElement('button');
        applyBtn.type = 'button';
        applyBtn.className = 'btn btn-sm btn-primary';
        applyBtn.innerHTML = '<i class="fa fa-check"></i>';
        applyBtn.addEventListener('click', () => {
            const pct = parseInt(input.value, 10);
            if (isNaN(pct) || pct < 1 || pct > 100) {
                input.classList.add('is-invalid');
                return;
            }
            if (category === 'other') {
                const otherLabel = (this._otherLabelInput?.value || '').trim();
                if (!otherLabel) {
                    this._otherLabelInput.classList.add('is-invalid');
                    this._otherLabelInput.focus();
                    return;
                }
                this._onSave('other', otherLabel, pct);
            } else {
                this._onSave(category, label || '', pct);
            }
            wrapper.remove();
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyBtn.click();
            }
        });

        wrapper.appendChild(input);
        wrapper.appendChild(applyBtn);
        btnRow.appendChild(wrapper);
        input.focus();
    }

    /**
     * Update the active penalties list in the popout.
     */
    _updateActiveList() {
        if (!this._activeListEl) {
            return;
        }
        this._activeListEl.innerHTML = '';

        if (!this._penalties.length) {
            const empty = document.createElement('span');
            empty.className = 'small text-muted';
            empty.textContent = '—';
            this._activeListEl.appendChild(empty);
            return;
        }

        this._penalties.forEach((p) => {
            const isLate = p.category === 'late';
            const badge = document.createElement('span');
            badge.className = isLate
                ? 'badge bg-danger penalty-active-item local-unifiedgrader-penalty-badge'
                : 'badge bg-warning text-dark penalty-active-item local-unifiedgrader-penalty-badge';

            // Late penalties are auto-managed — no delete button.
            if (!isLate) {
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'btn-close btn-close-sm ms-1';
                removeBtn.style.fontSize = '0.55rem';
                removeBtn.setAttribute('aria-label', 'Remove');
                removeBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this._onDelete(p.id);
                });
                const displayLabel = p.category === 'wordcount' ? 'Word count' : (p.label || 'Other');
                badge.textContent = '-' + p.percentage + '% ' + displayLabel;
                badge.appendChild(removeBtn);
            } else {
                badge.textContent = '-' + p.percentage + '% ' + (p.label || 'Late');
                badge.title = 'Automatically calculated based on penalty rules';
                getString('penalty_late_auto', 'local_unifiedgrader').then((s) => {
                    badge.title = s;
                });
            }

            this._activeListEl.appendChild(badge);
        });
    }

    /**
     * Highlight preset buttons that match active penalties.
     */
    _highlightActivePresets() {
        if (!this._el) {
            return;
        }

        // Reset all.
        this._el.querySelectorAll('.penalty-preset-btn').forEach((btn) => {
            btn.classList.remove('active');
        });

        this._penalties.forEach((p) => {
            const row = this._el.querySelector(`[data-category="${p.category}"]`);
            if (row) {
                const btn = row.querySelector(`[data-percentage="${p.percentage}"]`);
                if (btn) {
                    btn.classList.add('active');
                }
            }

            // Pre-fill the other label input.
            if (p.category === 'other' && this._otherLabelInput) {
                this._otherLabelInput.value = p.label || '';
            }
        });
    }

    /**
     * Create a horizontal divider.
     *
     * @return {HTMLElement}
     */
    _createDivider() {
        const hr = document.createElement('hr');
        hr.className = 'my-1';
        return hr;
    }
}
