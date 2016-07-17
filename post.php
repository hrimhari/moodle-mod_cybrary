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
 * Edit and save a new post to a discussion
 *
 * @package   mod_cybrary
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once('lib.php');
require_once($CFG->libdir.'/completionlib.php');

$reply   = optional_param('reply', 0, PARAM_INT);
$cybrary   = optional_param('cybrary', 0, PARAM_INT);
$edit    = optional_param('edit', 0, PARAM_INT);
$delete  = optional_param('delete', 0, PARAM_INT);
$prune   = optional_param('prune', 0, PARAM_INT);
$name    = optional_param('name', '', PARAM_CLEAN);
$confirm = optional_param('confirm', 0, PARAM_INT);
$groupid = optional_param('groupid', null, PARAM_INT);

$PAGE->set_url('/mod/cybrary/post.php', array(
        'reply' => $reply,
        'cybrary' => $cybrary,
        'edit'  => $edit,
        'delete'=> $delete,
        'prune' => $prune,
        'name'  => $name,
        'confirm'=>$confirm,
        'groupid'=>$groupid,
        ));
//these page_params will be passed as hidden variables later in the form.
$page_params = array('reply'=>$reply, 'cybrary'=>$cybrary, 'edit'=>$edit);

$sitecontext = context_system::instance();

if (!isloggedin() or isguestuser()) {

    if (!isloggedin() and !get_local_referer()) {
        // No referer+not logged in - probably coming in via email  See MDL-9052
        require_login();
    }

    if (!empty($cybrary)) {      // User is starting a new discussion in a cybrary
        if (! $cybrary = $DB->get_record('cybrary', array('id' => $cybrary))) {
            print_error('invalidcybraryid', 'cybrary');
        }
    } else if (!empty($reply)) {      // User is writing a new reply
        if (! $parent = cybrary_get_post_full($reply)) {
            print_error('invalidparentpostid', 'cybrary');
        }
        if (! $discussion = $DB->get_record('cybrary_discussions', array('id' => $parent->discussion))) {
            print_error('notpartofdiscussion', 'cybrary');
        }
        if (! $cybrary = $DB->get_record('cybrary', array('id' => $discussion->cybrary))) {
            print_error('invalidcybraryid');
        }
    }
    if (! $course = $DB->get_record('course', array('id' => $cybrary->course))) {
        print_error('invalidcourseid');
    }

    if (!$cm = get_coursemodule_from_instance('cybrary', $cybrary->id, $course->id)) { // For the logs
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }

    $PAGE->set_cm($cm, $course, $cybrary);
    $PAGE->set_context($modcontext);
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);
    $referer = get_local_referer(false);

    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('noguestpost', 'cybrary').'<br /><br />'.get_string('liketologin'), get_login_url(), $referer);
    echo $OUTPUT->footer();
    exit;
}

require_login(0, false);   // Script is useless unless they're logged in

if (!empty($cybrary)) {      // User is starting a new discussion in a cybrary
    if (! $cybrary = $DB->get_record("cybrary", array("id" => $cybrary))) {
        print_error('invalidcybraryid', 'cybrary');
    }
    if (! $course = $DB->get_record("course", array("id" => $cybrary->course))) {
        print_error('invalidcourseid');
    }
    if (! $cm = get_coursemodule_from_instance("cybrary", $cybrary->id, $course->id)) {
        print_error("invalidcoursemodule");
    }

    // Retrieve the contexts.
    $modcontext    = context_module::instance($cm->id);
    $coursecontext = context_course::instance($course->id);

    if (! cybrary_user_can_post_discussion($cybrary, $groupid, -1, $cm)) {
        if (!isguestuser()) {
            if (!is_enrolled($coursecontext)) {
                if (enrol_selfenrol_available($course->id)) {
                    $SESSION->wantsurl = qualified_me();
                    $SESSION->enrolcancel = get_local_referer(false);
                    redirect(new moodle_url('/enrol/index.php', array('id' => $course->id,
                        'returnurl' => '/mod/cybrary/view.php?f=' . $cybrary->id)),
                        get_string('youneedtoenrol'));
                }
            }
        }
        print_error('nopostcybrary', 'cybrary');
    }

    if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $modcontext)) {
        print_error("activityiscurrentlyhidden");
    }

    $SESSION->fromurl = get_local_referer(false);

    // Load up the $post variable.

    $post = new stdClass();
    $post->course        = $course->id;
    $post->cybrary         = $cybrary->id;
    $post->discussion    = 0;           // ie discussion # not defined yet
    $post->parent        = 0;
    $post->subject       = '';
    $post->userid        = $USER->id;
    $post->message       = '';
    $post->messageformat = editors_get_preferred_format();
    $post->messagetrust  = 0;

    if (isset($groupid)) {
        $post->groupid = $groupid;
    } else {
        $post->groupid = groups_get_activity_group($cm);
    }

    // Unsetting this will allow the correct return URL to be calculated later.
    unset($SESSION->fromdiscussion);

} else if (!empty($reply)) {      // User is writing a new reply

    if (! $parent = cybrary_get_post_full($reply)) {
        print_error('invalidparentpostid', 'cybrary');
    }
    if (! $discussion = $DB->get_record("cybrary_discussions", array("id" => $parent->discussion))) {
        print_error('notpartofdiscussion', 'cybrary');
    }
    if (! $cybrary = $DB->get_record("cybrary", array("id" => $discussion->cybrary))) {
        print_error('invalidcybraryid', 'cybrary');
    }
    if (! $course = $DB->get_record("course", array("id" => $discussion->course))) {
        print_error('invalidcourseid');
    }
    if (! $cm = get_coursemodule_from_instance("cybrary", $cybrary->id, $course->id)) {
        print_error('invalidcoursemodule');
    }

    // Ensure lang, theme, etc. is set up properly. MDL-6926
    $PAGE->set_cm($cm, $course, $cybrary);

    // Retrieve the contexts.
    $modcontext    = context_module::instance($cm->id);
    $coursecontext = context_course::instance($course->id);

    if (! cybrary_user_can_post($cybrary, $discussion, $USER, $cm, $course, $modcontext)) {
        if (!isguestuser()) {
            if (!is_enrolled($coursecontext)) {  // User is a guest here!
                $SESSION->wantsurl = qualified_me();
                $SESSION->enrolcancel = get_local_referer(false);
                redirect(new moodle_url('/enrol/index.php', array('id' => $course->id,
                    'returnurl' => '/mod/cybrary/view.php?f=' . $cybrary->id)),
                    get_string('youneedtoenrol'));
            }
        }
        print_error('nopostcybrary', 'cybrary');
    }

    // Make sure user can post here
    if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
        $groupmode =  $cm->groupmode;
    } else {
        $groupmode = $course->groupmode;
    }
    if ($groupmode == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $modcontext)) {
        if ($discussion->groupid == -1) {
            print_error('nopostcybrary', 'cybrary');
        } else {
            if (!groups_is_member($discussion->groupid)) {
                print_error('nopostcybrary', 'cybrary');
            }
        }
    }

    if (!$cm->visible and !has_capability('moodle/course:viewhiddenactivities', $modcontext)) {
        print_error("activityiscurrentlyhidden");
    }

    // Load up the $post variable.

    $post = new stdClass();
    $post->course      = $course->id;
    $post->cybrary       = $cybrary->id;
    $post->discussion  = $parent->discussion;
    $post->parent      = $parent->id;
    $post->subject     = $parent->subject;
    $post->userid      = $USER->id;
    $post->message     = '';

    $post->groupid = ($discussion->groupid == -1) ? 0 : $discussion->groupid;

    $strre = get_string('re', 'cybrary');
    if (!(substr($post->subject, 0, strlen($strre)) == $strre)) {
        $post->subject = $strre.' '.$post->subject;
    }

    // Unsetting this will allow the correct return URL to be calculated later.
    unset($SESSION->fromdiscussion);

} else if (!empty($edit)) {  // User is editing their own post

    if (! $post = cybrary_get_post_full($edit)) {
        print_error('invalidpostid', 'cybrary');
    }
    if ($post->parent) {
        if (! $parent = cybrary_get_post_full($post->parent)) {
            print_error('invalidparentpostid', 'cybrary');
        }
    }

    if (! $discussion = $DB->get_record("cybrary_discussions", array("id" => $post->discussion))) {
        print_error('notpartofdiscussion', 'cybrary');
    }
    if (! $cybrary = $DB->get_record("cybrary", array("id" => $discussion->cybrary))) {
        print_error('invalidcybraryid', 'cybrary');
    }
    if (! $course = $DB->get_record("course", array("id" => $discussion->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance("cybrary", $cybrary->id, $course->id)) {
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }

    $PAGE->set_cm($cm, $course, $cybrary);

    if (!($cybrary->type == 'news' && !$post->parent && $discussion->timestart > time())) {
        if (((time() - $post->created) > $CFG->maxeditingtime) and
                    !has_capability('mod/cybrary:editanypost', $modcontext)) {
            print_error('maxtimehaspassed', 'cybrary', '', format_time($CFG->maxeditingtime));
        }
    }
    if (($post->userid <> $USER->id) and
                !has_capability('mod/cybrary:editanypost', $modcontext)) {
        print_error('cannoteditposts', 'cybrary');
    }


    // Load up the $post variable.
    $post->edit   = $edit;
    $post->course = $course->id;
    $post->cybrary  = $cybrary->id;
    $post->groupid = ($discussion->groupid == -1) ? 0 : $discussion->groupid;

    $post = trusttext_pre_edit($post, 'message', $modcontext);

    // Unsetting this will allow the correct return URL to be calculated later.
    unset($SESSION->fromdiscussion);

}else if (!empty($delete)) {  // User is deleting a post

    if (! $post = cybrary_get_post_full($delete)) {
        print_error('invalidpostid', 'cybrary');
    }
    if (! $discussion = $DB->get_record("cybrary_discussions", array("id" => $post->discussion))) {
        print_error('notpartofdiscussion', 'cybrary');
    }
    if (! $cybrary = $DB->get_record("cybrary", array("id" => $discussion->cybrary))) {
        print_error('invalidcybraryid', 'cybrary');
    }
    if (!$cm = get_coursemodule_from_instance("cybrary", $cybrary->id, $cybrary->course)) {
        print_error('invalidcoursemodule');
    }
    if (!$course = $DB->get_record('course', array('id' => $cybrary->course))) {
        print_error('invalidcourseid');
    }

    require_login($course, false, $cm);
    $modcontext = context_module::instance($cm->id);

    if ( !(($post->userid == $USER->id && has_capability('mod/cybrary:deleteownpost', $modcontext))
                || has_capability('mod/cybrary:deleteanypost', $modcontext)) ) {
        print_error('cannotdeletepost', 'cybrary');
    }


    $replycount = cybrary_count_replies($post);

    if (!empty($confirm) && confirm_sesskey()) {    // User has confirmed the delete
        //check user capability to delete post.
        $timepassed = time() - $post->created;
        if (($timepassed > $CFG->maxeditingtime) && !has_capability('mod/cybrary:deleteanypost', $modcontext)) {
            print_error("cannotdeletepost", "cybrary",
                        cybrary_go_back_to(new moodle_url("/mod/cybrary/discuss.php", array('d' => $post->discussion))));
        }

        if ($post->totalscore) {
            notice(get_string('couldnotdeleteratings', 'rating'),
                   cybrary_go_back_to(new moodle_url("/mod/cybrary/discuss.php", array('d' => $post->discussion))));

        } else if ($replycount && !has_capability('mod/cybrary:deleteanypost', $modcontext)) {
            print_error("couldnotdeletereplies", "cybrary",
                        cybrary_go_back_to(new moodle_url("/mod/cybrary/discuss.php", array('d' => $post->discussion))));

        } else {
            if (! $post->parent) {  // post is a discussion topic as well, so delete discussion
                if ($cybrary->type == 'single') {
                    notice("Sorry, but you are not allowed to delete that discussion!",
                           cybrary_go_back_to(new moodle_url("/mod/cybrary/discuss.php", array('d' => $post->discussion))));
                }
                cybrary_delete_discussion($discussion, false, $course, $cm, $cybrary);

                $params = array(
                    'objectid' => $discussion->id,
                    'context' => $modcontext,
                    'other' => array(
                        'cybraryid' => $cybrary->id,
                    )
                );

                $event = \mod_cybrary\event\discussion_deleted::create($params);
                $event->add_record_snapshot('cybrary_discussions', $discussion);
                $event->trigger();

                redirect("view.php?f=$discussion->cybrary");

            } else if (cybrary_delete_post($post, has_capability('mod/cybrary:deleteanypost', $modcontext),
                $course, $cm, $cybrary)) {

                if ($cybrary->type == 'single') {
                    // Single discussion cybraries are an exception. We show
                    // the cybrary itself since it only has one discussion
                    // thread.
                    $discussionurl = new moodle_url("/mod/cybrary/view.php", array('f' => $cybrary->id));
                } else {
                    $discussionurl = new moodle_url("/mod/cybrary/discuss.php", array('d' => $discussion->id));
                }

                redirect(cybrary_go_back_to($discussionurl));
            } else {
                print_error('errorwhiledelete', 'cybrary');
            }
        }


    } else { // User just asked to delete something

        cybrary_set_return();
        $PAGE->navbar->add(get_string('delete', 'cybrary'));
        $PAGE->set_title($course->shortname);
        $PAGE->set_heading($course->fullname);

        if ($replycount) {
            if (!has_capability('mod/cybrary:deleteanypost', $modcontext)) {
                print_error("couldnotdeletereplies", "cybrary",
                      cybrary_go_back_to(new moodle_url('/mod/cybrary/discuss.php', array('d' => $post->discussion), 'p'.$post->id)));
            }
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($cybrary->name), 2);
            echo $OUTPUT->confirm(get_string("deletesureplural", "cybrary", $replycount+1),
                         "post.php?delete=$delete&confirm=$delete",
                         $CFG->wwwroot.'/mod/cybrary/discuss.php?d='.$post->discussion.'#p'.$post->id);

            cybrary_print_post($post, $discussion, $cybrary, $cm, $course, false, false, false);

            if (empty($post->edit)) {
                $cybrarytracked = cybrary_tp_is_tracked($cybrary);
                $posts = cybrary_get_all_discussion_posts($discussion->id, "created ASC", $cybrarytracked);
                cybrary_print_posts_nested($course, $cm, $cybrary, $discussion, $post, false, false, $cybrarytracked, $posts);
            }
        } else {
            echo $OUTPUT->header();
            echo $OUTPUT->heading(format_string($cybrary->name), 2);
            echo $OUTPUT->confirm(get_string("deletesure", "cybrary", $replycount),
                         "post.php?delete=$delete&confirm=$delete",
                         $CFG->wwwroot.'/mod/cybrary/discuss.php?d='.$post->discussion.'#p'.$post->id);
            cybrary_print_post($post, $discussion, $cybrary, $cm, $course, false, false, false);
        }

    }
    echo $OUTPUT->footer();
    die;


} else if (!empty($prune)) {  // Pruning

    if (!$post = cybrary_get_post_full($prune)) {
        print_error('invalidpostid', 'cybrary');
    }
    if (!$discussion = $DB->get_record("cybrary_discussions", array("id" => $post->discussion))) {
        print_error('notpartofdiscussion', 'cybrary');
    }
    if (!$cybrary = $DB->get_record("cybrary", array("id" => $discussion->cybrary))) {
        print_error('invalidcybraryid', 'cybrary');
    }
    if ($cybrary->type == 'single') {
        print_error('cannotsplit', 'cybrary');
    }
    if (!$post->parent) {
        print_error('alreadyfirstpost', 'cybrary');
    }
    if (!$cm = get_coursemodule_from_instance("cybrary", $cybrary->id, $cybrary->course)) { // For the logs
        print_error('invalidcoursemodule');
    } else {
        $modcontext = context_module::instance($cm->id);
    }
    if (!has_capability('mod/cybrary:splitdiscussions', $modcontext)) {
        print_error('cannotsplit', 'cybrary');
    }

    $PAGE->set_cm($cm);
    $PAGE->set_context($modcontext);

    $prunemform = new mod_cybrary_prune_form(null, array('prune' => $prune, 'confirm' => $prune));


    if ($prunemform->is_cancelled()) {
        redirect(cybrary_go_back_to(new moodle_url("/mod/cybrary/discuss.php", array('d' => $post->discussion))));
    } else if ($fromform = $prunemform->get_data()) {
        // User submits the data.
        $newdiscussion = new stdClass();
        $newdiscussion->course       = $discussion->course;
        $newdiscussion->cybrary        = $discussion->cybrary;
        $newdiscussion->name         = $name;
        $newdiscussion->firstpost    = $post->id;
        $newdiscussion->userid       = $discussion->userid;
        $newdiscussion->groupid      = $discussion->groupid;
        $newdiscussion->assessed     = $discussion->assessed;
        $newdiscussion->usermodified = $post->userid;
        $newdiscussion->timestart    = $discussion->timestart;
        $newdiscussion->timeend      = $discussion->timeend;

        $newid = $DB->insert_record('cybrary_discussions', $newdiscussion);

        $newpost = new stdClass();
        $newpost->id      = $post->id;
        $newpost->parent  = 0;
        $newpost->subject = $name;

        $DB->update_record("cybrary_posts", $newpost);

        cybrary_change_discussionid($post->id, $newid);

        // Update last post in each discussion.
        cybrary_discussion_update_last_post($discussion->id);
        cybrary_discussion_update_last_post($newid);

        // Fire events to reflect the split..
        $params = array(
            'context' => $modcontext,
            'objectid' => $discussion->id,
            'other' => array(
                'cybraryid' => $cybrary->id,
            )
        );
        $event = \mod_cybrary\event\discussion_updated::create($params);
        $event->trigger();

        $params = array(
            'context' => $modcontext,
            'objectid' => $newid,
            'other' => array(
                'cybraryid' => $cybrary->id,
            )
        );
        $event = \mod_cybrary\event\discussion_created::create($params);
        $event->trigger();

        $params = array(
            'context' => $modcontext,
            'objectid' => $post->id,
            'other' => array(
                'discussionid' => $newid,
                'cybraryid' => $cybrary->id,
                'cybrarytype' => $cybrary->type,
            )
        );
        $event = \mod_cybrary\event\post_updated::create($params);
        $event->add_record_snapshot('cybrary_discussions', $discussion);
        $event->trigger();

        redirect(cybrary_go_back_to(new moodle_url("/mod/cybrary/discuss.php", array('d' => $newid))));

    } else {
        // Display the prune form.
        $course = $DB->get_record('course', array('id' => $cybrary->course));
        $PAGE->navbar->add(format_string($post->subject, true), new moodle_url('/mod/cybrary/discuss.php', array('d'=>$discussion->id)));
        $PAGE->navbar->add(get_string("prune", "cybrary"));
        $PAGE->set_title(format_string($discussion->name).": ".format_string($post->subject));
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();
        echo $OUTPUT->heading(format_string($cybrary->name), 2);
        echo $OUTPUT->heading(get_string('pruneheading', 'cybrary'), 3);

        $prunemform->display();

        cybrary_print_post($post, $discussion, $cybrary, $cm, $course, false, false, false);
    }

    echo $OUTPUT->footer();
    die;
} else {
    print_error('unknowaction');

}

if (!isset($coursecontext)) {
    // Has not yet been set by post.php.
    $coursecontext = context_course::instance($cybrary->course);
}


// from now on user must be logged on properly

if (!$cm = get_coursemodule_from_instance('cybrary', $cybrary->id, $course->id)) { // For the logs
    print_error('invalidcoursemodule');
}
$modcontext = context_module::instance($cm->id);
require_login($course, false, $cm);

if (isguestuser()) {
    // just in case
    print_error('noguest');
}

if (!isset($cybrary->maxattachments)) {  // TODO - delete this once we add a field to the cybrary table
    $cybrary->maxattachments = 3;
}

$thresholdwarning = cybrary_check_throttling($cybrary, $cm);
$mform_post = new mod_cybrary_post_form('post.php', array('course' => $course,
                                                        'cm' => $cm,
                                                        'coursecontext' => $coursecontext,
                                                        'modcontext' => $modcontext,
                                                        'cybrary' => $cybrary,
                                                        'post' => $post,
                                                        'subscribe' => \mod_cybrary\subscriptions::is_subscribed($USER->id, $cybrary,
                                                                null, $cm),
                                                        'thresholdwarning' => $thresholdwarning,
                                                        'edit' => $edit), 'post', '', array('id' => 'mformcybrary'));

$draftitemid = file_get_submitted_draft_itemid('attachments');
file_prepare_draft_area($draftitemid, $modcontext->id, 'mod_cybrary', 'attachment', empty($post->id)?null:$post->id, mod_cybrary_post_form::attachment_options($cybrary));

//load data into form NOW!

if ($USER->id != $post->userid) {   // Not the original author, so add a message to the end
    $data = new stdClass();
    $data->date = userdate($post->modified);
    if ($post->messageformat == FORMAT_HTML) {
        $data->name = '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$USER->id.'&course='.$post->course.'">'.
                       fullname($USER).'</a>';
        $post->message .= '<p><span class="edited">('.get_string('editedby', 'cybrary', $data).')</span></p>';
    } else {
        $data->name = fullname($USER);
        $post->message .= "\n\n(".get_string('editedby', 'cybrary', $data).')';
    }
    unset($data);
}

$formheading = '';
if (!empty($parent)) {
    $heading = get_string("yourreply", "cybrary");
    $formheading = get_string('reply', 'cybrary');
} else {
    if ($cybrary->type == 'qanda') {
        $heading = get_string('yournewquestion', 'cybrary');
    } else {
        $heading = get_string('yournewtopic', 'cybrary');
    }
}

$postid = empty($post->id) ? null : $post->id;
$draftid_editor = file_get_submitted_draft_itemid('message');
$currenttext = file_prepare_draft_area($draftid_editor, $modcontext->id, 'mod_cybrary', 'post', $postid, mod_cybrary_post_form::editor_options($modcontext, $postid), $post->message);

$manageactivities = has_capability('moodle/course:manageactivities', $coursecontext);
if (\mod_cybrary\subscriptions::subscription_disabled($cybrary) && !$manageactivities) {
    // User does not have permission to subscribe to this discussion at all.
    $discussionsubscribe = false;
} else if (\mod_cybrary\subscriptions::is_forcesubscribed($cybrary)) {
    // User does not have permission to unsubscribe from this discussion at all.
    $discussionsubscribe = true;
} else {
    if (isset($discussion) && \mod_cybrary\subscriptions::is_subscribed($USER->id, $cybrary, $discussion->id, $cm)) {
        // User is subscribed to the discussion - continue the subscription.
        $discussionsubscribe = true;
    } else if (!isset($discussion) && \mod_cybrary\subscriptions::is_subscribed($USER->id, $cybrary, null, $cm)) {
        // Starting a new discussion, and the user is subscribed to the cybrary - subscribe to the discussion.
        $discussionsubscribe = true;
    } else {
        // User is not subscribed to either cybrary or discussion. Follow user preference.
        $discussionsubscribe = $USER->autosubscribe;
    }
}

$mform_post->set_data(array(        'attachments'=>$draftitemid,
                                    'general'=>$heading,
                                    'subject'=>$post->subject,
                                    'message'=>array(
                                        'text'=>$currenttext,
                                        'format'=>empty($post->messageformat) ? editors_get_preferred_format() : $post->messageformat,
                                        'itemid'=>$draftid_editor
                                    ),
                                    'discussionsubscribe' => $discussionsubscribe,
                                    'mailnow'=>!empty($post->mailnow),
                                    'userid'=>$post->userid,
                                    'parent'=>$post->parent,
                                    'discussion'=>$post->discussion,
                                    'course'=>$course->id) +
                                    $page_params +

                            (isset($post->format)?array(
                                    'format'=>$post->format):
                                array())+

                            (isset($discussion->timestart)?array(
                                    'timestart'=>$discussion->timestart):
                                array())+

                            (isset($discussion->timeend)?array(
                                    'timeend'=>$discussion->timeend):
                                array())+

                            (isset($post->groupid)?array(
                                    'groupid'=>$post->groupid):
                                array())+

                            (isset($discussion->id)?
                                    array('discussion'=>$discussion->id):
                                    array()));

if ($mform_post->is_cancelled()) {
    if (!isset($discussion->id) || $cybrary->type === 'qanda') {
        // Q and A cybraries don't have a discussion page, so treat them like a new thread..
        redirect(new moodle_url('/mod/cybrary/view.php', array('f' => $cybrary->id)));
    } else {
        redirect(new moodle_url('/mod/cybrary/discuss.php', array('d' => $discussion->id)));
    }
} else if ($fromform = $mform_post->get_data()) {

    if (empty($SESSION->fromurl)) {
        $errordestination = "$CFG->wwwroot/mod/cybrary/view.php?f=$cybrary->id";
    } else {
        $errordestination = $SESSION->fromurl;
    }

    $fromform->itemid        = $fromform->message['itemid'];
    $fromform->messageformat = $fromform->message['format'];
    $fromform->message       = $fromform->message['text'];
    // WARNING: the $fromform->message array has been overwritten, do not use it anymore!
    $fromform->messagetrust  = trusttext_trusted($modcontext);

    if ($fromform->edit) {           // Updating a post
        unset($fromform->groupid);
        $fromform->id = $fromform->edit;
        $message = '';

        //fix for bug #4314
        if (!$realpost = $DB->get_record('cybrary_posts', array('id' => $fromform->id))) {
            $realpost = new stdClass();
            $realpost->userid = -1;
        }


        // if user has edit any post capability
        // or has either startnewdiscussion or reply capability and is editting own post
        // then he can proceed
        // MDL-7066
        if ( !(($realpost->userid == $USER->id && (has_capability('mod/cybrary:replypost', $modcontext)
                            || has_capability('mod/cybrary:startdiscussion', $modcontext))) ||
                            has_capability('mod/cybrary:editanypost', $modcontext)) ) {
            print_error('cannotupdatepost', 'cybrary');
        }

        // If the user has access to all groups and they are changing the group, then update the post.
        if (isset($fromform->groupinfo) && has_capability('mod/cybrary:movediscussions', $modcontext)) {
            if (empty($fromform->groupinfo)) {
                $fromform->groupinfo = -1;
            }

            if (!cybrary_user_can_post_discussion($cybrary, $fromform->groupinfo, null, $cm, $modcontext)) {
                print_error('cannotupdatepost', 'cybrary');
            }

            $DB->set_field('cybrary_discussions' ,'groupid' , $fromform->groupinfo, array('firstpost' => $fromform->id));
        }

        $updatepost = $fromform; //realpost
        $updatepost->cybrary = $cybrary->id;
        if (!cybrary_update_post($updatepost, $mform_post, $message)) {
            print_error("couldnotupdate", "cybrary", $errordestination);
        }

        // MDL-11818
        if (($cybrary->type == 'single') && ($updatepost->parent == '0')){ // updating first post of single discussion type -> updating cybrary intro
            $cybrary->intro = $updatepost->message;
            $cybrary->timemodified = time();
            $DB->update_record("cybrary", $cybrary);
        }

        $timemessage = 2;
        if (!empty($message)) { // if we're printing stuff about the file upload
            $timemessage = 4;
        }

        if ($realpost->userid == $USER->id) {
            $message .= '<br />'.get_string("postupdated", "cybrary");
        } else {
            $realuser = $DB->get_record('user', array('id' => $realpost->userid));
            $message .= '<br />'.get_string("editedpostupdated", "cybrary", fullname($realuser));
        }

        if ($subscribemessage = cybrary_post_subscription($fromform, $cybrary, $discussion)) {
            $timemessage = 4;
        }
        if ($cybrary->type == 'single') {
            // Single discussion cybraries are an exception. We show
            // the cybrary itself since it only has one discussion
            // thread.
            $discussionurl = new moodle_url("/mod/cybrary/view.php", array('f' => $cybrary->id));
        } else {
            $discussionurl = new moodle_url("/mod/cybrary/discuss.php", array('d' => $discussion->id), 'p' . $fromform->id);
        }

        $params = array(
            'context' => $modcontext,
            'objectid' => $fromform->id,
            'other' => array(
                'discussionid' => $discussion->id,
                'cybraryid' => $cybrary->id,
                'cybrarytype' => $cybrary->type,
            )
        );

        if ($realpost->userid !== $USER->id) {
            $params['relateduserid'] = $realpost->userid;
        }

        $event = \mod_cybrary\event\post_updated::create($params);
        $event->add_record_snapshot('cybrary_discussions', $discussion);
        $event->trigger();

        redirect(cybrary_go_back_to($discussionurl), $message.$subscribemessage, $timemessage);

        exit;


    } else if ($fromform->discussion) { // Adding a new post to an existing discussion
        // Before we add this we must check that the user will not exceed the blocking threshold.
        cybrary_check_blocking_threshold($thresholdwarning);

        unset($fromform->groupid);
        $message = '';
        $addpost = $fromform;
        $addpost->cybrary=$cybrary->id;
        if ($fromform->id = cybrary_add_new_post($addpost, $mform_post, $message)) {
            $timemessage = 2;
            if (!empty($message)) { // if we're printing stuff about the file upload
                $timemessage = 4;
            }

            if ($subscribemessage = cybrary_post_subscription($fromform, $cybrary, $discussion)) {
                $timemessage = 4;
            }

            if (!empty($fromform->mailnow)) {
                $message .= get_string("postmailnow", "cybrary");
                $timemessage = 4;
            } else {
                $message .= '<p>'.get_string("postaddedsuccess", "cybrary") . '</p>';
                $message .= '<p>'.get_string("postaddedtimeleft", "cybrary", format_time($CFG->maxeditingtime)) . '</p>';
            }

            if ($cybrary->type == 'single') {
                // Single discussion cybraries are an exception. We show
                // the cybrary itself since it only has one discussion
                // thread.
                $discussionurl = new moodle_url("/mod/cybrary/view.php", array('f' => $cybrary->id), 'p'.$fromform->id);
            } else {
                $discussionurl = new moodle_url("/mod/cybrary/discuss.php", array('d' => $discussion->id), 'p'.$fromform->id);
            }

            $params = array(
                'context' => $modcontext,
                'objectid' => $fromform->id,
                'other' => array(
                    'discussionid' => $discussion->id,
                    'cybraryid' => $cybrary->id,
                    'cybrarytype' => $cybrary->type,
                )
            );
            $event = \mod_cybrary\event\post_created::create($params);
            $event->add_record_snapshot('cybrary_posts', $fromform);
            $event->add_record_snapshot('cybrary_discussions', $discussion);
            $event->trigger();

            // Update completion state
            $completion=new completion_info($course);
            if($completion->is_enabled($cm) &&
                ($cybrary->completionreplies || $cybrary->completionposts)) {
                $completion->update_state($cm,COMPLETION_COMPLETE);
            }

            redirect(cybrary_go_back_to($discussionurl), $message.$subscribemessage, $timemessage);

        } else {
            print_error("couldnotadd", "cybrary", $errordestination);
        }
        exit;

    } else { // Adding a new discussion.
        // The location to redirect to after successfully posting.
        $redirectto = new moodle_url('view.php', array('f' => $fromform->cybrary));

        $fromform->mailnow = empty($fromform->mailnow) ? 0 : 1;

        $discussion = $fromform;
        $discussion->name = $fromform->subject;

        $newstopic = false;
        if ($cybrary->type == 'news' && !$fromform->parent) {
            $newstopic = true;
        }
        $discussion->timestart = $fromform->timestart;
        $discussion->timeend = $fromform->timeend;

        $allowedgroups = array();
        $groupstopostto = array();

        // If we are posting a copy to all groups the user has access to.
        if (isset($fromform->posttomygroups)) {
            // Post to each of my groups.
            require_capability('mod/cybrary:canposttomygroups', $modcontext);

            // Fetch all of this user's groups.
            // Note: all groups are returned when in visible groups mode so we must manually filter.
            $allowedgroups = groups_get_activity_allowed_groups($cm);
            foreach ($allowedgroups as $groupid => $group) {
                if (cybrary_user_can_post_discussion($cybrary, $groupid, -1, $cm, $modcontext)) {
                    $groupstopostto[] = $groupid;
                }
            }
        } else if (isset($fromform->groupinfo)) {
            // Use the value provided in the dropdown group selection.
            $groupstopostto[] = $fromform->groupinfo;
            $redirectto->param('group', $fromform->groupinfo);
        } else if (isset($fromform->groupid) && !empty($fromform->groupid)) {
            // Use the value provided in the hidden form element instead.
            $groupstopostto[] = $fromform->groupid;
            $redirectto->param('group', $fromform->groupid);
        } else {
            // Use the value for all participants instead.
            $groupstopostto[] = -1;
        }

        // Before we post this we must check that the user will not exceed the blocking threshold.
        cybrary_check_blocking_threshold($thresholdwarning);

        foreach ($groupstopostto as $group) {
            if (!cybrary_user_can_post_discussion($cybrary, $group, -1, $cm, $modcontext)) {
                print_error('cannotcreatediscussion', 'cybrary');
            }

            $discussion->groupid = $group;
            $message = '';
            if ($discussion->id = cybrary_add_discussion($discussion, $mform_post, $message)) {

                $params = array(
                    'context' => $modcontext,
                    'objectid' => $discussion->id,
                    'other' => array(
                        'cybraryid' => $cybrary->id,
                    )
                );
                $event = \mod_cybrary\event\discussion_created::create($params);
                $event->add_record_snapshot('cybrary_discussions', $discussion);
                $event->trigger();

                $timemessage = 2;
                if (!empty($message)) { // If we're printing stuff about the file upload.
                    $timemessage = 4;
                }

                if ($fromform->mailnow) {
                    $message .= get_string("postmailnow", "cybrary");
                    $timemessage = 4;
                } else {
                    $message .= '<p>'.get_string("postaddedsuccess", "cybrary") . '</p>';
                    $message .= '<p>'.get_string("postaddedtimeleft", "cybrary", format_time($CFG->maxeditingtime)) . '</p>';
                }

                if ($subscribemessage = cybrary_post_subscription($fromform, $cybrary, $discussion)) {
                    $timemessage = 6;
                }
            } else {
                print_error("couldnotadd", "cybrary", $errordestination);
            }
        }

        // Update completion status.
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) &&
                ($cybrary->completiondiscussions || $cybrary->completionposts)) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }

        // Redirect back to the discussion.
        redirect(cybrary_go_back_to($redirectto->out()), $message . $subscribemessage, $timemessage);
    }
}



// To get here they need to edit a post, and the $post
// variable will be loaded with all the particulars,
// so bring up the form.

// $course, $cybrary are defined.  $discussion is for edit and reply only.

if ($post->discussion) {
    if (! $toppost = $DB->get_record("cybrary_posts", array("discussion" => $post->discussion, "parent" => 0))) {
        print_error('cannotfindparentpost', 'cybrary', '', $post->id);
    }
} else {
    $toppost = new stdClass();
    $toppost->subject = ($cybrary->type == "news") ? get_string("addanewtopic", "cybrary") :
                                                   get_string("addanewdiscussion", "cybrary");
}

if (empty($post->edit)) {
    $post->edit = '';
}

if (empty($discussion->name)) {
    if (empty($discussion)) {
        $discussion = new stdClass();
    }
    $discussion->name = $cybrary->name;
}
if ($cybrary->type == 'single') {
    // There is only one discussion thread for this cybrary type. We should
    // not show the discussion name (same as cybrary name in this case) in
    // the breadcrumbs.
    $strdiscussionname = '';
} else {
    // Show the discussion name in the breadcrumbs.
    $strdiscussionname = format_string($discussion->name).':';
}

$forcefocus = empty($reply) ? NULL : 'message';

if (!empty($discussion->id)) {
    $PAGE->navbar->add(format_string($toppost->subject, true), "discuss.php?d=$discussion->id");
}

if ($post->parent) {
    $PAGE->navbar->add(get_string('reply', 'cybrary'));
}

if ($edit) {
    $PAGE->navbar->add(get_string('edit', 'cybrary'));
}

$PAGE->set_title("$course->shortname: $strdiscussionname ".format_string($toppost->subject));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($cybrary->name), 2);

// checkup
if (!empty($parent) && !cybrary_user_can_see_post($cybrary, $discussion, $post, null, $cm)) {
    print_error('cannotreply', 'cybrary');
}
if (empty($parent) && empty($edit) && !cybrary_user_can_post_discussion($cybrary, $groupid, -1, $cm, $modcontext)) {
    print_error('cannotcreatediscussion', 'cybrary');
}

if ($cybrary->type == 'qanda'
            && !has_capability('mod/cybrary:viewqandawithoutposting', $modcontext)
            && !empty($discussion->id)
            && !cybrary_user_has_posted($cybrary->id, $discussion->id, $USER->id)) {
    echo $OUTPUT->notification(get_string('qandanotify','cybrary'));
}

// If there is a warning message and we are not editing a post we need to handle the warning.
if (!empty($thresholdwarning) && !$edit) {
    // Here we want to throw an exception if they are no longer allowed to post.
    cybrary_check_blocking_threshold($thresholdwarning);
}

if (!empty($parent)) {
    if (!$discussion = $DB->get_record('cybrary_discussions', array('id' => $parent->discussion))) {
        print_error('notpartofdiscussion', 'cybrary');
    }

    cybrary_print_post($parent, $discussion, $cybrary, $cm, $course, false, false, false);
    if (empty($post->edit)) {
        if ($cybrary->type != 'qanda' || cybrary_user_can_see_discussion($cybrary, $discussion, $modcontext)) {
            $cybrarytracked = cybrary_tp_is_tracked($cybrary);
            $posts = cybrary_get_all_discussion_posts($discussion->id, "created ASC", $cybrarytracked);
            cybrary_print_posts_threaded($course, $cm, $cybrary, $discussion, $parent, 0, false, $cybrarytracked, $posts);
        }
    }
} else {
    if (!empty($cybrary->intro)) {
        echo $OUTPUT->box(format_module_intro('cybrary', $cybrary, $cm->id), 'generalbox', 'intro');

        if (!empty($CFG->enableplagiarism)) {
            require_once($CFG->libdir.'/plagiarismlib.php');
            echo plagiarism_print_disclosure($cm->id);
        }
    }
}

if (!empty($formheading)) {
    echo $OUTPUT->heading($formheading, 2, array('class' => 'accesshide'));
}
$mform_post->display();

echo $OUTPUT->footer();

