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
 * External function: rate a forum post.
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
use local_unifiedgrader\adapter\adapter_factory;
use local_unifiedgrader\adapter\forum_adapter;

/**
 * Records the current user's rating on a single forum post.
 */
class save_post_rating extends external_api {
    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'postid' => new external_value(PARAM_INT, 'Forum post ID'),
            'rating' => new external_value(PARAM_INT, 'Scale value, or -999 (RATING_UNSET_RATING) to clear'),
        ]);
    }

    /**
     * Execute the function.
     *
     * A rating that core rejects — an out-of-window post, a lost race with a
     * changed setting — comes back as a failed result rather than an exception,
     * so one bad row does not take the whole panel down with it.
     *
     * @param int $cmid
     * @param int $postid
     * @param int $rating
     * @return array
     */
    public static function execute(int $cmid, int $postid, int $rating): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'postid' => $postid,
            'rating' => $rating,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);

        $adapter = adapter_factory::create($params['cmid']);
        if (!$adapter instanceof forum_adapter) {
            return self::failure(get_string('rating_notratingforum', 'local_unifiedgrader'));
        }

        try {
            $result = $adapter->save_post_rating($params['postid'], $params['rating']);
        } catch (\moodle_exception $e) {
            return self::failure($e->getMessage());
        }

        return [
            'success' => true,
            'error' => '',
            'postid' => (int) $result['postid'],
            'own' => $result['own'],
            'aggregate' => $result['aggregate'],
            'aggregatelabel' => (string) $result['aggregatelabel'],
            'count' => (int) $result['count'],
            'gradebookgrade' => $result['gradebookgrade'],
            'gradebookdisplay' => (string) $result['gradebookdisplay'],
        ];
    }

    /**
     * Build a failed response.
     *
     * @param string $message
     * @return array
     */
    private static function failure(string $message): array {
        return [
            'success' => false,
            'error' => $message,
            'postid' => 0,
            'own' => null,
            'aggregate' => null,
            'aggregatelabel' => '',
            'count' => 0,
            'gradebookgrade' => null,
            'gradebookdisplay' => '',
        ];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the rating was recorded'),
            'error' => new external_value(PARAM_TEXT, 'Why it was not', VALUE_DEFAULT, ''),
            'postid' => new external_value(PARAM_INT, 'Forum post ID', VALUE_DEFAULT, 0),
            'own' => new external_value(
                PARAM_INT,
                'The current grader\'s rating after the write, or null if cleared',
                VALUE_DEFAULT,
                null,
                NULL_ALLOWED,
            ),
            'aggregate' => new external_value(
                PARAM_FLOAT,
                'Fresh aggregate across all raters',
                VALUE_DEFAULT,
                null,
                NULL_ALLOWED,
            ),
            'aggregatelabel' => new external_value(PARAM_TEXT, 'Aggregate formatted for display', VALUE_DEFAULT, ''),
            'count' => new external_value(PARAM_INT, 'How many people have rated this post', VALUE_DEFAULT, 0),
            'gradebookgrade' => new external_value(
                PARAM_FLOAT,
                'The student\'s recomputed gradebook value',
                VALUE_DEFAULT,
                null,
                NULL_ALLOWED,
            ),
            'gradebookdisplay' => new external_value(PARAM_TEXT, 'That value formatted', VALUE_DEFAULT, ''),
        ]);
    }
}
