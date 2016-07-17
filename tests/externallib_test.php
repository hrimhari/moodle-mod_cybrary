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
 * The module cybraries external functions unit tests
 *
 * @package    mod_cybrary
 * @category   external
 * @copyright  2012 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

class mod_cybrary_external_testcase extends externallib_advanced_testcase {

    /**
     * Tests set up
     */
    protected function setUp() {
        global $CFG;

        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_cybrary\subscriptions::reset_cybrary_cache();

        require_once($CFG->dirroot . '/mod/cybrary/externallib.php');
    }

    public function tearDown() {
        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_cybrary\subscriptions::reset_cybrary_cache();
    }

    /**
     * Test get cybraries
     */
    public function test_mod_cybrary_get_cybraries_by_courses() {
        global $USER, $CFG, $DB;

        $this->resetAfterTest(true);

        // Create a user.
        $user = self::getDataGenerator()->create_user();

        // Set to the user.
        self::setUser($user);

        // Create courses to add the modules.
        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();

        // First cybrary.
        $record = new stdClass();
        $record->introformat = FORMAT_HTML;
        $record->course = $course1->id;
        $cybrary1 = self::getDataGenerator()->create_module('cybrary', $record);

        // Second cybrary.
        $record = new stdClass();
        $record->introformat = FORMAT_HTML;
        $record->course = $course2->id;
        $cybrary2 = self::getDataGenerator()->create_module('cybrary', $record);

        // Add discussions to the cybraries.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user->id;
        $record->cybrary = $cybrary1->id;
        $discussion1 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);
        // Expect one discussion.
        $cybrary1->numdiscussions = 1;
        $cybrary1->cancreatediscussions = true;

        $record = new stdClass();
        $record->course = $course2->id;
        $record->userid = $user->id;
        $record->cybrary = $cybrary2->id;
        $discussion2 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);
        $discussion3 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);
        // Expect two discussions.
        $cybrary2->numdiscussions = 2;
        // Default limited role, no create discussion capability enabled.
        $cybrary2->cancreatediscussions = false;

        // Check the cybrary was correctly created.
        $this->assertEquals(2, $DB->count_records_select('cybrary', 'id = :cybrary1 OR id = :cybrary2',
                array('cybrary1' => $cybrary1->id, 'cybrary2' => $cybrary2->id)));

        // Enrol the user in two courses.
        // DataGenerator->enrol_user automatically sets a role for the user with the permission mod/form:viewdiscussion.
        $this->getDataGenerator()->enrol_user($user->id, $course1->id, null, 'manual');
        // Execute real Moodle enrolment as we'll call unenrol() method on the instance later.
        $enrol = enrol_get_plugin('manual');
        $enrolinstances = enrol_get_instances($course2->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance2 = $courseenrolinstance;
                break;
            }
        }
        $enrol->enrol_user($instance2, $user->id);

        // Assign capabilities to view cybraries for cybrary 2.
        $cm2 = get_coursemodule_from_id('cybrary', $cybrary2->cmid, 0, false, MUST_EXIST);
        $context2 = context_module::instance($cm2->id);
        $newrole = create_role('Role 2', 'role2', 'Role 2 description');
        $roleid2 = $this->assignUserCapability('mod/cybrary:viewdiscussion', $context2->id, $newrole);

        // Create what we expect to be returned when querying the two courses.
        unset($cybrary1->displaywordcount);
        unset($cybrary2->displaywordcount);

        $expectedcybraries = array();
        $expectedcybraries[$cybrary1->id] = (array) $cybrary1;
        $expectedcybraries[$cybrary2->id] = (array) $cybrary2;

        // Call the external function passing course ids.
        $cybraries = mod_cybrary_external::get_cybraries_by_courses(array($course1->id, $course2->id));
        $cybraries = external_api::clean_returnvalue(mod_cybrary_external::get_cybraries_by_courses_returns(), $cybraries);
        $this->assertCount(2, $cybraries);
        foreach ($cybraries as $cybrary) {
            $this->assertEquals($expectedcybraries[$cybrary['id']], $cybrary);
        }

        // Call the external function without passing course id.
        $cybraries = mod_cybrary_external::get_cybraries_by_courses();
        $cybraries = external_api::clean_returnvalue(mod_cybrary_external::get_cybraries_by_courses_returns(), $cybraries);
        $this->assertCount(2, $cybraries);
        foreach ($cybraries as $cybrary) {
            $this->assertEquals($expectedcybraries[$cybrary['id']], $cybrary);
        }

        // Unenrol user from second course and alter expected cybraries.
        $enrol->unenrol_user($instance2, $user->id);
        unset($expectedcybraries[$cybrary2->id]);

        // Call the external function without passing course id.
        $cybraries = mod_cybrary_external::get_cybraries_by_courses();
        $cybraries = external_api::clean_returnvalue(mod_cybrary_external::get_cybraries_by_courses_returns(), $cybraries);
        $this->assertCount(1, $cybraries);
        $this->assertEquals($expectedcybraries[$cybrary1->id], $cybraries[0]);
        $this->assertTrue($cybraries[0]['cancreatediscussions']);

        // Change the type of the cybrary, the user shouldn't be able to add discussions.
        $DB->set_field('cybrary', 'type', 'news', array('id' => $cybrary1->id));
        $cybraries = mod_cybrary_external::get_cybraries_by_courses();
        $cybraries = external_api::clean_returnvalue(mod_cybrary_external::get_cybraries_by_courses_returns(), $cybraries);
        $this->assertFalse($cybraries[0]['cancreatediscussions']);

        // Call for the second course we unenrolled the user from.
        $cybraries = mod_cybrary_external::get_cybraries_by_courses(array($course2->id));
        $cybraries = external_api::clean_returnvalue(mod_cybrary_external::get_cybraries_by_courses_returns(), $cybraries);
        $this->assertCount(0, $cybraries);
    }

    /**
     * Test get cybrary discussions
     */
    public function test_mod_cybrary_get_cybrary_discussions() {
        global $USER, $CFG, $DB;

        $this->resetAfterTest(true);

        // Set the CFG variable to allow track cybraries.
        $CFG->cybrary_trackreadposts = true;

        // Create a user who can track cybraries.
        $record = new stdClass();
        $record->trackcybraries = true;
        $user1 = self::getDataGenerator()->create_user($record);
        // Create a bunch of other users to post.
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();

        // Set the first created user to the test user.
        self::setUser($user1);

        // Create courses to add the modules.
        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();

        // First cybrary with tracking off.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->trackingtype = CYBRARY_TRACKING_OFF;
        $cybrary1 = self::getDataGenerator()->create_module('cybrary', $record);

        // Second cybrary of type 'qanda' with tracking enabled.
        $record = new stdClass();
        $record->course = $course2->id;
        $record->type = 'qanda';
        $record->trackingtype = CYBRARY_TRACKING_FORCED;
        $cybrary2 = self::getDataGenerator()->create_module('cybrary', $record);

        // Add discussions to the cybraries.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user1->id;
        $record->cybrary = $cybrary1->id;
        $discussion1 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        $record = new stdClass();
        $record->course = $course2->id;
        $record->userid = $user2->id;
        $record->cybrary = $cybrary2->id;
        $discussion2 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        // Add three replies to the discussion 1 from different users.
        $record = new stdClass();
        $record->discussion = $discussion1->id;
        $record->parent = $discussion1->firstpost;
        $record->userid = $user2->id;
        $discussion1reply1 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_post($record);

        $record->parent = $discussion1reply1->id;
        $record->userid = $user3->id;
        $discussion1reply2 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_post($record);

        $record->userid = $user4->id;
        $discussion1reply3 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_post($record);

        // Add two replies to discussion 2 from different users.
        $record = new stdClass();
        $record->discussion = $discussion2->id;
        $record->parent = $discussion2->firstpost;
        $record->userid = $user1->id;
        $discussion2reply1 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_post($record);

        $record->parent = $discussion2reply1->id;
        $record->userid = $user3->id;
        $discussion2reply2 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_post($record);

        // Check the cybraries were correctly created.
        $this->assertEquals(2, $DB->count_records_select('cybrary', 'id = :cybrary1 OR id = :cybrary2',
                array('cybrary1' => $cybrary1->id, 'cybrary2' => $cybrary2->id)));

        // Check the discussions were correctly created.
        $this->assertEquals(2, $DB->count_records_select('cybrary_discussions', 'cybrary = :cybrary1 OR cybrary = :cybrary2',
                                                            array('cybrary1' => $cybrary1->id, 'cybrary2' => $cybrary2->id)));

        // Check the posts were correctly created, don't forget each discussion created also creates a post.
        $this->assertEquals(7, $DB->count_records_select('cybrary_posts', 'discussion = :discussion1 OR discussion = :discussion2',
                array('discussion1' => $discussion1->id, 'discussion2' => $discussion2->id)));

        // Enrol the user in the first course.
        $enrol = enrol_get_plugin('manual');
        // Following line enrol and assign default role id to the user.
        // So the user automatically gets mod/cybrary:viewdiscussion on all cybraries of the course.
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);

        // Now enrol into the second course.
        // We don't use the dataGenerator as we need to get the $instance2 to unenrol later.
        $enrolinstances = enrol_get_instances($course2->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance2 = $courseenrolinstance;
                break;
            }
        }
        $enrol->enrol_user($instance2, $user1->id);

        // Assign capabilities to view discussions for cybrary 2.
        $cm = get_coursemodule_from_id('cybrary', $cybrary2->cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $newrole = create_role('Role 2', 'role2', 'Role 2 description');
        $this->assignUserCapability('mod/cybrary:viewdiscussion', $context->id, $newrole);

        // Create what we expect to be returned when querying the cybraries.
        $expecteddiscussions = array();
        $expecteddiscussions[] = array(
                'id' => $discussion1->id,
                'course' => $discussion1->course,
                'cybrary' => $discussion1->cybrary,
                'name' => $discussion1->name,
                'firstpost' => $discussion1->firstpost,
                'userid' => $discussion1->userid,
                'groupid' => $discussion1->groupid,
                'assessed' => $discussion1->assessed,
                'timemodified' => $discussion1reply3->created,
                'usermodified' => $discussion1reply3->userid,
                'timestart' => $discussion1->timestart,
                'timeend' => $discussion1->timeend,
                'firstuserfullname' => fullname($user1),
                'firstuserimagealt' => $user1->imagealt,
                'firstuserpicture' => $user1->picture,
                'firstuseremail' => $user1->email,
                'subject' => $discussion1->name,
                'numreplies' => 3,
                'numunread' => '',
                'lastpost' => $discussion1reply3->id,
                'lastuserid' => $user4->id,
                'lastuserfullname' => fullname($user4),
                'lastuserimagealt' => $user4->imagealt,
                'lastuserpicture' => $user4->picture,
                'lastuseremail' => $user4->email
            );
        $expecteddiscussions[] = array(
                'id' => $discussion2->id,
                'course' => $discussion2->course,
                'cybrary' => $discussion2->cybrary,
                'name' => $discussion2->name,
                'firstpost' => $discussion2->firstpost,
                'userid' => $discussion2->userid,
                'groupid' => $discussion2->groupid,
                'assessed' => $discussion2->assessed,
                'timemodified' => $discussion2reply2->created,
                'usermodified' => $discussion2reply2->userid,
                'timestart' => $discussion2->timestart,
                'timeend' => $discussion2->timeend,
                'firstuserfullname' => fullname($user2),
                'firstuserimagealt' => $user2->imagealt,
                'firstuserpicture' => $user2->picture,
                'firstuseremail' => $user2->email,
                'subject' => $discussion2->name,
                'numreplies' => 2,
                'numunread' => 3,
                'lastpost' => $discussion2reply2->id,
                'lastuserid' => $user3->id,
                'lastuserfullname' => fullname($user3),
                'lastuserimagealt' => $user3->imagealt,
                'lastuserpicture' => $user3->picture,
                'lastuseremail' => $user3->email
            );

        // Call the external function passing cybrary ids.
        $discussions = mod_cybrary_external::get_cybrary_discussions(array($cybrary1->id, $cybrary2->id));
        $discussions = external_api::clean_returnvalue(mod_cybrary_external::get_cybrary_discussions_returns(), $discussions);
        $this->assertEquals($expecteddiscussions, $discussions);
        // Some debugging is going to be produced, this is because we switch PAGE contexts in the get_cybrary_discussions function,
        // the switch happens when the validate_context function is called inside a foreach loop.
        // See MDL-41746 for more information.
        $this->assertDebuggingCalled();

        // Remove the users post from the qanda cybrary and ensure they can still see the discussion.
        $DB->delete_records('cybrary_posts', array('id' => $discussion2reply1->id));
        $discussions = mod_cybrary_external::get_cybrary_discussions(array($cybrary2->id));
        $discussions = external_api::clean_returnvalue(mod_cybrary_external::get_cybrary_discussions_returns(), $discussions);
        $this->assertEquals(1, count($discussions));

        // Call without required view discussion capability.
        $this->unassignUserCapability('mod/cybrary:viewdiscussion', null, null, $course1->id);
        try {
            mod_cybrary_external::get_cybrary_discussions(array($cybrary1->id));
            $this->fail('Exception expected due to missing capability.');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }
        $this->assertDebuggingCalled();

        // Unenrol user from second course.
        $enrol->unenrol_user($instance2, $user1->id);

        // Call for the second course we unenrolled the user from, make sure exception thrown.
        try {
            mod_cybrary_external::get_cybrary_discussions(array($cybrary2->id));
            $this->fail('Exception expected due to being unenrolled from the course.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }
    }

    /**
     * Test get cybrary posts
     */
    public function test_mod_cybrary_get_cybrary_discussion_posts() {
        global $CFG, $PAGE;

        $this->resetAfterTest(true);

        // Set the CFG variable to allow track cybraries.
        $CFG->cybrary_trackreadposts = true;

        // Create a user who can track cybraries.
        $record = new stdClass();
        $record->trackcybraries = true;
        $user1 = self::getDataGenerator()->create_user($record);
        // Create a bunch of other users to post.
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();

        // Set the first created user to the test user.
        self::setUser($user1);

        // Create course to add the module.
        $course1 = self::getDataGenerator()->create_course();

        // Cybrary with tracking off.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->trackingtype = CYBRARY_TRACKING_OFF;
        $cybrary1 = self::getDataGenerator()->create_module('cybrary', $record);
        $cybrary1context = context_module::instance($cybrary1->cmid);

        // Add discussions to the cybraries.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user1->id;
        $record->cybrary = $cybrary1->id;
        $discussion1 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user2->id;
        $record->cybrary = $cybrary1->id;
        $discussion2 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        // Add 2 replies to the discussion 1 from different users.
        $record = new stdClass();
        $record->discussion = $discussion1->id;
        $record->parent = $discussion1->firstpost;
        $record->userid = $user2->id;
        $discussion1reply1 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_post($record);

        $record->parent = $discussion1reply1->id;
        $record->userid = $user3->id;
        $discussion1reply2 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_post($record);

        // Enrol the user in the  course.
        $enrol = enrol_get_plugin('manual');
        // Following line enrol and assign default role id to the user.
        // So the user automatically gets mod/cybrary:viewdiscussion on all cybraries of the course.
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id);

        // Delete one user, to test that we still receive posts by this user.
        delete_user($user3);

        // Create what we expect to be returned when querying the discussion.
        $expectedposts = array(
            'posts' => array(),
            'warnings' => array(),
        );

        // User pictures are initially empty, we should get the links once the external function is called.
        $expectedposts['posts'][] = array(
            'id' => $discussion1reply2->id,
            'discussion' => $discussion1reply2->discussion,
            'parent' => $discussion1reply2->parent,
            'userid' => (int) $discussion1reply2->userid,
            'created' => $discussion1reply2->created,
            'modified' => $discussion1reply2->modified,
            'mailed' => $discussion1reply2->mailed,
            'subject' => $discussion1reply2->subject,
            'message' => file_rewrite_pluginfile_urls($discussion1reply2->message, 'pluginfile.php',
                    $cybrary1context->id, 'mod_cybrary', 'post', $discussion1reply2->id),
            'messageformat' => 1,   // This value is usually changed by external_format_text() function.
            'messagetrust' => $discussion1reply2->messagetrust,
            'attachment' => $discussion1reply2->attachment,
            'totalscore' => $discussion1reply2->totalscore,
            'mailnow' => $discussion1reply2->mailnow,
            'children' => array(),
            'canreply' => true,
            'postread' => false,
            'userfullname' => fullname($user3),
            'userpictureurl' => ''
        );

        $expectedposts['posts'][] = array(
            'id' => $discussion1reply1->id,
            'discussion' => $discussion1reply1->discussion,
            'parent' => $discussion1reply1->parent,
            'userid' => (int) $discussion1reply1->userid,
            'created' => $discussion1reply1->created,
            'modified' => $discussion1reply1->modified,
            'mailed' => $discussion1reply1->mailed,
            'subject' => $discussion1reply1->subject,
            'message' => file_rewrite_pluginfile_urls($discussion1reply1->message, 'pluginfile.php',
                    $cybrary1context->id, 'mod_cybrary', 'post', $discussion1reply1->id),
            'messageformat' => 1,   // This value is usually changed by external_format_text() function.
            'messagetrust' => $discussion1reply1->messagetrust,
            'attachment' => $discussion1reply1->attachment,
            'totalscore' => $discussion1reply1->totalscore,
            'mailnow' => $discussion1reply1->mailnow,
            'children' => array($discussion1reply2->id),
            'canreply' => true,
            'postread' => false,
            'userfullname' => fullname($user2),
            'userpictureurl' => ''
        );

        // Test a discussion with two additional posts (total 3 posts).
        $posts = mod_cybrary_external::get_cybrary_discussion_posts($discussion1->id, 'modified', 'DESC');
        $posts = external_api::clean_returnvalue(mod_cybrary_external::get_cybrary_discussion_posts_returns(), $posts);
        $this->assertEquals(3, count($posts['posts']));

        // Generate here the pictures because we need to wait to the external function to init the theme.
        $userpicture = new user_picture($user3);
        $userpicture->size = 1; // Size f1.
        $expectedposts['posts'][0]['userpictureurl'] = $userpicture->get_url($PAGE)->out(false);

        $userpicture = new user_picture($user2);
        $userpicture->size = 1; // Size f1.
        $expectedposts['posts'][1]['userpictureurl'] = $userpicture->get_url($PAGE)->out(false);

        // Unset the initial discussion post.
        array_pop($posts['posts']);
        $this->assertEquals($expectedposts, $posts);

        // Test discussion without additional posts. There should be only one post (the one created by the discussion).
        $posts = mod_cybrary_external::get_cybrary_discussion_posts($discussion2->id, 'modified', 'DESC');
        $posts = external_api::clean_returnvalue(mod_cybrary_external::get_cybrary_discussion_posts_returns(), $posts);
        $this->assertEquals(1, count($posts['posts']));

    }

    /**
     * Test get cybrary posts (qanda cybrary)
     */
    public function test_mod_cybrary_get_cybrary_discussion_posts_qanda() {
        global $CFG, $DB;

        $this->resetAfterTest(true);

        $record = new stdClass();
        $user1 = self::getDataGenerator()->create_user($record);
        $user2 = self::getDataGenerator()->create_user();

        // Set the first created user to the test user.
        self::setUser($user1);

        // Create course to add the module.
        $course1 = self::getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course1->id);

        // Cybrary with tracking off.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->type = 'qanda';
        $cybrary1 = self::getDataGenerator()->create_module('cybrary', $record);
        $cybrary1context = context_module::instance($cybrary1->cmid);

        // Add discussions to the cybraries.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user2->id;
        $record->cybrary = $cybrary1->id;
        $discussion1 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        // Add 1 reply (not the actual user).
        $record = new stdClass();
        $record->discussion = $discussion1->id;
        $record->parent = $discussion1->firstpost;
        $record->userid = $user2->id;
        $discussion1reply1 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_post($record);

        // We still see only the original post.
        $posts = mod_cybrary_external::get_cybrary_discussion_posts($discussion1->id, 'modified', 'DESC');
        $posts = external_api::clean_returnvalue(mod_cybrary_external::get_cybrary_discussion_posts_returns(), $posts);
        $this->assertEquals(1, count($posts['posts']));

        // Add a new reply, the user is going to be able to see only the original post and their new post.
        $record = new stdClass();
        $record->discussion = $discussion1->id;
        $record->parent = $discussion1->firstpost;
        $record->userid = $user1->id;
        $discussion1reply2 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_post($record);

        $posts = mod_cybrary_external::get_cybrary_discussion_posts($discussion1->id, 'modified', 'DESC');
        $posts = external_api::clean_returnvalue(mod_cybrary_external::get_cybrary_discussion_posts_returns(), $posts);
        $this->assertEquals(2, count($posts['posts']));

        // Now, we can fake the time of the user post, so he can se the rest of the discussion posts.
        $discussion1reply2->created -= $CFG->maxeditingtime * 2;
        $DB->update_record('cybrary_posts', $discussion1reply2);

        $posts = mod_cybrary_external::get_cybrary_discussion_posts($discussion1->id, 'modified', 'DESC');
        $posts = external_api::clean_returnvalue(mod_cybrary_external::get_cybrary_discussion_posts_returns(), $posts);
        $this->assertEquals(3, count($posts['posts']));
    }

    /**
     * Test get cybrary discussions paginated
     */
    public function test_mod_cybrary_get_cybrary_discussions_paginated() {
        global $USER, $CFG, $DB, $PAGE;

        $this->resetAfterTest(true);

        // Set the CFG variable to allow track cybraries.
        $CFG->cybrary_trackreadposts = true;

        // Create a user who can track cybraries.
        $record = new stdClass();
        $record->trackcybraries = true;
        $user1 = self::getDataGenerator()->create_user($record);
        // Create a bunch of other users to post.
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();

        // Set the first created user to the test user.
        self::setUser($user1);

        // Create courses to add the modules.
        $course1 = self::getDataGenerator()->create_course();

        // First cybrary with tracking off.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->trackingtype = CYBRARY_TRACKING_OFF;
        $cybrary1 = self::getDataGenerator()->create_module('cybrary', $record);

        // Add discussions to the cybraries.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user1->id;
        $record->cybrary = $cybrary1->id;
        $discussion1 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        // Add three replies to the discussion 1 from different users.
        $record = new stdClass();
        $record->discussion = $discussion1->id;
        $record->parent = $discussion1->firstpost;
        $record->userid = $user2->id;
        $discussion1reply1 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_post($record);

        $record->parent = $discussion1reply1->id;
        $record->userid = $user3->id;
        $discussion1reply2 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_post($record);

        $record->userid = $user4->id;
        $discussion1reply3 = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_post($record);

        // Enrol the user in the first course.
        $enrol = enrol_get_plugin('manual');

        // We don't use the dataGenerator as we need to get the $instance2 to unenrol later.
        $enrolinstances = enrol_get_instances($course1->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance1 = $courseenrolinstance;
                break;
            }
        }
        $enrol->enrol_user($instance1, $user1->id);

        // Delete one user.
        delete_user($user4);

        // Assign capabilities to view discussions for cybrary 1.
        $cm = get_coursemodule_from_id('cybrary', $cybrary1->cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $newrole = create_role('Role 2', 'role2', 'Role 2 description');
        $this->assignUserCapability('mod/cybrary:viewdiscussion', $context->id, $newrole);

        // Create what we expect to be returned when querying the cybraries.

        $post1 = $DB->get_record('cybrary_posts', array('id' => $discussion1->firstpost), '*', MUST_EXIST);

        // User pictures are initially empty, we should get the links once the external function is called.
        $expecteddiscussions = array(
                'id' => $discussion1->firstpost,
                'name' => $discussion1->name,
                'groupid' => $discussion1->groupid,
                'timemodified' => $discussion1reply3->created,
                'usermodified' => $discussion1reply3->userid,
                'timestart' => $discussion1->timestart,
                'timeend' => $discussion1->timeend,
                'discussion' => $discussion1->id,
                'parent' => 0,
                'userid' => $discussion1->userid,
                'created' => $post1->created,
                'modified' => $post1->modified,
                'mailed' => $post1->mailed,
                'subject' => $post1->subject,
                'message' => $post1->message,
                'messageformat' => $post1->messageformat,
                'messagetrust' => $post1->messagetrust,
                'attachment' => $post1->attachment,
                'totalscore' => $post1->totalscore,
                'mailnow' => $post1->mailnow,
                'userfullname' => fullname($user1),
                'usermodifiedfullname' => fullname($user4),
                'userpictureurl' => '',
                'usermodifiedpictureurl' => '',
                'numreplies' => 3,
                'numunread' => 0
            );

        // Call the external function passing cybrary id.
        $discussions = mod_cybrary_external::get_cybrary_discussions_paginated($cybrary1->id);
        $discussions = external_api::clean_returnvalue(mod_cybrary_external::get_cybrary_discussions_paginated_returns(), $discussions);
        $expectedreturn = array(
            'discussions' => array($expecteddiscussions),
            'warnings' => array()
        );

        // Wait the theme to be loaded (the external_api call does that) to generate the user profiles.
        $userpicture = new user_picture($user1);
        $userpicture->size = 1; // Size f1.
        $expectedreturn['discussions'][0]['userpictureurl'] = $userpicture->get_url($PAGE)->out(false);

        $userpicture = new user_picture($user4);
        $userpicture->size = 1; // Size f1.
        $expectedreturn['discussions'][0]['usermodifiedpictureurl'] = $userpicture->get_url($PAGE)->out(false);

        $this->assertEquals($expectedreturn, $discussions);

        // Call without required view discussion capability.
        $this->unassignUserCapability('mod/cybrary:viewdiscussion', $context->id, $newrole);
        try {
            mod_cybrary_external::get_cybrary_discussions_paginated($cybrary1->id);
            $this->fail('Exception expected due to missing capability.');
        } catch (moodle_exception $e) {
            $this->assertEquals('noviewdiscussionspermission', $e->errorcode);
        }

        // Unenrol user from second course.
        $enrol->unenrol_user($instance1, $user1->id);

        // Call for the second course we unenrolled the user from, make sure exception thrown.
        try {
            mod_cybrary_external::get_cybrary_discussions_paginated($cybrary1->id);
            $this->fail('Exception expected due to being unenrolled from the course.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }
    }

    /**
     * Test get cybrary discussions paginated (qanda cybraries)
     */
    public function test_mod_cybrary_get_cybrary_discussions_paginated_qanda() {

        $this->resetAfterTest(true);

        // Create courses to add the modules.
        $course = self::getDataGenerator()->create_course();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        // First cybrary with tracking off.
        $record = new stdClass();
        $record->course = $course->id;
        $record->type = 'qanda';
        $cybrary = self::getDataGenerator()->create_module('cybrary', $record);

        // Add discussions to the cybraries.
        $discussionrecord = new stdClass();
        $discussionrecord->course = $course->id;
        $discussionrecord->userid = $user2->id;
        $discussionrecord->cybrary = $cybrary->id;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($discussionrecord);

        self::setAdminUser();
        $discussions = mod_cybrary_external::get_cybrary_discussions_paginated($cybrary->id);
        $discussions = external_api::clean_returnvalue(mod_cybrary_external::get_cybrary_discussions_paginated_returns(), $discussions);

        $this->assertCount(1, $discussions['discussions']);
        $this->assertCount(0, $discussions['warnings']);

        self::setUser($user1);
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);

        $discussions = mod_cybrary_external::get_cybrary_discussions_paginated($cybrary->id);
        $discussions = external_api::clean_returnvalue(mod_cybrary_external::get_cybrary_discussions_paginated_returns(), $discussions);

        $this->assertCount(1, $discussions['discussions']);
        $this->assertCount(0, $discussions['warnings']);

    }

    /**
     * Test add_discussion_post
     */
    public function test_add_discussion_post() {
        global $CFG;

        $this->resetAfterTest(true);

        $user = self::getDataGenerator()->create_user();
        $otheruser = self::getDataGenerator()->create_user();

        self::setAdminUser();

        // Create course to add the module.
        $course = self::getDataGenerator()->create_course(array('groupmode' => VISIBLEGROUPS, 'groupmodeforce' => 0));

        // Cybrary with tracking off.
        $record = new stdClass();
        $record->course = $course->id;
        $cybrary = self::getDataGenerator()->create_module('cybrary', $record);
        $cm = get_coursemodule_from_id('cybrary', $cybrary->cmid, 0, false, MUST_EXIST);
        $cybrarycontext = context_module::instance($cybrary->cmid);

        // Add discussions to the cybraries.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $user->id;
        $record->cybrary = $cybrary->id;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        // Try to post (user not enrolled).
        self::setUser($user);
        try {
            mod_cybrary_external::add_discussion_post($discussion->firstpost, 'some subject', 'some text here...');
            $this->fail('Exception expected due to being unenrolled from the course.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $this->getDataGenerator()->enrol_user($otheruser->id, $course->id);

        $post = mod_cybrary_external::add_discussion_post($discussion->firstpost, 'some subject', 'some text here...');
        $post = external_api::clean_returnvalue(mod_cybrary_external::add_discussion_post_returns(), $post);

        $posts = mod_cybrary_external::get_cybrary_discussion_posts($discussion->id);
        $posts = external_api::clean_returnvalue(mod_cybrary_external::get_cybrary_discussion_posts_returns(), $posts);
        // We receive the discussion and the post.
        $this->assertEquals(2, count($posts['posts']));

        $tested = false;
        foreach ($posts['posts'] as $postel) {
            if ($post['postid'] == $postel['id']) {
                $this->assertEquals('some subject', $postel['subject']);
                $this->assertEquals('some text here...', $postel['message']);
                $tested = true;
            }
        }
        $this->assertTrue($tested);

        // Check not posting in groups the user is not member of.
        $group = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        groups_add_member($group->id, $otheruser->id);

        $cybrary = self::getDataGenerator()->create_module('cybrary', $record, array('groupmode' => SEPARATEGROUPS));
        $record->cybrary = $cybrary->id;
        $record->userid = $otheruser->id;
        $record->groupid = $group->id;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_cybrary')->create_discussion($record);

        try {
            mod_cybrary_external::add_discussion_post($discussion->firstpost, 'some subject', 'some text here...');
            $this->fail('Exception expected due to invalid permissions for posting.');
        } catch (moodle_exception $e) {
            // Expect debugging since we are switching context, and this is something WS_SERVER mode don't like.
            $this->assertDebuggingCalled();
            $this->assertEquals('nopostcybrary', $e->errorcode);
        }

    }

    /*
     * Test add_discussion. A basic test since all the API functions are already covered by unit tests.
     */
    public function test_add_discussion() {

        $this->resetAfterTest(true);

        // Create courses to add the modules.
        $course = self::getDataGenerator()->create_course();

        $user1 = self::getDataGenerator()->create_user();
        $user2 = self::getDataGenerator()->create_user();

        // First cybrary with tracking off.
        $record = new stdClass();
        $record->course = $course->id;
        $record->type = 'news';
        $cybrary = self::getDataGenerator()->create_module('cybrary', $record);

        self::setUser($user1);
        $this->getDataGenerator()->enrol_user($user1->id, $course->id);

        try {
            mod_cybrary_external::add_discussion($cybrary->id, 'the subject', 'some text here...');
            $this->fail('Exception expected due to invalid permissions.');
        } catch (moodle_exception $e) {
            $this->assertEquals('cannotcreatediscussion', $e->errorcode);
        }

        self::setAdminUser();
        $discussion = mod_cybrary_external::add_discussion($cybrary->id, 'the subject', 'some text here...');
        $discussion = external_api::clean_returnvalue(mod_cybrary_external::add_discussion_returns(), $discussion);

        $discussions = mod_cybrary_external::get_cybrary_discussions_paginated($cybrary->id);
        $discussions = external_api::clean_returnvalue(mod_cybrary_external::get_cybrary_discussions_paginated_returns(), $discussions);

        $this->assertCount(1, $discussions['discussions']);
        $this->assertCount(0, $discussions['warnings']);

        $this->assertEquals($discussion['discussionid'], $discussions['discussions'][0]['discussion']);
        $this->assertEquals(-1, $discussions['discussions'][0]['groupid']);
        $this->assertEquals('the subject', $discussions['discussions'][0]['subject']);
        $this->assertEquals('some text here...', $discussions['discussions'][0]['message']);

    }

    /**
     * Test adding discussions in a course with gorups
     */
    public function test_add_discussion_in_course_with_groups() {
        global $CFG;

        $this->resetAfterTest(true);

        // Create course to add the module.
        $course = self::getDataGenerator()->create_course(array('groupmode' => VISIBLEGROUPS, 'groupmodeforce' => 0));
        $user = self::getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        // Cybrary forcing separate gropus.
        $record = new stdClass();
        $record->course = $course->id;
        $cybrary = self::getDataGenerator()->create_module('cybrary', $record, array('groupmode' => SEPARATEGROUPS));

        // Try to post (user not enrolled).
        self::setUser($user);

        // The user is not enroled in any group, try to post in a cybrary with separate groups.
        try {
            mod_cybrary_external::add_discussion($cybrary->id, 'the subject', 'some text here...');
            $this->fail('Exception expected due to invalid group permissions.');
        } catch (moodle_exception $e) {
            $this->assertEquals('cannotcreatediscussion', $e->errorcode);
        }

        try {
            mod_cybrary_external::add_discussion($cybrary->id, 'the subject', 'some text here...', 0);
            $this->fail('Exception expected due to invalid group permissions.');
        } catch (moodle_exception $e) {
            $this->assertEquals('cannotcreatediscussion', $e->errorcode);
        }

        // Create a group.
        $group = $this->getDataGenerator()->create_group(array('courseid' => $course->id));

        // Try to post in a group the user is not enrolled.
        try {
            mod_cybrary_external::add_discussion($cybrary->id, 'the subject', 'some text here...', $group->id);
            $this->fail('Exception expected due to invalid group permissions.');
        } catch (moodle_exception $e) {
            $this->assertEquals('cannotcreatediscussion', $e->errorcode);
        }

        // Add the user to a group.
        groups_add_member($group->id, $user->id);

        // Try to post in a group the user is not enrolled.
        try {
            mod_cybrary_external::add_discussion($cybrary->id, 'the subject', 'some text here...', $group->id + 1);
            $this->fail('Exception expected due to invalid group.');
        } catch (moodle_exception $e) {
            $this->assertEquals('cannotcreatediscussion', $e->errorcode);
        }

        // Nost add the discussion using a valid group.
        $discussion = mod_cybrary_external::add_discussion($cybrary->id, 'the subject', 'some text here...', $group->id);
        $discussion = external_api::clean_returnvalue(mod_cybrary_external::add_discussion_returns(), $discussion);

        $discussions = mod_cybrary_external::get_cybrary_discussions_paginated($cybrary->id);
        $discussions = external_api::clean_returnvalue(mod_cybrary_external::get_cybrary_discussions_paginated_returns(), $discussions);

        $this->assertCount(1, $discussions['discussions']);
        $this->assertCount(0, $discussions['warnings']);
        $this->assertEquals($discussion['discussionid'], $discussions['discussions'][0]['discussion']);
        $this->assertEquals($group->id, $discussions['discussions'][0]['groupid']);

        // Now add a discussions without indicating a group. The function should guess the correct group.
        $discussion = mod_cybrary_external::add_discussion($cybrary->id, 'the subject', 'some text here...');
        $discussion = external_api::clean_returnvalue(mod_cybrary_external::add_discussion_returns(), $discussion);

        $discussions = mod_cybrary_external::get_cybrary_discussions_paginated($cybrary->id);
        $discussions = external_api::clean_returnvalue(mod_cybrary_external::get_cybrary_discussions_paginated_returns(), $discussions);

        $this->assertCount(2, $discussions['discussions']);
        $this->assertCount(0, $discussions['warnings']);
        $this->assertEquals($group->id, $discussions['discussions'][0]['groupid']);
        $this->assertEquals($group->id, $discussions['discussions'][1]['groupid']);

        // Enrol the same user in other group.
        $group2 = $this->getDataGenerator()->create_group(array('courseid' => $course->id));
        groups_add_member($group2->id, $user->id);

        // Now add a discussions without indicating a group. The function should guess the correct group (the first one).
        $discussion = mod_cybrary_external::add_discussion($cybrary->id, 'the subject', 'some text here...');
        $discussion = external_api::clean_returnvalue(mod_cybrary_external::add_discussion_returns(), $discussion);

        $discussions = mod_cybrary_external::get_cybrary_discussions_paginated($cybrary->id);
        $discussions = external_api::clean_returnvalue(mod_cybrary_external::get_cybrary_discussions_paginated_returns(), $discussions);

        $this->assertCount(3, $discussions['discussions']);
        $this->assertCount(0, $discussions['warnings']);
        $this->assertEquals($group->id, $discussions['discussions'][0]['groupid']);
        $this->assertEquals($group->id, $discussions['discussions'][1]['groupid']);
        $this->assertEquals($group->id, $discussions['discussions'][2]['groupid']);

    }

    /**
     * Test view_url
     */
    public function test_view_url() {
        global $DB;

        $this->resetAfterTest(true);

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $url = $this->getDataGenerator()->create_module('cybrary', array('course' => $course->id));
        $context = context_module::instance($url->cmid);
        $cm = get_coursemodule_from_instance('cybrary', $url->id);

        // Test invalid instance id.
        try {
            mod_cybrary_external::view_url(0);
            $this->fail('Exception expected due to invalid mod_cybrary instance id.');
        } catch (moodle_exception $e) {
            $this->assertEquals('invalidrecord', $e->errorcode);
        }

        // Test not-enrolled user.
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        try {
            mod_cybrary_external::view_url($url->id);
            $this->fail('Exception expected due to not enrolled user.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

        // Test user with full capabilities.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $result = mod_cybrary_external::view_url($url->id);
        $result = external_api::clean_returnvalue(mod_cybrary_external::view_url_returns(), $result);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_cybrary\event\course_module_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $moodleurl = new \moodle_url('/mod/cybrary/view.php', array('id' => $cm->id));
        $this->assertEquals($moodleurl, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

        // Test user with no capabilities.
        // We need a explicit prohibit since this capability is only defined in authenticated user and guest roles.
        assign_capability('mod/cybrary:view', CAP_PROHIBIT, $studentrole->id, $context->id);
        // Empty all the caches that may be affected by this change.
        accesslib_clear_all_caches_for_unit_testing();
        course_modinfo::clear_instance_cache();

        try {
            mod_cybrary_external::view_url($url->id);
            $this->fail('Exception expected due to missing capability.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

    }

}
