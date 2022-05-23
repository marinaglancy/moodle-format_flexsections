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

/**
 * Various actions on modules and sections in the editing mode - hiding, duplicating, deleting, etc.
 *
 * @module     format_flexsections/format
 * @copyright  2022 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Notification from 'core/notification';
import {get_string as getString, get_strings as getStrings} from 'core/str';
import ModalFactory from "core/modal_factory";
//import Fragment from "core/fragment";
import Templates from "core/templates";
import {getCurrentCourseEditor} from 'core_courseformat/courseeditor';
//import ContentTree from "../../../amd/src/local/courseeditor/contenttree";
import Pending from "core/pending";
//import DefaultMutations from 'core_courseformat/local/courseeditor/mutations';
//import DefaultStateManager from 'core/local/reactive/stateManager';
//import {Reactive} from "core/reactive";

/* eslint-disable no-console */

const SELECTORS = {
    SECTION_MERGEUP: 'li.section a[data-action-flexsections="mergeup"]',
    SECTION_MOVE: 'li.section a[data-action-flexsections="move"]',
    SECTIONLINK: `[data-for='section']`,
};

/**
 * Initialize module
 */
export const init = () => {

    // Experiment - overriding "put" method.
    // const reactive = getCurrentCourseEditor();
    // const myput = (stateManager, updateName, fields) => {
    //     stateManager.defaultPut(stateManager, updateName, fields);
    // };
    // reactive.stateManager.addUpdateTypes({"put": myput});

    document.addEventListener('click', e => {
        const mergeupElement = e.target.closest(SELECTORS.SECTION_MERGEUP);
        if (mergeupElement) {
            e.preventDefault();
            confirmMerge(mergeupElement);
            return;
        }

        const moveElement = e.target.closest(SELECTORS.SECTION_MOVE);
        if (moveElement) {
            e.preventDefault();
            showMoveSectionPopup(moveElement);
            return;
        }
    });
};

const confirmMerge = (target) => {
    const href = target.getAttribute('href');
    getStrings([
        {key: 'confirm', component: 'moodle'},
        {key: 'confirmmerge', component: 'format_flexsections'},
        {key: 'yes', component: 'moodle'},
        {key: 'no', component: 'moodle'}
    ]).done(function(strings) {
        Notification.confirm(
            strings[0], // Confirm.
            strings[1], // Are you sure you want to merge.
            strings[2], // Yes.
            strings[3], // No.
            function() {
                window.location.href = href + '&confirm=1';
            }
        );
    }).fail(Notification.exception);
};

const showMoveSectionPopup = (target) => {
    const reactive = getCurrentCourseEditor();
    const sectionId = target.getAttribute('data-id');
    const sectionInfo = reactive.get('section', sectionId);
    const exporter = reactive.getExporter();
    const data = exporter.course(reactive.state);
    data.sectiontitle = sectionInfo.title;
    //console.log(data);

    if (data.sections.length === 1 && `${data.sections[0].singlesection}` === '1') {
        // We are on a page of a collapsed section. Do not show "move before" and "move after" controls.
        data.singlesectionmode = 1;
        data.sections[0].contentcollapsed = false;
    }
    const buildParents = (sections, parents) => {
        for (var i in sections) {
            sections[i].parents = parents;
            sections[i].aftersectionid = (i>0) ? sections[i-1].id : 0;
            buildParents(sections[i].children, parents + ',' + sections[i].id);
            sections[i].lastchildid = sections[i].children.length ? sections[i].children[sections[i].children.length - 1].id : 0;
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
            Templates.render('format_flexsections/local/content/movesection', data).
                then((body) => {
                    modal.setBody(body);
                    setupMoveSection(reactive, modal, modal.getBody()[0], sectionId, data);
            });
            modal.show();
            return modal;
        });
};

const setupMoveSection = (reactive, modal, modalBody, sectionId, data, element = null) => {

    // Disable moving before or after itself or under one of its own children.
    const links = modalBody.querySelectorAll(`${SELECTORS.SECTIONLINK}`);
    for (let i = 0; i < links.length; ++i) {
        const re = new RegExp(`,${sectionId},`,"g");
        if (links[i].getAttribute('data-id') === `${sectionId}` || links[i].getAttribute('data-after') === `${sectionId}` ||
            `${links[i].getAttribute('data-parents')},`.match(re)) {
            _disableLink(links[i]);
        }
    }

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
        if (!target.matches('a') || target.dataset.for !== 'section' || target.dataset.id === undefined) {
            return;
        }
        if (target.getAttribute('aria-disabled')) {
            return;
        }
        event.preventDefault();
        reactive.dispatch('sectionMove', [sectionId], target.dataset.id);

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
    });
};

/**
 * Disable link
 *
 * @param {Element} element
 */
const _disableLink = (element) => {
    if (element) {
        element.style.pointerEvents = 'none';
        element.style.userSelect = 'none';
        element.classList.add('disabled');
        element.setAttribute('aria-disabled', true);
        element.addEventListener('click', event => event.preventDefault());
    }
};
