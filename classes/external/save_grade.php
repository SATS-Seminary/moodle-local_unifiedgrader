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
 * External function: save grade.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_unifiedgrader\adapter\adapter_factory;
use local_unifiedgrader\penalty_manager;

/**
 * Saves a grade and feedback for a student.
 */
class save_grade extends external_api {
    /**
     * Parameter definition.
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module ID'),
            'userid' => new external_value(PARAM_INT, 'User ID'),
            'grade' => new external_value(PARAM_FLOAT, 'Grade value (-1 for no grade)', VALUE_DEFAULT, -1),
            'feedback' => new external_value(PARAM_RAW, 'Feedback HTML', VALUE_DEFAULT, ''),
            'feedbackformat' => new external_value(PARAM_INT, 'Feedback format', VALUE_DEFAULT, FORMAT_HTML),
            'advancedgradingdata' => new external_value(PARAM_RAW, 'Advanced grading data (JSON)', VALUE_DEFAULT, ''),
            'draftitemid' => new external_value(PARAM_INT, 'Draft area item ID for feedback files', VALUE_DEFAULT, 0),
            'feedbackfilesdraftid' => new external_value(
                PARAM_INT,
                'Draft area item ID for feedback files (assignfeedback_file)',
                VALUE_DEFAULT,
                0,
            ),
            'attemptnumber' => new external_value(
                PARAM_INT,
                'Attempt number (0-based), -1 for latest',
                VALUE_DEFAULT,
                -1,
            ),
            'reset' => new external_value(
                PARAM_BOOL,
                'Deliberate reset: clear the grade and remove any orphan submission row that was not student-submitted.',
                VALUE_DEFAULT,
                false,
            ),
        ]);
    }

    /**
     * Execute the function.
     *
     * @param int $cmid
     * @param int $userid
     * @param float $grade
     * @param string $feedback
     * @param int $feedbackformat
     * @param string $advancedgradingdata
     * @param int $draftitemid Draft area item ID for feedback file uploads.
     * @param int $feedbackfilesdraftid Draft area item ID for feedback files plugin.
     * @param int $attemptnumber Attempt number (0-based), or -1 for latest.
     * @param bool $reset When true, treat as a deliberate reset: clear the grade,
     *                    remove orphan submission rows that were not student-submitted.
     * @return array
     */
    public static function execute(
        int $cmid,
        int $userid,
        float $grade = -1,
        string $feedback = '',
        int $feedbackformat = FORMAT_HTML,
        string $advancedgradingdata = '',
        int $draftitemid = 0,
        int $feedbackfilesdraftid = 0,
        int $attemptnumber = -1,
        bool $reset = false,
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'userid' => $userid,
            'grade' => $grade,
            'feedback' => $feedback,
            'feedbackformat' => $feedbackformat,
            'advancedgradingdata' => $advancedgradingdata,
            'draftitemid' => $draftitemid,
            'feedbackfilesdraftid' => $feedbackfilesdraftid,
            'attemptnumber' => $attemptnumber,
            'reset' => $reset,
        ]);

        $context = \context_module::instance($params['cmid']);
        self::validate_context($context);
        require_capability('local/unifiedgrader:grade', $context);

        // Release the PHP session lock so concurrent AJAX from the same
        // teacher does not serialize behind this request. This handler
        // does not write to $SESSION.
        \core\session\manager::write_close();

        // Track wall-clock time so we can flag genuinely slow saves to admins.
        // A grade-save dragging into the multi-second range typically means
        // gradebook recompute is the bottleneck (large course / many graded
        // items) or a DB lock contention. Surfacing it via debugging() lets
        // an admin running with developer debug on spot the issue without
        // adding noise to normal operation.
        $starttime = microtime(true);

        $adapter = adapter_factory::create($params['cmid']);

        // Deliberate reset short-circuits the normal save flow: clear the
        // grade, remove any orphan submission row that was never genuinely
        // submitted by the student. Leaves real submissions untouched.
        if (!empty($params['reset'])) {
            $success = $adapter->reset_grade_and_submission($params['userid']);
            return ['success' => $success];
        }

        $gradevalue = $params['grade'] >= 0 ? $params['grade'] : null;

        // Apply penalty deductions to numeric grades (not scale, not quiz, not forum).
        // Quiz grades are computed by the question engine; penalties are not applicable.
        // Forum grades store the raw (teacher-given) grade; penalties are applied
        // separately when pushing to the gradebook via sync_gradebook_penalty().
        $activityinfo = null;
        if ($gradevalue !== null) {
            $activityinfo = $adapter->get_activity_info();
            $acttype = $activityinfo['type'] ?? '';

            // Cap manual grade entry at the activity's maximum. The client
            // surfaces this as an inline error so honest typos never reach
            // here; this is the authoritative belt-and-braces check.
            // Scale-based grading (usescale) uses a dropdown that can't
            // exceed its options, so no validation needed there. Quiz
            // grades are similarly clamped by the question engine —
            // applying our check would interfere with mid-attempt grading.
            if (empty($activityinfo['usescale']) && $acttype !== 'quiz') {
                $maxgrade = (float) ($activityinfo['maxgrade'] ?? 0);
                if ($maxgrade > 0 && $gradevalue > $maxgrade) {
                    throw new \moodle_exception(
                        'error_grade_exceeds_max',
                        'local_unifiedgrader',
                        '',
                        $maxgrade,
                    );
                }
            }

            // Sync late penalty for forums before saving (ensures penalty record is current).
            if ($acttype === 'forum') {
                $lateinfo = $adapter->calculate_late_penalty($params['userid']);
                penalty_manager::sync_late_penalty(
                    $params['cmid'],
                    $params['userid'],
                    $lateinfo['percentage'] ?? null,
                    $lateinfo['dayslate'] ?? 0,
                );
            }

            // Apply penalty deduction for activity types that store the already
            // reduced grade. Assignments, forums and BigBlueButton are NOT among
            // them: they store the mark the teacher typed and have the deduction
            // applied on the way to the gradebook (sync_gradebook_penalty below).
            //
            // Assignments moved to that model because storing the reduced grade
            // made the typed mark unrecoverable for display — a penalty applied
            // after grading inflated it, and a penalty large enough to clamp the
            // grade to zero erased it. See tests/penalty_roundtrip_test.php.
            // BigBlueButton followed for the same reasons; it keeps the raw mark
            // in grade_grades.rawgrade for want of a grades table of its own.
            $storesrawgrade = in_array($acttype, ['assign', 'forum', 'bigbluebuttonbn'], true);
            if (empty($activityinfo['usescale']) && $acttype !== 'quiz' && !$storesrawgrade) {
                $maxgrade = (float) ($activityinfo['maxgrade'] ?? 100);
                $deduction = penalty_manager::get_total_deduction(
                    $params['cmid'],
                    $params['userid'],
                    $maxgrade,
                );
                if ($deduction > 0) {
                    $gradevalue = max(0, $gradevalue - $deduction);
                }
            }
        }

        $advanceddata = [];
        if (!empty($params['advancedgradingdata'])) {
            $advanceddata = json_decode($params['advancedgradingdata'], true) ?: [];
            // Reject non-numeric criterion scores before they reach Moodle's
            // grading form, which writes them straight into a NUMBER column and
            // raises an opaque dml_write_exception. A teacher who accidentally
            // types an alphanumeric mark (e.g. "5a") gets a clear, localised
            // error instead of a 500.
            self::validate_advanced_grading_scores($advanceddata);
        }

        $success = $adapter->save_grade(
            $params['userid'],
            $gradevalue,
            $params['feedback'],
            $params['feedbackformat'],
            $advanceddata,
            $params['draftitemid'],
            $params['feedbackfilesdraftid'],
            $params['attemptnumber'],
        );

        // Forums, assignments and BigBlueButton store the raw (teacher-given)
        // grade; the gradebook gets rawgrade - penalties. Other types already
        // stored the reduced grade above, and their adapters no-op here.
        //
        // BigBlueButton is synced whichever way the grade was saved: the simple
        // path and the bbbext_advgrd rubric path both leave the un-penalised mark
        // in rawgrade, so a rubric-graded session picks up its deduction here too
        // — it never had one before.
        if ($activityinfo === null) {
            $activityinfo = $adapter->get_activity_info();
        }
        if (in_array($activityinfo['type'] ?? '', ['forum', 'assign', 'bigbluebuttonbn'], true)) {
            $adapter->sync_gradebook_penalty($params['userid']);
        }

        // Five seconds is the threshold: anything under it is normal noise;
        // above it suggests a real bottleneck (large gradebook, slow DB,
        // misbehaving plugin observer). The PHP session lock timeout is
        // typically two minutes, so logging at five seconds gives plenty of
        // warning before contention becomes user-visible.
        $elapsed = microtime(true) - $starttime;
        if ($elapsed > 5.0) {
            debugging(sprintf(
                'local_unifiedgrader_save_grade for cmid=%d user=%d took %.2fs '
                    . '(adapter=%s, advanced=%s, draftitemid=%d, files=%d)',
                $params['cmid'],
                $params['userid'],
                $elapsed,
                $activityinfo['type'] ?? 'unknown',
                empty($advanceddata) ? 'no' : 'yes',
                $params['draftitemid'],
                $params['feedbackfilesdraftid'],
            ), DEBUG_DEVELOPER);
        }

        return ['success' => $success];
    }

    /**
     * Return definition.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether the grade was saved successfully'),
        ]);
    }

    /**
     * Reject non-numeric criterion scores in advanced grading data before they
     * reach Moodle's grading form, which stores them in a numeric column and
     * throws an opaque dml_write_exception. Rubrics carry a level id rather than
     * a free-text score, so only the marking-guide `score` and quiz-manual
     * `mark` fields are validated. Empty values are allowed (an ungraded
     * criterion). Decimals must use a period — the client canonicalises comma
     * separators before sending.
     *
     * @param array $advanceddata Decoded advanced grading data.
     * @throws \moodle_exception When a score/mark is present but not numeric.
     */
    private static function validate_advanced_grading_scores(array $advanceddata): void {
        $rows = [];
        if (!empty($advanceddata['criteria']) && is_array($advanceddata['criteria'])) {
            $rows = $advanceddata['criteria'];
        } else if (!empty($advanceddata['questions']) && is_array($advanceddata['questions'])) {
            $rows = $advanceddata['questions'];
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach (['score', 'mark'] as $key) {
                if (!array_key_exists($key, $row)) {
                    continue;
                }
                $value = $row[$key];
                if ($value === '' || $value === null) {
                    continue;
                }
                if (!is_numeric($value)) {
                    throw new \moodle_exception('error_criterion_score_not_numeric', 'local_unifiedgrader');
                }
            }
        }
    }
}
