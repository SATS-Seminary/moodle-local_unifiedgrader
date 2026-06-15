@local @local_unifiedgrader @local_unifiedgrader_critical @javascript
Feature: Re-editing saved overall feedback collapses back to the saved card
  As a teacher revisiting an already-graded submission
  I want re-editing the overall feedback to confirm the save by collapsing to the card
  So that I can tell my edit saved instead of being left staring at an open editor

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
      | activity | name    | course | idnumber | grade | assignfeedback_comments_enabled |
      | assign   | Essay 1 | TC101  | a1       | 20    | 1                               |
    And "student1" has been graded with feedback "Seeded feedback marker" on "Essay 1"
    And I log in as "teacher1"

  Scenario: Re-editing existing feedback collapses to the saved card on save
    When I am on the Unified Grader for activity "Essay 1"
    And the marking panel has loaded
    # Existing feedback starts as the read-only card.
    Then the overall feedback is shown as a saved card
    And I should see "Seeded feedback marker"
    # Open it for editing.
    When I click on "[data-action=edit-feedback]" "css_element"
    Then the overall feedback is open for editing
    # Save. The regression was that it stayed in edit mode here, giving the
    # teacher no confirmation that the (successful) save had happened.
    When I click on "[data-action=save-grade]" "css_element"
    Then the overall feedback is shown as a saved card
    And I should see "Seeded feedback marker"
