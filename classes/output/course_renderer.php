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

    // Assignments -----------------------------------------------------------------------------------------------------
    public function show_assignment_badges0($mod){
        global $COURSE;
        $o = '';
        $date_format = "%d %B %Y";

        // Assignments Badges

        //
        $section_assignment = false;
        foreach($COURSE->assign_data as $module) {
            if ($module->assignment == $mod->instance) {
                $section_assignment = $module;
                break;
            }
        }

        if($section_assignment) {

            // Show assignment due date
            $badge_class = '';
            $due_text = get_string('badge_due', 'format_qmultopics');
            if($section_assignment->duedate < time()) {
                $badge_class = ' badge-danger';
                $due_text = get_string('badge_duetoday', 'format_qmultopics');
                if($section_assignment->duedate < (time() - 86400)) {
                    $due_text = get_string('badge_wasdue', 'format_qmultopics');
                }
            }
            elseif($section_assignment->duedate < (time() + (60 * 60 * 24 * 14))) {
                $badge_class = ' badge-warning';
            }
            $badge_content = $due_text . userdate($section_assignment->duedate,$date_format);
            $o .= $this->html_badge($badge_content, $badge_class);

            // check if the user is able to grade (e.g. is a teacher)
            if (has_capability('mod/assign:grade', $mod->context)) {
                // show submission numbers and ungraded submissions if any
                $o .= $this->show_assign_submissions($mod, $section_assignment);
            } else {
                // show date of submission
                $o .= $this->show_assign_submission($mod);
            }
        }
        return $o;
    }
    public function show_assignment_badges($mod){
        global $COURSE;
        $o = '';

        // Assignments Badges

        //
        $section_assignment = false;
        foreach($COURSE->assign_data as $module) {
            if ($module->assignment == $mod->instance) {
                $section_assignment = $module;
                break;
            }
        }

        if($section_assignment) {

            // Show assignment due date
            $o .= $this->show_due_date_badge($section_assignment);

            // check if the user is able to grade (e.g. is a teacher)
            if (has_capability('mod/assign:grade', $mod->context)) {
                // show submission numbers and ungraded submissions if any
                $o .= $this->show_assign_submissions($mod, $section_assignment);
            } else {
                // show date of submission
                $o .= $this->show_assign_submission($mod);
            }
        }
        return $o;
    }

    public function show_due_date_badge($module) {
        $date_format = "%d %B %Y";
        $badge_class = '';
        $due_text = get_string('badge_due', 'format_qmultopics');
        if($module->duedate < time()) {
            $badge_class = ' badge-danger';
            $due_text = get_string('badge_duetoday', 'format_qmultopics');
            if($module->duedate < (time() - 86400)) {
                $due_text = get_string('badge_wasdue', 'format_qmultopics');
            }
        }
        elseif($module->duedate < (time() + (60 * 60 * 24 * 14))) {
            $badge_class = ' badge-warning';
        }
        $badge_content = $due_text . userdate($module->duedate,$date_format);
        return $this->html_badge($badge_content, $badge_class);
    }

    public function show_assign_submissions($mod, $section_assignment) {
        global $COURSE;
        // Show submissions by enrolled students
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
            // check if the assignment allows group submissions
            if ($section_assignment->teamsubmission && ! $section_assignment->requireallteammemberssubmit) {
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

            } else {
                $submissions = $this->get_assign_submissions($mod);
                $gradings = $this->get_gradings($mod);
                $ungraded = $submissions - $gradings;
                $badge_text = $pre_text
                    .$submissions
                    .$xofy
                    .count($enrolled_students)
                    .$post_text;

                if($ungraded) {
                    $badge_text =
                        $badge_text
                        .$spacer
                        .$ungraded
                        .$ungraded_text;
                }

            }

            if($badge_text) {
                return $this->html_badge($badge_text, $badge_class);
            } else {
                return '';
            }
        }
    }

    /*
     * A badge to show a student his/her submission status
     * It will display the date of a submission, a mouseover will show the time for the submission
     */
    public function show_assign_submission($mod) {
        global $DB, $USER;
        $badge_class = '';
        $badge_title = '';
        $date_format = "%d %B %Y";
        $time_format = "%d %B %Y %H:%M:%S";

        $submission = $this->get_assign_submission($mod);
        if($submission) {
            $badge_text = get_string('badge_submitted', 'format_qmultopics').userdate($submission->submit_time,$date_format);
            if($this->get_grading($mod) || $this->get_group_grading($mod)) {
//                $badge_class = 'badge-success'; // this will turn the badge green
                $badge_text .= get_string('badge_feedback', 'format_qmultopics');
            }
            $badge_title = "Submission time: " . userdate($submission->submit_time,$time_format);
        } else {
            $badge_text = get_string('badge_notsubmitted', 'format_qmultopics');
        }
        if($badge_text) {
            return $this->html_badge($badge_text, $badge_class, $badge_title);
        } else {
            return '';
        }
    }

    public function get_assign_submissions($mod) {
        global $COURSE;

        if(!isset($COURSE->assign_data)) {
            return false;
        }
        $counter = 0;
        foreach($COURSE->assign_data as $module) {
            if($module->submission_status == 'submitted' && $module->assignment == $mod->instance) {
                $counter++;
            }
        }
        return $counter;
    }

    /*
     * Trying to find a submission from a given student to a given module
     */
    public function get_assign_submission($mod) {
        global $COURSE, $USER;

        if(!isset($COURSE->assign_data)) {
            return false;
        }

        foreach($COURSE->assign_data as $module) {
            if($module->userid == $USER->id && $module->assignment == $mod->instance) {
                return $module;
            }
        }
        return false;
    }

    public function get_gradings0($mod) {
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
        return count($gradings);
    }
    public function get_gradings($mod) {
        global $COURSE;
        $gradings = 0;
        if(!isset($COURSE->assign_data)) {
            return false;
        }
        foreach($COURSE->assign_data as $module){
            if($module->submission_status == 'submitted' && $module->grade > 0 && $module->assignment == $mod->instance) {
                $gradings++;
            }
        }
        return $gradings;
    }

    public function get_grading($mod) {
        global $COURSE, $USER;

        if(!isset($COURSE->assign_data)) {
            return false;
        }
        $grading = [];
        foreach($COURSE->assign_data as $module){
            if($module->assignment == $mod->instance && $module->userid == $USER->id && $module->grade > 0) {
                    $grading[] = $module;
            }
        }
        return $grading;
    }

    public function get_group_grading0($mod) {
        global $DB, $USER;

        $sql = "
            SELECT 
            *
            FROM {assign_submission} asu
            join {grade_items} gi on gi.iteminstance = asu.assignment
            join {groups} g on g.id = asu.groupid
            join {groups_members} gm on gm.groupid = asu.groupid
            join {grade_grades} gg on (gg.itemid = gi.id and gm.userid = gg.userid)
            where asu.status = 'submitted'
            and gg.finalgrade IS NOT NULL
            and asu.groupid > 0
            and asu.assignment = $mod->instance
            and gi.courseid = $mod->course
            and gm.userid = $USER->id
        ";
        $group_grading = $DB->get_records_sql($sql);
        return $group_grading;
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
    public function show_choice_badge($mod){
        global $COURSE;

        $o = '';
        if(isset($COURSE->choice_data)) foreach($COURSE->choice_data as $choice) {
            // if the choice has a due date show it
            if($choice->choice_id == $mod->instance && $choice->duedate > 0) {
                $o .= $this->show_due_date_badge($choice);
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
            if(isset($COURSE->choice_data)) foreach($COURSE->choice_data as $choice) {
                if($choice->user_id != null && $choice->choice_id == $mod->instance) {
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

    public function show_choice_answer($mod) {
        global $COURSE, $DB, $USER;
        $badge_class = '';
        $date_format = "%d %B %Y";

        $submit_time = false;
        if(isset($COURSE->choice_data)) foreach($COURSE->choice_data as $module) {
            if($module->choice_id == $mod->instance && $module->user_id == $USER->id) {
                $submit_time = $module->submit_time;
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
    public function show_feedback_badge($mod){
        global $COURSE;
        $o = '';

        if(isset($COURSE->feedback_data)) foreach($COURSE->feedback_data as $feedback) {
            // if the feedback has a due date show it
            if($feedback->feedback_id == $mod->instance && $feedback->duedate > 0) {
                $o .= $this->show_due_date_badge($feedback);
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
            if(isset($COURSE->feedback_data)) foreach($COURSE->feedback_data as $feedback) {
                if($feedback->feedback_id == $mod->instance && $feedback->user_id != null) {
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

    public function show_feedback_completion($mod) {
        global $COURSE, $USER;
        $badge_class = '';
        $date_format = "%d %B %Y";
        $submission = false;
        if(isset($COURSE->feedback_data)) foreach($COURSE->feedback_data as $feedback) {
            if($feedback->feedback_id == $mod->instance && $feedback->user_id == $USER->id) {
                $submission = $feedback;
                break;
            }
        }
//        $submission = $DB->get_record('feedback_completed', array('feedback' => $mod->instance, 'userid' => $USER->id));
        if($submission) {
//            $badge_class = 'badge-success';
            $badge_text = get_string('badge_completed', 'format_qmultopics').userdate($submission->timemodified,$date_format);
        } else {
            $badge_text = get_string('badge_notcompleted', 'format_qmultopics');
        }
        return $this->html_badge($badge_text, $badge_class);
    }

    // Lessons -------------------------------------------------------------------------------------------------------
    public function show_lesson_badge($mod){
        global $COURSE;
        $o = '';

        if(isset($COURSE->lesson_data)) foreach($COURSE->lesson_data as $lesson) {
            // if the feedback has a due date show it
            if($lesson->lesson_id == $mod->instance && $lesson->duedate > 0) {
                $o .= $this->show_due_date_badge($lesson);
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
            if(isset($COURSE->lesson_data)) foreach($COURSE->lesson_data as $lesson) {
                if($lesson->lesson_id == $mod->instance && $lesson->user_id != null) {
                    $submissions[$lesson->user_id] = true;
                    if($lesson->completed != null) {
                        $completed[$lesson->user_id] = true;
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

    public function show_lesson_attempt($mod) {
        global $COURSE, $USER;
        $badge_class = '';
        $date_format = "%d %B %Y";
        $submission = false;
        if(isset($COURSE->lesson_data)) foreach($COURSE->lesson_data as $lesson) {
            if($lesson->lesson_id == $mod->instance && $lesson->user_id == $USER->id) {
                $submission = $lesson;
                break;
            }
        }
//        $submission = $DB->get_record('feedback_completed', array('feedback' => $mod->instance, 'userid' => $USER->id));
        if($submission) {
            if($submission->completed) {
//            $badge_class = 'badge-success';
                $badge_text = get_string('badge_completed', 'format_qmultopics').userdate($submission->completed,$date_format);
            } else {
                $badge_text = get_string('badge_attempted', 'format_qmultopics').userdate($submission->timeseen,$date_format);
            }
        } else {
            $badge_text = get_string('badge_notcompleted', 'format_qmultopics');
        }
        return $this->html_badge($badge_text, $badge_class);
    }

    // Quizzes ---------------------------------------------------------------------------------------------------------
    public function show_quiz_badge($mod){
        global $COURSE;
        $o = '';

        if(isset($COURSE->quiz_data)) foreach($COURSE->quiz_data as $quiz) {
            // if the quiz has a due date show it
            if($quiz->quiz_id == $mod->instance && $quiz->duedate > 0) {
                $o .= $this->show_due_date_badge($quiz);
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
            if(isset($COURSE->quiz_data)) foreach($COURSE->quiz_data as $quiz) {
                if($quiz->quiz_id == $mod->instance && $quiz->user_id != null) {
                    $submissions++;
                    if($quiz->state == 'finished') {
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

    public function show_quiz_attempt($mod) {
        global $COURSE, $DB, $USER;
        $o = '';
        $badge_class = '';
        $date_format = "%d %B %Y";

        $submissions = [];
        if(isset($COURSE->quiz_data)) foreach($COURSE->quiz_data as $quiz) {
            if($quiz->quiz_id == $mod->instance && $quiz->user_id == $USER->id) {
                $submissions[] = $quiz;
            }
        }

        if(count($submissions)) foreach($submissions as $submission) {
            switch($submission->state) {
                case "inprogress":
                    $badge_text = get_string('badge_inprogress', 'format_qmultopics').userdate($submission->timestart,$date_format);
                    break;
                case "finished":
                    $badge_text = get_string('badge_finished', 'format_qmultopics').userdate($submission->timefinish,$date_format);
                    break;
            }
//            $badge_text = get_string('badge_attempted', 'format_qmultopics').userdate($submission->timemodified,$date_format);
            if($badge_text) {
                $o .= $this->html_badge($badge_text, $badge_class);
            }
        } else {
            $badge_text = get_string('badge_notattempted', 'format_qmultopics');
            $o .= $this->html_badge($badge_text, $badge_class);
        }
        return $o;
    }
    public function show_quiz_attempt1($mod) {
        global $DB, $USER;
        $o = '';
        $badge_class = '';
        $date_format = "%d %B %Y";

        $submissions = $DB->get_records('quiz_attempts', array('quiz' => $mod->instance, 'userid' => $USER->id));

        if($submissions) foreach($submissions as $submission) {
            switch($submission->state) {
                case "inprogress":
                    $badge_class = '';
                    $badge_text = get_string('badge_inprogress', 'format_qmultopics').userdate($submission->submit_time,$date_format);
                    break;
                case "finished":
//                    if($submission->sumgrades > .5) {
//                        $badge_class = 'badge-success';
//                    }
                    $badge_text = get_string('badge_attempted', 'format_qmultopics').userdate($submission->submit_time,$date_format);
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


    // Supporting ------------------------------------------------------------------------------------------------------
    public function html_badge($badge_text, $badge_class = "", $title = ""){
        $o = '';
        $o .= html_writer::div($badge_text, 'badge '.$badge_class, array('title' => $title));
        $o .= get_string('badge_spacer', 'format_qmultopics');
        return $o;
    }

    public function get_course_groups(){
        global $COURSE, $DB;

        return $DB->get_records('groups', array('courseid' => $COURSE->id));
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

