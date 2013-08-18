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
require_once($CFG->dirroot.'/mod/reader/report/filtering.php');

/**
 * mod_reader_report_renderer
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class mod_reader_report_renderer extends mod_reader_renderer {

    protected $filterfields = array();

    protected $pageparams = array();

    protected $filter = null;

    protected $users = null;

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
     * baseurl for table
     */
    public function baseurl() {
        $url = $this->page->url;
        foreach ($this->pageparams as $param => $default) {
            if (is_numeric($default)) {
                $type = PARAM_INT;
            } else {
                $type = PARAM_CLEAN;
            }
            if ($value = optional_param($param, $default, $type)) {
                $url->param($param, $value);
            }
        }
        return $url;
    }

    /**
     * reportcontent
     */
    public function reportcontent()  {
        global $DB, $FULLME, $USER;

        // set baseurl for this page (used for filters and table)
        $baseurl = $this->baseurl();

        // display user and attempt filters
        $this->display_filters($baseurl);

        // create report table
        $tableclass = 'reader_report_'.$this->mode.'_table';
        $uniqueid = $this->page->pagetype.'-'.$this->mode;
        $table = new $tableclass($uniqueid, $this);

        // setup the report table
        $table->setup_report_table($baseurl);

        // setup sql to COUNT records
        list($select, $from, $where, $params) = $table->count_sql();
        $table->set_count_sql("SELECT $select FROM $from WHERE $where", $params);

        // setup sql to SELECT records
        list($select, $from, $where, $params) = $table->select_sql();
        $table->set_sql($select, $from, $where, $params);

        // extract records
        $table->query_db($table->get_page_size());

        // disable paging if it is not needed
        if (empty($table->pagesize)) {
            $table->use_pages = false;
        }

        // fix suppressed columns (those in which duplicate values for the same user are not repeated)
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

            $classname = 'reader_report_'.$this->mode.'_filtering';
            $filter = new $classname($this->filterfields, $baseurl);

            // create user/attempt filters
            $this->filter = $filter->get_sql_filter();

            $filter->display_add();
            $filter->display_active();
        }
    }

    /**
     * fix_suppressed_columns_in_rawdata
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
        $showrows = array();
        foreach ($table->column_suppress as $column => $suppress) {
            if ($suppress && $table->has_column($column)) {
                $this->fix_suppressed_column_in_rawdata($table, $column, $showrows);
            }
        }
    }

    /**
     * fix_suppressed_column_in_rawdata
     *
     * @param xxx $table (passed by reference)
     * @param xxx $column
     * @param xxx $showrows (passed by reference)
     * @return xxx
     */
    function fix_suppressed_column_in_rawdata(&$table, $column, &$showrows)   {
        $value  = array();
        $prefix = array();

        foreach ($table->rawdata as $id => $record) {
            if (! isset($record->$column)) {
                continue; // shouldn't happen !!
            }

            if (! isset($showrows[$id])) {
                $showrows[$id] = false;
            }

            if (isset($value[$column]) && $value[$column]==$record->$column) {
                if ($showrows[$id]) {
                    // oops, same value as previous row - we must adjust the $column value,
                    // so that "print_row()" (lib/tablelib.php) does not suppress this value
                    if (isset($prefix[$column]) && $prefix[$column]) {
                        $prefix[$column] = '';
                    } else {
                        // add an empty span tag to make this value different from previous user's
                        $prefix[$column] = html_writer::tag('span', '');
                    }
                }
            } else {
                // different value from previous row, so we can unset the prefix
                $prefix[$column] = '';
                $showrows[$id] = true;
            }

            $value[$column] = $record->$column;

            if (isset($prefix[$column]) && $prefix[$column]) {
                $table->rawdata[$id]->$column = $prefix[$column].$table->rawdata[$id]->$column;
            }
        }
    }
}
