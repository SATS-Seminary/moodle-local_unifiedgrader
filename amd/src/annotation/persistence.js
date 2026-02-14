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
 * Annotation persistence — standalone AJAX helpers for loading, saving,
 * and deleting annotations via the Moodle web service API.
 *
 * These functions are called directly by PdfViewer (not through the
 * reactive stateManager) because annotation data does not go through
 * the Moodle reactive state system.
 *
 * @module     local_unifiedgrader/annotation/persistence
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Load all annotations for a file from the backend.
 *
 * @param {number} cmid Course module ID.
 * @param {number} userid Student user ID.
 * @param {number} fileid File ID.
 * @returns {Promise<Array>} Array of annotation records.
 */
export async function loadAnnotations(cmid, userid, fileid) {
    return Ajax.call([{
        methodname: 'local_unifiedgrader_get_annotations',
        args: {cmid, userid, fileid},
    }])[0];
}

/**
 * Save annotations for a file to the backend (batch).
 *
 * @param {number} cmid Course module ID.
 * @param {number} userid Student user ID.
 * @param {number} fileid File ID.
 * @param {Array} pages Array of {pagenum: number, annotationdata: string}.
 * @returns {Promise<object>} {success: boolean}
 */
export async function saveAnnotations(cmid, userid, fileid, pages) {
    return Ajax.call([{
        methodname: 'local_unifiedgrader_save_annotations',
        args: {cmid, userid, fileid, pages},
    }])[0];
}

/**
 * Delete all annotations for a file.
 *
 * @param {number} cmid Course module ID.
 * @param {number} userid Student user ID.
 * @param {number} fileid File ID.
 * @returns {Promise<object>} {success: boolean}
 */
export async function deleteAnnotations(cmid, userid, fileid) {
    return Ajax.call([{
        methodname: 'local_unifiedgrader_delete_annotations',
        args: {cmid, userid, fileid},
    }])[0];
}
