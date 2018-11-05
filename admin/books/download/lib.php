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
 * mod/reader/admin/books/download/lib.php
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
require_once($CFG->dirroot.'/mod/reader/lib.php');
require_once($CFG->dirroot.'/mod/reader/admin/books/download/downloader.php');
require_once($CFG->dirroot.'/mod/reader/admin/books/download/progress.php');
require_once($CFG->dirroot.'/mod/reader/admin/books/download/remotesite.php');
require_once($CFG->dirroot.'/mod/reader/admin/books/download/remotesite/moodlereadernet.php');
require_once($CFG->dirroot.'/mod/reader/admin/books/download/remotesite/mreaderorg.php');

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
