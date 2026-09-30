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

namespace local_unifiedgrader\dates;

use local_unifiedgrader\external\get_student_dates;
use local_unifiedgrader\external\preview_student_dates;
use local_unifiedgrader\external\save_student_dates;
use local_unifiedgrader\penalty\compat;
use core_external\external_api;

/**
 * The "Dates & extensions" dialogue's loading, preview and saving.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_unifiedgrader\dates\student_dates
 * @covers     \local_unifiedgrader\external\get_student_dates
 * @covers     \local_unifiedgrader\external\preview_student_dates
 * @covers     \local_unifiedgrader\external\save_student_dates
 */
final class student_dates_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        compat::reset_cache();
        $this->setTimezone('Africa/Johannesburg');
    }

    /**
     * Presets come from the setting, sorted, with weeks named as weeks.
     */
    public function test_presets(): void {
        $labels = array_column(student_dates::get_presets(), 'label');
        $this->assertSame(['+1 day', '+2 days', '+3 days', '+1 week'], $labels);

        set_config('extensionpresets', '14, 5, 5, 0, 1', 'local_unifiedgrader');
        $this->assertSame([1, 5, 14], array_column(student_dates::get_presets(), 'days'));
        $this->assertSame('+2 weeks', student_dates::get_presets()[2]['label']);
    }

    /**
     * Dates cross the boundary as local date-times in the teacher's time zone.
     */
    public function test_local_datetimes_use_the_teachers_time_zone(): void {
        $user = $this->getDataGenerator()->create_user(['timezone' => 'Africa/Johannesburg']);
        $this->setUser($user);

        $timestamp = student_dates::from_local('2026-08-17T23:59');
        $this->assertSame(strtotime('2026-08-17 21:59:00 UTC'), $timestamp);
        $this->assertSame('2026-08-17T23:59', student_dates::to_local($timestamp));

        $this->expectException(\invalid_parameter_exception::class);
        student_dates::from_local('17/08/2026');
    }

    /**
     * An assignment lists its dates with the class defaults beside the student's.
     */
    public function test_assign_load(): void {
        $s = $this->assign_scenario();

        $data = get_student_dates::execute((int) $s->cm->id, (int) $s->students[0]->id);
        $data = external_api::clean_returnvalue(get_student_dates::execute_returns(), $data);

        $this->assertSame('assign', $data['activitytype']);
        $this->assertSame(['allowsubmissionsfromdate', 'duedate', 'cutoffdate'], array_column($data['settings'], 'key'));
        $this->assertTrue($data['extension']['available']);
        $this->assertTrue($data['extension']['editable']);
        $this->assertSame(student_dates::to_local($s->due), $data['extension']['classvalue']);
        $this->assertSame('', $data['extension']['value']);
        $this->assertFalse($data['settings'][2]['overridden']);
        $this->assertTrue($data['settings'][2]['follows']);
    }

    /**
     * An extension past the cut-off moves the cut-off with it; clearing it all removes both.
     */
    public function test_assign_extension_moves_cutoff_and_clears(): void {
        global $DB;
        $s = $this->assign_scenario();
        $userid = (int) $s->students[0]->id;
        $ext = $s->cutoff + 2 * DAYSECS;

        $result = save_student_dates::execute((int) $s->cm->id, $userid, student_dates::to_local($ext), []);
        $result = external_api::clean_returnvalue(save_student_dates::execute_returns(), $result);

        $flags = $DB->get_record('assign_user_flags', ['assignment' => $s->activity->id, 'userid' => $userid]);
        $this->assertEquals($ext, $flags->extensionduedate);
        $override = $DB->get_record('assign_overrides', ['assignid' => $s->activity->id, 'userid' => $userid]);
        $this->assertEquals($ext, $override->cutoffdate);
        $this->assertNull($override->duedate);

        save_student_dates::execute((int) $s->cm->id, $userid, '', []);
        $flags = $DB->get_record('assign_user_flags', ['assignment' => $s->activity->id, 'userid' => $userid]);
        $this->assertEquals(0, $flags->extensionduedate);
        $this->assertFalse($DB->record_exists('assign_overrides', ['assignid' => $s->activity->id, 'userid' => $userid]));
    }

    /**
     * An override on the "Submissions open" row is saved and loads back as overridden.
     */
    public function test_assign_override_round_trip(): void {
        $s = $this->assign_scenario();
        $userid = (int) $s->students[0]->id;
        $open = student_dates::to_local($s->due - 10 * DAYSECS);

        save_student_dates::execute((int) $s->cm->id, $userid, '', [['key' => 'allowsubmissionsfromdate', 'value' => $open]]);

        $data = (new student_dates((int) $s->cm->id, $userid))->load();
        $this->assertTrue($data['settings'][0]['overridden']);
        $this->assertSame($open, $data['settings'][0]['value']);
    }

    /**
     * An extension must fall after the class due date.
     */
    public function test_assign_extension_must_be_after_due_date(): void {
        $s = $this->assign_scenario();

        $this->expectException(\moodle_exception::class);
        save_student_dates::execute((int) $s->cm->id, (int) $s->students[0]->id, student_dates::to_local($s->due - HOURSECS), []);
    }

    /**
     * A teacher who may grade but not manage overrides cannot change other settings.
     */
    public function test_overrides_need_the_capability(): void {
        $s = $this->assign_scenario();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/unifiedgrader:grade', CAP_ALLOW, $roleid, $s->context->id);
        $marker = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($marker->id, $s->course->id);
        role_assign($roleid, $marker->id, $s->context->id);
        $this->setUser($marker);

        $data = (new student_dates((int) $s->cm->id, (int) $s->students[0]->id))->load();
        $this->assertFalse($data['settings'][0]['editable']);

        $this->expectException(\required_capability_exception::class);
        save_student_dates::execute((int) $s->cm->id, (int) $s->students[0]->id, '', [
            ['key' => 'cutoffdate', 'value' => student_dates::to_local($s->cutoff + DAYSECS)],
        ]);
    }

    /**
     * Nothing submitted yet: the preview says so, whatever the extension.
     */
    public function test_preview_without_a_submission(): void {
        $s = $this->assign_scenario();

        $preview = preview_student_dates::execute((int) $s->cm->id, (int) $s->students[0]->id, '');
        $preview = external_api::clean_returnvalue(preview_student_dates::execute_returns(), $preview);
        $this->assertSame('neutral', $preview['tone']);
        $this->assertSame(get_string('dates_status_nosubmission_title', 'local_unifiedgrader'), $preview['title']);
    }

    /**
     * A forum extension covering a late first post clears the late penalty in the preview.
     */
    public function test_forum_preview_and_save(): void {
        global $DB;
        $this->setAdminUser();
        $this->add_penalty_rules();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $due = time() - 2 * DAYSECS;
        $s = $gen->create_grading_scenario('forum', ['modparams' => ['duedate' => $due]]);
        $userid = (int) $s->students[0]->id;
        $gen->create_forum_post($s->activity, $userid);

        $late = (new student_dates((int) $s->cm->id, $userid))->preview('');
        $this->assertSame('warning', $late['tone']);
        $this->assertStringContainsString('10%', $late['title']);

        $ext = student_dates::to_local(time() + DAYSECS);
        $ontime = (new student_dates((int) $s->cm->id, $userid))->preview($ext);
        $this->assertSame('success', $ontime['tone']);
        $this->assertSame(get_string('dates_status_extended_ontime_title', 'local_unifiedgrader'), $ontime['title']);

        save_student_dates::execute((int) $s->cm->id, $userid, $ext, []);
        $this->assertTrue($DB->record_exists('local_unifiedgrader_fext', ['cmid' => $s->cm->id, 'userid' => $userid]));
    }

    /**
     * Moodle 5.3 quiz: the extension is the core override's due date, saved with the other settings.
     */
    public function test_quiz_extension_and_override_on_53(): void {
        global $DB;
        if (!compat::unified()) {
            $this->markTestSkipped('Quiz due dates are core from Moodle 5.3.');
        }
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        // Whole minutes: the dialogue works in local date-times without seconds.
        $due = strtotime('tomorrow 23:59');
        $s = $gen->create_grading_scenario('quiz', ['modparams' => ['duedate' => $due, 'timeclose' => $due + DAYSECS]]);
        $userid = (int) $s->students[0]->id;
        $ext = $due + 3 * DAYSECS;

        save_student_dates::execute((int) $s->cm->id, $userid, student_dates::to_local($ext), [
            ['key' => 'timelimit', 'value' => '90'],
        ]);
        $override = $DB->get_record('quiz_overrides', ['quiz' => $s->activity->id, 'userid' => $userid], '*', MUST_EXIST);
        $this->assertEquals($ext, $override->duedate);
        $this->assertEquals($ext, $override->timeclose);
        $this->assertEquals(90 * MINSECS, $override->timelimit);

        // Removing everything deletes the override that is left empty.
        save_student_dates::execute((int) $s->cm->id, $userid, '', []);
        $this->assertFalse($DB->record_exists('quiz_overrides', ['quiz' => $s->activity->id, 'userid' => $userid]));
    }

    /**
     * Before Moodle 5.3 a quiz without the quizaccess_duedate rule offers no extension.
     */
    public function test_quiz_without_duedate_support_before_53(): void {
        if (compat::unified() || class_exists('\quizaccess_duedate\override_manager')) {
            $this->markTestSkipped('Only for Moodle before 5.3 without quizaccess_duedate.');
        }
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $gen->create_grading_scenario('quiz');

        $data = (new student_dates((int) $s->cm->id, (int) $s->students[0]->id))->load();
        $this->assertFalse($data['extension']['available']);
        $this->assertSame(get_string('dates_extension_unavailable', 'local_unifiedgrader'), $data['extension']['reason']);
    }

    // Helpers.

    /**
     * An assignment due in a day, with a cut-off a day later, as admin.
     *
     * @return \stdClass The scenario, with due and cutoff timestamps.
     */
    private function assign_scenario(): \stdClass {
        $this->setAdminUser();
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $due = strtotime('tomorrow 23:59');
        $cutoff = $due + DAYSECS;
        $s = $gen->create_grading_scenario('assign', ['modparams' => [
            'duedate' => $due,
            'cutoffdate' => $cutoff,
            'allowsubmissionsfromdate' => $due - 7 * DAYSECS,
        ]]);
        $s->due = $due;
        $s->cutoff = $cutoff;
        return $s;
    }

    /**
     * Site rules: up to a day late 5%, later 10%.
     */
    private function add_penalty_rules(): void {
        global $DB;
        $systemid = \context_system::instance()->id;
        foreach ([[DAYSECS, 5], [7 * DAYSECS, 10]] as $i => [$overdueby, $penalty]) {
            $DB->insert_record('gradepenalty_duedate_rule', (object) [
                'contextid' => $systemid, 'sortorder' => $i, 'overdueby' => $overdueby, 'penalty' => $penalty,
                'usermodified' => 0, 'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
    }
}
