@local @local_unifiedgrader @javascript
Feature: Teachers post grades for one student, one group, or the whole class
  In order to release feedback to the students who should see it
  As a teacher
  I need to post or hide grades for the open student, the groups I am viewing, or the class

  Background:
    Given the following "courses" exist:
      | fullname    | shortname | category |
      | Test Course | TC101     | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teach     | One      | teacher1@example.com |
      | student1 | Ann       | Able     | student1@example.com |
      | student2 | Ben       | Baker    | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC101  | editingteacher |
      | student1 | TC101  | student        |
      | student2 | TC101  | student        |
    And the following "groups" exist:
      | name    | course | idnumber |
      | Group A | TC101  | ga       |
      | Group B | TC101  | gb       |
    And the following "group members" exist:
      | user     | group |
      | student1 | ga    |
      | student2 | gb    |
    And the following config values are set as admin:
      | enable_assign | 1 | local_unifiedgrader |
    And the following "activities" exist:
      | activity | name    | course | idnumber | grade | groupmode | assignsubmission_onlinetext_enabled | assignfeedback_comments_enabled |
      | assign   | Essay 1 | TC101  | a1       | 20    | 1         | 1                                   | 1                               |
    And the following "mod_assign > submissions" exist:
      | assign  | user     | onlinetext |
      | Essay 1 | student1 | Ann's essay |
      | Essay 1 | student2 | Ben's essay |
    And "student1" has been graded with feedback "Ann is ready" on "Essay 1"
    And "student2" has been graded with feedback "Ben is ready" on "Essay 1"
    And grades are hidden for activity "Essay 1"

  Scenario: Post and hide the open student
    When I log in as "teacher1"
    And I am on the Unified Grader for activity "Essay 1"
    And the marking panel has loaded
    And I post grades for the open student
    Then the grade status shows "1 of 2 posted"
    And the open student's grade is posted
    And "1" participant grades are posted
    And "1" participant grades are hidden
    When I hide grades for the open student
    Then the grade status shows "Grades hidden"
    And the open student's grade is hidden
    And "2" participant grades are hidden

  Scenario: Post the group in the filter
    When I log in as "teacher1"
    And I am on the Unified Grader for activity "Essay 1"
    And the marking panel has loaded
    And I select group "Group A" in the navigator
    And I post grades for the groups in view
    Then the grade status shows "1 of 2 posted"
    And the open student's grade is posted
    When I select group "Group B" in the navigator
    Then the participant "Ben Baker" grade is hidden

  Scenario: Post the whole class, then hide one student
    When I log in as "teacher1"
    And I am on the Unified Grader for activity "Essay 1"
    And the marking panel has loaded
    And I post grades for the whole class
    Then the grade status shows "Grades posted"
    And "2" participant grades are posted
    When I hide grades for the open student
    Then the grade status shows "1 of 2 posted"
    And the open student's grade is hidden
    And "1" participant grades are posted

  Scenario: A posted student can read feedback and a hidden student cannot
    When I log in as "teacher1"
    And I am on the Unified Grader for activity "Essay 1"
    And the marking panel has loaded
    And I click on "Ann Able" "button"
    And I post grades for the open student
    And I log out
    And I log in as "student1"
    And I am on the feedback page for activity "Essay 1"
    Then I should see "Ann is ready"
    When I log out
    And I log in as "student2"
    And I am on the feedback page for activity "Essay 1"
    Then I should see "Your feedback is not yet available"
