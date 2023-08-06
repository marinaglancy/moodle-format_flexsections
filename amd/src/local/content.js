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

import Component from 'core_courseformat/local/content';
import {getCurrentCourseEditor} from 'core_courseformat/courseeditor';
import Section from 'format_flexsections/local/content/section';
import CmItem from 'core_courseformat/local/content/section/cmitem';
import Mutations from "format_flexsections/local/courseeditor/mutations";
import FlexsectionsActions from 'format_flexsections/local/content/actions';
import Exporter from "format_flexsections/local/courseeditor/exporter";

/**
 * Course format component
 *
 * @module     format_flexsections/local/content
 * @copyright  2022 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export default class FlexsectionComponent extends Component {
    // Extends course/format/amd/src/local/content.js

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
        courseEditor.getExporter = () => new Exporter(courseEditor);

        // Hack to preserve legacy mutations (added in core_course/actions) after we set own plugin mutations.
        let legacyActivityAction = courseEditor.mutations.legacyActivityAction ?? null;
        let legacySectionAction = courseEditor.mutations.legacySectionAction ?? null;
        courseEditor.setMutations(new Mutations());
        courseEditor.addMutations({
            ...(legacyActivityAction ? {legacyActivityAction} : {}),
            ...(legacySectionAction ? {legacySectionAction} : {})});

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
        // Optional component name for debugging.
        this.name = 'course_format_flexsections';
        this.selectors.COURSE_SUBSECTIONLIST = `[data-for='course_subsectionlist']`;
    }

    /**
     * Initial state ready method.
     *
     * @param {Object} state the state data
     */
    stateReady(state) {
        super.stateReady(state);
        if (this.reactive.supportComponents) {
            // Actions are only available in edit mode.
            if (this.reactive.isEditing) {
                new FlexsectionsActions(this);
            }
        }
        if (state.course.accordion) {
            this._ensureOnlyOneSectionIsExpanded(state);
            // Monitor hash change so that we can expand the section from the hash.
            window.addEventListener(
                "hashchange",
                this._hashHandler.bind(this),
            );
        }
    }

    _ensureOnlyOneSectionIsExpanded(state) {
        const isExpanded = (sectionInfo) => !sectionInfo.showaslink && !sectionInfo.contentcollapsed;
        const hasExpandedChildren = (sectionInfo) =>
            (sectionInfo.children ?? []).some(s => isExpanded(s));

        let firstExpandedSection = null;
        for (let sectionInfo of this._getSectionsWithCollapse(state)) {
            if (!firstExpandedSection && isExpanded(sectionInfo) && !hasExpandedChildren(sectionInfo)) {
                firstExpandedSection = sectionInfo;
            }
        }

        if (firstExpandedSection) {
            const sectionitem = this.getElement(this.selectors.SECTION, firstExpandedSection.id);
            this._collapseAllSectionsExceptFor(sectionitem, false);
        }
    }

    /**
     * Return the component watchers.
     *
     * @returns {Array} of watchers
     */
    getWatchers() {
        let res = super.getWatchers();
        res.push({watch: `course.hierarchy:updated`, handler: this._refreshCourseHierarchy});
        res.push({watch: `section.showaslink:updated`, handler: this._reloadSection});
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
        for (let i = 0; i < hierarchy.length; i++) {
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
     * Setup sections toggler.
     *
     * Toggler click is delegated to the main course content element because new sections can
     * appear at any moment and this way we prevent accidental double bindings.
     *
     * @param {Event} event the triggered event
     */
    _sectionTogglers(event) {
        // Overrides parent method to add more functionality.
        const sectionlink = event.target.closest(this.selectors.TOGGLER);
        const closestCollapse = event.target.closest(this.selectors.COLLAPSE);
        const isChevron = closestCollapse?.closest(this.selectors.SECTION_ITEM);

        if (sectionlink || isChevron) {
            const section = event.target.closest(this.selectors.SECTION);
            const toggler = section.querySelector(this.selectors.COLLAPSE);
            const isCollapsed = toggler?.classList.contains(this.classes.COLLAPSED) ?? false;


            if (isChevron || isCollapsed) {
                const sectionId = parseInt(section.getAttribute('data-id'));
                // Update the state.
                this.reactive.dispatch(
                    'sectionContentCollapsed',
                    [sectionId],
                    !isCollapsed
                );
            }
            // If we expanded a section, collapse all other expanded sections
            // except for this section parents.
            if (isCollapsed && this.reactive.stateManager.state.course.accordion) {
                this._collapseAllSectionsExceptFor(section);
            }
        }
    }

    /**
     * Collapse all sections except for the given one and its parents.
     *
     * @param {HTMLElement} section
     * @param {Boolean} scrollToSection
     */
    _collapseAllSectionsExceptFor(section, scrollToSection = true) {
        const sectionNumber = parseInt(section.getAttribute('data-sectionid'));
        const leaveOpen = [...this._findAllParents(sectionNumber), sectionNumber];
        if (sectionNumber > 0 && leaveOpen.includes(0)) {
            leaveOpen.splice(leaveOpen.indexOf(0), 1);
        }
        const sectionIds =
            this._getSectionsWithCollapse(this.reactive.stateManager.state)
                .filter(s => !leaveOpen.includes(parseInt(s.section)))
                .map(s => s.id);
        this.reactive.dispatch(
            'sectionContentCollapsed',
            sectionIds,
            true
        );
        if (scrollToSection) {
            const toggler = section.querySelector(this.selectors.COLLAPSE);
            setTimeout(() => {
                toggler.scrollIntoView({behavior: "smooth", block: "nearest"});
            }, 500);
        }
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
        const mainSection = this._mainSection();
        const sections = this._getSectionsWithCollapse(state);
        for (let i in sections) {
            if (parseInt(sections[i].parent) === mainSection) {
                allcollapsed = allcollapsed && sections[i].contentcollapsed;
            }
        }
        // Update control.
        if (allcollapsed) {
            target.classList.add(this.classes.COLLAPSED);
            target.setAttribute('aria-expanded', false);
        } else {
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

    /**
     * Find main section
     *
     * @returns {Number}
     */
    _mainSection() {
        return parseInt(this.element.getAttribute('data-flexsections-mainsection'));
    }

    /**
     * Get all sections that can be collapsed or expanded
     *
     * @param {Object} state The state data
     * @returns {Array}
     */
    _getSectionsWithCollapse(state) {
        if (state === undefined) {
            state = this.reactive.stateManager.state;
        }
        const mainSection = this._mainSection();
        let parents = {};
        parents[`${mainSection}`] = `${mainSection}`;
        let displayedSections = [];
        state.section.forEach(
            section => {
                const sectionNumber = parseInt(section.number);
                const toggler = this.getElement(this.selectors.SECTION, section.id)?.querySelector(this.selectors.COLLAPSE);

                if (!toggler || !(`${section.parent}` in parents) || section.showaslink) {
                    return;
                }
                parents[`${sectionNumber}`] = `${sectionNumber}`;
                displayedSections.push(section);
            }
        );
        return displayedSections;
    }

    /**
     * Find all parents of the current section (section numbers, not ids)
     *
     * @param {Number} thisSectionNumber
     * @returns {Array} Array of section numbers that are parents of this one
     */
    _findAllParents(thisSectionNumber) {
        // Section object has properties: number, id, parent, parentid.
        if (thisSectionNumber === this._mainSection()) {
            return [];
        }
        let section = this.reactive.stateManager.state.section
            .find(section => parseInt(section.number) === thisSectionNumber);
        if (section && section.parent !== undefined) {
            const parent = parseInt(section.parent);
            return [...this._findAllParents(parent), parent];
        }
        return [];
    }

    /**
     * Handler for when the page hash was changed - if in accordion mode, expand the target section
     */
    _hashHandler() {
        if ((window.location.hash ?? '').length <= 1) {
            return;
        }
        const target = document.querySelector(`${window.location.hash}${this.selectors.SECTION}`);
        if (!target) {
            return;
        }
        const toggler = target.querySelector(this.selectors.COLLAPSE);
        if (toggler) {
            const sectionNumber = parseInt(target.getAttribute('data-sectionid'));
            const toExpand = [...this._findAllParents(sectionNumber), sectionNumber].filter(s => s > 0);
            const sectionIds =
                this._getSectionsWithCollapse(this.reactive.stateManager.state)
                    .filter(s => toExpand.includes(parseInt(s.section)))
                    .map(s => s.id);
            this.reactive.dispatch(
                'sectionContentCollapsed',
                sectionIds,
                false
            );
            this._collapseAllSectionsExceptFor(target);
        }
    }
}