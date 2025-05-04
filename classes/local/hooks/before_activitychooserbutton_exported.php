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
        $format = course_get_format($section->course);

        if ($format->get_format() !== 'flexsections') {
            return;
        }

        // Remove action link added by submodule. Use Reflections to set protected property $activitychooserbutton->actionlinks.
        $refobject = new \ReflectionObject($activitychooserbutton);
        $refproperty = $refobject->getProperty('actionlinks');
        $refproperty->setAccessible(true);
        $actionlinks = $refproperty->getValue($activitychooserbutton);
        $actionlinks = array_filter($actionlinks, fn($a) => ($a->attributes['data-modname'] ?? null) !== 'subsection');
        $refproperty->setValue($activitychooserbutton, array_values($actionlinks));
    }
}
