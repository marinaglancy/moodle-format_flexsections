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
import Section from 'format_flexsections/local/content/section';
import CmItem from 'core_courseformat/local/content/section/cmitem';

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

        const courseEditor = getCurrentCourseEditor();

        return new FlexsectionComponent({
            element: document.getElementById(target),
            reactive: courseEditor,
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

    /**
     * Regenerate content indexes.
     *
     * This method is used when a legacy action refresh some content element.
     *
     * Override parent method and replace with our Section and CmItem classes.
     */
    _indexContents() {
        // Find unindexed sections.
        this._scanIndex(
            this.selectors.SECTION,
            this.sections,
            (item) => {
                return new Section(item);
            }
        );

        // Find unindexed cms.
        this._scanIndex(
            this.selectors.CM,
            this.cms,
            (item) => {
                return new CmItem(item);
            }
        );
    }

    /**
     * Refresh the collapse/expand all sections element.
     *
     * @param {Object} state The state data
     */
    _refreshAllSectionsToggler(state) {
        const target = this.getElement(this.selectors.TOGGLEALL);
        if (!target) {
            return;
        }
        // Check if we have all sections collapsed/expanded.
        let allcollapsed = true;
        let allexpanded = true;
        const mainSection = this._mainSection(state);
        const sections = this._getSectionsWithCollapse(state);
        for (let i in sections) {
            if (parseInt(sections[i].parent) === mainSection) {
                allcollapsed = allcollapsed && sections[i].contentcollapsed;
            }
            allexpanded = allexpanded && !sections[i].contentcollapsed;
        }
        // Update control.
        if (allcollapsed) {
            target.classList.add(this.classes.COLLAPSED);
            target.setAttribute('aria-expanded', false);
        }
        if (allexpanded) {
            target.classList.remove(this.classes.COLLAPSED);
            target.setAttribute('aria-expanded', true);
        }
    }

    /**
     * Handle the collapse/expand all sections button.
     *
     * Toggler click is delegated to the main course content element because new sections can
     * appear at any moment and this way we prevent accidental double bindings.
     *
     * @param {Event} event the triggered event
     */
    _allSectionToggler(event) {
        event.preventDefault();

        const target = event.target.closest(this.selectors.TOGGLEALL);
        const isAllCollapsed = target.classList.contains(this.classes.COLLAPSED);

        const sections = this._getSectionsWithCollapse();
        let ids = [];
        for (let i in sections) {
            ids.push(sections[i].id);
        }
        this.reactive.dispatch(
            'sectionContentCollapsed',
            ids,
            !isAllCollapsed
        );
    }

    _mainSection(state) {
        const sectionsList = state.course.sectionlist;
        let sectionNumber = 0;
        if (sectionsList.length === 1) {
            state.section.forEach(s => {
                if (`${s.id}` === `${sectionsList[0]}`) {
                    sectionNumber = parseInt(s.number);
                }
            });
        }
        return sectionNumber;
    }

    _getSectionsWithCollapse(state) {
        if (state === undefined) {
            state = this.reactive.stateManager.state;
        }
        const mainSection = this._mainSection(state);
        let parents = {};
        parents[`${mainSection}`] = `${mainSection}`;
        let displayedSections = [];
        state.section.forEach(
            section => {
                const sectionNumber = parseInt(section.number);
                if (!sectionNumber || sectionNumber === mainSection || !(`${section.parent}` in parents) || section.collapsed) {
                    return;
                }
                parents[`${sectionNumber}`] = `${sectionNumber}`;
                displayedSections.push(section);
            }
        );
        return displayedSections;
    }
}