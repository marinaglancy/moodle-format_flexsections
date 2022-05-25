/* eslint-disable no-console */
import {BaseComponent} from 'core/reactive';
import {get_string as getString, get_strings as getStrings} from "core/str";
import Notification from "core/notification";
import ModalFactory from "core/modal_factory";
import Templates from "core/templates";
import Pending from "core/pending";

export default class extends BaseComponent {
    // Example: course/format/amd/src/local/content/actions.js

    /**
     * Constructor hook.
     */
    create() {
        // Optional component name for debugging.
        // this.name = 'content_actions';
        // Default query selectors.
        this.selectors = {
            ACTIONLINK: `[data-action-flexsections]`,
            // // Move modal selectors.
            SECTIONLINK: `[data-for='section']`,
            // CMLINK: `[data-for='cm']`,
            // SECTIONNODE: `[data-for='sectionnode']`,
            // MODALTOGGLER: `[data-toggle='collapse']`,
            // ADDSECTION: `[data-action='addSection']`,
            // CONTENTTREE: `#destination-selector`,
            // ACTIONMENU: `.action-menu`,
            // ACTIONMENUTOGGLER: `[data-toggle="dropdown"]`,
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
        console.log('_dispatchClick');
        console.log(event);
        console.log(target);
        if (!target) {
            return;
        }
        if (target.classList.contains(this.classes.DISABLED)) {
            event.preventDefault();
            return;
        }

        // Invoke proper method.
        const methodName = this._actionMethodName(target.getAttribute('data-action-flexsections'));
        console.log('Trying to call '+methodName);

        if (this[methodName] !== undefined) {
            this[methodName](target, event);
        }
    }

    _actionMethodName(name) {
        const requestName = name.charAt(0).toUpperCase() + name.slice(1);
        return `_request${requestName}`;
    }

    _requestMergeup(target, event) {
        event.preventDefault();
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
                    this.reactive.dispatch('mergeup', [target.getAttribute('data-id')], 0);
                }
            );
        }).fail(Notification.exception);
    }

    _requestMoveSection(target, event) {
        event.preventDefault();
        const sectionId = target.getAttribute('data-id');
        const sectionInfo = this.reactive.get('section', sectionId);
        const exporter = this.reactive.getExporter();
        const data = exporter.course(this.reactive.state);
        data.sectiontitle = sectionInfo.title;
        //console.log(data);

        if (data.sections.length === 1 && `${data.sections[0].singlesection}` === '1') {
            // We are on a page of a collapsed section. Do not show "move before" and "move after" controls.
            data.singlesectionmode = 1;
            data.sections[0].contentcollapsed = false;
            // TODO allow to move from collapsed section up level.
        }
        const buildParents = (sections, parents) => {
            for (var i in sections) {
                sections[i].parents = parents;
                sections[i].aftersectionid = (i>0) ? sections[i-1].id : 0;
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
                Templates.render('format_flexsections/local/content/movesection', data).
                then((body) => {
                    modal.setBody(body);
                    this.setupMoveSection(modal, modal.getBody()[0], sectionId, data);
                });
                modal.show();
                return modal;
            });
    }

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
                Templates.render('format_flexsections/local/content/movecm', data).
                then((body) => {
                    modal.setBody(body);
                    this._setupMoveCm(modal, modal.getBody()[0], cmId, data);
                });
                modal.show();
                return modal;
            });
    }

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

    setupMoveSection(modal, modalBody, sectionId, data, element = null) {

        // Disable moving before or after itself or under one of its own children.
        const links = modalBody.querySelectorAll(`${this.selectors.SECTIONLINK}`);
        for (let i = 0; i < links.length; ++i) {
            const re = new RegExp(`,${sectionId},`,"g");
            if (links[i].getAttribute('data-before') === `${sectionId}` || links[i].getAttribute('data-after') === `${sectionId}` ||
                `${links[i].getAttribute('data-parents')},`.match(re)) {
                this._disableLink(links[i]);
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
            if (!target.matches('a') || target.dataset.for !== 'section' || target.dataset.after === undefined) {
                return;
            }
            if (target.getAttribute('aria-disabled')) {
                return;
            }
            event.preventDefault();
            const afterId = parseInt(target.dataset.after);
            const parentId = parseInt(target.dataset.parent);
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

    _requestAddSubSection(target, event) {
        event.preventDefault();
        this.reactive.dispatch('addSubSection', target.dataset.parentid ?? 0, );
    }
}
