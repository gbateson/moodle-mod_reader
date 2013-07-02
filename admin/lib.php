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

    public $remotesites = array();

    public $downloaded = array();

    public $available = array();

    /**
     * __construct
     *
     * @todo Finish documenting this function
     */
    public function __construct() {
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
    public function check_selected_itemids($publishers, $levels, &$itemids) {
        if (count($publishers)==0 && count($levels)==0) {
            return false; // nothing to do
        }
        foreach ($this->available as $r => $available) {
            $i = 0;
            foreach ($available->items as $publishername => $levels) {
                $i++;

                $ii = 0;
                foreach ($levels->items as $levelname => $items) {
                    $ii++;

                    if (in_array($i, $publishers) || in_array($i.'_'.$ii, $levels)) {
                        foreach ($items->items as $itemname => $itemid) {
                            if (! in_array($itemid, $itemids)) {
                                $itemids[] = $itemid;
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
die('Oops - no XML!');
            return false; // shouldn't happen !!
        }
print_object($xml);
die;
        $output = '';
        $started_list = false;
        foreach ($xml['myxml']['#']['item'] as $i => $item) {

            // set up default values for record from $tablename
            $record = (object)array(
                'publisher'  => '',
                'series'     => '',
                'level'      => '',
                'difficulty' => 0,
                'name'       => '',
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

            // transfer values from this $item to this $record
            $fields = get_object_vars($record);
            foreach ($fields as $field => $defaultvalue) {
                if (isset($item['@'][$field])) {
                    $value = $item['@'][$field];
                } else if ($field=='name') {
                    $value = $item['@']['title'];
                } else {
                    $value = $defaultvalue;
                }
                $record->$field = $value;
            }
            $record->quizid = 0; // ignore any incoming "quizid" values

            // initialize array to hold messages
            $msg = array();

            // add or update the $DB information
            $params = array('publisher' => $record->publisher,
                            'level'     => $record->level,
                            'name'      => $record->name);
            if ($record->id = $DB->get_field($tablename, 'id', $params)) {
                $DB->update_record($tablename, $record);
                $msg[] = 'Book data was updated: '.$record->name;
            } else {
                unset($record->id);
                $record->id = $DB->insert_record($tablename, $record);
                $msg[] = 'Book data was added: '.$record->name;
            }

            // download associated image (i.e. book cover)
            $this->download_image($itemid, $record->image, $r);
            //$msg[] = 'Book image was dowloaded: '.$record->image;

            if (count($msg)) {
                if ($started_list==false) {
                    $started_list = true;
                    $output .= html_writer::start_tag('div');
                    $output .= html_writer::start_tag('ul');
                }
                $output .= html_writer::tag('li', implode('</li><li>', $msg));;
            }

            if (! isset($this->downloaded[$r]->items[$record->publisher])) {
                $this->downloaded[$r]->items[$publisher] = new reader_items();
            }
            if (! isset($this->downloaded[$r]->items[$record->publisher]->items[$record->level])) {
                $this->downloaded[$r]->items[$record->publisher]->items[$record->level] = new reader_items();
            }
            if (! isset($this->downloaded[$r]->items[$record->publisher]->items[$record->level]->items[$record->name])) {
                $this->downloaded[$r]->items[$record->publisher]->items[$record->level]->items[$record->name] = true;
                $this->available[$r]->items[$record->publisher]->items[$record->level]->newcount--;
                $this->available[$r]->items[$record->publisher]->newcount--;
                $this->available[$r]->newcount--;
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
     * @param xxx $itemid
     * @param xxx $filename
     * @param xxx $r (optional, default=0)
     * @todo Finish documenting this function
     */
    public function download_image($itemid, $filename, $r=0) {
        global $CFG;
        make_upload_directory('reader/images');

        $remotesite = $this->remotesites[$r];
        $url = $remotesite->get_image_url($type, $itemid);

        if ($contents = @file_get_contents($url)) {
            if ($fp = @fopen($CFG->dataroot.'/reader/images/'.$filename, 'w+')) {
                @fwrite($fp, $contents);
                @fclose($fp);
            }
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

    /** the default download url for this remote site */
    const DEFAULT_URL = '';

    /** the basic connection parameters */
    public $baseurl = '';
    public $username = '';
    public $password = '';

    /**
     * __construct
     *
     * @param xxx $baseurl (optional, default='')
     * @param xxx $username (optional, default='')
     * @param xxx $password (optional, default='')
     * @todo Finish documenting this function
     */
    public function __construct($baseurl='', $username='', $password='') {
        $this->baseurl = ($baseurl ? $baseurl : self::DEFAULT_URL);
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * download_xml
     *
     * @uses $CFG
     * @param xxx $url
     * @param xxx $post (optional, default=false)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function download_xml($url, $post=false) {
        global $CFG;
        require_once($CFG->dirroot.'/lib/xmlize.php');
        if ($xml = $this->get_curlfile($url, $post)) {
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
        return $this->download_xml($url, $itemids);
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
        return $itemids;
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
     * @param xxx $itemid
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_image_params($itemid) {
        return null;
    }

    /**
     * get_curlfile
     *
     * @param xxx $url
     * @param xxx $params (optional, default=false)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_curlfile($url, $params=false) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        if ($params) {
            $postfields = array();
            foreach ($params as $name1 => $value1) {
                if (is_array($value1)) {
                    foreach ($value1 as $name2 => $value2) {
                        if (is_array($value2)) {
                            foreach ($value2 as $name3 => $value3) {
                                $postfields[] = $name1.'['.$name2.']['.$name3.']='.$value3;
                            }
                        } else {
                            $postfields[] = $name1.'['.$name2.']='.$value2;
                        }
                    }
                } else {
                    $postfields[] = $name1.'='.$value1;
                }
            }
            if ($postfields = implode('&', $postfields)) {
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
            }
        }

        $result = curl_exec($ch);
        curl_close($ch);

        if (empty($result)) {
            return false; // shouldn't happen !!
        }

        return $result;
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
    const DEFAULT_URL = 'http://moodlereader.net/quizbank';

    /**
     * get_publishers_url
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_publishers_url($type, $itemids) {
        if ($type==reader_downloader::BOOKS_WITH_QUIZZES) {
            $params = $this->get_publishers_params($type, $itemids);
            return new moodle_url($this->baseurl.'/index.php', $params);
        }
        if ($type==reader_downloader::BOOKS_WITHOUT_QUIZZES) {
            $params = $this->get_publishers_params($type, $itemids);
            return new moodle_url($this->baseurl.'/index-noq.php', $params);
        }
        return parent::get_publishers_url($type, $itemids); // shouldn't happen !!
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
        if ($type==reader_downloader::BOOKS_WITH_QUIZZES) {
            $params = $this->get_quizzes_params($type, $itemids);
            return new moodle_url($this->baseurl.'/index.php', $params);
        }
        if ($type==reader_downloader::BOOKS_WITHOUT_QUIZZES) {
            $params = $this->get_quizzes_params($type, $itemids);
            return new moodle_url($this->baseurl.'/index-noq.php', $params);
        }
        return parent::get_quizzes_url($type, $itemids); // shouldn't happen !!
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
        return array('quiz' => $itemids);
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
        if ($type==reader_downloader::BOOKS_WITH_QUIZZES) {
            $params = $this->get_quizzes_params($type, $itemid);
            return new moodle_url($this->baseurl.'/getfile.php', $params);
        }
        if ($type==reader_downloader::BOOKS_WITHOUT_QUIZZES) {
            $params = $this->get_quizzes_params($type, $itemid);
            return new moodle_url($this->baseurl.'/getfilenoq.php', $params);
        }
        return parent::get_image_url($type, $itemid); // shouldn't happen !!
    }

    /**
     * get_image_params
     *
     * @param xxx $itemid
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_image_params($itemid) {
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
            $anum  = intval($matches[2]);
        } else {
            $anum = 0;
        }

        // get filtered name (b)
        $bname = preg_replace_callback($search2, $replace2, preg_replace($search1, $replace1, $b));
        if (preg_match($split, $bname, $matches)) {
            $bname = trim($matches[1]);
            $bnum  = intval($matches[2]);
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
