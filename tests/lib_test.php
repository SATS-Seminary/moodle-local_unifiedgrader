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
 * Tests for lib.php functions.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversDefaultClass \local_unifiedgrader
 */
final class lib_test extends \advanced_testcase {

    /**
     * Test pluginfile returns false for non-module context.
     */
    public function test_pluginfile_wrong_contextlevel_returns_false(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $result = \local_unifiedgrader_pluginfile(
            $course, null, $context, 'annotatedpdf', [1, 2, 'file.pdf'], false,
        );
        $this->assertFalse($result);
    }

    /**
     * Test pluginfile returns false for unknown filearea.
     */
    public function test_pluginfile_wrong_filearea_returns_false(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        global $PAGE;
        $PAGE = new \moodle_page();

        $result = \local_unifiedgrader_pluginfile(
            $course, $cm, $context, 'unknownarea', [1, $teacher->id, 'file.pdf'], false,
        );
        $this->assertFalse($result);
    }

    /**
     * Test pluginfile returns false when user has no capability.
     */
    public function test_pluginfile_no_capability_returns_false(): void {
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $context = \context_module::instance($cm->id);

        // Create a user with no unifiedgrader capabilities.
        $user = $gen->create_user();
        $gen->enrol_user($user->id, $course->id, 'student');
        // Remove viewfeedback capability from student role.
        $roleid = $GLOBALS['DB']->get_field('role', 'id', ['shortname' => 'student']);
        assign_capability('local/unifiedgrader:viewfeedback', CAP_PROHIBIT, $roleid, $context->id);
        assign_capability('local/unifiedgrader:grade', CAP_PROHIBIT, $roleid, $context->id);
        $this->setUser($user);

        global $PAGE;
        $PAGE = new \moodle_page();

        $result = \local_unifiedgrader_pluginfile(
            $course, $cm, $context, 'annotatedpdf', [1, $user->id, 'file.pdf'], false,
        );
        $this->assertFalse($result);
    }

    /**
     * Test pluginfile returns false when student tries to access another user's PDF.
     */
    public function test_pluginfile_student_other_user_blocked(): void {
        $this->resetAfterTest();

        set_config('enable_assign', 1, 'local_unifiedgrader');

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $context = \context_module::instance($cm->id);

        $student1 = $gen->create_user();
        $student2 = $gen->create_user();
        $gen->enrol_user($student1->id, $course->id, 'student');
        $gen->enrol_user($student2->id, $course->id, 'student');

        $this->setUser($student1);

        global $PAGE;
        $PAGE = new \moodle_page();

        // Try to access student2's PDF.
        $result = \local_unifiedgrader_pluginfile(
            $course, $cm, $context, 'annotatedpdf', [1, $student2->id, 'file.pdf'], false,
        );
        $this->assertFalse($result);
    }

    /**
     * Test pluginfile returns false when file doesn't exist (teacher).
     */
    public function test_pluginfile_file_not_found_returns_false(): void {
        $this->resetAfterTest();

        set_config('enable_assign', 1, 'local_unifiedgrader');

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $assign = $gen->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        global $PAGE;
        $PAGE = new \moodle_page();

        $result = \local_unifiedgrader_pluginfile(
            $course, $cm, $context, 'annotatedpdf', [99999, $teacher->id, 'nonexistent.pdf'], false,
        );
        $this->assertFalse($result);
    }

    /**
     * Test extend_settings_navigation adds node for supported teacher.
     */
    public function test_extend_settings_navigation_adds_node(): void {
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

        $PAGE = new \moodle_page();
        $PAGE->set_cm($cminfo);
        $PAGE->set_context($context);
        $PAGE->set_url(new \moodle_url('/mod/assign/view.php', ['id' => $cm->id]));

        // Build settings navigation.
        $settingsnav = new \settings_navigation($PAGE);
        $settingsnav->initialise();

        \local_unifiedgrader_extend_settings_navigation($settingsnav, $context);

        $node = $settingsnav->find('local_unifiedgrader_grade', \navigation_node::TYPE_CUSTOM);
        $this->assertNotNull($node, 'Unified Grader navigation node should exist');
    }

    /**
     * Test extend_settings_navigation skips unsupported module types.
     */
    public function test_extend_settings_navigation_skips_unsupported(): void {
        global $PAGE;
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $pagemod = $gen->create_module('page', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('page', $pagemod->id);
        $cminfo = \cm_info::create($cm);
        $context = \context_module::instance($cm->id);

        $teacher = $gen->create_user();
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $PAGE = new \moodle_page();
        $PAGE->set_cm($cminfo);
        $PAGE->set_context($context);
        $PAGE->set_url(new \moodle_url('/mod/page/view.php', ['id' => $cm->id]));

        $settingsnav = new \settings_navigation($PAGE);
        $settingsnav->initialise();

        \local_unifiedgrader_extend_settings_navigation($settingsnav, $context);

        $node = $settingsnav->find('local_unifiedgrader_grade', \navigation_node::TYPE_CUSTOM);
        $this->assertFalse($node, 'Unified Grader node should NOT exist for page module');
    }

    /**
     * Test extend_settings_navigation skips when adapter is disabled.
     */
    public function test_extend_settings_navigation_skips_disabled(): void {
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

        $PAGE = new \moodle_page();
        $PAGE->set_cm($cminfo);
        $PAGE->set_context($context);
        $PAGE->set_url(new \moodle_url('/mod/assign/view.php', ['id' => $cm->id]));

        $settingsnav = new \settings_navigation($PAGE);
        $settingsnav->initialise();

        \local_unifiedgrader_extend_settings_navigation($settingsnav, $context);

        $node = $settingsnav->find('local_unifiedgrader_grade', \navigation_node::TYPE_CUSTOM);
        $this->assertFalse($node, 'Unified Grader node should NOT exist when assign is disabled');
    }
}
