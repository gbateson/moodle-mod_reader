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

    /** does this remote site have an API for taking quizzes remotely */
    const HAS_QUIZ_API = false;

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
     * download_bookcovers
     *
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */
    public function download_bookcovers($itemids) {
        $items = array();
        $params = array('a' => 'quizzes',
                        'login' => $this->username,
                        'password' => $this->password);
        $xml_file = new moodle_url($this->baseurl.'/index.php', $params);

        $params = array('password' => $this->password,
                        'quiz' => $itemids,
                        'upload' => 'true');
        $xml = reader_file($xml_file, $params);

        $xml = xmlize($xml);
        if (isset($xml['myxml']['#']['item'])) {
            $item = &$xml['myxml']['#']['item'];
            $i = 0;
            while (isset($item["$i"])) {
                if (isset($item["$i"]['@']['id']) && isset($item["$i"]['@']['image'])) {
                    $items[] = (object)array(
                        'id' => $item["$i"]['@']['id'],
                        'image' => $item["$i"]['@']['image']
                    );
                }
                $i++;
            }
        }
        return $items;
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
        $xml = $this->download_xml($url, $post);

        $items = array();
        if (empty($xml) || empty($xml['myxml']) || empty($xml['myxml']['#']) || empty($xml['myxml']['#']['item'])) {
            // shouldn't happen !!
        } else {
            foreach ($xml['myxml']['#']['item'] as $item) {

                if (empty($item) || empty($item['#']) || empty($item['@'])) {
                    continue; // shouldn't happen !!
                }

                // sanity check on expected fields
                if (! isset($item['@']['publisher'])) {
                    continue;
                }
                if (! isset($item['@']['level'])) {
                    continue;
                }
                if (! isset($item['@']['id'])) {
                    continue;
                }

                // append this $item to $items array
                $items[] = (object)array(
                    'publisher' => trim($item['@']['publisher']),
                    'level'     => trim($item['@']['level']),
                    'id'        => trim($item['@']['id']),
                    'title'     => trim($item['#']),
                    'time'      => (empty($item['@']['time']) ? 0 : intval($item['@']['time']))
                );
            }
        }
        return $items;
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
        $xml = $this->download_xml($url, $post);

        $items = array();

        if (empty($xml) || empty($xml['myxml']) || empty($xml['myxml']['#']) || empty($xml['myxml']['#']['item'])) {
            // shouldn't happen !!
        } else {
            foreach ($xml['myxml']['#']['item'] as $item) {

                if (empty($item) || empty($item['#']) || empty($item['@'])) {
                    continue; // shouldn't happen !!
                }

                // sanity check on expected fields
                if (! isset($item['@']['publisher'])) {
                    continue;
                }
                if (! isset($item['@']['level'])) {
                    continue;
                }
                if (! isset($item['@']['id'])) {
                    continue;
                }

                // convert main value from "path" to "title"
                if ($pos = strrpos($item['#'], '/')) {
                    $item['#'] = substr($item['#'], $pos + 1);
                    $item['#'] = strtr($item['#'], array('_' => ' ', '.xml.gz' => ''));
                }

                // populate "title" field, if necessary
                if (empty($item['@']['title'])) {
                    $item['@']['title'] = $item['#'];
                }

                // rename deprecated fields
                $fields = array('length' => 'points');
                foreach ($fields as $oldname => $newname) {
                    if (isset($item['@'][$oldname])) {
                        if (! isset($item['@'][$newname])) {
                            $item['@'][$newname] = $item['@'][$oldname];
                        }
                        unset($item['@'][$oldname]);
                    }
                }

                // "publisher", "title" and "id" are all required. "level" can be empty
                if ($item['@']['publisher'] && $item['@']['title'] && $item['@']['id']) {
                    $items[] = (object)$item['@'];
                }
            }
        }
        return $items;
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
