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
 * This file contains main class for Flexible sections course format.
 *
 * @package   format_flexsections
 * @copyright 2022 Marina Glancy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot. '/course/format/lib.php');

use format_flexsections\constants;
use core\output\inplace_editable;

define('FORMAT_FLEXSECTIONS_COLLAPSED', 1);
define('FORMAT_FLEXSECTIONS_EXPANDED', 0);
define('FORMAT_FLEXSECTIONS_LAYOUT_TOPICS', 0);
define('FORMAT_FLEXSECTIONS_LAYOUT_WEEKLY', 1);

/**
 * Main class for the Flexible sections course format.
 *
 * @package    format_flexsections
 * @copyright  2022 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_flexsections extends core_courseformat\base {

    /**
     * Returns true if this course format uses sections.
     *
     * @return bool
     */
    public function uses_sections() {
        return true;
    }

    /**
     * Uses course index
     *
     * @return bool
     */
    public function uses_course_index() {
        return $this->get_course_index_display() != constants::COURSEINDEX_NONE;
    }

    /**
     * Type of course index display
     *
     * @return int
     */
    public function get_course_index_display(): int {
        return (int)$this->get_course()->courseindexdisplay;
    }

    /**
     * Uses indentation
     *
     * @return bool
     */
    public function uses_indentation(): bool {
        return (get_config('format_flexsections', 'indentation')) ? true : false;
    }

    /**
     * Maximum number of subsections
     *
     * @return int
     */
    public function get_max_section_depth(): int {
        $limit = (int)get_config('format_flexsections', 'maxsectiondepth');
        return max(1, min($limit, 100));
    }

    /**
     * Accordion effect
     *
     * @return bool
     */
    public function get_accordion_setting(): bool {
        return (bool)$this->get_course()->accordion;
    }

    /**
     * Returns the display name of the given section that the course prefers.
     *
     * Use section name is specified by user. Otherwise use default ("Topic #").
     *
     * @param int|stdClass|section_info $section Section object from database or just field section.section
     * @return string Display name that the course format prefers, e.g. "Topic 2"
     */
    public function get_section_name($section) {
        if (!is_object($section)) {
            $section = $this->get_section($section);
        }
        if ((string)$section->name !== '') {
            return format_string($section->name, true,
                ['context' => context_course::instance($this->courseid)]);
        } else {
            return $this->get_default_section_name($section);
        }
    }

    /**
     * Returns the depth of the section in hierarchy
     *
     * For example, top section has depth 1, subsection of top section has depth 2,
     * its subsection has depth 3.
     *
     * @param section_info $section
     * @return int Depth of the section in hierarchy.
     */
    public function get_section_depth(section_info $section): int {
        $parent = $this->get_section($section->parent);
        return $parent && $parent->section ? $this->get_section_depth($parent) + 1 : 1;
    }

    /**
     * Returns the default section name for the flexsections course format.
     *
     * @param stdClass|section_info $section Section object from database or just field course_sections section
     * @return string The default value for the section name.
     */
    public function get_default_section_name($section) {
        $course = $this->get_course();

        if ($section->section == 0) {
            // Return the general section.
            return get_string('section0name', 'format_flexsections');
        } else if ($course->layout === FORMAT_FLEXSECTIONS_LAYOUT_WEEKLY) {
            if (!($section instanceof \section_info)) {
                // We need section_info instance to access parent attribute.
                $section = $this->get_section($section);
            }

            // Show weeks layout for top level section.
            if (isset($section->parent) && $section->parent === 0) {
                // This is identical what weekly format does.
                $dates = $this->get_section_dates($section);

                // We subtract 24 hours for display purposes.
                $dates->end = ($dates->end - DAYSECS);

                $dateformat = get_string('strftimedateshort');
                $weekday = userdate($dates->start, $dateformat);
                $endweekday = userdate($dates->end, $dateformat);
                return $weekday.' - '.$endweekday;
            } else {
                return get_string('subtopic', 'format_flexsections');
            }
        } else {
            // Use course_format::get_default_section_name implementation which
            // will display the section name in "Topic n" format.
            return parent::get_default_section_name($section);
        }
    }

    /**
     * Return the start and end date of the passed top level section.
     *
     * @param int|stdClass|section_info $section section to get the dates for
     * @param int $startdate Force course start date, useful when the course is not yet created
     * @param bool $resettopsections Reset top sections cache, required mainly in unittests.
     * @return stdClass property start for startdate, property end for enddate
     */
    public function get_section_dates($section, $startdate = false, $resettopsections = false) {
        global $USER;
        static $topsections = [];

        if ($resettopsections) {
            $topsections = [];
        }

        if (empty($topsections)) {
            // Populate top section numbers and keep it in static variable.
            foreach ($this->get_sections() as $s) {
                if ($s->parent === 0) {
                    array_push($topsections, $s->section);
                }
            }
        }

        if (is_object($section)) {
            $sectionnum = $section->section;
        } else {
            $sectionnum = $section;
        }

        if (empty($topsections)) {
            // New course, therefore no sections. Use number of sections as offset.
            $offset = $sectionnum;
        } else {
            // Determine offset based on top section consecutive number.
            $offset = array_search($sectionnum, $topsections);

            if ($offset === false) {
                // Supplied section is not a top section.
                throw new coding_exception('get_section_dates method is designed to be used with top level sections only.');
            }
        }

        if ($startdate === false) {
            $course = $this->get_course();
            $userdates = course_get_course_dates_for_user_id($course, $USER->id);
            $startdate = $userdates['start'];
        }

        // Hack alert. We add 2 hours to avoid possible DST problems. (e.g. we go into daylight
        // savings and the date changes.
        $startdate = $startdate + HOURSECS * 2;

        $dates = new stdClass();
        $dates->start = $startdate + (WEEKSECS * ($offset - 1));
        $dates->end = $dates->start + WEEKSECS;

        return $dates;
    }

    /**
     * Returns true if the specified week is current
     *
     * @param int|stdClass|section_info $section
     * @return bool
     */
    public function is_section_current($section) {
        $course = $this->get_course();
        if ($course->layout === FORMAT_FLEXSECTIONS_LAYOUT_WEEKLY) {
            if (is_object($section)) {
                $sectionnum = $section->section;
            } else {
                $sectionnum = $section;
            }

            if ($sectionnum < 1 || $section->parent !== 0) {
                return false;
            }
            $timenow = time();
            $dates = $this->get_section_dates($section);
            return (($timenow >= $dates->start) && ($timenow < $dates->end));
        }
        return parent::is_section_current($section);
    }

    /**
     * Generate the title for this section page.
     *
     * @return string the page title
     */
    public function page_title(): string {
        return get_string('topicoutline');
    }

    /**
     * Returns the section relative number regardless whether argument is an object or an int
     *
     * @param int|section_info $section
     * @return ?int
     */
    protected function resolve_section_number($section) {
        if ($section === null || $section === '') {
            return null;
        } else if (is_object($section)) {
            return $section->section;
        } else {
            return (int)$section;
        }
    }

    /**
     * The URL to use for the specified course (with section).
     *
     * @param int|stdClass $section Section object from database or just field course_sections.section
     *     if omitted the course view page is returned
     * @param array $options options for view URL. At the moment core uses:
     *     'navigation' (bool) if true and section has no separate page, the function returns null
     *     'sr' (int) used by multipage formats to specify to which section to return
     * @return null|moodle_url
     */
    public function get_view_url($section, $options = []) {
        $url = new moodle_url('/course/view.php', ['id' => $this->courseid]);

        $sectionno = $this->resolve_section_number($section);
        $section = $this->get_section($sectionno);
        if ($sectionno && (!$section->uservisible || !$this->is_section_real_available($section))) {
            return empty($options['navigation']) ? $url : null;
        }

        if (array_key_exists('sr', $options)) {
            // Return to the page for section with number $sr.
            $url->param('section', $options['sr']);
            if ($sectionno) {
                $url->set_anchor('section-'.$sectionno);
            }
        } else if ($sectionno) {
            // Check if this section has separate page.
            if ($section->collapsed == FORMAT_FLEXSECTIONS_COLLAPSED) {
                $url->param('section', $section->section);
                return $url;
            }
            // Find the parent (or grandparent) page that is displayed on separate page.
            if ($parent = $this->find_collapsed_parent($section->parent)) {
                $url->param('section', $parent);
            }
            $url->set_anchor('section-'.$sectionno);
        }
        return $url;
    }

    /**
     * Returns the information about the ajax support in the given source format.
     *
     * The returned object's property (boolean)capable indicates that
     * the course format supports Moodle course ajax features.
     *
     * @return stdClass
     */
    public function supports_ajax() {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        return $ajaxsupport;
    }

    /**
     * Supports components
     *
     * @return bool
     */
    public function supports_components() {
        return true;
    }

    /**
     * Loads all of the course sections into the navigation.
     *
     * @param global_navigation $navigation
     * @param navigation_node $node The course node within the navigation
     * @return void
     */
    public function extend_course_navigation($navigation, navigation_node $node) {
        // If course format displays section on separate pages and we are on course/view.php page
        // and the section parameter is specified, make sure this section is expanded in
        // navigation.
        if ($navigation->includesectionnum === false && $this->get_viewed_section() &&
            (!defined('AJAX_SCRIPT') || AJAX_SCRIPT == '0')) {
            $navigation->includesectionnum = $this->get_viewed_section();
        }

        $modinfo = get_fast_modinfo($this->courseid);
        if (!empty($modinfo->sections[0])) {
            foreach ($modinfo->sections[0] as $cmid) {
                $this->navigation_add_activity($node, $modinfo->get_cm($cmid));
            }
        }
        foreach ($modinfo->get_section_info_all() as $section) {
            if ($section->parent == 0 && $section->section != 0) {
                $this->navigation_add_section($navigation, $node, $section);
            }
        }
    }

    /**
     * Adds a course module to the navigation node
     *
     * @param navigation_node $node
     * @param cm_info $cm
     * @return null|navigation_node
     */
    protected function navigation_add_activity(navigation_node $node, cm_info $cm): ?navigation_node {
        if (!$cm->uservisible || !$cm->has_view()) {
            return null;
        }
        $activityname = $cm->get_formatted_name();
        $action = $cm->url;
        if ($cm->icon) {
            $icon = new pix_icon($cm->icon, $cm->modfullname, $cm->iconcomponent);
        } else {
            $icon = new pix_icon('icon', $cm->modfullname, $cm->modname);
        }
        $activitynode = $node->add($activityname, $action, navigation_node::TYPE_ACTIVITY, null, $cm->id, $icon);
        if (global_navigation::module_extends_navigation($cm->modname)) {
            $activitynode->nodetype = navigation_node::NODETYPE_BRANCH;
        } else {
            $activitynode->nodetype = navigation_node::NODETYPE_LEAF;
        }
        if (method_exists($cm, 'is_visible_on_course_page')) {
            $activitynode->display = $cm->is_visible_on_course_page();
        }
        return $activitynode;
    }

    /**
     * Adds a section to navigation node, loads modules and subsections if necessary
     *
     * @param global_navigation $navigation
     * @param navigation_node $node
     * @param section_info $section
     * @return null|navigation_node
     */
    protected function navigation_add_section($navigation, navigation_node $node, section_info $section): ?navigation_node {
        if (!$section->uservisible || !$this->is_section_real_available($section)) {
            return null;
        }
        $sectionname = get_section_name($this->get_course(), $section);
        $url = course_get_url($this->get_course(), $section->section, array('navigation' => true));

        $sectionnode = $node->add($sectionname, $url, navigation_node::TYPE_SECTION, null, $section->id);
        $sectionnode->nodetype = navigation_node::NODETYPE_BRANCH;
        $sectionnode->hidden = !$section->visible || !$section->available;
        if ($section->section == $this->get_viewed_section()) {
            $sectionnode->force_open();
        }
        if ($this->section_has_parent($navigation->includesectionnum, $section->section)
            || $navigation->includesectionnum == $section->section) {
            $modinfo = get_fast_modinfo($this->courseid);
            if (!empty($modinfo->sections[$section->section])) {
                foreach ($modinfo->sections[$section->section] as $cmid) {
                    $this->navigation_add_activity($sectionnode, $modinfo->get_cm($cmid));
                }
            }
            foreach ($modinfo->get_section_info_all() as $subsection) {
                if ($subsection->parent == $section->section && $subsection->section != 0) {
                    $this->navigation_add_section($navigation, $sectionnode, $subsection);
                }
            }
        }
        return $sectionnode;
    }

    /**
     * Custom action after section has been moved in AJAX mode.
     *
     * Used in course/rest.php
     *
     * @return array This will be passed in ajax respose
     */
    public function ajax_section_move() {
        global $PAGE;
        $titles = [];
        $course = $this->get_course();
        $modinfo = get_fast_modinfo($course);
        $renderer = $this->get_renderer($PAGE);
        if ($renderer && ($sections = $modinfo->get_section_info_all())) {
            foreach ($sections as $number => $section) {
                $titles[$number] = $renderer->section_title($section, $course);
            }
        }
        return ['sectiontitles' => $titles, 'action' => 'move'];
    }

    /**
     * Returns the list of blocks to be automatically added for the newly created course.
     *
     * @return array of default blocks, must contain two keys BLOCK_POS_LEFT and BLOCK_POS_RIGHT
     *     each of values is an array of block names (for left and right side columns)
     */
    public function get_default_blocks() {
        return [
            BLOCK_POS_LEFT => [],
            BLOCK_POS_RIGHT => [],
        ];
    }

    /**
     * Definitions of the additional options that this course format uses for section
     *
     * See {@see format_base::course_format_options()} for return array definition.
     *
     * Additionally section format options may have property 'cache' set to true
     * if this option needs to be cached in {@see get_fast_modinfo()}. The 'cache' property
     * is recommended to be set only for fields used in {@see format_base::get_section_name()},
     * {@see format_base::extend_course_navigation()} and {@see format_base::get_view_url()}
     *
     * For better performance cached options are recommended to have 'cachedefault' property
     * Unlike 'default', 'cachedefault' should be static and not access get_config().
     *
     * Regardless of value of 'cache' all options are accessed in the code as
     * $sectioninfo->OPTIONNAME
     * where $sectioninfo is instance of section_info, returned by
     * get_fast_modinfo($course)->get_section_info($sectionnum)
     * or get_fast_modinfo($course)->get_section_info_all()
     *
     * All format options for particular section are returned by calling:
     * $this->get_format_options($section);
     *
     * @param bool $foreditform
     * @return array
     */
    public function section_format_options($foreditform = false): array {
        return array(
            'parent' => array(
                'type' => PARAM_INT,
                'label' => '',
                'element_type' => 'hidden',
                'default' => 0,
                'cache' => true,
                'cachedefault' => 0,
            ),
            'visibleold' => array(
                'type' => PARAM_INT,
                'label' => '',
                'element_type' => 'hidden',
                'default' => 1,
                'cache' => true,
                'cachedefault' => 0,
            ),
            'collapsed' => array(
                'type' => PARAM_INT,
                'label' => get_string('displaycontent', 'format_flexsections'),
                'element_type' => 'select',
                'element_attributes' => array(
                    array(
                        FORMAT_FLEXSECTIONS_EXPANDED => new lang_string('showexpanded', 'format_flexsections'),
                        FORMAT_FLEXSECTIONS_COLLAPSED => new lang_string('showcollapsed', 'format_flexsections'),
                    )
                ),
                'cache' => true,
                'cachedefault' => FORMAT_FLEXSECTIONS_EXPANDED,
                'default' => COURSE_DISPLAY_SINGLEPAGE,
            )
        );
    }

    /**
     * Definitions of the additional options that this course format uses for course.
     *
     * Flexsections format uses the following options:
     * - showsection0title
     * - layout
     * - automaticenddate
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;

        if ($courseformatoptions === false) {
            $courseconfig = get_config('moodlecourse');
            $courseformatoptions = [
                'showsection0title' => [
                    'default' => get_config('format_flexsections', 'showsection0titledefault') ?? 0,
                    'type' => PARAM_BOOL,
                ],
                'courseindexdisplay' => [
                    'default' => get_config('format_flexsections', 'courseindexdisplay') ?? 0,
                    'type' => PARAM_INT,
                ],
                'accordion' => [
                    'default' => (bool)get_config('format_flexsections', 'accordion'),
                    'type' => PARAM_BOOL,
                ],
                'cmbacklink' => [
                    'default' => (bool)get_config('format_flexsections', 'cmbacklink'),
                ],
                'layout' => [
                    'default' => FORMAT_FLEXSECTIONS_LAYOUT_TOPICS,
                    'type' => PARAM_INT,
                ],
                'automaticenddate' => [
                    'default' => 0,
                    'type' => PARAM_BOOL,
                ],
            ];
        }
        if ($foreditform && !isset($courseformatoptions['showsection0title']['label'])) {
            $options = [
                constants::COURSEINDEX_FULL => get_string('courseindexfull', 'format_flexsections'),
                constants::COURSEINDEX_SECTIONS => get_string('courseindexsections', 'format_flexsections'),
                constants::COURSEINDEX_NONE => get_string('courseindexnone', 'format_flexsections'),
            ];
            $courseformatoptionsedit = [
                'showsection0title' => [
                    'label' => new lang_string('showsection0title', 'format_flexsections'),
                    'help' => 'showsection0title',
                    'help_component' => 'format_flexsections',
                    'element_type' => 'advcheckbox',
                ],
                'courseindexdisplay' => [
                    'label' => new lang_string('courseindexdisplay', 'format_flexsections'),
                    'element_type' => 'select',
                    'element_attributes' => [$options],
                ],
                'accordion' => [
                    'label' => new lang_string('accordion', 'format_flexsections'),
                    'element_type' => 'advcheckbox',
                ],
                'cmbacklink' => [
                    'label' => new lang_string('cmbacklink', 'format_flexsections'),
                    'element_type' => 'advcheckbox',
                ],
                'layout' => [
                    'label' => new lang_string('layout', 'format_flexsections'),
                    'help' => 'layout',
                    'help_component' => 'format_flexsections',
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            FORMAT_FLEXSECTIONS_LAYOUT_TOPICS => new lang_string('layouttopics', 'format_flexsections'),
                            FORMAT_FLEXSECTIONS_LAYOUT_WEEKLY => new lang_string('layoutweekly', 'format_flexsections')
                        ]
                    ],
                ],
                'automaticenddate' => [
                    'label' => new lang_string('automaticenddate', 'format_flexsections'),
                    'help' => 'automaticenddate',
                    'help_component' => 'format_flexsections',
                    'element_type' => 'advcheckbox',
                ]
            ];
            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }
        return $courseformatoptions;
    }

    /**
     * Adds format options elements to the course/section edit form.
     *
     * This function is called from {@see course_edit_form::definition_after_data()}.
     *
     * @param MoodleQuickForm $mform form the elements are added to.
     * @param bool $forsection 'true' if this is a section edit form, 'false' if this is course edit form.
     * @return array array of references to the added form elements.
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        global $COURSE;
        $elements = parent::create_edit_form_elements($mform, $forsection);

        if (!$forsection && (empty($COURSE->id) || $COURSE->id == SITEID)) {
            // Add "numsections" element to the create course form - it will force new course to be prepopulated
            // with empty sections.
            // The "Number of sections" option is no longer available when editing course, instead teachers should
            // delete and add sections when needed.
            $courseconfig = get_config('moodlecourse');
            $max = (int)$courseconfig->maxsections;
            $element = $mform->addElement('select', 'numsections', get_string('numberweeks'), range(0, $max ?: 52));
            $mform->setType('numsections', PARAM_INT);
            if (is_null($mform->getElementValue('numsections'))) {
                $mform->setDefault('numsections', $courseconfig->numsections);
            }
            array_unshift($elements, $element);
        }

        if (!$forsection) {
            // Re-order things.
            $mform->insertElementBefore($mform->removeElement('automaticenddate', false), 'idnumber');
            $mform->disabledIf('enddate', 'automaticenddate', 'checked');
            $mform->disabledIf('automaticenddate', 'layout', 'eq', FORMAT_FLEXSECTIONS_LAYOUT_TOPICS);
            foreach ($elements as $key => $element) {
                if ($element->getName() == 'automaticenddate') {
                    array_splice($elements, $key, 1);
                    break;
                }
            }
        }
        return $elements;
    }

    /**
     * Updates format options for a course
     *
     * If $data does not contain property with the option name, the option will not be updated
     *
     * @param stdClass|array $data return value from moodleform::get_data() or array with data
     * @param stdClass $oldcourse if this function is called from update_course()
     *     this object contains information about the course before update
     * @return bool whether there were any changes to the options values
     */
    public function update_course_format_options($data, $oldcourse = null) {
        global $DB;
        $result = $this->update_format_options($data);

        if (!empty($data->numsections) && $oldcourse === null ) {
            // New course is being created. Update course enddate as this is the last opportunity to access numsections.
            $course = $this->get_course();
            if ($course->layout === FORMAT_FLEXSECTIONS_LAYOUT_WEEKLY && $course->automaticenddate === 1 && $data->numsections) {
                $dates = $this->get_section_dates((int) $data->numsections, false);
                $DB->set_field('course', 'enddate', $dates->end, ['id' => $course->id]);
            }
        }

        return $result;
    }

    /**
     * Updates the end date for a course in weekly layout if option automaticenddate is set.
     *
     * This method is called from event observers and it can not use any modinfo or format caches because
     * events are triggered before the caches are reset.
     *
     * @param int $courseid
     */
    public static function update_end_date($courseid) {
        global $DB;

        // For performance use DB query rather class instance to check if we use flexsections
        // weekly layout and automaticenddate is enabled.
        $sql = "SELECT c.id, c.enddate
                  FROM {course} c
                  JOIN {course_format_options} fol
                    ON fol.courseid = c.id
                   AND fol.format = c.format
                   AND fol.name = :loptionname
                   AND fol.value = :loptionvalue
                   AND fol.sectionid = 0
                  JOIN {course_format_options} foa
                    ON foa.courseid = c.id
                   AND foa.format = c.format
                   AND foa.name = :aoptionname
                   AND foa.value = :aoptionvalue
                   AND foa.sectionid = 0
                 WHERE c.format = :format
                   AND c.id = :courseid";
        $course = $DB->get_record_sql($sql, [
            'loptionname' => 'layout',
            'loptionvalue' => FORMAT_FLEXSECTIONS_LAYOUT_WEEKLY,
            'aoptionname' => 'automaticenddate',
            'aoptionvalue' => 1,
            'format' => 'flexsections',
            'courseid' => $courseid
        ]);

        if (!$course) {
            // Looks like it is a course in a different format or layout is not weekly
            // or automaticenddate is disabled, nothing to do here.
            return;
        }

        $format = new format_flexsections('flexsections', $course->id);
        // Clear cache, otherwise get_sections may not contain new section.
        rebuild_course_cache($course->id, true, true);

        // Determine the last section at top level.
        $lasttopsection = 0;
        foreach ($format->get_sections() as $s) {
            if ($s->parent === 0) {
                $lasttopsection = $s->section;
            }
        }

        // Get the final week's last day.
        $dates = $format->get_section_dates((int) $lasttopsection, false, true);

        // Set the course end date.
        if ((int) $course->enddate !== $dates->end) {
            $DB->set_field('course', 'enddate', $dates->end, ['id' => $course->id]);
            rebuild_course_cache($course->id, true);
        }
    }

    /**
     * Whether this format allows to delete sections.
     *
     * Do not call this function directly, instead use {@see course_can_delete_section()}
     *
     * @param int|stdClass|section_info $section
     * @return bool
     */
    public function can_delete_section($section) {
        return true;
    }

    /**
     * Prepares the templateable object to display section name.
     *
     * @param \section_info|\stdClass $section
     * @param bool $linkifneeded
     * @param bool $editable
     * @param null|lang_string|string $edithint
     * @param null|lang_string|string $editlabel
     * @return inplace_editable|void
     */
    public function inplace_editable_render_section_name($section, $linkifneeded = true,
            $editable = null, $edithint = null, $editlabel = null) {
        if (empty($edithint)) {
            $edithint = new lang_string('editsectionname', 'format_flexsections');
        }
        if (empty($editlabel)) {
            $title = get_section_name($section->course, $section);
            $editlabel = new lang_string('newsectionname', 'format_flexsections', $title);
        }
        $section = $this->get_section($section);
        if ($linkifneeded && $section->collapsed != FORMAT_FLEXSECTIONS_COLLAPSED) {
            $linkifneeded = false;
        }
        return parent::inplace_editable_render_section_name($section, $linkifneeded, $editable, $edithint, $editlabel);
    }

    /**
     * Indicates whether the course format supports the creation of a news forum.
     *
     * @return bool
     */
    public function supports_news() {
        return true;
    }

    /**
     * Returns whether this course format allows the activity to
     * have "triple visibility state" - visible always, hidden on course page but available, hidden.
     *
     * @param stdClass|cm_info $cm course module (may be null if we are displaying a form for adding a module)
     * @param stdClass|section_info $section section where this module is located or will be added to
     * @return bool
     */
    public function allow_stealth_module_visibility($cm, $section) {
        // Allow the third visibility state inside visible sections or in section 0.
        return !$section->section || $section->visible;
    }

    /**
     * Callback used in WS core_course_edit_section when teacher performs an AJAX action on a section (show/hide).
     *
     * Access to the course is already validated in the WS but the callback has to make sure
     * that particular action is allowed by checking capabilities
     *
     * Course formats should register.
     *
     * @param section_info|stdClass $section
     * @param string $action
     * @param int $sr
     * @return null|array any data for the Javascript post-processor (must be json-encodeable)
     */
    public function section_action($section, $action, $sr) {
        global $PAGE;

        if ($section->section && ($action === 'setmarker' || $action === 'removemarker')) {
            // Format 'flexsections' allows to set and remove markers in addition to common section actions.
            require_capability('moodle/course:setcurrentsection', context_course::instance($this->courseid));
            course_set_marker($this->courseid, ($action === 'setmarker') ? $section->section : 0);
            return null;
        }

        if ($section->section && ($action === 'showexpanded' || $action === 'showcollapsed')) {
            require_capability('moodle/course:update', context_course::instance($this->courseid));
            $newvalue = ($action === 'showexpanded') ? FORMAT_FLEXSECTIONS_EXPANDED : FORMAT_FLEXSECTIONS_COLLAPSED;
            course_update_section($this->courseid, $section, ['collapsed' => $newvalue]);
            // TODO what to return?
            return null;
        }

        $mergeup = optional_param('mergeup', null, PARAM_INT);
        if ($mergeup && has_capability('moodle/course:update', context_course::instance($this->courseid))) {
            require_sesskey();
            $section = $this->get_section($mergeup, MUST_EXIST);
            $this->mergeup_section($section);
            $url = course_get_url($this->courseid, $section->parent);
            redirect($url);
        }

        // For show/hide actions call the parent method and return the new content for .section_availability element.
        $rv = parent::section_action($section, $action, $sr);
        $renderer = $PAGE->get_renderer('format_flexsections');

        if (!($section instanceof section_info)) {
            $modinfo = course_modinfo::instance($this->courseid);
            $section = $modinfo->get_section_info($section->section);
        }
        $elementclass = $this->get_output_classname('content\\section\\availability');
        $availability = new $elementclass($this, $section);

        $rv['section_availability'] = $renderer->render($availability);
        return $rv;
    }

    /**
     * Return the plugin configs for external functions.
     *
     * @return array the list of configuration settings
     */
    public function get_config_for_external() {
        // Return everything (nothing to hide).
        return $this->get_format_options();
    }

    /**
     * Checks if section is really available for the current user (analyses parent section available)
     *
     * @param int|section_info $section
     * @return bool
     */
    public function is_section_real_available($section) {
        if (($this->resolve_section_number($section) == 0)) {
            // Section 0 is always available.
            return true;
        }
        $context = context_course::instance($this->courseid);
        if (has_capability('moodle/course:viewhiddensections', $context)) {
            // For the purpose of this function only return true for teachers.
            return true;
        }
        $section = $this->get_section($section);
        return $section->available && $this->is_section_real_available($section->parent);
    }

    /**
     * Returns either section or it's parent or grandparent, whoever first is collapsed
     *
     * @param int|section_info $section
     * @param bool $returnid
     * @return int
     */
    public function find_collapsed_parent($section, $returnid = false) {
        $section = $this->get_section($section);
        if (!$section->section || $section->collapsed == FORMAT_FLEXSECTIONS_COLLAPSED) {
            return $returnid ? $section->id : $section->section;
        } else {
            return $this->find_collapsed_parent($section->parent, $returnid);
        }
    }

    /**
     * URL of the page from where this function was called (use referer if this is an AJAX request)
     *
     * @return moodle_url
     */
    protected function get_caller_page_url(): moodle_url {
        global $PAGE, $FULLME;
        $url = $PAGE->has_set_url() ? $PAGE->url : new moodle_url($FULLME);
        if ($url->compare(new moodle_url('/lib/ajax/service.php'), URL_MATCH_BASE)) {
            return !empty($_SERVER['HTTP_REFERER']) ? new moodle_url($_SERVER['HTTP_REFERER']) : $url;
        }
        return $url;
    }

    /**
     * Returns true if we are on /course/view.php page
     *
     * @return bool
     */
    public function on_course_view_page() {
        $url = $this->get_caller_page_url();
        return ($url && $url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE));
    }

    /**
     * If we are on course/view.php page return the 'section' attribute from query
     *
     * @return int
     */
    public function get_viewed_section() {
        if ($this->on_course_view_page()) {
            if ($s = $this->get_caller_page_url()->get_param('section')) {
                return (int)$s;
            }
            $sid = $this->get_caller_page_url()->get_param('sectionid');
            if ($sid && ($section = $this->get_modinfo()->get_section_info_by_id($sid))) {
                return $section->section;
            }
        }
        return 0;
    }

    /**
     * Is this section displayed on the current page
     *
     * Used in course index
     *
     * @param int $sectionnum
     * @return bool
     */
    public function is_section_displayed_on_current_page(int $sectionnum): bool {
        $viewedsection = $this->get_viewed_section();
        if ($viewedsection) {
            return $sectionnum == $viewedsection || $this->section_has_parent($sectionnum, $viewedsection);
        } else {
            $section = $this->get_section($sectionnum);
            if (!$section->parent) {
                return true;
            }
            return $this->find_collapsed_parent($section->parent) ? false : true;
        }
    }

    /**
     * Create a new section under given parent
     *
     * @param int|section_info $parent parent section
     * @param null|int|section_info $before
     * @return int $sectionnum
     */
    public function create_new_section($parent = 0, $before = null): int {
        global $DB;
        $section = course_create_section($this->courseid, 0);
        $sectionnum = $this->move_section($section, $parent, $before);

        if ($parent === 0) {
            // Update course enddate for the new top level section if necessary.
            $course = $this->get_course();
            if ($course->layout === FORMAT_FLEXSECTIONS_LAYOUT_WEEKLY && $course->automaticenddate === 1) {
                if ($before === null) {
                    // This is last section at the top level, use its number to adjust dates.
                    $dates = $this->get_section_dates($sectionnum, false, true);
                    $DB->set_field('course', 'enddate', $dates->end, ['id' => $this->courseid]);
                    rebuild_course_cache($this->courseid, true);
                } else {
                    // Section in the middle of top level was added.
                    self::update_end_date($this->courseid);
                }
            }
        }

        return $sectionnum;
    }

    /**
     * Allows course format to execute code on moodle_page::set_course()
     *
     * format_flexsections processes additional attributes in the view course URL
     * to manipulate sections and redirect to course view page
     *
     * @param moodle_page $page instance of page calling set_course
     */
    public function page_set_course(moodle_page $page) {
        global $PAGE;
        if ($PAGE != $page) {
            return;
        }
        if ($this->on_course_view_page()) {
            $context = context_course::instance($this->courseid);
            $currentsectionnum = $this->get_viewed_section();

            // Fix the section argument.
            if ($currentsectionnum) {
                $sectioninfo = $this->get_modinfo()->get_section_info($currentsectionnum);
                if (!$sectioninfo || !$sectioninfo->collapsed) {
                    redirect(course_get_url($this->get_course(), $sectioninfo ? $this->find_collapsed_parent($sectioninfo) : null));
                }
            }

            if (!$this->is_section_real_available($this->get_viewed_section())) {
                throw new moodle_exception('nopermissiontoviewpage');
            }

            if ($currentsectionnum) {
                navigation_node::override_active_url(new moodle_url('/course/view.php',
                    array('id' => $this->courseid,
                        'section' => $currentsectionnum)));
            }

            // If requested, create new section and redirect to course view page.
            $addchildsection = optional_param('addchildsection', null, PARAM_INT);
            if ($addchildsection !== null && has_capability('moodle/course:update', $context)) {
                $sectionnum = $this->create_new_section($addchildsection);
                $url = course_get_url($this->courseid, $sectionnum);
                redirect($url);
            }

            // If requested, merge the section content with parent and remove the section.
            $mergeup = optional_param('mergeup', null, PARAM_INT);
            if ($mergeup && confirm_sesskey() && has_capability('moodle/course:update', $context)) {
                $section = $this->get_section($mergeup, MUST_EXIST);
                $this->mergeup_section($section);
                $url = course_get_url($this->courseid, $section->parent);
                redirect($url);
            }

            // If requested, delete the section.
            $deletesection = optional_param('deletesection', null, PARAM_INT);
            if ($deletesection && confirm_sesskey() && has_capability('moodle/course:update', $context)
                && optional_param('confirm', 0, PARAM_INT) == 1) {
                $section = $this->get_section($deletesection, MUST_EXIST);
                $parent = $section->parent;
                $this->delete_section_with_children($section);
                $url = course_get_url($this->courseid, $parent);
                redirect($url);
            }

            // If requested, move section.
            $movesection = optional_param('movesection', null, PARAM_INT);
            $moveparent = optional_param('moveparent', null, PARAM_INT);
            $movebefore = optional_param('movebefore', null, PARAM_RAW);
            $sr = optional_param('sr', null, PARAM_RAW);
            $options = array();
            if ($sr !== null) {
                $options = array('sr' => $sr);
            }
            if ($movesection !== null && $moveparent !== null && has_capability('moodle/course:update', $context)) {
                $newsectionnum = $this->move_section($movesection, $moveparent, $movebefore);
                redirect(course_get_url($this->courseid, $newsectionnum, $options));
            }

            // If requested, switch collapsed attribute.
            $switchcollapsed = optional_param('switchcollapsed', null, PARAM_INT);
            if ($switchcollapsed && confirm_sesskey() && has_capability('moodle/course:update', $context)
                && ($section = $this->get_section($switchcollapsed))) {
                if ($section->collapsed == FORMAT_FLEXSECTIONS_EXPANDED) {
                    $newvalue = FORMAT_FLEXSECTIONS_COLLAPSED;
                } else {
                    $newvalue = FORMAT_FLEXSECTIONS_EXPANDED;
                }
                $this->update_section_format_options(array('id' => $section->id, 'collapsed' => $newvalue));
                if ($newvalue == FORMAT_FLEXSECTIONS_COLLAPSED) {
                    if (!isset($options['sr'])) {
                        $options['sr'] = $this->find_collapsed_parent($section->parent);
                    }
                    redirect(course_get_url($this->courseid, $switchcollapsed, $options));
                } else {
                    redirect(course_get_url($this->courseid, $switchcollapsed, $options));
                }
            }

            // Set course marker if required.
            $marker = optional_param('marker', null, PARAM_INT);
            if ($marker !== null && has_capability('moodle/course:setcurrentsection', $context) && confirm_sesskey()) {
                if ($marker > 0) {
                    // Set marker.
                    $url = course_get_url($this->courseid, $marker, array('sr' => $this->get_viewed_section()));
                    course_set_marker($this->courseid, $marker);
                    redirect($url);
                } else if ($this->get_course()->marker) {
                    // Remove marker.
                    $url = course_get_url($this->courseid, $this->get_course()->marker,
                        array('sr' => $this->get_viewed_section()));
                    course_set_marker($this->courseid, 0);
                    redirect($url);
                }
            }

            // Change visibility if required.
            $hide = optional_param('hide', null, PARAM_INT);
            if ($hide !== null && has_capability('moodle/course:sectionvisibility', $context) && confirm_sesskey()) {
                $url = course_get_url($this->courseid, $hide, array('sr' => $this->get_viewed_section()));
                $this->set_section_visible($hide, 0);
                redirect($url);
            }
            $show = optional_param('show', null, PARAM_INT);
            if ($show !== null && has_capability('moodle/course:sectionvisibility', $context) && confirm_sesskey()) {
                $url = course_get_url($this->courseid, $show, array('sr' => $this->get_viewed_section()));
                $this->set_section_visible($show, 1);
                redirect($url);
            }
        }
    }

    /**
     * Moves section to the specified position
     *
     * @param int|section_info $section
     * @param int|section_info $parent
     * @param null|int|section_info $before
     * @return int new section number
     */
    public function move_section($section, $parent, $before = null): int {
        global $DB;
        $section = $this->get_section($section);
        $parent = $this->get_section($parent);
        $newsectionnumber = $section->section;
        if (!$this->can_move_section_to($section, $parent, $before)) {
            return $newsectionnumber;
        }
        if ($section->visible != $parent->visible && $section->parent != $parent->section) {
            // Section is changing parent and new parent has different visibility than the section.
            if ($section->visible) {
                // Visible section is moved under hidden parent.
                $updatesectionvisible = 0;
                $updatesectionvisibleold = 1;
            } else {
                // Hidden section is moved under visible parent.
                if ($section->visibleold) {
                    $updatesectionvisible = 1;
                    $updatesectionvisibleold = 1;
                }
            }
        }

        // Find the changes in the sections numbering.
        $origorder = array();
        foreach ($this->get_sections() as $subsection) {
            $origorder[$subsection->id] = $subsection->section;
        }
        $neworder = array();
        $this->reorder_sections($neworder, 0, $section->section, $parent, $before);
        if (count($origorder) != count($neworder)) {
            die('Error in sections hierarchy'); // TODO.
        }
        $changes = array();
        foreach ($origorder as $id => $num) {
            if ($num == $section->section) {
                $newsectionnumber = $neworder[$id];
            }
            if ($num != $neworder[$id]) {
                $changes[$id] = array('old' => $num, 'new' => $neworder[$id]);
                if ($num && $this->get_course()->marker == $num) {
                    $changemarker = $neworder[$id];
                }
            }
            if ($this->resolve_section_number($parent) === $num) {
                $newparentnum = $neworder[$id];
            }
        }

        if (empty($changes) && $newparentnum == $section->parent) {
            return $newsectionnumber;
        }

        // Build array of required changes in field 'parent'.
        $changeparent = array();
        foreach ($this->get_sections() as $subsection) {
            foreach ($changes as $id => $change) {
                if ($subsection->parent == $change['old']) {
                    $changeparent[$subsection->id] = $change['new'];
                }
            }
        }
        $changeparent[$section->id] = $newparentnum;

        // Update all in database in one transaction.
        $transaction = $DB->start_delegated_transaction();
        // Update sections numbers in 2 steps to avoid breaking database uniqueness constraint.
        foreach ($changes as $id => $change) {
            $DB->set_field('course_sections', 'section', -$change['new'], array('id' => $id));
        }
        foreach ($changes as $id => $change) {
            $DB->set_field('course_sections', 'section', $change['new'], array('id' => $id));
        }
        // Change parents of their subsections.
        foreach ($changeparent as $id => $newnum) {
            $this->update_section_format_options(array('id' => $id, 'parent' => $newnum));
        }
        $transaction->allow_commit();

        // Update course enddate for the new top section if necessary.
        $course = $this->get_course();
        if ($course->layout === FORMAT_FLEXSECTIONS_LAYOUT_WEEKLY && $course->automaticenddate === 1) {
            self::update_end_date($this->courseid);
        }

        rebuild_course_cache($this->courseid, true);
        if (isset($changemarker)) {
            course_set_marker($this->courseid, $changemarker);
        }
        if (isset($updatesectionvisible)) {
            $this->set_section_visible($newsectionnumber, $updatesectionvisible, $updatesectionvisibleold);
        }
        return $newsectionnumber;
    }

    /**
     * Sets the section visible/hidden including subsections and modules
     *
     * @param int|stdClass|section_info $section
     * @param int $visibility
     * @param null|int $setvisibleold if specified in case of hiding the section,
     *    this will be the value of visibleold for the section $section.
     */
    protected function set_section_visible($section, $visibility, $setvisibleold = null) {
        $subsections = array();
        $sectionnumber = $this->resolve_section_number($section);
        if (!$sectionnumber && !$visibility) {
            // Can not hide section with number 0.
            return;
        }
        $section = $this->get_section($section);
        if ($visibility && $section->parent && !$this->get_section($section->parent)->visible) {
            // Can not set section visible when parent is hidden.
            return;
        }
        $ch = array($section);
        while (!empty($ch)) {
            $chlast = $ch;
            $ch = array();
            foreach ($chlast as $s) {
                // Store copy of attributes to avoid rebuilding course cache when we need to access section properties.
                $subsections[] = (object)array('section' => $s->section,
                    'id' => $s->id, 'visible' => $s->visible, 'visibleold' => $s->visibleold);
                $ch += $this->get_subsections($s);
            }
        }
        foreach ($subsections as $s) {
            if ($s->section == $sectionnumber) {
                set_section_visible($this->courseid, $s->section, $visibility);
                if ($setvisibleold === null) {
                    $setvisibleold = $visibility;
                }
                $this->update_section_format_options(array('id' => $s->id, 'visibleold' => $setvisibleold));
            } else {
                if ($visibility) {
                    if ($s->visibleold) {
                        set_section_visible($this->courseid, $s->section, $s->visibleold);
                    }
                } else {
                    if ($s->visible) {
                        set_section_visible($this->courseid, $s->section, $visibility);
                        $this->update_section_format_options(array('id' => $s->id, 'visibleold' => $s->visible));
                    }
                }
            }
        }
    }

    /**
     * Returns the list of direct subsections of the specified section
     *
     * @param int|section_info $section
     * @return array
     */
    public function get_subsections($section) {
        $sectionnum = $this->resolve_section_number($section);
        $subsections = array();
        foreach ($this->get_sections() as $num => $subsection) {
            if ($subsection->parent == $sectionnum && $num != $sectionnum) {
                $subsections[$num] = $subsection;
            }
        }
        return $subsections;
    }

    /**
     * Function recursively reorders the sections while moving one section to the new position
     *
     * If $movedsectionnum is not specified, function just populates the array for each (sub)section
     * If $movedsectionnum is specified, we ignore it on the present location but add it
     * under $movetoparentnum before $movebeforenum
     *
     * @param array $neworder the result or re-ordering, array (sectionid => sectionnumber)
     * @param int|section_info $cursection
     * @param int|section_info $movedsectionnum
     * @param int|section_info $movetoparentnum
     * @param int|section_info $movebeforenum
     */
    protected function reorder_sections(&$neworder, $cursection, $movedsectionnum = null,
                                        $movetoparentnum = null, $movebeforenum = null) {
        // Normalise arguments.
        $cursection = $this->get_section($cursection);
        $movetoparentnum = $this->resolve_section_number($movetoparentnum);
        $movebeforenum = $this->resolve_section_number($movebeforenum);
        $movedsectionnum = $this->resolve_section_number($movedsectionnum);
        if ($movedsectionnum === null) {
            $movebeforenum = $movetoparentnum = null;
        }

        // Ignore section being moved.
        if ($movedsectionnum !== null && $movedsectionnum == $cursection->section) {
            return;
        }

        // Add current section to $neworder.
        $neworder[$cursection->id] = count($neworder);
        // Loop through subsections and reorder them (insert $movedsectionnum if necessary).
        foreach ($this->get_subsections($cursection) as $subsection) {
            if ($movebeforenum && $subsection->section == $movebeforenum) {
                $this->reorder_sections($neworder, $movedsectionnum);
            }
            $this->reorder_sections($neworder, $subsection, $movedsectionnum, $movetoparentnum, $movebeforenum);
        }
        if (!$movebeforenum && $movetoparentnum !== null && $movetoparentnum == $cursection->section) {
            $this->reorder_sections($neworder, $movedsectionnum);
        }
    }

    /**
     * Check if we can move the section to this position
     *
     * not allow to insert section as it's own subsection
     * not allow to insert section directly before or after itself (it would not change anything)
     *
     * @param int|section_info $section
     * @param int|section_info $parent
     * @param null|section_info|int $before null if in the end of subsections list
     */
    public function can_move_section_to($section, $parent, $before = null) {
        $section = $this->get_section($section);
        $parent = $this->get_section($parent);
        if ($section === null || $parent === null ||
            !has_capability('moodle/course:update', context_course::instance($this->courseid))) {
            return false;
        }
        // Check that $parent is not subsection of $section.
        if ($section->section == $parent->section || $this->section_has_parent($parent, $section->section)) {
            return false;
        }
        if ($section->parent != $parent->section) {
            // When moving to another parent, check the depth.
            if ($this->get_section_depth($parent) + 1 > $this->get_max_section_depth()) {
                return false;
            }
        }

        if ($before) {
            if (is_string($before)) {
                $before = (int)$before;
            }
            $before = $this->get_section($before);
            // Check that it's a subsection of $parent.
            if (!$before || $before->parent !== $parent->section) {
                return false;
            }
        }

        if ($section->parent == $parent->section) {
            // Section's parent is not being changed
            // do not insert section directly before or after itself.
            if ($before && $before->section == $section->section) {
                return false;
            }
            $subsections = array();
            $lastsibling = null;
            foreach ($this->get_sections() as $num => $sibling) {
                if ($sibling->parent == $parent->section) {
                    if ($before && $before->section == $num) {
                        if ($lastsibling && $lastsibling->section == $section->section) {
                            return false;
                        } else {
                            return true;
                        }
                    }
                    $lastsibling = $sibling;
                }
            }
            if ($lastsibling && !$before && $lastsibling->section == $section->section) {
                return false;
            }
        }
        return true;
    }

    /**
     * Checks if given section has another section among it's parents
     *
     * @param int|section_info $section child section
     * @param int $parentnum parent section number
     * @return boolean
     */
    public function section_has_parent($section, $parentnum) {
        if (!$section) {
            return false;
        }
        $section = $this->get_section($section);
        if (!$section->section) {
            return false;
        } else if ($section->parent == $parentnum) {
            return true;
        } else if ($section->parent == 0) {
            return false;
        } else if ($section->parent >= $section->section) {
            // Some error.
            return false;
        } else {
            return $this->section_has_parent($section->parent, $parentnum);
        }
    }

    /**
     * Completely removes a section, all subsections and activities they contain
     *
     * @param section_info $section
     * @return array Array containing arrays of section ids and course mod ids that were deleted
     */
    public function delete_section_with_children(section_info $section): array {
        global $DB;
        if (!$section->section) {
            // Section 0 does not have parent.
            return [[], []];
        }

        $sectionid = $section->id;
        $course = $this->get_course();

        // Move the section to be removed to the end (this will re-number other sections).
        $this->move_section($section->section, 0);

        $modinfo = get_fast_modinfo($this->courseid);
        $allsections = $modinfo->get_section_info_all();
        $process = false;
        $sectionstodelete = [];
        $modulestodelete = [];
        foreach ($allsections as $sectioninfo) {
            if ($sectioninfo->id == $sectionid) {
                // This is the section to be deleted. Since we have already
                // moved it to the end we know that we need to delete this section
                // and all the following (which can only be its subsections).
                $process = true;
            }
            if ($process) {
                $sectionstodelete[] = $sectioninfo->id;
                if (!empty($modinfo->sections[$sectioninfo->section])) {
                    $modulestodelete = array_merge($modulestodelete,
                        $modinfo->sections[$sectioninfo->section]);
                }
                // Remove the marker if it points to this section.
                if ($sectioninfo->section == $course->marker) {
                    course_set_marker($course->id, 0);
                }
            }
        }

        foreach ($modulestodelete as $cmid) {
            course_delete_module($cmid);
        }

        [$sectionsql, $params] = $DB->get_in_or_equal($sectionstodelete);
        $sections = $DB->get_records_select('course_sections', "id $sectionsql", $params);

        // Delete section records.
        $transaction = $DB->start_delegated_transaction();
        $DB->execute('DELETE FROM {course_format_options} WHERE sectionid ' . $sectionsql, $params);
        $DB->execute('DELETE FROM {course_sections} WHERE id ' . $sectionsql, $params);
        $transaction->allow_commit();

        foreach ($sections as $section) {
            // Invalidate the section cache by given section id.
            course_modinfo::purge_course_section_cache_by_id($course->id, $section->id);

            // Delete section summary files.
            $context = \context_course::instance($course->id);
            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'course', 'section', $section->id);

            // Trigger an event for course section deletion.
            $event = \core\event\course_section_deleted::create(
                array(
                    'objectid' => $section->id,
                    'courseid' => $course->id,
                    'context' => $context,
                    'other' => [
                        'sectionnum' => $section->section,
                        'sectionname' => $this->get_section_name($section),
                    ]
                )
            );
            $event->add_record_snapshot('course_sections', $section);
            $event->trigger();
        }

        // Partial rebuild section cache that has been purged.
        rebuild_course_cache($this->courseid, true, true);

        return [$sectionstodelete, $modulestodelete];
    }

    /**
     * Moves the section content to the parent section and deletes it
     *
     * Moves all activities and subsections to the parent section (section 0
     * can never be deleted)
     *
     * @param section_info $section
     */
    public function mergeup_section(section_info $section): void {
        global $DB;
        if (!$section->section || !$section->parent) {
            // Section 0 does not have parent.
            return;
        }

        // Move all modules and activities from this section to parent.
        $modinfo = get_fast_modinfo($this->courseid);
        $allsections = $modinfo->get_section_info_all();
        $subsections = $this->get_subsections($section);
        $parent = $modinfo->get_section_info($section->parent);
        if (!empty($modinfo->sections[$section->section])) {
            foreach ($modinfo->sections[$section->section] as $cmid) {
                moveto_module($modinfo->get_cm($cmid), $parent);
            }
        }
        foreach ($subsections as $subsection) {
            $this->update_section_format_options(
                ['id' => $subsection->id, 'parent' => $parent->section]);
        }

        if ($this->get_course()->marker == $section->section) {
            course_set_marker($this->courseid, 0);
        }

        // Move the section to be removed to the end (this will re-number other sections).
        $this->move_section($section->section, 0);

        // Invalidate the section cache by given section id.
        course_modinfo::purge_course_section_cache_by_id($this->courseid, $section->id);

        // Delete section summary files.
        $context = \context_course::instance($this->courseid);
        $fs = get_file_storage();
        $fs->delete_area_files($context->id, 'course', 'section', $section->id);

        // Delete section completely.
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('course_format_options', ['courseid' => $this->courseid, 'sectionid' => $section->id]);
        $DB->delete_records('course_sections', ['id' => $section->id]);
        $transaction->allow_commit();

        // Partial rebuild section cache that has been purged.
        rebuild_course_cache($this->courseid, true, true);
    }

    /**
     * Display 'Add section' as a link on the page and not as a "Add subsection" menu item
     *
     * @param int $sectionnum
     * @return bool
     */
    public function should_display_add_sub_section_link(int $sectionnum): bool {
        // Display for the top-level sections and for the sections that are displayed as a link.
        if (!$sectionnum) {
            return true;
        }
        $section = $this->get_section($sectionnum);
        return (bool)$section->collapsed;
    }

    /**
     * Method used to get the maximum number of sections for this course format.
     *
     * Flexsections does not have a limit for the total number of the sections.
     *
     * @return int
     */
    public function get_max_sections() {
        return 9999999;
    }

    /**
     * Method used to get the maximum number of sections on the top level.
     * @return int
     */
    public function get_max_toplevel_sections() {
        $maxsections = get_config('moodlecourse', 'maxsections');
        if (!isset($maxsections) || !is_numeric($maxsections)) {
            $maxsections = 52;
        }
        return $maxsections;
    }
}

/**
 * Implements callback inplace_editable() allowing to edit values in-place.
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return ?inplace_editable
 */
function format_flexsections_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/externallib.php');
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            [$itemid, 'flexsections'], MUST_EXIST);
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
}

/**
 * Get icon mapping for font-awesome.
 */
function format_flexsections_get_fontawesome_icon_map() {
    return [
        'format_flexsections:mergeup' => 'fa-level-up',
    ];
}

/**
 * If we are on an activity page inside the course in the 'flexsections' format - return the activity
 *
 * @return cm_info|null
 */
function format_flexsections_add_back_link_to_cm(): ?cm_info {
    global $PAGE, $CFG;
    if ($PAGE->course
            && $PAGE->cm
            && $PAGE->course->format === 'flexsections' // Only modules in 'flexsections' courses.
            && course_get_format($PAGE->course)->get_course()->cmbacklink
            && $PAGE->pagelayout === 'incourse' // Only view pages with the incourse layout (not popup, embedded, etc).
            && $PAGE->cm->sectionnum // Do not display in activities in General section.
            && $PAGE->url->out_omit_querystring() === $CFG->wwwroot . "/mod/{$PAGE->cm->modname}/view.php") {
        return $PAGE->cm;
    }
    return null;
}

/**
 * Callback allowing to add contetnt inside the region-main, in the very end
 *
 * If we are on activity page, add the "Back to section" link
 *
 * @return string
 */
function format_flexsections_before_footer() {
    global $OUTPUT;
    if ($cm = format_flexsections_add_back_link_to_cm()) {
        return $OUTPUT->render_from_template('format_flexsections/back_link_in_cms', [
            'backtosection' => [
                'url' => course_get_url($cm->course, $cm->sectionnum)->out(false),
                'sectionname' => get_section_name($cm->course, $cm->sectionnum),
            ]
        ]);
    }
    return '';
}
