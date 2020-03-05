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
 * mod/reader/admin/books/editsite/renderer.php
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
require_once($CFG->dirroot.'/mod/reader/admin/books/renderer.php');
require_once($CFG->dirroot.'/mod/reader/admin/books/editsite/tablelib.php');
require_once($CFG->dirroot.'/mod/reader/admin/books/editsite/filtering.php');

/**
 * mod_reader_admin_books_editsite_renderer
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_books_editsite_renderer extends mod_reader_admin_books_renderer {

    public $mode = 'editsite';

    /** the name of the form element that, if present, signifies content is to be downloaded */
    protected $download_param_name = 'download';

    /**
     * get_tab
     *
     * @return integer tab id
     */
    public function get_tab() {
        return self::TAB_BOOKS_EDITSITE;
    }

    /**
     * render_page
     *
     * @return string HTML output to display navigation tabs
     */
    public function render_page() {
        echo $this->page_report();
    }
}
