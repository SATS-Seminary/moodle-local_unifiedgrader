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
 * Preview panel component - displays student submission content.
 *
 * Manages the left-panel document preview. For PDF files, delegates to the
 * PdfViewer component (PDF.js). For images and text, uses an iframe fallback.
 * Also renders a compact file selector in the right panel.
 *
 * @module     local_unifiedgrader/components/preview_panel
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent} from 'core/reactive';
import PdfViewer from 'local_unifiedgrader/components/pdf_viewer';

export default class extends BaseComponent {

    /**
     * Component creation hook.
     */
    create() {
        this.name = 'preview_panel';
        this.selectors = {
            NO_SUBMISSION: '[data-region="no-submission"]',
            PDF_VIEWER_WRAPPER: '[data-region="pdf-viewer-wrapper"]',
            DOCUMENT_PREVIEW: '[data-region="document-preview"]',
            PREVIEW_IFRAME: '[data-region="preview-iframe"]',
        };
        this._container = null;
        this._currentFileId = null;
        /** @type {?PdfViewer} */
        this._pdfViewer = null;
    }

    /**
     * Register state watchers.
     *
     * @return {Array}
     */
    getWatchers() {
        return [
            {watch: 'submission:updated', handler: this._renderSubmission},
            {watch: 'ui.loading:updated', handler: this._toggleLoading},
        ];
    }

    /**
     * Called when state is first ready.
     *
     * @param {object} state Current state.
     */
    stateReady(state) {
        // Cache reference to the main container for cross-panel file selector access.
        this._container = this.element.closest('.local-unifiedgrader-container');

        // Initialize the PDF viewer component on its wrapper element.
        const pdfViewerEl = this.getElement('[data-region="pdf-viewer"]');
        if (pdfViewerEl) {
            this._pdfViewer = new PdfViewer({
                element: pdfViewerEl,
                reactive: this.reactive,
            });
        }

        if (state.submission) {
            this._renderSubmission({state});
        }
    }

    /**
     * Render submission content.
     *
     * @param {object} args Watcher args.
     * @param {object} args.state Current state.
     */
    _renderSubmission({state}) {
        const submission = state.submission;
        const noSubEl = this.getElement(this.selectors.NO_SUBMISSION);
        const pdfWrapper = this.getElement(this.selectors.PDF_VIEWER_WRAPPER);
        const docPreview = this.getElement(this.selectors.DOCUMENT_PREVIEW);

        // Save pending annotations before switching students/submissions.
        if (this._pdfViewer) {
            this._pdfViewer.saveAnnotationsNow();
        }

        // Reset visibility.
        noSubEl.classList.add('d-none');
        noSubEl.classList.remove('d-flex');
        pdfWrapper.classList.add('d-none');
        docPreview.classList.add('d-none');
        this._currentFileId = null;

        // Reset file selector in the right panel.
        this._renderFileSelector([]);

        if (!submission || submission.status === 'nosubmission') {
            noSubEl.classList.remove('d-none');
            noSubEl.classList.add('d-flex');
            return;
        }

        // Handle files.
        const files = submission.files || [];
        if (files.length > 0) {
            this._renderFileSelector(files);

            // Auto-preview the first previewable file.
            const firstPreviewable = files.find(f => this._isPreviewable(f.mimetype));
            if (firstPreviewable) {
                this._previewFile(firstPreviewable);
            }
        }
    }

    /**
     * Render compact file selector buttons in the right panel.
     *
     * @param {Array} files Array of file objects.
     */
    _renderFileSelector(files) {
        if (!this._container) {
            return;
        }
        const wrapper = this._container.querySelector('[data-region="file-selector"]');
        const list = this._container.querySelector('[data-region="file-selector-list"]');
        if (!wrapper || !list) {
            return;
        }

        list.innerHTML = '';

        if (files.length === 0) {
            wrapper.classList.add('d-none');
            return;
        }

        wrapper.classList.remove('d-none');

        files.forEach((file) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-secondary d-flex align-items-center gap-1';
            btn.dataset.fileid = file.fileid;

            const name = document.createElement('span');
            name.className = 'small text-truncate';
            name.style.maxWidth = '180px';
            name.textContent = file.filename;

            btn.appendChild(name);

            btn.addEventListener('click', () => {
                if (this._isPreviewable(file.mimetype)) {
                    this._previewFile(file);
                } else {
                    window.open(file.url, '_blank');
                }
            });

            list.appendChild(btn);
        });
    }

    /**
     * Preview a file in the left panel.
     *
     * Routes PDF files to the PdfViewer component and other files to the iframe.
     *
     * @param {object} file File info object.
     */
    _previewFile(file) {
        const pdfWrapper = this.getElement(this.selectors.PDF_VIEWER_WRAPPER);
        const docPreview = this.getElement(this.selectors.DOCUMENT_PREVIEW);

        // Hide both viewers first.
        pdfWrapper.classList.add('d-none');
        docPreview.classList.add('d-none');

        if (file.mimetype === 'application/pdf' && this._pdfViewer) {
            // Use PDF.js viewer for PDF files.
            pdfWrapper.classList.remove('d-none');
            // Pass file context for annotation persistence.
            const state = this.reactive.state;
            this._pdfViewer.setFileContext(
                parseInt(state.activity.cmid, 10),
                parseInt(state.currentUser.id, 10),
                parseInt(file.fileid, 10),
            );
            this._pdfViewer.loadPdf(file.previewurl || file.url);
        } else {
            // Use iframe for images, text, etc.
            const iframe = this.getElement(this.selectors.PREVIEW_IFRAME);
            iframe.src = file.previewurl || file.url;
            docPreview.classList.remove('d-none');
        }

        this._currentFileId = file.fileid;

        // Highlight the active file button in the right-panel selector.
        this._highlightFileButton(file.fileid);
    }

    /**
     * Highlight the active file button in the file selector.
     *
     * @param {number|string} fileid The file ID to highlight.
     */
    _highlightFileButton(fileid) {
        if (!this._container) {
            return;
        }
        const list = this._container.querySelector('[data-region="file-selector-list"]');
        if (!list) {
            return;
        }
        list.querySelectorAll('button').forEach((btn) => {
            const isActive = btn.dataset.fileid === String(fileid);
            btn.classList.toggle('btn-outline-secondary', !isActive);
            btn.classList.toggle('btn-primary', isActive);
        });
    }

    /**
     * Check if a MIME type can be previewed inline.
     *
     * @param {string} mimetype MIME type string.
     * @return {boolean}
     */
    _isPreviewable(mimetype) {
        return [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'text/plain',
        ].includes(mimetype);
    }

    /**
     * Toggle loading state.
     *
     * @param {object} args Watcher args.
     * @param {object} args.state Current state.
     */
    _toggleLoading({state}) {
        this.element.style.opacity = state.ui.loading ? '0.5' : '1';
        this.element.style.pointerEvents = state.ui.loading ? 'none' : '';
    }
}
