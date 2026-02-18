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

import {
    TOOLS, CUSTOM_PROPS, DEFAULT_COLOR, SHAPES,
    createCommentMarker, createHighlight, createStamp,
    createShapeRect, createShapeEllipse, createShapeLine, createShapeArrow,
} from 'local_unifiedgrader/annotation/types';

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
        this._brushWidth = 3;
        /** @type {string} */
        this._currentShape = SHAPES.RECT;
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

        // Shape drag state.
        /** @type {?object} */
        this._tempShape = null;
        /** @type {?object} */
        this._lastPointer = null;

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
        /** @type {Function[]} */
        this._onToolChangeCallbacks = [];

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

        // Text-select mode: make Fabric.js canvas fully transparent to events
        // so the PDF.js text layer underneath can handle text selection.
        if (tool === TOOLS.TEXT_SELECT) {
            this._canvas.isDrawingMode = false;
            this._canvas.discardActiveObject();
            this._canvas.forEachObject((obj) => {
                obj.selectable = false;
                obj.evented = false;
            });
            // Let pointer events pass through the Fabric.js upper canvas.
            const upper = this._canvas.upperCanvasEl;
            if (upper) {
                upper.style.pointerEvents = 'none';
            }
            this._canvas.requestRenderAll();
            this._notifyToolChange(tool);
            return;
        }

        // Re-enable pointer events on the Fabric upper canvas for all other tools.
        const upper = this._canvas.upperCanvasEl;
        if (upper) {
            upper.style.pointerEvents = 'auto';
        }

        // Toggle Fabric.js drawing mode for pen tool.
        if (tool === TOOLS.PEN) {
            this._canvas.isDrawingMode = true;
            this._canvas.freeDrawingBrush = new this._fabric.PencilBrush(this._canvas);
            this._canvas.freeDrawingBrush.color = this._currentColor;
            this._canvas.freeDrawingBrush.width = this._brushWidth;
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
        this._notifyToolChange(tool);
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
     * Set the pen brush width.
     *
     * @param {number} width Brush width in pixels.
     */
    setBrushWidth(width) {
        this._brushWidth = width;
        if (this._canvas && this._canvas.isDrawingMode && this._canvas.freeDrawingBrush) {
            this._canvas.freeDrawingBrush.width = width;
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
     * Set the active shape type.
     *
     * @param {string} shapeType Key from SHAPES (RECT, CIRCLE, ARROW, LINE).
     */
    setShapeType(shapeType) {
        this._currentShape = shapeType;
    }

    /**
     * Resize the Fabric canvas to match the rendered PDF page.
     *
     * When rescale is true (e.g. after a zoom change), existing annotation
     * objects are scaled proportionally so they stay aligned with the PDF.
     *
     * @param {number} width Display width in pixels.
     * @param {number} height Display height in pixels.
     * @param {boolean} rescale Scale existing objects to the new dimensions.
     */
    setPageSize(width, height, rescale = false) {
        const oldW = this._canvasWidth;
        const oldH = this._canvasHeight;
        this._canvasWidth = width;
        this._canvasHeight = height;
        if (!this._canvas) {
            return;
        }
        this._canvas.setDimensions({width: width, height: height});

        // Scale existing objects to the new dimensions (e.g. after zoom).
        if (rescale && oldW > 0 && oldH > 0) {
            const scaleX = width / oldW;
            const scaleY = height / oldH;
            if (Math.abs(scaleX - 1) > 0.001 || Math.abs(scaleY - 1) > 0.001) {
                const objects = this._canvas.getObjects();
                if (objects.length > 0) {
                    this._scaleObjects(objects, scaleX, scaleY);
                }
            }
        }

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
     * Register a callback for tool changes.
     *
     * @param {Function} callback Called with the new tool name when the tool changes.
     */
    onToolChange(callback) {
        this._onToolChangeCallbacks.push(callback);
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

        // Trigger save when objects are moved, resized, or rotated.
        this._canvas.on('object:modified', () => this._notifyChange());
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
            case TOOLS.SHAPE:
                this._startShape(pointer);
                break;
        }
    }

    /**
     * Handle mouse move for highlight drag.
     *
     * @param {object} opt Fabric event options.
     */
    _onMouseMove(opt) {
        if (!this._isDragging) {
            return;
        }

        const pointer = this._canvas.getViewportPoint(opt.e);

        if (this._currentTool === TOOLS.HIGHLIGHT && this._tempRect) {
            const left = Math.min(this._dragStart.x, pointer.x);
            const top = Math.min(this._dragStart.y, pointer.y);
            const width = Math.abs(pointer.x - this._dragStart.x);
            const height = Math.abs(pointer.y - this._dragStart.y);
            this._tempRect.set({left, top, width, height});
            this._canvas.requestRenderAll();
        } else if (this._currentTool === TOOLS.SHAPE && this._tempShape) {
            this._lastPointer = {x: pointer.x, y: pointer.y};
            this._updateTempShape(pointer);
            this._canvas.requestRenderAll();
        }
    }

    /**
     * Handle mouse up to finalise highlight drag.
     */
    _onMouseUp() {
        if (!this._isDragging) {
            return;
        }
        this._isDragging = false;

        if (this._currentTool === TOOLS.HIGHLIGHT && this._tempRect) {
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
        } else if (this._currentTool === TOOLS.SHAPE && this._tempShape) {
            this._finalizeShape();
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
     * Start drawing a shape via drag.
     *
     * @param {object} pointer {x, y} canvas position.
     */
    _startShape(pointer) {
        this._isDragging = true;
        this._dragStart = {x: pointer.x, y: pointer.y};
        this._lastPointer = {x: pointer.x, y: pointer.y};

        const color = this._currentColor;
        const type = this._currentShape;

        if (type === SHAPES.RECT) {
            this._tempShape = createShapeRect(this._fabric, pointer.x, pointer.y, 0, 0, color);
        } else if (type === SHAPES.CIRCLE) {
            this._tempShape = createShapeEllipse(this._fabric, pointer.x, pointer.y, 0, 0, color);
        } else {
            // LINE and ARROW both preview as a simple line during drag.
            this._tempShape = createShapeLine(this._fabric, pointer.x, pointer.y, pointer.x, pointer.y, color);
        }

        if (this._tempShape) {
            this._tempShape.selectable = false;
            this._tempShape.evented = false;
            this._canvas.add(this._tempShape);
        }
    }

    /**
     * Update the temporary shape during drag.
     *
     * @param {object} pointer Current mouse position {x, y}.
     */
    _updateTempShape(pointer) {
        const shape = this._tempShape;
        const type = this._currentShape;

        if (type === SHAPES.RECT) {
            const left = Math.min(this._dragStart.x, pointer.x);
            const top = Math.min(this._dragStart.y, pointer.y);
            const width = Math.abs(pointer.x - this._dragStart.x);
            const height = Math.abs(pointer.y - this._dragStart.y);
            shape.set({left, top, width, height});
        } else if (type === SHAPES.CIRCLE) {
            const left = Math.min(this._dragStart.x, pointer.x);
            const top = Math.min(this._dragStart.y, pointer.y);
            const width = Math.abs(pointer.x - this._dragStart.x);
            const height = Math.abs(pointer.y - this._dragStart.y);
            shape.set({left, top, rx: width / 2, ry: height / 2});
        } else {
            // LINE and ARROW — update endpoint.
            shape.set({x1: this._dragStart.x, y1: this._dragStart.y, x2: pointer.x, y2: pointer.y});
        }
    }

    /**
     * Finalise the shape after drag ends.
     */
    _finalizeShape() {
        const shape = this._tempShape;
        this._tempShape = null;

        if (!shape) {
            return;
        }

        const type = this._currentShape;

        // Minimum size check for rect and circle.
        if (type === SHAPES.RECT || type === SHAPES.CIRCLE) {
            const w = shape.width || (shape.rx ? shape.rx * 2 : 0);
            const h = shape.height || (shape.ry ? shape.ry * 2 : 0);
            if (w < 5 && h < 5) {
                this._canvas.remove(shape);
                return;
            }
        }

        // Minimum length check for line and arrow.
        if (type === SHAPES.LINE || type === SHAPES.ARROW) {
            const dx = this._lastPointer.x - this._dragStart.x;
            const dy = this._lastPointer.y - this._dragStart.y;
            if (Math.sqrt(dx * dx + dy * dy) < 5) {
                this._canvas.remove(shape);
                return;
            }
        }

        // For arrow: replace the temp line with a proper arrow path.
        if (type === SHAPES.ARROW) {
            this._canvas.remove(shape);
            const arrow = createShapeArrow(
                this._fabric,
                this._dragStart.x, this._dragStart.y,
                this._lastPointer.x, this._lastPointer.y,
                this._currentColor
            );
            this._canvas.add(arrow);
            arrow.setCoords();
            this._undoStack.push({type: 'add', object: arrow});
            this._redoStack = [];
            this._notifyChange();
            return;
        }

        // Finalise rect, circle, line.
        shape.setCoords();
        this._undoStack.push({type: 'add', object: shape});
        this._redoStack = [];
        this._notifyChange();
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

        // Scale annotations to match current viewport if dimensions differ
        // from when they were saved. This ensures annotations remain aligned
        // across different screen sizes (teacher vs student, resize, etc.).
        this._scaleToCurrentViewport(json, objects);
    }

    /**
     * Scale loaded annotation objects to match the current canvas dimensions.
     *
     * Annotations are stored with absolute pixel coordinates relative to the
     * viewport size at the time of creation. If the current canvas differs,
     * all object positions, sizes, and strokes are scaled proportionally.
     *
     * @param {object} json The source Fabric JSON (with _viewportWidth/_viewportHeight).
     * @param {Array} objects The Fabric objects on the canvas.
     */
    _scaleToCurrentViewport(json, objects) {
        const savedW = json._viewportWidth;
        const savedH = json._viewportHeight;

        // No stored dimensions or no current dimensions — nothing to scale.
        if (!savedW || !savedH || !this._canvasWidth || !this._canvasHeight) {
            return;
        }

        const scaleX = this._canvasWidth / savedW;
        const scaleY = this._canvasHeight / savedH;

        // Skip if dimensions match (within floating-point tolerance).
        if (Math.abs(scaleX - 1) < 0.001 && Math.abs(scaleY - 1) < 0.001) {
            return;
        }

        this._scaleObjects(objects, scaleX, scaleY);
    }

    /**
     * Scale an array of Fabric objects by the given factors.
     *
     * Positions are scaled independently (scaleX for left, scaleY for top)
     * to handle non-uniform aspect ratio changes. Object dimensions (size,
     * stroke, font) use a uniform scale (min of scaleX, scaleY) to avoid
     * visual distortion.
     *
     * @param {Array} objects Fabric.js objects to scale.
     * @param {number} scaleX Horizontal scale factor.
     * @param {number} scaleY Vertical scale factor.
     */
    _scaleObjects(objects, scaleX, scaleY) {
        const uniformScale = Math.min(scaleX, scaleY);

        objects.forEach((obj) => {
            // Scale position.
            obj.left *= scaleX;
            obj.top *= scaleY;

            // Scale object size uniformly to avoid distortion.
            obj.scaleX = (obj.scaleX || 1) * uniformScale;
            obj.scaleY = (obj.scaleY || 1) * uniformScale;

            // Scale stroke width proportionally.
            if (obj.strokeWidth) {
                obj.strokeWidth *= uniformScale;
            }

            // Scale radius for circles/ellipses.
            if (obj.radius) {
                obj.radius *= uniformScale;
            }
            if (obj.rx) {
                obj.rx *= uniformScale;
            }
            if (obj.ry) {
                obj.ry *= uniformScale;
            }

            // Scale line endpoints.
            if (obj.x1 !== undefined) {
                obj.x1 *= scaleX;
                obj.y1 *= scaleY;
                obj.x2 *= scaleX;
                obj.y2 *= scaleY;
            }

            // Scale font size for text objects.
            if (obj.fontSize) {
                obj.fontSize *= uniformScale;
            }

            obj.setCoords();
        });

        this._canvas.requestRenderAll();
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

    /**
     * Notify all tool change callbacks.
     *
     * @param {string} tool The new active tool name.
     */
    _notifyToolChange(tool) {
        this._onToolChangeCallbacks.forEach((cb) => cb(tool));
    }
}
