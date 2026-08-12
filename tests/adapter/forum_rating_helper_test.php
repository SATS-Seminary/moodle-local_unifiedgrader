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
 * Tests for rating-based forum grading.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\adapter\forum_rating_helper
 * @covers \local_unifiedgrader\adapter\forum_adapter
 */
final class forum_rating_helper_test extends \advanced_testcase {
    /**
     * Build a forum scenario with the given grading configuration.
     *
     * @param array $modparams Forum module parameters.
     * @param int $studentcount How many students to enrol.
     * @return \stdClass Scenario with an ->adapter attached.
     */
    private function scenario(array $modparams, int $studentcount = 2): \stdClass {
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('forum', [
            'modparams' => $modparams,
            'studentcount' => $studentcount,
        ]);
        $this->setUser($scenario->teacher);
        $scenario->adapter = adapter_factory::create($scenario->cm->id);
        return $scenario;
    }

    /**
     * A rating forum: ratings on, whole-forum grading off.
     *
     * @param int $aggregate RATING_AGGREGATE_* constant.
     * @param int $scale Points max, or -scaleid.
     * @param int $studentcount
     * @return \stdClass
     */
    private function rating_scenario(
        int $aggregate = RATING_AGGREGATE_AVERAGE,
        int $scale = 5,
        int $studentcount = 2,
    ): \stdClass {
        return $this->scenario([
            'grade_forum' => 0,
            'assessed' => $aggregate,
            'scale' => $scale,
        ], $studentcount);
    }

    /**
     * Post as a student, returning the post id.
     *
     * @param \stdClass $scenario
     * @param \stdClass $student
     * @param string $subject
     * @param int $parent Parent post id, or 0 to start a discussion.
     * @return int
     */
    private function post(\stdClass $scenario, \stdClass $student, string $subject, int $parent = 0): int {
        $forumgen = $this->getDataGenerator()->get_plugin_generator('mod_forum');

        if ($parent === 0) {
            $discussion = $forumgen->create_discussion((object) [
                'course' => $scenario->course->id,
                'forum' => $scenario->activity->id,
                'userid' => $student->id,
                'name' => $subject,
                'message' => "Body of {$subject}",
            ]);
            return (int) $discussion->firstpost;
        }

        $parentpost = $this->getDataGenerator()->get_plugin_generator('mod_forum')->create_post((object) [
            'discussion' => $this->parent_discussion($parent),
            'parent' => $parent,
            'userid' => $student->id,
            'subject' => $subject,
            'message' => "Body of {$subject}",
        ]);
        return (int) $parentpost->id;
    }

    /**
     * The discussion a post belongs to.
     *
     * @param int $postid
     * @return int
     */
    private function parent_discussion(int $postid): int {
        global $DB;
        return (int) $DB->get_field('forum_posts', 'discussion', ['id' => $postid], MUST_EXIST);
    }

    /**
     * Mode resolution across every combination of the two grading systems.
     */
    public function test_grading_mode_resolution(): void {
        $this->resetAfterTest();

        $whole = $this->scenario(['grade_forum' => 100, 'assessed' => 0]);
        $this->assertEquals(forum_adapter::MODE_WHOLE, $whole->adapter->get_grading_mode());

        $rating = $this->scenario(['grade_forum' => 0, 'assessed' => RATING_AGGREGATE_AVERAGE, 'scale' => 5]);
        $this->assertEquals(forum_adapter::MODE_RATING, $rating->adapter->get_grading_mode());

        $none = $this->scenario(['grade_forum' => 0, 'assessed' => 0]);
        $this->assertEquals(forum_adapter::MODE_NONE, $none->adapter->get_grading_mode());

        // Both configured: whole-forum grading wins, which is what keeps every
        // forum that works today on the path it has always taken.
        $both = $this->scenario(['grade_forum' => 100, 'assessed' => RATING_AGGREGATE_AVERAGE, 'scale' => 5]);
        $this->assertEquals(forum_adapter::MODE_WHOLE, $both->adapter->get_grading_mode());

        // Ratings on but no scale is not a usable rating forum.
        $noscale = $this->scenario(['grade_forum' => 0, 'assessed' => RATING_AGGREGATE_AVERAGE, 'scale' => 0]);
        $this->assertEquals(forum_adapter::MODE_NONE, $noscale->adapter->get_grading_mode());
    }

    /**
     * fetch_grade_item() must target itemnumber 0 for ratings, 1 for whole-forum.
     */
    public function test_grade_item_targeting(): void {
        $this->resetAfterTest();

        $fetch = function (forum_adapter $adapter): ?\grade_item {
            $method = new \ReflectionMethod($adapter, 'fetch_grade_item');
            $method->setAccessible(true);
            return $method->invoke($adapter);
        };

        $rating = $this->rating_scenario();
        $item = $fetch($rating->adapter);
        $this->assertNotNull($item);
        $this->assertEquals(0, (int) $item->itemnumber);

        $whole = $this->scenario(['grade_forum' => 100]);
        $this->assertEquals(1, (int) $fetch($whole->adapter)->itemnumber);
    }

    /**
     * Scale metadata comes from forum.scale, with the value ranges core's
     * rating validator will accept.
     */
    public function test_scale_info(): void {
        $this->resetAfterTest();

        $points = $this->rating_scenario(RATING_AGGREGATE_AVERAGE, 5);
        $info = $points->adapter->get_activity_info();
        $this->assertEquals('rating', $info['gradingmode']);
        $this->assertFalse($info['usescale']);
        $this->assertEquals(5.0, (float) $info['maxgrade']);
        // Point scales include zero, so 0..5 is six options.
        $this->assertCount(6, $info['scaleitems']);
        $this->assertEquals(0, $info['scaleitems'][0]['value']);

        $scale = $this->getDataGenerator()->create_scale([
            'name' => 'Quality', 'scale' => 'Poor,Fair,Good,Excellent',
        ]);
        $scaled = $this->rating_scenario(RATING_AGGREGATE_SUM, -$scale->id);
        $sinfo = $scaled->adapter->get_activity_info();
        $this->assertTrue($sinfo['usescale']);
        $this->assertEquals(4.0, (float) $sinfo['maxgrade']);
        // Custom scales are 1-based; index 0 is not a valid rating.
        $this->assertEquals(1, $sinfo['scaleitems'][0]['value']);
        $this->assertEquals('Poor', $sinfo['scaleitems'][0]['label']);
    }

    /**
     * Rating a post moves the gradebook, and clearing it moves it back.
     */
    public function test_rate_and_clear(): void {
        $this->resetAfterTest();

        $s = $this->rating_scenario(RATING_AGGREGATE_AVERAGE, 5);
        $student = $s->students[0];
        $postid = $this->post($s, $student, 'First');

        $helper = $s->adapter->rating_helper();
        $this->assertNull($helper->get_gradebook_grade($student->id));

        $s->adapter->save_post_rating($postid, 4);
        $this->assertEquals(4.0, $helper->get_gradebook_grade($student->id));

        $ratings = $s->adapter->get_post_ratings($student->id);
        $this->assertCount(1, $ratings);
        $this->assertEquals(4, $ratings[0]['own']);
        $this->assertEquals(1, $ratings[0]['count']);

        // RATING_UNSET_RATING removes the rating; it does not score zero.
        $s->adapter->save_post_rating($postid, RATING_UNSET_RATING);
        $cleared = $s->adapter->get_post_ratings($student->id);
        $this->assertNull($cleared[0]['own']);
        $this->assertEquals(0, $cleared[0]['count']);
        $this->assertNull($helper->get_gradebook_grade($student->id));
    }

    /**
     * The gradebook aggregates across all of a student's posts, per method.
     */
    public function test_aggregation_methods(): void {
        $this->resetAfterTest();

        // Average of 5, 4, 3.
        $avg = $this->rating_scenario(RATING_AGGREGATE_AVERAGE, 5);
        $student = $avg->students[0];
        $posts = [
            $this->post($avg, $student, 'A'),
            $this->post($avg, $student, 'B'),
            $this->post($avg, $student, 'C'),
        ];
        foreach ([5, 4, 3] as $i => $value) {
            $avg->adapter->save_post_rating($posts[$i], $value);
        }
        $this->assertEqualsWithDelta(4.0, $avg->adapter->rating_helper()->get_gradebook_grade($student->id), 0.001);

        // Sum over a four-item scale: core clamps the total to the scale max,
        // so three "Excellent" ratings cannot produce 12.
        $scale = $this->getDataGenerator()->create_scale([
            'name' => 'Quality2', 'scale' => 'Poor,Fair,Good,Excellent',
        ]);
        $sum = $this->rating_scenario(RATING_AGGREGATE_SUM, -$scale->id);
        $sumstudent = $sum->students[0];
        foreach (['X', 'Y', 'Z'] as $subject) {
            $sum->adapter->save_post_rating($this->post($sum, $sumstudent, $subject), 4);
        }
        $this->assertEquals(4.0, $sum->adapter->rating_helper()->get_gradebook_grade($sumstudent->id));
    }

    /**
     * Feedback stored on the ratings grade item survives every later rating change.
     *
     * This is the assumption the whole feedback design rests on: grade_update()
     * omits the feedback key, and grade_item::update_raw_grade() only writes
     * feedback when that key is present. If core ever changed that, feedback
     * would silently vanish whenever a teacher adjusted a mark.
     */
    public function test_feedback_survives_rating_change(): void {
        $this->resetAfterTest();

        $s = $this->rating_scenario(RATING_AGGREGATE_AVERAGE, 5);
        $student = $s->students[0];
        $postid = $this->post($s, $student, 'First');

        $s->adapter->save_grade($student->id, null, '<p>Thoughtful and well evidenced.</p>', FORMAT_HTML);
        $this->assertStringContainsString(
            'Thoughtful and well evidenced',
            $s->adapter->get_grade_data($student->id)['feedback'],
        );

        $s->adapter->save_post_rating($postid, 5);

        $after = $s->adapter->get_grade_data($student->id);
        $this->assertStringContainsString('Thoughtful and well evidenced', $after['feedback']);
        $this->assertEquals(5.0, $after['grade']);

        // And again on a change rather than a first write.
        $s->adapter->save_post_rating($postid, 2);
        $this->assertStringContainsString(
            'Thoughtful and well evidenced',
            $s->adapter->get_grade_data($student->id)['feedback'],
        );
    }

    /**
     * Saving in rating mode must never create a whole-forum grade row.
     */
    public function test_save_grade_writes_no_forum_grades_row(): void {
        global $DB;
        $this->resetAfterTest();

        $s = $this->rating_scenario();
        $student = $s->students[0];
        $this->post($s, $student, 'First');

        // Even when a grade value is passed, it is not ours to store.
        $s->adapter->save_grade($student->id, 42.0, '<p>Note</p>', FORMAT_HTML);

        $this->assertEquals(0, $DB->count_records('forum_grades', ['forum' => $s->activity->id]));
    }

    /**
     * A teacher may not rate their own post, and the UI is told why.
     */
    public function test_cannot_rate_own_post(): void {
        $this->resetAfterTest();

        $s = $this->rating_scenario();
        $teacherpost = $this->post($s, $s->teacher, 'Teacher thread');

        $ratings = $s->adapter->get_post_ratings($s->teacher->id);
        $this->assertCount(1, $ratings);
        $this->assertEquals($teacherpost, $ratings[0]['postid']);
        $this->assertFalse($ratings[0]['canrate']);
        $this->assertNotEmpty($ratings[0]['noratereason']);
    }

    /**
     * A post outside the forum's rating window cannot be rated.
     */
    public function test_cannot_rate_outside_assess_window(): void {
        global $DB;
        $this->resetAfterTest();

        // A window that closed before the post was written.
        $s = $this->rating_scenario();
        $student = $s->students[0];
        $postid = $this->post($s, $student, 'Late thought');

        $DB->set_field('forum', 'assesstimestart', time() - DAYSECS * 10, ['id' => $s->activity->id]);
        $DB->set_field('forum', 'assesstimefinish', time() - DAYSECS * 5, ['id' => $s->activity->id]);

        // Rebuild so the adapter picks up the changed forum record.
        $adapter = adapter_factory::create($s->cm->id);
        $ratings = $adapter->get_post_ratings($student->id);

        $this->assertEquals($postid, $ratings[0]['postid']);
        $this->assertFalse($ratings[0]['canrate']);
        $this->assertNotEmpty($ratings[0]['noratereason']);
    }

    /**
     * Participants: a student is only "graded" once every post has been rated.
     */
    public function test_participant_status_tracks_partial_rating(): void {
        $this->resetAfterTest();

        $s = $this->rating_scenario();
        $student = $s->students[0];
        $first = $this->post($s, $student, 'One');
        $this->post($s, $student, 'Two');

        $findrow = function (array $participants, int $userid): ?array {
            foreach ($participants as $p) {
                if ((int) $p['id'] === $userid) {
                    return $p;
                }
            }
            return null;
        };

        $s->adapter->save_post_rating($first, 3);
        $partial = $findrow($s->adapter->get_participants(), $student->id);
        $this->assertEquals('submitted', $partial['status'], 'A half-rated student still needs grading');

        // Rate the second post too.
        $ratings = $s->adapter->get_post_ratings($student->id);
        foreach ($ratings as $r) {
            if ($r['count'] === 0) {
                $s->adapter->save_post_rating($r['postid'], 4);
            }
        }
        $complete = $findrow($s->adapter->get_participants(), $student->id);
        $this->assertEquals('graded', $complete['status']);
        $this->assertEqualsWithDelta(3.5, $complete['gradevalue'], 0.001);
    }

    /**
     * Resetting withdraws only this grader's ratings, and never the posts.
     */
    public function test_reset_removes_only_own_ratings(): void {
        global $DB;
        $this->resetAfterTest();

        $s = $this->rating_scenario();
        $student = $s->students[0];
        $postid = $this->post($s, $student, 'Shared');

        // A second marker rates the same post.
        $other = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($other->id, $s->course->id, 'editingteacher');
        $this->setUser($other);
        adapter_factory::create($s->cm->id)->save_post_rating($postid, 5);

        $this->setUser($s->teacher);
        $s->adapter->save_post_rating($postid, 1);
        $this->assertEquals(2, $DB->count_records('rating', ['itemid' => $postid]));

        $s->adapter->reset_grade_and_submission($student->id);

        $remaining = $DB->get_records('rating', ['itemid' => $postid]);
        $this->assertCount(1, $remaining, 'The other marker\'s rating must survive');
        $this->assertEquals($other->id, (int) reset($remaining)->userid);
        // The student's post is never the teacher's to delete.
        $this->assertTrue($DB->record_exists('forum_posts', ['id' => $postid]));
    }

    /**
     * Advanced grading is a whole-forum feature; ratings must not offer it.
     */
    public function test_no_advanced_grading_in_rating_mode(): void {
        $this->resetAfterTest();

        $s = $this->rating_scenario();
        $this->assertFalse($s->adapter->supports_feature('rubric'));
        $this->assertFalse($s->adapter->supports_feature('markingguide'));
        $this->assertTrue($s->adapter->supports_feature('postratings'));
        $this->assertNull($s->adapter->get_grading_definition());
    }

    /**
     * Release: a rated or commented-on student can see their feedback.
     */
    public function test_is_grade_released(): void {
        $this->resetAfterTest();

        $s = $this->rating_scenario();
        $student = $s->students[0];
        $postid = $this->post($s, $student, 'One');

        $this->assertFalse($s->adapter->is_grade_released($student->id));

        $s->adapter->save_post_rating($postid, 3);
        $this->assertTrue($s->adapter->is_grade_released($student->id));

        // Feedback alone is also worth releasing.
        $other = $s->students[1];
        $this->post($s, $other, 'Two');
        $this->assertFalse($s->adapter->is_grade_released($other->id));
        $s->adapter->save_grade($other->id, null, '<p>Some thoughts.</p>', FORMAT_HTML);
        $this->assertTrue($s->adapter->is_grade_released($other->id));
    }
}
