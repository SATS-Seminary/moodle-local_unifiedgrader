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
 * Font Awesome icon for a submitted file's type.
 *
 * Used by the file pills and the split-view file picker so a teacher can tell at
 * a glance which tab is the recording and which is the manuscript, rather than
 * reading filenames. All names below exist in the Font Awesome 6 set Moodle 5.0
 * ships.
 *
 * @module     local_unifiedgrader/lib/file_icons
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Exact mimetypes that map to an Office-style icon. */
const EXACT = {
    'application/pdf': 'fa-file-pdf',

    // Word processing: .doc/.docx/.odt.
    'application/msword': 'fa-file-word',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'fa-file-word',
    'application/vnd.oasis.opendocument.text': 'fa-file-word',
    'application/rtf': 'fa-file-word',

    // Spreadsheets: .xls/.xlsx/.ods.
    'application/vnd.ms-excel': 'fa-file-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'fa-file-excel',
    'application/vnd.oasis.opendocument.spreadsheet': 'fa-file-excel',

    // Presentations: .ppt/.pptx/.odp.
    'application/vnd.ms-powerpoint': 'fa-file-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'fa-file-powerpoint',
    'application/vnd.oasis.opendocument.presentation': 'fa-file-powerpoint',
};

/**
 * The icon class for a file, keyed on its mimetype.
 *
 * Falls back to the generic document icon so an unrecognised type still gets a
 * tab that looks deliberate rather than blank.
 *
 * @param {object} file A submission file (needs mimetype; filename is a fallback).
 * @return {string} A Font Awesome class, e.g. 'fa-file-pdf'.
 */
export const iconForFile = (file) => {
    const mimetype = ((file && file.mimetype) || '').toLowerCase();

    if (mimetype.startsWith('video/')) {
        return 'fa-video';
    }
    if (mimetype.startsWith('audio/')) {
        return 'fa-headphones';
    }
    if (EXACT[mimetype]) {
        return EXACT[mimetype];
    }

    // Some sources report a generic mimetype (application/octet-stream) even for
    // a well-known document, so fall back to the extension before giving up.
    const ext = ((file && file.filename) || '').split('.').pop().toLowerCase();
    const byext = {
        pdf: 'fa-file-pdf',
        doc: 'fa-file-word', docx: 'fa-file-word', odt: 'fa-file-word', rtf: 'fa-file-word',
        xls: 'fa-file-excel', xlsx: 'fa-file-excel', ods: 'fa-file-excel', csv: 'fa-file-excel',
        ppt: 'fa-file-powerpoint', pptx: 'fa-file-powerpoint', odp: 'fa-file-powerpoint',
        mp4: 'fa-video', mov: 'fa-video', avi: 'fa-video', mkv: 'fa-video', webm: 'fa-video',
        mp3: 'fa-headphones', m4a: 'fa-headphones', wav: 'fa-headphones', ogg: 'fa-headphones',
    };
    return byext[ext] || 'fa-file-alt';
};
