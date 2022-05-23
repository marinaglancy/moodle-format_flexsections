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
            //console.log(`i=${i}; sectionid = ${hierarchy[i].id}; children = `+sectionlist.join(','));
            if (listparent) {
                this._fixOrder(listparent, sectionlist, this.selectors.SECTION, this.dettachedSections, createSection);
            }
        }
    }

    /**
     * Fix/reorder the section or cms order.
     *
     * I had to override the parent method because it removes the empty container too early.
     *
     * @param {Element} container the HTML element to reorder.
     * @param {Array} neworder an array with the ids order
     * @param {string} selector the element selector
     * @param {Object} dettachedelements a list of dettached elements
     * @param {function} createMethod method to create missing elements
     */
    async _fixOrder(container, neworder, selector, dettachedelements, createMethod) {
        if (container === undefined) {
            return;
        }

        // Grant the list is visible (in case it was empty).
        container.classList.remove('hidden');

        // Move the elements in order at the beginning of the list.
        neworder.forEach((itemid, index) => {
            let item = this.getElement(selector, itemid) ?? dettachedelements[itemid] ?? createMethod(container, itemid);
            if (item === undefined) {
                // Missing elements cannot be sorted.
                return;
            }
            // Get the current elemnt at that position.
            const currentitem = container.children[index];
            if (currentitem === undefined) {
                container.append(item);
                return;
            }
            if (currentitem !== item) {
                container.insertBefore(item, currentitem);
            }
        });

        // Dndupload add a fake element we need to keep.
        let dndFakeActivity;

        // Remove the remaining elements.
        while (container.children.length > neworder.length) {
            const lastchild = container.lastChild;
            if (lastchild?.classList?.contains('dndupload-preview')) {
                dndFakeActivity = lastchild;
            } else {
                dettachedelements[lastchild?.dataset?.id ?? 0] = lastchild;
            }
            container.removeChild(lastchild);
        }

        // Empty lists should not be visible.
        if (!neworder.length) {
            container.classList.add('hidden');
            container.innerHTML = '';
            return;
        }

        // Restore dndupload fake element.
        if (dndFakeActivity) {
            container.append(dndFakeActivity);
        }
    }
}