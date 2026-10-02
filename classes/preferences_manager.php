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
 * Per-user UI preference storage for the unified grader.
 *
 * Backs the `local_unifiedgrader_prefs` table — one row per user, JSON
 * blob of key→value preferences. Keys are scoped freely by the caller
 * (e.g. `groupfilter.<cmid>` for the per-activity group filter so a
 * teacher's group choice on assignment A doesn't bleed into assignment B).
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader;

/**
 * CRUD helper for per-user UI preferences.
 */
class preferences_manager {
    /**
     * Read the entire preferences blob for a user.
     *
     * @param int $userid
     * @return array Key → value map (empty array when no row exists or JSON is malformed).
     */
    public static function get_all(int $userid): array {
        global $DB;

        $cache = \cache::make('local_unifiedgrader', 'userprefs');
        $cached = $cache->get($userid);
        if (is_array($cached)) {
            return $cached;
        }

        $prefs = [];
        $rec = $DB->get_record('local_unifiedgrader_prefs', ['userid' => $userid], 'preferences');
        if ($rec && !empty($rec->preferences)) {
            $decoded = json_decode($rec->preferences, true);
            $prefs = is_array($decoded) ? $decoded : [];
        }
        $cache->set($userid, $prefs);
        return $prefs;
    }

    /**
     * Forget the cached preferences of some users. Called when their rows are deleted.
     *
     * @param int[] $userids
     */
    public static function invalidate(array $userids): void {
        if ($userids) {
            \cache::make('local_unifiedgrader', 'userprefs')->delete_many(array_map('intval', $userids));
        }
    }

    /**
     * Get a single preference value by key.
     *
     * @param int $userid
     * @param string $key
     * @param mixed $default Returned when the key is unset.
     * @return mixed
     */
    public static function get(int $userid, string $key, $default = null) {
        $prefs = self::get_all($userid);
        return $prefs[$key] ?? $default;
    }

    /**
     * Set a single preference value. Creates the row on first write.
     *
     * @param int $userid
     * @param string $key
     * @param mixed $value Must be JSON-serialisable (string, number, bool, array of same).
     */
    public static function set(int $userid, string $key, $value): void {
        global $DB;
        $prefs = self::get_all($userid);
        $prefs[$key] = $value;
        self::write_all($userid, $prefs);
    }

    /**
     * Replace the entire preferences blob.
     *
     * @param int $userid
     * @param array $prefs
     */
    private static function write_all(int $userid, array $prefs): void {
        global $DB;
        $now = time();
        $existing = $DB->get_record('local_unifiedgrader_prefs', ['userid' => $userid], 'id');
        if ($existing) {
            $DB->update_record('local_unifiedgrader_prefs', (object) [
                'id' => $existing->id,
                'preferences' => json_encode($prefs),
                'timemodified' => $now,
            ]);
        } else {
            $DB->insert_record('local_unifiedgrader_prefs', (object) [
                'userid' => $userid,
                'preferences' => json_encode($prefs),
                'timemodified' => $now,
            ]);
        }
        \cache::make('local_unifiedgrader', 'userprefs')->set($userid, $prefs);
    }
}
