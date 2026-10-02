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
 * Cache definitions for local_unifiedgrader.
 *
 * Each holds a small value that is read far more often than it changes, and
 * that has one place to clear it from. Anything a student's grade or its
 * release depends on is deliberately read from the database every time.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    // Whether late penalties apply to an activity (penalty\activity_settings).
    // Keyed by course module ID; 1 or 0.
    'penaltyswitch' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 100,
    ],
    // Whether a forum is a news forum (forum_helper). Keyed by course module
    // ID; 1 or 0.
    'newsforum' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 100,
    ],
    // Whether the user may use the comment library (access::can_use_library()).
    // Working it out means finding a course they can grade in, and the grader
    // asks several times each time it opens. Keyed by user ID; only a yes is kept.
    'libraryaccess' => [
        'mode' => cache_store::MODE_SESSION,
        'simplekeys' => true,
        'simpledata' => true,
        'ttl' => 600,
    ],
    // A teacher's grader preferences (preferences_manager). Keyed by user ID.
    'userprefs' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 10,
    ],
];
