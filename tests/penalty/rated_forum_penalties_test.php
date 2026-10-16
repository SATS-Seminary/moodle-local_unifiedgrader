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

use local_unifiedgrader\adapter\adapter_factory;
use local_unifiedgrader\penalty\compat;
use local_unifiedgrader\penalty\service;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/rating/lib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');

/**
 * Penalties on forums graded by post ratings (Moodle 5.3+).
 *
 * They apply only when the ratings are points combined by average or sum, and
 * are recorded as a deduction beside the ratings grade, which Moodle clears
 * every time a post is rated.
 *
 * Every test uses one site rule set: up to a day late costs 10%, anything
 * later 20%. The due date is five days ago and the student posts now, so the
 * late penalty is 20% of the points maximum.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_unifiedgrader\adapter\forum_adapter
 * @covers     \local_unifiedgrader\adapter\forum_rating_helper
 * @covers     \local_unifiedgrader\observer
 * @covers     \local_unifiedgrader\feedback_data_helper
 */
final class rated_forum_penalties_test extends \advanced_testcase {
    /** @var int The points maximum of the ratings. */
    private const MAXRATING = 10;

    protected function setUp(): void {
        global $DB;
        parent::setUp();

        compat::reset_cache();
        if (!compat::unified()) {
            $this->markTestSkipped('Penalties on rated forums need Moodle 5.3 or later.');
        }
        $this->resetAfterTest();

        $systemid = \context_system::instance()->id;
        foreach ([[DAYSECS, 10], [2 * DAYSECS, 20]] as $i => [$overdueby, $penalty]) {
            $DB->insert_record('gradepenalty_duedate_rule', (object) [
                'contextid' => $systemid,
                'sortorder' => $i,
                'overdueby' => $overdueby,
                'penalty' => $penalty,
                'usermodified' => 0,
                'timecreated' => time(),
                'timemodified' => time(),
            ]);
        }
    }

    // Eligibility.

    /**
     * Points combined by average or sum qualify. Nothing else does.
     */
    public function test_eligibility_matrix(): void {
        $scale = $this->getDataGenerator()->create_scale(['name' => 'Quality', 'scale' => 'Poor,Fair,Good,Excellent']);
        $cases = [
            'average of points' => [RATING_AGGREGATE_AVERAGE, self::MAXRATING, true],
            'sum of points' => [RATING_AGGREGATE_SUM, self::MAXRATING, true],
            'count' => [RATING_AGGREGATE_COUNT, self::MAXRATING, false],
            'maximum' => [RATING_AGGREGATE_MAXIMUM, self::MAXRATING, false],
            'minimum' => [RATING_AGGREGATE_MINIMUM, self::MAXRATING, false],
            'average of a scale' => [RATING_AGGREGATE_AVERAGE, -$scale->id, false],
            'sum of a scale' => [RATING_AGGREGATE_SUM, -$scale->id, false],
        ];
        foreach ($cases as $name => [$aggregate, $ratingscale, $expected]) {
            $s = $this->rated_scenario($aggregate, $ratingscale);
            $userid = (int) $s->students[0]->id;
            $adapter = adapter_factory::create((int) $s->cm->id);

            $this->assertSame($expected, $adapter->rating_penalties_apply(), $name);
            $this->assertSame($expected, $adapter->get_activity_info()['ratingpenaltieseligible'], $name);
            $this->assertSame($expected ? 20 : null, $adapter->calculate_late_penalty($userid)['percentage'] ?? null, $name);
        }
    }

    /**
     * Whole-forum grading is decided as it always was, and is not "rated".
     */
    public function test_whole_forum_grading_is_unaffected(): void {
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $gen->create_grading_scenario('forum', [
            'modparams' => ['grade_forum' => 100, 'assessed' => 0, 'duedate' => time() - 5 * DAYSECS],
        ]);
        $userid = (int) $s->students[0]->id;
        $gen->create_forum_post($s->activity, $userid);

        $adapter = adapter_factory::create((int) $s->cm->id);
        $this->assertFalse($adapter->rating_penalties_apply());
        $this->assertFalse($adapter->get_activity_info()['ratingpenaltieseligible']);
        $this->assertSame(20, $adapter->calculate_late_penalty($userid)['percentage']);
    }

    // The deduction.

    /**
     * A late penalty and a manual one come off the ratings aggregate, which is not touched.
     */
    public function test_late_and_manual_penalties_are_deducted_from_the_ratings_grade(): void {
        $s = $this->rated_scenario(RATING_AGGREGATE_AVERAGE);
        $userid = (int) $s->students[0]->id;
        $adapter = adapter_factory::create((int) $s->cm->id);
        $postid = $this->first_post_id($s, $userid);

        $this->setUser($s->teacher);
        $adapter->save_post_rating($postid, 8);

        // 20% late of 10 points: 2 off the 8 awarded.
        $this->assertSame(20, $this->penalty_pct($s, $userid, 'late'));
        $this->assert_cell($s, $userid, 8.0, 2.0, 6.0);
        $this->assertEqualsWithDelta(8.0, $adapter->get_grade_data($userid)['grade'], 0.001);

        // A manual 30% joins it: 5 off in all.
        penalty_manager::save_penalty((int) $s->cm->id, $userid, (int) $s->teacher->id, 'other', 'Plagiarism', 30);
        $adapter->sync_gradebook_penalty($userid);
        $this->assert_cell($s, $userid, 8.0, 5.0, 3.0);

        // Penalties worth more than the mark stop at zero, and the rating is still 8.
        penalty_manager::save_penalty((int) $s->cm->id, $userid, (int) $s->teacher->id, 'other', 'Cheating', 80);
        $adapter->sync_gradebook_penalty($userid);
        $this->assert_cell($s, $userid, 8.0, 13.0, 0.0);
        $this->assertEqualsWithDelta(8.0, $adapter->get_grade_data($userid)['grade'], 0.001);
    }

    /**
     * The student's page and PDF work the sum from the aggregate, and land on the gradebook figure.
     */
    public function test_student_breakdown_matches_the_gradebook(): void {
        $s = $this->rated_scenario(RATING_AGGREGATE_AVERAGE);
        $userid = (int) $s->students[0]->id;
        $adapter = adapter_factory::create((int) $s->cm->id);

        $this->setUser($s->teacher);
        $adapter->save_post_rating($this->first_post_id($s, $userid), 8);

        $gradeinfo = feedback_data_helper::format_grade(
            $adapter->get_grade_data($userid),
            $adapter->get_activity_info(),
        );
        $breakdown = feedback_data_helper::penalty_breakdown((int) $s->cm->id, $userid, $gradeinfo);

        $this->assertTrue($breakdown['haspenalties']);
        $this->assertSame('8 / 10', $breakdown['awarded']);
        $this->assertSame(20, $breakdown['totalpercentage']);
        $this->assertEqualsWithDelta($this->cell($s, $userid)->finalgrade, $breakdown['finalvalue'], 0.001);
        $this->assertSame('6 / 10', $breakdown['final']);
    }

    /**
     * Moodle clears the deduction whenever a post is rated, so it is put back.
     *
     * Both rating routes are covered: through the grader, and through core
     * alone (forum_update_grades(), which is what the rating API calls and
     * which the plugin does not wrap).
     */
    public function test_deduction_survives_a_rerating(): void {
        global $DB;

        $s = $this->rated_scenario(RATING_AGGREGATE_AVERAGE);
        $userid = (int) $s->students[0]->id;
        $adapter = adapter_factory::create((int) $s->cm->id);
        $first = $this->first_post_id($s, $userid);
        $second = $this->add_post($s, $userid);

        $this->setUser($s->teacher);
        $adapter->save_post_rating($first, 8);
        $adapter->save_post_rating($second, 4);
        $this->assert_cell($s, $userid, 6.0, 2.0, 4.0);

        // The grader's own route: the aggregate moves to 9, the deduction stays.
        $adapter->save_post_rating($second, 10);
        $this->assert_cell($s, $userid, 9.0, 2.0, 7.0);

        // Core's route alone. The rating row changes underneath, and Moodle pushes
        // the new aggregate as it does when anyone rates through the forum page.
        $DB->set_field('rating', 'rating', 2, ['itemid' => $second, 'component' => 'mod_forum']);
        $forum = $DB->get_record('forum', ['id' => $s->activity->id], '*', MUST_EXIST);
        $forum->cmidnumber = $s->cm->idnumber;
        forum_update_grades($forum, $userid);
        $this->assert_cell($s, $userid, 5.0, 2.0, 3.0);

        // The same aggregate pushed again leaves the cell as it is.
        forum_update_grades($forum, $userid);
        $this->assert_cell($s, $userid, 5.0, 2.0, 3.0);
    }

    /**
     * A forum that does not qualify never has a deduction, even from a penalty row.
     */
    public function test_ineligible_forum_never_gets_a_deduction(): void {
        foreach ([RATING_AGGREGATE_COUNT, RATING_AGGREGATE_MAXIMUM, RATING_AGGREGATE_MINIMUM] as $aggregate) {
            $s = $this->rated_scenario($aggregate);
            $userid = (int) $s->students[0]->id;
            $adapter = adapter_factory::create((int) $s->cm->id);

            $this->setUser($s->teacher);
            $adapter->save_post_rating($this->first_post_id($s, $userid), 8);

            // A penalty row from a time the forum qualified.
            penalty_manager::save_penalty((int) $s->cm->id, $userid, (int) $s->teacher->id, 'other', 'Plagiarism', 30);
            $adapter->sync_gradebook_penalty($userid);
            service::resync((int) $s->cm->id, $userid);

            $cell = $this->cell($s, $userid);
            $this->assertEqualsWithDelta(0, $cell->deductedmark, 0.001);
            $this->assertEqualsWithDelta($cell->rawgrade, $cell->finalgrade, 0.001);
            $this->assertNull($this->penalty_pct($s, $userid, 'late'));

            // Nor does the student's page list the leftover row.
            $gradeinfo = feedback_data_helper::format_grade(
                $adapter->get_grade_data($userid),
                $adapter->get_activity_info(),
            );
            $this->assertFalse(feedback_data_helper::penalty_breakdown((int) $s->cm->id, $userid, $gradeinfo)['haspenalties']);
        }
    }

    /**
     * Changing the aggregate type to one that does not qualify takes the deduction away, and back restores it.
     */
    public function test_changing_the_aggregate_type_removes_and_restores_the_deduction(): void {
        global $DB;

        $s = $this->rated_scenario(RATING_AGGREGATE_AVERAGE);
        $userid = (int) $s->students[0]->id;
        $adapter = adapter_factory::create((int) $s->cm->id);
        $this->setUser($s->teacher);
        $adapter->save_post_rating($this->first_post_id($s, $userid), 8);
        $this->assert_cell($s, $userid, 8.0, 2.0, 6.0);

        $this->change_aggregate($s, RATING_AGGREGATE_MAXIMUM);
        $this->assert_cell($s, $userid, 8.0, 0.0, 8.0);
        $this->assertNull($this->penalty_pct($s, $userid, 'late'));

        // Changing the scale to a custom one is the same.
        $this->change_aggregate($s, RATING_AGGREGATE_AVERAGE);
        $this->assert_cell($s, $userid, 8.0, 2.0, 6.0);
        $scale = $this->getDataGenerator()->create_scale(['name' => 'Quality', 'scale' => 'Poor,Fair,Good,Excellent']);
        $DB->set_field('forum', 'scale', -$scale->id, ['id' => $s->activity->id]);
        $this->announce_change($s);
        $this->assertEqualsWithDelta(0, $this->cell($s, $userid)->deductedmark, 0.001);
        $this->assertNull($this->penalty_pct($s, $userid, 'late'));
    }

    /**
     * Late penalties are opt-in on a rated forum: off until a teacher ticks
     * "Apply late penalties", so no running or past course gains them. A forum
     * graded as a whole keeps them on by default.
     */
    public function test_late_penalties_are_opt_in_on_rated_forums(): void {
        $s = $this->rated_scenario(RATING_AGGREGATE_AVERAGE);
        $userid = (int) $s->students[0]->id;
        $cm = get_coursemodule_from_id('forum', (int) $s->cm->id);

        // As if nothing had ever been saved for it.
        $this->setAdminUser();
        \local_unifiedgrader\penalty\activity_settings::set_enabled((int) $s->cm->id, false);
        global $DB;
        $DB->delete_records('local_unifiedgrader_penset', ['cmid' => $s->cm->id]);
        \local_unifiedgrader\penalty\activity_settings::invalidate((int) $s->cm->id);
        $this->assertFalse(\local_unifiedgrader\penalty\activity_settings::is_enabled($cm));
        $this->assertNull(\local_unifiedgrader\adapter\adapter_factory::create((int) $s->cm->id)->calculate_late_penalty($userid));

        // A teacher opts in.
        \local_unifiedgrader\penalty\activity_settings::set_enabled((int) $s->cm->id, true);
        $adapter = \local_unifiedgrader\adapter\adapter_factory::create((int) $s->cm->id);
        $this->assertNotNull($adapter->calculate_late_penalty($userid));

        // Whole-forum grading is on unless switched off.
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $whole = $gen->create_grading_scenario('forum', ['modparams' => ['grade_forum' => 100]]);
        $this->assertTrue(\local_unifiedgrader\penalty\activity_settings::is_enabled(
            get_coursemodule_from_id('forum', (int) $whole->cm->id)
        ));
    }

    // Helpers.

    /**
     * A rated forum, due five days ago, with one student post made now.
     *
     * @param int $aggregate RATING_AGGREGATE_* constant.
     * @param int $scale Points maximum, or minus a scale id.
     * @return \stdClass The scenario, with the teacher as the current user.
     */
    private function rated_scenario(int $aggregate, int $scale = self::MAXRATING): \stdClass {
        // Creating a forum with a due date writes a calendar event.
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $gen->create_grading_scenario('forum', [
            'studentcount' => 1,
            'modparams' => [
                'grade_forum' => 0,
                'assessed' => $aggregate,
                'scale' => $scale,
                'duedate' => time() - 5 * DAYSECS,
            ],
        ]);
        $gen->create_forum_post($s->activity, (int) $s->students[0]->id);
        // Late penalties are opt-in on a rated forum: these tests are about what
        // happens once a teacher has switched them on.
        \local_unifiedgrader\penalty\activity_settings::set_enabled((int) $s->cm->id, true);
        $this->setUser($s->teacher);
        return $s;
    }

    /**
     * The id of the student's first post.
     *
     * @param \stdClass $s The scenario.
     * @param int $userid The student.
     * @return int
     */
    private function first_post_id(\stdClass $s, int $userid): int {
        global $DB;
        return (int) $DB->get_field_sql(
            'SELECT MIN(p.id) FROM {forum_posts} p JOIN {forum_discussions} d ON d.id = p.discussion
              WHERE d.forum = :forum AND p.userid = :userid',
            ['forum' => $s->activity->id, 'userid' => $userid],
        );
    }

    /**
     * Another post by the student, in a discussion of its own.
     *
     * @param \stdClass $s The scenario.
     * @param int $userid The student.
     * @return int The post id.
     */
    private function add_post(\stdClass $s, int $userid): int {
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        return (int) $gen->create_forum_post($s->activity, $userid, ['subject' => 'Second'])->post->id;
    }

    /**
     * The student's cell on the ratings grade item.
     *
     * @param \stdClass $s The scenario.
     * @param int $userid The student.
     * @return \grade_grade
     */
    private function cell(\stdClass $s, int $userid): \grade_grade {
        $item = \grade_item::fetch([
            'itemtype' => 'mod', 'itemmodule' => 'forum', 'iteminstance' => $s->activity->id, 'itemnumber' => 0,
        ]);
        return \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
    }

    /**
     * Assert what the gradebook holds for the student.
     *
     * @param \stdClass $s The scenario.
     * @param int $userid The student.
     * @param float $raw The ratings aggregate.
     * @param float $deduction The deduction recorded beside it.
     * @param float $final The final grade.
     */
    private function assert_cell(\stdClass $s, int $userid, float $raw, float $deduction, float $final): void {
        $cell = $this->cell($s, $userid);
        $this->assertEqualsWithDelta($raw, $cell->rawgrade, 0.001, 'raw grade');
        $this->assertEqualsWithDelta($deduction, $cell->deductedmark, 0.001, 'deduction');
        $this->assertEqualsWithDelta($final, $cell->finalgrade, 0.001, 'final grade');
    }

    /**
     * A penalty row's percentage.
     *
     * @param \stdClass $s The scenario.
     * @param int $userid The student.
     * @param string $category The category.
     * @return int|null
     */
    private function penalty_pct(\stdClass $s, int $userid, string $category): ?int {
        foreach (penalty_manager::get_penalties((int) $s->cm->id, $userid) as $penalty) {
            if ($penalty['category'] === $category) {
                return $penalty['percentage'];
            }
        }
        return null;
    }

    /**
     * Change the forum's aggregate type the way a settings save does.
     *
     * @param \stdClass $s The scenario.
     * @param int $aggregate The new RATING_AGGREGATE_* constant.
     */
    private function change_aggregate(\stdClass $s, int $aggregate): void {
        global $DB;
        $DB->set_field('forum', 'assessed', $aggregate, ['id' => $s->activity->id]);
        $this->announce_change($s);
    }

    /**
     * Tell the plugin the forum's settings were saved, and run what that queues.
     *
     * Core also pushes the new aggregates, which is what a real save does.
     *
     * @param \stdClass $s The scenario.
     */
    private function announce_change(\stdClass $s): void {
        global $DB;
        $forum = $DB->get_record('forum', ['id' => $s->activity->id], '*', MUST_EXIST);
        $forum->cmidnumber = $s->cm->idnumber;
        forum_update_grades($forum);

        \core\event\course_module_updated::create_from_cm(get_coursemodule_from_id('forum', $s->cm->id))->trigger();
        $this->runAdhocTasks(\local_unifiedgrader\task\resync_penalties::class);
    }
}
