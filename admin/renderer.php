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
 * mod/reader/admin/renderer.php
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
require_once($CFG->dirroot.'/mod/reader/renderer.php');
require_once($CFG->dirroot.'/mod/reader/admin/tablelib.php');
require_once($CFG->dirroot.'/mod/reader/admin/filtering.php');

/**
 * mod_reader_download_renderer
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_renderer extends mod_reader_renderer {

    public $tab = '';
    public $mode = '';

    protected $pageparams = array();
    protected $filter = null;

    public $actions = array();
    protected $download = '';

    /**
     * require_page_header
     */
    public function require_page_header() {
        return true;
    }

    /**
     * require_page_footer
     */
    public function require_page_footer() {
        return true;
    }

    /**
     * render_page_header
     */
    public function render_page_header() {
        $output = '';
        $output .= $this->header();
        $output .= $this->tabs();
        $output .= $this->box_start('generalbox');
        return $output;
    }

    /**
     * render_page_footer
     */
    public function render_page_footer() {
        $output = '';
        $output .= $this->box_end();
        $output .= $this->footer();
        return $output;
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
     * page_report
     */
    public function page_report()  {
        global $DB, $USER;

        // get form values
        $action = optional_param('action', '', PARAM_ALPHA);
        $download = optional_param('download', '', PARAM_ALPHA);

        // create report table
        $tableclass = 'reader_admin_'.$this->tab.'_'.$this->mode.'_table';
        $uniqueid = $this->page->pagetype.'-'.$this->mode;
        $table = new $tableclass($uniqueid, $this);

        // setup the report table
        $table->setup_report_table($action, $download);

        // execute required $action
        $table->execute_action($action);

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

        // display user and attempt filters
        $table->display_filters();

        // build the table rows
        $table->build_table();

        // display the table if it contains any rows
        // otherwise, display a helpful message :-)
        if ($table->started_output) {
            $table->finish_output();
        } else {
            $table->nothing_to_display($this->mode);
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

    /**
     * heading_action
     *
     * @param string  $action
     * @param string  $type of action: "quizzes", "books", "users", "attempts"
     * @return string formatted heading for this $action and $type
     */
    public function heading_action($action, $type) {
        if ($action) {
            $heading = $type.$action;
        } else {
            $heading = 'reader:manage'.$type;
        }
        return $this->heading(get_string($heading, 'mod_reader'));
    }

    /**
     * list_actions
     *
     * @param integer course_modules $id of current Reader activity
     * @param string  $type of action: "quizes", "books", "users", "attempts"
     * @return string formatted list of links to user actions
     */
    public function list_actions($cmid, $type) {
        $links = array();
        foreach ($this->actions as $action) {
            $params = array('id' => $cmid, 'action' => $action);
            $href = new moodle_url("/mod/reader/admin/$type.php", $params);
            $text = get_string($type.$action, 'mod_reader');
            $links[] = html_writer::link($href, $text);
        }
        return html_writer::alist($links);
    }

    /**
     * continue_button
     *
     * @param integer course_modules $id of current Reader activity
     * @return formatted link to return to admin index
     */
    public function continue_button($id) {
        $url = new moodle_url('/mod/reader/admin/index.php', array('id' => $id));
        return parent::continue_button($url);
    }

    /**
     * available_booktypes
     *
     * @param xxx
     * @return xxx
     * @todo Finish documenting this function
     */
    public function available_booktypes() {
        global $CFG;
        require_once($CFG->dirroot.'/mod/reader/admin/books/download/downloader.php');
        return array(reader_downloader::BOOKS_WITH_QUIZZES => get_string('bookswithquizzes', 'mod_reader'),
                     reader_downloader::BOOKS_WITHOUT_QUIZZES => get_string('bookswithoutquizzes', 'mod_reader'));
    }
}
