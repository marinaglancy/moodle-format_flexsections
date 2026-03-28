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

/**
 * Specialised restore for Flexible sections course format.
 *
 * Cleans up after partial course import by removing empty orphan sections
 * and renumbering to close gaps left by excluded subsections.
 *
 * @package    format_flexsections
 * @copyright  2026 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_format_flexsections_plugin extends restore_format_plugin {
    /**
     * Returns the paths to be handled by the plugin at course level.
     *
     * @return restore_path_element[]
     */
    public function define_course_plugin_structure() {
        return [new restore_path_element('dummy_course', $this->get_pathfor('/dummycourse'))];
    }

    /**
     * Dummy process method for course level.
     */
    public function process_dummy_course() {
    }

    /**
     * Returns the paths to be handled by the plugin at section level.
     *
     * @return restore_path_element[]
     */
    protected function define_section_plugin_structure() {
        return [new restore_path_element('dummy_section', $this->get_pathfor('/dummysection'))];
    }

    /**
     * Dummy process method for section level.
     */
    public function process_dummy_section() {
    }

    /**
     * Executed after course restore is complete (course-level hook).
     */
    public function after_restore_course() {
        $this->cleanup_after_restore();
    }

    /**
     * Executed after each section restore is complete (section-level hook).
     */
    public function after_restore_section() {
        $this->cleanup_after_restore();
    }

    /**
     * Clean up after partial import of a flexsections course.
     *
     * When sections are excluded during partial import, two problems can occur:
     * 1. Empty placeholder sections are created with parent=0 (orphan top-level sections).
     * 2. Section numbering has gaps (e.g. 0,1,2,4) which Moodle fills with "Topic N" sections.
     *
     * This method removes orphans and renumbers sections to close gaps.
     */
    protected function cleanup_after_restore() {
        global $DB;

        $courseid = $this->step->get_task()->get_courseid();

        // Get all sections and their parent format options.
        $sections = $DB->get_records(
            'course_sections',
            ['course' => $courseid],
            'section ASC',
            'id, section, name, summary, sequence'
        );
        $parentoptions = $DB->get_records_sql(
            "SELECT cfo.sectionid, cfo.id, cfo.value
               FROM {course_format_options} cfo
              WHERE cfo.courseid = ?
                AND cfo.format = 'flexsections'
                AND cfo.name = 'parent'
                AND cfo.sectionid != 0",
            [$courseid]
        );

        $parentsbyid = [];
        foreach ($parentoptions as $record) {
            $parentsbyid[$record->sectionid] = (int)$record->value;
        }

        // Build set of section numbers that exist.
        $existingnums = [];
        foreach ($sections as $section) {
            $existingnums[$section->section] = true;
        }

        // Reset parent references that point to non-existent section numbers.
        foreach ($parentoptions as $record) {
            $parentnum = (int)$record->value;
            if ($parentnum > 0 && !isset($existingnums[$parentnum])) {
                $DB->set_field('course_format_options', 'value', 0, ['id' => $record->id]);
                $parentsbyid[$record->sectionid] = 0;
            }
        }

        // Find which section numbers are referenced as parents.
        $referencedparents = [];
        foreach ($parentsbyid as $parentnum) {
            if ($parentnum > 0) {
                $referencedparents[$parentnum] = true;
            }
        }

        // Delete empty orphan sections: top-level (parent=0), no activities,
        // no name/summary, not referenced as parent by any other section.
        $changed = false;
        foreach ($sections as $section) {
            if ($section->section == 0) {
                continue;
            }
            $parent = $parentsbyid[$section->id] ?? 0;
            $hasactivities = !empty(trim($section->sequence ?? ''));
            $hasname = !empty(trim($section->name ?? ''));
            $hassummary = !empty(trim($section->summary ?? ''));
            $isparent = isset($referencedparents[$section->section]);

            if ($parent == 0 && !$hasactivities && !$hasname && !$hassummary && !$isparent) {
                $DB->delete_records('course_format_options', ['courseid' => $courseid, 'sectionid' => $section->id]);
                $DB->delete_records('course_sections', ['id' => $section->id]);
                unset($sections[$section->id]);
                $changed = true;
            }
        }

        // Renumber sections to close any gaps, using the two-step negative-number
        // approach to avoid unique constraint violations (same as move_section in lib.php).
        $remainingsections = $changed
            ? $DB->get_records('course_sections', ['course' => $courseid], 'section ASC')
            : $sections;
        $renumbermap = []; // Old section number => new section number.
        $num = 0;
        foreach ($remainingsections as $section) {
            if ($section->section != $num) {
                $renumbermap[$section->section] = $num;
            }
            $num++;
        }

        if (!empty($renumbermap)) {
            // Step 1: Set to negative numbers to avoid uniqueness constraint.
            foreach ($renumbermap as $old => $new) {
                $DB->execute(
                    "UPDATE {course_sections} SET section = ? WHERE course = ? AND section = ?",
                    [-$new - 1, $courseid, $old]
                );
            }
            // Step 2: Set to correct positive numbers.
            foreach ($renumbermap as $old => $new) {
                $DB->execute(
                    "UPDATE {course_sections} SET section = ? WHERE course = ? AND section = ?",
                    [$new, $courseid, -$new - 1]
                );
            }
            // Update parent references.
            foreach ($renumbermap as $old => $new) {
                $DB->execute(
                    "UPDATE {course_format_options}
                        SET value = ?
                      WHERE courseid = ? AND format = 'flexsections' AND name = 'parent' AND value = ?",
                    [(string)$new, $courseid, (string)$old]
                );
            }
        }

        if ($changed || !empty($renumbermap)) {
            rebuild_course_cache($courseid);
        }
    }
}
