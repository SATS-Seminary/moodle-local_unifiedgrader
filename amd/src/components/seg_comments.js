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
 * Segment-anchored marks component (Phase 2, grader side).
 *
 * Turns the translated submission view into a marking surface. Every aligned
 * phrase of the translation is wrapped, in place, in a segment span so the
 * grader can act on it. A small tool strip offers:
 *   - Select / Comment: highlight or click a phrase to attach a text comment,
 *     shown as a numbered pin joined by a dotted leader line to a card in a
 *     right-hand margin gutter (the library picker is available in the composer).
 *   - Tick / Cross / Highlight / Query: click a phrase to drop that mark on it.
 *
 * Every mark — comment or stamp — anchors to the student's ORIGINAL source text
 * (the selected translation phrase maps to its source segment id(s) via the
 * alignment, and local_nida computes the durable char-offset anchor). So marks
 * survive reflow and re-translation and can be shown to the student, unlike a
 * pixel-anchored canvas annotation. Freehand/shape tools are intentionally not
 * offered here because they cannot anchor to text; the warning next to the
 * search tool explains this.
 *
 * translation_panel owns the translated body DOM; this component only decorates
 * it (segment spans, marks, gutter, leader lines) bracketed by an observer
 * disconnect, and re-decorates whenever translation_panel re-renders.
 *
 * @module     local_unifiedgrader/components/seg_comments
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {BaseComponent} from 'core/reactive';
import {get_string as getString} from 'core/str';
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import renderDocInfo from '../lib/doc_info';

/** Class on a translated phrase span (the click/hit target). */
const SEG_CLASS = 'local-unifiedgrader-seg';
/** Class on a phrase that carries a text comment. */
const MARK_CLASS = 'local-unifiedgrader-seg-commented';
/** SVG namespace. */
const SVGNS = 'http://www.w3.org/2000/svg';

/**
 * Per-stamp presentation (comment is handled separately). A stamp with an empty
 * glyph renders as a text style only (no inline badge) — strikethrough draws a
 * line through the marked text rather than adding a badge.
 */
const STAMPS = {
    // Tick/cross labels are a matched pair, each readable on its own: a bare
    // "Correct" on a ✗ can be taken as "this is correct" (see segmark_cross).
    tick: {glyph: '✓', cls: 'local-unifiedgrader-mark-tick', key: 'segmark_tick', fallback: 'Good point'},
    cross: {glyph: '✗', cls: 'local-unifiedgrader-mark-cross', key: 'segmark_cross', fallback: 'Needs correction'},
    highlight: {glyph: '▨', cls: 'local-unifiedgrader-mark-highlight', key: 'segmark_highlight', fallback: 'Highlight'},
    query: {glyph: '?', cls: 'local-unifiedgrader-mark-query', key: 'segmark_query', fallback: 'Query'},
    strikethrough: {glyph: '', cls: 'local-unifiedgrader-mark-strike', key: 'segmark_strike', fallback: 'Strike out'},
};

/**
 * Fabric shape tools (PDF only), in popout order.
 *
 * The last two are stamps rather than drawn shapes: a tick or cross dropped at a
 * click, with no text to anchor to. The text-anchored marks in the strip cover
 * commenting on wording, but a cover page, a diagram or a signature block has
 * nothing to bind to — so those live here, where a free-floating mark is expected.
 * An entry carrying `stamp` drives the stamp tool; `shape` drives the shape tool.
 */
const SHAPES = [
    {shape: 'rect', icon: 'fa-square-o', key: 'annotate_shape_rect', fallback: 'Rectangle'},
    {shape: 'circle', icon: 'fa-circle-o', key: 'annotate_shape_circle', fallback: 'Ellipse'},
    {shape: 'line', icon: 'fa-minus', key: 'annotate_shape_line', fallback: 'Line'},
    {shape: 'arrow', icon: 'fa-long-arrow-right', key: 'annotate_shape_arrow', fallback: 'Arrow'},
    {shape: 'stamp-check', stamp: 'CHECK', icon: 'fa-check', key: 'annotate_stamp_tick', fallback: 'Tick stamp'},
    {shape: 'stamp-cross', stamp: 'CROSS', icon: 'fa-times', key: 'annotate_stamp_cross', fallback: 'Cross stamp'},
];

/** Freehand pen brush widths (px), matching the classic annotation toolbar. */
const PEN_WIDTHS = [
    {width: 1, key: 'annotate_pen_fine', fallback: 'Fine'},
    {width: 3, key: 'annotate_pen_medium', fallback: 'Medium'},
    {width: 8, key: 'annotate_pen_thick', fallback: 'Thick'},
];

/** The shape/drawing colour palette (matches the classic annotation toolbar). */
const COLORS = [
    // WCAG-tuned: the pin number's ink is auto-chosen (_textOn) so red/green/blue
    // keep a white number while yellow stays bright (dark number). See types.js.
    {color: '#D0342C', key: 'annotate_red', fallback: 'Red'},
    {color: '#F2C200', key: 'annotate_yellow', fallback: 'Yellow'},
    {color: '#2E7D32', key: 'annotate_green', fallback: 'Green'},
    {color: '#5D779E', key: 'annotate_blue', fallback: 'Blue'},
    {color: '#333333', key: 'annotate_black', fallback: 'Black'},
];

/**
 * The tool strip buttons, in order (FontAwesome icons, matching the toolbar).
 * `untranslatedOnly` tools are hidden in the translated (segment-anchored) view,
 * where free text selection can't map to aligned phrases (textflow): "Select
 * text" is a plain copy-selection tool that only makes sense on the original.
 */
const TOOLS = [
    {tool: 'select', icon: 'fa-i-cursor', key: 'segtool_select', fallback: 'Select text', untranslatedOnly: true},
    {tool: 'comment', icon: 'fa-comment', key: 'segtool_comment', fallback: 'Comment'},
    {tool: 'highlight', icon: 'fa-paint-brush', key: 'segmark_highlight', fallback: 'Highlight'},
    {tool: 'strikethrough', icon: 'fa-strikethrough', key: 'segmark_strike', fallback: 'Strike out'},
    {tool: 'tick', icon: 'fa-check', key: 'segmark_tick', fallback: 'Good point'},
    {tool: 'cross', icon: 'fa-times', key: 'segmark_cross', fallback: 'Needs correction'},
    {tool: 'query', icon: 'fa-question', key: 'segmark_query', fallback: 'Query'},
];

export default class extends BaseComponent {

    /**
     * Component creation hook.
     */
    create() {
        this.name = 'seg_comments';
        /** @type {?HTMLElement} The preview-content wrapper. */
        this._preview = null;
        /** @type {?HTMLElement} The translation-view region (strip anchor). */
        this._view = null;
        /** @type {?HTMLElement} The shared preview-content root spanning both views. */
        this._root = null;
        /** @type {?HTMLElement} The injected tool strip. */
        this._strip = null;
        /** @type {?HTMLElement} The translated-text warning wrapper (translated views only). */
        this._warnWrap = null;
        /** @type {?HTMLInputElement} The search input, when open. */
        this._search = null;
        /** @type {?MutationObserver} Watches the translation-view for content/visibility. */
        this._observer = null;
        /** @type {number} Debounced resize handle for redrawing leader lines. */
        this._resizeRaf = 0;
        /** @type {boolean} Whether the translated view is currently active. */
        this._active = false;
        /** @type {string} The current tool: select|comment|tick|cross|highlight|query. */
        this._tool = 'select';
        /** @type {number} The user whose data is currently loaded. */
        this._loadedUser = 0;
        /** @type {boolean} Whether data has been fetched for the current user. */
        this._loaded = false;
        /** @type {Array<object>} Parsed per-source alignment {type, fileid, segments, groups}. */
        this._sources = [];
        /** @type {?object} Translation metadata (language, mixed flag) for the doc-info panel. */
        this._meta = null;
        /** @type {Array<object>} Loaded marks (comments + stamps) for the current student/attempt. */
        this._comments = [];
        /** @type {Array<object>} Loaded comment-library entries. */
        this._library = [];
        /** @type {Array<object>} Loaded comment-library tags. */
        this._libraryTags = [];
        /** @type {boolean} Whether the library has been fetched. */
        this._libraryLoaded = false;
        /** @type {?HTMLElement} The floating composer popover, when open. */
        this._pop = null;
        /** @type {?HTMLTextAreaElement} The open composer's textarea (draft guard). */
        this._popText = null;
        /** @type {?Function} Returns the anchor's current viewport rect (live). */
        this._anchorRect = null;
        /** @type {?Function} Remover for the armed outside-click/scroll listeners. */
        this._dismiss = null;
        /** @type {number} Pending requestAnimationFrame id for arming the dismissers. */
        this._dismissRaf = 0;
        /** @type {Array<object>} Undo stack of forward ops {type:'add'|'remove', data}. */
        this._history = [];
        /** @type {Array<object>} Redo stack of forward ops. */
        this._redoStack = [];
        /** @type {number} The currently selected mark id (0 = none). */
        this._selectedId = 0;
        /** @type {?HTMLButtonElement} Undo button (enable/disable). */
        this._undoBtn = null;
        /** @type {?HTMLButtonElement} Redo button (enable/disable). */
        this._redoBtn = null;
        /** @type {?HTMLButtonElement} Delete-selected button (enable/disable). */
        this._delBtn = null;
        /** @type {?Function} Handler for the PDF text-layer (re)rendered event. */
        this._onPdfTextLayer = null;
        /** @type {number} Pending rAF id coalescing PDF re-decoration requests. */
        this._pdfRedecorateRaf = 0;
        /** @type {boolean} Last requested PDF text-selectable state (change guard). */
        this._pdfSelectRequested = false;
        /**
         * Resolved language strings by key. Elements REBUILT inside _render (the
         * gutter cards) must label themselves synchronously from this cache: an
         * async .then() label write lands after _render reconnects the observer,
         * so it re-triggers _render, which rebuilds the card, whose new button
         * writes its label again — an endless rebuild loop that replaced the
         * card buttons every frame and made their click handlers appear dead.
         * @type {Object<string, string>}
         */
        this._strs = {};
        /**
         * Ignore clicks until this timestamp (performance.now() ms). A drag ends
         * as mouseup THEN a click on the release element; once mouseup consumes
         * the drag (placing a mark / opening the composer) it clears the
         * selection, so the click's "still mid-drag" guard no longer holds and
         * the click would place a SECOND, word-sized mark at the release point
         * (a doubled badge + a double-density wash on the last word).
         * @type {number}
         */
        this._squelchUntil = 0;
        /** @type {string} How comments display: 'popup' (hover, default) or 'column' (margin). */
        this._commentView = 'popup';
        /**
         * @type {boolean} Read-only mode (the student feedback view): render the
         * grader's marks in the margin (column) view but no toolbar or editing.
         */
        this._readonly = false;
        /** @type {?HTMLButtonElement} The comment-view toggle button. */
        this._viewBtn = null;
        /** @type {?HTMLElement} The open hover popup (popup view), if any. */
        this._hoverPop = null;
        /** @type {number} Pending hover-popup hide timer. */
        this._hoverHideTimer = 0;
        /** @type {number} Last margin reserve (px) announced to the PDF viewer. */
        this._reserved = -1;
        /** @type {string} The active shape-popout entry (a shape or a stamp). */
        this._shape = 'rect';
        /** @type {string} The free-floating stamp glyph to place (CHECK|CROSS). */
        this._stamp = 'CHECK';
        /** @type {string} The active Fabric drawing colour. */
        this._shapeColor = '#EF4540';
        /** @type {?HTMLButtonElement} The shape tool button (icon reflects _shape). */
        this._shapeBtn = null;
        /** @type {number} The active Fabric pen (brush) width. */
        this._penWidth = 3;
        /** @type {?Function} Handler for a committed Fabric action (undo timeline). */
        this._onPdfAction = null;
        /** @type {?Function} Handler for the Fabric-history purge (zoom). */
        this._onPdfPurge = null;
    }

    /**
     * Register state watchers.
     * @return {Array}
     */
    getWatchers() {
        return [
            {watch: 'submission:updated', handler: this._onSubmissionUpdated},
        ];
    }

    /**
     * Called when state is first ready.
     */
    stateReady() {
        this._preview = this.element.closest('[data-region="preview-content"]');
        if (!this._isAssign() || !this._preview) {
            return;
        }
        // Two annotatable views live inside preview-content: the translated view
        // (segment anchoring, filled by translation_panel) and the inline
        // non-translated online-text view (offset anchoring, filled by
        // preview_panel). Delegate on the shared root so both are handled; each
        // source wrapper declares its anchoring via data-anchor-mode.
        this._root = this._preview;
        this._view = this._preview.querySelector('[data-region="translation-view"]');
        this._readonly = this.element.dataset.readonly === '1';

        if (this._readonly) {
            // Student feedback view: always the margin (column) view, and no
            // editing UI — skip the toolbar and the placement listeners. Hover
            // (below) still links a pin to its card.
            this._commentView = 'column';
        } else {
            // Sticky per-browser preference for how comments display (default: hover
            // popups; a stored choice wins).
            try {
                const stored = window.localStorage.getItem('local_unifiedgrader_segview');
                if (stored === 'column' || stored === 'popup') {
                    this._commentView = stored;
                }
            } catch (e) {
                // Storage unavailable (private browsing) — keep the default.
            }

            this._buildStrip();

            this._root.addEventListener('mouseup', (e) => this._onMouseUp(e));
            this._root.addEventListener('click', (e) => this._onClick(e));
            this._root.addEventListener('keydown', (e) => {
                if ((e.key === 'Enter' || e.key === ' ') && e.target.closest('[data-segcomment-id]')) {
                    e.preventDefault();
                    this._onClick(e);
                }
            });
        }

        this._root.addEventListener('mouseover', (e) => this._onHover(e, true));
        this._root.addEventListener('mouseout', (e) => this._onHover(e, false));

        // Leader lines re-project on resize (everything else scrolls together).
        window.addEventListener('resize', () => {
            if (this._resizeRaf) {
                return;
            }
            this._resizeRaf = requestAnimationFrame(() => {
                this._resizeRaf = 0;
                this._root?.querySelectorAll('.local-unifiedgrader-seg-layout')
                    .forEach((layout) => this._drawLines(layout));
            });
        });

        // Coalesce reconciles to one per animation frame, and IGNORE the PDF
        // viewer's internal churn entirely: page loads/zooms emit thousands of
        // mutations (canvas renders, Fabric layers, text-layer spans) and every
        // batch used to trigger a full re-decoration pass — the main drag on
        // navigation. PDF re-decoration doesn't need them: it rides the explicit
        // pdftextlayerrendered event below. The observer only matters for the
        // flowing views (translation/online-text renders, view switches).
        this._observer = new MutationObserver((records) => {
            const relevant = records.some((r) => {
                const el = r.target.nodeType === Node.ELEMENT_NODE ? r.target : r.target.parentElement;
                return !el || !el.closest('[data-region="pdf-viewer-wrapper"]');
            });
            if (relevant) {
                this._scheduleSync();
            }
        });
        this._observer.observe(this._root, {childList: true, subtree: true});

        // The PDF viewer rebuilds its text layers on render/zoom (wiping any mark
        // overlays); it fires this event so we re-materialise that page's marks.
        this._onPdfTextLayer = () => this._scheduleSync();
        document.addEventListener('unifiedgrader:pdftextlayerrendered', this._onPdfTextLayer);

        // Fabric (shape/pen) actions on the PDF join the strip's unified undo
        // timeline: one marker per action, tagged with its page; purged on zoom
        // when the Fabric layers' own undo stacks reset.
        this._onPdfAction = (e) => {
            this._recordOp('fabric', {page: parseInt(e.detail && e.detail.page, 10) || 0});
        };
        document.addEventListener('unifiedgrader:pdfaction', this._onPdfAction);
        this._onPdfPurge = () => this._purgeFabricHistory();
        document.addEventListener('unifiedgrader:pdfpurgehistory', this._onPdfPurge);

        this._sync();
    }

    /**
     * Remove document-level listeners on teardown.
     */
    destroy() {
        if (this._onPdfTextLayer) {
            document.removeEventListener('unifiedgrader:pdftextlayerrendered', this._onPdfTextLayer);
            this._onPdfTextLayer = null;
        }
        if (this._onPdfAction) {
            document.removeEventListener('unifiedgrader:pdfaction', this._onPdfAction);
            this._onPdfAction = null;
        }
        if (this._onPdfPurge) {
            document.removeEventListener('unifiedgrader:pdfpurgehistory', this._onPdfPurge);
            this._onPdfPurge = null;
        }
        this._hideHoverPop();
        this._observer?.disconnect();
        if (this._pdfRedecorateRaf) {
            cancelAnimationFrame(this._pdfRedecorateRaf);
            this._pdfRedecorateRaf = 0;
        }
        if (this._resizeRaf) {
            cancelAnimationFrame(this._resizeRaf);
            this._resizeRaf = 0;
        }
        if (typeof super.destroy === 'function') {
            super.destroy();
        }
    }

    /**
     * Coalesce reconcile requests to one per animation frame.
     */
    _scheduleSync() {
        if (this._pdfRedecorateRaf) {
            return;
        }
        this._pdfRedecorateRaf = requestAnimationFrame(() => {
            this._pdfRedecorateRaf = 0;
            this._sync();
        });
    }

    /**
     * Whether the current activity is an assignment.
     * @return {boolean}
     */
    _isAssign() {
        return this.reactive.state.activity?.type === 'assign';
    }

    /**
     * Reset everything when a new submission loads.
     */
    _onSubmissionUpdated() {
        this._sources = [];
        this._comments = [];
        this._loadedUser = 0;
        this._loaded = false;
        this._history = [];
        this._redoStack = [];
        this._selectedId = 0;
        this._closeComposer();
        this._hideHoverPop();
        this._setTool('select');
        this._active = false;
        this._updateHistoryButtons();
        // PDF-internal mutations no longer wake the observer, so a student
        // switch repaints via this explicit schedule (and, later, the per-page
        // text-layer events as the new document renders).
        this._scheduleSync();
    }

    /**
     * Whether the translated view is visible and carries renderable sources.
     * @return {boolean}
     */
    _isViewLive() {
        if (!this._root) {
            return false;
        }
        return [...this._root.querySelectorAll('[data-source-type]')].some((w) => w.offsetParent !== null);
    }

    /**
     * Reconcile to the current translated-view state.
     */
    async _sync() {
        if (this._isViewLive()) {
            this._active = true;
            this._strip?.classList.remove('d-none');
            // When a PDF is the live view, ask the PDF viewer to make its text
            // layer selectable so the grader can drag-select any run of text.
            this._requestPdfTextSelect(true);
            await this._ensureData();
            this._render();
        } else if (this._active) {
            this._active = false;
            this._strip?.classList.add('d-none');
            this._requestPdfTextSelect(false);
            this._closeComposer();
        }
    }

    /**
     * Whether a PDF page text layer is currently the live (visible) view.
     * @return {boolean}
     */
    _hasPdf() {
        return [...(this._root?.querySelectorAll('[data-anchor-mode="pdfpage"]') || [])]
            .some((w) => w.offsetParent !== null);
    }

    /**
     * Ask the PDF viewer to enable/disable text selection on its text layer.
     * Enabled only when the marks strip is active AND a PDF is the live view.
     * @param {boolean} enabled Whether selection should be on.
     */
    _requestPdfTextSelect(enabled) {
        const want = enabled && this._hasPdf();
        // _sync runs every animation frame while scrolling a PDF; only tell the
        // viewer when the desired state actually changes (toggling selectability
        // rewires per-span styles and a document listener each time).
        if (want === this._pdfSelectRequested) {
            return;
        }
        this._pdfSelectRequested = want;
        document.dispatchEvent(new CustomEvent('unifiedgrader:pdftextselect', {
            detail: {enabled: want},
        }));
    }

    // --- Data loading -----------------------------------------------------------

    /**
     * Ensure the alignment sources and marks are loaded for the current student.
     */
    async _ensureData() {
        const userid = parseInt(this.reactive.state.submission?.userid, 10) || 0;
        if (!userid) {
            return;
        }
        // Cache on a loaded flag, not on _sources: a non-translated submission has
        // no alignment sources, so keying off _sources.length would re-fetch on
        // every sync.
        if (this._loadedUser === userid && this._loaded) {
            return;
        }
        this._loadedUser = userid;
        this._loaded = true;
        await Promise.all([this._fetchSources(userid), this._fetchComments(userid)]);
    }

    /**
     * Re-fetch the translation payload to obtain per-source alignment groups.
     * @param {number} userid The student whose data to load.
     */
    async _fetchSources(userid) {
        const args = this._args(userid);
        if (!args) {
            return;
        }
        try {
            const result = await Ajax.call([{
                methodname: 'local_unifiedgrader_get_submission_translation',
                args, failurealert: false,
            }])[0];
            if (parseInt(this.reactive.state.submission?.userid, 10) !== userid) {
                return;
            }
            this._sources = (result.sources || []).map((s) => this._parseSource(s));
            this._meta = {
                hasmetadata: !!result.hasmetadata,
                detectedlang: result.detectedlang || '',
                resolvedlang: result.resolvedlang || '',
                mixedflag: !!result.mixedflag,
                agreement: typeof result.agreement === 'number' ? result.agreement : 1,
            };
        } catch (e) {
            this._sources = [];
        }
    }

    /**
     * Load the stored marks for the current student/attempt.
     * @param {number} userid The student whose marks to load.
     */
    async _fetchComments(userid) {
        const args = this._args(userid);
        if (!args) {
            return;
        }
        try {
            const result = await Ajax.call([{
                methodname: 'local_unifiedgrader_get_segment_comments',
                args, failurealert: false,
            }])[0];
            if (parseInt(this.reactive.state.submission?.userid, 10) !== userid) {
                return;
            }
            this._comments = result.comments || [];
        } catch (e) {
            this._comments = [];
        }
    }

    /**
     * Build the {cmid, userid, attempt} args for the current submission.
     * @param {number} userid The student user id.
     * @return {?object} The args, or null when unavailable.
     */
    _args(userid) {
        const cmid = parseInt(this.reactive.state.activity?.cmid, 10);
        if (!cmid || !userid) {
            return null;
        }
        const attempt = Number.isInteger(this.reactive.state.submission?.attemptnumber)
            ? this.reactive.state.submission.attemptnumber : -1;
        return {cmid, userid, attempt};
    }

    /**
     * Parse one source entry's alignment into {type, fileid, segments, groups}.
     * @param {object} source A source entry from get_submission_translation.
     * @return {object}
     */
    _parseSource(source) {
        const out = {
            type: source.type || 'onlinetext',
            fileid: parseInt(source.fileid, 10) || 0,
            filename: source.filename || '',
            mimetype: source.mimetype || '',
            filesize: parseInt(source.filesize, 10) || 0,
            segments: [],
            groups: [],
        };
        if (source.alignment) {
            try {
                const parsed = JSON.parse(source.alignment);
                if (parsed && Array.isArray(parsed.segments)) {
                    out.segments = parsed.segments;
                }
                if (parsed && Array.isArray(parsed.groups)) {
                    out.groups = parsed.groups;
                }
            } catch (e) {
                // Leave empty — the source cannot be marked.
            }
        }
        return out;
    }

    // --- Tool strip -------------------------------------------------------------

    /**
     * Build the tool strip once and insert it above the translated view. Built
     * synchronously (glyphs show at once); string titles fill in asynchronously.
     */
    _buildStrip() {
        const strip = document.createElement('div');
        strip.className = 'local-unifiedgrader-segtools d-none border-bottom bg-white px-2 py-1 '
            + 'd-flex align-items-center gap-1 flex-wrap';
        strip.setAttribute('role', 'toolbar');

        // Tool cluster (grouped like the standard toolbar).
        const tools = document.createElement('div');
        tools.className = 'btn-group btn-group-sm';
        tools.setAttribute('role', 'group');
        TOOLS.forEach((t) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-secondary local-unifiedgrader-segtool'
                + (t.tool === this._tool ? ' active' : '');
            btn.dataset.segtool = t.tool;
            if (t.untranslatedOnly) {
                btn.dataset.untranslatedOnly = '1';
            }
            btn.innerHTML = '<i class="fa ' + t.icon + '" aria-hidden="true"></i>';
            this._str(t.key, t.fallback).then((s) => {
                btn.title = s;
                btn.setAttribute('aria-label', s);
                return s;
            }).catch(() => {});
            btn.addEventListener('click', () => this._setTool(t.tool));
            tools.appendChild(btn);
        });
        strip.appendChild(tools);

        // Fabric drawing tools (PDF pixel annotations; hidden on flowing-text
        // views, which have no drawing canvas): select/move, shapes, pen, colour.
        strip.appendChild(this._buildMoveTool());
        strip.appendChild(this._buildShapeCluster());
        strip.appendChild(this._buildPenCluster());
        strip.appendChild(this._buildColorCluster());
        strip.appendChild(this._sep());

        // Undo / redo.
        const hist = document.createElement('div');
        hist.className = 'btn-group btn-group-sm';
        this._undoBtn = this._iconBtn('fa-undo', 'segtool_undo', 'Undo', () => this._undo());
        this._redoBtn = this._iconBtn('fa-repeat', 'segtool_redo', 'Redo', () => this._redo());
        hist.appendChild(this._undoBtn);
        hist.appendChild(this._redoBtn);
        strip.appendChild(hist);
        strip.appendChild(this._sep());

        // Delete selected + clear all.
        this._delBtn = this._iconBtn('fa-trash', 'segtool_delete', 'Delete selected', () => this._deleteSelected());
        strip.appendChild(this._delBtn);
        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'btn btn-sm btn-outline-danger';
        clearBtn.textContent = 'Clear all';
        this._str('segtool_clearall', 'Clear all').then((s) => {
            if (clearBtn.textContent !== s && !clearBtn.dataset.armed) {
                clearBtn.textContent = s;
            }
            return s;
        }).catch(() => {});
        clearBtn.addEventListener('click', this._armed(clearBtn, () => this._clearAll()));
        strip.appendChild(clearBtn);

        const spacer = document.createElement('div');
        spacer.className = 'flex-grow-1';
        strip.appendChild(spacer);

        // Comment view toggle: margin column ⇄ hover popups.
        const viewBtn = document.createElement('button');
        viewBtn.type = 'button';
        viewBtn.className = 'btn btn-sm btn-outline-secondary';
        viewBtn.addEventListener('click', () => {
            this._setCommentView(this._commentView === 'column' ? 'popup' : 'column');
        });
        this._viewBtn = viewBtn;
        strip.appendChild(viewBtn);
        this._updateViewBtn();

        // Find input (revealed by the search icon).
        const search = document.createElement('input');
        search.type = 'search';
        search.className = 'form-control form-control-sm local-unifiedgrader-segsearch d-none';
        search.style.maxWidth = '150px';
        this._str('segtool_search', 'Find…').then((s) => {
            search.placeholder = s;
            return s;
        }).catch(() => {});
        search.addEventListener('input', () => this._applySearch(search.value));
        this._search = search;
        strip.appendChild(search);

        // Search toggle (magnifier icon), matching the standard toolbar.
        const searchBtn = document.createElement('button');
        searchBtn.type = 'button';
        searchBtn.className = 'btn btn-sm btn-outline-secondary';
        searchBtn.innerHTML = '<i class="fa fa-search" aria-hidden="true"></i>';
        this._str('pdf_search', 'Search').then((s) => {
            searchBtn.title = s;
            searchBtn.setAttribute('aria-label', s);
            return s;
        }).catch(() => {});
        searchBtn.addEventListener('click', () => {
            const hidden = search.classList.toggle('d-none');
            if (hidden) {
                search.value = '';
                this._applySearch('');
            } else {
                search.focus();
            }
        });
        strip.appendChild(searchBtn);

        // Document info (word count, language, mark tally).
        strip.appendChild(this._buildDocInfo());

        // Red "!" translated-text warning, next to the search tool.
        strip.appendChild(this._buildWarnBadge());

        this._preview.insertBefore(strip, this._view);
        this._strip = strip;
        this._updateHistoryButtons();
    }

    /**
     * Build the document-info button + popout (word count, language, mark tally).
     * @return {HTMLElement}
     */
    _buildDocInfo() {
        this._wordCounts = this._wordCounts || {};
        // One-time listener for the PDF viewer's word-count reply; re-fills the
        // popout if it's open so a "Calculating…" row resolves to the real count.
        if (!this._wordCountListener) {
            this._wordCountListener = (e) => {
                const fid = parseInt(e.detail && e.detail.fileid, 10) || 0;
                if (!fid) {
                    return;
                }
                this._wordCounts[fid] = parseInt(e.detail.words, 10) || 0;
                if (this._docInfoPop && !this._docInfoPop.classList.contains('d-none')) {
                    this._fillDocInfo(this._docInfoPop);
                }
            };
            document.addEventListener('unifiedgrader:wordcount', this._wordCountListener);
        }
        const wrap = document.createElement('span');
        wrap.className = 'position-relative';
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-secondary';
        btn.innerHTML = '<i class="fa fa-info-circle" aria-hidden="true"></i>';
        this._str('docinfo', 'Document info').then((s) => {
            btn.title = s;
            btn.setAttribute('aria-label', s);
            return s;
        }).catch(() => {});
        const pop = document.createElement('div');
        pop.className = 'local-unifiedgrader-segwarn-pop card shadow d-none p-2 small';
        // Position INLINE, not via the class: the .card class (Bootstrap, loaded
        // after the plugin CSS) forces position:relative, which would drop the
        // popout into flow and stretch the flex toolbar. Inline wins that war.
        pop.style.position = 'absolute';
        pop.style.top = '100%';
        pop.style.right = '0';
        pop.style.zIndex = '1085';
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            this._closePopouts(pop);
            const hidden = pop.classList.toggle('d-none');
            if (!hidden) {
                this._fillDocInfo(pop);
            }
        });
        document.addEventListener('click', () => pop.classList.add('d-none'));
        wrap.appendChild(btn);
        wrap.appendChild(pop);
        return wrap;
    }

    /**
     * Fill the doc-info popout with the current metadata.
     * @param {HTMLElement} pop The popout element.
     */
    async _fillDocInfo(pop) {
        this._docInfoPop = pop;
        this._wordCounts = this._wordCounts || {};
        const [
            lWordcount, lComments, lMimetype, lFilesize, lCreated, lModified,
            lGroupDoc, lGroupFeedback, lGroupTranslation, lOriginalLang,
            lTranslatedWords, lLangPair, lConfidence, lConfConfirmed, lConfReview,
            lCalc, lEmpty,
        ] = await Promise.all([
            this._str('docinfo_wordcount', 'Word count'),
            this._str('docinfo_comments', 'Comments'),
            this._str('docinfo_mimetype', 'File type'),
            this._str('docinfo_filesize', 'File size'),
            this._str('docinfo_created', 'Created'),
            this._str('docinfo_modified', 'Modified'),
            this._str('docinfo_group_document', 'Document'),
            this._str('docinfo_group_feedback', 'Feedback'),
            this._str('docinfo_group_translation', 'Translation'),
            this._str('docinfo_original_language', 'Original language'),
            this._str('docinfo_translated_words', 'Translated words'),
            this._str('docinfo_language_pair', 'Direction'),
            this._str('docinfo_confidence', 'Confidence'),
            this._str('docinfo_confidence_confirmed', 'Confirmed'),
            this._str('docinfo_confidence_review', 'Needs review'),
            this._str('docinfo_calculating', 'Calculating…'),
            this._str('docinfo_empty', 'No document information available.'),
        ]);

        // Original word count from the aligned source segments (when present).
        let originalWords = 0;
        (this._sources || []).forEach((s) => {
            (s.segments || []).forEach((seg) => {
                originalWords += this._wordCountFromHtml(seg.src || '');
            });
        });
        // Translated word count from the rendered translation body.
        let translatedWords = 0;
        this._root?.querySelectorAll('.local-unifiedgrader-translation-body').forEach((b) => {
            translatedWords += this._norm(b.textContent).split(' ').filter(Boolean).length;
        });

        // Document metadata for the file currently in view, from the submission's
        // file list in state — which carries mimetype/filesize/timestamps for every
        // file whether translated or not.
        const activeFile = this._activeFile();
        let documentInfo = null;
        if (activeFile) {
            documentInfo = {
                created: activeFile.timecreated ? this._formatFileDate(activeFile.timecreated) : null,
                modified: activeFile.timemodified ? this._formatFileDate(activeFile.timemodified) : null,
                mimetype: activeFile.mimetype || null,
                filesize: activeFile.filesize ? this._formatBytes(activeFile.filesize) : null,
            };
        }

        const hasmeta = !!(this._meta && this._meta.hasmetadata);

        // Top word count: the source document's. For a file (PDF, or Word/etc.
        // converted to PDF) the whole-document count comes from the PDF viewer,
        // requested async — its reply re-fills this popout. Translated views put
        // the translated count in the Translation group; plain online text counts
        // what's shown.
        let docWords;
        if (originalWords > 0) {
            docWords = originalWords;
        } else if (hasmeta) {
            docWords = null;
        } else if (activeFile) {
            const fid = parseInt(activeFile.fileid, 10) || 0;
            if (this._wordCounts[fid] !== undefined) {
                docWords = this._wordCounts[fid] || null;
            } else {
                document.dispatchEvent(new CustomEvent('unifiedgrader:requestwordcount', {detail: {fileid: fid}}));
                docWords = lCalc;
            }
        } else {
            docWords = translatedWords || null;
        }

        // Feedback: margin comments of type 'comment' (tick/cross/query marks are
        // excluded — they aren't feedback text).
        const comments = this._comments.filter((c) => (c.marktype || 'comment') === 'comment').length;

        // Translation group (Nida only). Original language + translated words +
        // direction + whether detection agreed with the student's profile.
        let translation = null;
        if (hasmeta) {
            const src = (this._meta.resolvedlang || this._meta.detectedlang || '').toUpperCase();
            translation = {
                originallang: src || null,
                translatedwords: translatedWords || null,
                langpair: src ? (src + ' → EN') : null,
                confidence: this._meta.agreement === 0 ? lConfReview : lConfConfirmed,
            };
        }

        renderDocInfo(pop, {
            wordcount: docWords,
            pages: null,
            document: documentInfo,
            comments,
            translation,
        }, {
            wordcount: lWordcount,
            comments: lComments,
            mimetype: lMimetype,
            filesize: lFilesize,
            created: lCreated,
            modified: lModified,
            groupDocument: lGroupDoc,
            groupFeedback: lGroupFeedback,
            groupTranslation: lGroupTranslation,
            originallang: lOriginalLang,
            translatedwords: lTranslatedWords,
            langpair: lLangPair,
            confidence: lConfidence,
            empty: lEmpty,
        });
    }

    /**
     * Word count of an HTML fragment (tags stripped).
     * @param {string} html
     * @return {number}
     */
    _wordCountFromHtml(html) {
        if (!html) {
            return 0;
        }
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        return this._norm(tmp.textContent || '').split(' ').filter(Boolean).length;
    }

    /**
     * Human-readable byte size.
     * @param {number} bytes
     * @return {string}
     */
    _formatBytes(bytes) {
        if (!bytes) {
            return '0 B';
        }
        const units = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        const size = (bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1);
        return size + ' ' + units[i];
    }

    /**
     * The submission file currently in view (from state), matched by the fileid of
     * the visible file / PDF-page wrapper. Null for online text or no file.
     *
     * @return {?object}
     */
    _activeFile() {
        const files = this.reactive.state.submission?.files || [];
        if (!files.length || !this._root) {
            return null;
        }
        const visible = [...this._root.querySelectorAll('[data-fileid]')]
            .find((w) => w.offsetParent !== null && (parseInt(w.dataset.fileid, 10) || 0) > 0);
        const fileid = visible ? (parseInt(visible.dataset.fileid, 10) || 0) : 0;
        if (!fileid) {
            return null;
        }
        return files.find((f) => (parseInt(f.fileid, 10) || 0) === fileid) || null;
    }

    /**
     * Format a Unix timestamp (seconds) as a short readable date, or null.
     *
     * @param {number} ts
     * @return {?string}
     */
    _formatFileDate(ts) {
        if (!ts) {
            return null;
        }
        try {
            return new Date(ts * 1000).toLocaleDateString(undefined, {
                year: 'numeric', month: 'short', day: 'numeric',
            });
        } catch (e) {
            return null;
        }
    }

    /**
     * Close every strip popout except an optional one being opened.
     * @param {?HTMLElement} keep The popout to leave alone.
     */
    _closePopouts(keep) {
        this._strip?.querySelectorAll('.local-unifiedgrader-segwarn-pop').forEach((p) => {
            if (p !== keep) {
                p.classList.add('d-none');
            }
        });
    }

    /**
     * Build the red "!" warning badge + its dismissible popover (sync; text async).
     * @return {HTMLElement}
     */
    _buildWarnBadge() {
        const wrap = document.createElement('span');
        wrap.className = 'position-relative';

        const badge = document.createElement('button');
        badge.type = 'button';
        badge.className = 'btn btn-sm btn-link text-danger p-0 px-1 fw-bold local-unifiedgrader-segwarn';
        badge.textContent = '!';
        this._str('segwarn_title', 'About marking translated text').then((s) => {
            badge.title = s;
            badge.setAttribute('aria-label', s);
            return s;
        }).catch(() => {});

        const pop = document.createElement('div');
        pop.className = 'local-unifiedgrader-segwarn-pop card shadow d-none p-2 small';
        // Position INLINE, not via the class: the .card class (Bootstrap, loaded
        // after the plugin CSS) forces position:relative, which would drop the
        // popout into flow and stretch the flex toolbar. Inline wins that war.
        pop.style.position = 'absolute';
        pop.style.top = '100%';
        pop.style.right = '0';
        pop.style.zIndex = '1085';
        this._str('segwarn_body', 'You are marking a machine translation. Comments and marks anchor to '
            + 'the student’s original text, so they stay put and the student sees them. Freehand pen and '
            + 'shape tools are not offered here because they cannot anchor to reflowing text — mark those '
            + 'on the original file instead.').then((s) => {
            pop.textContent = s;
            return s;
        }).catch(() => {});
        badge.addEventListener('click', (e) => {
            e.stopPropagation();
            this._closePopouts(pop);
            pop.classList.toggle('d-none');
        });
        document.addEventListener('click', () => pop.classList.add('d-none'));

        wrap.appendChild(badge);
        wrap.appendChild(pop);
        // The shift warning only applies to translated text; hidden by _render
        // when the live view is non-translated.
        this._warnWrap = wrap;
        return wrap;
    }

    /**
     * Select a tool and reflect it in the strip.
     * @param {string} tool The tool key.
     */
    _setTool(tool) {
        const wasCanvas = this._isCanvasTool();
        this._tool = tool;
        this._strip?.querySelectorAll('[data-segtool]').forEach((b) => {
            b.classList.toggle('active', b.dataset.segtool === tool);
        });
        // In select/comment mode text is selectable; in stamp mode it is not, so a
        // click lands cleanly on a phrase.
        this._root?.classList.toggle('local-unifiedgrader-seg-marking',
            tool !== 'select' && tool !== 'comment');
        // A canvas tool (shape/pen) hands the pointer to the Fabric canvas: our
        // overlay marks must not intercept draws, and the PDF viewer swaps its own
        // tool + disables the text layer (via its onToolChange plumbing).
        const isCanvas = this._isCanvasTool();
        this._root?.classList.toggle('local-unifiedgrader-canvastool', isCanvas);
        if (tool === 'shape') {
            this._dispatchPdfTool({tool: 'shape', shape: this._shape, color: this._shapeColor});
        } else if (tool === 'stamp') {
            this._dispatchPdfTool({tool: 'stamp', stamp: this._stamp, color: this._shapeColor});
        } else if (tool === 'pen') {
            this._dispatchPdfTool({tool: 'pen', width: this._penWidth, color: this._shapeColor});
        } else if (tool === 'annotmove') {
            // Fabric select mode: existing shapes/strokes become selectable and
            // draggable for fine positioning (the viewer disables the text layer).
            this._dispatchPdfTool({tool: 'select'});
        } else if (wasCanvas) {
            this._dispatchPdfTool({tool: 'select'});
            // The viewer turned text selection off for the canvas; our change
            // guard thinks it is still on — force a fresh request.
            this._pdfSelectRequested = false;
            this._requestPdfTextSelect(true);
        }
        this._updateHistoryButtons();
    }

    /**
     * Whether the active tool draws on the Fabric canvas (shape or pen).
     * @return {boolean}
     */
    _isCanvasTool() {
        // 'stamp' belongs here too: a free-floating tick/cross is placed on the
        // Fabric canvas, so the pointer must reach it rather than the text layer.
        return this._tool === 'shape' || this._tool === 'pen'
            || this._tool === 'stamp' || this._tool === 'annotmove';
    }

    /**
     * Highlight phrases matching the search term (whole-phrase, case-insensitive).
     * @param {string} term The search term.
     */
    _applySearch(term) {
        const q = this._norm(term).toLowerCase();
        this._root?.querySelectorAll('.' + SEG_CLASS).forEach((span) => {
            const hit = q.length >= 2 && this._norm(span.textContent).toLowerCase().includes(q);
            span.classList.toggle('local-unifiedgrader-seg-found', hit);
        });
    }

    // --- Rendering the marks ----------------------------------------------------

    /**
     * Decorate every commentable translated page: segment the body, then draw the
     * comment gutter + pins + leader lines and the inline stamps. Bracketed by an
     * observer disconnect so DOM writes never re-trigger _sync().
     */
    _render() {
        if (!this._root || !this._observer) {
            return;
        }
        this._observer.disconnect();
        try {
            let seq = 1;
            let translated = false;
            this._root.querySelectorAll('[data-source-type]').forEach((wrapper) => {
                // Skip wrappers in a hidden view (only one view is shown at a time).
                if (!wrapper.offsetParent) {
                    return;
                }
                // PDF pages anchor to their text layer directly (no aligned
                // segments). Marks render as overlay rects; comments also get a
                // numbered pin + a card in a per-page margin gutter, sharing the
                // comment sequence with the flowing views.
                if ((wrapper.dataset.anchorMode || 'segment') === 'pdfpage') {
                    seq = this._renderPdfPage(wrapper, seq);
                    return;
                }
                const page = wrapper.querySelector('.local-unifiedgrader-translation-page') || wrapper;
                const body = wrapper.querySelector('.local-unifiedgrader-translation-body');
                // Parallel (side-by-side) pages keep their own behaviour.
                if (!body || wrapper.querySelector('[data-align-col]')) {
                    return;
                }
                const mode = wrapper.dataset.anchorMode || 'segment';
                const sourcetype = wrapper.dataset.sourceType || 'onlinetext';
                const fileid = parseInt(wrapper.dataset.fileid, 10) || 0;
                const mine = this._comments.filter((c) => (c.sourcetype || 'onlinetext') === sourcetype
                    && (sourcetype !== 'file' || (parseInt(c.fileid, 10) || 0) === fileid));

                // Segment (translated) mode pre-wraps every aligned phrase; offset
                // (non-translated) mode has no alignment and wraps only the marks.
                let source = null;
                if (mode === 'segment') {
                    source = this._sourceForWrapper(wrapper);
                    if (!source) {
                        return;
                    }
                    translated = true;
                    this._segmentBody(body, source);
                } else {
                    body.querySelectorAll('.' + SEG_CLASS).forEach((el) => this._unwrap(el));
                }

                const layout = this._ensureLayout(page, body);
                const gutter = layout.querySelector('.local-unifiedgrader-seg-gutter');
                gutter.innerHTML = '';
                seq = this._decorate(body, gutter, source, mine, seq, mode);
                this._drawLines(layout);
            });
            if (this._search?.value) {
                this._applySearch(this._search.value);
            }
            // The translated-text shift warning is only relevant on translated views.
            this._warnWrap?.classList.toggle('d-none', !translated);
            this._applyToolVisibility(translated);
            this._applySelection();
        } finally {
            this._observer.observe(this._root, {childList: true, subtree: true});
        }
        this._updateHistoryButtons();
        this._syncReserve();
    }

    /**
     * Show/hide tools that only apply to the untranslated original. In the
     * translated (segment-anchored) view, free-length text selection can't map to
     * aligned phrases, so "Select text" is hidden; if it was the active tool, fall
     * back to Comment so the strip is never left on a hidden tool.
     * @param {boolean} translated Whether the live view is the translated one.
     */
    _applyToolVisibility(translated) {
        if (!this._strip) {
            return;
        }
        this._strip.querySelectorAll('[data-segtool][data-untranslated-only="1"]').forEach((btn) => {
            btn.classList.toggle('d-none', translated);
        });
        if (translated && this._tool === 'select') {
            this._setTool('comment');
        }
        // Fabric shape/colour controls only apply where a drawing canvas exists.
        const pdf = this._hasPdf();
        this._strip.querySelectorAll('[data-pdf-only="1"]').forEach((el) => {
            el.classList.toggle('d-none', !pdf);
        });
        if (!pdf && this._isCanvasTool()) {
            this._setTool('comment');
        }

        // The comment-view (margin ⇄ hover) toggle only does something when there
        // are phrase comments to rearrange. Annotation-tool pins live in the PDF
        // annotation layer, not here, so on a PDF marked up with pins only (or any
        // view with no comments) the toggle would be a dead control. Show it only
        // when it actually functions.
        if (this._viewBtn) {
            const hascomments = this._comments.some((c) => (c.marktype || 'comment') === 'comment');
            this._viewBtn.classList.toggle('d-none', !hascomments);
        }
    }

    /**
     * Ensure a page has the two-column layout (text + gutter) and an SVG line
     * overlay, wrapping the existing body. Idempotent.
     * @param {HTMLElement} page The translation page element.
     * @param {HTMLElement} body The translated body element.
     * @return {HTMLElement} The layout element.
     */
    _ensureLayout(page, body) {
        let layout = page.querySelector('.local-unifiedgrader-seg-layout');
        if (layout) {
            return layout;
        }
        layout = document.createElement('div');
        layout.className = 'local-unifiedgrader-seg-layout';
        page.insertBefore(layout, body);
        layout.appendChild(body);

        const gutter = document.createElement('div');
        gutter.className = 'local-unifiedgrader-seg-gutter';
        layout.appendChild(gutter);

        const svg = document.createElementNS(SVGNS, 'svg');
        svg.setAttribute('class', 'local-unifiedgrader-seg-lines');
        layout.appendChild(svg);
        return layout;
    }

    /**
     * Wrap each aligned translation segment of the body in its own span (in place,
     * preserving layout) so it is a stable click/hit target. Ranges are located
     * against an immutable text snapshot and wrapped last-to-first so earlier
     * offsets stay valid.
     * @param {HTMLElement} body The translated body.
     * @param {object} source The parsed source (segments).
     */
    _segmentBody(body, source) {
        // Clear a previous pass.
        body.querySelectorAll('.' + SEG_CLASS).forEach((el) => this._unwrap(el));

        const nodes = [];
        const walker = document.createTreeWalker(body, NodeFilter.SHOW_TEXT);
        let raw = '';
        for (let n = walker.nextNode(); n; n = walker.nextNode()) {
            nodes.push({node: n, start: raw.length});
            raw += n.nodeValue;
        }

        let cursor = 0;
        const ranges = [];
        source.segments.forEach((seg) => {
            const target = this._norm(this._stripTags(seg.tx || ''));
            if (target.length < 2) {
                return;
            }
            const tokens = target.split(' ').filter(Boolean).map((t) => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
            if (!tokens.length) {
                return;
            }
            const re = new RegExp(tokens.join('\\s+'), 'i');
            const m = re.exec(raw.slice(cursor));
            if (!m) {
                return;
            }
            const from = cursor + m.index;
            const to = from + m[0].length;
            ranges.push({id: parseInt(seg.id, 10), from, to});
            cursor = to;
        });

        ranges.sort((a, b) => b.from - a.from);
        ranges.forEach((r) => {
            const start = this._locate(nodes, r.from);
            const end = this._locate(nodes, r.to);
            if (start && end && !(start.node.parentElement && start.node.parentElement.closest('.' + SEG_CLASS))) {
                this._wrapRange(start, end, body, SEG_CLASS, {nidaSeg: String(r.id)});
            }
        });
    }

    /**
     * Draw the comment pins + gutter cards + leader lines and the inline stamps for
     * one page's marks. Returns the next comment sequence number.
     * @param {HTMLElement} body The translated body.
     * @param {HTMLElement} gutter The gutter element.
     * @param {object} source The parsed source.
     * @param {Array<object>} marks The marks for this source.
     * @param {number} seq The next comment number.
     * @return {number} The updated sequence number.
     */
    _decorate(body, gutter, source, marks, seq, mode) {
        // Order marks by the document position of their first span. Segment marks
        // reuse the pre-wrapped phrase spans; offset marks wrap their char range.
        const decorated = marks.map((mark) => {
            let spans = [];
            if (mode === 'offset') {
                spans = this._wrapOffset(body, mark);
            } else {
                const txids = this._txIdsForComment(mark, source);
                txids.forEach((id) => {
                    body.querySelectorAll('.' + SEG_CLASS + '[data-nida-seg="' + id + '"]')
                        .forEach((el) => spans.push(el));
                });
            }
            return {mark, spans};
        }).filter((d) => d.spans.length);
        decorated.sort((a, b) =>
            (a.spans[0].compareDocumentPosition(b.spans[0]) & Node.DOCUMENT_POSITION_FOLLOWING) ? -1 : 1);

        decorated.forEach((d) => {
            const marktype = d.mark.marktype || 'comment';
            if (marktype === 'comment') {
                this._decorateComment(d.mark, d.spans, gutter, seq++);
            } else {
                this._decorateStamp(d.mark, d.spans, marktype);
            }
        });
        return seq;
    }

    /**
     * A text comment: highlight the phrase, place a numbered pin, add a gutter card.
     * @param {object} mark The comment record.
     * @param {Array<HTMLElement>} spans The phrase spans.
     * @param {HTMLElement} gutter The gutter element.
     * @param {number} num The comment number.
     */
    _decorateComment(mark, spans, gutter, num) {
        spans.forEach((s) => {
            s.classList.add(MARK_CLASS);
            s.dataset.segcommentId = mark.id;
        });
        const pin = document.createElement('sup');
        pin.className = 'local-unifiedgrader-seg-pin';
        pin.dataset.segcommentId = mark.id;
        pin.textContent = num;
        pin.setAttribute('role', 'button');
        pin.setAttribute('tabindex', '0');
        pin.style.backgroundColor = this._markColor(mark);
        pin.style.color = this._textOn(this._markColor(mark));
        spans[0].insertBefore(pin, spans[0].firstChild);

        // Column view fills the margin gutter; popup view leaves it empty (it
        // collapses) and the card shows as a hover popover instead.
        if (this._commentView === 'column') {
            gutter.appendChild(this._card(mark, num));
        }
    }

    /**
     * A stamp (tick/cross/highlight/query): render an inline badge on the phrase.
     * @param {object} mark The mark record.
     * @param {Array<HTMLElement>} spans The phrase spans.
     * @param {string} marktype The stamp type.
     */
    _decorateStamp(mark, spans, marktype) {
        const meta = STAMPS[marktype];
        if (!meta) {
            return;
        }
        spans.forEach((s) => {
            s.classList.add(meta.cls);
            s.dataset.segcommentId = mark.id;
        });
        if (meta.glyph) {
            const badge = document.createElement('sup');
            badge.className = 'local-unifiedgrader-seg-stampbadge ' + meta.cls;
            badge.dataset.segcommentId = mark.id;
            badge.textContent = meta.glyph;
            badge.setAttribute('role', 'button');
            badge.setAttribute('tabindex', '0');
            badge.title = meta.fallback;
            spans[0].insertBefore(badge, spans[0].firstChild);
        }
    }

    /**
     * Decorate one PDF page with its marks, painted as OVERLAY RECTANGLES (the
     * technique PDF.js itself uses for search highlights) — NOT by wrapping the
     * text-layer spans in place. PDF.js splits lines into arbitrarily-broken,
     * scaleX-transformed spans, so in-place wrappers mis-measure (partial-word
     * strikes, washes out of register). Rects come from the resolved range's own
     * getClientRects(), so they cover the full selection across spans and lines,
     * pixel-exact at any zoom. The canvas glyphs can't be recoloured (painted
     * pixels), so a tinted wash in the mark's colour is the PDF equivalent of
     * the translated view's coloured text. Rects carry data-segcomment-id, so
     * the delegated click/hover handlers open marks exactly like wrapped spans.
     * @param {HTMLElement} wrapper The page's canvas wrapper (data-source-type=file).
     */
    _renderPdfPage(wrapper, seq) {
        const body = wrapper.querySelector('[data-region="pdf-text-layer"]');
        if (!body) {
            return seq;
        }
        let overlay = wrapper.querySelector('.local-unifiedgrader-pdfmarks');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'local-unifiedgrader-pdfmarks';
            wrapper.appendChild(overlay);
        }
        overlay.textContent = '';
        // Give the page slot the translated view's two-column treatment: page +
        // margin gutter, with an SVG overlay for the pin→card leader lines. The
        // slot is already a centred flex row, and an empty gutter collapses
        // (display:none via :empty), so comment-free pages are untouched.
        const {slot, gutter} = this._ensurePdfLayout(wrapper);
        if (gutter) {
            gutter.innerHTML = '';
        }
        const fileid = parseInt(wrapper.dataset.fileid, 10) || 0;
        const page = parseInt(wrapper.dataset.page, 10) || 0;
        if (!fileid || !page) {
            return seq;
        }
        const mine = this._comments.filter((c) => c.sourcetype === 'file'
            && (parseInt(c.fileid, 10) || 0) === fileid
            && (parseInt(c.page, 10) || 0) === page);
        if (mine.length) {
            // One text walk serves every mark on the page: overlay painting
            // never mutates the text layer, so the walk stays valid throughout.
            const walk = this._walkText(body);
            mine.forEach((mark) => {
                const painted = this._paintPdfMark(overlay, body, mark, gutter, seq, walk);
                if (painted && (mark.marktype || 'comment') === 'comment') {
                    seq++;
                }
            });
        }
        if (slot) {
            this._drawLines(slot);
        }
        return seq;
    }

    /**
     * Ensure a PDF page slot carries the two-column marks layout: the seg-layout
     * class (flex + relative, shared with the translated view so the resize
     * redraw and narrow-pane rules apply), a margin gutter for comment cards and
     * an SVG leader-line overlay. Idempotent; rebuilt slots (zoom) re-acquire it
     * on the next render.
     * @param {HTMLElement} wrapper The page's canvas wrapper.
     * @return {{slot: ?HTMLElement, gutter: ?HTMLElement}}
     */
    _ensurePdfLayout(wrapper) {
        const slot = wrapper.closest('.pdf-page-slot') || wrapper.parentElement;
        if (!slot) {
            return {slot: null, gutter: null};
        }
        slot.classList.add('local-unifiedgrader-seg-layout');
        let gutter = slot.querySelector(':scope > .local-unifiedgrader-seg-gutter');
        if (!gutter) {
            gutter = document.createElement('div');
            gutter.className = 'local-unifiedgrader-seg-gutter';
            slot.appendChild(gutter);
        }
        if (!slot.querySelector(':scope > .local-unifiedgrader-seg-lines')) {
            const svg = document.createElementNS(SVGNS, 'svg');
            svg.setAttribute('class', 'local-unifiedgrader-seg-lines');
            slot.appendChild(svg);
        }
        return {slot, gutter};
    }

    /**
     * Paint one mark's overlay rects onto a page overlay: a stamp also gets its
     * glyph badge; a comment gets a numbered pin and a card in the page gutter.
     * @param {HTMLElement} overlay The page's mark overlay layer.
     * @param {HTMLElement} body The page text-layer element.
     * @param {object} mark The mark record.
     * @param {?HTMLElement} gutter The page's margin gutter (comment cards).
     * @param {number} num The comment sequence number (used for comments only).
     * @param {?{nodes: Array, raw: string}} walk Prebuilt text walk of the body.
     * @return {boolean} Whether the mark was painted.
     */
    _paintPdfMark(overlay, body, mark, gutter, num, walk) {
        // Read the overlay origin per mark, not once for the page. Appending the
        // first comment card flips the gutter from :empty (collapsed) to a 250px
        // column, reflowing the flex row and shifting the page; a single cached
        // origin would then place every later mark — and the caret — to the left
        // of its glyph.
        const origin = overlay.getBoundingClientRect();
        const loc = this._resolveOffsets(body, mark, walk);
        if (!loc) {
            return false;
        }
        const range = document.createRange();
        try {
            range.setStart(loc.start.node, loc.start.offset);
            range.setEnd(loc.end.node, loc.end.offset);
        } catch (e) {
            return false;
        }

        const marktype = mark.marktype || 'comment';
        const color = this._markColor(mark);
        // A point comment (anchored to a single character) renders as a thin caret
        // at the click position rather than a word wash. Distinguished by span ≤ 1;
        // longer selections keep the highlight logic below.
        const span = (parseInt(mark.endoffset, 10) || 0) - (parseInt(mark.startoffset, 10) || 0);
        if (marktype === 'comment' && span <= 1) {
            let rect = range.getBoundingClientRect();
            if (!rect || rect.height < 1) {
                const host = range.startContainer.nodeType === Node.TEXT_NODE
                    ? range.startContainer.parentElement
                    : range.startContainer;
                rect = host ? host.getBoundingClientRect() : null;
            }
            if (!rect) {
                return false;
            }
            const caretEl = document.createElement('div');
            caretEl.className = 'local-unifiedgrader-pdfmark local-unifiedgrader-pdfmark-comment'
                + ' local-unifiedgrader-pdfmark-point';
            caretEl.dataset.segcommentId = mark.id;
            caretEl.setAttribute('role', 'button');
            caretEl.setAttribute('tabindex', '0');
            caretEl.style.left = (rect.left - origin.left) + 'px';
            caretEl.style.top = (rect.top - origin.top) + 'px';
            caretEl.style.height = rect.height + 'px';
            // Set the caret's width/fill inline too: if the .pdfmark-point rule
            // hasn't loaded yet (stale CSS cache), an empty absolute div would be
            // width:auto (0) and never show. A 3px bar + soft halo keeps the point
            // legible against the page text.
            caretEl.style.width = '3px';
            caretEl.style.backgroundColor = color;
            caretEl.style.borderBottom = 'none';
            caretEl.style.boxShadow = '0 0 0 1px rgba(255, 255, 255, 0.7)';
            overlay.appendChild(caretEl);
            const pin = document.createElement('sup');
            pin.className = 'local-unifiedgrader-seg-pin local-unifiedgrader-pdfpin';
            pin.dataset.segcommentId = mark.id;
            pin.textContent = num;
            pin.setAttribute('role', 'button');
            pin.setAttribute('tabindex', '0');
            pin.style.left = (rect.left - origin.left) + 'px';
            pin.style.top = (rect.top - origin.top) + 'px';
            pin.style.backgroundColor = color;
            pin.style.color = this._textOn(color);
            overlay.appendChild(pin);
            if (gutter && this._commentView === 'column') {
                gutter.appendChild(this._card(mark, num));
            }
            return true;
        }

        // getClientRects() duplicates fully-covered text-layer spans (one rect
        // for the element, one for its text node — Chrome in particular), which
        // stacks the translucent wash and darkens part of the range. Keep a rect
        // only if it doesn't mostly overlap one we already kept.
        // Width > 2 also drops the caret-thin slivers getClientRects() emits at
        // range boundaries, which would otherwise render as tiny end fragments.
        const rects = [];
        [...range.getClientRects()]
            .filter((r) => r.width > 2 && r.height > 3)
            .forEach((r) => {
                const dup = rects.some((k) => {
                    const ix = Math.min(r.right, k.right) - Math.max(r.left, k.left);
                    const iy = Math.min(r.bottom, k.bottom) - Math.max(r.top, k.top);
                    if (ix <= 0 || iy <= 0) {
                        return false;
                    }
                    const inter = ix * iy;
                    return inter > 0.6 * r.width * r.height || inter > 0.6 * k.width * k.height;
                });
                if (!dup) {
                    rects.push(r);
                }
            });
        if (!rects.length) {
            return false;
        }
        rects.forEach((r) => {
            const d = document.createElement('div');
            d.className = 'local-unifiedgrader-pdfmark local-unifiedgrader-pdfmark-' + marktype;
            d.dataset.segcommentId = mark.id;
            d.setAttribute('role', 'button');
            d.setAttribute('tabindex', '0');
            d.style.left = (r.left - origin.left) + 'px';
            d.style.top = (r.top - origin.top) + 'px';
            d.style.width = r.width + 'px';
            d.style.height = r.height + 'px';
            if (marktype === 'comment') {
                d.style.backgroundColor = this._tint(color, 0.2);
                d.style.borderBottomColor = color;
            }
            overlay.appendChild(d);
        });
        if (marktype === 'comment') {
            // Numbered pin at the start of the range + a card in the margin
            // gutter, joined by the dotted leader line (_drawLines) — the same
            // marginalia as the translated view.
            const pin = document.createElement('sup');
            pin.className = 'local-unifiedgrader-seg-pin local-unifiedgrader-pdfpin';
            pin.dataset.segcommentId = mark.id;
            pin.textContent = num;
            pin.setAttribute('role', 'button');
            pin.setAttribute('tabindex', '0');
            pin.style.left = (rects[0].left - origin.left) + 'px';
            pin.style.top = (rects[0].top - origin.top) + 'px';
            pin.style.backgroundColor = color;
            pin.style.color = this._textOn(color);
            overlay.appendChild(pin);
            // Column view fills the margin gutter; popup view leaves it empty
            // (it collapses) and the card shows as a hover popover instead.
            if (gutter && this._commentView === 'column') {
                gutter.appendChild(this._card(mark, num));
            }
            return true;
        }
        const meta = STAMPS[marktype];
        if (meta && meta.glyph) {
            const badge = document.createElement('sup');
            badge.className = 'local-unifiedgrader-pdfbadge ' + meta.cls;
            badge.dataset.segcommentId = mark.id;
            badge.textContent = meta.glyph;
            badge.title = meta.fallback;
            badge.setAttribute('role', 'button');
            badge.setAttribute('tabindex', '0');
            badge.style.left = (rects[0].left - origin.left) + 'px';
            badge.style.top = (rects[0].top - origin.top) + 'px';
            overlay.appendChild(badge);
        }
        return true;
    }

    /**
     * The decoratable/selectable text body inside a source wrapper: the PDF text
     * layer for a PDF page, otherwise the flowing translation/online-text body.
     * @param {HTMLElement} wrapper The source wrapper.
     * @return {?HTMLElement}
     */
    _bodyForWrapper(wrapper) {
        if ((wrapper.dataset.anchorMode || 'segment') === 'pdfpage') {
            return wrapper.querySelector('[data-region="pdf-text-layer"]');
        }
        return wrapper.querySelector('.local-unifiedgrader-translation-body');
    }

    /**
     * Build an offset-anchored target carrying the wrapper's source identity
     * (file + page for PDF, online text otherwise).
     * @param {HTMLElement} wrapper The source wrapper.
     * @param {{startoffset: number, endoffset: number, anchortext: string}} off The offsets.
     * @return {object}
     */
    _offsetTarget(wrapper, off) {
        const isPdf = (wrapper.dataset.anchorMode || 'segment') === 'pdfpage';
        return {
            mode: 'offset',
            sourcetype: isPdf ? 'file' : 'onlinetext',
            fileid: isPdf ? (parseInt(wrapper.dataset.fileid, 10) || 0) : 0,
            page: isPdf ? (parseInt(wrapper.dataset.page, 10) || 0) : 0,
            startoffset: off.startoffset,
            endoffset: off.endoffset,
            anchortext: off.anchortext,
        };
    }

    /**
     * Build a gutter card for a comment.
     * @param {object} mark The comment record.
     * @param {number} num The comment number.
     * @return {HTMLElement}
     */
    _card(mark, num) {
        const card = document.createElement('div');
        card.className = 'local-unifiedgrader-seg-card border rounded p-2 mb-2 bg-light';
        card.dataset.segcommentId = mark.id;

        const head = document.createElement('div');
        head.className = 'd-flex align-items-center gap-1 mb-1';
        const chip = document.createElement('span');
        // No bg-primary utility here: its `background-color … !important` would
        // beat the inline colour and leave a blue badge with (for light swatches)
        // an unreadable dark number. Inline bg + auto-ink match the pin exactly.
        chip.className = 'badge local-unifiedgrader-seg-cardnum';
        chip.textContent = num;
        chip.style.backgroundColor = this._markColor(mark);
        chip.style.color = this._textOn(this._markColor(mark));
        head.appendChild(chip);
        if (mark.authorfullname) {
            const author = document.createElement('span');
            author.className = 'small text-muted text-truncate';
            author.textContent = mark.authorfullname;
            head.appendChild(author);
        }
        card.appendChild(head);

        const body = document.createElement('div');
        body.className = 'small local-unifiedgrader-segcomment-body';
        body.innerHTML = mark.commenttext || '';
        card.appendChild(body);

        const myId = this._myId();
        if (!myId || parseInt(mark.authorid, 10) === myId) {
            const foot = document.createElement('div');
            foot.className = 'd-flex gap-2 mt-1';
            const edit = this._linkBtn('segcomment_edit', 'Edit', () => this._editComment(mark));
            const del = this._linkBtn('segcomment_delete', 'Delete', () => this._delete(mark));
            del.classList.add('text-danger');
            foot.appendChild(edit);
            foot.appendChild(del);
            card.appendChild(foot);
        }

        // Hover a card ↔ its pin/phrase. A click on the card body (not a button) is
        // handled by the delegated view click via data-segcomment-id — no own click
        // handler here, so the composer never double-opens.
        card.addEventListener('mouseover', () => this._setLinked(mark.id, true));
        card.addEventListener('mouseout', () => this._setLinked(mark.id, false));
        return card;
    }

    /**
     * A small link-style button.
     * @param {string} key String key.
     * @param {string} fallback Fallback label.
     * @param {Function} onClick Click handler.
     * @return {HTMLButtonElement}
     */
    _linkBtn(key, fallback, onClick) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn btn-link btn-sm p-0';
        // Label synchronously from the string cache and only write the async
        // resolution if it differs: these buttons are rebuilt inside _render, and
        // an unconditional post-render write re-triggers the mutation observer —
        // an endless rebuild loop that killed the buttons' click handlers.
        b.textContent = this._strs[key] ?? fallback;
        this._str(key, fallback).then((s) => {
            if (b.textContent !== s) {
                b.textContent = s;
            }
            return s;
        }).catch(() => {});
        b.addEventListener('click', onClick);
        return b;
    }

    /**
     * Draw dotted leader lines from each comment pin to its gutter card, in the
     * layout's own coordinate space (so they hold while the pane scrolls).
     * @param {HTMLElement} layout The seg layout element.
     */
    _drawLines(layout) {
        const svg = layout.querySelector('.local-unifiedgrader-seg-lines');
        if (!svg) {
            return;
        }
        while (svg.firstChild) {
            svg.removeChild(svg.firstChild);
        }
        const lr = layout.getBoundingClientRect();
        if (!lr.width) {
            return;
        }
        svg.setAttribute('width', Math.round(lr.width));
        svg.setAttribute('height', Math.round(lr.height));

        layout.querySelectorAll('.local-unifiedgrader-seg-card').forEach((card) => {
            const id = card.dataset.segcommentId;
            const pin = layout.querySelector('.local-unifiedgrader-seg-pin[data-segcomment-id="' + id + '"]');
            if (!pin) {
                return;
            }
            const pr = pin.getBoundingClientRect();
            const cr = card.getBoundingClientRect();
            const x1 = pr.right - lr.left;
            const y1 = pr.top + pr.height / 2 - lr.top;
            const x2 = cr.left - lr.left;
            const y2 = cr.top + Math.min(14, cr.height / 2) - lr.top;
            const line = document.createElementNS(SVGNS, 'path');
            line.setAttribute('d', 'M' + x1 + ',' + y1 + ' L' + x2 + ',' + y2);
            line.setAttribute('class', 'local-unifiedgrader-seg-line');
            line.dataset.segcommentId = id;
            svg.appendChild(line);
        });
    }

    /**
     * Toggle the linked/active state across a mark's spans, pin, card and line.
     * @param {number|string} id The comment id.
     * @param {boolean} on Whether to activate.
     */
    _setLinked(id, on) {
        this._root?.querySelectorAll('[data-segcomment-id="' + id + '"]').forEach((el) => {
            el.classList.toggle('local-unifiedgrader-seg-linked', on);
        });
    }

    // --- Interaction ------------------------------------------------------------

    /**
     * Hover over a phrase/pin ↔ its card.
     * @param {Event} e The hover event.
     * @param {boolean} on Whether entering.
     */
    _onHover(e, on) {
        const el = e.target.closest('[data-segcomment-id]');
        if (el && this._root.contains(el)) {
            this._setLinked(el.dataset.segcommentId, on);
            // Popup view: hovering a comment mark shows its card as a popover.
            if (this._commentView === 'popup') {
                if (on) {
                    this._showHoverPop(parseInt(el.dataset.segcommentId, 10), el);
                } else {
                    this._scheduleHoverHide();
                }
            }
        }
    }

    /**
     * Whether an event happened in a dual-file pane that isn't the focused one.
     *
     * In the dual-file view both panes can hold a document, and both render real,
     * clickable text layers. Marks are only placed in the pane the teacher focused
     * (the one badged "Marking here"), so a stray click or selection in the other
     * pane — most likely while scrolling it for reference — cannot drop a mark into
     * the wrong document. Reading existing marks is unaffected.
     *
     * @param {Event} e The pointer event.
     * @return {boolean} True when the event should be ignored.
     */
    _inUnfocusedPane(e) {
        const pane = e.target?.closest?.('[data-region="split-pane"]');
        return !!pane && !pane.classList.contains('is-active');
    }

    /**
     * A click in the translated body: place a stamp, open an existing mark, or
     * (in select/comment mode) start a comment on the clicked phrase.
     * @param {Event} e The click event.
     */
    _onClick(e) {
        // Buttons (card Edit/Delete) handle their own clicks — don't also treat the
        // click as "open this mark", which would double-open the composer.
        if (e.target.closest('button')) {
            return;
        }
        if (this._inUnfocusedPane(e)) {
            return;
        }
        // The click that ends a mouseup-consumed drag: already handled there.
        if (performance.now() < this._squelchUntil) {
            return;
        }
        const sel = window.getSelection();
        // A drag-select also emits a click — let mouseup own it (marks and comments
        // both). A plain click (collapsed selection) is handled here.
        if (sel && !sel.isCollapsed) {
            return;
        }
        // Clicking an existing mark opens/edits it (in every tool, incl. Select).
        const marked = e.target.closest('[data-segcomment-id]');
        if (marked && this._root.contains(marked)) {
            this._openExisting(parseInt(marked.dataset.segcommentId, 10));
            return;
        }
        // The Select-text tool never creates a mark from a bare click.
        if (this._tool === 'select') {
            return;
        }
        const wrapper = e.target.closest('[data-source-type]');
        if (!wrapper || !this._root.contains(wrapper)) {
            return;
        }
        const mode = wrapper.dataset.anchorMode || 'segment';
        let target = null;
        if (mode === 'segment') {
            const span = e.target.closest('.' + SEG_CLASS);
            if (!span) {
                return;
            }
            target = this._targetForSegment(wrapper, span);
            if (target) {
                target.rect = span.getBoundingClientRect();
                target.getRect = () => span.getBoundingClientRect();
            }
        } else if (mode === 'pdfpage' && this._tool === 'comment') {
            // Bare click on a PDF with the Comment tool: drop a point (caret)
            // anchor rather than snapping to (and washing) the nearest word.
            target = this._targetForPoint(wrapper, e.clientX, e.clientY);
        } else {
            // Non-translated: click a word (caret → word range → offsets).
            target = this._targetForWord(wrapper, e.clientX, e.clientY);
        }
        if (!target) {
            return;
        }
        if (this._tool !== 'comment') {
            if (sel) {
                sel.removeAllRanges();
            }
            this._placeStamp(target, this._tool);
        } else {
            this._openComposer(target, null);
        }
    }

    /**
     * A drag-select in select/comment mode: comment on the selected phrase(s).
     * @param {Event} e The mouseup event.
     */
    _onMouseUp(e) {
        if (this._inUnfocusedPane(e)) {
            return;
        }
        if (this._pop && this._pop.contains(e.target)) {
            return;
        }
        if (this._pop && this._isDirty()) {
            return;
        }
        const sel = window.getSelection();
        if (!sel || sel.isCollapsed || sel.rangeCount === 0) {
            return;
        }
        // The Select-text tool is a plain copy selection: leave the native
        // selection intact and make no mark.
        if (this._tool === 'select') {
            return;
        }
        const text = sel.toString().trim();
        if (this._norm(text).length < 2) {
            return;
        }
        const range = sel.getRangeAt(0);
        const wrapper = this._wrapperOf(range.commonAncestorContainer);
        if (!wrapper) {
            return;
        }
        const mode = wrapper.dataset.anchorMode || 'segment';
        let target;
        if (mode === 'segment') {
            target = this._targetForSelection(wrapper, text);
        } else {
            const body = this._bodyForWrapper(wrapper);
            const off = body ? this._selectionToOffsets(body, range) : null;
            target = off ? this._offsetTarget(wrapper, off) : null;
        }
        if (!target) {
            return;
        }
        // This mouseup consumes the drag — swallow the click the browser fires
        // right after it, or that click would place a second word-sized mark at
        // the release point (see _squelchUntil).
        this._squelchUntil = performance.now() + 300;
        // A drag-select places a mark of any length in a stamp tool, or opens the
        // composer in comment mode.
        if (this._tool !== 'comment') {
            sel.removeAllRanges();
            this._placeStamp(target, this._tool);
            return;
        }
        const live = range.cloneRange();
        target.rect = range.getBoundingClientRect();
        target.getRect = () => live.getBoundingClientRect();
        this._openComposer(target, null);
    }

    /**
     * Compute a selection range's char offsets in a body's plaintext (the inverse
     * of the segmenting text-node walk), plus the selected text.
     * @param {HTMLElement} body The body element.
     * @param {Range} range The selection range.
     * @return {?{startoffset: number, endoffset: number, anchortext: string}}
     */
    _selectionToOffsets(body, range) {
        let start = -1;
        let end = -1;
        let pos = 0;
        const walker = document.createTreeWalker(body, NodeFilter.SHOW_TEXT);
        for (let n = walker.nextNode(); n; n = walker.nextNode()) {
            if (n === range.startContainer) {
                start = pos + range.startOffset;
            }
            if (n === range.endContainer) {
                end = pos + range.endOffset;
            }
            pos += n.nodeValue.length;
        }
        if (start < 0 || end < 0 || end <= start) {
            return null;
        }
        return {startoffset: start, endoffset: end, anchortext: range.toString()};
    }

    /**
     * Build an offset target from a click point: find the word under the cursor.
     * @param {HTMLElement} wrapper The source wrapper.
     * @param {number} x Viewport x.
     * @param {number} y Viewport y.
     * @return {?object} An offset target, or null.
     */
    _targetForWord(wrapper, x, y) {
        const body = this._bodyForWrapper(wrapper);
        if (!body) {
            return null;
        }
        const caret = this._caretRange(x, y);
        if (!caret || caret.startContainer.nodeType !== Node.TEXT_NODE || !body.contains(caret.startContainer)) {
            return null;
        }
        const text = caret.startContainer.nodeValue;
        let s = caret.startOffset;
        let e = caret.startOffset;
        while (s > 0 && /\S/.test(text[s - 1])) {
            s--;
        }
        while (e < text.length && /\S/.test(text[e])) {
            e++;
        }
        if (e <= s) {
            return null;
        }
        const wordRange = document.createRange();
        wordRange.setStart(caret.startContainer, s);
        wordRange.setEnd(caret.startContainer, e);
        const off = this._selectionToOffsets(body, wordRange);
        if (!off) {
            return null;
        }
        const live = wordRange.cloneRange();
        const target = this._offsetTarget(wrapper, off);
        target.rect = wordRange.getBoundingClientRect();
        target.getRect = () => live.getBoundingClientRect();
        return target;
    }

    /**
     * Build a POINT (caret) offset target from a click: anchor to the single
     * character at the caret rather than expanding to the whole word. The server
     * needs a non-empty anchortext, so a point is one char; the renderer draws it
     * as a thin caret (span ≤ 1) instead of a word wash.
     *
     * @param {HTMLElement} wrapper The source wrapper.
     * @param {number} x Viewport x.
     * @param {number} y Viewport y.
     * @return {?object} An offset target, or null.
     */
    _targetForPoint(wrapper, x, y) {
        const body = this._bodyForWrapper(wrapper);
        if (!body) {
            return null;
        }
        const caret = this._caretRange(x, y);
        if (!caret || caret.startContainer.nodeType !== Node.TEXT_NODE || !body.contains(caret.startContainer)) {
            return null;
        }
        const node = caret.startContainer;
        const text = node.nodeValue || '';
        let s = caret.startOffset;
        // Land on the nearest non-whitespace char (an all-whitespace anchor is
        // rejected server-side): prefer forward from the caret, else backward.
        if (!(s < text.length && /\S/.test(text[s]))) {
            let f = s;
            while (f < text.length && !/\S/.test(text[f])) {
                f++;
            }
            let b = s - 1;
            while (b >= 0 && !/\S/.test(text[b])) {
                b--;
            }
            if (f < text.length) {
                s = f;
            } else if (b >= 0) {
                s = b;
            } else {
                return null;
            }
        }
        const e = s + 1;
        if (e > text.length) {
            return null;
        }
        const charRange = document.createRange();
        charRange.setStart(node, s);
        charRange.setEnd(node, e);
        const off = this._selectionToOffsets(body, charRange);
        if (!off) {
            return null;
        }
        const live = charRange.cloneRange();
        const target = this._offsetTarget(wrapper, off);
        target.rect = charRange.getBoundingClientRect();
        target.getRect = () => live.getBoundingClientRect();
        return target;
    }

    /**
     * Cross-browser caret range from a viewport point.
     * @param {number} x Viewport x.
     * @param {number} y Viewport y.
     * @return {?Range}
     */
    _caretRange(x, y) {
        if (document.caretRangeFromPoint) {
            return document.caretRangeFromPoint(x, y);
        }
        if (document.caretPositionFromPoint) {
            const pos = document.caretPositionFromPoint(x, y);
            if (pos) {
                const r = document.createRange();
                r.setStart(pos.offsetNode, pos.offset);
                r.collapse(true);
                return r;
            }
        }
        return null;
    }

    /**
     * Open an existing mark: comments edit in the composer; stamps offer removal.
     * @param {number} id The mark id.
     */
    _openExisting(id) {
        const mark = this._comments.find((c) => parseInt(c.id, 10) === id);
        if (!mark) {
            return;
        }
        this._selectMark(id);
        if ((mark.marktype || 'comment') === 'comment') {
            this._editComment(mark);
        } else if (parseInt(mark.authorid, 10) === this._myId()) {
            const el = this._root.querySelector('[data-segcomment-id="' + id + '"]');
            this._openStampMenu(mark, el);
        }
    }

    /**
     * Edit a comment in the composer.
     * @param {object} mark The comment record.
     */
    _editComment(mark) {
        const el = this._root?.querySelector('.' + MARK_CLASS + '[data-segcomment-id="' + mark.id + '"]')
            || this._root?.querySelector('[data-segcomment-id="' + mark.id + '"]');
        const target = {
            sourcetype: mark.sourcetype || 'onlinetext',
            fileid: mark.fileid || 0,
            srcsegids: [],
            phrase: mark.anchortext,
            rect: el ? el.getBoundingClientRect() : null,
            getRect: el ? () => el.getBoundingClientRect() : null,
        };
        this._openComposer(target, mark);
    }

    /**
     * A tiny "delete this mark" menu for a stamp.
     * @param {object} mark The stamp record.
     * @param {?HTMLElement} el The stamp element (for positioning).
     */
    _openStampMenu(mark, el) {
        this._closeComposer();
        this._hideHoverPop();
        const pop = document.createElement('div');
        pop.className = 'local-unifiedgrader-segcomment-pop card shadow p-2';
        pop.style.position = 'fixed';
        pop.style.zIndex = '1080';
        pop.style.margin = '0';
        pop.appendChild(this._btn('btn-sm btn-outline-danger', 'segcomment_delete', 'Delete',
            () => this._delete(mark)));
        document.body.appendChild(pop);
        this._pop = pop;
        this._popText = null;
        this._anchorRect = el ? () => el.getBoundingClientRect() : (() => null);
        this._position(pop, el ? el.getBoundingClientRect() : null);
        this._armDismiss();
    }

    /**
     * Place a stamp of the given type on a segment target (immediate save).
     * @param {object} target The segment target.
     * @param {string} marktype The stamp type.
     */
    async _placeStamp(target, marktype) {
        // Clicking the same stamp type on the same phrase toggles it off.
        const existing = this._comments.find((c) => (c.marktype || 'comment') === marktype
            && parseInt(c.authorid, 10) === this._myId()
            && this._sameAnchor(c, target));
        if (existing) {
            this._delete(existing);
            return;
        }
        await this._save(target, null, '', marktype);
    }

    /**
     * Whether a stored mark anchors to the same place as a pending target.
     * Offset marks compare overlapping char ranges; segment marks compare source
     * ids (the translated phrase text can't be compared to the source anchortext).
     * @param {object} mark The stored mark.
     * @param {object} target The pending target.
     * @return {boolean}
     */
    _sameAnchor(mark, target) {
        if (target.mode === 'offset') {
            if (parseInt(mark.segmentid, 10) !== 0) {
                return false;
            }
            // A PDF mark also has to be on the same file + page to collide.
            if ((parseInt(mark.page, 10) || 0) !== (target.page || 0)) {
                return false;
            }
            if ((parseInt(mark.fileid, 10) || 0) !== (target.fileid || 0)) {
                return false;
            }
            const a1 = parseInt(mark.startoffset, 10) || 0;
            const a2 = parseInt(mark.endoffset, 10) || 0;
            return a1 < target.endoffset && a2 > target.startoffset;
        }
        const wanted = new Set(target.srcsegids || []);
        return this._srcIdsForComment(mark).some((id) => wanted.has(id));
    }

    // --- Mapping ----------------------------------------------------------------

    /**
     * A single clicked segment span → a target (its own id mapped to source ids).
     * @param {HTMLElement} wrapper The source wrapper.
     * @param {HTMLElement} span The clicked segment span.
     * @return {?object}
     */
    _targetForSegment(wrapper, span) {
        const source = this._sourceForWrapper(wrapper);
        if (!source) {
            return null;
        }
        const txid = parseInt(span.dataset.nidaSeg, 10);
        if (Number.isNaN(txid)) {
            return null;
        }
        const srcsegids = this._txToSrc([txid], source.groups);
        if (!srcsegids.length) {
            return null;
        }
        const sourcetype = wrapper.dataset.sourceType || 'onlinetext';
        return {
            sourcetype,
            fileid: sourcetype === 'file' ? source.fileid : 0,
            srcsegids,
            phrase: this._norm(span.textContent),
        };
    }

    /**
     * A drag-selected phrase → a target.
     * @param {HTMLElement} wrapper The source wrapper.
     * @param {string} text The selected text.
     * @return {?object}
     */
    _targetForSelection(wrapper, text) {
        const source = this._sourceForWrapper(wrapper);
        if (!source) {
            return null;
        }
        const selection = this._norm(text);
        const txids = [];
        source.segments.forEach((seg) => {
            const tx = this._norm(this._stripTags(seg.tx || ''));
            if (tx.length >= 2 && this._overlaps(selection, tx)) {
                txids.push(parseInt(seg.id, 10));
            }
        });
        if (!txids.length) {
            return null;
        }
        const srcsegids = this._txToSrc(txids, source.groups);
        if (!srcsegids.length) {
            return null;
        }
        const sourcetype = wrapper.dataset.sourceType || 'onlinetext';
        return {sourcetype, fileid: sourcetype === 'file' ? source.fileid : 0, srcsegids, phrase: text};
    }

    /**
     * Whether a selection and a segment overlap enough to count as selected.
     * @param {string} selection Normalised selection.
     * @param {string} seg Normalised segment text.
     * @return {boolean}
     */
    _overlaps(selection, seg) {
        if (selection.includes(seg) || seg.includes(selection)) {
            return true;
        }
        for (let len = seg.length; len >= 6; len--) {
            if (selection.includes(seg.slice(0, len)) || selection.includes(seg.slice(-len))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Map translation-segment ids to SOURCE ids via the n:m groups.
     * @param {Array<number>} txids Translation ids.
     * @param {Array} groups Alignment groups ([[srcIds],[txIds]]).
     * @return {Array<number>} Source ids, ascending, unique.
     */
    _txToSrc(txids, groups) {
        const wanted = new Set(txids);
        const out = new Set();
        const grouped = new Set();
        (Array.isArray(groups) ? groups : []).forEach((group) => {
            const srcIds = Array.isArray(group[0]) ? group[0].map(Number) : [];
            const txIds = Array.isArray(group[1]) ? group[1].map(Number) : [];
            txIds.forEach((t) => grouped.add(t));
            if (txIds.some((t) => wanted.has(t))) {
                srcIds.forEach((s) => out.add(s));
            }
        });
        txids.forEach((t) => {
            if (!grouped.has(t)) {
                out.add(t);
            }
        });
        return Array.from(out).sort((a, b) => a - b);
    }

    /**
     * The translation-segment ids a stored mark points at (via its source anchor).
     * @param {object} mark The mark record.
     * @param {object} source The parsed source.
     * @return {Array<number>}
     */
    _txIdsForComment(mark, source) {
        const anchor = this._norm(mark.anchortext);
        if (anchor.length < 2) {
            return [];
        }
        const srcids = new Set();
        source.segments.forEach((seg) => {
            const src = this._norm(this._stripTags(seg.src || ''));
            if (src.length >= 2 && (anchor.includes(src) || src.includes(anchor))) {
                srcids.add(parseInt(seg.id, 10));
            }
        });
        if (!srcids.size) {
            return [];
        }
        const txids = new Set();
        const grouped = new Set();
        (Array.isArray(source.groups) ? source.groups : []).forEach((group) => {
            const s = Array.isArray(group[0]) ? group[0].map(Number) : [];
            const t = Array.isArray(group[1]) ? group[1].map(Number) : [];
            s.forEach((id) => grouped.add(id));
            if (s.some((id) => srcids.has(id))) {
                t.forEach((id) => txids.add(id));
            }
        });
        srcids.forEach((id) => {
            if (!grouped.has(id)) {
                txids.add(id);
            }
        });
        return Array.from(txids);
    }

    /**
     * Re-derive a comment's source ids from the current alignment by anchortext.
     * @param {object} mark The mark.
     * @return {Array<number>}
     */
    _srcIdsForComment(mark) {
        const anchor = this._norm(mark.anchortext);
        if (anchor.length < 2) {
            return [];
        }
        for (const source of this._sources) {
            if (source.type !== (mark.sourcetype || 'onlinetext')) {
                continue;
            }
            if (source.type === 'file' && (source.fileid || 0) !== (parseInt(mark.fileid, 10) || 0)) {
                continue;
            }
            const ids = [];
            source.segments.forEach((seg) => {
                const src = this._norm(this._stripTags(seg.src || ''));
                if (src.length >= 2 && (anchor.includes(src) || src.includes(anchor))) {
                    ids.push(parseInt(seg.id, 10));
                }
            });
            if (ids.length) {
                return Array.from(new Set(ids)).sort((a, b) => a - b);
            }
        }
        return [];
    }

    // --- The composer (with comment library) ------------------------------------

    /**
     * Open the composer popover for a target phrase, new or editing.
     * @param {object} target {sourcetype, fileid, srcsegids, phrase, rect}.
     * @param {?object} existing The existing comment, or null.
     */
    _openComposer(target, existing) {
        // Synchronous by design: string labels fill in via .then, so `this._pop` is
        // set before any await could interleave a second open — otherwise two opens
        // (a card's own handler + the delegated view click) orphan a composer each.
        this._closeComposer();
        this._hideHoverPop();

        const pop = document.createElement('div');
        pop.className = 'local-unifiedgrader-segcomment-pop card shadow';
        pop.setAttribute('role', 'dialog');
        pop.style.position = 'fixed';
        pop.style.zIndex = '1080';
        pop.style.width = '350px';
        pop.style.maxWidth = 'calc(100vw - 16px)';
        pop.style.margin = '0';

        const header = document.createElement('div');
        header.className = 'small text-muted fst-italic px-2 pt-2';
        const phrase = target.phrase || target.anchortext || '';
        this._str('segcomment_anchor', 'Commenting on: “{$a}”', this._truncate(phrase, 80)).then((s) => {
            header.textContent = s;
            pop.setAttribute('aria-label', s);
            return s;
        }).catch(() => {});
        pop.appendChild(header);

        const textarea = document.createElement('textarea');
        textarea.className = 'form-control form-control-sm m-2';
        textarea.rows = 3;
        textarea.value = existing ? this._htmlToText(existing.commenttext) : '';
        textarea.dataset.initial = textarea.value;
        this._str('segcomment_placeholder', 'Write a comment…').then((s) => {
            textarea.placeholder = s;
            return s;
        }).catch(() => {});

        pop.appendChild(this._buildLibrary(textarea));
        pop.appendChild(textarea);

        const foot = document.createElement('div');
        foot.className = 'd-flex align-items-center gap-2 px-2 pb-2';
        foot.appendChild(this._btn('btn-primary btn-sm', 'segcomment_save', 'Save comment',
            () => this._save(target, existing, textarea.value, 'comment')));
        foot.appendChild(this._btn('btn-outline-secondary btn-sm', 'segcomment_cancel', 'Cancel',
            () => this._closeComposer()));
        const myId = this._myId();
        if (existing && (!myId || parseInt(existing.authorid, 10) === myId)) {
            foot.appendChild(this._btn('btn-link btn-sm p-0 text-danger ms-auto', 'segcomment_delete', 'Delete',
                () => this._delete(existing)));
        }
        pop.appendChild(foot);

        textarea.addEventListener('keydown', (ev) => {
            ev.stopPropagation();
            if (ev.key === 'Enter' && (ev.ctrlKey || ev.metaKey)) {
                ev.preventDefault();
                this._save(target, existing, textarea.value, 'comment');
            } else if (ev.key === 'Escape') {
                ev.preventDefault();
                this._closeComposer();
            }
        });

        document.body.appendChild(pop);
        this._pop = pop;
        this._popText = textarea;
        this._anchorRect = target.getRect || (() => target.rect);
        this._position(pop, target.rect);
        textarea.focus();
        this._armDismiss();
    }

    /**
     * A button with an async-resolved label.
     * @param {string} cls Button classes (after "btn ").
     * @param {string} key String key.
     * @param {string} fallback Fallback label.
     * @param {Function} onClick Click handler.
     * @return {HTMLButtonElement}
     */
    _btn(cls, key, fallback, onClick) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'btn ' + cls;
        // Sync label + idempotent async write — see _linkBtn for why.
        b.textContent = this._strs[key] ?? fallback;
        this._str(key, fallback).then((s) => {
            if (b.textContent !== s) {
                b.textContent = s;
            }
            return s;
        }).catch(() => {});
        b.addEventListener('click', onClick);
        return b;
    }

    /**
     * Build the comment-library section (tag chips + list). Clicking an entry fills
     * the composer textarea so the grader can use or tweak it.
     * @param {HTMLTextAreaElement} textarea The composer textarea.
     * @return {HTMLElement}
     */
    _buildLibrary(textarea) {
        const section = document.createElement('div');
        section.className = 'annotation-comment-picker-inline picker-library mx-2 mt-2';
        const tags = document.createElement('div');
        tags.className = 'picker-tags d-flex flex-wrap gap-1';
        const list = document.createElement('div');
        list.className = 'picker-list';
        section.appendChild(tags);
        section.appendChild(list);

        let activeTag = 0;
        const rerender = () => {
            this._renderLibTags(tags, activeTag, (id) => {
                activeTag = id;
                rerender();
            });
            this._renderLibList(list, activeTag, (content) => {
                textarea.value = content;
                textarea.focus();
            });
        };

        if (this._libraryLoaded) {
            rerender();
        } else {
            list.textContent = '…';
            // The list grows when it loads; re-place the composer so the footer
            // (Save / Cancel) never ends up pushed off-screen.
            const done = () => {
                rerender();
                this._reposition();
            };
            this._loadLibrary().then(done).catch(done);
        }
        return section;
    }

    /**
     * Load the comment library + tags (once).
     * @return {Promise<void>}
     */
    async _loadLibrary() {
        const container = this.element.closest('.local-unifiedgrader-container');
        const coursecode = container?.dataset.coursecode || '';
        try {
            const [comments, tags] = await Promise.all([
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_library_comments',
                    args: {coursecode, tagid: 0}, failurealert: false,
                }])[0],
                Ajax.call([{
                    methodname: 'local_unifiedgrader_get_library_tags', args: {}, failurealert: false,
                }])[0],
            ]);
            this._library = comments || [];
            this._libraryTags = (tags || []).slice().sort((a, b) => a.name.localeCompare(b.name));
        } catch (e) {
            this._library = [];
            this._libraryTags = [];
        }
        this._libraryLoaded = true;
    }

    /**
     * Render library tag filter chips.
     * @param {HTMLElement} container The tag container.
     * @param {number} activeTag The active tag id (0 = all).
     * @param {Function} onClick Called with the chosen tag id.
     */
    _renderLibTags(container, activeTag, onClick) {
        container.innerHTML = '';
        if (!this._libraryTags.length) {
            return;
        }
        const chip = (id, label) => {
            const el = document.createElement('span');
            el.className = 'badge ' + (activeTag === id ? 'bg-primary' : 'bg-light text-dark border');
            el.style.cursor = 'pointer';
            el.textContent = label;
            el.addEventListener('click', () => onClick(id));
            return el;
        };
        container.appendChild(chip(0, 'All'));
        this._libraryTags.forEach((t) => container.appendChild(chip(t.id, t.name)));
    }

    /**
     * Render the (filtered) library comment list.
     * @param {HTMLElement} container The list container.
     * @param {number} activeTag The active tag id.
     * @param {Function} onSelect Called with the chosen comment content.
     */
    _renderLibList(container, activeTag, onSelect) {
        container.innerHTML = '';
        let items = this._library;
        if (activeTag) {
            items = items.filter((c) => c.tagids && c.tagids.includes(activeTag));
        }
        if (!items.length) {
            const empty = document.createElement('div');
            empty.className = 'picker-empty';
            empty.textContent = '—';
            container.appendChild(empty);
            return;
        }
        items.forEach((c) => {
            const item = document.createElement('div');
            item.className = 'picker-comment-item';
            item.textContent = c.content.length > 120 ? c.content.slice(0, 120) + '…' : c.content;
            item.addEventListener('click', () => onSelect(c.content));
            container.appendChild(item);
        });
    }

    // --- Persistence ------------------------------------------------------------

    /**
     * Save a mark: create (an edit is create-then-delete). Comments carry text;
     * stamps carry an empty body and a marktype.
     * @param {object} target The pending target.
     * @param {?object} existing The existing comment being edited, or null.
     * @param {string} value The comment text.
     * @param {string} marktype The mark type.
     */
    async _save(target, existing, value, marktype) {
        const commenttext = (value || '').trim();
        if (marktype === 'comment' && !commenttext) {
            return;
        }
        const cmid = parseInt(this.reactive.state.activity?.cmid, 10);
        const userid = this._loadedUser;
        const attempt = Number.isInteger(this.reactive.state.submission?.attemptnumber)
            ? this.reactive.state.submission.attemptnumber : -1;
        if (!cmid || !userid) {
            return;
        }

        // An edit reuses the existing mark's own anchor (offset marks store
        // segmentid 0); a new mark uses the target's anchor mode. Offset-anchored
        // marks go through the non-nida save_text_comment path.
        const isOffset = existing ? parseInt(existing.segmentid, 10) === 0 : target.mode === 'offset';
        let call;
        if (isOffset) {
            const a = existing || target;
            call = {
                methodname: 'local_unifiedgrader_save_text_comment',
                args: {
                    cmid, userid, attempt,
                    sourcetype: a.sourcetype || 'onlinetext',
                    fileid: parseInt(a.fileid, 10) || 0,
                    page: parseInt(a.page, 10) || 0,
                    startoffset: parseInt(a.startoffset, 10) || 0,
                    endoffset: parseInt(a.endoffset, 10) || 0,
                    anchortext: a.anchortext || '',
                    commenttext, commentformat: 1, marktype,
                    color: existing ? (existing.color || '') : this._commentColor(),
                },
            };
        } else {
            let srcsegids = target.srcsegids;
            if (existing) {
                const rederived = this._srcIdsForComment(existing);
                if (!rederived.length) {
                    return;
                }
                srcsegids = rederived;
            }
            if (!srcsegids || !srcsegids.length) {
                return;
            }
            call = {
                methodname: 'local_unifiedgrader_save_segment_comment',
                args: {
                    cmid, userid, attempt,
                    sourcetype: target.sourcetype, fileid: target.fileid,
                    srcsegids, commenttext, commentformat: 1, marktype,
                    color: existing ? (existing.color || '') : this._commentColor(),
                },
            };
        }
        try {
            const created = await Ajax.call([call])[0];
            const shaped = this._toListShape(created);
            this._comments.push(shaped);
            if (existing) {
                // An edit is create-then-delete; a delete that finds the old row
                // already gone is fine (the goal was to remove it).
                await this._deleteId(existing.id);
                this._comments = this._comments.filter((c) => c.id !== existing.id);
            } else {
                // A brand-new mark is undoable; an edit is not (it just swaps ids).
                this._recordOp('add', shaped);
                this._selectedId = 0;
            }
            this._closeComposer();
            this._render();
        } catch (error) {
            Notification.exception(error);
        }
    }

    /**
     * Delete one row by id, tolerating "already gone". Surfaces other errors.
     * @param {number} id The mark id.
     * @return {Promise<boolean>} Whether the row is now absent.
     */
    async _deleteId(id) {
        try {
            await Ajax.call([{
                methodname: 'local_unifiedgrader_delete_segment_comment',
                args: {id}, failurealert: false,
            }])[0];
            return true;
        } catch (e) {
            if (e && e.errorcode === 'segcomment_notfound') {
                return true;
            }
            Notification.exception(e);
            return false;
        }
    }

    /**
     * Delete a mark (author-scoped). One click, no dialog: window.confirm is
     * unreliable here (WebKit suppresses it once the user activation expires
     * across the await gap fetching the confirm string — the dialog silently
     * never appeared, so Delete "did nothing"), and every delete is undoable
     * from the toolbar anyway. Clear-all, which is NOT undoable, uses a
     * two-click armed button instead.
     * @param {object} mark The mark to delete.
     */
    async _delete(mark) {
        this._recordOp('remove', {...mark});
        await this._deleteId(mark.id);
        this._comments = this._comments.filter((c) => c.id !== mark.id);
        if (parseInt(mark.id, 10) === this._selectedId) {
            this._selectedId = 0;
        }
        this._closeComposer();
        this._hideHoverPop();
        this._render();
    }

    /**
     * Normalise a saved row into the list-render shape.
     * @param {object} row The created row.
     * @return {object}
     */
    _toListShape(row) {
        return {
            id: row.id, segmentid: row.segmentid, currentsegmentid: 0,
            sourcetype: row.sourcetype, fileid: row.fileid, page: row.page || 0,
            startoffset: row.startoffset, endoffset: row.endoffset,
            anchortext: row.anchortext, commenttext: row.commenttext,
            commentformat: row.commentformat, marktype: row.marktype || 'comment',
            authorid: row.authorid, authorfullname: '',
            color: row.color || '',
            timecreated: row.timecreated, timemodified: row.timemodified,
        };
    }

    /**
     * The colour to store on a new comment: the grader's currently selected
     * palette colour, or '' to let the client fall back to the default.
     * @return {string} A #RRGGBB hex, or ''.
     */
    _commentColor() {
        return (/^#[0-9a-fA-F]{6}$/).test(this._shapeColor || '') ? this._shapeColor : '';
    }

    /**
     * The render colour for a mark: its stored colour, or the default blue when
     * unset/invalid (so pre-colour comments keep their look).
     * @param {object} mark The mark record.
     * @return {string} A #RRGGBB hex.
     */
    _markColor(mark) {
        const c = (mark && mark.color) || '';
        return (/^#[0-9a-fA-F]{6}$/).test(c) ? c : '#0f6cbf';
    }

    /**
     * A translucent rgba() from a #RRGGBB hex, for the highlight wash.
     * @param {string} hex A #RRGGBB colour.
     * @param {number} alpha Opacity 0–1.
     * @return {string} An rgba() string (or the input if unparseable).
     */
    _tint(hex, alpha) {
        const m = (/^#([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})$/).exec(hex);
        if (!m) {
            return hex;
        }
        return 'rgba(' + parseInt(m[1], 16) + ', ' + parseInt(m[2], 16)
            + ', ' + parseInt(m[3], 16) + ', ' + alpha + ')';
    }

    /**
     * WCAG relative luminance of a #RRGGBB colour (0 black … 1 white).
     * @param {string} hex A #RRGGBB colour.
     * @return {number}
     */
    _relLum(hex) {
        const m = (/^#([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})$/).exec(hex);
        if (!m) {
            return 0;
        }
        const ch = [1, 2, 3].map((i) => {
            const c = parseInt(m[i], 16) / 255;
            return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * ch[0] + 0.7152 * ch[1] + 0.0722 * ch[2];
    }

    /**
     * The legible number/text colour for a marker background: white unless it
     * would fail WCAG AA (4.5:1) on that background — as on a bright yellow —
     * where a dark number reads far better. Keeps the dark swatches on white.
     * @param {string} bg A #RRGGBB background colour.
     * @return {string} '#fff' or a dark ink.
     */
    _textOn(bg) {
        const whiteContrast = 1.05 / (this._relLum(bg) + 0.05);
        return whiteContrast >= 4.5 ? '#fff' : '#212529';
    }

    // --- History (undo/redo), selection, delete, clear --------------------------

    /**
     * A vertical separator between toolbar button groups.
     * @return {HTMLElement}
     */
    _sep() {
        const sep = document.createElement('span');
        sep.className = 'local-unifiedgrader-segtool-sep';
        sep.setAttribute('aria-hidden', 'true');
        return sep;
    }

    /**
     * Build an icon-only toolbar button wired to a handler.
     * @param {string} icon FontAwesome icon class (e.g. fa-undo).
     * @param {string} key Language string key for the label/title.
     * @param {string} fallback Fallback label.
     * @param {Function} onClick Click handler.
     * @return {HTMLButtonElement}
     */
    _iconBtn(icon, key, fallback, onClick) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-secondary';
        btn.innerHTML = '<i class="fa ' + icon + '" aria-hidden="true"></i>';
        this._str(key, fallback).then((s) => {
            btn.title = s;
            btn.setAttribute('aria-label', s);
            return s;
        }).catch(() => {});
        btn.addEventListener('click', onClick);
        return btn;
    }

    /**
     * Build the shape tool: a strip button showing the current shape, with a
     * popout to pick rectangle / ellipse / line / arrow. PDF-only.
     * @return {HTMLElement}
     */
    _buildShapeCluster() {
        const wrap = document.createElement('span');
        wrap.className = 'position-relative';
        wrap.dataset.pdfOnly = '1';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-secondary local-unifiedgrader-segtool';
        btn.dataset.segtool = 'shape';
        this._shapeBtn = btn;
        this._str('annotate_shape', 'Shapes').then((s) => {
            btn.title = s;
            btn.setAttribute('aria-label', s);
            return s;
        }).catch(() => {});

        const pop = document.createElement('div');
        pop.className = 'local-unifiedgrader-segwarn-pop card shadow d-none p-1';
        // Inline positioning — the .card class would drop it into flow (Boost
        // loads Bootstrap after plugin CSS) and stretch the toolbar.
        pop.style.position = 'absolute';
        pop.style.top = '100%';
        pop.style.left = '0';
        pop.style.right = 'auto';
        pop.style.zIndex = '1085';
        pop.style.width = 'auto';
        pop.style.whiteSpace = 'nowrap';
        SHAPES.forEach((s) => {
            const opt = document.createElement('button');
            opt.type = 'button';
            opt.className = 'btn btn-sm btn-outline-secondary border-0 mx-1'
                + (s.shape === this._shape ? ' active' : '');
            opt.dataset.shape = s.shape;
            opt.innerHTML = '<i class="fa ' + s.icon + '" aria-hidden="true"></i>';
            this._str(s.key, s.fallback).then((t) => {
                opt.title = t;
                opt.setAttribute('aria-label', t);
                return t;
            }).catch(() => {});
            opt.addEventListener('click', (e) => {
                e.stopPropagation();
                this._shape = s.shape;
                pop.querySelectorAll('[data-shape]').forEach((b) => {
                    b.classList.toggle('active', b.dataset.shape === s.shape);
                });
                this._updateShapeBtn();
                pop.classList.add('d-none');
                // A stamp entry places a glyph at the click; the others drag out a
                // shape. Both live in this popout, but they are different tools.
                if (s.stamp) {
                    this._stamp = s.stamp;
                    this._setTool('stamp');
                } else {
                    this._setTool('shape');
                }
            });
            pop.appendChild(opt);
        });

        // Clicking the button activates the shape tool and toggles the picker.
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            this._closePopouts(pop);
            pop.classList.toggle('d-none');
            if (this._tool !== 'shape') {
                this._setTool('shape');
            }
        });
        document.addEventListener('click', () => pop.classList.add('d-none'));

        wrap.appendChild(btn);
        wrap.appendChild(pop);
        this._updateShapeBtn();
        return wrap;
    }

    /**
     * Reflect the current shape on the shape tool button's icon.
     */
    _updateShapeBtn() {
        const meta = SHAPES.find((s) => s.shape === this._shape) || SHAPES[0];
        if (this._shapeBtn) {
            this._shapeBtn.innerHTML = '<i class="fa ' + meta.icon + '" aria-hidden="true"></i>';
        }
    }

    /**
     * Build the select/move tool: puts the Fabric layer into select mode so the
     * grader can click an existing shape/stroke and drag it for fine positioning.
     * PDF-only.
     * @return {HTMLElement}
     */
    _buildMoveTool() {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-secondary local-unifiedgrader-segtool';
        btn.dataset.segtool = 'annotmove';
        btn.dataset.pdfOnly = '1';
        btn.innerHTML = '<i class="fa fa-arrows-up-down-left-right" aria-hidden="true"></i>';
        this._str('segtool_move', 'Select / move annotations').then((s) => {
            btn.title = s;
            btn.setAttribute('aria-label', s);
            return s;
        }).catch(() => {});
        btn.addEventListener('click', () => this._setTool('annotmove'));
        return btn;
    }

    /**
     * Build the freehand pen tool: a strip button with a popout to pick the
     * brush width. PDF-only.
     * @return {HTMLElement}
     */
    _buildPenCluster() {
        const wrap = document.createElement('span');
        wrap.className = 'position-relative';
        wrap.dataset.pdfOnly = '1';

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-secondary local-unifiedgrader-segtool';
        btn.dataset.segtool = 'pen';
        btn.innerHTML = '<i class="fa fa-pencil" aria-hidden="true"></i>';
        this._str('annotate_pen', 'Pen').then((s) => {
            btn.title = s;
            btn.setAttribute('aria-label', s);
            return s;
        }).catch(() => {});

        const pop = document.createElement('div');
        pop.className = 'local-unifiedgrader-segwarn-pop card shadow d-none p-1 d-flex flex-row align-items-center gap-1';
        pop.style.position = 'absolute';
        pop.style.top = '100%';
        pop.style.left = '0';
        pop.style.right = 'auto';
        pop.style.zIndex = '1085';
        pop.style.width = 'auto';
        PEN_WIDTHS.forEach((w) => {
            const opt = document.createElement('button');
            opt.type = 'button';
            opt.className = 'btn btn-sm btn-outline-secondary border-0 d-flex align-items-center'
                + (w.width === this._penWidth ? ' active' : '');
            opt.dataset.penwidth = w.width;
            // A short line whose thickness previews the brush width.
            opt.innerHTML = '<span style="display:inline-block;width:22px;height:' + w.width
                + 'px;background:currentColor;border-radius:' + w.width + 'px;"></span>';
            this._str(w.key, w.fallback).then((s) => {
                opt.title = s;
                opt.setAttribute('aria-label', s);
                return s;
            }).catch(() => {});
            opt.addEventListener('click', (e) => {
                e.stopPropagation();
                this._penWidth = w.width;
                pop.querySelectorAll('[data-penwidth]').forEach((b) => {
                    b.classList.toggle('active', parseInt(b.dataset.penwidth, 10) === w.width);
                });
                pop.classList.add('d-none');
                this._setTool('pen');
            });
            pop.appendChild(opt);
        });

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            this._closePopouts(pop);
            pop.classList.toggle('d-none');
            if (this._tool !== 'pen') {
                this._setTool('pen');
            }
        });
        document.addEventListener('click', () => pop.classList.add('d-none'));

        wrap.appendChild(btn);
        wrap.appendChild(pop);
        return wrap;
    }

    /**
     * Build the drawing colour tool: one button showing the current colour as a
     * dot, expanding to a popout of full-size swatches. PDF-only.
     * @return {HTMLElement}
     */
    _buildColorCluster() {
        const wrap = document.createElement('span');
        wrap.className = 'position-relative';
        wrap.dataset.pdfOnly = '1';

        // The trigger IS a swatch: a circle filled with the current colour.
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm p-0 rounded-circle local-unifiedgrader-segswatch'
            + ' local-unifiedgrader-segswatch-current';
        btn.style.background = this._shapeColor;
        this._str('segtool_colour', 'Drawing colour').then((s) => {
            btn.title = s;
            btn.setAttribute('aria-label', s);
            return s;
        }).catch(() => {});

        const pop = document.createElement('div');
        pop.className = 'local-unifiedgrader-segwarn-pop local-unifiedgrader-segswatch-pop card shadow d-none';
        // Inline positioning — the .card class would drop it into flow (Boost
        // loads Bootstrap after plugin CSS) and stretch the toolbar.
        pop.style.position = 'absolute';
        pop.style.top = '100%';
        pop.style.left = '0';
        pop.style.right = 'auto';
        pop.style.zIndex = '1085';
        pop.style.width = 'auto';
        COLORS.forEach((c) => {
            const swatch = document.createElement('button');
            swatch.type = 'button';
            swatch.className = 'btn btn-sm p-0 rounded-circle local-unifiedgrader-segswatch'
                + (c.color === this._shapeColor ? ' active' : '');
            swatch.dataset.color = c.color;
            swatch.style.background = c.color;
            this._str(c.key, c.fallback).then((s) => {
                swatch.title = s;
                swatch.setAttribute('aria-label', s);
                return s;
            }).catch(() => {});
            swatch.addEventListener('click', (e) => {
                e.stopPropagation();
                this._shapeColor = c.color;
                pop.querySelectorAll('[data-color]').forEach((b) => {
                    b.classList.toggle('active', b.dataset.color === c.color);
                });
                btn.style.background = c.color;
                pop.classList.add('d-none');
                this._dispatchPdfTool({color: c.color});
            });
            pop.appendChild(swatch);
        });

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            this._closePopouts(pop);
            pop.classList.toggle('d-none');
        });
        document.addEventListener('click', () => pop.classList.add('d-none'));

        wrap.appendChild(btn);
        wrap.appendChild(pop);
        return wrap;
    }

    /**
     * Drive the PDF viewer's Fabric annotation layers (shape tool, colour) via
     * the decoupled document event.
     * @param {object} detail {tool?, shape?, color?, action?}.
     */
    _dispatchPdfTool(detail) {
        document.dispatchEvent(new CustomEvent('unifiedgrader:pdftool', {detail}));
    }

    /**
     * Switch how comments display and persist the choice.
     * @param {string} mode 'column' or 'popup'.
     */
    _setCommentView(mode) {
        this._commentView = mode === 'popup' ? 'popup' : 'column';
        try {
            window.localStorage.setItem('local_unifiedgrader_segview', this._commentView);
        } catch (e) {
            // Storage unavailable — the choice just won't stick.
        }
        this._hideHoverPop();
        this._updateViewBtn();
        this._render();
    }

    /**
     * Reflect the current comment view on the toggle button (icon + label).
     */
    _updateViewBtn() {
        const btn = this._viewBtn;
        if (!btn) {
            return;
        }
        const column = this._commentView === 'column';
        // fa-buffer is a FontAwesome BRANDS-family glyph: it only renders with
        // the fa-brands font family, not the default solid one.
        btn.innerHTML = column
            ? '<i class="fa fa-columns" aria-hidden="true"></i>'
            : '<i class="fa-brands fa-buffer" aria-hidden="true"></i>';
        btn.setAttribute('aria-pressed', column ? 'true' : 'false');
        const key = column ? 'segtool_viewcolumn' : 'segtool_viewpopup';
        const fb = column
            ? 'Comments in the margin — click for hover popups'
            : 'Comments as hover popups — click for the margin column';
        btn.title = this._strs[key] ?? fb;
        this._str(key, fb).then((s) => {
            btn.title = s;
            btn.setAttribute('aria-label', s);
            return s;
        }).catch(() => {});
    }

    /**
     * Announce the horizontal space the PDF viewer should reserve beside each
     * page: the comment column's width while it is in use, else zero. The viewer
     * re-fits its zoom on change so the page is never pushed out of the pane.
     */
    _syncReserve() {
        const want = this._commentView === 'column'
            && this._hasPdf()
            && this._comments.some((c) => (c.sourcetype || '') === 'file'
                && (c.marktype || 'comment') === 'comment');
        const px = want ? 262 : 0;
        if (px === this._reserved) {
            return;
        }
        this._reserved = px;
        document.dispatchEvent(new CustomEvent('unifiedgrader:pdfmarginreserve', {detail: {px}}));
    }

    /**
     * Show the hover popup for a comment mark (popup view only).
     * @param {number} id The mark id.
     * @param {HTMLElement} el The hovered mark element (anchor).
     */
    _showHoverPop(id, el) {
        if (this._hoverHideTimer) {
            clearTimeout(this._hoverHideTimer);
            this._hoverHideTimer = 0;
        }
        // Never stack over the composer; re-hovering the same mark is a no-op.
        if (this._pop) {
            return;
        }
        if (this._hoverPop && parseInt(this._hoverPop.dataset.segcommentId, 10) === id) {
            return;
        }
        const mark = this._comments.find((c) => parseInt(c.id, 10) === id);
        if (!mark || (mark.marktype || 'comment') !== 'comment') {
            return;
        }
        this._hideHoverPop();
        const pop = document.createElement('div');
        pop.className = 'local-unifiedgrader-segcomment-pop card shadow p-2';
        pop.style.position = 'fixed';
        pop.style.zIndex = '1080';
        pop.style.margin = '0';
        pop.dataset.segcommentId = mark.id;
        const num = this._root?.querySelector(
            '.local-unifiedgrader-seg-pin[data-segcomment-id="' + id + '"]')?.textContent || '';
        const card = this._card(mark, num);
        card.classList.remove('mb-2', 'border', 'bg-light');
        pop.appendChild(card);
        // Moving the pointer into the popup keeps it open (to reach Edit/Delete).
        pop.addEventListener('mouseenter', () => {
            if (this._hoverHideTimer) {
                clearTimeout(this._hoverHideTimer);
                this._hoverHideTimer = 0;
            }
        });
        pop.addEventListener('mouseleave', () => this._scheduleHoverHide());
        document.body.appendChild(pop);
        this._hoverPop = pop;
        this._position(pop, el.getBoundingClientRect());
    }

    /**
     * Hide the hover popup after a grace period (long enough to reach it).
     */
    _scheduleHoverHide() {
        if (this._hoverHideTimer) {
            clearTimeout(this._hoverHideTimer);
        }
        this._hoverHideTimer = setTimeout(() => {
            this._hoverHideTimer = 0;
            this._hideHoverPop();
        }, 300);
    }

    /**
     * Remove the hover popup, if open.
     */
    _hideHoverPop() {
        if (this._hoverHideTimer) {
            clearTimeout(this._hoverHideTimer);
            this._hoverHideTimer = 0;
        }
        this._hoverPop?.remove();
        this._hoverPop = null;
    }

    /**
     * Two-click confirmation for a destructive button: the first click arms it
     * (label swaps to "Confirm?" for a few seconds), a second click within that
     * window fires the action. Replaces window.confirm, which WebKit suppresses
     * when the user activation has expired across an async gap — the dialog
     * silently never opened and the action appeared to do nothing.
     * @param {HTMLButtonElement} btn The button whose label is swapped in place.
     * @param {Function} run The destructive action.
     * @return {Function} The click handler to attach.
     */
    _armed(btn, run) {
        return () => {
            if (btn.dataset.armed) {
                delete btn.dataset.armed;
                if (btn.dataset.restoreLabel) {
                    btn.textContent = btn.dataset.restoreLabel;
                    delete btn.dataset.restoreLabel;
                }
                run();
                return;
            }
            btn.dataset.armed = '1';
            btn.dataset.restoreLabel = btn.textContent;
            btn.textContent = 'Confirm?';
            this._str('segtool_confirm', 'Confirm?').then((s) => {
                if (btn.dataset.armed) {
                    btn.textContent = s;
                }
                return s;
            }).catch(() => {});
            setTimeout(() => {
                if (btn.dataset.armed) {
                    delete btn.dataset.armed;
                    btn.textContent = btn.dataset.restoreLabel || btn.textContent;
                    delete btn.dataset.restoreLabel;
                }
            }, 4000);
        };
    }

    /**
     * Record a forward operation for undo, clearing the redo stack (a new action
     * forks history). Edits are not recorded (they swap ids), only add/remove.
     * @param {string} type 'add' or 'remove'.
     * @param {object} data The mark row (a copy).
     */
    _recordOp(type, data) {
        this._history.push({type, data});
        this._redoStack = [];
        this._updateHistoryButtons();
    }

    /**
     * Drop Fabric (shape/pen) markers from the undo/redo timeline. Called when the
     * PDF viewer rebuilds its layers (zoom): the Fabric layers' own undo stacks
     * reset, so these markers would otherwise undo into empty layers and no-op.
     * Text-mark ops are unaffected.
     */
    _purgeFabricHistory() {
        const total = this._history.length + this._redoStack.length;
        this._history = this._history.filter((op) => op.type !== 'fabric');
        this._redoStack = this._redoStack.filter((op) => op.type !== 'fabric');
        if (this._history.length + this._redoStack.length !== total) {
            this._updateHistoryButtons();
        }
    }

    /**
     * Reflect history/selection availability on the toolbar buttons.
     */
    _updateHistoryButtons() {
        if (this._undoBtn) {
            this._undoBtn.disabled = !this._history.length;
        }
        if (this._redoBtn) {
            this._redoBtn.disabled = !this._redoStack.length;
        }
        if (this._delBtn) {
            // In a canvas tool the trash targets the canvas's selected object; we
            // can't cheaply know if one is selected, so keep it enabled there.
            this._delBtn.disabled = !this._isCanvasTool() && !this._selectedId;
        }
    }

    /**
     * Mark one mark as the current selection (for the toolbar delete button).
     * @param {number|string} id The mark id.
     */
    _selectMark(id) {
        this._selectedId = parseInt(id, 10) || 0;
        this._applySelection();
        this._updateHistoryButtons();
    }

    /**
     * Re-apply the selection outline to the selected mark's spans/pin/card. Called
     * after each render (which rebuilds the decorated DOM).
     */
    _applySelection() {
        if (!this._root) {
            return;
        }
        this._root.querySelectorAll('.local-unifiedgrader-seg-selected')
            .forEach((el) => el.classList.remove('local-unifiedgrader-seg-selected'));
        if (!this._selectedId) {
            return;
        }
        this._root.querySelectorAll('[data-segcomment-id="' + this._selectedId + '"]')
            .forEach((el) => el.classList.add('local-unifiedgrader-seg-selected'));
    }

    /**
     * Delete the currently selected mark (toolbar delete button).
     */
    _deleteSelected() {
        // In a canvas tool the trash acts on the Fabric canvas's selected object.
        if (this._isCanvasTool()) {
            this._dispatchPdfTool({action: 'deleteselected'});
            return;
        }
        if (!this._selectedId) {
            return;
        }
        const mark = this._comments.find((c) => parseInt(c.id, 10) === this._selectedId);
        if (mark) {
            this._delete(mark);
        }
    }

    /**
     * Delete every mark the current grader authored on this submission.
     * Destructive and NOT undoable, so the toolbar button arms first (two-click
     * confirm — see _armed); by the time this runs the grader has confirmed.
     * Clears the undo/redo history.
     */
    async _clearAll() {
        // Also wipe the PDF's drawn (Fabric) annotations — shapes, pen strokes —
        // which are a separate layer from the text marks.
        if (this._hasPdf()) {
            this._dispatchPdfTool({action: 'clearall'});
        }
        const myId = this._myId();
        const mine = this._comments.filter((c) => !myId || parseInt(c.authorid, 10) === myId);
        for (const mark of mine) {
            // eslint-disable-next-line no-await-in-loop
            await this._deleteId(mark.id);
        }
        const ids = new Set(mine.map((c) => c.id));
        this._comments = this._comments.filter((c) => !ids.has(c.id));
        this._history = [];
        this._redoStack = [];
        this._selectedId = 0;
        this._closeComposer();
        this._render();
    }

    /**
     * Undo the last add/remove: an add is undone by deleting the mark; a remove is
     * undone by re-creating it (which mints a new id — tracked for redo).
     */
    async _undo() {
        const op = this._history.pop();
        if (!op) {
            return;
        }
        // A Fabric (shape/pen) action: the pixel layer repaints itself; just ask
        // the owning page's layer to undo. No mark re-render needed.
        if (op.type === 'fabric') {
            this._dispatchPdfTool({action: 'undo', page: op.data.page});
            this._redoStack.push(op);
            this._updateHistoryButtons();
            return;
        }
        if (op.type === 'add') {
            await this._deleteId(op.data.id);
            this._comments = this._comments.filter((c) => c.id !== op.data.id);
            if (parseInt(op.data.id, 10) === this._selectedId) {
                this._selectedId = 0;
            }
        } else {
            const created = await this._recreate(op.data);
            if (created) {
                op.data = created;
            }
        }
        this._redoStack.push(op);
        this._afterHistory();
    }

    /**
     * Redo the last undone op: re-run the forward action.
     */
    async _redo() {
        const op = this._redoStack.pop();
        if (!op) {
            return;
        }
        if (op.type === 'fabric') {
            this._dispatchPdfTool({action: 'redo', page: op.data.page});
            this._history.push(op);
            this._updateHistoryButtons();
            return;
        }
        if (op.type === 'add') {
            const created = await this._recreate(op.data);
            if (created) {
                op.data = created;
            }
        } else {
            await this._deleteId(op.data.id);
            this._comments = this._comments.filter((c) => c.id !== op.data.id);
            if (parseInt(op.data.id, 10) === this._selectedId) {
                this._selectedId = 0;
            }
        }
        this._history.push(op);
        this._afterHistory();
    }

    /**
     * Shared tail for undo/redo: close any composer and repaint.
     */
    _afterHistory() {
        this._closeComposer();
        this._render();
        this._updateHistoryButtons();
    }

    /**
     * Re-create a previously removed mark from its stored row (used by undo of a
     * delete and redo of an add). Offset marks (segmentid 0) go through the
     * non-nida text path; segment marks re-derive their source ids from the row.
     * @param {object} data The stored mark row.
     * @return {Promise<?object>} The re-created list-shaped row, or null on failure.
     */
    async _recreate(data) {
        const cmid = parseInt(this.reactive.state.activity?.cmid, 10);
        const userid = this._loadedUser;
        const attempt = Number.isInteger(this.reactive.state.submission?.attemptnumber)
            ? this.reactive.state.submission.attemptnumber : -1;
        if (!cmid || !userid) {
            return null;
        }
        const marktype = data.marktype || 'comment';
        let call;
        if (parseInt(data.segmentid, 10) === 0) {
            call = {
                methodname: 'local_unifiedgrader_save_text_comment',
                args: {
                    cmid, userid, attempt,
                    sourcetype: data.sourcetype || 'onlinetext',
                    fileid: parseInt(data.fileid, 10) || 0,
                    page: parseInt(data.page, 10) || 0,
                    startoffset: parseInt(data.startoffset, 10) || 0,
                    endoffset: parseInt(data.endoffset, 10) || 0,
                    anchortext: data.anchortext || '',
                    commenttext: data.commenttext || '', commentformat: 1, marktype,
                    color: data.color || '',
                },
            };
        } else {
            const srcsegids = this._srcIdsForComment(data);
            if (!srcsegids.length) {
                return null;
            }
            call = {
                methodname: 'local_unifiedgrader_save_segment_comment',
                args: {
                    cmid, userid, attempt,
                    sourcetype: data.sourcetype, fileid: data.fileid,
                    srcsegids, commenttext: data.commenttext || '', commentformat: 1, marktype,
                    color: data.color || '',
                },
            };
        }
        try {
            const created = await Ajax.call([call])[0];
            const shaped = this._toListShape(created);
            this._comments.push(shaped);
            return shaped;
        } catch (error) {
            Notification.exception(error);
            return null;
        }
    }

    // --- Composer positioning / dismissal ---------------------------------------

    /**
     * Position a fixed popover at a rect: below it, flipped above when cramped,
     * clamped to the viewport.
     * @param {HTMLElement} pop The popover.
     * @param {?DOMRect} rect The anchor rect.
     */
    _position(pop, rect) {
        const margin = 8;
        const vw = window.innerWidth;
        const vh = window.innerHeight;
        const w = pop.offsetWidth || 340;
        const h = pop.offsetHeight || 160;
        let left = rect ? rect.left : margin;
        if (left + w > vw - margin) {
            left = vw - w - margin;
        }
        left = Math.max(margin, left);
        let top = rect ? rect.bottom + 6 : margin;
        if (top + h > vh - margin) {
            const above = (rect ? rect.top : margin) - 6 - h;
            top = above >= margin ? above : Math.max(margin, vh - h - margin);
        }
        pop.style.left = Math.round(left) + 'px';
        pop.style.top = Math.round(top) + 'px';
    }

    /**
     * Arm outside-click / scroll dismissers (deferred one frame).
     */
    _armDismiss() {
        this._disarmDismiss();
        const pop = this._pop;
        const onDown = (ev) => {
            if (!this._pop || this._pop.contains(ev.target)) {
                return;
            }
            if (this._isDirty()) {
                return;
            }
            this._closeComposer();
        };
        const onScroll = () => this._reposition();
        this._dismissRaf = requestAnimationFrame(() => {
            this._dismissRaf = 0;
            if (this._pop !== pop) {
                return;
            }
            document.addEventListener('mousedown', onDown, true);
            window.addEventListener('scroll', onScroll, true);
            this._dismiss = () => {
                document.removeEventListener('mousedown', onDown, true);
                window.removeEventListener('scroll', onScroll, true);
            };
        });
    }

    /**
     * Whether the open composer holds unsaved text.
     * @return {boolean}
     */
    _isDirty() {
        if (!this._popText) {
            return false;
        }
        return this._popText.value.trim() !== (this._popText.dataset.initial || '').trim();
    }

    /**
     * Re-place the open popover at its anchor's current position.
     */
    _reposition() {
        if (this._pop && this._anchorRect) {
            this._position(this._pop, this._anchorRect());
        }
    }

    /**
     * Remove the dismissers and cancel any pending arm.
     */
    _disarmDismiss() {
        if (this._dismissRaf) {
            cancelAnimationFrame(this._dismissRaf);
            this._dismissRaf = 0;
        }
        if (this._dismiss) {
            this._dismiss();
            this._dismiss = null;
        }
    }

    /**
     * Close and remove the composer popover.
     */
    _closeComposer() {
        this._disarmDismiss();
        if (this._pop) {
            this._pop.remove();
            this._pop = null;
        }
        this._popText = null;
        this._anchorRect = null;
    }

    // --- DOM helpers ------------------------------------------------------------

    /**
     * Map a character offset in the flattened text stream to a {node, offset}.
     * @param {Array<{node: Text, start: number}>} nodes The flattened map.
     * @param {number} pos The absolute offset.
     * @return {?{node: Text, offset: number}}
     */
    _locate(nodes, pos) {
        for (let i = nodes.length - 1; i >= 0; i--) {
            if (pos >= nodes[i].start) {
                return {node: nodes[i].node, offset: pos - nodes[i].start};
            }
        }
        return null;
    }

    /**
     * Wrap the text between two points in spans of a class, splitting boundary text
     * nodes and wrapping each intervening text node (a range may cross inline tags).
     * @param {{node: Text, offset: number}} start Start point.
     * @param {{node: Text, offset: number}} end End point.
     * @param {HTMLElement} body The containing body.
     * @param {string} className The class for the wrapper spans.
     * @param {object} data Dataset entries to set on each span.
     * @return {Array<HTMLElement>} The spans created (document order).
     */
    _wrapRange(start, end, body, className, data) {
        const walker = document.createTreeWalker(body, NodeFilter.SHOW_TEXT);
        const between = [];
        let inside = false;
        for (let n = walker.nextNode(); n; n = walker.nextNode()) {
            if (n === start.node) {
                inside = true;
            }
            if (inside) {
                between.push(n);
            }
            if (n === end.node) {
                break;
            }
        }
        const created = [];
        for (let i = between.length - 1; i >= 0; i--) {
            const node = between[i];
            const s = node === start.node ? start.offset : 0;
            const e = node === end.node ? end.offset : node.nodeValue.length;
            if (e <= s) {
                continue;
            }
            const range = document.createRange();
            range.setStart(node, s);
            range.setEnd(node, e);
            const span = document.createElement('span');
            span.className = className;
            Object.keys(data || {}).forEach((k) => {
                span.dataset[k] = data[k];
            });
            range.surroundContents(span);
            created.push(span);
        }
        return created.reverse();
    }

    /**
     * Wrap a stored offset-anchored mark's char range in the body, returning the
     * created spans. Verifies the range text against the mark's anchortext and
     * falls back to a whitespace-tolerant search when the offsets have drifted.
     * @param {HTMLElement} body The body element.
     * @param {object} mark The mark record (startoffset/endoffset/anchortext).
     * @return {Array<HTMLElement>} The created marker spans (may be empty).
     */
    _wrapOffset(body, mark) {
        const loc = this._resolveOffsets(body, mark);
        if (!loc) {
            return [];
        }
        try {
            return this._wrapRange(loc.start, loc.end, body, SEG_CLASS, {});
        } catch (e) {
            // A range that can't be surrounded (unexpected DOM shape) is skipped,
            // not fatal to the rest of the render.
            return [];
        }
    }

    /**
     * Resolve a stored offset-anchored mark to start/end locators in a body's
     * current text. Verifies the range text against the mark's anchortext and
     * falls back to a whitespace-tolerant search when the offsets have drifted;
     * clamps to the current text length (file/PDF offsets are client-trusted and
     * can drift — a re-uploaded PDF, a PDF.js version change, a corrupt row —
     * and an out-of-range offset must skip one mark, not abort the render).
     * @param {HTMLElement} body The body element.
     * @param {object} mark The mark record (startoffset/endoffset/anchortext).
     * @param {?{nodes: Array, raw: string}} walk A prebuilt text walk of the body
     *     — pass one when resolving several marks against an unchanging body
     *     (the PDF overlay path) to avoid re-walking per mark.
     * @return {?{start: {node: Node, offset: number}, end: {node: Node, offset: number}}}
     */
    _resolveOffsets(body, mark, walk) {
        const {nodes, raw} = walk || this._walkText(body);
        let from = Math.max(0, parseInt(mark.startoffset, 10) || 0);
        let to = Math.max(from, parseInt(mark.endoffset, 10) || 0);
        const anchor = this._norm(mark.anchortext);
        if (anchor.length >= 2 && this._norm(raw.slice(from, to)) !== anchor) {
            const tokens = anchor.split(' ').filter(Boolean).map((t) => t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
            if (tokens.length) {
                const m = new RegExp(tokens.join('\\s+'), 'i').exec(raw);
                if (m) {
                    from = m.index;
                    to = from + m[0].length;
                }
            }
        }
        to = Math.min(to, raw.length);
        from = Math.max(0, Math.min(from, to));
        if (to <= from) {
            return null;
        }
        const start = this._locate(nodes, from);
        const end = this._locate(nodes, to);
        if (!start || !end) {
            return null;
        }
        return {start, end};
    }

    /**
     * Walk a body's text nodes once, returning them with their start offsets
     * plus the concatenated text.
     * @param {HTMLElement} body The body element.
     * @return {{nodes: Array<{node: Node, start: number}>, raw: string}}
     */
    _walkText(body) {
        const nodes = [];
        const walker = document.createTreeWalker(body, NodeFilter.SHOW_TEXT);
        let raw = '';
        for (let n = walker.nextNode(); n; n = walker.nextNode()) {
            nodes.push({node: n, start: raw.length});
            raw += n.nodeValue;
        }
        return {nodes, raw};
    }

    /**
     * Unwrap a span, restoring its text into the parent (and normalising).
     * @param {HTMLElement} span The span.
     */
    _unwrap(span) {
        const parent = span.parentNode;
        if (!parent) {
            return;
        }
        // Drop any injected pins/badges before restoring the text.
        span.querySelectorAll('.local-unifiedgrader-seg-pin, .local-unifiedgrader-seg-stampbadge')
            .forEach((el) => el.remove());
        while (span.firstChild) {
            parent.insertBefore(span.firstChild, span);
        }
        parent.removeChild(span);
        parent.normalize();
    }

    /**
     * The nearest source wrapper for a node inside the view.
     * @param {Node} node A DOM node.
     * @return {?HTMLElement}
     */
    _wrapperOf(node) {
        const el = node.nodeType === Node.ELEMENT_NODE ? node : node.parentElement;
        if (!el || !this._root.contains(el)) {
            return null;
        }
        return el.closest('[data-source-type]');
    }

    /**
     * Resolve the parsed source for a wrapper by identity (type + fileid).
     * @param {HTMLElement} wrapper The wrapper.
     * @return {?object}
     */
    _sourceForWrapper(wrapper) {
        if (!wrapper) {
            return null;
        }
        const type = wrapper.dataset.sourceType || 'onlinetext';
        const fileid = parseInt(wrapper.dataset.fileid, 10) || 0;
        return this._sources.find((s) => s.type === type && (s.fileid || 0) === fileid)
            || this._sources.find((s) => s.type === type)
            || null;
    }

    /**
     * The logged-in grader's user id (the author of marks they create).
     *
     * NOT reactive.state.currentUser.id — that tracks the currently VIEWED
     * student (loadStudent sets it), so it is the wrong identity for "did I
     * author this mark". M.cfg.userId is the logged-in user, matching how the
     * other comment modules resolve authorship and how the server stamps it.
     * @return {number}
     */
    _myId() {
        return parseInt(window.M?.cfg?.userId, 10) || 0;
    }

    /**
     * Reduce whitespace to single spaces and trim.
     * @param {string} value Raw text.
     * @return {string}
     */
    _norm(value) {
        return (value || '').replace(/\s+/g, ' ').trim();
    }

    /**
     * Strip HTML to plain text via an inert document.
     * @param {string} html The HTML.
     * @return {string}
     */
    _stripTags(html) {
        const doc = new DOMParser().parseFromString(html || '', 'text/html');
        return doc.body.textContent || '';
    }

    /**
     * Strip HTML to plain text (for prefilling the composer).
     * @param {string} html The HTML.
     * @return {string}
     */
    _htmlToText(html) {
        return this._stripTags(html).trim();
    }

    /**
     * Truncate text for the composer header.
     * @param {string} value The text.
     * @param {number} max The max length.
     * @return {string}
     */
    _truncate(value, max) {
        const v = (value || '').trim();
        return v.length > max ? v.slice(0, max - 1) + '…' : v;
    }

    /**
     * Resolve a UG string, falling back to a supplied English default.
     * @param {string} key The string key.
     * @param {string} fallback The English default.
     * @param {*} [a] Optional placeholder.
     * @return {Promise<string>}
     */
    async _str(key, fallback, a) {
        try {
            const s = await getString(key, 'local_unifiedgrader', a);
            if (a === undefined || a === null) {
                this._strs[key] = s;
            }
            return s;
        } catch (e) {
            if (a !== undefined && a !== null) {
                return String(fallback).replace('{$a}', a);
            }
            return fallback;
        }
    }
}
