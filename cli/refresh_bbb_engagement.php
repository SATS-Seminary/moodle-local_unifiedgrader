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
 * Back-fill BigBlueButton attendance for existing recordings.
 *
 * The in-page "Refresh attendance" button covers one activity at a time, which
 * is fine going forward but tedious for a site that already has a term of
 * recordings with no attendance recorded against them. Attendance is what the
 * grader filters the session list by, so a recording with none is shown to every
 * student regardless of whether they were in it.
 *
 * Usage:
 *   php cli/refresh_bbb_engagement.php --help
 *   php cli/refresh_bbb_engagement.php --dry-run
 *   php cli/refresh_bbb_engagement.php --courseid=123
 *   php cli/refresh_bbb_engagement.php --cmid=14136
 *   php cli/refresh_bbb_engagement.php --all
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    [
        'help' => false,
        'all' => false,
        'courseid' => 0,
        'cmid' => 0,
        'dry-run' => false,
    ],
    ['h' => 'help'],
);

if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'core_admin', implode(PHP_EOL . '  ', $unrecognised)));
}

$help = <<<EOT
Back-fill BigBlueButton attendance for existing recordings.

Reads each recording's attendance from the BigBlueButton server and caches it,
so the Unified Grader can show a student only the sessions they were in.

Options:
  -h, --help        Print this help.
      --all         Every BigBlueButton activity on the site.
      --courseid=N  Every BigBlueButton activity in one course.
      --cmid=N      A single activity, by course module id.
      --dry-run     List what would be processed, fetch nothing.

Exactly one of --all, --courseid or --cmid is required.

Example:
  php cli/refresh_bbb_engagement.php --courseid=123

EOT;

if (!empty($options['help'])) {
    cli_writeln($help);
    exit(0);
}

$selectors = (int) (bool) $options['all']
    + (int) ((int) $options['courseid'] > 0)
    + (int) ((int) $options['cmid'] > 0);
if ($selectors !== 1) {
    cli_writeln($help);
    cli_error('Specify exactly one of --all, --courseid or --cmid.');
}

// Resolve the activities to visit.
$modules = [];
if ((int) $options['cmid'] > 0) {
    $cm = get_coursemodule_from_id('bigbluebuttonbn', (int) $options['cmid'], 0, false, IGNORE_MISSING);
    if (!$cm) {
        cli_error('No BigBlueButton activity with course module id ' . (int) $options['cmid'] . '.');
    }
    $modules[] = $cm;
} else {
    $params = ['modname' => 'bigbluebuttonbn'];
    $where = 'm.name = :modname';
    if ((int) $options['courseid'] > 0) {
        $where .= ' AND cm.course = :courseid';
        $params['courseid'] = (int) $options['courseid'];
    }
    $modules = $DB->get_records_sql(
        "SELECT cm.id, cm.course, cm.instance
           FROM {course_modules} cm
           JOIN {modules} m ON m.id = cm.module
          WHERE {$where}
       ORDER BY cm.course, cm.id",
        $params,
    );
}

if (!$modules) {
    cli_writeln('No BigBlueButton activities matched.');
    exit(0);
}

cli_writeln(count($modules) . ' BigBlueButton activit' . (count($modules) === 1 ? 'y' : 'ies') . ' to process.');

$totals = ['recordings' => 0, 'parsed' => 0, 'matched' => 0, 'unmatched' => 0];
$failed = [];

foreach ($modules as $cm) {
    $name = $DB->get_field('bigbluebuttonbn', 'name', ['id' => $cm->instance]);
    $label = 'cmid ' . $cm->id . ' (' . s((string) $name) . ')';

    if (!empty($options['dry-run'])) {
        cli_writeln('  would process ' . $label);
        continue;
    }

    try {
        $stats = \local_unifiedgrader\bbb\engagement_service::refresh_for_cmid((int) $cm->id);
    } catch (Throwable $e) {
        // One unreachable recording must not abandon the rest of the run.
        cli_writeln('  FAILED  ' . $label . ' — ' . $e->getMessage());
        $failed[] = $cm->id;
        continue;
    }

    foreach (array_keys($totals) as $key) {
        $totals[$key] += (int) ($stats[$key] ?? 0);
    }

    // Fewer read than found means some recordings gave us nothing back — usually
    // the server being unreachable, or a playback format we cannot read. Those
    // recordings keep no roster, so the grader keeps showing them to everyone.
    $note = $stats['parsed'] < $stats['recordings']
        ? '  <-- ' . ($stats['recordings'] - $stats['parsed']) . ' recording(s) yielded nothing'
        : '';
    cli_writeln(sprintf(
        '  %s: %d recording(s), %d read, %d attendee row(s) matched, %d unmatched%s',
        $label,
        $stats['recordings'],
        $stats['parsed'],
        $stats['matched'],
        $stats['unmatched'],
        $note,
    ));
}

if (!empty($options['dry-run'])) {
    cli_writeln('Dry run — nothing fetched.');
    exit(0);
}

cli_writeln('');
cli_writeln(sprintf(
    'Done. %d recording(s), %d read, %d matched, %d unmatched.',
    $totals['recordings'],
    $totals['parsed'],
    $totals['matched'],
    $totals['unmatched'],
));

if ($failed) {
    cli_writeln('Activities that errored: ' . implode(', ', $failed));
    exit(1);
}

// Unmatched rows are attendees we could not tie to a Moodle user — a guest, or
// someone whose display name does not match their account. They are recorded
// against userid 0 and count towards nobody.
if ($totals['unmatched'] > 0) {
    cli_writeln($totals['unmatched'] . ' attendee row(s) could not be matched to a Moodle user.');
}

exit(0);
