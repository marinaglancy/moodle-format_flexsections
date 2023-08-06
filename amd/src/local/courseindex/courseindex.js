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

import {getCurrentCourseEditor} from 'core_courseformat/courseeditor';
import BaseCourseindex from 'core_courseformat/local/courseindex/courseindex';
import Exporter from "format_flexsections/local/courseeditor/exporter";

/**
 * Course index main component.
 *
 * @module     format_flexsections/local/courseindex/courseindex
 * @copyright  2022 Marina Glancu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export default class Component extends BaseCourseindex {
    // Extends course/format/amd/src/local/courseindex/courseindex.js

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
        return new Component({
            element: document.getElementById(target),
            reactive: courseEditor,
            selectors,
        });
    }

    /**
     * Constructor hook.
     *
     * @param {Object} descriptor the component descriptor
     */
    create(descriptor) {
        super.create(descriptor);
        // Optional component name for debugging.
        this.name = 'course_format_flexsections_courseindex';
        this.selectors.COURSE_SUBSECTIONLIST = `[data-for='subsectionlist']`;
    }

    /**
     * Return the component watchers.
     *
     * @returns {Array} of watchers
     */
    getWatchers() {
        let res = super.getWatchers();
        res.push({watch: `course.hierarchy:updated`, handler: this._refreshCourseSectionlist});
        return res;
    }

    /**
     * Refresh the section list.
     *
     * @param {object} param
     * @param {Object} param.element
     */
    _refreshCourseSectionlist({element}) {
        const hierarchy = element.hierarchy ?? [];
        let dettachedSections = [];
        for (let i = 0; i < hierarchy.length; i++) {
            const listparent = this.getElement(this.selectors.COURSE_SUBSECTIONLIST + `[data-parent='${hierarchy[i].id}']`);
            this._fixOrderFlexsections(listparent, hierarchy[i].children, dettachedSections);
        }
        this._fixOrderFlexsections(this.element, element.sectionlist ?? [], dettachedSections);
    }

    /**
     * Refresh a section cm list.
     *
     * @param {object} param
     * @param {Object} param.element
     */
    _refreshSectionCmlist({element}) {
        const cmlist = element.cmlist ?? [];
        const listparent = this.getElement(this.selectors.SECTION_CMLIST, element.id);
        if (listparent) {
            // Function overridden because the cm list can be empty if the course index
            // is set to display sections only.
            this._fixOrder(listparent, cmlist, this.cms);
        }
    }

    /**
     * Fix/reorder the section or cms order.
     *
     * @param {Element} container the HTML element to reorder.
     * @param {Array} neworder an array with the ids order
     * @param {Object} dettachedelements a list of dettached elements
     */
    async _fixOrderFlexsections(container, neworder, dettachedelements) {
        if (container === undefined || !container) {
            return;
        }

        // Grant the list is visible (in case it was empty).
        container.classList.remove('hidden');

        // Move the elements in order at the beginning of the list.
        neworder.forEach((itemid, index) => {
            let item = this.getElement(this.selectors.SECTION, itemid) ?? dettachedelements[itemid];
            if (item === undefined) {
                // Missing elements cannot be sorted.
                return;
            }
            // Get the current element at that position.
            const currentitem = container.children[index];
            if (currentitem === undefined) {
                container.append(item);
                return;
            }
            if (currentitem !== item) {
                container.insertBefore(item, currentitem);
            }
        });

        // Remove the remaining elements.
        while (container.children.length > neworder.length) {
            const lastchild = container.lastChild;
            dettachedelements[lastchild?.dataset?.id ?? 0] = lastchild;
            container.removeChild(lastchild);
        }

        // Empty lists should not be visible.
        if (!neworder.length) {
            container.classList.add('hidden');
        }
    }

    /**
     * Create a new section instance.
     *
     * @param {Object} details the update details.
     * @param {Object} details.state the state data.
     * @param {Object} details.element the element data.
     */
    async _createSection({state, element}) {
        const sectionItem = this.getElement('section', element.id) ?? this._createFakeSection(this.element, element.id);
        if (0) { // eslint-disable-line no-constant-condition
            // TODO. Commented out part of parent function code. Is it needed for something?
            this.sections[element.id] = sectionItem;
            // Place the fake node on the correct position.
            this._refreshCourseSectionlist({
                state,
                element: state.course,
            });
        }
        // Collect render data.
        const exporter = this.reactive.getExporter();
        const data = exporter.section(state, element);
        // Create the new content.
        const newcomponent = await this.renderComponent(sectionItem, 'format_flexsections/local/courseindex/section', data);
        // Replace the fake node with the real content.
        const newelement = newcomponent.getElement();
        this.sections[element.id] = newelement;
        sectionItem.parentNode.replaceChild(newelement, sectionItem);
    }

    /**
     * Create a placeholder for a section
     *
     * @param {Element} container
     * @param {Number} sectionid
     * @returns {Element}
     */
    _createFakeSection(container, sectionid) {
        const fakeelement = document.createElement('div');
        container.appendChild(fakeelement);
        fakeelement.classList.add('bg-pulse-grey', 'w-100');
        fakeelement.dataset.for = 'section';
        fakeelement.dataset.id = sectionid;
        fakeelement.innerHTML = '&nbsp;';
        return fakeelement;
    }
}
