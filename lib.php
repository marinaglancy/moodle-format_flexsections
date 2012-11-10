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
 *
 *
 * @package    format_flexsections
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/course/format/formatlegacy.php');

/**
 * Format Flexsections base class
 *
 * @package    format_flexsections
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_flexsections extends format_legacy {
    public function section_format_options($foreditform = false) {
        return array(
            'sectionfield1' => array(
                'type' => PARAM_TEXT,
                'label' => 'THIS IS THE CUSTOM FIELD1',
                'element_type' => 'text',
            ),
            'sectionfield12' => array(
                'type' => PARAM_TEXT,
                'label' => 'THIS IS THE CUSTOM FIELD2',
                'element_type' => 'text',
            )
        );
    }

    public function course_header() {
        return new format_flexsections_courseobj('This is the course header', 'DDFFFF');
    }

    public function course_footer() {
        return new format_flexsections_courseobj('This is the course footer', 'DDFFFF');
    }

    public function course_content_header() {
        return new format_flexsections_courseobj('This is the course content header', 'DDDDDD');
    }

    public function course_content_footer() {
        return new format_flexsections_courseobj('This is the course content footer', 'DDDDDD');
    }
}

/**
 * Class storing information to be displayed in course header/footer
 * 
 * @package    format_flexsections
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_flexsections_courseobj implements renderable {
    public $background;
    public $text;
    public function __construct($text, $background) {
        $this->text = $text;
        $this->background = $background;
    }
}

/**
 * Indicates this format uses sections.
 *
 * @return bool Returns true
 */
function callback_flexsections_uses_sections() {
    return true;
}

/**
 * Used to display the course structure for a course where format=topic
 *
 * This is called automatically by {@link load_course()} if the current course
 * format = weeks.
 *
 * @param array $path An array of keys to the course node in the navigation
 * @param stdClass $modinfo The mod info object for the current course
 * @return bool Returns true
 */
function callback_flexsections_load_content(&$navigation, $course, $coursenode) {
    return $navigation->load_generic_course_sections($course, $coursenode);
}

/**
 * The string that is used to describe a section of the course
 * e.g. Topic, Week...
 *
 * @return string
 */
function callback_flexsections_definition() {
    return get_string('flexsections');
}

function callback_flexsections_get_section_name($course, $section) {
    // We can't add a node without any text
    if ((string)$section->name !== '') {
        return format_string($section->name, true, array('context' => context_course::instance($course->id)));
    } else if ($section->section == 0) {
        return get_string('section0name', 'format_flexsections');
    } else {
        return get_string('topic').' '.$section->section;
    }
}

/**
 * Declares support for course AJAX features
 *
 * @see course_format_ajax_support()
 * @return stdClass
 */
function callback_flexsections_ajax_support() {
    $ajaxsupport = new stdClass();
    $ajaxsupport->capable = true;
    $ajaxsupport->testedbrowsers = array('MSIE' => 6.0, 'Gecko' => 20061111, 'Safari' => 531, 'Chrome' => 6.0);
    return $ajaxsupport;
}

/**
 * Callback function to do some action after section move
 *
 * @param stdClass $course The course entry from DB
 * @return array This will be passed in ajax respose.
 */
function callback_flexsections_ajax_section_move($course) {
    $titles = array();
    rebuild_course_cache($course->id);
    $modinfo = get_fast_modinfo($course);
    $renderer = $PAGE->get_renderer('format_flexsections');
    if ($renderer && ($sections = $modinfo->get_section_info_all())) {
        foreach ($sections as $number => $section) {
            $titles[$number] = $renderer->section_title($section, $course);
        }
    }
    return array('sectiontitles' => $titles, 'action' => 'move');
}
