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
 * Defines renderer for course format flexsections
 *
 * @package    format_flexsections
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/course/format/renderer.php');

/**
 * Renderer for flexsections format.
 *
 * @copyright 2012 Marina Glancy
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_flexsections_renderer extends plugin_renderer_base {
    /** @var core_course_renderer Stores instances of core_course_renderer */
    protected $courserenderer = null;

    /**
     * Constructor
     *
     * @param moodle_page $page
     * @param string $target
     */
    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);
        $this->courserenderer = $page->get_renderer('core', 'course');
    }

    /**
     * Generate the section title (with link if section is collapsed)
     *
     * @param int|section_info $section
     * @param stdClass $course The course entry from DB
     * @param bool $supresslink
     * @return string HTML to output.
     */
    public function section_title($section, $course, $supresslink = false) {
        global $CFG;
        if ((float)$CFG->version >= 2016052300) {
            // For Moodle 3.1 or later use inplace editable for displaying section name.
            $section = course_get_format($course)->get_section($section);
            return $this->render(course_get_format($course)->inplace_editable_render_section_name($section, !$supresslink));
        }
        $title = get_section_name($course, $section);
        if (!$supresslink) {
            $url = course_get_url($course, $section, array('navigation' => true));
            if ($url) {
                $title = html_writer::link($url, $title);
            }
        }
        return $title;
    }

    /**
     * Generate html for a section summary text
     *
     * @param stdClass $section The course_section entry from DB
     * @return string HTML to output.
     */
    protected function format_summary_text($section) {
        $context = context_course::instance($section->course);
        $summarytext = file_rewrite_pluginfile_urls($section->summary, 'pluginfile.php',
            $context->id, 'course', 'section', $section->id);

        $options = new stdClass();
        $options->noclean = true;
        $options->overflowdiv = true;
        return format_text($summarytext, $section->summaryformat, $options);
    }

    /**
     * Display section and all its activities and subsections (called recursively)
     *
     * @param int|stdClass $course
     * @param int|section_info $section
     * @param int $sr section to return to (for building links)
     * @param int $level nested level on the page (in case of 0 also displays additional start/end html code)
     */
    public function display_section($course, $section, $sr, $level = 0) {
        $course = course_get_format($course)->get_course();
        $section = course_get_format($course)->get_section($section);
        $context = context_course::instance($course->id);
        $contentvisible = true;
        if (!$section->uservisible || !course_get_format($course)->is_section_real_available($section)) {
            if ($section->visible && !$section->available && $section->availableinfo) {
                // Still display section but without content.
                $contentvisible = false;
            } else {
                return '';
            }
        }
        $sectionnum = $section->section;
        $movingsection = course_get_format($course)->is_moving_section();
        if ($level === 0) {
            $cancelmovingcontrols = course_get_format($course)->get_edit_controls_cancelmoving();
            foreach ($cancelmovingcontrols as $control) {
                echo $this->render($control);
            }
            echo html_writer::start_tag('ul', array('class' => 'flexsections flexsections-level-0'));
            if ($section->section) {
                $this->display_insert_section_here($course, $section->parent, $section->section, $sr);
            }
        }
        echo html_writer::start_tag('li',
                array('class' => "section main".
                    ($movingsection === $sectionnum ? ' ismoving' : '').
                    (course_get_format($course)->is_section_current($section) ? ' current' : '').
                    (($section->visible && $contentvisible) ? '' : ' hidden'),
                    'id' => 'section-'.$sectionnum));

        // Display controls except for expanded/collapsed.
        /** @var format_flexsections_edit_control[] $controls */
        $controls = course_get_format($course)->get_section_edit_controls($section, $sr);
        $collapsedcontrol = null;
        $controlsstr = '';
        foreach ($controls as $idxcontrol => $control) {
            if ($control->actionname === 'expanded' || $control->actionname === 'collapsed') {
                $collapsedcontrol = $control;
            } else {
                $controlsstr .= $this->render($control);
            }
        }
        if (!empty($controlsstr)) {
            echo html_writer::tag('div', $controlsstr, array('class' => 'controls'));
        }

        // Display section content.
        echo html_writer::start_tag('div', array('class' => 'content'));
        // Display section name and expanded/collapsed control.
        if ($sectionnum && ($title = $this->section_title($sectionnum, $course, ($level == 0) || !$contentvisible))) {
            if ($collapsedcontrol) {
                $title = $this->render($collapsedcontrol). $title;
            }
            echo html_writer::tag('h3', $title, array('class' => 'sectionname'));
        }

        echo $this->section_availability_message($section,
            has_capability('moodle/course:viewhiddensections', $context));

        // Display section description (if needed).
        if ($contentvisible && ($summary = $this->format_summary_text($section))) {
            echo html_writer::tag('div', $summary, array('class' => 'summary'));
        } else {
            echo html_writer::tag('div', '', array('class' => 'summary nosummary'));
        }
        // Display section contents (activities and subsections).
        if ($contentvisible && ($section->collapsed == FORMAT_FLEXSECTIONS_EXPANDED || !$level)) {
            // Display resources and activities.
            echo $this->courserenderer->course_section_cm_list($course, $section, $sr);
            if ($this->page->user_is_editing()) {
                // A little hack to allow use drag&drop for moving activities if the section is empty.
                if (empty(get_fast_modinfo($course)->sections[$sectionnum])) {
                    echo "<ul class=\"section img-text\">\n</ul>\n";
                }
                echo $this->courserenderer->course_section_add_cm_control($course, $sectionnum, $sr);
            }
            // Display subsections.
            $children = course_get_format($course)->get_subsections($sectionnum);
            if (!empty($children) || $movingsection) {
                echo html_writer::start_tag('ul', ['class' => 'flexsections flexsections-level-' . ($level + 1)]);
                foreach ($children as $num) {
                    $this->display_insert_section_here($course, $section, $num, $sr);
                    $this->display_section($course, $num, $sr, $level + 1);
                }
                $this->display_insert_section_here($course, $section, null, $sr);
                echo html_writer::end_tag('ul'); // End of  .flexsections.
            }
            if ($addsectioncontrol = course_get_format($course)->get_add_section_control($sectionnum)) {
                echo $this->render($addsectioncontrol);
            }
        }
        echo html_writer::end_tag('div'); // End of .content .
        echo html_writer::end_tag('li'); // End of .section .
        if ($level === 0) {
            if ($section->section) {
                $this->display_insert_section_here($course, $section->parent, null, $sr);
            }
            echo html_writer::end_tag('ul'); // End of  .flexsections .
        }
    }

    /**
     * Displays the target div for moving section (in 'moving' mode only)
     *
     * @param int|stdClass $courseorid current course
     * @param int|section_info $parent new parent section
     * @param null|int|section_info $before number of section before which we want to insert (or null if in the end)
     * @param null $sr
     */
    protected function display_insert_section_here($courseorid, $parent, $before = null, $sr = null) {
        if ($control = course_get_format($courseorid)->get_edit_control_movehere($parent, $before, $sr)) {
            echo $this->render($control);
        }
    }

    /**
     * Renders HTML for format_flexsections_edit_control
     *
     * @param format_flexsections_edit_control $control
     * @return string
     */
    protected function render_format_flexsections_edit_control(format_flexsections_edit_control $control) {
        if (!$control) {
            return '';
        }
        if ($control->actionname === 'movehere') {
            $icon = new pix_icon('movehere', $control->text, 'moodle',
                ['class' => 'movetarget', 'title' => $control->text]);
            $action = new action_link($control->url, $icon, null, ['data-action' => $control->actionname]);
            return html_writer::tag('li', $this->render($action), ['class' => 'movehere']);
        } else if ($control->actionname === 'cancelmovingsection' || $control->actionname === 'cancelmovingactivity') {
            return html_writer::tag('div', html_writer::link($control->url, $control->text),
                    array('class' => 'cancelmoving '.$control->actionname));
        } else if ($control->actionname === 'addsection') {
            $icon = new pix_icon('t/add', '', 'moodle', ['class' => 'iconsmall']);
            $text = $this->render($icon). html_writer::tag('span', $control->text,
                    ['class' => $control->actionname.'-text']);
            $action = new action_link($control->url, $text, null, ['data-action' => $control->actionname]);
            return html_writer::tag('div', $this->render($action), ['class' => 'mdl-right']);
        } else if ($control->actionname === 'backto') {
            $icon = new pix_icon('t/up', '', 'moodle');
            $text = $this->render($icon). html_writer::tag('span', $control->text,
                    ['class' => $control->actionname.'-text']);
            return html_writer::tag('div', html_writer::link($control->url, $text),
                    array('class' => 'header '.$control->actionname));
        } else if ($control->actionname === 'settings' || $control->actionname === 'marker' ||
                $control->actionname === 'marked') {
            $icon = new pix_icon('i/'. $control->actionname, $control->text, 'moodle',
                ['class' => 'iconsmall', 'title' => $control->text]);
        } else if ($control->actionname === 'move' || $control->actionname === 'expanded' || $control->actionname === 'collapsed'
                || $control->actionname === 'hide' || $control->actionname === 'show' || $control->actionname === 'delete') {
            $icon = new pix_icon('t/'. $control->actionname, $control->text, 'moodle',
                ['class' => 'iconsmall', 'title' => $control->text]);
        } else if ($control->actionname === 'mergeup') {
            $icon = new pix_icon('mergeup', $control->text, 'format_flexsections',
                ['class' => 'iconsmall', 'title' => $control->text]);
        }
        if (isset($icon)) {
            if ($control->url) {
                // Icon with a link.
                $action = new action_link($control->url, $icon, null, ['data-action' => $control->actionname]);
                return $this->render($action);
            } else {
                // Just icon.
                return html_writer::tag('span', $this->render($icon), ['data-action' => $control->actionname]);
            }
        }
        // Unknown control.
        return ' '. html_writer::link($control->url, $control->text, ['data-action' => $control->actionname]). '';
    }

    /**
     * If section is not visible, display the message about that ('Not available
     * until...', that sort of thing). Otherwise, returns blank.
     *
     * For users with the ability to view hidden sections, it shows the
     * information even though you can view the section and also may include
     * slightly fuller information (so that teachers can tell when sections
     * are going to be unavailable etc). This logic is the same as for
     * activities.
     *
     * @param stdClass $section The course_section entry from DB
     * @param bool $canviewhidden True if user can view hidden sections
     * @return string HTML to output
     */
    protected function section_availability_message($section, $canviewhidden) {
        global $CFG;
        $o = '';
        if (!$section->visible) {
            if ($canviewhidden) {
                $o .= $this->courserenderer->availability_info(get_string('hiddenfromstudents'), 'ishidden');
            } else {
                // We are here because of the setting "Hidden sections are shown in collapsed form".
                // Student can not see the section contents but can see its name.
                $o .= $this->courserenderer->availability_info(get_string('notavailable'), 'ishidden');
            }
        } else if (!$section->uservisible) {
            if ($section->availableinfo) {
                // Note: We only get to this function if availableinfo is non-empty,
                // so there is definitely something to print.
                $formattedinfo = \core_availability\info::format_info(
                    $section->availableinfo, $section->course);
                $o .= $this->courserenderer->availability_info($formattedinfo, 'isrestricted');
            }
        } else if ($canviewhidden && !empty($CFG->enableavailability)) {
            // Check if there is an availability restriction.
            $ci = new \core_availability\info_section($section);
            $fullinfo = $ci->get_full_information();
            if ($fullinfo) {
                $formattedinfo = \core_availability\info::format_info(
                    $fullinfo, $section->course);
                $o .= $this->courserenderer->availability_info($formattedinfo, 'isrestricted isfullinfo');
            }
        }
        return $o;
    }

    /**
     * Displays a confirmation dialogue when deleting the section (for non-JS mode)
     *
     * @param stdClass $course
     * @param int $sectionreturn
     * @param int $deletesection
     */
    public function confirm_delete_section($course, $sectionreturn, $deletesection) {
        echo $this->box_start('noticebox');
        $courseurl = course_get_url($course, $sectionreturn);
        $optionsyes = array('confirm' => 1, 'deletesection' => $deletesection, 'sesskey' => sesskey());
        $formcontinue = new single_button(new moodle_url($courseurl, $optionsyes), get_string('yes'));
        $formcancel = new single_button($courseurl, get_string('no'), 'get');
        echo $this->confirm(get_string('confirmdelete', 'format_flexsections'), $formcontinue, $formcancel);
        echo $this->box_end();
    }
}
