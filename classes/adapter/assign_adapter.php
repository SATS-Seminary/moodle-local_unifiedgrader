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
 * @copyright  2026 South African Theological Seminary
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\adapter;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/locallib.php');
require_once($CFG->dirroot . '/grade/grading/lib.php');

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

        return [
            'id' => (int) $this->cm->id,
            'name' => format_string($instance->name),
            'type' => 'assign',
            'duedate' => (int) $instance->duedate,
            'cutoffdate' => (int) $instance->cutoffdate,
            'maxgrade' => (float) $instance->grade,
            'intro' => format_text(
                $instance->intro,
                $instance->introformat,
                ['context' => $this->context],
            ),
            'gradingmethod' => $gradingmethod ?: 'simple',
            'teamsubmission' => (bool) $instance->teamsubmission,
            'blindmarking' => (bool) $instance->blindmarking,
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

        $groupid = $filters['group'] ?? 0;
        $participants = $this->assign->list_participants($groupid, false);
        $instance = $this->assign->get_instance();

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

            $entry = [
                'id' => (int) $participant->id,
                'fullname' => $fullname,
                'email' => $instance->blindmarking ? '' : $participant->email,
                'profileimageurl' => $profileimageurl,
                'status' => $status,
                'submittedat' => $submission ? (int) $submission->timemodified : 0,
                'gradevalue' => ($grade && $grade->grade !== null && $grade->grade >= 0)
                    ? (float) $grade->grade : null,
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
     * Get full submission data for a user.
     *
     * @param int $userid
     * @return array
     */
    public function get_submission_data(int $userid): array {
        $submission = $this->assign->get_user_submission($userid, false);

        if (!$submission) {
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

        $onlinetext = $this->get_onlinetext($submission);

        return [
            'userid' => $userid,
            'status' => $submission->status,
            'content' => $this->get_submission_content($submission),
            'files' => $this->get_submission_files($userid),
            'onlinetext' => $onlinetext,
            'timecreated' => (int) $submission->timecreated,
            'timemodified' => (int) $submission->timemodified,
            'attemptnumber' => (int) $submission->attemptnumber,
        ];
    }

    /**
     * Get current grade and feedback for a user.
     *
     * @param int $userid
     * @return array
     */
    public function get_grade_data(int $userid): array {
        $grade = $this->assign->get_user_grade($userid, false);

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
        $gradingmanager = get_grading_manager($this->context, 'mod_assign', 'submissions');
        $controller = $gradingmanager->get_active_controller();
        $rubricdata = null;
        $gradingdefinition = null;

        if ($controller) {
            $gradingdefinition = $this->serialize_grading_definition($controller);

            if ($grade) {
                $rubricdata = $this->get_rubric_fill($controller, $grade, $userid);
            }
        }

        return [
            'grade' => ($grade && $grade->grade !== null && $grade->grade >= 0)
                ? (float) $grade->grade : null,
            'feedback' => $feedbacktext,
            'feedbackformat' => (int) $feedbackformat,
            'rubricdata' => $rubricdata ? json_encode($rubricdata) : '',
            'gradingdefinition' => $gradingdefinition ? json_encode($gradingdefinition) : '',
            'timegraded' => $grade ? (int) $grade->timemodified : 0,
            'grader' => $grade ? (int) $grade->grader : 0,
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

        // Moodle's assign::save_grade() requires attemptnumber to identify
        // which submission attempt the grade applies to.
        $submission = $this->assign->get_user_submission($userid, false) ?: null;
        $attemptnumber = $submission ? (int) $submission->attemptnumber : 0;

        // Check if advanced grading (rubric/marking guide) is active.
        $gradingmanager = get_grading_manager($this->context, 'mod_assign', 'submissions');
        $controller = $gradingmanager->get_active_controller();

        if ($controller && empty($advancedgradingdata)) {
            // Advanced grading is active but no criteria data provided.
            // Bypass assign::save_grade() to avoid the grading form trying
            // to process null criteria data (causes foreach-on-null warnings).
            $this->save_grade_directly($userid, $grade, $feedback, $feedbackformat, $attemptnumber);
            return true;
        }

        $data = new \stdClass();
        $data->grade = $grade;
        $data->attemptnumber = $attemptnumber;
        $data->assignfeedbackcomments_editor = [
            'text' => $feedback,
            'format' => $feedbackformat,
        ];

        if (!empty($advancedgradingdata)) {
            $data->advancedgrading = $advancedgradingdata;
        }

        $this->assign->save_grade($userid, $data);
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
     */
    private function save_grade_directly(
        int $userid,
        ?float $grade,
        string $feedback,
        int $feedbackformat,
        int $attemptnumber,
    ): void {
        global $USER, $DB;

        // Get or create the grade record.
        $gradeobj = $this->assign->get_user_grade($userid, true, $attemptnumber);
        $gradeobj->grade = $grade ?? -1;
        $gradeobj->grader = $USER->id;
        $gradeobj->timemodified = time();
        $DB->update_record('assign_grades', $gradeobj);

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

        // Push to gradebook and trigger events.
        $this->assign->update_grade($gradeobj);
    }

    /**
     * Get submitted files for document preview.
     *
     * @param int $userid
     * @return array
     */
    public function get_submission_files(int $userid): array {
        $submission = $this->assign->get_user_submission($userid, false);
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
                $this->context, 'mod_assign', 'submissions',
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
        } elseif ($method === 'guide' && !empty($definition->guide_criteria)) {
            $criteria = [];
            foreach ($definition->guide_criteria as $criterionid => $criterion) {
                $criteria[] = [
                    'id' => (int) $criterionid,
                    'shortname' => $criterion['shortname'] ?? '',
                    'description' => $criterion['description'] ?? '',
                    'descriptionmarkers' => $criterion['descriptionmarkers'] ?? '',
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
}
