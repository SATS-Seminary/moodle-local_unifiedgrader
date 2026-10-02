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
 * Forum-related helpers shared by the hook callback and the submission-
 * comments web services.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader;

/**
 * Small static helper.
 */
class forum_helper {
    /**
     * Whether the given course module is a news (announcements) forum.
     *
     * News forums are special: they're auto-created by Moodle on every
     * course, can't be graded, and never participate in the teacher /
     * student feedback loop. Any plugin feature keyed on "teacher might
     * want to leave a private comment for a student" should skip them.
     *
     * @param \stdClass|\cm_info $cm The course-module object (must have at least id + modname + instance).
     * @return bool
     */
    public static function is_news_forum($cm): bool {
        global $DB;
        if (empty($cm->modname) || $cm->modname !== 'forum') {
            return false;
        }

        // Asked on every forum page a student opens, and a forum's type all but
        // never changes. Stored as 1 or 0, because a cache miss is false.
        $cache = \cache::make('local_unifiedgrader', 'newsforum');
        $cached = $cache->get((int) $cm->id);
        if ($cached !== false) {
            return (bool) $cached;
        }

        $isnews = $DB->get_field('forum', 'type', ['id' => $cm->instance]) === 'news';
        $cache->set((int) $cm->id, (int) $isnews);
        return $isnews;
    }

    /**
     * Forget the cached forum type of an activity. Called when its settings are saved.
     *
     * @param int $cmid Course module ID.
     */
    public static function invalidate(int $cmid): void {
        \cache::make('local_unifiedgrader', 'newsforum')->delete($cmid);
    }
}
