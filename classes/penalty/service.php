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
 * Keeps late penalties and the gradebook in step (Moodle 5.3+).
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\penalty;

use local_unifiedgrader\adapter\adapter_factory;
use local_unifiedgrader\penalty_manager;

/**
 * One entry point for recalculating a student's penalties.
 *
 * Every trigger (a mark saved in the grader or on a core page, an extension or
 * override, a quiz attempt, a changed due date) ends up here. Resyncing works
 * the late penalty out afresh from the rules and the effective due date, stores
 * it as the student's 'late' penalty row, and writes the total of all their
 * penalty rows to the gradebook. Running it twice changes nothing.
 */
class service {
    /** @var bool True while a resync is running, so its own grade events are ignored. */
    private static bool $syncing = false;

    /**
     * Whether a resync is running in this request.
     *
     * The observer checks this to ignore the user_graded events a resync
     * raises itself.
     *
     * @return bool
     */
    public static function is_syncing(): bool {
        return self::$syncing;
    }

    /**
     * Recalculate one student's penalties and update the gradebook.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @param bool $pushraw True to push the activity's raw mark first. The
     *        observer passes false: the activity has just pushed it.
     * @param \local_unifiedgrader\adapter\base_adapter|null $adapter The activity's
     *        adapter, when the caller resyncs many students and already has one.
     */
    public static function resync(
        int $cmid,
        int $userid,
        bool $pushraw = true,
        ?\local_unifiedgrader\adapter\base_adapter $adapter = null,
    ): void {
        if (!compat::unified() || self::$syncing) {
            return;
        }

        $adapter ??= adapter_factory::create($cmid);
        if (!activity_settings::supports($adapter->get_type())) {
            return;
        }

        self::$syncing = true;
        try {
            $late = $adapter->calculate_late_penalty($userid);
            penalty_manager::sync_late_penalty(
                $cmid,
                $userid,
                $late['percentage'] ?? null,
                $late['dayslate'] ?? 0,
            );
            if ($pushraw) {
                $adapter->sync_gradebook_penalty($userid);
            } else {
                $adapter->apply_gradebook_deduction($userid);
            }
        } finally {
            self::$syncing = false;
        }
    }

    /**
     * Recalculate penalties for many students of an activity, in the background.
     *
     * Used when something changes for a whole activity or a group: a due date,
     * the penalty switch, or a group override.
     *
     * @param int $cmid Course module ID.
     * @param int[] $userids Students to resync; empty means everyone with a grade or attempt.
     */
    public static function resync_later(int $cmid, array $userids = []): void {
        if (!compat::unified()) {
            return;
        }
        $task = new \local_unifiedgrader\task\resync_penalties();
        $task->set_custom_data([
            'cmid' => $cmid,
            'userids' => array_values(array_map('intval', $userids)),
        ]);
        \core\task\manager::queue_adhoc_task($task, true);
    }
}
