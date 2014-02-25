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
 * reader_report_booksummary_table
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_report_booksummary_table extends reader_report_table {

    /** @var columns used in this table */
    protected $tablecolumns = array(
        'selected', 'publisher', 'level', 'name', 'difficulty',
        'countpassed', 'countfailed', 'averageduration', 'averagegrade', 'averagerating', 'countrating'
    );

    /** @var suppressed columns in this table */
    protected $suppresscolumns = array('publisher', 'level');

    /** @var columns in this table that are not sortable */
    protected $nosortcolumns = array();

    /** @var text columns in this table */
    protected $textcolumns = array('publisher', 'level', 'name');

    /** @var columns that are not to be center aligned */
    protected $leftaligncolumns = array('publisher', 'level', 'name');

    /** @var default sort columns */
    protected $defaultsortcolumns = array('publisher' => SORT_ASC, 'level' => SORT_ASC, 'name' => SORT_ASC);

    /** @var filter fields */
    protected $filterfields = array(
        'group'           => 0, 'publisher'    => 0, 'level'         => 1, 'name'  => 0,
        'difficulty'      => 1, 'countpassed'  => 1, 'countfailed'   => 1,
        'averageduration' => 1, 'averagegrade' => 1, 'averagerating' => 1, 'countrating'  => 1,
    );

    ////////////////////////////////////////////////////////////////////////////////
    // functions to extract data from $DB                                         //
    ////////////////////////////////////////////////////////////////////////////////

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
        list($attemptsql, $attemptparams) = $this->select_sql_attempts('quizid');

        // get users who can access this Reader activity
        list($usersql, $userparams) = $this->select_sql_users();

        $select = 'rb.id AS bookid, rb.publisher, rb.level, rb.name, rb.difficulty, '.
                  'raa.countpassed, raa.countfailed, '.
                  'raa.averageduration, raa.averagegrade, '.
                  'raa.countrating, raa.averagerating';
        $from   = '{reader_books} rb '.
                  "LEFT JOIN ($attemptsql) raa ON raa.quizid = rb.quizid";
        $where  = 'raa.quizid IS NOT NULL';

        $params = $attemptparams + array('readerid' => $this->output->reader->id) + $userparams;

        return $this->add_filter_params($select, $from, $where, '', '', '', $params);
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
            case 'bookid':
            case 'publisher':
            case 'level':
            case 'name':
            case 'difficulty':
                return array('reader_books', 'rb');

            // "reader_attempts" aggregate fields
            case 'countpassed':
            case 'countfailed':
            case 'averageduration':
            case 'averagegrade':
            case 'countrating':
            case 'averagerating':
                return array('', '');

            default:
                return parent::get_table_name_and_alias($fieldname);
        }
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format header cells                                           //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * header_averagerating
     *
     * @return xxx
     */
    public function header_averagerating()  {
        return get_string('averagerating', 'reader');
    }

    /**
     * header_countrating
     *
     * @return xxx
     */
    public function header_countrating()  {
        return get_string('countrating', 'reader');
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format data cells                                             //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * col_averagerating
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_averagerating($row)  {
        return $this->img_bookrating($row->averagerating);
    }
}
