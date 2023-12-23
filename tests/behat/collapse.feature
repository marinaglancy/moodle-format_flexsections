@format @format_flexsections @javascript
Feature: Collapsing and expanding sections in flexsections format

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

  Scenario: Collapse and expand all sections in flexsections format
    When I log in as "student"
    And I am on "Course 1" course homepage
    Then I should see "t110" in the "region-main" "region"
    And I should see "t111" in the "region-main" "region"
    And I should see "t210" in the "region-main" "region"
    And I click on "Collapse all" "link" in the "region-main" "region"
    And I should see "t100" in the "region-main" "region"
    And I should see "t200" in the "region-main" "region"
    And I should not see "t110" in the "region-main" "region"
    And I should not see "t111" in the "region-main" "region"
    And I should not see "t210" in the "region-main" "region"
    And I click on "Expand all" "link" in the "region-main" "region"
    Then I should see "t110" in the "region-main" "region"
    And I should see "t111" in the "region-main" "region"
    And I should see "t210" in the "region-main" "region"

  Scenario: Collapse and expand all sections in subsection in flexsections format
    When I log in as "student"
    And I am on "Course 1" course homepage
    And I click on "t300" "link" in the "region-main" "region"
    Then I should see "t311" in the "region-main" "region"
    And I click on "Collapse all" "link" in the "region-main" "region"
    And I should see "t300" in the "region-main" "region"
    And I should see "t310" in the "region-main" "region"
    And I should not see "t311" in the "region-main" "region"
    And I click on "Expand all" "link" in the "region-main" "region"
    And I should see "t310" in the "region-main" "region"
    And I should see "t311" in the "region-main" "region"

  Scenario: Expanding and collapsing sections with accordion in flexsections format
    When I log in as "teacher"
    When I am on "Course 1" course homepage
    And I follow "Settings"
    And I set the following fields to these values:
      | accordion | 1 |
    And I press "Save and display"
    And I log out
    When I log in as "student"
    And I am on "Course 1" course homepage
    # First section and first subsection are expanded, general section is always expanded
    And I should see "Page in General section" in the "region-main" "region"
    And I should see "Page in first section" in the "region-main" "region"
    And I should see "t111" in the "region-main" "region"
    And I should not see "t121" in the "region-main" "region"
    And I should not see "t210" in the "region-main" "region"
    # Expand the second section, the first section will be collapsed, the general section will stay expanded
    And I click on ".course-section-header[data-number=6] a[data-toggle=collapse]" "css_element"
    And I should see "Page in General section" in the "region-main" "region"
    And I should not see "Page in first section" in the "region-main" "region"
    And I should not see "t111" in the "region-main" "region"
    And I should see "t210" in the "region-main" "region"
    And I should see "t220" in the "region-main" "region"
    And I should not see "t211" in the "region-main" "region"
    # Expand the first subsection of the second section
    And I click on ".course-section-header[data-number=7] a[data-toggle=collapse]" "css_element"
    And I should see "Page in General section" in the "region-main" "region"
    And I should not see "Page in first section" in the "region-main" "region"
    And I should see "t211" in the "region-main" "region"
    # Collapse all will collapse all sections except for general
    And I click on "Collapse all" "link" in the "region-main" "region"
    And I should see "Page in General section" in the "region-main" "region"
    And I should not see "Page in first section" in the "region-main" "region"
    And I should not see "t110" in the "region-main" "region"
    And I should not see "t210" in the "region-main" "region"

  Scenario: Expanding and collapsing sections with accordion and expandable General section in flexsections format
    When I log in as "admin"
    And I am on "Course 1" course homepage
    And I navigate to "Settings" in current page administration
    And I expand all fieldsets
    And I set the following fields to these values:
      | accordion              | 1 |
      | Show top section title | 1 |
    And I press "Save and display"
    And I log out
    When I log in as "student"
    And I am on "Course 1" course homepage
    # Only general section is expanded
    And I should not see "t111" in the "region-main" "region"
    And I should not see "t121" in the "region-main" "region"
    And I should not see "t210" in the "region-main" "region"
    And I should see "Page in General section" in the "region-main" "region"
    And I should not see "Page in first section" in the "region-main" "region"
    # Expand first section, the general section will be collapsed
    And I click on ".course-section-header[data-number=1] a[data-toggle=collapse]" "css_element"
    And I should not see "Page in General section" in the "region-main" "region"
    And I should see "Page in first section" in the "region-main" "region"
    And I should see "t110" in the "region-main" "region"
    And I should not see "t111" in the "region-main" "region"
    And I should not see "t121" in the "region-main" "region"
    And I should not see "t210" in the "region-main" "region"
    # Expand General section, all others will be collapsed
    And I click on ".course-section-header[data-number=0] a[data-toggle=collapse]" "css_element"
    And I should not see "t110" in the "region-main" "region"
    And I should not see "t210" in the "region-main" "region"
    And I should see "Page in General section" in the "region-main" "region"
    And I should not see "Page in first section" in the "region-main" "region"
    # Collapse all will collapse all sections including general
    And I click on "Collapse all" "link" in the "region-main" "region"
    And I should not see "Page in General section" in the "region-main" "region"
    And I should not see "Page in first section" in the "region-main" "region"
    And I should not see "t110" in the "region-main" "region"
    And I should not see "t210" in the "region-main" "region"

  Scenario: Expanding and collapsing sections on the subsection page in flexible sections format
    When I log in as "student"
    And I am on "Course 1" course homepage
    And I click on "t300" "link" in the "region-main" "region"
    And I should not see "Page in General section" in the "region-main" "region"
    And I should see "Page in t300 section" in the "region-main" "region"
    And I should see "Page in t310 section" in the "region-main" "region"
    And I should see "t311" in the "region-main" "region"
    And I should see "t321" in the "region-main" "region"
    And I click on "Collapse all" "link" in the "region-main" "region"
    And I should not see "t311" in the "region-main" "region"
    And I should see "t310" in the "region-main" "region"
    And I should not see "t321" in the "region-main" "region"
    And I should see "t320" in the "region-main" "region"
    And I should see "Page in t300 section" in the "region-main" "region"
    And I should not see "Page in t310 section" in the "region-main" "region"
    And I click on "Expand all" "link" in the "region-main" "region"
    And I should see "t311" in the "region-main" "region"
    And I should see "t321" in the "region-main" "region"
    And I should see "Page in t300 section" in the "region-main" "region"
    And I should see "Page in t310 section" in the "region-main" "region"

  Scenario: Expanding and collapsing sections on the subsection page in accordion mode in flexible sections format
    When I log in as "teacher"
    When I am on "Course 1" course homepage
    And I follow "Settings"
    And I set the following fields to these values:
      | accordion | 1 |
    And I press "Save and display"
    And I log out
    When I log in as "student"
    And I am on "Course 1" course homepage
    And I click on "t300" "link" in the "region-main" "region"
    # Only first subsection is expanded (t310, t311)
    And I should not see "Page in General section" in the "region-main" "region"
    And I should see "Page in t300 section" in the "region-main" "region"
    And I should see "Page in t310 section" in the "region-main" "region"
    And I should see "t311" in the "region-main" "region"
    And I should not see "t321" in the "region-main" "region"
    # Expand section t320, section t310 will be collapsed
    And I click on ".course-section-header[data-number=14] a[data-toggle=collapse]" "css_element"
    And I should see "Page in t300 section" in the "region-main" "region"
    And I should not see "Page in t310 section" in the "region-main" "region"
    And I should not see "t311" in the "region-main" "region"
    And I should see "t321" in the "region-main" "region"
    # Collapse all sections
    And I click on "Collapse all" "link" in the "region-main" "region"
    And I should not see "t311" in the "region-main" "region"
    And I should see "t310" in the "region-main" "region"
    And I should not see "t321" in the "region-main" "region"
    And I should see "t320" in the "region-main" "region"
    And I should see "Page in t300 section" in the "region-main" "region"
    And I should not see "Page in t310 section" in the "region-main" "region"
