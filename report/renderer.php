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
 * Render an attempt at a Reader quiz
 *
 * @package   mod-reader
 * @copyright 2013 Gordon Bateson <gordon.bateson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die;

/** Include required files */
require_once($CFG->dirroot.'/mod/reader/renderer.php');
require_once($CFG->dirroot.'/mod/reader/report/tablelib.php');
require_once($CFG->dirroot.'/mod/reader/report/userfiltering.php');

/**
 * mod_reader_report_renderer
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class mod_reader_report_renderer extends mod_reader_renderer {

    protected $tablecolumns = array();

    protected $filterfields = array();

    protected $userfilter = null;

    protected $attemptfilter = null;

    public $mode = '';

    /**
     * init
     *
     * @param xxx $reader
     */
    protected function init($reader)   {
        global $DB;
        $this->reader = $reader;
    }

    /**
     * render_report
     *
     * @param xxx $reader
     */
    public function render_report($reader)  {
        $this->init($reader);
        echo $this->header();
        echo $this->reportcontent();
        echo $this->footer();
    }

    /**
     * reportcontent
     */
    public function reportcontent()  {
        global $DB, $USER;

        // check capabilities
        if ($this->reader->can_viewreports()) {
            $userid = 0;  // all users
        } else {
            return false; // shouldn't happen !!
        }

        // set baseurl for this page (used for filters and table)
        $baseurl = $this->reader->report_url($this->mode); //->out()

        // display user and attempt filters
        $this->display_filters($baseurl);

        // create report table
        $uniqueid = $this->page->pagetype.'-'.$this->mode;
        $table = new reader_report_table($uniqueid, $this);

        // set the table columns
        $tablecolumns = $this->tablecolumns;
        if (! $this->reader->can_manageattempts()) {
            // remove the select column from students view
            $i = array_search('selected', $tablecolumns);
            if (is_numeric($i)) {
                array_splice($tablecolumns, $i, 1);
            }
        }

        // setup the report table
        $table->setup_report_table($tablecolumns, $baseurl);

        // setup sql to COUNT records
        list($select, $from, $where, $params) = $this->count_sql($userid);
        $table->set_count_sql("SELECT $select FROM $from WHERE $where", $params);

        // setup sql to SELECT records
        list($select, $from, $where, $params) = $this->select_sql($userid);

        $table->set_sql($select, $from, $where, $params);

        // extract attempt records
        $table->query_db($table->get_page_size());

        $this->fix_suppressed_columns_in_rawdata($table);

        // display the table
        $table->build_table();
        $table->finish_html();
    }

    /**
     * display_filters
     *
     * @uses $DB
     */
    function display_filters($baseurl) {
        if (count($this->filterfields) && $this->reader->can_viewreports()) {

            $user_filtering = new reader_user_filtering($this->filterfields, $baseurl);

            // create user/attempt filters
            $this->userfilter = $user_filtering->get_sql_filter();
            $this->attemptfilter = $user_filtering->get_sql_filter_attempts();

            $user_filtering->display_add();
            $user_filtering->display_active();
        }
    }

    /**
     * add_filter_params
     *
     * @param string $userfields (can be "")
     * @param integer $userid    (can be 0)
     * @param integer $attemptid (can be 0)
     * @param string $select
     * @param string $from
     * @param string $where
     * @param array $params
     * @return void, but may modify $select $from $where $params
     */
    function add_filter_params($userfields, $userid, $attemptid, $select, $from, $where, $params) {

        // search string to detect db fieldname in a filter string
        // - not preceded by {:`"'_. a-z 0-9
        // - starts with lowercase a-z
        // - followed by lowercase a-z, 0-9 or underscore
        // - not followed by }:`"'_. a-z 0-9
        $before = '[{:`"'."'".'a-zA-Z0-9_.]';
        $after  = '[}:`"'."'".'a-zA-Z0-9_.]';
        $search = "/(?<!$before)([a-z][a-z0-9_]*)(?!$after)/";

        if (strpos($from, '{user}')===false) {
            $has_usertable = false;
        } else {
            $has_usertable = true;
        }
        if (strpos($from, '{reader_attempts}')===false) {
            $has_attempttable = false;
        } else {
            $has_attempttable = true;
        }

        $require_usertable = false;
        $require_attempttable = false;

        if ($userid) {
            throw new moodle_exception('how do we filter specific user?');
        } else if ($this->userfilter) {
            list($filterwhere, $filterparams) = $this->userfilter;
            if ($filterwhere) {
                $filterwhere = preg_replace($search, 'u.$1', $filterwhere);
                $where  .= ' AND '.$filterwhere;
                $params += $filterparams;
                $require_usertable = true;
            }
        }

        if ($attemptid) {
            throw new moodle_exception('how do we filter specific user?');
        } else if ($this->attemptfilter) {
            list($filterwhere, $filterparams) = $this->attemptfilter;
            if ($filterwhere) {
                $filterwhere = preg_replace($search, 'ra.$1', $filterwhere);
                $where  .= ' AND '.$filterwhere;
                $params += $filterparams;
                $require_attempttable = true;
            }
        }

        // add user table if needed
        if ($require_usertable && ! $has_usertable) {
            $from   .= ', {user} u';
        }

        // add attempt table if needed
        if ($require_attempttable && ! $has_attempttable) {
            $from  .= ', {reader_attempts} ra';
        }

        // join to grade tables if necessary
        if (strpos($select, 'gg')===false && strpos($where, 'gg')===false) {
            // grade tables not required
        } else if (strpos($from, 'grade_grades')===false) {
            // grade tables are required, but missing, so add them to $from
            // the "gg" table alias is added by the "set_sql()" method of this class
            // or the "get_sql_filter_attempts()" method of the reader "grade" filter
            $from   .= ', {grade_items} gi, {grade_grades} gg';
            $where  .= ' AND ra.userid = gg.userid AND gg.itemid = gi.id'.
                       ' AND gi.courseid = :courseid AND gi.itemtype = :itemtype'.
                       ' AND gi.itemmodule = :itemmodule AND gi.iteminstance = :iteminstance';
            $params += array('courseid' => $this->reader->course->id, 'itemtype' => 'mod',
                             'itemmodule' => 'reader', 'iteminstance' => $this->reader->id);
        }

        return array($select, $from, $where, $params);
    }

    /**
     * select_sql_users
     *
     * @uses $DB
     * @param string $prefix (optional, default="") prefix for DB $params
     * @return xxx
     */
    function select_sql_users($prefix='user') {
        global $DB;
        if ($this->users===null) {
            $this->users = get_enrolled_users($this->reader->context, 'mod/reader:viewbooks', 0, 'u.id', 'id');
        }
        if ($prefix=='') {
            $type = SQL_PARAMS_QM;
        } else {
            $type = SQL_PARAMS_NAMED;
        }
        return $DB->get_in_or_equal(array_keys($this->users), $type, $prefix);
    }

    /**
     * count_sql
     *
     * @param xxx $userid (optional, default=0)
     * @param xxx $attemptid (optional, default=0)
     * @return xxx
     */
    function count_sql($userid=0, $attemptid=0) {
        return '';
    }

    /**
     * select_sql
     *
     * @param xxx $userid (optional, default=0)
     * @param xxx $attemptid (optional, default=0)
     * @return xxx
     */
    function select_sql($userid=0, $attemptid=0) {
        return '';
    }

    /**
     * fix_suppressed_columns_in_rawdata
     *
     * this function adjusts the grade values
     *
     * @param xxx $table (passed by reference)
     * @return xxx
     */
    function fix_suppressed_columns_in_rawdata(&$table)   {
        if (empty($table->rawdata)) {
            return false; // no records
        }
        if (empty($table->column_suppress)) {
            return false; // no suppressed columns i.e. all columns are always printed
        }
        foreach ($table->column_suppress as $column => $suppress) {
            if ($suppress && $table->has_column($column)) {
                $this->fix_suppressed_column_in_rawdata($table, $column);
            }
        }
    }

    /**
     * fix_suppressed_column_in_rawdata
     *
     * this function adjusts the grade values
     *
     * @param xxx $table (passed by reference)
     * @return xxx
     */
    function fix_suppressed_column_in_rawdata(&$table, $column)   {
        $userid = 0;
        $value  = array();
        $prefix = array();

        foreach ($table->rawdata as $id => $record) {
            if (isset($record->$column)) {
                if ($userid) {
                    if ($userid==$record->userid) {
                        // same user - do nothing
                    } else {
                        if (isset($value[$column]) && $value[$column]==$record->$column) {
                            // oops, same value as previous user - we must adjust the $column value,
                            // so that "print_row()" (lib/tablelib.php) does not suppress this value
                            if (isset($prefix[$column]) && $prefix[$column]) {
                                $prefix[$column] = '';
                            } else {
                                // add an empty span tag to make this value different from previous user's
                                $prefix[$column] = html_writer::tag('span', '');
                            }
                        } else {
                            // different grade from previous user, so we can unset the prefix
                            $prefix[$column] = '';
                        }
                    }
                    if (isset($prefix[$column]) && $prefix[$column]) {
                        $table->rawdata[$id]->$column = $prefix[$column].$table->rawdata[$id]->$column;
                    }
                }
                $userid = $record->userid;
                $value[$column] = $record->$column;
            }
        }
    }
}
