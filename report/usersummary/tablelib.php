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
require_once($CFG->dirroot.'/mod/reader/report/tablelib.php');

/**
 * reader_report_usersummary_table
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_report_usersummary_table extends reader_report_table {

    /** @var columns used in this table */
    protected $tablecolumns = array(
        'selected', 'username', 'fullname', 'startlevel', 'currentlevel', 'nopromote',
        'countpassed', 'countfailed', 'averageduration', 'averagegrade', 'wordsthisterm', 'wordsallterms'
    );

    /** @var suppressed columns in this table */
    protected $suppresscolumns = array();

    /** @var columns in this table that are not sortable */
    protected $nosortcolumns = array('nopromote');

    /** @var text columns in this table */
    protected $textcolumns = array('username', 'fullname');

    /** @var columns that are not to be center aligned */
    protected $leftaligncolumns = array('username', 'fullname');

    /** @var default sort columns */
    protected $defaultsortcolumns = array('username' => SORT_ASC, 'lastname' => SORT_ASC, 'firstname' => SORT_ASC);

    /** @var filter fields */
    protected $filterfields = array(
        'group'            =>0, 'realname'      => 0,
        'lastname'        => 1, 'firstname'     => 1, 'username'  => 1,
        'startlevel'      => 1, 'currentlevel'  => 1, 'nopromote' => 1,
        'countpassed'     => 1, 'countfailed'   => 1,
        'averageduration' => 1, 'averagegrade'  => 1,
        'wordsthisterm'   => 1, 'wordsallterms' => 1
    );

    /** @var actions */
    protected $actions = array('setcurrentlevel', 'setreadinggoal', 'awardextrapoints', 'awardbookpoints');

    /*
     * get_tablecolumns
     *
     * @return array of column names
     */
    public function get_tablecolumns() {
        global $DB;

        $tablecolumns = parent::get_tablecolumns();

        // sql to detect if "goal" has been set for this Reader activity
        $select = 'readerid = :readerid AND goal IS NOT NULL AND goal > :zero';
        $params = array('readerid' => $this->output->reader->id, 'zero' => 0);

        // add "goal" column if required
        if ($this->output->reader->goal || $DB->record_exists_select('reader_goal', $select, $params) || $DB->record_exists_select('reader_levels', $select, $params)) {
            $tablecolumns[] = 'goal';
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

        $select = 'u.id AS userid, u.username, u.firstname, u.lastname, u.picture, u.imagealt, u.email, '.
                  'raa.countpassed, raa.countfailed, '.
                  'raa.averageduration, raa.averagegrade, '.
                  'raa.wordsthisterm, raa.wordsallterms,'.
                  'rl.startlevel, rl.currentlevel, rl.nopromote, 0 AS goal';
        $from   = '{user} u '.
                  "LEFT JOIN ($attemptsql) raa ON raa.userid = u.id ".
                  'LEFT JOIN {reader_levels} rl ON u.id = rl.userid';
        $where  = "rl.readerid = :readerid AND u.id $usersql";

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
                return array('reader_levels', 'rl');

            // "reader_attempts" aggregate fields
            case 'countpassed':
            case 'countfailed':
            case 'averageduration':
            case 'averagegrade':
            case 'wordsthisterm':
            case 'wordsallterms':
                return array('', '');
                //return array('reader_attempts', 'raa');

            default:
                return parent::get_table_name_and_alias($fieldname);
        }
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

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format and display action settings                            //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * action_settings_setcurrentlevel
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_setcurrentlevel($action) {
        $value = optional_param($action, '', PARAM_INT);
        $settings = '';
        $settings .= get_string('newreadinglevel', 'reader').': ';
        $settings .= html_writer::select(range(0, 15), $action, $value, '', array());
        return $this->display_action_settings($action, $settings);
    }

    /**
     * action_settings_setreadinggoal
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_setreadinggoal($action) {
        $value = optional_param($action, '', PARAM_INT);
        $options = array_merge(range(1000, 20000, 1000), range(25000, 100000, 5000));
        $options = array_map('number_format', $options);
        $settings = '';
        $settings .= get_string('newreadinggoal', 'reader').': ';
        $settings .= html_writer::select($options, $action, $value, '', array());
        return $this->display_action_settings($action, $settings);
    }

    /**
     * action_settings_awardextrapoints
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_awardextrapoints($action) {
        $value = optional_param($action, '', PARAM_INT);
        $settings = '';
        $settings .= get_string('numberofextrapoints', 'reader').': ';
        $settings .= html_writer::empty_tag('input', array('type'=>'input', 'value'=>$value, 'name'=>$action, 'id'=>'id_'.$action, 'size'=>8, 'maxlength'=>8));
        return $this->display_action_settings($action, $settings);
    }

    /**
     * action_settings_awardbookpoints
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_awardbookpoints($action) {
        $settings = $this->output->available_items($action);
        return $this->display_action_settings($action, $settings);
    }
}
