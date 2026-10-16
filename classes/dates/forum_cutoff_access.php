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
 * Lets a student with a forum extension keep posting after the forum's cut-off.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\dates;

/**
 * Gives a student with an extension past a forum's cut-off date the right to
 * post until their extension ends, and takes it away again.
 *
 * A forum's cut-off applies to everyone, and Moodle has no per-student version
 * of it the way assignments and quizzes have overrides. What it does have is a
 * capability, "Post to forums after their cut-off date", which teachers hold.
 * A permission override would give it to a whole role, so every student would
 * be able to post late. Instead the student is given a small role of their own,
 * holding only that capability, in that one forum, for as long as the extension
 * runs.
 *
 * The role assignment is made in this plugin's name, so it can be told apart
 * from anything a person assigned and is not removed by accident. A scheduled
 * task takes it away once the extension has passed.
 */
class forum_cutoff_access {
    /** @var string Short name of the role. */
    public const ROLE_SHORTNAME = 'ugforumextension';

    /** @var string The component the role assignments are made in the name of. */
    public const COMPONENT = 'local_unifiedgrader';

    /** @var string The capability the role carries. */
    public const CAPABILITY = 'mod/forum:canoverridecutoff';

    /**
     * The role's id, creating the role if the site does not have it yet.
     *
     * @return int
     */
    public static function roleid(): int {
        global $DB;

        $roleid = $DB->get_field('role', 'id', ['shortname' => self::ROLE_SHORTNAME]);
        return $roleid ? (int) $roleid : self::create_role();
    }

    /**
     * Create the role: posting after a forum's cut-off, and nothing else.
     *
     * It is given no context levels, so it never appears among the roles a
     * person can assign by hand. Only this plugin assigns it.
     *
     * @return int The new role's id.
     */
    public static function create_role(): int {
        $roleid = create_role(
            get_string('forumextension_role_name', 'local_unifiedgrader'),
            self::ROLE_SHORTNAME,
            get_string('forumextension_role_description', 'local_unifiedgrader'),
        );
        assign_capability(self::CAPABILITY, CAP_ALLOW, $roleid, \context_system::instance()->id, true);
        set_role_contextlevels($roleid, []);

        return (int) $roleid;
    }

    /**
     * Whether a student should be able to post after the cut-off right now.
     *
     * @param int $cutoff The forum's cut-off date, or 0 for none.
     * @param int $extension The student's extended due date, or 0 for none.
     * @param int $now
     * @return bool
     */
    public static function needs_access(int $cutoff, int $extension, int $now): bool {
        return $cutoff > 0 && $extension > $cutoff && $extension > $now;
    }

    /**
     * Give or take away a student's access to a forum after its cut-off, to
     * match their extension as it stands.
     *
     * @param int $cmid The forum's course module id.
     * @param int $userid
     * @param int|null $now
     * @return bool Whether the student now has the access.
     */
    public static function sync(int $cmid, int $userid, ?int $now = null): bool {
        global $DB;

        $now = $now ?? time();
        $cm = get_coursemodule_from_id('forum', $cmid, 0, false, IGNORE_MISSING);
        $context = $cm ? \context_module::instance($cmid, IGNORE_MISSING) : false;
        $extension = (int) $DB->get_field('local_unifiedgrader_fext', 'extensionduedate', ['cmid' => $cmid, 'userid' => $userid]);
        $cutoff = $cm ? (int) $DB->get_field('forum', 'cutoffdate', ['id' => $cm->instance]) : 0;

        $roleid = self::roleid();
        $assigned = $context && $DB->record_exists('role_assignments', ['roleid' => $roleid, 'userid' => $userid,
            'contextid' => $context->id, 'component' => self::COMPONENT]);

        if ($context && self::needs_access($cutoff, $extension, $now)) {
            if (!$assigned) {
                role_assign($roleid, $userid, $context->id, self::COMPONENT);
            }
            return true;
        }
        if ($assigned) {
            role_unassign($roleid, $userid, $context->id, self::COMPONENT);
        }
        return false;
    }

    /**
     * Bring every student's access into line with their extension.
     *
     * Takes the role away from anyone whose extension has ended, been
     * withdrawn, or been brought back inside the cut-off; and gives it to anyone
     * whose extension now passes the cut-off because the forum's cut-off was
     * moved earlier after the extension was granted.
     *
     * @param int|null $now
     * @return int[] Counts of role assignments 'removed' and 'added'.
     */
    public static function reconcile(?int $now = null): array {
        global $DB;

        $now = $now ?? time();
        $roleid = self::roleid();
        $counts = ['removed' => 0, 'added' => 0];

        // Everyone who has the role now.
        $held = $DB->get_records_sql(
            "SELECT ra.id, ra.userid, ra.contextid, ctx.instanceid AS cmid
               FROM {role_assignments} ra
               JOIN {context} ctx ON ctx.id = ra.contextid
              WHERE ra.roleid = :roleid AND ra.component = :component",
            ['roleid' => $roleid, 'component' => self::COMPONENT]
        );
        foreach ($held as $row) {
            if (!get_coursemodule_from_id('forum', (int) $row->cmid, 0, false, IGNORE_MISSING)) {
                // The forum has gone: the assignment has nothing left to grant.
                role_unassign($roleid, (int) $row->userid, (int) $row->contextid, self::COMPONENT);
                $counts['removed']++;
                continue;
            }
            if (!self::sync((int) $row->cmid, (int) $row->userid, $now)) {
                $counts['removed']++;
            }
        }

        // Everyone whose extension runs past the cut-off but who does not have it yet.
        $candidates = $DB->get_records_sql(
            "SELECT fe.id, fe.cmid, fe.userid
               FROM {local_unifiedgrader_fext} fe
               JOIN {course_modules} cm ON cm.id = fe.cmid
               JOIN {modules} m ON m.id = cm.module AND m.name = 'forum'
               JOIN {forum} f ON f.id = cm.instance
              WHERE f.cutoffdate > 0 AND fe.extensionduedate > f.cutoffdate AND fe.extensionduedate > :now",
            ['now' => $now]
        );
        foreach ($candidates as $row) {
            $context = \context_module::instance((int) $row->cmid, IGNORE_MISSING);
            $had = $context && $DB->record_exists('role_assignments', ['roleid' => $roleid, 'userid' => $row->userid,
                'contextid' => $context->id, 'component' => self::COMPONENT]);
            if (!$had && self::sync((int) $row->cmid, (int) $row->userid, $now)) {
                $counts['added']++;
            }
        }

        return $counts;
    }
}
