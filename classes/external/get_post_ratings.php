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
 * External function: get per-post ratings for a student.
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
 * Returns the rating state of each of a student's forum posts.
 */
class get_post_ratings extends external_api {
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
            return ['posts' => [], 'gradebookgrade' => null, 'gradebookdisplay' => '', 'aggregatelabel' => ''];
        }

        $posts = $adapter->get_post_ratings($params['userid']);
        $helper = $adapter->rating_helper();

        return [
            'posts' => $posts,
            'gradebookgrade' => $helper->get_gradebook_grade($params['userid']),
            'gradebookdisplay' => $helper->get_gradebook_display($params['userid']),
            'aggregatelabel' => $helper->get_aggregate_label(),
        ];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'posts' => new external_multiple_structure(self::post_structure(), 'One entry per post'),
            'gradebookgrade' => new external_value(
                PARAM_FLOAT,
                'The value mod_forum computed into the gradebook, or null',
                VALUE_DEFAULT,
                null,
                NULL_ALLOWED,
            ),
            'gradebookdisplay' => new external_value(PARAM_TEXT, 'That value formatted for display', VALUE_DEFAULT, ''),
            'aggregatelabel' => new external_value(PARAM_TEXT, 'How the ratings combine', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Shared per-post structure.
     *
     * @return external_single_structure
     */
    public static function post_structure(): external_single_structure {
        return new external_single_structure([
            'postid' => new external_value(PARAM_INT, 'Forum post ID'),
            'discussionid' => new external_value(PARAM_INT, 'Discussion ID'),
            'discussionname' => new external_value(PARAM_TEXT, 'Discussion name'),
            'subject' => new external_value(PARAM_TEXT, 'Post subject'),
            'created' => new external_value(PARAM_INT, 'Post creation timestamp'),
            'createddisplay' => new external_value(PARAM_TEXT, 'Formatted creation date'),
            'own' => new external_value(
                PARAM_INT,
                'The rating the current grader gave, or null',
                VALUE_DEFAULT,
                null,
                NULL_ALLOWED,
            ),
            'aggregate' => new external_value(
                PARAM_FLOAT,
                'Aggregate across all raters, or null',
                VALUE_DEFAULT,
                null,
                NULL_ALLOWED,
            ),
            'aggregatelabel' => new external_value(PARAM_TEXT, 'Aggregate formatted for display', VALUE_DEFAULT, ''),
            'count' => new external_value(PARAM_INT, 'How many people have rated this post', VALUE_DEFAULT, 0),
            'canrate' => new external_value(PARAM_BOOL, 'Whether this post may be rated now', VALUE_DEFAULT, false),
            'noratereason' => new external_value(PARAM_TEXT, 'Why it may not be rated', VALUE_DEFAULT, ''),
        ]);
    }
}
