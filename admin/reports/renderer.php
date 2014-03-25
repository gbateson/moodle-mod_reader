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
require_once($CFG->dirroot.'/mod/reader/admin/renderer.php');
require_once($CFG->dirroot.'/mod/reader/admin/reports/tablelib.php');
require_once($CFG->dirroot.'/mod/reader/admin/reports/filtering.php');

/**
 * mod_reader_admin_reports_renderer
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class mod_reader_admin_reports_renderer extends mod_reader_admin_renderer {

    /**#@+
     * tab ids
     *
     * @var integer
     */
    const TAB_REPORTS_USERSUMMARY  = 21;
    const TAB_REPORTS_USERDETAILED = 22;
    const TAB_REPORTS_GROUPSUMMARY = 23;
    const TAB_REPORTS_BOOKSUMMARY  = 24;
    const TAB_REPORTS_BOOKDETAILED = 25;
    /**#@-*/

    protected $pageparams = array();

    protected $filter = null;

    protected $users = null;

    protected $download = '';

    public $mode = '';

    /**
     * get_my_tab
     *
     * @return integer tab id
     */
    public function get_my_tab() {
        return self::TAB_REPORTS;
    }

    /**
     * get_default_tab
     *
     * @return integer tab id
     */
    public function get_default_tab() {
        return self::TAB_REPORTS_USERSUMMARY;
    }

    /**
     * get_tabs
     *
     * @return string HTML output to display navigation tabs
     */
    public function get_tabs() {
        $tabs = array();
        $cmid = $this->reader->cm->id;
        if ($this->reader->can_viewreports()) {
            $modes = mod_reader::get_modes('admin/reports');
            foreach ($modes as $mode) {
                $tab = constant('self::TAB_REPORTS_'.strtoupper($mode));
                $params = array('id' => $cmid, 'tab' => $tab, 'mode' => $mode);
                $url = new moodle_url('/mod/reader/admin/reports.php', $params);
                $tabs[] = new tabobject($tab, $url, get_string('report'.$mode, 'reader'));
            }
        }
        return $this->attach_tabs_subtree(parent::get_tabs(), parent::TAB_REPORTS, $tabs);
    }

    /**
     * define the names and order of the standard tab-modes for this renderer
     *
     * @return array of standard modes
     */
    static function get_standard_modes() {
        return array('usersummary', 'userdetailed', 'groupsummary', 'booksummary', 'bookdetailed');
    }

    /**
     * render_report
     *
     * @param object $reader
     * @param string $action
     * @param string $download
     */
    public function render_report($reader, $action, $download)  {
        $this->init($reader);
        if ($download=='') {
            echo $this->header();
            echo $this->tabs();
        }
        echo $this->reportcontent($action, $download);
        if ($download=='') {
            echo $this->footer();
        }
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
     *
     * @param string $action
     * @param string $download
     */
    public function reportcontent($action, $download)  {
        global $DB, $FULLME, $USER;

        // set baseurl for this page (used for filters and table)
        $baseurl = $this->baseurl();

        // create report table
        $tableclass = 'reader_admin_reports_'.$this->mode.'_table';
        $uniqueid = $this->page->pagetype.'-'.$this->mode;
        $table = new $tableclass($uniqueid, $this);

        // setup the report table
        $table->setup_report_table($baseurl, $action, $download);

        // execute required $action
        $table->execute_action($action);

        // display user and attempt filters
        $table->display_filters();

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
        $table->finish_output();
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
        $showcells = array();
        foreach ($table->column_suppress as $column => $suppress) {
            if ($suppress && $table->has_column($column)) {
                $this->fix_suppressed_column_in_rawdata($table, $column, $showcells);
            }
        }
    }

    /**
     * fix_suppressed_column_in_rawdata
     *
     * @param xxx $table (passed by reference)
     * @param xxx $column
     * @param xxx $showcells (passed by reference)
     * @return xxx
     */
    function fix_suppressed_column_in_rawdata(&$table, $column, &$showcells)   {
        $value  = array();
        $prefix = array();

        foreach ($table->rawdata as $id => $record) {
            if (! isset($record->$column)) {
                continue; // shouldn't happen !!
            }

            if (! isset($showcells[$id])) {
                $showcells[$id] = false;
            }

            if (isset($value[$column]) && $value[$column]==$record->$column) {
                if ($showcells[$id]) {
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
                // force the rest of the cells in this row to be shown too
                $showcells[$id] = true;
            }

            // cache this $column value
            $value[$column] = $record->$column;

            if (isset($prefix[$column]) && $prefix[$column]) {
                $table->rawdata[$id]->$column = $prefix[$column].$table->rawdata[$id]->$column;
            }
        }
    }
}
