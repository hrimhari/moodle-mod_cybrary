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
 * Set tracking option for the cybrary.
 *
 * @package   mod_cybrary
 * @copyright 2005 mchurch
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");

$id         = required_param('id',PARAM_INT);                           // The cybrary to subscribe or unsubscribe to
$returnpage = optional_param('returnpage', 'index.php', PARAM_FILE);    // Page to return to.

require_sesskey();

if (! $cybrary = $DB->get_record("cybrary", array("id" => $id))) {
    print_error('invalidcybraryid', 'cybrary');
}

if (! $course = $DB->get_record("course", array("id" => $cybrary->course))) {
    print_error('invalidcoursemodule');
}

if (! $cm = get_coursemodule_from_instance("cybrary", $cybrary->id, $course->id)) {
    print_error('invalidcoursemodule');
}
require_login($course, false, $cm);
$returnpageurl = new moodle_url('/mod/cybrary/' . $returnpage, array('id' => $course->id, 'f' => $cybrary->id));
$returnto = cybrary_go_back_to($returnpageurl);

if (!cybrary_tp_can_track_cybraries($cybrary)) {
    redirect($returnto);
}

$info = new stdClass();
$info->name  = fullname($USER);
$info->cybrary = format_string($cybrary->name);

$eventparams = array(
    'context' => context_module::instance($cm->id),
    'relateduserid' => $USER->id,
    'other' => array('cybraryid' => $cybrary->id),
);

if (cybrary_tp_is_tracked($cybrary) ) {
    if (cybrary_tp_stop_tracking($cybrary->id)) {
        $event = \mod_cybrary\event\readtracking_disabled::create($eventparams);
        $event->trigger();
        redirect($returnto, get_string("nownottracking", "cybrary", $info), 1);
    } else {
        print_error('cannottrack', '', get_local_referer(false));
    }

} else { // subscribe
    if (cybrary_tp_start_tracking($cybrary->id)) {
        $event = \mod_cybrary\event\readtracking_enabled::create($eventparams);
        $event->trigger();
        redirect($returnto, get_string("nowtracking", "cybrary", $info), 1);
    } else {
        print_error('cannottrack', '', get_local_referer(false));
    }
}
