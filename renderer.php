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
require_once($CFG->dirroot.'/course/format/qmultc/lib.php');
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
    public function print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused) {
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
            $tab->title = get_string('assessmentinformation', 'format_qmultc');
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
            echo html_writer::tag('a', get_string('modulecontent', 'format_qmultc'), array('data-toggle'=>'tab', 'class'=>'qmultablink nav-link active modulecontentlink', 'href'=>'#modulecontent'));
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
            if ($section > $course->numsections) {
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
                if ($section <= $course->numsections or empty($modinfo->sections[$section])) {
                    // this is not stealth section or it is empty
                    continue;
                }
                echo $this->stealth_section_header($section);
                echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                echo $this->stealth_section_footer();
            }

            echo $this->end_section_list();

            echo html_writer::start_tag('div', array('id' => 'changenumsections', 'class' => 'mdl-right'));

            // Increase number of sections.
            $straddsection = get_string('increasesections', 'moodle');
            $url = new moodle_url('/course/changenumsections.php',
                array('courseid' => $course->id,
                      'increase' => true,
                      'sesskey' => sesskey()));
            $icon = $this->output->pix_icon('t/switch_plus', $straddsection);
            echo html_writer::link($url, $icon.get_accesshide($straddsection), array('class' => 'increase-sections'));

            if ($course->numsections > 0) {
                // Reduce number of sections sections.
                $strremovesection = get_string('reducesections', 'moodle');
                $url = new moodle_url('/course/changenumsections.php',
                    array('courseid' => $course->id,
                          'increase' => false,
                          'sesskey' => sesskey()));
                $icon = $this->output->pix_icon('t/switch_minus', $strremovesection);
                echo html_writer::link($url, $icon.get_accesshide($strremovesection), array('class' => 'reduce-sections'));
            }

            echo html_writer::end_tag('div');
        } else {
            echo $this->end_section_list();
        }

        echo html_writer::end_tag('div');

        foreach ($extratabs as $extratab) {
            echo html_writer::start_tag('div', array('id'=>$extratab->name, 'class'=>'tab-pane col-12 '.$extratab->name));
            echo html_writer::tag('div', $extratab->content, array('class'=>'p-1'));
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
}
