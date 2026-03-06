<?php
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
 * Serve submission files inline for the unified grader preview panel.
 *
 * Moodle's assignsubmission_file plugin hardcodes force-download on all files.
 * This endpoint re-serves the same files with Content-Disposition: inline,
 * restricted to safe MIME types and users with the grading or viewfeedback capability.
 *
 * For students (viewfeedback only), additionally validates file ownership and grade release.
 *
 * When convert=pdf is passed, non-PDF files are converted to PDF using
 * Moodle's document converter API (unoconv / Google Drive). The converted
 * PDF is served instead of the original file. Conversions are cached by
 * Moodle based on the source file's content hash.
 *
 * Usage: preview_file.php?fileid=123&cmid=456
 *        preview_file.php?fileid=123&cmid=456&convert=pdf
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_unifiedgrader\adapter\adapter_factory;

$fileid = required_param('fileid', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);
$convert = optional_param('convert', '', PARAM_ALPHA);

// Validate context and capability.
$context = context_module::instance($cmid);
require_login(get_course($context->get_course_context()->instanceid), false, get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST));

// Teachers with grade capability can access any file in this context.
// Students with viewfeedback capability can only access their own files after grade release.
$isteacher = has_capability('local/unifiedgrader:grade', $context);
$isstudent = !$isteacher && has_capability('local/unifiedgrader:viewfeedback', $context);

if (!$isteacher && !$isstudent) {
    throw new moodle_exception('nopermission', 'local_unifiedgrader');
}

// Fetch the file.
$fs = get_file_storage();
$file = $fs->get_file_by_id($fileid);

if (!$file || $file->is_directory()) {
    throw new moodle_exception('filenotfound', 'error');
}

// Verify the file belongs to this activity context.
if ((int) $file->get_contextid() !== (int) $context->id) {
    throw new moodle_exception('filenotfound', 'error');
}

// For students: verify file ownership and grade release.
if ($isstudent) {
    global $USER;
    $adapter = adapter_factory::create($cmid);

    // Grade must be released.
    if (!$adapter->is_grade_released((int) $USER->id)) {
        throw new moodle_exception('filenotfound', 'error');
    }

    // File must belong to this student's submission.
    $submissionfiles = $adapter->get_submission_files((int) $USER->id);
    $fileids = array_column($submissionfiles, 'fileid');
    if (!in_array($fileid, $fileids)) {
        throw new moodle_exception('filenotfound', 'error');
    }
}

// Handle PDF conversion for non-PDF files.
if ($convert === 'pdf') {
    $mimetype = $file->get_mimetype();
    if ($mimetype === 'application/pdf') {
        // Already a PDF — serve directly.
        send_stored_file($file, 0, 0, false, [
            'cacheability' => 'private',
            'immutable' => false,
        ]);
        exit;
    }

    // Attempt conversion to PDF.
    $converter = new \core_files\converter();
    $conversion = $converter->start_conversion($file, 'pdf');
    $status = $conversion->get('status');

    // Poll briefly (up to ~5 seconds) for quick conversions to complete
    // synchronously, avoiding client-side polling in the common case.
    $maxpolls = 5;
    $pollcount = 0;
    while (
        $status !== \core_files\conversion::STATUS_COMPLETE
        && $status !== \core_files\conversion::STATUS_FAILED
        && $pollcount < $maxpolls
    ) {
        sleep(1);
        $converter->poll_conversion($conversion);
        $status = $conversion->get('status');
        $pollcount++;
    }

    if ($status === \core_files\conversion::STATUS_COMPLETE) {
        $convertedfile = $conversion->get_destfile();
        if ($convertedfile) {
            send_stored_file($convertedfile, 0, 0, false, [
                'cacheability' => 'private',
                'immutable' => false,
            ]);
            exit;
        }
    }

    // Conversion not ready or failed — return JSON status for the frontend.
    header('Content-Type: application/json; charset=utf-8');
    if ($status === \core_files\conversion::STATUS_FAILED) {
        http_response_code(422);
        echo json_encode(['error' => get_string('conversion_failed', 'local_unifiedgrader')]);
    } else {
        http_response_code(202);
        echo json_encode(['status' => 'converting']);
    }
    exit;
}

// Direct file serving (no conversion) — only allow safe MIME types inline.
$safetypes = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/svg+xml',
    'text/plain',
];

$mimetype = $file->get_mimetype();
$issafe = in_array($mimetype, $safetypes)
    || str_starts_with($mimetype, 'audio/')
    || str_starts_with($mimetype, 'video/');

if (!$issafe) {
    throw new moodle_exception('filenotfound', 'error');
}

// Serve inline (forcedownload = false).
send_stored_file($file, 0, 0, false, [
    'cacheability' => 'private',
    'immutable' => false,
]);
