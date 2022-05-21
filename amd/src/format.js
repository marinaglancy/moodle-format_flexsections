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
import {get_strings as getStrings} from 'core/str';

const SELECTORS = {
    SECTION_MERGEUP: 'li.section a[data-action-flexsections="mergeup"]'
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
