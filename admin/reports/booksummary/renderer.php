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
 * mod/reader/admin/reports/booksummary/renderer.php
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
require_once($CFG->dirroot.'/mod/reader/admin/reports/renderer.php');
require_once($CFG->dirroot.'/mod/reader/admin/reports/booksummary/tablelib.php');
require_once($CFG->dirroot.'/mod/reader/admin/reports/booksummary/filtering.php');


/**
 * mod_reader_admin_reports_booksummary_renderer
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_reports_booksummary_renderer extends mod_reader_admin_reports_renderer {

    public $mode = 'booksummary';

    protected $filterfields = array(
        'group' => 0, 'realname'=>0, 'lastname'=>1, 'firstname'=>1, 'username'=>1,
        //'startlevel' => 1, 'currentlevel' => 1, 'allowpromotion' => 1,
        //'countpassed' => 1, 'countfailed' => 1, 'countwords' => 1
    );

    /**
     * get_tab
     *
     * @return integer tab id
     */
    public function get_tab() {
        return self::TAB_REPORTS_BOOKSUMMARY;
    }
}
