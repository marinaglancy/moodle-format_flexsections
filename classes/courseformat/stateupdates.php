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

/**
 * class stateupdates
 *
 * @package   format_flexsections
 * @copyright 2022 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stateupdates extends \core_courseformat\stateupdates {

    /**
     * Add track about a section deleted.
     *
     * @param int $sectionid The affected section id.
     */
    public function add_section_remove(int $sectionid): void {
        // In Moodle 4.0 - 4.0.2 this function did not exist.
        // In Moodle 4.1 using add_section_delete() displays a debugging message.
        // This override can be removed when minimum required version is 4.1 or 4.2.
        $this->add_update('section', 'remove', (object)['id' => $sectionid]);
    }

    /**
     * Add track about a course module removed.
     *
     * @param int $cmid the affected course module id
     */
    public function add_cm_remove(int $cmid): void {
        $this->add_update('cm', 'remove', (object)['id' => $cmid]);
    }
}
