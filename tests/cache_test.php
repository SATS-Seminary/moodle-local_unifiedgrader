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

namespace local_unifiedgrader;

use local_unifiedgrader\penalty\activity_settings;

/**
 * Tests for the plugin's caches: that a cached read costs no query, and that
 * everything able to change the answer clears it.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\penalty\activity_settings
 * @covers \local_unifiedgrader\forum_helper
 * @covers \local_unifiedgrader\preferences_manager
 * @covers \local_unifiedgrader\observer::handle_course_module_updated
 */
final class cache_test extends \advanced_testcase {
    /**
     * Create a forum of the given type and return its course module.
     *
     * @param string $type Forum type.
     * @return \stdClass Course module record, with modname and instance.
     */
    private function create_forum(string $type = 'general'): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        $forum = $this->getDataGenerator()->create_module('forum', ['course' => $course->id, 'type' => $type]);
        return get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
    }

    /**
     * The late penalty switch is read once, then served from the cache.
     */
    public function test_penalty_switch_is_cached(): void {
        global $DB;
        $this->resetAfterTest();
        $cm = $this->create_forum();

        // A forum without a saved value is on by default.
        $this->assertTrue(activity_settings::is_enabled($cm));

        $reads = $DB->perf_get_reads();
        $this->assertTrue(activity_settings::is_enabled($cm));
        $this->assertSame($reads, $DB->perf_get_reads());
    }

    /**
     * Saving and deleting the switch both clear the cached answer.
     */
    public function test_penalty_switch_cache_follows_saved_value(): void {
        $this->resetAfterTest();
        $cm = $this->create_forum();

        $this->assertTrue(activity_settings::is_enabled($cm));

        activity_settings::set_enabled((int) $cm->id, false);
        $this->assertFalse(activity_settings::is_enabled($cm));

        activity_settings::set_enabled((int) $cm->id, true);
        $this->assertTrue(activity_settings::is_enabled($cm));

        activity_settings::set_enabled((int) $cm->id, false);
        $this->assertFalse(activity_settings::is_enabled($cm));
        activity_settings::delete((int) $cm->id);
        $this->assertTrue(activity_settings::is_enabled($cm));
    }

    /**
     * Saving an activity's settings clears what is cached about it.
     *
     * The default for an activity without a saved value is read from the
     * activity's own settings, which change without this plugin being told
     * anything except through this event.
     */
    public function test_course_module_updated_clears_activity_caches(): void {
        global $DB;
        $this->resetAfterTest();
        $cm = $this->create_forum();

        activity_settings::set_enabled((int) $cm->id, false);
        $this->assertFalse(activity_settings::is_enabled($cm));
        $this->assertFalse(forum_helper::is_news_forum($cm));

        // Change both behind the caches' backs: they still give the old answers.
        $DB->set_field('local_unifiedgrader_penset', 'enabled', 1, ['cmid' => $cm->id]);
        $DB->set_field('forum', 'type', 'news', ['id' => $cm->instance]);
        $this->assertFalse(activity_settings::is_enabled($cm));
        $this->assertFalse(forum_helper::is_news_forum($cm));

        \core\event\course_module_updated::create_from_cm($cm)->trigger();

        $this->assertTrue(activity_settings::is_enabled($cm));
        $this->assertTrue(forum_helper::is_news_forum($cm));
    }

    /**
     * The news forum check is read once, then served from the cache.
     */
    public function test_news_forum_is_cached(): void {
        global $DB;
        $this->resetAfterTest();
        $news = $this->create_forum('news');
        $general = $this->create_forum();

        $this->assertTrue(forum_helper::is_news_forum($news));
        $this->assertFalse(forum_helper::is_news_forum($general));

        $reads = $DB->perf_get_reads();
        $this->assertTrue(forum_helper::is_news_forum($news));
        $this->assertFalse(forum_helper::is_news_forum($general));
        $this->assertSame($reads, $DB->perf_get_reads());
    }

    /**
     * Preferences are read once, and a write keeps the cache in step.
     */
    public function test_preferences_are_cached_and_follow_writes(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        // A user without a row is cached too.
        $this->assertSame([], preferences_manager::get_all((int) $user->id));
        $reads = $DB->perf_get_reads();
        $this->assertSame([], preferences_manager::get_all((int) $user->id));
        $this->assertSame($reads, $DB->perf_get_reads());

        preferences_manager::set((int) $user->id, 'forumview_1', 'paged');
        preferences_manager::set((int) $user->id, 'groupfilter.1', '0');

        $reads = $DB->perf_get_reads();
        $this->assertSame('paged', preferences_manager::get((int) $user->id, 'forumview_1'));
        $this->assertSame('0', preferences_manager::get((int) $user->id, 'groupfilter.1'));
        $this->assertSame($reads, $DB->perf_get_reads());

        // What was cached is what was stored.
        $stored = $DB->get_field('local_unifiedgrader_prefs', 'preferences', ['userid' => $user->id]);
        $this->assertSame(json_decode($stored, true), preferences_manager::get_all((int) $user->id));
    }

    /**
     * Deleting a user's data through the privacy API clears their cached preferences.
     */
    public function test_privacy_delete_clears_cached_preferences(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        preferences_manager::set((int) $user->id, 'forumview_1', 'paged');
        $this->assertSame('paged', preferences_manager::get((int) $user->id, 'forumview_1'));

        $contextlist = new \core_privacy\local\request\approved_contextlist(
            $user,
            'local_unifiedgrader',
            [\context_user::instance($user->id)->id],
        );
        privacy\provider::delete_data_for_user($contextlist);

        $this->assertNull(preferences_manager::get((int) $user->id, 'forumview_1'));
    }
}
