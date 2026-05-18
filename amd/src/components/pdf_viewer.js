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
 * PDF viewer component — continuous-scroll multi-page orchestrator.
 *
 * Renders all PDF pages in a vertically scrollable container using PDF.js.
 * Each visible page gets its own Fabric.js AnnotationLayer instance for
 * annotation editing. IntersectionObserver handles lazy rendering as pages
 * enter the viewport, and far-away pages can be torn down to reclaim memory.
 *
 * @module     local_unifiedgrader/components/pdf_viewer
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent} from 'core/reactive';
import {get_string as getString, get_strings as getStrings} from 'core/str';
import Ajax from 'core/ajax';
import PdfjsLoader from 'local_unifiedgrader/lib/pdfjs_loader';
import FabricLoader from 'local_unifiedgrader/lib/fabric_loader';
import AnnotationLayer from 'local_unifiedgrader/components/annotation_layer';
import AnnotationToolbar from 'local_unifiedgrader/components/annotation_toolbar';
import {loadAnnotations, saveAnnotations, uploadAnnotatedPdf, deleteAnnotatedPdf}
    from 'local_unifiedgrader/annotation/persistence';
import {flattenAnnotatedPdf} from 'local_unifiedgrader/annotation/pdf_flatten';
import PdflibLoader from 'local_unifiedgrader/lib/pdflib_loader';
import * as DirtyTracker from 'local_unifiedgrader/dirty_tracker';
import * as OfflineCache from 'local_unifiedgrader/offline_cache';

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

/** @type {string} IntersectionObserver root margin for pre-loading pages near the viewport. */
const OBSERVER_ROOT_MARGIN = '300px 0px';

export default class PdfViewer extends BaseComponent {

    /**
     * Component creation hook.
     */
    create() {
        this.name = 'pdf_viewer';
        this.selectors = {
            PAGE_CONTAINER: '[data-region="pdf-page-container"]',
            ANNOTATION_TOOLBAR: '[data-region="annotation-toolbar"]',
            LOADING: '[data-region="pdf-loading"]',
            LOADING_MESSAGE: '[data-region="pdf-loading-message"]',
            ERROR_MESSAGE: '[data-region="pdf-error"]',
            CURRENT_PAGE: '[data-region="current-page"]',
            TOTAL_PAGES: '[data-region="total-pages"]',
            ZOOM_LEVEL: '[data-region="zoom-level"]',
            PREV_BTN: '[data-action="prev-page"]',
            NEXT_BTN: '[data-action="next-page"]',
            ZOOM_IN_BTN: '[data-action="zoom-in"]',
            ZOOM_OUT_BTN: '[data-action="zoom-out"]',
            ZOOM_FIT_BTN: '[data-action="zoom-fit"]',
            SEARCH_BAR: '[data-region="pdf-search-bar"]',
            SEARCH_INPUT: '[data-action="search-input"]',
            SEARCH_COUNT: '[data-region="search-count"]',
            SEARCH_PREV: '[data-action="search-prev"]',
            SEARCH_NEXT: '[data-action="search-next"]',
            SEARCH_CLOSE: '[data-action="search-close"]',
            SEARCH_TOGGLE: '[data-action="search-toggle"]',
        };

        // PDF.js state.
        /** @type {?object} PDF.js document proxy. */
        this._pdfDoc = null;
        /** @type {number} Total number of pages. */
        this._totalPages = 0;
        /** @type {?string} URL of the currently loaded PDF. */
        this._currentUrl = null;
        /** @type {?object} PDF.js library reference. */
        this._pdfjsLib = null;
        /** @type {?ArrayBuffer} Original PDF bytes for flattening. */
        this._pdfBytes = null;
        /** @type {?AbortController} Abort controller for in-flight PDF fetch. */
        this._fetchAbortController = null;
        /** @type {?AbortController} Abort controller for in-flight flatten. */
        this._flattenAbortController = null;
        /** @type {?object} Current file metadata. */
        this._fileInfo = null;
        /** @type {Map<number, number>} Cached word counts per file ID. */
        this._wordCountCache = new Map();

        // Zoom state.
        /** @type {number} Current zoom level index. */
        this._zoomIndex = DEFAULT_ZOOM_INDEX;
        /** @type {boolean} Whether fit-to-width mode is active. */
        this._fitToWidth = true;
        /** @type {number} Current display scale factor. */
        this._currentScale = 1.0;

        // Page slot management.
        /** @type {Map<number, object>} Page number → slot object. */
        this._pageSlots = new Map();
        /** @type {?IntersectionObserver} Lazy-render observer. */
        this._observer = null;
        /** @type {number} Currently most-visible page (1-based). */
        this._activePageNum = 1;
        /** @type {?AnnotationLayer} The active page's annotation layer. */
        this._activeAnnotationLayer = null;
        /** @type {?number} RequestAnimationFrame ID for scroll handler. */
        this._scrollRAF = null;
        /** @type {?Function} Bound scroll handler. */
        this._onScroll = null;

        // Central annotation state.
        /** @type {Map<number, object>} Page number → Fabric JSON. */
        this._pageAnnotations = new Map();
        /** @type {Set<number>} Page numbers loaded from backend. */
        this._loadedPageNums = new Set();
        /** @type {boolean} Whether annotations have unsaved changes. */
        this._dirty = false;
        /** @type {boolean} Whether a save is currently in-flight. */
        this._saving = false;
        /** @type {?number} Debounce timer for auto-save. */
        this._saveTimer = null;

        // Shared tool state (propagated to all annotation layers).
        /** @type {?object} Cached Fabric.js library. */
        this._fabricLib = null;
        /** @type {string} Current annotation tool. */
        this._currentTool = 'select';
        /** @type {?string} Current annotation color (null = layer default). */
        this._currentColor = null;
        /** @type {string} Current stamp type. */
        this._currentStamp = 'CHECK';
        /** @type {string} Current shape type. */
        this._currentShape = 'rect';
        /** @type {number} Current brush width. */
        this._brushWidth = 3;
        /** @type {boolean} Whether text selection is enabled. */
        this._textSelectable = false;
        /** @type {boolean} Guard flag for state propagation. */
        this._propagating = false;

        // Annotation toolbar.
        /** @type {?AnnotationToolbar} */
        this._annotationToolbar = null;

        // Persistence context.
        /** @type {number} Course module ID. */
        this._cmid = 0;
        /** @type {number} Student user ID. */
        this._userid = 0;
        /** @type {number} Current file ID. */
        this._fileid = 0;

        // Read-only mode (student feedback view).
        /** @type {boolean} */
        this._readOnly = this.element?.dataset?.readonly === '1';

        // Text search state.
        /** @type {boolean} */
        this._searchOpen = false;
        /** @type {string} */
        this._searchQuery = '';
        /** @type {Array<{page: number}>} */
        this._searchMatches = [];
        /** @type {number} */
        this._searchIndex = -1;
        /** @type {Map<number, object>} Cached text per page. */
        this._pageTextCache = new Map();
        /** @type {?number} Debounce timer for search input. */
        this._searchDebounceTimer = null;

        // Word count tooltip for text selection.
        /** @type {?HTMLElement} */
        this._wordCountTooltip = null;
        /** @type {?Function} Bound mouseup handler for text selection word count. */
        this._onTextSelectMouseUp = null;
        /** @type {?Function} Bound mousedown handler to hide word count tooltip. */
        this._onTextSelectMouseDown = null;

        // Localised strings (loaded asynchronously, fallbacks used until ready).
        /** @type {?object} */
        this._strings = null;
        this._loadStrings();
    }

    /**
     * Load localised strings asynchronously.
     *
     * @private
     */
    async _loadStrings() {
        try {
            const keys = [
                {key: 'search_no_results', component: 'local_unifiedgrader'},
                {key: 'search_x_of_y', component: 'local_unifiedgrader'},
            ];
            const values = await getStrings(keys);
            this._strings = {
                search_no_results: values[0],
                search_x_of_y: values[1],
            };
        } catch (e) {
            // Fallback: leave _strings null so fallback literals are used.
            window.console.warn('[PdfViewer] Failed to load lang strings', e);
        }
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

        // Listen for annotation restoration from the offline cache recovery flow.
        document.addEventListener('unifiedgrader:restoreannotations', (e) => {
            const {fileid, pages} = e.detail || {};
            if (fileid === this._fileid && Array.isArray(pages)) {
                this._pageAnnotations = new Map(pages);
                this._dirty = true;
                DirtyTracker.markDirty('annotations');
                // Re-render currently visible pages with restored annotations.
                for (const [pageNum, slot] of this._pageSlots) {
                    if (slot.annotationLayer && this._pageAnnotations.has(pageNum)) {
                        slot.annotationLayer.loadAnnotations(this._pageAnnotations.get(pageNum));
                    }
                }
                this._scheduleSave();
            }
        });
    }

    /**
     * Bind click handlers for navigation, zoom, keyboard, and search.
     */
    _bindControls() {
        const prevBtn = this.getElement(this.selectors.PREV_BTN);
        const nextBtn = this.getElement(this.selectors.NEXT_BTN);
        const zoomInBtn = this.getElement(this.selectors.ZOOM_IN_BTN);
        const zoomOutBtn = this.getElement(this.selectors.ZOOM_OUT_BTN);
        const zoomFitBtn = this.getElement(this.selectors.ZOOM_FIT_BTN);

        if (prevBtn) {
            prevBtn.addEventListener('click', () => this._goToPage(this._activePageNum - 1));
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', () => this._goToPage(this._activePageNum + 1));
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

        // Keyboard shortcuts (bound to document — canvas doesn't reliably get focus).
        this._onPageKeyDown = (e) => {
            // Ctrl+F / Cmd+F — open search bar.
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                if (this._pdfDoc) {
                    e.preventDefault();
                    this._toggleSearch();
                }
                return;
            }

            // Escape — close search bar.
            if (e.key === 'Escape' && this._searchOpen) {
                e.preventDefault();
                this._closeSearch();
                return;
            }

            // Skip when focus is inside an input/textarea.
            const tag = e.target.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA' || e.target.isContentEditable) {
                return;
            }
            switch (e.key) {
                case 'PageDown':
                    this._goToPage(this._activePageNum + 1);
                    e.preventDefault();
                    break;
                case 'PageUp':
                    this._goToPage(this._activePageNum - 1);
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

        this._bindSearchControls();

        // Editing shortcuts (skip in read-only mode).
        if (!this._readOnly) {
            // Delete key removes selected annotation on whichever page has a selection.
            this._onKeyDown = (e) => {
                if ((e.key === 'Delete' || e.key === 'Backspace')) {
                    if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                        for (const [, slot] of this._pageSlots) {
                            if (slot.annotationLayer && slot.annotationLayer.hasSelection()) {
                                slot.annotationLayer.deleteSelected();
                                e.preventDefault();
                                break;
                            }
                        }
                    }
                }
            };
            document.addEventListener('keydown', this._onKeyDown);

            // Best-effort save on page unload.
            this._onBeforeUnload = () => {
                if (this._dirty && this._fileid) {
                    this._saveAllSlotAnnotations();
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
        if (this._fileid && this._fileid !== fileid && this._dirty) {
            this._saveAllSlotAnnotations();
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
     * Force-save annotations immediately.
     * Called by preview_panel before switching students.
     */
    saveAnnotationsNow() {
        this._saveAllSlotAnnotations();
        // Ensure dirty is set so that even if a save is in-flight,
        // the finally block will re-schedule another save.
        if (this._pageAnnotations.size > 0) {
            this._dirty = true;
        }
        this._saveAnnotationsToBackend();
    }

    // ──────────────────────────────────────────────
    //  PDF loading
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

        // Abort any in-flight fetch from a previous call.
        if (this._fetchAbortController) {
            this._fetchAbortController.abort();
        }
        this._fetchAbortController = new AbortController();

        this._showLoading(true);
        this._showLoadingMessage('');
        this._showError('');

        try {
            // Load PDF.js library.
            if (!this._pdfjsLib) {
                this._pdfjsLib = await PdfjsLoader.load();
            }

            // Tear down previous state.
            this._destroyAllSlots();

            if (this._pdfDoc) {
                this._pdfDoc.destroy();
                this._pdfDoc = null;
            }
            if (this._annotationToolbar) {
                this._annotationToolbar.destroy();
                this._annotationToolbar = null;
            }

            this._dirty = false;
            DirtyTracker.markClean('annotations');
            this._loadedPageNums = new Set();
            this._pageAnnotations = new Map();
            this._pdfBytes = null;
            this._pageTextCache.clear();
            this._closeSearch();
            if (this._saveTimer) {
                clearTimeout(this._saveTimer);
                this._saveTimer = null;
            }

            // Fetch PDF data (handles document conversion polling).
            const pdfData = await this._fetchPdfData(url);
            this._pdfBytes = pdfData.slice(0);

            // Load the PDF document.
            this._pdfDoc = await this._pdfjsLib.getDocument({
                data: pdfData,
                disableRange: true,
                disableStream: true,
            }).promise;

            this._currentUrl = url;
            this._totalPages = this._pdfDoc.numPages;
            this._activePageNum = 1;

            // Load Fabric.js (needed for annotation layer in both modes).
            if (!this._fabricLib) {
                this._fabricLib = await FabricLoader.load();
            }

            // Create placeholder slots for all pages.
            await this._createAllPageSlots();

            // Load annotations from backend into central map (before observer,
            // so pages that render will already have annotation data available).
            if (!this._readOnly) {
                await this._loadAnnotationsFromBackend();
            }

            // Set up scroll listener for active page tracking.
            this._setupScrollListener();

            // Set up IntersectionObserver — visible pages will start rendering.
            this._setupObserver();

            this._updatePageControls();
            this._updateZoomDisplay(this._currentScale);

        } catch (err) {
            if (err.name === 'AbortError') {
                return;
            }
            window.console.error('[pdf_viewer] Failed to load PDF:', err);
            // When the failed URL was a docx → PDF conversion attempt and
            // the error looks conversion-shaped (HTTP 422 from preview_file
            // .php, or our 30-second timeout message), surface a retry
            // affordance instead of a flat error — the underlying converter
            // (Google Docs / unoconv / FlaskOffice) is the most common
            // cause and is often recoverable by re-queuing the job.
            const isConversionUrl = url.includes('preview_file.php') && url.includes('convert=pdf');
            if (isConversionUrl) {
                this._showConversionError(err.message || 'Document conversion failed.', url);
            } else {
                this._showError(err.message || 'Failed to load document.');
            }
        } finally {
            this._showLoading(false);
            this._showLoadingMessage('');
        }
    }

    // ──────────────────────────────────────────────
    //  Page slot management
    // ──────────────────────────────────────────────

    /**
     * Create placeholder DOM slots for all pages with correct dimensions.
     *
     * @returns {Promise<void>}
     */
    async _createAllPageSlots() {
        const container = this.getElement(this.selectors.PAGE_CONTAINER);
        if (!container) {
            return;
        }

        // Calculate scale from the first page.
        const firstPage = await this._pdfDoc.getPage(1);
        if (this._fitToWidth) {
            this._currentScale = this._calculateFitToWidthScale(firstPage);
        } else {
            this._currentScale = ZOOM_LEVELS[this._zoomIndex];
        }

        for (let pageNum = 1; pageNum <= this._totalPages; pageNum++) {
            const page = await this._pdfDoc.getPage(pageNum);
            const viewport = page.getViewport({scale: this._currentScale});
            const slot = this._createPageSlot(pageNum, viewport.width, viewport.height);
            this._pageSlots.set(pageNum, slot);
            container.appendChild(slot.wrapper);
        }
    }

    /**
     * Create a single page slot with all necessary DOM elements.
     *
     * @param {number} pageNum Page number (1-based).
     * @param {number} width Display width in pixels.
     * @param {number} height Display height in pixels.
     * @returns {object} Slot object.
     */
    _createPageSlot(pageNum, width, height) {
        const w = Math.round(width);
        const h = Math.round(height);

        const wrapper = document.createElement('div');
        wrapper.className = 'pdf-page-slot';
        wrapper.dataset.pageNum = pageNum;

        const canvasWrapper = document.createElement('div');
        canvasWrapper.dataset.region = 'pdf-canvas-wrapper';
        canvasWrapper.style.position = 'relative';
        canvasWrapper.style.width = w + 'px';
        canvasWrapper.style.height = h + 'px';
        canvasWrapper.style.backgroundColor = '#fff';
        canvasWrapper.style.boxShadow = '0 1px 3px rgba(0,0,0,0.12)';

        // PDF render canvas.
        const pdfCanvas = document.createElement('canvas');
        pdfCanvas.dataset.region = 'pdf-canvas';
        pdfCanvas.style.width = w + 'px';
        pdfCanvas.style.height = h + 'px';

        // Text layer (for text selection and search highlights).
        const textLayerDiv = document.createElement('div');
        textLayerDiv.dataset.region = 'pdf-text-layer';
        textLayerDiv.style.position = 'absolute';
        textLayerDiv.style.top = '0';
        textLayerDiv.style.left = '0';
        textLayerDiv.style.width = w + 'px';
        textLayerDiv.style.height = h + 'px';
        textLayerDiv.style.lineHeight = '1.0';
        textLayerDiv.style.pointerEvents = 'none';
        textLayerDiv.style.opacity = '1';

        // Annotation canvas (Fabric.js overlay).
        const annotCanvas = document.createElement('canvas');
        annotCanvas.dataset.region = 'annotation-canvas';
        annotCanvas.width = w;
        annotCanvas.height = h;
        annotCanvas.style.width = w + 'px';
        annotCanvas.style.height = h + 'px';
        annotCanvas.style.position = 'absolute';
        annotCanvas.style.top = '0';
        annotCanvas.style.left = '0';

        // Link layer (clickable hyperlinks from PDF).
        const linkLayerDiv = document.createElement('div');
        linkLayerDiv.dataset.region = 'pdf-link-layer';
        linkLayerDiv.style.position = 'absolute';
        linkLayerDiv.style.top = '0';
        linkLayerDiv.style.left = '0';
        linkLayerDiv.style.width = w + 'px';
        linkLayerDiv.style.height = h + 'px';
        linkLayerDiv.style.zIndex = '5';
        linkLayerDiv.style.pointerEvents = 'none';

        canvasWrapper.appendChild(pdfCanvas);
        canvasWrapper.appendChild(textLayerDiv);
        canvasWrapper.appendChild(annotCanvas);
        canvasWrapper.appendChild(linkLayerDiv);
        wrapper.appendChild(canvasWrapper);

        return {
            wrapper,
            canvasWrapper,
            pdfCanvas,
            textLayerDiv,
            linkLayerDiv,
            annotCanvas,
            annotationLayer: null,
            textLayerObj: null,
            rendered: false,
            rendering: false,
        };
    }

    /**
     * Set up IntersectionObserver on all page slots for lazy rendering.
     */
    _setupObserver() {
        if (this._observer) {
            this._observer.disconnect();
        }

        const container = this.getElement(this.selectors.PAGE_CONTAINER);
        if (!container) {
            return;
        }

        this._observer = new IntersectionObserver((entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    const pageNum = parseInt(entry.target.dataset.pageNum, 10);
                    if (pageNum) {
                        this._renderPageIfNeeded(pageNum);
                    }
                }
            }
        }, {
            root: container,
            rootMargin: OBSERVER_ROOT_MARGIN,
            threshold: 0,
        });

        for (const [, slot] of this._pageSlots) {
            this._observer.observe(slot.wrapper);
        }
    }

    /**
     * Set up scroll event listener for active page tracking.
     */
    _setupScrollListener() {
        const container = this.getElement(this.selectors.PAGE_CONTAINER);
        if (!container) {
            return;
        }

        // Remove previous listener if any.
        if (this._onScroll) {
            container.removeEventListener('scroll', this._onScroll);
        }

        this._onScroll = () => {
            if (this._scrollRAF) {
                return;
            }
            this._scrollRAF = requestAnimationFrame(() => {
                this._scrollRAF = null;
                this._updateActivePage();
            });
        };
        container.addEventListener('scroll', this._onScroll, {passive: true});
    }

    /**
     * Render a page if it hasn't been rendered yet.
     * Called by the IntersectionObserver when a page enters the viewport.
     *
     * @param {number} pageNum Page number (1-based).
     * @returns {Promise<void>}
     */
    async _renderPageIfNeeded(pageNum) {
        const slot = this._pageSlots.get(pageNum);
        if (!slot || slot.rendered || slot.rendering) {
            return;
        }

        slot.rendering = true;

        try {
            const page = await this._pdfDoc.getPage(pageNum);
            const dpr = window.devicePixelRatio || 1;
            const viewport = page.getViewport({scale: this._currentScale * dpr});
            const displayViewport = page.getViewport({scale: this._currentScale});

            // Size the PDF canvas (high-res for sharp rendering).
            slot.pdfCanvas.width = viewport.width;
            slot.pdfCanvas.height = viewport.height;
            slot.pdfCanvas.style.width = Math.round(displayViewport.width) + 'px';
            slot.pdfCanvas.style.height = Math.round(displayViewport.height) + 'px';

            // Render PDF page to canvas.
            const ctx = slot.pdfCanvas.getContext('2d');
            await page.render({
                canvasContext: ctx,
                viewport: viewport,
            }).promise;

            // Render text layer and link layer.
            await this._renderTextLayerForSlot(page, displayViewport, slot);
            await this._renderLinkLayerForSlot(page, displayViewport, slot);

            // Initialize annotation layer.
            await this._initAnnotationForSlot(pageNum, slot, displayViewport);

            // Apply text selectability state.
            this._setTextSelectableForSlot(slot, this._textSelectable);

            // Apply search highlights if active.
            if (this._searchQuery) {
                this._highlightSearchMatchesForSlot(pageNum, slot);
            }

            slot.rendered = true;

            // Create toolbar when first annotation layer is ready.
            if (!this._readOnly && !this._annotationToolbar && slot.annotationLayer) {
                const toolbarEl = this.getElement(this.selectors.ANNOTATION_TOOLBAR);
                if (toolbarEl) {
                    this._annotationToolbar = new AnnotationToolbar(toolbarEl, slot.annotationLayer);
                    this._annotationToolbar.show();
                }
                this._activeAnnotationLayer = slot.annotationLayer;
                this._pushDocumentInfo();
            }

        } catch (err) {
            window.console.error('[pdf_viewer] Failed to render page', pageNum, ':', err);
        } finally {
            slot.rendering = false;
        }
    }

    /**
     * Initialize an AnnotationLayer for a page slot.
     *
     * @param {number} pageNum Page number.
     * @param {object} slot Page slot object.
     * @param {object} displayViewport PDF.js viewport at display scale.
     * @returns {Promise<void>}
     */
    async _initAnnotationForSlot(pageNum, slot, displayViewport) {
        if (!this._fabricLib) {
            return;
        }

        const w = Math.round(displayViewport.width);
        const h = Math.round(displayViewport.height);

        const layer = new AnnotationLayer(this._fabricLib, slot.annotCanvas, slot.canvasWrapper, this._readOnly);

        // Pass course code for comment library.
        const coursecode = this.reactive?.state?.activity?.coursecode || '';
        layer.setCourseCode(coursecode);

        layer.setPageSize(w, h);

        // Apply current shared tool state.
        if (this._currentColor !== null) {
            layer.setColor(this._currentColor);
        }
        layer.setStampType(this._currentStamp);
        layer.setShapeType(this._currentShape);
        layer.setBrushWidth(this._brushWidth);
        layer.setTool(this._currentTool);

        // Load annotations from central map.
        const fabricJson = this._pageAnnotations.get(pageNum);
        if (fabricJson) {
            await layer.loadAnnotations(fabricJson);
        }

        // Wire callbacks (skip in read-only mode).
        if (!this._readOnly) {
            layer.onChange(() => {
                this._dirty = true;
                DirtyTracker.markDirty('annotations');
                this._saveSlotAnnotations(pageNum, slot);
                this._scheduleSave();
                this._scheduleCacheWrite();
            });

            layer.onToolChange((tool) => {
                // Guard: when propagating setTool to other layers, their
                // _notifyToolChange fires this callback again. Without this
                // guard we get infinite recursion → stack overflow.
                if (this._propagating) {
                    return;
                }
                this._currentTool = tool;
                this._setTextSelectableAll(tool === 'textselect');
                // Propagate tool to all other layers.
                this._propagating = true;
                for (const [otherNum, otherSlot] of this._pageSlots) {
                    if (otherNum !== pageNum && otherSlot.annotationLayer) {
                        otherSlot.annotationLayer.setTool(tool);
                    }
                }
                this._propagating = false;
            });
        }

        // Wrap set* methods for cross-page state propagation.
        this._wrapLayerStateTracking(layer, pageNum);

        slot.annotationLayer = layer;
    }

    /**
     * Wrap an annotation layer's set* methods to track state and propagate
     * changes to all other rendered layers.
     *
     * @param {AnnotationLayer} layer The annotation layer.
     * @param {number} pageNum The page number for this layer.
     */
    _wrapLayerStateTracking(layer, pageNum) {
        const viewer = this;

        const wrapMethod = (methodName, stateKey) => {
            const original = layer[methodName].bind(layer);
            layer[methodName] = function(value) {
                original(value);
                if (!viewer._propagating) {
                    viewer[stateKey] = value;
                    viewer._propagating = true;
                    for (const [otherNum, otherSlot] of viewer._pageSlots) {
                        if (otherNum !== pageNum && otherSlot.annotationLayer) {
                            otherSlot.annotationLayer[methodName](value);
                        }
                    }
                    viewer._propagating = false;
                }
            };
        };

        wrapMethod('setColor', '_currentColor');
        wrapMethod('setStampType', '_currentStamp');
        wrapMethod('setShapeType', '_currentShape');
        wrapMethod('setBrushWidth', '_brushWidth');
    }

    /**
     * Determine which page is most visible and update active page tracking.
     */
    _updateActivePage() {
        const container = this.getElement(this.selectors.PAGE_CONTAINER);
        if (!container || this._pageSlots.size === 0) {
            return;
        }

        const containerRect = container.getBoundingClientRect();
        const centerY = containerRect.top + containerRect.height / 2;

        let bestPage = this._activePageNum;
        let bestDist = Infinity;

        for (const [pageNum, slot] of this._pageSlots) {
            const slotRect = slot.wrapper.getBoundingClientRect();
            const slotCenterY = slotRect.top + slotRect.height / 2;
            const dist = Math.abs(slotCenterY - centerY);
            if (dist < bestDist) {
                bestDist = dist;
                bestPage = pageNum;
            }
        }

        if (bestPage !== this._activePageNum) {
            this._activePageNum = bestPage;
            this._updatePageControls();

            // Re-bind toolbar to the new active page's layer.
            const slot = this._pageSlots.get(bestPage);
            if (slot && slot.annotationLayer && this._annotationToolbar) {
                this._activeAnnotationLayer = slot.annotationLayer;
                this._annotationToolbar.setLayer(slot.annotationLayer);
            }
        }
    }

    /**
     * Scroll to a specific page.
     *
     * @param {number} pageNum Target page number (1-based).
     */
    _goToPage(pageNum) {
        if (pageNum < 1 || pageNum > this._totalPages) {
            return;
        }
        const slot = this._pageSlots.get(pageNum);
        if (slot) {
            slot.wrapper.scrollIntoView({behavior: 'smooth', block: 'start'});
        }
    }

    /**
     * Destroy all page slots and clean up observers.
     */
    _destroyAllSlots() {
        if (this._observer) {
            this._observer.disconnect();
            this._observer = null;
        }

        const container = this.getElement(this.selectors.PAGE_CONTAINER);
        if (container && this._onScroll) {
            container.removeEventListener('scroll', this._onScroll);
            this._onScroll = null;
        }

        if (this._scrollRAF) {
            cancelAnimationFrame(this._scrollRAF);
            this._scrollRAF = null;
        }

        for (const [, slot] of this._pageSlots) {
            if (slot.annotationLayer) {
                slot.annotationLayer.destroy();
            }
            if (slot.textLayerObj) {
                slot.textLayerObj.cancel();
            }
            slot.wrapper.remove();
        }

        this._pageSlots.clear();
        this._activeAnnotationLayer = null;
    }

    // ──────────────────────────────────────────────
    //  Annotation state management
    // ──────────────────────────────────────────────

    /**
     * Save a single slot's annotation state to the central map.
     *
     * @param {number} pageNum Page number.
     * @param {object} slot Page slot object.
     */
    _saveSlotAnnotations(pageNum, slot) {
        if (!slot.annotationLayer) {
            return;
        }
        const state = slot.annotationLayer.getCurrentPageState();
        if (state) {
            this._pageAnnotations.set(pageNum, state);
        } else {
            this._pageAnnotations.delete(pageNum);
        }
    }

    /**
     * Save all rendered slot annotations to the central map.
     */
    _saveAllSlotAnnotations() {
        for (const [pageNum, slot] of this._pageSlots) {
            if (slot.annotationLayer && slot.rendered) {
                this._saveSlotAnnotations(pageNum, slot);
            }
        }
    }

    /**
     * Load annotations from the backend into the central map.
     *
     * @returns {Promise<void>}
     */
    async _loadAnnotationsFromBackend() {
        if (!this._fileid) {
            return;
        }

        try {
            const annotations = await loadAnnotations(this._cmid, this._userid, this._fileid);

            this._loadedPageNums = new Set();

            annotations.forEach((annot) => {
                try {
                    const json = JSON.parse(annot.annotationdata);
                    this._pageAnnotations.set(annot.pagenum, json);
                    this._loadedPageNums.add(annot.pagenum);
                } catch (e) {
                    window.console.warn('[pdf_viewer] Invalid annotation JSON for page', annot.pagenum, e);
                }
            });

        } catch (err) {
            window.console.error('[pdf_viewer] Failed to load annotations:', err);
        }
    }

    /**
     * Schedule a debounced cache write to IndexedDB. Longer interval than server save
     * to avoid excessive IDB writes during rapid drawing.
     */
    _scheduleCacheWrite() {
        if (this._cacheWriteTimer) {
            clearTimeout(this._cacheWriteTimer);
        }
        this._cacheWriteTimer = setTimeout(() => {
            this._cacheWriteTimer = null;
            if (this._cmid && this._userid && this._fileid) {
                this._saveAllSlotAnnotations();
                OfflineCache.save(this._cmid, this._userid, 'annotations', {
                    fileid: this._fileid,
                    pages: [...this._pageAnnotations.entries()],
                });
            }
        }, 3000);
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
        if (!this._fileid || !this._dirty) {
            return;
        }

        // Prevent concurrent saves — if a save is already in-flight, let it
        // finish; the finally block will re-schedule if _dirty was re-set.
        if (this._saving) {
            return;
        }

        if (this._saveTimer) {
            clearTimeout(this._saveTimer);
            this._saveTimer = null;
        }

        // Clear dirty BEFORE the await so that any new annotations created
        // during the network request will correctly re-set _dirty to true.
        this._dirty = false;
        this._saving = true;

        // Flush all rendered slot states to the central map.
        this._saveAllSlotAnnotations();

        try {
            const pages = [];

            for (const [pageNum, fabricJson] of this._pageAnnotations) {
                pages.push({
                    pagenum: pageNum,
                    annotationdata: JSON.stringify(fabricJson),
                });
            }

            // Send empty data for pages that were loaded but no longer have annotations.
            for (const loadedPageNum of this._loadedPageNums) {
                if (!this._pageAnnotations.has(loadedPageNum)) {
                    pages.push({
                        pagenum: loadedPageNum,
                        annotationdata: '',
                    });
                }
            }

            if (pages.length === 0) {
                return;
            }

            await saveAnnotations(this._cmid, this._userid, this._fileid, pages);

            this._loadedPageNums = new Set(this._pageAnnotations.keys());
            DirtyTracker.markClean('annotations');
            if (this._cmid && this._userid) {
                OfflineCache.remove(this._cmid, this._userid, 'annotations');
            }

            // Trigger flattened PDF generation in the background.
            this._flattenAndUpload();

        } catch (err) {
            // Re-mark dirty so the next scheduled save retries via _scheduleSave().
            // Annotations self-retry via the _dirty flag, so we do NOT enqueue in
            // the SaveQueue (which would create duplicate entries that persist).
            this._dirty = true;
            DirtyTracker.markDirty('annotations');
            window.console.warn('[pdf_viewer] Failed to save annotations, will retry:', err);
        } finally {
            this._saving = false;
            // If new annotations were created during the save, schedule another.
            if (this._dirty) {
                this._scheduleSave();
            }
        }
    }

    // ──────────────────────────────────────────────
    //  Flattened PDF generation
    // ──────────────────────────────────────────────

    /**
     * Generate a flattened annotated PDF and upload to Moodle file storage.
     */
    async _flattenAndUpload() {
        if (this._flattenAbortController) {
            this._flattenAbortController.abort();
        }
        this._flattenAbortController = new AbortController();
        const signal = this._flattenAbortController.signal;

        const cmid = this._cmid;
        const userid = this._userid;
        const fileid = this._fileid;
        const pdfBytes = this._pdfBytes;

        if (!pdfBytes || !fileid) {
            return;
        }

        // Snapshot current annotations.
        const annotationSnapshot = new Map(this._pageAnnotations);

        let hasAnnotations = false;
        for (const [, json] of annotationSnapshot) {
            if (json.objects && json.objects.length > 0) {
                hasAnnotations = true;
                break;
            }
        }

        if (!hasAnnotations) {
            try {
                await deleteAnnotatedPdf(cmid, userid, fileid);
            } catch (e) {
                window.console.warn('[pdf_viewer] Failed to delete annotated PDF:', e);
            }
            return;
        }

        try {
            const [PDFLib, fabricLib] = await Promise.all([
                PdflibLoader.load(),
                FabricLoader.load(),
            ]);

            if (signal.aborted) {
                return;
            }

            // pageDimensions empty — flatten uses _viewportWidth/_viewportHeight from JSON.
            const pageDimensions = new Map();

            const flattenedBytes = await flattenAnnotatedPdf(
                pdfBytes, annotationSnapshot, pageDimensions, fabricLib, PDFLib,
            );

            if (signal.aborted) {
                return;
            }

            const base64 = _arrayBufferToBase64(flattenedBytes);
            await uploadAnnotatedPdf(cmid, userid, fileid, base64, 'annotated.pdf');

        } catch (err) {
            if (err.name === 'AbortError') {
                return;
            }
            window.console.error('[pdf_viewer] Failed to flatten/upload annotated PDF:', err);
        }
    }

    // ──────────────────────────────────────────────
    //  Zoom
    // ──────────────────────────────────────────────

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
        this._applyZoom();
    }

    /**
     * Reset zoom to fit the page width within the container.
     */
    _zoomFitToWidth() {
        this._fitToWidth = true;
        this._applyZoom();
    }

    /**
     * Apply the current zoom level by rebuilding all page slots.
     *
     * @returns {Promise<void>}
     */
    async _applyZoom() {
        if (!this._pdfDoc) {
            return;
        }

        // Record scroll position relative to active page.
        const scrollRef = this._getScrollReference();

        // Flush all annotation states to central map.
        this._saveAllSlotAnnotations();

        // Destroy all slots (DOM + layers).
        this._destroyAllSlots();

        // Recreate slots at new scale.
        await this._createAllPageSlots();

        // Re-setup observers and listeners.
        this._setupScrollListener();
        this._setupObserver();

        // Restore scroll position.
        this._restoreScrollPosition(scrollRef);

        this._updatePageControls();
        this._updateZoomDisplay(this._currentScale);
    }

    /**
     * Capture current scroll position as a page reference.
     *
     * @returns {{pageNum: number, proportion: number}}
     */
    _getScrollReference() {
        const container = this.getElement(this.selectors.PAGE_CONTAINER);
        if (!container) {
            return {pageNum: 1, proportion: 0};
        }
        const activeSlot = this._pageSlots.get(this._activePageNum);
        if (!activeSlot) {
            return {pageNum: 1, proportion: 0};
        }

        const containerRect = container.getBoundingClientRect();
        const slotRect = activeSlot.wrapper.getBoundingClientRect();
        const offset = containerRect.top - slotRect.top;
        const proportion = slotRect.height > 0 ? offset / slotRect.height : 0;

        return {pageNum: this._activePageNum, proportion};
    }

    /**
     * Restore scroll position from a previously captured reference.
     *
     * @param {object} ref Scroll reference from _getScrollReference.
     */
    _restoreScrollPosition(ref) {
        const slot = this._pageSlots.get(ref.pageNum);
        if (!slot) {
            return;
        }
        const container = this.getElement(this.selectors.PAGE_CONTAINER);
        if (!container) {
            return;
        }

        const containerRect = container.getBoundingClientRect();
        const slotRect = slot.wrapper.getBoundingClientRect();
        const targetOffset = ref.proportion * slotRect.height;
        const currentOffset = containerRect.top - slotRect.top;

        container.scrollTop += currentOffset - targetOffset;
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

    // ──────────────────────────────────────────────
    //  Document info
    // ──────────────────────────────────────────────

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
            // Some PDFs have no metadata.
        }

        const info = {
            filename: this._fileInfo?.filename || '',
            filesize: this._fileInfo?.filesize || 0,
            pages: this._totalPages,
            metadata: metadata,
        };

        const fileid = this._fileid;
        const wordCountFn = () => this._getWordCount(fileid);

        this._annotationToolbar.setDocumentInfo(info, wordCountFn);
    }

    /**
     * Count words across all pages of the current PDF.
     *
     * @param {number} fileid File ID to verify context.
     * @returns {Promise<number>} Total word count.
     */
    async _getWordCount(fileid) {
        if (this._wordCountCache.has(fileid)) {
            return this._wordCountCache.get(fileid);
        }

        if (!this._pdfDoc) {
            return 0;
        }

        let totalWords = 0;
        for (let i = 1; i <= this._pdfDoc.numPages; i++) {
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

    // ──────────────────────────────────────────────
    //  Text layer and link layer (per-slot)
    // ──────────────────────────────────────────────

    /**
     * Render the PDF.js text layer for a page slot.
     *
     * @param {object} page PDF.js page proxy.
     * @param {object} viewport Display viewport.
     * @param {object} slot Page slot object.
     * @returns {Promise<void>}
     */
    async _renderTextLayerForSlot(page, viewport, slot) {
        if (slot.textLayerObj) {
            slot.textLayerObj.cancel();
            slot.textLayerObj = null;
        }
        slot.textLayerDiv.innerHTML = '';

        slot.textLayerDiv.style.width = viewport.width + 'px';
        slot.textLayerDiv.style.height = viewport.height + 'px';

        // PDF.js v4.x TextLayer needs this CSS variable for font sizing.
        slot.textLayerDiv.style.setProperty('--scale-factor', viewport.scale);

        if (typeof this._pdfjsLib.TextLayer !== 'function') {
            return;
        }

        try {
            const textContent = await page.getTextContent();
            slot.textLayerObj = new this._pdfjsLib.TextLayer({
                textContentSource: textContent,
                container: slot.textLayerDiv,
                viewport: viewport,
            });
            await slot.textLayerObj.render();

            const spans = slot.textLayerDiv.querySelectorAll('span');
            spans.forEach((span) => {
                span.style.color = 'transparent';
            });
        } catch (err) {
            window.console.warn('[pdf_viewer] Failed to render text layer:', err);
        }

        if (this._readOnly) {
            slot.textLayerDiv.classList.add('text-selectable');
        }
    }

    /**
     * Render clickable hyperlink elements for a page slot.
     *
     * @param {object} page PDF.js page proxy.
     * @param {object} viewport Display viewport.
     * @param {object} slot Page slot object.
     * @returns {Promise<void>}
     */
    async _renderLinkLayerForSlot(page, viewport, slot) {
        slot.linkLayerDiv.innerHTML = '';
        slot.linkLayerDiv.style.width = viewport.width + 'px';
        slot.linkLayerDiv.style.height = viewport.height + 'px';

        try {
            const annotations = await page.getAnnotations();
            const ALLOWED_LINK_PROTOCOLS = /^https?:|^mailto:/i;
            for (const annot of annotations) {
                const linkUrl = annot.url || annot.unsafeUrl;
                if (annot.subtype !== 'Link' || !linkUrl || !ALLOWED_LINK_PROTOCOLS.test(linkUrl)) {
                    continue;
                }

                const rect = viewport.convertToViewportRectangle(annot.rect);
                const [x1, y1, x2, y2] = this._pdfjsLib.Util.normalizeRect(rect);

                const link = document.createElement('a');
                link.href = linkUrl;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.title = linkUrl;
                link.style.position = 'absolute';
                link.style.left = Math.round(x1) + 'px';
                link.style.top = Math.round(y1) + 'px';
                link.style.width = Math.round(x2 - x1) + 'px';
                link.style.height = Math.round(y2 - y1) + 'px';
                link.style.pointerEvents = 'auto';
                link.style.cursor = 'pointer';
                link.style.opacity = '0';

                slot.linkLayerDiv.appendChild(link);
            }
        } catch (err) {
            window.console.warn('[pdf_viewer] Failed to render link layer:', err);
        }
    }

    // ──────────────────────────────────────────────
    //  Text selection
    // ──────────────────────────────────────────────

    /**
     * Enable or disable text selection on all rendered pages.
     *
     * @param {boolean} enabled Whether text selection should be enabled.
     */
    setTextSelectable(enabled) {
        this._setTextSelectableAll(enabled);
    }

    /**
     * Apply text selectability to all rendered page slots.
     *
     * @param {boolean} enabled Whether text selection should be enabled.
     */
    _setTextSelectableAll(enabled) {
        this._textSelectable = enabled;
        for (const [, slot] of this._pageSlots) {
            if (slot.rendered) {
                this._setTextSelectableForSlot(slot, enabled);
            }
        }
        this._toggleWordCountListeners(enabled);
    }

    /**
     * Apply text selectability to a single page slot.
     *
     * @param {object} slot Page slot object.
     * @param {boolean} enabled Whether text selection should be enabled.
     */
    _setTextSelectableForSlot(slot, enabled) {
        const textLayerDiv = slot.textLayerDiv;
        const wrapper = slot.canvasWrapper;
        if (!textLayerDiv || !wrapper) {
            return;
        }

        textLayerDiv.classList.toggle('text-selectable', enabled);
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

        // Reorder text layer relative to Fabric.js canvas-container.
        const canvasContainer = wrapper.querySelector('.canvas-container');
        if (!canvasContainer) {
            return;
        }

        if (enabled) {
            const linkLayer = slot.linkLayerDiv;
            if (linkLayer && linkLayer.parentNode === wrapper) {
                wrapper.insertBefore(textLayerDiv, linkLayer);
            } else {
                canvasContainer.after(textLayerDiv);
            }
        } else {
            wrapper.insertBefore(textLayerDiv, canvasContainer);
        }
    }

    // ──────────────────────────────────────────────
    //  Word count tooltip (text selection)
    // ──────────────────────────────────────────────

    /**
     * Attach or remove mouseup/mousedown listeners for the word count tooltip.
     *
     * @param {boolean} enabled Whether text-select mode is active.
     */
    _toggleWordCountListeners(enabled) {
        const container = this.getElement(this.selectors.PAGE_CONTAINER);
        if (!container) {
            return;
        }

        if (enabled) {
            if (!this._onTextSelectMouseUp) {
                this._onTextSelectMouseUp = (e) => this._handleTextSelectMouseUp(e);
                this._onTextSelectMouseDown = () => this._hideWordCountTooltip();
            }
            container.addEventListener('mouseup', this._onTextSelectMouseUp);
            container.addEventListener('mousedown', this._onTextSelectMouseDown);
        } else {
            this._hideWordCountTooltip();
            if (this._onTextSelectMouseUp) {
                container.removeEventListener('mouseup', this._onTextSelectMouseUp);
                container.removeEventListener('mousedown', this._onTextSelectMouseDown);
            }
        }
    }

    /**
     * Handle mouseup in text-select mode — show word count if text is selected.
     *
     * @param {MouseEvent} e The mouseup event.
     */
    _handleTextSelectMouseUp(e) {
        const sel = window.getSelection();
        const text = sel ? sel.toString().trim() : '';
        if (!text) {
            this._hideWordCountTooltip();
            return;
        }

        // Count words: split on whitespace runs, filter out empty tokens.
        const words = text.split(/\s+/).filter((w) => w.length > 0);
        const count = words.length;
        if (count === 0) {
            this._hideWordCountTooltip();
            return;
        }

        this._showWordCountTooltip(count, e);
    }

    /**
     * Show the word count tooltip near the mouse position.
     *
     * @param {number} count Number of words selected.
     * @param {MouseEvent} e The mouseup event (for positioning).
     */
    _showWordCountTooltip(count, e) {
        this._hideWordCountTooltip();

        const container = this.getElement(this.selectors.PAGE_CONTAINER);
        if (!container) {
            return;
        }

        const tooltip = document.createElement('div');
        tooltip.className = 'text-selection-wordcount';

        const label = count === 1 ? 'word' : 'words';
        tooltip.textContent = `${count.toLocaleString()} ${label}`;

        // Position relative to the scrollable page container.
        const rect = container.getBoundingClientRect();
        let left = e.clientX - rect.left + container.scrollLeft + 12;
        let top = e.clientY - rect.top + container.scrollTop - 30;

        // Clamp so tooltip stays within the container viewport.
        if (top < container.scrollTop) {
            top = e.clientY - rect.top + container.scrollTop + 16;
        }

        tooltip.style.left = left + 'px';
        tooltip.style.top = top + 'px';

        container.appendChild(tooltip);
        this._wordCountTooltip = tooltip;
    }

    /**
     * Hide the word count tooltip if visible.
     */
    _hideWordCountTooltip() {
        if (this._wordCountTooltip) {
            this._wordCountTooltip.remove();
            this._wordCountTooltip = null;
        }
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
     * Close the search bar and clear all search state.
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
     * @returns {Promise<{fullText: string, itemRanges: Array}>}
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
     * Perform a search across all pages.
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

        if (this._searchIndex >= 0) {
            const match = this._searchMatches[this._searchIndex];
            const slot = this._pageSlots.get(match.page);
            if (slot) {
                slot.wrapper.scrollIntoView({behavior: 'smooth', block: 'start'});
                if (!slot.rendered) {
                    await this._renderPageIfNeeded(match.page);
                }
            }
            this._highlightSearchMatches();
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

        if (this._searchIndex < 0) {
            this._searchIndex = 0;
        } else {
            this._searchIndex = (this._searchIndex + delta + this._searchMatches.length)
                % this._searchMatches.length;
        }

        this._updateSearchCount();

        const match = this._searchMatches[this._searchIndex];
        const slot = this._pageSlots.get(match.page);
        if (!slot) {
            return;
        }

        // Highlight on all rendered pages.
        this._highlightSearchMatches();

        // If the target page isn't rendered, scroll there and force-render.
        if (!slot.rendered) {
            slot.wrapper.scrollIntoView({behavior: 'smooth', block: 'start'});
            this._renderPageIfNeeded(match.page).then(() => {
                this._highlightSearchMatchesForSlot(match.page, slot);
            });
        }
    }

    /**
     * Highlight search matches on all rendered page slots.
     */
    _highlightSearchMatches() {
        for (const [pageNum, slot] of this._pageSlots) {
            if (slot.rendered) {
                this._highlightSearchMatchesForSlot(pageNum, slot);
            }
        }
    }

    /**
     * Highlight search matches on a single page slot.
     *
     * @param {number} pageNum Page number.
     * @param {object} slot Page slot object.
     */
    _highlightSearchMatchesForSlot(pageNum, slot) {
        const spans = slot.textLayerDiv.querySelectorAll('span');
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

        // Build concatenated text from DOM spans.
        let fullText = '';
        const spanRanges = [];
        spans.forEach((span, idx) => {
            const start = fullText.length;
            fullText += span.textContent;
            spanRanges.push({start, end: fullText.length, idx});
        });

        const queryLower = this._searchQuery.toLowerCase();
        const textLower = fullText.toLowerCase();

        // Determine which local match on this page is "active".
        let activeLocalIndex = -1;
        if (this._searchIndex >= 0 && this._searchMatches[this._searchIndex]?.page === pageNum) {
            let firstOnPage = -1;
            for (let i = 0; i < this._searchMatches.length; i++) {
                if (this._searchMatches[i].page === pageNum) {
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

            let firstSpan = null;
            for (const range of spanRanges) {
                if (range.end > pos && range.start < matchEnd) {
                    spans[range.idx].style.backgroundColor = bgColor;
                    if (!firstSpan) {
                        firstSpan = spans[range.idx];
                    }
                }
            }

            if (isActive && firstSpan) {
                firstSpan.scrollIntoView({block: 'center', behavior: 'smooth'});
            }

            pos += 1;
            localIndex++;
        }
    }

    /**
     * Remove all search highlight styling from all rendered page slots.
     */
    _clearSearchHighlights() {
        for (const [, slot] of this._pageSlots) {
            if (slot.rendered && slot.textLayerDiv) {
                const spans = slot.textLayerDiv.querySelectorAll('span');
                spans.forEach((span) => {
                    span.style.backgroundColor = '';
                });
            }
        }
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
                countEl.textContent = (this._strings?.search_no_results || '0 results');
            } else {
                const current = this._searchIndex + 1;
                const total = this._searchMatches.length;
                countEl.textContent = (this._strings?.search_x_of_y || (current + ' of ' + total))
                    .replace('{$a->current}', current)
                    .replace('{$a->total}', total);
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

    // ──────────────────────────────────────────────
    //  PDF data fetching (with conversion polling)
    // ──────────────────────────────────────────────

    /**
     * Fetch PDF data from a URL, handling document conversion responses.
     *
     * @param {string} url The PDF URL.
     * @returns {Promise<ArrayBuffer>} The PDF data.
     */
    async _fetchPdfData(url) {
        const signal = this._fetchAbortController?.signal;
        const convertingMsg = await getString('converting_file', 'local_unifiedgrader');

        for (let attempt = 0; attempt <= CONVERSION_MAX_RETRIES; attempt++) {
            const response = await fetch(url, {credentials: 'same-origin', signal});

            if (response.ok && response.headers.get('content-type')?.includes('application/pdf')) {
                return response.arrayBuffer();
            }

            if (response.status === 202) {
                if (attempt >= CONVERSION_MAX_RETRIES) {
                    break;
                }
                this._showLoadingMessage(convertingMsg);
                await new Promise((resolve, reject) => {
                    const timer = setTimeout(resolve, CONVERSION_RETRY_DELAY_MS);
                    signal?.addEventListener('abort', () => {
                        clearTimeout(timer);
                        reject(new DOMException('Aborted', 'AbortError'));
                    }, {once: true});
                });
                continue;
            }

            if (response.status === 422) {
                let errorMsg = '';
                try {
                    const json = await response.json();
                    errorMsg = json.error || '';
                } catch {
                    // Ignore.
                }
                throw new Error(errorMsg || 'Document conversion failed.');
            }

            if (response.ok) {
                return response.arrayBuffer();
            }

            throw new Error('Failed to load document (HTTP ' + response.status + ').');
        }

        const timeoutMsg = await getString('conversion_timeout', 'local_unifiedgrader');
        throw new Error(timeoutMsg);
    }

    // ──────────────────────────────────────────────
    //  UI helpers
    // ──────────────────────────────────────────────

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
     * Show or clear a status message in the loading overlay.
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
     * Show or clear an error message overlay.
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
     * Render a conversion-failed error with a "Retry conversion" button.
     * The button asks the server to drop the cached failed-conversion row
     * (so the next preview attempt re-runs the converter) and then re-fires
     * the PDF load with the same URL.
     *
     * @param {string} text The user-facing error message.
     * @param {string} url The conversion URL the viewer was trying to load.
     */
    _showConversionError(text, url) {
        const el = this.getElement(this.selectors.ERROR_MESSAGE);
        if (!el) {
            return;
        }
        el.textContent = '';
        el.classList.remove('d-none');

        const msg = document.createElement('div');
        msg.className = 'mb-2';
        msg.textContent = text;
        el.appendChild(msg);

        // Pull the args the WS needs straight out of the failed URL.
        let fileid = 0;
        let cmid = 0;
        try {
            const u = new URL(url, window.location.origin);
            fileid = parseInt(u.searchParams.get('fileid') || '0', 10);
            cmid = parseInt(u.searchParams.get('cmid') || '0', 10);
        } catch {
            // Malformed URL — fall through to a plain error, no retry.
        }
        if (!fileid || !cmid) {
            return;
        }

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-primary';
        btn.innerHTML = '<i class="fa fa-refresh me-1"></i>Retry conversion';
        getString('retry_conversion', 'local_unifiedgrader').then((s) => {
            btn.innerHTML = '<i class="fa fa-refresh me-1"></i>' + s;
            return s;
        }).catch(() => {});
        btn.addEventListener('click', () => this._retryConversion(fileid, cmid, url, btn));
        el.appendChild(btn);
    }

    /**
     * Drop the cached failed-conversion row server-side and re-fire the
     * PDF load. The retry button is disabled while in flight to prevent
     * a rapid double-click from triggering two parallel conversions.
     *
     * @param {number} fileid Source file ID.
     * @param {number} cmid Course module ID.
     * @param {string} url The PDF preview URL to re-fetch after the cache clear.
     * @param {HTMLButtonElement} btn The retry button (for in-flight state).
     */
    async _retryConversion(fileid, cmid, url, btn) {
        btn.disabled = true;
        try {
            await Ajax.call([{
                methodname: 'local_unifiedgrader_retry_file_conversion',
                args: {cmid, fileid},
            }])[0];
            // Clear the early-return guard in loadPdf — it would otherwise
            // skip the re-fetch because the URL hasn't changed.
            this._currentUrl = null;
            this._showError('');
            await this.loadPdf(url);
        } catch (err) {
            window.console.error('[pdf_viewer] Retry conversion failed:', err);
            this._showError(err.message || 'Retry failed.');
        } finally {
            btn.disabled = false;
        }
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
            currentEl.textContent = this._activePageNum;
        }
        if (totalEl) {
            totalEl.textContent = this._totalPages;
        }
        if (prevBtn) {
            prevBtn.disabled = this._activePageNum <= 1;
        }
        if (nextBtn) {
            nextBtn.disabled = this._activePageNum >= this._totalPages;
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

    // ──────────────────────────────────────────────
    //  Public API
    // ──────────────────────────────────────────────

    /**
     * Get the currently active page number.
     *
     * @returns {number} Current page (1-based).
     */
    getCurrentPage() {
        return this._activePageNum;
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
     * Set annotation data for a specific page.
     * Used by feedback_viewer.js to load read-only annotations.
     *
     * @param {number} pageNum Page number (1-based).
     * @param {object} fabricJson Fabric.js canvas JSON.
     */
    setPageAnnotations(pageNum, fabricJson) {
        this._pageAnnotations.set(pageNum, fabricJson);
    }

    /**
     * Reload annotation overlays on all currently rendered pages.
     * Used by feedback_viewer.js after loading annotations.
     *
     * @returns {Promise<void>}
     */
    async refreshRenderedAnnotations() {
        for (const [pageNum, slot] of this._pageSlots) {
            if (slot.rendered && slot.annotationLayer) {
                const json = this._pageAnnotations.get(pageNum);
                if (json) {
                    await slot.annotationLayer.loadAnnotations(json);
                }
            }
        }
    }

    // ──────────────────────────────────────────────
    //  Cleanup
    // ──────────────────────────────────────────────

    /**
     * Clean up resources when component is destroyed.
     */
    destroy() {
        if (this._fetchAbortController) {
            this._fetchAbortController.abort();
            this._fetchAbortController = null;
        }

        // Save any pending annotations.
        if (this._dirty && this._fileid) {
            this._saveAllSlotAnnotations();
            this._saveAnnotationsToBackend();
        }

        if (this._saveTimer) {
            clearTimeout(this._saveTimer);
            this._saveTimer = null;
        }

        this._destroyAllSlots();

        if (this._annotationToolbar) {
            this._annotationToolbar.destroy();
            this._annotationToolbar = null;
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

        if (this._pdfDoc) {
            this._pdfDoc.destroy();
            this._pdfDoc = null;
        }

        if (this._flattenAbortController) {
            this._flattenAbortController.abort();
            this._flattenAbortController = null;
        }

        this._pdfBytes = null;
        this._currentUrl = null;
        this._pageAnnotations.clear();

        if (this._searchDebounceTimer) {
            clearTimeout(this._searchDebounceTimer);
            this._searchDebounceTimer = null;
        }
        this._pageTextCache.clear();
        this._searchMatches = [];

        this._toggleWordCountListeners(false);

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
