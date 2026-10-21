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

namespace local_unifiedgrader;

/**
 * Keeps an assignment's submission comments the same in Unified Grader and in core.
 *
 * Unified Grader's own table holds the submission comments of every activity
 * type, because core has them only for assignments, and it is what the
 * notifications and the translation plugin work from. An assignment also has
 * core's submission comments (assignsubmission_comments), which the
 * submissions list and core's grader show. Without this class the two never
 * met: a comment typed in one was missing from the other (issue #24).
 *
 * Each Unified Grader comment on an assignment is linked to one core comment
 * by local_unifiedgrader_scomm.corecommentid:
 *
 * - A comment posted in Unified Grader is also written to core (push()).
 * - A comment posted in core is copied in by the comment_created observer,
 *   and notified like one posted in Unified Grader (import_core_comment()).
 * - Deleting either deletes the other.
 * - Reading a thread imports any core comment not yet linked
 *   (import_missing()), which covers a course restore, comments made while
 *   the plugin was off, and sites upgrading from before the link existed.
 *
 * On a team assignment core keeps one thread per group submission, so a core
 * comment is copied into the thread of every member, each copy linked to the
 * same core comment.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_comment_sync {
    /** @var string Core's component for assignment submission comments. */
    public const COMPONENT = 'assignsubmission_comments';

    /** @var string Core's comment area for assignment submission comments. */
    public const AREA = 'submission_comments';

    /** @var bool True while this class writes to core, so the observers ignore its own events. */
    private static bool $writing = false;

    /**
     * Whether a course module is an assignment.
     *
     * @param int $cmid Course module ID.
     * @return bool
     */
    public static function is_assign(int $cmid): bool {
        $cm = get_coursemodule_from_id('', $cmid, 0, false, IGNORE_MISSING);
        return $cm && $cm->modname === 'assign';
    }

    /**
     * Whether core's submission comments are switched on for this site.
     *
     * @return bool
     */
    public static function core_comments_enabled(): bool {
        global $CFG;
        return !empty($CFG->usecomments) && !get_config(self::COMPONENT, 'disabled');
    }

    /**
     * Write a Unified Grader comment on an assignment to core, and link the two.
     *
     * @param \stdClass $record The local_unifiedgrader_scomm row, without a link.
     * @return int|null The core comment ID, or null when nothing was written.
     */
    public static function push(\stdClass $record): ?int {
        global $DB;

        if (!empty($record->corecommentid) || !self::core_comments_enabled() || !self::is_assign((int) $record->cmid)) {
            return null;
        }
        $assign = self::assign((int) $record->cmid);
        if ($assign->get_instance()->teamsubmission) {
            $submission = $assign->get_group_submission((int) $record->userid, 0, true);
        } else {
            $submission = $assign->get_user_submission((int) $record->userid, true);
        }
        if (!$submission) {
            return null;
        }

        $context = $assign->get_context();
        $coreid = (int) $DB->insert_record('comments', (object) [
            'contextid' => $context->id,
            'component' => self::COMPONENT,
            'commentarea' => self::AREA,
            'itemid' => (int) $submission->id,
            'content' => $record->content,
            'format' => FORMAT_MOODLE,
            'userid' => (int) $record->authorid,
            'timecreated' => (int) $record->timecreated,
        ]);
        $DB->set_field('local_unifiedgrader_scomm', 'corecommentid', $coreid, ['id' => $record->id]);
        $record->corecommentid = $coreid;

        self::without_observers(function () use ($context, $coreid, $submission, $record) {
            \assignsubmission_comments\event\comment_created::create([
                'context' => $context,
                'objectid' => $coreid,
                'other' => ['itemid' => (int) $submission->id],
            ])->trigger();
        });

        return $coreid;
    }

    /**
     * Delete the core comment a Unified Grader comment is linked to, and its other copies.
     *
     * @param \stdClass $record The local_unifiedgrader_scomm row being deleted.
     */
    public static function delete_linked(\stdClass $record): void {
        global $DB;

        if (empty($record->corecommentid)) {
            return;
        }
        $coreid = (int) $record->corecommentid;
        // A team comment is copied into every member's thread; all go together.
        $DB->delete_records('local_unifiedgrader_scomm', ['cmid' => $record->cmid, 'corecommentid' => $coreid]);

        $core = $DB->get_record('comments', ['id' => $coreid]);
        if (!$core) {
            return;
        }
        $DB->delete_records('comments', ['id' => $coreid]);
        $context = \context::instance_by_id((int) $core->contextid, IGNORE_MISSING);
        if (!$context) {
            return;
        }
        self::without_observers(function () use ($context, $core) {
            \assignsubmission_comments\event\comment_deleted::create([
                'context' => $context,
                'objectid' => (int) $core->id,
                'other' => ['itemid' => (int) $core->itemid],
            ])->trigger();
        });
    }

    /**
     * Copy a comment posted in core into Unified Grader, and notify as Unified Grader would.
     *
     * @param int $coreid Core comment ID.
     * @param bool $notify Send Unified Grader's comment notification.
     * @return int Number of Unified Grader comments created.
     */
    public static function import_core_comment(int $coreid, bool $notify = true): int {
        global $DB;

        if (self::$writing) {
            return 0;
        }
        $core = $DB->get_record('comments', ['id' => $coreid]);
        if (!$core || $core->component !== self::COMPONENT || $core->commentarea !== self::AREA) {
            return 0;
        }
        $context = \context::instance_by_id((int) $core->contextid, IGNORE_MISSING);
        if (!$context || $context->contextlevel != CONTEXT_MODULE) {
            return 0;
        }
        $cmid = (int) $context->instanceid;
        $submission = $DB->get_record('assign_submission', ['id' => (int) $core->itemid]);
        if (!$submission) {
            return 0;
        }

        $created = [];
        foreach (self::thread_owners($cmid, $submission) as $userid) {
            $link = ['cmid' => $cmid, 'userid' => $userid, 'corecommentid' => $coreid];
            if ($DB->record_exists('local_unifiedgrader_scomm', $link)) {
                continue;
            }
            submission_comment_manager::add_comment($cmid, $userid, (int) $core->userid, $core->content, [
                'corecommentid' => $coreid,
                'timecreated' => (int) $core->timecreated,
            ]);
            $created[] = $userid;
        }

        if ($notify && $created) {
            self::notify($cmid, $created, (int) $core->userid, $core->content);
        }
        return count($created);
    }

    /**
     * Remove the Unified Grader copies of a comment deleted in core.
     *
     * @param int $coreid Core comment ID.
     */
    public static function core_comment_deleted(int $coreid): void {
        global $DB;
        if (self::$writing) {
            return;
        }
        $DB->delete_records('local_unifiedgrader_scomm', ['corecommentid' => $coreid]);
    }

    /**
     * Import the core comments on a student's assignment submissions that are not linked yet.
     *
     * No notification: these are comments that were missed, not new ones.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @return int Number of comments imported.
     */
    public static function import_missing(int $cmid, int $userid): int {
        global $DB;

        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return 0;
        }
        $context = \context_module::instance($cmid);

        // The student's own submissions, and on a team assignment the
        // submissions of the groups they belong to (group 0 holds students in
        // no group).
        $groupids = [0];
        foreach (groups_get_user_groups($cm->course, $userid) as $ids) {
            $groupids = array_merge($groupids, array_map('intval', $ids));
        }
        [$gsql, $gparams] = $DB->get_in_or_equal(array_unique($groupids), SQL_PARAMS_NAMED, 'g');
        $sql = "SELECT c.id
                  FROM {comments} c
                  JOIN {assign_submission} s ON s.id = c.itemid
                  JOIN {assign} a ON a.id = s.assignment
             LEFT JOIN {local_unifiedgrader_scomm} sc
                       ON sc.corecommentid = c.id AND sc.cmid = :cmid AND sc.userid = :scuserid
                 WHERE c.contextid = :contextid
                   AND c.component = :component
                   AND c.commentarea = :area
                   AND s.assignment = :assignid
                   AND ((a.teamsubmission = 0 AND s.userid = :userid)
                        OR (a.teamsubmission = 1 AND s.userid = 0 AND s.groupid {$gsql}))
                   AND sc.id IS NULL
              ORDER BY c.timecreated, c.id";
        $coreids = $DB->get_fieldset_sql($sql, [
            'cmid' => $cmid,
            'scuserid' => $userid,
            'contextid' => $context->id,
            'component' => self::COMPONENT,
            'area' => self::AREA,
            'assignid' => (int) $cm->instance,
            'userid' => $userid,
        ] + $gparams);

        $imported = 0;
        foreach ($coreids as $coreid) {
            $imported += self::import_core_comment((int) $coreid, false);
        }
        return $imported;
    }

    /**
     * Bring every assignment on the site into step: link, import and push.
     *
     * Run once by the upgrade (as an ad hoc task). Before the link existed,
     * the 2026031800 upgrade copied core's comments into Unified Grader once,
     * so most core comments have an identical copy to link to. Core comments
     * with no copy are imported, and Unified Grader comments with no core
     * comment are written to core. Nobody is notified.
     *
     * @return array{linked: int, imported: int, pushed: int}
     */
    public static function reconcile_all(): array {
        global $DB;

        $totals = ['linked' => 0, 'imported' => 0, 'pushed' => 0];
        $cmids = $DB->get_fieldset_sql(
            "SELECT DISTINCT cm.id
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
              WHERE cm.deletioninprogress = 0
                AND (EXISTS (SELECT 1 FROM {local_unifiedgrader_scomm} sc WHERE sc.cmid = cm.id)
                     OR EXISTS (SELECT 1 FROM {context} ctx
                                  JOIN {comments} c ON c.contextid = ctx.id
                                 WHERE ctx.instanceid = cm.id AND ctx.contextlevel = :ctxlevel
                                   AND c.component = :component))",
            ['ctxlevel' => CONTEXT_MODULE, 'component' => self::COMPONENT],
        );
        foreach ($cmids as $cmid) {
            foreach (self::reconcile_cm((int) $cmid) as $key => $n) {
                $totals[$key] += $n;
            }
        }
        return $totals;
    }

    /**
     * Link, import and push the comments of one assignment.
     *
     * @param int $cmid Course module ID.
     * @return array{linked: int, imported: int, pushed: int}
     */
    public static function reconcile_cm(int $cmid): array {
        global $DB;

        $result = ['linked' => 0, 'imported' => 0, 'pushed' => 0];
        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return $result;
        }
        $context = \context_module::instance($cmid);

        // Link the copies the old one-off migration made: same thread, author,
        // text and time.
        $unlinked = $DB->get_records('local_unifiedgrader_scomm', ['cmid' => $cmid, 'corecommentid' => null], 'id');
        $cores = $DB->get_records_sql(
            "SELECT c.*, s.userid AS subuserid, s.groupid AS subgroupid
               FROM {comments} c
               JOIN {assign_submission} s ON s.id = c.itemid
              WHERE c.contextid = :contextid AND c.component = :component AND c.commentarea = :area
           ORDER BY c.id",
            ['contextid' => $context->id, 'component' => self::COMPONENT, 'area' => self::AREA],
        );
        foreach ($cores as $core) {
            foreach ($unlinked as $id => $row) {
                $same = (int) $row->authorid === (int) $core->userid
                    && (int) $row->timecreated === (int) $core->timecreated
                    && $row->content === $core->content
                    && ((int) $core->subuserid === 0 || (int) $row->userid === (int) $core->subuserid);
                if ($same) {
                    $DB->set_field('local_unifiedgrader_scomm', 'corecommentid', $core->id, ['id' => $id]);
                    unset($unlinked[$id]);
                    $result['linked']++;
                    if ((int) $core->subuserid !== 0) {
                        break;
                    }
                }
            }
        }

        // Core comments nobody copied, for every student who has a thread there.
        foreach ($cores as $core) {
            $result['imported'] += self::import_core_comment((int) $core->id, false);
        }

        // Unified Grader comments core has never seen.
        foreach ($unlinked as $row) {
            if (self::push($row)) {
                $result['pushed']++;
            }
        }
        return $result;
    }

    /**
     * The students whose Unified Grader thread a core submission's comments belong in.
     *
     * @param int $cmid Course module ID.
     * @param \stdClass $submission The assign_submission row.
     * @return int[] User IDs.
     */
    private static function thread_owners(int $cmid, \stdClass $submission): array {
        if ((int) $submission->userid > 0) {
            return [(int) $submission->userid];
        }
        $members = self::assign($cmid)->get_submission_group_members((int) $submission->groupid, true);
        return array_map(fn($u) => (int) $u->id, array_values($members));
    }

    /**
     * Send Unified Grader's notification for a comment that arrived from core.
     *
     * A teacher's comment is notified to every student whose thread it went
     * into. A student's comment is notified to the teachers once, even on a
     * team submission.
     *
     * @param int $cmid Course module ID.
     * @param int[] $userids Students whose thread received the comment.
     * @param int $authorid Who wrote it.
     * @param string $content The comment text.
     */
    private static function notify(int $cmid, array $userids, int $authorid, string $content): void {
        $context = \context_module::instance($cmid);
        if (!has_capability('local/unifiedgrader:grade', $context, $authorid)) {
            $userids = [in_array($authorid, $userids, true) ? $authorid : reset($userids)];
        }
        foreach ($userids as $userid) {
            try {
                notification\submission_comment_notification::send($cmid, $userid, $authorid, $content);
            } catch (\Throwable $e) {
                debugging('Submission comment notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }

    /**
     * Run a write to core without this class's observers acting on it.
     *
     * @param callable $fn The write.
     */
    private static function without_observers(callable $fn): void {
        self::$writing = true;
        try {
            $fn();
        } finally {
            self::$writing = false;
        }
    }

    /**
     * The assign object for a course module.
     *
     * @param int $cmid Course module ID.
     * @return \assign
     */
    private static function assign(int $cmid): \assign {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'assign');
        return new \assign(\context_module::instance($cmid), $cm, $course);
    }
}
