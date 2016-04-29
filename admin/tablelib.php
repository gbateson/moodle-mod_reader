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
 * Create a table to display records for a Reader activity
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
 * reader_admin_table
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_admin_table extends table_sql {

    /**#@+
    * default values for display options
    *
    * @const integer
    */
    const DEFAULT_ROWSPERPAGE = 30;
    const DEFAULT_SORTFIELDS  = ''; // i.e. no special sorting
    /**#@-*/

    /**#@+
    * special values for reading level
    *
    * @const integer
    */
    const LEVEL_TRANSFER = -1;
    /**#@-*/

    /** @var is_sortable (from flexible table) */
    public $is_sortable = true;

    /** @var sort_default_column (from flexible table) */
    public $sort_default_column = '';

    /** @var use_pages (from flexible table) */
    public $use_pages = true;

    /** @var use_initials (from flexible table) */
    public $use_initials = true;

    /** @var string field in the attempt records that refers to the user id (from flexible table) */
    public $useridfield = 'userid';

    /** @var mod_reader_admin_xxx_renderer for the current page */
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

    /** @var sync_table_preferences_value */
    protected $sync_table_preferences_value = false;

    /**
     * Constructor
     *
     * @param int    $uniqueid
     * @param object $output renderer for this Reader activity
     */
    public function __construct($uniqueid, $output) {
        parent::__construct($uniqueid);
        $this->output = $output;
        $this->strtimeformat = get_string($this->timeformat);
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
     * @param xxx $action
     * @param xxx $download
     * @param xxx $persistent (optional, default=FALSE)
     */
    public function setup_report_table($action, $download, $persistent=true)  {

        // fetch table preferences first
        // as they maybe required by filters
        $this->get_table_preferences();

        $tab = $this->output->tab;
        $mode = $this->output->mode;
        $baseurl = $this->output->baseurl();

        // set up download, if requested
        if ($download) {
            $title = $this->output->reader->course->shortname;
            $title .= ' '.$this->output->reader->name;
            $title .= ' '.get_string('report'.$mode, 'mod_reader');
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
        $this->set_attribute('class', $mode);

        // use persistent table settings in Moodle >= 2.9
        if (method_exists($this, 'is_persistent')) {
            $this->is_persistent($persistent);
        } else {
            $persistent = false;
        }

        // setup filter form, but don't display it yet
        // this must be done BEFORE calling parent::setup()
        if (count($this->filterfields) && $this->output->reader->can_viewreports()) {
            $classname = 'reader_admin_'.$tab.'_'.$mode.'_filtering';
            $this->filter = new $classname($this->filterfields, $baseurl, null, $this->optionfields, $this);

            // set number of rows per page
            if ($rowsperpage = $this->filter->get_optionvalue('rowsperpage')) {
                $this->pagesize = $rowsperpage;
            }
        }

        parent::setup();

        if ($this->has_persistent_table_preferences()) {
            // fetch persistent preferences into $SESSION
            // because this is not done in $this->setup()
            // see "lib/moodlelib.php" (around line 536)
            $this->get_persistent_table_preferences();
        } else {
            // save the current table preferences
            $this->set_table_preferences();
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
            if ($i !== false) {
                array_splice($tablecolumns, $i, 1);
            }
        }

        return $tablecolumns;
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to get and set user preferences                                  //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * get_table_preferences
     *
     * @uses $SESSION
     */
    public function get_table_preferences() {
        global $SESSION;

        $uniqueid = $this->uniqueid;
        switch (true) {
            case optional_param('treset', false, PARAM_BOOL):
                $prefs = null;
                break;

            case isset($SESSION->flextable[$uniqueid]):
                $prefs = $SESSION->flextable[$uniqueid];
                break;

            default:
                $prefs = $this->get_persistent_table_preferences();
                break;
        }

        if (method_exists($this, 'is_persistent')) {
            // Moodle >= 2.9 (preferences are stored as an ARRAY)
            if (empty($prefs)) {
                $prefs = array(
                    'collapse' => array(),
                    'i_first'  => '',
                    'i_last'   => '',
                    'textsort' => array()
                );
            } else if (is_object($prefs)) {
                $prefs = (array)$prefs;
            }
            if (empty($prefs['sortby'])) {
                $prefs['sortby'] = array();
            }
            if (empty($prefs['display'])) {
                $prefs['display'] = array();
            }
            $sortby = &$prefs['sortby'];
            $display = &$prefs['display'];
        } else {
            // Moodle <= 2.8 (preferences are stored as an OBJECT)
            if (empty($prefs)) {
                $prefs = (object)array(
                    'collapse' => array(),
                    'i_first'  => '',
                    'i_last'   => '',
                    'textsort' => array()
                );
            } else if (is_array($prefs)) {
                $prefs = (object)$prefs;
            }
            if (empty($prefs->sortby)) {
                $prefs->sortby = array();
            }
            if (empty($prefs->display)) {
                $prefs->display = array();
            }
            $sortby = &$prefs->sortby;
            $display = &$prefs->display;
        }

        // set default sort columns, if necessary
        if (empty($sortby)) {
            $sortby = $this->defaultsortcolumns;
            $display['sortfields'] = $this->defaultsortcolumns;
        }
        unset($sortby);
        unset($display);

        // update preferences in $SESSION object
        if (empty($SESSION->flextable)) {
            $SESSION->flextable = array();
        }

        $SESSION->flextable[$uniqueid] = $prefs;

        // update settings is case they have changed
        $this->set_table_preferences();
    }

    /**
     * has_persistent_table_preferences
     */
    public function has_persistent_table_preferences() {
        return (method_exists($this, 'is_persistent') && $this->is_persistent());
    }

    /**
     * get_persistent_table_preferences
     */
    public function get_persistent_table_preferences() {
        $uniqueid = $this->uniqueid;
        if ($this->has_persistent_table_preferences()) {
            // Moodle >= 2.9 with persistent table settings
            if ($prefs = get_user_preferences('flextable_'.$uniqueid)) {
                return json_decode($prefs, true);
            } else {
                return null;
            }
        } else {
            // Moodle <= 2.8 (or Moodle >= 2.9 non-persistent table settings)
            if ($prefs = get_user_preferences($uniqueid)) {
                $prefs = unserialize(base64_decode($prefs));
            } else {
                return null;
            }
        }
    }

    /**
     * set_table_preferences
     *
     * @uses $SESSION
     */
    public function set_table_preferences() {
        global $SESSION;

        $uniqueid = $this->uniqueid;
        if (isset($SESSION->flextable[$uniqueid])) {

            $prefs = $SESSION->flextable[$uniqueid];
            if (method_exists($this, 'is_persistent') && $this->is_persistent()) {
                // Moodle >= 2.9
                $prefs = json_encode($prefs);
                set_user_preference('flextable_'.$uniqueid, $prefs);
            } else {
                // Moodle <= 2.8
                $prefs = base64_encode(serialize($prefs));
                set_user_preference($uniqueid, $prefs);
            }
        }
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
            return array('COUNT(*)', "($temptable) temptable", '1=1', $params);
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
     * get_table_names_and_aliases
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
            default: debugging('Oops'); die("What table alias for field: $fieldname");
        }
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to start and finish form (if required)                            //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * records_exist
     */
    public function records_exist() {
        return false;
    }

    /**
     * nothing_to_display
     *
     * @param string $mode of the current page e.g. "usersummary"
     * @return void, but will display a message explaining why there is nothing to display
     */
    public function nothing_to_display($mode) {
        if ($this->records_exist()) {
            $text = get_string('norecordsmatch', 'mod_reader');
            $class = 'norecordsmatch';
        } else {
            $text = get_string('start'.$mode, 'mod_reader');
            $class = 'getstarted';
        }
        $text = format_text($text, FORMAT_MARKDOWN);
        echo html_writer::tag('div', $text, array('class' => $class));
    }

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
        $url = $this->output->tab.'_url';
        $url = $this->output->reader->$url();

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
            echo html_writer::start_tag('fieldset', array('class'=>'clearfix collapsible collapsed'));
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
            $selectsomerows = addslashes_js(get_string('selectsomerows', 'mod_reader'));
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
                    ."alert('$selectsomerows');"
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
     * display_action_settings
     *
     * @param string $action
     * @param string $settings (optional, default="")
     * @param string $label    (optional, default="")
     * @return xxx
     */
    public function display_action_settings($action, $settings='', $label='') {
        echo html_writer::start_tag('div', array('id' => "readerreportaction_$action", 'class'=>'readerreportaction'));

        $name = 'action';
        $id = 'id_'.$name.'_'.$action;
        $onclick = '';

        $params = array('type'=>'radio', 'name'=>$name, 'id'=> $id, 'value'=>$action, 'onclick'=>$onclick);
        if ($action==optional_param($name, 'noaction', PARAM_ALPHA)) {
            $params['checked'] = 'checked';
        }
        echo html_writer::empty_tag('input', $params);

        if ($label) {
            $label = get_string($label, 'mod_reader');
        } else {
            $label = get_string($action, 'mod_reader');
        }
        echo html_writer::tag('label', $label, array('for'=>$id));

        if ($settings) {
            echo html_writer::tag('div', $settings, array('class' => 'actionsettings'));
        }

        echo html_writer::end_tag('div');
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
        if ($column=='selected' || $column=='studentview') {
            return '';
        } else {
            return parent::show_hide_link($column, $index);
        }
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
        $key = key($row); // name of first column, e.g. "id"
        if ($selected = $this->get_selected($key)) {
            $checked = in_array($row->$key, $selected);
        } else {
            $checked = (isset($row->selected) && $row->selected);
        }
        return html_writer::checkbox('selected['.$key.'][]', $row->$key, $checked);
    }

    /**
     * col_words
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_words($row)  {
        $row->words = intval($row->words);
        if ($this->is_downloading()) {
            return $row->words;
        } else {
            return number_format($row->words);
        }
    }

    /**
     * col_points
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_points($row)  {
        $row->points = floatval($row->points);
        if ($this->is_downloading()) {
            return $row->points;
        } else {
            return number_format($row->points, 2);
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
        if ($this->is_downloading()) {
            return $row->$column;
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
            // display the filters
            if ($this->download=='') {
                if (count($this->rawdata)) {
                    $this->filter->display_add();
                }
                $this->filter->display_active();
                $this->filter->display_options();
            }
        }
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format, display and handle action settings                    //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * display_action_onclickchange
     *
     * @param string $action
     * @param string $type "onclick" or "onchange"
     * @param string $more (optional, default="")
     * @return xxx
     */
    public function display_action_onclickchange($action, $type, $more='') {
        return array($type => "var obj=document.getElementById('id_action_$action');if(obj)obj.checked=true;$more");
    }

    /**
     * display_action_settings_setreadinglevel
     *
     * @param string  $action
     * @param boolean $unlimited (optional, default=false)
     * @param boolean $transfer  (optional, default=false)
     * @return xxx
     */
    public function display_action_settings_setreadinglevel($action, $unlimited=false, $transfer=false) {
        $value = optional_param($action, 0, PARAM_INT);
        $options = range(0, 15);
        if ($unlimited) {
            $options += array(99 => get_string('unlimited'));
        }
        if ($transfer) {
            // Convert $transfer to an array of courses.
            // If there are no courses to transfer from,
            // then $transfer will be set to FALSE
            $transfer = $this->get_transfer_courses();
        }
        if ($transfer) {
            $options += array(self::LEVEL_TRANSFER => get_string('transferfromcourse', 'mod_reader'));
        }
        $settings = '';
        $settings .= get_string('newreadinglevel', 'mod_reader').': ';
        $settings .= html_writer::select($options, $action, $value, '', $this->display_action_onclickchange($action, 'onchange'));
        if ($transfer) {
            $name = $action.'transfer';
            $value = optional_param($name, 0, PARAM_INT);
            $settings .= ' '.html_writer::select($transfer, $name, $value, '', $this->display_action_onclickchange($action, 'onchange'));
        }
        return $this->display_action_settings($action, $settings);
    }

    /**
     * get_userids_from_rawdata
     *
     * @return array of $userids
     */
    public function get_userids_from_rawdata() {
        static $userids = null;
        if ($userids===null) {
            $userids = array();
            foreach (array_keys($this->rawdata) as $id) {
                $userids[$this->rawdata[$id]->userid] = true;
            }
            $userids = array_keys($userids);
        }
        return $userids;
    }

    /**
     * get_transfer_courses
     *
     * @return array of courses($id => $shortname) visible to this teacher which have level info about users in this course's reader
     */
    public function get_transfer_courses() {
        global $DB;
        static $courses = null;
        if ($courses===null) {
            $userids = $this->get_userids_from_rawdata();
            if (count($userids)) {
                $select = 'c.id, c.shortname';
                $from   = '{course} c';
                list($where, $params) = $DB->get_in_or_equal($userids);
                $where = 'SELECT DISTINCT r.course '.
                         'FROM {reader_levels} rl JOIN {reader} r ON rl.readerid = r.id '.
                         "WHERE rl.userid $where";
                $where = "c.id IN ($where) AND c.id <> ?";
                $params[] = $this->output->reader->course->id;
                if ($courses = $DB->get_records_sql_menu("SELECT $select FROM $from WHERE $where ORDER BY c.shortname", $params)) {
                    foreach (array_keys($courses) as $courseid) {
                        $context = mod_reader::context(CONTEXT_COURSE, $courseid);
                        if (! has_capability('mod/reader:viewreports', $context)) {
                            unset($courses[$courseid]);
                        }
                    }
                }
                if (count($courses)) {
                    $courses = array(0 => '') + $courses;
                } else {
                    $courses = false;
                }
            } else {
                $courses = false;
            }
        }
        return $courses;
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
