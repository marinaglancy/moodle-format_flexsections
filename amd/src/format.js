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
 * @copyright  2020 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/templates', 'core/notification', 'core/str'],
    function($, Ajax, Templates, Notification, Str) {
        var confirmAction = function(e, message) {
            // Prevent the default button action.
            e.preventDefault();
            var href = $(e.currentTarget).attr('href');
            Str.get_strings([
                {key: 'confirm', component: 'moodle'},
                {key: message, component: 'format_flexsections'},
                {key: 'yes', component: 'moodle'},
                {key: 'no', component: 'moodle'}
            ]).done(function(strings) {
                Notification.confirm(
                    strings[0], // Confirm.
                    strings[1], // Are you sure you want to delete.
                    strings[2], // Yes.
                    strings[3], // No.
                    function() {
                        window.location.href = href + '&confirm=1';
                    }
                );
            }).fail(Notification.exception);
        };

        return {
            init: function() {
                $('body').on('click', 'li.section > .controls > a[data-action="delete"]', function(e) {
                    confirmAction(e, 'confirmdelete');
                });
                $('body').on('click', 'li.section > .controls > a[data-action="mergeup"]', function(e) {
                    confirmAction(e, 'confirmmerge');
                });
            }
        };
    }
);
