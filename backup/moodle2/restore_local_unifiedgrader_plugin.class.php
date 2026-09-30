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
 * Restore of the activity settings Unified Grader adds.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Restores an activity's late penalty switch.
 */
class restore_local_unifiedgrader_plugin extends restore_local_plugin {
    /**
     * Paths to process in each activity's backup.
     *
     * @return restore_path_element[]
     */
    protected function define_module_plugin_structure() {
        return [
            new restore_path_element('local_unifiedgrader_latepenalty', $this->get_pathfor('/latepenalty')),
        ];
    }

    /**
     * Save the switch against the restored activity.
     *
     * @param array $data The backed-up row.
     */
    public function process_local_unifiedgrader_latepenalty($data): void {
        $data = (object) $data;
        \local_unifiedgrader\penalty\activity_settings::set_enabled(
            (int) $this->task->get_moduleid(),
            !empty($data->enabled),
        );
    }
}
