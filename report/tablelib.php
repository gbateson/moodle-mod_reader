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

// get parent classes (table_sql and flexible_table)
require_once($CFG->dirroot.'/lib//tablelib.php');

/**
 * reader_report_table
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_report_table extends table_sql {

    /** @var string field in the attempt records that refers to the user id */
    public $useridfield = 'userid';

    /** @var mod_reader_report_renderer for the current page */
    protected $output;

    /** @var string time format used for the "timemodified" column */
    protected $timeformat = 'strftimerecentfull';

    /** @var string localized format used for the "timemodified" column */
    protected $strtimeformat;

    /** @var array list of distinct values stored in response columns */
    protected $legend = array();

    /**
     * Constructor
     *
     * @param int $uniqueid
     */
    function __construct($uniqueid, $output) {
        parent::__construct($uniqueid);
        $this->output = $output;
        $this->strtimeformat = get_string($this->timeformat);
    }

    /**
     * setup_report_table
     *
     * @param xxx $tablecolumns
     * @param xxx $baseurl
     * @param xxx $usercount (optional, default value = 10)
     */
    function setup_report_table($tablecolumns, $baseurl, $usercount=10)  {

        // generate headers (using "header_xxx()" methods below)
        $tableheaders = array();
        foreach ($tablecolumns as $tablecolumn) {
            $tableheaders[] = $this->format_header($tablecolumn);
        }

        $this->define_columns($tablecolumns);
        $this->define_headers($tableheaders);
        $this->define_baseurl($baseurl);

        if ($this->has_column('fullname')) {
            $this->pageable(true);
            $this->sortable(true);
            $this->initialbars($usercount > 20);

            // this information is only printed once per user
            $this->column_suppress('fullname');
            $this->column_suppress('picture');
            $this->column_suppress('grade');
            $this->column_suppress('userlevel');

            // special css class for "picture" column
            $this->column_class('picture', 'picture');
        } else {
            $this->pageable(false);
            $this->sortable(false);
            // you can set specific columns to be unsortable:
            // $this->no_sorting('columnname');
        }

        // basically all columns are centered
        $this->column_style_all('text-align', 'center');

        // some columns are not centered
        if ($this->has_column('username')) {
            $this->column_style('username', 'text-align', '');
        }
        if ($this->has_column('fullname')) {
            $this->column_style('fullname', 'text-align', '');
        }
        if ($this->has_column('booktitle')) {
            $this->column_style('booktitle', 'text-align', '');
        }

        // attributes in the table tag
        $this->set_attribute('id', 'attempts');
        $this->set_attribute('align', 'center');
        $this->set_attribute('class', $this->output->mode);

        parent::setup();
    }

    /**
     * wrap_html_start
     */
    function wrap_html_start() {

        // check this table has a "selected" column
        if (! $this->has_column('selected')) {
            return false;
        }

        // check user can delete attempts
        if (! $this->output->reader->can_manageattempts()) {
            return false;
        }

        // start form
        $url = $this->output->reader->report_url($this->output->mode);
        $params = array('id'=>'attemptsform', 'method'=>'post', 'action'=>$url->out_omit_querystring());
        echo html_writer::start_tag('form', $params);

        // create hidden fields
        $params = array('type'=>'hidden', 'name'=>'sesskey', 'value'=>sesskey());
        $hidden_fields = html_writer::input_hidden_params($url).
                         html_writer::empty_tag('input', $params)."\n";

        // put hidden fields in a containiner (for strict XHTML compatability)
        $params = array('style'=>'display: none;');
        echo html_writer::tag('div', $hidden_fields, $params);
    }

    /**
     * wrap_html_finish
     */
    function wrap_html_finish() {

        // check this table has a "selected" column
        if (! $this->has_column('selected')) {
            return false;
        }

        // check user can delete attempts
        if (! $this->output->reader->can_manageattempts()) {
            return false;
        }

        // start "commands" div
        $params = array('id' => 'commands');
        echo html_writer::start_tag('div', $params);

        // add "select all" link
        $text = get_string('selectall', 'quiz');
        $href = "javascript:select_all_in('TABLE',null,'attempts');";
        echo html_writer::tag('a', $text, array('href' => $href));

        echo ' / ';

        // add "deselect all" link
        $text = get_string('selectnone', 'quiz');
        $href = "javascript:deselect_all_in('TABLE',null,'attempts');";
        echo html_writer::tag('a', $text, array('href' => $href));

        echo ' &nbsp; ';

        echo 'Choose an action: ';

        // add button to delete attempts
        $confirm = addslashes_js(get_string('confirm'));
        $onclick = ''
            ."if(confirm('$confirm') && this.form && this.form.elements['confirmed']) {"
                ."this.form.elements['confirmed'].value = '1';"
                ."return true;"
            ."} else {"
                ."return false;"
            ."}"
        ;
        echo html_writer::empty_tag('input', array('type'=>'submit', 'onclick'=>"$onclick", 'name'=>'go', 'value'=>get_string('go')));
        echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'confirmed', 'value'=>'0'))."\n";

        // finish "commands" div
        echo html_writer::end_tag('div');

        // finish the "attemptsform" form
        echo html_writer::end_tag('form');
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format header cells                                           //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * format_header
     *
     * @param xxx $tablecolumn
     * @return xxx
     */
    function format_header($tablecolumn)  {
        $method = 'header_'.$tablecolumn;
        if (method_exists($this, $method)) {
            return $this->$method();
        } else {
            return $this->header_other($tablecolumn);
        }
    }

    /**
     * header_username
     *
     * @return xxx
     */
    function header_username()  {
        return get_string('username');
    }

    /**
     * header_picture
     *
     * @return xxx
     */
    function header_picture()  {
        return '';
    }

    /**
     * header_fullname
     *
     * @return xxx
     */
    function header_fullname()  {
        return get_string('name');
    }

    /**
     * header_grade
     *
     * @return xxx
     */
    function header_grade()  {
        return get_string('grade');
    }

    /**
     * header_selected
     *
     * @return xxx
     */
    function header_selected()  {
        return '';
    }

    /**
     * header_startlevel
     *
     * @return xxx
     */
    function header_startlevel()  {
        return get_string('startlevel', 'reader');
    }

    /**
     * header_currentlevel
     *
     * @return xxx
     */
    function header_currentlevel()  {
        return get_string('currentlevel', 'reader');
    }

    /**
     * header_nopromote
     *
     * @return xxx
     */
    function header_nopromote()  {
        return get_string('nopromote', 'reader');
    }

    /**
     * header_countpassed
     *
     * @return xxx
     */
    function header_countpassed()  {
        return get_string('countpassed', 'reader');
    }

    /**
     * header_countfailed
     *
     * @return xxx
     */
    function header_countfailed()  {
        return get_string('countfailed', 'reader');
    }

    /**
     * header_totalwords
     *
     * @return xxx
     */
    function header_totalwords($type='')  {
        $totalwords = get_string('totalwords', 'reader');
        if ($type) {
            $totalwords .= ' '.html_writer::tag('span', "($type)", array('class' => 'nowrap'));
        }
        return $totalwords;
    }

    /**
     * header_wordsthisterm
     *
     * @return xxx
     */
    function header_wordsthisterm()  {
        return $this->header_totalwords(get_string('thisterm', 'reader'));
    }

    /**
     * header_wordsallterms
     *
     * @return xxx
     */
    function header_wordsallterms()  {
        return $this->header_totalwords(get_string('allterms', 'reader'));
    }

    /**
     * header_goal
     *
     * @return xxx
     */
    function header_goal()  {
        return get_string('goal', 'reader');
    }

    /**
     * header_userlevel
     *
     * @return xxx
     */
    function header_userlevel() {
        return get_string('userlevel', 'reader');
    }

    /**
     * header_booklevel
     *
     * @return xxx
     */
    function header_booklevel() {
        return get_string('booklevel', 'reader');
    }

    /**
     * header_timefinish
     *
     * @return xxx
     */
    function header_timefinish() {
        return get_string('date');
    }

    /**
     * header_percentgrade
     *
     * @return xxx
     */
    function header_percentgrade() {
        return get_string('grade');
    }

    /**
     * header_words
     *
     * @return xxx
     */
    function header_words() {
        return get_string('words', 'reader');
    }

    /**
     * header_booktitle
     *
     * @return xxx
     */
    function header_booktitle() {
        return get_string('booktitle', 'reader');
    }

    /**
     * header_passed
     *
     * @return xxx
     */
    function header_passed() {
        return implode('/', array(get_string('passedshort', 'reader'), get_string('failedshort', 'reader'), get_string('cheatedshort', 'reader')));
    }

    /**
     * header_other
     *
     * @return xxx
     */
    function header_other($column)  {
        return 'header_'.$column.' is missing';
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format data cells                                             //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * col_selected
     *
     * @param xxx $row
     * @return xxx
     */
    function col_selected($row)  {
        $key = key($row); // name of first column
        return html_writer::checkbox('selected['.$key.']', 1, false);
    }

    /**
     * col_picture
     *
     * @param xxx $row
     * @return xxx
     */
    function col_picture($row)  {
        $courseid = $this->output->reader->course->id;
        $user = (object)array(
            'id'        => $row->userid,
            'firstname' => $row->firstname,
            'lastname'  => $row->lastname,
            'picture'   => $row->picture,
            'imagealt'  => $row->imagealt,
            'email'     => $row->email
        );
        return $this->output->user_picture($user, array('courseid'=>$courseid));
    }

    /**
     * col_grade
     *
     * @param xxx $row
     * @return xxx
     */
    function col_grade($row)  {
        if (isset($row->grade)) {
            return $row->grade.'%';
        } else {
            return '&nbsp;';
        }
    }

    /**
     * col_percentgrade
     *
     * @param xxx $row
     * @return xxx
     */
    function col_percentgrade($row)  {
        if (isset($row->percentgrade)) {
            return round($row->percentgrade).'%';
        } else {
            return '&nbsp;';
        }
    }

    /**
     * col_passed
     *
     * @param xxx $row
     * @return xxx
     */
    function col_passed($row)  {
        if (isset($row->passed) && $row->passed=='true') {
            return html_writer::tag('span', get_string('passedshort', 'reader'), array('class' => 'passed'));
        } else {
            return html_writer::tag('span', get_string('failedshort', 'reader'), array('class' => 'failed'));
        }
    }

    /**
     * col_timefinish
     *
     * @param xxx $row
     * @return xxx
     */
    function col_timefinish($row)  {
        if (empty($row->timefinish)) {
            return '';
        } else {
            return userdate($row->timefinish, get_string('strftimefinish', 'reader'));
        }
    }

    /**
     * col_totalwords
     *
     * @param xxx $row
     * @return xxx
     */
    function col_totalwords($row)  {
        static $userid = 0;
        static $totalwords = 0;

        if (empty($row->userid)) {
            return ''; // shouldn't happen !!
        }

        if ($userid && $userid==$row->userid) {
            // same user
        } else {
            $userid = $row->userid;
            $totalwords = 0;
        }

        if (isset($row->passed) && $row->passed=='true' && isset($row->words)) {
            $totalwords += $row->words;
        }

        return number_format($totalwords);
    }

    /**
     * other_cols
     *
     * @param xxx $column
     * @param xxx $row
     * @return xxx
     */
    function other_cols($column, $row) {
        if (! property_exists($row, $column)) {
            return "$column not found";
        }

        // format columns Q-1 .. Q-99
        if (is_numeric($row->$column)) {
            return number_format($row->$column);
        } else {
            return $this->format_text($row->$column);
        }
    }

    /**
     * override parent class method, because we may want to specify a default sort
     *
     * @return xxx
     */
    function get_sql_sort()  {

        // if user has specified a sort column, use that
        if ($sort = parent::get_sql_sort()) {
            return $sort;
        }

        // if there is a "fullname" column, sort by first/last name
        if ($this->has_column('fullname')) {
            $sort = 'u.firstname, u.lastname';
            if ($this->has_column('attempt')) {
                $sort .= ', ra.attempt ASC';
            }
            return $sort;
        }

        // no sort column, and no "fullname" column
        return '';
    }

    /**
     * has_column
     *
     * @param xxx $column
     * @return xxx
     */
    public function has_column($column)  {
        return array_key_exists($column, $this->columns);
    }

    /**
     * delete_rows
     *
     * @param xxx $delete_rows
     */
    function delete_rows($delete_rows)  {
        foreach ($delete_rows as $id => $delete_flag) {
            if ($delete_flag) {
                unset($this->rawdata[$id]);
            }
        }
    }

    /**
     * delete_columns
     *
     * @param xxx $delete_columns
     */
    function delete_columns($delete_columns)  {
        $newcolnum = 0;
        foreach($this->columns as $column => $oldcolnum) {
            if (empty($delete_columns[$column])) {
                $this->columns[$column] = $newcolnum++;
            } else {
                unset($this->columns[$column]);
                unset($this->headers[$oldcolnum]);
                foreach (array_keys($this->rawdata) as $id) {
                    unset($this->rawdata[$id]->$column);
                }
            }
        }
        // reset indexes on headers
        $this->headers = array_values($this->headers);
    }
}
