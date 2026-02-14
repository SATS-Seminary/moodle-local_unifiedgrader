# Phase 4: PDF Annotation Tools — Implementation Plan

## Overview

Replace the iframe-based PDF preview with a custom **PDF.js renderer** + **Fabric.js annotation canvas overlay**. Four annotation tools: click-to-comment, highlight, freehand drawing, and text stamps. Annotations visible to both teachers (editable) and students (read-only).

---

## Architecture

```
┌─────────────────────────────────────────────────────┐
│  preview_panel (existing component, modified)        │
│  ┌───────────────────────────────────────────────┐  │
│  │  annotation_toolbar (new component)            │  │
│  │  [Comment] [Highlight] [Pen] [Stamp] [Color▼] │  │
│  │  [Undo] [Redo] [Clear] [Save]                  │  │
│  └───────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────┐  │
│  │  pdf_viewer (new component)                    │  │
│  │  ┌─────────────────────────────────────────┐  │  │
│  │  │  Page container (scrollable)             │  │  │
│  │  │  ┌───────────────────────────────────┐  │  │  │
│  │  │  │  PDF.js <canvas> (background)     │  │  │  │
│  │  │  │  Fabric.js <canvas> (overlay)     │  │  │  │
│  │  │  └───────────────────────────────────┘  │  │  │
│  │  │  Page nav: [◀ Page 1/5 ▶] [Zoom -/+]  │  │  │
│  │  └─────────────────────────────────────────┘  │  │
│  └───────────────────────────────────────────────┘  │
│  (iframe kept as fallback for non-PDF files)         │
└─────────────────────────────────────────────────────┘
```

### Coordinate System
- All annotation coordinates stored as **normalized values (0.0–1.0)** relative to page dimensions
- Converted to/from pixel coordinates on render/save using current viewport size
- This makes annotations zoom-independent and resolution-independent

### Storage Model
- **One DB row per annotation** (not per page)
- `annotationdata` JSON stores the Fabric.js object serialization + metadata
- Example JSON: `{"type":"comment","fabricObject":{...},"text":"Fix this","color":"#ff0000"}`
- Existing `local_unifiedgrader_annot` table used as-is

---

## Sub-Phase Breakdown

### 4A: Third-Party Libraries & PDF Viewer (Foundation)

**Goal**: Render PDFs with PDF.js instead of an iframe. No annotations yet.

**New files:**
- `thirdparty/pdfjs/pdf.min.mjs` — PDF.js library (from pdfjs-dist npm package)
- `thirdparty/pdfjs/pdf.worker.min.mjs` — PDF.js web worker
- `thirdparty/fabric/fabric.min.js` — Fabric.js library (loaded in 4B)
- `thirdparty/thirdpartylibs.xml` — Moodle third-party library manifest
- `amd/src/lib/pdfjs_loader.js` — AMD wrapper to load PDF.js + configure worker
- `amd/src/components/pdf_viewer.js` — New reactive component: renders PDF pages to canvas, handles page navigation, zoom, scroll
- `templates/pdf_viewer.mustache` — Template: canvas container, page nav controls, zoom controls

**Modified files:**
- `amd/src/components/preview_panel.js` — Detect PDF files → use pdf_viewer component instead of iframe. Keep iframe for images/text.
- `templates/preview_panel.mustache` — Add pdf-viewer region alongside existing iframe
- `amd/src/grader.js` — Register pdf_viewer component

**Key decisions:**
- Single-page view with page navigation (not continuous scroll) — simpler for annotation layer
- Zoom: fit-to-width (default), zoom in/out buttons, pinch-to-zoom on touch
- PDF.js loaded as ES module via dynamic import, worker configured at load time

---

### 4B: Fabric.js Annotation Layer & Tools

**Goal**: Overlay a Fabric.js canvas on the PDF page. Implement all four annotation tools.

**New files:**
- `amd/src/lib/fabric_loader.js` — AMD wrapper to load Fabric.js
- `amd/src/components/annotation_layer.js` — Manages Fabric.js canvas lifecycle on top of PDF canvas. Handles tool modes, coordinate normalization, undo/redo stack.
- `amd/src/components/annotation_toolbar.js` — Toolbar reactive component with tool selection, color picker, undo/redo/clear/save buttons
- `amd/src/components/comment_popup.js` — Modal/popup for entering comment text when placing a comment marker
- `amd/src/annotation/types.js` — Annotation type definitions and factory (createComment, createHighlight, createPen, createStamp)
- `amd/src/annotation/serializer.js` — Serialize/deserialize annotations (Fabric.js ↔ JSON ↔ normalized coords)
- `templates/annotation_toolbar.mustache` — Toolbar template
- `templates/comment_popup.mustache` — Comment text input popup template

**Modified files:**
- `amd/src/components/pdf_viewer.js` — Integrate annotation_layer, pass canvas reference
- `amd/src/grader.js` — Register annotation_toolbar, annotation_layer
- `templates/preview_panel.mustache` — Add toolbar region

**Annotation tool behaviors:**

| Tool | Interaction | Fabric.js Object | Visual |
|------|------------|-------------------|--------|
| Comment | Click to place marker → popup opens for text | Custom Group (icon + hidden text) | Pin/marker icon; hover shows text preview |
| Highlight | Click-drag rectangle | Rect with 30% opacity fill | Semi-transparent colored rectangle |
| Pen | Freehand draw | Path (free drawing mode) | Colored stroke, configurable width |
| Stamp | Click to place, select from palette | Image or Group (icon + text) | Checkmark ✓, X ✗, Question ?, or custom text |

**Color palette**: Red, Yellow, Green, Blue, Black (matching Moodle's editpdf convention)

**Undo/Redo**: In-memory stack per page. Stores annotation add/remove/modify operations.

---

### 4C: Backend Services & State Integration

**Goal**: Save and load annotations via web services. Integrate into reactive state.

**New PHP files:**
- `classes/annotation_manager.php` — CRUD class for annotations (like notes_manager.php pattern)
  - `get_annotations(int $cmid, int $userid, int $fileid): array`
  - `save_annotation(int $cmid, int $userid, int $fileid, int $pagenum, string $data): int`
  - `update_annotation(int $id, string $data): bool`
  - `delete_annotation(int $id): bool`
  - `delete_all_annotations(int $cmid, int $userid, int $fileid): bool`
- `classes/external/get_annotations.php` — Web service: load annotations for a file
- `classes/external/save_annotation.php` — Web service: save/update single annotation
- `classes/external/delete_annotation.php` — Web service: delete annotation

**Modified PHP files:**
- `db/services.php` — Register 3 new web services (ajax: true)
- `lang/en/local_unifiedgrader.php` — New language strings for annotation tools
- `classes/adapter/assign_adapter.php` — Change `supports_feature('annotations')` to `true`
- `version.php` — Bump version number

**Modified JS files:**
- `amd/src/mutations.js` — Add mutations:
  - `loadAnnotations(cmid, userid, fileid)` — Fetch from server, update state
  - `saveAnnotation(cmid, userid, fileid, pagenum, annotationdata)` — Save to server
  - `deleteAnnotation(cmid, annotationid)` — Delete from server
- `amd/src/grader.js` — Add `annotations` array to initial state structure

**State structure addition:**
```javascript
annotations: [
    {
        id: 1,
        cmid: 42,
        userid: 5,        // student
        authorid: 3,       // teacher
        fileid: 100,
        pagenum: 0,
        annotationdata: '{"type":"comment",...}',
        timecreated: 1700000000,
        timemodified: 1700000000,
    },
    // ...
]
```

**Auto-save strategy**: Debounced save (2-second delay after last change). Visual indicator shows save status (saved/saving/unsaved). Also save on page change and student navigation.

---

### 4D: Student View (Read-Only Annotations)

**Goal**: Students see teacher annotations on their submissions in read-only mode.

**New files:**
- `view_annotations.php` — Student-facing entry point (or extend grade.php with mode param)

**Modified files:**
- `classes/adapter/assign_adapter.php` — Add method to check if current user is student
- `amd/src/components/annotation_layer.js` — Add read-only mode (disable Fabric.js interaction, hide toolbar)
- `amd/src/components/preview_panel.js` — Detect student mode, load annotations in read-only
- `db/access.php` — Add capability: `local/unifiedgrader:viewannotations` (for students)
- `lib.php` — Add navigation link for students to view annotated submissions
- `lang/en/local_unifiedgrader.php` — Student-facing strings

**Access control:**
- Teachers: `local/unifiedgrader:grade` — can create/edit/delete annotations
- Students: `local/unifiedgrader:viewannotations` — can view annotations on own submissions only
- Students see annotations only after grade is released (respect assignment workflow)

---

### 4E: Polish & Integration

**Goal**: Production-ready quality.

**Modified files:**
- `classes/privacy/provider.php` — Add annotations to metadata declarations (currently only handles deletes)
- `amd/src/components/preview_panel.js` — Show annotation count badges on file selector buttons
- `styles.css` — Annotation toolbar styling, comment popup styling, color picker
- `templates/preview_panel.mustache` — Badge indicators
- `db/upgrade.php` — Add upgrade step if schema changes needed (add `type` column to annotations table for easier querying — optional)

**Performance considerations:**
- Lazy-load PDF.js and Fabric.js only when a PDF file is selected (not on page load)
- Render only the current page's annotations (not all pages)
- Cache loaded PDF documents in memory during the session
- Debounced auto-save to avoid excessive AJAX calls

---

## File Inventory

### New Files (18)

| File | Purpose |
|------|---------|
| `thirdparty/pdfjs/pdf.min.mjs` | PDF.js library |
| `thirdparty/pdfjs/pdf.worker.min.mjs` | PDF.js web worker |
| `thirdparty/fabric/fabric.min.js` | Fabric.js library |
| `thirdparty/thirdpartylibs.xml` | Third-party manifest |
| `amd/src/lib/pdfjs_loader.js` | PDF.js AMD wrapper |
| `amd/src/lib/fabric_loader.js` | Fabric.js AMD wrapper |
| `amd/src/components/pdf_viewer.js` | PDF page renderer component |
| `amd/src/components/annotation_layer.js` | Fabric.js canvas overlay |
| `amd/src/components/annotation_toolbar.js` | Tool selection toolbar |
| `amd/src/components/comment_popup.js` | Comment text input popup |
| `amd/src/annotation/types.js` | Annotation type factory |
| `amd/src/annotation/serializer.js` | Annotation serialization |
| `classes/annotation_manager.php` | PHP annotation CRUD |
| `classes/external/get_annotations.php` | Web service: get |
| `classes/external/save_annotation.php` | Web service: save |
| `classes/external/delete_annotation.php` | Web service: delete |
| `templates/pdf_viewer.mustache` | PDF viewer template |
| `templates/annotation_toolbar.mustache` | Toolbar template |

### Modified Files (14)

| File | Changes |
|------|---------|
| `amd/src/components/preview_panel.js` | PDF detection → pdf_viewer, annotation integration |
| `amd/src/grader.js` | Register new components, add annotations state |
| `amd/src/mutations.js` | Add 3 annotation mutations |
| `templates/preview_panel.mustache` | Add pdf-viewer + toolbar regions |
| `db/services.php` | Register 3 new web services |
| `db/access.php` | Add viewannotations capability |
| `classes/adapter/assign_adapter.php` | Enable annotations, add student check |
| `classes/privacy/provider.php` | Add annotations metadata |
| `lang/en/local_unifiedgrader.php` | ~30 new strings |
| `lib.php` | Student nav link |
| `styles.css` | Annotation UI styling |
| `version.php` | Version bump |

---

## Implementation Order

1. **4A** — PDF.js viewer (foundation, can be tested independently)
2. **4C backend only** — Web services + annotation_manager (can be tested with unit tests)
3. **4B** — Fabric.js annotation layer + tools (depends on 4A)
4. **4C frontend** — Wire up state + mutations (connects 4B to 4C backend)
5. **4D** — Student view (depends on everything above)
6. **4E** — Polish (final pass)

Estimated scope: ~32 files touched, ~3000-4000 lines of new code.

---

## Open Questions / Risks

1. **PDF.js module loading**: Need to verify that dynamic ES module imports work within Moodle's AMD/RequireJS environment. May need a shim approach.
2. **Fabric.js version**: v6.x uses ES modules natively. Need to ensure compatibility with Moodle's RequireJS loader. May need the UMD/CommonJS build.
3. **Mobile/touch support**: Fabric.js supports touch events natively, but annotation tools may need touch-specific adjustments.
4. **Large PDFs**: Pages rendered one at a time (single-page view) mitigates memory concerns. May want to add a page limit warning for very large documents.
5. **Non-PDF files**: Annotations on images could reuse the same Fabric.js layer without PDF.js. Scope for later.
