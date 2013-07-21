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
    public $output = null;

    /** download progress bar */
    public $bar = null;

    /**
     * __construct
     *
     * @param xxx $course
     * @param xxx $cm
     * @param xxx $reader
     * @param xxx $output renderer
     * @todo Finish documenting this function
     */
    public function __construct($course, $cm, $reader, $output) {
        $this->course = $course;
        $this->cm     = $cm;
        $this->reader = $reader;
        $this->output = $output;
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
        global $CFG, $DB;

        $this->downloaded[$r] = new reader_items();

        $booktable = $this->get_book_table($type);
        if ($records = $DB->get_records($booktable, null, 'publisher,level,name')) {

            foreach ($records as $record) {

                $publisher = $record->publisher;
                $level     = $record->level;
                $itemname  = $record->name;
                $time      = $record->time;

                // ensure the $downloaded array has the required structure
                if (! isset($this->downloaded[$r]->items[$publisher])) {
                    $this->downloaded[$r]->items[$publisher] = new reader_items();
                }
                if (! isset($this->downloaded[$r]->items[$publisher]->items[$level])) {
                    $this->downloaded[$r]->items[$publisher]->items[$level] = new reader_items();
                }

                if ($time==0) {
                    // get $time this book was last updated by checking
                    // the "last modified" time on the book's image file
                    $time = $this->get_imagefile_time($record->image);
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
        foreach (array_keys($this->remotesites) as $r) {
            if ($this->available[$r]->count) {
                return true;
            }
        }
        return false;
    }

    /**
     * has_updated_items
     *
     * @return boolean
     * @todo Finish documenting this function
     */
    public function has_updated_items() {
        foreach (array_keys($this->remotesites) as $r) {
            if ($this->available[$r]->updatecount) {
                return true;
            }
        }
        return false;
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
                            if (! in_array($item->id, $selecteditemids)) {
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
     * @uses $DB
     * @param xxx $type
     * @param xxx $itemids
     * @param xxx $r (optional, default=0)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_selected_itemids($type, $itemids, $r=0) {
        global $DB;

        if (empty($itemids)) {
            return false; // nothing to do
        }

        $remotesite = $this->remotesites[$r];
        $xml = $remotesite->download_quizzes($type, $itemids);
        if (empty($xml) || empty($xml['myxml']) || empty($xml['myxml']['#'])) {
            return false; // shouldn't happen !!
        }

        $this->bar = reader_download_progress_bar::create($itemids, 'readerdownload');

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

            $titletext = $publisher;
            if ($level=='' || $level=='--' || $level=='No Level') {
                // do nothing
            } else {
                $titletext .= " ($level)";
            }
            $titlehtml = html_writer::tag('span', $titletext, array('style' => 'font-weight: normal')).
                         html_writer::empty_tag('br').
                         html_writer::tag('span', $name, array('style' => 'white-space: nowrap'));
            $titletext .= " $name";

            // show this book in the progress bar
            $title = ($i + 1).' / '.$i_max.' '.$titlehtml;
            $this->bar->start_item($itemid, $title);

            // set $params to select $book
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
                if ($DB->update_record($booktable, $book)) {
                    $msg = get_string('bookupdated', 'reader', $titletext);
                } else {
                    $msg = get_string('booknotupdated', 'reader', $titletext);
                    $error = 1;
                }
            } else {
                $book->quizid = 0;
                if ($book->id = $DB->insert_record($booktable, $book)) {
                    $msg = get_string('bookadded', 'reader', $titletext);
                } else {
                    $msg = get_string('booknotadded', 'reader', $titletext);
                    $error = 1;
                }
            }

            // download associated image (i.e. book cover)
            if ($error==0) {
                $this->download_image($type, $itemid, $book->image, $r);
                $msg .= html_writer::empty_tag('br').get_string('imageadded', 'reader', $book->image);
            }

            if ($started_list==false) {
                $started_list = true;
                echo $this->output->box_start('generalbox', 'notice');
                echo html_writer::start_tag('div');
                echo $this->output->showhide_js_start();
                echo html_writer::tag('b', get_string('downloadedbooks', 'reader'));
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
                $this->available[$r]->items[$book->publisher]->items[$book->level]->newcount--;
                $this->available[$r]->items[$book->publisher]->newcount--;
                $this->available[$r]->newcount--;
            } else if ($this->downloaded[$r]->items[$book->publisher]->items[$book->level]->items[$book->name]->time < $itemtime) {
                // an updated item
                $this->available[$r]->items[$book->publisher]->items[$book->level]->updatecount--;
                $this->available[$r]->items[$book->publisher]->updatecount--;
                $this->available[$r]->updatecount--;
            }

            // flag this item as "downloaded" and set update $time
            $this->downloaded[$r]->items[$book->publisher]->items[$book->level]->items[$book->name] = new reader_download_item($itemid, $time);

            // add quiz if necessary
            if ($error==0 && $type==reader_downloader::BOOKS_WITH_QUIZZES) {
                if ($quiz = $this->add_quiz($item, $book, $r)) {
                    if ($DB->record_exists('quiz_question_instances', array('quiz' => $quiz->id))) {
                        $link = new moodle_url('/mod/quiz/view.php', array('q' => $quiz->id));
                        $link = html_writer::link($link, $strquiz, array('onclick' => 'this.target="_blank"'));

                        list($cheatsheeturl, $strcheatsheet) = reader_cheatsheet_init('takequiz');
                        if ($cheatsheeturl) {
                            $link .= ' '.reader_cheatsheet_link($cheatsheeturl, $strcheatsheet, $titletext, $book);
                        }

                        if ($book->quizid==0) {
                            $msg .= html_writer::empty_tag('br').get_string('quizadded', 'reader', $link);
                        } else {
                            $msg .= html_writer::empty_tag('br').get_string('quizupdated', 'reader', $link);
                        }

                        if ($book->id==0 || $book->quizid != $quiz->id) {
                            $book->quizid = $quiz->id;
                            $DB->set_field('reader_books', 'quizid', $book->quizid, array('id' => $book->id));
                        }
                    } else {
                        // delete quiz
                        $msg .= html_writer::empty_tag('br');
                        $msg .= html_writer::tag('span', get_string('error').': ', array('class' => 'notifyproblem'));
                        $msg .= get_string('quizhasnoquestions', 'reader');
                        $this->remove_coursemodule($quiz->id, 'quiz');

                        // remove $book from "reader_books" table
                        $DB->delete_records('reader_books', array('id' => $book->id));

                        // add $book to "reader_noquiz" table
                        $params = array('publisher' => $book->publisher,
                                        'level'     => $book->level,
                                        'name'      => $book->name);
                        if ($book->id = $DB->get_field('reader_noquiz', 'id', $params)) {
                            $DB->update_record('reader_noquiz', $book);
                        } else {
                            unset($book->id);
                            $book->id = $DB->insert_record('reader_noquiz', $book);
                        }
                        $msg .= html_writer::empty_tag('br').'Book moved to "books without quizzes" list';

                        // remove from list of downloaded books and available counters
                        unset($this->downloaded[$r]->items[$book->publisher]->items[$book->level]->items[$book->name]);
                        $this->available[$r]->items[$book->publisher]->items[$book->level]->newcount++;
                        $this->available[$r]->items[$book->publisher]->newcount++;
                        $this->available[$r]->newcount++;
                    }
                }
            }
            echo html_writer::tag('li', $msg);

            // move the progress bar
            $this->bar->finish_item();

            // reclaim a bit of memory
            unset($xml['myxml']['#']['item']);
        }

        // finish the progress bar
        $duration = microtime_diff($starttime, microtime());
        $title = ($i + 1)." / $i_max ".get_string('success').' ('.format_time(round($duration)).')';
        $this->bar->finish($title);

        if ($started_list==true) {
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
     * @param array $item xml data for this download item (= book)
     * @param object $book recently added/updated "reader_books" record
     * @param integer $r (optional, default=0)
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
     * @param boolean $set_config (optional, default=false)
     * @todo Finish documenting this function
     */
    public function set_quiz_courseid($courseid, $set_config=false) {
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
    public function get_quiz_courseid($numsections=1) {
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
    public function create_sectionname($book) {
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
    public function get_quiz_sectionnum($courseid, $book, $sectiontype=0, $sectionid=0) {
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
        events_trigger('mod_updated', $event);

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
    function remove_coursemodule($cmid_or_instanceid, $modname='') {
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

        add_to_log($cm->course, 'course', 'delete mod', "view.php?id=$cm->course", "$cm->modname $cm->instance", $cm->id);

        // Note: course cache was rebuilt in "delete_mod_from_section()"
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
            $this->bar->add_quiz($categories, $module->question_instances);
        }

        // create module - usually this is not necessary !!
        //if (empty($module)) {
        //    $this->create_question_module($module, $quiz);
        //}

        // create question instances - usually this is not necessary !!
        //if (empty($module->question_instances)) {
        //    $this->create_question_instances($module, $categories);
        //}

        // prune questions to leave only main questions or sub questions
        // e.g. questions used by random or multianswer questions
        $this->prune_question_categories($module, $categories);

        // we need to track old and new question ids
        $restoreids = new reader_restore_ids();

        foreach ($categories as $category) {
            $this->bar->start_category($category->id);
            $this->add_question_category($restoreids, $category, $quiz, $cm);
            $this->bar->finish_category();
        }

        if (isset($module->question_instances)) {
            foreach ($module->question_instances as $instance) {
                $this->bar->start_instance($instance->id);
                $this->add_question_instance($restoreids, $instance, $quiz);
                $this->bar->finish_instance();
            }
        }

        // convert old ids to new ids and make other adjustments
        $this->add_question_postprocessing($restoreids, $module, $quiz);
    }

    /**
     * create_question_instances
     *
     * @param xxx $module (passed by reference)
     * @param xxx $quiz
     * @return xxx
     * @todo Finish documenting this function
     */
    public function create_question_module(&$module, $quiz) {
        $module = (object)array(
            'id' => 9876543210,  // enough to be unique for the time it takes to add this question
            'question_instances' => array(),
            // the fields below are probably not necessary
            'modtype'       => 'quiz',
            'name'          => $quiz->name,
            'intro'         => '',
            'timeopen'      => 0,
            'timeclose'     => 0,
            'optionflags'   => 1,
            'penaltyscheme' => 1,
            'attempts_number' => 0,
            'attemptonlast' => 0,
            'grademethod'   => 1,
            'decimalpoints' => 2,
            'review'        => 4652015,
            'questions'     => '',
            'questionsperpage' => 0,
            'shufflequestions' => 0,
            'shuffleanswers' => 1,
            'sumgrades'     => 0,
            'grade'         => 100,
            'timecreated'   => 0,
            'timemodified'  => 0,
            'timelimit'     => 0,
            'password'      => '',
            'subnet'        => '',
            'popup'         => 0,
            'delay1'        => 0,
            'delay2'        => 0,
            'feedbacks'     => array(),
        );
    }

    /**
     * create_question_instances
     *
     * @param xxx $module (passed by reference)
     * @param xxx $categories (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function create_question_instances(&$module, &$categories) {

        if (empty($module)) {
            return; // shouldn't happen !!
        }

        if (empty($module->question_instances)) {
            $module->question_instances = array();
        }

        if (count($module->question_instances)) {
            return; // instances already exist
        }

        if (empty($categories)) {
            return; // no questions to add :-(
        }

        $instanceid = 1;
        foreach ($categories as $category) {
            $has_random = $this->has_random_questions($category);
            foreach ($category->questions as $question) {
                if ($has_random==false || $question->qtype=='random') {
                    $module->question_instances[] = (object)array(
                        'id' => $instanceid++,
                        'question' => $question->id,
                        'grade' => 1,
                    );
                }
            }
        }
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
        $ids = array();

        // get main questions used in this quiz
        if (isset($module->question_instances)) {
            foreach ($module->question_instances as $instance) {
                $ids[$instance->question] = array($instance->question);
            }
        }

        // get sub-questions used in this quiz
        foreach ($categories as $categoryid => $category) {

            // fix category name, if necessary
            if ($category->name=='ordering' || $category->name=='ORDERING') {
                $categories[$categoryid]->name = 'Ordering';
            }

            // fix category context level, if necessary
            if ($category->context->level=='course' && ! $this->is_default_category($category)) {
                $categories[$categoryid]->context->level = 'module';
            }


            if (isset($category->questions)) {
                $has_nonrandom = $this->has_nonrandom_questions($category);
                foreach ($category->questions as $questionid => $question) {
                    if (isset($ids[$question->id]) && $question->qtype=='random') {
                        if ($has_nonrandom) {
                            // for random questions, we keep the whole category
                            $ids[$question->id] = array_keys($category->questions);
                        } else {
                            // this question is a "random" question in a
                            // category that contains ONLY "random" questions
                            // Therefore, there is no point in keeping this question
                            unset($ids[$question->id]);
                        }
                    } else if (isset($ids[$question->parent])) {
                        // otherwise we add this question to the list of child questions for this parent
                        $ids[$question->parent][] = $questionid;
                    }
                }
            }
        }

        // flatten array of required question ids
        $keepids = array();
        foreach (array_keys($ids) as $id) {
            $keepids = array_merge($keepids, $ids[$id]);
        }
        $keepids = array_flip($keepids);

        foreach ($categories as $categoryid => $category) {
            // delete unused questions
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
            $category->info = get_string('defaultquestioncategoryinfo', 'reader', $a);
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
            $this->bar->start_question($question->id);
            $this->add_question($bestquestionids, $restoreids, $categoryid, $question);
            $this->bar->finish_question();
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
        if ($record = $DB->get_record('question_categories', $params)) {
            if ($record->info != $category->info) {
                $record->info = $category->info;
                $DB->update_record('question_categories', $record);
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

        // set category id
        $question->category = $categoryid;

        // set defaultmark (Moodle 2.x) from defaultgrade (Moodle 1.x)
        if ($question->defaultgrade) {
            $question->defaultmark = $question->defaultgrade;
        }

        // set questiontext on ordering questions, if necessary
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
            'logical' => $question->ordering->logical,
            'studentsee' => $question->ordering->studentsee,
            'correctfeedback' => $question->ordering->correctfeedback,
            'incorrectfeedback' => $question->ordering->incorrectfeedback,
            'partiallycorrectfeedback' => $question->ordering->partiallycorrectfeedback
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

        if ($table=='' || $field=='') {
            throw new moodle_exception(get_string('cannotinsertrecord', 'error', "question_$type options"));
        }

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

        // update progress bar
        $this->bar->finish_options();
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
        if (isset($mins[$length])) {
            return $mins[$length];
        }

        // set minimum required $levenshtein difference
        // we can then ignore any strings that differ
        // by greater than $min levenshtein
        // $length => $min levenshtein
        //     3   =>   2 (  3 = 3 * 2 / 2)
        //     6   =>   3 (  6 = 4 * 3 / 2)
        //    10   =>   4 ( 10 = 5 * 4 / 2)
        //    15   =>   5 ( 15 = 6 * 5 / 2)
        //    21   =>   6 ( 21 = 7 * 6 / 2)
        //    28   =>   7 ( 28 = 8 * 7 / 2)
        $min = 2;
        while ((($min + 1) * $min / 2) < $length) {
            $min ++;
        }

        // cache and return the $min value
        $mins[$length] = $min;
        return $mins[$length];
    }

    public function add_question_answer(&$restoreids, $bestanswerids, $xmlanswer, $answer) {
        global $DB;
        $this->bar->start_answer($xmlanswer->id);
        if (isset($bestanswerids[$xmlanswer->id])) {
            $answer->id = $bestanswerids[$xmlanswer->id];
            if (! $DB->update_record('question_answers', $answer)) {
                throw new moodle_exception(get_string('cannotupdaterecord', 'error', 'question_answers (id='.$answer->id.')'));
            }
        } else {
            if (! $answer->id = $DB->insert_record('question_answers', $answer)) {
                throw new moodle_exception(get_string('cannotinsertrecord', 'error', 'question_answers'));
            }
        }
        $restoreids->set_ids('question_answers', $xmlanswer->id, $answer->id);
        $this->bar->finish_answer();
    }

    /**
     * add_question_instance
     *
     * @uses $DB
     * @param xxx $restoreids (passed by reference)
     * @param xxx $category
     * @param xxx $quiz
     * @param xxx $cm
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_question_instance(&$restoreids, $instance, $quiz) {
        global $DB;

        if (! $questionid = $restoreids->get_newid('question', $instance->question)) {
            return false; // question was not added for some reason
        }

        // set up quiz/reader instance record
        $instance = (object)array(
            'quiz'     => $quiz->id,
            'question' => $questionid,
            'grade'    => $instance->grade,
        );

        // define search $params for old (=existing) instance record
        $params = array('quiz' => $instance->quiz, 'question' => $instance->question);

        // add quiz question instance record, if necessary
        if (! $DB->record_exists('quiz_question_instances', $params)) {
            if (! $DB->insert_record('quiz_question_instances', $instance)) {
                throw new moodle_exception(get_string('cannotinsertrecord', 'error', 'quiz_question_instances'));
            }
        }

        // add reader question instance record, if necessary
        if (! $DB->record_exists('reader_question_instances', $params)) {
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

        // $quiz->questions
        if (isset($module->questions)) {
            $questions = explode(',', $module->questions);
            foreach (array_keys($questions) as $q) {
                $questions[$q] = $restoreids->get_newid('question', $questions[$q]);
            }
        } else {
            $questions = array(); // shouldn't happen !!
        }

        $questions = array_filter($questions); // remove blanks
        $questions = implode(',', $questions); // convert to string
        $DB->set_field('quiz', 'questions', $questions, array('id' => $quiz->id));

        // $quiz->sumgrades
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
            $answers = explode(',', $options->answers);
            foreach (array_keys($answers) as $a) {
                $answers[$a] = $restoreids->get_newid('question_answers', $answers[$a]);
            }
            $answers = array_filter($answers);
            $answers = implode(',', $answers);
            $DB->set_field($table, 'answers', $answers, array($field => $questionid));
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
    const DEFAULT_FILESFOLDER = '';

    /** recognized types of web server */
    const SERVER_APACHE = 1;
    const SERVER_IIS    = 2;
    const SERVER_NGINX  = 3;

    /** the basic connection parameters */
    public $baseurl = '';
    public $username = '';
    public $password = '';

    /** identifiers for this remotesite */
    public $sitename = '';
    public $foldername = '';

    /** path (below $baseurl) to "files" folder on remote server */
    public $filesfolder = '';

    /** cache of filetimes */
    public $filetimes = null;

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
    public function __construct($baseurl='', $username='', $password='', $sitename='', $foldername='', $filesfolder='') {
        $this->baseurl = ($baseurl ? $baseurl : $this::DEFAULT_BASEURL);
        $this->username = $username;
        $this->password = $password;
        $this->sitename = ($sitename ? $sitename : $this::DEFAULT_SITENAME);
        $this->foldername = ($foldername ? $foldername : $this::DEFAULT_FOLDERNAME);
        $this->filesfolder = ($filesfolder ? $filesfolder : $this::DEFAULT_FILESFOLDER);
    }

    /**
     * remote_filetime
     *
     * @param xxx $publisher
     * @param xxx $level
     * @param xxx $name
     * @param xxx $time
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_remote_filetime($publisher, $level, $name, $time) {
        static $namechars = array('"' => '', "'" => '', '&' => '', ' ' => '_');
        //return mt_rand(0,1);

        // get the last modified dates for the "publisher" folders
        if ($this->filetimes===null) {
            $this->filetimes = $this->get_remote_filetimes();
        }

        // if the "publisher" folder hasn't changed, return the publisher update time
        if (isset($this->filetimes[$publisher]) && $this->filetimes[$publisher] < $time) {
            return $this->filetimes[$publisher];
        }

        // get the last modified dates for the "level" folders for this publisher
        $leveldir = "$publisher/$level";
        if (empty($this->filetimes[$leveldir])) {
            $filepath = '/'.rawurlencode($publisher).'/';
            $this->filetimes += $this->get_remote_filetimes($filepath);
        }

        // if the "level" folder hasn't changed, return the level update time
        if (isset($this->filetimes[$leveldir]) && $this->filetimes[$leveldir] < $time) {
            return $this->filetimes[$leveldir];
        }

        // define path to xml file
        $xmlfile = strtr("$name.xml", $namechars);
        $xmlfile = "$publisher/$level/$xmlfile";

        // get the last modified dates for the files for this "level" and "publisher"
        if (empty($this->filetimes[$xmlfile])) {
            $filepath = '/'.rawurlencode($publisher).'/'.rawurlencode($level).'/';
            $this->filetimes += $this->get_remote_filetimes($filepath);
        }

        // return the $xmlfile update time
        if (isset($this->filetimes[$xmlfile])) {
            return $this->filetimes[$xmlfile];
        } else {
            return 0; // xml file not found - shouldn't happen !!
        }
    }

    /**
     * clear_filetimes
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function clear_filetimes() {
        $this->filetimes = null;
    }

    /**
     * get_remote_filetimes
     *
     * @param xxx $path (optional, default="")
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_remote_filetimes($path='') {
        static $server = null;
        static $search = null;

        $filetimes = array();

        $url = new moodle_url($this->baseurl.$this->filesfolder.$path);
        $response = download_file_content($url, null, null, true);

        if ($server===null) {
            list($server, $search) = $this->get_server_search($response->headers);
            if ($server=='' || $search=='') {
                throw new moodle_exception('Could not contact remote server');
            }
        }

        $dir = ltrim(urldecode($path), '/');

        if (preg_match_all($search, $response->results, $matches)) {

            $i_max = count($matches[0]);
            for ($i=0; $i<$i_max; $i++) {

                switch ($server) {

                    case self::SERVER_APACHE:
                        $file = trim($matches[1][$i], ' /');
                        $time = trim($matches[2][$i]);
                        $size = trim($matches[3][$i]);
                        $datetime = strtotime($time);
                        break;

                    case self::SERVER_IIS:
                        $date = trim($matches[1][$i]);
                        $time = trim($matches[2][$i]);
                        $size = trim($matches[3][$i]);
                        $file = trim($matches[4][$i]);
                        $datetime = strtotime("$date $time");
                        break;

                    case self::SERVER_NGINX:
                        $file = trim($matches[1][$i]);
                        $date = trim($matches[2][$i]);
                        $time = trim($matches[3][$i]);
                        $size = trim($matches[4][$i]);
                        $datetime = strtotime("$date $time");
                        break;
                }

                if ($file=='Parent Directory') {
                    continue; // Apache
                }

                $filetimes[$dir.$file] = $datetime;
            }
        }

        return $filetimes;
    }

    /**
     * get_server_search
     *
     * return server type and search string to extract
     * file names and times from an index listing page
     *
     * @param array $headers from a CURL request
     * @return array($server, $search)
     */
    public function get_server_search($headers) {
        $server = '';
        $search = '';
        foreach ($headers as $header) {

            if ($pos = strpos($header, ':')) {
                $name = trim(substr($header, 0, $pos));
                $value = trim(substr($header, $pos+1));

                if ($name=='Server') {
                    switch (true) {

                        case (substr($value, 0, 6)=='Apache'):
                            $server = self::SERVER_APACHE;
                            $search = '/<td[^>]*><a href="[^"]*">(.*?)<\/a><\/td><td[^>]*>(.*?)<\/td><td[^>]*>(.*?)<\/td>/i';
                            break;

                        case (substr($value, 0, 13)=='Microsoft-IIS'):
                            $server = self::SERVER_IIS;
                            $search = '/ +([^ ]+) +([^ ]+) +([^ ]+) +<a href="[^"]*">(.*?)<\/a>/i';
                            break;

                        case (substr($value, 0, 5)=='nginx'):
                            $server = self::SERVER_NGINX;
                            $search = '/<a href="[^"]*">(.*?)<\/a> +([^ ]+) +([^ ]+) +([^ ]+)/i';
                            break;

                        default;
                            throw new moodle_exception('Unknown server type: '. $value);
                    }
                }
            }
        }
        return array($server, $search);
    }

    /**
     * is_update_available_old
     * this function is not used
     *
     * @param xxx $filepath (optional, default="")
     * @return xxx
     * @todo Finish documenting this function
     */
    public function is_update_available_old($filepath='') {

        // define list of locations to check ($filepath => $is_folder)
        $filepaths = array("/$folder1/" => true, "/$folder1/$folder2/" => true, "/$folder1/$folder2/$xmlfile" => false);
        foreach ($filepaths as $filepath => $is_folder) {

            if ($is_folder && isset($filetimes[$filepath])) {
                $filetime = $filetimes[$filepath];
            } else {
                $filetime = $this->get_remote_filetime_old($filepath);
                if ($is_folder) {
                    $filetimes[$filepath] = $filetime;
                }
            }
            if ($filetime && $filetime < $time) {
                return false;
            }
        }

        return true; // all paths were more recent than $time i.e. update is available
    }

    /**
     * get_remote_filetime_old
     * this function is not used - but it works
     *
     * @param xxx $filepath
     * @todo Finish documenting this function
     */
    public function get_remote_filetime_old($filepath) {
        // construct url
        $url = new moodle_url($this->baseurl.$this->filesfolder.$filepath);

        // get remote file "last modified" date, thanks to following post:
        // http://stackoverflow.com/questions/1378915/header-only-retrieval-in-php-via-curl
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_FILETIME, true);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_exec($curl);
        $filetime = curl_getinfo($curl, CURLINFO_FILETIME);
        curl_close($curl);

        if ($filetime < 0) {
            $filetime = 0;
        }

        return $filetime;
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
        global $OUTPUT;

        // get "full response" from CURL so that we can handle errors
        $response = download_file_content($url, $headers, $post, true);

        if (empty($response->results)) {
            if ($response->error) {
                $output = '';
                $output .= html_writer::tag('h3', get_string('cannotdownloadata', 'reader'));
                $output .= html_writer::tag('p', "URL: $url");
                $output .= html_writer::tag('p', get_string('curlerror', 'reader', $response->error));
                $output = $OUTPUT->notification($output);
                echo $OUTPUT->box($output, 'generalbox', 'notice');
            }
            return false;
        }

        // make sure all lone ampersands are encoded as HTML entities
        // otherwise the XML parser will fail
        // e.g. Penguin - Level 2: Marley & Me (book data with quiz)
        // e.g. Macmillan - Beginner: The Last Leaf & Other Stories (without quiz)
        $search = '/&(?!(?:gt|lt|amp|quot|[a-z0-9]+|(?:#?[0-9]+)|(?:#x[a-f0-9]+));)/i';
        $response->results = preg_replace($search, '&amp;', $response->results);

        // return "xmlized" results
        return xmlize($response->results);
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
     * download_items
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */
    public function download_items($type, $itemids) {
        $url = $this->get_items_url($type, $itemids);
        $post = $this->get_items_post($type, $itemids);
        return $this->download_xml($url, $post);
    }

    /**
     * get_items_url
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_items_url($type, $itemids) {
        return $this->baseurl;
    }

    /**
     * get_items_params
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_items_params($type, $itemids) {
        return null;
    }

    /**
     * get_items_post
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_items_post($type, $itemids) {
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

    /**
     * get_questions
     *
     * @param xxx $itemid
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_questions($itemid) {

        $url = $this->get_questions_url($itemid);
        $post = $this->get_questions_post($itemid);
        $xml = $this->download_xml($url, $post);

        // the data from a Moodle 1.x backup has the following structure:
        // MOODLE_BACKUP -> INFO
        // - MOODLE_VERSION, MOODLE_RELEASE, DATE, ORIGINAL_WWWROOT, ZIP_METHOD, DETAILS
        // MOODLE_BACKUP -> ROLES
        // - ROLE
        // MOODLE_BACKUP -> COURSE
        // - HEADER, BLOCKS, SECTIONS, QUESTION_CATEGORIES, GROUPS, GRADEBOOK, MODULES, FORMDATA

        $modules = array();
        $categories = array();

        if (is_array($xml)) {
            if (isset($xml['MOODLE_BACKUP']['#']['COURSE'])) {
                $course = &$xml['MOODLE_BACKUP']['#']['COURSE'];
                if (isset($course['0']['#']['MODULES'])) {
                    $modules = $this->get_xml_values_mods($course['0']['#']['MODULES']);
                }
                if (isset($course['0']['#']['QUESTION_CATEGORIES'])) {
                    $categories = $this->get_xml_values_categories($course['0']['#']['QUESTION_CATEGORIES']);
                }
                unset($course);
            }
        }

        $module = reset($modules);
        return array($module, $categories);
    }

    /*
     * get_xml_values_context
     *
     * @param xxx $xml (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_xml_values_context(&$xml) {
        $defaults = array('level' => '', 'instance' => 0);
        return $this->get_xml_values($xml['0']['#'], $defaults);
    }

    /*
     * get_xml_values_categories
     *
     * @param xxx $xml (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_xml_values_categories(&$xml) {
        $categories = array();

        if (isset($xml['0']['#']['QUESTION_CATEGORY'])) {
            $category = &$xml['0']['#']['QUESTION_CATEGORY'];

            foreach (array_keys($category) as $c) {
                $categories[$c] = $this->get_xml_values_category($category["$c"]['#']);
            }
            unset($category);
        }

        return $this->convert_to_assoc_array($categories, 'id');
    }

    /*
     * get_xml_values_category
     *
     * @param xxx $xml (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_xml_values_category(&$xml) {
        $defaults = array('id' => '', 'name' => '', 'info' => '', 'stamp' => '', 'parent' => 0, 'sortorder' => 0);
        return $this->get_xml_values($xml, $defaults);
    }

    /*
     * get_xml_values_questions
     *
     * @param xxx $xml (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_xml_values_questions(&$xml) {
        $questions = array();
        if (isset($xml['0']['#']['QUESTION'])) {

            $question = $xml['0']['#']['QUESTION'];
            foreach (array_keys($question) as $q) {
                $defaults = array('id'              => 0,  'parent'             => 0,  'name'      => '',
                                  'questiontext'    => '', 'questiontextformat' => 0,  'image'     => '',
                                  'generalfeedback' => '', 'generalfeedbackformat' => 0,
                                  'defaultgrade'    => 0,  'defaultscore'       => 0,  'penalty'   => 0, 'qtype'      => '',
                                  'length'          => '', 'stamp'              => '', 'version'   => 0, 'hidden'     => '',
                                  'timecreated'     => 0,  'timemodified'       => 0,  'createdby' => 0, 'modifiedby' => 0);
                $questions[$q] = $this->get_xml_values($question["$q"]['#'], $defaults);
            }
            unset($question);
        }

        return $this->convert_to_assoc_array($questions, 'id');
    }

    /*
     * get_xml_values_ordering
     *
     * @param xxx $xml (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_xml_values_ordering(&$xml) {
        $defaults = array('logical' => 1, 'studentsee' => 6, 'correctfeedback' => '', 'partiallycorrectfeedback' => '', 'incorrectfeedback' => '');
        return $this->get_xml_values($xml['0']['#'], $defaults);
    }

    /*
     * get_xml_values_matchoptions
     *
     * @param xxx $xml (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_xml_values_matchoptions(&$xml) {
        $defaults = array('id' => 0, 'question' => 0, 'subquestions' => '', 'shuffleanswers' => 1, 'shownumcorrect' => 0, 'correctfeedback' => '', 'partiallycorrectfeedback' => '', 'incorrectfeedback' => '');
        return $this->get_xml_values($xml['0']['#'], $defaults);
    }

    /*
     * get_xml_values_matchs
     *
     * @param xxx $xml (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_xml_values_matchs(&$xml) {
        $matchs = array();

        if (isset($xml['0']['#']['MATCH'])) {
            $match = &$xml['0']['#']['MATCH'];

            foreach (array_keys($match) as $m) {
                $defaults = array('id' => 0, 'code' => 0, 'questiontext' => '', 'questiontextformat' => 0, 'answertext' => '');
                $matchs[$m] = $this->get_xml_values($match["$m"]['#'], $defaults);
            }
            unset($match);
        }
        return $matchs;
    }

    /*
     * get_xml_values_multianswers
     *
     * @param xxx $xml (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_xml_values_multianswers(&$xml) {
        $multianswers = array();

        if (isset($xml['0']['#']['MULTIANSWER'])) {
            $multianswer = &$xml['0']['#']['MULTIANSWER'];

            foreach (array_keys($multianswer) as $m) {
                $defaults = array('id' => 0, 'question' => 0, 'sequence' => '');
                $multianswers[$m] = $this->get_xml_values($multianswer["$m"]['#'], $defaults);
            }
            unset($multianswer);
        }
        return $multianswers;
    }

    /*
     * get_xml_values_multichoice
     *
     * @param xxx $xml (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_xml_values_multichoice(&$xml) {
        $defaults = array('layout' => '0', 'answers' => array(), 'single' => 1, 'shuffleanswers' => 1, 'answernumbering' => 'abc', 'shownumcorrect' => 0, 'correctfeedback' => '', 'partiallycorrectfeedback' => '', 'incorrectfeedback' => '');
        return $this->get_xml_values($xml['0']['#'], $defaults);
    }

    /*
     * get_xml_values_truefalse
     *
     * @param xxx $xml (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_xml_values_truefalse(&$xml) {
        $defaults = array('trueanswer' => 0, 'falseanswer' => 0);
        return $this->get_xml_values($xml['0']['#'], $defaults);
    }

    /*
     * get_xml_values_shortanswer
     * Cengage Footprint (2600) Dinosaur Builder
     *
     * @param xxx $xml (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_xml_values_shortanswer(&$xml) {
        $defaults = array('answers' => '', 'usecase' => 0);
        return $this->get_xml_values($xml['0']['#'], $defaults);
    }

    /*
     * get_xml_values_answers
     *
     * @param xxx $xml (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_xml_values_answers(&$xml) {
        $answers = array();

        if (isset($xml['0']['#']['ANSWER'])) {
            $answer = &$xml['0']['#']['ANSWER'];

            foreach (array_keys($answer) as $a) {
                $defaults = array('id' => 0, 'answer_text' => '', 'fraction' => 0, 'feedback' => '');
                $answers[$a] = $this->get_xml_values($answer["$a"]['#'], $defaults);
            }
            unset($answer);
        }
        return $answers;
    }

    /*
     * get_xml_values_mods
     *
     * @param xxx $xml (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_xml_values_mods(&$xml) {
        $mods = array();
        if (isset($xml['0']['#']['MOD'])) {
            $mod = &$xml['0']['#']['MOD'];

            foreach (array_keys($mod) as $m) {
                $defaults = $this->get_xml_values_mod_defaults($mod["$m"]['#']);
                $mods[$m] = $this->get_xml_values($mod["$m"]['#'], $defaults);
            }
            unset($mod);
        }
        return $this->convert_to_assoc_array($mods, 'id');
    }

    public function get_xml_values_mod_defaults(&$xml) {
        $modtype = $xml['MODTYPE']['0']['#'];
        if ($modtype=='quiz') {
            return array('id'              => 0, 'modtype'       => '', 'name'             => '', 'intro'            => '',
                         'timeopen'        => 0, 'timeclose'     => 0,  'optionflags'      => 0,  'penaltyscheme'    => 0,
                         'attempts_number' => 0, 'attemptonlast' => 0,  'grademethod'      => 0,  'decimalpoints'    => 0,
                         'review'          => 0, 'questions'     => '', 'questionsperpage' => 0,  'shufflequestions' => 0,
                         'shuffleanswers'  => 0, 'sumgrades'     => 0,  'grade'            => 0,  'timecreated'      => 0,
                         'timemodified'    => 0, 'timelimit'     => 0,  'password'         => '', 'subnet'           => '',
                         'popup'           => 0, 'delay1'        => 0,  'delay2'           => 0);
        }
        // report unknown $modtype, and suggest $defaults
        $keys = array_keys($xml);
        $keys = array_map('strtolower', $keys);
        echo '$defaults'." = array('".implode("' => '', '", $keys)."' => '');";
        throw new moodle_exception('Unknown MODTYPE: '.$modtype);
    }
    /*
     * get_xml_values_question_instances
     *
     * @param xxx $xml (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_xml_values_question_instances(&$xml) {
        $instances = array();
        if (isset($xml['0']['#']['QUESTION_INSTANCE'])) {

            $instance = $xml['0']['#']['QUESTION_INSTANCE'];
            foreach (array_keys($instance) as $i) {
                $defaults = array('id' => 0, 'question' => 0, 'grade' => 0);
                $instances[$i] = $this->get_xml_values($instance["$i"]['#'], $defaults);
            }
            unset($instance);
        }
        return $this->convert_to_assoc_array($instances, 'id');
    }

    /*
     * get_xml_values_feedbacks
     *
     * @param xxx $xml (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_xml_values_feedbacks(&$xml) {
        $feedbacks = array();
        if (isset($xml['0']['#']['FEEDBACK'])) {

            $feedback = $xml['0']['#']['FEEDBACK'];
            foreach (array_keys($feedback) as $f) {
                $defaults = array('id' => 0, 'quizid' => 0, 'feedbacktext' => '', 'mingrade' => 0, 'maxgrade' => 0);
                $feedbacks[$f] = $this->get_xml_values($feedback["$f"]['#'], $defaults);
            }
            unset($feedback);
        }
        return $this->convert_to_assoc_array($feedbacks, 'id');
    }

    /*
     * get_xml_values_sections
     *
     * @param xxx $xml (passed by reference)
     * @param xxx $mods (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_xml_values_sections(&$xml, &$mods) {
        $sections = array();
        if ($xml['0']['#']['SECTION']) {
            $section = $xml['0']['#']['SECTION'];
            foreach (array_keys($section) as $s) {
                if (isset($section["$s"]['#']['MODS']['0']['#']['MOD'])) {
                    $defaults = array('id' => 0, 'number' => 0, 'summary' => '', 'visible' => 1);
                    $sections[$s] = $this->get_xml_values($section["$s"]['#'], $defaults);
                    $sections[$s]->summary = stripslashes(strip_tags($sections[$s]->summary));
                }
            }
        }
        return $this->convert_to_assoc_array($sections, 'number');
    }

    /*
     * convert_to_assoc_array
     *
     * @param xxx $items
     * @param xxx $field
     * @return xxx
     * @todo Finish documenting this function
     */
    public function convert_to_assoc_array($items, $field) {
        $return = array();
        foreach ($items as $item) {
            $return[$item->$field] = $item;
        }
        return $return;
    }

    /*
     * get_xml_values
     *
     * @param xxx $xml (passed by reference)
     * @param xxx $defaults
     * @param xxx $stdclass (optional, default=null)
     * @todo Finish documenting this function
     */
    public function get_xml_values(&$xml, $defaults, $stdclass=null) {

        if ($xml===null) {
            throw new moodle_exception('Oops $xml is NULL');
        }

        if ($stdclass===null) {
            $stdclass = new stdClass();
        }

        foreach ($defaults as $name => $value) {
            $NAME = strtoupper($name);
            if (isset($xml[$NAME]['0']['#'])) {
                $stdclass->$name = $xml[$NAME]['0']['#'];
            } else {
                $stdclass->$name = $value;
            }
        }

        // get the $names of fields from the $xml
        // that were not transferred to $stdclass
        $names = array_keys($xml);
        $names = array_map('strtolower', $names);
        $names = array_diff($names, array_keys($defaults));

        foreach ($names as $name) {
            $method = 'get_xml_values_'.$name;
            if (method_exists($this, $method)) {
                $NAME = strtoupper($name);
                $stdclass->$name = $this->$method($xml[$NAME]);
            } else {
                echo 'oops, method not found: '.$method;
                print_object($stdclass);
                print_object($xml);
                throw new moodle_exception('oops');
            }
        }

        return $stdclass;
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
    const DEFAULT_FILESFOLDER = '/files';

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
     * get_items_url
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_items_url($type, $itemids) {
        switch ($type) {
            case reader_downloader::BOOKS_WITH_QUIZZES: $filepath = '/index.php'; break;
            case reader_downloader::BOOKS_WITHOUT_QUIZZES: $filepath = '/index-noq.php'; break;
            default: $filepath = ''; // shouldn't happen !!
        }
        $params = $this->get_items_params($type, $itemids);
        return new moodle_url($this->baseurl.$filepath, $params);
    }

    /**
     * get_items_params
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_items_params($type, $itemids) {
        return array('a' => 'items', 'login' => $this->username, 'password' => $this->password);
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
        $available = new reader_download_items();

        $items = $this->download_items($type, $itemids);
        if ($items && isset($items['myxml']['#']['item'])) {

            foreach ($items['myxml']['#']['item'] as $item) {

                // sanity check on expected fields
                if (! isset($item['@']['publisher'])) {
                    continue;
                }
                if (! isset($item['@']['needpass'])) {
                    continue;
                }
                if (! isset($item['@']['level'])) {
                    continue;
                }
                if (! isset($item['@']['id'])) {
                    continue;
                }
                if (! isset($item['#'])) {
                    continue;
                }

                $publisher = trim($item['@']['publisher']);
                $needpass  = trim($item['@']['needpass']);
                $level     = trim($item['@']['level']);
                $itemid    = trim($item['@']['id']);
                $itemname  = trim($item['#']);
                $time      = (empty($item['@']['time']) ? 0 : intval($item['@']['time']));

                if ($time==0 && isset($downloaded->items[$publisher]->items[$level]->items[$itemname])) {
                    $time = $downloaded->items[$publisher]->items[$level]->items[$itemname]->time;
                    $time = $this->get_remote_filetime($publisher, $level, $itemname, $time);
                }

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

                if (! isset($downloaded->items[$publisher]->items[$level]->items[$itemname])) {
                    // this item has never been downloaded
                    $available->newcount++;
                    $available->items[$publisher]->newcount++;
                    $available->items[$publisher]->items[$level]->newcount++;
                } else if ($downloaded->items[$publisher]->items[$level]->items[$itemname]->time < $time) {
                    // an update for this item is available
                    $available->updatecount++;
                    $available->items[$publisher]->updatecount++;
                    $available->items[$publisher]->items[$level]->updatecount++;
                }

                // flag this item as available
                $available->items[$publisher]->items[$level]->items[$itemname] = new reader_download_item($itemid, $time);
            }
        }

        // define callback for sorting levels by name
        $sort_level_by_name = array($this, 'sort_level_by_name');

        // sort items by name
        ksort($available->items);
        $publishers = array_keys($available->items);
        foreach ($publishers as $publisher) {
            uksort($available->items[$publisher]->items, $sort_level_by_name);
            $levels = array_keys($available->items[$publisher]->items);
            foreach ($levels as $level) {
                ksort($available->items[$publisher]->items[$level]->items);
            }
        }

        return $available;
    }

    /**
     * sort_level_by_name
     *
     * @param xxx $a
     * @param xxx $b
     * @return xxx
     * @todo Finish documenting this function
     */
    public function sort_level_by_name($a, $b) {

        // search and replace strings
        $search1 = array('/^-+$/', '/\bLadder\s+([0-9]+)$/', '/\bLevel\s+([0-9]+)$/', '/\bStage\s+([0-9]+)$/', '/^Extra_Points|testing|_testing_only$/', '/Booksworms/');
        $replace1 = array('', '100$1', '200$1', '300$1', 9999, 'Bookworms');

        $search2 = '/\b(Pre|Low|Upper|High)?[ -]*(EasyStarts?|Quick Start|Starter|Beginner|Beginning|Elementary|Intermediate|Advanced)$/';
        $replace2 = array($this, 'convert_level_to_number');

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

        // empty names always go last
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
     * convert_level_to_number
     *
     * @param xxx $matches 1=Pre|Low|Upper|High, 2=Beginner|Elementary|Intermediate|Advanced ...
     * @return xxx
     * @todo Finish documenting this function
     */
    public function convert_level_to_number($matches) {
        $num = 0;
        switch ($matches[1]) {
            case 'Pre':   $num -= 10; break;
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
 * reader_download_item
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class reader_download_item {
    /** the item id */
    public $id = 0;

    /** the last modified/updated time */
    public $time = 0;

    public function __construct($id, $time) {
        $this->id  = $id;
        $this->time = $time;
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
    /** an array of items */
    public $items = array();

    /** the number of items in the $items array */
    public $count = 0;
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
    /** the number of items which have not been downloaded before */
    public $newcount = 0;

    /** the number of items which have updates available */
    public $updatecount = 0;

    /** the password ,if any, that is required to access these items */
    public $needpassword = false;
}

/**
 * reader_restore_ids
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class reader_restore_ids {
    public $ids = array();

    /**
     * set_ids
     *
     * @param string  $type
     * @param integer $oldid
     * @param integer $newid
     * @return xxx
     * @todo Finish documenting this function
     */
    public function set_ids($type, $oldid, $newid) {
        if (empty($this->ids[$type])) {
            $this->ids[$type] = array();
        }
        $this->ids[$type][$oldid] = $newid;
    }

    /**
     * get_newid
     *
     * @param string  $type
     * @param integer $oldid
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_newid($type, $oldid) {
        if (empty($this->ids[$type][$oldid])) {
            return 0;
        }
        return $this->ids[$type][$oldid];
    }

    /**
     * get_oldid
     *
     * @param string  $type
     * @param integer $newid
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_oldid($type, $newid) {
        if (empty($this->ids[$type])) {
            return false;
        }
        return array_search($newid, $this->ids[$type]);
    }

    /**
     * get_newids
     *
     * @param string $type
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_newids($type) {
        if (empty($this->ids[$type])) {
            return array();
        }
        return $this->ids[$type];
    }
}

/**
 * reader_download_progress_task
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class reader_download_progress_task {
    /** the name of this task */
    public $name = '';

    /** the percentage to which this task is complete */
    public $percent = 0;

    /** the weighting of this task toward its parent task */
    public $weighting = 0;

    /** the total weighting of the child tasks */
    public $childweighting = 0;

    /** the parent task object */
    public $parenttask = null;

    /** an array of child tasks */
    public $tasks = array();

    /**
     * __construct
     *
     * @param xxx $name (optional, default="")
     * @param xxx $weighting (optional, default=100)
     * @param xxx $tasks (optional, default=array())
     * @return xxx
     * @todo Finish documenting this function
     */
    public function __construct($name='', $weighting=100, $tasks=array()) {
        $this->name = $name;
        $this->weighting = $weighting;
        $this->add_tasks($tasks);
    }

    /**
     * add_tasks
     *
     * @param xxx $tasks
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_tasks($tasks) {
        foreach ($tasks as $taskid => $task) {
            $this->add_task($taskid, $task);
        }
    }

    /**
     * add_task
     *
     * @param xxx $taskid
     * @param xxx $task
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_task($taskid, $task) {
        if (is_string($task)) {
            $taskid = $task;
            $task = new reader_download_progress_task();
        }
        $task->set_parenttask($this);
        $this->tasks[$taskid] = $task;
        $this->childweighting += $task->weighting;
    }

    /**
     * get_task
     *
     * @param xxx $taskid
     * @param xxx $task
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_task($taskid) {
        if (empty($this->tasks[$taskid])) {
            return false; // shouldn't happen !!
        }
        return $this->tasks[$taskid];
    }

    /**
     * set_parenttask
     *
     * @param xxx $parenttask (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function set_parenttask($parenttask) {
        $this->parenttask = $parenttask;
    }

    /**
     * set_title
     *
     * @param string $title
     * @return xxx
     * @todo Finish documenting this function
     */
    public function set_title($title='') {
        if ($this->parenttask && $title) {
            $this->parenttask->set_title($title);
        }
    }

    /**
     * finish
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function finish($title='') {
        $this->set_percent(100, $title);
    }

    /**
     * set_percent
     *
     * @param integer $percent
     * @return xxx
     * @todo Finish documenting this function
     */
    public function set_percent($percent, $title='') {
        $this->percent = $percent;
        if ($this->parenttask) {
            $this->parenttask->checktasks($title);
        }
    }

    /**
     * checktasks
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function checktasks($title='') {
        if ($this->childweighting) {
            $childweighting = 0;
            foreach ($this->tasks as $task) {
                $childweighting += ($task->weighting * ($task->percent / 100));
            }
            $percent = round(100 * ($childweighting / $this->childweighting));
        } else {
            $percent = 0;
        }
        $this->set_percent($percent, $title);
    }
}

/**
 * reader_download_progress_bar
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class reader_download_progress_bar extends reader_download_progress_task {

    /** a Moodle progress bar to display the progress of the download */
    private $bar = null;

    /** the title displayed in the progress bar */
    private $title = null;

    /** the time after which more processing time will be requested */
    private $timeout = 0;

    /** object to store current ids */
    public $current = null;

    /**
     * __construct
     *
     * @param xxx $name
     * @param xxx $weighting
     * @param xxx $tasks (optional, default=array())
     * @return xxx
     * @todo Finish documenting this function
     */
    public function __construct($name='', $weighting=100, $tasks=array()) {
        parent::__construct($name, $weighting, $tasks);
        $this->bar = new progress_bar($name, 500, true);
        $this->title = get_string($this->name, 'reader');
        $this->start_current();
        $this->reset_timeout();
    }

    /**
     * create
     *
     * @param array $itemids
     * @param string $name
     * @param integer $weighting (optional, default=100)
     * @return xxx
     * @todo Finish documenting this function
     */
    static function create($itemids, $name, $weighting=100) {
        $tasks = array();
        $tasks['items'] = self::create_items($itemids);
        return new reader_download_progress_bar($name, $weighting, $tasks);
    }

    /**
     * create_items
     *
     * @param array $items
     * @param integer $weighting (optional, default=100)
     * @return xxx
     * @todo Finish documenting this function
     */
    static function create_items($items, $weighting=100) {
        $tasks = array();
        foreach ($items as $item) {
            $taskid = (is_object($item) ? $item->id : $item);
            $tasks[$taskid] = self::create_item($item);
        }
        return new reader_download_progress_task('items', $weighting, $tasks);
    }

    /**
     * create_item
     *
     * @param xxx $item
     * @param integer $weighting (optional, default=100)
     * @return xxx
     * @todo Finish documenting this function
     */
    static function create_item($item, $weighting=100) {
        $tasks = array();
        $tasks['data'] = new reader_download_progress_task('data', 20);
        if (isset($item->quiz)) {
            $tasks['quiz'] = self::create_quiz($item->quiz, 80);
        }
        return new reader_download_progress_task('item', $weighting, $tasks);
    }

    /**
     * create_quiz
     *
     * @param xxx $quiz
     * @param integer $weighting (optional, default=100)
     * @return xxx
     * @todo Finish documenting this function
     */
    static function create_quiz($quiz, $weighting=100) {
        $tasks = array();
        $tasks['data'] = new reader_download_progress_task('data', 10);
        if (isset($quiz->categories)) {
            $tasks['categories'] = self::create_categories($quiz->categories, 80);
        }
        if (isset($quiz->instances)) {
            $tasks['instances'] = self::create_instances($quiz->instances, 10);
        }
        return new reader_download_progress_task('quiz', $weighting, $tasks);
    }

    /**
     * create_instances
     *
     * @param array $instances
     * @param integer $weighting (optional, default=100)
     * @return xxx
     * @todo Finish documenting this function
     */
    static function create_instances($instances, $weighting=100) {
        $tasks = array();
        foreach ($instances as $instance) {
            $taskid = (is_object($instance) ? $instance->id : $instance);
            $tasks[$taskid] = new reader_download_progress_task('instance');
        }
        return new reader_download_progress_task('instances', $weighting, $tasks);
    }

    /**
     * create_categories
     *
     * @param array $categories
     * @param integer $weighting (optional, default=100)
     * @return xxx
     * @todo Finish documenting this function
     */
    static function create_categories($categories, $weighting=100) {
        $tasks = array();
        foreach ($categories as $category) {
            $taskid = (is_object($category) ? $category->id : $category);
            $tasks[$taskid] = self::create_category($category);
        }
        return new reader_download_progress_task('categories', $weighting, $tasks);
    }

    /**
     * create_category
     *
     * @param xxx $category
     * @param integer $weighting (optional, default=100)
     * @return xxx
     * @todo Finish documenting this function
     */
    static function create_category($category, $weighting=100) {
        $tasks = array();
        $tasks['data'] = new reader_download_progress_task('data', 20);
        if (isset($category->questions)) {
            $tasks['questions'] = self::create_questions($category->questions, 80);
        }
        return new reader_download_progress_task('category', $weighting, $tasks);
    }

    /**
     * create_questions
     *
     * @param array $questions
     * @param integer $weighting (optional, default=100)
     * @return xxx
     * @todo Finish documenting this function
     */
    static function create_questions($questions, $weighting=100) {
        $tasks = array();
        foreach ($questions as $question) {
            $taskid = (is_object($question) ? $question->id : $question);
            $tasks[$taskid] = self::create_question($question);
        }
        return new reader_download_progress_task('questions', $weighting, $tasks);
    }

    /**
     * create_question
     *
     * @param xxx $question
     * @param integer $weighting (optional, default=100)
     * @return xxx
     * @todo Finish documenting this function
     */
    static function create_question($question, $weighting=100) {
        $tasks = array();
        $tasks['data'] = new reader_download_progress_task('data', 10);
        $tasks['options'] = new reader_download_progress_task('options', 10);
        if (isset($question->answers)) {
            $tasks['answers'] = self::create_answers($question->answers, 80);
        }
        return new reader_download_progress_task('question', $weighting, $tasks);
    }

    /**
     * create_answers
     *
     * @param array $answers
     * @param integer $weighting (optional, default=100)
     * @return xxx
     * @todo Finish documenting this function
     */
    static function create_answers($answers, $weighting=100) {
        $tasks = array();
        foreach ($answers as $answer) {
            $taskid = (is_object($answer) ? $answer->id : $answer);
            $tasks[$taskid] = new reader_download_progress_task('answer');
        }
        return new reader_download_progress_task('answers', $weighting, $tasks);
    }

    /**
     * set_percent
     *
     * @param integer $percent
     * @return xxx
     * @todo Finish documenting this function
     */
    public function set_percent($percent, $title='') {
        parent::set_percent($percent);
        $this->set_title($title);
    }

    /**
     * start
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function set_title($title='') {
        if ($title) {
            $this->title = $title;
        }
        $this->update();
    }

    /**
     * update
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function update() {
        $this->reset_timeout(); // request more time
        $this->bar->update($this->percent, 100, $this->title);
    }

    /**
     * reset_timeout
     *
     * @param integer $timeout (optional, default=300)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function reset_timeout($moretime=300) {
        $time = time();
        if ($this->timeout < $time && $this->percent < 100) {
            $this->timeout = ($time + $moretime);
            set_time_limit($moretime);
        }
    }

    /**
     * start_current
     *
     * @param string  $type  (optional, default='')
     * @param integer $id    (optional, default=0)
     * @param string  $title (optional, default='')
     * @return xxx
     * @todo Finish documenting this function
     */
    public function start_current($type='', $id=0, $title='') {
        $field = $type.'id';
        if ($type && isset($this->current->$field)) {
            $this->current->$field = $id;
        } else {
            $this->current = new stdClass();
        }

        // setup ids (drop-throughs are intentional)
        switch ($type) {
            case ''        : $this->current->itemid     = 0;
            case 'item'    : $this->current->instanceid = 0;
            case 'instance': $this->current->categoryid = 0;
            case 'category': $this->current->questionid = 0;
            case 'question': $this->current->answerid   = 0;
        }

        $this->set_title($title);
    }

    /**
     * finish_current
     *
     * @param string  $type  (optional, default='')
     * @param string  $title (optional, default='')
     * @return xxx
     * @todo Finish documenting this function
     */
    public function finish_current($type='', $title='') {
        // assemble required ids (drop-throughs are intentional)
        switch ($type) {
            case 'answer'  : $answerid   = $this->current->answerid;
            case 'options' : // drop though
            case 'question': $questionid = $this->current->questionid;
            case 'instance': $instanceid = $this->current->instanceid;
            case 'category': $categoryid = $this->current->categoryid;
            case 'item'    : $itemid     = $this->current->itemid;
        }

        // initiate "finish()" method of appropriate object
        switch ($type) {
            case ''        : $this->finish($title);
                             unset($this->tasks['items']);
                             break;
            case 'item'    : $this->tasks['items']->tasks[$itemid]->finish($title);
                             unset($this->tasks['items']->tasks[$itemid]->tasks['quiz']);
                             break;
            case 'instance': $this->tasks['items']->tasks[$itemid]->tasks['quiz']->tasks['instances']->tasks[$instanceid]->finish($title);
                             //unset($this->tasks['items']->tasks[$itemid]->tasks['quiz']->tasks['instances']->tasks[$instanceid]);
                             break;
            case 'category': $this->tasks['items']->tasks[$itemid]->tasks['quiz']->tasks['categories']->tasks[$categoryid]->finish($title);
                             //unset($this->tasks['items']->tasks[$itemid]->tasks['quiz']->tasks['categories']->tasks[$categoryid]);
                             break;
            case 'question': $this->tasks['items']->tasks[$itemid]->tasks['quiz']->tasks['categories']->tasks[$categoryid]->tasks['questions']->tasks[$questionid]->finish($title);
                             //unset($this->tasks['items']->tasks[$itemid]->tasks['quiz']->tasks['categories']->tasks[$categoryid]->tasks['questions']->tasks[$questionid]);
                             break;
            case 'options' : $this->tasks['items']->tasks[$itemid]->tasks['quiz']->tasks['categories']->tasks[$categoryid]->tasks['questions']->tasks[$questionid]->tasks['options']->finish($title);
                             //unset($this->tasks['items']->tasks[$itemid]->tasks['quiz']->tasks['categories']->tasks[$categoryid]->tasks['questions']->tasks[$questionid]->tasks['options']);
                             break;
            case 'answer'  : $this->tasks['items']->tasks[$itemid]->tasks['quiz']->tasks['categories']->tasks[$categoryid]->tasks['questions']->tasks[$questionid]->tasks['answers']->tasks[$answerid]->finish($title);
                             //unset($this->tasks['items']->tasks[$itemid]->tasks['quiz']->tasks['categories']->tasks[$categoryid]->tasks['questions']->tasks[$questionid]->tasks['answers']->tasks[$answerid]);
                             break;
        }
    }

    /**
     * add_quiz
     *
     * @param xxx $categories
     * @param xxx $instances
     * @param integer $weighting (optional, default=80)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function add_quiz($categories, $instances, $weighting=80) {
        $itemid = $this->current->itemid;
        $quiz = (object)array('categories' => $categories, 'instances' => $instances);
        $this->tasks['items']->tasks[$itemid]->add_task('quiz', self::create_quiz($quiz, $weighting));
    }

    /**
     * start_item
     *
     * @param xxx $id
     * @param xxx $title (optional, default="")
     * @return xxx
     * @todo Finish documenting this function
     */
    public function start_item($id, $title='') {
        $this->start_current('item', $id, $title);
    }

    /**
     * start_instance
     *
     * @param xxx $id
     * @param xxx $title (optional, default="")
     * @return xxx
     * @todo Finish documenting this function
     */
    public function start_instance($id, $title='') {
        $this->start_current('instance', $id, $title);
    }

    /**
     * start_category
     *
     * @param xxx $id
     * @param xxx $title (optional, default="")
     * @return xxx
     * @todo Finish documenting this function
     */
    public function start_category($id, $title='') {
        $this->start_current('category', $id, $title);
    }

    /**
     * start_question
     *
     * @param xxx $id
     * @param xxx $title (optional, default="")
     * @return xxx
     * @todo Finish documenting this function
     */
    public function start_question($id, $title='') {
        $this->start_current('question', $id, $title);
    }

    /**
     * start_answer
     *
     * @param xxx $id
     * @param xxx $title (optional, default="")
     * @return xxx
     * @todo Finish documenting this function
     */
    public function start_answer($id, $title='') {
        $this->start_current('answer', $id, $title);
    }

    /**
     * finish_item
     *
     * @param xxx $itemid
     * @param xxx $title (optional, default="")
     * @return xxx
     * @todo Finish documenting this function
     */
    public function finish_item($title='') {
        $this->finish_current('item', $title);
    }

    /**
     * finish_instances
     *
     * @param xxx $itemid
     * @param xxx $instanceid
     * @param xxx $title (optional, default="")
     * @return xxx
     * @todo Finish documenting this function
     */
    public function finish_instance($title='') {
        $this->finish_current('instance', $title);
    }

    /**
     * finish_category
     *
     * @param xxx $title (optional, default="")
     * @return xxx
     * @todo Finish documenting this function
     */
    public function finish_category($title='') {
        $this->finish_current('category', $title);
    }

    /**
     * finish_question
     *
     * @param xxx $title (optional, default="")
     * @return xxx
     * @todo Finish documenting this function
     */
    public function finish_question($title='') {
        $this->finish_current('question', $title);
    }

    /**
     * finish_options
     *
     * @return xxx
     * @todo Finish documenting this function
     */
    public function finish_options($title='') {
        $this->finish_current('options', $title);
    }

    /**
     * finish_answer
     *
     * @param xxx $title (optional, default="")
     * @return xxx
     * @todo Finish documenting this function
     */
    public function finish_answer($title='') {
        $this->finish_current('answer', $title);
    }
}
