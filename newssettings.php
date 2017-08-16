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
 * Edit the introduction of a section
 *
 * @copyright 1999 Martin Dougiamas  http://dougiamas.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package course
 */
require_once("../../../config.php");
require_once("lib.php");
require_once($CFG->libdir . '/filelib.php');
require_once('newssettings_form.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once('locallib.php');
require_once($CFG->dirroot . '/lib/filestorage/file_storage.php');
require_once($CFG->dirroot . '/lib/gdlib.php');

$courseid = required_param('course', PARAM_INT);    // Week/topic ID

$PAGE->set_url('/course/newssettings.php', array('course' => $courseid));

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$modinfo = get_fast_modinfo($course->id);
$section = $modinfo->get_section_info(0);

require_login($course);
$context = context_course::instance($course->id);
require_capability('moodle/course:update', $context);

$editoroptions = array('context' => $context, 'maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $CFG->maxbytes, 'trusttext' => false, 'noclean' => true);

//Get saved details:
if (!$newssettings = $DB->get_record('format_qmultopics_news', array('courseid' => $course->id))) {
    $newssettings = new stdClass();
    $newssettings->courseid = $course->id;
}

$newssettings->statictextformat = FORMAT_HTML;
$newssettings = file_prepare_standard_editor($newssettings, 'statictext', $editoroptions, $context, 'format_qmultopics');

$mform = new newssettings_form(null, array('course' => $course));
$mform->set_data($newssettings); // set current value

$redir = new moodle_url('/course/view.php', array('id' => $course->id));
/// If data submitted, then process and store.
if ($mform->is_cancelled()) {
    redirect($redir);
} else if ($data = $mform->get_data()) {
    //Insert or update
    $dbobj = new stdClass();
    $dbobj->displaynews = isset($data->displaynews) ? $data->displaynews : 0;
    $dbobj->usestatictext = isset($data->usestatictext) ? $data->usestatictext : 0;
    $dbobj->shownewsfull = isset($data->shownewsfull) ? $data->shownewsfull : 0;
    if(isset($data->statictext_editor)) {
        $dbobj->statictext = isset($data->statictext_editor['text']) ? $data->statictext_editor['text'] : '';
        $dbobj->statictextformat = isset($data->statictext_editor['format']) ? $data->statictext_editor['format'] : 1;
    }

    if ($existing = $DB->get_record('format_qmultopics_news', array('courseid' => $course->id))) {
        $dbobj->id = $existing->id;
        $DB->update_record('format_qmultopics_news', $dbobj);
    } else {
        $dbobj->courseid = $course->id;
        $DB->insert_record('format_qmultopics_news', $dbobj);
    }
    redirect($redir);
}
$stredit = get_string('edita', '', " $course->fullname");
$strsummaryof = get_string('newssettingsof', 'format_qmultopics', $course->fullname);

$PAGE->set_title($stredit);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($stredit);
echo $OUTPUT->header();

echo $OUTPUT->heading($strsummaryof);

$mform->display();
echo $OUTPUT->footer();
