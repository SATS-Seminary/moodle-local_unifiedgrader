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
 * External function: refresh cached BBB engagement metrics by scraping
 * each recording's statistics playback page.
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
use local_unifiedgrader\bbb\engagement_service;

/**
 * Triggers a fresh scrape of BBB recording statistics pages and reports
 * how many participants were matched / unmatched.
 */
class refresh_bbb_engagement extends external_api {
    /**
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID of a BigBlueButton activity'),
        ]);
    }

    /**
     * Execute the refresh.
     *
     * @param int $cmid
     * @return array
     */
    public static function execute(int $cmid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);

        $cm = get_coursemodule_from_id('', $params['cmid'], 0, false, MUST_EXIST);
        if ($cm->modname !== 'bigbluebuttonbn') {
            throw new \moodle_exception('invalidactivitytype', 'local_unifiedgrader');
        }

        $stats = engagement_service::refresh_for_cmid($params['cmid']);
        return [
            'success' => true,
            'recordings' => $stats['recordings'],
            'parsed' => $stats['parsed'],
            'matched' => $stats['matched'],
            'unmatched' => $stats['unmatched'],
        ];
    }

    /**
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the refresh ran'),
            'recordings' => new external_value(PARAM_INT, 'Total recordings inspected'),
            'parsed' => new external_value(PARAM_INT, 'Recordings whose statistics page parsed successfully'),
            'matched' => new external_value(PARAM_INT, 'Participants matched to a Moodle user by fullname'),
            'unmatched' => new external_value(PARAM_INT, 'Participants without a Moodle user match'),
        ]);
    }
}
