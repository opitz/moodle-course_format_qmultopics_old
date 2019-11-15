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
 * Topics (QMUL) course format.  Display the whole course as "topics" made of modules.
 *
 * @package format_qmultopics
 * @copyright 2006 The Open University
 * @author N.D.Freear@open.ac.uk, and others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/completionlib.php');

// Horrible backwards compatible parameter aliasing..
if ($topic = optional_param('topic', 0, PARAM_INT)) {
    $url = $PAGE->url;
    $url->param('section', $topic);
    debugging('Outdated topic param passed to course/view.php', DEBUG_DEVELOPER);
    redirect($url);
}
// End backwards-compatible aliasing..

// check if the Assessment Information block is required and available - and if needed install it into the course
$fo = $DB->get_records('course_format_options', array('courseid' => $course->id));
$format_options = array();
foreach($fo as $o) {
    $format_options[$o->name] = $o->value;
}
if ( array_key_exists('enable_assessmentinformation', $format_options) && $format_options['enable_assessmentinformation'] == '1') {
    $available_blocks = core_plugin_manager::instance()->get_plugins_of_type('block');
    if( array_key_exists("assessment_information", $available_blocks)) { // only do something if the AI block is available
        // get the installed blocks and check if the assessment info block is one of them
        $sql = "SELECT * FROM {context} cx join {block_instances} bi on bi.parentcontextid = cx.id where cx.contextlevel = 50 and cx.instanceid = ".$course->id;
        $installed_blocks = $DB->get_records_sql($sql, array());
        $assessment_info_block_id = false;
        foreach($installed_blocks as $installed_block) {
            if($installed_block->blockname == 'assessment_information') {
                $assessment_info_block_id = (int)$installed_block->id;
                break;
            }
        }

        // if the AI block is not yet installed for this course do it now
        if(!$assessment_info_block_id) {
            // get block context for the course
            $context = $DB->get_record('context', array('instanceid' => $course->id, 'contextlevel' => '50'));
            // install the Assessment Information block
            $ai_record = new stdClass();
            $ai_record->blockname = 'assessment_information';
            $ai_record->parentcontextid = $context->id;
            $ai_record->showinsubcontexts = 0;
            $ai_record->requiredbytheme = 0;
            $ai_record->pagetypepattern = 'course-view-*';
            $ai_record->defaultregion = 'side-pre';
            $ai_record->defaultweight = -5;
            $ai_record->configdata = '';
            $ai_record->timecreated = time();
            $ai_record->timemodified = time();

            $result = $DB->insert_record('block_instances', $ai_record);

            rebuild_course_cache($course->id, true); // rebuild the cache for that course so the changes become effective
        }
    }
}

$context = context_course::instance($course->id);

if (($marker >=0) && has_capability('moodle/course:setcurrentsection', $context) && confirm_sesskey()) {
    $course->marker = $marker;
    course_set_marker($course->id, $marker);
}

// make sure all sections are created
$course = course_get_format($course)->get_course();
course_create_sections_if_missing($course, 1);

$renderer = $PAGE->get_renderer('format_qmultopics');

if (!empty($displaysection)) {
    $renderer->print_single_section_page($course, null, null, null, null, $displaysection);
} else {
    $renderer->print_multiple_section_page($course, null, null, null, null);
}

// Include course format js module
$PAGE->requires->js('/course/format/qmultopics/format.js');
