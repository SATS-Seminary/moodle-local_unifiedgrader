# Changelog

## v2.3.0 (2026-05-09)
### BigBlueButton activity adapter
- Recording playback rendered inline in the preview pane via iframe to BBB's playback wrapper, with a fullscreen button and "Open in new tab" link
- Recording switcher ("All sessions" + one pill per recording) — clicking a pill loads that recording and pivots the Activity Points card to that session's metrics
- Activity Points card with chats, talks, raise hand, poll votes, emojis, and duration — aggregated across all sessions or filtered to a single session
- BigBlueButton's 0-10 Activity Score surfaced as a tile (averaged across sessions in the aggregate view, exact value in the per-session view)
- "View full analytics" button opens BBB's Statistics dashboard in a new tab — single button when there is one recording, dropdown when there are several
- "Did not attend" badge for students with no JOIN or SUMMARY logs (still gradeable)
- Group mode supported via BBB's native group filtering
- New `enable_bigbluebuttonbn` admin setting (default off)

### Companion-plugin integrations
- `bbbext_advgrd` — rubric / marking-guide definitions on a BBB activity render in the marking pane and save through the extension's grader pipeline (per-user evidence snapshot, gradebook passthrough, analytic sub-scores). Grading instance is reused across saves rather than minted fresh each time.
- Engagement metric fallback for missing analytics callbacks: parses BBB's Statistics playback page server-side and caches per-user metrics in a new `local_unifiedgrader_bbbeng` table. Triggered by a "Pull engagement data from BBB recordings" button in the engagement-pending warning.
- New web service `local_unifiedgrader_refresh_bbb_engagement` (requires `local/unifiedgrader:grade`).
- Engagement-pending banner is actionable: site admins see the exact admin setting to enable plus a deep link to the BBB plugin settings; teachers without site access see a softer message.

### In-product documentation
- New `?` icon to the right of the hamburger in the grading toolbar opens a dedicated help page in a new tab, deep-linked to the section matching the active activity type
- Covers every adapter (assign, forum, quiz, BigBlueButton), companion-plugin integrations (Byblos portfolio, `bbbext_advgrd`, `quizaccess_duedate`, `gradepenalty_duedate`, plagiarism plugins, TinyMCE recorder), cross-cutting features, admin settings, architecture, and troubleshooting
- Six inline SVG diagrams — adapter pattern, BBB engagement data flow, marking-guide save lifecycle, companion-plugin landscape, annotation flatten pipeline, auto-save state machine. No CDN dependency.

### Auto-save race fixes (affect all activity types, not just BBB)
- `_renderGuide` and `_renderRubric` switch from destructive `innerHTML = ''` rebuilds to incremental DOM updates: snapshot values at save dispatch, then on the post-save state refresh keep the focused field — and any field edited since the save fired — instead of clobbering it
- Same protection extended to the top-level grade input, scale dropdown, and TinyMCE feedback editor (`setContent()` calls during a state refresh now refuse when the editor has focus or contains unsent edits)
- Re-mark grade dirty after a reconciled refresh so the next focusout flushes a follow-up save
- Treat `focusout` with null `relatedTarget` as ambiguous (defer to a microtask and re-check `document.activeElement`) — fixes marking-guide values resetting when opening the comment library in WebKit/Safari

## v2.1.8 (2026-04-23)
- Fix "Mark as graded" toggle reverting on reload for Grade:None assignments

## v2.1.7 (2026-04-18)
- Render Byblos portfolio submissions inline in the preview pane with pop-out button
- Remove dead code: legacy v1 comment library classes and 30 unused language strings

## v2.1.6 (2026-04-09)
- Replace penalty recalculation gate with post-save confirmation dialog
- Extensions save immediately; teacher is prompted to recalculate penalty if grades exist
- Fix extensions not recalculating penalties when granted after grading
- Fix quiz attempts incorrectly flagged as needing grading when zero-mark questions are present

## v2.1.5 (2026-04-07)
- Fix group/team submissions not displaying in the grading interface
- Fix quiz question ordering for shuffled quizzes (use attempt layout order)
- Close participant list panel when clicking outside or focusing TinyMCE editor
- Add labeled info box for grader information in quiz marking panel
- Fix auto-save race condition that could overwrite marking guide data

## v2.1.4 (2026-04-04)
- Fix online text submissions not displaying in preview panel
- Add "Render online text as PDF" setting for PDF annotation of text submissions
- Fix marking guide grade normalization when guide total differs from activity max grade
- Fix unicode escape sequences in Spanish, French, German, and Afrikaans language files
- Fix quiz division by zero when grading zero-mark questions
- Disable score input for zero-mark quiz questions in marking panel

## v2.1.3 (2026-03-31)
- Fix quiz question numbering skew when description/label items are present
- Fix comment library offline banner for non-manager teachers (permission check too strict)

## v2.1.2 (2026-03-25)
- Add capability checks to comment library external services (guest and sharecomments validation)
- Add Frankenstyle prefix to all global functions in override and extension pages
- Add GPL boilerplate headers to all source files (mustache, CSS, JS)
- Add thirdpartylibs.xml documenting PDF.js, Fabric.js, and pdf-lib
- Replace hard-coded language strings with get_string() API across JS components
- Replace innerHTML with DOM manipulation in save status indicator
- Add automated test suite with 367 tests and 921 assertions
- Fix external API validation errors on quiz and forum grading (missing return fields)
- Fix student feedback banner not showing for ungraded multi-attempt assignments
- Fix student PDF preview 404 for multi-attempt assignments with auto-reopen

## v2.1.1 (2026-03-21)
- Add student submission comments for quiz and forum activities (popout chat bubble)
- Add submission comment popout to quiz feedback viewer
- Fix SATS Mail bridge hardcoded assign URL to support all activity types
- Fix unified grader link missing from format_simple cog menu
- Add GitHub Actions CI workflow (moodle-plugin-ci)
- Consolidate overrides and extensions into a single unified modal for all activity types
- Auto-adjust cut-off/close date override when extension exceeds it (assign and quiz)

## v2.0.3 (2026-03-17)
- Fix forum preview not displaying uploaded videos and media (missing pluginfile URL rewrite)

## v2.0.2 (2026-03-13)
- Add multilingual support with 12 languages (Afrikaans, German, Greek, Spanish, French, Hebrew, Italian, Portuguese, Russian, Swahili, Xhosa, Zulu)
- Add multi-group filtering with "All my groups" pseudo-group and multi-select checkbox dropdown
- Add comment library autocomplete suggestions in marking guide remark textareas and annotation comment picker
- Fix late penalty not recalculating after a due date extension is granted
- Fix hardcoded penalty strings to use language strings
- Fix feedback video clipping in student feedback view
- Remap up/down arrow keys to scroll the preview pane instead of navigating between students

## v2.0.1 (2026-03-06)
- Add "Mark as graded" toggle for feedback-only activities (assignments and forums with no grade type)
- Fix multi-attempt grade sync to ensure gradebook reflects the graded attempt
- Fix per-attempt submission dates in student navigator
- Fix preview panel rendering for specific assignment attempts
- Fix coding standards and security issues from audit
- Update plugin icon

## v2.0.0 (2026-03-04)
- Add late penalty badges with time offset display
- Add grading-disabled activity support (feedback without grades)
- Fix forum feedback file storage and gradebook sync
- Add quiz late penalty badge and shareable grader URL
- Add per-attempt quiz feedback with separate feedback per attempt
- Fix audio playback in gradebook feedback view
- Add multi-attempt selector to assignment student feedback view
- Fix forum gradebook sync for grade updates

## v1.9.0 (2026-02-28)
- Add forum due date extensions with embedded form
- Fix penalty and grade separation in grading workflow
- Add offline comment library caching and unsaved changes protection
- Improve quiz adapter with multi-attempt support and penalties
- Add penalty system with automatic and custom late penalties
- Add feedback summary PDF generation (with GhostScript support)
- Include original submission PDF in feedback download when no annotations exist

## v1.8.0 (2026-02-22)
- Add continuous scroll PDF viewer
- Fix annotation save issues with page switching
- Fix quiz preview blank screen
- Add forum and quiz feedback file storage areas
- Add academic impropriety report form integration
- Add security hardening and annotation data validation
- Add auto-save loop prevention

## v1.7.0 (2026-02-16)
- Add comment library v2 with tagging and course-code organisation
- Add quiz extension management (via quizaccess_duedate plugin)
- Add auto-save for grades and feedback
- Add forum attachment preview in submission panel
- Add student profile popout
- Add forum plagiarism shields
- Exclude suspended students from grader participant list

## v1.6.0 (2026-02-10)
- Add due date extension modal
- Add per-user late submission detection
- Add override management for due dates and grades
- Add intuitive status filters (all, submitted, graded, not submitted)
- Improve feedback view with assessment criteria display

## v1.5.0 (2026-02-04)
- Add assessment criteria modal for rubric and marking guide display
- Add text selection tool for annotations
- Add shape annotations (rectangles, circles, arrows, lines)
- Add late submission indicators
- Add submission actions (lock, unlock, revert to draft, submit on behalf)

## v1.4.0 (2026-01-29)
- Add grade posting toggle with post/unpost functionality
- Add scheduled grade posting for assignments
- Add student feedback display banner (PSR-14 hook injection)
- Add TinyMCE feedback editor with audio/video recording support
- Add submission comment threads
- Add manual grade override option for rubric/marking guide activities
- Add document info panel (page count, word count, file metadata)

## v1.3.0 (2026-01-21)
- Add plagiarism plugin integration (Turnitin, Copyleaks)
- Add student feedback view with flattened annotated PDFs
- Add forum and quiz adapters
- Add group filtering for participant lists
- Add media preview (audio/video) in submission panel

## v1.2.0 (2026-01-14)
- Add PDF annotation layer with Fabric.js (highlighting, pen, stamps, comments)
- Add annotation persistence with per-page state management
- Add flattened annotated PDF generation (client-side pdf-lib)
- Add annotated PDF storage and student download

## v1.1.0 (2026-01-07)
- Add PDF.js viewer with continuous scroll and zoom
- Add annotation toolbar UI
- Add private teacher notes

## v1.0.0 (2025-12-20)
- Initial release
- Assignment grading adapter with full Moodle assign integration
- Split-view grading interface (preview + marking panel)
- Student navigator with search and filtering
- Rubric and marking guide support
- User preferences persistence
- Privacy API implementation
