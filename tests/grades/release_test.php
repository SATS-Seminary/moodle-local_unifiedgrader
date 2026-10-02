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

namespace local_unifiedgrader\grades;

use local_unifiedgrader\adapter\adapter_factory;
use local_unifiedgrader\external\set_grades_posted;

/**
 * Per-student and per-group grade release.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\grades\release
 * @covers \local_unifiedgrader\external\set_grades_posted
 */
final class release_test extends \advanced_testcase {
    /**
     * The same-request release guard is static, and this suite reuses user ids.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->forget_released_now();
    }

    /**
     * Leave the next test class with an empty guard.
     */
    protected function tearDown(): void {
        $this->forget_released_now();
        parent::tearDown();
    }

    /**
     * Posting one student while the item is hidden reveals the item, that cell,
     * and nobody else.
     */
    public function test_post_one_student_lifts_item_and_holds_the_rest(): void {
        $this->resetAfterTest();
        $s = $this->graded_assignment();
        [$a, $b] = [$s->scenario->students[0], $s->scenario->students[1]];

        $s->adapter->set_grades_posted(1);
        $s->adapter->set_grades_posted(0, [$a->id]);

        $item = $s->adapter->get_grade_item();
        $this->assertSame(0, $this->item_hidden($item->id));
        $this->assertSame(0, $this->cell_hidden($item->id, $a->id));
        $this->assertSame(1, $this->cell_hidden($item->id, $b->id));
        $this->assertTrue($s->adapter->is_grade_released($a->id));
        $this->assertFalse($s->adapter->is_grade_released($b->id));

        $status = $s->adapter->posting_status();
        $this->assertFalse($status['posted']);
        $this->assertTrue($status['partial']);
        $this->assertSame(1, $status['postedcount']);
        $this->assertSame(3, $status['total']);
    }

    /**
     * Posting a group releases its members only. A student in both selected groups has one cell.
     */
    public function test_post_groups_releases_members_once(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->graded_assignment();
        $gen = $this->getDataGenerator();
        $one = $gen->create_group(['courseid' => $s->scenario->course->id, 'name' => 'One']);
        $two = $gen->create_group(['courseid' => $s->scenario->course->id, 'name' => 'Two']);
        [$a, $b, $c] = $s->scenario->students;
        groups_add_member($one->id, $a->id);
        groups_add_member($two->id, $b->id);
        groups_add_member($one->id, $c->id);
        groups_add_member($two->id, $c->id);

        $s->adapter->set_grades_posted(1);
        $result = set_grades_posted::execute($s->scenario->cm->id, 0, 'groups', 0, [$one->id, $two->id]);

        $itemid = $s->adapter->get_grade_item()->id;
        $this->assertSame(0, $this->item_hidden($itemid));
        $this->assertSame(0, $this->cell_hidden($itemid, $a->id));
        $this->assertSame(0, $this->cell_hidden($itemid, $b->id));
        $this->assertSame(0, $this->cell_hidden($itemid, $c->id));
        $this->assertSame(1, $DB->count_records('grade_grades', ['itemid' => $itemid, 'userid' => $c->id]));
        $this->assertTrue($result['posted']);
        $this->assertSame(3, $result['postedcount']);

        // A fresh class, one group: the other group's student stays hidden.
        $s = $this->graded_assignment();
        $one = $gen->create_group(['courseid' => $s->scenario->course->id, 'name' => 'One']);
        $two = $gen->create_group(['courseid' => $s->scenario->course->id, 'name' => 'Two']);
        [$a, $b] = $s->scenario->students;
        groups_add_member($one->id, $a->id);
        groups_add_member($two->id, $b->id);
        $s->adapter->set_grades_posted(1);
        set_grades_posted::execute($s->scenario->cm->id, 0, 'groups', 0, [$one->id]);

        $itemid = $s->adapter->get_grade_item()->id;
        $this->assertSame(0, $this->cell_hidden($itemid, $a->id));
        $this->assertSame(1, $this->cell_hidden($itemid, $b->id));
    }

    /**
     * Posting the class reveals the item and every existing cell.
     */
    public function test_post_class_clears_item_and_cells(): void {
        $this->resetAfterTest();
        $s = $this->graded_assignment();
        $a = $s->scenario->students[0];

        $s->adapter->set_grades_posted(1);
        $s->adapter->set_grades_posted(0, [$a->id]);
        $s->adapter->set_grades_posted(0);

        $itemid = $s->adapter->get_grade_item()->id;
        $this->assertSame(0, $this->item_hidden($itemid));
        foreach ($s->scenario->students as $student) {
            $cell = $this->cell_hidden($itemid, $student->id);
            $this->assertTrue($cell === null || $cell === 0, 'A class post leaves no hidden cell.');
        }
        $this->assertTrue($s->adapter->are_grades_posted());
    }

    /**
     * Hiding one student writes that cell and leaves the item and the other cells.
     */
    public function test_hide_one_student_while_item_visible(): void {
        $this->resetAfterTest();
        $s = $this->graded_assignment();
        [$a, $b] = $s->scenario->students;

        $s->adapter->set_grades_posted(1, [$a->id]);

        $itemid = $s->adapter->get_grade_item()->id;
        $this->assertSame(0, $this->item_hidden($itemid));
        $this->assertSame(1, $this->cell_hidden($itemid, $a->id));
        $this->assertSame(0, $this->cell_hidden($itemid, $b->id));
        $this->assertFalse($s->adapter->is_grade_released($a->id));
        $this->assertTrue($s->adapter->is_grade_released($b->id));
    }

    /**
     * Hiding the class sets the item and leaves the cells as they are.
     */
    public function test_hide_class_leaves_cells(): void {
        $this->resetAfterTest();
        $s = $this->graded_assignment();
        $itemid = $s->adapter->get_grade_item()->id;
        $before = $this->cell_hidden($itemid, $s->scenario->students[0]->id);

        $s->adapter->set_grades_posted(1);

        $this->assertSame(1, $this->item_hidden($itemid));
        $this->assertSame($before, $this->cell_hidden($itemid, $s->scenario->students[0]->id));
        $this->assertFalse($s->adapter->are_grades_posted());
    }

    /**
     * Scheduling one student stamps that cell and leaves the item visible.
     */
    public function test_schedule_one_student(): void {
        $this->resetAfterTest();
        $s = $this->graded_assignment();
        [$a, $b] = $s->scenario->students;
        $item = $s->adapter->get_grade_item();
        $held = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $b->id]);
        $held->set_hidden(1);

        $when = time() + DAYSECS;
        $s->adapter->set_grades_posted($when, [$a->id]);

        $this->assertSame(0, $this->item_hidden($item->id));
        $this->assertSame($when, $this->cell_hidden($item->id, $a->id));
        $this->assertSame(1, $this->cell_hidden($item->id, $b->id));
        $this->assertFalse($s->adapter->is_grade_released($a->id));
    }

    /**
     * A grade saved after a partial post is hidden. A grade saved for an already
     * released student is not hidden again. A grade saved when the class is fully
     * posted stays visible.
     */
    public function test_new_grade_is_sealed_until_the_class_is_posted(): void {
        $this->resetAfterTest();
        $s = $this->graded_assignment();
        [$a, $b, $c] = $s->scenario->students;
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');

        $s->adapter->set_grades_posted(1);
        $s->adapter->set_grades_posted(0, [$a->id]);

        $this->setUser($c);
        $plugingen->create_assign_submission($s->scenario->activity, $c->id);
        $this->setUser($s->scenario->teacher);
        $s->adapter->save_grade($c->id, 70.0, '<p>Later.</p>');

        $itemid = $s->adapter->get_grade_item()->id;
        $this->assertSame(1, $this->cell_hidden($itemid, $c->id));
        $this->assertFalse($s->adapter->is_grade_released($c->id));

        // A later request: the "just released" guard has gone. Regrading the
        // student who was posted must still not hide them again.
        $this->forget_released_now();
        $s->adapter->save_grade($a->id, 91.0, '<p>Revised.</p>');
        $this->assertSame(0, $this->cell_hidden($itemid, $a->id));
        $this->assertTrue($s->adapter->is_grade_released($a->id));
        $this->assertSame(1, $this->cell_hidden($itemid, $b->id));

        // Fully posted: a new grade on a fresh activity stays visible.
        $open = $this->graded_assignment(2);
        $open->adapter->set_grades_posted(0);
        $third = $open->scenario->students[1];
        // The second student was graded before the post. Grade them again after it.
        $open->adapter->save_grade($third->id, 40.0, '<p>After.</p>');
        $openitem = $open->adapter->get_grade_item()->id;
        $this->assertSame(0, $this->cell_hidden($openitem, $third->id));
    }

    /**
     * A student graded only after the class is fully posted is not sealed.
     */
    public function test_grade_after_full_post_stays_visible(): void {
        $this->resetAfterTest();
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('assign', ['studentcount' => 2]);
        $this->setUser($scenario->students[0]);
        $plugingen->create_assign_submission($scenario->activity, $scenario->students[0]->id);
        $this->setUser($scenario->teacher);
        $adapter = adapter_factory::create($scenario->cm->id);
        $adapter->save_grade($scenario->students[0]->id, 80.0, '<p>First.</p>');
        $adapter->set_grades_posted(0);

        $this->setUser($scenario->students[1]);
        $plugingen->create_assign_submission($scenario->activity, $scenario->students[1]->id);
        $this->setUser($scenario->teacher);
        $this->forget_released_now();
        $adapter->save_grade($scenario->students[1]->id, 60.0, '<p>Second.</p>');

        $this->assertSame(0, $this->cell_hidden($adapter->get_grade_item()->id, $scenario->students[1]->id));
        $this->assertTrue($adapter->is_grade_released($scenario->students[1]->id));
    }

    /**
     * Marking workflow follows the same students, and the mailed flag follows a lift.
     */
    public function test_workflow_and_mailed_follow_partial_post(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $this->resetAfterTest();
        $s = $this->graded_assignment(2, ['markingworkflow' => 1]);
        [$a, $b] = $s->scenario->students;

        $this->assertFalse($s->adapter->are_grades_posted());

        $s->adapter->set_grades_posted(1);
        $s->adapter->set_grades_posted(0, [$a->id]);

        $this->assertSame(
            ASSIGN_MARKING_WORKFLOW_STATE_RELEASED,
            $DB->get_field('assign_user_flags', 'workflowstate', [
                'assignment' => $s->scenario->activity->id,
                'userid' => $a->id,
            ]),
        );
        $this->assertNotSame(
            ASSIGN_MARKING_WORKFLOW_STATE_RELEASED,
            $DB->get_field('assign_user_flags', 'workflowstate', [
                'assignment' => $s->scenario->activity->id,
                'userid' => $b->id,
            ]),
        );
        $this->assertSame(0, (int) $DB->get_field('assign_user_flags', 'mailed', [
            'assignment' => $s->scenario->activity->id,
            'userid' => $a->id,
        ]));
        $this->assertSame(1, (int) $DB->get_field('assign_user_flags', 'mailed', [
            'assignment' => $s->scenario->activity->id,
            'userid' => $b->id,
        ]));
        $this->assertTrue($s->adapter->is_grade_released($a->id));
        $this->assertFalse($s->adapter->is_grade_released($b->id));
        $this->assertFalse($s->adapter->are_grades_posted());

        // Releasing the second student, with the item already visible, clears mailed.
        // The third student was never graded. Revealing the item held their cell,
        // so the class stays partly posted until that student is posted too.
        $s->adapter->set_grades_posted(0, [$b->id]);
        $this->assertSame(0, (int) $DB->get_field('assign_user_flags', 'mailed', [
            'assignment' => $s->scenario->activity->id,
            'userid' => $b->id,
        ]));
        $status = $s->adapter->posting_status();
        $this->assertFalse($status['posted']);
        $this->assertTrue($status['partial']);
        $this->assertSame(2, $status['postedcount']);
        $this->assertSame(3, $status['total']);
    }

    /**
     * A partial post sets workflow for a student who has no grade yet, and the
     * grade saved afterwards stays released.
     */
    public function test_partial_post_releases_ungraded_workflow(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $this->resetAfterTest();
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('assign', [
            'studentcount' => 2,
            'modparams' => ['markingworkflow' => 1],
        ]);
        $this->setUser($scenario->students[0]);
        $plugingen->create_assign_submission($scenario->activity, $scenario->students[0]->id);
        $this->setUser($scenario->teacher);
        $adapter = adapter_factory::create($scenario->cm->id);
        $adapter->save_grade($scenario->students[0]->id, 80.0, '<p>Graded.</p>');
        $adapter->set_grades_posted(1);

        $waiting = $scenario->students[1];
        $adapter->set_grades_posted(0, [$waiting->id]);
        $this->assertSame(
            ASSIGN_MARKING_WORKFLOW_STATE_RELEASED,
            $DB->get_field('assign_user_flags', 'workflowstate', [
                'assignment' => $scenario->activity->id,
                'userid' => $waiting->id,
            ]),
        );

        $this->setUser($waiting);
        $plugingen->create_assign_submission($scenario->activity, $waiting->id);
        $this->setUser($scenario->teacher);
        $this->forget_released_now();
        $adapter->save_grade($waiting->id, 55.0, '<p>Now graded.</p>');
        $this->assertSame(0, $this->cell_hidden($adapter->get_grade_item()->id, $waiting->id));
        $this->assertTrue($adapter->is_grade_released($waiting->id));
    }

    /**
     * A class post does not mark the other students as mailed.
     */
    public function test_class_post_does_not_mark_mailed(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->graded_assignment(1, ['markingworkflow' => 1]);
        $student = $s->scenario->students[0];
        $params = [
            'assignment' => $s->scenario->activity->id,
            'userid' => $student->id,
        ];
        $this->assertNotFalse($DB->get_field('assign_user_flags', 'id', $params));
        $DB->set_field('assign_user_flags', 'mailed', 0, $params);

        $s->adapter->set_grades_posted(0);

        $this->assertSame(0, (int) $DB->get_field('assign_user_flags', 'mailed', $params));
    }

    /**
     * Forum and BigBlueButton feedback follow the cell, not only the item.
     */
    public function test_forum_and_bbb_follow_the_cell(): void {
        $this->resetAfterTest();
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');

        $forum = $plugingen->create_grading_scenario('forum');
        $this->setUser($forum->teacher);
        $forumadapter = adapter_factory::create($forum->cm->id);
        $forumadapter->save_grade($forum->students[0]->id, 80.0, '<p>Forum.</p>');
        $this->assertTrue($forumadapter->is_grade_released($forum->students[0]->id));
        $forumadapter->set_grades_posted(1, [$forum->students[0]->id]);
        $this->assertFalse($forumadapter->is_grade_released($forum->students[0]->id));
        $this->assertSame(0, $this->item_hidden($forumadapter->get_grade_item()->id));

        if (!class_exists('\mod_bigbluebuttonbn\instance')) {
            return;
        }
        $bbb = $plugingen->create_grading_scenario('bigbluebuttonbn');
        $this->setUser($bbb->teacher);
        $bbbadapter = adapter_factory::create($bbb->cm->id);
        $bbbadapter->save_grade($bbb->students[0]->id, 80.0, '<p>Session.</p>');
        $this->assertTrue($bbbadapter->is_grade_released($bbb->students[0]->id));
        $bbbadapter->set_grades_posted(1, [$bbb->students[0]->id]);
        $this->assertFalse($bbbadapter->is_grade_released($bbb->students[0]->id));
    }

    /**
     * A quiz student or group post does not touch review options, and a hidden
     * cell withholds the grade even when the class review options show marks.
     */
    public function test_quiz_partial_post_leaves_review_options(): void {
        global $DB;
        $this->resetAfterTest();
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('quiz', ['studentcount' => 2]);
        $this->setUser($scenario->teacher);
        $adapter = adapter_factory::create($scenario->cm->id);
        $fields = 'reviewmarks, reviewmaxmarks, reviewoverallfeedback';
        $before = $DB->get_record('quiz', ['id' => $scenario->activity->id], $fields, MUST_EXIST);

        $adapter->set_grades_posted(0, [$scenario->students[0]->id]);
        $after = $DB->get_record('quiz', ['id' => $scenario->activity->id], $fields, MUST_EXIST);
        $this->assertEquals($before, $after);

        // Class post turns the review bits on. A hidden cell then withholds one student.
        $adapter->set_grades_posted(0);
        $quiz = $DB->get_record('quiz', ['id' => $scenario->activity->id], '*', MUST_EXIST);
        $this->assertNotEquals(0, (int) $quiz->reviewmarks & 0x00100);
        foreach ($scenario->students as $student) {
            $DB->insert_record('quiz_grades', (object) [
                'quiz' => $scenario->activity->id,
                'userid' => $student->id,
                'grade' => 80,
                'timemodified' => time(),
            ]);
        }
        $adapter->set_grades_posted(1, [$scenario->students[0]->id]);
        $bits = $DB->get_record('quiz', ['id' => $scenario->activity->id], $fields, MUST_EXIST);
        $this->assertEquals(
            (object) [
                'reviewmarks' => $quiz->reviewmarks,
                'reviewmaxmarks' => $quiz->reviewmaxmarks,
                'reviewoverallfeedback' => $quiz->reviewoverallfeedback,
            ],
            $bits,
        );
        $this->assertFalse($adapter->is_grade_released($scenario->students[0]->id));

        // No gradebook cell, while someone else is hidden, is not released.
        $this->assertFalse($adapter->is_grade_released($scenario->students[1]->id));

        // A visible cell is released. Give the second student one.
        $item = $adapter->get_grade_item();
        $grade = new \grade_grade(['itemid' => $item->id, 'userid' => $scenario->students[1]->id]);
        $grade->finalgrade = 80;
        $grade->rawgrade = 80;
        $grade->rawgrademin = $item->grademin;
        $grade->rawgrademax = $item->grademax;
        $grade->hidden = 0;
        $grade->insert('phpunit');
        $this->assertTrue($adapter->is_grade_released($scenario->students[1]->id));

        // Class hidden: a cell of 0 still does not release.
        $adapter->set_grades_posted(1);
        $adapter->set_grades_posted(0, [$scenario->students[1]->id]);
        $this->assertNotSame(0, $this->item_hidden($item->id));
        $this->assertFalse($adapter->is_grade_released($scenario->students[1]->id));
    }

    /**
     * A separate-groups teacher can post their own student, and not the class or another group.
     */
    public function test_separate_groups_teacher_is_confined(): void {
        global $DB;
        $this->resetAfterTest();
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $plugingen->create_grading_scenario('assign', [
            'studentcount' => 2,
            'modparams' => ['groupmode' => SEPARATEGROUPS],
        ]);
        $gen = $this->getDataGenerator();
        $red = $gen->create_group(['courseid' => $s->course->id]);
        $blue = $gen->create_group(['courseid' => $s->course->id]);
        groups_add_member($red->id, $s->students[0]->id);
        groups_add_member($blue->id, $s->students[1]->id);

        $roleid = $DB->get_field('role', 'id', ['shortname' => 'teacher'], MUST_EXIST);
        assign_capability('local/unifiedgrader:grade', CAP_ALLOW, $roleid, $s->context->id, true);
        $marker = $gen->create_user();
        $gen->enrol_user($marker->id, $s->course->id, 'teacher');
        groups_add_member($red->id, $marker->id);

        $this->setUser($s->teacher);
        $adapter = adapter_factory::create($s->cm->id);
        $adapter->set_grades_posted(1);

        $this->setUser($marker);
        try {
            set_grades_posted::execute($s->cm->id, 0, 'class');
            $this->fail('A separate-groups teacher must not post the class.');
        } catch (\moodle_exception $e) {
            $this->assertSame('post_grades_class_denied', $e->errorcode);
        }
        try {
            set_grades_posted::execute($s->cm->id, 0, 'user', $s->students[1]->id);
            $this->fail('A separate-groups teacher must not post another group.');
        } catch (\moodle_exception $e) {
            $this->assertSame('nopermission', $e->errorcode);
        }
        try {
            set_grades_posted::execute($s->cm->id, 0, 'groups', 0, [$blue->id]);
            $this->fail('A separate-groups teacher must not post another group by id.');
        } catch (\moodle_exception $e) {
            $this->assertSame('nopermission', $e->errorcode);
        }

        $result = set_grades_posted::execute($s->cm->id, 0, 'user', $s->students[0]->id);
        $this->assertTrue($result['success']);
        // This teacher can see only their own student, and that grade is posted.
        $this->assertTrue($result['posted']);
        $this->assertFalse($result['partial']);
        $itemid = $adapter->get_grade_item()->id;
        $this->assertSame(0, $this->item_hidden($itemid));
        $this->assertSame(0, $this->cell_hidden($itemid, $s->students[0]->id));
        $this->assertSame(1, $this->cell_hidden($itemid, $s->students[1]->id));

        $this->setUser($s->teacher);
        $wide = adapter_factory::create($s->cm->id);
        $status = $wide->posting_status();
        $this->assertFalse($status['posted']);
        $this->assertTrue($status['partial']);

        // The adapter itself still accepts a class post. The refusal lives in the web service.
        $adapter->set_grades_posted(0);
        $this->assertSame(0, $this->item_hidden($itemid));
    }

    /**
     * A group with no students changes nothing. An unknown scope is rejected.
     */
    public function test_empty_group_and_invalid_scope(): void {
        $this->resetAfterTest();
        $s = $this->graded_assignment(1);
        $s->adapter->set_grades_posted(1);
        $empty = $this->getDataGenerator()->create_group(['courseid' => $s->scenario->course->id]);

        $result = set_grades_posted::execute($s->scenario->cm->id, 0, 'groups', 0, [$empty->id]);
        $this->assertTrue($result['success']);
        $this->assertFalse($result['posted']);
        $this->assertSame(1, $this->item_hidden($s->adapter->get_grade_item()->id));

        $this->expectException(\moodle_exception::class);
        set_grades_posted::execute($s->scenario->cm->id, 0, 'cohort');
    }

    /**
     * The participant list carries each student's effective hidden value.
     */
    public function test_participants_include_gradehidden(): void {
        $this->resetAfterTest();
        $s = $this->graded_assignment();
        $s->adapter->set_grades_posted(1);
        $s->adapter->set_grades_posted(0, [$s->scenario->students[0]->id]);

        $rows = [];
        foreach ($s->adapter->get_participants([]) as $row) {
            $rows[$row['id']] = $row['gradehidden'];
        }
        $this->assertSame(0, $rows[$s->scenario->students[0]->id]);
        $this->assertSame(1, $rows[$s->scenario->students[1]->id]);
        // The third student was not graded. Revealing the item still held their cell.
        $this->assertSame(1, $rows[$s->scenario->students[2]->id]);
    }

    /**
     * An assignment with graded students.
     *
     * @param int $count How many of the three students to grade.
     * @param array $modparams Assignment settings.
     * @return object{adapter: \local_unifiedgrader\adapter\assign_adapter, scenario: \stdClass}
     */
    private function graded_assignment(int $count = 2, array $modparams = []): object {
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('assign', [
            'studentcount' => 3,
            'modparams' => $modparams,
        ]);
        $adapter = adapter_factory::create($scenario->cm->id);
        for ($i = 0; $i < $count; $i++) {
            $student = $scenario->students[$i];
            $this->setUser($student);
            $plugingen->create_assign_submission($scenario->activity, $student->id);
            $this->setUser($scenario->teacher);
            $adapter->save_grade($student->id, 80.0, '<p>Marked.</p>');
        }
        $this->setUser($scenario->teacher);
        return (object) ['adapter' => $adapter, 'scenario' => $scenario];
    }

    /**
     * Drop the in-request "just released" guard, as a new page request would.
     */
    private function forget_released_now(): void {
        $property = new \ReflectionProperty(release::class, 'releasednow');
        $property->setValue(null, []);
    }

    /**
     * The grade item's hidden value, read from the database.
     *
     * @param int $itemid
     * @return int
     */
    private function item_hidden(int $itemid): int {
        global $DB;
        return (int) $DB->get_field('grade_items', 'hidden', ['id' => $itemid]);
    }

    /**
     * A cell's hidden value, or null when the student has no row.
     *
     * @param int $itemid
     * @param int $userid
     * @return int|null
     */
    private function cell_hidden(int $itemid, int $userid): ?int {
        global $DB;
        $hidden = $DB->get_field('grade_grades', 'hidden', ['itemid' => $itemid, 'userid' => $userid]);
        return $hidden === false ? null : (int) $hidden;
    }
}
