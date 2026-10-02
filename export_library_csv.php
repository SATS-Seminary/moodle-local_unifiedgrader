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
 * Streams a comment library export as a CSV download.
 *
 * Shared by both the admin moderation tool and the teacher self-service
 * page — access is decided entirely by the $scope/$owner/$filteruser
 * combination and a capability check, not by which page linked here.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use local_unifiedgrader\library_csv;

require_login();

if (isguestuser()) {
    throw new moodle_exception('noguest');
}

// The "scope" param selects the source: "mine" is the logged-in user's own
// library (my_library.php); "bucket" is one owner/code pair (admin bucket
// drill-down); "filter" is every library matching the moderation page's
// filters (admin only).
$scope = required_param('scope', PARAM_ALPHA);

$filename = 'comment-library-' . userdate(time(), '%Y%m%d-%H%M') . '.csv';

if ($scope === 'mine') {
    \local_unifiedgrader\access::require_library_access();
    $csv = library_csv::export_for_owner($USER->id);
} else if ($scope === 'bucket') {
    $owner = required_param('owner', PARAM_INT);
    $code = optional_param('code', '', PARAM_TEXT);

    // Exporting your own bucket needs nothing beyond being logged in — this
    // is what the teacher self-service page's bucket-scoped export link
    // uses. Exporting anyone else's requires the moderation capability.
    if ($owner !== (int) $USER->id) {
        require_capability('local/unifiedgrader:moderatelibraries', context_system::instance());
    } else {
        \local_unifiedgrader\access::require_library_access();
    }

    $csv = library_csv::export_for_owner($owner, $code);
} else if ($scope === 'filter') {
    require_capability('local/unifiedgrader:moderatelibraries', context_system::instance());
    $filteruser = optional_param('filteruser', 0, PARAM_INT);
    $filtercode = optional_param('filtercode', '', PARAM_TEXT);
    $csv = library_csv::export_for_filters($filteruser, $filtercode);
} else {
    throw new moodle_exception('invalidparameter', 'debug');
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($csv));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: no-cache');

echo $csv;
