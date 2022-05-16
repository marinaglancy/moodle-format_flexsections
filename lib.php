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

use core\output\inplace_editable;

define('FORMAT_FLEXSECTIONS_COLLAPSED', 1);
define('FORMAT_FLEXSECTIONS_EXPANDED', 0);

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
        return true;
    }

    /**
     * Uses indentation
     *
     * @return bool
     */
    public function uses_indentation(): bool {
        return false;
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
        $section = $this->get_section($section);
        if ((string)$section->name !== '') {
            return format_string($section->name, true,
                ['context' => context_course::instance($this->courseid)]);
        } else {
            return $this->get_default_section_name($section);
        }
    }

    /**
     * Returns the default section name for the flexsections course format.
     *
     * If the section number is 0, it will use the string with key = section0name from the course format's lang file.
     * If the section number is not 0, the base implementation of course_format::get_default_section_name which uses
     * the string with the key = 'sectionname' from the course format's lang file + the section number will be used.
     *
     * @param stdClass|section_info $section Section object from database or just field course_sections section
     * @return string The default value for the section name.
     */
    public function get_default_section_name($section) {
        if ($section->section == 0) {
            // Return the general section.
            return get_string('section0name', 'format_flexsections');
        } else {
            // Use course_format::get_default_section_name implementation which
            // will display the section name in "Topic n" format.
            return parent::get_default_section_name($section);
        }
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
     * @return int
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
        $url = new moodle_url('/course/view.php', array('id' => $this->courseid));

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
            $url->param('sectionid', $this->find_collapsed_parent($section->parent, true));
            $url->set_anchor('section-'.$sectionno);
            return $url;
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
        global $PAGE;
        // If section is specified in course/view.php, make sure it is expanded in navigation.
        if ($navigation->includesectionnum === false) {
            $selectedsection = optional_param('section', null, PARAM_INT);
            if ($selectedsection !== null && (!defined('AJAX_SCRIPT') || AJAX_SCRIPT == '0') &&
                    $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
                $navigation->includesectionnum = $selectedsection;
            }
        }

        // Check if there are callbacks to extend course navigation.
        parent::extend_course_navigation($navigation, $node);

        // We want to remove the general section if it is empty.
        $modinfo = get_fast_modinfo($this->get_course());
        $sections = $modinfo->get_sections();
        if (!isset($sections[0])) {
            // The general section is empty to find the navigation node for it we need to get its ID.
            $section = $modinfo->get_section_info(0);
            $generalsection = $node->get($section->id, navigation_node::TYPE_SECTION);
            if ($generalsection) {
                // We found the node - now remove it.
                $generalsection->remove();
            }
        }
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
    public function section_format_options($foreditform = false) {
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

        return $elements;
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
     * @return inplace_editable
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
            return $this->get_caller_page_url()->get_param('section');
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
     * @return int
     */
    public function create_new_section($parent = 0, $before = null) {
        $sections = get_fast_modinfo($this->courseid)->get_section_info_all();
        $sectionnums = array_keys($sections);
        $sectionnum = array_pop($sectionnums) + 1;
        course_create_sections_if_missing($this->courseid, $sectionnum);
        $sectionnum = $this->move_section($sectionnum, $parent, $before);
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

            if (!$this->is_section_real_available($this->get_viewed_section())) {
                throw new moodle_exception('nopermissiontoviewpage');
            }

            if ($currentsectionnum = $this->get_viewed_section()) {
                navigation_node::override_active_url(new moodle_url('/course/view.php',
                    array('id' => $this->courseid,
                        'sectionid' => $this->get_section($currentsectionnum)->id)));
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
                $this->delete_section_int($section);
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
    protected function move_section($section, $parent, $before = null) {
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
     */
    protected function delete_section_int($section) {
        global $DB;
        if (!$section->section) {
            // Section 0 does not have parent.
            return;
        }

        $sectionid = $section->id;

        // Move the section to be removed to the end (this will re-number other sections).
        $this->move_section($section->section, 0);

        $modinfo = get_fast_modinfo($this->courseid);
        $allsections = $modinfo->get_section_info_all();
        $section = null;
        $sectionstodelete = array();
        $modulestodelete = array();
        foreach ($allsections as $sectioninfo) {
            if ($sectioninfo->id == $sectionid) {
                // This is the section to be deleted. Since we have already
                // moved it to the end we know that we need to delete this section
                // and all the following (which can only be its subsections).
                $section = $sectioninfo;
            }
            if ($section) {
                $sectionstodelete[] = $sectioninfo->id;
                if (!empty($modinfo->sections[$sectioninfo->section])) {
                    $modulestodelete = array_merge($modulestodelete,
                        $modinfo->sections[$sectioninfo->section]);
                }
            }
        }

        foreach ($modulestodelete as $cmid) {
            course_delete_module($cmid);
        }

        list($sectionsql, $params) = $DB->get_in_or_equal($sectionstodelete);
        $DB->execute('DELETE FROM {course_format_options} WHERE sectionid ' . $sectionsql, $params);
        $DB->execute('DELETE FROM {course_sections} WHERE id ' . $sectionsql, $params);

        rebuild_course_cache($this->courseid, true);
    }

    /**
     * Moves the section content to the parent section and deletes it
     *
     * Moves all activities and subsections to the parent section (section 0
     * can never be deleted)
     *
     * @param section_info $section
     */
    protected function mergeup_section($section) {
        global $DB;
        if (!$section->section) {
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
                array('id' => $subsection->id, 'parent' => $parent->section));
        }

        if ($this->get_course()->marker == $section->section) {
            course_set_marker($this->courseid, 0);
        }

        // Move the section to be removed to the end (this will re-number other sections).
        $this->move_section($section->section, 0);
        // Delete it completely.
        $params = array('courseid' => $this->courseid,
            'sectionid' => $section->id);
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('course_format_options', $params);
        $DB->delete_records('course_sections', array('id' => $section->id));
        $transaction->allow_commit();
        rebuild_course_cache($this->courseid, true);
    }
}

/**
 * Implements callback inplace_editable() allowing to edit values in-place.
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return inplace_editable
 */
function format_flexsections_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            [$itemid, 'flexsections'], MUST_EXIST);
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
}
