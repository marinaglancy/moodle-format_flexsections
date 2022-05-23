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

namespace format_flexsections\courseformat;

use context_course;
use stdClass;

/**
 * class stateactions
 *
 * @package   format_flexsections
 * @copyright 2022 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stateactions extends  \core_courseformat\stateactions {
    /**
     * Moving a section
     *
     * @param \core_courseformat\stateupdates $updates
     * @param stdClass $course
     * @param array $ids
     * @param int|null $targetsectionid
     * @param int|null $targetcmid
     * @return void
     */
    public function section_move(\core_courseformat\stateupdates $updates, stdClass $course, array $ids,
                                 ?int $targetsectionid = null, ?int $targetcmid = null): void {
        $this->validate_sections($course, $ids, __FUNCTION__);

        $coursecontext = context_course::instance($course->id);
        require_capability('moodle/course:movesections', $coursecontext);

        /** @var \format_flexsections $format */
        $format = course_get_format($course);
        $modinfo = $format->get_modinfo();

        // Target section.
        if ($targetsectionid > 0) {
            $this->validate_sections($course, [$targetsectionid], __FUNCTION__);
            $before = $modinfo->get_section_info_by_id($targetsectionid, MUST_EXIST);
            $parent = $before->parent;
        } else if ($targetsectionid < 0) {
            $this->validate_sections($course, [-$targetsectionid], __FUNCTION__);
            $before = null;
            $parent = $modinfo->get_section_info_by_id(-$targetsectionid, MUST_EXIST);
        } else {
            $before = null;
            $parent = 0;
        }

        // Move sections.
        $sections = $this->get_section_info($modinfo, $ids);
        foreach ($sections as $section) {
            if ($format->can_move_section_to($section, $parent, $before)) {
                $format->move_section($section, $parent, $before);
            }
        }

        // All course sections can be renamed because of the resort.
        $allsections = $modinfo->get_section_info_all();
        foreach ($allsections as $section) {
            $updates->add_section_put($section->id);
        }
        // The section order is at a course level.
        $updates->add_course_put();
    }
}
