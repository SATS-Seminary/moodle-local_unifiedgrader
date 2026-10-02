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
 * External function: save an offset-anchored comment/mark on a NON-translated
 * submission phrase (the grader's own free text selection).
 *
 * Unlike save_segment_comment (which resolves a durable anchor from local_nida's
 * alignment), this path is for plain, non-translated text: the grader selects any
 * run of text, the client sends its character offsets and the selected text, and
 * the server's only anchoring authority is an anti-spoof check — the selected
 * anchortext must actually occur in the submission's plaintext. The offsets are
 * stored as the client computed them (the submission text is immutable for a given
 * attempt, so they re-locate reliably on the same rendering); segmentid is 0.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use local_unifiedgrader\segment_comment_manager;

/**
 * Saves a single offset-anchored comment/mark on a non-translated submission.
 */
class save_text_comment extends external_api {
    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
            'attempt' => new external_value(PARAM_INT, 'Attempt number (0-based), -1 for latest'),
            'sourcetype' => new external_value(PARAM_ALPHA, 'Source type: onlinetext or file'),
            'fileid' => new external_value(PARAM_INT, '{files}.id for file sources, 0 for online text', VALUE_DEFAULT, 0),
            'startoffset' => new external_value(PARAM_INT, 'Selection start (char offset in the source plaintext)'),
            'endoffset' => new external_value(PARAM_INT, 'Selection end (char offset in the source plaintext)'),
            'anchortext' => new external_value(PARAM_RAW, 'The exact selected text (plaintext)'),
            'page' => new external_value(
                PARAM_INT,
                '1-based PDF page for file (PDF text-layer) sources; 0 for online text',
                VALUE_DEFAULT,
                0
            ),
            'commenttext' => new external_value(PARAM_RAW, 'The grader comment (HTML)'),
            'commentformat' => new external_value(PARAM_INT, 'Comment text format', VALUE_DEFAULT, FORMAT_HTML),
            'marktype' => new external_value(
                PARAM_ALPHA,
                'Mark type: comment, tick, cross, highlight or query',
                VALUE_DEFAULT,
                'comment'
            ),
            'color' => new external_value(
                PARAM_RAW_TRIMMED,
                'Marker colour as #RRGGBB hex; empty uses the default',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @param int $attempt Attempt number (0-based) or -1 for latest.
     * @param string $sourcetype 'onlinetext' or 'file'.
     * @param int $fileid {files}.id for file sources, 0 for online text.
     * @param int $startoffset Selection start offset.
     * @param int $endoffset Selection end offset.
     * @param string $anchortext The selected text.
     * @param int $page 1-based PDF page for file sources; 0 for online text.
     * @param string $commenttext The grader comment (HTML).
     * @param int $commentformat The comment text format.
     * @param string $marktype The mark type.
     * @param string $color Marker colour (#RRGGBB) or '' for the default.
     * @return array The stored comment.
     */
    public static function execute(
        int $cmid,
        int $userid,
        int $attempt,
        string $sourcetype,
        int $fileid,
        int $startoffset,
        int $endoffset,
        string $anchortext,
        int $page,
        string $commenttext,
        int $commentformat = FORMAT_HTML,
        string $marktype = 'comment',
        string $color = ''
    ): array {
        global $USER, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
            'attempt' => $attempt,
            'sourcetype' => $sourcetype,
            'fileid' => $fileid,
            'startoffset' => $startoffset,
            'endoffset' => $endoffset,
            'anchortext' => $anchortext,
            'page' => $page,
            'commenttext' => $commenttext,
            'commentformat' => $commentformat,
            'marktype' => $marktype,
            'color' => $color,
        ]);
        $marktype = segment_comment_manager::normalise_marktype($params['marktype']);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);
        \local_unifiedgrader\access::require_student_access($context, (int) $params['userid']);
        \core\session\manager::write_close();

        // Offset-anchorable sources: online text (anti-spoofed against the
        // submission plaintext) and PDF file text layers (page-scoped).
        $sourcetype = $params['sourcetype'];
        if ($sourcetype !== 'onlinetext' && $sourcetype !== 'file') {
            throw new \moodle_exception('segcomment_badsource', 'local_unifiedgrader');
        }
        $anchortext = trim((string) $params['anchortext']);
        if ($anchortext === '') {
            throw new \moodle_exception('segcomment_noanchor', 'local_unifiedgrader');
        }

        // The mod/assign locallib uses the global $CFG at load; keep it in scope.
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $cm = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        if ($cm->modname !== 'assign') {
            throw new \moodle_exception('segcomment_notassign', 'local_unifiedgrader');
        }
        [$course, $cminfo] = get_course_and_cm_from_cmid($params['cmid'], 'assign');
        $assign = new \assign($context, $cminfo, $course);
        $submission = $assign->get_user_submission(
            $params['userid'],
            false,
            $params['attempt'] >= 0 ? $params['attempt'] : -1
        );
        $submissionid = $submission ? (int) $submission->id : 0;
        $attemptnumber = $submission ? (int) $submission->attemptnumber : max((int) $params['attempt'], 0);

        $fileid = 0;
        $page = 0;
        if ($sourcetype === 'onlinetext') {
            // Anti-spoof: the selected text must occur in the submission's plaintext.
            $plaintext = self::submission_plaintext($assign, $submission);
            if ($plaintext === '' || !self::contains($plaintext, $anchortext)) {
                throw new \moodle_exception('segcomment_noanchor', 'local_unifiedgrader');
            }
        } else {
            // File (PDF) source. There is no server-side PDF text extraction, so
            // the offsets/anchortext are trusted as the client (grader) computed
            // them against the PDF.js text layer. We still bind the mark to a real
            // file in THIS student's submission, and require a 1-based page, so a
            // mark can only ever reference the submission it is scoped to.
            $fileid = (int) $params['fileid'];
            $page = (int) $params['page'];
            if ($page < 1) {
                throw new \moodle_exception('segcomment_badpage', 'local_unifiedgrader');
            }
            if (!self::file_belongs_to_submission($fileid, $context->id, $submissionid)) {
                throw new \moodle_exception('segcomment_badsource', 'local_unifiedgrader');
            }
        }

        $clean = clean_text($params['commenttext'], FORMAT_HTML);
        $record = segment_comment_manager::create(
            (int) $params['cmid'],
            (int) $params['userid'],
            (int) $USER->id,
            $submissionid,
            $attemptnumber,
            $sourcetype,
            $fileid,
            0,
            max(0, (int) $params['startoffset']),
            max(0, (int) $params['endoffset']),
            $anchortext,
            $clean,
            FORMAT_HTML,
            $marktype,
            $page,
            $params['color'],
        );

        return [
            'id' => (int) $record->id,
            'segmentid' => 0,
            'sourcetype' => (string) $record->sourcetype,
            'fileid' => (int) $record->fileid,
            'page' => (int) $record->page,
            'startoffset' => (int) $record->startoffset,
            'endoffset' => (int) $record->endoffset,
            'anchortext' => (string) $record->anchortext,
            'commenttext' => (string) $record->commenttext,
            'commentformat' => (int) $record->commentformat,
            'marktype' => (string) $record->marktype,
            'color' => (string) ($record->color ?? ''),
            'authorid' => (int) $record->authorid,
            'timecreated' => (int) $record->timecreated,
            'timemodified' => (int) $record->timemodified,
        ];
    }

    /**
     * Whether a file id is a real file in this student's submission file area.
     *
     * Binds a PDF text-layer mark to a genuine submission file (the original
     * submission file's {files}.id — convertible files keep that id even when
     * previewed as a converted PDF), preventing a mark from referencing a file
     * outside the submission it is scoped to.
     *
     * @param int $fileid The {files}.id claimed by the client.
     * @param int $contextid The assign module context id.
     * @param int $submissionid The resolved assign_submission id.
     * @return bool
     */
    private static function file_belongs_to_submission(int $fileid, int $contextid, int $submissionid): bool {
        if ($fileid <= 0 || $submissionid <= 0) {
            return false;
        }
        $file = get_file_storage()->get_file_by_id($fileid);
        return $file
            && !$file->is_directory()
            && (int) $file->get_contextid() === $contextid
            && $file->get_component() === 'assignsubmission_file'
            && $file->get_filearea() === 'submission_files'
            && (int) $file->get_itemid() === $submissionid;
    }

    /**
     * The submission's online-text as plaintext (for the anti-spoof check).
     *
     * @param \assign $assign The assign instance.
     * @param \stdClass|false $submission The resolved submission, or false.
     * @return string The plaintext, or '' when there is no online text.
     */
    private static function submission_plaintext(\assign $assign, $submission): string {
        if (!$submission) {
            return '';
        }
        $plugin = $assign->get_submission_plugin_by_type('onlinetext');
        if (!$plugin || !$plugin->is_enabled()) {
            return '';
        }
        $html = (string) $plugin->get_editor_text('onlinetext', $submission->id);
        if ($html === '') {
            return '';
        }
        return html_to_text($html, 0, false);
    }

    /**
     * Whitespace-tolerant containment: whether $needle appears in $haystack once
     * both are reduced to single-spaced plaintext.
     *
     * @param string $haystack The submission plaintext.
     * @param string $needle The selected text.
     * @return bool
     */
    private static function contains(string $haystack, string $needle): bool {
        $norm = static function (string $s): string {
            return trim(preg_replace('/\s+/u', ' ', $s));
        };
        $h = $norm($haystack);
        $n = $norm($needle);
        return $n !== '' && \core_text::strpos($h, $n) !== false;
    }

    /**
     * Return definition.
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        return new \core_external\external_single_structure([
            'id' => new external_value(PARAM_INT, 'The new comment id'),
            'segmentid' => new external_value(PARAM_INT, 'Always 0 for non-translated comments'),
            'sourcetype' => new external_value(PARAM_ALPHA, 'Source type: onlinetext or file'),
            'fileid' => new external_value(PARAM_INT, '{files}.id for file sources, 0 for online text'),
            'page' => new external_value(PARAM_INT, '1-based PDF page for file sources; 0 for online text'),
            'startoffset' => new external_value(PARAM_INT, 'Anchor start offset in the source plaintext'),
            'endoffset' => new external_value(PARAM_INT, 'Anchor end offset in the submission plaintext'),
            'anchortext' => new external_value(PARAM_RAW, 'The exact selected text (plaintext)'),
            'commenttext' => new external_value(PARAM_RAW, 'The stored comment (cleaned HTML)'),
            'commentformat' => new external_value(PARAM_INT, 'Comment text format'),
            'marktype' => new external_value(PARAM_ALPHA, 'Mark type: comment, tick, cross, highlight or query'),
            'color' => new external_value(PARAM_RAW, 'Marker colour (#RRGGBB) or empty for the default'),
            'authorid' => new external_value(PARAM_INT, 'Author (grader) user id'),
            'timecreated' => new external_value(PARAM_INT, 'Creation timestamp'),
            'timemodified' => new external_value(PARAM_INT, 'Last modified timestamp'),
        ]);
    }
}
