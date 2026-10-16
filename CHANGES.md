# Changelog

## v3.0.0 (2026101600)

### Penalties explained on the student's feedback

A student's feedback page and feedback PDF showed the mark as the marker typed it, with each penalty as a bare "-10% Late" chip beside it. Nothing said what the chips were taken from or what was left, and the figure on the page did not match the gradebook.

- **Feedback page:** a Penalties card sits under the overall feedback, on assignments, forums, quizzes and BigBlueButton. It lists the mark awarded, each penalty with its reason and the marks it cost, and the final grade, with a note that penalties are percentages of the maximum grade. The chips are replaced by one "Penalties: -30%" label that jumps to the card.
- **Headline grade:** with penalties, the grade at the top of the page is now the final grade, the one in the gradebook.
- **Feedback PDF:** the grade ring and the fraction under it show the final grade, and the same breakdown is listed beneath them in place of the row of chips. Each penalty is drawn on the ring as its own segment, carrying on from where the final grade stops, in a muted colour and a narrower band so it cannot be read as marks that count. The list is the ring's legend: every row opens with the colour of its segment, and "Mark awarded" and "Final grade" are in bold.
- **Reasons:** a late penalty reads "Late submission" with how late beneath it; a word count penalty reads "Word count"; a marker's own penalty shows the label they typed, such as "Plagiarism".
- **Edge cases:** the final grade does not go below zero, and the total shows what was actually deducted. A scale grade lists the penalties without the arithmetic. During a Friction Feedback walk the card stays hidden with the mark.
- **The PDF button is on every feedback page.** A quiz showed "Download feedback PDF" only when the marker had written overall feedback, so a quiz marked question by question had no PDF at all; an assignment showed it only with a grade. Assignments, forums, quizzes and BigBlueButton now all show it whenever the feedback is released.
- The arithmetic is in one place, `feedback_data_helper::penalty_breakdown()`, used by the page and the PDF. Seven new strings, in all 13 language packs.

### Forum extensions past the cut-off date

A forum's cut-off date applies to everyone, and Moodle has no per-student override for it, so an extension past the cut-off only moved the date a late penalty was counted from; the student still could not post.

- **The student can post until their extension ends.** Moodle has a capability for posting after a forum's cut-off (`mod/forum:canoverridecutoff`), held by teachers. Unified Grader now creates a role holding only that, *Forum extension (Unified Grader)*, and gives it to the student in that one forum when their extension runs past the cut-off. Nobody else is affected.
- **It ends with the extension.** A new scheduled task, `expire_forum_extensions`, runs every 15 minutes and takes the role away once the extension is over. Removing the extension, or bringing it back before the cut-off, takes it away at once. If a teacher moves the cut-off earlier than an extension already granted, the task gives the student the role.
- **Made in the plugin's name.** The role cannot be assigned by hand, and the assignments are marked as Unified Grader's, so they are never confused with roles a person gave. The upgrade creates the role and gives it to any student whose existing extension already passes the cut-off.
- **Rated forums get Dates & extensions back.** It was hidden on forums graded by post ratings because they take no late penalty. It now appears there too, for the cut-off, and the dialogue no longer predicts a late penalty for them.
- The cut-off row and status line in the dialogue now say the student can post until their extension ends, in all 13 language packs.

### Penalties on forums graded by post ratings

Penalties were switched off for every forum graded by rating its posts, because Moodle works the grade out from the ratings and rewrites it each time one changes. They now apply to the rated forums where a penalty means something. Moodle 5.3 only.

- **Which forums.** The ratings must be points (not a scale such as stars or words), and the aggregate type must be average or sum. A count only tallies participation, and with maximum or minimum a single post decides the grade, so those take no penalty and the Penalties button stays hidden. The rule is `forum_rating_helper::penalties_apply()`, and the grader gets the answer as `ratingpenaltieseligible` in the activity info.
- **Same arithmetic as everywhere.** Lateness is measured from the student's first post against their due date or extension. Late, word count and other penalties are percentages of the points maximum, summed, and taken off the combined rating, never below zero. The marking panel shows the badges and the final grade beside the Post ratings card.
- **Kept in the gradebook.** The combined rating stays as Moodle computed it and the deduction is recorded beside it. Moodle clears that deduction every time a post is rated or re-rated, so it is put back after each rating change. The mark awarded shown to the teacher and the student is the combined rating; the final grade matches the gradebook.
- **Settings changes.** Changing the aggregate type or the scale so a forum no longer qualifies removes its deductions, and its late and other penalty rows stop being shown. Changing it back applies them again. The Dates & extensions dialogue previews the late penalty on a qualifying forum, and on one that does not, says why there is none.
- **Opt-in.** A rated forum takes late penalties only once a teacher ticks *Apply late penalties* in its settings; it is off by default, so courses already running and past courses gain no penalties they never had. Forums graded as a whole keep it on by default, as before. One new string, in all 13 language packs; the help explains which forums qualify.

### The marker's marks on the pages of the feedback PDF

On the web a student sees their document with the marker's marks on it: highlights, ticks, crosses, queries, strike-outs, and comments as numbered pins beside the text. None of that reached the feedback PDF, which carried the summary and the bare document.

- **On the pages:** every text-anchored mark is now drawn on the submission's pages in the PDF, on the words it was put on, in the same colours as the web view. A comment is a numbered pin at the start of its phrase.
- **Comment cards beside the page:** as on the web, each page has a margin on its right, and every comment is set there as a card with its number, the marker's name and what they wrote, joined to its pin by a dotted line. A card starts level with its pin; where pins are close the cards stack, and a crowded page gets smaller type so all of them fit. The marker's name is left off where the assignment hides marker identities.
- **In the summary:** only a comment that could not be shown beside its words is listed there, under "Comments on your submission", with its number, page and the phrase it is about: a comment on online text, or on a site that cannot draw on the pages. The overall feedback and the rubric or marking guide are as before.
- **How a mark finds its place:** a mark is stored against a phrase of the document's text, not a position. Ghostscript reports where every character sits, the phrase is found in that (the occurrence nearest where the marker put it, when it occurs more than once), and the mark is drawn there with the page importer Moodle's own PDF annotator uses (`pdf\submission_marks_pdf`, `pdf\page_importer`).
- **Checked against the browser:** on a five-page test document (two columns of small type, a landscape page, US Letter stored rotated, A5, ligatures, accents, a phrase repeated on one page) and a Word submission, all twenty marks land within 2.1 points (0.7 mm) of where the browser's viewer lays the same phrase out.
- **Two things that would have broken it:** a page stored rotated cannot be measured letter by letter, so the pages are measured on the same plain copy the marks are drawn on; and rewriting a PDF can lose the text behind a ligature ("tt", "Th"), so the original's text is used wherever the copy lost any.
- **Best effort:** this needs Ghostscript (the path Moodle already uses for Annotate PDF). Without it the document is left as it was, and the comments are listed in the summary instead. Word submissions use the PDF Moodle converted them to.

### One feedback PDF for every activity

- **Course name in the header:** a new setting on the Grading page, "Course name in the header" (`feedbackpdf_coursename`), chooses between the course full name (the default) and the short name.
- **Generated date:** the footer of every page now reads "Generated by Unified Grader on" followed by the date and time, beside the date the work was graded. A saved PDF can outlive a change to the grade, and this says when the copy was made.
- **Same button everywhere:** see the PDF button note under the penalties entry above. Assignments, forums, quizzes and BigBlueButton share one generator, header, score ring and footer; only the pages that follow differ (the annotated submission, the posts, the attempt, or the session figures).

### Documentation

The built-in help page (`help.php`) is rewritten for 3.0.0 on Moodle 5.3.

- **Four parts:** teachers and markers, students, administrators, and developers, with a section navigation that marks where you are, and a start area that routes each reader.
- **Screenshots:** 51 real screenshots in `pix/help` (about 3 MB), each with alt text and a caption, opening enlarged on click. The empty screenshot placeholders are gone.
- **3.0 features documented:** posting grades, Friction Feedback, late penalties, Dates & extensions, academic integrity referral, proctoring review priority and the settings index. Every setting and capability is listed with its key, default and meaning.
- **Corrections:** the annotation tool list, the entry points, the status menu, blind marking (supported), and the `viewall` capability (it does not open the grader). `quizaccess_duedate` is described as legacy.
- **Troubleshooting** is a list of collapsible entries, including "Conversion fails although the conversion server is up" (the converter's port must be in Moodle's allowed ports).
- Existing anchors are kept, so the grader's help button and older links still land.
- The description of "Render online text as PDF" no longer names converter products, in all 13 language packs.
- `README.md` brought up to 3.0.

### Proctoring review priority on quizzes

On a proctored quiz, each student in the list carries a red, yellow or green marker for their review priority, and the marking panel has a Proctoring card with the findings behind it and a link to the evidence. Four services are supported, each behind a provider class (`proctoring\provider\*`), so the marker, the card, the web service and the capability are the same for all.

Every service is off by default. An administrator ticks the ones the institution uses under "Proctoring services to show"; a service that is not ticked is never contacted and nothing about it is shown. Only users with the new `local/unifiedgrader:viewproctoring` capability (editing teachers and managers by default) see any of it.

| Service | Read from | The colour is |
|---|---|---|
| Quilgo (`quizaccess_quilgo`) | The plugin's report table; reports it has not stored yet are fetched through its own API client | Quilgo's own confidence level: high is green, moderate yellow, low red |
| ProctorLink (`quizaccess_quizproctoring`) | The plugin's tables | Ours, from the kind of warning |
| AutoProctor (`quizaccess_autoproctor`) | AutoProctor's REST API, with the credentials its plugin holds | Ours, from its trust score against two site thresholds (80% and 50% by default) |
| SMOWL (`block_smowl`) | SMOWL's Results API, with the entity name and key saved in the SMOWL block | Ours, from the kind of alarm |

- **Which service proctors a quiz is detected.** The three access rule plugins each record that they are on for a quiz, so there is nothing to set per quiz. For SMOWL two things must hold: the course has the SMOWL block (`block_smowl`), and the quiz is switched on in the SMOWL panel, which SMOWL's API is asked about. A course without the block is never asked about. In a course with it, a quiz that is not switched on shows "SMOWL is not active on this quiz", since a teacher cannot otherwise tell without opening the panel.
- **"Ours, from the kind"** (`proctoring\rule`): any finding in a high-priority category is red, any other is yellow, none is green. It follows the kind of finding, not the number. The categories are site settings per service, and the colours can differ from what the service's own report suggests.
- **Per attempt where the service is.** Quilgo, ProctorLink and AutoProctor keep a result per attempt: the card follows the attempt selector, and the student's marker is their worst attempt. SMOWL keeps one result per student per quiz.
- **Still under review.** Green is only ever a finished verdict. While any evidence is waiting on the service (SMOWL camera validation, a Quilgo or AutoProctor report not ready), a student with nothing against them shows as "Awaiting review", never as low priority. An alarm already reported counts at once: the marker is red or yellow straight away, hollow to show that more may follow.
- **Links.** The marker and the card open the service's own report in a new tab: the student's attempts in ProctorLink, the attempt in AutoProctor, the quiz's report list in Quilgo, the SMOWL block's panel for the course. A link shows only to a user who holds that plugin's own report capability.
- **Cost.** Answers from SMOWL, AutoProctor and Quilgo's API are kept for fifteen minutes (`proctoring` cache); the card's refresh button asks again. ProctorLink is read from its tables each time. The grader draws first and asks afterwards, so an unreachable service does not hold up marking.
- **Other plugins' data is only read.** A provider checks that the plugin and its tables exist before reading, and never writes to them.

Covered by `tests/proctoring/`. The access rule plugins are not installed on a test site, so their tables are stood in for and their APIs faked: none of the four has yet been checked against the live service. Checked against the live services so far: SMOWL's authentication, request format and "quiz not switched on" answer, and AutoProctor's authentication, request format and "unknown attempt" answer. Also seen with real data: Quilgo reports and its top-up call, an AutoProctor trust score, ProctorLink warnings, and a SMOWL result filed under the quiz ID and the Moodle user ID. Still to see: a SMOWL result after SmowlTech has finished validating it.

### Settings grouped into pages

The single settings page had grown long. "Unified Grader" under Local plugins now opens an index of tiles, one per group of settings: General (which activities open in the grader), Grading (display and marking options, posting quiz grades, late penalties), Comment library (its settings, with buttons to the two library tools), Friction feedback, and Academic integrity (plagiarism scanning, the impropriety report form, proctoring).

- Each page keeps its own address, and General keeps the one the single page had. No setting was renamed, so saved values carry over.
- The pages sit in a hidden admin category, so Moodle no longer renders them all on one category page. Every page links back to the index.
- Academic integrity opens with a note on plagiarism scanning: whether plagiarism plugins are enabled on the site, and where Moodle configures them. The Unified Grader has no plagiarism settings of its own.
- A proctoring service's own settings appear only once it is ticked and saved.

### Translations

All twelve language packs (Afrikaans, German, Greek, Spanish, French, Hebrew, Italian, Portuguese, Russian, Swahili, Xhosa and Zulu) are back at parity with English: 218 strings added to each, covering everything new in 3.0.0. Placeholders and embedded links were checked against English in every pack. Product names (Quilgo, ProctorLink, AutoProctor, SMOWL, Friction Feedback, Unified Grader) are left in English.

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
- Replace the plain textarea for comments on manually marked quiz questions with the TinyMCE editor, including images and recorded audio
- Show images and audio from question comments in the attempt preview
- Convert line breaks in comments saved from the old textarea so they display
- Keep the plain textarea for teachers whose preferred editor is not TinyMCE

## v2.12.5 (2026092802)
- Fix re-edited overall feedback sometimes staying open after a save

## v2.12.4 (2026092801)
- Show the forum feedback banner inside a discussion as well as on the forum's list of discussions

## v2.12.3 (2026092800)
- Fix the quiz feedback page crashing for every student

## v2.12.2 (2026092301)
- Fix a quiz due date extension vanishing from the student's calendar when a core override is saved or deleted (needs `quizaccess_duedate` v2.0)
- Fix an error when removing a student's quiz override, or clearing all their overrides

## v2.12.1 (2026092300)
- Fix the marking guide missing from the student's BigBlueButton feedback view

## v2.12.0 (2026092200)
- Stop offering BigBlueButton sessions a student could not have attended, using group membership under separate groups
- Fix course, activity, forum and group names showing `&amp;` where the name contains an ampersand
- Show the rubric or marking guide in the student's BigBlueButton feedback view, with every session's engagement figures at once
- Redesign the feedback summary PDF: grade donut beside the feedback, site logo, engagement tiles, icons, and a footer with page numbers on every page
- List recording annotations in the PDF as timestamped text
- Let `deploy.sh` deploy to several Moodle installs, building the JavaScript once
- Add test coverage for the BigBlueButton group filter, the feedback PDF and logo lookup

## v2.11.1 (2026091000)
- Replace the Overall Feedback editor with a notice on assignments whose "Feedback comments" feedback type is disabled, and refuse a save that carries feedback there
- Fix a previous attempt loading the latest attempt's feedback into the editor
- Fix re-saving a previous attempt overwriting the latest attempt's feedback or mark
- Add test coverage for both

## v2.11.0 (2026082601)
- Add CSV export and import for comment libraries, for a teacher's own library and for the admin moderation tool; imports skip comments that already exist
- Add "Delete selected" to the library bucket view
- Add a "Possible duplicate comments" section with one-click removal of extra copies
- Add test coverage for CSV export, import and duplicate detection

## v2.10.0 (2026082500)
- Add "Moderate comment libraries" for administrators: lists every library by owner and course code, flags entries that have drifted out of their owner's reach, and can re-scope or reassign them
- Add capability `local/unifiedgrader:moderatelibraries`
- Add "Organise library" so teachers can move their own comments between course codes
- Add an import for comments still stranded in the pre-v2 table
- Fix a course code pattern with an empty capture group silently making comments universal
- Fix the library sidebar's system bucket being saved as a course code
- Fix a delete attempted by a non-owner stripping a comment's tags
- Bring all twelve translations up to date with English
- Add test coverage for library moderation and course code extraction

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
