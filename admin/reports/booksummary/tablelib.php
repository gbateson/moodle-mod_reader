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
require_once($CFG->dirroot.'/mod/reader/admin/reports/tablelib.php');

/**
 * reader_admin_reports_booksummary_table
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_admin_reports_booksummary_table extends reader_admin_reports_table {

    /** @var columns used in this table */
    protected $tablecolumns = array(
        'publisher', 'level', 'selected', 'name', 'difficulty', 'words', 'points',
        'countpassed', 'countfailed', 'averageduration', 'averagegrade', 'averagerating', 'countrating'
    );

    /** @var suppressed columns in this table */
    protected $suppresscolumns = array('publisher', 'level');

    /** @var columns in this table that are not sortable */
    protected $nosortcolumns = array();

    /** @var text columns in this table */
    protected $textcolumns = array('publisher', 'level', 'name');

    /** @var number columns in this table */
    protected $numbercolumns = array('difficulty', 'countpassed', 'countfailed', 'countrating');

    /** @var columns that are not to be center aligned */
    protected $leftaligncolumns = array('publisher', 'level', 'name');

    /** @var default sort columns */
    protected $defaultsortcolumns = array('publisher' => SORT_ASC, 'level' => SORT_ASC, 'name' => SORT_ASC);

    /** @var filter fields ($fieldname => $advanced) */
    protected $filterfields = array(
        //'group'         => 0,
        'publisher'       => 0, 'level'        => 1, 'name'          => 0,
        'difficulty'      => 1, 'countpassed'  => 1, 'countfailed'   => 1,
        'averageduration' => 1, 'averagegrade' => 1, 'averagerating' => 1, 'countrating'  => 1,
    );

    /** @var option fields */
    protected $optionfields = array('booktype'    => self::DEFAULT_BOOKTYPE,
                                    'termtype'    => self::DEFAULT_TERMTYPE,
                                    'rowsperpage' => self::DEFAULT_ROWSPERPAGE,
                                    'showhidden'  => self::DEFAULT_SHOWHIDDEN,
                                    'sortfields'  => array());

    /** @var actions */
    protected $actions = array('showhidebooks');

    /**
     * Constructor
     *
     * @param int $uniqueid
     */
    public function __construct($uniqueid, $output) {
        $this->fix_words_or_points_fields($output, array('words'), array('points'));
        parent::__construct($uniqueid, $output);
    }

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
        list($attemptsql, $attemptparams) = $this->select_sql_attempts('bookid');

        // get users who can access this Reader activity
        list($usersql, $userparams) = $this->select_sql_users();

        if ($this->output->reader->wordsorpoints==0) {
            $wordsorpoints = 'rb.words';
        } else {
            $wordsorpoints = 'rb.points';
        }

        $select = 'rb.id AS bookid, rb.publisher, rb.level, rb.name, rb.difficulty, '.$wordsorpoints.', '.
                  'raa.countpassed, raa.countfailed, '.
                  'raa.averageduration, raa.averagegrade, '.
                  'raa.countrating, raa.averagerating';
        $from   = '{reader_books} rb '.
                  "LEFT JOIN ($attemptsql) raa ON raa.bookid = rb.id";

        $booktype = $this->filter->get_optionvalue('booktype');
        switch ($booktype) {

            case reader_admin_reports_options::BOOKS_AVAILABLE_WITHOUT:
                $where = 'raa.bookid IS NULL';
                break;

            case reader_admin_reports_options::BOOKS_AVAILABLE_WITH:
            case reader_admin_reports_options::BOOKS_ALL_WITH:
                $where = 'raa.bookid IS NOT NULL';
                break;

            case reader_admin_reports_options::BOOKS_AVAILABLE_ALL:
            default: // shouldn't happen !!
                $where = '1=1';
                break;
        }

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
     * header_averagerating
     *
     * @return xxx
     */
    public function header_averagerating()  {
        return get_string('averagerating', 'mod_reader');
    }

    /**
     * header_countrating
     *
     * @return xxx
     */
    public function header_countrating()  {
        return get_string('countrating', 'mod_reader');
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format data cells                                             //
    ////////////////////////////////////////////////////////////////////////////////

   /**
     * col_percentgrade
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_averagegrade($row)  {
        if (! isset($row->averagegrade)) {
            return $this->empty_cell();
        }
        $params = array('id' => $this->output->reader->cm->id, 'bookid' => $row->bookid);
        $url = new moodle_url('/mod/reader/view_attempts.php', $params);
        return html_writer::link($url, round($row->averagegrade).'%', array('onclick' => "this.target='_blank'"));
    }

    /**
     * col_averagerating
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_averagerating($row)  {
        return $this->img_bookrating($row->averagerating);
    }

    /**
     * display_action_settings_showhidebooks
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_showhidebooks($action) {
        $value = optional_param($action, 0, PARAM_INT);
        $settings = '';
        $settings .= get_string('newsetting', 'mod_reader').': ';
        $options = array('0' => get_string('show'), '1' => get_string('hide'));
        $settings .= html_writer::select($options, $action, $value, '', array());
        return $this->display_action_settings($action, $settings);
    }

    /**
     * execute_action_showhidebooks
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_showhidebooks($action) {
        $value = optional_param($action, 0, PARAM_INT);
        return $this->execute_action_update('bookid', 'reader_books', 'hidden', $value);
    }
}
