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
 * reader_report_userdetailed_table
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_report_userdetailed_table extends reader_report_table {

    /** @var columns used in this table */
    protected $tablecolumns = array(
        'username', 'fullname', 'userlevel', // , 'picture'
        'selected', 'booklevel', 'booktitle', 'timefinish', 'percentgrade', 'passed', 'words', 'totalwords',
    );

    /** @var suppressed columns in this table */
    protected $suppresscolumns = array('username', 'fullname', 'userlevel');

    /** @var columns in this table that are not sortable */
    protected $nosortcolumns = array();

    /** @var text columns in this table */
    protected $textcolumns = array('username', 'fullname', 'booktitle');

    /** @var columns that are not to be center aligned */
    protected $leftaligncolumns = array('username', 'fullname', 'booktitle');

    /** @var default sort columns */
    protected $defaultsortcolumns = array('username' => SORT_ASC, 'lastname' => SORT_ASC, 'firstname' => SORT_ASC, 'booktitle' => SORT_ASC); // timefinish => SORT_DESC

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

        $words  = 'CASE WHEN (ra.passed = :passed) THEN rb.words ELSE 0 END';
        $select = "ra.id, ra.timefinish, ra.percentgrade, ra.passed, ($words) AS words, 0 AS totalwords, ".
                  'u.id AS userid, u.username, u.firstname, u.lastname, u.picture, u.imagealt, u.email, '.
                  'rl.currentlevel AS userlevel, rb.difficulty AS booklevel, rb.name AS booktitle';
        $from   = '{reader_attempts} ra '.
                  'LEFT JOIN {user} u ON ra.userid = u.id '.
                  'LEFT JOIN {reader_levels} rl ON u.id = rl.userid '.
                  'LEFT JOIN {reader_books} rb ON ra.quizid = rb.quizid';
        $where  = "ra.reader = :reader AND rl.readerid = :readerid AND ra.timefinish > :time AND u.id $usersql";

        $params = array('reader' => $this->output->reader->id, 'readerid' => $this->output->reader->id, 'time' => $this->output->reader->ignoredate, 'passed' => 'true');

        return $this->add_filter_params($select, $from, $where, '', '', $params + $userparams);
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format header cells                                           //
    ////////////////////////////////////////////////////////////////////////////////

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format data cells                                             //
    ////////////////////////////////////////////////////////////////////////////////
}
