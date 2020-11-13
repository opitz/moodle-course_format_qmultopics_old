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

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    // It must be included from a Moodle page.
}

require_once($CFG->libdir . '/formslib.php');

class newssettings_form extends moodleform {

    public function definition() {
        $mform = $this->_form;
        $course = $this->_customdata['course'];

        // Hidden elements.
        $mform->addElement('hidden', 'course', $course->id);
        $mform->setType('course', PARAM_INT);

        $mform->addElement('checkbox', 'displaynews', get_string('displaynews', 'format_qmultopics'));
        $mform->setDefault('displaynews', false);
        $mform->addElement('checkbox', 'shownewsfull', get_string('shownewsfull', 'format_qmultopics'));
        $mform->setDefault('shownewsfull', false);

        $mform->disabledIf('name', 'displaynews', 'checked');

        // Prepare file upload.
        $mform->addElement('checkbox', 'usestatictext', get_string('usestatictext', 'format_qmultopics'));
        $mform->addElement('editor', 'statictext_editor', get_string('statictext', 'format_qmultopics'));
        $mform->setType('statictext_editor', PARAM_RAW);
        $mform->disabledIf('statictext', 'usestatictext', 'notchecked');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons();
    }
}
