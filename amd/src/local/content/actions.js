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

import {BaseComponent} from 'core/reactive';
import {get_string as getString, get_strings as getStrings} from "core/str";
import Notification from "core/notification";
import ModalFactory from "core/modal_factory";
import Templates from "core/templates";
import Pending from "core/pending";

/**
 * Actions
 *
 * @module     format_flexsections/local/content/actions
 * @copyright  2022 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export default class extends BaseComponent {
    // Example: course/format/amd/src/local/content/actions.js

    /**
     * Constructor hook.
     */
    create() {
        // Optional component name for debugging.
        this.name = 'content_actions_flexsections';
        // Default query selectors.
        this.selectors = {
            ACTIONLINK: `[data-action-flexsections]`,
            SECTIONLINK: `[data-for='section']`,
        };
        // Component css classes.
        this.classes = {
            DISABLED: `disabled`,
        };
    }

    /**
     * Initial state ready method.
     *
     * @param {Object} state the state data.
     *
     */
    stateReady(state) {
        super.stateReady(state);
        // Delegate dispatch clicks.
        this.addEventListener(
            this.element,
            'click',
            this._dispatchClick
        );
    }

    _dispatchClick(event) {
        const target = event.target.closest(this.selectors.ACTIONLINK);
        if (!target) {
            return;
        }
        if (target.classList.contains(this.classes.DISABLED)) {
            event.preventDefault();
            return;
        }

        // Invoke proper method.
        const methodName = this._actionMethodName(target.getAttribute('data-action-flexsections'));

        if (this[methodName] !== undefined) {
            this[methodName](target, event);
        }
    }

    _actionMethodName(name) {
        const requestName = name.charAt(0).toUpperCase() + name.slice(1);
        return `_request${requestName}`;
    }

    /**
     * Handle a section delete request.
     *
     * @param {Element} target the dispatch action element
     * @param {Event} event the triggered event
     */
    _requestDelete(target, event) {
        const sectionId = target.dataset.id;
        if (!sectionId) {
            return;
        }
        event.preventDefault();

        const sectionInfo = this.reactive.get('section', sectionId);

        const cmList = sectionInfo.cmlist ?? [];
        if (cmList.length || sectionInfo.hassummary || sectionInfo.rawtitle || sectionInfo.children.length) {
            getStrings([
                {key: 'confirm', component: 'moodle'},
                {key: 'confirmdelete', component: 'format_flexsections'},
                {key: 'yes', component: 'moodle'},
                {key: 'no', component: 'moodle'}
            ]).done((strings) => {
                Notification.confirm(
                    strings[0], // Confirm.
                    strings[1], // Are you sure you want to delete.
                    strings[2], // Yes.
                    strings[3], // No.
                    () => {
                        this.reactive.dispatch('sectionDelete', [sectionId]);
                    }
                );
            }).fail(Notification.exception);
        } else {
            // We don't need confirmation to merge empty sections.
            this.reactive.dispatch('sectionDelete', [sectionId]);
        }
    }

    /**
     * Handle a merge section request.
     *
     * @param {Element} target the dispatch action element
     * @param {Event} event the triggered event
     */
    _requestMergeup(target, event) {
        const sectionId = target.dataset.id;
        if (!sectionId) {
            return;
        }
        event.preventDefault();

        const sectionInfo = this.reactive.get('section', sectionId);

        const cmList = sectionInfo.cmlist ?? [];
        if (cmList.length || sectionInfo.hassummary || sectionInfo.rawtitle || sectionInfo.children.length) {
            getStrings([
                {key: 'confirm', component: 'moodle'},
                {key: 'confirmmerge', component: 'format_flexsections'},
                {key: 'yes', component: 'moodle'},
                {key: 'no', component: 'moodle'}
            ]).done((strings) => {
                Notification.confirm(
                    strings[0], // Confirm.
                    strings[1], // Are you sure you want to merge.
                    strings[2], // Yes.
                    strings[3], // No.
                    () => {
                        this.reactive.dispatch('sectionMergeUp', sectionId);
                    }
                );
            }).fail(Notification.exception);
        } else {
            // We don't need confirmation to merge empty sections.
            this.reactive.dispatch('sectionMergeUp', sectionId);
        }
    }

    /**
     * Handle a move section request.
     *
     * @param {Element} target the dispatch action element
     * @param {Event} event the triggered event
     */
    _requestMoveSection(target, event) {
        event.preventDefault();
        const sectionId = target.dataset.id;
        const sectionInfo = this.reactive.get('section', sectionId);
        const exporter = this.reactive.getExporter();
        const data = exporter.course(this.reactive.state);
        data.sectiontitle = sectionInfo.title;

        if (data.sections.length === 1 && `${data.sections[0].singlesection}` === '1') {
            // We are on a page of a collapsed section. Do not show "move before" and "move after" controls.
            data.singlesectionmode = 1;
            data.sections[0].contentcollapsed = false;
            // TODO allow to move from collapsed section up level.
        }
        const buildParents = (sections, parents) => {
            for (var i in sections) {
                sections[i].parents = parents;
                sections[i].aftersectionid = (i > 0) ? sections[i - 1].id : 0;
                buildParents(sections[i].children, parents + ',' + sections[i].id);
                sections[i].lastchildid = sections[i].children.length ?
                    sections[i].children[sections[i].children.length - 1].id : 0;
            }
        };
        buildParents(data.sections, '');
        data.lastchildid = data.sections.length ? data.sections[data.sections.length - 1].id : 0;

        ModalFactory.create({
            type: ModalFactory.types.CANCEL,
            title: getString('movecoursesection', 'core'),
            large: true,
            buttons: {cancel: getString('closebuttontitle', 'core')},
            removeOnClose: true,
        })
            .then(modal => {
                // eslint-disable-next-line promise/no-nesting
                Templates.render('format_flexsections/local/content/movesection', data).
                then((body) => {
                    modal.setBody(body);
                    this._setupMoveSection(modal, modal.getBody()[0], sectionId, data);
                    return null;
                })
                .fail(() => null);
                modal.show();
                return modal;
            })
            .fail(() => null);
    }

    /**
     * Handle a move activity request.
     *
     * @param {Element} target the dispatch action element
     * @param {Event} event the triggered event
     */
    _requestMoveCm(target, event) {
        event.preventDefault();
        const cmId = target.dataset.id;
        if (!cmId) {
            return;
        }
        const cmInfo = this.reactive.get('cm', cmId);

        const exporter = this.reactive.getExporter();
        const data = exporter.course(this.reactive.state);

        // TODO set before and after current as disabled.
        // TODO allow to move from collapsed section up level.

        data.cmid = cmInfo.id;
        data.cmname = cmInfo.name;

        ModalFactory.create({
            type: ModalFactory.types.CANCEL,
            title: getString('movecoursemodule', 'core'),
            large: true,
            buttons: {cancel: getString('closebuttontitle', 'core')},
            removeOnClose: true,
        })
            .then(modal => {
                // eslint-disable-next-line promise/no-nesting
                Templates.render('format_flexsections/local/content/movecm', data).
                then((body) => {
                    modal.setBody(body);
                    this._setupMoveCm(modal, modal.getBody()[0], cmId, data);
                    return null;
                })
                .fail(() => null);
                modal.show();
                return modal;
            })
            .fail(() => null);
    }

    /**
     * Set up a popup window for moving activity
     *
     * @param {Modal} modal
     * @param {Element} modalBody
     * @param {Number} cmId
     * @param {Object} data
     * @param {Element} element
     */
    _setupMoveCm(modal, modalBody, cmId, data, element = null) {

        // Capture click.
        modalBody.addEventListener('click', (event) => {
            const target = event.target;
            if (!target.matches('a') || target.dataset.for === undefined || target.dataset.id === undefined) {
                return;
            }
            if (target.getAttribute('aria-disabled')) {
                return;
            }
            event.preventDefault();

            const targetSectionId = (target.dataset.for === 'section') ? target.dataset.id : 0;
            const targetCmId = (target.dataset.for === 'cm') ? target.dataset.id : 0;
            this.reactive.dispatch('cmMove', [cmId], targetSectionId, targetCmId);
            this._destroyModal(modal, element);
        });
    }

    /**
     * Destroy modal popup
     *
     * @param {Modal} modal
     * @param {Element} element
     */
    _destroyModal(modal, element = null) {

        // Destroy
        modal.hide();
        const pendingDestroy = new Pending(`courseformat/actions:destroyModal`);
        if (element) {
            element.focus();
        }
        setTimeout(() =>{
            modal.destroy();
            pendingDestroy.resolve();
        }, 500);

    }

    /**
     * Set up a popup window for moving section
     *
     * @param {Modal} modal
     * @param {Element} modalBody
     * @param {Number} sectionId
     * @param {Object} data
     * @param {Element} element
     */
    _setupMoveSection(modal, modalBody, sectionId, data, element = null) {

        // Disable moving before or after itself or under one of its own children.
        const links = modalBody.querySelectorAll(`${this.selectors.SECTIONLINK}`);
        for (let i = 0; i < links.length; ++i) {
            const re = new RegExp(`,${sectionId},`, "g");
            if (links[i].getAttribute('data-before') === `${sectionId}` || links[i].getAttribute('data-after') === `${sectionId}` ||
                `${links[i].getAttribute('data-parents')},`.match(re)) {
                this._disableLink(links[i]);
            }
            // Disable moving to the depth that exceeds the maxsectiondepth setting.
            const depth = (`${links[i].getAttribute('data-parents')}`.match(/,/g) || []).length;
            if (data.maxsectiondepth && depth >= data.maxsectiondepth) {
                this._disableLink(links[i]);
            }
        }

        // TODO.
        // Setup keyboard navigation.
        // new ContentTree(
        //     modalBody.querySelector(this.selectors.CONTENTTREE),
        //     {
        //         SECTION: this.selectors.SECTIONNODE,
        //         TOGGLER: this.selectors.MODALTOGGLER,
        //         COLLAPSE: this.selectors.MODALTOGGLER,
        //     },
        //     true
        // );

        // Capture click.
        modalBody.addEventListener('click', (event) => {

            const target = event.target;
            if (!target.matches('a') || target.dataset.for !== 'section' || target.dataset.after === undefined) {
                return;
            }
            if (target.getAttribute('aria-disabled')) {
                return;
            }
            event.preventDefault();
            const afterId = parseInt(target.dataset.after);
            const parentId = parseInt(target.dataset.parentid);
            this.reactive.dispatch('sectionMove', [sectionId], afterId ? afterId : -parentId);

            this._destroyModal(modal, element);
        });
    }

    /**
     * Disable link
     *
     * @param {Element} element
     */
    _disableLink(element) {
        if (element) {
            element.style.pointerEvents = 'none';
            element.style.userSelect = 'none';
            element.classList.add('disabled');
            element.setAttribute('aria-disabled', true);
            element.addEventListener('click', event => event.preventDefault());
        }
    }

    /**
     * Handle a request to add a subsection as the last child of the parent
     *
     * @param {Element} target the dispatch action element
     * @param {Event} event the triggered event
     */
    _requestAddSubSection(target, event) {
        event.preventDefault();
        this.reactive.dispatch('addSubSection', parseInt(target.dataset.parentid ?? 0));
    }

    /**
     * Handle a request to add a subsection as the first child of the parent
     *
     * @param {Element} target the dispatch action element
     * @param {Event} event the triggered event
     */
    _requestInsertSubSection(target, event) {
        event.preventDefault();
        this.reactive.dispatch('insertSubSection', parseInt(target.dataset.parentid ?? 0));
    }

    /**
     * Handle a request to switch the section mode (displayed on the same page vs as a link).
     *
     * @param {Element} target the dispatch action element
     * @param {Event} event the triggered event
     */
    _requestSectionSwitchCollapsed(target, event) {
        event.preventDefault();
        this.reactive.dispatch('sectionSwitchCollapsed', target.dataset.id ?? 0);
    }
}
