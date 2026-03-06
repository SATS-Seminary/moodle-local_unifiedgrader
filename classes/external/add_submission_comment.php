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
 * External function: add a submission comment.
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
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Adds a comment to a student's assignment submission.
 */
class add_submission_comment extends external_api {

    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
            'content' => new external_value(PARAM_RAW, 'Comment content'),
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
     * @param string $content
     * @return array
     */
    public static function execute(int $cmid, int $userid, string $content, int $attemptnumber = -1): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
            'content' => $content,
            'attemptnumber' => $attemptnumber,
        ]);

        global $USER;

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);

        // Allow teachers (grade) or students posting on their own submission (viewfeedback).
        $hasgrade = has_capability('local/unifiedgrader:grade', $context);
        $hasviewfeedback = has_capability('local/unifiedgrader:viewfeedback', $context);
        if (!$hasgrade && !$hasviewfeedback) {
            require_capability('local/unifiedgrader:grade', $context);
        }
        // Students can only post on their own submission.
        if (!$hasgrade && (int) $params['userid'] !== (int) $USER->id) {
            throw new \moodle_exception('nopermission', 'local_unifiedgrader');
        }

        [$course, $cm] = get_course_and_cm_from_cmid($params['cmid']);

        // Submission comments are only supported for assignments.
        if ($cm->modname !== 'assign') {
            throw new \moodle_exception('invalidactivitytype', 'local_unifiedgrader');
        }

        $assign = new \assign($context, $cm, $course);
        $submission = $assign->get_user_submission($params['userid'], false, $params['attemptnumber']);

        if (!$submission) {
            throw new \moodle_exception('nosubmission', 'local_unifiedgrader');
        }

        $options = new \stdClass();
        $options->context = $context;
        $options->component = 'assignsubmission_comments';
        $options->itemid = $submission->id;
        $options->area = 'submission_comments';
        $options->course = $course;
        $options->cm = $cm;

        $commentobj = new \comment($options);

        if (!$commentobj->can_post()) {
            throw new \moodle_exception('nopermissiontocomment', 'comment');
        }

        $newcomment = $commentobj->add($params['content']);

        return [
            'id' => (int) $newcomment->id,
            'content' => $newcomment->content,
            'fullname' => $newcomment->fullname ?? '',
            'time' => $newcomment->time ?? '',
            'count' => $commentobj->count(),
        ];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'New comment ID'),
            'content' => new external_value(PARAM_RAW, 'Comment content'),
            'fullname' => new external_value(PARAM_TEXT, 'Author full name'),
            'time' => new external_value(PARAM_TEXT, 'Human-readable time'),
            'count' => new external_value(PARAM_INT, 'Updated total comment count'),
        ]);
    }
}
