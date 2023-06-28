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

namespace format_flexsections\output\courseformat\state;
use format_flexsections\constants;

/**
 * Contains the ajax update section structure.
 *
 * @package   format_flexsections
 * @copyright 2022 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section extends \core_courseformat\output\local\state\section {

    /** @var \format_flexsections the course format class */
    protected $format;

    /**
     * Export this data so it can be used as state object in the course editor.
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @return \stdClass data context for a mustache template
     */
    public function export_for_template(\renderer_base $output): \stdClass {
        /** @var \stdClass $data */
        $data = parent::export_for_template($output);
        $data->parent = $this->section->parent;
        $data->parentid = $this->section->parent ? $this->format->get_modinfo()->get_section_info($this->section->parent)->id : 0;

        // For sections that are displayed as a link do not print list of cms or controls.
        $showaslink = $this->section->collapsed == FORMAT_FLEXSECTIONS_COLLAPSED
            && $this->format->get_viewed_section() != $this->section->section;
        $data->showaslink = $showaslink;
        $data->children = [];
        if ($this->section->section) {
            foreach ($this->format->get_modinfo()->get_section_info_all() as $s) {
                if ($s->parent == $this->section->section && $this->format->is_section_visible($s)) {
                    $data->children[] = (array)((new static($this->format, $s))->export_for_template($output)) +
                        $this->default_section_properties();
                }
            }
        }
        $data->haschildren = !empty($data->children);
        $data->singlesection = (int)($this->section->collapsed && $this->section->section == $this->format->get_viewed_section());
        $courseindex = $this->format->get_course_index_display();
        $data->hidecmsinindex = ($courseindex == constants::COURSEINDEX_SECTIONS);

        return $data;
    }

    /**
     * Since we display sections nested the values from the parent can propagate in templates
     *
     * @return array
     */
    protected function default_section_properties(): array {
        return [
            'isstealth' => false, 'ishidden' => false, 'notavailable' => false, 'hiddenfromstudents' => false,
            'cmlist' => [], 'hascms' => false, 'cms' => [],
        ];
    }
}
