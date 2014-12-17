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
 * reader_admin_reports_groupsummary_table
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_admin_reports_groupsummary_table extends reader_admin_reports_table {

    /** @var columns used in this table */
    protected $tablecolumns = array(
        'groupname', 'selected',
        'countactive', // number of students who have taken quizzes
        'countinactive', // number of students who hove NOT taken quizzes
        'percentactive', // percent of students who have taken quizzes
        'percentinactive', // percent of students who have NOT taken quizzes
        'averagetaken',  // average number of quizzes taken
        'averagepassed', // average number of quizzes passed
        'averagefailed', // average number of quizzes failed
        'averagepercentgrade', // average percent grade average
        'averagewordsthisterm', // average number of words this term
        'averagewordsallterms'  // average number of words all terms
    );

    /** @var suppressed columns in this table */
    protected $suppresscolumns = array();

    /** @var columns in this table that are not sortable */
    protected $nosortcolumns = array('percentactive', 'percentinactive',
                                     'averagetaken' , 'averagepassed'  , 'averagefailed',
                                     'averagepercentgrade', 'averagewordsthisterm', 'averagewordsallterms');

    /** @var text columns in this table */
    protected $textcolumns = array('groupname');

    /** @var number columns in this table */
    protected $numbercolumns = array('countactive', 'countinactive', 'averagetaken', 'averagepassed', 'averagefailed', 'averagewordsthisterm', 'averagewordsallterms');

    /** @var columns that are not to be center aligned */
    protected $leftaligncolumns = array('groupname');

    /** @var default sort columns */
    protected $defaultsortcolumns = array('groupname' => SORT_ASC);

    /** @var filter fields ($fieldname => $advanced) */
    protected $filterfields = array(
        //'groupname'   => 0,
        'countactive'   => 1, 'countinactive'   => 1,
        'percentactive' => 1, 'percentinactive' => 1,
        'averagetaken'  => 1, 'averagepassed'   => 1, 'averagefailed' => 1,
        'averagepercentgrade' => 1, 'averagewordsthisterm' => 1, 'averagewordsallterms' => 1
    );

    /** @var option fields */
    protected $optionfields = array('rowsperpage' => self::DEFAULT_ROWSPERPAGE,
                                    'sortfields'  => array());

    /** @var actions */
    protected $actions = array('setreadinggoal', 'sendmessage');

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
        list($attemptsql, $attemptparams) = $this->select_sql_attempts('userid');

        $select = 'g.id AS groupid, g.name AS groupname,'.
                  'COUNT(u.id) AS countusers,'.
                  'SUM(CASE WHEN (raa.userid IS NOT NULL AND (raa.countpassed > 0 OR raa.countfailed > 0)) THEN 1 ELSE 0 END) AS countactive,'.
                  'SUM(CASE WHEN (raa.userid IS NOT NULL AND (raa.countpassed > 0 OR raa.countfailed > 0)) THEN 0 ELSE 1 END) AS countinactive,'.
                  'SUM(raa.countpassed) AS countpassed,'.
                  'SUM(raa.countfailed) AS countfailed,'.
                  'SUM(raa.averagegrade) AS sumaveragegrade,'.
                  'SUM(raa.totalthisterm) AS totalthisterm,'.
                  'SUM(raa.totalallterms) AS totalallterms';

        $from   = '{user} u '.
                  "LEFT JOIN ($attemptsql) raa ON u.id = raa.userid ".
                  'LEFT JOIN {groups_members} gm ON u.id = gm.userid '.
                  'LEFT JOIN {groups} g ON gm.groupid = g.id';

        $where  = 'g.courseid = :courseid';

        $params = array('courseid' => $this->output->reader->course->id);

        return $this->add_filter_params($select, $from, $where, 'g.id', '', '', $params + $attemptparams);
    }

    /**
     * get_table_name_and_alias
     *
     * @param string $fieldname
     * @return array($tablename, $tablealias)
     * @todo Finish documenting this function
     */
    public function get_table_name_and_alias($fieldname) {
        switch ($fieldname) {

            case 'groupname':
            case 'countactive':
            case 'countinactive':
            case 'percentactive':
            case 'percentinactive':
            case 'averagetaken':
            case 'averagepassed':
            case 'averagefailed':
            case 'averagepercentgrade':
            case 'averagewordsthisterm':
            case 'averagewordsallterms':
                return array('', '');

            default:
                return parent::get_table_name_and_alias($fieldname);
        }
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format header cells                                           //
    ////////////////////////////////////////////////////////////////////////////////

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
        return get_string('countactive', 'mod_reader').$this->help_icon('countactive');
    }

    /**
     * header_countinactive
     *
     * @return string
     */
    public function header_countinactive() {
        return get_string('countinactive', 'mod_reader').$this->help_icon('countinactive');
    }

    /**
     * header_percentactive
     *
     * @return string
     */
    public function header_percentactive() {
        return get_string('percentactive', 'mod_reader').$this->help_icon('percentactive');
    }

    /**
     * header_percentinactive
     *
     * @return string
     */
    public function header_percentinactive() {
        return get_string('percentinactive', 'mod_reader').$this->help_icon('percentinactive');
    }

    /**
     * header_averagetaken
     *
     * @return string
     */
    public function header_averagetaken() {
        return get_string('averagetaken', 'mod_reader').$this->help_icon('averagetaken');
    }

    /**
     * header_averagepassed
     *
     * @return string
     */
    public function header_averagepassed() {
        return get_string('averagepassed', 'mod_reader').$this->help_icon('averagepassed');
    }

    /**
     * header_averagefailed
     *
     * @return string
     */
    public function header_averagefailed() {
        return get_string('averagefailed', 'mod_reader').$this->help_icon('averagefailed');
    }

    /**
     * header_averagepercentgrade
     *
     * @return string
     */
    public function header_averagepercentgrade() {
        return get_string('averagegrade', 'mod_reader').$this->help_icon('averagegrade');
    }

    /**
     * header_averagewords
     *
     * @param xxx $type (optional, default="") "", "thisterm" or "allterms"
     * @return xxx
     */
    public function header_averagewords($type='')  {
        $averagewords = get_string('averagewords', 'mod_reader');
        if ($type) {
            $averagewords .= ' ';
            $strtype = get_string($type, 'mod_reader');
            if ($this->is_downloading()) { // $this->download
                $averagewords .= "($strtype)";
            } else {
                $averagewords .= html_writer::tag('span', "($strtype)", array('class' => 'nowrap'));
                $averagewords .= $this->help_icon('averagewords'.$type);
            }

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

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format data cells                                             //
    ////////////////////////////////////////////////////////////////////////////////

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
            return number_format(round($row->totalthisterm / $row->countusers));
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
            return number_format(round($row->totalallterms / $row->countusers));
        }
    }

    /**
     * display_action_settings_setreadinggoal
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_setreadinggoal($action) {
        $settings = '';

        // all levels
        $name = $action.'[0]';
        $value = optional_param($name, '', PARAM_INT);
        $params = array('type' => 'input', 'id' => "id_$name", 'name' => $name, 'size' => 6, 'value' => $value);
        $settings .= get_string('alllevels', 'mod_reader').': '.html_writer::empty_tag('input', $params);

        // separate levels
        $settings .= html_writer::tag('div', get_string('separatelevels', 'mod_reader').':', array('class' => 'separate clearfix'));
        for ($col=0; $col<=1; $col++) {
            $settings .= html_writer::start_tag('ul', array('class' => 'levels', 'class' => 'levels'));
            for ($row=0; $row<=4; $row++) {
                $i = ($col * 5) + $row + 1;
                $name = $action."[$i]";
                $value = optional_param($name, '', PARAM_INT);
                $params = array('type' => 'input', 'id' => "id_$name", 'name' => $name, 'size' => 6, 'value' => $value);
                $level = get_string('leveli', 'mod_reader', $i).': '.html_writer::empty_tag('input', $params);
                $settings .= html_writer::tag('li', $level, array('class' => 'level'));
            }
            $settings .= html_writer::end_tag('ul');
        }
        $settings .= html_writer::tag('div', '', array('class' => 'clearfix'));

        return $this->display_action_settings($action, $settings);
    }

    /**
     * display_action_settings_sendmessage
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_sendmessage($action) {
        global $CFG;
        require_once($CFG->dirroot.'/lib/form/editor.php');

        $settings = '';

        // time (as number of hours) for which message should be displayed
        $name = $action.'time';
        $settings .= get_string($name, 'mod_reader').': ';
        $options = array('168' => '1 Week',
                         '240' => '10 Days',
                         '336' => '2 Weeks',
                         '504' => '3 Weeks');
        $value = optional_param($name, 0, PARAM_INT);
        $settings .= html_writer::select($options, $name, $value, '');

        // message text
        //  - generate id
        //  - disable file uploads
        $name = $action.'text';
        $editor = new MoodleQuickForm_editor($name, get_string($name, 'mod_reader'));
        $editor->updateAttributes(array('id' => 'id_'.$name));
        $editor->setMaxfiles(0);
        $settings .= $editor->toHtml();

        return $this->display_action_settings($action, $settings);
    }

    /**
     * execute_action_setreadinggoal
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_setreadinggoal($action) {
        global $DB;

        $readinggoal = optional_param_array($action, null, PARAM_INT);

        if ($readinggoal===null) {
            return; // no reading goal specified
        }

        $groupids = $this->get_selected('groupid');
        if (empty($groupids)) {
            return; // no ids selected
        }

        $params = array('readerid' => $this->output->reader->id);
        if ($goalids = $DB->get_records('reader_goals', $params, null, 'id,readerid')) {
            $goalids = array_keys($goalids);
        } else {
            $goalids = array();
        }

        foreach ($groupids as $groupid) {
            for ($level=0; $level<=10; $level++) {

                // get goal from form (it should be there)
                if (array_key_exists($level, $readinggoal)) {
                    $goal = $readinggoal[$level];
                } else {
                    $goal = 0; // shoudn't happen !!
                }

                // skip "All levels", if it is not used
                if ($level==0 && $goal==0) {
                    continue;
                }

                // convert $goal to a DB record
                $goal = (object)array(
                    'readerid' => $this->output->reader->id,
                    'groupid'  => $groupid,
                    'level'    => $level,
                    'goal'     => $goal
                );

                // insert new $goal record
                // reuse previous goal ids if possible
                if (empty($goalids)) {
                    $goal->id = $DB->insert_record('reader_goals', $goal);
                } else {
                    $goal->id = array_shift($goalids);
                    $DB->update_record('reader_goals', $goal);
                }

                // stop here if we re not using separate levels
                if ($level==0) {
                    break;
                }
            }
            // remove any used records from "reader_goals"
            if (count($goalids)) {
                list($select, $params) = $DB->get_in_or_equal($goalids);
                $DB->delete_records_select('reader_goals', $select, $params);
            }
        }

        // send "Changes saved" message to browser
        echo $this->output->notification(get_string('changessaved'), 'notifysuccess');
    }

    /**
     * execute_action_sendmessage
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_sendmessage($action) {
        global $DB, $PAGE, $USER;

        $time = $action.'time';
        $text = $action.'text';

        if (! $data = data_submitted()) {
            return; // no form data !!
        }
        if (empty($data->$text)) {
            return; // no message data
        }

        $groupids = $this->get_selected('groupid');
        if (empty($groupids)) {
            return; // no ids selected
        }

        // extract message time
        if ($time = clean_param($data->$time, PARAM_INT)) {
            $time = time() + ($time * 60 * 60);
        } else {
            $time = 0; // i.e. display indefinitely
        }

        // extract message text
        $text   = $data->$text;
        $format = clean_param($text['format'], PARAM_INT);
        $text   = clean_param($text['text'],   PARAM_RAW);

        // verify groupids
        list($select, $params) = $DB->get_in_or_equal($groupids);
        $select = "id $select AND courseid = ?";
        $params[] = $this->output->reader->course->id;

        if ($groups = $DB->get_records_select('groups', $select, $params, 'id', 'id,courseid')) {
            $message = (object)array(
                'readerid'      =>  $this->output->reader->id,
                'teacherid'     =>  $USER->id,
                'groupids'       =>  implode(',', array_keys($groups)),
                'messagetext'   =>  $text,
                'messageformat' =>  $format,
                'timefinish'    =>  $time,
                'timemodified'  =>  time()
            );
            if (isset($message->id)) {
                $DB->update_record('reader_messages', $message);
            } else {
                $message->id = $DB->insert_record('reader_messages', $message);
            }
        }
    }
}
