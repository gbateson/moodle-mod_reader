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
 * mod/reader/admin/books/download/remotesite/moodlereadernet.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die();

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
     * is_error_curl_xml
     *
     * @param string $results downloaded via CURL
     * @return string
     * @todo Finish documenting this function
     */
    public function is_error_curl_xml($results) {
        $search = '/^\s*<\?xml[^>]*>\s*<myxml[^>]*>\s*<error[^>]*>(.*?)<\/error>\s*<\/myxml>\s*$/is';
        if (preg_match($search, $results, $matches)) {
            return $matches[1];
        }
        return ''; // i.e. no error
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
        return array('getid' => $itemid); // , 'pass' => ''
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

    /*
     * get_xml_values_ordering
     *
     * @param xxx $xml (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_xml_values_ordering(&$xml) {
        // rename "logical" to "selecttype"
        if (isset($xml['0']['#']['LOGICAL'])) {
            $xml['0']['#']['SELECTTYPE'] = $xml['0']['#']['LOGICAL'];
            unset($xml['0']['#']['LOGICAL']);
        }
        // convert "studentsee" to "selectcount"
        if (isset($xml['0']['#']['STUDENTSEE'])) {
            $xml['0']['#']['SELECTCOUNT'] = $xml['0']['#']['STUDENTSEE'];
            $xml['0']['#']['SELECTCOUNT']['0']['#'] += 2;
            unset($xml['0']['#']['STUDENTSEE']);
        }
        return parent::get_xml_values_ordering($xml);
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

                if ($publisher=='testing' || $publisher=='_testing_only') {
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

    /*
     * get_usage_url
     *
     * @return string
     */
    public function get_usage_url() {
        return new moodle_url($this->baseurl.'/update_quizzes.php');
    }

    /*
     * get_usage_post
     *
     * @param array $usage
     * @return array
     */
    public function get_usage_post($usage) {
        return array('json' => json_encode($usage));
    }

    /**
     * get_usage
     *
     * @param boolean $include_unused_books (optional, default=FALSE) if TRUE, return details of unused books
     * @todo Finish documenting this function
     */
    function get_usage($include_unused_books=false) {
        global $DB;

        $readercfg = get_config('mod_reader');
        $time = time();

        $usage = (object)array(
            'readers'     => array(),
            'books'       => array(),
            'lastupdate'  => $time,
            'userlogin'   => $readercfg->serverusername,
            'returnimage' => 1
        );

        if ($readers = $DB->get_records ('reader')) {
            foreach ($readers as $reader) {

                $usage->readers[$reader->id] = (object)array(
                    'totalusers'      => 0,
                    'attemptsperuser' => 0,
                    'ignoredate'      => $reader->ignoredate,
                    'course'          => $reader->course,
                    'shortname'       => $DB->get_field('course', 'shortname', array('id' => $reader->course)),
                );

                $select = 'readerid = ? and timestart >= ?';
                $params = array($reader->id, $reader->ignoredate);

                if ($countattempts = $DB->get_field_select('reader_attempts', 'COUNT(id)', $select, $params)) {
                    if ($countusers = $DB->get_field_select('reader_attempts', 'COUNT(DISTINCT userid)', $select, $params)) {
                        $usage->readers[$reader->id]->totalusers = $countusers;
                        $usage->readers[$reader->id]->attemptsperuser = round($countattempts / $countusers, 1);
                    }
                }
            }
        } else {
            $readers = array();
        }

        if ($books = $DB->get_records_select('reader_books', 'hidden <> ?', array(1))) {

            // specify fixed parameters for extracting attempts
            $sort   = 'readerid, timefinished';
            $fields = 'id, bookid, readerid, passed, credit, bookrating';

            foreach ($books as $book) {

                $count = array();
                $ratings = array();

                $select = 'bookid = ? AND deleted = ? AND cheated = ? AND timefinished > ?';
                $params = array($book->id, 0, 0, 0);
                if ($attempts = $DB->get_records_select('reader_attempts', $select, $params, $sort, $fields)) {
                    foreach ($attempts as $attempt) {

                        if (empty($usage->books[$attempt->readerid])) {
                            $usage->books[$attempt->readerid] = array();
                        }
                        if (empty($usage->books[$attempt->readerid][$book->image])) {
                            $usage->books[$attempt->readerid][$book->image] = (object)array(
                                'true'   => 0,
                                'false'  => 0,
                                'credit' => 0,
                                'time'   => $book->time,
                                'rate'   => 0,
                                'course' => $usage->readers[$attempt->readerid]->course,
                                'shortname' => $usage->readers[$attempt->readerid]->shortname
                            );
                        }

                        if ($attempt->credit) {
                            $type = 'credit';
                        } else {
                            $type = ($attempt->passed ? 'true' : 'false');
                        }
                        $usage->books[$attempt->readerid][$book->image]->$type++;

                        if (empty($count[$attempt->readerid])) {
                            $count[$attempt->readerid] = 1;
                        } else {
                            $count[$attempt->readerid]++;
                        }

                        if (empty($ratings[$attempt->readerid])) {
                            $ratings[$attempt->readerid] = $attempt->bookrating;
                        } else {
                            $ratings[$attempt->readerid] += $attempt->bookrating;
                        }
                    }
                } else if ($include_unused_books) {
                    // no attempts at this book
                    if (empty($usage->books[0])) {
                        $usage->books[0] = array();
                    }
                    $usage->books[0][$book->image] = (object)array(
                        'true'      => 0,
                        'false'     => 0,
                        'credit'    => 0,
                        'time'      => $book->time,
                        'rate'      => 0,
                        'course'    => 0,
                        'shortname' => 'NOTUSED'
                    );
                }

                foreach ($ratings as $readerid => $rating) {
                    $usage->books[$readerid][$book->image]->rate = round($rating / $count[$readerid], 1);
                }
            }
        }

        return $usage;
    }
}
