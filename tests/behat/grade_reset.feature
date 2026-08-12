@local @local_unifiedgrader @local_unifiedgrader_critical @javascript
Feature: Dash escape hatches in the overall grade input
  As a teacher who accidentally interacted with the marking panel
  I want a low-friction way to undo the accidental grade
  So that the student doesn't end up flagged as graded with a zero (or whatever stray value)

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
    And the following "activities" exist:
      | activity | name    | course | idnumber | grade |
      | assign   | Essay 1 | TC101  | a1       | 20    |
    And I log in as "teacher1"

  Scenario: Typing "-" clears the grade
    When I am on the Unified Grader for activity "Essay 1"
    And the marking panel has loaded
    And I enter "15" as the overall grade
    # Save fires, grade persists.
    When I reload the page
    And the marking panel has loaded
    Then the field with xpath "//input[@data-action='grade-input']" matches value "15"
    # Now reset.
    When I enter "-" as the overall grade
    Then the field with xpath "//input[@data-action='grade-input']" matches value ""
    # After refresh, server-side state should reflect "no grade" (-1
    # internally → blank in the display).
    When I reload the page
    And the marking panel has loaded
    Then the field with xpath "//input[@data-action='grade-input']" matches value ""

  Scenario: Typing a stray non-numeric value also resets without error
    # Regression for the original "lone - throws PARAM_FLOAT exception"
    # bug — any non-numeric input should be normalised to a clean reset.
    When I am on the Unified Grader for activity "Essay 1"
    And the marking panel has loaded
    And I enter "x" as the overall grade
    Then the field with xpath "//input[@data-action='grade-input']" matches value ""
    And I should not see "Exception"

  @local_unifiedgrader_wip
  Scenario: Typing "--" removes the orphan submission row too
    # Needs a step to seed an assign_submission row with status='new'
    # (e.g. via a custom data generator step or a direct $DB write).
    # The assertion would then verify, after refreshing, that the
    # student shows as "Not submitted" (no orphan row left behind).
    Given student "student1" has an orphan submission marker for activity "Essay 1"
    When I am on the Unified Grader for activity "Essay 1"
    And the marking panel has loaded
    And I enter "--" as the overall grade
    When I reload the page
    Then I should see "Not submitted"
    And student "student1" has no submission row for activity "Essay 1"
