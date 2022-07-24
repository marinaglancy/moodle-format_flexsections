# Changelog
All notable changes to this project will be documented in this file.

## [4.0.4] - Unreleased
### Added
- Add option to section settings that allows defining secondary title which is displayed above the main title and remain visible when section is collapsed.

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
