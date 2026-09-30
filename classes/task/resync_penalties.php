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
 * Background recalculation of an activity's penalties.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\task;

use local_unifiedgrader\penalty\service;

/**
 * Resyncs penalties for many students of one activity.
 *
 * Queued when something changes for a whole activity or group: its due date,
 * its penalty switch, or a group override. Custom data: cmid, and userids
 * (empty for everyone who has a grade or a penalty row in the activity).
 */
class resync_penalties extends \core\task\adhoc_task {
    /**
     * Run the task.
     */
    public function execute(): void {
        $data = $this->get_custom_data();
        $cmid = (int) ($data->cmid ?? 0);
        $cm = $cmid ? get_coursemodule_from_id('', $cmid) : false;
        if (!$cm) {
            return;
        }

        $userids = array_map('intval', (array) ($data->userids ?? []));
        if (!$userids) {
            $userids = self::users_to_resync($cm);
        }

        foreach ($userids as $userid) {
            service::resync($cmid, $userid);
        }
    }

    /**
     * Everyone in the activity whose penalties could change.
     *
     * Students with a raw grade on any of its grade items, plus anyone who
     * already has a penalty row (so a penalty that no longer applies is removed).
     *
     * @param \stdClass $cm Course module record, with modname and instance.
     * @return int[]
     */
    public static function users_to_resync(\stdClass $cm): array {
        global $DB;

        $sql = "SELECT DISTINCT gg.userid
                  FROM {grade_grades} gg
                  JOIN {grade_items} gi ON gi.id = gg.itemid
                 WHERE gi.itemtype = 'mod' AND gi.itemmodule = :modname AND gi.iteminstance = :instance
                   AND gg.rawgrade IS NOT NULL
                 UNION
                SELECT DISTINCT p.userid
                  FROM {local_unifiedgrader_penalty} p
                 WHERE p.cmid = :cmid";
        return array_map('intval', $DB->get_fieldset_sql($sql, [
            'modname' => $cm->modname,
            'instance' => $cm->instance,
            'cmid' => $cm->id,
        ]));
    }
}
