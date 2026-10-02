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
     * @param int $feedbackfilesdraftid Draft area item ID for feedback files plugin (0 = no files).
     * @param int $attemptnumber Attempt number (0-based), or -1 for latest.
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
     * Deliberate reset: clear the saved grade for this user and remove any
     * orphan submission row that was created without the student actually
     * submitting anything (e.g. by accidental teacher interaction with the
     * marking panel for a non-submitter).
     *
     * Concrete adapters that don't support this concept can leave the
     * default no-op behaviour in place — the WS just returns success.
     *
     * @param int $userid The student user ID.
     * @return bool True on success.
     */
    public function reset_grade_and_submission(int $userid): bool {
        // Activity types with no grade of ours to clear (quiz, BigBlueButton)
        // still have something for "--" to undo: a gradebook override pinning the
        // cell, left behind by manual gradebook editing or the penalty subsystem.
        // Lifting it lets the cell recompute from the activity — for a quiz, from
        // the attempt — which is what the teacher means by resetting the mark.
        //
        // Previously this returned true without doing anything, so "--" reported
        // success while changing nothing, and the override had to be cleared by
        // hand in the gradebook. Adapters that own a stored grade (assign, forum)
        // override this method and clear that as well.
        $this->clear_recoverable_gradebook_block($userid);
        return true;
    }

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
     * Push this student's penalised grade to the gradebook.
     *
     * For activity types that store the teacher's RAW mark (so the grader can
     * always show back exactly what was typed), the penalty deduction is applied
     * only on the way to the gradebook. Types that still store the already-reduced
     * grade have nothing to do here.
     *
     * @param int $userid The student user ID.
     */
    public function sync_gradebook_penalty(int $userid): void {
        // No-op by default.
    }

    /**
     * Work out a student's late penalty from the due date penalty rules.
     *
     * Used from Moodle 5.3, when Unified Grader owns late penalties. Adapters
     * return null when the activity's penalty switch is off, when the student has
     * no effective due date, or when their work was on time.
     *
     * @param int $userid The student user ID.
     * @param int|null $duedate A due date to test instead of the student's effective one (a preview).
     * @return array|null ['percentage' => int, 'dayslate' => int], or null.
     */
    public function calculate_late_penalty(int $userid, ?int $duedate = null): ?array {
        return null;
    }

    /**
     * When the student's work counts as submitted, for deciding whether it was late.
     *
     * @param int $userid The student user ID.
     * @return int Timestamp, or 0 when nothing has been submitted.
     */
    public function get_late_reference_time(int $userid): int {
        return 0;
    }

    /**
     * The maximum grade penalty percentages are taken of.
     *
     * 0 for scales and "no grade", where a percentage of the maximum means nothing.
     *
     * @return float
     */
    protected function get_penalty_max_grade(): float {
        return 0.0;
    }

    /**
     * Write the student's total penalty deduction to the gradebook (Moodle 5.3+).
     *
     * Leaves the raw grade alone, so call it after the activity's raw mark has
     * been pushed.
     *
     * @param int $userid The student user ID.
     * @return string A gradebook_writer result constant.
     */
    public function apply_gradebook_deduction(int $userid): string {
        $gradeitem = $this->fetch_grade_item();
        if (!$gradeitem) {
            return \local_unifiedgrader\penalty\gradebook_writer::SKIPPED;
        }
        $maxgrade = $this->get_penalty_max_grade();
        $deduction = $maxgrade > 0
            ? \local_unifiedgrader\penalty_manager::get_total_deduction((int) $this->cm->id, $userid, $maxgrade)
            : 0.0;
        return \local_unifiedgrader\penalty\gradebook_writer::apply($gradeitem, $userid, $deduction);
    }

    /**
     * Whether plagiarism scanning can say anything meaningful about a file.
     *
     * Plagiarism services extract and compare text, so a recording, image or
     * archive can never yield a useful result. The vendor plugin reports that as
     * a failed scan — which reads to a teacher as "something broke" and offers a
     * "Try again" that can never succeed. Callers use this to show a neutral
     * "not applicable" note instead of relaying that false alarm, while a genuine
     * failure on a real document still surfaces as an error worth acting on.
     *
     * Deny-list rather than allow-list on purpose: an unfamiliar document format
     * still goes to the plugin (a real result beats a wrongly-suppressed one),
     * and supporting audio later is a one-line removal here.
     *
     * @param \stored_file $file The submitted file.
     * @return bool True when the file is worth sending to a plagiarism plugin.
     */
    protected function plagiarism_applies_to_file(\stored_file $file): bool {
        $mimetype = (string) $file->get_mimetype();
        foreach (['audio/', 'video/', 'image/'] as $prefix) {
            if (strpos($mimetype, $prefix) === 0) {
                return false;
            }
        }
        return !in_array($mimetype, [
            'application/zip',
            'application/x-tar',
            'application/g-zip',
            'application/x-gzip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
        ], true);
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
     * Get the module context.
     *
     * @return \context_module
     */
    public function get_context(): \context_module {
        return $this->context;
    }

    /**
     * The course module id.
     *
     * @return int
     */
    public function get_cmid(): int {
        return (int) $this->cm->id;
    }

    /**
     * Check whether grades are currently posted (visible to students).
     *
     * Posted means every student this user can see has a released cell. A hidden
     * grade item, or a hidden cell while other cells are released, is not posted.
     *
     * @return bool True if grades are posted.
     */
    public function are_grades_posted(): bool {
        return $this->posting_status()['posted'];
    }

    /**
     * Get the raw hidden value for the grade item.
     *
     * Returns 0 (visible), 1 (always hidden), or a Unix timestamp (hidden until).
     * Per-student release lives on the cells; this remains the item's own value.
     *
     * @return int The hidden value.
     */
    public function get_grades_hidden_value(): int {
        return $this->posting_status()['hidden'];
    }

    /**
     * Post, hide, or schedule grades for the class or for the given students.
     *
     * An empty user list is the whole class. The returned status includes
     * `lifted`, which is true when a partial post had to reveal the grade item.
     *
     * @param int $hidden 0 = post (visible), 1 = hide permanently, or Unix timestamp = hide until.
     * @param int[] $userids Students to change. Empty for the whole class.
     * @return array{posted:bool,partial:bool,hidden:int,postedcount:int,total:int,lifted:bool}
     */
    public function set_grades_posted(int $hidden, array $userids = []): array {
        $status = \local_unifiedgrader\grades\release::apply($this, $hidden, $userids, true);
        \local_unifiedgrader\friction\service::settle($this, $hidden, $userids);
        $fresh = $this->posting_status();
        $fresh['lifted'] = !empty($status['lifted']);
        return $fresh;
    }

    /**
     * The current posting status: posted, partial, the item's hidden value, and the counts.
     *
     * @return array{posted:bool,partial:bool,hidden:int,postedcount:int,total:int,lifted:bool}
     */
    public function posting_status(): array {
        return \local_unifiedgrader\grades\release::summarise($this);
    }

    /**
     * The grade item this activity posts against.
     *
     * @return \grade_item|null
     */
    public function get_grade_item(): ?\grade_item {
        return $this->fetch_grade_item();
    }

    /**
     * Add each participant's effective grade-hidden value, in one query.
     *
     * @param array $participants Participant rows. Each has an `id`.
     * @return array The same rows, each with `gradehidden`.
     */
    public function attach_grade_hidden(array $participants): array {
        global $DB;

        if (!$participants) {
            return $participants;
        }
        $item = $this->fetch_grade_item();
        $itemhidden = $item ? (int) $item->get_hidden() : 0;
        $selective = $item && \local_unifiedgrader\grades\release::is_selective($item);
        $cells = $item
            ? $DB->get_records_menu('grade_grades', ['itemid' => $item->id], '', 'userid, hidden')
            : [];
        $pending = $item
            ? array_flip(\local_unifiedgrader\friction\service::pending_userids((int) $item->id))
            : [];
        $released = ($item && $itemhidden === 1 && $this->release_outlives_hidden_item())
            ? array_flip(\local_unifiedgrader\friction\service::released_userids((int) $item->id))
            : [];
        foreach ($participants as &$participant) {
            $userid = (int) ($participant['id'] ?? 0);
            // The cell is hidden while the student reads the feedback. The teacher
            // still sees that grade as posted. A finished quiz walk stays posted
            // while the review options keep the grade item hidden.
            if (isset($pending[$userid]) || isset($released[$userid])) {
                $participant['gradehidden'] = 0;
                continue;
            }
            $cell = array_key_exists($userid, $cells) ? (int) $cells[$userid] : null;
            $participant['gradehidden'] = \local_unifiedgrader\grades\release::effective_hidden(
                $itemhidden,
                $cell,
                $selective,
            );
        }
        unset($participant);
        return $participants;
    }

    /**
     * Whether this student's gradebook cell is withheld.
     *
     * A missing cell is withheld when the item is hidden, and also when release
     * is selective: other students have been held back, so this one has not been posted.
     *
     * @param int $userid
     * @return bool
     */
    protected function grade_is_withheld(int $userid): bool {
        $item = $this->fetch_grade_item();
        if (!$item) {
            return false;
        }
        $grade = \grade_grade::fetch(['itemid' => $item->id, 'userid' => $userid]);
        if ($grade) {
            return $grade->is_hidden();
        }
        return $item->is_hidden() || \local_unifiedgrader\grades\release::is_selective($item);
    }

    /**
     * Whether a finished walk still counts as posted while the grade item is hidden.
     *
     * Quizzes leave the item hidden until the class review options show marks.
     * A student post cannot lift it, so the finished walk is the release.
     *
     * @return bool
     */
    public function release_outlives_hidden_item(): bool {
        return false;
    }

    /**
     * Whether a withheld cell should keep the feedback page closed.
     *
     * A pending Friction Feedback walk hides the cell and still opens the page,
     * so the student can read the feedback. The mark itself stays off that page.
     *
     * @param int $userid
     * @return bool
     */
    protected function withheld_blocks_release(int $userid): bool {
        if (\local_unifiedgrader\friction\service::is_pending_user($this, $userid)) {
            return false;
        }
        return $this->grade_is_withheld($userid);
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
     * Clear a gradebook override (grade_grades.overridden) for the given user
     * on this activity. Leaves a locked grade alone — that's an admin-set
     * lockout, not a teacher concern. Uses grade_grade::set_overridden(false,
     * true) so refresh_grades runs and the gradebook cell reverts to whatever
     * the activity currently reports.
     *
     * @param int $userid
     * @return bool True when an override was cleared.
     */
    protected function clear_recoverable_gradebook_block(int $userid): bool {
        $gradeitem = $this->fetch_grade_item();
        if (!$gradeitem) {
            return false;
        }
        $gradegrade = \grade_grade::fetch([
            'itemid' => $gradeitem->id,
            'userid' => $userid,
        ]);
        if (!$gradegrade) {
            return false;
        }
        if (!empty($gradegrade->locked) || !empty($gradeitem->locked)) {
            return false;
        }
        if (empty($gradegrade->overridden)) {
            return false;
        }
        $gradegrade->set_overridden(false, true);
        return true;
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
     * Extract the group filter from the filters array.
     *
     * Supports the new 'groups' key (array of group IDs) with fallback
     * to the legacy 'group' key (single int).
     *
     * In separate groups mode, a teacher who cannot access all groups is
     * confined to their own groups, whatever the filter asks for.
     *
     * @param array $filters Filter array from get_participants().
     * @return int[]|null Array of group IDs. Empty array means no group filter
     *         (all groups); null means the viewer may see nobody.
     */
    protected function get_group_ids(array $filters): ?array {
        if (isset($filters['groups']) && is_array($filters['groups'])) {
            $groupids = $filters['groups'];
        } else {
            // Legacy: single group ID.
            $groupid = (int) ($filters['group'] ?? 0);
            $groupids = $groupid > 0 ? [$groupid] : [];
        }
        return \local_unifiedgrader\access::visible_group_ids($this->cm, $this->context, $groupids);
    }

    /**
     * Get enrolled users filtered by multiple group IDs, deduplicated.
     *
     * When $groupids is empty, returns all enrolled users (no group filter).
     * When $groupids has one entry, delegates to standard get_enrolled_users.
     * When $groupids has multiple entries, fetches per group and merges.
     *
     * @param \context $context The context to check enrolment in.
     * @param string $capability Required capability (empty for any enrolled user).
     * @param int[] $groupids Array of group IDs (empty = all groups).
     * @param string $fields User fields to return.
     * @param string|null $sort Sort clause.
     * @return array Associative array keyed by user ID.
     */
    protected function get_enrolled_users_multigroup(
        \context $context,
        string $capability,
        array $groupids,
        string $fields = 'u.*',
        ?string $sort = null,
    ): array {
        if (empty($groupids)) {
            return get_enrolled_users($context, $capability, 0, $fields, $sort, 0, 0, true);
        }
        if (count($groupids) === 1) {
            return get_enrolled_users($context, $capability, reset($groupids), $fields, $sort, 0, 0, true);
        }
        // Multiple groups: fetch each and merge by user ID.
        $merged = [];
        foreach ($groupids as $gid) {
            $users = get_enrolled_users($context, $capability, $gid, $fields, $sort, 0, 0, true);
            $merged += $users; // Preserves first occurrence per key.
        }
        return $merged;
    }

    /**
     * The files of several items of one of this activity's file areas, in one query.
     *
     * file_storage::get_area_files() takes one item at a time, which costs a
     * query for each forum post or attempt. Directories are left out.
     *
     * @param string $component Component owning the file area.
     * @param string $filearea File area.
     * @param int[] $itemids Item IDs.
     * @return \stored_file[][] Files in filename order, keyed by item ID. An
     *         item without files has no entry.
     */
    protected function get_area_files_by_item(string $component, string $filearea, array $itemids): array {
        global $DB;

        $fs = get_file_storage();
        $result = [];
        foreach (array_chunk(array_values(array_unique(array_map('intval', $itemids))), 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $params += [
                'contextid' => $this->context->id,
                'component' => $component,
                'filearea' => $filearea,
                'dot' => '.',
            ];
            $records = $DB->get_records_select(
                'files',
                "contextid = :contextid AND component = :component AND filearea = :filearea
                 AND filename <> :dot AND itemid {$insql}",
                $params,
                'itemid, filename',
            );
            foreach ($records as $record) {
                if (!empty($record->referencefileid)) {
                    // A file held in a repository needs its reference details as well.
                    $file = $fs->get_file_by_id($record->id);
                } else {
                    $record->repositoryid = null;
                    $record->reference = null;
                    $record->referencelastsync = null;
                    $file = $fs->get_file_instance($record);
                }
                if ($file) {
                    $result[(int) $record->itemid][] = $file;
                }
            }
        }
        return $result;
    }

    /**
     * Look up the user context of every listed user in one query.
     *
     * A profile picture URL needs the user's context ID, and user_picture
     * fetches it one user at a time unless the record already carries it.
     * Call this before building picture URLs for a list of users.
     *
     * @param \stdClass[] $users User records with id and picture. Each one with
     *        a picture gains a contextid property.
     */
    protected function attach_user_contextids(array $users): void {
        global $DB;

        $userids = [];
        foreach ($users as $user) {
            if (!empty($user->picture) && empty($user->contextid)) {
                $userids[] = (int) $user->id;
            }
        }

        $contextids = [];
        foreach (array_chunk($userids, 1000) as $chunk) {
            [$insql, $params] = $DB->get_in_or_equal($chunk, SQL_PARAMS_NAMED);
            $params['contextlevel'] = CONTEXT_USER;
            $contextids += $DB->get_records_select_menu(
                'context',
                "contextlevel = :contextlevel AND instanceid {$insql}",
                $params,
                '',
                'instanceid, id',
            );
        }

        foreach ($users as $user) {
            if (isset($contextids[$user->id])) {
                $user->contextid = (int) $contextids[$user->id];
            }
        }
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
