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
 * Displays a post, and all the posts below it.
 * If no post is given, displays all posts in a discussion
 *
 * @package   mod_cybrary
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$d      = required_param('d', PARAM_INT);                // Discussion ID
$parent = optional_param('parent', 0, PARAM_INT);        // If set, then display this post and all children.
$mode   = optional_param('mode', 0, PARAM_INT);          // If set, changes the layout of the thread
$move   = optional_param('move', 0, PARAM_INT);          // If set, moves this discussion to another cybrary
$mark   = optional_param('mark', '', PARAM_ALPHA);       // Used for tracking read posts if user initiated.
$postid = optional_param('postid', 0, PARAM_INT);        // Used for tracking read posts if user initiated.

$url = new moodle_url('/mod/cybrary/discuss.php', array('d'=>$d));
if ($parent !== 0) {
    $url->param('parent', $parent);
}
$PAGE->set_url($url);

$discussion = $DB->get_record('cybrary_discussions', array('id' => $d), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $discussion->course), '*', MUST_EXIST);
$cybrary = $DB->get_record('cybrary', array('id' => $discussion->cybrary), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('cybrary', $cybrary->id, $course->id, false, MUST_EXIST);

require_course_login($course, true, $cm);

// move this down fix for MDL-6926
require_once($CFG->dirroot.'/mod/cybrary/lib.php');

$modcontext = context_module::instance($cm->id);
require_capability('mod/cybrary:viewdiscussion', $modcontext, NULL, true, 'noviewdiscussionspermission', 'cybrary');

if (!empty($CFG->enablerssfeeds) && !empty($CFG->cybrary_enablerssfeeds) && $cybrary->rsstype && $cybrary->rssarticles) {
    require_once("$CFG->libdir/rsslib.php");

    $rsstitle = format_string($course->shortname, true, array('context' => context_course::instance($course->id))) . ': ' . format_string($cybrary->name);
    rss_add_http_header($modcontext, 'mod_cybrary', $cybrary, $rsstitle);
}

// Move discussion if requested.
if ($move > 0 and confirm_sesskey()) {
    $return = $CFG->wwwroot.'/mod/cybrary/discuss.php?d='.$discussion->id;

    if (!$cybraryto = $DB->get_record('cybrary', array('id' => $move))) {
        print_error('cannotmovetonotexist', 'cybrary', $return);
    }

    require_capability('mod/cybrary:movediscussions', $modcontext);

    if ($cybrary->type == 'single') {
        print_error('cannotmovefromsinglecybrary', 'cybrary', $return);
    }

    if (!$cybraryto = $DB->get_record('cybrary', array('id' => $move))) {
        print_error('cannotmovetonotexist', 'cybrary', $return);
    }

    if ($cybraryto->type == 'single') {
        print_error('cannotmovetosinglecybrary', 'cybrary', $return);
    }

    // Get target cybrary cm and check it is visible to current user.
    $modinfo = get_fast_modinfo($course);
    $cybraries = $modinfo->get_instances_of('cybrary');
    if (!array_key_exists($cybraryto->id, $cybraries)) {
        print_error('cannotmovetonotfound', 'cybrary', $return);
    }
    $cmto = $cybraries[$cybraryto->id];
    if (!$cmto->uservisible) {
        print_error('cannotmovenotvisible', 'cybrary', $return);
    }

    $destinationctx = context_module::instance($cmto->id);
    require_capability('mod/cybrary:startdiscussion', $destinationctx);

    if (!cybrary_move_attachments($discussion, $cybrary->id, $cybraryto->id)) {
        echo $OUTPUT->notification("Errors occurred while moving attachment directories - check your file permissions");
    }
    // For each subscribed user in this cybrary and discussion, copy over per-discussion subscriptions if required.
    $discussiongroup = $discussion->groupid == -1 ? 0 : $discussion->groupid;
    $potentialsubscribers = \mod_cybrary\subscriptions::fetch_subscribed_users(
        $cybrary,
        $discussiongroup,
        $modcontext,
        'u.id',
        true
    );

    // Pre-seed the subscribed_discussion caches.
    // Firstly for the cybrary being moved to.
    \mod_cybrary\subscriptions::fill_subscription_cache($cybraryto->id);
    // And also for the discussion being moved.
    \mod_cybrary\subscriptions::fill_subscription_cache($cybrary->id);
    $subscriptionchanges = array();
    $subscriptiontime = time();
    foreach ($potentialsubscribers as $subuser) {
        $userid = $subuser->id;
        $targetsubscription = \mod_cybrary\subscriptions::is_subscribed($userid, $cybraryto, null, $cmto);
        $discussionsubscribed = \mod_cybrary\subscriptions::is_subscribed($userid, $cybrary, $discussion->id);
        $cybrariesubscribed = \mod_cybrary\subscriptions::is_subscribed($userid, $cybrary);

        if ($cybrariesubscribed && !$discussionsubscribed && $targetsubscription) {
            // The user has opted out of this discussion and the move would cause them to receive notifications again.
            // Ensure they are unsubscribed from the discussion still.
            $subscriptionchanges[$userid] = \mod_cybrary\subscriptions::CYBRARY_DISCUSSION_UNSUBSCRIBED;
        } else if (!$cybrariesubscribed && $discussionsubscribed && !$targetsubscription) {
            // The user has opted into this discussion and would otherwise not receive the subscription after the move.
            // Ensure they are subscribed to the discussion still.
            $subscriptionchanges[$userid] = $subscriptiontime;
        }
    }

    $DB->set_field('cybrary_discussions', 'cybrary', $cybraryto->id, array('id' => $discussion->id));
    $DB->set_field('cybrary_read', 'cybraryid', $cybraryto->id, array('discussionid' => $discussion->id));

    // Delete the existing per-discussion subscriptions and replace them with the newly calculated ones.
    $DB->delete_records('cybrary_discussion_subs', array('discussion' => $discussion->id));
    $newdiscussion = clone $discussion;
    $newdiscussion->cybrary = $cybraryto->id;
    foreach ($subscriptionchanges as $userid => $preference) {
        if ($preference != \mod_cybrary\subscriptions::CYBRARY_DISCUSSION_UNSUBSCRIBED) {
            // Users must have viewdiscussion to a discussion.
            if (has_capability('mod/cybrary:viewdiscussion', $destinationctx, $userid)) {
                \mod_cybrary\subscriptions::subscribe_user_to_discussion($userid, $newdiscussion, $destinationctx);
            }
        } else {
            \mod_cybrary\subscriptions::unsubscribe_user_from_discussion($userid, $newdiscussion, $destinationctx);
        }
    }

    $params = array(
        'context' => $destinationctx,
        'objectid' => $discussion->id,
        'other' => array(
            'fromcybraryid' => $cybrary->id,
            'tocybraryid' => $cybraryto->id,
        )
    );
    $event = \mod_cybrary\event\discussion_moved::create($params);
    $event->add_record_snapshot('cybrary_discussions', $discussion);
    $event->add_record_snapshot('cybrary', $cybrary);
    $event->add_record_snapshot('cybrary', $cybraryto);
    $event->trigger();

    // Delete the RSS files for the 2 cybraries to force regeneration of the feeds
    require_once($CFG->dirroot.'/mod/cybrary/rsslib.php');
    cybrary_rss_delete_file($cybrary);
    cybrary_rss_delete_file($cybraryto);

    redirect($return.'&move=-1&sesskey='.sesskey());
}

// Trigger discussion viewed event.
cybrary_discussion_view($modcontext, $cybrary, $discussion);

unset($SESSION->fromdiscussion);

if ($mode) {
    set_user_preference('cybrary_displaymode', $mode);
}

$displaymode = get_user_preferences('cybrary_displaymode', $CFG->cybrary_displaymode);

if ($parent) {
    // If flat AND parent, then force nested display this time
    if ($displaymode == CYBRARY_MODE_FLATOLDEST or $displaymode == CYBRARY_MODE_FLATNEWEST) {
        $displaymode = CYBRARY_MODE_NESTED;
    }
} else {
    $parent = $discussion->firstpost;
}

if (! $post = cybrary_get_post_full($parent)) {
    print_error("notexists", 'cybrary', "$CFG->wwwroot/mod/cybrary/view.php?f=$cybrary->id");
}

if (!cybrary_user_can_see_post($cybrary, $discussion, $post, null, $cm)) {
    print_error('noviewdiscussionspermission', 'cybrary', "$CFG->wwwroot/mod/cybrary/view.php?id=$cybrary->id");
}

if ($mark == 'read' or $mark == 'unread') {
    if ($CFG->cybrary_usermarksread && cybrary_tp_can_track_cybraries($cybrary) && cybrary_tp_is_tracked($cybrary)) {
        if ($mark == 'read') {
            cybrary_tp_add_read_record($USER->id, $postid);
        } else {
            // unread
            cybrary_tp_delete_read_records($USER->id, $postid);
        }
    }
}

$searchform = cybrary_search_form($course);

$cybrarynode = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
if (empty($cybrarynode)) {
    $cybrarynode = $PAGE->navbar;
} else {
    $cybrarynode->make_active();
}
$node = $cybrarynode->add(format_string($discussion->name), new moodle_url('/mod/cybrary/discuss.php', array('d'=>$discussion->id)));
$node->display = false;
if ($node && $post->id != $discussion->firstpost) {
    $node->add(format_string($post->subject), $PAGE->url);
}

$PAGE->set_title("$course->shortname: ".format_string($discussion->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_button($searchform);
$renderer = $PAGE->get_renderer('mod_cybrary');

echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($cybrary->name), 2);
echo $OUTPUT->heading(format_string($discussion->name), 3, 'discussionname');

// is_guest should be used here as this also checks whether the user is a guest in the current course.
// Guests and visitors cannot subscribe - only enrolled users.
if ((!is_guest($modcontext, $USER) && isloggedin()) && has_capability('mod/cybrary:viewdiscussion', $modcontext)) {
    // Discussion subscription.
    if (\mod_cybrary\subscriptions::is_subscribable($cybrary)) {
        echo html_writer::div(
            cybrary_get_discussion_subscription_icon($cybrary, $post->discussion, null, true),
            'discussionsubscription'
        );
        echo cybrary_get_discussion_subscription_icon_preloaders();
    }
}


/// Check to see if groups are being used in this cybrary
/// If so, make sure the current person is allowed to see this discussion
/// Also, if we know they should be able to reply, then explicitly set $canreply for performance reasons

$canreply = cybrary_user_can_post($cybrary, $discussion, $USER, $cm, $course, $modcontext);
if (!$canreply and $cybrary->type !== 'news') {
    if (isguestuser() or !isloggedin()) {
        $canreply = true;
    }
    if (!is_enrolled($modcontext) and !is_viewing($modcontext)) {
        // allow guests and not-logged-in to see the link - they are prompted to log in after clicking the link
        // normal users with temporary guest access see this link too, they are asked to enrol instead
        $canreply = enrol_selfenrol_available($course->id);
    }
}

// Output the links to neighbour discussions.
$neighbours = cybrary_get_discussion_neighbours($cm, $discussion, $cybrary);
$neighbourlinks = $renderer->neighbouring_discussion_navigation($neighbours['prev'], $neighbours['next']);
echo $neighbourlinks;

/// Print the controls across the top
echo '<div class="discussioncontrols clearfix">';

if (!empty($CFG->enableportfolios) && has_capability('mod/cybrary:exportdiscussion', $modcontext)) {
    require_once($CFG->libdir.'/portfoliolib.php');
    $button = new portfolio_add_button();
    $button->set_callback_options('cybrary_portfolio_caller', array('discussionid' => $discussion->id), 'mod_cybrary');
    $button = $button->to_html(PORTFOLIO_ADD_FULL_FORM, get_string('exportdiscussion', 'mod_cybrary'));
    $buttonextraclass = '';
    if (empty($button)) {
        // no portfolio plugin available.
        $button = '&nbsp;';
        $buttonextraclass = ' noavailable';
    }
    echo html_writer::tag('div', $button, array('class' => 'discussioncontrol exporttoportfolio'.$buttonextraclass));
} else {
    echo html_writer::tag('div', '&nbsp;', array('class'=>'discussioncontrol nullcontrol'));
}

// groups selector not needed here
echo '<div class="discussioncontrol displaymode">';
cybrary_print_mode_form($discussion->id, $displaymode);
echo "</div>";

if ($cybrary->type != 'single'
            && has_capability('mod/cybrary:movediscussions', $modcontext)) {

    echo '<div class="discussioncontrol movediscussion">';
    // Popup menu to move discussions to other cybraries. The discussion in a
    // single discussion cybrary can't be moved.
    $modinfo = get_fast_modinfo($course);
    if (isset($modinfo->instances['cybrary'])) {
        $cybrarymenu = array();
        // Check cybrary types and eliminate simple discussions.
        $cybrarycheck = $DB->get_records('cybrary', array('course' => $course->id),'', 'id, type');
        foreach ($modinfo->instances['cybrary'] as $cybrarycm) {
            if (!$cybrarycm->uservisible || !has_capability('mod/cybrary:startdiscussion',
                context_module::instance($cybrarycm->id))) {
                continue;
            }
            $section = $cybrarycm->sectionnum;
            $sectionname = get_section_name($course, $section);
            if (empty($cybrarymenu[$section])) {
                $cybrarymenu[$section] = array($sectionname => array());
            }
            $cybraryidcompare = $cybrarycm->instance != $cybrary->id;
            $cybrarytypecheck = $cybrarycheck[$cybrarycm->instance]->type !== 'single';
            if ($cybraryidcompare and $cybrarytypecheck) {
                $url = "/mod/cybrary/discuss.php?d=$discussion->id&move=$cybrarycm->instance&sesskey=".sesskey();
                $cybrarymenu[$section][$sectionname][$url] = format_string($cybrarycm->name);
            }
        }
        if (!empty($cybrarymenu)) {
            echo '<div class="movediscussionoption">';
            $select = new url_select($cybrarymenu, '',
                    array('/mod/cybrary/discuss.php?d=' . $discussion->id => get_string("movethisdiscussionto", "cybrary")),
                    'cybrarymenu', get_string('move'));
            echo $OUTPUT->render($select);
            echo "</div>";
        }
    }
    echo "</div>";
}
echo '<div class="clearfloat">&nbsp;</div>';
echo "</div>";

if (!empty($cybrary->blockafter) && !empty($cybrary->blockperiod)) {
    $a = new stdClass();
    $a->blockafter  = $cybrary->blockafter;
    $a->blockperiod = get_string('secondstotime'.$cybrary->blockperiod);
    echo $OUTPUT->notification(get_string('thiscybraryisthrottled','cybrary',$a));
}

if ($cybrary->type == 'qanda' && !has_capability('mod/cybrary:viewqandawithoutposting', $modcontext) &&
            !cybrary_user_has_posted($cybrary->id,$discussion->id,$USER->id)) {
    echo $OUTPUT->notification(get_string('qandanotify','cybrary'));
}

if ($move == -1 and confirm_sesskey()) {
    echo $OUTPUT->notification(get_string('discussionmoved', 'cybrary', format_string($cybrary->name,true)), 'notifysuccess');
}

$canrate = has_capability('mod/cybrary:rate', $modcontext);
cybrary_print_discussion($course, $cm, $cybrary, $discussion, $post, $displaymode, $canreply, $canrate);

echo $neighbourlinks;

// Add the subscription toggle JS.
$PAGE->requires->yui_module('moodle-mod_cybrary-subscriptiontoggle', 'Y.M.mod_cybrary.subscriptiontoggle.init');

echo $OUTPUT->footer();
