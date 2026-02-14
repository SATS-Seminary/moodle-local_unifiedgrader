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
 * @copyright  2026 South African Theological Seminary
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
     * @return bool Success.
     */
    abstract public function save_grade(
        int $userid,
        ?float $grade,
        string $feedback,
        int $feedbackformat = FORMAT_HTML,
        array $advancedgradingdata = [],
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
}
