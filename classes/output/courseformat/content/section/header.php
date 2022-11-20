<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace format_flexsections\output\courseformat\content\section;

/**
 * Contains the section header output class.
 *
 * @package   format_flexsections
 * @copyright 2022 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class header extends \core_courseformat\output\local\content\section\header {

    /**
     * Template name
     *
     * @param \renderer_base $renderer
     * @return string
     */
    public function get_template_name(\renderer_base $renderer): string {
        return 'format_flexsections/local/content/section/header';
    }

    /**
     * Data exporter
     *
     * @param \renderer_base $output
     * @return \stdClass
     */
    public function export_for_template(\renderer_base $output): \stdClass {

        $data = parent::export_for_template($output);
        $data->indenttitle = false;
        $course = $this->format->get_course();

        if ($this->section->collapsed == FORMAT_FLEXSECTIONS_COLLAPSED) {
            // Do not display the collapse/expand caret for sections that are meant to be shown on a separate page.
            $data->headerdisplaymultipage = true;
            if ($this->format->get_viewed_section() != $this->section->section) {
                // If the section is displayed as a link and we are not on this section's page, display it as a link.
                $data->title = $output->section_title($this->section, $course);
                $data->indenttitle = $this->title_needs_indenting();
            }
        }

        $data->hidetitle = false;
        if (!$course->showsection0title && $this->section->section === 0) {
            // Do not display header title for the "General" section.
            $data->hidetitle = true;
        }

        $data->headerdisplaymultipage = !empty($data->headerdisplaymultipage);
        return $data;
    }

    /**
     * Title needs indenting
     *
     * Title displayed as a link needs indenting if some siblings are collpased and some are not.
     *
     * @return bool
     */
    protected function title_needs_indenting(): bool {
        $hassections = [FORMAT_FLEXSECTIONS_COLLAPSED => false, FORMAT_FLEXSECTIONS_EXPANDED => 0];
        foreach ($this->format->get_modinfo()->get_section_info_all() as $section) {
            if ($section->section && $section->parent == $this->section->parent && $this->format->is_section_visible($section)) {
                $hassections[$section->collapsed] = true;
            }
        }
        return $hassections[FORMAT_FLEXSECTIONS_EXPANDED] && $hassections[FORMAT_FLEXSECTIONS_COLLAPSED];
    }
}
