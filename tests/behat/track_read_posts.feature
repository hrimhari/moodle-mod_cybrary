@mod @mod_cybrary
Feature: A teacher can set one of 3 possible options for tracking read cybrary posts
  In order to ease the cybrary posts follow up
  As a user
  I need to distinct the unread posts from the read ones

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email | trackcybraries |
      | student1 | Student | 1 | student1@example.com | 1 |
      | student2 | Student | 2 | student2@example.com | 0 |
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

  Scenario: Tracking cybrary posts off
    Given I add a "Cybrary" to section "1" and I fill the form with:
      | Cybrary name | Test cybrary name |
      | Cybrary type | Standard cybrary for general use |
      | Description | Test cybrary description |
      | Read tracking | Off |
    And I add a new discussion to "Test cybrary name" cybrary with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student1"
    And I follow "Course 1"
    Then I should not see "1 unread post"
    And I follow "Test cybrary name"
    And I should not see "Track unread posts"

  Scenario: Tracking cybrary posts optional with user tracking on
    Given I add a "Cybrary" to section "1" and I fill the form with:
      | Cybrary name | Test cybrary name |
      | Cybrary type | Standard cybrary for general use |
      | Description | Test cybrary description |
      | Read tracking | Optional |
    And I add a new discussion to "Test cybrary name" cybrary with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student1"
    And I follow "Course 1"
    Then I should see "1 unread post"
    And I follow "Test cybrary name"
    And I follow "Don't track unread posts"
    And I wait to be redirected
    And I follow "Course 1"
    And I should not see "1 unread post"
    And I follow "Test cybrary name"
    And I follow "Track unread posts"
    And I wait to be redirected
    And I click on "1" "link" in the "Admin User" "table_row"
    And I follow "Course 1"
    And I should not see "1 unread post"

  Scenario: Tracking cybrary posts optional with user tracking off
    Given I add a "Cybrary" to section "1" and I fill the form with:
      | Cybrary name | Test cybrary name |
      | Cybrary type | Standard cybrary for general use |
      | Description | Test cybrary description |
      | Read tracking | Optional |
    And I add a new discussion to "Test cybrary name" cybrary with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student2"
    And I follow "Course 1"
    Then I should not see "1 unread post"
    And I follow "Test cybrary name"
    And I should not see "Track unread posts"

  Scenario: Tracking cybrary posts forced with user tracking on
    Given the following config values are set as admin:
      | cybrary_allowforcedreadtracking | 1 |
    And I am on site homepage
    And I follow "Course 1"
    Given I add a "Cybrary" to section "1" and I fill the form with:
      | Cybrary name | Test cybrary name |
      | Cybrary type | Standard cybrary for general use |
      | Description | Test cybrary description |
      | Read tracking | Force |
    And I add a new discussion to "Test cybrary name" cybrary with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student1"
    And I follow "Course 1"
    Then I should see "1 unread post"
    And I follow "1 unread post"
    And I should not see "Don't track unread posts"
    And I follow "Test post subject"
    And I follow "Course 1"
    And I should not see "1 unread post"

  Scenario: Tracking cybrary posts forced with user tracking off
    Given the following config values are set as admin:
      | cybrary_allowforcedreadtracking | 1 |
    And I am on site homepage
    And I follow "Course 1"
    Given I add a "Cybrary" to section "1" and I fill the form with:
      | Cybrary name | Test cybrary name |
      | Cybrary type | Standard cybrary for general use |
      | Description | Test cybrary description |
      | Read tracking | Force |
    And I add a new discussion to "Test cybrary name" cybrary with:
      | Subject | Test post subject |
      | Message | Test post message |
    And I log out
    When I log in as "student2"
    And I follow "Course 1"
    Then I should see "1 unread post"
    And I follow "1 unread post"
    And I should not see "Don't track unread posts"
    And I follow "Test post subject"
    And I follow "Course 1"
    And I should not see "1 unread post"

  Scenario: Tracking cybrary posts forced (with force disabled) with user tracking on
    Given the following config values are set as admin:
      | cybrary_allowforcedreadtracking | 1 |
    And I am on site homepage
    And I follow "Course 1"
    Given I add a "Cybrary" to section "1" and I fill the form with:
      | Cybrary name | Test cybrary name |
      | Cybrary type | Standard cybrary for general use |
      | Description | Test cybrary description |
      | Read tracking | Force |
    And I add a new discussion to "Test cybrary name" cybrary with:
      | Subject | Test post subject |
      | Message | Test post message |
    And the following config values are set as admin:
      | cybrary_allowforcedreadtracking | 0 |
    And I log out
    When I log in as "student1"
    And I follow "Course 1"
    Then I should see "1 unread post"
    And I follow "Test cybrary name"
    And I follow "Don't track unread posts"
    And I wait to be redirected
    And I follow "Course 1"
    And I should not see "1 unread post"
    And I follow "Test cybrary name"
    And I follow "Track unread posts"
    And I wait to be redirected
    And I click on "1" "link" in the "Admin User" "table_row"
    And I follow "Course 1"
    And I should not see "1 unread post"

  Scenario: Tracking cybrary posts forced (with force disabled) with user tracking off
    Given the following config values are set as admin:
      | cybrary_allowforcedreadtracking | 1 |
    And I am on site homepage
    And I follow "Course 1"
    Given I add a "Cybrary" to section "1" and I fill the form with:
      | Cybrary name | Test cybrary name |
      | Cybrary type | Standard cybrary for general use |
      | Description | Test cybrary description |
      | Read tracking | Force |
    And I add a new discussion to "Test cybrary name" cybrary with:
      | Subject | Test post subject |
      | Message | Test post message |
    And the following config values are set as admin:
      | cybrary_allowforcedreadtracking | 0 |
    And I log out
    When I log in as "student2"
    And I follow "Course 1"
    Then I should not see "1 unread post"
    And I follow "Test cybrary name"
    And I should not see "Track unread posts"
