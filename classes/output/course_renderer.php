<?php

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/renderer.php');

class qmultopics_course_renderer extends \core_course_renderer{

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
        switch($mod->modname) {
            case 'assign':
                return $this->show_assignment_badges($mod);
                break;
            case 'choice':
                return $this->show_choice_badge($mod);
                break;
            case 'feedback':
                return $this->show_feedback_badge($mod);
                break;
            case 'lesson':
                return $this->show_lesson_badge($mod);
                break;
            case 'quiz':
                return $this->show_quiz_badge($mod);
                break;
            default:
                return '';
        }
    }

    public function show_due_date_badge($duedate) {
        $date_format = "%d %B %Y";
        $badge_class = '';
        $due_text = get_string('badge_due', 'format_qmultopics');
        if($duedate < time()) {
            // The due date has passed - show a red badge
            $badge_class = ' badge-danger';
            $due_text = get_string('badge_duetoday', 'format_qmultopics');
            if($duedate < (time() - 86400)) {
                $due_text = get_string('badge_wasdue', 'format_qmultopics');
            }
        }
        elseif($duedate < (time() + (60 * 60 * 24 * 14))) {
            // Only 14 days left until the due date - show a yellow badge
            $badge_class = ' badge-warning';
        }
        $badge_content = $due_text . userdate($duedate,$date_format);
        return $this->html_badge($badge_content, $badge_class);
    }

    public function html_badge($badge_text, $badge_class = "", $title = ""){
        $o = '';
        $o .= html_writer::div($badge_text, 'badge '.$badge_class, array('title' => $title));
        $o .= get_string('badge_spacer', 'format_qmultopics');
        return $o;
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

    // Assignments -----------------------------------------------------------------------------------------------------
    /*
     * Show badge for assign plus additional due date badge
     */
    public function show_assignment_badges($mod){
        global $COURSE;
        $o = '';

        $assignment = false;
        foreach($COURSE->module_data as $module) {
            if ($module->assign_id == $mod->instance) {
                $assignment = $module;
                break;
            }
        }

        if($assignment) {

            // Show assignment due date
            $o .= $this->show_due_date_badge($assignment->assign_duedate);

            // check if the user is able to grade (e.g. is a teacher)
            if (has_capability('mod/assign:grade', $mod->context)) {
                // show submission numbers and ungraded submissions if any
                // check if the assignment allows group submissions
                if ($assignment->teamsubmission && ! $assignment->requireallteammemberssubmit) {
                    $o .= $this->show_assign_group_submissions($mod);
                } else {
                    $o .= $this->show_assign_submissions($mod);
                }
            } else {
                // show date of submission
                $o .= $this->show_assign_submission($mod);
            }
        }
        return $o;
    }

    /*
     * Show badge with submissions and gradings for all students
     */
    public function show_assign_submissions($mod) {
        global $COURSE;
        // Show submissions by enrolled students
        $spacer = get_string('badge_commaspacer', 'format_qmultopics');
        $badge_text = false;
        $badge_class = '';
        $capability = 'assign';
        $pre_text = '';
        $xofy = get_string('badge_xofy', 'format_qmultopics');
        $post_text = get_string('badge_submitted', 'format_qmultopics');
        $groups_text = get_string('badge_groups', 'format_qmultopics');
        $ungraded_text = get_string('badge_ungraded', 'format_qmultopics');
        $enrolled_students = $this->enrolled_users($capability);
        if(!empty($mod->availability)) {

            // Get availability information.
            $info = new \core_availability\info_module($mod);
            $restricted_students = $info->filter_user_list($enrolled_students);
        } else {
            $restricted_students = $enrolled_students;
        }

        if($enrolled_students){
            $submissions = 0;
            $gradings = 0;
            if($COURSE->module_data) foreach($COURSE->module_data as $module) {
                if($module->module_name == 'assign' &&
                    $module->assign_id == $mod->instance &&
                    $module->assign_submission_status == 'submitted') {
                    $submissions++;
                    if($module->assign_grade > 0) {
                        $gradings++;
                    }
                }
            }
            $ungraded = $submissions - $gradings;
            $badge_text = $pre_text
                .$submissions
                .$xofy
                .count($restricted_students)
                .$post_text;

            if($ungraded) {
                $badge_text =
                    $badge_text
                    .$spacer
                    .$ungraded
                    .$ungraded_text;
            }


            if($badge_text) {
                return $this->html_badge($badge_text, $badge_class);
            } else {
                return '';
            }
        }
    }

    /*
     * Show badge with submissions and gradings for all groups
     */
    public function show_assign_group_submissions($mod) {
        global $COURSE;
        // Show group submissions by enrolled students
        $spacer = get_string('badge_commaspacer', 'format_qmultopics');
        $badge_class = '';
        $capability = 'assign';
        $pre_text = '';
        $xofy = get_string('badge_xofy', 'format_qmultopics');
        $post_text = get_string('badge_submitted', 'format_qmultopics');
        $groups_text = get_string('badge_groups', 'format_qmultopics');
        $ungraded_text = get_string('badge_ungraded', 'format_qmultopics');
        $enrolled_students = $this->enrolled_users($capability);
        if($enrolled_students){
            // go through the group_data to get numbers for groups, submissions and gradings
            $course_groups_array = [];
            $group_submissions_array = [];
            $group_gradings_array = [];
            if(isset($COURSE->group_assign_data)) foreach($COURSE->group_assign_data as $record) {
                $course_groups_array[$record->groupid] = $record->groupid;
                if($record->grade < 0) {
                    $group_submissions_array[$record->groupid] = true;
                } elseif($record->grade > 0) {
                    $group_submissions_array[$record->groupid] = true;
                    $group_gradings_array[$record->groupid] = $record->grade;
                }
            }
            $course_groups = count($course_groups_array);
            $group_submissions = count($group_submissions_array);
            $group_gradings = count($group_gradings_array);
            $ungraded = $group_submissions - $group_gradings;
            $badge_text = $pre_text
                .$group_submissions
                .$xofy
                .$course_groups
                .$groups_text
                .$post_text
            ;
            // if there are ungraded submissions show that in the badge as well
            if($ungraded) {
                $badge_text =
                    $badge_text
                    .$spacer
                    .$ungraded
                    .$ungraded_text;
            }

            if($badge_text) {
                return $this->html_badge($badge_text, $badge_class);
            } else {
                return '';
            }
        }
    }

    /*
     * A badge to show the student as $USER his/her submission status
     * It will display the date of a submission, a mouseover will show the time for the submission
     */
    public function show_assign_submission($mod) {
        global $COURSE, $USER;
        $badge_class = '';
        $badge_title = '';
        $date_format = "%d %B %Y";
        $time_format = "%d %B %Y %H:%M:%S";

        $submission = false;
        foreach($COURSE->module_data as $module) {
            if($module->module_name == 'assign' && $module->assign_userid == $USER->id && $module->assign_id == $mod->instance) {
                $submission = $module;
                break;
            }
        }

        if($submission) {
            $badge_text = get_string('badge_submitted', 'format_qmultopics').userdate($submission->assign_submit_time,$date_format);
            if($this->get_grading($mod) || $this->get_group_grading($mod)) {
//                $badge_class = 'badge-success'; // this will turn the badge green
                $badge_text .= get_string('badge_feedback', 'format_qmultopics');
            }
            $badge_title = get_string('badge_submission_time_title', 'format_qmultopics') . userdate($submission->assign_submit_time,$time_format);
        } else {
            $badge_text = get_string('badge_notsubmitted', 'format_qmultopics');
        }
        if($badge_text) {
            return $this->html_badge($badge_text, $badge_class, $badge_title);
        } else {
            return '';
        }
    }

    /*
     * Return grading if the given student as $USER has been graded yet
     */
    public function get_grading($mod) {
        global $COURSE, $USER;

        $grading = [];
        if (isset($COURSE->module_data)) foreach($COURSE->module_data as $module){
            if($module->module_name == 'assign' && $module->assign_id == $mod->instance && $module->assign_userid == $USER->id && $module->assign_grade > 0) {
                $grading[] = $module;
            }
        }
        return $grading;
    }

    /*
     * Return true if the submission of the group of which the given student is a member has already been graded
     */
    public function get_group_grading($mod) {
        global $COURSE, $USER;

        if(!isset($COURSE->group_assign_data)) {
            return false;
        }
        foreach($COURSE->group_assign_data as $record){
            if($record->assignment = $mod->instance && $record->user_id = $USER->id && $record->grade > 0) {
                return true;
            }
        }
        return false;
    }

    // Choices ---------------------------------------------------------------------------------------------------------
    /*
     * Show badge for choice plus a due date badge if there is a due date
     */
    public function show_choice_badge($mod){
        global $COURSE;

        $o = '';
        if(isset($COURSE->module_data)) foreach($COURSE->module_data as $module) {
            // if the choice has a due date show it
            if($module->module_name == 'choice' && $module->choice_id == $mod->instance && $module->choice_duedate > 0) {
                $o .= $this->show_due_date_badge($module->choice_duedate);
                break;
            }
        }

        // check if the user is able to grade (e.g. is a teacher)
        if (has_capability('mod/assign:grade', $mod->context)) {
            // show submission numbers and ungraded submissions if any
            $o .= $this->show_choice_answers($mod);
        } else {
            // show date of submission
            $o .= $this->show_choice_answer($mod);
        }

        return $o;
    }

    /*
     * Show badge with choice answers of all students
     */
    public function show_choice_answers($mod) {
        global $COURSE;

        // Show answers by enrolled students
        $badge_text = '';
        $badge_class = '';
        $capability = 'choice';
        $pre_text = '';
        $xofy = ' of ';
        $post_text = get_string('badge_answered', 'format_qmultopics');
        $enrolled_students = $this->enrolled_users($capability);
        if($enrolled_students){
            $submissions = 0;
            if(isset($COURSE->module_data)) foreach($COURSE->module_data as $module) {
                if($module->module_name == 'choice' && $module->choice_userid != null && $module->choice_id == $mod->instance) {
                    $submissions++;
                }
            }
            $badge_text = $pre_text
                .$submissions
                .$xofy
                .count($enrolled_students)
                .$post_text;
        }
        if($badge_text != '') {
            return $this->html_badge($badge_text, $badge_class);
        } else {
            return '';
        }
    }

    /*
     * Show choice answer for current student as $USER
     */
    public function show_choice_answer($mod) {
        global $COURSE, $DB, $USER;
        $badge_class = '';
        $date_format = "%d %B %Y";

        $submit_time = false;
        if(isset($COURSE->module_data)) foreach($COURSE->module_data as $module) {
            if($module->module_name == 'choice' && $module->choice_id == $mod->instance && $module->choice_userid == $USER->id) {
                $submit_time = $module->choice_submit_time;
                break;
            }
        }
        if($submit_time) {
//            $badge_class = 'badge-success';
            $badge_text = get_string('badge_answered', 'format_qmultopics').userdate($submit_time,$date_format);
        } else {
            $badge_text = get_string('badge_notanswered', 'format_qmultopics');
        }
        return $this->html_badge($badge_text, $badge_class);
    }

    // Feedbacks -------------------------------------------------------------------------------------------------------
    /*
     * Show feedback badge plus a due date badge if there is a due date
     */
    public function show_feedback_badge($mod){
        global $COURSE;
        $o = '';

        if(isset($COURSE->module_data)) foreach($COURSE->module_data as $module) {
            // if the feedback has a due date show it
            if($module->module_name == 'feedback' && $module->feedback_id == $mod->instance && $module->feedback_duedate > 0) {
                $o .= $this->show_due_date_badge($module->feedback_duedate);
                break;
            }
        }

        // check if the user is able to grade (e.g. is a teacher)
        if (has_capability('mod/assign:grade', $mod->context)) {
            // show submission numbers and ungraded submissions if any
            $o .= $this->show_feedback_completions($mod);
        } else {
            // show date of submission
            $o .= $this->show_feedback_completion($mod);
        }

        return $o;
    }

    /*
     * Show badge with feedback completions of all students
     */
    public function show_feedback_completions($mod) {
        global $COURSE;

        // Show answers by enrolled students
        $badge_text = '';
        $badge_class = '';
        $capability = 'feedback';
        $pre_text = '';
        $xofy = ' of ';
        $post_text = get_string('badge_completed', 'format_qmultopics');
        $enrolled_students = $this->enrolled_users($capability);
        if($enrolled_students){
            $submissions = 0;
            if(isset($COURSE->module_data)) foreach($COURSE->module_data as $module) {
                if($module->module_name == 'feedback' && $module->feedback_id == $mod->instance && $module->feedback_userid != null) {
                    $submissions++;
                }
            }

            $badge_text = $pre_text
                .$submissions
                .$xofy
                .count($enrolled_students)
                .$post_text;

        }
        if($badge_text != '') {
            return $this->html_badge($badge_text, $badge_class);
        } else {
            return '';
        }
    }

    /*
     * Show feedback by current student as $USER
     */
    public function show_feedback_completion($mod) {
        global $COURSE, $USER;
        $badge_class = '';
        $date_format = "%d %B %Y";
        $submission = false;
        if(isset($COURSE->module_data)) foreach($COURSE->module_data as $module) {
            if($module->module_name == 'feedback' && $module->feedback_id == $mod->instance && $module->feedback_userid == $USER->id) {
                $submission = $module;
                break;
            }
        }
        if($submission) {
//            $badge_class = 'badge-success';
            $badge_text = get_string('badge_completed', 'format_qmultopics').userdate($submission->feedback_submit_time,$date_format);
        } else {
            $badge_text = get_string('badge_notcompleted', 'format_qmultopics');
        }
        return $this->html_badge($badge_text, $badge_class);
    }

    // Lessons -------------------------------------------------------------------------------------------------------
    /*
     * Show lesson badge plus additional due date badge if there is a due date
     */
    public function show_lesson_badge($mod){
        global $COURSE;
        $o = '';

        if(isset($COURSE->module_data)) foreach($COURSE->module_data as $module) {
            // if the feedback has a due date show it
            if($module->module_name == 'lesson' & $module->lesson_id == $mod->instance && $module->lesson_duedate > 0) {
                $o .= $this->show_due_date_badge($module->lesson_duedate);
                break;
            }
        }

        // check if the user is able to grade (e.g. is a teacher)
        if (has_capability('mod/assign:grade', $mod->context)) {
            // show submission numbers and ungraded submissions if any
            $o .= $this->show_lesson_attempts($mod);
        } else {
            // show date of submission
            $o .= $this->show_lesson_attempt($mod);
        }

        return $o;
    }

    /*
     * Show badge with lesson attempts of all students
     */
    public function show_lesson_attempts($mod) {
        global $COURSE;

        // Show answers by enrolled students
        $spacer = get_string('badge_commaspacer', 'format_qmultopics');
        $badge_text = '';
        $badge_class = '';
        $capability = 'lesson';
        $pre_text = '';
        $xofy = ' of ';
        $post_text = get_string('badge_attempted', 'format_qmultopics');
        $completed_text = get_string('badge_completed', 'format_qmultopics');
        $enrolled_students = $this->enrolled_users($capability);
        if($enrolled_students){
            $submissions = [];
            $completed = [];
            if(isset($COURSE->module_data)) foreach($COURSE->module_data as $module) {
                if($module->module_name == 'lesson' && $module->lesson_id == $mod->instance && $module->lesson_userid != null) {
                    $submissions[$module->lesson_userid] = true;
                    if($module->lesson_completed != null) {
                        $completed[$module->lesson_userid] = true;
                    }
                }
            }

            $badge_text = $pre_text
                .count($submissions)
                .$xofy
                .count($enrolled_students)
                .$post_text;

            if($completed > 0) {
                $badge_text =
                    $badge_text
                    .$spacer
                    .count($completed)
                    .$completed_text;
            }
        }
        if($badge_text != '') {
            return $this->html_badge($badge_text, $badge_class);
        } else {
            return '';
        }
    }

    /*
     * Show lesson attempt for the current student as $USER
     */
    public function show_lesson_attempt($mod) {
        global $COURSE, $USER;
        $badge_class = '';
        $date_format = "%d %B %Y";
        $submission = false;
        if(isset($COURSE->module_data)) foreach($COURSE->module_data as $module) {
            if($module->module_name == 'lesson' && $module->lesson_id == $mod->instance && $module->lesson_userid == $USER->id) {
                $submission = $module;
                break;
            }
        }
        if($submission) {
            if($submission->lesson_completed) {
//            $badge_class = 'badge-success';
                $badge_text = get_string('badge_completed', 'format_qmultopics').userdate($submission->lesson_completed,$date_format);
            } else {
                $badge_text = get_string('badge_attempted', 'format_qmultopics').userdate($submission->lesson_submit_time,$date_format);
            }
        } else {
            $badge_text = get_string('badge_notcompleted', 'format_qmultopics');
        }
        return $this->html_badge($badge_text, $badge_class);
    }

    // Quizzes ---------------------------------------------------------------------------------------------------------
    /*
     * Quiz badge plus a due date badge if there is a due date
     */
    public function show_quiz_badge($mod){
        global $COURSE;
        $o = '';

        if(isset($COURSE->module_data)) foreach($COURSE->module_data as $module) {
            // if the quiz has a due date show it
            if($module->quiz_id == $mod->instance && $module->quiz_duedate > 0) {
                $o .= $this->show_due_date_badge($module->quiz_duedate);
                break;
            }
        }

        // check if the user is able to grade (e.g. is a teacher)
        if (has_capability('mod/assign:grade', $mod->context)) {
            // show submission numbers and ungraded submissions if any
            $o .= $this->show_quiz_attempts($mod);
        } else {
            // show date of submission
            $o .= $this->show_quiz_attempt($mod);
        }

        return $o;
    }

    /*
     * Show quiz attempts of all stydents
     */
    public function show_quiz_attempts($mod) {
        global $COURSE;

        // Show attempts by enrolled students
        $badge_class = '';
        $capability = 'quiz';
        $pre_text = '';
        $xofy = get_string('badge_xofy', 'format_qmultopics');
        $post_text = get_string('badge_attempted', 'format_qmultopics');
        $enrolled_students = $this->enrolled_users($capability);

        if($enrolled_students){
            $submissions = 0;
            $finished = 0;
            if(isset($COURSE->module_data)) foreach($COURSE->module_data as $module) {
                if($module->module_name == 'quiz' && $module->quiz_id == $mod->instance && $module->quiz_userid != null) {
                    $submissions++;
                    if($module->quiz_state == 'finished') {
                        $finished++;
                    }
                }
            }
            $badge_text = $pre_text
                .$submissions
                .$xofy
                .count($enrolled_students)
                .$post_text
                .($submissions > 0 ? ', '.$finished.get_string('badge_finished', 'format_qmultopics') : '');
            ;

        }
        if($badge_text) {
            return $this->html_badge($badge_text, $badge_class);
        } else {
            return '';
        }
    }

    /*
     * Show quiz attempts for the current student as $USER
     */
    public function show_quiz_attempt($mod) {
        global $COURSE, $DB, $USER;
        $o = '';
        $badge_class = '';
        $date_format = "%d %B %Y";

        $submissions = [];
        if(isset($COURSE->module_data)) foreach($COURSE->module_data as $module) {
            if($module->module_name == 'quiz' && $module->quiz_id == $mod->instance && $module->quiz_userid == $USER->id) {
                $submissions[] = $module;
            }
        }

        if(count($submissions)) foreach($submissions as $submission) {
            switch($submission->quiz_state) {
                case "inprogress":
                    $badge_text = get_string('badge_inprogress', 'format_qmultopics').userdate($submission->quiz_timestart,$date_format);
                    break;
                case "finished":
                    $badge_text = get_string('badge_finished', 'format_qmultopics').userdate($submission->quiz_submit_time,$date_format);
                    break;
            }
            if($badge_text) {
                $o .= $this->html_badge($badge_text, $badge_class);
            }
        } else {
            $badge_text = get_string('badge_notattempted', 'format_qmultopics');
            $o .= $this->html_badge($badge_text, $badge_class);
        }
        return $o;
    }

}

