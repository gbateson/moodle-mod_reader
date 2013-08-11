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
 * mod/reader/report/groupsummary/renderer.php
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
 * mod_reader_report_groupsummary_renderer
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_report_groupsummary_renderer extends mod_reader_report_renderer {
    public $mode = 'groupsummary';

    public $tablecolumns = array(
        'groupname',
        'countactive', // number of students who have taken quizzes
        'countinactive', // number of students who hove NOT taken quizzes
        'percentactive', // percent of students who have taken quizzes
        'percentinactive', // percent of students who have NOT taken quizzes
        'averagetaken',  // average number of quizzes taken
        'averagepassed', // average number of quizzes passed
        'averagefailed', // average number of quizzes failed
        'averagepoints', // average number of points
        'averagewordsthisterm', // average number of words this term
        'averagewordsallterms'  // average number of words all terms
    );

    /**
     * count_sql
     *
     * @param xxx $userid (optional, default=0)
     * @param xxx $attemptid (optional, default=0)
     * @return xxx
     */
    function count_sql($userid=0, $attemptid=0) {
        $select = 'COUNT(*)';
        $from   = '{groups}';
        $where  = "courseid = :courseid";
        $params = array('courseid' => $this->reader->course->id);
        return array($select, $from, $where, $params);
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

        // get attempts at this Reader activity
        list($attemptsql, $attemptparams) = $this->select_sql_attempts();

        $words  = 'CASE WHEN (ra.passed = :passed) THEN rb.words ELSE 0 END';

        $select = 'u.id AS userid, u.username, u.firstname, u.lastname, u.picture, u.imagealt, u.email, '.
                  'rx.countpassed, rx.countfailed, rx.wordsthisterm, rx.wordsallterms,'.
                  'rl.startlevel, rl.currentlevel, rl.nopromote, 0 AS goal';
        $from   = '{user} u '.
                  "LEFT JOIN ($attemptsql) rx ON rx.userid = u.id ".
                  'LEFT JOIN {reader_levels} rl ON u.id = rl.userid';
        $where  = "rl.readerid = :readerid AND u.id $usersql";

        $params = $attemptparams + array('readerid' => $this->reader->id) + $userparams;

        $userfields = '';
        return $this->add_filter_params($userfields, $userid, $attemptid, $select, $from, $where, $params);
    }
}
