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
 * mod/reader/admin/reports/bookdetailed/tablelib.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/**
 * Create a table to display attempts at a Reader activity
 *
 * @package   mod-reader
 * @copyright 2013 Gordon Bateson <gordon.bateson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// get parent class

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die();

/** Include required files */
require_once($CFG->dirroot.'/mod/reader/admin/reports/tablelib.php');

/**
 * reader_admin_reports_bookdetailed_table
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_admin_reports_bookdetailed_table extends reader_admin_reports_table {

    /** @var columns used in this table */
    protected $tablecolumns = array(
        'publisher', 'level', 'name', 'difficulty', 'words', 'points',
        'selected', 'username', 'fullname', 'timefinish', 'duration', 'grade', 'passed', 'bookrating'
    );

    /** @var suppressed columns in this table */
    protected $suppresscolumns = array('publisher', 'level', 'name', 'difficulty', 'words', 'points');

    /** @var columns in this table that are not sortable */
    protected $nosortcolumns = array();

    /** @var text columns in this table */
    protected $textcolumns = array('publisher', 'level', 'name', 'username', 'fullname');

    /** @var number columns in this table */
    protected $numbercolumns = array('difficulty');

    /** @var columns that are not to be center aligned */
    protected $leftaligncolumns = array('publisher', 'level', 'name', 'username', 'fullname');

    /** @var default sort columns */
    protected $defaultsortcolumns = array('publisher' => SORT_ASC, 'level' => SORT_ASC, 'name' => SORT_ASC, 'username' => SORT_ASC);

    /** @var filter fields ($fieldname => $advanced) */
    protected $filterfields = array(
        // 'group'   => 0,
        'publisher'  => 0, 'level'     => 1, 'name'     => 0, 'difficulty' => 1,
        'username'   => 1, 'firstname' => 1, 'lastname' => 1,
        'timefinish' => 1, 'duration'  => 1, 'grade'    => 1,
        'passed'     => 1, 'cheated'   => 1, 'credit'   => 1, 'bookrating' => 1
    );

    protected $optionfields = array('termtype'    => self::DEFAULT_TERMTYPE,
                                    'rowsperpage' => self::DEFAULT_ROWSPERPAGE,
                                    'showhidden'  => self::DEFAULT_SHOWHIDDEN,
                                    'showdeleted' => self::DEFAULT_SHOWDELETED,
                                    'sortfields'  => array());

    /** @var actions */
    protected $actions = array('deleteattempts', 'restoreattempts', 'updatepassed', 'updatecheated', 'sendmessage');

    ////////////////////////////////////////////////////////////////////////////////
    // functions to extract data from $DB                                         //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * Constructor
     *
     * @param int $uniqueid
     */
    public function __construct($uniqueid, $output) {
        $wordsfields = array('words');
        $pointsfields = array('points');
        $this->fix_words_or_points_fields($output, $wordsfields, $pointsfields);
        parent::__construct($uniqueid, $output);
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

        if ($this->output->reader->wordsorpoints==0) {
            $wordsorpoints = 'rb.words';
        } else {
            $wordsorpoints = 'rb.points';
        }

        $exclude = 'ra.timefinish IS NULL OR ra.timefinish = 0';
        $duration = "CASE WHEN ($exclude) THEN 0 ELSE (ra.timefinish - ra.timestart) END";

        $exclude = 'ra.percentgrade IS NULL';
        $grade = "CASE WHEN ($exclude) THEN 0 ELSE ra.percentgrade END";

        $select = 'ra.id, ra.passed, ra.cheated, ra.timefinish, ra.layout, '.
                  "($duration) AS duration, ($grade) AS grade, ra.bookrating, ".
                  $this->get_userfields('u', array('username'), 'userid').', '.
                  'rb.publisher, rb.level, rb.name, rb.difficulty, '.$wordsorpoints;
        $from   = '{reader_attempts} ra '.
                  'LEFT JOIN {user} u ON ra.userid = u.id '.
                  'LEFT JOIN {reader_books} rb ON ra.bookid = rb.id';
        $where  = 'ra.readerid = :readerid AND ra.timefinish IS NOT NULL';
        $params = array('readerid' => $this->output->reader->id);

        $termtype = $this->filter->get_optionvalue('termtype');
        if ($termtype==reader_admin_reports_options::THIS_TERM) {
            $where .= ' AND ra.timefinish >= :time';
            $params['time'] = $this->output->reader->ignoredate;
        }

        if ($usersql) {
            $where .= " AND u.id $usersql";
        }

        $sortby = 'rb.name, rb.publisher, u.username';

        if ($this->output->reader->bookinstances) {
            $from  .= ' LEFT JOIN {reader_book_instances} rbi ON rb.id = rbi.bookid';
            $where .= ' AND rbi.id IS NOT NULL AND rbi.readerid = :rbireader';
            $params['rbireader'] = $this->output->reader->id;
        }

        return $this->add_filter_params($select, $from, $where, '', '', '', $params + $userparams);
    }

    /**
     * get_table_name_and_alias
     *
     * @param string $fieldname
     * @return array($tablename, $tablealias, $jointype, $jointable, $joinconditions)
     * @todo Finish documenting this function
     */
    public function get_table_name_and_alias($fieldname) {
        switch ($fieldname) {

            // "reader_levels" fields
            case 'publisher':
            case 'level':
            case 'name':
            case 'difficulty':
                return array('reader_books', 'rb');

            // "reader_attempts" aggregate fields
            case 'passed':
            case 'cheated':
            case 'bookrating':
                return array('reader_attempts', 'ra');

            default:
                return parent::get_table_name_and_alias($fieldname);
        }
    }

    /**
     * records_exist
     */
    public function records_exist() {
        return $this->books_exist();
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
        return get_string('bookrating', 'mod_reader');
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
