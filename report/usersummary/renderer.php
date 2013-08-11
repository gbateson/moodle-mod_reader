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
 * mod/reader/report/usersummary/renderer.php
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
 * mod_reader_report_usersummary_renderer
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_report_usersummary_renderer extends mod_reader_report_renderer {

    public $mode = 'usersummary';

    public $tablecolumns = array(
        'selected', 'username', 'fullname', // , 'picture'
        'startlevel', 'currentlevel', 'nopromote',
        'countpassed', 'countfailed', 'wordsthisterm', 'wordsallterms'
    );

    public $filterfields = array(
        'group' => 0, 'realname'=>0, 'lastname'=>1, 'firstname'=>1, 'username'=>1,
        //'startlevel' => 1, 'currentlevel' => 1, 'nopromote' => 1,
        //'countpassed' => 1, 'countfailed' => 1, 'countwords' => 1
    );

    /*
     * get_tablecolumns
     *
     * @return array of column names
     */
    public function get_tablecolumns() {
        global $DB;

        $tablecolumns = parent::get_tablecolumns();

        // add "goal" column if required
        $select = 'readerid = :readerid AND goal IS NOT NULL AND goal > :zero';
        $params = array('readerid' => $this->reader->id, 'zero' => 0);
        if ($this->reader->goal || $DB->record_exists_select('reader_goal', $select, $params) || $DB->record_exists_select('reader_levels', $select, $params)) {
            $tablecolumns[] = 'goal';
        }

        return $tablecolumns;
    }

    /**
     * count_sql
     *
     * @param xxx $userid (optional, default=0)
     * @param xxx $attemptid (optional, default=0)
     * @return xxx
     */
    function count_sql($userid=0, $attemptid=0) {

        // get users who can access this Reader activity
        list($where, $params) = $this->select_sql_users();

        $select = 'COUNT(*)';
        $from   = '{user} u';
        $where  = "id $where";

        $userfields = '';
        return $this->add_filter_params($userfields, $userid, $attemptid, $select, $from, $where, $params);
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

        // get users who can access this Reader activity
        list($usersql, $userparams) = $this->select_sql_users();

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
