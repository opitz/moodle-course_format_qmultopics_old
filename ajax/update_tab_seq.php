<?php
/**
 * Created by PhpStorm.
 * User: Matthias Opitz
 * Date: 04/10/18
 * Time: 14:46
 *
 * Updating the course format options with a new sequence in which the tabs are displayed
 */
require_once('../../../../config.php');

function update_tab_seq($sectionid, $tab_seq) {
    global $DB;

    // we only know at least one valid section ID of the course - use this to get the course ID
    $section = $DB->get_record('course_sections', array('id'=>$sectionid));

    if($DB->record_exists('course_format_options', array('courseid'=>$section->course, 'name'=>'tab_seq'))) {
        $tab_seq_record = $DB->get_record('course_format_options', array('courseid'=>$section->course, 'name'=>'tab_seq'));
        $tab_seq_record->value = $tab_seq;
        $DB->update_record('course_format_options', $tab_seq_record);
    } else {
        $tab_seq_record = new \stdClass();
        $tab_seq_record->courseid = $section->course;
        $tab_seq_record->format = 'tabbedtopics';
        $tab_seq_record->sectionid = 0;
        $tab_seq_record->name = 'tab_seq';
        $tab_seq_record->value = $tab_seq;
        $DB->insert_record('course_format_options', $tab_seq_record);
    }
    return $tab_seq;
}

if(sizeof($_POST['tab_seq']) === 0) {
    exit;
}

$tab_seq = $_POST['tab_seq'];
$sectionid = $_POST['sectionid'];

echo update_tab_seq($_POST['sectionid'], $_POST['tab_seq']);