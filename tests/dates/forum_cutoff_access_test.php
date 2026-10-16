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

use local_unifiedgrader\external\save_student_dates;

/**
 * A forum extension past the cut-off lets that one student keep posting, and only until it ends.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_unifiedgrader\dates\forum_cutoff_access
 * @covers     \local_unifiedgrader\task\expire_forum_extensions
 */
final class forum_cutoff_access_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->setAdminUser();
    }

    /**
     * A forum whose cut-off has passed, with two students.
     *
     * @param array $params Forum settings to change.
     * @return \stdClass The scenario, with 'context' added.
     */
    private function forum(array $params = []): \stdClass {
        $gen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $s = $gen->create_grading_scenario('forum', ['studentcount' => 2, 'modparams' => $params + [
            'duedate' => time() - 3 * DAYSECS,
            'cutoffdate' => time() - DAYSECS,
        ]]);
        $s->context = \context_module::instance($s->cm->id);
        return $s;
    }

    /**
     * Whether a student may post in the forum after its cut-off.
     *
     * @param \stdClass $s The scenario.
     * @param int $index Which student.
     * @return bool
     */
    private function can_post_late(\stdClass $s, int $index): bool {
        return has_capability(forum_cutoff_access::CAPABILITY, $s->context, $s->students[$index]->id);
    }

    /**
     * The role exists, carries only the cut-off capability, and cannot be assigned by hand.
     */
    public function test_the_role(): void {
        global $DB;

        $roleid = forum_cutoff_access::roleid();
        $this->assertSame($roleid, forum_cutoff_access::roleid(), 'The role is created once.');

        $caps = $DB->get_records_menu('role_capabilities', ['roleid' => $roleid], '', 'capability, permission');
        $this->assertSame([forum_cutoff_access::CAPABILITY => (string) CAP_ALLOW], $caps);
        $this->assertSame([], get_role_contextlevels($roleid));
    }

    /**
     * An extension past the cut-off lets that student, and no other, post late;
     * withdrawing it takes the access away at once.
     */
    public function test_an_extension_past_the_cutoff_lets_only_that_student_post(): void {
        $s = $this->forum();
        $this->assertFalse($this->can_post_late($s, 0));

        save_student_dates::execute(
            (int) $s->cm->id,
            (int) $s->students[0]->id,
            student_dates::to_local(time() + 2 * DAYSECS),
            []
        );

        $this->assertTrue($this->can_post_late($s, 0));
        $this->assertFalse($this->can_post_late($s, 1), 'The other student is not affected.');

        save_student_dates::execute((int) $s->cm->id, (int) $s->students[0]->id, '', []);
        $this->assertFalse($this->can_post_late($s, 0));
    }

    /**
     * An extension that stays inside the cut-off needs no access, nor does a forum with no cut-off.
     */
    public function test_no_access_when_the_cutoff_is_not_passed(): void {
        $s = $this->forum(['duedate' => time() + DAYSECS, 'cutoffdate' => time() + 10 * DAYSECS]);
        save_student_dates::execute(
            (int) $s->cm->id,
            (int) $s->students[0]->id,
            student_dates::to_local(time() + 5 * DAYSECS),
            []
        );
        $this->assertFalse($this->can_post_late($s, 0));

        $open = $this->forum(['duedate' => time() + DAYSECS, 'cutoffdate' => 0]);
        save_student_dates::execute(
            (int) $open->cm->id,
            (int) $open->students[0]->id,
            student_dates::to_local(time() + 5 * DAYSECS),
            []
        );
        $this->assertFalse($this->can_post_late($open, 0));
    }

    /**
     * The scheduled task takes the access away once the extension has ended, and
     * gives it where a cut-off was moved earlier than an extension already granted.
     */
    public function test_the_task_ends_and_starts_access(): void {
        global $DB;

        // Granted while the cut-off was still ahead: no access needed yet.
        $s = $this->forum(['duedate' => time() + DAYSECS, 'cutoffdate' => time() + 10 * DAYSECS]);
        $extension = time() + 5 * DAYSECS;
        save_student_dates::execute((int) $s->cm->id, (int) $s->students[0]->id, student_dates::to_local($extension), []);
        $this->assertFalse($this->can_post_late($s, 0));

        // The teacher brings the cut-off forward, inside the extension: the task notices.
        $DB->set_field('forum', 'cutoffdate', time() + 2 * DAYSECS, ['id' => $s->cm->instance]);
        $this->assertSame(['removed' => 0, 'added' => 1], forum_cutoff_access::reconcile());
        $this->assertTrue($this->can_post_late($s, 0));

        // A minute after the extension ends, the task takes it away.
        $this->assertSame(['removed' => 1, 'added' => 0], forum_cutoff_access::reconcile($extension + 60));
        $this->assertFalse($this->can_post_late($s, 0));

        // Run as the scheduled task, at the real time: the extension has not ended
        // yet, so the access is given back.
        $this->expectOutputRegex('/0 ended, 1 started/');
        (new \local_unifiedgrader\task\expire_forum_extensions())->execute();
        $this->assertTrue($this->can_post_late($s, 0));
    }

    /**
     * Only the plugin's own assignment is ever removed: the same role given by
     * hand in the same forum is left alone.
     */
    public function test_only_its_own_assignment_is_removed(): void {
        global $DB;

        $s = $this->forum();
        $userid = (int) $s->students[0]->id;
        save_student_dates::execute((int) $s->cm->id, $userid, student_dates::to_local(time() + DAYSECS), []);
        role_assign(forum_cutoff_access::roleid(), $userid, $s->context->id);

        save_student_dates::execute((int) $s->cm->id, $userid, '', []);

        $this->assertFalse($DB->record_exists('role_assignments', ['userid' => $userid,
            'contextid' => $s->context->id, 'component' => forum_cutoff_access::COMPONENT]));
        $this->assertTrue($DB->record_exists('role_assignments', ['userid' => $userid,
            'contextid' => $s->context->id, 'component' => '']));
    }
}
