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
 * Test data generator for local_unifiedgrader.
 *
 * @package    local_unifiedgrader
 * @category   test
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Data generator for the unified grader plugin.
 *
 * Provides helper methods for creating plugin-specific test data
 * and common grading scenarios.
 */
class local_unifiedgrader_generator extends component_generator_base {

    /** @var int Counter for notes. */
    protected int $notecount = 0;

    /** @var int Counter for annotations. */
    protected int $annotcount = 0;

    /** @var int Counter for library comments. */
    protected int $clibcount = 0;

    /** @var int Counter for library tags. */
    protected int $tagcount = 0;

    /** @var int Counter for penalties. */
    protected int $penaltycount = 0;

    /** @var int Counter for grading scenarios (used for unique course shortnames). */
    protected int $scenariocount = 0;

    /**
     * Reset generator counters.
     */
    public function reset(): void {
        $this->notecount = 0;
        $this->annotcount = 0;
        $this->clibcount = 0;
        $this->tagcount = 0;
        $this->penaltycount = 0;
        $this->scenariocount = 0;
        parent::reset();
    }

    /**
     * Create a private teacher note.
     *
     * @param array $data Required: cmid, userid, authorid. Optional: content, timecreated, timemodified.
     * @return \stdClass The created record.
     */
    public function create_note(array $data): \stdClass {
        global $DB;

        $this->notecount++;
        $now = time();

        $record = (object) array_merge([
            'content' => "Test note {$this->notecount}",
            'timecreated' => $now,
            'timemodified' => $now,
        ], $data);

        $record->id = $DB->insert_record('local_unifiedgrader_notes', $record);
        return $record;
    }

    /**
     * Create an annotation record.
     *
     * @param array $data Required: cmid, userid, authorid, fileid. Optional: pagenum, annotationdata.
     * @return \stdClass The created record.
     */
    public function create_annotation(array $data): \stdClass {
        global $DB;

        $this->annotcount++;
        $now = time();

        $record = (object) array_merge([
            'pagenum' => 0,
            'annotationdata' => '{"objects":[]}',
            'timecreated' => $now,
            'timemodified' => $now,
        ], $data);

        $record->id = $DB->insert_record('local_unifiedgrader_annot', $record);
        return $record;
    }

    /**
     * Create a user preference record.
     *
     * @param array $data Required: userid. Optional: preferences (JSON string).
     * @return \stdClass The created record.
     */
    public function create_preference(array $data): \stdClass {
        global $DB;

        $now = time();

        $record = (object) array_merge([
            'preferences' => '{}',
            'timemodified' => $now,
        ], $data);

        $record->id = $DB->insert_record('local_unifiedgrader_prefs', $record);
        return $record;
    }

    /**
     * Create a comment library v2 entry.
     *
     * @param array $data Required: userid. Optional: coursecode, content, shared, sortorder.
     * @return \stdClass The created record.
     */
    public function create_library_comment(array $data): \stdClass {
        global $DB;

        $this->clibcount++;
        $now = time();

        $record = (object) array_merge([
            'coursecode' => '',
            'content' => "Test library comment {$this->clibcount}",
            'shared' => 0,
            'sortorder' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ], $data);

        $record->id = $DB->insert_record('local_unifiedgrader_clib', $record);
        return $record;
    }

    /**
     * Create a comment library v2 tag.
     *
     * @param array $data Required: userid. Optional: name, sortorder.
     * @return \stdClass The created record.
     */
    public function create_library_tag(array $data): \stdClass {
        global $DB;

        $this->tagcount++;
        $now = time();

        $record = (object) array_merge([
            'name' => "Tag {$this->tagcount}",
            'sortorder' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ], $data);

        $record->id = $DB->insert_record('local_unifiedgrader_cltag', $record);
        return $record;
    }

    /**
     * Create a comment-to-tag mapping.
     *
     * @param array $data Required: commentid, tagid.
     * @return \stdClass The created record.
     */
    public function create_tag_mapping(array $data): \stdClass {
        global $DB;

        $record = (object) $data;
        $record->id = $DB->insert_record('local_unifiedgrader_clmap', $record);
        return $record;
    }

    /**
     * Create a legacy comment library entry.
     *
     * @param array $data Required: userid. Optional: courseid, content, sortorder.
     * @return \stdClass The created record.
     */
    public function create_legacy_comment(array $data): \stdClass {
        global $DB;

        $now = time();

        $record = (object) array_merge([
            'courseid' => 0,
            'content' => 'Test legacy comment',
            'sortorder' => 0,
            'timecreated' => $now,
            'timemodified' => $now,
        ], $data);

        $record->id = $DB->insert_record('local_unifiedgrader_comments', $record);
        return $record;
    }

    /**
     * Create a grade penalty record.
     *
     * @param array $data Required: cmid, userid, authorid. Optional: category, label, percentage.
     * @return \stdClass The created record.
     */
    public function create_penalty(array $data): \stdClass {
        global $DB;

        $this->penaltycount++;
        $now = time();

        $record = (object) array_merge([
            'category' => 'wordcount',
            'label' => '',
            'percentage' => 10,
            'timecreated' => $now,
            'timemodified' => $now,
        ], $data);

        $record->id = $DB->insert_record('local_unifiedgrader_penalty', $record);
        return $record;
    }

    /**
     * Create a complete grading scenario with course, activity, teacher, and enrolled students.
     *
     * @param string $modname Activity type: 'assign', 'forum', or 'quiz'.
     * @param array $options Optional overrides:
     *   - int studentcount: Number of students (default 3).
     *   - array modparams: Extra params for create_module.
     *   - bool enable: Whether to enable this adapter type in plugin config (default true).
     * @return \stdClass Object with properties: course, cm, context, teacher, students (array), activity.
     */
    public function create_grading_scenario(string $modname, array $options = []): \stdClass {
        $gen = $this->datagenerator;

        $studentcount = $options['studentcount'] ?? 3;
        $modparams = $options['modparams'] ?? [];
        $enable = $options['enable'] ?? true;

        if ($enable) {
            set_config("enable_{$modname}", 1, 'local_unifiedgrader');
        }

        // Create course with unique shortname.
        $this->scenariocount++;
        $course = $gen->create_course([
            'shortname' => 'TEST101-2026-S1-' . $this->scenariocount,
            'fullname' => 'Test Course',
        ]);

        // Create activity.
        $defaults = ['course' => $course->id, 'name' => "Test {$modname}"];
        if ($modname === 'assign') {
            $defaults['duedate'] = time() + DAYSECS * 7;
            $defaults['submissiondrafts'] = 0;
            $defaults['assignsubmission_onlinetext_enabled'] = 1;
            $defaults['assignfeedback_comments_enabled'] = 1;
        } else if ($modname === 'forum') {
            $defaults['grade_forum'] = 100;
        } else if ($modname === 'quiz') {
            $defaults['timeclose'] = time() + DAYSECS * 7;
            $defaults['grade'] = 100;
        }
        $activity = $gen->create_module($modname, array_merge($defaults, $modparams));
        $cm = get_coursemodule_from_instance($modname, $activity->id, $course->id, false, MUST_EXIST);
        $cm = \cm_info::create($cm);
        $context = \context_module::instance($cm->id);

        // Create teacher and enrol.
        $sc = $this->scenariocount;
        $teacher = $gen->create_user(['username' => "teacher{$sc}"]);
        $gen->enrol_user($teacher->id, $course->id, 'editingteacher');

        // Create students and enrol.
        $students = [];
        for ($i = 1; $i <= $studentcount; $i++) {
            $student = $gen->create_user(['username' => "student{$sc}_{$i}"]);
            $gen->enrol_user($student->id, $course->id, 'student');
            $students[] = $student;
        }

        return (object) [
            'course' => $course,
            'activity' => $activity,
            'cm' => $cm,
            'context' => $context,
            'teacher' => $teacher,
            'students' => $students,
        ];
    }

    /**
     * Create an online text submission for an assignment.
     *
     * @param \stdClass $assign The assignment record.
     * @param int $userid The student user ID.
     * @param string $text The submission text.
     * @return \stdClass The submission record.
     */
    public function create_assign_submission(\stdClass $assign, int $userid, string $text = 'Test submission'): \stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $cm = get_coursemodule_from_instance('assign', $assign->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $assignobj = new \assign($context, $cm, null);

        // Set the user so the submission is created for the right student.
        $submission = $assignobj->get_user_submission($userid, true);

        // Add online text.
        global $DB;
        $onlinetext = $DB->get_record('assignsubmission_onlinetext', ['submission' => $submission->id]);
        if ($onlinetext) {
            $onlinetext->onlinetext = $text;
            $DB->update_record('assignsubmission_onlinetext', $onlinetext);
        } else {
            $DB->insert_record('assignsubmission_onlinetext', (object) [
                'assignment' => $assign->id,
                'submission' => $submission->id,
                'onlinetext' => $text,
                'onlineformat' => FORMAT_HTML,
            ]);
        }

        // Mark as submitted.
        $submission->status = ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        $submission->timemodified = time();
        $DB->update_record('assign_submission', $submission);

        return $submission;
    }

    /**
     * Create a forum discussion and post for a user.
     *
     * @param \stdClass $forum The forum record.
     * @param int $userid The user ID.
     * @param array $options Optional: subject, message.
     * @return \stdClass Object with: discussion, post.
     */
    public function create_forum_post(\stdClass $forum, int $userid, array $options = []): \stdClass {
        $forumgen = $this->datagenerator->get_plugin_generator('mod_forum');

        $discussion = $forumgen->create_discussion((object) [
            'forum' => $forum->id,
            'course' => $forum->course,
            'userid' => $userid,
            'name' => $options['subject'] ?? 'Test discussion',
            'message' => $options['message'] ?? '<p>Test forum post content.</p>',
        ]);

        global $DB;
        $post = $DB->get_record('forum_posts', ['discussion' => $discussion->id, 'userid' => $userid], '*', MUST_EXIST);

        return (object) [
            'discussion' => $discussion,
            'post' => $post,
        ];
    }
}
