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
import SegComments from 'local_unifiedgrader/components/seg_comments';
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
    const userid = parseInt(container.dataset.userid, 10);
    const attempt = Number.isNaN(parseInt(container.dataset.attempt, 10))
        ? -1 : parseInt(container.dataset.attempt, 10);

    // Find the PDF viewer component element.
    const viewerEl = container.querySelector('[data-region="pdf-viewer-component"]');
    if (!viewerEl) {
        return;
    }

    // Ensure read-only flag is set on the element (PdfViewer reads this in create()).
    viewerEl.dataset.readonly = '1';

    let files = [];
    try {
        files = JSON.parse(container.dataset.pdffiles || '[]');
    } catch (e) {
        files = [];
    }

    // Reactive parent for the read-only components. PdfViewer only needs `loaded`;
    // the read-only SegComments reads activity/submission (cmid, type, userid,
    // attempt, files) to fetch and anchor the grader's marks. No mutations — the
    // student view never changes state. Root values must be objects (Moodle's
    // StateManager can't proxy primitives).
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
            loaded: {id: 'loaded', value: true},
            activity: {cmid, type: 'assign'},
            submission: {userid, attemptnumber: attempt, files},
        },
        mutations: {},
    });

    // Create and register the PdfViewer component.
    const pdfViewer = new PdfViewer({
        element: viewerEl,
        reactive,
    });

    // Give the viewer its file context so each page wrapper carries the real
    // {files}.id (SegComments matches marks to pages by it). Read-only skips the
    // grade-gated backend annotation load, so this only sets context — no fetch.
    pdfViewer.setFileContext(cmid, userid, fileid);

    // Wait for the PDF to load.
    await pdfViewer.loadPdf(pdfUrl);

    // Always load annotation overlays for the interactive viewer —
    // comment markers display as icons with hover tooltips for the text.
    await loadAnnotationsForFile(pdfViewer, cmid, fileid, attempt);

    // Render the grader's segment comments read-only in the margin (column) view —
    // the SAME component the grader uses (highlights, strikethroughs, numbered
    // colour pins, and margin cards joined by leader lines), minus the editing UI.
    mountSegComments(container, reactive);

    // Handle file switching (multiple PDFs).
    const fileSelector = container.querySelector('[data-action="file-selector"]');
    if (fileSelector) {
        fileSelector.addEventListener('change', async(e) => {
            const newFileId = parseInt(e.target.value, 10);
            const selectedOption = e.target.selectedOptions[0];
            const url = selectedOption.dataset.url;

            // Reset the PDF viewer URL to force reload.
            pdfViewer._currentUrl = null;
            pdfViewer.setFileContext(cmid, userid, newFileId);
            await pdfViewer.loadPdf(url);

            // Always load annotation overlays for hover tooltips. The file
            // selector only switches files within the same attempt.
            await loadAnnotationsForFile(pdfViewer, cmid, newFileId, attempt);
        });
    }
};

/**
 * Mount the grader's SegComments component read-only. It expects a
 * [data-region="preview-content"] root that contains the page wrappers plus a
 * [data-region="seg-comments"] host element; the student template has neither,
 * so scaffold them around the existing PDF viewer wrapper. The data-readonly flag
 * makes SegComments skip its toolbar and placement listeners.
 *
 * @param {HTMLElement} container The feedback-viewer container.
 * @param {Reactive} reactive The shared reactive (carries activity/submission).
 */
function mountSegComments(container, reactive) {
    const pdfWrapper = container.querySelector('[data-region="pdf-viewer-wrapper"]');
    if (!pdfWrapper) {
        return;
    }
    let preview = container.querySelector('[data-region="preview-content"]');
    if (!preview) {
        preview = document.createElement('div');
        preview.dataset.region = 'preview-content';
        pdfWrapper.parentNode.insertBefore(preview, pdfWrapper);
        preview.appendChild(pdfWrapper);
    }
    let segEl = preview.querySelector('[data-region="seg-comments"]');
    if (!segEl) {
        segEl = document.createElement('div');
        segEl.dataset.region = 'seg-comments';
        segEl.dataset.readonly = '1';
        preview.insertBefore(segEl, preview.firstChild);
    }
    // eslint-disable-next-line no-new
    new SegComments({element: segEl, reactive});
}

/**
 * Load student annotations for a file and apply them to the PDF viewer.
 *
 * Uses PdfViewer-level annotation API (setPageAnnotations + refreshRenderedAnnotations)
 * which works with the continuous-scroll multi-page architecture.
 *
 * @param {PdfViewer} pdfViewer The PDF viewer instance.
 * @param {number} cmid Course module ID.
 * @param {number} fileid File ID.
 * @param {number} attempt Attempt number currently displayed, or -1 for latest.
 */
async function loadAnnotationsForFile(pdfViewer, cmid, fileid, attempt) {
    try {
        const annotations = await loadStudentAnnotations(cmid, fileid, attempt);

        annotations.forEach((annot) => {
            try {
                const json = JSON.parse(annot.annotationdata);
                pdfViewer.setPageAnnotations(annot.pagenum, json);
            } catch (e) {
                window.console.warn('[feedback_viewer] Invalid annotation JSON for page', annot.pagenum, e);
            }
        });

        // Refresh annotation overlays on all currently rendered pages.
        if (annotations.length > 0) {
            await pdfViewer.refreshRenderedAnnotations();
        }
    } catch (err) {
        window.console.error('[feedback_viewer] Failed to load annotations:', err);
    }
}

