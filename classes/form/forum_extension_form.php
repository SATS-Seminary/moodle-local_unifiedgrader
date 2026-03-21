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
 * Forum extension form for the unified grader.
 *
 * A simple date/time picker form for granting or editing a forum due date
 * extension. Stored in the local_unifiedgrader_fext table.
 *
 * @package    local_unifiedgrader
 * @copyright  2026 South African Theological Seminary (mathieu@sats.ac.za)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_unifiedgrader\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

class forum_extension_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $duedate = $this->_customdata['duedate'] ?? 0;
        $existingextension = $this->_customdata['existingextension'] ?? 0;

        // Show the original forum due date as a read-only reference.
        if ($duedate > 0) {
            $mform->addElement(
                'static',
                'originalduedate',
                get_string('forum_extension_original_duedate', 'local_unifiedgrader'),
                userdate($duedate),
            );
        }

        // Show the current extension if one already exists.
        if ($existingextension > 0) {
            $mform->addElement(
                'static',
                'currentextension',
                get_string('forum_extension_current_extension', 'local_unifiedgrader'),
                userdate($existingextension),
            );
        }

        // Extension due date picker.
        $mform->addElement(
            'date_time_selector',
            'extensionduedate',
            get_string('forum_extension_new_duedate', 'local_unifiedgrader'),
        );

        // Hidden fields.
        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);

        $this->add_action_buttons(true, get_string('save'));
    }

    /**
     * Validate the extension date is after the forum due date.
     *
     * @param array $data Form data.
     * @param array $files Form files.
     * @return array Validation errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $duedate = $this->_customdata['duedate'] ?? 0;
        if ($duedate > 0 && $data['extensionduedate'] <= $duedate) {
            $errors['extensionduedate'] = get_string(
                'forum_extension_must_be_after_duedate', 'local_unifiedgrader'
            );
        }

        return $errors;
    }
}
