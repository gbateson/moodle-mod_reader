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
 * mod/reader/admin/books/download/downloader.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die;

/**
 * reader_downloader
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class reader_downloader {

    /**#@+
    * values for download $type
    *
    * @const integer
    */
    const BOOKS_WITHOUT_QUIZZES = 0;
    const BOOKS_WITH_QUIZZES    = 1;
    /**#@-*/

    /**#@+
    * values for $downloadmode
    *
    * @const integer
    */
    const NORMAL_MODE           = 0;
    const REPAIR_MODE           = 1;
    /**#@-*/

    /**#@+
    * values for $action
    *
    * @const integer
    */
    const ACTION_NONE           = 0;

    const ACTION_CATEGORYTYPE   = 1;
    const ACTION_CATEGORYID     = 2;
    const ACTION_CATEGORYTEXT   = 3;

    const ACTION_COURSETYPE     = 4;
    const ACTION_COURSEID       = 5;
    const ACTION_COURSETEXT     = 6;

    const ACTION_SECTIONTYPE    = 7;
    const ACTION_SECTIONNUM     = 8;
    const ACTION_SECTIONTEXT    = 9;
    /**#@-*/

    /**#@+
    * values for $targetcategorytype
    *
    * @const integer
    */
    const CATEGORYTYPE_DEFAULT  = 0;
    const CATEGORYTYPE_HIDDEN   = 1;
    const CATEGORYTYPE_VISIBLE  = 2;
    const CATEGORYTYPE_CURRENT  = 3;
    const CATEGORYTYPE_NEW      = 4;
    /**#@-*/

    /**#@+
    * values for $targetcoursetype
    *
    * @const integer
    */
    const COURSETYPE_DEFAULT    = 0;
    const COURSETYPE_HIDDEN     = 1;
    const COURSETYPE_VISIBLE    = 2;
    const COURSETYPE_CURRENT    = 3;
    const COURSETYPE_NEW        = 4;
    /**#@-*/

    /**#@+
    * values for $targetsectiontype
    *
    * @const integer
    */
    const SECTIONTYPE_DEFAULT   = 0;
    const SECTIONTYPE_HIDDEN    = 1;
    const SECTIONTYPE_VISIBLE   = 2;
    const SECTIONTYPE_LAST      = 3;
    const SECTIONTYPE_NEW       = 4;
    /**#@-*/

    /** sites from which we can download */
    public $remotesites = array();

    /** items that are available for download */
    public $available = array();

    /** items that have already been downloaded */
    public $downloaded = array();

    /**#@+
    * current course, course module, reader and output renderer
    *
    * @var object
    */
    public $course = null;
    public $cm     = null;
    public $reader = null;
    public $output = null;
    /**#@-*/

    /**#@+
    * default categoryid and courseid
    *
    * @var integer
    */
    public $defaultcategoryid = 0;
    public $defaultcourseid   = 0;
    /**#@-*/

    /**#@+
    * form parameters and their default values
    *
    * @var integer
    */
    public $targetcategorytype =  0;
    public $targetcategoryid   =  0;
    public $targetcategorytext = '';

    public $targetcoursetype   =  0;
    public $targetcourseid     =  0;
    public $targetcoursetext   = '';

    public $targetsectiontype  =  0;
    public $targetsectionnum   =  0;
    public $targetsectiontext  = '';
    /**#@-*/

    /** download progress bar */
    public $bar = null;

    /** if TRUE use "quiz_slots" table, otherwise use "quiz_question_instances" table */
    private $quiz_slots = null;

    /** if TRUE "quiz.questions" field exists, otherwise it doesn't */
    private $quiz_questions = null;

    /**
     * __construct
     *
     * @param xxx $reader
     * @param xxx $output renderer
     * @todo Finish documenting this function
     */
    public function __construct($output) {
        global $CFG, $DB;

        // get course category PHP library (Moodle >= 2.6)
        if (file_exists($CFG->dirroot.'/lib/coursecatlib.php')) {
            require_once($CFG->dirroot.'/lib/coursecatlib.php');
        }

        $this->course = $output->reader->course;
        $this->cm     = $output->reader->cm;
        $this->reader = $output->reader;
        $this->output = $output;

        // set default course and category
        if ($this->reader->usecourse) {
            $this->defaultcourseid = $this->reader->usecourse;
        } else {
            $this->defaultcourseid = get_config('mod_reader', 'usecourse');
        }
        if ($this->defaultcourseid) {
            $this->defaultcategoryid = $DB->get_field('course', 'category', array('id' => $this->defaultcourseid));
        } else {
            $this->defaultcategoryid = $DB->get_field('course_categories', 'MIN(id)', array());
        }

        // get page parameters
        $action = optional_param('action', self::ACTION_NONE, PARAM_INT);
        $params = (object)array(
            'targetcategorytype' => optional_param('targetcategorytype', 0,  PARAM_INT),
            'targetcategoryid'   => optional_param('targetcategoryid',   0,  PARAM_INT),
            'targetcategorytext' => optional_param('targetcategorytext', '', PARAM_TEXT),
            'targetcoursetype'   => optional_param('targetcoursetype',   0,  PARAM_INT),
            'targetcourseid'     => optional_param('targetcourseid',     0,  PARAM_INT),
            'targetcoursetext'   => optional_param('targetcoursetext',   '', PARAM_TEXT),
            'targetsectiontype'  => optional_param('targetsectiontype',  0,  PARAM_INT),
            'targetsectionnum'   => optional_param('targetsectionnum',   0,  PARAM_INT),
            'targetsectiontext'  => optional_param('targetsectiontext',  '', PARAM_TEXT),
        );

        // check consistency of child parameters
        switch ($action) {
            case self::ACTION_NONE:
            case self::ACTION_CATEGORYTYPE : $this->check_categorytype($params, 1); // 1
            case self::ACTION_CATEGORYID   : $this->check_categoryid($params,   1); // 2
            case self::ACTION_CATEGORYTEXT : $this->check_categorytext($params, 1); // 3

            case self::ACTION_COURSETYPE   : $this->check_coursetype($params,   1); // 4
            case self::ACTION_COURSEID     : $this->check_courseid($params,     1); // 5
            case self::ACTION_COURSETEXT   : $this->check_coursetext($params,   1); // 6

            case self::ACTION_SECTIONTYPE  : $this->check_sectiontype($params,  1); // 7
            case self::ACTION_SECTIONNUM   : $this->check_sectionnum($params,   1); // 8
            case self::ACTION_SECTIONTEXT  : $this->check_sectiontext($params,  1); // 9
        }

        // check consistency of parent parameters
        switch ($action) {
            case self::ACTION_SECTIONTEXT  : $this->check_sectiontext($params,  2); // 9
            case self::ACTION_SECTIONNUM   : $this->check_sectionnum($params,   2); // 8
            case self::ACTION_SECTIONTYPE  : $this->check_sectiontype($params,  2); // 7

            case self::ACTION_COURSETEXT   : $this->check_coursetext($params,   2); // 6
            case self::ACTION_COURSEID     : $this->check_courseid($params,     2); // 5
            case self::ACTION_COURSETYPE   : $this->check_coursetype($params,   2); // 4

            case self::ACTION_CATEGORYTEXT : $this->check_categorytext($params, 2); // 3
            case self::ACTION_CATEGORYID   : $this->check_categoryid($params,   2); // 2
            case self::ACTION_CATEGORYTYPE : $this->check_categorytype($params, 2); // 1
        }

        // cache $param values in this object
        foreach (get_object_vars($params) as $name => $value) {
            $this->$name = $value;
        }

        // we need the DB manager to check whether certain tables and fields exist
        $dbman = $DB->get_manager();

        // detect "quiz_slots" table  (Moodle >= 2.7)
        $this->quiz_slots = $dbman->table_exists('quiz_slots');

        // detect "quiz.questions" field (Moodle <= 2.6)
        $this->quiz_questions = $dbman->field_exists('quiz', 'questions');
    }

    /**
     * check_type
     *
     * @param  array   $params       (passed by reference)
     * @param  string  $type         "category", "course" or "section"
     * @param  array   $SUBTYPES     names of valid subtypes
     * @param  string  $DEFAULT      default subtype
     * @param  integer $contextlevel (optional, default=0) one of CONTEXT_xxx constants
     * @param  integer $contextid    (optional, default=0)
     * @return void    but may update $params
     */
    public function check_type(&$params, $type, $SUBTYPES, $DEFAULT, $contextlevel=0, $contextid=0) {
        $ok   = false;
        $name = 'target'.$type.'type';    // e.g. targetcoursetype
        $TYPE = strtoupper($type.'type'); // e.g. COURSETYPE
        foreach ($SUBTYPES as $SUBTYPE => $capability) {
            if ($params->$name==constant('self::'.$TYPE.'_'.$SUBTYPE)) {
                if ($capability) {
                    if ($context = reader_get_context($contextlevel, $contextid)) {
                        $ok = has_capability($capability, $context);
                    } else {
                        $ok = false; // invalid $contextid
                    }
                } else {
                    $ok = true; // no capability required
                }
                break;
            }
        }
        if ($ok==false) {
            $params->$name = constant('self::'.$TYPE.'_'.$DEFAULT);
        }
        return $params->$name;
    }

    /**
     * get_default_recordid
     *
     * @param  string   $table ("course" or "course_categories")
     * @param  integer  $id
     * @param  array    (optional, default=null) $params to select records from $DB
     * @param  boolean  (optional, default=true) true=first record, false=last record
     * @return integer  id of first (or last) visible (or hidden) record in $table
     */
    public function get_default_recordid($table, $id, $params=null, $first=true) {
        global $DB;

        if (isset($params) && empty($params['visible'])) {
            $visible = 0;
        } else {
            $visible = 1;
        }

        if ($id) {
            if ($DB->get_field($table, 'visible', array('id' => $id))==$visible) {
                return $id;
            }
        }

        $sort = 'sortorder '.($first ? 'ASC' : 'DESC');
        if ($records = $DB->get_records($table, $params, $sort, 'id,sortorder', 0, 1)) {
            return key($records);
        }
        return 0;
    }

    /**
     * check_categorytype
     *
     * @param  object  $params (passed by reference)
     * @param  integer $check  1=child, 2=parent
     * @return void    but may update $params
     */
    public function check_categorytype(&$params, $check=1, $default=self::CATEGORYTYPE_DEFAULT) {
        global $DB;
        if ($check==1) {
            // check type
            $types = array('DEFAULT' => null,
                           'HIDDEN'  => 'moodle/category:viewhiddencategories',
                           'VISIBLE' => null,
                           'CURRENT' => null,
                           'NEW'     => 'moodle/category:manage');
            $this->check_type($params, 'category', $types, 'DEFAULT', CONTEXT_SYSTEM);

            // fix categoryid, as necesasry
            switch ($params->targetcategorytype) {

                case self::CATEGORYTYPE_DEFAULT:
                    $params->targetcategoryid = $this->defaultcategoryid;
                    break;

                case self::CATEGORYTYPE_HIDDEN:
                    $params->targetcategoryid = $this->get_default_recordid('course_categories', $params->targetcategoryid, array('visible' => 0));
                    break;

                case self::CATEGORYTYPE_VISIBLE:
                    $params->targetcategoryid = $this->get_default_recordid('course_categories', $params->targetcategoryid, array('visible' => 1));
                    break;

                case self::CATEGORYTYPE_CURRENT:
                    $params->targetcategoryid = $this->course->category;
                    break;

                case self::CATEGORYTYPE_NEW:
                    $params->targetcategoryid = 0;
                    break;
            }

        } else {
            // check parent - nothing to do :-)
        }
    }

    /**
     * check_categoryid
     *
     * @param  object  $params (passed by reference)
     * @param  integer $check  1=child, 2=parent
     * @return void    but may update $params
     */
    public function check_categoryid(&$params, $check=1) {
        global $DB;
        if ($check==1) {
            // check $params->categoryid is valid
            if ($params->targetcategoryid && ! $DB->record_exists('course_categories', array('id' => $params->targetcategoryid))) {
                $params->targetcategoryid = 0;
            }

            // clear categorytext, if necessary
            if ($params->targetcategoryid) {
                // check targetcoursetype is suitable for this categorytype + categoryid

                $hidden_exists = $DB->record_exists('course', array('category' => $params->targetcategoryid, 'visible' => 0));
                $visible_exists = $DB->record_exists('course', array('category' => $params->targetcategoryid, 'visible' => 1));
                $current_exists = $DB->record_exists('course', array('id' => $this->course->id, 'category' => $params->targetcategoryid));

                // set default course type
                switch (true) {
                    case ($params->targetcategorytype==self::CATEGORYTYPE_DEFAULT):
                        $defaultcoursetype = self::COURSETYPE_DEFAULT;
                        break;
                    case ($current_exists):
                        $defaultcoursetype = self::COURSETYPE_CURRENT;
                        break;
                    case ($hidden_exists):
                        $defaultcoursetype = self::COURSETYPE_HIDDEN;
                        break;
                    case ($visible_exists):
                        $defaultcoursetype = self::COURSETYPE_VISIBLE;
                        break;
                    default:
                        $defaultcoursetype = 0;
                }

                switch ($params->targetcoursetype) {
                    case self::COURSETYPE_DEFAULT:
                        if (! $DB->record_exists('course', array('id' => $this->defaultcourseid, 'category' => $params->targetcategoryid))) {
                            $params->targetcoursetype = $defaultcoursetype;
                        }
                        break;
                    case self::COURSETYPE_VISIBLE:
                        if (! $visible_exists) {
                            $params->targetcoursetype = $defaultcoursetype;
                        }
                        break;
                    case self::COURSETYPE_HIDDEN:
                        if (! $hidden_exists) {
                            $params->targetcoursetype = $defaultcoursetype;
                        }
                        break;
                    case self::COURSETYPE_CURRENT:
                        if (! $current_exists) {
                            $params->targetcoursetype = $defaultcoursetype;
                        }
                        break;
                    case self::COURSETYPE_NEW:
                        if (! $this->can_create_course($params->targetcategoryid)) {
                            $params->targetcoursetype = $defaultcoursetype;
                        }
                        break;
                }
                $params->targetcategorytext = '';
            } else {
                // targetcategoryid was not specified (or invalid)
                switch ($params->targetcoursetype) {
                    case self::COURSETYPE_DEFAULT:
                    case self::COURSETYPE_NEW:
                        // do nothing - these types are allowed
                        break;
                    default:
                        // other types are NOT allowed
                        $params->targetcoursetype = self::COURSETYPE_DEFAULT;
                }
            }
        } else {
            // check $params->categoryid matches $params->categorytext
        }
    }

    /**
     * check_categorytext
     *
     * @param  object  $params (passed by reference)
     * @param  integer $check  1=child, 2=parent
     * @return void    but may update $params
     */
    public function check_categorytext(&$params, $check=1) {
        if ($check==1) {
            // check $params->categorytext is valid
            // check $params->categorytext matches $params->categorytype
        }
    }

    /**
     * check_coursetype
     *
     * @param  object  $params (passed by reference)
     * @param  integer $check  1=child, 2=parent
     * @return void    but may update $params
     */
    public function check_coursetype(&$params, $check=1) {
        global $DB;
        if ($check==1) {

            // set default coursetype
            if ($params->targetcategorytype==self::CATEGORYTYPE_NEW) {
                $default = 'NEW';
            } else {
                $default = 'DEFAULT';
            }

            // check $params->coursetype is valid
            $types = array('DEFAULT' => null,
                           'HIDDEN'  => 'moodle/course:viewhiddencourses',
                           'VISIBLE' => null,
                           'CURRENT' => null,
                           'NEW'     => 'moodle/course:create');
            $this->check_type($params, 'course', $types, $default, CONTEXT_COURSECAT, $params->targetcategoryid);

            // fix courseid, as necesasry
            switch ($params->targetcoursetype) {
                case self::COURSETYPE_DEFAULT:
                    $params->targetcourseid = $this->defaultcourseid;
                    break;
                case self::COURSETYPE_HIDDEN:
                    $params->targetcourseid = $this->get_default_recordid('course', $params->targetcourseid, array('visible' => 0, 'category' => $params->targetcategoryid));
                    break;
                case self::COURSETYPE_VISIBLE:
                    $params->targetcourseid = $this->get_default_recordid('course', $params->targetcourseid, array('visible' => 1, 'category' => $params->targetcategoryid));
                    break;
                case self::COURSETYPE_CURRENT:
                    $params->targetcourseid = $this->course->id;
                    break;
                case self::COURSETYPE_NEW:
                    $params->targetcourseid = 0;
                    break;
            }
        } else {
            // check $params->coursetype matches $params->courseid
        }
    }

    /**
     * check_courseid
     *
     * @param  object  $params (passed by reference)
     * @param  integer $check  1=child, 2=parent
     * @return void    but may update $params
     */
    public function check_courseid(&$params, $check=1) {
        global $DB;
        if ($check==1) {
            // check $params->courseid is valid
            if ($params->targetcourseid && ! $DB->record_exists('course', array('id' => $params->targetcourseid))) {
                $params->targetcourseid = 0;
            }

            // unset coursetext, if necessary
            if ($params->targetcourseid) {
                $params->targetcoursetext = '';
            } else {
                switch ($params->targetsectiontype) {
                    case self::SECTIONTYPE_DEFAULT:
                    case self::SECTIONTYPE_NEW:
                        // do nothing - these types are allowed
                        break;
                    default:
                        // other types are NOT allowed
                        $params->targetsectiontype = 0;
                }
            }
        } else {
            // check $params->courseid matches $params->coursetext
        }
    }

    /**
     * check_coursetext
     *
     * @param  object  $params (passed by reference)
     * @param  integer $check  1=child, 2=parent
     * @return void    but may update $params
     */
    public function check_coursetext(&$params, $check=1) {
        if ($check==1) {
            // check $params->coursetext is valid
            // check $params->coursetext matches $params->coursetype
        }
    }

    /**
     * check_sectiontype
     *
     * @param  object  $params (passed by reference)
     * @param  integer $check  1=child, 2=parent
     * @return void    but may update $params
     */
    public function check_sectiontype(&$params, $check=1) {
        global $DB;
        if ($check==1) {

            // check $params->sectiontype is valid
            $types = array('DEFAULT'  => null,
                           'HIDDEN'   => 'moodle/course:viewhiddensections',
                           'VISIBLE'  => null,
                           'LAST'     => null,
                           'NEW'      => 'moodle/course:viewhiddensections');
            $this->check_type($params, 'section', $types, 'DEFAULT', CONTEXT_COURSE, $params->targetcourseid);

            // fix courseid, as necesasry
            switch ($params->targetsectiontype) {
                case self::SECTIONTYPE_DEFAULT:
                    $params->targetsectionnum = 0;
                    break;
                case self::SECTIONTYPE_LAST:
                    $sql = "SELECT MAX(section) FROM {course_sections} WHERE course = ?";
                    $params->targetsectionnum = $DB->get_field_sql($sql, array($params->targetcourseid));
                    break;
                case self::SECTIONTYPE_VISIBLE:
                    if ($params->targetsectionnum && $DB->get_field('course_sections', 'visible', array('course' => $params->targetcourseid, 'section' => $params->targetsectionnum))==0) {
                        $params->targetsectionnum = 0;
                    }
                    break;
                case self::SECTIONTYPE_HIDDEN:
                    if ($params->targetsectionnum && $DB->get_field('course_sections', 'visible', array('course' => $params->targetcourseid, 'section' => $params->targetsectionnum))==1) {
                        $params->targetsectionnum = 0;
                    }
                    break;
                case self::SECTIONTYPE_NEW:
                    $params->targetsectionnum = 0;
                    break;
            }
        } else {
            // check $params->sectiontype matches $params->sectionnum
        }
    }

    /**
     * check_sectionnum
     *
     * @param  object  $params (passed by reference)
     * @param  integer $check  1=child, 2=parent
     * @return void    but may update $params
     */
    public function check_sectionnum(&$params, $check=1) {
        if ($check==1) {
            // check $params->sectionnum is valid
            // check $params->sectionnum matches $params->coursetype/courseid/sectiontype
        } else {
            // check $params->sectionnum matches $params->sectiontext
        }
    }

    /**
     * check_sectiontext
     *
     * @param  object  $params (passed by reference)
     * @param  integer $check  1=child, 2=parent
     * @return void    but may update $params
     */
    public function check_sectiontext(&$params, $check=1) {
        if ($check==1) {
            // check $params->sectiontext is valid
            // check $params->sectiontext matches $params->sectiontype
        }
    }

    /**
     * get_downloaded_items
     *
     * @uses $DB
     * @param xxx $type
     * @param xxx $downloadmode
     * @param xxx $r (optional, default=0)
     * @todo Finish documenting this function
     */
    public function get_downloaded_items($type, $downloadmode, $r=0) {
        global $CFG, $DB;

        $this->downloaded[$r] = new reader_items();

        // cache $isrepairmode flag
        $isrepairmode = ($downloadmode==reader_downloader::REPAIR_MODE);

        if ($type==self::BOOKS_WITH_QUIZZES) {
            $select = 'quizid > ?';
        } else {
            $select = 'quizid = ?';
        }
        if ($records = $DB->get_records_select('reader_books', $select, array(0), 'publisher,level,name')) {

            foreach ($records as $record) {

                $publisher = $record->publisher;
                $level     = $record->level;
                $itemname  = $record->name;

                if ($isrepairmode) {
                    $time = 0;
                } else if ($record->time) {
                    $time = $record->time;
                } else {
                    // get $time this book was last updated by checking
                    // the "last modified" time on the book's image file
                    $time = $this->get_imagefile_time($record->image);
                }

                // ensure the $downloaded array has the required structure
                if (! isset($this->downloaded[$r]->items[$publisher])) {
                    $this->downloaded[$r]->items[$publisher] = new reader_items();
                }
                if (! isset($this->downloaded[$r]->items[$publisher]->items[$level])) {
                    $this->downloaded[$r]->items[$publisher]->items[$level] = new reader_items();
                }

                // record the time this item was last updated
                $this->downloaded[$r]->items[$publisher]->items[$level]->items[$itemname] = new reader_download_item($record->id, $time);
            }
        }
        $this->remotesites[$r]->clear_filetimes();
    }

    /**
     * get_imagefile_time
     *
     * @param string $image
     * @return integer
     * @todo Finish documenting this function
     */
    public function get_imagefile_time($image) {
        global $CFG;

        // define image file(s) to search for
        $imagefiles = array($image);
        if (substr($image, 0, 1)=='-') {
            // this image doesn't have the expected publisher code prefix
            // so we add an alternative "tidy" image file name
            $imagefiles[] = substr($image, 1);
        }

        foreach ($imagefiles as $imagefile) {
            $imagefile = $CFG->dataroot.'/reader/images/'.$imagefile;
            if (file_exists($imagefile)) {
                return filemtime($imagefile);
            }
        }

        return 0; // shouldn't happen !!
    }

    /**
     * add_remotesite
     *
     * @param xxx $remotesite
     * @todo Finish documenting this function
     */
    public function add_remotesite($remotesite) {
        $this->remotesites[] = $remotesite;
    }

    /**
     * add_available_items
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return void
     * @todo Finish documenting this function
     */
    public function add_available_items($type, $itemids) {
        foreach ($this->remotesites as $r => $remotesite) {
            $this->available[$r] = $remotesite->get_available_items($type, $itemids, $this->downloaded[$r]);
        }
    }

    /**
     * has_available_items
     *
     * @return boolean
     * @todo Finish documenting this function
     */
    public function has_available_items() {
        $count = 0;
        foreach (array_keys($this->remotesites) as $r) {
            $count += $this->available[$r]->count;
        }
        return $count;
    }

    /**
     * has_updated_items
     *
     * @return boolean
     * @todo Finish documenting this function
     */
    public function has_updated_items() {
        $updatecount = 0;
        foreach (array_keys($this->remotesites) as $r) {
            $updatecount += $this->available[$r]->updatecount;
        }
        return $updatecount;
    }

    /**
     * has_new_items
     *
     * @return boolean
     * @todo Finish documenting this function
     */
    public function has_new_items() {
        $newcount = 0;
        foreach (array_keys($this->remotesites) as $r) {
            $newcount += $this->available[$r]->newcount;
        }
        return $newcount;
    }

    /**
     * check_selected_itemids
     *
     * @param xxx $publishers
     * @param xxx $levels
     * @param xxx $itemids (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function check_selected_itemids($selectedpublishers, $selectedlevels, &$selecteditemids) {
        if (count($selectedpublishers)==0 && count($selectedlevels)==0) {
            return false; // nothing to do
        }
        foreach ($this->available as $r => $available) {
            $i = 0;
            foreach ($available->items as $publishername => $levels) {
                $i++;

                $ii = 0;
                foreach ($levels->items as $levelname => $items) {
                    $ii++;

                    if (in_array($i, $selectedpublishers) || in_array($i.'_'.$ii, $selectedlevels)) {
                        foreach ($items->items as $itemname => $item) {

                            if (isset($this->downloaded[$r]->items[$publishername]->items[$levelname]->items[$itemname])) {
                                $updatetime = $this->downloaded[$r]->items[$publishername]->items[$levelname]->items[$itemname]->time;
                            } else {
                                $updatetime = 0;
                            }

                            if (in_array($item->id, $selecteditemids)) {
                                // this item has already been selected
                            } else if ($updatetime >= $item->time) {
                                // the most recent update of this item has already been downloaded
                            } else {
                                $selecteditemids[] = $item->id;
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * add_selected_itemids
     *
     * @uses $CFG
     * @uses $DB
     * @param xxx $type
     * @param xxx $itemids
     * @param xxx $r (optional, default=0)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_selected_itemids($type, $itemids, $r=0) {
        global $CFG, $DB;

        if (empty($itemids)) {
            return false; // nothing to do
        }

        $remotesite = $this->remotesites[$r];
        $xml = $remotesite->download_quizzes($type, $itemids);
        if (empty($xml) || empty($xml['myxml']) || empty($xml['myxml']['#'])) {
            return false; // shouldn't happen !!
        }

        if (CLI_SCRIPT===false) {
            $this->bar = reader_download_progress_bar::create($itemids, 'readerdownload');
        }

        // show memory on main Reader module developer site
        $show_memory = (file_exists($CFG->dirroot.'/mod/reader/admin/tools/print_cheatsheet.php'));

        $errors = 0;
        $output = '';
        $time = time();
        $started_list = false;
        $starttime = microtime();
        $strquiz = get_string('modulename', 'quiz');

        $i_max = count($xml['myxml']['#']['item']);
        foreach ($xml['myxml']['#']['item'] as $i => $item) {

            // sanity checks on $item fields
            if (! isset($item['@']['publisher'])) {
                continue;
            }
            if (! isset($item['@']['level'])) {
                continue;
            }
            if (! isset($item['@']['title'])) {
                continue;
            }
            if (! isset($item['@']['id'])) {
                continue;
            }

            $publisher = trim($item['@']['publisher']);
            $level     = trim($item['@']['level']);
            $name      = trim($item['@']['title']);
            $itemid    = trim($item['@']['id']);
            $itemtime  = trim($item['@']['time']);

            if ($publisher=='' || $name=='' || $itemid=='') { // $level can be empty
                continue;
            }

            $publisherlevel = $publisher;
            if ($level=='' || $level=='--' || $level=='No Level') {
                // do nothing
            } else {
                $publisherlevel .= " - $level";
            }
            $titlehtml = html_writer::tag('span', $publisherlevel, array('style' => 'font-weight: normal')).
                         html_writer::empty_tag('br').
                         html_writer::tag('span', $name, array('style' => 'white-space: nowrap'));
            $titletext = "$publisherlevel: $name";

            if ($show_memory) {
                $memory_usage = memory_get_usage();
                $memory_usage = number_format($memory_usage/1000000).' MB';
                $memory_peak_usage = memory_get_peak_usage();
                $memory_peak_usage = number_format($memory_peak_usage/1000000).' MB';
                $titletext .= " (memory=$memory_usage peak=$memory_peak_usage)";
            }

            // show this book in the progress bar
            if ($this->bar) {
                $title = ($i + 1).' / '.$i_max.' '.$titlehtml;
                $this->bar->start_item($itemid, $title);
            }

            // set $params to select $book
            $select = 'publisher = ? AND level = ? AND name = ?';
            if ($type==self::BOOKS_WITH_QUIZZES) {
                $select .= ' AND quizid > ?';
            } else {
                $select .= ' AND quizid = ?';
            }
            $params = array($publisher, $level, $name, 0);

            if ($book = $DB->get_record_select('reader_books', $select, $params)) {
                // do nothing
            } else {
                // set up default values for a new $book
                $book = (object)array(
                    'publisher'  => $publisher,
                    'series'     => '',
                    'level'      => $level,
                    'difficulty' => 0,
                    'name'       => $name,
                    'words'      => 0,
                    'genre'      => '',
                    'fiction'    => '',
                    'image'      => '',
                    'length'     => '',
                    'private'    => 0,
                    'sametitle'  => '',
                    'hidden'     => 0,
                    'maxtime'    => 0,
                );
            }

            $book->time = $time;

            // transfer values from this $item to this $book
            $fields = get_object_vars($book);
            foreach ($fields as $field => $defaultvalue) {
                if ($field=='id' || $field=='publisher' || $field=='level' || $field=='name' || $field=='quizid') {
                    // $field has already been set
                } else if (isset($item['@'][$field])) {
                    $book->$field = $item['@'][$field];
                }
            }

            // update or add the $book
            $error = 0;
            if (isset($book->id)) {
                if ($DB->update_record('reader_books', $book)) {
                    $msg = get_string('bookupdated', 'mod_reader', $titletext);
                } else {
                    $msg = get_string('booknotupdated', 'mod_reader', $titletext);
                    $error = 1;
                }
            } else {
                $book->quizid = 0;
                if ($book->id = $DB->insert_record('reader_books', $book)) {
                    $msg = get_string('bookadded', 'mod_reader', $titletext);
                } else {
                    $msg = get_string('booknotadded', 'mod_reader', $titletext);
                    $error = 1;
                }
            }

            // download associated image (i.e. book cover)
            if ($error==0) {
                $this->download_image($type, $itemid, $book->image, $r);
                $msg .= html_writer::empty_tag('br').get_string('imageadded', 'mod_reader', $book->image);
            }

            if ($started_list==false && CLI_SCRIPT==false) {
                $started_list = true;
                echo $this->output->box_start('generalbox', 'notice');
                echo html_writer::start_tag('div');
                echo $this->output->showhide_js_start();
                //echo html_writer::tag('p', get_string('targetcategory', 'mod_reader').': '.$DB->get_field('course_categories', 'name', array('id' => $this->targetcategoryid)));
                //echo html_writer::tag('p', get_string('targetcourse', 'mod_reader').': '.$DB->get_field('course', 'fullname', array('id' => $this->targetcourseid)));
                echo html_writer::tag('b', get_string('downloadedbooks', 'mod_reader'));
                echo $this->output->available_list_img();
                echo html_writer::start_tag('ol');
            }

            // update "newcount" (=downloadable) and "updatecount" (=updatable) counters
            if (! isset($this->downloaded[$r]->items[$book->publisher])) {
                $this->downloaded[$r]->items[$publisher] = new reader_items();
            }
            if (! isset($this->downloaded[$r]->items[$book->publisher]->items[$book->level])) {
                $this->downloaded[$r]->items[$book->publisher]->items[$book->level] = new reader_items();
            }
            if (! isset($this->downloaded[$r]->items[$book->publisher]->items[$book->level]->items[$book->name])) {
                // a new item
                if (isset($this->available[$r]->newcount)) {
                    if (isset($this->available[$r]->items[$book->publisher]->newcount)) {
                        if (isset($this->available[$r]->items[$book->publisher]->items[$book->level]->newcount)) {
                            $this->available[$r]->items[$book->publisher]->items[$book->level]->newcount--;
                        }
                        $this->available[$r]->items[$book->publisher]->newcount--;
                    }
                    $this->available[$r]->newcount--;
                }
            } else if ($this->downloaded[$r]->items[$book->publisher]->items[$book->level]->items[$book->name]->time < $itemtime) {
                // an updated item
                if (isset($this->available[$r]->updatecount)) {
                    if (isset($this->available[$r]->items[$book->publisher]->updatecount)) {
                        if (isset($this->available[$r]->items[$book->publisher]->items[$book->level]->updatecount)) {
                            $this->available[$r]->items[$book->publisher]->items[$book->level]->updatecount--;
                        }
                        $this->available[$r]->items[$book->publisher]->updatecount--;
                    }
                    $this->available[$r]->updatecount--;
                }
            }

            // flag this item as "downloaded" and set update $time
            $this->downloaded[$r]->items[$book->publisher]->items[$book->level]->items[$book->name] = new reader_download_item($itemid, $time);

            // add quiz if necessary
            if ($error==0 && $type==reader_downloader::BOOKS_WITH_QUIZZES) {
                if ($quiz = $this->add_quiz($item, $book, $r)) {
                    if ($this->quiz_slots) {
                        $questions_exist = $DB->record_exists('quiz_slots', array('quizid' => $quiz->id));
                    } else {
                        $questions_exist = $DB->record_exists('quiz_question_instances', array('quiz' => $quiz->id));
                    }
                    if ($questions_exist) {
                        $link = new moodle_url('/mod/quiz/view.php', array('q' => $quiz->id));
                        $link = html_writer::link($link, $strquiz, array('onclick' => 'this.target="_blank"'));

                        list($cheatsheeturl, $strcheatsheet) = $this->output->cheatsheet_init('takequiz');
                        if ($cheatsheeturl) {
                            $link .= ' '.$this->output->cheatsheet_link($cheatsheeturl, $strcheatsheet, $publisherlevel, $book);
                        }

                        if ($book->quizid==0) {
                            $msg .= html_writer::empty_tag('br').get_string('quizadded', 'mod_reader', $link);
                        } else {
                            $msg .= html_writer::empty_tag('br').get_string('quizupdated', 'mod_reader', $link);
                        }

                        if ($book->id==0 || $book->quizid != $quiz->id) {
                            $book->quizid = $quiz->id;
                            $DB->set_field('reader_books', 'quizid', $book->quizid, array('id' => $book->id));
                        }
                    } else {
                        // delete quiz
                        $msg .= html_writer::empty_tag('br');
                        $msg .= html_writer::tag('span', get_string('error').': ', array('class' => 'notifyproblem'));
                        $msg .= get_string('quizhasnoquestions', 'mod_reader');
                        $this->remove_coursemodule($quiz->id, 'quiz');

                        // mark $book in "reader_books" table as having no quiz
                        $book->quizid = 0;
                        if ($book->id) {
                            $DB->update_record('reader_books', $book);
                        } else {
                            unset($book->id);
                            $book->id = $DB->insert_record('reader_books', $book);
                        }
                        $msg .= html_writer::empty_tag('br').'Book moved to "books without quizzes" list';

                        // remove from list of downloaded books and available counters
                        unset($this->downloaded[$r]->items[$book->publisher]->items[$book->level]->items[$book->name]);
                        $this->available[$r]->items[$book->publisher]->items[$book->level]->newcount++;
                        $this->available[$r]->items[$book->publisher]->newcount++;
                        $this->available[$r]->newcount++;
                        $error = 1;
                    }
                }
            }

            // print this item
            if (CLI_SCRIPT==false) {
                echo html_writer::tag('li', $msg, array('class' => 'downloaditem'));
            }

            // move the progress bar
            if ($this->bar) {
                $this->bar->finish_item();
            }

            // reclaim a bit of memory
            unset($xml['myxml']['#']['item']);

            // keep track of how many errors were found
            $errors += $error;
        }

        // finish the progress bar
        if ($this->bar) {
            $title = get_string('itemsdownloaded', 'mod_reader', ($i + 1).' / '.$i_max);
            if ($duration = microtime_diff($starttime, microtime())) {
                $title .= ' ('.format_time(round($duration)).')';
            }
            if ($errors) {
                $errors = get_string('errorsfound', 'mod_reader').': '.$errors;
                $title .= html_writer::empty_tag('br');
                $title .= html_writer::tag('span', $errors, array('class' => 'error'));
                $title .= ' ('.get_string('seedetailsbelow', 'mod_reader').')';
            }
            $this->bar->finish($title);
        }

        if ($started_list==true && CLI_SCRIPT==false) {
            echo html_writer::end_tag('ul');
            echo html_writer::end_tag('div');
            echo $this->output->box_end();
        }
    }

    /**
     * download_image
     *
     * @uses $CFG
     * @param xxx $type
     * @param xxx $itemid
     * @param xxx $filename
     * @param xxx $r (optional, default=0)
     * @todo Finish documenting this function
     */
    public function download_image($type, $itemid, $filename, $r=0) {
        global $CFG;
        make_upload_directory('reader/images');

        $remotesite = $this->remotesites[$r];
        $url = $remotesite->get_image_url($type, $itemid);
        $post = $remotesite->get_image_post($type, $itemid);

        if ($image = download_file_content($url, null, $post)) {
            if ($fp = @fopen($CFG->dataroot.'/reader/images/'.$filename, 'w+')) {
                @fwrite($fp, $image);
                @fclose($fp);
                return true;
            }
        }
        return false;
    }

    /**
     * add_quiz
     *
     * @uses $DB
     * @param array    $item  xml data for this download item (= book)
     * @param object   $book  recently added/updated "reader_books" record
     * @param integer  $r     (optional, default=0)
     * @todo Finish documenting this function
     */
    public function add_quiz($item, $book, $r=0) {
        global $DB;

        // get/create course to hold quiz
        $courseid = $this->get_quiz_courseid();

        // get/create section (in $course) to hold quiz
        $sectionnum = $this->get_quiz_sectionnum($courseid, $book);

        // get/create "course_module" record for (new) quiz
        $cm = $this->get_quiz_coursemodule($courseid, $sectionnum, $book->name);

        // get newly created/updated quiz
        $quiz = $DB->get_record('quiz', array('id' => $cm->instance));

        // add questions to quiz
        $this->add_question_categories($quiz, $cm, $item, $r);

        return $quiz;
    }

    /**
     * set_quiz_courseid
     *
     * @uses $DB
     * @param integer $courseid
     * @param boolean $coursetype (optional, default=0)
     * @param boolean $categoryid (optional, default=0)
     * @param boolean $set_reader (optional, default=false)
     * @param boolean $set_config (optional, default=false)
     * @todo Finish documenting this function
     */
    public function set_quiz_courseid($courseid, $categorytype=null, $coursetype=null, $categoryid=null, $set_reader=false, $set_config=false) {
        global $DB;

        // cache this course id
        $this->targetcourseid = $courseid;
        $course = $DB->get_record('course', array('id' => $courseid));

        // cache this course type
        if (isset($coursetype)) {
            $this->targetcoursetype = $coursetype;
        } else if ($courseid==$this->reader->course) {
            $this->targetcoursetype = self::COURSETYPE_CURRENT; // 3
        } else if ($DB->get_field('course', 'visible', array('id' => $courseid))) {
            $this->targetcoursetype = self::COURSETYPE_VISIBLE; // 2
        } else {
            $this->targetcoursetype = self::COURSETYPE_HIDDEN;  // 1
        }

        // cache this category id
        if (isset($categoryid)) {
            $this->targetcategoryid = $categoryid;
        } else {
            $this->targetcategoryid = $DB->get_field('course', 'category', array('id' => $courseid));
        }

        // cache this category type
        if (isset($categorytype)) {
            $this->targetcategorytype = $categorytype;
        } else if ($course->category==$this->course->category) {
            $this->targetcategorytype = self::CATEGORYTYPE_CURRENT; // 3
        } else if ($DB->get_field('course_categories', 'visible', array('id' => $course->category))) {
            $this->targetcategorytype = self::CATEGORYTYPE_VISIBLE; // 2
        } else {
            $this->targetcategorytype = self::CATEGORYTYPE_HIDDEN;  // 1
        }

        if ($set_reader && $this->reader->usecourse==0) {
            $this->reader->usecourse = $courseid;
            $DB->update_record('reader', $this->reader);
        }

        if ($set_config && get_config('mod_reader', 'usecourse')==0) {
            set_config('usecourse', $courseid, 'mod_reader');
        }
    }

    /**
     * get_course_categorytype
     *
     * @uses $DB
     * @param integer $numsections (optional, default=1)
     * @return integer $courseid
     * @todo Finish documenting this function
     */
    public function get_course_categorytype() {
        if ($this->targetcategorytype) {
            $this->get_quiz_courseid();
        }
        return $this->targetcategorytype;
    }

    /**
     * get_course_categoryid
     *
     * @uses $DB
     * @param integer $numsections (optional, default=1)
     * @return integer $courseid
     * @todo Finish documenting this function
     */
    public function get_course_categoryid() {

        // category id is cached
        if ($categoryid = $this->targetcategoryid) {
            return $categoryid;
        }

        // derive categoryid from courseid
        if ($courseid = $this->get_quiz_courseid()) {
            if ($categoryid = $this->targetcategoryid) {
                return $categoryid;
            }
        }

        return 0;
    }

    /**
     * get_quiz_coursetype
     *
     * @uses $DB
     * @param integer $numsections (optional, default=1)
     * @return integer $courseid
     * @todo Finish documenting this function
     */
    public function get_quiz_coursetype() {

        // course type is cached
        if ($coursetype = $this->targetcoursetype) {
            return $coursetype;
        }

        // derive coursetype from courseid
        if ($courseid = $this->get_quiz_courseid()) {
            if ($coursetype = $this->targetcoursetype) {
                return $this->targetcoursetype;
            }
        }

        return self::COURSETYPE_DEFAULT;
    }

    /**
     * get_quiz_courseid
     *
     * @uses $DB
     * @param integer $numsections (optional, default=1)
     * @return integer $courseid
     * @todo Finish documenting this function
     */
    public function get_quiz_courseid($numsections=1) {
        global $DB;

        if (defined('AJAX_SCRIPT') && AJAX_SCRIPT==true) {
            return $this->targetcourseid;
        }

        // course id is usually cached
        if ($courseid = $this->targetcourseid) {
            return $courseid;
        }
        $coursetype   = $this->targetcoursetype;
        $coursetext   = $this->targetcoursetext;

        $categorytype = $this->targetcategorytype;
        $categoryid   = $this->targetcategoryid;
        $categorytext = $this->targetcategorytext;

        // try to find suitable category
        if ($categoryid==0) {

            // get list of course categories
            $requiredcapability = 'moodle/course:create';
            if (class_exists('coursecat')) {
                $category_list = coursecat::make_categories_list($requiredcapability);
            } else { // Moodle <= 2.4
                $category_list = array();
                $category_parents = array();
                make_categories_list($category_list, $category_parents, $requiredcapability);
            }

            // get first category of required type
            foreach ($category_list as $categoryid => $category_name) {
                switch ($categorytype) {
                    case self::CATEGORYTYPE_HIDDEN:
                        $keep = ! $DB->get_field('course_categories', 'visible', array('id' => $categoryid));
                        break;
                    case self::CATEGORYTYPE_VISIBLE:
                        $keep = $DB->get_field('course_categories', 'visible', array('id' => $categoryid));
                        break;
                    case self::CATEGORYTYPE_CURRENT:
                        $keep = ($categoryid==$this->course->category);
                        break;
                    case self::CATEGORYTYPE_NEW:
                        $keep = false;
                        break;
                    case self::CATEGORYTYPE_DEFAULT:
                    default:
                        $keep = true;
                }
                if (! $keep) {
                    unset($category_list[$categoryid]);
                }
            }

            // get first valid category $categoryid (e.g. Miscellaneous)
            reset($category_list);
            list($categoryid, $category_name) = each($category_list);
        }

        // allow system admin to create courses anywhere
        if ($categoryid==0 && $this->can_create_course()) {

            if ($categorytext=='') {
                $categorytext = get_string('defaultcategoryname', 'mod_reader');
            }
            $categorytext = $this->get_unique_name($categorytext, 'course_categories', 'name');

            // setup new course category
            $category = (object)array(
                'name'          => $categorytext,
                'idnumber'      => '',
                'description'   => '',
                'descriptionformat' => FORMAT_PLAIN, // plain text
                'parent'        => 0,
                'sortorder'     => 0,
                'coursecount'   => 0,
                'visible'       => 0, // hidden
                'visibleold'    => 0,
                'timemodified'  => 0,
                'depth'         => 0,
                'path'          => '',
                'theme'         => '',
            );

            if (class_exists('coursecat')) {
                // Moodle >= 2.5
                $category = coursecat::create($category);
            } else {
                // Moodle <= 2.4
                $category = create_course_category($category);
            }

            $categoryid = $category->id;
            $categorytype = self::CATEGORYTYPE_HIDDEN;
        }

        // create course if allowed
        if ($categoryid) {

            if ($coursetext=='') {
                $coursetext = get_string('defaultcoursename', 'mod_reader');
            }
            $coursetext = $this->get_unique_name($coursetext, 'course', 'shortname');

            // setup new course
            $course = (object)array(
                'category'      => $categoryid, // crucial !!
                'fullname'      => $coursetext,
                'shortname'     => $coursetext,
                'summary'       => '',
                'summaryformat' => FORMAT_PLAIN, // plain text
                'format'        => 'topics',
                'newsitems'     => 0,
                'startdate'     => time(),
                'visible'       => 0, // hidden
                'numsections'   => $numsections
            );

            // create new course
            $course = create_course($course);

            // save new course id
            $this->set_quiz_courseid($course->id, $categorytype, self::COURSETYPE_HIDDEN, $categoryid, true);

            // return new course id
            return $course->id;
        }

        // use course id specified by this reader activity
        if ($courseid = $this->reader->usecourse) {
            if ($this->can_manage_course($courseid)) {
                $this->set_quiz_courseid($courseid);
                return $courseid;
            }
        }

        // use course id specified by site config settings
        if ($courseid = get_config('mod_reader', 'usecourse')) {
            if ($this->can_manage_course($courseid)) {
                $this->set_quiz_courseid($courseid);
                return $courseid;
            }
        }

        // get default name for Reader quiz course
        $coursetext = get_string('defaultcoursename', 'mod_reader');

        // course with default Reader course name
        if ($courseid = $DB->get_field('course', 'id', array('shortname' => $coursetext))) {
            if ($this->can_manage_course($courseid)) {
                $this->set_quiz_courseid($courseid, 0, 0, 0, true);
                return $courseid;
            }
        }

        // we should be able to restore into the current course
        if ($courseid = $this->reader->course) {
            if ($this->can_manage_course($courseid)) {
                $this->set_quiz_courseid($courseid);
                return $courseid;
            }
        }

        // this user is not allowed to create new courses
        // or add stuff to the current course, so abort
        throw new moodle_exception('cannotcreatecourse', 'reader');
    }

    /**
     * get_unique_name
     *
     * @param string $name
     * @param string $table
     * @param string $field
     * @todo Finish documenting this function
     */
    public function get_unique_name($name, $table, $namefield) {
        global $DB;
        $i = 1;
        $uniquename = $name;
        while ($DB->record_exists($table, array($namefield => $uniquename))) {
            $i++;
            $uniquename = "$name $i";
        }
        return $uniquename;
    }

    /**
     * can_manage_category
     *
     * @param integer $categoryid (optional, default=0)
     * @todo Finish documenting this function
     */
    public function can_manage_category($categoryid=0) {
        if ($categoryid) {
            $context = reader_get_context(CONTEXT_COURSECAT, $categoryid);
        } else {
            $context = reader_get_context(CONTEXT_SYSTEM); // SITE context
        }
        if ($context) {
            return has_capability('moodle/category:manage', $context);
        } else {
            return false; // shouldn't happen !!
        }
    }

    /**
     * can_create_course
     *
     * @param integer $categoryid (optional, default=0)
     * @todo Finish documenting this function
     */
    public function can_create_course($categoryid=0) {
        if ($categoryid) {
            $context = reader_get_context(CONTEXT_COURSECAT, $categoryid);
        } else {
            $context = reader_get_context(CONTEXT_SYSTEM); // SITE context
        }
        if ($context) {
            return has_capability('moodle/course:create', $context);
        } else {
            return false; // shouldn't happen !!
        }
    }

    /**
     * can_manage_course
     *
     * @param integer $courseid
     * @todo Finish documenting this function
     */
    public function can_manage_course($courseid) {
        if ($courseid) {
            $context = reader_get_context(CONTEXT_COURSE, $courseid);
        } else {
            $context = reader_get_context(CONTEXT_SYSTEM); // SITE context
        }
        if ($context) {
            return has_capability('moodle/course:manageactivities', $context);
        } else {
            return false; // shouldn't happen !!
        }
    }

    /**
     * create_sectionname($book)
     *
     * @param object $book recently added/updated "reader_books" record
     * @todo Finish documenting this function
     */
    public function create_sectionname($book) {
        if (empty($book)) {
            return '';
        }
        if ($book->level=='' || $book->level=='--' || $book->level=='No Level') {
            return $book->publisher;
        } else {
            return $book->publisher.' - '.$book->level;
        }
    }

    /**
     * get_quiz_sectiontype
     *
     * @uses $DB
     * @param integer $numsections (optional, default=1)
     * @return integer $courseid
     * @todo Finish documenting this function
     */
    public function get_quiz_sectiontype() {

        // get cached value, if possible
        if ($sectiontype = $this->targetsectiontype) {
            return $sectiontype;
        }

        return self::SECTIONTYPE_DEFAULT;
    }

    /**
     * get_quiz_sectionnum
     *
     * @uses $DB
     * @param xxx $courseid where Reader quizzes are stored
     * @param xxx $book (optional, default="") recently added/modified book
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_quiz_sectionnum($courseid, $book='') {
        global $DB;

        // use cached sectionnum, if possible
        if ($sectionnum = $this->targetsectionnum) {
            return $sectionnum;
        }

        // get required section type
        $sectiontype = $this->get_quiz_sectiontype();
        $sectionname = '';

        // some DBs (e.g. MSSQL) cannot compare TEXT fields
        // so we must CAST them to something else (e.g. CHAR)
        $summary = $DB->sql_compare_text('summary');
        $sequence = $DB->sql_compare_text('sequence');

        $cache = false;
        switch ($sectiontype) {

            case self::SECTIONTYPE_DEFAULT:
                $sectionname = $this->create_sectionname($book);
                $select = 'course = ? AND (name = ? OR '.$summary.' = ?)';
                $params = array($courseid, $sectionname, $sectionname);
                if ($coursesections = $DB->get_records_select('course_sections', $select, $params, 'section', '*', 0, 1)) {
                    $coursesection = reset($coursesections);
                    $sectionnum = $coursesection->section;
                }
                break;

            case self::SECTIONTYPE_HIDDEN:
            case self::SECTIONTYPE_VISIBLE:
                if ($sectionnum = optional_param('sectionnum', 0, PARAM_INT)) {
                    $params = array('course' => $courseid, 'section' => $sectionnum);
                    if ($coursesection = $DB->get_record('course_sections', $params)) {
                        $sectionnum = $coursesection->section;
                    } else {
                        $sectionnum = 0;
                    }
                }
                $cache = true;
                break;

            case self::SECTIONTYPE_LAST:
                $params = array('course' => $courseid);
                if ($coursesections = $DB->get_records('course_sections', $params, 'section DESC', '*', 0, 1)) {
                    $coursesection = reset($coursesections);
                    $sectionnum = $coursesection->section;
                    $cache = true;
                }
                break;

            case self::SECTIONTYPE_NEW:
                $sectionname = $this->targetsectiontext;
                $cache = true;
                break;
        }

        // reuse an empty section, if possible
        if ($sectionnum==0) {
            $select = 'course = ? AND section > ?'.
                      ' AND (name IS NULL OR name = ?)'.
                      ' AND (summary IS NULL OR '.$summary.' = ?)'.
                      ' AND (sequence IS NULL OR '.$sequence.' = ?)';
            $params = array($courseid, 0, '', '', '');

            if ($coursesections = $DB->get_records_select('course_sections', $select, $params, 'section', '*', 0, 1)) {
                $coursesection = reset($coursesections);
                $sectionnum = $coursesection->section;
                $coursesection->name = $sectionname;
                $DB->update_record('course_sections', $coursesection);
            }
        }

        // create a new section, if necessary
        if ($sectionnum==0) {
            $sql = "SELECT MAX(section) FROM {course_sections} WHERE course = ?";
            if ($sectionnum = $DB->get_field_sql($sql, array($courseid))) {
                $sectionnum ++;
            } else {
                $sectionnum = 1;
            }
            $coursesection = (object)array(
                'course'        => $courseid,
                'section'       => $sectionnum,
                'name'          => $sectionname,
                'summary'       => '',
                'summaryformat' => FORMAT_HTML,
            );
            $coursesection->id = $DB->insert_record('course_sections', $coursesection);
        }

        if ($sectionnum > reader_get_numsections($courseid)) {
            reader_set_numsections($courseid, $sectionnum);
        }

        // cache this section type and number
        if ($cache) {
            $this->targetsectionnum = $sectionnum;
        }

        return $sectionnum;
    }

    /**
     * get_quiz_coursemodule
     *
     * @uses $DB
     * @uses $USER
     * @param xxx $courseid
     * @param xxx $sectionnum
     * @param xxx $quizname (same as book name)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_quiz_coursemodule($courseid, $sectionnum, $quizname) {
        global $DB, $USER;
        static $quizmoduleid = 0;

        if ($quizmoduleid==0) {
            $quizmoduleid = $DB->get_field('modules', 'id', array('name' => 'quiz'));
        }

        // try to get the most recent visible version of this quiz if possible

        $select = 'cm.*';
        $from   = '{course_modules} cm '.
                  'JOIN {course_sections} cs ON cm.section = cs.id '.
                  'JOIN {quiz} q ON cm.module = ? AND cm.instance = q.id';
        $where  = 'cs.section = ? AND q.name = ? AND cm.visible = ?';
        $params = array($quizmoduleid, $sectionnum, $quizname, 1);
        $orderby = 'cm.visible DESC, cm.added DESC'; // newest, visible cm first
        if ($cms = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $orderby", $params, 0, 1)) {
            return reset($cms);
        }

        $sumgrades = 0;
        $newquiz = (object)array(
            // standard Quiz fields
            'name'            => $quizname,
            'intro'           => ' ',
            'visible'         => 1,
            'introformat'     => FORMAT_HTML, // =1
            'timeopen'        => 0,
            'timeclose'       => 0,
            'preferredbehaviour' => 'deferredfeedback',
            'attempts'        => 0,
            'attemptonlast'   => 1,
            'grademethod'     => 1,
            'decimalpoints'   => 2,
            'reviewattempt'   => 0,
            'reviewcorrectness' => 0,
            'reviewmarks'       => 0,
            'reviewspecificfeedback' => 0,
            'reviewgeneralfeedback'  => 0,
            'reviewrightanswer'      => 0,
            'reviewoverallfeedback'  => 0,
            'questionsperpage' => 0,
            'shufflequestions' => 0,
            'shuffleanswers'  => 1,
            'questions'       => '0,',
            'sumgrades'       => 0, // reset after adding questions
            'grade'           => 100,
            'timecreated'     => time(),
            'timemodified'    => time(),
            'timelimit'       => 0,
            'overduehandling' => '',
            'graceperiod'     => 0,
            'quizpassword'    => '', // should be "password" ?
            'subnet'          => '',
            'browsersecurity' => '',
            'delay1'          => 0,
            'delay2'          => 0,
            'showuserpicture' => 0,
            'showblocks'      => 0,
            'navmethod'       => '',

            // feedback fields (for "quiz_feedback" table)
            'feedbacktext'    => array_fill(0, 5, array('text' => '', 'format' => 0)),
            'feedbackboundaries' => array(0 => 0, -1 => 11),
            'feedbackboundarycount' => 0,

            // these fields may not be necessary in Moodle 2.x
            'adaptive'      => 1,
            'penaltyscheme' => 1,
            'popup'         => 0,

            // standard fields for adding a new cm
            'course'        => $courseid,
            'section'       => $sectionnum,
            'module'        => $quizmoduleid,
            'modulename'    => 'quiz',
            'add'           => 'quiz',
            'update'        => 0,
            'return'        => 0,
            'cmidnumber'    => '',
            'groupmode'     => 0,
            'MAX_FILE_SIZE' => 10485760, // 10 GB

        );

        //$newquiz->instance = quiz_add_instance($newquiz);
        if (! $newquiz->instance = $DB->insert_record('quiz', $newquiz)) {
            return false;
        }
        if (! $newquiz->coursemodule = add_course_module($newquiz) ) { // $mod
            throw new reader_exception('Could not add a new course module');
        }
        $newquiz->id = $newquiz->coursemodule; // $cmid
        if (function_exists('course_add_cm_to_section')) {
            $sectionid = course_add_cm_to_section($courseid, $newquiz->coursemodule, $sectionnum);
        } else {
            $sectionid = add_mod_to_section($newquiz);
        }
        if (! $sectionid) {
            throw new reader_exception('Could not add the new course module to that section');
        }
        if (! $DB->set_field('course_modules', 'section',  $sectionid, array('id' => $newquiz->coursemodule))) {
            throw new reader_exception('Could not update the course module with the correct section');
        }

        // if the section is hidden, we should also hide the new quiz activity
        if (! isset($newquiz->visible)) {
            $newquiz->visible = $DB->get_field('course_sections', 'visible', array('id' => $sectionid));
        }
        set_coursemodule_visible($newquiz->coursemodule, $newquiz->visible);

        // Trigger mod_updated event with information about this module.
        $event = (object)array(
            'courseid'   => $newquiz->course,
            'cmid'       => $newquiz->coursemodule,
            'modulename' => $newquiz->modulename,
            'name'       => $newquiz->name,
            'userid'     => $USER->id
        );
        if (function_exists('events_trigger_legacy')) {
            events_trigger_legacy('mod_updated', $event);
        } else {
            events_trigger('mod_updated', $event);
        }

        // rebuild_course_cache (needed for Moodle 2.0)
        rebuild_course_cache($courseid, true);

        return $newquiz;
    }

    /**
     * remove_coursemodule
     *
     * @param integer $cmid_or_instanceid
     * @param integer $modname (optional, default="")
     * @return xxx
     * @todo Finish documenting this function
     */
    public function remove_coursemodule($cmid_or_instanceid, $modname='') {
        global $CFG, $DB, $OUTPUT;

        // get course module - with sectionnum :-)
        if ($modname) {
            if (! $cm = get_coursemodule_from_instance($modname, $cmid_or_instanceid, 0, true)) {
                throw new moodle_exception(get_string('invalidmodulename', 'error', "$modname (id=$cmid_or_instanceid)"));
            }
        } else {
            if (! $cm = get_coursemodule_from_id('', $cmid_or_instanceid, 0, true)) {
                throw new moodle_exception(get_string('invalidmoduleid', 'error', $cmid_or_instanceid));
            }
        }

        if (function_exists('course_delete_module')) {
            // Moodle >= 2.5
            course_delete_module($cm->id);
        } else {
            // Moodle <= 2.4
            $libfile = $CFG->dirroot.'/mod/'.$cm->modname.'/lib.php';
            if (! file_exists($libfile)) {
                throw new moodle_exception("$cm->modname lib.php not accessible ($libfile)");
            }
            require_once($libfile);

            $deleteinstancefunction = $cm->modname.'_delete_instance';
            if (! function_exists($deleteinstancefunction)) {
                throw new moodle_exception("$cm->modname delete function not found ($deleteinstancefunction)");
            }

            // copied from 'course/mod.php'
            if (! $deleteinstancefunction($cm->instance)) {
                throw new moodle_exception("Could not delete the $cm->modname (instance id=$cm->instance)");
            }
            if (! delete_course_module($cm->id)) {
                throw new moodle_exception("Could not delete the $cm->modname (coursemodule, id=$cm->id)");
            }
            if (! $sectionid = $DB->get_field('course_sections', 'id', array('course' => $cm->course, 'section' => $cm->sectionnum))) {
                throw new moodle_exception("Could not get section id (course id=$cm->course, section num=$cm->sectionnum)");
            }
            if (! delete_mod_from_section($cm->id, $sectionid)) {
                throw new moodle_exception("Could not delete the $cm->modname (id=$cm->id) from that section (id=$sectionid)");
            }
            reader_add_to_log($cm->course, 'course', 'delete mod', "view.php?id=$cm->course", "$cm->modname $cm->instance", $cm->id);
        }

        // Note: course cache was rebuilt in "delete_mod_from_section()" or "course_delete_module()"
    }

    /**
     * add_question_categories
     *
     * @uses $DB
     * @param xxx $quiz
     * @param xxx $cm
     * @param xxx $item
     * @param xxx $r (optional, default=0)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_question_categories($quiz, $cm, $item, $r=0) {
        // extract $itemid
        $itemid = $item['@']['id'];

        // select $remotesite
        $remotesite = $this->remotesites[$r];

        // fetch question categories
        list($module, $categories) = $remotesite->get_questions($itemid);

        if (isset($module->question_instances)) {
            $sumgrades = 0;
            foreach ($module->question_instances as $instance) {
                $sumgrades += $instance->grade;
            }
            if ($sumgrades==0 && $quiz->grade > 0) {
                if ($instance = reset($module->question_instances)) {
                    $module->question_instances[$instance->id]->grade = 1;
                }
            }
            if ($this->bar) {
                $this->bar->add_quiz($categories, $module->question_instances);
            }
        }

        // prune questions to leave only main questions or sub questions
        // e.g. questions used by random or multianswer questions
        $this->prune_question_categories($module, $categories);

        // we need to track old and new question ids
        $restoreids = new reader_restore_ids();

        foreach ($categories as $category) {
            if ($this->bar) {
                $this->bar->start_category($category->id);
            }
            $this->add_question_category($restoreids, $category, $quiz, $cm);
            if ($this->bar) {
                $this->bar->finish_category();
            }
        }

        if (isset($module->question_instances)) {
            foreach ($module->question_instances as $instance) {
                if ($this->bar) {
                    $this->bar->start_instance($instance->id);
                }
                $this->add_question_instance($restoreids, $instance, $quiz);
                if ($this->bar) {
                    $this->bar->finish_instance();
                }
            }
        }

        // convert old ids to new ids and make other adjustments
        $this->add_question_postprocessing($restoreids, $module, $quiz);
    }

    /**
     * has_random_questions
     *
     * @uses $DB
     * @param xxx $category
     * @return xxx
     * @todo Finish documenting this function
     */
    public function has_random_questions($category) {
        if (isset($category->questions)) {
            foreach ($category->questions as $question) {
                if ($question->qtype=='random') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * has_nonrandom_questions
     *
     * @uses $DB
     * @param xxx $category
     * @return xxx
     * @todo Finish documenting this function
     */
    public function has_nonrandom_questions($category) {
        if (isset($category->questions)) {
            foreach ($category->questions as $question) {
                if ($question->qtype != 'random') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * prune_question_categories
     *
     * @param xxx $module (passed by reference)
     * @param xxx $categories (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function prune_question_categories(&$module, &$categories) {
        // ids of questions used in this $quiz
        $mainids = array();
        $keepids = array();
        $skipids = array();

        // get main questions used in this quiz
        if (isset($module->question_instances)) {
            foreach ($module->question_instances as $instance) {
                $mainids[$instance->question] = array($instance->question);
            }
        }

        // get sub-questions used in this quiz
        foreach ($categories as $categoryid => $category) {

            // fix category name, if necessary
            if ($category->name=='ordering' || $category->name=='ORDERING' || $category->name=='ORDER') {
                $categories[$categoryid]->name = 'Ordering';
            }

            // fix category context level, if necessary
            if ($category->context->level=='course' && ! $this->is_default_category($category)) {
                $categories[$categoryid]->context->level = 'module';
            }

            // prune faulty questions and answers
            if (isset($category->questions)) {

                $has_nonrandom = $this->has_nonrandom_questions($category);
                foreach ($category->questions as $questionid => $question) {
                    if (! $this->question_has_correct_answer($mainids, $categories, $categoryid, $questionid, $has_nonrandom)) {
                        $skipids[] = $questionid;
                        if (debugging('', DEBUG_DEVELOPER)) {
                            echo 'Oops, faulty question found';
                            print_object($question);
                        }
                    }
                }
            }
        }

        // flatten array of required question ids
        foreach (array_keys($mainids) as $mainid) {
            $keepids = array_merge($keepids, array_diff($mainids[$mainid], $skipids));
        }
        $keepids = array_flip($keepids);

        foreach ($categories as $categoryid => $category) {
            // delete unused or faulty questions
            if (isset($category->questions)) {
                foreach ($category->questions as $questionid => $question) {
                    if (array_key_exists($questionid, $keepids)) {
                        continue; // keep this question
                    }
                    unset($categories[$categoryid]->questions[$questionid]);
                }
            }
            // delete category if it now contains no questions
            if (empty($categories[$categoryid]->questions)) {
                unset($categories[$categoryid]);
            }
        }

        if (isset($module->question_instances)) {
            foreach ($module->question_instances as $instanceid => $instance) {
                if (array_key_exists($instance->question, $keepids)) {
                    continue; // keep this question instance
                }
                unset($module->question_instances[$instanceid]);
            }
        }

        return $categories;
    }

    /**
     * question_has_correct_answer
     *
     * @param array   $mainids    (passed by reference)
     * @param array   $categories (passed by reference)
     * @param integer $categoryid
     * @param integer $questionid
     * @param boolean $has_norandom
     * @return boolean TRUE if this question as a correct answer, or FALSE otherwise
     * @todo Finish documenting this function
     */
    public function question_has_correct_answer(&$mainids, &$categories, $categoryid, $questionid, $has_nonrandom) {

        $question = $categories[$categoryid]->questions[$questionid];
        switch ($question->qtype) {

            case 'description':
                return true;
                break;

            case 'match':
                if (empty($question->matchs)) {
                    return false; // shouldn't happen !!
                }
                foreach ($question->matchs as $m => $match) {
                    if (isset($match->questiontext) && strlen($match->questiontext) && isset($match->answertext) && strlen($match->answertext)) {
                        // do nothing
                    } else {
                        unset($question->matchs[$m]);
                    }
                }
                $categories[$categoryid]->questions[$questionid] = $question;
                if (count($question->matchs)) {
                    return true;
                }
                return false;
                break;

            case 'multianswer':
                if (empty($question->multianswers)) {
                    return false; // shouldn't happen !!
                }
                foreach ($question->multianswers as $a => $answer) {
                    $count_correct = 0;
                    $sequence = explode(',', $answer->sequence);
                    foreach ($sequence as $q) {
                        foreach (array_keys($categories) as $c) {
                            if (empty($categories[$c]->questions)) {
                                continue;
                            }
                            if (empty($categories[$c]->questions[$q])) {
                                continue;
                            }
                            if ($this->question_has_correct_answer($module, $categories, $c, $q, $has_nonrandom)) {
                                $count_correct ++;
                                break;
                            }
                        }
                    }
                    if ($count_correct && $count_correct==count($sequence)) {
                        return true; // all gaps have a correct answer
                    }
                    // oops, one or more gaps has no correct answer
                    // maybe we should remove this question ?
                }
                return false;
                break;

            case 'multichoice':
                if (empty($question->multichoice) || empty($question->answers)) {
                    return false; // shouldn't happen !!
                }
                // remove blank and missing answers
                $answerids = array();
                foreach ($question->answers as $a => $answer) {
                    if (isset($answer->answer_text) && strlen($answer->answer_text)) {
                        $answerids[] = $answer->id; // keep this answer
                        if (isset($answer->fraction) && intval($answer->fraction)) {
                            $has_correct = true;
                        }
                    } else {
                        unset($question->answers[$a]); // skip this answer
                    }
                }
                if ($answerids = implode(',', $answerids)) {
                    $question->multichoice->answers = $answerids;
                    $categories[$categoryid]->questions[$questionid] = $question;
                    return true;
                }
                return false;
                break;

            case 'ordering':
                if (isset($question->answers) && count($question->answers)) {
                    return true;
                }
                return false;
                break;

            case 'random':
                if (isset($mainids[$question->id]) && $has_nonrandom) {
                    // for random questions, we keep the whole category
                    $mainids[$questionid] = array_keys($categories[$categoryid]->questions);
                    return true;
                }
                if (isset($mainids[$question->id]) && ! $has_nonrandom) {
                    // this question is a "random" question in a
                    // category that contains ONLY "random" questions
                    // Therefore, there is no point in keeping this question
                    unset($mainids[$questionid]);
                    return false;
                }
                if (isset($mainids[$question->parent])) {
                    // add this question to the list of child questions for this parent
                    $mainids[$question->parent][] = $questionid;
                    return true;
                }
                if ($question->id==$question->parent) {
                    return true;
                }
                // random question with no parent - shouldn't happen !
                return false;
                break;

            case 'numerical':
                if (empty($question->numerical) || empty($question->answers)) {
                    return false; // shouldn't happen !!
                }
                // remove blank and missing answers
                $has_correct = false;
                foreach ($question->answers as $a => $answer) {
                    if (isset($answer->answer_text) && strlen($answer->answer_text)) {
                        if (isset($answer->fraction) && intval($answer->fraction)) {
                            $has_correct = true;
                        }
                    } else {
                        unset($question->answers[$a]); // skip this answer
                    }
                }
                if ($has_correct) {
                    $categories[$categoryid]->questions[$questionid] = $question;
                    return true;
                }
                return false;
                break;

            case 'shortanswer':
            case 'truefalse':
                foreach ($question->answers as $a => $answer) {
                    if (isset($answer->fraction) && intval($answer->fraction)) {
                        return true;
                    }
                }
                return false;
                break;

            default:
                if (debugging('', DEBUG_DEVELOPER)) {
                    echo 'Oops, unknown question type: '.$question->qtype;
                    print_object($question);
                }
                return true;
        }
    }

    /**
     * add_question_category
     *
     * @uses $DB
     * @param xxx $restoreids (passed by reference)
     * @param xxx $itemid
     * @param xxx $category
     * @param xxx $quiz
     * @param xxx $cm
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_question_category(&$restoreids, $category, $quiz, $cm) {
        global $DB;
        static $default = null;

        if (empty($category->questions)) {
            return false; // skip empty categories
        }

        // initialize the default id object
        if ($default===null) {
            $default = (object)array('course' => null, 'module' => null);
        }

        // update default course info, if necessary
        if ($default->course===null || $default->course->id != $cm->course) {
            $default->course = new stdClass();
            $default->course->id = $cm->course;
            $default->course->context = reader_get_context(CONTEXT_COURSE, $default->course->id);
            $default->course->questioncategory = question_make_default_categories(array($default->course->context));
        }

        // update default module info, if necessary
        if ($default->module===null || $default->module->id != $cm->id) {
            $default->module = new stdClass();
            $default->module->id = $cm->id;
            $default->module->context = reader_get_context(CONTEXT_MODULE, $default->module->id);
            $default->module->questioncategory = question_make_default_categories(array($default->module->context));
        }

        if (empty($category->info)) {
            $a = (object)array('category' => $category->name, 'quiz' => $quiz->name);
            $category->info = get_string('defaultquestioncategoryinfo', 'mod_reader', $a);
        }

        $category->parent = 0;
        if ($this->is_default_category($category)) {
            if ($category->context->level=='course') {
                $category->contextid = $default->course->context->id;
                $categoryid = $default->course->questioncategory->id;
            } else {
                $category->contextid = $default->module->context->id;
                $categoryid = $default->module->questioncategory->id;
            }
        } else {
            $category->contextid = $default->module->context->id;
            $categoryid = $this->get_categoryid($category);
        }

        if (! $categoryid) {
            return false;
        }

        $bestquestionids = $this->get_best_match_questions($categoryid, $category);

        foreach ($category->questions as $question) {
            if ($this->bar) {
                $this->bar->start_question($question->id);
            }
            $this->add_question($bestquestionids, $restoreids, $categoryid, $question);
            if ($this->bar) {
                $this->bar->finish_question();
            }
        }
    }

    /**
     * get_categoryid
     *
     * @param xxx $category
     * @return boolean
     * @todo Finish documenting this function
     */
    public function get_categoryid($category) {
        global $DB;

        $params = array('contextid' => $category->contextid, 'name' => $category->name);
        if ($records = $DB->get_records('question_categories', $params)) {
            // we only expect one record, but there can be duplicates
            foreach ($records as $record) {
                if ($record->info != $category->info) {
                    $record->info = $category->info;
                    $DB->update_record('question_categories', $record);
                }
            }
        } else {
            $record = (object)array(
                'name' => $category->name,
                'info' => $category->info,
                'stamp' => $category->stamp,
                'parent' => $category->parent,
                'sortorder' => 999,
                'contextid' => $category->contextid
            );
            $record->id = $DB->insert_record('question_categories', $record);
        }
        return $record->id;
    }

    /**
     * is_default_category
     *
     * @param xxx $category
     * @return boolean
     * @todo Finish documenting this function
     */
    public function is_default_category($category) {
        if (strpos($category->name, 'Default')===false && strpos($category->name, 'default')===false) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * add_question
     *
     * @uses $DB
     * @param xxx $bestquestionids (passed by reference)
     * @param xxx $restoreids (passed by reference)
     * @param xxx $categoryid of newly restored category
     * @param xxx $question from backup data
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_question(&$bestquestionids, &$restoreids, $categoryid, $question) {
        global $DB, $USER;
        static $numfields = null;

        // setup $numfields ($fieldname => $defaultvalue)
        if ($numfields===null) {
            $numfields = array('timecreated'  => time(),
                               'timemodified' => time(),
                               'createdby'    => $USER->id,
                               'modifiedby'   => $USER->id);
        }

        // set category id
        $question->category = $categoryid;

        // set defaultmark (Moodle 2.x) from defaultgrade (Moodle 1.x)
        if ($question->defaultgrade) {
            $question->defaultmark = $question->defaultgrade;
        }

        // adjust fields on ordering questions, if necessary
        if ($question->qtype=='ordering' && empty($question->questiontext)) {
            $question->questiontext = get_string('defaultquestionname', 'qtype_ordering');
        }

        // updated "modified" info
        $question->modifiedby = $USER->id;
        $question->timemodified = time();

        // cache question id (from backup xml file)
        $xmlquestionid = $question->id;
        unset($question->id);

        // add/update the question record
        if (isset($bestquestionids[$xmlquestionid]) && $bestquestionids[$xmlquestionid]) {

            // sanity check on numeric fields
            // e.g. "$@NULL@$" in "createdby" field
            foreach ($numfields as $fieldname => $defaultvalue) {
                if (! is_numeric($question->$fieldname)) {
                    $question->$fieldname = $defaultvalue;
                }
            }

            $question->id = $bestquestionids[$xmlquestionid];
            if (! $DB->update_record('question', $question)) {
                throw new moodle_exception(get_string('cannotupdaterecord', 'error', 'question (id='.$question->id.')'));
            }
        } else {
            $question->createdby = $question->modifiedby;
            $question->timecreated = $question->timemodified;
            if (! $question->id = $DB->insert_record('question', $question)) {
                throw new moodle_exception(get_string('cannotinsertrecord', 'error', 'question'));
            }
        }

        // map old (backup) question id to new $question->id in this Moodle $DB
        $restoreids->set_ids('question', $xmlquestionid, $question->id);

        // perhaps we should save the options like this?
        // question_bank::get_qtype($question->qtype)->save_question_options($question);

        switch ($question->qtype) {
            case 'description' : /* do nothing */ break;
            case 'match'       : $this->add_question_match($restoreids, $question);       break;
            case 'multianswer' : $this->add_question_multianswer($restoreids, $question); break;
            case 'multichoice' : $this->add_question_multichoice($restoreids, $question); break;
            case 'numerical'   : $this->add_question_numerical($restoreids, $question);   break;
            case 'ordering'    : $this->add_question_ordering($restoreids, $question);    break;
            case 'random'      : $this->add_question_random($restoreids, $question);      break;
            case 'truefalse'   : $this->add_question_truefalse($restoreids, $question);   break;
            case 'shortanswer' : $this->add_question_shortanswer($restoreids, $question); break;
            default: throw new moodle_exception('Unknown qtype: '.$question->qtype);
        }
    }

    /**
     * add_question_match
     *
     * @uses $DB
     * @param xxx $restoreids (passed by reference)
     * @param xxx $question
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_question_match(&$restoreids, $question) {
        global $DB;

        $bestsubquestionids = $this->get_best_match_subquestions($question);

        list($table, $field) = $this->get_question_options_table('match', true);
        // Moodle <= 2.4: question_match_sub (question)
        // Moodle >= 2.5: qtype_match_subquestion (questionid)

        $subquestions = array();
        foreach ($question->matchs as $match) {
            $id = $match->id;
            unset($match->id);
            $match->$field = $question->id;
            if (empty($bestsubquestionids[$id])) {
                if (! $match->id = $DB->insert_record($table, $match)) {
                    throw new moodle_exception(get_string('cannotinsertrecord', 'error', $table));
                }
            } else {
                $match->id = $bestsubquestionids[$id];
                if (! $DB->update_record($table, $match)) {
                    throw new moodle_exception(get_string('cannotupdaterecord', 'error', $table.' (id='.$match->id.')'));
                }
            }
            $subquestions[] = $match->id;
        }
        $subquestions = implode(',', $subquestions);

        if (isset($question->matchoptions)) {
            $shuffleanswers = $question->matchoptions->shuffleanswers;
            $shownumcorrect = $question->matchoptions->shownumcorrect;
        } else {
            $shuffleanswers = 1;
            $shownumcorrect = 0;
        }

        // create $options for this match question
        $options = (object)array(
            'subquestions'    => $subquestions,
            'shuffleanswers'  => $shuffleanswers,
            'shownumcorrect'  => $shownumcorrect,
            'correctfeedback' => '',
            'incorrectfeedback' => '',
            'partiallycorrectfeedback' => '',
            'correctfeedbackformat'    => 0, // FORMAT_MOODLE
            'incorrectfeedbackformat'  => 0, // FORMAT_MOODLE
            'partiallycorrectfeedbackformat' => 0, // FORMAT_MOODLE
        );

        // add/update $options for this match question
        $this->add_question_options('match', $options, $question);
    }

    /**
     * add_question_multianswer
     *
     * @uses $DB
     * @param xxx $restoreids (passed by reference)
     * @param xxx $question
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_question_multianswer(&$restoreids, $question) {
        global $DB;

        if (isset($question->multianswers[0]->sequence)) {
            $sequence = $question->multianswers[0]->sequence;
        } else {
            // e.g. Choose Your Own Adventure (500) Lost City of the Outback
            $sequence = ''; // shouldn't happen !!
        }

        // create $options for this multichoice question
        $options = (object)array(
            'sequence' => $sequence,
        );

        // add/update $options for this multianswer question
        $this->add_question_options('multianswer', $options, $question);
    }

    /**
     * add_question_multichoice
     *
     * @uses $DB
     * @param xxx $restoreids (passed by reference)
     * @param xxx $question
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_question_multichoice(&$restoreids, $question) {
        global $DB;

        $bestanswerids = $this->get_best_match_answers($question);

        $sumfraction = 0;
        $maxfraction = -1;
        if (isset($question->answers)) {
            foreach ($question->answers as $xmlanswer) {
                $answer = (object)array(
                    'question' => $question->id,
                    'fraction' => $xmlanswer->fraction,
                    'answer'   => $xmlanswer->answer_text,
                    'answerformat' => FORMAT_MOODLE, // =0
                    'feedback' => '',
                    'feedbackformat' => FORMAT_MOODLE, // =0
                );
                $this->add_question_answer($restoreids, $bestanswerids, $xmlanswer, $answer);

                if ($answer->fraction > 0) {
                    $sumfraction += $answer->fraction;
                }
                if ($maxfraction < $answer->fraction) {
                    $maxfraction = $answer->fraction;
                }
            }
        }

        // create $options for this multichoice question
        $options = (object)array(
            'answers'         => $question->multichoice->answers,
            'layout'          => $question->multichoice->layout,
            'single'          => $question->multichoice->single,
            'shuffleanswers'  => $question->multichoice->shuffleanswers,
            'answernumbering' => $question->multichoice->answernumbering,
            'shownumcorrect'  => $question->multichoice->shownumcorrect,

            // feedback settings
            'correctfeedback'          => $question->multichoice->correctfeedback,
            'incorrectfeedback'        => $question->multichoice->incorrectfeedback,
            'partiallycorrectfeedback' => $question->multichoice->partiallycorrectfeedback,
            'correctfeedbackformat'          => FORMAT_MOODLE, // =0,
            'incorrectfeedbackformat'        => FORMAT_MOODLE, // =0,
            'partiallycorrectfeedbackformat' => FORMAT_MOODLE, // =0,
        );

        // add/update $options for this multichoice question
        $this->add_question_options('multichoice', $options, $question);
    }

    /**
     * add_question_numerical
     *
     * @uses $DB
     * @param xxx $restoreids (passed by reference)
     * @param xxx $question
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_question_numerical(&$restoreids, $question) {
        global $DB;

        $bestanswerids = $this->get_best_match_answers($question);

        if (isset($question->answers)) {
            foreach ($question->answers as $xmlanswer) {
                $answer = (object)array(
                    'question' => $question->id,
                    'fraction' => $xmlanswer->fraction,
                    'answer'   => $xmlanswer->answer_text,
                    'feedback' => '',
                    'answerformat'   => FORMAT_MOODLE, // =0
                    'feedbackformat' => FORMAT_MOODLE, // =0
                );
                $this->add_question_answer($restoreids, $bestanswerids, $xmlanswer, $answer);

                // add "tolerance" value for this answer
                // stored in the "question_numerical" table
                if ($answer->id = $restoreids->get_newid('question_answers', $xmlanswer->id)) {
                    if (empty($question->numerical[$xmlanswer->id])) {
                        $tolerance = 0;
                    } else {
                        $tolerance = $question->numerical[$xmlanswer->id]->tolerance;
                    }
                    $table = 'question_numerical';
                    $params = array('question' => $question->id, 'answer' => $answer->id);
                    if ($numerical = $DB->get_record($table, $params)) {
                        $numerical->tolerance = $tolerance;
                        $DB->update_record($table, $numerical);
                    } else {
                        $numerical = (object)array(
                            'question'  => $question->id,
                            'answer'    => $answer->id,
                            'tolerance' => $tolerance,
                        );
                        $DB->insert_record($table, $numerical);
                    }
                }
            }
        }

        // create $options for this numerical question
        // none of these options existed in Moodle 1.x
        $options = (object)array(
            'showunits'       => 3,
            'unitsleft'       => 0,
            'unitgradingtype' => 0,
            'unitpenalty'     => 0.1,
        );

        // add/update $options for this numerical question
        $this->add_question_options('numerical', $options, $question);
    }

    /**
     * add_question_ordering
     *
     * @uses $DB
     * @param xxx $restoreids (passed by reference)
     * @param xxx $question
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_question_ordering(&$restoreids, $question) {
        global $DB;

        $bestanswerids = $this->get_best_match_answers($question);

        $sortorder = 1;
        foreach ($question->answers as $xmlanswerid => $xmlanswer) {
            $answer = (object)array(
                'question' => $question->id,
                'fraction' => $sortorder++,
                'answer'   => $xmlanswer->answer_text,
                'answerformat' => FORMAT_MOODLE, // =0
                'feedback' => '',
                'feedbackformat' => FORMAT_MOODLE, // =0
            );
            $this->add_question_answer($restoreids, $bestanswerids, $xmlanswer, $answer);
        }

        // create $options for this ordering question
        $options = (object)array(
            'selecttype' => $question->ordering->selecttype,
            'selectcount' => $question->ordering->selectcount,
            'correctfeedback' => $question->ordering->correctfeedback,
            'correctfeedbackformat' => FORMAT_MOODLE, // =0
            'incorrectfeedback' => $question->ordering->incorrectfeedback,
            'incorrectfeedbackformat' => FORMAT_MOODLE, // =0
            'partiallycorrectfeedback' => $question->ordering->partiallycorrectfeedback,
            'partiallycorrectfeedbackformat' => FORMAT_MOODLE // =0
        );

        // add/update $options for this ordering question
        $this->add_question_options('ordering', $options, $question);
    }

    /**
     * add_question_random
     *
     * set the "parent" field to be the same as the "id" field
     * and force the question name to be "Random $categoryname"
     *
     * @uses $DB
     * @param xxx $restoreids (passed by reference)
     * @param xxx $question
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_question_random(&$restoreids, $question) {
        global $DB;

        // set parent field, if necessary
        if ($question->parent==0) {
            $question->parent = $restoreids->get_oldid('question', $question->id);
        }

        // set question name depending on whether we include subcategories or not
        // (Note: $question->questiontext is used as "include subcategories" flag)
        if (empty($question->questiontext)) {
            $strname = 'randomqname';
        } else {
            $strname = 'randomqplusname';
        }
        $question->name = $DB->get_field('question_categories', 'name', array('id' => $question->category));
        $question->name = get_string($strname, 'qtype_random', shorten_text($question->name, 100));

        // update record with new "parent" and "name"
        $DB->update_record('question', $question);
    }

    /**
     * add_question_shortanswer
     *
     * @uses $DB
     * @param xxx $restoreids (passed by reference)
     * @param xxx $question
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_question_shortanswer(&$restoreids, $question) {
        global $DB;

        $bestanswerids = $this->get_best_match_answers($question);

        foreach ($question->answers as $xmlanswer) {
            $answer = (object)array(
                'question' => $question->id,
                'fraction' => $xmlanswer->fraction,
                'answer'   => $xmlanswer->answer_text,
                'answerformat' => FORMAT_MOODLE, // =0
                'feedback' => '',
                'feedbackformat' => FORMAT_MOODLE, // =0
            );
            $this->add_question_answer($restoreids, $bestanswerids, $xmlanswer, $answer);
        }

        // create $options for this shortanswer question
        $options = (object)array(
            'answers'  => (empty($question->shortanswer->answer) ? '' : $question->shortanswer->answer),
            'usecase'  => (empty($question->shortanswer->usecase) ? 0 : $question->shortanswer->usecase),
        );

        // add/update $options for this shortanswer question
        $this->add_question_options('shortanswer', $options, $question);
    }

    /**
     * add_question_truefalse
     *
     * @uses $DB
     * @param xxx $restoreids (passed by reference)
     * @param xxx $question
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_question_truefalse(&$restoreids, $question) {
        global $DB;

        $bestanswerids = $this->get_best_match_answers($question);

        foreach ($question->answers as $xmlanswer) {
            $answer = (object)array(
                'question' => $question->id,
                'fraction' => $xmlanswer->fraction,
                'answer'   => $xmlanswer->answer_text,
                'answerformat' => FORMAT_MOODLE, // =0
                'feedback' => '',
                'feedbackformat' => FORMAT_MOODLE, // =0
            );
            $this->add_question_answer($restoreids, $bestanswerids, $xmlanswer, $answer);
        }

        // create $options for this truefalse question
        $options = (object)array(
            'trueanswer' => $question->truefalse->trueanswer,
            'falseanswer' => $question->truefalse->falseanswer,
        );

        // add/update $options for this truefalse question
        $this->add_question_options('truefalse', $options, $question);
    }

    /**
     * add_question_options
     *
     * @uses $DB
     * @param string $table
     * @param object $options
     * @param object $question
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_question_options($type, $options, $question) {
        global $DB;

        list($table, $field) = $this->get_question_options_table($type);

        if ($table && $field) {
            $options->$field = $question->id;
            if ($options->id = $DB->get_field($table, 'id', array($field => $question->id))) {
                if (! $DB->update_record($table, $options)) {
                    throw new moodle_exception(get_string('cannotupdaterecord', 'error', $table.' (id='.$options->id.')'));
                }
            } else {
                if (! $options->id = $DB->insert_record($table, $options)) {
                    throw new moodle_exception(get_string('cannotinsertrecord', 'error', $table));
                }
            }
        }

        // update progress bar
        if ($this->bar) {
            $this->bar->finish_options();
        }
    }

    /**
     * get_question_options_table
     *
     * @uses $DB
     * @param string $type
     * @return array($table, $field)
     * @todo Finish documenting this function
     */
    public function get_question_options_table($type, $sub=false) {
        global $DB;

        // we need the db manager to detect the names of question options tables
        $dbman = $DB->get_manager();

        switch (true) {

            // from Moodle 2.5, the table names start to look like this
            case $dbman->table_exists('qtype_'.$type.'_options'):
                if ($sub) {
                    $table = 'qtype_'.$type.'_subquestions';
                } else {
                    $table = 'qtype_'.$type.'_options';
                }
                $field = 'questionid';
                break;

            // Moodle >= 2.0 numeric questions
            case $dbman->table_exists('question_'.$type.'_options'):
                if ($sub) {
                    $table = 'question_'.$type.'_subquestions';
                } else {
                    $table = 'question_'.$type.'_options';
                }
                $field = 'question';
                break;

            // Moodle <= 2.4
            case $dbman->table_exists('question_'.$type):
                if ($sub) {
                    $table = 'question_'.$type.'_sub';
                } else {
                    $table = 'question_'.$type;
                }
                $field = 'question';
                break;

            default:
                $table = '';
                $field = '';
        }

        return array($table, $field);
    }

    /**
     * get_best_match_questions
     *
     * @uses $DB
     * @param xxx $question
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_best_match_questions($categoryid, $category) {
        // get the ids of the old (=existing) questions
        // which most closely match the questions in this category
        $table = 'question';
        $params = array('category' => $categoryid);
        $xmlrecords = $category->questions;
        $xmlfield = array(
            array('qtype', 'random', 'name'),  // if ($category->questions[$q]->qtype=='random')  {$xmlfield = 'name'}
            array('questiontext', '', 'name'), // if ($category->questions[$q]->questiontext=='') {$xmlfield = 'name'}
            'questiontext'                     // otherwise $xmlfield = 'questiontext'
        );
        return $this->get_best_matches($table, $params, $xmlrecords, $xmlfield);
    }

    /**
     * get_best_match_answers
     *
     * @uses $DB
     * @param xxx $question
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_best_match_answers($question) {
        if (empty($question->answers)) {
            return array(); // shouldn't happen !!
        }
        $table      = 'question_answers';
        $params     = array('question' => $question->id);
        $xmlrecords = $question->answers;
        $xmlfield   = 'answer_text';
        $dbfield    = 'answer';
        return $this->get_best_matches($table, $params, $xmlrecords, $xmlfield, $dbfield);
    }

    /**
     * get_best_match_subquestions
     *
     * @uses $DB
     * @param xxx $question
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_best_match_subquestions($question) {
        list($table, $field) = $this->get_question_options_table('match', true);
        $params     = array($field => $question->id);
        $xmlrecords = $question->matchs;
        $xmlfield   = 'questiontext';
        return $this->get_best_matches($table, $params, $xmlrecords, $xmlfield);
    }

    /**
     * get_best_matches
     *
     * @uses $DB
     * @param string $table
     * @param array  $params
     * @param array  $xmlrecords
     * @param mixed  $xmlfield either string or array
     * @param string $dbfield (optional, default='')
     * @return array
     * @todo Finish documenting this function
     */
    public function get_best_matches($table, $params, $xmlrecords, $xmlfield, $dbfield='') {
        global $DB;

        $ids = array();
        if ($dbrecords = $DB->get_records($table, $params)) {
            foreach ($xmlrecords as $xmlrecordid => $xmlrecord) {

                // make sure we have the "id" from the xmlrecord
                // this is especially for "answers" and "matchs"
                // which are not indexed by the "id" field
                if (isset($xmlrecord->id)) {
                    $xmlrecordid = $xmlrecord->id;
                }

                // start setup for this $xmlrecord
                $ids[$xmlrecordid] = array();

                // set the $xmlfield will we use for comparison
                if (is_array($xmlfield)) {
                    foreach ($xmlfield as $field) {
                        if (is_array($field)) {
                            list($conditionfield, $conditionvalue, $conditionfield) = $field;
                            if ($xmlrecord->$conditionfield==$conditionvalue) {
                                $xmlfield = $conditionfield;
                                break;
                            }
                        } else if (is_string($field)) {
                            $xmlfield = $field;
                            break;
                        }
                    }
                }

                // set $dbfield, if necessary
                if ($dbfield=='') {
                    $dbfield = $xmlfield;
                }

                // make sure $str1 string is not too long
                $str1 = $xmlrecord->$xmlfield;
                if (strlen($str1) > 255) {
                    $str1 = substr(0, 255);
                }

                // get the minimum levenshtein difference for a string of this length
                $min_levenshtein = $this->get_min_levenshtein($xmlrecord->$xmlfield);

                // compare this $xmlrecord to all the db (=existing) records
                foreach ($dbrecords as $dbrecordid => $dbrecord) {

                    // make sure $str2 string is not too long
                    $str2 = $dbrecord->$dbfield;
                    if (strlen($str2) > 255) {
                        $str2 = substr(0, 255);
                    }

                    // compare the strings and store the levenshtein difference
                    $levenshtein = levenshtein($str1, $str2);
                    if ($levenshtein <= $min_levenshtein) {
                        $ids[$xmlrecordid][$dbrecordid] = $levenshtein;
                    }
                }
            }
        }

        // select best match not used by other records
        $bestids = array();
        foreach ($ids as $xmlrecordid => $dbrecordids) {

            // sort db record ids by Levenshtein difference
            // (lower difference is better match, 0 is a best)
            asort($dbrecordids);

            // remove ids that have already been used
            $dbrecordids = array_keys($dbrecordids);
            $dbrecordids = array_diff($dbrecordids, $bestids);

            // select the best remaining match
            $dbrecordid = reset($dbrecordids);
            $bestids[$xmlrecordid] = $dbrecordid;
        }

        // hide/delete $dbrecords that were not selected
        if ($dbrecords) {
            $dbman = $DB->get_manager();
            $has_hidden_field = $dbman->field_exists($table, 'hidden');

            $ids = array_values($bestids); // dbrecordids

            // unhide records that were selected
            if ($has_hidden_field) {
                list($select, $params) = $DB->get_in_or_equal($ids);
                $DB->set_field_select($table, 'hidden', 0, "id $select", $params);
            }

            foreach ($bestids as $xmlrecordid => $dbrecordid) {
                unset($dbrecords[$dbrecordid]);
            }

            // hide/delete remaining records
            if (count($dbrecords)) {
                $ids = array_keys($dbrecords);
                if ($has_hidden_field) {
                    list($select, $params) = $DB->get_in_or_equal($ids);
                    $DB->set_field_select($table, 'hidden', 1, "id $select", $params);
                } else {
                    $DB->delete_records_list($table, 'id', $ids);
                }
            }
        }

        return $bestids;
    }

    /**
     * get_min_levenshtein
     *
     * @param string $str
     * @return integer
     * @todo Finish documenting this function
     */
    public function get_min_levenshtein($str) {
        static $mins = array();

        $length = strlen($str);
        if (! array_key_exists($length, $mins)) {
            // set minimum required $levenshtein difference
            // we can then ignore any strings that differ
            // by greater than $min levenshtein
            // $length => $min levenshtein
            //     3   =>   2 (  3 = 3 * 2 / 2) = intval(sqrt( 3 * 2)) = intval(2.449)
            //     6   =>   3 (  6 = 4 * 3 / 2) = intval(sqrt( 6 * 2)) = intval(3.464)
            //    10   =>   4 ( 10 = 5 * 4 / 2) = intval(sqrt(10 * 2)) = intval(4.472)
            //    15   =>   5 ( 15 = 6 * 5 / 2) = intval(sqrt(15 * 2)) = intval(5.577)
            //    21   =>   6 ( 21 = 7 * 6 / 2) = intval(sqrt(21 * 2)) = intval(6.480)
            //    28   =>   7 ( 28 = 8 * 7 / 2) = intval(sqrt(28 * 2)) = intval(7.483)
            $min = 2;
            while ((($min + 1) * $min / 2) < $length) {
                $min ++;
            }

            // cache and return the $min value
            $mins[$length] = $min;
        }
        return $mins[$length];
    }

    public function add_question_answer(&$restoreids, $bestanswerids, $xmlanswer, $answer) {
        global $DB;
        if ($this->bar) {
            $this->bar->start_answer($xmlanswer->id);
        }
        if (empty($bestanswerids[$xmlanswer->id])) {
            if (! $answer->id = $DB->insert_record('question_answers', $answer)) {
                throw new moodle_exception(get_string('cannotinsertrecord', 'error', 'question_answers'));
            }
        } else {
            $answer->id = $bestanswerids[$xmlanswer->id];
            if (! $DB->update_record('question_answers', $answer)) {
                throw new moodle_exception(get_string('cannotupdaterecord', 'error', 'question_answers (id='.$answer->id.')'));
            }
        }
        $restoreids->set_ids('question_answers', $xmlanswer->id, $answer->id);
        if ($this->bar) {
            $this->bar->finish_answer();
        }
    }

    /**
     * add_question_instance
     *
     * @uses $DB
     * @param xxx $restoreids (passed by reference)
     * @param xxx $instance
     * @param xxx $quiz
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_question_instance(&$restoreids, $instance, $quiz) {
        global $DB;

        if (! $questionid = $restoreids->get_newid('question', $instance->question)) {
            return false; // question was not added for some reason
        }

        // add quiz question instance/slot record, if necessary
        if ($this->quiz_slots) {
            // Moodle >= 2.7
            $params = array('quizid' => $quiz->id, 'questionid' => $questionid);
            if (! $DB->record_exists('quiz_slots', $params)) {
                $page = $DB->get_field('quiz_slots', 'MAX(page)', array('quizid' => $quiz->id));
                $slot = $DB->get_field('quiz_slots', 'MAX(slot)', array('quizid' => $quiz->id));
                $slot = (object)array(
                    'quizid'     => $quiz->id,
                    'questionid' => $questionid,
                    'maxmark'    => $instance->grade,
                    'page'       => ($page ? $page : 1),
                    'slot'       => ($slot ? $slot : 0) + 1
                );
                if (! $DB->insert_record('quiz_slots', $slot)) {
                    throw new moodle_exception(get_string('cannotinsertrecord', 'error', 'quiz_slots'));
                }
            }
        } else {
            // Moodle <= 2.6
            $params = array('quiz' => $quiz->id, 'question' => $questionid);
            if (! $DB->record_exists('quiz_question_instances', $params)) {
                $instance = (object)array(
                    'quiz'     => $quiz->id,
                    'question' => $questionid,
                    'grade'    => $instance->grade,
                );
                if (! $DB->insert_record('quiz_question_instances', $instance)) {
                    throw new moodle_exception(get_string('cannotinsertrecord', 'error', 'quiz_question_instances'));
                }
            }
        }

        // add reader question instance record, if necessary
        $params = array('quiz' => $quiz->id, 'question' => $questionid);
        if (! $DB->record_exists('reader_question_instances', $params)) {
            $instance = (object)array(
                'quiz'     => $quiz->id,
                'question' => $questionid,
                'grade'    => $instance->grade,
            );
            if (! $id = $DB->insert_record('reader_question_instances', $instance)) {
                throw new moodle_exception(get_string('cannotinsertrecord', 'error', 'reader_question_instances'));
            }
        }
    }

    /**
     * add_question_postprocessing
     *
     * @param xxx $restoreids (passed by reference)
     * @param xxx $module
     * @param xxx $quiz
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_question_postprocessing(&$restoreids, $module, $quiz) {
        global $DB;

        // fix slots and layouts in quiz attempts
        $this->add_question_postprocessing_attempts($restoreids, $module, $quiz);

        // set $quiz->sumgrades
        $sumgrades = 0;
        if (isset($module->question_instances)) {
            foreach ($module->question_instances as $instance) {
                $sumgrades += $instance->grade;
            }
        }
        $DB->set_field('quiz', 'sumgrades', $sumgrades, array('id' => $quiz->id));

        // postprocessing for individual question types
        foreach ($restoreids->get_newids('question') as $questionid) {
            if ($parent = $DB->get_field('question', 'parent', array('id' => $questionid))) {
                $parent = $restoreids->get_newid('question', $parent);
                $DB->set_field('question', 'parent', $parent, array('id' => $questionid));
            }
            switch ($DB->get_field('question', 'qtype', array('id' => $questionid))) {
                case 'multianswer' : $this->add_question_postprocessing_multianswer($restoreids, $questionid); break;
                case 'match'       : $this->add_question_postprocessing_match($restoreids, $questionid);       break;
                case 'multichoice' : $this->add_question_postprocessing_multichoice($restoreids, $questionid); break;
                case 'truefalse'   : $this->add_question_postprocessing_truefalse($restoreids, $questionid);   break;
                case 'shortanswer' : $this->add_question_postprocessing_shortanswer($restoreids, $questionid); break;
            }
        }
    }

    /**
     * add_question_postprocessing_attempts
     *
     * @uses $DB
     * @param xxx $module
     * @param xxx $quiz
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_question_postprocessing_attempts(&$restoreids, $module, $quiz) {
        global $DB;

        // map question slots (=positions) to ids
        if (isset($module->questions)) {
            $questions = explode(',', $module->questions);
            foreach ($questions as $i => $question) {
                $questions[$i] = $restoreids->get_newid('question', $question);
            }
            $questions = array_filter($questions); // remove blanks
            $questions = array_values($questions); // number sequentially
        } else {
            $questions = array(); // shouldn't happen !!
        }

        $oldlayout = array();
        $newlayout = array();

        if ($this->quiz_slots) {
            // Moodle >= 2.7
            if ($slots = $DB->get_records('quiz_slots', array('quizid' => $quiz->id))) {

                // on table "quiz_slots", index "quizslot_quislo_uix" requires unique (quizid,slot)
                // so we must offset current "slot" values out of range before we reset anything
                if ($slot = $DB->get_field('quiz_slots', 'MAX(slot)', array('quizid' => $quiz->id))) {
                    $sql = 'UPDATE {quiz_slots} SET slot = (slot + ?) WHERE quizid = ?';
                    $DB->execute($sql, array($slot, $quiz->id));
                }

                // reset slot values to match order in $questions array
                foreach ($slots as $id => $slot) {
                    $newslot = array_search($slot->questionid, $questions);
                    if ($newslot===false) {
                        $DB->delete_records('quiz_slots', array('id' => $id));
                    } else {
                        $newslot++;
                        $DB->set_field('quiz_slots', 'slot', $newslot, array('id' => $id));
                        $newlayout[] = $newslot;
                        $newlayout[] = 0;
                    }
                    $oldlayout[] = $slot->slot;
                    $oldlayout[] = 0;
                }

            }
        } else {
            // Moodle <= 2.6
            $ids = explode(',', $quiz->questions);
            $ids = array_filter($ids);
            $ids = array_values($ids);
            $oldslot = 0;
            foreach ($ids as $id) {
                $oldlayout[] = ++$oldslot;
                $oldlayout[] = 0;
                $newslot = array_search($id, $questions);
                if ($newslot===false) {
                    // skip deleted questions
                } else {
                    $newlayout[] = ++$newslot;
                    $newlayout[] = 0;
                }
            }
        }

        // convert layouts to comma-separated strings
        $newlayout = implode(',', $newlayout);
        $oldlayout = implode(',', $oldlayout);

        // adjust layout in attempts, if necessary
        if ($newlayout != $oldlayout) {
            $layout = $DB->sql_compare_text('layout'); // Comparisons of text column conditions are not allowed !!
            $params = array($quiz->id, $oldlayout);
            $DB->set_field_select('quiz_attempts', 'layout', $newlayout, "quiz = ? AND $layout = ?", $params);
            $DB->set_field_select('reader_attempts', 'layout', $newlayout, "quizid = ? AND $layout = ?", $params);
        }

        // update quiz->question list, if necessary (Moodle <= 2.6)
        if ($this->quiz_questions) {
            $questions[] = '0';
            $questions = implode(',', $questions); // convert to comma-separated list
            $DB->set_field('quiz', 'questions', $questions, array('id' => $quiz->id));
        }
    }

    /**
     * add_question_postprocessing_multianswer
     *
     * @param xxx $restoreids (passed by reference)
     * @param xxx $module
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_question_postprocessing_multianswer(&$restoreids, $questionid) {
        global $DB;
        list($table, $field) = $this->get_question_options_table('multianswer');
        if ($options = $DB->get_record($table, array($field => $questionid))) {
            $sequence = explode(',', $options->sequence);
            foreach (array_keys($sequence) as $s) {
                $sequence[$s] = $restoreids->get_newid('question', $sequence[$s]);
            }
            $sequence = array_filter($sequence);
            $sequence = implode(',', $sequence);
            $DB->set_field($table, 'sequence', $sequence, array($field => $questionid));
        }
    }

    /**
     * add_question_postprocessing_multichoice
     *
     * @param xxx $restoreids (passed by reference)
     * @param xxx $module
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_question_postprocessing_multichoice(&$restoreids, $questionid) {
        global $DB;
        list($table, $field) = $this->get_question_options_table('multichoice');
        if ($options = $DB->get_record($table, array($field => $questionid))) {
            if (isset($options->answers)) { // Moodle <= 2.5
                $answers = explode(',', $options->answers);
                foreach (array_keys($answers) as $a) {
                    $answers[$a] = $restoreids->get_newid('question_answers', $answers[$a]);
                }
                $answers = array_filter($answers);
                $answers = implode(',', $answers);
                $DB->set_field($table, 'answers', $answers, array($field => $questionid));
            }
        }
    }

    /**
     * add_question_postprocessing_match
     *
     * @param xxx $restoreids (passed by reference)
     * @param xxx $module
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_question_postprocessing_match(&$restoreids, $questionid) {
        // the subquestions have already been set up with new ids
    }

    /**
     * add_question_postprocessing_truefalse
     *
     * @param xxx $restoreids (passed by reference)
     * @param xxx $questionid
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_question_postprocessing_truefalse(&$restoreids, $questionid) {
        global $DB;
        list($table, $field) = $this->get_question_options_table('truefalse');
        if ($options = $DB->get_record($table, array($field => $questionid))) {
            $options->trueanswer = $restoreids->get_newid('question_answers', $options->trueanswer);
            $options->falseanswer = $restoreids->get_newid('question_answers', $options->falseanswer);
            $DB->update_record($table, $options);
        }
    }

    /**
     * add_question_postprocessing_shortanswer
     *
     * @param xxx $restoreids (passed by reference)
     * @param xxx $questionid
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_question_postprocessing_shortanswer(&$restoreids, $questionid) {
        global $DB;
        list($table, $field) = $this->get_question_options_table('shortanswer');
        if ($options = $DB->get_record($table, array($field => $questionid))) {
            if (isset($options->answers)) { // Moodle <= 2.4
                $answers = explode(',', $options->answers);
                foreach ($answers as $a => $answer) {
                    $answers[$a] = $restoreids->get_newid('question_answers', $answer);
                }
                $answers = array_filter($answers);
                $options->answers = implode(',', $answers);
                $DB->update_record($table, $options);
            }
        }
    }
}
