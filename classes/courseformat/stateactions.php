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
use core_courseformat\stateupdates;
use stdClass;
use core_component;
use moodle_exception;

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
     * @param stateupdates $updates
     * @param stdClass $course
     * @param array $ids
     * @param int|null $targetsectionid if positive number, move AFTER this section under the same parent
     *     if negative number, move TO the parent with id abs($targetsectionid) as the first child
     *     if 0, move to parent=0 as the first child
     *     (it's quite hacky but unfortunately we can only use one argument here so have to be creative)
     * @param int|null $targetcmid
     * @return void
     */
    public function section_move(stateupdates $updates, stdClass $course, array $ids,
                                 ?int $targetsectionid = null, ?int $targetcmid = null): void {
        $this->validate_sections($course, $ids, __FUNCTION__);

        $coursecontext = context_course::instance($course->id);
        require_capability('moodle/course:movesections', $coursecontext);

        /** @var \format_flexsections $format */
        $format = course_get_format($course);
        $modinfo = $format->get_modinfo();

        // Parent section and position.
        if ($targetsectionid > 0) {
            $this->validate_sections($course, [$targetsectionid], __FUNCTION__);
            $targetsection = $modinfo->get_section_info_by_id($targetsectionid, MUST_EXIST);
            $before = $this->find_next_section($modinfo, $targetsection);
            $parent = $targetsection->parent;
        } else if ($targetsectionid < 0) {
            $this->validate_sections($course, [-$targetsectionid], __FUNCTION__);
            $targetsection = $modinfo->get_section_info_by_id(-$targetsectionid, MUST_EXIST);
            $before = $this->get_first_child($modinfo, $targetsection->section);
            $parent = $modinfo->get_section_info_by_id(-$targetsectionid, MUST_EXIST);
        } else {
            $before = $this->get_first_child($modinfo, 0);
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

    /**
     * Find next section within the same parent.
     *
     * @param \course_modinfo $modinfo
     * @param \section_info $thissection
     * @return \section_info|null
     */
    protected function find_next_section(\course_modinfo $modinfo, \section_info $thissection): ?\section_info {
        // Build array of same parent sections starting from next to $thissection.
        $sections = array_filter($modinfo->get_section_info_all(), function($s) use ($thissection) {
            return ($s->parent == $thissection->parent) && ($s->section > $thissection->section);
        });

        // Empty array means $thissection is last section, otherwise the first section is the one we need.
        return empty($sections) ? null : array_shift($sections);
    }

    /**
     * Get first subsection
     *
     * @param \course_modinfo $modinfo
     * @param int $parent
     * @return \section_info|null
     */
    protected function get_first_child(\course_modinfo $modinfo, int $parent): ?\section_info {
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->parent == $parent && $section->section) {
                return $section;
            }
        }
        return null;
    }

    /**
     * Merging a section with its parent
     *
     * @param \format_flexsections\courseformat\stateupdates $updates the affected course elements track
     * @param stdClass $course the course object
     * @param int[] $ids not used
     * @param int $targetsectionid section id to merge up
     * @param int $targetcmid not used
     */
    public function section_mergeup(
        stateupdates $updates,
        stdClass $course,
        array $ids = [],
        ?int $targetsectionid = null,
        ?int $targetcmid = null
    ): void {
        if (!$targetsectionid) {
            throw new moodle_exception("Action section_mergeup requires targetsectionid");
        }

        $coursecontext = context_course::instance($course->id);
        require_capability('moodle/course:update', $coursecontext);

        $modinfo = get_fast_modinfo($course);
        /** @var \format_flexsections $format */
        $format = course_get_format($course->id);

        $this->validate_sections($course, [$targetsectionid], __FUNCTION__);
        $targetsection = $modinfo->get_section_info_by_id($targetsectionid, MUST_EXIST);
        if (!$targetsection->parent) {
            throw new moodle_exception("Action section_mergeup can't merge top level parentless sections");
        }

        $format->mergeup_section($targetsection);
        $updates->add_section_remove($targetsectionid);

        // Merging a section affects the full course structure.
        $this->course_state($updates, $course);
    }

    /**
     * Check maxsectionslimit
     *
     * Only compare the number of sections on the top level.
     *
     * @param stdClass $course
     * @throws moodle_exception
     */
    protected function check_maxsections(stdClass $course) {
        /** @var \format_flexsections $format */
        $format = course_get_format($course->id);
        $cnt = 0;
        foreach ($format->get_sections() as $section) {
            if ($section->section && !$section->parent) {
                $cnt++;
            }
        }
        $maxsections = $format->get_max_toplevel_sections();

        if ($cnt >= $maxsections) {
            throw new moodle_exception('maxsectionslimit', 'moodle', '', $maxsections);
        }
    }

    /**
     * Check maxsectiondepth
     *
     * @param stdClass $course
     * @param \section_info $targetsection
     * @throws moodle_exception
     */
    protected function check_maxdepth(stdClass $course, \section_info $targetsection) {
        // Validate if we do not exceed depth.
        /** @var \format_flexsections $format */
        $format = course_get_format($course->id);
        $targetsectiondepth = $format->get_section_depth($targetsection);
        if ($targetsectiondepth >= $format->get_max_section_depth()) {
            throw new moodle_exception('errorsectiondepthexceeded', 'format_flexsections');
        }
    }

    /**
     * Find section number of the next sibling
     *
     * @param stdClass $course
     * @param int $parent section number of the parent section (0 for top level)
     * @param int $sectionnum section number of the section, 0 for finding the first child
     * @return int|null section number of the next sibling or null if it is the last child
     */
    protected function find_next_sibling(stdClass $course, int $parent, int $sectionnum): ?int {
        foreach (get_fast_modinfo($course)->get_section_info_all() as $section) {
            if ($section->parent == $parent && $section->section > $sectionnum) {
                return $section->section;
            }
        }
        return null;
    }

    /**
     * Create a course section.
     *
     * This method follows the same logic as changenumsections.php.
     *
     * @param stateupdates $updates the affected course elements track
     * @param stdClass $course the course object
     * @param int[] $ids not used
     * @param int $targetsectionid optional target section id (if not passed section will be appended),
     *     the section will be inserted to the same parent AFTER the target section
     * @param int $targetcmid not used
     */
    public function section_add(
        stateupdates $updates,
        stdClass $course,
        array $ids = [],
        ?int $targetsectionid = null,
        ?int $targetcmid = null
    ): void {

        $coursecontext = context_course::instance($course->id);
        require_capability('moodle/course:update', $coursecontext);

        /** @var \format_flexsections $format */
        $format = course_get_format($course->id);

        // Calculate position to insert new section (parent and number of the next section).
        $parentsection = 0;
        $insertposition = null;
        if ($targetsectionid) {
            $targetsection = get_fast_modinfo($course)->get_section_info_by_id($targetsectionid, MUST_EXIST);
            $parentsection = $targetsection->parent ? $format->get_section($targetsection->parent) : 0;
            $insertposition = $this->find_next_sibling($course, $targetsection->parent, $targetsection->section);
        }

        if (!($parentsection && $parentsection->section)) {
            $this->check_maxsections($course);
        }

        $format->create_new_section($parentsection, $insertposition);

        // Adding a section affects the full course structure.
        $this->course_state($updates, $course);
    }

    /**
     * Adding a subsection as the last child of the parent
     *
     * @param \core_courseformat\stateupdates $updates
     * @param stdClass $course
     * @param array $ids not used
     * @param int|null $targetsectionid parent section id
     * @param int|null $targetcmid not used
     * @return void
     */
    public function section_add_subsection(\core_courseformat\stateupdates $updates, stdClass $course, array $ids,
                            ?int $targetsectionid = null, ?int $targetcmid = null): void {
        require_capability('moodle/course:update', context_course::instance($course->id));
        /** @var \format_flexsections $format */
        $format = course_get_format($course);
        $modinfo = $format->get_modinfo();
        $targetsection = $modinfo->get_section_info_by_id($targetsectionid, MUST_EXIST);
        $this->check_maxdepth($course, $targetsection);

        $format->create_new_section($targetsection);

        // Adding subsection affects the full course structure.
        $this->course_state($updates, $course);
    }

    /**
     * Adding a subsection as the first child of the parent
     *
     * @param stateupdates $updates
     * @param stdClass $course
     * @param array $ids not used
     * @param int|null $targetsectionid parent section id
     * @param int|null $targetcmid not used
     * @return void
     */
    public function section_insert_subsection(stateupdates $updates, stdClass $course, array $ids,
                            ?int $targetsectionid = null, ?int $targetcmid = null): void {
        require_capability('moodle/course:update', context_course::instance($course->id));
        /** @var \format_flexsections $format */
        $format = course_get_format($course);
        $modinfo = $format->get_modinfo();
        $targetsection = $modinfo->get_section_info_by_id($targetsectionid, MUST_EXIST);
        $this->check_maxdepth($course, $targetsection);

        $insertposition = $this->find_next_sibling($course, $targetsection->section, 0);
        $format->create_new_section($targetsection, $insertposition);

        // Adding subsection affects the full course structure.
        $this->course_state($updates, $course);
    }

    /**
     * Delete course sections.
     *
     * @param stateupdates $updates the affected course elements track
     * @param stdClass $course the course object
     * @param int[] $ids section ids
     * @param int $targetsectionid not used
     * @param int $targetcmid not used
     */
    public function section_delete(
        stateupdates $updates,
        stdClass $course,
        array $ids = [],
        ?int $targetsectionid = null,
        ?int $targetcmid = null
    ): void {

        if (empty($ids)) {
            // Nothing to delete.
            return;
        }

        $coursecontext = context_course::instance($course->id);
        require_capability('moodle/course:update', $coursecontext);
        require_capability('moodle/course:movesections', $coursecontext);

        $modinfo = get_fast_modinfo($course);
        /** @var \format_flexsections $format */
        $format = course_get_format($course);
        $sectionid = array_shift($ids);

        $section = $modinfo->get_section_info_by_id($sectionid, MUST_EXIST);
        [$sectionstodelete, $modulestodelete] = $format->delete_section_with_children($section);

        foreach ($modulestodelete as $cmid) {
            $updates->add_cm_remove($cmid);
        }

        foreach ($sectionstodelete as $sid) {
            $updates->add_section_remove($sid);
        }

        // Removing a section affects the full course structure.
        $this->course_state($updates, $course);
    }

    /**
     * Switch collapsed state (display as link/ display on the same page)
     *
     * @param \core_courseformat\stateupdates $updates
     * @param stdClass $course
     * @param array $ids section id
     * @param int|null $targetsectionid not used
     * @param int|null $targetcmid not used
     * @return void
     */
    public function section_switch_collapsed(\core_courseformat\stateupdates $updates, stdClass $course, array $ids,
                                           ?int $targetsectionid = null, ?int $targetcmid = null): void {
        $this->validate_sections($course, $ids, __FUNCTION__);
        require_capability('moodle/course:update', context_course::instance($course->id));
        /** @var \format_flexsections $format */
        $format = course_get_format($course);
        $modinfo = $format->get_modinfo();
        foreach ($this->get_section_info($modinfo, $ids) as $section) {
            if ($section->collapsed == FORMAT_FLEXSECTIONS_EXPANDED) {
                $newvalue = FORMAT_FLEXSECTIONS_COLLAPSED;
            } else {
                $newvalue = FORMAT_FLEXSECTIONS_EXPANDED;
            }
            $format->update_section_format_options(['id' => $section->id, 'collapsed' => $newvalue]);
            rebuild_course_cache($course->id, true);
        }

        // All course sections can be renamed because of the resort.
        $modinfo = get_fast_modinfo($course->id);
        $allsections = $modinfo->get_section_info_all();
        foreach ($allsections as $section) {
            $updates->add_section_put($section->id);
        }
        // The section order is at a course level.
        $updates->add_course_put();
    }

}
