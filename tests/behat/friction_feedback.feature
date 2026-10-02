@local @local_unifiedgrader @javascript
Feature: Students read feedback before the mark appears
  In order to meet the feedback before the score
  As a student
  I need to tick through each part and only then see my mark

  Background:
    Given the following "courses" exist:
      | fullname    | shortname | category |
      | Test Course | TC101     | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teach     | One      | teacher1@example.com |
      | student1 | Ann       | Able     | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC101  | editingteacher |
      | student1 | TC101  | student        |
    And the following config values are set as admin:
      | enable_assign     | 1 | local_unifiedgrader |
      | friction_enable   | 1 | local_unifiedgrader |
      | friction_seconds  | 0 | local_unifiedgrader |
    And the following "activities" exist:
      | activity | name    | course | idnumber | grade | assignsubmission_onlinetext_enabled | assignfeedback_comments_enabled |
      | assign   | Essay 1 | TC101  | a1       | 20    | 1                                   | 1                               |
    And the following "mod_assign > submissions" exist:
      | assign  | user     | onlinetext  |
      | Essay 1 | student1 | Ann's essay |
    And "student1" has been graded with feedback "Ann is ready" on "Essay 1"
    And grades are hidden for activity "Essay 1"

  Scenario: A posted student ticks through the feedback and then sees the mark
    When I log in as "teacher1"
    And I am on the Unified Grader for activity "Essay 1"
    And the marking panel has loaded
    And I post grades for the whole class
    Then the grade status shows "Grades posted"
    And I log out
    And I log in as "student1"
    And I am on the feedback page for activity "Essay 1"
    Then I should see "Ann is ready"
    And I should see "I have seen this"
    And I should not see "Add time"
    And I should not see "15 / 20"
    When I click on "I have seen this" "button"
    Then I should see "Ann is ready"
    And I should see "15 / 20"
    And I should not see "I have seen this"
    When I reload the page
    Then I should see "15 / 20"
    And I should not see "I have seen this"
