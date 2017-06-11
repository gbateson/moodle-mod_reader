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
require_once($CFG->dirroot.'/mod/reader/admin/tablelib.php');

/**
 * reader_admin_reports_table
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_admin_reports_table extends reader_admin_table {

    /**#@+
    * default values for display options
    *
    * @const integer
    */
    const DEFAULT_USERTYPE    = 0;  // enrolled users with attempts
    const DEFAULT_BOOKTYPE    = 0;  // available books with attempts
    const DEFAULT_TERMTYPE    = 0;  // this term
    const DEFAULT_SHOWDELETED = 0;  // ignore deleted attempts
    const DEFAULT_SHOWHIDDEN  = 0;  // ignore hidden quizzes
    /**#@-*/

    /**#@+
    * boolean switches denoting whether or not there
    * are any records related to this Reader activity
    *
    * @var boolean
    */
    protected $has_attempts = null;
    protected $has_books    = null;
    protected $has_groups   = null;
    protected $has_users    = null;
    /**#@-*/

    /** array of users who can access this Reader activity */
    protected $users = null;

    /**
     * Constructor
     *
     * @param integer $uniqueid
     * @param object  $output renderer
     */
    public function __construct($uniqueid, $output) {
        parent::__construct($uniqueid, $output);

        // remove group filter if it is not needed
        if (isset($this->filterfields['group'])) {
            if ($this->groups_exist()==false) {
                unset($this->filterfields['group']);
                if ($i = array_search('groups', $this->tablecolumns)) {
                    array_splice($this->tablecolumns, $i, 1);
                }
            }
        }
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to extract data from $DB                                         //
    ////////////////////////////////////////////////////////////////////////////////

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
        } else {
            $type = ($prefix=='' ? SQL_PARAMS_QM : SQL_PARAMS_NAMED);
            return $DB->get_in_or_equal(array_keys($this->users), $type, $prefix);
        }
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
        $sql = "SELECT $select FROM $from WHERE $where";
        if ($alias) {
            $sql = "$join ($sql) $alias ON $alias.userid = u.id";
        }
        return array($sql, $params);
    }

    /**
     * select_sql_attempts
     *
     * called by select_sql() in summary reports:
     * usersummary, groupsummary, and booksummary
     *
     * @params string $groupbyfield "reader_attempts" field name ("userid" or "bookid")
     * @return xxx
     */
    public function select_sql_attempts($groupbyfield) {
        list($usersql, $userparams) = $this->select_sql_users();

        // cache some settings for this Reader acitivity
        // "ignoredate" is the start of the current term
        $readerid    = $this->output->reader->id;
        $maxduration = $this->output->reader->timelimit;
        $ignoredate  = $this->output->reader->ignoredate;

        $termtype = $this->filter->get_optionvalue('termtype');

        // average grade
        $exclude = $this->select_sql_attempts_exclude(1, $termtype);
        $sum = "SUM(CASE WHEN ($exclude) THEN 0 ELSE (ra.percentgrade) END)";
        $exclude = $this->select_sql_attempts_exclude(2, $termtype);
        $count = "SUM(CASE WHEN ($exclude) THEN 0 ELSE 1 END)";
        $averagegrade  = "ROUND($sum / $count, 0)";

        // sum duration
        $exclude = $this->select_sql_attempts_exclude(3, $termtype);
        $sum = 'ra.timefinish - ra.timestart';
        $sum = "CASE WHEN (ra.timefinish = ra.timestart) THEN :maxduration ELSE ($sum) END";
        $sum = "SUM(CASE WHEN ($exclude) THEN 0 ELSE ($sum) END)";

        // count duration
        $exclude = $this->select_sql_attempts_exclude(4, $termtype);
        $count = "SUM(CASE WHEN ($exclude) THEN 0 ELSE 1 END)";

        // average duration
        $averageduration = "ROUND($sum / $count, 0)";

        // count passed
        $include = $this->select_sql_attempts_include(5, $termtype);
        $include = "$include AND ra.passed = :passed5";
        $countpassed = "SUM(CASE WHEN ($include) THEN 1 ELSE 0 END)";

        // count failed
        $include = $this->select_sql_attempts_include(6, $termtype);
        $include = "$include AND ra.passed <> :passed6";
        $countfailed = "SUM(CASE WHEN ($include) THEN 1 ELSE 0 END)";

        $select = "ra.$groupbyfield,".
                  "$averagegrade AS averagegrade,".
                  "$averageduration AS averageduration,".
                  "$countpassed AS countpassed,".
                  "$countfailed AS countfailed";

        $from   = "{reader_attempts} ra ".
                  "LEFT JOIN {reader_books} rb ON ra.bookid = rb.id";

        $params = array('passed5' => 1,
                        'passed6' => 1,
                        'maxduration' => $maxduration);

        if ($termtype==reader_admin_reports_options::THIS_TERM) {
            $params['reader1'] = $readerid;
            $params['reader2'] = $readerid;
            $params['reader3'] = $readerid;
            $params['reader4'] = $readerid;
            $params['reader5'] = $readerid;
            $params['reader6'] = $readerid;
            $params['time1'] = $ignoredate;
            $params['time2'] = $ignoredate;
            $params['time3'] = $ignoredate;
            $params['time4'] = $ignoredate;
            $params['time5'] = $ignoredate;
            $params['time6'] = $ignoredate;
        }

        if ($this->output->reader->wordsorpoints==0) {
            // words
            $totalfield = 'rb.words';
            $totalalias = 'totalwords';
        } else {
            // points
            $totalfield = 'rb.points';
            $totalalias = 'totalpoints';
        }

        switch ($groupbyfield) {
            case 'userid': // usersummary AND groupsummary

                $include = $this->select_sql_attempts_include(7, reader_admin_reports_options::THIS_TERM);
                $include = "$include AND ra.passed = :passed7";
                $totalthisterm = "SUM(CASE WHEN ($include) THEN $totalfield ELSE 0 END)";

                $include = $this->select_sql_attempts_include(8, reader_admin_reports_options::ALL_TERMS);
                $include = "$include AND ra.passed = :passed8";
                $totalallterms = "SUM(CASE WHEN ($include) THEN $totalfield ELSE 0 END)";

                $select .= ",$totalthisterm AS {$totalalias}thisterm".
                           ",$totalallterms AS {$totalalias}allterms";

                $params += array('reader7' => $this->output->reader->id,
                                 'reader8' => $this->output->reader->id,
                                 'passed7' => 1, 'time7' => $ignoredate,
                                 'passed8' => 1, 'time8' => 0);
                break;

            case 'bookid': // booksummary
                $notrated = 'ra.timefinish IS NULL OR ra.timefinish = 0 OR ra.bookrating IS NULL';

                $exclude = "ra.readerid <> :reader7 OR $notrated";
                $countrating = "SUM(CASE WHEN ($exclude) THEN 0 ELSE 1 END)";

                $exclude = "ra.readerid <> :reader8 OR $notrated";
                $sum = "SUM(CASE WHEN ($exclude) THEN 0 ELSE ra.bookrating END)";

                $exclude = "ra.readerid <> :reader9 OR $notrated";
                $count = "SUM(CASE WHEN ($exclude) THEN 0 ELSE 1 END)";

                $averagerating = "ROUND($sum / $count, 0)";

                $select     .= ",$countrating AS countrating".
                               ",$averagerating AS averagerating";

                $params += array('reader7' => $this->output->reader->id,
                                 'reader8' => $this->output->reader->id,
                                 'reader9' => $this->output->reader->id);
                break;
        }

        if ($usersql) {
            $where = "ra.userid $usersql";
        } else {
            $where = '1=0'; // must keep MSSQL happy :-)
        }
        $params += $userparams;

        if (! array_key_exists('showdeleted', $this->optionfields)) {
            $where .= ' AND ra.deleted = :ra_deleted';
            $params['ra_deleted'] = self::DEFAULT_SHOWDELETED;
        }

        //if (! array_key_exists('showhidden', $this->optionfields)) {
        //    $where .= ' AND rb.hidden = :rb_hidden';
        //    $params['rb_hidden'] = self::DEFAULT_SHOWHIDDEN;
        //}

        //if ($this->output->reader->bookinstances) {
        //    $from  .= ' LEFT JOIN {reader_book_instances} rbi ON rb.id = rbi.bookid';
        //    $where .= ' AND rbi.id IS NOT NULL AND rbi.readerid = :rbi_readerid';
        //    $params['rbi_readerid'] = $this->output->reader->id;
        //}

        return array("SELECT $select FROM $from WHERE $where GROUP BY ra.$groupbyfield", $params);
    }

    /**
     * select_sql_attempts_exclude
     *
     * @params integer $i
     * @params integer $termtype (see reader_admin_reports_options::THIS_TERM/ALL_TERMS)
     * @return string
     */
    protected function select_sql_attempts_exclude($i, $termtype) {
        // restrict attempts to those that are not finished
        $sql = "ra.timefinish IS NULL OR ra.timefinish = 0";

        // if necessary, restrict attempts to those that are NOT for the current reader or the current term
        if ($termtype==reader_admin_reports_options::THIS_TERM) {
            $sql = "ra.readerid <> :reader$i OR $sql OR ra.timefinish < :time$i";
        }

        return $sql;
    }

    /**
     * select_sql_attempts_include
     *
     * @params integer $i
     * @params integer $termtype (see reader_admin_reports_options::THIS_TERM/ALL_TERMS)
     * @return string
     */
    protected function select_sql_attempts_include($i, $termtype) {
        // restrict attempts to those that are finished
        $sql = "ra.timefinish IS NOT NULL AND ra.timefinish > 0";

        // if necessary, restrict attempts to those for the current reader in the current term
        if ($termtype==reader_admin_reports_options::THIS_TERM) {
            $sql = "ra.readerid = :reader$i AND $sql AND ra.timefinish >= :time$i";
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
    // functions to detect whether or not records exists                          //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * attempts_exist
     */
    public function attempts_exist() {
        global $DB;
        if ($this->has_attempts===null) {
            $params = array('readerid' => $this->output->reader->id);
            $this->has_attempts = $DB->record_exists('reader_attempts', $params);
        }
        return $this->has_attempts;
    }

    /**
     * books_exist
     */
    public function books_exist() {
        global $DB;
        if ($this->has_books===null) {
            if (empty($this->output->reader->bookinstances)) {
                $this->has_books = $DB->record_exists('reader_books', array());
            } else {
                $params = array('readerid' => $this->output->reader->id);
                $this->has_books = $DB->record_exists('reader_book_instances', $params);
            }
        }
        return $this->has_books;
    }

    /**
     * groups_exist
     */
    public function groups_exist() {
        global $DB;

        if ($this->has_groups===null) {
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
                    $this->has_groups = $DB->record_exists('groups', array('courseid' => $courseid));
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

                    $this->has_groups = $DB->record_exists_sql("SELECT $select FROM $from WHERE $where", $params);
                    break;

                case NOGROUPS:
                default:
                    $this->has_groups = false;
                    break;
            }
        }
        return $this->has_groups;
    }

    /**
     * users_exist
     */
    public function users_exist() {
        if ($this->has_users===null) {
            if ($this->users===null) {
                list($select, $params) = $this->get_users_sql();
                $this->has_users = ($select=='' ? false : true);
            } else {
                $this->has_users = true;
            }
        }
        return $this->has_users;
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format header cells                                           //
    ////////////////////////////////////////////////////////////////////////////////

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
     * header_groups
     *
     * @return xxx
     */
    public function header_groups()  {
        return get_string('groups');
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
        if ($this->is_downloading()) {
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
     * header_passed
     *
     * @return xxx
     */
    public function header_passed() {
        return implode('/', array(get_string('passedshort', 'mod_reader'),
                                  get_string('failedshort', 'mod_reader'),
                                  get_string('cheatedshort', 'mod_reader')));
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format data cells                                             //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * col_groups
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_groups($row)  {
        global $DB;

        // We delay fetching the groups until the first time this function is called.
        // By this time we can restrict the userids to only those that are displayed
        // on the current page, so we can fetch all groups data with a single DB query.
        if (! isset($row->groups)) {

            $groups = '';
            switch ($DB->get_dbfamily()) {
                case 'mssql'    : $groups = "STUFF(SELECT ', ' + name FROM {groups} FOR XML PATH(''), 1, 1, '')"; break;
                case 'mysql'    : $groups = "GROUP_CONCAT(g.name SEPARATOR ', ')"; break;
                case 'oracle'   : $groups = "LISTAGG(g.name, ', ') WITHIN GROUP (ORDER BY g.name) 'groups'"; break;
                case 'postgres' : $groups = "string_agg(g.name, ', ')"; // "array_to_string(array(g.name), ',')"; break;
            }
            if ($groups) {
                $select = "u.id, $groups AS groups";
                $from   = '{user} u '.
                          'RIGHT JOIN {groups_members} gm ON u.id = gm.userid '.
                          'LEFT JOIN {groups} g ON gm.groupid = g.id';
                $params = $this->get_userids_from_rawdata();
                list($where, $params) = $DB->get_in_or_equal($params);
                $where  = "g.courseid = ? AND u.id $where";
                array_unshift($params, $this->output->reader->course->id);
                $groups = $DB->get_records_sql("SELECT $select FROM $from WHERE $where GROUP BY u.id", $params);
            }
            if ($groups==='' || $groups===false) {
                $groups = array();
            }
            foreach (array_keys($this->rawdata) as $id) {
                $userid = $this->rawdata[$id]->userid;
                if (empty($groups[$userid])) {
                    $this->rawdata[$id]->groups = '';
                } else {
                    $this->rawdata[$id]->groups = $groups[$userid]->groups;
                }
            }
        }

        return $row->groups;
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
     * col_grade
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_grade($row)  {
        if (isset($row->grade)) {
            $grade = round($row->grade).'%';
            if (empty($row->layout) || $this->is_downloading()) {
                // NULL, "", or "0" means there are no questions in this attempt,
            } else if ($this->output->reader->showreviewlinks) {
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
        if ($row->cheated) {
            $type = 'cheated';
        } else {
            $type = ($row->passed ? 'passed' : 'failed');
        }
        $text = get_string($type.'short', 'mod_reader');
        if ($this->download) {
            return $text;
        } else {
            return html_writer::tag('span', $text, array('class' => $type));
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
                if (method_exists($this->output, 'image_url')) {
                    $image_url = 'image_url'; // Moodle >= 3.3
                } else {
                    $image_url = 'pix_url'; // Moodle >= 3.2
                }
                if (file_exists($CFG->dirroot.'/pix/t/approve.png')) {
                    $src = $this->output->$image_url('t/approve'); // Moodle >= 2.4
                } else {
                    $src = $this->output->$image_url('t/clear'); // Moodle >= 2.0
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
        if ($this->is_downloading()) {
            $totalwordsthisterm = $row->totalwordsthisterm;
        } else {
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
        }
        return $totalwordsthisterm;
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
     * display_action_settings_updatepassed
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_updatepassed($action) {
        $value = optional_param($action, 0, PARAM_INT);
        $settings = '';
        $settings .= get_string('newsetting', 'mod_reader').': ';
        $options = array(0 => get_string('failedshort', 'mod_reader').' - '.get_string('failed', 'mod_reader'),
                         1 => get_string('passedshort', 'mod_reader').' - '.get_string('passed', 'mod_reader'));
        $settings .= html_writer::select($options, $action, $value, '', array());
        return $this->display_action_settings($action, $settings);
    }

    /**
     * display_action_settings_updatecheated
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_updatecheated($action) {
        $value = optional_param($action, 0, PARAM_INT);
        $settings = '';
        $settings .= get_string('newsetting', 'mod_reader').': ';
        $options = array(0 => get_string('no'),
                         1 => get_string('yes').' - '.get_string('cheated', 'mod_reader'));
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
     * execute_action_updatepassed
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_updatepassed($action) {
        $value = optional_param($action, '', PARAM_INT);
        return $this->execute_action_updateattempts('passed', $value);
    }

    /**
     * execute_action_updatecheated
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_updatecheated($action) {
        $value = optional_param($action, '', PARAM_INT);
        return $this->execute_action_updateattempts('cheated', $value);
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
}
