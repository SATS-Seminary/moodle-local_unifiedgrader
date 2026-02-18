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
