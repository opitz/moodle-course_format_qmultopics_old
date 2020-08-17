<?php

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/renderer.php');

class qmultopics_course_renderer extends \core_course_renderer{

    /**
     * Renders HTML to display a list of course modules in a course section
     * Also displays "move here" controls in Javascript-disabled mode
     *
     * This function calls {@link core_course_renderer::course_section_cm()}
     *
     * @param stdClass $course course object
     * @param int|stdClass|section_info $section relative section number or section object
     * @param int $sectionreturn section number to return to
     * @param int $displayoptions
     * @return void
     */
    public function course_section_cm_list($course, $section, $sectionreturn = null, $displayoptions = array()) {
        global $USER;

        $output = '';
        $modinfo = get_fast_modinfo($course);
        if (is_object($section)) {
            $section = $modinfo->get_section_info($section->section);
        } else {
            $section = $modinfo->get_section_info($section);
        }
        $completioninfo = new completion_info($course);

        // check if we are currently in the process of moving a module with JavaScript disabled
        $ismoving = $this->page->user_is_editing() && ismoving($course->id);
        if ($ismoving) {
            $movingpix = new pix_icon('movehere', get_string('movehere'), 'moodle', array('class' => 'movetarget'));
            $strmovefull = strip_tags(get_string("movefull", "", "'$USER->activitycopyname'"));
        }

        // Get the list of modules visible to user (excluding the module being moved if there is one)
        $moduleshtml = array();
        if (!empty($modinfo->sections[$section->section])) {
            foreach ($modinfo->sections[$section->section] as $modnumber) {
                $mod = $modinfo->cms[$modnumber];

                if ($ismoving and $mod->id == $USER->activitycopy) {
                    // do not display moving mod
                    continue;
                }

                //$assignment_modules =
                if ($modulehtml = $this->course_section_cm_list_item($course,
                    $completioninfo, $mod, $sectionreturn, $displayoptions)) {
                    $moduleshtml[$modnumber] = $modulehtml;
                }
            }
        }

        $sectionoutput = '';
        if (!empty($moduleshtml) || $ismoving) {
            foreach ($moduleshtml as $modnumber => $modulehtml) {
                if ($ismoving) {
                    $movingurl = new moodle_url('/course/mod.php', array('moveto' => $modnumber, 'sesskey' => sesskey()));
                    $sectionoutput .= html_writer::tag('li',
                        html_writer::link($movingurl, $this->output->render($movingpix), array('title' => $strmovefull)),
                        array('class' => 'movehere'));
                }

                $sectionoutput .= $modulehtml;
            }

            if ($ismoving) {
                $movingurl = new moodle_url('/course/mod.php', array('movetosection' => $section->id, 'sesskey' => sesskey()));
                $sectionoutput .= html_writer::tag('li',
                    html_writer::link($movingurl, $this->output->render($movingpix), array('title' => $strmovefull)),
                    array('class' => 'movehere'));
            }
        }

        // Always output the section module list.
        $output .= html_writer::tag('ul', $sectionoutput, array('class' => 'section img-text'));

        return $output;
    }

    /**
     * Renders HTML to display one course module for display within a section.
     *
     * This function calls:
     * {@link core_course_renderer::course_section_cm()}
     *
     * @param stdClass $course
     * @param completion_info $completioninfo
     * @param cm_info $mod
     * @param int|null $sectionreturn
     * @param array $displayoptions
     * @return String
     */
    public function course_section_cm_list_item($course, &$completioninfo, cm_info $mod, $sectionreturn, $displayoptions = array()) {
        $output = '';
        if ($modulehtml = $this->course_section_cm($course, $completioninfo, $mod, $sectionreturn, $displayoptions)) {
            $modclasses = 'activity ' . $mod->modname . ' modtype_' . $mod->modname . ' ' . $mod->extraclasses;
            $output .= html_writer::tag('li', $modulehtml, array('class' => $modclasses, 'id' => 'module-' . $mod->id));
        }

        $output .= $this->show_badges($mod);

        return $output;
    }

    public function show_badges($mod){
        $badge_content = 'This is an assignment test!';
        $o = '';
        $date_format = "%d %B %Y";

        // Assignments Badges
        if($section_assignment = $this->get_submission_assignment($mod)) {
            // Show assignment due date
            $badge_class = 'badge-default';
            $due_text = 'Due ';
            if($section_assignment->duedate < time()) {
                $due_text = 'Was due ';
                $badge_class = ' badge-danger';
            }
            elseif($section_assignment->duedate < (time() + (60 * 60 * 24 * 14))) {
                $badge_class = ' badge-warning';
            }
            $badge_content = $due_text . userdate($section_assignment->duedate,$date_format);
            $o .= $this->html_badge($badge_content, $badge_class);

            // Show submissions by enrolled students
            $spacer = ",&nbsp;&nbsp;";
            $badge_class = 'badge-default';
            $capability = 'assign';
            $pre_text = '';
            $xofy = ' of ';
            $post_text = ' Submitted';
            $groups_text = ' Groups';
            $graded_text = ' Ungraded';
            $enrolled_students = $this->enrolled_users($capability);
            if($enrolled_students){
                // check if the assignment allows group submissions
                if ($section_assignment->teamsubmission && ! $section_assignment->requireallteammemberssubmit) {
//                    $submissions = $this->get_submissions($mod);
                    $group_gradings = $this->get_group_gradings($mod);
//                    $badge_text = $pre_text . count($submissions).$xofy.count($enrolled_students).$post_text.'&nbsp;&nbsp;'.count($gradings).' graded';
                    $course_groups = $this->get_course_groups();
                    $group_submissions = $this->get_group_submissions($mod);
                    $ungraded = (int) count($group_submissions) - count($group_gradings);
                    $badge_text = $pre_text
                        .count($group_submissions)
                        .$xofy
                        .count($course_groups)
                        .$groups_text
                        .$post_text
                    ;
                    // if there are ungraded submissions show that in the badge as well
                    if($ungraded) {
                        $badge_text =
                            $badge_text
                            .$spacer
                            .$ungraded
                            .$graded_text;
                    }

                } else {
                    $submissions = $this->get_submissions($mod);
                    $gradings = $this->get_gradings($mod);
                    $ungraded = (int) count($submissions) - count($gradings);
                    $badge_text = $pre_text
                    .count($submissions)
                    .$xofy
                    .count($enrolled_students)
                    .$post_text;

                    if($ungraded) {
                        $badge_text =
                            $badge_text
                            .$spacer
                            .$ungraded
                            .$graded_text;
                    }

                }

                if($badge_text) {
                    $o .= $this->html_badge($badge_text, $badge_class);
                }
            }

        }

        return $o;
    }

    public function get_course_groups(){
        global $COURSE, $DB;

        return $DB->get_records('groups', array('courseid' => $COURSE->id));
    }

    private function html_badge($badge_text, $badge_class = ""){
        $o = '';
        $o .= html_writer::div($badge_text, 'badge '.$badge_class);
        $o .= "&nbsp;&nbsp;";
        return $o;
    }

    public function get_submission_assignment($mod){
        global $DB;
        $o = '';
        $courseid = $mod->course;
        $sql = "
        SELECT *
            FROM mdl_course_modules cm
            join mdl_modules m on m.id = cm.module
            join mdl_assign a on a.id = cm.instance
            where 1
            and m.name = 'assign'
            and cm.course = ?
            and cm.section = ?
            and cm.module = ?
            and cm.instance = ?
";
        $submission_assignment = $DB->get_record_sql($sql, array($courseid, $mod->section, $mod->module, $mod->instance));
        return $submission_assignment;
    }

    public function get_submissions($mod) {
        global $DB;

        return $DB->get_records('assign_submission', array('assignment' => $mod->instance, 'status' => 'submitted', 'groupid' => 0));
    }

    public function get_group_submissions($mod) {
        global $DB;

//        return $DB->get_records('assign_submission', array('assignment' => $mod->instance, 'status' => 'submitted'));
        $sql = "
        select *
        from {assign_submission}
        where status = 'submitted'
        and assignment = $mod->instance
        and groupid > 0
        ";
        return $DB->get_records_sql($sql);
    }

    public function get_gradings($mod) {
        global $DB;

        $sql = "
        SELECT 
        *
        FROM {assign_submission} asu
        join {grade_items} gi on gi.iteminstance = asu.assignment
        join {grade_grades} gg on gg.itemid = gi.id
        where 1
        and asu.assignment = $mod->instance
        and gi.courseid = $mod->course
        and asu.status = 'submitted'
        and gg.finalgrade IS NOT NULL
        and asu.userid = gg.userid
        ";
        $gradings = $DB->get_records_sql($sql);
        return $gradings;
    }

    public function get_group_gradings($mod) {
        global $DB;

        $sql = "
            SELECT 
            distinct(asu.groupid)
            FROM {assign_submission} asu
            join {grade_items} gi on gi.iteminstance = asu.assignment
            join {groups} g on g.id = asu.groupid
            join {groups_members} gm on gm.groupid = asu.groupid
            join {user} u on u.id = gm.userid
            join {grade_grades} gg on (gg.itemid = gi.id and gm.userid = gg.userid)
            where asu.status = 'submitted'
            and gg.rawgrade IS NOT NULL
            and asu.groupid > 0
            and asu.assignment = $mod->instance
            and gi.courseid = $mod->course
        ";
        $group_gradings = $DB->get_records_sql($sql);
        return $group_gradings;
    }

    public function enrolled_users($capability){
        global $COURSE, $DB;

        switch($capability) {
            case 'assign':
                $capability = 'mod/assign:submit';
                break;
            case 'quiz':
                $capability = 'mod/quiz:attempt';
                break;
            case 'choice':
                $capability = 'mod/choice:choose';
                break;
            case 'feedback':
                $capability = 'mod/feedback:complete';
                break;
            default:
                // If no modname is specified, assume a count of all users is required.
                $capability = '';
        }


        $context = \context_course::instance($COURSE->id);
        $groupid = '';

        $onlyactive = true;
        $capjoin = get_enrolled_with_capabilities_join(
            $context, '', $capability, $groupid, $onlyactive);
        $sql = "SELECT DISTINCT u.id
                FROM {user} u
                $capjoin->joins
                WHERE $capjoin->wheres 
                AND u.deleted = 0
                ";
        return $DB->get_records_sql($sql, $capjoin->params);

    }
}

