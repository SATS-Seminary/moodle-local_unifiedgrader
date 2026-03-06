# Changelog

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
