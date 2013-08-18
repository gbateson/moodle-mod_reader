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

    /** @var is_sortable (from flexible table) */
    public $is_sortable = true;

    /** @var sort_default_column (from flexible table) */
    public $sort_default_column = '';

    /** @var use_pages (from flexible table) */
    public $use_pages = true;

    /** @var use_initials (from flexible table) */
    public $use_initials = true;

    /** @var string field in the attempt records that refers to the user id */
    public $useridfield = 'userid';

    /** @var mod_reader_report_renderer for the current page */
    protected $output;

    /** @var name of lang pack string that holds format for "timemodified" column */
    protected $timeformat = 'strftimerecentfull';

    /** @var string localized format used for the "timemodified" column */
    protected $strtimeformat;

    /** @var array of enrolled users who are able to view the current Reader activity */
    protected $users = null;

    /** @var columns used in this table */
    protected $tablecolumns = array();

    /** @var suppressed columns in this table */
    protected $suppresscolumns = array();

    /** @var columns in this table that are not sortable */
    protected $nosortcolumns = array();

    /** @var sortable columns in this table */
    protected $textcolumns = array();

    /** @var columns that are not to be center aligned */
    protected $leftaligncolumns = array();

    /** @var default sort columns */
    protected $defaultsortcolumns = array(); // $column => SORT_ASC/DESC

    /**
     * Constructor
     *
     * @param int $uniqueid
     */
    public function __construct($uniqueid, $output) {
        parent::__construct($uniqueid);
        $this->output = $output;
        $this->strtimeformat = get_string($this->timeformat);
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to setup table                                                   //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * setup_report_table
     *
     * @param xxx $tablecolumns
     * @param xxx $baseurl
     * @param xxx $rowcount (optional, default value = 10)
     */
    public function setup_report_table($baseurl)  {

        $tablecolumns = $this->get_tablecolumns();

        // generate headers (using "header_xxx()" methods below)
        $tableheaders = array();
        foreach ($tablecolumns as $tablecolumn) {
            $tableheaders[] = $this->format_header($tablecolumn);
        }

        $this->define_columns($tablecolumns);
        $this->define_headers($tableheaders);
        $this->define_baseurl($baseurl);

        // disable sorting on "selected" field
        if ($this->has_column('selected')) {
            $this->no_sorting('selected');
        }

        // basically all columns are centered
        $this->column_style_all('text-align', 'center');

        foreach ($this->tablecolumns as $column) {
            if (in_array($column, $this->nosortcolumns)) {
                $this->no_sorting($column);
            }
            if (in_array($column, $this->textcolumns)) {
                $this->text_sorting($column);
            }
            if (in_array($column, $this->suppresscolumns)) {
                $this->column_suppress($column);
            }
            if (in_array($column, $this->leftaligncolumns)) {
                $this->column_style($column, 'text-align', '');
            }
        }

        // add download buttons at bottom of page
        $this->is_downloadable(true);
        $this->show_download_buttons_at(array(TABLE_P_BOTTOM));

        // attributes in the table tag
        $this->set_attribute('id', 'attempts');
        $this->set_attribute('align', 'center');
        $this->set_attribute('class', $this->output->mode);

        parent::setup();

        // add default sort columns if necessary
        foreach ($this->defaultsortcolumns as $column => $sortdirection) {
            if ($this->has_column($column) || (($column=='firstname' || $column=='lastname') && $this->has_column('fullname'))) {
                if (! isset($this->sess->sortby)) {
                    $this->sess->sortby = array();
                }
                if (! array_key_exists($column, $this->sess->sortby)) {
                    $this->sess->sortby[$column] = $sortdirection;
                }
            }
        }
    }

    /*
     * get_tablecolumns
     *
     * @return array of column names
     */
    public function get_tablecolumns() {
        $tablecolumns = $this->tablecolumns;

        if (! $this->output->reader->can_manageattempts()) {
            // remove the select column from students view
            $i = array_search('selected', $tablecolumns);
            if (is_numeric($i)) {
                array_splice($tablecolumns, $i, 1);
            }
        }

        return $tablecolumns;
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to extract data from $DB                                         //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * count_sql
     *
     * @return array($sql, $params)
     */
    function count_sql() {
        return array('', array());
    }

    /**
     * select_sql
     *
     * @return array($sql, $params)
     */
    function select_sql() {
        return array('', array());
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
            $this->users = get_enrolled_users($this->output->reader->context, 'mod/reader:viewbooks', 0, 'u.id', 'id');
        }
        if ($prefix=='') {
            $type = SQL_PARAMS_QM;
        } else {
            $type = SQL_PARAMS_NAMED;
        }
        return $DB->get_in_or_equal(array_keys($this->users), $type, $prefix);
    }

    /**
     * select_sql_attempts
     *
     * @params string $groupbyfield "reader_attempts" field name ("userid" or "quizid")
     * @return xxx
     */
    function select_sql_attempts($groupbyfield) {
        list($usersql, $userparams) = $this->select_sql_users();

        // we ignore attempts before the "ignoredate"
        $ignoredate = $this->output->reader->ignoredate;

        $notfinished   = 'ra.timefinish IS NULL OR ra.timefinish = 0';
        $countattempts = "SUM(CASE WHEN ($notfinished) THEN 0 ELSE 1 END)";
        $sumgrade      = "SUM(CASE WHEN ($notfinished) THEN 0 ELSE (ra.percentgrade) END)";
        $sumduration   = "SUM(CASE WHEN ($notfinished) THEN 0 ELSE (ra.timefinish - ra.timestart) END)";

        $select = "ra.$groupbyfield,".
                  "ROUND($sumgrade / $countattempts) AS averagegrade,".
                  "ROUND($sumduration / $countattempts) AS averageduration,".
                  "SUM(CASE WHEN (ra.passed = :passed1 AND ra.timefinish > :time1) THEN 1 ELSE 0 END) AS countpassed,".
                  "SUM(CASE WHEN (ra.passed = :passed2 AND ra.timefinish > :time2) THEN 0 ELSE 1 END) AS countfailed";

        $from   = "{reader_attempts} ra ".
                  "LEFT JOIN mdl_reader_books rb ON ra.quizid = rb.quizid";

        $params = array('passed1' => 'true', 'time1' => $ignoredate,  // countpassed (this term)
                        'passed2' => 'true', 'time2' => $ignoredate); // countfailed (this term)

        switch ($groupbyfield) {
            case 'userid':
                $select .= ",SUM(CASE WHEN (ra.passed = :passed3 AND ra.timefinish > :time3) THEN rb.words ELSE 0 END) AS wordsthisterm".
                           ",SUM(CASE WHEN (ra.passed = :passed4 AND ra.timefinish > :time4) THEN rb.words ELSE 0 END) AS wordsallterms";
                $params += array('passed3' => 'true', 'time3' => $ignoredate, // wordsthisterm
                                 'passed4' => 'true', 'time4' => 0);          // wordsallterms
                break;

            case 'quizid':
                $notrated    = "$notfinished OR ra.bookrating IS NULL";
                $countrating = "SUM(CASE WHEN ($notrated) THEN 0 ELSE 1 END)";
                $sumrating   = "SUM(CASE WHEN ($notrated) THEN 0 ELSE ra.bookrating END)";
                $select     .= ",$countrating AS countrating".
                               ",ROUND($sumrating / $countrating) AS averagerating";
                break;
        }

        $where  = "ra.reader = :reader AND ra.userid $usersql";

        $params['reader'] = $this->output->reader->id;
        $params += $userparams;

        return array("SELECT $select FROM $from WHERE $where GROUP BY ra.$groupbyfield", $params);
    }

    /**
     * add_filter_params
     *
     * @param string $select
     * @param string $from
     * @param string $where
     * @param string $orderby
     * @param string $groupby
     * @param array $params
     * @return void, but may modify $select $from $where $params
     */
    function add_filter_params($select, $from, $where, $orderby, $groupby, $params) {

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

        // get filter $sql and $params

        // add user table if needed
        if ($require_usertable && ! $has_usertable) {
            $from   .= ', {user} u';
        }

        // add attempt table if needed
        if ($require_attempttable && ! $has_attempttable) {
            $from  .= ', {reader_attempts} ra';
        }

        if ($orderby) {
            $where .= " ORDER BY $orderby";
        }
        if ($groupby) {
            $where .= " GROUP BY $groupby";
        }

        return array($select, $from, $where, $params);
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to tart and finish form (if required)                            //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * wrap_html_start
     */
    public function wrap_html_start() {

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
    public function wrap_html_finish() {

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
    public function format_header($tablecolumn)  {
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
    public function header_username()  {
        return get_string('username');
    }

    /**
     * header_picture
     *
     * @return xxx
     */
    public function header_picture()  {
        return '';
    }

    /**
     * header_fullname
     *
     * @return xxx
     */
    public function header_fullname()  {
        return get_string('name');
    }

    /**
     * header_grade
     *
     * @return xxx
     */
    public function header_grade()  {
        return get_string('grade');
    }

    /**
     * header_selected
     *
     * @return xxx
     */
    public function header_selected()  {
        $selectall = get_string('selectall', 'quiz');
        $selectnone = get_string('selectnone', 'quiz');
        $onclick = "if (this.checked) {".
                       "select_all_in('TABLE',null,'attempts');".
                       "this.title = '".addslashes_js($selectnone)."';".
                   "} else {".
                       "deselect_all_in('TABLE',null,'attempts');".
                       "this.title = '".addslashes_js($selectall)."';".
                   "}";
        return get_string('select').html_writer::empty_tag('br').
               html_writer::empty_tag('input', array('type' => 'checkbox', 'name' => 'selected[0]', 'title' => $selectall, 'onclick' => $onclick));
    }

    /**
     * show_hide_link
     *
     * override default function so that certain columns are always visible
     *
     * @param string $column the column name, index into various names.
     * @param int $index numerical index of the column.
     * @return string HTML fragment.
     */
    protected function show_hide_link($column, $index) {
        if ($column=='selected') {
            return '';
        } else {
            return parent::show_hide_link($column, $index);
        }
    }

    /**
     * header_startlevel
     *
     * @return xxx
     */
    public function header_startlevel()  {
        return get_string('startlevel', 'reader');
    }

    /**
     * header_currentlevel
     *
     * @return xxx
     */
    public function header_currentlevel()  {
        return get_string('currentlevel', 'reader');
    }

    /**
     * header_nopromote
     *
     * @return xxx
     */
    public function header_nopromote()  {
        return get_string('nopromote', 'reader');
    }

    /**
     * header_countpassed
     *
     * @return xxx
     */
    public function header_countpassed()  {
        return get_string('countpassed', 'reader');
    }

    /**
     * header_countfailed
     *
     * @return xxx
     */
    public function header_countfailed()  {
        return get_string('countfailed', 'reader');
    }

    /**
     * header_averageduration
     *
     * @return xxx
     */
    public function header_averageduration()  {
        return get_string('averageduration', 'reader');
    }

    /**
     * header_averagegrade
     *
     * @return xxx
     */
    public function header_averagegrade()  {
        return get_string('averagegrade', 'reader');
    }

    /**
     * header_totalwords
     *
     * @return xxx
     */
    public function header_totalwords($type='')  {
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
    public function header_wordsthisterm()  {
        return $this->header_totalwords(get_string('thisterm', 'reader'));
    }

    /**
     * header_wordsallterms
     *
     * @return xxx
     */
    public function header_wordsallterms()  {
        return $this->header_totalwords(get_string('allterms', 'reader'));
    }

    /**
     * header_goal
     *
     * @return xxx
     */
    public function header_goal()  {
        return get_string('goal', 'reader');
    }

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

    /**
     * header_userlevel
     *
     * @return xxx
     */
    public function header_userlevel() {
        return get_string('userlevel', 'reader');
    }

    /**
     * header_booklevel
     *
     * @return xxx
     */
    public function header_booklevel() {
        return get_string('booklevel', 'reader');
    }

    /**
     * header_timefinish
     *
     * @return xxx
     */
    public function header_timefinish() {
        return get_string('date');
    }

    /**
     * header_percentgrade
     *
     * @return xxx
     */
    public function header_percentgrade() {
        return get_string('grade');
    }

    /**
     * header_words
     *
     * @return xxx
     */
    public function header_words() {
        return get_string('words', 'reader');
    }

    /**
     * header_booktitle
     *
     * @return xxx
     */
    public function header_booktitle() {
        return get_string('booktitle', 'reader');
    }

    /**
     * header_publisher
     *
     * @return xxx
     */
    public function header_publisher() {
        return get_string('publisher', 'reader');
    }

    /**
     * header_level
     *
     * @return xxx
     */
    public function header_level() {
        return get_string('level', 'reader');
    }

    /**
     * header_passed
     *
     * @return xxx
     */
    public function header_passed() {
        return implode('/', array(get_string('passedshort', 'reader'), get_string('failedshort', 'reader'), get_string('cheatedshort', 'reader')));
    }

    /**
     * header_bookrating
     *
     * @return xxx
     */
    public function header_bookrating() {
        return get_string('bookrating', 'reader');
    }

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
        return get_string('countactive', 'reader').' '.$this->help_icon('countactive');
    }

    /**
     * header_countinactive
     *
     * @return string
     */
    public function header_countinactive() {
        return get_string('countinactive', 'reader').' '.$this->help_icon('countinactive');
    }

    /**
     * header_percentactive
     *
     * @return string
     */
    public function header_percentactive() {
        return get_string('percentactive', 'reader').' '.$this->help_icon('percentactive');
    }

    /**
     * header_percentinactive
     *
     * @return string
     */
    public function header_percentinactive() {
        return get_string('percentinactive', 'reader').' '.$this->help_icon('percentinactive');
    }

    /**
     * header_averagetaken
     *
     * @return string
     */
    public function header_averagetaken() {
        return get_string('averagetaken', 'reader').' '.$this->help_icon('averagetaken');
    }

    /**
     * header_averagepassed
     *
     * @return string
     */
    public function header_averagepassed() {
        return get_string('averagepassed', 'reader').' '.$this->help_icon('averagepassed');
    }

    /**
     * header_averagefailed
     *
     * @return string
     */
    public function header_averagefailed() {
        return get_string('averagefailed', 'reader').' '.$this->help_icon('averagefailed');
    }

    /**
     * header_averagepoints
     *
     * @return string
     */
    public function header_averagepercentgrade() {
        return get_string('averagegrade', 'reader').' '.$this->help_icon('averagegrade');
    }

    /**
     * header_averagewords
     *
     * @return xxx
     */
    public function header_averagewords($type='')  {
        $averagewords = get_string('averagewords', 'reader');
        if ($type) {
            $strtype = get_string($type, 'reader');
            $averagewords .= ' '.html_writer::tag('span', "($strtype)", array('class' => 'nowrap'));
            $averagewords .= ' '.$this->help_icon('averagewords'.$type);
        }
        return $averagewords;
    }

    /**
     * header_averagewordsthisterm
     *
     * @return string
     */
    public function header_averagewordsthisterm() {
        return $this->header_averagewords('thisterm');
    }

    /**
     * header_averagewordsallterms
     *
     * @return string
     */
    public function header_averagewordsallterms() {
        return $this->header_averagewords('allterms');
    }

    /**
     * header_other
     *
     * @return xxx
     */
    public function header_other($column)  {
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
    public function col_selected($row)  {
        $key = key($row); // name of first column
        return html_writer::checkbox('selected['.$key.']', 1, false);
    }

    /**
     * col_picture
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_picture($row)  {
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
    public function col_grade($row)  {
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
    public function col_percentgrade($row)  {
        if (isset($row->percentgrade)) {
            return round($row->percentgrade).'%';
        } else {
            return '&nbsp;';
        }
    }

    /**
     * col_averageduration
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_averageduration($row)  {
        if (empty($row->averageduration)) {
            return '&nbsp;';
        } else {
            return format_time($row->averageduration);
        }
    }

    /**
     * col_averagegrade
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_averagegrade($row)  {
        if (isset($row->averagegrade)) {
            return round($row->averagegrade).'%';
        } else {
            return '&nbsp;';
        }
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
     * col_passed
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_passed($row)  {
        if (isset($row->passed) && $row->passed=='true') {
            return html_writer::tag('span', get_string('passedshort', 'reader'), array('class' => 'passed'));
        } else {
            return html_writer::tag('span', get_string('failedshort', 'reader'), array('class' => 'failed'));
        }
    }

    /**
     * img_bookrating
     *
     * @param xxx $row
     * @return xxx
     */
    public function img_bookrating($rating)  {
        global $OUTPUT;
        static $img = null;
        $rating = intval($rating);
        if ($rating >= 1 && $rating <= 3) {
            if ($img===null) {
                $src = $OUTPUT->pix_url('t/approve');
                $img = html_writer::empty_tag('img', array('src' => $src, 'alt' => get_string('bookrating', 'reader')));
            }
            return str_repeat($img, $rating);
        } else {
            return '';
        }
    }

    /**
     * col_bookrating
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_bookrating($row)  {
        return $this->img_bookrating($row->bookrating);
    }

    /**
     * col_timefinish
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_timefinish($row)  {
        if (empty($row->timefinish)) {
            return '';
        } else {
            return userdate($row->timefinish, get_string('strftimefinish', 'reader'));
        }
    }

    /**
     * col_wordsthisterm
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_wordsthisterm($row) {
        $wordsthisterm = number_format($row->wordsthisterm);
        switch (true) {
            case isset($row->userid): $report_url = $this->output->reader->report_url('userdetailed', null, $row->userid); break;
            case isset($row->bookid): $report_url = $this->output->reader->report_url('bookdetailed', null, $row->bookid); break;
            default:                  $report_url = '';
        }
        if ($report_url) {
            $wordsthisterm = html_writer::link($report_url, $wordsthisterm);
        }
        return $wordsthisterm;
    }

    /**
     * col_totalwords
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_totalwords($row)  {
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
     * col_totalwords
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_goal($row)  {
        global $DB;

        // cache for goals defined for each group
        static $goals = array();

        if (empty($row->userid)) {
            return ''; // shouldn't happen !!
        }

        $level = $row->currentlevel;
        $readerid = $this->output->reader->id;
        $courseid = $this->output->reader->course->id;

        $goal = $DB->get_field('reader_levels', 'goal', array('userid' => $row->userid, 'readerid' => $readerid));

        if ($goal===null || $goal===false) {
            $goal = 0;
            if ($groups = groups_get_all_groups($courseid, $row->userid)) {
                foreach ($groups as $groupid => $group) {
                    if (! array_key_exists($groupid, $goals)) {
                        $goals[$groupid] = array();
                    }
                    if (! array_key_exists($level, $goals[$groupid])) {
                        if ($groupgoal = $DB->get_field('reader_goal', 'goal', array('readerid' => $readerid, 'groupid' => $groupid, 'level' => $level))) {
                            $goals[$groupid][$level] = $groupgoal; // level specific goal
                        } else if ($groupgoal = $DB->get_field('reader_goal', 'goal', array('readerid' => $readerid, 'groupid' => $groupid, 'level' => 0))) {
                            $goals[$groupid][$level] = $groupgoal; // any level
                        } else {
                            $goals[$groupid][$level] = 0;
                        }
                    }
                    $goal = max($goal, $goals[$groupid][$level]);
                }
            }
        }

        if ($goal==0) {
            $goal = $this->output->reader->goal;
        }

        if ($goal==0) {
            return '';
        } else {
            return number_format($goal);
        }
    }

    /**
     * col_percentactive
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_percentactive($row) {
        if (empty($row->countusers)) {
            return '';
        } else {
            return round($row->countactive / $row->countusers * 100).'%';
        }
    }

    /**
     * col_percentinactive
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_percentinactive($row) {
        if (empty($row->countusers)) {
            return '';
        } else {
            return round($row->countinactive / $row->countusers * 100).'%';
        }
    }

    /**
     * col_averagetaken
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_averagetaken($row) {
        if (empty($row->countusers)) {
            return '';
        } else {
            return round(($row->countpassed + $row->countfailed) / $row->countusers);
        }
    }

    /**
     * col_averagepassed
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_averagepassed($row) {
        if (empty($row->countusers)) {
            return '';
        } else {
            return round($row->countpassed / $row->countusers);
        }
    }

    /**
     * col_averagefailed
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_averagefailed($row) {
        if (empty($row->countusers)) {
            return '';
        } else {
            return round($row->countfailed / $row->countusers);
        }
    }

    /**
     * col_averagepercentgrade
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_averagepercentgrade($row) {
        if (empty($row->countusers)) {
            return '';
        } else {
            return round($row->sumaveragegrade / $row->countusers).'%';
        }
    }

    /**
     * col_averagewordsthisterm
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_averagewordsthisterm($row) {
        if (empty($row->countusers)) {
            return '';
        } else {
            return number_format(round($row->wordsthisterm / $row->countusers));
        }
    }

    /**
     * col_averagewordsallterms
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_averagewordsallterms($row) {
        if (empty($row->countusers)) {
            return '';
        } else {
            return number_format(round($row->wordsallterms / $row->countusers));
        }
    }

    /**
     * other_cols
     *
     * @param xxx $column
     * @param xxx $row
     * @return xxx
     */
    public function other_cols($column, $row) {
        if (! property_exists($row, $column)) {
            return "$column not found";
        }

        if (is_numeric($row->$column)) {
            return number_format($row->$column);
        } else {
            return $this->format_text($row->$column);
        }
    }

    ////////////////////////////////////////////////////////////////////////////////
    // utility functions                                                          //
    ////////////////////////////////////////////////////////////////////////////////

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
    public function delete_rows($delete_rows)  {
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
    public function delete_columns($delete_columns)  {
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

    /**
     * Returns HTML to display a help icon
     *
     * @param string $column
     * @return string HTML fragment
     */
    public function help_icon($column) {
        global $OUTPUT;
        return $OUTPUT->help_icon($column, 'reader');
    }

    ////////////////////////////////////////////////////////////////////////////////
    // override parent functions                                                  //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * override parent class method, because we may want to specify a default sort
     *
     * @return xxx
     */
    public function get_sql_sort()  {

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
}
