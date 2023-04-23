@format @format_flexsections @javascript
Feature: Moving sections in a course in flexsections format

  Scenario: Move sections in flexsections format
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format       | coursedisplay | numsections |
      | Course 1 | C1        | flexsections | 0             | 4           |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    When I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on

    And I set the field "Edit section name" in the "Topic 1" "section" to "Section A"
    And I set the field "Edit section name" in the "Topic 2" "section" to "Section B0"
    And I set the field "Edit section name" in the "Topic 3" "section" to "Section C"
    And I set the field "Edit section name" in the "Topic 4" "section" to "Section B1"

    And I open section "4" edit menu
    And I click on "Move" "link" in the "#section-4 .action-menu" "css_element"
    And I should see "Move Section B1 to this location:" in the "Move section" "dialogue"
    And I click on "As a subsection of 'Section B0'" "link" in the "Move section" "dialogue"

    Then "Section A" "text" should appear before "Section B0" "text" in the ".course-content" "css_element"
    And "Section B0" "text" should appear before "Section B1" "text" in the ".course-content" "css_element"
    And "Section B1" "text" should appear before "Section C" "text" in the ".course-content" "css_element"

    When I open section "1" edit menu
    And I click on "Move" "link" in the "#section-1 .action-menu" "css_element"
    And I should see "Move Section A to this location:" in the "Move section" "dialogue"
    And I click on "Before 'Section C'" "link" in the "Move section" "dialogue"

    Then "Section B0" "text" should appear before "Section B1" "text" in the ".course-content" "css_element"
    And "Section B1" "text" should appear before "Section A" "text" in the ".course-content" "css_element"
    And "Section A" "text" should appear before "Section C" "text" in the ".course-content" "css_element"
