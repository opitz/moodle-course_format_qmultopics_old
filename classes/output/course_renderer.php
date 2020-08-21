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
    public function course_section_cm_list0($course, $section, $sectionreturn = null, $displayoptions = array()) {
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
    public function course_section_cm_list_item0($course, &$completioninfo, cm_info $mod, $sectionreturn, $displayoptions = array()) {
        $output = '';
        if ($modulehtml = $this->course_section_cm($course, $completioninfo, $mod, $sectionreturn, $displayoptions)) {
            $modclasses = 'activity ' . $mod->modname . ' modtype_' . $mod->modname . ' ' . $mod->extraclasses;
            $output .= html_writer::tag('li', $modulehtml, array('class' => $modclasses, 'id' => 'module-' . $mod->id));
        }

//        $output .= $this->show_badges($mod);

        return $output;
    }

    /**
     * Renders HTML to display one course module in a course section
     *
     * This includes link, content, availability, completion info and additional information
     * that module type wants to display (i.e. number of unread forum posts)
     *
     * This function calls:
     * {@link core_course_renderer::course_section_cm_name()}
     * {@link core_course_renderer::course_section_cm_text()}
     * {@link core_course_renderer::course_section_cm_availability()}
     * {@link core_course_renderer::course_section_cm_completion()}
     * {@link course_get_cm_edit_actions()}
     * {@link core_course_renderer::course_section_cm_edit_actions()}
     *
     * @param stdClass $course
     * @param completion_info $completioninfo
     * @param cm_info $mod
     * @param int|null $sectionreturn
     * @param array $displayoptions
     * @return string
     */
    public function course_section_cm($course, &$completioninfo, cm_info $mod, $sectionreturn, $displayoptions = array()) {
        $output = '';
        // We return empty string (because course module will not be displayed at all)
        // if:
        // 1) The activity is not visible to users
        // and
        // 2) The 'availableinfo' is empty, i.e. the activity was
        //     hidden in a way that leaves no info, such as using the
        //     eye icon.
        if (!$mod->is_visible_on_course_page()) {
            return $output;
        }

        $indentclasses = 'mod-indent';
        if (!empty($mod->indent)) {
            $indentclasses .= ' mod-indent-'.$mod->indent;
            if ($mod->indent > 15) {
                $indentclasses .= ' mod-indent-huge';
            }
        }

        $output .= html_writer::start_tag('div');

        if ($this->page->user_is_editing()) {
            $output .= course_get_cm_move($mod, $sectionreturn);
        }

        $output .= html_writer::start_tag('div', array('class' => 'mod-indent-outer'));

        // This div is used to indent the content.
        $output .= html_writer::div('', $indentclasses);

        // Start a wrapper for the actual content to keep the indentation consistent
        $output .= html_writer::start_tag('div');

        // Display the link to the module (or do nothing if module has no url)
        $cmname = $this->course_section_cm_name($mod, $displayoptions);

        if (!empty($cmname)) {
            // Start the div for the activity title, excluding the edit icons.
            $output .= html_writer::start_tag('div', array('class' => 'activityinstance'));
            $output .= $cmname;


            // Module can put text after the link (e.g. forum unread)
            $output .= $mod->afterlink;

            // Closing the tag which contains everything but edit icons. Content part of the module should not be part of this.
            $output .= html_writer::end_tag('div'); // .activityinstance
        }

        // If there is content but NO link (eg label), then display the
        // content here (BEFORE any icons). In this case cons must be
        // displayed after the content so that it makes more sense visually
        // and for accessibility reasons, e.g. if you have a one-line label
        // it should work similarly (at least in terms of ordering) to an
        // activity.
        $contentpart = $this->course_section_cm_text($mod, $displayoptions);
        $url = $mod->url;
        if (empty($url)) {
            $output .= $contentpart;
        }

        $modicons = '';
        if ($this->page->user_is_editing()) {
            $editactions = course_get_cm_edit_actions($mod, $mod->indent, $sectionreturn);
            $modicons .= ' '. $this->course_section_cm_edit_actions($editactions, $mod, $displayoptions);
            $modicons .= $mod->afterediticons;
        }

        $modicons .= $this->course_section_cm_completion($course, $completioninfo, $mod, $displayoptions);

        if (!empty($modicons)) {
            $output .= html_writer::span($modicons, 'actions');
        }

        // Show availability info (if module is not available).
        $output .= $this->course_section_cm_availability($mod, $displayoptions);

        // If there is content AND a link, then display the content here
        // (AFTER any icons). Otherwise it was displayed before
        if (!empty($url)) {
            $output .= $contentpart;
        }

        // amending badges
        $output .= html_writer::start_div();
        $output .= $this->show_badges($mod);
        $output .= html_writer::end_div();

        $output .= html_writer::end_tag('div'); // $indentclasses

        // End of indentation div.
        $output .= html_writer::end_tag('div');

        $output .= html_writer::end_tag('div');
        return $output;
    }

    public function show_badges($mod){
        global $USER;
        $o = '';
        $date_format = "%d %B %Y";

        // Assignments Badges
        if($section_assignment = $this->get_submission_assignment($mod)) {
            // Show assignment due date
            $badge_class = 'badge-default';
            $badge_class = 'badge-info';
            $due_text = get_string('badge_due', 'format_qmultopics');
            if($section_assignment->duedate < time()) {
                $due_text = get_string('badge_wasdue', 'format_qmultopics');
                $badge_class = ' badge-danger';
            }
            elseif($section_assignment->duedate < (time() + (60 * 60 * 24 * 14))) {
                $badge_class = ' badge-warning';
            }
            $badge_content = $due_text . userdate($section_assignment->duedate,$date_format);
            $o .= $this->html_badge($badge_content, $badge_class);

            // check if the user is able to grade (e.g. is a teacher)
            if (has_capability('mod/assign:grade', $mod->context)) {
                // show submission numbers and ungraded submissions if any
                $o .= $this->show_submissions($mod, $section_assignment);
            } else {
                // show date of submission
                $o .= $this->show_submission_date($mod, $section_assignment);
            }
        }
        return $o;
    }

    public function show_submissions($mod, $section_assignment) {
        // Show submissions by enrolled students
        $spacer = get_string('badge_commaspacer', 'format_qmultopics');
        $badge_class = 'badge-info';
        $capability = 'assign';
        $pre_text = '';
        $xofy = ' of ';
        $post_text = get_string('badge_submitted', 'format_qmultopics');
        $groups_text = get_string('badge_groups', 'format_qmultopics');
        $graded_text = get_string('badge_ungraded', 'format_qmultopics');
        $enrolled_students = $this->enrolled_users($capability);
        if($enrolled_students){
            // check if the assignment allows group submissions
            if ($section_assignment->teamsubmission && ! $section_assignment->requireallteammemberssubmit) {
                $group_gradings = $this->get_group_gradings($mod);
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
                return $this->html_badge($badge_text, $badge_class);
            } else {
                return '';
            }
        }
    }

    public function show_submission_date($mod, $section_assignment) {
        global $DB, $USER;
        $badge_class = 'badge-info';
        $date_format = "%d %B %Y";

        $submission = $DB->get_record('assign_submission', array('status' => 'submitted', 'assignment' => $mod->instance, 'userid' => $USER->id));
        if($submission) {
            $badge_class = 'badge-success';
            $badge_text = get_string('badge_submitted', 'format_qmultopics').userdate($submission->timemodified,$date_format);
        } else {
            $badge_text = get_string('badge_notsubmitted', 'format_qmultopics');
        }
        if($badge_text) {
            return $this->html_badge($badge_text, $badge_class);
        } else {
            return '';
        }
    }

    public function get_course_groups(){
        global $COURSE, $DB;

        return $DB->get_records('groups', array('courseid' => $COURSE->id));
    }

    public function html_badge($badge_text, $badge_class = ""){
        $o = '';
        $o .= html_writer::div($badge_text, 'badge '.$badge_class);
        $o .= get_string('badge_spacer', 'format_qmultopics');
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

