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
 * Rating-based forum grading helper.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\adapter;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/rating/lib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');
require_once($CFG->libdir . '/gradelib.php');

/**
 * Everything the grader needs to know about a rating-based forum.
 *
 * Ratings are a different animal from whole-forum grading. The grade is not
 * stored anywhere the plugin owns: a teacher rates individual posts into the
 * core {rating} table, and mod_forum recomputes the gradebook value by
 * aggregating every rating on every post the student wrote. So this class
 * never writes a grade — it writes ratings and reads back what core computed.
 *
 * All writes go through rating_manager::add_rating(), which is the only entry
 * point that performs the permission checks, runs forum_rating_validate(),
 * and syncs the gradebook afterwards.
 */
class forum_rating_helper {
    /** @var \cm_info Course module info. */
    private \cm_info $cm;

    /** @var \context_module Module context. */
    private \context_module $context;

    /** @var \stdClass Course record. */
    private \stdClass $course;

    /** @var \stdClass Raw forum DB record. */
    private \stdClass $forum;

    /** @var \rating_manager Shared rating manager. */
    private \rating_manager $rm;

    /**
     * Constructor.
     *
     * @param \cm_info $cm Course module info.
     * @param \context_module $context Module context.
     * @param \stdClass $course Course record.
     * @param \stdClass $forum Raw forum DB record.
     */
    public function __construct(
        \cm_info $cm,
        \context_module $context,
        \stdClass $course,
        \stdClass $forum,
    ) {
        $this->cm = $cm;
        $this->context = $context;
        $this->course = $course;
        $this->forum = $forum;
        $this->rm = new \rating_manager();
    }

    /**
     * The aggregation method in force (a RATING_AGGREGATE_* constant).
     *
     * @return int
     */
    public function get_aggregate_method(): int {
        return (int) $this->forum->assessed;
    }

    /**
     * Human-readable aggregation label, e.g. "Average of ratings".
     *
     * rating_manager appends a label separator for inline use; we want the
     * bare phrase.
     *
     * @return string
     */
    public function get_aggregate_label(): string {
        $label = $this->rm->get_aggregate_label($this->get_aggregate_method());
        $sep = get_string('labelsep', 'langconfig');
        if ($sep !== '' && str_ends_with($label, $sep)) {
            $label = substr($label, 0, -strlen($sep));
        }
        return trim($label);
    }

    /**
     * Scale metadata for the rating selects.
     *
     * forum.scale uses the standard signed convention: a positive value is a
     * points maximum, a negative value is -scaleid. The item values must match
     * what core's rating renderer offers, because forum_rating_validate()
     * bounds-checks against exactly this range — custom scales are 1-based,
     * point scales include 0.
     *
     * @return array With keys usescale, scaleitems, maxgrade.
     */
    public function get_scale_info(): array {
        global $DB;

        $scale = (int) $this->forum->scale;
        $items = [];

        if ($scale < 0) {
            $record = $DB->get_record('scale', ['id' => abs($scale)]);
            if ($record) {
                $labels = explode(',', $record->scale);
                foreach ($labels as $i => $label) {
                    // Custom scales are 1-based; index 0 is not a valid rating.
                    $items[] = ['value' => $i + 1, 'label' => trim($label)];
                }
            }
            return [
                'usescale' => true,
                'scaleitems' => $items,
                'maxgrade' => (float) count($items),
            ];
        }

        for ($i = 0; $i <= $scale; $i++) {
            $items[] = ['value' => $i, 'label' => (string) $i];
        }
        return [
            'usescale' => false,
            'scaleitems' => $items,
            'maxgrade' => (float) $scale,
        ];
    }

    /**
     * Whether the current user may rate posts in this forum at all.
     *
     * Per-post permission still has to be checked separately — this is only
     * the blanket capability test.
     *
     * @return bool
     */
    public function can_rate(): bool {
        return has_capability('moodle/rating:rate', $this->context)
            && has_capability('mod/forum:rate', $this->context);
    }

    /**
     * All of a user's non-deleted posts in this forum, oldest first.
     *
     * @param int $userid
     * @return array Post records keyed by post id, each with ->discussionname.
     */
    public function get_user_posts(int $userid): array {
        global $DB;

        $sql = "SELECT p.*, d.name AS discussionname, d.id AS discussionid
                  FROM {forum_posts} p
                  JOIN {forum_discussions} d ON d.id = p.discussion
                 WHERE d.forum = :forumid AND p.userid = :userid AND p.deleted = 0
              ORDER BY p.created ASC";
        return $DB->get_records_sql($sql, [
            'forumid' => (int) $this->forum->id,
            'userid' => $userid,
        ]);
    }

    /**
     * Per-post rating state for one student.
     *
     * Two numbers matter and they are not the same: "own" is the rating the
     * current grader gave, "aggregate" blends every grader who rated that post.
     * A second marker's rating changes the aggregate without touching yours.
     *
     * @param int $userid The student.
     * @return array List of per-post arrays.
     */
    public function get_post_ratings(int $userid): array {
        $posts = $this->get_user_posts($userid);
        if (empty($posts)) {
            return [];
        }
        return $this->decorate_posts_with_ratings($posts, $userid);
    }

    /**
     * Attach rating state to a set of post records.
     *
     * Shared with the context builder so the threaded views show the same
     * numbers as the marking panel without a second round of queries.
     *
     * @param array $posts Post records (must carry ->id and ->userid).
     * @param int $itemuserid The author whose posts these are.
     * @return array List of per-post rating arrays keyed by post id.
     */
    public function decorate_posts_with_ratings(array $posts, int $itemuserid): array {
        global $USER;

        // The rating manager mutates the items it is given, so hand it
        // lightweight stand-ins rather than the real post records.
        $items = [];
        foreach ($posts as $post) {
            $item = new \stdClass();
            $item->id = (int) $post->id;
            $item->userid = (int) $post->userid;
            $item->created = (int) $post->created;
            $items[] = $item;
        }

        $options = new \stdClass();
        $options->context = $this->context;
        $options->component = 'mod_forum';
        $options->ratingarea = 'post';
        $options->items = $items;
        $options->aggregate = $this->get_aggregate_method();
        $options->scaleid = (int) $this->forum->scale;
        $options->userid = $USER->id;
        $options->assesstimestart = (int) $this->forum->assesstimestart;
        $options->assesstimefinish = (int) $this->forum->assesstimefinish;
        $options->plugintype = 'mod';
        $options->pluginname = 'forum';

        $rated = $this->rm->get_ratings($options);

        $cancapability = $this->can_rate();
        $result = [];
        foreach ($rated as $item) {
            $rating = $item->rating ?? null;
            $own = ($rating && $rating->rating !== null) ? (int) $rating->rating : null;
            $aggregate = ($rating && $rating->aggregate !== null) ? (float) $rating->aggregate : null;
            $count = $rating ? (int) $rating->count : 0;

            [$canrate, $reason] = $this->resolve_can_rate($item, $cancapability);

            $result[(int) $item->id] = [
                'postid' => (int) $item->id,
                'own' => $own,
                'aggregate' => $aggregate,
                'aggregatelabel' => $this->format_aggregate($aggregate),
                'count' => $count,
                'canrate' => $canrate,
                'noratereason' => $reason,
            ];
        }

        return $result;
    }

    /**
     * Decide whether the current user may rate a specific post, and why not.
     *
     * Mirrors the checks forum_rating_validate() will apply, so the UI can
     * disable the control with an explanation instead of letting the teacher
     * pick a value that then bounces.
     *
     * @param \stdClass $item Item stand-in with ->id, ->userid, ->created.
     * @param bool $cancapability Result of can_rate().
     * @return array [bool $canrate, string $reason]
     */
    private function resolve_can_rate(\stdClass $item, bool $cancapability): array {
        global $USER;

        if (!$cancapability) {
            return [false, get_string('rating_norate_nocap', 'local_unifiedgrader')];
        }

        // Core forbids rating your own contribution, without exception.
        if ((int) $item->userid === (int) $USER->id) {
            return [false, get_string('rating_norate_ownpost', 'local_unifiedgrader')];
        }

        // The assess window is only enforced when both ends are set.
        $start = (int) $this->forum->assesstimestart;
        $finish = (int) $this->forum->assesstimefinish;
        if (!empty($start) && !empty($finish)) {
            $created = (int) $item->created;
            if ($created < $start || $created > $finish) {
                return [false, get_string('rating_norate_window', 'local_unifiedgrader')];
            }
        }

        return [true, ''];
    }

    /**
     * Render an aggregate for display, using scale labels where appropriate.
     *
     * Follows core's own rule in rating::get_aggregate_string(): for a custom
     * scale the aggregate indexes back into the scale, except under SUM and
     * COUNT where the number is the answer.
     *
     * @param float|null $aggregate
     * @return string
     */
    public function format_aggregate(?float $aggregate): string {
        if ($aggregate === null) {
            return '';
        }

        $method = $this->get_aggregate_method();
        $scaleinfo = $this->get_scale_info();

        if (
            $scaleinfo['usescale']
            && $method !== RATING_AGGREGATE_COUNT
            && $method !== RATING_AGGREGATE_SUM
        ) {
            $index = (int) round($aggregate);
            foreach ($scaleinfo['scaleitems'] as $item) {
                if ($item['value'] === $index) {
                    return $item['label'];
                }
            }
            return (string) $index;
        }

        if ($method === RATING_AGGREGATE_COUNT) {
            return (string) (int) $aggregate;
        }

        return (string) round($aggregate, 1);
    }

    /**
     * Write (or clear) the current user's rating on a post.
     *
     * Delegates wholesale to rating_manager::add_rating(), which validates,
     * writes, and calls forum_update_grades() so the gradebook follows.
     * RATING_UNSET_RATING is passed straight through — core reads it as
     * "remove my rating", which is not the same as rating zero.
     *
     * @param int $postid
     * @param int $rating The scale value, or RATING_UNSET_RATING to clear.
     * @return array With keys aggregate, aggregatelabel, count, gradebookgrade, gradebookdisplay.
     * @throws \moodle_exception On a validation or permission failure.
     */
    public function save_post_rating(int $postid, int $rating): array {
        global $DB;

        $post = $DB->get_record_sql(
            "SELECT p.* FROM {forum_posts} p
               JOIN {forum_discussions} d ON d.id = p.discussion
              WHERE p.id = :postid AND d.forum = :forumid",
            ['postid' => $postid, 'forumid' => (int) $this->forum->id],
            MUST_EXIST,
        );

        if ($rating === RATING_UNSET_RATING) {
            // Clearing is handled here rather than through add_rating() for two
            // reasons. Core's clear path leaves a stale gradebook value behind:
            // rating_manager::get_user_grades() omits users who now have no
            // ratings, and grade_update() early-returns on an empty array, so
            // the old aggregate survives a teacher deleting the mark that
            // produced it. It also calls round() on a null aggregate on the way
            // out. Both are core behaviour that rate_ajax.php shares; this
            // feature makes the clear a first-class action, so it has to work.
            $this->clear_own_rating($postid, (int) $post->userid);
        } else {
            $result = $this->rm->add_rating(
                $this->cm,
                $this->context,
                'mod_forum',
                'post',
                $postid,
                (int) $this->forum->scale,
                $rating,
                (int) $post->userid,
                $this->get_aggregate_method(),
            );

            if (!empty($result->error)) {
                throw new \moodle_exception($result->error, 'rating');
            }
        }

        // Core returns the aggregate pre-formatted for display; recompute
        // the raw value so the client can decide how to render it.
        $fresh = $this->decorate_posts_with_ratings([$post], (int) $post->userid);
        $state = $fresh[$postid] ?? null;

        return [
            'postid' => $postid,
            'own' => $state['own'] ?? null,
            'aggregate' => $state['aggregate'] ?? null,
            'aggregatelabel' => $state['aggregatelabel'] ?? '',
            'count' => $state['count'] ?? 0,
            'gradebookgrade' => $this->get_gradebook_grade((int) $post->userid),
            'gradebookdisplay' => $this->get_gradebook_display((int) $post->userid),
        ];
    }

    /**
     * Withdraw the current user's rating from one post and resync the gradebook.
     *
     * @param int $postid
     * @param int $rateduserid The post's author.
     * @throws \moodle_exception If the user may not rate here.
     */
    private function clear_own_rating(int $postid, int $rateduserid): void {
        global $USER;

        if (!$this->can_rate()) {
            throw new \moodle_exception('ratepermissiondenied', 'rating');
        }

        $this->rm->delete_ratings((object) [
            'contextid' => $this->context->id,
            'component' => 'mod_forum',
            'ratingarea' => 'post',
            'itemid' => $postid,
            'userid' => $USER->id,
        ]);

        $this->resync_gradebook($rateduserid);
    }

    /**
     * Recompute a student's gradebook value from whatever ratings remain.
     *
     * forum_update_grades() handles the case where some ratings survive. When
     * none do it does nothing at all, so the null has to be pushed explicitly
     * or the student keeps a grade nobody gave them.
     *
     * @param int $userid The rated student.
     */
    private function resync_gradebook(int $userid): void {
        global $DB;

        $forumrecord = $DB->get_record('forum', ['id' => (int) $this->forum->id], '*', MUST_EXIST);
        $forumrecord->cmidnumber = $this->cm->idnumber;
        forum_update_grades($forumrecord, $userid);

        $stats = $this->count_post_stats([$userid]);
        if (($stats[$userid]['rated'] ?? 0) > 0) {
            return;
        }

        // A non-empty array carrying a null rawgrade is what grade_update()
        // needs to actually clear the cell; an empty array is a no-op.
        grade_update(
            'mod/forum',
            (int) $this->course->id,
            'mod',
            'forum',
            (int) $this->forum->id,
            0,
            [$userid => (object) ['userid' => $userid, 'rawgrade' => null]],
        );
    }

    /**
     * Remove only the current grader's ratings on one student's posts.
     *
     * Deliberately scoped to $USER: another marker's ratings are their
     * judgement, not ours to discard. Posts are never touched — a student's
     * contribution is not the teacher's to delete.
     *
     * @param int $userid The student.
     */
    public function delete_own_ratings_for_user(int $userid): void {
        global $USER;

        $posts = $this->get_user_posts($userid);
        if (empty($posts)) {
            return;
        }

        foreach ($posts as $post) {
            $this->rm->delete_ratings((object) [
                'contextid' => $this->context->id,
                'component' => 'mod_forum',
                'ratingarea' => 'post',
                'itemid' => (int) $post->id,
                'userid' => $USER->id,
            ]);
        }

        // Deleting ratings has no gradebook side effect, unlike adding one.
        $this->resync_gradebook($userid);
    }

    /**
     * The gradebook value core computed for this student, or null.
     *
     * Read rather than recomputed: aggregating post ratings into a user grade
     * is mod_forum's job, and second-guessing it is how the two drift apart.
     *
     * @param int $userid
     * @return float|null
     */
    public function get_gradebook_grade(int $userid): ?float {
        $item = $this->fetch_rating_grade_item();
        if (!$item) {
            return null;
        }
        $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
        if (!$grade || $grade->finalgrade === null) {
            return null;
        }
        return (float) $grade->finalgrade;
    }

    /**
     * The gradebook value formatted for display (scale label where relevant).
     *
     * @param int $userid
     * @return string
     */
    public function get_gradebook_display(int $userid): string {
        $grade = $this->get_gradebook_grade($userid);
        if ($grade === null) {
            return '';
        }
        return $this->format_aggregate($grade);
    }

    /**
     * The itemnumber-0 grade item that ratings feed.
     *
     * @return \grade_item|null
     */
    public function fetch_rating_grade_item(): ?\grade_item {
        $item = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'forum',
            'iteminstance' => (int) $this->forum->id,
            'itemnumber' => 0,
            'courseid' => (int) $this->course->id,
        ]);
        return $item ?: null;
    }

    /**
     * Post and rated-post counts for a whole cohort, in one query.
     *
     * "Rated" means at least one rating by anybody — a post another marker has
     * already scored is not outstanding work.
     *
     * @param int[] $userids
     * @return array userid => ['posts' => int, 'rated' => int]
     */
    public function count_post_stats(array $userids): array {
        global $DB;

        if (empty($userids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $params['forumid'] = (int) $this->forum->id;
        $params['contextid'] = $this->context->id;
        $params['component'] = 'mod_forum';
        $params['ratingarea'] = 'post';

        $sql = "SELECT p.userid,
                       COUNT(DISTINCT p.id) AS postcount,
                       COUNT(DISTINCT CASE WHEN r.id IS NOT NULL THEN p.id END) AS ratedcount
                  FROM {forum_posts} p
                  JOIN {forum_discussions} d ON d.id = p.discussion
             LEFT JOIN {rating} r ON r.itemid = p.id
                       AND r.contextid = :contextid
                       AND r.component = :component
                       AND r.ratingarea = :ratingarea
                 WHERE d.forum = :forumid AND p.deleted = 0 AND p.userid {$insql}
              GROUP BY p.userid";

        $stats = [];
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $stats[(int) $row->userid] = [
                'posts' => (int) $row->postcount,
                'rated' => (int) $row->ratedcount,
            ];
        }
        return $stats;
    }

    /**
     * Gradebook values for a whole cohort, in one query.
     *
     * @param int[] $userids
     * @return array userid => float
     */
    public function get_gradebook_grades(array $userids): array {
        global $DB;

        $item = $this->fetch_rating_grade_item();
        if (!$item || empty($userids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $params['itemid'] = $item->id;

        $sql = "SELECT userid, finalgrade
                  FROM {grade_grades}
                 WHERE itemid = :itemid AND finalgrade IS NOT NULL AND userid {$insql}";

        $grades = [];
        foreach ($DB->get_records_sql($sql, $params) as $row) {
            $grades[(int) $row->userid] = (float) $row->finalgrade;
        }
        return $grades;
    }
}
