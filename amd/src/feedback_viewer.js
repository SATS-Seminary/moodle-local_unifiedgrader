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
 * Feedback viewer — lightweight JS initializer for the student read-only
 * annotation view. When a flattened annotated PDF is available, it loads
 * that directly (annotations are baked in). Otherwise, falls back to
 * loading the original PDF with a Fabric.js annotation overlay.
 *
 * @module     local_unifiedgrader/feedback_viewer
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {Reactive} from 'core/reactive';
import PdfViewer from 'local_unifiedgrader/components/pdf_viewer';
import {loadStudentAnnotations} from 'local_unifiedgrader/annotation/persistence';

/**
 * Initialise the feedback viewer.
 */
export const init = async() => {
    const container = document.querySelector('[data-region="feedback-viewer"]');
    if (!container) {
        return;
    }

    const cmid = parseInt(container.dataset.cmid, 10);
    const fileid = parseInt(container.dataset.fileid, 10);
    const pdfUrl = container.dataset.pdfurl;

    // Find the PDF viewer component element.
    const viewerEl = container.querySelector('[data-region="pdf-viewer-component"]');
    if (!viewerEl) {
        return;
    }

    // Ensure read-only flag is set on the element (PdfViewer reads this in create()).
    viewerEl.dataset.readonly = '1';

    // Create a minimal reactive instance — PdfViewer extends BaseComponent which
    // requires a Reactive parent. We use a trivial state with no mutations.
    const reactive = new Reactive({
        name: 'feedback_viewer',
        eventName: 'feedback_viewer:stateChanged',
        eventDispatch: (detail, target) => {
            (target ?? viewerEl).dispatchEvent(new CustomEvent('feedback_viewer:stateChanged', {
                bubbles: true,
                detail,
            }));
        },
        state: {
            // Minimal state — BaseComponent needs at least an initial state.
            loaded: {id: 'loaded', value: true},
        },
        mutations: {},
    });

    // Create and register the PdfViewer component.
    const pdfViewer = new PdfViewer({
        element: viewerEl,
        reactive,
    });

    // Wait for the PDF to load.
    await pdfViewer.loadPdf(pdfUrl);

    // Always load annotation overlays for the interactive viewer —
    // comment markers display as icons with hover tooltips for the text.
    await loadAnnotationsForFile(pdfViewer, cmid, fileid);

    // Handle file switching (multiple PDFs).
    const fileSelector = container.querySelector('[data-action="file-selector"]');
    if (fileSelector) {
        fileSelector.addEventListener('change', async(e) => {
            const newFileId = parseInt(e.target.value, 10);
            const selectedOption = e.target.selectedOptions[0];
            const url = selectedOption.dataset.url;

            // Reset the PDF viewer URL to force reload.
            pdfViewer._currentUrl = null;
            await pdfViewer.loadPdf(url);

            // Always load annotation overlays for hover tooltips.
            await loadAnnotationsForFile(pdfViewer, cmid, newFileId);
        });
    }
};

/**
 * Load student annotations for a file and apply them to the PDF viewer's annotation layer.
 *
 * @param {PdfViewer} pdfViewer The PDF viewer instance.
 * @param {number} cmid Course module ID.
 * @param {number} fileid File ID.
 */
async function loadAnnotationsForFile(pdfViewer, cmid, fileid) {
    const annotationLayer = pdfViewer.getAnnotationLayer();
    if (!annotationLayer) {
        return;
    }

    try {
        const annotations = await loadStudentAnnotations(cmid, fileid);

        annotations.forEach((annot) => {
            try {
                const json = JSON.parse(annot.annotationdata);
                annotationLayer.setPageAnnotations(annot.pagenum, json);
            } catch (e) {
                window.console.warn('[feedback_viewer] Invalid annotation JSON for page', annot.pagenum, e);
            }
        });

        // Reload the current page to display the annotations.
        if (annotations.length > 0) {
            await annotationLayer.reloadCurrentPage();
        }
    } catch (err) {
        window.console.error('[feedback_viewer] Failed to load annotations:', err);
    }
}
