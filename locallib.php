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
    private function __construct(stdclass $dbrecord, stdclass $cm, stdclass $course, stdclass $context=null, stdclass $attempt=null) {
        foreach ($dbrecord as $field => $value) {
            $this->$field = $value;
        }
        $this->cm = $cm;
        $this->course = $course;
        if ($context) {
            $this->context = $context;
        } else {
            $this->context = self::context(CONTEXT_MODULE, $this->cm->id);
        }
        if (is_null($attempt)) {
            // do nothing
        } else {
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
    static public function create(stdclass $dbrecord, stdclass $cm, stdclass $course, stdclass $context=null, stdclass $attempt=null) {
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
     * in Moodle 2.0 and 2.1, $textlib is first initiated, then called.
     * in Moodle >= 2.2, we use only static methods of the "textlib" class.
     *
     * @param string $method
     * @param mixed any extra params that are required by the textlib $method
     * @return result from the textlib $method
     * @todo Finish documenting this function
     */
    public static function textlib() {
        if (method_exists('textlib', 'textlib')) {
            $textlib = textlib_get_instance();
        } else {
            $textlib = 'textlib'; // Moodle >= 2.2
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
                return $course->numsections; // Moodle >= 2.3
            }
            if (isset($course->format)) {
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
     * @return moodle_url of this reader's view page
     */
    public function view_url($cm=null) {
        if (is_null($cm)) {
            $cm = $this->cm;
        }
        return new moodle_url('/mod/'.$cm->modname.'/view.php', array('id' => $cm->id));
    }

    /**
     * @return moodle_url of this reader's view page
     */
    public function report_url($mode='', $cm=null) {
        if (is_null($cm)) {
            $cm = $this->cm;
        }
        $params = array('id' => $cm->id);
        if ($mode) {
            $params['mode'] = $mode;
        }
        return new moodle_url('/mod/reader/report.php', $params);
    }

    /**
     * @return moodle_url of this reader's attempt page
     */
    public function attempt_url($framename='', $cm=null) {
        if (is_null($cm)) {
            $cm = $this->cm;
        }
        $params = array('id' => $cm->id);
        if ($framename) {
            $params['framename'] = $framename;
        }
        return new moodle_url('/mod/reader/attempt.php', $params);
    }

    /**
     * @return moodle_url of this course's reader index page
     */
    public function index_url($course=null) {
        if (is_null($course)) {
            $course = $this->course;
        }
        return new moodle_url('/mod/reader/index.php', array('id' => $course->id));
    }

    /**
     * @return moodle_url of this reader's course page
     */
    public function course_url($course=null) {
        if (is_null($course)) {
            $course = $this->course;
        }
        $params = array('id' => $course->id);
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
        if (is_null($course)) {
            $course = $this->course;
        }
        return new moodle_url('/grade/index.php', array('id' => $course->id));
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

    /**
     * get_report_modes
     *
     * @return array of report modes
     * @todo check for custom reports in "/mod/reader/report"
     */
    static public function get_report_modes() {
        $modes = array('usersummary', 'userdetailed', 'groupsummary', 'booksummary', 'bookdetailed', 'bookratings');
        return $modes;
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
            $stdclass->cmidnumber = $this->cm->id;
        }
        $stdclass->modname = 'reader';
        return $stdclass;
    }
}
