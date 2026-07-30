@local @local_unifiedgrader @local_unifiedgrader_critical @javascript
Feature: The marking panel's attempt selector reflects real attempt data
  As a teacher
  I want to see and switch between all of a student's submission attempts
  So that I can find and grade every attempt, including one created by a
    manual reopen that exceeds the activity's configured attempt cap

  Background:
    Given the following "courses" exist:
      | fullname    | shortname | category |
      | Test Course | TC101     | 0        |
    And the following "users" exist:
      | username   | firstname | lastname | email                |
      | teacher1   | Teach     | One      | teacher1@example.com |
      | student1   | Stu       | Dent     | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | TC101  | editingteacher |
      | student1 | TC101  | student        |
    And I log in as "teacher1"

  Scenario: The attempt selector stays hidden for a genuine single-attempt submission
    Given the following "activities" exist:
      | activity | name    | course | idnumber | grade | maxattempts | attemptreopenmethod |
      | assign   | Essay 1 | TC101  | a1       | 20    | 1           | none                 |
    And "student1" has 1 graded submission attempts on "Essay 1"
    When I am on the Unified Grader for activity "Essay 1"
    And the marking panel has loaded
    Then "[data-region=\"attempt-selector\"]" "css_element" should not be visible

  Scenario: The attempt selector appears for a real second attempt even though maxattempts is 1
    # Regression test for a bug where the marking panel hid the attempt
    # selector whenever the activity's maxattempts was 1, regardless of how
    # many attempts actually existed. maxattempts only limits a student's
    # own automatic reopens — a teacher can still manually reopen a submission
    # (attemptreopenmethod: manual, set below) and produce a genuine second
    # attempt despite the cap. When that happened, the teacher had no way to
    # see or switch back to the earlier, already-graded attempt: the selector
    # trusted the configured cap instead of the real attempt count returned
    # by the server. The student-facing feedback view never had this bug —
    # it only ever checked the real attempt count — which is what made the
    # teacher-side gap easy to miss.
    Given the following "activities" exist:
      | activity | name    | course | idnumber | grade | maxattempts | attemptreopenmethod |
      | assign   | Essay 2 | TC101  | a2       | 20    | 1           | manual               |
    And "student1" has 2 graded submission attempts on "Essay 2"
    When I am on the Unified Grader for activity "Essay 2"
    And the marking panel has loaded
    Then "[data-region=\"attempt-selector\"]" "css_element" should be visible
    And I should see "Attempt 1 (graded)" in the "[data-action=\"attempt-select\"]" "css_element"
    And I should see "Attempt 2 (graded)" in the "[data-action=\"attempt-select\"]" "css_element"
