@mod @mod_cybrary
Feature: A teacher can move discussions between cybraries
  In order to move a discussion
  As a teacher
  I need to use the move discussion selector

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | teacher1 | Teacher   | 1        | teacher1@example.com  |
      | student1 | Student   | 1        | student1@example.com  |
    And the following "courses" exist:
      | fullname | shortname  | category  |
      | Course 1 | C1         | 0         |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
      | student1 | C1 | student |

  Scenario: A teacher can move discussions
    Given the following "activities" exist:
      | activity   | name                   | intro             | course | idnumber     | groupmode |
      | cybrary      | Test cybrary 1           | Test cybrary 2      | C1     | cybrary        | 0         |
      | cybrary      | Test cybrary 2           | Test cybrary 1      | C1     | cybrary        | 0         |
    And I log in as "student1"
    And I follow "Course 1"
    And I follow "Test cybrary 1"
    And I add a new discussion to "Test cybrary 1" cybrary with:
      | Subject | Discussion 1 |
      | Message | Test post message |
    And I wait "1" seconds
    And I log out
    And I log in as "teacher1"
    And I follow "Course 1"
    And I follow "Test cybrary 1"
    And I follow "Discussion 1"
    When I set the field "jump" to "Test cybrary 2"
    And I press "Move"
    Then I should see "This discussion has been moved to 'Test cybrary 2'."
    And I press "Move"
    And I should see "Discussion 1"
