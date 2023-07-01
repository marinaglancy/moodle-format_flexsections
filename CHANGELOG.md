# Changelog
All notable changes to this project will be documented in this file.

## UNRELEASED
### Added
- Setting to show header for the General section and make it collapsible
- Setting how to show the course index (sections and activities, only sections,
  do not display)
- Setting to enable 'accordion' effect - when one section is expanded, all others
  are collapsed
- Setting to display 'Back to...' link inside the activities allowing to return
  back to the course section
### Fixed
- Fixed a bug when drag&drop of activities was not possible if the target
  section is empty

## [4.0.3] - 2023-05-06
### Added
- Allow to indent activities on the course page
- Added automated tests on Moodle 4.2
- Subsection depth limiting course format setting. Setting maximum number of
  subsection levels will restrict ability of user to create sections at levels
  deeper than configured. The setting does not affect existing course subsections
  layout.
### Fixed
- Fixed a bug causing section not to be moved to the correct location in some cases.
  See https://github.com/marinaglancy/moodle-format_flexsections/issues/37
- Trigger event when section is deleted

## [4.0.2] - 2023-04-17
### Changed
- Course index always shows all sections and activities regardless of the current page. More details
  https://github.com/marinaglancy/moodle-format_flexsections/issues/39
- Added automated tests on Moodle 4.1

## [4.0.1] - 2022-06-19
### Added
- Course format "Flexible sections" now has UI in-line with the Moodle LMS 4.0 course formats. It supports AJAX editing of activities and sections and course index.
