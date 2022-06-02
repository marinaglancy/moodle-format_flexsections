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

import BaseSection from "core_courseformat/local/courseindex/section";

/**
 * Course index section component.
 *
 * This component is used to control specific course section interactions like drag and drop.
 *
 * @module     format_flexsections/local/courseindex/section
 * @copyright  2022 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export default class Component extends BaseSection {
    // Extends course/format/amd/src/local/courseindex/section.js
    // Extends course/format/amd/src/local/courseeditor/dndsection.js

    /**
     * Static method to create a component instance form the mustahce template.
     *
     * @param {string} target the DOM main element or its ID
     * @param {object} selectors optional css selector overrides
     * @return {Component}
     */
    static init(target, selectors) {
        return new Component({
            element: document.getElementById(target),
            selectors,
        });
    }

    /**
     * Register state values and the drag and drop subcomponent.
     *
     * @param {BaseComponent} sectionitem section item component
     */
    configDragDrop(sectionitem) {
        sectionitem.draggable = false; // <---- my modification - disable drag&drop of sections for now.
        super.configDragDrop(sectionitem);
    }
}
