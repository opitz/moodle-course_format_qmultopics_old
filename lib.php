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
 * This file contains main class for the course format Topic
 *
 * @since     Moodle 2.0
 * @package   format_qmultopics
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot. '/course/format/lib.php');
require_once($CFG->dirroot. '/course/format/topics/lib.php');
require_once($CFG->dirroot. '/course/format/topcoll/lib.php'); // For format_qmultc.

/**
 * Main class for the Topics (QMUL) course format
 *
 * @package    format_qmultopics
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_qmultopics extends format_topics {

    /**
     * Adds format options elements to the course/section edit form
     *
     * This function is called from {@link course_edit_form::definition_after_data()}
     *
     * @param MoodleQuickForm $mform form the elements are added to
     * @param bool $forsection 'true' if this is a section edit form, 'false' if this is course edit form
     * @return array array of references to the added form elements
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        global $CFG, $OUTPUT;
        $elements = parent::create_edit_form_elements($mform, $forsection);
        if ($forsection == false) {

            // Assessment Information
            $elements[] = $mform->addElement('header', 'assessmentinformation', get_string('assessmentinformation', 'format_qmultc'));
            $mform->addHelpButton('assessmentinformation', 'assessmentinformation', 'format_qmultc', '', true);

            $elements[] = $mform->addElement('checkbox', 'enable_assessmentinformation', get_string('enabletab', 'format_qmultc'));

            $elements[] = $mform->addElement('htmleditor', 'content_assessmentinformation', get_string('assessmentinformation', 'format_qmultc'));

            // Extra Tab 1
            $elements[] = $mform->addElement('header', 'extratab1', get_string('extratab', 'format_qmultc', 1));
            $mform->addHelpButton('extratab1', 'extratab', 'format_qmultc', '', true);

            $elements[] = $mform->addElement('checkbox', 'enable_extratab1', get_string('enabletab', 'format_qmultc'));

            $elements[] = $mform->addElement('text', 'title_extratab1', get_string('tabtitle', 'format_qmultc'));

            $elements[] = $mform->addElement('htmleditor', 'content_extratab1', get_string('tabcontent', 'format_qmultc'));

            // Extra Tab 2
            $elements[] = $mform->addElement('header', 'extratab2', get_string('extratab', 'format_qmultc', 2));
            $mform->addHelpButton('extratab2', 'extratab', 'format_qmultc', '', true);

            $elements[] = $mform->addElement('checkbox', 'enable_extratab2', get_string('enabletab', 'format_qmultc'));

            $elements[] = $mform->addElement('text', 'title_extratab2', get_string('tabtitle', 'format_qmultc'));

            $elements[] = $mform->addElement('htmleditor', 'content_extratab2', get_string('tabcontent', 'format_qmultc'));

            // Extra Tab 3
            $elements[] = $mform->addElement('header', 'extratab3', get_string('extratab', 'format_qmultc', 3));
            $mform->addHelpButton('extratab3', 'extratab', 'format_qmultc', '', true);

            $elements[] = $mform->addElement('checkbox', 'enable_extratab3', get_string('enabletab', 'format_qmultc'));

            $elements[] = $mform->addElement('text', 'title_extratab3', get_string('tabtitle', 'format_qmultc'));

            $elements[] = $mform->addElement('htmleditor', 'content_extratab3', get_string('tabcontent', 'format_qmultc'));

        }

        return $elements;
    }

    public function edit_form_validation($data, $files, $errors) {

        $return = parent::edit_form_validation($data, $files, $errors);

        if (isset($data['enable_extratab1'])) {
            if (empty($data['title_extratab1'])) {
                $return['title_extratab1'] = get_string('titlerequiredwhenenabled', 'format_qmultc');
            }
        } else {
            $data['enabled_extratab1'] = 0;
        }
        if (isset($data['enable_extratab2'])) {
            if (empty($data['title_extratab2'])) {
                $return['title_extratab2'] = get_string('titlerequiredwhenenabled', 'format_qmultc');
            }
        } else {
            $data['enabled_extratab1'] = 0;
        }
        if (isset($data['enable_extratab3'])) {
            if (empty($data['title_extratab3'])) {
                $return['title_extratab3'] = get_string('titlerequiredwhenenabled', 'format_qmultc');
            }
        } else {
            $data['enabled_extratab1'] = 0;
        }

        return $return;
    }

    /**
     * Updates format options for a course
     *
     * In case if course format was changed to 'Collapsed Topics', we try to copy options
     * 'coursedisplay', 'numsections' and 'hiddensections' from the previous format.
     * If previous course format did not have 'numsections' option, we populate it with the
     * current number of sections.  The layout and colour defaults will come from 'course_format_options'.
     *
     * @param stdClass|array $data return value from {@link moodleform::get_data()} or array with data
     * @param stdClass $oldcourse if this function is called from {@link update_course()}
     *     this object contains information about the course before update
     * @return bool whether there were any changes to the options values
     */
    public function update_course_format_options($data, $oldcourse = null) {
        global $DB;

        $newdata = (array) $data;
        $savedata = array();
        if (isset($newdata['fullname'])) {
            if (isset($newdata['enable_assessmentinformation'])) {
                $savedata['enable_assessmentinformation'] = $newdata['enable_assessmentinformation'];
            } else {
                $savedata['enable_assessmentinformation'] = 0;
            }
            if (isset($newdata['content_assessmentinformation'])) {
                $savedata['content_assessmentinformation'] = $newdata['content_assessmentinformation'];
            }
            if (isset($newdata['enable_extratab1'])) {
                $savedata['enable_extratab1'] = $newdata['enable_extratab1'];
            } else {
                $savedata['enable_extratab1'] = 0;
            }
            if (isset($newdata['title_extratab1'])) {
                $savedata['title_extratab1'] = $newdata['title_extratab1'];
            }
            if (isset($newdata['content_extratab1'])) {
                $savedata['content_extratab1'] = $newdata['content_extratab1'];
            }
            if (isset($newdata['enable_extratab2'])) {
                $savedata['enable_extratab2'] = $newdata['enable_extratab2'];
            } else {
                $savedata['enable_extratab2'] = 0;
            }
            if (isset($newdata['title_extratab2'])) {
                $savedata['title_extratab2'] = $newdata['title_extratab2'];
            }
            if (isset($newdata['content_extratab2'])) {
                $savedata['content_extratab2'] = $newdata['content_extratab2'];
            }
            if (isset($newdata['enable_extratab3'])) {
                $savedata['enable_extratab3'] = $newdata['enable_extratab3'];
            } else {
                $savedata['enable_extratab3'] = 0;
            }
            if (isset($newdata['title_extratab3'])) {
                $savedata['title_extratab3'] = $newdata['title_extratab3'];
            }
            if (isset($newdata['content_extratab3'])) {
                $savedata['content_extratab3'] = $newdata['content_extratab3'];
            }
        }

        $records = $DB->get_records('course_format_options',
                array('courseid' => $this->courseid,
                      'format' => $this->format,
                      'sectionid' => 0
                    ), '', 'name,id,value');

        foreach ($savedata as $key => $value) {
             if (isset($records[$key])) {
                if (array_key_exists($key, $newdata) && $records[$key]->value !== $newdata[$key]) {
                    $DB->set_field('course_format_options', 'value',
                            $value, array('id' => $records[$key]->id));
                    $changed = true;
                } else {
                    $DB->set_field('course_format_options', 'value',
                            $value, array('id' => $records[$key]->id));
                    $changed = true;
                }
            } else {
                $DB->insert_record('course_format_options', array(
                    'courseid' => $this->courseid,
                    'format' => $this->format,
                    'sectionid' => 0,
                    'name' => $key,
                    'value' => $value
                ));
            }
        }

        $changes = parent::update_course_format_options($data, $oldcourse);

        return $changes;
    }

    /**
     * Returns the format options stored for this course or course section
     *
     * When overriding please note that this function is called from rebuild_course_cache()
     * and section_info object, therefore using of get_fast_modinfo() and/or any function that
     * accesses it may lead to recursion.
     *
     * @param null|int|stdClass|section_info $section if null the course format options will be returned
     *     otherwise options for specified section will be returned. This can be either
     *     section object or relative section number (field course_sections.section)
     * @return array
     */
    public function get_format_options($section = null) {
        global $DB;

        $options = parent::get_format_options($section);

        if ($section === null) {
            // course format options will be returned
            $sectionid = 0;
        } else if ($this->courseid && isset($section->id)) {
            // course section format options will be returned
            $sectionid = $section->id;
        } else if ($this->courseid && is_int($section) &&
                ($sectionobj = $DB->get_record('course_sections',
                        array('section' => $section, 'course' => $this->courseid), 'id'))) {
            // course section format options will be returned
            $sectionid = $sectionobj->id;
        } else {
            // non-existing (yet) section was passed as an argument
            // default format options for course section will be returned
            $sectionid = -1;
        }

        if ($sectionid == 0) {
            $alloptions = $DB->get_records('course_format_options',
                        array('courseid'=>$this->courseid, 'format'=>'qmultopics',
                            'sectionid'=>0));

            foreach ($alloptions as $option) {
                if (!isset($options[$option->name])) {
                    $options[$option->name] = $option->value;
                }
            }

            $this->formatoptions[$sectionid] = $options;
        }

        return $options;
    }

}