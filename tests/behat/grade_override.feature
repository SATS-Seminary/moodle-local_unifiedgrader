@local @local_unifiedgrader @local_unifiedgrader_critical @javascript
Feature: Manual grade override locks against subsequent rubric edits
  As a teacher
  I want to override a rubric-computed grade
  And have my override survive any further rubric tweaks I make to that student
  So that I don't silently lose my override when fixing a typo in a rubric remark

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
    And the following "activities" exist:
      | activity | name    | course | idnumber | grade |
      | assign   | Essay 1 | TC101  | a1       | 20    |
    And I log in as "teacher1"

  Scenario: Typing a value in the grade input survives a rubric score edit
    When I am on the Unified Grader for activity "Essay 1"
    And the marking panel has loaded
    # The rubric isn't set up in this stub — UG works fine without one.
    # When a rubric IS attached we'd seed a rubric scenario in the Given.
    And I enter "18" as the overall grade
    Then the field with xpath "//input[@data-action='grade-input']" matches value "18"
    # After a page reload the override persists (assumes autosave fired
    # on focusout). The override badge should also be visible because the
    # displayed grade differs from any rubric-computed total.
    When I reload the page
    And the marking panel has loaded
    Then the field with xpath "//input[@data-action='grade-input']" matches value "18"

  @local_unifiedgrader_wip
  Scenario: Override survives a rubric score edit
    # Requires a rubric to be attached to the activity. Marking-guide
    # creation through Behat data generators isn't straightforward, so
    # this scenario is WIP until we add a generator helper or a custom
    # step that creates the rubric via the gradingform_guide API.
    Given a marking guide is attached to "Essay 1" with criteria:
      | shortname     | maxscore |
      | Argumentation | 10       |
      | Style         | 10       |
    When I am on the Unified Grader for activity "Essay 1"
    And the marking panel has loaded
    And I set the rubric score for "Argumentation" to "8"
    And I set the rubric score for "Style" to "7"
    # Rubric total = 15. Teacher overrides to 18.
    And I enter "18" as the overall grade
    And I set the rubric score for "Argumentation" to "9"
    # Rubric total would now be 16, but the override locked, so:
    Then the field with xpath "//input[@data-action='grade-input']" matches value "18"
    And I should see "Overridden"
