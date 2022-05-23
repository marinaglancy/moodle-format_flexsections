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
/* eslint-disable no-console */

/**
 * Course format component
 *
 * @module     format_flexsections/format
 * @copyright  2022 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Component from 'core_courseformat/local/content'; // course/format/amd/src/local/content.js
import {getCurrentCourseEditor} from 'core_courseformat/courseeditor';

export default class FlexsectionComponent extends Component {

    /**
     * Static method to create a component instance form the mustahce template.
     *
     * @param {string} target the DOM main element or its ID
     * @param {object} selectors optional css selector overrides
     * @param {number} sectionReturn the content section return
     * @return {Component}
     */
    static init(target, selectors, sectionReturn) {
        // Must be overridden because parent class returns "new Component" instead of returning "new this".
        return new FlexsectionComponent({
            element: document.getElementById(target),
            reactive: getCurrentCourseEditor(),
            selectors,
            sectionReturn,
        });
    }

    /**
     * Constructor hook.
     *
     * @param {Object} descriptor the component descriptor
     */
    create(descriptor) {
        super.create(descriptor);
        this.selectors.COURSE_SUBSECTIONLIST = `[data-for='course_subsectionlist']`;
    }

    /**
     * Return the component watchers.
     *
     * @returns {Array} of watchers
     */
    getWatchers() {
        let res = super.getWatchers();
        res.push({watch: `course.hierarchy:updated`, handler: this._refreshCourseHierarchy});
        return res;
    }

    /**
     * Refresh the section list.
     *
     * @param {Object} param
     * @param {Object} param.element details the update details.
     */
    _refreshCourseHierarchy({element}) {
        const hierarchy = element.hierarchy ?? [];
        const createSection = this._createSectionItem.bind(this);
        for (let i = 0; i<hierarchy.length; i++) {
            const sectionlist = hierarchy[i].children;
            const listparent = this.getElement(this.selectors.COURSE_SUBSECTIONLIST + `[data-parent='${hierarchy[i].id}']`);
            if (listparent) {
                this._fixOrder(listparent, sectionlist, this.selectors.SECTION, this.dettachedSections, createSection);
            }
        }
    }
}