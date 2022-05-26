<?php
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

namespace format_flexsections\output\courseformat\content\cm;

use action_menu;
use action_menu_link;
use action_menu_link_secondary;
use cm_info;
use core\output\named_templatable;
use core_courseformat\base as course_format;
use core_courseformat\output\local\courseformat_named_templatable;
use moodle_url;
use pix_icon;
use renderable;
use section_info;
use stdClass;

/**
 * Class to render a course module menu inside a course format.
 *
 * @package   format_flexsections
 * @copyright 2022 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class controlmenu extends \core_courseformat\output\local\content\cm\controlmenu {

    /** @var \format_flexsections the course format */
    protected $format;

    /**
     * Generate the edit control items of a course module.
     *
     * This method uses course_get_cm_edit_actions function to get the cm actions.
     * However, format plugins can override the method to add or remove elements
     * from the menu.
     *
     * @return array of edit control items
     */
    protected function cm_control_items() {
        $actions = parent::cm_control_items();

        $baseurl = new moodle_url('/course/mod.php', array('sesskey' => sesskey()));
        $sr = $this->format->get_section_number();
        $mod = $this->mod;

        if ($sr !== null) {
            $baseurl->param('sr', $sr);
        }

        if (isset($actions['move'])) {
            $actions['move'] = new action_menu_link_secondary(
                new moodle_url($baseurl, ['sesskey' => sesskey(), 'copy' => $mod->id]),
                new pix_icon('i/dragdrop', '', 'moodle', ['class' => 'iconsmall']),
                get_string('move', 'moodle'),
                [
                    'class' => 'editing_movecm',
                    'data-action-flexsections' => 'moveCm',
                    'data-id' => $mod->id,
                ]
            );
        }

        return $actions;
    }
}
