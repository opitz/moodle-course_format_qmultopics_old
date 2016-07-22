<?php

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir . '/formslib.php');

class newssettings_form extends moodleform {

    function definition() {
        global $CFG, $DB;

        $mform = $this->_form;
        $course = $this->_customdata['course'];
        $image = $this->_customdata['image'];

        //Hidden elements:
        $mform->addElement('hidden', 'course', $course->id);
        $mform->setType('course', PARAM_INT);

        $mform->addElement('checkbox', 'displaynews', get_string('displaynews', 'format_qmultopics'));
        $mform->setDefault('displaynews', false);

        $mform->addElement('checkbox', 'shownewsfull', get_string('shownewsfull', 'format_qmultopics'));
        $mform->setDefault('shownewsfull', false);

        $mform->disabledIf('name', 'displaynews', 'checked');

        /// Prepare file upload
        $filemanager_options = array();
        // 3 == FILE_EXTERNAL & FILE_INTERNAL
        // These two constant names are defined in repository/lib.php
        $filemanager_options['return_types'] = 3;
        $filemanager_options['accepted_types'] = 'jpg,jpeg,gif,png';
        $filemanager_options['subdirs'] = 0;
        $filemanager_options['maxbytes'] = 0;
        $filemanager_options['maxfiles'] = 1;
        $filemanager_options['mainfile'] = true;

        if ($image) {
            $mform->addElement('static', 'imagepreview', get_string('currentimage', 'format_qmultopics'), $image);
        }
        $mform->addElement('filepicker', 'image', get_string('imagefile', 'format_qmultopics'), null, $filemanager_options);
        $mform->addElement('checkbox', 'deleteimage', get_string('deleteimage', 'format_qmultopics'));

        $mform->addElement('text', 'alttext', get_string('alttext', 'format_qmultopics'));
        $mform->setType('alttext', PARAM_TEXT);

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
