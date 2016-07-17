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

defined('MOODLE_INTERNAL') || die();

/** Include required files */
require_once(__DIR__ . '/deprecatedlib.php');
require_once($CFG->libdir.'/filelib.php');
require_once("$CFG->libdir/resourcelib.php");
require_once($CFG->libdir.'/eventslib.php');
require_once('locallib.php');

/// CONSTANTS ///////////////////////////////////////////////////////////

define('CYBRARY_MODE_FLATOLDEST', 1);
define('CYBRARY_MODE_FLATNEWEST', -1);
define('CYBRARY_MODE_THREADED', 2);
define('CYBRARY_MODE_NESTED', 3);

define('CYBRARY_CHOOSESUBSCRIBE', 0);
define('CYBRARY_FORCESUBSCRIBE', 1);
define('CYBRARY_INITIALSUBSCRIBE', 2);
define('CYBRARY_DISALLOWSUBSCRIBE',3);

/**
 * CYBRARY_TRACKING_OFF - Tracking is not available for this cybrary.
 */
define('CYBRARY_TRACKING_OFF', 0);

/**
 * CYBRARY_TRACKING_OPTIONAL - Tracking is based on user preference.
 */
define('CYBRARY_TRACKING_OPTIONAL', 1);

/**
 * CYBRARY_TRACKING_FORCED - Tracking is on, regardless of user setting.
 * Treated as CYBRARY_TRACKING_OPTIONAL if $CFG->cybrary_allowforcedreadtracking is off.
 */
define('CYBRARY_TRACKING_FORCED', 2);

define('CYBRARY_MAILED_PENDING', 0);
define('CYBRARY_MAILED_SUCCESS', 1);
define('CYBRARY_MAILED_ERROR', 2);

if (!defined('CYBRARY_CRON_USER_CACHE')) {
    /** Defines how many full user records are cached in cybrary cron. */
    define('CYBRARY_CRON_USER_CACHE', 5000);
}

/**
 * CYBRARY_POSTS_ALL_USER_GROUPS - All the posts in groups where the user is enrolled.
 */
define('CYBRARY_POSTS_ALL_USER_GROUPS', -2);

/// STANDARD FUNCTIONS ///////////////////////////////////////////////////////////

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $cybrary add cybrary instance
 * @param mod_cybrary_mod_form $mform
 * @return int intance id
 */
function cybrary_add_instance($cybrary, $mform = null) {
    global $CFG, $DB;

    $cybrary->timemodified = time();

    if (empty($cybrary->assessed)) {
        $cybrary->assessed = 0;
    }

    if (empty($cybrary->ratingtime) or empty($cybrary->assessed)) {
        $cybrary->assesstimestart  = 0;
        $cybrary->assesstimefinish = 0;
    }

    $parameters = array();
    for ($i=0; $i < 100; $i++) {
        $parameter = "parameter_$i";
        $variable  = "variable_$i";
        if (empty($cybrary->$parameter) or empty($cybrary->$variable)) {
            continue;
        }
        $parameters[$cybrary->$parameter] = $cybrary->$variable;
    }
    $cybrary->parameters = serialize($parameters);

    $displayoptions = array();
    if ($cybrary->display == RESOURCELIB_DISPLAY_POPUP) {
        $displayoptions['popupwidth']  = $cybrary->popupwidth;
        $displayoptions['popupheight'] = $cybrary->popupheight;
    }
    if (in_array($cybrary->display, array(RESOURCELIB_DISPLAY_AUTO, RESOURCELIB_DISPLAY_EMBED, RESOURCELIB_DISPLAY_FRAME))) {
        $displayoptions['printintro']   = (int)!empty($cybrary->printintro);
    }
    $cybrary->displayoptions = serialize($displayoptions);

    $cybrary->externalurl = url_fix_submitted_url($cybrary->externalurl);

    $cybrary->id = $DB->insert_record('cybrary', $cybrary);
    $modcontext = context_module::instance($cybrary->coursemodule);

    if ($cybrary->type == 'single') {  // Create related discussion.
        $discussion = new stdClass();
        $discussion->course        = $cybrary->course;
        $discussion->cybrary         = $cybrary->id;
        $discussion->name          = $cybrary->name;
        $discussion->assessed      = $cybrary->assessed;
        $discussion->message       = $cybrary->intro;
        $discussion->messageformat = $cybrary->introformat;
        $discussion->messagetrust  = trusttext_trusted(context_course::instance($cybrary->course));
        $discussion->mailnow       = false;
        $discussion->groupid       = -1;

        $message = '';

        $discussion->id = cybrary_add_discussion($discussion, null, $message);

        if ($mform and $draftid = file_get_submitted_draft_itemid('introeditor')) {
            // Ugly hack - we need to copy the files somehow.
            $discussion = $DB->get_record('cybrary_discussions', array('id'=>$discussion->id), '*', MUST_EXIST);
            $post = $DB->get_record('cybrary_posts', array('id'=>$discussion->firstpost), '*', MUST_EXIST);

            $options = array('subdirs'=>true); // Use the same options as intro field!
            $post->message = file_save_draft_area_files($draftid, $modcontext->id, 'mod_cybrary', 'post', $post->id, $options, $post->message);
            $DB->set_field('cybrary_posts', 'message', $post->message, array('id'=>$post->id));
        }
    }

    cybrary_grade_item_update($cybrary);

    return $cybrary->id;
}

/**
 * Handle changes following the creation of a cybrary instance.
 * This function is typically called by the course_module_created observer.
 *
 * @param object $context the cybrary context
 * @param stdClass $cybrary The cybrary object
 * @return void
 */
function cybrary_instance_created($context, $cybrary) {
    if ($cybrary->forcesubscribe == CYBRARY_INITIALSUBSCRIBE) {
        $users = \mod_cybrary\subscriptions::get_potential_subscribers($context, 0, 'u.id, u.email');
        foreach ($users as $user) {
            \mod_cybrary\subscriptions::subscribe_user($user->id, $cybrary, $context);
        }
    }
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @global object
 * @param object $cybrary cybrary instance (with magic quotes)
 * @return bool success
 */
function cybrary_update_instance($cybrary, $mform) {
    global $DB, $OUTPUT, $USER;

    $cybrary->timemodified = time();
    $cybrary->id           = $cybrary->instance;

    if (empty($cybrary->assessed)) {
        $cybrary->assessed = 0;
    }

    if (empty($cybrary->ratingtime) or empty($cybrary->assessed)) {
        $cybrary->assesstimestart  = 0;
        $cybrary->assesstimefinish = 0;
    }

    $oldcybrary = $DB->get_record('cybrary', array('id'=>$cybrary->id));

    // MDL-3942 - if the aggregation type or scale (i.e. max grade) changes then recalculate the grades for the entire cybrary
    // if  scale changes - do we need to recheck the ratings, if ratings higher than scale how do we want to respond?
    // for count and sum aggregation types the grade we check to make sure they do not exceed the scale (i.e. max score) when calculating the grade
    if (($oldcybrary->assessed<>$cybrary->assessed) or ($oldcybrary->scale<>$cybrary->scale)) {
        cybrary_update_grades($cybrary); // recalculate grades for the cybrary
    }

    if ($cybrary->type == 'single') {  // Update related discussion and post.
        $discussions = $DB->get_records('cybrary_discussions', array('cybrary'=>$cybrary->id), 'timemodified ASC');
        if (!empty($discussions)) {
            if (count($discussions) > 1) {
                echo $OUTPUT->notification(get_string('warnformorepost', 'cybrary'));
            }
            $discussion = array_pop($discussions);
        } else {
            // try to recover by creating initial discussion - MDL-16262
            $discussion = new stdClass();
            $discussion->course          = $cybrary->course;
            $discussion->cybrary           = $cybrary->id;
            $discussion->name            = $cybrary->name;
            $discussion->assessed        = $cybrary->assessed;
            $discussion->message         = $cybrary->intro;
            $discussion->messageformat   = $cybrary->introformat;
            $discussion->messagetrust    = true;
            $discussion->mailnow         = false;
            $discussion->groupid         = -1;

            $message = '';

            cybrary_add_discussion($discussion, null, $message);

            if (! $discussion = $DB->get_record('cybrary_discussions', array('cybrary'=>$cybrary->id))) {
                print_error('cannotadd', 'cybrary');
            }
        }
        if (! $post = $DB->get_record('cybrary_posts', array('id'=>$discussion->firstpost))) {
            print_error('cannotfindfirstpost', 'cybrary');
        }

        $cm         = get_coursemodule_from_instance('cybrary', $cybrary->id);
        $modcontext = context_module::instance($cm->id, MUST_EXIST);

        $post = $DB->get_record('cybrary_posts', array('id'=>$discussion->firstpost), '*', MUST_EXIST);
        $post->subject       = $cybrary->name;
        $post->message       = $cybrary->intro;
        $post->messageformat = $cybrary->introformat;
        $post->messagetrust  = trusttext_trusted($modcontext);
        $post->modified      = $cybrary->timemodified;
        $post->userid        = $USER->id;    // MDL-18599, so that current teacher can take ownership of activities.

        if ($mform and $draftid = file_get_submitted_draft_itemid('introeditor')) {
            // Ugly hack - we need to copy the files somehow.
            $options = array('subdirs'=>true); // Use the same options as intro field!
            $post->message = file_save_draft_area_files($draftid, $modcontext->id, 'mod_cybrary', 'post', $post->id, $options, $post->message);
        }

        $DB->update_record('cybrary_posts', $post);
        $discussion->name = $cybrary->name;
        $DB->update_record('cybrary_discussions', $discussion);
    }

    $DB->update_record('cybrary', $cybrary);

    $modcontext = context_module::instance($cybrary->coursemodule);
    if (($cybrary->forcesubscribe == CYBRARY_INITIALSUBSCRIBE) && ($oldcybrary->forcesubscribe <> $cybrary->forcesubscribe)) {
        $users = \mod_cybrary\subscriptions::get_potential_subscribers($modcontext, 0, 'u.id, u.email', '');
        foreach ($users as $user) {
            \mod_cybrary\subscriptions::subscribe_user($user->id, $cybrary, $modcontext);
        }
    }

    cybrary_grade_item_update($cybrary);

    return true;
}


/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @global object
 * @param int $id cybrary instance id
 * @return bool success
 */
function cybrary_delete_instance($id) {
    global $DB;

    if (!$cybrary = $DB->get_record('cybrary', array('id'=>$id))) {
        return false;
    }
    if (!$cm = get_coursemodule_from_instance('cybrary', $cybrary->id)) {
        return false;
    }
    if (!$course = $DB->get_record('course', array('id'=>$cm->course))) {
        return false;
    }

    $context = context_module::instance($cm->id);

    // now get rid of all files
    $fs = get_file_storage();
    $fs->delete_area_files($context->id);

    $result = true;

    // Delete digest and subscription preferences.
    $DB->delete_records('cybrary_digests', array('cybrary' => $cybrary->id));
    $DB->delete_records('cybrary_subscriptions', array('cybrary'=>$cybrary->id));
    $DB->delete_records('cybrary_discussion_subs', array('cybrary' => $cybrary->id));

    if ($discussions = $DB->get_records('cybrary_discussions', array('cybrary'=>$cybrary->id))) {
        foreach ($discussions as $discussion) {
            if (!cybrary_delete_discussion($discussion, true, $course, $cm, $cybrary)) {
                $result = false;
            }
        }
    }

    cybrary_tp_delete_read_records(-1, -1, -1, $cybrary->id);

    if (!$DB->delete_records('cybrary', array('id'=>$cybrary->id))) {
        $result = false;
    }

    cybrary_grade_item_delete($cybrary);

    return $result;
}


/**
 * Indicates API features that the cybrary supports.
 *
 * @uses FEATURE_GROUPS
 * @uses FEATURE_GROUPINGS
 * @uses FEATURE_MOD_INTRO
 * @uses FEATURE_COMPLETION_TRACKS_VIEWS
 * @uses FEATURE_COMPLETION_HAS_RULES
 * @uses FEATURE_GRADE_HAS_GRADE
 * @uses FEATURE_GRADE_OUTCOMES
 * @param string $feature
 * @return mixed True if yes (some features may use other values)
 */
function cybrary_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:                  return true;
        case FEATURE_GROUPINGS:               return true;
        case FEATURE_MOD_INTRO:               return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_COMPLETION_HAS_RULES:    return true;
        case FEATURE_GRADE_HAS_GRADE:         return true;
        case FEATURE_GRADE_OUTCOMES:          return true;
        case FEATURE_RATE:                    return true;
        case FEATURE_BACKUP_MOODLE2:          return true;
        case FEATURE_SHOW_DESCRIPTION:        return true;
        case FEATURE_PLAGIARISM:              return true;

        default: return null;
    }
}


/**
 * Obtains the automatic completion state for this cybrary based on any conditions
 * in cybrary settings.
 *
 * @global object
 * @global object
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not. (If no conditions, then return
 *   value depends on comparison type)
 */
function cybrary_get_completion_state($course,$cm,$userid,$type) {
    global $CFG,$DB;

    // Get cybrary details
    if (!($cybrary=$DB->get_record('cybrary',array('id'=>$cm->instance)))) {
        throw new Exception("Can't find cybrary {$cm->instance}");
    }

    $result=$type; // Default return value

    $postcountparams=array('userid'=>$userid,'cybraryid'=>$cybrary->id);
    $postcountsql="
SELECT
    COUNT(1)
FROM
    {cybrary_posts} fp
    INNER JOIN {cybrary_discussions} fd ON fp.discussion=fd.id
WHERE
    fp.userid=:userid AND fd.cybrary=:cybraryid";

    if ($cybrary->completiondiscussions) {
        $value = $cybrary->completiondiscussions <=
                 $DB->count_records('cybrary_discussions',array('cybrary'=>$cybrary->id,'userid'=>$userid));
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }
    if ($cybrary->completionreplies) {
        $value = $cybrary->completionreplies <=
                 $DB->get_field_sql( $postcountsql.' AND fp.parent<>0',$postcountparams);
        if ($type==COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }
    if ($cybrary->completionposts) {
        $value = $cybrary->completionposts <= $DB->get_field_sql($postcountsql,$postcountparams);
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }

    return $result;
}

/**
 * Create a message-id string to use in the custom headers of cybrary notification emails
 *
 * message-id is used by email clients to identify emails and to nest conversations
 *
 * @param int $postid The ID of the cybrary post we are notifying the user about
 * @param int $usertoid The ID of the user being notified
 * @param string $hostname The server's hostname
 * @return string A unique message-id
 */
function cybrary_get_email_message_id($postid, $usertoid, $hostname) {
    return '<'.hash('sha256',$postid.'to'.$usertoid).'@'.$hostname.'>';
}

/**
 * Removes properties from user record that are not necessary
 * for sending post notifications.
 * @param stdClass $user
 * @return void, $user parameter is modified
 */
function cybrary_cron_minimise_user_record(stdClass $user) {

    // We store large amount of users in one huge array,
    // make sure we do not store info there we do not actually need
    // in mail generation code or messaging.

    unset($user->institution);
    unset($user->department);
    unset($user->address);
    unset($user->city);
    unset($user->url);
    unset($user->currentlogin);
    unset($user->description);
    unset($user->descriptionformat);
}

/**
 * Function to be run periodically according to the scheduled task.
 *
 * Finds all posts that have yet to be mailed out, and mails them
 * out to all subscribers as well as other maintance tasks.
 *
 * NOTE: Since 2.7.2 this function is run by scheduled task rather
 * than standard cron.
 *
 * @todo MDL-44734 The function will be split up into seperate tasks.
 */
function cybrary_cron() {
    global $CFG, $USER, $DB, $PAGE;

    $site = get_site();

    // The main renderers.
    $htmlout = $PAGE->get_renderer('mod_cybrary', 'email', 'htmlemail');
    $textout = $PAGE->get_renderer('mod_cybrary', 'email', 'textemail');
    $htmldigestfullout = $PAGE->get_renderer('mod_cybrary', 'emaildigestfull', 'htmlemail');
    $textdigestfullout = $PAGE->get_renderer('mod_cybrary', 'emaildigestfull', 'textemail');
    $htmldigestbasicout = $PAGE->get_renderer('mod_cybrary', 'emaildigestbasic', 'htmlemail');
    $textdigestbasicout = $PAGE->get_renderer('mod_cybrary', 'emaildigestbasic', 'textemail');

    // All users that are subscribed to any post that needs sending,
    // please increase $CFG->extramemorylimit on large sites that
    // send notifications to a large number of users.
    $users = array();
    $userscount = 0; // Cached user counter - count($users) in PHP is horribly slow!!!

    // Status arrays.
    $mailcount  = array();
    $errorcount = array();

    // caches
    $discussions        = array();
    $cybraries             = array();
    $courses            = array();
    $coursemodules      = array();
    $subscribedusers    = array();
    $messageinboundhandlers = array();

    // Posts older than 2 days will not be mailed.  This is to avoid the problem where
    // cron has not been running for a long time, and then suddenly people are flooded
    // with mail from the past few weeks or months
    $timenow   = time();
    $endtime   = $timenow - $CFG->maxeditingtime;
    $starttime = $endtime - 48 * 3600;   // Two days earlier

    // Get the list of cybrary subscriptions for per-user per-cybrary maildigest settings.
    $digestsset = $DB->get_recordset('cybrary_digests', null, '', 'id, userid, cybrary, maildigest');
    $digests = array();
    foreach ($digestsset as $thisrow) {
        if (!isset($digests[$thisrow->cybrary])) {
            $digests[$thisrow->cybrary] = array();
        }
        $digests[$thisrow->cybrary][$thisrow->userid] = $thisrow->maildigest;
    }
    $digestsset->close();

    // Create the generic messageinboundgenerator.
    $messageinboundgenerator = new \core\message\inbound\address_manager();
    $messageinboundgenerator->set_handler('\mod_cybrary\message\inbound\reply_handler');

    if ($posts = cybrary_get_unmailed_posts($starttime, $endtime, $timenow)) {
        // Mark them all now as being mailed.  It's unlikely but possible there
        // might be an error later so that a post is NOT actually mailed out,
        // but since mail isn't crucial, we can accept this risk.  Doing it now
        // prevents the risk of duplicated mails, which is a worse problem.

        if (!cybrary_mark_old_posts_as_mailed($endtime)) {
            mtrace('Errors occurred while trying to mark some posts as being mailed.');
            return false;  // Don't continue trying to mail them, in case we are in a cron loop
        }

        // checking post validity, and adding users to loop through later
        foreach ($posts as $pid => $post) {

            $discussionid = $post->discussion;
            if (!isset($discussions[$discussionid])) {
                if ($discussion = $DB->get_record('cybrary_discussions', array('id'=> $post->discussion))) {
                    $discussions[$discussionid] = $discussion;
                    \mod_cybrary\subscriptions::fill_subscription_cache($discussion->cybrary);
                    \mod_cybrary\subscriptions::fill_discussion_subscription_cache($discussion->cybrary);

                } else {
                    mtrace('Could not find discussion ' . $discussionid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            $cybraryid = $discussions[$discussionid]->cybrary;
            if (!isset($cybraries[$cybraryid])) {
                if ($cybrary = $DB->get_record('cybrary', array('id' => $cybraryid))) {
                    $cybraries[$cybraryid] = $cybrary;
                } else {
                    mtrace('Could not find cybrary '.$cybraryid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            $courseid = $cybraries[$cybraryid]->course;
            if (!isset($courses[$courseid])) {
                if ($course = $DB->get_record('course', array('id' => $courseid))) {
                    $courses[$courseid] = $course;
                } else {
                    mtrace('Could not find course '.$courseid);
                    unset($posts[$pid]);
                    continue;
                }
            }
            if (!isset($coursemodules[$cybraryid])) {
                if ($cm = get_coursemodule_from_instance('cybrary', $cybraryid, $courseid)) {
                    $coursemodules[$cybraryid] = $cm;
                } else {
                    mtrace('Could not find course module for cybrary '.$cybraryid);
                    unset($posts[$pid]);
                    continue;
                }
            }

            // Save the Inbound Message datakey here to reduce DB queries later.
            $messageinboundgenerator->set_data($pid);
            $messageinboundhandlers[$pid] = $messageinboundgenerator->fetch_data_key();

            // Caching subscribed users of each cybrary.
            if (!isset($subscribedusers[$cybraryid])) {
                $modcontext = context_module::instance($coursemodules[$cybraryid]->id);
                if ($subusers = \mod_cybrary\subscriptions::fetch_subscribed_users($cybraries[$cybraryid], 0, $modcontext, 'u.*', true)) {

                    foreach ($subusers as $postuser) {
                        // this user is subscribed to this cybrary
                        $subscribedusers[$cybraryid][$postuser->id] = $postuser->id;
                        $userscount++;
                        if ($userscount > CYBRARY_CRON_USER_CACHE) {
                            // Store minimal user info.
                            $minuser = new stdClass();
                            $minuser->id = $postuser->id;
                            $users[$postuser->id] = $minuser;
                        } else {
                            // Cache full user record.
                            cybrary_cron_minimise_user_record($postuser);
                            $users[$postuser->id] = $postuser;
                        }
                    }
                    // Release memory.
                    unset($subusers);
                    unset($postuser);
                }
            }
            $mailcount[$pid] = 0;
            $errorcount[$pid] = 0;
        }
    }

    if ($users && $posts) {

        $urlinfo = parse_url($CFG->wwwroot);
        $hostname = $urlinfo['host'];

        foreach ($users as $userto) {
            // Terminate if processing of any account takes longer than 2 minutes.
            core_php_time_limit::raise(120);

            mtrace('Processing user ' . $userto->id);

            // Init user caches - we keep the cache for one cycle only, otherwise it could consume too much memory.
            if (isset($userto->username)) {
                $userto = clone($userto);
            } else {
                $userto = $DB->get_record('user', array('id' => $userto->id));
                cybrary_cron_minimise_user_record($userto);
            }
            $userto->viewfullnames = array();
            $userto->canpost       = array();
            $userto->markposts     = array();

            // Setup this user so that the capabilities are cached, and environment matches receiving user.
            cron_setup_user($userto);

            // Reset the caches.
            foreach ($coursemodules as $cybraryid => $unused) {
                $coursemodules[$cybraryid]->cache       = new stdClass();
                $coursemodules[$cybraryid]->cache->caps = array();
                unset($coursemodules[$cybraryid]->uservisible);
            }

            foreach ($posts as $pid => $post) {
                $discussion = $discussions[$post->discussion];
                $cybrary      = $cybraries[$discussion->cybrary];
                $course     = $courses[$cybrary->course];
                $cm         =& $coursemodules[$cybrary->id];

                // Do some checks to see if we can bail out now.

                // Only active enrolled users are in the list of subscribers.
                // This does not necessarily mean that the user is subscribed to the cybrary or to the discussion though.
                if (!isset($subscribedusers[$cybrary->id][$userto->id])) {
                    // The user does not subscribe to this cybrary.
                    continue;
                }

                if (!\mod_cybrary\subscriptions::is_subscribed($userto->id, $cybrary, $post->discussion, $coursemodules[$cybrary->id])) {
                    // The user does not subscribe to this cybrary, or to this specific discussion.
                    continue;
                }

                if ($subscriptiontime = \mod_cybrary\subscriptions::fetch_discussion_subscription($cybrary->id, $userto->id)) {
                    // Skip posts if the user subscribed to the discussion after it was created.
                    if (isset($subscriptiontime[$post->discussion]) && ($subscriptiontime[$post->discussion] > $post->created)) {
                        continue;
                    }
                }

                // Don't send email if the cybrary is Q&A and the user has not posted.
                // Initial topics are still mailed.
                if ($cybrary->type == 'qanda' && !cybrary_get_user_posted_time($discussion->id, $userto->id) && $pid != $discussion->firstpost) {
                    mtrace('Did not email ' . $userto->id.' because user has not posted in discussion');
                    continue;
                }

                // Get info about the sending user.
                if (array_key_exists($post->userid, $users)) {
                    // We might know the user already.
                    $userfrom = $users[$post->userid];
                    if (!isset($userfrom->idnumber)) {
                        // Minimalised user info, fetch full record.
                        $userfrom = $DB->get_record('user', array('id' => $userfrom->id));
                        cybrary_cron_minimise_user_record($userfrom);
                    }

                } else if ($userfrom = $DB->get_record('user', array('id' => $post->userid))) {
                    cybrary_cron_minimise_user_record($userfrom);
                    // Fetch only once if possible, we can add it to user list, it will be skipped anyway.
                    if ($userscount <= CYBRARY_CRON_USER_CACHE) {
                        $userscount++;
                        $users[$userfrom->id] = $userfrom;
                    }
                } else {
                    mtrace('Could not find user ' . $post->userid . ', author of post ' . $post->id . '. Unable to send message.');
                    continue;
                }

                // Note: If we want to check that userto and userfrom are not the same person this is probably the spot to do it.

                // Setup global $COURSE properly - needed for roles and languages.
                cron_setup_user($userto, $course);

                // Fill caches.
                if (!isset($userto->viewfullnames[$cybrary->id])) {
                    $modcontext = context_module::instance($cm->id);
                    $userto->viewfullnames[$cybrary->id] = has_capability('moodle/site:viewfullnames', $modcontext);
                }
                if (!isset($userto->canpost[$discussion->id])) {
                    $modcontext = context_module::instance($cm->id);
                    $userto->canpost[$discussion->id] = cybrary_user_can_post($cybrary, $discussion, $userto, $cm, $course, $modcontext);
                }
                if (!isset($userfrom->groups[$cybrary->id])) {
                    if (!isset($userfrom->groups)) {
                        $userfrom->groups = array();
                        if (isset($users[$userfrom->id])) {
                            $users[$userfrom->id]->groups = array();
                        }
                    }
                    $userfrom->groups[$cybrary->id] = groups_get_all_groups($course->id, $userfrom->id, $cm->groupingid);
                    if (isset($users[$userfrom->id])) {
                        $users[$userfrom->id]->groups[$cybrary->id] = $userfrom->groups[$cybrary->id];
                    }
                }

                // Make sure groups allow this user to see this email.
                if ($discussion->groupid > 0 and $groupmode = groups_get_activity_groupmode($cm, $course)) {
                    // Groups are being used.
                    if (!groups_group_exists($discussion->groupid)) {
                        // Can't find group - be safe and don't this message.
                        continue;
                    }

                    if (!groups_is_member($discussion->groupid) and !has_capability('moodle/site:accessallgroups', $modcontext)) {
                        // Do not send posts from other groups when in SEPARATEGROUPS or VISIBLEGROUPS.
                        continue;
                    }
                }

                // Make sure we're allowed to see the post.
                if (!cybrary_user_can_see_post($cybrary, $discussion, $post, null, $cm)) {
                    mtrace('User ' . $userto->id .' can not see ' . $post->id . '. Not sending message.');
                    continue;
                }

                // OK so we need to send the email.

                // Does the user want this post in a digest?  If so postpone it for now.
                $maildigest = cybrary_get_user_maildigest_bulk($digests, $userto, $cybrary->id);

                if ($maildigest > 0) {
                    // This user wants the mails to be in digest form.
                    $queue = new stdClass();
                    $queue->userid       = $userto->id;
                    $queue->discussionid = $discussion->id;
                    $queue->postid       = $post->id;
                    $queue->timemodified = $post->created;
                    $DB->insert_record('cybrary_queue', $queue);
                    continue;
                }

                // Prepare to actually send the post now, and build up the content.

                $cleancybraryname = str_replace('"', "'", strip_tags(format_string($cybrary->name)));

                $userfrom->customheaders = array (
                    // Headers to make emails easier to track.
                    'List-Id: "'        . $cleancybraryname . '" <moodlecybrary' . $cybrary->id . '@' . $hostname.'>',
                    'List-Help: '       . $CFG->wwwroot . '/mod/cybrary/view.php?f=' . $cybrary->id,
                    'Message-ID: '      . cybrary_get_email_message_id($post->id, $userto->id, $hostname),
                    'X-Course-Id: '     . $course->id,
                    'X-Course-Name: '   . format_string($course->fullname, true),

                    // Headers to help prevent auto-responders.
                    'Precedence: Bulk',
                    'X-Auto-Response-Suppress: All',
                    'Auto-Submitted: auto-generated',
                );

                $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));

                // Generate a reply-to address from using the Inbound Message handler.
                $replyaddress = null;
                if ($userto->canpost[$discussion->id] && array_key_exists($post->id, $messageinboundhandlers)) {
                    $messageinboundgenerator->set_data($post->id, $messageinboundhandlers[$post->id]);
                    $replyaddress = $messageinboundgenerator->generate($userto->id);
                }

                if (!isset($userto->canpost[$discussion->id])) {
                    $canreply = cybrary_user_can_post($cybrary, $discussion, $userto, $cm, $course, $modcontext);
                } else {
                    $canreply = $userto->canpost[$discussion->id];
                }

                $data = new \mod_cybrary\output\cybrary_post_email(
                        $course,
                        $cm,
                        $cybrary,
                        $discussion,
                        $post,
                        $userfrom,
                        $userto,
                        $canreply
                    );

                if (!isset($userto->viewfullnames[$cybrary->id])) {
                    $data->viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);
                } else {
                    $data->viewfullnames = $userto->viewfullnames[$cybrary->id];
                }

                $a = new stdClass();
                $a->courseshortname = $data->get_coursename();
                $a->cybraryname = $cleancybraryname;
                $a->subject = $data->get_subject();
                $postsubject = html_to_text(get_string('postmailsubject', 'cybrary', $a), 0);

                $rootid = cybrary_get_email_message_id($discussion->firstpost, $userto->id, $hostname);

                if ($post->parent) {
                    // This post is a reply, so add reply header (RFC 2822).
                    $parentid = cybrary_get_email_message_id($post->parent, $userto->id, $hostname);
                    $userfrom->customheaders[] = "In-Reply-To: $parentid";

                    // If the post is deeply nested we also reference the parent message id and
                    // the root message id (if different) to aid threading when parts of the email
                    // conversation have been deleted (RFC1036).
                    if ($post->parent != $discussion->firstpost) {
                        $userfrom->customheaders[] = "References: $rootid $parentid";
                    } else {
                        $userfrom->customheaders[] = "References: $parentid";
                    }
                }

                // MS Outlook / Office uses poorly documented and non standard headers, including
                // Thread-Topic which overrides the Subject and shouldn't contain Re: or Fwd: etc.
                $a->subject = $discussion->name;
                $threadtopic = html_to_text(get_string('postmailsubject', 'cybrary', $a), 0);
                $userfrom->customheaders[] = "Thread-Topic: $threadtopic";
                $userfrom->customheaders[] = "Thread-Index: " . substr($rootid, 1, 28);

                // Send the post now!
                mtrace('Sending ', '');

                $eventdata = new \core\message\message();
                $eventdata->component           = 'mod_cybrary';
                $eventdata->name                = 'posts';
                $eventdata->userfrom            = $userfrom;
                $eventdata->userto              = $userto;
                $eventdata->subject             = $postsubject;
                $eventdata->fullmessage         = $textout->render($data);
                $eventdata->fullmessageformat   = FORMAT_PLAIN;
                $eventdata->fullmessagehtml     = $htmlout->render($data);
                $eventdata->notification        = 1;
                $eventdata->replyto             = $replyaddress;
                if (!empty($replyaddress)) {
                    // Add extra text to email messages if they can reply back.
                    $textfooter = "\n\n" . get_string('replytopostbyemail', 'mod_cybrary');
                    $htmlfooter = html_writer::tag('p', get_string('replytopostbyemail', 'mod_cybrary'));
                    $additionalcontent = array('fullmessage' => array('footer' => $textfooter),
                                     'fullmessagehtml' => array('footer' => $htmlfooter));
                    $eventdata->set_additional_content('email', $additionalcontent);
                }

                // If cybrary_replytouser is not set then send mail using the noreplyaddress.
                if (empty($CFG->cybrary_replytouser)) {
                    $eventdata->userfrom = core_user::get_noreply_user();
                }

                $smallmessagestrings = new stdClass();
                $smallmessagestrings->user          = fullname($userfrom);
                $smallmessagestrings->cybraryname     = "$shortname: " . format_string($cybrary->name, true) . ": " . $discussion->name;
                $smallmessagestrings->message       = $post->message;

                // Make sure strings are in message recipients language.
                $eventdata->smallmessage = get_string_manager()->get_string('smallmessage', 'cybrary', $smallmessagestrings, $userto->lang);

                $contexturl = new moodle_url('/mod/cybrary/discuss.php', array('d' => $discussion->id), 'p' . $post->id);
                $eventdata->contexturl = $contexturl->out();
                $eventdata->contexturlname = $discussion->name;

                $mailresult = message_send($eventdata);
                if (!$mailresult) {
                    mtrace("Error: mod/cybrary/lib.php cybrary_cron(): Could not send out mail for id $post->id to user $userto->id".
                            " ($userto->email) .. not trying again.");
                    $errorcount[$post->id]++;
                } else {
                    $mailcount[$post->id]++;

                    // Mark post as read if cybrary_usermarksread is set off.
                    if (!$CFG->cybrary_usermarksread) {
                        $userto->markposts[$post->id] = $post->id;
                    }
                }

                mtrace('post ' . $post->id . ': ' . $post->subject);
            }

            // Mark processed posts as read.
            cybrary_tp_mark_posts_read($userto, $userto->markposts);
            unset($userto);
        }
    }

    if ($posts) {
        foreach ($posts as $post) {
            mtrace($mailcount[$post->id]." users were sent post $post->id, '$post->subject'");
            if ($errorcount[$post->id]) {
                $DB->set_field('cybrary_posts', 'mailed', CYBRARY_MAILED_ERROR, array('id' => $post->id));
            }
        }
    }

    // release some memory
    unset($subscribedusers);
    unset($mailcount);
    unset($errorcount);

    cron_setup_user();

    $sitetimezone = core_date::get_server_timezone();

    // Now see if there are any digest mails waiting to be sent, and if we should send them

    mtrace('Starting digest processing...');

    core_php_time_limit::raise(300); // terminate if not able to fetch all digests in 5 minutes

    if (!isset($CFG->digestmailtimelast)) {    // To catch the first time
        set_config('digestmailtimelast', 0);
    }

    $timenow = time();
    $digesttime = usergetmidnight($timenow, $sitetimezone) + ($CFG->digestmailtime * 3600);

    // Delete any really old ones (normally there shouldn't be any)
    $weekago = $timenow - (7 * 24 * 3600);
    $DB->delete_records_select('cybrary_queue', "timemodified < ?", array($weekago));
    mtrace ('Cleaned old digest records');

    if ($CFG->digestmailtimelast < $digesttime and $timenow > $digesttime) {

        mtrace('Sending cybrary digests: '.userdate($timenow, '', $sitetimezone));

        $digestposts_rs = $DB->get_recordset_select('cybrary_queue', "timemodified < ?", array($digesttime));

        if ($digestposts_rs->valid()) {

            // We have work to do
            $usermailcount = 0;

            //caches - reuse the those filled before too
            $discussionposts = array();
            $userdiscussions = array();

            foreach ($digestposts_rs as $digestpost) {
                if (!isset($posts[$digestpost->postid])) {
                    if ($post = $DB->get_record('cybrary_posts', array('id' => $digestpost->postid))) {
                        $posts[$digestpost->postid] = $post;
                    } else {
                        continue;
                    }
                }
                $discussionid = $digestpost->discussionid;
                if (!isset($discussions[$discussionid])) {
                    if ($discussion = $DB->get_record('cybrary_discussions', array('id' => $discussionid))) {
                        $discussions[$discussionid] = $discussion;
                    } else {
                        continue;
                    }
                }
                $cybraryid = $discussions[$discussionid]->cybrary;
                if (!isset($cybraries[$cybraryid])) {
                    if ($cybrary = $DB->get_record('cybrary', array('id' => $cybraryid))) {
                        $cybraries[$cybraryid] = $cybrary;
                    } else {
                        continue;
                    }
                }

                $courseid = $cybraries[$cybraryid]->course;
                if (!isset($courses[$courseid])) {
                    if ($course = $DB->get_record('course', array('id' => $courseid))) {
                        $courses[$courseid] = $course;
                    } else {
                        continue;
                    }
                }

                if (!isset($coursemodules[$cybraryid])) {
                    if ($cm = get_coursemodule_from_instance('cybrary', $cybraryid, $courseid)) {
                        $coursemodules[$cybraryid] = $cm;
                    } else {
                        continue;
                    }
                }
                $userdiscussions[$digestpost->userid][$digestpost->discussionid] = $digestpost->discussionid;
                $discussionposts[$digestpost->discussionid][$digestpost->postid] = $digestpost->postid;
            }
            $digestposts_rs->close(); /// Finished iteration, let's close the resultset

            // Data collected, start sending out emails to each user
            foreach ($userdiscussions as $userid => $thesediscussions) {

                core_php_time_limit::raise(120); // terminate if processing of any account takes longer than 2 minutes

                cron_setup_user();

                mtrace(get_string('processingdigest', 'cybrary', $userid), '... ');

                // First of all delete all the queue entries for this user
                $DB->delete_records_select('cybrary_queue', "userid = ? AND timemodified < ?", array($userid, $digesttime));

                // Init user caches - we keep the cache for one cycle only,
                // otherwise it would unnecessarily consume memory.
                if (array_key_exists($userid, $users) and isset($users[$userid]->username)) {
                    $userto = clone($users[$userid]);
                } else {
                    $userto = $DB->get_record('user', array('id' => $userid));
                    cybrary_cron_minimise_user_record($userto);
                }
                $userto->viewfullnames = array();
                $userto->canpost       = array();
                $userto->markposts     = array();

                // Override the language and timezone of the "current" user, so that
                // mail is customised for the receiver.
                cron_setup_user($userto);

                $postsubject = get_string('digestmailsubject', 'cybrary', format_string($site->shortname, true));

                $headerdata = new stdClass();
                $headerdata->sitename = format_string($site->fullname, true);
                $headerdata->userprefs = $CFG->wwwroot.'/user/edit.php?id='.$userid.'&amp;course='.$site->id;

                $posttext = get_string('digestmailheader', 'cybrary', $headerdata)."\n\n";
                $headerdata->userprefs = '<a target="_blank" href="'.$headerdata->userprefs.'">'.get_string('digestmailprefs', 'cybrary').'</a>';

                $posthtml = "<head>";
/*                foreach ($CFG->stylesheets as $stylesheet) {
                    //TODO: MDL-21120
                    $posthtml .= '<link rel="stylesheet" type="text/css" href="'.$stylesheet.'" />'."\n";
                }*/
                $posthtml .= "</head>\n<body id=\"email\">\n";
                $posthtml .= '<p>'.get_string('digestmailheader', 'cybrary', $headerdata).'</p><br /><hr size="1" noshade="noshade" />';

                foreach ($thesediscussions as $discussionid) {

                    core_php_time_limit::raise(120);   // to be reset for each post

                    $discussion = $discussions[$discussionid];
                    $cybrary      = $cybraries[$discussion->cybrary];
                    $course     = $courses[$cybrary->course];
                    $cm         = $coursemodules[$cybrary->id];

                    //override language
                    cron_setup_user($userto, $course);

                    // Fill caches
                    if (!isset($userto->viewfullnames[$cybrary->id])) {
                        $modcontext = context_module::instance($cm->id);
                        $userto->viewfullnames[$cybrary->id] = has_capability('moodle/site:viewfullnames', $modcontext);
                    }
                    if (!isset($userto->canpost[$discussion->id])) {
                        $modcontext = context_module::instance($cm->id);
                        $userto->canpost[$discussion->id] = cybrary_user_can_post($cybrary, $discussion, $userto, $cm, $course, $modcontext);
                    }

                    $strcybraries      = get_string('cybraries', 'cybrary');
                    $canunsubscribe = ! \mod_cybrary\subscriptions::is_forcesubscribed($cybrary);
                    $canreply       = $userto->canpost[$discussion->id];
                    $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));

                    $posttext .= "\n \n";
                    $posttext .= '=====================================================================';
                    $posttext .= "\n \n";
                    $posttext .= "$shortname -> $strcybraries -> ".format_string($cybrary->name,true);
                    if ($discussion->name != $cybrary->name) {
                        $posttext  .= " -> ".format_string($discussion->name,true);
                    }
                    $posttext .= "\n";
                    $posttext .= $CFG->wwwroot.'/mod/cybrary/discuss.php?d='.$discussion->id;
                    $posttext .= "\n";

                    $posthtml .= "<p><font face=\"sans-serif\">".
                    "<a target=\"_blank\" href=\"$CFG->wwwroot/course/view.php?id=$course->id\">$shortname</a> -> ".
                    "<a target=\"_blank\" href=\"$CFG->wwwroot/mod/cybrary/index.php?id=$course->id\">$strcybraries</a> -> ".
                    "<a target=\"_blank\" href=\"$CFG->wwwroot/mod/cybrary/view.php?f=$cybrary->id\">".format_string($cybrary->name,true)."</a>";
                    if ($discussion->name == $cybrary->name) {
                        $posthtml .= "</font></p>";
                    } else {
                        $posthtml .= " -> <a target=\"_blank\" href=\"$CFG->wwwroot/mod/cybrary/discuss.php?d=$discussion->id\">".format_string($discussion->name,true)."</a></font></p>";
                    }
                    $posthtml .= '<p>';

                    $postsarray = $discussionposts[$discussionid];
                    sort($postsarray);

                    foreach ($postsarray as $postid) {
                        $post = $posts[$postid];

                        if (array_key_exists($post->userid, $users)) { // we might know him/her already
                            $userfrom = $users[$post->userid];
                            if (!isset($userfrom->idnumber)) {
                                $userfrom = $DB->get_record('user', array('id' => $userfrom->id));
                                cybrary_cron_minimise_user_record($userfrom);
                            }

                        } else if ($userfrom = $DB->get_record('user', array('id' => $post->userid))) {
                            cybrary_cron_minimise_user_record($userfrom);
                            if ($userscount <= CYBRARY_CRON_USER_CACHE) {
                                $userscount++;
                                $users[$userfrom->id] = $userfrom;
                            }

                        } else {
                            mtrace('Could not find user '.$post->userid);
                            continue;
                        }

                        if (!isset($userfrom->groups[$cybrary->id])) {
                            if (!isset($userfrom->groups)) {
                                $userfrom->groups = array();
                                if (isset($users[$userfrom->id])) {
                                    $users[$userfrom->id]->groups = array();
                                }
                            }
                            $userfrom->groups[$cybrary->id] = groups_get_all_groups($course->id, $userfrom->id, $cm->groupingid);
                            if (isset($users[$userfrom->id])) {
                                $users[$userfrom->id]->groups[$cybrary->id] = $userfrom->groups[$cybrary->id];
                            }
                        }

                        // Headers to help prevent auto-responders.
                        $userfrom->customheaders = array(
                                "Precedence: Bulk",
                                'X-Auto-Response-Suppress: All',
                                'Auto-Submitted: auto-generated',
                            );

                        $maildigest = cybrary_get_user_maildigest_bulk($digests, $userto, $cybrary->id);
                        if (!isset($userto->canpost[$discussion->id])) {
                            $canreply = cybrary_user_can_post($cybrary, $discussion, $userto, $cm, $course, $modcontext);
                        } else {
                            $canreply = $userto->canpost[$discussion->id];
                        }

                        $data = new \mod_cybrary\output\cybrary_post_email(
                                $course,
                                $cm,
                                $cybrary,
                                $discussion,
                                $post,
                                $userfrom,
                                $userto,
                                $canreply
                            );

                        if (!isset($userto->viewfullnames[$cybrary->id])) {
                            $data->viewfullnames = has_capability('moodle/site:viewfullnames', $modcontext, $userto->id);
                        } else {
                            $data->viewfullnames = $userto->viewfullnames[$cybrary->id];
                        }

                        if ($maildigest == 2) {
                            // Subjects and link only.
                            $posttext .= $textdigestbasicout->render($data);
                            $posthtml .= $htmldigestbasicout->render($data);
                        } else {
                            // The full treatment.
                            $posttext .= $textdigestfullout->render($data);
                            $posthtml .= $htmldigestfullout->render($data);

                            // Create an array of postid's for this user to mark as read.
                            if (!$CFG->cybrary_usermarksread) {
                                $userto->markposts[$post->id] = $post->id;
                            }
                        }
                    }
                    $footerlinks = array();
                    if ($canunsubscribe) {
                        $footerlinks[] = "<a href=\"$CFG->wwwroot/mod/cybrary/subscribe.php?id=$cybrary->id\">" . get_string("unsubscribe", "cybrary") . "</a>";
                    } else {
                        $footerlinks[] = get_string("everyoneissubscribed", "cybrary");
                    }
                    $footerlinks[] = "<a href='{$CFG->wwwroot}/mod/cybrary/index.php?id={$cybrary->course}'>" . get_string("digestmailpost", "cybrary") . '</a>';
                    $posthtml .= "\n<div class='mdl-right'><font size=\"1\">" . implode('&nbsp;', $footerlinks) . '</font></div>';
                    $posthtml .= '<hr size="1" noshade="noshade" /></p>';
                }
                $posthtml .= '</body>';

                if (empty($userto->mailformat) || $userto->mailformat != 1) {
                    // This user DOESN'T want to receive HTML
                    $posthtml = '';
                }

                $attachment = $attachname='';
                // Directly email cybrary digests rather than sending them via messaging, use the
                // site shortname as 'from name', the noreply address will be used by email_to_user.
                $mailresult = email_to_user($userto, $site->shortname, $postsubject, $posttext, $posthtml, $attachment, $attachname);

                if (!$mailresult) {
                    mtrace("ERROR: mod/cybrary/cron.php: Could not send out digest mail to user $userto->id ".
                        "($userto->email)... not trying again.");
                } else {
                    mtrace("success.");
                    $usermailcount++;

                    // Mark post as read if cybrary_usermarksread is set off
                    cybrary_tp_mark_posts_read($userto, $userto->markposts);
                }
            }
        }
    /// We have finishied all digest emails, update $CFG->digestmailtimelast
        set_config('digestmailtimelast', $timenow);
    }

    cron_setup_user();

    if (!empty($usermailcount)) {
        mtrace(get_string('digestsentusers', 'cybrary', $usermailcount));
    }

    if (!empty($CFG->cybrary_lastreadclean)) {
        $timenow = time();
        if ($CFG->cybrary_lastreadclean + (24*3600) < $timenow) {
            set_config('cybrary_lastreadclean', $timenow);
            mtrace('Removing old cybrary read tracking info...');
            cybrary_tp_clean_read_records();
        }
    } else {
        set_config('cybrary_lastreadclean', time());
    }

    return true;
}

/**
 *
 * @param object $course
 * @param object $user
 * @param object $mod TODO this is not used in this function, refactor
 * @param object $cybrary
 * @return object A standard object with 2 variables: info (number of posts for this user) and time (last modified)
 */
function cybrary_user_outline($course, $user, $mod, $cybrary) {
    global $CFG;
    require_once("$CFG->libdir/gradelib.php");
    $grades = grade_get_grades($course->id, 'mod', 'cybrary', $cybrary->id, $user->id);
    if (empty($grades->items[0]->grades)) {
        $grade = false;
    } else {
        $grade = reset($grades->items[0]->grades);
    }

    $count = cybrary_count_user_posts($cybrary->id, $user->id);

    if ($count && $count->postcount > 0) {
        $result = new stdClass();
        $result->info = get_string("numposts", "cybrary", $count->postcount);
        $result->time = $count->lastpost;
        if ($grade) {
            $result->info .= ', ' . get_string('grade') . ': ' . $grade->str_long_grade;
        }
        return $result;
    } else if ($grade) {
        $result = new stdClass();
        $result->info = get_string('grade') . ': ' . $grade->str_long_grade;

        //datesubmitted == time created. dategraded == time modified or time overridden
        //if grade was last modified by the user themselves use date graded. Otherwise use date submitted
        //TODO: move this copied & pasted code somewhere in the grades API. See MDL-26704
        if ($grade->usermodified == $user->id || empty($grade->datesubmitted)) {
            $result->time = $grade->dategraded;
        } else {
            $result->time = $grade->datesubmitted;
        }

        return $result;
    }
    return NULL;
}


/**
 * @global object
 * @global object
 * @param object $coure
 * @param object $user
 * @param object $mod
 * @param object $cybrary
 */
function cybrary_user_complete($course, $user, $mod, $cybrary) {
    global $CFG,$USER, $OUTPUT;
    require_once("$CFG->libdir/gradelib.php");

    $grades = grade_get_grades($course->id, 'mod', 'cybrary', $cybrary->id, $user->id);
    if (!empty($grades->items[0]->grades)) {
        $grade = reset($grades->items[0]->grades);
        echo $OUTPUT->container(get_string('grade').': '.$grade->str_long_grade);
        if ($grade->str_feedback) {
            echo $OUTPUT->container(get_string('feedback').': '.$grade->str_feedback);
        }
    }

    if ($posts = cybrary_get_user_posts($cybrary->id, $user->id)) {

        if (!$cm = get_coursemodule_from_instance('cybrary', $cybrary->id, $course->id)) {
            print_error('invalidcoursemodule');
        }
        $discussions = cybrary_get_user_involved_discussions($cybrary->id, $user->id);

        foreach ($posts as $post) {
            if (!isset($discussions[$post->discussion])) {
                continue;
            }
            $discussion = $discussions[$post->discussion];

            cybrary_print_post($post, $discussion, $cybrary, $cm, $course, false, false, false);
        }
    } else {
        echo "<p>".get_string("noposts", "cybrary")."</p>";
    }
}

/**
 * Filters the cybrary discussions according to groups membership and config.
 *
 * @since  Moodle 2.8, 2.7.1, 2.6.4
 * @param  array $discussions Discussions with new posts array
 * @return array Cybraries with the number of new posts
 */
function cybrary_filter_user_groups_discussions($discussions) {

    // Group the remaining discussions posts by their cybraryid.
    $filteredcybraries = array();

    // Discard not visible groups.
    foreach ($discussions as $discussion) {

        // Course data is already cached.
        $instances = get_fast_modinfo($discussion->course)->get_instances();
        $cybrary = $instances['cybrary'][$discussion->cybrary];

        // Continue if the user should not see this discussion.
        if (!cybrary_is_user_group_discussion($cybrary, $discussion->groupid)) {
            continue;
        }

        // Grouping results by cybrary.
        if (empty($filteredcybraries[$cybrary->instance])) {
            $filteredcybraries[$cybrary->instance] = new stdClass();
            $filteredcybraries[$cybrary->instance]->id = $cybrary->id;
            $filteredcybraries[$cybrary->instance]->count = 0;
        }
        $filteredcybraries[$cybrary->instance]->count += $discussion->count;

    }

    return $filteredcybraries;
}

/**
 * Returns whether the discussion group is visible by the current user or not.
 *
 * @since Moodle 2.8, 2.7.1, 2.6.4
 * @param cm_info $cm The discussion course module
 * @param int $discussiongroupid The discussion groupid
 * @return bool
 */
function cybrary_is_user_group_discussion(cm_info $cm, $discussiongroupid) {

    if ($discussiongroupid == -1 || $cm->effectivegroupmode != SEPARATEGROUPS) {
        return true;
    }

    if (isguestuser()) {
        return false;
    }

    if (has_capability('moodle/site:accessallgroups', context_module::instance($cm->id)) ||
            in_array($discussiongroupid, $cm->get_modinfo()->get_groups($cm->groupingid))) {
        return true;
    }

    return false;
}

/**
 * @global object
 * @global object
 * @global object
 * @param array $courses
 * @param array $htmlarray
 */
function cybrary_print_overview($courses,&$htmlarray) {
    global $USER, $CFG, $DB, $SESSION;

    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }

    if (!$cybraries = get_all_instances_in_courses('cybrary',$courses)) {
        return;
    }

    // Courses to search for new posts
    $coursessqls = array();
    $params = array();
    foreach ($courses as $course) {

        // If the user has never entered into the course all posts are pending
        if ($course->lastaccess == 0) {
            $coursessqls[] = '(d.course = ?)';
            $params[] = $course->id;

        // Only posts created after the course last access
        } else {
            $coursessqls[] = '(d.course = ? AND p.created > ?)';
            $params[] = $course->id;
            $params[] = $course->lastaccess;
        }
    }
    $params[] = $USER->id;
    $coursessql = implode(' OR ', $coursessqls);

    $sql = "SELECT d.id, d.cybrary, d.course, d.groupid, COUNT(*) as count "
                .'FROM {cybrary_discussions} d '
                .'JOIN {cybrary_posts} p ON p.discussion = d.id '
                ."WHERE ($coursessql) "
                .'AND p.userid != ? '
                .'AND (d.timestart <= ? AND (d.timeend = 0 OR d.timeend > ?)) '
                .'GROUP BY d.id, d.cybrary, d.course, d.groupid '
                .'ORDER BY d.course, d.cybrary';
    $params[] = time();
    $params[] = time();

    // Avoid warnings.
    if (!$discussions = $DB->get_records_sql($sql, $params)) {
        $discussions = array();
    }

    $cybrariesnewposts = cybrary_filter_user_groups_discussions($discussions);

    // also get all cybrary tracking stuff ONCE.
    $trackingcybraries = array();
    foreach ($cybraries as $cybrary) {
        if (cybrary_tp_can_track_cybraries($cybrary)) {
            $trackingcybraries[$cybrary->id] = $cybrary;
        }
    }

    if (count($trackingcybraries) > 0) {
        $cutoffdate = isset($CFG->cybrary_oldpostdays) ? (time() - ($CFG->cybrary_oldpostdays*24*60*60)) : 0;
        $sql = 'SELECT d.cybrary,d.course,COUNT(p.id) AS count '.
            ' FROM {cybrary_posts} p '.
            ' JOIN {cybrary_discussions} d ON p.discussion = d.id '.
            ' LEFT JOIN {cybrary_read} r ON r.postid = p.id AND r.userid = ? WHERE (';
        $params = array($USER->id);

        foreach ($trackingcybraries as $track) {
            $sql .= '(d.cybrary = ? AND (d.groupid = -1 OR d.groupid = 0 OR d.groupid = ?)) OR ';
            $params[] = $track->id;
            if (isset($SESSION->currentgroup[$track->course])) {
                $groupid =  $SESSION->currentgroup[$track->course];
            } else {
                // get first groupid
                $groupids = groups_get_all_groups($track->course, $USER->id);
                if ($groupids) {
                    reset($groupids);
                    $groupid = key($groupids);
                    $SESSION->currentgroup[$track->course] = $groupid;
                } else {
                    $groupid = 0;
                }
                unset($groupids);
            }
            $params[] = $groupid;
        }
        $sql = substr($sql,0,-3); // take off the last OR
        $sql .= ') AND p.modified >= ? AND r.id is NULL ';
        $sql .= 'AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)) ';
        $sql .= 'GROUP BY d.cybrary,d.course';
        $params[] = $cutoffdate;
        $params[] = time();
        $params[] = time();

        if (!$unread = $DB->get_records_sql($sql, $params)) {
            $unread = array();
        }
    } else {
        $unread = array();
    }

    if (empty($unread) and empty($cybrariesnewposts)) {
        return;
    }

    $strcybrary = get_string('modulename','cybrary');

    foreach ($cybraries as $cybrary) {
        $str = '';
        $count = 0;
        $thisunread = 0;
        $showunread = false;
        // either we have something from logs, or trackposts, or nothing.
        if (array_key_exists($cybrary->id, $cybrariesnewposts) && !empty($cybrariesnewposts[$cybrary->id])) {
            $count = $cybrariesnewposts[$cybrary->id]->count;
        }
        if (array_key_exists($cybrary->id,$unread)) {
            $thisunread = $unread[$cybrary->id]->count;
            $showunread = true;
        }
        if ($count > 0 || $thisunread > 0) {
            $str .= '<div class="overview cybrary"><div class="name">'.$strcybrary.': <a title="'.$strcybrary.'" href="'.$CFG->wwwroot.'/mod/cybrary/view.php?f='.$cybrary->id.'">'.
                $cybrary->name.'</a></div>';
            $str .= '<div class="info"><span class="postsincelogin">';
            $str .= get_string('overviewnumpostssince', 'cybrary', $count)."</span>";
            if (!empty($showunread)) {
                $str .= '<div class="unreadposts">'.get_string('overviewnumunread', 'cybrary', $thisunread).'</div>';
            }
            $str .= '</div></div>';
        }
        if (!empty($str)) {
            if (!array_key_exists($cybrary->course,$htmlarray)) {
                $htmlarray[$cybrary->course] = array();
            }
            if (!array_key_exists('cybrary',$htmlarray[$cybrary->course])) {
                $htmlarray[$cybrary->course]['cybrary'] = ''; // initialize, avoid warnings
            }
            $htmlarray[$cybrary->course]['cybrary'] .= $str;
        }
    }
}

/**
 * Given a course and a date, prints a summary of all the new
 * messages posted in the course since that date
 *
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $course
 * @param bool $viewfullnames capability
 * @param int $timestart
 * @return bool success
 */
function cybrary_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $USER, $DB, $OUTPUT;

    // do not use log table if possible, it may be huge and is expensive to join with other tables

    $allnamefields = user_picture::fields('u', null, 'duserid');
    if (!$posts = $DB->get_records_sql("SELECT p.*, f.type AS cybrarytype, d.cybrary, d.groupid,
                                              d.timestart, d.timeend, $allnamefields
                                         FROM {cybrary_posts} p
                                              JOIN {cybrary_discussions} d ON d.id = p.discussion
                                              JOIN {cybrary} f             ON f.id = d.cybrary
                                              JOIN {user} u              ON u.id = p.userid
                                        WHERE p.created > ? AND f.course = ?
                                     ORDER BY p.id ASC", array($timestart, $course->id))) { // order by initial posting date
         return false;
    }

    $modinfo = get_fast_modinfo($course);

    $groupmodes = array();
    $cms    = array();

    $strftimerecent = get_string('strftimerecent');

    $printposts = array();
    foreach ($posts as $post) {
        if (!isset($modinfo->instances['cybrary'][$post->cybrary])) {
            // not visible
            continue;
        }
        $cm = $modinfo->instances['cybrary'][$post->cybrary];
        if (!$cm->uservisible) {
            continue;
        }
        $context = context_module::instance($cm->id);

        if (!has_capability('mod/cybrary:viewdiscussion', $context)) {
            continue;
        }

        if (!empty($CFG->cybrary_enabletimedposts) and $USER->id != $post->duserid
          and (($post->timestart > 0 and $post->timestart > time()) or ($post->timeend > 0 and $post->timeend < time()))) {
            if (!has_capability('mod/cybrary:viewhiddentimedposts', $context)) {
                continue;
            }
        }

        // Check that the user can see the discussion.
        if (cybrary_is_user_group_discussion($cm, $post->groupid)) {
            $printposts[] = $post;
        }

    }
    unset($posts);

    if (!$printposts) {
        return false;
    }

    echo $OUTPUT->heading(get_string('newcybraryposts', 'cybrary').':', 3);
    echo "\n<ul class='unlist'>\n";

    foreach ($printposts as $post) {
        $subjectclass = empty($post->parent) ? ' bold' : '';

        echo '<li><div class="head">'.
               '<div class="date">'.userdate($post->modified, $strftimerecent).'</div>'.
               '<div class="name">'.fullname($post, $viewfullnames).'</div>'.
             '</div>';
        echo '<div class="info'.$subjectclass.'">';
        if (empty($post->parent)) {
            echo '"<a href="'.$CFG->wwwroot.'/mod/cybrary/discuss.php?d='.$post->discussion.'">';
        } else {
            echo '"<a href="'.$CFG->wwwroot.'/mod/cybrary/discuss.php?d='.$post->discussion.'&amp;parent='.$post->parent.'#p'.$post->id.'">';
        }
        $post->subject = break_up_long_words(format_string($post->subject, true));
        echo $post->subject;
        echo "</a>\"</div></li>\n";
    }

    echo "</ul>\n";

    return true;
}

/**
 * Return grade for given user or all users.
 *
 * @global object
 * @global object
 * @param object $cybrary
 * @param int $userid optional user id, 0 means all users
 * @return array array of grades, false if none
 */
function cybrary_get_user_grades($cybrary, $userid = 0) {
    global $CFG;

    require_once($CFG->dirroot.'/rating/lib.php');

    $ratingoptions = new stdClass;
    $ratingoptions->component = 'mod_cybrary';
    $ratingoptions->ratingarea = 'post';

    //need these to work backwards to get a context id. Is there a better way to get contextid from a module instance?
    $ratingoptions->modulename = 'cybrary';
    $ratingoptions->moduleid   = $cybrary->id;
    $ratingoptions->userid = $userid;
    $ratingoptions->aggregationmethod = $cybrary->assessed;
    $ratingoptions->scaleid = $cybrary->scale;
    $ratingoptions->itemtable = 'cybrary_posts';
    $ratingoptions->itemtableusercolumn = 'userid';

    $rm = new rating_manager();
    return $rm->get_user_grades($ratingoptions);
}

/**
 * Update activity grades
 *
 * @category grade
 * @param object $cybrary
 * @param int $userid specific user only, 0 means all
 * @param boolean $nullifnone return null if grade does not exist
 * @return void
 */
function cybrary_update_grades($cybrary, $userid=0, $nullifnone=true) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    if (!$cybrary->assessed) {
        cybrary_grade_item_update($cybrary);

    } else if ($grades = cybrary_get_user_grades($cybrary, $userid)) {
        cybrary_grade_item_update($cybrary, $grades);

    } else if ($userid and $nullifnone) {
        $grade = new stdClass();
        $grade->userid   = $userid;
        $grade->rawgrade = NULL;
        cybrary_grade_item_update($cybrary, $grade);

    } else {
        cybrary_grade_item_update($cybrary);
    }
}

/**
 * Create/update grade item for given cybrary
 *
 * @category grade
 * @uses GRADE_TYPE_NONE
 * @uses GRADE_TYPE_VALUE
 * @uses GRADE_TYPE_SCALE
 * @param stdClass $cybrary Cybrary object with extra cmidnumber
 * @param mixed $grades Optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok
 */
function cybrary_grade_item_update($cybrary, $grades=NULL) {
    global $CFG;
    if (!function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->libdir.'/gradelib.php');
    }

    $params = array('itemname'=>$cybrary->name, 'idnumber'=>$cybrary->cmidnumber);

    if (!$cybrary->assessed or $cybrary->scale == 0) {
        $params['gradetype'] = GRADE_TYPE_NONE;

    } else if ($cybrary->scale > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $cybrary->scale;
        $params['grademin']  = 0;

    } else if ($cybrary->scale < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$cybrary->scale;
    }

    if ($grades  === 'reset') {
        $params['reset'] = true;
        $grades = NULL;
    }

    return grade_update('mod/cybrary', $cybrary->course, 'mod', 'cybrary', $cybrary->id, 0, $grades, $params);
}

/**
 * Delete grade item for given cybrary
 *
 * @category grade
 * @param stdClass $cybrary Cybrary object
 * @return grade_item
 */
function cybrary_grade_item_delete($cybrary) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/cybrary', $cybrary->course, 'mod', 'cybrary', $cybrary->id, 0, NULL, array('deleted'=>1));
}


/**
 * This function returns if a scale is being used by one cybrary
 *
 * @global object
 * @param int $cybraryid
 * @param int $scaleid negative number
 * @return bool
 */
function cybrary_scale_used ($cybraryid,$scaleid) {
    global $DB;
    $return = false;

    $rec = $DB->get_record("cybrary",array("id" => "$cybraryid","scale" => "-$scaleid"));

    if (!empty($rec) && !empty($scaleid)) {
        $return = true;
    }

    return $return;
}

/**
 * Checks if scale is being used by any instance of cybrary
 *
 * This is used to find out if scale used anywhere
 *
 * @global object
 * @param $scaleid int
 * @return boolean True if the scale is used by any cybrary
 */
function cybrary_scale_used_anywhere($scaleid) {
    global $DB;
    if ($scaleid and $DB->record_exists('cybrary', array('scale' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

// SQL FUNCTIONS ///////////////////////////////////////////////////////////

/**
 * Gets a post with all info ready for cybrary_print_post
 * Most of these joins are just to get the cybrary id
 *
 * @global object
 * @global object
 * @param int $postid
 * @return mixed array of posts or false
 */
function cybrary_get_post_full($postid) {
    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_record_sql("SELECT p.*, d.cybrary, $allnames, u.email, u.picture, u.imagealt
                             FROM {cybrary_posts} p
                                  JOIN {cybrary_discussions} d ON p.discussion = d.id
                                  LEFT JOIN {user} u ON p.userid = u.id
                            WHERE p.id = ?", array($postid));
}

/**
 * Gets all posts in discussion including top parent.
 *
 * @global object
 * @global object
 * @global object
 * @param int $discussionid
 * @param string $sort
 * @param bool $tracking does user track the cybrary?
 * @return array of posts
 */
function cybrary_get_all_discussion_posts($discussionid, $sort, $tracking=false) {
    global $CFG, $DB, $USER;

    $tr_sel  = "";
    $tr_join = "";
    $params = array();

    if ($tracking) {
        $now = time();
        $cutoffdate = $now - ($CFG->cybrary_oldpostdays * 24 * 3600);
        $tr_sel  = ", fr.id AS postread";
        $tr_join = "LEFT JOIN {cybrary_read} fr ON (fr.postid = p.id AND fr.userid = ?)";
        $params[] = $USER->id;
    }

    $allnames = get_all_user_name_fields(true, 'u');
    $params[] = $discussionid;
    if (!$posts = $DB->get_records_sql("SELECT p.*, $allnames, u.email, u.picture, u.imagealt $tr_sel
                                     FROM {cybrary_posts} p
                                          LEFT JOIN {user} u ON p.userid = u.id
                                          $tr_join
                                    WHERE p.discussion = ?
                                 ORDER BY $sort", $params)) {
        return array();
    }

    foreach ($posts as $pid=>$p) {
        if ($tracking) {
            if (cybrary_tp_is_post_old($p)) {
                 $posts[$pid]->postread = true;
            }
        }
        if (!$p->parent) {
            continue;
        }
        if (!isset($posts[$p->parent])) {
            continue; // parent does not exist??
        }
        if (!isset($posts[$p->parent]->children)) {
            $posts[$p->parent]->children = array();
        }
        $posts[$p->parent]->children[$pid] =& $posts[$pid];
    }

    // Start with the last child of the first post.
    $post = &$posts[reset($posts)->id];

    $lastpost = false;
    while (!$lastpost) {
        if (!isset($post->children)) {
            $post->lastpost = true;
            $lastpost = true;
        } else {
             // Go to the last child of this post.
            $post = &$posts[end($post->children)->id];
        }
    }

    return $posts;
}

/**
 * An array of cybrary objects that the user is allowed to read/search through.
 *
 * @global object
 * @global object
 * @global object
 * @param int $userid
 * @param int $courseid if 0, we look for cybraries throughout the whole site.
 * @return array of cybrary objects, or false if no matches
 *         Cybrary objects have the following attributes:
 *         id, type, course, cmid, cmvisible, cmgroupmode, accessallgroups,
 *         viewhiddentimedposts
 */
function cybrary_get_readable_cybraries($userid, $courseid=0) {

    global $CFG, $DB, $USER;
    require_once($CFG->dirroot.'/course/lib.php');

    if (!$cybrarymod = $DB->get_record('modules', array('name' => 'cybrary'))) {
        print_error('notinstalled', 'cybrary');
    }

    if ($courseid) {
        $courses = $DB->get_records('course', array('id' => $courseid));
    } else {
        // If no course is specified, then the user can see SITE + his courses.
        $courses1 = $DB->get_records('course', array('id' => SITEID));
        $courses2 = enrol_get_users_courses($userid, true, array('modinfo'));
        $courses = array_merge($courses1, $courses2);
    }
    if (!$courses) {
        return array();
    }

    $readablecybraries = array();

    foreach ($courses as $course) {

        $modinfo = get_fast_modinfo($course);

        if (empty($modinfo->instances['cybrary'])) {
            // hmm, no cybraries?
            continue;
        }

        $coursecybraries = $DB->get_records('cybrary', array('course' => $course->id));

        foreach ($modinfo->instances['cybrary'] as $cybraryid => $cm) {
            if (!$cm->uservisible or !isset($coursecybraries[$cybraryid])) {
                continue;
            }
            $context = context_module::instance($cm->id);
            $cybrary = $coursecybraries[$cybraryid];
            $cybrary->context = $context;
            $cybrary->cm = $cm;

            if (!has_capability('mod/cybrary:viewdiscussion', $context)) {
                continue;
            }

         /// group access
            if (groups_get_activity_groupmode($cm, $course) == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $context)) {

                $cybrary->onlygroups = $modinfo->get_groups($cm->groupingid);
                $cybrary->onlygroups[] = -1;
            }

        /// hidden timed discussions
            $cybrary->viewhiddentimedposts = true;
            if (!empty($CFG->cybrary_enabletimedposts)) {
                if (!has_capability('mod/cybrary:viewhiddentimedposts', $context)) {
                    $cybrary->viewhiddentimedposts = false;
                }
            }

        /// qanda access
            if ($cybrary->type == 'qanda'
                    && !has_capability('mod/cybrary:viewqandawithoutposting', $context)) {

                // We need to check whether the user has posted in the qanda cybrary.
                $cybrary->onlydiscussions = array();  // Holds discussion ids for the discussions
                                                    // the user is allowed to see in this cybrary.
                if ($discussionspostedin = cybrary_discussions_user_has_posted_in($cybrary->id, $USER->id)) {
                    foreach ($discussionspostedin as $d) {
                        $cybrary->onlydiscussions[] = $d->id;
                    }
                }
            }

            $readablecybraries[$cybrary->id] = $cybrary;
        }

        unset($modinfo);

    } // End foreach $courses

    return $readablecybraries;
}

/**
 * Returns a list of posts found using an array of search terms.
 *
 * @global object
 * @global object
 * @global object
 * @param array $searchterms array of search terms, e.g. word +word -word
 * @param int $courseid if 0, we search through the whole site
 * @param int $limitfrom
 * @param int $limitnum
 * @param int &$totalcount
 * @param string $extrasql
 * @return array|bool Array of posts found or false
 */
function cybrary_search_posts($searchterms, $courseid=0, $limitfrom=0, $limitnum=50,
                            &$totalcount, $extrasql='') {
    global $CFG, $DB, $USER;
    require_once($CFG->libdir.'/searchlib.php');

    $cybraries = cybrary_get_readable_cybraries($USER->id, $courseid);

    if (count($cybraries) == 0) {
        $totalcount = 0;
        return false;
    }

    $now = round(time(), -2); // db friendly

    $fullaccess = array();
    $where = array();
    $params = array();

    foreach ($cybraries as $cybraryid => $cybrary) {
        $select = array();

        if (!$cybrary->viewhiddentimedposts) {
            $select[] = "(d.userid = :userid{$cybraryid} OR (d.timestart < :timestart{$cybraryid} AND (d.timeend = 0 OR d.timeend > :timeend{$cybraryid})))";
            $params = array_merge($params, array('userid'.$cybraryid=>$USER->id, 'timestart'.$cybraryid=>$now, 'timeend'.$cybraryid=>$now));
        }

        $cm = $cybrary->cm;
        $context = $cybrary->context;

        if ($cybrary->type == 'qanda'
            && !has_capability('mod/cybrary:viewqandawithoutposting', $context)) {
            if (!empty($cybrary->onlydiscussions)) {
                list($discussionid_sql, $discussionid_params) = $DB->get_in_or_equal($cybrary->onlydiscussions, SQL_PARAMS_NAMED, 'qanda'.$cybraryid.'_');
                $params = array_merge($params, $discussionid_params);
                $select[] = "(d.id $discussionid_sql OR p.parent = 0)";
            } else {
                $select[] = "p.parent = 0";
            }
        }

        if (!empty($cybrary->onlygroups)) {
            list($groupid_sql, $groupid_params) = $DB->get_in_or_equal($cybrary->onlygroups, SQL_PARAMS_NAMED, 'grps'.$cybraryid.'_');
            $params = array_merge($params, $groupid_params);
            $select[] = "d.groupid $groupid_sql";
        }

        if ($select) {
            $selects = implode(" AND ", $select);
            $where[] = "(d.cybrary = :cybrary{$cybraryid} AND $selects)";
            $params['cybrary'.$cybraryid] = $cybraryid;
        } else {
            $fullaccess[] = $cybraryid;
        }
    }

    if ($fullaccess) {
        list($fullid_sql, $fullid_params) = $DB->get_in_or_equal($fullaccess, SQL_PARAMS_NAMED, 'fula');
        $params = array_merge($params, $fullid_params);
        $where[] = "(d.cybrary $fullid_sql)";
    }

    $selectdiscussion = "(".implode(" OR ", $where).")";

    $messagesearch = '';
    $searchstring = '';

    // Need to concat these back together for parser to work.
    foreach($searchterms as $searchterm){
        if ($searchstring != '') {
            $searchstring .= ' ';
        }
        $searchstring .= $searchterm;
    }

    // We need to allow quoted strings for the search. The quotes *should* be stripped
    // by the parser, but this should be examined carefully for security implications.
    $searchstring = str_replace("\\\"","\"",$searchstring);
    $parser = new search_parser();
    $lexer = new search_lexer($parser);

    if ($lexer->parse($searchstring)) {
        $parsearray = $parser->get_parsed_array();
        list($messagesearch, $msparams) = search_generate_SQL($parsearray, 'p.message', 'p.subject',
                                                              'p.userid', 'u.id', 'u.firstname',
                                                              'u.lastname', 'p.modified', 'd.cybrary');
        $params = array_merge($params, $msparams);
    }

    $fromsql = "{cybrary_posts} p,
                  {cybrary_discussions} d,
                  {user} u";

    $selectsql = " $messagesearch
               AND p.discussion = d.id
               AND p.userid = u.id
               AND $selectdiscussion
                   $extrasql";

    $countsql = "SELECT COUNT(*)
                   FROM $fromsql
                  WHERE $selectsql";

    $allnames = get_all_user_name_fields(true, 'u');
    $searchsql = "SELECT p.*,
                         d.cybrary,
                         $allnames,
                         u.email,
                         u.picture,
                         u.imagealt
                    FROM $fromsql
                   WHERE $selectsql
                ORDER BY p.modified DESC";

    $totalcount = $DB->count_records_sql($countsql, $params);

    return $DB->get_records_sql($searchsql, $params, $limitfrom, $limitnum);
}

/**
 * Returns a list of all new posts that have not been mailed yet
 *
 * @param int $starttime posts created after this time
 * @param int $endtime posts created before this
 * @param int $now used for timed discussions only
 * @return array
 */
function cybrary_get_unmailed_posts($starttime, $endtime, $now=null) {
    global $CFG, $DB;

    $params = array();
    $params['mailed'] = CYBRARY_MAILED_PENDING;
    $params['ptimestart'] = $starttime;
    $params['ptimeend'] = $endtime;
    $params['mailnow'] = 1;

    if (!empty($CFG->cybrary_enabletimedposts)) {
        if (empty($now)) {
            $now = time();
        }
        $selectsql = "AND (p.created >= :ptimestart OR d.timestart >= :pptimestart)";
        $params['pptimestart'] = $starttime;
        $timedsql = "AND (d.timestart < :dtimestart AND (d.timeend = 0 OR d.timeend > :dtimeend))";
        $params['dtimestart'] = $now;
        $params['dtimeend'] = $now;
    } else {
        $timedsql = "";
        $selectsql = "AND p.created >= :ptimestart";
    }

    return $DB->get_records_sql("SELECT p.*, d.course, d.cybrary
                                 FROM {cybrary_posts} p
                                 JOIN {cybrary_discussions} d ON d.id = p.discussion
                                 WHERE p.mailed = :mailed
                                 $selectsql
                                 AND (p.created < :ptimeend OR p.mailnow = :mailnow)
                                 $timedsql
                                 ORDER BY p.modified ASC", $params);
}

/**
 * Marks posts before a certain time as being mailed already
 *
 * @global object
 * @global object
 * @param int $endtime
 * @param int $now Defaults to time()
 * @return bool
 */
function cybrary_mark_old_posts_as_mailed($endtime, $now=null) {
    global $CFG, $DB;

    if (empty($now)) {
        $now = time();
    }

    $params = array();
    $params['mailedsuccess'] = CYBRARY_MAILED_SUCCESS;
    $params['now'] = $now;
    $params['endtime'] = $endtime;
    $params['mailnow'] = 1;
    $params['mailedpending'] = CYBRARY_MAILED_PENDING;

    if (empty($CFG->cybrary_enabletimedposts)) {
        return $DB->execute("UPDATE {cybrary_posts}
                             SET mailed = :mailedsuccess
                             WHERE (created < :endtime OR mailnow = :mailnow)
                             AND mailed = :mailedpending", $params);
    } else {
        return $DB->execute("UPDATE {cybrary_posts}
                             SET mailed = :mailedsuccess
                             WHERE discussion NOT IN (SELECT d.id
                                                      FROM {cybrary_discussions} d
                                                      WHERE d.timestart > :now)
                             AND (created < :endtime OR mailnow = :mailnow)
                             AND mailed = :mailedpending", $params);
    }
}

/**
 * Get all the posts for a user in a cybrary suitable for cybrary_print_post
 *
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @return array
 */
function cybrary_get_user_posts($cybraryid, $userid) {
    global $CFG, $DB;

    $timedsql = "";
    $params = array($cybraryid, $userid);

    if (!empty($CFG->cybrary_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('cybrary', $cybraryid);
        if (!has_capability('mod/cybrary:viewhiddentimedposts' , context_module::instance($cm->id))) {
            $now = time();
            $timedsql = "AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
        }
    }

    $allnames = get_all_user_name_fields(true, 'u');
    return $DB->get_records_sql("SELECT p.*, d.cybrary, $allnames, u.email, u.picture, u.imagealt
                              FROM {cybrary} f
                                   JOIN {cybrary_discussions} d ON d.cybrary = f.id
                                   JOIN {cybrary_posts} p       ON p.discussion = d.id
                                   JOIN {user} u              ON u.id = p.userid
                             WHERE f.id = ?
                                   AND p.userid = ?
                                   $timedsql
                          ORDER BY p.modified ASC", $params);
}

/**
 * Get all the discussions user participated in
 *
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @param int $cybraryid
 * @param int $userid
 * @return array Array or false
 */
function cybrary_get_user_involved_discussions($cybraryid, $userid) {
    global $CFG, $DB;

    $timedsql = "";
    $params = array($cybraryid, $userid);
    if (!empty($CFG->cybrary_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('cybrary', $cybraryid);
        if (!has_capability('mod/cybrary:viewhiddentimedposts' , context_module::instance($cm->id))) {
            $now = time();
            $timedsql = "AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
        }
    }

    return $DB->get_records_sql("SELECT DISTINCT d.*
                              FROM {cybrary} f
                                   JOIN {cybrary_discussions} d ON d.cybrary = f.id
                                   JOIN {cybrary_posts} p       ON p.discussion = d.id
                             WHERE f.id = ?
                                   AND p.userid = ?
                                   $timedsql", $params);
}

/**
 * Get all the posts for a user in a cybrary suitable for cybrary_print_post
 *
 * @global object
 * @global object
 * @param int $cybraryid
 * @param int $userid
 * @return array of counts or false
 */
function cybrary_count_user_posts($cybraryid, $userid) {
    global $CFG, $DB;

    $timedsql = "";
    $params = array($cybraryid, $userid);
    if (!empty($CFG->cybrary_enabletimedposts)) {
        $cm = get_coursemodule_from_instance('cybrary', $cybraryid);
        if (!has_capability('mod/cybrary:viewhiddentimedposts' , context_module::instance($cm->id))) {
            $now = time();
            $timedsql = "AND (d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
        }
    }

    return $DB->get_record_sql("SELECT COUNT(p.id) AS postcount, MAX(p.modified) AS lastpost
                             FROM {cybrary} f
                                  JOIN {cybrary_discussions} d ON d.cybrary = f.id
                                  JOIN {cybrary_posts} p       ON p.discussion = d.id
                                  JOIN {user} u              ON u.id = p.userid
                            WHERE f.id = ?
                                  AND p.userid = ?
                                  $timedsql", $params);
}

/**
 * Given a log entry, return the cybrary post details for it.
 *
 * @global object
 * @global object
 * @param object $log
 * @return array|null
 */
function cybrary_get_post_from_log($log) {
    global $CFG, $DB;

    $allnames = get_all_user_name_fields(true, 'u');
    if ($log->action == "add post") {

        return $DB->get_record_sql("SELECT p.*, f.type AS cybrarytype, d.cybrary, d.groupid, $allnames, u.email, u.picture
                                 FROM {cybrary_discussions} d,
                                      {cybrary_posts} p,
                                      {cybrary} f,
                                      {user} u
                                WHERE p.id = ?
                                  AND d.id = p.discussion
                                  AND p.userid = u.id
                                  AND u.deleted <> '1'
                                  AND f.id = d.cybrary", array($log->info));


    } else if ($log->action == "add discussion") {

        return $DB->get_record_sql("SELECT p.*, f.type AS cybrarytype, d.cybrary, d.groupid, $allnames, u.email, u.picture
                                 FROM {cybrary_discussions} d,
                                      {cybrary_posts} p,
                                      {cybrary} f,
                                      {user} u
                                WHERE d.id = ?
                                  AND d.firstpost = p.id
                                  AND p.userid = u.id
                                  AND u.deleted <> '1'
                                  AND f.id = d.cybrary", array($log->info));
    }
    return NULL;
}

/**
 * Given a discussion id, return the first post from the discussion
 *
 * @global object
 * @global object
 * @param int $dicsussionid
 * @return array
 */
function cybrary_get_firstpost_from_discussion($discussionid) {
    global $CFG, $DB;

    return $DB->get_record_sql("SELECT p.*
                             FROM {cybrary_discussions} d,
                                  {cybrary_posts} p
                            WHERE d.id = ?
                              AND d.firstpost = p.id ", array($discussionid));
}

/**
 * Returns an array of counts of replies to each discussion
 *
 * @global object
 * @global object
 * @param int $cybraryid
 * @param string $cybrariesort
 * @param int $limit
 * @param int $page
 * @param int $perpage
 * @return array
 */
function cybrary_count_discussion_replies($cybraryid, $cybrariesort="", $limit=-1, $page=-1, $perpage=0) {
    global $CFG, $DB;

    if ($limit > 0) {
        $limitfrom = 0;
        $limitnum  = $limit;
    } else if ($page != -1) {
        $limitfrom = $page*$perpage;
        $limitnum  = $perpage;
    } else {
        $limitfrom = 0;
        $limitnum  = 0;
    }

    if ($cybrariesort == "") {
        $orderby = "";
        $groupby = "";

    } else {
        $orderby = "ORDER BY $cybrariesort";
        $groupby = ", ".strtolower($cybrariesort);
        $groupby = str_replace('desc', '', $groupby);
        $groupby = str_replace('asc', '', $groupby);
    }

    if (($limitfrom == 0 and $limitnum == 0) or $cybrariesort == "") {
        $sql = "SELECT p.discussion, COUNT(p.id) AS replies, MAX(p.id) AS lastpostid
                  FROM {cybrary_posts} p
                       JOIN {cybrary_discussions} d ON p.discussion = d.id
                 WHERE p.parent > 0 AND d.cybrary = ?
              GROUP BY p.discussion";
        return $DB->get_records_sql($sql, array($cybraryid));

    } else {
        $sql = "SELECT p.discussion, (COUNT(p.id) - 1) AS replies, MAX(p.id) AS lastpostid
                  FROM {cybrary_posts} p
                       JOIN {cybrary_discussions} d ON p.discussion = d.id
                 WHERE d.cybrary = ?
              GROUP BY p.discussion $groupby $orderby";
        return $DB->get_records_sql($sql, array($cybraryid), $limitfrom, $limitnum);
    }
}

/**
 * @global object
 * @global object
 * @global object
 * @staticvar array $cache
 * @param object $cybrary
 * @param object $cm
 * @param object $course
 * @return mixed
 */
function cybrary_count_discussions($cybrary, $cm, $course) {
    global $CFG, $DB, $USER;

    static $cache = array();

    $now = round(time(), -2); // db cache friendliness

    $params = array($course->id);

    if (!isset($cache[$course->id])) {
        if (!empty($CFG->cybrary_enabletimedposts)) {
            $timedsql = "AND d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)";
            $params[] = $now;
            $params[] = $now;
        } else {
            $timedsql = "";
        }

        $sql = "SELECT f.id, COUNT(d.id) as dcount
                  FROM {cybrary} f
                       JOIN {cybrary_discussions} d ON d.cybrary = f.id
                 WHERE f.course = ?
                       $timedsql
              GROUP BY f.id";

        if ($counts = $DB->get_records_sql($sql, $params)) {
            foreach ($counts as $count) {
                $counts[$count->id] = $count->dcount;
            }
            $cache[$course->id] = $counts;
        } else {
            $cache[$course->id] = array();
        }
    }

    if (empty($cache[$course->id][$cybrary->id])) {
        return 0;
    }

    $groupmode = groups_get_activity_groupmode($cm, $course);

    if ($groupmode != SEPARATEGROUPS) {
        return $cache[$course->id][$cybrary->id];
    }

    if (has_capability('moodle/site:accessallgroups', context_module::instance($cm->id))) {
        return $cache[$course->id][$cybrary->id];
    }

    require_once($CFG->dirroot.'/course/lib.php');

    $modinfo = get_fast_modinfo($course);

    $mygroups = $modinfo->get_groups($cm->groupingid);

    // add all groups posts
    $mygroups[-1] = -1;

    list($mygroups_sql, $params) = $DB->get_in_or_equal($mygroups);
    $params[] = $cybrary->id;

    if (!empty($CFG->cybrary_enabletimedposts)) {
        $timedsql = "AND d.timestart < $now AND (d.timeend = 0 OR d.timeend > $now)";
        $params[] = $now;
        $params[] = $now;
    } else {
        $timedsql = "";
    }

    $sql = "SELECT COUNT(d.id)
              FROM {cybrary_discussions} d
             WHERE d.groupid $mygroups_sql AND d.cybrary = ?
                   $timedsql";

    return $DB->get_field_sql($sql, $params);
}

/**
 * Get all discussions in a cybrary
 *
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $cm
 * @param string $cybrariesort
 * @param bool $fullpost
 * @param int $unused
 * @param int $limit
 * @param bool $userlastmodified
 * @param int $page
 * @param int $perpage
 * @param int $groupid if groups enabled, get discussions for this group overriding the current group.
 *                     Use CYBRARY_POSTS_ALL_USER_GROUPS for all the user groups
 * @return array
 */
function cybrary_get_discussions($cm, $cybrariesort="", $fullpost=true, $unused=-1, $limit=-1,
                                $userlastmodified=false, $page=-1, $perpage=0, $groupid = -1) {
    global $CFG, $DB, $USER;

    $timelimit = '';

    $now = round(time(), -2);
    $params = array($cm->instance);

    $modcontext = context_module::instance($cm->id);

    if (!has_capability('mod/cybrary:viewdiscussion', $modcontext)) { /// User must have perms to view discussions
        return array();
    }

    if (!empty($CFG->cybrary_enabletimedposts)) { /// Users must fulfill timed posts

        if (!has_capability('mod/cybrary:viewhiddentimedposts', $modcontext)) {
            $timelimit = " AND ((d.timestart <= ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
            if (isloggedin()) {
                $timelimit .= " OR d.userid = ?";
                $params[] = $USER->id;
            }
            $timelimit .= ")";
        }
    }

    if ($limit > 0) {
        $limitfrom = 0;
        $limitnum  = $limit;
    } else if ($page != -1) {
        $limitfrom = $page*$perpage;
        $limitnum  = $perpage;
    } else {
        $limitfrom = 0;
        $limitnum  = 0;
    }

    $groupmode    = groups_get_activity_groupmode($cm);

    if ($groupmode) {

        if (empty($modcontext)) {
            $modcontext = context_module::instance($cm->id);
        }

        // Special case, we received a groupid to override currentgroup.
        if ($groupid > 0) {
            $course = get_course($cm->course);
            if (!groups_group_visible($groupid, $course, $cm)) {
                // User doesn't belong to this group, return nothing.
                return array();
            }
            $currentgroup = $groupid;
        } else if ($groupid === -1) {
            $currentgroup = groups_get_activity_group($cm);
        } else {
            // Get discussions for all groups current user can see.
            $currentgroup = null;
        }

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "";
            }

        } else {
            // Separate groups.

            // Get discussions for all groups current user can see.
            if ($currentgroup === null) {
                $mygroups = array_keys(groups_get_all_groups($cm->course, $USER->id, $cm->groupingid, 'g.id'));
                if (empty($mygroups)) {
                     $groupselect = "AND d.groupid = -1";
                } else {
                    list($insqlgroups, $inparamsgroups) = $DB->get_in_or_equal($mygroups);
                    $groupselect = "AND (d.groupid = -1 OR d.groupid $insqlgroups)";
                    $params = array_merge($params, $inparamsgroups);
                }
            } else if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "AND d.groupid = -1";
            }
        }
    } else {
        $groupselect = "";
    }
    if (empty($cybrariesort)) {
        $cybrariesort = cybrary_get_default_sort_order();
    }
    if (empty($fullpost)) {
        $postdata = "p.id,p.subject,p.modified,p.discussion,p.userid";
    } else {
        $postdata = "p.*";
    }

    if (empty($userlastmodified)) {  // We don't need to know this
        $umfields = "";
        $umtable  = "";
    } else {
        $umfields = ', ' . get_all_user_name_fields(true, 'um', null, 'um') . ', um.email AS umemail, um.picture AS umpicture,
                        um.imagealt AS umimagealt';
        $umtable  = " LEFT JOIN {user} um ON (d.usermodified = um.id)";
    }

    $allnames = get_all_user_name_fields(true, 'u');
    $sql = "SELECT $postdata, d.name, d.timemodified, d.usermodified, d.groupid, d.timestart, d.timeend, $allnames,
                   u.email, u.picture, u.imagealt $umfields
              FROM {cybrary_discussions} d
                   JOIN {cybrary_posts} p ON p.discussion = d.id
                   JOIN {user} u ON p.userid = u.id
                   $umtable
             WHERE d.cybrary = ? AND p.parent = 0
                   $timelimit $groupselect
          ORDER BY $cybrariesort";
    return $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
}

/**
 * Gets the neighbours (previous and next) of a discussion.
 *
 * The calculation is based on the timemodified of the discussion and does not handle
 * the neighbours having an identical timemodified. The reason is that we do not have any
 * other mean to sort the records, e.g. we cannot use IDs as a greater ID can have a lower
 * timemodified.
 *
 * For blog-style cybraries, the calculation is based on the original creation time of the
 * blog post.
 *
 * Please note that this does not check whether or not the discussion passed is accessible
 * by the user, it simply uses it as a reference to find the neighbours. On the other hand,
 * the returned neighbours are checked and are accessible to the current user.
 *
 * @param object $cm The CM record.
 * @param object $discussion The discussion record.
 * @param object $cybrary The cybrary instance record.
 * @return array That always contains the keys 'prev' and 'next'. When there is a result
 *               they contain the record with minimal information such as 'id' and 'name'.
 *               When the neighbour is not found the value is false.
 */
function cybrary_get_discussion_neighbours($cm, $discussion, $cybrary) {
    global $CFG, $DB, $USER;

    if ($cm->instance != $discussion->cybrary or $discussion->cybrary != $cybrary->id or $cybrary->id != $cm->instance) {
        throw new coding_exception('Discussion is not part of the same cybrary.');
    }

    $neighbours = array('prev' => false, 'next' => false);
    $now = round(time(), -2);
    $params = array();

    $modcontext = context_module::instance($cm->id);
    $groupmode    = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    // Users must fulfill timed posts.
    $timelimit = '';
    if (!empty($CFG->cybrary_enabletimedposts)) {
        if (!has_capability('mod/cybrary:viewhiddentimedposts', $modcontext)) {
            $timelimit = ' AND ((d.timestart <= :tltimestart AND (d.timeend = 0 OR d.timeend > :tltimeend))';
            $params['tltimestart'] = $now;
            $params['tltimeend'] = $now;
            if (isloggedin()) {
                $timelimit .= ' OR d.userid = :tluserid';
                $params['tluserid'] = $USER->id;
            }
            $timelimit .= ')';
        }
    }

    // Limiting to posts accessible according to groups.
    $groupselect = '';
    if ($groupmode) {
        if ($groupmode == VISIBLEGROUPS || has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = 'AND (d.groupid = :groupid OR d.groupid = -1)';
                $params['groupid'] = $currentgroup;
            }
        } else {
            if ($currentgroup) {
                $groupselect = 'AND (d.groupid = :groupid OR d.groupid = -1)';
                $params['groupid'] = $currentgroup;
            } else {
                $groupselect = 'AND d.groupid = -1';
            }
        }
    }

    if ($cybrary->type === 'blog') {
        $params['cybraryid'] = $cm->instance;
        $params['discid1'] = $discussion->id;
        $params['discid2'] = $discussion->id;

        $sql = "SELECT d.id, d.name, d.timemodified, d.groupid, d.timestart, d.timeend
                  FROM {cybrary_discussions} d
                  JOIN {cybrary_posts} p ON d.firstpost = p.id
                 WHERE d.cybrary = :cybraryid
                   AND d.id <> :discid1
                       $timelimit
                       $groupselect";

        $sub = "SELECT pp.created
                  FROM {cybrary_discussions} dd
                  JOIN {cybrary_posts} pp ON dd.firstpost = pp.id
                 WHERE dd.id = :discid2";

        $prevsql = $sql . " AND p.created < ($sub)
                       ORDER BY p.created DESC";

        $nextsql = $sql . " AND p.created > ($sub)
                       ORDER BY p.created ASC";

        $neighbours['prev'] = $DB->get_record_sql($prevsql, $params, IGNORE_MULTIPLE);
        $neighbours['next'] = $DB->get_record_sql($nextsql, $params, IGNORE_MULTIPLE);

    } else {
        $params['cybraryid'] = $cm->instance;
        $params['discid'] = $discussion->id;
        $params['disctimemodified'] = $discussion->timemodified;

        $sql = "SELECT d.id, d.name, d.timemodified, d.groupid, d.timestart, d.timeend
                  FROM {cybrary_discussions} d
                 WHERE d.cybrary = :cybraryid
                   AND d.id <> :discid
                       $timelimit
                       $groupselect";

        if (empty($CFG->cybrary_enabletimedposts)) {
            $prevsql = $sql . " AND d.timemodified < :disctimemodified";
            $nextsql = $sql . " AND d.timemodified > :disctimemodified";

        } else {
            // Normally we would just use the timemodified for sorting
            // discussion posts. However, when timed discussions are enabled,
            // then posts need to be sorted base on the later of timemodified
            // or the release date of the post (timestart).
            $params['disctimecompare'] = $discussion->timemodified;
            if ($discussion->timemodified < $discussion->timestart) {
                $params['disctimecompare'] = $discussion->timestart;
            }

            // Here we need to take into account the release time (timestart)
            // if one is set, of the neighbouring posts and compare it to the
            // timestart or timemodified of *this* post depending on if the
            // release date of this post is in the future or not.
            // This stops discussions that appear later because of the
            // timestart value from being buried under discussions that were
            // made afterwards.
            $prevsql = $sql . " AND CASE WHEN d.timemodified < d.timestart
                                    THEN d.timestart ELSE d.timemodified END < :disctimecompare";
            $nextsql = $sql . " AND CASE WHEN d.timemodified < d.timestart
                                    THEN d.timestart ELSE d.timemodified END > :disctimecompare";
        }
        $prevsql .= ' ORDER BY '.cybrary_get_default_sort_order();
        $nextsql .= ' ORDER BY '.cybrary_get_default_sort_order(false);

        $neighbours['prev'] = $DB->get_record_sql($prevsql, $params, IGNORE_MULTIPLE);
        $neighbours['next'] = $DB->get_record_sql($nextsql, $params, IGNORE_MULTIPLE);
    }

    return $neighbours;
}

/**
 * Get the sql to use in the ORDER BY clause for cybrary discussions.
 *
 * This has the ordering take timed discussion windows into account.
 *
 * @param bool $desc True for DESC, False for ASC.
 * @param string $compare The field in the SQL to compare to normally sort by.
 * @param string $prefix The prefix being used for the discussion table.
 * @return string
 */
function cybrary_get_default_sort_order($desc = true, $compare = 'd.timemodified', $prefix = 'd') {
    global $CFG;

    if (!empty($prefix)) {
        $prefix .= '.';
    }

    $dir = $desc ? 'DESC' : 'ASC';

    $sort = "{$prefix}timemodified";
    if (!empty($CFG->cybrary_enabletimedposts)) {
        $sort = "CASE WHEN {$compare} < {$prefix}timestart
                 THEN {$prefix}timestart
                 ELSE {$compare}
                 END";
    }
    return "$sort $dir";
}

/**
 *
 * @global object
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $cm
 * @return array
 */
function cybrary_get_discussions_unread($cm) {
    global $CFG, $DB, $USER;

    $now = round(time(), -2);
    $cutoffdate = $now - ($CFG->cybrary_oldpostdays*24*60*60);

    $params = array();
    $groupmode    = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        $modcontext = context_module::instance($cm->id);

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = :currentgroup OR d.groupid = -1)";
                $params['currentgroup'] = $currentgroup;
            } else {
                $groupselect = "";
            }

        } else {
            //separate groups without access all
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = :currentgroup OR d.groupid = -1)";
                $params['currentgroup'] = $currentgroup;
            } else {
                $groupselect = "AND d.groupid = -1";
            }
        }
    } else {
        $groupselect = "";
    }

    if (!empty($CFG->cybrary_enabletimedposts)) {
        $timedsql = "AND d.timestart < :now1 AND (d.timeend = 0 OR d.timeend > :now2)";
        $params['now1'] = $now;
        $params['now2'] = $now;
    } else {
        $timedsql = "";
    }

    $sql = "SELECT d.id, COUNT(p.id) AS unread
              FROM {cybrary_discussions} d
                   JOIN {cybrary_posts} p     ON p.discussion = d.id
                   LEFT JOIN {cybrary_read} r ON (r.postid = p.id AND r.userid = $USER->id)
             WHERE d.cybrary = {$cm->instance}
                   AND p.modified >= :cutoffdate AND r.id is NULL
                   $groupselect
                   $timedsql
          GROUP BY d.id";
    $params['cutoffdate'] = $cutoffdate;

    if ($unreads = $DB->get_records_sql($sql, $params)) {
        foreach ($unreads as $unread) {
            $unreads[$unread->id] = $unread->unread;
        }
        return $unreads;
    } else {
        return array();
    }
}

/**
 * @global object
 * @global object
 * @global object
 * @uses CONEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $cm
 * @return array
 */
function cybrary_get_discussions_count($cm) {
    global $CFG, $DB, $USER;

    $now = round(time(), -2);
    $params = array($cm->instance);
    $groupmode    = groups_get_activity_groupmode($cm);
    $currentgroup = groups_get_activity_group($cm);

    if ($groupmode) {
        $modcontext = context_module::instance($cm->id);

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $modcontext)) {
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "";
            }

        } else {
            //seprate groups without access all
            if ($currentgroup) {
                $groupselect = "AND (d.groupid = ? OR d.groupid = -1)";
                $params[] = $currentgroup;
            } else {
                $groupselect = "AND d.groupid = -1";
            }
        }
    } else {
        $groupselect = "";
    }

    $cutoffdate = $now - ($CFG->cybrary_oldpostdays*24*60*60);

    $timelimit = "";

    if (!empty($CFG->cybrary_enabletimedposts)) {

        $modcontext = context_module::instance($cm->id);

        if (!has_capability('mod/cybrary:viewhiddentimedposts', $modcontext)) {
            $timelimit = " AND ((d.timestart <= ? AND (d.timeend = 0 OR d.timeend > ?))";
            $params[] = $now;
            $params[] = $now;
            if (isloggedin()) {
                $timelimit .= " OR d.userid = ?";
                $params[] = $USER->id;
            }
            $timelimit .= ")";
        }
    }

    $sql = "SELECT COUNT(d.id)
              FROM {cybrary_discussions} d
                   JOIN {cybrary_posts} p ON p.discussion = d.id
             WHERE d.cybrary = ? AND p.parent = 0
                   $groupselect $timelimit";

    return $DB->get_field_sql($sql, $params);
}


// OTHER FUNCTIONS ///////////////////////////////////////////////////////////


/**
 * @global object
 * @global object
 * @param int $courseid
 * @param string $type
 */
function cybrary_get_course_cybrary($courseid, $type) {
// How to set up special 1-per-course cybraries
    global $CFG, $DB, $OUTPUT, $USER;

    if ($cybraries = $DB->get_records_select("cybrary", "course = ? AND type = ?", array($courseid, $type), "id ASC")) {
        // There should always only be ONE, but with the right combination of
        // errors there might be more.  In this case, just return the oldest one (lowest ID).
        foreach ($cybraries as $cybrary) {
            return $cybrary;   // ie the first one
        }
    }

    // Doesn't exist, so create one now.
    $cybrary = new stdClass();
    $cybrary->course = $courseid;
    $cybrary->type = "$type";
    if (!empty($USER->htmleditor)) {
        $cybrary->introformat = $USER->htmleditor;
    }
    switch ($cybrary->type) {
        case "news":
            $cybrary->name  = get_string("namenews", "cybrary");
            $cybrary->intro = get_string("intronews", "cybrary");
            $cybrary->forcesubscribe = CYBRARY_FORCESUBSCRIBE;
            $cybrary->assessed = 0;
            if ($courseid == SITEID) {
                $cybrary->name  = get_string("sitenews");
                $cybrary->forcesubscribe = 0;
            }
            break;
        case "social":
            $cybrary->name  = get_string("namesocial", "cybrary");
            $cybrary->intro = get_string("introsocial", "cybrary");
            $cybrary->assessed = 0;
            $cybrary->forcesubscribe = 0;
            break;
        case "blog":
            $cybrary->name = get_string('blogcybrary', 'cybrary');
            $cybrary->intro = get_string('introblog', 'cybrary');
            $cybrary->assessed = 0;
            $cybrary->forcesubscribe = 0;
            break;
        default:
            echo $OUTPUT->notification("That cybrary type doesn't exist!");
            return false;
            break;
    }

    $cybrary->timemodified = time();
    $cybrary->id = $DB->insert_record("cybrary", $cybrary);

    if (! $module = $DB->get_record("modules", array("name" => "cybrary"))) {
        echo $OUTPUT->notification("Could not find cybrary module!!");
        return false;
    }
    $mod = new stdClass();
    $mod->course = $courseid;
    $mod->module = $module->id;
    $mod->instance = $cybrary->id;
    $mod->section = 0;
    include_once("$CFG->dirroot/course/lib.php");
    if (! $mod->coursemodule = add_course_module($mod) ) {
        echo $OUTPUT->notification("Could not add a new course module to the course '" . $courseid . "'");
        return false;
    }
    $sectionid = course_add_cm_to_section($courseid, $mod->coursemodule, 0);
    return $DB->get_record("cybrary", array("id" => "$cybrary->id"));
}

/**
 * Print a cybrary post
 *
 * @global object
 * @global object
 * @uses CYBRARY_MODE_THREADED
 * @uses PORTFOLIO_FORMAT_PLAINHTML
 * @uses PORTFOLIO_FORMAT_FILE
 * @uses PORTFOLIO_FORMAT_RICHHTML
 * @uses PORTFOLIO_ADD_TEXT_LINK
 * @uses CONTEXT_MODULE
 * @param object $post The post to print.
 * @param object $discussion
 * @param object $cybrary
 * @param object $cm
 * @param object $course
 * @param boolean $ownpost Whether this post belongs to the current user.
 * @param boolean $reply Whether to print a 'reply' link at the bottom of the message.
 * @param boolean $link Just print a shortened version of the post as a link to the full post.
 * @param string $footer Extra stuff to print after the message.
 * @param string $highlight Space-separated list of terms to highlight.
 * @param int $post_read true, false or -99. If we already know whether this user
 *          has read this post, pass that in, otherwise, pass in -99, and this
 *          function will work it out.
 * @param boolean $dummyifcantsee When cybrary_user_can_see_post says that
 *          the current user can't see this post, if this argument is true
 *          (the default) then print a dummy 'you can't see this post' post.
 *          If false, don't output anything at all.
 * @param bool|null $istracked
 * @return void
 */
function cybrary_print_post($post, $discussion, $cybrary, &$cm, $course, $ownpost=false, $reply=false, $link=false,
                          $footer="", $highlight="", $postisread=null, $dummyifcantsee=true, $istracked=null, $return=false) {
    global $USER, $CFG, $OUTPUT;

    require_once($CFG->libdir . '/filelib.php');

    // String cache
    static $str;

    $modcontext = context_module::instance($cm->id);

    $post->course = $course->id;
    $post->cybrary  = $cybrary->id;
    $post->message = file_rewrite_pluginfile_urls($post->message, 'pluginfile.php', $modcontext->id, 'mod_cybrary', 'post', $post->id);
    if (!empty($CFG->enableplagiarism)) {
        require_once($CFG->libdir.'/plagiarismlib.php');
        $post->message .= plagiarism_get_links(array('userid' => $post->userid,
            'content' => $post->message,
            'cmid' => $cm->id,
            'course' => $post->course,
            'cybrary' => $post->cybrary));
    }

    // caching
    if (!isset($cm->cache)) {
        $cm->cache = new stdClass;
    }

    if (!isset($cm->cache->caps)) {
        $cm->cache->caps = array();
        $cm->cache->caps['mod/cybrary:viewdiscussion']   = has_capability('mod/cybrary:viewdiscussion', $modcontext);
        $cm->cache->caps['moodle/site:viewfullnames']  = has_capability('moodle/site:viewfullnames', $modcontext);
        $cm->cache->caps['mod/cybrary:editanypost']      = has_capability('mod/cybrary:editanypost', $modcontext);
        $cm->cache->caps['mod/cybrary:splitdiscussions'] = has_capability('mod/cybrary:splitdiscussions', $modcontext);
        $cm->cache->caps['mod/cybrary:deleteownpost']    = has_capability('mod/cybrary:deleteownpost', $modcontext);
        $cm->cache->caps['mod/cybrary:deleteanypost']    = has_capability('mod/cybrary:deleteanypost', $modcontext);
        $cm->cache->caps['mod/cybrary:viewanyrating']    = has_capability('mod/cybrary:viewanyrating', $modcontext);
        $cm->cache->caps['mod/cybrary:exportpost']       = has_capability('mod/cybrary:exportpost', $modcontext);
        $cm->cache->caps['mod/cybrary:exportownpost']    = has_capability('mod/cybrary:exportownpost', $modcontext);
    }

    if (!isset($cm->uservisible)) {
        $cm->uservisible = \core_availability\info_module::is_user_visible($cm, 0, false);
    }

    if ($istracked && is_null($postisread)) {
        $postisread = cybrary_tp_is_post_read($USER->id, $post);
    }

    if (!cybrary_user_can_see_post($cybrary, $discussion, $post, NULL, $cm)) {
        $output = '';
        if (!$dummyifcantsee) {
            if ($return) {
                return $output;
            }
            echo $output;
            return;
        }
        $output .= html_writer::tag('a', '', array('id'=>'p'.$post->id));
        $output .= html_writer::start_tag('div', array('class'=>'cybrarypost clearfix',
                                                       'role' => 'region',
                                                       'aria-label' => get_string('hiddencybrarypost', 'cybrary')));
        $output .= html_writer::start_tag('div', array('class'=>'row header'));
        $output .= html_writer::tag('div', '', array('class'=>'left picture')); // Picture
        if ($post->parent) {
            $output .= html_writer::start_tag('div', array('class'=>'topic'));
        } else {
            $output .= html_writer::start_tag('div', array('class'=>'topic starter'));
        }
        $output .= html_writer::tag('div', get_string('cybrariesubjecthidden','cybrary'), array('class' => 'subject',
                                                                                           'role' => 'header')); // Subject.
        $output .= html_writer::tag('div', get_string('cybraryauthorhidden', 'cybrary'), array('class' => 'author',
                                                                                           'role' => 'header')); // Author.
        $output .= html_writer::end_tag('div');
        $output .= html_writer::end_tag('div'); // row
        $output .= html_writer::start_tag('div', array('class'=>'row'));
        $output .= html_writer::tag('div', '&nbsp;', array('class'=>'left side')); // Groups
        $output .= html_writer::tag('div', get_string('cybrarybodyhidden','cybrary'), array('class'=>'content')); // Content
        $output .= html_writer::end_tag('div'); // row
        $output .= html_writer::end_tag('div'); // cybrarypost

        if ($return) {
            return $output;
        }
        echo $output;
        return;
    }

    if (empty($str)) {
        $str = new stdClass;
        $str->edit         = get_string('edit', 'cybrary');
        $str->delete       = get_string('delete', 'cybrary');
        $str->reply        = get_string('reply', 'cybrary');
        $str->parent       = get_string('parent', 'cybrary');
        $str->pruneheading = get_string('pruneheading', 'cybrary');
        $str->prune        = get_string('prune', 'cybrary');
        $str->displaymode     = get_user_preferences('cybrary_displaymode', $CFG->cybrary_displaymode);
        $str->markread     = get_string('markread', 'cybrary');
        $str->markunread   = get_string('markunread', 'cybrary');
    }

    $discussionlink = new moodle_url('/mod/cybrary/discuss.php', array('d'=>$post->discussion));

    // Build an object that represents the posting user
    $postuser = new stdClass;
    $postuserfields = explode(',', user_picture::fields());
    $postuser = username_load_fields_from_object($postuser, $post, null, $postuserfields);
    $postuser->id = $post->userid;
    $postuser->fullname    = fullname($postuser, $cm->cache->caps['moodle/site:viewfullnames']);
    $postuser->profilelink = new moodle_url('/user/view.php', array('id'=>$post->userid, 'course'=>$course->id));

    // Prepare the groups the posting user belongs to
    if (isset($cm->cache->usersgroups)) {
        $groups = array();
        if (isset($cm->cache->usersgroups[$post->userid])) {
            foreach ($cm->cache->usersgroups[$post->userid] as $gid) {
                $groups[$gid] = $cm->cache->groups[$gid];
            }
        }
    } else {
        $groups = groups_get_all_groups($course->id, $post->userid, $cm->groupingid);
    }

    // Prepare the attachements for the post, files then images
    list($attachments, $attachedimages) = cybrary_print_attachments($post, $cm, 'separateimages');

    // Determine if we need to shorten this post
    $shortenpost = ($link && (strlen(strip_tags($post->message)) > $CFG->cybrary_longpost));


    // Prepare an array of commands
    $commands = array();

    // SPECIAL CASE: The front page can display a news item post to non-logged in users.
    // Don't display the mark read / unread controls in this case.
    if ($istracked && $CFG->cybrary_usermarksread && isloggedin()) {
        $url = new moodle_url($discussionlink, array('postid'=>$post->id, 'mark'=>'unread'));
        $text = $str->markunread;
        if (!$postisread) {
            $url->param('mark', 'read');
            $text = $str->markread;
        }
        if ($str->displaymode == CYBRARY_MODE_THREADED) {
            $url->param('parent', $post->parent);
        } else {
            $url->set_anchor('p'.$post->id);
        }
        $commands[] = array('url'=>$url, 'text'=>$text);
    }

    // Zoom in to the parent specifically
    if ($post->parent) {
        $url = new moodle_url($discussionlink);
        if ($str->displaymode == CYBRARY_MODE_THREADED) {
            $url->param('parent', $post->parent);
        } else {
            $url->set_anchor('p'.$post->parent);
        }
        $commands[] = array('url'=>$url, 'text'=>$str->parent);
    }

    // Hack for allow to edit news posts those are not displayed yet until they are displayed
    $age = time() - $post->created;
    if (!$post->parent && $cybrary->type == 'news' && $discussion->timestart > time()) {
        $age = 0;
    }

    if ($cybrary->type == 'single' and $discussion->firstpost == $post->id) {
        if (has_capability('moodle/course:manageactivities', $modcontext)) {
            // The first post in single simple is the cybrary description.
            $commands[] = array('url'=>new moodle_url('/course/modedit.php', array('update'=>$cm->id, 'sesskey'=>sesskey(), 'return'=>1)), 'text'=>$str->edit);
        }
    } else if (($ownpost && $age < $CFG->maxeditingtime) || $cm->cache->caps['mod/cybrary:editanypost']) {
        $commands[] = array('url'=>new moodle_url('/mod/cybrary/post.php', array('edit'=>$post->id)), 'text'=>$str->edit);
    }

    if ($cm->cache->caps['mod/cybrary:splitdiscussions'] && $post->parent && $cybrary->type != 'single') {
        $commands[] = array('url'=>new moodle_url('/mod/cybrary/post.php', array('prune'=>$post->id)), 'text'=>$str->prune, 'title'=>$str->pruneheading);
    }

    if ($cybrary->type == 'single' and $discussion->firstpost == $post->id) {
        // Do not allow deleting of first post in single simple type.
    } else if (($ownpost && $age < $CFG->maxeditingtime && $cm->cache->caps['mod/cybrary:deleteownpost']) || $cm->cache->caps['mod/cybrary:deleteanypost']) {
        $commands[] = array('url'=>new moodle_url('/mod/cybrary/post.php', array('delete'=>$post->id)), 'text'=>$str->delete);
    }

    if ($reply) {
        $commands[] = array('url'=>new moodle_url('/mod/cybrary/post.php#mformcybrary', array('reply'=>$post->id)), 'text'=>$str->reply);
    }

    if ($CFG->enableportfolios && ($cm->cache->caps['mod/cybrary:exportpost'] || ($ownpost && $cm->cache->caps['mod/cybrary:exportownpost']))) {
        $p = array('postid' => $post->id);
        require_once($CFG->libdir.'/portfoliolib.php');
        $button = new portfolio_add_button();
        $button->set_callback_options('cybrary_portfolio_caller', array('postid' => $post->id), 'mod_cybrary');
        if (empty($attachments)) {
            $button->set_formats(PORTFOLIO_FORMAT_PLAINHTML);
        } else {
            $button->set_formats(PORTFOLIO_FORMAT_RICHHTML);
        }

        $porfoliohtml = $button->to_html(PORTFOLIO_ADD_TEXT_LINK);
        if (!empty($porfoliohtml)) {
            $commands[] = $porfoliohtml;
        }
    }
    // Finished building commands


    // Begin output

    $output  = '';

    if ($istracked) {
        if ($postisread) {
            $cybrarypostclass = ' read';
        } else {
            $cybrarypostclass = ' unread';
            $output .= html_writer::tag('a', '', array('name'=>'unread'));
        }
    } else {
        // ignore trackign status if not tracked or tracked param missing
        $cybrarypostclass = '';
    }

    $topicclass = '';
    if (empty($post->parent)) {
        $topicclass = ' firstpost starter';
    }

    if (!empty($post->lastpost)) {
        $cybrarypostclass .= ' lastpost';
    }

    $postbyuser = new stdClass;
    $postbyuser->post = $post->subject;
    $postbyuser->user = $postuser->fullname;
    $discussionbyuser = get_string('postbyuser', 'cybrary', $postbyuser);
    $output .= html_writer::tag('a', '', array('id'=>'p'.$post->id));
    $output .= html_writer::start_tag('div', array('class'=>'cybrarypost clearfix'.$cybrarypostclass.$topicclass,
                                                   'role' => 'region',
                                                   'aria-label' => $discussionbyuser));
    $output .= html_writer::start_tag('div', array('class'=>'row header clearfix'));
    $output .= html_writer::start_tag('div', array('class'=>'left picture'));
    $output .= $OUTPUT->user_picture($postuser, array('courseid'=>$course->id));
    $output .= html_writer::end_tag('div');


    $output .= html_writer::start_tag('div', array('class'=>'topic'.$topicclass));

    $postsubject = $post->subject;
    if (empty($post->subjectnoformat)) {
        $postsubject = format_string($postsubject);
    }
    $output .= html_writer::tag('div', $postsubject, array('class'=>'subject',
                                                           'role' => 'heading',
                                                           'aria-level' => '2'));

    $by = new stdClass();
    $by->name = html_writer::link($postuser->profilelink, $postuser->fullname);
    $by->date = userdate($post->modified);
    $output .= html_writer::tag('div', get_string('bynameondate', 'cybrary', $by), array('class'=>'author',
                                                                                       'role' => 'heading',
                                                                                       'aria-level' => '2'));

    $output .= html_writer::end_tag('div'); //topic
    $output .= html_writer::end_tag('div'); //row

    $output .= html_writer::start_tag('div', array('class'=>'row maincontent clearfix'));
    $output .= html_writer::start_tag('div', array('class'=>'left'));

    $groupoutput = '';
    if ($groups) {
        $groupoutput = print_group_picture($groups, $course->id, false, true, true);
    }
    if (empty($groupoutput)) {
        $groupoutput = '&nbsp;';
    }
    $output .= html_writer::tag('div', $groupoutput, array('class'=>'grouppictures'));

    $output .= html_writer::end_tag('div'); //left side
    $output .= html_writer::start_tag('div', array('class'=>'no-overflow'));
    $output .= html_writer::start_tag('div', array('class'=>'content'));

    $options = new stdClass;
    $options->para    = false;
    $options->trusted = $post->messagetrust;
    $options->context = $modcontext;
    if ($shortenpost) {
        // Prepare shortened version by filtering the text then shortening it.
        $postclass    = 'shortenedpost';
        $postcontent  = format_text($post->message, $post->messageformat, $options);
        $postcontent  = shorten_text($postcontent, $CFG->cybrary_shortpost);
        $postcontent .= html_writer::link($discussionlink, get_string('readtherest', 'cybrary'));
        $postcontent .= html_writer::tag('div', '('.get_string('numwords', 'moodle', count_words($post->message)).')',
            array('class'=>'post-word-count'));
    } else {
        // Prepare whole post
        $postclass    = 'fullpost';
        $postcontent  = format_text($post->message, $post->messageformat, $options, $course->id);
        if (!empty($highlight)) {
            $postcontent = highlight($highlight, $postcontent);
        }
        if (!empty($cybrary->displaywordcount)) {
            $postcontent .= html_writer::tag('div', get_string('numwords', 'moodle', count_words($post->message)),
                array('class'=>'post-word-count'));
        }
        $postcontent .= html_writer::tag('div', $attachedimages, array('class'=>'attachedimages'));
    }

    // Output the post content
    $output .= html_writer::tag('div', $postcontent, array('class'=>'posting '.$postclass));
    $output .= html_writer::end_tag('div'); // Content
    $output .= html_writer::end_tag('div'); // Content mask
    $output .= html_writer::end_tag('div'); // Row

    $output .= html_writer::start_tag('div', array('class'=>'row side'));
    $output .= html_writer::tag('div','&nbsp;', array('class'=>'left'));
    $output .= html_writer::start_tag('div', array('class'=>'options clearfix'));

    if (!empty($attachments)) {
        $output .= html_writer::tag('div', $attachments, array('class' => 'attachments'));
    }

    // Output ratings
    if (!empty($post->rating)) {
        $output .= html_writer::tag('div', $OUTPUT->render($post->rating), array('class'=>'cybrary-post-rating'));
    }

    // Output the commands
    $commandhtml = array();
    foreach ($commands as $command) {
        if (is_array($command)) {
            $commandhtml[] = html_writer::link($command['url'], $command['text']);
        } else {
            $commandhtml[] = $command;
        }
    }
    $output .= html_writer::tag('div', implode(' | ', $commandhtml), array('class'=>'commands'));

    // Output link to post if required
    if ($link && cybrary_user_can_post($cybrary, $discussion, $USER, $cm, $course, $modcontext)) {
        if ($post->replies == 1) {
            $replystring = get_string('repliesone', 'cybrary', $post->replies);
        } else {
            $replystring = get_string('repliesmany', 'cybrary', $post->replies);
        }
        if (!empty($discussion->unread) && $discussion->unread !== '-') {
            $replystring .= ' <span class="sep">/</span> <span class="unread">';
            if ($discussion->unread == 1) {
                $replystring .= get_string('unreadpostsone', 'cybrary');
            } else {
                $replystring .= get_string('unreadpostsnumber', 'cybrary', $discussion->unread);
            }
            $replystring .= '</span>';
        }

        $output .= html_writer::start_tag('div', array('class'=>'link'));
        $output .= html_writer::link($discussionlink, get_string('discussthistopic', 'cybrary'));
        $output .= '&nbsp;('.$replystring.')';
        $output .= html_writer::end_tag('div'); // link
    }

    // Output footer if required
    if ($footer) {
        $output .= html_writer::tag('div', $footer, array('class'=>'footer'));
    }

    // Close remaining open divs
    $output .= html_writer::end_tag('div'); // content
    $output .= html_writer::end_tag('div'); // row
    $output .= html_writer::end_tag('div'); // cybrarypost

    // Mark the cybrary post as read if required
    if ($istracked && !$CFG->cybrary_usermarksread && !$postisread) {
        cybrary_tp_mark_post_read($USER->id, $post, $cybrary->id);
    }

    if ($return) {
        return $output;
    }
    echo $output;
    return;
}

/**
 * Return rating related permissions
 *
 * @param string $options the context id
 * @return array an associative array of the user's rating permissions
 */
function cybrary_rating_permissions($contextid, $component, $ratingarea) {
    $context = context::instance_by_id($contextid, MUST_EXIST);
    if ($component != 'mod_cybrary' || $ratingarea != 'post') {
        // We don't know about this component/ratingarea so just return null to get the
        // default restrictive permissions.
        return null;
    }
    return array(
        'view'    => has_capability('mod/cybrary:viewrating', $context),
        'viewany' => has_capability('mod/cybrary:viewanyrating', $context),
        'viewall' => has_capability('mod/cybrary:viewallratings', $context),
        'rate'    => has_capability('mod/cybrary:rate', $context)
    );
}

/**
 * Validates a submitted rating
 * @param array $params submitted data
 *            context => object the context in which the rated items exists [required]
 *            component => The component for this module - should always be mod_cybrary [required]
 *            ratingarea => object the context in which the rated items exists [required]
 *            itemid => int the ID of the object being rated [required]
 *            scaleid => int the scale from which the user can select a rating. Used for bounds checking. [required]
 *            rating => int the submitted rating [required]
 *            rateduserid => int the id of the user whose items have been rated. NOT the user who submitted the ratings. 0 to update all. [required]
 *            aggregation => int the aggregation method to apply when calculating grades ie RATING_AGGREGATE_AVERAGE [required]
 * @return boolean true if the rating is valid. Will throw rating_exception if not
 */
function cybrary_rating_validate($params) {
    global $DB, $USER;

    // Check the component is mod_cybrary
    if ($params['component'] != 'mod_cybrary') {
        throw new rating_exception('invalidcomponent');
    }

    // Check the ratingarea is post (the only rating area in cybrary)
    if ($params['ratingarea'] != 'post') {
        throw new rating_exception('invalidratingarea');
    }

    // Check the rateduserid is not the current user .. you can't rate your own posts
    if ($params['rateduserid'] == $USER->id) {
        throw new rating_exception('nopermissiontorate');
    }

    // Fetch all the related records ... we need to do this anyway to call cybrary_user_can_see_post
    $post = $DB->get_record('cybrary_posts', array('id' => $params['itemid'], 'userid' => $params['rateduserid']), '*', MUST_EXIST);
    $discussion = $DB->get_record('cybrary_discussions', array('id' => $post->discussion), '*', MUST_EXIST);
    $cybrary = $DB->get_record('cybrary', array('id' => $discussion->cybrary), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cybrary->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('cybrary', $cybrary->id, $course->id , false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    // Make sure the context provided is the context of the cybrary
    if ($context->id != $params['context']->id) {
        throw new rating_exception('invalidcontext');
    }

    if ($cybrary->scale != $params['scaleid']) {
        //the scale being submitted doesnt match the one in the database
        throw new rating_exception('invalidscaleid');
    }

    // check the item we're rating was created in the assessable time window
    if (!empty($cybrary->assesstimestart) && !empty($cybrary->assesstimefinish)) {
        if ($post->created < $cybrary->assesstimestart || $post->created > $cybrary->assesstimefinish) {
            throw new rating_exception('notavailable');
        }
    }

    //check that the submitted rating is valid for the scale

    // lower limit
    if ($params['rating'] < 0  && $params['rating'] != RATING_UNSET_RATING) {
        throw new rating_exception('invalidnum');
    }

    // upper limit
    if ($cybrary->scale < 0) {
        //its a custom scale
        $scalerecord = $DB->get_record('scale', array('id' => -$cybrary->scale));
        if ($scalerecord) {
            $scalearray = explode(',', $scalerecord->scale);
            if ($params['rating'] > count($scalearray)) {
                throw new rating_exception('invalidnum');
            }
        } else {
            throw new rating_exception('invalidscaleid');
        }
    } else if ($params['rating'] > $cybrary->scale) {
        //if its numeric and submitted rating is above maximum
        throw new rating_exception('invalidnum');
    }

    // Make sure groups allow this user to see the item they're rating
    if ($discussion->groupid > 0 and $groupmode = groups_get_activity_groupmode($cm, $course)) {   // Groups are being used
        if (!groups_group_exists($discussion->groupid)) { // Can't find group
            throw new rating_exception('cannotfindgroup');//something is wrong
        }

        if (!groups_is_member($discussion->groupid) and !has_capability('moodle/site:accessallgroups', $context)) {
            // do not allow rating of posts from other groups when in SEPARATEGROUPS or VISIBLEGROUPS
            throw new rating_exception('notmemberofgroup');
        }
    }

    // perform some final capability checks
    if (!cybrary_user_can_see_post($cybrary, $discussion, $post, $USER, $cm)) {
        throw new rating_exception('nopermissiontorate');
    }

    return true;
}

/**
 * Can the current user see ratings for a given itemid?
 *
 * @param array $params submitted data
 *            contextid => int contextid [required]
 *            component => The component for this module - should always be mod_cybrary [required]
 *            ratingarea => object the context in which the rated items exists [required]
 *            itemid => int the ID of the object being rated [required]
 *            scaleid => int scale id [optional]
 * @return bool
 * @throws coding_exception
 * @throws rating_exception
 */
function mod_cybrary_rating_can_see_item_ratings($params) {
    global $DB, $USER;

    // Check the component is mod_cybrary.
    if (!isset($params['component']) || $params['component'] != 'mod_cybrary') {
        throw new rating_exception('invalidcomponent');
    }

    // Check the ratingarea is post (the only rating area in cybrary).
    if (!isset($params['ratingarea']) || $params['ratingarea'] != 'post') {
        throw new rating_exception('invalidratingarea');
    }

    if (!isset($params['itemid'])) {
        throw new rating_exception('invaliditemid');
    }

    $post = $DB->get_record('cybrary_posts', array('id' => $params['itemid']), '*', MUST_EXIST);
    $discussion = $DB->get_record('cybrary_discussions', array('id' => $post->discussion), '*', MUST_EXIST);
    $cybrary = $DB->get_record('cybrary', array('id' => $discussion->cybrary), '*', MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cybrary->course), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('cybrary', $cybrary->id, $course->id , false, MUST_EXIST);

    // Perform some final capability checks.
    if (!cybrary_user_can_see_post($cybrary, $discussion, $post, $USER, $cm)) {
        return false;
    }
    return true;
}

/**
 * This function prints the overview of a discussion in the cybrary listing.
 * It needs some discussion information and some post information, these
 * happen to be combined for efficiency in the $post parameter by the function
 * that calls this one: cybrary_print_latest_discussions()
 *
 * @global object
 * @global object
 * @param object $post The post object (passed by reference for speed).
 * @param object $cybrary The cybrary object.
 * @param int $group Current group.
 * @param string $datestring Format to use for the dates.
 * @param boolean $cantrack Is tracking enabled for this cybrary.
 * @param boolean $cybrarytracked Is the user tracking this cybrary.
 * @param boolean $canviewparticipants True if user has the viewparticipants permission for this course
 * @param boolean $canviewhiddentimedposts True if user has the viewhiddentimedposts permission for this cybrary
 */
function cybrary_print_discussion_header(&$post, $cybrary, $group = -1, $datestring = "",
                                        $cantrack = true, $cybrarytracked = true, $canviewparticipants = true, $modcontext = null,
                                        $canviewhiddentimedposts = false) {

    global $COURSE, $USER, $CFG, $OUTPUT, $PAGE;

    static $rowcount;
    static $strmarkalldread;

    if (empty($modcontext)) {
        if (!$cm = get_coursemodule_from_instance('cybrary', $cybrary->id, $cybrary->course)) {
            print_error('invalidcoursemodule');
        }
        $modcontext = context_module::instance($cm->id);
    }

    if (!isset($rowcount)) {
        $rowcount = 0;
        $strmarkalldread = get_string('markalldread', 'cybrary');
    } else {
        $rowcount = ($rowcount + 1) % 2;
    }

    $post->subject = format_string($post->subject,true);

    $timeddiscussion = !empty($CFG->cybrary_enabletimedposts) && ($post->timestart || $post->timeend);
    $timedoutsidewindow = '';
    if ($timeddiscussion && ($post->timestart > time() || ($post->timeend != 0 && $post->timeend < time()))) {
        $timedoutsidewindow = ' dimmed_text';
    }

    echo "\n\n";
    echo '<tr class="discussion r'.$rowcount.$timedoutsidewindow.'">';

    // Topic
    echo '<td class="topic starter">';

    $canalwaysseetimedpost = $USER->id == $post->userid || $canviewhiddentimedposts;
    if ($timeddiscussion && $canalwaysseetimedpost) {
        echo $PAGE->get_renderer('mod_cybrary')->timed_discussion_tooltip($post, empty($timedoutsidewindow));
    }

    echo '<a href="'.$CFG->wwwroot.'/mod/cybrary/discuss.php?d='.$post->discussion.'">'.$post->subject.'</a>';
    echo "</td>\n";

    // Picture
    $postuser = new stdClass();
    $postuserfields = explode(',', user_picture::fields());
    $postuser = username_load_fields_from_object($postuser, $post, null, $postuserfields);
    $postuser->id = $post->userid;
    echo '<td class="picture">';
    echo $OUTPUT->user_picture($postuser, array('courseid'=>$cybrary->course));
    echo "</td>\n";

    // User name
    $fullname = fullname($postuser, has_capability('moodle/site:viewfullnames', $modcontext));
    echo '<td class="author">';
    echo '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$post->userid.'&amp;course='.$cybrary->course.'">'.$fullname.'</a>';
    echo "</td>\n";

    // Group picture
    if ($group !== -1) {  // Groups are active - group is a group data object or NULL
        echo '<td class="picture group">';
        if (!empty($group->picture) and empty($group->hidepicture)) {
            if ($canviewparticipants && $COURSE->groupmode) {
                $picturelink = true;
            } else {
                $picturelink = false;
            }
            print_group_picture($group, $cybrary->course, false, false, $picturelink);
        } else if (isset($group->id)) {
            if ($canviewparticipants && $COURSE->groupmode) {
                echo '<a href="'.$CFG->wwwroot.'/user/index.php?id='.$cybrary->course.'&amp;group='.$group->id.'">'.$group->name.'</a>';
            } else {
                echo $group->name;
            }
        }
        echo "</td>\n";
    }

    if (has_capability('mod/cybrary:viewdiscussion', $modcontext)) {   // Show the column with replies
        echo '<td class="replies">';
        echo '<a href="'.$CFG->wwwroot.'/mod/cybrary/discuss.php?d='.$post->discussion.'">';
        echo $post->replies.'</a>';
        echo "</td>\n";

        if ($cantrack) {
            echo '<td class="replies">';
            if ($cybrarytracked) {
                if ($post->unread > 0) {
                    echo '<span class="unread">';
                    echo '<a href="'.$CFG->wwwroot.'/mod/cybrary/discuss.php?d='.$post->discussion.'#unread">';
                    echo $post->unread;
                    echo '</a>';
                    echo '<a title="'.$strmarkalldread.'" href="'.$CFG->wwwroot.'/mod/cybrary/markposts.php?f='.
                         $cybrary->id.'&amp;d='.$post->discussion.'&amp;mark=read&amp;returnpage=view.php">' .
                         '<img src="'.$OUTPUT->pix_url('t/markasread') . '" class="iconsmall" alt="'.$strmarkalldread.'" /></a>';
                    echo '</span>';
                } else {
                    echo '<span class="read">';
                    echo $post->unread;
                    echo '</span>';
                }
            } else {
                echo '<span class="read">';
                echo '-';
                echo '</span>';
            }
            echo "</td>\n";
        }
    }

    echo '<td class="lastpost">';
    $usedate = (empty($post->timemodified)) ? $post->modified : $post->timemodified;  // Just in case
    $parenturl = '';
    $usermodified = new stdClass();
    $usermodified->id = $post->usermodified;
    $usermodified = username_load_fields_from_object($usermodified, $post, 'um');

    // In QA cybraries we check that the user can view participants.
    if ($cybrary->type !== 'qanda' || $canviewparticipants) {
        echo '<a href="'.$CFG->wwwroot.'/user/view.php?id='.$post->usermodified.'&amp;course='.$cybrary->course.'">'.
             fullname($usermodified).'</a><br />';
        $parenturl = (empty($post->lastpostid)) ? '' : '&amp;parent='.$post->lastpostid;
    }

    echo '<a href="'.$CFG->wwwroot.'/mod/cybrary/discuss.php?d='.$post->discussion.$parenturl.'">'.
          userdate($usedate, $datestring).'</a>';
    echo "</td>\n";

    // is_guest should be used here as this also checks whether the user is a guest in the current course.
    // Guests and visitors cannot subscribe - only enrolled users.
    if ((!is_guest($modcontext, $USER) && isloggedin()) && has_capability('mod/cybrary:viewdiscussion', $modcontext)) {
        // Discussion subscription.
        if (\mod_cybrary\subscriptions::is_subscribable($cybrary)) {
            echo '<td class="discussionsubscription">';
            echo cybrary_get_discussion_subscription_icon($cybrary, $post->discussion);
            echo '</td>';
        }
    }

    echo "</tr>\n\n";

}

/**
 * Return the markup for the discussion subscription toggling icon.
 *
 * @param stdClass $cybrary The cybrary object.
 * @param int $discussionid The discussion to create an icon for.
 * @return string The generated markup.
 */
function cybrary_get_discussion_subscription_icon($cybrary, $discussionid, $returnurl = null, $includetext = false) {
    global $USER, $OUTPUT, $PAGE;

    if ($returnurl === null && $PAGE->url) {
        $returnurl = $PAGE->url->out();
    }

    $o = '';
    $subscriptionstatus = \mod_cybrary\subscriptions::is_subscribed($USER->id, $cybrary, $discussionid);
    $subscriptionlink = new moodle_url('/mod/cybrary/subscribe.php', array(
        'sesskey' => sesskey(),
        'id' => $cybrary->id,
        'd' => $discussionid,
        'returnurl' => $returnurl,
    ));

    if ($includetext) {
        $o .= $subscriptionstatus ? get_string('subscribed', 'mod_cybrary') : get_string('notsubscribed', 'mod_cybrary');
    }

    if ($subscriptionstatus) {
        $output = $OUTPUT->pix_icon('t/subscribed', get_string('clicktounsubscribe', 'cybrary'), 'mod_cybrary');
        if ($includetext) {
            $output .= get_string('subscribed', 'mod_cybrary');
        }

        return html_writer::link($subscriptionlink, $output, array(
                'title' => get_string('clicktounsubscribe', 'cybrary'),
                'class' => 'discussiontoggle iconsmall',
                'data-cybraryid' => $cybrary->id,
                'data-discussionid' => $discussionid,
                'data-includetext' => $includetext,
            ));

    } else {
        $output = $OUTPUT->pix_icon('t/unsubscribed', get_string('clicktosubscribe', 'cybrary'), 'mod_cybrary');
        if ($includetext) {
            $output .= get_string('notsubscribed', 'mod_cybrary');
        }

        return html_writer::link($subscriptionlink, $output, array(
                'title' => get_string('clicktosubscribe', 'cybrary'),
                'class' => 'discussiontoggle iconsmall',
                'data-cybraryid' => $cybrary->id,
                'data-discussionid' => $discussionid,
                'data-includetext' => $includetext,
            ));
    }
}

/**
 * Return a pair of spans containing classes to allow the subscribe and
 * unsubscribe icons to be pre-loaded by a browser.
 *
 * @return string The generated markup
 */
function cybrary_get_discussion_subscription_icon_preloaders() {
    $o = '';
    $o .= html_writer::span('&nbsp;', 'preload-subscribe');
    $o .= html_writer::span('&nbsp;', 'preload-unsubscribe');
    return $o;
}

/**
 * Print the drop down that allows the user to select how they want to have
 * the discussion displayed.
 *
 * @param int $id cybrary id if $cybrarytype is 'single',
 *              discussion id for any other cybrary type
 * @param mixed $mode cybrary layout mode
 * @param string $cybrarytype optional
 */
function cybrary_print_mode_form($id, $mode, $cybrarytype='') {
    global $OUTPUT;
    if ($cybrarytype == 'single') {
        $select = new single_select(new moodle_url("/mod/cybrary/view.php", array('f'=>$id)), 'mode', cybrary_get_layout_modes(), $mode, null, "mode");
        $select->set_label(get_string('displaymode', 'cybrary'), array('class' => 'accesshide'));
        $select->class = "cybrarymode";
    } else {
        $select = new single_select(new moodle_url("/mod/cybrary/discuss.php", array('d'=>$id)), 'mode', cybrary_get_layout_modes(), $mode, null, "mode");
        $select->set_label(get_string('displaymode', 'cybrary'), array('class' => 'accesshide'));
    }
    echo $OUTPUT->render($select);
}

/**
 * @global object
 * @param object $course
 * @param string $search
 * @return string
 */
function cybrary_search_form($course, $search='') {
    global $CFG, $OUTPUT;

    $output  = '<div class="cybrariesearch">';
    $output .= '<form action="'.$CFG->wwwroot.'/mod/cybrary/search.php" style="display:inline">';
    $output .= '<fieldset class="invisiblefieldset">';
    $output .= $OUTPUT->help_icon('search');
    $output .= '<label class="accesshide" for="search" >'.get_string('search', 'cybrary').'</label>';
    $output .= '<input id="search" name="search" type="text" size="18" value="'.s($search, true).'" />';
    $output .= '<label class="accesshide" for="searchcybraries" >'.get_string('searchcybraries', 'cybrary').'</label>';
    $output .= '<input id="searchcybraries" value="'.get_string('searchcybraries', 'cybrary').'" type="submit" />';
    $output .= '<input name="id" type="hidden" value="'.$course->id.'" />';
    $output .= '</fieldset>';
    $output .= '</form>';
    $output .= '</div>';

    return $output;
}


/**
 * @global object
 * @global object
 */
function cybrary_set_return() {
    global $CFG, $SESSION;

    if (! isset($SESSION->fromdiscussion)) {
        $referer = get_local_referer(false);
        // If the referer is NOT a login screen then save it.
        if (! strncasecmp("$CFG->wwwroot/login", $referer, 300)) {
            $SESSION->fromdiscussion = $referer;
        }
    }
}


/**
 * @global object
 * @param string|\moodle_url $default
 * @return string
 */
function cybrary_go_back_to($default) {
    global $SESSION;

    if (!empty($SESSION->fromdiscussion)) {
        $returnto = $SESSION->fromdiscussion;
        unset($SESSION->fromdiscussion);
        return $returnto;
    } else {
        return $default;
    }
}

/**
 * Given a discussion object that is being moved to $cybraryto,
 * this function checks all posts in that discussion
 * for attachments, and if any are found, these are
 * moved to the new cybrary directory.
 *
 * @global object
 * @param object $discussion
 * @param int $cybraryfrom source cybrary id
 * @param int $cybraryto target cybrary id
 * @return bool success
 */
function cybrary_move_attachments($discussion, $cybraryfrom, $cybraryto) {
    global $DB;

    $fs = get_file_storage();

    $newcm = get_coursemodule_from_instance('cybrary', $cybraryto);
    $oldcm = get_coursemodule_from_instance('cybrary', $cybraryfrom);

    $newcontext = context_module::instance($newcm->id);
    $oldcontext = context_module::instance($oldcm->id);

    // loop through all posts, better not use attachment flag ;-)
    if ($posts = $DB->get_records('cybrary_posts', array('discussion'=>$discussion->id), '', 'id, attachment')) {
        foreach ($posts as $post) {
            $fs->move_area_files_to_new_context($oldcontext->id,
                    $newcontext->id, 'mod_cybrary', 'post', $post->id);
            $attachmentsmoved = $fs->move_area_files_to_new_context($oldcontext->id,
                    $newcontext->id, 'mod_cybrary', 'attachment', $post->id);
            if ($attachmentsmoved > 0 && $post->attachment != '1') {
                // Weird - let's fix it
                $post->attachment = '1';
                $DB->update_record('cybrary_posts', $post);
            } else if ($attachmentsmoved == 0 && $post->attachment != '') {
                // Weird - let's fix it
                $post->attachment = '';
                $DB->update_record('cybrary_posts', $post);
            }
        }
    }

    return true;
}

/**
 * Returns attachments as formated text/html optionally with separate images
 *
 * @global object
 * @global object
 * @global object
 * @param object $post
 * @param object $cm
 * @param string $type html/text/separateimages
 * @return mixed string or array of (html text withouth images and image HTML)
 */
function cybrary_print_attachments($post, $cm, $type) {
    global $CFG, $DB, $USER, $OUTPUT;

    if (empty($post->attachment)) {
        return $type !== 'separateimages' ? '' : array('', '');
    }

    if (!in_array($type, array('separateimages', 'html', 'text'))) {
        return $type !== 'separateimages' ? '' : array('', '');
    }

    if (!$context = context_module::instance($cm->id)) {
        return $type !== 'separateimages' ? '' : array('', '');
    }
    $strattachment = get_string('attachment', 'cybrary');

    $fs = get_file_storage();

    $imagereturn = '';
    $output = '';

    $canexport = !empty($CFG->enableportfolios) && (has_capability('mod/cybrary:exportpost', $context) || ($post->userid == $USER->id && has_capability('mod/cybrary:exportownpost', $context)));

    if ($canexport) {
        require_once($CFG->libdir.'/portfoliolib.php');
    }

    // We retrieve all files according to the time that they were created.  In the case that several files were uploaded
    // at the sametime (e.g. in the case of drag/drop upload) we revert to using the filename.
    $files = $fs->get_area_files($context->id, 'mod_cybrary', 'attachment', $post->id, "filename", false);
    if ($files) {
        if ($canexport) {
            $button = new portfolio_add_button();
        }
        foreach ($files as $file) {
            $filename = $file->get_filename();
            $mimetype = $file->get_mimetype();
            $iconimage = $OUTPUT->pix_icon(file_file_icon($file), get_mimetype_description($file), 'moodle', array('class' => 'icon'));
            $path = file_encode_url($CFG->wwwroot.'/pluginfile.php', '/'.$context->id.'/mod_cybrary/attachment/'.$post->id.'/'.$filename);

            if ($type == 'html') {
                $output .= "<a href=\"$path\">$iconimage</a> ";
                $output .= "<a href=\"$path\">".s($filename)."</a>";
                if ($canexport) {
                    $button->set_callback_options('cybrary_portfolio_caller', array('postid' => $post->id, 'attachment' => $file->get_id()), 'mod_cybrary');
                    $button->set_format_by_file($file);
                    $output .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                }
                $output .= "<br />";

            } else if ($type == 'text') {
                $output .= "$strattachment ".s($filename).":\n$path\n";

            } else { //'returnimages'
                if (in_array($mimetype, array('image/gif', 'image/jpeg', 'image/png'))) {
                    // Image attachments don't get printed as links
                    $imagereturn .= "<br /><img src=\"$path\" alt=\"\" />";
                    if ($canexport) {
                        $button->set_callback_options('cybrary_portfolio_caller', array('postid' => $post->id, 'attachment' => $file->get_id()), 'mod_cybrary');
                        $button->set_format_by_file($file);
                        $imagereturn .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                    }
                } else {
                    $output .= "<a href=\"$path\">$iconimage</a> ";
                    $output .= format_text("<a href=\"$path\">".s($filename)."</a>", FORMAT_HTML, array('context'=>$context));
                    if ($canexport) {
                        $button->set_callback_options('cybrary_portfolio_caller', array('postid' => $post->id, 'attachment' => $file->get_id()), 'mod_cybrary');
                        $button->set_format_by_file($file);
                        $output .= $button->to_html(PORTFOLIO_ADD_ICON_LINK);
                    }
                    $output .= '<br />';
                }
            }

            if (!empty($CFG->enableplagiarism)) {
                require_once($CFG->libdir.'/plagiarismlib.php');
                $output .= plagiarism_get_links(array('userid' => $post->userid,
                    'file' => $file,
                    'cmid' => $cm->id,
                    'course' => $cm->course,
                    'cybrary' => $cm->instance));
                $output .= '<br />';
            }
        }
    }

    if ($type !== 'separateimages') {
        return $output;

    } else {
        return array($output, $imagereturn);
    }
}

////////////////////////////////////////////////////////////////////////////////
// File API                                                                   //
////////////////////////////////////////////////////////////////////////////////

/**
 * Lists all browsable file areas
 *
 * @package  mod_cybrary
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @return array
 */
function cybrary_get_file_areas($course, $cm, $context) {
    return array(
        'attachment' => get_string('areaattachment', 'mod_cybrary'),
        'post' => get_string('areapost', 'mod_cybrary'),
    );
}

/**
 * File browsing support for cybrary module.
 *
 * @package  mod_cybrary
 * @category files
 * @param stdClass $browser file browser object
 * @param stdClass $areas file areas
 * @param stdClass $course course object
 * @param stdClass $cm course module
 * @param stdClass $context context module
 * @param string $filearea file area
 * @param int $itemid item ID
 * @param string $filepath file path
 * @param string $filename file name
 * @return file_info instance or null if not found
 */
function cybrary_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    global $CFG, $DB, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return null;
    }

    // filearea must contain a real area
    if (!isset($areas[$filearea])) {
        return null;
    }

    // Note that cybrary_user_can_see_post() additionally allows access for parent roles
    // and it explicitly checks qanda cybrary type, too. One day, when we stop requiring
    // course:managefiles, we will need to extend this.
    if (!has_capability('mod/cybrary:viewdiscussion', $context)) {
        return null;
    }

    if (is_null($itemid)) {
        require_once($CFG->dirroot.'/mod/cybrary/locallib.php');
        return new cybrary_file_info_container($browser, $course, $cm, $context, $areas, $filearea);
    }

    static $cached = array();
    // $cached will store last retrieved post, discussion and cybrary. To make sure that the cache
    // is cleared between unit tests we check if this is the same session
    if (!isset($cached['sesskey']) || $cached['sesskey'] != sesskey()) {
        $cached = array('sesskey' => sesskey());
    }

    if (isset($cached['post']) && $cached['post']->id == $itemid) {
        $post = $cached['post'];
    } else if ($post = $DB->get_record('cybrary_posts', array('id' => $itemid))) {
        $cached['post'] = $post;
    } else {
        return null;
    }

    if (isset($cached['discussion']) && $cached['discussion']->id == $post->discussion) {
        $discussion = $cached['discussion'];
    } else if ($discussion = $DB->get_record('cybrary_discussions', array('id' => $post->discussion))) {
        $cached['discussion'] = $discussion;
    } else {
        return null;
    }

    if (isset($cached['cybrary']) && $cached['cybrary']->id == $cm->instance) {
        $cybrary = $cached['cybrary'];
    } else if ($cybrary = $DB->get_record('cybrary', array('id' => $cm->instance))) {
        $cached['cybrary'] = $cybrary;
    } else {
        return null;
    }

    $fs = get_file_storage();
    $filepath = is_null($filepath) ? '/' : $filepath;
    $filename = is_null($filename) ? '.' : $filename;
    if (!($storedfile = $fs->get_file($context->id, 'mod_cybrary', $filearea, $itemid, $filepath, $filename))) {
        return null;
    }

    // Checks to see if the user can manage files or is the owner.
    // TODO MDL-33805 - Do not use userid here and move the capability check above.
    if (!has_capability('moodle/course:managefiles', $context) && $storedfile->get_userid() != $USER->id) {
        return null;
    }
    // Make sure groups allow this user to see this file
    if ($discussion->groupid > 0 && !has_capability('moodle/site:accessallgroups', $context)) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode == SEPARATEGROUPS && !groups_is_member($discussion->groupid)) {
            return null;
        }
    }

    // Make sure we're allowed to see it...
    if (!cybrary_user_can_see_post($cybrary, $discussion, $post, NULL, $cm)) {
        return null;
    }

    $urlbase = $CFG->wwwroot.'/pluginfile.php';
    return new file_info_stored($browser, $context, $storedfile, $urlbase, $itemid, true, true, false, false);
}

/**
 * Serves the cybrary attachments. Implements needed access control ;-)
 *
 * @package  mod_cybrary
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found - justsend the file
 */
function cybrary_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG, $DB;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    $areas = cybrary_get_file_areas($course, $cm, $context);

    // filearea must contain a real area
    if (!isset($areas[$filearea])) {
        return false;
    }

    $postid = (int)array_shift($args);

    if (!$post = $DB->get_record('cybrary_posts', array('id'=>$postid))) {
        return false;
    }

    if (!$discussion = $DB->get_record('cybrary_discussions', array('id'=>$post->discussion))) {
        return false;
    }

    if (!$cybrary = $DB->get_record('cybrary', array('id'=>$cm->instance))) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/mod_cybrary/$filearea/$postid/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        return false;
    }

    // Make sure groups allow this user to see this file
    if ($discussion->groupid > 0) {
        $groupmode = groups_get_activity_groupmode($cm, $course);
        if ($groupmode == SEPARATEGROUPS) {
            if (!groups_is_member($discussion->groupid) and !has_capability('moodle/site:accessallgroups', $context)) {
                return false;
            }
        }
    }

    // Make sure we're allowed to see it...
    if (!cybrary_user_can_see_post($cybrary, $discussion, $post, NULL, $cm)) {
        return false;
    }

    // finally send the file
    send_stored_file($file, 0, 0, true, $options); // download MUST be forced - security!
}

/**
 * If successful, this function returns the name of the file
 *
 * @global object
 * @param object $post is a full post record, including course and cybrary
 * @param object $cybrary
 * @param object $cm
 * @param mixed $mform
 * @param string $unused
 * @return bool
 */
function cybrary_add_attachment($post, $cybrary, $cm, $mform=null, $unused=null) {
    global $DB;

    if (empty($mform)) {
        return false;
    }

    if (empty($post->attachments)) {
        return true;   // Nothing to do
    }

    $context = context_module::instance($cm->id);

    $info = file_get_draft_area_info($post->attachments);
    $present = ($info['filecount']>0) ? '1' : '';
    file_save_draft_area_files($post->attachments, $context->id, 'mod_cybrary', 'attachment', $post->id,
            mod_cybrary_post_form::attachment_options($cybrary));

    $DB->set_field('cybrary_posts', 'attachment', $present, array('id'=>$post->id));

    return true;
}

/**
 * Add a new post in an existing discussion.
 *
 * @global object
 * @global object
 * @global object
 * @param object $post
 * @param mixed $mform
 * @param string $unused formerly $message, renamed in 2.8 as it was unused.
 * @return int
 */
function cybrary_add_new_post($post, $mform, $unused = null) {
    global $USER, $CFG, $DB;

    $discussion = $DB->get_record('cybrary_discussions', array('id' => $post->discussion));
    $cybrary      = $DB->get_record('cybrary', array('id' => $discussion->cybrary));
    $cm         = get_coursemodule_from_instance('cybrary', $cybrary->id);
    $context    = context_module::instance($cm->id);

    $post->created    = $post->modified = time();
    $post->mailed     = CYBRARY_MAILED_PENDING;
    $post->userid     = $USER->id;
    $post->attachment = "";
    if (!isset($post->totalscore)) {
        $post->totalscore = 0;
    }
    if (!isset($post->mailnow)) {
        $post->mailnow    = 0;
    }

    $post->id = $DB->insert_record("cybrary_posts", $post);
    $post->message = file_save_draft_area_files($post->itemid, $context->id, 'mod_cybrary', 'post', $post->id,
            mod_cybrary_post_form::editor_options($context, null), $post->message);
    $DB->set_field('cybrary_posts', 'message', $post->message, array('id'=>$post->id));
    cybrary_add_attachment($post, $cybrary, $cm, $mform);

    // Update discussion modified date
    $DB->set_field("cybrary_discussions", "timemodified", $post->modified, array("id" => $post->discussion));
    $DB->set_field("cybrary_discussions", "usermodified", $post->userid, array("id" => $post->discussion));

    if (cybrary_tp_can_track_cybraries($cybrary) && cybrary_tp_is_tracked($cybrary)) {
        cybrary_tp_mark_post_read($post->userid, $post, $post->cybrary);
    }

    // Let Moodle know that assessable content is uploaded (eg for plagiarism detection)
    cybrary_trigger_content_uploaded_event($post, $cm, 'cybrary_add_new_post');

    return $post->id;
}

/**
 * Update a post
 *
 * @global object
 * @global object
 * @global object
 * @param object $post
 * @param mixed $mform
 * @param string $message
 * @return bool
 */
function cybrary_update_post($post, $mform, &$message) {
    global $USER, $CFG, $DB;

    $discussion = $DB->get_record('cybrary_discussions', array('id' => $post->discussion));
    $cybrary      = $DB->get_record('cybrary', array('id' => $discussion->cybrary));
    $cm         = get_coursemodule_from_instance('cybrary', $cybrary->id);
    $context    = context_module::instance($cm->id);

    $post->modified = time();

    $DB->update_record('cybrary_posts', $post);

    $discussion->timemodified = $post->modified; // last modified tracking
    $discussion->usermodified = $post->userid;   // last modified tracking

    if (!$post->parent) {   // Post is a discussion starter - update discussion title and times too
        $discussion->name      = $post->subject;
        $discussion->timestart = $post->timestart;
        $discussion->timeend   = $post->timeend;
    }
    $post->message = file_save_draft_area_files($post->itemid, $context->id, 'mod_cybrary', 'post', $post->id,
            mod_cybrary_post_form::editor_options($context, $post->id), $post->message);
    $DB->set_field('cybrary_posts', 'message', $post->message, array('id'=>$post->id));

    $DB->update_record('cybrary_discussions', $discussion);

    cybrary_add_attachment($post, $cybrary, $cm, $mform, $message);

    if (cybrary_tp_can_track_cybraries($cybrary) && cybrary_tp_is_tracked($cybrary)) {
        cybrary_tp_mark_post_read($post->userid, $post, $post->cybrary);
    }

    // Let Moodle know that assessable content is uploaded (eg for plagiarism detection)
    cybrary_trigger_content_uploaded_event($post, $cm, 'cybrary_update_post');

    return true;
}

/**
 * Given an object containing all the necessary data,
 * create a new discussion and return the id
 *
 * @param object $post
 * @param mixed $mform
 * @param string $unused
 * @param int $userid
 * @return object
 */
function cybrary_add_discussion($discussion, $mform=null, $unused=null, $userid=null) {
    global $USER, $CFG, $DB;

    $timenow = time();

    if (is_null($userid)) {
        $userid = $USER->id;
    }

    // The first post is stored as a real post, and linked
    // to from the discuss entry.

    $cybrary = $DB->get_record('cybrary', array('id'=>$discussion->cybrary));
    $cm    = get_coursemodule_from_instance('cybrary', $cybrary->id);

    $post = new stdClass();
    $post->discussion    = 0;
    $post->parent        = 0;
    $post->userid        = $userid;
    $post->created       = $timenow;
    $post->modified      = $timenow;
    $post->mailed        = CYBRARY_MAILED_PENDING;
    $post->subject       = $discussion->name;
    $post->message       = $discussion->message;
    $post->messageformat = $discussion->messageformat;
    $post->messagetrust  = $discussion->messagetrust;
    $post->attachments   = isset($discussion->attachments) ? $discussion->attachments : null;
    $post->cybrary         = $cybrary->id;     // speedup
    $post->course        = $cybrary->course; // speedup
    $post->mailnow       = $discussion->mailnow;

    $post->id = $DB->insert_record("cybrary_posts", $post);

    // TODO: Fix the calling code so that there always is a $cm when this function is called
    if (!empty($cm->id) && !empty($discussion->itemid)) {   // In "single simple discussions" this may not exist yet
        $context = context_module::instance($cm->id);
        $text = file_save_draft_area_files($discussion->itemid, $context->id, 'mod_cybrary', 'post', $post->id,
                mod_cybrary_post_form::editor_options($context, null), $post->message);
        $DB->set_field('cybrary_posts', 'message', $text, array('id'=>$post->id));
    }

    // Now do the main entry for the discussion, linking to this first post

    $discussion->firstpost    = $post->id;
    $discussion->timemodified = $timenow;
    $discussion->usermodified = $post->userid;
    $discussion->userid       = $userid;
    $discussion->assessed     = 0;

    $post->discussion = $DB->insert_record("cybrary_discussions", $discussion);

    // Finally, set the pointer on the post.
    $DB->set_field("cybrary_posts", "discussion", $post->discussion, array("id"=>$post->id));

    if (!empty($cm->id)) {
        cybrary_add_attachment($post, $cybrary, $cm, $mform, $unused);
    }

    if (cybrary_tp_can_track_cybraries($cybrary) && cybrary_tp_is_tracked($cybrary)) {
        cybrary_tp_mark_post_read($post->userid, $post, $post->cybrary);
    }

    // Let Moodle know that assessable content is uploaded (eg for plagiarism detection)
    if (!empty($cm->id)) {
        cybrary_trigger_content_uploaded_event($post, $cm, 'cybrary_add_discussion');
    }

    return $post->discussion;
}


/**
 * Deletes a discussion and handles all associated cleanup.
 *
 * @global object
 * @param object $discussion Discussion to delete
 * @param bool $fulldelete True when deleting entire cybrary
 * @param object $course Course
 * @param object $cm Course-module
 * @param object $cybrary Cybrary
 * @return bool
 */
function cybrary_delete_discussion($discussion, $fulldelete, $course, $cm, $cybrary) {
    global $DB, $CFG;
    require_once($CFG->libdir.'/completionlib.php');

    $result = true;

    if ($posts = $DB->get_records("cybrary_posts", array("discussion" => $discussion->id))) {
        foreach ($posts as $post) {
            $post->course = $discussion->course;
            $post->cybrary  = $discussion->cybrary;
            if (!cybrary_delete_post($post, 'ignore', $course, $cm, $cybrary, $fulldelete)) {
                $result = false;
            }
        }
    }

    cybrary_tp_delete_read_records(-1, -1, $discussion->id);

    // Discussion subscriptions must be removed before discussions because of key constraints.
    $DB->delete_records('cybrary_discussion_subs', array('discussion' => $discussion->id));
    if (!$DB->delete_records("cybrary_discussions", array("id" => $discussion->id))) {
        $result = false;
    }

    // Update completion state if we are tracking completion based on number of posts
    // But don't bother when deleting whole thing
    if (!$fulldelete) {
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC &&
           ($cybrary->completiondiscussions || $cybrary->completionreplies || $cybrary->completionposts)) {
            $completion->update_state($cm, COMPLETION_INCOMPLETE, $discussion->userid);
        }
    }

    return $result;
}


/**
 * Deletes a single cybrary post.
 *
 * @global object
 * @param object $post Cybrary post object
 * @param mixed $children Whether to delete children. If false, returns false
 *   if there are any children (without deleting the post). If true,
 *   recursively deletes all children. If set to special value 'ignore', deletes
 *   post regardless of children (this is for use only when deleting all posts
 *   in a disussion).
 * @param object $course Course
 * @param object $cm Course-module
 * @param object $cybrary Cybrary
 * @param bool $skipcompletion True to skip updating completion state if it
 *   would otherwise be updated, i.e. when deleting entire cybrary anyway.
 * @return bool
 */
function cybrary_delete_post($post, $children, $course, $cm, $cybrary, $skipcompletion=false) {
    global $DB, $CFG, $USER;
    require_once($CFG->libdir.'/completionlib.php');

    $context = context_module::instance($cm->id);

    if ($children !== 'ignore' && ($childposts = $DB->get_records('cybrary_posts', array('parent'=>$post->id)))) {
       if ($children) {
           foreach ($childposts as $childpost) {
               cybrary_delete_post($childpost, true, $course, $cm, $cybrary, $skipcompletion);
           }
       } else {
           return false;
       }
    }

    // Delete ratings.
    require_once($CFG->dirroot.'/rating/lib.php');
    $delopt = new stdClass;
    $delopt->contextid = $context->id;
    $delopt->component = 'mod_cybrary';
    $delopt->ratingarea = 'post';
    $delopt->itemid = $post->id;
    $rm = new rating_manager();
    $rm->delete_ratings($delopt);

    // Delete attachments.
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_cybrary', 'attachment', $post->id);
    $fs->delete_area_files($context->id, 'mod_cybrary', 'post', $post->id);

    // Delete cached RSS feeds.
    if (!empty($CFG->enablerssfeeds)) {
        require_once($CFG->dirroot.'/mod/cybrary/rsslib.php');
        cybrary_rss_delete_file($cybrary);
    }

    if ($DB->delete_records("cybrary_posts", array("id" => $post->id))) {

        cybrary_tp_delete_read_records(-1, $post->id);

    // Just in case we are deleting the last post
        cybrary_discussion_update_last_post($post->discussion);

        // Update completion state if we are tracking completion based on number of posts
        // But don't bother when deleting whole thing

        if (!$skipcompletion) {
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) == COMPLETION_TRACKING_AUTOMATIC &&
               ($cybrary->completiondiscussions || $cybrary->completionreplies || $cybrary->completionposts)) {
                $completion->update_state($cm, COMPLETION_INCOMPLETE, $post->userid);
            }
        }

        $params = array(
            'context' => $context,
            'objectid' => $post->id,
            'other' => array(
                'discussionid' => $post->discussion,
                'cybraryid' => $cybrary->id,
                'cybrarytype' => $cybrary->type,
            )
        );
        if ($post->userid !== $USER->id) {
            $params['relateduserid'] = $post->userid;
        }
        $event = \mod_cybrary\event\post_deleted::create($params);
        $event->add_record_snapshot('cybrary_posts', $post);
        $event->trigger();

        return true;
    }
    return false;
}

/**
 * Sends post content to plagiarism plugin
 * @param object $post Cybrary post object
 * @param object $cm Course-module
 * @param string $name
 * @return bool
*/
function cybrary_trigger_content_uploaded_event($post, $cm, $name) {
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_cybrary', 'attachment', $post->id, "timemodified", false);
    $params = array(
        'context' => $context,
        'objectid' => $post->id,
        'other' => array(
            'content' => $post->message,
            'pathnamehashes' => array_keys($files),
            'discussionid' => $post->discussion,
            'triggeredfrom' => $name,
        )
    );
    $event = \mod_cybrary\event\assessable_uploaded::create($params);
    $event->trigger();
    return true;
}

/**
 * @global object
 * @param object $post
 * @param bool $children
 * @return int
 */
function cybrary_count_replies($post, $children=true) {
    global $DB;
    $count = 0;

    if ($children) {
        if ($childposts = $DB->get_records('cybrary_posts', array('parent' => $post->id))) {
           foreach ($childposts as $childpost) {
               $count ++;                   // For this child
               $count += cybrary_count_replies($childpost, true);
           }
        }
    } else {
        $count += $DB->count_records('cybrary_posts', array('parent' => $post->id));
    }

    return $count;
}

/**
 * Given a new post, subscribes or unsubscribes as appropriate.
 * Returns some text which describes what happened.
 *
 * @param object $fromform The submitted form
 * @param stdClass $cybrary The cybrary record
 * @param stdClass $discussion The cybrary discussion record
 * @return string
 */
function cybrary_post_subscription($fromform, $cybrary, $discussion) {
    global $USER;

    if (\mod_cybrary\subscriptions::is_forcesubscribed($cybrary)) {
        return "";
    } else if (\mod_cybrary\subscriptions::subscription_disabled($cybrary)) {
        $subscribed = \mod_cybrary\subscriptions::is_subscribed($USER->id, $cybrary);
        if ($subscribed && !has_capability('moodle/course:manageactivities', context_course::instance($cybrary->course), $USER->id)) {
            // This user should not be subscribed to the cybrary.
            \mod_cybrary\subscriptions::unsubscribe_user($USER->id, $cybrary);
        }
        return "";
    }

    $info = new stdClass();
    $info->name  = fullname($USER);
    $info->discussion = format_string($discussion->name);
    $info->cybrary = format_string($cybrary->name);

    if (isset($fromform->discussionsubscribe) && $fromform->discussionsubscribe) {
        if ($result = \mod_cybrary\subscriptions::subscribe_user_to_discussion($USER->id, $discussion)) {
            return html_writer::tag('p', get_string('discussionnowsubscribed', 'cybrary', $info));
        }
    } else {
        if ($result = \mod_cybrary\subscriptions::unsubscribe_user_from_discussion($USER->id, $discussion)) {
            return html_writer::tag('p', get_string('discussionnownotsubscribed', 'cybrary', $info));
        }
    }

    return '';
}

/**
 * Generate and return the subscribe or unsubscribe link for a cybrary.
 *
 * @param object $cybrary the cybrary. Fields used are $cybrary->id and $cybrary->forcesubscribe.
 * @param object $context the context object for this cybrary.
 * @param array $messages text used for the link in its various states
 *      (subscribed, unsubscribed, forcesubscribed or cantsubscribe).
 *      Any strings not passed in are taken from the $defaultmessages array
 *      at the top of the function.
 * @param bool $cantaccessagroup
 * @param bool $fakelink
 * @param bool $backtoindex
 * @param array $subscribed_cybraries
 * @return string
 */
function cybrary_get_subscribe_link($cybrary, $context, $messages = array(), $cantaccessagroup = false, $fakelink=true, $backtoindex=false, $subscribed_cybraries=null) {
    global $CFG, $USER, $PAGE, $OUTPUT;
    $defaultmessages = array(
        'subscribed' => get_string('unsubscribe', 'cybrary'),
        'unsubscribed' => get_string('subscribe', 'cybrary'),
        'cantaccessgroup' => get_string('no'),
        'forcesubscribed' => get_string('everyoneissubscribed', 'cybrary'),
        'cantsubscribe' => get_string('disallowsubscribe','cybrary')
    );
    $messages = $messages + $defaultmessages;

    if (\mod_cybrary\subscriptions::is_forcesubscribed($cybrary)) {
        return $messages['forcesubscribed'];
    } else if (\mod_cybrary\subscriptions::subscription_disabled($cybrary) &&
            !has_capability('mod/cybrary:managesubscriptions', $context)) {
        return $messages['cantsubscribe'];
    } else if ($cantaccessagroup) {
        return $messages['cantaccessgroup'];
    } else {
        if (!is_enrolled($context, $USER, '', true)) {
            return '';
        }

        $subscribed = \mod_cybrary\subscriptions::is_subscribed($USER->id, $cybrary);
        if ($subscribed) {
            $linktext = $messages['subscribed'];
            $linktitle = get_string('subscribestop', 'cybrary');
        } else {
            $linktext = $messages['unsubscribed'];
            $linktitle = get_string('subscribestart', 'cybrary');
        }

        $options = array();
        if ($backtoindex) {
            $backtoindexlink = '&amp;backtoindex=1';
            $options['backtoindex'] = 1;
        } else {
            $backtoindexlink = '';
        }
        $link = '';

        if ($fakelink) {
            $PAGE->requires->js('/mod/cybrary/cybrary.js');
            $PAGE->requires->js_function_call('cybrary_produce_subscribe_link', array($cybrary->id, $backtoindexlink, $linktext, $linktitle));
            $link = "<noscript>";
        }
        $options['id'] = $cybrary->id;
        $options['sesskey'] = sesskey();
        $url = new moodle_url('/mod/cybrary/subscribe.php', $options);
        $link .= $OUTPUT->single_button($url, $linktext, 'get', array('title'=>$linktitle));
        if ($fakelink) {
            $link .= '</noscript>';
        }

        return $link;
    }
}

/**
 * Returns true if user created new discussion already.
 *
 * @param int $cybraryid  The cybrary to check for postings
 * @param int $userid   The user to check for postings
 * @param int $groupid  The group to restrict the check to
 * @return bool
 */
function cybrary_user_has_posted_discussion($cybraryid, $userid, $groupid = null) {
    global $CFG, $DB;

    $sql = "SELECT 'x'
              FROM {cybrary_discussions} d, {cybrary_posts} p
             WHERE d.cybrary = ? AND p.discussion = d.id AND p.parent = 0 AND p.userid = ?";

    $params = [$cybraryid, $userid];

    if ($groupid) {
        $sql .= " AND d.groupid = ?";
        $params[] = $groupid;
    }

    return $DB->record_exists_sql($sql, $params);
}

/**
 * @global object
 * @global object
 * @param int $cybraryid
 * @param int $userid
 * @return array
 */
function cybrary_discussions_user_has_posted_in($cybraryid, $userid) {
    global $CFG, $DB;

    $haspostedsql = "SELECT d.id AS id,
                            d.*
                       FROM {cybrary_posts} p,
                            {cybrary_discussions} d
                      WHERE p.discussion = d.id
                        AND d.cybrary = ?
                        AND p.userid = ?";

    return $DB->get_records_sql($haspostedsql, array($cybraryid, $userid));
}

/**
 * @global object
 * @global object
 * @param int $cybraryid
 * @param int $did
 * @param int $userid
 * @return bool
 */
function cybrary_user_has_posted($cybraryid, $did, $userid) {
    global $DB;

    if (empty($did)) {
        // posted in any cybrary discussion?
        $sql = "SELECT 'x'
                  FROM {cybrary_posts} p
                  JOIN {cybrary_discussions} d ON d.id = p.discussion
                 WHERE p.userid = :userid AND d.cybrary = :cybraryid";
        return $DB->record_exists_sql($sql, array('cybraryid'=>$cybraryid,'userid'=>$userid));
    } else {
        return $DB->record_exists('cybrary_posts', array('discussion'=>$did,'userid'=>$userid));
    }
}

/**
 * Returns creation time of the first user's post in given discussion
 * @global object $DB
 * @param int $did Discussion id
 * @param int $userid User id
 * @return int|bool post creation time stamp or return false
 */
function cybrary_get_user_posted_time($did, $userid) {
    global $DB;

    $posttime = $DB->get_field('cybrary_posts', 'MIN(created)', array('userid'=>$userid, 'discussion'=>$did));
    if (empty($posttime)) {
        return false;
    }
    return $posttime;
}

/**
 * @global object
 * @param object $cybrary
 * @param object $currentgroup
 * @param int $unused
 * @param object $cm
 * @param object $context
 * @return bool
 */
function cybrary_user_can_post_discussion($cybrary, $currentgroup=null, $unused=-1, $cm=NULL, $context=NULL) {
// $cybrary is an object
    global $USER;

    // shortcut - guest and not-logged-in users can not post
    if (isguestuser() or !isloggedin()) {
        return false;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('cybrary', $cybrary->id, $cybrary->course)) {
            print_error('invalidcoursemodule');
        }
    }

    if (!$context) {
        $context = context_module::instance($cm->id);
    }

    if ($currentgroup === null) {
        $currentgroup = groups_get_activity_group($cm);
    }

    $groupmode = groups_get_activity_groupmode($cm);

    if ($cybrary->type == 'news') {
        $capname = 'mod/cybrary:addnews';
    } else if ($cybrary->type == 'qanda') {
        $capname = 'mod/cybrary:addquestion';
    } else {
        $capname = 'mod/cybrary:startdiscussion';
    }

    if (!has_capability($capname, $context)) {
        return false;
    }

    if ($cybrary->type == 'single') {
        return false;
    }

    if ($cybrary->type == 'eachuser') {
        if (cybrary_user_has_posted_discussion($cybrary->id, $USER->id, $currentgroup)) {
            return false;
        }
    }

    if (!$groupmode or has_capability('moodle/site:accessallgroups', $context)) {
        return true;
    }

    if ($currentgroup) {
        return groups_is_member($currentgroup);
    } else {
        // no group membership and no accessallgroups means no new discussions
        // reverted to 1.7 behaviour in 1.9+,  buggy in 1.8.0-1.9.0
        return false;
    }
}

/**
 * This function checks whether the user can reply to posts in a cybrary
 * discussion. Use cybrary_user_can_post_discussion() to check whether the user
 * can start discussions.
 *
 * @global object
 * @global object
 * @uses DEBUG_DEVELOPER
 * @uses CONTEXT_MODULE
 * @uses VISIBLEGROUPS
 * @param object $cybrary cybrary object
 * @param object $discussion
 * @param object $user
 * @param object $cm
 * @param object $course
 * @param object $context
 * @return bool
 */
function cybrary_user_can_post($cybrary, $discussion, $user=NULL, $cm=NULL, $course=NULL, $context=NULL) {
    global $USER, $DB;
    if (empty($user)) {
        $user = $USER;
    }

    // shortcut - guest and not-logged-in users can not post
    if (isguestuser($user) or empty($user->id)) {
        return false;
    }

    if (!isset($discussion->groupid)) {
        debugging('incorrect discussion parameter', DEBUG_DEVELOPER);
        return false;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('cybrary', $cybrary->id, $cybrary->course)) {
            print_error('invalidcoursemodule');
        }
    }

    if (!$course) {
        debugging('missing course', DEBUG_DEVELOPER);
        if (!$course = $DB->get_record('course', array('id' => $cybrary->course))) {
            print_error('invalidcourseid');
        }
    }

    if (!$context) {
        $context = context_module::instance($cm->id);
    }

    // normal users with temporary guest access can not post, suspended users can not post either
    if (!is_viewing($context, $user->id) and !is_enrolled($context, $user->id, '', true)) {
        return false;
    }

    if ($cybrary->type == 'news') {
        $capname = 'mod/cybrary:replynews';
    } else {
        $capname = 'mod/cybrary:replypost';
    }

    if (!has_capability($capname, $context, $user->id)) {
        return false;
    }

    if (!$groupmode = groups_get_activity_groupmode($cm, $course)) {
        return true;
    }

    if (has_capability('moodle/site:accessallgroups', $context)) {
        return true;
    }

    if ($groupmode == VISIBLEGROUPS) {
        if ($discussion->groupid == -1) {
            // allow students to reply to all participants discussions - this was not possible in Moodle <1.8
            return true;
        }
        return groups_is_member($discussion->groupid);

    } else {
        //separate groups
        if ($discussion->groupid == -1) {
            return false;
        }
        return groups_is_member($discussion->groupid);
    }
}

/**
* Check to ensure a user can view a timed discussion.
*
* @param object $discussion
* @param object $user
* @param object $context
* @return boolean returns true if they can view post, false otherwise
*/
function cybrary_user_can_see_timed_discussion($discussion, $user, $context) {
    global $CFG;

    // Check that the user can view a discussion that is normally hidden due to access times.
    if (!empty($CFG->cybrary_enabletimedposts)) {
        $time = time();
        if (($discussion->timestart != 0 && $discussion->timestart > $time)
            || ($discussion->timeend != 0 && $discussion->timeend < $time)) {
            if (!has_capability('mod/cybrary:viewhiddentimedposts', $context, $user->id)) {
                return false;
            }
        }
    }

    return true;
}

/**
* Check to ensure a user can view a group discussion.
*
* @param object $discussion
* @param object $cm
* @param object $context
* @return boolean returns true if they can view post, false otherwise
*/
function cybrary_user_can_see_group_discussion($discussion, $cm, $context) {

    // If it's a grouped discussion, make sure the user is a member.
    if ($discussion->groupid > 0) {
        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode == SEPARATEGROUPS) {
            return groups_is_member($discussion->groupid) || has_capability('moodle/site:accessallgroups', $context);
        }
    }

    return true;
}

/**
 * @global object
 * @global object
 * @uses DEBUG_DEVELOPER
 * @param object $cybrary
 * @param object $discussion
 * @param object $context
 * @param object $user
 * @return bool
 */
function cybrary_user_can_see_discussion($cybrary, $discussion, $context, $user=NULL) {
    global $USER, $DB;

    if (empty($user) || empty($user->id)) {
        $user = $USER;
    }

    // retrieve objects (yuk)
    if (is_numeric($cybrary)) {
        debugging('missing full cybrary', DEBUG_DEVELOPER);
        if (!$cybrary = $DB->get_record('cybrary',array('id'=>$cybrary))) {
            return false;
        }
    }
    if (is_numeric($discussion)) {
        debugging('missing full discussion', DEBUG_DEVELOPER);
        if (!$discussion = $DB->get_record('cybrary_discussions',array('id'=>$discussion))) {
            return false;
        }
    }
    if (!$cm = get_coursemodule_from_instance('cybrary', $cybrary->id, $cybrary->course)) {
        print_error('invalidcoursemodule');
    }

    if (!has_capability('mod/cybrary:viewdiscussion', $context)) {
        return false;
    }

    if (!cybrary_user_can_see_timed_discussion($discussion, $user, $context)) {
        return false;
    }

    if (!cybrary_user_can_see_group_discussion($discussion, $cm, $context)) {
        return false;
    }

    return true;
}

/**
 * @global object
 * @global object
 * @param object $cybrary
 * @param object $discussion
 * @param object $post
 * @param object $user
 * @param object $cm
 * @return bool
 */
function cybrary_user_can_see_post($cybrary, $discussion, $post, $user=NULL, $cm=NULL) {
    global $CFG, $USER, $DB;

    // Context used throughout function.
    $modcontext = context_module::instance($cm->id);

    // retrieve objects (yuk)
    if (is_numeric($cybrary)) {
        debugging('missing full cybrary', DEBUG_DEVELOPER);
        if (!$cybrary = $DB->get_record('cybrary',array('id'=>$cybrary))) {
            return false;
        }
    }

    if (is_numeric($discussion)) {
        debugging('missing full discussion', DEBUG_DEVELOPER);
        if (!$discussion = $DB->get_record('cybrary_discussions',array('id'=>$discussion))) {
            return false;
        }
    }
    if (is_numeric($post)) {
        debugging('missing full post', DEBUG_DEVELOPER);
        if (!$post = $DB->get_record('cybrary_posts',array('id'=>$post))) {
            return false;
        }
    }

    if (!isset($post->id) && isset($post->parent)) {
        $post->id = $post->parent;
    }

    if (!$cm) {
        debugging('missing cm', DEBUG_DEVELOPER);
        if (!$cm = get_coursemodule_from_instance('cybrary', $cybrary->id, $cybrary->course)) {
            print_error('invalidcoursemodule');
        }
    }

    if (empty($user) || empty($user->id)) {
        $user = $USER;
    }

    $canviewdiscussion = !empty($cm->cache->caps['mod/cybrary:viewdiscussion']) || has_capability('mod/cybrary:viewdiscussion', $modcontext, $user->id);
    if (!$canviewdiscussion && !has_all_capabilities(array('moodle/user:viewdetails', 'moodle/user:readuserposts'), context_user::instance($post->userid))) {
        return false;
    }

    if (isset($cm->uservisible)) {
        if (!$cm->uservisible) {
            return false;
        }
    } else {
        if (!\core_availability\info_module::is_user_visible($cm, $user->id, false)) {
            return false;
        }
    }

    if (!cybrary_user_can_see_timed_discussion($discussion, $user, $modcontext)) {
        return false;
    }

    if (!cybrary_user_can_see_group_discussion($discussion, $cm, $modcontext)) {
        return false;
    }

    if ($cybrary->type == 'qanda') {
        $firstpost = cybrary_get_firstpost_from_discussion($discussion->id);
        $userfirstpost = cybrary_get_user_posted_time($discussion->id, $user->id);

        return (($userfirstpost !== false && (time() - $userfirstpost >= $CFG->maxeditingtime)) ||
                $firstpost->id == $post->id || $post->userid == $user->id || $firstpost->userid == $user->id ||
                has_capability('mod/cybrary:viewqandawithoutposting', $modcontext, $user->id));
    }
    return true;
}


/**
 * Prints the discussion view screen for a cybrary.
 *
 * @global object
 * @global object
 * @param object $course The current course object.
 * @param object $cybrary Cybrary to be printed.
 * @param int $maxdiscussions .
 * @param string $displayformat The display format to use (optional).
 * @param string $sort Sort arguments for database query (optional).
 * @param int $groupmode Group mode of the cybrary (optional).
 * @param void $unused (originally current group)
 * @param int $page Page mode, page to display (optional).
 * @param int $perpage The maximum number of discussions per page(optional)
 * @param boolean $subscriptionstatus Whether the user is currently subscribed to the discussion in some fashion.
 *
 */
function cybrary_print_latest_discussions($course, $cybrary, $maxdiscussions = -1, $displayformat = 'plain', $sort = '',
                                        $currentgroup = -1, $groupmode = -1, $page = -1, $perpage = 100, $cm = null) {
    global $CFG, $USER, $OUTPUT;

    if (!$cm) {
        if (!$cm = get_coursemodule_from_instance('cybrary', $cybrary->id, $cybrary->course)) {
            print_error('invalidcoursemodule');
        }
    }
    $context = context_module::instance($cm->id);

    if (empty($sort)) {
        $sort = cybrary_get_default_sort_order();
    }

    $olddiscussionlink = false;

 // Sort out some defaults
    if ($perpage <= 0) {
        $perpage = 0;
        $page    = -1;
    }

    if ($maxdiscussions == 0) {
        // all discussions - backwards compatibility
        $page    = -1;
        $perpage = 0;
        if ($displayformat == 'plain') {
            $displayformat = 'header';  // Abbreviate display by default
        }

    } else if ($maxdiscussions > 0) {
        $page    = -1;
        $perpage = $maxdiscussions;
    }

    $fullpost = false;
    if ($displayformat == 'plain') {
        $fullpost = true;
    }


// Decide if current user is allowed to see ALL the current discussions or not

// First check the group stuff
    if ($currentgroup == -1 or $groupmode == -1) {
        $groupmode    = groups_get_activity_groupmode($cm, $course);
        $currentgroup = groups_get_activity_group($cm);
    }

    $groups = array(); //cache

// If the user can post discussions, then this is a good place to put the
// button for it. We do not show the button if we are showing site news
// and the current user is a guest.

    $canstart = cybrary_user_can_post_discussion($cybrary, $currentgroup, $groupmode, $cm, $context);
    if (!$canstart and $cybrary->type !== 'news') {
        if (isguestuser() or !isloggedin()) {
            $canstart = true;
        }
        if (!is_enrolled($context) and !is_viewing($context)) {
            // allow guests and not-logged-in to see the button - they are prompted to log in after clicking the link
            // normal users with temporary guest access see this button too, they are asked to enrol instead
            // do not show the button to users with suspended enrolments here
            $canstart = enrol_selfenrol_available($course->id);
        }
    }

    if ($canstart) {
        echo '<div class="singlebutton cybraryaddnew">';
        echo "<form id=\"newdiscussionform\" method=\"get\" action=\"$CFG->wwwroot/mod/cybrary/post.php\">";
        echo '<div>';
        echo "<input type=\"hidden\" name=\"cybrary\" value=\"$cybrary->id\" />";
        switch ($cybrary->type) {
            case 'news':
            case 'blog':
                $buttonadd = get_string('addanewtopic', 'cybrary');
                break;
            case 'qanda':
                $buttonadd = get_string('addanewquestion', 'cybrary');
                break;
            default:
                $buttonadd = get_string('addanewdiscussion', 'cybrary');
                break;
        }
        echo '<input type="submit" value="'.$buttonadd.'" />';
        echo '</div>';
        echo '</form>';
        echo "</div>\n";

    } else if (isguestuser() or !isloggedin() or $cybrary->type == 'news' or
        $cybrary->type == 'qanda' and !has_capability('mod/cybrary:addquestion', $context) or
        $cybrary->type != 'qanda' and !has_capability('mod/cybrary:startdiscussion', $context)) {
        // no button and no info

    } else if ($groupmode and !has_capability('moodle/site:accessallgroups', $context)) {
        // inform users why they can not post new discussion
        if (!$currentgroup) {
            echo $OUTPUT->notification(get_string('cannotadddiscussionall', 'cybrary'));
        } else if (!groups_is_member($currentgroup)) {
            echo $OUTPUT->notification(get_string('cannotadddiscussion', 'cybrary'));
        }
    }

// Get all the recent discussions we're allowed to see

    $getuserlastmodified = ($displayformat == 'header');

    if (! $discussions = cybrary_get_discussions($cm, $sort, $fullpost, null, $maxdiscussions, $getuserlastmodified, $page, $perpage) ) {
        echo '<div class="cybrarynodiscuss">';
        if ($cybrary->type == 'news') {
            echo '('.get_string('nonews', 'cybrary').')';
        } else if ($cybrary->type == 'qanda') {
            echo '('.get_string('noquestions','cybrary').')';
        } else {
            echo '('.get_string('nodiscussions', 'cybrary').')';
        }
        echo "</div>\n";
        return;
    }

// If we want paging
    if ($page != -1) {
        ///Get the number of discussions found
        $numdiscussions = cybrary_get_discussions_count($cm);

        ///Show the paging bar
        echo $OUTPUT->paging_bar($numdiscussions, $page, $perpage, "view.php?f=$cybrary->id");
        if ($numdiscussions > 1000) {
            // saves some memory on sites with very large cybraries
            $replies = cybrary_count_discussion_replies($cybrary->id, $sort, $maxdiscussions, $page, $perpage);
        } else {
            $replies = cybrary_count_discussion_replies($cybrary->id);
        }

    } else {
        $replies = cybrary_count_discussion_replies($cybrary->id);

        if ($maxdiscussions > 0 and $maxdiscussions <= count($discussions)) {
            $olddiscussionlink = true;
        }
    }

    $canviewparticipants = has_capability('moodle/course:viewparticipants',$context);
    $canviewhiddentimedposts = has_capability('mod/cybrary:viewhiddentimedposts', $context);

    $strdatestring = get_string('strftimerecentfull');

    // Check if the cybrary is tracked.
    if ($cantrack = cybrary_tp_can_track_cybraries($cybrary)) {
        $cybrarytracked = cybrary_tp_is_tracked($cybrary);
    } else {
        $cybrarytracked = false;
    }

    if ($cybrarytracked) {
        $unreads = cybrary_get_discussions_unread($cm);
    } else {
        $unreads = array();
    }

    if ($displayformat == 'header') {
        echo '<table cellspacing="0" class="cybraryheaderlist">';
        echo '<thead>';
        echo '<tr>';
        echo '<th class="header topic" scope="col">'.get_string('discussion', 'cybrary').'</th>';
        echo '<th class="header author" colspan="2" scope="col">'.get_string('startedby', 'cybrary').'</th>';
        if ($groupmode > 0) {
            echo '<th class="header group" scope="col">'.get_string('group').'</th>';
        }
        if (has_capability('mod/cybrary:viewdiscussion', $context)) {
            echo '<th class="header replies" scope="col">'.get_string('replies', 'cybrary').'</th>';
            // If the cybrary can be tracked, display the unread column.
            if ($cantrack) {
                echo '<th class="header replies" scope="col">'.get_string('unread', 'cybrary');
                if ($cybrarytracked) {
                    echo '<a title="'.get_string('markallread', 'cybrary').
                         '" href="'.$CFG->wwwroot.'/mod/cybrary/markposts.php?f='.
                         $cybrary->id.'&amp;mark=read&amp;returnpage=view.php">'.
                         '<img src="'.$OUTPUT->pix_url('t/markasread') . '" class="iconsmall" alt="'.get_string('markallread', 'cybrary').'" /></a>';
                }
                echo '</th>';
            }
        }
        echo '<th class="header lastpost" scope="col">'.get_string('lastpost', 'cybrary').'</th>';
        if ((!is_guest($context, $USER) && isloggedin()) && has_capability('mod/cybrary:viewdiscussion', $context)) {
            if (\mod_cybrary\subscriptions::is_subscribable($cybrary)) {
                echo '<th class="header discussionsubscription" scope="col">';
                echo cybrary_get_discussion_subscription_icon_preloaders();
                echo '</th>';
            }
        }
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
    }

    foreach ($discussions as $discussion) {
        if ($cybrary->type == 'qanda' && !has_capability('mod/cybrary:viewqandawithoutposting', $context) &&
            !cybrary_user_has_posted($cybrary->id, $discussion->discussion, $USER->id)) {
            $canviewparticipants = false;
        }

        if (!empty($replies[$discussion->discussion])) {
            $discussion->replies = $replies[$discussion->discussion]->replies;
            $discussion->lastpostid = $replies[$discussion->discussion]->lastpostid;
        } else {
            $discussion->replies = 0;
        }

        // SPECIAL CASE: The front page can display a news item post to non-logged in users.
        // All posts are read in this case.
        if (!$cybrarytracked) {
            $discussion->unread = '-';
        } else if (empty($USER)) {
            $discussion->unread = 0;
        } else {
            if (empty($unreads[$discussion->discussion])) {
                $discussion->unread = 0;
            } else {
                $discussion->unread = $unreads[$discussion->discussion];
            }
        }

        if (isloggedin()) {
            $ownpost = ($discussion->userid == $USER->id);
        } else {
            $ownpost=false;
        }
        // Use discussion name instead of subject of first post
        $discussion->subject = $discussion->name;

        switch ($displayformat) {
            case 'header':
                if ($groupmode > 0) {
                    if (isset($groups[$discussion->groupid])) {
                        $group = $groups[$discussion->groupid];
                    } else {
                        $group = $groups[$discussion->groupid] = groups_get_group($discussion->groupid);
                    }
                } else {
                    $group = -1;
                }
                cybrary_print_discussion_header($discussion, $cybrary, $group, $strdatestring, $cantrack, $cybrarytracked,
                    $canviewparticipants, $context, $canviewhiddentimedposts);
            break;
            default:
                $link = false;

                if ($discussion->replies) {
                    $link = true;
                } else {
                    $modcontext = context_module::instance($cm->id);
                    $link = cybrary_user_can_see_discussion($cybrary, $discussion, $modcontext, $USER);
                }

                $discussion->cybrary = $cybrary->id;

                cybrary_print_post($discussion, $discussion, $cybrary, $cm, $course, $ownpost, 0, $link, false,
                        '', null, true, $cybrarytracked);
            break;
        }
    }

    if ($displayformat == "header") {
        echo '</tbody>';
        echo '</table>';
    }

    if ($olddiscussionlink) {
        if ($cybrary->type == 'news') {
            $strolder = get_string('oldertopics', 'cybrary');
        } else {
            $strolder = get_string('olderdiscussions', 'cybrary');
        }
        echo '<div class="cybraryolddiscuss">';
        echo '<a href="'.$CFG->wwwroot.'/mod/cybrary/view.php?f='.$cybrary->id.'&amp;showall=1">';
        echo $strolder.'</a> ...</div>';
    }

    if ($page != -1) { ///Show the paging bar
        echo $OUTPUT->paging_bar($numdiscussions, $page, $perpage, "view.php?f=$cybrary->id");
    }
}


/**
 * Prints a cybrary discussion
 *
 * @uses CONTEXT_MODULE
 * @uses CYBRARY_MODE_FLATNEWEST
 * @uses CYBRARY_MODE_FLATOLDEST
 * @uses CYBRARY_MODE_THREADED
 * @uses CYBRARY_MODE_NESTED
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $cybrary
 * @param stdClass $discussion
 * @param stdClass $post
 * @param int $mode
 * @param mixed $canreply
 * @param bool $canrate
 */
function cybrary_print_discussion($course, $cm, $cybrary, $discussion, $post, $mode, $canreply=NULL, $canrate=false) {
    global $USER, $CFG;

    require_once($CFG->dirroot.'/rating/lib.php');

    $ownpost = (isloggedin() && $USER->id == $post->userid);

    $modcontext = context_module::instance($cm->id);
    if ($canreply === NULL) {
        $reply = cybrary_user_can_post($cybrary, $discussion, $USER, $cm, $course, $modcontext);
    } else {
        $reply = $canreply;
    }

    // $cm holds general cache for cybrary functions
    $cm->cache = new stdClass;
    $cm->cache->groups      = groups_get_all_groups($course->id, 0, $cm->groupingid);
    $cm->cache->usersgroups = array();

    $posters = array();

    // preload all posts - TODO: improve...
    if ($mode == CYBRARY_MODE_FLATNEWEST) {
        $sort = "p.created DESC";
    } else {
        $sort = "p.created ASC";
    }

    $cybrarytracked = cybrary_tp_is_tracked($cybrary);
    $posts = cybrary_get_all_discussion_posts($discussion->id, $sort, $cybrarytracked);
    $post = $posts[$post->id];

    foreach ($posts as $pid=>$p) {
        $posters[$p->userid] = $p->userid;
    }

    // preload all groups of ppl that posted in this discussion
    if ($postersgroups = groups_get_all_groups($course->id, $posters, $cm->groupingid, 'gm.id, gm.groupid, gm.userid')) {
        foreach($postersgroups as $pg) {
            if (!isset($cm->cache->usersgroups[$pg->userid])) {
                $cm->cache->usersgroups[$pg->userid] = array();
            }
            $cm->cache->usersgroups[$pg->userid][$pg->groupid] = $pg->groupid;
        }
        unset($postersgroups);
    }

    //load ratings
    if ($cybrary->assessed != RATING_AGGREGATE_NONE) {
        $ratingoptions = new stdClass;
        $ratingoptions->context = $modcontext;
        $ratingoptions->component = 'mod_cybrary';
        $ratingoptions->ratingarea = 'post';
        $ratingoptions->items = $posts;
        $ratingoptions->aggregate = $cybrary->assessed;//the aggregation method
        $ratingoptions->scaleid = $cybrary->scale;
        $ratingoptions->userid = $USER->id;
        if ($cybrary->type == 'single' or !$discussion->id) {
            $ratingoptions->returnurl = "$CFG->wwwroot/mod/cybrary/view.php?id=$cm->id";
        } else {
            $ratingoptions->returnurl = "$CFG->wwwroot/mod/cybrary/discuss.php?d=$discussion->id";
        }
        $ratingoptions->assesstimestart = $cybrary->assesstimestart;
        $ratingoptions->assesstimefinish = $cybrary->assesstimefinish;

        $rm = new rating_manager();
        $posts = $rm->get_ratings($ratingoptions);
    }


    $post->cybrary = $cybrary->id;   // Add the cybrary id to the post object, later used by cybrary_print_post
    $post->cybrarytype = $cybrary->type;

    $post->subject = format_string($post->subject);

    $postread = !empty($post->postread);

    cybrary_print_post($post, $discussion, $cybrary, $cm, $course, $ownpost, $reply, false,
                         '', '', $postread, true, $cybrarytracked);

    switch ($mode) {
        case CYBRARY_MODE_FLATOLDEST :
        case CYBRARY_MODE_FLATNEWEST :
        default:
            cybrary_print_posts_flat($course, $cm, $cybrary, $discussion, $post, $mode, $reply, $cybrarytracked, $posts);
            break;

        case CYBRARY_MODE_THREADED :
            cybrary_print_posts_threaded($course, $cm, $cybrary, $discussion, $post, 0, $reply, $cybrarytracked, $posts);
            break;

        case CYBRARY_MODE_NESTED :
            cybrary_print_posts_nested($course, $cm, $cybrary, $discussion, $post, $reply, $cybrarytracked, $posts);
            break;
    }
}


/**
 * @global object
 * @global object
 * @uses CYBRARY_MODE_FLATNEWEST
 * @param object $course
 * @param object $cm
 * @param object $cybrary
 * @param object $discussion
 * @param object $post
 * @param object $mode
 * @param bool $reply
 * @param bool $cybrarytracked
 * @param array $posts
 * @return void
 */
function cybrary_print_posts_flat($course, &$cm, $cybrary, $discussion, $post, $mode, $reply, $cybrarytracked, $posts) {
    global $USER, $CFG;

    $link  = false;

    if ($mode == CYBRARY_MODE_FLATNEWEST) {
        $sort = "ORDER BY created DESC";
    } else {
        $sort = "ORDER BY created ASC";
    }

    foreach ($posts as $post) {
        if (!$post->parent) {
            continue;
        }
        $post->subject = format_string($post->subject);
        $ownpost = ($USER->id == $post->userid);

        $postread = !empty($post->postread);

        cybrary_print_post($post, $discussion, $cybrary, $cm, $course, $ownpost, $reply, $link,
                             '', '', $postread, true, $cybrarytracked);
    }
}

/**
 * @todo Document this function
 *
 * @global object
 * @global object
 * @uses CONTEXT_MODULE
 * @return void
 */
function cybrary_print_posts_threaded($course, &$cm, $cybrary, $discussion, $parent, $depth, $reply, $cybrarytracked, $posts) {
    global $USER, $CFG;

    $link  = false;

    if (!empty($posts[$parent->id]->children)) {
        $posts = $posts[$parent->id]->children;

        $modcontext       = context_module::instance($cm->id);
        $canviewfullnames = has_capability('moodle/site:viewfullnames', $modcontext);

        foreach ($posts as $post) {

            echo '<div class="indent">';
            if ($depth > 0) {
                $ownpost = ($USER->id == $post->userid);
                $post->subject = format_string($post->subject);

                $postread = !empty($post->postread);

                cybrary_print_post($post, $discussion, $cybrary, $cm, $course, $ownpost, $reply, $link,
                                     '', '', $postread, true, $cybrarytracked);
            } else {
                if (!cybrary_user_can_see_post($cybrary, $discussion, $post, NULL, $cm)) {
                    echo "</div>\n";
                    continue;
                }
                $by = new stdClass();
                $by->name = fullname($post, $canviewfullnames);
                $by->date = userdate($post->modified);

                if ($cybrarytracked) {
                    if (!empty($post->postread)) {
                        $style = '<span class="cybrarythread read">';
                    } else {
                        $style = '<span class="cybrarythread unread">';
                    }
                } else {
                    $style = '<span class="cybrarythread">';
                }
                echo $style."<a name=\"$post->id\"></a>".
                     "<a href=\"discuss.php?d=$post->discussion&amp;parent=$post->id\">".format_string($post->subject,true)."</a> ";
                print_string("bynameondate", "cybrary", $by);
                echo "</span>";
            }

            cybrary_print_posts_threaded($course, $cm, $cybrary, $discussion, $post, $depth-1, $reply, $cybrarytracked, $posts);
            echo "</div>\n";
        }
    }
}

/**
 * @todo Document this function
 * @global object
 * @global object
 * @return void
 */
function cybrary_print_posts_nested($course, &$cm, $cybrary, $discussion, $parent, $reply, $cybrarytracked, $posts) {
    global $USER, $CFG;

    $link  = false;

    if (!empty($posts[$parent->id]->children)) {
        $posts = $posts[$parent->id]->children;

        foreach ($posts as $post) {

            echo '<div class="indent">';
            if (!isloggedin()) {
                $ownpost = false;
            } else {
                $ownpost = ($USER->id == $post->userid);
            }

            $post->subject = format_string($post->subject);
            $postread = !empty($post->postread);

            cybrary_print_post($post, $discussion, $cybrary, $cm, $course, $ownpost, $reply, $link,
                                 '', '', $postread, true, $cybrarytracked);
            cybrary_print_posts_nested($course, $cm, $cybrary, $discussion, $post, $reply, $cybrarytracked, $posts);
            echo "</div>\n";
        }
    }
}

/**
 * Returns all cybrary posts since a given time in specified cybrary.
 *
 * @todo Document this functions args
 * @global object
 * @global object
 * @global object
 * @global object
 */
function cybrary_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0)  {
    global $CFG, $COURSE, $USER, $DB;

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array('id' => $courseid));
    }

    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->cms[$cmid];
    $params = array($timestart, $cm->instance);

    if ($userid) {
        $userselect = "AND u.id = ?";
        $params[] = $userid;
    } else {
        $userselect = "";
    }

    if ($groupid) {
        $groupselect = "AND d.groupid = ?";
        $params[] = $groupid;
    } else {
        $groupselect = "";
    }

    $allnames = get_all_user_name_fields(true, 'u');
    if (!$posts = $DB->get_records_sql("SELECT p.*, f.type AS cybrarytype, d.cybrary, d.groupid,
                                              d.timestart, d.timeend, d.userid AS duserid,
                                              $allnames, u.email, u.picture, u.imagealt, u.email
                                         FROM {cybrary_posts} p
                                              JOIN {cybrary_discussions} d ON d.id = p.discussion
                                              JOIN {cybrary} f             ON f.id = d.cybrary
                                              JOIN {user} u              ON u.id = p.userid
                                        WHERE p.created > ? AND f.id = ?
                                              $userselect $groupselect
                                     ORDER BY p.id ASC", $params)) { // order by initial posting date
         return;
    }

    $groupmode       = groups_get_activity_groupmode($cm, $course);
    $cm_context      = context_module::instance($cm->id);
    $viewhiddentimed = has_capability('mod/cybrary:viewhiddentimedposts', $cm_context);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $cm_context);

    $printposts = array();
    foreach ($posts as $post) {

        if (!empty($CFG->cybrary_enabletimedposts) and $USER->id != $post->duserid
          and (($post->timestart > 0 and $post->timestart > time()) or ($post->timeend > 0 and $post->timeend < time()))) {
            if (!$viewhiddentimed) {
                continue;
            }
        }

        if ($groupmode) {
            if ($post->groupid == -1 or $groupmode == VISIBLEGROUPS or $accessallgroups) {
                // oki (Open discussions have groupid -1)
            } else {
                // separate mode
                if (isguestuser()) {
                    // shortcut
                    continue;
                }

                if (!in_array($post->groupid, $modinfo->get_groups($cm->groupingid))) {
                    continue;
                }
            }
        }

        $printposts[] = $post;
    }

    if (!$printposts) {
        return;
    }

    $aname = format_string($cm->name,true);

    foreach ($printposts as $post) {
        $tmpactivity = new stdClass();

        $tmpactivity->type         = 'cybrary';
        $tmpactivity->cmid         = $cm->id;
        $tmpactivity->name         = $aname;
        $tmpactivity->sectionnum   = $cm->sectionnum;
        $tmpactivity->timestamp    = $post->modified;

        $tmpactivity->content = new stdClass();
        $tmpactivity->content->id         = $post->id;
        $tmpactivity->content->discussion = $post->discussion;
        $tmpactivity->content->subject    = format_string($post->subject);
        $tmpactivity->content->parent     = $post->parent;

        $tmpactivity->user = new stdClass();
        $additionalfields = array('id' => 'userid', 'picture', 'imagealt', 'email');
        $additionalfields = explode(',', user_picture::fields());
        $tmpactivity->user = username_load_fields_from_object($tmpactivity->user, $post, null, $additionalfields);
        $tmpactivity->user->id = $post->userid;

        $activities[$index++] = $tmpactivity;
    }

    return;
}

/**
 * @todo Document this function
 * @global object
 */
function cybrary_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
    global $CFG, $OUTPUT;

    if ($activity->content->parent) {
        $class = 'reply';
    } else {
        $class = 'discussion';
    }

    echo '<table border="0" cellpadding="3" cellspacing="0" class="cybrary-recent">';

    echo "<tr><td class=\"userpicture\" valign=\"top\">";
    echo $OUTPUT->user_picture($activity->user, array('courseid'=>$courseid));
    echo "</td><td class=\"$class\">";

    if ($activity->content->parent) {
        $class = 'title';
    } else {
        // Bold the title of new discussions so they stand out.
        $class = 'title bold';
    }
    echo "<div class=\"{$class}\">";
    if ($detail) {
        $aname = s($activity->name);
        echo "<img src=\"" . $OUTPUT->pix_url('icon', $activity->type) . "\" ".
             "class=\"icon\" alt=\"{$aname}\" />";
    }
    echo "<a href=\"$CFG->wwwroot/mod/cybrary/discuss.php?d={$activity->content->discussion}"
         ."#p{$activity->content->id}\">{$activity->content->subject}</a>";
    echo '</div>';

    echo '<div class="user">';
    $fullname = fullname($activity->user, $viewfullnames);
    echo "<a href=\"$CFG->wwwroot/user/view.php?id={$activity->user->id}&amp;course=$courseid\">"
         ."{$fullname}</a> - ".userdate($activity->timestamp);
    echo '</div>';
      echo "</td></tr></table>";

    return;
}

/**
 * recursively sets the discussion field to $discussionid on $postid and all its children
 * used when pruning a post
 *
 * @global object
 * @param int $postid
 * @param int $discussionid
 * @return bool
 */
function cybrary_change_discussionid($postid, $discussionid) {
    global $DB;
    $DB->set_field('cybrary_posts', 'discussion', $discussionid, array('id' => $postid));
    if ($posts = $DB->get_records('cybrary_posts', array('parent' => $postid))) {
        foreach ($posts as $post) {
            cybrary_change_discussionid($post->id, $discussionid);
        }
    }
    return true;
}

/**
 * Prints the editing button on subscribers page
 *
 * @global object
 * @global object
 * @param int $courseid
 * @param int $cybraryid
 * @return string
 */
function cybrary_update_subscriptions_button($courseid, $cybraryid) {
    global $CFG, $USER;

    if (!empty($USER->subscriptionsediting)) {
        $string = get_string('turneditingoff');
        $edit = "off";
    } else {
        $string = get_string('turneditingon');
        $edit = "on";
    }

    return "<form method=\"get\" action=\"$CFG->wwwroot/mod/cybrary/subscribers.php\">".
           "<input type=\"hidden\" name=\"id\" value=\"$cybraryid\" />".
           "<input type=\"hidden\" name=\"edit\" value=\"$edit\" />".
           "<input type=\"submit\" value=\"$string\" /></form>";
}

// Functions to do with read tracking.

/**
 * Mark posts as read.
 *
 * @global object
 * @global object
 * @param object $user object
 * @param array $postids array of post ids
 * @return boolean success
 */
function cybrary_tp_mark_posts_read($user, $postids) {
    global $CFG, $DB;

    if (!cybrary_tp_can_track_cybraries(false, $user)) {
        return true;
    }

    $status = true;

    $now = time();
    $cutoffdate = $now - ($CFG->cybrary_oldpostdays * 24 * 3600);

    if (empty($postids)) {
        return true;

    } else if (count($postids) > 200) {
        while ($part = array_splice($postids, 0, 200)) {
            $status = cybrary_tp_mark_posts_read($user, $part) && $status;
        }
        return $status;
    }

    list($usql, $postidparams) = $DB->get_in_or_equal($postids, SQL_PARAMS_NAMED, 'postid');

    $insertparams = array(
        'userid1' => $user->id,
        'userid2' => $user->id,
        'userid3' => $user->id,
        'firstread' => $now,
        'lastread' => $now,
        'cutoffdate' => $cutoffdate,
    );
    $params = array_merge($postidparams, $insertparams);

    if ($CFG->cybrary_allowforcedreadtracking) {
        $trackingsql = "AND (f.trackingtype = ".CYBRARY_TRACKING_FORCED."
                        OR (f.trackingtype = ".CYBRARY_TRACKING_OPTIONAL." AND tf.id IS NULL))";
    } else {
        $trackingsql = "AND ((f.trackingtype = ".CYBRARY_TRACKING_OPTIONAL."  OR f.trackingtype = ".CYBRARY_TRACKING_FORCED.")
                            AND tf.id IS NULL)";
    }

    // First insert any new entries.
    $sql = "INSERT INTO {cybrary_read} (userid, postid, discussionid, cybraryid, firstread, lastread)

            SELECT :userid1, p.id, p.discussion, d.cybrary, :firstread, :lastread
                FROM {cybrary_posts} p
                    JOIN {cybrary_discussions} d       ON d.id = p.discussion
                    JOIN {cybrary} f                   ON f.id = d.cybrary
                    LEFT JOIN {cybrary_track_prefs} tf ON (tf.userid = :userid2 AND tf.cybraryid = f.id)
                    LEFT JOIN {cybrary_read} fr        ON (
                            fr.userid = :userid3
                        AND fr.postid = p.id
                        AND fr.discussionid = d.id
                        AND fr.cybraryid = f.id
                    )
                WHERE p.id $usql
                    AND p.modified >= :cutoffdate
                    $trackingsql
                    AND fr.id IS NULL";

    $status = $DB->execute($sql, $params) && $status;

    // Then update all records.
    $updateparams = array(
        'userid' => $user->id,
        'lastread' => $now,
    );
    $params = array_merge($postidparams, $updateparams);
    $status = $DB->set_field_select('cybrary_read', 'lastread', $now, '
                userid      =  :userid
            AND lastread    <> :lastread
            AND postid      ' . $usql,
            $params) && $status;

    return $status;
}

/**
 * Mark post as read.
 * @global object
 * @global object
 * @param int $userid
 * @param int $postid
 */
function cybrary_tp_add_read_record($userid, $postid) {
    global $CFG, $DB;

    $now = time();
    $cutoffdate = $now - ($CFG->cybrary_oldpostdays * 24 * 3600);

    if (!$DB->record_exists('cybrary_read', array('userid' => $userid, 'postid' => $postid))) {
        $sql = "INSERT INTO {cybrary_read} (userid, postid, discussionid, cybraryid, firstread, lastread)

                SELECT ?, p.id, p.discussion, d.cybrary, ?, ?
                  FROM {cybrary_posts} p
                       JOIN {cybrary_discussions} d ON d.id = p.discussion
                 WHERE p.id = ? AND p.modified >= ?";
        return $DB->execute($sql, array($userid, $now, $now, $postid, $cutoffdate));

    } else {
        $sql = "UPDATE {cybrary_read}
                   SET lastread = ?
                 WHERE userid = ? AND postid = ?";
        return $DB->execute($sql, array($now, $userid, $userid));
    }
}

/**
 * If its an old post, do nothing. If the record exists, the maintenance will clear it up later.
 *
 * @return bool
 */
function cybrary_tp_mark_post_read($userid, $post, $cybraryid) {
    if (!cybrary_tp_is_post_old($post)) {
        return cybrary_tp_add_read_record($userid, $post->id);
    } else {
        return true;
    }
}

/**
 * Marks a whole cybrary as read, for a given user
 *
 * @global object
 * @global object
 * @param object $user
 * @param int $cybraryid
 * @param int|bool $groupid
 * @return bool
 */
function cybrary_tp_mark_cybrary_read($user, $cybraryid, $groupid=false) {
    global $CFG, $DB;

    $cutoffdate = time() - ($CFG->cybrary_oldpostdays*24*60*60);

    $groupsel = "";
    $params = array($user->id, $cybraryid, $cutoffdate);

    if ($groupid !== false) {
        $groupsel = " AND (d.groupid = ? OR d.groupid = -1)";
        $params[] = $groupid;
    }

    $sql = "SELECT p.id
              FROM {cybrary_posts} p
                   LEFT JOIN {cybrary_discussions} d ON d.id = p.discussion
                   LEFT JOIN {cybrary_read} r        ON (r.postid = p.id AND r.userid = ?)
             WHERE d.cybrary = ?
                   AND p.modified >= ? AND r.id is NULL
                   $groupsel";

    if ($posts = $DB->get_records_sql($sql, $params)) {
        $postids = array_keys($posts);
        return cybrary_tp_mark_posts_read($user, $postids);
    }

    return true;
}

/**
 * Marks a whole discussion as read, for a given user
 *
 * @global object
 * @global object
 * @param object $user
 * @param int $discussionid
 * @return bool
 */
function cybrary_tp_mark_discussion_read($user, $discussionid) {
    global $CFG, $DB;

    $cutoffdate = time() - ($CFG->cybrary_oldpostdays*24*60*60);

    $sql = "SELECT p.id
              FROM {cybrary_posts} p
                   LEFT JOIN {cybrary_read} r ON (r.postid = p.id AND r.userid = ?)
             WHERE p.discussion = ?
                   AND p.modified >= ? AND r.id is NULL";

    if ($posts = $DB->get_records_sql($sql, array($user->id, $discussionid, $cutoffdate))) {
        $postids = array_keys($posts);
        return cybrary_tp_mark_posts_read($user, $postids);
    }

    return true;
}

/**
 * @global object
 * @param int $userid
 * @param object $post
 */
function cybrary_tp_is_post_read($userid, $post) {
    global $DB;
    return (cybrary_tp_is_post_old($post) ||
            $DB->record_exists('cybrary_read', array('userid' => $userid, 'postid' => $post->id)));
}

/**
 * @global object
 * @param object $post
 * @param int $time Defautls to time()
 */
function cybrary_tp_is_post_old($post, $time=null) {
    global $CFG;

    if (is_null($time)) {
        $time = time();
    }
    return ($post->modified < ($time - ($CFG->cybrary_oldpostdays * 24 * 3600)));
}

/**
 * Returns the count of records for the provided user and course.
 * Please note that group access is ignored!
 *
 * @global object
 * @global object
 * @param int $userid
 * @param int $courseid
 * @return array
 */
function cybrary_tp_get_course_unread_posts($userid, $courseid) {
    global $CFG, $DB;

    $now = round(time(), -2); // DB cache friendliness.
    $cutoffdate = $now - ($CFG->cybrary_oldpostdays * 24 * 60 * 60);
    $params = array($userid, $userid, $courseid, $cutoffdate, $userid);

    if (!empty($CFG->cybrary_enabletimedposts)) {
        $timedsql = "AND d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)";
        $params[] = $now;
        $params[] = $now;
    } else {
        $timedsql = "";
    }

    if ($CFG->cybrary_allowforcedreadtracking) {
        $trackingsql = "AND (f.trackingtype = ".CYBRARY_TRACKING_FORCED."
                            OR (f.trackingtype = ".CYBRARY_TRACKING_OPTIONAL." AND tf.id IS NULL
                                AND (SELECT trackcybraries FROM {user} WHERE id = ?) = 1))";
    } else {
        $trackingsql = "AND ((f.trackingtype = ".CYBRARY_TRACKING_OPTIONAL." OR f.trackingtype = ".CYBRARY_TRACKING_FORCED.")
                            AND tf.id IS NULL
                            AND (SELECT trackcybraries FROM {user} WHERE id = ?) = 1)";
    }

    $sql = "SELECT f.id, COUNT(p.id) AS unread
              FROM {cybrary_posts} p
                   JOIN {cybrary_discussions} d       ON d.id = p.discussion
                   JOIN {cybrary} f                   ON f.id = d.cybrary
                   JOIN {course} c                  ON c.id = f.course
                   LEFT JOIN {cybrary_read} r         ON (r.postid = p.id AND r.userid = ?)
                   LEFT JOIN {cybrary_track_prefs} tf ON (tf.userid = ? AND tf.cybraryid = f.id)
             WHERE f.course = ?
                   AND p.modified >= ? AND r.id is NULL
                   $trackingsql
                   $timedsql
          GROUP BY f.id";

    if ($return = $DB->get_records_sql($sql, $params)) {
        return $return;
    }

    return array();
}

/**
 * Returns the count of records for the provided user and cybrary and [optionally] group.
 *
 * @global object
 * @global object
 * @global object
 * @param object $cm
 * @param object $course
 * @return int
 */
function cybrary_tp_count_cybrary_unread_posts($cm, $course) {
    global $CFG, $USER, $DB;

    static $readcache = array();

    $cybraryid = $cm->instance;

    if (!isset($readcache[$course->id])) {
        $readcache[$course->id] = array();
        if ($counts = cybrary_tp_get_course_unread_posts($USER->id, $course->id)) {
            foreach ($counts as $count) {
                $readcache[$course->id][$count->id] = $count->unread;
            }
        }
    }

    if (empty($readcache[$course->id][$cybraryid])) {
        // no need to check group mode ;-)
        return 0;
    }

    $groupmode = groups_get_activity_groupmode($cm, $course);

    if ($groupmode != SEPARATEGROUPS) {
        return $readcache[$course->id][$cybraryid];
    }

    if (has_capability('moodle/site:accessallgroups', context_module::instance($cm->id))) {
        return $readcache[$course->id][$cybraryid];
    }

    require_once($CFG->dirroot.'/course/lib.php');

    $modinfo = get_fast_modinfo($course);

    $mygroups = $modinfo->get_groups($cm->groupingid);

    // add all groups posts
    $mygroups[-1] = -1;

    list ($groups_sql, $groups_params) = $DB->get_in_or_equal($mygroups);

    $now = round(time(), -2); // db cache friendliness
    $cutoffdate = $now - ($CFG->cybrary_oldpostdays*24*60*60);
    $params = array($USER->id, $cybraryid, $cutoffdate);

    if (!empty($CFG->cybrary_enabletimedposts)) {
        $timedsql = "AND d.timestart < ? AND (d.timeend = 0 OR d.timeend > ?)";
        $params[] = $now;
        $params[] = $now;
    } else {
        $timedsql = "";
    }

    $params = array_merge($params, $groups_params);

    $sql = "SELECT COUNT(p.id)
              FROM {cybrary_posts} p
                   JOIN {cybrary_discussions} d ON p.discussion = d.id
                   LEFT JOIN {cybrary_read} r   ON (r.postid = p.id AND r.userid = ?)
             WHERE d.cybrary = ?
                   AND p.modified >= ? AND r.id is NULL
                   $timedsql
                   AND d.groupid $groups_sql";

    return $DB->get_field_sql($sql, $params);
}

/**
 * Deletes read records for the specified index. At least one parameter must be specified.
 *
 * @global object
 * @param int $userid
 * @param int $postid
 * @param int $discussionid
 * @param int $cybraryid
 * @return bool
 */
function cybrary_tp_delete_read_records($userid=-1, $postid=-1, $discussionid=-1, $cybraryid=-1) {
    global $DB;
    $params = array();

    $select = '';
    if ($userid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'userid = ?';
        $params[] = $userid;
    }
    if ($postid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'postid = ?';
        $params[] = $postid;
    }
    if ($discussionid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'discussionid = ?';
        $params[] = $discussionid;
    }
    if ($cybraryid > -1) {
        if ($select != '') $select .= ' AND ';
        $select .= 'cybraryid = ?';
        $params[] = $cybraryid;
    }
    if ($select == '') {
        return false;
    }
    else {
        return $DB->delete_records_select('cybrary_read', $select, $params);
    }
}
/**
 * Get a list of cybraries not tracked by the user.
 *
 * @global object
 * @global object
 * @param int $userid The id of the user to use.
 * @param int $courseid The id of the course being checked.
 * @return mixed An array indexed by cybrary id, or false.
 */
function cybrary_tp_get_untracked_cybraries($userid, $courseid) {
    global $CFG, $DB;

    if ($CFG->cybrary_allowforcedreadtracking) {
        $trackingsql = "AND (f.trackingtype = ".CYBRARY_TRACKING_OFF."
                            OR (f.trackingtype = ".CYBRARY_TRACKING_OPTIONAL." AND (ft.id IS NOT NULL
                                OR (SELECT trackcybraries FROM {user} WHERE id = ?) = 0)))";
    } else {
        $trackingsql = "AND (f.trackingtype = ".CYBRARY_TRACKING_OFF."
                            OR ((f.trackingtype = ".CYBRARY_TRACKING_OPTIONAL." OR f.trackingtype = ".CYBRARY_TRACKING_FORCED.")
                                AND (ft.id IS NOT NULL
                                    OR (SELECT trackcybraries FROM {user} WHERE id = ?) = 0)))";
    }

    $sql = "SELECT f.id
              FROM {cybrary} f
                   LEFT JOIN {cybrary_track_prefs} ft ON (ft.cybraryid = f.id AND ft.userid = ?)
             WHERE f.course = ?
                   $trackingsql";

    if ($cybraries = $DB->get_records_sql($sql, array($userid, $courseid, $userid))) {
        foreach ($cybraries as $cybrary) {
            $cybraries[$cybrary->id] = $cybrary;
        }
        return $cybraries;

    } else {
        return array();
    }
}

/**
 * Determine if a user can track cybraries and optionally a particular cybrary.
 * Checks the site settings, the user settings and the cybrary settings (if
 * requested).
 *
 * @global object
 * @global object
 * @global object
 * @param mixed $cybrary The cybrary object to test, or the int id (optional).
 * @param mixed $userid The user object to check for (optional).
 * @return boolean
 */
function cybrary_tp_can_track_cybraries($cybrary=false, $user=false) {
    global $USER, $CFG, $DB;

    // if possible, avoid expensive
    // queries
    if (empty($CFG->cybrary_trackreadposts)) {
        return false;
    }

    if ($user === false) {
        $user = $USER;
    }

    if (isguestuser($user) or empty($user->id)) {
        return false;
    }

    if ($cybrary === false) {
        if ($CFG->cybrary_allowforcedreadtracking) {
            // Since we can force tracking, assume yes without a specific cybrary.
            return true;
        } else {
            return (bool)$user->trackcybraries;
        }
    }

    // Work toward always passing an object...
    if (is_numeric($cybrary)) {
        debugging('Better use proper cybrary object.', DEBUG_DEVELOPER);
        $cybrary = $DB->get_record('cybrary', array('id' => $cybrary), '', 'id,trackingtype');
    }

    $cybraryallows = ($cybrary->trackingtype == CYBRARY_TRACKING_OPTIONAL);
    $cybraryforced = ($cybrary->trackingtype == CYBRARY_TRACKING_FORCED);

    if ($CFG->cybrary_allowforcedreadtracking) {
        // If we allow forcing, then forced cybraries takes procidence over user setting.
        return ($cybraryforced || ($cybraryallows  && (!empty($user->trackcybraries) && (bool)$user->trackcybraries)));
    } else {
        // If we don't allow forcing, user setting trumps.
        return ($cybraryforced || $cybraryallows)  && !empty($user->trackcybraries);
    }
}

/**
 * Tells whether a specific cybrary is tracked by the user. A user can optionally
 * be specified. If not specified, the current user is assumed.
 *
 * @global object
 * @global object
 * @global object
 * @param mixed $cybrary If int, the id of the cybrary being checked; if object, the cybrary object
 * @param int $userid The id of the user being checked (optional).
 * @return boolean
 */
function cybrary_tp_is_tracked($cybrary, $user=false) {
    global $USER, $CFG, $DB;

    if ($user === false) {
        $user = $USER;
    }

    if (isguestuser($user) or empty($user->id)) {
        return false;
    }

    // Work toward always passing an object...
    if (is_numeric($cybrary)) {
        debugging('Better use proper cybrary object.', DEBUG_DEVELOPER);
        $cybrary = $DB->get_record('cybrary', array('id' => $cybrary));
    }

    if (!cybrary_tp_can_track_cybraries($cybrary, $user)) {
        return false;
    }

    $cybraryallows = ($cybrary->trackingtype == CYBRARY_TRACKING_OPTIONAL);
    $cybraryforced = ($cybrary->trackingtype == CYBRARY_TRACKING_FORCED);
    $userpref = $DB->get_record('cybrary_track_prefs', array('userid' => $user->id, 'cybraryid' => $cybrary->id));

    if ($CFG->cybrary_allowforcedreadtracking) {
        return $cybraryforced || ($cybraryallows && $userpref === false);
    } else {
        return  ($cybraryallows || $cybraryforced) && $userpref === false;
    }
}

/**
 * @global object
 * @global object
 * @param int $cybraryid
 * @param int $userid
 */
function cybrary_tp_start_tracking($cybraryid, $userid=false) {
    global $USER, $DB;

    if ($userid === false) {
        $userid = $USER->id;
    }

    return $DB->delete_records('cybrary_track_prefs', array('userid' => $userid, 'cybraryid' => $cybraryid));
}

/**
 * @global object
 * @global object
 * @param int $cybraryid
 * @param int $userid
 */
function cybrary_tp_stop_tracking($cybraryid, $userid=false) {
    global $USER, $DB;

    if ($userid === false) {
        $userid = $USER->id;
    }

    if (!$DB->record_exists('cybrary_track_prefs', array('userid' => $userid, 'cybraryid' => $cybraryid))) {
        $track_prefs = new stdClass();
        $track_prefs->userid = $userid;
        $track_prefs->cybraryid = $cybraryid;
        $DB->insert_record('cybrary_track_prefs', $track_prefs);
    }

    return cybrary_tp_delete_read_records($userid, -1, -1, $cybraryid);
}


/**
 * Clean old records from the cybrary_read table.
 * @global object
 * @global object
 * @return void
 */
function cybrary_tp_clean_read_records() {
    global $CFG, $DB;

    if (!isset($CFG->cybrary_oldpostdays)) {
        return;
    }
// Look for records older than the cutoffdate that are still in the cybrary_read table.
    $cutoffdate = time() - ($CFG->cybrary_oldpostdays*24*60*60);

    //first get the oldest tracking present - we need tis to speedup the next delete query
    $sql = "SELECT MIN(fp.modified) AS first
              FROM {cybrary_posts} fp
                   JOIN {cybrary_read} fr ON fr.postid=fp.id";
    if (!$first = $DB->get_field_sql($sql)) {
        // nothing to delete;
        return;
    }

    // now delete old tracking info
    $sql = "DELETE
              FROM {cybrary_read}
             WHERE postid IN (SELECT fp.id
                                FROM {cybrary_posts} fp
                               WHERE fp.modified >= ? AND fp.modified < ?)";
    $DB->execute($sql, array($first, $cutoffdate));
}

/**
 * Sets the last post for a given discussion
 *
 * @global object
 * @global object
 * @param into $discussionid
 * @return bool|int
 **/
function cybrary_discussion_update_last_post($discussionid) {
    global $CFG, $DB;

// Check the given discussion exists
    if (!$DB->record_exists('cybrary_discussions', array('id' => $discussionid))) {
        return false;
    }

// Use SQL to find the last post for this discussion
    $sql = "SELECT id, userid, modified
              FROM {cybrary_posts}
             WHERE discussion=?
             ORDER BY modified DESC";

// Lets go find the last post
    if (($lastposts = $DB->get_records_sql($sql, array($discussionid), 0, 1))) {
        $lastpost = reset($lastposts);
        $discussionobject = new stdClass();
        $discussionobject->id           = $discussionid;
        $discussionobject->usermodified = $lastpost->userid;
        $discussionobject->timemodified = $lastpost->modified;
        $DB->update_record('cybrary_discussions', $discussionobject);
        return $lastpost->id;
    }

// To get here either we couldn't find a post for the discussion (weird)
// or we couldn't update the discussion record (weird x2)
    return false;
}


/**
 * List the actions that correspond to a view of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array
 */
function cybrary_get_view_actions() {
    return array('view discussion', 'search', 'cybrary', 'cybraries', 'subscribers', 'view cybrary');
}

/**
 * List the actions that correspond to a post of this module.
 * This is used by the participation report.
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array
 */
function cybrary_get_post_actions() {
    return array('add discussion','add post','delete discussion','delete post','move discussion','prune post','update post');
}

/**
 * Returns a warning object if a user has reached the number of posts equal to
 * the warning/blocking setting, or false if there is no warning to show.
 *
 * @param int|stdClass $cybrary the cybrary id or the cybrary object
 * @param stdClass $cm the course module
 * @return stdClass|bool returns an object with the warning information, else
 *         returns false if no warning is required.
 */
function cybrary_check_throttling($cybrary, $cm = null) {
    global $CFG, $DB, $USER;

    if (is_numeric($cybrary)) {
        $cybrary = $DB->get_record('cybrary', array('id' => $cybrary), '*', MUST_EXIST);
    }

    if (!is_object($cybrary)) {
        return false; // This is broken.
    }

    if (!$cm) {
        $cm = get_coursemodule_from_instance('cybrary', $cybrary->id, $cybrary->course, false, MUST_EXIST);
    }

    if (empty($cybrary->blockafter)) {
        return false;
    }

    if (empty($cybrary->blockperiod)) {
        return false;
    }

    $modcontext = context_module::instance($cm->id);
    if (has_capability('mod/cybrary:postwithoutthrottling', $modcontext)) {
        return false;
    }

    // Get the number of posts in the last period we care about.
    $timenow = time();
    $timeafter = $timenow - $cybrary->blockperiod;
    $numposts = $DB->count_records_sql('SELECT COUNT(p.id) FROM {cybrary_posts} p
                                        JOIN {cybrary_discussions} d
                                        ON p.discussion = d.id WHERE d.cybrary = ?
                                        AND p.userid = ? AND p.created > ?', array($cybrary->id, $USER->id, $timeafter));

    $a = new stdClass();
    $a->blockafter = $cybrary->blockafter;
    $a->numposts = $numposts;
    $a->blockperiod = get_string('secondstotime'.$cybrary->blockperiod);

    if ($cybrary->blockafter <= $numposts) {
        $warning = new stdClass();
        $warning->canpost = false;
        $warning->errorcode = 'cybraryblockingtoomanyposts';
        $warning->module = 'error';
        $warning->additional = $a;
        $warning->link = $CFG->wwwroot . '/mod/cybrary/view.php?f=' . $cybrary->id;

        return $warning;
    }

    if ($cybrary->warnafter <= $numposts) {
        $warning = new stdClass();
        $warning->canpost = true;
        $warning->errorcode = 'cybraryblockingalmosttoomanyposts';
        $warning->module = 'cybrary';
        $warning->additional = $a;
        $warning->link = null;

        return $warning;
    }
}

/**
 * Throws an error if the user is no longer allowed to post due to having reached
 * or exceeded the number of posts specified in 'Post threshold for blocking'
 * setting.
 *
 * @since Moodle 2.5
 * @param stdClass $thresholdwarning the warning information returned
 *        from the function cybrary_check_throttling.
 */
function cybrary_check_blocking_threshold($thresholdwarning) {
    if (!empty($thresholdwarning) && !$thresholdwarning->canpost) {
        print_error($thresholdwarning->errorcode,
                    $thresholdwarning->module,
                    $thresholdwarning->link,
                    $thresholdwarning->additional);
    }
}


/**
 * Removes all grades from gradebook
 *
 * @global object
 * @global object
 * @param int $courseid
 * @param string $type optional
 */
function cybrary_reset_gradebook($courseid, $type='') {
    global $CFG, $DB;

    $wheresql = '';
    $params = array($courseid);
    if ($type) {
        $wheresql = "AND f.type=?";
        $params[] = $type;
    }

    $sql = "SELECT f.*, cm.idnumber as cmidnumber, f.course as courseid
              FROM {cybrary} f, {course_modules} cm, {modules} m
             WHERE m.name='cybrary' AND m.id=cm.module AND cm.instance=f.id AND f.course=? $wheresql";

    if ($cybraries = $DB->get_records_sql($sql, $params)) {
        foreach ($cybraries as $cybrary) {
            cybrary_grade_item_update($cybrary, 'reset');
        }
    }
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all posts from the specified cybrary
 * and clean up any related data.
 *
 * @global object
 * @global object
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function cybrary_reset_userdata($data) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/rating/lib.php');

    $componentstr = get_string('modulenameplural', 'cybrary');
    $status = array();

    $params = array($data->courseid);

    $removeposts = false;
    $typesql     = "";
    if (!empty($data->reset_cybrary_all)) {
        $removeposts = true;
        $typesstr    = get_string('resetcybrariesall', 'cybrary');
        $types       = array();
    } else if (!empty($data->reset_cybrary_types)){
        $removeposts = true;
        $types       = array();
        $sqltypes    = array();
        $cybrary_types_all = cybrary_get_cybrary_types_all();
        foreach ($data->reset_cybrary_types as $type) {
            if (!array_key_exists($type, $cybrary_types_all)) {
                continue;
            }
            $types[] = $cybrary_types_all[$type];
            $sqltypes[] = $type;
        }
        if (!empty($sqltypes)) {
            list($typesql, $typeparams) = $DB->get_in_or_equal($sqltypes);
            $typesql = " AND f.type " . $typesql;
            $params = array_merge($params, $typeparams);
        }
        $typesstr = get_string('resetcybraries', 'cybrary').': '.implode(', ', $types);
    }
    $alldiscussionssql = "SELECT fd.id
                            FROM {cybrary_discussions} fd, {cybrary} f
                           WHERE f.course=? AND f.id=fd.cybrary";

    $allcybrariessql      = "SELECT f.id
                            FROM {cybrary} f
                           WHERE f.course=?";

    $allpostssql       = "SELECT fp.id
                            FROM {cybrary_posts} fp, {cybrary_discussions} fd, {cybrary} f
                           WHERE f.course=? AND f.id=fd.cybrary AND fd.id=fp.discussion";

    $cybrariessql = $cybraries = $rm = null;

    if( $removeposts || !empty($data->reset_cybrary_ratings) ) {
        $cybrariessql      = "$allcybrariessql $typesql";
        $cybraries = $cybraries = $DB->get_records_sql($cybrariessql, $params);
        $rm = new rating_manager();
        $ratingdeloptions = new stdClass;
        $ratingdeloptions->component = 'mod_cybrary';
        $ratingdeloptions->ratingarea = 'post';
    }

    if ($removeposts) {
        $discussionssql = "$alldiscussionssql $typesql";
        $postssql       = "$allpostssql $typesql";

        // now get rid of all attachments
        $fs = get_file_storage();
        if ($cybraries) {
            foreach ($cybraries as $cybraryid=>$unused) {
                if (!$cm = get_coursemodule_from_instance('cybrary', $cybraryid)) {
                    continue;
                }
                $context = context_module::instance($cm->id);
                $fs->delete_area_files($context->id, 'mod_cybrary', 'attachment');
                $fs->delete_area_files($context->id, 'mod_cybrary', 'post');

                //remove ratings
                $ratingdeloptions->contextid = $context->id;
                $rm->delete_ratings($ratingdeloptions);
            }
        }

        // first delete all read flags
        $DB->delete_records_select('cybrary_read', "cybraryid IN ($cybrariessql)", $params);

        // remove tracking prefs
        $DB->delete_records_select('cybrary_track_prefs', "cybraryid IN ($cybrariessql)", $params);

        // remove posts from queue
        $DB->delete_records_select('cybrary_queue', "discussionid IN ($discussionssql)", $params);

        // all posts - initial posts must be kept in single simple discussion cybraries
        $DB->delete_records_select('cybrary_posts', "discussion IN ($discussionssql) AND parent <> 0", $params); // first all children
        $DB->delete_records_select('cybrary_posts', "discussion IN ($discussionssql AND f.type <> 'single') AND parent = 0", $params); // now the initial posts for non single simple

        // finally all discussions except single simple cybraries
        $DB->delete_records_select('cybrary_discussions', "cybrary IN ($cybrariessql AND f.type <> 'single')", $params);

        // remove all grades from gradebook
        if (empty($data->reset_gradebook_grades)) {
            if (empty($types)) {
                cybrary_reset_gradebook($data->courseid);
            } else {
                foreach ($types as $type) {
                    cybrary_reset_gradebook($data->courseid, $type);
                }
            }
        }

        $status[] = array('component'=>$componentstr, 'item'=>$typesstr, 'error'=>false);
    }

    // remove all ratings in this course's cybraries
    if (!empty($data->reset_cybrary_ratings)) {
        if ($cybraries) {
            foreach ($cybraries as $cybraryid=>$unused) {
                if (!$cm = get_coursemodule_from_instance('cybrary', $cybraryid)) {
                    continue;
                }
                $context = context_module::instance($cm->id);

                //remove ratings
                $ratingdeloptions->contextid = $context->id;
                $rm->delete_ratings($ratingdeloptions);
            }
        }

        // remove all grades from gradebook
        if (empty($data->reset_gradebook_grades)) {
            cybrary_reset_gradebook($data->courseid);
        }
    }

    // remove all digest settings unconditionally - even for users still enrolled in course.
    if (!empty($data->reset_cybrary_digests)) {
        $DB->delete_records_select('cybrary_digests', "cybrary IN ($allcybrariessql)", $params);
        $status[] = array('component' => $componentstr, 'item' => get_string('resetdigests', 'cybrary'), 'error' => false);
    }

    // remove all subscriptions unconditionally - even for users still enrolled in course
    if (!empty($data->reset_cybrary_subscriptions)) {
        $DB->delete_records_select('cybrary_subscriptions', "cybrary IN ($allcybrariessql)", $params);
        $DB->delete_records_select('cybrary_discussion_subs', "cybrary IN ($allcybrariessql)", $params);
        $status[] = array('component' => $componentstr, 'item' => get_string('resetsubscriptions', 'cybrary'), 'error' => false);
    }

    // remove all tracking prefs unconditionally - even for users still enrolled in course
    if (!empty($data->reset_cybrary_track_prefs)) {
        $DB->delete_records_select('cybrary_track_prefs', "cybraryid IN ($allcybrariessql)", $params);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('resettrackprefs','cybrary'), 'error'=>false);
    }

    /// updating dates - shift may be negative too
    if ($data->timeshift) {
        shift_course_mod_dates('cybrary', array('assesstimestart', 'assesstimefinish'), $data->timeshift, $data->courseid);
        $status[] = array('component'=>$componentstr, 'item'=>get_string('datechanged'), 'error'=>false);
    }

    return $status;
}

/**
 * Called by course/reset.php
 *
 * @param $mform form passed by reference
 */
function cybrary_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'cybraryheader', get_string('modulenameplural', 'cybrary'));

    $mform->addElement('checkbox', 'reset_cybrary_all', get_string('resetcybrariesall','cybrary'));

    $mform->addElement('select', 'reset_cybrary_types', get_string('resetcybraries', 'cybrary'), cybrary_get_cybrary_types_all(), array('multiple' => 'multiple'));
    $mform->setAdvanced('reset_cybrary_types');
    $mform->disabledIf('reset_cybrary_types', 'reset_cybrary_all', 'checked');

    $mform->addElement('checkbox', 'reset_cybrary_digests', get_string('resetdigests','cybrary'));
    $mform->setAdvanced('reset_cybrary_digests');

    $mform->addElement('checkbox', 'reset_cybrary_subscriptions', get_string('resetsubscriptions','cybrary'));
    $mform->setAdvanced('reset_cybrary_subscriptions');

    $mform->addElement('checkbox', 'reset_cybrary_track_prefs', get_string('resettrackprefs','cybrary'));
    $mform->setAdvanced('reset_cybrary_track_prefs');
    $mform->disabledIf('reset_cybrary_track_prefs', 'reset_cybrary_all', 'checked');

    $mform->addElement('checkbox', 'reset_cybrary_ratings', get_string('deleteallratings'));
    $mform->disabledIf('reset_cybrary_ratings', 'reset_cybrary_all', 'checked');
}

/**
 * Course reset form defaults.
 * @return array
 */
function cybrary_reset_course_form_defaults($course) {
    return array('reset_cybrary_all'=>1, 'reset_cybrary_digests' => 0, 'reset_cybrary_subscriptions'=>0, 'reset_cybrary_track_prefs'=>0, 'reset_cybrary_ratings'=>1);
}

/**
 * Returns array of cybrary layout modes
 *
 * @return array
 */
function cybrary_get_layout_modes() {
    return array (CYBRARY_MODE_FLATOLDEST => get_string('modeflatoldestfirst', 'cybrary'),
                  CYBRARY_MODE_FLATNEWEST => get_string('modeflatnewestfirst', 'cybrary'),
                  CYBRARY_MODE_THREADED   => get_string('modethreaded', 'cybrary'),
                  CYBRARY_MODE_NESTED     => get_string('modenested', 'cybrary'));
}

/**
 * Returns array of cybrary types chooseable on the cybrary editing form
 *
 * @return array
 */
function cybrary_get_cybrary_types() {
    return array (//'general'  => get_string('generalcybrary', 'cybrary'),
                  //'eachuser' => get_string('eachusercybrary', 'cybrary'),
                  'single'   => get_string('singlecybrary', 'cybrary'),
                  //'qanda'    => get_string('qandacybrary', 'cybrary'),
                  //'blog'     => get_string('blogcybrary', 'cybrary')
	);
}

/**
 * Returns array of all cybrary layout modes
 *
 * @return array
 */
function cybrary_get_cybrary_types_all() {
    return array ('news'     => get_string('namenews','cybrary'),
                  'social'   => get_string('namesocial','cybrary'),
                  'general'  => get_string('generalcybrary', 'cybrary'),
                  'eachuser' => get_string('eachusercybrary', 'cybrary'),
                  'single'   => get_string('singlecybrary', 'cybrary'),
                  'qanda'    => get_string('qandacybrary', 'cybrary'),
                  'blog'     => get_string('blogcybrary', 'cybrary'));
}

/**
 * Returns all other caps used in module
 *
 * @return array
 */
function cybrary_get_extra_capabilities() {
    return array('moodle/site:accessallgroups', 'moodle/site:viewfullnames', 'moodle/site:trustcontent', 'moodle/rating:view', 'moodle/rating:viewany', 'moodle/rating:viewall', 'moodle/rating:rate');
}

/**
 * Adds module specific settings to the settings block
 *
 * @param settings_navigation $settings The settings navigation object
 * @param navigation_node $cybrarynode The node to add module settings to
 */
function cybrary_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $cybrarynode) {
    global $USER, $PAGE, $CFG, $DB, $OUTPUT;

    $cybraryobject = $DB->get_record("cybrary", array("id" => $PAGE->cm->instance));
    if (empty($PAGE->cm->context)) {
        $PAGE->cm->context = context_module::instance($PAGE->cm->instance);
    }

    $params = $PAGE->url->params();
    if (!empty($params['d'])) {
        $discussionid = $params['d'];
    }

    // for some actions you need to be enrolled, beiing admin is not enough sometimes here
    $enrolled = is_enrolled($PAGE->cm->context, $USER, '', false);
    $activeenrolled = is_enrolled($PAGE->cm->context, $USER, '', true);

    $canmanage  = has_capability('mod/cybrary:managesubscriptions', $PAGE->cm->context);
    $subscriptionmode = \mod_cybrary\subscriptions::get_subscription_mode($cybraryobject);
    $cansubscribe = $activeenrolled && !\mod_cybrary\subscriptions::is_forcesubscribed($cybraryobject) &&
            (!\mod_cybrary\subscriptions::subscription_disabled($cybraryobject) || $canmanage);

    if ($canmanage) {
        $mode = $cybrarynode->add(get_string('subscriptionmode', 'cybrary'), null, navigation_node::TYPE_CONTAINER);

        $allowchoice = $mode->add(get_string('subscriptionoptional', 'cybrary'), new moodle_url('/mod/cybrary/subscribe.php', array('id'=>$cybraryobject->id, 'mode'=>CYBRARY_CHOOSESUBSCRIBE, 'sesskey'=>sesskey())), navigation_node::TYPE_SETTING);
        $forceforever = $mode->add(get_string("subscriptionforced", "cybrary"), new moodle_url('/mod/cybrary/subscribe.php', array('id'=>$cybraryobject->id, 'mode'=>CYBRARY_FORCESUBSCRIBE, 'sesskey'=>sesskey())), navigation_node::TYPE_SETTING);
        $forceinitially = $mode->add(get_string("subscriptionauto", "cybrary"), new moodle_url('/mod/cybrary/subscribe.php', array('id'=>$cybraryobject->id, 'mode'=>CYBRARY_INITIALSUBSCRIBE, 'sesskey'=>sesskey())), navigation_node::TYPE_SETTING);
        $disallowchoice = $mode->add(get_string('subscriptiondisabled', 'cybrary'), new moodle_url('/mod/cybrary/subscribe.php', array('id'=>$cybraryobject->id, 'mode'=>CYBRARY_DISALLOWSUBSCRIBE, 'sesskey'=>sesskey())), navigation_node::TYPE_SETTING);

        switch ($subscriptionmode) {
            case CYBRARY_CHOOSESUBSCRIBE : // 0
                $allowchoice->action = null;
                $allowchoice->add_class('activesetting');
                break;
            case CYBRARY_FORCESUBSCRIBE : // 1
                $forceforever->action = null;
                $forceforever->add_class('activesetting');
                break;
            case CYBRARY_INITIALSUBSCRIBE : // 2
                $forceinitially->action = null;
                $forceinitially->add_class('activesetting');
                break;
            case CYBRARY_DISALLOWSUBSCRIBE : // 3
                $disallowchoice->action = null;
                $disallowchoice->add_class('activesetting');
                break;
        }

    } else if ($activeenrolled) {

        switch ($subscriptionmode) {
            case CYBRARY_CHOOSESUBSCRIBE : // 0
                $notenode = $cybrarynode->add(get_string('subscriptionoptional', 'cybrary'));
                break;
            case CYBRARY_FORCESUBSCRIBE : // 1
                $notenode = $cybrarynode->add(get_string('subscriptionforced', 'cybrary'));
                break;
            case CYBRARY_INITIALSUBSCRIBE : // 2
                $notenode = $cybrarynode->add(get_string('subscriptionauto', 'cybrary'));
                break;
            case CYBRARY_DISALLOWSUBSCRIBE : // 3
                $notenode = $cybrarynode->add(get_string('subscriptiondisabled', 'cybrary'));
                break;
        }
    }

    if ($cansubscribe) {
        if (\mod_cybrary\subscriptions::is_subscribed($USER->id, $cybraryobject, null, $PAGE->cm)) {
            $linktext = get_string('unsubscribe', 'cybrary');
        } else {
            $linktext = get_string('subscribe', 'cybrary');
        }
        $url = new moodle_url('/mod/cybrary/subscribe.php', array('id'=>$cybraryobject->id, 'sesskey'=>sesskey()));
        $cybrarynode->add($linktext, $url, navigation_node::TYPE_SETTING);

        if (isset($discussionid)) {
            if (\mod_cybrary\subscriptions::is_subscribed($USER->id, $cybraryobject, $discussionid, $PAGE->cm)) {
                $linktext = get_string('unsubscribediscussion', 'cybrary');
            } else {
                $linktext = get_string('subscribediscussion', 'cybrary');
            }
            $url = new moodle_url('/mod/cybrary/subscribe.php', array(
                    'id' => $cybraryobject->id,
                    'sesskey' => sesskey(),
                    'd' => $discussionid,
                    'returnurl' => $PAGE->url->out(),
                ));
            $cybrarynode->add($linktext, $url, navigation_node::TYPE_SETTING);
        }
    }

    if (has_capability('mod/cybrary:viewsubscribers', $PAGE->cm->context)){
        $url = new moodle_url('/mod/cybrary/subscribers.php', array('id'=>$cybraryobject->id));
        $cybrarynode->add(get_string('showsubscribers', 'cybrary'), $url, navigation_node::TYPE_SETTING);
    }

    if ($enrolled && cybrary_tp_can_track_cybraries($cybraryobject)) { // keep tracking info for users with suspended enrolments
        if ($cybraryobject->trackingtype == CYBRARY_TRACKING_OPTIONAL
                || ((!$CFG->cybrary_allowforcedreadtracking) && $cybraryobject->trackingtype == CYBRARY_TRACKING_FORCED)) {
            if (cybrary_tp_is_tracked($cybraryobject)) {
                $linktext = get_string('notrackcybrary', 'cybrary');
            } else {
                $linktext = get_string('trackcybrary', 'cybrary');
            }
            $url = new moodle_url('/mod/cybrary/settracking.php', array(
                    'id' => $cybraryobject->id,
                    'sesskey' => sesskey(),
                ));
            $cybrarynode->add($linktext, $url, navigation_node::TYPE_SETTING);
        }
    }

    if (!isloggedin() && $PAGE->course->id == SITEID) {
        $userid = guest_user()->id;
    } else {
        $userid = $USER->id;
    }

    $hascourseaccess = ($PAGE->course->id == SITEID) || can_access_course($PAGE->course, $userid);
    $enablerssfeeds = !empty($CFG->enablerssfeeds) && !empty($CFG->cybrary_enablerssfeeds);

    if ($enablerssfeeds && $cybraryobject->rsstype && $cybraryobject->rssarticles && $hascourseaccess) {

        if (!function_exists('rss_get_url')) {
            require_once("$CFG->libdir/rsslib.php");
        }

        if ($cybraryobject->rsstype == 1) {
            $string = get_string('rsssubscriberssdiscussions','cybrary');
        } else {
            $string = get_string('rsssubscriberssposts','cybrary');
        }

        $url = new moodle_url(rss_get_url($PAGE->cm->context->id, $userid, "mod_cybrary", $cybraryobject->id));
        $cybrarynode->add($string, $url, settings_navigation::TYPE_SETTING, null, null, new pix_icon('i/rss', ''));
    }
}

/**
 * Adds information about unread messages, that is only required for the course view page (and
 * similar), to the course-module object.
 * @param cm_info $cm Course-module object
 */
function cybrary_cm_info_view(cm_info $cm) {
    global $CFG;

    if (cybrary_tp_can_track_cybraries()) {
        if ($unread = cybrary_tp_count_cybrary_unread_posts($cm, $cm->get_course())) {
            $out = '<span class="unread"> <a href="' . $cm->url . '">';
            if ($unread == 1) {
                $out .= get_string('unreadpostsone', 'cybrary');
            } else {
                $out .= get_string('unreadpostsnumber', 'cybrary', $unread);
            }
            $out .= '</a></span>';
            $cm->set_after_link($out);
        }
    }
}

/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function cybrary_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $cybrary_pagetype = array(
        'mod-cybrary-*'=>get_string('page-mod-cybrary-x', 'cybrary'),
        'mod-cybrary-view'=>get_string('page-mod-cybrary-view', 'cybrary'),
        'mod-cybrary-discuss'=>get_string('page-mod-cybrary-discuss', 'cybrary')
    );
    return $cybrary_pagetype;
}

/**
 * Gets all of the courses where the provided user has posted in a cybrary.
 *
 * @global moodle_database $DB The database connection
 * @param stdClass $user The user who's posts we are looking for
 * @param bool $discussionsonly If true only look for discussions started by the user
 * @param bool $includecontexts If set to trye contexts for the courses will be preloaded
 * @param int $limitfrom The offset of records to return
 * @param int $limitnum The number of records to return
 * @return array An array of courses
 */
function cybrary_get_courses_user_posted_in($user, $discussionsonly = false, $includecontexts = true, $limitfrom = null, $limitnum = null) {
    global $DB;

    // If we are only after discussions we need only look at the cybrary_discussions
    // table and join to the userid there. If we are looking for posts then we need
    // to join to the cybrary_posts table.
    if (!$discussionsonly) {
        $subquery = "(SELECT DISTINCT fd.course
                         FROM {cybrary_discussions} fd
                         JOIN {cybrary_posts} fp ON fp.discussion = fd.id
                        WHERE fp.userid = :userid )";
    } else {
        $subquery= "(SELECT DISTINCT fd.course
                         FROM {cybrary_discussions} fd
                        WHERE fd.userid = :userid )";
    }

    $params = array('userid' => $user->id);

    // Join to the context table so that we can preload contexts if required.
    if ($includecontexts) {
        $ctxselect = ', ' . context_helper::get_preload_record_columns_sql('ctx');
        $ctxjoin = "LEFT JOIN {context} ctx ON (ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel)";
        $params['contextlevel'] = CONTEXT_COURSE;
    } else {
        $ctxselect = '';
        $ctxjoin = '';
    }

    // Now we need to get all of the courses to search.
    // All courses where the user has posted within a cybrary will be returned.
    $sql = "SELECT c.* $ctxselect
            FROM {course} c
            $ctxjoin
            WHERE c.id IN ($subquery)";
    $courses = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
    if ($includecontexts) {
        array_map('context_helper::preload_from_record', $courses);
    }
    return $courses;
}

/**
 * Gets all of the cybraries a user has posted in for one or more courses.
 *
 * @global moodle_database $DB
 * @param stdClass $user
 * @param array $courseids An array of courseids to search or if not provided
 *                       all courses the user has posted within
 * @param bool $discussionsonly If true then only cybraries where the user has started
 *                       a discussion will be returned.
 * @param int $limitfrom The offset of records to return
 * @param int $limitnum The number of records to return
 * @return array An array of cybraries the user has posted within in the provided courses
 */
function cybrary_get_cybraries_user_posted_in($user, array $courseids = null, $discussionsonly = false, $limitfrom = null, $limitnum = null) {
    global $DB;

    if (!is_null($courseids)) {
        list($coursewhere, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'courseid');
        $coursewhere = ' AND f.course '.$coursewhere;
    } else {
        $coursewhere = '';
        $params = array();
    }
    $params['userid'] = $user->id;
    $params['cybrary'] = 'cybrary';

    if ($discussionsonly) {
        $join = 'JOIN {cybrary_discussions} ff ON ff.cybrary = f.id';
    } else {
        $join = 'JOIN {cybrary_discussions} fd ON fd.cybrary = f.id
                 JOIN {cybrary_posts} ff ON ff.discussion = fd.id';
    }

    $sql = "SELECT f.*, cm.id AS cmid
              FROM {cybrary} f
              JOIN {course_modules} cm ON cm.instance = f.id
              JOIN {modules} m ON m.id = cm.module
              JOIN (
                  SELECT f.id
                    FROM {cybrary} f
                    {$join}
                   WHERE ff.userid = :userid
                GROUP BY f.id
                   ) j ON j.id = f.id
             WHERE m.name = :cybrary
                 {$coursewhere}";

    $coursecybraries = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
    return $coursecybraries;
}

/**
 * Returns posts made by the selected user in the requested courses.
 *
 * This method can be used to return all of the posts made by the requested user
 * within the given courses.
 * For each course the access of the current user and requested user is checked
 * and then for each post access to the post and cybrary is checked as well.
 *
 * This function is safe to use with usercapabilities.
 *
 * @global moodle_database $DB
 * @param stdClass $user The user whose posts we want to get
 * @param array $courses The courses to search
 * @param bool $musthaveaccess If set to true errors will be thrown if the user
 *                             cannot access one or more of the courses to search
 * @param bool $discussionsonly If set to true only discussion starting posts
 *                              will be returned.
 * @param int $limitfrom The offset of records to return
 * @param int $limitnum The number of records to return
 * @return stdClass An object the following properties
 *               ->totalcount: the total number of posts made by the requested user
 *                             that the current user can see.
 *               ->courses: An array of courses the current user can see that the
 *                          requested user has posted in.
 *               ->cybraries: An array of cybraries relating to the posts returned in the
 *                         property below.
 *               ->posts: An array containing the posts to show for this request.
 */
function cybrary_get_posts_by_user($user, array $courses, $musthaveaccess = false, $discussionsonly = false, $limitfrom = 0, $limitnum = 50) {
    global $DB, $USER, $CFG;

    $return = new stdClass;
    $return->totalcount = 0;    // The total number of posts that the current user is able to view
    $return->courses = array(); // The courses the current user can access
    $return->cybraries = array();  // The cybraries that the current user can access that contain posts
    $return->posts = array();   // The posts to display

    // First up a small sanity check. If there are no courses to check we can
    // return immediately, there is obviously nothing to search.
    if (empty($courses)) {
        return $return;
    }

    // A couple of quick setups
    $isloggedin = isloggedin();
    $isguestuser = $isloggedin && isguestuser();
    $iscurrentuser = $isloggedin && $USER->id == $user->id;

    // Checkout whether or not the current user has capabilities over the requested
    // user and if so they have the capabilities required to view the requested
    // users content.
    $usercontext = context_user::instance($user->id, MUST_EXIST);
    $hascapsonuser = !$iscurrentuser && $DB->record_exists('role_assignments', array('userid' => $USER->id, 'contextid' => $usercontext->id));
    $hascapsonuser = $hascapsonuser && has_all_capabilities(array('moodle/user:viewdetails', 'moodle/user:readuserposts'), $usercontext);

    // Before we actually search each course we need to check the user's access to the
    // course. If the user doesn't have the appropraite access then we either throw an
    // error if a particular course was requested or we just skip over the course.
    foreach ($courses as $course) {
        $coursecontext = context_course::instance($course->id, MUST_EXIST);
        if ($iscurrentuser || $hascapsonuser) {
            // If it is the current user, or the current user has capabilities to the
            // requested user then all we need to do is check the requested users
            // current access to the course.
            // Note: There is no need to check group access or anything of the like
            // as either the current user is the requested user, or has granted
            // capabilities on the requested user. Either way they can see what the
            // requested user posted, although its VERY unlikely in the `parent` situation
            // that the current user will be able to view the posts in context.
            if (!is_viewing($coursecontext, $user) && !is_enrolled($coursecontext, $user)) {
                // Need to have full access to a course to see the rest of own info
                if ($musthaveaccess) {
                    print_error('errorenrolmentrequired', 'cybrary');
                }
                continue;
            }
        } else {
            // Check whether the current user is enrolled or has access to view the course
            // if they don't we immediately have a problem.
            if (!can_access_course($course)) {
                if ($musthaveaccess) {
                    print_error('errorenrolmentrequired', 'cybrary');
                }
                continue;
            }

            // Check whether the requested user is enrolled or has access to view the course
            // if they don't we immediately have a problem.
            if (!can_access_course($course, $user) && !is_enrolled($coursecontext, $user)) {
                if ($musthaveaccess) {
                    print_error('notenrolled', 'cybrary');
                }
                continue;
            }

            // If groups are in use and enforced throughout the course then make sure
            // we can meet in at least one course level group.
            // Note that we check if either the current user or the requested user have
            // the capability to access all groups. This is because with that capability
            // a user in group A could post in the group B cybrary. Grrrr.
            if (groups_get_course_groupmode($course) == SEPARATEGROUPS && $course->groupmodeforce
              && !has_capability('moodle/site:accessallgroups', $coursecontext) && !has_capability('moodle/site:accessallgroups', $coursecontext, $user->id)) {
                // If its the guest user to bad... the guest user cannot access groups
                if (!$isloggedin or $isguestuser) {
                    // do not use require_login() here because we might have already used require_login($course)
                    if ($musthaveaccess) {
                        redirect(get_login_url());
                    }
                    continue;
                }
                // Get the groups of the current user
                $mygroups = array_keys(groups_get_all_groups($course->id, $USER->id, $course->defaultgroupingid, 'g.id, g.name'));
                // Get the groups the requested user is a member of
                $usergroups = array_keys(groups_get_all_groups($course->id, $user->id, $course->defaultgroupingid, 'g.id, g.name'));
                // Check whether they are members of the same group. If they are great.
                $intersect = array_intersect($mygroups, $usergroups);
                if (empty($intersect)) {
                    // But they're not... if it was a specific course throw an error otherwise
                    // just skip this course so that it is not searched.
                    if ($musthaveaccess) {
                        print_error("groupnotamember", '', $CFG->wwwroot."/course/view.php?id=$course->id");
                    }
                    continue;
                }
            }
        }
        // Woo hoo we got this far which means the current user can search this
        // this course for the requested user. Although this is only the course accessibility
        // handling that is complete, the cybrary accessibility tests are yet to come.
        $return->courses[$course->id] = $course;
    }
    // No longer beed $courses array - lose it not it may be big
    unset($courses);

    // Make sure that we have some courses to search
    if (empty($return->courses)) {
        // If we don't have any courses to search then the reality is that the current
        // user doesn't have access to any courses is which the requested user has posted.
        // Although we do know at this point that the requested user has posts.
        if ($musthaveaccess) {
            print_error('permissiondenied');
        } else {
            return $return;
        }
    }

    // Next step: Collect all of the cybraries that we will want to search.
    // It is important to note that this step isn't actually about searching, it is
    // about determining which cybraries we can search by testing accessibility.
    $cybraries = cybrary_get_cybraries_user_posted_in($user, array_keys($return->courses), $discussionsonly);

    // Will be used to build the where conditions for the search
    $cybrariesearchwhere = array();
    // Will be used to store the where condition params for the search
    $cybrariesearchparams = array();
    // Will record cybraries where the user can freely access everything
    $cybrariesearchfullaccess = array();
    // DB caching friendly
    $now = round(time(), -2);
    // For each course to search we want to find the cybraries the user has posted in
    // and providing the current user can access the cybrary create a search condition
    // for the cybrary to get the requested users posts.
    foreach ($return->courses as $course) {
        // Now we need to get the cybraries
        $modinfo = get_fast_modinfo($course);
        if (empty($modinfo->instances['cybrary'])) {
            // hmmm, no cybraries? well at least its easy... skip!
            continue;
        }
        // Iterate
        foreach ($modinfo->get_instances_of('cybrary') as $cybraryid => $cm) {
            if (!$cm->uservisible or !isset($cybraries[$cybraryid])) {
                continue;
            }
            // Get the cybrary in question
            $cybrary = $cybraries[$cybraryid];

            // This is needed for functionality later on in the cybrary code. It is converted to an object
            // because the cm_info is readonly from 2.6. This is a dirty hack because some other parts of the
            // code were expecting an writeable object. See {@link cybrary_print_post()}.
            $cybrary->cm = new stdClass();
            foreach ($cm as $key => $value) {
                $cybrary->cm->$key = $value;
            }

            // Check that either the current user can view the cybrary, or that the
            // current user has capabilities over the requested user and the requested
            // user can view the discussion
            if (!has_capability('mod/cybrary:viewdiscussion', $cm->context) && !($hascapsonuser && has_capability('mod/cybrary:viewdiscussion', $cm->context, $user->id))) {
                continue;
            }

            // This will contain cybrary specific where clauses
            $cybrariesearchselect = array();
            if (!$iscurrentuser && !$hascapsonuser) {
                // Make sure we check group access
                if (groups_get_activity_groupmode($cm, $course) == SEPARATEGROUPS and !has_capability('moodle/site:accessallgroups', $cm->context)) {
                    $groups = $modinfo->get_groups($cm->groupingid);
                    $groups[] = -1;
                    list($groupid_sql, $groupid_params) = $DB->get_in_or_equal($groups, SQL_PARAMS_NAMED, 'grps'.$cybraryid.'_');
                    $cybrariesearchparams = array_merge($cybrariesearchparams, $groupid_params);
                    $cybrariesearchselect[] = "d.groupid $groupid_sql";
                }

                // hidden timed discussions
                if (!empty($CFG->cybrary_enabletimedposts) && !has_capability('mod/cybrary:viewhiddentimedposts', $cm->context)) {
                    $cybrariesearchselect[] = "(d.userid = :userid{$cybraryid} OR (d.timestart < :timestart{$cybraryid} AND (d.timeend = 0 OR d.timeend > :timeend{$cybraryid})))";
                    $cybrariesearchparams['userid'.$cybraryid] = $user->id;
                    $cybrariesearchparams['timestart'.$cybraryid] = $now;
                    $cybrariesearchparams['timeend'.$cybraryid] = $now;
                }

                // qanda access
                if ($cybrary->type == 'qanda' && !has_capability('mod/cybrary:viewqandawithoutposting', $cm->context)) {
                    // We need to check whether the user has posted in the qanda cybrary.
                    $discussionspostedin = cybrary_discussions_user_has_posted_in($cybrary->id, $user->id);
                    if (!empty($discussionspostedin)) {
                        $cybraryonlydiscussions = array();  // Holds discussion ids for the discussions the user is allowed to see in this cybrary.
                        foreach ($discussionspostedin as $d) {
                            $cybraryonlydiscussions[] = $d->id;
                        }
                        list($discussionid_sql, $discussionid_params) = $DB->get_in_or_equal($cybraryonlydiscussions, SQL_PARAMS_NAMED, 'qanda'.$cybraryid.'_');
                        $cybrariesearchparams = array_merge($cybrariesearchparams, $discussionid_params);
                        $cybrariesearchselect[] = "(d.id $discussionid_sql OR p.parent = 0)";
                    } else {
                        $cybrariesearchselect[] = "p.parent = 0";
                    }

                }

                if (count($cybrariesearchselect) > 0) {
                    $cybrariesearchwhere[] = "(d.cybrary = :cybrary{$cybraryid} AND ".implode(" AND ", $cybrariesearchselect).")";
                    $cybrariesearchparams['cybrary'.$cybraryid] = $cybraryid;
                } else {
                    $cybrariesearchfullaccess[] = $cybraryid;
                }
            } else {
                // The current user/parent can see all of their own posts
                $cybrariesearchfullaccess[] = $cybraryid;
            }
        }
    }

    // If we dont have any search conditions, and we don't have any cybraries where
    // the user has full access then we just return the default.
    if (empty($cybrariesearchwhere) && empty($cybrariesearchfullaccess)) {
        return $return;
    }

    // Prepare a where condition for the full access cybraries.
    if (count($cybrariesearchfullaccess) > 0) {
        list($fullidsql, $fullidparams) = $DB->get_in_or_equal($cybrariesearchfullaccess, SQL_PARAMS_NAMED, 'fula');
        $cybrariesearchparams = array_merge($cybrariesearchparams, $fullidparams);
        $cybrariesearchwhere[] = "(d.cybrary $fullidsql)";
    }

    // Prepare SQL to both count and search.
    // We alias user.id to useridx because we cybrary_posts already has a userid field and not aliasing this would break
    // oracle and mssql.
    $userfields = user_picture::fields('u', null, 'useridx');
    $countsql = 'SELECT COUNT(*) ';
    $selectsql = 'SELECT p.*, d.cybrary, d.name AS discussionname, '.$userfields.' ';
    $wheresql = implode(" OR ", $cybrariesearchwhere);

    if ($discussionsonly) {
        if ($wheresql == '') {
            $wheresql = 'p.parent = 0';
        } else {
            $wheresql = 'p.parent = 0 AND ('.$wheresql.')';
        }
    }

    $sql = "FROM {cybrary_posts} p
            JOIN {cybrary_discussions} d ON d.id = p.discussion
            JOIN {user} u ON u.id = p.userid
           WHERE ($wheresql)
             AND p.userid = :userid ";
    $orderby = "ORDER BY p.modified DESC";
    $cybrariesearchparams['userid'] = $user->id;

    // Set the total number posts made by the requested user that the current user can see
    $return->totalcount = $DB->count_records_sql($countsql.$sql, $cybrariesearchparams);
    // Set the collection of posts that has been requested
    $return->posts = $DB->get_records_sql($selectsql.$sql.$orderby, $cybrariesearchparams, $limitfrom, $limitnum);

    // We need to build an array of cybraries for which posts will be displayed.
    // We do this here to save the caller needing to retrieve them themselves before
    // printing these cybraries posts. Given we have the cybraries already there is
    // practically no overhead here.
    foreach ($return->posts as $post) {
        if (!array_key_exists($post->cybrary, $return->cybraries)) {
            $return->cybraries[$post->cybrary] = $cybraries[$post->cybrary];
        }
    }

    return $return;
}

/**
 * Set the per-cybrary maildigest option for the specified user.
 *
 * @param stdClass $cybrary The cybrary to set the option for.
 * @param int $maildigest The maildigest option.
 * @param stdClass $user The user object. This defaults to the global $USER object.
 * @throws invalid_digest_setting thrown if an invalid maildigest option is provided.
 */
function cybrary_set_user_maildigest($cybrary, $maildigest, $user = null) {
    global $DB, $USER;

    if (is_number($cybrary)) {
        $cybrary = $DB->get_record('cybrary', array('id' => $cybrary));
    }

    if ($user === null) {
        $user = $USER;
    }

    $course  = $DB->get_record('course', array('id' => $cybrary->course), '*', MUST_EXIST);
    $cm      = get_coursemodule_from_instance('cybrary', $cybrary->id, $course->id, false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    // User must be allowed to see this cybrary.
    require_capability('mod/cybrary:viewdiscussion', $context, $user->id);

    // Validate the maildigest setting.
    $digestoptions = cybrary_get_user_digest_options($user);

    if (!isset($digestoptions[$maildigest])) {
        throw new moodle_exception('invaliddigestsetting', 'mod_cybrary');
    }

    // Attempt to retrieve any existing cybrary digest record.
    $subscription = $DB->get_record('cybrary_digests', array(
        'userid' => $user->id,
        'cybrary' => $cybrary->id,
    ));

    // Create or Update the existing maildigest setting.
    if ($subscription) {
        if ($maildigest == -1) {
            $DB->delete_records('cybrary_digests', array('cybrary' => $cybrary->id, 'userid' => $user->id));
        } else if ($maildigest !== $subscription->maildigest) {
            // Only update the maildigest setting if it's changed.

            $subscription->maildigest = $maildigest;
            $DB->update_record('cybrary_digests', $subscription);
        }
    } else {
        if ($maildigest != -1) {
            // Only insert the maildigest setting if it's non-default.

            $subscription = new stdClass();
            $subscription->cybrary = $cybrary->id;
            $subscription->userid = $user->id;
            $subscription->maildigest = $maildigest;
            $subscription->id = $DB->insert_record('cybrary_digests', $subscription);
        }
    }
}

/**
 * Determine the maildigest setting for the specified user against the
 * specified cybrary.
 *
 * @param Array $digests An array of cybraries and user digest settings.
 * @param stdClass $user The user object containing the id and maildigest default.
 * @param int $cybraryid The ID of the cybrary to check.
 * @return int The calculated maildigest setting for this user and cybrary.
 */
function cybrary_get_user_maildigest_bulk($digests, $user, $cybraryid) {
    if (isset($digests[$cybraryid]) && isset($digests[$cybraryid][$user->id])) {
        $maildigest = $digests[$cybraryid][$user->id];
        if ($maildigest === -1) {
            $maildigest = $user->maildigest;
        }
    } else {
        $maildigest = $user->maildigest;
    }
    return $maildigest;
}

/**
 * Retrieve the list of available user digest options.
 *
 * @param stdClass $user The user object. This defaults to the global $USER object.
 * @return array The mapping of values to digest options.
 */
function cybrary_get_user_digest_options($user = null) {
    global $USER;

    // Revert to the global user object.
    if ($user === null) {
        $user = $USER;
    }

    $digestoptions = array();
    $digestoptions['0']  = get_string('emaildigestoffshort', 'mod_cybrary');
    $digestoptions['1']  = get_string('emaildigestcompleteshort', 'mod_cybrary');
    $digestoptions['2']  = get_string('emaildigestsubjectsshort', 'mod_cybrary');

    // We need to add the default digest option at the end - it relies on
    // the contents of the existing values.
    $digestoptions['-1'] = get_string('emaildigestdefault', 'mod_cybrary',
            $digestoptions[$user->maildigest]);

    // Resort the options to be in a sensible order.
    ksort($digestoptions);

    return $digestoptions;
}

/**
 * Determine the current context if one was not already specified.
 *
 * If a context of type context_module is specified, it is immediately
 * returned and not checked.
 *
 * @param int $cybraryid The ID of the cybrary
 * @param context_module $context The current context.
 * @return context_module The context determined
 */
function cybrary_get_context($cybraryid, $context = null) {
    global $PAGE;

    if (!$context || !($context instanceof context_module)) {
        // Find out cybrary context. First try to take current page context to save on DB query.
        if ($PAGE->cm && $PAGE->cm->modname === 'cybrary' && $PAGE->cm->instance == $cybraryid
                && $PAGE->context->contextlevel == CONTEXT_MODULE && $PAGE->context->instanceid == $PAGE->cm->id) {
            $context = $PAGE->context;
        } else {
            $cm = get_coursemodule_from_instance('cybrary', $cybraryid);
            $context = \context_module::instance($cm->id);
        }
    }

    return $context;
}

/**
 * Mark the activity completed (if required) and trigger the course_module_viewed event.
 *
 * @param  stdClass $cybrary   cybrary object
 * @param  stdClass $course  course object
 * @param  stdClass $cm      course module object
 * @param  stdClass $context context object
 * @since Moodle 2.9
 */
function cybrary_view($cybrary, $course, $cm, $context) {

    // Completion.
    $completion = new completion_info($course);
    $completion->set_module_viewed($cm);

    // Trigger course_module_viewed event.

    $params = array(
        'context' => $context,
        'objectid' => $cybrary->id
    );

    $event = \mod_cybrary\event\course_module_viewed::create($params);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('cybrary', $cybrary);
    $event->trigger();
}

/**
 * Trigger the discussion viewed event
 *
 * @param  stdClass $modcontext module context object
 * @param  stdClass $cybrary      cybrary object
 * @param  stdClass $discussion discussion object
 * @since Moodle 2.9
 */
function cybrary_discussion_view($modcontext, $cybrary, $discussion) {
    $params = array(
        'context' => $modcontext,
        'objectid' => $discussion->id,
    );

    $event = \mod_cybrary\event\discussion_viewed::create($params);
    $event->add_record_snapshot('cybrary_discussions', $discussion);
    $event->add_record_snapshot('cybrary', $cybrary);
    $event->trigger();
}

/**
 * Add nodes to myprofile page.
 *
 * @param \core_user\output\myprofile\tree $tree Tree object
 * @param stdClass $user user object
 * @param bool $iscurrentuser
 * @param stdClass $course Course object
 *
 * @return bool
 */
function mod_cybrary_myprofile_navigation(core_user\output\myprofile\tree $tree, $user, $iscurrentuser, $course) {
    if (isguestuser($user)) {
        // The guest user cannot post, so it is not possible to view any posts.
        // May as well just bail aggressively here.
        return false;
    }
    $postsurl = new moodle_url('/mod/cybrary/user.php', array('id' => $user->id));
    if (!empty($course)) {
        $postsurl->param('course', $course->id);
    }
    $string = get_string('cybraryposts', 'mod_cybrary');
    $node = new core_user\output\myprofile\node('miscellaneous', 'cybraryposts', $string, null, $postsurl);
    $tree->add_node($node);

    $discussionssurl = new moodle_url('/mod/cybrary/user.php', array('id' => $user->id, 'mode' => 'discussions'));
    if (!empty($course)) {
        $discussionssurl->param('course', $course->id);
    }
    $string = get_string('myprofileotherdis', 'mod_cybrary');
    $node = new core_user\output\myprofile\node('miscellaneous', 'cybrarydiscussions', $string, null,
        $discussionssurl);
    $tree->add_node($node);

    return true;
}
