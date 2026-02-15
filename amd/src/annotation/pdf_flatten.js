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
 * PDF flattening — bakes Fabric.js annotation overlays into PDF pages.
 *
 * For each annotated page, renders the Fabric.js objects onto an off-screen
 * canvas, exports as a transparent PNG, and embeds it into the PDF page
 * using pdf-lib. The result is a self-contained PDF with annotations visible
 * without any JavaScript rendering.
 *
 * @module     local_unifiedgrader/annotation/pdf_flatten
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {CUSTOM_PROPS} from 'local_unifiedgrader/annotation/types';

/** @type {number} Default scale multiplier when viewport dimensions are unknown. */
const DEFAULT_SCALE = 1.5;

/**
 * Generate a flattened PDF with annotation overlays baked in.
 *
 * @param {ArrayBuffer} originalPdfBytes The original PDF file data.
 * @param {Map<number, object>} pageAnnotations Map of page number (1-based) to Fabric.js JSON.
 * @param {Map<number, {width: number, height: number}>} pageDimensions Viewport dimensions per page.
 * @param {object} fabric The Fabric.js library namespace.
 * @param {object} PDFLib The pdf-lib library namespace.
 * @returns {Promise<Uint8Array>} The flattened PDF bytes.
 */
export async function flattenAnnotatedPdf(originalPdfBytes, pageAnnotations, pageDimensions, fabric, PDFLib) {
    const pdfDoc = await PDFLib.PDFDocument.load(originalPdfBytes);
    const pages = pdfDoc.getPages();

    for (const [pageNum, fabricJson] of pageAnnotations) {
        if (!fabricJson.objects || fabricJson.objects.length === 0) {
            continue;
        }

        const pageIndex = pageNum - 1;
        if (pageIndex < 0 || pageIndex >= pages.length) {
            continue;
        }

        const pdfPage = pages[pageIndex];
        const {width: pageW, height: pageH} = pdfPage.getSize();

        // Determine canvas dimensions from stored viewport or fallback.
        let canvasW, canvasH;
        if (pageDimensions.has(pageNum)) {
            const dims = pageDimensions.get(pageNum);
            canvasW = dims.width;
            canvasH = dims.height;
        } else if (fabricJson._viewportWidth && fabricJson._viewportHeight) {
            canvasW = fabricJson._viewportWidth;
            canvasH = fabricJson._viewportHeight;
        } else {
            canvasW = Math.round(pageW * DEFAULT_SCALE);
            canvasH = Math.round(pageH * DEFAULT_SCALE);
        }

        // Render annotation layer to PNG.
        const pngBytes = await renderAnnotationPng(fabricJson, canvasW, canvasH, fabric);

        // Embed PNG and draw full-page overlay.
        const pngImage = await pdfDoc.embedPng(pngBytes);
        pdfPage.drawImage(pngImage, {
            x: 0,
            y: 0,
            width: pageW,
            height: pageH,
        });
    }

    return pdfDoc.save();
}

/**
 * Render a page's Fabric.js annotations to a transparent PNG.
 *
 * Creates an off-screen canvas, loads the annotation JSON, renders comment
 * text bubbles for comment markers, and exports as PNG.
 *
 * @param {object} fabricJson The Fabric.js canvas JSON for one page.
 * @param {number} width Canvas width in pixels.
 * @param {number} height Canvas height in pixels.
 * @param {object} fabric The Fabric.js library namespace.
 * @returns {Promise<Uint8Array>} PNG image data.
 */
async function renderAnnotationPng(fabricJson, width, height, fabric) {
    const offscreen = document.createElement('canvas');
    offscreen.width = width;
    offscreen.height = height;

    const fabricCanvas = new fabric.StaticCanvas(offscreen, {
        width: width,
        height: height,
        renderOnAddRemove: false,
    });

    // Deep copy to prevent mutation of the source data.
    const safeCopy = JSON.parse(JSON.stringify(fabricJson));

    // Load annotation objects with custom property preservation.
    const reviver = (serialisedObj, fabricObj) => {
        CUSTOM_PROPS.forEach((prop) => {
            if (serialisedObj[prop] !== undefined) {
                fabricObj[prop] = serialisedObj[prop];
            }
        });
    };

    await fabricCanvas.loadFromJSON(safeCopy, reviver);

    // Post-load fallback: re-apply custom props by index.
    const objects = fabricCanvas.getObjects();
    if (fabricJson.objects && objects.length === fabricJson.objects.length) {
        for (let i = 0; i < objects.length; i++) {
            CUSTOM_PROPS.forEach((prop) => {
                if (fabricJson.objects[i][prop] !== undefined) {
                    objects[i][prop] = fabricJson.objects[i][prop];
                }
            });
        }
    }

    // Render comment text bubbles next to comment markers.
    const commentLabels = addCommentTextLabels(fabricCanvas, objects, fabric);

    fabricCanvas.renderAll();

    // Export as transparent PNG.
    const pngDataUrl = offscreen.toDataURL('image/png');
    const pngBase64 = pngDataUrl.split(',')[1];
    const pngBytes = Uint8Array.from(atob(pngBase64), (c) => c.charCodeAt(0));

    // Clean up.
    commentLabels.forEach((label) => fabricCanvas.remove(label));
    fabricCanvas.dispose();

    return pngBytes;
}

/**
 * Add temporary text labels next to comment markers so their text is
 * visible in the flattened PDF (comment text is normally only shown
 * on hover via tooltips).
 *
 * @param {object} fabricCanvas The Fabric.js canvas instance.
 * @param {Array} objects The canvas objects.
 * @param {object} fabric The Fabric.js library namespace.
 * @returns {Array} The temporary label objects (for cleanup).
 */
function addCommentTextLabels(fabricCanvas, objects, fabric) {
    const labels = [];

    for (const obj of objects) {
        if (obj.annotationType !== 'comment' || !obj.annotationText) {
            continue;
        }

        const label = new fabric.FabricText(obj.annotationText, {
            left: obj.left + 18,
            top: obj.top - 10,
            fontSize: 11,
            fill: '#333333',
            backgroundColor: '#fffde7',
            padding: 4,
            fontFamily: 'Arial, sans-serif',
            selectable: false,
            evented: false,
        });

        fabricCanvas.add(label);
        labels.push(label);
    }

    return labels;
}
