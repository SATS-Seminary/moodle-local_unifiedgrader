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
 * CRUD operations for grade penalties.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader;

defined('MOODLE_INTERNAL') || die();

/**
 * Manages grade penalties per student per activity.
 *
 * Penalties deduct a percentage of the max grade from the student's score.
 * Two categories: 'wordcount' (max one per student/activity) and 'other'
 * (one per unique label per student/activity).
 */
class penalty_manager {

    /** @var string Table name. */
    private const TABLE = 'local_unifiedgrader_penalty';

    /**
     * Get all penalties for a student in a specific activity.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @return array List of penalty arrays.
     */
    public static function get_penalties(int $cmid, int $userid): array {
        global $DB;

        $records = $DB->get_records(self::TABLE, [
            'cmid' => $cmid,
            'userid' => $userid,
        ], 'timecreated ASC');

        return array_values(array_map(function ($rec) {
            return [
                'id' => (int) $rec->id,
                'cmid' => (int) $rec->cmid,
                'userid' => (int) $rec->userid,
                'authorid' => (int) $rec->authorid,
                'category' => $rec->category,
                'label' => $rec->label,
                'percentage' => (int) $rec->percentage,
                'timecreated' => (int) $rec->timecreated,
                'timemodified' => (int) $rec->timemodified,
            ];
        }, $records));
    }

    /**
     * Save a penalty (create or update).
     *
     * For 'wordcount' category: replaces any existing wordcount penalty for (cmid, userid).
     * For 'other' category: replaces any existing penalty with the same label, or inserts new.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @param int $authorid Author (teacher) user ID.
     * @param string $category 'wordcount' or 'other'.
     * @param string $label Custom label (for 'other' category, max 15 chars).
     * @param int $percentage Penalty percentage (1-100).
     * @param int $penaltyid Existing penalty ID to update, or 0 for auto-detect.
     * @return int The penalty ID.
     */
    public static function save_penalty(
        int $cmid,
        int $userid,
        int $authorid,
        string $category,
        string $label,
        int $percentage,
        int $penaltyid = 0,
    ): int {
        global $DB;

        $now = time();
        $label = \core_text::substr(trim($label), 0, 15);

        // If updating by ID, just update.
        if ($penaltyid > 0) {
            $record = $DB->get_record(self::TABLE, ['id' => $penaltyid], '*', MUST_EXIST);
            $record->category = $category;
            $record->label = $label;
            $record->percentage = $percentage;
            $record->authorid = $authorid;
            $record->timemodified = $now;
            $DB->update_record(self::TABLE, $record);
            return $penaltyid;
        }

        // Auto-detect: for wordcount, replace existing; for other, replace if same label.
        $conditions = [
            'cmid' => $cmid,
            'userid' => $userid,
            'category' => $category,
        ];
        if ($category === 'other') {
            $conditions['label'] = $label;
        }

        $existing = $DB->get_record(self::TABLE, $conditions);
        if ($existing) {
            $existing->percentage = $percentage;
            $existing->authorid = $authorid;
            $existing->label = $label;
            $existing->timemodified = $now;
            $DB->update_record(self::TABLE, $existing);
            return (int) $existing->id;
        }

        // Insert new.
        $record = (object) [
            'cmid' => $cmid,
            'userid' => $userid,
            'authorid' => $authorid,
            'category' => $category,
            'label' => $label,
            'percentage' => $percentage,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        return $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Delete a single penalty.
     *
     * @param int $penaltyid The penalty ID.
     */
    public static function delete_penalty(int $penaltyid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['id' => $penaltyid]);
    }

    /**
     * Delete all penalties for a student in an activity.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     */
    public static function delete_all_penalties(int $cmid, int $userid): void {
        global $DB;
        $DB->delete_records(self::TABLE, ['cmid' => $cmid, 'userid' => $userid]);
    }

    /**
     * Calculate the total marks to deduct based on penalties.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @param float $maxgrade The maximum grade for the activity.
     * @return float Total marks to deduct.
     */
    public static function get_total_deduction(int $cmid, int $userid, float $maxgrade): float {
        global $DB;

        $sum = $DB->get_field_sql(
            "SELECT COALESCE(SUM(percentage), 0)
               FROM {" . self::TABLE . "}
              WHERE cmid = :cmid AND userid = :userid",
            ['cmid' => $cmid, 'userid' => $userid],
        );

        return ((float) $sum / 100.0) * $maxgrade;
    }

    /**
     * Synchronise the auto-calculated late penalty for a student.
     *
     * Creates, updates, or deletes the 'late' penalty record to match the
     * current penalty rules. Called before returning penalties or saving grades
     * for forum activities.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @param int|null $percentage Penalty percentage (null or 0 to remove).
     * @param int $dayslate Number of whole days late (for the label).
     */
    public static function sync_late_penalty(int $cmid, int $userid, ?int $percentage, int $dayslate = 0): void {
        global $DB, $USER;

        $existing = $DB->get_record(self::TABLE, [
            'cmid' => $cmid,
            'userid' => $userid,
            'category' => 'late',
        ]);

        // Remove if no penalty applies.
        if (empty($percentage)) {
            if ($existing) {
                $DB->delete_records(self::TABLE, ['id' => $existing->id]);
            }
            return;
        }

        $label = $dayslate > 0
            ? get_string('penalty_late_days', 'local_unifiedgrader', $dayslate)
            : get_string('penalty_late', 'local_unifiedgrader');
        $label = \core_text::substr($label, 0, 15);
        $now = time();

        if ($existing) {
            // Only update if something changed.
            if ((int) $existing->percentage !== $percentage || $existing->label !== $label) {
                $existing->percentage = $percentage;
                $existing->label = $label;
                $existing->timemodified = $now;
                $DB->update_record(self::TABLE, $existing);
            }
            return;
        }

        // Create new late penalty record.
        $record = (object) [
            'cmid' => $cmid,
            'userid' => $userid,
            'authorid' => isset($USER->id) ? (int) $USER->id : 0,
            'category' => 'late',
            'label' => $label,
            'percentage' => $percentage,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $DB->insert_record(self::TABLE, $record);
    }

    /**
     * Get the sum of penalty percentages for a student.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     * @return int Total percentage (e.g. 15 for 5% + 10%).
     */
    public static function get_total_percentage(int $cmid, int $userid): int {
        global $DB;

        return (int) $DB->get_field_sql(
            "SELECT COALESCE(SUM(percentage), 0)
               FROM {" . self::TABLE . "}
              WHERE cmid = :cmid AND userid = :userid",
            ['cmid' => $cmid, 'userid' => $userid],
        );
    }
}
