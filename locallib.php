<?php

require_once($CFG->dirroot . '/course/lib.php');

define('FORMAT_QMULTOPICS_IMAGE_HEIGHT', 200);
define('FORMAT_QMULTOPICS_IMAGE_NAME', 'qmt1');

/*
 * Functions for all QMUL news formats
 */

/*
 * Return string with latest news
 * @param object course
 */
function format_qmultopics_getnews($course) {
    global $CFG, $DB, $OUTPUT;

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

    $context = new stdClass();

    if (!$discussions = forum_get_discussions($cm, 'p.modified DESC', true, $currentgroup, 1)) {
        return $OUTPUT->render_from_template('format_qmultopics/news', $context);
    }

    /// Actually create the listing now

    $strftimerecent = get_string('strftimerecent');

    $shownewsfull = $DB->get_field('format_qmultopics_news', 'shownewsfull', array('courseid' => $course->id));

    /// Accessibility: markup as a list.
    foreach ($discussions as $key => $discussion) {

        $discussion->subject = $discussion->name;

        $discussion->subject = format_string($discussion->subject, true, $forum->course);

        $discussion->displaymessage = !$shownewsfull ? $discussion->message : format_qmultopics_truncatehtml($discussion->message, 500);

        $discussion->discussionlink = new moodle_url('/mod/forum/discuss.php', array('d'=>$discussion->discussion));

        $discussion->fullname = fullname($discussion);
        $discussion->date = userdate($discussion->modified, $strftimerecent);
        $discussions[$key] = $discussion;
    }

    $context->discussions = array_values($discussions);

    //Links to older topics
    if (count($discussions)) {
        $context->morenewslink = new moodle_url('/mod/forum/view.php', array('f'=>$forum->id, 'showall'=>1));
    }

    return $OUTPUT->render_from_template('format_qmultopics/news', $context);
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
