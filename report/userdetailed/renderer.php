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
 * mod/reader/report/userdetailed/renderer.php
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
require_once($CFG->dirroot.'/mod/reader/report/renderer.php');

/**
 * mod_reader_report_userdetailed_renderer
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_report_userdetailed_renderer extends mod_reader_report_renderer {

    public $mode = 'userdetailed';

    public $tablecolumns = array(
        'username', 'fullname', 'userlevel', // , 'picture'
        'selected', 'booklevel', 'booktitle', 'timefinish', 'percentgrade', 'passed', 'words', 'totalwords',
    );

    public $filterfields = array(
        'group' => 0, 'realname'=>0, 'lastname'=>1, 'firstname'=>1, 'username'=>1,
        //'currentlevel' => 1, 'booklevel' => 1, 'booktitle' => 1,
        //'percentgrade' => 1, 'passed' => 1, 'words' => 1
    );

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
        $params = array('reader' => $this->reader->id, 'time' => $this->reader->ignoredate);

        $userfields = '';
        return $this->add_filter_params($userfields, $userid, $attemptid, $select, $from, $where, $params + $userparams);
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

        $params = array('reader' => $this->reader->id, 'readerid' => $this->reader->id, 'time' => $this->reader->ignoredate, 'passed' => 'true');

        $userfields = '';
        return $this->add_filter_params($userfields, $userid, $attemptid, $select, $from, $where, $params + $userparams);
    }
}
