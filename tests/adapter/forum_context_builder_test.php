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

namespace local_unifiedgrader\adapter;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/rating/lib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');

/**
 * Tests for the threaded post-context builder.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\adapter\forum_context_builder
 */
final class forum_context_builder_test extends \advanced_testcase {
    /** @var \stdClass Scenario fixture. */
    private \stdClass $s;

    /** @var \mod_forum_generator Forum generator. */
    private $forumgen;

    /**
     * Build a rating forum with a teacher and three students.
     *
     * @param array $modparams Extra forum parameters.
     */
    private function build(array $modparams = []): void {
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $this->s = $plugingen->create_grading_scenario('forum', [
            'modparams' => array_merge([
                'grade_forum' => 0,
                'assessed' => RATING_AGGREGATE_AVERAGE,
                'scale' => 5,
            ], $modparams),
            'studentcount' => 3,
        ]);
        $this->setUser($this->s->teacher);
        $this->s->adapter = adapter_factory::create($this->s->cm->id);
        $this->forumgen = $this->getDataGenerator()->get_plugin_generator('mod_forum');
    }

    /**
     * Start a discussion, returning [discussionid, firstpostid].
     *
     * @param \stdClass $author
     * @param string $name
     * @return array
     */
    private function discussion(\stdClass $author, string $name): array {
        $d = $this->forumgen->create_discussion((object) [
            'course' => $this->s->course->id,
            'forum' => $this->s->activity->id,
            'userid' => $author->id,
            'name' => $name,
            'message' => "Prompt: {$name}",
        ]);
        return [(int) $d->id, (int) $d->firstpost];
    }

    /**
     * Reply to a post.
     *
     * @param int $discussionid
     * @param int $parent
     * @param \stdClass $author
     * @param string $subject
     * @return int The new post id.
     */
    private function reply(int $discussionid, int $parent, \stdClass $author, string $subject): int {
        $post = $this->forumgen->create_post((object) [
            'discussion' => $discussionid,
            'parent' => $parent,
            'userid' => $author->id,
            'subject' => $subject,
            'message' => "Body of {$subject} with several words in it.",
        ]);
        return (int) $post->id;
    }

    /**
     * Find a post entry in a built discussion.
     *
     * @param array $discussion
     * @param int $postid
     * @return ?array
     */
    private function find(array $discussion, int $postid): ?array {
        foreach ($discussion['posts'] as $post) {
            if ($post['id'] === $postid) {
                return $post;
            }
        }
        return null;
    }

    /**
     * The tree is in reading order with correct depth, and target posts are
     * only the graded student's.
     */
    public function test_reading_order_and_depth(): void {
        $this->resetAfterTest();
        $this->build();
        [$alice, $bob, $carol] = $this->s->students;

        [$did, $prompt] = $this->discussion($this->s->teacher, 'Opening question');
        $alicepost = $this->reply($did, $prompt, $alice, 'Alice replies');
        $bobpost = $this->reply($did, $prompt, $bob, 'Bob replies');
        $carolreply = $this->reply($did, $alicepost, $carol, 'Carol answers Alice');

        $ctx = $this->s->adapter->get_post_context($alice->id);

        $this->assertCount(1, $ctx['discussions']);
        $d = $ctx['discussions'][0];
        $this->assertCount(4, $d['posts']);

        // Depth-first: prompt, Alice, Carol (under Alice), then Bob.
        $this->assertEquals(
            [$prompt, $alicepost, $carolreply, $bobpost],
            array_map(fn($p) => $p['id'], $d['posts']),
        );
        $this->assertEquals([0, 1, 2, 1], array_map(fn($p) => $p['depth'], $d['posts']));

        $this->assertTrue($this->find($d, $prompt)['isprompt']);
        $this->assertFalse($this->find($d, $alicepost)['isprompt']);

        // Only Alice's posts are targets — the pager walks what is being graded.
        $this->assertEquals([$alicepost], $ctx['targetpostids']);
    }

    /**
     * Only the graded student's posts carry grading metadata.
     */
    public function test_metadata_scoped_to_graded_student(): void {
        $this->resetAfterTest();
        $this->build();
        [$alice, $bob] = $this->s->students;

        [$did, $prompt] = $this->discussion($this->s->teacher, 'Question');
        $alicepost = $this->reply($did, $prompt, $alice, 'Alice');
        $bobpost = $this->reply($did, $prompt, $bob, 'Bob');

        $this->s->adapter->save_post_rating($alicepost, 4);
        $this->s->adapter->save_post_rating($bobpost, 2);

        $d = $this->s->adapter->get_post_context($alice->id)['discussions'][0];

        $mine = $this->find($d, $alicepost);
        $this->assertTrue($mine['isstudent']);
        $this->assertGreaterThan(0, $mine['wordcount']);
        $this->assertTrue($mine['hasrating']);
        $this->assertEquals(4, $mine['rating']['own']);

        // Bob's post is context, not work under assessment: no word count, and
        // no rating state even though he has in fact been rated.
        $theirs = $this->find($d, $bobpost);
        $this->assertFalse($theirs['isstudent']);
        $this->assertEquals(0, $theirs['wordcount']);
        $this->assertFalse($theirs['hasrating']);
        $this->assertArrayNotHasKey('rating', $theirs);
    }

    /**
     * Two posts by the same student in one discussion still yield one prompt.
     *
     * This is the case a naive paged implementation renders twice.
     */
    public function test_two_posts_in_one_discussion(): void {
        $this->resetAfterTest();
        $this->build();
        [$alice] = $this->s->students;

        [$did, $prompt] = $this->discussion($this->s->teacher, 'Question');
        $first = $this->reply($did, $prompt, $alice, 'Alice one');
        $second = $this->reply($did, $prompt, $alice, 'Alice two');

        $ctx = $this->s->adapter->get_post_context($alice->id);

        $this->assertCount(1, $ctx['discussions'], 'One discussion, not one per post');
        $this->assertEquals([$first, $second], $ctx['targetpostids']);

        $prompts = array_filter($ctx['discussions'][0]['posts'], fn($p) => $p['isprompt']);
        $this->assertCount(1, $prompts);
    }

    /**
     * Only discussions the student took part in are returned.
     */
    public function test_scoped_to_participating_discussions(): void {
        $this->resetAfterTest();
        $this->build();
        [$alice, $bob] = $this->s->students;

        [$did1, $prompt1] = $this->discussion($this->s->teacher, 'Alice was here');
        $this->reply($did1, $prompt1, $alice, 'Alice');

        [$did2, $prompt2] = $this->discussion($this->s->teacher, 'Alice was not');
        $this->reply($did2, $prompt2, $bob, 'Bob only');

        $ctx = $this->s->adapter->get_post_context($alice->id);
        $this->assertCount(1, $ctx['discussions']);
        $this->assertEquals($did1, $ctx['discussions'][0]['id']);
    }

    /**
     * A student who started the discussion has that first post as a target.
     */
    public function test_student_authored_prompt_is_a_target(): void {
        $this->resetAfterTest();
        $this->build();
        [$alice, $bob] = $this->s->students;

        [$did, $prompt] = $this->discussion($alice, 'Alice opens');
        $this->reply($did, $prompt, $bob, 'Bob answers');

        $ctx = $this->s->adapter->get_post_context($alice->id);
        $this->assertEquals([$prompt], $ctx['targetpostids']);

        $entry = $this->find($ctx['discussions'][0], $prompt);
        $this->assertTrue($entry['isprompt']);
        $this->assertTrue($entry['isstudent']);
    }

    /**
     * Separate groups: a teacher without accessallgroups sees only their group's
     * posts here, exactly as they would in the forum itself.
     */
    public function test_separate_groups_visibility(): void {
        global $DB;
        $this->resetAfterTest();
        $this->build(['groupmode' => SEPARATEGROUPS]);
        [$alice, $bob] = $this->s->students;

        $DB->set_field('course_modules', 'groupmode', SEPARATEGROUPS, ['id' => $this->s->cm->id]);
        rebuild_course_cache($this->s->course->id, true);

        $groupa = $this->getDataGenerator()->create_group(['courseid' => $this->s->course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $this->s->course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $alice->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupb->id, 'userid' => $bob->id]);

        // A non-editing teacher in group A only.
        $limited = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($limited->id, $this->s->course->id, 'teacher');
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $limited->id]);
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability(
            'moodle/site:accessallgroups',
            CAP_PROHIBIT,
            $roleid,
            \context_course::instance($this->s->course->id)->id,
            true,
        );

        $groupbdiscussion = $this->forumgen->create_discussion((object) [
            'course' => $this->s->course->id,
            'forum' => $this->s->activity->id,
            'userid' => $bob->id,
            'groupid' => $groupb->id,
            'name' => 'Group B only',
            'message' => 'Not for group A eyes.',
        ]);

        $this->setUser($limited);
        $adapter = adapter_factory::create($this->s->cm->id);
        $ctx = $adapter->get_post_context($bob->id);

        $names = array_map(fn($d) => $d['name'], $ctx['discussions']);
        $this->assertNotContains(
            'Group B only',
            $names,
            'A teacher without accessallgroups must not read another group\'s thread here',
        );
        unset($groupbdiscussion);
    }

    /**
     * A student with no posts yields an empty payload rather than an error.
     */
    public function test_empty_payload_for_silent_student(): void {
        $this->resetAfterTest();
        $this->build();
        [$alice, , $carol] = $this->s->students;

        [$did, $prompt] = $this->discussion($this->s->teacher, 'Question');
        $this->reply($did, $prompt, $alice, 'Alice');

        $ctx = $this->s->adapter->get_post_context($carol->id);
        $this->assertSame([], $ctx['discussions']);
        $this->assertSame([], $ctx['targetpostids']);
    }

    /**
     * The builder also runs for a whole-forum forum, just with no rating state.
     */
    public function test_works_without_rating_mode(): void {
        $this->resetAfterTest();
        $this->build(['grade_forum' => 100, 'assessed' => 0, 'scale' => 0]);
        [$alice] = $this->s->students;

        $this->assertEquals(forum_adapter::MODE_WHOLE, $this->s->adapter->get_grading_mode());

        [$did, $prompt] = $this->discussion($this->s->teacher, 'Question');
        $post = $this->reply($did, $prompt, $alice, 'Alice');

        $ctx = $this->s->adapter->get_post_context($alice->id);
        $this->assertEquals([$post], $ctx['targetpostids']);
        $this->assertFalse($this->find($ctx['discussions'][0], $post)['hasrating']);
    }
}
