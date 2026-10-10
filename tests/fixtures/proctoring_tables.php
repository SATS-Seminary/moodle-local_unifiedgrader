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

namespace local_unifiedgrader\proctoring;

/**
 * Stand-ins for the quiz access rule plugins' tables, where the test site does not have them.
 *
 * Only the columns the providers read are created. Where a plugin really is
 * installed, its own tables are used and left alone.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class proctoring_tables {
    /** @var array[] Table => [column => [type, length]]; every table also has id. */
    const TABLES = [
        'quizaccess_quilgo_settings' => [
            'quizid' => [XMLDB_TYPE_INTEGER, '10'],
            'proctoring_enabled' => [XMLDB_TYPE_INTEGER, '1'],
        ],
        'quizaccess_quilgo_reports' => [
            'attemptid' => [XMLDB_TYPE_INTEGER, '10'],
            'plasmsessionid' => [XMLDB_TYPE_CHAR, '100'],
            'generate_status' => [XMLDB_TYPE_INTEGER, '2'],
            'stat' => [XMLDB_TYPE_TEXT, null],
            'proctoring_enabled' => [XMLDB_TYPE_INTEGER, '1'],
        ],
        'quizaccess_quizproctoring' => [
            'quizid' => [XMLDB_TYPE_INTEGER, '10'],
            'enableproctoring' => [XMLDB_TYPE_INTEGER, '1'],
        ],
        'quizaccess_quizproctoring_ma' => [
            'userid' => [XMLDB_TYPE_INTEGER, '10'],
            'quizid' => [XMLDB_TYPE_INTEGER, '10'],
            'attemptid' => [XMLDB_TYPE_INTEGER, '10'],
            'deleted' => [XMLDB_TYPE_INTEGER, '1'],
            'status' => [XMLDB_TYPE_CHAR, '100'],
        ],
        'quizaccess_quizproctoring_da' => [
            'userid' => [XMLDB_TYPE_INTEGER, '10'],
            'quizid' => [XMLDB_TYPE_INTEGER, '10'],
            'attemptid' => [XMLDB_TYPE_INTEGER, '10'],
            'deleted' => [XMLDB_TYPE_INTEGER, '1'],
            'status' => [XMLDB_TYPE_CHAR, '100'],
        ],
        'quizaccess_autoproctor' => [
            'quiz_id' => [XMLDB_TYPE_INTEGER, '10'],
            'proctoring_enabled' => [XMLDB_TYPE_INTEGER, '1'],
        ],
        'quizaccess_autoproctor_sessions' => [
            'quiz_id' => [XMLDB_TYPE_INTEGER, '10'],
            'quiz_attempt_id' => [XMLDB_TYPE_INTEGER, '10'],
            'test_attempt_id' => [XMLDB_TYPE_CHAR, '64'],
        ],
    ];

    /** @var string[] The tables this class created, and so may drop. */
    private static array $created = [];

    /**
     * Create a stand-in for each table the site lacks.
     */
    public static function create(): void {
        global $DB;
        $dbman = $DB->get_manager();
        foreach (self::TABLES as $name => $columns) {
            $table = new \xmldb_table($name);
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            foreach ($columns as $column => [$type, $length]) {
                $notnull = $type === XMLDB_TYPE_INTEGER ? XMLDB_NOTNULL : null;
                $table->add_field($column, $type, $length, null, $notnull, null, $type === XMLDB_TYPE_INTEGER ? '0' : null);
            }
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            if (!$dbman->table_exists($table)) {
                $dbman->create_table($table);
                self::$created[] = $name;
            }
        }
    }

    /**
     * Drop the stand-ins again: a test's reset does not undo a created table.
     */
    public static function drop(): void {
        global $DB;
        $dbman = $DB->get_manager();
        foreach (self::$created as $name) {
            $table = new \xmldb_table($name);
            if ($dbman->table_exists($table)) {
                $dbman->drop_table($table);
            }
        }
        self::$created = [];
    }

    /**
     * Insert a row, filling in whatever else a real plugin's table insists on.
     *
     * @param string $table Table name.
     * @param array $row The columns the test cares about.
     * @return int The new row's ID.
     */
    public static function insert(string $table, array $row): int {
        global $DB;
        foreach ($DB->get_columns($table) as $name => $column) {
            if ($name !== 'id' && !array_key_exists($name, $row) && $column->not_null && !$column->has_default) {
                $row[$name] = in_array($column->meta_type, ['I', 'N', 'F'], true) ? 0 : '';
            }
        }
        return (int) $DB->insert_record($table, $row);
    }
}
