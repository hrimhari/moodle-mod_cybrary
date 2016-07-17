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
 * Strings for component 'cybrary', language 'en', branch 'MOODLE_20_STABLE'
 *
 * @package   mod_cybrary
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['activityoverview'] = 'There are new cybrary posts';
$string['addanewdiscussion'] = 'Add a new discussion topic';
$string['addanewquestion'] = 'Add a new question';
$string['addanewtopic'] = 'Add a new topic';
$string['advancedsearch'] = 'Advanced search';
$string['allcybraries'] = 'All cybraries';
$string['allowdiscussions'] = 'Can a {$a} post to this cybrary?';
$string['allowsallsubscribe'] = 'This cybrary allows everyone to choose whether to subscribe or not';
$string['allowsdiscussions'] = 'This cybrary allows each person to start one discussion topic.';
$string['allsubscribe'] = 'Subscribe to all cybraries';
$string['allunsubscribe'] = 'Unsubscribe from all cybraries';
$string['alreadyfirstpost'] = 'This is already the first post in the discussion';
$string['anyfile'] = 'Any file';
$string['areaattachment'] = 'Attachments';
$string['areapost'] = 'Messages';
$string['attachment'] = 'Attachment';
$string['attachment_help'] = 'You can optionally attach one or more files to a cybrary post. If you attach an image, it will be displayed after the message.';
$string['attachmentnopost'] = 'You cannot export attachments without a post id';
$string['attachments'] = 'Attachments';
$string['attachmentswordcount'] = 'Attachments and word count';
$string['blockafter'] = 'Post threshold for blocking';
$string['blockafter_help'] = 'This setting specifies the maximum number of posts which a user can post in the given time period. Users with the capability mod/cybrary:postwithoutthrottling are exempt from post limits.';
$string['blockperiod'] = 'Time period for blocking';
$string['blockperiod_help'] = 'Students can be blocked from posting more than a given number of posts in a given time period. Users with the capability mod/cybrary:postwithoutthrottling are exempt from post limits.';
$string['blockperioddisabled'] = 'Don\'t block';
$string['blogcybrary'] = 'Standard cybrary displayed in a blog-like format';
$string['bynameondate'] = 'by {$a->name} - {$a->date}';
$string['cannotadd'] = 'Could not add the discussion for this cybrary';
$string['cannotadddiscussion'] = 'Adding discussions to this cybrary requires group membership.';
$string['cannotadddiscussionall'] = 'You do not have permission to add a new discussion topic for all participants.';
$string['cannotaddsubscriber'] = 'Could not add subscriber with id {$a} to this cybrary!';
$string['cannotaddteachercybraryto'] = 'Could not add converted teacher cybrary instance to section 0 in the course';
$string['cannotcreatediscussion'] = 'Could not create new discussion';
$string['cannotcreateinstanceforteacher'] = 'Could not create new course module instance for the teacher cybrary';
$string['cannotdeletepost'] = 'You can\'t delete this post!';
$string['cannoteditposts'] = 'You can\'t edit other people\'s posts!';
$string['cannotfinddiscussion'] = 'Could not find the discussion in this cybrary';
$string['cannotfindfirstpost'] = 'Could not find the first post in this cybrary';
$string['cannotfindorcreatecybrary'] = 'Could not find or create a main news cybrary for the site';
$string['cannotfindparentpost'] = 'Could not find top parent of post {$a}';
$string['cannotmovefromsinglecybrary'] = 'Cannot move discussion from a simple single discussion cybrary';
$string['cannotmovenotvisible'] = 'Cybrary not visible';
$string['cannotmovetonotexist'] = 'You can\'t move to that cybrary - it doesn\'t exist!';
$string['cannotmovetonotfound'] = 'Target cybrary not found in this course.';
$string['cannotmovetosinglecybrary'] = 'Cannot move discussion to a simple single discussion cybrary';
$string['cannotpurgecachedrss'] = 'Could not purge the cached RSS feeds for the source and/or destination cybrary(s) - check your file permissionscybraries';
$string['cannotremovesubscriber'] = 'Could not remove subscriber with id {$a} from this cybrary!';
$string['cannotreply'] = 'You cannot reply to this post';
$string['cannotsplit'] = 'Discussions from this cybrary cannot be split';
$string['cannotsubscribe'] = 'Sorry, but you must be a group member to subscribe.';
$string['cannottrack'] = 'Could not stop tracking that cybrary';
$string['cannotunsubscribe'] = 'Could not unsubscribe you from that cybrary';
$string['cannotupdatepost'] = 'You can not update this post';
$string['cannotviewpostyet'] = 'You cannot read other students questions in this discussion yet because you haven\'t posted';
$string['cannotviewusersposts'] = 'There are no posts made by this user that you are able to view.';
$string['cleanreadtime'] = 'Mark old posts as read hour';
$string['clicktounsubscribe'] = 'You are subscribed to this discussion. Click to unsubscribe.';
$string['clicktosubscribe'] = 'You are not subscribed to this discussion. Click to subscribe.';
$string['completiondiscussions'] = 'Student must create discussions:';
$string['completiondiscussionsgroup'] = 'Require discussions';
$string['completiondiscussionshelp'] = 'requiring discussions to complete';
$string['completionposts'] = 'Student must post discussions or replies:';
$string['completionpostsgroup'] = 'Require posts';
$string['completionpostshelp'] = 'requiring discussions or replies to complete';
$string['completionreplies'] = 'Student must post replies:';
$string['completionrepliesgroup'] = 'Require replies';
$string['completionreplieshelp'] = 'requiring replies to complete';
$string['configcleanreadtime'] = 'The hour of the day to clean old posts from the \'read\' table.';
$string['configdigestmailtime'] = 'People who choose to have emails sent to them in digest form will be emailed the digest daily. This setting controls which time of day the daily mail will be sent (the next cron that runs after this hour will send it).';
$string['configdisplaymode'] = 'The default display mode for discussions if one isn\'t set.';
$string['configenablerssfeeds'] = 'This switch will enable the possibility of RSS feeds for all cybraries.  You will still need to turn feeds on manually in the settings for each cybrary.';
$string['configenabletimedposts'] = 'Set to \'yes\' if you want to allow setting of display periods when posting a new cybrary discussion (Experimental as not yet fully tested)';
$string['configlongpost'] = 'Any post over this length (in characters not including HTML) is considered long. Posts displayed on the site front page, social format course pages, or user profiles are shortened to a natural break somewhere between the cybrary_shortpost and cybrary_longpost values.';
$string['configmanydiscussions'] = 'Maximum number of discussions shown in a cybrary per page';
$string['configmaxattachments'] = 'Default maximum number of attachments allowed per post.';
$string['configmaxbytes'] = 'Default maximum size for all cybrary attachments on the site (subject to course limits and other local settings)';
$string['configoldpostdays'] = 'Number of days old any post is considered read.';
$string['configreplytouser'] = 'When a cybrary post is mailed out, should it contain the user\'s email address so that recipients can reply personally rather than via the cybrary? Even if set to \'Yes\' users can choose in their profile to keep their email address secret.';
$string['configrsstypedefault'] = 'If RSS feeds are enabled, sets the default activity type.';
$string['configrssarticlesdefault'] = 'If RSS feeds are enabled, sets the default number of articles (either discussions or posts).';
$string['configshortpost'] = 'Any post under this length (in characters not including HTML) is considered short (see below).';
$string['configtrackingtype'] = 'Default setting for read tracking.';
$string['configtrackreadposts'] = 'Set to \'yes\' if you want to track read/unread for each user.';
$string['configusermarksread'] = 'If \'yes\', the user must manually mark a post as read. If \'no\', when the post is viewed it is marked as read.';
$string['confirmsubscribediscussion'] = 'Do you really want to subscribe to discussion \'{$a->discussion}\' in cybrary \'{$a->cybrary}\'?';
$string['confirmunsubscribediscussion'] = 'Do you really want to unsubscribe from discussion \'{$a->discussion}\' in cybrary \'{$a->cybrary}\'?';
$string['confirmsubscribe'] = 'Do you really want to subscribe to cybrary \'{$a}\'?';
$string['confirmunsubscribe'] = 'Do you really want to unsubscribe from cybrary \'{$a}\'?';
$string['couldnotadd'] = 'Could not add your post due to an unknown error';
$string['couldnotdeletereplies'] = 'Sorry, that cannot be deleted as people have already responded to it';
$string['couldnotupdate'] = 'Could not update your post due to an unknown error';
$string['crontask'] = 'Cybrary mailings and maintenance jobs';
$string['delete'] = 'Delete';
$string['deleteddiscussion'] = 'The discussion topic has been deleted';
$string['deletedpost'] = 'The post has been deleted';
$string['deletedposts'] = 'Those posts have been deleted';
$string['deletesure'] = 'Are you sure you want to delete this post?';
$string['deletesureplural'] = 'Are you sure you want to delete this post and all replies? ({$a} posts)';
$string['digestmailheader'] = 'This is your daily digest of new posts from the {$a->sitename} cybraries. To change your default cybrary email preferences, go to {$a->userprefs}.';
$string['digestmailpost'] = 'Change your cybrary digest preferences';
$string['digestmailpostlink'] = 'Change your cybrary digest preferences: {$a}';
$string['digestmailprefs'] = 'your user profile';
$string['digestmailsubject'] = '{$a}: cybrary digest';
$string['digestmailtime'] = 'Hour to send digest emails';
$string['digestsentusers'] = 'Email digests successfully sent to {$a} users.';
$string['disallowsubscribe'] = 'Subscriptions not allowed';
$string['disallowsubscription'] = 'Subscription';
$string['disallowsubscription_help'] = 'This cybrary has been configured so that you cannot subscribe to discussions.';
$string['disallowsubscribeteacher'] = 'Subscriptions not allowed (except for teachers)';
$string['discussion'] = 'Discussion';
$string['discussionmoved'] = 'This discussion has been moved to \'{$a}\'.';
$string['discussionmovedpost'] = 'This discussion has been moved to <a href="{$a->discusshref}">here</a> in the cybrary <a href="{$a->cybraryhref}">{$a->cybraryname}</a>';
$string['discussionname'] = 'Discussion name';
$string['discussionnownotsubscribed'] = '{$a->name} will NOT be notified of new posts in \'{$a->discussion}\' of \'{$a->cybrary}\'';
$string['discussionnowsubscribed'] = '{$a->name} will be notified of new posts in \'{$a->discussion}\' of \'{$a->cybrary}\'';
$string['discussionsubscribestop'] = 'I don\'t want to be notified of new posts in this discussion';
$string['discussionsubscribestart'] = 'Send me notifications of new posts in this discussion';
$string['discussionsubscription'] = 'Discussion subscription';
$string['discussionsubscription_help'] = 'Subscribing to a discussion means you will receive notifications of new posts to that discussion.';
$string['discussions'] = 'Discussions';
$string['discussionsstartedby'] = 'Discussions started by {$a}';
$string['discussionsstartedbyrecent'] = 'Discussions recently started by {$a}';
$string['discussionsstartedbyuserincourse'] = 'Discussions started by {$a->fullname} in {$a->coursename}';
$string['discussthistopic'] = 'Discuss this topic';
$string['displayend'] = 'Display end';
$string['displayend_help'] = 'This setting specifies whether a cybrary post should be hidden after a certain date. Note that administrators can always view cybrary posts.';
$string['displaymode'] = 'Display mode';
$string['displayperiod'] = 'Display period';
$string['displaystart'] = 'Display start';
$string['displaystart_help'] = 'This setting specifies whether a cybrary post should be displayed from a certain date. Note that administrators can always view cybrary posts.';
$string['displaywordcount'] = 'Display word count';
$string['displaywordcount_help'] = 'This setting specifies whether the word count of each post should be displayed or not.';
$string['eachusercybrary'] = 'Each person posts one discussion';
$string['edit'] = 'Edit';
$string['editedby'] = 'Edited by {$a->name} - original submission {$a->date}';
$string['editedpostupdated'] = '{$a}\'s post was updated';
$string['editing'] = 'Editing';
$string['eventcoursesearched'] = 'Course searched';
$string['eventdiscussioncreated'] = 'Discussion created';
$string['eventdiscussionupdated'] = 'Discussion updated';
$string['eventdiscussiondeleted'] = 'Discussion deleted';
$string['eventdiscussionmoved'] = 'Discussion moved';
$string['eventdiscussionviewed'] = 'Discussion viewed';
$string['eventdiscussionsubscriptioncreated'] = 'Discussion subscription created';
$string['eventdiscussionsubscriptiondeleted'] = 'Discussion subscription deleted';
$string['eventuserreportviewed'] = 'User report viewed';
$string['eventpostcreated'] = 'Post created';
$string['eventpostdeleted'] = 'Post deleted';
$string['eventpostupdated'] = 'Post updated';
$string['eventreadtrackingdisabled'] = 'Read tracking disabled';
$string['eventreadtrackingenabled'] = 'Read tracking enabled';
$string['eventsubscribersviewed'] = 'Subscribers viewed';
$string['eventsubscriptioncreated'] = 'Subscription created';
$string['eventsubscriptiondeleted'] = 'Subscription deleted';
$string['emaildigestcompleteshort'] = 'Complete posts';
$string['emaildigestdefault'] = 'Default ({$a})';
$string['emaildigestoffshort'] = 'No digest';
$string['emaildigestsubjectsshort'] = 'Subjects only';
$string['emaildigesttype'] = 'Email digest options';
$string['emaildigesttype_help'] = 'The type of notification that you will receive for each cybrary.

* Default - follow the digest setting found in your user profile. If you update your profile, then that change will be reflected here too;
* No digest - you will receive one e-mail per cybrary post;
* Digest - complete posts - you will receive one digest e-mail per day containing the complete contents of each cybrary post;
* Digest - subjects only - you will receive one digest e-mail per day containing just the subject of each cybrary post.
';
$string['emaildigestupdated'] = 'The e-mail digest option was changed to \'{$a->maildigesttitle}\' for the cybrary \'{$a->cybrary}\'. {$a->maildigestdescription}';
$string['emaildigestupdated_default'] = 'Your default profile setting of \'{$a->maildigesttitle}\' was used for the cybrary \'{$a->cybrary}\'. {$a->maildigestdescription}.';
$string['emaildigest_0'] = 'You will receive one e-mail per cybrary post.';
$string['emaildigest_1'] = 'You will receive one digest e-mail per day containing the complete contents of each cybrary post.';
$string['emaildigest_2'] = 'You will receive one digest e-mail per day containing the subject of each cybrary post.';
$string['emptymessage'] = 'Something was wrong with your post. Perhaps you left it blank, or the attachment was too big. Your changes have NOT been saved.';
$string['erroremptymessage'] = 'Post message cannot be empty';
$string['erroremptysubject'] = 'Post subject cannot be empty.';
$string['errorenrolmentrequired'] = 'You must be enrolled in this course to access this content';
$string['errorwhiledelete'] = 'An error occurred while deleting record.';
$string['eventassessableuploaded'] = 'Some content has been posted.';
$string['everyonecanchoose'] = 'Everyone can choose to be subscribed';
$string['everyonecannowchoose'] = 'Everyone can now choose to be subscribed';
$string['everyoneisnowsubscribed'] = 'Everyone is now subscribed to this cybrary';
$string['everyoneissubscribed'] = 'Everyone is subscribed to this cybrary';
$string['existingsubscribers'] = 'Existing subscribers';
$string['exportdiscussion'] = 'Export whole discussion to portfolio';
$string['forcedreadtracking'] = 'Allow forced read tracking';
$string['forcedreadtracking_desc'] = 'Allows cybraries to be set to forced read tracking. Will result in decreased performance for some users, particularly on courses with many cybraries and posts. When off, any cybraries previously set to Forced are treated as optional.';
$string['forcesubscribed_help'] = 'This cybrary has been configured so that you cannot unsubscribe from discussions.';
$string['forcesubscribed'] = 'This cybrary forces everyone to be subscribed';
$string['cybrary'] = 'Cybrary';
$string['cybrary:addinstance'] = 'Add a new cybrary';
$string['cybrary:addnews'] = 'Add news';
$string['cybrary:addquestion'] = 'Add question';
$string['cybrary:allowforcesubscribe'] = 'Allow force subscribe';
$string['cybraryauthorhidden'] = 'Author (hidden)';
$string['cybraryblockingalmosttoomanyposts'] = 'You are approaching the posting threshold. You have posted {$a->numposts} times in the last {$a->blockperiod} and the limit is {$a->blockafter} posts.';
$string['cybrarybodyhidden'] = 'This post cannot be viewed by you, probably because you have not posted in the discussion, the maximum editing time hasn\'t passed yet, the discussion has not started or the discussion has expired.';
$string['cybrary:canposttomygroups'] = 'Can post to all groups you have access to';
$string['cybrary:createattachment'] = 'Create attachments';
$string['cybrary:deleteanypost'] = 'Delete any posts (anytime)';
$string['cybrary:deleteownpost'] = 'Delete own posts (within deadline)';
$string['cybrary:editanypost'] = 'Edit any post';
$string['cybrary:exportdiscussion'] = 'Export whole discussion';
$string['cybrary:exportownpost'] = 'Export own post';
$string['cybrary:exportpost'] = 'Export post';
$string['cybraryintro'] = 'Description';
$string['cybrary:managesubscriptions'] = 'Manage subscriptions';
$string['cybrary:movediscussions'] = 'Move discussions';
$string['cybrary:postwithoutthrottling'] = 'Exempt from post threshold';
$string['cybraryname'] = 'Cybrary name';
$string['cybraryposts'] = 'Cybrary posts';
$string['cybrary:rate'] = 'Rate posts';
$string['cybrary:replynews'] = 'Reply to news';
$string['cybrary:replypost'] = 'Reply to posts';
$string['cybraries'] = 'Cybraries';
$string['cybrary:splitdiscussions'] = 'Split discussions';
$string['cybrary:startdiscussion'] = 'Start new discussions';
$string['cybrariesubjecthidden'] = 'Subject (hidden)';
$string['cybrarytracked'] = 'Unread posts are being tracked';
$string['cybrarytrackednot'] = 'Unread posts are not being tracked';
$string['cybrarytype'] = 'Cybrary type';
$string['cybrarytype_help'] = 'There are 5 cybrary types:

* A single simple discussion - A single discussion topic which everyone can reply to (cannot be used with separate groups)
* Each person posts one discussion - Each student can post exactly one new discussion topic, which everyone can then reply to
* Q and A cybrary - Students must first post their perspectives before viewing other students\' posts
* Standard cybrary displayed in a blog-like format - An open cybrary where anyone can start a new discussion at any time, and in which discussion topics are displayed on one page with "Discuss this topic" links
* Standard cybrary for general use - An open cybrary where anyone can start a new discussion at any time';
$string['cybrary:viewallratings'] = 'View all raw ratings given by individuals';
$string['cybrary:viewanyrating'] = 'View total ratings that anyone received';
$string['cybrary:viewdiscussion'] = 'View discussions';
$string['cybrary:viewhiddentimedposts'] = 'View hidden timed posts';
$string['cybrary:viewqandawithoutposting'] = 'Always see Q and A posts';
$string['cybrary:viewrating'] = 'View the total rating you received';
$string['cybrary:viewsubscribers'] = 'View subscribers';
$string['generalcybrary'] = 'Standard cybrary for general use';
$string['generalcybraries'] = 'General cybraries';
$string['hiddencybrarypost'] = 'Hidden cybrary post';
$string['incybrary'] = 'in {$a}';
$string['introblog'] = 'The posts in this cybrary were copied here automatically from blogs of users in this course because those blog entries are no longer available';
$string['intronews'] = 'General news and announcements';
$string['introsocial'] = 'An open cybrary for chatting about anything you want to';
$string['introteacher'] = 'A cybrary for teacher-only notes and discussion';
$string['invalidaccess'] = 'This page was not accessed correctly';
$string['invaliddiscussionid'] = 'Discussion ID was incorrect or no longer exists';
$string['invaliddigestsetting'] = 'An invalid mail digest setting was provided';
$string['invalidforcesubscribe'] = 'Invalid force subscription mode';
$string['invalidcybraryid'] = 'Cybrary ID was incorrect';
$string['invalidparentpostid'] = 'Parent post ID was incorrect';
$string['invalidpostid'] = 'Invalid post ID - {$a}';
$string['lastpost'] = 'Last post';
$string['learningcybraries'] = 'Learning cybraries';
$string['longpost'] = 'Long post';
$string['mailnow'] = 'Mail now';
$string['manydiscussions'] = 'Discussions per page';
$string['markalldread'] = 'Mark all posts in this discussion read.';
$string['markallread'] = 'Mark all posts in this cybrary read.';
$string['markread'] = 'Mark read';
$string['markreadbutton'] = 'Mark<br />read';
$string['markunread'] = 'Mark unread';
$string['markunreadbutton'] = 'Mark<br />unread';
$string['maxattachments'] = 'Maximum number of attachments';
$string['maxattachments_help'] = 'This setting specifies the maximum number of files that can be attached to a cybrary post.';
$string['maxattachmentsize'] = 'Maximum attachment size';
$string['maxattachmentsize_help'] = 'This setting specifies the largest size of file that can be attached to a cybrary post.';
$string['maxtimehaspassed'] = 'Sorry, but the maximum time for editing this post ({$a}) has passed!';
$string['message'] = 'Message';
$string['messageinboundattachmentdisallowed'] = 'Unable to post your reply, since it includes an attachment and the cybrary doesn\'t allow attachments.';
$string['messageinboundfilecountexceeded'] = 'Unable to post your reply, since it includes more than the maximum number of attachments allowed for the cybrary ({$a->cybrary->maxattachments}).';
$string['messageinboundfilesizeexceeded'] = 'Unable to post your reply, since the total attachment size ({$a->filesize}) is greater than the maximum size allowed for the cybrary ({$a->maxbytes}).';
$string['messageinboundcybraryhidden'] = 'Unable to post your reply, since the cybrary is currently unavailable.';
$string['messageinboundnopostcybrary'] = 'Unable to post your reply, since you do not have permission to post in the {$a->cybrary->name} cybrary.';
$string['messageinboundthresholdhit'] = 'Unable to post your reply.  You have exceeded the posting threshold set for this cybrary';
$string['messageprovider:digests'] = 'Subscribed cybrary digests';
$string['messageprovider:posts'] = 'Subscribed cybrary posts';
$string['missingsearchterms'] = 'The following search terms occur only in the HTML markup of this message:';
$string['modeflatnewestfirst'] = 'Display replies flat, with newest first';
$string['modeflatoldestfirst'] = 'Display replies flat, with oldest first';
$string['modenested'] = 'Display replies in nested form';
$string['modethreaded'] = 'Display replies in threaded form';
$string['modulename'] = 'Cybrary';
$string['modulename_help'] = 'The cybrary activity module enables participants to have asynchronous discussions i.e. discussions that take place over an extended period of time.

There are several cybrary types to choose from, such as a standard cybrary where anyone can start a new discussion at any time; a cybrary where each student can post exactly one discussion; or a question and answer cybrary where students must first post before being able to view other students\' posts. A teacher can allow files to be attached to cybrary posts. Attached images are displayed in the cybrary post.

Participants can subscribe to a cybrary to receive notifications of new cybrary posts. A teacher can set the subscription mode to optional, forced or auto, or prevent subscription completely. If required, students can be blocked from posting more than a given number of posts in a given time period; this can prevent individuals from dominating discussions.

Cybrary posts can be rated by teachers or students (peer evaluation). Ratings can be aggregated to form a final grade which is recorded in the gradebook.

Cybraries have many uses, such as

* A social space for students to get to know each other
* For course announcements (using a news cybrary with forced subscription)
* For discussing course content or reading materials
* For continuing online an issue raised previously in a face-to-face session
* For teacher-only discussions (using a hidden cybrary)
* A help centre where tutors and students can give advice
* A one-on-one support area for private student-teacher communications (using a cybrary with separate groups and with one student per group)
* For extension activities, for example ‘brain teasers’ for students to ponder and suggest solutions to';
$string['modulename_link'] = 'mod/cybrary/view';
$string['modulenameplural'] = 'Cybraries';
$string['more'] = 'more';
$string['movedmarker'] = '(Moved)';
$string['movethisdiscussionto'] = 'Move this discussion to ...';
$string['mustprovidediscussionorpost'] = 'You must provide either a discussion id or post id to export';
$string['myprofileownpost'] = 'My cybrary posts';
$string['myprofileowndis'] = 'My cybrary discussions';
$string['myprofileotherdis'] = 'Cybrary discussions';
$string['namenews'] = 'News cybrary';
$string['namenews_help'] = 'The news cybrary is a special cybrary for announcements that is automatically created when a course is created. A course can have only one news cybrary. Only teachers and administrators can post in the news cybrary. The "Latest news" block will display recent discussions from the news cybrary.';
$string['namesocial'] = 'Social cybrary';
$string['nameteacher'] = 'Teacher cybrary';
$string['nextdiscussiona'] = 'Next discussion: {$a}';
$string['newcybraryposts'] = 'New cybrary posts';
$string['noattachments'] = 'There are no attachments to this post';
$string['nodiscussions'] = 'There are no discussion topics yet in this cybrary';
$string['nodiscussionsstartedby'] = '{$a} has not started any discussions';
$string['nodiscussionsstartedbyyou'] = 'You haven\'t started any discussions yet';
$string['noguestpost'] = 'Sorry, guests are not allowed to post.';
$string['noguestsubscribe'] = 'Sorry, guests are not allowed to subscribe.';
$string['noguesttracking'] = 'Sorry, guests are not allowed to set tracking options.';
$string['nomorepostscontaining'] = 'No more posts containing \'{$a}\' were found';
$string['nonews'] = 'No news has been posted yet';
$string['noonecansubscribenow'] = 'Subscriptions are now disallowed';
$string['nopermissiontosubscribe'] = 'You do not have the permission to view cybrary subscribers';
$string['nopermissiontoview'] = 'You do not have permissions to view this post';
$string['nopostcybrary'] = 'Sorry, you are not allowed to post to this cybrary';
$string['noposts'] = 'No posts';
$string['nopostsmadebyuser'] = '{$a} has made no posts';
$string['nopostsmadebyyou'] = 'You haven\'t made any posts';
$string['noquestions'] = 'There are no questions yet in this cybrary';
$string['nosubscribers'] = 'There are no subscribers yet for this cybrary';
$string['notsubscribed'] = 'Subscribe';
$string['notexists'] = 'Discussion no longer exists';
$string['nothingnew'] = 'Nothing new for {$a}';
$string['notingroup'] = 'Sorry, but you need to be part of a group to see this cybrary.';
$string['notinstalled'] = 'The cybrary module is not installed';
$string['notpartofdiscussion'] = 'This post is not part of a discussion!';
$string['notrackcybrary'] = 'Don\'t track unread posts';
$string['noviewdiscussionspermission'] = 'You do not have the permission to view discussions in this cybrary';
$string['nowallsubscribed'] = 'All cybraries in {$a} are subscribed.';
$string['nowallunsubscribed'] = 'All cybraries in {$a} are not subscribed.';
$string['nownotsubscribed'] = '{$a->name} will NOT be notified of new posts in \'{$a->cybrary}\'';
$string['nownottracking'] = '{$a->name} is no longer tracking \'{$a->cybrary}\'.';
$string['nowsubscribed'] = '{$a->name} will be notified of new posts in \'{$a->cybrary}\'';
$string['nowtracking'] = '{$a->name} is now tracking \'{$a->cybrary}\'.';
$string['numposts'] = '{$a} posts';
$string['olderdiscussions'] = 'Older discussions';
$string['oldertopics'] = 'Older topics';
$string['oldpostdays'] = 'Read after days';
$string['overviewnumpostssince'] = '{$a} posts since last login';
$string['overviewnumunread'] = '{$a} total unread';
$string['page-mod-cybrary-x'] = 'Any cybrary module page';
$string['page-mod-cybrary-view'] = 'Cybrary module main page';
$string['page-mod-cybrary-discuss'] = 'Cybrary module discussion thread page';
$string['parent'] = 'Show parent';
$string['parentofthispost'] = 'Parent of this post';
$string['posttomygroups'] = 'Post a copy to all groups';
$string['posttomygroups_help'] = 'Posts a copy of this message to all groups you have access to. Participants in groups you do not have access to will not see this post';
$string['prevdiscussiona'] = 'Previous discussion: {$a}';
$string['pluginadministration'] = 'Cybrary administration';
$string['pluginname'] = 'Cybrary';
$string['postadded'] = '<p>Your post was successfully added.</p> <p>You have {$a} to edit it if you want to make any changes.</p>';
$string['postaddedsuccess'] = 'Your post was successfully added.';
$string['postaddedtimeleft'] = 'You have {$a} to edit it if you want to make any changes.';
$string['postbymailsuccess'] = 'Congratulations, your cybrary post with subject "{$a->subject}" was successfully added. You can view it at {$a->discussionurl}.';
$string['postbymailsuccess_html'] = 'Congratulations, your <a href="{$a->discussionurl}">cybrary post</a> with subject "{$a->subject}" was successfully posted.';
$string['postbyuser'] = '{$a->post} by {$a->user}';
$string['postincontext'] = 'See this post in context';
$string['postmailinfo'] = 'This is a copy of a message posted on the {$a} website.

To reply click on this link:';
$string['postmailinfolink'] = 'This is a copy of a message posted in {$a->coursename}.

To reply click on this link: {$a->replylink}';
$string['postmailnow'] = '<p>This post will be mailed out immediately to all cybrary subscribers.</p>';
$string['postmailsubject'] = '{$a->courseshortname}: {$a->subject}';
$string['postrating1'] = 'Mostly separate knowing';
$string['postrating2'] = 'Separate and connected';
$string['postrating3'] = 'Mostly connected knowing';
$string['posts'] = 'Posts';
$string['postsmadebyuser'] = 'Posts made by {$a}';
$string['postsmadebyuserincourse'] = 'Posts made by {$a->fullname} in {$a->coursename}';
$string['posttocybrary'] = 'Post to cybrary';
$string['postupdated'] = 'Your post was updated';
$string['potentialsubscribers'] = 'Potential subscribers';
$string['processingdigest'] = 'Processing email digest for user {$a}';
$string['processingpost'] = 'Processing post {$a}';
$string['prune'] = 'Split';
$string['prunedpost'] = 'A new discussion has been created from that post';
$string['pruneheading'] = 'Split the discussion and move this post to a new discussion';
$string['qandacybrary'] = 'Q and A cybrary';
$string['qandanotify'] = 'This is a question and answer cybrary. In order to see other responses to these questions, you must first post your answer';
$string['re'] = 'Re:';
$string['readtherest'] = 'Read the rest of this topic';
$string['replies'] = 'Replies';
$string['repliesmany'] = '{$a} replies so far';
$string['repliesone'] = '{$a} reply so far';
$string['reply'] = 'Reply';
$string['replycybrary'] = 'Reply to cybrary';
$string['replytopostbyemail'] = 'You can reply to this via email.';
$string['replytouser'] = 'Use email address in reply';
$string['reply_handler'] = 'Reply to cybrary posts via email';
$string['reply_handler_name'] = 'Reply to cybrary posts';
$string['resetcybraries'] = 'Delete posts from';
$string['resetcybrariesall'] = 'Delete all posts';
$string['resetdigests'] = 'Delete all per-user cybrary digest preferences';
$string['resetsubscriptions'] = 'Delete all cybrary subscriptions';
$string['resettrackprefs'] = 'Delete all cybrary tracking preferences';
$string['rsssubscriberssdiscussions'] = 'RSS feed of discussions';
$string['rsssubscriberssposts'] = 'RSS feed of posts';
$string['rssarticles'] = 'Number of RSS recent articles';
$string['rssarticles_help'] = 'This setting specifies the number of articles (either discussions or posts) to include in the RSS feed. Between 5 and 20 generally acceptable.';
$string['rsstype'] = 'RSS feed for this activity';
$string['rsstype_help'] = 'To enable the RSS feed for this activity, select either discussions or posts to be included in the feed.';
$string['rsstypedefault'] = 'RSS feed type';
$string['search'] = 'Search';
$string['searchdatefrom'] = 'Posts must be newer than this';
$string['searchdateto'] = 'Posts must be older than this';
$string['searchcybraryintro'] = 'Please enter search terms into one or more of the following fields:';
$string['searchcybraries'] = 'Search cybraries';
$string['searchfullwords'] = 'These words should appear as whole words';
$string['searchnotwords'] = 'These words should NOT be included';
$string['searcholderposts'] = 'Search older posts...';
$string['searchphrase'] = 'This exact phrase must appear in the post';
$string['searchresults'] = 'Search results';
$string['searchsubject'] = 'These words should be in the subject';
$string['searchuser'] = 'This name should match the author';
$string['searchuserid'] = 'The Moodle ID of the author';
$string['searchwhichcybraries'] = 'Choose which cybraries to search';
$string['searchwords'] = 'These words can appear anywhere in the post';
$string['seeallposts'] = 'See all posts made by this user';
$string['shortpost'] = 'Short post';
$string['showsubscribers'] = 'Show/edit current subscribers';
$string['singlecybrary'] = 'A single simple discussion';
$string['smallmessage'] = '{$a->user} posted in {$a->cybraryname}';
$string['startedby'] = 'Started by';
$string['subject'] = 'Subject';
$string['subscribe'] = 'Subscribe to this cybrary';
$string['subscribediscussion'] = 'Subscribe to this discussion';
$string['subscribeall'] = 'Subscribe everyone to this cybrary';
$string['subscribeenrolledonly'] = 'Sorry, only enrolled users are allowed to subscribe to cybrary post notifications.';
$string['subscribed'] = 'Subscribed';
$string['subscribenone'] = 'Unsubscribe everyone from this cybrary';
$string['subscribers'] = 'Subscribers';
$string['subscriberstowithcount'] = 'Subscribers to "{$a->name}" ({$a->count})';
$string['subscribestart'] = 'Send me notifications of new posts in this cybrary';
$string['subscribestop'] = 'I don\'t want to be notified of new posts in this cybrary';
$string['subscription'] = 'Subscription';
$string['subscription_help'] = 'If you are subscribed to a cybrary it means you will receive notification of new cybrary posts. Usually you can choose whether you wish to be subscribed, though sometimes subscription is forced so that everyone receives notifications.';
$string['subscriptionandtracking'] = 'Subscription and tracking';
$string['subscriptionmode'] = 'Subscription mode';
$string['subscriptionmode_help'] = 'When a participant is subscribed to a cybrary it means they will receive cybrary post notifications. There are 4 subscription mode options:

* Optional subscription - Participants can choose whether to be subscribed
* Forced subscription - Everyone is subscribed and cannot unsubscribe
* Auto subscription - Everyone is subscribed initially but can choose to unsubscribe at any time
* Subscription disabled - Subscriptions are not allowed

Note: Any subscription mode changes will only affect users who enrol in the course in the future, and not existing users.';
$string['subscriptionoptional'] = 'Optional subscription';
$string['subscriptionforced'] = 'Forced subscription';
$string['subscriptionauto'] = 'Auto subscription';
$string['subscriptiondisabled'] = 'Subscription disabled';
$string['subscriptions'] = 'Subscriptions';
$string['thiscybraryisthrottled'] = 'This cybrary has a limit to the number of cybrary postings you can make in a given time period - this is currently set at {$a->blockafter} posting(s) in {$a->blockperiod}';
$string['timedhidden'] = 'Timed status: Hidden from students';
$string['timedposts'] = 'Timed posts';
$string['timedvisible'] = 'Timed status: Visible to all users';
$string['timestartenderror'] = 'Display end date cannot be earlier than the start date';
$string['trackcybrary'] = 'Track unread posts';
$string['tracking'] = 'Track';
$string['trackingoff'] = 'Off';
$string['trackingon'] = 'Forced';
$string['trackingoptional'] = 'Optional';
$string['trackingtype'] = 'Read tracking';
$string['trackingtype_help'] = 'If enabled, participants can track read and unread posts in the cybrary and in discussions. There are three options:

* Optional - Participants can choose whether to turn tracking on or off via a link in the administration block. Cybrary tracking must also be enabled in the user\'s profile settings.
* Forced - Tracking is always on, regardless of user setting. Available depending on administrative setting.
* Off - Read and unread posts are not tracked.';
$string['unread'] = 'Unread';
$string['unreadposts'] = 'Unread posts';
$string['unreadpostsnumber'] = '{$a} unread posts';
$string['unreadpostsone'] = '1 unread post';
$string['unsubscribe'] = 'Unsubscribe from this cybrary';
$string['unsubscribelink'] = 'Unsubscribe from this cybrary: {$a}';
$string['unsubscribediscussion'] = 'Unsubscribe from this discussion';
$string['unsubscribediscussionlink'] = 'Unsubscribe from this discussion: {$a}';
$string['unsubscribeall'] = 'Unsubscribe from all cybraries';
$string['unsubscribeallconfirm'] = 'You are currently subscribed to {$a->cybraries} cybraries, and {$a->discussions} discussions. Do you really want to unsubscribe from all cybraries and discussions, and disable discussion auto-subscription?';
$string['unsubscribeallconfirmcybraries'] = 'You are currently subscribed to {$a->cybraries} cybraries. Do you really want to unsubscribe from all cybraries and disable discussion auto-subscription?';
$string['unsubscribeallconfirmdiscussions'] = 'You are currently subscribed to {$a->discussions} discussions. Do you really want to unsubscribe from all discussions and disable discussion auto-subscription?';
$string['unsubscribealldone'] = 'All optional cybrary subscriptions were removed. You will still receive notifications from cybraries with forced subscription. To manage cybrary notifications go to Messaging in My Profile Settings.';
$string['unsubscribeallempty'] = 'You are not subscribed to any cybraries. To disable all notifications from this server go to Messaging in My Profile Settings.';
$string['unsubscribed'] = 'Unsubscribed';
$string['unsubscribeshort'] = 'Unsubscribe';
$string['usermarksread'] = 'Manual message read marking';
$string['viewalldiscussions'] = 'View all discussions';
$string['warnafter'] = 'Post threshold for warning';
$string['warnafter_help'] = 'Students can be warned as they approach the maximum number of posts allowed in a given period. This setting specifies after how many posts they are warned. Users with the capability mod/cybrary:postwithoutthrottling are exempt from post limits.';
$string['warnformorepost'] = 'Warning! There is more than one discussion in this cybrary - using the most recent';
$string['yournewquestion'] = 'Your new question';
$string['yournewtopic'] = 'Your new discussion topic';
$string['yourreply'] = 'Your reply';

// Deprecated since Moodle 3.0.
$string['subscribersto'] = 'Subscribers to "{$a->name}"';

$string['clicktoopen'] = 'Click {$a} link to open resource.';
$string['configdisplayoptions'] = 'Select all options that should be available, existing settings are not modified. Hold CTRL key to select multiple fields.';
$string['configframesize'] = 'When a web page or an uploaded file is displayed within a frame, this value is the height (in pixels) of the top frame (which contains the navigation).';
$string['configrolesinparams'] = 'Enable if you want to include localized role names in list of available parameter variables.';
$string['configsecretphrase'] = 'This secret phrase is used to produce encrypted code value that can be sent to some servers as a parameter.  The encrypted code is produced by an md5 value of the current user IP address concatenated with your secret phrase. ie code = md5(IP.secretphrase). Please note that this is not reliable because IP address may change and is often shared by different computers.';
$string['contentheader'] = 'Content';
$string['createurl'] = 'Create a URL';
$string['displayoptions'] = 'Available display options';
$string['displayselect'] = 'Display';
$string['displayselect_help'] = 'This setting, together with the URL file type and whether the browser allows embedding, determines how the URL is displayed. Options may include:

* Automatic - The best display option for the URL is selected automatically
* Embed - The URL is displayed within the page below the navigation bar together with the URL description and any blocks
* Open - Only the URL is displayed in the browser window
* In pop-up - The URL is displayed in a new browser window without menus or an address bar
* In frame - The URL is displayed within a frame below the navigation bar and URL description
* New window - The URL is displayed in a new browser window with menus and an address bar';
$string['displayselectexplain'] = 'Choose display type, unfortunately not all types are suitable for all URLs.';
$string['externalurl'] = 'External URL';
$string['framesize'] = 'Frame height';
$string['invalidstoredurl'] = 'Cannot display this resource, URL is invalid.';
$string['chooseavariable'] = 'Choose a variable...';
$string['invalidurl'] = 'Entered URL is invalid';
$string['page-mod-cybrary-x'] = 'Any Cybrary module page';
$string['parameterinfo'] = '&amp;parameter=variable';
$string['parametersheader'] = 'URL variables';
$string['parametersheader_help'] = 'Some internal Moodle variables may be automatically appended to the URL. Type your name for the parameter into each text box(es) and then select the required matching variable.';
$string['pluginadministration'] = 'Cybrary module administration';
$string['pluginname'] = 'Cybrary';
$string['popupheight'] = 'Pop-up height (in pixels)';
$string['popupheightexplain'] = 'Specifies default height of popup windows.';
$string['popupwidth'] = 'Pop-up width (in pixels)';
$string['popupwidthexplain'] = 'Specifies default width of popup windows.';
$string['printintro'] = 'Display URL description';
$string['printintroexplain'] = 'Display URL description below content? Some display types may not display description even if enabled.';
$string['rolesinparams'] = 'Include role names in parameters';
$string['serverurl'] = 'Server URL';
$string['cybrary:view'] = 'View Cybrary';
