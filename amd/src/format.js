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

const SELECTORS = {
    SECTION_MERGEUP: 'li.section a[data-action-flexsections="mergeup"]',
    SECTION_MOVE: 'li.section a[data-action-flexsections="move"]',
    SECTIONLINK: `[data-for='section']`,
};

/**
 * Initialize module
 */
export const init = () => {
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
            moveSection(moveElement);
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

const moveSection = (target) => {
    /* eslint-disable no-console */
    console.log(target);
    console.log(target.getAttribute('data-ctxid'));
    const reactive = getCurrentCourseEditor();
    console.log(reactive);
    const sectionId = target.getAttribute('data-id');
    const sectionInfo = reactive.get('section', sectionId);
    console.log(sectionInfo);
    const exporter = reactive.getExporter();
    const data = exporter.course(reactive.state);
    console.log(data);
    ModalFactory.create({
        type: ModalFactory.types.CANCEL,
        title: getString('movecoursesection', 'core'),
        //body: Templates.render('format_flexsections/local/content/movesection', data),
        large: true,
        buttons: {cancel: getString('closebuttontitle', 'core')},
        removeOnClose: true,
    })
        .then(modal => {
            // Fragment.loadFragment('format_flexsections', 'section_move_target', target.getAttribute('data-ctxid'),
            //         {sid: sectionId}).done((html, js) => {
            //     modal.setBody(html);
            //     Templates.runTemplateJS(js);
            // });
            Templates.render('format_flexsections/local/content/movesection', data).
                then((body) => {
                    modal.setBody(body);
                    setupMoveSection(reactive, modal, modal.getBody()[0], sectionId);
            });
            modal.show();
            return modal;
        });
};

const setupMoveSection = (reactive, modal, modalBody, sectionId, element = null) => {
    // Disable current element and section zero.
    // const currentElement = modalBody.querySelector(`${SELECTORS.SECTIONLINK}[data-id='${sectionId}']`);
    // _disableLink(currentElement);
    // const generalSection = modalBody.querySelector(`${SELECTORS.SECTIONLINK}[data-number='0']`);
    // _disableLink(generalSection);

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
 * Replace an element with a copy with a different tag name.
 *
 * @param {Element} element the original element
 */
// const _disableLink = (element) => {
//     console.log('...trying to disable '+element.getAttribute('data-id'));
//     if (element) {
//         element.style.pointerEvents = 'none';
//         element.style.userSelect = 'none';
//         element.classList.add('disabled');
//         element.setAttribute('aria-disabled', true);
//         element.addEventListener('click', event => event.preventDefault());
//     }
// };
