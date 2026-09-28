@local @local_unifiedgrader @local_unifiedgrader_critical
Feature: Students can open their feedback for every supported activity type
  In order to read what my teacher wrote about my work
  As a student
  I need the feedback page to load for assignments, forums, quizzes and BigBlueButton sessions

  # view_feedback.php builds a separate page for each activity type, and a
  # branch can throw while the others work: v2.12.1 crashed the quiz page for
  # every student with "grading_template_data(): Argument #1 ($gradinginfo)
  # must be of type array, null given". PHPUnit cannot execute the script, so
  # each scenario opens the real page as the student.

  Background:
    Given the following "courses" exist:
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
      | enable_assign          | 1 | local_unifiedgrader |
      | enable_forum           | 1 | local_unifiedgrader |
      | enable_quiz            | 1 | local_unifiedgrader |
      | enable_bigbluebuttonbn | 1 | local_unifiedgrader |

  Scenario: A student opens their assignment feedback
    Given the following "activities" exist:
      | activity | name    | course | idnumber | grade | assignsubmission_onlinetext_enabled | assignfeedback_comments_enabled |
      | assign   | Essay 1 | TC101  | a1       | 20    | 1                                   | 1                               |
    And the following "mod_assign > submissions" exist:
      | assign  | user     | onlinetext         |
      | Essay 1 | student1 | My essay, in full. |
    And "student1" has been graded with feedback "A well argued essay" on "Essay 1"
    When I log in as "student1"
    And I am on the feedback page for activity "Essay 1"
    Then I should see "A well argued essay"
    And I should not see "Exception"

  Scenario: A student opens their forum feedback
    Given the following "activities" exist:
      | activity | name        | intro      | course | idnumber | grade_forum |
      | forum    | Class Forum | Discuss it | TC101  | f1       | 20          |
    And the following "mod_forum > discussions" exist:
      | forum | user     | name       | message                    |
      | f1    | student1 | My opinion | Here is what I make of it. |
    And "student1" has been graded with feedback "Thoughtful contribution" on "Class Forum"
    When I log in as "student1"
    And I am on the feedback page for activity "Class Forum"
    Then I should see "Thoughtful contribution"
    And I should not see "Exception"

  Scenario: A student opens their quiz feedback
    Given the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | TC101     | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext    |
      | Test questions   | truefalse | TF1  | The sky is blue |
    And the following "activities" exist:
      | activity | name   | course | idnumber |
      | quiz     | Quiz 1 | TC101  | q1       |
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |
    And user "student1" has attempted "Quiz 1" with responses:
      | slot | response |
      | 1    | True     |
    And "student1" has been graded with feedback "Good work on the quiz" on "Quiz 1"
    When I log in as "student1"
    And I am on the feedback page for activity "Quiz 1"
    Then I should see "Good work on the quiz"
    And I should not see "Exception"

  Scenario: A student opens their BigBlueButton feedback
    Given I enable "bigbluebuttonbn" "mod" plugin
    And the following "activities" exist:
      | activity        | name         | course | idnumber | grade |
      | bigbluebuttonbn | Live Seminar | TC101  | bbb1     | 20    |
    And "student1" has been graded with feedback "Engaged throughout" on "Live Seminar"
    When I log in as "student1"
    And I am on the feedback page for activity "Live Seminar"
    Then I should see "Engaged throughout"
    And I should not see "Exception"
