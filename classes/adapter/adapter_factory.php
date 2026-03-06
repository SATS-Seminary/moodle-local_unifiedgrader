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
 * Factory for creating activity-type-specific adapters.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\adapter;

defined('MOODLE_INTERNAL') || die();

/**
 * Resolves a course module ID to the correct adapter subclass.
 */
class adapter_factory {

    /** @var array<string, class-string<base_adapter>> Registered adapter classes. */
    private const ADAPTERS = [
        'assign' => assign_adapter::class,
        'forum' => forum_adapter::class,
        'quiz' => quiz_adapter::class,
    ];

    /**
     * Create an adapter for the given course module.
     *
     * @param int $cmid Course module ID.
     * @return base_adapter
     * @throws \moodle_exception If activity type is unsupported or disabled.
     */
    public static function create(int $cmid): base_adapter {
        [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        $modname = $cm->modname;

        if (!isset(self::ADAPTERS[$modname])) {
            throw new \moodle_exception('invalidactivitytype', 'local_unifiedgrader');
        }

        if (!get_config('local_unifiedgrader', "enable_{$modname}")) {
            throw new \moodle_exception('invalidactivitytype', 'local_unifiedgrader');
        }

        $context = \context_module::instance($cm->id);
        $class = self::ADAPTERS[$modname];
        return new $class($cm, $context, $course);
    }

    /**
     * Check whether a given activity type is supported and enabled.
     *
     * @param string $modname The module name (e.g., 'assign').
     * @return bool
     */
    public static function is_supported(string $modname): bool {
        return isset(self::ADAPTERS[$modname])
            && get_config('local_unifiedgrader', "enable_{$modname}");
    }
}
