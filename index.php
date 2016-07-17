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
 * @package   mod_cybrary
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/cybrary/lib.php');
require_once($CFG->libdir . '/rsslib.php');

$id = optional_param('id', 0, PARAM_INT);                   // Course id
$subscribe = optional_param('subscribe', null, PARAM_INT);  // Subscribe/Unsubscribe all cybraries

$url = new moodle_url('/mod/cybrary/index.php', array('id'=>$id));
if ($subscribe !== null) {
    require_sesskey();
    $url->param('subscribe', $subscribe);
}
$PAGE->set_url($url);

if ($id) {
    if (! $course = $DB->get_record('course', array('id' => $id))) {
        print_error('invalidcourseid');
    }
} else {
    $course = get_site();
}

require_course_login($course);
$PAGE->set_pagelayout('incourse');
$coursecontext = context_course::instance($course->id);


unset($SESSION->fromdiscussion);

$params = array(
    'context' => context_course::instance($course->id)
);
$event = \mod_cybrary\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strcybraries       = get_string('cybraries', 'cybrary');
$strcybrary        = get_string('cybrary', 'cybrary');
$strdescription  = get_string('description');
$strdiscussions  = get_string('discussions', 'cybrary');
$strsubscribed   = get_string('subscribed', 'cybrary');
$strunreadposts  = get_string('unreadposts', 'cybrary');
$strtracking     = get_string('tracking', 'cybrary');
$strmarkallread  = get_string('markallread', 'cybrary');
$strtrackcybrary   = get_string('trackcybrary', 'cybrary');
$strnotrackcybrary = get_string('notrackcybrary', 'cybrary');
$strsubscribe    = get_string('subscribe', 'cybrary');
$strunsubscribe  = get_string('unsubscribe', 'cybrary');
$stryes          = get_string('yes');
$strno           = get_string('no');
$strrss          = get_string('rss');
$stremaildigest  = get_string('emaildigest');

$searchform = cybrary_search_form($course);

// Retrieve the list of cybrary digest options for later.
$digestoptions = cybrary_get_user_digest_options();
$digestoptions_selector = new single_select(new moodle_url('/mod/cybrary/maildigest.php',
    array(
        'backtoindex' => 1,
    )),
    'maildigest',
    $digestoptions,
    null,
    '');
$digestoptions_selector->method = 'post';

// Start of the table for General Cybraries

$generaltable = new html_table();
$generaltable->head  = array ($strcybrary, $strdescription, $strdiscussions);
$generaltable->align = array ('left', 'left', 'center');

if ($usetracking = cybrary_tp_can_track_cybraries()) {
    $untracked = cybrary_tp_get_untracked_cybraries($USER->id, $course->id);

    $generaltable->head[] = $strunreadposts;
    $generaltable->align[] = 'center';

    $generaltable->head[] = $strtracking;
    $generaltable->align[] = 'center';
}

// Fill the subscription cache for this course and user combination.
\mod_cybrary\subscriptions::fill_subscription_cache_for_course($course->id, $USER->id);

$can_subscribe = is_enrolled($coursecontext);
if ($can_subscribe) {
    $generaltable->head[] = $strsubscribed;
    $generaltable->align[] = 'center';

    $generaltable->head[] = $stremaildigest . ' ' . $OUTPUT->help_icon('emaildigesttype', 'mod_cybrary');
    $generaltable->align[] = 'center';
}

if ($show_rss = (($can_subscribe || $course->id == SITEID) &&
                 isset($CFG->enablerssfeeds) && isset($CFG->cybrary_enablerssfeeds) &&
                 $CFG->enablerssfeeds && $CFG->cybrary_enablerssfeeds)) {
    $generaltable->head[] = $strrss;
    $generaltable->align[] = 'center';
}

$usesections = course_format_uses_sections($course->format);

$table = new html_table();

// Parse and organise all the cybraries.  Most cybraries are course modules but
// some special ones are not.  These get placed in the general cybraries
// category with the cybraries in section 0.

$cybraries = $DB->get_records_sql("
    SELECT f.*,
           d.maildigest
      FROM {cybrary} f
 LEFT JOIN {cybrary_digests} d ON d.cybrary = f.id AND d.userid = ?
     WHERE f.course = ?
    ", array($USER->id, $course->id));

$generalcybraries  = array();
$learningcybraries = array();
$modinfo = get_fast_modinfo($course);

foreach ($modinfo->get_instances_of('cybrary') as $cybraryid=>$cm) {
    if (!$cm->uservisible or !isset($cybraries[$cybraryid])) {
        continue;
    }

    $cybrary = $cybraries[$cybraryid];

    if (!$context = context_module::instance($cm->id, IGNORE_MISSING)) {
        continue;   // Shouldn't happen
    }

    if (!has_capability('mod/cybrary:viewdiscussion', $context)) {
        continue;
    }

    // fill two type array - order in modinfo is the same as in course
    if ($cybrary->type == 'news' or $cybrary->type == 'social') {
        $generalcybraries[$cybrary->id] = $cybrary;

    } else if ($course->id == SITEID or empty($cm->sectionnum)) {
        $generalcybraries[$cybrary->id] = $cybrary;

    } else {
        $learningcybraries[$cybrary->id] = $cybrary;
    }
}

// Do course wide subscribe/unsubscribe if requested
if (!is_null($subscribe)) {
    if (isguestuser() or !$can_subscribe) {
        // there should not be any links leading to this place, just redirect
        redirect(new moodle_url('/mod/cybrary/index.php', array('id' => $id)), get_string('subscribeenrolledonly', 'cybrary'));
    }
    // Can proceed now, the user is not guest and is enrolled
    foreach ($modinfo->get_instances_of('cybrary') as $cybraryid=>$cm) {
        $cybrary = $cybraries[$cybraryid];
        $modcontext = context_module::instance($cm->id);
        $cansub = false;

        if (has_capability('mod/cybrary:viewdiscussion', $modcontext)) {
            $cansub = true;
        }
        if ($cansub && $cm->visible == 0 &&
            !has_capability('mod/cybrary:managesubscriptions', $modcontext))
        {
            $cansub = false;
        }
        if (!\mod_cybrary\subscriptions::is_forcesubscribed($cybrary)) {
            $subscribed = \mod_cybrary\subscriptions::is_subscribed($USER->id, $cybrary, null, $cm);
            $canmanageactivities = has_capability('moodle/course:manageactivities', $coursecontext, $USER->id);
            if (($canmanageactivities || \mod_cybrary\subscriptions::is_subscribable($cybrary)) && $subscribe && !$subscribed && $cansub) {
                \mod_cybrary\subscriptions::subscribe_user($USER->id, $cybrary, $modcontext, true);
            } else if (!$subscribe && $subscribed) {
                \mod_cybrary\subscriptions::unsubscribe_user($USER->id, $cybrary, $modcontext, true);
            }
        }
    }
    $returnto = cybrary_go_back_to(new moodle_url('/mod/cybrary/index.php', array('id' => $course->id)));
    $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));
    if ($subscribe) {
        redirect($returnto, get_string('nowallsubscribed', 'cybrary', $shortname), 1);
    } else {
        redirect($returnto, get_string('nowallunsubscribed', 'cybrary', $shortname), 1);
    }
}

/// First, let's process the general cybraries and build up a display

if ($generalcybraries) {
    foreach ($generalcybraries as $cybrary) {
        $cm      = $modinfo->instances['cybrary'][$cybrary->id];
        $context = context_module::instance($cm->id);

        $count = cybrary_count_discussions($cybrary, $cm, $course);

        if ($usetracking) {
            if ($cybrary->trackingtype == CYBRARY_TRACKING_OFF) {
                $unreadlink  = '-';
                $trackedlink = '-';

            } else {
                if (isset($untracked[$cybrary->id])) {
                        $unreadlink  = '-';
                } else if ($unread = cybrary_tp_count_cybrary_unread_posts($cm, $course)) {
                        $unreadlink = '<span class="unread"><a href="view.php?f='.$cybrary->id.'">'.$unread.'</a>';
                    $unreadlink .= '<a title="'.$strmarkallread.'" href="markposts.php?f='.
                                   $cybrary->id.'&amp;mark=read"><img src="'.$OUTPUT->pix_url('t/markasread') . '" alt="'.$strmarkallread.'" class="iconsmall" /></a></span>';
                } else {
                    $unreadlink = '<span class="read">0</span>';
                }

                if (($cybrary->trackingtype == CYBRARY_TRACKING_FORCED) && ($CFG->cybrary_allowforcedreadtracking)) {
                    $trackedlink = $stryes;
                } else if ($cybrary->trackingtype === CYBRARY_TRACKING_OFF || ($USER->trackcybraries == 0)) {
                    $trackedlink = '-';
                } else {
                    $aurl = new moodle_url('/mod/cybrary/settracking.php', array(
                            'id' => $cybrary->id,
                            'sesskey' => sesskey(),
                        ));
                    if (!isset($untracked[$cybrary->id])) {
                        $trackedlink = $OUTPUT->single_button($aurl, $stryes, 'post', array('title'=>$strnotrackcybrary));
                    } else {
                        $trackedlink = $OUTPUT->single_button($aurl, $strno, 'post', array('title'=>$strtrackcybrary));
                    }
                }
            }
        }

        $cybrary->intro = shorten_text(format_module_intro('cybrary', $cybrary, $cm->id), $CFG->cybrary_shortpost);
        $cybraryname = format_string($cybrary->name, true);

        if ($cm->visible) {
            $style = '';
        } else {
            $style = 'class="dimmed"';
        }
        $cybrarylink = "<a href=\"view.php?f=$cybrary->id\" $style>".format_string($cybrary->name,true)."</a>";
        $discussionlink = "<a href=\"view.php?f=$cybrary->id\" $style>".$count."</a>";

        $row = array ($cybrarylink, $cybrary->intro, $discussionlink);
        if ($usetracking) {
            $row[] = $unreadlink;
            $row[] = $trackedlink;    // Tracking.
        }

        if ($can_subscribe) {
            $row[] = cybrary_get_subscribe_link($cybrary, $context, array('subscribed' => $stryes,
                    'unsubscribed' => $strno, 'forcesubscribed' => $stryes,
                    'cantsubscribe' => '-'), false, false, true);

            $digestoptions_selector->url->param('id', $cybrary->id);
            if ($cybrary->maildigest === null) {
                $digestoptions_selector->selected = -1;
            } else {
                $digestoptions_selector->selected = $cybrary->maildigest;
            }
            $row[] = $OUTPUT->render($digestoptions_selector);
        }

        //If this cybrary has RSS activated, calculate it
        if ($show_rss) {
            if ($cybrary->rsstype and $cybrary->rssarticles) {
                //Calculate the tooltip text
                if ($cybrary->rsstype == 1) {
                    $tooltiptext = get_string('rsssubscriberssdiscussions', 'cybrary');
                } else {
                    $tooltiptext = get_string('rsssubscriberssposts', 'cybrary');
                }

                if (!isloggedin() && $course->id == SITEID) {
                    $userid = guest_user()->id;
                } else {
                    $userid = $USER->id;
                }
                //Get html code for RSS link
                $row[] = rss_get_link($context->id, $userid, 'mod_cybrary', $cybrary->id, $tooltiptext);
            } else {
                $row[] = '&nbsp;';
            }
        }

        $generaltable->data[] = $row;
    }
}


// Start of the table for Learning Cybraries
$learningtable = new html_table();
$learningtable->head  = array ($strcybrary, $strdescription, $strdiscussions);
$learningtable->align = array ('left', 'left', 'center');

if ($usetracking) {
    $learningtable->head[] = $strunreadposts;
    $learningtable->align[] = 'center';

    $learningtable->head[] = $strtracking;
    $learningtable->align[] = 'center';
}

if ($can_subscribe) {
    $learningtable->head[] = $strsubscribed;
    $learningtable->align[] = 'center';

    $learningtable->head[] = $stremaildigest . ' ' . $OUTPUT->help_icon('emaildigesttype', 'mod_cybrary');
    $learningtable->align[] = 'center';
}

if ($show_rss = (($can_subscribe || $course->id == SITEID) &&
                 isset($CFG->enablerssfeeds) && isset($CFG->cybrary_enablerssfeeds) &&
                 $CFG->enablerssfeeds && $CFG->cybrary_enablerssfeeds)) {
    $learningtable->head[] = $strrss;
    $learningtable->align[] = 'center';
}

/// Now let's process the learning cybraries

if ($course->id != SITEID) {    // Only real courses have learning cybraries
    // 'format_.'$course->format only applicable when not SITEID (format_site is not a format)
    $strsectionname  = get_string('sectionname', 'format_'.$course->format);
    // Add extra field for section number, at the front
    array_unshift($learningtable->head, $strsectionname);
    array_unshift($learningtable->align, 'center');


    if ($learningcybraries) {
        $currentsection = '';
            foreach ($learningcybraries as $cybrary) {
            $cm      = $modinfo->instances['cybrary'][$cybrary->id];
            $context = context_module::instance($cm->id);

            $count = cybrary_count_discussions($cybrary, $cm, $course);

            if ($usetracking) {
                if ($cybrary->trackingtype == CYBRARY_TRACKING_OFF) {
                    $unreadlink  = '-';
                    $trackedlink = '-';

                } else {
                    if (isset($untracked[$cybrary->id])) {
                        $unreadlink  = '-';
                    } else if ($unread = cybrary_tp_count_cybrary_unread_posts($cm, $course)) {
                        $unreadlink = '<span class="unread"><a href="view.php?f='.$cybrary->id.'">'.$unread.'</a>';
                        $unreadlink .= '<a title="'.$strmarkallread.'" href="markposts.php?f='.
                                       $cybrary->id.'&amp;mark=read"><img src="'.$OUTPUT->pix_url('t/markasread') . '" alt="'.$strmarkallread.'" class="iconsmall" /></a></span>';
                    } else {
                        $unreadlink = '<span class="read">0</span>';
                    }

                    if (($cybrary->trackingtype == CYBRARY_TRACKING_FORCED) && ($CFG->cybrary_allowforcedreadtracking)) {
                        $trackedlink = $stryes;
                    } else if ($cybrary->trackingtype === CYBRARY_TRACKING_OFF || ($USER->trackcybraries == 0)) {
                        $trackedlink = '-';
                    } else {
                        $aurl = new moodle_url('/mod/cybrary/settracking.php', array('id'=>$cybrary->id));
                        if (!isset($untracked[$cybrary->id])) {
                            $trackedlink = $OUTPUT->single_button($aurl, $stryes, 'post', array('title'=>$strnotrackcybrary));
                        } else {
                            $trackedlink = $OUTPUT->single_button($aurl, $strno, 'post', array('title'=>$strtrackcybrary));
                        }
                    }
                }
            }

            $cybrary->intro = shorten_text(format_module_intro('cybrary', $cybrary, $cm->id), $CFG->cybrary_shortpost);

            if ($cm->sectionnum != $currentsection) {
                $printsection = get_section_name($course, $cm->sectionnum);
                if ($currentsection) {
                    $learningtable->data[] = 'hr';
                }
                $currentsection = $cm->sectionnum;
            } else {
                $printsection = '';
            }

            $cybraryname = format_string($cybrary->name,true);

            if ($cm->visible) {
                $style = '';
            } else {
                $style = 'class="dimmed"';
            }
            $cybrarylink = "<a href=\"view.php?f=$cybrary->id\" $style>".format_string($cybrary->name,true)."</a>";
            $discussionlink = "<a href=\"view.php?f=$cybrary->id\" $style>".$count."</a>";

            $row = array ($printsection, $cybrarylink, $cybrary->intro, $discussionlink);
            if ($usetracking) {
                $row[] = $unreadlink;
                $row[] = $trackedlink;    // Tracking.
            }

            if ($can_subscribe) {
                $row[] = cybrary_get_subscribe_link($cybrary, $context, array('subscribed' => $stryes,
                    'unsubscribed' => $strno, 'forcesubscribed' => $stryes,
                    'cantsubscribe' => '-'), false, false, true);

                $digestoptions_selector->url->param('id', $cybrary->id);
                if ($cybrary->maildigest === null) {
                    $digestoptions_selector->selected = -1;
                } else {
                    $digestoptions_selector->selected = $cybrary->maildigest;
                }
                $row[] = $OUTPUT->render($digestoptions_selector);
            }

            //If this cybrary has RSS activated, calculate it
            if ($show_rss) {
                if ($cybrary->rsstype and $cybrary->rssarticles) {
                    //Calculate the tolltip text
                    if ($cybrary->rsstype == 1) {
                        $tooltiptext = get_string('rsssubscriberssdiscussions', 'cybrary');
                    } else {
                        $tooltiptext = get_string('rsssubscriberssposts', 'cybrary');
                    }
                    //Get html code for RSS link
                    $row[] = rss_get_link($context->id, $USER->id, 'mod_cybrary', $cybrary->id, $tooltiptext);
                } else {
                    $row[] = '&nbsp;';
                }
            }

            $learningtable->data[] = $row;
        }
    }
}


/// Output the page
$PAGE->navbar->add($strcybraries);
$PAGE->set_title("$course->shortname: $strcybraries");
$PAGE->set_heading($course->fullname);
$PAGE->set_button($searchform);
echo $OUTPUT->header();

// Show the subscribe all options only to non-guest, enrolled users
if (!isguestuser() && isloggedin() && $can_subscribe) {
    echo $OUTPUT->box_start('subscription');
    echo html_writer::tag('div',
        html_writer::link(new moodle_url('/mod/cybrary/index.php', array('id'=>$course->id, 'subscribe'=>1, 'sesskey'=>sesskey())),
            get_string('allsubscribe', 'cybrary')),
        array('class'=>'helplink'));
    echo html_writer::tag('div',
        html_writer::link(new moodle_url('/mod/cybrary/index.php', array('id'=>$course->id, 'subscribe'=>0, 'sesskey'=>sesskey())),
            get_string('allunsubscribe', 'cybrary')),
        array('class'=>'helplink'));
    echo $OUTPUT->box_end();
    echo $OUTPUT->box('&nbsp;', 'clearer');
}

if ($generalcybraries) {
    echo $OUTPUT->heading(get_string('generalcybraries', 'cybrary'), 2);
    echo html_writer::table($generaltable);
}

if ($learningcybraries) {
    echo $OUTPUT->heading(get_string('learningcybraries', 'cybrary'), 2);
    echo html_writer::table($learningtable);
}

echo $OUTPUT->footer();

