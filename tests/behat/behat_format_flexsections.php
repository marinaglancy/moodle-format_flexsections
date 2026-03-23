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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Behat steps in plugin format_flexsections
 *
 * @package    format_flexsections
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_format_flexsections extends behat_base {
    /**
     * Opens the activity chooser and opens the activity/resource link form page. Sections 0 and 1 are also allowed on frontpage.
     *
     * This step require javascript enabled and it is used mainly to click activities or resources by name,
     * not by plugin name. Use the standard behat_course::i_add_to_course_section step instead unless the
     * plugin create extra entries into the activity chooser (like LTI).
     *
     * @Given I add a :activityname to section :sectionnum in flexsections using the activity chooser
     * @Given I add an :activityname to section :sectionnum in flexsections using the activity chooser
     * @param string $activityname
     * @param int $sectionnum
     */
    public function i_add_to_section_in_flexsections_using_the_activity_chooser($activityname, $sectionnum) {
        global $CFG;

        $this->require_javascript('Please use the \'the following "activity" exists:\' data generator instead.');

        $sectionxpath = "//li[@id='section-" . $sectionnum . "']";

        // Clicks add activity or resource section link.
        $sectionnode = $this->find('xpath', $sectionxpath);
        $this->execute('behat_general::i_click_on_in_the', [
            "//button[@data-action='open-chooser' and not(@data-beforemod)]",
            'xpath',
            $sectionnode,
            'NodeElement',
        ]);

        if ($CFG->branch >= 501) {
            // Clicks the selected activity if it exists.
            $activityliteral = behat_context_helper::escape(ucfirst($activityname));
            $activityxpath = "//div[contains(concat(' ', normalize-space(@class), ' '), ' modchooser ')]" .
                "/descendant::*[contains(concat(' ', normalize-space(@class), ' '), ' optioninfo ')]" .
                "/descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' optionname ')]" .
                "[normalize-space(.)=$activityliteral]" .
                "/parent::a";

            $this->execute('behat_general::i_click_on', [$activityxpath, 'xpath']);

            $this->execute('behat_general::i_click_on_in_the', [
                "Add selected activity",
                'button',
                "Add an activity or resource",
                'dialogue',
            ]);
        } else {
            // Clicks the selected activity if it exists.
            $activityliteral = behat_context_helper::escape(ucfirst($activityname));
            $activityxpath = "//div[contains(concat(' ', normalize-space(@class), ' '), ' modchooser ')]" .
                "/descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' optioninfo ')]" .
                "/descendant::div[contains(concat(' ', normalize-space(@class), ' '), ' optionname ')]" .
                "[normalize-space(.)=$activityliteral]" .
                "/parent::a";

            $this->execute('behat_general::i_click_on', [$activityxpath, 'xpath']);
        }
    }
}
