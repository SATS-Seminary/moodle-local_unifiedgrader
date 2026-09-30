@local @local_unifiedgrader @local_unifiedgrader_critical
Feature: Unified Grader applies late penalties on Moodle 5.3
  As a teacher
  I want late work penalised by the grade penalty rules, whoever grades it and wherever
  So that every assignment, forum and quiz follows the same late penalty policy

  # From Moodle 5.3 the quiz has its own due date and Unified Grader owns late
  # penalties. The first step skips these scenarios on earlier versions.
  #
  # Rules: up to a day late costs 5%, up to a week 10%. The quiz was due two
  # days ago, so an attempt made now is 10% late: full marks become 90.

  Background:
    Given Unified Grader manages late penalties on this site
    And the following "courses" exist:
      | fullname    | shortname | category |
      | Test Course | TC101     | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teach     | One      | teacher1@example.com |
      | student1 | Stu       | Dent     | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC101  | editingteacher |
      | student1 | TC101  | student        |
    And the following config values are set as admin:
      | enable_quiz | 1 | local_unifiedgrader |
    And the following late penalty rules exist:
      | overdue by (days) | penalty |
      | 1                 | 5       |
      | 7                 | 10      |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | TC101     | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext    |
      | Test questions   | truefalse | TF1  | Is this a test? |
    And the following "activities" exist:
      | activity | name   | course | idnumber | duedate      |
      | quiz     | Quiz 1 | TC101  | q1       | ##-2 days##  |
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |

  Scenario: The quiz settings offer the late penalty switch, off for a new quiz
    Given I am on the "Quiz 1" "quiz activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    Then the field "Apply late penalties" matches value "0"
    When I set the field "Apply late penalties" to "1"
    And I press "Save and return to course"
    And I am on the "Quiz 1" "quiz activity editing" page
    And I expand all fieldsets
    Then the field "Apply late penalties" matches value "1"

  @javascript
  Scenario: A late quiz attempt is penalised in the grader and the gradebook without marking
    Given late penalties are applied to "Quiz 1"
    And user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      | 1    | True     |
    When I log in as "teacher1"
    And I am on the Unified Grader for activity "Quiz 1"
    And the marking panel has loaded
    Then I should see "-10%" in the "[data-region=penalty-badges]" "css_element"
    When I am on the "Test Course" "grades > Grader report > View" page
    Then "90.00" "text" should exist in the "Stu Dent" "table_row"

  @javascript
  Scenario: A due date override made on the quiz's own page removes the late penalty
    Given late penalties are applied to "Quiz 1"
    And user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      | 1    | True     |
    When I am on the "Quiz 1" "mod_quiz > User overrides" page logged in as "teacher1"
    And I change window size to "large"
    And I press "Add user override"
    And I set the following fields to these values:
      | Override user | Stu Dent (student1@example.com) |
      | Due date      | ##tomorrow##                    |
    And I press "Save"
    And I am on the Unified Grader for activity "Quiz 1"
    And the marking panel has loaded
    Then I should not see "-10%" in the "[data-region=penalty-badges]" "css_element"
    When I am on the "Test Course" "grades > Grader report > View" page
    Then "100.00" "text" should exist in the "Stu Dent" "table_row"

  @javascript
  Scenario: A quiz with the switch off is not penalised
    Given user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      | 1    | True     |
    When I log in as "teacher1"
    And I am on the "Test Course" "grades > Grader report > View" page
    Then "100.00" "text" should exist in the "Stu Dent" "table_row"
