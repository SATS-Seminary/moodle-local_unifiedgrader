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
 * External function: upload a flattened annotated PDF to file storage.
 *
 * Receives a base64-encoded PDF with annotations baked in and stores it
 * in Moodle's file storage for student access via pluginfile.php.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Stores a flattened annotated PDF in Moodle file storage.
 */
class save_annotated_pdf extends external_api {

    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
            'fileid' => new external_value(PARAM_INT, 'Original submission file ID'),
            'pdfdata' => new external_value(PARAM_RAW, 'Base64-encoded flattened PDF'),
            'filename' => new external_value(PARAM_FILE, 'Filename for the stored PDF'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @param int $userid
     * @param int $fileid
     * @param string $pdfdata
     * @param string $filename
     * @return array
     */
    public static function execute(
        int $cmid,
        int $userid,
        int $fileid,
        string $pdfdata,
        string $filename,
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
            'fileid' => $fileid,
            'pdfdata' => $pdfdata,
            'filename' => $filename,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);

        // Decode the base64 PDF data.
        $pdfbytes = base64_decode($params['pdfdata'], true);
        if ($pdfbytes === false) {
            throw new \invalid_parameter_exception('Invalid base64 PDF data.');
        }

        $fs = get_file_storage();

        // Delete any existing annotated PDF for this combination.
        $existing = $fs->get_file(
            $context->id,
            'local_unifiedgrader',
            'annotatedpdf',
            $params['fileid'],
            '/' . $params['userid'] . '/',
            $params['filename'],
        );
        if ($existing) {
            $existing->delete();
        }

        // Store the new flattened PDF.
        $filerecord = [
            'contextid' => $context->id,
            'component' => 'local_unifiedgrader',
            'filearea' => 'annotatedpdf',
            'itemid' => $params['fileid'],
            'filepath' => '/' . $params['userid'] . '/',
            'filename' => $params['filename'],
        ];
        $fs->create_file_from_string($filerecord, $pdfbytes);

        return ['success' => true];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the upload succeeded'),
        ]);
    }
}
