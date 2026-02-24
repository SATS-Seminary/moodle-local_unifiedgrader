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
 * undo/redo, clear buttons, and document info popout.
 *
 * This is a plain class (not a reactive component) that is wired to
 * an AnnotationLayer instance by the PdfViewer.
 *
 * @module     local_unifiedgrader/components/annotation_toolbar
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {get_string as getString} from 'core/str';
import Notification from 'core/notification';

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
        /** @type {?object} Document info data. */
        this._docInfo = null;
        /** @type {?Function} Callback to compute word count. */
        this._wordCountFn = null;
        /** @type {?number} Cached word count. */
        this._wordCount = null;
        /** @type {boolean} Whether word count is being computed. */
        this._wordCountLoading = false;
        /** @type {?Function} Bound outside-click handler for closing the popout. */
        this._outsideClickHandler = null;

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

            // Pen width selection.
            if (btn.dataset.penwidth) {
                this._selectPenWidth(btn);
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
                Notification.saveCancelPromise(
                    getString('confirm', 'moodle'),
                    getString('annotate_clear_confirm', 'local_unifiedgrader'),
                    getString('yes', 'moodle'),
                ).then(() => {
                    this._layer.clearAnnotations();
                    this._updateActionStates();
                    return;
                }).catch(() => {
                    // User cancelled — do nothing.
                });
                return; // Don't update action states until confirmed.
            } else if (action === 'doc-info') {
                this._toggleDocInfo();
                return; // Don't update action states for info toggle.
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
        const shape = btn.dataset.shape || null;

        // If clicking the same stamp, or a different tool, update.
        // For stamps, also set the stamp type.
        if (stamp) {
            this._layer.setStampType(stamp);
            if (this._activeTool !== 'stamp') {
                this._layer.setTool('stamp');
            }
        } else if (shape) {
            this._layer.setShapeType(shape);
            if (this._activeTool !== 'shape') {
                this._layer.setTool('shape');
            }
        } else {
            this._layer.setTool(tool);
        }

        this._activeTool = stamp ? 'stamp' : (shape ? 'shape' : tool);

        // Update visual active state — tools in the btn-group.
        const toolBtns = this._el.querySelectorAll('[data-region="tool-selector"] button');
        toolBtns.forEach((b) => b.classList.toggle('active', b.dataset.tool === this._activeTool));

        // Update pen button (outside the btn-group).
        const penBtn = this._el.querySelector('[data-region="pen-width-wrapper"] > button[data-tool="pen"]');
        if (penBtn) {
            penBtn.classList.toggle('active', this._activeTool === 'pen');
        }

        // Update shape button (outside the btn-group).
        const shapeBtn = this._el.querySelector('[data-region="shape-wrapper"] > button[data-tool="shape"]');
        if (shapeBtn) {
            shapeBtn.classList.toggle('active', this._activeTool === 'shape');
        }

        // Update stamp buttons.
        const stampBtns = this._el.querySelectorAll('[data-region="stamp-selector"] button');
        stampBtns.forEach((b) => {
            b.classList.toggle('active', stamp !== null && b.dataset.stamp === stamp);
        });

        // Update shape popout buttons.
        const shapeBtns = this._el.querySelectorAll('[data-region="shape-popout"] button');
        shapeBtns.forEach((b) => {
            b.classList.toggle('active', shape !== null && b.dataset.shape === shape);
        });

        // Show/hide pen width popout.
        this._updatePenWidthPopout();
        // Show/hide shape popout.
        this._updateShapePopout();
    }

    /**
     * Show the pen width popout when pen is active, hide otherwise.
     */
    _updatePenWidthPopout() {
        const popout = this._el.querySelector('[data-region="pen-width-popout"]');
        if (popout) {
            popout.classList.toggle('d-none', this._activeTool !== 'pen');
        }
    }

    /**
     * Show the shape popout when shape tool is active, hide otherwise.
     */
    _updateShapePopout() {
        const popout = this._el.querySelector('[data-region="shape-popout"]');
        if (popout) {
            popout.classList.toggle('d-none', this._activeTool !== 'shape');
        }
    }

    /**
     * Handle pen width button click.
     *
     * @param {HTMLElement} btn The clicked pen width button.
     */
    _selectPenWidth(btn) {
        const width = parseInt(btn.dataset.penwidth, 10);
        this._layer.setBrushWidth(width);

        // Update active state on width buttons.
        const widthBtns = this._el.querySelectorAll('[data-region="pen-width-popout"] button');
        widthBtns.forEach((b) => b.classList.toggle('active', b.dataset.penwidth === btn.dataset.penwidth));
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

    // ──────────────────────────────────────────────
    //  Document info popout
    // ──────────────────────────────────────────────

    /**
     * Set document info data to display in the popout.
     *
     * @param {object} info Document info.
     * @param {string} info.filename File name.
     * @param {number} info.filesize File size in bytes.
     * @param {number} info.pages Total page count.
     * @param {object} info.metadata PDF internal metadata.
     * @param {Function} wordCountFn Async callback that returns the word count.
     */
    setDocumentInfo(info, wordCountFn) {
        this._docInfo = info;
        this._wordCountFn = wordCountFn;
        this._wordCount = null;
        this._wordCountLoading = false;
    }

    /**
     * Toggle the document info popout visibility.
     */
    _toggleDocInfo() {
        const popout = this._el.querySelector('[data-region="doc-info-popout"]');
        if (!popout) {
            return;
        }

        const isVisible = !popout.classList.contains('d-none');
        if (isVisible) {
            this._closeDocInfo();
        } else {
            this._renderDocInfo();
            popout.classList.remove('d-none');
            // Close on outside click (deferred so current click doesn't trigger it).
            requestAnimationFrame(() => {
                this._outsideClickHandler = (e) => {
                    const wrapper = this._el.querySelector('[data-region="doc-info-wrapper"]');
                    if (wrapper && !wrapper.contains(e.target)) {
                        this._closeDocInfo();
                    }
                };
                document.addEventListener('click', this._outsideClickHandler, true);
            });
        }
    }

    /**
     * Close the document info popout.
     */
    _closeDocInfo() {
        const popout = this._el.querySelector('[data-region="doc-info-popout"]');
        if (popout) {
            popout.classList.add('d-none');
        }
        if (this._outsideClickHandler) {
            document.removeEventListener('click', this._outsideClickHandler, true);
            this._outsideClickHandler = null;
        }
    }

    /**
     * Render document info content into the popout.
     */
    async _renderDocInfo() {
        const popout = this._el.querySelector('[data-region="doc-info-popout"]');
        if (!popout || !this._docInfo) {
            return;
        }

        const info = this._docInfo;
        const meta = info.metadata || {};

        // Fetch all labels in parallel.
        const [
            lblFilename, lblFilesize, lblPages, lblWordcount,
            lblAuthor, lblCreator, lblCreated, lblModified, lblCalc,
        ] = await Promise.all([
            getString('docinfo_filename', 'local_unifiedgrader'),
            getString('docinfo_filesize', 'local_unifiedgrader'),
            getString('docinfo_pages', 'local_unifiedgrader'),
            getString('docinfo_wordcount', 'local_unifiedgrader'),
            getString('docinfo_author', 'local_unifiedgrader'),
            getString('docinfo_creator', 'local_unifiedgrader'),
            getString('docinfo_created', 'local_unifiedgrader'),
            getString('docinfo_modified', 'local_unifiedgrader'),
            getString('docinfo_calculating', 'local_unifiedgrader'),
        ]);

        const rows = [];
        rows.push(this._makeRow(lblFilename, info.filename || ''));
        rows.push(this._makeRow(lblFilesize, this._formatBytes(info.filesize)));
        rows.push(this._makeRow(lblPages, String(info.pages || 0)));

        // Word count: show cached value or calculating placeholder.
        const wordCountValue = this._wordCount !== null
            ? this._wordCount.toLocaleString()
            : lblCalc;
        rows.push(this._makeRow(lblWordcount, wordCountValue, 'doc-info-wordcount'));

        // PDF metadata rows (only show if value exists).
        if (meta.Author) {
            rows.push(this._makeRow(lblAuthor, meta.Author));
        }
        if (meta.Creator) {
            rows.push(this._makeRow(lblCreator, meta.Creator));
        }
        if (meta.CreationDate) {
            rows.push(this._makeRow(lblCreated, this._formatPdfDate(meta.CreationDate)));
        }
        if (meta.ModDate) {
            rows.push(this._makeRow(lblModified, this._formatPdfDate(meta.ModDate)));
        }

        popout.innerHTML = '';
        rows.forEach((row) => popout.appendChild(row));

        // Trigger lazy word count computation if not yet done.
        if (this._wordCount === null && !this._wordCountLoading && this._wordCountFn) {
            this._wordCountLoading = true;
            try {
                this._wordCount = await this._wordCountFn();
                const wcEl = popout.querySelector('[data-region="doc-info-wordcount"]');
                if (wcEl) {
                    wcEl.textContent = this._wordCount.toLocaleString();
                }
            } catch {
                // Silently ignore word count errors.
            } finally {
                this._wordCountLoading = false;
            }
        }
    }

    /**
     * Create a key-value row element for the popout.
     *
     * @param {string} label The label text.
     * @param {string} value The value text.
     * @param {string} [valueRegion] Optional data-region for the value element.
     * @return {HTMLElement}
     */
    _makeRow(label, value, valueRegion) {
        const row = document.createElement('div');
        row.className = 'docinfo-row';

        const lbl = document.createElement('span');
        lbl.className = 'docinfo-label';
        lbl.textContent = label;

        const val = document.createElement('span');
        val.className = 'docinfo-value';
        val.textContent = value;
        if (valueRegion) {
            val.dataset.region = valueRegion;
        }

        row.appendChild(lbl);
        row.appendChild(val);
        return row;
    }

    /**
     * Format bytes into a human-readable string.
     *
     * @param {number} bytes File size in bytes.
     * @return {string}
     */
    _formatBytes(bytes) {
        if (!bytes || bytes === 0) {
            return '0 B';
        }
        const units = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        const size = (bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1);
        return size + ' ' + units[i];
    }

    /**
     * Parse a PDF date string (D:YYYYMMDDHHmmSS) into a readable format.
     *
     * @param {string} pdfDate PDF date string.
     * @return {string}
     */
    _formatPdfDate(pdfDate) {
        if (!pdfDate) {
            return '';
        }
        // PDF dates look like "D:20240315120000+02'00'" or "D:20240315120000".
        const match = pdfDate.match(
            /D:(\d{4})(\d{2})(\d{2})(\d{2})?(\d{2})?(\d{2})?/
        );
        if (!match) {
            return pdfDate;
        }
        const [, y, m, d, h, min] = match;
        const date = new Date(
            parseInt(y, 10), parseInt(m, 10) - 1, parseInt(d, 10),
            parseInt(h || '0', 10), parseInt(min || '0', 10),
        );
        return date.toLocaleDateString(undefined, {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: '2-digit', minute: '2-digit',
        });
    }

    /**
     * Switch the annotation layer this toolbar controls.
     * Re-registers change/selection callbacks and updates button states.
     *
     * @param {object} layer An AnnotationLayer instance.
     */
    setLayer(layer) {
        this._layer = layer;
        // Re-register callbacks on the new layer.
        this._layer.onChange(() => this._updateActionStates());
        this._layer.onSelectionChange(() => this._updateDeleteState());
        this._updateActionStates();
        this._updateDeleteState();
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
        this._closeDocInfo();
        this._layer = null;
    }
}
