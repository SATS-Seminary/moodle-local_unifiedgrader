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
 * External function: get the threaded context around a student's posts.
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
use local_unifiedgrader\adapter\forum_adapter;

/**
 * Returns every discussion the student took part in, in reading order.
 *
 * One call per student rather than one per post: the paged view walks the tree
 * client-side, so turning a page costs nothing.
 */
class get_post_context extends external_api {
    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'userid' => new external_value(PARAM_INT, 'Student user ID'),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @param int $userid
     * @return array
     */
    public static function execute(int $cmid, int $userid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);

        \core\session\manager::write_close();

        $adapter = adapter_factory::create($params['cmid']);
        if (!$adapter instanceof forum_adapter) {
            return ['discussions' => [], 'targetpostids' => []];
        }

        return $adapter->get_post_context($params['userid']);
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'discussions' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Discussion ID'),
                    'name' => new external_value(PARAM_TEXT, 'Discussion name'),
                    'posts' => new external_multiple_structure(
                        self::post_structure(),
                        'Every visible post, depth-first in reading order',
                    ),
                ]),
                'Discussions this student took part in',
            ),
            'targetpostids' => new external_multiple_structure(
                new external_value(PARAM_INT, 'Post ID'),
                'The student\'s own posts, in order — drives the pager',
            ),
        ]);
    }

    /**
     * Per-post structure for the threaded views.
     *
     * @return external_single_structure
     */
    private static function post_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Post ID'),
            'parent' => new external_value(PARAM_INT, 'Parent post ID, 0 for the thread root'),
            'depth' => new external_value(PARAM_INT, 'Nesting depth'),
            'discussionid' => new external_value(PARAM_INT, 'Discussion ID'),
            'subject' => new external_value(PARAM_TEXT, 'Post subject'),
            'message' => new external_value(PARAM_RAW, 'Formatted post HTML'),
            'authorname' => new external_value(PARAM_TEXT, 'Author full name'),
            'authorpicture' => new external_value(PARAM_URL, 'Author avatar URL', VALUE_DEFAULT, ''),
            'created' => new external_value(PARAM_INT, 'Creation timestamp'),
            'createddisplay' => new external_value(PARAM_TEXT, 'Formatted creation date'),
            'wordcount' => new external_value(PARAM_INT, 'Word count (graded student\'s posts only)', VALUE_DEFAULT, 0),
            'isstudent' => new external_value(PARAM_BOOL, 'Whether the graded student wrote it'),
            'isprompt' => new external_value(PARAM_BOOL, 'Whether it is the discussion\'s first post'),
            'hasrating' => new external_value(PARAM_BOOL, 'Whether rating state is attached', VALUE_DEFAULT, false),
            'rating' => new external_single_structure([
                'own' => new external_value(PARAM_INT, 'The current grader\'s rating', VALUE_DEFAULT, null, NULL_ALLOWED),
                'aggregate' => new external_value(PARAM_FLOAT, 'Aggregate', VALUE_DEFAULT, null, NULL_ALLOWED),
                'aggregatelabel' => new external_value(PARAM_TEXT, 'Aggregate formatted', VALUE_DEFAULT, ''),
                'count' => new external_value(PARAM_INT, 'Number of raters', VALUE_DEFAULT, 0),
                'canrate' => new external_value(PARAM_BOOL, 'Whether it may be rated', VALUE_DEFAULT, false),
                'noratereason' => new external_value(PARAM_TEXT, 'Why not', VALUE_DEFAULT, ''),
            ], 'Rating state, present only for the graded student\'s posts', VALUE_OPTIONAL),
        ]);
    }
}
