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
 * Assignment adapter for the unified grading interface.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\adapter;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');
use local_unifiedgrader\submission_comment_manager;

/**
 * Concrete adapter wrapping mod_assign's internal API.
 */
class assign_adapter extends base_adapter {
    /** @var \assign The native assign instance. */
    private \assign $assign;

    /**
     * Constructor.
     *
     * @param \cm_info $cm Course module info.
     * @param \context_module $context Module context.
     * @param \stdClass $course Course record.
     */
    public function __construct(\cm_info $cm, \context_module $context, \stdClass $course) {
        parent::__construct($cm, $context, $course);
        $this->assign = new \assign($context, $cm, $course);
    }

    /**
     * Get assignment metadata.
     *
     * @return array
     */
    public function get_activity_info(): array {
        $instance = $this->assign->get_instance();
        $gradingmanager = get_grading_manager($this->context, 'mod_assign', 'submissions');
        $gradingmethod = $gradingmanager->get_active_method();

        // Grade type "None" means grade == 0 (positive = points, negative = scale).
        $gradingenabled = (int) $instance->grade !== 0;

        // Detect scale-based grading (negative grade = scale ID).
        $rawgrade = (int) $instance->grade;
        $usescale = $rawgrade < 0;
        $scaleitems = [];
        $maxgrade = (float) $instance->grade;
        if ($usescale) {
            $menu = make_grades_menu($rawgrade);
            foreach ($menu as $value => $label) {
                $scaleitems[] = ['value' => (int) $value, 'label' => (string) $label];
            }
            $maxgrade = (float) count($scaleitems);
        }

        return [
            'id' => (int) $this->cm->id,
            'name' => format_string($instance->name),
            'type' => 'assign',
            'duedate' => (int) $instance->duedate,
            'cutoffdate' => (int) $instance->cutoffdate,
            'maxgrade' => $maxgrade,
            'usescale' => $usescale,
            'scaleitems' => $scaleitems,
            'intro' => format_text(
                $instance->intro,
                $instance->introformat,
                ['context' => $this->context],
            ),
            // When grading is disabled (grade type "None"), force simple so the
            // client does not try to render an advanced grading form.
            'gradingmethod' => $gradingenabled ? ($gradingmethod ?: 'simple') : 'simple',
            'gradingdisabled' => !$gradingenabled,
            'teamsubmission' => (bool) $instance->teamsubmission,
            'blindmarking' => (bool) $instance->blindmarking,
            'canmanageoverrides' => has_capability('mod/assign:manageoverrides', $this->context),
            'maxattempts' => (int) $instance->maxattempts,
            'gradepenaltyenabled' => !empty($instance->gradepenalty),
        ];
    }

    /**
     * Get participant list with submission status.
     *
     * @param array $filters Optional: status, group, search, sort, sortdir.
     * @return array
     */
    public function get_participants(array $filters = []): array {
        global $PAGE;

        $groupids = $this->get_group_ids($filters);
        $instance = $this->assign->get_instance();

        // Fetch participants (list_participants only takes a single group ID).
        if (empty($groupids)) {
            $participants = $this->assign->list_participants(0, false);
        } else if (count($groupids) === 1) {
            $participants = $this->assign->list_participants(reset($groupids), false);
        } else {
            // Multiple groups: fetch per group and merge by user ID.
            $participants = [];
            foreach ($groupids as $gid) {
                $participants += $this->assign->list_participants($gid, false);
            }
        }

        // Exclude suspended enrolments — list_participants() may include
        // them depending on user preferences and capabilities.
        $activeids = $this->get_enrolled_users_multigroup($this->context, '', $groupids, 'u.id');
        $participants = array_intersect_key($participants, $activeids);

        // Batch-load user overrides to avoid N+1 queries.
        global $DB;
        $overrides = $DB->get_records_select(
            'assign_overrides',
            'assignid = :assignid AND userid IS NOT NULL',
            ['assignid' => $instance->id],
            '',
            'userid, duedate',
        );
        $overrideset = [];
        foreach ($overrides as $ov) {
            $overrideset[(int) $ov->userid] = $ov->duedate !== null ? (int) $ov->duedate : null;
        }

        // Batch-load extension due dates from user flags.
        $extensions = $DB->get_records_select(
            'assign_user_flags',
            'assignment = :assignid AND extensionduedate > 0',
            ['assignid' => $instance->id],
            '',
            'userid, extensionduedate',
        );
        $extensionset = [];
        foreach ($extensions as $ext) {
            $extensionset[(int) $ext->userid] = (int) $ext->extensionduedate;
        }

        $globalduedate = (int) $instance->duedate;

        $result = [];
        foreach ($participants as $participant) {
            $submission = $this->assign->get_user_submission($participant->id, false) ?: null;
            $grade = $this->assign->get_user_grade($participant->id, false) ?: null;
            $status = $this->resolve_status($submission, $grade);

            // Build display name (handle blind marking).
            $fullname = $instance->blindmarking
                ? get_string('hiddenuser', 'assign') . ' ' . $this->assign->get_uniqueid_for_user($participant->id)
                : fullname($participant);

            // Profile image URL.
            $userpicture = new \user_picture($participant);
            $userpicture->size = 64;
            $profileimageurl = $userpicture->get_url($PAGE)->out(false);

            $flags = $this->assign->get_user_flags($participant->id, false);
            $locked = ($flags && !empty($flags->locked));

            // Effective due date: override duedate > extension duedate > global duedate.
            $uid = (int) $participant->id;
            $effectiveduedate = $overrideset[$uid] ?? $extensionset[$uid] ?? $globalduedate;
            $submittedat = $submission ? (int) $submission->timemodified : 0;
            $islate = $effectiveduedate > 0 && $submittedat > 0 && $submittedat > $effectiveduedate;

            $entry = [
                'id' => $uid,
                'fullname' => $fullname,
                'email' => $instance->blindmarking ? '' : $participant->email,
                'profileimageurl' => $profileimageurl,
                'status' => $status,
                'submittedat' => $submittedat,
                'gradevalue' => ($grade && $grade->grade !== null && $grade->grade >= 0)
                    ? (float) $grade->grade : null,
                'locked' => $locked,
                'hasoverride' => isset($overrideset[$uid]),
                'hasextension' => isset($extensionset[$uid]),
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
     * Get full submission data for a user (latest attempt).
     *
     * @param int $userid
     * @return array
     */
    public function get_submission_data(int $userid): array {
        $submission = $this->assign->get_user_submission($userid, false);
        return $this->build_submission_data($userid, $submission ?: null);
    }

    /**
     * Get submission data for a specific attempt.
     *
     * @param int $userid The user ID.
     * @param int $attemptnumber Attempt number (0-based), or -1 for latest.
     * @return array
     */
    public function get_submission_data_for_attempt(int $userid, int $attemptnumber = -1): array {
        $submission = $this->assign->get_user_submission($userid, false, $attemptnumber);
        return $this->build_submission_data($userid, $submission ?: null);
    }

    /**
     * Get the list of submission attempts for a user.
     *
     * @param int $userid The user ID.
     * @return array List of arrays with keys: id, attemptnumber, status, timemodified, graded.
     */
    public function get_attempts(int $userid): array {
        $submissions = $this->assign->get_all_submissions($userid);
        $result = [];
        foreach ($submissions as $sub) {
            $grade = $this->assign->get_user_grade($userid, false, (int) $sub->attemptnumber);
            $result[] = [
                'id' => (int) $sub->attemptnumber,
                'attemptnumber' => (int) $sub->attemptnumber,
                'status' => $sub->status,
                'timemodified' => (int) $sub->timemodified,
                'graded' => ($grade && $grade->grade !== null && $grade->grade >= 0),
            ];
        }
        return $result;
    }

    /**
     * Build submission data array from a submission record.
     *
     * @param int $userid
     * @param \stdClass|null $submission
     * @return array
     */
    private function build_submission_data(int $userid, ?\stdClass $submission): array {
        $flags = $this->assign->get_user_flags($userid, false);
        $locked = ($flags && !empty($flags->locked));

        if (!$submission) {
            return [
                'userid' => $userid,
                'status' => 'nosubmission',
                'content' => '',
                'hascontent' => false,
                'files' => [],
                'onlinetext' => '',
                'timecreated' => 0,
                'timemodified' => 0,
                'attemptnumber' => 0,
                'commentcount' => 0,
                'locked' => $locked,
            ];
        }

        $onlinetext = $this->get_onlinetext($submission);

        // Get submission comment count from our custom table.
        $commentcount = submission_comment_manager::count_comments($this->cm->id, $userid);

        // Check whether any non-file submission plugins have actual student content.
        $hascontent = false;
        foreach ($this->assign->get_submission_plugins() as $plugin) {
            if (
                $plugin->is_enabled() && $plugin->is_visible()
                    && $plugin->get_type() !== 'file'
                    && !$plugin->is_empty($submission)
            ) {
                $hascontent = true;
                break;
            }
        }

        return [
            'userid' => $userid,
            'status' => $submission->status,
            'content' => $this->get_submission_content($submission),
            'hascontent' => $hascontent,
            'files' => $this->build_submission_files($submission),
            'onlinetext' => $onlinetext,
            'timecreated' => (int) $submission->timecreated,
            'timemodified' => (int) $submission->timemodified,
            'attemptnumber' => (int) $submission->attemptnumber,
            'commentcount' => $commentcount,
            'locked' => $locked,
        ];
    }

    /**
     * Get current grade and feedback for a user (latest attempt).
     *
     * @param int $userid
     * @return array
     */
    public function get_grade_data(int $userid): array {
        $grade = $this->assign->get_user_grade($userid, false);
        return $this->build_grade_data($userid, $grade ?: null);
    }

    /**
     * Get grade data for a specific attempt.
     *
     * @param int $userid The user ID.
     * @param int $attemptnumber Attempt number (0-based), or -1 for latest.
     * @return array
     */
    public function get_grade_data_for_attempt(int $userid, int $attemptnumber = -1): array {
        $grade = $this->assign->get_user_grade($userid, false, $attemptnumber);
        return $this->build_grade_data($userid, $grade ?: null);
    }

    /**
     * Build grade data array from a grade record.
     *
     * @param int $userid
     * @param \stdClass|null $grade
     * @return array
     */
    private function build_grade_data(int $userid, ?\stdClass $grade): array {
        // Get feedback comments if the feedback plugin is enabled.
        $feedbacktext = '';
        $feedbackformat = FORMAT_HTML;
        if ($grade) {
            $feedbackplugins = $this->assign->get_feedback_plugins();
            foreach ($feedbackplugins as $plugin) {
                if ($plugin->get_type() === 'comments' && $plugin->is_enabled()) {
                    $feedbacktext = $plugin->text_for_gradebook($grade);
                    break;
                }
            }
        }

        // Advanced grading: read the grading definition and current fill.
        // Skip when grading is disabled (grade type "None") — a rubric/marking
        // guide without a grade type is a non-sequitur.
        $rubricdata = null;
        $gradingdefinition = null;
        $instance = $this->assign->get_instance();

        if ((int) $instance->grade !== 0) {
            $gradingmanager = get_grading_manager($this->context, 'mod_assign', 'submissions');
            $controller = $gradingmanager->get_active_controller();

            if ($controller) {
                $gradingdefinition = $this->serialize_grading_definition($controller);

                if ($grade) {
                    $rubricdata = $this->get_rubric_fill($controller, $grade, $userid);
                }
            }
        }

        // Moodle stores assign_grades.penalty as a percentage of the student's
        // grade (deducted_points / grade * 100), not the max grade. Convert it
        // to a percentage of max grade so it matches the configured penalty rules.
        //
        // Suppress the penalty if the submission is no longer late (e.g. an
        // extension was granted after the student submitted). The penalty field
        // in assign_grades is not recalculated by Moodle core when extensions
        // change, so we must cross-check against the effective due date.
        $latepenaltypct = null;
        if ($grade && isset($grade->penalty) && $grade->penalty > 0 && $grade->grade > 0) {
            $effectiveduedate = $this->get_effective_duedate($userid);
            $submission = $this->assign->get_user_submission($userid, false);
            $submittedat = $submission ? (int) $submission->timecreated : 0;

            // Only report the penalty if the submission is actually late.
            $stilllate = $effectiveduedate > 0 && $submittedat > 0 && $submittedat > $effectiveduedate;
            if ($stilllate) {
                $maxgrade = (float) $instance->grade;
                if ($maxgrade > 0) {
                    $deductedmark = $grade->grade * $grade->penalty / 100;
                    $latepenaltypct = (int) round($deductedmark / $maxgrade * 100);
                }
            }
        }

        return [
            'grade' => ($grade && $grade->grade !== null && $grade->grade >= 0)
                ? (float) $grade->grade : null,
            'feedback' => format_text(
                $grade ? file_rewrite_pluginfile_urls(
                    $feedbacktext,
                    'pluginfile.php',
                    $this->context->id,
                    'assignfeedback_comments',
                    'feedback',
                    (int) $grade->id,
                ) : $feedbacktext,
                $feedbackformat,
                ['context' => $this->context],
            ),
            'feedbackformat' => (int) $feedbackformat,
            'rubricdata' => $rubricdata ? json_encode($rubricdata) : '',
            'gradingdefinition' => $gradingdefinition ? json_encode($gradingdefinition) : '',
            'timegraded' => $grade ? (int) $grade->timemodified : 0,
            'grader' => $grade ? (int) $grade->grader : 0,
            'latepenaltypct' => $latepenaltypct,
        ];
    }

    /**
     * Save a grade and feedback for a user.
     *
     * @param int $userid
     * @param float|null $grade
     * @param string $feedback
     * @param int $feedbackformat
     * @param array $advancedgradingdata
     * @param int $draftitemid Draft area item ID for feedback file uploads.
     * @param int $feedbackfilesdraftid Draft area item ID for feedback files (assignfeedback_file).
     * @param int $attemptnumber Attempt number (0-based), or -1 for latest.
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
        global $USER, $DB;

        // Moodle's assign::save_grade() requires attemptnumber to identify
        // which submission attempt the grade applies to.
        // When attemptnumber is -1 (default), use the latest submission's attempt.
        if ($attemptnumber >= 0) {
            $submission = $this->assign->get_user_submission($userid, false, $attemptnumber) ?: null;
        } else {
            $submission = $this->assign->get_user_submission($userid, false) ?: null;
        }
        $attemptnumber = $submission ? (int) $submission->attemptnumber : 0;

        // Check if advanced grading (rubric/marking guide) is active.
        // Skip when grading is disabled (grade type "None").
        $controller = null;
        $instance = $this->assign->get_instance();
        if ((int) $instance->grade !== 0) {
            $gradingmanager = get_grading_manager($this->context, 'mod_assign', 'submissions');
            $controller = $gradingmanager->get_active_controller();
        }

        if ($controller && empty($advancedgradingdata)) {
            // Advanced grading is active but no criteria data provided.
            // Bypass assign::save_grade() to avoid the grading form trying
            // to process null criteria data (causes foreach-on-null warnings).
            $this->save_grade_directly(
                $userid,
                $grade,
                $feedback,
                $feedbackformat,
                $attemptnumber,
                $draftitemid,
                $feedbackfilesdraftid,
            );
            return true;
        }

        $data = new \stdClass();
        $data->grade = $grade;
        $data->attemptnumber = $attemptnumber;
        $editordata = [
            'text' => $feedback,
            'format' => $feedbackformat,
        ];
        if ($draftitemid > 0) {
            $editordata['itemid'] = $draftitemid;
        }
        $data->assignfeedbackcomments_editor = $editordata;

        if (!empty($advancedgradingdata)) {
            $data->advancedgrading = $advancedgradingdata;
        }

        // Add feedback files draft ID so assignfeedback_file::save() can find it.
        // The plugin searches $data for keys matching "files_*_filemanager".
        if ($feedbackfilesdraftid > 0) {
            $elementname = 'files_' . $userid;
            $data->{$elementname . '_filemanager'} = $feedbackfilesdraftid;
        }

        $this->assign->save_grade($userid, $data);

        // Moodle core only pushes grades to the gradebook for the latest attempt.
        // If this save triggered a reopen (new attempt), or we're re-grading a
        // previous attempt, ensure the gradebook reflects the actual grade.
        $this->ensure_gradebook_sync($userid, $attemptnumber);

        // When advanced grading is active, assign::save_grade() calculates the
        // grade from the rubric/guide criteria, ignoring $data->grade. If the
        // admin allows manual grade overrides, apply the teacher's explicit
        // grade value after the advanced grading has been saved.
        if (
            $grade !== null && !empty($advancedgradingdata)
                && get_config('local_unifiedgrader', 'allow_manual_grade_override')
        ) {
            $gradeobj = $this->assign->get_user_grade($userid, false);
            if ($gradeobj && (float) $gradeobj->grade !== $grade) {
                $gradeobj->grade = $grade;
                $gradeobj->timemodified = time();
                $DB->update_record('assign_grades', $gradeobj);
                $this->assign->update_grade($gradeobj);
            }
        }

        // The assign::save_grade() → assignfeedback_comments::save() stores the
        // raw editor text but does NOT process draft files (that is normally
        // handled by the grading form). Move files from draft to permanent
        // storage and rewrite draftfile.php URLs to @@PLUGINFILE@@.
        if ($draftitemid > 0) {
            $gradeobj = $this->assign->get_user_grade($userid, false);
            if ($gradeobj) {
                $rewritten = file_save_draft_area_files(
                    $draftitemid,
                    $this->context->id,
                    'assignfeedback_comments',
                    'feedback',
                    (int) $gradeobj->id,
                    $this->get_editor_options(),
                    $feedback,
                );
                $comment = $DB->get_record('assignfeedback_comments', ['grade' => $gradeobj->id]);
                if ($comment) {
                    $comment->commenttext = $rewritten;
                    $DB->update_record('assignfeedback_comments', $comment);
                }
            }
        }

        return true;
    }

    /**
     * Save grade and feedback directly, bypassing the grading form.
     *
     * Used when advanced grading is active but no criteria data is provided
     * (e.g., quick numeric grade override from the unified grader).
     *
     * @param int $userid
     * @param float|null $grade
     * @param string $feedback
     * @param int $feedbackformat
     * @param int $attemptnumber
     * @param int $draftitemid Draft area item ID for feedback file uploads.
     * @param int $feedbackfilesdraftid Draft area item ID for feedback files (assignfeedback_file).
     */
    private function save_grade_directly(
        int $userid,
        ?float $grade,
        string $feedback,
        int $feedbackformat,
        int $attemptnumber,
        int $draftitemid = 0,
        int $feedbackfilesdraftid = 0,
    ): void {
        global $USER, $DB;

        // Get or create the grade record.
        $gradeobj = $this->assign->get_user_grade($userid, true, $attemptnumber);
        $gradeobj->grade = $grade ?? -1;
        $gradeobj->grader = $USER->id;
        $gradeobj->timemodified = time();
        $DB->update_record('assign_grades', $gradeobj);

        // If a draft area was provided, migrate files from draft to permanent storage.
        if ($draftitemid > 0) {
            $feedback = file_save_draft_area_files(
                $draftitemid,
                $this->context->id,
                'assignfeedback_comments',
                'feedback',
                $gradeobj->id,
                $this->get_editor_options(),
                $feedback,
            );
        }

        // Save feedback via the comments plugin.
        foreach ($this->assign->get_feedback_plugins() as $plugin) {
            if ($plugin->get_type() === 'comments' && $plugin->is_enabled()) {
                $existingcomment = $DB->get_record('assignfeedback_comments', [
                    'assignment' => $gradeobj->assignment,
                    'grade' => $gradeobj->id,
                ]);
                if ($existingcomment) {
                    $existingcomment->commenttext = $feedback;
                    $existingcomment->commentformat = $feedbackformat;
                    $DB->update_record('assignfeedback_comments', $existingcomment);
                } else {
                    $record = new \stdClass();
                    $record->assignment = $gradeobj->assignment;
                    $record->grade = $gradeobj->id;
                    $record->commenttext = $feedback;
                    $record->commentformat = $feedbackformat;
                    $DB->insert_record('assignfeedback_comments', $record);
                }
                break;
            }
        }

        // Save feedback files via the file plugin (bypasses plugin iteration).
        if ($feedbackfilesdraftid > 0 && $this->has_feedback_plugin('file')) {
            $this->save_feedback_files_directly($gradeobj, $userid, $feedbackfilesdraftid);
        }

        // Push to gradebook and trigger events.
        $this->assign->update_grade($gradeobj);

        // Ensure gradebook reflects the actual grade if the latest attempt is ungraded.
        $this->ensure_gradebook_sync($userid, $attemptnumber);
    }

    /**
     * Ensure the gradebook reflects the most recent actual grade.
     *
     * Moodle core's update_grade() only pushes to the gradebook when the graded
     * attempt matches the latest submission's attempt number. After a reopen
     * (new attempt created) or when re-grading a previous attempt, the gradebook
     * may not be updated. This method checks if the latest attempt has no grade
     * and, if so, pushes the graded attempt's grade to the gradebook.
     *
     * @param int $userid The user ID.
     * @param int $gradedattempt The attempt number that was just graded.
     */
    private function ensure_gradebook_sync(int $userid, int $gradedattempt): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/lib.php');

        // Get the latest submission to check if it matches the graded attempt.
        $latestsubmission = $this->assign->get_user_submission($userid, false);
        if (!$latestsubmission) {
            return;
        }

        $latestattempt = (int) $latestsubmission->attemptnumber;
        if ($latestattempt === $gradedattempt) {
            // The graded attempt IS the latest — Moodle core already pushed to gradebook.
            return;
        }

        // The graded attempt is NOT the latest (e.g., after a reopen).
        // Check if the latest attempt has an actual grade.
        $latestgrade = $this->assign->get_user_grade($userid, false, $latestattempt);
        if ($latestgrade && $latestgrade->grade !== null && (float) $latestgrade->grade >= 0) {
            // Latest attempt has a real grade — gradebook is correct, do nothing.
            return;
        }

        // Latest attempt has no grade. Push the graded attempt's grade to the gradebook
        // so it reflects the most recent actual mark.
        $gradedgradeobj = $this->assign->get_user_grade($userid, false, $gradedattempt);
        if (!$gradedgradeobj || $gradedgradeobj->grade === null || (float) $gradedgradeobj->grade < 0) {
            return;
        }

        // Build the gradebook grade array matching Moodle's convert_grade_for_gradebook() format.
        $gradebookgrade = [];
        $gradebookgrade['rawgrade'] = (float) $gradedgradeobj->grade;
        $gradebookgrade['userid'] = $userid;
        $gradebookgrade['usermodified'] = (int) $gradedgradeobj->grader;
        $gradebookgrade['datesubmitted'] = null;
        $gradebookgrade['dategraded'] = (int) $gradedgradeobj->timemodified;

        // Include feedback if available.
        foreach ($this->assign->get_feedback_plugins() as $plugin) {
            if ($plugin->get_type() === 'comments' && $plugin->is_enabled()) {
                $gradebookgrade['feedback'] = $plugin->text_for_gradebook($gradedgradeobj);
                $gradebookgrade['feedbackformat'] = $plugin->format_for_gradebook($gradedgradeobj);
                break;
            }
        }

        $instance = clone $this->assign->get_instance();
        $instance->cmidnumber = $this->cm->idnumber;
        $instance->gradefeedbackenabled = $this->assign->is_gradebook_feedback_enabled();
        assign_grade_item_update($instance, $gradebookgrade);
    }

    /**
     * Get submitted files for document preview (latest attempt).
     *
     * @param int $userid
     * @return array
     */
    public function get_submission_files(int $userid): array {
        $submission = $this->assign->get_user_submission($userid, false);
        return $this->build_submission_files($submission ?: null);
    }

    /**
     * Get submission files for a specific attempt.
     *
     * @param int $userid
     * @param int $attemptnumber 0-based attempt number, -1 for latest.
     * @return array
     */
    public function get_submission_files_for_attempt(int $userid, int $attemptnumber = -1): array {
        $submission = $this->assign->get_user_submission($userid, false, $attemptnumber);
        return $this->build_submission_files($submission ?: null);
    }

    /**
     * Build the file list for a given submission record.
     *
     * @param \stdClass|null $submission
     * @return array
     */
    private function build_submission_files(?\stdClass $submission): array {
        if (!$submission) {
            return [];
        }

        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $this->context->id,
            'assignsubmission_file',
            'submission_files',
            $submission->id,
            'sortorder, filename',
            false,
        );

        // Check which non-previewable formats can be converted to PDF.
        $converter = new \core_files\converter();

        $result = [];
        foreach ($files as $file) {
            $downloadurl = \moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename(),
            );

            $mimetype = $file->get_mimetype();
            $extension = pathinfo($file->get_filename(), PATHINFO_EXTENSION);

            // Check if the file can be converted to PDF (for non-PDF files).
            $convertible = false;
            if ($mimetype !== 'application/pdf' && $extension) {
                $convertible = $converter->can_convert_format_to($extension, 'pdf');
            }

            // Build preview URL: use convert=pdf for convertible files.
            $previewparams = [
                'fileid' => $file->get_id(),
                'cmid' => $this->cm->id,
            ];
            if ($convertible) {
                $previewparams['convert'] = 'pdf';
            }
            $previewurl = new \moodle_url('/local/unifiedgrader/preview_file.php', $previewparams);

            $result[] = [
                'fileid' => (int) $file->get_id(),
                'filename' => $file->get_filename(),
                'mimetype' => $mimetype,
                'filesize' => (int) $file->get_filesize(),
                'url' => $downloadurl->out(false),
                'previewurl' => $previewurl->out(false),
                'convertible' => $convertible,
            ];
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
        $instance = $this->assign->get_instance();
        return match ($feature) {
            'rubric', 'markingguide' => (bool) get_grading_manager(
                $this->context,
                'mod_assign',
                'submissions',
            )->get_active_method(),
            'onlinetext' => $this->has_submission_plugin('onlinetext'),
            'filesubmission' => $this->has_submission_plugin('file'),
            'blindmarking' => (bool) $instance->blindmarking,
            'annotations' => false,
            default => false,
        };
    }

    /**
     * Check whether the grade for a user has been released and visible to the student.
     *
     * @param int $userid
     * @return bool
     */
    public function is_grade_released(int $userid): bool {
        // 1. Check that a grade exists and is non-null.
        $grade = $this->assign->get_user_grade($userid, false) ?: null;
        if (!$grade || $grade->grade === null || $grade->grade < 0) {
            return false;
        }

        // 2. Check the gradebook item is not hidden.
        $gradeitem = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'iteminstance' => $this->assign->get_instance()->id,
            'itemnumber' => 0,
            'courseid' => $this->course->id,
        ]);
        if ($gradeitem && $gradeitem->is_hidden()) {
            return false;
        }

        // 3. If marking workflow is enabled, require state = RELEASED.
        $instance = $this->assign->get_instance();
        if (!empty($instance->markingworkflow)) {
            $workflowstate = $this->assign->get_user_flags($userid, false);
            if (!$workflowstate || $workflowstate->workflowstate !== ASSIGN_MARKING_WORKFLOW_STATE_RELEASED) {
                return false;
            }
        }

        return true;
    }

    /**
     * Prepare the feedback draft area for a student.
     *
     * Clears the shared draft area, copies the student's existing feedback
     * files into it, and returns the feedback HTML with draft URLs.
     *
     * @param int $userid The student user ID.
     * @param int $draftitemid The shared draft area item ID.
     * @param int $attemptnumber Attempt number (0-based), or -1 for latest.
     * @return array With key 'feedbackhtml'.
     */
    public function prepare_feedback_draft(int $userid, int $draftitemid, int $attemptnumber = -1): array {
        global $USER, $DB;

        $grade = $this->assign->get_user_grade($userid, false) ?: null;
        $feedbacktext = '';

        if ($grade) {
            $comment = $DB->get_record('assignfeedback_comments', ['grade' => $grade->id]);
            if ($comment) {
                $feedbacktext = $comment->commenttext;
            }
        }

        // Clear existing draft files from the previous student.
        $fs = get_file_storage();
        $usercontext = \context_user::instance($USER->id);
        $fs->delete_area_files($usercontext->id, 'user', 'draft', $draftitemid);

        // Copy this student's feedback files from permanent storage into the draft area.
        // NOTE: file_prepare_draft_area() only copies files when draftitemid is empty (0).
        // Since we reuse the same draftitemid across student switches, we must copy manually.
        $gradeid = $grade ? (int) $grade->id : 0;
        if ($gradeid) {
            $files = $fs->get_area_files(
                $this->context->id,
                'assignfeedback_comments',
                'feedback',
                $gradeid,
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
     * @return array Editor options compatible with file_prepare_draft_area / file_save_draft_area_files.
     */
    private function get_editor_options(): array {
        global $CFG;
        return [
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'maxbytes' => $CFG->maxbytes,
            'context' => $this->context,
            'subdirs' => true,
        ];
    }

    /**
     * Get the grading definition (rubric/marking guide) for this assignment.
     *
     * @return array|null
     */
    public function get_grading_definition(): ?array {
        $gradingmanager = get_grading_manager($this->context, 'mod_assign', 'submissions');
        $method = $gradingmanager->get_active_method();
        if (!$method) {
            return null;
        }
        $controller = $gradingmanager->get_controller($method);
        if (!$controller) {
            return null;
        }
        return $this->serialize_grading_definition($controller);
    }

    /**
     * Get plagiarism report links for a user's assignment submission.
     *
     * Calls Moodle's generic plagiarism API for each submitted file and for
     * online text content. Works with any plagiarism plugin (Copyleaks, Turnitin, etc.).
     *
     * @param int $userid The user ID.
     * @return array Array of arrays with keys: 'label' (string), 'html' (string).
     */
    public function get_plagiarism_links(int $userid): array {
        global $CFG;

        if (empty($CFG->enableplagiarism)) {
            return [];
        }

        require_once($CFG->libdir . '/plagiarismlib.php');

        $submission = $this->assign->get_user_submission($userid, false);
        if (!$submission) {
            return [];
        }

        $results = [];

        // Per-file plagiarism links.
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $this->context->id,
            'assignsubmission_file',
            'submission_files',
            $submission->id,
            'sortorder, filename',
            false,
        );

        foreach ($files as $file) {
            $linkhtml = plagiarism_get_links([
                'userid' => $userid,
                'file' => $file,
                'cmid' => $this->cm->id,
                'course' => $this->course->id,
            ]);
            if (!empty(trim($linkhtml))) {
                $results[] = [
                    'label' => $file->get_filename(),
                    'html' => $linkhtml,
                ];
            }
        }

        // Online text plagiarism link.
        $onlinetext = $this->get_onlinetext($submission);
        if (!empty($onlinetext)) {
            $linkhtml = plagiarism_get_links([
                'userid' => $userid,
                'content' => $onlinetext,
                'cmid' => $this->cm->id,
                'course' => $this->course->id,
                'assignment' => $submission->assignment,
            ]);
            if (!empty(trim($linkhtml))) {
                $results[] = [
                    'label' => get_string('onlinetext', 'local_unifiedgrader'),
                    'html' => $linkhtml,
                ];
            }
        }

        return $results;
    }

    /**
     * Get the effective due date for a specific user.
     *
     * Priority: override duedate > extension duedate > global duedate.
     *
     * @param int $userid The user ID.
     * @return int The effective due date timestamp (0 if no due date).
     */
    public function get_effective_duedate(int $userid): int {
        global $DB;

        $instance = $this->assign->get_instance();
        $globalduedate = (int) $instance->duedate;

        // Check for override duedate.
        $override = $DB->get_field('assign_overrides', 'duedate', [
            'assignid' => $instance->id,
            'userid' => $userid,
        ]);
        if ($override !== false && $override !== null) {
            return (int) $override;
        }

        // Check for extension.
        $extension = $DB->get_field('assign_user_flags', 'extensionduedate', [
            'assignment' => $instance->id,
            'userid' => $userid,
        ]);
        if ($extension !== false && (int) $extension > 0) {
            return (int) $extension;
        }

        return $globalduedate;
    }

    /**
     * Get the user-level override for a student.
     *
     * @param int $userid The student user ID.
     * @return array|null Override data or null.
     */
    public function get_user_override(int $userid): ?array {
        global $DB;

        $instance = $this->assign->get_instance();
        $record = $DB->get_record('assign_overrides', [
            'assignid' => $instance->id,
            'userid' => $userid,
        ]);

        if (!$record) {
            return null;
        }

        return [
            'id' => (int) $record->id,
            'duedate' => $record->duedate !== null ? (int) $record->duedate : null,
            'cutoffdate' => $record->cutoffdate !== null ? (int) $record->cutoffdate : null,
            'allowsubmissionsfromdate' => $record->allowsubmissionsfromdate !== null
                ? (int) $record->allowsubmissionsfromdate : null,
            'timelimit' => $record->timelimit !== null ? (int) $record->timelimit : null,
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

        $instance = $this->assign->get_instance();
        $record = $DB->get_record('assign_overrides', [
            'assignid' => $instance->id,
            'userid' => $userid,
        ]);

        if (!$record) {
            return true;
        }

        $DB->delete_records('assign_overrides', ['id' => $record->id]);

        // Fire the user override deleted event.
        \mod_assign\event\user_override_deleted::create([
            'objectid' => $record->id,
            'context' => $this->context,
            'relateduserid' => $userid,
            'other' => ['assignid' => $instance->id],
        ])->trigger();

        // Clear the override cache.
        $cachekey = "{$instance->id}_u_{$userid}";
        \cache::make('mod_assign', 'overrides')->delete($cachekey);

        // Update calendar events for this user.
        $this->assign->update_calendar($this->cm->id);

        return true;
    }

    /**
     * Resolve the display status from submission and grade records.
     *
     * @param \stdClass|null $submission
     * @param \stdClass|null $grade
     * @return string
     */
    private function resolve_status(?\stdClass $submission, ?\stdClass $grade): string {
        if (!$submission || $submission->status === 'new') {
            return 'nosubmission';
        }
        if ($grade && $grade->grade !== null && $grade->grade >= 0) {
            return 'graded';
        }
        if ($submission->status === 'submitted') {
            return 'submitted';
        }
        return $submission->status;
    }

    /**
     * Check if a submission plugin of the given type is enabled.
     *
     * @param string $type
     * @return bool
     */
    private function has_submission_plugin(string $type): bool {
        foreach ($this->assign->get_submission_plugins() as $plugin) {
            if ($plugin->get_type() === $type && $plugin->is_enabled()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a feedback plugin of the given type is enabled.
     *
     * @param string $type Plugin type identifier (e.g. 'file', 'comments').
     * @return bool
     */
    public function has_feedback_plugin(string $type): bool {
        foreach ($this->assign->get_feedback_plugins() as $plugin) {
            if ($plugin->get_type() === $type && $plugin->is_enabled()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Prepare the feedback files draft area for a student.
     *
     * Clears the shared draft area and repopulates it with the student's
     * existing feedback files from assignfeedback_file storage.
     *
     * @param int $userid The student user ID.
     * @param int $draftitemid The shared draft area item ID.
     * @return array With key 'filecount'.
     */
    public function prepare_feedback_files_draft(int $userid, int $draftitemid): array {
        global $USER;

        $grade = $this->assign->get_user_grade($userid, false) ?: null;

        // Clear existing draft files from the previous student.
        $fs = get_file_storage();
        $usercontext = \context_user::instance($USER->id);
        $fs->delete_area_files($usercontext->id, 'user', 'draft', $draftitemid);

        $filecount = 0;
        $gradeid = $grade ? (int) $grade->id : 0;
        if ($gradeid) {
            $files = $fs->get_area_files(
                $this->context->id,
                'assignfeedback_file',
                'feedback_files',
                $gradeid,
                'sortorder, filename',
                false,
            );
            $filerecord = [
                'contextid' => $usercontext->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftitemid,
            ];
            foreach ($files as $file) {
                $fs->create_file_from_storedfile($filerecord, $file);
                $filecount++;
            }
        }

        return ['filecount' => $filecount];
    }

    /**
     * Save feedback files for a student from the draft area.
     *
     * Creates the grade record if it doesn't exist, then moves files from
     * the draft area to permanent storage.
     *
     * @param int $userid Student user ID.
     * @param int $feedbackfilesdraftid Draft area item ID containing feedback files.
     * @return array{filecount: int} Number of feedback files saved.
     */
    public function save_feedback_files(int $userid, int $feedbackfilesdraftid): array {
        $gradeobj = $this->assign->get_user_grade($userid, true);
        $this->save_feedback_files_directly($gradeobj, $userid, $feedbackfilesdraftid);

        // Return the current file count.
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $this->context->id,
            'assignfeedback_file',
            'feedback_files',
            $gradeobj->id,
            'id',
            false,
        );

        return ['filecount' => count($files)];
    }

    /**
     * Save feedback files directly, bypassing the feedback plugin iteration.
     *
     * Used by save_grade_directly() and save_feedback_files() when the
     * normal assign::save_grade() path is not available.
     *
     * @param \stdClass $gradeobj The grade record object.
     * @param int $userid The student user ID.
     * @param int $feedbackfilesdraftid The draft area item ID containing feedback files.
     */
    private function save_feedback_files_directly(
        \stdClass $gradeobj,
        int $userid,
        int $feedbackfilesdraftid,
    ): void {
        global $DB, $COURSE;

        $fileoptions = [
            'subdirs' => 1,
            'maxbytes' => $COURSE->maxbytes,
            'accepted_types' => '*',
            'return_types' => FILE_INTERNAL,
        ];

        // Build the data object that file_postupdate_standard_filemanager expects.
        $elementname = 'files_' . $userid;
        $data = new \stdClass();
        $data->{$elementname . '_filemanager'} = $feedbackfilesdraftid;

        file_postupdate_standard_filemanager(
            $data,
            $elementname,
            $fileoptions,
            $this->context,
            'assignfeedback_file',
            'feedback_files',
            $gradeobj->id,
        );

        // Update the file count in the assignfeedback_file table.
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $this->context->id,
            'assignfeedback_file',
            'feedback_files',
            $gradeobj->id,
            'id',
            false,
        );
        $numfiles = count($files);

        $existing = $DB->get_record('assignfeedback_file', [
            'assignment' => $gradeobj->assignment,
            'grade' => $gradeobj->id,
        ]);
        if ($existing) {
            $existing->numfiles = $numfiles;
            $DB->update_record('assignfeedback_file', $existing);
        } else if ($numfiles > 0) {
            $record = new \stdClass();
            $record->assignment = $gradeobj->assignment;
            $record->grade = $gradeobj->id;
            $record->numfiles = $numfiles;
            $DB->insert_record('assignfeedback_file', $record);
        }
    }

    /**
     * Get the online text submission content.
     *
     * @param \stdClass $submission
     * @return string
     */
    private function get_onlinetext(\stdClass $submission): string {
        foreach ($this->assign->get_submission_plugins() as $plugin) {
            if ($plugin->get_type() === 'onlinetext' && $plugin->is_enabled()) {
                return $plugin->get_editor_text('onlinetext', $submission->id);
            }
        }
        return '';
    }

    /**
     * Get rendered submission content from all visible plugins.
     *
     * @param \stdClass $submission
     * @return string HTML content.
     */
    private function get_submission_content(\stdClass $submission): string {
        $text = '';
        foreach ($this->assign->get_submission_plugins() as $plugin) {
            if ($plugin->is_enabled() && $plugin->is_visible()) {
                $pluginview = $plugin->view($submission);
                if (!empty($pluginview)) {
                    $text .= $pluginview;
                }
            }
        }
        return $text;
    }

    /**
     * Serialize the grading definition (rubric/marking guide) for the frontend.
     *
     * @param \gradingform_controller $controller
     * @return array|null
     */
    private function serialize_grading_definition(\gradingform_controller $controller): ?array {
        $definition = $controller->get_definition();
        if (!$definition) {
            return null;
        }

        $method = get_grading_manager($this->context, 'mod_assign', 'submissions')->get_active_method();

        $result = [
            'id' => (int) $definition->id,
            'method' => $method,
            'name' => $definition->name ?? '',
            'description' => $definition->description ?? '',
        ];

        if ($method === 'rubric' && !empty($definition->rubric_criteria)) {
            $criteria = [];
            foreach ($definition->rubric_criteria as $criterionid => $criterion) {
                $levels = [];
                if (!empty($criterion['levels'])) {
                    foreach ($criterion['levels'] as $levelid => $level) {
                        $levels[] = [
                            'id' => (int) $levelid,
                            'score' => (float) ($level['score'] ?? 0),
                            'definition' => $level['definition'] ?? '',
                        ];
                    }
                    // Sort levels by score ascending.
                    usort($levels, fn($a, $b) => $a['score'] <=> $b['score']);
                }
                $criteria[] = [
                    'id' => (int) $criterionid,
                    'description' => $criterion['description'] ?? '',
                    'levels' => $levels,
                ];
            }
            $result['criteria'] = $criteria;
        } else if ($method === 'guide' && !empty($definition->guide_criteria)) {
            $criteria = [];
            foreach ($definition->guide_criteria as $criterionid => $criterion) {
                $criteria[] = [
                    'id' => (int) $criterionid,
                    'shortname' => $criterion['shortname'] ?? '',
                    'description' => $criterion['description'] ?? '',
                    'descriptionmarkers' => format_text(
                        $criterion['descriptionmarkers'] ?? '',
                        FORMAT_HTML,
                        ['context' => $this->context],
                    ),
                    'maxscore' => (float) ($criterion['maxscore'] ?? 0),
                ];
            }
            $result['criteria'] = $criteria;
        }

        return $result;
    }

    /**
     * Get current rubric/marking guide fill data for a graded submission.
     *
     * @param \gradingform_controller $controller
     * @param \stdClass $grade
     * @param int $userid
     * @return array|null
     */
    private function get_rubric_fill(\gradingform_controller $controller, \stdClass $grade, int $userid): ?array {
        try {
            $instances = $controller->get_active_instances($grade->id);
            if (empty($instances)) {
                return null;
            }

            // Use the most recent active instance.
            $instance = end($instances);

            // Each grading form type has its own filling method.
            if ($instance instanceof \gradingform_guide_instance) {
                return $instance->get_guide_filling();
            } else if ($instance instanceof \gradingform_rubric_instance) {
                return $instance->get_rubric_filling();
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Perform a submission management action.
     *
     * Delegates to the underlying assign class public methods which handle
     * their own capability checks internally.
     *
     * @param int $userid The student user ID.
     * @param string $action One of: revert_to_draft, remove, lock, unlock.
     * @return bool
     * @throws \moodle_exception If action is invalid.
     */
    public function perform_submission_action(int $userid, string $action): bool {
        switch ($action) {
            case 'revert_to_draft':
                return $this->assign->revert_to_draft($userid);
            case 'remove':
                return $this->assign->remove_submission($userid);
            case 'lock':
                return $this->assign->lock_submission($userid);
            case 'unlock':
                return $this->assign->unlock_submission($userid);
            case 'submit':
                return $this->submit_for_grading($userid);
            default:
                throw new \moodle_exception('invalidaction', 'local_unifiedgrader');
        }
    }

    /**
     * Submit a draft submission on behalf of a student.
     *
     * The assign class has no public submit method, so we update the
     * submission status directly and fire the appropriate event.
     *
     * @param int $userid Student user ID.
     * @return bool
     */
    protected function submit_for_grading(int $userid): bool {
        global $DB;

        $submission = $this->assign->get_user_submission($userid, false);
        if (!$submission || $submission->status !== ASSIGN_SUBMISSION_STATUS_DRAFT) {
            throw new \moodle_exception('invalidaction', 'local_unifiedgrader');
        }

        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $submission->timemodified = time();
        $DB->update_record('assign_submission', $submission);

        \mod_assign\event\submission_status_updated::create_from_submission(
            $this->assign,
            $submission,
        )->trigger();

        return true;
    }
}
