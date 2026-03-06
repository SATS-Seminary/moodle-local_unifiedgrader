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
 * Abstract base adapter for activity modules.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\adapter;

defined('MOODLE_INTERNAL') || die();

/**
 * Base adapter defining the contract that all activity adapters must implement.
 *
 * Each supported activity type (assign, forum, quiz) provides a concrete subclass
 * that normalizes its data into a common format for the unified grading interface.
 */
abstract class base_adapter {

    /** @var \cm_info Course module info. */
    protected \cm_info $cm;

    /** @var \context_module Module context. */
    protected \context_module $context;

    /** @var \stdClass Course record. */
    protected \stdClass $course;

    /**
     * Constructor.
     *
     * @param \cm_info $cm Course module info.
     * @param \context_module $context Module context.
     * @param \stdClass $course Course record.
     */
    public function __construct(\cm_info $cm, \context_module $context, \stdClass $course) {
        $this->cm = $cm;
        $this->context = $context;
        $this->course = $course;
    }

    /**
     * Get activity metadata.
     *
     * @return array With keys: id, name, type, duedate, maxgrade, intro, gradingmethod, etc.
     */
    abstract public function get_activity_info(): array;

    /**
     * Get list of participants with submission status.
     *
     * @param array $filters Optional filters: status, group, search, sort, sortdir.
     * @return array List of participant arrays.
     */
    abstract public function get_participants(array $filters = []): array;

    /**
     * Get full submission data for a specific user.
     *
     * @param int $userid The user ID.
     * @return array With keys: userid, status, content, files, onlinetext, timecreated, timemodified, attemptnumber.
     */
    abstract public function get_submission_data(int $userid): array;

    /**
     * Get current grade and feedback for a user.
     *
     * @param int $userid The user ID.
     * @return array With keys: grade, feedback, feedbackformat, rubricdata, gradingdefinition, timegraded, grader.
     */
    abstract public function get_grade_data(int $userid): array;

    /**
     * Save a grade and feedback.
     *
     * @param int $userid The user ID.
     * @param float|null $grade The grade value, null for advanced grading only.
     * @param string $feedback HTML feedback text.
     * @param int $feedbackformat Text format constant.
     * @param array $advancedgradingdata Optional rubric/marking guide fill data.
     * @param int $draftitemid Draft area item ID for feedback file uploads (0 = no files).
     * @return bool Success.
     */
    abstract public function save_grade(
        int $userid,
        ?float $grade,
        string $feedback,
        int $feedbackformat = FORMAT_HTML,
        array $advancedgradingdata = [],
        int $draftitemid = 0,
        int $feedbackfilesdraftid = 0,
        int $attemptnumber = -1,
    ): bool;

    /**
     * Get submitted files for document preview.
     *
     * @param int $userid The user ID.
     * @return array List of file info arrays with keys: fileid, filename, mimetype, filesize, url.
     */
    abstract public function get_submission_files(int $userid): array;

    /**
     * Check if this adapter supports a given feature.
     *
     * @param string $feature Feature name (e.g., 'rubric', 'onlinetext', 'filesubmission', 'annotations').
     * @return bool
     */
    abstract public function supports_feature(string $feature): bool;

    /**
     * Check whether the grade for a user has been released and is visible to the student.
     *
     * This checks that a grade exists, the gradebook item is not hidden, and
     * (for activities with marking workflow) the workflow state is "released".
     *
     * @param int $userid The student user ID.
     * @return bool True if the grade is released and visible to the student.
     */
    abstract public function is_grade_released(int $userid): bool;

    /**
     * Get the list of submission attempts for a user.
     *
     * Returns an empty array for activity types that don't support attempts.
     * Override in concrete adapters (e.g., assign) that support multiple attempts.
     *
     * @param int $userid The user ID.
     * @return array List of arrays with keys: id, attemptnumber, status, timemodified, graded.
     */
    public function get_attempts(int $userid): array {
        return [];
    }

    /**
     * Get submission data for a specific attempt.
     *
     * Default delegates to get_submission_data() (ignoring attemptnumber).
     *
     * @param int $userid The user ID.
     * @param int $attemptnumber Attempt number (0-based), or -1 for latest.
     * @return array
     */
    public function get_submission_data_for_attempt(int $userid, int $attemptnumber = -1): array {
        return $this->get_submission_data($userid);
    }

    /**
     * Get grade data for a specific attempt.
     *
     * Default delegates to get_grade_data() (ignoring attemptnumber).
     *
     * @param int $userid The user ID.
     * @param int $attemptnumber Attempt number (0-based), or -1 for latest.
     * @return array
     */
    public function get_grade_data_for_attempt(int $userid, int $attemptnumber = -1): array {
        return $this->get_grade_data($userid);
    }

    /**
     * Get plagiarism report links for a user's submission.
     *
     * Returns an array of HTML strings from plagiarism plugins (e.g., Copyleaks, Turnitin).
     * Each entry represents a file or online text content with its plagiarism link.
     * Returns empty array if plagiarism is not enabled or no links are available.
     *
     * Subclasses should override this to call plagiarism_get_links() for each submission item.
     *
     * @param int $userid The user ID.
     * @return array Array of arrays with keys: 'label' (string), 'html' (string).
     */
    public function get_plagiarism_links(int $userid): array {
        return [];
    }

    /**
     * Get the grading definition (rubric/marking guide) for this activity.
     *
     * Returns the serialized grading definition with criteria and levels/scores.
     * Subclasses should override this for activity types that use advanced grading.
     *
     * @return array|null The grading definition, or null if simple grading.
     */
    public function get_grading_definition(): ?array {
        return null;
    }

    /**
     * Prepare the feedback draft area for a student.
     *
     * Clears the shared draft area and repopulates it with the student's
     * existing feedback files, returning display-ready HTML with draft URLs.
     *
     * Subclasses should override this for activity types that support
     * file-backed feedback (e.g., assignfeedback_comments).
     *
     * @param int $userid The student user ID.
     * @param int $draftitemid The shared draft area item ID.
     * @param int $attemptnumber Attempt number (activity-specific), or -1 for latest.
     * @return array With key 'feedbackhtml' containing HTML with draft URLs.
     */
    public function prepare_feedback_draft(int $userid, int $draftitemid, int $attemptnumber = -1): array {
        return ['feedbackhtml' => ''];
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
        return ['filecount' => 0];
    }

    /**
     * Check if a feedback plugin of the given type is enabled.
     *
     * @param string $type Plugin type identifier (e.g. 'file', 'comments').
     * @return bool
     */
    public function has_feedback_plugin(string $type): bool {
        return false;
    }

    /**
     * Get the activity type identifier.
     *
     * @return string e.g., 'assign', 'forum', 'quiz'.
     */
    public function get_type(): string {
        return $this->cm->modname;
    }

    /**
     * Get the course module info.
     *
     * @return \cm_info
     */
    public function get_cm(): \cm_info {
        return $this->cm;
    }

    /**
     * Get the module context.
     *
     * @return \context_module
     */
    public function get_context(): \context_module {
        return $this->context;
    }

    /**
     * Check whether grades are currently posted (visible to students).
     *
     * @return bool True if grades are posted (grade item is not hidden).
     */
    public function are_grades_posted(): bool {
        $gradeitem = $this->fetch_grade_item();
        return $gradeitem ? !$gradeitem->is_hidden() : true;
    }

    /**
     * Get the raw hidden value for the grade item.
     *
     * Returns 0 (visible), 1 (always hidden), or a Unix timestamp (hidden until).
     *
     * @return int The hidden value.
     */
    public function get_grades_hidden_value(): int {
        $gradeitem = $this->fetch_grade_item();
        return $gradeitem ? (int) $gradeitem->get_hidden() : 0;
    }

    /**
     * Set grade posting status for this activity.
     *
     * @param int $hidden 0 = post (visible), 1 = hide permanently, or Unix timestamp = hide until.
     */
    public function set_grades_posted(int $hidden): void {
        $gradeitem = $this->fetch_grade_item();
        if ($gradeitem) {
            $gradeitem->set_hidden($hidden);
        }
    }

    /**
     * Fetch the grade_item for this activity.
     *
     * Subclasses may override this to use a different itemnumber
     * (e.g., forum whole-forum grading uses itemnumber 1).
     *
     * @return \grade_item|null
     */
    protected function fetch_grade_item(): ?\grade_item {
        $item = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => $this->cm->modname,
            'iteminstance' => $this->cm->instance,
            'itemnumber' => 0,
            'courseid' => $this->course->id,
        ]);
        return $item ?: null;
    }

    /**
     * Perform a submission management action.
     *
     * Override in concrete adapters that support submission actions.
     *
     * @param int $userid The student user ID.
     * @param string $action Action identifier.
     * @return bool
     * @throws \moodle_exception If action is not supported.
     */
    public function perform_submission_action(int $userid, string $action): bool {
        throw new \moodle_exception('invalidaction', 'local_unifiedgrader');
    }

    /**
     * Get the user-level override for a student.
     *
     * Subclasses should override this for activity types that support
     * per-user overrides (assign, quiz).
     *
     * @param int $userid The student user ID.
     * @return array|null Override data array with 'id' key, or null if no override.
     */
    public function get_user_override(int $userid): ?array {
        return null;
    }

    /**
     * Delete the user-level override for a student.
     *
     * Subclasses should override this for activity types that support overrides.
     *
     * @param int $userid The student user ID.
     * @return bool True on success.
     * @throws \moodle_exception If not supported.
     */
    public function delete_user_override(int $userid): bool {
        throw new \moodle_exception('invalidaction', 'local_unifiedgrader');
    }

    /**
     * Get the effective due date for a specific user.
     *
     * Accounts for user overrides and extensions. Subclasses should
     * override this to provide activity-specific logic.
     *
     * @param int $userid The user ID.
     * @return int The effective due date timestamp (0 if no due date).
     */
    public function get_effective_duedate(int $userid): int {
        $info = $this->get_activity_info();
        return (int) ($info['duedate'] ?? 0);
    }

    /**
     * Check whether a participant entry matches the selected filter.
     *
     * Filter semantics:
     * - submitted:    Has been submitted (status is 'submitted' or 'graded').
     * - notsubmitted: Not yet submitted (status is 'draft', 'new', or 'nosubmission').
     * - graded:       Has a grade value present (any status).
     * - needsgrading: Submitted but not yet graded (status 'submitted', no grade, or 'needsgrading').
     * - late:         Entry is flagged as late (computed with per-user effective due date).
     *
     * @param string $filter The active filter value.
     * @param array $entry Participant entry with 'status', 'submittedat', 'gradevalue', 'islate' keys.
     * @param int $duedate The effective due/close date for this user (unused for late; kept for compat).
     * @return bool True if the entry matches the filter.
     */
    protected function matches_filter(string $filter, array $entry, int $duedate): bool {
        switch ($filter) {
            case 'submitted':
                return in_array($entry['status'], ['submitted', 'graded']);
            case 'notsubmitted':
                return in_array($entry['status'], ['draft', 'new', 'nosubmission']);
            case 'graded':
                return $entry['gradevalue'] !== null;
            case 'needsgrading':
                return in_array($entry['status'], ['submitted', 'needsgrading'])
                    && $entry['gradevalue'] === null;
            case 'late':
                return !empty($entry['islate']);
            default:
                return $entry['status'] === $filter;
        }
    }
}
