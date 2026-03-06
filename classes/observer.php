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
 * Event observer callbacks for local_unifiedgrader.
 *
 * These observers keep the plugin in sync when grades or submissions change
 * through native activity UIs. In Phase 1 they are stubs for future
 * cache invalidation and real-time update logic.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer callbacks for keeping the plugin in sync with native activity UIs.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /**
     * Handle assignment submission graded event.
     *
     * @param \mod_assign\event\submission_graded $event
     */
    public static function handle_submission_graded(\mod_assign\event\submission_graded $event): void {
        // Future: invalidate cached participant/grade data for this activity.
    }

    /**
     * Handle core user graded event.
     *
     * @param \core\event\user_graded $event
     */
    public static function handle_user_graded(\core\event\user_graded $event): void {
        // Future: invalidate cached data when a grade changes via gradebook.
    }

    /**
     * Handle assignment submission created event.
     *
     * @param \mod_assign\event\submission_created $event
     */
    public static function handle_submission_created(\mod_assign\event\submission_created $event): void {
        // Future: invalidate cached participant data for this activity.
    }

    /**
     * Handle assignment submission updated event.
     *
     * @param \mod_assign\event\submission_updated $event
     */
    public static function handle_submission_updated(\mod_assign\event\submission_updated $event): void {
        // Future: invalidate cached submission data for this activity.
    }
}
