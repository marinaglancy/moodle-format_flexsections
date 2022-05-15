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

namespace format_flexsections\output\courseformat;

use core_courseformat\external\get_state;
use stdClass;

/**
 * Render a course content.
 *
 * @package   format_flexsections
 * @copyright 2022 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content extends \core_courseformat\output\local\content {

    /** @var \format_flexsections the course format class */
    protected $format;

    /**
     * @var bool Flexsections format has add section after each topic.
     *
     * The responsible for the buttons is core_courseformat\output\local\content\section.
     */
    protected $hasaddsection = false;

    /**
     * Template name for this exporter
     *
     * @param \renderer_base $renderer
     * @return string
     */
    public function get_template_name(\renderer_base $renderer): string {
        return 'format_flexsections/local/content';
    }

    /**
     * Export this data so it can be used as the context for a mustache template (core/inplace_editable).
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @return stdClass data context for a mustache template
     */
    public function export_for_template(\renderer_base $output) {
        $data = parent::export_for_template($output);

        if ($this->format->get_viewed_section()) {
            // Do not display the "General" section when on a page of another section.
            $data->initialsection = null;
        }

        // If we are on course view page for particular section, return 'back to parent' control.
        if ($this->format->get_viewed_section()) {
            $section = $this->format->get_section($this->format->get_viewed_section());
            if ($section->parent) {
                $sr = $this->format->find_collapsed_parent($section->parent);
                $url = $this->format->get_view_url($section->section, array('sr' => $sr));
                $data->backtosection = [
                    'url' => $url->out(false),
                    'sectionname' => $this->format->get_section_name($section->parent)
                ];
            } else {
                $sr = 0;
                $url = $this->format->get_view_url($section->section, array('sr' => $sr));
                $context = \context_course::instance($this->format->get_courseid());
                $data->backtocourse = [
                    'url' => $url->out(false),
                    'coursename' => format_string($this->format->get_course()->fullname, true, ['context' => $context]),
                ];
            }
        }

        return $data;
    }
}
