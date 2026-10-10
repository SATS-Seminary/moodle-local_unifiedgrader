# Unified Grader

One marking screen for Moodle assignments, forums, quizzes and BigBlueButton sessions: the student's work on the left, the grade and feedback on the right. Grades go to the normal Moodle gradebook.

Release 3.0.0. Developed and documented on **Moodle 5.3**.

Full documentation ships with the plugin: open the **?** button in the grader, or go to `/local/unifiedgrader/help.php`. It has separate parts for teachers, students, administrators and developers, with screenshots.

## What it does

### One grading screen
- Submission preview beside the marking panel, with split, preview-only, grading-only and stacked dual-file layouts
- Student list with search, filters (submitted, not submitted, graded, ungraded, late), sorting and group selection
- Assignments, whole-forum and rated forums, manually marked quiz questions, and BigBlueButton recordings with engagement figures
- Follows assignment settings such as blind marking, group submissions, marking workflow and multiple attempts

### Annotation
- PDFs and images open with annotation tools: comments, text and area highlights, strikethrough, tick, cross and query marks that attach to a phrase, shapes, a pen, five colours, undo and redo
- Word and other office files, and online text, are shown as PDFs through a document converter enabled in Moodle
- Students see the marked document with margin comments and can download it as a PDF

### Grades and feedback
- Numeric and scale grades, rubrics and marking guides, with an optional manual override
- Rich-text feedback with images and recorded audio or video, and feedback files
- Comment library with tags, course codes, shared comments and site-wide defaults
- Private teacher notes, and a message thread with the student on each piece of work
- Auto-save, with an offline queue and recovery of unsaved changes

### New in 3.0
- **Post grades** for one student, a group or the whole class, or on a date. Quiz grades can be posted too (off by default)
- **Friction Feedback**: a posted mark stays hidden until the student has read each part of the feedback
- **Late penalties** applied the same way to assignments, forums and quizzes, from Moodle's grade penalty rules, with one switch per activity (Moodle 5.3)
- **Dates & extensions**: every date and limit for one student in one dialogue, with one-click extensions and a penalty preview
- **Academic integrity**: refer a submission for review, optionally opening your institution's report form with the details filled in
- **Proctoring review priority** on quizzes, read from Quilgo, ProctorLink, AutoProctor or SMOWL
- Settings grouped into five pages behind a tile index

See [CHANGES.md](CHANGES.md) for the full history.

## Requirements

- **Moodle 5.3** is the version this release is developed, tested and documented on.
- The plugin still installs on Moodle 5.0 (`requires` 2025041400). There, late penalties and quiz due dates behave as they did in release 2.13.
- A PHP version supported by your Moodle release.
- Moodle's cron, for the scheduled tasks.

## Optional integrations

None of these is required. Each is detected when needed, and its part of the interface is hidden when it is absent.

| For | You need |
|-----|----------|
| Annotating Word files and online text | A document converter enabled in Moodle |
| Automatic late penalties | Moodle's grade penalty rules (due date rule) |
| Plagiarism results in the grader | Any Moodle plagiarism plugin |
| Proctoring review priority | The Moodle plugin of Quilgo, ProctorLink, AutoProctor or SMOWL |
| BigBlueButton engagement figures | Session summaries enabled on the BigBlueButton plugin and server |
| Rubrics on BigBlueButton activities | `bbbext_advgrd` |
| Recorded audio and video feedback | A recorder plugin for the TinyMCE editor |
| Portfolio submissions | The Byblos assignment submission plugin |
| Translated submissions and feedback | `local_nida` |

`quizaccess_duedate` is legacy. Moodle 5.3 has the quiz due date in core, and the plugin ignores the access rule there. Before uninstalling it on an upgraded site, let the `migrate_penalties` task run once (or run `php local/unifiedgrader/cli/migrate_penalties.php`).

## Installation

1. Extract the plugin to `local/unifiedgrader` in your Moodle code (`public/local/unifiedgrader` on Moodle 5.3).
2. Log in as an administrator and complete the installation from the notifications page.
3. Open **Site administration > Plugins > Local plugins > Unified Grader** and choose a settings page from the index.

Only assignment grading is on by default. Forums, quizzes and BigBlueButton are switched on under **General**.

## Usage

A **Unified Grader** item appears in the navigation of supported activities, and the activity's **Grade** button opens the grader, for users who hold `local/unifiedgrader:grade` (editing teachers and managers by default). Non-editing teachers need that capability granted before they can mark.

Students see a banner on the activity page, such as **View Annotated Feedback**, once their grade is posted.

## Settings

The settings index has five pages. The built-in help lists every setting with its key, default and meaning.

| Page | Settings |
|------|----------|
| General | Which activities open in the grader (`enable_assign`, `enable_forum`, `enable_quiz`, `enable_bigbluebuttonbn`), and `enable_submission_comments` |
| Grading | `onlinetext_as_pdf`, `allow_manual_grade_override`, `enable_quiz_post_grades`, `extensionpresets`, `quizgenuineattemptpct` |
| Comment library | `require_systemdefault_approval`, `coursecode_regex`, and the library management and moderation tools |
| Friction feedback | `friction_enable`, `friction_seconds`, `friction_daysbeforeend`, `friction_daysafterpost`, `friction_invitation` |
| Academic integrity | `enable_report_form`, `report_form_url`, `proctoring_services`, and the settings of each ticked proctoring service |

## Capabilities

| Capability | Allows | Default roles |
|------------|--------|---------------|
| `local/unifiedgrader:grade` | Open the grader; save grades, feedback, annotations and penalties; post grades; change a student's dates | Editing teacher, Manager |
| `local/unifiedgrader:viewall` | Reserved. It does not open the grader | Teacher, Editing teacher, Manager |
| `local/unifiedgrader:viewnotes` | Read teacher notes | Teacher, Editing teacher, Manager |
| `local/unifiedgrader:managenotes` | Add and delete teacher notes | Editing teacher, Manager |
| `local/unifiedgrader:viewfeedback` | Open the feedback page, download the feedback PDF, use submission comments | Student, Teacher, Editing teacher, Manager |
| `local/unifiedgrader:refer` | Refer a submission for an academic integrity review, and resolve a referral | Editing teacher, Manager |
| `local/unifiedgrader:viewproctoring` | See proctoring markers and the Proctoring card | Editing teacher, Manager |
| `local/unifiedgrader:sharecomments` | Copy a shared comment into your own library (site level) | Editing teacher, Manager |
| `local/unifiedgrader:managesystemdefaults` | Maintain system-default comments and approve suggestions (site level) | Manager |
| `local/unifiedgrader:moderatelibraries` | Moderate and export teachers' comment libraries (site level) | Manager |

## Languages

English, Afrikaans, German, Greek, Spanish, French, Hebrew, Italian, Portuguese, Russian, Swahili, Xhosa and Zulu. The built-in help page is in English.

## Privacy

The plugin implements Moodle's Privacy API for the data it stores.

## Third-party libraries

Bundled in `thirdparty/`; nothing is loaded from external servers.

- **PDF.js** v4.10.38 (Apache-2.0): PDF rendering
- **Fabric.js** v6.9.1 (MIT): the annotation layer
- **pdf-lib** v1.17.1 (MIT): PDFs with the annotations printed in

## License

This plugin is licensed under the [GNU GPL v3 or later](https://www.gnu.org/copyleft/gpl.html).

Copyright 2026 South African Theological Seminary (mathieu@sats.ac.za).
