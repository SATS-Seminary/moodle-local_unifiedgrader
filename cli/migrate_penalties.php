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
 * Run the Moodle 5.3 penalty migration now, instead of waiting for cron.
 *
 * Saves the late penalty switch quizaccess_duedate held for each quiz. Run it
 * after upgrading to Moodle 5.3 and before uninstalling quizaccess_duedate.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(['help' => false, 'force' => false], ['h' => 'help', 'f' => 'force']);

if ($options['help']) {
    echo "Save quizaccess_duedate's per-quiz late penalty switch into Unified Grader (Moodle 5.3+).

Options:
-f, --force   Run again even if the migration has already run.
-h, --help    Print this help.
";
    exit(0);
}

if (!\local_unifiedgrader\penalty\compat::unified()) {
    cli_error('This site does not run Moodle 5.3 or later; nothing to migrate.');
}

if ($options['force']) {
    unset_config(\local_unifiedgrader\task\migrate_penalties::DONE_FLAG, 'local_unifiedgrader');
}

(new \local_unifiedgrader\task\migrate_penalties())->execute();
