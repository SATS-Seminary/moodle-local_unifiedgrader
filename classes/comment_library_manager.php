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
 * Comment library v2 manager — CRUD for comments, tags, and mappings.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader;

/**
 * Manages comment library entries, tags, and tag mappings.
 */
class comment_library_manager {
    /**
     * Get comments for a teacher, optionally filtered by course code and/or tag.
     *
     * @param int $userid The teacher's user ID.
     * @param string $coursecode Filter by course code (empty = all).
     * @param int $tagid Filter by tag ID (0 = all).
     * @return array List of comment records with tags.
     */
    public static function get_comments(int $userid, string $coursecode = '', int $tagid = 0): array {
        global $DB;

        // Two-bucket query: the teacher's own comments (optionally scoped to a
        // course) UNION system-default comments (userid = 0, always visible
        // regardless of course).
        $params = ['userid' => $userid];
        if ($coursecode !== '') {
            // Teacher's own: current course's comments PLUS the teacher's
            // universal comments (empty coursecode = visible everywhere).
            $personal = '(c.userid = :userid AND (c.coursecode = :coursecode OR c.coursecode = :universal))';
            $params['coursecode'] = $coursecode;
            $params['universal'] = '';
        } else {
            $personal = '(c.userid = :userid)';
        }

        $where = "({$personal} OR c.userid = 0)";

        if ($tagid > 0) {
            $where .= ' AND c.id IN (SELECT commentid FROM {local_unifiedgrader_clmap} WHERE tagid = :tagid)';
            $params['tagid'] = $tagid;
        }

        $sql = "SELECT c.*
                  FROM {local_unifiedgrader_clib} c
                 WHERE {$where}
                 ORDER BY c.userid ASC, c.sortorder ASC, c.timecreated DESC";

        $comments = $DB->get_records_sql($sql, $params);

        [$tagsbycomment, $propsbycomment] = self::prefetch_comment_metadata(array_keys($comments));

        return array_values(array_map(function ($c) use ($tagsbycomment, $propsbycomment) {
            return self::format_comment($c, $tagsbycomment, $propsbycomment);
        }, $comments));
    }

    /**
     * Get only system-default comments (userid = 0). Used by the admin
     * "Manage system defaults" page so the admin sees the system rows
     * separately from any personal library entries.
     *
     * @return array
     */
    public static function get_system_comments(): array {
        global $DB;
        $records = $DB->get_records(
            'local_unifiedgrader_clib',
            ['userid' => 0],
            'sortorder ASC, timecreated DESC',
        );
        [$tagsbycomment, $propsbycomment] = self::prefetch_comment_metadata(array_keys($records));
        return array_values(array_map(function ($c) use ($tagsbycomment, $propsbycomment) {
            return self::format_comment($c, $tagsbycomment, $propsbycomment);
        }, $records));
    }

    /**
     * Admin: create or update a system-default comment (userid = 0).
     *
     * @param string $content
     * @param int[] $tagids
     * @param int $commentid 0 = new.
     * @return int The comment id.
     */
    public static function save_system_comment(string $content, array $tagids = [], int $commentid = 0): int {
        global $DB;
        require_capability('local/unifiedgrader:managesystemdefaults', \context_system::instance());

        $now = time();
        if ($commentid > 0) {
            $record = $DB->get_record(
                'local_unifiedgrader_clib',
                ['id' => $commentid, 'userid' => 0],
                '*',
                MUST_EXIST,
            );
            $record->content = $content;
            $record->timemodified = $now;
            $DB->update_record('local_unifiedgrader_clib', $record);
        } else {
            $commentid = $DB->insert_record('local_unifiedgrader_clib', (object) [
                'userid' => 0,
                'coursecode' => '',
                'content' => $content,
                'shared' => 0,
                'sortorder' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
        self::sync_comment_tags($commentid, $tagids);
        return $commentid;
    }

    /**
     * Admin: delete a system-default comment.
     *
     * @param int $commentid
     */
    public static function delete_system_comment(int $commentid): void {
        global $DB;
        require_capability('local/unifiedgrader:managesystemdefaults', \context_system::instance());
        $DB->delete_records('local_unifiedgrader_clib', ['id' => $commentid, 'userid' => 0]);
        $DB->delete_records('local_unifiedgrader_clmap', ['commentid' => $commentid]);
    }

    /**
     * Admin: create or update a system-default tag (userid = 0).
     *
     * @param string $name
     * @param int $sortorder
     * @param int $tagid 0 = new.
     * @return int The tag id.
     */
    public static function save_system_tag(string $name, int $sortorder = 0, int $tagid = 0): int {
        global $DB;
        require_capability('local/unifiedgrader:managesystemdefaults', \context_system::instance());

        $now = time();
        if ($tagid > 0) {
            $record = $DB->get_record(
                'local_unifiedgrader_cltag',
                ['id' => $tagid, 'userid' => 0],
                '*',
                MUST_EXIST,
            );
            $record->name = $name;
            $record->sortorder = $sortorder;
            $record->timemodified = $now;
            $DB->update_record('local_unifiedgrader_cltag', $record);
            return $tagid;
        }
        return $DB->insert_record('local_unifiedgrader_cltag', (object) [
            'userid' => 0,
            'name' => $name,
            'sortorder' => $sortorder,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Admin: delete a system-default tag and unmap it from all comments.
     *
     * @param int $tagid
     */
    public static function delete_system_tag(int $tagid): void {
        global $DB;
        require_capability('local/unifiedgrader:managesystemdefaults', \context_system::instance());
        $DB->delete_records('local_unifiedgrader_cltag', ['id' => $tagid, 'userid' => 0]);
        $DB->delete_records('local_unifiedgrader_clmap', ['tagid' => $tagid]);
    }

    /**
     * Save (create or update) a comment.
     *
     * @param int $userid Owner teacher ID.
     * @param string $coursecode The course code.
     * @param string $content Comment text.
     * @param int[] $tagids Tag IDs to assign.
     * @param int $shared 1 = shared, 0 = private.
     * @param int $commentid Existing comment ID to update (0 = new).
     * @return int The comment ID.
     */
    public static function save_comment(
        int $userid,
        string $coursecode,
        string $content,
        array $tagids = [],
        int $shared = 0,
        int $commentid = 0,
    ): int {
        global $DB;

        $now = time();

        if ($commentid > 0) {
            $record = $DB->get_record('local_unifiedgrader_clib', ['id' => $commentid, 'userid' => $userid], '*', MUST_EXIST);
            $record->coursecode = $coursecode;
            $record->content = $content;
            $record->shared = $shared;
            $record->timemodified = $now;
            $DB->update_record('local_unifiedgrader_clib', $record);
        } else {
            $commentid = $DB->insert_record('local_unifiedgrader_clib', (object) [
                'userid' => $userid,
                'coursecode' => $coursecode,
                'content' => $content,
                'shared' => $shared,
                'sortorder' => 0,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        // Sync tag mappings.
        self::sync_comment_tags($commentid, $tagids);

        return $commentid;
    }

    /**
     * Delete a comment and its tag mappings.
     *
     * @param int $commentid The comment ID.
     * @param int $userid The owner — enforces ownership check.
     */
    public static function delete_comment(int $commentid, int $userid): void {
        global $DB;

        $DB->delete_records('local_unifiedgrader_clib', ['id' => $commentid, 'userid' => $userid]);
        $DB->delete_records('local_unifiedgrader_clmap', ['commentid' => $commentid]);
    }

    /**
     * Get tags visible to a teacher.
     *
     * When $coursecode is provided, returns only system-default tags plus tags
     * actually attached to comments in that course (or to universal comments,
     * which have an empty coursecode). When $coursecode is null, returns every
     * tag the teacher can see — used by the manage-library modal so the tag
     * filter dropdown can offer all of the teacher's tags regardless of which
     * course the modal was opened from.
     *
     * @param int $userid The teacher's user ID.
     * @param string|null $coursecode Restrict to tags relevant to this course (and universal
     *                                comments). Null = no course restriction.
     * @return array List of tag records.
     */
    public static function get_tags(int $userid, ?string $coursecode = null): array {
        global $DB;

        if ($coursecode === null) {
            $sql = "SELECT *
                      FROM {local_unifiedgrader_cltag}
                     WHERE userid = :userid OR userid = 0
                     ORDER BY sortorder ASC, name ASC";
            $tags = $DB->get_records_sql($sql, ['userid' => $userid]);
        } else {
            // System tags always visible; user tags only if attached to a
            // comment scoped to this course OR to a universal comment.
            $sql = "SELECT t.*
                      FROM {local_unifiedgrader_cltag} t
                     WHERE t.userid = 0
                        OR (t.userid = :userid AND t.id IN (
                              SELECT m.tagid
                                FROM {local_unifiedgrader_clmap} m
                                JOIN {local_unifiedgrader_clib} c ON c.id = m.commentid
                               WHERE c.userid = :userid2
                                 AND (c.coursecode = :coursecode OR c.coursecode = '')
                          ))
                     ORDER BY t.sortorder ASC, t.name ASC";
            $tags = $DB->get_records_sql($sql, [
                'userid' => $userid,
                'userid2' => $userid,
                'coursecode' => $coursecode,
            ]);
        }

        return array_values(array_map(function ($t) {
            return [
                'id' => (int) $t->id,
                'userid' => (int) $t->userid,
                'name' => $t->name,
                'sortorder' => (int) $t->sortorder,
                'issystem' => ((int) $t->userid === 0),
            ];
        }, $tags));
    }

    /**
     * Save (create or update) a tag.
     *
     * @param int $userid Owner teacher ID.
     * @param string $name Tag display name.
     * @param int $tagid Existing tag ID to update (0 = new).
     * @return int The tag ID.
     */
    public static function save_tag(int $userid, string $name, int $tagid = 0): int {
        global $DB;

        $now = time();

        if ($tagid > 0) {
            $record = $DB->get_record('local_unifiedgrader_cltag', ['id' => $tagid, 'userid' => $userid], '*', MUST_EXIST);
            $record->name = $name;
            $record->timemodified = $now;
            $DB->update_record('local_unifiedgrader_cltag', $record);
            return $tagid;
        }

        return $DB->insert_record('local_unifiedgrader_cltag', (object) [
            'userid' => $userid,
            'name' => $name,
            'sortorder' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Delete a tag and its mappings. Only non-system tags owned by the user.
     *
     * @param int $tagid The tag ID.
     * @param int $userid The owner — enforces ownership check.
     */
    public static function delete_tag(int $tagid, int $userid): void {
        global $DB;

        // Prevent deleting system defaults.
        $tag = $DB->get_record('local_unifiedgrader_cltag', ['id' => $tagid], '*', MUST_EXIST);
        if ((int) $tag->userid === 0) {
            throw new \moodle_exception('cannotdeletesystemtag', 'local_unifiedgrader');
        }
        if ((int) $tag->userid !== $userid) {
            throw new \moodle_exception('nopermission', 'local_unifiedgrader');
        }

        $DB->delete_records('local_unifiedgrader_cltag', ['id' => $tagid]);
        $DB->delete_records('local_unifiedgrader_clmap', ['tagid' => $tagid]);
    }

    /**
     * Get instance-wide shared comments, optionally filtered by tag.
     *
     * @param int $userid The requesting teacher's ID (excluded from results to avoid seeing own comments).
     * @param int $tagid Filter by tag ID (0 = all).
     * @return array List of comment records with tags and owner info.
     */
    public static function get_shared_comments(int $userid, int $tagid = 0): array {
        global $DB;

        $params = ['userid' => $userid];
        $where = 'c.shared = 1 AND c.userid != :userid';

        if ($tagid > 0) {
            $where .= ' AND c.id IN (SELECT commentid FROM {local_unifiedgrader_clmap} WHERE tagid = :tagid)';
            $params['tagid'] = $tagid;
        }

        $sql = "SELECT c.*, u.firstname, u.lastname
                  FROM {local_unifiedgrader_clib} c
                  JOIN {user} u ON u.id = c.userid
                 WHERE {$where}
                 ORDER BY c.timemodified DESC";

        $comments = $DB->get_records_sql($sql, $params);

        [$tagsbycomment, $propsbycomment] = self::prefetch_comment_metadata(array_keys($comments));

        return array_values(array_map(function ($c) use ($tagsbycomment, $propsbycomment) {
            $formatted = self::format_comment($c, $tagsbycomment, $propsbycomment);
            $formatted['ownername'] = fullname($c);
            return $formatted;
        }, $comments));
    }

    /**
     * Import a shared comment into a teacher's own library.
     *
     * @param int $sourcecommentid The shared comment ID to copy.
     * @param int $userid The importing teacher's ID.
     * @param string $coursecode The course code to assign in the new copy.
     * @return int The new comment ID.
     */
    public static function import_shared_comment(int $sourcecommentid, int $userid, string $coursecode): int {
        global $DB;

        $source = $DB->get_record('local_unifiedgrader_clib', ['id' => $sourcecommentid, 'shared' => 1], '*', MUST_EXIST);

        $now = time();
        $newid = $DB->insert_record('local_unifiedgrader_clib', (object) [
            'userid' => $userid,
            'coursecode' => $coursecode,
            'content' => $source->content,
            'shared' => 0,
            'sortorder' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        // Copy tag mappings.
        $maps = $DB->get_records('local_unifiedgrader_clmap', ['commentid' => $sourcecommentid]);
        foreach ($maps as $map) {
            $DB->insert_record('local_unifiedgrader_clmap', (object) [
                'commentid' => $newid,
                'tagid' => $map->tagid,
            ]);
        }

        return $newid;
    }

    /**
     * Sync tag mappings for a comment (replace all).
     *
     * @param int $commentid
     * @param int[] $tagids
     */
    private static function sync_comment_tags(int $commentid, array $tagids): void {
        global $DB;

        $DB->delete_records('local_unifiedgrader_clmap', ['commentid' => $commentid]);

        foreach ($tagids as $tagid) {
            $DB->insert_record('local_unifiedgrader_clmap', (object) [
                'commentid' => $commentid,
                'tagid' => (int) $tagid,
            ]);
        }
    }

    /**
     * Batch-fetch tag mappings and latest proposals for a set of comment IDs.
     *
     * Returns a tuple of two maps keyed by comment id:
     *   - tags:  commentid => int[] tag ids
     *   - props: commentid => stdClass {status, decisionreason} (latest only)
     *
     * Replaces the per-comment DB hits previously done inside format_comment()
     * — turns N+1 queries into 2 queries regardless of the number of comments.
     *
     * @param int[] $commentids
     * @return array{0: array<int,int[]>, 1: array<int,\stdClass>}
     */
    private static function prefetch_comment_metadata(array $commentids): array {
        global $DB;

        if (empty($commentids)) {
            return [[], []];
        }

        // Tag mappings.
        [$insql, $inparams] = $DB->get_in_or_equal($commentids, SQL_PARAMS_NAMED, 'cid');
        $maps = $DB->get_records_select(
            'local_unifiedgrader_clmap',
            "commentid {$insql}",
            $inparams,
            '',
            'id, commentid, tagid',
        );
        $tagsbycomment = [];
        foreach ($maps as $m) {
            $tagsbycomment[(int) $m->commentid][] = (int) $m->tagid;
        }

        // Latest proposal per comment — ORDER BY timecreated DESC, then keep
        // the first occurrence we see for each commentid.
        [$insql2, $inparams2] = $DB->get_in_or_equal($commentids, SQL_PARAMS_NAMED, 'cidp');
        $props = $DB->get_records_select(
            'local_unifiedgrader_clibprop',
            "commentid {$insql2}",
            $inparams2,
            'timecreated DESC',
            'id, commentid, status, decisionreason',
        );
        $propsbycomment = [];
        foreach ($props as $p) {
            $cid = (int) $p->commentid;
            if (!isset($propsbycomment[$cid])) {
                $propsbycomment[$cid] = $p;
            }
        }

        return [$tagsbycomment, $propsbycomment];
    }

    /**
     * Format a comment record with its tags for API output.
     *
     * Tag and proposal data must be pre-fetched by the caller via
     * prefetch_comment_metadata() so this method does no DB I/O per row.
     *
     * @param object $record The DB record.
     * @param array $tagsbycomment Map of commentid to list of tag ids.
     * @param array $propsbycomment Map of commentid to latest proposal row (stdClass).
     * @return array Formatted comment.
     */
    private static function format_comment(
        object $record,
        array $tagsbycomment,
        array $propsbycomment,
    ): array {
        $id = (int) $record->id;
        $tagids = $tagsbycomment[$id] ?? [];

        $out = [
            'id' => $id,
            'userid' => (int) $record->userid,
            'coursecode' => $record->coursecode,
            'content' => $record->content,
            'shared' => (int) $record->shared,
            'sortorder' => (int) $record->sortorder,
            'timecreated' => (int) $record->timecreated,
            'timemodified' => (int) $record->timemodified,
            'tagids' => array_map('intval', $tagids),
            'proposalstatus' => '',
            'proposalreason' => '',
        ];

        // Surface the latest proposal status so the UI can show a
        // "Pending review" / "Rejected" badge on the proposer's own card.
        // System-default rows (userid = 0) never have proposals.
        if ((int) $record->userid !== 0) {
            $latest = $propsbycomment[$id] ?? null;
            if ($latest !== null) {
                $out['proposalstatus'] = (string) $latest->status;
                $out['proposalreason'] = (string) ($latest->decisionreason ?? '');
            }
        }

        return $out;
    }

    // Proposals.

    /**
     * Submit a teacher's comment for promotion to a system default. Allowed
     * only on a comment the proposer owns (no proxying), and only if there
     * isn't already a pending proposal for the same comment.
     *
     * Behaviour branches on the `require_systemdefault_approval` admin
     * setting: when on (default) the proposal sits as `pending` for an
     * admin to approve; when off, the comment is immediately copied into
     * the system defaults (only system-tagged) and the proposal row is
     * recorded with `status = approved` and `decidedby = 0` for audit.
     *
     * @param int $commentid The teacher's comment id.
     * @param int $proposerid The submitting teacher's user id.
     * @param string $rationale Optional explanation shown to the admin.
     * @return int The new proposal id.
     */
    public static function submit_proposal(int $commentid, int $proposerid, string $rationale = ''): int {
        global $DB;

        // Ownership check: a teacher can only propose their own comments.
        $comment = $DB->get_record(
            'local_unifiedgrader_clib',
            ['id' => $commentid, 'userid' => $proposerid],
            '*',
            MUST_EXIST,
        );

        // Refuse duplicate pending submissions for the same comment.
        $existingpending = $DB->record_exists(
            'local_unifiedgrader_clibprop',
            ['commentid' => $comment->id, 'status' => 'pending'],
        );
        if ($existingpending) {
            throw new \moodle_exception('error_proposal_already_pending', 'local_unifiedgrader');
        }

        $requireapproval = !empty(get_config('local_unifiedgrader', 'require_systemdefault_approval'));
        $now = time();

        if ($requireapproval) {
            // Approval mode: queue for admin review.
            return $DB->insert_record('local_unifiedgrader_clibprop', (object) [
                'commentid' => $comment->id,
                'proposerid' => $proposerid,
                'rationale' => $rationale,
                'status' => 'pending',
                'decisionreason' => null,
                'decidedby' => null,
                'timecreated' => $now,
                'timedecided' => null,
            ]);
        }

        // Trust mode: promote immediately. Copy only system tags — the
        // user's directive was "comments may only be added to existing
        // system categories". A comment with no system tags still gets
        // promoted (untagged); the admin can categorise it later if
        // needed.
        $proposedtagids = $DB->get_fieldset_select(
            'local_unifiedgrader_clmap',
            'tagid',
            'commentid = ?',
            [$comment->id],
        );
        $systemtagids = [];
        if (!empty($proposedtagids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($proposedtagids, SQL_PARAMS_NAMED, 'tag');
            $systemtagids = $DB->get_fieldset_select(
                'local_unifiedgrader_cltag',
                'id',
                "userid = 0 AND id {$insql}",
                $inparams,
            );
        }

        // Direct insert with userid = 0 — bypasses save_system_comment's
        // managesystemdefaults capability check because the trust-mode
        // workflow is what we're explicitly authorising here. The
        // proposer's library:grade capability got them this far.
        $newcommentid = $DB->insert_record('local_unifiedgrader_clib', (object) [
            'userid' => 0,
            'coursecode' => '',
            'content' => (string) $comment->content,
            'shared' => 0,
            'sortorder' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        self::sync_comment_tags($newcommentid, array_map('intval', $systemtagids));

        // Record the proposal as auto-approved for audit. decidedby = 0
        // signals "no admin decided"; the decisionreason carries the mode.
        return $DB->insert_record('local_unifiedgrader_clibprop', (object) [
            'commentid' => $comment->id,
            'proposerid' => $proposerid,
            'rationale' => $rationale,
            'status' => 'approved',
            'decisionreason' => 'Auto-approved (trust mode)',
            'decidedby' => 0,
            'timecreated' => $now,
            'timedecided' => $now,
        ]);
    }

    /**
     * Admin: list all pending proposals for the review queue. Each entry
     * carries the comment text, the proposer's name, any rationale, and
     * the tags the original was attached to (so the admin can preview the
     * eventual system-default's tag set before approving).
     *
     * @return array
     */
    public static function get_pending_proposals(): array {
        global $DB;
        require_capability('local/unifiedgrader:managesystemdefaults', \context_system::instance());

        $sql = "SELECT p.id, p.commentid, p.proposerid, p.rationale, p.timecreated,
                       c.content, c.coursecode,
                       u.firstname, u.lastname
                  FROM {local_unifiedgrader_clibprop} p
                  JOIN {local_unifiedgrader_clib} c ON c.id = p.commentid
                  JOIN {user} u ON u.id = p.proposerid
                 WHERE p.status = :status
              ORDER BY p.timecreated ASC";
        $records = $DB->get_records_sql($sql, ['status' => 'pending']);

        $out = [];
        foreach ($records as $r) {
            $tagids = $DB->get_fieldset_select(
                'local_unifiedgrader_clmap',
                'tagid',
                'commentid = ?',
                [$r->commentid],
            );
            $out[] = [
                'id' => (int) $r->id,
                'commentid' => (int) $r->commentid,
                'proposerid' => (int) $r->proposerid,
                'proposername' => fullname($r),
                'rationale' => (string) ($r->rationale ?? ''),
                'content' => (string) $r->content,
                'coursecode' => (string) $r->coursecode,
                'tagids' => array_map('intval', $tagids),
                'timecreated' => (int) $r->timecreated,
            ];
        }
        return $out;
    }

    /**
     * Admin: approve a pending proposal. Creates a NEW system-default
     * comment that mirrors the proposed comment's content and tag set;
     * the proposer's original row is untouched.
     *
     * @param int $proposalid
     * @param int $adminid The deciding admin's user id (for audit).
     * @param string $note Optional approval note (rare but allowed).
     * @return int The new system-default comment id.
     */
    public static function approve_proposal(int $proposalid, int $adminid, string $note = ''): int {
        global $DB;
        require_capability('local/unifiedgrader:managesystemdefaults', \context_system::instance());

        $proposal = $DB->get_record(
            'local_unifiedgrader_clibprop',
            ['id' => $proposalid, 'status' => 'pending'],
            '*',
            MUST_EXIST,
        );
        $comment = $DB->get_record(
            'local_unifiedgrader_clib',
            ['id' => $proposal->commentid],
            '*',
            MUST_EXIST,
        );

        // Copy the proposer's tag selection, filtered to system tags only —
        // a system-default comment with a personal tag would never render
        // for the teacher who didn't own that tag.
        $proposedtagids = $DB->get_fieldset_select(
            'local_unifiedgrader_clmap',
            'tagid',
            'commentid = ?',
            [$comment->id],
        );
        $systemtagids = [];
        if (!empty($proposedtagids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($proposedtagids, SQL_PARAMS_NAMED, 'tag');
            $systemtagids = $DB->get_fieldset_select(
                'local_unifiedgrader_cltag',
                'id',
                "userid = 0 AND id {$insql}",
                $inparams,
            );
        }

        $newid = self::save_system_comment(
            (string) $comment->content,
            array_map('intval', $systemtagids),
            0,
        );

        $proposal->status = 'approved';
        $proposal->decisionreason = $note;
        $proposal->decidedby = $adminid;
        $proposal->timedecided = time();
        $DB->update_record('local_unifiedgrader_clibprop', $proposal);

        return $newid;
    }

    /**
     * Admin: reject a pending proposal with an optional written reason.
     *
     * @param int $proposalid
     * @param int $adminid
     * @param string $reason
     */
    public static function reject_proposal(int $proposalid, int $adminid, string $reason = ''): void {
        global $DB;
        require_capability('local/unifiedgrader:managesystemdefaults', \context_system::instance());

        $proposal = $DB->get_record(
            'local_unifiedgrader_clibprop',
            ['id' => $proposalid, 'status' => 'pending'],
            '*',
            MUST_EXIST,
        );
        $proposal->status = 'rejected';
        $proposal->decisionreason = $reason;
        $proposal->decidedby = $adminid;
        $proposal->timedecided = time();
        $DB->update_record('local_unifiedgrader_clibprop', $proposal);
    }
}
