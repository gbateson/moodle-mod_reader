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
 * reader_admin_reports_usersummary_table
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_admin_reports_usersummary_table extends reader_admin_reports_table {

    /** @var columns used in this table */
    protected $tablecolumns = array(
        'selected', 'studentview', 'username', 'fullname', 'groups',
        'startlevel', 'currentlevel', 'stoplevel', 'allowpromotion', 'goal',
        'countpassed', 'countfailed', 'averageduration', 'averagegrade',
        'totalwordsthisterm', 'totalwordsallterms',
        'totalpointsthisterm', 'totalpointsallterms'
    );

    /** @var suppressed columns in this table */
    protected $suppresscolumns = array();

    /** @var columns in this table that are not sortable */
    protected $nosortcolumns = array('studentview', 'groups', 'allowpromotion');

    /** @var text columns in this table */
    protected $textcolumns = array('username', 'fullname');

    /** @var number columns in this table */
    protected $numbercolumns = array('startlevel', 'currentlevel', 'stoplevel', 'allowpromotion', 'goal',
                                     'countpassed', 'countfailed', 'totalthisterm', 'totalallterms');

    /** @var columns that are not to be center aligned */
    protected $leftaligncolumns = array('username', 'fullname');

    /** @var default sort columns */
    protected $defaultsortcolumns = array('username' => SORT_ASC); // , 'lastname' => SORT_ASC, 'firstname' => SORT_ASC

    /** @var filter fields ($fieldname => $advanced) */
    protected $filterfields = array(
        'group'           => 0, 'realname'      => 0,
        'lastname'        => 1, 'firstname'     => 1, 'username'  => 1,
        'startlevel'      => 1, 'currentlevel'  => 1, 'stoplevel' => 1, 'goal' => 1, 'allowpromotion' => 1,
        'countpassed'     => 1, 'countfailed'   => 1,
        'averageduration' => 1, 'averagegrade'  => 1,
        'totalthisterm'   => 1, 'totalallterms' => 1
    );

    /** @var option fields */
    protected $optionfields = array('rowsperpage' => self::DEFAULT_ROWSPERPAGE,
                                    'usertype'    => self::DEFAULT_USERTYPE,
                                    'sortfields'  => array());

    /** @var actions */
    protected $actions = array('setstartlevel',     'setcurrentlevel',  'setstoplevel',
                               'setallowpromotion', 'setpromotiontime', 'setreadinggoal',
                               'sendmessage',       'awardextrapoints', 'awardbookpoints');

    /**
     * Constructor
     *
     * @param int $uniqueid
     */
    public function __construct($uniqueid, $output) {
        $wordsfields = array('totalwordsthisterm', 'totalwordsallterms');
        $pointsfields = array('totalpointsthisterm', 'totalpointsallterms');
        $this->fix_words_or_points_fields($output, $wordsfields, $pointsfields);
        parent::__construct($uniqueid, $output);
    }

    /*
     * get_tablecolumns
     *
     * @return array of column names
     */
    public function get_tablecolumns() {
        global $DB;

        $tablecolumns = parent::get_tablecolumns();
        if (in_array('goal', $tablecolumns)==false) {

            // sql to detect if a "goal" has been set for this Reader activity
            $select = 'readerid = :readerid AND goal IS NOT NULL AND goal > :zero';
            $params = array('readerid' => $this->output->reader->id, 'zero' => 0);

            // add "goal" column if required
            if ($this->output->reader->goal && ($DB->record_exists_select('reader_goals', $select, $params) || $DB->record_exists_select('reader_levels', $select, $params))) {
                if ($last = array_pop($tablecolumns)) {
                    if ($last=='totalallterms') {
                        $tablecolumns[] = 'goal';
                        $tablecolumns[] = $last;
                    } else {
                        $tablecolumns[] = $last;
                        $tablecolumns[] = 'goal';
                    }
                }
            }
        }

        return $tablecolumns;
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to extract data from $DB                                         //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * select_sql
     *
     * @return xxx
     */
    function select_sql() {

        // get attempts at this Reader activity
        list($attemptsql, $attemptparams) = $this->select_sql_attempts('userid');

        // get users who can access this Reader activity
        list($usersql, $userparams) = $this->select_sql_users();

        $usertype = $this->filter->get_optionvalue('usertype');
        switch ($usertype) {

            case reader_admin_reports_options::USERS_ENROLLED_WITHOUT:
                $raa_join   = 'LEFT JOIN';
                $raa_join_u = 'raa.userid IS NULL';
                break;

            case reader_admin_reports_options::USERS_ENROLLED_ALL:
                $raa_join   = 'LEFT JOIN';
                $raa_join_u = '(raa.userid IS NULL OR raa.userid = u.id)';
                break;

            case reader_admin_reports_options::USERS_ENROLLED_WITH:
            case reader_admin_reports_options::USERS_ALL_WITH:
            default: // shouldn't happen !!
                $raa_join   = 'JOIN';
                $raa_join_u = 'raa.userid = u.id';
                break;

        }

        if ($this->output->reader->wordsorpoints==0) {
            $totalthisterm = 'totalwordsthisterm';
            $totalallterms = 'totalwordsallterms';
        } else {
            $totalthisterm = 'totalpointsthisterm';
            $totalallterms = 'totalpointsallterms';
        }

        $select = $this->get_userfields('u', array('username'), 'userid').', '.
                  'raa.countpassed, raa.countfailed, '.
                  'raa.averageduration, raa.averagegrade, '.
                  "raa.$totalthisterm, raa.$totalallterms,".
                  'rl.startlevel, rl.currentlevel, rl.stoplevel, rl.allowpromotion, rl.goal';
        $from   = '{user} u '.
                  "$raa_join ($attemptsql) raa ON $raa_join_u ".
                  "LEFT JOIN {reader_levels} rl ON (rl.readerid = :readerid AND rl.userid = u.id)";
        if ($usersql) {
            $where = "u.id $usersql";
        } else {
            $where = 'u.id > 0'; // must keep MSSQL happy :-)
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
            case 'startlevel':
            case 'currentlevel':
            case 'stoplevel':
            case 'allowpromotion':
            case 'goal':
                return array('reader_levels', 'rl');

            // "reader_attempts" aggregate fields
            case 'countpassed':
            case 'countfailed':
            case 'averageduration':
            case 'averagegrade':
            case 'totalthisterm':
            case 'totalallterms':
                return array('', '');
                //return array('reader_attempts', 'raa');

            default:
                return parent::get_table_name_and_alias($fieldname);
        }
    }

    /**
     * records_exist
     */
    public function records_exist() {
        return $this->users_exist();
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format header cells                                           //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * header_startlevel
     *
     * @return xxx
     */
    public function header_startlevel()  {
        return get_string('startlevel', 'mod_reader');
    }

    /**
     * header_currentlevel
     *
     * @return xxx
     */
    public function header_currentlevel()  {
        return get_string('currentlevel', 'mod_reader');
    }

    /**
     * header_stoplevel
     *
     * @return xxx
     */
    public function header_stoplevel()  {
        return get_string('stoplevel', 'mod_reader');
    }

    /**
     * header_allowpromotion
     *
     * @return xxx
     */
    public function header_allowpromotion()  {
        return get_string('allowpromotion', 'mod_reader');
    }

    /**
     * header_goal
     *
     * @return xxx
     */
    public function header_goal()  {
        return get_string('goal', 'mod_reader');
    }

    /**
     * header_totalwords
     *
     * @return xxx
     */
    public function header_totalwords()  {
        return get_string('totalwords', 'mod_reader');
    }

    /**
     * header_totalwordsthisterm
     *
     * @return xxx
     */
    public function header_totalwordsthisterm()  {
        $header = $this->header_totalwords();
        return $this->header_add_period($header, 'thisterm');
    }

    /**
     * header_totalallterms
     *
     * @return xxx
     */
    public function header_totalwordsallterms()  {
        $header = $this->header_totalwords();
        return $this->header_add_period($header, 'allterms');
    }

    /**
     * header_totalpoints
     *
     * @return xxx
     */
    public function header_totalpoints()  {
        return get_string('totalpoints', 'mod_reader');
    }

    /**
     * header_totalpointsthisterm
     *
     * @return xxx
     */
    public function header_totalpointsthisterm()  {
        $header = $this->header_totalpoints();
        return $this->header_add_period($header, 'thisterm');
    }

    /**
     * header_totalallterms
     *
     * @return xxx
     */
    public function header_totalpointsallterms()  {
        $header = $this->header_totalpoints();
        return $this->header_add_period($header, 'allterms');
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format data cells                                             //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * col_goal
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
                        if ($groupgoal = $DB->get_field('reader_goals', 'goal', array('readerid' => $readerid, 'groupid' => $groupid, 'level' => $level))) {
                            $goals[$groupid][$level] = $groupgoal; // level specific goal
                        } else if ($groupgoal = $DB->get_field('reader_goals', 'goal', array('readerid' => $readerid, 'groupid' => $groupid, 'level' => 0))) {
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
        }
        if ($this->is_downloading()) {
            return $goal;
        }
        return number_format($goal);
    }

    /**
     * col_allowpromotion
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_stoplevel($row)  {
        if (is_null($row->stoplevel) || $row->stoplevel==99) {
            return get_string('unlimited');
        }
        if ($this->is_downloading()) {
            return $row->stoplevel;
        }
        return number_format($row->stoplevel);
    }

    /**
     * col_allowpromotion
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_allowpromotion($row)  {
        if (is_null($row->allowpromotion) || $row->allowpromotion) {
            $text = get_string('yes');
            $class = 'passed';
        } else {
            $text = get_string('no');
            $class = 'failed';
        }
        if ($this->download) {
            return $text;
        } else {
            return html_writer::tag('span', $text, array('class' => $class));
        }
    }

    /**
     * col_totalwordsallterms
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_totalwordsallterms($row) {
        if ($this->is_downloading()) {
            return $row->totalwordsallterms;
        } else {
            return number_format($row->totalwordsallterms);
        }
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format, display and handle action settings                    //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * display_action_settings_setcurrentlevel
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_setstartlevel($action) {
        return $this->display_action_settings_setreadinglevel($action, false, true);
    }

    /**
     * display_action_settings_setcurrentlevel
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_setcurrentlevel($action) {
        return $this->display_action_settings_setreadinglevel($action, false, true);
    }

    /**
     * display_action_settings_setstoplevel
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_setstoplevel($action) {
        return $this->display_action_settings_setreadinglevel($action, true, true);
    }

    /**
     * display_action_settings_setallowpromotion
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_setallowpromotion($action) {
        $value = optional_param($action, 0, PARAM_INT);
        $options = array(0 => get_string('disallowpromotion', 'mod_reader'),
                         1 => get_string('allowpromotion',  'mod_reader'));
        $settings = '';
        //$settings .= get_string('newsetting', 'mod_reader').': ';
        $settings .= html_writer::select($options, $action, $value, '', $this->display_action_onclickchange($action, 'onchange'));
        return $this->display_action_settings($action, $settings);
    }

    /**
     * get_datetime
     *
     * @param string $name
     * @param mixed $default (optional, default = null)
     * @return xxx
     */
    public function get_datetime($name, $default=null) {

        if ($year = optional_param($name.'year', 0, PARAM_INT)) {
            $month     = optional_param($name.'month',   0, PARAM_INT);
            $day       = optional_param($name.'day',     0, PARAM_INT);
            $hours     = optional_param($name.'hours',   0, PARAM_INT);
            $minutes   = optional_param($name.'minutes', 0, PARAM_INT);
            $seconds   = optional_param($name.'seconds', 0, PARAM_INT);
            $valuezone = 99; // always 99
            $applydst  = false; // always false
            return make_timestamp($year, $month, $day, $hours, $minutes, $seconds, $valuezone, $applydst);
        } else {
            return $default;
        }
    }

    /**
     * display_action_settings_setpromotiontime
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_setpromotiontime($action) {

        $days = array();
        for ($i=1; $i<=31; $i++) {
            $days[$i] = $i;
        }
        $months = array();
        for ($i=1; $i<=12; $i++) {
            $months[$i] = userdate(gmmktime(12,0,0, $i,15,2000), '%B');
        }
        $years = array();
        for ($i=1970; $i<=2020; $i++) {
            $years[$i] = $i;
        }
        $hours = array();
        for ($i=0; $i<=23; $i++) {
            $hours[$i] = sprintf('%02d', $i);
        }
        $minutes = array();
        $seconds = array();
        for ($i=0; $i<60; $i++) {
            $minutes[$i] = sprintf('%02d', $i);
            $seconds[$i] = sprintf('%02d', $i);
        }

        $name = 'promotiontime';
        $value = $this->get_datetime($name, time());
        $value = usergetdate($value);

        $settings = '';
        $settings .= get_string('newdate', 'mod_reader').': ';
        $settings .= html_writer::select($years,   $name.'year',    intval($value['year']),    '').' ';
        $settings .= html_writer::select($months,  $name.'month',   intval($value['mon']),     '').' ';
        $settings .= html_writer::select($days,    $name.'day',     intval($value['mday']),    '').' ';
        $settings .= html_writer::empty_tag('br');
        $settings .= get_string('newtime', 'mod_reader').': ';
        $settings .= html_writer::select($hours,   $name.'hours',   intval($value['hours']),   '').' ';
        $settings .= html_writer::select($minutes, $name.'minutes', intval($value['minutes']), '').' ';
        $settings .= html_writer::select($seconds, $name.'seconds', intval($value['seconds']), '').' ';

        return $this->display_action_settings($action, $settings);
    }

    /**
     * display_action_settings_setreadinggoal
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_setreadinggoal($action) {
        $value = optional_param($action, 0, PARAM_INT);

        $options = array_merge(range(1000, 19000, 1000),
                               range(20000, 95000, 5000),
                               range(100000, 450000, 50000),
                               range(500000, 1000000, 100000));
        $options = array_combine($options, $options);
        $options = array_map('number_format', $options);

        $settings = '';
        $settings .= get_string('newreadinggoal', 'mod_reader').': ';
        $settings .= html_writer::select($options, $action, $value, '', $this->display_action_onclickchange($action, 'onchange'));
        return $this->display_action_settings($action, $settings);
    }

    /**
     * display_action_settings_sendmessage
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_sendmessage($action) {
        $settings = '';

        $name = $action.'subject';
        $value = optional_param($name, '', PARAM_TEXT);
        $settings .= get_string('subject', 'forum').': ';
        $params = array('name' => $name, 'value' => $value, 'type' => 'text', 'size' => 44);
        $params += $this->display_action_onclickchange($name, 'onchange');
        $settings .= html_writer::empty_tag('input', $params);

        $settings .= html_writer::empty_tag('br');

        $name = $action.'message';
        $value = optional_param($name, '', PARAM_TEXT);
        $settings .= get_string('message', 'forum').': ';
        $params = array('name' => $name, 'rows' => 2, 'cols' => 44);
        $params += $this->display_action_onclickchange($name, 'onchange');
        $settings .= html_writer::tag('textarea', $value, $params);

        return $this->display_action_settings($action, $settings);
    }

    /**
     * display_action_settings_awardextrapoints
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_awardextrapoints($action) {
        $value = optional_param($action, 0, PARAM_INT);
        $settings = '';
        $settings .= get_string('numberofextrapoints', 'mod_reader').': ';
        $options = $this->output->available_extrapoints();
        $settings .= html_writer::select($options, $action, $value, '', $this->display_action_onclickchange($action, 'onchange'));
        return $this->display_action_settings($action, $settings);
    }

    /**
     * display_action_settings_awardbookpoints
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_awardbookpoints($action) {

        $name = 'type';
        $options = $this->output->available_booktypes();

        $value = reader_downloader::BOOKS_WITHOUT_QUIZZES;
        $value = optional_param($name, $value, PARAM_INT);

        $params = array('action' => $action,
                        'mode'   => $this->output->mode,
                        'id'     => $this->output->reader->cm->id,
                        'type'   => ''); // will be added by javascript
        $onchange = $this->output->select_items_url('/mod/reader/view_books.php', $params);
        $onchange = "request($onchange, 'publishers')";

        $settings = '';
        $settings .= get_string($name, 'mod_reader').': ';
        $settings .= html_writer::select($options, $name, $value, '', $this->display_action_onclickchange($action, 'onchange', $onchange));
        $settings .= html_writer::empty_tag('br');

        $settings .= $this->output->select_items($action);
        return $this->display_action_settings($action, $settings);
    }

    /**
     * execute_action_setlevelfield
     *
     * @param string $action
     * @param string $field name
     * @param integer $value
     * @param integer $transfer (optional, default=FALSE)
     * @return void, but may update/insert record in "reader_levels" table
     */
    public function execute_action_setlevelfield($action, $field, $value, $transfer=false) {
        global $DB;

        if ($value===null) {
            return; // no value specified
        }

        if ($userids = $this->get_selected('userid')) {
            list($select, $params) = $this->select_sql_users();
            $userids = array_intersect($userids, $params);
        }

        if (empty($userids)) {
            return; // no (valid) userids selected
        }

        if ($transfer && $value===self::LEVEL_TRANSFER) {
            $name = $action.'transfer';
            if (! $courseid = optional_param($name, 0, PARAM_INT)) {
                return; // invalid courseid - shouldn't happen !!
            }
            $context = mod_reader::context(CONTEXT_COURSE, $courseid);
            if (! has_capability('mod/reader:viewreports', $context)) {
                return; // access denied - shouldn't happen !!
            }
            if (! $readerids = $DB->get_records('reader', array('course' => $courseid), 'id', 'id,course')) {
                return; // no readers - shouldn't happen !!
            }
            $readerids = array_keys($readerids);
            list($user_select, $user_params) = $DB->get_in_or_equal($userids);
            list($reader_select, $reader_params) = $DB->get_in_or_equal($readerids);
            $select = "userid, MAX($field) AS $field";
            $from   = '{reader_levels}';
            $where  = "readerid $reader_select AND userid $user_select";
            $params = array_merge($reader_params, $user_params);
            if (! $value = $DB->get_records_sql_menu("SELECT $select FROM $from WHERE $where GROUP BY userid", $params)) {
                return; // no level info - shouldn't happen !!
            }
            unset($user_select, $user_params, $reader_select, $reader_params);
            unset($courseid, $context, $readerids, $select, $from, $where, $params);
        }

        // update selected userids to the new value
        $time = time();
        foreach ($userids as $userid) {
            if (is_array($value) && ! array_key_exists($userid, $value)) {
                continue;
            }
            $params = array('userid' => $userid, 'readerid' => $this->output->reader->id);
            $level = $DB->get_record('reader_levels', $params);
            if ($level===false) {
                $level = (object)array(
                    'userid'         => $userid,
                    'readerid'       => $this->output->reader->id,
                    'startlevel'     => 0,
                    'currentlevel'   => 0,
                    'stoplevel'      => $this->output->reader->stoplevel,
                    'allowpromotion' => 1,
                    'goal'           => $this->output->reader->goal,
                    'time'           => $time,
                );
            }
            if (is_array($value)) {
                $v = $value[$userid]; // transfer value from another course
            } else {
                $v = $value;
            }
            if ($field=='currentlevel' && $level->$field < $v) {
                $level->time = $time; // manual promotion
            }
            $level->$field = $v;
            if (isset($level->id)) {
                $DB->update_record('reader_levels', $level);
            } else {
                $level->id = $DB->insert_record('reader_levels', $level);
            }
        }

        // send "Changes saved" message to browser
        echo $this->output->notification(get_string('changessaved'), 'notifysuccess');
    }

    /**
     * execute_action_setstartlevel
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_setstartlevel($action) {
        $field = 'startlevel';
        $value = optional_param($action, null, PARAM_INT);
        $this->execute_action_setlevelfield($action, $field, $value);
    }

    /**
     * execute_action_setcurrentlevel
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_setcurrentlevel($action) {
        $field = 'currentlevel';
        $value = optional_param($action, null, PARAM_INT);
        $this->execute_action_setlevelfield($action, $field, $value);
    }

    /**
     * execute_action_setstoplevel
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_setstoplevel($action) {
        $field = 'stoplevel';
        $value = optional_param($action, null, PARAM_INT);
        $this->execute_action_setlevelfield($action, $field, $value);
    }

    /**
     * execute_action_setallowpromotion
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_setallowpromotion($action) {
        $field = 'allowpromotion';
        $value = optional_param($action, null, PARAM_INT);
        $this->execute_action_setlevelfield($action, $field, $value);
    }

    /**
     * execute_action_setpromotiontime
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_setpromotiontime($action) {
        $field = 'time';
        $value = $this->get_datetime('promotiontime');
        $this->execute_action_setlevelfield($action, $field, $value);
    }

    /**
     * execute_action_setreadinggoal
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_setreadinggoal($action) {
        $field = 'goal';
        $value = optional_param($action, null, PARAM_INT);
        $this->execute_action_setlevelfield($action, $field, $value);
    }

    /**
     * execute_action_sendmessage
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_sendmessage($action) {
        global $DB, $USER;

        // get subject
        $subject = optional_param($action.'subject', '', PARAM_TEXT);
        if (trim($subject)=='') {
            return false; // no subject
        }

        $message = optional_param($action.'message', '', PARAM_TEXT);
        if (trim($message)=='') {
            return false; // no message
        }

        // get selected userids
        if ($userids = $this->get_selected('userid')) {
            list($select, $params) = $this->select_sql_users();
            $userids = array_intersect($userids, $params);
        }

        if (empty($userids)) {
            return false; // no (valid) userids selected
        }

        // send message to selected users userids
        $sentmessage = 0;
        foreach ($userids as $userid) {
            if ( ! $user = $DB->get_record('user', array('id' => $userid))) {
                continue; // invalid userid - shouldn't happen !!
            }
            if (! email_to_user($user, $USER, $subject, $message)) {
                continue; // email problems - shouldn't happen !!
            }
            $sentmessage++;
        }

        // send confirmation message to browser
        if ($sentmessage) {
            $sentmessage = get_string('sentmessage', 'mod_reader', $sentmessage);
            echo $this->output->notification($sentmessage, 'notifysuccess');
        }
    }

    /**
     * execute_action_awardextrapoints
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_awardextrapoints($action) {

        $extrapoints = optional_param($action, null, PARAM_INT);
        if ($extrapoints===null || $extrapoints < 0 || $extrapoints > 5) {
            return false; // no (valid) extra points specified
        }
        $length = floatval($extrapoints==0 ? '0.5' : "$extrapoints.0");

        if (! $book = $this->get_extrapoints_book($length)) {
            $params = array('id' => $this->output->reader->cm->id,
                            'tab' => mod_reader_admin_books_renderer::TAB_BOOKS_DOWNLOAD_WITH, // 33
                            'type' => reader_downloader::BOOKS_WITH_QUIZZES, // 1
                            'mode' => 'download');
            $url = new moodle_url('/mod/reader/admin/books.php', $params);
            $msg = get_string('downloadextrapoints', 'mod_reader');
            $msg = html_writer::link($url, $msg);
            echo $this->output->notification($msg, 'notifyproblem');
            return false; // shouldn't happen !!
        }

        // award extrapoints to selected userids
        $this->execute_action_awardpoints($book, true);
    }

    /**
     * get_extrapoints_book
     *
     * @param decimal $length
     * @return xxx
     */
    public function get_extrapoints_book($length) {
        global $DB;

        // try localized version of "Extra points"
        $params = array('publisher' => get_string('extrapoints', 'mod_reader'),
                        'level'     => '99',
                        'length'    => sprintf('%01.1f', $length)); // 0.5, 1.0, 2.0, ...
        if ($book = $DB->get_records('reader_books', $params)) {
            return reset($book);
        }

        // try downloaded version of "Extra_points"
        $params = array('publisher' => 'Extra_points',
                        'level'     => '99',
                        'length'    => sprintf('%01.2f', $length)); // 0.50, 1.00, 2.00, ...
        if ($book = $DB->get_records('reader_books', $params)) {
            return reset($book);
        }

        return false;
    }

    /**
     * execute_action_awardbookpoints
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_awardbookpoints($action) {
        global $DB;

        if (! $bookid = optional_param('bookid', 0, PARAM_INT)) {
            return; // no book id specified
        }

        if (! $book = $DB->get_record('reader_books', array('id' => $bookid))) {
            return false; // invalid book id
        }

        // award points for this book
        $this->execute_action_awardpoints($book, false);
    }

    /**
     * execute_action_awardpoints
     *
     * @param string  $book
     * @param boolean $allowmultiple
     * @return xxx
     */
    public function execute_action_awardpoints($book, $allowmultiple) {
        global $DB;

        // get selected userids
        if ($userids = $this->get_selected('userid')) {
            list($select, $params) = $this->select_sql_users();
            $userids = array_intersect($userids, $params);
        }

        if (empty($userids)) {
            return false; // no (valid) userids selected
        }

        // cache common settings
        $readerid = $this->output->reader->id;
        $cmid = $this->output->reader->cm->id;
        $contextid = $this->output->reader->context->id;


        // loop through userids
        $changessaved = false;
        foreach ($userids as $userid) {

            $params = array('readerid' => $readerid,
                            'userid'   => $userid,
                            'bookid'   => $book->id,
                            'preview'  => 0);
            if ($allowmultiple==false && $DB->record_exists('reader_attempts', $params)) {
                echo 'User (id='.$userid.') has already been awarded points for the selected book<br />';
                continue;
            }

            // we make the $time in the past, so it doesn't interfere with
            // the restriction on the frequency at which quizzes can be taken
            $time = time();
            $time -= $this->output->reader->get_delay($userid);

            // get next attempt number
            $select = 'MAX(attempt)';
            $from   = '{reader_attempts}';
            $where  = 'readerid = ? AND userid = ? AND timefinish > ? AND preview = ?';
            $params = array($readerid, $userid, 0, 0);

            if($attemptnumber = $DB->get_field_sql("SELECT $select FROM $from WHERE $where", $params)) {
                $attemptnumber += 1;
            } else {
                $attemptnumber = 1;
            }

            $attempt = (object)array(
                'uniqueid'     => reader_get_new_uniqueid($contextid, $book->quizid),
                'readerid'     => $readerid,
                'userid'       => $userid,
                'bookid'       => $book->id,
                'quizid'       => $book->quizid,
                'attempt'      => $attemptnumber,
                'sumgrades'    => 100.0,
                'percentgrade' => 100.0,
                'passed'       => 'true',
                'checkbox'     => 0,
                'timestart'    => $time,
                'timefinish'   => $time,
                'timecreated'  => $time,
                'timemodified' => $time,
                'layout'       => '0',
                'preview'      => 0,
                'bookrating'   => 0,
                'ip'           => getremoteaddr(),
            );

            // add new attempt record to $DB
            if ($attempt->id = $DB->insert_record('reader_attempts', $attempt)) {
                echo 'Added '.number_format($book->words).' points to the user: '.$userid.'<br />';
            } else {
                throw new reader_exception('Oops, could not create new attempt'); // shouldn't happen !!
            }

            // log this action
            reader_add_to_log($this->output->reader->course->id, 'reader', "AWP (userid: $userid; set: $book->words)", 'admin.php?id='.$cmid, $cmid);
            $changessaved = true;
        }

        // send "Changes saved" message to browser
        if ($changessaved) {
            echo $this->output->notification(get_string('changessaved'), 'notifysuccess');
        }
    }
}
