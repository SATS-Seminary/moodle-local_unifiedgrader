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
import {iconForFile} from 'local_unifiedgrader/lib/file_icons';

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
            TEXT_ANNOT_VIEW: '[data-region="text-annot-view"]',
            ANNOTATION_TOOLBAR: '[data-region="annotation-toolbar"]',
            SPLIT_VIEW: '[data-region="split-view"]',
            SPLIT_DIVIDER: '[data-region="split-divider"]',
            FORUM_VIEW_TOOLBAR: '[data-region="forum-view-toolbar"]',
            FORUM_CONTEXT_VIEW: '[data-region="forum-context-view"]',
            FORUM_PAGER: '[data-region="forum-pager"]',
            FORUM_PAGER_LABEL: '[data-region="forum-pager-label"]',
            FORUM_JUMP_NEXT: '[data-region="forum-jump-next"]',
        };
        this._container = null;
        this._currentFileId = null;
        /** @type {?PdfViewer} */
        this._pdfViewer = null;
        /**
         * Dual-file (multi-view) state. Two stacked panes, each showing one of the
         * submission's files, so a recording can be watched while its manuscript is
         * marked. Off by default — the single viewer above is the normal path.
         * @type {boolean}
         */
        this._multiview = false;
        /** @type {Object<string, ?PdfViewer>} Per-pane PDF viewer, keyed 'a'/'b'. */
        this._panePdf = {a: null, b: null};
        /** @type {Object<string, number>} File id shown in each pane (0 = none). */
        this._paneFile = {a: 0, b: 0};
        /** @type {string} The pane that owns the annotation tools. */
        this._activePane = 'b';
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
            // Mode changes, page turns and rating updates all arrive here.
            // currentpostid is written by the marking panel too — that shared
            // field is the whole binding between the two components.
            {watch: 'forumcontext:updated', handler: this._renderForumContext},
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

        this._setupForumViewControls();

        if (state.submission) {
            this._renderSubmission({state});
        }
        this._renderForumContext({state});
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
        this.getElement(this.selectors.TEXT_ANNOT_VIEW)?.classList.add('d-none');
        this.getElement(this.selectors.FORUM_CONTEXT_VIEW)?.classList.add('d-none');
        // Hidden by default; re-shown only when a PDF is previewed.
        this._setPixelToolbar(false);
        this._currentFileId = null;
        this._removePortfolioPopout();
        this._updateForumToolbar(state);

        // Reset file selector in the right panel.
        this._renderFileSelector([]);

        if (!submission || submission.status === 'nosubmission' || submission.status === 'reopened'
                || !submission.status) {
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
        const hasPortfolio = !!(submission.portfoliourl);

        // Render the right-column pill selector. Includes "Portfolio" when
        // present, "Submission" when other content exists, plus each file.
        this._renderFileSelector(files, hasContent, isForum, hasPortfolio);

        // Offer (or withdraw) the dual-file toggle for this submission. Done here,
        // with the real file list — the reset call above passes an empty array, and
        // judging availability from that would switch the view off on every render.
        this._updateMultiviewAvailability(files, hasContent);

        // Byblos portfolio submissions take priority — render the portfolio
        // iframe as the default view. Other content remains accessible via pills.
        if (hasPortfolio) {
            this._showPortfolio(submission.portfoliourl);
            return;
        }

        // In the dual-file view the panes own the preview. Stop here rather than
        // auto-previewing into the single viewer: those regions sit ABOVE the split
        // in the DOM, so showing one would push a clipped third pane above the two
        // (most visibly with online text, which renders inline).
        if (this._multiview) {
            this._seedPanes(files, hasContent);
            return;
        }

        if (files.length > 0) {
            // Auto-preview the first previewable file.
            const firstPreviewable = files.find(f => this._isPreviewable(f));
            if (firstPreviewable) {
                this._previewFile(firstPreviewable);
                return;
            }
            // Files exist but none are previewable — fall through to show
            // submission content (online text, audio, etc.) if available.
        }

        // Show submission content in an iframe via a proper Moodle page.
        // This ensures submission plugin CSS, JS, and AMD modules load correctly
        // (e.g. ytsubmission's YouTube player, online text, etc.).
        if (hasContent || submission.status === 'submitted') {
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
     * @param {boolean} hasPortfolio Whether the submission has a Byblos portfolio URL.
     */
    _renderFileSelector(files, hasContent = false, isForum = false, hasPortfolio = false) {
        if (!this._container) {
            return;
        }
        const wrapper = this._container.querySelector('[data-region="file-selector"]');
        const list = this._container.querySelector('[data-region="file-selector-list"]');
        if (!wrapper || !list) {
            return;
        }

        list.innerHTML = '';

        // Show the pill bar whenever a portfolio is present (so it has a pill),
        // there is other content, or there are files to choose between.
        if (files.length === 0 && !hasPortfolio) {
            wrapper.classList.add('d-none');
            return;
        }

        wrapper.classList.remove('d-none');

        // Portfolio pill — primary view when a Byblos portfolio is submitted.
        if (hasPortfolio) {
            const pill = document.createElement('span');
            pill.className = 'btn-group btn-group-sm';
            pill.dataset.fileid = 'portfolio';

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-secondary d-flex align-items-center gap-1';
            const icon = document.createElement('i');
            icon.className = 'fa fa-book';
            icon.setAttribute('aria-hidden', 'true');
            btn.appendChild(icon);
            const label = document.createElement('span');
            label.className = 'small';
            label.textContent = 'Portfolio';
            getString('portfolio_pill', 'local_unifiedgrader')
                .then((str) => { label.textContent = str; })
                .catch(() => {});
            btn.appendChild(label);
            btn.addEventListener('click', () => {
                if (this._pdfViewer) {
                    this._pdfViewer.saveAnnotationsNow();
                }
                const url = this.reactive.state.submission?.portfoliourl;
                if (url) {
                    this._showPortfolio(url);
                    this._currentFileId = null;
                    this._highlightFileButton('portfolio');
                }
            });

            pill.appendChild(btn);
            list.appendChild(pill);
        }

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
            // File-type icon: with several attachments (e.g. a recording plus its
            // manuscript) the kind of file is what the teacher scans for, not the
            // filename.
            const typeIcon = document.createElement('i');
            typeIcon.className = 'fa ' + iconForFile(file) + ' me-1';
            typeIcon.setAttribute('aria-hidden', 'true');
            previewBtn.appendChild(typeIcon);
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
        // In the dual-file view the single viewer is not on screen, so a click on a
        // file pill loads that file into the focused pane instead of tearing the
        // split down and losing the pairing the teacher set up.
        if (this._multiview) {
            this.showFileInPane(file, this._activePane);
            this._currentFileId = file.fileid;
            this._highlightFileButton(file.fileid);
            return;
        }

        const pdfWrapper = this.getElement(this.selectors.PDF_VIEWER_WRAPPER);
        const docPreview = this.getElement(this.selectors.DOCUMENT_PREVIEW);

        // Hide the viewers (incl. the inline text sheet) first.
        pdfWrapper.classList.add('d-none');
        docPreview.classList.add('d-none');
        this.getElement(this.selectors.TEXT_ANNOT_VIEW)?.classList.add('d-none');

        // Remove the portfolio pop-out button when switching to a file preview.
        this._removePortfolioPopout();

        if ((file.mimetype === 'application/pdf' || file.convertible) && this._pdfViewer) {
            // Use PDF.js viewer for PDF files (and files converted to PDF). The old
            // Fabric annotation toolbar is superseded by the text-anchored marks
            // strip (seg_comments), which shows itself over the PDF; keep the old
            // one hidden so it no longer grabs focus. (Freehand/shape tools are
            // folded into the unified strip in a later slice.)
            this._setPixelToolbar(false);
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
            this._setPixelToolbar(false);
            const iframe = this.getElement(this.selectors.PREVIEW_IFRAME);
            const cmid = this.reactive.state.activity?.cmid;
            iframe.src = `${M.cfg.wwwroot}/local/unifiedgrader/preview_media.php`
                + `?fileid=${file.fileid}&cmid=${cmid}`;
            docPreview.classList.remove('d-none');
        } else {
            // Use iframe for images, text, etc.
            this._setPixelToolbar(false);
            const iframe = this.getElement(this.selectors.PREVIEW_IFRAME);
            iframe.src = file.previewurl || file.url;
            docPreview.classList.remove('d-none');
        }

        this._currentFileId = file.fileid;

        // Highlight the active file button in the right-panel selector.
        this._highlightFileButton(file.fileid);
    }

    // --- Dual file (multi-view) -------------------------------------------------

    /**
     * Show or hide the dual-file toggle for this submission, and drop out of the
     * view if the student we just moved to has nothing to pair.
     *
     * @param {Array<object>} files The submission's files.
     * @param {boolean} hasContent Whether the submission also has online text.
     */
    _updateMultiviewAvailability(files, hasContent) {
        const btn = this._container?.querySelector('[data-action="layout-multiview"]');
        if (!btn) {
            return;
        }
        // Count the submission's own content as a pairable source: a manuscript
        // attached alongside a TinyMCE-recorded video is one file plus the online
        // text, which is exactly the case this view is for.
        const previewable = (files || []).filter((f) => this._isPreviewable(f));
        const sources = previewable.length + (hasContent ? 1 : 0);
        const available = sources >= 2;
        btn.classList.toggle('d-none', !available);

        if (!available && this._multiview) {
            // Navigating to a student with nothing to pair must not leave a stale
            // split showing the previous student's documents.
            btn.setAttribute('aria-pressed', 'false');
            btn.classList.remove('active');
            this.setMultiview(false, files);
            return;
        }
        if (this._multiview) {
            // Same view, new student: clear the pairing so the panes re-seed from
            // this student's own submission.
            this._paneFile.a = 0;
            this._paneFile.b = 0;
        }
    }

    /**
     * One of the two split panes.
     * @param {string} pane 'a' (top) or 'b' (bottom).
     * @return {?HTMLElement}
     */
    _paneEl(pane) {
        return this.getElement('[data-region="split-pane"][data-pane="' + pane + '"]');
    }

    /**
     * Whether the dual-file view is currently showing.
     * @return {boolean}
     */
    isMultiview() {
        return this._multiview;
    }

    /**
     * Turn the dual-file view on or off.
     *
     * On: the single-viewer regions are hidden and the two stacked panes take over.
     * Off: the panes are blanked (so a video stops playing and a PDF stops holding
     * its document) and the normal single viewer is restored.
     *
     * @param {boolean} on Whether to show two files at once.
     * @param {Array<object>} [files] The submission's files, used to seed the panes.
     */
    setMultiview(on, files = []) {
        this._multiview = !!on;
        const split = this.getElement(this.selectors.SPLIT_VIEW);
        if (!split) {
            return;
        }

        const singles = [
            this.selectors.PDF_VIEWER_WRAPPER,
            this.selectors.DOCUMENT_PREVIEW,
            this.selectors.TEXT_ANNOT_VIEW,
        ];

        if (!this._multiview) {
            split.classList.add('d-none');
            split.classList.remove('d-flex');
            // Blank both panes so media stops playing behind the single view.
            ['a', 'b'].forEach((p) => this._clearPane(p));
            // Restore whichever file was last shown singly.
            const files2 = files.length ? files : (this.reactive.state.submission?.files || []);
            const previous = files2.find((f) => parseInt(f.fileid, 10) === parseInt(this._currentFileId, 10));
            if (previous) {
                this._previewFile(previous);
            }
            return;
        }

        singles.forEach((sel) => this.getElement(sel)?.classList.add('d-none'));
        this._removePortfolioPopout();
        split.classList.remove('d-none');
        split.classList.add('d-flex');
        this._initDivider();

        const list = files.length ? files : (this.reactive.state.submission?.files || []);
        this._seedPanes(list, !!this.reactive.state.submission?.hascontent);
    }

    /**
     * Fill the panes for the current submission.
     *
     * Pairs a recording with a document, which is the case this view exists for.
     * The recording may be an attached media file OR a clip embedded in the
     * submission's online text by the TinyMCE recorder — the latter is not a
     * "file" at all, so submission content is offered as a pane source in its own
     * right (value 'content').
     *
     * @param {Array<object>} files The submission's files.
     * @param {boolean} hasContent Whether the submission also has online text.
     */
    _seedPanes(files, hasContent) {
        this._populatePaneSelects(files, hasContent);

        if (this._paneFile.a || this._paneFile.b) {
            // Already paired (e.g. a re-render); leave the teacher's choice alone.
            this._setActivePane(this._activePane);
            return;
        }

        const media = files.find((f) => this._isMediaFile(f));
        const doc = files.find((f) => !this._isMediaFile(f) && this._isAnnotatable(f))
            || files.find((f) => !this._isMediaFile(f));

        // Top pane: an attached recording, else the online text (which is where a
        // TinyMCE-recorded video lives), else whatever the first file is.
        if (media) {
            this.showFileInPane(media, 'a');
        } else if (hasContent) {
            this.showContentInPane('a');
        } else if (files[0]) {
            this.showFileInPane(files[0], 'a');
        }

        // Bottom pane: the document to mark up.
        const top = this._paneFile.a;
        const bottom = doc && String(doc.fileid) !== String(top)
            ? doc
            : files.find((f) => String(f.fileid) !== String(top));
        if (bottom) {
            this.showFileInPane(bottom, 'b');
        } else if (hasContent && top !== 'content') {
            this.showContentInPane('b');
        }

        // Marks belong to the document, so focus the annotatable pane by default.
        this._setActivePane(this._paneFile.b ? 'b' : 'a');
    }

    /**
     * Show the submission's own content (online text) in a pane.
     *
     * For assignments the text is rendered inline, exactly as the single view does
     * it, so an embedded recording plays and seg_comments can still anchor marks to
     * the words. Anything else (forum posts, quiz attempts) keeps the iframe so the
     * submission plugin's own CSS/JS loads.
     *
     * @param {string} pane 'a' (top) or 'b' (bottom).
     */
    showContentInPane(pane) {
        const el = this._paneEl(pane);
        if (!el) {
            return;
        }
        el.querySelector('[data-region="split-pdf"]')?.classList.add('d-none');
        el.querySelector('[data-region="split-doc"]')?.classList.add('d-none');
        el.querySelector('[data-region="split-text"]')?.classList.add('d-none');
        el.querySelector('[data-region="split-pane-empty"]')?.classList.add('d-none');

        const icon = el.querySelector('[data-region="split-pane-icon"]');
        if (icon) {
            icon.className = 'fa fa-file-alt';
        }
        const select = el.querySelector('[data-region="split-pane-select"]');
        if (select) {
            select.value = 'content';
        }

        const state = this.reactive.state;
        const isAssign = state.activity?.type === 'assign';
        const html = state.submission?.onlinetexthtml || '';
        const textHost = el.querySelector('[data-region="split-text"]');

        if (isAssign && html && textHost) {
            this._renderInlineText(textHost, html);
            textHost.classList.remove('d-none');
            this._trackPaneMediaHeight(textHost);
        } else {
            const iframe = el.querySelector('[data-region="split-iframe"]');
            const cmid = state.activity?.cmid;
            const userid = state.submission?.userid;
            if (iframe && cmid && userid) {
                let url = `${M.cfg.wwwroot}/local/unifiedgrader/preview_submission.php`
                    + `?cmid=${cmid}&userid=${userid}`;
                const attemptnumber = state.submission?.attemptnumber;
                if (attemptnumber !== undefined && attemptnumber !== null && attemptnumber >= 0) {
                    url += `&attempt=${attemptnumber}`;
                }
                iframe.src = url;
                el.querySelector('[data-region="split-doc"]')?.classList.remove('d-none');
            }
        }

        this._paneFile[pane] = 'content';
    }

    /**
     * Keep embedded media sized to the pane it sits in.
     *
     * A recording in an online-text submission (satsrecorder, or a bare <video>)
     * carries its own intrinsic size, so dragging the divider would otherwise clip
     * it instead of shrinking it. The stylesheet caps such media at
     * --ug-media-max-h; this keeps that value in step with the pane's real height,
     * on drag and on window resize alike.
     *
     * @param {HTMLElement} host The pane's inline-text host.
     */
    _trackPaneMediaHeight(host) {
        const apply = () => {
            // Leave a little room so the media never fills the pane edge to edge
            // and the surrounding text stays reachable by scrolling.
            const available = Math.max(120, host.clientHeight - 24);
            host.style.setProperty('--ug-media-max-h', available + 'px');
        };
        apply();

        if (host.dataset.mediaObserved === '1' || typeof ResizeObserver === 'undefined') {
            return;
        }
        host.dataset.mediaObserved = '1';
        // The pane's height is set by the flex layout and the divider drag, so
        // observe the element itself rather than listening for drag events.
        new ResizeObserver(apply).observe(host);
    }

    /**
     * Whether a file plays as media (and so can never carry text annotations).
     * @param {object} file The submission file.
     * @return {boolean}
     */
    _isMediaFile(file) {
        const m = (file && file.mimetype) || '';
        return m.startsWith('audio/') || m.startsWith('video/');
    }

    /**
     * Whether a file opens in the annotatable PDF viewer.
     * @param {object} file The submission file.
     * @return {boolean}
     */
    _isAnnotatable(file) {
        return !!file && (file.mimetype === 'application/pdf' || !!file.convertible);
    }

    /**
     * Empty a pane: stop its media, drop its PDF, and show the placeholder.
     * @param {string} pane 'a' or 'b'.
     */
    _clearPane(pane) {
        const el = this._paneEl(pane);
        if (!el) {
            return;
        }
        const iframe = el.querySelector('[data-region="split-iframe"]');
        if (iframe) {
            // about:blank rather than removing the node — this is what stops a video
            // continuing to play once the pane is hidden.
            iframe.src = 'about:blank';
        }
        el.querySelector('[data-region="split-pdf"]')?.classList.add('d-none');
        el.querySelector('[data-region="split-doc"]')?.classList.add('d-none');
        const text = el.querySelector('[data-region="split-text"]');
        if (text) {
            text.classList.add('d-none');
            // Drop the inline copy too, so its anchors can't outlive the pane.
            text.innerHTML = '';
        }
        el.querySelector('[data-region="split-pane-empty"]')?.classList.remove('d-none');
        const select = el.querySelector('[data-region="split-pane-select"]');
        if (select) {
            select.value = '0';
        }
        this._paneFile[pane] = 0;
    }

    /**
     * Fill both panes' file choosers from the submission's files.
     *
     * Every file is offered in both panes (plus a "None" entry), which is how more
     * than two attachments are supported: the teacher pairs whichever two they
     * want rather than being limited to a fixed first-and-second.
     *
     * @param {Array<object>} files The submission files.
     * @param {boolean} hasContent Whether to offer the submission's online text.
     */
    _populatePaneSelects(files, hasContent) {
        ['a', 'b'].forEach((pane) => {
            const select = this._paneEl(pane)?.querySelector('[data-region="split-pane-select"]');
            if (!select) {
                return;
            }
            const current = select.value;
            select.innerHTML = '';
            const none = document.createElement('option');
            none.value = '0';
            getString('multiview_none', 'local_unifiedgrader')
                .then((s) => { none.textContent = s; return s; })
                .catch(() => { none.textContent = '—'; });
            select.appendChild(none);

            // Submission content is a first-class pane source: a recording made
            // with the TinyMCE recorder lives inside the online text, not in a file.
            if (hasContent) {
                const opt = document.createElement('option');
                opt.value = 'content';
                const isForum = this.reactive.state.activity?.type === 'forum';
                getString(isForum ? 'forum_posts_pill' : 'submission_content_pill', 'local_unifiedgrader')
                    .then((s) => { opt.textContent = s; return s; })
                    .catch(() => { opt.textContent = 'Submission'; });
                select.appendChild(opt);
            }

            files.forEach((file) => {
                if (!this._isPreviewable(file)) {
                    return;
                }
                const opt = document.createElement('option');
                opt.value = String(file.fileid);
                opt.textContent = file.filename;
                select.appendChild(opt);
            });
            if (current) {
                select.value = current;
            }

            if (select.dataset.bound !== '1') {
                select.dataset.bound = '1';
                select.addEventListener('change', () => {
                    const value = select.value;
                    if (value === 'content') {
                        this.showContentInPane(pane);
                        return;
                    }
                    const id = parseInt(value, 10) || 0;
                    if (!id) {
                        this._clearPane(pane);
                        return;
                    }
                    const chosen = (this.reactive.state.submission?.files || [])
                        .find((f) => parseInt(f.fileid, 10) === id);
                    if (chosen) {
                        this.showFileInPane(chosen, pane);
                    }
                });
            }
        });
    }

    /**
     * Show one submission file in one pane, routing it the same way the single
     * viewer does: the PDF viewer for documents, the media player page for audio
     * and video, a plain iframe for anything else previewable.
     *
     * @param {object} file The submission file.
     * @param {string} pane 'a' (top) or 'b' (bottom).
     */
    showFileInPane(file, pane) {
        const el = this._paneEl(pane);
        if (!el || !file) {
            return;
        }
        const pdfHost = el.querySelector('[data-region="split-pdf"]');
        const docHost = el.querySelector('[data-region="split-doc"]');
        const empty = el.querySelector('[data-region="split-pane-empty"]');
        const iframe = el.querySelector('[data-region="split-iframe"]');

        pdfHost?.classList.add('d-none');
        docHost?.classList.add('d-none');
        empty?.classList.add('d-none');
        const textHost = el.querySelector('[data-region="split-text"]');
        if (textHost) {
            // Swapping a pane away from online text must remove the inline copy,
            // or its text would still be in the DOM for seg_comments to anchor to.
            textHost.classList.add('d-none');
            textHost.innerHTML = '';
        }

        // Pane header: type icon + the chooser reflecting what is shown here.
        const icon = el.querySelector('[data-region="split-pane-icon"]');
        if (icon) {
            icon.className = 'fa ' + iconForFile(file);
        }
        const select = el.querySelector('[data-region="split-pane-select"]');
        if (select) {
            select.value = String(file.fileid);
        }

        const state = this.reactive.state;
        if (this._isAnnotatable(file)) {
            const viewer = this._ensurePaneViewer(pane);
            if (viewer) {
                pdfHost.classList.remove('d-none');
                viewer.setFileContext(
                    parseInt(state.activity.cmid, 10),
                    parseInt(state.currentUser.id, 10),
                    parseInt(file.fileid, 10),
                );
                viewer.setFileInfo(file);
                viewer.loadPdf(file.previewurl || file.url);
            }
        } else if (this._isMediaFile(file)) {
            iframe.src = `${M.cfg.wwwroot}/local/unifiedgrader/preview_media.php`
                + `?fileid=${file.fileid}&cmid=${state.activity?.cmid}`;
            docHost.classList.remove('d-none');
        } else {
            iframe.src = file.previewurl || file.url;
            docHost.classList.remove('d-none');
        }

        this._paneFile[pane] = parseInt(file.fileid, 10) || 0;
        // A media pane can never take marks, so put the tools on the other one.
        if (this._isMediaFile(file) && this._activePane === pane) {
            this._setActivePane(pane === 'a' ? 'b' : 'a');
        }
    }

    /**
     * Create (once) the PDF viewer that backs a pane.
     *
     * Each pane owns a separate PdfViewer instance on its own root element, so the
     * two documents keep independent pages, zoom and annotation layers. PdfViewer
     * scopes its queries to that element, so the duplicated markup does not clash.
     *
     * @param {string} pane 'a' or 'b'.
     * @return {?PdfViewer}
     */
    _ensurePaneViewer(pane) {
        if (this._panePdf[pane]) {
            return this._panePdf[pane];
        }
        const host = this._paneEl(pane)?.querySelector('[data-region="split-pdf"] [data-region="pdf-viewer"]');
        if (!host) {
            return null;
        }
        // The legacy pixel toolbar inside the partial is superseded by the marks
        // strip; keep it hidden so it cannot grab focus in either pane.
        host.querySelector('[data-region="annotation-toolbar"]')?.classList.add('d-none');
        this._panePdf[pane] = new PdfViewer({element: host, reactive: this.reactive});
        return this._panePdf[pane];
    }

    /**
     * Focus a pane for marking: it gets the "Marking here" badge and the highlight,
     * and its document is the one the annotation tools act on.
     *
     * @param {string} pane 'a' or 'b'.
     */
    _setActivePane(pane) {
        this._activePane = pane;
        ['a', 'b'].forEach((p) => {
            const el = this._paneEl(p);
            if (!el) {
                return;
            }
            const active = p === pane;
            el.classList.toggle('is-active', active);
            // Only badge the pane that can actually take marks.
            const annotatable = active && !!this._paneFile[p] && this._paneIsAnnotatable(p);
            el.querySelector('[data-region="split-pane-active"]')?.classList.toggle('d-none', !annotatable);
        });
    }

    /**
     * Whether the file currently in a pane can carry annotations.
     * @param {string} pane 'a' or 'b'.
     * @return {boolean}
     */
    _paneIsAnnotatable(pane) {
        // Inline online text takes marks too (seg_comments anchors them by offset),
        // so a pane showing submission content counts as annotatable.
        if (this._paneFile[pane] === 'content') {
            return this.reactive.state.activity?.type === 'assign'
                && !!this.reactive.state.submission?.onlinetexthtml;
        }
        const files = this.reactive.state.submission?.files || [];
        const file = files.find((f) => parseInt(f.fileid, 10) === this._paneFile[pane]);
        return this._isAnnotatable(file);
    }

    /**
     * Wire the divider: drag to trade height between the panes, double-click to
     * reset to an even split. Bound once.
     */
    _initDivider() {
        const divider = this.getElement(this.selectors.SPLIT_DIVIDER);
        const split = this.getElement(this.selectors.SPLIT_VIEW);
        const top = this._paneEl('a');
        if (!divider || !split || !top || divider.dataset.bound === '1') {
            return;
        }
        divider.dataset.bound = '1';

        const MIN = 60;
        let dragging = false;

        const onMove = (e) => {
            if (!dragging) {
                return;
            }
            const rect = split.getBoundingClientRect();
            const y = (e.touches ? e.touches[0].clientY : e.clientY) - rect.top;
            // Leave room for the other pane and the divider itself.
            const max = rect.height - MIN - divider.offsetHeight;
            const height = Math.max(MIN, Math.min(y, max));
            top.style.flex = '0 0 ' + height + 'px';
        };
        const stop = () => {
            if (!dragging) {
                return;
            }
            dragging = false;
            divider.classList.remove('is-dragging');
            split.classList.remove('local-unifiedgrader-split-dragging');
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('touchmove', onMove);
        };
        const start = (e) => {
            dragging = true;
            divider.classList.add('is-dragging');
            // Suppress iframe/canvas pointer capture, or dragging over a video or
            // PDF page loses the mouse and the divider sticks mid-drag.
            split.classList.add('local-unifiedgrader-split-dragging');
            document.addEventListener('mousemove', onMove);
            document.addEventListener('touchmove', onMove, {passive: true});
            e.preventDefault();
        };

        divider.addEventListener('mousedown', start);
        divider.addEventListener('touchstart', start, {passive: false});
        document.addEventListener('mouseup', stop);
        document.addEventListener('touchend', stop);
        divider.addEventListener('dblclick', () => {
            top.style.flex = '0 0 45%';
        });
        // Keyboard: the divider is focusable, so let arrows nudge it too.
        divider.addEventListener('keydown', (e) => {
            if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') {
                return;
            }
            e.preventDefault();
            const step = e.key === 'ArrowUp' ? -24 : 24;
            const height = Math.max(MIN, top.getBoundingClientRect().height + step);
            top.style.flex = '0 0 ' + height + 'px';
        });

        // Clicking a pane moves the marking focus to it.
        ['a', 'b'].forEach((p) => {
            const el = this._paneEl(p);
            el?.addEventListener('focusin', () => this._setActivePane(p));
            el?.addEventListener('mousedown', () => this._setActivePane(p));
        });
    }

    /**
     * Show submission content (e.g. forum posts) in the iframe preview.
     */
    _showSubmissionContent() {
        const pdfWrapper = this.getElement(this.selectors.PDF_VIEWER_WRAPPER);
        const docPreview = this.getElement(this.selectors.DOCUMENT_PREVIEW);
        const textView = this.getElement(this.selectors.TEXT_ANNOT_VIEW);
        // Submission content is never annotated with the pixel toolbar.
        this._setPixelToolbar(false);
        pdfWrapper.classList.add('d-none');
        docPreview.classList.add('d-none');
        if (textView) {
            textView.classList.add('d-none');
        }

        // Remove the portfolio pop-out button if it was added previously.
        this._removePortfolioPopout();

        // A forum in paged or thread mode renders its posts inline with the
        // surrounding discussion rather than through the flat-list iframe.
        const forumMode = this.reactive.state.forumcontext?.mode || 'flat';
        if (this.reactive.state.activity?.type === 'forum' && forumMode !== 'flat') {
            this._renderForumInline(this.reactive.state, forumMode);
            return;
        }
        const contextView = this.getElement(this.selectors.FORUM_CONTEXT_VIEW);
        if (contextView) {
            contextView.classList.add('d-none');
        }

        // Assignment online text renders inline as an annotatable paper sheet
        // (seg_comments then adds margin comments + marks). Other content types
        // (forum posts, quiz attempts) keep the iframe so their plugin JS/CSS load.
        const isAssign = this.reactive.state.activity?.type === 'assign';
        const html = this.reactive.state.submission?.onlinetexthtml || '';
        if (isAssign && html && textView) {
            this._renderInlineText(textView, html);
            return;
        }

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
     * Wire the forum display-mode buttons and pager.
     *
     * Every control dispatches into shared state rather than calling the
     * marking panel: one source of truth, two readers.
     */
    _setupForumViewControls() {
        const toolbar = this.getElement(this.selectors.FORUM_VIEW_TOOLBAR);
        if (!toolbar) {
            return;
        }

        toolbar.querySelectorAll('[data-action="forum-view-mode"]').forEach((btn) => {
            btn.addEventListener('click', () => {
                this.reactive.dispatch(
                    'setForumViewMode',
                    this.reactive.state.activity?.cmid,
                    btn.dataset.mode,
                );
            });
        });

        const prev = toolbar.querySelector('[data-action="forum-post-prev"]');
        if (prev) {
            prev.addEventListener('click', () => this._stepPost(-1));
        }
        toolbar.querySelectorAll('[data-action="forum-post-next"]').forEach((btn) => {
            btn.addEventListener('click', () => this._stepPost(1));
        });
    }

    /**
     * Move focus to the student's previous or next post.
     *
     * @param {number} delta -1 or 1.
     */
    _stepPost(delta) {
        const ids = this.reactive.state.forumcontext?.targetpostids || [];
        if (ids.length === 0) {
            return;
        }
        const current = this.reactive.state.forumcontext?.currentpostid || 0;
        const index = ids.indexOf(current);
        // An unknown current post starts the walk at the beginning rather than
        // dead-ending, which is what index -1 would otherwise do.
        const next = index === -1
            ? 0
            : Math.min(Math.max(index + delta, 0), ids.length - 1);
        this.reactive.dispatch('setCurrentForumPost', ids[next]);
    }

    /**
     * Render whichever forum display mode is active.
     *
     * @param {object} args Watcher args.
     * @param {object} args.state Current state.
     */
    _renderForumContext({state}) {
        if (!this._updateForumToolbar(state)) {
            return;
        }

        // _showSubmissionContent is the single entry point for "show the posts";
        // it routes to the iframe or to the inline renderer depending on mode,
        // so rendering here as well would double the work.
        this._showSubmissionContent();
    }

    /**
     * Sync the toolbar to the current mode.
     *
     * Kept separate from rendering so _renderSubmission can refresh the controls
     * without triggering a second content render on top of the one it already does.
     *
     * @param {object} state Current state.
     * @return {boolean} Whether the posts view is on screen.
     */
    _updateForumToolbar(state) {
        const toolbar = this.getElement(this.selectors.FORUM_VIEW_TOOLBAR);
        const view = this.getElement(this.selectors.FORUM_CONTEXT_VIEW);
        if (!toolbar || !view) {
            return false;
        }

        const isForum = state.activity?.type === 'forum';
        // The mode switch belongs to the posts view; it has nothing to say
        // while a file preview or the split view is on screen.
        const showing = isForum && this._currentFileId === null && !this._multiview;
        toolbar.classList.toggle('d-none', !showing);
        if (!showing) {
            view.classList.add('d-none');
            return false;
        }

        const mode = state.forumcontext?.mode || 'flat';
        toolbar.querySelectorAll('[data-action="forum-view-mode"]').forEach((btn) => {
            const active = btn.dataset.mode === mode;
            btn.classList.toggle('active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        const pager = this.getElement(this.selectors.FORUM_PAGER);
        const jump = this.getElement(this.selectors.FORUM_JUMP_NEXT);
        if (pager) {
            pager.classList.toggle('d-none', mode !== 'paged');
        }
        if (jump) {
            jump.classList.toggle('d-none', mode !== 'thread');
        }

        return true;
    }

    /**
     * Render the paged or thread view inline, in place of the posts iframe.
     *
     * @param {object} state Current state.
     * @param {string} mode Either paged or thread.
     */
    _renderForumInline(state, mode) {
        const view = this.getElement(this.selectors.FORUM_CONTEXT_VIEW);
        if (!view) {
            return;
        }

        const docPreview = this.getElement(this.selectors.DOCUMENT_PREVIEW);
        const pdfWrapper = this.getElement(this.selectors.PDF_VIEWER_WRAPPER);
        const textView = this.getElement(this.selectors.TEXT_ANNOT_VIEW);
        if (docPreview) {
            docPreview.classList.add('d-none');
        }
        if (pdfWrapper) {
            pdfWrapper.classList.add('d-none');
        }
        if (textView) {
            textView.classList.add('d-none');
        }
        view.classList.remove('d-none');

        if (mode === 'paged') {
            this._renderPagedPost(state, view);
        } else {
            this._renderThread(state, view);
        }
    }

    /**
     * Paged mode: one of the student's posts with the context needed to judge it.
     *
     * Prompt, what it replied to, what else was said in reply to the same post,
     * the post itself, and what it drew in return. Siblings matter more than
     * they look — they are how you tell whether the student added anything or
     * restated a classmate.
     *
     * @param {object} state Current state.
     * @param {HTMLElement} view Container.
     */
    _renderPagedPost(state, view) {
        view.innerHTML = '';

        const ids = state.forumcontext?.targetpostids || [];
        const discussions = state.forumcontext?.discussions || [];
        if (ids.length === 0) {
            this._renderNoContext(view);
            return;
        }

        const currentId = state.forumcontext?.currentpostid || ids[0];
        const located = this._locatePost(discussions, currentId);
        if (!located) {
            this._renderNoContext(view);
            return;
        }
        const {discussion, post, byId} = located;

        this._updatePagerLabel(ids.indexOf(currentId) + 1, ids.length);

        const heading = document.createElement('h5');
        heading.className = 'border-bottom pb-2 mb-3';
        heading.textContent = discussion.name;
        view.appendChild(heading);

        const prompt = discussion.posts.find((p) => p.isprompt);
        // Only show the prompt separately when it is not already the parent —
        // otherwise a top-level reply would render it twice.
        if (prompt && prompt.id !== post.id && prompt.id !== post.parent) {
            view.appendChild(this._buildContextGroup('forumview_prompt', [prompt], true));
        }

        const parent = post.parent ? byId[post.parent] : null;
        if (parent) {
            const key = parent.isprompt ? 'forumview_prompt' : 'forumview_replying_to';
            view.appendChild(this._buildContextGroup(key, [parent], parent.isprompt));
        }

        const siblings = discussion.posts.filter(
            (p) => p.parent === post.parent && p.id !== post.id,
        );
        if (siblings.length > 0) {
            view.appendChild(this._buildContextGroup('forumview_siblings', siblings, true));
        }

        view.appendChild(this._buildPostCard(post, state, true));

        const replies = discussion.posts.filter((p) => p.parent === post.id);
        if (replies.length > 0) {
            view.appendChild(this._buildContextGroup('forumview_replies', replies, false));
        }
    }

    /**
     * Thread mode: the whole discussion, the student's posts highlighted.
     *
     * Long runs of unrelated posts collapse so a 40-reply thread stays readable
     * when only three posts are being graded.
     *
     * @param {object} state Current state.
     * @param {HTMLElement} view Container.
     */
    _renderThread(state, view) {
        view.innerHTML = '';

        const discussions = state.forumcontext?.discussions || [];
        if (discussions.length === 0) {
            this._renderNoContext(view);
            return;
        }

        const COLLAPSE_RUN = 3;

        discussions.forEach((discussion) => {
            const block = document.createElement('div');
            block.className = 'mb-4';

            const heading = document.createElement('h5');
            heading.className = 'border-bottom pb-2 mb-3';
            heading.textContent = discussion.name;
            block.appendChild(heading);

            // Walk the thread, buffering consecutive posts that are neither the
            // student's nor the prompt. A short run renders as-is; a long one
            // folds behind a disclosure.
            let run = [];
            const flushRun = () => {
                if (run.length === 0) {
                    return;
                }
                if (run.length < COLLAPSE_RUN) {
                    run.forEach((p) => block.appendChild(this._buildPostCard(p, state, false)));
                } else {
                    block.appendChild(this._buildCollapsedRun(run, state));
                }
                run = [];
            };

            discussion.posts.forEach((post) => {
                if (post.isstudent || post.isprompt) {
                    flushRun();
                    block.appendChild(this._buildPostCard(post, state, post.isstudent));
                } else {
                    run.push(post);
                }
            });
            flushRun();

            view.appendChild(block);
        });

        this._scrollToCurrentPost(state, view);
    }

    /**
     * Build a labelled group of context posts.
     *
     * @param {string} stringKey Lang string for the group heading.
     * @param {Array} posts Posts to render.
     * @param {boolean} muted Whether to dim them relative to the graded post.
     * @return {HTMLElement}
     */
    _buildContextGroup(stringKey, posts, muted) {
        const wrapper = document.createElement('div');
        wrapper.className = 'mb-3';

        const label = document.createElement('div');
        label.className = 'small text-uppercase text-muted fw-semibold mb-1';
        label.textContent = '';
        getString(stringKey, 'local_unifiedgrader').then((str) => {
            label.textContent = str;
            return str;
        }).catch(() => {});
        wrapper.appendChild(label);

        posts.forEach((post) => {
            const card = this._buildPostCard(post, null, false);
            if (muted) {
                card.classList.add('opacity-75');
            }
            wrapper.appendChild(card);
        });

        return wrapper;
    }

    /**
     * Fold a run of unrelated posts behind a disclosure.
     *
     * @param {Array} posts The run.
     * @param {object} state Current state.
     * @return {HTMLElement}
     */
    _buildCollapsedRun(posts, state) {
        const wrapper = document.createElement('div');
        wrapper.className = 'mb-2';

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'btn btn-sm btn-link text-decoration-none p-0 small';
        const body = document.createElement('div');
        body.className = 'd-none';

        posts.forEach((post) => {
            const card = this._buildPostCard(post, state, false);
            card.classList.add('opacity-75');
            body.appendChild(card);
        });

        const setLabel = (expanded) => {
            const key = expanded ? 'forumview_collapse' : 'forumview_collapsed';
            getString(key, 'local_unifiedgrader', posts.length).then((str) => {
                toggle.innerHTML = '';
                const icon = document.createElement('i');
                icon.className = expanded ? 'fa fa-chevron-up me-1' : 'fa fa-chevron-down me-1';
                icon.setAttribute('aria-hidden', 'true');
                toggle.appendChild(icon);
                toggle.appendChild(document.createTextNode(str));
                return str;
            }).catch(() => {
                toggle.textContent = `… ${posts.length} …`;
            });
        };
        setLabel(false);

        toggle.addEventListener('click', () => {
            const expanded = body.classList.toggle('d-none') === false;
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            setLabel(expanded);
        });

        wrapper.appendChild(toggle);
        wrapper.appendChild(body);
        return wrapper;
    }

    /**
     * Render one post card.
     *
     * @param {object} post Post entry from the context payload.
     * @param {?object} state Current state, or null for pure context posts.
     * @param {boolean} highlight Whether this is a post being graded.
     * @return {HTMLElement}
     */
    _buildPostCard(post, state, highlight) {
        const card = document.createElement('div');
        card.className = 'card mb-2 local-unifiedgrader-context-post';
        card.dataset.postid = String(post.id);
        if (highlight) {
            card.classList.add('border-primary', 'local-unifiedgrader-context-post-target');
        }
        // Indent by reply depth, capped so a deep thread does not walk off the
        // right edge of the panel. Carried as an attribute, not an inline
        // style, so the narrow-panel media query can reset it.
        card.dataset.depth = String(Math.min(post.depth, 4));

        const header = document.createElement('div');
        header.className = 'card-header py-1 small text-muted d-flex '
            + 'justify-content-between align-items-center gap-2';

        const who = document.createElement('span');
        who.className = 'd-flex align-items-center gap-2 text-truncate';
        if (post.authorpicture) {
            const img = document.createElement('img');
            img.src = post.authorpicture;
            img.alt = '';
            img.className = 'rounded-circle';
            img.width = 20;
            img.height = 20;
            who.appendChild(img);
        }
        const name = document.createElement('span');
        name.className = 'text-truncate';
        const subject = document.createElement('strong');
        subject.textContent = post.subject;
        name.appendChild(subject);
        name.appendChild(document.createTextNode(` — ${post.authorname}, ${post.createddisplay}`));
        who.appendChild(name);
        header.appendChild(who);

        const badges = document.createElement('span');
        badges.className = 'd-flex align-items-center gap-2 text-nowrap';
        if (highlight) {
            const mine = document.createElement('span');
            mine.className = 'badge bg-primary';
            getString('forumview_this_student', 'local_unifiedgrader').then((str) => {
                mine.textContent = str;
                return str;
            }).catch(() => {});
            badges.appendChild(mine);
        }
        if (post.wordcount > 0) {
            const words = document.createElement('span');
            getString('forum_wordcount', 'local_unifiedgrader', post.wordcount).then((str) => {
                words.textContent = str;
                return str;
            }).catch(() => {});
            badges.appendChild(words);
        }
        if (post.rating && post.rating.count > 0) {
            const badge = document.createElement('span');
            badge.className = 'badge bg-primary-subtle text-primary-emphasis fw-normal';
            badge.textContent = post.rating.aggregatelabel;
            badges.appendChild(badge);
        }
        header.appendChild(badges);

        const body = document.createElement('div');
        body.className = 'card-body py-2';
        // Server-formatted through format_text(); assigning it is the same trust
        // boundary the flat view already relies on.
        body.innerHTML = post.message;

        card.appendChild(header);
        card.appendChild(body);

        // Rate the post where it is read. The marking panel's list is the
        // overview and the running total; this is the control you reach for
        // with the post in front of you.
        const rater = this._buildInlineRatingControl(post, state);
        if (rater) {
            card.appendChild(rater);
        }

        // Clicking any post the student wrote focuses it, so the thread view can
        // drive the marking panel's rating rows too.
        if (post.isstudent && state) {
            card.style.cursor = 'pointer';
            card.addEventListener('click', (e) => {
                if (e.target.closest('a, button, input, select')) {
                    return;
                }
                this.reactive.dispatch('setCurrentForumPost', post.id);
            });
        }

        return card;
    }

    /**
     * Build the inline rating footer for one of the graded student's posts.
     *
     * Returns null for anything that is not rateable work under assessment:
     * context posts by classmates, forums that are not rating-graded, and posts
     * core will not accept a rating for (your own, or one outside the forum's
     * rating window) — those show the reason instead of a dead control.
     *
     * Writes go through the same mutation the marking panel uses, so the two
     * stay in step without either knowing about the other.
     *
     * @param {object} post Post entry from the context payload.
     * @param {?object} state Current state, or null for pure context posts.
     * @return {?HTMLElement}
     */
    _buildInlineRatingControl(post, state) {
        if (!state || !post.isstudent || !post.rating) {
            return null;
        }
        if (state.activity?.gradingmode !== 'rating') {
            return null;
        }

        const footer = document.createElement('div');
        footer.className = 'card-footer py-2 d-flex align-items-center gap-2 flex-wrap';
        footer.dataset.region = 'post-rating-control';
        footer.dataset.postid = String(post.id);

        const label = document.createElement('label');
        label.className = 'small text-muted mb-0';
        label.setAttribute('for', `ug-rate-${post.id}`);
        getString('rating_your_rating', 'local_unifiedgrader').then((str) => {
            label.textContent = str;
            return str;
        }).catch(() => {});
        footer.appendChild(label);

        const select = document.createElement('select');
        select.id = `ug-rate-${post.id}`;
        select.className = 'form-select form-select-sm w-auto';
        select.dataset.action = 'inline-post-rating';
        select.dataset.postid = String(post.id);

        // -999 is RATING_UNSET_RATING: core reads it as "remove my rating",
        // which is not the same as rating zero.
        const unset = document.createElement('option');
        unset.value = '-999';
        unset.textContent = '...';
        getString('rating_choose', 'local_unifiedgrader').then((str) => {
            unset.textContent = str;
            return str;
        }).catch(() => {});
        select.appendChild(unset);

        (state.activity?.scaleitems || []).forEach((item) => {
            const option = document.createElement('option');
            option.value = String(item.value);
            option.textContent = item.label;
            select.appendChild(option);
        });
        const own = post.rating.own;
        select.value = own === null || own === undefined ? '-999' : String(own);

        if (!post.rating.canrate) {
            select.disabled = true;
            select.title = post.rating.noratereason || '';
        } else {
            select.addEventListener('change', (e) => {
                this.reactive.dispatch(
                    'savePostRating',
                    state.activity.cmid,
                    post.id,
                    parseInt(e.target.value, 10),
                );
            });
        }
        footer.appendChild(select);

        // The aggregate is a different number from the select: it blends every
        // marker who rated this post, while the select holds only yours.
        const aggregate = document.createElement('span');
        aggregate.className = 'small text-muted ms-auto';
        aggregate.dataset.region = 'inline-rating-aggregate';
        if (post.rating.count > 0) {
            getString(
                post.rating.count === 1 ? 'rating_aggregate_one' : 'rating_aggregate_of',
                'local_unifiedgrader',
                {value: post.rating.aggregatelabel, count: post.rating.count},
            ).then((str) => {
                aggregate.textContent = str;
                return str;
            }).catch(() => {
                aggregate.textContent = post.rating.aggregatelabel;
            });
        } else {
            getString('rating_unrated', 'local_unifiedgrader').then((str) => {
                aggregate.textContent = str;
                return str;
            }).catch(() => {});
        }
        footer.appendChild(aggregate);

        if (!post.rating.canrate && post.rating.noratereason) {
            const reason = document.createElement('span');
            reason.className = 'small text-muted w-100';
            reason.textContent = post.rating.noratereason;
            footer.appendChild(reason);
        }

        return footer;
    }

    /**
     * Find a post and its discussion within the payload.
     *
     * @param {Array} discussions The context payload.
     * @param {number} postid
     * @return {?object} {discussion, post, byId} or null.
     */
    _locatePost(discussions, postid) {
        for (const discussion of discussions) {
            const post = discussion.posts.find((p) => p.id === postid);
            if (post) {
                const byId = {};
                discussion.posts.forEach((p) => {
                    byId[p.id] = p;
                });
                return {discussion, post, byId};
            }
        }
        return null;
    }

    /**
     * Update the "Post n of m" label.
     *
     * @param {number} index 1-based.
     * @param {number} total
     */
    _updatePagerLabel(index, total) {
        const label = this.getElement(this.selectors.FORUM_PAGER_LABEL);
        if (!label) {
            return;
        }
        getString('forumview_post_of', 'local_unifiedgrader', {index, total}).then((str) => {
            label.textContent = str;
            return str;
        }).catch(() => {
            label.textContent = `${index} / ${total}`;
        });
    }

    /**
     * Bring the focused post into view in thread mode.
     *
     * @param {object} state Current state.
     * @param {HTMLElement} view Container.
     */
    _scrollToCurrentPost(state, view) {
        const current = state.forumcontext?.currentpostid || 0;
        if (!current) {
            return;
        }
        const target = view.querySelector(`[data-postid="${current}"]`);
        if (target) {
            target.scrollIntoView({behavior: 'smooth', block: 'center'});
        }
    }

    /**
     * Render the empty state.
     *
     * @param {HTMLElement} view Container.
     */
    _renderNoContext(view) {
        const message = document.createElement('p');
        message.className = 'text-muted';
        getString('forumview_no_context', 'local_unifiedgrader').then((str) => {
            message.textContent = str;
            return str;
        }).catch(() => {});
        view.innerHTML = '';
        view.appendChild(message);
    }

    /**
     * Render the submission's online text inline as an annotatable paper sheet.
     * The structure mirrors the translated view so seg_comments decorates it; the
     * offset anchor-mode tells it to use arbitrary-selection (not segment) anchoring.
     *
     * @param {HTMLElement} view The text-annot-view region.
     * @param {string} html The formatted online-text HTML (server-cleaned).
     */
    _renderInlineText(view, html) {
        // The inline text uses the seg_comments tool strip, not the pixel toolbar.
        this._setPixelToolbar(false);
        view.innerHTML = '';
        const slot = document.createElement('div');
        slot.className = 'local-unifiedgrader-translation-slot';
        slot.dataset.sourceType = 'onlinetext';
        slot.dataset.fileid = '0';
        slot.dataset.anchorMode = 'offset';
        const page = document.createElement('div');
        page.className = 'local-unifiedgrader-translation-page';
        const body = document.createElement('div');
        body.className = 'local-unifiedgrader-translation-body';
        // html is the adapter's format_text() output (already sanitised) — safe.
        body.innerHTML = html;
        page.appendChild(body);
        slot.appendChild(page);
        view.appendChild(slot);
        view.classList.remove('d-none');
    }

    /**
     * Show or hide the legacy pixel annotation toolbar. It drives the PDF viewer
     * only; on the inline text / iframe / translated views the seg_comments tool
     * strip is used instead, so the old toolbar is hidden there (it would
     * otherwise linger from a previous PDF and grab focus).
     *
     * @param {boolean} show Whether to show it.
     */
    _setPixelToolbar(show) {
        const toolbar = document.querySelector('[data-region="annotation-toolbar"]');
        if (toolbar) {
            toolbar.classList.toggle('d-none', !show);
        }
    }

    /**
     * Render a Byblos portfolio in the iframe preview, with a pop-out button
     * that lets the teacher open the portfolio in a new tab.
     *
     * @param {string} url The portfolio URL (with embedded=1 for chrome-free render).
     */
    _showPortfolio(url) {
        const pdfWrapper = this.getElement(this.selectors.PDF_VIEWER_WRAPPER);
        const docPreview = this.getElement(this.selectors.DOCUMENT_PREVIEW);
        pdfWrapper.classList.add('d-none');
        this.getElement(this.selectors.TEXT_ANNOT_VIEW)?.classList.add('d-none');
        this._setPixelToolbar(false);

        const iframe = this.getElement(this.selectors.PREVIEW_IFRAME);
        iframe.src = url;
        docPreview.classList.remove('d-none');

        // Add (or refresh) the pop-out button overlaid on the iframe area.
        this._addPortfolioPopout(url);
    }

    /**
     * Add a pop-out button to open the portfolio in a new tab.
     * The button is overlaid in the top-right of the preview area.
     *
     * @param {string} url The portfolio URL.
     */
    _addPortfolioPopout(url) {
        this._removePortfolioPopout();

        const docPreview = this.getElement(this.selectors.DOCUMENT_PREVIEW);
        if (!docPreview) {
            return;
        }

        const popoutUrl = url.replace(/([?&])embedded=1(&|$)/, (m, p1, p2) => (p2 ? p1 : '')) || url;

        const btn = document.createElement('a');
        btn.dataset.region = 'portfolio-popout';
        btn.href = popoutUrl;
        btn.target = '_blank';
        btn.rel = 'noopener noreferrer';
        btn.className = 'btn btn-sm btn-light border shadow-sm position-absolute';
        btn.style.top = '8px';
        btn.style.right = '16px';
        btn.style.zIndex = '10';

        const icon = document.createElement('i');
        icon.className = 'fa fa-external-link-alt';
        icon.setAttribute('aria-hidden', 'true');
        btn.appendChild(icon);

        getString('portfolio_popout', 'local_unifiedgrader').then((s) => {
            btn.setAttribute('title', s);
            btn.setAttribute('aria-label', s);
            return s;
        }).catch(() => {
            btn.setAttribute('title', 'Open portfolio in new tab');
        });

        // Ensure the preview wrapper is positioned so absolute children anchor correctly.
        if (getComputedStyle(docPreview).position === 'static') {
            docPreview.style.position = 'relative';
        }
        docPreview.appendChild(btn);
    }

    /**
     * Remove the portfolio pop-out button if present.
     */
    _removePortfolioPopout() {
        const docPreview = this.getElement(this.selectors.DOCUMENT_PREVIEW);
        if (!docPreview) {
            return;
        }
        const existing = docPreview.querySelector('[data-region="portfolio-popout"]');
        if (existing) {
            existing.remove();
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
