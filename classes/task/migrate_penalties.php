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

/**
 * One-off move to the Moodle 5.3 penalty model.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\task;

use local_unifiedgrader\penalty\activity_settings;
use local_unifiedgrader\penalty\compat;

/**
 * Saves the late penalty switch the quizaccess_duedate rule held for each quiz.
 *
 * A scheduled task rather than an upgrade step, because the move depends on
 * the Moodle version, and this plugin's upgrade does not run again when Moodle
 * itself is upgraded to 5.3. It does nothing before 5.3, and nothing after it
 * has run once. cli/migrate_penalties.php runs it on demand.
 *
 * Run it before uninstalling quizaccess_duedate: until then the setting is read
 * straight from the rule's table, afterwards only saved values remain.
 *
 * Nothing here touches grades. A cell the old rule pinned keeps its penalised
 * mark until that student's grade is next resynced (see gradebook_writer), so
 * grades in closed courses are not recalculated under the new rules.
 */
class migrate_penalties extends \core\task\scheduled_task {
    /** @var string Config flag set once the migration has run. */
    public const DONE_FLAG = 'penalties_migrated_53';

    /**
     * Name shown in the task list.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_migrate_penalties', 'local_unifiedgrader');
    }

    /**
     * Run the task.
     */
    public function execute(): void {
        if (!compat::unified() || get_config('local_unifiedgrader', self::DONE_FLAG)) {
            return;
        }

        $count = self::save_quizaccess_switches();
        mtrace("Saved the late penalty switch for {$count} quizzes from quizaccess_duedate.");

        set_config(self::DONE_FLAG, time(), 'local_unifiedgrader');
    }

    /**
     * Save a switch for every quiz the access rule penalised.
     *
     * Quizzes that already have a saved value are left alone.
     *
     * @return int Number of quizzes saved.
     */
    public static function save_quizaccess_switches(): int {
        global $DB;

        if (!$DB->get_manager()->table_exists('quizaccess_duedate_instances')) {
            return 0;
        }

        $sql = "SELECT cm.id
                  FROM {quizaccess_duedate_instances} qd
                  JOIN {course_modules} cm ON cm.instance = qd.quizid
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
             LEFT JOIN {local_unifiedgrader_penset} ps ON ps.cmid = cm.id
                 WHERE qd.penaltyenabled = 1 AND qd.duedate > 0 AND ps.id IS NULL";
        $cmids = $DB->get_fieldset_sql($sql);
        foreach ($cmids as $cmid) {
            activity_settings::set_enabled((int) $cmid, true);
        }
        return count($cmids);
    }
}
