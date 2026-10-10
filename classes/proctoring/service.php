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
 * Proctoring results for a quiz, from whichever service proctors it.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\proctoring;

/**
 * Finds the proctoring service behind a quiz and reads its results.
 *
 * Every service is off until an administrator ticks it. Institutions use
 * different integrity tools, and a site should see no trace of one it does
 * not use.
 */
class service {
    /**
     * The services the grader knows, in the order they are tried.
     *
     * The quiz access rules come first: each says for itself whether it is on
     * for a quiz. SMOWL cannot, so it is asked last.
     *
     * @var string[] Provider key => class.
     */
    const PROVIDERS = [
        'quilgo' => provider\quilgo::class,
        'proctorlink' => provider\proctorlink::class,
        'autoproctor' => provider\autoproctor::class,
        'smowl' => provider\smowl::class,
    ];

    /**
     * The services switched on for the site that have what they need.
     *
     * @return string[] Provider key => class.
     */
    public static function enabled_providers(): array {
        $ticked = array_filter(explode(',', (string) get_config('local_unifiedgrader', 'proctoring_services')));
        $enabled = [];
        foreach (self::PROVIDERS as $key => $class) {
            if (in_array($key, $ticked, true) && $class::installed()) {
                $enabled[$key] = $class;
            }
        }
        return $enabled;
    }

    /**
     * Whether the grader should ask about proctoring for an activity at all.
     *
     * @param \cm_info|\stdClass $cm The activity.
     * @param \context_module $context Its context.
     * @return bool
     */
    public static function applies($cm, \context_module $context): bool {
        return $cm->modname === 'quiz'
            && has_capability('local/unifiedgrader:viewproctoring', $context)
            && (bool) self::enabled_providers();
    }

    /**
     * The service that proctors a quiz.
     *
     * @param \cm_info $cm The quiz.
     * @return provider|null Null when no enabled service covers it.
     */
    public static function provider_for(\cm_info $cm): ?provider {
        foreach (self::enabled_providers() as $class) {
            $provider = new $class($cm);
            if ($provider->covers_quiz()) {
                return $provider;
            }
        }
        return null;
    }

    /**
     * A quiz's proctoring results, ready for the grader.
     *
     * @param \cm_info $cm The quiz.
     * @param bool $refresh Ask the service again instead of using a cached answer.
     * @return array As provider::load(), with the service's name under provider.
     */
    public static function load(\cm_info $cm, bool $refresh = false): array {
        $provider = self::provider_for($cm);
        if (!$provider) {
            return ['state' => provider::STATE_NONE, 'provider' => '', 'fetchedat' => 0, 'reporturl' => '', 'users' => []];
        }
        return ['provider' => $provider::name()] + $provider->load($refresh);
    }
}
