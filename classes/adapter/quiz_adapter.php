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
 * Quiz adapter for the unified grading interface.
 *
 * Supports whole-quiz grading with per-question manual grading for essay
 * questions. Auto-graded questions are read-only. The quiz grade is
 * auto-calculated from attempt scores using the configured grade method.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\adapter;

use local_unifiedgrader\submission_comment_manager;

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->dirroot . '/question/engine/lib.php');

use mod_quiz\quiz_attempt;
use mod_quiz\quiz_settings;
use mod_quiz\grade_calculator;
use question_engine;
use question_state;

/**
 * Concrete adapter wrapping mod_quiz's grading API.
 */
class quiz_adapter extends base_adapter {
    /** @var \stdClass The raw quiz DB record. */
    private \stdClass $quiz;

    /** @var quiz_settings The quiz settings object. */
    private quiz_settings $quizobj;

    /**
     * Constructor.
     *
     * @param \cm_info $cm Course module info.
     * @param \context_module $context Module context.
     * @param \stdClass $course Course record.
     */
    public function __construct(\cm_info $cm, \context_module $context, \stdClass $course) {
        parent::__construct($cm, $context, $course);

        global $DB;
        $this->quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
        $this->quizobj = quiz_settings::create_for_cmid($cm->id);
    }

    /**
     * Get quiz metadata.
     *
     * @return array
     */
    public function get_activity_info(): array {
        $hasduedateplugin = class_exists('\quizaccess_duedate\override_manager');

        // Use duedate plugin's quiz-level duedate if available, otherwise fallback to timeclose.
        $duedate = (int) ($this->quiz->timeclose ?? 0);
        if ($hasduedateplugin) {
            $pluginduedate = $this->get_duedate_plugin_duedate();
            if ($pluginduedate > 0) {
                $duedate = $pluginduedate;
            }
        }

        // Detect scale-based grading (negative grade = scale ID).
        // Grade type "None" means grade == 0.
        $rawgrade = (int) $this->quiz->grade;
        $gradingenabled = $rawgrade !== 0;
        $usescale = $rawgrade < 0;
        $scaleitems = [];
        $maxgrade = (float) $this->quiz->grade;
        if ($usescale) {
            $menu = make_grades_menu($rawgrade);
            foreach ($menu as $value => $label) {
                $scaleitems[] = ['value' => (int) $value, 'label' => (string) $label];
            }
            $maxgrade = (float) count($scaleitems);
        }

        return [
            'id' => (int) $this->cm->id,
            'name' => format_string($this->quiz->name),
            'type' => 'quiz',
            'duedate' => $duedate,
            'cutoffdate' => (int) ($this->quiz->timeclose ?? 0),
            'maxgrade' => $maxgrade,
            'usescale' => $usescale,
            'scaleitems' => $scaleitems,
            'intro' => format_text(
                $this->quiz->intro ?? '',
                $this->quiz->introformat ?? FORMAT_HTML,
                ['context' => $this->context],
            ),
            'gradingmethod' => 'simple',
            'gradingdisabled' => !$gradingenabled,
            'teamsubmission' => false,
            'blindmarking' => false,
            'canmanageoverrides' => has_capability('mod/quiz:manageoverrides', $this->context),
            'hasduedateplugin' => $hasduedateplugin,
            'canmanageextensions' => $hasduedateplugin
                && has_capability('quizaccess/duedate:manageoverrides', $this->context),
            'maxattempts' => (int) ($this->quiz->attempts ?? 0),
        ];
    }

    /**
     * Get participant list with attempt/grade status.
     *
     * @param array $filters Optional: status, group, search, sort, sortdir.
     * @return array
     */
    public function get_participants(array $filters = []): array {
        global $DB, $PAGE;

        $groupids = $this->get_group_ids($filters);

        // Get enrolled users who can attempt quizzes (active enrolments only).
        $enrolledusers = $this->get_enrolled_users_multigroup(
            $this->context,
            'mod/quiz:attempt',
            $groupids,
            'u.*',
            'u.lastname, u.firstname',
        );

        // Batch-load attempt stats per user.
        $sql = "SELECT userid,
                       COUNT(*) AS attemptcount,
                       MAX(timefinish) AS lastfinish,
                       MAX(CASE WHEN state = :finished THEN 1 ELSE 0 END) AS hasfinished,
                       MAX(CASE WHEN state = :inprogress THEN 1 ELSE 0 END) AS hasinprogress
                  FROM {quiz_attempts}
                 WHERE quiz = :quizid AND preview = 0
              GROUP BY userid";
        $attemptstats = $DB->get_records_sql($sql, [
            'quizid' => $this->quiz->id,
            'finished' => quiz_attempt::FINISHED,
            'inprogress' => quiz_attempt::IN_PROGRESS,
        ]);

        // Batch-load computed grades from quiz_grades.
        $grades = $DB->get_records('quiz_grades', ['quiz' => $this->quiz->id], '', 'userid, grade, timemodified');

        // Check which users have questions needing manual grading.
        $needsgrading = $this->get_users_needing_grading();

        // Batch-load user overrides to avoid N+1 queries.
        $overrides = $DB->get_records_select(
            'quiz_overrides',
            'quiz = :quizid2 AND userid IS NOT NULL',
            ['quizid2' => $this->quiz->id],
            '',
            'userid, timeclose',
        );
        $overrideset = [];
        foreach ($overrides as $ov) {
            $overrideset[(int) $ov->userid] = $ov->timeclose !== null ? (int) $ov->timeclose : null;
        }

        // Batch-load duedate plugin extensions (if plugin is installed).
        $hasduedateplugin = class_exists('\quizaccess_duedate\override_manager');
        $duedateextensions = [];
        if ($hasduedateplugin) {
            $ddoverrides = \quizaccess_duedate\override_manager::get_overrides($this->quiz->id, 'user');
            foreach ($ddoverrides as $ddo) {
                $duedateextensions[(int) $ddo->userid] = (int) $ddo->duedate;
            }
        }

        $globaltimeclose = (int) ($this->quiz->timeclose ?? 0);

        $result = [];
        foreach ($enrolledusers as $user) {
            $userid = (int) $user->id;
            $stats = $attemptstats[$userid] ?? null;
            $usergrade = $grades[$userid] ?? null;

            $status = $this->resolve_status($stats, $usergrade, isset($needsgrading[$userid]));

            $userpicture = new \user_picture($user);
            $userpicture->size = 64;
            $profileimageurl = $userpicture->get_url($PAGE)->out(false);

            // Effective due date: duedate plugin (if installed) > native override > global timeclose.
            if ($hasduedateplugin) {
                $effectiveduedate = (int) \quizaccess_duedate\override_manager::get_effective_duedate(
                    $this->quiz->id, $userid
                );
            } else {
                $effectiveduedate = $overrideset[$userid] ?? $globaltimeclose;
            }
            $submittedat = $stats ? (int) ($stats->lastfinish ?? 0) : 0;
            $islate = $effectiveduedate > 0 && $submittedat > 0 && $submittedat > $effectiveduedate;

            $entry = [
                'id' => $userid,
                'fullname' => fullname($user),
                'email' => $user->email,
                'profileimageurl' => $profileimageurl,
                'status' => $status,
                'submittedat' => $submittedat,
                'gradevalue' => $usergrade ? (float) $usergrade->grade : null,
                'hasoverride' => isset($overrideset[$userid]),
                'hasextension' => isset($duedateextensions[$userid]),
                'islate' => $islate,
            ];

            // Apply status filter.
            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                if (!$this->matches_filter($filters['status'], $entry, $effectiveduedate)) {
                    continue;
                }
            }

            // Apply search filter.
            if (!empty($filters['search'])) {
                $needle = \core_text::strtolower($filters['search']);
                if (strpos(\core_text::strtolower($entry['fullname']), $needle) === false) {
                    continue;
                }
            }

            $result[] = $entry;
        }

        // Sort.
        $sort = $filters['sort'] ?? 'fullname';
        $sortdir = $filters['sortdir'] ?? 'asc';
        $validkeys = ['fullname', 'submittedat', 'status', 'gradevalue'];
        if (!in_array($sort, $validkeys)) {
            $sort = 'fullname';
        }

        usort($result, function ($a, $b) use ($sort, $sortdir) {
            $va = $a[$sort] ?? '';
            $vb = $b[$sort] ?? '';
            if (is_string($va)) {
                $cmp = strcasecmp($va, $vb);
            } else {
                $cmp = ($va ?? 0) <=> ($vb ?? 0);
            }
            return $sortdir === 'desc' ? -$cmp : $cmp;
        });

        return $result;
    }

    /**
     * Get the list of finished quiz attempts for a user.
     *
     * @param int $userid The user ID.
     * @return array List of arrays with keys: id, attemptnumber, status, timemodified, graded.
     */
    public function get_attempts(int $userid): array {
        $attempts = quiz_get_user_attempts($this->quiz->id, $userid, 'finished');
        $result = [];
        foreach ($attempts as $attempt) {
            $result[] = [
                'id' => (int) $attempt->attempt,
                'attemptnumber' => (int) $attempt->attempt,
                'status' => 'finished',
                'timemodified' => (int) ($attempt->timefinish ?: $attempt->timemodified),
                'graded' => $attempt->sumgrades !== null,
            ];
        }
        return $result;
    }

    /**
     * Get quiz attempt rendered as HTML for preview (latest attempt).
     *
     * @param int $userid
     * @return array
     */
    public function get_submission_data(int $userid): array {
        // Get the latest finished attempt.
        $attempt = $this->get_latest_finished_attempt($userid);
        if (!$attempt) {
            return $this->empty_submission($userid);
        }

        return $this->build_submission_data($userid, $attempt);
    }

    /**
     * Get submission data for a specific quiz attempt.
     *
     * @param int $userid The user ID.
     * @param int $attemptnumber Attempt number (1-based for quiz), or -1 for latest.
     * @return array
     */
    public function get_submission_data_for_attempt(int $userid, int $attemptnumber = -1): array {
        if ($attemptnumber < 1) {
            return $this->get_submission_data($userid);
        }

        $attempt = $this->get_attempt_by_number($userid, $attemptnumber);
        if (!$attempt) {
            return $this->empty_submission($userid);
        }

        return $this->build_submission_data($userid, $attempt);
    }

    /**
     * Build submission data array from an attempt record.
     *
     * @param int $userid
     * @param \stdClass $attempt The attempt record.
     * @return array
     */
    private function build_submission_data(int $userid, \stdClass $attempt): array {
        $quba = question_engine::load_questions_usage_by_activity($attempt->uniqueid);
        $slots = $quba->get_slots();
        $content = $this->render_attempt_as_html($quba, $slots, $attempt);

        return [
            'userid' => $userid,
            'status' => 'submitted',
            'content' => $content,
            'hascontent' => !empty($content),
            'files' => $this->get_submission_files_for_attempt($attempt),
            'onlinetext' => '',
            'timecreated' => (int) $attempt->timestart,
            'timemodified' => (int) ($attempt->timefinish ?: $attempt->timemodified),
            'attemptnumber' => (int) $attempt->attempt,
            'commentcount' => submission_comment_manager::count_comments($this->cm->id, $userid),
        ];
    }

    /**
     * Get current grade and per-question manual grading data (latest attempt).
     *
     * @param int $userid
     * @return array
     */
    public function get_grade_data(int $userid): array {
        $attempt = $this->get_latest_finished_attempt($userid);
        return $this->build_grade_data($userid, $attempt);
    }

    /**
     * Get grade data for a specific quiz attempt.
     *
     * Overall grade is per-user. Feedback is per-attempt (stored in
     * local_unifiedgrader_qfb) with gradebook fallback. Per-question
     * manual grading data comes from the specified attempt.
     *
     * @param int $userid The user ID.
     * @param int $attemptnumber Attempt number (1-based for quiz), or -1 for latest.
     * @return array
     */
    public function get_grade_data_for_attempt(int $userid, int $attemptnumber = -1): array {
        if ($attemptnumber < 1) {
            return $this->get_grade_data($userid);
        }

        $attempt = $this->get_attempt_by_number($userid, $attemptnumber);
        return $this->build_grade_data($userid, $attempt, $attemptnumber);
    }

    /**
     * Build grade data array from an attempt record.
     *
     * Feedback is loaded from the per-attempt table (local_unifiedgrader_qfb)
     * first, falling back to the gradebook for backwards compatibility.
     *
     * @param int $userid
     * @param \stdClass|null $attempt The attempt record (for per-question grading data).
     * @param int $attemptnumber Attempt number (1-based), or -1 for latest.
     * @return array
     */
    private function build_grade_data(int $userid, ?\stdClass $attempt, int $attemptnumber = -1): array {
        global $DB;

        // Get overall quiz grade (per-user, not per-attempt).
        $quizgrade = $DB->get_record('quiz_grades', [
            'quiz' => $this->quiz->id,
            'userid' => $userid,
        ]);
        $hasgrade = $quizgrade && $quizgrade->grade !== null;

        // Resolve attemptnumber: use the attempt record if available, otherwise latest.
        $resolvedattempt = $attemptnumber;
        if ($resolvedattempt < 1 && $attempt) {
            $resolvedattempt = (int) $attempt->attempt;
        }

        // Try per-attempt feedback from our table first.
        $feedbacktext = '';
        $feedbackformat = (int) FORMAT_HTML;
        $qfb = null;
        if ($resolvedattempt >= 1) {
            $qfb = $DB->get_record('local_unifiedgrader_qfb', [
                'cmid' => $this->cm->id,
                'userid' => $userid,
                'attemptnumber' => $resolvedattempt,
            ]);
        }

        if ($qfb && !empty($qfb->feedback)) {
            // Per-attempt feedback found — use qfb.id as itemid for file URLs.
            $feedbacktext = file_rewrite_pluginfile_urls(
                $qfb->feedback,
                'pluginfile.php',
                $this->context->id,
                'local_unifiedgrader',
                'quizfeedback',
                (int) $qfb->id,
            );
            $feedbackformat = (int) ($qfb->feedbackformat ?? FORMAT_HTML);
        } else {
            // Fall back to gradebook feedback (backwards compat).
            $gradeitem = \grade_item::fetch([
                'itemtype' => 'mod',
                'itemmodule' => 'quiz',
                'iteminstance' => $this->quiz->id,
                'itemnumber' => 0,
                'courseid' => $this->course->id,
            ]);
            if ($gradeitem) {
                $gradegrade = \grade_grade::fetch([
                    'itemid' => $gradeitem->id,
                    'userid' => $userid,
                ]);
                if ($gradegrade && !empty($gradegrade->feedback)) {
                    $feedbacktext = file_rewrite_pluginfile_urls(
                        $gradegrade->feedback,
                        'pluginfile.php',
                        $this->context->id,
                        'local_unifiedgrader',
                        'quizfeedback',
                        (int) $gradegrade->id,
                    );
                    $feedbackformat = (int) ($gradegrade->feedbackformat ?? FORMAT_HTML);
                }
            }
        }

        // Build per-question manual grading data from the specified attempt.
        $gradingdefinition = '';
        $rubricdata = '';

        if ($attempt) {
            $quba = question_engine::load_questions_usage_by_activity($attempt->uniqueid);
            $manualquestions = $this->get_manual_questions($quba);

            if (!empty($manualquestions['criteria'])) {
                $gradingdefinition = json_encode([
                    'method' => 'quizmanual',
                    'name' => get_string('manualquestions', 'local_unifiedgrader'),
                    'criteria' => $manualquestions['criteria'],
                ]);
                $rubricdata = json_encode([
                    'criteria' => $manualquestions['fill'],
                ]);
            }
        }

        // Calculate late penalty percentage from the duedate plugin settings.
        // Mirrors the observer logic: check first attempt finish time against effective
        // due date (which honours extensions). Does not depend on gradebook feedback text.
        $latepenaltypct = $this->get_duedate_late_penalty_pct($userid);

        return [
            'grade' => $hasgrade ? (float) $quizgrade->grade : null,
            'feedback' => format_text($feedbacktext, $feedbackformat, ['context' => $this->context]),
            'feedbackformat' => $feedbackformat,
            'rubricdata' => $rubricdata,
            'gradingdefinition' => $gradingdefinition,
            'timegraded' => $quizgrade ? (int) ($quizgrade->timemodified ?? 0) : 0,
            'grader' => $qfb ? (int) $qfb->grader : 0,
            'latepenaltypct' => $latepenaltypct,
        ];
    }

    /**
     * Save grade — per-question manual grading or simple feedback.
     *
     * @param int $userid
     * @param float|null $grade
     * @param string $feedback
     * @param int $feedbackformat
     * @param array $advancedgradingdata
     * @param int $draftitemid Draft area item ID for feedback file uploads.
     * @param int $feedbackfilesdraftid Draft area item ID for feedback files plugin.
     * @param int $attemptnumber Attempt number (1-based for quiz), or -1 for latest.
     * @return bool
     */
    public function save_grade(
        int $userid,
        ?float $grade,
        string $feedback,
        int $feedbackformat = FORMAT_HTML,
        array $advancedgradingdata = [],
        int $draftitemid = 0,
        int $feedbackfilesdraftid = 0,
        int $attemptnumber = -1,
    ): bool {
        global $DB, $USER;

        if ($attemptnumber >= 1) {
            $attempt = $this->get_attempt_by_number($userid, $attemptnumber);
        } else {
            $attempt = $this->get_latest_finished_attempt($userid);
        }

        if (!empty($advancedgradingdata['method']) && $advancedgradingdata['method'] === 'quizmanual'
                && !empty($advancedgradingdata['questions']) && $attempt) {
            // Per-question manual grading.
            $this->save_manual_question_grades($attempt, $advancedgradingdata['questions']);
        }

        // Resolve attempt number for per-attempt feedback storage.
        $resolvedattempt = $attemptnumber;
        if ($resolvedattempt < 1 && $attempt) {
            $resolvedattempt = (int) $attempt->attempt;
        }
        if ($resolvedattempt < 1) {
            $resolvedattempt = 1;
        }

        // Insert or update the per-attempt feedback record.
        $qfb = $DB->get_record('local_unifiedgrader_qfb', [
            'cmid' => $this->cm->id,
            'userid' => $userid,
            'attemptnumber' => $resolvedattempt,
        ]);

        $now = time();
        if ($qfb) {
            $qfb->feedback = $feedback;
            $qfb->feedbackformat = $feedbackformat;
            $qfb->grader = $USER->id;
            $qfb->timemodified = $now;
            $DB->update_record('local_unifiedgrader_qfb', $qfb);
            $qfbid = (int) $qfb->id;
        } else {
            $qfbid = $DB->insert_record('local_unifiedgrader_qfb', (object) [
                'cmid' => $this->cm->id,
                'userid' => $userid,
                'attemptnumber' => $resolvedattempt,
                'feedback' => $feedback,
                'feedbackformat' => $feedbackformat,
                'grader' => $USER->id,
                'timemodified' => $now,
            ]);
        }

        // Process feedback files from the draft area using qfb.id as itemid.
        $feedbacktosave = $feedback;
        if ($draftitemid > 0) {
            $feedbacktosave = file_save_draft_area_files(
                $draftitemid,
                $this->context->id,
                'local_unifiedgrader',
                'quizfeedback',
                $qfbid,
                $this->get_editor_options(),
                $feedback,
            );
            // Update the record with rewritten @@PLUGINFILE@@ URLs.
            $DB->set_field('local_unifiedgrader_qfb', 'feedback', $feedbacktosave, ['id' => $qfbid]);
        }

        // Sync gradebook with the latest attempt's feedback (not necessarily
        // the one being saved — if the teacher edits an earlier attempt, the
        // gradebook should still show the latest attempt's feedback).
        $latestqfb = $this->get_latest_attempt_feedback($userid);
        if ($latestqfb) {
            $this->save_feedback_to_gradebook(
                $userid, $latestqfb->feedback ?? '', (int) $latestqfb->feedbackformat, (int) $latestqfb->id
            );
        } else {
            $this->save_feedback_to_gradebook($userid, $feedbacktosave, $feedbackformat, $qfbid);
        }

        return true;
    }

    /**
     * Get the feedback record for the latest attempt from local_unifiedgrader_qfb.
     *
     * @param int $userid
     * @return \stdClass|null
     */
    private function get_latest_attempt_feedback(int $userid): ?\stdClass {
        global $DB;

        $records = $DB->get_records('local_unifiedgrader_qfb', [
            'cmid' => $this->cm->id,
            'userid' => $userid,
        ], 'attemptnumber DESC', '*', 0, 1);

        return $records ? reset($records) : null;
    }

    /**
     * Prepare the feedback draft area for a student.
     *
     * Clears the shared draft area, copies the student's existing feedback
     * files into it, and returns the feedback HTML with draft URLs.
     *
     * @param int $userid The student user ID.
     * @param int $draftitemid The shared draft area item ID.
     * @param int $attemptnumber Attempt number (1-based), or -1 for latest.
     * @return array With key 'feedbackhtml'.
     */
    public function prepare_feedback_draft(int $userid, int $draftitemid, int $attemptnumber = -1): array {
        global $USER, $DB;

        // Resolve attempt number.
        $resolvedattempt = $attemptnumber;
        if ($resolvedattempt < 1) {
            $latest = $this->get_latest_finished_attempt($userid);
            $resolvedattempt = $latest ? (int) $latest->attempt : 1;
        }

        // Try per-attempt feedback from our table.
        $feedbacktext = '';
        $fileitemid = 0;
        $qfb = $DB->get_record('local_unifiedgrader_qfb', [
            'cmid' => $this->cm->id,
            'userid' => $userid,
            'attemptnumber' => $resolvedattempt,
        ]);

        if ($qfb) {
            $feedbacktext = $qfb->feedback ?? '';
            $fileitemid = (int) $qfb->id;
        } else {
            // Fall back to gradebook feedback (backwards compat).
            $gradeitem = \grade_item::fetch([
                'itemtype' => 'mod',
                'itemmodule' => 'quiz',
                'iteminstance' => $this->quiz->id,
                'itemnumber' => 0,
                'courseid' => $this->course->id,
            ]);
            if ($gradeitem) {
                $gradegrade = \grade_grade::fetch([
                    'itemid' => $gradeitem->id,
                    'userid' => $userid,
                ]);
                if ($gradegrade) {
                    $feedbacktext = $gradegrade->feedback ?? '';
                    $fileitemid = (int) $gradegrade->id;
                }
            }
        }

        // Clear existing draft files from the previous student.
        $fs = get_file_storage();
        $usercontext = \context_user::instance($USER->id);
        $fs->delete_area_files($usercontext->id, 'user', 'draft', $draftitemid);

        // Copy this student's feedback files from permanent storage into the draft area.
        if ($fileitemid) {
            $files = $fs->get_area_files(
                $this->context->id,
                'local_unifiedgrader',
                'quizfeedback',
                $fileitemid,
            );
            $filerecord = [
                'contextid' => $usercontext->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftitemid,
            ];
            foreach ($files as $file) {
                if ($file->is_directory() && $file->get_filepath() === '/') {
                    continue;
                }
                $fs->create_file_from_storedfile($filerecord, $file);
            }
        }

        // Rewrite @@PLUGINFILE@@ URLs to draftfile.php URLs for the editor.
        if (!empty($feedbacktext)) {
            $feedbacktext = file_rewrite_pluginfile_urls(
                $feedbacktext,
                'draftfile.php',
                $usercontext->id,
                'user',
                'draft',
                $draftitemid,
                $this->get_editor_options(),
            );
        }

        return ['feedbackhtml' => $feedbacktext];
    }

    /**
     * Get editor options for the feedback editor.
     *
     * @return array Editor options compatible with file_save_draft_area_files.
     */
    private function get_editor_options(): array {
        global $CFG;
        return [
            'maxfiles' => -1,
            'maxbytes' => $CFG->maxbytes,
            'context' => $this->context,
            'subdirs' => true,
        ];
    }

    /**
     * Get essay question file attachments from the latest attempt.
     *
     * @param int $userid
     * @return array
     */
    public function get_submission_files(int $userid): array {
        $attempt = $this->get_latest_finished_attempt($userid);
        if (!$attempt) {
            return [];
        }
        return $this->get_submission_files_for_attempt($attempt);
    }

    /**
     * Get essay question file attachments from a specific attempt.
     *
     * @param \stdClass $attempt The attempt record.
     * @return array
     */
    private function get_submission_files_for_attempt(\stdClass $attempt): array {
        $quba = question_engine::load_questions_usage_by_activity($attempt->uniqueid);
        $slots = $quba->get_slots();
        $result = [];

        foreach ($slots as $slot) {
            $qa = $quba->get_question_attempt($slot);
            $question = $qa->get_question();

            // Only essay questions have file attachments.
            if (!($question instanceof \qtype_essay_question)) {
                continue;
            }

            $files = $qa->get_last_qt_files('attachments', $this->context->id);
            foreach ($files as $file) {
                $downloadurl = $qa->get_response_file_url($file);
                $previewurl = new \moodle_url('/local/unifiedgrader/preview_file.php', [
                    'fileid' => $file->get_id(),
                    'cmid' => $this->cm->id,
                ]);
                $result[] = [
                    'fileid' => (int) $file->get_id(),
                    'filename' => $file->get_filename(),
                    'mimetype' => $file->get_mimetype(),
                    'filesize' => (int) $file->get_filesize(),
                    'url' => $downloadurl,
                    'previewurl' => $previewurl->out(false),
                ];
            }
        }

        return $result;
    }

    /**
     * Check feature support.
     *
     * @param string $feature
     * @return bool
     */
    public function supports_feature(string $feature): bool {
        return match ($feature) {
            'rubric', 'markingguide' => false,
            'onlinetext' => true,
            'filesubmission' => $this->has_essay_questions(),
            'blindmarking' => false,
            'annotations' => false,
            default => false,
        };
    }

    /**
     * Check whether the quiz grade is released and visible to the student.
     *
     * A grade is considered released when:
     * 1. A quiz_grades record exists with a non-null grade.
     * 2. The quiz review options allow marks visibility based on the
     *    current state (open vs closed). This is consistent with
     *    set_grades_posted() which toggles the same bitmask bits.
     *
     * @param int $userid
     * @return bool
     */
    public function is_grade_released(int $userid): bool {
        global $DB;

        $quizgrade = $DB->get_record('quiz_grades', [
            'quiz' => $this->quiz->id,
            'userid' => $userid,
        ]);
        if (!$quizgrade || $quizgrade->grade === null) {
            return false;
        }

        // Review option bit constants from \mod_quiz\question\display_options.
        $laterwhileopen = 0x00100;
        $afterclose     = 0x00010;
        $reviewmarks = (int) $this->quiz->reviewmarks;
        $timeclose = (int) ($this->quiz->timeclose ?? 0);

        if ($timeclose > 0 && time() >= $timeclose) {
            // Quiz is closed — check AFTER_CLOSE bit.
            return (bool) ($reviewmarks & $afterclose);
        }
        // Quiz is still open (or no close date) — check LATER_WHILE_OPEN bit.
        return (bool) ($reviewmarks & $laterwhileopen);
    }

    /**
     * Post or unpost quiz grades by updating review options.
     *
     * Unlike assignments and forums, quiz grade visibility is controlled by
     * the quiz's review options (reviewmarks/reviewmaxmarks bitmasks), not
     * the gradebook's hidden flag. This override updates those bitmasks so
     * the change persists through grade recalculations.
     *
     * Scheduling (hidden > 1) is not supported because review options are
     * state-based (open/closed), not date-based.
     *
     * @param int $hidden 0 = post (visible), 1 = hide. Timestamps not supported.
     * @throws \moodle_exception If a timestamp is passed (scheduling not supported).
     */
    public function set_grades_posted(int $hidden): void {
        global $DB;

        if ($hidden > 1) {
            throw new \moodle_exception('quiz_post_grades_no_schedule', 'local_unifiedgrader');
        }

        $quiz = $DB->get_record('quiz', ['id' => $this->cm->instance], '*', MUST_EXIST);

        // Review option bit constants from \mod_quiz\question\display_options.
        $laterwhileopen = 0x00100;
        $afterclose     = 0x00010;
        $mask = $laterwhileopen | $afterclose;

        if ($hidden === 0) {
            // Post: enable marks + max marks for LATER_WHILE_OPEN and AFTER_CLOSE.
            $quiz->reviewmarks    = $quiz->reviewmarks | $mask;
            $quiz->reviewmaxmarks = $quiz->reviewmaxmarks | $mask;
        } else {
            // Unpost: clear marks + max marks for LATER_WHILE_OPEN and AFTER_CLOSE.
            $quiz->reviewmarks    = $quiz->reviewmarks & ~$mask;
            $quiz->reviewmaxmarks = $quiz->reviewmaxmarks & ~$mask;
        }

        $DB->update_record('quiz', $quiz);

        // Re-sync grade item hidden status with the updated review options.
        quiz_grade_item_update($quiz);
    }

    /**
     * Get the effective due date for a specific user.
     *
     * When the quizaccess_duedate plugin is installed, delegates to its override
     * manager (which handles user overrides, group overrides, and the quiz default).
     * Otherwise falls back to native quiz_overrides.timeclose.
     *
     * @param int $userid The user ID.
     * @return int The effective due date timestamp (0 if no due date).
     */
    public function get_effective_duedate(int $userid): int {
        if (class_exists('\quizaccess_duedate\override_manager')) {
            return (int) \quizaccess_duedate\override_manager::get_effective_duedate(
                $this->quiz->id, $userid
            );
        }

        global $DB;

        $globaltimeclose = (int) ($this->quiz->timeclose ?? 0);

        $override = $DB->get_field('quiz_overrides', 'timeclose', [
            'quiz' => $this->quiz->id,
            'userid' => $userid,
        ]);
        if ($override !== false && $override !== null) {
            return (int) $override;
        }

        return $globaltimeclose;
    }

    /**
     * Get the user-level override for a student.
     *
     * @param int $userid The student user ID.
     * @return array|null Override data or null.
     */
    public function get_user_override(int $userid): ?array {
        global $DB;

        $record = $DB->get_record('quiz_overrides', [
            'quiz' => $this->quiz->id,
            'userid' => $userid,
        ]);

        if (!$record) {
            return null;
        }

        return [
            'id' => (int) $record->id,
            'timeopen' => $record->timeopen !== null ? (int) $record->timeopen : null,
            'timeclose' => $record->timeclose !== null ? (int) $record->timeclose : null,
            'timelimit' => $record->timelimit !== null ? (int) $record->timelimit : null,
            'attempts' => $record->attempts !== null ? (int) $record->attempts : null,
            'password' => $record->password,
        ];
    }

    /**
     * Delete the user-level override for a student.
     *
     * @param int $userid The student user ID.
     * @return bool True on success.
     */
    public function delete_user_override(int $userid): bool {
        global $DB;

        $record = $DB->get_record('quiz_overrides', [
            'quiz' => $this->quiz->id,
            'userid' => $userid,
        ]);

        if (!$record) {
            return true;
        }

        $this->quizobj->get_override_manager()->delete_overrides(overrideids: [$record->id]);

        return true;
    }

    // -------------------------------------------------------------------------
    // Duedate plugin extension methods.
    // -------------------------------------------------------------------------

    /**
     * Get the duedate plugin extension for a user (if any).
     *
     * @param int $userid The student user ID.
     * @return array|null Array with 'id' and 'duedate', or null if none.
     */
    public function get_duedate_extension(int $userid): ?array {
        if (!class_exists('\quizaccess_duedate\override_manager')) {
            return null;
        }

        global $DB;
        $record = $DB->get_record('quizaccess_duedate_overrides', [
            'quizid' => $this->quiz->id,
            'userid' => $userid,
        ]);
        if (!$record) {
            return null;
        }
        return [
            'id' => (int) $record->id,
            'duedate' => (int) $record->duedate,
        ];
    }

    /**
     * Save a duedate plugin extension for a user (create or update).
     *
     * @param int $userid The student user ID.
     * @param int $duedate Extension due date timestamp.
     */
    public function save_duedate_extension(int $userid, int $duedate): void {
        if (!class_exists('\quizaccess_duedate\override_manager')) {
            throw new \coding_exception('quizaccess_duedate plugin is not installed');
        }

        global $DB;

        $data = new \stdClass();
        $data->quizid = $this->quiz->id;
        $data->userid = $userid;
        $data->groupid = null;
        $data->duedate = $duedate;

        // Check for existing override to update.
        $existing = $DB->get_record('quizaccess_duedate_overrides', [
            'quizid' => $this->quiz->id,
            'userid' => $userid,
        ]);
        if ($existing) {
            $data->id = (int) $existing->id;
        }

        \quizaccess_duedate\override_manager::save_override($data);
        \quizaccess_duedate\override_manager::update_calendar_event(
            $data, $this->quiz->name, $this->course->id
        );
        \quizaccess_duedate\override_manager::recalculate_grades_for_override($data);
    }

    /**
     * Delete the duedate plugin extension for a user.
     *
     * @param int $userid The student user ID.
     */
    public function delete_duedate_extension(int $userid): void {
        if (!class_exists('\quizaccess_duedate\override_manager')) {
            return;
        }

        global $DB;
        $record = $DB->get_record('quizaccess_duedate_overrides', [
            'quizid' => $this->quiz->id,
            'userid' => $userid,
        ]);
        if (!$record) {
            return;
        }

        \quizaccess_duedate\override_manager::delete_calendar_event($record);
        \quizaccess_duedate\override_manager::delete_override((int) $record->id);
        \quizaccess_duedate\override_manager::recalculate_grades_for_user(
            $this->quiz->id, $userid
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers.
    // -------------------------------------------------------------------------

    /**
     * Get the quiz-level duedate from the quizaccess_duedate plugin.
     *
     * @return int Timestamp or 0.
     */
    /**
     * Calculate the late penalty percentage from the quizaccess_duedate plugin.
     *
     * Mirrors the observer's logic: checks the first finished attempt's submission
     * time against the user's effective due date (which honours extensions).
     * Returns null if the duedate plugin is not installed, penalties are disabled,
     * or no penalty applies (on time or extension covers the submission).
     *
     * @param int $userid Student user ID.
     * @return int|null Penalty percentage, or null if no penalty.
     */
    public function get_duedate_late_penalty_pct(int $userid): ?int {
        global $DB;

        if (!class_exists('\quizaccess_duedate\override_manager')) {
            return null;
        }

        // Check if penalties are enabled for this quiz.
        $settings = $DB->get_record('quizaccess_duedate_instances', ['quizid' => $this->quiz->id]);
        if (!$settings || !$settings->penaltyenabled || !$settings->duedate) {
            return null;
        }

        // Resolve the effective due date for this user (honours extensions/overrides).
        $effectiveduedate = \quizaccess_duedate\override_manager::get_effective_duedate(
            $this->quiz->id, $userid,
        );
        if (!$effectiveduedate) {
            return null;
        }

        // Find the first finished attempt (same logic as the observer).
        $firstattempt = $DB->get_record_sql(
            'SELECT timefinish FROM {quiz_attempts}
              WHERE quiz = ? AND userid = ? AND timefinish > 0
              ORDER BY timefinish ASC LIMIT 1',
            [$this->quiz->id, $userid],
        );
        if (!$firstattempt) {
            return null;
        }

        // Calculate penalty based on days late.
        if ($firstattempt->timefinish <= $effectiveduedate) {
            return null; // On time — no penalty.
        }

        $secondslate = $firstattempt->timefinish - $effectiveduedate;
        $dayslate = ceil($secondslate / 86400);
        $totalpenalty = $dayslate * (float) $settings->penalty;

        if ($settings->penaltycapenabled && $settings->penaltycap > 0) {
            $totalpenalty = min($totalpenalty, (float) $settings->penaltycap);
        } else {
            $totalpenalty = min($totalpenalty, 100);
        }

        return $totalpenalty > 0 ? (int) round($totalpenalty) : null;
    }

    private function get_duedate_plugin_duedate(): int {
        global $DB;
        $settings = $DB->get_record('quizaccess_duedate_instances', ['quizid' => $this->quiz->id]);
        if ($settings && $settings->duedate) {
            return (int) $settings->duedate;
        }
        return 0;
    }

    /**
     * Get the latest finished attempt for a user.
     *
     * @param int $userid
     * @return \stdClass|null Attempt record or null.
     */
    private function get_latest_finished_attempt(int $userid): ?\stdClass {
        $attempts = quiz_get_user_attempts($this->quiz->id, $userid, 'finished');
        if (empty($attempts)) {
            return null;
        }
        return end($attempts);
    }

    /**
     * Find a specific finished attempt by its 1-based attempt number.
     *
     * @param int $userid The user ID.
     * @param int $attemptnumber The 1-based attempt number.
     * @return \stdClass|null The attempt record or null.
     */
    private function get_attempt_by_number(int $userid, int $attemptnumber): ?\stdClass {
        $attempts = quiz_get_user_attempts($this->quiz->id, $userid, 'finished');
        foreach ($attempts as $attempt) {
            if ((int) $attempt->attempt === $attemptnumber) {
                return $attempt;
            }
        }
        return null;
    }

    /**
     * Resolve display status from attempt stats and grade data.
     *
     * @param \stdClass|null $stats Attempt stats (attemptcount, hasfinished, hasinprogress).
     * @param \stdClass|null $grade Quiz grade record.
     * @param bool $hasneedsgrading Whether user has questions needing manual grading.
     * @return string
     */
    private function resolve_status(?\stdClass $stats, ?\stdClass $grade, bool $hasneedsgrading): string {
        if (!$stats) {
            return 'nosubmission';
        }

        $hasfinished = (int) ($stats->hasfinished ?? 0) > 0;
        $hasinprogress = (int) ($stats->hasinprogress ?? 0) > 0;

        if (!$hasfinished && $hasinprogress) {
            return 'draft';
        }

        if ($hasfinished && $hasneedsgrading) {
            return 'submitted';
        }

        if ($hasfinished && $grade && $grade->grade !== null) {
            return 'graded';
        }

        if ($hasfinished) {
            return 'graded';
        }

        return 'nosubmission';
    }

    /**
     * Get user IDs that have questions needing manual grading.
     *
     * @return array Keyed by userid => true.
     */
    private function get_users_needing_grading(): array {
        global $DB;

        // Find all finished quiz attempts, then check for needsgrading questions.
        $sql = "SELECT DISTINCT qa_outer.userid
                  FROM {quiz_attempts} qa_outer
                  JOIN {question_attempts} qatt ON qatt.questionusageid = qa_outer.uniqueid
                  JOIN {question_attempt_steps} qas ON qas.questionattemptid = qatt.id
                 WHERE qa_outer.quiz = :quizid
                   AND qa_outer.preview = 0
                   AND qa_outer.state = :finished
                   AND qas.sequencenumber = (
                       SELECT MAX(qas2.sequencenumber)
                         FROM {question_attempt_steps} qas2
                        WHERE qas2.questionattemptid = qatt.id
                   )
                   AND qas.state = :needsgrading";
        $records = $DB->get_records_sql($sql, [
            'quizid' => $this->quiz->id,
            'finished' => quiz_attempt::FINISHED,
            'needsgrading' => 'needsgrading',
        ]);

        $result = [];
        foreach ($records as $record) {
            $result[(int) $record->userid] = true;
        }
        return $result;
    }

    /**
     * Get manual (essay) question definitions and current fill data from a QUBA.
     *
     * @param \question_usage_by_activity $quba
     * @return array With keys 'criteria' (definition) and 'fill' (current data).
     */
    private function get_manual_questions(\question_usage_by_activity $quba): array {
        $slots = $quba->get_slots();
        $criteria = [];
        $fill = [];

        $questionnum = 0;
        foreach ($slots as $slot) {
            $questionnum++;
            $qa = $quba->get_question_attempt($slot);
            $question = $qa->get_question();
            $state = $qa->get_state();

            // Include questions that use manual grading behaviour.
            $behaviour = $qa->get_behaviour_name();
            if ($behaviour !== 'manualgraded') {
                continue;
            }

            $maxmark = (float) $qa->get_max_mark();
            $questionname = $question->name ?? get_string('question', 'question');

            $criteria[] = [
                'id' => (int) $slot,
                'shortname' => 'Q' . $questionnum . ': ' . \core_text::substr(
                    format_string($questionname), 0, 60
                ),
                'description' => strip_tags(
                    format_text($question->questiontext ?? '', $question->questiontextformat ?? FORMAT_HTML,
                        ['context' => $this->context])
                ),
                'descriptionmarkers' => format_text(
                    $question->graderinfo ?? '',
                    $question->graderinfoformat ?? FORMAT_HTML,
                    ['context' => $this->context],
                ),
                'maxscore' => $maxmark,
            ];

            // Current fill data.
            $mark = $qa->get_mark();
            [$comment, $commentformat, $step] = $qa->get_manual_comment();

            $fill[(string) $slot] = [
                'score' => $mark !== null ? round($mark, 5) : '',
                'remark' => html_to_text($comment ?? '', 0, false),
            ];
        }

        return ['criteria' => $criteria, 'fill' => $fill];
    }

    /**
     * Save manual grades for individual questions in an attempt.
     *
     * @param \stdClass $attempt The attempt record.
     * @param array $questions Per-slot grading data: {slot: {mark, comment}, ...}.
     */
    private function save_manual_question_grades(\stdClass $attempt, array $questions): void {
        global $DB;

        $quba = question_engine::load_questions_usage_by_activity($attempt->uniqueid);

        foreach ($questions as $slot => $data) {
            $slot = (int) $slot;
            $mark = isset($data['mark']) && $data['mark'] !== '' ? (float) $data['mark'] : null;
            $comment = $data['comment'] ?? '';

            if ($mark !== null) {
                // Mark provided — save both mark and comment.
                $quba->manual_grade($slot, $comment, $mark, FORMAT_HTML);
            } else if (!empty($comment)) {
                // Comment-only update — reuse the existing mark if available.
                // When $existingmark is null (never graded), manual_grade() with
                // null mark saves just the comment via the question engine's
                // comment-only path (no grade change, state preserved).
                $qa = $quba->get_question_attempt($slot);
                $existingmark = $qa->get_mark();
                $quba->manual_grade($slot, $comment, $existingmark, FORMAT_HTML);
            }
        }

        // Save question engine data.
        question_engine::save_questions_usage_by_activity($quba);

        // Update attempt sumgrades from the QUBA total.
        $totalmark = $quba->get_total_mark();
        $attempt->sumgrades = $totalmark;
        $attempt->timemodified = time();
        $DB->update_record('quiz_attempts', $attempt);

        // Recompute the final quiz grade (handles grade method: highest/average/first/last).
        $gradecalc = grade_calculator::create($this->quizobj);
        $gradecalc->recompute_final_grade((int) $attempt->userid);

        // Sync to gradebook.
        quiz_update_grades($this->quiz, (int) $attempt->userid);
    }

    /**
     * Render a quiz attempt as HTML for the preview panel.
     *
     * @param \question_usage_by_activity $quba
     * @param array $slots Question slots.
     * @param \stdClass $attempt The attempt record.
     * @return string HTML content.
     */
    private function render_attempt_as_html(
        \question_usage_by_activity $quba,
        array $slots,
        \stdClass $attempt,
    ): string {
        $html = '';

        // Attempt summary header.
        $attemptnum = (int) $attempt->attempt;
        $startdate = userdate($attempt->timestart);
        $finishdate = $attempt->timefinish ? userdate($attempt->timefinish) : '—';
        $sumgrades = $attempt->sumgrades !== null ? quiz_format_grade($this->quiz, $attempt->sumgrades) : '—';
        $maxsum = quiz_format_grade($this->quiz, $this->quiz->sumgrades);
        $finalgrade = null;

        global $DB;
        $quizgrade = $DB->get_record('quiz_grades', ['quiz' => $this->quiz->id, 'userid' => $attempt->userid]);
        if ($quizgrade) {
            $finalgrade = quiz_format_grade($this->quiz, $quizgrade->grade);
        }

        $html .= '<div class="card mb-3">';
        $html .= '<div class="card-header py-2"><strong>'
            . get_string('attempt', 'quiz', $attemptnum) . '</strong></div>';
        $html .= '<div class="card-body py-2 small">';
        $html .= '<div>' . get_string('startedon', 'quiz') . ': ' . $startdate . '</div>';
        $html .= '<div>' . get_string('completedon', 'quiz') . ': ' . $finishdate . '</div>';
        $html .= '<div>' . get_string('marks', 'quiz') . ': ' . $sumgrades . ' / ' . $maxsum . '</div>';
        if ($finalgrade !== null) {
            $html .= '<div>' . get_string('grade', 'local_unifiedgrader') . ': ' . $finalgrade
                . ' / ' . quiz_format_grade($this->quiz, $this->quiz->grade) . '</div>';
        }
        $html .= '</div></div>';

        // Render each question.
        $questionnum = 0;
        foreach ($slots as $slot) {
            $questionnum++;
            $qa = $quba->get_question_attempt($slot);
            $question = $qa->get_question();
            $state = $qa->get_state();

            $questionname = $question->name ?? '';
            $mark = $qa->get_mark();
            $maxmark = $qa->get_max_mark();
            $stateclass = $this->get_state_badge_class($state);
            $statestring = $state->default_string(true);

            // Question card.
            $html .= '<div class="card mb-2">';

            // Header with question number, name, mark, state.
            $html .= '<div class="card-header py-1 d-flex justify-content-between align-items-center">';
            $html .= '<span class="small fw-bold">Q' . $questionnum . '. '
                . format_string($questionname) . '</span>';
            $html .= '<span>';
            if ($mark !== null) {
                $html .= '<span class="badge bg-secondary me-1">'
                    . round($mark, 2) . ' / ' . round($maxmark, 2) . '</span>';
            } else {
                $html .= '<span class="badge bg-secondary me-1">'
                    . '— / ' . round($maxmark, 2) . '</span>';
            }
            $html .= '<span class="badge ' . $stateclass . '">' . $statestring . '</span>';
            $html .= '</span>';
            $html .= '</div>';

            // Question text.
            $html .= '<div class="card-body py-2">';
            $questiontext = format_text(
                $question->questiontext ?? '',
                $question->questiontextformat ?? FORMAT_HTML,
                ['context' => $this->context],
            );
            $html .= '<div class="small text-muted mb-2">' . $questiontext . '</div>';

            // Student response.
            $html .= '<div class="border-top pt-2">';
            $html .= '<div class="small fw-bold mb-1">'
                . get_string('response', 'local_unifiedgrader') . ':</div>';

            // For essay questions, show the full response text.
            if ($question instanceof \qtype_essay_question) {
                $response = $qa->get_last_qt_data();
                $answertext = $response['answer'] ?? '';
                $answerformat = $response['answerformat'] ?? FORMAT_HTML;
                $formatted = format_text($answertext, $answerformat, ['context' => $this->context]);
                $html .= '<div class="small">' . $formatted . '</div>';
            } else {
                // For other question types, show the response summary.
                $responsesummary = $qa->get_response_summary();
                $html .= '<div class="small">' . s($responsesummary) . '</div>';
            }

            $html .= '</div>';

            // Show manual comment if present.
            [$comment, $commentformat, $commentstep] = $qa->get_manual_comment();
            if ($comment) {
                $html .= '<div class="border-top pt-2 mt-2">';
                $html .= '<div class="small fw-bold mb-1">'
                    . get_string('teachercomment', 'local_unifiedgrader') . ':</div>';
                $html .= '<div class="small text-muted">'
                    . format_text($comment, $commentformat ?? FORMAT_HTML, ['context' => $this->context])
                    . '</div>';
                $html .= '</div>';
            }

            $html .= '</div></div>';
        }

        return $html;
    }

    /**
     * Get Bootstrap badge class for a question state.
     *
     * @param \question_state $state
     * @return string CSS class.
     */
    private function get_state_badge_class(\question_state $state): string {
        $summary = $state->get_summary_state();
        return match ($summary) {
            'needsgrading' => 'bg-warning text-dark',
            'manuallygraded' => 'bg-info',
            'autograded' => 'bg-success',
            default => 'bg-secondary',
        };
    }

    /**
     * Check if the quiz has any essay questions.
     *
     * @return bool
     */
    private function has_essay_questions(): bool {
        global $DB;

        $sql = "SELECT COUNT(*)
                  FROM {quiz_slots} qs
                  JOIN {question_references} qr ON qr.component = 'mod_quiz'
                       AND qr.questionarea = 'slot'
                       AND qr.itemid = qs.id
                  JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                  JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                  JOIN {question} q ON q.id = qv.questionid
                 WHERE qs.quizid = :quizid AND q.qtype = :qtype";
        return $DB->count_records_sql($sql, ['quizid' => $this->quiz->id, 'qtype' => 'essay']) > 0;
    }

    /**
     * Return an empty submission array.
     *
     * @param int $userid
     * @return array
     */
    private function empty_submission(int $userid): array {
        return [
            'userid' => $userid,
            'status' => 'nosubmission',
            'content' => '',
            'files' => [],
            'onlinetext' => '',
            'timecreated' => 0,
            'timemodified' => 0,
            'attemptnumber' => 0,
            'commentcount' => submission_comment_manager::count_comments($this->cm->id, $userid),
        ];
    }

    /**
     * Save feedback text (and associated files) to the gradebook.
     *
     * Sets the grade_grade.feedbackfiles property so that Moodle's built-in
     * grade_grade::add_feedback_files() copies our files to the 'grade'/'feedback'
     * filearea. This makes embedded audio/video/images work in the gradebook and
     * student activity overview.
     *
     * @param int $userid
     * @param string $feedback
     * @param int $feedbackformat
     * @param int $qfbid The local_unifiedgrader_qfb record ID (used as file itemid).
     */
    private function save_feedback_to_gradebook(int $userid, string $feedback, int $feedbackformat, int $qfbid = 0): void {
        $gradeitem = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $this->quiz->id,
            'itemnumber' => 0,
            'courseid' => $this->course->id,
        ]);

        if (!$gradeitem) {
            return;
        }

        $gradegrade = \grade_grade::fetch([
            'itemid' => $gradeitem->id,
            'userid' => $userid,
        ]);

        if ($gradegrade) {
            // Load grade_item so that update_feedback_files() can run.
            // Without this, grade_grade::update_feedback_files() silently
            // skips the file copy because $this->grade_item is null after fetch().
            $gradegrade->grade_item = $gradeitem;

            $gradegrade->feedback = $feedback;
            $gradegrade->feedbackformat = $feedbackformat;

            // Tell the gradebook where to copy feedback files from.
            // Moodle's grade_grade::update_feedback_files() will delete old
            // files and copy ours to the 'grade'/'feedback' filearea so they
            // render correctly in grade reports and student activity views.
            if ($qfbid > 0) {
                $gradegrade->feedbackfiles = [
                    'contextid' => $this->context->id,
                    'component' => 'local_unifiedgrader',
                    'filearea' => 'quizfeedback',
                    'itemid' => $qfbid,
                ];
            }

            $gradegrade->update('local/unifiedgrader');
        }
    }
}
