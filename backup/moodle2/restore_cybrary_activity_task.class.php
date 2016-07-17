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
 * @package    mod_cybrary
 * @subpackage backup-moodle2
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/cybrary/backup/moodle2/restore_cybrary_stepslib.php'); // Because it exists (must)

/**
 * cybrary restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 */
class restore_cybrary_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // Choice only has one structure step
        $this->add_step(new restore_cybrary_activity_structure_step('cybrary_structure', 'cybrary.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    static public function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('cybrary', array('intro', 'externalurl'), 'cybrary');
        $contents[] = new restore_decode_content('cybrary_posts', array('message'), 'cybrary_post');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    static public function define_decode_rules() {
        $rules = array();

        // List of cybraries in course
        $rules[] = new restore_decode_rule('CYBRARYINDEX', '/mod/cybrary/index.php?id=$1', 'course');
        // Cybrary by cm->id and cybrary->id
        $rules[] = new restore_decode_rule('CYBRARYVIEWBYID', '/mod/cybrary/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('CYBRARYVIEWBYF', '/mod/cybrary/view.php?f=$1', 'cybrary');
        // Link to cybrary discussion
        $rules[] = new restore_decode_rule('CYBRARYDISCUSSIONVIEW', '/mod/cybrary/discuss.php?d=$1', 'cybrary_discussion');
        // Link to discussion with parent and with anchor posts
        $rules[] = new restore_decode_rule('CYBRARYDISCUSSIONVIEWPARENT', '/mod/cybrary/discuss.php?d=$1&parent=$2',
                                           array('cybrary_discussion', 'cybrary_post'));
        $rules[] = new restore_decode_rule('CYBRARYDISCUSSIONVIEWINSIDE', '/mod/cybrary/discuss.php?d=$1#$2',
                                           array('cybrary_discussion', 'cybrary_post'));

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * cybrary logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    static public function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('cybrary', 'add', 'view.php?id={course_module}', '{cybrary}');
        $rules[] = new restore_log_rule('cybrary', 'update', 'view.php?id={course_module}', '{cybrary}');
        $rules[] = new restore_log_rule('cybrary', 'view', 'view.php?id={course_module}', '{cybrary}');
        $rules[] = new restore_log_rule('cybrary', 'view cybrary', 'view.php?id={course_module}', '{cybrary}');
        $rules[] = new restore_log_rule('cybrary', 'mark read', 'view.php?f={cybrary}', '{cybrary}');
        $rules[] = new restore_log_rule('cybrary', 'start tracking', 'view.php?f={cybrary}', '{cybrary}');
        $rules[] = new restore_log_rule('cybrary', 'stop tracking', 'view.php?f={cybrary}', '{cybrary}');
        $rules[] = new restore_log_rule('cybrary', 'subscribe', 'view.php?f={cybrary}', '{cybrary}');
        $rules[] = new restore_log_rule('cybrary', 'unsubscribe', 'view.php?f={cybrary}', '{cybrary}');
        $rules[] = new restore_log_rule('cybrary', 'subscriber', 'subscribers.php?id={cybrary}', '{cybrary}');
        $rules[] = new restore_log_rule('cybrary', 'subscribers', 'subscribers.php?id={cybrary}', '{cybrary}');
        $rules[] = new restore_log_rule('cybrary', 'view subscribers', 'subscribers.php?id={cybrary}', '{cybrary}');
        $rules[] = new restore_log_rule('cybrary', 'add discussion', 'discuss.php?d={cybrary_discussion}', '{cybrary_discussion}');
        $rules[] = new restore_log_rule('cybrary', 'view discussion', 'discuss.php?d={cybrary_discussion}', '{cybrary_discussion}');
        $rules[] = new restore_log_rule('cybrary', 'move discussion', 'discuss.php?d={cybrary_discussion}', '{cybrary_discussion}');
        $rules[] = new restore_log_rule('cybrary', 'delete discussi', 'view.php?id={course_module}', '{cybrary}',
                                        null, 'delete discussion');
        $rules[] = new restore_log_rule('cybrary', 'delete discussion', 'view.php?id={course_module}', '{cybrary}');
        $rules[] = new restore_log_rule('cybrary', 'add post', 'discuss.php?d={cybrary_discussion}&parent={cybrary_post}', '{cybrary_post}');
        $rules[] = new restore_log_rule('cybrary', 'update post', 'discuss.php?d={cybrary_discussion}#p{cybrary_post}&parent={cybrary_post}', '{cybrary_post}');
        $rules[] = new restore_log_rule('cybrary', 'update post', 'discuss.php?d={cybrary_discussion}&parent={cybrary_post}', '{cybrary_post}');
        $rules[] = new restore_log_rule('cybrary', 'prune post', 'discuss.php?d={cybrary_discussion}', '{cybrary_post}');
        $rules[] = new restore_log_rule('cybrary', 'delete post', 'discuss.php?d={cybrary_discussion}', '[post]');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    static public function define_restore_log_rules_for_course() {
        $rules = array();

        $rules[] = new restore_log_rule('cybrary', 'view cybraries', 'index.php?id={course}', null);
        $rules[] = new restore_log_rule('cybrary', 'subscribeall', 'index.php?id={course}', '{course}');
        $rules[] = new restore_log_rule('cybrary', 'unsubscribeall', 'index.php?id={course}', '{course}');
        $rules[] = new restore_log_rule('cybrary', 'user report', 'user.php?course={course}&id={user}&mode=[mode]', '{user}');
        $rules[] = new restore_log_rule('cybrary', 'search', 'search.php?id={course}&search=[searchenc]', '[search]');

        return $rules;
    }
}
