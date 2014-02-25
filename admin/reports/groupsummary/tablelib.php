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
        'groupname',
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
    protected $nosortcolumns = array();

    /** @var text columns in this table */
    protected $textcolumns = array('groupname');

    /** @var columns that are not to be center aligned */
    protected $leftaligncolumns = array('groupname');

    /** @var default sort columns */
    protected $defaultsortcolumns = array('groupname' => SORT_ASC);

    /** @var filter fields */
    protected $filterfields = array(
        'groupname'     => 0,
        'countactive'   => 1, 'countinactive'   => 1,
        'percentactive' => 1, 'percentinactive' => 1,
        'averagetaken'  => 1, 'averagepassed'   => 1, 'averagefailed' => 1,
        'averagepercentgrade' => 1, 'averagewordsthisterm' => 1, 'averagewordsallterms' => 1
    );

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
                  'SUM(raa.wordsthisterm) AS wordsthisterm,'.
                  'SUM(raa.wordsallterms) AS wordsallterms';

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
        return get_string('countactive', 'reader').$this->help_icon('countactive');
    }

    /**
     * header_countinactive
     *
     * @return string
     */
    public function header_countinactive() {
        return get_string('countinactive', 'reader').$this->help_icon('countinactive');
    }

    /**
     * header_percentactive
     *
     * @return string
     */
    public function header_percentactive() {
        return get_string('percentactive', 'reader').$this->help_icon('percentactive');
    }

    /**
     * header_percentinactive
     *
     * @return string
     */
    public function header_percentinactive() {
        return get_string('percentinactive', 'reader').$this->help_icon('percentinactive');
    }

    /**
     * header_averagetaken
     *
     * @return string
     */
    public function header_averagetaken() {
        return get_string('averagetaken', 'reader').$this->help_icon('averagetaken');
    }

    /**
     * header_averagepassed
     *
     * @return string
     */
    public function header_averagepassed() {
        return get_string('averagepassed', 'reader').$this->help_icon('averagepassed');
    }

    /**
     * header_averagefailed
     *
     * @return string
     */
    public function header_averagefailed() {
        return get_string('averagefailed', 'reader').$this->help_icon('averagefailed');
    }

    /**
     * header_averagepercentgrade
     *
     * @return string
     */
    public function header_averagepercentgrade() {
        return get_string('averagegrade', 'reader').$this->help_icon('averagegrade');
    }

    /**
     * header_averagewords
     *
     * @param xxx $type (optional, default="") "", "thisterm" or "allterms"
     * @return xxx
     */
    public function header_averagewords($type='')  {
        $averagewords = get_string('averagewords', 'reader');
        if ($type) {
            $strtype = get_string($type, 'reader').' ';
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
}
