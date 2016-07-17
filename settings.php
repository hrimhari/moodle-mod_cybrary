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
 * @copyright  2009 Petr Skoda (http://skodak.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/cybrary/lib.php');
    require_once("$CFG->libdir/resourcelib.php");

    $displayoptions = resourcelib_get_displayoptions(array(RESOURCELIB_DISPLAY_AUTO,
                                                           RESOURCELIB_DISPLAY_EMBED,
                                                           RESOURCELIB_DISPLAY_FRAME,
                                                           RESOURCELIB_DISPLAY_OPEN,
                                                           RESOURCELIB_DISPLAY_NEW,
                                                           RESOURCELIB_DISPLAY_POPUP,
                                                          ));
    $defaultdisplayoptions = array(RESOURCELIB_DISPLAY_AUTO,
                                   RESOURCELIB_DISPLAY_EMBED,
                                   RESOURCELIB_DISPLAY_OPEN,
                                   RESOURCELIB_DISPLAY_POPUP,
                                  );

    //--- general settings -----------------------------------------------------------------------------------
    $settings->add(new admin_setting_configtext('cybrary_framesize',
        get_string('framesize', 'cybrary'), get_string('configframesize', 'cybrary'), 130, PARAM_INT));
    $settings->add(new admin_setting_configpasswordunmask('cybrary_secretphrase', get_string('password'),
        get_string('configsecretphrase', 'cybrary'), ''));
    $settings->add(new admin_setting_configcheckbox('cybrary_rolesinparams',
        get_string('rolesinparams', 'cybrary'), get_string('configrolesinparams', 'cybrary'), false));
    $settings->add(new admin_setting_configmultiselect('cybrary_displayoptions',
        get_string('displayoptions', 'cybrary'), get_string('configdisplayoptions', 'cybrary'),
        $defaultdisplayoptions, $displayoptions));

    //--- modedit defaults -----------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('urlmodeditdefaults', get_string('modeditdefaults', 'admin'), get_string('condifmodeditdefaults', 'admin')));

    $settings->add(new admin_setting_configcheckbox('cybrary_printintro',
        get_string('printintro', 'cybrary'), get_string('printintroexplain', 'cybrary'), 1));
    $settings->add(new admin_setting_configselect('cybrary_display',
        get_string('displayselect', 'cybrary'), get_string('displayselectexplain', 'cybrary'), RESOURCELIB_DISPLAY_AUTO, $displayoptions));
    $settings->add(new admin_setting_configtext('cybrary_popupwidth',
        get_string('popupwidth', 'cybrary'), get_string('popupwidthexplain', 'cybrary'), 620, PARAM_INT, 7));
    $settings->add(new admin_setting_configtext('cybrary_popupheight',
        get_string('popupheight', 'cybrary'), get_string('popupheightexplain', 'cybrary'), 450, PARAM_INT, 7));


    $settings->add(new admin_setting_configselect('cybrary_displaymode', get_string('displaymode', 'cybrary'),
                       get_string('configdisplaymode', 'cybrary'), CYBRARY_MODE_NESTED, cybrary_get_layout_modes()));

    $settings->add(new admin_setting_configcheckbox('cybrary_replytouser', get_string('replytouser', 'cybrary'),
                       get_string('configreplytouser', 'cybrary'), 1));

    // Less non-HTML characters than this is short
    $settings->add(new admin_setting_configtext('cybrary_shortpost', get_string('shortpost', 'cybrary'),
                       get_string('configshortpost', 'cybrary'), 300, PARAM_INT));

    // More non-HTML characters than this is long
    $settings->add(new admin_setting_configtext('cybrary_longpost', get_string('longpost', 'cybrary'),
                       get_string('configlongpost', 'cybrary'), 600, PARAM_INT));

    // Number of discussions on a page
    $settings->add(new admin_setting_configtext('cybrary_manydiscussions', get_string('manydiscussions', 'cybrary'),
                       get_string('configmanydiscussions', 'cybrary'), 100, PARAM_INT));

    if (isset($CFG->maxbytes)) {
        $maxbytes = 0;
        if (isset($CFG->cybrary_maxbytes)) {
            $maxbytes = $CFG->cybrary_maxbytes;
        }
        $settings->add(new admin_setting_configselect('cybrary_maxbytes', get_string('maxattachmentsize', 'cybrary'),
                           get_string('configmaxbytes', 'cybrary'), 512000, get_max_upload_sizes($CFG->maxbytes, 0, 0, $maxbytes)));
    }

    // Default number of attachments allowed per post in all cybraries
    $settings->add(new admin_setting_configtext('cybrary_maxattachments', get_string('maxattachments', 'cybrary'),
                       get_string('configmaxattachments', 'cybrary'), 9, PARAM_INT));

    // Default Read Tracking setting.
    $options = array();
    $options[CYBRARY_TRACKING_OPTIONAL] = get_string('trackingoptional', 'cybrary');
    $options[CYBRARY_TRACKING_OFF] = get_string('trackingoff', 'cybrary');
    $options[CYBRARY_TRACKING_FORCED] = get_string('trackingon', 'cybrary');
    $settings->add(new admin_setting_configselect('cybrary_trackingtype', get_string('trackingtype', 'cybrary'),
                       get_string('configtrackingtype', 'cybrary'), CYBRARY_TRACKING_OPTIONAL, $options));

    // Default whether user needs to mark a post as read
    $settings->add(new admin_setting_configcheckbox('cybrary_trackreadposts', get_string('trackcybrary', 'cybrary'),
                       get_string('configtrackreadposts', 'cybrary'), 1));

    // Default whether user needs to mark a post as read.
    $settings->add(new admin_setting_configcheckbox('cybrary_allowforcedreadtracking', get_string('forcedreadtracking', 'cybrary'),
                       get_string('forcedreadtracking_desc', 'cybrary'), 0));

    // Default number of days that a post is considered old
    $settings->add(new admin_setting_configtext('cybrary_oldpostdays', get_string('oldpostdays', 'cybrary'),
                       get_string('configoldpostdays', 'cybrary'), 14, PARAM_INT));

    // Default whether user needs to mark a post as read
    $settings->add(new admin_setting_configcheckbox('cybrary_usermarksread', get_string('usermarksread', 'cybrary'),
                       get_string('configusermarksread', 'cybrary'), 0));

    $options = array();
    for ($i = 0; $i < 24; $i++) {
        $options[$i] = sprintf("%02d",$i);
    }
    // Default time (hour) to execute 'clean_read_records' cron
    $settings->add(new admin_setting_configselect('cybrary_cleanreadtime', get_string('cleanreadtime', 'cybrary'),
                       get_string('configcleanreadtime', 'cybrary'), 2, $options));

    // Default time (hour) to send digest email
    $settings->add(new admin_setting_configselect('digestmailtime', get_string('digestmailtime', 'cybrary'),
                       get_string('configdigestmailtime', 'cybrary'), 17, $options));

    if (empty($CFG->enablerssfeeds)) {
        $options = array(0 => get_string('rssglobaldisabled', 'admin'));
        $str = get_string('configenablerssfeeds', 'cybrary').'<br />'.get_string('configenablerssfeedsdisabled2', 'admin');

    } else {
        $options = array(0=>get_string('no'), 1=>get_string('yes'));
        $str = get_string('configenablerssfeeds', 'cybrary');
    }
    $settings->add(new admin_setting_configselect('cybrary_enablerssfeeds', get_string('enablerssfeeds', 'admin'),
                       $str, 0, $options));

    if (!empty($CFG->enablerssfeeds)) {
        $options = array(
            0 => get_string('none'),
            1 => get_string('discussions', 'cybrary'),
            2 => get_string('posts', 'cybrary')
        );
        $settings->add(new admin_setting_configselect('cybrary_rsstype', get_string('rsstypedefault', 'cybrary'),
                get_string('configrsstypedefault', 'cybrary'), 0, $options));

        $options = array(
            0  => '0',
            1  => '1',
            2  => '2',
            3  => '3',
            4  => '4',
            5  => '5',
            10 => '10',
            15 => '15',
            20 => '20',
            25 => '25',
            30 => '30',
            40 => '40',
            50 => '50'
        );
        $settings->add(new admin_setting_configselect('cybrary_rssarticles', get_string('rssarticles', 'cybrary'),
                get_string('configrssarticlesdefault', 'cybrary'), 0, $options));
    }

    $settings->add(new admin_setting_configcheckbox('cybrary_enabletimedposts', get_string('timedposts', 'cybrary'),
                       get_string('configenabletimedposts', 'cybrary'), 0));
}

