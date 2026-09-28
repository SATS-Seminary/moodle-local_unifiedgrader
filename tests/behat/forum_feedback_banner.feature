@local @local_unifiedgrader @javascript
Feature: Graded students see the forum feedback banner inside a discussion
  In order to notice that my forum participation has been graded
  As a student
  I need the feedback banner on the discussion I am reading, not only on the list of discussions

  # A course format can send a student straight into a forum's only discussion
  # (format_simple does this for Q&A forums), so the list of discussions, where
  # the banner used to be the only place it appeared, may never be seen.

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
      | enable_forum | 1 | local_unifiedgrader |
    And the following "activities" exist:
      | activity | name        | intro      | course | idnumber | grade_forum |
      | forum    | Class Forum | Discuss it | TC101  | f1       | 20          |
    And the following "mod_forum > discussions" exist:
      | forum | user     | name       | message                    |
      | f1    | student1 | My opinion | Here is what I make of it. |

  Scenario: A graded student sees the banner in the discussion
    Given "student1" has been graded with feedback "Thoughtful contribution" on "Class Forum"
    When I log in as "student1"
    And I am on the "Class Forum" "forum activity" page
    And I should see "Your teacher has graded your forum participation."
    And I follow "My opinion"
    Then I should see "Here is what I make of it."
    And I should see "Your teacher has graded your forum participation."
    And "View Forum Feedback" "link" should exist in the "#ug-feedback-banner" "css_element"

  Scenario: An ungraded student sees no banner in the discussion
    When I log in as "student1"
    And I am on the "Class Forum" "forum activity" page
    And I follow "My opinion"
    Then I should see "Here is what I make of it."
    And I should not see "Your teacher has graded your forum participation."
