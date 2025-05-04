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

/**
 * Class bulkedittools
 *
 * @package    format_flexsections
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulkedittools extends \core_courseformat\output\local\content\bulkedittools {

    /**
     * Generate the bulk edit control items of a course module.
     *
     * Format plugins can override the method to add or remove elements
     * from the toolbar.
     *
     * @return array of edit control items
     */
    protected function cm_control_items(): array {
        $items = parent::cm_control_items();
        // TODO "Move" action from the parent class is not working with flexsections.
        unset($items['move']);
        return $items;
    }

    /**
     * Generate the bulk edit control items of a section.
     *
     * Format plugins can override the method to add or remove elements
     * from the toolbar.
     *
     * @return array of edit control items
     */
    protected function section_control_items(): array {
        // TODO Section controls are not working with flexsections.
        return [];
    }
}
