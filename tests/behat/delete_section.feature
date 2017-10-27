@format @format_flexsections
Feature: Deleting sections in flexsections format
  In order to organise the content in the course
  As a teacher
  I need to be able to delete sections

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Terry | Teacher | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format       | numsections |
      | Course 1 | C1        | flexsections | 0           |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I should not see "Add section"
    And I turn editing mode on
    And I follow "Add section"
    And I should see "Topic 1"
    And I add a "Assignment" to section "1" and I fill the form with:
      | Assignment name | First module |
      | Description | Test |
    And I click on "Add subsection" "link" in the "li#section-1" "css_element"
    And I add a "Forum" to section "2" and I fill the form with:
      | Forum name | Second module |
      | Description | Test |
    And I click on "Add subsection" "link" in the "li#section-1 > .content > .mdl-right" "css_element"
    And I add a "Forum" to section "3" and I fill the form with:
      | Forum name | Third module |
      | Description | Test |
    And I follow "Add section"
    And I follow "Add section"
    And I add a "Forum" to section "5" and I fill the form with:
      | Forum name | Fourth module |
      | Description | Test |
    And I should see "Topic 5"

  @javascript
  Scenario: Deleting the empty section in flexsections format
    When I click on "Delete section" "link" in the "li#section-4" "css_element"
    And I click on "Yes" "button" in the "Confirm" "dialogue"
    Then I should not see "Topic 5"
    And I should see "Topic 4"
    And I should see "Fourth module"

  @javascript
  Scenario: Deleting the last section in flexsections format
    When I click on "Delete section" "link" in the "li#section-5" "css_element"
    And I click on "Yes" "button" in the "Confirm" "dialogue"
    Then I should not see "Topic 5"
    And I should see "Topic 4"
    And I should not see "Fourth module"

  @javascript
  Scenario: Deleting the subsection in flexsections format with JS on
    When I click on "Delete section" "link" in the "li#section-2" "css_element"
    And I click on "Yes" "button" in the "Confirm" "dialogue"
    Then I should not see "Topic 5"
    And I should see "Topic 4"
    And I should not see "Second module"
    And I should see "Fourth module"

  Scenario: Deleting the subsection in flexsections format with JS off
    When I click on "Delete section" "link" in the "li#section-2" "css_element"
    Then I should see "Are you sure"
    And I click on "Yes" "button"
    And I should not see "Topic 5"
    And I should see "Topic 4"
    And I should not see "Second module"
    And I should see "Fourth module"

  @javascript
  Scenario: Deleting the section with subsections in flexsections format
    When I click on "Delete section" "link" in the "li#section-1" "css_element"
    And I click on "Yes" "button" in the "Confirm" "dialogue"
    Then I should not see "Topic 5"
    And I should not see "Topic 4"
    And I should not see "Topic 3"
    And I should see "Topic 2"
    And I should not see "First module"
    And I should not see "Second module"
    And I should not see "Third module"
    And I should see "Fourth module"
