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
require_once($CFG->dirroot. '/course/format/topcoll/lib.php'); // For format_qmultopics.

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
            $elements[] = $mform->addElement('header', 'assessmentinformation', get_string('assessmentinformation', 'format_qmultopics'));
            $mform->addHelpButton('assessmentinformation', 'assessmentinformation', 'format_qmultopics', '', true);

            $elements[] = $mform->addElement('checkbox', 'enable_assessmentinformation', get_string('enabletab', 'format_qmultopics'));

            $elements[] = $mform->addElement('htmleditor', 'content_assessmentinformation', get_string('assessmentinformation', 'format_qmultopics'));

            // Extra Tab 1
            $elements[] = $mform->addElement('header', 'extratab1', get_string('extratab', 'format_qmultopics', 1));
            $mform->addHelpButton('extratab1', 'extratab', 'format_qmultopics', '', true);

            $elements[] = $mform->addElement('checkbox', 'enable_extratab1', get_string('enabletab', 'format_qmultopics'));

            $elements[] = $mform->addElement('text', 'title_extratab1', get_string('tabtitle', 'format_qmultopics'));

            $elements[] = $mform->addElement('htmleditor', 'content_extratab1', get_string('tabcontent', 'format_qmultopics'));

            // Extra Tab 2
            $elements[] = $mform->addElement('header', 'extratab2', get_string('extratab', 'format_qmultopics', 2));
            $mform->addHelpButton('extratab2', 'extratab', 'format_qmultopics', '', true);

            $elements[] = $mform->addElement('checkbox', 'enable_extratab2', get_string('enabletab', 'format_qmultopics'));

            $elements[] = $mform->addElement('text', 'title_extratab2', get_string('tabtitle', 'format_qmultopics'));

            $elements[] = $mform->addElement('htmleditor', 'content_extratab2', get_string('tabcontent', 'format_qmultopics'));

            // Extra Tab 3
            $elements[] = $mform->addElement('header', 'extratab3', get_string('extratab', 'format_qmultopics', 3));
            $mform->addHelpButton('extratab3', 'extratab', 'format_qmultopics', '', true);

            $elements[] = $mform->addElement('checkbox', 'enable_extratab3', get_string('enabletab', 'format_qmultopics'));

            $elements[] = $mform->addElement('text', 'title_extratab3', get_string('tabtitle', 'format_qmultopics'));

            $elements[] = $mform->addElement('htmleditor', 'content_extratab3', get_string('tabcontent', 'format_qmultopics'));

        }

        return $elements;
    }

    public function edit_form_validation($data, $files, $errors) {

        $return = parent::edit_form_validation($data, $files, $errors);

        if (isset($data['enable_extratab1'])) {
            if (empty($data['title_extratab1'])) {
                $return['title_extratab1'] = get_string('titlerequiredwhenenabled', 'format_qmultopics');
            }
        } else {
            $data['enabled_extratab1'] = 0;
        }
        if (isset($data['enable_extratab2'])) {
            if (empty($data['title_extratab2'])) {
                $return['title_extratab2'] = get_string('titlerequiredwhenenabled', 'format_qmultopics');
            }
        } else {
            $data['enabled_extratab1'] = 0;
        }
        if (isset($data['enable_extratab3'])) {
            if (empty($data['title_extratab3'])) {
                $return['title_extratab3'] = get_string('titlerequiredwhenenabled', 'format_qmultopics');
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

    public function course_format_options($foreditform = false) {
        global $CFG;
        $max_tabs = (isset($CFG->max_tabs) ? $CFG->max_tabs : 5);
        static $courseformatoptions = false;

        if ($courseformatoptions === false) {
            $courseconfig = get_config('moodlecourse');
            $courseformatoptions = array(
                'hiddensections' => array(
                    'default' => $courseconfig->hiddensections,
                    'type' => PARAM_INT,
                ),
//                'coursedisplay' => array(
//                    'default' => get_config('format_qmultopics', 'defaultcoursedisplay'),
//                    'type' => PARAM_INT,
//                ),
                'section0_ontop' => array(
                    'default' => false,
                    'type' => PARAM_BOOL,
                    'label' => '',
                    'element_type' => 'hidden',
                ),

                'single_section_tabs' => array(
                    'default' => get_config('format_qmultopics', 'defaultsectionnameastabname'),
                    'type' => PARAM_BOOL
                ),

/*
                'assessment_info_block_tab' => array(
                    'default' => get_config('format_qmultopics', 'defaultshowassessmentinfotab'),
                    'type' => PARAM_BOOL
                ),
*/
                'assessment_info_block_tab' => array(
                    'default' => get_config('format_qmultopics', 'defaultshowassessmentinfotab'),
                    'type' => PARAM_INT,
                ),

                'displayinstructions' => array(
                    'default' => get_config('format_qmultopics', 'defaultdisplayinstructions'),
                    'type' => PARAM_INT,
                ),
                'layoutelement' => array(
                    'default' => get_config('format_qmultopics', 'defaultlayoutelement'),
                    'type' => PARAM_INT,
                    'label' => '',
                    'element_type' => 'hidden',
                ),
                'layoutstructure' => array(
                    'default' => get_config('format_qmultopics', 'defaultlayoutstructure'),
                    'type' => PARAM_INT,
                    'label' => '',
                    'element_type' => 'hidden',
                ),
                'layoutcolumns' => array(
                    'default' => get_config('format_qmultopics', 'defaultlayoutcolumns'),
                    'type' => PARAM_INT,
                    'label' => '',
                    'element_type' => 'hidden',
                ),
                'layoutcolumnorientation' => array(
                    'default' => get_config('format_qmultopics', 'defaultlayoutcolumnorientation'),
                    'type' => PARAM_INT,
                    'label' => '',
                    'element_type' => 'hidden',
                ),
            );
            // the sequence in which the tabs will be displayed
            $courseformatoptions['tab_seq'] = array('default' => '','type' => PARAM_TEXT,'label' => '','element_type' => 'hidden',);

            // now loop through the tabs but don't show them as we only need the DB records...
            $courseformatoptions['tab0_title'] = array('default' => get_string('modulecontent', 'format_qmultopics'),'type' => PARAM_TEXT,'label' => '','element_type' => 'hidden',);
            $courseformatoptions['tab0'] = array('default' => "",'type' => PARAM_TEXT,'label' => '','element_type' => 'hidden',);
            for ($i = 1; $i <= $max_tabs; $i++) {
                $courseformatoptions['tab'.$i.'_title'] = array('default' => "Tab ".$i,'type' => PARAM_TEXT,'label' => '','element_type' => 'hidden',);
                $courseformatoptions['tab'.$i] = array('default' => "",'type' => PARAM_TEXT,'label' => '','element_type' => 'hidden',);
                $courseformatoptions['tab'.$i.'_sectionnums'] = array('default' => "",'type' => PARAM_TEXT,'label' => '','element_type' => 'hidden',);
            }

            // Allow to store a name for the Assessment Info tab
            $courseformatoptions['tab_assessment_information_title'] = array('default' => get_string('tab_assessment_information_title', 'format_qmultopics'),'type' => PARAM_TEXT,'label' => '','element_type' => 'hidden',);

            // Allow to store a name for the Assessment Info Block tab
            $courseformatoptions['tab_assessment_info_block_title'] = array('default' => get_string('tab_assessment_info_block_title', 'format_qmultopics'),'type' => PARAM_TEXT,'label' => '','element_type' => 'hidden',);

        }
        if ($foreditform && !isset($courseformatoptions['coursedisplay']['label'])) {
            $courseconfig = get_config('moodlecourse');
            $sectionmenu = array();
            for ($i = 0; $i <= $courseconfig->maxsections; $i++) {
                $sectionmenu[$i] = "$i";
            }
            $courseformatoptionsedit = array(

                'hiddensections' => array(
                    'label' => new lang_string('hiddensections'),
                    'help' => 'hiddensections',
                    'help_component' => 'moodle',
                    'element_type' => 'hidden',
                    'element_attributes' => array(
                        array(0 => new lang_string('hiddensectionscollapsed'),
                            1 => new lang_string('hiddensectionsinvisible')
                        )
                    ),
                ),

                'displayinstructions' => array(
                    'label' => new lang_string('displayinstructions', 'format_qmultopics'),
                    'help' => 'displayinstructions',
                    'help_component' => 'format_qmultopics',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(1 => new lang_string('no'),
                            2 => new lang_string('yes'))
                    )
                ),

                'single_section_tabs' => array(
                    'label' => get_string('single_section_tabs_label', 'format_qmultopics'),
                    'element_type' => 'advcheckbox',
                    'help' => 'single_section_tabs',
                    'help_component' => 'format_qmultopics',
                ),

/*
                'assessment_info_block_tab' => array(
                    'label' => get_string('assessment_info_block_tab_label', 'format_qmultopics'),
                    'element_type' => 'advcheckbox',
                    'help' => 'assessment_info_block_tab',
                    'help_component' => 'format_qmultopics',
                ),
*/
                'assessment_info_block_tab' => array(
                    'label' => get_string('assessment_info_block_tab_label', 'format_qmultopics'),
                    'help' => 'assessment_info_block_tab',
                    'help_component' => 'format_qmultopics',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(0 => get_string('assessment_info_block_tab_option0', 'format_qmultopics'),
                            1 => get_string('assessment_info_block_tab_option1', 'format_qmultopics'),
                            2 => get_string('assessment_info_block_tab_option2', 'format_qmultopics'))
                    )
                ),
            );

            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }
        return $courseformatoptions;
    }

    public function section_action($section, $action, $sr) {
        global $PAGE;

        $tcsettings = $this->get_format_options();
        if ($section->section && ($action === 'setmarker' || $action === 'removemarker')) {
            // Format 'tabtopics' allows to set and remove markers in addition to common section actions.
            require_capability('moodle/course:setcurrentsection', context_course::instance($this->courseid));
            course_set_marker($this->courseid, ($action === 'setmarker') ? $section->section : 0);
            return null;
        }

        switch ($action) {
            case 'movetotabzero':
                return $this->move2tab(0, $section, $tcsettings);
                break;
            case 'movetotabone':
                return $this->move2tab(1, $section, $tcsettings);
                break;
            case 'movetotabtwo':
                return $this->move2tab(2, $section, $tcsettings);
                break;
            case 'movetotabthree':
                return $this->move2tab(3, $section, $tcsettings);
                break;
            case 'movetotabfour':
                return $this->move2tab(4, $section, $tcsettings);
                break;
            case 'movetotabfive':
                return $this->move2tab(5, $section, $tcsettings);
                break;
            case 'movetotabsix':
                return $this->move2tab(6, $section, $tcsettings);
                break;
            case 'movetotabseven':
                return $this->move2tab(7, $section, $tcsettings);
                break;
            case 'movetotabeight':
                return $this->move2tab(8, $section, $tcsettings);
                break;
            case 'movetotabnine':
                return $this->move2tab(9, $section, $tcsettings);
                break;
            case 'movetotabten':
                return $this->move2tab(10, $section, $tcsettings);
                break;
            case 'removefromtabs':
                return $this->removefromtabs($PAGE->course, $section, $tcsettings);
                break;
            case 'sectionzeroontop':
                return $this->sectionzeroswitch($tcsettings, true);
                break;
            case 'sectionzeroinline':
                return $this->sectionzeroswitch($tcsettings, false);
                break;
        }

        // For show/hide actions call the parent method and return the new content for .section_availability element.
        $rv = parent::section_action($section, $action, $sr);
        $renderer = $PAGE->get_renderer('format_tabtopics');
        $rv['section_availability'] = $renderer->section_availability($this->get_section($section));
        return $rv;
    }

// move section ID and section number to tab format settings of a given tab
    public function move2tab($tabnum, $section2move, $settings) {
        global $PAGE;

        $course = $PAGE->course;

        // remove section number from all tab format settings
        $settings = $this->removefromtabs($course, $section2move, $settings);

        // add section number to new tab format settings if not tab0
        if($tabnum > 0){
            $settings['tab'.$tabnum] .= ($settings['tab'.$tabnum] === '' ? '' : ',').$section2move->id;
            $settings['tab'.$tabnum.'_sectionnums'] .= ($settings['tab'.$tabnum.'_sectionnums'] === '' ? '' : ',').$section2move->section;
            $this->update_course_format_options($settings);
        }
        return $settings;
    }

// remove section id from all tab format settings
    public function removefromtabs($course, $section2remove, $settings) {
        global $CFG;

        $max_tabs = (isset($CFG->max_tabs) ? $CFG->max_tabs : 5);

        for($i = 0; $i <= $max_tabs; $i++) {
            if(strstr($settings['tab'.$i], $section2remove->id) > -1) {
                $sections = explode(',', $settings['tab'.$i]);
                $new_sections = array();
                foreach($sections as $section) {
                    if($section != $section2remove->id) {
                        $new_sections[] = $section;
                    }
                }
                $settings['tab'.$i] = implode(',', $new_sections);

                $section_nums = explode(',', $settings['tab'.$i.'_sectionnums']);
                $new_section_nums = array();
                foreach($section_nums as $section_num) {
                    if($section_num != $section2remove->section) {
                        $new_section_nums[] = $section_num;
                    }
                }
                $settings['tab'.$i.'_sectionnums'] = implode(',', $new_section_nums);
                $this->update_course_format_options($settings);
            }
        }
        return $settings;
    }

// switch to show section0 always on top of the tabs
    public function sectionzeroswitch($settings, $value) {
        $settings['section0_ontop'] = $value;
        $this->update_course_format_options($settings);

        return $settings;
    }
}

/**
 * Implements callback inplace_editable() allowing to edit values in-place
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return \core\output\inplace_editable
 */
function format_qmultopics_inplace_editable($itemtype, $itemid, $newvalue) {
    global $CFG;
    require_once($CFG->dirroot . '/course/lib.php');

    // deal with inplace changes of a section name
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        global $DB;
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            array($itemid, 'qmultopics'), MUST_EXIST);
        $result = course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
        return $result;
    }

    // deal with inplace changes of a tab name
    if ($itemtype === 'tabname') {
        global $DB, $PAGE;
        $courseid = key($_SESSION['USER']->currentcourseaccess);
        // the $itemid is actually the name of the record so use it to get the id

        // update the database with the new value given
        // Must call validate_context for either system, or course or course module context.
        // This will both check access and set current context.
        \external_api::validate_context(context_system::instance());
        // Check permission of the user to update this item.
//        require_capability('moodle/course:update', context_system::instance());
        // Clean input and update the record.
        $newvalue = clean_param($newvalue, PARAM_NOTAGS);
        $record = $DB->get_record('course_format_options', array('id' => $itemid), '*', MUST_EXIST);
//        $record['value'] = $newvalue;
        $DB->update_record('course_format_options', array('id' => $record->id, 'value' => $newvalue));

        // Prepare the element for the output ():
        $output = new \core\output\inplace_editable('format_qmultopics', 'tabname', $record->id,
            true,
            format_string($newvalue), $newvalue, 'Edit tab name',  'New value for ' . format_string($newvalue));

        return $output;
    }
}

function format_qmultopics_inplace_editable0($itemtype, $itemid, $newvalue) {
    global $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        global $DB;
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            array($itemid, 'qmultopics'), MUST_EXIST);
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
}

function qmultopics_format_get_assessmentinformation($content) {
    global $CFG, $DB, $COURSE, $OUTPUT, $USER;

    $output = html_writer::tag('div', format_text($content), array('class'=>'assessmentinfo col-12 mb-3'));

    $assignments = qmultopics_format_get_assignments();

    $assignoutput = html_writer::tag('div', get_string('assignmentsdue', 'format_qmultopics'), array('class'=>'card-header h5'));
    $assignoutput .= html_writer::start_tag('div', array('class'=>'list-group list-group-flush'));
    $assignsubmittedoutput = html_writer::tag('div', get_string('assignmentssubmitted', 'format_qmultopics'), array('class'=>'card-header h5'));
    $assignsubmittedoutput .= html_writer::start_tag('div', array('class'=>'list-group list-group-flush'));

    $modinfo = get_fast_modinfo($COURSE);

    $submitted = 0;
    $due = 0;
    foreach ($assignments as $assignment) {

        $context = context_module::instance($assignment->cmid);
        $canviewhidden = has_capability('moodle/course:viewhiddenactivities', $context);

        $hidden = '';
        if (!$assignment->visible) {
            $hidden = ' notvisible';
        }

        $cminfo = $modinfo->get_cm($assignment->cmid);

        $conditionalhidden = false;
        if (!empty($CFG->enableavailability)) {
            $info = new \core_availability\info_module($cminfo);
            if (!$info->is_available_for_all()) {
                $information = '';
                if ($info->is_available($information)) {
                    $hidden = ' conditionalhidden';
                    $conditionalhidden = false;
                } else {
                    $hidden = ' notvisible conditionalhidden';
                    $conditionalhidden = true;
                }
            }
        }

        $accessiblebutdim = (!$assignment->visible || $conditionalhidden) && $canviewhidden;

        if ((!$assignment->visible || $conditionalhidden) && !$canviewhidden) {
            continue;
        }

        // Check overrides for new duedate

        $sql = "SELECT
                    module.id,
                    module.allowsubmissionsfromdate AS timeopen,
                    module.duedate AS timeclose";
        $groups = groups_get_user_groups($COURSE->id);
        $groupbysql = '';
        $params = array();
        if ($groups[0]) {
            list ($groupsql, $params) = $DB->get_in_or_equal($groups[0]);
            $sql .= ", CASE WHEN ovrd1.allowsubmissionsfromdate IS NULL THEN MIN(ovrd2.allowsubmissionsfromdate) ELSE ovrd1.allowsubmissionsfromdate END AS timeopenover,
                    CASE WHEN ovrd1.duedate IS NULL THEN MAX(ovrd2.duedate) ELSE ovrd1.duedate END AS timecloseover
                    FROM {assign} module
                    LEFT JOIN {assign_overrides} ovrd1 ON module.id=ovrd1.assignid AND $USER->id=ovrd1.userid
                    LEFT JOIN {assign_overrides} ovrd2 ON module.id=ovrd2.assignid AND ovrd2.groupid $groupsql";
            $groupbysql = " GROUP BY module.id, timeopen, timeclose, ovrd1.allowsubmissionsfromdate, ovrd1.duedate";
        } else {
            $sql .= ", ovrd1.allowsubmissionsfromdate AS timeopenover, ovrd1.duedate AS timecloseover
                     FROM {assign} module
                     LEFT JOIN {assign_overrides} ovrd1
                     ON module.id=ovrd1.assignid AND $USER->id=ovrd1.userid";
        }
        $sql .= " WHERE module.course = ?";
        $sql .= " AND module.id = ?";
        $sql .= $groupbysql;
        $params[] = $COURSE->id;
        $params[] = $assignment->id;
        $overrides = $DB->get_records_sql($sql, $params);
        $overrides = reset($overrides);
        if (!empty($overrides->timecloseover)) {
            $assignment->duedate = $overrides->timecloseover;
            if ($overrides->timeopenover) {
                $assignment->open = $overrides->open;
            }
        }

        $out = '';
        $url = new moodle_url('/mod/assign/view.php', array('id' => $assignment->cmid));
        if ($assignment->status == 'submitted') {
            $duestatus = get_string('submitted', 'widgettype_assignments');
            $statusclass = 'success';
        } else if ($assignment->status == 'draft') {
            $duestatus = get_string('draft', 'widgettype_assignments');
            $statusclass = 'info';
        } else if ($assignment->duedate > 0 && $assignment->duedate < time()) {
            $duestatus = get_string('overdue', 'widgettype_assignments');
            $statusclass = 'danger';
        } else if ($assignment->duedate > 0 && $assignment->duedate < (time() + 14 * DAYSECS)) {
            $duestatus = get_string('duesoon', 'widgettype_assignments');
            $statusclass = 'warning';
        } else {
            $duestatus = '';
            $statusclass = 'info';
        }

        $duedate = date('d/m/Y', $assignment->duedate);

        $out .= html_writer::start_tag('div', array('class'=>'list-group-item assignment'.$hidden));

        $out .= html_writer::start_tag('div', array('class'=>'d-flex flex-wrap align-items-center mb-2'));
        $out .= $OUTPUT->pix_icon('icon', 'assign', 'mod_assign', ['class'=>'mr-2']);
        $out .= html_writer::link($url, $assignment->name, array('class'=>'name col p-0'));

        if ($assignment->duedate > 0) {
            $out .= html_writer::tag('div', $duedate, array('class'=>'due-date ml-auto badge badge-'.$statusclass,
                'data-toggle'=>'tooltip', 'data-placement'=>'top', 'title'=>$duestatus));
        }
        $out .= html_writer::end_tag('div');

        if ($assignment->showdescription) {
            $out .= html_writer::tag('div', format_text($assignment->intro), array('class'=>"summary pl-4"));
        }
        $out .= html_writer::end_tag('div');

        if ($assignment->status == 'submitted') {
            $submitted++;
            $assignsubmittedoutput .= $out;
        } else {
            $due++;
            $assignoutput .= $out;
        }
    }
    if ($submitted == 0) {
        $assignsubmittedoutput .= html_writer::tag('div', get_string('noassignmentssubmitted', 'format_qmultopics'), array('class'=>'card-body'));
    }
    if ($due == 0) {
        $assignoutput .= html_writer::tag('div', get_string('noassignmentsdue', 'format_qmultopics'), array('class'=>'card-body'));
    }
    $assignoutput .= html_writer::end_tag('div');
    $assignsubmittedoutput .= html_writer::end_tag('div');
    $assignoutput = html_writer::tag('div', $assignoutput, array('class'=>'card'));
    $assignsubmittedoutput = html_writer::tag('div', $assignsubmittedoutput, array('class'=>'card'));

    $output .= html_writer::tag('div', $assignoutput, array('class'=>'col-12 col-md-6 mb-1'));
    $output .= html_writer::tag('div', $assignsubmittedoutput, array('class'=>'col-12 col-md-6 mb-1'));

    return html_writer::tag('div', $output, array('class'=>'row'));
}

function qmultopics_format_get_assignments() {
    global $DB, $COURSE, $USER;
    $sql = "
       SELECT a.id, cm.id AS cmid, cm.visible, cm.showdescription, a.name, a.duedate, s.status, a.intro, g.grade, gi.gradepass,
              gi.hidden As gradehidden, a.markingworkflow, uf.workflowstate
         FROM {assign} a
         JOIN {course_modules} cm ON cm.instance = a.id
         JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
         JOIN (SELECT DISTINCT e.courseid
                          FROM {enrol} e
                          JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = :userid1
                         WHERE e.status = :enabled AND ue.status = :active
                           AND ue.timestart < :now1 AND (ue.timeend = 0 OR ue.timeend > :now2)
              ) en ON (en.courseid = a.course)
         LEFT JOIN {assign_submission} s ON s.assignment = a.id AND s.userid = :userid2 AND s.latest = 1
         LEFT JOIN {assign_grades} g ON g.assignment = a.id AND g.userid = :userid3 AND g.attemptnumber = s.attemptnumber
         LEFT JOIN {grade_items} gi ON gi.iteminstance = a.id AND itemmodule = 'assign'
         LEFT JOIN {assign_user_flags} uf ON uf.assignment = a.id AND uf.userid = s.userid
        WHERE a.course = :courseid
        ORDER BY a.duedate
    ";
    $params = [
        'userid1' => $USER->id, 'userid2' => $USER->id, 'userid3' => $USER->id,
        'now1' => time(), 'now2' => time(),
        'active' => ENROL_USER_ACTIVE, 'enabled' => ENROL_INSTANCE_ENABLED,
        'courseid' => $COURSE->id
    ];

    $assignments = $DB->get_recordset_sql($sql, $params);
    return $assignments;
}