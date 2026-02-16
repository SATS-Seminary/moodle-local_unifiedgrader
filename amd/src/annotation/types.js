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
    COMMENT: 'comment',
    HIGHLIGHT: 'highlight',
    PEN: 'pen',
    STAMP: 'stamp',
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
 * Custom properties stored on every Fabric.js annotation object.
 * These are included in toJSON() serialization.
 *
 * @type {string[]}
 */
export const CUSTOM_PROPS = ['annotationType', 'annotationText', 'stampType'];

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
