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
 * Uninstall steps for local_unifiedgrader.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Remove the forum extension role, and with it every assignment of it.
 *
 * @return bool
 */
function xmldb_local_unifiedgrader_uninstall() {
    global $DB;

    $roleid = $DB->get_field('role', 'id', ['shortname' => \local_unifiedgrader\dates\forum_cutoff_access::ROLE_SHORTNAME]);
    if ($roleid) {
        delete_role((int) $roleid);
    }
    return true;
}
