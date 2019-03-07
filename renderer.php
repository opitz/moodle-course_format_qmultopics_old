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
require_once($CFG->dirroot . '/course/format/renderer.php');
require_once($CFG->dirroot . '/course/format/qmultopics/lib.php');
require_once($CFG->dirroot . '/course/format/tabbedtopics/renderer.php');

/**
 * Basic renderer for qmultopics format.
 *
 * @copyright 2012 Dan Poltawski | 2019 Matthias Opitz
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class format_qmultopics_renderer extends format_tabbedtopics_renderer {

    private $courseformat = null;
    private $tcsettings;

    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);
        $this->courseformat = course_get_format($page->course);
        $this->tcsettings = $this->courseformat->get_format_options();
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

    // Prepare standard tabs with added assessment info tab and extratabs
    public function prepare_tabs($course, $format_options, $sections) {
        // Get the standard tabs
        $tabs = parent::prepare_tabs($course, $format_options, $sections);

        // Merge old extratabs
        $tabs = array_merge($tabs,$this->prepare_extratabs($course, $format_options));

        // Merge tab(s) for assessment information (old and new)
        $tabs = array_merge($tabs, $this->prepare_assessment_tabs($course, $format_options));

        $this->tabs = $tabs;
        return $tabs;
    }

    // prepare the old extratabs for legacy reasons
    public function prepare_extratabs($course, $format_options) {
        $extratabnames = array('extratab1', 'extratab2', 'extratab3');
        $extratabs = array();
        foreach ($extratabnames as $extratabname) {
            if (isset($this->tcsettings["enable_{$extratabname}"]) &&
                $this->tcsettings["enable_{$extratabname}"] == 1) {
                $tab = new stdClass();
                $tab->id = $extratabname;
                $tab->name = $extratabname;
                $tab->title = $this->tcsettings["title_{$extratabname}"];
                $tab->generic_title = ucfirst($extratabname);
                $tab->sections = $extratabname;
                $tab->section_nums = "";
                $tab->content = format_text($this->tcsettings["content_{$extratabname}"], FORMAT_HTML, array('trusted' => true, 'noclean' => true));
                $extratabs[$tab->id] = $tab;
            }
        }
        return $extratabs;
    }

    // Prepare the assessment Information tabs (old and new)
    public function prepare_assessment_tabs($course, $format_options) {
        global $CFG, $DB, $PAGE;

        $tabs = array();

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
        if (isset($this->tcsettings['assessment_info_block_tab']) &&
            $assessment_info_block_id &&
            $this->tcsettings['assessment_info_block_tab'] == 1) {
            $tab = new stdClass();
            $tab->id = "tab_assessment_info_block";
            $tab->name = 'assessment_info_block';
            $tab->title = $this->tcsettings['tab_assessment_info_block_title'];
            $tab->generic_title = get_string('tab_assessment_info_title', 'format_qmultopics');
            $tab->content = ''; // not required - we are only interested in the tab
            $tab->sections = "block_assessment_information";
            $tab->section_nums = "";
            $tabs[$tab->id] = $tab;
            // in case the assment info tab is not present but should be in the tab sequence when used fix this
            if(strlen($this->tcsettings['tab_seq']) && !strstr($this->tcsettings['tab_seq'], $tab->id)) {
                $this->tcsettings['tab_seq'] .= ','.$tab->id;
//                $format_options['tab_seq'] .= ','.$tab->id;
            }
        }

        // the old assessment info tab - as a new tab
        if (isset($this->tcsettings['enable_assessmentinformation']) &&
            $this->tcsettings['enable_assessmentinformation'] == 1) {
            $tab = new stdClass();
            $tab->id = "tab_assessment_information";
            $tab->name = 'assessment_info';
            $tab->title = $this->tcsettings['tab_assessment_information_title'];
            $tab->generic_title = get_string('tab_assessment_information_title', 'format_qmultopics');
            // Get the synergy assessment info and store the result as content for this tab
            $tab->content = $this->get_assessmentinformation($this->tcsettings['content_assessmentinformation']);
            $tab->sections = "assessment_information";
            $tab->section_nums = "";
            $tabs[$tab->id] = $tab;
            // in case the assment info tab is not present but should be in the tab sequence when used fix this
            if(strlen($this->tcsettings['tab_seq']) && !strstr($this->tcsettings['tab_seq'], $tab->id)) {
                $this->tcsettings['tab_seq'] .= ','.$tab->id;
//                $format_options['tab_seq'] .= ','.$tab->id;
            }
        }

        return $tabs;
    }

    // Get the content for the assessment information section
    public function get_assessmentinformation($content) {
        global $CFG, $DB, $COURSE, $OUTPUT, $USER;

        $output = html_writer::tag('div', format_text($content), array('class'=>'assessmentinfo col-12 mb-3'));

        $assignments = $this->get_assignments();

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

    // Get assignments for assessment information
    public function get_assignments() {
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

    // Render a standard tab or an extratab - as long as they are still around...
    public function render_tab($tab){
        if(!isset($tab)) {
            return false;
        }
        // as long as there are still old extratabs around we need to treat them slightly different from normal tabs
        // this overriding function may be removed once extratabs are gone
        if(strstr($tab->id, 'extratab')) {
            return $this->render_extratab($tab);
        } else {
            return parent::render_tab($tab);
        }
    }

    // Render an extratab
    public function render_extratab($tab) {
        global $DB, $PAGE, $OUTPUT;
        $o = '';
        if($tab->sections == '') {
            $o .= html_writer::start_tag('li', array('class'=>'tabitem nav-item', 'style' => 'display:none;'));
        } else {
            $o .= html_writer::start_tag('li', array('class'=>'tabitem nav-item'));
        }

        $sections_array = explode(',', str_replace(' ', '', $tab->sections));
        if($sections_array[0]) {
            while ($sections_array[0] == "0") { // remove any occurences of section-0
                array_shift($sections_array);
            }
        }

        if($PAGE->user_is_editing()) {
            // get the format option record for the given tab - we need the id
            // if the record does not exist, create it first
            if(!$DB->record_exists('course_format_options', array('courseid' => $PAGE->course->id, 'name' => 'title_'.$tab->id))) {
                $record = new stdClass();
                $record->courseid = $PAGE->course->id;
                $record->format = 'qmultopics';
                $record->section = 0;
                $record->name = 'title_'.$tab->id;
                $record->value = $tab->id;
                $DB->insert_record('course_format_options', $record);
            }

            $format_option_tab = $DB->get_record('course_format_options', array('courseid' => $PAGE->course->id, 'name' => 'title_'.$tab->id));
            $itemid = $format_option_tab->id;
        } else {
            $itemid = false;
        }

        if ($tab->id == 'tab0') {
            $o .= '<span 
                data-toggle="tab" id="'.$tab->id.'" 
                sections="'.$tab->sections.'" 
                section_nums="'.$tab->section_nums.'" 
                class="tablink nav-link " 
                tab_title="'.$tab->title.'", 
                generic_title = "'.$tab->generic_title.'"
                >';
        } else {
            $o .= '<span 
                data-toggle="tab" id="'.$tab->id.'" 
                sections="'.$tab->sections.'" 
                section_nums="'.$tab->section_nums.'" 
                class="tablink topictab nav-link " 
                tab_title="'.$tab->title.'" 
                generic_title = "'.$tab->generic_title.'" 
                style="'.($PAGE->user_is_editing() ? 'cursor: move;' : '').'">';
        }
        // render the tab name as inplace_editable
        $tmpl = new \core\output\inplace_editable('format_tabbedtopics', 'tabname', $itemid,
            $PAGE->user_is_editing(),
            format_string($tab->title), $tab->title, get_string('tabtitle_edithint', 'format_tabbedtopics'),  get_string('tabtitle_editlabel', 'format_tabbedtopics', format_string($tab->title)));
        $o .= $OUTPUT->render($tmpl);
        $o .= "</span>";
        $o .= html_writer::end_tag('li');
        return $o;
    }

    // Render sections with added assessment info and extratab sections
    public function render_sections($course, $sections, $format_options, $modinfo, $numsections){
        $o = '';
        $o .= $this->render_assessment_section($format_options);
        $o .= $this->render_extratab_sections($format_options);
        $o .= parent::render_sections($course, $sections, $format_options, $modinfo, $numsections);
        return $o;
    }

    // Render extratab sections as long as they are still around...
    public function render_extratab_sections($format_options) {
        $extratabnames = array('extratab1', 'extratab2', 'extratab3');
        $o = '';
        foreach ($extratabnames as $extratabname) {
            if ($format_options['enable_'.$extratabname]) {
                $o .= html_writer::start_tag('li', array('id' => $extratabname, 'section-id' => $extratabname, 'class' => 'extratab section', 'style' => 'display: none;'));

                // show the extratab title
                $o .= html_writer::start_tag('h3', array('class' => 'sectionname'));
                $o .= $this->tabs[$extratabname]->title;
                $o .= html_writer::end_tag('h3');
                // show the content
                $o .= html_writer::start_tag('div', array('class' => 'content'));
                $o .= html_writer::start_tag('div', array('class' => 'summary'));
                $o .= $this->tabs[$extratabname]->content;
                $o .= html_writer::end_tag('div');
                $o .= html_writer::end_tag('div');

                $o .= html_writer::end_tag('li');
            }
        }
        return $o;
    }

    // Render section for assessment information
    public function render_assessment_section($format_options) {
        $o = '';
        if ($format_options['enable_assessmentinformation']) {
            // If the option to merge assessment information add a specific class as indicator for JS
            if ($format_options['assessment_info_block_tab'] == '2') {
                $o .= html_writer::start_tag('li', array('id' => 'content_assessmentinformation_area', 'section-id' => 'assessment_information', 'class' => 'section merge_assessment_info', 'style' => 'display: none;'));
            } else {
                $o .= html_writer::start_tag('li', array('id' => 'content_assessmentinformation_area', 'section-id' => 'assessment_information', 'class' => 'section', 'style' => 'display: none;'));
            }
            $o .= html_writer::start_tag('div', array('class' => 'content'));
            $o .= html_writer::start_tag('div', array('class' => 'summary'));
            $o .= $this->tabs['tab_assessment_information']->content;
            $o .= html_writer::end_tag('div');
            $o .= html_writer::end_tag('div');
            $o .= html_writer::end_tag('li');
        }
        return $o;
    }

    protected function start_section_list() {
        $o = '';
        $o .= html_writer::start_tag('div', array('id'=>'modulecontent', 'class'=>'tab-pane modulecontent active'));
        $o .= html_writer::start_tag('ul', array('class' => 'topics'));
        return $o;
    }

    protected function end_section_list() {
        $o = '';
        $o .= html_writer::end_tag('ul');
        $o .= html_writer::end_tag('div');
        return $o;
    }

}

