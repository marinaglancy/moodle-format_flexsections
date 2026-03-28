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

namespace format_flexsections\local\hooks\output;

/**
 * Hook callbacks for format_flexsections
 *
 * @package    format_flexsections
 * @copyright  2024 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class before_footer_html_generation {
    /**
     * Callback allowing to add contetnt inside the region-main, in the very end
     *
     * If we are on activity page, add the "Back to section" link
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function callback(\core\hook\output\before_footer_html_generation $hook): void {
        global $OUTPUT, $CFG;
        require_once($CFG->dirroot . '/course/format/flexsections/lib.php');

        if (during_initial_install() || isset($CFG->upgraderunning)) {
            // Do nothing during installation or upgrade.
            return;
        }

        if ($cm = format_flexsections_add_back_link_to_cm()) {
            $hook->add_html($OUTPUT->render_from_template('format_flexsections/back_link_in_cms', [
                'backtosection' => [
                    'url' => course_get_url($cm->course, $cm->sectionnum)->out(false),
                    'sectionname' => get_section_name($cm->course, $cm->sectionnum),
                ],
            ]));
        }
    }
}
