@local @local_unifiedgrader @local_unifiedgrader_critical
Feature: Moodle's activity navigation footer stays out of Unified Grader
  As a teacher or a student
  I want Unified Grader's own pages free of Moodle's "Previous / Next activity" footer
  So that it does not cover the grading controls or the feedback

  # Moodle 5.3 adds a sticky "Previous / Next activity" footer to every page
  # with an activity context. The grader and the student feedback page switch
  # it off. The first check in each scenario shows the footer really is on
  # for this course.

  Background:
    Given the following "courses" exist:
      | fullname    | shortname | category | format |
      | Test Course | TC101     | 0        | topics |
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
    And the following "activities" exist:
      | activity | name   | course | idnumber |
      | page     | Page 1 | TC101  | p1       |
      | quiz     | Quiz 1 | TC101  | q1       |
      | page     | Page 2 | TC101  | p2       |

  Scenario: The grader has no activity navigation footer
    Given Moodle adds an activity navigation footer on this site
    When I am on the "Quiz 1" "quiz activity" page logged in as "teacher1"
    Then ".course-linear-navigation" "css_element" should exist
    When I am on the Unified Grader for activity "Quiz 1"
    Then ".course-linear-navigation" "css_element" should not exist

  Scenario: The student feedback page has no activity navigation footer
    Given Moodle adds an activity navigation footer on this site
    And the following config values are set as admin:
      | enable_assign | 1 | local_unifiedgrader |
    And the following "activities" exist:
      | activity | name    | course | idnumber | grade | assignsubmission_onlinetext_enabled | assignfeedback_comments_enabled |
      | assign   | Essay 1 | TC101  | a1       | 20    | 1                                   | 1                               |
    And "student1" has been graded with feedback "A well argued essay" on "Essay 1"
    When I am on the "Essay 1" "assign activity" page logged in as "student1"
    Then ".course-linear-navigation" "css_element" should exist
    When I am on the feedback page for activity "Essay 1"
    Then ".course-linear-navigation" "css_element" should not exist
    And I should see "A well argued essay"

  Scenario: The feedback page still hides the footer when the grade is not released
    Given Moodle adds an activity navigation footer on this site
    And the following config values are set as admin:
      | enable_assign | 1 | local_unifiedgrader |
    And the following "activities" exist:
      | activity | name    | course | idnumber | grade | assignsubmission_onlinetext_enabled | assignfeedback_comments_enabled |
      | assign   | Essay 1 | TC101  | a1       | 20    | 1                                   | 1                               |
    When I am on the "Essay 1" "assign activity" page logged in as "student1"
    Then ".course-linear-navigation" "css_element" should exist
    When I am on the feedback page for activity "Essay 1"
    Then ".course-linear-navigation" "css_element" should not exist
    And I should see "Your feedback is not yet available"
