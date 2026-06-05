@local_unifiedgrader @local_unifiedgrader_critical @javascript
Feature: Annotation tools stay responsive after a PDF zoom
  In order to keep marking after I zoom the PDF preview
  As a teacher with annotation rights
  I need the toolbar to keep dispatching tool changes to the active page's annotation layer

  Background:
    Given the following "courses" exist:
      | fullname        | shortname     | category |
      | Annotation Test | annot_test    | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                 |
      | teacher1 | Tess      | Acher    | teacher1@example.com  |
      | student1 | Stu       | Dent     | student1@example.com  |
    And the following "course enrolments" exist:
      | user     | course     | role           |
      | teacher1 | annot_test | editingteacher |
      | student1 | annot_test | student        |
    And the following "activities" exist:
      | activity | name            | intro    | course     | idnumber | submissiondrafts |
      | assign   | Annotation PDF  | Try me   | annot_test | annot1   | 0                |
    And the "local_unifiedgrader/enable_assign" admin setting is "1"

  # This scenario is the regression marker for the v2.5.1 → v2.5.2 sequence:
  # zoom destroys all page slots, the toolbar's _layer reference was cleared
  # for safety, and the next tool click silently no-op'd because the
  # _initAnnotationLayer re-link branch only ran on first render. Refresh
  # "fixed" it because the constructor rebuilt the toolbar from scratch.
  # The data-current-tool attribute is stamped by AnnotationLayer only when
  # the layer actually accepts the tool — distinct from the toolbar button
  # .active class which moves regardless.
  #
  # Tagged @local_unifiedgrader_wip because it requires a PDF submission
  # fixture to be in place via a generator helper. The intended check is
  # documented in the scenario steps below; the helper will replace the
  # "I have submitted a PDF as" stub once added.
  @local_unifiedgrader_wip
  Scenario: Tool clicks still dispatch after the teacher zooms in
    Given I have submitted a PDF as "student1" to "Annotation PDF"
    And I log in as "teacher1"
    And I am on the Unified Grader for activity "Annotation PDF"
    And the marking panel has loaded
    When I click on "[data-tool=\"pen\"]" "css_element"
    Then the active annotation layer should report tool "pen"
    When I click on "[data-action=\"zoom-in\"]" "css_element"
    And I wait "1" seconds
    And I click on "[data-tool=\"highlight\"]" "css_element"
    Then the active annotation layer should report tool "highlight"
    # Same again on the new text-highlight tool — it goes through the same
    # propagation path and is most likely to expose a regression in this
    # area because it also wires the PDF.js text layer.
    When I click on "[data-tool=\"texthighlight\"]" "css_element"
    Then the active annotation layer should report tool "texthighlight"
