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

use backup;
use backup_controller;
use restore_controller;

/**
 * Backup and restore tests for flexible sections course format.
 *
 * @package    format_flexsections
 * @copyright  2026 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_flexsections
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Shared setup for the testcase.
     */
    public function setUp(): void {
        global $CFG;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        parent::setUp();
    }

    /**
     * Test that partial course import does not create orphan sections.
     *
     * Regression test for https://github.com/marinaglancy/moodle-format_flexsections/issues/17
     * When importing a flexsections course and excluding a subsection, the excluded subsection
     * should not appear as a new top-level "Topic N" section in the target course.
     */
    public function test_partial_import_no_orphan_sections(): void {
        global $CFG, $DB, $USER;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator();

        // Create source course with flexsections format: section 1 with subsections.
        $course1 = $generator->create_course(
            ['numsections' => 1, 'format' => 'flexsections', 'shortname' => 'source'],
            ['createsections' => true]
        );
        /** @var \format_flexsections $format */
        $format = course_get_format($course1);

        // Create subsections: C1.1 and C1.2 under section 1.
        $sub1num = $format->create_new_section(1);
        $sub2num = $format->create_new_section(1);

        // Add an activity to each subsection.
        $generator->create_module('forum', ['course' => $course1->id, 'section' => $sub1num]);
        $generator->create_module('page', ['course' => $course1->id, 'section' => $sub2num]);

        // Verify source structure: 4 sections (0, 1, sub1, sub2).
        $modinfo = get_fast_modinfo($course1);
        $allsections = $modinfo->get_section_info_all();
        $this->assertCount(4, $allsections);

        // Get section IDs for use in backup settings.
        $sub2info = $modinfo->get_section_info($sub2num);
        $sub2id = $sub2info->id;

        // Backup the source course.
        $CFG->backup_file_logger_level = backup::LOG_NONE;
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course1->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Create empty target course.
        $course2 = $generator->create_course(
            ['numsections' => 0, 'format' => 'flexsections', 'shortname' => 'target'],
            ['createsections' => true]
        );

        // Restore to target course, excluding sub2.
        $rc = new restore_controller(
            $backupid,
            $course2->id,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id,
            backup::TARGET_CURRENT_ADDING
        );

        // Exclude sub2 from restore.
        $settingname = 'section_' . $sub2id . '_included';
        if ($rc->get_plan()->setting_exists($settingname)) {
            $rc->get_plan()->get_setting($settingname)->set_value(false);
        }

        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        // Verify target course structure.
        $modinfo2 = get_fast_modinfo($course2);
        $allsections2 = $modinfo2->get_section_info_all();

        // We should have: section 0, section 1 (imported), sub1 (imported).
        // We should NOT have sub2 or any orphan "Topic N" section.
        // Count sections that have parent=0 and are not section 0 — these are top-level sections.
        $toplevel = [];
        $subsections = [];
        foreach ($allsections2 as $s) {
            if ($s->section == 0) {
                continue;
            }
            if ($s->parent == 0) {
                $toplevel[] = $s;
            } else {
                $subsections[] = $s;
            }
        }

        // There should be exactly 1 top-level section (the imported section 1).
        $this->assertCount(
            1,
            $toplevel,
            'There should be exactly 1 top-level section, but found ' . count($toplevel) .
            '. Extra sections indicate orphan "Topic N" sections created during partial import.'
        );

        // There should be exactly 1 subsection (sub1, since sub2 was excluded).
        $this->assertCount(
            1,
            $subsections,
            'There should be exactly 1 subsection (sub2 was excluded), but found ' . count($subsections)
        );
    }

    /**
     * Test that partial import with named sections does not leave numbering gaps.
     *
     * Reproduces the exact scenario from issue #17: a course with "Chapter 1" containing
     * named subsections C1.1, C1.2, C1.3. When C1.2 is excluded during import, the
     * resulting course should not have a gap in section numbering that gets filled
     * with a "Topic N" section.
     */
    public function test_partial_import_no_section_number_gaps(): void {
        global $CFG, $DB, $USER;
        $this->resetAfterTest(true);
        $this->setAdminUser();

        $generator = $this->getDataGenerator();

        // Create source course: Chapter 1 with subsections C1.1, C1.2, C1.3.
        $course1 = $generator->create_course(
            ['numsections' => 1, 'format' => 'flexsections', 'shortname' => 'source'],
            ['createsections' => true]
        );
        /** @var \format_flexsections $format */
        $format = course_get_format($course1);

        // Name the parent section.
        $DB->set_field(
            'course_sections',
            'name',
            'Chapter 1',
            ['course' => $course1->id, 'section' => 1]
        );

        // Create three named subsections under section 1.
        $sub1num = $format->create_new_section(1);
        $sub2num = $format->create_new_section(1);
        $sub3num = $format->create_new_section(1);
        $DB->set_field(
            'course_sections',
            'name',
            'C1.1',
            ['course' => $course1->id, 'section' => $sub1num]
        );
        $DB->set_field(
            'course_sections',
            'name',
            'C1.2',
            ['course' => $course1->id, 'section' => $sub2num]
        );
        $DB->set_field(
            'course_sections',
            'name',
            'C1.3',
            ['course' => $course1->id, 'section' => $sub3num]
        );

        // Add an activity to each subsection.
        $generator->create_module('forum', ['course' => $course1->id, 'section' => $sub1num]);
        $generator->create_module('page', ['course' => $course1->id, 'section' => $sub2num]);
        $generator->create_module('assign', ['course' => $course1->id, 'section' => $sub3num]);

        // Get section ID of C1.2 for excluding during import.
        $modinfo = get_fast_modinfo($course1);
        $sub2id = $modinfo->get_section_info($sub2num)->id;

        // Backup the source course.
        $CFG->backup_file_logger_level = backup::LOG_NONE;
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course1->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Create empty target course.
        $course2 = $generator->create_course(
            ['numsections' => 0, 'format' => 'flexsections', 'shortname' => 'target'],
            ['createsections' => true]
        );

        // Restore to target course, excluding C1.2.
        $rc = new restore_controller(
            $backupid,
            $course2->id,
            backup::INTERACTIVE_NO,
            backup::MODE_IMPORT,
            $USER->id,
            backup::TARGET_CURRENT_ADDING
        );
        $settingname = 'section_' . $sub2id . '_included';
        if ($rc->get_plan()->setting_exists($settingname)) {
            $rc->get_plan()->get_setting($settingname)->set_value(false);
        }
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        // Verify no gaps in section numbering.
        $sections = $DB->get_records('course_sections', ['course' => $course2->id], 'section ASC');
        $nums = array_column($sections, 'section');
        $expected = range(0, count($sections) - 1);
        $this->assertEquals(
            $expected,
            array_map('intval', $nums),
            'Section numbering should be sequential with no gaps'
        );

        // Verify the structure: Chapter 1 with C1.1 and C1.3 as subsections.
        $modinfo2 = get_fast_modinfo($course2);
        $sectionnames = [];
        foreach ($modinfo2->get_section_info_all() as $s) {
            if ($s->section == 0) {
                continue;
            }
            $sectionnames[] = $s->name;
        }
        $this->assertContains('Chapter 1', $sectionnames);
        $this->assertContains('C1.1', $sectionnames);
        $this->assertNotContains('C1.2', $sectionnames, 'Excluded section C1.2 should not be in the target course');
        $this->assertContains('C1.3', $sectionnames);

        // No section should be called "Topic 3" or similar orphan name.
        foreach ($modinfo2->get_section_info_all() as $s) {
            if ($s->section == 0) {
                continue;
            }
            $this->assertNotEmpty(
                $s->name,
                "Section {$s->section} has no name — likely an orphan placeholder"
            );
        }
    }
}
