@mod @mod_cybrary
Feature: A teacher can control the subscription to a cybrary
  In order to change individual user's subscriptions
  As a course administrator
  I can change subscription setting for my users

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher  | Teacher   | Tom      | teacher@example.com   |
      | student1 | Student   | 1        | student.1@example.com |
      | student2 | Student   | 2        | student.2@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher  | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And I log in as "teacher"
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Cybrary" to section "1" and I fill the form with:
      | Cybrary name        | Test cybrary name                |
      | Cybrary type        | Standard cybrary for general use |
      | Description       | Test cybrary description         |
      | Subscription mode | Auto subscription              |

  Scenario: A teacher can change toggle subscription editing on and off
    Given I follow "Test cybrary name"
    And I follow "Show/edit current subscribers"
    Then ".userselector" "css_element" should not exist
    And "Turn editing on" "button" should exist
    And I press "Turn editing on"
    And ".userselector" "css_element" should exist
    And "Turn editing off" "button" should exist
    And I press "Turn editing off"
    And ".userselector" "css_element" should not exist
    And "Turn editing on" "button" should exist
    And I press "Turn editing on"
    And ".userselector" "css_element" should exist
    And "Turn editing off" "button" should exist
    And I press "Turn editing off"
    And ".userselector" "css_element" should not exist
    And "Turn editing on" "button" should exist
