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

/**
 * Contains the default section controls output class.
 *
 * @package   format_flexsections
 * @copyright 2022 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_flexsections\output\courseformat\content\section;

use action_menu;
use action_menu_link_secondary;
use context_course;
use moodle_url;
use pix_icon;
use renderer_base;
use section_info;
use stdClass;

/**
 * Base class to render a course section menu.
 *
 * @package   format_flexsections
 * @copyright 2022 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class controlmenu extends \core_courseformat\output\local\content\section\controlmenu {

    /** @var \format_flexsections the course format class */
    protected $format;

    /** @var section_info the course section class */
    protected $section;

    /**
     * Generate the edit control items of a section.
     *
     * This method must remain public until the final deprecation of section_edit_control_items.
     *
     * @return array of edit control items
     */
    public function section_control_items() {

        $format = $this->format;
        $section = $this->section;
        $course = $format->get_course();
        $sectionreturn = $format->get_section_number();

        $coursecontext = context_course::instance($course->id);

        if ($sectionreturn) {
            $url = course_get_url($course, $section->section);
        } else {
            $url = course_get_url($course);
        }
        $url->param('sesskey', sesskey());

        $controls = [];
        if ($section->section && has_capability('moodle/course:setcurrentsection', $coursecontext)) {
            $markerurl = new \moodle_url($url);
            if ($course->marker == $section->section) {  // Show the "light globe" on/off.
                $markerurl->param('marker', 0);
                $highlightoff = get_string('highlightoff');
                $controls['highlight'] = [
                    'url' => $markerurl,
                    'icon' => 'i/marked',
                    'name' => $highlightoff,
                    'pixattr' => ['class' => ''],
                    'attr' => [
                        'class' => 'editing_highlight',
                        'data-action' => 'removemarker'
                    ],
                ];
            } else {
                $markerurl->param('marker', $section->section);
                $highlight = get_string('highlight');
                $controls['highlight'] = [
                    'url' => $markerurl,
                    'icon' => 'i/marker',
                    'name' => $highlight,
                    'pixattr' => ['class' => ''],
                    'attr' => [
                        'class' => 'editing_highlight',
                        'data-action' => 'setmarker'
                    ],
                ];
            }
        }

        if ($section->section && has_capability('moodle/course:update', $coursecontext) &&
                $section->section != $this->format->get_viewed_section()) {
            $collapseurl = new \moodle_url($url, ['switchcollapsed' => $section->section]);
            $attrs = [
                'data-action-flexsections' => 'sectionSwitchCollapsed',
                'class' => 'editing_collapsed',
                'data-id' => $section->id,
            ];
            if ($section->collapsed == FORMAT_FLEXSECTIONS_COLLAPSED) {
                $controls['collapsed'] = [
                    'url' => $collapseurl,
                    'icon' => 't/expanded',
                    'name' => get_string('showexpanded', 'format_flexsections'),
                    'pixattr' => ['class' => ''],
                    'attr' => $attrs,
                ];
            } else {
                $controls['collapsed'] = [
                    'url' => $collapseurl,
                    'icon' => 't/collapsed',
                    'name' => get_string('showcollapsed', 'format_flexsections'),
                    'pixattr' => ['class' => ''],
                    'attr' => $attrs,
                ];
            }
        }

        if ($section->parent && has_capability('moodle/course:update', $coursecontext) &&
                $section->section != $this->format->get_viewed_section()) {
            $collapseurl = new \moodle_url($url, ['mergeup' => $section->section]);
            $controls['mergeup'] = [
                'url' => $collapseurl,
                'icon' => 'mergeup',
                'iconcomponent' => 'format_flexsections',
                'name' => get_string('mergeup', 'format_flexsections'),
                'pixattr' => ['class' => ''],
                'attr' => [
                    'class' => 'editing_mergeup',
                    'data-action-flexsections' => 'mergeup',
                    'data-id' => $section->id,
                ],
            ];
        }

        if (has_capability('moodle/course:update', $coursecontext) && $section->section &&
                (!$section->collapsed || $section->section != $this->format->get_viewed_section())) {
            $moveurl = new moodle_url('#');
            $controls['moveflexsections'] = [
                'url' => $moveurl,
                'icon' => 'i/dragdrop',
                'name' => get_string('move', 'moodle'),
                'pixattr' => ['class' => ''],
                'attr' => [
                    'data-action-flexsections' => 'moveSection',
                    'data-id' => $section->id,
                    'data-ctxid' => context_course::instance($this->format->get_courseid())->id,
                ],
            ];
        }

        $parentcontrols = parent::section_control_items();
        unset($parentcontrols['movesection'], $parentcontrols['moveup'], $parentcontrols['movedown']);
        if ($section->section == $this->format->get_viewed_section()) {
            // Deleting section that is currently viewed does not really work in AJAX (as well as mergeup).
            // Maybe we re-write it at some moment so it redirects to the parent section.
            unset($parentcontrols['delete']);
        }

        // If the edit key exists, we are going to insert our controls after it.
        if (array_key_exists("edit", $parentcontrols)) {
            $merged = [];
            // We can't use splice because we are using associative arrays.
            // Step through the array and merge the arrays.
            foreach ($parentcontrols as $key => $action) {
                $merged[$key] = $action;
                if ($key == "edit") {
                    // If we have come to the edit key, merge these controls here.
                    $merged = array_merge($merged, $controls);
                }
            }

            return $merged;
        } else {
            return array_merge($controls, $parentcontrols);
        }
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * TODO Almost exact copy of the parent method because iconcomponent can not be specified.
     *
     * @param renderer_base $output typically, the renderer that's calling this function
     * @return array data context for a mustache template
     */
    public function export_for_template(\renderer_base $output): stdClass {

        $section = $this->section;

        $controls = $this->section_control_items();

        if (empty($controls)) {
            return new stdClass();
        }

        // Convert control array into an action_menu.
        $menu = new action_menu();
        $icon = $output->pix_icon('i/menu', get_string('edit'));
        $menu->set_menu_trigger($icon, 'btn btn-icon d-flex align-items-center justify-content-center');
        $menu->attributes['class'] .= ' section-actions';
        foreach ($controls as $value) {
            $url = empty($value['url']) ? '' : $value['url'];
            $icon = empty($value['icon']) ? '' : $value['icon'];
            $name = empty($value['name']) ? '' : $value['name'];
            $attr = empty($value['attr']) ? [] : $value['attr'];
            $class = empty($value['pixattr']['class']) ? '' : $value['pixattr']['class'];
            $al = new action_menu_link_secondary(
                new moodle_url($url),
                new pix_icon($icon, '', $value['iconcomponent'] ?? null, ['class' => "smallicon " . $class]),
                $name,
                $attr
            );
            $menu->add($al);
        }

        $data = (object)[
            'menu' => $output->render($menu),
            'hasmenu' => true,
            'id' => $section->id,
        ];

        return $data;
    }
}
