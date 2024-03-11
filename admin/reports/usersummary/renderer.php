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
 * mod/reader/admin/reports/usersummary/renderer.php
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
require_once($CFG->dirroot.'/mod/reader/admin/reports/usersummary/tablelib.php');
require_once($CFG->dirroot.'/mod/reader/admin/reports/usersummary/filtering.php');

/**
 * mod_reader_admin_reports_usersummary_renderer
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_reports_usersummary_renderer extends mod_reader_admin_reports_renderer {
    public $mode = 'usersummary';

    /**
     * available_extrapoints
     *
     * @uses $DB
     * @return xxx
     * @todo Finish documenting this function
     */
    public function available_extrapoints() {
        global $DB;
        $options = array();
        $params = array('publisher' => get_string('extrapoints', 'mod_reader'), 'level' => 99);
        if ($books = $DB->get_records('reader_books', $params, 'points', 'id,name,words,points')) {
            foreach ($books as $book) {
                $i = (float)$book->points;
                $options["$i"] = $book->name.' / '.get_string('extrawords', 'mod_reader', number_format($book->words));
            }
        } else {
            $i_max = 6;
            for ($i=0; $i<=$i_max; $i++) {
                $options["$i"] = get_string('extrapoints'.$i, 'mod_reader').' / '.get_string('extrawords', 'mod_reader', number_format(1000 * pow(2, $i-1)));
            }
        }
        return $options;
    }

    /**
     * get_tab
     *
     * @return integer tab id
     */
    public function get_tab() {
        return self::TAB_REPORTS_USERSUMMARY;
    }
}
