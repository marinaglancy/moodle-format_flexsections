@format @format_flexsections
Feature: Duplicate a section in format_flexsections
  In order to set up my course contents quickly
  As a teacher
  I need to duplicate sections inside the same course

  Background:
    Given the site is running Moodle version 4.2 or higher
    Given the following "courses" exist:
      | fullname | shortname | category | enablecompletion | numsections | format       |
      | Course 1 | C1        | 0        | 1                | 5           | flexsections |
    And the following "activities" exist:
      | activity | name                 | intro                       | course | idnumber | section |
      | assign   | Activity sample A0.1 | Test assignment description | C1     | sample11 | 1       |
      | book     | Activity sample A0.2 | Test book description       | C1     | sample12 | 1       |
      | assign   | Activity sample A1.1 | Test assignment description | C1     | sample21 | 2       |
      | book     | Activity sample A1.2 | Test book description       | C1     | sample22 | 2       |
      | choice   | Activity sample B0   | Test choice description     | C1     | sample31 | 3       |
      | folder   | Activity sample B1   | Test folder description     | C1     | sample41 | 4       |
      | assign   | Activity sample C    | Test assign description     | C1     | sample51 | 5       |
      | assign   | Activity sample 6    | Test assign description     | C1     | sample51 | 6       |
    And the following "users" exist:
      | username | firstname | lastname | email               |
      | teacher  | Tom       | Teacher  | teacher@example.com |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | teacher | C1     | editingteacher |
    And I log in as "teacher"
    And I am on "Course 1" course homepage with editing mode on
    Given I set the field "Edit section name" in the "Topic 1" "section" to "Section A0"
    And I set the field "Edit section name" in the "Topic 2" "section" to "Section A1"
    And I set the field "Edit section name" in the "Topic 3" "section" to "Section B0"
    And I set the field "Edit section name" in the "Topic 4" "section" to "Section B1"
    And I set the field "Edit section name" in the "Topic 5" "section" to "Section C"
    And I open section "2" edit menu
    And I click on "Move" "link" in the "#section-2 .action-menu" "css_element"
    And I click on "As a subsection of 'Section A0'" "link" in the "Move section" "dialogue"
    And I open section "4" edit menu
    And I click on "Move" "link" in the "#section-4 .action-menu" "css_element"
    And I click on "As a subsection of 'Section B0'" "link" in the "Move section" "dialogue"
    And I open section "3" edit menu
    And I click on "Display as a link" "link" in the "#section-3 .action-menu" "css_element"

  @javascript
  Scenario: Duplicate named section without a parent and without subsections
    And I should see "Topic 6"
    And I should not see "Topic 7"
    When I open section "5" edit menu
    And I click on "Duplicate" "link" in the "Section C" "section"
    And "Section C (copy)" "section" should appear after "Section C" "section"
    And "Activity sample C" "activity" should exist in the "Section C" "section"
    And "Activity sample C" "activity" should exist in the "Section C (copy)" "section"
    And I should see "Topic 7"
    And I should not see "Topic 6"

  @javascript
  Scenario: Duplicate unnamed section without a parent and without subsections
    And I should see "Topic 6"
    And I should not see "Topic 7"
    When I open section "6" edit menu
    And I click on "Duplicate" "link" in the "Topic 6" "section"
    And "Topic 7" "section" should appear after "Topic 6" "section"
    And "Activity sample 6" "activity" should exist in the "Topic 6" "section"
    And "Activity sample 6" "activity" should exist in the "Topic 7" "section"

  @javascript
  Scenario: Duplicate section without a parent with subsections
    When I open section "1" edit menu
    And I click on "Duplicate" "link" in the "Section A0" "section"
    And "Section A0 (copy)" "section" should appear after "Section A0" "section"
    # Make section A0 (copy) appear on the separate page so we can check contents.
    And I open section "3" edit menu
    And I click on "Display as a link" "link" in the "#section-3 .action-menu" "css_element"
    And I click on "Section A0 (copy)" "link" in the "region-main" "region"
    And "Activity sample A0.1" "activity" should exist
    And "Activity sample A0.2" "activity" should exist
    And "Activity sample A1.1" "activity" should exist
    And "Activity sample A1.2" "activity" should exist
    And I should see "Section A1"

  @javascript
  Scenario: Duplicate a collapsed section without a parent with subsections
    When I open section "3" edit menu
    And I click on "Duplicate" "link" in the "Section B0" "section"
    And I am on "Course 1" course homepage with editing mode on
    And "Section B0 (copy)" "section" should appear after "Section B0" "section"
    And I click on "Section B0 (copy)" "link" in the "region-main" "region"
    And "Activity sample B0" "activity" should exist
    And "Activity sample B1" "activity" should exist
    And I should see "Section B1"

  @javascript
  Scenario: Duplicate a section with a parent
    When I open section "2" edit menu
    And I choose "Duplicate" in the open action menu
    And I should see "Section A1 (copy)"
    # Check that they have the same parent
    And I open section "1" edit menu
    And I click on "Display as a link" "link" in the "#section-1 .action-menu" "css_element"
    And I click on "Section A0" "link" in the "region-main" "region"
    And "Section A1 (copy)" "text" should appear after "Section A1" "text" in the "region-main" "region"
    And I open section "7" edit menu
    And I click on "Display as a link" "link" in the "#section-7 .action-menu" "css_element"
    And I click on "Section A1 (copy)" "link" in the "region-main" "region"
    And I should see "Section A1 (copy)"
    And "Activity sample A1.1" "activity" should exist
    And "Activity sample A1.2" "activity" should exist
    And I should see "Back to 'Section A0'"

  @javascript
  Scenario: Duplicate a subsection on a page of the parent section on Moodle 4.4 and above
    Given the site is running Moodle version 4.4 or higher
    And I click on "Section B0" "link" in the "region-main" "region"
    And I open section "4" edit menu
    And I choose "Duplicate" in the open action menu
    And "Section B1 (copy)" "text" should appear after "Section B1" "text" in the "region-main" "region"
    And I click on "#action-menu-toggle-4" "css_element"
    And I choose "Display as a link" in the open action menu
    And I click on "Section B1 (copy)" "link" in the "region-main" "region"
    And I should see "Section B1 (copy)"
    And "Activity sample B1" "activity" should exist
    And I should see "Back to 'Section B0'"

  @javascript
  Scenario: Duplicate a subsection on a page of the parent section on Moodle 4.2-4.3
    Given the site is running Moodle version 4.3.99 or lower
    And I click on "Section B0" "link" in the "region-main" "region"
    And I open section "4" edit menu
    And I choose "Duplicate" in the open action menu
    And "Section B1 (copy)" "text" should appear after "Section B1" "text" in the "region-main" "region"
    And I click on "#action-menu-toggle-6" "css_element"
    And I choose "Display as a link" in the open action menu
    And I click on "Section B1 (copy)" "link" in the "region-main" "region"
    And I should see "Section B1 (copy)"
    And "Activity sample B1" "activity" should exist
    And I should see "Back to 'Section B0'"

  @javascript
  Scenario: Duplicate empty sections
    And I click on "(//a[@data-add-sections='Add section'])[6]" "xpath_element"
    And I open section "7" edit menu
    And I choose "Duplicate" in the open action menu
    And "Topic 8" "section" should appear after "Topic 7" "section"
    And I open section "8" edit menu
    And I click on "Move" "link" in the "#section-8 .action-menu" "css_element"
    And I click on "As a subsection of 'Topic 7'" "link" in the "Move section" "dialogue"
    And I open section "7" edit menu
    And I choose "Duplicate" in the open action menu
    And I should see "Topic 9"
    And I should see "Topic 10"
    And I open section "9" edit menu
    And I choose "Display as a link" in the open action menu
    And I click on "Topic 9" "link" in the "region-main" "region"
    And I should see "Topic 10"
    And I should see "Back to course 'Course 1'"
