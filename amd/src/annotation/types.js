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
 * Annotation type constants and Fabric.js object factory functions.
 *
 * @module     local_unifiedgrader/annotation/types
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Available annotation tools.
 *
 * @type {object}
 */
export const TOOLS = {
    SELECT: 'select',
    TEXT_SELECT: 'textselect',
    COMMENT: 'comment',
    HIGHLIGHT: 'highlight',
    TEXT_HIGHLIGHT: 'texthighlight',
    STRIKETHROUGH: 'strikethrough',
    PEN: 'pen',
    STAMP: 'stamp',
    SHAPE: 'shape',
};

/**
 * Annotation colour palette (matches Moodle's editpdf convention).
 *
 * @type {object}
 */
export const COLORS = {
    RED: '#EF4540',
    YELLOW: '#FFCF35',
    GREEN: '#98CA3E',
    BLUE: '#7D9FD3',
    BLACK: '#333333',
};

/** @type {string} Default annotation colour. */
export const DEFAULT_COLOR = COLORS.RED;

/**
 * Stamp types with display characters.
 *
 * @type {object}
 */
export const STAMPS = {
    CHECK: '\u2713',
    CROSS: '\u2717',
    QUESTION: '?',
};

/**
 * Shape types available in the shape tool popout.
 *
 * @type {object}
 */
export const SHAPES = {
    RECT: 'rect',
    CIRCLE: 'circle',
    ARROW: 'arrow',
    LINE: 'line',
};

/**
 * Custom properties stored on every Fabric.js annotation object.
 * These are included in toJSON() serialization.
 *
 * @type {string[]}
 */
export const CUSTOM_PROPS = ['annotationType', 'annotationText', 'stampType', 'shapeType'];

/** @type {number} Maximum number of Fabric.js objects allowed per page. */
const MAX_OBJECTS_PER_PAGE = 500;

/** @type {Set<string>} Allowed Fabric.js object types for annotation data.
 * Fabric.js v6 uses PascalCase class names (e.g. FabricText) but serializes
 * with shorter type strings (e.g. "Text"). Both forms must be accepted. */
const ALLOWED_FABRIC_TYPES = new Set([
    'Rect', 'Circle', 'Ellipse', 'Path', 'Line',
    'FabricText', 'Text',
    'Group', 'IText', 'Textbox', 'Polygon', 'Polyline', 'Image',
]);

/**
 * Validate Fabric.js annotation JSON before loading.
 *
 * Guards against malformed or adversarial annotation data that could cause
 * browser DoS (excessive objects, extreme coordinates) or unexpected types.
 *
 * @param {object} json The Fabric.js JSON to validate.
 * @returns {boolean} True if the JSON is safe to load.
 */
export function validateAnnotationJson(json) {
    if (!json || typeof json !== 'object') {
        return false;
    }
    if (!Array.isArray(json.objects)) {
        return false;
    }
    if (json.objects.length > MAX_OBJECTS_PER_PAGE) {
        window.console.warn('[annotation] Rejected: too many objects (' + json.objects.length + ')');
        return false;
    }
    for (const obj of json.objects) {
        if (!obj || typeof obj !== 'object' || !ALLOWED_FABRIC_TYPES.has(obj.type)) {
            window.console.warn('[annotation] Rejected: unknown object type "' + (obj?.type) + '"');
            return false;
        }
    }
    return true;
}

/**
 * SVG path for a speech-bubble comment icon (24×22 viewbox).
 * Rounded rectangle body with a triangular tail at the bottom-left.
 *
 * @type {string}
 */
const COMMENT_BUBBLE_PATH =
    'M3 0 C1.34 0 0 1.34 0 3 L0 13 C0 14.66 1.34 16 3 16'
    + ' L6 16 L6 21 L11 16 L21 16 C22.66 16 24 14.66 24 13'
    + ' L24 3 C24 1.34 22.66 0 21 0 Z';

/**
 * Create a comment marker (speech-bubble icon).
 *
 * @param {object} fabric The Fabric.js library namespace.
 * @param {number} x Centre x position.
 * @param {number} y Centre y position.
 * @param {string} color Fill colour.
 * @param {string} text Comment text.
 * @returns {object} Fabric.js Path object.
 */
export function createCommentMarker(fabric, x, y, color, text) {
    const marker = new fabric.Path(COMMENT_BUBBLE_PATH, {
        left: x,
        top: y,
        fill: color,
        stroke: '#ffffff',
        strokeWidth: 1.5,
        originX: 'center',
        originY: 'center',
        selectable: true,
        hasControls: false,
        hasBorders: true,
        lockScalingX: true,
        lockScalingY: true,
    });
    marker.annotationType = 'comment';
    marker.annotationText = text || '';
    return marker;
}

/**
 * Create a highlight rectangle.
 *
 * @param {object} fabric The Fabric.js library namespace.
 * @param {number} left Left edge x.
 * @param {number} top Top edge y.
 * @param {number} width Rectangle width.
 * @param {number} height Rectangle height.
 * @param {string} color Fill colour.
 * @returns {object} Fabric.js Rect object.
 */
export function createHighlight(fabric, left, top, width, height, color) {
    const rect = new fabric.Rect({
        left: left,
        top: top,
        width: width,
        height: height,
        fill: color,
        opacity: 0.3,
        selectable: true,
        hasControls: true,
        lockRotation: true,
    });
    rect.annotationType = 'highlight';
    return rect;
}

/**
 * Create a text-line highlight annotation from a selection's client rects.
 *
 * The caller passes one rect per visual line of selected text — typically
 * obtained from `Range.getClientRects()` after a text-tool mouseup, then
 * translated into canvas coordinates relative to the PDF page. We render
 * one translucent Fabric.Rect per line and bundle them into a single
 * Group so the whole highlight selects/deletes/drags as a unit.
 *
 * Resize and rotate are locked because the geometry is bound to the
 * underlying text — letting the user stretch it would just visually
 * detach the annotation from the words it marks. Drag is allowed so a
 * mis-placed highlight can be nudged.
 *
 * @param {object} fabric The Fabric.js library namespace.
 * @param {Array<{left:number, top:number, width:number, height:number}>} rects One per line.
 * @param {string} color Fill colour for the highlight rectangles.
 * @returns {?object} Fabric.js Group, or null if no usable rects were passed.
 */
export function createTextHighlight(fabric, rects, color) {
    const usable = rects.filter(r => r.width > 0 && r.height > 0);
    if (usable.length === 0) {
        return null;
    }
    const shapes = usable.map(r => new fabric.Rect({
        left: r.left,
        top: r.top,
        width: r.width,
        height: r.height,
        fill: color,
        opacity: 0.35,
        originX: 'left',
        originY: 'top',
    }));
    const group = new fabric.Group(shapes, {
        selectable: true,
        hasControls: false,
        lockRotation: true,
        lockScalingX: true,
        lockScalingY: true,
    });
    group.annotationType = 'texthighlight';
    return group;
}

/**
 * Create a strikethrough annotation from a selection's client rects.
 *
 * One thin horizontal Fabric.Line per visual line of selected text,
 * positioned at each rect's vertical centre. Same resize-locked /
 * drag-enabled posture as `createTextHighlight` — the geometry is
 * meaningful relative to the underlying words.
 *
 * @param {object} fabric The Fabric.js library namespace.
 * @param {Array<{left:number, top:number, width:number, height:number}>} rects One per line.
 * @param {string} color Stroke colour for the strikethrough lines.
 * @returns {?object} Fabric.js Group, or null if no usable rects were passed.
 */
export function createTextStrikethrough(fabric, rects, color) {
    const usable = rects.filter(r => r.width > 0 && r.height > 0);
    if (usable.length === 0) {
        return null;
    }
    const lines = usable.map((r) => {
        const midY = r.top + (r.height / 2);
        return new fabric.Line(
            [r.left, midY, r.left + r.width, midY],
            {
                stroke: color,
                strokeWidth: 2,
                strokeLineCap: 'round',
                opacity: 0.85,
                originX: 'left',
                originY: 'top',
            }
        );
    });
    const group = new fabric.Group(lines, {
        selectable: true,
        hasControls: false,
        lockRotation: true,
        lockScalingX: true,
        lockScalingY: true,
    });
    group.annotationType = 'strikethrough';
    return group;
}

/**
 * Create a stamp text object.
 *
 * @param {object} fabric The Fabric.js library namespace.
 * @param {number} x Centre x position.
 * @param {number} y Centre y position.
 * @param {string} stampType Key from STAMPS (CHECK, CROSS, QUESTION).
 * @param {string} color Fill colour.
 * @returns {object} Fabric.js FabricText object.
 */
export function createStamp(fabric, x, y, stampType, color) {
    const char = STAMPS[stampType] || STAMPS.CHECK;
    const text = new fabric.FabricText(char, {
        left: x,
        top: y,
        fontSize: 28,
        fontWeight: 'bold',
        fill: color,
        fontFamily: 'Arial, sans-serif',
        originX: 'center',
        originY: 'center',
        selectable: true,
        hasControls: false,
        hasBorders: true,
        lockScalingX: true,
        lockScalingY: true,
    });
    text.annotationType = 'stamp';
    text.stampType = stampType;
    return text;
}

/**
 * Create a shape rectangle (stroke outline, no fill).
 *
 * @param {object} fabric The Fabric.js library namespace.
 * @param {number} left Left edge x.
 * @param {number} top Top edge y.
 * @param {number} width Rectangle width.
 * @param {number} height Rectangle height.
 * @param {string} color Stroke colour.
 * @returns {object} Fabric.js Rect object.
 */
export function createShapeRect(fabric, left, top, width, height, color) {
    const rect = new fabric.Rect({
        left,
        top,
        width,
        height,
        fill: 'transparent',
        stroke: color,
        strokeWidth: 2,
        selectable: true,
        hasControls: true,
        hasBorders: true,
    });
    rect.annotationType = 'shape';
    rect.shapeType = SHAPES.RECT;
    return rect;
}

/**
 * Create a shape ellipse (stroke outline, no fill).
 *
 * @param {object} fabric The Fabric.js library namespace.
 * @param {number} left Left edge x of bounding box.
 * @param {number} top Top edge y of bounding box.
 * @param {number} rx Horizontal radius.
 * @param {number} ry Vertical radius.
 * @param {string} color Stroke colour.
 * @returns {object} Fabric.js Ellipse object.
 */
export function createShapeEllipse(fabric, left, top, rx, ry, color) {
    const ellipse = new fabric.Ellipse({
        left,
        top,
        rx,
        ry,
        fill: 'transparent',
        stroke: color,
        strokeWidth: 2,
        selectable: true,
        hasControls: true,
        hasBorders: true,
    });
    ellipse.annotationType = 'shape';
    ellipse.shapeType = SHAPES.CIRCLE;
    return ellipse;
}

/**
 * Create a shape line.
 *
 * @param {object} fabric The Fabric.js library namespace.
 * @param {number} x1 Start x.
 * @param {number} y1 Start y.
 * @param {number} x2 End x.
 * @param {number} y2 End y.
 * @param {string} color Stroke colour.
 * @returns {object} Fabric.js Line object.
 */
export function createShapeLine(fabric, x1, y1, x2, y2, color) {
    const line = new fabric.Line([x1, y1, x2, y2], {
        stroke: color,
        strokeWidth: 2,
        strokeLineCap: 'round',
        selectable: true,
        hasControls: true,
        hasBorders: true,
    });
    line.annotationType = 'shape';
    line.shapeType = SHAPES.LINE;
    return line;
}

/**
 * Create a shape arrow (line shaft with filled arrowhead).
 *
 * @param {object} fabric The Fabric.js library namespace.
 * @param {number} x1 Start x.
 * @param {number} y1 Start y.
 * @param {number} x2 End x (arrowhead tip).
 * @param {number} y2 End y (arrowhead tip).
 * @param {string} color Stroke and fill colour.
 * @returns {object} Fabric.js Path object.
 */
export function createShapeArrow(fabric, x1, y1, x2, y2, color) {
    const headLen = 12;
    const angle = Math.atan2(y2 - y1, x2 - x1);

    // Arrowhead triangle corners.
    const ax1 = x2 - headLen * Math.cos(angle - Math.PI / 6);
    const ay1 = y2 - headLen * Math.sin(angle - Math.PI / 6);
    const ax2 = x2 - headLen * Math.cos(angle + Math.PI / 6);
    const ay2 = y2 - headLen * Math.sin(angle + Math.PI / 6);

    const pathStr = `M ${x1} ${y1} L ${x2} ${y2} M ${ax1} ${ay1} L ${x2} ${y2} L ${ax2} ${ay2} Z`;

    const arrow = new fabric.Path(pathStr, {
        fill: color,
        stroke: color,
        strokeWidth: 2,
        strokeLineCap: 'round',
        strokeLineJoin: 'round',
        selectable: true,
        hasControls: true,
        hasBorders: true,
    });
    arrow.annotationType = 'shape';
    arrow.shapeType = SHAPES.ARROW;
    return arrow;
}
