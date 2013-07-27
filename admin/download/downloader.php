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
 * mod/reader/admin/download/downloader.php
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
                $time      = (empty($record->time) ? 0 : $record->time);

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

        $this->bar = reader_download_progress_bar::create($itemids, 'readerdownload');

        // show memory on main Reader module developer site
        $show_memory = (file_exists($CFG->dirroot.'/mod/reader/utilities/print_cheatsheet.php'));

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
                $titletext .= " - $level";
            }
            $titlehtml = html_writer::tag('span', $titletext, array('style' => 'font-weight: normal')).
                         html_writer::empty_tag('br').
                         html_writer::tag('span', $name, array('style' => 'white-space: nowrap'));
            $titletext .= ": $name";

            if ($show_memory) {
                $memory_usage = memory_get_usage();
                $memory_usage = number_format($memory_usage/1000000).' MB';
                $memory_peak_usage = memory_get_peak_usage();
                $memory_peak_usage = number_format($memory_peak_usage/1000000).' MB';
                $titletext .= " (memory=$memory_usage peak=$memory_peak_usage)";
            }


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
