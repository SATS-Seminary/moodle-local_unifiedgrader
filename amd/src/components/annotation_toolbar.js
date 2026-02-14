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
 * Annotation toolbar handler — manages tool selection, colour picker,
 * undo/redo, and clear buttons.
 *
 * This is a plain class (not a reactive component) that is wired to
 * an AnnotationLayer instance by the PdfViewer.
 *
 * @module     local_unifiedgrader/components/annotation_toolbar
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export default class AnnotationToolbar {

    /**
     * @param {HTMLElement} toolbarEl The toolbar container element.
     * @param {object} annotationLayer An AnnotationLayer instance.
     */
    constructor(toolbarEl, annotationLayer) {
        /** @type {HTMLElement} */
        this._el = toolbarEl;
        /** @type {object} */
        this._layer = annotationLayer;
        /** @type {string} */
        this._activeTool = 'select';

        this._bindEvents();
    }

    /**
     * Bind click handlers via event delegation.
     */
    _bindEvents() {
        this._onClick = (e) => {
            const btn = e.target.closest('button');
            if (!btn) {
                return;
            }

            // Tool selection.
            if (btn.dataset.tool) {
                this._selectTool(btn);
                return;
            }

            // Colour selection.
            if (btn.dataset.color) {
                this._selectColor(btn);
                return;
            }

            // Actions.
            const action = btn.dataset.action;
            if (action === 'undo') {
                this._layer.undo();
            } else if (action === 'redo') {
                this._layer.redo();
            } else if (action === 'delete-selected') {
                this._layer.deleteSelected();
            } else if (action === 'clear-annotations') {
                this._layer.clearAnnotations();
            }

            this._updateActionStates();
        };
        this._el.addEventListener('click', this._onClick);

        // Update button states when annotations change.
        this._layer.onChange(() => this._updateActionStates());

        // Update delete button when selection changes on the canvas.
        this._layer.onSelectionChange(() => this._updateDeleteState());
    }

    /**
     * Handle tool button click.
     *
     * @param {HTMLElement} btn The clicked tool button.
     */
    _selectTool(btn) {
        const tool = btn.dataset.tool;
        const stamp = btn.dataset.stamp || null;

        // If clicking the same stamp, or a different tool, update.
        // For stamps, also set the stamp type.
        if (stamp) {
            this._layer.setStampType(stamp);
            // If switching from a non-stamp tool, set to stamp.
            if (this._activeTool !== 'stamp') {
                this._layer.setTool('stamp');
            }
        } else {
            this._layer.setTool(tool);
        }

        this._activeTool = stamp ? 'stamp' : tool;

        // Update visual active state — tools.
        const toolBtns = this._el.querySelectorAll('[data-region="tool-selector"] button');
        toolBtns.forEach((b) => b.classList.toggle('active', b.dataset.tool === this._activeTool));

        // Update stamp buttons.
        const stampBtns = this._el.querySelectorAll('[data-region="stamp-selector"] button');
        stampBtns.forEach((b) => {
            b.classList.toggle('active', stamp !== null && b.dataset.stamp === stamp);
        });
    }

    /**
     * Handle colour button click.
     *
     * @param {HTMLElement} btn The clicked colour button.
     */
    _selectColor(btn) {
        const color = btn.dataset.color;
        this._layer.setColor(color);

        // Update visual active state.
        const colorBtns = this._el.querySelectorAll('[data-region="color-picker"] button');
        colorBtns.forEach((b) => b.classList.toggle('active', b.dataset.color === color));
    }

    /**
     * Update the enabled/disabled state of undo, redo, and delete buttons.
     */
    _updateActionStates() {
        const undoBtn = this._el.querySelector('[data-action="undo"]');
        const redoBtn = this._el.querySelector('[data-action="redo"]');
        if (undoBtn) {
            undoBtn.disabled = !this._layer.canUndo();
        }
        if (redoBtn) {
            redoBtn.disabled = !this._layer.canRedo();
        }
        this._updateDeleteState();
    }

    /**
     * Update the enabled/disabled state of the delete button.
     */
    _updateDeleteState() {
        const deleteBtn = this._el.querySelector('[data-action="delete-selected"]');
        if (deleteBtn) {
            deleteBtn.disabled = !this._layer.hasSelection();
        }
    }

    /**
     * Show the toolbar.
     */
    show() {
        this._el.classList.remove('d-none');
    }

    /**
     * Hide the toolbar.
     */
    hide() {
        this._el.classList.add('d-none');
    }

    /**
     * Remove event listeners and clean up references.
     */
    destroy() {
        if (this._onClick) {
            this._el.removeEventListener('click', this._onClick);
            this._onClick = null;
        }
        this._layer = null;
    }
}
