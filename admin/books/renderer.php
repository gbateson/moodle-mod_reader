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
defined('MOODLE_INTERNAL') || die();

/** Include required files */
require_once($CFG->dirroot.'/mod/reader/admin/renderer.php');
require_once($CFG->dirroot.'/mod/reader/admin/books/tablelib.php');
require_once($CFG->dirroot.'/mod/reader/admin/books/filtering.php');

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

    public $tab = 'books';
    public $mode = '';

    /**#@+
     * tab ids
     *
     * @var integer
     */
    const TAB_BOOKS_EDITSITE         = 41;
    const TAB_BOOKS_EDITCOURSE       = 42;
    const TAB_BOOKS_ADDBOOK          = 49;
    const TAB_BOOKS_DOWNLOAD_WITH    = 43;
    const TAB_BOOKS_DOWNLOAD_WITHOUT = 44;
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
        if ($this->reader->bookinstances) {
            return self::TAB_BOOKS_EDITCOURSE;
        } else {
            return self::TAB_BOOKS_EDITSITE;
        }
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

            $tab = self::TAB_BOOKS_EDITSITE;
            $mode = 'editsite';
            $params = array('id' => $cmid, 'tab' => $tab, 'mode' => $mode);
            $url = new moodle_url('/mod/reader/admin/books.php', $params);
            $tabs[] = new tabobject($tab, $url, get_string('books'.$mode, 'mod_reader'));

            if ($this->reader->bookinstances) {
                $tab = self::TAB_BOOKS_EDITCOURSE;
                $mode = 'editcourse';
                $params = array('id' => $cmid, 'tab' => $tab, 'mode' => $mode);
                $url = new moodle_url('/mod/reader/admin/books.php', $params);
                $tabs[] = new tabobject($tab, $url, get_string('books'.$mode, 'mod_reader'));
            }

            $tab = self::TAB_BOOKS_ADDBOOK;
            $mode = 'addbook';
            $params = array('id' => $cmid, 'tab' => $tab, 'mode' => $mode);
            $url = new moodle_url('/mod/reader/admin/books.php', $params);
            $tabs[] = new tabobject($tab, $url, get_string('books'.$mode, 'mod_reader'));

            $tab = self::TAB_BOOKS_DOWNLOAD_WITH;
            $mode = 'download';
            $type = reader_downloader::BOOKS_WITH_QUIZZES;
            $params = array('id' => $cmid, 'tab' => $tab, 'mode' => $mode, 'type' => $type);
            $url = new moodle_url('/mod/reader/admin/books.php', $params);
            $tabs[] = new tabobject($tab, $url, get_string($mode.'bookswithquizzes', 'mod_reader'));

            $tab = self::TAB_BOOKS_DOWNLOAD_WITHOUT;
            $mode = 'download';
            $type = reader_downloader::BOOKS_WITHOUT_QUIZZES;
            $params = array('id' => $cmid, 'tab' => $tab, 'mode' => $mode, 'type' => $type);
            $url = new moodle_url('/mod/reader/admin/books.php', $params);
            $tabs[] = new tabobject($tab, $url, get_string($mode.'bookswithoutquizzes', 'mod_reader'));
        }
        return $this->attach_tabs_subtree(parent::get_tabs(), parent::TAB_BOOKS, $tabs);
    }

    /**
     * get_standard_modes
     *
     * @param object $reader (optional, default=null)
     * @return string HTML output to display navigation tabs
     */
    static public function get_standard_modes($reader=null) {
        if ($reader && $reader->bookinstances) {
            return array('editcourse', 'editsite', 'addbook', 'download');
        } else {
            return array('editsite', 'addbook', 'download');
        }
    }
}
