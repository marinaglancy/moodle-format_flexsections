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

namespace format_flexsections\output\courseformat\content;

use stdClass;

/**
 * Contains the section controls output class.
 *
 * @package   format_flexsections
 * @copyright 2022 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section extends \core_courseformat\output\local\content\section {

    /** @var \format_flexsections the course format */
    protected $format;

    /** @var int subsection level */
    protected $level = 1;

    /**
     * Template name
     *
     * @param \renderer_base $renderer
     * @return string
     */
    public function get_template_name(\renderer_base $renderer): string {
        return 'format_flexsections/local/content/section';
    }

    /**
     * Data exporter
     *
     * @param \renderer_base $output
     * @return stdClass
     */
    public function export_for_template(\renderer_base $output): stdClass {
        $format = $this->format;
        $course = $format->get_course();

        $data = parent::export_for_template($output);

        // For sections that are displayed as a link do not print list of cms or controls.
        $showaslink = $this->section->collapsed == FORMAT_FLEXSECTIONS_COLLAPSED
            && $this->format->get_viewed_section() != $this->section->section;

        $data->showaslink = $showaslink;
        if ($showaslink) {
            $data->cmlist = [];
            $data->cmcontrols = '';
        }

        // Add subsections.
        if (!$showaslink) {
            $data->subsections = $this->section->section ? $this->get_subsections($output) : [];
            $data->level = $this->level;
        }

        if ((!$course->showsection0title && $this->section->section === 0) ||
                ($this->section->section !== 0 && $this->section->section === $this->format->get_viewed_section())) {
            // Never collapse content of top section in single section view or
            // when showing title of the top section is not shown.
            $data->contentcollapsed = false;
        }

        if ($this->section->section === 0 || $this->section->section === $this->format->get_viewed_section()) {
            // Show collapse/expand all menu at top section header.
            $data->collapsemenu = true;
        } else {
            $data->collapsemenu = false;
        }

        $data->addsectionafter = false;
        $data->insertsubsection = false;
        if ($this->format->should_display_add_sub_section_link($this->section->parent)
                && ($this->section->section != $this->format->get_viewed_section() || $this->section->section === 0)) {
            // Display 'Add section' button after to insert after this section.
            $data->addsectionafter = $this->export_add_section($output);
        }
        if ($this->section->section && $this->format->should_display_add_sub_section_link($this->section->section)) {
            // Display 'Add section' button to insert a section as a first direct child of this section.
            $data->insertsubsection = $this->export_add_section($output, $this->section->id);
        }

        return $data;
    }

    /**
     * Exporter for the 'Add section' link
     *
     * @param \renderer_base $output
     * @param int $parentid
     * @return stdClass
     */
    protected function export_add_section(\renderer_base $output, int $parentid = 0): stdClass {
        $addsectionclass = $this->format->get_output_classname('content\\addsection');
        /** @var \core_courseformat\output\local\content\addsection $addsection */
        $addsection = new $addsectionclass($this->format);
        $data = $addsection->export_for_template($output);
        $data->insertparentid = $parentid;
        return $data;
    }

    /**
     * Subsections (recursive)
     *
     * @param \renderer_base $output
     * @return array
     */
    protected function get_subsections(\renderer_base $output): array {
        $modinfo = $this->format->get_modinfo();
        $data = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->parent == $this->section->section) {
                if ($this->format->is_section_visible($section)) {
                    $instance = new static($this->format, $section);
                    $instance->level++;
                    $d = (array)($instance->export_for_template($output)) +
                        $this->default_section_properties();
                    $data[] = (object)$d;
                }
            }
        }
        return $data;
    }

    /**
     * Since we display sections nested the values from the parent can propagate in templates
     *
     * @return array
     */
    protected function default_section_properties(): array {
        return [
            'collapsemenu' => false, 'summary' => [],
            'insertafter' => false, 'numsections' => false,
            'availability' => [], 'restrictionlock' => false, 'hasavailability' => false,
            'isstealth' => false, 'ishidden' => false, 'notavailable' => false, 'hiddenfromstudents' => false,
            'controlmenu' => [], 'cmcontrols' => '',
            'singleheader' => [], 'header' => [],
            'cmsummary' => [], 'onlysummary' => false, 'cmlist' => [],
        ];
    }
}
