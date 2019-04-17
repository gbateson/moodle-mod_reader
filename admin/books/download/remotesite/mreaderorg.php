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
 * mod/reader/admin/books/download/remotesite/mreaderorg.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die();

/** get required libraries */
require_once($CFG->dirroot.'/mod/reader/quiz/mreaderlib.php');

/**
 * reader_remotesite_mreaderorg
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class reader_remotesite_mreaderorg extends reader_remotesite {

    const DEFAULT_BASEURL = 'https://mreader.org';
    const DEFAULT_SITENAME = 'mReader.org Quiz API';

    /** does this remote site have an API for taking quizzes remotely */
    const HAS_QUIZ_API = true;

    /**
     * get_publishers_url
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */

    /**
     * get_publishers_params
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */

    /**
     * get_items_url
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */

    /**
     * get_items_params
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */

    /**
     * download_quizzes
     *
     * @param xxx $type
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */
    public function download_quizzes($type, $itemids) {
        $items = array();
        $mreader = new reader_site_mreader();
        $url = $mreader->get_items_url($type);
        if ($itemids) {
            $params = array('ids' => $itemids);
        } else {
            $params = null;
        }
        if ($results = $this->download_json($url, $params, self::curl_options())) {
            $items = array_values(get_object_vars($results));
        }
        return $items;
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
        $mreader = new reader_site_mreader();
        $url = $mreader->get_image_url($type);
        return $url->out(false); // convert &amp; to &
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
        return array('imageid' => $itemid);
    }

    /**
     * download_bookcovers
     *
     * @param xxx $itemids
     * @return xxx
     * @todo Finish documenting this function
     */
    public function download_bookcovers($itemids) {
        $mreader = new reader_site_mreader();
        $url = $mreader->get_bookcovers_url();
        if ($itemids) {
            $params = array('ids' => $itemids);
        } else {
            $params = null;
        }
        if ($results = $this->download_json($url, $params, self::curl_options())) {
            return $results;
        }
        return array();
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
        $items = array();
        $mreader = new reader_site_mreader();
        $url = $mreader->get_items_url($type);
        if ($itemids) {
            $params = array('ids' => $itemids);
        } else {
            $params = null;
        }
        if ($results = $this->download_json($url, $params, self::curl_options())) {
            foreach ($results as $result) {
                $items[] = (object)array(
                    'id'        => $result->id,
                    'publisher' => $result->publisher,
                    'level'     => $result->level,
                    'title'     => $result->title,
                    'time'      => $result->time
                );
            }
        }
        return $items;
    }

    /*
     * get_usage_url
     *
     * @return string
     */

    /*
     * get_usage_post
     *
     * @param array $usage
     * @return array
     */

    /**
     * get_usage
     *
     * @param boolean $include_unused_books (optional, default=FALSE) if TRUE, return details of unused books
     * @todo Finish documenting this function
     */

    /*
     * get_config
     *
     * @param string $username on moodlereader.com
     * @param string $password for $username
     * @return object OR null
     */
    static public function get_config($username, $password) {
        global $CFG;
        $result = null;
        if ($username && $password) {
            $mreader = new reader_site_mreader(null, self::DEFAULT_BASEURL);
            $url = $mreader->siteid_url($CFG->wwwroot.'/mod/reader/', $username, $password);
            // try to get siteid for this $username + $password
            // If $username + $password are NOT valid, then $result is as NULL
            // Otherwise, if a siteid/key pair is already exists on moodlereader.com, it will be returned
            // Otherwise a NEW siteid/key will be created on moodlereader.com and returned in the $results
            if ($results = self::get_curl_post($url)) {
                $results->url = self::DEFAULT_BASEURL;
            }
            return $results;
        }
    }

    /*
     * get_curl_post
     *
     * @return array
     */
    static public function get_curl_post($url) {
        $curl = new curl();
        $curl->setHeader(array('Accept: application/json', 'Expect:'));
        if ($result = $curl->post($url->out_omit_querystring(), $url->params(), self::curl_options())) {
            $result = json_decode($result);
        }
        return $result;
    }

    /*
     * curl_options
     *
     * @return array
     */
    static public function curl_options() {
        return array(
            'FRESH_CONNECT'     => true,
            'RETURNTRANSFER'    => true,
            'FORBID_REUSE'      => true,
            'HEADER'            => 0,
            'CONNECTTIMEOUT'    => 3,
            // Follow redirects with the same type of request when sent 301, or 302 redirects.
            'CURLOPT_POSTREDIR' => 3
        );
    }
}
