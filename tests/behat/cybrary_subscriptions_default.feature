@mod @mod_cybrary
Feature: A user can control their default discussion subscription settings
  In order to automatically subscribe to discussions
  As a user
  I can choose my default subscription preference

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                   | autosubscribe |
      | student1 | Student   | One      | student.one@example.com | 1             |
      | student2 | Student   | Two      | student.one@example.com | 0             |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |
      | student2 | C1 | student |
    And I log in as "admin"
    And I am on site homepage
    And I follow "Course 1"
    And I turn editing mode on

  Scenario: Creating a new discussion in an optional cybrary follows user preferences
    Given I add a "Cybrary" to section "1" and I fill the form with:
      | Cybrary name        | Test cybrary name |
      | Cybrary type        | Standard cybrary for general use |
      | Description       | Test cybrary description |
      | Subscription mode | Optional subscription |
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    And I follow "Test cybrary name"
    When I press "Add a new discussion topic"
    Then "input[name=discussionsubscribe][checked=checked]" "css_element" should exist
    And I log out
    And I log in as "student2"
    And I follow "Course 1"
    And I follow "Test cybrary name"
    And I press "Add a new discussion topic"
    And "input[name=discussionsubscribe]:not([checked=checked])" "css_element" should exist

  Scenario: Replying to an existing discussion in an optional cybrary follows user preferences
    Given I add a "Cybrary" to section "1" and I fill the form with:
      | Cybrary name        | Test cybrary name |
      | Cybrary type        | Standard cybrary for general use |
      | Description       | Test cybrary description |
      | Subscription mode | Optional subscription |
    And I add a new discussion to "Test cybrary name" cybrary with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    And I follow "Test cybrary name"
    And I follow "Test post subject"
    When I follow "Reply"
    Then "input[name=discussionsubscribe][checked=checked]" "css_element" should exist
    And I log out
    And I log in as "student2"
    And I follow "Course 1"
    And I follow "Test cybrary name"
    And I follow "Test post subject"
    And I follow "Reply"
    And "input[name=discussionsubscribe]:not([checked=checked])" "css_element" should exist

  Scenario: Creating a new discussion in an automatic cybrary follows cybrary subscription
    Given I add a "Cybrary" to section "1" and I fill the form with:
      | Cybrary name        | Test cybrary name |
      | Cybrary type        | Standard cybrary for general use |
      | Description       | Test cybrary description |
      | Subscription mode | Auto subscription |
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    And I follow "Test cybrary name"
    When I press "Add a new discussion topic"
    Then "input[name=discussionsubscribe][checked=checked]" "css_element" should exist
    And I log out
    And I log in as "student2"
    And I follow "Course 1"
    And I follow "Test cybrary name"
    And I press "Add a new discussion topic"
    And "input[name=discussionsubscribe][checked=checked]" "css_element" should exist

  Scenario: Replying to an existing discussion in an automatic cybrary follows cybrary subscription
    Given I add a "Cybrary" to section "1" and I fill the form with:
      | Cybrary name        | Test cybrary name |
      | Cybrary type        | Standard cybrary for general use |
      | Description       | Test cybrary description |
      | Subscription mode | Optional subscription |
    And I add a new discussion to "Test cybrary name" cybrary with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    And I follow "Test cybrary name"
    And I follow "Test post subject"
    When I follow "Reply"
    Then "input[name=discussionsubscribe][checked=checked]" "css_element" should exist
    And I log out
    And I log in as "student2"
    And I follow "Course 1"
    And I follow "Test cybrary name"
    And I follow "Test post subject"
    And I follow "Reply"
    And "input[name=discussionsubscribe]:not([checked=checked])" "css_element" should exist

  Scenario: Replying to an existing discussion in an automatic cybrary which has been unsubscribed from follows user preferences
    Given I add a "Cybrary" to section "1" and I fill the form with:
      | Cybrary name        | Test cybrary name |
      | Cybrary type        | Standard cybrary for general use |
      | Description       | Test cybrary description |
      | Subscription mode | Auto subscription |
    And I add a new discussion to "Test cybrary name" cybrary with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    And I follow "Test cybrary name"
    And I click on "You are subscribed to this discussion. Click to unsubscribe." "link" in the "Test post subject" "table_row"
    And I follow "Continue"
    And I follow "Test post subject"
    When I follow "Reply"
    And "input[name=discussionsubscribe][checked=checked]" "css_element" should exist
    And I log out
    And I log in as "student2"
    And I follow "Course 1"
    And I follow "Test cybrary name"
    And I click on "You are subscribed to this discussion. Click to unsubscribe." "link" in the "Test post subject" "table_row"
    And I follow "Continue"
    And I follow "Test post subject"
    And I follow "Reply"
    And "input[name=discussionsubscribe]:not([checked=checked])" "css_element" should exist
