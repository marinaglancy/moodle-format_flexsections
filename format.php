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
 * Included from course/view.php when course is in flexsections format
 *
 * @package    format_flexsections
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/completionlib.php');

$context = context_course::instance($course->id);

if (($marker >= 0) && has_capability('moodle/course:setcurrentsection', $context) && confirm_sesskey()) {
    $course->marker = $marker;
    course_set_marker($course->id, $marker);
}

// Make sure section 0 is created.
course_create_sections_if_missing($course, 0);

$renderer = $PAGE->get_renderer('format_flexsections');
if (($deletesection = optional_param('deletesection', 0, PARAM_INT)) && confirm_sesskey()) {
    $renderer->confirm_delete_section($course, $displaysection, $deletesection);
} else {
    $renderer->display_section($course, $displaysection, $displaysection);
}

// Include course format js module.
$PAGE->requires->js('/course/format/flexsections/format.js');
$PAGE->requires->js_call_amd('format_flexsections/format', 'init');
