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
 * Backup of the activity settings Unified Grader adds.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Backs up an activity's late penalty switch with the activity.
 *
 * Only a saved value is backed up. An activity without one restores without
 * one too, and keeps using its type's default.
 */
class backup_local_unifiedgrader_plugin extends backup_local_plugin {
    /**
     * Define the structure added to each activity's backup.
     *
     * @return backup_plugin_element
     */
    protected function define_module_plugin_structure() {
        $plugin = $this->get_plugin_element();

        $wrapper = new backup_nested_element($this->get_recommended_name());
        $latepenalty = new backup_nested_element('latepenalty', ['id'], ['enabled']);

        $plugin->add_child($wrapper);
        $wrapper->add_child($latepenalty);

        $latepenalty->set_source_table('local_unifiedgrader_penset', ['cmid' => backup::VAR_MODID]);

        return $plugin;
    }
}
