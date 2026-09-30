@local @local_unifiedgrader @local_unifiedgrader_critical
Feature: Moodle's activity navigation footer stays out of the grader
  As a teacher
  I want the grader free of Moodle's "Previous / Next activity" footer
  So that it does not cover the grading controls

  # Moodle 5.3 adds a sticky "Previous / Next activity" footer to every page
  # with an activity context. The grader switches it off.
  # The first check shows the footer really is on for this course.

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
