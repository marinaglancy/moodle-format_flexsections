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

namespace format_flexsections\local\hooks;

use action_link;
use context_course;
use moodle_url;
use pix_icon;

/**
 * Hook callbacks for format_flexsections
 *
 * @package    format_flexsections
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class before_activitychooserbutton_exported {

    /**
     * This hook is triggered when a activity chooser button is exported.
     *
     * @param \core_course\hook\before_activitychooserbutton_exported $hook
     */
    public static function callback(\core_course\hook\before_activitychooserbutton_exported $hook): void {
        $activitychooserbutton = $hook->get_activitychooserbutton();
        $section = $hook->get_section();
        $cm = $hook->get_cm();
        $format = course_get_format($section->course);

        if ($format->get_format() !== 'flexsections') {
            return;
        }

        // Remove action link added by submodule. Use Reflections to set protected property $activitychooserbutton->actionlinks.
        $refObject   = new \ReflectionObject( $activitychooserbutton );
        $refProperty = $refObject->getProperty( 'actionlinks' );
        $refProperty->setAccessible( true );
        $refProperty->setValue($activitychooserbutton, []);

        // Add the 'Add subsection' action link from format_flexsections.
        // $coursecontext = context_course::instance($section->course);
        // $sectiondepth = $format->get_section_depth($section);
        // if (has_capability('moodle/course:update', $coursecontext) && $section->section &&
        //     $sectiondepth < $format->get_max_section_depth() &&
        //     (!$section->collapsed || $section->section == $format->get_viewed_section())) {

        //     $attributes = [
        //         'class' => 'dropdown-item editing_addsubsection',
        //         'data-action-flexsections' => 'addSubSection',
        //         'data-parentid' => $section->id,
        //     ];
        //     $hook->get_activitychooserbutton()->add_action_link(new action_link(
        //         new moodle_url('#'),
        //         get_string('addsubsection', 'format_flexsections'), // TODO change to "Subsection"
        //         null,
        //         $attributes,
        //         new pix_icon('subsection', '', 'mod_subsection')
        //     ));
        // }
    }
}
