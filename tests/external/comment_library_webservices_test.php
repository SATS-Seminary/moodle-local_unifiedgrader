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

namespace local_unifiedgrader\external;

use core_external\external_api;

/**
 * Tests for comment library web services.
 *
 * Tests the 8 comment library web services that operate in CONTEXT_SYSTEM:
 * get_library_comments, save_library_comment, delete_library_comment,
 * get_library_tags, save_library_tag, delete_library_tag,
 * get_shared_library, import_shared_comment.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_unifiedgrader\external\get_library_comments
 * @covers \local_unifiedgrader\external\save_library_comment
 * @covers \local_unifiedgrader\external\delete_library_comment
 * @covers \local_unifiedgrader\external\get_library_tags
 * @covers \local_unifiedgrader\external\save_library_tag
 * @covers \local_unifiedgrader\external\delete_library_tag
 * @covers \local_unifiedgrader\external\get_shared_library
 * @covers \local_unifiedgrader\external\import_shared_comment
 */
final class comment_library_webservices_test extends \advanced_testcase {
    // Get_library_comments tests.

    /**
     * Test get_library_comments returns comments for the logged-in user.
     */
    public function test_get_library_comments_happy_path(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $plugingen->create_library_comment(['userid' => $user->id, 'coursecode' => 'BIB101', 'content' => 'Good analysis']);
        $plugingen->create_library_comment(['userid' => $user->id, 'coursecode' => 'BIB201', 'content' => 'Needs more detail']);

        $result = get_library_comments::execute();

        $this->assertCount(2, $result);
        $contents = array_column($result, 'content');
        $this->assertContains('Good analysis', $contents);
        $this->assertContains('Needs more detail', $contents);
    }

    /**
     * Test get_library_comments return value passes validation.
     */
    public function test_get_library_comments_return_validation(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $plugingen->create_library_comment(['userid' => $user->id, 'content' => 'Test comment']);

        $result = get_library_comments::execute();
        $cleaned = external_api::clean_returnvalue(get_library_comments::execute_returns(), $result);

        $this->assertCount(1, $cleaned);
        $this->assertArrayHasKey('id', $cleaned[0]);
        $this->assertArrayHasKey('userid', $cleaned[0]);
        $this->assertArrayHasKey('coursecode', $cleaned[0]);
        $this->assertArrayHasKey('content', $cleaned[0]);
        $this->assertArrayHasKey('shared', $cleaned[0]);
        $this->assertArrayHasKey('sortorder', $cleaned[0]);
        $this->assertArrayHasKey('timecreated', $cleaned[0]);
        $this->assertArrayHasKey('timemodified', $cleaned[0]);
        $this->assertArrayHasKey('tagids', $cleaned[0]);
    }

    /**
     * Test get_library_comments with coursecode filter.
     */
    public function test_get_library_comments_filter_by_coursecode(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $plugingen->create_library_comment(['userid' => $user->id, 'coursecode' => 'BIB101', 'content' => 'Match']);
        $plugingen->create_library_comment(['userid' => $user->id, 'coursecode' => 'BIB201', 'content' => 'No match']);

        $result = get_library_comments::execute('BIB101');

        $this->assertCount(1, $result);
        $this->assertEquals('Match', $result[0]['content']);
    }

    /**
     * Test get_library_comments with tag filter.
     */
    public function test_get_library_comments_filter_by_tag(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $c1 = $plugingen->create_library_comment(['userid' => $user->id, 'content' => 'Tagged']);
        $plugingen->create_library_comment(['userid' => $user->id, 'content' => 'Untagged']);
        $tag = $plugingen->create_library_tag(['userid' => $user->id, 'name' => 'Grammar']);
        $plugingen->create_tag_mapping(['commentid' => $c1->id, 'tagid' => $tag->id]);

        $result = get_library_comments::execute('', (int) $tag->id);

        $this->assertCount(1, $result);
        $this->assertEquals('Tagged', $result[0]['content']);
    }

    /**
     * Test get_library_comments returns empty for user with no comments.
     */
    public function test_get_library_comments_empty(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $result = get_library_comments::execute();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test get_library_comments does not return other users' comments.
     */
    public function test_get_library_comments_excludes_other_users(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $plugingen->create_library_comment(['userid' => $user1->id, 'content' => 'User1 comment']);
        $plugingen->create_library_comment(['userid' => $user2->id, 'content' => 'User2 comment']);

        $this->setUser($user2);
        $result = get_library_comments::execute();

        $this->assertCount(1, $result);
        $this->assertEquals('User2 comment', $result[0]['content']);
    }

    // Save_library_comment tests.

    /**
     * Test save_library_comment creates a new comment.
     */
    public function test_save_library_comment_create(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $result = save_library_comment::execute('BIB101', 'Great work on exegesis');

        $this->assertArrayHasKey('commentid', $result);
        $this->assertGreaterThan(0, $result['commentid']);

        // Verify the comment was saved.
        $comments = get_library_comments::execute('BIB101');
        $this->assertCount(1, $comments);
        $this->assertEquals('Great work on exegesis', $comments[0]['content']);
    }

    /**
     * Test save_library_comment return value passes validation.
     */
    public function test_save_library_comment_return_validation(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $result = save_library_comment::execute('BIB101', 'Test content');
        $cleaned = external_api::clean_returnvalue(save_library_comment::execute_returns(), $result);

        $this->assertArrayHasKey('commentid', $cleaned);
        $this->assertIsInt($cleaned['commentid']);
    }

    /**
     * Test save_library_comment updates an existing comment.
     */
    public function test_save_library_comment_update(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $result = save_library_comment::execute('BIB101', 'Original');
        $commentid = $result['commentid'];

        save_library_comment::execute('BIB201', 'Updated', [], 1, $commentid);

        $comments = get_library_comments::execute();
        $this->assertCount(1, $comments);
        $this->assertEquals('Updated', $comments[0]['content']);
        $this->assertEquals('BIB201', $comments[0]['coursecode']);
        $this->assertEquals(1, $comments[0]['shared']);
    }

    /**
     * Test save_library_comment with tag IDs.
     */
    public function test_save_library_comment_with_tags(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $tag1 = $plugingen->create_library_tag(['userid' => $user->id, 'name' => 'Grammar']);
        $tag2 = $plugingen->create_library_tag(['userid' => $user->id, 'name' => 'Content']);

        $result = save_library_comment::execute('BIB101', 'Tagged comment', [(int) $tag1->id, (int) $tag2->id]);

        $comments = get_library_comments::execute();
        $this->assertCount(1, $comments);
        $this->assertCount(2, $comments[0]['tagids']);
        $this->assertContains((int) $tag1->id, $comments[0]['tagids']);
        $this->assertContains((int) $tag2->id, $comments[0]['tagids']);
    }

    // Delete_library_comment tests.

    /**
     * Test delete_library_comment removes a comment.
     */
    public function test_delete_library_comment_happy_path(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $comment = $plugingen->create_library_comment(['userid' => $user->id, 'content' => 'To delete']);

        $result = delete_library_comment::execute((int) $comment->id);
        $this->assertTrue($result['success']);

        $comments = get_library_comments::execute();
        $this->assertEmpty($comments);
    }

    /**
     * Test delete_library_comment return value passes validation.
     */
    public function test_delete_library_comment_return_validation(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $comment = $plugingen->create_library_comment(['userid' => $user->id, 'content' => 'To delete']);

        $result = delete_library_comment::execute((int) $comment->id);
        $cleaned = external_api::clean_returnvalue(delete_library_comment::execute_returns(), $result);

        $this->assertArrayHasKey('success', $cleaned);
        $this->assertTrue($cleaned['success']);
    }

    /**
     * Test delete_library_comment for another user's comment has no effect.
     */
    public function test_delete_library_comment_wrong_owner(): void {
        global $DB;
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $comment = $plugingen->create_library_comment(['userid' => $user1->id, 'content' => 'Protected']);

        // Login as user2 and try to delete user1's comment.
        $this->setUser($user2);
        delete_library_comment::execute((int) $comment->id);

        // Comment should still exist.
        $this->assertTrue($DB->record_exists('local_unifiedgrader_clib', ['id' => $comment->id]));
    }

    // Get_library_tags tests.

    /**
     * Test get_library_tags returns personal and system tags.
     */
    public function test_get_library_tags_happy_path(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $plugingen->create_library_tag(['userid' => 0, 'name' => 'System Tag']);
        $plugingen->create_library_tag(['userid' => $user->id, 'name' => 'Personal Tag']);

        $result = get_library_tags::execute();

        $this->assertCount(2, $result);
        $names = array_column($result, 'name');
        $this->assertContains('System Tag', $names);
        $this->assertContains('Personal Tag', $names);
    }

    /**
     * Test get_library_tags return value passes validation.
     */
    public function test_get_library_tags_return_validation(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $plugingen->create_library_tag(['userid' => $user->id, 'name' => 'Test']);

        $result = get_library_tags::execute();
        $cleaned = external_api::clean_returnvalue(get_library_tags::execute_returns(), $result);

        $this->assertCount(1, $cleaned);
        $this->assertArrayHasKey('id', $cleaned[0]);
        $this->assertArrayHasKey('userid', $cleaned[0]);
        $this->assertArrayHasKey('name', $cleaned[0]);
        $this->assertArrayHasKey('sortorder', $cleaned[0]);
        $this->assertArrayHasKey('issystem', $cleaned[0]);
    }

    /**
     * Test get_library_tags correctly marks system tags with issystem flag.
     */
    public function test_get_library_tags_issystem_flag(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $plugingen->create_library_tag(['userid' => 0, 'name' => 'System']);
        $plugingen->create_library_tag(['userid' => $user->id, 'name' => 'Personal']);

        $result = get_library_tags::execute();

        $systemtag = array_values(array_filter($result, fn($t) => $t['name'] === 'System'));
        $personaltag = array_values(array_filter($result, fn($t) => $t['name'] === 'Personal'));

        $this->assertTrue($systemtag[0]['issystem']);
        $this->assertFalse($personaltag[0]['issystem']);
    }

    /**
     * Test get_library_tags excludes other users' personal tags.
     */
    public function test_get_library_tags_excludes_other_users(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $plugingen->create_library_tag(['userid' => $user1->id, 'name' => 'User1 Tag']);
        $plugingen->create_library_tag(['userid' => $user2->id, 'name' => 'User2 Tag']);

        $this->setUser($user2);
        $result = get_library_tags::execute();

        $this->assertCount(1, $result);
        $this->assertEquals('User2 Tag', $result[0]['name']);
    }

    // Save_library_tag tests.

    /**
     * Test save_library_tag creates a new tag.
     */
    public function test_save_library_tag_create(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $result = save_library_tag::execute('Grammar');

        $this->assertArrayHasKey('tagid', $result);
        $this->assertGreaterThan(0, $result['tagid']);

        $tags = get_library_tags::execute();
        $this->assertCount(1, $tags);
        $this->assertEquals('Grammar', $tags[0]['name']);
    }

    /**
     * Test save_library_tag return value passes validation.
     */
    public function test_save_library_tag_return_validation(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $result = save_library_tag::execute('Content');
        $cleaned = external_api::clean_returnvalue(save_library_tag::execute_returns(), $result);

        $this->assertArrayHasKey('tagid', $cleaned);
        $this->assertIsInt($cleaned['tagid']);
    }

    /**
     * Test save_library_tag updates an existing tag.
     */
    public function test_save_library_tag_update(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $result = save_library_tag::execute('Original');
        $tagid = $result['tagid'];

        save_library_tag::execute('Renamed', $tagid);

        $tags = get_library_tags::execute();
        $this->assertCount(1, $tags);
        $this->assertEquals('Renamed', $tags[0]['name']);
    }

    // Delete_library_tag tests.

    /**
     * Test delete_library_tag removes a personal tag.
     */
    public function test_delete_library_tag_happy_path(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $tag = $plugingen->create_library_tag(['userid' => $user->id, 'name' => 'ToDelete']);

        $result = delete_library_tag::execute((int) $tag->id);
        $this->assertTrue($result['success']);

        $tags = get_library_tags::execute();
        $this->assertEmpty($tags);
    }

    /**
     * Test delete_library_tag return value passes validation.
     */
    public function test_delete_library_tag_return_validation(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $tag = $plugingen->create_library_tag(['userid' => $user->id, 'name' => 'ToDelete']);

        $result = delete_library_tag::execute((int) $tag->id);
        $cleaned = external_api::clean_returnvalue(delete_library_tag::execute_returns(), $result);

        $this->assertArrayHasKey('success', $cleaned);
        $this->assertTrue($cleaned['success']);
    }

    /**
     * Test delete_library_tag for system tag (userid=0) throws exception.
     */
    public function test_delete_library_tag_system_tag_throws(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $systag = $plugingen->create_library_tag(['userid' => 0, 'name' => 'System']);

        $this->expectException(\moodle_exception::class);
        delete_library_tag::execute((int) $systag->id);
    }

    /**
     * Test delete_library_tag for another user's tag throws exception.
     */
    public function test_delete_library_tag_wrong_owner_throws(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $tag = $plugingen->create_library_tag(['userid' => $user1->id, 'name' => 'Private']);

        $this->setUser($user2);

        $this->expectException(\moodle_exception::class);
        delete_library_tag::execute((int) $tag->id);
    }

    // Get_shared_library tests.

    /**
     * Test get_shared_library returns shared comments from other users.
     */
    public function test_get_shared_library_happy_path(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user(['firstname' => 'Alice', 'lastname' => 'Smith']);

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $plugingen->create_library_comment(['userid' => $user2->id, 'content' => 'Shared by Alice', 'shared' => 1]);

        $this->setUser($user1);
        $result = get_shared_library::execute();

        $this->assertCount(1, $result);
        $this->assertEquals('Shared by Alice', $result[0]['content']);
        $this->assertArrayHasKey('ownername', $result[0]);
        $this->assertStringContainsString('Alice', $result[0]['ownername']);
    }

    /**
     * Test get_shared_library return value passes validation.
     */
    public function test_get_shared_library_return_validation(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $plugingen->create_library_comment(['userid' => $user2->id, 'content' => 'Shared', 'shared' => 1]);

        $this->setUser($user1);
        $result = get_shared_library::execute();
        $cleaned = external_api::clean_returnvalue(get_shared_library::execute_returns(), $result);

        $this->assertCount(1, $cleaned);
        $this->assertArrayHasKey('id', $cleaned[0]);
        $this->assertArrayHasKey('userid', $cleaned[0]);
        $this->assertArrayHasKey('content', $cleaned[0]);
        $this->assertArrayHasKey('ownername', $cleaned[0]);
        $this->assertArrayHasKey('tagids', $cleaned[0]);
    }

    /**
     * Test get_shared_library excludes own comments and non-shared comments.
     */
    public function test_get_shared_library_excludes_own_and_private(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        // Own shared comment should not appear.
        $plugingen->create_library_comment(['userid' => $user1->id, 'content' => 'My shared', 'shared' => 1]);
        // Other user's private comment should not appear.
        $plugingen->create_library_comment(['userid' => $user2->id, 'content' => 'Other private', 'shared' => 0]);
        // Other user's shared comment should appear.
        $plugingen->create_library_comment(['userid' => $user2->id, 'content' => 'Other shared', 'shared' => 1]);

        $this->setUser($user1);
        $result = get_shared_library::execute();

        $this->assertCount(1, $result);
        $this->assertEquals('Other shared', $result[0]['content']);
    }

    // Import_shared_comment tests.

    /**
     * Test import_shared_comment copies a shared comment into own library.
     */
    public function test_import_shared_comment_happy_path(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $source = $plugingen->create_library_comment([
            'userid' => $user1->id,
            'coursecode' => 'BIB101',
            'content' => 'Shared wisdom',
            'shared' => 1,
        ]);

        $this->setUser($user2);
        $result = import_shared_comment::execute((int) $source->id, 'BIB201');

        $this->assertArrayHasKey('commentid', $result);
        $this->assertGreaterThan(0, $result['commentid']);
        $this->assertNotEquals((int) $source->id, $result['commentid']);

        // Verify the imported comment is in user2's library.
        $comments = get_library_comments::execute('BIB201');
        $this->assertCount(1, $comments);
        $this->assertEquals('Shared wisdom', $comments[0]['content']);
        $this->assertEquals('BIB201', $comments[0]['coursecode']);
        // Imported as private.
        $this->assertEquals(0, $comments[0]['shared']);
    }

    /**
     * Test import_shared_comment return value passes validation.
     */
    public function test_import_shared_comment_return_validation(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $source = $plugingen->create_library_comment([
            'userid' => $user1->id,
            'content' => 'Shared',
            'shared' => 1,
        ]);

        $this->setUser($user2);
        $result = import_shared_comment::execute((int) $source->id, '');
        $cleaned = external_api::clean_returnvalue(import_shared_comment::execute_returns(), $result);

        $this->assertArrayHasKey('commentid', $cleaned);
        $this->assertIsInt($cleaned['commentid']);
    }

    /**
     * Test import_shared_comment for a non-shared comment throws exception.
     */
    public function test_import_shared_comment_nonshared_throws(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $private = $plugingen->create_library_comment([
            'userid' => $user1->id,
            'content' => 'Private comment',
            'shared' => 0,
        ]);

        $this->setUser($user2);

        $this->expectException(\dml_missing_record_exception::class);
        import_shared_comment::execute((int) $private->id, 'BIB101');
    }

    /**
     * Test import_shared_comment preserves tag mappings from the source.
     */
    public function test_import_shared_comment_preserves_tags(): void {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $plugingen = $this->getDataGenerator()->get_plugin_generator('local_unifiedgrader');
        $source = $plugingen->create_library_comment([
            'userid' => $user1->id,
            'content' => 'Tagged shared',
            'shared' => 1,
        ]);
        $tag = $plugingen->create_library_tag(['userid' => $user1->id, 'name' => 'Grammar']);
        $plugingen->create_tag_mapping(['commentid' => $source->id, 'tagid' => $tag->id]);

        $this->setUser($user2);
        $result = import_shared_comment::execute((int) $source->id, '');

        // Verify the imported comment has the tag mapping.
        $comments = get_library_comments::execute();
        $imported = array_values(array_filter($comments, fn($c) => $c['id'] === $result['commentid']));
        $this->assertCount(1, $imported);
        $this->assertContains((int) $tag->id, $imported[0]['tagids']);
    }
}
