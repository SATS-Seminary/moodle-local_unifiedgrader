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
 * External function: re-queue a failed docx → PDF conversion.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Drop the cached failed-conversion record for a source file so the next
 * preview request triggers a fresh attempt against the document converter.
 *
 * For online-text submissions (which cache the rendered PDF in our own
 * filearea instead of going through file_conversion) the cached PDF is
 * deleted instead, so the next call to get_onlinetext_pdf() regenerates it.
 */
class retry_file_conversion extends external_api {
    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'fileid' => new external_value(PARAM_INT, 'Source file ID whose conversion to retry'),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $cmid
     * @param int $fileid
     * @return array
     */
    public static function execute(int $cmid, int $fileid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'fileid' => $fileid,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);

        $fs = get_file_storage();
        $file = $fs->get_file_by_id($params['fileid']);
        if (!$file) {
            throw new \moodle_exception('error_file_not_found', 'local_unifiedgrader');
        }

        // Online-text PDFs live in our own filearea and don't use Moodle's
        // file_conversion table. Delete the cached PDF so the next
        // get_onlinetext_pdf() call regenerates it from the source HTML.
        if (
            $file->get_component() === 'local_unifiedgrader'
            && $file->get_filearea() === 'onlinetextpdf'
        ) {
            $file->delete();
            return ['success' => true, 'path' => 'onlinetext'];
        }

        // Regular file conversion: drop the cached file_conversion row
        // (and any rows pointing at the same destfile, in case multiple
        // formats were requested). The orphan destfile is cleaned up by
        // \core_files\task\conversion_cleanup_task on its next run.
        $deleted = $DB->delete_records('file_conversion', ['sourcefileid' => $file->get_id()]);

        return ['success' => (bool) $deleted, 'path' => 'fileconversion'];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the conversion cache was cleared'),
            'path' => new external_value(PARAM_ALPHA, 'Which cache path was cleared'),
        ]);
    }
}
