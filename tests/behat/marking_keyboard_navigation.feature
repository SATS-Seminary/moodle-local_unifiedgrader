@local @local_unifiedgrader @local_unifiedgrader_critical @javascript
Feature: Arrow-key student navigation respects editing context
  As a teacher leaving overall feedback
  I want arrow keys to move within the editor, not switch students
  So that my feedback and grade never land on the wrong student's submission

  Background:
    Given the following "courses" exist:
      | fullname    | shortname | category |
      | Test Course | TC101     | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teach     | One      | teacher1@example.com |
      | student1 | Stu       | Dent     | student1@example.com |
      | student2 | Otto      | Mate     | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC101  | editingteacher |
      | student1 | TC101  | student        |
      | student2 | TC101  | student        |
    And the following "activities" exist:
      | activity | name    | course | idnumber | grade |
      | assign   | Essay 1 | TC101  | a1       | 20    |
    And I log in as "teacher1"

  Scenario: Arrow keys while editing overall feedback do not switch students
    # The regression: a stray arrow press in the feedback editor (e.g. while
    # cleaning up HTML in the source-code dialog) used to advance the student,
    # so the next save landed on someone else's submission.
    When I am on the Unified Grader for activity "Essay 1"
    And the marking panel has loaded
    And I note the current Unified Grader student
    And I press the right arrow key from the editor toolbar
    Then the Unified Grader student should be unchanged
    When I press the left arrow key from the editor toolbar
    Then the Unified Grader student should be unchanged

  Scenario: Arrow keys in a grade or score input do not switch students
    # Representative of every text-entry box in the marking panel — rubric
    # score inputs, marking-guide remarks and comment textareas all sit in
    # this same INPUT/TEXTAREA class. Arrows must only move the cursor.
    When I am on the Unified Grader for activity "Essay 1"
    And the marking panel has loaded
    And I note the current Unified Grader student
    And I press the right arrow key from the grade input
    Then the Unified Grader student should be unchanged
    When I press the left arrow key from the grade input
    Then the Unified Grader student should be unchanged

  Scenario: Arrow keys outside any editor still navigate between students
    # Guard against over-correcting: with focus on the page (not an editor),
    # left/right arrows must still move between students.
    When I am on the Unified Grader for activity "Essay 1"
    And the marking panel has loaded
    And I note the current Unified Grader student
    And I press the right arrow key from the page body
    Then the Unified Grader student should be changed
