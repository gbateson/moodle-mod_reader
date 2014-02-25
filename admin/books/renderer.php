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
 * mod/reader/admin/books/renderer.php
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
require_once($CFG->dirroot.'/mod/reader/admin/renderer.php');

/**
 * mod_reader_admin_books_renderer
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_books_renderer extends mod_reader_admin_renderer {

    /**#@+
     * tab ids
     *
     * @var integer
     */
    const TAB_BOOKS_DOWNLOAD_WITH    = 31;
    const TAB_BOOKS_DOWNLOAD_WITHOUT = 32;
    const TAB_BOOKS_EDIT             = 33;
    /**#@-*/

    /**
     * get_my_tab
     *
     * @return integer tab id
     */
    public function get_my_tab() {
        return self::TAB_BOOKS;
    }

    /**
     * get_default_tab
     *
     * @return integer tab id
     */
    public function get_default_tab() {
        return self::TAB_BOOKS_DOWNLOAD_WITH;
    }

    /**
     * get_tabs
     *
     * @return string HTML output to display navigation tabs
     */
    public function get_tabs() {
        $tabs = array();
        $cmid = $this->reader->cm->id;
        if ($this->reader->can_managebooks()) {

            $tab = self::TAB_BOOKS_DOWNLOAD_WITH;
            $type = reader_downloader::BOOKS_WITH_QUIZZES;
            $params = array('id' => $cmid, 'tab' => $tab, 'type' => $type);
            $url = new moodle_url('/mod/reader/admin/download.php', $params);
            $tabs[] = new tabobject($tab, $url, get_string('downloadbookswithquizzes', 'reader'));

            $tab = self::TAB_BOOKS_DOWNLOAD_WITHOUT;
            $type = reader_downloader::BOOKS_WITHOUT_QUIZZES;
            $params = array('id' => $cmid, 'tab' => $tab, 'type' => $type);
            $url = new moodle_url('/mod/reader/admin/download.php', $params);
            $tabs[] = new tabobject($tab, $url, get_string('downloadbookswithoutquizzes', 'reader'));

            $tab = self::TAB_BOOKS_EDIT;
            $params = array('id' => $cmid, 'tab' => $tab, 'action' => 'editdetails');
            $url = new moodle_url('/mod/reader/admin/books.php', $params);
            $tabs[] = new tabobject($tab, $url, get_string('edit'));
        }
        return $this->attach_tabs_subtree(parent::get_tabs(), parent::TAB_BOOKS, $tabs);
    }
}
