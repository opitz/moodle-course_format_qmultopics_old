<?php
require_once($CFG->dirroot . '/course/lib.php');

define('FORMAT_QMULTOPICS_IMAGE_HEIGHT', 200);
define('FORMAT_QMULTOPICS_IMAGE_NAME', 'qmt1');

/**
 * Functions for all QMUL news formats
 *
 * Return string with latest news
 * @param object course
 */
function format_qmultopics_getnews($course) {
    global $CFG, $DB, $OUTPUT;

    require_once($CFG->dirroot . '/mod/forum/lib.php');   // We'll need this.

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

    // User must have perms to view discussions in that forum.
    if (!has_capability('mod/forum:viewdiscussion', $context)) {
        return '';
    }

    // First work out whether we can post to this group and if so, include a link.
    $currentgroup = groups_get_activity_group($cm, true);

    // Get all the recent discussions we're allowed to see.
    $context = new stdClass();
    if (!$discussions = forum_get_discussions($cm, 'p.modified DESC', true, $currentgroup, 1)) {
        return $OUTPUT->render_from_template('format_qmultopics/news', $context);
    }

    // Actually create the listing now.
    $strftimerecent = get_string('strftimerecent');
    $shownewsfull = $DB->get_field('format_qmultopics_news', 'shownewsfull', array('courseid' => $course->id));

    // Accessibility: markup as a list.
    foreach ($discussions as $key => $discussion) {
        $discussion->subject = $discussion->name;
        $discussion->subject = format_string($discussion->subject, true, $forum->course);
        $discussion->displaymessage = !$shownewsfull ? $discussion->message :
            format_qmultopics_truncatehtml($discussion->message, 500);
        $discussion->discussionlink = new moodle_url('/mod/forum/discuss.php', array('d' => $discussion->discussion));
        $discussion->fullname = fullname($discussion);
        $discussion->date = userdate($discussion->modified, $strftimerecent);
        $discussions[$key] = $discussion;
    }

    $context->discussions = array_values($discussions);

    // Links to older topics.
    if (count($discussions)) {
        $context->morenewslink = new moodle_url('/mod/forum/view.php', array('f' => $forum->id, 'showall' => 1));
    }

    return $OUTPUT->render_from_template('format_qmultopics/news', $context);
}

function format_qmultopics_truncatehtml($text, $length = 100, $ending = '...', $exact = false, $considerhtml = true) {
    if ($considerhtml) {
        // If the plain text is shorter than the maximum length, return the whole text.
        if (strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
            return $text;
        }
        // Splits all html-tags to scanable lines.
        preg_match_all('/(<.+?>)?([^<>]*)/s', $text, $lines, PREG_SET_ORDER);
        $totallength = strlen($ending);
        $opentags = array();
        $truncate = '';
        foreach ($lines as $linematchings) {
            // If there is any html-tag in this line, handle it and add it (uncounted) to the output.
            if (!empty($linematchings[1])) {
                // If it's an "empty element" with or without xhtml-conform closing slash.
                if (preg_match(
                    '/^<(\s*.+?\/\s*|\s*(img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param)(\s.+?)?)>$/is',
                    $linematchings[1])) {
                    // Do nothing.
                } else if (preg_match('/^<\s*\/([^\s]+?)\s*>$/s', $linematchings[1], $tagmatchings)) {
                    // If tag is a closing tag.
                    // Delete tag from $opentags list.
                    $pos = array_search($tagmatchings[1], $opentags);
                    if ($pos !== false) {
                        unset($opentags[$pos]);
                    }
                } else if (preg_match('/^<\s*([^\s>!]+).*?>$/s', $linematchings[1], $tagmatchings)) {
                    // If tag is an opening tag.
                    // Add tag to the beginning of $opentags list.
                    array_unshift($opentags, strtolower($tagmatchings[1]));
                }
                // Add html-tag to $truncate'd text.
                $truncate .= $linematchings[1];
            }
            // Calculate the length of the plain text part of the line; handle entities as one character.
            $contentlength = strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i',
                ' ', $linematchings[2]));
            if ($totallength + $contentlength > $length) {
                // The number of characters which are left.
                $left = $length - $totallength;
                $entitieslength = 0;
                // Search for html entities.
                if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', $linematchings[2],
                    $entities, PREG_OFFSET_CAPTURE)) {
                    // Calculate the real length of all entities in the legal range.
                    foreach ($entities[0] as $entity) {
                        if ($entity[1] + 1 - $entitieslength <= $left) {
                            $left--;
                            $entitieslength += strlen($entity[0]);
                        } else {
                            // No more characters left.
                            break;
                        }
                    }
                }
                $truncate .= substr($linematchings[2], 0, $left + $entitieslength);
                // Maximum lenght is reached, so get off the loop.
                break;
            } else {
                $truncate .= $linematchings[2];
                $totallength += $contentlength;
            }
            // If the maximum length is reached, get off the loop.
            if ($totallength >= $length) {
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
    // If the words shouldn't be cut in the middle...
    if (!$exact) {
        // ...search the last occurance of a space...
        $spacepos = strrpos($truncate, ' ');
        if (isset($spacepos)) {
            // ...and cut the text in this position.
            $truncate = substr($truncate, 0, $spacepos);
        }
    }
    // Add the defined ending to the text.
    $truncate .= $ending;
    if ($considerhtml) {
        // Close all unclosed html-tags.
        foreach ($opentags as $tag) {
            $truncate .= '</' . $tag . '>';
        }
    }
    return $truncate;
}
