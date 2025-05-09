# Changelog
All notable changes to this project will be documented in this file.

## [4.1.4] - 2025-05-10
### Added
- Bulk edit of activity modules (except for "Move" aciton) #103 #67
### Fixed
- (4.4+) Item 'Permalink' in the course section edit menu displays the copy-to-clipboard popup
  and allows to copy the link to the section #95
- Section 'Permalink' contain section ids rather than section numbers (persistent after
  moving sections around)
- (4.4+) Removed the 'View' item from the course section edit menu added by core, it conflicts the
  functionality of the format_flexsections plugin "Display as a link".
- (4.5+) Do not display a link to add subsection (mod_subsection) as it is confusing with the
  flexible sections subsections.
- (4.5+) If the subsections (mod_subsection) already exist in the course (i.e. they were added
  before the course format was changed to flexible sections), display them correctly and
  allow to delete them.
- Use lock when deleting sections to avoid course corruption #82

## [4.1.3] - 2024-12-08
### Fixed
- Fixed exception: Call to undefined function format_flexsections_add_back_link_to_cm #99, #101

## [4.1.2] - 2024-10-06
### Fixed
- When a section used to be displayed on a course page and is now displayed as a link,
  the old student preferences about collapsed state affect the visibility of
  the section summary #68
- Coding error when trying to collapse a large number of sections #88
  (workaround for the core bug MDL-78073)
### Added
- Support for Moodle 4.5

## [4.1.1] - 2024-10-02
### Fixed
- When section has availability restriction and the restriction is displayed, all subsections
  and activities in the subsections should not be available.
  This also fixes the exception that made course index disappear for students #89
- Coding style fixes for upcoming 4.5 release

## [4.1.0] - 2024-05-22
### Added
- Support for Moodle 4.4, #83
- Ability to duplicate sections, #69
### Fixed
- Fixed fatal PHP error on Moodle 4.3 introduced by changes in MDL-81610

## [4.0.6] - 2023-12-23
### Added
- Adjusted automatic tests for Moodle 4.3
### Fixed
- #71 - 'Back to' link does not display ampersand (&) correcty

## [4.0.5] - 2023-08-08
### Fixed
- When scrolling the page, the course index now correctly highlights subsections.
  Thanks to [luttje](https://github.com/luttje) for the patch submitted in
  https://github.com/marinaglancy/moodle-format_flexsections/issues/47 .
- Fixed unexpected scrolling when expanding a section in accordion mode.
### Added
- Course level settings for 'accordion' effect, 'Back to...' link and how to
  show the course index. (In addition to site-level settings added in v4.0.4)
- In accordion mode clicking on the section in the course index will expand
  it in the course contents.

## [4.0.4] - 2023-07-02
### Added
- Setting to show header for the General section and make it collapsible
- Link 'Add section' between sections (only for the top-level sections on the
  current page)
- Setting how to show the course index (sections and activities, only sections,
  do not display)
- Setting to enable 'accordion' effect - when one section is expanded, all others
  are collapsed
- Setting to display 'Back to...' link inside the activities allowing to return
  back to the course section
- Setting 'maxsections' now only affects the number of top-level sections. Number
  of subsections is unlimited (there is also a setting about the maximum depth).
### Fixed
- Fixed a bug when drag&drop of activities was not possible if the target
  section is empty

Release contributor: **Hogeschool Inholland, the Netherlands**.

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
