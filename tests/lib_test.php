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
 * The module cybraries tests
 *
 * @package    mod_cybrary
 * @copyright  2013 Frédéric Massart
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/cybrary/lib.php');
require_once($CFG->dirroot . '/rating/lib.php');

class mod_cybrary_lib_testcase extends advanced_testcase {

    public function setUp() {
        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_cybrary\subscriptions::reset_cybrary_cache();
    }

    public function tearDown() {
        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_cybrary\subscriptions::reset_cybrary_cache();
    }

    public function test_cybrary_trigger_content_uploaded_event() {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $cybrary = $this->getDataGenerator()->create_module('cybrary', array('course' => $course->id));
        $context = context_module::instance($cybrary->cmid);

        $this->setUser($user->id);
        $fakepost = (object) array('id' => 123, 'message' => 'Yay!', 'discussion' => 100);
        $cm = get_coursemodule_from_instance('cybrary', $cybrary->id);

        $fs = get_file_storage();
        $dummy = (object) array(
            'contextid' => $context->id,
            'component' => 'mod_cybrary',
            'filearea' => 'attachment',
            'itemid' => $fakepost->id,
            'filepath' => '/',
            'filename' => 'myassignmnent.pdf'
        );
        $fi = $fs->create_file_from_string($dummy, 'Content of ' . $dummy->filename);

        $data = new stdClass();
        $sink = $this->redirectEvents();
        cybrary_trigger_content_uploaded_event($fakepost, $cm, 'some triggered from value');
        $events = $sink->get_events();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf('\mod_cybrary\event\assessable_uploaded', $event);
        $this->assertEquals($context->id, $event->contextid);
        $this->assertEquals($fakepost->id, $event->objectid);
        $this->assertEquals($fakepost->message, $event->other['content']);
        $this->assertEquals($fakepost->discussion, $event->other['discussionid']);
        $this->assertCount(1, $event->other['pathnamehashes']);
        $this->assertEquals($fi->get_pathnamehash(), $event->other['pathnamehashes'][0]);
        $expected = new stdClass();
        $expected->modulename = 'cybrary';
        $expected->name = 'some triggered from value';
        $expected->cmid = $cybrary->cmid;
        $expected->itemid = $fakepost->id;
        $expected->courseid = $course->id;
        $expected->userid = $user->id;
        $expected->content = $fakepost->message;
        $expected->pathnamehashes = array($fi->get_pathnamehash());
        $this->assertEventLegacyData($expected, $event);
        $this->assertEventContextNotUsed($event);
    }

    public function test_cybrary_get_courses_user_posted_in() {
        $this->resetAfterTest();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();

        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $course3 = $this->getDataGenerator()->create_course();

        // Create 3 cybraries, one in each course.
        $record = new stdClass();
        $record->course = $course1->id;
        $cybrary1 = $this->getDataGenerator()->create_module('cybrary', $record);

        $record = new stdClass();
        $record->course = $course2->id;
        $cybrary2 = $this->getDataGenerator()->create_module('cybrary', $record);

        $record = new stdClass();
        $record->course = $course3->id;
        $cybrary3 = $this->getDataGenerator()->create_module('cybrary', $record);

        // Add a second cybrary in course 1.
        $record = new stdClass();
        $record->course = $course1->id;
        $cybrary4 = $this->getDataGenerator()->create_module('cybrary', $record);

        // Add discussions to course 1 started by user1.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user1->id;
        $record->cybrary = $cybrary1->id;
        $this->getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user1->id;
        $record->cybrary = $cybrary4->id;
        $this->getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        // Add discussions to course2 started by user1.
        $record = new stdClass();
        $record->course = $course2->id;
        $record->userid = $user1->id;
        $record->cybrary = $cybrary2->id;
        $this->getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        // Add discussions to course 3 started by user2.
        $record = new stdClass();
        $record->course = $course3->id;
        $record->userid = $user2->id;
        $record->cybrary = $cybrary3->id;
        $discussion3 = $this->getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        // Add post to course 3 by user1.
        $record = new stdClass();
        $record->course = $course3->id;
        $record->userid = $user1->id;
        $record->cybrary = $cybrary3->id;
        $record->discussion = $discussion3->id;
        $this->getDataGenerator()->get_plugin_generator('mod_cybrary')->create_post($record);

        // User 3 hasn't posted anything, so shouldn't get any results.
        $user3courses = cybrary_get_courses_user_posted_in($user3);
        $this->assertEmpty($user3courses);

        // User 2 has only posted in course3.
        $user2courses = cybrary_get_courses_user_posted_in($user2);
        $this->assertCount(1, $user2courses);
        $user2course = array_shift($user2courses);
        $this->assertEquals($course3->id, $user2course->id);
        $this->assertEquals($course3->shortname, $user2course->shortname);

        // User 1 has posted in all 3 courses.
        $user1courses = cybrary_get_courses_user_posted_in($user1);
        $this->assertCount(3, $user1courses);
        foreach ($user1courses as $course) {
            $this->assertContains($course->id, array($course1->id, $course2->id, $course3->id));
            $this->assertContains($course->shortname, array($course1->shortname, $course2->shortname,
                $course3->shortname));

        }

        // User 1 has only started a discussion in course 1 and 2 though.
        $user1courses = cybrary_get_courses_user_posted_in($user1, true);
        $this->assertCount(2, $user1courses);
        foreach ($user1courses as $course) {
            $this->assertContains($course->id, array($course1->id, $course2->id));
            $this->assertContains($course->shortname, array($course1->shortname, $course2->shortname));
        }
    }

    /**
     * Test the logic in the cybrary_tp_can_track_cybraries() function.
     */
    public function test_cybrary_tp_can_track_cybraries() {
        global $CFG;

        $this->resetAfterTest();

        $useron = $this->getDataGenerator()->create_user(array('trackcybraries' => 1));
        $useroff = $this->getDataGenerator()->create_user(array('trackcybraries' => 0));
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'trackingtype' => CYBRARY_TRACKING_OFF); // Off.
        $cybraryoff = $this->getDataGenerator()->create_module('cybrary', $options);

        $options = array('course' => $course->id, 'trackingtype' => CYBRARY_TRACKING_FORCED); // On.
        $cybraryforce = $this->getDataGenerator()->create_module('cybrary', $options);

        $options = array('course' => $course->id, 'trackingtype' => CYBRARY_TRACKING_OPTIONAL); // Optional.
        $cybraryoptional = $this->getDataGenerator()->create_module('cybrary', $options);

        // Allow force.
        $CFG->cybrary_allowforcedreadtracking = 1;

        // User on, cybrary off, should be off.
        $result = cybrary_tp_can_track_cybraries($cybraryoff, $useron);
        $this->assertEquals(false, $result);

        // User on, cybrary on, should be on.
        $result = cybrary_tp_can_track_cybraries($cybraryforce, $useron);
        $this->assertEquals(true, $result);

        // User on, cybrary optional, should be on.
        $result = cybrary_tp_can_track_cybraries($cybraryoptional, $useron);
        $this->assertEquals(true, $result);

        // User off, cybrary off, should be off.
        $result = cybrary_tp_can_track_cybraries($cybraryoff, $useroff);
        $this->assertEquals(false, $result);

        // User off, cybrary force, should be on.
        $result = cybrary_tp_can_track_cybraries($cybraryforce, $useroff);
        $this->assertEquals(true, $result);

        // User off, cybrary optional, should be off.
        $result = cybrary_tp_can_track_cybraries($cybraryoptional, $useroff);
        $this->assertEquals(false, $result);

        // Don't allow force.
        $CFG->cybrary_allowforcedreadtracking = 0;

        // User on, cybrary off, should be off.
        $result = cybrary_tp_can_track_cybraries($cybraryoff, $useron);
        $this->assertEquals(false, $result);

        // User on, cybrary on, should be on.
        $result = cybrary_tp_can_track_cybraries($cybraryforce, $useron);
        $this->assertEquals(true, $result);

        // User on, cybrary optional, should be on.
        $result = cybrary_tp_can_track_cybraries($cybraryoptional, $useron);
        $this->assertEquals(true, $result);

        // User off, cybrary off, should be off.
        $result = cybrary_tp_can_track_cybraries($cybraryoff, $useroff);
        $this->assertEquals(false, $result);

        // User off, cybrary force, should be off.
        $result = cybrary_tp_can_track_cybraries($cybraryforce, $useroff);
        $this->assertEquals(false, $result);

        // User off, cybrary optional, should be off.
        $result = cybrary_tp_can_track_cybraries($cybraryoptional, $useroff);
        $this->assertEquals(false, $result);

    }

    /**
     * Test the logic in the test_cybrary_tp_is_tracked() function.
     */
    public function test_cybrary_tp_is_tracked() {
        global $CFG;

        $this->resetAfterTest();

        $useron = $this->getDataGenerator()->create_user(array('trackcybraries' => 1));
        $useroff = $this->getDataGenerator()->create_user(array('trackcybraries' => 0));
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'trackingtype' => CYBRARY_TRACKING_OFF); // Off.
        $cybraryoff = $this->getDataGenerator()->create_module('cybrary', $options);

        $options = array('course' => $course->id, 'trackingtype' => CYBRARY_TRACKING_FORCED); // On.
        $cybraryforce = $this->getDataGenerator()->create_module('cybrary', $options);

        $options = array('course' => $course->id, 'trackingtype' => CYBRARY_TRACKING_OPTIONAL); // Optional.
        $cybraryoptional = $this->getDataGenerator()->create_module('cybrary', $options);

        // Allow force.
        $CFG->cybrary_allowforcedreadtracking = 1;

        // User on, cybrary off, should be off.
        $result = cybrary_tp_is_tracked($cybraryoff, $useron);
        $this->assertEquals(false, $result);

        // User on, cybrary force, should be on.
        $result = cybrary_tp_is_tracked($cybraryforce, $useron);
        $this->assertEquals(true, $result);

        // User on, cybrary optional, should be on.
        $result = cybrary_tp_is_tracked($cybraryoptional, $useron);
        $this->assertEquals(true, $result);

        // User off, cybrary off, should be off.
        $result = cybrary_tp_is_tracked($cybraryoff, $useroff);
        $this->assertEquals(false, $result);

        // User off, cybrary force, should be on.
        $result = cybrary_tp_is_tracked($cybraryforce, $useroff);
        $this->assertEquals(true, $result);

        // User off, cybrary optional, should be off.
        $result = cybrary_tp_is_tracked($cybraryoptional, $useroff);
        $this->assertEquals(false, $result);

        // Don't allow force.
        $CFG->cybrary_allowforcedreadtracking = 0;

        // User on, cybrary off, should be off.
        $result = cybrary_tp_is_tracked($cybraryoff, $useron);
        $this->assertEquals(false, $result);

        // User on, cybrary force, should be on.
        $result = cybrary_tp_is_tracked($cybraryforce, $useron);
        $this->assertEquals(true, $result);

        // User on, cybrary optional, should be on.
        $result = cybrary_tp_is_tracked($cybraryoptional, $useron);
        $this->assertEquals(true, $result);

        // User off, cybrary off, should be off.
        $result = cybrary_tp_is_tracked($cybraryoff, $useroff);
        $this->assertEquals(false, $result);

        // User off, cybrary force, should be off.
        $result = cybrary_tp_is_tracked($cybraryforce, $useroff);
        $this->assertEquals(false, $result);

        // User off, cybrary optional, should be off.
        $result = cybrary_tp_is_tracked($cybraryoptional, $useroff);
        $this->assertEquals(false, $result);

        // Stop tracking so we can test again.
        cybrary_tp_stop_tracking($cybraryforce->id, $useron->id);
        cybrary_tp_stop_tracking($cybraryoptional->id, $useron->id);
        cybrary_tp_stop_tracking($cybraryforce->id, $useroff->id);
        cybrary_tp_stop_tracking($cybraryoptional->id, $useroff->id);

        // Allow force.
        $CFG->cybrary_allowforcedreadtracking = 1;

        // User on, preference off, cybrary force, should be on.
        $result = cybrary_tp_is_tracked($cybraryforce, $useron);
        $this->assertEquals(true, $result);

        // User on, preference off, cybrary optional, should be on.
        $result = cybrary_tp_is_tracked($cybraryoptional, $useron);
        $this->assertEquals(false, $result);

        // User off, preference off, cybrary force, should be on.
        $result = cybrary_tp_is_tracked($cybraryforce, $useroff);
        $this->assertEquals(true, $result);

        // User off, preference off, cybrary optional, should be off.
        $result = cybrary_tp_is_tracked($cybraryoptional, $useroff);
        $this->assertEquals(false, $result);

        // Don't allow force.
        $CFG->cybrary_allowforcedreadtracking = 0;

        // User on, preference off, cybrary force, should be on.
        $result = cybrary_tp_is_tracked($cybraryforce, $useron);
        $this->assertEquals(false, $result);

        // User on, preference off, cybrary optional, should be on.
        $result = cybrary_tp_is_tracked($cybraryoptional, $useron);
        $this->assertEquals(false, $result);

        // User off, preference off, cybrary force, should be off.
        $result = cybrary_tp_is_tracked($cybraryforce, $useroff);
        $this->assertEquals(false, $result);

        // User off, preference off, cybrary optional, should be off.
        $result = cybrary_tp_is_tracked($cybraryoptional, $useroff);
        $this->assertEquals(false, $result);
    }

    /**
     * Test the logic in the cybrary_tp_get_course_unread_posts() function.
     */
    public function test_cybrary_tp_get_course_unread_posts() {
        global $CFG;

        $this->resetAfterTest();

        $useron = $this->getDataGenerator()->create_user(array('trackcybraries' => 1));
        $useroff = $this->getDataGenerator()->create_user(array('trackcybraries' => 0));
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'trackingtype' => CYBRARY_TRACKING_OFF); // Off.
        $cybraryoff = $this->getDataGenerator()->create_module('cybrary', $options);

        $options = array('course' => $course->id, 'trackingtype' => CYBRARY_TRACKING_FORCED); // On.
        $cybraryforce = $this->getDataGenerator()->create_module('cybrary', $options);

        $options = array('course' => $course->id, 'trackingtype' => CYBRARY_TRACKING_OPTIONAL); // Optional.
        $cybraryoptional = $this->getDataGenerator()->create_module('cybrary', $options);

        // Add discussions to the tracking off cybrary.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $useron->id;
        $record->cybrary = $cybraryoff->id;
        $discussionoff = $this->getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        // Add discussions to the tracking forced cybrary.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $useron->id;
        $record->cybrary = $cybraryforce->id;
        $discussionforce = $this->getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        // Add post to the tracking forced discussion.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $useroff->id;
        $record->cybrary = $cybraryforce->id;
        $record->discussion = $discussionforce->id;
        $this->getDataGenerator()->get_plugin_generator('mod_cybrary')->create_post($record);

        // Add discussions to the tracking optional cybrary.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $useron->id;
        $record->cybrary = $cybraryoptional->id;
        $discussionoptional = $this->getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        // Allow force.
        $CFG->cybrary_allowforcedreadtracking = 1;

        $result = cybrary_tp_get_course_unread_posts($useron->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(false, isset($result[$cybraryoff->id]));
        $this->assertEquals(true, isset($result[$cybraryforce->id]));
        $this->assertEquals(2, $result[$cybraryforce->id]->unread);
        $this->assertEquals(true, isset($result[$cybraryoptional->id]));
        $this->assertEquals(1, $result[$cybraryoptional->id]->unread);

        $result = cybrary_tp_get_course_unread_posts($useroff->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(false, isset($result[$cybraryoff->id]));
        $this->assertEquals(true, isset($result[$cybraryforce->id]));
        $this->assertEquals(2, $result[$cybraryforce->id]->unread);
        $this->assertEquals(false, isset($result[$cybraryoptional->id]));

        // Don't allow force.
        $CFG->cybrary_allowforcedreadtracking = 0;

        $result = cybrary_tp_get_course_unread_posts($useron->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(false, isset($result[$cybraryoff->id]));
        $this->assertEquals(true, isset($result[$cybraryforce->id]));
        $this->assertEquals(2, $result[$cybraryforce->id]->unread);
        $this->assertEquals(true, isset($result[$cybraryoptional->id]));
        $this->assertEquals(1, $result[$cybraryoptional->id]->unread);

        $result = cybrary_tp_get_course_unread_posts($useroff->id, $course->id);
        $this->assertEquals(0, count($result));
        $this->assertEquals(false, isset($result[$cybraryoff->id]));
        $this->assertEquals(false, isset($result[$cybraryforce->id]));
        $this->assertEquals(false, isset($result[$cybraryoptional->id]));

        // Stop tracking so we can test again.
        cybrary_tp_stop_tracking($cybraryforce->id, $useron->id);
        cybrary_tp_stop_tracking($cybraryoptional->id, $useron->id);
        cybrary_tp_stop_tracking($cybraryforce->id, $useroff->id);
        cybrary_tp_stop_tracking($cybraryoptional->id, $useroff->id);

        // Allow force.
        $CFG->cybrary_allowforcedreadtracking = 1;

        $result = cybrary_tp_get_course_unread_posts($useron->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(false, isset($result[$cybraryoff->id]));
        $this->assertEquals(true, isset($result[$cybraryforce->id]));
        $this->assertEquals(2, $result[$cybraryforce->id]->unread);
        $this->assertEquals(false, isset($result[$cybraryoptional->id]));

        $result = cybrary_tp_get_course_unread_posts($useroff->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(false, isset($result[$cybraryoff->id]));
        $this->assertEquals(true, isset($result[$cybraryforce->id]));
        $this->assertEquals(2, $result[$cybraryforce->id]->unread);
        $this->assertEquals(false, isset($result[$cybraryoptional->id]));

        // Don't allow force.
        $CFG->cybrary_allowforcedreadtracking = 0;

        $result = cybrary_tp_get_course_unread_posts($useron->id, $course->id);
        $this->assertEquals(0, count($result));
        $this->assertEquals(false, isset($result[$cybraryoff->id]));
        $this->assertEquals(false, isset($result[$cybraryforce->id]));
        $this->assertEquals(false, isset($result[$cybraryoptional->id]));

        $result = cybrary_tp_get_course_unread_posts($useroff->id, $course->id);
        $this->assertEquals(0, count($result));
        $this->assertEquals(false, isset($result[$cybraryoff->id]));
        $this->assertEquals(false, isset($result[$cybraryforce->id]));
        $this->assertEquals(false, isset($result[$cybraryoptional->id]));
    }

    /**
     * Test the logic in the test_cybrary_tp_get_untracked_cybraries() function.
     */
    public function test_cybrary_tp_get_untracked_cybraries() {
        global $CFG;

        $this->resetAfterTest();

        $useron = $this->getDataGenerator()->create_user(array('trackcybraries' => 1));
        $useroff = $this->getDataGenerator()->create_user(array('trackcybraries' => 0));
        $course = $this->getDataGenerator()->create_course();
        $options = array('course' => $course->id, 'trackingtype' => CYBRARY_TRACKING_OFF); // Off.
        $cybraryoff = $this->getDataGenerator()->create_module('cybrary', $options);

        $options = array('course' => $course->id, 'trackingtype' => CYBRARY_TRACKING_FORCED); // On.
        $cybraryforce = $this->getDataGenerator()->create_module('cybrary', $options);

        $options = array('course' => $course->id, 'trackingtype' => CYBRARY_TRACKING_OPTIONAL); // Optional.
        $cybraryoptional = $this->getDataGenerator()->create_module('cybrary', $options);

        // Allow force.
        $CFG->cybrary_allowforcedreadtracking = 1;

        // On user with force on.
        $result = cybrary_tp_get_untracked_cybraries($useron->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(true, isset($result[$cybraryoff->id]));

        // Off user with force on.
        $result = cybrary_tp_get_untracked_cybraries($useroff->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(true, isset($result[$cybraryoff->id]));
        $this->assertEquals(true, isset($result[$cybraryoptional->id]));

        // Don't allow force.
        $CFG->cybrary_allowforcedreadtracking = 0;

        // On user with force off.
        $result = cybrary_tp_get_untracked_cybraries($useron->id, $course->id);
        $this->assertEquals(1, count($result));
        $this->assertEquals(true, isset($result[$cybraryoff->id]));

        // Off user with force off.
        $result = cybrary_tp_get_untracked_cybraries($useroff->id, $course->id);
        $this->assertEquals(3, count($result));
        $this->assertEquals(true, isset($result[$cybraryoff->id]));
        $this->assertEquals(true, isset($result[$cybraryoptional->id]));
        $this->assertEquals(true, isset($result[$cybraryforce->id]));

        // Stop tracking so we can test again.
        cybrary_tp_stop_tracking($cybraryforce->id, $useron->id);
        cybrary_tp_stop_tracking($cybraryoptional->id, $useron->id);
        cybrary_tp_stop_tracking($cybraryforce->id, $useroff->id);
        cybrary_tp_stop_tracking($cybraryoptional->id, $useroff->id);

        // Allow force.
        $CFG->cybrary_allowforcedreadtracking = 1;

        // On user with force on.
        $result = cybrary_tp_get_untracked_cybraries($useron->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(true, isset($result[$cybraryoff->id]));
        $this->assertEquals(true, isset($result[$cybraryoptional->id]));

        // Off user with force on.
        $result = cybrary_tp_get_untracked_cybraries($useroff->id, $course->id);
        $this->assertEquals(2, count($result));
        $this->assertEquals(true, isset($result[$cybraryoff->id]));
        $this->assertEquals(true, isset($result[$cybraryoptional->id]));

        // Don't allow force.
        $CFG->cybrary_allowforcedreadtracking = 0;

        // On user with force off.
        $result = cybrary_tp_get_untracked_cybraries($useron->id, $course->id);
        $this->assertEquals(3, count($result));
        $this->assertEquals(true, isset($result[$cybraryoff->id]));
        $this->assertEquals(true, isset($result[$cybraryoptional->id]));
        $this->assertEquals(true, isset($result[$cybraryforce->id]));

        // Off user with force off.
        $result = cybrary_tp_get_untracked_cybraries($useroff->id, $course->id);
        $this->assertEquals(3, count($result));
        $this->assertEquals(true, isset($result[$cybraryoff->id]));
        $this->assertEquals(true, isset($result[$cybraryoptional->id]));
        $this->assertEquals(true, isset($result[$cybraryforce->id]));
    }

    /**
     * Test subscription using automatic subscription on create.
     */
    public function test_cybrary_auto_subscribe_on_create() {
        global $CFG;

        $this->resetAfterTest();

        $usercount = 5;
        $course = $this->getDataGenerator()->create_course();
        $users = array();

        for ($i = 0; $i < $usercount; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $users[] = $user;
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
        }

        $options = array('course' => $course->id, 'forcesubscribe' => CYBRARY_INITIALSUBSCRIBE); // Automatic Subscription.
        $cybrary = $this->getDataGenerator()->create_module('cybrary', $options);

        $result = \mod_cybrary\subscriptions::fetch_subscribed_users($cybrary);
        $this->assertEquals($usercount, count($result));
        foreach ($users as $user) {
            $this->assertTrue(\mod_cybrary\subscriptions::is_subscribed($user->id, $cybrary));
        }
    }

    /**
     * Test subscription using forced subscription on create.
     */
    public function test_cybrary_forced_subscribe_on_create() {
        global $CFG;

        $this->resetAfterTest();

        $usercount = 5;
        $course = $this->getDataGenerator()->create_course();
        $users = array();

        for ($i = 0; $i < $usercount; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $users[] = $user;
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
        }

        $options = array('course' => $course->id, 'forcesubscribe' => CYBRARY_FORCESUBSCRIBE); // Forced subscription.
        $cybrary = $this->getDataGenerator()->create_module('cybrary', $options);

        $result = \mod_cybrary\subscriptions::fetch_subscribed_users($cybrary);
        $this->assertEquals($usercount, count($result));
        foreach ($users as $user) {
            $this->assertTrue(\mod_cybrary\subscriptions::is_subscribed($user->id, $cybrary));
        }
    }

    /**
     * Test subscription using optional subscription on create.
     */
    public function test_cybrary_optional_subscribe_on_create() {
        global $CFG;

        $this->resetAfterTest();

        $usercount = 5;
        $course = $this->getDataGenerator()->create_course();
        $users = array();

        for ($i = 0; $i < $usercount; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $users[] = $user;
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
        }

        $options = array('course' => $course->id, 'forcesubscribe' => CYBRARY_CHOOSESUBSCRIBE); // Subscription optional.
        $cybrary = $this->getDataGenerator()->create_module('cybrary', $options);

        $result = \mod_cybrary\subscriptions::fetch_subscribed_users($cybrary);
        // No subscriptions by default.
        $this->assertEquals(0, count($result));
        foreach ($users as $user) {
            $this->assertFalse(\mod_cybrary\subscriptions::is_subscribed($user->id, $cybrary));
        }
    }

    /**
     * Test subscription using disallow subscription on create.
     */
    public function test_cybrary_disallow_subscribe_on_create() {
        global $CFG;

        $this->resetAfterTest();

        $usercount = 5;
        $course = $this->getDataGenerator()->create_course();
        $users = array();

        for ($i = 0; $i < $usercount; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $users[] = $user;
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
        }

        $options = array('course' => $course->id, 'forcesubscribe' => CYBRARY_DISALLOWSUBSCRIBE); // Subscription prevented.
        $cybrary = $this->getDataGenerator()->create_module('cybrary', $options);

        $result = \mod_cybrary\subscriptions::fetch_subscribed_users($cybrary);
        // No subscriptions by default.
        $this->assertEquals(0, count($result));
        foreach ($users as $user) {
            $this->assertFalse(\mod_cybrary\subscriptions::is_subscribed($user->id, $cybrary));
        }
    }

    /**
     * Test that context fetching returns the appropriate context.
     */
    public function test_cybrary_get_context() {
        global $DB, $PAGE;

        $this->resetAfterTest();

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => CYBRARY_CHOOSESUBSCRIBE);
        $cybrary = $this->getDataGenerator()->create_module('cybrary', $options);
        $cybrarycm = get_coursemodule_from_instance('cybrary', $cybrary->id);
        $cybrarycontext = \context_module::instance($cybrarycm->id);

        // First check that specifying the context results in the correct context being returned.
        // Do this before we set up the page object and we should return from the coursemodule record.
        // There should be no DB queries here because the context type was correct.
        $startcount = $DB->perf_get_reads();
        $result = cybrary_get_context($cybrary->id, $cybrarycontext);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($cybrarycontext, $result);
        $this->assertEquals(0, $aftercount - $startcount);

        // And a context which is not the correct type.
        // This tests will result in a DB query to fetch the course_module.
        $startcount = $DB->perf_get_reads();
        $result = cybrary_get_context($cybrary->id, $coursecontext);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($cybrarycontext, $result);
        $this->assertEquals(1, $aftercount - $startcount);

        // Now do not specify a context at all.
        // This tests will result in a DB query to fetch the course_module.
        $startcount = $DB->perf_get_reads();
        $result = cybrary_get_context($cybrary->id);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($cybrarycontext, $result);
        $this->assertEquals(1, $aftercount - $startcount);

        // Set up the default page event to use the cybrary.
        $PAGE = new moodle_page();
        $PAGE->set_context($cybrarycontext);
        $PAGE->set_cm($cybrarycm, $course, $cybrary);

        // Now specify a context which is not a context_module.
        // There should be no DB queries here because we use the PAGE.
        $startcount = $DB->perf_get_reads();
        $result = cybrary_get_context($cybrary->id, $coursecontext);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($cybrarycontext, $result);
        $this->assertEquals(0, $aftercount - $startcount);

        // Now do not specify a context at all.
        // There should be no DB queries here because we use the PAGE.
        $startcount = $DB->perf_get_reads();
        $result = cybrary_get_context($cybrary->id);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($cybrarycontext, $result);
        $this->assertEquals(0, $aftercount - $startcount);

        // Now specify the page context of the course instead..
        $PAGE = new moodle_page();
        $PAGE->set_context($coursecontext);

        // Now specify a context which is not a context_module.
        // This tests will result in a DB query to fetch the course_module.
        $startcount = $DB->perf_get_reads();
        $result = cybrary_get_context($cybrary->id, $coursecontext);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($cybrarycontext, $result);
        $this->assertEquals(1, $aftercount - $startcount);

        // Now do not specify a context at all.
        // This tests will result in a DB query to fetch the course_module.
        $startcount = $DB->perf_get_reads();
        $result = cybrary_get_context($cybrary->id);
        $aftercount = $DB->perf_get_reads();
        $this->assertEquals($cybrarycontext, $result);
        $this->assertEquals(1, $aftercount - $startcount);
    }

    /**
     * Test getting the neighbour threads of a discussion.
     */
    public function test_cybrary_get_neighbours() {
        global $CFG, $DB;
        $this->resetAfterTest();

        // Setup test data.
        $cybrarygen = $this->getDataGenerator()->get_plugin_generator('mod_cybrary');
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $cybrary = $this->getDataGenerator()->create_module('cybrary', array('course' => $course->id));
        $cm = get_coursemodule_from_instance('cybrary', $cybrary->id);
        $context = context_module::instance($cm->id);

        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user->id;
        $record->cybrary = $cybrary->id;
        $record->timemodified = time();
        $disc1 = $cybrarygen->create_discussion($record);
        $record->timemodified++;
        $disc2 = $cybrarygen->create_discussion($record);
        $record->timemodified++;
        $disc3 = $cybrarygen->create_discussion($record);
        $record->timemodified++;
        $disc4 = $cybrarygen->create_discussion($record);
        $record->timemodified++;
        $disc5 = $cybrarygen->create_discussion($record);

        // Getting the neighbours.
        $neighbours = cybrary_get_discussion_neighbours($cm, $disc1, $cybrary);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc2->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc2, $cybrary);
        $this->assertEquals($disc1->id, $neighbours['prev']->id);
        $this->assertEquals($disc3->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc3, $cybrary);
        $this->assertEquals($disc2->id, $neighbours['prev']->id);
        $this->assertEquals($disc4->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc4, $cybrary);
        $this->assertEquals($disc3->id, $neighbours['prev']->id);
        $this->assertEquals($disc5->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc5, $cybrary);
        $this->assertEquals($disc4->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Post in some discussions. We manually update the discussion record because
        // the data generator plays with timemodified in a way that would break this test.
        $record->timemodified++;
        $disc1->timemodified = $record->timemodified;
        $DB->update_record('cybrary_discussions', $disc1);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc5, $cybrary);
        $this->assertEquals($disc4->id, $neighbours['prev']->id);
        $this->assertEquals($disc1->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc2, $cybrary);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc3->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc1, $cybrary);
        $this->assertEquals($disc5->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // After some discussions were created.
        $record->timemodified++;
        $disc6 = $cybrarygen->create_discussion($record);
        $neighbours = cybrary_get_discussion_neighbours($cm, $disc6, $cybrary);
        $this->assertEquals($disc1->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        $record->timemodified++;
        $disc7 = $cybrarygen->create_discussion($record);
        $neighbours = cybrary_get_discussion_neighbours($cm, $disc7, $cybrary);
        $this->assertEquals($disc6->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Adding timed discussions.
        $CFG->cybrary_enabletimedposts = true;
        $now = $record->timemodified;
        $past = $now - 60;
        $future = $now + 60;

        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user->id;
        $record->cybrary = $cybrary->id;
        $record->timestart = $past;
        $record->timeend = $future;
        $record->timemodified = $now;
        $record->timemodified++;
        $disc8 = $cybrarygen->create_discussion($record);
        $record->timemodified++;
        $record->timestart = $future;
        $record->timeend = 0;
        $disc9 = $cybrarygen->create_discussion($record);
        $record->timemodified++;
        $record->timestart = 0;
        $record->timeend = 0;
        $disc10 = $cybrarygen->create_discussion($record);
        $record->timemodified++;
        $record->timestart = 0;
        $record->timeend = $past;
        $disc11 = $cybrarygen->create_discussion($record);
        $record->timemodified++;
        $record->timestart = $past;
        $record->timeend = $future;
        $disc12 = $cybrarygen->create_discussion($record);
        $record->timemodified++;
        $record->timestart = $future + 1; // Should be last post for those that can see it.
        $record->timeend = 0;
        $disc13 = $cybrarygen->create_discussion($record);

        // Admin user ignores the timed settings of discussions.
        // Post ordering taking into account timestart:
        //  8 = t
        // 10 = t+3
        // 11 = t+4
        // 12 = t+5
        //  9 = t+60
        // 13 = t+61.
        $this->setAdminUser();
        $neighbours = cybrary_get_discussion_neighbours($cm, $disc8, $cybrary);
        $this->assertEquals($disc7->id, $neighbours['prev']->id);
        $this->assertEquals($disc10->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc9, $cybrary);
        $this->assertEquals($disc12->id, $neighbours['prev']->id);
        $this->assertEquals($disc13->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc10, $cybrary);
        $this->assertEquals($disc8->id, $neighbours['prev']->id);
        $this->assertEquals($disc11->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc11, $cybrary);
        $this->assertEquals($disc10->id, $neighbours['prev']->id);
        $this->assertEquals($disc12->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc12, $cybrary);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc9->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc13, $cybrary);
        $this->assertEquals($disc9->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Normal user can see their own timed discussions.
        $this->setUser($user);
        $neighbours = cybrary_get_discussion_neighbours($cm, $disc8, $cybrary);
        $this->assertEquals($disc7->id, $neighbours['prev']->id);
        $this->assertEquals($disc10->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc9, $cybrary);
        $this->assertEquals($disc12->id, $neighbours['prev']->id);
        $this->assertEquals($disc13->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc10, $cybrary);
        $this->assertEquals($disc8->id, $neighbours['prev']->id);
        $this->assertEquals($disc11->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc11, $cybrary);
        $this->assertEquals($disc10->id, $neighbours['prev']->id);
        $this->assertEquals($disc12->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc12, $cybrary);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc9->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc13, $cybrary);
        $this->assertEquals($disc9->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Normal user does not ignore timed settings.
        $this->setUser($user2);
        $neighbours = cybrary_get_discussion_neighbours($cm, $disc8, $cybrary);
        $this->assertEquals($disc7->id, $neighbours['prev']->id);
        $this->assertEquals($disc10->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc10, $cybrary);
        $this->assertEquals($disc8->id, $neighbours['prev']->id);
        $this->assertEquals($disc12->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc12, $cybrary);
        $this->assertEquals($disc10->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Reset to normal mode.
        $CFG->cybrary_enabletimedposts = false;
        $this->setAdminUser();

        // Two discussions with identical timemodified ignore each other.
        $record->timemodified++;
        $DB->update_record('cybrary_discussions', (object) array('id' => $disc3->id, 'timemodified' => $record->timemodified));
        $DB->update_record('cybrary_discussions', (object) array('id' => $disc2->id, 'timemodified' => $record->timemodified));
        $disc2 = $DB->get_record('cybrary_discussions', array('id' => $disc2->id));
        $disc3 = $DB->get_record('cybrary_discussions', array('id' => $disc3->id));

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc2, $cybrary);
        $this->assertEquals($disc13->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc3, $cybrary);
        $this->assertEquals($disc13->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);
    }

    /**
     * Test getting the neighbour threads of a blog-like cybrary.
     */
    public function test_cybrary_get_neighbours_blog() {
        global $CFG, $DB;
        $this->resetAfterTest();

        // Setup test data.
        $cybrarygen = $this->getDataGenerator()->get_plugin_generator('mod_cybrary');
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        $cybrary = $this->getDataGenerator()->create_module('cybrary', array('course' => $course->id, 'type' => 'blog'));
        $cm = get_coursemodule_from_instance('cybrary', $cybrary->id);
        $context = context_module::instance($cm->id);

        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user->id;
        $record->cybrary = $cybrary->id;
        $record->timemodified = time();
        $disc1 = $cybrarygen->create_discussion($record);
        $record->timemodified++;
        $disc2 = $cybrarygen->create_discussion($record);
        $record->timemodified++;
        $disc3 = $cybrarygen->create_discussion($record);
        $record->timemodified++;
        $disc4 = $cybrarygen->create_discussion($record);
        $record->timemodified++;
        $disc5 = $cybrarygen->create_discussion($record);

        // Getting the neighbours.
        $neighbours = cybrary_get_discussion_neighbours($cm, $disc1, $cybrary);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc2->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc2, $cybrary);
        $this->assertEquals($disc1->id, $neighbours['prev']->id);
        $this->assertEquals($disc3->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc3, $cybrary);
        $this->assertEquals($disc2->id, $neighbours['prev']->id);
        $this->assertEquals($disc4->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc4, $cybrary);
        $this->assertEquals($disc3->id, $neighbours['prev']->id);
        $this->assertEquals($disc5->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc5, $cybrary);
        $this->assertEquals($disc4->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Make sure that the thread's timemodified does not affect the order.
        $record->timemodified++;
        $disc1->timemodified = $record->timemodified;
        $DB->update_record('cybrary_discussions', $disc1);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc1, $cybrary);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc2->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc2, $cybrary);
        $this->assertEquals($disc1->id, $neighbours['prev']->id);
        $this->assertEquals($disc3->id, $neighbours['next']->id);

        // Add another blog post.
        $record->timemodified++;
        $disc6 = $cybrarygen->create_discussion($record);
        $neighbours = cybrary_get_discussion_neighbours($cm, $disc6, $cybrary);
        $this->assertEquals($disc5->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        $record->timemodified++;
        $disc7 = $cybrarygen->create_discussion($record);
        $neighbours = cybrary_get_discussion_neighbours($cm, $disc7, $cybrary);
        $this->assertEquals($disc6->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Adding timed discussions.
        $CFG->cybrary_enabletimedposts = true;
        $now = $record->timemodified;
        $past = $now - 60;
        $future = $now + 60;

        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user->id;
        $record->cybrary = $cybrary->id;
        $record->timestart = $past;
        $record->timeend = $future;
        $record->timemodified = $now;
        $record->timemodified++;
        $disc8 = $cybrarygen->create_discussion($record);
        $record->timemodified++;
        $record->timestart = $future;
        $record->timeend = 0;
        $disc9 = $cybrarygen->create_discussion($record);
        $record->timemodified++;
        $record->timestart = 0;
        $record->timeend = 0;
        $disc10 = $cybrarygen->create_discussion($record);
        $record->timemodified++;
        $record->timestart = 0;
        $record->timeend = $past;
        $disc11 = $cybrarygen->create_discussion($record);
        $record->timemodified++;
        $record->timestart = $past;
        $record->timeend = $future;
        $disc12 = $cybrarygen->create_discussion($record);

        // Admin user ignores the timed settings of discussions.
        $this->setAdminUser();
        $neighbours = cybrary_get_discussion_neighbours($cm, $disc8, $cybrary);
        $this->assertEquals($disc7->id, $neighbours['prev']->id);
        $this->assertEquals($disc9->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc9, $cybrary);
        $this->assertEquals($disc8->id, $neighbours['prev']->id);
        $this->assertEquals($disc10->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc10, $cybrary);
        $this->assertEquals($disc9->id, $neighbours['prev']->id);
        $this->assertEquals($disc11->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc11, $cybrary);
        $this->assertEquals($disc10->id, $neighbours['prev']->id);
        $this->assertEquals($disc12->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc12, $cybrary);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Normal user can see their own timed discussions.
        $this->setUser($user);
        $neighbours = cybrary_get_discussion_neighbours($cm, $disc8, $cybrary);
        $this->assertEquals($disc7->id, $neighbours['prev']->id);
        $this->assertEquals($disc9->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc9, $cybrary);
        $this->assertEquals($disc8->id, $neighbours['prev']->id);
        $this->assertEquals($disc10->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc10, $cybrary);
        $this->assertEquals($disc9->id, $neighbours['prev']->id);
        $this->assertEquals($disc11->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc11, $cybrary);
        $this->assertEquals($disc10->id, $neighbours['prev']->id);
        $this->assertEquals($disc12->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc12, $cybrary);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Normal user does not ignore timed settings.
        $this->setUser($user2);
        $neighbours = cybrary_get_discussion_neighbours($cm, $disc8, $cybrary);
        $this->assertEquals($disc7->id, $neighbours['prev']->id);
        $this->assertEquals($disc10->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc10, $cybrary);
        $this->assertEquals($disc8->id, $neighbours['prev']->id);
        $this->assertEquals($disc12->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc12, $cybrary);
        $this->assertEquals($disc10->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Reset to normal mode.
        $CFG->cybrary_enabletimedposts = false;
        $this->setAdminUser();

        // Two blog posts with identical creation time ignore each other.
        $record->timemodified++;
        $DB->update_record('cybrary_posts', (object) array('id' => $disc2->firstpost, 'created' => $record->timemodified));
        $DB->update_record('cybrary_posts', (object) array('id' => $disc3->firstpost, 'created' => $record->timemodified));

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc2, $cybrary);
        $this->assertEquals($disc12->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        $neighbours = cybrary_get_discussion_neighbours($cm, $disc3, $cybrary);
        $this->assertEquals($disc12->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);
    }

    /**
     * Test getting the neighbour threads of a discussion.
     */
    public function test_cybrary_get_neighbours_with_groups() {
        $this->resetAfterTest();

        // Setup test data.
        $cybrarygen = $this->getDataGenerator()->get_plugin_generator('mod_cybrary');
        $course = $this->getDataGenerator()->create_course();
        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id);
        $this->getDataGenerator()->create_group_member(array('userid' => $user1->id, 'groupid' => $group1->id));

        $cybrary1 = $this->getDataGenerator()->create_module('cybrary', array('course' => $course->id, 'groupmode' => VISIBLEGROUPS));
        $cybrary2 = $this->getDataGenerator()->create_module('cybrary', array('course' => $course->id, 'groupmode' => SEPARATEGROUPS));
        $cm1 = get_coursemodule_from_instance('cybrary', $cybrary1->id);
        $cm2 = get_coursemodule_from_instance('cybrary', $cybrary2->id);
        $context1 = context_module::instance($cm1->id);
        $context2 = context_module::instance($cm2->id);

        // Creating discussions in both cybraries.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user1->id;
        $record->cybrary = $cybrary1->id;
        $record->groupid = $group1->id;
        $record->timemodified = time();
        $disc11 = $cybrarygen->create_discussion($record);
        $record->cybrary = $cybrary2->id;
        $record->timemodified++;
        $disc21 = $cybrarygen->create_discussion($record);

        $record->timemodified++;
        $record->userid = $user2->id;
        $record->cybrary = $cybrary1->id;
        $record->groupid = $group2->id;
        $disc12 = $cybrarygen->create_discussion($record);
        $record->cybrary = $cybrary2->id;
        $disc22 = $cybrarygen->create_discussion($record);

        $record->timemodified++;
        $record->userid = $user1->id;
        $record->cybrary = $cybrary1->id;
        $record->groupid = null;
        $disc13 = $cybrarygen->create_discussion($record);
        $record->cybrary = $cybrary2->id;
        $disc23 = $cybrarygen->create_discussion($record);

        $record->timemodified++;
        $record->userid = $user2->id;
        $record->cybrary = $cybrary1->id;
        $record->groupid = $group2->id;
        $disc14 = $cybrarygen->create_discussion($record);
        $record->cybrary = $cybrary2->id;
        $disc24 = $cybrarygen->create_discussion($record);

        $record->timemodified++;
        $record->userid = $user1->id;
        $record->cybrary = $cybrary1->id;
        $record->groupid = $group1->id;
        $disc15 = $cybrarygen->create_discussion($record);
        $record->cybrary = $cybrary2->id;
        $disc25 = $cybrarygen->create_discussion($record);

        // Admin user can see all groups.
        $this->setAdminUser();
        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc11, $cybrary1);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc12->id, $neighbours['next']->id);
        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc21, $cybrary2);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc22->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc12, $cybrary1);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc13->id, $neighbours['next']->id);
        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc22, $cybrary2);
        $this->assertEquals($disc21->id, $neighbours['prev']->id);
        $this->assertEquals($disc23->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc13, $cybrary1);
        $this->assertEquals($disc12->id, $neighbours['prev']->id);
        $this->assertEquals($disc14->id, $neighbours['next']->id);
        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc23, $cybrary2);
        $this->assertEquals($disc22->id, $neighbours['prev']->id);
        $this->assertEquals($disc24->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc14, $cybrary1);
        $this->assertEquals($disc13->id, $neighbours['prev']->id);
        $this->assertEquals($disc15->id, $neighbours['next']->id);
        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc24, $cybrary2);
        $this->assertEquals($disc23->id, $neighbours['prev']->id);
        $this->assertEquals($disc25->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc15, $cybrary1);
        $this->assertEquals($disc14->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);
        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc25, $cybrary2);
        $this->assertEquals($disc24->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Admin user is only viewing group 1.
        $_POST['group'] = $group1->id;
        $this->assertEquals($group1->id, groups_get_activity_group($cm1, true));
        $this->assertEquals($group1->id, groups_get_activity_group($cm2, true));

        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc11, $cybrary1);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc13->id, $neighbours['next']->id);
        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc21, $cybrary2);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc23->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc13, $cybrary1);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc15->id, $neighbours['next']->id);
        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc23, $cybrary2);
        $this->assertEquals($disc21->id, $neighbours['prev']->id);
        $this->assertEquals($disc25->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc15, $cybrary1);
        $this->assertEquals($disc13->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);
        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc25, $cybrary2);
        $this->assertEquals($disc23->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Normal user viewing non-grouped posts (this is only possible in visible groups).
        $this->setUser($user1);
        $_POST['group'] = 0;
        $this->assertEquals(0, groups_get_activity_group($cm1, true));

        // They can see anything in visible groups.
        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc12, $cybrary1);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc13->id, $neighbours['next']->id);
        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc13, $cybrary1);
        $this->assertEquals($disc12->id, $neighbours['prev']->id);
        $this->assertEquals($disc14->id, $neighbours['next']->id);

        // Normal user, orphan of groups, can only see non-grouped posts in separate groups.
        $this->setUser($user2);
        $_POST['group'] = 0;
        $this->assertEquals(0, groups_get_activity_group($cm2, true));

        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc23, $cybrary2);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEmpty($neighbours['next']);

        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc22, $cybrary2);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc23->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc24, $cybrary2);
        $this->assertEquals($disc23->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Switching to viewing group 1.
        $this->setUser($user1);
        $_POST['group'] = $group1->id;
        $this->assertEquals($group1->id, groups_get_activity_group($cm1, true));
        $this->assertEquals($group1->id, groups_get_activity_group($cm2, true));

        // They can see non-grouped or same group.
        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc11, $cybrary1);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc13->id, $neighbours['next']->id);
        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc21, $cybrary2);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc23->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc13, $cybrary1);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc15->id, $neighbours['next']->id);
        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc23, $cybrary2);
        $this->assertEquals($disc21->id, $neighbours['prev']->id);
        $this->assertEquals($disc25->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc15, $cybrary1);
        $this->assertEquals($disc13->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);
        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc25, $cybrary2);
        $this->assertEquals($disc23->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Querying the neighbours of a discussion passing the wrong CM.
        $this->setExpectedException('coding_exception');
        cybrary_get_discussion_neighbours($cm2, $disc11, $cybrary2);
    }

    /**
     * Test getting the neighbour threads of a blog-like cybrary with groups involved.
     */
    public function test_cybrary_get_neighbours_with_groups_blog() {
        $this->resetAfterTest();

        // Setup test data.
        $cybrarygen = $this->getDataGenerator()->get_plugin_generator('mod_cybrary');
        $course = $this->getDataGenerator()->create_course();
        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id);
        $this->getDataGenerator()->create_group_member(array('userid' => $user1->id, 'groupid' => $group1->id));

        $cybrary1 = $this->getDataGenerator()->create_module('cybrary', array('course' => $course->id, 'type' => 'blog',
                'groupmode' => VISIBLEGROUPS));
        $cybrary2 = $this->getDataGenerator()->create_module('cybrary', array('course' => $course->id, 'type' => 'blog',
                'groupmode' => SEPARATEGROUPS));
        $cm1 = get_coursemodule_from_instance('cybrary', $cybrary1->id);
        $cm2 = get_coursemodule_from_instance('cybrary', $cybrary2->id);
        $context1 = context_module::instance($cm1->id);
        $context2 = context_module::instance($cm2->id);

        // Creating blog posts in both cybraries.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user1->id;
        $record->cybrary = $cybrary1->id;
        $record->groupid = $group1->id;
        $record->timemodified = time();
        $disc11 = $cybrarygen->create_discussion($record);
        $record->cybrary = $cybrary2->id;
        $record->timemodified++;
        $disc21 = $cybrarygen->create_discussion($record);

        $record->timemodified++;
        $record->userid = $user2->id;
        $record->cybrary = $cybrary1->id;
        $record->groupid = $group2->id;
        $disc12 = $cybrarygen->create_discussion($record);
        $record->cybrary = $cybrary2->id;
        $disc22 = $cybrarygen->create_discussion($record);

        $record->timemodified++;
        $record->userid = $user1->id;
        $record->cybrary = $cybrary1->id;
        $record->groupid = null;
        $disc13 = $cybrarygen->create_discussion($record);
        $record->cybrary = $cybrary2->id;
        $disc23 = $cybrarygen->create_discussion($record);

        $record->timemodified++;
        $record->userid = $user2->id;
        $record->cybrary = $cybrary1->id;
        $record->groupid = $group2->id;
        $disc14 = $cybrarygen->create_discussion($record);
        $record->cybrary = $cybrary2->id;
        $disc24 = $cybrarygen->create_discussion($record);

        $record->timemodified++;
        $record->userid = $user1->id;
        $record->cybrary = $cybrary1->id;
        $record->groupid = $group1->id;
        $disc15 = $cybrarygen->create_discussion($record);
        $record->cybrary = $cybrary2->id;
        $disc25 = $cybrarygen->create_discussion($record);

        // Admin user can see all groups.
        $this->setAdminUser();
        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc11, $cybrary1);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc12->id, $neighbours['next']->id);
        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc21, $cybrary2);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc22->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc12, $cybrary1);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc13->id, $neighbours['next']->id);
        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc22, $cybrary2);
        $this->assertEquals($disc21->id, $neighbours['prev']->id);
        $this->assertEquals($disc23->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc13, $cybrary1);
        $this->assertEquals($disc12->id, $neighbours['prev']->id);
        $this->assertEquals($disc14->id, $neighbours['next']->id);
        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc23, $cybrary2);
        $this->assertEquals($disc22->id, $neighbours['prev']->id);
        $this->assertEquals($disc24->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc14, $cybrary1);
        $this->assertEquals($disc13->id, $neighbours['prev']->id);
        $this->assertEquals($disc15->id, $neighbours['next']->id);
        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc24, $cybrary2);
        $this->assertEquals($disc23->id, $neighbours['prev']->id);
        $this->assertEquals($disc25->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc15, $cybrary1);
        $this->assertEquals($disc14->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);
        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc25, $cybrary2);
        $this->assertEquals($disc24->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Admin user is only viewing group 1.
        $_POST['group'] = $group1->id;
        $this->assertEquals($group1->id, groups_get_activity_group($cm1, true));
        $this->assertEquals($group1->id, groups_get_activity_group($cm2, true));

        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc11, $cybrary1);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc13->id, $neighbours['next']->id);
        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc21, $cybrary2);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc23->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc13, $cybrary1);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc15->id, $neighbours['next']->id);
        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc23, $cybrary2);
        $this->assertEquals($disc21->id, $neighbours['prev']->id);
        $this->assertEquals($disc25->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc15, $cybrary1);
        $this->assertEquals($disc13->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);
        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc25, $cybrary2);
        $this->assertEquals($disc23->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Normal user viewing non-grouped posts (this is only possible in visible groups).
        $this->setUser($user1);
        $_POST['group'] = 0;
        $this->assertEquals(0, groups_get_activity_group($cm1, true));

        // They can see anything in visible groups.
        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc12, $cybrary1);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc13->id, $neighbours['next']->id);
        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc13, $cybrary1);
        $this->assertEquals($disc12->id, $neighbours['prev']->id);
        $this->assertEquals($disc14->id, $neighbours['next']->id);

        // Normal user, orphan of groups, can only see non-grouped posts in separate groups.
        $this->setUser($user2);
        $_POST['group'] = 0;
        $this->assertEquals(0, groups_get_activity_group($cm2, true));

        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc23, $cybrary2);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEmpty($neighbours['next']);

        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc22, $cybrary2);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc23->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc24, $cybrary2);
        $this->assertEquals($disc23->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Switching to viewing group 1.
        $this->setUser($user1);
        $_POST['group'] = $group1->id;
        $this->assertEquals($group1->id, groups_get_activity_group($cm1, true));
        $this->assertEquals($group1->id, groups_get_activity_group($cm2, true));

        // They can see non-grouped or same group.
        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc11, $cybrary1);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc13->id, $neighbours['next']->id);
        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc21, $cybrary2);
        $this->assertEmpty($neighbours['prev']);
        $this->assertEquals($disc23->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc13, $cybrary1);
        $this->assertEquals($disc11->id, $neighbours['prev']->id);
        $this->assertEquals($disc15->id, $neighbours['next']->id);
        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc23, $cybrary2);
        $this->assertEquals($disc21->id, $neighbours['prev']->id);
        $this->assertEquals($disc25->id, $neighbours['next']->id);

        $neighbours = cybrary_get_discussion_neighbours($cm1, $disc15, $cybrary1);
        $this->assertEquals($disc13->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);
        $neighbours = cybrary_get_discussion_neighbours($cm2, $disc25, $cybrary2);
        $this->assertEquals($disc23->id, $neighbours['prev']->id);
        $this->assertEmpty($neighbours['next']);

        // Querying the neighbours of a discussion passing the wrong CM.
        $this->setExpectedException('coding_exception');
        cybrary_get_discussion_neighbours($cm2, $disc11, $cybrary2);
    }

    public function test_count_discussion_replies_basic() {
        list($cybrary, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);

        // Count the discussion replies in the cybrary.
        $result = cybrary_count_discussion_replies($cybrary->id);
        $this->assertCount(10, $result);
    }

    public function test_count_discussion_replies_limited() {
        list($cybrary, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Adding limits shouldn't make a difference.
        $result = cybrary_count_discussion_replies($cybrary->id, "", 20);
        $this->assertCount(10, $result);
    }

    public function test_count_discussion_replies_paginated() {
        list($cybrary, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Adding paging shouldn't make any difference.
        $result = cybrary_count_discussion_replies($cybrary->id, "", -1, 0, 100);
        $this->assertCount(10, $result);
    }

    public function test_count_discussion_replies_paginated_sorted() {
        list($cybrary, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Specifying the cybrariesort should also give a good result. This follows a different path.
        $result = cybrary_count_discussion_replies($cybrary->id, "d.id asc", -1, 0, 100);
        $this->assertCount(10, $result);
        foreach ($result as $row) {
            // Grab the first discussionid.
            $discussionid = array_shift($discussionids);
            $this->assertEquals($discussionid, $row->discussion);
        }
    }

    public function test_count_discussion_replies_limited_sorted() {
        list($cybrary, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Adding limits, and a cybrariesort shouldn't make a difference.
        $result = cybrary_count_discussion_replies($cybrary->id, "d.id asc", 20);
        $this->assertCount(10, $result);
        foreach ($result as $row) {
            // Grab the first discussionid.
            $discussionid = array_shift($discussionids);
            $this->assertEquals($discussionid, $row->discussion);
        }
    }

    public function test_count_discussion_replies_paginated_sorted_small() {
        list($cybrary, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Grabbing a smaller subset and they should be ordered as expected.
        $result = cybrary_count_discussion_replies($cybrary->id, "d.id asc", -1, 0, 5);
        $this->assertCount(5, $result);
        foreach ($result as $row) {
            // Grab the first discussionid.
            $discussionid = array_shift($discussionids);
            $this->assertEquals($discussionid, $row->discussion);
        }
    }

    public function test_count_discussion_replies_paginated_sorted_small_reverse() {
        list($cybrary, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Grabbing a smaller subset and they should be ordered as expected.
        $result = cybrary_count_discussion_replies($cybrary->id, "d.id desc", -1, 0, 5);
        $this->assertCount(5, $result);
        foreach ($result as $row) {
            // Grab the last discussionid.
            $discussionid = array_pop($discussionids);
            $this->assertEquals($discussionid, $row->discussion);
        }
    }

    public function test_count_discussion_replies_limited_sorted_small_reverse() {
        list($cybrary, $discussionids) = $this->create_multiple_discussions_with_replies(10, 5);
        // Adding limits, and a cybrariesort shouldn't make a difference.
        $result = cybrary_count_discussion_replies($cybrary->id, "d.id desc", 5);
        $this->assertCount(5, $result);
        foreach ($result as $row) {
            // Grab the last discussionid.
            $discussionid = array_pop($discussionids);
            $this->assertEquals($discussionid, $row->discussion);
        }
    }

    public function test_cybrary_view() {
        global $CFG;

        $CFG->enablecompletion = 1;
        $this->resetAfterTest();

        // Setup test data.
        $course = $this->getDataGenerator()->create_course(array('enablecompletion' => 1));
        $cybrary = $this->getDataGenerator()->create_module('cybrary', array('course' => $course->id),
                                                            array('completion' => 2, 'completionview' => 1));
        $context = context_module::instance($cybrary->cmid);
        $cm = get_coursemodule_from_instance('cybrary', $cybrary->id);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $this->setAdminUser();
        cybrary_view($cybrary, $course, $cm, $context);

        $events = $sink->get_events();
        // 2 additional events thanks to completion.
        $this->assertCount(3, $events);
        $event = array_pop($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_cybrary\event\course_module_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $url = new \moodle_url('/mod/cybrary/view.php', array('f' => $cybrary->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

        // Check completion status.
        $completion = new completion_info($course);
        $completiondata = $completion->get_data($cm);
        $this->assertEquals(1, $completiondata->completionstate);

    }

    /**
     * Test cybrary_discussion_view.
     */
    public function test_cybrary_discussion_view() {
        global $CFG, $USER;

        $this->resetAfterTest();

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $cybrary = $this->getDataGenerator()->create_module('cybrary', array('course' => $course->id));
        $discussion = $this->create_single_discussion_with_replies($cybrary, $USER, 2);

        $context = context_module::instance($cybrary->cmid);
        $cm = get_coursemodule_from_instance('cybrary', $cybrary->id);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $this->setAdminUser();
        cybrary_discussion_view($context, $cybrary, $discussion);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_pop($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_cybrary\event\discussion_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'cybrary', 'view discussion', "discuss.php?d={$discussion->id}",
            $discussion->id, $cybrary->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());

    }

    /**
     * Create a new course, cybrary, and user with a number of discussions and replies.
     *
     * @param int $discussioncount The number of discussions to create
     * @param int $replycount The number of replies to create in each discussion
     * @return array Containing the created cybrary object, and the ids of the created discussions.
     */
    protected function create_multiple_discussions_with_replies($discussioncount, $replycount) {
        $this->resetAfterTest();

        // Setup the content.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $record = new stdClass();
        $record->course = $course->id;
        $cybrary = $this->getDataGenerator()->create_module('cybrary', $record);

        // Create 10 discussions with replies.
        $discussionids = array();
        for ($i = 0; $i < $discussioncount; $i++) {
            $discussion = $this->create_single_discussion_with_replies($cybrary, $user, $replycount);
            $discussionids[] = $discussion->id;
        }
        return array($cybrary, $discussionids);
    }

    /**
     * Create a discussion with a number of replies.
     *
     * @param object $cybrary The cybrary which has been created
     * @param object $user The user making the discussion and replies
     * @param int $replycount The number of replies
     * @return object $discussion
     */
    protected function create_single_discussion_with_replies($cybrary, $user, $replycount) {
        global $DB;

        $generator = self::getDataGenerator()->get_plugin_generator('mod_cybrary');

        $record = new stdClass();
        $record->course = $cybrary->course;
        $record->cybrary = $cybrary->id;
        $record->userid = $user->id;
        $discussion = $generator->create_discussion($record);

        // Retrieve the first post.
        $replyto = $DB->get_record('cybrary_posts', array('discussion' => $discussion->id));

        // Create the replies.
        $post = new stdClass();
        $post->userid = $user->id;
        $post->discussion = $discussion->id;
        $post->parent = $replyto->id;

        for ($i = 0; $i < $replycount; $i++) {
            $generator->create_post($post);
        }

        return $discussion;
    }

    /**
     * Tests for mod_cybrary_rating_can_see_item_ratings().
     *
     * @throws coding_exception
     * @throws rating_exception
     */
    public function test_mod_cybrary_rating_can_see_item_ratings() {
        global $DB;

        $this->resetAfterTest();

        // Setup test data.
        $course = new stdClass();
        $course->groupmode = SEPARATEGROUPS;
        $course->groupmodeforce = true;
        $course = $this->getDataGenerator()->create_course($course);
        $cybrary = $this->getDataGenerator()->create_module('cybrary', array('course' => $course->id));
        $generator = self::getDataGenerator()->get_plugin_generator('mod_cybrary');
        $cm = get_coursemodule_from_instance('cybrary', $cybrary->id);
        $context = context_module::instance($cm->id);

        // Create users.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();

        // Groups and stuff.
        $role = $DB->get_record('role', array('shortname' => 'teacher'), '*', MUST_EXIST);
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, $role->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, $role->id);
        $this->getDataGenerator()->enrol_user($user3->id, $course->id, $role->id);
        $this->getDataGenerator()->enrol_user($user4->id, $course->id, $role->id);

        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        groups_add_member($group1, $user1);
        groups_add_member($group1, $user2);
        groups_add_member($group2, $user3);
        groups_add_member($group2, $user4);

        $record = new stdClass();
        $record->course = $cybrary->course;
        $record->cybrary = $cybrary->id;
        $record->userid = $user1->id;
        $record->groupid = $group1->id;
        $discussion = $generator->create_discussion($record);

        // Retrieve the first post.
        $post = $DB->get_record('cybrary_posts', array('discussion' => $discussion->id));

        $ratingoptions = new stdClass;
        $ratingoptions->context = $context;
        $ratingoptions->ratingarea = 'post';
        $ratingoptions->component = 'mod_cybrary';
        $ratingoptions->itemid  = $post->id;
        $ratingoptions->scaleid = 2;
        $ratingoptions->userid  = $user2->id;
        $rating = new rating($ratingoptions);
        $rating->update_rating(2);

        // Now try to access it as various users.
        unassign_capability('moodle/site:accessallgroups', $role->id);
        $params = array('contextid' => 2,
                        'component' => 'mod_cybrary',
                        'ratingarea' => 'post',
                        'itemid' => $post->id,
                        'scaleid' => 2);
        $this->setUser($user1);
        $this->assertTrue(mod_cybrary_rating_can_see_item_ratings($params));
        $this->setUser($user2);
        $this->assertTrue(mod_cybrary_rating_can_see_item_ratings($params));
        $this->setUser($user3);
        $this->assertFalse(mod_cybrary_rating_can_see_item_ratings($params));
        $this->setUser($user4);
        $this->assertFalse(mod_cybrary_rating_can_see_item_ratings($params));

        // Now try with accessallgroups cap and make sure everything is visible.
        assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $role->id, $context->id);
        $this->setUser($user1);
        $this->assertTrue(mod_cybrary_rating_can_see_item_ratings($params));
        $this->setUser($user2);
        $this->assertTrue(mod_cybrary_rating_can_see_item_ratings($params));
        $this->setUser($user3);
        $this->assertTrue(mod_cybrary_rating_can_see_item_ratings($params));
        $this->setUser($user4);
        $this->assertTrue(mod_cybrary_rating_can_see_item_ratings($params));

        // Change group mode and verify visibility.
        $course->groupmode = VISIBLEGROUPS;
        $DB->update_record('course', $course);
        unassign_capability('moodle/site:accessallgroups', $role->id);
        $this->setUser($user1);
        $this->assertTrue(mod_cybrary_rating_can_see_item_ratings($params));
        $this->setUser($user2);
        $this->assertTrue(mod_cybrary_rating_can_see_item_ratings($params));
        $this->setUser($user3);
        $this->assertTrue(mod_cybrary_rating_can_see_item_ratings($params));
        $this->setUser($user4);
        $this->assertTrue(mod_cybrary_rating_can_see_item_ratings($params));

    }

    /**
     * Test cybrary_get_discussions
     */
    public function test_cybrary_get_discussions_with_groups() {
        global $DB;

        $this->resetAfterTest(true);

        // Create course to add the module.
        $course = self::getDataGenerator()->create_course(array('groupmode' => VISIBLEGROUPS, 'groupmodeforce' => 0));
        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        $role = $DB->get_record('role', array('shortname' => 'student'), '*', MUST_EXIST);
        self::getDataGenerator()->enrol_user($user1->id, $course->id, $role->id);
        self::getDataGenerator()->enrol_user($user2->id, $course->id, $role->id);
        self::getDataGenerator()->enrol_user($user3->id, $course->id, $role->id);

        // Cybrary forcing separate gropus.
        $record = new stdClass();
        $record->course = $course->id;
        $cybrary = self::getDataGenerator()->create_module('cybrary', $record, array('groupmode' => SEPARATEGROUPS));
        $cm = get_coursemodule_from_instance('cybrary', $cybrary->id);

        // Create groups.
        $group1 = self::getDataGenerator()->create_group(array('courseid' => $course->id));
        $group2 = self::getDataGenerator()->create_group(array('courseid' => $course->id));
        $group3 = self::getDataGenerator()->create_group(array('courseid' => $course->id));

        // Add the user1 to g1 and g2 groups.
        groups_add_member($group1->id, $user1->id);
        groups_add_member($group2->id, $user1->id);

        // Add the user 2 and 3 to only one group.
        groups_add_member($group1->id, $user2->id);
        groups_add_member($group3->id, $user3->id);

        // Add a few discussions.
        $record = array();
        $record['course'] = $course->id;
        $record['cybrary'] = $cybrary->id;
        $record['userid'] = $user1->id;
        $record['groupid'] = $group1->id;
        $discussiong1u1 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        $record['groupid'] = $group2->id;
        $discussiong2u1 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        $record['userid'] = $user2->id;
        $record['groupid'] = $group1->id;
        $discussiong1u2 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        $record['userid'] = $user3->id;
        $record['groupid'] = $group3->id;
        $discussiong3u3 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        self::setUser($user1);
        // Test retrieve discussions not passing the groupid parameter. We will receive only first group discussions.
        $discussions = cybrary_get_discussions($cm);
        self::assertCount(2, $discussions);
        foreach ($discussions as $discussion) {
            self::assertEquals($group1->id, $discussion->groupid);
        }

        // Get all my discussions.
        $discussions = cybrary_get_discussions($cm, '', true, -1, -1, false, -1, 0, 0);
        self::assertCount(3, $discussions);

        // Get all my g1 discussions.
        $discussions = cybrary_get_discussions($cm, '', true, -1, -1, false, -1, 0, $group1->id);
        self::assertCount(2, $discussions);
        foreach ($discussions as $discussion) {
            self::assertEquals($group1->id, $discussion->groupid);
        }

        // Get all my g2 discussions.
        $discussions = cybrary_get_discussions($cm, '', true, -1, -1, false, -1, 0, $group2->id);
        self::assertCount(1, $discussions);
        $discussion = array_shift($discussions);
        self::assertEquals($group2->id, $discussion->groupid);
        self::assertEquals($user1->id, $discussion->userid);
        self::assertEquals($discussiong2u1->id, $discussion->discussion);

        // Get all my g3 discussions (I'm not enrolled in that group).
        $discussions = cybrary_get_discussions($cm, '', true, -1, -1, false, -1, 0, $group3->id);
        self::assertCount(0, $discussions);

        // This group does not exist.
        $discussions = cybrary_get_discussions($cm, '', true, -1, -1, false, -1, 0, $group3->id + 1000);
        self::assertCount(0, $discussions);

        self::setUser($user2);

        // Test retrieve discussions not passing the groupid parameter. We will receive only first group discussions.
        $discussions = cybrary_get_discussions($cm);
        self::assertCount(2, $discussions);
        foreach ($discussions as $discussion) {
            self::assertEquals($group1->id, $discussion->groupid);
        }

        // Get all my viewable discussions.
        $discussions = cybrary_get_discussions($cm, '', true, -1, -1, false, -1, 0, 0);
        self::assertCount(2, $discussions);
        foreach ($discussions as $discussion) {
            self::assertEquals($group1->id, $discussion->groupid);
        }

        // Get all my g2 discussions (I'm not enrolled in that group).
        $discussions = cybrary_get_discussions($cm, '', true, -1, -1, false, -1, 0, $group2->id);
        self::assertCount(0, $discussions);

        // Get all my g3 discussions (I'm not enrolled in that group).
        $discussions = cybrary_get_discussions($cm, '', true, -1, -1, false, -1, 0, $group3->id);
        self::assertCount(0, $discussions);

    }

    /**
     * Test cybrary_user_has_posted_discussion with no groups.
     */
    public function test_cybrary_user_has_posted_discussion_no_groups() {
        global $CFG;

        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();
        $author = self::getDataGenerator()->create_user();
        $other = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($author->id, $course->id);
        $cybrary = self::getDataGenerator()->create_module('cybrary', (object) ['course' => $course->id ]);

        self::setUser($author);

        // Neither user has posted.
        $this->assertFalse(cybrary_user_has_posted_discussion($cybrary->id, $author->id));
        $this->assertFalse(cybrary_user_has_posted_discussion($cybrary->id, $other->id));

        // Post in the cybrary.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $author->id;
        $record->cybrary = $cybrary->id;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        // The author has now posted, but the other user has not.
        $this->assertTrue(cybrary_user_has_posted_discussion($cybrary->id, $author->id));
        $this->assertFalse(cybrary_user_has_posted_discussion($cybrary->id, $other->id));
    }

    /**
     * Test cybrary_user_has_posted_discussion with multiple cybraries
     */
    public function test_cybrary_user_has_posted_discussion_multiple_cybraries() {
        global $CFG;

        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();
        $author = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($author->id, $course->id);
        $cybrary1 = self::getDataGenerator()->create_module('cybrary', (object) ['course' => $course->id ]);
        $cybrary2 = self::getDataGenerator()->create_module('cybrary', (object) ['course' => $course->id ]);

        self::setUser($author);

        // No post in either cybrary.
        $this->assertFalse(cybrary_user_has_posted_discussion($cybrary1->id, $author->id));
        $this->assertFalse(cybrary_user_has_posted_discussion($cybrary2->id, $author->id));

        // Post in the cybrary.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $author->id;
        $record->cybrary = $cybrary1->id;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        // The author has now posted in cybrary1, but not cybrary2.
        $this->assertTrue(cybrary_user_has_posted_discussion($cybrary1->id, $author->id));
        $this->assertFalse(cybrary_user_has_posted_discussion($cybrary2->id, $author->id));
    }

    /**
     * Test cybrary_user_has_posted_discussion with multiple groups.
     */
    public function test_cybrary_user_has_posted_discussion_multiple_groups() {
        global $CFG;

        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();
        $author = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($author->id, $course->id);

        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        groups_add_member($group1->id, $author->id);
        groups_add_member($group2->id, $author->id);

        $cybrary = self::getDataGenerator()->create_module('cybrary', (object) ['course' => $course->id ], [
                    'groupmode' => SEPARATEGROUPS,
                ]);

        self::setUser($author);

        // The user has not posted in either group.
        $this->assertFalse(cybrary_user_has_posted_discussion($cybrary->id, $author->id));
        $this->assertFalse(cybrary_user_has_posted_discussion($cybrary->id, $author->id, $group1->id));
        $this->assertFalse(cybrary_user_has_posted_discussion($cybrary->id, $author->id, $group2->id));

        // Post in one group.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $author->id;
        $record->cybrary = $cybrary->id;
        $record->groupid = $group1->id;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        // The author has now posted in one group, but the other user has not.
        $this->assertTrue(cybrary_user_has_posted_discussion($cybrary->id, $author->id));
        $this->assertTrue(cybrary_user_has_posted_discussion($cybrary->id, $author->id, $group1->id));
        $this->assertFalse(cybrary_user_has_posted_discussion($cybrary->id, $author->id, $group2->id));

        // Post in the other group.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $author->id;
        $record->cybrary = $cybrary->id;
        $record->groupid = $group2->id;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        // The author has now posted in one group, but the other user has not.
        $this->assertTrue(cybrary_user_has_posted_discussion($cybrary->id, $author->id));
        $this->assertTrue(cybrary_user_has_posted_discussion($cybrary->id, $author->id, $group1->id));
        $this->assertTrue(cybrary_user_has_posted_discussion($cybrary->id, $author->id, $group2->id));
    }

    /**
     * Tests the mod_cybrary_myprofile_navigation() function.
     */
    public function test_mod_cybrary_myprofile_navigation() {
        $this->resetAfterTest(true);

        // Set up the test.
        $tree = new \core_user\output\myprofile\tree();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $iscurrentuser = true;

        // Set as the current user.
        $this->setUser($user);

        // Check the node tree is correct.
        mod_cybrary_myprofile_navigation($tree, $user, $iscurrentuser, $course);
        $reflector = new ReflectionObject($tree);
        $nodes = $reflector->getProperty('nodes');
        $nodes->setAccessible(true);
        $this->assertArrayHasKey('cybraryposts', $nodes->getValue($tree));
        $this->assertArrayHasKey('cybrarydiscussions', $nodes->getValue($tree));
    }

    /**
     * Tests the mod_cybrary_myprofile_navigation() function as a guest.
     */
    public function test_mod_cybrary_myprofile_navigation_as_guest() {
        global $USER;

        $this->resetAfterTest(true);

        // Set up the test.
        $tree = new \core_user\output\myprofile\tree();
        $course = $this->getDataGenerator()->create_course();
        $iscurrentuser = true;

        // Set user as guest.
        $this->setGuestUser();

        // Check the node tree is correct.
        mod_cybrary_myprofile_navigation($tree, $USER, $iscurrentuser, $course);
        $reflector = new ReflectionObject($tree);
        $nodes = $reflector->getProperty('nodes');
        $nodes->setAccessible(true);
        $this->assertArrayNotHasKey('cybraryposts', $nodes->getValue($tree));
        $this->assertArrayNotHasKey('cybrarydiscussions', $nodes->getValue($tree));
    }

    /**
     * Tests the mod_cybrary_myprofile_navigation() function as a user viewing another user's profile.
     */
    public function test_mod_cybrary_myprofile_navigation_different_user() {
        $this->resetAfterTest(true);

        // Set up the test.
        $tree = new \core_user\output\myprofile\tree();
        $user = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $iscurrentuser = true;

        // Set to different user's profile.
        $this->setUser($user2);

        // Check the node tree is correct.
        mod_cybrary_myprofile_navigation($tree, $user, $iscurrentuser, $course);
        $reflector = new ReflectionObject($tree);
        $nodes = $reflector->getProperty('nodes');
        $nodes->setAccessible(true);
        $this->assertArrayHasKey('cybraryposts', $nodes->getValue($tree));
        $this->assertArrayHasKey('cybrarydiscussions', $nodes->getValue($tree));
    }

    public function test_print_overview() {
        $this->resetAfterTest();
        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();

        // Create an author user.
        $author = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($author->id, $course1->id);
        $this->getDataGenerator()->enrol_user($author->id, $course2->id);

        // Create a viewer user.
        $viewer = self::getDataGenerator()->create_user((object) array('trackcybraries' => 1));
        $this->getDataGenerator()->enrol_user($viewer->id, $course1->id);
        $this->getDataGenerator()->enrol_user($viewer->id, $course2->id);

        // Create two cybraries - one in each course.
        $record = new stdClass();
        $record->course = $course1->id;
        $cybrary1 = self::getDataGenerator()->create_module('cybrary', (object) array('course' => $course1->id));
        $cybrary2 = self::getDataGenerator()->create_module('cybrary', (object) array('course' => $course2->id));

        // A standard post in the cybrary.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $author->id;
        $record->cybrary = $cybrary1->id;
        $this->getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        $this->setUser($viewer->id);
        $courses = array(
            $course1->id => clone $course1,
            $course2->id => clone $course2,
        );

        foreach ($courses as $courseid => $course) {
            $courses[$courseid]->lastaccess = 0;
        }
        $results = array();
        cybrary_print_overview($courses, $results);

        // There should be one entry for course1, and no others.
        $this->assertCount(1, $results);

        // There should be one entry for a cybrary in course1.
        $this->assertCount(1, $results[$course1->id]);
        $this->assertArrayHasKey('cybrary', $results[$course1->id]);
    }

    public function test_print_overview_groups() {
        $this->resetAfterTest();
        $course1 = self::getDataGenerator()->create_course();
        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course1->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course1->id));

        // Create an author user.
        $author = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($author->id, $course1->id);

        // Create two viewer users - one in each group.
        $viewer1 = self::getDataGenerator()->create_user((object) array('trackcybraries' => 1));
        $this->getDataGenerator()->enrol_user($viewer1->id, $course1->id);
        $this->getDataGenerator()->create_group_member(array('userid' => $viewer1->id, 'groupid' => $group1->id));

        $viewer2 = self::getDataGenerator()->create_user((object) array('trackcybraries' => 1));
        $this->getDataGenerator()->enrol_user($viewer2->id, $course1->id);
        $this->getDataGenerator()->create_group_member(array('userid' => $viewer2->id, 'groupid' => $group2->id));

        // Create a cybrary.
        $record = new stdClass();
        $record->course = $course1->id;
        $cybrary1 = self::getDataGenerator()->create_module('cybrary', (object) array(
            'course'        => $course1->id,
            'groupmode'     => SEPARATEGROUPS,
        ));

        // A post in the cybrary for group1.
        $record = new stdClass();
        $record->course     = $course1->id;
        $record->userid     = $author->id;
        $record->cybrary      = $cybrary1->id;
        $record->groupid    = $group1->id;
        $this->getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        $course1->lastaccess = 0;
        $courses = array($course1->id => $course1);

        // As viewer1 (same group as post).
        $this->setUser($viewer1->id);
        $results = array();
        cybrary_print_overview($courses, $results);

        // There should be one entry for course1.
        $this->assertCount(1, $results);

        // There should be one entry for a cybrary in course1.
        $this->assertCount(1, $results[$course1->id]);
        $this->assertArrayHasKey('cybrary', $results[$course1->id]);

        $this->setUser($viewer2->id);
        $results = array();
        cybrary_print_overview($courses, $results);

        // There should be one entry for course1.
        $this->assertCount(0, $results);
    }

    /**
     * @dataProvider print_overview_timed_provider
     */
    public function test_print_overview_timed($config, $hasresult) {
        $this->resetAfterTest();
        $course1 = self::getDataGenerator()->create_course();

        // Create an author user.
        $author = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($author->id, $course1->id);

        // Create a viewer user.
        $viewer = self::getDataGenerator()->create_user((object) array('trackcybraries' => 1));
        $this->getDataGenerator()->enrol_user($viewer->id, $course1->id);

        // Create a cybrary.
        $record = new stdClass();
        $record->course = $course1->id;
        $cybrary1 = self::getDataGenerator()->create_module('cybrary', (object) array('course' => $course1->id));

        // A timed post with a timestart in the past (24 hours ago).
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $author->id;
        $record->cybrary = $cybrary1->id;
        if (isset($config['timestartmodifier'])) {
            $record->timestart = time() + $config['timestartmodifier'];
        }
        if (isset($config['timeendmodifier'])) {
            $record->timeend = time() + $config['timeendmodifier'];
        }
        $this->getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        $course1->lastaccess = 0;
        $courses = array($course1->id => $course1);

        // As viewer, check the cybrary_print_overview result.
        $this->setUser($viewer->id);
        $results = array();
        cybrary_print_overview($courses, $results);

        if ($hasresult) {
            // There should be one entry for course1.
            $this->assertCount(1, $results);

            // There should be one entry for a cybrary in course1.
            $this->assertCount(1, $results[$course1->id]);
            $this->assertArrayHasKey('cybrary', $results[$course1->id]);
        } else {
            // There should be no entries for any course.
            $this->assertCount(0, $results);
        }
    }

    /**
     * @dataProvider print_overview_timed_provider
     */
    public function test_print_overview_timed_groups($config, $hasresult) {
        $this->resetAfterTest();
        $course1 = self::getDataGenerator()->create_course();
        $group1 = $this->getDataGenerator()->create_group(array('courseid' => $course1->id));
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course1->id));

        // Create an author user.
        $author = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($author->id, $course1->id);

        // Create two viewer users - one in each group.
        $viewer1 = self::getDataGenerator()->create_user((object) array('trackcybraries' => 1));
        $this->getDataGenerator()->enrol_user($viewer1->id, $course1->id);
        $this->getDataGenerator()->create_group_member(array('userid' => $viewer1->id, 'groupid' => $group1->id));

        $viewer2 = self::getDataGenerator()->create_user((object) array('trackcybraries' => 1));
        $this->getDataGenerator()->enrol_user($viewer2->id, $course1->id);
        $this->getDataGenerator()->create_group_member(array('userid' => $viewer2->id, 'groupid' => $group2->id));

        // Create a cybrary.
        $record = new stdClass();
        $record->course = $course1->id;
        $cybrary1 = self::getDataGenerator()->create_module('cybrary', (object) array(
            'course'        => $course1->id,
            'groupmode'     => SEPARATEGROUPS,
        ));

        // A post in the cybrary for group1.
        $record = new stdClass();
        $record->course     = $course1->id;
        $record->userid     = $author->id;
        $record->cybrary      = $cybrary1->id;
        $record->groupid    = $group1->id;
        if (isset($config['timestartmodifier'])) {
            $record->timestart = time() + $config['timestartmodifier'];
        }
        if (isset($config['timeendmodifier'])) {
            $record->timeend = time() + $config['timeendmodifier'];
        }
        $this->getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        $course1->lastaccess = 0;
        $courses = array($course1->id => $course1);

        // As viewer1 (same group as post).
        $this->setUser($viewer1->id);
        $results = array();
        cybrary_print_overview($courses, $results);

        if ($hasresult) {
            // There should be one entry for course1.
            $this->assertCount(1, $results);

            // There should be one entry for a cybrary in course1.
            $this->assertCount(1, $results[$course1->id]);
            $this->assertArrayHasKey('cybrary', $results[$course1->id]);
        } else {
            // There should be no entries for any course.
            $this->assertCount(0, $results);
        }

        $this->setUser($viewer2->id);
        $results = array();
        cybrary_print_overview($courses, $results);

        // There should be one entry for course1.
        $this->assertCount(0, $results);
    }

    public function print_overview_timed_provider() {
        return array(
            'timestart_past' => array(
                'discussionconfig' => array(
                    'timestartmodifier' => -86000,
                ),
                'hasresult'         => true,
            ),
            'timestart_future' => array(
                'discussionconfig' => array(
                    'timestartmodifier' => 86000,
                ),
                'hasresult'         => false,
            ),
            'timeend_past' => array(
                'discussionconfig' => array(
                    'timeendmodifier'   => -86000,
                ),
                'hasresult'         => false,
            ),
            'timeend_future' => array(
                'discussionconfig' => array(
                    'timeendmodifier'   => 86000,
                ),
                'hasresult'         => true,
            ),
        );
    }

    /**
     * @dataProvider cybrary_get_unmailed_posts_provider
     */
    public function test_cybrary_get_unmailed_posts($discussiondata, $enabletimedposts, $expectedcount, $expectedreplycount) {
        global $CFG, $DB;

        $this->resetAfterTest();

        // Configure timed posts.
        $CFG->cybrary_enabletimedposts = $enabletimedposts;

        $course = $this->getDataGenerator()->create_course();
        $cybrary = $this->getDataGenerator()->create_module('cybrary', ['course' => $course->id]);
        $user = $this->getDataGenerator()->create_user();
        $cybrarygen = $this->getDataGenerator()->get_plugin_generator('mod_cybrary');

        // Keep track of the start time of the test. Do not use time() after this point to prevent random failures.
        $time = time();

        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user->id;
        $record->cybrary = $cybrary->id;
        if (isset($discussiondata['timecreated'])) {
            $record->timemodified = $time + $discussiondata['timecreated'];
        }
        if (isset($discussiondata['timestart'])) {
            $record->timestart = $time + $discussiondata['timestart'];
        }
        if (isset($discussiondata['timeend'])) {
            $record->timeend = $time + $discussiondata['timeend'];
        }
        if (isset($discussiondata['mailed'])) {
            $record->mailed = $discussiondata['mailed'];
        }

        $discussion = $cybrarygen->create_discussion($record);

        // Fetch the unmailed posts.
        $timenow   = $time;
        $endtime   = $timenow - $CFG->maxeditingtime;
        $starttime = $endtime - 2 * DAYSECS;

        $unmailed = cybrary_get_unmailed_posts($starttime, $endtime, $timenow);
        $this->assertCount($expectedcount, $unmailed);

        // Add a reply just outside the maxeditingtime.
        $replyto = $DB->get_record('cybrary_posts', array('discussion' => $discussion->id));
        $reply = new stdClass();
        $reply->userid = $user->id;
        $reply->discussion = $discussion->id;
        $reply->parent = $replyto->id;
        $reply->created = max($replyto->created, $endtime - 1);
        $cybrarygen->create_post($reply);

        $unmailed = cybrary_get_unmailed_posts($starttime, $endtime, $timenow);
        $this->assertCount($expectedreplycount, $unmailed);
    }

    public function cybrary_get_unmailed_posts_provider() {
        return [
            'Untimed discussion; Single post; maxeditingtime not expired' => [
                'discussion'        => [
                ],
                'timedposts'        => false,
                'postcount'         => 0,
                'replycount'        => 0,
            ],
            'Untimed discussion; Single post; maxeditingtime expired' => [
                'discussion'        => [
                    'timecreated'   => - DAYSECS,
                ],
                'timedposts'        => false,
                'postcount'         => 1,
                'replycount'        => 2,
            ],
            'Timed discussion; Single post; Posted 1 week ago; timestart maxeditingtime not expired' => [
                'discussion'        => [
                    'timecreated'   => - WEEKSECS,
                    'timestart'     => 0,
                ],
                'timedposts'        => true,
                'postcount'         => 0,
                'replycount'        => 0,
            ],
            'Timed discussion; Single post; Posted 1 week ago; timestart maxeditingtime expired' => [
                'discussion'        => [
                    'timecreated'   => - WEEKSECS,
                    'timestart'     => - DAYSECS,
                ],
                'timedposts'        => true,
                'postcount'         => 1,
                'replycount'        => 2,
            ],
            'Timed discussion; Single post; Posted 1 week ago; timestart maxeditingtime expired; timeend not reached' => [
                'discussion'        => [
                    'timecreated'   => - WEEKSECS,
                    'timestart'     => - DAYSECS,
                    'timeend'       => + DAYSECS
                ],
                'timedposts'        => true,
                'postcount'         => 1,
                'replycount'        => 2,
            ],
            'Timed discussion; Single post; Posted 1 week ago; timestart maxeditingtime expired; timeend passed' => [
                'discussion'        => [
                    'timecreated'   => - WEEKSECS,
                    'timestart'     => - DAYSECS,
                    'timeend'       => - HOURSECS,
                ],
                'timedposts'        => true,
                'postcount'         => 0,
                'replycount'        => 0,
            ],
            'Timed discussion; Single post; Posted 1 week ago; timeend not reached' => [
                'discussion'        => [
                    'timecreated'   => - WEEKSECS,
                    'timeend'       => + DAYSECS
                ],
                'timedposts'        => true,
                'postcount'         => 0,
                'replycount'        => 1,
            ],
            'Timed discussion; Single post; Posted 1 week ago; timeend passed' => [
                'discussion'        => [
                    'timecreated'   => - WEEKSECS,
                    'timeend'       => - DAYSECS,
                ],
                'timedposts'        => true,
                'postcount'         => 0,
                'replycount'        => 0,
            ],

            'Previously mailed; Untimed discussion; Single post; maxeditingtime not expired' => [
                'discussion'        => [
                    'mailed'        => 1,
                ],
                'timedposts'        => false,
                'postcount'         => 0,
                'replycount'        => 0,
            ],

            'Previously mailed; Untimed discussion; Single post; maxeditingtime expired' => [
                'discussion'        => [
                    'timecreated'   => - DAYSECS,
                    'mailed'        => 1,
                ],
                'timedposts'        => false,
                'postcount'         => 0,
                'replycount'        => 1,
            ],
            'Previously mailed; Timed discussion; Single post; Posted 1 week ago; timestart maxeditingtime not expired' => [
                'discussion'        => [
                    'timecreated'   => - WEEKSECS,
                    'timestart'     => 0,
                    'mailed'        => 1,
                ],
                'timedposts'        => true,
                'postcount'         => 0,
                'replycount'        => 0,
            ],
            'Previously mailed; Timed discussion; Single post; Posted 1 week ago; timestart maxeditingtime expired' => [
                'discussion'        => [
                    'timecreated'   => - WEEKSECS,
                    'timestart'     => - DAYSECS,
                    'mailed'        => 1,
                ],
                'timedposts'        => true,
                'postcount'         => 0,
                'replycount'        => 1,
            ],
            'Previously mailed; Timed discussion; Single post; Posted 1 week ago; timestart maxeditingtime expired; timeend not reached' => [
                'discussion'        => [
                    'timecreated'   => - WEEKSECS,
                    'timestart'     => - DAYSECS,
                    'timeend'       => + DAYSECS,
                    'mailed'        => 1,
                ],
                'timedposts'        => true,
                'postcount'         => 0,
                'replycount'        => 1,
            ],
            'Previously mailed; Timed discussion; Single post; Posted 1 week ago; timestart maxeditingtime expired; timeend passed' => [
                'discussion'        => [
                    'timecreated'   => - WEEKSECS,
                    'timestart'     => - DAYSECS,
                    'timeend'       => - HOURSECS,
                    'mailed'        => 1,
                ],
                'timedposts'        => true,
                'postcount'         => 0,
                'replycount'        => 0,
            ],
            'Previously mailed; Timed discussion; Single post; Posted 1 week ago; timeend not reached' => [
                'discussion'        => [
                    'timecreated'   => - WEEKSECS,
                    'timeend'       => + DAYSECS,
                    'mailed'        => 1,
                ],
                'timedposts'        => true,
                'postcount'         => 0,
                'replycount'        => 1,
            ],
            'Previously mailed; Timed discussion; Single post; Posted 1 week ago; timeend passed' => [
                'discussion'        => [
                    'timecreated'   => - WEEKSECS,
                    'timeend'       => - DAYSECS,
                    'mailed'        => 1,
                ],
                'timedposts'        => true,
                'postcount'         => 0,
                'replycount'        => 0,
            ],
        ];
    }
}
