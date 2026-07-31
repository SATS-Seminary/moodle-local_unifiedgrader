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

namespace local_unifiedgrader;

/**
 * Guards against applying a late penalty twice to the same grade.
 *
 * assign_update_grades() already applies the penalty: it calls
 * assign_grade_item_update(), which ends by calling
 * \mod_assign\penalty\helper::apply_penalty_to_user() for every affected user.
 * Calling apply_penalty_to_user() again afterwards deducts the penalty a SECOND
 * time, because:
 *
 *  - gradepenalty_duedate deducts a fixed number of marks (max grade × penalty%,
 *    not a share of the remaining grade), so the second pass removes the full
 *    penalty again rather than compounding to a smaller amount; and
 *  - core has no "already penalised" guard — is_penalty_enabled_for_grade() only
 *    rejects minimum, overridden and locked grades.
 *
 * The observed symptom was a 35% (7 days late) rule taking 70% off a student's
 * grade after an extension triggered a recalculation. The endpoints where this
 * happened (recalculate_penalty.php, extension.php) are web entry points guarded
 * by require_login()/require_sesskey(), so this is enforced structurally instead:
 * no plugin file may call apply_penalty_to_user() in the lines following an
 * assign_update_grades() call.
 *
 * Comments are stripped before scanning (via token_get_all), so prose mentioning
 * both function names — such as the notes left at the fixed call sites — cannot
 * trip the check; only real calls count.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class penalty_double_apply_test extends \advanced_testcase {
    /** @var int How many lines after assign_update_grades() count as the same block. */
    private const WINDOW = 25;

    /**
     * No plugin code may re-apply the penalty right after a full recalculation.
     */
    public function test_penalty_is_not_applied_twice(): void {
        $offenders = [];

        foreach ($this->plugin_php_files() as $path) {
            $recalcs = [];
            $applies = [];
            foreach ($this->code_tokens($path) as [$name, $line]) {
                if ($name === 'assign_update_grades') {
                    $recalcs[] = $line;
                } else if ($name === 'apply_penalty_to_user') {
                    $applies[] = $line;
                }
            }
            foreach ($recalcs as $recalcline) {
                foreach ($applies as $applyline) {
                    if ($applyline > $recalcline && ($applyline - $recalcline) <= self::WINDOW) {
                        $offenders[] = basename($path) . ': assign_update_grades() on line ' . $recalcline
                            . ' is followed by apply_penalty_to_user() on line ' . $applyline;
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "The late penalty would be applied twice, halving the student's grade a second time.\n"
                . "assign_update_grades() already applies it — drop the extra apply_penalty_to_user() call.\n"
                . implode("\n", $offenders),
        );
    }

    /**
     * The scan must actually be looking at the files that had the bug, so a
     * refactor that renames or moves them cannot silently disable this guard.
     */
    public function test_guard_covers_the_penalty_recalculation_entry_points(): void {
        $scanned = array_map('basename', $this->plugin_php_files());

        foreach (['recalculate_penalty.php', 'extension.php', 'overrides_extensions.php'] as $expected) {
            $this->assertContains(
                $expected,
                $scanned,
                "{$expected} is no longer being scanned for the double-penalty pattern.",
            );
        }
    }

    /**
     * The pattern the guard looks for is really detected — a canary, so the scan
     * cannot silently pass because of a broken tokeniser or path list.
     */
    public function test_guard_detects_the_offending_pattern(): void {
        $source = "<?php\nassign_update_grades(\$instance, \$userid);\n"
            . "\\mod_assign\\penalty\\helper::apply_penalty_to_user(\$cmid, \$userid);\n";
        $file = make_request_directory() . '/double_apply_fixture.php';
        file_put_contents($file, $source);

        $names = array_column($this->code_tokens($file), 0);

        $this->assertContains('assign_update_grades', $names);
        $this->assertContains('apply_penalty_to_user', $names);
    }

    /**
     * Prose in comments must not be mistaken for a call, or the fixed sites —
     * which explain the hazard in a comment — would report as offenders.
     */
    public function test_comments_are_ignored(): void {
        $source = "<?php\nassign_update_grades(\$instance, \$userid);\n"
            . "// Do NOT call apply_penalty_to_user() again here.\n"
            . "/* apply_penalty_to_user() would double the deduction. */\n";
        $file = make_request_directory() . '/comment_only_fixture.php';
        file_put_contents($file, $source);

        $names = array_column($this->code_tokens($file), 0);

        $this->assertContains('assign_update_grades', $names);
        $this->assertNotContains('apply_penalty_to_user', $names);
    }

    /**
     * Function-name tokens in a file, with comments and strings excluded.
     *
     * @param string $path Absolute path to a PHP file.
     * @return array<array{0: string, 1: int}> Pairs of [name, line number].
     */
    private function code_tokens(string $path): array {
        $tokens = @token_get_all((string) file_get_contents($path));
        $names = [];
        foreach ($tokens as $token) {
            // Comments and doc comments arrive as arrays too; only T_STRING is a
            // real identifier, so prose about these functions is skipped.
            if (is_array($token) && $token[0] === T_STRING) {
                $names[] = [$token[1], $token[2]];
            }
        }
        return $names;
    }

    /**
     * Every PHP file shipped by the plugin, excluding this test's own fixtures.
     *
     * @return array<string> Absolute paths.
     */
    private function plugin_php_files(): array {
        global $CFG;

        $root = $CFG->dirroot . '/local/unifiedgrader';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );
        $files = [];
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $path = $file->getPathname();
            // Skip build output and this guard itself (its prose is in strings).
            if (strpos($path, '/node_modules/') !== false || strpos($path, '/vendor/') !== false) {
                continue;
            }
            if (basename($path) === 'penalty_double_apply_test.php') {
                continue;
            }
            $files[] = $path;
        }
        sort($files);
        return $files;
    }
}
