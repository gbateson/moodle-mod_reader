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
 * mod/reader/quiz/mreader.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2018 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die();

/**
 * reader_mreader
 *
 * @copyright  2018 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class reader_site {
    /**#@+
     * URI's of scripts on external server
     *
     * @var string
     */
    const REMOTE_START_SCRIPT    = '';
    const REMOTE_CONTINUE_SCRIPT = '';
    const REMOTE_FINISH_SCRIPT   = '';

    /**#@+
     * length of randomly generated strings
     *
     * @var string
     */
    const LENGTH_UNIQUEID  = 8;
    const LENGTH_FIRSTNAME = 2;
    const LENGTH_LASTNAME  = 4;

    /** an object to represent the reader attempt */
    public $attempt = null;

    /** base URL of the external site */
    public $baseurl = '';

    /** The numeric ID under which this Moodle site is registered on the external site  */
    public $siteid = 0;

    /** The secret key required to verify the site id  */
    public $sitekey = '';

    /** The id and image file name of the required book quiz  */
    public $bookid = 0;
    public $bookimage = '';

    /** object holding unique id and name for the current user */
    public $user = null;

    /** the start time for this quiz */
    public $time = 0;

    /**
     * __construct
     *
     * @param xxx $url (optional, default='')
     * @param xxx $siteid (optional, default = 0)
     * @param xxx $key (optional, default='')
     * @todo Finish documenting this function
     */
    public function __construct($attempt, $baseurl='', $siteid=0, $sitekey='') {
        $this->attempt = $attempt;
        $this->baseurl = $baseurl;
        $this->siteid  = $siteid;
        $this->sitekey = $sitekey;
        $this->time = time();
    }

    /**
     * get_user_uniqueid
     *
     * @todo Finish documenting this function
     */
    public function get_user_uniqueid() {
    	return $this->get_user('uniqueid');
    }

    /**
     * get_user_uniquename
     *
     * @todo Finish documenting this function
     */
    public function get_user_uniquename() {
    	return $this->get_user('uniquename');
    }

    /**
     * get_user
     *
     * @todo Finish documenting this function
     */
    public function get_user($field='') {
        global $DB, $USER;
        if ($this->user===null) {
        	$this->user = $DB->get_record('reader_users', array('userid' => $USER->id));
			if ($this->user===false) {
				$this->user = (object)array('userid' => $USER->id,
									        'uniqueid' => $this->generate_uniqueid(),
									        'uniquename' => $this->generate_uniquename());
				$this->user->id = $DB->insert_record('reader_users', $this->user);
			}
        }
        if ($field=='') {
			return $this->user;
        } else {
        	return $this->user->$field;
        }
    }

    /**
     * generate_uniqueid
     *
     * @todo Finish documenting this function
     */
    public function generate_uniqueid() {
        global $DB;
        $id = random_string(self::LENGTH_UNIQUEID);
        while ($DB->record_exists('reader_users', array('uniqueid' => $id))) {
			$id = random_string(self::LENGTH_UNIQUEID);
        }
        return $id;
    }

    /**
     * generate_uniquename
     *
     * @todo Finish documenting this function
     */
    public function generate_uniquename() {
        return random_string(self::LENGTH_FIRSTNAME).' '.random_string(self::LENGTH_LASTNAME);
    }

    /**
     * start_url
     *
     * @todo Finish documenting this function
     */
    public function start_url() {
    	$url = $this->baseurl.$this::REMOTE_START_SCRIPT;
        $url = new moodle_url($url, $this->start_params());
        if ($token = $this->generate_token($url)) {
        	$url->param('token', $token);
        }
        return $url;
    }

    /**
     * start_params
     *
     * @todo Finish documenting this function
     */
    public function start_params() {
        return array();
    }

    /**
     * generate_token
     *
     * @todo Finish documenting this function
     */
    public function generate_token($url) {
        return '';
    }

    /**
     * view_url
     *
     * @todo Finish documenting this function
     */
    public function view_url() {
    	$params = array('r' => $this->attempt->readerid);
        return new moodle_url('/mod/reader/view.php', $params);
    }

    /**
     * processattempt_url
     *
     * @todo Finish documenting this function
     */
    public function processattempt_url() {
    	$params = array('attempt' => $this->attempt->id,
    					'sesskey' => sesskey(),
    					'thispage' => -1); // force end of attempt
        return new moodle_url('/mod/reader/quiz/processattempt.php', $params);
    }

    /**
     * summary_url
     *
     * @todo Finish documenting this function
     */
    public function summary_url() {
    	$params = array('attempt' => $this->attempt->id);
        return new moodle_url('/mod/reader/quiz/summary.php', $params);
    }
}

/**
 * reader_mreader
 *
 * @copyright  2018 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class reader_site_mreader extends reader_site {

    /**#@+
     * URI's of scripts on external server
     *
     * @var string
     */
    const REMOTE_START_SCRIPT    = '/api_takequiz.php';
    const REMOTE_CONTINUE_SCRIPT = '/api_quizzes.php';
    const REMOTE_FINISH_SCRIPT   = '/api_completequiz.php';

    /**
     * __construct
     *
     * @param xxx $url (optional, default='')
     * @param xxx $siteid (optional, default = 0)
     * @param xxx $key (optional, default='')
     * @todo Finish documenting this function
     */
    public function __construct($attempt, $baseurl='', $siteid=0, $sitekey='') {
        $config = get_config('mod_reader');
        parent::__construct($attempt, 
        					$baseurl ? $baseurl : (empty($config->mreaderurl) ? '' : $config->mreaderurl),
                            $siteid  ? $siteid  : (empty($config->mreadersiteid) ? '' : $config->mreadersiteid),
                            $sitekey ? $sitekey : (empty($config->mreadersitekey) ? '' : $config->mreadersitekey));
    }

    /**
     * start_params
     *
     * @todo Finish documenting this function
     */
    public function start_params() {
    	global $DB;
        return array(
        	't' => $this->time,
        	'sid' => get_config('mod_reader', 'mreadersiteid'),
        	'book' => $DB->get_field('reader_books', 'image', array('id' => $this->attempt->bookid)),
        	'uname' => $this->get_user_uniqueid(), // a fake, but unique, username
        	'sname' => $this->get_user_uniquename(), // a fake first and last name
        	'custom_id' => $this->attempt->id
        );
    }

    /**
     * generate_token
     *
     * @todo Finish documenting this function
     */
    public function generate_token($url) {
    	$query = $url->get_query_string(false); // & instead of '&amp;'
        return md5($query.get_config('mod_reader', 'mreadersitekey'));
    }
}
