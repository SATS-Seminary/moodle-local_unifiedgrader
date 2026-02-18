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
 * PDF viewer component - renders PDFs via PDF.js with page navigation, zoom,
 * and a Fabric.js annotation overlay layer with backend persistence.
 *
 * @module     local_unifiedgrader/components/pdf_viewer
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent} from 'core/reactive';
import {get_string as getString} from 'core/str';
import PdfjsLoader from 'local_unifiedgrader/lib/pdfjs_loader';
import FabricLoader from 'local_unifiedgrader/lib/fabric_loader';
import AnnotationLayer from 'local_unifiedgrader/components/annotation_layer';
import AnnotationToolbar from 'local_unifiedgrader/components/annotation_toolbar';
import {loadAnnotations, saveAnnotations, uploadAnnotatedPdf, deleteAnnotatedPdf}
    from 'local_unifiedgrader/annotation/persistence';
import {flattenAnnotatedPdf} from 'local_unifiedgrader/annotation/pdf_flatten';
import PdflibLoader from 'local_unifiedgrader/lib/pdflib_loader';

/** @type {number[]} Available zoom levels as multipliers. */
const ZOOM_LEVELS = [0.5, 0.75, 1.0, 1.25, 1.5, 2.0, 3.0];

/** @type {number} Default zoom index (1.0 = 100%). */
const DEFAULT_ZOOM_INDEX = 2;

/** @type {number} Auto-save debounce delay in milliseconds. */
const SAVE_DEBOUNCE_MS = 2500;

/** @type {number} Maximum retries for document conversion polling. */
const CONVERSION_MAX_RETRIES = 15;

/** @type {number} Retry delay in milliseconds for conversion polling. */
const CONVERSION_RETRY_DELAY_MS = 2000;

export default class PdfViewer extends BaseComponent {

    /**
     * Component creation hook.
     */
    create() {
        this.name = 'pdf_viewer';
        this.selectors = {
            PAGE_CONTAINER: '[data-region="pdf-page-container"]',
            CANVAS_WRAPPER: '[data-region="pdf-canvas-wrapper"]',
            PDF_CANVAS: '[data-region="pdf-canvas"]',
            ANNOTATION_CANVAS: '[data-region="annotation-canvas"]',
            ANNOTATION_TOOLBAR: '[data-region="annotation-toolbar"]',
            LOADING: '[data-region="pdf-loading"]',
            CURRENT_PAGE: '[data-region="current-page"]',
            TOTAL_PAGES: '[data-region="total-pages"]',
            ZOOM_LEVEL: '[data-region="zoom-level"]',
            PREV_BTN: '[data-action="prev-page"]',
            NEXT_BTN: '[data-action="next-page"]',
            ZOOM_IN_BTN: '[data-action="zoom-in"]',
            ZOOM_OUT_BTN: '[data-action="zoom-out"]',
            ZOOM_FIT_BTN: '[data-action="zoom-fit"]',
            LOADING_MESSAGE: '[data-region="pdf-loading-message"]',
            ERROR_MESSAGE: '[data-region="pdf-error"]',
            TEXT_LAYER: '[data-region="pdf-text-layer"]',
            LINK_LAYER: '[data-region="pdf-link-layer"]',
            SEARCH_BAR: '[data-region="pdf-search-bar"]',
            SEARCH_INPUT: '[data-action="search-input"]',
            SEARCH_COUNT: '[data-region="search-count"]',
            SEARCH_PREV: '[data-action="search-prev"]',
            SEARCH_NEXT: '[data-action="search-next"]',
            SEARCH_CLOSE: '[data-action="search-close"]',
            SEARCH_TOGGLE: '[data-action="search-toggle"]',
        };

        /** @type {?object} PDF.js document proxy. */
        this._pdfDoc = null;
        /** @type {number} Current page number (1-based). */
        this._currentPage = 1;
        /** @type {number} Total number of pages. */
        this._totalPages = 0;
        /** @type {number} Current zoom level index. */
        this._zoomIndex = DEFAULT_ZOOM_INDEX;
        /** @type {boolean} Whether fit-to-width mode is active. */
        this._fitToWidth = true;
        /** @type {boolean} Whether a page render is in progress. */
        this._rendering = false;
        /** @type {?number} Queued page number to render after current render completes. */
        this._pendingPage = null;
        /** @type {?string} URL of the currently loaded PDF. */
        this._currentUrl = null;
        /** @type {?object} PDF.js library reference. */
        this._pdfjsLib = null;

        // Annotation layer and toolbar (initialised on first PDF load).
        /** @type {?AnnotationLayer} */
        this._annotationLayer = null;
        /** @type {?AnnotationToolbar} */
        this._annotationToolbar = null;
        /** @type {boolean} */
        this._annotationsInitialised = false;

        // Annotation persistence state.
        /** @type {number} Course module ID. */
        this._cmid = 0;
        /** @type {number} Student user ID. */
        this._userid = 0;
        /** @type {number} Current file ID. */
        this._fileid = 0;
        /** @type {?number} Debounce timer ID for auto-save. */
        this._saveTimer = null;
        /** @type {boolean} Whether annotations have unsaved changes. */
        this._dirty = false;
        /** @type {Set<number>} Page numbers that were loaded from the backend. */
        this._loadedPageNums = new Set();
        /** @type {boolean} Whether this is a read-only student view. */
        this._readOnly = this.element?.dataset?.readonly === '1';
        /** @type {?object} PDF.js TextLayer instance for the current page. */
        this._textLayer = null;
        /** @type {?AbortController} Abort controller for in-flight PDF fetch/conversion polling. */
        this._fetchAbortController = null;
        /** @type {?ArrayBuffer} Original PDF bytes for flattening. */
        this._pdfBytes = null;
        /** @type {?AbortController} Abort controller for in-flight flatten operation. */
        this._flattenAbortController = null;
        /** @type {?object} Current file metadata {filename, filesize, mimetype}. */
        this._fileInfo = null;
        /** @type {Map<number, number>} Cached word counts per file ID. */
        this._wordCountCache = new Map();

        // Text search state.
        /** @type {boolean} Whether the search bar is open. */
        this._searchOpen = false;
        /** @type {string} Current search query. */
        this._searchQuery = '';
        /** @type {Array<{page: number}>} All matches across all pages. */
        this._searchMatches = [];
        /** @type {number} Index of the active match in _searchMatches (-1 = none). */
        this._searchIndex = -1;
        /** @type {Map<number, {fullText: string, itemRanges: Array}>} Cached text per page. */
        this._pageTextCache = new Map();
        /** @type {?number} Debounce timer for search input. */
        this._searchDebounceTimer = null;
    }

    /**
     * Register state watchers.
     *
     * @return {Array}
     */
    getWatchers() {
        return [];
    }

    /**
     * Called after the component DOM is ready.
     */
    stateReady() {
        this._bindControls();
    }

    /**
     * Bind click handlers for page navigation and zoom controls.
     */
    _bindControls() {
        const prevBtn = this.getElement(this.selectors.PREV_BTN);
        const nextBtn = this.getElement(this.selectors.NEXT_BTN);
        const zoomInBtn = this.getElement(this.selectors.ZOOM_IN_BTN);
        const zoomOutBtn = this.getElement(this.selectors.ZOOM_OUT_BTN);
        const zoomFitBtn = this.getElement(this.selectors.ZOOM_FIT_BTN);

        if (prevBtn) {
            prevBtn.addEventListener('click', () => this._goToPage(this._currentPage - 1));
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', () => this._goToPage(this._currentPage + 1));
        }
        if (zoomInBtn) {
            zoomInBtn.addEventListener('click', () => this._zoom(1));
        }
        if (zoomOutBtn) {
            zoomOutBtn.addEventListener('click', () => this._zoom(-1));
        }
        if (zoomFitBtn) {
            zoomFitBtn.addEventListener('click', () => this._zoomFitToWidth());
        }

        // Keyboard shortcuts for page navigation and search (works in both read-only and edit modes).
        // Bound to document because the PDF canvas doesn't reliably receive keyboard focus.
        this._onPageKeyDown = (e) => {
            // Ctrl+F / Cmd+F — open search bar (intercept browser find).
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                // Only intercept when our PDF viewer is visible.
                if (this._pdfDoc) {
                    e.preventDefault();
                    this._toggleSearch();
                }
                return;
            }

            // Escape — close search bar if open.
            if (e.key === 'Escape' && this._searchOpen) {
                e.preventDefault();
                this._closeSearch();
                return;
            }

            // Skip page navigation when focus is inside an input, textarea, or contenteditable element.
            const tag = e.target.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || e.target.isContentEditable) {
                return;
            }
            switch (e.key) {
                case 'PageDown':
                    this._goToPage(this._currentPage + 1);
                    e.preventDefault();
                    break;
                case 'PageUp':
                    this._goToPage(this._currentPage - 1);
                    e.preventDefault();
                    break;
                case 'Home':
                    this._goToPage(1);
                    e.preventDefault();
                    break;
                case 'End':
                    this._goToPage(this._totalPages);
                    e.preventDefault();
                    break;
            }
        };
        document.addEventListener('keydown', this._onPageKeyDown);

        // Search bar controls.
        this._bindSearchControls();

        // Skip editing shortcuts and save handlers in read-only mode.
        if (!this._readOnly) {
            // Keyboard shortcut: Delete key removes selected annotation.
            // Bound to document because the Fabric.js canvas doesn't reliably receive keyboard focus.
            this._onKeyDown = (e) => {
                if ((e.key === 'Delete' || e.key === 'Backspace') && this._annotationLayer) {
                    if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                        this._annotationLayer.deleteSelected();
                        e.preventDefault();
                    }
                }
            };
            document.addEventListener('keydown', this._onKeyDown);

            // Best-effort save on page unload.
            this._onBeforeUnload = () => {
                if (this._dirty && this._annotationLayer && this._fileid) {
                    this._saveAnnotationsToBackend();
                }
            };
            window.addEventListener('beforeunload', this._onBeforeUnload);
        }
    }

    // ──────────────────────────────────────────────
    //  File context and annotation persistence
    // ──────────────────────────────────────────────

    /**
     * Set the file context for annotation persistence.
     * Called by preview_panel before loadPdf().
     *
     * @param {number} cmid Course module ID.
     * @param {number} userid Student user ID.
     * @param {number} fileid File ID.
     */
    setFileContext(cmid, userid, fileid) {
        // If switching to a different file, save current annotations first.
        if (this._fileid && this._fileid !== fileid && this._dirty) {
            this._saveAnnotationsToBackend();
        }
        this._cmid = cmid;
        this._userid = userid;
        this._fileid = fileid;
    }

    /**
     * Store file metadata for the document info popout.
     *
     * @param {object} file File info from submission state.
     */
    setFileInfo(file) {
        this._fileInfo = {
            filename: file.filename || '',
            filesize: file.filesize || 0,
            mimetype: file.mimetype || '',
        };
    }

    /**
     * Gather PDF metadata and pass it to the annotation toolbar.
     *
     * @returns {Promise<void>}
     */
    async _pushDocumentInfo() {
        if (!this._pdfDoc || !this._annotationToolbar) {
            return;
        }

        let metadata = {};
        try {
            const meta = await this._pdfDoc.getMetadata();
            metadata = meta?.info || {};
        } catch {
            // Some PDFs have no metadata — ignore errors.
        }

        const info = {
            filename: this._fileInfo?.filename || '',
            filesize: this._fileInfo?.filesize || 0,
            pages: this._totalPages,
            metadata: metadata,
        };

        // Pass a lazy word count callback bound to the current file.
        const fileid = this._fileid;
        const wordCountFn = () => this._getWordCount(fileid);

        this._annotationToolbar.setDocumentInfo(info, wordCountFn);
    }

    /**
     * Count words across all pages of the current PDF.
     *
     * Uses PDF.js text extraction. Results are cached per file ID.
     *
     * @param {number} fileid File ID to verify we're still on the same file.
     * @returns {Promise<number>} Total word count.
     */
    async _getWordCount(fileid) {
        // Return cached value if available.
        if (this._wordCountCache.has(fileid)) {
            return this._wordCountCache.get(fileid);
        }

        if (!this._pdfDoc) {
            return 0;
        }

        let totalWords = 0;
        for (let i = 1; i <= this._pdfDoc.numPages; i++) {
            // Bail out if the user switched files while we're counting.
            if (this._fileid !== fileid) {
                return 0;
            }
            const page = await this._pdfDoc.getPage(i);
            const textContent = await page.getTextContent();
            const pageText = textContent.items.map((item) => item.str).join(' ');
            const words = pageText.split(/\s+/).filter((w) => w.length > 0);
            totalWords += words.length;
        }

        this._wordCountCache.set(fileid, totalWords);
        return totalWords;
    }

    /**
     * Force-save annotations immediately.
     * Called by preview_panel before switching students.
     */
    saveAnnotationsNow() {
        this._saveAnnotationsToBackend();
    }

    /**
     * Load annotations from the backend and populate the annotation layer.
     *
     * @returns {Promise<void>}
     */
    async _loadAnnotationsFromBackend() {
        if (!this._annotationLayer || !this._fileid) {
            return;
        }

        try {
            const annotations = await loadAnnotations(this._cmid, this._userid, this._fileid);

            this._loadedPageNums = new Set();

            annotations.forEach((annot) => {
                try {
                    const json = JSON.parse(annot.annotationdata);
                    this._annotationLayer.setPageAnnotations(annot.pagenum, json);
                    this._loadedPageNums.add(annot.pagenum);
                } catch (e) {
                    window.console.warn('[pdf_viewer] Invalid annotation JSON for page', annot.pagenum, e);
                }
            });

            // Reload current page to show any loaded annotations.
            // Use reloadCurrentPage() instead of switchPage() because the canvas
            // is empty — switchPage() would save the empty state and delete the
            // annotations we just loaded into the map.
            if (this._loadedPageNums.size > 0) {
                await this._annotationLayer.reloadCurrentPage();
            }

        } catch (err) {
            window.console.error('[pdf_viewer] Failed to load annotations:', err);
        }
    }

    /**
     * Schedule a debounced save. Resets the timer on each call.
     */
    _scheduleSave() {
        if (this._saveTimer) {
            clearTimeout(this._saveTimer);
        }
        this._saveTimer = setTimeout(() => {
            this._saveTimer = null;
            this._saveAnnotationsToBackend();
        }, SAVE_DEBOUNCE_MS);
    }

    /**
     * Save all annotations to the backend immediately.
     *
     * @returns {Promise<void>}
     */
    async _saveAnnotationsToBackend() {
        if (!this._annotationLayer || !this._fileid || !this._dirty) {
            return;
        }

        // Cancel any pending debounce.
        if (this._saveTimer) {
            clearTimeout(this._saveTimer);
            this._saveTimer = null;
        }

        try {
            const allAnnotations = this._annotationLayer.getAllAnnotations();
            const pages = [];

            allAnnotations.forEach((fabricJson, pageNum) => {
                pages.push({
                    pagenum: pageNum,
                    annotationdata: JSON.stringify(fabricJson),
                });
            });

            // For pages that were loaded from backend but are no longer in the map
            // (user cleared them), send empty annotationdata so the backend deletes them.
            for (const loadedPageNum of this._loadedPageNums) {
                if (!allAnnotations.has(loadedPageNum)) {
                    pages.push({
                        pagenum: loadedPageNum,
                        annotationdata: '',
                    });
                }
            }

            if (pages.length === 0) {
                this._dirty = false;
                return;
            }

            await saveAnnotations(this._cmid, this._userid, this._fileid, pages);
            this._dirty = false;

            // Update loaded pages tracking to match what was just saved.
            this._loadedPageNums = new Set(allAnnotations.keys());

            // Trigger flattened PDF generation in the background.
            this._flattenAndUpload(allAnnotations);

        } catch (err) {
            window.console.error('[pdf_viewer] Failed to save annotations:', err);
            // Leave dirty = true so next trigger retries.
        }
    }

    // ──────────────────────────────────────────────
    //  PDF loading and rendering
    // ──────────────────────────────────────────────

    /**
     * Load and display a PDF from a URL.
     *
     * @param {string} url The PDF file URL.
     * @returns {Promise<void>}
     */
    async loadPdf(url) {
        if (this._currentUrl === url) {
            return;
        }

        // Abort any in-flight fetch/conversion polling from a previous loadPdf call.
        if (this._fetchAbortController) {
            this._fetchAbortController.abort();
        }
        this._fetchAbortController = new AbortController();

        this._showLoading(true);
        this._showLoadingMessage('');
        this._showError('');

        try {
            // Load PDF.js if not yet loaded.
            if (!this._pdfjsLib) {
                this._pdfjsLib = await PdfjsLoader.load();
            }

            // Close previous document.
            if (this._pdfDoc) {
                this._pdfDoc.destroy();
                this._pdfDoc = null;
            }

            // Destroy existing annotation layer and toolbar when switching PDFs.
            if (this._annotationToolbar) {
                this._annotationToolbar.destroy();
                this._annotationToolbar = null;
            }
            if (this._annotationLayer) {
                this._annotationLayer.destroy();
                this._annotationLayer = null;
                this._annotationsInitialised = false;
            }

            // Clean up text layer from previous PDF.
            if (this._textLayer) {
                this._textLayer.cancel();
                this._textLayer = null;
            }

            // Reset persistence state for the new file.
            this._dirty = false;
            this._loadedPageNums = new Set();
            this._pdfBytes = null;

            // Clear search state for the new document.
            this._pageTextCache.clear();
            this._closeSearch();
            if (this._saveTimer) {
                clearTimeout(this._saveTimer);
                this._saveTimer = null;
            }

            // Fetch PDF data — handles document conversion polling for .docx/.doc files.
            const pdfData = await this._fetchPdfData(url);

            // Retain a clone of the PDF bytes for annotation flattening.
            // PDF.js may transfer/consume the original buffer.
            this._pdfBytes = pdfData.slice(0);

            // Load the PDF from the fetched ArrayBuffer.
            this._pdfDoc = await this._pdfjsLib.getDocument({
                data: pdfData,
                disableRange: true,
                disableStream: true,
            }).promise;

            this._currentUrl = url;
            this._currentPage = 1;
            this._totalPages = this._pdfDoc.numPages;

            this._updatePageControls();

            // Render the first page.
            await this._renderPage(this._currentPage);

            // Initialise annotation layer after first render.
            await this._initAnnotations();

            // Pass document info to the toolbar for the info popout.
            if (this._annotationToolbar) {
                await this._pushDocumentInfo();
            }

            // Load saved annotations from backend (skip in read-only mode —
            // annotations are loaded externally by feedback_viewer.js via the student API).
            if (!this._readOnly) {
                await this._loadAnnotationsFromBackend();
            }

        } catch (err) {
            if (err.name === 'AbortError') {
                return; // loadPdf was called again — silently abort.
            }
            window.console.error('[pdf_viewer] Failed to load PDF:', err);
            this._showError(err.message || 'Failed to load document.');
        } finally {
            this._showLoading(false);
            this._showLoadingMessage('');
        }
    }

    /**
     * Initialise the Fabric.js annotation layer and toolbar.
     *
     * @returns {Promise<void>}
     */
    async _initAnnotations() {
        if (this._annotationsInitialised) {
            return;
        }

        try {
            const fabricLib = await FabricLoader.load();
            const annotCanvas = this.getElement(this.selectors.ANNOTATION_CANVAS);
            const wrapperEl = this.getElement(this.selectors.CANVAS_WRAPPER);
            const toolbarEl = this.getElement(this.selectors.ANNOTATION_TOOLBAR);

            if (!annotCanvas || !wrapperEl) {
                return;
            }

            // Create the annotation layer (read-only for student view).
            this._annotationLayer = new AnnotationLayer(fabricLib, annotCanvas, wrapperEl, this._readOnly);
            this._annotationLayer.setPageSize(
                parseInt(annotCanvas.style.width, 10),
                parseInt(annotCanvas.style.height, 10)
            );

            // In read-only mode, skip auto-save and toolbar.
            if (!this._readOnly) {
                // Wire auto-save: mark dirty and debounce on any annotation change.
                this._annotationLayer.onChange(() => {
                    this._dirty = true;
                    this._scheduleSave();
                });

                // Toggle text selection based on annotation tool.
                // Only the 'textselect' tool enables the PDF.js text layer for selection.
                this._annotationLayer.onToolChange((tool) => {
                    this.setTextSelectable(tool === 'textselect');
                });
                // Start with text selection off (default tool is 'select' for annotations).
                this.setTextSelectable(false);

                // Create the toolbar handler and show it.
                if (toolbarEl) {
                    this._annotationToolbar = new AnnotationToolbar(toolbarEl, this._annotationLayer);
                    this._annotationToolbar.show();
                }
            }

            this._annotationsInitialised = true;

        } catch (err) {
            window.console.error('[pdf_viewer] Failed to initialise annotations:', err);
        }
    }

    /**
     * Render a specific page to the canvas.
     *
     * @param {number} pageNum Page number (1-based).
     * @returns {Promise<void>}
     */
    async _renderPage(pageNum) {
        if (!this._pdfDoc) {
            return;
        }

        // If currently rendering, queue this page.
        if (this._rendering) {
            this._pendingPage = pageNum;
            return;
        }

        this._rendering = true;
        this._showLoading(true);

        const isPageChange = (pageNum !== this._currentPage);

        try {
            const page = await this._pdfDoc.getPage(pageNum);

            // Calculate scale.
            let scale;
            if (this._fitToWidth) {
                scale = this._calculateFitToWidthScale(page);
            } else {
                scale = ZOOM_LEVELS[this._zoomIndex];
            }

            // Account for device pixel ratio for sharp rendering.
            const dpr = window.devicePixelRatio || 1;
            const viewport = page.getViewport({scale: scale * dpr});
            const displayViewport = page.getViewport({scale: scale});

            // Size the PDF canvas.
            const pdfCanvas = this.getElement(this.selectors.PDF_CANVAS);
            const annotCanvas = this.getElement(this.selectors.ANNOTATION_CANVAS);

            pdfCanvas.width = viewport.width;
            pdfCanvas.height = viewport.height;
            pdfCanvas.style.width = displayViewport.width + 'px';
            pdfCanvas.style.height = displayViewport.height + 'px';

            // Set annotation canvas dimensions.
            // Before Fabric.js init, set HTML attributes so the canvas has correct
            // dimensions when Fabric wraps it (otherwise it reads the default 300x150).
            // After init, setPageSize() calls setDimensions() which handles both canvases.
            if (!this._annotationsInitialised) {
                annotCanvas.width = Math.round(displayViewport.width);
                annotCanvas.height = Math.round(displayViewport.height);
            }
            annotCanvas.style.width = displayViewport.width + 'px';
            annotCanvas.style.height = displayViewport.height + 'px';

            // Render the PDF page.
            const ctx = pdfCanvas.getContext('2d');
            await page.render({
                canvasContext: ctx,
                viewport: viewport,
            }).promise;

            // Render the text layer (for text selection) and link layer (for clickable hyperlinks).
            await this._renderTextLayer(page, displayViewport);
            await this._renderLinkLayer(page, displayViewport);

            this._currentPage = pageNum;
            this._updatePageControls();
            this._updateZoomDisplay(scale);

            // Update annotation layer for the new page.
            if (this._annotationLayer) {
                // Rescale annotations on zoom (same page) but not on page switch
                // (where switchPage replaces the canvas contents entirely).
                this._annotationLayer.setPageSize(
                    Math.round(displayViewport.width),
                    Math.round(displayViewport.height),
                    !isPageChange
                );
                if (isPageChange) {
                    await this._annotationLayer.switchPage(pageNum);
                    // Re-debounce save after page switch updates the in-memory Map.
                    if (this._dirty) {
                        this._scheduleSave();
                    }
                }
            }

            // Re-apply search highlights if a search is active.
            if (this._searchQuery) {
                this._highlightSearchMatches();
            }

            // Dispatch event for external listeners.
            this.element.dispatchEvent(new CustomEvent('pdf-page-rendered', {
                bubbles: true,
                detail: {
                    pageNum: pageNum,
                    totalPages: this._totalPages,
                    width: displayViewport.width,
                    height: displayViewport.height,
                    scale: scale,
                },
            }));

        } catch (err) {
            window.console.error('[pdf_viewer] Failed to render page:', err);
        } finally {
            this._rendering = false;
            this._showLoading(false);

            if (this._pendingPage !== null) {
                const next = this._pendingPage;
                this._pendingPage = null;
                this._renderPage(next);
            }
        }
    }

    /**
     * Calculate scale factor to fit page width within the container.
     *
     * @param {object} page PDF.js page proxy.
     * @returns {number} Scale factor.
     */
    _calculateFitToWidthScale(page) {
        const container = this.getElement(this.selectors.PAGE_CONTAINER);
        const containerWidth = container.clientWidth - 32;
        const pageWidth = page.getViewport({scale: 1.0}).width;
        return containerWidth / pageWidth;
    }

    /**
     * Navigate to a specific page.
     *
     * @param {number} pageNum Target page number (1-based).
     */
    _goToPage(pageNum) {
        if (pageNum < 1 || pageNum > this._totalPages) {
            return;
        }
        this._renderPage(pageNum);
    }

    /**
     * Zoom in or out by one step.
     *
     * @param {number} direction 1 for zoom in, -1 for zoom out.
     */
    _zoom(direction) {
        this._fitToWidth = false;
        const newIndex = this._zoomIndex + direction;
        if (newIndex < 0 || newIndex >= ZOOM_LEVELS.length) {
            return;
        }
        this._zoomIndex = newIndex;
        this._renderPage(this._currentPage);
    }

    /**
     * Reset zoom to fit the page width within the container.
     */
    _zoomFitToWidth() {
        this._fitToWidth = true;
        this._renderPage(this._currentPage);
    }

    /**
     * Update page navigation buttons and display.
     */
    _updatePageControls() {
        const currentEl = this.getElement(this.selectors.CURRENT_PAGE);
        const totalEl = this.getElement(this.selectors.TOTAL_PAGES);
        const prevBtn = this.getElement(this.selectors.PREV_BTN);
        const nextBtn = this.getElement(this.selectors.NEXT_BTN);

        if (currentEl) {
            currentEl.textContent = this._currentPage;
        }
        if (totalEl) {
            totalEl.textContent = this._totalPages;
        }
        if (prevBtn) {
            prevBtn.disabled = this._currentPage <= 1;
        }
        if (nextBtn) {
            nextBtn.disabled = this._currentPage >= this._totalPages;
        }
    }

    /**
     * Update the zoom level display.
     *
     * @param {number} scale Current scale factor.
     */
    _updateZoomDisplay(scale) {
        const zoomEl = this.getElement(this.selectors.ZOOM_LEVEL);
        if (zoomEl) {
            zoomEl.textContent = Math.round(scale * 100) + '%';
        }
    }

    /**
     * Fetch PDF data from a URL, handling document conversion responses.
     *
     * Uses fetch() to inspect the HTTP response before passing data to PDF.js.
     * Handles HTTP 202 (conversion in progress) with retry polling and
     * HTTP 422 (conversion failed) with a user-facing error.
     *
     * @param {string} url The PDF URL (may include ?convert=pdf for convertible files).
     * @returns {Promise<ArrayBuffer>} The PDF data as an ArrayBuffer.
     */
    async _fetchPdfData(url) {
        const signal = this._fetchAbortController?.signal;
        const convertingMsg = await getString('converting_file', 'local_unifiedgrader');

        for (let attempt = 0; attempt <= CONVERSION_MAX_RETRIES; attempt++) {
            const response = await fetch(url, {credentials: 'same-origin', signal});

            // Success — PDF content returned.
            if (response.ok && response.headers.get('content-type')?.includes('application/pdf')) {
                return response.arrayBuffer();
            }

            // Conversion in progress — show message and retry.
            if (response.status === 202) {
                if (attempt >= CONVERSION_MAX_RETRIES) {
                    break; // Fall through to timeout error.
                }
                this._showLoadingMessage(convertingMsg);
                await new Promise((resolve, reject) => {
                    const timer = setTimeout(resolve, CONVERSION_RETRY_DELAY_MS);
                    // Respect abort signal during the wait.
                    signal?.addEventListener('abort', () => {
                        clearTimeout(timer);
                        reject(new DOMException('Aborted', 'AbortError'));
                    }, {once: true});
                });
                continue;
            }

            // Conversion failed — extract server error message.
            if (response.status === 422) {
                let errorMsg = '';
                try {
                    const json = await response.json();
                    errorMsg = json.error || '';
                } catch {
                    // Ignore JSON parse errors.
                }
                throw new Error(errorMsg || 'Document conversion failed.');
            }

            // 200 with unexpected content-type — might still be PDF data.
            if (response.ok) {
                return response.arrayBuffer();
            }

            // Other HTTP errors (403, 404, 500, etc.).
            throw new Error('Failed to load document (HTTP ' + response.status + ').');
        }

        // Max retries exceeded.
        const timeoutMsg = await getString('conversion_timeout', 'local_unifiedgrader');
        throw new Error(timeoutMsg);
    }

    // ──────────────────────────────────────────────
    //  Flattened PDF generation
    // ──────────────────────────────────────────────

    /**
     * Generate a flattened annotated PDF and upload to Moodle file storage.
     *
     * Runs in the background after annotation save. Cancels any previous
     * in-flight flatten operation to avoid stale uploads.
     *
     * @param {Map<number, object>} allAnnotations Snapshot of all page annotations.
     */
    async _flattenAndUpload(allAnnotations) {
        // Cancel any in-flight flatten.
        if (this._flattenAbortController) {
            this._flattenAbortController.abort();
        }
        this._flattenAbortController = new AbortController();
        const signal = this._flattenAbortController.signal;

        // Capture context (may change if user switches student).
        const cmid = this._cmid;
        const userid = this._userid;
        const fileid = this._fileid;
        const pdfBytes = this._pdfBytes;

        if (!pdfBytes || !fileid) {
            return;
        }

        // Check if there are any actual annotations.
        let hasAnnotations = false;
        for (const [, json] of allAnnotations) {
            if (json.objects && json.objects.length > 0) {
                hasAnnotations = true;
                break;
            }
        }

        if (!hasAnnotations) {
            // No annotations — delete any existing flattened PDF.
            try {
                await deleteAnnotatedPdf(cmid, userid, fileid);
            } catch (e) {
                window.console.warn('[pdf_viewer] Failed to delete annotated PDF:', e);
            }
            return;
        }

        try {
            // Lazy-load pdf-lib.
            const [PDFLib, fabricLib] = await Promise.all([
                PdflibLoader.load(),
                FabricLoader.load(),
            ]);

            if (signal.aborted) {
                return;
            }

            // Get page dimensions from annotation layer.
            const pageDimensions = this._annotationLayer
                ? this._annotationLayer.getPageDimensions()
                : new Map();

            const flattenedBytes = await flattenAnnotatedPdf(
                pdfBytes, allAnnotations, pageDimensions, fabricLib, PDFLib,
            );

            if (signal.aborted) {
                return;
            }

            // Convert to base64 for upload.
            const base64 = _arrayBufferToBase64(flattenedBytes);

            // Upload via web service.
            await uploadAnnotatedPdf(cmid, userid, fileid, base64, 'annotated.pdf');

        } catch (err) {
            if (err.name === 'AbortError') {
                return;
            }
            window.console.error('[pdf_viewer] Failed to flatten/upload annotated PDF:', err);
        }
    }

    /**
     * Render the PDF.js text layer for text selection.
     *
     * @param {object} page PDF.js page proxy.
     * @param {object} viewport Display viewport (not DPR-scaled).
     * @returns {Promise<void>}
     */
    async _renderTextLayer(page, viewport) {
        const textLayerDiv = this.getElement(this.selectors.TEXT_LAYER);
        if (!textLayerDiv) {
            window.console.warn('[pdf_viewer] Text layer div not found in DOM.');
            return;
        }

        // Cancel previous TextLayer render.
        if (this._textLayer) {
            this._textLayer.cancel();
            this._textLayer = null;
        }
        textLayerDiv.innerHTML = '';

        // Set container styles inline so they work even if CSS isn't loaded.
        textLayerDiv.style.width = viewport.width + 'px';
        textLayerDiv.style.height = viewport.height + 'px';
        textLayerDiv.style.position = 'absolute';
        textLayerDiv.style.top = '0';
        textLayerDiv.style.left = '0';
        textLayerDiv.style.lineHeight = '1.0';
        textLayerDiv.style.pointerEvents = 'none';
        textLayerDiv.style.opacity = '1';

        // CRITICAL: PDF.js v4.x TextLayer uses calc(var(--scale-factor) * Xpx)
        // for font sizes and some positioning. Without this CSS variable, all
        // font sizes are invalid and text spans render at the wrong size.
        textLayerDiv.style.setProperty('--scale-factor', viewport.scale);

        // Check if TextLayer is available in this PDF.js build.
        if (typeof this._pdfjsLib.TextLayer !== 'function') {
            window.console.warn('[pdf_viewer] PDF.js TextLayer not available — text selection disabled.');
            return;
        }

        try {
            const textContent = await page.getTextContent();
            this._textLayer = new this._pdfjsLib.TextLayer({
                textContentSource: textContent,
                container: textLayerDiv,
                viewport: viewport,
            });
            await this._textLayer.render();

            // Only set color: transparent on spans — TextLayer handles all
            // positioning (left, top, fontSize, transform) via its own styles.
            const spans = textLayerDiv.querySelectorAll('span');
            spans.forEach((span) => {
                span.style.color = 'transparent';
            });

            window.console.info('[pdf_viewer] Text layer rendered:', spans.length, 'spans',
                '| scale-factor:', viewport.scale);
        } catch (err) {
            window.console.warn('[pdf_viewer] Failed to render text layer:', err);
        }

        // Enable text selection in read-only mode by default.
        if (this._readOnly) {
            textLayerDiv.classList.add('text-selectable');
        }
    }

    /**
     * Render clickable hyperlink elements over PDF link annotations.
     *
     * @param {object} page PDF.js page proxy.
     * @param {object} viewport Display viewport (not DPR-scaled).
     * @returns {Promise<void>}
     */
    async _renderLinkLayer(page, viewport) {
        const linkLayerDiv = this.getElement(this.selectors.LINK_LAYER);
        if (!linkLayerDiv) {
            window.console.warn('[pdf_viewer] Link layer div not found in DOM.');
            return;
        }

        linkLayerDiv.innerHTML = '';

        // Set critical styles inline so they work even if CSS isn't loaded.
        linkLayerDiv.style.width = viewport.width + 'px';
        linkLayerDiv.style.height = viewport.height + 'px';
        linkLayerDiv.style.position = 'absolute';
        linkLayerDiv.style.top = '0';
        linkLayerDiv.style.left = '0';
        linkLayerDiv.style.zIndex = '5';
        linkLayerDiv.style.pointerEvents = 'none';

        try {
            const annotations = await page.getAnnotations();
            let linkCount = 0;
            for (const annot of annotations) {
                // Accept links with url or unsafeUrl.
                const linkUrl = annot.url || annot.unsafeUrl;
                if (annot.subtype !== 'Link' || !linkUrl) {
                    continue;
                }

                // Convert PDF coordinates to viewport coordinates.
                const rect = viewport.convertToViewportRectangle(annot.rect);
                const [x1, y1, x2, y2] = this._pdfjsLib.Util.normalizeRect(rect);

                const link = document.createElement('a');
                link.href = linkUrl;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.title = linkUrl;

                // Set all positioning inline (don't rely on external CSS).
                link.style.position = 'absolute';
                link.style.left = Math.round(x1) + 'px';
                link.style.top = Math.round(y1) + 'px';
                link.style.width = Math.round(x2 - x1) + 'px';
                link.style.height = Math.round(y2 - y1) + 'px';
                link.style.pointerEvents = 'auto';
                link.style.cursor = 'pointer';
                link.style.opacity = '0';

                linkLayerDiv.appendChild(link);
                linkCount++;
            }
            window.console.info('[pdf_viewer] Link layer rendered:', linkCount, 'links from',
                annotations.length, 'total annotations');
        } catch (err) {
            window.console.warn('[pdf_viewer] Failed to render link layer:', err);
        }
    }

    /**
     * Enable or disable text selection on the text layer.
     *
     * When enabled, the text layer is moved after the Fabric.js canvas-container
     * in the DOM so it sits on top for event capture. Text spans get pointer-events
     * and clicks on non-text areas fall through to Fabric for annotation selection.
     *
     * @param {boolean} enabled Whether text selection should be enabled.
     */
    setTextSelectable(enabled) {
        const textLayerDiv = this.getElement(this.selectors.TEXT_LAYER);
        const wrapper = this.getElement(this.selectors.CANVAS_WRAPPER);
        if (!textLayerDiv || !wrapper) {
            return;
        }

        textLayerDiv.classList.toggle('text-selectable', enabled);

        // Set z-index, pointer-events, and user-select inline.
        // user-select is critical: Moodle/Bootstrap may set user-select: none
        // on parent elements, which prevents text selection from working.
        textLayerDiv.style.zIndex = enabled ? '4' : '1';
        textLayerDiv.style.userSelect = enabled ? 'text' : 'none';
        textLayerDiv.style.webkitUserSelect = enabled ? 'text' : 'none';
        const spans = textLayerDiv.querySelectorAll('span');
        spans.forEach((span) => {
            span.style.pointerEvents = enabled ? 'auto' : '';
            span.style.cursor = enabled ? 'text' : '';
            span.style.userSelect = enabled ? 'text' : '';
            span.style.webkitUserSelect = enabled ? 'text' : '';
        });

        // Reorder the text layer relative to the Fabric.js canvas-container.
        // Fabric.js wraps the annotation canvas in a .canvas-container div whose
        // upper-canvas captures all mouse events. DOM order determines which
        // sibling receives events first when z-index alone is unreliable.
        const canvasContainer = wrapper.querySelector('.canvas-container');
        if (!canvasContainer) {
            window.console.info('[pdf_viewer] setTextSelectable:', enabled,
                '— no canvas-container yet (Fabric not initialised)');
            return;
        }

        if (enabled) {
            // Place text layer AFTER canvas-container but BEFORE link layer.
            const linkLayer = this.getElement(this.selectors.LINK_LAYER);
            if (linkLayer && linkLayer.parentNode === wrapper) {
                wrapper.insertBefore(textLayerDiv, linkLayer);
            } else {
                canvasContainer.after(textLayerDiv);
            }
        } else {
            // Move text layer BEFORE canvas-container so Fabric gets events.
            wrapper.insertBefore(textLayerDiv, canvasContainer);
        }

        window.console.info('[pdf_viewer] setTextSelectable:', enabled,
            '| spans:', spans.length, '| DOM order: text-layer',
            enabled ? 'AFTER' : 'BEFORE', 'canvas-container');
    }

    // ──────────────────────────────────────────────
    //  In-document text search
    // ──────────────────────────────────────────────

    /**
     * Bind event listeners for the search bar controls.
     */
    _bindSearchControls() {
        const toggleBtn = this.getElement(this.selectors.SEARCH_TOGGLE);
        const closeBtn = this.getElement(this.selectors.SEARCH_CLOSE);
        const prevBtn = this.getElement(this.selectors.SEARCH_PREV);
        const nextBtn = this.getElement(this.selectors.SEARCH_NEXT);
        const input = this.getElement(this.selectors.SEARCH_INPUT);

        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => this._toggleSearch());
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this._closeSearch());
        }
        if (prevBtn) {
            prevBtn.addEventListener('click', () => this._navigateSearchMatch(-1));
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', () => this._navigateSearchMatch(1));
        }
        if (input) {
            input.addEventListener('input', () => {
                if (this._searchDebounceTimer) {
                    clearTimeout(this._searchDebounceTimer);
                }
                this._searchDebounceTimer = setTimeout(() => {
                    this._searchDebounceTimer = null;
                    this._performSearch();
                }, 300);
            });
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    if (e.shiftKey) {
                        this._navigateSearchMatch(-1);
                    } else {
                        this._navigateSearchMatch(1);
                    }
                }
                if (e.key === 'Escape') {
                    e.preventDefault();
                    this._closeSearch();
                }
            });
        }
    }

    /**
     * Toggle the search bar open/closed.
     */
    _toggleSearch() {
        if (this._searchOpen) {
            this._closeSearch();
        } else {
            this._openSearch();
        }
    }

    /**
     * Open the search bar and focus the input.
     */
    _openSearch() {
        const bar = this.getElement(this.selectors.SEARCH_BAR);
        const input = this.getElement(this.selectors.SEARCH_INPUT);
        if (!bar) {
            return;
        }

        bar.classList.remove('d-none');
        bar.classList.add('d-flex');
        this._searchOpen = true;

        if (input) {
            input.focus();
            input.select();
        }
    }

    /**
     * Close the search bar and clear all search state and highlights.
     */
    _closeSearch() {
        const bar = this.getElement(this.selectors.SEARCH_BAR);
        const input = this.getElement(this.selectors.SEARCH_INPUT);
        if (bar) {
            bar.classList.add('d-none');
            bar.classList.remove('d-flex');
        }

        this._searchOpen = false;
        this._searchQuery = '';
        this._searchMatches = [];
        this._searchIndex = -1;
        if (this._searchDebounceTimer) {
            clearTimeout(this._searchDebounceTimer);
            this._searchDebounceTimer = null;
        }
        this._clearSearchHighlights();
        this._updateSearchCount();

        if (input) {
            input.value = '';
        }
    }

    /**
     * Extract and cache text content for a given page.
     *
     * @param {number} pageNum Page number (1-based).
     * @returns {Promise<{fullText: string, itemRanges: Array<{start: number, end: number, itemIndex: number}>}>}
     */
    async _getPageText(pageNum) {
        if (this._pageTextCache.has(pageNum)) {
            return this._pageTextCache.get(pageNum);
        }

        if (!this._pdfDoc) {
            return {fullText: '', itemRanges: []};
        }

        const page = await this._pdfDoc.getPage(pageNum);
        const textContent = await page.getTextContent();

        let fullText = '';
        const itemRanges = [];

        for (let i = 0; i < textContent.items.length; i++) {
            const item = textContent.items[i];
            const str = item.str;
            if (str.length === 0) {
                continue;
            }
            const start = fullText.length;
            fullText += str;
            itemRanges.push({start, end: fullText.length, itemIndex: i});
        }

        const result = {fullText, itemRanges};
        this._pageTextCache.set(pageNum, result);
        return result;
    }

    /**
     * Perform a search across all pages using the current input value.
     *
     * Stores matches as {page} only — highlighting is done separately
     * from the actual DOM spans to avoid index mapping issues.
     */
    async _performSearch() {
        const input = this.getElement(this.selectors.SEARCH_INPUT);
        const query = input ? input.value.trim() : '';

        if (!query) {
            this._searchQuery = '';
            this._searchMatches = [];
            this._searchIndex = -1;
            this._clearSearchHighlights();
            this._updateSearchCount();
            return;
        }

        this._searchQuery = query;
        const queryLower = query.toLowerCase();
        const matches = [];

        for (let pageNum = 1; pageNum <= this._totalPages; pageNum++) {
            const {fullText} = await this._getPageText(pageNum);
            const textLower = fullText.toLowerCase();

            let pos = 0;
            while ((pos = textLower.indexOf(queryLower, pos)) !== -1) {
                matches.push({page: pageNum});
                pos += 1;
            }
        }

        this._searchMatches = matches;
        this._searchIndex = matches.length > 0 ? 0 : -1;
        this._updateSearchCount();

        // Navigate to the first match.
        if (this._searchIndex >= 0) {
            const match = this._searchMatches[this._searchIndex];
            if (match.page !== this._currentPage) {
                this._goToPage(match.page);
            } else {
                this._highlightSearchMatches();
            }
        } else {
            this._clearSearchHighlights();
        }
    }

    /**
     * Navigate to the next or previous search match.
     *
     * @param {number} delta 1 for next, -1 for previous.
     */
    _navigateSearchMatch(delta) {
        if (this._searchMatches.length === 0) {
            return;
        }

        // If no active match yet but there are matches, start at 0.
        if (this._searchIndex < 0) {
            this._searchIndex = 0;
        } else {
            this._searchIndex = (this._searchIndex + delta + this._searchMatches.length)
                % this._searchMatches.length;
        }

        this._updateSearchCount();

        const match = this._searchMatches[this._searchIndex];
        if (match.page !== this._currentPage) {
            // Page change will trigger _highlightSearchMatches via _renderPage.
            this._goToPage(match.page);
        } else {
            this._highlightSearchMatches();
        }
    }

    /**
     * Highlight search matches on the current page by searching within
     * the actual DOM text-layer spans. This avoids index-mapping issues
     * between getTextContent() items and rendered spans.
     */
    _highlightSearchMatches() {
        const textLayerDiv = this.getElement(this.selectors.TEXT_LAYER);
        if (!textLayerDiv) {
            return;
        }

        const spans = textLayerDiv.querySelectorAll('span');
        if (spans.length === 0) {
            return;
        }

        // Clear previous highlights.
        spans.forEach((span) => {
            span.style.backgroundColor = '';
        });

        if (!this._searchQuery || this._searchMatches.length === 0) {
            return;
        }

        // Build concatenated text from the actual DOM spans.
        let fullText = '';
        const spanRanges = [];
        spans.forEach((span, idx) => {
            const start = fullText.length;
            fullText += span.textContent;
            spanRanges.push({start, end: fullText.length, idx});
        });

        // Find all matches in the DOM-derived text (case-insensitive).
        const queryLower = this._searchQuery.toLowerCase();
        const textLower = fullText.toLowerCase();

        // Determine which local match on this page should be "active".
        // Count global matches on pages before the current one.
        let activeLocalIndex = -1;
        if (this._searchIndex >= 0 && this._searchMatches[this._searchIndex]?.page === this._currentPage) {
            let firstOnPage = -1;
            for (let i = 0; i < this._searchMatches.length; i++) {
                if (this._searchMatches[i].page === this._currentPage) {
                    firstOnPage = i;
                    break;
                }
            }
            if (firstOnPage >= 0) {
                activeLocalIndex = this._searchIndex - firstOnPage;
            }
        }

        let pos = 0;
        let localIndex = 0;
        while ((pos = textLower.indexOf(queryLower, pos)) !== -1) {
            const matchEnd = pos + queryLower.length;
            const isActive = (localIndex === activeLocalIndex);
            const bgColor = isActive
                ? 'rgba(255, 150, 0, 0.5)'
                : 'rgba(255, 220, 0, 0.35)';

            // Find which DOM spans overlap with this character range.
            let firstSpan = null;
            for (const range of spanRanges) {
                if (range.end > pos && range.start < matchEnd) {
                    spans[range.idx].style.backgroundColor = bgColor;
                    if (!firstSpan) {
                        firstSpan = spans[range.idx];
                    }
                }
            }

            // Scroll the active match into view.
            if (isActive && firstSpan) {
                firstSpan.scrollIntoView({block: 'center', behavior: 'smooth'});
            }

            pos += 1;
            localIndex++;
        }
    }

    /**
     * Remove all search highlight styling from text layer spans.
     */
    _clearSearchHighlights() {
        const textLayerDiv = this.getElement(this.selectors.TEXT_LAYER);
        if (!textLayerDiv) {
            return;
        }
        const spans = textLayerDiv.querySelectorAll('span');
        spans.forEach((span) => {
            span.style.backgroundColor = '';
        });
    }

    /**
     * Update the search count display and prev/next button states.
     */
    _updateSearchCount() {
        const countEl = this.getElement(this.selectors.SEARCH_COUNT);
        const prevBtn = this.getElement(this.selectors.SEARCH_PREV);
        const nextBtn = this.getElement(this.selectors.SEARCH_NEXT);

        if (countEl) {
            if (!this._searchQuery) {
                countEl.textContent = '';
            } else if (this._searchMatches.length === 0) {
                countEl.textContent = '0 results';
            } else {
                countEl.textContent = (this._searchIndex + 1) + ' of ' + this._searchMatches.length;
            }
        }

        const hasMatches = this._searchMatches.length > 0;
        if (prevBtn) {
            prevBtn.disabled = !hasMatches;
        }
        if (nextBtn) {
            nextBtn.disabled = !hasMatches;
        }
    }

    /**
     * Show or hide the loading spinner.
     *
     * @param {boolean} show Whether to show the spinner.
     */
    _showLoading(show) {
        const el = this.getElement(this.selectors.LOADING);
        if (el) {
            el.classList.toggle('d-none', !show);
            el.classList.toggle('d-flex', show);
        }
    }

    /**
     * Show or clear a status message in the loading overlay (e.g. "Converting document...").
     *
     * @param {string} text Message text, or empty string to clear.
     */
    _showLoadingMessage(text) {
        const el = this.getElement(this.selectors.LOADING_MESSAGE);
        if (el) {
            el.textContent = text;
            el.classList.toggle('d-none', !text);
        }
    }

    /**
     * Show or clear an error message overlay in the PDF viewer area.
     *
     * @param {string} text Error message, or empty string to clear.
     */
    _showError(text) {
        const el = this.getElement(this.selectors.ERROR_MESSAGE);
        if (el) {
            el.textContent = text;
            el.classList.toggle('d-none', !text);
        }
    }

    /**
     * Get the current page number.
     *
     * @returns {number} Current page (1-based).
     */
    getCurrentPage() {
        return this._currentPage;
    }

    /**
     * Get the total number of pages.
     *
     * @returns {number} Total pages.
     */
    getTotalPages() {
        return this._totalPages;
    }

    /**
     * Get the annotation layer instance.
     *
     * @returns {?AnnotationLayer}
     */
    getAnnotationLayer() {
        return this._annotationLayer;
    }

    /**
     * Clean up resources when component is destroyed.
     */
    destroy() {
        // Abort any in-flight fetch/conversion polling.
        if (this._fetchAbortController) {
            this._fetchAbortController.abort();
            this._fetchAbortController = null;
        }

        // Save any pending annotations.
        if (this._dirty && this._annotationLayer && this._fileid) {
            this._saveAnnotationsToBackend();
        }

        if (this._saveTimer) {
            clearTimeout(this._saveTimer);
            this._saveTimer = null;
        }
        if (this._onBeforeUnload) {
            window.removeEventListener('beforeunload', this._onBeforeUnload);
            this._onBeforeUnload = null;
        }
        if (this._onPageKeyDown) {
            document.removeEventListener('keydown', this._onPageKeyDown);
            this._onPageKeyDown = null;
        }
        if (this._onKeyDown) {
            document.removeEventListener('keydown', this._onKeyDown);
            this._onKeyDown = null;
        }
        if (this._annotationToolbar) {
            this._annotationToolbar.destroy();
            this._annotationToolbar = null;
        }
        if (this._annotationLayer) {
            this._annotationLayer.destroy();
            this._annotationLayer = null;
        }
        if (this._pdfDoc) {
            this._pdfDoc.destroy();
            this._pdfDoc = null;
        }
        // Abort any in-flight flatten operation.
        if (this._flattenAbortController) {
            this._flattenAbortController.abort();
            this._flattenAbortController = null;
        }

        if (this._textLayer) {
            this._textLayer.cancel();
            this._textLayer = null;
        }
        this._pdfBytes = null;
        this._currentUrl = null;

        // Clear search state.
        if (this._searchDebounceTimer) {
            clearTimeout(this._searchDebounceTimer);
            this._searchDebounceTimer = null;
        }
        this._pageTextCache.clear();
        this._searchMatches = [];

        super.destroy();
    }
}

/**
 * Convert an ArrayBuffer or Uint8Array to a base64 string.
 *
 * @param {ArrayBuffer|Uint8Array} buffer The binary data.
 * @returns {string} Base64-encoded string.
 */
function _arrayBufferToBase64(buffer) {
    const bytes = buffer instanceof Uint8Array ? buffer : new Uint8Array(buffer);
    let binary = '';
    const chunkSize = 8192;
    for (let i = 0; i < bytes.length; i += chunkSize) {
        binary += String.fromCharCode.apply(null, bytes.subarray(i, i + chunkSize));
    }
    return btoa(binary);
}
