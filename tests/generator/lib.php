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
 * Data generator class
 *
 * @package    format_flexsections
 * @category   test
 * @copyright  2023 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_flexsections_generator extends component_generator_base {
    /**
     * Create a new course section.
     *
     * @param array $data properties such as: courseid, name,
     *     summary, summaryformat, visible,
     *     parent (= name of the parent section),
     *     collapsed (= display as a link)
     * @return int
     */
    public function create_section(array $data): int {
        global $DB;
        $courseid = $data['courseid'];
        $parentname = $data['parent'] ?? '';
        unset($data['courseid'], $data['parent']);
        $lastsection = (int)$DB->get_field_sql(
            'SELECT max(section) from {course_sections} WHERE course = ?',
            [$courseid]);
        $section = course_create_section($courseid, $lastsection + 1, true);
        course_update_section($courseid, $section, $data);
        if (strlen('' . $parentname)) {
            $parentsection = $DB->get_field('course_sections', 'section',
                ['course' => $courseid, 'name' => $parentname], MUST_EXIST);
            /** @var format_flexsections $format */
            $format = course_get_format($courseid);
            $format->move_section($section->section, $parentsection);
        }
        return $section->id;
    }
}
