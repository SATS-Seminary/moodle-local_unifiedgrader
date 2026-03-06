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

/**
 * Tests for the forum_adapter class.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\adapter\forum_adapter
 */
final class forum_adapter_test extends \advanced_testcase {

    /**
     * Helper: create a forum scenario and return the adapter.
     *
     * @param array $options Scenario options.
     * @return object{adapter: forum_adapter, scenario: \stdClass}
     */
    private function create_scenario(array $options = []): object {
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $defaults = ['modparams' => ['grade_forum' => 100]];
        $scenario = $plugingen->create_grading_scenario('forum', array_merge_recursive($defaults, $options));
        $this->setUser($scenario->teacher);
        $adapter = adapter_factory::create($scenario->cm->id);
        return (object) ['adapter' => $adapter, 'scenario' => $scenario];
    }

    /**
     * Test get_activity_info returns correct basic info.
     */
    public function test_get_activity_info_basic(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $info = $s->adapter->get_activity_info();

        $this->assertEquals($s->scenario->cm->id, $info['id']);
        $this->assertEquals('forum', $info['type']);
        $this->assertArrayHasKey('name', $info);
        $this->assertArrayHasKey('duedate', $info);
        $this->assertArrayHasKey('maxgrade', $info);
        $this->assertArrayHasKey('gradingmethod', $info);
    }

    /**
     * Test get_participants returns students with no posts as nosubmission.
     */
    public function test_get_participants_no_posts(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $participants = $s->adapter->get_participants();

        $this->assertCount(3, $participants);
        foreach ($participants as $p) {
            $this->assertEquals('nosubmission', $p['status']);
        }
    }

    /**
     * Test get_participants shows 'submitted' for users with posts.
     */
    public function test_get_participants_with_posts(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $this->create_scenario();

        // Create a post for first student.
        $this->setUser($s->scenario->students[0]);
        $plugingen->create_forum_post($s->scenario->activity, $s->scenario->students[0]->id);

        $this->setUser($s->scenario->teacher);
        $participants = $s->adapter->get_participants();

        $posted = array_values(array_filter($participants, fn($p) => $p['id'] == $s->scenario->students[0]->id));
        $this->assertCount(1, $posted);
        $this->assertEquals('submitted', $posted[0]['status']);
    }

    /**
     * Test get_submission_data with no posts.
     */
    public function test_get_submission_data_no_posts(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $data = $s->adapter->get_submission_data($s->scenario->students[0]->id);

        $this->assertEquals($s->scenario->students[0]->id, $data['userid']);
        $this->assertEquals('nosubmission', $data['status']);
    }

    /**
     * Test get_submission_data with posts renders content.
     */
    public function test_get_submission_data_with_posts(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $this->create_scenario();

        $this->setUser($s->scenario->students[0]);
        $plugingen->create_forum_post($s->scenario->activity, $s->scenario->students[0]->id, [
            'message' => '<p>My forum contribution</p>',
        ]);

        $this->setUser($s->scenario->teacher);
        $data = $s->adapter->get_submission_data($s->scenario->students[0]->id);

        $this->assertEquals('submitted', $data['status']);
        $this->assertNotEmpty($data['content']);
    }

    /**
     * Test get_grade_data with no grade.
     */
    public function test_get_grade_data_no_grade(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $data = $s->adapter->get_grade_data($s->scenario->students[0]->id);

        $this->assertNull($data['grade']);
    }

    /**
     * Test save_grade and read it back.
     */
    public function test_save_grade_simple(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $result = $s->adapter->save_grade($s->scenario->students[0]->id, 75.0, '<p>Good posts!</p>');
        $this->assertTrue($result);

        $data = $s->adapter->get_grade_data($s->scenario->students[0]->id);
        $this->assertEquals(75.0, $data['grade']);
    }

    /**
     * Test get_type returns 'forum'.
     */
    public function test_get_type(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $this->assertEquals('forum', $s->adapter->get_type());
    }

    /**
     * Test are_grades_posted defaults to true.
     */
    public function test_are_grades_posted_default(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $this->assertTrue($s->adapter->are_grades_posted());
    }

    /**
     * Test set_grades_posted hides grades.
     */
    public function test_set_grades_posted(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $s->adapter->set_grades_posted(1);
        $this->assertFalse($s->adapter->are_grades_posted());
    }

    /**
     * Test get_participants structure.
     */
    public function test_get_participants_structure(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario(['studentcount' => 1]);
        $participants = $s->adapter->get_participants();

        $this->assertCount(1, $participants);
        $p = $participants[0];

        $expectedkeys = ['id', 'fullname', 'status', 'submittedat', 'gradevalue'];
        foreach ($expectedkeys as $key) {
            $this->assertArrayHasKey($key, $p, "Missing key: {$key}");
        }
    }

    /**
     * Test is_grade_released with no grade returns false.
     */
    public function test_is_grade_released_no_grade(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $this->assertFalse($s->adapter->is_grade_released($s->scenario->students[0]->id));
    }

    /**
     * Test supports_feature returns expected values.
     */
    public function test_supports_feature(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        // Forum always has onlinetext (the posts).
        $this->assertTrue($s->adapter->supports_feature('onlinetext'));
        // Forum supports file attachments on posts.
        $this->assertTrue($s->adapter->supports_feature('filesubmission'));
    }

    /**
     * Test perform_submission_action throws for forum (not supported).
     */
    public function test_perform_submission_action_throws(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();

        $this->expectException(\moodle_exception::class);
        $s->adapter->perform_submission_action($s->scenario->students[0]->id, 'lock');
    }

    /**
     * Test prepare_feedback_draft returns empty for student with no feedback.
     */
    public function test_prepare_feedback_draft_no_feedback(): void {
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $draftitemid = file_get_unused_draft_itemid();
        $result = $s->adapter->prepare_feedback_draft($s->scenario->students[0]->id, $draftitemid);

        $this->assertArrayHasKey('feedbackhtml', $result);
        $this->assertEmpty($result['feedbackhtml']);
    }

    /**
     * Test save_grade with draftitemid persists feedback files.
     */
    public function test_save_grade_persists_feedback_files(): void {
        global $USER;
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $student = $s->scenario->students[0];

        // First, save a grade without files so grade_grade record exists.
        $s->adapter->save_grade($student->id, 80.0, '<p>Initial feedback</p>');

        // Create a file in the draft area simulating a TinyMCE upload.
        $draftitemid = file_get_unused_draft_itemid();
        $usercontext = \context_user::instance($USER->id);
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'testvideo.mp4',
        ], 'fake video content');

        // Save grade with the draft, embedding a @@PLUGINFILE@@ reference.
        $feedbackhtml = '<p>See video: <video src="@@PLUGINFILE@@/testvideo.mp4"></video></p>';
        $s->adapter->save_grade($student->id, 85.0, $feedbackhtml, FORMAT_HTML, [], $draftitemid);

        // Verify file was moved to permanent storage.
        $gradeitem = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'forum',
            'iteminstance' => $s->scenario->activity->id,
            'itemnumber' => 1,
            'courseid' => $s->scenario->course->id,
        ]);
        $gradegrade = \grade_grade::fetch([
            'itemid' => $gradeitem->id,
            'userid' => $student->id,
        ]);

        $context = \context_module::instance($s->scenario->cm->id);
        $files = $fs->get_area_files(
            $context->id,
            'local_unifiedgrader',
            'forumfeedback',
            (int) $gradegrade->id,
            'filename',
            false,
        );

        $this->assertCount(1, $files);
        $file = reset($files);
        $this->assertEquals('testvideo.mp4', $file->get_filename());
    }

    /**
     * Test prepare_feedback_draft copies existing files into draft area.
     */
    public function test_prepare_feedback_draft_copies_files(): void {
        global $USER;
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $student = $s->scenario->students[0];

        // Save a grade with an embedded file.
        $draftitemid = file_get_unused_draft_itemid();
        $usercontext = \context_user::instance($USER->id);
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'recording.webm',
        ], 'fake audio content');

        $feedbackhtml = '<p>Listen: <audio src="@@PLUGINFILE@@/recording.webm"></audio></p>';
        $s->adapter->save_grade($student->id, 70.0, $feedbackhtml, FORMAT_HTML, [], $draftitemid);

        // Now prepare a new draft and verify the file is copied back.
        $newdraftid = file_get_unused_draft_itemid();
        $result = $s->adapter->prepare_feedback_draft($student->id, $newdraftid);

        $this->assertNotEmpty($result['feedbackhtml']);
        $this->assertStringContainsString('recording.webm', $result['feedbackhtml']);
        $this->assertStringContainsString('draftfile.php', $result['feedbackhtml']);

        // Verify file is in the new draft area.
        $draftfiles = $fs->get_area_files(
            $usercontext->id,
            'user',
            'draft',
            $newdraftid,
            'filename',
            false,
        );
        $this->assertCount(1, $draftfiles);
        $this->assertEquals('recording.webm', reset($draftfiles)->get_filename());
    }

    /**
     * Test get_grade_data rewrites @@PLUGINFILE@@ URLs for display.
     */
    public function test_get_grade_data_rewrites_pluginfile_urls(): void {
        global $USER;
        $this->resetAfterTest();

        $s = $this->create_scenario();
        $student = $s->scenario->students[0];

        // Save a grade with an embedded file.
        $draftitemid = file_get_unused_draft_itemid();
        $usercontext = \context_user::instance($USER->id);
        $fs = get_file_storage();
        $fs->create_file_from_string([
            'contextid' => $usercontext->id,
            'component' => 'user',
            'filearea' => 'draft',
            'itemid' => $draftitemid,
            'filepath' => '/',
            'filename' => 'feedback.mp4',
        ], 'video data');

        $feedbackhtml = '<p><video src="@@PLUGINFILE@@/feedback.mp4"></video></p>';
        $s->adapter->save_grade($student->id, 90.0, $feedbackhtml, FORMAT_HTML, [], $draftitemid);

        // Read grade data back — URLs should be rewritten to pluginfile.php.
        $data = $s->adapter->get_grade_data($student->id);

        $this->assertStringContainsString('pluginfile.php', $data['feedback']);
        $this->assertStringContainsString('feedback.mp4', $data['feedback']);
        $this->assertStringNotContainsString('@@PLUGINFILE@@', $data['feedback']);
    }
}
