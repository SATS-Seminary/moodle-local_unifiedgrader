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
 * Forum adapter for the unified grading interface.
 *
 * Supports whole-forum grading (itemnumber 1). Post ratings (itemnumber 0)
 * are a separate system not handled here.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\adapter;

use local_unifiedgrader\submission_comment_manager;

global $CFG;
require_once($CFG->dirroot . '/grade/grading/lib.php');
require_once($CFG->dirroot . '/mod/forum/lib.php');
require_once($CFG->libdir . '/gradelib.php');

use mod_forum\local\container as forum_container;
use mod_forum\local\entities\forum as forum_entity;
use mod_forum\grades\forum_gradeitem;

/**
 * Concrete adapter wrapping mod_forum's grading API.
 */
class forum_adapter extends base_adapter {
    /** @var forum_entity The forum entity. */
    private forum_entity $forum;

    /** @var forum_gradeitem The grade item for whole-forum grading. */
    private forum_gradeitem $gradeitem;

    /** @var \stdClass The raw forum DB record. */
    private \stdClass $forumrecord;

    /**
     * Constructor.
     *
     * @param \cm_info $cm Course module info.
     * @param \context_module $context Module context.
     * @param \stdClass $course Course record.
     */
    public function __construct(\cm_info $cm, \context_module $context, \stdClass $course) {
        parent::__construct($cm, $context, $course);

        $vaultfactory = forum_container::get_vault_factory();
        $forumvault = $vaultfactory->get_forum_vault();
        $this->forum = $forumvault->get_from_course_module_id((int) $cm->id);
        $this->gradeitem = forum_gradeitem::load_from_forum_entity($this->forum);

        global $DB;
        $this->forumrecord = $DB->get_record('forum', ['id' => $this->forum->get_id()], '*', MUST_EXIST);
    }

    /**
     * Get forum metadata.
     *
     * @return array
     */
    public function get_activity_info(): array {
        $gradingmanager = get_grading_manager($this->context, 'mod_forum', 'forum');
        $gradingmethod = $gradingmanager->get_active_method();

        // Detect whether whole-forum grading is enabled (grade_for_forum == 0 means "None").
        $gradingenabled = $this->forum->is_grading_enabled();

        // Detect scale-based grading (negative grade = scale ID).
        $rawgrade = (int) $this->forum->get_grade_for_forum();
        $usescale = $rawgrade < 0;
        $scaleitems = [];
        $maxgrade = (float) $this->forum->get_grade_for_forum();
        if ($usescale) {
            $menu = make_grades_menu($rawgrade);
            foreach ($menu as $value => $label) {
                $scaleitems[] = ['value' => (int) $value, 'label' => (string) $label];
            }
            $maxgrade = (float) count($scaleitems);
        }

        return [
            'id' => (int) $this->cm->id,
            'name' => format_string($this->forum->get_name()),
            'type' => 'forum',
            'duedate' => (int) $this->forum->get_due_date(),
            'cutoffdate' => (int) $this->forum->get_cutoff_date(),
            'maxgrade' => $maxgrade,
            'usescale' => $usescale,
            'scaleitems' => $scaleitems,
            'intro' => format_text(
                $this->forum->get_intro(),
                $this->forum->get_intro_format(),
                ['context' => $this->context],
            ),
            // When grading is disabled (grade type "None"), force simple so the
            // client does not try to render an advanced grading form.
            'gradingmethod' => $gradingenabled ? ($gradingmethod ?: 'simple') : 'simple',
            'gradingdisabled' => !$gradingenabled,
            'teamsubmission' => false,
            'blindmarking' => false,
            'canmanageextensions' => has_capability('local/unifiedgrader:grade', $this->context),
        ];
    }

    /**
     * Get participant list with post/grade status.
     *
     * @param array $filters Optional: status, group, search, sort, sortdir.
     * @return array
     */
    public function get_participants(array $filters = []): array {
        global $DB, $PAGE;

        $groupids = $this->get_group_ids($filters);
        $forumid = $this->forum->get_id();

        // Get enrolled users who can view discussions (active enrolments only).
        $enrolledusers = $this->get_enrolled_users_multigroup(
            $this->context,
            'mod/forum:viewdiscussion',
            $groupids,
            'u.*',
            'u.lastname, u.firstname',
        );

        // Exclude users who can grade — teachers should not appear in the student list.
        // This mirrors what \assign::list_participants() does internally for assignments.
        $graders = $this->get_enrolled_users_multigroup($this->context, 'mod/forum:grade', $groupids, 'u.id');
        $enrolledusers = array_diff_key($enrolledusers, $graders);

        // Batch-load post counts, first post time (for lateness), and last post time per user.
        $sql = "SELECT p.userid, COUNT(p.id) AS postcount, MIN(p.created) AS firstpost, MAX(p.created) AS lastpost
                  FROM {forum_posts} p
                  JOIN {forum_discussions} d ON d.id = p.discussion
                 WHERE d.forum = :forumid AND p.deleted = 0
              GROUP BY p.userid";
        $poststats = $DB->get_records_sql($sql, ['forumid' => $forumid]);

        // Batch-load whole-forum grades (itemnumber = 1).
        $grades = $DB->get_records('forum_grades', [
            'forum' => $forumid,
            'itemnumber' => 1,
        ], '', 'userid, grade, timemodified');

        // Batch-load forum extensions.
        $extensions = $DB->get_records('local_unifiedgrader_fext', [
            'cmid' => (int) $this->cm->id,
        ], '', 'userid, extensionduedate');
        $extensionset = [];
        foreach ($extensions as $ext) {
            $extensionset[(int) $ext->userid] = (int) $ext->extensionduedate;
        }

        $forumduedate = (int) $this->forum->get_due_date();

        // For grading-disabled forums, batch-load gradebook feedback to determine
        // "graded" status (since forum_grades won't have entries).
        $gradingenabled = $this->forum->is_grading_enabled();
        $feedbackuserids = [];
        if (!$gradingenabled) {
            $gradeitem = $this->fetch_grade_item();
            if ($gradeitem) {
                $gradegrades = \grade_grade::fetch_all(['itemid' => $gradeitem->id]);
                if ($gradegrades) {
                    foreach ($gradegrades as $gg) {
                        if (!empty($gg->feedback)) {
                            $feedbackuserids[(int) $gg->userid] = true;
                        }
                    }
                }
            }
        }

        $result = [];
        foreach ($enrolledusers as $user) {
            $userid = (int) $user->id;
            $userposts = $poststats[$userid] ?? null;
            $usergrade = $grades[$userid] ?? null;

            $hasposts = $userposts && (int) $userposts->postcount > 0;
            $hasgrade = $usergrade && $usergrade->grade !== null;
            // For grading-disabled forums, treat "has gradebook feedback" as graded.
            if (!$gradingenabled && !$hasgrade && isset($feedbackuserids[$userid])) {
                $hasgrade = true;
            }
            $status = $this->resolve_status($hasposts, $hasgrade);

            $userpicture = new \user_picture($user);
            $userpicture->size = 64;
            $profileimageurl = $userpicture->get_url($PAGE)->out(false);

            // Use the first post time to determine lateness — a student who posted
            // on time but added follow-up posts after the due date is NOT late.
            $firstpostat = $userposts ? (int) $userposts->firstpost : 0;
            $submittedat = $userposts ? (int) $userposts->firstpost : 0;
            $effectiveduedate = $extensionset[$userid] ?? $forumduedate;
            $islate = $effectiveduedate > 0 && $firstpostat > 0 && $firstpostat > $effectiveduedate;

            $entry = [
                'id' => $userid,
                'fullname' => fullname($user),
                'email' => $user->email,
                'profileimageurl' => $profileimageurl,
                'status' => $status,
                'submittedat' => $submittedat,
                'gradevalue' => $hasgrade ? (float) $usergrade->grade : null,
                'islate' => $islate,
                'hasextension' => isset($extensionset[$userid]),
            ];

            // Apply status filter.
            if (!empty($filters['status']) && $filters['status'] !== 'all') {
                if (!$this->matches_filter($filters['status'], $entry, (int) $this->forum->get_due_date())) {
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
     * Get forum posts for a user, rendered as HTML.
     *
     * @param int $userid
     * @return array
     */
    public function get_submission_data(int $userid): array {
        global $DB;

        $forumid = $this->forum->get_id();

        // Get all discussions in this forum.
        $discussions = $DB->get_records('forum_discussions', ['forum' => $forumid], 'timemodified ASC');

        if (empty($discussions)) {
            return $this->empty_submission($userid);
        }

        // Get all of this user's posts across all discussions.
        $discussionids = array_keys($discussions);
        [$insql, $params] = $DB->get_in_or_equal($discussionids, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;
        $posts = $DB->get_records_select(
            'forum_posts',
            "discussion {$insql} AND userid = :userid AND deleted = 0",
            $params,
            'created ASC',
        );

        if (empty($posts)) {
            return $this->empty_submission($userid);
        }

        // Render posts as HTML content grouped by discussion.
        $content = $this->render_posts_as_html($posts, $discussions, $userid);

        // Get the earliest and latest post times.
        $timecreated = PHP_INT_MAX;
        $timemodified = 0;
        foreach ($posts as $post) {
            $timecreated = min($timecreated, (int) $post->created);
            $timemodified = max($timemodified, (int) $post->modified);
        }

        return [
            'userid' => $userid,
            'status' => 'submitted',
            'content' => $content,
            'files' => $this->get_submission_files($userid),
            'onlinetext' => '',
            'timecreated' => $timecreated,
            'timemodified' => $timemodified,
            'attemptnumber' => 0,
            'commentcount' => submission_comment_manager::count_comments($this->cm->id, $userid),
        ];
    }

    /**
     * Get current grade and feedback for a user.
     *
     * @param int $userid
     * @return array
     */
    public function get_grade_data(int $userid): array {
        global $DB;

        $forumid = $this->forum->get_id();

        // Get grade from forum_grades table directly (avoids creating empty records).
        $graderecord = $DB->get_record('forum_grades', [
            'forum' => $forumid,
            'itemnumber' => 1,
            'userid' => $userid,
        ]);

        $hasgrade = $graderecord && $graderecord->grade !== null;

        // Get feedback from the gradebook (forums have no feedback table).
        $feedbacktext = '';
        $feedbackformat = (int) FORMAT_HTML;
        $gradeitem = $this->fetch_grade_item();
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
                    'forumfeedback',
                    (int) $gradegrade->id,
                );
                $feedbackformat = (int) ($gradegrade->feedbackformat ?? FORMAT_HTML);
            }
        }

        // Advanced grading: read the grading definition and current fill.
        // Skip entirely when forum grading is disabled (grade type "None") — a
        // marking guide/rubric without a grade type is a non-sequitur.
        $rubricdata = null;
        $gradingdefinition = null;

        if ($this->forum->is_grading_enabled()) {
            $gradingmanager = get_grading_manager($this->context, 'mod_forum', 'forum');
            $controller = $gradingmanager->get_active_controller();

            if ($controller) {
                $gradingdefinition = $this->serialize_grading_definition($controller);

                // Load rubric fill data whenever a grade record exists, even if grade
                // is null. The grading instance fillings may have been saved by
                // store_grade_from_formdata → update() even when the grade couldn't be
                // computed (e.g. forum grading type set to "none" → get_grade() returns -1).
                if ($graderecord) {
                    $rubricdata = $this->get_rubric_fill($controller, $graderecord);
                }
            }
        }

        return [
            'grade' => $hasgrade ? (float) $graderecord->grade : null,
            'feedback' => format_text($feedbacktext, $feedbackformat, ['context' => $this->context]),
            'feedbackformat' => $feedbackformat,
            'rubricdata' => $rubricdata ? json_encode($rubricdata) : '',
            'gradingdefinition' => $gradingdefinition ? json_encode($gradingdefinition) : '',
            'timegraded' => $graderecord ? (int) ($graderecord->timemodified ?? 0) : 0,
            'grader' => 0,
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
     * @param int $feedbackfilesdraftid Draft area item ID for feedback files plugin.
     * @param int $attemptnumber Attempt number (unused for forums).
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

        $forumid = $this->forum->get_id();
        $gradeduser = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        $graderuser = $DB->get_record('user', ['id' => $USER->id], '*', MUST_EXIST);

        // Check if advanced grading is active (only when grading is enabled).
        $controller = null;
        if ($this->forum->is_grading_enabled()) {
            $gradingmanager = get_grading_manager($this->context, 'mod_forum', 'forum');
            $controller = $gradingmanager->get_active_controller();
        }

        if ($controller && !empty($advancedgradingdata)) {
            // Advanced grading with criteria data — use the gradeitem API.
            $graderecord = $this->gradeitem->get_grade_for_user($gradeduser, $graderuser);
            $gradinginstance = $this->gradeitem->get_advanced_grading_instance(
                $graderuser,
                $graderecord,
            );

            $formdata = new \stdClass();
            $formdata->grade = $grade;
            $formdata->instanceid = $gradinginstance->get_id();
            $formdata->advancedgrading = $advancedgradingdata;

            $this->gradeitem->store_grade_from_formdata($gradeduser, $graderuser, $formdata);
        } else {
            // Simple grading or advanced grading without criteria data.
            // Update forum_grades directly and sync to gradebook.
            $this->save_grade_directly($userid, $grade);
        }

        // Ensure the gradebook record exists before processing feedback files,
        // since file storage is keyed by grade_grade.id.
        $this->sync_gradebook_penalty($userid);

        // Process feedback files from the draft area before saving feedback text.
        $feedbacktosave = $feedback;
        if ($draftitemid > 0) {
            $gradeitem = $this->fetch_grade_item();
            if ($gradeitem) {
                $gradegrade = \grade_grade::fetch([
                    'itemid' => $gradeitem->id,
                    'userid' => $userid,
                ]);
                if ($gradegrade) {
                    $feedbacktosave = file_save_draft_area_files(
                        $draftitemid,
                        $this->context->id,
                        'local_unifiedgrader',
                        'forumfeedback',
                        (int) $gradegrade->id,
                        $this->get_editor_options(),
                        $feedback,
                    );
                }
            }
        }

        // Persist feedback in the gradebook (forums have no feedback table).
        $this->save_feedback_to_gradebook($userid, $feedbacktosave, $feedbackformat);

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
     * @param int $attemptnumber Attempt number (unused for forums).
     * @return array With key 'feedbackhtml'.
     */
    public function prepare_feedback_draft(int $userid, int $draftitemid, int $attemptnumber = -1): array {
        global $USER;

        $feedbacktext = '';
        $gradegradeid = 0;

        $gradeitem = $this->fetch_grade_item();
        if ($gradeitem) {
            $gradegrade = \grade_grade::fetch([
                'itemid' => $gradeitem->id,
                'userid' => $userid,
            ]);
            if ($gradegrade) {
                $gradegradeid = (int) $gradegrade->id;
                $feedbacktext = $gradegrade->feedback ?? '';
            }
        }


        // Clear existing draft files from the previous student.
        $fs = get_file_storage();
        $usercontext = \context_user::instance($USER->id);
        $fs->delete_area_files($usercontext->id, 'user', 'draft', $draftitemid);

        // Copy this student's feedback files from permanent storage into the draft area.
        // NOTE: file_prepare_draft_area() only copies files when draftitemid is empty (0).
        // Since we reuse the same draftitemid across student switches, we must copy manually.
        if ($gradegradeid) {
            $files = $fs->get_area_files(
                $this->context->id,
                'local_unifiedgrader',
                'forumfeedback',
                $gradegradeid,
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
     * Get attachments from all of a user's forum posts.
     *
     * @param int $userid
     * @return array
     */
    public function get_submission_files(int $userid): array {
        global $DB;

        $forumid = $this->forum->get_id();

        // Get all discussion IDs for this forum.
        $discussions = $DB->get_records('forum_discussions', ['forum' => $forumid], '', 'id');
        if (empty($discussions)) {
            return [];
        }

        // Get all post IDs by this user.
        $discussionids = array_keys($discussions);
        [$insql, $params] = $DB->get_in_or_equal($discussionids, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;
        $postids = $DB->get_fieldset_select(
            'forum_posts',
            'id',
            "discussion {$insql} AND userid = :userid AND deleted = 0",
            $params,
        );

        if (empty($postids)) {
            return [];
        }

        // Get attachment files for these posts.
        $fs = get_file_storage();
        $converter = new \core_files\converter();
        $result = [];
        foreach ($postids as $postid) {
            $files = $fs->get_area_files(
                $this->context->id,
                'mod_forum',
                'attachment',
                $postid,
                'filename',
                false,
            );
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
            'rubric', 'markingguide' => (bool) get_grading_manager(
                $this->context, 'mod_forum', 'forum',
            )->get_active_method(),
            'onlinetext' => true,
            'filesubmission' => true,
            'blindmarking' => false,
            'annotations' => false,
            default => false,
        };
    }

    /**
     * Get plagiarism report links for a user's forum posts.
     *
     * Calls Moodle's generic plagiarism API for each post's text content
     * and each post attachment. Works with any plagiarism plugin
     * (Copyleaks, Turnitin, etc.).
     *
     * @param int $userid The user ID.
     * @return array Array of arrays with keys: 'label' (string), 'html' (string).
     */
    public function get_plagiarism_links(int $userid): array {
        global $CFG, $DB;

        if (empty($CFG->enableplagiarism)) {
            return [];
        }

        require_once($CFG->libdir . '/plagiarismlib.php');

        $forumid = $this->forum->get_id();
        $discussions = $DB->get_records('forum_discussions', ['forum' => $forumid], '', 'id');
        if (empty($discussions)) {
            return [];
        }

        $discussionids = array_keys($discussions);
        [$insql, $params] = $DB->get_in_or_equal($discussionids, SQL_PARAMS_NAMED);
        $params['userid'] = $userid;
        $posts = $DB->get_records_select(
            'forum_posts',
            "discussion {$insql} AND userid = :userid AND deleted = 0",
            $params,
            'created ASC',
        );

        if (empty($posts)) {
            return [];
        }

        $results = [];
        $fs = get_file_storage();

        foreach ($posts as $post) {
            // Post text plagiarism.
            $linkhtml = plagiarism_get_links([
                'userid' => $userid,
                'content' => $post->message,
                'cmid' => $this->cm->id,
                'course' => $this->course->id,
                'forum' => $forumid,
            ]);
            if (!empty(trim($linkhtml))) {
                $results[] = [
                    'label' => format_string($post->subject),
                    'html' => $linkhtml,
                ];
            }

            // Per-attachment plagiarism.
            $files = $fs->get_area_files(
                $this->context->id,
                'mod_forum',
                'attachment',
                $post->id,
                'filename',
                false,
            );
            foreach ($files as $file) {
                $filehtml = plagiarism_get_links([
                    'userid' => $userid,
                    'file' => $file,
                    'cmid' => $this->cm->id,
                    'course' => $this->course->id,
                    'forum' => $forumid,
                ]);
                if (!empty(trim($filehtml))) {
                    $results[] = [
                        'label' => $file->get_filename(),
                        'html' => $filehtml,
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Check whether a forum grade has been released to the student.
     *
     * A forum grade is considered released when a forum_grades record exists
     * with a non-null grade AND the gradebook item is not hidden.
     *
     * @param int $userid
     * @return bool
     */
    public function is_grade_released(int $userid): bool {
        global $DB;

        $forumid = $this->forum->get_id();

        if ($this->forum->is_grading_enabled()) {
            // Graded forum: require a numeric grade in forum_grades.
            $graderecord = $DB->get_record('forum_grades', [
                'forum' => $forumid,
                'itemnumber' => 1,
                'userid' => $userid,
            ]);
            if (!$graderecord || $graderecord->grade === null) {
                return false;
            }
        } else {
            // Grading-disabled forum (grade type "None"): feedback-only.
            // Consider feedback "released" when a grade_grades record with
            // non-empty feedback exists — this is the only way the teacher
            // communicates with the student in this mode.
            $gradeitem = $this->fetch_grade_item();
            if (!$gradeitem) {
                return false;
            }
            $gradegrade = \grade_grade::fetch([
                'itemid' => $gradeitem->id,
                'userid' => $userid,
            ]);
            if (!$gradegrade || empty($gradegrade->feedback)) {
                return false;
            }
        }

        // Check the gradebook item is not hidden.
        $gradeitem = $this->fetch_grade_item();
        if ($gradeitem && $gradeitem->is_hidden()) {
            return false;
        }

        return true;
    }

    /**
     * Get the grading definition (rubric/marking guide) for this forum.
     *
     * @return array|null
     */
    public function get_grading_definition(): ?array {
        $gradingmanager = get_grading_manager($this->context, 'mod_forum', 'forum');
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
     * Fetch the grade_item for whole-forum grading (itemnumber 1).
     *
     * @return \grade_item|null
     */
    protected function fetch_grade_item(): ?\grade_item {
        $params = [
            'itemtype' => 'mod',
            'itemmodule' => 'forum',
            'iteminstance' => $this->forum->get_id(),
            'itemnumber' => 1,
            'courseid' => $this->course->id,
        ];
        $item = \grade_item::fetch($params);

        if (!$item && !$this->forum->is_grading_enabled()) {
            // For forums with grade type "None", Moodle's grade_update() skips
            // creating the grade_item entirely (gradelib.php returns early when
            // gradetype == GRADE_TYPE_NONE for a new item). Create it manually
            // so we can store feedback in the gradebook even without a numeric grade.
            $params['gradetype'] = GRADE_TYPE_NONE;
            $params['itemname'] = get_string('gradeitemnameforwholeforum', 'forum', $this->forumrecord);
            $item = new \grade_item($params, false);
            $item->insert('local/unifiedgrader');
        }

        return $item ?: null;
    }

    /**
     * Resolve the display status from post and grade data.
     *
     * @param bool $hasposts Whether the user has any posts.
     * @param bool $hasgrade Whether the user has a grade.
     * @return string
     */
    private function resolve_status(bool $hasposts, bool $hasgrade): string {
        if ($hasgrade) {
            return 'graded';
        }
        if ($hasposts) {
            return 'submitted';
        }
        return 'nosubmission';
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
     * Render forum posts as HTML grouped by discussion.
     *
     * Includes per-post plagiarism shield icons when a plagiarism plugin
     * is enabled and returns results for the post content or attachments.
     *
     * @param array $posts Post records.
     * @param array $discussions Discussion records keyed by id.
     * @param int $userid The user ID for plagiarism checks.
     * @return string HTML content.
     */
    private function render_posts_as_html(array $posts, array $discussions, int $userid = 0): string {
        global $CFG;

        $plagiarismenabled = !empty($CFG->enableplagiarism);
        if ($plagiarismenabled) {
            require_once($CFG->libdir . '/plagiarismlib.php');
        }

        // Pre-build the academic impropriety report URL (same for all posts of this user).
        $reporturl = '';
        $reportenabled = get_config('local_unifiedgrader', 'enable_report_form');
        $reporturltemplate = get_config('local_unifiedgrader', 'report_form_url');
        if (!empty($reportenabled) && !empty($reporturltemplate) && $userid) {
            $reporturl = $this->build_report_url($reporturltemplate, $userid);
        }

        // Group posts by discussion.
        $grouped = [];
        foreach ($posts as $post) {
            $did = (int) $post->discussion;
            $grouped[$did][] = $post;
        }

        $forumid = $this->forum->get_id();
        $fs = $plagiarismenabled ? get_file_storage() : null;
        $hasanyplagiarism = false;

        $html = '';
        foreach ($grouped as $discussionid => $discussionposts) {
            $discussion = $discussions[$discussionid] ?? null;
            $discussionname = $discussion ? format_string($discussion->name) : get_string('discussion', 'forum');

            $html .= '<div class="mb-4">';
            $html .= '<h5 class="border-bottom pb-2 mb-3">' . $discussionname . '</h5>';

            foreach ($discussionposts as $post) {
                // Rewrite @@PLUGINFILE@@ URLs to actual pluginfile.php URLs
                // before formatting. Without this, embedded media (video, audio,
                // images) stored via the editor would have broken source URLs.
                $message = file_rewrite_pluginfile_urls(
                    $post->message,
                    'pluginfile.php',
                    $this->context->id,
                    'mod_forum',
                    'post',
                    $post->id,
                );
                $formattedmessage = format_text(
                    $message,
                    $post->messageformat,
                    ['context' => $this->context],
                );
                $postdate = userdate($post->created);
                $subject = format_string($post->subject);
                $wordcount = count_words(html_to_text($formattedmessage, 0, false));
                $wordcountlabel = get_string('forum_wordcount', 'local_unifiedgrader', $wordcount);

                // Gather plagiarism data for this post.
                $shieldhtml = '';
                if ($plagiarismenabled) {
                    $plagiarismhtml = $this->get_post_plagiarism_html($post, $forumid, $fs);
                    if (!empty($plagiarismhtml)) {
                        $hasanyplagiarism = true;
                        $severity = $this->extract_plagiarism_severity($plagiarismhtml);
                        $percentage = $this->extract_plagiarism_percentage($plagiarismhtml);
                        $shieldhtml = $this->render_plagiarism_shield(
                            $post->id, $severity, $percentage, $plagiarismhtml, $reporturl,
                        );
                    }
                }

                $html .= '<div class="card mb-2">';
                $html .= '<div class="card-header py-1 small text-muted'
                    . ' d-flex justify-content-between align-items-center">';
                $html .= '<span><strong>' . $subject . '</strong> &mdash; ' . $postdate . '</span>';
                $html .= '<span class="ms-2 text-nowrap d-flex align-items-center gap-2">';
                $html .= $wordcountlabel;
                $html .= $shieldhtml;
                $html .= '</span>';
                $html .= '</div>';
                $html .= '<div class="card-body py-2">' . $formattedmessage . '</div>';
                $html .= '</div>';
            }

            $html .= '</div>';
        }

        // Append inline script for popout toggle if any shields were rendered.
        if ($hasanyplagiarism) {
            $html .= $this->render_plagiarism_inline_script();
        }

        return $html;
    }

    /**
     * Get combined plagiarism HTML for a single forum post (text + attachments).
     *
     * @param \stdClass $post The forum post record.
     * @param int $forumid The forum ID.
     * @param \file_storage $fs File storage instance.
     * @return string Combined plagiarism HTML, or empty string.
     */
    private function get_post_plagiarism_html(\stdClass $post, int $forumid, \file_storage $fs): string {
        $parts = [];

        // Post text.
        $linkhtml = plagiarism_get_links([
            'userid' => (int) $post->userid,
            'content' => $post->message,
            'cmid' => $this->cm->id,
            'course' => $this->course->id,
            'forum' => $forumid,
        ]);
        if (!empty(trim($linkhtml))) {
            $parts[] = '<div class="mb-1"><strong class="small">'
                . get_string('onlinetext', 'local_unifiedgrader')
                . '</strong></div>' . $linkhtml;
        }

        // Attachments.
        $files = $fs->get_area_files(
            $this->context->id,
            'mod_forum',
            'attachment',
            $post->id,
            'filename',
            false,
        );
        foreach ($files as $file) {
            $filehtml = plagiarism_get_links([
                'userid' => (int) $post->userid,
                'file' => $file,
                'cmid' => $this->cm->id,
                'course' => $this->course->id,
                'forum' => $forumid,
            ]);
            if (!empty(trim($filehtml))) {
                $parts[] = '<div class="mb-1"><strong class="small">'
                    . s($file->get_filename())
                    . '</strong></div>' . $filehtml;
            }
        }

        return implode('<hr class="my-2">', $parts);
    }

    /**
     * Extract a numeric percentage from plagiarism HTML.
     *
     * Plagiarism plugins return arbitrary HTML. This attempts to find a
     * percentage pattern (e.g. "42%") common across Turnitin, Copyleaks,
     * and most other plugins.
     *
     * @param string $html Plagiarism HTML from plagiarism_get_links().
     * @return string|null Percentage string (e.g. "42") or null if not found.
     */
    private function extract_plagiarism_percentage(string $html): ?string {
        $text = strip_tags($html);
        if (preg_match('/(\d+)\s*%/', $text, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extract severity level from plagiarism plugin HTML.
     *
     * Reads the CSS classes that the plagiarism plugin itself uses to
     * colour its widget, so we respect the plugin's own configurable
     * thresholds rather than inventing our own.
     *
     * Supports Copyleaks (plagiarism + AI dual indicators) and Turnitin.
     * For Copyleaks, if either plagiarism or AI indicator is at a higher
     * severity, the higher severity wins.
     *
     * @param string $html Raw HTML from plagiarism_get_links().
     * @return string One of: 'success', 'warning', 'danger', 'pending', 'error', 'unknown'.
     */
    private function extract_plagiarism_severity(string $html): string {
        // Severity hierarchy (higher index = worse).
        $levels = ['success' => 0, 'warning' => 1, 'danger' => 2];
        $worst = -1;

        // --- Copyleaks ---
        // Plagiarism indicator: cls-plag-score-level-{low,mid,high}.
        // AI content indicator: cls-ai-score-level-{low,mid,high}.
        $copyleaksmap = ['low' => 'success', 'mid' => 'warning', 'high' => 'danger'];

        if (preg_match_all('/cls-(?:plag|ai)-score-level-(low|mid|high)/', $html, $matches)) {
            foreach ($matches[1] as $level) {
                $severity = $copyleaksmap[$level];
                $worst = max($worst, $levels[$severity]);
            }
        }

        // Copyleaks pending/error states.
        if (preg_match('/\b(in-progress|scheduled)\b/', $html)) {
            return $worst >= 0 ? array_search($worst, $levels) : 'pending';
        }
        if (preg_match('/\b(failed|error|retry)\b/', $html)) {
            return $worst >= 0 ? array_search($worst, $levels) : 'error';
        }

        // --- Turnitin ---
        // Score colour classes: score_colour_75 (red), score_colour_50 (orange),
        // score_colour_25 (green/yellow). No class = very low (blue).
        if (preg_match_all('/score_colour_(\d+)/', $html, $matches)) {
            foreach ($matches[1] as $threshold) {
                $threshold = (int) $threshold;
                if ($threshold >= 75) {
                    $worst = max($worst, $levels['danger']);
                } else if ($threshold >= 50) {
                    $worst = max($worst, $levels['warning']);
                } else {
                    $worst = max($worst, $levels['success']);
                }
            }
        }

        // Turnitin pending: look for queued/pending indicators.
        if (preg_match('/class="[^"]*turnitin_status[^"]*"/', $html) && $worst < 0) {
            return 'pending';
        }

        if ($worst >= 0) {
            return array_search($worst, $levels);
        }

        // --- Generic fallback ---
        // Look for common Bootstrap-style colour classes used by other plugins.
        if (preg_match('/\b(?:text-danger|bg-danger|badge-danger|alert-danger)\b/', $html)) {
            return 'danger';
        }
        if (preg_match('/\b(?:text-warning|bg-warning|badge-warning|alert-warning)\b/', $html)) {
            return 'warning';
        }
        if (preg_match('/\b(?:text-success|bg-success|badge-success|alert-success)\b/', $html)) {
            return 'success';
        }

        return 'unknown';
    }

    /**
     * Render the shield icon HTML with optional percentage and hidden popout.
     *
     * @param int $postid The post ID (used for unique element IDs).
     * @param string $severity Severity level from extract_plagiarism_severity().
     * @param string|null $percentage Extracted percentage, or null.
     * @param string $plagiarismhtml Full plagiarism widget HTML for the popout.
     * @param string $reporturl URL for the full plagiarism report.
     * @return string HTML for the shield + popout.
     */
    private function render_plagiarism_shield(
        int $postid,
        string $severity,
        ?string $percentage,
        string $plagiarismhtml,
        string $reporturl = '',
    ): string {
        $percentlabel = $percentage !== null ? ($percentage . '%') : '';
        $shieldtitle = get_string('plagiarism', 'local_unifiedgrader');

        // Map severity to Bootstrap text colour class.
        $colourclass = match ($severity) {
            'success' => 'text-success',
            'warning' => 'text-warning',
            'danger' => 'text-danger',
            'pending' => 'text-muted',
            'error' => 'text-muted',
            default => 'text-secondary',
        };

        // For pending/error states, use a different icon indicator.
        $iconclass = 'fa-shield';
        if ($severity === 'pending') {
            $iconclass = 'fa-shield';
            $percentlabel = '';
            $shieldtitle = get_string('plagiarism_pending', 'local_unifiedgrader');
        } else if ($severity === 'error') {
            $iconclass = 'fa-shield';
            $percentlabel = '';
            $shieldtitle = get_string('plagiarism_error', 'local_unifiedgrader');
        }

        $html = '<span class="position-relative d-inline-flex align-items-center"'
            . ' data-region="plagiarism-shield-wrapper">';
        $html .= '<button type="button"'
            . ' class="btn btn-link btn-sm p-0 ms-1 plagiarism-shield-btn ' . $colourclass . '"'
            . ' data-postid="' . $postid . '"'
            . ' title="' . s($shieldtitle) . '"'
            . ' aria-label="' . s($shieldtitle) . '">';
        $html .= '<i class="fa ' . $iconclass . '" aria-hidden="true"></i>';
        if ($severity === 'pending') {
            $html .= ' <i class="fa fa-spinner fa-spin small" aria-hidden="true"></i>';
        } else if ($severity === 'error') {
            $html .= ' <i class="fa fa-exclamation-triangle small" aria-hidden="true"></i>';
        } else if (!empty($percentlabel)) {
            $html .= ' <span class="small fw-bold">' . $percentlabel . '</span>';
        }
        $html .= '</button>';
        $html .= '<div class="local-unifiedgrader-plagiarism-popout d-none"'
            . ' data-region="plagiarism-popout" data-postid="' . $postid . '">';
        $html .= '<div class="d-flex justify-content-between align-items-center mb-2">';
        $html .= '<strong class="small">' . s(get_string('plagiarism', 'local_unifiedgrader')) . '</strong>';
        $html .= '<button type="button" class="btn btn-sm btn-link p-0 plagiarism-close-btn"'
            . ' aria-label="Close"><i class="fa fa-times"></i></button>';
        $html .= '</div>';
        $html .= '<div class="plagiarism-popout-content small">' . $plagiarismhtml . '</div>';
        if (!empty($reporturl)) {
            $reportlabel = get_string('report_impropriety', 'local_unifiedgrader');
            $html .= '<div class="mt-2 pt-2 border-top">';
            $html .= '<a href="' . s($reporturl) . '" target="_blank" rel="noopener"'
                . ' class="btn btn-sm btn-outline-danger w-100">';
            $html .= '<i class="fa fa-flag me-1"></i>' . s($reportlabel);
            $html .= '</a>';
            $html .= '</div>';
        }
        $html .= '</div>';
        $html .= '</span>';

        return $html;
    }

    /**
     * Build the academic impropriety report URL with placeholders replaced.
     *
     * @param string $urltemplate URL template with placeholders.
     * @param int $userid The student user ID.
     * @return string The resolved URL.
     */
    private function build_report_url(string $urltemplate, int $userid): string {
        global $USER, $DB, $CFG;

        $student = $DB->get_record('user', ['id' => $userid], 'id, firstname, lastname');
        $studentname = $student ? fullname($student) : '';
        $coursecode = \local_unifiedgrader\course_code_helper::extract_code($this->course->shortname);
        $graderurl = $CFG->wwwroot . '/local/unifiedgrader/grade.php?cmid='
            . $this->cm->id . '&userid=' . $userid;

        $replacements = [
            '{studentname}' => rawurlencode($studentname),
            '{coursecode}' => rawurlencode($coursecode),
            '{coursename}' => rawurlencode(format_string($this->course->fullname)),
            '{activityname}' => rawurlencode(format_string($this->forum->get_name())),
            '{activitytype}' => rawurlencode('forum'),
            '{studentid}' => rawurlencode((string) $userid),
            '{gradername}' => rawurlencode(fullname($USER)),
            '{graderurl}' => rawurlencode($graderurl),
        ];

        return str_ireplace(array_keys($replacements), array_values($replacements), $urltemplate);
    }

    /**
     * Render the inline JavaScript for plagiarism popout toggle.
     *
     * Uses event delegation and outside-click to close pattern,
     * consistent with the annotation toolbar's docinfo popout.
     *
     * @return string HTML script block.
     */
    private function render_plagiarism_inline_script(): string {
        return <<<'SCRIPT'
<script>
(function() {
    var activePopout = null;
    var outsideHandler = null;

    function closeActive() {
        if (activePopout) {
            activePopout.classList.add('d-none');
            activePopout = null;
        }
        if (outsideHandler) {
            document.removeEventListener('click', outsideHandler, true);
            outsideHandler = null;
        }
    }

    document.addEventListener('click', function(e) {
        // Handle close button.
        var closeBtn = e.target.closest('.plagiarism-close-btn');
        if (closeBtn) {
            closeActive();
            return;
        }

        // Handle shield button toggle.
        var shieldBtn = e.target.closest('.plagiarism-shield-btn');
        if (!shieldBtn) {
            return;
        }

        var wrapper = shieldBtn.closest('[data-region="plagiarism-shield-wrapper"]');
        if (!wrapper) {
            return;
        }
        var popout = wrapper.querySelector('[data-region="plagiarism-popout"]');
        if (!popout) {
            return;
        }

        // If clicking the same shield, toggle off.
        if (activePopout === popout) {
            closeActive();
            return;
        }

        // Close any other open popout first.
        closeActive();

        // Open this popout.
        popout.classList.remove('d-none');
        activePopout = popout;

        // Defer outside-click handler so current click doesn't trigger it.
        requestAnimationFrame(function() {
            outsideHandler = function(evt) {
                if (!wrapper.contains(evt.target)) {
                    closeActive();
                }
            };
            document.addEventListener('click', outsideHandler, true);
        });
    });
})();
</script>
SCRIPT;
    }

    /**
     * Save grade directly to forum_grades table and sync to gradebook.
     *
     * Used for simple grading or when advanced grading is active but no
     * criteria data is provided (numeric override).
     *
     * @param int $userid
     * @param float|null $grade
     */
    private function save_grade_directly(int $userid, ?float $grade): void {
        global $DB;

        $forumid = $this->forum->get_id();

        // Get or create the grade record (stores the raw teacher-given grade).
        $graderecord = $DB->get_record('forum_grades', [
            'forum' => $forumid,
            'itemnumber' => 1,
            'userid' => $userid,
        ]);

        if ($graderecord) {
            $graderecord->grade = $grade;
            $graderecord->timemodified = time();
            $DB->update_record('forum_grades', $graderecord);
        } else {
            $graderecord = (object) [
                'forum' => $forumid,
                'itemnumber' => 1,
                'userid' => $userid,
                'grade' => $grade,
                'timecreated' => time(),
                'timemodified' => time(),
            ];
            $DB->insert_record('forum_grades', $graderecord);
        }

        // Note: gradebook sync is handled by sync_gradebook_penalty() which is
        // called by the save_grade web service after this method returns.
        // This ensures the gradebook gets the penalized grade while forum_grades
        // retains the raw teacher-given grade.
    }

    /**
     * Push the penalized grade to the gradebook.
     *
     * Reads the raw (teacher-given) grade from forum_grades, calculates the
     * total penalty deduction, and pushes (rawgrade - deduction) to the
     * Moodle gradebook. This keeps forum_grades as the source of truth for
     * the teacher's intended grade while the gradebook reflects penalties.
     *
     * Called after grade save, extension grant/delete, and penalty changes.
     *
     * @param int $userid Student user ID.
     */
    public function sync_gradebook_penalty(int $userid): void {
        global $DB, $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/mod/forum/lib.php');

        $forumid = $this->forum->get_id();

        $graderecord = $DB->get_record('forum_grades', [
            'forum' => $forumid,
            'itemnumber' => 1,
            'userid' => $userid,
        ]);

        // Build the grade to push. If no grade exists, push null to clear.
        $this->forumrecord->cmidnumber = $this->cm->idnumber;

        if (!$graderecord || $graderecord->grade === null) {
            // No grade yet — just ensure the grade item exists.
            forum_grade_item_update($this->forumrecord);
            return;
        }

        $rawgrade = (float) $graderecord->grade;

        // Calculate penalty deduction.
        $maxgrade = (float) $this->forum->get_grade_for_forum();
        $deduction = \local_unifiedgrader\penalty_manager::get_total_deduction(
            (int) $this->cm->id,
            $userid,
            $maxgrade > 0 ? $maxgrade : 100,
        );

        $penalizedgrade = $deduction > 0 ? max(0, $rawgrade - $deduction) : $rawgrade;

        // Push the penalized grade to the gradebook.
        $forumgrades = [
            $userid => (object) [
                'userid' => $userid,
                'rawgrade' => $penalizedgrade,
                'datesubmitted' => 0,
                'dategraded' => (int) $graderecord->timemodified,
            ],
        ];
        forum_grade_item_update($this->forumrecord, null, $forumgrades);
    }

    /**
     * Save feedback text to the gradebook.
     *
     * Forums have no feedback table, so feedback is persisted in the
     * gradebook's grade_grades record.
     *
     * @param int $userid
     * @param string $feedback
     * @param int $feedbackformat
     */
    private function save_feedback_to_gradebook(int $userid, string $feedback, int $feedbackformat): void {
        $gradeitem = $this->fetch_grade_item();

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
        } else {
            // No grade_grade record yet — this happens when the teacher writes
            // feedback before entering a numeric grade. Create a record so
            // the feedback is persisted in the gradebook.
            $gradegrade = new \grade_grade();
            $gradegrade->itemid = $gradeitem->id;
            $gradegrade->userid = $userid;
            $gradegrade->feedback = $feedback;
            $gradegrade->feedbackformat = $feedbackformat;
            $gradegrade->timecreated = time();
            $gradegrade->timemodified = time();
            $gradegrade->insert('local/unifiedgrader');
        }
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

        $method = get_grading_manager($this->context, 'mod_forum', 'forum')->get_active_method();

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
     * @param \stdClass $grade The forum_grades record.
     * @return array|null
     */
    private function get_rubric_fill(\gradingform_controller $controller, \stdClass $grade): ?array {
        try {
            $instances = $controller->get_active_instances($grade->id);
            if (empty($instances)) {
                return null;
            }

            $instance = end($instances);

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
     * Calculate the late penalty for a student based on Moodle's penalty rules.
     *
     * Uses the gradepenalty_duedate plugin's configured rules (module → course → system
     * context hierarchy) to determine the penalty percentage for late forum submissions.
     *
     * @param int $userid Student user ID.
     * @return array|null Array with 'percentage' (int) and 'dayslate' (int), or null if not late/not applicable.
     */
    public function calculate_late_penalty(int $userid): ?array {
        global $DB;

        // No penalty if gradepenalty_duedate plugin is not installed.
        if (!class_exists('\gradepenalty_duedate\penalty_calculator')) {
            return null;
        }

        // Use effective due date (accounts for extensions).
        $duedate = $this->get_effective_duedate($userid);
        if ($duedate <= 0) {
            return null;
        }

        // No penalty for scale-based grading.
        if ((int) $this->forum->get_grade_for_forum() < 0) {
            return null;
        }

        // Use the first post time to determine lateness — a student who posted
        // on time but added follow-up posts after the due date is NOT late.
        $forumid = $this->forum->get_id();
        $firstpost = $DB->get_field_sql(
            "SELECT MIN(p.created)
               FROM {forum_posts} p
               JOIN {forum_discussions} d ON d.id = p.discussion
              WHERE d.forum = :forumid AND p.userid = :userid AND p.deleted = 0",
            ['forumid' => $forumid, 'userid' => $userid],
        );

        if (empty($firstpost)) {
            return null;
        }

        $submissiontime = (int) $firstpost;

        // Not late.
        if ($submissiontime <= $duedate) {
            return null;
        }

        // Get penalty percentage from Moodle's duedate penalty rules.
        $percentage = \gradepenalty_duedate\penalty_calculator::get_penalty_from_rules(
            $this->cm,
            $submissiontime,
            $duedate,
        );

        $pct = (int) round($percentage);
        if ($pct <= 0) {
            return null;
        }

        $secondslate = $submissiontime - $duedate;
        $dayslate = (int) ceil($secondslate / DAYSECS);

        return [
            'percentage' => $pct,
            'dayslate' => $dayslate,
        ];
    }

    /**
     * Get the effective due date for a student, accounting for extensions.
     *
     * @param int $userid Student user ID.
     * @return int Effective due date timestamp (0 if no due date).
     */
    public function get_effective_duedate(int $userid): int {
        global $DB;

        $extension = $DB->get_field('local_unifiedgrader_fext', 'extensionduedate', [
            'cmid' => (int) $this->cm->id,
            'userid' => $userid,
        ]);

        if ($extension !== false && (int) $extension > 0) {
            return (int) $extension;
        }

        return (int) $this->forum->get_due_date();
    }

    /**
     * Get the forum extension for a student.
     *
     * @param int $userid Student user ID.
     * @return array|null Extension data or null if no extension.
     */
    public function get_forum_extension(int $userid): ?array {
        global $DB;

        $record = $DB->get_record('local_unifiedgrader_fext', [
            'cmid' => (int) $this->cm->id,
            'userid' => $userid,
        ]);

        if (!$record) {
            return null;
        }

        return [
            'id' => (int) $record->id,
            'extensionduedate' => (int) $record->extensionduedate,
        ];
    }

    /**
     * Save a forum extension for a student.
     *
     * @param int $userid Student user ID.
     * @param int $extensionduedate Extended due date timestamp.
     */
    public function save_forum_extension(int $userid, int $extensionduedate): void {
        global $DB, $USER;

        $now = time();
        $existing = $DB->get_record('local_unifiedgrader_fext', [
            'cmid' => (int) $this->cm->id,
            'userid' => $userid,
        ]);

        if ($existing) {
            $existing->extensionduedate = $extensionduedate;
            $existing->timemodified = $now;
            $DB->update_record('local_unifiedgrader_fext', $existing);
        } else {
            $DB->insert_record('local_unifiedgrader_fext', (object) [
                'cmid' => (int) $this->cm->id,
                'userid' => $userid,
                'extensionduedate' => $extensionduedate,
                'authorid' => (int) $USER->id,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }
    }

    /**
     * Delete a forum extension for a student.
     *
     * @param int $userid Student user ID.
     */
    public function delete_forum_extension(int $userid): void {
        global $DB;

        $DB->delete_records('local_unifiedgrader_fext', [
            'cmid' => (int) $this->cm->id,
            'userid' => $userid,
        ]);
    }
}
