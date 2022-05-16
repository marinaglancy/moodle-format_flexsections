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

use core_courseformat\base as course_format;
use stdClass;

/**
 * Base class to render a course add section buttons.
 *
 * @package   format_flexsections
 * @copyright 2022 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class addsection extends \core_courseformat\output\local\content\addsection {

    /** @var \format_flexsections the course format class */
    protected $format;

    /** @var \section_info */
    protected $section;

    /**
     * Constructor
     *
     * @param course_format $format
     * @param \section_info $section
     */
    public function __construct(course_format $format, ?\section_info $section = null) {
        parent::__construct($format);
        $this->section = $section;
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @return stdClass data context for a mustache template
     */
    public function export_for_template(\renderer_base $output): stdClass {
        global $PAGE;
        $data = (object)[];
        if ($this->section) {
            // This is the content we add in the section, after the "Add an activity or resource" control.
            if ($this->section->section) {
                if ($PAGE->user_is_editing()) {
                    $url = new \moodle_url(course_get_url($this->format->get_courseid()),
                        ['addchildsection' => $this->section->section]);
                    $data->addsubsection = (object)[
                        'url' => $url->out(false),
                        'title' => get_string('addsubsectionfor', 'format_flexsections',
                            $this->format->get_section_name($this->section)),
                    ];
                }
            }
        } else {
            // This is the content we add in the bottom of the page.
            if (!$this->format->get_viewed_section() && $PAGE->user_is_editing()) {
                $url = new \moodle_url(course_get_url($this->format->get_courseid()), ['addchildsection' => 0]);
                $data->addsections = (object)[
                    'url' => $url->out(false),
                    'title' => get_string('addsections', 'format_flexsections'),
                ];
            }
        }

        return $data;
    }
}
