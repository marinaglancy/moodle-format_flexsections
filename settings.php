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
 * Settings for format_flexsections
 *
 * @package    format_flexsections
 * @copyright  2023 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $url = new moodle_url('/admin/course/resetindentation.php', ['format' => 'flexsections']);
    $link = html_writer::link($url, get_string('resetindentation', 'admin'));
    $settings->add(new admin_setting_configcheckbox(
        'format_flexsections/indentation',
        new lang_string('indentation', 'format_topics'),
        new lang_string('indentation_help', 'format_topics').'<br />'.$link,
        1
    ));
    $settings->add(new admin_setting_configtext('format_flexsections/maxsubsections',
        get_string('maxsubsections', 'format_flexsections'),
        get_string('maxsubsectionsdesc', 'format_flexsections'), 10, PARAM_INT, 7));
}
