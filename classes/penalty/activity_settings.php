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
 * Per-activity late penalty switch.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\penalty;

/**
 * Stores whether late penalties apply to an activity.
 *
 * An activity without a stored row uses its type's default, which keeps the
 * behaviour it had before Unified Grader took over late penalties:
 *
 * - assignment: its own "Apply penalties" setting (assign.gradepenalty);
 * - forum: on, since the grader already penalised forums whenever a rule matched;
 * - quiz: on only if the quizaccess_duedate rule had penalties switched on for it.
 *
 * Working this out on read, rather than backfilling rows on upgrade, means it is
 * right whenever the site reaches Moodle 5.3, even if this plugin was upgraded
 * first. Saving the activity's settings form always writes a row, so a new
 * activity takes the form's default (off).
 */
class activity_settings {
    /** @var string Table name. */
    private const TABLE = 'local_unifiedgrader_penset';

    /** @var string[] Activity types whose late penalties Unified Grader owns. */
    public const MODULES = ['assign', 'forum', 'quiz'];

    /**
     * Whether Unified Grader manages late penalties for this activity type.
     *
     * @param string $modname Module name.
     * @return bool
     */
    public static function supports(string $modname): bool {
        return in_array($modname, self::MODULES, true);
    }

    /**
     * Whether late penalties apply to an activity.
     *
     * @param \cm_info|\stdClass $cm Course module, with modname and instance.
     * @return bool
     */
    public static function is_enabled($cm): bool {
        global $DB;

        if (!self::supports((string) $cm->modname)) {
            return false;
        }
        $enabled = $DB->get_field(self::TABLE, 'enabled', ['cmid' => (int) $cm->id]);
        if ($enabled !== false) {
            return (bool) $enabled;
        }
        return self::get_default($cm);
    }

    /**
     * The value an activity has when nothing has been saved for it.
     *
     * @param \cm_info|\stdClass $cm Course module, with modname and instance.
     * @return bool
     */
    public static function get_default($cm): bool {
        global $DB;

        switch ($cm->modname) {
            case 'assign':
                return (bool) $DB->get_field('assign', 'gradepenalty', ['id' => (int) $cm->instance]);
            case 'forum':
                return true;
            case 'quiz':
                return self::quizaccess_penalty_enabled((int) $cm->instance);
            default:
                return false;
        }
    }

    /**
     * Whether the quiz had late penalties switched on in quizaccess_duedate.
     *
     * Carries a teacher's choice over from the old access rule, for as long as
     * its table exists. migrate_penalties stores the answer as a saved value,
     * so it survives the rule being uninstalled.
     *
     * @param int $quizid Quiz ID.
     * @return bool
     */
    public static function quizaccess_penalty_enabled(int $quizid): bool {
        global $DB;

        if (!$DB->get_manager()->table_exists('quizaccess_duedate_instances')) {
            return false;
        }
        return $DB->record_exists_select(
            'quizaccess_duedate_instances',
            'quizid = :quizid AND penaltyenabled = 1 AND duedate > 0',
            ['quizid' => $quizid],
        );
    }

    /**
     * Save the switch for an activity.
     *
     * @param int $cmid Course module ID.
     * @param bool $enabled Whether late penalties apply.
     */
    public static function set_enabled(int $cmid, bool $enabled): void {
        global $DB;

        $existing = $DB->get_record(self::TABLE, ['cmid' => $cmid]);
        if ($existing) {
            if ((bool) $existing->enabled === $enabled) {
                return;
            }
            $existing->enabled = (int) $enabled;
            $existing->timemodified = time();
            $DB->update_record(self::TABLE, $existing);
            return;
        }
        $DB->insert_record(self::TABLE, (object) [
            'cmid' => $cmid,
            'enabled' => (int) $enabled,
            'timemodified' => time(),
        ]);
    }

    /**
     * Whether a value has been saved for an activity.
     *
     * @param int $cmid Course module ID.
     * @return bool
     */
    public static function has_saved_value(int $cmid): bool {
        global $DB;
        return $DB->record_exists(self::TABLE, ['cmid' => $cmid]);
    }

    /**
     * Forget the saved value for an activity.
     *
     * @param int $cmid Course module ID.
     */
    public static function delete(int $cmid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['cmid' => $cmid]);
    }
}
