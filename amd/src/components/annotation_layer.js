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
 * Annotation layer - manages a Fabric.js canvas overlay on the PDF page.
 *
 * This is a plain class (not a Moodle reactive component) that handles:
 * - Fabric.js canvas lifecycle on top of the PDF canvas
 * - Tool modes (select, comment, highlight, pen, stamp)
 * - Per-page annotation storage with backend persistence
 * - Undo / redo stack
 * - Comment popup for the comment tool
 *
 * @module     local_unifiedgrader/components/annotation_layer
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {TOOLS, CUSTOM_PROPS, DEFAULT_COLOR, createCommentMarker, createHighlight, createStamp} from
    'local_unifiedgrader/annotation/types';

export default class AnnotationLayer {

    /**
     * @param {object} fabric The Fabric.js library namespace.
     * @param {HTMLCanvasElement} canvasEl The annotation canvas element.
     * @param {HTMLElement} wrapperEl The canvas wrapper element (for popup positioning).
     * @param {boolean} readOnly If true, annotations are non-editable (student view).
     */
    constructor(fabric, canvasEl, wrapperEl, readOnly = false) {
        /** @type {object} */
        this._fabric = fabric;
        /** @type {HTMLElement} */
        this._wrapperEl = wrapperEl;
        /** @type {boolean} */
        this._readOnly = readOnly;
        /** @type {string} */
        this._currentTool = TOOLS.SELECT;
        /** @type {string} */
        this._currentColor = DEFAULT_COLOR;
        /** @type {string} */
        this._currentStamp = 'CHECK';
        /** @type {number} */
        this._canvasWidth = 0;
        /** @type {number} */
        this._canvasHeight = 0;

        // Per-page state: page number → Fabric JSON.
        /** @type {Map<number, object>} */
        this._pageAnnotations = new Map();
        /** @type {number} */
        this._currentPageNum = 1;

        // Undo / redo.
        /** @type {Array} */
        this._undoStack = [];
        /** @type {Array} */
        this._redoStack = [];

        // Highlight drag state.
        /** @type {?object} */
        this._tempRect = null;
        /** @type {?object} */
        this._dragStart = null;
        /** @type {boolean} */
        this._isDragging = false;

        // Comment popup and tooltip.
        /** @type {?HTMLElement} */
        this._commentPopup = null;
        /** @type {?HTMLElement} */
        this._commentTooltip = null;

        // Callbacks (arrays to support multiple listeners).
        /** @type {Function[]} */
        this._onChangeCallbacks = [];
        /** @type {Function[]} */
        this._onSelectionChangeCallbacks = [];

        // Enable pointer events on the canvas element.
        canvasEl.style.pointerEvents = 'auto';

        // Initialise Fabric.js canvas.
        this._canvas = new fabric.Canvas(canvasEl, {
            selection: readOnly ? false : false,
            renderOnAddRemove: true,
            preserveObjectStacking: true,
        });

        // In read-only mode, disable interaction except hover for tooltips.
        if (readOnly) {
            this._canvas.selection = false;
            this._canvas.hoverCursor = 'default';
            this._canvas.defaultCursor = 'default';
            this._setupReadOnlyEventHandlers();
        } else {
            this._setupEventHandlers();
        }
    }

    // ──────────────────────────────────────────────
    //  Public API
    // ──────────────────────────────────────────────

    /**
     * Set the active annotation tool.
     *
     * @param {string} tool One of TOOLS values.
     */
    setTool(tool) {
        if (this._readOnly) {
            return;
        }
        this._closeCommentPopup();
        this._currentTool = tool;
        if (!this._canvas) {
            return;
        }

        // Toggle Fabric.js drawing mode for pen tool.
        if (tool === TOOLS.PEN) {
            this._canvas.isDrawingMode = true;
            this._canvas.freeDrawingBrush = new this._fabric.PencilBrush(this._canvas);
            this._canvas.freeDrawingBrush.color = this._currentColor;
            this._canvas.freeDrawingBrush.width = 3;
        } else {
            this._canvas.isDrawingMode = false;
        }

        // In select mode, allow object selection. Otherwise disable it.
        this._canvas.forEachObject((obj) => {
            obj.selectable = (tool === TOOLS.SELECT);
            obj.evented = (tool === TOOLS.SELECT);
        });
        if (tool !== TOOLS.SELECT) {
            this._canvas.discardActiveObject();
        }
        this._canvas.requestRenderAll();
    }

    /**
     * Set the annotation colour.
     *
     * @param {string} color Hex colour string.
     */
    setColor(color) {
        this._currentColor = color;
        if (this._canvas && this._canvas.isDrawingMode && this._canvas.freeDrawingBrush) {
            this._canvas.freeDrawingBrush.color = color;
        }
    }

    /**
     * Set the active stamp type.
     *
     * @param {string} stampType Key from STAMPS (CHECK, CROSS, QUESTION).
     */
    setStampType(stampType) {
        this._currentStamp = stampType;
    }

    /**
     * Resize the Fabric canvas to match the rendered PDF page.
     *
     * @param {number} width Display width in pixels.
     * @param {number} height Display height in pixels.
     */
    setPageSize(width, height) {
        this._canvasWidth = width;
        this._canvasHeight = height;
        if (!this._canvas) {
            return;
        }
        this._canvas.setDimensions({width: width, height: height});
        this._canvas.requestRenderAll();
    }

    /**
     * Switch to a new page: save current page state, load new page state.
     *
     * @param {number} pageNum The new page number (1-based).
     */
    async switchPage(pageNum) {
        // Save current page (skip in read-only mode — nothing to save).
        if (!this._readOnly) {
            this._saveCurrentPageState();
        }

        this._currentPageNum = pageNum;
        this._undoStack = [];
        this._redoStack = [];
        if (!this._canvas) {
            return;
        }

        // Load the new page's annotations.
        const saved = this._pageAnnotations.get(pageNum);
        this._canvas.clear();
        if (saved && saved.objects && saved.objects.length > 0) {
            await this._loadFromJSONWithCustomProps(saved);
            if (this._readOnly) {
                this._markAllObjectsReadOnly();
            } else {
                // Re-apply selectability based on current tool.
                this.setTool(this._currentTool);
            }
        }
        this._canvas.requestRenderAll();
        if (!this._readOnly) {
            this._notifyChange();
        }
    }

    /**
     * Undo the last annotation operation.
     */
    undo() {
        if (this._readOnly || this._undoStack.length === 0 || !this._canvas) {
            return;
        }
        const action = this._undoStack.pop();
        this._redoStack.push(action);

        if (action.type === 'add') {
            this._canvas.remove(action.object);
        } else if (action.type === 'remove') {
            this._canvas.add(action.object);
        }
        this._canvas.requestRenderAll();
        this._notifyChange();
    }

    /**
     * Redo the last undone operation.
     */
    redo() {
        if (this._readOnly || this._redoStack.length === 0 || !this._canvas) {
            return;
        }
        const action = this._redoStack.pop();
        this._undoStack.push(action);

        if (action.type === 'add') {
            this._canvas.add(action.object);
        } else if (action.type === 'remove') {
            this._canvas.remove(action.object);
        }
        this._canvas.requestRenderAll();
        this._notifyChange();
    }

    /** @returns {boolean} */
    canUndo() {
        return this._undoStack.length > 0;
    }

    /** @returns {boolean} */
    canRedo() {
        return this._redoStack.length > 0;
    }

    /**
     * Remove all annotations from the current page.
     */
    clearAnnotations() {
        if (this._readOnly || !this._canvas) {
            return;
        }
        const objects = this._canvas.getObjects().slice();
        objects.forEach((obj) => {
            this._undoStack.push({type: 'add', object: obj});
        });
        this._redoStack = [];
        this._canvas.clear();
        this._canvas.requestRenderAll();
        this._notifyChange();
    }

    /**
     * Delete the currently selected annotation.
     */
    deleteSelected() {
        if (this._readOnly || !this._canvas) {
            return;
        }
        const active = this._canvas.getActiveObject();
        if (active) {
            this._canvas.remove(active);
            this._undoStack.push({type: 'remove', object: active});
            this._redoStack = [];
            this._canvas.discardActiveObject();
            this._canvas.requestRenderAll();
            this._notifyChange();
        }
    }

    /**
     * Get all annotation data for all pages (for saving).
     *
     * @returns {Map<number, object>} Map of page number to Fabric JSON.
     */
    getAllAnnotations() {
        this._saveCurrentPageState();
        return new Map(this._pageAnnotations);
    }

    /**
     * Load annotations for a specific page from external data.
     *
     * @param {number} pageNum Page number.
     * @param {object} fabricJson Fabric.js canvas JSON.
     */
    setPageAnnotations(pageNum, fabricJson) {
        this._pageAnnotations.set(pageNum, fabricJson);
    }

    /**
     * Reload the current page's annotations onto the canvas without saving
     * current state first. Used after setPageAnnotations() populates the map
     * from the backend — calling switchPage() would save the empty canvas
     * and overwrite the just-loaded data.
     */
    async reloadCurrentPage() {
        if (!this._canvas) {
            return;
        }
        const saved = this._pageAnnotations.get(this._currentPageNum);
        this._canvas.clear();
        if (saved && saved.objects && saved.objects.length > 0) {
            await this._loadFromJSONWithCustomProps(saved);
            if (this._readOnly) {
                this._markAllObjectsReadOnly();
            } else {
                this.setTool(this._currentTool);
            }
        }
        this._canvas.requestRenderAll();
    }

    /**
     * Whether an object is currently selected on the canvas.
     *
     * @returns {boolean}
     */
    hasSelection() {
        return !!this._canvas && !!this._canvas.getActiveObject();
    }

    /**
     * Register a callback for annotation changes.
     *
     * @param {Function} callback Called with no args when annotations change.
     */
    onChange(callback) {
        this._onChangeCallbacks.push(callback);
    }

    /**
     * Register a callback for selection changes.
     *
     * @param {Function} callback Called with no args when selection changes.
     */
    onSelectionChange(callback) {
        this._onSelectionChangeCallbacks.push(callback);
    }

    /**
     * Clean up and destroy the Fabric canvas.
     */
    destroy() {
        this._closeCommentPopup();
        this._hideCommentTooltip();
        this._saveCurrentPageState();
        if (this._canvas) {
            this._canvas.dispose();
            this._canvas = null;
        }
    }

    // ──────────────────────────────────────────────
    //  Event Handlers
    // ──────────────────────────────────────────────

    /** Set up read-only event handlers (comment tooltips only). */
    _setupReadOnlyEventHandlers() {
        this._canvas.on('mouse:over', (opt) => this._onMouseOver(opt));
        this._canvas.on('mouse:out', () => this._hideCommentTooltip());
    }

    /** Set up Fabric canvas event handlers. */
    _setupEventHandlers() {
        this._canvas.on('mouse:down', (opt) => this._onMouseDown(opt));
        this._canvas.on('mouse:move', (opt) => this._onMouseMove(opt));
        this._canvas.on('mouse:up', () => this._onMouseUp());
        this._canvas.on('path:created', (opt) => this._onPathCreated(opt));
        this._canvas.on('mouse:dblclick', (opt) => this._onDoubleClick(opt));
        this._canvas.on('mouse:over', (opt) => this._onMouseOver(opt));
        this._canvas.on('mouse:out', () => this._hideCommentTooltip());
        this._canvas.on('selection:created', () => this._notifySelectionChange());
        this._canvas.on('selection:updated', () => this._notifySelectionChange());
        this._canvas.on('selection:cleared', () => this._notifySelectionChange());
    }

    /**
     * Handle mouse down based on current tool.
     *
     * @param {object} opt Fabric event options.
     */
    _onMouseDown(opt) {
        // Ignore if clicking on an existing object in non-select mode
        // (let Fabric handle it in select mode).
        if (this._currentTool === TOOLS.SELECT) {
            return;
        }
        if (this._currentTool === TOOLS.PEN) {
            return; // Fabric handles drawing mode.
        }

        const pointer = this._canvas.getViewportPoint(opt.e);

        switch (this._currentTool) {
            case TOOLS.COMMENT:
                this._placeComment(pointer);
                break;
            case TOOLS.HIGHLIGHT:
                this._startHighlight(pointer);
                break;
            case TOOLS.STAMP:
                this._placeStamp(pointer);
                break;
        }
    }

    /**
     * Handle mouse move for highlight drag.
     *
     * @param {object} opt Fabric event options.
     */
    _onMouseMove(opt) {
        if (this._currentTool !== TOOLS.HIGHLIGHT || !this._isDragging || !this._tempRect) {
            return;
        }
        const pointer = this._canvas.getViewportPoint(opt.e);
        const left = Math.min(this._dragStart.x, pointer.x);
        const top = Math.min(this._dragStart.y, pointer.y);
        const width = Math.abs(pointer.x - this._dragStart.x);
        const height = Math.abs(pointer.y - this._dragStart.y);

        this._tempRect.set({left, top, width, height});
        this._canvas.requestRenderAll();
    }

    /**
     * Handle mouse up to finalise highlight drag.
     */
    _onMouseUp() {
        if (this._currentTool !== TOOLS.HIGHLIGHT || !this._isDragging) {
            return;
        }
        this._isDragging = false;

        if (this._tempRect) {
            const width = this._tempRect.width || 0;
            const height = this._tempRect.height || 0;

            // Minimum size check — remove if too small (accidental click).
            if (width < 5 && height < 5) {
                this._canvas.remove(this._tempRect);
            } else {
                // Finalise — set selectable and add to undo stack.
                this._tempRect.setCoords();
                this._undoStack.push({type: 'add', object: this._tempRect});
                this._redoStack = [];
                this._notifyChange();
            }
            this._tempRect = null;
        }
    }

    /**
     * Handle path created by free drawing (pen tool).
     *
     * @param {object} opt Fabric event with .path property.
     */
    _onPathCreated(opt) {
        const path = opt.path;
        if (path) {
            path.annotationType = 'pen';
            this._undoStack.push({type: 'add', object: path});
            this._redoStack = [];
            this._notifyChange();
        }
    }

    /**
     * Double-click to edit comment text.
     *
     * @param {object} opt Fabric event options.
     */
    _onDoubleClick(opt) {
        if (this._currentTool !== TOOLS.SELECT || !opt.target) {
            return;
        }
        if (opt.target.annotationType === 'comment') {
            this._showCommentPopup(opt.target.left, opt.target.top, opt.target.annotationText, (text) => {
                if (text !== null) {
                    opt.target.annotationText = text;
                    this._notifyChange();
                }
            });
        }
    }

    // ──────────────────────────────────────────────
    //  Tool actions
    // ──────────────────────────────────────────────

    /**
     * Place a comment marker at the given position.
     *
     * @param {object} pointer {x, y} canvas position.
     */
    _placeComment(pointer) {
        const marker = createCommentMarker(this._fabric, pointer.x, pointer.y, this._currentColor, '');
        this._canvas.add(marker);
        this._canvas.requestRenderAll();

        // Show popup for text entry.
        this._showCommentPopup(pointer.x, pointer.y, '', (text) => {
            if (text !== null && text.trim() !== '') {
                marker.annotationText = text;
                this._undoStack.push({type: 'add', object: marker});
                this._redoStack = [];
                this._notifyChange();
            } else {
                // Cancelled or empty — remove the marker.
                this._canvas.remove(marker);
                this._canvas.requestRenderAll();
            }
        });
    }

    /**
     * Start drawing a highlight rectangle.
     *
     * @param {object} pointer {x, y} canvas position.
     */
    _startHighlight(pointer) {
        this._isDragging = true;
        this._dragStart = {x: pointer.x, y: pointer.y};

        this._tempRect = createHighlight(
            this._fabric, pointer.x, pointer.y, 0, 0, this._currentColor
        );
        this._tempRect.selectable = false;
        this._tempRect.evented = false;
        this._canvas.add(this._tempRect);
    }

    /**
     * Place a stamp at the given position.
     *
     * @param {object} pointer {x, y} canvas position.
     */
    _placeStamp(pointer) {
        const stamp = createStamp(this._fabric, pointer.x, pointer.y, this._currentStamp, this._currentColor);
        this._canvas.add(stamp);
        this._undoStack.push({type: 'add', object: stamp});
        this._redoStack = [];
        this._canvas.requestRenderAll();
        this._notifyChange();
    }

    // ──────────────────────────────────────────────
    //  Comment popup
    // ──────────────────────────────────────────────

    /**
     * Show a text input popup near a position for comment entry.
     *
     * @param {number} x Canvas x position.
     * @param {number} y Canvas y position.
     * @param {string} existingText Pre-filled text.
     * @param {Function} callback Called with the entered text or null if cancelled.
     */
    _showCommentPopup(x, y, existingText, callback) {
        this._closeCommentPopup();

        const popup = document.createElement('div');
        popup.className = 'annotation-comment-popup';

        // Position to the right of the marker, clamped to canvas bounds.
        let popupLeft = x + 20;
        if (popupLeft + 220 > this._canvasWidth) {
            popupLeft = x - 240;
        }
        let popupTop = y - 10;
        if (popupTop < 0) {
            popupTop = 0;
        }
        popup.style.left = popupLeft + 'px';
        popup.style.top = popupTop + 'px';

        const textarea = document.createElement('textarea');
        textarea.className = 'form-control form-control-sm';
        textarea.rows = 3;
        textarea.placeholder = 'Enter comment...';
        textarea.value = existingText || '';

        const btnRow = document.createElement('div');
        btnRow.className = 'd-flex gap-1 mt-1';

        const saveBtn = document.createElement('button');
        saveBtn.className = 'btn btn-sm btn-primary';
        saveBtn.textContent = 'Save';

        const cancelBtn = document.createElement('button');
        cancelBtn.className = 'btn btn-sm btn-secondary';
        cancelBtn.textContent = 'Cancel';

        btnRow.appendChild(saveBtn);
        btnRow.appendChild(cancelBtn);
        popup.appendChild(textarea);
        popup.appendChild(btnRow);

        this._wrapperEl.appendChild(popup);
        this._commentPopup = popup;

        // Focus after a tick (Fabric.js may steal focus).
        setTimeout(() => textarea.focus(), 50);

        const finish = (text) => {
            this._closeCommentPopup();
            callback(text);
        };

        saveBtn.addEventListener('click', () => finish(textarea.value));
        cancelBtn.addEventListener('click', () => finish(null));

        textarea.addEventListener('keydown', (e) => {
            // Ctrl+Enter or Cmd+Enter to save.
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                e.preventDefault();
                finish(textarea.value);
            }
            // Escape to cancel.
            if (e.key === 'Escape') {
                e.preventDefault();
                finish(null);
            }
            // Stop propagation so Fabric.js / page nav don't react.
            e.stopPropagation();
        });
    }

    /** Close the comment popup if open. */
    _closeCommentPopup() {
        if (this._commentPopup) {
            this._commentPopup.remove();
            this._commentPopup = null;
        }
    }

    // ──────────────────────────────────────────────
    //  Comment tooltip (hover)
    // ──────────────────────────────────────────────

    /**
     * Show tooltip when hovering over a comment marker.
     *
     * @param {object} opt Fabric mouse:over event options.
     */
    _onMouseOver(opt) {
        const target = opt.target;
        if (!target || target.annotationType !== 'comment' || !target.annotationText) {
            return;
        }
        this._showCommentTooltip(target.left, target.top, target.annotationText);
    }

    /**
     * Show a read-only tooltip near a comment marker.
     *
     * @param {number} x Canvas x position.
     * @param {number} y Canvas y position.
     * @param {string} text Comment text.
     */
    _showCommentTooltip(x, y, text) {
        this._hideCommentTooltip();

        const tooltip = document.createElement('div');
        tooltip.className = 'annotation-comment-tooltip';

        // Position to the right of the marker, clamped to canvas bounds.
        let tipLeft = x + 20;
        if (tipLeft + 200 > this._canvasWidth) {
            tipLeft = x - 220;
        }
        let tipTop = y - 10;
        if (tipTop < 0) {
            tipTop = 0;
        }
        tooltip.style.left = tipLeft + 'px';
        tooltip.style.top = tipTop + 'px';
        tooltip.textContent = text;

        this._wrapperEl.appendChild(tooltip);
        this._commentTooltip = tooltip;
    }

    /** Hide the comment tooltip if visible. */
    _hideCommentTooltip() {
        if (this._commentTooltip) {
            this._commentTooltip.remove();
            this._commentTooltip = null;
        }
    }

    // ──────────────────────────────────────────────
    //  Page state management
    // ──────────────────────────────────────────────

    /**
     * Load annotations from JSON with custom property preservation.
     *
     * Fabric.js v6 fromObject() does not preserve custom properties like
     * annotationType, annotationText, stampType during deserialization.
     * This method uses two strategies to ensure they survive:
     *   1. A reviver callback that copies custom props during deserialization.
     *   2. A post-load fallback that re-applies them by index from a deep copy.
     *
     * @param {object} json The Fabric JSON to load.
     * @returns {Promise<void>}
     */
    async _loadFromJSONWithCustomProps(json) {
        // Deep-copy the JSON so loadFromJSON cannot mutate our source data.
        const safeCopy = JSON.parse(JSON.stringify(json));

        // Strategy 1: reviver callback — Fabric.js v6 calls this for each object
        // with (serialisedObj, fabricInstance) after fromObject() creates the instance.
        const reviver = (serialisedObj, fabricObj) => {
            CUSTOM_PROPS.forEach((prop) => {
                if (serialisedObj[prop] !== undefined) {
                    fabricObj[prop] = serialisedObj[prop];
                }
            });
        };

        await this._canvas.loadFromJSON(safeCopy, reviver);

        // Strategy 2: fallback — in case the reviver signature changed or
        // didn't fire, walk the objects again using the untouched deep copy.
        const objects = this._canvas.getObjects();
        if (json.objects && objects.length === json.objects.length) {
            for (let i = 0; i < objects.length; i++) {
                CUSTOM_PROPS.forEach((prop) => {
                    if (json.objects[i][prop] !== undefined) {
                        objects[i][prop] = json.objects[i][prop];
                    }
                });
            }
        }

    }

    /** Save the current canvas state for the current page. */
    _saveCurrentPageState() {
        if (!this._canvas) {
            return;
        }
        const json = this._canvas.toObject(CUSTOM_PROPS);
        if (json.objects && json.objects.length > 0) {
            // Store viewport dimensions alongside annotations for PDF flattening.
            json._viewportWidth = this._canvasWidth;
            json._viewportHeight = this._canvasHeight;
            this._pageAnnotations.set(this._currentPageNum, json);
        } else {
            this._pageAnnotations.delete(this._currentPageNum);
        }
    }

    /**
     * Get stored viewport dimensions for all annotated pages.
     *
     * @returns {Map<number, {width: number, height: number}>}
     */
    getPageDimensions() {
        this._saveCurrentPageState();
        const dims = new Map();
        for (const [pageNum, json] of this._pageAnnotations) {
            if (json._viewportWidth && json._viewportHeight) {
                dims.set(pageNum, {
                    width: json._viewportWidth,
                    height: json._viewportHeight,
                });
            }
        }
        return dims;
    }

    /** Mark all objects on the canvas as non-selectable but hoverable (for tooltips). */
    _markAllObjectsReadOnly() {
        if (!this._canvas) {
            return;
        }
        this._canvas.forEachObject((obj) => {
            obj.selectable = false;
            obj.evented = true; // Keep hover events for comment tooltips.
            obj.hoverCursor = 'default';
        });
    }

    /** Notify all change callbacks. */
    _notifyChange() {
        this._onChangeCallbacks.forEach((cb) => cb());
    }

    /** Notify all selection change callbacks. */
    _notifySelectionChange() {
        this._onSelectionChangeCallbacks.forEach((cb) => cb());
    }
}
