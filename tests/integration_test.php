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

/**
 * Comprehensive integration tests for the Unified Grader plugin.
 *
 * Tests the full grading lifecycle across assignment, forum, and quiz activity
 * types — including comments, penalties, student view, and feedback download.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_unifiedgrader\adapter\assign_adapter
 * @covers     \local_unifiedgrader\adapter\forum_adapter
 * @covers     \local_unifiedgrader\adapter\quiz_adapter
 */
class integration_test extends \advanced_testcase {
    /** @var \local_unifiedgrader_generator */
    private $gen;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
    }

    // Assignment tests.

    /**
     * Test full assignment grading lifecycle: submit → grade → verify student view.
     */
    public function test_assign_full_lifecycle(): void {
        global $PAGE;

        $scenario = $this->gen->create_grading_scenario('assign', [
            'studentcount' => 2,
            'enable' => true,
            'modparams' => [
                'assignfeedback_comments_enabled' => 1,
                'duedate' => time() + 86400,
            ],
        ]);

        $adapter = adapter_factory::create($scenario->cm->id);
        $student1 = $scenario->students[0];
        $student2 = $scenario->students[1];

        // Verify activity info.
        $info = $adapter->get_activity_info();
        $this->assertEquals('assign', $info['type']);
        $this->assertGreaterThan(0, $info['maxgrade']);
        $this->assertFalse((bool) $info['gradingdisabled']);

        // Get participants — both should appear.
        $participants = $adapter->get_participants(['status' => 'all']);
        $this->assertCount(2, $participants);

        // Submit for student 1.
        $this->gen->create_assign_submission(
            $scenario->activity, $student1->id, 'My assignment submission text.'
        );

        // Verify submission data.
        $this->setUser($scenario->teacher);
        $sub = $adapter->get_submission_data($student1->id);
        $this->assertEquals('submitted', $sub['status']);
        $this->assertTrue($sub['hascontent']);

        // Student 2 has no submission.
        $sub2 = $adapter->get_submission_data($student2->id);
        $this->assertNotEquals('submitted', $sub2['status']);

        // Grade student 1.
        $result = $adapter->save_grade(
            $student1->id,
            85,
            '<p>Great work on this assignment!</p>',
            FORMAT_HTML,
            [],
            0,
            0,
            -1,
        );
        $this->assertTrue($result);

        // Verify grade data.
        $gradedata = $adapter->get_grade_data($student1->id);
        $this->assertEquals(85, $gradedata['grade']);
        $this->assertStringContainsString('Great work', $gradedata['feedback']);

        // Verify grade is released for student.
        $this->assertTrue($adapter->is_grade_released($student1->id));

        // Student 2 has no grade released.
        $this->assertFalse($adapter->is_grade_released($student2->id));

        // Verify feedback data helper works.
        $gradeinfo = feedback_data_helper::format_grade($gradedata, $info);
        $this->assertEquals(85, $gradeinfo['gradevalue']);
        $this->assertGreaterThan(0, $gradeinfo['percentage']);
        $this->assertNotEmpty($gradeinfo['gradedisplay']);
    }

    /**
     * Test assignment penalties (wordcount and custom).
     */
    public function test_assign_penalties(): void {
        $scenario = $this->gen->create_grading_scenario('assign', [
            'studentcount' => 1,
            'enable' => true,
            'modparams' => [
                'assignfeedback_comments_enabled' => 1,
            ],
        ]);

        $student = $scenario->students[0];

        // Create penalties.
        $this->gen->create_penalty([
            'cmid' => $scenario->cm->id,
            'userid' => $student->id,
            'authorid' => $scenario->teacher->id,
            'category' => 'wordcount',
            'label' => 'Over limit',
            'percentage' => 10,
        ]);
        $this->gen->create_penalty([
            'cmid' => $scenario->cm->id,
            'userid' => $student->id,
            'authorid' => $scenario->teacher->id,
            'category' => 'other',
            'label' => 'Formatting',
            'percentage' => 5,
        ]);

        // Verify penalties via manager.
        $penalties = penalty_manager::get_penalties($scenario->cm->id, $student->id);
        $this->assertCount(2, $penalties);

        // Verify total percentage.
        $total = penalty_manager::get_total_percentage($scenario->cm->id, $student->id);
        $this->assertEquals(15, $total);

        // Verify deduction calculation.
        $deduction = penalty_manager::get_total_deduction($scenario->cm->id, $student->id, 100);
        $this->assertEquals(15, $deduction);

        // Format penalties for display.
        $penaltyinfo = feedback_data_helper::format_penalties($scenario->cm->id, $student->id);
        $this->assertTrue($penaltyinfo['haspenalties']);
        $this->assertCount(2, $penaltyinfo['penalties']);
    }

    /**
     * Test assignment with no submission shows correct status.
     */
    public function test_assign_no_submission_status(): void {
        $scenario = $this->gen->create_grading_scenario('assign', [
            'studentcount' => 1,
            'enable' => true,
        ]);

        $student = $scenario->students[0];
        $adapter = adapter_factory::create($scenario->cm->id);

        $this->setUser($scenario->teacher);
        $participants = $adapter->get_participants(['status' => 'all']);
        $participant = $participants[0];

        // Participant should have status 'nosubmission' (assign adapter resolves 'new' → 'nosubmission').
        $this->assertEquals('nosubmission', $participant['status']);
    }

    /**
     * Test assignment notes CRUD.
     */
    public function test_assign_notes(): void {
        $scenario = $this->gen->create_grading_scenario('assign', [
            'studentcount' => 1,
            'enable' => true,
        ]);

        $student = $scenario->students[0];

        // Create a note.
        $note = $this->gen->create_note([
            'cmid' => $scenario->cm->id,
            'userid' => $student->id,
            'authorid' => $scenario->teacher->id,
            'content' => 'Private teacher note about this student.',
        ]);
        $this->assertGreaterThan(0, $note->id);

        // Retrieve notes.
        $notes = notes_manager::get_notes($scenario->cm->id, $student->id);
        $this->assertCount(1, $notes);
        $this->assertStringContainsString('Private teacher note', $notes[0]['content']);

        // Delete note.
        notes_manager::delete_note($notes[0]['id']);
        $notes = notes_manager::get_notes($scenario->cm->id, $student->id);
        $this->assertCount(0, $notes);
    }

    // Forum tests.

    /**
     * Test forum grading lifecycle with grading enabled.
     */
    public function test_forum_graded_lifecycle(): void {
        $scenario = $this->gen->create_grading_scenario('forum', [
            'studentcount' => 1,
            'enable' => true,
            'modparams' => [
                'grade_forum' => 100,
            ],
        ]);

        $adapter = adapter_factory::create($scenario->cm->id);
        $student = $scenario->students[0];

        // Create a forum post.
        $this->gen->create_forum_post($scenario->activity, $student->id, [
            'subject' => 'My Discussion',
            'message' => '<p>This is my forum post content.</p>',
        ]);

        // Get submission data — should show posts.
        $this->setUser($scenario->teacher);
        $sub = $adapter->get_submission_data($student->id);
        $this->assertNotEmpty($sub['content']);

        // Grade the student.
        $result = $adapter->save_grade(
            $student->id,
            90,
            '<p>Excellent discussion contribution!</p>',
            FORMAT_HTML,
            [],
            0,
            0,
            -1,
        );
        $this->assertTrue($result);

        // Verify grade data.
        $gradedata = $adapter->get_grade_data($student->id);
        $this->assertEquals(90, $gradedata['grade']);
        $this->assertStringContainsString('Excellent', $gradedata['feedback']);

        // Grade should be released.
        $this->assertTrue($adapter->is_grade_released($student->id));
    }

    /**
     * Test forum with grading disabled (grade type "None") — feedback only.
     */
    public function test_forum_ungraded_feedback_only(): void {
        $scenario = $this->gen->create_grading_scenario('forum', [
            'studentcount' => 1,
            'enable' => true,
            'modparams' => [
                'grade_forum' => 0, // No grading.
            ],
        ]);

        $adapter = adapter_factory::create($scenario->cm->id);
        $student = $scenario->students[0];

        // Verify grading is disabled.
        $info = $adapter->get_activity_info();
        $this->assertTrue((bool) $info['gradingdisabled']);

        // Save feedback without a grade (grade = -1).
        $this->setUser($scenario->teacher);
        $result = $adapter->save_grade(
            $student->id,
            -1,
            '<p>Good posts, but no grade is given for this forum.</p>',
            FORMAT_HTML,
            [],
            0,
            0,
            -1,
        );
        $this->assertTrue($result);

        // Grade should be "released" based on feedback existence.
        $this->assertTrue($adapter->is_grade_released($student->id));

        // Grade data should have the feedback.
        $gradedata = $adapter->get_grade_data($student->id);
        $this->assertStringContainsString('Good posts', $gradedata['feedback']);

        // Grade was saved as -1 (no grade), so it may be stored as -1.0.
        $this->assertTrue($gradedata['grade'] === null || $gradedata['grade'] < 0,
            'Grade should be null or negative for ungraded forum');

        // Format grade should handle negative/null grades gracefully.
        $gradeinfo = feedback_data_helper::format_grade($gradedata, $info);
        // For ungraded forums (grade disabled), display should be empty or indicate no grade.
        $this->assertTrue(
            empty($gradeinfo['gradedisplay']) || $gradeinfo['gradevalue'] === null || $gradeinfo['gradevalue'] < 0,
            'Grade display should reflect no grade for ungraded forum'
        );
    }

    /**
     * Test forum with no feedback is NOT released.
     */
    public function test_forum_ungraded_no_feedback_not_released(): void {
        $scenario = $this->gen->create_grading_scenario('forum', [
            'studentcount' => 1,
            'enable' => true,
            'modparams' => [
                'grade_forum' => 0,
            ],
        ]);

        $adapter = adapter_factory::create($scenario->cm->id);
        $student = $scenario->students[0];

        // No grade, no feedback — should not be released.
        $this->assertFalse($adapter->is_grade_released($student->id));
    }

    /**
     * Test forum penalties.
     */
    public function test_forum_penalties(): void {
        $scenario = $this->gen->create_grading_scenario('forum', [
            'studentcount' => 1,
            'enable' => true,
            'modparams' => [
                'grade_forum' => 100,
            ],
        ]);

        $student = $scenario->students[0];

        // Create a late penalty.
        $this->gen->create_penalty([
            'cmid' => $scenario->cm->id,
            'userid' => $student->id,
            'authorid' => $scenario->teacher->id,
            'category' => 'late',
            'percentage' => 20,
        ]);

        $penalties = penalty_manager::get_penalties($scenario->cm->id, $student->id);
        $this->assertCount(1, $penalties);
        $this->assertEquals('late', $penalties[0]['category']);
        $this->assertEquals(20, $penalties[0]['percentage']);
    }

    // Quiz tests.

    /**
     * Test quiz adapter basic functionality.
     */
    public function test_quiz_basic_lifecycle(): void {
        $scenario = $this->gen->create_grading_scenario('quiz', [
            'studentcount' => 1,
            'enable' => true,
        ]);

        $adapter = adapter_factory::create($scenario->cm->id);

        // Verify activity info.
        $info = $adapter->get_activity_info();
        $this->assertEquals('quiz', $info['type']);

        // Get participants.
        $participants = $adapter->get_participants(['status' => 'all']);
        $this->assertCount(1, $participants);
    }

    // Comment library tests.

    /**
     * Test comment library CRUD operations.
     */
    public function test_comment_library_crud(): void {
        $scenario = $this->gen->create_grading_scenario('assign', [
            'studentcount' => 1,
            'enable' => true,
        ]);

        $teacher = $scenario->teacher;

        // Create a library comment.
        $comment = $this->gen->create_library_comment([
            'userid' => $teacher->id,
            'coursecode' => 'TEST101',
            'content' => 'Well argued thesis statement.',
            'shared' => 0,
        ]);
        $this->assertGreaterThan(0, $comment->id);

        // Create a tag.
        $tag = $this->gen->create_library_tag([
            'userid' => $teacher->id,
            'name' => 'Writing Quality',
        ]);
        $this->assertGreaterThan(0, $tag->id);

        // Map tag to comment.
        $mapping = $this->gen->create_tag_mapping([
            'commentid' => $comment->id,
            'tagid' => $tag->id,
        ]);
        $this->assertGreaterThan(0, $mapping->id);

        // Verify via comment library manager.
        $comments = comment_library_manager::get_comments($teacher->id);
        $this->assertGreaterThanOrEqual(1, count($comments));
    }

    // Feedback download tests.

    /**
     * Test feedback data helper grade formatting.
     */
    public function test_feedback_data_helper_format_grade(): void {
        // Grade with a value.
        $result = feedback_data_helper::format_grade(
            ['grade' => 75.5],
            ['maxgrade' => 100]
        );
        $this->assertEquals(75.5, $result['gradevalue']);
        $this->assertEquals(100, $result['maxgrade']);
        $this->assertEquals(76, $result['percentage']); // 75.5/100 = 75.5 → round = 76.
        $this->assertNotEmpty($result['gradedisplay']);

        // Null grade.
        $result = feedback_data_helper::format_grade(
            ['grade' => null],
            ['maxgrade' => 100]
        );
        $this->assertNull($result['gradevalue']);
        $this->assertEmpty($result['gradedisplay']);
    }

    /**
     * Test feedback data helper rubric parsing.
     */
    public function test_feedback_data_helper_rubric_parsing(): void {
        $definition = json_encode([
            'method' => 'rubric',
            'criteria' => [
                [
                    'id' => 1,
                    'description' => 'Content Quality',
                    'levels' => [
                        ['id' => 10, 'score' => 0, 'definition' => 'Poor'],
                        ['id' => 11, 'score' => 5, 'definition' => 'Good'],
                        ['id' => 12, 'score' => 10, 'definition' => 'Excellent'],
                    ],
                ],
            ],
        ]);

        $rubricdata = json_encode([
            'criteria' => [
                1 => ['levelid' => 11, 'remark' => 'Solid work'],
            ],
        ]);

        $result = feedback_data_helper::parse_grading_data([
            'gradingdefinition' => $definition,
            'rubricdata' => $rubricdata,
        ]);

        $this->assertTrue($result['hasrubric']);
        $this->assertFalse($result['hasguide']);
        $this->assertTrue($result['hasadvancedgrading']);
        $this->assertEquals(5, $result['rubrictotal']);
        $this->assertCount(1, $result['rubriccriteria']);
        $this->assertTrue($result['rubriccriteria'][0]['hasremark']);
    }

    /**
     * Test feedback data helper marking guide parsing.
     */
    public function test_feedback_data_helper_guide_parsing(): void {
        $definition = json_encode([
            'method' => 'guide',
            'criteria' => [
                [
                    'id' => 1,
                    'shortname' => 'Research',
                    'description' => 'Quality of research',
                    'maxscore' => 25,
                ],
                [
                    'id' => 2,
                    'shortname' => 'Writing',
                    'description' => 'Quality of writing',
                    'maxscore' => 25,
                ],
            ],
        ]);

        $rubricdata = json_encode([
            'criteria' => [
                1 => ['score' => 20, 'remark' => 'Good research'],
                2 => ['score' => 18, 'remark' => ''],
            ],
        ]);

        $result = feedback_data_helper::parse_grading_data([
            'gradingdefinition' => $definition,
            'rubricdata' => $rubricdata,
        ]);

        $this->assertFalse($result['hasrubric']);
        $this->assertTrue($result['hasguide']);
        $this->assertEquals(38, $result['guidetotal']);
        $this->assertEquals(50, $result['guidemaxtotal']);
        $this->assertCount(2, $result['guidecriteria']);
        $this->assertTrue($result['guidecriteria'][0]['hasremark']);
        $this->assertFalse($result['guidecriteria'][1]['hasremark']);
    }

    /**
     * Test PDF summary generation does not throw.
     */
    public function test_feedback_pdf_generation(): void {
        $pdf = new \local_unifiedgrader\pdf\feedback_summary_pdf();
        $bytes = $pdf->generate([
            'activityname' => 'Test Assignment',
            'coursename' => 'Test Course',
            'studentname' => 'Test Student',
            'gradevalue' => 85,
            'maxgrade' => 100,
            'percentage' => 85,
            'feedback' => '<p>Well done!</p>',
            'gradingmethod' => 'simple',
            'rubriccriteria' => [],
            'guidecriteria' => [],
            'penalties' => [['text' => '-10% Late']],
            'dategraded' => 'March 4, 2026',
            'plagiarismlinks' => [],
            'additionalcontent' => '',
            'additionalcontenttitle' => '',
        ]);

        // PDF should have been generated.
        $this->assertNotEmpty($bytes);
        // Verify it starts with PDF magic bytes.
        $this->assertStringStartsWith('%PDF', $bytes);
    }

    /**
     * Test PDF with forum posts as additional content.
     */
    public function test_feedback_pdf_with_forum_posts(): void {
        $pdf = new \local_unifiedgrader\pdf\feedback_summary_pdf();
        $bytes = $pdf->generate([
            'activityname' => 'Discussion Forum',
            'coursename' => 'Test Course',
            'studentname' => 'Test Student',
            'gradevalue' => null,
            'maxgrade' => 0,
            'percentage' => null,
            'feedback' => '<p>Good participation.</p>',
            'gradingmethod' => 'simple',
            'rubriccriteria' => [],
            'guidecriteria' => [],
            'penalties' => [],
            'dategraded' => 'March 4, 2026',
            'plagiarismlinks' => [],
            'additionalcontent' => '<div class="card"><div class="card-body">My forum post content</div></div>',
            'additionalcontenttitle' => 'Your forum posts',
        ]);

        $this->assertNotEmpty($bytes);
        $this->assertStringStartsWith('%PDF', $bytes);
    }

    // Cross-cutting concerns.

    /**
     * Test adapter factory creates correct adapter types.
     */
    public function test_adapter_factory(): void {
        $assign = $this->gen->create_grading_scenario('assign', ['enable' => true]);
        $forum = $this->gen->create_grading_scenario('forum', [
            'enable' => true,
            'modparams' => ['grade_forum' => 100],
        ]);
        $quiz = $this->gen->create_grading_scenario('quiz', ['enable' => true]);

        $this->assertInstanceOf(
            \local_unifiedgrader\adapter\assign_adapter::class,
            adapter_factory::create($assign->cm->id)
        );
        $this->assertInstanceOf(
            \local_unifiedgrader\adapter\forum_adapter::class,
            adapter_factory::create($forum->cm->id)
        );
        $this->assertInstanceOf(
            \local_unifiedgrader\adapter\quiz_adapter::class,
            adapter_factory::create($quiz->cm->id)
        );
    }

    /**
     * Test penalty manager across activity types.
     */
    public function test_penalties_across_types(): void {
        $types = ['assign', 'forum'];

        foreach ($types as $type) {
            $modparams = $type === 'forum' ? ['grade_forum' => 100] : [];
            $scenario = $this->gen->create_grading_scenario($type, [
                'studentcount' => 1,
                'enable' => true,
                'modparams' => $modparams,
            ]);

            $student = $scenario->students[0];

            // No penalties initially.
            $penalties = penalty_manager::get_penalties($scenario->cm->id, $student->id);
            $this->assertCount(0, $penalties, "No initial penalties for $type");

            // Add a penalty.
            $pid = penalty_manager::save_penalty(
                $scenario->cm->id, $student->id, $scenario->teacher->id,
                'wordcount', 'Over limit', 15, 0
            );
            $this->assertGreaterThan(0, $pid, "Penalty created for $type");

            // Verify.
            $penalties = penalty_manager::get_penalties($scenario->cm->id, $student->id);
            $this->assertCount(1, $penalties, "One penalty for $type");
            $this->assertEquals(15, $penalties[0]['percentage']);

            // Delete.
            penalty_manager::delete_penalty($pid);
            $penalties = penalty_manager::get_penalties($scenario->cm->id, $student->id);
            $this->assertCount(0, $penalties, "Penalty deleted for $type");
        }
    }

    /**
     * Test lang strings referenced in code exist.
     */
    public function test_lang_strings_exist(): void {
        // These were found missing in the audit and should now be defined.
        $strings = [
            'viewfeedback',
            'invalidmodule',
            'error_network',
            'error_offline_comments',
            'feedback_banner_default',
            'privacy_forum_extensions',
            'privacy_quiz_feedback',
        ];

        foreach ($strings as $key) {
            $value = get_string($key, 'local_unifiedgrader');
            $this->assertNotEmpty($value, "Lang string '$key' should be defined and non-empty");
            // If the string were missing, get_string returns [[key]] — check it's not that.
            $this->assertStringNotContainsString('[[', $value,
                "Lang string '$key' should not be a missing-string placeholder");
        }
    }

    /**
     * Test that the settings navigation function does not crash.
     */
    public function test_settings_navigation_no_crash(): void {
        global $PAGE;

        $scenario = $this->gen->create_grading_scenario('assign', [
            'studentcount' => 1,
            'enable' => true,
        ]);

        $context = \context_module::instance($scenario->cm->id);

        // Reset PAGE after course creation (boost_union theme conflict).
        $PAGE = new \moodle_page();

        $this->setUser($scenario->teacher);
        $PAGE->set_cm($scenario->cm);
        $PAGE->set_context($context);
        $PAGE->set_url(new \moodle_url('/mod/assign/view.php', ['id' => $scenario->cm->id]));

        $settingsnav = new \settings_navigation($PAGE);
        $settingsnav->initialise();

        // The function should not throw.
        \local_unifiedgrader_extend_settings_navigation($settingsnav, $context);

        // If we get here without exception, the dead code removal didn't break anything.
        $this->assertTrue(true);
    }
}
