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
 * One-off sync of assignment comments with core's submission comments.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\task;

use local_unifiedgrader\assign_comment_sync;

/**
 * Links, imports and pushes every assignment's submission comments.
 *
 * Queued by the upgrade that added the link (issue #24). Nobody is notified.
 */
class sync_assign_comments extends \core\task\adhoc_task {
    /**
     * Run the task.
     */
    public function execute(): void {
        $totals = assign_comment_sync::reconcile_all();
        mtrace("Assignment comments: {$totals['linked']} linked, {$totals['imported']} copied from core, "
            . "{$totals['pushed']} written to core.");
    }
}
