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
 * A plain rubric with a single criterion reaches the marking panel.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(assign_adapter::class)]
final class rubric_single_criterion_test extends \advanced_testcase {
    /**
     * One criterion scored 0, 1 or 2, on an assignment graded in points.
     */
    public function test_single_criterion_rubric_is_served(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        // Graded out of 3 while the rubric tops out at 2, as on the site this was reported from.
        $scenario = $plugingen->create_grading_scenario('assign', ['modparams' => ['grade' => 3]]);
        $this->setUser($scenario->teacher);

        $rubricgen = $this->getDataGenerator()->get_plugin_generator('gradingform_rubric');
        $rubricgen->create_instance($scenario->context, 'mod_assign', 'submissions', 'One criterion', '', [
            'Argument' => [
                'Missing' => 0,
                'Partial' => 1,
                'Complete' => 2,
            ],
        ]);

        $adapter = adapter_factory::create($scenario->cm->id);

        $info = $adapter->get_activity_info();
        $this->assertSame('rubric', $info['gradingmethod']);

        $definition = $adapter->get_grading_definition();
        $this->assertIsArray($definition);
        $this->assertSame('rubric', $definition['method']);
        $this->assertCount(1, $definition['criteria']);
        $this->assertSame([0.0, 1.0, 2.0], array_column($definition['criteria'][0]['levels'], 'score'));

        // The marking panel reads the definition from the grade data.
        $student = reset($scenario->students);
        $gradedata = $adapter->get_grade_data((int) $student->id);
        $this->assertNotEmpty($gradedata['gradingdefinition']);
        $served = json_decode($gradedata['gradingdefinition'], true);
        $this->assertCount(1, $served['criteria']);
    }
}
