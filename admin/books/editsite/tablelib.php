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
require_once($CFG->dirroot.'/mod/reader/admin/books/tablelib.php');

/**
 * reader_admin_books_editsite_table
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_admin_books_editsite_table extends reader_admin_books_table {

    /** @var columns used in this table */
    protected $tablecolumns = array(
        'publisher', 'level', 'selected', 'name', 'genre', 'difficulty', 'words', 'points', 'hidden', 'quiz', 'attempts', 'available'
        // 'image', how do we display/update this field?
        // 'maxtime' is this the time limit for a quiz?
        // the following fields can probably be deleted:
        // 'fiction'
    );

    /** @var suppressed columns in this table */
    protected $suppresscolumns = array('publisher', 'level');

    /** @var columns in this table that are not sortable */
    protected $nosortcolumns = array();

    /** @var text columns in this table */
    protected $textcolumns = array('publisher', 'level', 'name');

    /** @var number columns in this table */
    protected $numbercolumns = array('difficulty');

    /** @var columns that are not to be center aligned */
    protected $leftaligncolumns = array('publisher', 'level', 'name');

    /** @var default sort columns */
    protected $defaultsortcolumns = array('publisher' => SORT_ASC, 'level' => SORT_ASC, 'name' => SORT_ASC);

    /** @var filter fields ($fieldname => $advanced) */
    protected $filterfields = array(
        'publisher' => 0, 'level' => 1, 'name' => 0,
        'genre' => 1, 'difficulty' => 1, 'words' => 1, 'points' => 1, 'hidden' => 1,
        'quiz' => 1, 'attempts' => 1, 'available' => 1
    );

    /** @var option fields */
    protected $optionfields = array('rowsperpage' => self::DEFAULT_ROWSPERPAGE,
                                    'sortfields'  => array());

    /** @var actions */
    protected $actions = array('setpublisher', 'setlevel', 'setname', 'setgenre',
                               'setdifficulty', 'setwords', 'setpoints', 'showhidebook',
                                // 'changequiz'
                                // 'forcedownload'
                               'removebook', 'makebookavailable');

    /** @var maintable */
    protected $maintable = 'reader_books';

    /**
     * Constructor
     *
     * @param int    $uniqueid
     * @param object $output renderer for this Reader activity
     */
    public function __construct($uniqueid, $output) {
        if (empty($output->reader->bookinstances)) {
            unset($this->tablecolumns[array_search('available', $this->tablecolumns)]);
            unset($this->filterfields['available']);
            unset($this->actions[array_search('makebookavailable', $this->actions)]);
        }
        parent::__construct($uniqueid, $output);
    }

    /**
     * select_sql
     *
     * @return array($select, $from, $where, $params)
     */
    public function select_sql() {
        $select = 'rb.*, '.
                  '(CASE WHEN (rb.quizid IS NULL OR rb.quizid = 0) THEN 0 ELSE 1 END) AS quiz, '.
                  '(SELECT COUNT(*) FROM {reader_attempts} ra WHERE rb.id = ra.bookid AND ra.deleted = 0) AS attempts';
        $from   = '{reader_books} rb';
        $where  = 'rb.level <> :level';
        $params = array('level' => 99);
        if ($this->output->reader->bookinstances) {
            $select .= ', (CASE WHEN rbi.id IS NULL THEN 0 ELSE 1 END) AS available';
            $from   .= ' LEFT JOIN {reader_book_instances} rbi ON rbi.readerid = :readerid AND rb.id = rbi.bookid';
            $params['readerid'] = $this->output->reader->id;
        }
        return $this->add_filter_params($select, $from, $where, '', '', '', $params);
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format header cells                                           //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * header_genre
     *
     * @return xxx
     */
    public function header_hidden() {
        return get_string('hidden', 'mod_reader');
    }

    /**
     * header_genre
     *
     * @return xxx
     */
    public function header_genre() {
        return get_string('genre', 'mod_reader');
    }

    /**
     * header_quiz
     *
     * @return xxx
     */
    public function header_quiz() {
        return get_string('modulename', 'mod_quiz');
    }

    /**
     * header_attempts
     *
     * @return xxx
     */
    public function header_attempts() {
        return get_string('attempts', 'mod_reader');
    }

    /**
     * header_available
     *
     * @return xxx
     */
    public function header_available() {
        return get_string('available', 'mod_reader');
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format data cells                                             //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * col_hidden
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_hidden($row)  {
        if ($row->hidden) {
            return get_string('hide');
        } else {
            return get_string('show');
        }
    }

    /**
     * col_genre
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_genre($row)  {
        if (empty($row->genre)) {
            return '';
        } else {
            return mod_reader_renderer::valid_genres($row->genre, html_writer::empty_tag('br'));
        }
    }

    /**
     * col_quiz
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_quiz($row)  {
        if (empty($row->quizid)) {
            return ''; // get_string('no')
        } else {
            $url = new moodle_url('/mod/quiz/view.php', array('q' => $row->quizid));
            return html_writer::link($url, get_string('yes'), array('onclick' => 'this.target="_blank"'));
        }
    }

    /**
     * col_available
     *
     * @param xxx $row
     * @return xxx
     */
    public function col_available($row)  {
        if (empty($row->available)) {
            return get_string('no');
        } else {
            return get_string('yes');
        }
    }

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format, display and handle action settings                    //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * display_action_settings_settext
     *
     * @param string $action
     * @param string $label (optional, default="")
     * @return xxx
     */
    public function display_action_settings_settext($action, $label='') {
        $value = optional_param($action, '', PARAM_TEXT);
        $settings = '';
        $params = array('type' => 'text', 'name' => $action, 'value' => $value, 'size' => 20);
        $params += $this->display_action_onclickchange($action, 'onchange');
        $settings .= html_writer::empty_tag('input', $params);
        return $this->display_action_settings($action, $settings, $label);
    }

    /**
     * display_action_settings_setpublisher
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_setpublisher($action) {
        return $this->display_action_settings_settext($action);
    }

    /**
     * display_action_settings_setlevel
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_setlevel($action) {
        return $this->display_action_settings_settext($action);
    }

    /**
     * display_action_settings_setname
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_setname($action) {
        return $this->display_action_settings_settext($action);
    }

    /**
     * display_action_settings_showhidebook
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_showhidebook($action) {
        $value = optional_param($action, 0, PARAM_INT);
        $settings = '';
        $options = array('0' => get_string('show'), '1' => get_string('hide'));
        $params = $this->display_action_onclickchange($action, 'onchange');
        $settings .= html_writer::select($options, $action, $value, '', $params);
        return $this->display_action_settings($action, $settings);
    }

    /**
     * display_action_settings_setgenre
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_setgenre($action) {
        $value = mod_reader::optional_param_array($action, 0, PARAM_ALPHA);
        $settings = '';
        $options = mod_reader_renderer::valid_genres();
        $params = array('multiple' => 'multiple', 'size' => 5);
        $params += $this->display_action_onclickchange($action, 'onchange');
        $settings .= html_writer::select($options, $action.'[]', $value, '', $params);
        return $this->display_action_settings($action, $settings);
    }

    /**
     * display_action_settings_changequiz
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_changequiz($action) {
        $settings = '';

        $label = get_string('category');
        $params = array('class' => 'selecteditemhdr');
        $settings .= html_writer::tag('span', $label, $params).': ';

        $name = $action.'categoryid';
        $value = mod_reader::optional_param_array($name, 0, PARAM_INT);
        $options = array('0' => get_string('show'), '1' => get_string('hide'));
        $params = $this->display_action_onclickchange($action, 'onchange');
        $settings .= html_writer::select($options, $name, $value, '', $params);

        $settings .= html_writer::empty_tag('br');

        $label = get_string('course');
        $params = array('class' => 'selecteditemhdr');
        $settings .= html_writer::tag('span', $label, $params).': ';

        $name = $action.'courseid';
        $value = mod_reader::optional_param_array($name, 0, PARAM_INT);
        $options = array('0' => get_string('show'), '1' => get_string('hide'));
        $params = $this->display_action_onclickchange($action, 'onchange');
        $settings .= html_writer::select($options, $name, $value, '', $params);

        $settings .= html_writer::empty_tag('br');

        $label = get_string('section');
        $params = array('class' => 'selecteditemhdr');
        $settings .= html_writer::tag('span', $label, $params).': ';

        $name = $action.'sectionid';
        $value = mod_reader::optional_param_array($name, 0, PARAM_INT);
        $options = array('0' => get_string('show'), '1' => get_string('hide'));
        $params = $this->display_action_onclickchange($action, 'onchange');
        $settings .= html_writer::select($options, $name, $value, '', $params);

        $settings .= html_writer::empty_tag('br');

        $label = get_string('pluginname', 'quiz');
        $params = array('class' => 'selecteditemhdr');
        $settings .= html_writer::tag('span', $label, $params).': ';

        $name = $action.'quizid';
        $value = mod_reader::optional_param_array($name, 0, PARAM_INT);
        $options = array('0' => get_string('show'), '1' => get_string('hide'));
        $params = $this->display_action_onclickchange($action, 'onchange');
        $settings .= html_writer::select($options, $name, $value, '', $params);

        return $this->display_action_settings($action, $settings);
    }

    /**
     * display_action_settings_removebook
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_removebook($action) {
        $value = mod_reader::optional_param_array('force'.$action, 0, PARAM_INT);
        $params = $this->display_action_onclickchange($action, 'onchange');
        $settings = html_writer::checkbox('force'.$action, 1, $value, get_string('force'), $params);
        return $this->display_action_settings($action, $settings);
    }

    /**
     * execute_action_showhidebook
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_showhidebook($action) {
        $value = optional_param($action, 0, PARAM_INT);
        return $this->execute_action_update('id', 'reader_books', 'hidden', $value);
    }

    /**
     * execute_action_showhidebook
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_setgenre($action) {
        $value = mod_reader::optional_param_array($action, 0, PARAM_ALPHA);
        $value = array_intersect($value, array_keys(mod_reader_renderer::valid_genres()));
        $value = implode(',', $value);
        if ($value=='') {
            return false; // shouldn't happen !!
        } else {
            return $this->execute_action_update('id', 'reader_books', 'genre', $value);
        }
    }

    /**
     * execute_action_settext
     *
     * @param string $action
     * @param string $allowemptystring
     * @return xxx
     */
    public function execute_action_settext($action, $field, $allowemptystring=false) {
        $value = optional_param($action, 0, PARAM_TEXT);
        if ($value=='' && $allowemptystring==false) {
            return false; // shouldn't happen !!
        } else {
            return $this->execute_action_update('id', 'reader_books', $field, $value);
        }
    }

    /**
     * execute_action_setpublisher
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_setpublisher($action) {
        return $this->execute_action_settext($action, 'publisher');
    }

    /**
     * execute_action_setpublisher
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_setlevel($action) {
        return $this->execute_action_settext($action, 'level', true);
    }

    /**
     * execute_action_setpublisher
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_setname($action) {
        return $this->execute_action_settext($action, 'name');
    }

    /**
     * execute_action_removebook
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_removebook($action) {
        global $DB;
        $result = array();
        $ids = $this->get_selected('id');
        if (count($ids)) {
            $force = optional_param('force'.$action, 0, PARAM_INT);
            list($select, $params) = $DB->get_in_or_equal($ids);
            $books = $DB->get_records_select('reader_books', "id $select", $params, 'id', 'id,publisher,level,name');
            foreach ($books as $id => $book) {
                $bookname = $book->publisher.($book->level ? " ($book->level)" : '')." $book->name";
                if ($force==0 && $DB->record_exists('reader_attempts', array('bookid' => $id, 'deleted' => 0))) {
                    $bookname = html_writer::tag('span', $bookname, array('style' => 'color: initial;'));
                    $bookname = get_string('removebookerror', 'mod_reader', $bookname);
                    $result[] = html_writer::tag('span', $bookname, array('class' => 'notifyproblem'));
                    continue; // don't delete books that have "live" attempts
                }
                if ($attemptids = $DB->get_records('reader_attempts', array('bookid' => $id), 'id', 'id, bookid')) {
                    $attemptids = array_keys($attemptids);
                    $DB->delete_records_list('reader_attempt_questions', 'attemptid', $attemptids);
                    $DB->delete_records_list('reader_cheated_log', 'attempt1', $attemptids);
                    $DB->delete_records_list('reader_cheated_log', 'attempt2', $attemptids);
                    $DB->delete_records_list('reader_attempts', 'id', $attemptids);
                }
                $bookname = html_writer::tag('span', $bookname, array('style' => 'color: initial;'));
                $bookname = get_string('removebooksuccess', 'mod_reader', $bookname);
                $result[] = html_writer::tag('span', $bookname, array('class' => 'notifysuccess'));
                $DB->delete_records('reader_books', array('id' => $id));
            }
        }
        if (empty($result)) {
            return false; // shouldn't happen !!
        } else {
            return html_writer::alist($result);
        }
    }

    /**
     * execute_action_makebookavailable
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_makebookavailable($action) {
        global $DB;
        $result = array();
        $ids = $this->get_selected('id');
        if (count($ids)) {
            $readerid = $this->output->reader->id;
            list($select, $params) = $DB->get_in_or_equal($ids);
            $books = $DB->get_records_select('reader_books', "id $select", $params, 'id');
            foreach ($books as $book) {
                $params = array('readerid' => $readerid, 'bookid' => $book->id);
                if ($DB->record_exists('reader_book_instances', $params)) {
                    continue; // shoudn't happen !!
                }
                $instance = (object)array(
                    'readerid'   => $readerid,
                    'bookid'     => $book->id,
                    'difficulty' => $book->difficulty,
                    'words'      => $book->words,
                    'points'     => $book->points
                );
                $bookname = $book->publisher.($book->level ? " ($book->level)" : '')." $book->name";
                if ($DB->insert_record('reader_book_instances', $instance)) {
                    $bookname = get_string('makebookavailablesuccess', 'mod_reader', $bookname);
                    $class = 'notifysuccess';
                } else {
                    $bookname = get_string('makebookavailableproblem', 'mod_reader', $bookname);
                    $class = 'notifyproblem';
                }
                $result[] = html_writer::tag('span', $bookname, array('class' => $class));
            }
        }
        if (empty($result)) {
            return false; // shouldn't happen !!
        } else {
            return html_writer::alist($result);
        }
    }
}
