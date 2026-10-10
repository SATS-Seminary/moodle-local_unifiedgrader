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
 * A proctoring service the grader can read results from.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\proctoring;

/**
 * One proctoring service, for one quiz.
 *
 * Each service keeps its results somewhere different: SMOWL behind its own
 * API, the quiz access rule plugins in their own tables or behind theirs. A
 * provider hides that and hands the grader the same thing every time: a
 * review priority per student, the findings behind it, and where to look at
 * the evidence.
 *
 * A provider reads another plugin's data and never writes to it.
 */
abstract class provider {
    /** @var string The service has an answer for this quiz. */
    const STATE_OK = 'ok';
    /** @var string The quiz is expected to be proctored but the service knows nothing of it. */
    const STATE_NOTCONFIGURED = 'notconfigured';
    /** @var string The service could not be reached. */
    const STATE_UNAVAILABLE = 'unavailable';
    /** @var string Nothing to show. */
    const STATE_NONE = 'none';

    /** @var int Seconds before a failed request is tried again. */
    const ERROR_TTL = 60;

    /** @var string[] Access rules taken to be installed, in tests only. */
    private static array $testrules = [];

    /**
     * Treat quiz access rules as installed when only their tables exist, for tests.
     *
     * @param string[] $rules Access rule names, e.g. "quilgo".
     */
    public static function assume_access_rules_for_testing(array $rules): void {
        if (!PHPUNIT_TEST) {
            throw new \coding_exception('assume_access_rules_for_testing() is for tests only.');
        }
        self::$testrules = $rules;
    }

    /**
     * Constructor.
     *
     * @param \cm_info $cm The quiz.
     */
    public function __construct(
        /** @var \cm_info The quiz. */
        protected \cm_info $cm,
    ) {
    }

    /**
     * The service's short name, as used in settings and cache keys.
     *
     * @return string Lower-case letters only.
     */
    abstract public static function key(): string;

    /**
     * Whether the site has what this service needs: its plugin, or its credentials.
     *
     * @return bool
     */
    abstract public static function installed(): bool;

    /**
     * Whether this service proctors the quiz.
     *
     * Must be cheap: it is asked on every grader page load.
     *
     * @return bool
     */
    abstract public function covers_quiz(): bool;

    /**
     * The quiz's results.
     *
     * Each user has level, provisional, reporturl, tools and attempts. A tool
     * has label, status, statuslabel and issues; an issue has label, count
     * and level. attempts is keyed by the quiz attempt number and holds the
     * same without attempts of its own; it is empty for a service that keeps
     * one result per student.
     *
     * @param bool $refresh Ask the service again instead of using a cached answer.
     * @return array{state: string, fetchedat: int, reporturl: string, users: array}
     *         users is keyed by Moodle user ID.
     */
    abstract public function load(bool $refresh = false): array;

    /**
     * The service's name, as shown to teachers.
     *
     * @return string
     */
    public static function name(): string {
        return get_string('proctoring_service_' . static::key(), 'local_unifiedgrader');
    }

    /**
     * Whether a quiz access rule plugin is installed with the tables this provider reads.
     *
     * @param string $rule The access rule's name, e.g. "quilgo".
     * @param string[] $tables Tables that must exist.
     * @return bool
     */
    protected static function access_rule_installed(string $rule, array $tables): bool {
        global $DB;
        if (\core_component::get_plugin_directory('quizaccess', $rule) === null && !in_array($rule, self::$testrules, true)) {
            return false;
        }
        foreach ($tables as $table) {
            if (!$DB->get_manager()->table_exists($table)) {
                return false;
            }
        }
        return true;
    }

    /**
     * The name for one of a service's constants.
     *
     * A constant without a language string (services add them over time) is
     * shown as readable words: MORE_THAN_ONE becomes "More than one".
     *
     * @param string $prefix Language string prefix.
     * @param string $constant The constant, e.g. FrontCamera or WEB_NAVIGATION.
     * @return string
     */
    protected static function label(string $prefix, string $constant): string {
        $key = $prefix . \core_text::strtolower(preg_replace('/[^A-Za-z0-9]/', '', $constant));
        if (get_string_manager()->string_exists($key, 'local_unifiedgrader')) {
            return get_string($key, 'local_unifiedgrader');
        }
        $words = preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', str_replace(['_', '-'], ' ', $constant));
        return ucfirst(\core_text::strtolower($words));
    }

    /**
     * Whether the user holds one of the service's own capabilities.
     *
     * The capability belongs to another plugin, so it may not exist.
     *
     * @param string $capability E.g. quizaccess/quilgo:viewreport.
     * @return bool
     */
    protected function can(string $capability): bool {
        return get_capability_info($capability) !== null && has_capability($capability, $this->cm->context);
    }

    /**
     * A student's result across their attempts: the worst one.
     *
     * A student whose worst attempt is green, with another attempt still
     * awaiting review, is awaiting review: green is only for a finished verdict.
     *
     * @param array $attempts Attempt number => result (level, provisional, reporturl, tools).
     * @return array The worst attempt's result, with every attempt under attempts.
     */
    protected function across_attempts(array $attempts): array {
        ksort($attempts);
        $worst = null;
        $provisional = false;
        foreach ($attempts as $attempt) {
            $provisional = $provisional || $attempt['provisional'] || $attempt['level'] === rule::PENDING;
            if ($worst === null || rule::RANK[$attempt['level']] > rule::RANK[$worst['level']]) {
                $worst = $attempt;
            }
        }
        if ($provisional && $worst['level'] === rule::GREEN) {
            $worst['level'] = rule::PENDING;
        }
        $worst['provisional'] = $provisional && $worst['level'] !== rule::PENDING;
        $worst['attempts'] = $attempts;
        return $worst;
    }

    /**
     * A cached answer for this quiz, if it is still good.
     *
     * A failed request is remembered only briefly, so the service is neither
     * hammered nor given up on.
     *
     * @return array|null
     */
    protected function cached(): ?array {
        $cached = \cache::make('local_unifiedgrader', 'proctoring')->get($this->cache_key());
        if (!is_array($cached)) {
            return null;
        }
        if (($cached['state'] ?? '') === 'error' && $cached['fetchedat'] <= time() - self::ERROR_TTL) {
            return null;
        }
        return $cached;
    }

    /**
     * Remember an answer for this quiz.
     *
     * @param array $answer With at least state and fetchedat.
     */
    protected function remember(array $answer): void {
        \cache::make('local_unifiedgrader', 'proctoring')->set($this->cache_key(), $answer);
    }

    /**
     * This quiz's key in the proctoring cache.
     *
     * @return string
     */
    private function cache_key(): string {
        return static::key() . '_' . $this->cm->id;
    }
}
