@local @local_unifiedgrader @local_unifiedgrader_critical @javascript
Feature: The Dates & extensions dialogue
  As a teacher
  I want every date setting shown with the class default beside the student's own value
  So that I can grant an extension or an override in one place, with Save always in view

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
      | enable_assign | 1 | local_unifiedgrader |
    And the following "activities" exist:
      | activity | name    | course | idnumber | duedate     | cutoffdate  | assignsubmission_onlinetext_enabled |
      | assign   | Essay 1 | TC101  | a1       | ##+2 days## | ##+3 days## | 1                                   |

  Scenario: Grant an extension with a preset, save it, and see it again
    Given I log in as "teacher1"
    And I am on the Unified Grader for activity "Essay 1"
    And the marking panel has loaded
    When I click on "[data-region=student-status-wrapper] button.badge" "css_element"
    And I click on "Dates & extensions" "button"
    Then I should see "Class default" in the ".modal-dialog" "css_element"
    And I should see "Nothing submitted yet" in the ".modal-dialog" "css_element"
    And I should see "Same as class" in the "Due date" "table_row"
    And the ".modal-footer [data-action=save]" "css_element" should be disabled
    When I click on "+1 day" "button"
    Then I should see "Extension" in the "Due date" "table_row"
    And I should see "1 change for Stu Dent" in the ".modal-footer" "css_element"
    When I click on "Save" "button" in the ".modal-footer" "css_element"
    And I wait until the page is ready
    And I click on "[data-region=student-status-wrapper] button.badge" "css_element"
    And I click on "Dates & extensions" "button"
    Then I should see "Extension" in the "Due date" "table_row"
    And "+1 day" "button" should exist in the ".modal-dialog" "css_element"
    And the ".modal-footer [data-action=save]" "css_element" should be disabled

  Scenario: Override a setting in its row and reset it
    Given I log in as "teacher1"
    And I am on the Unified Grader for activity "Essay 1"
    And the marking panel has loaded
    When I click on "[data-region=student-status-wrapper] button.badge" "css_element"
    And I click on "Dates & extensions" "button"
    And I click on "Change" "button" in the "Cut-off date" "table_row"
    Then I should see "Override" in the "Cut-off date" "table_row"
    And I should see "1 change for Stu Dent" in the ".modal-footer" "css_element"
    When I click on "Reset" "button" in the "Cut-off date" "table_row"
    Then I should see "Same as class" in the "Cut-off date" "table_row"
    And I should see "No changes" in the ".modal-footer" "css_element"
