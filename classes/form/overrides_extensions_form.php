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
 * Unified overrides and extensions form for the unified grader.
 *
 * A single form that dynamically renders override and extension fields
 * based on the activity type (assign, quiz, or forum).
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Unified overrides and extensions form.
 */
class overrides_extensions_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $activitytype = $this->_customdata['activitytype'];
        $defaults = $this->_customdata['defaults'];
        $overrides = $this->_customdata['overrides'];

        // Hidden fields.
        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);

        // Extension section (first, most frequently used).
        $mform->addElement('header', 'extensionhdr',
            get_string('overrides_section_extension', 'local_unifiedgrader'));

        if ($activitytype === 'assign') {
            $this->add_assign_extension($mform, $defaults, $overrides);
        } else if ($activitytype === 'quiz') {
            $this->add_quiz_extension($mform, $defaults, $overrides);
        } else if ($activitytype === 'forum') {
            $this->add_forum_extension($mform, $defaults, $overrides);
        }

        // Activity defaults (read-only reference).
        $mform->addElement('header', 'defaultshdr',
            get_string('overrides_section_defaults', 'local_unifiedgrader'));

        $this->add_defaults_section($mform, $activitytype, $defaults);

        // Overrides section (assign and quiz only).
        if ($activitytype === 'assign' || $activitytype === 'quiz') {
            $mform->addElement('header', 'overrideshdr',
                get_string('overrides_section_overrides_only', 'local_unifiedgrader'));

            if ($activitytype === 'assign') {
                $this->add_assign_overrides($mform, $defaults, $overrides);
            } else {
                $this->add_quiz_overrides($mform, $defaults, $overrides);
            }
        }

        $this->add_action_buttons(true, get_string('save'));
    }

    // Extension fields (per activity type).

    /**
     * Add assignment extension fields.
     *
     * @param \MoodleQuickForm $mform
     * @param array $defaults
     * @param array $overrides
     */
    private function add_assign_extension($mform, $defaults, $overrides) {
        // Show current due date for reference.
        $this->add_static_date($mform, 'ext_ref_duedate',
            get_string('duedate', 'assign'), $defaults['duedate'] ?? 0);

        // Extension date picker.
        $this->add_override_date($mform, 'extensionduedate',
            get_string('extensionduedate', 'assign'),
            $overrides['extensionduedate'] ?? 0, $defaults['duedate'] ?? 0);

        // Auto-adjust notice for cutoff.
        $cutoff = $defaults['cutoffdate'] ?? 0;
        if ($cutoff > 0) {
            $mform->addElement('static', 'cutoff_auto_notice', '',
                '<div class="alert alert-info small mb-0">'
                . get_string('extension_cutoff_auto_adjust', 'local_unifiedgrader',
                    userdate($cutoff))
                . '</div>');
        }
    }

    /**
     * Add quiz extension fields.
     *
     * @param \MoodleQuickForm $mform
     * @param array $defaults
     * @param array $overrides
     */
    private function add_quiz_extension($mform, $defaults, $overrides) {
        if (empty($defaults['hasduedateplugin'])) {
            $mform->addElement('static', 'no_duedate_plugin', '',
                '<div class="alert alert-warning small mb-0">'
                . get_string('quiz_extension_plugin_missing', 'local_unifiedgrader')
                . '</div>');
            return;
        }

        // Show current due date for reference.
        $this->add_static_date($mform, 'ext_ref_duedate',
            get_string('duedate', 'assign'), $defaults['duedate'] ?? 0);

        $this->add_override_date($mform, 'extensionduedate',
            get_string('overrides_ext_duedate', 'local_unifiedgrader'),
            $overrides['extensionduedate'] ?? 0, $defaults['duedate'] ?? 0);

        // Auto-adjust notice for timeclose.
        $timeclose = $defaults['timeclose'] ?? 0;
        if ($timeclose > 0) {
            $mform->addElement('static', 'close_auto_notice', '',
                '<div class="alert alert-info small mb-0">'
                . get_string('extension_close_auto_adjust', 'local_unifiedgrader',
                    userdate($timeclose))
                . '</div>');
        }
    }

    /**
     * Add forum extension fields.
     *
     * @param \MoodleQuickForm $mform
     * @param array $defaults
     * @param array $overrides
     */
    private function add_forum_extension($mform, $defaults, $overrides) {
        // Show current due date for reference.
        $this->add_static_date($mform, 'ext_ref_duedate',
            get_string('duedate', 'forum'), $defaults['duedate'] ?? 0);

        $this->add_override_date($mform, 'extensionduedate',
            get_string('overrides_ext_duedate', 'local_unifiedgrader'),
            $overrides['extensionduedate'] ?? 0, $defaults['duedate'] ?? 0);

        // Cutoff warning for forums (can't auto-adjust — no override table).
        $cutoff = $defaults['cutoffdate'] ?? 0;
        if ($cutoff > 0) {
            $mform->addElement('static', 'cutoff_warning', '',
                '<div class="alert alert-warning small mb-0">'
                . get_string('extension_cutoff_forum_warning', 'local_unifiedgrader',
                    userdate($cutoff))
                . '</div>');
        }
    }

    // Activity defaults (read-only).

    /**
     * Add the read-only defaults section.
     *
     * @param \MoodleQuickForm $mform
     * @param string $activitytype
     * @param array $defaults
     */
    private function add_defaults_section($mform, $activitytype, $defaults) {
        if ($activitytype === 'assign') {
            $this->add_static_date($mform, 'default_duedate',
                get_string('duedate', 'assign'), $defaults['duedate'] ?? 0);
            $this->add_static_date($mform, 'default_cutoffdate',
                get_string('cutoffdate', 'assign'), $defaults['cutoffdate'] ?? 0);
            $this->add_static_date($mform, 'default_allowsubmissionsfromdate',
                get_string('allowsubmissionsfromdate', 'assign'), $defaults['allowsubmissionsfromdate'] ?? 0);
            if (!empty($defaults['timelimit'])) {
                $mform->addElement('static', 'default_timelimit',
                    get_string('timelimit', 'assign'), format_time($defaults['timelimit']));
            }
        } else if ($activitytype === 'quiz') {
            $this->add_static_date($mform, 'default_timeopen',
                get_string('quizopen', 'quiz'), $defaults['timeopen'] ?? 0);
            $this->add_static_date($mform, 'default_timeclose',
                get_string('quizclose', 'quiz'), $defaults['timeclose'] ?? 0);
            if (!empty($defaults['timelimit'])) {
                $mform->addElement('static', 'default_timelimit',
                    get_string('timelimit', 'quiz'), format_time($defaults['timelimit']));
            }
            $mform->addElement('static', 'default_attempts',
                get_string('attemptsallowed', 'quiz'),
                ($defaults['attempts'] ?? 0) == 0
                    ? get_string('unlimited')
                    : $defaults['attempts']);
            if (!empty($defaults['hasduedateplugin'])) {
                $this->add_static_date($mform, 'default_duedate',
                    get_string('duedate', 'assign'), $defaults['duedate'] ?? 0);
            }
        } else if ($activitytype === 'forum') {
            $this->add_static_date($mform, 'default_duedate',
                get_string('duedate', 'forum'), $defaults['duedate'] ?? 0);
            $this->add_static_date($mform, 'default_cutoffdate',
                get_string('cutoffdate', 'forum'), $defaults['cutoffdate'] ?? 0);
        }
    }

    // Override fields (assign and quiz only).

    /**
     * Add assignment override fields (no duedate — extension handles that).
     *
     * @param \MoodleQuickForm $mform
     * @param array $defaults
     * @param array $overrides
     */
    private function add_assign_overrides($mform, $defaults, $overrides) {
        $this->add_override_date($mform, 'cutoffdate',
            get_string('cutoffdate', 'assign'),
            $overrides['cutoffdate'] ?? 0, $defaults['cutoffdate'] ?? 0);

        $this->add_override_date($mform, 'allowsubmissionsfromdate',
            get_string('allowsubmissionsfromdate', 'assign'),
            $overrides['allowsubmissionsfromdate'] ?? 0,
            $defaults['allowsubmissionsfromdate'] ?? 0);

        // Time limit override (Moodle 5.0 timed assignments).
        if (array_key_exists('timelimit', $defaults)) {
            $mform->addElement('advcheckbox', 'override_timelimit',
                '', get_string('override_enable', 'local_unifiedgrader'));
            $mform->addElement('duration', 'timelimit', get_string('timelimit', 'assign'));
            $mform->disabledIf('timelimit', 'override_timelimit');

            if (!empty($overrides['timelimit'])) {
                $mform->setDefault('override_timelimit', 1);
                $mform->setDefault('timelimit', $overrides['timelimit']);
            }
        }
    }

    /**
     * Add quiz override fields.
     *
     * @param \MoodleQuickForm $mform
     * @param array $defaults
     * @param array $overrides
     */
    private function add_quiz_overrides($mform, $defaults, $overrides) {
        $this->add_override_date($mform, 'timeopen',
            get_string('quizopen', 'quiz'),
            $overrides['timeopen'] ?? 0, $defaults['timeopen'] ?? 0);

        $this->add_override_date($mform, 'timeclose',
            get_string('quizclose', 'quiz'),
            $overrides['timeclose'] ?? 0, $defaults['timeclose'] ?? 0);

        // Time limit.
        $mform->addElement('advcheckbox', 'override_timelimit',
            '', get_string('override_enable', 'local_unifiedgrader'));
        $mform->addElement('duration', 'timelimit', get_string('timelimit', 'quiz'));
        $mform->disabledIf('timelimit', 'override_timelimit');

        if (!empty($overrides['timelimit'])) {
            $mform->setDefault('override_timelimit', 1);
            $mform->setDefault('timelimit', $overrides['timelimit']);
        }

        // Attempts.
        $attemptoptions = [0 => get_string('unlimited')];
        for ($i = 1; $i <= 10; $i++) {
            $attemptoptions[$i] = $i;
        }
        $mform->addElement('advcheckbox', 'override_attempts',
            '', get_string('override_enable', 'local_unifiedgrader'));
        $mform->addElement('select', 'attempts', get_string('attemptsallowed', 'quiz'),
            $attemptoptions);
        $mform->disabledIf('attempts', 'override_attempts');

        if (isset($overrides['attempts']) && $overrides['attempts'] !== null) {
            $mform->setDefault('override_attempts', 1);
            $mform->setDefault('attempts', $overrides['attempts']);
        }
    }

    // Shared helpers.

    /**
     * Add a static date field.
     *
     * @param \MoodleQuickForm $mform
     * @param string $name
     * @param string $label
     * @param int $timestamp
     */
    private function add_static_date($mform, $name, $label, $timestamp) {
        $display = $timestamp > 0 ? userdate($timestamp) : get_string('none');
        $mform->addElement('static', $name, $label, $display);
    }

    /**
     * Add an overrideable date field with enable checkbox.
     *
     * @param \MoodleQuickForm $mform
     * @param string $name Field name.
     * @param string $label Field label.
     * @param int $currentvalue Current override value (0 = not set).
     * @param int $defaultvalue Default activity value.
     */
    private function add_override_date($mform, $name, $label, $currentvalue, $defaultvalue) {
        $checkboxname = 'override_' . $name;
        $mform->addElement('advcheckbox', $checkboxname,
            '', get_string('override_enable', 'local_unifiedgrader'));
        $mform->addElement('date_time_selector', $name, $label);
        $mform->disabledIf($name, $checkboxname);

        if (!empty($currentvalue)) {
            $mform->setDefault($checkboxname, 1);
            $mform->setDefault($name, $currentvalue);
        } else if (!empty($defaultvalue)) {
            $mform->setDefault($name, $defaultvalue);
        }
    }

    // Validation.

    /**
     * Validate the form data.
     *
     * @param array $data Form data.
     * @param array $files Form files.
     * @return array Validation errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $activitytype = $this->_customdata['activitytype'];
        $defaults = $this->_customdata['defaults'];

        if ($activitytype === 'assign') {
            $this->validate_assign($data, $defaults, $errors);
        } else if ($activitytype === 'quiz') {
            $this->validate_quiz($data, $defaults, $errors);
        } else if ($activitytype === 'forum') {
            $this->validate_forum($data, $defaults, $errors);
        }

        return $errors;
    }

    /**
     * Validate assignment extension.
     *
     * @param array $data
     * @param array $defaults
     * @param array $errors
     */
    private function validate_assign($data, $defaults, &$errors) {
        if (!empty($data['override_extensionduedate']) && !empty($data['extensionduedate'])) {
            $duedate = $defaults['duedate'] ?? 0;
            if ($duedate > 0 && $data['extensionduedate'] <= $duedate) {
                $errors['extensionduedate'] = get_string('extensionnotafterduedate', 'assign');
            }
        }
    }

    /**
     * Validate quiz extension/overrides.
     *
     * @param array $data
     * @param array $defaults
     * @param array $errors
     */
    private function validate_quiz($data, $defaults, &$errors) {
        if (!empty($data['override_extensionduedate']) && !empty($data['extensionduedate'])) {
            $duedate = $defaults['duedate'] ?? 0;
            if ($duedate > 0 && $data['extensionduedate'] <= $duedate) {
                $errors['extensionduedate'] = get_string(
                    'quiz_extension_must_be_after_duedate', 'local_unifiedgrader');
            }
        }

        // Time open must be before time close.
        if (!empty($data['override_timeopen']) && !empty($data['override_timeclose'])) {
            if ($data['timeopen'] > 0 && $data['timeclose'] > 0 && $data['timeopen'] >= $data['timeclose']) {
                $errors['timeclose'] = get_string('closebeforeopen', 'quiz');
            }
        }
    }

    /**
     * Validate forum extension.
     *
     * @param array $data
     * @param array $defaults
     * @param array $errors
     */
    private function validate_forum($data, $defaults, &$errors) {
        if (!empty($data['override_extensionduedate']) && !empty($data['extensionduedate'])) {
            $duedate = $defaults['duedate'] ?? 0;
            if ($duedate > 0 && $data['extensionduedate'] <= $duedate) {
                $errors['extensionduedate'] = get_string(
                    'forum_extension_must_be_after_duedate', 'local_unifiedgrader');
            }
        }
    }
}
