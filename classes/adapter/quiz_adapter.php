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
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\adapter;

defined('MOODLE_INTERNAL') || die();

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
        return [
            'id' => (int) $this->cm->id,
            'name' => format_string($this->quiz->name),
            'type' => 'quiz',
            'duedate' => (int) ($this->quiz->timeclose ?? 0),
            'cutoffdate' => (int) ($this->quiz->timeclose ?? 0),
            'maxgrade' => (float) $this->quiz->grade,
            'intro' => format_text(
                $this->quiz->intro ?? '',
                $this->quiz->introformat ?? FORMAT_HTML,
                ['context' => $this->context],
            ),
            'gradingmethod' => 'simple',
            'teamsubmission' => false,
            'blindmarking' => false,
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

        $groupid = $filters['group'] ?? 0;

        // Get enrolled users who can attempt quizzes.
        $enrolledusers = get_enrolled_users(
            $this->context,
            'mod/quiz:attempt',
            $groupid,
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

        $result = [];
        foreach ($enrolledusers as $user) {
            $userid = (int) $user->id;
            $stats = $attemptstats[$userid] ?? null;
            $usergrade = $grades[$userid] ?? null;

            $status = $this->resolve_status($stats, $usergrade, isset($needsgrading[$userid]));

            $userpicture = new \user_picture($user);
            $userpicture->size = 64;
            $profileimageurl = $userpicture->get_url($PAGE)->out(false);

            $entry = [
                'id' => $userid,
                'fullname' => fullname($user),
                'email' => $user->email,
                'profileimageurl' => $profileimageurl,
                'status' => $status,
                'submittedat' => $stats ? (int) ($stats->lastfinish ?? 0) : 0,
                'gradevalue' => $usergrade ? (float) $usergrade->grade : null,
            ];

            // Apply status filter.
            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                if ($entry['status'] !== $filters['status']) {
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
     * Get quiz attempt rendered as HTML for preview.
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

        // Load question usage.
        $quba = question_engine::load_questions_usage_by_activity($attempt->uniqueid);
        $slots = $quba->get_slots();

        // Render each question as HTML.
        $content = $this->render_attempt_as_html($quba, $slots, $attempt);

        return [
            'userid' => $userid,
            'status' => 'submitted',
            'content' => $content,
            'files' => $this->get_submission_files($userid),
            'onlinetext' => '',
            'timecreated' => (int) $attempt->timestart,
            'timemodified' => (int) ($attempt->timefinish ?: $attempt->timemodified),
            'attemptnumber' => (int) $attempt->attempt,
        ];
    }

    /**
     * Get current grade and per-question manual grading data.
     *
     * @param int $userid
     * @return array
     */
    public function get_grade_data(int $userid): array {
        global $DB;

        // Get overall quiz grade.
        $quizgrade = $DB->get_record('quiz_grades', [
            'quiz' => $this->quiz->id,
            'userid' => $userid,
        ]);
        $hasgrade = $quizgrade && $quizgrade->grade !== null;

        // Get feedback from gradebook (quiz has no feedback table).
        $feedbacktext = '';
        $feedbackformat = (int) FORMAT_HTML;
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
                $feedbacktext = $gradegrade->feedback;
                $feedbackformat = (int) ($gradegrade->feedbackformat ?? FORMAT_HTML);
            }
        }

        // Build per-question manual grading data from the latest finished attempt.
        $gradingdefinition = '';
        $rubricdata = '';
        $attempt = $this->get_latest_finished_attempt($userid);

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

        return [
            'grade' => $hasgrade ? (float) $quizgrade->grade : null,
            'feedback' => $feedbacktext,
            'feedbackformat' => $feedbackformat,
            'rubricdata' => $rubricdata,
            'gradingdefinition' => $gradingdefinition,
            'timegraded' => $quizgrade ? (int) ($quizgrade->timemodified ?? 0) : 0,
            'grader' => 0,
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
     * @return bool
     */
    public function save_grade(
        int $userid,
        ?float $grade,
        string $feedback,
        int $feedbackformat = FORMAT_HTML,
        array $advancedgradingdata = [],
    ): bool {
        global $USER;

        $attempt = $this->get_latest_finished_attempt($userid);

        if (!empty($advancedgradingdata['method']) && $advancedgradingdata['method'] === 'quizmanual'
                && !empty($advancedgradingdata['questions']) && $attempt) {
            // Per-question manual grading.
            $this->save_manual_question_grades($attempt, $advancedgradingdata['questions']);
        }

        // Save feedback to gradebook.
        $this->save_feedback_to_gradebook($userid, $feedback, $feedbackformat);

        return true;
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
     * Quizzes do not support PDF annotations, so grades are never "released"
     * in the annotation feedback sense.
     *
     * @param int $userid
     * @return bool Always false.
     */
    public function is_grade_released(int $userid): bool {
        return false;
    }

    // -------------------------------------------------------------------------
    // Private helpers.
    // -------------------------------------------------------------------------

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
                'descriptionmarkers' => $question->graderinfo ?? '',
                'maxscore' => $maxmark,
            ];

            // Current fill data.
            $mark = $qa->get_mark();
            [$comment, $commentformat, $step] = $qa->get_manual_comment();

            $fill[(string) $slot] = [
                'score' => $mark !== null ? round($mark, 5) : '',
                'remark' => $comment ?? '',
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

            // Only grade if a mark is provided.
            if ($mark !== null) {
                $quba->manual_grade($slot, $comment, $mark, FORMAT_HTML);
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
        ];
    }

    /**
     * Save feedback text to the gradebook.
     *
     * @param int $userid
     * @param string $feedback
     * @param int $feedbackformat
     */
    private function save_feedback_to_gradebook(int $userid, string $feedback, int $feedbackformat): void {
        if (empty($feedback)) {
            return;
        }

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
            $gradegrade->feedback = $feedback;
            $gradegrade->feedbackformat = $feedbackformat;
            $gradegrade->update('local/unifiedgrader');
        }
    }
}
