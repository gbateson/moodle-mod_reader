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
 * Create a table to display attempts at a Reader activity
 *
 * @package   mod-reader
 * @copyright 2013 Gordon Bateson <gordon.bateson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// get parent class
require_once($CFG->dirroot.'/mod/reader/report/tablelib.php');

/**
 * reader_report_bookdetailed_table
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_report_bookdetailed_table extends reader_report_table {

    /** @var columns used in this table */
    protected $tablecolumns = array(
        'publisher', 'level', 'booktitle', 'booklevel',
        'selected', 'username', 'fullname', 'passed', 'bookrating'
    );

    /** @var suppressed columns in this table */
    protected $suppresscolumns = array('publisher', 'level', 'booktitle', 'booklevel');

    /** @var columns in this table that are not sortable */
    protected $nosortcolumns = array();

    /** @var text columns in this table */
    protected $textcolumns = array('publisher', 'level', 'booktitle', 'username', 'fullname');

    /** @var columns that are not to be center aligned */
    protected $leftaligncolumns = array('publisher', 'level', 'booktitle', 'username', 'fullname');

    /** @var default sort columns */
    protected $defaultsortcolumns = array('publisher' => SORT_ASC, 'level' => SORT_ASC, 'booktitle' => SORT_ASC, 'username' => SORT_ASC);

    ////////////////////////////////////////////////////////////////////////////////
    // functions to extract data from $DB                                         //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * count_sql
     *
     * @param xxx $userid (optional, default=0)
     * @param xxx $attemptid (optional, default=0)
     * @return xxx
     */
    function count_sql($userid=0, $attemptid=0) {
        // get users who can access this Reader activity
        list($usersql, $userparams) = $this->select_sql_users();

        $select = 'COUNT(*)';
        $from   = '{reader_attempts} ra LEFT JOIN {user} u ON ra.userid = u.id';
        $where  = "ra.reader = :reader AND ra.timefinish > :time AND ra.userid $usersql";
        $params = array('reader' => $this->output->reader->id, 'time' => $this->output->reader->ignoredate);

        return $this->add_filter_params($select, $from, $where, '', '', $params + $userparams);
    }

    /**
     * select_sql
     *
     * @uses $DB
     * @param xxx $userid (optional, default=0)
     * @param xxx $attemptid (optional, default=0)
     * @return xxx
     */
    function select_sql($userid=0, $attemptid=0) {

        // get users who can access this Reader activity
        list($usersql, $userparams) = $this->select_sql_users();

        $select = 'ra.id, ra.passed, ra.bookrating, '.
                  'u.id AS userid, u.username, u.firstname, u.lastname, u.picture, u.imagealt, u.email, '.
                  'rb.name AS booktitle, rb.publisher, rb.level, rb.difficulty AS booklevel';
        $from   = '{reader_attempts} ra '.
                  'LEFT JOIN {user} u ON ra.userid = u.id '.
                  'LEFT JOIN {reader_books} rb ON ra.quizid = rb.quizid';
        $where  = "ra.reader = :reader AND ra.timefinish > :time AND u.id $usersql";

        $sortby = 'rb.name, rb.publisher, u.username';

        $params = array('reader' => $this->output->reader->id, 'time' => $this->output->reader->ignoredate);

        return $this->add_filter_params($select, $from, $where, '', '', $params + $userparams);
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format header cells                                           //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * header_bookrating
     *
     * @return xxx
     */
    public function header_bookrating() {
        return get_string('bookrating', 'reader');
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format data cells                                             //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * col_bookrating
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_bookrating($row)  {
        return $this->img_bookrating($row->bookrating);
    }
}
