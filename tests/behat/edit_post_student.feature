@mod @mod_cybrary
Feature: Students can edit or delete their cybrary posts within a set time limit
  In order to refine cybrary posts
  As a user
  I need to edit or delete my cybrary posts within a certain period of time after posting

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student | 1 | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |
    And the following "activities" exist:
      | activity   | name                   | intro                   | course  | idnumber  |
      | cybrary      | Test cybrary name        | Test cybrary description  | C1      | cybrary     |
    And I log in as "student1"
    And I follow "Course 1"
    And I add a new discussion to "Test cybrary name" cybrary with:
      | Subject | Cybrary post subject |
      | Message | This is the body |

  Scenario: Edit cybrary post
    Given I follow "Cybrary post subject"
    And I follow "Edit"
    When I set the following fields to these values:
      | Subject | Edited post subject |
      | Message | Edited post body |
    And I press "Save changes"
    And I wait to be redirected
    Then I should see "Edited post subject"
    And I should see "Edited post body"

  Scenario: Delete cybrary post
    Given I follow "Cybrary post subject"
    When I follow "Delete"
    And I press "Continue"
    Then I should not see "Cybrary post subject"

  @javascript
  Scenario: Time limit expires
    Given I log out
    And I log in as "admin"
    And I expand "Site administration" node
    And I expand "Security" node
    And I follow "Site policies"
    And I set the field "Maximum time to edit posts" to "1 minutes"
    And I press "Save changes"
    And I am on site homepage
    And I follow "Course 1"
    And I turn editing mode on
    And I add a "Cybrary" to section "1" and I fill the form with:
      | Cybrary name | Test cybrary name |
      | Cybrary type | Standard cybrary for general use |
      | Description | Test cybrary description |
    And I log out
    And I log in as "student1"
    And I follow "Course 1"
    When I wait "61" seconds
    And I follow "Cybrary post subject"
    Then I should not see "Edit" in the "region-main" "region"
    And I should not see "Delete" in the "region-main" "region"
