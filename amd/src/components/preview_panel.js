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
import {getString} from 'core/str';
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

        if (!submission || submission.status === 'nosubmission' || !submission.status) {
            noSubEl.classList.remove('d-none');
            noSubEl.classList.add('d-flex');
            return;
        }

        // Handle files.
        const files = submission.files || [];
        const isForum = this.reactive.state.activity?.type === 'forum';
        // Forums always have post content; for other types, rely on the backend flag
        // that checks whether non-file submission plugins produced content.
        const hasContent = isForum
            ? (submission.status && submission.status !== 'nosubmission')
            : !!submission.hascontent;

        if (files.length > 0) {
            this._renderFileSelector(files, hasContent, isForum);

            // Auto-preview the first previewable file.
            const firstPreviewable = files.find(f => this._isPreviewable(f));
            if (firstPreviewable) {
                this._previewFile(firstPreviewable);
                return;
            }
        }

        // Show submission content in an iframe via a proper Moodle page.
        // This ensures submission plugin CSS, JS, and AMD modules load correctly
        // (e.g. ytsubmission's YouTube player and timestamped feedback interface).
        if (hasContent) {
            this._showSubmissionContent();
            return;
        }

        // Fallback: submission exists but has no previewable files and no
        // non-file content. Show submission content iframe anyway — submission
        // plugins (e.g. audio recording) may render their own player there.
        if (files.length === 0 && submission.status === 'submitted') {
            this._showSubmissionContent();
            return;
        }
    }

    /**
     * Render compact file selector buttons in the right panel.
     *
     * @param {Array} files Array of file objects.
     * @param {boolean} hasContent Whether the submission has text content (e.g. forum posts).
     * @param {boolean} isForum Whether the current activity is a forum.
     */
    _renderFileSelector(files, hasContent = false, isForum = false) {
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

        // Add a content pill when there is both content and file attachments,
        // so the teacher can switch between viewing content and previewing files.
        if (hasContent && files.length > 0) {
            const contentPill = document.createElement('span');
            contentPill.className = 'btn-group btn-group-sm';
            contentPill.dataset.fileid = 'content';

            const contentBtn = document.createElement('button');
            contentBtn.type = 'button';
            contentBtn.className = 'btn btn-sm btn-outline-secondary d-flex align-items-center gap-1';
            const contentIcon = document.createElement('i');
            contentIcon.className = isForum ? 'fa fa-comments' : 'fa fa-desktop';
            contentIcon.setAttribute('aria-hidden', 'true');
            contentBtn.appendChild(contentIcon);
            const contentLabel = document.createElement('span');
            contentLabel.className = 'small';
            const stringKey = isForum ? 'forum_posts_pill' : 'submission_content_pill';
            contentLabel.textContent = isForum ? 'Posts' : 'Submission';
            getString(stringKey, 'local_unifiedgrader')
                .then((str) => { contentLabel.textContent = str; })
                .catch(() => {});
            contentBtn.appendChild(contentLabel);
            contentBtn.addEventListener('click', () => {
                // Save annotations before switching to content view.
                if (this._pdfViewer) {
                    this._pdfViewer.saveAnnotationsNow();
                }
                this._showSubmissionContent();
                this._currentFileId = null;
                this._highlightFileButton('content');
            });

            contentPill.appendChild(contentBtn);
            list.appendChild(contentPill);
        }

        files.forEach((file) => {
            const pill = document.createElement('span');
            pill.className = 'btn-group btn-group-sm';
            pill.dataset.fileid = file.fileid;

            // Preview button (filename).
            const previewBtn = document.createElement('button');
            previewBtn.type = 'button';
            previewBtn.className = 'btn btn-sm btn-outline-secondary d-flex align-items-center';
            const name = document.createElement('span');
            name.className = 'small text-truncate';
            name.style.maxWidth = '180px';
            name.textContent = file.filename;
            previewBtn.appendChild(name);
            previewBtn.addEventListener('click', () => {
                if (this._isPreviewable(file)) {
                    this._previewFile(file);
                } else {
                    window.open(file.url, '_blank');
                }
            });

            // Download button (icon).
            const dlLink = document.createElement('a');
            dlLink.href = file.url;
            dlLink.download = file.filename;
            dlLink.className = 'btn btn-sm btn-outline-secondary d-flex align-items-center';
            getString('download_original_submission', 'local_unifiedgrader', file.filename)
                .then((str) => { dlLink.title = str; })
                .catch(() => { dlLink.title = file.filename; });
            const dlIcon = document.createElement('i');
            dlIcon.className = 'fa fa-download';
            dlIcon.setAttribute('aria-hidden', 'true');
            dlLink.appendChild(dlIcon);

            pill.appendChild(previewBtn);
            pill.appendChild(dlLink);
            list.appendChild(pill);
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

        if ((file.mimetype === 'application/pdf' || file.convertible) && this._pdfViewer) {
            // Use PDF.js viewer for PDF files (and files converted to PDF).
            pdfWrapper.classList.remove('d-none');
            // Pass file context for annotation persistence.
            const state = this.reactive.state;
            this._pdfViewer.setFileContext(
                parseInt(state.activity.cmid, 10),
                parseInt(state.currentUser.id, 10),
                parseInt(file.fileid, 10),
            );
            this._pdfViewer.setFileInfo(file);
            this._pdfViewer.loadPdf(file.previewurl || file.url);
        } else if (file.mimetype.startsWith('audio/') || file.mimetype.startsWith('video/')) {
            // Use styled media player page for audio/video.
            const iframe = this.getElement(this.selectors.PREVIEW_IFRAME);
            const cmid = this.reactive.state.activity?.cmid;
            iframe.src = `${M.cfg.wwwroot}/local/unifiedgrader/preview_media.php`
                + `?fileid=${file.fileid}&cmid=${cmid}`;
            docPreview.classList.remove('d-none');
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
     * Show submission content (e.g. forum posts) in the iframe preview.
     */
    _showSubmissionContent() {
        const pdfWrapper = this.getElement(this.selectors.PDF_VIEWER_WRAPPER);
        const docPreview = this.getElement(this.selectors.DOCUMENT_PREVIEW);
        pdfWrapper.classList.add('d-none');
        docPreview.classList.add('d-none');

        const iframe = this.getElement(this.selectors.PREVIEW_IFRAME);
        const cmid = this.reactive.state.activity?.cmid;
        const userid = this.reactive.state.submission?.userid;
        if (cmid && userid) {
            let url = `${M.cfg.wwwroot}/local/unifiedgrader/preview_submission.php`
                + `?cmid=${cmid}&userid=${userid}`;
            // Include attempt number for quiz multi-attempt support.
            const attemptnumber = this.reactive.state.submission?.attemptnumber;
            if (attemptnumber !== undefined && attemptnumber !== null && attemptnumber >= 0) {
                url += `&attempt=${attemptnumber}`;
            }
            iframe.src = url;
            docPreview.classList.remove('d-none');
        }
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
        list.querySelectorAll('[data-fileid]').forEach((pill) => {
            const isActive = pill.dataset.fileid === String(fileid);
            pill.querySelectorAll('button, a').forEach((el) => {
                el.classList.toggle('btn-outline-secondary', !isActive);
                el.classList.toggle('btn-primary', isActive);
            });
        });
    }

    /**
     * Check if a file can be previewed inline.
     *
     * @param {object} file File info object with mimetype and convertible properties.
     * @return {boolean}
     */
    _isPreviewable(file) {
        if (file.convertible) {
            return true;
        }
        const mimetype = file.mimetype || '';
        if ([
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'text/plain',
        ].includes(mimetype)) {
            return true;
        }
        // Audio and video types are previewable via HTML5 media elements.
        return mimetype.startsWith('audio/') || mimetype.startsWith('video/');
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
