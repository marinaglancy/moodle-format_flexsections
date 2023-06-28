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

/**
 * Contains the ajax update course structure.
 *
 * @package   format_flexsections
 * @copyright 2022 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course extends \core_courseformat\output\local\state\course {

    /** @var \format_flexsections the course format class */
    protected $format;

    /**
     * Export this data so it can be used as state object in the course editor.
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @return \stdClass data context for a mustache template
     */
    public function export_for_template(\renderer_base $output): \stdClass {
        $data = parent::export_for_template($output);

        // Build list of first-level sections (used by courseindex).
        $data->sectionlist = array_values(array_filter($data->sectionlist,
        function($sectionid) {
            $section = $this->format->get_modinfo()->get_section_info_by_id($sectionid);
            return $section && !$section->parent;
        }));

        // Build sections hierarchy.
        $allsections = $this->format->get_modinfo()->get_section_info_all();
        $res1 = $res2 = [];
        foreach ($allsections as $s) {
            if ($s->section && $this->format->is_section_visible($s)) {
                $children = [];
                foreach ($allsections as $ss) {
                    if ($ss->parent == $s->section) {
                        $children[] = $ss->id;
                    }
                }
                if ($children) {
                    $res1[] = ['id' => $s->id, 'section' => $s->section, 'children' => $children];
                } else {
                    $res2[] = ['id' => $s->id, 'section' => $s->section, 'children' => $children];
                }
            }
        }
        // Function _fixOrder in lib/amd/src/local/reactive/basecomponent.js removes all existing children of empty lists
        // too early, before saving them in the 'dettachedelements'. To avoid accidentally losing sections during
        // reordering we pass the empty lists in the end.
        $data->hierarchy = array_merge($res1, $res2);
        $data->maxsectiondepth = $this->format->get_max_section_depth();
        if ($this->format->get_accordion_setting()) {
            $data->accordion = 1;
        }

        return $data;
    }
}
