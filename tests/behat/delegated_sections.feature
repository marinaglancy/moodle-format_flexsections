@format @format_flexsections @javascript
Feature: Testing delegated sections in format_flexsections

  Background:
    Given the site is running Moodle version 4.5 or higher
    And I enable "subsection" "mod" plugin
    And the following "course" exists:
      | fullname      | Course 1 |
      | shortname     | C1       |
      | category      | 0        |
      | numsections   | 3       |
      | initsections  | 1        |
    And the following "activities" exist:
      | activity | name              | course | idnumber | section |
      | assign   | First assignment  | C1     | assign1  | 2       |
    And the following "activity" exists:
      | activity | subsection  |
      | name     | Subsection1 |
      | course   | C1          |
      | idnumber | subsection1 |
      | section  | 1           |
    And the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  Scenario: Viewing delegated sections
    When I am on the "Course 1" course page logged in as teacher1
    And I should see "Subsection1"
    And I navigate to "Settings" in current page administration
    And I expand all fieldsets
    And I set the field "Format" to "Flexible sections format"
    And I press "Save and display"
    And I should see "Subsection1"
    And I turn editing mode on
    And I click on "#action-menu-toggle-3" "css_element"
    And I choose "Delete" in the open action menu
    And I click on "Delete" "button" in the "Delete subsection?" "dialogue"
    And I should not see "Subsection1"
    And I reload the page
    And I should not see "Subsection1"
