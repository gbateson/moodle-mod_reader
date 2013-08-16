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

    protected $tablecolumns = array(
        'groupname',
        'countactive', // number of students who have taken quizzes
        'countinactive', // number of students who hove NOT taken quizzes
        'percentactive', // percent of students who have taken quizzes
        'percentinactive', // percent of students who have NOT taken quizzes
        'averagetaken',  // average number of quizzes taken
        'averagepassed', // average number of quizzes passed
        'averagefailed', // average number of quizzes failed
        'averagepercentgrade', // average percent grade average
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
        list($attemptsql, $attemptparams) = $this->select_sql_attempts('userid');

        $select = 'g.id AS groupid, g.name AS groupname,'.
                  'COUNT(u.id) AS countusers,'.
                  'SUM(CASE WHEN (raa.userid IS NOT NULL AND (raa.countpassed > 0 OR raa.countfailed > 0)) THEN 1 ELSE 0 END) AS countactive,'.
                  'SUM(CASE WHEN (raa.userid IS NOT NULL AND (raa.countpassed > 0 OR raa.countfailed > 0)) THEN 0 ELSE 1 END) AS countinactive,'.
                  'SUM(raa.countpassed) AS countpassed,'.
                  'SUM(raa.countfailed) AS countfailed,'.
                  'SUM(raa.averagegrade) AS sumaveragegrade,'.
                  'SUM(raa.wordsthisterm) AS wordsthisterm,'.
                  'SUM(raa.wordsallterms) AS wordsallterms';

        $from   = '{user} u '.
                  "LEFT JOIN ($attemptsql) raa ON u.id = raa.userid ".
                  'LEFT JOIN {groups_members} gm ON u.id = gm.userid '.
                  'LEFT JOIN {groups} g ON gm.groupid = g.id';

        $where  = 'g.courseid = :courseid GROUP BY g.id';

        $params = array('courseid' => $this->reader->course->id) + $attemptparams;

        return array($select, $from, $where, $params);
    }
}
