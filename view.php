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

    require_once('../../config.php');
    require_once('lib.php');
    require_once($CFG->libdir.'/completionlib.php');

    $id          = optional_param('id', 0, PARAM_INT);       // Course Module ID
    $f           = optional_param('f', 0, PARAM_INT);        // Cybrary ID
    $mode        = optional_param('mode', 0, PARAM_INT);     // Display mode (for single cybrary)
    $showall     = optional_param('showall', '', PARAM_INT); // show all discussions on one page
    $changegroup = optional_param('group', -1, PARAM_INT);   // choose the current group
    $page        = optional_param('page', 0, PARAM_INT);     // which page to show
    $search      = optional_param('search', '', PARAM_CLEAN);// search string
    $redirect    = optional_param('redirect', 0, PARAM_BOOL);


    $params = array();
    if ($id) {
        $params['id'] = $id;
    } else {
        $params['f'] = $f;
    }
    if ($page) {
        $params['page'] = $page;
    }
    if ($search) {
        $params['search'] = $search;
    }
    $PAGE->set_url('/mod/cybrary/view.php', $params);

    if ($id) {
        if (! $cm = get_coursemodule_from_id('cybrary', $id)) {
            print_error('invalidcoursemodule');
        }
        if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
            print_error('coursemisconf');
        }
        if (! $cybrary = $DB->get_record("cybrary", array("id" => $cm->instance))) {
            print_error('invalidcybraryid', 'cybrary');
        }
        if ($cybrary->type == 'single') {
            $PAGE->set_pagetype('mod-cybrary-discuss');
        }
        // move require_course_login here to use forced language for course
        // fix for MDL-6926
        require_course_login($course, true, $cm);
        $strcybraries = get_string("modulenameplural", "cybrary");
        $strcybrary = get_string("modulename", "cybrary");
    } else if ($f) {

        if (! $cybrary = $DB->get_record("cybrary", array("id" => $f))) {
            print_error('invalidcybraryid', 'cybrary');
        }
        if (! $course = $DB->get_record("course", array("id" => $cybrary->course))) {
            print_error('coursemisconf');
        }

        if (!$cm = get_coursemodule_from_instance("cybrary", $cybrary->id, $course->id)) {
            print_error('missingparameter');
        }
        // move require_course_login here to use forced language for course
        // fix for MDL-6926
        require_course_login($course, true, $cm);
        $strcybraries = get_string("modulenameplural", "cybrary");
        $strcybrary = get_string("modulename", "cybrary");
    } else {
        print_error('missingparameter');
    }

    if (!$PAGE->button) {
        $PAGE->set_button(cybrary_search_form($course, $search));
    }

    $context = context_module::instance($cm->id);
    $PAGE->set_context($context);

    if (!empty($CFG->enablerssfeeds) && !empty($CFG->cybrary_enablerssfeeds) && $cybrary->rsstype && $cybrary->rssarticles) {
        require_once("$CFG->libdir/rsslib.php");

        $rsstitle = format_string($course->shortname, true, array('context' => context_course::instance($course->id))) . ': ' . format_string($cybrary->name);
        rss_add_http_header($context, 'mod_cybrary', $cybrary, $rsstitle);
    }

/// Print header.

    $PAGE->set_title($cybrary->name);
    $PAGE->add_body_class('cybrarytype-'.$cybrary->type);
    $PAGE->set_heading($course->fullname);

/// Some capability checks.
    if (empty($cm->visible) and !has_capability('moodle/course:viewhiddenactivities', $context)) {
        notice(get_string("activityiscurrentlyhidden"));
    }

    if (!has_capability('mod/cybrary:viewdiscussion', $context)) {
        notice(get_string('noviewdiscussionspermission', 'cybrary'));
    }

    // Mark viewed and trigger the course_module_viewed event.
    cybrary_view($cybrary, $course, $cm, $context);

    echo $OUTPUT->header();

    echo $OUTPUT->heading(format_string($cybrary->name), 2);

// Make sure URL exists before generating output - some older sites may contain empty urls
// Do not use PARAM_URL here, it is too strict and does not support general URIs!
    $exturl = trim($cybrary->externalurl);
    if (empty($exturl) or $exturl === 'http://') {
        notice(get_string('invalidstoredurl', 'cybrary'), new moodle_url('/course/view.php', array('id'=>$cm->course)));
        die;
    }
    unset($exturl);
    
    $displaytype = url_get_final_display_type($cybrary);
    if ($displaytype == RESOURCELIB_DISPLAY_OPEN) {
        // For 'open' links, we always redirect to the content - except if the user
        // just chose 'save and display' from the form then that would be confusing
        if (strpos(get_local_referer(false), 'modedit.php') === false) {
            $redirect = true;
        }
    }

    if (!empty($cybrary->intro) && $cybrary->type != 'single' && $cybrary->type != 'teacher') {
        echo $OUTPUT->box(format_module_intro('cybrary', $cybrary, $cm->id), 'generalbox', 'intro');
    }

    if ($redirect) {
        // coming from course page or url index page,
        // the redirection is needed for completion tracking and logging
        $fullurl = str_replace('&amp;', '&', url_get_full_url($cybrary, $cm, $course));
    
        if (!course_get_format($course)->has_view_page()) {
            // If course format does not have a view page, add redirection delay with a link to the edit page.
            // Otherwise teacher is redirected to the external URL without any possibility to edit activity or course settings.
            $editurl = null;
            if (has_capability('moodle/course:manageactivities', $context)) {
                $editurl = new moodle_url('/course/modedit.php', array('update' => $cm->id));
                $edittext = get_string('editthisactivity');
            } else if (has_capability('moodle/course:update', $context->get_course_context())) {
                $editurl = new moodle_url('/course/edit.php', array('id' => $course->id));
                $edittext = get_string('editcoursesettings');
            }
            if ($editurl) {
                redirect($fullurl, html_writer::link($editurl, $edittext)."<br/>".
                        get_string('pageshouldredirect'), 10);
            }
        }
        redirect($fullurl);
    }

    switch ($displaytype) {
        case RESOURCELIB_DISPLAY_EMBED:
            url_display_embed($cybrary, $cm, $course);
            break;
        case RESOURCELIB_DISPLAY_FRAME:
            url_display_frame($cybrary, $cm, $course);
            break;
        default:
            url_print_workaround($cybrary, $cm, $course);
            break;
    }

/// find out current groups mode
    groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/cybrary/view.php?id=' . $cm->id);

    $SESSION->fromdiscussion = qualified_me();   // Return here if we post or set subscription etc


/// Print settings and things across the top

    // If it's a simple single discussion cybrary, we need to print the display
    // mode control.
    if ($cybrary->type == 'single') {
        $discussion = NULL;
        $discussions = $DB->get_records('cybrary_discussions', array('cybrary'=>$cybrary->id), 'timemodified ASC');
        if (!empty($discussions)) {
            $discussion = array_pop($discussions);
        }
        if ($discussion) {
            if ($mode) {
                set_user_preference("cybrary_displaymode", $mode);
            }
            $displaymode = get_user_preferences("cybrary_displaymode", $CFG->cybrary_displaymode);
            cybrary_print_mode_form($cybrary->id, $displaymode, $cybrary->type);
        }
    }

    if (!empty($cybrary->blockafter) && !empty($cybrary->blockperiod)) {
        $a = new stdClass();
        $a->blockafter = $cybrary->blockafter;
        $a->blockperiod = get_string('secondstotime'.$cybrary->blockperiod);
        echo $OUTPUT->notification(get_string('thiscybraryisthrottled', 'cybrary', $a));
    }

    if ($cybrary->type == 'qanda' && !has_capability('moodle/course:manageactivities', $context)) {
        echo $OUTPUT->notification(get_string('qandanotify','cybrary'));
    }

    switch ($cybrary->type) {
        case 'single':
            if (!empty($discussions) && count($discussions) > 1) {
                echo $OUTPUT->notification(get_string('warnformorepost', 'cybrary'));
            }
            if (! $post = cybrary_get_post_full($discussion->firstpost)) {
                print_error('cannotfindfirstpost', 'cybrary');
            }
            if ($mode) {
                set_user_preference("cybrary_displaymode", $mode);
            }

            $canreply    = cybrary_user_can_post($cybrary, $discussion, $USER, $cm, $course, $context);
            $canrate     = has_capability('mod/cybrary:rate', $context);
            $displaymode = get_user_preferences("cybrary_displaymode", $CFG->cybrary_displaymode);

            echo '&nbsp;'; // this should fix the floating in FF
            cybrary_print_discussion($course, $cm, $cybrary, $discussion, $post, $displaymode, $canreply, $canrate);
            break;

        case 'eachuser':
            echo '<p class="mdl-align">';
            if (cybrary_user_can_post_discussion($cybrary, null, -1, $cm)) {
                print_string("allowsdiscussions", "cybrary");
            } else {
                echo '&nbsp;';
            }
            echo '</p>';
            if (!empty($showall)) {
                cybrary_print_latest_discussions($course, $cybrary, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                cybrary_print_latest_discussions($course, $cybrary, -1, 'header', '', -1, -1, $page, $CFG->cybrary_manydiscussions, $cm);
            }
            break;

        case 'teacher':
            if (!empty($showall)) {
                cybrary_print_latest_discussions($course, $cybrary, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                cybrary_print_latest_discussions($course, $cybrary, -1, 'header', '', -1, -1, $page, $CFG->cybrary_manydiscussions, $cm);
            }
            break;

        case 'blog':
            echo '<br />';
            if (!empty($showall)) {
                cybrary_print_latest_discussions($course, $cybrary, 0, 'plain', 'p.created DESC', -1, -1, -1, 0, $cm);
            } else {
                cybrary_print_latest_discussions($course, $cybrary, -1, 'plain', 'p.created DESC', -1, -1, $page,
                    $CFG->cybrary_manydiscussions, $cm);
            }
            break;

        default:
            echo '<br />';
            if (!empty($showall)) {
                cybrary_print_latest_discussions($course, $cybrary, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                cybrary_print_latest_discussions($course, $cybrary, -1, 'header', '', -1, -1, $page, $CFG->cybrary_manydiscussions, $cm);
            }


            break;
    }

    // Add the subscription toggle JS.
    $PAGE->requires->yui_module('moodle-mod_cybrary-subscriptiontoggle', 'Y.M.mod_cybrary.subscriptiontoggle.init');

    echo $OUTPUT->footer($course);
