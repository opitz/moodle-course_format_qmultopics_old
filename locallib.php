<?php

require_once($CFG->dirroot . '/course/lib.php');

define('FORMAT_QMULTOPICS_IMAGE_HEIGHT', 200);
define('FORMAT_QMULTOPICS_IMAGE_NAME', 'qmt1');

/*
 * Functions for all QMUL news formats
 */


/**
 * Get the image file for the news
 * @param object $context - the course context object
 * @param object $section - the course section object
 * @return stored_file | false
 */
function format_qmultopics_getimagefile($context, $section) {
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'course', 'section', $section->id);
    foreach ($files as $file) {
        $imagename = FORMAT_QMULTOPICS_IMAGE_NAME.'.';
        if (substr_compare($file->get_filename(), $imagename, 0, strlen($imagename)) == 0) {
            return $file;
        }
    }
    return false;
}

/* Return image tag to display next to news
 * @param object $course the course to get the image for
 * @param string $alttext the alttext to use for this image
 * @return mixed bool | string - false if no image; HTML to display image if it does exist
 */
function format_qmultopics_getimage($course, $alttext = '') {
    $context = context_course::instance($course->id);
    $modinfo = get_fast_modinfo($course->id);
    $section = $modinfo->get_section_info(0);
    if (! $file = format_qmultopics_getimagefile($context, $section) ) {
        return false;
    }
    $fullurl = moodle_url::make_pluginfile_url($file->get_contextid(), $file->get_component(), $file->get_filearea(),
                                               $file->get_itemid(), $file->get_filepath(), $file->get_filename());
    // Prevent browser image caching when file changes
    if (strpos($fullurl, '?') === false) {
        $fullurl .= '?ts='.$file->get_timemodified();
    } else {
        $fullurl .= '&amp;ts='.$file->get_timemodified();
    }
    $mimetype = $file->get_mimetype();
    if (!in_array($mimetype, array('image/gif', 'image/jpeg', 'image/png'))) {  // It's not an image
        return '';
    }
    $tag = '<div class="static-img"><img src="' . $fullurl . '" alt="' . s($alttext) . '" '
            . 'height="' . FORMAT_QMULTOPICS_IMAGE_HEIGHT . '" width="' . FORMAT_QMULTOPICS_IMAGE_HEIGHT . '"/></div>';
    return $tag;
}

/*
 * Return string with latest news
 * @param object course
 */
function format_qmultopics_getnews($course) {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/mod/forum/lib.php');   // We'll need this

    $text = '';

    if (!$forum = forum_get_course_forum($course->id, 'news')) {
        return '';
    }

    $modinfo = get_fast_modinfo($course);
    if (empty($modinfo->instances['forum'][$forum->id])) {
        return '';
    }
    $cm = $modinfo->instances['forum'][$forum->id];

    if (!$cm->uservisible) {
        return '';
    }

    $context = context_module::instance($cm->id);

    /// User must have perms to view discussions in that forum
    if (!has_capability('mod/forum:viewdiscussion', $context)) {
        return '';
    }

    /// First work out whether we can post to this group and if so, include a link
    $currentgroup = groups_get_activity_group($cm, true);

    /// Get all the recent discussions we're allowed to see

    if (!$discussions = forum_get_discussions($cm, 'p.modified DESC', true, $currentgroup, 1)) {
        $text .= '(' . get_string('nonews', 'forum') . ')';
    }

    /// Actually create the listing now

    $strftimerecent = get_string('strftimerecent');

    $shownewsfull = $DB->get_field('format_qmultopics_news', 'shownewsfull', array('courseid' => $course->id));

    /// Accessibility: markup as a list.
    foreach ($discussions as $discussion) {

        $discussion->subject = $discussion->name;

        $discussion->subject = format_string($discussion->subject, true, $forum->course);

        $displaymessage = $shownewsfull ? $discussion->message : format_qmultopics_truncatehtml($discussion->message, 500);

        $discussionlink = new moodle_url('/mod/forum/discuss.php', array('d'=>$discussion->discussion));
        $text .=
                '<span class="head clearfix">' .
                '<span class="info"><b>' . $discussion->subject . '</b><br />' .
                '<span class="info">' . $displaymessage . '</span><br />' .
                '<span class="link">' . html_writer::link($discussionlink, get_string('readfullpost', 'format_qmultopics')) . '</span><br /><br />' .
                '<span class="name"><i>' . fullname($discussion) . '</i></span> ' .
                '<span class="date"><i>' . userdate($discussion->modified, $strftimerecent) . '</i></span>' .
                '</span>' .
                "\n";
    }

    //Links to older topics
    if (count($discussions)) {
        $text .=
                '<span class="newslink">' .
                '<a href="' . $CFG->wwwroot . '/mod/forum/view.php?f=' . $forum->id . '&amp;showall=1">' .
                get_string('morenews', 'format_qmultopics') .
                '</a>' .
                '</span>';
    }

    return $text;
}

function format_qmultopics_truncatehtml($text, $length = 100, $ending = '...', $exact = false, $considerHtml = true) {
    if ($considerHtml) {
        // if the plain text is shorter than the maximum length, return the whole text
        if (strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
            return $text;
        }
        // splits all html-tags to scanable lines
        preg_match_all('/(<.+?>)?([^<>]*)/s', $text, $lines, PREG_SET_ORDER);
        $total_length = strlen($ending);
        $open_tags = array();
        $truncate = '';
        foreach ($lines as $line_matchings) {
            // if there is any html-tag in this line, handle it and add it (uncounted) to the output
            if (!empty($line_matchings[1])) {
                // if it's an "empty element" with or without xhtml-conform closing slash
                if (preg_match('/^<(\s*.+?\/\s*|\s*(img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param)(\s.+?)?)>$/is', $line_matchings[1])) {
                    // do nothing
                // if tag is a closing tag
                } else if (preg_match('/^<\s*\/([^\s]+?)\s*>$/s', $line_matchings[1], $tag_matchings)) {
                    // delete tag from $open_tags list
                    $pos = array_search($tag_matchings[1], $open_tags);
                    if ($pos !== false) {
                    unset($open_tags[$pos]);
                    }
                // if tag is an opening tag
                } else if (preg_match('/^<\s*([^\s>!]+).*?>$/s', $line_matchings[1], $tag_matchings)) {
                    // add tag to the beginning of $open_tags list
                    array_unshift($open_tags, strtolower($tag_matchings[1]));
                }
                // add html-tag to $truncate'd text
                $truncate .= $line_matchings[1];
            }
            // calculate the length of the plain text part of the line; handle entities as one character
            $content_length = strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', ' ', $line_matchings[2]));
            if ($total_length+$content_length> $length) {
                // the number of characters which are left
                $left = $length - $total_length;
                $entities_length = 0;
                // search for html entities
                if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', $line_matchings[2], $entities, PREG_OFFSET_CAPTURE)) {
                    // calculate the real length of all entities in the legal range
                    foreach ($entities[0] as $entity) {
                        if ($entity[1]+1-$entities_length <= $left) {
                            $left--;
                            $entities_length += strlen($entity[0]);
                        } else {
                            // no more characters left
                            break;
                        }
                    }
                }
                $truncate .= substr($line_matchings[2], 0, $left+$entities_length);
                // maximum lenght is reached, so get off the loop
                break;
            } else {
                $truncate .= $line_matchings[2];
                $total_length += $content_length;
            }
            // if the maximum length is reached, get off the loop
            if($total_length>= $length) {
                break;
            }
        }
    } else {
        if (strlen($text) <= $length) {
            return $text;
        } else {
            $truncate = substr($text, 0, $length - strlen($ending));
        }
    }
    // if the words shouldn't be cut in the middle...
    if (!$exact) {
        // ...search the last occurance of a space...
        $spacepos = strrpos($truncate, ' ');
        if (isset($spacepos)) {
            // ...and cut the text in this position
            $truncate = substr($truncate, 0, $spacepos);
        }
    }
    // add the defined ending to the text
    $truncate .= $ending;
    if($considerHtml) {
        // close all unclosed html-tags
        foreach ($open_tags as $tag) {
            $truncate .= '</' . $tag . '>';
        }
    }
    return $truncate;
}

/**
 * Stores optimised icon images in icon file area
 * Modified version of function process_new_icon in lib/gdlib.php
 *
 * @param $context
 * @param component
 * @param $itemid
 * @param $originalfile
 * @return bool
 */
function format_qmultopics_process_new_icon($context, $section, $originalfile) {
    global $CFG;

    if (empty($CFG->gdversion)) {
        return false;
    }

    if (!is_file($originalfile)) {
        return false;
    }

    $imageinfo = getimagesize($originalfile);

    if (empty($imageinfo)) {
        return false;
    }

    $image = new stdClass();
    $image->width = $imageinfo[0];
    $image->height = $imageinfo[1];
    $image->type = $imageinfo[2];

    switch ($image->type) {
        case IMAGETYPE_GIF:
            if (function_exists('imagecreatefromgif')) {
                $im = imagecreatefromgif($originalfile);
            } else {
                debugging('GIF not supported on this server');
                return false;
            }
            break;
        case IMAGETYPE_JPEG:
            if (function_exists('imagecreatefromjpeg')) {
                $im = imagecreatefromjpeg($originalfile);
            } else {
                debugging('JPEG not supported on this server');
                return false;
            }
            break;
        case IMAGETYPE_PNG:
            if (function_exists('imagecreatefrompng')) {
                $im = imagecreatefrompng($originalfile);
            } else {
                debugging('PNG not supported on this server');
                return false;
            }
            break;
        default:
            return false;
    }

    if (function_exists('imagepng')) {
        $imagefnc = 'imagepng';
        $imageext = '.png';
        $filters = PNG_NO_FILTER;
        $quality = 1;
    } else if (function_exists('imagejpeg')) {
        $imagefnc = 'imagejpeg';
        $imageext = '.jpg';
        $filters = null; // not used
        $quality = 90;
    } else {
        debugging('Jpeg and png not supported on this server, please fix server configuration');
        return false;
    }

    if (function_exists('imagecreatetruecolor') and $CFG->gdversion >= 2) {
        $im1 = imagecreatetruecolor(FORMAT_QMULTOPICS_IMAGE_HEIGHT, FORMAT_QMULTOPICS_IMAGE_HEIGHT);
        if ($image->type == IMAGETYPE_PNG and $imagefnc === 'imagepng') {
            imagealphablending($im1, false);
            $color = imagecolorallocatealpha($im1, 0, 0, 0, 127);
            imagefill($im1, 0, 0, $color);
            imagesavealpha($im1, true);
        }
    } else {
        $im1 = imagecreate(FORMAT_QMULTOPICS_IMAGE_HEIGHT, FORMAT_QMULTOPICS_IMAGE_HEIGHT);
    }

    $cx = $image->width / 2;
    $cy = $image->height / 2;

    if ($image->width < $image->height) {
        $half = floor($image->width / 2.0);
    } else {
        $half = floor($image->height / 2.0);
    }

    imagecopybicubic($im1, $im, 0, 0, $cx - $half, $cy - $half, FORMAT_QMULTOPICS_IMAGE_HEIGHT, FORMAT_QMULTOPICS_IMAGE_HEIGHT, $half * 2, $half * 2);

    ob_start();
    if (!$imagefnc($im1, NULL, $quality, $filters)) {
        // keep old icons
        ob_end_clean();
        return false;
    }
    $data = ob_get_clean();
    imagedestroy($im1);
    // Cannot delete all area files, as that would delete any files attached via the HTML editor
    format_qmultopics_delete_icon($context, $section);
    $fs = get_file_storage();
    $icon = array('contextid' => $context->id, 'component' => 'course', 'filearea' => 'section', 'itemid' => $section->id, 'filepath' => '/');
    $icon['filename'] = FORMAT_QMULTOPICS_IMAGE_NAME . $imageext;
    $fs->create_file_from_string($icon, $data);
    return true;
}

function format_qmultopics_delete_icon($context, $section) {
    if ($file = format_qmultopics_getimagefile($context, $section)) {
        $file->delete();
    }
}