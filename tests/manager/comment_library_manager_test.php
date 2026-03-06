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

/**
 * Tests for the comment_library_manager class.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\comment_library_manager
 */
final class comment_library_manager_test extends \advanced_testcase {

    /**
     * Test creating a new comment.
     */
    public function test_create_comment(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $id = comment_library_manager::save_comment($user->id, 'BIB101', 'Good analysis', [], 0, 0);

        $this->assertGreaterThan(0, $id);
        $record = $DB->get_record('local_unifiedgrader_clib', ['id' => $id]);
        $this->assertEquals($user->id, $record->userid);
        $this->assertEquals('BIB101', $record->coursecode);
        $this->assertEquals('Good analysis', $record->content);
        $this->assertEquals(0, $record->shared);
    }

    /**
     * Test updating an existing comment.
     */
    public function test_update_comment(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $id = comment_library_manager::save_comment($user->id, 'BIB101', 'Original');
        comment_library_manager::save_comment($user->id, 'BIB201', 'Updated', [], 1, $id);

        $comments = comment_library_manager::get_comments($user->id);
        $this->assertCount(1, $comments);
        $this->assertEquals('Updated', $comments[0]['content']);
        $this->assertEquals('BIB201', $comments[0]['coursecode']);
        $this->assertEquals(1, $comments[0]['shared']);
    }

    /**
     * Test that updating enforces ownership.
     */
    public function test_update_comment_enforces_ownership(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $id = comment_library_manager::save_comment($user1->id, 'BIB101', 'User1 comment');

        $this->expectException(\dml_missing_record_exception::class);
        comment_library_manager::save_comment($user2->id, 'BIB101', 'Hijack', [], 0, $id);
    }

    /**
     * Test deleting a comment and its tag mappings.
     */
    public function test_delete_comment(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');

        $id = comment_library_manager::save_comment($user->id, '', 'To delete', []);
        $tag = $plugingen->create_library_tag(['userid' => $user->id, 'name' => 'TestTag']);
        $plugingen->create_tag_mapping(['commentid' => $id, 'tagid' => $tag->id]);

        comment_library_manager::delete_comment($id, $user->id);

        $this->assertFalse($DB->record_exists('local_unifiedgrader_clib', ['id' => $id]));
        $this->assertFalse($DB->record_exists('local_unifiedgrader_clmap', ['commentid' => $id]));
    }

    /**
     * Test deleting another user's comment has no effect.
     */
    public function test_delete_comment_wrong_owner(): void {
        global $DB;
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $id = comment_library_manager::save_comment($user1->id, '', 'Keep me');
        comment_library_manager::delete_comment($id, $user2->id);

        // Comment should still exist.
        $this->assertTrue($DB->record_exists('local_unifiedgrader_clib', ['id' => $id]));
    }

    /**
     * Test getting comments with no filters.
     */
    public function test_get_comments_no_filters(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        comment_library_manager::save_comment($user->id, 'BIB101', 'Comment 1');
        comment_library_manager::save_comment($user->id, 'BIB201', 'Comment 2');

        $comments = comment_library_manager::get_comments($user->id);
        $this->assertCount(2, $comments);
    }

    /**
     * Test filtering comments by coursecode.
     */
    public function test_get_comments_filter_by_coursecode(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        comment_library_manager::save_comment($user->id, 'BIB101', 'Match');
        comment_library_manager::save_comment($user->id, 'BIB201', 'No match');

        $comments = comment_library_manager::get_comments($user->id, 'BIB101');
        $this->assertCount(1, $comments);
        $this->assertEquals('Match', $comments[0]['content']);
    }

    /**
     * Test filtering comments by tag.
     */
    public function test_get_comments_filter_by_tag(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');

        $c1 = comment_library_manager::save_comment($user->id, '', 'Tagged');
        $c2 = comment_library_manager::save_comment($user->id, '', 'Untagged');

        $tag = $plugingen->create_library_tag(['userid' => $user->id, 'name' => 'Grammar']);
        $plugingen->create_tag_mapping(['commentid' => $c1, 'tagid' => $tag->id]);

        $comments = comment_library_manager::get_comments($user->id, '', $tag->id);
        $this->assertCount(1, $comments);
        $this->assertEquals('Tagged', $comments[0]['content']);
    }

    /**
     * Test that returned comments include tag IDs.
     */
    public function test_get_comments_returns_tagids(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');

        $cid = comment_library_manager::save_comment($user->id, '', 'With tags');
        $tag1 = $plugingen->create_library_tag(['userid' => $user->id, 'name' => 'T1']);
        $tag2 = $plugingen->create_library_tag(['userid' => $user->id, 'name' => 'T2']);
        $plugingen->create_tag_mapping(['commentid' => $cid, 'tagid' => $tag1->id]);
        $plugingen->create_tag_mapping(['commentid' => $cid, 'tagid' => $tag2->id]);

        $comments = comment_library_manager::get_comments($user->id);
        $this->assertCount(1, $comments);
        $this->assertIsArray($comments[0]['tagids']);
        $this->assertCount(2, $comments[0]['tagids']);
        $this->assertContains((int) $tag1->id, $comments[0]['tagids']);
        $this->assertContains((int) $tag2->id, $comments[0]['tagids']);
    }

    /**
     * Test creating a tag.
     */
    public function test_create_tag(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $id = comment_library_manager::save_tag($user->id, 'Grammar');

        $this->assertGreaterThan(0, $id);
        $record = $DB->get_record('local_unifiedgrader_cltag', ['id' => $id]);
        $this->assertEquals('Grammar', $record->name);
        $this->assertEquals($user->id, $record->userid);
    }

    /**
     * Test updating a tag.
     */
    public function test_update_tag(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $id = comment_library_manager::save_tag($user->id, 'Original');
        comment_library_manager::save_tag($user->id, 'Renamed', $id);

        $tags = comment_library_manager::get_tags($user->id);
        $found = array_filter($tags, fn($t) => $t['id'] === $id);
        $this->assertCount(1, $found);
        $this->assertEquals('Renamed', reset($found)['name']);
    }

    /**
     * Test deleting a tag and its mappings.
     */
    public function test_delete_tag(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');

        $tag = $plugingen->create_library_tag(['userid' => $user->id, 'name' => 'ToDelete']);
        $cid = comment_library_manager::save_comment($user->id, '', 'Test');
        $plugingen->create_tag_mapping(['commentid' => $cid, 'tagid' => $tag->id]);

        comment_library_manager::delete_tag($tag->id, $user->id);

        $this->assertFalse($DB->record_exists('local_unifiedgrader_cltag', ['id' => $tag->id]));
        $this->assertFalse($DB->record_exists('local_unifiedgrader_clmap', ['tagid' => $tag->id]));
    }

    /**
     * Test that system tags cannot be deleted.
     */
    public function test_delete_system_tag_throws(): void {
        $this->resetAfterTest();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $systag = $plugingen->create_library_tag(['userid' => 0, 'name' => 'System']);

        $user = $this->getDataGenerator()->create_user();

        $this->expectException(\moodle_exception::class);
        comment_library_manager::delete_tag($systag->id, $user->id);
    }

    /**
     * Test that another user's tag cannot be deleted.
     */
    public function test_delete_tag_wrong_owner_throws(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $tag = $plugingen->create_library_tag(['userid' => $user1->id, 'name' => 'Private']);

        $this->expectException(\moodle_exception::class);
        comment_library_manager::delete_tag($tag->id, $user2->id);
    }

    /**
     * Test getting tags includes both system and personal tags.
     */
    public function test_get_tags_includes_system_and_own(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');

        $plugingen->create_library_tag(['userid' => 0, 'name' => 'System Tag']);
        $plugingen->create_library_tag(['userid' => $user->id, 'name' => 'Personal Tag']);

        $tags = comment_library_manager::get_tags($user->id);
        $this->assertCount(2, $tags);

        $names = array_column($tags, 'name');
        $this->assertContains('System Tag', $names);
        $this->assertContains('Personal Tag', $names);

        // Check issystem flag.
        $systemtag = array_values(array_filter($tags, fn($t) => $t['name'] === 'System Tag'));
        $this->assertTrue($systemtag[0]['issystem']);
    }

    /**
     * Test that shared comments exclude own comments.
     */
    public function test_get_shared_comments_excludes_own(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        comment_library_manager::save_comment($user1->id, '', 'My shared', [], 1);
        comment_library_manager::save_comment($user2->id, '', 'Other shared', [], 1);

        $shared = comment_library_manager::get_shared_comments($user1->id);
        $this->assertCount(1, $shared);
        $this->assertEquals('Other shared', $shared[0]['content']);
    }

    /**
     * Test that non-shared comments are excluded from shared list.
     */
    public function test_get_shared_comments_only_shared(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        comment_library_manager::save_comment($user2->id, '', 'Shared one', [], 1);
        comment_library_manager::save_comment($user2->id, '', 'Private one', [], 0);

        $shared = comment_library_manager::get_shared_comments($user1->id);
        $this->assertCount(1, $shared);
        $this->assertEquals('Shared one', $shared[0]['content']);
    }

    /**
     * Test that shared comments include owner name.
     */
    public function test_get_shared_comments_includes_ownername(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'lastname' => 'Smith']);

        comment_library_manager::save_comment($user2->id, '', 'Shared', [], 1);

        $shared = comment_library_manager::get_shared_comments($user1->id);
        $this->assertCount(1, $shared);
        $this->assertArrayHasKey('ownername', $shared[0]);
        $this->assertStringContainsString('Alice', $shared[0]['ownername']);
    }

    /**
     * Test importing a shared comment.
     */
    public function test_import_shared_comment(): void {
        global $DB;
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');

        $sourceid = comment_library_manager::save_comment($user1->id, 'BIB101', 'Shared comment', [], 1);
        $tag = $plugingen->create_library_tag(['userid' => $user1->id, 'name' => 'Tag1']);
        $plugingen->create_tag_mapping(['commentid' => $sourceid, 'tagid' => $tag->id]);

        $newid = comment_library_manager::import_shared_comment($sourceid, $user2->id, 'BIB201');

        $this->assertGreaterThan(0, $newid);
        $this->assertNotEquals($sourceid, $newid);

        $record = $DB->get_record('local_unifiedgrader_clib', ['id' => $newid]);
        $this->assertEquals($user2->id, $record->userid);
        $this->assertEquals('BIB201', $record->coursecode);
        $this->assertEquals('Shared comment', $record->content);
        $this->assertEquals(0, $record->shared); // Imported as private.

        // Check tag mappings were copied.
        $maps = $DB->get_records('local_unifiedgrader_clmap', ['commentid' => $newid]);
        $this->assertCount(1, $maps);
        $this->assertEquals($tag->id, reset($maps)->tagid);
    }

    /**
     * Test importing a non-shared comment throws.
     */
    public function test_import_nonshared_comment_throws(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $id = comment_library_manager::save_comment($user1->id, '', 'Private', [], 0);

        $this->expectException(\dml_missing_record_exception::class);
        comment_library_manager::import_shared_comment($id, $user2->id, '');
    }

    /**
     * Test saving a comment with tag IDs syncs mappings.
     */
    public function test_save_comment_syncs_tags(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');

        $tag1 = $plugingen->create_library_tag(['userid' => $user->id, 'name' => 'T1']);
        $tag2 = $plugingen->create_library_tag(['userid' => $user->id, 'name' => 'T2']);

        $cid = comment_library_manager::save_comment($user->id, '', 'Tagged', [$tag1->id, $tag2->id]);

        $maps = $DB->get_records('local_unifiedgrader_clmap', ['commentid' => $cid]);
        $this->assertCount(2, $maps);

        // Update: remove tag1, keep tag2.
        comment_library_manager::save_comment($user->id, '', 'Re-tagged', [$tag2->id], 0, $cid);

        $maps = $DB->get_records('local_unifiedgrader_clmap', ['commentid' => $cid]);
        $this->assertCount(1, $maps);
        $this->assertEquals($tag2->id, reset($maps)->tagid);
    }
}
