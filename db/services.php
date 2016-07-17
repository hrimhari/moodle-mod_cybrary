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
 * Cybrary external functions and service definitions.
 *
 * @package    mod_cybrary
 * @copyright  2012 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = array(

    'mod_cybrary_get_cybraries_by_courses' => array(
        'classname' => 'mod_cybrary_external',
        'methodname' => 'get_cybraries_by_courses',
        'classpath' => 'mod/cybrary/externallib.php',
        'description' => 'Returns a list of cybrary instances in a provided set of courses, if
            no courses are provided then all the cybrary instances the user has access to will be
            returned.',
        'type' => 'read',
        'capabilities' => 'mod/cybrary:viewdiscussion'
    ),

    'mod_cybrary_get_cybrary_discussions' => array(
        'classname' => 'mod_cybrary_external',
        'methodname' => 'get_cybrary_discussions',
        'classpath' => 'mod/cybrary/externallib.php',
        'description' => 'DEPRECATED (use mod_cybrary_get_cybrary_discussions_paginated instead):
                            Returns a list of cybrary discussions contained within a given set of cybraries.',
        'type' => 'read',
        'capabilities' => 'mod/cybrary:viewdiscussion, mod/cybrary:viewqandawithoutposting'
    ),

    'mod_cybrary_get_cybrary_discussion_posts' => array(
        'classname' => 'mod_cybrary_external',
        'methodname' => 'get_cybrary_discussion_posts',
        'classpath' => 'mod/cybrary/externallib.php',
        'description' => 'Returns a list of cybrary posts for a discussion.',
        'type' => 'read',
        'capabilities' => 'mod/cybrary:viewdiscussion, mod/cybrary:viewqandawithoutposting'
    ),

    'mod_cybrary_get_cybrary_discussions_paginated' => array(
        'classname' => 'mod_cybrary_external',
        'methodname' => 'get_cybrary_discussions_paginated',
        'classpath' => 'mod/cybrary/externallib.php',
        'description' => 'Returns a list of cybrary discussions optionally sorted and paginated.',
        'type' => 'read',
        'capabilities' => 'mod/cybrary:viewdiscussion, mod/cybrary:viewqandawithoutposting'
    ),

    'mod_cybrary_view_cybrary' => array(
        'classname' => 'mod_cybrary_external',
        'methodname' => 'view_cybrary',
        'classpath' => 'mod/cybrary/externallib.php',
        'description' => 'Trigger the course module viewed event and update the module completion status.',
        'type' => 'write',
        'capabilities' => 'mod/cybrary:viewdiscussion'
    ),

    'mod_cybrary_view_cybrary_discussion' => array(
        'classname' => 'mod_cybrary_external',
        'methodname' => 'view_cybrary_discussion',
        'classpath' => 'mod/cybrary/externallib.php',
        'description' => 'Trigger the cybrary discussion viewed event.',
        'type' => 'write',
        'capabilities' => 'mod/cybrary:viewdiscussion'
    ),

    'mod_cybrary_add_discussion_post' => array(
        'classname' => 'mod_cybrary_external',
        'methodname' => 'add_discussion_post',
        'classpath' => 'mod/cybrary/externallib.php',
        'description' => 'Create new posts into an existing discussion.',
        'type' => 'write',
        'capabilities' => 'mod/cybrary:replypost'
    ),

    'mod_cybrary_add_discussion' => array(
        'classname' => 'mod_cybrary_external',
        'methodname' => 'add_discussion',
        'classpath' => 'mod/cybrary/externallib.php',
        'description' => 'Add a new discussion into an existing cybrary.',
        'type' => 'write',
        'capabilities' => 'mod/cybrary:startdiscussion'
    ),
);
