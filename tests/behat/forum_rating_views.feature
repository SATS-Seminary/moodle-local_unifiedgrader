@local @local_unifiedgrader @local_unifiedgrader_critical @javascript
Feature: Grading a rating-based forum
  In order to mark a forum where the grade comes from rating individual posts
  As a teacher
  I need per-post rating controls and a way to read each post in its discussion context

  Background:
    Given the following "courses" exist:
      | fullname   | shortname | category |
      | Rated Talk | ratedtalk | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Tess      | Acher    | teacher1@example.com |
      | student1 | Stu       | Dent     | student1@example.com |
      | student2 | Pat       | Peer     | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course    | role           |
      | teacher1 | ratedtalk | editingteacher |
      | student1 | ratedtalk | student        |
      | student2 | ratedtalk | student        |
    # assessed=1 is RATING_AGGREGATE_AVERAGE; grade_forum=0 keeps whole-forum
    # grading off, which is what selects rating mode.
    And the following "activities" exist:
      | activity | name        | intro      | course    | idnumber | assessed | scale | grade_forum |
      | forum    | Rated Forum | Discuss it | ratedtalk | ratedf1  | 1        | 5     | 0           |
    And the following "mod_forum > discussions" exist:
      | forum   | user     | name          | message                 |
      | ratedf1 | teacher1 | Opening topic | What do you make of it? |
    And the following "mod_forum > posts" exist:
      | parentsubject | user     | subject     | message                              |
      | Opening topic | student1 | Stu replies | My reading of the passage in detail. |
      | Opening topic | student2 | Pat replies | A different reading entirely.        |
    And the following config values are set as admin:
      | enable_forum | 1 | local_unifiedgrader |
    And I log in as "teacher1"

  Scenario: The grade box is replaced by per-post rating controls
    When I am on the Unified Grader for activity "Rated Forum"
    And the post ratings list has loaded
    Then I should see "Post ratings"
    And I should see "Average of ratings"
    # The grade is derived from the ratings, so there is nothing to type into.
    And "[data-action=\"grade-input\"]" "css_element" should not be visible
    # And no penalty control, because the gradebook value is core's to compute.
    And "[data-action=\"toggle-penalties\"]" "css_element" should not be visible

  Scenario: Rating a post from the marking panel updates the running total
    When I am on the Unified Grader for activity "Rated Forum"
    And the post ratings list has loaded
    And I set the field with xpath "//select[@data-action='post-rating-input']" to "4"
    And I wait until "//*[@data-region='post-ratings-total'][contains(., '4')]" "xpath_element" exists
    Then I should see "1 of 1 posts rated"

  Scenario: Rating a post from the post card itself updates the marking panel
    When I am on the Unified Grader for activity "Rated Forum"
    And the post ratings list has loaded
    # The in-context view is the default for a rated forum, and the student's
    # own post card carries its own rating control.
    And I set the field with xpath "//select[@data-action='inline-post-rating']" to "3"
    And I wait until "//*[@data-region='post-ratings-total'][contains(., '3')]" "xpath_element" exists
    Then I should see "1 of 1 posts rated"

  Scenario: The teacher can switch between the three post views
    When I am on the Unified Grader for activity "Rated Forum"
    And the post ratings list has loaded
    # A rated forum opens on the in-context view, since the post is the unit
    # being graded.
    Then "[data-action=\"forum-view-mode\"][data-mode=\"paged\"].active" "css_element" should exist
    And I should see "Post 1 of 1"
    # Context: the prompt the student was answering is on screen with the post.
    And I should see "What do you make of it?"
    And I should see "Stu replies"
    # A classmate's reply to the same prompt is shown as a sibling — this is how
    # a marker tells whether the student added anything.
    And I should see "Other replies to the same post"
    And I should see "Pat replies"

    When I click on "[data-action=\"forum-view-mode\"][data-mode=\"thread\"]" "css_element"
    Then "[data-action=\"forum-view-mode\"][data-mode=\"thread\"].active" "css_element" should exist
    And I should see "This student"

    When I click on "[data-action=\"forum-view-mode\"][data-mode=\"flat\"]" "css_element"
    Then "[data-action=\"forum-view-mode\"][data-mode=\"flat\"].active" "css_element" should exist
    And "[data-region=\"forum-context-view\"]" "css_element" should not be visible

  Scenario: The chosen post view is remembered for next time
    When I am on the Unified Grader for activity "Rated Forum"
    And the post ratings list has loaded
    And I click on "[data-action=\"forum-view-mode\"][data-mode=\"thread\"]" "css_element"
    And I wait until "[data-action=\"forum-view-mode\"][data-mode=\"thread\"].active" "css_element" exists
    And I reload the page
    And the post ratings list has loaded
    Then "[data-action=\"forum-view-mode\"][data-mode=\"thread\"].active" "css_element" should exist

  Scenario: Clicking a rating row highlights it, keeping both panels in step
    When I am on the Unified Grader for activity "Rated Forum"
    And the post ratings list has loaded
    And I click on "[data-region=\"post-rating-row\"] [data-action=\"focus-post\"]" "css_element"
    Then "[data-region=\"post-rating-row\"].local-unifiedgrader-rating-row-current" "css_element" should exist

  Scenario: A whole-forum forum is unaffected and still opens flat
    Given the following "activities" exist:
      | activity | name        | intro    | course    | idnumber | grade_forum |
      | forum    | Whole Forum | Grade me | ratedtalk | wholef1  | 100         |
    When I am on the Unified Grader for activity "Whole Forum"
    And the marking panel has loaded
    Then I should not see "Post ratings"
    And "[data-action=\"grade-input\"]" "css_element" should be visible
    And "[data-action=\"forum-view-mode\"][data-mode=\"flat\"].active" "css_element" should exist
