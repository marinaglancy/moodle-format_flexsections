@format @format_flexsections @javascript
Feature: Return to section from activity in format flexsections

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

  Scenario: Return to section from activity in format flexsections (default to no link)
    When I log in as "student"
    And I am on "Course 1" course homepage
    And I follow "Page in first section"
    Then I should not see "Back to '"

  Scenario: Return to section from activity in format flexsections (with a link)
    When I log in as "teacher"
    When I am on "Course 1" course homepage
    And I follow "Settings"
    And I set the following fields to these values:
      | cmbacklink | 1 |
    And I press "Save and display"
    And I log out
    When I log in as "student"
    And I am on "Course 1" course homepage
    And I follow "Page in General section"
    Then I should not see "Back to '"
    And I am on "Course 1" course homepage
    And I follow "Page in first section"
    And I wait "2" seconds
    And "Back to" "text" should appear before "Test page content" "text"
    Then I follow "Back to 't100'"
    And I should see "t100" in the "region-main" "region"
    And I should see "t110" in the "region-main" "region"
    And I should see "t200" in the "region-main" "region"
    And I follow "t300"
    And I follow "Page in t300 section"
    Then I follow "Back to 't300'"
    And I should see "t300" in the "region-main" "region"
    And I should see "t310" in the "region-main" "region"
    And I should see "t320" in the "region-main" "region"
    And I follow "Page in t310 section"
    Then I follow "Back to 't310'"
    And I should see "t300" in the "region-main" "region"
    And I should see "t310" in the "region-main" "region"
    And I should see "t320" in the "region-main" "region"
