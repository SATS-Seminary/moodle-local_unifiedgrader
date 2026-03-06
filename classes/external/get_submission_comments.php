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
 * External function: get submission comments.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/comment/lib.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Returns submission comments for a student's assignment submission.
 */
class get_submission_comments extends external_api {

    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
            'page' => new external_value(PARAM_INT, 'Page number (0-based)', VALUE_DEFAULT, 0),
            'attemptnumber' => new external_value(
                PARAM_INT, 'Attempt number (0-based), -1 for latest', VALUE_DEFAULT, -1
            ),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @param int $userid
     * @param int $page
     * @return array
     */
    public static function execute(int $cmid, int $userid, int $page = 0, int $attemptnumber = -1): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
            'page' => $page,
            'attemptnumber' => $attemptnumber,
        ]);

        global $USER;

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);

        // Allow teachers (grade) or students viewing their own feedback (viewfeedback).
        $hasgrade = has_capability('local/unifiedgrader:grade', $context);
        $hasviewfeedback = has_capability('local/unifiedgrader:viewfeedback', $context);
        if (!$hasgrade && !$hasviewfeedback) {
            require_capability('local/unifiedgrader:grade', $context);
        }
        // Students can only view their own comments.
        if (!$hasgrade && (int) $params['userid'] !== (int) $USER->id) {
            throw new \moodle_exception('nopermission', 'local_unifiedgrader');
        }

        [$course, $cm] = get_course_and_cm_from_cmid($params['cmid']);

        // Submission comments are only supported for assignments.
        if ($cm->modname !== 'assign') {
            return [
                'comments' => [],
                'count' => 0,
                'canpost' => false,
            ];
        }

        $assign = new \assign($context, $cm, $course);
        $submission = $assign->get_user_submission($params['userid'], false, $params['attemptnumber']);

        if (!$submission) {
            return [
                'comments' => [],
                'count' => 0,
                'canpost' => false,
            ];
        }

        $options = new \stdClass();
        $options->context = $context;
        $options->component = 'assignsubmission_comments';
        $options->itemid = $submission->id;
        $options->area = 'submission_comments';
        $options->course = $course;
        $options->cm = $cm;

        $commentobj = new \comment($options);
        $comments = $commentobj->get_comments($params['page'], 'ASC');

        // Format the comments for output.
        $result = [];
        if ($comments !== false) {
            foreach ($comments as $c) {
                $result[] = [
                    'id' => (int) $c->id,
                    'content' => $c->content,
                    'fullname' => $c->fullname ?? '',
                    'avatar' => $c->avatar ?? '',
                    'time' => $c->time ?? '',
                    'timecreated' => (int) ($c->timecreated ?? 0),
                    'userid' => (int) ($c->userid ?? 0),
                    'candelete' => !empty($c->delete),
                ];
            }
        }

        return [
            'comments' => $result,
            'count' => $commentobj->count(),
            'canpost' => $commentobj->can_post(),
        ];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'comments' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Comment ID'),
                    'content' => new external_value(PARAM_RAW, 'Comment content'),
                    'fullname' => new external_value(PARAM_TEXT, 'Author full name'),
                    'avatar' => new external_value(PARAM_RAW, 'Author avatar HTML'),
                    'time' => new external_value(PARAM_TEXT, 'Human-readable time'),
                    'timecreated' => new external_value(PARAM_INT, 'Timestamp'),
                    'userid' => new external_value(PARAM_INT, 'Author user ID'),
                    'candelete' => new external_value(PARAM_BOOL, 'Whether current user can delete this comment'),
                ]),
            ),
            'count' => new external_value(PARAM_INT, 'Total comment count'),
            'canpost' => new external_value(PARAM_BOOL, 'Whether the user can post comments'),
        ]);
    }
}
