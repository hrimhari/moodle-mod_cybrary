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
 * Steps definitions related with the cybrary activity.
 *
 * @package    mod_cybrary
 * @category   test
 * @copyright  2013 David Monllaó
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Behat\Context\Step\Given as Given,
    Behat\Gherkin\Node\TableNode as TableNode;
/**
 * Cybrary-related steps definitions.
 *
 * @package    mod_cybrary
 * @category   test
 * @copyright  2013 David Monllaó
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_cybrary extends behat_base {

    /**
     * Adds a topic to the cybrary specified by it's name. Useful for the News cybrary and blog-style cybraries.
     *
     * @Given /^I add a new topic to "(?P<cybrary_name_string>(?:[^"]|\\")*)" cybrary with:$/
     * @param string $cybraryname
     * @param TableNode $table
     */
    public function i_add_a_new_topic_to_cybrary_with($cybraryname, TableNode $table) {
        return $this->add_new_discussion($cybraryname, $table, get_string('addanewtopic', 'cybrary'));
    }

    /**
     * Adds a discussion to the cybrary specified by it's name with the provided table data (usually Subject and Message). The step begins from the cybrary's course page.
     *
     * @Given /^I add a new discussion to "(?P<cybrary_name_string>(?:[^"]|\\")*)" cybrary with:$/
     * @param string $cybraryname
     * @param TableNode $table
     */
    public function i_add_a_cybrary_discussion_to_cybrary_with($cybraryname, TableNode $table) {
        return $this->add_new_discussion($cybraryname, $table, get_string('addanewdiscussion', 'cybrary'));
    }

    /**
     * Adds a reply to the specified post of the specified cybrary. The step begins from the cybrary's page or from the cybrary's course page.
     *
     * @Given /^I reply "(?P<post_subject_string>(?:[^"]|\\")*)" post from "(?P<cybrary_name_string>(?:[^"]|\\")*)" cybrary with:$/
     * @param string $postname The subject of the post
     * @param string $cybraryname The cybrary name
     * @param TableNode $table
     */
    public function i_reply_post_from_cybrary_with($postsubject, $cybraryname, TableNode $table) {

        return array(
            new Given('I follow "' . $this->escape($cybraryname) . '"'),
            new Given('I follow "' . $this->escape($postsubject) . '"'),
            new Given('I follow "' . get_string('reply', 'cybrary') . '"'),
            new Given('I set the following fields to these values:', $table),
            new Given('I press "' . get_string('posttocybrary', 'cybrary') . '"'),
            new Given('I wait to be redirected')
        );

    }

    /**
     * Returns the steps list to add a new discussion to a cybrary.
     *
     * Abstracts add a new topic and add a new discussion, as depending
     * on the cybrary type the button string changes.
     *
     * @param string $cybraryname
     * @param TableNode $table
     * @param string $buttonstr
     * @return Given[]
     */
    protected function add_new_discussion($cybraryname, TableNode $table, $buttonstr) {

        // Escaping $cybraryname as it has been stripped automatically by the transformer.
        return array(
            new Given('I follow "' . $this->escape($cybraryname) . '"'),
            new Given('I press "' . $buttonstr . '"'),
            new Given('I set the following fields to these values:', $table),
            new Given('I press "' . get_string('posttocybrary', 'cybrary') . '"'),
            new Given('I wait to be redirected')
        );

    }

}
