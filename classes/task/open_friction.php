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
 * Daily fail-safe for Friction Feedback.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\task;

use local_unifiedgrader\friction\service;

/**
 * Opens marks the student has not read once a fail-safe clock is due.
 */
class open_friction extends \core\task\scheduled_task {
    /**
     * Name shown in the task list.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_open_friction', 'local_unifiedgrader');
    }

    /**
     * Run the task.
     */
    public function execute(): void {
        service::open_due();
    }
}
