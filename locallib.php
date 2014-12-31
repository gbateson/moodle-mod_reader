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
 * Library of internal classes and functions for module reader
 *
 * All the reader specific functions, needed to implement the module
 * logic, should go to here. Instead of having bunch of function named
 * reader_something() taking the reader instance as the first
 * parameter, we use a class reader that provides all methods.
 *
 * @package   mod-reader
 * @copyright 2013 Gordon Bateson <gordon.bateson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die;

/** Include required files */
require_once($CFG->dirroot.'/mod/reader/lib.php');

/**
 * Full-featured reader API
 *
 * This wraps the reader database record with a set of methods that are called
 * from the module itself. The class should be initialized right after you get
 * $reader, $cm and $course records at the begining of the script.
 */
class mod_reader {

    /** @var stdclass course module record */
    public $cm;

    /** @var stdclass course record */
    public $course;

    /** @var stdclass context object */
    public $context;

    /** @var array of attempts */
    public $attempts;

    /**
     * Initializes the reader API instance using the data from DB
     *
     * Makes deep copy of all passed records properties. Replaces integer $course attribute
     * with a full database record (course should not be stored in instances table anyway).
     *
     * The method is "protected" to prevent it being called directly. To create a new
     * instance of this class please use the self::create() method (see below).
     *
     * @param stdclass $dbrecord Reader instance data from the {reader} table
     * @param stdclass $cm       Course module record as returned by {@link get_coursemodule_from_id()}
     * @param stdclass $course   Course record from {course} table
     * @param stdclass $context  The context of the reader instance
     * @param stdclass $attempt  attempt data from the {reader_attempts} table
     */
    private function __construct($dbrecord=null, $cm=null, $course=null, $context=null, $attempt=null) {
        global $COURSE;

        if ($dbrecord) {
            foreach ($dbrecord as $field => $value) {
                $this->$field = $value;
            }
        }

        if ($cm) {
            $this->cm = $cm;
        }

        if ($course) {
            $this->course = $course;
        } else {
            $this->course = $COURSE;
        }

        if ($context) {
            $this->context = $context;
        } else if ($cm) {
            $this->context = self::context(CONTEXT_MODULE, $cm->id);
        } else {
            $this->context = self::context(CONTEXT_COURSE, $this->course->id);
        }

        if ($attempt) {
            $this->attempt = $attempt;
        }

        $this->time = time();
    }

    ////////////////////////////////////////////////////////////////////////////////
    // Static methods                                                             //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * Creates a new Reader object
     *
     * @param stdclass $dbrecord a row from the reader table
     * @param stdclass $cm a row from the course_modules table
     * @param stdclass $course a row from the course table
     * @return reader the new reader object
     */
    static public function create($dbrecord, $cm, $course, $context=null, $attempt=null) {
        return new mod_reader($dbrecord, $cm, $course, $context, $attempt);
    }

    /**
     * text_editors_options
     *
     * @param xxx $context
     * @return xxx
     */
    public static function text_editors_options($context)  {
        return array('subdirs' => 1, 'maxbytes' => 0, 'maxfiles' => EDITOR_UNLIMITED_FILES,
                     'changeformat' => 1, 'context' => $context, 'noclean' => 1, 'trusttext' => 0);
    }

    /**
     * context
     *
     * a wrapper method to offer consistent API to get contexts
     * in Moodle 2.0 and 2.1, we use context() function
     * in Moodle >= 2.2, we use static context_xxx::instance() method
     *
     * @param integer $contextlevel
     * @param integer $instanceid (optional, default=0)
     * @param int $strictness (optional, default=0 i.e. IGNORE_MISSING)
     * @return required context
     * @todo Finish documenting this function
     */
    public static function context($contextlevel, $instanceid=0, $strictness=0) {
        if (class_exists('context_helper')) {
            // use call_user_func() to prevent syntax error in PHP 5.2.x
            // return $classname::instance($instanceid, $strictness);
            $class = context_helper::get_class_for_level($contextlevel);
            return call_user_func(array($class, 'instance'), $instanceid, $strictness);
        } else {
            return get_context_instance($contextlevel, $instanceid);
        }
    }

    /**
     * textlib
     *
     * a wrapper method to offer consistent API for textlib class
     * in Moodle 2.0 - 2.1, $textlib is first initiated, then called
     * in Moodle 2.2 - 2.5, we use only static methods of the "textlib" class
     * in Moodle >= 2.6, we use only static methods of the "core_text" class
     *
     * @param string $method
     * @param mixed any extra params that are required by the textlib $method
     * @return result from the textlib $method
     * @todo Finish documenting this function
     */
    public static function textlib() {
        if (class_exists('core_text')) {
            // Moodle >= 2.6
            $textlib = 'core_text';
        } else if (method_exists('textlib', 'textlib')) {
            // Moodle 2.0 - 2.1
            $textlib = textlib_get_instance();
        } else {
            // Moodle 2.3 - 2.5
            $textlib = 'textlib';
        }
        $args = func_get_args();
        $method = array_shift($args);
        $callback = array($textlib, $method);
        return call_user_func_array($callback, $args);
    }

    /**
     * get_numsections
     *
     * a wrapper method to offer consistent API for $course->numsections
     * in Moodle 2.0 - 2.3, "numsections" is a field in the "course" table
     * in Moodle >= 2.4, "numsections" is in the "course_format_options" table
     *
     * @uses $DB
     * @param object $course
     * @return integer $numsections
     */
    public static function get_numsections($course) {
        global $DB;
        if (is_numeric($course)) {
            $course = $DB->get_record('course', array('id' => $course));
        }
        if ($course && isset($course->id)) {
            if (isset($course->numsections)) {
                // Moodle <= 2.3
                return $course->numsections;
            }
            if (isset($course->format)) {
                // Moodle >= 2.4
                return $DB->get_field('course_format_options', 'value', array('courseid' => $course->id, 'format' => $course->format, 'name' => 'numsections'));
            }
        }
        return 0; // shouldn't happen !!
    }

    /**
     * set_numsections
     *
     * a wrapper method to offer consistent API for $course->numsections
     * in Moodle 2.0 - 2.3, "numsections" is a field in the "course" table
     * in Moodle >= 2.4, "numsections" is in the "course_format_options" table
     *
     * ================================================================
     * NOTE: maybe we should check function_exists('course_get_format')
     * in Moodle 2.4, and if it exists, use that to set "numsections"
     * ================================================================
     *
     * @uses $DB
     * @param object $course
     * @param integer $numsections
     * @return void, but may update "course" or "course_format_options" table
     */
    public static function set_numsections($course, $numsections) {
        global $DB;
        if (is_numeric($course)) {
            $course = $DB->get_record('course', array('id' => $course));
        }
        if (empty($course) || empty($course->id)) {
            return false;
        }
        if (isset($course->numsections)) {
            return $DB->set_field('course', 'numsections', $numsections, array('id' => $course->id));
        } else {
            return $DB->set_field('course_format_options', 'value', $numsections, array('courseid' => $course->id, 'format' => $course->format));
        }
    }

    /**
     * optional_param_array
     *
     * a wrapper method to offer consistent API for getting array parameters
     *
     * @param string $name the name of the parameter
     * @param mixed $default
     * @param mixed $type one of the PARAM_xxx constants
     * @return either an array of form values or the $default value
     */
    public static function optional_param_array($name, $default, $type) {
        $optional_param_array = 'optional_param';
        if (function_exists('optional_param_array')) {
            switch (true) {
                case (isset($_POST[$name]) && is_array($_POST[$name])): $optional_param_array = 'optional_param_array'; break;
                case (isset($_GET[$name])  && is_array($_GET[$name])) : $optional_param_array = 'optional_param_array'; break;
            }
        }
        return $optional_param_array($name, $default, $type);
    }

    /**
     * set_user_editing
     */
    static public function set_user_editing() {
        global $USER;
        $editmode = optional_param('editmode', null, PARAM_BOOL);
        if (! is_null($editmode)) {
            $USER->editing = $editmode;
        }
    }

    /**
     * is_loggedinas
     *
     * a wrapper method to offer consistent API for checking
     * if a teacher/admin is logged in as a different user
     */
     static public function is_loggedinas() {
        if (class_exists('\\core\\session\\manager')) {
            return \core\session\manager::is_loggedinas();
        } else {
            return session_is_loggedinas();
        }
    }

    /**
     * loginas
     *
     * a wrapper method to offer consistent API for allowing
     * a teacher/admin to log in as a different user
     *
     * @param  integer  $userid
     * @param  object   $context
     */
     static public function loginas($userid, $context) {
        if (class_exists('\\core\\session\\manager')) {
            \core\session\manager::loginas($userid, $context);
        } else {
            session_loginas($userid, $context);
        }
    }

    /**
     * Returns a js module object for the Reader module
     *
     * @param array $requires
     *    e.g. array('base', 'dom', 'event-delegate', 'event-key')
     * @return array $strings
     *    e.g. array(
     *        array('timesup', 'quiz'),
     *        array('functiondisabledbysecuremode', 'quiz'),
     *        array('flagged', 'question')
     *    )
     */
    public static function get_js_module(array $requires = null, array $strings = null) {
        return array(
            'name' => 'mod_reader',
            'fullpath' => '/mod/reader/module.js',
            'requires' => $requires,
            'strings' => $strings,
        );
    }

    ////////////////////////////////////////////////////////////////////////////////
    // Reader URLs API                                                            //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * @return moodle_url of this reader page
     */
    public function url($url, $params=null, $cm=null) {

        if ($params===null) {
            $params = array();
        }

        if (isset($params['id'])) {
            // do nothing
        } else if ($id = optional_param('id', 0, PARAM_INT)) {
            $params['id'] = $id;
        } else if (isset($cm)) {
            $params['id'] = $cm->id;
        } else if (isset($this->cm)) {
            $params['id'] = $this->cm->id;
        }

        if (isset($params['tab'])) {
            // do nothing
        } else if ($tab = optional_param('tab', 0, PARAM_INT)) {
            $params['tab'] = $tab;
        }

        if (isset($params['mode'])) {
            // do nothing
        } else if ($mode = optional_param('mode', '', PARAM_ALPHA)) {
            $params['mode'] = $mode;
        }

        return new moodle_url($url, $params);
    }

    /**
     * @return moodle_url of this reader's view page
     */
    public function view_url($cm=null) {
        if ($cm===null) {
            $url = '/mod/reader/view.php';
        } else {
            $url = '/mod/'.$cm->modname.'/view.php';
        }
        return $this->url($url, $params, $cm);
    }

    /**
     * @return moodle_url of this reader's view page
     */
    public function reports_url($params=null, $cm=null) {
        return $this->url('/mod/reader/admin/reports.php', $params, $cm);
    }

    /**
     * @return moodle_url of this reader's attempt page
     */
    public function attempt_url($framename='', $cm=null) {
        $params = array();
        if ($framename) {
            $params['framename'] = $framename;
        }
        return $this->url('/mod/reader/attempt.php', $params, $cm);
    }

    /**
     * @return moodle_url of this course's reader index page
     */
    public function index_url($course=null) {
        if (isset($course)) {
            $params = array('id' => $course->id);
        } else {
            $params = array('id' => $this->course->id);
        }
        return new moodle_url('/mod/reader/index.php', $params);
    }

    /**
     * @return moodle_url of this reader's course page
     */
    public function course_url($course=null) {
        if (isset($course)) {
            $params = array('id' => $course->id);
        } else {
            $params = array('id' => $this->course->id);
        }
        $sectionnum = 0;
        if (isset($course->coursedisplay) && defined('COURSE_DISPLAY_MULTIPAGE')) {
            // Moodle >= 2.3
            if ($course->coursedisplay==COURSE_DISPLAY_MULTIPAGE) {
                $courseid = $course->id;
                $sectionid = $this->cm->section;
                if ($modinfo = get_fast_modinfo($this->course)) {
                    $sections = $modinfo->get_section_info_all();
                    foreach ($sections as $section) {
                        if ($section->id==$sectionid) {
                            $sectionnum = $section->section;
                            break;
                        }
                    }
                }
                unset($modinfo, $sections, $section);
            }
        }
        if ($sectionnum) {
            $params['section'] = $sectionnum;
        }
        return new moodle_url('/course/view.php', $params);
    }

    /**
     * @return moodle_url of this reader's course grade page
     */
    public function grades_url($course=null) {
        if (isset($course)) {
            $params = array('id' => $course->id);
        } else {
            $params = array('id' => $this->course->id);
        }
        return new moodle_url('/grade/index.php', $params);
    }

    ////////////////////////////////////////////////////////////////////////////////
    // Reader capabilities API                                                    //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * can
     *
     * @return xxx
     */
    function can($name, $type='', $context=null) {
        $can = 'can'.$name;
        if (! isset($this->$can)) {
            if ($type==='') {
                $type = 'mod/reader';
            }
            if ($context===null) {
                $context = $this->context;
            }
            $this->$can = has_capability($type.':'.$name, $context);
        }
        return $this->$can;
    }

    /*
     * can_addinstance
     *
     * @return boolean
     **/
    public function can_addinstance() {
        return $this->can('addinstance');
    }

    /*
     * can_managebooks
     *
     * @return boolean
     **/
    public function can_manageattempts() {
        return $this->can('manageattempts');
    }

    /*
     * can_managebooks
     *
     * @return boolean
     **/
    public function can_managebooks() {
        return $this->can('managebooks');
    }

    /*
     * can_managequizzes
     *
     * @return boolean
     **/
    public function can_managequizzes() {
        return $this->can('managequizzes');
    }

    /*
     * can_manageremotesites
     *
     * @return boolean
     **/
    public function can_manageremotesites() {
        return $this->can('manageremotesites');
    }

    /*
     * can_manageusers
     *
     * @return boolean
     **/
    public function can_manageusers() {
        return $this->can('manageusers');
    }

    /*
     * can_managetools
     *
     * @return boolean
     **/
    public function can_managetools() {
        return $this->can('managetools');
    }

    /*
     * can_viewbooks
     *
     * @return boolean
     **/
    public function can_viewbooks() {
        return $this->can('viewbooks');
    }

    /*
     * can_viewreports
     *
     * @return boolean
     **/
    public function can_viewreports() {
        return $this->can('viewreports');
    }

    /*
     * get_delay
     *
     * @param integer $userid
     * @return boolean
     **/
    public function get_delay($userid=0, $groupid=0) {
        global $DB, $USER;

        if ($userid==0) {
            $userid = $USER->id;
        }
        if ($groupid==0 && $this->course->groupmode) {
            if ($groupid = groups_get_user_groups($this->course->id, $userid)) {
                if ($groupid = reset($groupid)) { // first grouping
                    if ($groupid = reset($groupid)) { // first group
                        if (isset($groupid->id)) {
                            $groupid = $groupid->id;
                        }
                    }
                }
            }
            if (empty($groupid)) {
                $groupid = 0;
            }
        }

        // get current reading level for current user in current reader
        if ($level = $DB->get_record('reader_levels', array('userid' => $userid, 'readerid' => $this->id))) {
            $level = $level->currentlevel;
        } else {
            $level = 0;
        }

        $select = '';
        $params = array();

        if (empty($this->id)) {
            $select .= 'readerid = ?';
            $params[] = 0;
        } else {
            $select .= '(readerid = ? OR readerid = ?)';
            array_push($params, 0, $this->id);
        }

        if ($groupid==0) {
            $select .= ' AND groupid = ?';
            $params[] = 0;
        } else {
            $select .= ' AND (groupid = ? OR groupid = ?)';
            array_push($params, 0, $groupid);
        }

        if ($level==0) {
            $select .= ' AND level = ?';
            $params[] = 0;
        } else {
            $select .= ' AND (level = ? OR level = ?)';
            array_push($params, 0, $level);
        }

        $sort = 'readerid DESC, groupid DESC, level DESC, delay ASC';
        if ($delay = $DB->get_records_select('reader_delays', $select, $params, $sort)) {
            $delay = reset($delay); // use shortest and most specific delay available
            $delay = $delay->delay;
        } else {
            $delay = 0;
        }

        return $delay;
    }

    /**
     * get_standard_modes
     *
     * define the names and order of the standard tab-modes for this renderer
     *
     * @return array of standard modes
     */
    static function get_standard_modes() {
        return array();
    }

    /**
     * get_modes
     *
     * @param string $directory path to dir below "mod/reader"
     * @param string $exclude   (optional, default='') modes to exclude
     * @return array of report modes
     */
    static public function get_modes($directory, $exclude='') {
        global $CFG;
        static $cache = array();

        if (! array_key_exists($directory, $cache)) {
            $modes = array(); // default modes
            $classfile = $CFG->dirroot.'/mod/reader/'.$directory.'/renderer.php';
            $classname = 'mod_reader_'.str_replace('/', '_', $directory).'_renderer';
            if (file_exists($classfile)) {
                require_once($classfile);
                if (method_exists($classname, 'get_standard_modes')) {
                    $modes = call_user_func(array($classname, 'get_standard_modes'));
                    // we use call_user_func() to prevent syntax error in PHP 5.2.x
                }
            }

            // all report plugins
            $plugins = get_list_of_plugins('mod/reader/'.$directory, $exclude);

            // remove missing standard reports
            $modes = array_intersect($modes, $plugins);

            // append custom reports, if any
            $plugins = array_diff($plugins, $modes);
            $modes = array_merge($modes, $plugins);

            // cache $modes for this $directory
            $cache[$directory] = $modes;
        }

        return $cache[$directory];
    }

    /**
     * get_mode
     *
     * @param string $directory path to dir below "mod/reader"
     * @param string $exclude   (optional, default='') modes to exclude
     * @param string $default   (optional, default='') default mode
     * @return string a valid report mode
     */
    static public function get_mode($directory, $exclude='', $default='') {
        $modes = self::get_modes($directory, $exclude);
        if ($mode = optional_param('mode', '', PARAM_ALPHA)) {
            if (in_array($mode, $modes)) {
                return $mode;
            }
        }
        if (count($modes)) {
            return reset($modes);
        }
        return $default;
    }

    /**
     * get_types
     *
     * @param string $directory path to dir below "mod/reader"
     * @param string $exclude   (optional, default='') types to exclude
     * @return string a valid report type
     */
    static public function get_types($directory, $exclude='') {
        global $CFG;
        static $cache = array();

        if (! array_key_exists($directory, $cache)) {
            $types = array(); // default $types
            $classfile = $CFG->dirroot.'/mod/reader/'.$directory.'/renderer.php';
            $classname = 'mod_reader_'.str_replace('/', '_', $directory).'_renderer';
            if (file_exists($classfile)) {
                require_once($classfile);
                if (method_exists($classname, 'get_standard_types')) {
                    $types = call_user_func(array($classname, 'get_standard_types'));
                    // we use call_user_func() to prevent syntax error in PHP 5.2.x
                }
            }
            $cache[$directory] = $types;
        }

        return $cache[$directory];
    }

    /**
     * get_type
     *
     * @param string $directory path to dir below "mod/reader"
     * @param string $exclude   (optional, default='') types to exclude
     * @param string $default   (optional, default=0) default type
     * @return string a valid report type
     */
    static public function get_type($directory, $exclude='', $default=0) {
        $types = self::get_types($directory, $exclude);
        $type = optional_param('type', null, PARAM_INT);
        if (is_numeric($type) && in_array($type, $types)) {
            return $type;
        }
        if (count($types)) {
            return reset($types);
        }
        return $default;
    }

    /**
     * to_stdclass
     *
     * @return xxx
     */
    public function to_stdclass() {
        $stdclass = new stdclass();
        $vars = get_object_vars($this);
        foreach ($vars as $name => $value) {
            if (is_object($this->$name) || is_array($this->$name)) {
                continue;
            }
            $stdclass->$name = $value;
        }
        // extra fields required for grades
        if (isset($this->course) && is_object($this->course)) {
            $stdclass->course = $this->course->id;
        }
        if (isset($this->cm) && is_object($this->cm)) {
            $stdclass->cmidnumber = $this->cm->idnumber;
        }
        $stdclass->modname = 'reader';
        return $stdclass;
    }
}
