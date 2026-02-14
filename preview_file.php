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
 * restricted to safe MIME types and users with the grading capability.
 *
 * Usage: preview_file.php?fileid=123&cmid=456
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$fileid = required_param('fileid', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);

// Validate context and capability.
$context = context_module::instance($cmid);
require_login(get_course($context->get_course_context()->instanceid), false, get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST));
require_capability('local/unifiedgrader:grade', $context);

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

// Only serve safe MIME types inline. Everything else is rejected.
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
if (!in_array($mimetype, $safetypes)) {
    throw new moodle_exception('filenotfound', 'error');
}

// Serve inline (forcedownload = false).
send_stored_file($file, 0, 0, false, [
    'cacheability' => 'private',
    'immutable' => false,
]);
