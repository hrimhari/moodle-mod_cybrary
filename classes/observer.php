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
 * Event observers used in cybrary.
 *
 * @package    mod_cybrary
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for mod_cybrary.
 */
class mod_cybrary_observer {

    /**
     * Triggered via user_enrolment_deleted event.
     *
     * @param \core\event\user_enrolment_deleted $event
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event) {
        global $DB;

        // NOTE: this has to be as fast as possible.
        // Get user enrolment info from event.
        $cp = (object)$event->other['userenrolment'];
        if ($cp->lastenrol) {
            $params = array('userid' => $cp->userid, 'courseid' => $cp->courseid);
            $cybrarieselect = "IN (SELECT f.id FROM {cybrary} f WHERE f.course = :courseid)";

            $DB->delete_records_select('cybrary_digests', 'userid = :userid AND cybrary '.$cybrarieselect, $params);
            $DB->delete_records_select('cybrary_subscriptions', 'userid = :userid AND cybrary '.$cybrarieselect, $params);
            $DB->delete_records_select('cybrary_track_prefs', 'userid = :userid AND cybraryid '.$cybrarieselect, $params);
            $DB->delete_records_select('cybrary_read', 'userid = :userid AND cybraryid '.$cybrarieselect, $params);
        }
    }

    /**
     * Observer for role_assigned event.
     *
     * @param \core\event\role_assigned $event
     * @return void
     */
    public static function role_assigned(\core\event\role_assigned $event) {
        global $CFG, $DB;

        $context = context::instance_by_id($event->contextid, MUST_EXIST);

        // If contextlevel is course then only subscribe user. Role assignment
        // at course level means user is enroled in course and can subscribe to cybrary.
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        // Cybrary lib required for the constant used below.
        require_once($CFG->dirroot . '/mod/cybrary/lib.php');

        $userid = $event->relateduserid;
        $sql = "SELECT f.id, f.course as course, cm.id AS cmid, f.forcesubscribe
                  FROM {cybrary} f
                  JOIN {course_modules} cm ON (cm.instance = f.id)
                  JOIN {modules} m ON (m.id = cm.module)
             LEFT JOIN {cybrary_subscriptions} fs ON (fs.cybrary = f.id AND fs.userid = :userid)
                 WHERE f.course = :courseid
                   AND f.forcesubscribe = :initial
                   AND m.name = 'cybrary'
                   AND fs.id IS NULL";
        $params = array('courseid' => $context->instanceid, 'userid' => $userid, 'initial' => CYBRARY_INITIALSUBSCRIBE);

        $cybraries = $DB->get_records_sql($sql, $params);
        foreach ($cybraries as $cybrary) {
            // If user doesn't have allowforcesubscribe capability then don't subscribe.
            $modcontext = context_module::instance($cybrary->cmid);
            if (has_capability('mod/cybrary:allowforcesubscribe', $modcontext, $userid)) {
                \mod_cybrary\subscriptions::subscribe_user($userid, $cybrary, $modcontext);
            }
        }
    }

    /**
     * Observer for \core\event\course_module_created event.
     *
     * @param \core\event\course_module_created $event
     * @return void
     */
    public static function course_module_created(\core\event\course_module_created $event) {
        global $CFG;

        if ($event->other['modulename'] === 'cybrary') {
            // Include the cybrary library to make use of the cybrary_instance_created function.
            require_once($CFG->dirroot . '/mod/cybrary/lib.php');

            $cybrary = $event->get_record_snapshot('cybrary', $event->other['instanceid']);
            cybrary_instance_created($event->get_context(), $cybrary);
        }
    }
}
