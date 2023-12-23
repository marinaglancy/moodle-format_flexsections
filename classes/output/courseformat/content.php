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
use course_modinfo;
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

    /** @var bool Flexsections format has add section. */
    protected $hasaddsection = true;

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
     * @return \stdClass data context for a mustache template
     */
    public function export_for_template(\renderer_base $output) {
        /** @var \stdClass $data */
        $data = parent::export_for_template($output);

        // If we are on course view page for particular section.
        if ($this->format->get_viewed_section()) {
            // Do not display the "General" section when on a page of another section.
            $data->initialsection = null;

            // Add 'back to parent' control.
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

            // Hide add section link below page content.
            $data->numsections = false;
        }
        $data->accordion = $this->format->get_accordion_setting() ? 1 : '';
        $data->mainsection = $this->format->get_viewed_section();

        return $data;
    }

    /**
     * Export sections array data.
     *
     * TODO: this is an exact copy of the parent function because get_sections_to_display() is private
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @return array data context for a mustache template
     */
    protected function export_sections(\renderer_base $output): array {

        $format = $this->format;
        $course = $format->get_course();
        $modinfo = $this->format->get_modinfo();

        // Generate section list.
        $sections = [];
        $stealthsections = [];
        $numsections = $format->get_last_section_number();
        foreach ($this->get_sections_to_display($modinfo) as $sectionnum => $thissection) {
            // The course/view.php check the section existence but the output can be called
            // from other parts so we need to check it.
            if (!$thissection) {
                throw new \moodle_exception('unknowncoursesection', 'error',
                    course_get_url($course), format_string($course->fullname));
            }

            $section = new $this->sectionclass($format, $thissection);

            if ($sectionnum > $numsections) {
                // Activities inside this section are 'orphaned', this section will be printed as 'stealth' below.
                if (!empty($modinfo->sections[$sectionnum])) {
                    $stealthsections[] = $section->export_for_template($output);
                }
                continue;
            }

            if (!$format->is_section_visible($thissection)) {
                continue;
            }

            $sections[] = $section->export_for_template($output);
        }
        if (!empty($stealthsections)) {
            $sections = array_merge($sections, $stealthsections);
        }
        return $sections;
    }

    /**
     * Return an array of sections to display.
     *
     * This method is used to differentiate between display a specific section
     * or a list of them.
     *
     * @param course_modinfo $modinfo the current course modinfo object
     * @return \section_info[] an array of section_info to display
     */
    private function get_sections_to_display(course_modinfo $modinfo): array {
        $viewedsection = $this->format->get_viewed_section();
        return array_values(array_filter($modinfo->get_section_info_all(), function($s) use ($viewedsection) {
            return (!$s->section) ||
                (!$viewedsection && !$s->parent && $this->format->is_section_visible($s)) ||
                ($viewedsection && $s->section == $viewedsection);
        }));
    }
}
