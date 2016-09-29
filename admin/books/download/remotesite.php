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
 * mod/reader/admin/books/download/remotesite.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die();

/** Include required files */
require_once($CFG->dirroot.'/lib/xmlize.php');

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
        static $namechars = array('"' => '', "'" => '', ',' => '', '&' => '', ' ' => '_');
        //return mt_rand(0,1);

        // get the last modified dates for the "publisher" folders
        if ($this->filetimes===null) {
            $this->filetimes = $this->get_remote_filetimes('/');
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

        // convert $url to unescaped URL string
        $url = $url->out(false);

        // get "full response" from CURL so that we can handle errors
        $response = download_file_content($url, $headers, $post, true);

        // check for $error
        if (empty($response->results)) {
            if (empty($response->error)) {
                $error = ' '; // a single space to trigger error report
            } else {
                $error = get_string('curlerror', 'mod_reader', $response->error);
            }
        } else {
            if ($error = $this->is_error_curl_xml($response->results)) {
                $error = get_string('servererror', 'mod_reader', $error);
            }
        }

        // report $error (and quit), if necessary
        if ($error) {
            $output = '';
            $output .= html_writer::tag('h3', get_string('cannotdownloadata', 'mod_reader'));
            if ($error = trim($error)) {
                $output .= html_writer::tag('p', $error);
            }
            if ($this->debugdeveloper()) {
                $output .= html_writer::tag('p', "URL: $url");
            }
            $output = $OUTPUT->notification($output);
            echo $OUTPUT->box($output, 'generalbox', 'notice');
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
     * is_error_curl_xml
     *
     * @param string $results downloaded via CURL
     * @return string
     * @todo Finish documenting this function
     */
    public function is_error_curl_xml($results) {
        return '';
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

            $defaults = array('id'              => 0,  'parent'             => 0,  'name'      => '',
                              'questiontext'    => '', 'questiontextformat' => 0,  'image'     => '',
                              'generalfeedback' => '', 'generalfeedbackformat' => 0,
                              'defaultgrade'    => 0,  'defaultscore'       => 0,  'penalty'   => 0, 'qtype'      => '',
                              'length'          => '', 'stamp'              => '', 'version'   => 0, 'hidden'     => '',
                              'timecreated'     => 0,  'timemodified'       => 0,  'createdby' => 0, 'modifiedby' => 0);

            // regular expressions to detect unwantedtags in questions text
            // - <script> ... </script>
            // - <style> ... </style>
            // - <!-- ... -->
            // - <pre> and </pre>
            $search = array('/\s*<(script|style|xml)\b[^>]*>.*?<\/\1>/is',
                            '/\s*(&lt;)!--.*?--(&gt;)/s',
                            '/\s*<\/?(link|meta|pre)\b[^>]*>/i');

            $question = $xml['0']['#']['QUESTION'];
            foreach (array_keys($question) as $q) {
                $questions[$q] = $this->get_xml_values($question["$q"]['#'], $defaults);
                $questions[$q]->questiontext = preg_replace($search, '', $questions[$q]->questiontext);
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
        $defaults = array('layouttype' => 0, // VERTICAL
                          'selecttype' => 1, // RANDOM
                          'selectcount' => 6,
                          'gradingtype' => 1, // RELATIVE_NEXT_EXCLUDE_LAST
                          'correctfeedback' => '', 'correctfeedbackformat' => 0,
                          'incorrectfeedback' => '', 'incorrectfeedbackformat' => 0,
                          'partiallycorrectfeedback' => '', 'partiallycorrectfeedbackformat' => 0);
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
        $defaults = array('id' => 0, 'question' => 0, // not required ?
                          'subquestions' => '', 'shuffleanswers' => 1, 'shownumcorrect' => 0,
                          'correctfeedback' => '', 'correctfeedbackformat' => 0,
                          'incorrectfeedback' => '', 'incorrectfeedbackformat' => 0,
                          'partiallycorrectfeedback' => '', 'partiallycorrectfeedbackformat' => 0);
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
     * get_xml_values_numerical
     *
     * @param xxx $xml (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_xml_values_numerical(&$xml) {
        $numerical = array();
        foreach (array_keys($xml) as $i) {
            $defaults = array('answer' => 0, 'tolerance' => 0);
            $values = $this->get_xml_values($xml["$i"]['#'], $defaults);
            $numerical[$values->answer] = $values;
        }
        return $numerical;
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

    /*
     * debugdeveloper
     *
     * @uses $CFG
     * @return boolean TRUE if site is in developer debugging mode; FALSE otherwise
     */
    public function debugdeveloper() {
        global $CFG;
        if (isset($CFG->debugdeveloper)) {
            // Moodle >= 2.6
            return $CFG->debugdeveloper;
        } else {
            // Moodle <= 2.5
            return (($CFG->debug & DEBUG_DEVELOPER)===DEBUG_DEVELOPER);
        }
    }

    /**
     * download_json
     *
     * @uses $CFG
     * @param xxx $url
     * @param xxx $post (optional, default=null)
     * @param xxx $headers (optional, default=null)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function download_json($url, $post=null, $headers=null) {
        global $OUTPUT;

        // convert $url to unescaped URL string
        $url = $url->out(false);

        // get "full response" from CURL so that we can handle errors
        $response = download_file_content($url, $headers, $post, true);

        // check for errors
        if (empty($response->results)) {
            return false;
        }

        $results = json_decode($response->results);
        if (function_exists('json_last_error')) {
            // PHP >= 5.3
            $ok = (json_last_error()==JSON_ERROR_NONE);
        } else {
            // PHP <= 5.2
            // (note this check is specific to the string we are expecting)
            $ok = (substr($results, 0, 1)=='{' && substr($results, -1)=='}');
        }

        if ($ok) {
            return $results;
        } else {
            return false;
        }
    }

    /*
     * send_usage_stats
     *
     * @return boolean TRUE if usage stats were sent, FALSE otherwise
     */
    public function send_usage_stats() {
        if ($usage = $this->get_usage()) {
            $url = $this->get_usage_url();
            $post = $this->get_usage_post($usage);
            return $this->download_json($url, $post);
        }
        return false; // no usage stats
    }

    /*
     * get_usage
     *
     * @return array
     */
    public function get_usage() {
        return null;
    }

    /*
     * get_usage_url
     *
     * @return string
     */
    public function get_usage_url() {
        return null;
    }

    /*
     * get_usage_post
     *
     * @return array
     */
    public function get_usage_post($usage) {
        return null;
    }
}
