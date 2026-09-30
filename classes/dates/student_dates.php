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
 * One student's dates in one activity: extensions and overrides.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\dates;

use local_unifiedgrader\adapter\adapter_factory;
use local_unifiedgrader\adapter\base_adapter;
use local_unifiedgrader\adapter\quiz_adapter;
use local_unifiedgrader\penalty\compat;
use local_unifiedgrader\penalty\service;
use local_unifiedgrader\penalty_manager;

/**
 * Loads, previews and saves a student's dates for the "Dates & extensions" dialogue.
 *
 * The dialogue shows every date setting with the class default beside the
 * student's own value. The due date is changed through an extension; the other
 * settings through a user override. Dates cross the web service boundary as
 * local date-time strings ("2026-08-17T23:59") in the teacher's Moodle time
 * zone, so the browser never has to convert time zones.
 *
 * Saving goes through the same activity APIs as before: assign_user_flags and
 * assign_overrides for assignments, the quiz override manager (and the
 * quizaccess_duedate rule before Moodle 5.3) for quizzes, and this plugin's
 * forum extensions.
 */
class student_dates {
    /** @var string A setting holding a date. */
    public const TYPE_DATETIME = 'datetime';

    /** @var string A setting holding a duration, edited in minutes. */
    public const TYPE_DURATION = 'duration';

    /** @var string The number of attempts allowed (0 = unlimited). */
    public const TYPE_ATTEMPTS = 'attempts';

    /** @var string Local date-time format used for the date inputs. */
    private const LOCAL_FORMAT = 'Y-m-d\TH:i';

    /** @var \cm_info The activity. */
    private \cm_info $cm;

    /** @var \context_module The activity's context. */
    private \context_module $context;

    /** @var \stdClass The course. */
    private \stdClass $course;

    /** @var base_adapter The activity's adapter. */
    private base_adapter $adapter;

    /** @var \stdClass The student. */
    private \stdClass $student;

    /**
     * Constructor.
     *
     * @param int $cmid Course module ID.
     * @param int $userid Student user ID.
     */
    public function __construct(int $cmid, int $userid) {
        [$this->course, $this->cm] = get_course_and_cm_from_cmid($cmid);
        if (!in_array($this->cm->modname, ['assign', 'quiz', 'forum'], true)) {
            throw new \moodle_exception('invalidmodule', 'local_unifiedgrader');
        }
        $this->context = \context_module::instance($this->cm->id);
        $this->adapter = adapter_factory::create($cmid);
        $this->student = \core_user::get_user($userid, '*', MUST_EXIST);
    }

    // Presets.

    /**
     * The extension presets, from the extensionpresets setting.
     *
     * @return array[] Each ['days' => int, 'label' => string], shortest first.
     */
    public static function get_presets(): array {
        $raw = (string) get_config('local_unifiedgrader', 'extensionpresets');
        if (trim($raw) === '') {
            $raw = '1, 2, 3, 7';
        }
        $days = [];
        foreach (explode(',', $raw) as $part) {
            $n = (int) trim($part);
            if ($n > 0 && $n <= 365) {
                $days[$n] = $n;
            }
        }
        ksort($days);

        $presets = [];
        foreach ($days as $n) {
            if ($n % 7 === 0) {
                $weeks = intdiv($n, 7);
                $label = $weeks === 1
                    ? get_string('dates_preset_week', 'local_unifiedgrader')
                    : get_string('dates_preset_weeks', 'local_unifiedgrader', $weeks);
            } else {
                $label = $n === 1
                    ? get_string('dates_preset_day', 'local_unifiedgrader')
                    : get_string('dates_preset_days', 'local_unifiedgrader', $n);
            }
            $presets[] = ['days' => $n, 'label' => $label];
        }
        return $presets;
    }

    // Loading.

    /**
     * Everything the dialogue shows.
     *
     * @return array See get_student_dates::execute_returns().
     */
    public function load(): array {
        global $PAGE;

        $picture = new \user_picture($this->student);
        $picture->size = 64;

        $state = $this->current_state();
        $extension = $this->extension_info($state);

        return [
            'cmid' => (int) $this->cm->id,
            'userid' => (int) $this->student->id,
            'fullname' => fullname($this->student),
            'profileimageurl' => $picture->get_url($PAGE)->out(false),
            'activityname' => format_string($this->cm->name, true, ['context' => $this->context, 'escape' => false]),
            'activitytype' => $this->cm->modname,
            'extension' => $extension,
            'settings' => $this->settings_rows($state),
            'canremoveall' => $this->can_manage_overrides() || $extension['editable'],
            'preview' => $this->preview($extension['value']),
        ];
    }

    /**
     * The class defaults and the student's current values, in timestamps and numbers.
     *
     * @return array ['class' => [key => int], 'student' => [key => int|null], 'extension' => int,
     *               'extensionavailable' => bool, 'overrideid' => int, 'order' => string[]]
     */
    private function current_state(): array {
        global $DB;

        $state = ['class' => [], 'student' => [], 'extension' => 0, 'extensionavailable' => true,
            'overrideid' => 0, 'order' => [], 'extra' => []];

        if ($this->cm->modname === 'assign') {
            $instance = $DB->get_record('assign', ['id' => $this->cm->instance], '*', MUST_EXIST);
            $state['order'] = ['allowsubmissionsfromdate', 'duedate', 'cutoffdate'];
            if (get_config('assign', 'enabletimelimit')) {
                $state['order'][] = 'timelimit';
            }
            foreach (['allowsubmissionsfromdate', 'duedate', 'cutoffdate', 'timelimit'] as $key) {
                $state['class'][$key] = (int) ($instance->{$key} ?? 0);
            }
            $override = $this->adapter->get_user_override((int) $this->student->id);
            if ($override) {
                $state['overrideid'] = (int) $override['id'];
                foreach (['allowsubmissionsfromdate', 'cutoffdate', 'timelimit'] as $key) {
                    $state['student'][$key] = $override[$key];
                }
                // A due date override made on Moodle's own page is kept, and becomes
                // the date the extension is measured from.
                if ($override['duedate'] !== null) {
                    $state['class']['duedate'] = (int) $override['duedate'];
                    $state['extra']['overrideduedate'] = (int) $override['duedate'];
                }
            }
            $flags = $DB->get_record('assign_user_flags', [
                'assignment' => $instance->id,
                'userid' => $this->student->id,
            ]);
            $state['extension'] = $flags ? (int) $flags->extensionduedate : 0;
        } else if ($this->cm->modname === 'quiz') {
            $quiz = $DB->get_record('quiz', ['id' => $this->cm->instance], '*', MUST_EXIST);
            $state['order'] = ['timeopen', 'duedate', 'timeclose', 'timelimit', 'attempts'];
            foreach (['timeopen', 'timeclose', 'timelimit', 'attempts'] as $key) {
                $state['class'][$key] = (int) $quiz->{$key};
            }
            if (compat::unified()) {
                $state['class']['duedate'] = (int) $quiz->duedate;
            } else if (compat::use_quizaccess_duedate()) {
                $settings = $DB->get_record('quizaccess_duedate_instances', ['quizid' => $quiz->id]);
                $state['class']['duedate'] = $settings ? (int) $settings->duedate : 0;
            } else {
                $state['class']['duedate'] = 0;
                $state['extensionavailable'] = false;
            }
            $override = $this->adapter->get_user_override((int) $this->student->id);
            if ($override) {
                $state['overrideid'] = (int) $override['id'];
                foreach (['timeopen', 'timeclose', 'timelimit', 'attempts'] as $key) {
                    $state['student'][$key] = $override[$key];
                }
            }
            if ($state['extensionavailable']) {
                $ext = $this->adapter->get_duedate_extension((int) $this->student->id);
                $state['extension'] = $ext ? (int) $ext['duedate'] : 0;
            }
        } else {
            $forum = $DB->get_record('forum', ['id' => $this->cm->instance], '*', MUST_EXIST);
            $state['order'] = ['duedate', 'cutoffdate'];
            $state['class']['duedate'] = (int) ($forum->duedate ?? 0);
            $state['class']['cutoffdate'] = (int) ($forum->cutoffdate ?? 0);
            $ext = $this->adapter->get_forum_extension((int) $this->student->id);
            $state['extension'] = $ext ? (int) $ext['extensionduedate'] : 0;
        }

        return $state;
    }

    /**
     * The extension block of the dialogue.
     *
     * @param array $state From current_state().
     * @return array
     */
    private function extension_info(array $state): array {
        $classdue = (int) ($state['class']['duedate'] ?? 0);
        $editable = $state['extensionavailable'] && $classdue > 0 && $this->can_extend();

        $reason = '';
        if (!$state['extensionavailable']) {
            $reason = get_string('dates_extension_unavailable', 'local_unifiedgrader');
        } else if ($classdue <= 0) {
            $reason = get_string('dates_noduedate', 'local_unifiedgrader');
        }

        return [
            'available' => $state['extensionavailable'] && $classdue > 0,
            'editable' => $editable,
            'reason' => $reason,
            'classvalue' => $classdue > 0 ? self::to_local($classdue) : '',
            'classtext' => self::format_setting(self::TYPE_DATETIME, $classdue),
            'value' => $state['extension'] > 0 ? self::to_local($state['extension']) : '',
            'presets' => self::get_presets(),
        ];
    }

    /**
     * The rows of the settings table.
     *
     * @param array $state From current_state().
     * @return array[]
     */
    private function settings_rows(array $state): array {
        $canoverride = $this->can_manage_overrides();
        $rows = [];
        foreach ($state['order'] as $key) {
            $type = self::setting_type($key);
            $classvalue = (int) ($state['class'][$key] ?? 0);
            $row = [
                'key' => $key,
                'label' => get_string('dates_label_' . $key, 'local_unifiedgrader'),
                'type' => $type,
                'classtext' => self::format_setting($type, $classvalue),
                'classvalue' => self::to_input($type, $classvalue),
                'isextension' => $key === 'duedate',
                'overridden' => false,
                'value' => '',
                'valuetext' => '',
                'editable' => false,
                'follows' => false,
                'note' => '',
            ];

            if ($key === 'duedate') {
                $row['overridden'] = $state['extension'] > 0;
                $row['value'] = $state['extension'] > 0 ? self::to_local($state['extension']) : '';
            } else if ($this->cm->modname === 'forum' && $key === 'cutoffdate') {
                // A forum's cut-off cannot be set per student.
                $row['note'] = $classvalue > 0 ? get_string('dates_forum_cutoff_note', 'local_unifiedgrader') : '';
            } else {
                $current = $state['student'][$key] ?? null;
                $row['overridden'] = $current !== null;
                $row['value'] = $current !== null ? self::to_input($type, (int) $current) : '';
                $row['valuetext'] = $current !== null ? self::format_setting($type, (int) $current) : '';
                $row['editable'] = $canoverride;
                $row['follows'] = in_array($key, ['cutoffdate', 'timeclose'], true);
            }
            $rows[] = $row;
        }
        return $rows;
    }

    // Preview.

    /**
     * What a given extension would mean for the student's lateness and penalty.
     *
     * @param string $extension Local date-time of the extension, or '' for none.
     * @return array ['tone' => 'success'|'warning'|'neutral'|'none', 'title', 'body', 'extensiontext', 'moves' => [key => text]]
     */
    public function preview(string $extension): array {
        $state = $this->current_state();
        $userid = (int) $this->student->id;
        $ext = $extension !== '' ? self::from_local($extension) : 0;
        $classdue = (int) ($state['class']['duedate'] ?? 0);
        $effective = $ext > 0 ? $ext : $classdue;

        $result = [
            'tone' => 'none', 'title' => '', 'body' => '',
            'extensiontext' => $ext > 0 ? self::format_setting(self::TYPE_DATETIME, $ext) : '',
            'moves' => [],
        ];

        // Settings that move with an extension past them.
        if ($ext > 0) {
            foreach (['cutoffdate', 'timeclose'] as $key) {
                if (!array_key_exists($key, $state['class']) || $this->cm->modname === 'forum') {
                    continue;
                }
                $current = $state['student'][$key] ?? null;
                $limit = $current !== null ? (int) $current : (int) $state['class'][$key];
                if ($limit > 0 && $ext > $limit) {
                    $result['moves'][] = ['key' => $key, 'text' => self::format_setting(self::TYPE_DATETIME, $ext)];
                }
            }
        }

        if ($effective <= 0) {
            return $result;
        }

        $submitted = $this->adapter->get_late_reference_time($userid);
        if ($submitted <= 0) {
            $result['tone'] = 'neutral';
            $result['title'] = get_string('dates_status_nosubmission_title', 'local_unifiedgrader');
            $result['body'] = get_string('dates_status_nosubmission_body', 'local_unifiedgrader');
            return $this->with_forum_cutoff_warning($result, $state, $ext);
        }

        // Whether this plugin knows the penalty: always for forums, and for
        // every type once it owns late penalties (Moodle 5.3).
        $knowspenalty = compat::unified() || $this->cm->modname === 'forum';
        $classpct = $knowspenalty ? (int) ($this->adapter->calculate_late_penalty($userid, $classdue)['percentage'] ?? 0) : 0;
        $newpct = $knowspenalty ? (int) ($this->adapter->calculate_late_penalty($userid, $effective)['percentage'] ?? 0) : 0;
        $time = self::format_setting(self::TYPE_DATETIME, $submitted);
        $lateagainstclass = $classdue > 0 && $submitted > $classdue;

        if ($submitted <= $effective) {
            $result['tone'] = 'success';
            if ($ext > 0 && $lateagainstclass) {
                $result['title'] = get_string('dates_status_extended_ontime_title', 'local_unifiedgrader');
                $result['body'] = $classpct > 0
                    ? get_string('dates_status_extended_ontime_body', 'local_unifiedgrader', ['time' => $time, 'pct' => $classpct])
                    : get_string('dates_status_extended_ontime_body_nopenalty', 'local_unifiedgrader', $time);
            } else {
                $result['title'] = get_string('dates_status_ontime_title', 'local_unifiedgrader');
                $result['body'] = get_string('dates_status_ontime_body', 'local_unifiedgrader', $time);
            }
        } else {
            $result['tone'] = 'warning';
            $days = (int) ceil(($submitted - $effective) / DAYSECS);
            $a = ['time' => $time, 'days' => $days, 'pct' => $newpct, 'was' => $classpct];
            if ($ext > 0 && $knowspenalty && $classpct > 0 && $newpct > 0 && $newpct !== $classpct) {
                $result['title'] = get_string('dates_status_extended_late_title', 'local_unifiedgrader', $newpct);
                $result['body'] = get_string('dates_status_extended_late_body', 'local_unifiedgrader', $a);
            } else if ($knowspenalty && $newpct > 0) {
                $result['title'] = get_string('dates_status_late_title', 'local_unifiedgrader', $newpct);
                $result['body'] = get_string('dates_status_late_body', 'local_unifiedgrader', $a);
            } else if ($knowspenalty) {
                $result['title'] = get_string('dates_status_late_nopenalty_title', 'local_unifiedgrader');
                $result['body'] = get_string('dates_status_late_nopenalty_body', 'local_unifiedgrader', $a);
            } else {
                $result['title'] = get_string('dates_status_late_nopenalty_title', 'local_unifiedgrader');
                $result['body'] = get_string('dates_status_late_body', 'local_unifiedgrader', $a);
            }
        }

        return $this->with_forum_cutoff_warning($result, $state, $ext);
    }

    /**
     * Warn when a forum extension passes the forum's cut-off, which it cannot move.
     *
     * @param array $result The preview so far.
     * @param array $state From current_state().
     * @param int $ext The extension, or 0.
     * @return array
     */
    private function with_forum_cutoff_warning(array $result, array $state, int $ext): array {
        $cutoff = (int) ($state['class']['cutoffdate'] ?? 0);
        if ($this->cm->modname === 'forum' && $ext > 0 && $cutoff > 0 && $ext > $cutoff) {
            $warning = get_string(
                'dates_forum_cutoff_warning',
                'local_unifiedgrader',
                self::format_setting(self::TYPE_DATETIME, $cutoff),
            );
            $result['body'] = trim($result['body'] . ' ' . $warning);
            if ($result['tone'] === 'none') {
                $result['tone'] = 'warning';
                $result['title'] = get_string('dates_forum_cutoff_title', 'local_unifiedgrader');
                $result['body'] = $warning;
            }
        }
        return $result;
    }

    // Saving.

    /**
     * Save the student's extension and overrides.
     *
     * @param string $extension Local date-time of the extension, or '' for none.
     * @param array $overrides [key => value] for each overridden setting; a setting
     *        left out goes back to the class default. Dates as local date-times,
     *        durations in minutes, attempts as a number.
     * @return array ['askrecalc' => bool] True when core still penalises this
     *         assignment and the teacher should be offered a recalculation.
     */
    public function save(string $extension, array $overrides): array {
        $state = $this->current_state();
        $ext = $extension !== '' ? self::from_local($extension) : 0;

        $extchanged = $ext !== (int) $state['extension'];
        if ($extchanged) {
            if (!$this->can_extend() || !$state['extensionavailable']) {
                throw new \required_capability_exception($this->context, $this->extension_capability(), 'nopermissions', '');
            }
            $classdue = (int) ($state['class']['duedate'] ?? 0);
            if ($ext > 0 && $ext <= $classdue) {
                throw new \moodle_exception('dates_error_extension_before_due', 'local_unifiedgrader');
            }
        }

        // Only the settings this activity offers, converted to stored values.
        $values = [];
        foreach ($state['order'] as $key) {
            if ($key === 'duedate' || ($this->cm->modname === 'forum')) {
                continue;
            }
            if (array_key_exists($key, $overrides)) {
                $values[$key] = self::from_input(self::setting_type($key), (string) $overrides[$key]);
            } else {
                $values[$key] = null;
            }
        }
        $overrideschanged = false;
        foreach ($values as $key => $value) {
            $current = $state['student'][$key] ?? null;
            if ($value !== ($current === null ? null : (int) $current)) {
                $overrideschanged = true;
            }
        }
        if ($overrideschanged && !$this->can_manage_overrides()) {
            throw new \required_capability_exception(
                $this->context,
                'mod/' . $this->cm->modname . ':manageoverrides',
                'nopermissions',
                '',
            );
        }

        // Settings that must move with the extension: close and cut-off dates.
        foreach (['cutoffdate', 'timeclose'] as $key) {
            if (!array_key_exists($key, $values) || $ext <= 0) {
                continue;
            }
            $limit = $values[$key] ?? (int) $state['class'][$key];
            if ($limit > 0 && $ext > $limit) {
                $values[$key] = $ext;
                $overrideschanged = true;
            }
        }

        $askrecalc = false;
        if ($this->cm->modname === 'assign') {
            $askrecalc = $this->save_assign($state, $ext, $extchanged, $values, $overrideschanged);
        } else if ($this->cm->modname === 'quiz') {
            $this->save_quiz($state, $ext, $extchanged, $values, $overrideschanged);
        } else if ($extchanged) {
            $this->save_forum($ext);
        }

        return ['askrecalc' => $askrecalc];
    }

    /**
     * Save an assignment's extension and override.
     *
     * @param array $state From current_state().
     * @param int $ext The extension, or 0.
     * @param bool $extchanged Whether the extension changed.
     * @param array $values [key => int|null] override values.
     * @param bool $overrideschanged Whether any override value changed.
     * @return bool Whether to offer the teacher a penalty recalculation.
     */
    private function save_assign(array $state, int $ext, bool $extchanged, array $values, bool $overrideschanged): bool {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $userid = (int) $this->student->id;
        $assign = new \assign($this->context, $this->cm, $this->course);
        $instance = $assign->get_instance();

        if ($extchanged && !$assign->save_user_extension($userid, $ext)) {
            throw new \moodle_exception('extensionnotafterduedate', 'assign');
        }

        if ($overrideschanged) {
            // A setting the dialogue does not show (a time limit, when the site has
            // them switched off) keeps whatever the override already holds.
            $value = fn(string $key) => array_key_exists($key, $values) ? $values[$key] : ($state['student'][$key] ?? null);
            $record = (object) [
                'assignid' => $instance->id,
                'userid' => $userid,
                // A due date override made on Moodle's own page is kept.
                'duedate' => $state['extra']['overrideduedate'] ?? null,
                'allowsubmissionsfromdate' => $value('allowsubmissionsfromdate'),
                'cutoffdate' => $value('cutoffdate'),
                'timelimit' => $value('timelimit'),
            ];
            $hasany = $record->duedate !== null || $record->allowsubmissionsfromdate !== null
                || $record->cutoffdate !== null || $record->timelimit !== null;
            $overrideid = (int) $state['overrideid'];
            $params = ['context' => $this->context, 'relateduserid' => $userid, 'other' => ['assignid' => $instance->id]];

            if ($hasany && $overrideid) {
                $record->id = $overrideid;
                $DB->update_record('assign_overrides', $record);
                \mod_assign\event\user_override_updated::create($params + ['objectid' => $overrideid])->trigger();
            } else if ($hasany) {
                $record->id = $DB->insert_record('assign_overrides', $record);
                \mod_assign\event\user_override_created::create($params + ['objectid' => $record->id])->trigger();
            } else if ($overrideid) {
                // Moodle 5.3 moved this to an override manager (MDL-86513).
                if (class_exists('\mod_assign\override_manager')) {
                    (new \mod_assign\override_manager($instance, $this->context))->delete_overrides_by_id([$overrideid]);
                } else {
                    $assign->delete_override($overrideid);
                }
            }
            \cache::make('mod_assign', 'overrides')->delete("{$instance->id}_u_{$userid}");
            if ($hasany) {
                assign_update_events($assign, $record);
            }
        }

        if (compat::unified() && !$this->core_assign_penalty_active($instance)) {
            service::resync((int) $this->cm->id, $userid);
            return false;
        }

        // Core still penalises this assignment: keep its penalty field current and
        // offer the full recalculation the grader has always offered.
        if (
            class_exists('\mod_assign\penalty\helper')
                && \mod_assign\penalty\helper::is_penalty_enabled((int) $instance->id)
        ) {
            \mod_assign\penalty\helper::apply_penalty_to_user((int) $instance->id, $userid);
            return $extchanged && $DB->record_exists_select(
                'assign_grades',
                'assignment = ? AND userid = ? AND grade >= 0',
                [$instance->id, $userid],
            );
        }
        return false;
    }

    /**
     * Save a quiz's extension and override.
     *
     * @param array $state From current_state().
     * @param int $ext The extension, or 0.
     * @param bool $extchanged Whether the extension changed.
     * @param array $values [key => int|null] override values.
     * @param bool $overrideschanged Whether any override value changed.
     */
    private function save_quiz(array $state, int $ext, bool $extchanged, array $values, bool $overrideschanged): void {
        global $DB;

        $userid = (int) $this->student->id;
        $unified = compat::unified();
        /** @var quiz_adapter $adapter */
        $adapter = $this->adapter;

        // Before 5.3 the extension lives in the quizaccess_duedate rule.
        if (!$unified && $extchanged) {
            if ($ext > 0) {
                $adapter->save_duedate_extension($userid, $ext);
            } else {
                $adapter->delete_duedate_extension($userid);
            }
        }

        if (!$overrideschanged && !($unified && $extchanged)) {
            return;
        }

        $data = ['quiz' => (int) $this->cm->instance, 'userid' => $userid];
        if ($state['overrideid']) {
            $data = (array) $DB->get_record('quiz_overrides', ['id' => $state['overrideid']], '*', MUST_EXIST);
        }
        foreach ($values as $key => $value) {
            $data[$key] = $value;
        }
        if ($unified) {
            $data['duedate'] = $ext > 0 ? $ext : null;
        }

        $quizobj = \mod_quiz\quiz_settings::create_for_cmid((int) $this->cm->id);
        $manager = $quizobj->get_override_manager();
        $settings = ['timeopen', 'timeclose', 'duedate', 'timelimit', 'attempts', 'password'];
        $remaining = array_filter(
            $manager->parse_formdata($data),
            fn($value, $key) => in_array($key, $settings, true) && $value !== null,
            ARRAY_FILTER_USE_BOTH,
        );
        if ($remaining) {
            $manager->save_override($data);
        } else if ($state['overrideid']) {
            $manager->delete_overrides_by_id([(int) $state['overrideid']]);
        }
        quiz_adapter::refresh_duedate_calendar_events((int) $this->cm->instance);
        service::resync((int) $this->cm->id, $userid);
    }

    /**
     * Save a forum extension and resync the student's penalties.
     *
     * @param int $ext The extension, or 0.
     */
    private function save_forum(int $ext): void {
        $userid = (int) $this->student->id;
        if ($ext > 0) {
            $this->adapter->save_forum_extension($userid, $ext);
        } else {
            $this->adapter->delete_forum_extension($userid);
        }

        if (compat::unified()) {
            service::resync((int) $this->cm->id, $userid);
            return;
        }
        $lateinfo = $this->adapter->calculate_late_penalty($userid);
        penalty_manager::sync_late_penalty(
            (int) $this->cm->id,
            $userid,
            $lateinfo['percentage'] ?? null,
            $lateinfo['dayslate'] ?? 0,
        );
        $this->adapter->sync_gradebook_penalty($userid);
    }

    // Capabilities.

    /**
     * The capability that lets a teacher grant an extension here.
     *
     * @return string
     */
    private function extension_capability(): string {
        if ($this->cm->modname === 'assign') {
            return 'mod/assign:grantextension';
        }
        if ($this->cm->modname === 'quiz') {
            return compat::unified() ? 'mod/quiz:manageoverrides' : 'quizaccess/duedate:manageoverrides';
        }
        return 'local/unifiedgrader:grade';
    }

    /**
     * Whether the teacher may grant this student an extension.
     *
     * @return bool
     */
    private function can_extend(): bool {
        if ($this->cm->modname === 'quiz' && !compat::unified() && !compat::use_quizaccess_duedate()) {
            return false;
        }
        return has_capability($this->extension_capability(), $this->context);
    }

    /**
     * Whether the teacher may override this student's other settings.
     *
     * @return bool
     */
    private function can_manage_overrides(): bool {
        if ($this->cm->modname === 'forum') {
            return false;
        }
        return has_capability('mod/' . $this->cm->modname . ':manageoverrides', $this->context);
    }

    /**
     * Whether core still applies its own late penalty to this assignment.
     *
     * @param \stdClass $instance The assign record.
     * @return bool
     */
    private function core_assign_penalty_active(\stdClass $instance): bool {
        return !empty($instance->gradepenalty) && !empty($instance->duedate)
            && (float) $instance->grade >= GRADE_TYPE_VALUE
            && \core_grades\penalty_manager::is_penalty_enabled_for_module('assign');
    }

    // Values.

    /**
     * The kind of value a setting holds.
     *
     * @param string $key Setting key.
     * @return string A TYPE_ constant.
     */
    private static function setting_type(string $key): string {
        if ($key === 'timelimit') {
            return self::TYPE_DURATION;
        }
        if ($key === 'attempts') {
            return self::TYPE_ATTEMPTS;
        }
        return self::TYPE_DATETIME;
    }

    /**
     * A setting's value as the teacher reads it.
     *
     * @param string $type A TYPE_ constant.
     * @param int $value The stored value.
     * @return string
     */
    public static function format_setting(string $type, int $value): string {
        if ($type === self::TYPE_ATTEMPTS) {
            return $value > 0 ? (string) $value : get_string('unlimited');
        }
        if ($value <= 0) {
            return get_string('none');
        }
        if ($type === self::TYPE_DURATION) {
            return format_time($value);
        }
        return userdate($value, get_string('dates_strftime', 'local_unifiedgrader'));
    }

    /**
     * A stored value as a form input value.
     *
     * @param string $type A TYPE_ constant.
     * @param int $value The stored value.
     * @return string
     */
    private static function to_input(string $type, int $value): string {
        if ($type === self::TYPE_DATETIME) {
            return $value > 0 ? self::to_local($value) : '';
        }
        if ($type === self::TYPE_DURATION) {
            return (string) intdiv(max(0, $value), MINSECS);
        }
        return (string) max(0, $value);
    }

    /**
     * A form input value as a stored value.
     *
     * @param string $type A TYPE_ constant.
     * @param string $value The input value.
     * @return int
     */
    private static function from_input(string $type, string $value): int {
        if ($type === self::TYPE_DATETIME) {
            return self::from_local($value);
        }
        $number = clean_param($value, PARAM_INT);
        if ($number < 0) {
            throw new \invalid_parameter_exception('Negative value');
        }
        return $type === self::TYPE_DURATION ? $number * MINSECS : $number;
    }

    /**
     * A timestamp as a local date-time in the teacher's time zone.
     *
     * @param int $timestamp Unix time.
     * @return string "YYYY-MM-DDTHH:MM"
     */
    public static function to_local(int $timestamp): string {
        $date = new \DateTime('@' . $timestamp);
        $date->setTimezone(\core_date::get_user_timezone_object());
        return $date->format(self::LOCAL_FORMAT);
    }

    /**
     * A local date-time in the teacher's time zone as a timestamp.
     *
     * @param string $local "YYYY-MM-DDTHH:MM"
     * @return int Unix time.
     */
    public static function from_local(string $local): int {
        $date = \DateTime::createFromFormat(self::LOCAL_FORMAT, $local, \core_date::get_user_timezone_object());
        $errors = \DateTime::getLastErrors();
        if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count']))) {
            throw new \invalid_parameter_exception('Invalid date: ' . $local);
        }
        return $date->getTimestamp();
    }
}
