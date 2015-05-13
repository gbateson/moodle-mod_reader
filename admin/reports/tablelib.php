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
require_once($CFG->dirroot.'/lib/formslib.php');
require_once($CFG->dirroot.'/lib//tablelib.php');

/**
 * reader_admin_reports_table
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_admin_reports_table extends table_sql {

    /**#@+
    * default values for display options
    *
    * @const integer
    */
    const DEFAULT_USERTYPE    = 0;  // i.e. enrolled users with attempts
    const DEFAULT_ROWSPERPAGE = 30;
    const DEFAULT_SHOWDELETED = 0;  // i.e. ignore deleted attempts
    const DEFAULT_SHOWHIDDEN  = 0;  // i.e. ignore hidden quizzes
    const DEFAULT_SORTFIELDS  = ''; // i.e. no special sorting
    /**#@-*/

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

    /** @var mod_reader_admin_reports_renderer for the current page */
    protected $output;

    /** @var name of lang pack string that holds format for "timemodified" column */
    protected $timeformat = 'strftimerecentfull';

    /** @var string localized format used for the "timemodified" column */
    protected $strtimeformat = '';

    /** @var array of enrolled users who are able to view the current Reader activity */
    protected $users = null;

    /** @var columns used in this table */
    protected $tablecolumns = array();

    /** @var suppressed columns in this table */
    protected $suppresscolumns = array();

    /** @var columns in this table that are not sortable */
    protected $nosortcolumns = array();

    /** @var sortable columns to be formatted as text */
    protected $textcolumns = array();

    /** @var columns to be formatted as a number */
    protected $numbercolumns = array();

    /** @var columns that are not to be center aligned */
    protected $leftaligncolumns = array();

    /** @var default sort columns array($column => SORT_ASC or SORT_DESC) */
    protected $defaultsortcolumns = array();

    /** @var filter: user_filtering object */
    protected $filter = null;

    /** @var filter fields */
    protected $filterfields = array();

    /** @var option fields */
    protected $optionfields = array();

    /** @var actions */
    protected $actions = array();

    /** @var date_strings */
    protected $date_strings = null;

    /**
     * Constructor
     *
     * @param int $uniqueid
     */
    public function __construct($uniqueid, $output) {
        global $DB, $USER;

        parent::__construct($uniqueid);
        $this->output = $output;
        $this->strtimeformat = get_string($this->timeformat);

        // remove group filter if it is not needed
        if (isset($this->filterfields['group'])) {

            $courseid  = $this->output->reader->course->id;
            $groupmode = $this->output->reader->course->groupmode;

            if ($groupmode==SEPARATEGROUPS) {
                $context = reader_get_context(CONTEXT_COURSE, $courseid);
                if (has_capability('moodle/site:accessallgroups', $context)) {
                    $groupmode = VISIBLEGROUPS; // user can access all groups
                }
            }

            // set $has_groups
            switch ($groupmode) {

                case VISIBLEGROUPS:
                    $has_groups = $DB->record_exists('groups', array('courseid' => $courseid));
                    break;

                case SEPARATEGROUPS:
                    $select = 'gm.id, gm.groupid, g.courseid';
                    $from   = '{groups_members} gm JOIN {groups} g ON gm.groupid = g.id';
                    $where  = 'gm.userid = ? AND g.courseid = ?';
                    $params = array($USER->id, $courseid);

                    if ($defaultgroupingid = $this->output->reader->course->defaultgroupingid) {
                        $select .= ', gg.groupingid';
                        $from   .= ' JOIN {groupings_groups} gg ON g.id = gg.groupid';
                        $where  .= ' AND gg.groupingid = ?';
                        $params[] = $defaultgroupingid;
                    }

                    $has_groups = $DB->record_exists_sql("SELECT $select FROM $from WHERE $where", $params);
                    break;

                case NOGROUPS:
                default:
                    $has_groups = false;
                    break;
            }

            if ($has_groups==false) {
                unset($this->filterfields['group']);
            }
        }
    }

    /**
     * fix_words_or_points_fields
     *
     * @param onject $output
     * @param array  $wordsfields
     * @param array  $pointsfields
     */
    public function fix_words_or_points_fields($output, $wordsfields, $pointsfields) {
        if ($output->reader->wordsorpoints==0) {
            $fields = $pointsfields;
        } else {
            $fields = $wordsfields;
        }
        foreach ($fields as $field) {
            if ($i = array_search($field, $this->tablecolumns)) {
                array_splice($this->tablecolumns, $i, 1);
            }
            if ($i = array_search($field, $this->numbercolumns)) {
                array_splice($this->numbercolumns, $i, 1);
            }
            if (array_key_exists($field, $this->filterfields)) {
                unset($this->filterfields[$field]);
            }
        }
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to setup table                                                   //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * setup_report_table
     *
     * @param xxx $tablecolumns
     * @param xxx $baseurl
     * @param xxx $action
     * @param xxx $download
     */
    public function setup_report_table($baseurl, $action, $download)  {
        global $SESSION;

        // set up download, if requested
        if ($download) {
            $title = $this->output->reader->course->shortname;
            $title .= ' '.$this->output->reader->name;
            $title .= ' '.get_string('report'.$this->output->mode, 'mod_reader');
            $title = strip_tags(format_string($title, true));
            $this->is_downloading($download, clean_filename($title), $title);

            // disable initials bars and suppressed columns
            $this->initialbars(false);
            $this->suppresscolumns = array();
        }

        $tablecolumns = $this->get_tablecolumns();

        // generate headers (using "header_xxx()" methods below)
        $tableheaders = array();
        foreach ($tablecolumns as $tablecolumn) {
            $tableheaders[] = $this->format_header($tablecolumn);
        }

        $this->define_columns($tablecolumns);
        $this->define_headers($tableheaders);
        $this->define_baseurl($baseurl);

        // disable sorting on "studentview" field
        if ($this->has_column('studentview')) {
            $this->no_sorting('studentview');
        }

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
                if (method_exists($this, 'text_sorting')) {
                    $this->text_sorting($column); // Moodle >= 2.2
                }
            }
            if (in_array($column, $this->suppresscolumns)) {
                $this->column_suppress($column);
            }
            if (in_array($column, $this->leftaligncolumns)) {
                $this->column_style($column, 'text-align', '');
            }
        }


        // make the page downloadable
        $this->is_downloadable(true);

        // add download buttons at bottom of page
        $this->show_download_buttons_at(array(TABLE_P_BOTTOM));

        // attributes in the table tag
        $this->set_attribute('id', 'attempts');
        $this->set_attribute('align', 'center');
        $this->set_attribute('class', $this->output->mode);

        // use persistent table settings in Moodle >= 2.9
        if (method_exists($this, 'is_persistent')) {
            $this->is_persistent(true);
        }

        // get user preferences
        $this->get_user_preferences();

        parent::setup();

        // setup filter form (but don't display it yet)
        if (count($this->filterfields) && $this->output->reader->can_viewreports()) {
            $classname = 'reader_admin_reports_'.$this->output->mode.'_filtering';
            $this->filter = new $classname($this->filterfields, $this->baseurl, null, $this->optionfields);
        }
    }

    /**
     * get_download_menu
     *
     * this function overrides standard get_download_menu()
     * so that Excel download is disabled if xmlwriter class is missing
     */
    public function get_download_menu() {
        $exportclasses = parent::get_download_menu();
        if (! class_exists('XMLWriter')) {
            unset($exportclasses['excel']);
        }
        return $exportclasses;
    }

    /*
     * get_tablecolumns
     *
     * @return array of column names
     */
    public function get_tablecolumns() {
        $tablecolumns = $this->tablecolumns;

        // certain columns are not needed in certain situations
        $removecolumns = array();

        if (empty($this->actions) || $this->download || ! $this->output->reader->can_manageattempts()) {
            $removecolumns[] = 'selected';
        }
        if ($this->download || ! $this->output->reader->can_manageattempts()) {
            $removecolumns[] = 'studentview';
        }

        foreach ($removecolumns as $removecolumn) {
            $i = array_search($removecolumn, $tablecolumns);
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
     * @return array($select, $from, $where, $params)
     */
    public function count_sql() {
        list($select, $from, $where, $params) = $this->select_sql();
        $temptable = '';
        if ($select) {
            $temptable .= "SELECT $select";
        }
        if ($from) {
            $temptable .= " FROM $from";
        }
        if ($where) {
            $temptable .= " WHERE $where";
        }
        if ($temptable=='') {
            return array('', '', '', array());
        } else {
            return array('COUNT(*)', "($temptable) temptable", '1', $params);
        }
    }

    /**
     * select_sql
     *
     * @return array($select, $from, $where, $params)
     */
    public function select_sql() {
        return array('', '', '', array());
    }

    /**
     * select_sql_users
     *
     * @uses $DB
     * @param string $prefix (optional, default="") prefix for DB $params
     * @return xxx
     */
    public function select_sql_users($prefix='user') {
        global $DB;
        if ($this->users===null) {

            $usertype = $this->filter->get_optionvalue('usertype');
            switch ($usertype) {

                case reader_admin_reports_options::USERS_ENROLLED_WITH:
                case reader_admin_reports_options::USERS_ENROLLED_WITHOUT:
                case reader_admin_reports_options::USERS_ENROLLED_ALL:
                    list($enrolled, $params) = get_enrolled_sql($this->output->reader->context, 'mod/reader:viewbooks');
                    $select = 'u.id';
                    $from   = '{user} u JOIN ('.$enrolled.') e ON e.id = u.id';
                    $where  = 'u.deleted = 0';
                    $order  = 'id';
                    if ($usertype==reader_admin_reports_options::USERS_ENROLLED_WITH) {
                        list($from, $params) = $this->join_users_with_attempts('tmp', "$from JOIN", $params);
                    }
                    if ($usertype==reader_admin_reports_options::USERS_ENROLLED_WITHOUT) {
                        list($from, $params) = $this->join_users_with_attempts('tmp', "$from LEFT JOIN", $params);
                        $where .= ' AND tmp.userid IS NULL';
                    }
                    $this->users = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $order", $params);
                    break;

                case reader_admin_reports_options::USERS_ALL_WITH:
                    list($sql, $params) = $this->join_users_with_attempts();
                    $this->users = $DB->get_records_sql($sql, $params);
                    break;
            }
            /***********************
            it might be possible to restrict search to users in certain groups using the following code
            ************************
            if (array_key_exists('group', $this->filterfields) && array_key_exists('group', $SESSION->user_filtering) && count($SESSION->user_filtering['group'])) {
                $this->users = array();
                foreach ($SESSION->user_filtering['group'] as $data) {
                    list($sql, $params) = $this->filter->_fields['group']->get_sql_filter($data);
                    $this->users = array_merge($this->users, array_values($params));
                }
                $this->users = array_unique($this->users);
            } else {
                $this->users = get_enrolled_users($this->output->reader->context, 'mod/reader:viewbooks', 0, 'u.id', 'id');
                $this->users = array_keys($this->users);
            }
            ************************/
        }

        if (empty($this->users)) {
            return array('', array());
        }

        if ($prefix=='') {
            $type = SQL_PARAMS_QM;
        } else {
            $type = SQL_PARAMS_NAMED;
        }
        return $DB->get_in_or_equal(array_keys($this->users), $type, $prefix);
    }

    /**
     * join_users_with_attempts
     *
     * @param string $alias  (optional, default="")
     * @param string $join   (optional, default="")
     * @param array  $params (optional, default=array())
     * @return array(string, array())
     */
    public function join_users_with_attempts($alias='', $join='', $params=array()) {
        $select = 'DISTINCT userid';
        $from   = '{reader_attempts}';
        $where  = 'readerid = '.$this->output->reader->id.' AND deleted = 0';
        $order  = 'userid';
        $sql = "SELECT $select FROM $from WHERE $where ORDER BY $order";
        if ($alias) {
            $sql = "$join ($sql) $alias ON $alias.userid = u.id";
        }
        return array($sql, $params);
    }

    /**
     * get_userfields
     *
     * @param string $tableprefix name of database table prefix in query
     * @param array  $extrafields extra fields to be included in result (do not include TEXT columns because it would break SELECT DISTINCT in MSSQL and ORACLE)
     * @param string $idalias     alias of id field
     * @param string $fieldprefix prefix to add to all columns in their aliases, does not apply to 'id'
     * @return string
     */
     function get_userfields($tableprefix = '', array $extrafields = NULL, $idalias = 'id', $fieldprefix = '') {
        if (class_exists('user_picture')) { // Moodle >= 2.6
            return user_picture::fields($tableprefix, $extrafields, $idalias, $fieldprefix);
        }
        // Moodle <= 2.5
        $fields = array('id', 'firstname', 'lastname', 'picture', 'imagealt', 'email');
        if ($tableprefix || $extrafields || $idalias) {
            if ($tableprefix) {
                $tableprefix .= '.';
            }
            if ($extrafields) {
                $fields = array_unique(array_merge($fields, $extrafields));
            }
            if ($idalias) {
                $idalias = " AS $idalias";
            }
            if ($fieldprefix) {
                $fieldprefix = " AS $fieldprefix";
            }
            foreach ($fields as $i => $field) {
                $fields[$i] = "$tableprefix$field".($field=='id' ? $idalias : ($fieldprefix=='' ? '' : "$fieldprefix$field"));
            }
        }
        return implode(',', $fields);
        //return 'u.id AS userid, u.username, u.firstname, u.lastname, u.picture, u.imagealt, u.email';
    }

    /**
     * select_sql_attempts
     *
     * @params string $groupbyfield "reader_attempts" field name ("userid" or "quizid")
     * @return xxx
     */
    public function select_sql_attempts($groupbyfield) {
        list($usersql, $userparams) = $this->select_sql_users();

        // we ignore attempts before the "ignoredate"
        $ignoredate = $this->output->reader->ignoredate;
        $notfinished   = 'ra.timefinish IS NULL OR ra.timefinish = 0';

        $sum = "SUM(CASE WHEN (ra.readerid <> :reader1 OR $notfinished) THEN 0 ELSE (ra.percentgrade) END)";
        $count = "SUM(CASE WHEN (ra.readerid <> :reader2 OR $notfinished) THEN 0 ELSE 1 END)";
        $averagegrade  = "ROUND($sum / $count, 0)";

        $sum = "SUM(CASE WHEN (ra.readerid <> :reader3 OR $notfinished) THEN 0 ELSE (ra.timefinish - ra.timestart) END)";
        $count = "SUM(CASE WHEN (ra.readerid <> :reader4 OR $notfinished) THEN 0 ELSE 1 END)";
        $averageduration = "ROUND($sum / $count, 0)";

        $countpassed = "SUM(CASE WHEN (ra.readerid = :reader5 AND ra.passed = :passed1 AND ra.timefinish > :time1) THEN 1 ELSE 0 END)";
        $countfailed = "SUM(CASE WHEN (ra.readerid = :reader6 AND ra.passed <> :passed2 AND ra.timefinish > :time2) THEN 1 ELSE 0 END)";

        $select = "ra.$groupbyfield,".
                  "$averagegrade AS averagegrade,".
                  "$averageduration AS averageduration,".
                  "$countpassed AS countpassed,".
                  "$countfailed AS countfailed";

        $from   = "{reader_attempts} ra ".
                  "LEFT JOIN {reader_books} rb ON ra.bookid = rb.id";

        $where  = '';
        $params = array('reader1' => $this->output->reader->id,
                        'reader2' => $this->output->reader->id,
                        'reader3' => $this->output->reader->id,
                        'reader4' => $this->output->reader->id,
                        'reader5' => $this->output->reader->id,
                        'reader6' => $this->output->reader->id,
                        'passed1' => 'true', 'time1' => $ignoredate,  // countpassed (this term)
                        'passed2' => 'true', 'time2' => $ignoredate); // countfailed (this term)

        if ($this->output->reader->wordsorpoints==0) {
            // words
            $totalfield = 'rb.words';
            $totalalias = 'totalwords';
        } else {
            // points
            $totalfield = 'rb.length';
            $totalalias = 'totalpoints';
        }

        switch ($groupbyfield) {
            case 'userid':
                $totalthisterm = "SUM(CASE WHEN (ra.readerid = :reader7 AND ra.passed = :passed3 AND ra.timefinish > :time3) THEN $totalfield ELSE 0 END)";
                $totalallterms = "SUM(CASE WHEN (ra.passed = :passed4 AND ra.timefinish > :time4) THEN $totalfield ELSE 0 END)";

                $select .= ",$totalthisterm AS {$totalalias}thisterm".
                           ",$totalallterms AS {$totalalias}allterms";

                $params += array('reader7' => $this->output->reader->id,
                                 'passed3' => 'true', 'time3' => $ignoredate, // totalthisterm
                                 'passed4' => 'true', 'time4' => 0);          // totalallterms
                break;

            case 'bookid':
                $notrated    = "$notfinished OR ra.bookrating IS NULL";

                $countrating = "SUM(CASE WHEN (ra.readerid <> :reader7 OR $notrated) THEN 0 ELSE 1 END)";

                $sum = "SUM(CASE WHEN (ra.readerid <> :reader8 OR $notrated) THEN 0 ELSE ra.bookrating END)";
                $count = "SUM(CASE WHEN (ra.readerid <> :reader9 OR $notrated) THEN 0 ELSE 1 END)";
                $averagerating = "ROUND($sum / $count, 0)";

                $select     .= ",$countrating AS countrating".
                               ",$averagerating AS averagerating";
                $params += array('reader7' => $this->output->reader->id,
                                 'reader8' => $this->output->reader->id,
                                 'reader9' => $this->output->reader->id);
                break;
        }

        $where  .= "ra.userid $usersql";
        $params += $userparams;

        if (! array_key_exists('showdeleted', $this->optionfields)) {
            $where .= ' AND ra.deleted = :ra_deleted';
            $params['ra_deleted'] = self::DEFAULT_SHOWDELETED;
        }

        if (! array_key_exists('showhidden', $this->optionfields)) {
            $where .= ' AND rb.hidden = :rb_hidden';
            $params['rb_hidden'] = self::DEFAULT_SHOWHIDDEN;
        }

        if ($this->output->reader->bookinstances) {
            $from  .= ' LEFT JOIN {reader_book_instances} rbi ON rb.id = rbi.bookid';
            $where .= ' AND rbi.id IS NOT NULL AND rbi.readerid = :rbireader';
            $params['rbireader'] = $this->output->reader->id;
        }

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
    public function add_filter_params($select, $from, $where, $groupby, $having, $orderby, $params) {

        // get filter $sql and $params
        if ($this->filter) {
            list($filterwhere, $filterhaving, $filterparams) = $this->filter->get_sql_filter();
            if ($filterwhere) {
                $where .= ($where=='' ? '' : ' AND ').$filterwhere;
            }
            if ($filterhaving) {
                $having .= ($having=='' ? '' : ' AND ').$filterhaving;
            }
            if ($filterwhere || $filterhaving) {
                $params += $filterparams;
            }
        }

        if ($groupby) {
            $where .= " GROUP BY ".$this->get_table_names_and_aliases($groupby);
        }
        if ($having) {
            $where .= " HAVING $having"; // table aliases are NOT required
        }
        if ($orderby) {
            $where .= " ORDER BY ".$this->get_table_names_and_aliases($orderby);
        }

        return array($select, $from, $where, $params);
    }

    /**
     * get_table_name_and_alias
     *
     * @param string $fieldname
     * @return array($tablename, $tablealias, $jointype, $jointable, $joinconditions)
     * @todo Finish documenting this function
     */
    public function get_table_names_and_aliases($sql) {
        // search string to detect db fieldname in an sql string
        // - not preceded by {:`"'_. a-z 0-9
        // - starts with lowercase a-z
        // - followed by lowercase a-z, 0-9 or underscore
        // - not followed by }:`"'_. a-z 0-9
        $before = '[{:`"'."'".'a-zA-Z0-9_.]';
        $after  = '[}:`"'."'".'a-zA-Z0-9_.]';
        $search = "/(?<!$before)([a-z][a-z0-9_]*)(?!$after)/";

        // extract all database table names from the SQL
        if (preg_match_all($search, $sql, $matches, PREG_OFFSET_CAPTURE)) {
            $i_max = count($matches[0]) - 1;
            for ($i=$i_max; $i>=0; $i--) {
                list($match, $start) = $matches[1][$i];
                list($tablename, $tablealias) = $this->get_table_name_and_alias($match);
                if ($tablename && $tablealias) {
                    if (strpos($from, '{'.$tablename.'}')===false) {
                        $from .= ', {'.$tablename.'} '.$tablealias;
                    }
                    $sql = substr_replace($sql, "$tablealias.$match", $start, strlen($match));
                }
            }
        }

        return $sql;
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

            // "user" fields
            case 'id':
            case 'firstname':
            case 'lastname':
            case 'username':
                return array('user', 'u');

            // "reader_attempts" fields
            case 'percentgrade':
            case 'passed':
            case 'timestart':
            case 'timefinish':
            case 'bookrating':
                return array('reader_attempts', 'ra');

            default:
                die("What table alias for field: $fieldname");
        }
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to start and finish form (if required)                            //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * wrap_html_start
     */
    public function wrap_html_start() {

        // check this table has a "selected" column
        if (! $this->has_column('selected')) {
            return false;
        }

        // check user can manage attempts
        if (! $this->output->reader->can_manageattempts()) {
            return false;
        }

        // start form
        $url = $this->output->reader->reports_url();

        $params = array('id'=>'attemptsform', 'method'=>'post', 'action'=>$url->out_omit_querystring(), 'class'=>'mform');
        echo html_writer::start_tag('form', $params);

        // create hidden fields
        $hidden_fields = html_writer::input_hidden_params($url);
        $params = array('type'=>'hidden', 'name'=>'sesskey', 'value'=>sesskey());
        $hidden_fields .= html_writer::empty_tag('input', $params)."\n";

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

        // check user can manage attempts
        if (! $this->output->reader->can_manageattempts()) {
            return false;
        }

        $actions = $this->actions;
        if (count($actions)) {
            array_unshift($actions, 'noaction');

            // start "commands" div
            echo html_writer::start_tag('fieldset', array('class'=>'clearfix'));
            echo html_writer::tag('legend', get_string('actions'));

            foreach ($actions as $action) {
                $method = 'display_action_settings_'.$action;
                if (method_exists($this, $method)) {
                    $this->$method($action);
                } else {
                    $this->display_action_settings($action);
                }
            }

            // add action submit button
            echo html_writer::start_tag('div', array('class'=>'readerreportsubmit'));
            $confirm = addslashes_js(get_string('confirm'));
            $onclick = ''
                ."var found = 0;"
                ."if (this.form && this.form.elements) {"
                    ."var i_max = this.form.elements.length;"
                    ."for (var i=0; i<i_max; i++) {"
                        ."var obj = this.form.elements[i];"
                        ."if (obj.name.indexOf('selected')==0 && obj.checked) {"
                           ."found++;"
                        ."}"
                        ."obj = null;"
                    ."}"
                ."}"
                ."if (found) {"
                    ."found = confirm('$confirm');"
                ."} else {"
                    ."alert('Please select some users');"
                ."}"
                ."if(found) {"
                    ."if(this.form.elements['confirmed']) {"
                        ."this.form.elements['confirmed'].value = '1';"
                    ."}"
                    ."return true;"
                ."} else {"
                    ."return false;"
                ."}"
            ;
            echo html_writer::empty_tag('input', array('type'=>'submit', 'onclick'=>$onclick, 'name'=>'go', 'value'=>get_string('go')));
            echo html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'confirmed', 'value'=>'0'))."\n";
            echo html_writer::end_tag('div'); // readerreportsubmit DIV

            // finish "readerreportactions" fieldset
            echo html_writer::end_tag('fieldset'); // clearfix FIEDLSET
        }

        // finish the form
        echo html_writer::end_tag('form');
    }

    /**
     * finish_html
     *
     * override standard method so that we can save $SESSION values as user preferences
     */
    public function finish_html() {
        parent::finish_html();
        $this->set_user_preferences();
    }

    /**
     * display_action_settings
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings($action, $settings='') {
        echo html_writer::start_tag('div', array('id' => "readerreportaction_$action", 'class'=>'readerreportaction'));

        $name = 'action';
        $id = 'id_'.$name.'_'.$action;
        $onclick = '';

        $params = array('type'=>'radio', 'name'=>$name, 'id'=> $id, 'value'=>$action, 'onclick'=>$onclick);
        if ($action==optional_param($name, 'noaction', PARAM_ALPHA)) {
            $params['checked'] = 'checked';
        }

        echo html_writer::empty_tag('input', $params);
        echo html_writer::tag('label', get_string($action, 'mod_reader'), array('for'=>$id));

        if ($settings) {
            echo html_writer::tag('div', $settings, array('class' => 'actionsettings'));
        }

        echo html_writer::end_tag('div');
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to get and set user preferences                                  //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * get_user_preferences
     *
     * @uses $SESSION
     */
    public function get_user_preferences() {
        global $SESSION;

        $uniqueid = $this->uniqueid;
        if (empty($SESSION->flextable[$this->uniqueid])) {

            if (method_exists($this, 'is_persistent') && $this->is_persistent()) {
                // Moodle >= 2.9 with "persistent" table settings
                if ($prefs = get_user_preferences('flextable_'.$uniqueid)) {
                    $prefs = json_decode($prefs, true);
                } else {
                    $prefs = array(
                        'collapse' => array(),
                        'sortby'   => array(),
                        'i_first'  => '',
                        'i_last'   => '',
                        'textsort' => array()
                    );
                }
                if (empty($prefs['sortby'])) {
                    $prefs['sortby'] = array();
                }
                $sortby = &$prefs['sortby'];
            } else {
                // Moodle <= 2.8
                if ($prefs = get_user_preferences($this->uniqueid, null)) {
                    $prefs = unserialize(base64_decode($prefs));
                } else {
                    $prefs = new stdClass();
                }
                if (empty($prefs->sortby)) {
                    $prefs->sortby = array();
                }
                $sortby = &$prefs->sortby;
            }

            if (empty($sortby)) {
                foreach ($this->defaultsortcolumns as $column => $sortdirection) {
                    if ($this->has_column($column)) {
                        $sortby[$column] = $sortdirection;
                    }
                }
            }
            unset($sortby);

            if (empty($SESSION->flextable)) {
                $SESSION->flextable = array();
            }

            $SESSION->flextable[$this->uniqueid] = $prefs;
        }
    }

    /**
     * set_user_preferences
     *
     * @uses $SESSION
     */
    public function set_user_preferences() {
        global $SESSION;
        $uniqueid = $this->uniqueid;
        if (isset($SESSION->flextable[$uniqueid])) {
            $prefs = $SESSION->flextable[$uniqueid];
            if (method_exists($this, 'is_persistent') && $this->is_persistent()) {
                $prefs = json_encode($prefs);
                if ($prefs==get_user_preferences('flextable_'.$uniqueid)) {
                    // do nothing - $prefs have not changed
                } else {
                    set_user_preference('flextable_'.$this->uniqueid, $prefs);
                }
            } else {
                $prefs = base64_encode(serialize($prefs));
                if ($prefs==get_user_preferences($uniqueid, null)) {
                    // do nothing - $prefs have not changed
                } else {
                    set_user_preference($uniqueid, $prefs);
                }
            }
        }
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to execute requested $action                                     //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * execute_action
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action($action) {
        if ($action && in_array($action, $this->actions)) {
            $method = 'execute_action_'.$action;
            if (method_exists($this, $method)) {
                echo $this->$method($action);
            } else {
                debugging("Oops, action handler not found: $method");
            }
        }
    }

    /**
     * get_selected
     *
     * @param string $name
     * @param mixed  $default (optional, default=null)
     * @param mixed  $type    (optional, default=PARAM_INT) the PARAM type
     * @return xxx
     */
    public function get_selected($name, $default=null, $type=PARAM_INT) {
        if ($selected = reader_optional_param_array('selected', null, $type)) {
            if (isset($selected[$name])) {
                return $selected[$name];
            }
        }
        return $default;
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
     * header_studentview
     *
     * @return xxx
     */
    public function header_studentview()  {
        return '';
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
        if ($column=='selected' || $column=='studentview') {
            return '';
        } else {
            return parent::show_hide_link($column, $index);
        }
    }

    /**
     * header_countpassed
     *
     * @return xxx
     */
    public function header_countpassed()  {
        return get_string('countpassed', 'mod_reader');
    }

    /**
     * header_countfailed
     *
     * @return xxx
     */
    public function header_countfailed()  {
        return get_string('countfailed', 'mod_reader');
    }

    /**
     * header_averageduration
     *
     * @return xxx
     */
    public function header_averageduration()  {
        return get_string('averageduration', 'mod_reader');
    }

    /**
     * header_duration
     *
     * @return xxx
     */
    public function header_duration()  {
        return get_string('duration', 'mod_reader');
    }

    /**
     * header_averagegrade
     *
     * @return xxx
     */
    public function header_averagegrade()  {
        return get_string('averagegrade', 'mod_reader');
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
     * header_grade
     *
     * @return xxx
     */
    public function header_grade()  {
        return get_string('grade');
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
     * header_add_period
     *
     * @param string $header localised string from mod_reader language pack
     * @param string $period mod_reader string name for required period
     * @param string $help (optional, default="") string name for help
     * @return xxx
     */
    public function header_add_period($header, $period, $help='')  {
        $period = '('.get_string($period, 'mod_reader').')';
        if ($this->is_downloading()) { // $this->download
            $header = "$header $period";
        } else {
            $header = html_writer::tag('span', $header, array('class' => 'nowrap')).
                      html_writer::empty_tag('br').
                      html_writer::tag('span', $period, array('class' => 'nowrap'));
            if ($help) {
                $header .= $this->help_icon($help);
            }
        }
        return $header;
    }

    /**
     * header_currentlevel
     *
     * @return xxx
     */
    public function header_currentlevel() {
        return get_string('currentlevel', 'mod_reader');
    }

    /**
     * header_difficulty
     *
     * @return xxx
     */
    public function header_difficulty() {
        return get_string('bookdifficulty', 'mod_reader');
        //$long = get_string('difficulty', 'mod_reader');
        //$short = get_string('difficultyshort', 'mod_reader');
        //return $long.html_writer::empty_tag('br')."($short)";
    }

    /**
     * header_words
     *
     * @return xxx
     */
    public function header_words() {
        return get_string('words', 'mod_reader');
    }

    /**
     * header_points
     *
     * @return xxx
     */
    public function header_points() {
        return get_string('points', 'mod_reader');
    }

    /**
     * header_name
     *
     * @return xxx
     */
    public function header_name() {
        return get_string('booktitle', 'mod_reader');
    }

    /**
     * header_publisher
     *
     * @return xxx
     */
    public function header_publisher() {
        return get_string('publisher', 'mod_reader');
    }

    /**
     * header_level
     *
     * @return xxx
     */
    public function header_level() {
        return get_string('level', 'mod_reader');
    }

    /**
     * header_passed
     *
     * @return xxx
     */
    public function header_passed() {
        return implode('/', array(get_string('passedshort', 'mod_reader'), get_string('failedshort', 'mod_reader'), get_string('cheatedshort', 'mod_reader')));
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
     * empty_cell
     *
     * @return xxx
     */
    public function empty_cell()  {
        if ($this->download) {
            return '';
        } else {
            return '&nbsp;';
        }
    }


    /**
     * col_selected
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_selected($row)  {
        $checked = false;
        $key = key($row); // name of first column
        if ($selected = $this->get_selected($key)) {
            if (in_array($row->$key, $selected)) {
                $checked = true;
            }
        }
        return html_writer::checkbox('selected['.$key.'][]', $row->$key, $checked);
    }

    /**
     * col_studentview
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_studentview($row)  {
        global $USER;
        if ($USER->id==$row->userid) {
            $params = array('id' => $this->output->reader->cm->id);
            $url = new moodle_url('/mod/reader/view.php', $params);
        } else {
            $params = array('id' => $this->output->reader->cm->id, 'userid' => $row->userid);
            $url = new moodle_url('/mod/reader/view_loginas.php', $params);
        }
        $img = $this->output->pix_icon('t/preview', get_string('studentview', 'mod_reader'));
        return html_writer::link($url, $img);
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
     * col_words
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_words($row)  {
        $row->words = intval($row->words);
        return number_format($row->words);
    }

    /**
     * col_points
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_points($row)  {
        $row->points = floatval($row->points);
        return number_format($row->points);
    }

    /**
     * col_grade
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_grade($row)  {
        if (isset($row->grade)) {
            $grade = round($row->grade).'%';
            if (empty($row->layout)) {
                // NULL, "", or "0" means there are no questions in this attempt,
            } else {
                $params = array('id' => $this->output->reader->cm->id, 'attemptid' => $row->id);
                $url = new moodle_url('/mod/reader/view_attempts.php', $params);
                $grade = html_writer::link($url, $grade, array('onclick' => "this.target='_blank'"));
            }
            return $grade;
        } else {
            return $this->empty_cell();
        }
    }

    /**
     * col_timefinish
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_timefinish($row)  {
        return $this->col_time($row, 'timefinish');
    }

    /**
     * col_time
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_time($row, $colname)  {
        if (empty($row->$colname)) {
            return $this->empty_cell();
        }
        if ($this->download) {
            $fmt = get_string('strfattempttimeshort', 'mod_reader');
        } else {
            $fmt = get_string('strfattempttime', 'mod_reader');
        }
        return userdate($row->$colname, $fmt);
    }

    /**
     * col_averageduration
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_averageduration($row)  {
        return $this->col_duration($row, 'averageduration');
    }

    /**
     * col_duration
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_duration($row, $colname='duration')  {
        if (empty($row->$colname)) {
            return $this->empty_cell();
        }

        // prevent warnings on Moodle 2.0
        // and speed up later versions too
        if ($this->date_strings===null) {
            $this->date_strings = (object)array(
                'day'   => get_string('day'),
                'days'  => get_string('days'),
                'hour'  => get_string('hour'),
                'hours' => get_string('hours'),
                'min'   => get_string('min'),
                'mins'  => get_string('mins'),
                'sec'   => get_string('sec'),
                'secs'  => get_string('secs'),
                'year'  => get_string('year'),
                'years' => get_string('years'),
            );
        }

        return format_time($row->$colname, $this->date_strings);
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
            return $this->empty_cell();
        }
    }

    /**
     * col_passed
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_passed($row)  {
        if (isset($row->passed) && $row->passed=='true') {
            $text = get_string('passedshort', 'mod_reader');
            $class = 'passed';
        } else {
            $text = get_string('failedshort', 'mod_reader');
            $class = 'failed';
        }
        if ($this->download) {
            return $text;
        } else {
            return html_writer::tag('span', $text, array('class' => $class));
        }
    }

    /**
     * img_bookrating
     *
     * @param xxx $row
     * @return xxx
     */
    public function img_bookrating($rating)  {
        global  $CFG;
        static $img = null;
        $rating = intval($rating);
        if ($rating >= 1 && $rating <= 3) {
            if ($img===null) {
                if (file_exists($CFG->dirroot.'/pix/t/approve.png')) {
                    $src = $this->output->pix_url('t/approve'); // Moodle >= 2.4
                } else {
                    $src = $this->output->pix_url('t/clear'); // Moodle >= 2.0
                }
                $img = html_writer::empty_tag('img', array('src' => $src, 'alt' => get_string('bookrating', 'mod_reader')));
            }
            return str_repeat($img, $rating);
        } else {
            return '';
        }
    }

    /**
     * col_totalwordsthisterm
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_totalwordsthisterm($row) {
        $totalwordsthisterm = number_format($row->totalwordsthisterm);
        switch (true) {
            case isset($row->userid):
                $params = array('mode' => 'userdetailed', 'userid' => $row->userid);
                $report_url = $this->output->reader->reports_url($params);
                break;
            case isset($row->bookid):
                $params = array('mode' => 'bookdetailed', 'bookid' => $row->bookid);
                $report_url = $this->output->reader->reports_url($params);
                break;
            default:
                $report_url = '';
        }
        if ($report_url) {
        //    $totalwordsthisterm = html_writer::link($report_url, $totalwordsthisterm);
        }
        return $totalwordsthisterm;
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
        if (in_array($column, $this->numbercolumns) && is_numeric($row->$column)) {
            return number_format($row->$column);
        } else {
            return $this->format_text($row->$column);
        }
    }

    ////////////////////////////////////////////////////////////////////////////////
    // tool functions                                                          //
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
     * @param string $strname
     * @return string HTML fragment
     */
    public function help_icon($strname) {
        if ($this->is_downloading()) {
            return ''; // no help icon required
        } else {
            return $this->output->help_icon($strname, 'mod_reader');
        }
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

    /**
     * Generate the HTML for the sort link. This is a helper method used by {@link print_headers()}.
     * @param string $text the text for the link.
     * @param string $column the column name, may be a fake column like 'firstname' or a real one.
     * @param bool $isprimary whether the is column is the current primary sort column.
     * @param int $order SORT_ASC or SORT_DESC
     * @return string HTML fragment.
     */
    protected function sort_link($text, $column, $isprimary, $order) {

        $before = '';
        $after = '';

        $search = '/^(.*?)(<span [^>]*class="helptooltip"[^>]*>.*<\/span>)(.*?)$/';
        if (preg_match($search, $text, $matches)) {

            if ($text = trim($matches[1])) {
                $after  = trim($matches[2].$matches[3]);
            } else if ($text = trim($matches[3])) {
                $before = trim($matches[1].$matches[2]);
            } else {
                // only a help link - shouldn't happen !!
                return $matches[2];
            }
        }

        $text = parent::sort_link($text, $column, $isprimary, $order);
        return $before.$text.$after;
    }

    ////////////////////////////////////////////////////////////////////////////////
    // filters                                                                    //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * display_filters
     *
     * filters form was setup in setup_report_table()
     * because it may be required by execute_actions()
     *
     * @uses $DB
     */
    public function display_filters() {
        if ($this->filter) {
            // set number of rows per page
            if ($rowsperpage = $this->filter->get_optionvalue('rowsperpage')) {
                $this->pagesize = $rowsperpage;
            }
            // display the filters
            if ($this->download=='') {
                $this->filter->display_add();
                $this->filter->display_active();
                $this->filter->display_options();
            }
        }
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format, display and handle action settings                    //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * display_action_settings_deleteattempts
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_deleteattempts($action) {
        if ($this->filter && $this->filter->get_optionvalue('showdeleted')==0) {
            $this->display_action_settings($action);
        }
    }

    /**
     * display_action_settings_restoreattempts
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_restoreattempts($action) {
        if ($this->filter && $this->filter->get_optionvalue('showdeleted')==1) {
            $this->display_action_settings($action);
        }
    }

    /**
     * display_action_settings_passfailattempts
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_passfailattempts($action) {
        $value = optional_param($action, 0, PARAM_INT);
        $settings = '';
        $settings .= get_string('newsetting', 'mod_reader').': ';
        $options = array('true'    => get_string('passedshort', 'mod_reader').' - '.get_string('passed', 'mod_reader'),
                         'false'   => get_string('failedshort', 'mod_reader').' - '.get_string('failed', 'mod_reader'),
                         'cheated' => get_string('cheatedshort', 'mod_reader').' - '.get_string('cheated', 'mod_reader'));
        $settings .= html_writer::select($options, $action, $value, '', array());
        return $this->display_action_settings($action, $settings);
    }

    /**
     * execute_action_deleteattempts
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_deleteattempts($action) {
        return $this->execute_action_updateattempts('deleted', 1);
    }

    /**
     * execute_action_restoreattempts
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_restoreattempts($action) {
        return $this->execute_action_updateattempts('deleted', 0);
    }

    /**
     * execute_action_passfailattempts
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_passfailattempts($action) {
        $value = optional_param($action, '', PARAM_ALPHA);
        return $this->execute_action_updateattempts('passed', $value);
    }

    /**
     * execute_action_updateattempts
     *
     * @param string $table
     * @param string $field
     * @param mixed  $value
     * @return xxx
     */
    public function execute_action_updateattempts($field, $value) {
        $table = 'reader_attempts';
        $select = 'readerid = ?';
        $params = array($this->output->reader->id);
        return $this->execute_action_update('id', $table, $field, $value, $select, $params);
    }

    /**
     * execute_action_update
     *
     * @param string $idfield
     * @param string $table
     * @param string $field
     * @param mixed  $value
     * @param string $moreselect (optional, default='')
     * @param array  $moreparams (optional, default=null)
     * @return xxx
     */
    public function execute_action_update($idfield, $table, $field, $value, $moreselect='', $moreparams=null) {
        global $DB;

        // get selected record ids
        $ids = $this->get_selected($idfield);
        if (empty($ids)) {
            return; // no ids selected
        }

        // set $field $value for selected ids
        list($select, $params) = $DB->get_in_or_equal($ids);

        // add additional sql, if necessary
        if ($moreselect) {
            $select .= " AND $moreselect";
            if ($moreparams) {
                $params = array_merge($params, $moreparams);
                // for named keys use: $params += $moreparams
            }
        }

        $DB->set_field_select($table, $field, $value, "id $select", $params);

        // send "Changes saved" message to browser
        echo $this->output->notification(get_string('changessaved'), 'notifysuccess');
    }
}
