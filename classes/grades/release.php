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
 * Per-student grade release.
 *
 * The grade item's hidden flag still covers the whole class. A posted student
 * or group is a grade_grades.hidden value on that student's cell. While the
 * item itself is hidden it covers every cell, so a partial post copies the
 * item's hidden value onto the existing cells and only then reveals the item.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\grades;

use local_unifiedgrader\access;

/**
 * Reads and writes who can see their grade.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class release {
    /** @var bool True while this class is writing hidden flags, so the observer does not re-enter. */
    private static bool $sealing = false;

    /** @var array<int,true> User ids whose cell was set visible during this request. */
    private static array $releasednow = [];

    /**
     * Whether a hidden-flag write is in progress.
     *
     * @return bool
     */
    public static function is_sealing(): bool {
        return self::$sealing;
    }

    /**
     * Post, hide, or schedule grades for the class or for a list of students.
     *
     * An empty user list is the whole class. A list, while the item is hidden,
     * copies that hidden value onto every existing cell before the item is
     * revealed, so one failed step cannot show the rest of the class. Quizzes
     * pass $liftitem false: their item flag belongs to the review options.
     *
     * @param \local_unifiedgrader\adapter\base_adapter $adapter
     * @param int $hidden 0 post, 1 hide, or a Unix timestamp.
     * @param int[] $userids Empty for the whole class.
     * @param bool $liftitem Whether a partial post may reveal the grade item.
     * @return array{posted:bool,partial:bool,hidden:int,postedcount:int,total:int,lifted:bool}
     */
    public static function apply($adapter, int $hidden, array $userids, bool $liftitem = true): array {
        $userids = self::clean_ids($userids);
        $item = $adapter->get_grade_item();
        if (!$item) {
            $status = self::summarise($adapter);
            $status['lifted'] = false;
            return $status;
        }
        if ($hidden < 0) {
            $hidden = 1;
        }

        self::$sealing = true;
        $lifted = false;
        try {
            // Hiding a student while the item is already hidden changes nothing.
            $covered = $userids && $hidden === 1 && $item->is_hidden();
            if (!$userids) {
                if ($hidden === 0) {
                    // Clear cells before the item, so a previously hidden cell is not
                    // the only one on show for the moment between the two writes.
                    self::set_every_cell($item, 0);
                    $item->set_hidden(0);
                } else {
                    // A class hide or schedule leaves the cells as they are. The item covers everyone.
                    $item->set_hidden($hidden);
                }
            } else if (!$covered && $item->is_hidden() && $liftitem) {
                // The item flag covers students who have no cell yet. Revealing it
                // would publish them, so give each of them the item's hidden value
                // first. This is the whole class, not the groups the current user
                // can see: a separate-groups teacher must not uncover another group.
                $stamp = (int) $item->get_hidden();
                $hold = $stamp === 0 ? 1 : $stamp;
                self::set_every_cell($item, $hold);
                $releasing = array_flip($userids);
                foreach (self::course_student_ids($adapter) as $userid) {
                    if (!isset($releasing[$userid])) {
                        self::write_cell($item, $userid, $hold);
                    }
                }
                $item->set_hidden(0);
                foreach ($userids as $userid) {
                    self::write_cell($item, $userid, $hidden);
                }
                $lifted = true;
            } else if (!$covered) {
                foreach ($userids as $userid) {
                    self::write_cell($item, $userid, $hidden);
                }
            }
        } finally {
            self::$sealing = false;
        }

        $status = self::summarise($adapter);
        $status['lifted'] = $lifted;
        return $status;
    }

    /**
     * How many of the visible students currently have a released grade.
     *
     * No students, a visible item, and no hidden cell counts as posted. That
     * keeps an activity with nobody enrolled looking the way it did before
     * cells were consulted.
     *
     * @param \local_unifiedgrader\adapter\base_adapter $adapter
     * @return array{posted:bool,partial:bool,hidden:int,postedcount:int,total:int,lifted:bool}
     */
    public static function summarise($adapter): array {
        global $DB;

        $item = $adapter->get_grade_item();
        if (!$item) {
            return self::empty_status(true);
        }

        $itemhidden = (int) $item->get_hidden();
        $students = self::student_ids($adapter);
        $total = count($students);
        $selective = self::is_selective($item);
        $cells = $total
            ? $DB->get_records_menu('grade_grades', ['itemid' => $item->id], '', 'userid, hidden')
            : [];

        // A friction-held cell is hidden from the student and still posted for the
        // teacher. The eye stays open, and the class counts that student as posted.
        // A finished quiz walk stays posted too: the item flag belongs to the
        // review options and can stay hidden after that student was released.
        $pending = array_flip(\local_unifiedgrader\friction\service::pending_userids((int) $item->id));
        $released = [];
        if ($itemhidden === 1 && $adapter->release_outlives_hidden_item()) {
            $released = array_flip(\local_unifiedgrader\friction\service::released_userids((int) $item->id));
        }
        $postedcount = 0;
        foreach ($students as $userid) {
            if (isset($pending[$userid]) || isset($released[$userid])) {
                $postedcount++;
                continue;
            }
            $cell = array_key_exists($userid, $cells) ? (int) $cells[$userid] : null;
            if (self::is_visible_value(self::effective_hidden($itemhidden, $cell, $selective))) {
                $postedcount++;
            }
        }

        $posted = $total === 0
            ? (!$item->is_hidden() && !$selective)
            : ($postedcount === $total);

        return [
            'posted' => $posted,
            'partial' => $postedcount > 0 && $postedcount < $total,
            'hidden' => $itemhidden,
            'postedcount' => $postedcount,
            'total' => $total,
            'lifted' => false,
        ];
    }

    /**
     * Whether release is decided cell by cell.
     *
     * True when the item itself is visible and some other cell is still hidden
     * (always, or until a future time). A timestamp that has already passed is
     * visible, so it does not keep the activity in this mode.
     *
     * @param \grade_item $item
     * @param int|null $exceptuserid Ignore this student's own cell.
     * @return bool
     */
    public static function is_selective(\grade_item $item, ?int $exceptuserid = null): bool {
        global $DB;

        if ($item->is_hidden()) {
            return false;
        }
        $params = ['itemid' => $item->id, 'now' => time()];
        $sql = "SELECT COUNT(1)
                  FROM {grade_grades}
                 WHERE itemid = :itemid
                   AND (hidden = 1 OR hidden > :now)";
        if ($exceptuserid) {
            $sql .= " AND userid <> :userid";
            $params['userid'] = $exceptuserid;
        }
        return $DB->count_records_sql($sql, $params) > 0;
    }

    /**
     * Whether a grade that has just been written should be hidden.
     *
     * A new cell is hidden when any other cell on the item is hidden and this
     * student was not released on purpose during the request. An update of a
     * grade that is already released stays visible. The decision is made before
     * a penalty resync adds another grade-history row.
     *
     * @param \core\event\user_graded $event
     * @return bool
     */
    public static function should_seal(\core\event\user_graded $event): bool {
        global $CFG, $DB;

        if (self::$sealing) {
            return false;
        }
        $userid = (int) $event->relateduserid;
        if ($userid <= 0 || isset(self::$releasednow[$userid])) {
            return false;
        }

        $grade = $event->get_grade();
        if (!$grade || (int) $grade->hidden !== 0) {
            return false;
        }
        $item = \grade_item::fetch(['id' => $grade->itemid]);
        if (!$item || $item->itemtype !== 'mod') {
            return false;
        }
        if (!in_array($item->itemmodule, ['assign', 'forum', 'quiz', 'bigbluebuttonbn'], true)) {
            return false;
        }
        if (!self::is_selective($item, $userid)) {
            return false;
        }

        if (empty($CFG->disablegradehistory)) {
            $history = $DB->count_records('grade_grades_history', ['oldid' => $grade->id]);
            if ($history > 1) {
                return false;
            }
        } else if (abs((int) $grade->timecreated - (int) $grade->timemodified) >= 2) {
            return false;
        }
        return true;
    }

    /**
     * Hide a grade cell that should not have been born visible.
     *
     * Reloads the row first. A penalty resync in the same request may have
     * written the grade again after the decision to seal it.
     *
     * @param \grade_grade $grade
     */
    public static function seal(\grade_grade $grade): void {
        self::$sealing = true;
        try {
            $fresh = \grade_grade::fetch(['id' => $grade->id]);
            if (!$fresh || (int) $fresh->hidden !== 0) {
                return;
            }
            if (isset(self::$releasednow[(int) $fresh->userid])) {
                return;
            }
            $fresh->set_hidden(1);
        } finally {
            self::$sealing = false;
        }
    }

    /**
     * Set the hidden value on every existing cell of an item.
     *
     * Does not create rows for students who have no cell yet.
     *
     * @param \grade_item $item
     * @param int $hidden
     */
    public static function set_every_cell(\grade_item $item, int $hidden): void {
        $grades = \grade_grade::fetch_all(['itemid' => $item->id]);
        if (!$grades) {
            return;
        }
        foreach ($grades as $grade) {
            if ((int) $grade->hidden !== $hidden) {
                $grade->set_hidden($hidden);
            }
            self::remember((int) $grade->userid, $hidden);
        }
    }

    /**
     * The hidden value a student experiences, combining the item and the cell.
     *
     * Mirrors {@see \grade_grade::get_hidden()} for a cell that exists. A missing
     * cell follows the item, except while release is selective: that student has
     * not been posted, so the value is "always hidden".
     *
     * @param int $itemhidden
     * @param int|null $cellhidden Null when the student has no grade_grades row.
     * @param bool $selective
     * @return int
     */
    public static function effective_hidden(int $itemhidden, ?int $cellhidden, bool $selective): int {
        if ($cellhidden === null) {
            if ($itemhidden !== 0 && !($itemhidden > 1 && $itemhidden <= time())) {
                return $itemhidden;
            }
            return $selective ? 1 : 0;
        }
        if ($itemhidden === 1) {
            return 1;
        }
        if ($itemhidden === 0) {
            return $cellhidden;
        }
        if ($cellhidden === 0) {
            return $itemhidden;
        }
        if ($cellhidden === 1) {
            return 1;
        }
        return $cellhidden > $itemhidden ? $cellhidden : $itemhidden;
    }

    /**
     * Whether a hidden value leaves the grade visible at this moment.
     *
     * @param int $hidden
     * @return bool
     */
    public static function is_visible_value(int $hidden): bool {
        return $hidden === 0 || ($hidden > 1 && $hidden <= time());
    }

    /**
     * Gradebook-role students the current user may release, one id each.
     *
     * Separate groups confine the list to the user's own groups. An empty
     * result from that check means the user may see nobody.
     *
     * @param \local_unifiedgrader\adapter\base_adapter $adapter
     * @return int[]
     */
    public static function student_ids($adapter): array {
        $context = $adapter->get_context();
        $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, MUST_EXIST);
        $groupids = access::visible_group_ids($cm, $context, []);
        if ($groupids === null) {
            return [];
        }
        $ids = self::course_student_ids($adapter);
        if (!$groupids) {
            return $ids;
        }
        return array_values(array_intersect($ids, self::member_ids($groupids)));
    }

    /**
     * Every gradebook-role student enrolled in the course, ignoring groups.
     *
     * Used when a partial post reveals the grade item, which would otherwise
     * publish a student in a group the current user cannot see.
     *
     * @param \local_unifiedgrader\adapter\base_adapter $adapter
     * @return int[]
     */
    private static function course_student_ids($adapter): array {
        global $CFG, $DB;

        $context = $adapter->get_context();
        $roleids = array_filter(array_map('intval', explode(',', (string) ($CFG->gradebookroles ?? ''))));
        if (!$roleids) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);
        $params['contextid'] = $context->get_course_context()->id;
        $roleusers = array_map('intval', $DB->get_fieldset_sql(
            "SELECT DISTINCT ra.userid
               FROM {role_assignments} ra
              WHERE ra.contextid = :contextid
                AND ra.roleid $insql",
            $params,
        ));
        $enrolled = get_enrolled_users($context, '', 0, 'u.id', null, 0, 0, true);
        return array_values(array_intersect($roleusers, array_map('intval', array_keys($enrolled))));
    }

    /**
     * Gradebook-role students who belong to any of the groups, one id each.
     *
     * @param \local_unifiedgrader\adapter\base_adapter $adapter
     * @param int[] $groupids
     * @return int[]
     */
    public static function students_in_groups($adapter, array $groupids): array {
        $groupids = self::clean_ids($groupids);
        if (!$groupids) {
            return [];
        }
        return array_values(array_intersect(self::student_ids($adapter), self::member_ids($groupids)));
    }

    /**
     * Show these students in the gradebook, including when the grade item is hidden.
     *
     * @param \local_unifiedgrader\adapter\base_adapter $adapter
     * @param int[] $userids
     */
    public static function reveal_students($adapter, array $userids): void {
        $item = $adapter->get_grade_item();
        if (!$item) {
            return;
        }
        self::reveal_item($item, $userids);
    }

    /**
     * Show these students on one grade item, including when that item is hidden.
     *
     * Moodle hides a grade when the item is hidden, whatever the cell says. A quiz
     * keeps that item hidden from its review options, and the gradebook will not
     * show the item while the activity owns the flag. This copies the item's
     * hidden value onto every other student, then shows the item and these cells.
     * Students who already finished a walk stay open. Review options are not
     * touched. The course module is not loaded, so an upgrade can call this.
     *
     * @param \grade_item $item
     * @param int[] $userids
     */
    public static function reveal_item(\grade_item $item, array $userids): void {
        $userids = self::clean_ids($userids);
        if (!$userids) {
            return;
        }
        // A quiz sync can hide the item again between two students finishing.
        // Keep everyone who is already released in the open set.
        $userids = self::clean_ids(array_merge(
            $userids,
            \local_unifiedgrader\friction\service::released_userids((int) $item->id),
        ));
        if (!$item->is_hidden()) {
            foreach ($userids as $userid) {
                self::set_cell($item, $userid, 0);
            }
            return;
        }

        $stamp = (int) $item->get_hidden();
        $hold = $stamp === 0 ? 1 : $stamp;
        $opening = array_flip($userids);
        $previous = self::$sealing;
        self::$sealing = true;
        try {
            $seal = [];
            $grades = \grade_grade::fetch_all(['itemid' => $item->id]) ?: [];
            foreach ($grades as $grade) {
                $seal[(int) $grade->userid] = true;
            }
            foreach (self::course_gradebook_userids((int) $item->courseid) as $studentid) {
                $seal[$studentid] = true;
            }
            foreach (array_keys($seal) as $studentid) {
                if (!isset($opening[$studentid])) {
                    self::set_cell($item, $studentid, $hold);
                }
            }
            // No cascade. The cells sealed above must stay hidden.
            $item->set_hidden(0, false);
            foreach ($userids as $userid) {
                self::set_cell($item, $userid, 0);
            }
        } finally {
            self::$sealing = $previous;
        }
    }

    /**
     * Gradebook-role students enrolled in the course, one id each.
     *
     * @param int $courseid
     * @return int[]
     */
    private static function course_gradebook_userids(int $courseid): array {
        global $CFG, $DB;

        $roleids = array_filter(array_map('intval', explode(',', (string) ($CFG->gradebookroles ?? ''))));
        if (!$roleids || $courseid <= 0) {
            return [];
        }
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);
        $params['contextid'] = $context->id;
        $roleusers = array_map('intval', $DB->get_fieldset_sql(
            "SELECT DISTINCT ra.userid
               FROM {role_assignments} ra
              WHERE ra.contextid = :contextid
                AND ra.roleid $insql",
            $params,
        ));
        $enrolled = get_enrolled_users($context, '', 0, 'u.id', null, 0, 0, true);
        return array_values(array_intersect($roleusers, array_map('intval', array_keys($enrolled))));
    }

    /**
     * Set one cell's hidden value without letting a same-request grade save undo it.
     *
     * Hiding or revealing the cell is not a new mark, so the grade's timemodified
     * is put back. Friction Feedback uses that clock to tell one mark from the next.
     *
     * @param \grade_item $item
     * @param int $userid
     * @param int $hidden
     */
    public static function set_cell(\grade_item $item, int $userid, int $hidden): void {
        global $DB;

        $existing = $DB->get_record('grade_grades', ['itemid' => $item->id, 'userid' => $userid], 'id, timemodified');
        $stamp = $existing ? (int) $existing->timemodified : null;
        $previous = self::$sealing;
        self::$sealing = true;
        try {
            self::write_cell($item, $userid, $hidden);
        } finally {
            self::$sealing = $previous;
        }
        if ($stamp !== null) {
            $DB->set_field('grade_grades', 'timemodified', $stamp, ['id' => $existing->id]);
        }
    }

    /**
     * Write one cell, creating the row when the student has none yet.
     *
     * The row is created so a later grade push updates it and keeps this hidden
     * value, instead of inserting a new visible cell.
     *
     * @param \grade_item $item
     * @param int $userid
     * @param int $hidden
     */
    private static function write_cell(\grade_item $item, int $userid, int $hidden): void {
        global $USER;

        $grade = new \grade_grade(['itemid' => $item->id, 'userid' => $userid]);
        $grade->grade_item = $item;
        if (empty($grade->id)) {
            $grade->rawgrademin = $item->grademin;
            $grade->rawgrademax = $item->grademax;
            $grade->rawscaleid = $item->scaleid;
            $grade->usermodified = $USER->id ?? 0;
            $grade->hidden = $hidden;
            $grade->insert('local/unifiedgrader');
        } else if ((int) $grade->hidden !== $hidden) {
            $grade->set_hidden($hidden);
        }
        self::remember($userid, $hidden);
    }

    /**
     * Remember a student who was just released, and forget one who was hidden.
     *
     * @param int $userid
     * @param int $hidden
     */
    private static function remember(int $userid, int $hidden): void {
        if ($hidden === 0) {
            self::$releasednow[$userid] = true;
        } else {
            unset(self::$releasednow[$userid]);
        }
    }

    /**
     * Positive unique ids.
     *
     * @param int[] $ids
     * @return int[]
     */
    private static function clean_ids(array $ids): array {
        return array_values(array_unique(array_filter(array_map('intval', $ids), fn(int $id): bool => $id > 0)));
    }

    /**
     * Members of the groups, one id each, including anyone who is in more than one.
     *
     * @param int[] $groupids
     * @return int[]
     */
    private static function member_ids(array $groupids): array {
        $members = [];
        foreach ($groupids as $groupid) {
            $members = array_merge($members, array_map('intval', array_keys(groups_get_members($groupid, 'u.id'))));
        }
        return array_values(array_unique($members));
    }

    /**
     * A status array for an activity with nothing to post.
     *
     * @param bool $posted
     * @return array{posted:bool,partial:bool,hidden:int,postedcount:int,total:int,lifted:bool}
     */
    private static function empty_status(bool $posted): array {
        return [
            'posted' => $posted,
            'partial' => false,
            'hidden' => 0,
            'postedcount' => 0,
            'total' => 0,
            'lifted' => false,
        ];
    }
}
