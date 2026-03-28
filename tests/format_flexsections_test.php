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

namespace format_flexsections;

use context_course;
use core_external;
use core_external\external_api;
use moodle_exception;
use moodle_url;
use testable_course_edit_form;

/**
 * Flexible sections course format related unit tests.
 *
 * @package    format_flexsections
 * @copyright  2022 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_flexsections
 */
final class format_flexsections_test extends \advanced_testcase {
    /**
     * Shared setup for the testcase.
     */
    public function setUp(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        parent::setUp();
    }

    /**
     * Tests for format_flexsections::get_section_name method with default section names.
     */
    public function test_get_section_name(): void {
        global $DB;
        $this->resetAfterTest(true);

        // Generate a course with 5 sections.
        $generator = $this->getDataGenerator();
        $numsections = 5;
        $course = $generator->create_course(
            ['numsections' => $numsections, 'format' => 'flexsections'],
            ['createsections' => true]
        );

        // Get section names for course.
        $coursesections = $DB->get_records('course_sections', ['course' => $course->id]);

        // Test get_section_name with default section names.
        $courseformat = course_get_format($course);
        foreach ($coursesections as $section) {
            // Assert that with unmodified section names, get_section_name returns the same result as get_default_section_name.
            $this->assertEquals($courseformat->get_default_section_name($section), $courseformat->get_section_name($section));
        }
    }

    /**
     * Tests for format_flexsections::get_section_name method with modified section names.
     *
     * @return void
     */
    public function test_get_section_name_customised(): void {
        global $DB;
        $this->resetAfterTest(true);

        // Generate a course with 5 sections.
        $generator = $this->getDataGenerator();
        $numsections = 5;
        $course = $generator->create_course(
            ['numsections' => $numsections, 'format' => 'flexsections'],
            ['createsections' => true]
        );

        // Get section names for course.
        $coursesections = $DB->get_records('course_sections', ['course' => $course->id]);

        // Modify section names.
        $customname = "Custom Section";
        foreach ($coursesections as $section) {
            $section->name = "$customname $section->section";
            $DB->update_record('course_sections', $section);
        }

        // Requery updated section names then test get_section_name.
        $coursesections = $DB->get_records('course_sections', ['course' => $course->id]);
        $courseformat = course_get_format($course);
        foreach ($coursesections as $section) {
            // Assert that with modified section names, get_section_name returns the modified section name.
            $this->assertEquals($section->name, $courseformat->get_section_name($section));
        }
    }

    /**
     * Tests for format_flexsections::get_default_section_name.
     *
     * @return void
     */
    public function test_get_default_section_name(): void {
        global $DB;
        $this->resetAfterTest(true);

        // Generate a course with 5 sections.
        $generator = $this->getDataGenerator();
        $numsections = 5;
        $course = $generator->create_course(
            ['numsections' => $numsections, 'format' => 'flexsections'],
            ['createsections' => true]
        );

        // Get section names for course.
        $coursesections = $DB->get_records('course_sections', ['course' => $course->id]);

        // Test get_default_section_name with default section names.
        $courseformat = course_get_format($course);
        foreach ($coursesections as $section) {
            if ($section->section == 0) {
                $sectionname = get_string('section0name', 'format_flexsections');
                $this->assertEquals($sectionname, $courseformat->get_default_section_name($section));
            } else {
                $sectionname = get_string('sectionname', 'format_flexsections') . ' ' . $section->section;
                $this->assertEquals($sectionname, $courseformat->get_default_section_name($section));
            }
        }
    }

    /**
     * Test web service updating section name.
     *
     * @return void
     */
    public function test_update_inplace_editable(): void {
        global $CFG, $DB, $PAGE;
        require_once($CFG->dirroot . '/lib/external/externallib.php');

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $course = $this->getDataGenerator()->create_course(
            ['numsections' => 5, 'format' => 'flexsections'],
            ['createsections' => true]
        );
        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 2]);

        // Call webservice without necessary permissions.
        try {
            core_external::update_inplace_editable('format_flexsections', 'sectionname', $section->id, 'New section name');
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals(
                'Course or activity not accessible. (Not enrolled)',
                $e->getMessage()
            );
        }

        // Change to teacher and make sure that section name can be updated using web service update_inplace_editable().
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $teacherrole->id);

        $res = core_external::update_inplace_editable('format_flexsections', 'sectionname', $section->id, 'New section name');
        $res = external_api::clean_returnvalue(core_external::update_inplace_editable_returns(), $res);
        $this->assertEquals('New section name', $res['value']);
        $this->assertEquals('New section name', $DB->get_field('course_sections', 'name', ['id' => $section->id]));
    }

    /**
     * Test callback updating section name.
     *
     * @return void
     */
    public function test_inplace_editable(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course(
            ['numsections' => 5, 'format' => 'flexsections'],
            ['createsections' => true]
        );
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $teacherrole->id);
        $this->setUser($user);

        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 2]);

        // Call callback format_flexsections_inplace_editable() directly.
        $tmpl = component_callback(
            'format_flexsections',
            'inplace_editable',
            ['sectionname', $section->id, 'Rename me again']
        );
        $this->assertInstanceOf('core\output\inplace_editable', $tmpl);
        $res = $tmpl->export_for_template($PAGE->get_renderer('core'));
        $this->assertEquals('Rename me again', $res['value']);
        $this->assertEquals('Rename me again', $DB->get_field('course_sections', 'name', ['id' => $section->id]));

        // Try updating using callback from mismatching course format.
        try {
            component_callback('format_weeks', 'inplace_editable', ['sectionname', $section->id, 'New name']);
            $this->fail('Exception expected');
        } catch (moodle_exception $e) {
            $this->assertEquals(1, preg_match('/^Can\'t find data record in database/', $e->getMessage()));
        }
    }

    /**
     * Test get_default_course_enddate.
     *
     * @return void
     */
    public function test_default_course_enddate(): void {
        global $CFG, $DB;

        $this->resetAfterTest(true);

        require_once($CFG->dirroot . '/course/tests/fixtures/testable_course_edit_form.php');

        $this->setTimezone('UTC');

        $params = ['format' => 'flexsections', 'numsections' => 5, 'startdate' => 1445644800];
        $course = $this->getDataGenerator()->create_course($params);
        $category = $DB->get_record('course_categories', ['id' => $course->category]);

        $args = [
            'course' => $course,
            'category' => $category,
            'editoroptions' => [
                'context' => context_course::instance($course->id),
                'subdirs' => 0,
            ],
            'returnto' => new moodle_url('/'),
            'returnurl' => new moodle_url('/'),
        ];

        $courseform = new testable_course_edit_form(null, $args);
        $courseform->definition_after_data();

        $enddate = $params['startdate'] + get_config('moodlecourse', 'courseduration');

        $format = course_get_format($course->id);
        $this->assertEquals($enddate, $format->get_default_course_enddate($courseform->get_quick_form()));
    }

    /**
     * Test for get_view_url() to ensure that the url is only given for the correct cases.
     *
     * @return void
     */
    public function test_get_view_url(): void {
        global $CFG;
        $this->resetAfterTest();

        // Generate a course with two sections (0 and 1) and two modules.
        $generator = $this->getDataGenerator();
        $course1 = $generator->create_course(['format' => 'flexsections']);
        course_create_sections_if_missing($course1, [0, 1]);

        $data = (object)['id' => $course1->id];
        /** @var \format_flexsections $format */
        $format = course_get_format($course1);
        $format->update_course_format_options($data);

        $this->assertNotEmpty($format->get_view_url(null));
        $this->assertNotEmpty($format->get_view_url(0));
        $this->assertNotEmpty($format->get_view_url(1));
    }

    /**
     * Test get_view_url() with null section when section number is set.
     *
     * Regression test for https://github.com/marinaglancy/moodle-format_flexsections/issues/109
     * When viewing a single section page and get_view_url is called with null,
     * it should not produce a warning about reading property on null.
     *
     * @return void
     */
    public function test_get_view_url_with_section_number_set(): void {
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(['format' => 'flexsections']);
        course_create_sections_if_missing($course, [0, 1]);

        /** @var \format_flexsections $format */
        $format = course_get_format($course);

        // Simulate being on a single section page by setting the section number.
        $format->set_sectionnum(1);

        // This should not produce a PHP warning "Attempt to read property 'id' on null".
        $url = $format->get_view_url(null);
        $this->assertNotEmpty($url);

        // Also test with navigation option.
        $url = $format->get_view_url(null, ['navigation' => true]);
        $this->assertTrue($url === null || $url instanceof moodle_url);
    }

    /**
     * Tests for format_flexsections::delete_section_with_children.
     */
    public function test_delete_section_with_children(): void {
        global $DB;
        $this->resetAfterTest(true);

        // Generate a course with 5 sections.
        $generator = $this->getDataGenerator();
        $numsections = 5;
        $course = $generator->create_course(
            ['numsections' => $numsections, 'format' => 'flexsections'],
            ['createsections' => true]
        );

        // Get last section.
        $courseformat = course_get_format($course);
        $sections = $courseformat->get_sections();
        $lastsection = array_pop($sections);

        // Create 2 subsections.
        $this->assertEquals(6, $courseformat->create_new_section($lastsection->section));
        $this->assertEquals(7, $courseformat->create_new_section($lastsection->section));
        // Sanity check, expect 8 sections in total (section 0-7).
        $this->assertCount(8, $courseformat->get_sections());

        // Delete last top section, this should delete 3 sections in total.
        $sink = $this->redirectEvents();
        $courseformat->delete_section_with_children($lastsection);

        // Check events.
        $events = $sink->get_events();
        $this->assertCount(3, $events);
        foreach ($events as $event) {
            $this->assertInstanceOf('\core\event\course_section_deleted', $event);
            $this->assertContains((int) $event->get_data()['other']['sectionnum'], [5, 6, 7]);
        }
        // Sanity check, expect 5 sections in total (section 0-4).
        $this->assertCount(5, $courseformat->get_sections());
    }

    /**
     * Test that hiding a section via stateactions also hides subsections recursively.
     *
     * Regression test for https://github.com/marinaglancy/moodle-format_flexsections/issues/107
     * When a section containing subsections is hidden using the AJAX action (section_hide),
     * activities in subsections must also become hidden from students.
     */
    public function test_section_hide_recursive(): void {
        global $DB;
        $this->resetAfterTest(true);

        $generator = $this->getDataGenerator();
        $course = $generator->create_course(
            ['numsections' => 3, 'format' => 'flexsections'],
            ['createsections' => true]
        );
        $user = $generator->create_user();
        $teacherrole = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $generator->enrol_user($user->id, $course->id, $teacherrole->id);
        $this->setUser($user);

        /** @var \format_flexsections $format */
        $format = course_get_format($course);

        // Create subsection under section 1, and sub-subsection under that.
        $subsectionnum = $format->create_new_section(1);
        $subsubsectionnum = $format->create_new_section($subsectionnum);

        // Add activities to each level.
        $generator->create_module('assign', ['course' => $course->id, 'section' => 1]);
        $generator->create_module('forum', ['course' => $course->id, 'section' => $subsectionnum]);
        $generator->create_module('page', ['course' => $course->id, 'section' => $subsubsectionnum]);

        // Get section info.
        $modinfo = get_fast_modinfo($course);
        $section1 = $modinfo->get_section_info(1);
        $subsection = $modinfo->get_section_info($subsectionnum);
        $subsubsection = $modinfo->get_section_info($subsubsectionnum);

        // Verify parent hierarchy is correct.
        $this->assertEquals(1, $subsection->parent, 'Subsection parent should be section 1');
        $this->assertEquals($subsectionnum, $subsubsection->parent, 'Sub-subsection parent should be subsection');

        // Verify all sections are visible initially.
        $this->assertEquals(1, $section1->visible);
        $this->assertEquals(1, $subsection->visible);
        $this->assertEquals(1, $subsubsection->visible);

        // Hide section 1 via stateactions (simulating the AJAX hide action).
        $updates = new \core_courseformat\stateupdates($format);
        $actions = $format->get_stateactions_instance();
        $actions->section_hide($updates, $course, [$section1->id]);

        // Verify that section 1 and all its subsections are now hidden.
        $modinfo = get_fast_modinfo($course);
        $this->assertEquals(0, $modinfo->get_section_info(1)->visible, 'Section 1 should be hidden');
        $this->assertEquals(0, $modinfo->get_section_info($subsectionnum)->visible, 'Subsection should be hidden');
        $this->assertEquals(0, $modinfo->get_section_info($subsubsectionnum)->visible, 'Sub-subsection should be hidden');

        // Now show section 1 and verify subsections become visible again.
        $updates = new \core_courseformat\stateupdates($format);
        $actions = $format->get_stateactions_instance();
        $actions->section_show($updates, $course, [$section1->id]);

        $modinfo = get_fast_modinfo($course);
        $this->assertEquals(1, $modinfo->get_section_info(1)->visible, 'Section 1 should be visible');
        $this->assertEquals(1, $modinfo->get_section_info($subsectionnum)->visible, 'Subsection should be visible');
        $this->assertEquals(1, $modinfo->get_section_info($subsubsectionnum)->visible, 'Sub-subsection should be visible');
    }
}
