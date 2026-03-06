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
 * Database upgrade steps for local_unifiedgrader.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade the plugin database schema.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool
 */
function xmldb_local_unifiedgrader_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026022006) {

        // 1. Create local_unifiedgrader_clib table.
        $table = new xmldb_table('local_unifiedgrader_clib');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('coursecode', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('content', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('shared', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('ix_userid_coursecode', XMLDB_INDEX_NOTUNIQUE, ['userid', 'coursecode']);
        $table->add_index('ix_shared', XMLDB_INDEX_NOTUNIQUE, ['shared']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        } else {
            // Table may exist from a failed prior upgrade with CHAR(50).
            // Drop the index first (XMLDB won't alter a column with a dependent index),
            // widen the column, then re-add the index.
            $index = new xmldb_index('ix_userid_coursecode', XMLDB_INDEX_NOTUNIQUE, ['userid', 'coursecode']);
            if ($dbman->index_exists($table, $index)) {
                $dbman->drop_index($table, $index);
            }
            $field = new xmldb_field('coursecode', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
            $dbman->change_field_precision($table, $field);
            if (!$dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        // 2. Create local_unifiedgrader_cltag table.
        $table = new xmldb_table('local_unifiedgrader_cltag');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('ix_userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // 3. Create local_unifiedgrader_clmap table.
        $table = new xmldb_table('local_unifiedgrader_clmap');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('commentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('tagid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('ix_commentid', XMLDB_INDEX_NOTUNIQUE, ['commentid']);
        $table->add_index('ix_tagid', XMLDB_INDEX_NOTUNIQUE, ['tagid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // 4. Create system-default tags (userid = 0).
        $now = time();
        $defaulttags = ['Intro', 'Referencing', 'Content', 'Argumentation', 'Presentation'];
        foreach ($defaulttags as $order => $tagname) {
            if (!$DB->record_exists('local_unifiedgrader_cltag', ['userid' => 0, 'name' => $tagname])) {
                $DB->insert_record('local_unifiedgrader_cltag', (object) [
                    'userid' => 0,
                    'name' => $tagname,
                    'sortorder' => $order,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ]);
            }
        }

        // 5. Migrate data from old local_unifiedgrader_comments to new clib table.
        if ($dbman->table_exists('local_unifiedgrader_comments')) {
            // Clear any partially migrated data from a failed prior attempt.
            $DB->delete_records('local_unifiedgrader_clib');

            $oldcomments = $DB->get_records('local_unifiedgrader_comments');
            $regex = get_config('local_unifiedgrader', 'coursecode_regex');

            foreach ($oldcomments as $old) {
                // Convert courseid to coursecode.
                $coursecode = '';
                if (!empty($old->courseid)) {
                    $shortname = $DB->get_field('course', 'shortname', ['id' => $old->courseid]);
                    if ($shortname !== false) {
                        if (!empty($regex) && @preg_match($regex, $shortname, $matches)) {
                            $coursecode = $matches[1] ?? $matches[0];
                        } else {
                            $coursecode = $shortname;
                        }
                    }
                }

                $DB->insert_record('local_unifiedgrader_clib', (object) [
                    'userid' => $old->userid,
                    'coursecode' => $coursecode,
                    'content' => $old->content,
                    'shared' => 0,
                    'sortorder' => $old->sortorder,
                    'timecreated' => $old->timecreated,
                    'timemodified' => $old->timemodified,
                ]);
            }
        }

        upgrade_plugin_savepoint(true, 2026022006, 'local', 'unifiedgrader');
    }

    if ($oldversion < 2026022502) {

        // Create local_unifiedgrader_penalty table.
        $table = new xmldb_table('local_unifiedgrader_penalty');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('authorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('category', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('label', XMLDB_TYPE_CHAR, '15', null, XMLDB_NOTNULL, null, '');
        $table->add_field('percentage', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('ix_cmid_userid', XMLDB_INDEX_NOTUNIQUE, ['cmid', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026022502, 'local', 'unifiedgrader');
    }

    if ($oldversion < 2026022701) {

        // Create local_unifiedgrader_fext table for forum due date extensions.
        $table = new xmldb_table('local_unifiedgrader_fext');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('extensionduedate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('authorid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('uix_cmid_userid', XMLDB_INDEX_UNIQUE, ['cmid', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026022701, 'local', 'unifiedgrader');
    }

    if ($oldversion < 2026022702) {

        // Create local_unifiedgrader_qfb table for per-attempt quiz feedback.
        $table = new xmldb_table('local_unifiedgrader_qfb');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('attemptnumber', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('feedback', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('feedbackformat', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('grader', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('uix_cmid_userid_attempt', XMLDB_INDEX_UNIQUE, ['cmid', 'userid', 'attemptnumber']);
        $table->add_index('ix_cmid_userid', XMLDB_INDEX_NOTUNIQUE, ['cmid', 'userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026022702, 'local', 'unifiedgrader');
    }

    return true;
}
