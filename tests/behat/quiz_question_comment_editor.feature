@local @local_unifiedgrader @local_unifiedgrader_critical @javascript @editor_tiny
Feature: Quiz question comments use the rich-text editor
  As a teacher marking a quiz question by hand
  I want the comment to be the same editor Moodle's manual grading page gives me
  So that I can format it and add images or recorded audio

  # The comment was a plain textarea until v2.13.0. The editor is created by
  # the marking panel for each student, on the question's own draft area, so
  # this checks it appears, and that a formatted comment saves and reloads.

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
      | enable_quiz | 1 | local_unifiedgrader |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | TC101     | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype | name     | questiontext        |
      | Test questions   | essay | Essay Q1 | Discuss the passage |
    And the following "activities" exist:
      | activity | name   | course | idnumber |
      | quiz     | Quiz 1 | TC101  | q1       |
    And quiz "Quiz 1" contains the following questions:
      | question | page | maxmark |
      | Essay Q1 | 1    | 10      |
    And user "student1" has attempted "Quiz 1" with responses:
      | slot | response               |
      | 1    | My reading of the text |

  Scenario: A formatted comment on an essay question saves and reloads
    Given I log in as "teacher1"
    When I am on the Unified Grader for activity "Quiz 1"
    And the marking panel has loaded
    Then "[data-region=rubric-body] .tox-tinymce" "css_element" should exist
    When I set the field "Remark" to "<p><strong>Well argued</strong> reading.</p>"
    And I set the rubric score for "Q1: Essay Q1" to "8"
    And I click on "[data-action=save-grade]" "css_element"
    And I wait until the page is ready
    And I reload the page
    And the marking panel has loaded
    Then the field "Remark" matches value "<p><strong>Well argued</strong> reading.</p>"
