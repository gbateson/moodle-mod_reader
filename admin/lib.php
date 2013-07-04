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
 * mod/reader/admin/lib.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die;

/** Include required files */
require_once($CFG->dirroot.'/mod/reader/lib.php');

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

    /** values for download $type */
    const BOOKS_WITH_QUIZZES    = 1;
    const BOOKS_WITHOUT_QUIZZES = 0;

    /** values for section type */
    const SECTIONTYPE_BOTTOM   = 1;
    const SECTIONTYPE_SORTED   = 2;
    const SECTIONTYPE_SPECIFIC = 3;

    public $remotesites = array();

    public $downloaded = array();

    public $available = array();

    public $course = null;
    public $cm     = null;
    public $reader = null;

    /**
     * __construct
     *
     * @todo Finish documenting this function
     */
    public function __construct($course, $cm, $reader) {
        $this->course = $course;
        $this->cm     = $cm;
        $this->reader = $reader;
    }

    /**
     * get_book_table
     *
     * @param xxx $type
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_book_table($type) {
        switch ($type) {
            case self::BOOKS_WITH_QUIZZES: return 'reader_books';
            case self::BOOKS_WITHOUT_QUIZZES: return 'reader_noquiz';
        }
        return ''; // shouldn't happen !!
    }

    /**
     * get_downloaded_items
     *
     * @uses $DB
     * @param xxx $type
     * @param xxx $r (optional, default=0)
     * @todo Finish documenting this function
     */
    public function get_downloaded_items($type, $r=0) {
        global $DB;

        $this->downloaded[$r] = new reader_items();

        $booktable = $this->get_book_table($type);
        if ($records = $DB->get_records($booktable)) {
            foreach ($records as $record) {

                $publisher = $record->publisher;
                $level     = $record->level;
                $itemname  = $record->name;

                if (! isset($this->downloaded[$r]->items[$publisher])) {
                    $this->downloaded[$r]->items[$publisher] = new reader_items();
                }
                if (! isset($this->downloaded[$r]->items[$publisher]->items[$level])) {
                    $this->downloaded[$r]->items[$publisher]->items[$level] = new reader_items();
                }
                $this->downloaded[$r]->items[$publisher]->items[$level]->items[$itemname] = true;
            }
        }
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
     * @todo Finish documenting this function
     */
    public function add_available_items($type, $itemids) {
        foreach ($this->remotesites as $r => $remotesite) {
            $this->available[$r] = $remotesite->get_available_items($type, $itemids, $this->downloaded[$r]);
        }
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
                        foreach ($items->items as $itemname => $itemid) {
                            if (! in_array($itemid, $selecteditemids)) {
                                $selecteditemids[] = $itemid;
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
     * @uses $DB
     * @uses $OUTPUT
     * @param xxx $type
     * @param xxx $itemids
     * @param xxx $r (optional, default=0)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_selected_itemids($type, $itemids, $r=0) {
        global $DB, $OUTPUT;

        if (empty($itemids)) {
            return false; // nothing to do
        }

        $remotesite = $this->remotesites[$r];
        $xml = $remotesite->download_quizzes($type, $itemids);
        if (empty($xml) || empty($xml['myxml']) || empty($xml['myxml']['#'])) {
            return false; // shouldn't happen !!
        }

        $output = '';
        $started_list = false;
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

            if ($publisher=='' || $name=='' || $itemid=='') { // $level can be empty
                continue;
            }

            $params = array('publisher' => $publisher,
                            'level'     => $level,
                            'name'      => $name);

            $booktable = $this->get_book_table($type);
            if ($book = $DB->get_record($booktable, $params)) {
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
                if ($DB->update_record($booktable, $book)) {
                    $msg = "Book data updated: $book->name";
                } else {
                    $msg = "Book data could NOT be updated: $book->name";
                    $error = 1;
                }
            } else {
                if ($book->id = $DB->insert_record($booktable, $book)) {
                    $msg = "Book data added: $book->name";
                } else {
                    $msg = "Book data could NOT be added: $book->name";
                    $error = 1;
                }
            }

            // download associated image (i.e. book cover)
            if ($error==0) {
                $this->download_image($type, $itemid, $book->image, $r);
                $msg .= html_writer::empty_tag('br')."Image added: $book->image";
            }

            if ($started_list==false) {
                $started_list = true;
                $output .= html_writer::start_tag('div');
                $output .= html_writer::start_tag('ul');
            }
            $output .= html_writer::tag('li', $msg);

            // update available book counters
            if (! isset($this->downloaded[$r]->items[$book->publisher])) {
                $this->downloaded[$r]->items[$publisher] = new reader_items();
            }
            if (! isset($this->downloaded[$r]->items[$book->publisher]->items[$book->level])) {
                $this->downloaded[$r]->items[$book->publisher]->items[$book->level] = new reader_items();
            }
            if (! isset($this->downloaded[$r]->items[$book->publisher]->items[$book->level]->items[$book->name])) {
                $this->downloaded[$r]->items[$book->publisher]->items[$book->level]->items[$book->name] = true;
                $this->available[$r]->items[$book->publisher]->items[$book->level]->newcount--;
                $this->available[$r]->items[$book->publisher]->newcount--;
                $this->available[$r]->newcount--;
            }

            // add quiz if necessary
            if ($error==0 && $type==reader_downloader::BOOKS_WITH_QUIZZES) {
                if ($quiz = $this->add_quiz($item, $book, $r)) {
                    if ($book->quizid==0) {
                        $output .= html_writer::tag('li', 'Quiz was successfully added');
                    } else {
                        $output .= html_writer::tag('li', 'Quiz was successfully updated');
                    }
                    if ($book->id==0 || $book->quizid != $quiz->id) {
                        $book->quizid = $quiz->id;
                        $DB->set_field('reader_books', 'quizid', $book->quizid, array('id' => $book->id));
                    }
                }
            }
        }

        if ($started_list==true) {
            $output .= html_writer::end_tag('ul');
            $output .= html_writer::end_tag('div');
        }

        if ($output) {
            echo $OUTPUT->box($output, 'generalbox', 'notice');
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
     * @param array $item xml data for this download item (= book)
     * @param object $book recently added/updated "reader_books" record
     * @param integer $r (optional, default=0)
     * @todo Finish documenting this function
     */
    function add_quiz($item, $book, $r=0) {

        // get/create course to hold quiz
        $courseid = $this->get_quiz_courseid();

        // get/create section (in $course) to hold quiz
        $sectionnum = $this->get_quiz_sectionnum($courseid, $book);

        // get/create "course_module" record for (new) quiz
        $cm = $this->get_quiz_coursemodule($courseid, $sectionnum, $book->name);

        // add questions to quiz
        $this->add_questions($cm, $item, $r);

        return $quiz;
    }

    /**
     * set_quiz_courseid
     *
     * @uses $DB
     * @param integer $courseid
     * @param boolean $set_config (optional, default=false)
     * @todo Finish documenting this function
     */
    function set_quiz_courseid($courseid, $set_config=false) {
        global $DB;

        if ($this->reader->usecourse==0) {
            $this->reader->usecourse = $courseid;
            $DB->update_record('reader', $this->reader);
        }

        if ($set_config) {
            set_config('usecourse', $courseid, 'reader');
        }
    }

    /**
     * get_quiz_courseid
     *
     * @uses $DB
     * @param integer $numsections (optional, default=1)
     * @return integer $courseid
     * @todo Finish documenting this function
     */
    function get_quiz_courseid($numsections=1) {
        global $DB;

        if ($courseid = $this->reader->usecourse) {
            return $courseid;
        }

        if ($courseid = get_config('reader', 'usecourse')) {
            $this->set_quiz_courseid($courseid);
            return $courseid;
        }

        // get default name for Reader quiz course
        $coursename = get_string('defaultcoursename', 'reader');

        if ($courseid = $DB->get_field('course', 'id', array('fullname' => $coursename, 'shortname' => $coursename))) {
            $this->set_quiz_courseid($courseid, true);
            return $courseid;
        }

        // otherwise we create a new course to hold the quizzes

        // get the first valid $category_id
        $category_list = array();
        $category_parents = array();
        make_categories_list($category_list, $category_parents);
        list($category_id, $category_name) = each($category_list);

        // setup new course
        $course = (object)array(
            'category'      => $category_id, // crucial !!
            'fullname'      => $coursename,
            'shortname'     => $coursename,
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
        $this->set_quiz_courseid($course->id, true);

        // return new course id
        return $course->id;

    }

    /**
     * create_sectionname($book)
     *
     * @param object $book recently added/updated "reader_books" record
     * @todo Finish documenting this function
     */
    function create_sectionname($book) {
        if ($book->level=='' || $book->level=='--' || $book->level=='No Level') {
            return $book->publisher;
        } else {
            return $book->publisher.' - '.$book->level;
        }
    }

    /**
     * get_quiz_sectionnum
     *
     * @uses $DB
     * @param xxx $courseid where Reader quizzes are stored
     * @param xxx $book recently added/modified book
     * @param xxx $sectiontype (optional, default=0)
     * @param xxx $sectionid (optional, default=0)
     * @return xxx
     * @todo Finish documenting this function
     */
    function get_quiz_sectionnum($courseid, $book, $sectiontype=0, $sectionid=0) {
        global $DB;

        $sectionname = $this->create_sectionname($book);

        $sectionnum = 0;
        switch ($sectiontype) {

            case self::SECTIONTYPE_BOTTOM:
                $params = array('course' => $courseid);
                if ($coursesections = $DB->get_records('course_sections', $params, 'section DESC', '*', 0, 1)) {
                    $coursesection = reset($coursesections);
                    if ($coursesection->name == $sectionname) {
                        $sectionnum = $coursesection->section;
                    }
                }
                break;

            case 0:
            case self::SECTIONTYPE_SORTED:
                $select = 'course = ? AND (name = ? OR summary = ?)';
                $params = array($courseid, $sectionname, $sectionname);
                if ($coursesections = $DB->get_records_select('course_sections', $select, $params, 'section', '*', 0, 1)) {
                    $coursesection = reset($coursesections);
                    $sectionnum = $coursesection->section;
                }
                break;

            case self::SECTIONTYPE_SPECIFIC:
            default: // shouldn't happen !!
                $params = array('course' => $courseid, 'section' => $section);
                if ($coursesection = $DB->get_record('course_sections', $params)) {
                    $sectionnum = $coursesection->section;
                }
                break;
        }

        // reuse an empty section, if possible
        if ($sectionnum==0) {
            $select = 'course = ? AND section > ?'.
                      ' AND (name IS NULL OR name = ?)'.
                      ' AND (summary IS NULL OR summary = ?)'.
                      ' AND (sequence IS NULL OR sequence = ?)';
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
    function get_quiz_coursemodule($courseid, $sectionnum, $quizname) {
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
            'name'          => $quizname,
            'intro'         => ' ',
            'visible'       => 1,
            'introformat'   => FORMAT_HTML, // =1
            'timeopen'      => 0,
            'timeclose'     => 0,
            'preferredbehaviour' => 'deferredfeedback',
            'attempts'      => 0,
            'attemptonlast' => 1,
            'grademethod'   => 1,
            'decimalpoints' => 2,
            'questionsperpage' => 0,
            'shufflequestions' => 0,
            'shuffleanswers' => 1,
            'timemodified'  => time(),
            'timelimit'     => 0,
            'subnet'        => '',
            'quizpassword'  => '',
            'delay1'        => 0,
            'delay2'        => 0,
            'questions'     => '0,',

            // feedback fields (for "quiz_feedback" table)
            'feedbacktext'          => array_fill(0, 5, array('text' => '', 'format' => 0)),
            'feedbackboundaries'    => array(0 => 0, -1 => 11),
            'feedbackboundarycount' => 0,

            // these fields may not be necessary in Moodle 2.x
            'sumgrades'     => 0, // reset after adding questions
            'grade'         => 100,
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
        events_trigger('mod_updated', $event);

        return $newquiz;
    }

    /**
     * get_quiz_coursemodule
     *
     * @uses $DB
     * @uses $USER
     * @param xxx $cm
     * @param xxx $item
     * @param xxx $r (optional, default=0)
     * @return xxx
     * @todo Finish documenting this function
     */
    function add_questions($cm, $item, $r=0) {
        global $CFG, $DB, $USER;
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $remotesite = $this->remotesites[$r];
        $xml = $remotesite->download_questions($item['@']['id']);

echo 'STOP in add_questions (admin/lib.php, line 711)';
die;

        // try and use Moodle API to parse this Moodle backup XML
        // see /backup/restore.php
        $restoreid = optional_param('restore', false, PARAM_ALPHANUM);
        // $restoreid = 89416501dfcd6fce4e0ad9276b64c83f;
        if ($rc = restore_ui::load_controller($restoreid)) {
            if ($rc->get_status() == backup::STATUS_REQUIRE_CONV) {
                $rc->convert();
            }
            $restore = new restore_ui($rc, array('contextid'=>$context->id));
        }
    }
}

/**
 * reader_remotesite
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class reader_remotesite {

    /** the default values for this remotesite */
    const DEFAULT_BASEURL = '';
    const DEFAULT_SITENAME = '';
    const DEFAULT_FOLDERNAME = '';

    /** the basic connection parameters */
    public $baseurl = '';
    public $username = '';
    public $password = '';

    /** identifiers for this remotesite */
    public $sitename = '';
    public $foldername = '';

    /**
     * __construct
     *
     * @param xxx $baseurl (optional, default='')
     * @param xxx $username
     * @param xxx $password
     * @param xxx $sitename (optional, default='')
     * @param xxx $foldername (optional, default='')
     * @todo Finish documenting this function
     */
    public function __construct($baseurl='', $username='', $password='', $sitename='', $foldername='') {
        $this->baseurl = ($baseurl ? $baseurl : $this::DEFAULT_BASEURL);
        $this->username = $username;
        $this->password = $password;
        $this->sitename = ($sitename ? $sitename : $this::DEFAULT_SITENAME);
        $this->foldername = ($foldername ? $foldername : $this::DEFAULT_FOLDERNAME);
    }

    /**
     * download_xml
     *
     * @uses $CFG
     * @param xxx $url
     * @param xxx $post (optional, default=null)
     * @param xxx $headers (optional, default=null)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function download_xml($url, $post=null, $headers=null) {
        if ($xml = download_file_content($url, $headers, $post)) {
            return xmlize($xml);
        }
        return false; // shouldn't happen !!
    }

    /**
     * download_publishers
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */
    public function download_publishers($type, $itemids) {
        $url = $this->get_publishers_url($type, $itemids);
        $post = $this->get_publishers_post($type, $itemids);
        return $this->download_xml($url, $post);
    }

    /**
     * get_publishers_url
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_publishers_url($type, $itemids) {
        return $this->baseurl;
    }

    /**
     * get_publishers_params
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_publishers_params($type, $itemids) {
        return null;
    }

    /**
     * get_publishers_post
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_publishers_post($type, $itemids) {
        return null;
    }

    /**
     * download_quizzes
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */
    public function download_quizzes($type, $itemids) {
        $url = $this->get_quizzes_url($type, $itemids);
        $post = $this->get_quizzes_post($type, $itemids);
        return $this->download_xml($url, $post);
    }

    /**
     * get_quizzes_url
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_quizzes_url($type, $itemids) {
        return $this->baseurl;
    }

    /**
     * get_quizzes_params
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_quizzes_params($type, $itemids) {
        return null;
    }

    /**
     * get_quizzes_post
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_quizzes_post($type, $itemids) {
        return null;
    }

    /**
     * download_questions
     *
     * @param xxx $itemid
     * @return xxx
     * @todo Finish documenting this function
     */
    public function download_questions($itemid) {
        $url = $this->get_questions_url($itemid);
        $post = $this->get_questions_post($itemid);
        return $this->download_xml($url, $post);
    }

    /**
     * get_questions_url
     *
     * @param xxx $itemid
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_questions_url($itemid) {
        return $this->baseurl;
    }

    /**
     * get_questions_params
     *
     * @param xxx $itemid
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_questions_params($itemid) {
        return null;
    }

    /**
     * get_questions_post
     *
     * @param xxx $itemid
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_questions_post($itemid) {
        return null;
    }

    /**
     * get_image_url
     *
     * @param xxx $type
     * @param xxx $itemid
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_image_url($type, $itemid) {
        return $this->baseurl;
    }

    /**
     * get_image_params
     *
     * @param xxx $type
     * @param xxx $itemid
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_image_params($type, $itemid) {
        return null;
    }

    /**
     * get_image_post
     *
     * @param xxx $type
     * @param xxx $itemid
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_image_post($type, $itemid) {
        return null;
    }
}

/**
 * reader_remotesite_moodlereadernet
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class reader_remotesite_moodlereadernet extends reader_remotesite {

    const DEFAULT_BASEURL = 'http://moodlereader.net/quizbank';
    const DEFAULT_SITENAME = 'MoodleReader.net Quiz Bank';
    const DEFAULT_FOLDERNAME = 'moodlereader.net';

    /**
     * get_publishers_url
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_publishers_url($type, $itemids) {
        switch ($type) {
            case reader_downloader::BOOKS_WITH_QUIZZES: $filepath = '/index.php'; break;
            case reader_downloader::BOOKS_WITHOUT_QUIZZES: $filepath = '/index-noq.php'; break;
            default: $filepath = ''; // shouldn't happen !!
        }
        $params = $this->get_publishers_params($type, $itemids);
        return new moodle_url($this->baseurl.$filepath, $params);
    }

    /**
     * get_publishers_params
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_publishers_params($type, $itemids) {
        return array('a' => 'publishers', 'login' => $this->username, 'password' => $this->password);
    }

    /**
     * get_quizzes_url
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_quizzes_url($type, $itemids) {
        switch ($type) {
            case reader_downloader::BOOKS_WITH_QUIZZES: $filepath = '/index.php'; break;
            case reader_downloader::BOOKS_WITHOUT_QUIZZES: $filepath = '/index-noq.php'; break;
            default: $filepath = '';
        }
        $params = $this->get_quizzes_params($type, $itemids);
        return new moodle_url($this->baseurl.$filepath, $params);
    }

    /**
     * get_quizzes_params
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_quizzes_params($type, $itemids) {
        return array('a' => 'quizzes', 'login' => $this->username, 'password' => $this->password);
    }

    /**
     * get_quizzes_post
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_quizzes_post($type, $itemids) {
        return array('quiz' => $itemids, 'password' => '', 'upload' => 'true');
    }

    /**
     * get_questions_url
     *
     * @param xxx $itemid
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_questions_url($itemid) {
        $params = $this->get_questions_params($itemid);
        return new moodle_url($this->baseurl.'/getfile.php', $params);
    }

    /**
     * get_questions_params
     *
     * @param xxx $itemid
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_questions_params($itemid) {
        return array('getid' => $itemid, 'pass' => '');
    }

    /**
     * get_image_url
     *
     * @param xxx $type
     * @param xxx $itemid
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_image_url($type, $itemid) {
        switch ($type) {
            case reader_downloader::BOOKS_WITH_QUIZZES: $filepath = '/getfile.php'; break;
            case reader_downloader::BOOKS_WITHOUT_QUIZZES: $filepath = '/getfilenoq.php'; break;
            default: $filename = ''; // shouldn't happen !!
        }
        $params = $this->get_image_params($type, $itemid);
        return new moodle_url($this->baseurl.$filepath, $params);
    }

    /**
     * get_image_params
     *
     * @param xxx $type
     * @param xxx $itemid
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_image_params($type, $itemid) {
        return array('imageid' => $itemid);
    }

    /**
     * get_available_items
     *
     * @param xxx $type
     * @param xxx $itemids
     * @param xxx $downloaded
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_available_items($type, $itemids, $downloaded) {
        $items = $this->download_publishers($type, $itemids);

        $available = new reader_download_items();
        foreach ($items['myxml']['#']['item'] as $item) {

            $publisher = $item['@']['publisher'];
            $needpass  = $item['@']['needpass'];
            $level     = $item['@']['level'];
            $itemid    = $item['@']['id'];
            $itemname  = $item['#'];

            if ($publisher=='Extra_Points' || $publisher=='testing' || $publisher=='_testing_only') {
                continue; // ignore these publisher categories
            }

            if (! isset($available->items[$publisher])) {
                $available->items[$publisher] = new reader_download_items();
            }
            if (! isset($available->items[$publisher]->items[$level])) {
                $available->items[$publisher]->items[$level] = new reader_download_items();
            }

            if ($needpass=='true') {
                $available->items[$publisher]->items[$level]->needpassword = true;
            }

            $available->count++;
            $available->items[$publisher]->count++;
            $available->items[$publisher]->items[$level]->count++;

            if (empty($downloaded->items[$publisher]->items[$level]->items[$itemname])) {
                $available->newcount++;
                $available->items[$publisher]->newcount++;
                $available->items[$publisher]->items[$level]->newcount++;
            }

            $available->items[$publisher]->items[$level]->items[$itemname] = $itemid;
        }

        // sort items by name
        ksort($available->items);
        $publishers = array_keys($available->items);
        $sort_by_name = array($this, 'sort_by_name');
        foreach ($publishers as $publisher) {
            uksort($available->items[$publisher]->items, $sort_by_name);
            $levels = array_keys($available->items[$publisher]->items);
            foreach ($levels as $level) {
                ksort($available->items[$publisher]->items[$level]->items);
            }
        }

        return $available;
    }

    /**
     * sort_by_name
     *
     * @param xxx $a
     * @param xxx $b
     * @return xxx
     * @todo Finish documenting this function
     */
    function sort_by_name($a, $b) {

        // search and replace strings
        $search1 = array('/^-+$/', '/\bLadder\s+([0-9]+)$/', '/\bLevel\s+([0-9]+)$/', '/\bStage\s+([0-9]+)$/', '/^Extra_Points|testing|_testing_only$/', '/Booksworms/');
        $replace1 = array('', '100$1', '200$1', '300$1', 9999, 'Bookworms');

        $search2 = '/\b(Pre|Low|Upper|High)?[ -]*(EasyStarts?|Quick Start|Starter|Beginner|Beginning|Elementary|Intermediate|Advanced)$/';
        $replace2 = array($this, 'replace_name_with_number');

        $split = '/^(.*?)([0-9]+)$/';

        // get filtered name (a)
        $aname = preg_replace_callback($search2, $replace2, preg_replace($search1, $replace1, $a));
        if (preg_match($split, $aname, $matches)) {
            $aname = trim($matches[1]);
            $anum = intval($matches[2]);
        } else {
            $anum = 0;
        }

        // get filtered name (b)
        $bname = preg_replace_callback($search2, $replace2, preg_replace($search1, $replace1, $b));
        if (preg_match($split, $bname, $matches)) {
            $bname = trim($matches[1]);
            $bnum = intval($matches[2]);
        } else {
            $bnum = 0;
        }

        // deal with empty names always go last
        if ($aname || $bname) {
            if ($aname=='') {
                return -1;
            }
            if ($bname=='') {
                return 1;
            }
            if ($aname < $bname) {
                return -1;
            }
            if ($aname > $bname) {
                return 1;
            }
        }

        // compare level/stage/word numbers
        if ($anum < $bnum) {
            return -1;
        }
        if ($anum > $bnum) {
            return 1;
        }

        // same name && same level/stage/word number
        return 0;
    }

    /**
     * replace_name_with_number
     *
     * @param xxx $matches
     * @return xxx
     * @todo Finish documenting this function
     */
    function replace_name_with_number($matches) {
        $num = 0;
        switch ($matches[1]) {
            case 'Pre':   $num += 10; break;
            case 'Low':   $num += 20; break;
            case 'Upper': $num += 30; break;
            case 'High':  $num += 40; break;
        }
        switch ($matches[2]) {
            case 'Quick Start':  break; // 0
            case 'EasyStart':
            case 'EasyStarts':
            case 'Starter':      $num += 100; break;
            case 'Beginner':
            case 'Beginning':    $num += 200; break;
            case 'Elementary':   $num += 300; break;
            case 'Intermediate': $num += 400; break;
            case 'Advanced':     $num += 500; break;
        }
        return $num;
    }
}

/**
 * reader_items
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class reader_items {
    public $count = 0;
    public $items = array();
}

/**
 * reader_download_items
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class reader_download_items extends reader_items {
    public $newcount = 0;
    public $needpassword = false;
}
