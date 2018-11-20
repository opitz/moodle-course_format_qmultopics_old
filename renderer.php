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
 * Renderer for outputting the qmultopics course format.
 *
 * @package format_qmultopics
 * @copyright 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.3
 */


defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/course/format/renderer.php');
require_once($CFG->dirroot.'/course/format/qmultopics/lib.php');
require_once($CFG->dirroot . '/theme/qmul/classes/output/format_topics_renderer.php');

/**
 * Basic renderer for qmultopics format.
 *
 * @copyright 2012 Dan Poltawski
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_qmultopics_renderer extends theme_qmul_format_topics_renderer {

    private $courseformat = null;
    private $tcsettings;

    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);
        $this->courseformat = course_get_format($page->course);
        $this->tcsettings = $this->courseformat->get_format_options();
    }

    /**
     * Output the html for a multiple section page
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     */
    public function print_multiple_section_page1($course, $sections, $mods, $modnames, $modnamesused)
    {
        global $DB, $CFG, $PAGE, $OUTPUT;

        // allow up to 5 user tabs if nothing else is set in the config file
        $max_tabs = (isset($CFG->max_tabs) ? $CFG->max_tabs : 5);

        $this->page->requires->js_call_amd('format_qmultopics/tabs', 'init', array());

        $tabs = array();
        $modinfo = get_fast_modinfo($course);
        $course = $this->courseformat->get_course();
        $sections = $DB->get_records('course_sections', array('course' => $course->id));
        $format_options = $this->courseformat->get_format_options();
        if (empty($this->tcsettings)) {
            $this->tcsettings = $this->courseformat->get_settings();
        }

        $this->tcsettings['showsectionsummary'] = 1;

        $context = context_course::instance($course->id);
        // Title with completion help icon.
        $completioninfo = new completion_info($course);
        echo $completioninfo->display_help_icon();
        echo $this->output->heading($this->page_title(), 2, 'accesshide');

        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, 0);

        // Now the list of sections..
        if ($this->formatresponsive) {
            $this->tccolumnwidth = 100; // Reset to default.
        }

        // preparing the tabs
        $count_tabs = 0;
        for ($i = 0; $i <= $max_tabs; $i++) {
            $tab_sections = str_replace(' ', '', $format_options['tab' . $i]);
            $tab_section_nums = str_replace(' ', '', $format_options['tab' . $i. '_sectionnums']);

            // check section IDs and section numbers for tabs other than tab0
            if($i > 0) {
                $section_ids = explode(',', $tab_sections);
                $section_nums = explode(',', $tab_section_nums);

                // check section IDs are valid for this course - and repair them using section numbers if they are not
                $tab_format_record = $DB->get_record('course_format_options', array('courseid' => $course->id, 'name' => 'tab'.$i));
                $ids_have_changed = false;
                $new_section_nums = array();
                foreach($section_ids as $index => $section_id) {
                    $section = $sections[$section_id];
                    $new_section_nums[] = $section->section;
                    if($section_id && !($section)) {
                        $section = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $section_nums[$index]));
                        $tab_sections = str_replace($section_id, $section->id, $tab_sections);
                        $ids_have_changed = true;
                    }
                }
                if($ids_have_changed) {
                    $DB->update_record('course_format_options', array('id' => $tab_format_record->id, 'value' => $tab_sections));
                }
                else { // all IDs are good - so check stored section numbers and restore them with the real numbers in case they have changed
                    $new_sectionnums = implode(',', $new_section_nums);
                    if($tab_section_nums !== $new_sectionnums) { // the stored section numbers seems to be different
                        if($DB->record_exists('course_format_options', array('courseid' => $course->id, 'name' => 'tab'.$i.'_sectionnums'))) {
                            $tab_format_record = $DB->get_record('course_format_options', array('courseid' => $course->id, 'name' => 'tab'.$i.'_sectionnums'));
                            $DB->update_record('course_format_options', array('id' => $tab_format_record->id, 'value' => $new_sectionnums));
                        } else {
                            $new_tab_format_record = new \stdClass();
                            $new_tab_format_record->courseid = $course->id;
                            $new_tab_format_record->format = 'qmultopics';
                            $new_tab_format_record->sectionid = 0;
                            $new_tab_format_record->name = 'tab'.$i.'_sectionnums';
                            $new_tab_format_record->value = $new_sectionnums;
                            $DB->insert_record('course_format_options', $new_tab_format_record);
                        }
                    }
                }
            }

            $tab = new stdClass();
            $tab->id = "tab" . $i;
            $tab->name = "tab" . $i;
            $tab->title = $format_options['tab' . $i . '_title'];
            $tab->sections = $tab_sections;
            $tab->section_nums = $tab_section_nums;
            $tabs[$tab->id] = $tab;
            if ($tab_sections != null) {
                $count_tabs++;
            }
        }

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
        // the assessment info block tab
        if (isset($this->tcsettings['assessment_info_tab']) &&
            $assessment_info_block_id &&
            $this->tcsettings['assessment_info_tab'] == 1) {
            $tab = new stdClass();
            $tab->id = "tab_assessment_info";
            $tab->name = 'assessment_info';
            $tab->title = $format_options['tab_assessment_info_title'];
            $tab->content = "Lorem Ipsum...";
            $tab->sections = "block_assessment_information";
            $tab_section_nums = "";
            $tabs[$tab->id] = $tab;
            // in case the assment info tab is not present but should be in the tab sequence when used fix this
            if(strlen($this->tcsettings['tab_seq']) && !strstr($this->tcsettings['tab_seq'], $tab->id)) {
                $this->tcsettings['tab_seq'] .= ','.$tab->id;
                $format_options['tab_seq'] .= ','.$tab->id;
            }
        }

        $numsections = course_get_format($course)->get_last_section_number();

        // old extratabs supported for legacy reasons
        $extratabnames = array('extratab1', 'extratab2', 'extratab3');
        $extratabs = array();

        // the old 'Assessment Information' tab
        if (isset($this->tcsettings['enable_assessmentinformation']) &&
            $this->tcsettings['enable_assessmentinformation'] == 1) {
            $tab = new stdClass();
            $tab->name = 'assessmentinformation';
            $tab->title = get_string('assessmentinformation', 'format_qmultopics');
            $tab->content = qmultopics_format_get_assessmentinformation($this->tcsettings['content_assessmentinformation']);
            $extratabs[] = $tab;
        }
        // the old extratabs
        foreach ($extratabnames as $extratabname) {
            if (isset($this->tcsettings["enable_{$extratabname}"]) &&
                $this->tcsettings["enable_{$extratabname}"] == 1) {
                $tab = new stdClass();
                $tab->name = $extratabname;
                $tab->title = format_text($this->tcsettings["title_{$extratabname}"]);
                $tab->content = format_text($this->tcsettings["content_{$extratabname}"], FORMAT_HTML, array('trusted' => true, 'noclean' => true));
                $extratabs[] = $tab;
            }
        }

        $sections = $modinfo->get_section_info_all();

        // if section 0 is to be shown before the tabs
        if ($format_options['section0_ontop']) {
            echo html_writer::start_tag('div', array('id' => 'ontop_area', 'class' => 'section0_ontop'));
            echo $this->start_section_list();
            echo html_writer::start_tag('div', array('class' => 'row bg-white'));
            echo html_writer::start_tag('div', array('id' => 'modulecontent_section_0', 'class' => 'col-12 section0_ontop'));
//            echo html_writer::start_tag('div', array('class' => 'qmultabcontent tab-content row bg-white'));
//            echo html_writer::start_tag('div', array('id' => 'modulecontent_section_0', 'class' => 'col-12 tab-pane qmultab modulecontent section0_ontop'));

            $thissection = $sections[0];
            // 0-section is displayed a little different then the others
            if ($thissection->summary or !empty($modinfo->sections[0]) or $PAGE->user_is_editing()) {
                echo $this->section_header($thissection, $course, false, 0);
                echo $this->output->custom_block_region('topiczero');
                echo  $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                echo  $this->courserenderer->course_section_add_cm_control($course, 0, 0);
                echo  $this->section_footer();
            }
            echo html_writer::end_tag('div');
            echo html_writer::end_tag('div');
            echo "<div id='ontop_spacer'<p></p><p></p></div>";
            echo $this->end_section_list();
        } else {
            echo html_writer::start_tag('div', array('id' => 'ontop_area'));
        }
        echo html_writer::end_tag('div');

        // rendering the tab navigation
        echo html_writer::start_tag('ul', array('class'=>'qmultabs nav nav-tabs row'));
        echo html_writer::start_tag('li', array('class' => 'qmultabitem nav-item'));

        // if there are no tabs show the standard "Module Content" tab and hide it otherwise
        if($count_tabs == 0) {
            echo html_writer::tag('a', get_string('modulecontent', 'format_qmultopics'),
                array('data-toggle' => 'tab', 'class' => 'qmultablink nav-link active modulecontentlink', 'href' => '#modulecontent')
            );
        } else {
            echo html_writer::tag('a', get_string('modulecontent', 'format_qmultopics'),
                array('data-toggle' => 'tab', 'class' => 'qmultablink nav-link active modulecontentlink', 'style' => 'display:none;')
            );
        }
        echo html_writer::end_tag('li');

        // add a tab that allows to pin a course to the personal dashboard
        if (function_exists('theme_qmul_add_pin_tab')) {
            theme_qmul_add_pin_tab();
        }

        // the new tab navigation
        $tab_seq = array();
        if ($format_options['tab_seq']) {
            $tab_seq = explode(',',$format_options['tab_seq']);
        }

        // if a tab sequence is found use it to arrange the tabs otherwise show them in default order
        if(sizeof($tab_seq) > 0) {
            foreach ($tab_seq as $tabid) {
                $tab = $tabs[$tabid];
                render_tab($tab);
            }
        } else {
            foreach ($tabs as $tab) {
                render_tab($tab);
            }

        }

        // the old tab navigation
        foreach ($extratabs as $extratab) {
            echo html_writer::start_tag('li', array('class'=>'qmultabitem nav-item'));
            echo html_writer::tag('a', $extratab->title, array('data-toggle'=>'tab', 'class'=>"nav-link qmultablink {$extratab->name}", 'href'=>"#{$extratab->name}"));
            echo html_writer::end_tag('li');
        }
        echo html_writer::end_tag('ul');

        echo html_writer::start_tag('div', array('class' => 'qmultabcontent tab-content row bg-white'));
        echo html_writer::start_tag('div', array('id' => 'modulecontent', 'class' => 'col-12 tab-pane qmultab modulecontent active'));
        echo $this->start_section_list();

        // General section if non-empty or not already shown on top.
        $thissection = $sections[0];
        unset($sections[0]);
        echo html_writer::start_tag('div', array('id' => 'inline_area'));
//        if (($thissection->summary or !empty($modinfo->sections[0]) or $this->userisediting)) {
        if (($thissection->summary or !empty($modinfo->sections[0]) or $this->userisediting) && $format_options['section0_ontop'] != true) {
            echo $this->section_header($thissection, $course, false, 0);
            // added topic zero block
            echo $this->output->custom_block_region('topiczero');
            echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
            echo $this->courserenderer->course_section_add_cm_control($course, $thissection->section, 0, 0);
            echo $this->section_footer();
        }
        echo html_writer::end_tag('div');

        if ($this->numsections > 0) {
            if ($this->numsections > 1) {
                if ($this->userisediting || (isset($course->coursedisplay) && $course->coursedisplay != COURSE_DISPLAY_MULTIPAGE)) {
                    // Collapsed Topics all toggles.
                    // echo $this->toggle_all();
                    if ($this->tcsettings['displayinstructions'] == 2) {
                        // Collapsed Topics instructions.
                        echo $this->display_instructions();
                    }
                }
            }
            $currentsectionfirst = false;
            if (($this->tcsettings['layoutstructure'] == 4) && (!$this->userisediting)) {
                $currentsectionfirst = true;
            }

            if (($this->tcsettings['layoutstructure'] != 3) || ($this->userisediting)) {
                $section = 1;
            } else {
                $timenow = time();
                $weekofseconds = 604800;
                $course->enddate = $course->startdate + ($weekofseconds * $this->numsections);
                $section = $this->numsections;
                $weekdate = $course->enddate;      // This should be 0:00 Monday of that week.
                $weekdate -= 7200;                 // Subtract two hours to avoid possible DST problems.
            }

            $numsections = $this->numsections; // Because we want to manipulate this for column breakpoints.
            if (($this->tcsettings['layoutstructure'] == 3) && ($this->userisediting == false)) {
                $loopsection = 1;
                $numsections = 0;
                while ($loopsection <= $this->numsections) {
                    $nextweekdate = $weekdate - ($weekofseconds);
                    if ((($thissection->uservisible ||
                                ($thissection->visible && !$thissection->available && !empty($thissection->availableinfo))) &&
                            ($nextweekdate <= $timenow)) == true) {
                        $numsections++; // Section not shown so do not count in columns calculation.
                    }
                    $weekdate = $nextweekdate;
                    $section--;
                    $loopsection++;
                }
                // Reset.
                $section = $this->numsections;
                $weekdate = $course->enddate;      // This should be 0:00 Monday of that week.
                $weekdate -= 7200;                 // Subtract two hours to avoid possible DST problems.
            }

            if ($numsections < $this->tcsettings['layoutcolumns']) {
                $this->tcsettings['layoutcolumns'] = $numsections;  // Help to ensure a reasonable display.
            }
            if (($this->tcsettings['layoutcolumns'] > 1) && ($this->mobiletheme === false)) {
                if ($this->tcsettings['layoutcolumns'] > 4) {
                    // Default in config.php (and reset in database) or database has been changed incorrectly.
                    $this->tcsettings['layoutcolumns'] = 4;

                    // Update....
                    $this->courseformat->update_qmultopics_columns_setting($this->tcsettings['layoutcolumns']);
                }

                if (($this->tablettheme === true) && ($this->tcsettings['layoutcolumns'] > 2)) {
                    // Use a maximum of 2 for tablets.
                    $this->tcsettings['layoutcolumns'] = 2;
                }

                if ($this->formatresponsive) {
                    $this->tccolumnwidth = 100 / $this->tcsettings['layoutcolumns'];
                    if ($this->tcsettings['layoutcolumnorientation'] == 2) { // Horizontal column layout.
                        $this->tccolumnwidth -= 0.5;
                        $this->tccolumnpadding = 0; // In 'px'.
                    } else {
                        $this->tccolumnwidth -= 0.2;
                        $this->tccolumnpadding = 0; // In 'px'.
                    }
                }
            } else if ($this->tcsettings['layoutcolumns'] < 1) {
                // Distributed default in plugin settings (and reset in database) or database has been changed incorrectly.
                $this->tcsettings['layoutcolumns'] = 1;

                // Update....
                $this->courseformat->update_qmultopics_columns_setting($this->tcsettings['layoutcolumns']);
            }

            echo $this->end_section_list();
            if ((!$this->formatresponsive) && ($this->tcsettings['layoutcolumnorientation'] == 1)) { // Vertical columns.
                echo html_writer::start_tag('div', array('class' => $this->get_row_class()));
            }
            echo $this->start_toggle_section_list();

            $loopsection = 0;
            $breaking = false; // Once the first section is shown we can decide if we break on another column.
            $canbreak = ($this->tcsettings['layoutcolumns'] > 1);
            $columncount = 1;
            $breakpoint = 0;
            $shownsectioncount = 0;

            if ($this->userpreference != null) {
                $this->isoldtogglepreference = $this->togglelib->is_old_preference($this->userpreference);
                if ($this->isoldtogglepreference == true) {
                    $ts1 = base_convert(substr($this->userpreference, 0, 6), 36, 2);
                    $ts2 = base_convert(substr($this->userpreference, 6, 12), 36, 2);
                    $thesparezeros = "00000000000000000000000000";
                    if (strlen($ts1) < 26) {
                        // Need to PAD.
                        $ts1 = substr($thesparezeros, 0, (26 - strlen($ts1))) . $ts1;
                    }
                    if (strlen($ts2) < 27) {
                        // Need to PAD.
                        $ts2 = substr($thesparezeros, 0, (27 - strlen($ts2))) . $ts2;
                    }
                    $tb = $ts1 . $ts2;
                } else {
                    // Check we have enough digits for the number of toggles in case this has increased.
                    $numdigits = $this->togglelib->get_required_digits($this->numsections);
                    if ($numdigits > strlen($this->userpreference)) {
                        if ($this->defaultuserpreference == 0) {
                            $dchar = $this->togglelib->get_min_digit();
                        } else {
                            $dchar = $this->togglelib->get_max_digit();
                        }
                        for ($i = strlen($this->userpreference); $i < $numdigits; $i++) {
                            $this->userpreference .= $dchar;
                        }
                    }
                    $this->togglelib->set_toggles($this->userpreference);
                }
            } else {
                $numdigits = $this->togglelib->get_required_digits($this->numsections);
                if ($this->defaultuserpreference == 0) {
                    $dchar = $this->togglelib->get_min_digit();
                } else {
                    $dchar = $this->togglelib->get_max_digit();
                }
                $this->userpreference = '';
                for ($i = 0; $i < $numdigits; $i++) {
                    $this->userpreference .= $dchar;
                }
                $this->togglelib->set_toggles($this->userpreference);
            }

            // loop through all sections and show all but hidden ones without a name
            while ($loopsection++ <= $this->numsections) {
                if (($this->tcsettings['layoutstructure'] == 3) && ($this->userisediting == false)) {
                    $nextweekdate = $weekdate - ($weekofseconds);
                }
                $thissection = $modinfo->get_section_info($section);

                if($thissection->visible != '1' && (!($thissection->name) || $thissection->name == '' || $thissection->name == 'Section 52')) {  // hidden sections with no name will be ignored
                    $section++;
                    continue;
                }
                /* Show the section if the user is permitted to access it, OR if it's not available
                  but there is some available info text which explains the reason & should display. */
                if (($this->tcsettings['layoutstructure'] != 3) || ($this->userisediting)) {
                    $showsection = $thissection->uservisible ||
                        ($thissection->visible && !$thissection->available && !empty($thissection->availableinfo));
                } else {
                    $showsection = ($thissection->uservisible ||
                            ($thissection->visible && !$thissection->available && !empty($thissection->availableinfo))) &&
                        ($nextweekdate <= $timenow);
                }
                if (($currentsectionfirst == true) && ($showsection == true)) {
                    // Show the section if we were meant to and it is the current section:....
                    $showsection = ($course->marker == $section);
                } else if (($this->tcsettings['layoutstructure'] == 4) &&
                    ($course->marker == $section) && (!$this->userisediting)) {
                    $showsection = false; // Do not reshow current section.
                }
                if (!$showsection) {
                    // Hidden section message is overridden by 'unavailable' control.
                    $testhidden = false;
                    if ($this->tcsettings['layoutstructure'] != 4) {
                        if (($this->tcsettings['layoutstructure'] != 3) || ($this->userisediting)) {
                            $testhidden = true;
                        } else if ($nextweekdate <= $timenow) {
                            $testhidden = true;
                        }
                    } else {
                        if (($currentsectionfirst == true) && ($course->marker == $section)) {
                            $testhidden = true;
                        } else if (($currentsectionfirst == false) && ($course->marker != $section)) {
                            $testhidden = true;
                        }
                    }
                    if ($testhidden) {
                        if (!$course->hiddensections && $thissection->available) {
                            $shownsectioncount++;
                            echo $this->section_hidden($thissection);
                        }
                    }
                } else {
                    $shownsectioncount++;
                    if (!$this->userisediting && (isset($course->coursedisplay) && $course->coursedisplay != COURSE_DISPLAY_MULTIPAGE)) {
                        // Display section summary only.
                        echo $this->section_summary($thissection, $course, null);
                    } else {
                        if ($this->isoldtogglepreference == true) {
                            $togglestate = substr($tb, $section, 1);
                            if ($togglestate == '1') {
                                $thissection->toggle = true;
                            } else {
                                $thissection->toggle = false;
                            }
                        } else {
                            $thissection->toggle = $this->togglelib->get_toggle_state($thissection->section);
                        }
                        echo $this->section_header($thissection, $course, false, 0);
                        if ($thissection->uservisible) {
                            echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                            echo $this->courserenderer->course_section_add_cm_control($course, $thissection->section, 0);
                        }
                        echo html_writer::end_tag('div');
                        echo $this->section_footer();
                    }
                }

                if ($currentsectionfirst == false) {
                    /* Only need to do this on the iteration when $currentsectionfirst is not true as this iteration will always
                      happen.  Otherwise you get duplicate entries in course_sections in the DB. */
                    unset($sections[$section]);
                }
                if (($this->tcsettings['layoutstructure'] != 3) || ($this->userisediting)) {
                    $section++;
                } else {
                    $section--;
                    if (($this->tcsettings['layoutstructure'] == 3) && ($this->userisediting == false)) {
                        $weekdate = $nextweekdate;
                    }
                }

                // Only check for breaking up the structure with rows if more than one column and when we output all of the sections.
                if (($canbreak === true) && ($currentsectionfirst === false)) {
                    // Only break in non-mobile themes or using a responsive theme.
                    if ((!$this->formatresponsive) || ($this->mobiletheme === false)) {
                        if ($this->tcsettings['layoutcolumnorientation'] == 1) {  // Vertical mode.
                            // This is not perfect yet as does not tally the shown sections and divide by columns.
                            if (($breaking == false) && ($showsection == true)) {
                                $breaking = true;
                                // Divide the number of sections by the number of columns.
                                $breakpoint = $numsections / $this->tcsettings['layoutcolumns'];
                            }

                            if (($breaking == true) && ($shownsectioncount >= $breakpoint) &&
                                ($columncount < $this->tcsettings['layoutcolumns'])) {
                                echo $this->end_section_list();
                                echo $this->start_toggle_section_list();
                                $columncount++;
                                // Next breakpoint is...
                                $breakpoint += $numsections / $this->tcsettings['layoutcolumns'];
                            }
                        } else {  // Horizontal mode.
                            if (($breaking == false) && ($showsection == true)) {
                                $breaking = true;
                                // The lowest value here for layoutcolumns is 2 and the maximum for shownsectioncount is 2, so :).
                                $breakpoint = $this->tcsettings['layoutcolumns'];
                            }

                            if (($breaking == true) && ($shownsectioncount >= $breakpoint) && ($loopsection < $this->numsections)) {
                                echo $this->end_section_list();
                                echo $this->start_toggle_section_list();
                                // Next breakpoint is...
                                $breakpoint += $this->tcsettings['layoutcolumns'];
                            }
                        }
                    }
                }

                if (($currentsectionfirst == true) && ($loopsection > $this->numsections)) {
                    // Now show the rest.
                    $currentsectionfirst = false;
                    $loopsection = 1;
                    $section = 1;
                }
                if ($section > $numsections) {
                    // Activities inside this section are 'orphaned', this section will be printed as 'stealth' below.
                    continue;
                }
            }

            // loop through all sections but only show hidden ones without a name
            $loopsection = 1;
            $section = 1;
            while ($loopsection++ <= $this->numsections) {
                if (($this->tcsettings['layoutstructure'] == 3) && ($this->userisediting == false)) {
                    $nextweekdate = $weekdate - ($weekofseconds);
                }
                $thissection = $modinfo->get_section_info($section);

                if($thissection->visible == '1' || ($thissection->name != '' && $thissection->name != 'Section 52')) {  // skip normal sections - show only hidden ones without a name
                    $section++;
                    continue;
                }
                /* Show the section if the user is permitted to access it, OR if it's not available
                  but there is some available info text which explains the reason & should display. */
                if (($this->tcsettings['layoutstructure'] != 3) || ($this->userisediting)) {
                    $showsection = $thissection->uservisible ||
                        ($thissection->visible && !$thissection->available && !empty($thissection->availableinfo));
                } else {
                    $showsection = ($thissection->uservisible ||
                            ($thissection->visible && !$thissection->available && !empty($thissection->availableinfo))) &&
                        ($nextweekdate <= $timenow);
                }
                if (($currentsectionfirst == true) && ($showsection == true)) {
                    // Show the section if we were meant to and it is the current section:....
                    $showsection = ($course->marker == $section);
                } else if (($this->tcsettings['layoutstructure'] == 4) &&
                    ($course->marker == $section) && (!$this->userisediting)) {
                    $showsection = false; // Do not reshow current section.
                }
                if (!$showsection) {
                    // Hidden section message is overridden by 'unavailable' control.
                    $testhidden = false;
                    if ($this->tcsettings['layoutstructure'] != 4) {
                        if (($this->tcsettings['layoutstructure'] != 3) || ($this->userisediting)) {
                            $testhidden = true;
                        } else if ($nextweekdate <= $timenow) {
                            $testhidden = true;
                        }
                    } else {
                        if (($currentsectionfirst == true) && ($course->marker == $section)) {
                            $testhidden = true;
                        } else if (($currentsectionfirst == false) && ($course->marker != $section)) {
                            $testhidden = true;
                        }
                    }
                    if ($testhidden) {
                        if (!$course->hiddensections && $thissection->available) {
                            $shownsectioncount++;
                            echo $this->section_hidden($thissection);
                        }
                    }
                } else {
                    $shownsectioncount++;
                    if (!$this->userisediting && (isset($course->coursedisplay) && $course->coursedisplay != COURSE_DISPLAY_MULTIPAGE)) {
                        // Display section summary only.
                        echo $this->section_summary($thissection, $course, null);
                    } else {
                        if ($this->isoldtogglepreference == true) {
                            $togglestate = substr($tb, $section, 1);
                            if ($togglestate == '1') {
                                $thissection->toggle = true;
                            } else {
                                $thissection->toggle = false;
                            }
                        } else {
                            $thissection->toggle = $this->togglelib->get_toggle_state($thissection->section);
                        }
                        echo $this->section_header($thissection, $course, false, 0);
                        if ($thissection->uservisible) {
                            echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                            echo $this->courserenderer->course_section_add_cm_control($course, $thissection->section, 0);
                        }
                        echo html_writer::end_tag('div');
                        echo $this->section_footer();
                    }
                }

                if ($currentsectionfirst == false) {
                    /* Only need to do this on the iteration when $currentsectionfirst is not true as this iteration will always
                      happen.  Otherwise you get duplicate entries in course_sections in the DB. */
                    unset($sections[$section]);
                }
                if (($this->tcsettings['layoutstructure'] != 3) || ($this->userisediting)) {
                    $section++;
                } else {
                    $section--;
                    if (($this->tcsettings['layoutstructure'] == 3) && ($this->userisediting == false)) {
                        $weekdate = $nextweekdate;
                    }
                }

                // Only check for breaking up the structure with rows if more than one column and when we output all of the sections.
                if (($canbreak === true) && ($currentsectionfirst === false)) {
                    // Only break in non-mobile themes or using a responsive theme.
                    if ((!$this->formatresponsive) || ($this->mobiletheme === false)) {
                        if ($this->tcsettings['layoutcolumnorientation'] == 1) {  // Vertical mode.
                            // This is not perfect yet as does not tally the shown sections and divide by columns.
                            if (($breaking == false) && ($showsection == true)) {
                                $breaking = true;
                                // Divide the number of sections by the number of columns.
                                $breakpoint = $numsections / $this->tcsettings['layoutcolumns'];
                            }

                            if (($breaking == true) && ($shownsectioncount >= $breakpoint) &&
                                ($columncount < $this->tcsettings['layoutcolumns'])) {
                                echo $this->end_section_list();
                                echo $this->start_toggle_section_list();
                                $columncount++;
                                // Next breakpoint is...
                                $breakpoint += $numsections / $this->tcsettings['layoutcolumns'];
                            }
                        } else {  // Horizontal mode.
                            if (($breaking == false) && ($showsection == true)) {
                                $breaking = true;
                                // The lowest value here for layoutcolumns is 2 and the maximum for shownsectioncount is 2, so :).
                                $breakpoint = $this->tcsettings['layoutcolumns'];
                            }

                            if (($breaking == true) && ($shownsectioncount >= $breakpoint) && ($loopsection < $this->numsections)) {
                                echo $this->end_section_list();
                                echo $this->start_toggle_section_list();
                                // Next breakpoint is...
                                $breakpoint += $this->tcsettings['layoutcolumns'];
                            }
                        }
                    }
                }

                if (($currentsectionfirst == true) && ($loopsection > $this->numsections)) {
                    // Now show the rest.
                    $currentsectionfirst = false;
                    $loopsection = 1;
                    $section = 1;
                }
                if ($section > $numsections) {
                    // Activities inside this section are 'orphaned', this section will be printed as 'stealth' below.
                    continue;
                }
            }
        }

        if ($this->userisediting and has_capability('moodle/course:update', $context)) {
            // Print stealth sections if present.
            foreach ($modinfo->get_section_info_all() as $section => $thissection) {
                if ($section <= $numsections or empty($modinfo->sections[$section])) {
                    // This is not stealth section or it is empty.
                    continue;
                }
                echo $this->stealth_section_header($section);
                echo $this->courserenderer->course_section_cm_list($course, $thissection->section, 0);
                echo $this->stealth_section_footer();
            }

            echo $this->end_section_list();
            if ((!$this->formatresponsive) && ($this->tcsettings['layoutcolumnorientation'] == 1)) { // Vertical columns.
                echo html_writer::end_tag('div');
            }

            // Increase number of sections.
            echo $this->change_number_sections($course, 0);
        } else {
            echo $this->end_section_list();
            if ((!$this->formatresponsive) && ($this->tcsettings['layoutcolumnorientation'] == 1)) { // Vertical columns.
                echo html_writer::end_tag('div');
            }
        }
        echo html_writer::end_tag('div');

        foreach ($extratabs as $extratab) {
            echo html_writer::start_tag('div', array('id' => $extratab->name, 'class' => 'tab-pane col-12 ' . $extratab->name));
            echo html_writer::tag('div', $extratab->content, array('class' => 'p-3'));
            echo html_writer::end_tag('div');
        }
        echo html_writer::end_tag('div');
    }
    public function print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused) {
        global $DB, $CFG, $PAGE, $OUTPUT;

        // allow up to 5 user tabs if nothing else is set in the config file
        $max_tabs = (isset($CFG->max_tabs) ? $CFG->max_tabs : 5);

        $this->page->requires->js_call_amd('format_qmultopics/tabs', 'init', array());

        $tabs = array();
        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();

        $context = context_course::instance($course->id);
        // Title with completion help icon.
        $completioninfo = new completion_info($course);
        echo $completioninfo->display_help_icon();
        echo $this->output->heading($this->page_title(), 2, 'accesshide');

        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, 0);

        if (empty($this->tcsettings)) {
            $this->tcsettings = $this->courseformat->get_format_options();
        }

        // preparing the tabs
        $count_tabs = 0;
        for ($i = 0; $i <= $max_tabs; $i++) {
            $tab_sections = str_replace(' ', '', $format_options['tab' . $i]);
            $tab_section_nums = str_replace(' ', '', $format_options['tab' . $i. '_sectionnums']);

            // check section IDs and section numbers for tabs other than tab0
            if($i > 0) {
                $section_ids = explode(',', $tab_sections);
                $section_nums = explode(',', $tab_section_nums);

                // check section IDs are valid for this course - and repair them using section numbers if they are not
                $tab_format_record = $DB->get_record('course_format_options', array('courseid' => $course->id, 'name' => 'tab'.$i));
                $ids_have_changed = false;
                $new_section_nums = array();
                foreach($section_ids as $index => $section_id) {
                    $section = $sections[$section_id];
                    $new_section_nums[] = $section->section;
                    if($section_id && !($section)) {
                        $section = $DB->get_record('course_sections', array('course' => $course->id, 'section' => $section_nums[$index]));
                        $tab_sections = str_replace($section_id, $section->id, $tab_sections);
                        $ids_have_changed = true;
                    }
                }
                if($ids_have_changed) {
                    $DB->update_record('course_format_options', array('id' => $tab_format_record->id, 'value' => $tab_sections));
                }
                else { // all IDs are good - so check stored section numbers and restore them with the real numbers in case they have changed
                    $new_sectionnums = implode(',', $new_section_nums);
                    if($tab_section_nums !== $new_sectionnums) { // the stored section numbers seems to be different
                        if($DB->record_exists('course_format_options', array('courseid' => $course->id, 'name' => 'tab'.$i.'_sectionnums'))) {
                            $tab_format_record = $DB->get_record('course_format_options', array('courseid' => $course->id, 'name' => 'tab'.$i.'_sectionnums'));
                            $DB->update_record('course_format_options', array('id' => $tab_format_record->id, 'value' => $new_sectionnums));
                        } else {
                            $new_tab_format_record = new \stdClass();
                            $new_tab_format_record->courseid = $course->id;
                            $new_tab_format_record->format = 'qmultopics';
                            $new_tab_format_record->sectionid = 0;
                            $new_tab_format_record->name = 'tab'.$i.'_sectionnums';
                            $new_tab_format_record->value = $new_sectionnums;
                            $DB->insert_record('course_format_options', $new_tab_format_record);
                        }
                    }
                }
            }

            $tab = new stdClass();
            $tab->id = "tab" . $i;
            $tab->name = "tab" . $i;
            $tab->title = $format_options['tab' . $i . '_title'];
            $tab->sections = $tab_sections;
            $tab->section_nums = $tab_section_nums;
            $tabs[$tab->id] = $tab;
            if ($tab_sections != null) {
                $count_tabs++;
            }
        }

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
        // the assessment info block tab
        if (isset($this->tcsettings['assessment_info_tab']) &&
            $assessment_info_block_id &&
            $this->tcsettings['assessment_info_tab'] == 1) {
            $tab = new stdClass();
            $tab->id = "tab_assessment_info";
            $tab->name = 'assessment_info';
            $tab->title = $format_options['tab_assessment_info_title'];
            $tab->content = "Lorem Ipsum...";
            $tab->sections = "block_assessment_information";
            $tab_section_nums = "";
            $tabs[$tab->id] = $tab;
            // in case the assment info tab is not present but should be in the tab sequence when used fix this
            if(strlen($this->tcsettings['tab_seq']) && !strstr($this->tcsettings['tab_seq'], $tab->id)) {
                $this->tcsettings['tab_seq'] .= ','.$tab->id;
                $format_options['tab_seq'] .= ','.$tab->id;
            }
        }

        $numsections = course_get_format($course)->get_last_section_number();

        // old extratabs supported for legacy reasons
        $extratabnames = array('extratab1', 'extratab2', 'extratab3');
        $extratabs = array();

        // the old 'Assessment Information' tab
        if (isset($this->tcsettings['enable_assessmentinformation']) &&
            $this->tcsettings['enable_assessmentinformation'] == 1) {
            $tab = new stdClass();
            $tab->name = 'assessmentinformation';
            $tab->title = get_string('assessmentinformation', 'format_qmultopics');
            $tab->content = qmultopics_format_get_assessmentinformation($this->tcsettings['content_assessmentinformation']);
            $extratabs[] = $tab;
        }
        // the old extratabs
        foreach ($extratabnames as $extratabname) {
            if (isset($this->tcsettings["enable_{$extratabname}"]) &&
                $this->tcsettings["enable_{$extratabname}"] == 1) {
                $tab = new stdClass();
                $tab->name = $extratabname;
                $tab->title = format_text($this->tcsettings["title_{$extratabname}"]);
                $tab->content = format_text($this->tcsettings["content_{$extratabname}"], FORMAT_HTML, array('trusted' => true, 'noclean' => true));
                $extratabs[] = $tab;
            }
        }

        $sections = $modinfo->get_section_info_all();

        // if section 0 is to be shown before the tabs
        if ($format_options['section0_ontop']) {
            echo html_writer::start_tag('div', array('id' => 'ontop_area', 'class' => 'section0_ontop'));
            echo $this->start_section_list();
            echo html_writer::start_tag('div', array('class' => 'row bg-white'));
            echo html_writer::start_tag('div', array('id' => 'modulecontent_section_0', 'class' => 'col-12 section0_ontop'));
//            echo html_writer::start_tag('div', array('class' => 'qmultabcontent tab-content row bg-white'));
//            echo html_writer::start_tag('div', array('id' => 'modulecontent_section_0', 'class' => 'col-12 tab-pane qmultab modulecontent section0_ontop'));

            $thissection = $sections[0];
            // 0-section is displayed a little different then the others
            if ($thissection->summary or !empty($modinfo->sections[0]) or $PAGE->user_is_editing()) {
                echo $this->section_header($thissection, $course, false, 0);
                echo $this->output->custom_block_region('topiczero');
                echo  $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                echo  $this->courserenderer->course_section_add_cm_control($course, 0, 0);
                echo  $this->section_footer();
            }
            echo html_writer::end_tag('div');
            echo html_writer::end_tag('div');
            echo "<div id='ontop_spacer'<p></p><p></p></div>";
            echo $this->end_section_list();
        } else {
            echo html_writer::start_tag('div', array('id' => 'ontop_area'));
        }
        echo html_writer::end_tag('div');

        // rendering the tab navigation
        echo html_writer::start_tag('ul', array('class'=>'qmultabs nav nav-tabs row'));
        echo html_writer::start_tag('li', array('class' => 'qmultabitem nav-item'));

        // if there are no tabs show the standard "Module Content" tab and hide it otherwise
        if($count_tabs == 0) {
            echo html_writer::tag('a', get_string('modulecontent', 'format_qmultopics'),
                array('data-toggle' => 'tab', 'class' => 'qmultablink nav-link active modulecontentlink', 'href' => '#modulecontent')
            );
        } else {
            echo html_writer::tag('a', get_string('modulecontent', 'format_qmultopics'),
                array('data-toggle' => 'tab', 'class' => 'qmultablink nav-link active modulecontentlink', 'style' => 'display:none;')
            );
        }
        echo html_writer::end_tag('li');

        // add a tab that allows to pin a course to the personal dashboard
        if (function_exists('theme_qmul_add_pin_tab')) {
            theme_qmul_add_pin_tab();
        }

        // the new tab navigation
        $tab_seq = array();
        if ($format_options['tab_seq']) {
            $tab_seq = explode(',',$format_options['tab_seq']);
        }

        // if a tab sequence is found use it to arrange the tabs otherwise show them in default order
        if(sizeof($tab_seq) > 0) {
            foreach ($tab_seq as $tabid) {
                $tab = $tabs[$tabid];
                render_tab($tab);
            }
        } else {
            foreach ($tabs as $tab) {
                render_tab($tab);
            }

        }

        // the old tab navigation
        foreach ($extratabs as $extratab) {
            echo html_writer::start_tag('li', array('class'=>'qmultabitem nav-item'));
            echo html_writer::tag('a', $extratab->title, array('data-toggle'=>'tab', 'class'=>"nav-link qmultablink {$extratab->name}", 'href'=>"#{$extratab->name}"));
            echo html_writer::end_tag('li');
        }
        echo html_writer::end_tag('ul');


        echo html_writer::start_tag('div', array('class'=>'qmultabcontent tab-content row bg-white'));
        echo html_writer::start_tag('div', array('id'=>'modulecontent', 'class'=>'col-12 tab-pane qmultab modulecontent active'));
        // Now the list of sections..
        echo $this->start_section_list();

        $numsections = course_get_format($course)->get_last_section_number();

        foreach ($modinfo->get_section_info_all() as $section => $thissection) {
            if ($section == 0) {
                // 0-section is displayed a little different then the others
                if ($thissection->summary or !empty($modinfo->sections[0]) or $PAGE->user_is_editing()) {
                    echo $this->section_header($thissection, $course, false, 0);
                    // added topic zero block
                    echo $this->output->custom_block_region('topiczero');
                    echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                    echo $this->courserenderer->course_section_add_cm_control($course, 0, 0);
                    echo $this->section_footer();
                }
                continue;
            }
            if ($section > $numsections) {
                // activities inside this section are 'orphaned', this section will be printed as 'stealth' below
                continue;
            }
            // Show the section if the user is permitted to access it, OR if it's not available
            // but there is some available info text which explains the reason & should display.
            $showsection = $thissection->uservisible ||
                ($thissection->visible && !$thissection->available &&
                    !empty($thissection->availableinfo));
            if (!$showsection) {
                // If the hiddensections option is set to 'show hidden sections in collapsed
                // form', then display the hidden section message - UNLESS the section is
                // hidden by the availability system, which is set to hide the reason.
                if (!$course->hiddensections && $thissection->available) {
                    echo $this->section_hidden($section, $course->id);
                }

                continue;
            }

            if (!$PAGE->user_is_editing() && $course->coursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                // Display section summary only.
                echo $this->section_summary($thissection, $course, null);
            } else {
                echo $this->section_header($thissection, $course, false, 0);
                if ($thissection->uservisible) {
                    echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                    echo $this->courserenderer->course_section_add_cm_control($course, $section, 0);
                }
                echo $this->section_footer();
            }
        }

        if ($PAGE->user_is_editing() and has_capability('moodle/course:update', $context)) {
            // Print stealth sections if present.
            foreach ($modinfo->get_section_info_all() as $section => $thissection) {
                if ($section <= $numsections or empty($modinfo->sections[$section])) {
                    // this is not stealth section or it is empty
                    continue;
                }
                echo $this->stealth_section_header($section);
                echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                echo $this->stealth_section_footer();
            }

            echo $this->end_section_list();

            echo $this->change_number_sections($course, 0);
        } else {
            echo $this->end_section_list();
        }

        echo html_writer::end_tag('div');

        foreach ($extratabs as $extratab) {
            echo html_writer::start_tag('div', array('id'=>$extratab->name, 'class'=>'tab-pane col-12 '.$extratab->name));
            echo html_writer::tag('div', $extratab->content, array('class'=>'p-3'));
            echo html_writer::end_tag('div');
        }
        echo html_writer::end_tag('div');

    }
    public function print_multiple_section_page0($course, $sections, $mods, $modnames, $modnamesused) {
        global $PAGE;

        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();

        $context = context_course::instance($course->id);
        // Title with completion help icon.
        $completioninfo = new completion_info($course);
        echo $completioninfo->display_help_icon();
        echo $this->output->heading($this->page_title(), 2, 'accesshide');

        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, 0);

        if (empty($this->tcsettings)) {
            $this->tcsettings = $this->courseformat->get_format_options();
        }

        $extratabnames = array('extratab1', 'extratab2', 'extratab3');
        $extratabs = array();
        if (isset($this->tcsettings['enable_assessmentinformation']) &&
            $this->tcsettings['enable_assessmentinformation'] == 1) {
            $tab = new stdClass();
            $tab->name = 'assessmentinformation';
            $tab->title = get_string('assessmentinformation', 'format_qmultopics');
            $tab->content = qmul_format_get_assessmentinformation($this->tcsettings['content_assessmentinformation']);
            $extratabs[] = $tab;
        }

        foreach ($extratabnames as $extratabname) {
            if (isset($this->tcsettings["enable_{$extratabname}"]) &&
                $this->tcsettings["enable_{$extratabname}"] == 1) {
                $tab = new stdClass();
                $tab->name = $extratabname;
                $tab->title = format_text($this->tcsettings["title_{$extratabname}"]);
                $tab->content = format_text($this->tcsettings["content_{$extratabname}"], FORMAT_HTML, array('trusted'=>true, 'noclean'=>true));
                $extratabs[] = $tab;
            }
        }


        // Add tab navigation
        echo html_writer::start_tag('ul', array('class'=>'qmultabs nav nav-tabs row'));
        echo html_writer::start_tag('li', array('class'=>'qmultabitem nav-item'));
        echo html_writer::tag('a', get_string('modulecontent', 'format_qmultopics'), array('data-toggle'=>'tab', 'class'=>'qmultablink nav-link active modulecontentlink', 'href'=>'#modulecontent'));
        echo html_writer::end_tag('li');
        if (function_exists('theme_qmul_add_pin_tab')) {
            theme_qmul_add_pin_tab();
        }
        foreach ($extratabs as $extratab) {
            echo html_writer::start_tag('li', array('class'=>'qmultabitem nav-item'));
            echo html_writer::tag('a', $extratab->title, array('data-toggle'=>'tab', 'class'=>"nav-link qmultablink {$extratab->name}", 'href'=>"#{$extratab->name}"));
            echo html_writer::end_tag('li');
        }
        echo html_writer::end_tag('ul');


        echo html_writer::start_tag('div', array('class'=>'qmultabcontent tab-content row bg-white'));
        echo html_writer::start_tag('div', array('id'=>'modulecontent', 'class'=>'col-12 tab-pane qmultab modulecontent active'));
        // Now the list of sections..
        echo $this->start_section_list();

        $numsections = course_get_format($course)->get_last_section_number();

        foreach ($modinfo->get_section_info_all() as $section => $thissection) {
            if ($section == 0) {
                // 0-section is displayed a little different then the others
                if ($thissection->summary or !empty($modinfo->sections[0]) or $PAGE->user_is_editing()) {
                    echo $this->section_header($thissection, $course, false, 0);
                    // added topic zero block
                    echo $this->output->custom_block_region('topiczero');
                    echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                    echo $this->courserenderer->course_section_add_cm_control($course, 0, 0);
                    echo $this->section_footer();
                }
                continue;
            }
            if ($section > $numsections) {
                // activities inside this section are 'orphaned', this section will be printed as 'stealth' below
                continue;
            }
            // Show the section if the user is permitted to access it, OR if it's not available
            // but there is some available info text which explains the reason & should display.
            $showsection = $thissection->uservisible ||
                ($thissection->visible && !$thissection->available &&
                    !empty($thissection->availableinfo));
            if (!$showsection) {
                // If the hiddensections option is set to 'show hidden sections in collapsed
                // form', then display the hidden section message - UNLESS the section is
                // hidden by the availability system, which is set to hide the reason.
                if (!$course->hiddensections && $thissection->available) {
                    echo $this->section_hidden($section, $course->id);
                }

                continue;
            }

            if (!$PAGE->user_is_editing() && $course->coursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                // Display section summary only.
                echo $this->section_summary($thissection, $course, null);
            } else {
                echo $this->section_header($thissection, $course, false, 0);
                if ($thissection->uservisible) {
                    echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                    echo $this->courserenderer->course_section_add_cm_control($course, $section, 0);
                }
                echo $this->section_footer();
            }
        }

        if ($PAGE->user_is_editing() and has_capability('moodle/course:update', $context)) {
            // Print stealth sections if present.
            foreach ($modinfo->get_section_info_all() as $section => $thissection) {
                if ($section <= $numsections or empty($modinfo->sections[$section])) {
                    // this is not stealth section or it is empty
                    continue;
                }
                echo $this->stealth_section_header($section);
                echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                echo $this->stealth_section_footer();
            }

            echo $this->end_section_list();

            echo $this->change_number_sections($course, 0);
        } else {
            echo $this->end_section_list();
        }

        echo html_writer::end_tag('div');

        foreach ($extratabs as $extratab) {
            echo html_writer::start_tag('div', array('id'=>$extratab->name, 'class'=>'tab-pane col-12 '.$extratab->name));
            echo html_writer::tag('div', $extratab->content, array('class'=>'p-3'));
            echo html_writer::end_tag('div');
        }
        echo html_writer::end_tag('div');

    }

    /**
     * SYNERGY LEARNING - output news section
     * @param object $course
     * @return string
     */
    public function output_news($course) {
        global $CFG, $DB, $OUTPUT, $PAGE;

        $streditsummary = get_string('editsummary');
        $context = context_course::instance($course->id);
        $o = '';

        require_once($CFG->dirroot.'/course/format/qmultopics/locallib.php');
        $subcat = $DB->get_record('course_categories', array('id' => $course->category));
        $o .= $OUTPUT->heading(format_string($subcat->name), 2, 'schoolname');
        $o .= $OUTPUT->heading(format_string($course->fullname), 2, 'coursename');

        if ($PAGE->user_is_editing() && has_capability('moodle/course:update', $context)) {
            $o .= '<p class="clearfix"><a title="' . get_string('editnewssettings', 'format_qmultopics') . '" ' .
                ' href="' . $CFG->wwwroot . '/course/format/qmultopics/newssettings.php' . '?course=' . $course->id . '"><img src="' . $OUTPUT->pix_url('t/edit') . '" ' .
                ' class="iconsmall edit" alt="' . $streditsummary . '" /></a></p>';
        }

        if ($newssettings = $DB->get_record('format_qmultopics_news', array('courseid' => $course->id))) {
            if ($newssettings->displaynews) {
                if($newssettings->usestatictext) {
                    $newstext = $newssettings->statictext;
                } else {
                    $newstext = format_qmultopics_getnews($course);
                }
                $o .= '<div class="static-text"><div class="static-padding">'.$newstext.'</div></div>';
                $o .= '<p class="clearfix" />';
            }
        }

        return $o;
    }

    /**
     * Generate the edit control items of a section
     *
     * @param stdClass $course The course entry from DB
     * @param stdClass $section The course_section entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return array of edit control items
     */
    protected function section_edit_control_items($course, $section, $onsectionpage = false) {
        global $CFG, $PAGE;

        if (!$PAGE->user_is_editing()) {
            return array();
        }

        $max_tabs = (isset($CFG->max_tabs) ? $CFG->max_tabs : 5);
        $max_tabs = ($max_tabs < 10 ? $max_tabs : 9 ); // Restrict tabs to 10 max (0...9)
        $coursecontext = context_course::instance($course->id);

        if ($onsectionpage) {
            $url = course_get_url($course, $section->section);
        } else {
            $url = course_get_url($course);
        }
        $url->param('sesskey', sesskey());

        $controls = array();

        // add move to/from top for section0 only
        if ($section->section === 0) {
            $controls['ontop'] = array(
                "icon" => 't/up',
                'name' => 'Show always on top',

                'attr' => array(
                    'tabnr' => 0,
                    'class' => 'ontop_mover',
                    'title' => 'Show always on top',
                    'data-action' => 'sectionzeroontop'
                )
            );
            $controls['inline'] = array(
                "icon" => 't/down',
                'name' => 'Show inline',

                'attr' => array(
                    'tabnr' => 0,
                    'class' => 'inline_mover',
                    'title' => 'Show inline',
                    'data-action' => 'sectionzeroinline'
                )
            );
        }

        // Insert tab moving menu items
        $itemtitle = "Move to Tab ";
        $actions = array('movetotabzero', 'movetotabone', 'movetotabtwo','movetotabthree','movetotabfour','movetotabfive','movetotabsix','movetotabseven','movetotabeight','movetotabnine','movetotabten', 'sectionzeroontop', 'sectionzeroinline');
        for($i = 0; $i <= $max_tabs; $i++) {
            $tabname = 'tab'.$i.'_title';
            $itemname = 'To Tab "'.($course->$tabname ? $course->$tabname : $i).'"';

            $controls['to_tab'.$i] = array(
                "icon" => 't/right',
                'name' => $itemname,

                'attr' => array(
                    'tabnr' => $i,
                    'class' => 'tab_mover',
                    'title' => $itemtitle,
                    'data-action' => $actions[$i]
                )
            );
        }

        $controls['no_tab'] = array(
            "icon" => 't/left',
            'name' => 'Remove from Tabs',

            'attr' => array(
                'tabnr' => 0,
                'class' => 'tab_mover',
                'title' => 'Remove from Tabs',
                'data-action' => 'removefromtabs'
            )
        );

        if ($section->section && has_capability('moodle/course:setcurrentsection', $coursecontext)) {
            if ($course->marker == $section->section) {  // Show the "light globe" on/off.
                $url->param('marker', 0);
                $markedthistopic = get_string('markedthistopic');
                $highlightoff = get_string('highlightoff');
                $controls['highlight'] = array('url' => $url, "icon" => 'i/marked',
                    'name' => $highlightoff,
                    'pixattr' => array('class' => '', 'alt' => $markedthistopic),
                    'attr' => array('class' => 'editing_highlight', 'title' => $markedthistopic,
                        'data-action' => 'removemarker'));
            } else {
                $url->param('marker', $section->section);
                $markthistopic = get_string('markthistopic');
                $highlight = get_string('highlight');
                $controls['highlight'] = array('url' => $url, "icon" => 'i/marker',
                    'name' => $highlight,
                    'pixattr' => array('class' => '', 'alt' => $markthistopic),
                    'attr' => array('class' => 'editing_highlight', 'title' => $markthistopic,
                        'data-action' => 'setmarker'));
            }
        }

        $parentcontrols = parent::section_edit_control_items($course, $section, $onsectionpage);

        // If the edit key exists, we are going to insert our controls after it.
        if (array_key_exists("edit", $parentcontrols)) {
            $merged = array();
            // We can't use splice because we are using associative arrays.
            // Step through the array and merge the arrays.
            foreach ($parentcontrols as $key => $action) {
                $merged[$key] = $action;
                if ($key == "edit") {
                    // If we have come to the edit key, merge these controls here.
                    $merged = array_merge($merged, $controls);
                }
            }

            return $merged;
        } else {
            return array_merge($controls, $parentcontrols);
        }
    }
    protected function section_edit_control_items0($course, $section, $onsectionpage = false) {
        global $CFG;
        if (!$this->userisediting) {
            return array();
        }
        $max_tabs = (isset($CFG->max_tabs) ? $CFG->max_tabs : 5);
        $max_tabs = ($max_tabs < 10 ? $max_tabs : 9 ); // Restrict tabs to 10 max (0...9)
        $coursecontext = context_course::instance($course->id);
        $sectionreturn = $onsectionpage ? $section->section : null;

        $url = course_get_url($course, $sectionreturn);
        $url->param('sesskey', sesskey());

        if (empty($this->tcsettings)) {
            $this->tcsettings = $this->courseformat->get_settings();
        }
        $controls = array();

        $url->param('marker', $section->section);

        // add move to/from top for section0 only
        if ($section->section === 0) {
            $controls['ontop'] = array(
                "icon" => 't/up',
                'name' => 'Show always on top',

                'attr' => array(
                    'tabnr' => 0,
                    'class' => 'ontop_mover',
                    'title' => 'Show always on top',
                    'data-action' => 'sectionzeroontop'
                )
            );
            $controls['inline'] = array(
                "icon" => 't/down',
                'name' => 'Show inline',

                'attr' => array(
                    'tabnr' => 0,
                    'class' => 'inline_mover',
                    'title' => 'Show inline',
                    'data-action' => 'sectionzeroinline'
                )
            );
        }

        // Insert tab moving menu items
        $itemtitle = "Move to Tab ";
        $actions = array('movetotabzero', 'movetotabone', 'movetotabtwo','movetotabthree','movetotabfour','movetotabfive','movetotabsix','movetotabseven','movetotabeight','movetotabnine','movetotabten', 'sectionzeroontop', 'sectionzeroinline');
        for($i = 1; $i <= $max_tabs; $i++) {
            $tabname = 'tab'.$i.'_title';
            $itemname = 'To Tab "'.($course->$tabname ? $course->$tabname : $i).'"';

            $controls['to_tab'.$i] = array(
                "icon" => 't/right',
                'name' => $itemname,

                'attr' => array(
                    'tabnr' => $i,
                    'class' => 'tab_mover',
                    'title' => 'tab_mover',
                    'data-action' => $actions[$i]
                )
            );
        }

        $controls['no_tab'] = array(
            "icon" => 't/left',
            'name' => 'Remove from Tabs',

            'attr' => array(
                'tabnr' => 0,
                'class' => 'tab_mover',
                'title' => 'Remove from Tabs',
                'data-action' => 'removefromtabs'
            )
        );

        if ((($this->tcsettings['layoutstructure'] == 1) || ($this->tcsettings['layoutstructure'] == 4)) &&
            $section->section && has_capability('moodle/course:setcurrentsection', $coursecontext)) {
            if ($course->marker == $section->section) {  // Show the "light globe" on/off.
                $url->param('marker', 0);
                $markedthissection = get_string('markedthissection', 'format_topcoll');
                $highlightoff = get_string('highlightoff');
                $controls['highlight'] = array('url' => $url, "icon" => 'i/marked',
                    'name' => $highlightoff,
                    'pixattr' => array('class' => '', 'alt' => $markedthissection),
                    'attr' => array('class' => 'editing_highlight', 'title' => $markedthissection,
                        'data-action' => 'removemarker'));
            } else {
                $url->param('marker', $section->section);
                $markthissection = get_string('markthissection', 'format_topcoll');
                $highlight = get_string('highlight');
                $controls['highlight'] = array('url' => $url, "icon" => 'i/marker',
                    'name' => $highlight,
                    'pixattr' => array('class' => '', 'alt' => $markthissection),
                    'attr' => array('class' => 'editing_highlight', 'title' => $markthissection,
                        'data-action' => 'setmarker'));
            }
        }

        $parentcontrols = parent::section_edit_control_items($course, $section, $onsectionpage);

        // If the edit key exists, we are going to insert our controls after it.
        if (array_key_exists("edit", $parentcontrols)) {
            $merged = array();
            // We can't use splice because we are using associative arrays.
            // Step through the array and merge the arrays.
            foreach ($parentcontrols as $key => $action) {
                $merged[$key] = $action;
                if ($key == "edit") {
                    // If we have come to the edit key, merge these controls here.
                    $merged = array_merge($merged, $controls);
                }
            }

            return $merged;
        } else {
            return array_merge($controls, $parentcontrols);
        }
    }
}

function render_tab($tab) {
    global $DB, $PAGE, $OUTPUT;
    if($tab->sections == '') {
        echo html_writer::start_tag('li', array('class'=>'qmultabitem nav-item', 'style' => 'display:none;'));
    } else {
        echo html_writer::start_tag('li', array('class'=>'qmultabitem nav-item'));
    }

    if ($tab->id == 'tab_all') {
        echo html_writer::tag('a', $tab->title,
            array('data-toggle' => 'tab', 'id' => $tab->id, 'sections' => $tab->sections, 'section_nums' => $tab->section_nums, 'class' => "tablinkx nav-link qmultablink {$tab->name}", 'href' => '#')
        );
    } else {
        $sections_array = explode(',', str_replace(' ', '', $tab->sections));
        if($sections_array[0]) {
            while ($sections_array[0] == "0") { // remove any occurences of section-0
                array_shift($sections_array);
            }
        }

        if($PAGE->user_is_editing()) {
            // get the format option record for the given tab - we need the id
            // if the record does not exist, create it first
            if(!$DB->record_exists('course_format_options', array('courseid' => $PAGE->course->id, 'name' => $tab->id.'_title'))) {
                if($tab->id === 'tab0') {
                    $newvalue = get_string('modulecontent', 'format_qmultopics');
                } else {
//                    $newvalue = 'Tab '.substr($tab->id,3);
                    $newvalue = $tab->title;
                }
                $record = new stdClass();
                $record->courseid = $PAGE->course->id;
                $record->format = 'qmultopics';
                $record->section = 0;
                $record->name = $tab->id.'_title';
//                $record->value = $newvalue;
                $record->value = $tab->title;
                $DB->insert_record('course_format_options', $record);
            }

            $format_option_tab = $DB->get_record('course_format_options', array('courseid' => $PAGE->course->id, 'name' => $tab->id.'_title'));
            $itemid = $format_option_tab->id;
        } else {
            $itemid = false;
        }

        if ($tab->id == 'tab0') {
            echo '<span data-toggle="tab" id="'.$tab->id.'" sections="'.$tab->sections.'" section_nums="'.$tab->section_nums.'" class="tablink nav-link qmultablink " tab_title="'.$tab->title.'">';
        } else {
            echo '<span data-toggle="tab" id="'.$tab->id.'" sections="'.$tab->sections.'" section_nums="'.$tab->section_nums.'" class="tablink topictab nav-link qmultablink " tab_title="'.$tab->title.'" style="'.($PAGE->user_is_editing() ? 'cursor: move;' : '').'">';
        }
        // render the tab name as inplace_editable
        $tmpl = new \core\output\inplace_editable('format_qmultopics', 'tabname', $itemid,
            $PAGE->user_is_editing(),
            format_string($tab->title), $tab->title, get_string('tabtitle_edithint', 'format_qmultopics'),  get_string('tabtitle_editlabel', 'format_qmultopics', format_string($tab->title)));
        echo $OUTPUT->render($tmpl);
        echo "</span>";
    }
    echo html_writer::end_tag('li');
}
