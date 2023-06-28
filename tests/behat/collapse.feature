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
      | user     | course | role     |
      | student  | C1     | student  |
      | teacher  | C1     | teacher  |
    And the following "format_flexsections > sections" exist:
      | name | course | parent | collapsed |
      | t100 | C1     |        | 0         |
      | t200 | C1     |        | 0         |
      | t300 | C1     |        | 1         |
      | t110 | C1     | t100   | 0         |
      | t120 | C1     | t100   | 0         |
      | t210 | C1     | t200   | 0         |
      | t111 | C1     | t110   | 0         |
      | t220 | C1     | t200   | 0         |
      | t310 | C1     | t300   | 0         |
      | t320 | C1     | t300   | 0         |
      | t311 | C1     | t310   | 0         |
      | t312 | C1     | t310   | 0         |
      | t321 | C1     | t320   | 0         |

  Scenario: Collapse and expand all sections in flexsections format
    When I log in as "student"
    And I am on "Course 1" course homepage
    Then I should see "t110" in the "region-main" "region"
    And I should see "t111" in the "region-main" "region"
    And I should see "t210" in the "region-main" "region"
    And I follow "Collapse all"
    And I should see "t100" in the "region-main" "region"
    And I should see "t200" in the "region-main" "region"
    And I should not see "t110" in the "region-main" "region"
    And I should not see "t111" in the "region-main" "region"
    And I should not see "t210" in the "region-main" "region"
    And I follow "Expand all"
    Then I should see "t110" in the "region-main" "region"
    And I should see "t111" in the "region-main" "region"
    And I should see "t210" in the "region-main" "region"

  Scenario: Collapse and expand all sections in subsection in flexsections format
    When I log in as "student"
    And I am on "Course 1" course homepage
    And I click on "t300" "link" in the "region-main" "region"
    Then I should see "t311" in the "region-main" "region"
    And I follow "Collapse all"
    And I should see "t300" in the "region-main" "region"
    And I should see "t310" in the "region-main" "region"
    And I should not see "t311" in the "region-main" "region"
    And I follow "Expand all"
    And I should see "t310" in the "region-main" "region"
    And I should see "t311" in the "region-main" "region"
