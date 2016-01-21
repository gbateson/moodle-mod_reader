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
require_once($CFG->dirroot.'/mod/reader/admin/books/tablelib.php');

/**
 * reader_admin_books_editcourse_table
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_admin_books_editcourse_table extends reader_admin_books_table {

    /** @var columns used in this table */
    protected $tablecolumns = array(
        'publisher', 'level', 'selected', 'name', 'difficulty', 'words', 'points'
    );

    /** @var suppressed columns in this table */
    protected $suppresscolumns = array('publisher', 'level');

    /** @var columns in this table that are not sortable */
    protected $nosortcolumns = array();

    /** @var text columns in this table */
    protected $textcolumns = array('publisher', 'level', 'name');

    /** @var number columns in this table */
    protected $numbercolumns = array('difficulty');

    /** @var columns that are not to be center aligned */
    protected $leftaligncolumns = array('publisher', 'level', 'name');

    /** @var default sort columns */
    protected $defaultsortcolumns = array('publisher' => SORT_ASC, 'level' => SORT_ASC, 'name' => SORT_ASC);

    /** @var filter fields ($fieldname => $advanced) */
    protected $filterfields = array(
        'publisher' => 0, 'level' => 1, 'name' => 0, 'difficulty' => 1, 'words' => 1, 'points' => 1
    );

    /** @var option fields */
    protected $optionfields = array('rowsperpage' => self::DEFAULT_ROWSPERPAGE,
                                    'sortfields'  => array());

    /** @var actions */
    protected $actions = array('setdifficulty', 'setwords', 'setpoints', 'removebookinstance', 'addbookinstance');

    /** @var maintable */
    protected $maintable = 'reader_book_instances';

    /**
     * select_sql
     *
     * @return array($select, $from, $where, $params)
     */
    public function select_sql() {
        $select = "rbi.id, rbi.readerid, rbi.bookid, rbi.difficulty, rbi.words, rbi.points, ".
                  'rb.publisher, rb.level, rb.name';
        $from   = '{reader_book_instances} rbi '.
                  'LEFT JOIN {reader_books} rb ON rbi.bookid = rb.id';
        $where  = 'rbi.readerid = :readerid AND rb.hidden = :hidden AND rb.level <> :level';
        $order  = 'rb.publisher, rb.level, rb.name';
        $params = array('readerid' => $this->output->reader->id, 'hidden' => 0, 'level' => 99);
        return $this->add_filter_params($select, $from, $where, '', '', '', $params);
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format, display and handle action settings                    //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * execute_action_removebookinstance
     *
     * @param string  $action
     * @return xxx
     */
    public function execute_action_removebookinstance($action) {
        global $DB;
        $ids = $this->get_selected('id');
        if (empty($ids)) {
            return false; // shouldn't happen !!
        }
        return $DB->delete_records_list('reader_book_instances', 'id', $ids);
    }
}
