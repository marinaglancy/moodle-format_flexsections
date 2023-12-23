@format @format_flexsections @javascript
Feature: Adding sections in flexsections format

  Background:
    Given the following "courses" exist:
      | fullname | shortname | format       | numsections |
      | Course 1 | C1        | flexsections | 0           |
    And the following "users" exist:
      | username | firstname | lastname | email               |
      | student  | Sam       | Student  | student@example.com |
      | teacher  | Tom       | Teacher  | teacher@example.com |
    And the following "course enrolments" exist:
      | user    | course | role           |
      | student | C1     | student        |
      | teacher | C1     | editingteacher |
    And the following "format_flexsections > sections" exist:
      | name | course | parent | collapsed |
      | t100 | C1     |        | 0         |
      | t200 | C1     |        | 0         |
      | t300 | C1     |        | 1         |
      | t110 | C1     | t100   | 0         |
      | t120 | C1     | t100   | 0         |
      | t121 | C1     | t120   | 0         |
      | t210 | C1     | t200   | 0         |
      | t211 | C1     | t210   | 0         |
      | t111 | C1     | t110   | 0         |
      | t220 | C1     | t200   | 0         |
      | t310 | C1     | t300   | 0         |
      | t320 | C1     | t300   | 0         |
      | t311 | C1     | t310   | 0         |
      | t312 | C1     | t310   | 0         |
      | t321 | C1     | t320   | 0         |
    And the following "activities" exist:
      | activity | course | idnumber | name                    | section |
      | page     | C1     | p1       | Page in General section | 0       |
      | page     | C1     | p2       | Page in first section   | 1       |
      | page     | C1     | p3       | Page in t300 section    | 10      |
      | page     | C1     | p4       | Page in t310 section    | 11      |

  Scenario: Adding section after the general section on the main course page
    When I log in as "teacher"
    And I am on the "C1" course page
    And I turn editing mode on
    And I click on "Add section" "link" in the "li#section-0" "css_element"
    Then "Topic 1" "section" should appear after "Page in General section" "text" in the "region-main" "region"
    And "t100" "section" should appear after "Topic 1" "section" in the "region-main" "region"
    And I click on "Collapse all" "link" in the "region-main" "region"
    And I click on "Add section" "link" in the "Topic 1" "section"
    And "Topic 2" "section" should appear after "Topic 1" "section"
    And "t100" "section" should appear after "Topic 2" "section"

  Scenario: Adding section between the sections on the main course page
    When I log in as "teacher"
    And I am on the "C1" course page
    And I turn editing mode on
    And I click on "Add section" "link" in the "t100" "section"
    Then "Topic 6" "section" should appear after "t100" "section"
    And "Topic 6" "section" should appear after "t110" "section"
    And "t200" "section" should appear after "Topic 6" "section"
    And I click on "Collapse all" "link" in the "region-main" "region"
    And I click on "Add section" "link" in the "Topic 6" "section"
    And "Topic 7" "section" should appear after "Topic 6" "section"
    And "t200" "section" should appear after "Topic 7" "section"

  Scenario: Adding section as the first subsection
    When I log in as "teacher"
    And I am on the "C1" course page
    And I turn editing mode on
    And I click on "t300" "link" in the "region-main" "region"
    And I click on "Add section" "link" in the "li#section-10" "css_element"
    Then "Topic 11" "text" should appear after "Page in t300 section" "text" in the "region-main" "region"
    And "t310" "text" should appear after "Topic 11" "text" in the "region-main" "region"
    And I click on "Collapse all" "link" in the "region-main" "region"
    And I click on "Add section" "link" in the "li#section-11" "css_element"
    And "Topic 12" "text" should appear after "Topic 11" "text"
    And "t310" "text" should appear after "Topic 12" "text"

  Scenario: Adding section between the sections on the subsection page
    When I log in as "teacher"
    And I am on the "C1" course page
    And I turn editing mode on
    And I click on "t300" "link" in the "region-main" "region"
    And I click on "Add section" "link" in the "li#section-11" "css_element"
    Then "Topic 14" "text" should appear after "t312" "text"
    And "t320" "section" should appear after "Topic 14" "text"
    And I click on "Collapse all" "link" in the "region-main" "region"
    And I click on "Add section" "link" in the "li#section-14" "css_element"
    And "Topic 15" "text" should appear after "Topic 14" "text"
    And "t320" "text" should appear after "Topic 15" "text"

  Scenario: Respecting maxsections when adding sections
    Given the following config values are set as admin:
      | maxsections | 4 | moodlecourse |
    When I log in as "teacher"
    And I am on the "C1" course page
    And I turn editing mode on
    And I click on "Add section" "link" in the "li#section-10" "css_element"
    And "Topic 16" "text" should appear after "t300" "text" in the "region-main" "region"
    # Adding another section would show a warning but we can't test it in behat yet
    # We can add subsections though
    And I open section "1" edit menu
    And I click on "Add subsection" "link" in the "li#section-1" "css_element"
    And "Topic 6" "text" should appear after "t121" "text" in the "region-main" "region"
    And "Topic 6" "text" should appear before "t200" "text" in the "region-main" "region"
    And I am on the "C1" course page
    And I click on "t300" "link" in the "region-main" "region"
    # Any amount of subsections can be added here
    And I follow "Add section"
    And "Topic 12" "text" should appear after "Page in t300 section" "text" in the "region-main" "region"
    And "t310" "text" should appear after "Topic 12" "text" in the "region-main" "region"
    And I click on "Add section" "link" in the "li#section-13" "css_element"
    And "Topic 16" "text" should appear after "t312" "text" in the "region-main" "region"
    And "t320" "text" should appear after "Topic 16" "text" in the "region-main" "region"
