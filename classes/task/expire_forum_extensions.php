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
 * Ends forum access after the cut-off once a student's extension is over.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\task;

use local_unifiedgrader\dates\forum_cutoff_access;

/**
 * Takes away the role that lets a student post after a forum's cut-off once
 * their extension has ended. Runs every quarter of an hour, so a student can
 * post for at most that long after their extension.
 */
class expire_forum_extensions extends \core\task\scheduled_task {
    /**
     * Name shown in the task list.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_expire_forum_extensions', 'local_unifiedgrader');
    }

    /**
     * Bring every forum extension's access into line.
     */
    public function execute(): void {
        $counts = forum_cutoff_access::reconcile();
        mtrace("Forum extensions past the cut-off: {$counts['removed']} ended, {$counts['added']} started.");
    }
}
