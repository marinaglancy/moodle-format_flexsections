@format @format_flexsections @javascript
Feature: Sections can be edited and deleted in flexsections format
  In order to rearrange my course contents
  As a teacher
  I need to edit and Delete sections

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email            |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format | coursedisplay | numsections |
      | Course 1 | C1        | flexsections | 0             | 5           |
    And the following "activities" exist:
      | activity   | name                   | intro                         | course | idnumber    | section |
      | assign     | Test assignment name   | Test assignment description   | C1     | assign1     | 0       |
      | book       | Test book name         | Test book description         | C1     | book1       | 1       |
      | chat       | Test chat name         | Test chat description         | C1     | chat1       | 4       |
      | choice     | Test choice name       | Test choice description       | C1     | choice1     | 5       |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on

  Scenario: View the default name of the second section in flexsections format
    When I edit the section "2"
    Then the field "Custom" matches value "0"
    And the field "New value for Section name" matches value "Topic 2"

  Scenario: Edit section summary in flexsections format
    When I edit the section "2" and I fill the form with:
      | Summary | Welcome to section 2 |
    Then I should see "Welcome to section 2" in the "Topic 2" "section"

  Scenario: Edit section default name in flexsections format
    When I edit the section "2" and I fill the form with:
      | Custom | 1                      |
      | New value for Section name      | This is the second topic |
    Then I should see "This is the second topic" in the "This is the second topic" "section"
    And I should not see "Topic 2" in the "region-main" "region"

  Scenario: Inline edit section name in flexsections format
    When I set the field "Edit section name" in the "Topic 1" "section" to "Midterm evaluation"
    Then I should not see "Topic 1" in the "region-main" "region"
    And "New name for section" "field" should not exist
    And I should see "Midterm evaluation" in the "Midterm evaluation" "section"
    And I am on "Course 1" course homepage
    And I should not see "Topic 1" in the "region-main" "region"
    And I should see "Midterm evaluation" in the "Midterm evaluation" "section"

  Scenario: Deleting the last section in flexsections format
    When I delete section "5"
    Then I should see "Are you sure you want to delete this section? All activities and subsections will also be deleted"
    And I click on "Yes" "button" in the "Confirm" "dialogue"
    And I should not see "Topic 5" in the "region-main" "region"
    And I should see "Topic 4" in the "region-main" "region"

  Scenario: Deleting the middle section in flexsections format
    When I delete section "4"
    And I click on "Yes" "button" in the "Confirm" "dialogue"
    Then I should not see "Topic 5" in the "region-main" "region"
    And I should not see "Test chat name"
    And I should see "Test choice name" in the "Topic 4" "section"
    And I should see "Topic 4" in the "region-main" "region"

  Scenario: Adding sections at the end of a flexsections format
    When I click on "Add section" "link" in the "Topic 5" "section"
    Then I should see "Topic 6" in the "Topic 6" "section"
    And I should see "Test choice name" in the "Topic 5" "section"
