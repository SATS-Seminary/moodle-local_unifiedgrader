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
 * External function: get the segment-anchored comments for a student/attempt.
 *
 * Returns the grader's own stored comments (from local_unifiedgrader's own table)
 * with their durable char-offset anchor. A `currentsegmentid` field is reserved
 * for a re-resolved render handle when local_nida exposes one; until then it is 0
 * and the client matches markers to the rendered source spans by anchortext.
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
use local_unifiedgrader\adapter\adapter_factory;
use local_unifiedgrader\segment_comment_manager;

/**
 * Returns the segment-anchored comments for a student's attempt.
 */
class get_segment_comments extends external_api {
    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
            'attempt' => new external_value(PARAM_INT, 'Attempt number (0-based), -1 for latest'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @param int $attempt Attempt number (0-based) or -1 for latest.
     * @return array The list of stored comments.
     */
    public static function execute(int $cmid, int $userid, int $attempt): array {
        global $CFG, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
            'attempt' => $attempt,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);

        // Graders read any student's marks. A student may read their OWN marks once
        // the grade is released — this powers the read-only margin view on their
        // feedback page. Anything else is denied.
        if (!has_capability('local/unifiedgrader:grade', $context)) {
            require_capability('local/unifiedgrader:viewfeedback', $context);
            if ((int) $params['userid'] !== (int) $USER->id) {
                throw new \required_capability_exception(
                    $context,
                    'local/unifiedgrader:grade',
                    'nopermissions',
                    '',
                );
            }
            if (!adapter_factory::create($params['cmid'])->is_grade_released((int) $USER->id)) {
                throw new \moodle_exception('feedback_not_available', 'local_unifiedgrader');
            }
        } else {
            // A teacher may only work with students they can see in this activity.
            \local_unifiedgrader\access::require_student_access($context, (int) $params['userid']);
        }

        // Release the PHP session lock so concurrent grader AJAX does not serialize
        // behind this request. This handler does not write to $SESSION.
        \core\session\manager::write_close();

        // Resolve the concrete attempt so markers stay consistent with the view
        // (and with what save_segment_comment stored). Falls back to the raw param.
        $attemptnumber = self::resolve_attempt($CFG, $context, $params['cmid'], $params['userid'], $params['attempt']);

        $records = segment_comment_manager::get_for(
            (int) $params['cmid'],
            (int) $params['userid'],
            $attemptnumber,
        );

        $comments = [];
        foreach ($records as $record) {
            $comments[] = [
                'id' => (int) $record->id,
                'segmentid' => (int) $record->segmentid,
                'currentsegmentid' => 0,
                'sourcetype' => (string) $record->sourcetype,
                'fileid' => (int) $record->fileid,
                'page' => (int) ($record->page ?? 0),
                'startoffset' => (int) $record->startoffset,
                'endoffset' => (int) $record->endoffset,
                'anchortext' => (string) $record->anchortext,
                'commenttext' => (string) $record->commenttext,
                'commentformat' => (int) $record->commentformat,
                'marktype' => (string) ($record->marktype ?? 'comment'),
                'color' => (string) ($record->color ?? ''),
                'authorid' => (int) $record->authorid,
                'authorfullname' => fullname($record),
                'timecreated' => (int) $record->timecreated,
                'timemodified' => (int) $record->timemodified,
            ];
        }

        return ['comments' => $comments];
    }

    /**
     * Resolve the concrete attempt number for an assignment submission.
     *
     * @param object $cfg The global $CFG.
     * @param \context_module $context The module context.
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @param int $attempt Attempt number (0-based) or -1 for latest.
     * @return int The resolved attempt number, or the raw param when unresolved.
     */
    private static function resolve_attempt($cfg, \context_module $context, int $cmid, int $userid, int $attempt): int {
        // The mod/assign locallib.php uses the global $CFG at load time; it must be
        // in scope here or it fatals when required from this function scope.
        global $CFG;
        $cm = get_coursemodule_from_id('', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm || $cm->modname !== 'assign') {
            return $attempt;
        }
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        [$course, $cminfo] = get_course_and_cm_from_cmid($cmid, 'assign');
        $assign = new \assign($context, $cminfo, $course);
        $submission = $assign->get_user_submission($userid, false, $attempt >= 0 ? $attempt : -1);
        return $submission ? (int) $submission->attemptnumber : $attempt;
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'comments' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Comment id'),
                    'segmentid' => new external_value(PARAM_INT, 'The nida FIELD segment id'),
                    'currentsegmentid' => new external_value(
                        PARAM_INT,
                        'Re-resolved render segment id (0 when the client matches by anchortext)',
                    ),
                    'sourcetype' => new external_value(PARAM_ALPHA, 'Source type: onlinetext or file'),
                    'fileid' => new external_value(PARAM_INT, '{files}.id for file sources, 0 for online text'),
                    'page' => new external_value(PARAM_INT, '1-based PDF page for file sources; 0 for online text'),
                    'startoffset' => new external_value(PARAM_INT, 'Anchor start offset in the source plaintext'),
                    'endoffset' => new external_value(PARAM_INT, 'Anchor end offset in the source plaintext'),
                    'anchortext' => new external_value(PARAM_RAW, 'The exact anchored source phrase (plaintext)'),
                    'commenttext' => new external_value(PARAM_RAW, 'The stored comment (cleaned HTML)'),
                    'commentformat' => new external_value(PARAM_INT, 'Comment text format'),
                    'marktype' => new external_value(
                        PARAM_ALPHA,
                        'Mark type: comment, tick, cross, highlight or query',
                        VALUE_DEFAULT,
                        'comment'
                    ),
                    'color' => new external_value(
                        PARAM_RAW,
                        'Marker colour (#RRGGBB) or empty for the default',
                        VALUE_DEFAULT,
                        ''
                    ),
                    'authorid' => new external_value(PARAM_INT, 'Author (grader) user id'),
                    'authorfullname' => new external_value(PARAM_NOTAGS, 'Author display name'),
                    'timecreated' => new external_value(PARAM_INT, 'Creation timestamp'),
                    'timemodified' => new external_value(PARAM_INT, 'Last modified timestamp'),
                ]),
                'The stored segment comments',
                VALUE_DEFAULT,
                [],
            ),
        ]);
    }
}
