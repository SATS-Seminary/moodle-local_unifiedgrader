# Unified Grader

A comprehensive grading interface for Moodle 5.0+ that consolidates the marking experience across assignments, forums, and quizzes into a single, streamlined workspace.

## Features

### Unified grading interface
- Split-view layout with submission preview on the left and grading panel on the right
- Quick student navigation with search, filtering (all, submitted, graded, not submitted), and group support
- Switch between split view, preview-only, or grading-only layouts
- Works with assignments, forums, and quizzes from one consistent interface

### PDF annotation
- Built-in PDF viewer with continuous scroll, zoom, and page navigation
- Rich annotation toolbar: highlighting, pen tool, shapes (rectangles, circles, arrows, lines), stamps (checkmark, cross, question mark), and comment bubbles
- Configurable pen stroke widths and colours (red, yellow, green, blue, black)
- Per-page annotations saved automatically with undo/redo support
- Office documents (Word, PowerPoint) auto-converted to PDF for annotation
- Flattened annotated PDFs generated client-side and stored for student download

### Rich feedback
- TinyMCE editor for formatted feedback with embedded audio/video recording
- Feedback file attachments
- Feedback visible to students through a dedicated feedback view with assessment criteria display

### Grading methods
- Simple numeric grading with percentage display
- Rubrics and marking guides (Moodle advanced grading)
- Optional manual grade override when using rubrics
- "Mark as graded" toggle for feedback-only activities (no grade type)
- Multi-attempt grading for assignments configured with multiple submissions

### Grade management
- Post/unpost grades to control student visibility
- Scheduled grade posting (assignments)
- Late submission detection with penalty support
- Due date extension management (assignments, forums, quizzes)
- User-level override management

### Teacher productivity
- Private teacher notes per student per activity
- Comment library with tagging and course-code organisation
- Shared comment library for team collaboration
- Submission comment threads for teacher discussion
- Offline caching and unsaved changes protection

### Student feedback view
- Students access feedback through a banner on the activity page
- Displays assessment criteria (rubric/marking guide), teacher feedback, and annotated PDFs
- Multi-attempt feedback viewing for assignments

### Integrations
- Plagiarism plugin support (Turnitin, Copyleaks) with inline results
- Quiz extension support via the quizaccess_duedate plugin (optional)
- Moodle Privacy API compliant (GDPR)

## Requirements

- Moodle 5.0 or later
- PHP 8.2 or later

## Installation

1. Download the plugin and extract it to `/local/unifiedgrader/` in your Moodle installation.
2. Log in as an administrator and complete the plugin installation through the notification screen.
3. Configure the plugin under **Site Administration > Plugins > Local plugins > Unified Grader**.

By default, only assignment grading is enabled. Forum and quiz grading can be enabled in the plugin settings.

## Usage

Once installed, a **Unified Grader** tab appears in the secondary navigation of supported activities for users with the `local/unifiedgrader:grade` capability (editing teachers and managers by default).

Students with graded and released feedback will see a **View Feedback** link on the activity page.

## Configuration

| Setting | Description | Default |
|---------|-------------|---------|
| Enable for assignments | Show Unified Grader for assignment activities | Enabled |
| Enable for forums | Show Unified Grader for forum activities | Disabled |
| Enable for quizzes | Show Unified Grader for quiz activities | Disabled |
| Enable post grades for quizzes | Allow overriding quiz review options | Disabled |
| Allow manual grade override | Allow numeric entry alongside rubrics | Enabled |
| Course code regex | Regex to extract course codes from short names | Empty |
| Enable academic impropriety form | Show reporting button in grading interface | Disabled |
| Report form URL template | URL template with placeholders for reporting | Empty |

## Capabilities

| Capability | Description | Default roles |
|------------|-------------|---------------|
| `local/unifiedgrader:grade` | Access the grading interface | Editing teacher, Manager |
| `local/unifiedgrader:viewall` | View all students' submissions | Teacher, Editing teacher, Manager |
| `local/unifiedgrader:viewnotes` | View private teacher notes | Teacher, Editing teacher, Manager |
| `local/unifiedgrader:managenotes` | Create, edit, and delete notes | Editing teacher, Manager |
| `local/unifiedgrader:viewfeedback` | View feedback (student view) | Student, Teacher, Editing teacher, Manager |
| `local/unifiedgrader:sharecomments` | Share comments with other teachers | Editing teacher, Manager |

## Third-party libraries

This plugin includes the following third-party libraries in the `thirdparty/` directory:

- **PDF.js** v4.10.38 (Apache-2.0) - PDF rendering
- **Fabric.js** v6.9.1 (MIT) - Canvas-based annotation layer
- **pdf-lib** v1.17.1 (MIT) - Client-side PDF generation for flattened annotations

## License

This plugin is licensed under the [GNU GPL v3 or later](https://www.gnu.org/copyleft/gpl.html).

Copyright 2026 South African Theological Seminary (mathieu@sats.ac.za).
