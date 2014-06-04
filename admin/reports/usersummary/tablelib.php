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
        'selected', 'studentview', 'username', 'fullname', 'startlevel', 'currentlevel', 'nopromote',
        'countpassed', 'countfailed', 'averageduration', 'averagegrade', 'wordsthisterm', 'wordsallterms'
    );

    /** @var suppressed columns in this table */
    protected $suppresscolumns = array();

    /** @var columns in this table that are not sortable */
    protected $nosortcolumns = array('nopromote');

    /** @var text columns in this table */
    protected $textcolumns = array('username', 'fullname');

    /** @var number columns in this table */
    protected $numbercolumns = array('startlevel', 'currentlevel', 'countpassed', 'countfailed', 'wordsthisterm', 'wordsallterms');

    /** @var columns that are not to be center aligned */
    protected $leftaligncolumns = array('username', 'fullname');

    /** @var default sort columns */
    protected $defaultsortcolumns = array('username' => SORT_ASC, 'lastname' => SORT_ASC, 'firstname' => SORT_ASC);

    /** @var filter fields ($fieldname => $advanced) */
    protected $filterfields = array(
        'group'           => 0, 'realname'      => 0,
        'lastname'        => 1, 'firstname'     => 1, 'username'  => 1,
        'startlevel'      => 1, 'currentlevel'  => 1, 'nopromote' => 1,
        'countpassed'     => 1, 'countfailed'   => 1,
        'averageduration' => 1, 'averagegrade'  => 1,
        'wordsthisterm'   => 1, 'wordsallterms' => 1
    );

    /** @var option fields */
    protected $optionfields = array('rowsperpage' => self::DEFAULT_ROWSPERPAGE);

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
        if ($this->output->reader->goal && ($DB->record_exists_select('reader_goals', $select, $params) || $DB->record_exists_select('reader_levels', $select, $params))) {
            if ($last = array_pop($tablecolumns)) {
                if ($last=='wordsallterms') {
                    $tablecolumns[] = 'goal';
                    $tablecolumns[] = $last;
                } else {
                    $tablecolumns[] = $last;
                    $tablecolumns[] = 'goal';
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

        $select = $this->get_userfields('u', array('username'), 'userid').', '.
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
        } else {
            return number_format($goal);
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
    public function display_action_settings_setcurrentlevel($action) {
        $value = optional_param($action, 0, PARAM_INT);
        $settings = '';
        $settings .= get_string('newreadinglevel', 'reader').': ';
        $settings .= html_writer::select(range(0, 15), $action, $value, '', array());
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

        $options = array_merge(range(1000, 20000, 1000), range(25000, 100000, 5000));
        $options = array_combine($options, $options);
        $options = array_map('number_format', $options);

        $settings = '';
        $settings .= get_string('newreadinggoal', 'reader').': ';
        $settings .= html_writer::select($options, $action, $value, '', array());
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
        $settings .= get_string('numberofextrapoints', 'reader').': ';
        $options = $this->output->available_extrapoints();
        $settings .= html_writer::select($options, $action, $value, '', array());
        return $this->display_action_settings($action, $settings);
    }

    /**
     * display_action_settings_awardbookpoints
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_awardbookpoints($action) {
        $settings = $this->output->available_items($action);
        return $this->display_action_settings($action, $settings);
    }

    /**
     * execute_action_setcurrentlevel
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_setcurrentlevel($action) {
        global $DB;

        $currentlevel = optional_param($action, null, PARAM_INT);
        if ($currentlevel===null) {
            return; // no current level specified
        }

        if ($userids = $this->get_selected('userid')) {
            list($select, $params) = $this->select_sql_users();
            $userids = array_intersect($userids, $params);
        }

        if (empty($userids)) {
            return; // no (valid) userids selected
        }

        // update selected userids to the new currentlevel
        list($select, $params) = $DB->get_in_or_equal($userids);
        $select = "userid $select AND readerid = ?";
        $params[] = $this->output->reader->id;
        $DB->set_field_select('reader_levels', 'time', time(), $select, $params);
        $DB->set_field_select('reader_levels', 'currentlevel', $currentlevel, $select, $params);

        // send "Changes saved" message to browser
        echo $this->output->notification(get_string('changessaved'), 'notifysuccess');
    }

    /**
     * execute_action_setreadinggoal
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_setreadinggoal($action) {
        global $DB;

        $readinggoal = optional_param($action, null, PARAM_INT);
        if ($readinggoal===null) {
            return; // no reading goal specified
        }

        if ($userids = $this->get_selected('userid')) {
            list($select, $params) = $this->select_sql_users();
            $userids = array_intersect($userids, $params);
        }

        if (empty($userids)) {
            return; // no (valid) userids selected
        }

        // update selected userids to the new readinggoal
        list($select, $params) = $DB->get_in_or_equal($userids);
        $select = "userid $select AND readerid = ?";
        $params[] = $this->output->reader->id;
        $DB->set_field_select('reader_levels', 'time', time(), $select, $params);
        $DB->set_field_select('reader_levels', 'goal', $readinggoal, $select, $params);

        // send "Changes saved" message to browser
        echo $this->output->notification(get_string('changessaved'), 'notifysuccess');
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
                            'tab' => mod_reader_admin_books_renderer::TAB_BOOKS_DOWNLOAD_WITH, // 32
                            'type' => reader_downloader::BOOKS_WITH_QUIZZES, // 1
                            'mode' => 'download');
            $url = new moodle_url('/mod/reader/admin/books.php', $params);
            $msg = get_string('downloadextrapoints', 'reader');
            $msg = html_writer::link($url, $msg);
            echo $this->output->notification($msg, 'notifyproblem');
            return false; // shouldn't happen !!
        }

        // award extrapoints to selected userids
        $this->execute_action_awardpoints($book);
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
        $params = array('publisher' => get_string('extrapoints', 'reader'),
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
        $this->execute_action_awardpoints($book);
    }

    /**
     * execute_action_awardpoints
     *
     * @param string $book
     * @param array  $userids
     * @return xxx
     */
    public function execute_action_awardpoints($book) {
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
        $cmid  = $this->output->reader->cm->id;
        $contextid = $this->output->reader->context->id;

        // we make the $time in the past, so it doesn't interfere with
        // the restriction on the frequency at which quizzes can be taken
        // "attemptsofday" is the minimum number of days (0..3) between quizzes
        $time  = time() - ($this->output->reader->attemptsofday * 3600 * 24);

        // loop through userids
        foreach ($userids as $userid) {

            // get next attempt number
            $select = 'MAX(attempt)';
            $from   = '{reader_attempts}';
            $where  = 'reader = ? AND userid = ? AND timefinish > ? AND preview != ?';
            $params = array($this->output->reader->id, $userid, 0, 1);

            if($attemptnumber = $DB->get_field_sql("SELECT $select FROM $from WHERE $where", $params)) {
                $attemptnumber += 1;
            } else {
                $attemptnumber = 1;
            }

            $attempt = (object)array(
                'uniqueid'     => reader_get_new_uniqueid($contextid, $book->quizid),
                'reader'       => $this->output->reader->id,
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
        }

        // send "Changes saved" message to browser
        echo $this->output->notification(get_string('changessaved'), 'notifysuccess');
    }
}
