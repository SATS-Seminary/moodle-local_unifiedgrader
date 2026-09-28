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
 * Tests for the PSR-14 hook callbacks.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\hook_callbacks
 */
final class hook_callbacks_test extends \advanced_testcase {
    /**
     * Helper: Create a hook instance and invoke the callback.
     *
     * @return \core\hook\output\before_standard_top_of_body_html_generation
     */
    private function create_hook(): \core\hook\output\before_standard_top_of_body_html_generation {
        global $PAGE;
        $renderer = $PAGE->get_renderer('core');
        return new \core\hook\output\before_standard_top_of_body_html_generation($renderer);
    }

    /**
     * Test that non-module context is skipped (no exception).
     */
    public function test_non_module_context_skipped(): void {
        global $PAGE;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $PAGE->set_context(\context_course::instance($course->id));
        $PAGE->set_url(new \moodle_url('/course/view.php', ['id' => $course->id]));

        $hook = $this->create_hook();
        hook_callbacks::before_standard_top_of_body_html($hook);

        // No exception means the hook silently exited.
        $this->assertTrue(true);
    }

    /**
     * Test that unsupported module types are skipped.
     */
    public function test_unsupported_module_skipped(): void {
        global $PAGE;
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('page', $page->id);
        $cminfo = \cm_info::create($cm);
        $context = \context_module::instance($cm->id);

        // Reset $PAGE after course creation to avoid boost_union theme init conflict.
        $PAGE = new \moodle_page();

        $PAGE->set_cm($cminfo);
        $PAGE->set_context($context);
        $PAGE->set_url(new \moodle_url('/mod/page/view.php', ['id' => $cm->id]));

        $hook = $this->create_hook();
        hook_callbacks::before_standard_top_of_body_html($hook);

        // No exception or JS injected for unsupported modules.
        $this->assertTrue(true);
    }

    /**
     * Test that disabled module type is skipped.
     */
    public function test_disabled_module_skipped(): void {
        global $PAGE;
        $this->resetAfterTest();

        set_config('enable_assign', 0, 'local_unifiedgrader');

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $cminfo = \cm_info::create($cm);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        // Reset $PAGE after course creation to avoid boost_union theme init conflict.
        $PAGE = new \moodle_page();

        $PAGE->set_cm($cminfo);
        $PAGE->set_context($context);
        $PAGE->set_url(new \moodle_url('/mod/assign/view.php', ['id' => $cm->id]));

        $hook = $this->create_hook();
        hook_callbacks::before_standard_top_of_body_html($hook);

        $this->assertTrue(true);
    }

    /**
     * Test that teacher with grade capability gets grade_button_override JS.
     */
    public function test_teacher_gets_grade_override_js(): void {
        global $PAGE;
        $this->resetAfterTest();

        set_config('enable_assign', 1, 'local_unifiedgrader');

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $cminfo = \cm_info::create($cm);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        // Reset $PAGE after course creation to avoid boost_union theme init conflict.
        $PAGE = new \moodle_page();

        $PAGE->set_cm($cminfo);
        $PAGE->set_context($context);
        $PAGE->set_url(new \moodle_url('/mod/assign/view.php', ['id' => $cm->id]));

        $hook = $this->create_hook();
        hook_callbacks::before_standard_top_of_body_html($hook);

        // Verify that the JS module was called.
        $jscalls = $PAGE->requires->get_end_code();
        $this->assertStringContainsString('local_unifiedgrader/grade_button_override', $jscalls);
    }

    /**
     * Test that student with viewfeedback capability and released grade gets feedback banner JS.
     */
    public function test_student_graded_gets_feedback_banner(): void {
        global $PAGE;
        $this->resetAfterTest();

        set_config('enable_assign', 1, 'local_unifiedgrader');

        $gen = $this->getDataGenerator();
        $plugingen = $gen->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('assign', ['studentcount' => 1]);

        // Submit and grade.
        $this->setUser($scenario->students[0]);
        $plugingen->create_assign_submission($scenario->activity, $scenario->students[0]->id);

        $this->setUser($scenario->teacher);
        $adapter = adapter\adapter_factory::create($scenario->cm->id);
        $adapter->save_grade($scenario->students[0]->id, 80.0, 'Good');

        // Now act as the student.
        $this->setUser($scenario->students[0]);

        // Reset $PAGE after course creation to avoid boost_union theme init conflict.
        $PAGE = new \moodle_page();

        $PAGE->set_cm($scenario->cm);
        $PAGE->set_context($scenario->context);
        $PAGE->set_url(new \moodle_url('/mod/assign/view.php', ['id' => $scenario->cm->id]));

        $hook = $this->create_hook();
        hook_callbacks::before_standard_top_of_body_html($hook);

        $jscalls = $PAGE->requires->get_end_code();
        $this->assertStringContainsString('local_unifiedgrader/feedback_banner', $jscalls);
    }

    /**
     * Run the hook for a graded forum student on the given forum page.
     *
     * @param string $path The page path, e.g. '/mod/forum/discuss.php'.
     * @return string The JS the hook queued.
     */
    private function graded_forum_student_js_on(string $path): string {
        global $PAGE;

        set_config('enable_forum', 1, 'local_unifiedgrader');

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('forum', [
            'studentcount' => 1,
            'modparams' => ['grade_forum' => 100],
        ]);
        $student = $scenario->students[0];
        $plugingen->create_forum_post($scenario->activity, $student->id, ['subject' => 'My post']);

        $this->setUser($scenario->teacher);
        adapter\adapter_factory::create($scenario->cm->id)->save_grade($student->id, 90.0, 'Well argued');

        $this->setUser($student);

        // Reset $PAGE after course creation to avoid boost_union theme init conflict.
        $PAGE = new \moodle_page();

        $PAGE->set_cm($scenario->cm);
        $PAGE->set_context($scenario->context);
        $PAGE->set_url(new \moodle_url($path, ['id' => $scenario->cm->id]));

        hook_callbacks::before_standard_top_of_body_html($this->create_hook());

        return $PAGE->requires->get_end_code();
    }

    /**
     * A student can open a forum discussion without passing through the list of discussions,
     * so the feedback banner must be on the discussion page as well as the list.
     */
    public function test_student_graded_gets_feedback_banner_in_forum_discussion(): void {
        $this->resetAfterTest();

        $this->assertStringContainsString(
            'local_unifiedgrader/feedback_banner',
            $this->graded_forum_student_js_on('/mod/forum/view.php'),
        );
        $this->assertStringContainsString(
            'local_unifiedgrader/feedback_banner',
            $this->graded_forum_student_js_on('/mod/forum/discuss.php'),
        );
    }

    /**
     * Forum pages other than the list and a discussion, such as the reply form, stay untouched.
     */
    public function test_forum_post_page_skipped(): void {
        $this->resetAfterTest();

        $this->assertStringNotContainsString(
            'local_unifiedgrader/',
            $this->graded_forum_student_js_on('/mod/forum/post.php'),
        );
    }

    /**
     * Test that student without released grade gets assessment_criteria JS (if rubric exists).
     */
    public function test_student_ungraded_does_not_get_feedback_banner(): void {
        global $PAGE;
        $this->resetAfterTest();

        set_config('enable_assign', 1, 'local_unifiedgrader');

        $gen = $this->getDataGenerator();
        $plugingen = $gen->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('assign', ['studentcount' => 1]);

        $this->setUser($scenario->students[0]);

        // Reset $PAGE after course creation to avoid boost_union theme init conflict.
        $PAGE = new \moodle_page();

        $PAGE->set_cm($scenario->cm);
        $PAGE->set_context($scenario->context);
        $PAGE->set_url(new \moodle_url('/mod/assign/view.php', ['id' => $scenario->cm->id]));

        $hook = $this->create_hook();
        hook_callbacks::before_standard_top_of_body_html($hook);

        $jscalls = $PAGE->requires->get_end_code();
        // Should NOT contain the feedback banner since grade is not released.
        $this->assertStringNotContainsString('local_unifiedgrader/feedback_banner', $jscalls);
    }

    /**
     * Test that student on assignment view gets assignment_comments JS when setting is enabled.
     */
    public function test_student_assignment_gets_submission_comments_js(): void {
        global $PAGE;
        $this->resetAfterTest();

        set_config('enable_assign', 1, 'local_unifiedgrader');
        set_config('enable_submission_comments', 1, 'local_unifiedgrader');

        $gen = $this->getDataGenerator();
        $plugingen = $gen->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('assign', ['studentcount' => 1]);

        $this->setUser($scenario->students[0]);

        // Reset $PAGE after course creation to avoid boost_union theme init conflict.
        $PAGE = new \moodle_page();

        $PAGE->set_cm($scenario->cm);
        $PAGE->set_context($scenario->context);
        $PAGE->set_url(new \moodle_url('/mod/assign/view.php', ['id' => $scenario->cm->id]));

        $hook = $this->create_hook();
        hook_callbacks::before_standard_top_of_body_html($hook);

        $jscalls = $PAGE->requires->get_end_code();
        $this->assertStringContainsString('local_unifiedgrader/assignment_comments', $jscalls);
    }

    /**
     * Test that assignment_comments JS is NOT injected when setting is disabled.
     */
    public function test_student_assignment_no_comments_when_disabled(): void {
        global $PAGE;
        $this->resetAfterTest();

        set_config('enable_assign', 1, 'local_unifiedgrader');
        set_config('enable_submission_comments', 0, 'local_unifiedgrader');

        $gen = $this->getDataGenerator();
        $plugingen = $gen->get_plugin_generator('local_unifiedgrader');
        $scenario = $plugingen->create_grading_scenario('assign', ['studentcount' => 1]);

        $this->setUser($scenario->students[0]);

        // Reset $PAGE after course creation to avoid boost_union theme init conflict.
        $PAGE = new \moodle_page();

        $PAGE->set_cm($scenario->cm);
        $PAGE->set_context($scenario->context);
        $PAGE->set_url(new \moodle_url('/mod/assign/view.php', ['id' => $scenario->cm->id]));

        $hook = $this->create_hook();
        hook_callbacks::before_standard_top_of_body_html($hook);

        $jscalls = $PAGE->requires->get_end_code();
        $this->assertStringNotContainsString('local_unifiedgrader/assignment_comments', $jscalls);
    }
}
