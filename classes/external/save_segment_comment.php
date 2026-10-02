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
 * External function: save a segment-anchored comment on a submission phrase.
 *
 * The comment always anchors to the student's ORIGINAL source text. The client
 * sends the source segment id(s) it selected; the durable char-offset anchor
 * (startoffset/endoffset/anchortext) is computed server-side by local_nida from
 * the stored aligned source, so it survives re-translation. When local_nida is
 * absent, or cannot resolve an anchor, the save fails cleanly rather than storing
 * a bogus anchor.
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
use local_unifiedgrader\segment_comment_manager;

/**
 * Saves a single segment-anchored comment on a student's submission.
 */
class save_segment_comment extends external_api {
    /** @var string The fully-qualified local_nida submission API class. */
    private const NIDA_API = '\local_nida\local\assign\submission_api';

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
            'srcsegids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'A source segment id the grader anchored to'),
                'Source segment id(s) selected in the parallel view',
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
     * The module context is resolved server-side from cmid and every access check
     * runs against it. The stored comment is targeted purely by (cmid, userid,
     * attempt) — no translation-row or comment id is ever trusted from the client,
     * and the author is forced to the logged-in grader.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @param int $attempt Attempt number (0-based) or -1 for latest.
     * @param string $sourcetype 'onlinetext' or 'file'.
     * @param int $fileid {files}.id for file sources, 0 for online text.
     * @param array $srcsegids Source segment id(s) the grader anchored to.
     * @param string $commenttext The grader comment (HTML).
     * @param int $commentformat The comment text format.
     * @param string $marktype The mark type: comment, tick, cross, highlight or query.
     * @param string $color Marker colour (#RRGGBB) or '' for the default.
     * @return array The stored comment (fields for the client to render a marker).
     */
    public static function execute(
        int $cmid,
        int $userid,
        int $attempt,
        string $sourcetype,
        int $fileid,
        array $srcsegids,
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
            'srcsegids' => $srcsegids,
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

        // Release the PHP session lock so concurrent grader AJAX does not serialize
        // behind this request. This handler does not write to $SESSION.
        \core\session\manager::write_close();

        // Only online-text and file sources are anchorable.
        if ($params['sourcetype'] !== 'onlinetext' && $params['sourcetype'] !== 'file') {
            throw new \moodle_exception('segcomment_badsource', 'local_unifiedgrader');
        }
        if (empty($params['srcsegids'])) {
            throw new \moodle_exception('segcomment_noanchor', 'local_unifiedgrader');
        }

        // Resolve the assignment submission for the stored (submissionid, attempt).
        [$submissionid, $attemptnumber] = self::resolve_submission(
            $CFG,
            $context,
            $params['cmid'],
            $params['userid'],
            $params['attempt'],
        );

        // Compute the durable anchor from local_nida's aligned source. This is the
        // only authority for the offsets/anchortext — never trust client offsets.
        $anchor = self::resolve_anchor(
            $params['cmid'],
            $params['userid'],
            $params['attempt'],
            $params['sourcetype'],
            $params['fileid'],
            array_map('intval', $params['srcsegids']),
            (int) $USER->id,
        );

        // Sanitise the grader's comment HTML before it is stored.
        $clean = clean_text($params['commenttext'], FORMAT_HTML);

        $record = segment_comment_manager::create(
            (int) $params['cmid'],
            (int) $params['userid'],
            (int) $USER->id,
            $submissionid,
            $attemptnumber,
            $params['sourcetype'],
            (int) $params['fileid'],
            (int) $anchor['segmentid'],
            (int) $anchor['startoffset'],
            (int) $anchor['endoffset'],
            (string) $anchor['anchortext'],
            $clean,
            FORMAT_HTML,
            $marktype,
            0,
            $params['color'],
        );

        return [
            'id' => (int) $record->id,
            'segmentid' => (int) $record->segmentid,
            'sourcetype' => (string) $record->sourcetype,
            'fileid' => (int) $record->fileid,
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
     * Resolve the assignment submission id and concrete attempt number.
     *
     * @param object $cfg The global $CFG.
     * @param \context_module $context The module context.
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @param int $attempt Attempt number (0-based) or -1 for latest.
     * @return array [submissionid, attemptnumber].
     */
    private static function resolve_submission(
        $cfg,
        \context_module $context,
        int $cmid,
        int $userid,
        int $attempt
    ): array {
        // The mod/assign locallib.php uses the global $CFG in its top-level code,
        // so it must be in scope here — a require_once from a function scope does
        // not otherwise see it, and locallib.php then fatals on $CFG->libdir.
        global $CFG;
        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
        if ($cm->modname !== 'assign') {
            throw new \moodle_exception('segcomment_notassign', 'local_unifiedgrader');
        }
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        [$course, $cminfo] = get_course_and_cm_from_cmid($cmid, 'assign');
        $assign = new \assign($context, $cminfo, $course);
        $submission = $assign->get_user_submission($userid, false, $attempt >= 0 ? $attempt : -1);
        if ($submission) {
            return [(int) $submission->id, (int) $submission->attemptnumber];
        }
        return [0, max($attempt, 0)];
    }

    /**
     * Resolve the durable source anchor via local_nida.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @param int $attempt Attempt number (0-based) or -1 for latest.
     * @param string $sourcetype 'onlinetext' or 'file'.
     * @param int $fileid {files}.id for file sources, 0 for online text.
     * @param array $srcsegids Source segment id(s) the grader anchored to.
     * @param int $graderid The requesting grader's user id.
     * @return array {segmentid, startoffset, endoffset, anchortext}.
     */
    private static function resolve_anchor(
        int $cmid,
        int $userid,
        int $attempt,
        string $sourcetype,
        int $fileid,
        array $srcsegids,
        int $graderid
    ): array {
        $api = self::NIDA_API;
        $available = class_exists($api) && method_exists($api, 'resolve_source_anchor');
        if (!$available) {
            throw new \moodle_exception('segcomment_nonida', 'local_unifiedgrader');
        }

        $anchor = $api::resolve_source_anchor(
            $cmid,
            $userid,
            $attempt,
            $sourcetype,
            $fileid,
            $srcsegids,
            $graderid,
        );

        if ($anchor === null || !is_array($anchor) || !isset($anchor['anchortext'])) {
            throw new \moodle_exception('segcomment_noanchor', 'local_unifiedgrader');
        }
        return $anchor;
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'The new comment id'),
            'segmentid' => new external_value(PARAM_INT, 'The nida FIELD segment id'),
            'sourcetype' => new external_value(PARAM_ALPHA, 'Source type: onlinetext or file'),
            'fileid' => new external_value(PARAM_INT, '{files}.id for file sources, 0 for online text'),
            'startoffset' => new external_value(PARAM_INT, 'Anchor start offset in the source plaintext'),
            'endoffset' => new external_value(PARAM_INT, 'Anchor end offset in the source plaintext'),
            'anchortext' => new external_value(PARAM_RAW, 'The exact anchored source phrase (plaintext)'),
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
