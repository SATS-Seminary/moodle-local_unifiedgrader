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
 * Threaded post-context builder for the forum preview panel.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\adapter;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/forum/lib.php');

/**
 * Builds the discussion tree surrounding a student's posts.
 *
 * A rating is a judgement about one post, and a reply is frequently
 * unintelligible on its own — "I agree, but 8:28 cuts the other way" cannot be
 * scored without the post it answers. The flat list of a student's own posts
 * that serves whole-forum grading is the wrong instrument here.
 *
 * This returns one payload covering every discussion the student took part in,
 * in reading order with depth. The paged view selects a target post's prompt,
 * parent, siblings and replies from it; the thread view renders it whole. One
 * fetch per student, so paging costs no round trip.
 */
class forum_context_builder {
    /** @var \cm_info Course module info. */
    private \cm_info $cm;

    /** @var \context_module Module context. */
    private \context_module $context;

    /** @var \stdClass Raw forum DB record. */
    private \stdClass $forum;

    /** @var forum_rating_helper|null Present only for rating forums. */
    private ?forum_rating_helper $ratinghelper;

    /**
     * Constructor.
     *
     * @param \cm_info $cm Course module info.
     * @param \context_module $context Module context.
     * @param \stdClass $forum Raw forum DB record.
     * @param forum_rating_helper|null $ratinghelper Supplied in rating mode only.
     */
    public function __construct(
        \cm_info $cm,
        \context_module $context,
        \stdClass $forum,
        ?forum_rating_helper $ratinghelper = null,
    ) {
        $this->cm = $cm;
        $this->context = $context;
        $this->forum = $forum;
        $this->ratinghelper = $ratinghelper;
    }

    /**
     * Build the full context payload for one student.
     *
     * @param int $userid The student being graded.
     * @return array With keys discussions and targetpostids.
     */
    public function build(int $userid): array {
        global $DB;

        $discussions = $this->get_participating_discussions($userid);
        if (empty($discussions)) {
            return ['discussions' => [], 'targetpostids' => []];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_keys($discussions), SQL_PARAMS_NAMED, 'd');
        $allposts = $DB->get_records_select(
            'forum_posts',
            "discussion {$insql} AND deleted = 0",
            $params,
            'created ASC',
        );

        // Authors are fetched separately rather than joined: forum_posts.* already
        // occupies the `id` column, so a user-picture join would need aliasing
        // every field just to avoid that one clash.
        $authors = $this->fetch_authors($allposts);

        // Rating state for the graded student's posts only — a classmate's
        // ratings are not this teacher's business inside this view.
        $ratings = [];
        if ($this->ratinghelper) {
            $ownposts = array_filter($allposts, fn($p) => (int) $p->userid === $userid);
            if (!empty($ownposts)) {
                $ratings = $this->ratinghelper->decorate_posts_with_ratings($ownposts, $userid);
            }
        }

        $result = [];
        $targetpostids = [];

        foreach ($discussions as $discussion) {
            $posts = array_filter(
                $allposts,
                fn($p) => (int) $p->discussion === (int) $discussion->id,
            );

            // Core's own visibility test — this is what keeps a teacher without
            // accessallgroups from reading another group's thread here when they
            // could not read it in the forum itself.
            $visible = [];
            foreach ($posts as $post) {
                if (forum_user_can_see_post($this->forum, $discussion, $post, null, $this->cm)) {
                    $visible[(int) $post->id] = $post;
                }
            }
            if (empty($visible)) {
                continue;
            }

            $ordered = $this->sort_into_reading_order($visible);

            $rendered = [];
            foreach ($ordered as [$post, $depth]) {
                $isstudent = (int) $post->userid === $userid;
                if ($isstudent) {
                    $targetpostids[] = (int) $post->id;
                }
                $rendered[] = $this->render_post(
                    $post,
                    $authors[(int) $post->userid] ?? null,
                    $depth,
                    $isstudent,
                    (int) $post->id === (int) $discussion->firstpost,
                    $ratings[(int) $post->id] ?? null,
                );
            }

            $result[] = [
                'id' => (int) $discussion->id,
                'name' => format_string($discussion->name),
                'posts' => $rendered,
            ];
        }

        return [
            'discussions' => $result,
            'targetpostids' => $targetpostids,
        ];
    }

    /**
     * Batch-load the user records needed to render post authors.
     *
     * @param array $posts Post records.
     * @return array User records keyed by id, carrying user-picture fields.
     */
    private function fetch_authors(array $posts): array {
        global $DB;

        $userids = array_unique(array_map(fn($p) => (int) $p->userid, $posts));
        if (empty($userids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'au');
        $fields = \core_user\fields::for_userpic()->get_sql('', false, '', '', false)->selects;

        return $DB->get_records_select('user', "id {$insql}", $params, '', $fields);
    }

    /**
     * Discussions in this forum the student actually posted in.
     *
     * Scoping to these keeps the payload proportionate: a busy forum can carry
     * dozens of threads the student never touched, and none of them are context
     * for anything being rated.
     *
     * @param int $userid
     * @return array Discussion records keyed by id.
     */
    private function get_participating_discussions(int $userid): array {
        global $DB;

        $sql = "SELECT DISTINCT d.*
                  FROM {forum_discussions} d
                  JOIN {forum_posts} p ON p.discussion = d.id
                 WHERE d.forum = :forumid AND p.userid = :userid AND p.deleted = 0
              ORDER BY d.timemodified ASC";
        return $DB->get_records_sql($sql, [
            'forumid' => (int) $this->forum->id,
            'userid' => $userid,
        ]);
    }

    /**
     * Arrange posts depth-first, replies under their parent, oldest first.
     *
     * This is the order a reader sees in the forum itself, which is the whole
     * point of offering the view.
     *
     * @param array $posts Visible post records keyed by id.
     * @return array List of [post, depth] pairs.
     */
    private function sort_into_reading_order(array $posts): array {
        $children = [];
        $roots = [];

        foreach ($posts as $post) {
            $parent = (int) $post->parent;
            // A post whose parent was filtered out (or is the thread root)
            // becomes a root here, so nothing gets orphaned into invisibility.
            if ($parent === 0 || !isset($posts[$parent])) {
                $roots[] = $post;
            } else {
                $children[$parent][] = $post;
            }
        }

        $ordered = [];
        $walk = function (array $siblings, int $depth) use (&$walk, &$ordered, $children): void {
            usort($siblings, fn($a, $b) => (int) $a->created <=> (int) $b->created);
            foreach ($siblings as $post) {
                $ordered[] = [$post, $depth];
                $kids = $children[(int) $post->id] ?? [];
                if (!empty($kids)) {
                    $walk($kids, $depth + 1);
                }
            }
        };
        $walk($roots, 0);

        return $ordered;
    }

    /**
     * Turn one post record into the client-facing structure.
     *
     * @param \stdClass $post Post record.
     * @param \stdClass|null $author The post's author, carrying user-picture fields.
     * @param int $depth Nesting depth, 0 for the thread root.
     * @param bool $isstudent Whether the graded student wrote it.
     * @param bool $isprompt Whether it is the discussion's first post.
     * @param array|null $rating Rating state, for the student's posts in rating mode.
     * @return array
     */
    private function render_post(
        \stdClass $post,
        ?\stdClass $author,
        int $depth,
        bool $isstudent,
        bool $isprompt,
        ?array $rating,
    ): array {
        global $PAGE;

        $message = file_rewrite_pluginfile_urls(
            $post->message,
            'pluginfile.php',
            $this->context->id,
            'mod_forum',
            'post',
            $post->id,
        );
        $formatted = format_text($message, $post->messageformat, ['context' => $this->context]);

        $authorname = '';
        $authorpicture = '';
        if ($author) {
            $userpicture = new \user_picture($author);
            $userpicture->size = 32;
            $authorname = fullname($author);
            $authorpicture = $userpicture->get_url($PAGE)->out(false);
        }

        // Word count is grading metadata, so it goes on the work being graded
        // and not on the classmates' posts that merely surround it.
        $wordcount = $isstudent ? count_words(html_to_text($formatted, 0, false)) : 0;

        $entry = [
            'id' => (int) $post->id,
            'parent' => (int) $post->parent,
            'depth' => $depth,
            'discussionid' => (int) $post->discussion,
            'subject' => format_string($post->subject),
            'message' => $formatted,
            'authorname' => $authorname,
            'authorpicture' => $authorpicture,
            'created' => (int) $post->created,
            'createddisplay' => userdate($post->created),
            'wordcount' => $wordcount,
            'isstudent' => $isstudent,
            'isprompt' => $isprompt,
            'hasrating' => $rating !== null,
        ];

        // The key is omitted rather than nulled: an optional structure in a web
        // service return has to be absent, not present-and-null.
        if ($rating !== null) {
            $entry['rating'] = [
                'own' => $rating['own'],
                'aggregate' => $rating['aggregate'],
                'aggregatelabel' => $rating['aggregatelabel'],
                'count' => $rating['count'],
                'canrate' => $rating['canrate'],
                'noratereason' => $rating['noratereason'],
            ];
        }

        return $entry;
    }
}
