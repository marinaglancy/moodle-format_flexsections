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

import BaseDrawer from 'core_courseformat/local/courseindex/drawer';
import {getCurrentCourseEditor} from 'core_courseformat/courseeditor';
import Exporter from "format_flexsections/local/courseeditor/exporter";

/**
 * Course format component
 *
 * @module     format_flexsections/local/courseindex/drawer
 * @copyright  2022 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export default class Drawer extends BaseDrawer {
    // Extends course/format/amd/src/local/courseindex/drawer.js

    /**
     * Constructor hook.
     */
    create() {
        // Optional component name for debugging.
        this.name = 'courseindex-drawer-flexsections';
    }

    /**
     * Static method to create a component instance form the mustache template.
     *
     * @param {element|string} target the DOM main element or its ID
     * @param {object} selectors optional css selector overrides
     * @return {Component}
     */
    static init(target, selectors) {
        const courseEditor = getCurrentCourseEditor();
        courseEditor.getExporter = () => new Exporter(courseEditor);
        return new Drawer({
            element: document.getElementById(target),
            reactive: courseEditor,
            selectors,
        });
    }
}