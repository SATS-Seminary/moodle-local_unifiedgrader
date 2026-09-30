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
 * Late penalty lookup against the core due date penalty rules.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\penalty;

/**
 * Turns a submission time and a due date into a late penalty.
 *
 * The percentages come from the rules of core's gradepenalty_duedate plugin
 * (Site administration > Grades > Grade penalties, and the course and activity
 * "Grade penalties" pages), resolved activity → course → site. These are tiers
 * ("overdue by up to 1 day: 10%"), not a rate per day.
 */
class rules {
    /**
     * The late penalty for a submission, or null when none applies.
     *
     * @param \cm_info $cm The activity.
     * @param int $submitted When the work was submitted.
     * @param int $duedate The student's effective due date (0 = none).
     * @return array|null ['percentage' => int, 'dayslate' => int], or null.
     */
    public static function late_penalty(\cm_info $cm, int $submitted, int $duedate): ?array {
        if (!class_exists('\gradepenalty_duedate\penalty_calculator')) {
            return null;
        }
        if ($duedate <= 0 || $submitted <= 0 || $submitted <= $duedate) {
            return null;
        }

        $percentage = \gradepenalty_duedate\penalty_calculator::get_penalty_from_rules($cm, $submitted, $duedate);
        $pct = (int) round($percentage);
        if ($pct <= 0) {
            return null;
        }

        return [
            'percentage' => min($pct, 100),
            'dayslate' => (int) ceil(($submitted - $duedate) / DAYSECS),
        ];
    }
}
