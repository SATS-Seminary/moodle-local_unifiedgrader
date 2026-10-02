# Changelog

## v3.0.0 (2026100100)

### Post grades for one student, a group, or the class

Grade posting is no longer only a switch for the whole class. The post-grades menu now posts or hides the open student, the groups in the current filter, or the whole class. A partly posted class shows how many students are posted, and each row carries an eye for that student's grade.

The class switch is still the grade item's hidden flag. A student or a group is that student's gradebook cell (`grade_grades.hidden`). While the item is hidden it covers every cell, so posting some of the class copies the item's hidden value onto every other student's cell, creating one where none exists, and only then reveals the item. A grade saved afterwards for a student who was not posted is hidden too. Posting the whole class clears every cell.

"Group" is a course group, the same groups the grader already filters by. A separate-groups teacher posts their own groups, and cannot post the class.

Assignments with marking workflow release the same students. A mark saved afterwards is written even when Moodle 5.3 has locked a released grade, and the workflow state is left as it was. When a partial post reveals the item, students who stay hidden are marked mailed, so the assignment's notification cron (which watches the item, not the cell) does not write to them.

Quizzes are unchanged on the attempt review page. Posting one student or one group writes the gradebook cell and Unified Grader feedback only, and leaves the quiz review options alone. While those options still hide marks, the gradebook hides the mark as well, and the gradebook eye cannot show the quiz item because the quiz owns that flag. With Friction Feedback, finishing the walk shows that student's mark in the gradebook and keeps their feedback page open. The review options stay as they are, other students stay hidden, and a later quiz grade sync does not hide the mark again. Hiding the class closes it. The admin setting `enable_quiz_post_grades` still gates every quiz post.

Covered by `tests/grades/release_test.php` and `tests/behat/post_grades.feature`.

### Friction feedback

Posting a grade can hold the mark back until the student has read the feedback. The site setting is off. An activity can follow that, or force the walk on or off. The post itself does not grow a new choice.

The student is told the feedback is ready. On the feedback page a single step shows one rubric criterion, one marking-guide criterion, one manually marked quiz question, or the overall feedback. Points stay off the card. A timer (20 seconds by default) runs and pauses while the tab is hidden. When it finishes, the student ticks "I have seen this". The last tick reveals the mark, the points, and the feedback PDF on the normal page. On a quiz that page stays open even while the attempt review options still hide marks. Closing the page resumes on the same step. A new mark starts the walk again.

The gradebook mark stays hidden for that student until then. On a quiz, that includes the grade item the review options keep hidden. The teacher still sees the grade as posted. The stored course total includes the hidden grade. Moodle's user report drops hidden items from the total a student sees, so a daily task opens a held cell when either clock is due, whichever comes first: a set number of days before the course end (14 by default), or a set number of days after the mark was posted (21 by default). The second clock also covers a course with no end date. A grade posted after its clock has already passed opens on the next run. A mark a teacher hides is left hidden. The assignment release email stays unsent for the whole hold, including the fail-safe, so the mark is not mailed days later.

The quiz attempt review page is unchanged, and forum rating stars stay visible. A course backup does not carry an in-progress walk.

Covered by `tests/friction/service_test.php` and `tests/behat/friction_feedback.feature`.

### Unified Grader owns late penalties on Moodle 5.3

Moodle 5.3 gives quizzes their own due date and due date overrides (MDL-82521), and records a gradebook deduction separately from the raw grade (MDL-88407). On 5.3 and later, Unified Grader now applies late penalties itself for assignments, forums and quizzes, from the core `gradepenalty_duedate` rules, so all three behave the same way. Moodle 5.0 keeps the behaviour of 2.13 unchanged. The switch is made at runtime (`penalty\compat::unified()`, true once `mod_quiz` is at 2026083100 or later), so one release serves both.

- **One switch per activity.** An "Apply late penalties" box in the assignment, forum and quiz settings, stored in the new `local_unifiedgrader_penset` table and carried through backup and restore. Until it is saved, an activity keeps the behaviour it had: assignments follow their own "Apply penalties" setting, forums are on, and quizzes are on only if `quizaccess_duedate` penalised them. New activities start off.
- **Quiz due dates and extensions come from core.** The quiz due date is `quiz.duedate`, and an extension is the `duedate` on the student's core override, saved through the quiz's override manager with the close time raised to match. `quizaccess_duedate` is ignored on 5.3 even if still installed, since its `duedate` form field and settings query collide with core's. A quiz without a due date is never late; the close time is no longer used in its place.
- **Quizzes: the first genuine attempt decides.** Only the first submitted attempt in which the student answered at least `quizgenuineattemptpct` of the scoring questions (new setting, default 50%) counts. On time, and later attempts can reach full marks. Late, and its penalty comes off the quiz grade whatever later attempts score. An attempt submitted empty no longer secures an on-time attempt.
- **Deductions go to `grade_grades.deductedmark`** (`penalty\gradebook_writer`). The raw grade stays the mark as given, the gradebook shows its penalty icon, and nothing is pinned with an override, so quiz regrades and new attempts flow through. Teacher overrides and locked cells are left alone. A cell pinned by the old quiz penalty path is released the next time that student's grade is resynced, so grades in closed courses are not recalculated. Such a pin is recognised by its "Late penalty of N% applied." feedback, which survives a course backup and restore that rewrites the grade history, or by the history entry that set the override. That auto-written feedback is cleared with the pin. Courses still frozen on the pre-5.3 penalty maths fall back to the old reduced push.
- **Penalties survive grading outside the grader.** New observers resync penalties (`penalty\service::resync()`) on `user_graded`, quiz attempt submission, assignment extensions, and assignment and quiz user and group overrides. Changes to an activity's settings resync the whole activity in an adhoc task (`task\resync_penalties`). Before this, a mark saved on Moodle's own assignment grading page discarded Unified Grader's manual penalties from the gradebook.
- **Assignments no longer rely on core's late penalty.** While an administrator leaves Assignment on under Grade penalties: supported modules, Unified Grader leaves the late penalty to core for assignments with "Apply penalties" ticked, and the plugin settings page asks for it to be switched off. Core's assignment pages show the penalised grade and penalty icon from the gradebook. `assign_grades.penalty` is left to core, which resets it on every grade push, so previous attempts listed there show their unpenalised marks.
- **Late penalty is a penalty row everywhere.** The grader shows it read-only, like forum late penalties, and it changes only when an extension, override or due date changes. The client no longer reads a separate late figure or old "Late penalty of N% applied" feedback text on 5.3 (new `latepenaltyisrow` activity flag).
- **Migration.** The hourly `task\migrate_penalties` (or `cli/migrate_penalties.php`) saves the late penalty switch `quizaccess_duedate` held for each quiz. It runs once, on 5.3. Run it before uninstalling the access rule. Moving the rule's due dates and extensions into core is left to the rule's own final release.

Covered by `tests/penalty/unified_penalties_test.php` and `tests/behat/late_penalties.feature` (both skipped below 5.3).

### The Dates & extensions dialogue

"Overrides and extensions" is now "Dates & extensions", a native dialogue in place of the iframe of Moodle forms. It works for assignments, quizzes and forums, on Moodle 5.0 and 5.3.

- **Every setting on one screen.** A table lists each date setting with the class default beside this student's value. There are no collapsed sections, and the class defaults are always in view. A changed value is highlighted, with the default struck through beside it.
- **Overrides are made in their row.** "Change" opens an input in place, "Reset" puts the class default back, and "Remove all overrides" clears everything. Nothing is saved until Save.
- **Extensions in one click.** Preset buttons (+1 day, +2 days, +3 days, +1 week by default) and a date picker. The presets come from a new site setting, `extensionpresets`: day counts, with multiples of 7 shown as weeks. A close or cut-off date that an extension passes moves with it, and its row says so.
- **Penalty preview before saving.** A banner states what the chosen dates mean: submitted on time, late with the penalty it carries, or "On time with this extension". On Moodle 5.3 it gives the late penalty for every activity type. On 5.0 it gives it for forums, and states lateness for assignments and quizzes, whose penalties core and `quizaccess_duedate` still own there.
- **Save stays in view.** The footer, with Save, a count of changes and "Remove all overrides", no longer scrolls away when a section or notice grows.

Behind it, `dates\student_dates` loads, previews and saves through the same activity APIs as before, via three new web services (`get_student_dates`, `preview_student_dates`, `save_student_dates`). Dates cross the web service boundary as local date-times in the teacher's Moodle time zone. Extending an assignment still offers the penalty recalculation wherever core owns its penalty. Adapters gained `get_late_reference_time()` and an optional due date on `calculate_late_penalty()` for the preview.

Removed: `overrides_extensions.php`, `override.php`, `extension.php`, `quiz_extension.php`, `forum_extension.php`, their forms, and the unused `override_modal` and `extension_modal` modules.

Covered by `tests/dates/student_dates_test.php` and `tests/behat/dates_dialogue.feature`.

### Caching

The plugin now uses the Moodle Universal Cache for three small lookups that are read far more often than they change (`db/caches.php`). Each is cleared wherever its answer can change.

- **`penaltyswitch`:** whether late penalties apply to an activity (`penalty\activity_settings::is_enabled()`). On Moodle 5.3 every penalty resync asks this, so a regrade or a changed due date asked it once per student. Cleared when the switch is saved or deleted, and when the activity's settings are saved, since the default comes from them.
- **`newsforum`:** whether a forum is a news forum, asked on every forum page a student opens. Cleared when the forum's settings are saved.
- **`userprefs`:** a teacher's grader preferences, read each time the grader opens. Kept in step on every write, and cleared when the privacy API deletes them.

Grades, submission status and whether feedback is released are still read from the database every time. Covered by `tests/cache_test.php`.

### Fewer queries as classes grow

The participant list, which the grader fetches on opening and again after every saved grade, cost one or more queries per student. It now costs the same for 15 students as for 3, for every activity type.

- **Profile pictures.** Building each student's picture URL looked up their user context one at a time. All four adapters now fetch them in one query (`base_adapter::attach_user_contextids()`); the forum thread view and the submission comment thread do the same for their authors.
- **Quiz due dates.** Each student's effective due date was worked out separately, which with `quizaccess_duedate` on Moodle 5.0 meant several queries per student. `quiz_adapter::get_effective_duedates()` resolves user overrides, group overrides and the quiz's own date for the whole class at once.
- **Team submissions.** Assignments with team submissions fell back to core's per-student calls, about five queries each. They are now batched like individual submissions, and listing the class no longer creates empty submission rows as those calls did.
- **Blind marking.** The anonymous IDs are read in one query.
- **Forum attachments.** A student's attachments were read post by post, twice. `base_adapter::get_area_files_by_item()` reads them in one query.
- **Also batched:** the grades behind an assignment's attempt list, and the tags of pending comment library proposals.
- **Penalty resync.** `task\resync_penalties` builds one adapter for the activity, not one per student.

`tests/query_count_test.php` fails if a per-student query returns to any participant list, and checks each batched lookup against the single one it replaced.

## v2.13.0 (2026093000)

### Rich-text comments on manually marked quiz questions

The comment on each manually marked quiz question was a plain textarea, so a teacher could not format it or add images or recorded audio, which Moodle's own manual grading page allows. It is now the same TinyMCE editor, with the options that page uses and the same file handling. Each question's editor works on its own draft area, which holds the files already attached to the comment. On save, those files are stored with the grading step in `question/response_bf_comment`, where the quiz serves them to students. A save that changes neither the text nor the files still adds no grading step.

- `grade.php` ships one TinyMCE configuration for these editors (built by the new `tiny_editor_config`, since `use_editor()` can only set up a textarea already in the page). The marking panel clones it for each question and points the file picker at that question's draft area.
- `prepare_feedback_draft` takes a new `questioncomments` flag. When a student or attempt loads, it also prepares a draft area for each question comment (`quiz_adapter::prepare_question_comment_drafts()`). Autosaves leave the flag off, so they create no draft areas.
- A comment saved from the old textarea was plain text stored as HTML, so its line breaks were never shown. When the editor loads such a comment, its line breaks become `<br>`.
- The attempt preview now resolves `@@PLUGINFILE@@` links in comments, so their images and audio display there.
- A teacher whose preferred editor is not TinyMCE keeps the plain textarea.

Covered by `tests/adapter/quiz_comment_files_test.php`.

## v2.12.5 (2026092802)

### Re-edited feedback sometimes stayed open after saving

Clicking Edit on saved overall feedback focuses the editor 100 ms later. After a save, the editor collapses back to the saved card only if it does not have focus, so a Save clicked within those 100 ms was followed by the delayed focus, and the editor stayed open as if the save had failed. The delayed focus is now skipped once a save has started.

The timer was also invisible to Behat's wait for pending JavaScript, which is how `feedback_card_reedit.feature` raced it: the scenario failed three times in a row on one loaded CI runner in v2.12.4 (and before, in the `deploy.sh` run of 23 September) while passing everywhere else. The earlier fix of a longer wait could not help, since the save had landed and the editor was simply focused. The timer is now tracked with `core/pending`.

## v2.12.4 (2026092801)

### Forum feedback banner missing inside a discussion

The "Your teacher has graded your forum participation" banner only appeared on the forum's list of discussions (`mod/forum/view.php`). A student who opened a discussion from elsewhere never passed through the list and could miss their feedback entirely. format_simple 1.1.3 makes this common: it opens a Q&A forum's only discussion straight from the course page.

The output hook now also acts on `mod/forum/discuss.php`, so the banner sits at the top of every discussion as well. The other scripts it loads there (the "View grades" override, the comments bubble, the teacher's grade button) find nothing to attach to on that page and do nothing. Other forum pages, such as the reply form, are still left alone. Covered by `tests/hook_callbacks_test.php` and `tests/behat/forum_feedback_banner.feature`, both of which fail on v2.12.3.

## v2.12.3 (2026092800)

### Quiz feedback page crashed for every student

The quiz branch of `view_feedback.php` merged `$gradinginfo` into its template without ever assigning it. v2.12.0 added the nine grading keys to that branch by mistake (they were meant for BigBlueButton), where the undefined variable only raised warnings. v2.12.1 moved them into the typed `feedback_data_helper::grading_template_data()`, which throws on the null: "Argument #1 ($gradinginfo) must be of type array, null given".

The quiz branch now calls `parse_grading_data()` like the others. Quizzes have no rubric or marking guide, so the page renders as it did before v2.12.0. The wiring test in `tests/feedback_data_helper_test.php` now also checks that every feedback view parses its own grading data, and fails on the v2.12.2 code.

## v2.12.2 (2026092301)

### Quiz extensions vanishing from the student's calendar

Saving or deleting a core quiz override rewrites the student's calendar events on that quiz, and the due date extension's event went with them. The combined overrides and extensions form does exactly that whenever an extension runs past the close date, since it pushes the close date out to match. The student's calendar and timeline then showed the class due date, not their extension.

After a core override is saved or deleted (the combined form, the override page, and `quiz_adapter::delete_user_override()`, which the delete and clear-all web services use), `quiz_adapter::refresh_duedate_calendar_events()` has quizaccess_duedate rebuild the quiz's due-date events. It needs quizaccess_duedate v2.0 and does nothing on older versions.

### Deleting a quiz override threw an error

`quiz_adapter::delete_user_override()` called core's `delete_overrides()` with a named argument, `overrideids`, that it does not have, so removing a student's quiz override from the grader, or clearing all their overrides, failed with "Unknown named parameter". It now calls `delete_overrides_by_id()`. The existing web service tests only covered assignments, so `test_core_override_changes_keep_duedate_event` now exercises the quiz path.

## v2.12.1 (2026092300)

### Marking guide missing from the student's BBB feedback view

v2.12.0 parsed the grading data in the BigBlueButton branch of `view_feedback.php` but never passed it to the template, so the rubric and marking guide reached the downloadable PDF and not the screen. The four branches end in identical lines, and the edit landed on the quiz one.

The nine template keys are now produced by `feedback_data_helper::grading_template_data()` and merged by every branch, so a branch that omits them is visible at a glance. `tests/feedback_data_helper_test.php` asserts each `render_from_template()` call site has its merge — reintroducing the defect fails that test.

## v2.12.0 (2026092200)

BigBlueButton graders were offered every session on the activity, students could not see the marking guide behind their grade, the feedback PDF is redesigned, and an ampersand in a course name reached the screen as `&amp;`.

### Sessions a student was never in

Attendance *is* the submission on BBB, so a session the student did not attend is not theirs to be marked on. The existing filter compares BBB recording ids against attendance ids; where a site's analytics callback reports a `recordid` matching no recording, nothing reconciles and every session stays visible.

- Group membership is now checked first. Under separate groups each recording carries the group it ran for, so a student outside that group could not have joined — decided from enrolment data rather than a roster BBB may never have sent.
- Ungrouped sessions, and any recording carrying the student's own feedback, are always kept.
- Under visible groups or no groups the pass stands down: a student may then join another group's meeting, so membership proves nothing.
- A student in no group keeps every session and is told why.
- The "ids do not reconcile" notice now fires whenever nothing matches, not only when the filter empties the list.

### Double-escaped course and activity names

`format_string()` escaped these values and the output layer escaped them again, so "Grief & Loss" reached the screen as "Grief &amp; Loss". They all flow into sinks that escape on their own — Mustache, `textContent`, `encodeURIComponent`, TCPDF's literal `Cell()` — so `format_string()` now runs with `'escape' => false` at each source.

Affects course short and full names, activity names across all four adapters, BBB session labels, forum subjects and discussion names, quiz criterion shortnames, and group names in the filter. Two cases where the entity reached data rather than a screen are also fixed: the impropriety report form's URL parameters, and the PDF header. Calls feeding raw HTML are unchanged.

### Marking guide in the student's feedback view

The BBB branch of `view_feedback.php` never parsed its grading data and rendered through a template with no rubric or guide markup, so students saw the overall feedback alone — the per-criterion scores and remarks reached them only in the PDF. The right-hand column now opens for a marking guide even with no written feedback.

A student reading their own BBB feedback also sees every session's figures at once, totals then one block per session, rather than the grader's one-at-a-time switcher.

### Feedback summary PDF

Shared by every adapter, so assignments, forums, quizzes and BBB sessions all open the same way.

- Score and overall feedback side by side: the grade as a donut on the left, the teacher's comments on the right.
- Site logo is drawn into the header band, taken from Moodle's own settings. SVG logos are skipped — TCPDF cannot read them.
- Engagement figures as dashboard tiles: an emphasised totals row, then one row per attended session, each carrying all six metrics.
- Font Awesome icons on each tile and section heading, converted from the TrueType original Moodle ships.
- Recording annotations listed as timestamped text. Previously the PDF dumped the grading pane's Bootstrap markup into TCPDF, which ignores the class hiding unselected sessions, so every session rendered at once under a player a PDF cannot play.
- Footer on every page, with a page number. It was painted once, before the annotation pages existed.
- "Graded on [[strftimedatefull]]" fixed — not a core string.

### Deployment

`deploy.sh` takes a list of Moodle installs rather than one. AMD is built on the first and shipped to the rest, so every install runs identical JavaScript.

### Coverage

`tests/adapter/bbb_adapter_test.php` gains twelve tests covering the group filter, the reconciliation notice, the feedback report and the student's stacked session tiles; `assign_adapter_test.php` adds two for the unescaped activity name. Two new files: `tests/pdf/feedback_summary_pdf_test.php` (grade bands, engagement, annotations, logo, page breaks) and `tests/feedback_data_helper_logo_test.php` (logo lookup order, SVG skip, unconfigured site).

## v2.11.1 (2026091000)

Overall feedback could be typed into an assignment that has nowhere to store it, and two attempt-handling slips could overwrite feedback on multi-attempt assignments.

### Assignments with "Feedback comments" disabled

The Overall Feedback editor was shown on every assignment, including ones whose **Feedback types** settings leave **Feedback comments** unticked. `mod_assign` discards feedback text for a disabled feedback type without complaint, so a teacher could write feedback, click save, see success, and lose it. On those assignments the editor is now replaced with a warning label: *Feedback comments are not enabled for this assessment*. Forums and quizzes are unaffected; they always store feedback.

The save itself is guarded as well. A grader page opened before the setting was changed still has the editor, so a save that carries feedback text to a comments-disabled assignment is now refused with an explanatory error before anything is written, and the text stays in the teacher's editor. A blank editor saves the grade as normal.

### Feedback on previous attempts

- **Viewing a previous attempt loaded the latest attempt's feedback into the editor.** `prepare_feedback_draft()` accepted an attempt number but never used it. Saving from that screen then wrote the latest attempt's text over the previous attempt's own feedback. The editor now loads the attempt on screen.
- **Re-saving a previous attempt overwrote the latest attempt's feedback.** After `mod_assign` stored the graded attempt, the follow-up step that moves embedded files out of the editor's draft area rewrote the comment of the *latest* attempt's grade instead. The manual-grade-override step on rubric and marking-guide saves had the same slip and could put the typed mark on the latest attempt. Both now target the attempt being graded.

### Coverage

`tests/adapter/assign_adapter_test.php` gains four tests: a feedback-carrying save is refused on a comments-disabled assignment (text and media-only feedback) with no grade written; blank feedback still saves the grade there without creating a comment row; re-saving a previous attempt leaves the latest attempt's feedback and grade intact; and the feedback draft loads the requested attempt's feedback. A new Behat feature, `feedback_comments_disabled.feature`, checks that the notice replaces the editor only when comments are disabled. `marking_keyboard_navigation.feature` now enables feedback comments on its assignment explicitly: core's assignment generator leaves them off unless asked, and that scenario types into the feedback editor. For the same reason, `tests/penalty_roundtrip_test.php` now enables feedback comments on its assignment: its web-service test sends feedback text, which was previously discarded silently and is now refused.

## v2.11.0 (2026082601)

Export, import, and clean up the comment library — as CSV, from either the admin moderation tool or a teacher's own library.

### CSV export and import

Every library view — a teacher's own library, one admin-inspected bucket, or the moderation page's full filtered list across every teacher — now has an **Export (CSV)** link, and a matching **Import** upload alongside it. The two shapes are deliberately minimal: `coursecode,shared,tags,content` for a single owner, with `ownerid,ownername` added at the front when the export spans more than one teacher. Tags are one cell, pipe-separated (`Grammar|Needs revision`).

Import is additive and idempotent, in the same spirit as the legacy importer added in v2.10.0: a row whose owner, course code and content already match an existing comment is skipped rather than duplicated, so re-running an import — after fixing a couple of bad rows, say — never doubles up what already succeeded. `content` is the only required column; a missing `tags` cell resolves against the owner's existing tags by name (case-insensitively) before creating a new one, and a missing `coursecode` cell falls back to whatever the import context implies — the current bucket when importing into one, or universal otherwise.

Two safety rules are enforced beneath the UI, not just by it: a teacher's own import always writes to their own library regardless of what an `ownerid` column in the file claims, and an admin importing without a chosen owner filter is required to either filter to one teacher first or supply that column — otherwise every unattributed row would default to `userid = 0`, the system-defaults bucket, which is almost never what "restore this backup" means.

### Bulk delete and duplicate cleanup

The bucket view (both admin and teacher) gains a **Delete selected** action beside the existing re-scope and reassign forms, reusing the same checkboxes — tick comments, then move, hand off, or remove them in one visit rather than three.

A new **Possible duplicate comments** section — on the moderation page (site-wide or filtered to one teacher) and on the teacher's own library — groups exact repeats: same owner, same course code, same content, most often left behind by a double-submit or a re-run import. Each group gets a one-click **Delete extra copies, keep oldest** button. Comments that happen to share wording across two different courses, or across two different teachers, are not flagged — that's normal reuse, not a duplicate.

### Coverage

`tests/manager/library_csv_test.php` (14 tests) covers both export shapes, the required-content-column and empty-file error paths, the skip-on-duplicate behaviour, tag reuse and creation, the owner-column override and its teacher-side lockout, an invalid ownerid being reported rather than silently defaulting, and forced-coursecode bucket imports. `tests/manager/library_audit_test.php` gains coverage for `delete_comments()` (including its owner scope) and `find_duplicate_comments()` (grouping, and that it does *not* group across course codes or across owners). Full suite: 605 tests, 1685 assertions, all passing.

## v2.10.0 (2026082500)

Comment libraries you can audit, and three defects that were quietly misfiling them.

### Comment libraries can now be moderated

A comment library entry is scoped by a free-text `coursecode` string, not a link to a course. Nothing at the database level keeps that string pointing anywhere real, and nothing in the interface showed an admin what had been stored — so an entry filed under the wrong code was indistinguishable from an entry that had never been saved. A teacher reporting "my library from last term is gone" could not be answered.

**Site administration → Plugins → Local plugins → Unified Grader → Moderate comment libraries** lists every library on the site, one row per owner per course code, and flags seven ways an entry can drift out of its owner's reach:

- **Owner missing** / **Owner deleted** — the `userid` points at no user row, or at a deleted one.
- **System default with a course code** — a `userid = 0` row is always written with an empty code, so a code here means a scoped comment was written into the system bucket.
- **Sentinel stored as code** — the modal's sidebar buckets (`__system__`, `__universal__`) stored verbatim as a course code.
- **Padded code** — leading or trailing whitespace, which splits one bucket into two and is invisible in the interface. Codes are shown in `[brackets]` throughout so this is legible at a glance.
- **Code matches no course** — no course on the site currently produces this code.
- **Competing spellings of one code** — two codes that differ only by case or padding. Detected site-wide, so filtering to one teacher still reports a clash with another teacher's spelling.

The **Matching courses** column shows which courses each stored code resolves to today, which is also how an over-truncating extraction regex becomes visible: two different courses collapsing onto one code is a merged library, not a coincidence.

From a bucket you can re-scope comments to another code (blank makes them universal), or reassign them to another owner — for rescuing a library after a duplicate account is merged. Both are logged as a `library_repaired` event. Orphaned tag mappings can be purged, and rows still stranded in the pre-v2 `local_unifiedgrader_comments` table are listed with the code they would be filed under, and can be imported. That import is additive and idempotent: it skips inserting anything the owner already has, unlike the upgrade-time migration, which opens by deleting the entire v2 table. Each row's legacy source is removed only once its copy is confirmed present in the current library — whether newly inserted or already there — so a successful import clears the backlog instead of reporting the same rows on every subsequent visit.

Gated on a new `local/unifiedgrader:moderatelibraries` capability, granted to managers.

### Teachers can re-file their own comments

**Organise library**, linked from the comment library sidebar, shows a teacher their own comments grouped by course code and lets them move a selection to another code without involving an admin. Only codes for courses they are enrolled in are offered — moving comments into a course they do not teach would only lose them again. Every operation is scoped to the teacher's own rows at the manager level.

### Three defects that misfiled comments

- **An extraction regex with an empty capture group silently made comments universal.** `course_code_helper::extract_code()` returned `$matches[1] ?? $matches[0]`, and `??` only falls back on null — not on a group that participated in the match without capturing anything. Such a group yields `''`, and an empty course code is precisely what `get_comments()` treats as "visible in all my courses". Every comment a teacher saved while grading an affected course went into their universal bucket, where they used it all term without noticing, and where it does not appear under the course next term. Extraction now tests for emptiness rather than null, and never returns an empty code for a shortname that has one.
- **The library sidebar's `__system__` bucket leaked into saved comments.** `__universal__` was handled explicitly when computing a new comment's course code; `__system__` fell through and was stored verbatim, producing a phantom course bucket only that teacher could see. Both sentinels are now filtered.
- **A delete attempted by a non-owner stripped a comment's tags.** `delete_comment()` scoped the comment delete by `userid` but deleted the tag mappings unconditionally, and `delete_records()` reports success whether or not it matched a row. The comment survived, untagged and much harder to find. Both `delete_comment()` and `delete_system_comment()` now confirm the scoped row exists before touching the mappings.

### Every language pack brought up to date

All twelve translations — Afrikaans, German, Greek, Spanish, French, Hebrew, Italian, Portuguese, Russian, Swahili, Xhosa and Zulu — were last touched for content in v2.6.6 (2 July). Seven releases of English strings had landed since, so each pack was short **350 strings** and still carried **29 keys that no longer exist** in English: leftovers from the marking-panel and comment-library rewrites (`clib_edit_comment`, `markingpanel`, `status_needsgrading`, `download_annotated_pdf` and the like).

Every pack is now at full parity with English — 682 strings each, obsolete keys removed, files re-sorted by key. The newly translated material covers the BigBlueButton engagement and attendance work, forum post-rating and post views, submission translation and segment-anchored marking, the dual file view, integrity referrals, ranged rubrics, the comment-library approval queue, and this release's own moderation pages.

Checked mechanically across all twelve, not just spot-read: key parity against English in both directions, no duplicate keys, sort order, `php -l` on every file, and — the check that matters most for a translation pass — **placeholder parity**, so no `{$a}`, `{$a->count}`, `{parsed}` or `{recordings}` token was dropped, renamed or mistranslated in any of the 4,200 new strings. Every one of those checks passes on every language.

Terms that are product names or configuration values are deliberately left in English: BigBlueButton, Moodle, `local_nida`, `unoconv`, and the literal setting name "Register live sessions" that admins have to find in the BigBlueButton plugin's own settings page.

### Coverage

`tests/manager/library_audit_test.php` covers each anomaly flag, site-wide variant detection surviving a user filter, owner-scoped re-scoping, reassignment refusing an invalid owner, orphan purging leaving live mappings alone, and legacy import being idempotent. `tests/helper/course_code_helper_test.php` gains regressions for the empty capture group and for a regex that can match zero characters. `tests/manager/comment_library_manager_test.php` strengthens the existing wrong-owner delete test to assert the comment's *tags* survive as well — the original only checked the comment itself, which is why the mapping bug went unnoticed. Full suite: 583 tests, 1620 assertions, all passing.

## v2.9.5 (2026082400)
- Fix ranged rubrics (`gradingform_rubric_ranges`) showing an empty criteria modal and an empty marking pane on assignments, forums and BigBlueButton
- Fix marks recorded against a ranged rubric disappearing when the student was reopened
- Fix ranged criteria scoring zero unless a score was typed; a criterion without a score now counts as incomplete
- Add a colour-banded slider with a number box for marking ranged criteria
- Add a PDF download of the assessment criteria for students (ranged rubrics only)
- Show ranged rubrics in the student feedback view and the feedback PDF
- Fix BigBlueButton AI mark suggestions not resolving to a ranged rubric level
- Add test coverage for ranged rubrics

## v2.9.4 (2026081900)
- **Action required:** run "Refresh attendance" once per BigBlueButton activity after upgrading, or use `php cli/refresh_bbb_engagement.php --courseid=N` (or `--all`)
- Show only the sessions a student attended in the session list; a student who attended none shows "Did not attend"
- Keep every session listed, with a notice, when attendance data is missing or cannot be matched to a recording
- Fix the player opening on the wrong recording; it now opens on the session the student attended longest
- Read attendance from BigBlueButton's Learning Dashboard data, matched by Moodle user id and back-filled for past recordings
- Fix recordings refusing to play when the teacher's active group differed from the recording's group
- Add `cli/refresh_bbb_engagement.php` to back-fill attendance for an activity, a course or the whole site
- Remove Activity Score, which the Learning Dashboard does not provide

## v2.9.3 (2026081700)
- Fix the quiz grade showing only the manual question marks while a student is being marked
- Fix the displayed quiz grade moving by the wrong amount when the raw total differs from the maximum grade
- Add test coverage for the quiz grade display

## v2.9.2 (2026081201)
- First public release since v2.7.2; versions 2.8.0, 2.8.1, 2.9.0 and 2.9.1 were never released
- **Action required:** penalties recorded after a grade was issued may not have reached the gradebook. Compare the grader's "final grade after penalties" with the gradebook and re-save affected grades through the Unified Grader
- Add support for rating-graded forums, with a Post ratings section in place of the grade box
- Add rating dropdowns on the student's posts in the In context and Thread views
- Add a Post view switch for forums: All posts, In context and Thread
- Show students their post ratings and the aggregation method in the feedback view
- Add dual file view: two submissions stacked with a draggable divider and a file chooser per pane
- Restrict marking to the focused pane in dual file view
- Offer online text, including embedded recordings, as a pane source
- Add file type icons to the submission tabs
- Add free-floating tick and cross stamps
- Fix penalties saved or deleted in the grader not syncing to the gradebook
- Fix removing a student's last penalty not restoring the mark
- Store the raw mark for assignments and BigBlueButton sessions, applying penalties only on the way to the gradebook
- Fix manual penalties not applying to quizzes
- Fix manual penalties not applying to BigBlueButton sessions, including those marked through `bbbext_advgrd`
- Fix `-` not clearing an assignment grade
- Fix `--` not clearing a gradebook override on quizzes and BigBlueButton activities
- Fix the quiz total not updating in the grade field after a save
- Fix quiz marks not reaching the gradebook when a late penalty is in force
- Fix manual penalties being applied to scale-graded forums as if they were out of 100
- Fix scale grades being shown to students as a fraction and percentage
- Fix undo for comment markers, highlight boxes and stamps
- Fix the toolbar delete button not removing a selected shape
- Fix a plagiarism plugin's JavaScript being printed into the feedback PDF
- Hide penalties, extensions and advanced grading on rated forums
- Add web services `get_post_ratings`, `save_post_rating` and `get_post_context`
- New interface strings are English-only
- Fix the Behat suite, which had never run and reported success on every build
- Add a grade and penalty test matrix across all four activity types
- Add test coverage for forum ratings, forum context, override reset and PDF text

## v2.7.2 (2026073100)
- Fix a late penalty being deducted twice when a grade is recalculated after an extension or override; re-save affected grades to correct them
- Reword the tick and cross annotation tooltips to "Good point (tick)" and "Needs correction (cross)"

## v2.7.1 (2026073000)
- Fix the student feedback view dropping annotations when the displayed attempt is not the latest one
- Fix the attempt selector staying hidden for teachers when a student has more than one attempt

## v2.7.0 (2026072300)
- Consolidates the unreleased 2.6.7–2.6.18 development builds
- Add submission translation for graders through `local_nida`, with side-by-side mode and a submission-language badge
- Add over-the-page segment comments anchored to a phrase of the submission
- Add an English dub of recorder audio embedded in a submission
- Add a client-side sanitiser for translated content
- Overhaul PDF and online-text annotation: text-anchored highlight and strikethrough, unified toolbar, pen tool, marquee select/move, unified undo/redo
- Show non-scannable files (audio, video, images, archives) as "Not applicable" instead of a failed plagiarism scan
- Add per-comment marker colours, with a palette that meets WCAG AA
- Show students the grader's over-the-page comments in place on the PDF feedback view
- Fix text-anchored PDF marks landing to the left of the selection, and the caret being invisible
- Redesign the document-info popout as one grouped panel shared by the PDF and online-text views
- Fix students in separate-groups BigBlueButton activities being unable to see their feedback
- Fix BigBlueButton recording selection when an activity has several recordings and the `bbbext_advgrd` overlay is active
- Fix the translation language selector pre-selecting the wrong language when the detected one is not a course option
- Update the in-product help for the 2.7.0 integrations

## v2.6.6 (2026070100)
- Fix quiz manual grading showing the previous student's mark and a spurious "Overridden" badge
- Rename the quiz grading pane heading to "Manually marked questions"

## v2.6.5 (2026061701)
- Fix a non-numeric marking-guide score crashing the save; it is now rejected with a clear error

## v2.6.4 (2026061700)
- Fix rubric grades not scaling to the activity's maximum grade; re-save affected rubric-graded students

## v2.6.3 (2026061405)
- Fix the first student's mark showing blank when re-opening a graded assignment
- Fix the marking panel showing a blank or another student's grade when loads overlap
- Fix re-edited overall feedback not collapsing back to the saved card
- Fix overall grades failing to save when a late penalty is in place
- Fix arrow keys switching student while the teacher is editing
- Update the in-product help for everything shipped since v2.4.5

## v2.6.2 (2026061400)
- Render the `bbbext_advgrd` annotation overlay in place of the BigBlueButton recording iframe in the preview and student feedback panes

## v2.6.1 (2026061301)
- Fix the overall-feedback editor clipping its text when resized
- Consolidate the academic-integrity controls into a single button hosted with the plagiarism report
- Fix annotation tools becoming unresponsive after a zoom

## v2.6.0 (2026061200)
- Add academic-integrity referrals: flag a submission for review and mark it resolved from the marking panel
- Add capability `local/unifiedgrader:refer`

## v2.5.1 (2026-06-05)
- Fix annotation tools getting stuck after a marking-pane update
- Add text-selection Highlight text and Strikethrough annotation tools

## v2.5.0 (2026-06-03)
- Fix intermittent missing student name in the marking-panel header
- Security: fix stored XSS in submission comments
- Security: filter rubric and marking-guide HTML through `format_text()`
- Security: disable PDF.js eval-based code paths
- Security: verify SHA-256 hashes of bundled third-party libraries on deploy
- Security: document that Fabric.js CVE-2026-27013 is not reachable, as `toSVG()` is never called
- Performance: batch the per-participant queries in the assignment adapter
- Performance: batch tag and proposal lookups in the comment library
- Performance: batch author lookups for teacher notes
- Fix an annotation save race with a unique index and a transaction

## v2.4.7 (2026-05-26)
- Release the PHP session lock in all web service handlers after capability checks
- Log a debugging warning when a grade save takes longer than five seconds
- Add a "Send mail" shortcut to the profile popout when `local_satsmail` is installed
- Fix the participant list showing "Graded" for a submission reverted to draft

## v2.4.6 (2026-05-26)
- Validate marking-guide criterion scores against their maximum
- Scope submission-comment notifications to teachers in the student's group on group-mode activities
- Fix the grading panel not refreshing when a different attempt is selected
- Fix the late submission badge and late-penalty badge disappearing when a draft was started on time but submitted late
- Fix the late-penalty deduction not showing in the grade card
- Add a canonical `submittedat` field to submission data for all adapters

## v2.4.5 (2026-05-20)
- Quiz "Post grades" now toggles only the marks, maximum marks and overall feedback review options ([#13](https://github.com/SATS-Seminary/moodle-local_unifiedgrader/issues/13))
- Show per-attachment plagiarism reports for graded forums in the marking panel
- Add a `feedback_viewed` event fired when a student views feedback
- Fix the submission-comments chat bubble appearing on announcements forums

## v2.4.4 (2026-05-18)
- Fix floating-point artifacts in the marking-guide total
- Round totals to the gradebook's decimal places, with a minimum of two

## v2.4.3 (2026-05-18)
- Typing `-` in the grade input clears the grade to "no grade"
- Typing `--` performs a deliberate reset: clears the grade, removes orphan submission rows and lifts gradebook overrides
- Apply `-` and `--` to forums
- Add a `reset` parameter to the `save_grade` web service
- Add a Retry conversion button to the conversion-failed overlay

## v2.4.1 (2026-05-12)
- Default the group filter to the teacher's own groups
- Persist the group filter selection per activity

## v2.4.0 (2026-05-12)
- Scope comment library pills to system defaults and tags used in the current course
- Add universal comments, visible across all of a teacher's courses
- Add a search box to the manage-library modal
- Add an admin tool to manage system-default tags and comments, with capability `local/unifiedgrader:managesystemdefaults`
- Add a proposal workflow for teachers to suggest system-default comments
- Add the setting "Require approval for system-default suggestions"
- Fix a manual grade override not autosaving on focus-out
- Fix rubric and marking-guide edits overwriting a manually entered grade
- Add an Override indicator with a "Reset to rubric total" link
- Cap the manual grade input at the activity's maximum mark

## v2.3.2 (2026-05-11)
- Fix rubric and marking-guide fillings failing to save when the gradebook entry is overridden
- Fix an auto-save race that could wipe marks, remarks and the grade
- Fix marking-guide scores saving as 0 in locales that use a decimal comma
- Switch score and grade inputs to text inputs that accept comma or period decimals
- Fix a `TypeError` in the comment library popout when it is not yet built

## v2.3.0 (2026-05-09)
- Add a BigBlueButton activity adapter with inline recording playback and a recording switcher
- Add an Activity Points card with per-session and aggregate engagement metrics
- Add a "View full analytics" button and a "Did not attend" badge
- Add the `enable_bigbluebuttonbn` admin setting (default off)
- Add `bbbext_advgrd` integration for rubrics and marking guides on BigBlueButton activities
- Add an engagement metric fallback that parses BBB's statistics page, with web service `refresh_bbb_engagement`
- Add an in-product help page, opened from the `?` icon in the grading toolbar
- Fix auto-save races that could overwrite fields being edited, on all activity types

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
