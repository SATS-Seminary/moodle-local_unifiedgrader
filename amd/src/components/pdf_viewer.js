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
import PdfjsLoader from 'local_unifiedgrader/lib/pdfjs_loader';
import FabricLoader from 'local_unifiedgrader/lib/fabric_loader';
import AnnotationLayer from 'local_unifiedgrader/components/annotation_layer';
import AnnotationToolbar from 'local_unifiedgrader/components/annotation_toolbar';
import {loadAnnotations, saveAnnotations} from 'local_unifiedgrader/annotation/persistence';

/** @type {number[]} Available zoom levels as multipliers. */
const ZOOM_LEVELS = [0.5, 0.75, 1.0, 1.25, 1.5, 2.0, 3.0];

/** @type {number} Default zoom index (1.0 = 100%). */
const DEFAULT_ZOOM_INDEX = 2;

/** @type {number} Auto-save debounce delay in milliseconds. */
const SAVE_DEBOUNCE_MS = 2500;

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

        this._showLoading(true);

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

            // Reset persistence state for the new file.
            this._dirty = false;
            this._loadedPageNums = new Set();
            if (this._saveTimer) {
                clearTimeout(this._saveTimer);
                this._saveTimer = null;
            }

            // Load the new PDF.
            this._pdfDoc = await this._pdfjsLib.getDocument({
                url: url,
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

            // Load saved annotations from backend.
            await this._loadAnnotationsFromBackend();

        } catch (err) {
            window.console.error('[pdf_viewer] Failed to load PDF:', err);
        } finally {
            this._showLoading(false);
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

            // Create the annotation layer.
            this._annotationLayer = new AnnotationLayer(fabricLib, annotCanvas, wrapperEl);
            this._annotationLayer.setPageSize(
                parseInt(annotCanvas.style.width, 10),
                parseInt(annotCanvas.style.height, 10)
            );

            // Wire auto-save: mark dirty and debounce on any annotation change.
            this._annotationLayer.onChange(() => {
                this._dirty = true;
                this._scheduleSave();
            });

            // Create the toolbar handler and show it.
            if (toolbarEl) {
                this._annotationToolbar = new AnnotationToolbar(toolbarEl, this._annotationLayer);
                this._annotationToolbar.show();
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

            this._currentPage = pageNum;
            this._updatePageControls();
            this._updateZoomDisplay(scale);

            // Update annotation layer for the new page.
            if (this._annotationLayer) {
                this._annotationLayer.setPageSize(
                    Math.round(displayViewport.width),
                    Math.round(displayViewport.height)
                );
                if (isPageChange) {
                    await this._annotationLayer.switchPage(pageNum);
                    // Re-debounce save after page switch updates the in-memory Map.
                    if (this._dirty) {
                        this._scheduleSave();
                    }
                }
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
        this._currentUrl = null;
        super.destroy();
    }
}
