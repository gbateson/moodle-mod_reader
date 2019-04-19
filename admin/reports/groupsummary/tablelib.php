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
 * mod/reader/admin/reports/groupsummary/tablelib.php
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
 * reader_admin_reports_groupsummary_table
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_admin_reports_groupsummary_table extends reader_admin_reports_table {

    /** @var columns used in this table */
    protected $tablecolumns = array(
        'groupname', 'selected',
        'countactive', // number of students who have taken quizzes
        'countinactive', // number of students who hove NOT taken quizzes
        'percentactive', // percent of students who have taken quizzes
        'percentinactive', // percent of students who have NOT taken quizzes
        'averagetaken',  // average number of quizzes taken
        'averagepassed', // average number of quizzes passed
        'averagefailed', // average number of quizzes failed
        'averagepercentgrade', // average percent grade average
        'averagewordsthisterm', // average number of words this term
        'averagewordsallterms', // average number of words all terms
        'averagepointsthisterm', // average number of points this term
        'averagepointsallterms'  // average number of points all terms
    );

    /** @var suppressed columns in this table */
    protected $suppresscolumns = array();

    /** @var columns in this table that are not sortable */
    protected $nosortcolumns = array('percentactive', 'percentinactive',
                                     'averagetaken' , 'averagepassed', 'averagefailed', 'averagepercentgrade',
                                     'averagewordsthisterm', 'averagewordsallterms',
                                     'averagepointsthisterm', 'averagepointsallterms');

    /** @var text columns in this table */
    protected $textcolumns = array('groupname');

    /** @var number columns in this table */
    protected $numbercolumns = array('countactive', 'countinactive', 'averagetaken', 'averagepassed', 'averagefailed',
                                     'averagewordsthisterm', 'averagewordsallterms', 'averagepointsthisterm', 'averagepointsallterms');

    /** @var columns that are not to be center aligned */
    protected $leftaligncolumns = array('groupname');

    /** @var default sort columns */
    protected $defaultsortcolumns = array('groupname' => SORT_ASC);

    /** @var filter fields ($fieldname => $advanced) */
    protected $filterfields = array(
        //'groupname'   => 0,
        'countactive'   => 1, 'countinactive'   => 1,
        'percentactive' => 1, 'percentinactive' => 1,
        'averagetaken'  => 1, 'averagepassed'   => 1, 'averagefailed' => 1, 'averagepercentgrade' => 1,
        'averagewordsthisterm' => 1, 'averagewordsallterms' => 1,
        'averagepointsthisterm' => 1, 'averagepointsallterms' => 1
    );

    /** @var option fields */
    protected $optionfields = array('termtype'    => self::DEFAULT_TERMTYPE,
                                    'rowsperpage' => self::DEFAULT_ROWSPERPAGE,
                                    'sortfields'  => array());

    /** @var actions */
    //protected $actions = array('setreadinggoal', 'setmessage');

    ////////////////////////////////////////////////////////////////////////////////
    // functions to extract data from $DB                                         //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * Constructor
     *
     * @param int $uniqueid
     */
    public function __construct($uniqueid, $output) {
        $wordsfields = array('averagewordsthisterm', 'averagewordsallterms');
        $pointsfields = array('averagepointsthisterm', 'averagepointsallterms');
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

        // get attempts at this Reader activity
        list($attemptsql, $attemptparams) = $this->select_sql_attempts('userid');

        if ($this->output->reader->wordsorpoints==0) {
            $totalthisterm = 'totalwordsthisterm';
            $totalallterms = 'totalwordsallterms';
        } else {
            $totalthisterm = 'totalpointsthisterm';
            $totalallterms = 'totalpointsallterms';
        }

        $select = 'g.id AS groupid, g.name AS groupname,'.
                  'COUNT(u.id) AS countusers,'.
                  'SUM(CASE WHEN (raa.userid IS NOT NULL AND (raa.countpassed > 0 OR raa.countfailed > 0)) THEN 1 ELSE 0 END) AS countactive,'.
                  'SUM(CASE WHEN (raa.userid IS NOT NULL AND (raa.countpassed > 0 OR raa.countfailed > 0)) THEN 0 ELSE 1 END) AS countinactive,'.
                  'SUM(raa.countpassed) AS countpassed,'.
                  'SUM(raa.countfailed) AS countfailed,'.
                  'SUM(raa.averagegrade) AS sumaveragegrade,'.
                  "SUM(raa.$totalthisterm) AS $totalthisterm,".
                  "SUM(raa.$totalallterms) AS $totalallterms";

        $from   = '{user} u '.
                  "LEFT JOIN ($attemptsql) raa ON u.id = raa.userid ".
                  'LEFT JOIN {groups_members} gm ON u.id = gm.userid '.
                  'LEFT JOIN {groups} g ON gm.groupid = g.id';

        $where  = 'g.courseid = :courseid';

        $params = array('courseid' => $this->output->reader->course->id);

        return $this->add_filter_params($select, $from, $where, 'g.id,g.name', '', '', $params + $attemptparams);
    }

    /**
     * get_table_name_and_alias
     *
     * @param string $fieldname
     * @return array($tablename, $tablealias)
     * @todo Finish documenting this function
     */
    public function get_table_name_and_alias($fieldname) {
        switch ($fieldname) {

            case 'groupname':
            case 'countactive':
            case 'countinactive':
            case 'percentactive':
            case 'percentinactive':
            case 'averagetaken':
            case 'averagepassed':
            case 'averagefailed':
            case 'averagepercentgrade':
            case 'averagewordsthisterm':
            case 'averagewordsallterms':
                return array('', '');

            default:
                return parent::get_table_name_and_alias($fieldname);
        }
    }

    /**
     * override parent class method, because we may want to specify a default sort
     *
     * @return xxx
     */
    public function get_sql_sort()  {

        if ($sort = parent::get_sql_sort()) {
            // MSSQL does not like to sort by secondary fields
            // so we must convert this field back to its original name
            $sort = str_replace('groupname', 'g.name', $sort);
        }
        return $sort;
    }

    /**
     * records_exist
     */
    public function records_exist() {
        return $this->groups_exist();
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format header cells                                           //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * header_groupname
     *
     * @return string
     */
    public function header_groupname() {
        return get_string('group');
    }

    /**
     * header_countactive
     *
     * @return string
     */
    public function header_countactive() {
        return get_string('countactive', 'mod_reader').$this->help_icon('countactive');
    }

    /**
     * header_countinactive
     *
     * @return string
     */
    public function header_countinactive() {
        return get_string('countinactive', 'mod_reader').$this->help_icon('countinactive');
    }

    /**
     * header_percentactive
     *
     * @return string
     */
    public function header_percentactive() {
        return get_string('percentactive', 'mod_reader').$this->help_icon('percentactive');
    }

    /**
     * header_percentinactive
     *
     * @return string
     */
    public function header_percentinactive() {
        return get_string('percentinactive', 'mod_reader').$this->help_icon('percentinactive');
    }

    /**
     * header_averagetaken
     *
     * @return string
     */
    public function header_averagetaken() {
        return get_string('averagetaken', 'mod_reader').$this->help_icon('averagetaken');
    }

    /**
     * header_averagepassed
     *
     * @return string
     */
    public function header_averagepassed() {
        return get_string('averagepassed', 'mod_reader').$this->help_icon('averagepassed');
    }

    /**
     * header_averagefailed
     *
     * @return string
     */
    public function header_averagefailed() {
        return get_string('averagefailed', 'mod_reader').$this->help_icon('averagefailed');
    }

    /**
     * header_averagepercentgrade
     *
     * @return string
     */
    public function header_averagepercentgrade() {
        return get_string('averagegrade', 'mod_reader').$this->help_icon('averagegrade');
    }

    /**
     * header_averagewords
     *
     * @param xxx $type (optional, default="") "", "thisterm" or "allterms"
     * @return xxx
     */
    public function header_averagewords($type='')  {
        return get_string('averagewords', 'mod_reader');
    }

    /**
     * header_averagewordsthisterm
     *
     * @return string
     */
    public function header_averagewordsthisterm() {
        $header = $this->header_averagewords();
        return $this->header_add_period($header, 'thisterm', 'averagewordsthisterm');
    }

    /**
     * header_averagewordsallterms
     *
     * @return string
     */
    public function header_averagewordsallterms() {
        $header = $this->header_averagewords();
        return $this->header_add_period($header, 'allterms', 'averagewordsallterms');
    }

    /**
     * header_averagepoints
     *
     * @param xxx $type (optional, default="") "", "thisterm" or "allterms"
     * @return xxx
     */
    public function header_averagepoints($type='')  {
        return get_string('averagepoints', 'mod_reader');
    }

    /**
     * header_averagepointsthisterm
     *
     * @return string
     */
    public function header_averagepointsthisterm() {
        $header = $this->header_averagepoints();
        return $this->header_add_period($header, 'thisterm', 'averagepointsthisterm');
    }

    /**
     * header_averagepointsallterms
     *
     * @return string
     */
    public function header_averagepointsallterms() {
        $header = $this->header_averagepoints();
        return $this->header_add_period($header, 'allterms', 'averagepointsallterms');
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format data cells                                             //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * format_average_number
     *
     * @param xxx $row
     * @param xxx $field
     * @param xxx $value (optional, default=null)
     * @return xxx
     */
    public function format_average_number($row, $field, $value=null) {
        if (empty($row->countusers)) {
            return '';
        }
        if ($value===null) {
            $value = $row->$field;
        }
        $value = round($value / $row->countusers);
        if ($this->is_downloading()) {
            return $value;
        } else {
            return number_format($value);
        }
    }

    /**
     * format_average_percent
     *
     * @param xxx $row
     * @param xxx $field
     * @param xxx $value (optional, default=null)
     * @param xxx $multiplier (optional, default=1)
     * @return xxx
     */
    public function format_average_percent($row, $field, $value=null, $multiplier=100) {
        if (empty($row->countusers)) {
            return '';
        }
        if ($value===null) {
            $value = $row->$field;
        }
        return round($value / $row->countusers * $multiplier).'%';
    }

    /**
     * col_percentactive
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_percentactive($row) {
        return $this->format_average_percent($row, 'countactive');
    }

    /**
     * col_percentinactive
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_percentinactive($row) {
        return $this->format_average_percent($row, 'countinactive');
    }

    /**
     * col_averagetaken
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_averagetaken($row) {
        return $this->format_average_number($row, null, $row->countpassed + $row->countfailed);
    }

    /**
     * col_averagepassed
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_averagepassed($row) {
        return $this->format_average_number($row, 'countpassed');
    }

    /**
     * col_averagefailed
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_averagefailed($row) {
        return $this->format_average_number($row, 'countfailed');
    }

    /**
     * col_averagepercentgrade
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_averagepercentgrade($row) {
        return $this->format_average_percent($row, 'sumaveragegrade', null, 1);
    }

    /**
     * col_averagewordsthisterm
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_averagewordsthisterm($row) {
        return $this->format_average_number($row, 'totalwordsthisterm');
    }

    /**
     * col_averagewordsallterms
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_averagewordsallterms($row) {
        return $this->format_average_number($row, 'totalwordsallterms');
    }

    /**
     * col_averagepointsthisterm
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_averagepointsthisterm($row) {
        return $this->format_average_number($row, 'totalpointsthisterm');
    }

    /**
     * col_averagepointsallterms
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_averagepointsallterms($row) {
        return $this->format_average_number($row, 'totalpointsallterms');
    }
}
