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
 * Version gate for the unified late penalty system.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\penalty;

/**
 * Decides whether this site runs the Moodle 5.3 penalty model.
 *
 * From Moodle 5.3 the quiz has its own due date and due date overrides
 * (MDL-82521), and the gradebook records a deduction separately from the raw
 * grade (MDL-88407). Together they let Unified Grader own late penalties for
 * every activity type it grades. Earlier versions keep the behaviour they had
 * before 3.0: core penalises assignments, the quizaccess_duedate rule
 * penalises quizzes, and forums are penalised from the grader.
 *
 * The gate tests the quiz module version rather than $CFG->version, because
 * the quiz due date is the feature the whole model depends on.
 */
class compat {
    /** @var int Version of mod_quiz that added quiz.duedate (MDL-82521). */
    public const QUIZ_DUEDATE_VERSION = 2026083100;

    /** @var bool|null Answer for this request, or null before the first call. */
    private static ?bool $unified = null;

    /**
     * Whether Unified Grader owns late penalties on this site.
     *
     * @return bool
     */
    public static function unified(): bool {
        if (self::$unified === null) {
            self::$unified = (int) get_config('mod_quiz', 'version') >= self::QUIZ_DUEDATE_VERSION;
        }
        return self::$unified;
    }

    /**
     * Whether the quizaccess_duedate rule should be used for quiz due dates.
     *
     * Only before 5.3. On 5.3 the rule's settings collide with core's own
     * quiz.duedate, so it is ignored even if it is still installed.
     *
     * @return bool
     */
    public static function use_quizaccess_duedate(): bool {
        return !self::unified() && class_exists('\quizaccess_duedate\override_manager');
    }

    /**
     * Whether an activity type's late penalty is one of this plugin's penalty rows.
     *
     * Tells the grader to show the late penalty from the penalty list, not from
     * an external source (core's assign_grades.penalty, or the quizaccess_duedate
     * figure and its gradebook feedback text).
     *
     * @param string $modname Module name.
     * @return bool
     */
    public static function late_penalty_is_row(string $modname): bool {
        return self::unified() && activity_settings::supports($modname);
    }

    /**
     * Forget the cached answer. For unit tests only.
     */
    public static function reset_cache(): void {
        self::$unified = null;
    }
}
