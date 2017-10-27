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
require_once($CFG->dirroot. '/course/format/lib.php');

define('FORMAT_FLEXSECTIONS_COLLAPSED', 1);
define('FORMAT_FLEXSECTIONS_EXPANDED', 0);

/**
 * Format Flexsections base class
 *
 * @package    format_flexsections
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_flexsections extends format_base {
    /**
     * Returns true if this course format uses sections
     *
     * @return bool
     */
    public function uses_sections() {
        return true;
    }

    /**
     * Returns the display name of the given section that the course prefers.
     *
     * @param int|stdClass $section Section object from database or just field section.section
     * @return string Display name that the course format prefers, e.g. "Topic 2"
     */
    public function get_section_name($section) {
        $section = $this->get_section($section);
        if ((string)$section->name !== '') {
            return format_string($section->name, true, array('context' => context_course::instance($this->courseid)));
        } else {
            return $this->get_default_section_name($section);
        }
    }

    /**
     * Returns the default section using format_base's implementation of get_section_name.
     *
     * @param int|stdClass|section_info $section Section object from database or just field course_sections section
     * @return string The default value for the section name based on the given course format.
     */
    public function get_default_section_name($section) {
        if (is_object($section)) {
            $sectionnum = $section->section;
        } else {
            $sectionnum = $section;
        }
        if ($sectionnum == 0) {
            // Return the general section.
            return get_string('section0name', 'format_' . $this->format);
        } else {
            return get_string('topic').' '.$sectionnum;
        }
        return '';
    }

    /**
     * The URL to use for the specified course (with section)
     *
     * @param int|stdClass $section Section object from database or just field course_sections.section
     *     if omitted the course view page is returned
     * @param array $options options for view URL. At the moment core uses:
     *     'navigation' (bool) if true and section has no separate page, the function returns null
     *     'sr' (int) used by multipage formats to specify to which section to return
     * @return null|moodle_url
     */
    public function get_view_url($section, $options = array()) {
        $url = new moodle_url('/course/view.php', array('id' => $this->courseid));

        $sectionno = $this->get_section_number($section);
        $section = $this->get_section($sectionno);
        if ($sectionno && (!$section->uservisible || !$this->is_section_real_available($section))) {
            return empty($options['navigation']) ? $url : null;
        }

        if (array_key_exists('sr', $options)) {
            // return to the page for section with number $sr
            $url->param('section', $options['sr']);
            if ($sectionno) {
                $url->set_anchor('section-'.$sectionno);
            }
        } else if ($sectionno) {
            // check if this section has separate page
            if ($section->collapsed == FORMAT_FLEXSECTIONS_COLLAPSED) {
                $url->param('sectionid', $section->id);
                return $url;
            }
            // find the parent (or grandparent) page that is displayed on separate page
            $url->param('sectionid', $this->find_collapsed_parent($section->parent, true));
            $url->set_anchor('section-'.$sectionno);
            return $url;
        }
        return $url;
    }

    /**
     * Returns either section or it's parent or grandparent, whoever first is collapsed
     *
     * @param int|section_info $section
     * @return int
     */
    protected function find_collapsed_parent($section, $returnid = false) {
        $section = $this->get_section($section);
        if (!$section->section || $section->collapsed == FORMAT_FLEXSECTIONS_COLLAPSED) {
            return $returnid ? $section->id : $section->section;
        } else {
            return $this->find_collapsed_parent($section->parent, $returnid);
        }
    }

    /**
     * Returns the information about the ajax support in the given source format
     *
     * The returned object's property (boolean)capable indicates that
     * the course format supports Moodle course ajax features.
     * The property (array)testedbrowsers can be used as a parameter for {@link ajaxenabled()}.
     *
     * @return stdClass
     */
    public function supports_ajax() {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        $ajaxsupport->testedbrowsers = array('MSIE' => 6.0, 'Gecko' => 20061111, 'Safari' => 531, 'Chrome' => 6.0);
        return $ajaxsupport;
    }

    /**
     * Loads all of the course sections into the navigation
     *
     * This method is called from {@link global_navigation::load_course_sections()}
     *
     * When overwriting please note that navigationlib relies on using the correct values for
     * arguments $type and $key in {@link navigation_node::add()}
     *
     * Example of code creating a section node:
     * $sectionnode = $node->add($sectionname, $url, navigation_node::TYPE_SECTION, null, $section->id);
     * $sectionnode->nodetype = navigation_node::NODETYPE_BRANCH;
     *
     * Example of code creating an activity node:
     * $activitynode = $sectionnode->add($activityname, $action, navigation_node::TYPE_ACTIVITY, null, $activity->id, $icon);
     * if (global_navigation::module_extends_navigation($activity->modname)) {
     *     $activitynode->nodetype = navigation_node::NODETYPE_BRANCH;
     * } else {
     *     $activitynode->nodetype = navigation_node::NODETYPE_LEAF;
     * }
     *
     * Also note that if $navigation->includesectionnum is not null, the section with this relative
     * number needs is expected to be loaded
     *
     * @param global_navigation $navigation
     * @param navigation_node $node The course node within the navigation
     */
    public function extend_course_navigation($navigation, navigation_node $node) {
        global $PAGE;
        // if course format displays section on separate pages and we are on course/view.php page
        // and the section parameter is specified, make sure this section is expanded in
        // navigation
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
        return array();
    }

    /**
     * Checks if given section has another section among it's parents
     *
     * @param int|section_info $section child section
     * @param int $parentnum parent section number
     * @return boolean
     */
    protected function section_has_parent($section, $parentnum) {
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
            // some error
            return false;
        } else {
            return $this->section_has_parent($section->parent, $parentnum);
        }
    }

    /**
     * Adds a section to navigation node, loads modules and subsections if necessary
     *
     * @param global_navigation $navigation
     * @param navigation_node $node
     * @param section_info $section
     * @return null|navigation_node
     */
    protected function navigation_add_section($navigation, navigation_node $node, $section) {
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
     * Adds a course module to the navigation node
     *
     * @param navigation_node $node
     * @param cm_info $cm
     * @return null|navigation_node
     */
    protected function navigation_add_activity(navigation_node $node, $cm) {
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
     * Custom action after section has been moved in AJAX mode
     *
     * Used in course/rest.php
     *
     * @return array This will be passed in ajax respose
     */
    public function ajax_section_move() {
        global $PAGE;
        $titles = array();
        $modinfo = get_fast_modinfo($this->courseid);
        $renderer = $this->get_renderer($PAGE);
        if ($renderer && ($sections = $modinfo->get_section_info_all())) {
            foreach ($sections as $number => $section) {
                $titles[$number] = $renderer->section_title($section, $this->get_course());
            }
        }
        return array('sectiontitles' => $titles, 'action' => 'move');
    }

    /**
     * Returns the list of blocks to be automatically added for the newly created course
     *
     * @return array of default blocks, must contain two keys BLOCK_POS_LEFT and BLOCK_POS_RIGHT
     *     each of values is an array of block names (for left and right side columns)
     */
    public function get_default_blocks() {
        return array(
            BLOCK_POS_LEFT => array(),
            BLOCK_POS_RIGHT => array('search_forums', 'news_items', 'calendar_upcoming', 'recent_activity')
        );
    }

    /**
     * Definitions of the additional options that this course format uses for course
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        return array();
    }

    /**
     * Definitions of the additional options that this course format uses for section
     *
     * See {@link format_base::course_format_options()} for return array definition.
     *
     * Additionally section format options may have property 'cache' set to true
     * if this option needs to be cached in {@link get_fast_modinfo()}. The 'cache' property
     * is recommended to be set only for fields used in {@link format_base::get_section_name()},
     * {@link format_base::extend_course_navigation()} and {@link format_base::get_view_url()}
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
                'default' => COURSE_DISPLAY_SINGLEPAGE,
            )
        );
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
            // seciton 0 does not have parent
            return;
        }

        // move all modules and activities from this section to parent
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

        // move the section to be removed to the end (this will re-number other sections)
        $this->move_section($section->section, 0);
        // delete it completely
        $params = array('courseid' => $this->courseid,
                    'sectionid' => $section->id);
        $transaction = $DB->start_delegated_transaction();
        $DB->delete_records('course_format_options', $params);
        $DB->delete_records('course_sections', array('id' => $section->id));
        $transaction->allow_commit();
        rebuild_course_cache($this->courseid, true);
    }

    /**
     * Completely removes a section, all subsections and activities they contain
     *
     * @param section_info $section
     */
    protected function delete_section_int($section) {
        global $DB;
        if (!$section->section) {
            // section 0 does not have parent
            return;
        }

        $sectionid = $section->id;

        // move the section to be removed to the end (this will re-number other sections)
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
     * Returns true if we are on /course/view.php page
     *
     * @return bool
     */
    public function on_course_view_page() {
        global $PAGE;
        return ($PAGE->has_set_url() &&
                $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)
                );
    }

    /**
     * If we are on course/view.php page return the 'section' attribute from query
     *
     * @return int
     */
    public function get_viewed_section() {
        global $PAGE;
        if ($this->on_course_view_page()) {
            return $PAGE->url->get_param('section');
        }
        return 0;
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

            // if requested, create new section and redirect to course view page
            $addchildsection = optional_param('addchildsection', null, PARAM_INT);
            if ($addchildsection !== null && has_capability('moodle/course:update', $context)) {
                $sectionnum = $this->create_new_section($addchildsection);
                $url = course_get_url($this->courseid, $sectionnum);
                redirect($url);
            }

            // if requested, merge the section content with parent and remove the section
            $mergeup = optional_param('mergeup', null, PARAM_INT);
            if ($mergeup && confirm_sesskey() && has_capability('moodle/course:update', $context)) {
                $section = $this->get_section($mergeup, MUST_EXIST);
                $this->mergeup_section($section);
                $url = course_get_url($this->courseid, $section->parent);
                redirect($url);
            }

            // if requested, delete the section
            $deletesection = optional_param('deletesection', null, PARAM_INT);
            if ($deletesection && confirm_sesskey() && has_capability('moodle/course:update', $context)
                    && optional_param('confirm', 0, PARAM_INT) == 1) {
                $section = $this->get_section($deletesection, MUST_EXIST);
                $parent = $section->parent;
                $this->delete_section_int($section);
                $url = course_get_url($this->courseid, $parent);
                redirect($url);
            }

            // if requested, move section
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

            // if requested, switch collapsed attribute
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

            // set course marker if required
            $marker = optional_param('marker', null, PARAM_INT);
            if ($marker !== null && has_capability('moodle/course:setcurrentsection', $context) && confirm_sesskey()) {
                if ($marker > 0) {
                    // set marker
                    $url = course_get_url($this->courseid, $marker, array('sr' => $this->get_viewed_section()));
                    course_set_marker($this->courseid, $marker);
                    redirect($url);
                } else if ($this->get_course()->marker) {
                    // remove marker
                    $url = course_get_url($this->courseid, $this->get_course()->marker,
                            array('sr' => $this->get_viewed_section()));
                    course_set_marker($this->courseid, 0);
                    redirect($url);
                }
            }

            // change visibility if required
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
     * Adds format options elements to the course/section edit form
     *
     * This function is called from {@link course_edit_form::definition_after_data()}
     *
     * @param MoodleQuickForm $mform form the elements are added to
     * @param bool $forsection 'true' if this is a section edit form, 'false' if this is course edit form
     * @return array array of references to the added form elements
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        $elements = parent::create_edit_form_elements($mform, $forsection);
        if ($forsection && ($section0 = $this->get_section(0))) {
            // Disable/hide "collapsed" control for general section. Hiding is availabe in Moodle 3.4 and above.
            if (method_exists($mform, 'hideIf')) {
                $mform->hideIf('collapsed', 'id', 'eq', $section0->id);
            } else {
                $mform->disabledIf('collapsed', 'id', 'eq', $section0->id);
            }
        }
        return $elements;
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
        $sectionnumber = $this->get_section_number($section);
        if (!$sectionnumber && !$visibility) {
            // can not hide section with number 0
            return;
        }
        $section = $this->get_section($section);
        if ($visibility && $section->parent && !$this->get_section($section->parent)->visible) {
            // can not set section visible when parent is hidden
            return;
        }
        $ch = array($section);
        while (!empty($ch)) {
            $chlast = $ch;
            $ch = array();
            foreach ($chlast as $s) {
                // store copy of attributes to avoid rebuilding course cache when we need to access section properties
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
     * Returns a list of all controls available for particular section on particular page
     *
     * @param int|section_info $section
     * @return array of format_flexsections_edit_control
     */
    public function get_section_edit_controls($section) {
        global $PAGE;
        $controls = array();
        if (!$PAGE->user_is_editing()) {
            return $controls;
        }
        $section = $this->get_section($section);
        $sectionnum = $section->section;
        $course = $this->get_course();
        $context = context_course::instance($this->courseid);
        $movingsection = $this->is_moving_section();
        $sr = $this->get_viewed_section(); // section to return to

        // Collapse/expand
        if ($sectionnum && has_capability('moodle/course:update', $context) && $sectionnum != $sr) {
            $switchcollapsedurl = course_get_url($course, $sr);
            $switchcollapsedurl->params(array('switchcollapsed' => $section->section, 'sesskey' => sesskey()));
            if ($section->collapsed == FORMAT_FLEXSECTIONS_EXPANDED) {
                $text = new lang_string('showcollapsed', 'format_flexsections');
                $class = 'expanded';
            } else {
                $text = new lang_string('showexpanded', 'format_flexsections');
                $class = 'collapsed';
            }
            $controls[] = new format_flexsections_edit_control($class, $switchcollapsedurl, $text);
        }

        // Set marker
        if ($sectionnum && has_capability('moodle/course:setcurrentsection', $context)) {
            $setmarkerurl = course_get_url($course, $sr);
            if ($course->marker == $section->section) {
                $marker = 0;
                $text = new lang_string('removemarker', 'format_flexsections');
                $class = 'marked';
            } else {
                $marker = $section->section;
                $text = new lang_string('setmarker', 'format_flexsections');
                $class = 'marker';
            }
            $setmarkerurl->params(array('marker' => $marker, 'sesskey' => sesskey()));
            $controls[] = new format_flexsections_edit_control($class, $setmarkerurl, $text);
        }

        // Edit section control
        if (has_capability('moodle/course:update', $context)) {
            $editurl = new moodle_url('/course/editsection.php', array('id' => $section->id, 'sr' => $sr));
            $text = new lang_string('edit');
            $controls[] = new format_flexsections_edit_control('settings', $editurl, $text);
        }

        // Merge-up section control
        if ($sectionnum && has_capability('moodle/course:update', $context)) {
            $mergeupurl = course_get_url($course, $sr);
            $mergeupurl->params(array('mergeup' => $section->section, 'sesskey' => sesskey()));
            $text = new lang_string('mergeup', 'format_flexsections');
            $controls[] = new format_flexsections_edit_control('mergeup', $mergeupurl, $text);
        }

        // Delete section control
        if ($sectionnum && has_capability('moodle/course:update', $context)) {
            $deleteurl = course_get_url($course, $sr);
            $deleteurl->params(array('deletesection' => $section->section, 'sesskey' => sesskey()));
            $text = new lang_string('deletesection', 'format_flexsections');
            $controls[] = new format_flexsections_edit_control('delete', $deleteurl, $text);
        }

        // Move section control
        if ($sectionnum && !$movingsection && has_capability('moodle/course:update', $context) && $sectionnum != $sr) {
            $moveurl = course_get_url($course, $section->section, array('sr' => $sr));
            $moveurl->params(array('moving' => $section->section, 'sesskey' => sesskey()));
            $text = new lang_string('move');
            $controls[] = new format_flexsections_edit_control('move', $moveurl, $text);
        }

        if ($sectionnum && has_capability('moodle/course:sectionvisibility', $context)) {
            if ($section->visible) {
                $hideurl = course_get_url($course, $sr);
                $hideurl->params(array('hide' => $section->section, 'sesskey' => sesskey()));
                $text = new lang_string('hide');
                $controls[] = new format_flexsections_edit_control('hide', $hideurl, $text);
            } else {
                if ($section->parent && !$this->get_section($section->parent)->visible) {
                    $controls[] = new format_flexsections_edit_control('show', null, '');
                } else {
                    $showurl = course_get_url($course, $sr);
                    $showurl->params(array('show' => $section->section, 'sesskey' => sesskey()));
                    $text = new lang_string('show');
                    $controls[] = new format_flexsections_edit_control('show', $showurl, $text);
                }
            }
        }

        return $controls;
    }

    /**
     * Returns control 'Move here' for particular parent section
     *
     * @param int|section_info $parent
     * @param int|section_info $before
     * @return array of controls (0 or 1 element)
     */
    public function get_edit_control_movehere($parent, $before, $sr = null) {
        $movingsection = $this->is_moving_section();
        if (!$movingsection || !$this->can_move_section_to($movingsection, $parent, $before)) {
            return null;
        }

        $beforenum = $this->get_section_number($before);
        $parentnum = $this->get_section_number($parent);

        $movelink = course_get_url($this->courseid);
        $movelink->params(array('movesection' => $movingsection, 'moveparent' => $parentnum));
        if ($beforenum) {
            $movelink->params(array('movebefore' => $beforenum));
        }
        if ($sr !== null) {
            $movelink->params(array('sr' => $sr));
        }
        $str = strip_tags(get_string('movefull', '', "'".$this->get_section_name($movingsection)."'"));
        return new format_flexsections_edit_control('movehere', $movelink, $str);
    }

    /**
     * Returns a control to exit the section moving mode
     *
     * @return null|format_flexsections_edit_control
     */
    public function get_edit_controls_cancelmoving() {
        global $USER;
        $controls = array();
        // cancel moving section
        $movingsection = $this->is_moving_section();
        if ($movingsection) {
            $cancelmovingurl = course_get_url($this->courseid, $this->get_viewed_section());
            $str = strip_tags(get_string('cancelmoving', 'format_flexsections', $this->get_section_name($movingsection)));
            $controls[] = new format_flexsections_edit_control('cancelmovingsection', $cancelmovingurl, $str);
        }
        // cancel moving activity
        if (ismoving($this->courseid)) {
            $cancelmovingurl = new moodle_url('/course/mod.php',
                    array('sesskey' => sesskey(), 'cancelcopy' => true, 'sr' => $this->get_viewed_section));
            $str = strip_tags(get_string('cancelmoving', 'format_flexsections', $USER->activitycopyname));
            $controls[] = new format_flexsections_edit_control('cancelmovingactivity', $cancelmovingurl, $str);
        }
        return $controls;
    }

    /**
     * Returns the control to add a (sub)section
     *
     * @param int|section_info $parentsection
     * @return null|format_flexsections_edit_control
     */
    public function get_add_section_control($parentsection) {
        global $PAGE;
        if (!$PAGE->user_is_editing()) {
            return null;
        }
        $parentsection = $this->get_section_number($parentsection);
        $url = course_get_url($this->courseid, $this->get_viewed_section());
        $url->param('addchildsection', $parentsection);
        if ($parentsection) {
            $text = new lang_string('addsubsection', 'format_flexsections');
        } else {
            $text = new lang_string('addsection', 'format_flexsections');
        }
        return new format_flexsections_edit_control('addsection', $url, $text);
    }

    /**
     * If in section moving mode returns section number, otherwise returns null
     *
     * @return null|int
     */
    public function is_moving_section() {
        global $PAGE;
        if ($this->on_course_view_page() && $PAGE->user_is_editing()) {
            return optional_param('moving', null, PARAM_INT);
        }
        return null;
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
        // check that $parent is not subsection of $section
        if ($section->section == $parent->section || $this->section_has_parent($parent, $section->section)) {
            return false;
        }

        if ($before) {
            if (is_string($before)) {
                $before = (int)$before;
            }
            $before = $this->get_section($before);
            // check that it's a subsection of $parent
            if (!$before || $before->parent !== $parent->section) {
                return false;
            }
        }

        if ($section->parent == $parent->section) {
            // section's parent is not being changed
            // do not insert section directly before or after itself
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
     * Returns the list of direct subsections of the specified section
     *
     * @param int|section_info $section
     * @return array
     */
    public function get_subsections($section) {
        $sectionnum = $this->get_section_number($section);
        $subsections = array();
        foreach ($this->get_sections() as $num => $subsection) {
            if ($subsection->parent == $sectionnum && $num != $sectionnum) {
                $subsections[$num] = $subsection;
            }
        }
        return $subsections;
    }

    /**
     * Returns the section relative number regardless whether argument is an object or an int
     *
     * @param int|section_info $section
     * @return int
     */
    protected function get_section_number($section) {
        if ($section === null || $section === '') {
            return null;
        } else if (is_object($section)) {
            return $section->section;
        } else {
            return (int)$section;
        }
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
    protected function reorder_sections(&$neworder, $cursection, $movedsectionnum = null, $movetoparentnum = null, $movebeforenum = null) {
        // normalise arguments
        $cursection = $this->get_section($cursection);
        $movetoparentnum = $this->get_section_number($movetoparentnum);
        $movebeforenum = $this->get_section_number($movebeforenum);
        $movedsectionnum = $this->get_section_number($movedsectionnum);
        if ($movedsectionnum === null) {
            $movebeforenum = $movetoparentnum = null;
        }

        // ignore section being moved
        if ($movedsectionnum !== null && $movedsectionnum == $cursection->section) {
            return;
        }

        // add current section to $neworder
        $neworder[$cursection->id] = count($neworder);
        // loop through subsections and reorder them (insert $movedsectionnum if necessary)
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
            // section is changing parent and new parent has different visibility than the section
            if ($section->visible) {
                // visible section is moved under hidden parent
                $updatesectionvisible = 0;
                $updatesectionvisibleold = 1;
            } else {
                // hidden section is moved under visible parent
                if ($section->visibleold) {
                    $updatesectionvisible = 1;
                    $updatesectionvisibleold = 1;
                }
            }
        }

        // find the changes in the sections numbering
        $origorder = array();
        foreach ($this->get_sections() as $subsection) {
            $origorder[$subsection->id] = $subsection->section;
        }
        $neworder = array();
        $this->reorder_sections($neworder, 0, $section->section, $parent, $before);
        if (count($origorder) != count($neworder)) {
            die('Error in sections hierarchy'); // TODO
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
            if ($this->get_section_number($parent) === $num) {
                $newparentnum = $neworder[$id];
            }
        }

        if (empty($changes) && $newparentnum == $section->parent) {
            return $newsectionnumber;
        }

        // build array of required changes in field 'parent'
        // $changeparent[sectionid] = newsectionnum;
        $changeparent = array();
        foreach ($this->get_sections() as $subsection) {
            foreach ($changes as $id => $change) {
                if ($subsection->parent == $change['old']) {
                    $changeparent[$subsection->id] = $change['new'];
                }
            }
        }
        $changeparent[$section->id] = $newparentnum;

        // Update all in database in one transaction
        $transaction = $DB->start_delegated_transaction();
        // Update sections numbers in 2 steps to avoid breaking database uniqueness constraint
        foreach ($changes as $id => $change) {
            $DB->set_field('course_sections', 'section', -$change['new'], array('id' => $id));
        }
        foreach ($changes as $id => $change) {
            $DB->set_field('course_sections', 'section', $change['new'], array('id' => $id));
        }
        // change parents of their subsections
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
     * Course-specific information to be output immediately above content on any course page
     *
     * See {@link format_base::course_header()} for usage
     *
     * @return null|renderable null for no output or object with data for plugin renderer
     */
    public function course_content_header() {
        global $PAGE;

        // if we are on course view page for particular section, return 'back to parent' control
        if ($this->get_viewed_section()) {
            $section = $this->get_section($this->get_viewed_section());
            if ($section->parent) {
                $sr = $this->find_collapsed_parent($section->parent);
                $text = new lang_string('backtosection', 'format_flexsections', $this->get_section_name($section->parent));
            } else {
                $sr = 0;
                $text = new lang_string('backtocourse', 'format_flexsections', $this->get_course()->fullname);
            }
            $url = $this->get_view_url($section->section, array('sr' => $sr));
            return new format_flexsections_edit_control('backto', $url, strip_tags($text));
        }

        // if we are on module view page, return 'back to section' control
        if ($PAGE->context && $PAGE->context->contextlevel == CONTEXT_MODULE && $PAGE->cm) {
            $sectionnum = $PAGE->cm->sectionnum;
            if ($sectionnum) {
                $text = new lang_string('backtosection', 'format_flexsections', $this->get_section_name($sectionnum));
            } else {
                $text = new lang_string('backtocourse', 'format_flexsections', $this->get_course()->fullname);
            }
            return new format_flexsections_edit_control('backto', $this->get_view_url($sectionnum), strip_tags($text));
        }

        return parent::course_content_header();
    }

    /**
     * Checks if section is really available for the current user (analyses parent section available)
     *
     * @param int|section_info $section
     * @return bool
     */
    public function is_section_real_available($section) {
        if (($this->get_section_number($section) == 0)) {
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
     * Checks if all section's parents are available
     *
     * @param int|section_info $section
     * @return bool
     */
    public function is_section_parent_available($section) {
        if (($this->get_section_number($section) == 0)) {
            // Section 0 is always available.
            return true;
        }
        $section = $this->get_section($section, MUST_EXIST);
        $parent = $this->get_section($section->parent, MUST_EXIST);
        return $parent->available && $this->is_section_parent_available($parent);
    }

    /**
     * Allows to specify for modinfo that section is not available even when it is visible and conditionally available.
     *
     * @param section_info $section
     * @param bool $available
     * @param string $availableinfo
     */
    public function section_get_available_hook(section_info $section, &$available, &$availableinfo) {
        if ($available && !$this->is_section_parent_available($section)) {
            $available = false;
        }
    }

    /**
     * Prepares the templateable object to display section name
     *
     * @param \section_info|\stdClass $section
     * @param bool $linkifneeded
     * @param bool $editable
     * @param null|lang_string|string $edithint
     * @param null|lang_string|string $editlabel
     * @return \core\output\inplace_editable
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
        return parent::inplace_editable_render_section_name($section, $linkifneeded, $editable, $edithint, $editlabel);
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
}

/**
 * Represents one edit control
 *
 * @package    format_flexsections
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_flexsections_edit_control implements renderable {
    public $url;
    public $text;
    public $class;
    public function __construct($class, $url, $text) {
        $this->class = $class;
        $this->url = $url;
        $this->text = $text;
    }
}

/**
 * Implements callback inplace_editable() allowing to edit values in-place
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return \core\output\inplace_editable
 */
function format_flexsections_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            array($itemid, 'flexsections'), MUST_EXIST);
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
