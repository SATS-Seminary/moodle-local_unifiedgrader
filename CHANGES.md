# Changelog

## v2.5.1 (2026-06-05)
- Fix annotation tools getting stuck after a marking-pane state update (reported by users as "annotation freezes after I update the grading panel; refresh fixes it"). Root cause was a `_propagating` recursion-guard in `pdf_viewer.js` that fanned out `setTool` / `setColor` / `setStampType` / `setShapeType` / `setBrushWidth` to every page slot without a `try/finally`. If any one of those propagated calls threw synchronously — which is plausible during a marking-pane update because `submission:updated` re-fires `_renderSubmission`, which can collide with an in-flight `_renderPageIfNeeded` and leave a sibling page's Fabric canvas half-initialised — the flag stayed pinned to `true` for the lifetime of the page. From that moment, every subsequent tool click was silently dropped at the top of `layer.onToolChange` and the toolbar looked "active" but the active page's tool state never moved. A page refresh appeared to fix it because the constructor reinitialises the flag. Both propagation loops (`onToolChange` and `_wrapLayerStateTracking`) are now wrapped in `try/finally`, and each individual propagation is wrapped in its own `try/catch` so one bad slot logs an error without blocking the others. `AnnotationLayer.setTool` also bails early if the Fabric canvas has been disposed (`!this._canvas || !this._canvas.upperCanvasEl`) and wraps its remaining body in `try/catch` as belt-and-braces — a half-disposed canvas during the propagation race no longer surfaces an uncaught Fabric exception. Same-pass fix for a secondary stale-reference bug: `_destroyAllSlots` (called on every zoom) used to leave the annotation toolbar pointing at a disposed layer until the user scrolled and a new layer became active. The toolbar now gets `setLayer(null)` immediately during teardown; `setLayer` handles null cleanly; the next `_initAnnotationLayer` for page 1 re-links it. The toolbar's `_selectTool` is null-safe too, so a tool click in the brief teardown window is silently dropped instead of throwing.
- New PDF annotation tools: text-selection-based **Highlight text** and **Strikethrough**. The existing area-based "Highlight" tool was great for charts, diagrams, and any region where text selection won't reach — but for prose, teachers wanted to drag through the text itself, the way every other PDF reader works. Both new tools sit next to the existing area-highlight in the toolbar. Selecting the tool makes the PDF.js text layer interactable; the teacher selects text the normal way, and on mouseup each visual line of the selection becomes a Fabric.js shape — translucent rectangles for highlight, thin horizontal lines through the text's vertical centre for strikethrough. Annotations group per-line shapes so the whole highlight selects/drags/deletes as a unit. Resize and rotate are locked because the geometry is bound to the underlying words; drag stays enabled for nudging mis-placed marks. The current colour picker drives both tools' fill/stroke — pick yellow for the classic highlighter look, red for strikethrough. Annotations round-trip through the existing save/load pipeline and flatten into the final annotated PDF without any changes to `pdf_flatten.js` because they're standard Fabric Groups.

## v2.5.0 (2026-06-03)

This release lands the outcomes of a four-stream security and performance audit (XSS, SQL/RCE injection, third-party CVE / supply chain, DB load and lock contention). The headline is a stored XSS in submission comments — exploitable by any student with `viewfeedback` against any teacher who reads the thread — plus a clutch of N+1 query batches that turn the participant list from O(N) round-trips into O(1).

### Bug fixes
- Fix intermittent missing student name in the marking-panel header. The name occasionally rendered as `--` while the submission, feedback, and grade displays stayed correct; a page refresh brought the name back. Affected anyone whose active filter excludes the current student after a status change — most reproducibly `filter=needsgrading` + grading the student (`gradevalue` becomes non-null and the server-side `matches_filter('needsgrading')` starts returning false), `filter=notsubmitted` + submit-for-grading, or `filter=graded` + grade reset. After `saveGrade` / `submissionAction` / `updateFilters` / `loadParticipants` the mutations layer reassigns `state.participants` to the freshly filtered WS response, and the header rendering code's `participants.find(p => p.id === currentUser.id)` started returning `undefined`. The marking panel itself kept working because it keys on `state.currentUser.id` directly, not on a participant record — that's why the bug surfaced only in the top-of-card name + avatar. `student_navigator` now maintains a per-userid snapshot cache (`_participantCache`) seeded from every participants list it sees, with a `_resolveCurrentParticipant()` helper that prefers the live list (so status/grade/late changes propagate immediately) and falls back to the snapshot when the active filter has hidden the student. `_onSubmissionUpdated` and `_showProfilePopout` route through the same helper. The two `findIndex` sites (prev/next student buttons) are intentionally left as-is — those genuinely need an index in the live filtered list.

### Security
- **CRITICAL — Fix stored XSS in submission comments.** `local_unifiedgrader_scomm.content` was stored raw via `add_submission_comment` (`PARAM_RAW`), returned raw by `get_submission_comments` (`PARAM_RAW`), and assigned to `innerHTML` in four JS files (the marking-panel chat widget, the on-activity chat bubble for quiz/forum/BBB, the inline assignment widget, and the student feedback view). A student with `local/unifiedgrader:viewfeedback` could post `<img src=x onerror=…>` and the next teacher to open the marking panel would execute the payload with grading privileges — full session takeover. Both WS handlers now wrap `content` with `format_text($content, FORMAT_MOODLE, ['context' => $context, 'para' => false])` before returning, so the client renders sanitized HTML uniformly whether it just posted the comment or fetched the thread. New regression test `tests/external/submission_comment_webservices_test.php::test_submission_comment_strips_script_payload` pins the behaviour by round-tripping `<script>`, inline `onerror=`, and `javascript:` URI payloads through both endpoints and asserting they're neutralised. The "Trust boundary" comments in the four JS files used to claim the content was sanitized by Moodle core's comment API — that was false (the plugin uses its own `submission_comment_manager` table, not the core comment API). The comments have been corrected to describe the real trust boundary.
- **MEDIUM — Filter teacher-authored rubric/marking-guide HTML through `format_text()`.** `description`, `definition`, and per-criterion `remark` fields were emitted to the client as raw HTML and rendered via Mustache `{{{triple-stache}}}` in the student feedback view, the forum feedback view, and the assessment-criteria modal that students see pre-grading. The pattern was already correct for the `descriptionmarkers` (information-for-graders) field — `format_text()` with `context_module`. That treatment is now applied to the other three fields in `feedback_data_helper::parse_grading_data()` (which now takes a required `\context` parameter, threaded through from `view_feedback.php` and `download_feedback.php`) and in `assign_adapter::serialize_grading_definition()` / `forum_adapter::serialize_grading_definition()`. Closes the teacher-with-edit-rights → student script-execution boundary.
- **Defense-in-depth — Disable PDF.js eval-based code paths.** Adds `isEvalSupported: false` to the `getDocument({...})` call in `pdf_viewer.js`. PDF.js 4.2.67+ removed the eval-based font loader (which CVE-2024-4367 used as a sink) entirely, so 4.10.38 is not exploitable — but explicitly disabling eval is cheap insurance against future regressions and a clearer signal of intent for anyone auditing the bundle later.
- **Supply chain — SHA-256 integrity verification on every deploy.** `thirdpartylibs.xml` now carries `<sha256><file path="…">…</file></sha256>` entries for `pdf.min.js`, `pdf.worker.min.js`, `fabric.js`, and `pdf-lib.min.js`. `deploy.sh` runs a `verify_thirdparty_integrity` step before rsync that recomputes hashes against the dev tree and aborts on mismatch. If `xmllint` or `shasum` aren't installed the check skips gracefully so dev machines without those tools still work. Catches accidental or malicious tampering of bundled libraries between releases.
- **CVE watch — Fabric.js CVE-2026-27013 (CVSS 7.6, stored XSS via SVG export).** The bundled Fabric.js 6.9.1 is within the vulnerable range (`< 7.2.0`), but the sink does not exist in this plugin's call graph — we never call `toSVG()`. Annotation flattening goes via `OffscreenCanvas.toDataURL('image/png')` in `pdf_flatten.js`, which rasterises away any unescaped attribute text. If a future feature ever exports SVG (e.g. for printing or vector PDF embedding), Fabric.js MUST be upgraded to v7.2+ first. The `loadFromJSON()` call sites have been annotated with comments documenting this sink-absence invariant so a future contributor knows the constraint.

### Performance
- **HIGH — Batch the N+1 participant query in assign.** `assign_adapter::get_participants()` used to fire `get_user_submission()` + `get_user_grade()` + `get_user_flags()` once per participant — three DB round-trips × N students on top of the user_picture rendering. For a 500-student course that was ~1500 single-row queries; for 5000 students, ~15000. The forum, quiz, and bbb adapters were already batching these correctly; only the primary use case was slow. `get_participants()` now batch-loads all three tables once outside the loop using `get_records_sql` with a `MAX(attemptnumber)` self-join for the latest-attempt semantic on submissions and grades, plus a single `get_records` for `assign_user_flags`. Drops 3N round-trips to 3 fixed queries. Team-submission assignments (a small minority where submissions are keyed on `groupid` rather than `userid`) fall back to the original per-user path — the batching doesn't apply to that shape.
- **MEDIUM — Batch tag + proposal lookup in the comment library.** `comment_library_manager::format_comment()` was doing 1–2 DB queries per comment (a tag-id fetch via `get_fieldset_select`, plus optionally a per-comment proposal-status lookup). A teacher with 200 personal comments paid 200–400 queries every time the comment-library pill rendered — which is on every page load and after every save. A new `prefetch_comment_metadata()` helper batch-loads `local_unifiedgrader_clmap` and `local_unifiedgrader_clibprop` via `get_in_or_equal()`, builds two id-keyed lookup maps, and passes them into `format_comment()` (now a pure array transform — no DB). Brings the per-render cost to 2 fixed queries regardless of comment count. All three internal callers (`get_comments`, `get_system_comments`, `get_shared_comments`) updated to call the prefetch once before the format-loop.
- **MEDIUM — Batch author lookup in `notes_manager::get_notes()`.** The render loop did `\core_user::get_user($note->authorid)` per note — static-cached after the first hit but cold renders still paid per author. Now a single `get_records_select('user', "id $insql", …)` upfront, using `\core_user\fields::for_name()->with_userpic()->get_required_fields()` to pick up the full set of fields needed for `fullname()` and user-picture rendering.

### Concurrency / data integrity
- **MEDIUM — Fix annotation save race with UNIQUE constraint + transaction.** `annotation_manager::save_annotations()` did `get_record` → conditional `insert` / `update` / `delete` per page with no transaction and no unique constraint on `(cmid, userid, authorid, fileid, pagenum)`. A fast-typing teacher (the annotation layer autosaves on every tool action) could fire two overlapping WS saves; both observe "no row exists" via `get_record`, both insert, producing duplicate rows that then corrupt subsequent reads. The composite index `ix_cmid_userid_fileid` was non-unique so the schema did nothing to prevent this. Replaced by a strictly-superset UNIQUE index `uix_cmid_userid_authorid_fileid_pagenum` (still index-accelerates the `cmid + userid` partial-prefix reads). An upgrade step (gated at savepoint `2026052900`) de-duplicates any existing colliding rows before adding the unique index — the de-dup keeps the row with the highest `id` per composite key, since `annotationdata` is overwritten on subsequent saves anyway. The save batch in `save_annotations()` is now wrapped in `\core\dml\moodle_database::start_delegated_transaction()`; the race loser hits the unique constraint, the transaction rolls back cleanly, and the client can retry instead of leaving half-applied state.

## v2.4.7 (2026-05-26)
- Release the PHP session lock in all 41 web service handlers immediately after capability checks. **Background:** Moodle (and PHP itself) holds a per-user session lock for the entire lifetime of every request — the lock exists to prevent two concurrent requests from the same user from racing to write `$SESSION`. While the lock is held, every other AJAX call from the same teacher serialises behind the running one, even if the requests are completely independent. The Unified Grader fans out a lot of parallel WS calls in normal use — picking a student fires `get_submission_data` + `get_grade_data` + `get_participants` + `get_annotations` + `get_notes` + `get_submission_comments` + `prepare_feedback_draft` + `prepare_feedback_files_draft` simultaneously; debounced autosave fires `save_grade` mid-edit; the annotation layer pings `save_annotations`. With the session lock held by an in-flight `save_grade` (which itself runs Moodle core's heavy grade-pipeline + file moves), the next eight requests queue up. If `save_grade` takes long enough — slow gradebook recompute, big course, contended DB — the queued requests trip Moodle's `session_lock_expire` of 2 minutes and the teacher sees: *"Unable to obtain lock for session id session_XX within 2 mins. It is likely that another page (.../save_grade) is still running in another browser tab."* **Fix:** none of our 41 WS handlers writes to `$SESSION` after authentication, so each one now calls `\core\session\manager::write_close()` immediately after `validate_context()` + `require_capability()`. The session is closed, the lock is released, and concurrent requests proceed in parallel instead of head-of-line-blocking behind a slow save. For the three handlers where capability is conditional (grader-or-student paths in `add_submission_comment`, `get_submission_comments`, `delete_submission_comment`), the close was moved out of the defensive throw-only branch so it always runs after every auth gate has passed.
- Slow-save instrumentation: `local_unifiedgrader_save_grade` now emits a `debugging()` warning when a single grade save exceeds five seconds, with cmid, userid, elapsed time, and the most likely culprit dimensions (adapter type, whether advanced grading was in play, feedback draft sizes). Five seconds is well below the 2-minute session-lock timeout but well above normal save latency, so admins running with developer debug get early warning of a real bottleneck (gradebook recompute on a heavy course, DB contention, a misbehaving event observer) without daily noise. No effect on normal operation.
- Profile popout now offers a "Send mail" shortcut into `local_satsmail` when that companion plugin is installed. Previously the email line below the student's name was a plain `mailto:` link that opened the teacher's external mail client — useful, but it bypassed the satsmail audit trail and lost the course context. When satsmail is detected (presence check on its compose entry point, not a class autoload so it degrades cleanly when satsmail isn't deployed), the email line becomes a "Send mail" link that opens satsmail's compose flow with the course and recipient pre-filled. The student's email address remains visible on hover (and in the full profile view). When satsmail isn't installed the popout falls back to the existing `mailto:` behaviour.
- Fix the participants list showing "Graded" for a submission that has just been reverted to draft from the marking panel. `assign_adapter::resolve_status()` was returning `'graded'` whenever an `assign_grades` row existed, regardless of the underlying `assign_submission.status`. Reverting to draft flips the submission row to `draft` but deliberately leaves the grade row intact (so the teacher's previous mark is preserved for next time) — the participant pill then kept saying "Graded" while the marking-panel pill, which reads `submission.status` raw, correctly said "Draft". Two contradictory verdicts for the same student. The resolver now treats an explicit `draft` or `reopened` status as authoritative and only falls through to the grade-row check when the submission is still in a graded-or-submitted shape. Regression test in `tests/adapter/assign_adapter_test.php` (`test_get_participants_reverted_graded_submission_shows_draft`) pins the behaviour.

## v2.4.6 (2026-05-26)
- Validate marking-guide criterion scores against their allocated maximum. Previously a teacher could type any value into a criterion score input — entering 6 in a criterion allocated 5 marks would round-trip silently to the server, inflate the marking-guide total, and break the gradebook column with no warning. The score input now flags out-of-range values (negative or above the criterion max) inline with a red border and a "Score for "X" must be between 0 and Y" message; the save dispatch (manual click + debounced autosave) refuses to fire while any criterion is flagged. Validation also runs against server-supplied fills on initial render and on student switch so anomalies introduced by older grading paths surface immediately. Same logic applies to per-question marks in quiz manual grading mode (which routes through the same widget).
- Submission-comment notifications now respect group mode on group-aware activities. Previously a student posting a comment notified every user with `local/unifiedgrader:grade` on the activity, including teachers responsible for other groups (and any course-wide editing teacher). For SEPARATEGROUPS or VISIBLEGROUPS activities, the notification is now scoped to teachers who share at least one group with the student. When the student belongs to no group, or none of the matching teachers are in the student's group(s), fall back to the legacy "all graders" recipient list so the message never gets lost. New focused test suite `tests/notification/submission_comment_notification_test.php` (7 tests) pins down the routing for every combination of group mode + membership
- Fix multi-attempt assignment grading panel not refreshing when the teacher picks a different attempt from the attempt selector. The `loadAttempt` mutation fetched the per-attempt grade and feedback correctly, but `_renderGrade`'s "navigation-boundary" override-lock gating only treated *student-switch* as a fresh render — same student, different attempt counted as a non-navigation re-render and skipped the form-input writes. The grade input, rubric/marking-guide fills and feedback editor all stayed on the previously-shown attempt's values. The gate now keys on `(userid, attemptnumber)` together so attempt-switch triggers the same fresh-render path that student-switch does.
- Fix the "late submission" badge silently disappearing from the marking panel on assignments where the student started a draft on time but submitted after the due date. The badge logic was reading `state.submission.timecreated` (draft start) while the student-list red dot used the submission's `timemodified` (final submit) — same student, two contradictory verdicts. The marking-panel badge stayed hidden while the red dot still showed.
- Same root cause was masking the late-penalty badge for graded submissions: `_getLatePenaltyPct` suppressed the badge whenever `timecreated <= duedate`, so an assignment that was penalised at save time never showed a "-X% Late" indicator on the next visit. Returning teachers and moderators couldn't see at a glance that the grade had been reduced.
- Fix: each adapter (`assign`, `forum`, `quiz`, `bbb`) now surfaces a canonical `submittedat` field in `build_submission_data()` that matches the semantic it already uses for the islate flag in `get_participants()` — `assign` = final submit (`timemodified`), `forum` = first post (`timecreated`), `quiz` = attempt `timefinish`, `bbb` = 0 (no submission concept). The `marking_panel.js` late-indicator and penalty-badge logic now read this single field instead of guessing between `timecreated`/`timemodified`, so the student-list red dot and the marking-panel badge always agree. WS schema for `get_submission_data` extended to include `submittedat`.
- Fix the late-penalty *point deduction* not surfacing in the grade card when Moodle's core gradepenalty subsystem applies a late penalty. `assign_adapter::get_grade_data()` was computing `$stilllate` against `$submission->timecreated` (draft start) — same divergence as above, but on the server side. For an on-time-draft-but-late-submit assignment the check returned false, `$latepenaltypct` stayed null, the WS returned no percentage, and the client had nothing to render — so the grade card showed the raw rubric total with no indication that the saved/gradebook grade was actually lower. Teachers had to open the gradebook column to confirm the penalty existed. Now uses `$submission->timemodified` to match the student-list islate check and the new `submittedat` field; the `-X% Late` badge and the "Final grade after penalties" display both populate correctly.

## v2.4.5 (2026-05-20)
- Quiz "Post grades" now flips a narrow, well-defined slice of the quiz review-options matrix instead of leaving teachers guessing about which review settings change. Specifically: `reviewmarks`, `reviewmaxmarks`, AND `reviewoverallfeedback` (added — previously only the first two) for the `LATER_WHILE_OPEN` and `AFTER_CLOSE` timeframes. Every other review setting is left exactly as the teacher configured on the quiz Review options page — most importantly `reviewattempt`, which controls whether the student can open the attempt at all and is governed only by the teacher's manual quiz settings. This means a teacher who wants "marks visible, attempt hidden" can leave `reviewattempt` off and use UG to flip marks visibility on or off without UG ever revealing the attempt contents. Resolves issue [#13](https://github.com/SATS-Seminary/moodle-local_unifiedgrader/issues/13). The confirmation dialog and in-product quiz help section have been updated to describe exactly which review options UG touches and which it leaves alone
- Surface per-attachment plagiarism reports on graded forums in the marking-panel "Plagiarism" card, the same way assignment file attachments are listed. Forum post-body plagiarism continues to render via the existing inline post shields in the preview panel; the marking-panel card lists attachment shields only so there's no duplicate display. The card sits above the marking guide / rubric just like for assignments
- New `\local_unifiedgrader\event\feedback_viewed` event class, fired from `view_feedback.php` after the grade-released check passes. Analytics consumers (gradereport_coifish / local_coifish) can now measure student engagement with Unified Grader feedback alongside the native `\mod_assign\event\feedback_viewed` event — closing the visibility gap on UG-graded forums, quizzes, and BBB activities, which never emit `mod_assign` view events
- The event is naturally gated by the existing `enable_<modname>` admin setting and the grade-release check, so it only fires when the student is actually viewing feedback for an activity type the institution has enabled
- Fix the submission-comments chat bubble being injected into the news / announcements forum. Moodle auto-creates an Announcements forum on every course, but those aren't graded and never form part of the teacher / student feedback loop, so the chat affordance has no place there. The hook callback now skips the widget on `forum.type === 'news'`, and the `add_submission_comment` web service rejects calls targeting a news forum as defensive belt-and-braces in case a stale cache lets the JS slip through. New `local_unifiedgrader\forum_helper::is_news_forum()` helper shared by both call sites
- Any pre-existing rows in `local_unifiedgrader_scomm` against a news forum are left as-is by this commit; if you want them gone, run: `DELETE s FROM {local_unifiedgrader_scomm} s JOIN {course_modules} cm ON cm.id = s.cmid JOIN {forum} f ON f.id = cm.instance JOIN {modules} m ON m.id = cm.module AND m.name = 'forum' WHERE f.type = 'news'`

## v2.4.4 (2026-05-18)
- Fix the marking-guide total badge displaying floating-point summing artifacts when the teacher entered decimal scores — `4 + 3.8 + 4.1 + 2` was being shown as `13.899999999999999 / 25` instead of `13.9 / 25`. The total is now rounded at the source so any value derived from it (the badge, the grade input via `_computeRubricGrade`, the percentage display) stays clean
- Rounding precision follows the gradebook's per-item `decimalpoints` setting (the same one that governs gradebook column display), with a hard floor of 2 decimal places so fractional rubric scores aren't silently swallowed when the gradebook is configured at 0dp. No new plugin setting — the existing course-level gradebook config is the single source of truth. Surfaced into the serialised grading definition for all three adapters (assign, forum, quiz)

## v2.4.3 (2026-05-18)
### Grade reset escape hatches
- Typing `-` in the overall grade input now resets the grade to "no grade" — mirroring how Moodle's gradebook accepts `-` to clear a cell. Previously a lone `-` reached the `save_grade` WS as `NaN` and surfaced an unhandled PARAM_FLOAT exception. The grade input clears immediately, the manual-override lock drops, and the save sends `-1` (no grade) to the server. Any other non-numeric placeholder is normalised the same way as belt-and-braces against future regressions
- Typing `--` is a **deliberate reset**: same grade-clearing behaviour as `-` plus
   - removes any orphan submission row whose status is not `submitted` (i.e. one created by accidental teacher interaction with the marking panel, not a real student submission). Real submitted rows are left intact
   - clears any `grade_grades.overridden` flag on the gradebook entry, so the gradebook column reverts to ungraded rather than staying pinned to a previously-applied override (typical when `gradepenalty_duedate` or a manual gradebook edit had set it)
- The `-` / `--` semantics apply to **forums too**: forum_adapter previously routed null grades through the advanced-grading pipeline which would rewrite a freshly-computed grade back in, and never overrode the deliberate-reset method. Both paths now correctly null the `forum_grades` row (light reset) or delete it entirely (deliberate reset, plus gradebook override lift). The student's posts are never touched
- New `reset` boolean param on `local_unifiedgrader_save_grade` (default false, so existing callers are unaffected); the WS short-circuits to a new `reset_grade_and_submission()` adapter method when set. `clear_recoverable_gradebook_block()` was hoisted from `assign_adapter` to `base_adapter` so all adapters share it

### Retry document conversion
- New **Retry conversion** button on the previewer's conversion-failed overlay. When the document converter (Google Docs, unoconv, FlaskOffice, etc.) is offline, Moodle's `mdl_file_conversion` table caches the failure and subsequent preview requests hit the stale failed row rather than re-trying. The button drops the cached row server-side and re-fires the preview load, which triggers a fresh conversion attempt. Backed by a new `local_unifiedgrader_retry_file_conversion` web service that handles both regular file conversions and online-text PDFs (which cache in our own `local_unifiedgrader/onlinetextpdf` filearea instead)
- The button is laid out on its own line below the error message with `gap-3` breathing room from the error text — earlier draft had them inline which crowded the borders

## v2.4.1 (2026-05-12)
- Default group filter to the teacher's own group(s) instead of "All groups" — previously this only happened for users without the `moodle/site:accessallgroups` capability, so course managers and admins saw every group by default
- Persist the group filter selection per-activity, keyed by cmid, so it survives page refreshes. Backed by a new `local_unifiedgrader_save_preference` web service and a small `preferences_manager` helper around the existing `local_unifiedgrader_prefs` table (previously only the privacy provider knew the table existed)

## v2.4.0 (2026-05-12)
### Comment library
- **Quick-access pill scoping**: pills now show only system defaults plus tags actually attached to comments in the current course (or to universal comments). Previously every tag the teacher had ever created showed up regardless of which course they were grading in
- **Universal comments**: new "Universal" checkbox on the new-comment and edit-comment forms in the manage-library modal. Universal comments have an empty coursecode and are visible across all of the teacher's courses; their tags also appear in every course's pill row. A sidebar entry labelled "Universal" surfaces them as their own bucket alongside per-course buckets
- **Search box** in the manage-library toolbar — case-insensitive substring match on comment content, combined with the existing course and tag filters
- **Admin: manage comment library** — new admin tool at *Site administration › Plugins › Local plugins › Unified Grader › Manage comment library* lets managers curate system-default tags (previously hardcoded) and seed system-default comments visible to every teacher across every course. New capability `local/unifiedgrader:managesystemdefaults` (manager archetype by default). System defaults render with a yellow star badge in the teacher's library modal and a dedicated "System defaults" sidebar bucket; they are read-only for teachers
- **Proposal workflow for system defaults**: teachers can suggest one of their own comments for inclusion as a system default via a star icon on each card; an optional rationale is prompted. Submissions appear in a "Pending submissions" section on the admin Manage System Defaults page; admins approve (creates a system-default copy preserving system tags, leaves the teacher's original untouched) or reject with an optional reason. The proposer's card shows a "Pending review" badge until decided and a "Rejected" badge (with the reason as a tooltip) afterwards. Backed by a new `local_unifiedgrader_clibprop` table and `local_unifiedgrader_submit_library_proposal` web service
- New setting *Require approval for system-default suggestions* (default ON). When ON, the proposal queue behaves as above. When OFF, teacher suggestions are promoted immediately to system defaults, inheriting only system tags from the proposer's selection; the proposal row is still recorded with `status = approved` and `decidedby = 0` for audit
- Fix: manual grade override (or any standalone edit of the grade input) now triggers an autosave on focus-out. Previously the rubric body had a focusout-autosave listener but the grade input did not, so an override of the rubric-computed total silently vanished on the next student switch or refresh
- Fix: once a teacher manually edits the top-level grade input, subsequent rubric/marking-guide edits no longer overwrite the manual value. Previously every `input` event on a rubric score fired `_updateGuideTotal` which wrote the auto-computed total back to the grade input, silently reverting any override. The lock clears on student switch so the next student starts in auto-sync mode
- Add an **Override** indicator below the grade input — a yellow badge plus the rubric total and a *Reset to rubric total* link — that appears whenever the displayed grade differs from the rubric/marking-guide computed value. The badge persists across sessions so a returning teacher or moderator can immediately see that the score has been manually adjusted; clicking *Reset* re-syncs the grade input with the rubric and triggers an autosave

### Grading
- Cap the manual grade input at the activity's maximum mark. Previously a teacher could type 40 in a 20-mark assignment and the save would persist 40 (200%), which silently broke gradebook calculations. The grade input now shows an inline error and refuses to save when the entered value exceeds the max; the server-side web service throws `error_grade_exceeds_max` as a belt-and-braces check. Skipped for scale-based grading (dropdown can't exceed) and for quiz grades (question engine clamps separately). Extra credit is not currently supported

## v2.3.2 (2026-05-11)
- Fix marking-guide / rubric fillings silently failing to save when the gradebook entry for the student is overridden — most commonly caused by Moodle 5.0's `gradepenalty_duedate` plugin applying a late-penalty deduction, which leaves `grade_grades.overridden` set. With the flag set, `assign::grading_disabled()` returns true and `apply_grade_to_user` silently skips the entire advanced-grading save block: rubric fillings never reach `gradingform_guide_fillings` even though the grade itself appears to save. Recovery now uses `grade_grade::set_overridden(false, true)` (which also calls `grade_item->refresh_grades($userid)` to reconcile the gradebook cell) and trusts the clear function's boolean return — the previous `grading_disabled()` re-check returned a request-scoped cached value showing the flag still set, aborting recovery even after a successful clear
- Fix auto-save race that could wipe marks, remarks, and the grade after a teacher typed and clicked on blank space — replaces the per-field snapshot/timing-based protection (which depended on fragile browser focusout/relatedTarget signals) with a **navigation-boundary gate**: server values are applied to editable form inputs only on the initial render or when the active student changes; every other `grade:updated` watcher invocation leaves the DOM as the teacher last edited it. Derived displays (penalty badges, percentage, late indicator, total scores) keep updating freely. Belt-and-braces guard in `_updateFeedbackContent`: TinyMCE editor still refuses overwrite when it has focus
- Fix marking-guide score inputs silently persisting as 0 when the teacher's browser locale uses comma as the decimal separator: `<input type="number">` rejects period-decimal input in those locales, so "3.5" was being sent to the server as an empty string and stored in `gradingform_guide_fillings.score` (a NOT NULL number column) as 0
- Switch score inputs and the top-level grade input from `type="number"` to `type="text"` + `inputmode="decimal"`, and canonicalise comma → period on every keystroke so values round-trip correctly regardless of locale
- Fix `TypeError: Cannot set properties of undefined (setting 'innerHTML')` thrown by `comment_library_popout._renderTags` when the remark-textarea autocomplete calls `getComments()` before the popout DOM has been built — `_renderTags` and `_renderComments` now bail out cleanly when their containers aren't ready yet

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
