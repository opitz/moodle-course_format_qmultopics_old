<?php

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir . '/formslib.php');

class newssettings_form extends moodleform {

    function definition() {
        $mform = $this->_form;
        $course = $this->_customdata['course'];

        //Hidden elements:
        $mform->addElement('hidden', 'course', $course->id);
        $mform->setType('course', PARAM_INT);

        $mform->addElement('checkbox', 'displaynews', get_string('displaynews', 'format_qmultopics'));
        $mform->setDefault('displaynews', false);
        $mform->addElement('checkbox', 'shownewsfull', get_string('shownewsfull', 'format_qmultopics'));
        $mform->setDefault('shownewsfull', false);

        $mform->disabledIf('name', 'displaynews', 'checked');

        /// Prepare file upload
        $mform->addElement('checkbox', 'usestatictext', get_string('usestatictext', 'format_qmultopics'));
        $mform->addElement('editor', 'statictext_editor', get_string('statictext', 'format_qmultopics'));
        $mform->setType('statictext_editor', PARAM_RAW);
        $mform->disabledIf('statictext', 'usestatictext', 'notchecked');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

//--------------------------------------------------------------------------------
        $this->add_action_buttons();
    }
}
