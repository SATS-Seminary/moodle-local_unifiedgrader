@local @local_unifiedgrader @javascript
Feature: A course with no students still shows its rubric
  In order to check an activity in a blueprint course
  As a course developer
  I need to see the rubric in the grader even when nobody is enrolled to grade

  Scenario: The rubric is shown read-only and the preview says there is nobody to grade
    Given the following "courses" exist:
      | fullname  | shortname | category |
      | Blueprint | BP101     | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teach     | One      | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | BP101  | editingteacher |
    And the following "activities" exist:
      | activity | name    | course | idnumber | grade | advancedgradingmethod_submissions |
      | assign   | Essay 1 | BP101  | a1       | 3     | rubric                            |
    And I log in as "teacher1"
    And I am on "Blueprint" course homepage with editing mode on
    And I go to "Essay 1" advanced grading definition page
    And I set the following fields to these values:
      | Name | One criterion |
    # The core step drops a cell that PHP's empty() rejects, so 0 is written 0.0.
    And I define the following rubric:
      | Argument | Missing | 0.0 | Partial | 1 | Complete | 2 |
    And I press "Save rubric and make it ready"
    When I am on the Unified Grader for activity "Essay 1"
    And the marking panel has loaded
    Then I should see "No students to grade in this activity"
    And I should see "Preview only: there is no student to grade."
    And I should see "Argument"
    And I should see "Partial"
    And the "disabled" attribute of "button[data-criterionid]" "css_element" should be set
    And the "disabled" attribute of "[data-action='grade-input']" "css_element" should be set
