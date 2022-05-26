@format @format_flexsections @javascript
Feature: Using course in flexsections format
  In order to use flexsections format
  As a teacher and student
  I need to test all basic functionality

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Sam | Student | student1@example.com |
      | student2 | Mary | Student | student2@example.com |
      | teacher1 | Terry | Teacher | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format       | numsections |
      | Course 1 | C1        | flexsections | 0           |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
      | teacher1 | C1     | editingteacher |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I should not see "Add section"
    And I should not see "Add subsection"
    And I turn editing mode on
    And I follow "Add section"
    And I should see "Topic 1"
    And I add a "Assignment" to section "1" and I fill the form with:
      | Assignment name | First module |
      | Description     | Test         |
    And I click on "Add subsection" "link" in the "li#section-1" "css_element"
    And I add a "Forum" to section "2" and I fill the form with:
      | Forum name  | Second module |
      | Description | Test          |

  Scenario: Add sections and activities to flexsections format
    Given I should see "First module"
    And I should see "Second module"
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "First module"
    And I should see "Second module"

  Scenario: Hiding section in flexsections format
    When I open section "2" edit menu
    And I click on "Hide topic" "link" in the "li#section-2" "css_element"
    # TODO check the page
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "Topic 1"
    And I should not see "Topic 2"
    And I should see "First module"
    And I should not see "Second module"

  Scenario: Hiding section in flexsections format hides the subsections and activities
    When I open section "1" edit menu
    And I click on "Hide topic" "link" in the "li#section-1" "css_element"
    # TODO check the page
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should not see "Topic 1"
    And I should not see "First module"
    And I should not see "Topic 2"
    And I should not see "Second module"

  Scenario: Collapsing section in flexsections format
    Given the following config values are set as admin:
      | unaddableblocks | | theme_boost|
    Given I add the "Navigation" block if not present
    When I open section "2" edit menu
    When I click on "Display as a link" "link" in the "li#section-2" "css_element"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "Topic 1"
    And I should see "First module"
    And I should not see "Second module"
    And navigation node "Topic 1" should be expandable
    And I click on "Topic 2" "link" in the "li#section-1" "css_element"
    And I should not see "First module" in the "region-main" "region"
    And I should see "Topic 2" in the "region-main" "region"
    And I should see "Second module" in the "region-main" "region"
    And I should see "Topic 2" in the "Navigation" "block"
    And I should see "Second module" in the "Navigation" "block"

  Scenario: Collapsing section with subsections in flexsections format
    And I change window size to "large"
    Given the following config values are set as admin:
      | unaddableblocks | | theme_boost|
    Given I add the "Navigation" block if not present
    When I open section "1" edit menu
    And I click on "Display as a link" "link" in the "li#section-1" "css_element"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    Then I should see "Topic 1" in the "region-main" "region"
    And I should see "Topic 1" in the "Navigation" "block"
    And I should not see "First module"
    And I should not see "Topic 2"
    And I should not see "Second module"
    And I click on "Topic 1" "link" in the "region-main" "region"
    And I should see "Topic 1" in the "region-main" "region"
    And I should see "First module" in the "region-main" "region"
    And I should see "First module" in the "Navigation" "block"
    And I should see "Topic 2" in the "region-main" "region"
    And I should see "Second module" in the "region-main" "region"
    And I should not see "Second module" in the "Navigation" "block"
    And I expand "Topic 2" node
    And I should see "Second module" in the "Navigation" "block"

  Scenario: Merging subsection in flexsections format
    Given the following config values are set as admin:
      | unaddableblocks | | theme_boost|
    Given I add the "Navigation" block if not present
    When I open section "2" edit menu
    And I click on "Merge with parent" "link" in the "li#section-2" "css_element"
    And I click on "Yes" "button" in the "Confirm" "dialogue"
    Then I should see "Topic 1" in the "region-main" "region"
    And "li#section-2" "css_element" should not exist
    And I should not see "Topic 2"
    And I should see "First module" in the "region-main" "region"
    And I should see "Second module" in the "region-main" "region"
    And I expand "Topic 1" node
    And I should see "First module" in the "Navigation" "block"
    And I should see "Second module" in the "Navigation" "block"
