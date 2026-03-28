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

namespace format_flexsections\local\helpers;
use format_flexsections;

/**
 * Tests for Flexible sections format
 *
 * @covers     \format_flexsections\local\helpers\preferences
 * @package    format_flexsections
 * @category   test
 * @copyright  Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class preferences_test extends \advanced_testcase {
    public function setUp(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        parent::setUp();
    }

    public function test_long_preferences(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        format_flexsections::set_long_preference('name', 'value');
        $this->assertEquals('value', format_flexsections::get_long_preference('name'));
        $preferences = get_user_preferences();
        $this->assertArrayHasKey('name', $preferences);
        $this->assertArrayNotHasKey('name#1', $preferences);

        $longpref = random_string(2000);
        format_flexsections::set_long_preference('name', $longpref);
        $this->assertEquals($longpref, format_flexsections::get_long_preference('name'));
        $preferences = get_user_preferences();
        $this->assertArrayHasKey('name', $preferences);
        $this->assertArrayHasKey('name#1', $preferences);
        $this->assertArrayNotHasKey('name#2', $preferences);

        $verylongpref = random_string(1300 * 5 + 500);
        format_flexsections::set_long_preference('name', $verylongpref);
        $this->assertEquals($verylongpref, format_flexsections::get_long_preference('name'));
        $preferences = get_user_preferences();
        $this->assertArrayHasKey('name', $preferences);
        $this->assertArrayHasKey('name#1', $preferences);
        $this->assertArrayHasKey('name#2', $preferences);
        $this->assertArrayHasKey('name#3', $preferences);
        $this->assertArrayHasKey('name#4', $preferences);
        $this->assertArrayHasKey('name#5', $preferences);
        $this->assertArrayNotHasKey('name#6', $preferences);

        format_flexsections::set_long_preference('name', 'value again');
        $this->assertEquals('value again', format_flexsections::get_long_preference('name'));
        $preferences = get_user_preferences();
        $this->assertArrayHasKey('name', $preferences);
        $this->assertArrayNotHasKey('name#1', $preferences);

        format_flexsections::set_long_preference('name', null);
        $this->assertEquals('', format_flexsections::get_long_preference('name'));
        $preferences = get_user_preferences();
        $this->assertArrayNotHasKey('name', $preferences);
    }

    /**
     * Test for the default delete format data behaviour.
     *
     * @covers ::set_sections_preference
     */
    public function test_set_sections_preference(): void {
        $this->resetAfterTest();
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(
            ['numsections' => 200, 'format' => 'flexsections'],
            ['createsections' => true]
        );
        $user = $generator->create_and_enrol($course, 'student');

        $format = course_get_format($course);
        $this->setUser($user);

        // Load data from user 1.
        $format->set_sections_preference('pref1', [1, 2]);
        $format->set_sections_preference('pref2', [1]);
        $format->set_sections_preference('pref3', []);

        $preferences = $format->get_sections_preferences();
        $this->assertEquals(
            (object)['pref1' => true, 'pref2' => true],
            $preferences[1]
        );
        $this->assertEquals(
            (object)['pref1' => true],
            $preferences[2]
        );
    }

    public function test_add_section_preference_ids(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(
            ['numsections' => 200, 'format' => 'flexsections'],
            ['createsections' => true]
        );
        $user = $this->getDataGenerator()->create_and_enrol($course);
        $this->setUser($user);

        $format = course_get_format($course);

        // Add section preference ids.
        $format->add_section_preference_ids('pref1', [1, 2]);
        $format->add_section_preference_ids('pref1', [3]);
        $format->add_section_preference_ids('pref2', [1]);

        // Get section preferences.
        $sectionpreferences = $format->get_sections_preferences_by_preference();
        $this->assertCount(3, $sectionpreferences['pref1']);
        $this->assertContains(1, $sectionpreferences['pref1']);
        $this->assertContains(2, $sectionpreferences['pref1']);
        $this->assertContains(3, $sectionpreferences['pref1']);
        $this->assertCount(1, $sectionpreferences['pref2']);
        $this->assertContains(1, $sectionpreferences['pref1']);
    }

    public function test_add_section_preference_ids_long(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course(
            ['numsections' => 500, 'format' => 'flexsections'],
            ['createsections' => true]
        );
        $user = $this->getDataGenerator()->create_and_enrol($course);
        $this->setUser($user);

        $format = course_get_format($course);

        // Add section preference ids.
        $vals = array_keys(array_fill(0, 500, true));
        $format->add_section_preference_ids('contentcollapsed', $vals);

        // Get section preferences.
        $sectionpreferences = $format->get_sections_preferences_by_preference();
        $this->assertCount(500, $sectionpreferences['contentcollapsed']);
    }

    /**
     * Test remove_section_preference_ids() method.
     *
     * @covers \core_courseformat\base::persist_to_user_preference
     */
    public function test_remove_section_preference_ids(): void {
        $this->resetAfterTest();
        // Create initial data.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(
            ['numsections' => 200, 'format' => 'flexsections'],
            ['createsections' => true]
        );
        $user = $generator->create_and_enrol($course);
        // Get the course format.
        $format = course_get_format($course);
        // Login as the user.
        $this->setUser($user);
        // Set initial preferences.
        $format->set_sections_preference('pref1', [1, 2, 3]);
        $format->set_sections_preference('pref2', [1]);

        // Remove section with id = 3 out of the pref1.
        $format->remove_section_preference_ids('pref1', [3]);
        // Get section preferences.
        $sectionpreferences = $format->get_sections_preferences_by_preference();
        $this->assertCount(2, $sectionpreferences['pref1']);
        $this->assertCount(1, $sectionpreferences['pref2']);

        // Remove section with id = 2 out of the pref1.
        $format->remove_section_preference_ids('pref1', [2]);
        // Remove section with id = 1 out of the pref2.
        $format->remove_section_preference_ids('pref2', [1]);
        // Get section preferences.
        $sectionpreferences = $format->get_sections_preferences_by_preference();
        $this->assertCount(1, $sectionpreferences['pref1']);
        $this->assertEmpty($sectionpreferences['pref2']);
    }
}
