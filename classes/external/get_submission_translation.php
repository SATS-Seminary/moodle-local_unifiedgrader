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
 * External function: get an English translation of a student's submission for graders.
 *
 * Bridges to the local_nida translation plugin. When local_nida is absent or too
 * old to expose the assignment submission API, the call degrades gracefully to a
 * status of 'unavailable' so the grading interface keeps working unchanged.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Returns the English translation of a submission for a grader, via local_nida.
 */
class get_submission_translation extends external_api {
    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
            'attempt' => new external_value(
                PARAM_INT,
                'Attempt number (0-based), -1 for latest',
                VALUE_DEFAULT,
                -1,
            ),
        ]);
    }

    /**
     * Execute the function.
     *
     * The module context is resolved server-side from cmid and every access check
     * runs against it. The (cmid, userid, attempt) triple is re-validated inside
     * local_nida's submission API — no translation-row identifiers are ever
     * trusted from the client.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @param int $attempt Attempt number (0-based) or -1 for latest.
     * @return array
     */
    public static function execute(int $cmid, int $userid, int $attempt = -1): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
            'attempt' => $attempt,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);
        \local_unifiedgrader\access::require_student_access($context, (int) $params['userid']);

        // Release the PHP session lock so concurrent grader AJAX does not serialize
        // behind this request. This handler does not write to $SESSION.
        \core\session\manager::write_close();

        // Degrade gracefully when local_nida is absent or predates the assign API.
        if (!class_exists('\local_nida\local\assign\submission_api')) {
            return self::empty_result('unavailable');
        }

        try {
            $result = \local_nida\local\assign\submission_api::get_for_grader(
                (int) $params['cmid'],
                (int) $params['userid'],
                (int) $params['attempt'],
                (int) $USER->id,
            );
        } catch (\Throwable $e) {
            // A translation-subsystem failure must never break the grading UI.
            debugging(
                'local_nida submission_api::get_for_grader failed: ' . $e->getMessage(),
                DEBUG_DEVELOPER,
            );
            return self::empty_result('unavailable');
        }

        if ($result === null) {
            return self::empty_result('unavailable');
        }

        // Language metadata comes back null under blind marking — display nothing.
        $hasmetadata = isset($result->detectedlang) && $result->detectedlang !== null;

        $sources = [];
        $fs = get_file_storage();
        foreach (($result->sources ?? []) as $source) {
            $source = (array) $source;
            $alignment = $source['alignment'] ?? null;
            if ($alignment !== null && !is_string($alignment)) {
                $alignment = json_encode($alignment);
            }
            // File metadata for the document-info popout (empty for online text).
            // Reading mimetype/filesize off the stored file is metadata-only — no
            // content is served here — and the fileid comes from local_nida's
            // server-side submission API, not the client.
            $fileid = (int) ($source['fileid'] ?? 0);
            $mimetype = '';
            $filesize = 0;
            if ($fileid > 0) {
                $storedfile = $fs->get_file_by_id($fileid);
                if ($storedfile && !$storedfile->is_directory()) {
                    $mimetype = (string) $storedfile->get_mimetype();
                    $filesize = (int) $storedfile->get_filesize();
                }
            }
            $sources[] = [
                'type' => (string) ($source['type'] ?? 'onlinetext'),
                'filename' => (string) ($source['filename'] ?? ''),
                // Stored-file id a segment comment on this source must anchor to
                // (0 for online text). The grader JS sends it back on save so the
                // resolver can locate the file's source segment.
                'fileid' => $fileid,
                'mimetype' => $mimetype,
                'filesize' => $filesize,
                // Content is pre-sanitised by local_nida (clean_text, FORMAT_HTML)
                // before it leaves its submission_api; UG passes it through
                // unchanged. The alignment payload carries per-segment inner HTML
                // with NO segment spans — the grader JS wraps each already-clean
                // segment in its own data-nida-seg span client-side — so nothing
                // here relies on an HTML attribute surviving a purifier.
                'html' => (string) ($source['html'] ?? ''),
                'alignment' => $alignment === null ? '' : (string) $alignment,
                'verdict' => (string) ($source['verdict'] ?? ''),
            ];
        }

        // English dubs of recorder audio embedded in the submission — grader-only.
        // local_nida returns each as a ready serve.php URL + filename; older
        // versions omit the field entirely, so default to none.
        $audio = [];
        foreach (($result->audio ?? []) as $clip) {
            $clip = (array) $clip;
            $url = (string) ($clip['url'] ?? '');
            if ($url === '') {
                continue;
            }
            $audio[] = [
                'url' => $url,
                'filename' => (string) ($clip['filename'] ?? ''),
            ];
        }

        return [
            'status' => (string) ($result->status ?? 'unavailable'),
            'hasmetadata' => $hasmetadata,
            'detectedlang' => $hasmetadata ? (string) $result->detectedlang : '',
            'resolvedlang' => ($hasmetadata && $result->resolvedlang !== null)
                ? (string) $result->resolvedlang : '',
            'agreement' => $hasmetadata ? (int) ($result->agreement ?? 1) : 1,
            'mixedflag' => !empty($result->mixedflag),
            'sources' => $sources,
            'audio' => $audio,
        ];
    }

    /**
     * Build an empty result payload for a terminal status (no translation content).
     *
     * @param string $status The status code to report.
     * @return array
     */
    private static function empty_result(string $status): array {
        return [
            'status' => $status,
            'hasmetadata' => false,
            'detectedlang' => '',
            'resolvedlang' => '',
            'agreement' => 1,
            'mixedflag' => false,
            'sources' => [],
            'audio' => [],
        ];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(
                PARAM_ALPHA,
                'One of: pending, ready, fallback, nottranslated, noattempt, unavailable',
            ),
            'hasmetadata' => new external_value(
                PARAM_BOOL,
                'Whether language metadata is available (false under blind marking)',
            ),
            'detectedlang' => new external_value(PARAM_RAW, 'Detected submission language code', VALUE_DEFAULT, ''),
            'resolvedlang' => new external_value(
                PARAM_RAW,
                'Confirmed/resolved language code (empty if unresolved)',
                VALUE_DEFAULT,
                '',
            ),
            'agreement' => new external_value(PARAM_INT, '1 when detected agrees with profile, else 0', VALUE_DEFAULT, 1),
            'mixedflag' => new external_value(PARAM_BOOL, 'Whether heavy language mixing was detected', VALUE_DEFAULT, false),
            'sources' => new external_multiple_structure(
                new external_single_structure([
                    'type' => new external_value(PARAM_ALPHA, 'Source type: onlinetext or file'),
                    'filename' => new external_value(PARAM_TEXT, 'File name for file sources', VALUE_DEFAULT, ''),
                    'fileid' => new external_value(
                        PARAM_INT,
                        '{files}.id a segment comment on this source anchors to (0 for online text)',
                        VALUE_DEFAULT,
                        0,
                    ),
                    'mimetype' => new external_value(
                        PARAM_RAW,
                        'Source file MIME type for the document-info popout (empty for online text)',
                        VALUE_DEFAULT,
                        '',
                    ),
                    'filesize' => new external_value(
                        PARAM_INT,
                        'Source file size in bytes (0 for online text)',
                        VALUE_DEFAULT,
                        0,
                    ),
                    'html' => new external_value(PARAM_RAW, 'Cleaned translated HTML (already clean_text\'d by local_nida)'),
                    'alignment' => new external_value(
                        PARAM_RAW,
                        'Segment alignment metadata (JSON) or empty string',
                        VALUE_DEFAULT,
                        '',
                    ),
                    'verdict' => new external_value(
                        PARAM_ALPHANUMEXT,
                        'Per-source verdict: notext/failed/unsupported/truncated',
                        VALUE_DEFAULT,
                        '',
                    ),
                ]),
                'Per-source translated content',
                VALUE_DEFAULT,
                [],
            ),
            'audio' => new external_multiple_structure(
                new external_single_structure([
                    'url' => new external_value(PARAM_RAW, 'Recorder serve.php URL for the English audio dub'),
                    'filename' => new external_value(PARAM_TEXT, 'Display name of the dubbed clip', VALUE_DEFAULT, ''),
                ]),
                'English dubs of recorder audio embedded in the submission (grader-only)',
                VALUE_DEFAULT,
                [],
            ),
        ]);
    }
}
