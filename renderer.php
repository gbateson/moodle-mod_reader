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
 * mod/reader/renderer.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die();

/**
 * mod_reader_renderer
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_renderer extends plugin_renderer_base {

    /**#@+
     * tab ids
     *
     * @var integer
     */
    const TAB_VIEW      = 1;
    const TAB_SETTINGS  = 2;
    const TAB_REPORTS   = 3;
    const TAB_BOOKS     = 4;
    const TAB_USERS     = 6;
    const TAB_TOOLS     = 7;
    const TAB_ADMINAREA = 8;
    /**#@-*/

    /** object to represent associated reader activity */
    public $reader = null;

    /** array of allow modes for this page (mode is second row of tabs) */
    public $modes = array();

    /**
     * init
     *
     * @param xxx $reader
     */
    public function init($reader)   {
        $this->reader = $reader;
    }

    ///////////////////////////////////////////
    // format tabs
    ///////////////////////////////////////////

    /**
     * tabs
     *
     * @return string HTML output to display navigation tabs
     */
    public function tabs($selected=null, $inactive=null, $activated=null) {

        $tab = $this->get_tab();
        $tabs = $this->get_tabs();

        if (class_exists('tabtree')) {
            // Moodle >= 2.6
            return $this->tabtree($tabs, $tab);
        } else {
            // Moodle <= 2.5
            $this->set_active_tabs($tabs, $tab);
            $html = convert_tree_to_html($tabs);
            return html_writer::tag('div', $html, array('class' => 'tabtree')).
                   html_writer::tag('div', '',    array('class' => 'clearer'));
        }

    }

    /**
     * set_active_tabs
     *
     * @param array $tabs (passed by reference)
     * @param integer currently selected $tab id
     * @return boolean, TRUE if any tabs or child tabs were selected, FALSE otherwise
     */
    public function set_active_tabs(&$tabs, $tab) {
        $result = false;
        foreach (array_keys($tabs) as $t) {

            // selected
            if ($tabs[$t]->id==$tab) {
                $tabs[$t]->selected = true;
            } else {
                $tabs[$t]->selected = false;
            }

            // active
            if (isset($tabs[$t]->subtree) && $this->set_active_tabs($tabs[$t]->subtree, $tab)) {
                $tabs[$t]->active = true;
            } else {
                $tabs[$t]->active = false;
            }

            // inactive (make sure it is set)
            if (empty($tabs[$t]->inactive)) {
                $tabs[$t]->inactive = false;
            }

            // result
            $result = ($result || $tabs[$t]->selected || $tabs[$t]->active);
        }
        return $result;
    }

    /**
     * get_tab
     *
     * @return integer tab id
     */
    public function get_tab() {
        $tab = $this->get_my_tab(); // the default tab
        $tab = optional_param('tab', $tab, PARAM_INT);
        if ($tab==$this->get_my_tab()) {
            $tab = $this->get_default_tab();
        }
        return $tab;
    }

    /**
     * get_my_tab
     *
     * @return integer tab id
     */
    public function get_my_tab() {
        return self::TAB_VIEW;
    }

    /**
     * get_default_tab
     *
     * @return integer tab id
     */
    public function get_default_tab() {
        return self::TAB_VIEW;
    }

    /**
     * get_tabs
     *
     * @return string HTML output to display navigation tabs
     */
    public function get_tabs() {
        $tabs = array();
        if (isset($this->reader)) {
            if (isset($this->reader->cm)) {
                $cmid = $this->reader->cm->id;
            } else {
                $cmid = 0; // unusual !!
            }
            $showadminarea = false;
            if ($this->reader->can_viewbooks()) {
                $tab = self::TAB_VIEW;
                $url = new moodle_url('/mod/reader/view.php', array('id' => $cmid, 'tab' => $tab));
                $tabs[$tab] = new tabobject($tab, $url, get_string('view'));
            }
            if ($this->reader->can_addinstance()) {
                $tab = self::TAB_SETTINGS;
                $url = new moodle_url('/course/mod.php', array('update' => $cmid, 'return' => 1, 'sesskey' => sesskey()));
                $tabs[$tab] = new tabobject($tab, $url, get_string('settings'));
            }
            if ($this->reader->can_viewreports()) {
                $tab = self::TAB_REPORTS;
                $url = new moodle_url('/mod/reader/admin/reports.php', array('id' => $cmid, 'tab' => $tab));
                $tabs[$tab] = new tabobject($tab, $url, get_string('reports'));
                $showadminarea = true;
            }
            if ($this->reader->can_managebooks()) {
                $tab = self::TAB_BOOKS;
                $url = new moodle_url('/mod/reader/admin/books.php', array('id' => $cmid, 'tab' => $tab));
                $tabs[$tab] = new tabobject($tab, $url, get_string('books', 'mod_reader'));
                $showadminarea = true;
            }
            if ($this->reader->can_manageusers()) {
                $tab = self::TAB_USERS;
                $url = new moodle_url('/mod/reader/admin/users.php', array('id' => $cmid, 'tab' => $tab));
                $tabs[$tab] = new tabobject($tab, $url, get_string('users', 'mod_reader'));
                $showadminarea = true;
            }
            if ($this->reader->can_managetools() || $this->reader->can_managebooks()) {
                $tab = self::TAB_TOOLS;
                $url = new moodle_url('/mod/reader/admin/tools.php', array('id' => $cmid, 'tab' => $tab));
                $tabs[$tab] = new tabobject($tab, $url, get_string('tools', 'mod_reader'));
                $showadminarea = true;
            }
            if ($showadminarea) {
                $tab = self::TAB_ADMINAREA;
                $url = new moodle_url('/mod/reader/admin.php', array('id' => $cmid, 'tab' => $tab, 'a' => 'admin'));
                $tabs[$tab] = new tabobject($tab, $url, get_string('adminarea', 'mod_reader'));
            }
        }
        return $tabs;
    }

    /**
     * attach_tabs_subtree
     *
     * @return string HTML output to display navigation tabs
     */
    public function attach_tabs_subtree($tabs, $id, $subtree) {
        foreach (array_keys($tabs) as $i) {
            if ($tabs[$i]->id==$id) {
                $tabs[$i]->subtree = $subtree;
            }
        }
        return $tabs;
    }

    ///////////////////////////////////////////
    // generate SQL to select available books
    ///////////////////////////////////////////

    /**
     * available_sql
     * generate sql to select books that this user is currently allowed to attempt
     *
     * @param boolean $noquiz TRUE books that have no associated quiz, FALSE otherwise
     * @param mixed $hasquiz (TRUE  : require quizid > 0,
     *                        FALSE : require quizid == 0,
     *                        NULL  : require quizid >= 0)
     * @return array (string $from, string $where, array $params)
     */
    public function available_sql($hasquiz=null) {
        global $USER;
        $reader = $this->reader;
        $cmid = $reader->cm->id;
        $userid = $USER->id;
        return reader_available_sql($cmid, $reader, $userid, $hasquiz);
    }

    /**
     * select_items
     *
     * @param xxx $action
     * @return xxx
     * @todo Finish documenting this function
     */
    public function select_items($action='') {
        global $CFG, $USER;

        // get parameters passed from browser
        $publisher = optional_param('publisher', null, PARAM_CLEAN); // book publisher
        $level     = optional_param('level',     null, PARAM_CLEAN); // book level
        $bookid    = optional_param('bookid',    null, PARAM_INT  ); // book id
        if ($action=='') {
            $action = optional_param('action', $action, PARAM_CLEAN);
        }

        require_once($CFG->dirroot.'/mod/reader/admin/books/download/downloader.php');
        $type = reader_downloader::BOOKS_WITHOUT_QUIZZES;
        $type = optional_param('type', $type, PARAM_INT);

        // get SQL $from and $where statements to extract available books
        $hasquiz = ($type==reader_downloader::BOOKS_WITH_QUIZZES);
        list($from, $where, $params) = reader_available_sql($this->reader->cm->id,
                                                            $this->reader,
                                                            $USER->id,
                                                            $hasquiz);

        $output = '';
        switch (true) {
            case ($publisher===null || $publisher=='' || optional_param('go', null, PARAM_CLEAN)):
                $output .= $this->request_js();
                $output .= html_writer::start_tag('div', array('id' => 'publishers'));
                $output .= $this->select_publishers($action, $from, $where, $params, $type, $publisher, $level, $bookid);
                $output .= html_writer::end_tag('div');
                break;

            case ($level===null || $level==''):
                $output .= html_writer::start_tag('div', array('id' => 'levels'));
                $output .= $this->select_levels($action, $from, $where, $params, $type, $publisher, $level, $bookid);
                $output .= html_writer::end_tag('div');
                break;

            case ($bookid===null || $bookid==0) :
                $output .= html_writer::start_tag('div', array('id' => 'books'));
                $output .= $this->select_books($action, $from, $where, $params, $type, $publisher, $level, $bookid);
                $output .= html_writer::end_tag('div');
                break;

            default:
                $output .= $this->select_book($action, $from, $where, $params, $type, $publisher, $level, $bookid);
        }
        return $output;
    }

    /**
     * select_items_url
     *
     * @param xxx $url
     * @param xxx $params
     * @return xxx
     * @todo Finish documenting this function
     */
    public function select_items_url($url, $params) {
        $url = new moodle_url($url, $params);
        $url = "$url"; // convert to string
        if (substr($url, -1)=='=') {
            // Moodle <= 2.4
            $url = substr($url, 0 ,-1);
        }
        return "'$url='+escape(this.options[this.selectedIndex].value)";
    }


    /**
     * select_publishers
     *
     * @param xxx $action
     * @param xxx $from
     * @param xxx $where
     * @param xxx $sqlparams
     * @param xxx $publisher (optional, default="")
     * @param xxx $level (optional, default="")
     * @param xxx $bookid (optional, default=0)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function select_publishers($action, $sqlfrom, $sqlwhere, $sqlparams, $type, $publisher='', $level='', $bookid=0) {
        global $DB;
        $output = '';

        $select = 'rb.publisher, COUNT(*) AS countbooks';
        if ($records = $DB->get_records_sql("SELECT $select FROM $sqlfrom WHERE $sqlwhere GROUP BY rb.publisher ORDER BY rb.publisher", $sqlparams)) {
            $count = count($records);
        } else {
            $count = 0;
        }

        // publisher title
        $output .= html_writer::tag('div', get_string('publisher', 'mod_reader'), array('class' => 'selecteditemhdr'));

        // publisher list
        if ($count==0) {
            if ($this->reader->can_viewallbooks()) {
                $msg = get_string('nobooksfound', 'mod_reader');
            } else {
                $msg = get_string('nobooksinlist', 'mod_reader');
            }
            $output .= html_writer::tag('div', $msg, array('class' => 'selecteditemtxt'));

        } else if ($count==1) {
            $record = reset($records);
            $publisher = $record->publisher;
            $output .= html_writer::tag('div', $publisher, array('class' => 'selecteditemtxt'));
            $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'publisher', 'value' => $publisher));

        } else if ($count > 1) {
            $params = array('action'    => $action,
                            'mode'      => $this->mode,
                            'id'        => $this->reader->cm->id,
                            'type'      => $type,
                            'publisher' => ''); // will be added by javascript
            $url = $this->select_items_url('/mod/reader/view_books.php', $params);

            $params = array('id' => 'id_publisher',
                            'name' => 'publisher',
                            'size' => min(10, count($records)),
                            'onchange' => "request($url, 'levels')");
            $output .= html_writer::start_tag('select', $params);

            foreach ($records as $record) {
                $params = array('value' => $record->publisher);
                if ($publisher==$record->publisher) {
                    $params['selected'] = 'selected';
                }
                $output .= html_writer::tag('option', "$record->publisher ($record->countbooks books)", $params);
            }

            $output .= html_writer::end_tag('select');
        }

        $output .= html_writer::start_tag('div', array('id' => 'levels'));
        if ($count==0 || $publisher=='') {
            $output .= html_writer::tag('div', '', array('id' => 'books'));
        } else {
            $output .= $this->select_levels($action, $sqlfrom, $sqlwhere, $sqlparams, $type, $publisher, $level, $bookid);
        }
        $output .= html_writer::end_tag('div');

        return $output;
    }

    /**
     * select_levels
     *
     * @param xxx $action
     * @param xxx $sqlfrom
     * @param xxx $sqlwhere
     * @param xxx $sqlparams
     * @param xxx $type
     * @param xxx $publisher
     * @param xxx $level (optional, default = "")
     * @param xxx $bookid (optional, default = 0)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function select_levels($action, $sqlfrom, $sqlwhere, $sqlparams, $type, $publisher, $level='', $bookid=0) {
        global $DB;
        $output = '';

        $select = "rb.level, COUNT(*) AS countbooks, ROUND(SUM(rb.difficulty) / COUNT(*), 0) AS average_difficulty";
        $where  = $sqlwhere.' AND rb.publisher = ?';
        $params = array_merge($sqlparams, array($publisher));

        if ($records = $DB->get_records_sql("SELECT $select FROM $sqlfrom WHERE $where GROUP BY rb.level ORDER BY average_difficulty", $params)) {
            $count = count($records);
        } else {
            $count = 0;
        }

        // level title
        $output .= html_writer::tag('div', get_string('level', 'mod_reader'), array('class' => 'selecteditemhdr'));

        // level list
        if ($count==0) {
            $$msg = 'Sorry, there are currently no books for you by '.$publisher;
            $output .= html_writer::tag('div', $msg, array('class' => 'selecteditemtxt'));

        } else if ($count==1) {
            $record = reset($records);
            $level = $record->level;
            $output .= html_writer::tag('div', $level, array('class' => 'selecteditemtxt'));
            $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'level', 'value' => $level));

        } else if ($count > 1) {
            $params = array('action'    => $action,
                            'mode'      => $this->mode,
                            'id'        => $this->reader->cm->id,
                            'type'      => $type,
                            'publisher' => $publisher,
                            'level'     => ''); // will be added by javascript
            $url = $this->select_items_url('/mod/reader/view_books.php', $params);

            $params = array('id' => 'id_level',
                            'name' => 'level',
                            'size' => min(10, count($records)),
                            'onchange' => "request($url, 'books')");
            $output .= html_writer::start_tag('select', $params);

            foreach ($records as $record) {
                if ($record->level=='' || $record->level=='--') {
                    $displaylevel = $publisher;
                } else {
                    $displaylevel = $record->level;
                }
                $params = array('value' => $record->level);
                if ($level==$record->level) {
                    $params['selected'] = 'selected';
                }
                $output .= html_writer::tag('option', "$displaylevel ($record->countbooks books)", $params);
            }

            $output .= html_writer::end_tag('select');
        }

        $output .= html_writer::start_tag('div', array('id' => 'books'));
        if ($count==0 || $level=='') {
            $output .= html_writer::tag('div', '', array('id' => 'book'));
        } else {
            $output .= $this->select_books($action, $sqlfrom, $sqlwhere, $sqlparams, $type, $publisher, $level, $bookid);
        }
        $output .= html_writer::end_tag('div');

        return $output;
    }

    /**
     * select_books
     *
     * @param xxx $action
     * @param xxx $sqlfrom
     * @param xxx $sqlwhere
     * @param xxx $sqlparams
     * @param xxx $type
     * @param xxx $publisher
     * @param xxx $level
     * @param xxx $bookid (optional, default = 0)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function select_books($action, $sqlfrom, $sqlwhere, $sqlparams, $type, $publisher, $level, $bookid=0) {
        global $DB;
        $output = '';

        $select = '*';
        $where  = $sqlwhere.' AND rb.publisher = ? AND rb.level = ?';
        $params = array_merge($sqlparams, array($publisher, $level));

        if ($records = $DB->get_records_sql("SELECT $select FROM $sqlfrom WHERE $where ORDER BY rb.name", $params)) {
            $count = count($records);
        } else {
            $count = 0;
        }

        $output .= html_writer::tag('div', get_string('book', 'mod_reader'), array('class' => 'selecteditemhdr'));

        if ($count==0) {
            $msg = "Sorry, there are currently no books for you by $publisher ($level)";
            $output .= html_writer::tag('div', $msg, array('class' => 'selecteditemtxt'));

        } else if ($count==1) {
            $record = reset($records); // just one book found
            $bookid = $record->id;
            $output .= html_writer::tag('div', $this->format_bookname($record), array('class' => 'selecteditemtxt'));
            $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'bookid', 'value' => $record->id));

        } else if ($count > 1) {
            $params = array('action'    => $action,
                            'mode'      => $this->mode,
                            'id'        => $this->reader->cm->id,
                            'type'      => $type,
                            'publisher' => $publisher,
                            'level'     => $level,
                            'bookid'    => ''); // will be added by javascript
            $url = $this->select_items_url('/mod/reader/view_books.php', $params);

            $params = array('id' => 'id_bookid',
                            'name' => 'bookid',
                            'size' => min(10, count($records)),
                            'onchange' => "request($url, 'bookid')");
            $output .= html_writer::start_tag('select', $params);

            foreach ($records as $record) {
                $params = array('value' => $record->id);
                if ($bookid==$record->id) {
                    $params['selected'] = 'selected';
                }
                $output .= html_writer::tag('option', "[RL-$record->difficulty] $record->name", $params);
            }

            $output .= html_writer::end_tag('select');

        }

        $output .= html_writer::start_tag('div', array('id' => 'bookid', 'style' => 'clear: both;'));
        if ($count==0 || $bookid==0) {
            // do nothing
        } else {
            $output .= $this->select_book($action, $sqlfrom, $sqlwhere, $sqlparams, $type, $publisher, $level, $bookid);
        }
        $output .= html_writer::end_tag('div');

        return $output;
    }

    /**
     * select_book
     *
     * @param xxx $action
     * @param xxx $sqlfrom
     * @param xxx $sqlwhere
     * @param xxx $sqlparams
     * @param xxx $type
     * @param xxx $publisher
     * @param xxx $level
     * @param xxx $bookid
     * @return xxx
     * @todo Finish documenting this function
     */
    public function select_book($action, $sqlfrom, $sqlwhere, $sqlparams, $type, $publisher, $level, $bookid) {
        global $DB;
        $output = '';

        $select = 'rb.id, rb.publisher, rb.level, rb.name, rb.words';
        $where  = $sqlwhere.' AND rb.publisher = ? AND rb.level = ? AND rb.id = ?';
        $params = array_merge($sqlparams, array($publisher, $level, $bookid));

        if ($record = $DB->get_record_sql("SELECT $select FROM $sqlfrom WHERE $where", $params)) {
            $output .= html_writer::tag('div', $this->format_bookname($record), array('class' => 'selecteditemtxt'));
            $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'bookid', 'value' => $record->id));
        }

        return $output;
    }

    /**
     * format_bookname
     *
     * @param xxx $book
     * @return xxx
     * @todo Finish documenting this function
     */
    public function format_bookname($book) {
        return "$book->name (".number_format($book->words)." words)";
    }

    /**
     * get_booktable
     *
     * @param xxx $action
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_booktable($action='') {
        return 'reader_books';
    }

    /**
     * request_js
     *
     * @return string
     */
    public function request_js() {
        static $done = false;

        if ($done) {
            return '';
        }
        $done = true;

        global $PAGE;
        if (method_exists($PAGE->theme, 'image_url')) {
            $image_url = 'image_url'; // Moodle >= 3.3
        } else {
            $image_url = 'pix_url'; // Moodle <= 3.2
        }
        $src = $PAGE->theme->$image_url('i/ajaxloader', 'core')->out();

        $js = '';

        $js .= '<script type="text/javascript">'."\n";
        $js .= "//<![CDATA[\n";

        $js .= "window.loading = '".'<img src="'.$src.'" alt="loading..."/>'."';\n";
        $js .= "window.req = false;\n";

        $js .= "function request(url, targetids, callback) {\n";
        $js .= "    url = url.replace(new RegExp('&amp;', 'g'), '&');\n";

        $js .= "    if (typeof(targetids)=='string') {\n";
        $js .= "        targetids = targetids.split(',');\n";
        $js .= "    }\n";

        $js .= "    var i_max = targetids.length;\n";
        $js .= "    for (var i=0; i<i_max; i++) {\n";
        $js .= "        var obj = document.getElementById(targetids[i]);\n";
        $js .= "    	if (obj) {\n";
        $js .= "            obj.innerHTML = (i==0 && loading ? loading : '');\n";
        $js .= "    	    obj = null;\n";
        $js .= "    	}\n";
        $js .= "    }\n";

        $js .= "    if (window.XMLHttpRequest) {\n"; // modern browser (incl. IE7+)
        $js .= "        req = new XMLHttpRequest();\n";
        $js .= "    } else if (window.ActiveXObject) {\n"; // IE6, IE5
        $js .= "        req = new ActiveXObject('Microsoft.XMLHTTP');\n";
        $js .= "    }\n";

        $js .= "    if (req) {\n"; //  && confirm('REQUEST: ' + url)
        $js .= "        if (callback) {\n";
        $js .= "            req.onreadystatechange = eval(callback);\n";
        $js .= "        } else {\n";
        $js .= "            req.onreadystatechange = function() { response(url, targetids); }\n";
        $js .= "        }\n";
        $js .= "        req.open('GET', url, true);\n";
        $js .= "        req.send(null);\n";
        $js .= "    }\n";
        $js .= "}\n";

        $js .= "function response(url, targetids) {\n";
        $js .= "    if (req.readyState==4) {\n";

        $js .= "        if (typeof(targetids)=='string') {\n";
        $js .= "            targetids = targetids.split(',');\n";
        $js .= "        }\n";

        $js .= "        var i_max = targetids.length;\n";
        $js .= "        for (var i=0; i<i_max; i++) {\n";
        $js .= "            var obj = document.getElementById(targetids[i]);\n";
        $js .= "            if (obj) {\n";
        $js .= "                if (req.status==200) {\n";
        $js .= "                    obj.innerHTML = req.responseText;\n";
        $js .= "                } else if (i==0) {\n";
        $js .= "                    obj.innerHTML = 'An error was encountered: ' + req.status;\n";
        $js .= "                } else {\n";
        $js .= "                    obj.innerHTML = '';\n";
        $js .= "                }\n";
        $js .= "                obj = null;\n";
        $js .= "            }\n";
        $js .= "        }\n";
        $js .= "    }\n";
        $js .= "}\n";

        $js .= "function setLoadMessage(msg) {\n";
        $js .= "    loading = msg;\n";
        $js .= "}\n";

        $js .= "//]]>\n";
        $js .= "</script>\n";

        return $js;
    }

    ///////////////////////////////////////////
    // get user level and book difficulty
    ///////////////////////////////////////////

    /**
     * get_level_data
     *
     * @uses $CFG
     * @uses $DB
     * @uses $USER
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_level_data($userid=0) {
        global $CFG, $DB, $USER;

        // initialize count of quizzes taken at "prev", "this" and "next" levels
        //     Note that for "prev" and "next" we count ANY attempt
        //     but for "this" level, we only count PASSED attempts
        $count = array('prev' => 0, 'this' => 0, 'next' => 0);

        if ($userid==0) {
            $userid = $USER->id;
        }

        if (! $level = $DB->get_record('reader_levels', array('userid' => $userid, 'readerid' => $this->id))) {
            $level = (object)array(
                'userid'         => $userid,
                'readerid'       => $this->id,
                'startlevel'     => 0,
                'currentlevel'   => 0,
                'allowpromotion' => 1,
                'stoplevel'      => $this->stoplevel,
                'goal'           => $this->goal,
                'time'           => time(),
            );
            if (! $level->id = $DB->insert_record('reader_levels', $level)) {
                // oops record could not be added - shouldn't happen !!
            }
        }

        $select = 'ra.*, rb.difficulty, rb.id AS bookid';
        $from   = '{reader_attempts} ra INNER JOIN {reader_books} rb ON ra.bookid = rb.id';
        $where  = 'ra.userid= ? AND ra.readerid= ? AND ra.timefinish > ?';
        $params = array($USER->id, $this->id, $this->ignoredate);

        if ($attempts = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY ra.timemodified", $params)) {
            foreach ($attempts as $attempt) {

                $difficulty = $this->get_reader_difficulty($attempt->bookid, $attempt->difficulty);
                switch (true) {

                    case ($difficulty == ($level->currentlevel - 1)):
                        if ($level->currentlevel < $level->startlevel) {
                            $count['prev'] = -1;
                        } else if ($level->time < $attempt->timefinish) {
                            $count['prev'] += 1;
                        }
                        break;

                    case ($difficulty == $level->currentlevel):
                        if ($attempt->passed) {
                            $count['this'] += 1;
                        }
                        break;

                    case ($difficulty == ($level->currentlevel + 1)):
                        if ($level->time < $attempt->timefinish) {
                            $count['next'] += 1;
                        }
                        break;
                }
            }
        }

        // if this is the highest allowed level, then disable the "allowpromotion" switch
        if ($level->stoplevel > 0 && $level->stoplevel <= $level->currentlevel) {
            $DB->set_field('reader_levels', 'allowpromotion', 0, array('readerid' => $this->id, 'userid' => $USER->id));
            $level->allowpromotion = 0;
        }

        if ($level->allowpromotion==0) {
            $count['this'] = 1;
        }

        // promote this student, if they have done enough quizzes at this level
        if ($count['this'] >= $this->thislevel) {
            $level->currentlevel += 1;
            $level->time = time();
            $DB->update_record('reader_levels', $level);

            $count['this'] = 0;
            $count['prev'] = 0;
            $count['next'] = 0;

            echo '<script type="text/javascript">'."\n";
            echo '//<![CDATA['."\n";
            echo 'alert("'.addslashes_js(get_string('youhavebeenpromoted', 'mod_reader'. $level->currentlevel)).'");'."\n";
            echo '//]]>'."\n";
            echo '</script>';
        }

        // prepare level data
        $leveldata = array(
            'promotiondate' => $level->time,
            'currentlevel'  => $level->currentlevel,              // current level of this user
            'prevlevel'     => $this->prevlevel - $count['prev'], // number of quizzes allowed at previous level
            'thislevel'     => $this->thislevel - $count['this'], // number of quizzes allowed at current level
            'nextlevel'     => $this->nextlevel - $count['next']  // number of quizzes allowed at next level
        );
        if ($level->currentlevel==0 || $count['prev'] == -1) {
            $leveldata['prevlevel'] = -1;
        }

        return $leveldata;
    }

    /**
     * get_reader_difficulty
     *
     * @uses $DB
     * @param xxx $bookid
     * @param xxx $difficulty (optional, default=0)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function get_reader_difficulty($bookid, $difficulty=0) {
        global $DB;

        // "Course-specific quiz selection" is enabled for this reader activity
        if ($this->bookinstances) {
            if ($instance = $DB->get_record('reader_book_instances', array('readerid' => $this->id, 'bookid' => $bookid))) {
                return $instance->difficulty;
            }
        }

        // if we already know the difficulty for this book, then use that
        if ($difficulty) {
            return $difficulty;
        }

        // get the book difficulty from the "reader_books" table
        if ($book = $DB->get_record('reader_books', array('id' => $bookid))) {
            return $book->difficulty;
        }

        return 0; // shouldn't happen !!
    }

    ///////////////////////////////////////////
    // format search form
    ///////////////////////////////////////////

    /**
     * search_form
     *
     * generate HTML code to display a form to search for books
     * that appears when students view this module. As well as
     * allowing students to search by name, the form includes
     * filters for publisher, level, reading level, and genre.
     *
     * The action for this form is handled by view_books.php
     * which calls the books_menu() method
     *
     * @param xxx $userid
     * @param xxx $includeformtag (optional, default=false)
     * @param xxx $action (optional, default='')
     * @return html code for a form to enter criteria for searching books
     * @todo Finish documenting this function
     */
    public function search_form($userid=0, $includeformtag=false, $action='') {
        global $CFG, $DB, $OUTPUT, $USER;
        $output = '';

        if ($userid==0) {
            $userid = $USER->id;
        }

        // get parameters passed from form
        $searchpublisher  = optional_param('searchpublisher',    '', PARAM_CLEAN);
        $searchlevel      = optional_param('searchlevel',        '', PARAM_CLEAN);
        $searchname       = optional_param('searchname',         '', PARAM_CLEAN);
        $searchgenre      = optional_param('searchgenre',        '', PARAM_CLEAN);
        $searchdifficulty = optional_param('searchdifficulty',   -1, PARAM_INT);
        $search           = optional_param('search',              0, PARAM_INT);
        $action           = optional_param('action',        $action, PARAM_CLEAN);
        $ajax             = optional_param('ajax',                0, PARAM_INT);

        // get SQL $from and $where statements to extract available books
        list($from, $where, $sqlparams) = reader_available_sql($this->reader->cm->id, $this->reader, $userid);

        if ($includeformtag) {
            $target_div = 'searchresultsdiv';
            $target_url = "'view_books.php?id=".$this->reader->cm->id."'".
                          "+'&ajax=1'". // so we can detect incoming search results
                          "+'&search='+parseInt(this.search.value)".
                          "+'&action=$action'". // "adjustscores" or "takequiz"
                          "+'&searchpublisher='+escape(this.searchpublisher.value)".
                          "+'&searchlevel='+escape(this.searchlevel.value)".
                          "+'&searchname='+escape(this.searchname.value)".
                          "+'&searchgenre='+escape(this.searchgenre.options[this.searchgenre.selectedIndex].value)".
                          "+'&searchdifficulty='+this.searchdifficulty.options[this.searchdifficulty.selectedIndex].value";

            // create the search form
            $params = array(
                'id'     => 'id_readersearchform',
                'class'  => 'readersearchform',
                'method' => 'post',
                'action' => new moodle_url('/mod/reader/view.php', array('id' => $this->reader->cm->id)),
                'onsubmit' => "request($target_url, '$target_div'); return false;"
            );
            $output .= html_writer::start_tag('form', $params);

            $hidden = html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'search', 'value' => 1));
            $output .= html_writer::tag('div', $hidden, array('style' => 'display: none;'));

            $table = new html_table();
            $table->align = array('right', 'left');

            $table->rowclasses[0] = 'advanced'; // publisher
            $table->rowclasses[1] = 'advanced'; // level
            $table->rowclasses[3] = 'advanced'; // genre
            $table->rowclasses[4] = 'advanced'; // difficulty

            $table->data[] = new html_table_row(array(
                html_writer::tag('b', get_string('publisher', 'mod_reader').':'),
                html_writer::empty_tag('input', array('type' => 'text', 'name' => 'searchpublisher', 'value' => $searchpublisher))
            ));
            $table->data[] = new html_table_row(array(
                html_writer::tag('b', get_string('level', 'mod_reader').':'),
                html_writer::empty_tag('input', array('type' => 'text', 'name' => 'searchlevel', 'value' => $searchlevel))
            ));
            $table->data[] = new html_table_row(array(
                html_writer::tag('b', get_string('booktitle', 'mod_reader').':'),
                html_writer::empty_tag('input', array('type' => 'text', 'name' => 'searchname', 'value' => $searchname))
            ));

            // get list of valid and available genres ($code => $text)
            $genres = $this->available_genres($from, $where, $sqlparams);
            $genres = array('' => get_string('none')) + $genres;

            // add the "genre" drop-down list
            $table->data[] = new html_table_row(array(
                html_writer::tag('b', get_string('genre', 'mod_reader').':'),
                html_writer::select($genres, 'searchgenre', $searchgenre, '')
            ));

            // can this user view all levels of books in this reader activity?
            if (isset($_SESSION['SESSION']->reader_teacherview) && $_SESSION['SESSION']->reader_teacherview == 'teacherview') {
                // this is a teacher
                $alllevels = true;
            } else if ($this->reader->levelcheck == 0) {
                // no level checking
                $alllevels = true;
            } else {
                $alllevels = false;
            }

            // create list of RL's (reading levels) this user can attempt
            $levels = array();
            if ($alllevels) {
                if ($this->reader->bookinstances) {
                    $tablename = 'reader_book_instances';
                } else {
                    $tablename = 'reader_books';
                }
                if ($records = $DB->get_records_select($tablename, 'difficulty < 99', null, 'difficulty', 'DISTINCT difficulty')) {
                    foreach ($records as $record) {
                        $levels[] = $record->difficulty;
                    }
                }
            } else {
                $leveldata = reader_get_level_data($this->reader, $userid);
                if ($leveldata['prevlevel'] > 0 && $leveldata['currentlevel'] >= 1) {
                    $levels[] = ($leveldata['currentlevel'] - 1);
                }
                if ($leveldata['thislevel'] > 0 && $leveldata['currentlevel'] >= 0) {
                    $levels[] = $leveldata['currentlevel'];
                }
                if ($leveldata['nextlevel'] > 0) {
                    $levels[] = ($leveldata['currentlevel'] + 1);
                }
            }

            // make each $levels key the same as the value
            // and then prepend the (-1 => "none") key & value
            if (count($levels)) {
                $levels = array_combine($levels, $levels);
                $levels = array(-1 => get_string('none')) + $levels;
            }

            // add the "RL" (reading level) drop-down list
            $table->data[] = new html_table_row(array(
                html_writer::tag('b', get_string('difficultyshort', 'mod_reader').':'),
                html_writer::select($levels, 'searchdifficulty', $searchdifficulty, '')
            ));

            // javascript to show/hide the "advanced" search fields
            $onclick = '';
            $onclick .= "var obj = document.getElementById('id_readersearchform');";
            $onclick .= "if (obj) {";
            $onclick .=     "obj = obj.getElementsByTagName('tr');";
            $onclick .= "}";
            $onclick .= "var styledisplay = '';";
            $onclick .= "if (obj) {";
            $onclick .=     "for (var i=0; i<obj.length; i++) {";
            $onclick .=         "if (obj[i].className.indexOf('advanced')>=0) {";
            $onclick .=             "styledisplay = obj[i].style.display;";
            $onclick .=             "obj[i].style.display = (styledisplay ? '' : 'table-row');";
            $onclick .=         "}";
            $onclick .=     "}";
            $onclick .= "}";
            $onclick .= "this.innerHTML = (styledisplay ? '".get_string('showadvanced', 'form')."' : '".get_string('hideadvanced', 'form')."');";

            // add the "search" button
            $table->data[] = new html_table_row(array(
                '&nbsp;',
                html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'submit', 'value' => get_string('search'))).
                ' '.html_writer::tag('small', html_writer::tag('a', get_string('showadvanced', 'form').' ...', array('onclick' => $onclick)))
            ));

            // create search results table
            $output .= html_writer::table($table);

            // finish search form
            $output .= html_writer::end_tag('form');
        }

        // disable $search if there are no search parameters
        if ($search) {

            // restrict search, if necessary
            $search = array();
            if (is_numeric($searchdifficulty) && $searchdifficulty >= 0) {
                array_unshift($search, 'difficulty = ?');
                array_unshift($sqlparams, $searchdifficulty);
            }
            if ($searchgenre) {
                if ($DB->sql_regex_supported()) {
                    array_unshift($search, 'genre '.$DB->sql_regex().' ?');
                    array_unshift($sqlparams, '(^|,)'.$searchgenre.'(,|$)');
                } else {
                    $filter = array('genre = ?',
                                    $DB->sql_like('genre', '?', false, false),  // start
                                    $DB->sql_like('genre', '?', false, false),  // middle
                                    $DB->sql_like('genre', '?', false, false)); // end
                    array_unshift($search, '('.implode(' OR ', $filter).')');
                    array_unshift($sqlparams, "$searchgenre", "$searchgenre,%", "%,$searchgenre,%", "%,$searchgenre");
                }
            }
            if ($searchpublisher) {
                array_unshift($search, $DB->sql_like('publisher', '?', false, false));
                array_unshift($sqlparams, "%$searchpublisher%");
            }
            if ($searchlevel) {
                array_unshift($search, $DB->sql_like('level', '?', false, false));
                array_unshift($sqlparams, "%$searchlevel%");
            }
            if ($searchname) {
                array_unshift($search, $DB->sql_like('name', '?', false, false));
                array_unshift($sqlparams, "%$searchname%");
            }
            if (count($search)) {
                $where = implode(' AND ', $search)." AND $where";
                $search = 1;
            } else {
                $search = 0;
            }
        }

        $searchresults = '';
        if ($search) {
            list($cheatsheeturl, $strcheatsheet) = $this->cheatsheet_init($action);

            // search for available books that match  the search criteria
            $select = 'rb.id, rb.publisher, rb.level, rb.name, rb.quizid, rb.genre';
            if ($this->reader->bookinstances) {
                $select .= ', rbi.difficulty';
            } else {
                $select .= ', rb.difficulty';
            }
            if ($books = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $sqlparams)) {

                $table = new html_table();

                // add table headers - one per column
                $table->head = array(
                    get_string('publisher', 'mod_reader'),
                    get_string('level', 'mod_reader'),
                    get_string('booktitle', 'mod_reader')." (".count($books)." books)",
                    get_string('genre', 'mod_reader'),
                    get_string('difficultyshort', 'mod_reader')
                );

                // add column for "takequiz" button, if required
                if ($action=='takequiz') {
                    $table->head[] = '&nbsp;';
                }

                // add extra column for "cheatsheet" links, if required
                if ($cheatsheeturl) {
                    $table->head[] = html_writer::tag('small', $strcheatsheet);
                }

                // add one row for each book in the search results
                foreach ($books as $book) {

                    // format publisher- level
                    $publisher = $book->publisher.(($book->level=='' | $book->level=='--') ? '' : ' - '.$book->level);

                    // add cells to this row of the table
                    $cells = array(
                        $book->publisher,
                        (($book->level=='' || $book->level=='--') ? '' : $book->level),
                        $book->name,
                        (empty($book->genre) ? '' : self::valid_genres($book->genre)),
                        $book->difficulty
                    );

                    if ($book->quizid==0) {
                        if ($action=='takequiz') {
                            $cell = get_string('awardpointsmanually', 'mod_reader');
                            $cell = html_writer::tag('i', $cell);
                            $cell = new html_table_cell($cell);
                            if ($cheatsheeturl) {
                                $cell->colspan = 2;
                            }
                            $cell->attributes['class'] = 'awardpointsmanually';
                            $cells[] = $cell;
                            $awardpointsmanually = true;
                        }
                    } else {
                        if ($action=='takequiz') {
                            // construct url to start attempt at quiz
                            $params = array('id' => $this->reader->cm->id, 'book' => $book->id);
                            $url = new moodle_url('/mod/reader/quiz/startattempt.php', $params);

                            // construct button to start attempt at quiz
                            $params = array('class' => 'singlebutton readerquizbutton');
                            $button = $OUTPUT->single_button($url, get_string('takethisquiz', 'mod_reader'), 'get', $params);

                            $cells[] = $button;
                        }

                        // add cheat sheet link, if required
                        if ($cheatsheeturl) {
                            $cells[] = $this->cheatsheet_link($cheatsheeturl, $strcheatsheet, $publisher, $book);
                        }
                    }

                    // add this row to the table
                    $table->data[] = new html_table_row($cells);
                }

                // create the HTML for the table of search results
                if (count($table->data)) {
                    $searchresults .= html_writer::table($table);
                }
            } else {
                $searchresults .= html_writer::tag('p', get_string('nosearchresults', 'mod_reader'));
            }
        }

        if ($ajax) {
            $output .= $searchresults; // not need to wrap it in "searchresultsdiv"
        } else {
            $output .= html_writer::tag('div', $searchresults, array('id' => 'searchresultsdiv'));
        }

        return $output;
    }

    /**
     * available_genres
     *
     * @param xxx $from
     * @param xxx $where
     * @param xxx $sqlparams
     * @return xxx
     * @todo Finish documenting this function
     */
    public function available_genres($from, $where, $sqlparams) {
        global $DB;

        // a list of valid genres ($code => $text)
        $genres = array();

        // skip NULL and empty genre fields
        $where = "rb.genre IS NOT NULL AND rb.genre <> ? AND $where";
        array_unshift($sqlparams, '');

        if ($records = $DB->get_records_sql("SELECT DISTINCT rb.genre FROM $from WHERE $where", $sqlparams)) {

            $genres = array_keys($records);
            $genres = array_filter($genres); // remove blanks
            $genres = implode(',', $genres); // some books have a comma-separated list of genres
            $genres = explode(',', $genres); // so we need to implode and then explode the list
            $genres = array_unique($genres); // remove duplicates
            sort($genres);

            // extract only the required valid genres
            $genres = array_flip($genres);
            $genres = array_intersect_key(self::valid_genres(), $genres);

            // sort the values (but maintain keys)
            asort($genres);
        }

        return $genres;
    }

    /**
     * valid_genres
     *
     * @param string $genre     (optional, default='') a comma-separated list of genre codes to be expanded
     * @param string $separator (optional, default=', ') separator to use when imploding the list of genres
     * @return xxx
     * @todo Finish documenting this function
     */
    static public function valid_genres($genre='', $seperator=', ') {

        $validgenres = array(
            'all' => "All Genres",
            'ad' => 'Adventure',
            'an' => 'Animals',
            'bu' => 'Business',
            'bi' => 'Biography',
            'cl' => 'Classics',
            'ch' => "Children's literature",
            'co' => 'Comedy',
            'cu' => 'Culture',
            'fa' => 'Fantasy',
            'hf' => 'Historical fiction',
            'ge' => 'Geography/Environment',
            'gr' => 'Graphic Novel',
            'ho' => 'Horror',
            'hi' => 'Historical',
            'hu' => 'Human interest',
            'li' => 'Literature in Translation',
            'mo' => 'Movies',
            'mu' => 'Murder Mystery',
            'pu' => 'Puzzle',
            'ro' => 'Romance',
            'sc' => 'Science fiction',
            'sh' => 'Short stories',
            'sp' => 'Sports',
            'te' => 'Technology & Science',
            'th' => 'Thriller',
            'yo' => 'Young life/adventure',
            'yc' => 'Young children'
        );

        // if no genre is requested, return whole list of valid genre codes
        if ($genre=='') {
            return $validgenres;
        }

        // a genre code (list) has been given, so expand the codes to full descriptions
        $genre = explode(',', $genre);
        $genre = array_flip($genre);
        $genre = array_intersect_key($validgenres, $genre);
        $genre = implode($seperator, $genre);
        return $genre;
    }

    ///////////////////////////////////////////
    // format form to select a book
    ///////////////////////////////////////////

    /**
     * publishers_menu
     *
     * @param xxx $cmid
     * @param xxx $action
     * @param xxx $from
     * @param xxx $where
     * @param xxx $sqlparams
     * @param xxx $count (passed by reference)
     * @param xxx $record (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function publishers_menu($cmid, $action, $from, $where, $sqlparams, &$count, &$record) {
        global $DB;
        $output = '';

        $select = 'publisher, COUNT(*) AS countbooks';
        if ($records = $DB->get_records_sql("SELECT $select FROM $from WHERE $where GROUP BY publisher ORDER BY publisher", $sqlparams)) {
            $count = count($records);
        } else {
            $count = 0;
        }

        if ($count==0) {
            $output .= 'Sorry, there are currently no books for you';

        } else if ($count==1) {
            $record = reset($records);
            $output .= html_writer::tag('p', 'Publisher: '.$record->publisher);

        } else if ($count > 1) {
            $target_div = 'bookleveldiv';
            $target_url = "'view_books.php?id=$cmid&action=$action&publisher='+escape(this.options[this.selectedIndex].value)";

            $params = array('id' => 'id_publisher',
                            'name' => 'publisher',
                            'size' => min(10, count($records)),
                            'style' => 'width: 240px; float: left; margin: 0px 9px;',
                            'onchange' => "request($target_url, '$target_div')");
            $output .= html_writer::start_tag('select', $params);

            foreach ($records as $record) {
                $output .= html_writer::tag('option', "$record->publisher ($record->countbooks books)", array('value' => $record->publisher));
            }
            $record = null;

            if ($action=='takequiz' || $action=='noquiz' || $action=='awardbookpoints') {
                $output .= html_writer::end_tag('select');
                $output .= html_writer::tag('div', '', array('id' => $target_div));
            }
        }

        return $output;
    }

    /**
     * levels_menu
     *
     * @param xxx $publisher
     * @param xxx $cmid
     * @param xxx $action
     * @param xxx $from
     * @param xxx $where
     * @param xxx $sqlparams
     * @param xxx $count (passed by reference)
     * @param xxx $record (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function levels_menu($publisher, $cmid, $action, $from, $where, $sqlparams, &$count, &$record) {
        global $DB;
        $output = '';

        $where .= ' AND publisher = ?';
        array_push($sqlparams, $publisher);

        $select = "level, COUNT(*) AS countbooks, ROUND(SUM(rb.difficulty) / COUNT(*), 0) AS average_difficulty";
        if ($records = $DB->get_records_sql("SELECT $select FROM $from WHERE $where GROUP BY level ORDER BY average_difficulty", $sqlparams)) {
            $count = count($records);
        } else {
            $count = 0;
        }

        if ($count==0) {
            $output .= 'Sorry, there are currently no books for you by '.$publisher;
        } else if ($count==1) {
            $record = reset($records);
            if ($record->level != '' && $record->level != '--') {
                $output .= html_writer::tag('p', 'Level: '.$record->level, array('style' => 'float: left; margin: 0px 9px;'));
            }
        } else if ($count > 1) {
            //$output .= html_writer::tag('p', 'Choose a level');

            $target_div = 'bookiddiv';
            $target_url = "'view_books.php?id=$cmid&action=$action&publisher=$publisher&level='+escape(this.options[this.selectedIndex].value)";

            $params = array('id' => 'id_level',
                            'name' => 'level',
                            'size' => min(10, count($records)),
                            'style' => 'width: 240px; float: left; margin: 0px 9px;',
                            'onchange' => "request($target_url, '$target_div')");
            $output .= html_writer::start_tag('select', $params);

            foreach ($records as $record) {
                if ($record->level=='' || $record->level=='--') {
                    $displaylevel = $publisher;
                } else {
                    $displaylevel = $record->level;
                }
                $output .= html_writer::tag('option', "$displaylevel ($record->countbooks books)", array('value' => $record->level));
            }
            $record = null;

            if ($action=='takequiz' || $action=='noquiz' || $action=='awardbookpoints') {
                $output .= html_writer::end_tag('select');
                $output .= html_writer::tag('div', '', array('id' => $target_div));
            }
        }

        return $output;
    }

    /**
     * bookids_menu
     *
     * @param xxx $publisher
     * @param xxx $level
     * @param xxx $cmid
     * @param xxx $action
     * @param xxx $from
     * @param xxx $where
     * @param xxx $sqlparams
     * @param xxx $count (passed by reference)
     * @param xxx $record (passed by reference)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function bookids_menu($publisher, $level, $cmid, $action, $from, $where, $sqlparams, &$count, &$record) {
        global $DB;
        $output = '';

        $where .= " AND rb.publisher = ? AND rb.level = ?";
        array_push($sqlparams, $publisher, $level);

        $select = 'rb.*';
        if ($records = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY name", $sqlparams)) {
            $count = count($records);
        } else {
            $count = 0;
        }

        if ($count==0) {
            $output .= 'Sorry, there are currently no books for you by '.$publisher;
            $output .= (($level=='' || $level=='--') ? '' : " ($level)");

        } else if ($count==1) {
            $record = reset($records); // just one book found

        } else if ($count > 1) {
            //$output .= html_writer::tag('p', 'Book:');

            $target_div = 'booknamediv';
            $target_url = "'view_books.php?id=$cmid&action=$action&publisher=$publisher&level=$level&bookid='+this.options[this.selectedIndex].value";

            $params = array('id' => 'id_book',
                            'name' => 'book',
                            'size' => min(10, count($records)),
                            'style' => 'width: 360px; float: left; margin: 0px 9px;',
                            'onchange' => "request($target_url, '$target_div')");
            $output .= html_writer::start_tag('select', $params);

            foreach ($records as $record) {
                $output .= html_writer::tag('option', "[RL-$record->difficulty] $record->name", array('value' => $record->id));
            }

            $output .= html_writer::end_tag('select');
            if ($action=='takequiz' || $action=='noquiz' || $action='awardbookpoints') {
                $output .= html_writer::tag('div', '', array('id' => $target_div, 'style' => 'float: left; margin: 0px 9px;'));
            }
        }

        return $output;
    }

    /**
     * books_menu
     *
     * @param xxx $cmid
     * @param xxx $reader
     * @param xxx $userid
     * @param xxx $action
     * @return xxx
     * @todo Finish documenting this function
     */
    public function books_menu($cmid, $reader, $userid, $action='') {
        global $CFG, $DB, $OUTPUT;
        $output = '';

        // get parameters passed from browser
        $publisher = optional_param('publisher', null, PARAM_CLEAN); // book publisher
        $level     = optional_param('level',     null, PARAM_CLEAN); // book level
        $bookid    = optional_param('bookid',    null, PARAM_INT  ); // book id
        $action    = optional_param('action', $action, PARAM_CLEAN);

        // get SQL $from and $where statements to extract available books
        if ($action=='') {
            $hasquiz = null;
        } else if ($action=='noquiz' || $action=='awardbookpoints') {
            $hasquiz = false;
        } else {
            $hasquiz = true;
        }
        $hasquiz = null;
        list($from, $where, $sqlparams) = reader_available_sql($cmid, $reader, $userid, $hasquiz);

        if ($publisher===null) {

            $count = 0;
            $record = null;
            $output .= $this->publishers_menu($cmid, $action, $from, $where, $sqlparams, $count, $record);

            if ($count==0 || $count > 1) {
                return $output;
            }

            // otherwise, there is just one publisher, so continue and show the levels
            $publisher = $record->publisher;
        }

        if ($level===null) {

            $count = 0;
            $record = null;
            $output .= $this->levels_menu($publisher, $cmid, $action, $from, $where, $sqlparams, $count, $record);

            if ($count==0 || $count > 1) {
                return $output;
            }

            // otherwise there is just one level, so continue and show the books
            $level = $record->level;
        }

        $book = null;
        if ($bookid===null || $bookid===0) {

            $count = 0;
            $record = null;
            $output .= $this->bookids_menu($publisher, $level, $cmid, $action, $from, $where, $sqlparams, $count, $record);

            if ($count==0 || $count > 1) {
                return $output;
            }

            // otherwise there is just one book, so continue and show the book name
            $bookid = $record->id;
        }

        if ($book===null) {
            $params = array('id' => $bookid);
            if ($hasquiz===false) {
                $params['quizid'] = 0;
            }
            $book = $DB->get_record('reader_books', $params);
        }
        if ($action=='takequiz' && $this->reader->can_viewbooks()) {
            if (empty($book->quizid)) {
                $params = array('class' => 'awardpointsmanually', 'style' => 'max-width: 220px;');
                $output .= html_writer::tag('p', get_string('awardpointsmanually', 'mod_reader'), $params);
            } else if ($book->quizid < 0 && strpos($CFG->wwwroot, '/localhost/')) {
                $url = str_replace('localhost', php_uname('n'), $_SERVER['HTTP_REFERER']);
                $params = array('class' => 'restrictlocalhost', 'style' => 'max-width: 220px;');
                $output .= html_writer::tag('p', get_string('restrictlocalhost', 'mod_reader', $url), $params);
            } else {
                $params = array('id' => $cmid, 'book' => $bookid);
                $url = new moodle_url('/mod/reader/quiz/startattempt.php', $params);

                $params = array('class' => 'singlebutton readerquizbutton');
                $output .= $OUTPUT->single_button($url, get_string('takequizfor', 'mod_reader', $book->name), 'get', $params);

                list($cheatsheeturl, $strcheatsheet) = $this->cheatsheet_init($action);
                if ($cheatsheeturl) {
                    if ($level && $level != '--') {
                        $publisher .= ' - '.$level;
                    }
                    $output .= $this->cheatsheet_link($cheatsheeturl, $strcheatsheet, $publisher, $book);
                }
            }
        }

        if ($action=='noquiz') {
            $output .= $book->name;
            $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'book', 'value' => $bookid)).' ';
            $output .= html_writer::empty_tag('input', array('type' => 'submit', 'name' => 'submit', 'value' => get_string('go')));
        }

        if ($action=='awardbookpoints') {
            $output .= $book->name;
            $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'book', 'value' => $bookid));
        }

        return $output;
    }


    /**
     * users_menu
     *
     * @param xxx $cmid
     * @param xxx $reader
     * @param xxx $userid
     * @param xxx $action
     * @return xxx
     * @todo Finish documenting this function
     */
    public function users_menu($cmid, $reader, $userid, $action='') {
        global $DB, $OUTPUT;
        $output = '';

        // get values from form
        $gid = optional_param('gid', null, PARAM_ALPHANUM);
        $userid = optional_param('userid', null, PARAM_SEQUENCE);
        $attemptid = optional_param('attemptid', null, PARAM_SEQUENCE);

        if ($gid===null) {

            $label = '';
            $options = array();

            $strgroup = get_string('group', 'group');
            $strgrouping = get_string('grouping', 'group');

            if ($groupings = groups_get_all_groupings($reader->course)) {
                $label = $strgrouping;
                $has_groupings = true;
            } else {
                $has_groupings = false;
                $groupings = array();
            }

            if ($groups = groups_get_all_groups($reader->course)) {
                if ($label) {
                    $label .= ' / ';
                }
                $label .= $strgroup;
                $has_groups = true;
            } else {
                $has_groups = false;
                $groups = array();
            }

            foreach ($groupings as $gid => $grouping) {
                if ($count = mod_reader::count_grouping_members($gid)) {
                    if ($has_groups) {
                        $prefix = $strgrouping.': ';
                    } else {
                        $prefix = '';
                    }
                    $options["grouping$gid"] = $prefix.format_string($grouping->name).' ('.$count.' users)';
                }
            }

            foreach ($groups as $gid => $group) {
                if ($count = mod_reader::count_group_members($gid)) {
                    if ($has_groupings) {
                        $prefix = $strgroup.': ';
                    } else {
                        $prefix = '';
                    }
                    $options["group$gid"] = $prefix.format_string($group->name).' ('.$count.' users)';
                }
            }

            $count = count($options);

            if ($count==1) {
                $gid = 0;
            } else if ($count==1) {
                $gid = key($options);
                $option = current($options);
                $output .= html_writer::tag('p', $label.': '.$option);

            } else if ($count > 1) {
                $target_div = 'useriddiv';
                $target_url = "'view_users.php?id=$cmid&action=$action&gid='+escape(this.options[this.selectedIndex].value)";

                $params = array('id' => 'id_users',
                                'name' => 'users',
                                'size' => min(10, $count),
                                'style' => 'width: 240px; float: left; margin: 0px 9px;',
                                'onchange' => "request($target_url, '$target_div')");
                $output .= html_writer::start_tag('select', $params);

                $options = array('' => get_string('allgroups')) + $options;
                foreach ($options as $id => $option) {
                    $output .= html_writer::tag('option', $option, array('value' => $id));
                }
                $option = null;

                $output .= html_writer::end_tag('select');
                $output .= html_writer::tag('div', '', array('id' => $target_div));
            }

            if ($gid===null) {
                return $output;
            }
        }

        if ($userid===null) {
            $userids = array();
            if (substr($gid, 0, 5)=='group') {
                if (substr($gid, 5, 3)=='ing') {
                    $gids = groups_get_all_groupings($reader->course);
                    $gid = intval(substr($gid, 8));
                    if ($gids && array_key_exists($gid, $gids)) {
                        $userids = mod_reader::get_grouping_userids($gid);
                    }
                } else {
                    $gids = groups_get_all_groups($reader->course);
                    $gid = intval(substr($gid, 5));
                    if ($gids && array_key_exists($gid, $gids)) {
                        $userids = mod_reader::get_group_userids($gid);
                    }
                }
            } else if ($gid=='' || $gid=='all') {
                if ($userids = $DB->get_records('reader_attempts', array('readerid' => $reader->id), 'userid', 'DISTINCT userid')) {
                    $userids = array_keys($userids);
                } else {
                    $userids = array();
                }
            }

            $count = count($userids);
            if ($count==0) {
                $userid = '';

            } else if ($count==1) {
                $userid = reset($userids);

            } else {
                list($select, $params) = $DB->get_in_or_equal($userids); // , SQL_PARAMS_NAMED, '', true
                $select = "deleted = ? AND id $select";
                array_unshift($params, 0);
                if ($users = $DB->get_records_select('user', $select, $params, 'lastname,firstname', 'id, firstname, lastname')) {

                    $target_div = 'usernamediv';
                    $target_url = "'view_users.php?id=$cmid&action=$action&gid=$gid&userid='+escape(this.values)";

                    $params = array('id' => 'id_userid',
                                    'name' => 'userid',
                                    'size' => min(10, $count),
                                    'multiple' => 'multiple',
                                    'style' => 'width: 240px; float: left; margin: 0px 9px;',
                                    'onchange' => "this.values = new Array();".
                                                  "for (var i=0; i<this.options.length; i++) {".
                                                      "if (this.options[i].selected) {".
                                                          "this.values.push(this.options[i].value);".
                                                      "}".
                                                  "}".
                                                  "this.values = this.values.join(',');".
                                                  "request($target_url, '$target_div')");
                    $output .= html_writer::start_tag('select', $params);

                    // force case for fullnames
                    foreach ($users as $user) {
                        $user->firstname = preg_replace_callback('/\b[a-z]/', 'strtoupper', strtolower($user->firstname));
                        $user->lastname = strtoupper($user->lastname);
                    }

                    foreach ($users as $user) {
                        $output .= html_writer::tag('option', fullname($user), array('value' => $user->id));
                    }

                    $output .= html_writer::end_tag('select');
                    if ($action=='takequiz') {
                        $output .= html_writer::tag('div', '', array('id' => $target_div));
                    }
                }

                return $output;
            }
        }

        $userids = explode(',', $userid);
        $userids = array_filter($userids); // remove blanks
        if ($count = count($userids)) {
            $output .= html_writer::tag('p', count($userids)." users selected: $userid");
            $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'userids', 'id' => 'id_userids', 'value' => $userid));
        }

        return $output;
    }

    /**
     * cheatsheet_init
     *
     * @param xxx $action
     * @return xxx
     * @todo Finish documenting this function
     */
    function cheatsheet_init($action) {
        global $CFG;

        $cheatsheeturl = '';
        $strcheatsheet = '';

        // if there is a "cheatsheet" script, make it available (for developer site admins only)
        if ($action=='takequiz' && $this->reader->can('config', 'moodle/site')) {
            if (file_exists($CFG->dirroot.'/mod/reader/admin/tools/print_cheatsheet.php')) {
                $cheatsheeturl = $CFG->wwwroot.'/mod/reader/admin/tools/print_cheatsheet.php';
                $strcheatsheet = get_string('cheatsheet', 'mod_reader');
            }
        }

        return array($cheatsheeturl, $strcheatsheet);
    }

    /**
     * cheatsheet_link
     *
     * @param xxx $cheatsheeturl
     * @param xxx $strcheatsheet
     * @param xxx $publisher
     * @param xxx $book
     * @return xxx
     * @todo Finish documenting this function
     */
    function cheatsheet_link($cheatsheeturl, $strcheatsheet, $publisher, $book) {
        $url = new moodle_url($cheatsheeturl, array('publishers' => $publisher, 'books' => $book->id));
        $params = array('href' => $url, 'onclick' => "this.target='cheatsheet'; return true;");
        return html_writer::tag('small', html_writer::tag('a', $strcheatsheet, $params));
    }

    ///////////////////////////////////////////
    // format progress bar
    ///////////////////////////////////////////

    /**
     * progressbar
     *
     * @uses $CFG
     * @uses $DB
     * @uses $USER
     * @param xxx $progress
     * @param xxx $reader
     * @return xxx
     * @todo Finish documenting this function
     */
    function progressbar($progress) {
        global $CFG, $DB, $USER;

        $params = array('userid' => $USER->id, 'readerid' => $this->reader->id);
        if ($record = $DB->get_record('reader_levels', $params)) {
            $goal = $record->goal;
            $currentlevel = $record->currentlevel;
        } else {
            $goal = 0;
            $currentlevel = 0;
            $record = (object)array(
                'userid'         => $USER->id,
                'readerid'       => $this->reader->id,
                'startlevel'     => 0,
                'currentlevel'   => $currentlevel,
                'stoplevel'      => $this->reader->stoplevel,
                'allowpromotion' => 1,
                'goal'           => $goal,
                'time'           => time(),
            );
            $record->id = $DB->insert_record('reader_levels', $record);
        }

        if (! $goal) {
            if ($records = $DB->get_records('reader_goals', array('readerid' => $this->reader->id))) {
                foreach ($records as $record) {
                    if ($record->groupid && ! groups_is_member($record->groupid, $USER->id)) {
                        continue; // wrong group
                    }
                    if ($currentlevel != $record->level) {
                        continue; // wrong level
                    }
                    $goal = $record->goal;
                }
            }
        }

        if (! $goal) {
            $goal = $this->reader->goal;
        }

        $goal = intval($goal);
        if ($goal > 1000000) {
            $goal = 1000000;
        } else if ($goal < 0) {
            $goal = 0;
        }

        $progress = intval($progress);
        if ($progress > 1000000) {
            $progress = 1000000;
        } else if ($progress < 0) {
            $progress = 0;
        }

        $max = max($goal, $progress);
        switch (true) {
            // Note: colors are the same as the
            // colors blocks on the progress bar
            case ($max <= 10000):
                $max = 10000;
                $bordercolor = '#FF0000'; // red
                $backgroundcolor = '#FFE5E5';
                break;
            case ($max <= 20000):
                $max = 20000;
                $bordercolor = '#FF9900'; // orange
                $backgroundcolor = '#FFF2E5';
                break;
            case ($max <= 30000):
                $max = 30000;
                $bordercolor = '#E5E600'; // yellow
                $backgroundcolor = '#FFFF99';
                break;
            case ($max <= 40000):
                $max = 40000;
                $bordercolor = '#CCFF66'; // light green
                $backgroundcolor = '#EEFFCC';
                break;
            case ($max <= 50000):
                $max = 50000;
                $bordercolor = '#33CC33'; // dark green
                $backgroundcolor = '#D6F5D6';
                break;
            case ($max <= 100000):
                $max = 100000;
                $bordercolor = '#66CCFF'; // light blue
                $backgroundcolor = '#CCEEFF';
                break;
            case ($max <= 250000):
                $max = 250000;
                $bordercolor = '#3333CC'; // dark blue
                $backgroundcolor = '#D6D6F5';
                break;
            case ($max <= 500000):
                $max = 500000;
                $bordercolor = '#6600CC'; // purple
                $backgroundcolor = '#E5CCFF';
                break;
            default:
                $max = 1000000;
                $bordercolor = '#FF3399'; // pink
                $backgroundcolor = '#FFCCE5';
        }

        // these measurements are for the "clip-path" attribute
        // Note: The total image width is 800 px and the
        //       left/right margins are each 3px wide
        //       3px is (3 / 8) = 0.375% of 800px
        $width = 100;
        $margin = 0.375;
        $available = ($width - (2 * $margin));
        $goalpercent = ($available * ($goal / $max));
        $leftpercent = ($available * ($progress / $max));
        $rightpercent = ($available - $leftpercent);
        $goalpercent = round($goalpercent + $margin);
        $leftpercent = round($leftpercent + $margin).'%';
        $rightpercent = round($rightpercent + $margin).'%';

        // these measurements are for the "clip" attribute
        // in browsers that ignore the "clip-path" attribute
        $width = 800;
        $margin = 3;
        $available = ($width - (2 * $margin));
        $leftpixel = ($available * ($progress / $max));
        $rightpixel = ($available - $leftpixel);
        $leftpixel = round($leftpixel + $margin + 2).'px'; // small correction required here
        $rightpixel = round($rightpixel + $margin).'px';

        // convert $max to a 3-digit number
        // padded with zeroes on the left
        $max = sprintf('%03d', $max / 10000);

        $html = '';
/*        $html .= html_writer::empty_tag('img', array('src'   => new moodle_url("/mod/reader/img/progressbar.$max.grey.png"),
                                                     'class' => 'grey'));
        $html .= html_writer::empty_tag('img', array('src'   => new moodle_url("/mod/reader/img/progressbar.$max.color.png"),
                                                     'class' => 'color',
                                                     'style' => "clip: rect(auto $leftpixel auto auto); ". // Safari, Firefox & IE
                                                                "clip-path: inset(0px $rightpercent 0px 0px)")); // Chrome
                                                                // https://css-tricks.com/clipping-masking-css/
        $html .= html_writer::empty_tag('img', array('src'   => new moodle_url('/mod/reader/img/now.png'),
                                                     'class' => 'now',
                                                     'style' => "left: $leftpercent;"));
        if ($goal) {
            switch (true) {
                case ($goalpercent >= 90): $leftpixel = '-20px'; break;
                case ($goalpercent >= 80): $leftpixel = '-19px'; break;
                case ($goalpercent >= 70): $leftpixel = '-18px'; break;
                case ($goalpercent >= 60): $leftpixel = '-17px'; break;
                default: $leftpixel = '-16px'; // as in styles.css
            }
            $leftpercent = $goalpercent.'%';
            $text = html_writer::empty_tag('img', array('src' => new moodle_url('/mod/reader/img/goal.png'), 'style' => "left: $leftpixel;"));
            $html .= html_writer::tag('div', $text, array('class' => 'goal', 'style' => "left: $leftpercent;"));
        }
        $html = html_writer::tag('div', $html, array('id' => 'ProgressBarImages'));

        $text = get_string('in1000sofwords', 'mod_reader');
        $html .= html_writer::tag('div', $text, array('id' => 'ProgressBarFootnote', 'style' => 'background-color:'.$backgroundcolor.';'));

        $html = html_writer::tag('div', $html, array('id' => 'ProgressBar', 'style' => 'border-color:'.$bordercolor.';'));
        $html .= html_writer::tag('div', '', array('style' => 'clear: both; height: 1.0em; width: 1.0em;'));
*/

    ///////////////////////////////////////////
    // new progress bar using Bootstrap CSS
    ///////////////////////////////////////////

    /**
     * progressbar
     *
     * @uses $CFG
     * @uses $DB
     * @uses $USER
     * @param xxx $progress
     * @param xxx $goal
     * @param xxx $reader
     * @todo Finish documenting this function
     */
// START ADDED CODE
// First avoid dividing by zero
        $npggoal = ($goal + 1);
        switch (true) {
            case ($npggoal <= 1):
                $npggoal = 30000;
                break;
            case ($npggoal > 1):
                $npggoal = ($goal);
                break;
        }


// Get value for $npgbarprog as an integer between 0 and 100 % of goal reached -- will be width of progress-bar
        $npgbarprog = ($progress / $npggoal);

        switch (true) {
            case ($npgbarprog >= 1):
		$npgextrafactor = intval($npgbarprog); // Set factor for multiplication of Additional words read bar
		$npgextragoal = ($npggoal * $npgextrafactor); // Set goal (100%) for the Additional words read bar
                $npgbarprog = 100;
		$npgextrawords = ($progress - $npggoal);
		$npgextraprog = ($npgextrawords / $npgextragoal); // Prepare percent for second progress bar
                break;
            case ($npgbarprog < 1):
                $npgbarprog = ($npgbarprog * 100);
                $npgbarprog = intval($npgbarprog);
                break;
            default:
                $npgbarprog = 0;
        }

       switch (true) {
            case ($npgbarprog <= 25):
                $npgcolor = '#d9534f'; // red
                break;
            case ($npgbarprog <= 50):
                $npgcolor = '#fa4f00'; // orange
                break;
            case ($npgbarprog <= 75):
                $npgcolor = '#ed9c2c'; // yellow
                break;
            case ($npgbarprog < 100):
                $npgcolor = '#5cb85c'; // green
                break;
            case ($npgbarprog >= 100):
                $npgcolor = '#1177d1'; // blue
                break;
            default:
                $npgcolor = '#5cb85c'; // green
        }


	$npgquartergoal = intval($npggoal / 4);
	$npghalfgoal = intval($npggoal / 2);
	$npgthreequartergoal = ($npghalfgoal + $npgquartergoal);

        $npgnums = '<div>0 words　　</div><div>'.$npgquartergoal.' words</div><div>'.$npghalfgoal.' words</div><div>'.$npgthreequartergoal.' words</div><div>'.$npggoal.' words</div>';
        $npgdivs = '<div>|</div><div>|</div><div>|</div><div>|</div><div>|</div>';

// First progress bar -- Always visible
        $npgtitle = 'Progress'; // To Do: Replace this with get_string
        $html .= html_writer::tag('h3', $npgtitle, array('class' => 'progtitle')); // Add title for extra progressbar
        $npgpercent = $npgbarprog.'%';
        $npgcapn = $npgpercent.' Complete!';
        $npgbar = html_writer::tag('div', $npgcapn, array('class' => 'progress-bar', 'style' => 'background-color:'.$npgcolor.'; width:'.$npgpercent.';')); // Add the coloured bit
        $html .= html_writer::tag('div', $npgbar, array('class' => 'progress')); // Embed the coloured bit of the progress bar in its container and add it to $html
        $html .= html_writer::tag('div', $npgdivs, array('class' => 'prognums npgdivs')); // Add dividers to the bottom of the progressbar
        $html .= html_writer::tag('div', $npgnums, array('class' => 'prognums')); // Add numbers to the bottom of the progressbar
        $html .= html_writer::tag('div', '', array('style' => 'clear: both; height: 1.0em; width: 1.0em;')); // Added to the end to make a space before the rest of the page

// Start Second BAR (ONLY VISIBLE when goal exceeded. Extra words -- up to 2x Goal)
        if ($npgextraprog > 0) {
                // Multiply quarterly goals by $npgextrafactor for second bar
                $npgquartergoal = ($npggoal + ($npgquartergoal * $npgextrafactor));
                $npghalfgoal = ($npggoal + ($npghalfgoal * $npgextrafactor));
                $npgthreequartergoal =  ($npggoal + ($npgthreequartergoal * $npgextrafactor));
                $npgxgoal =  ($npggoal + ($npggoal * $npgextrafactor));
                $npgnums = '<div>'.$npggoal.' words</div><div>'.$npgquartergoal.' words</div><div>'.$npghalfgoal.' words</div><div>'.$npgthreequartergoal.' words</div><div>'.$npgxgoal.' words</div>';
                $npgextratitle = 'Additional Words Read:'; // To Do: Replace this with get_string
                $html .= html_writer::tag('h3', $npgextratitle, array('class' => 'progtitle')); // Add title for extra progressbar
                $npgpercent = intval($npgextraprog * 100).'%'; // Calculate progress bar percentage for extra words
                $npgcapn = $npgextrawords.' Extra words read!';
                $npgbar = html_writer::tag('div', $npgcapn, array('class' => 'progress-bar', 'style' => 'background-color:'.$npgcolor.'; width:'.$npgpercent.';')); // Add the coloured bit
                $html .= html_writer::tag('div', $npgbar, array('class' => 'progress')); // Embed the coloured bit of the progress bar in its container and add it to $html
                $html .= html_writer::tag('div', $npgdivs, array('class' => 'prognums npgdivs')); // Add dividers to the bottom of the progressbar
                $html .= html_writer::tag('div', $npgnums, array('class' => 'prognums')); // Add numbers to the bottom of the progressbar
                $html .= html_writer::tag('div', '', array('style' => 'clear: both; height: 1.0em; width: 1.0em;')); // Added to the end to make a space before the rest of the page
        }





// END New progress bar




        return $html;
    }

    ///////////////////////////////////////////
    // format messages from the teacher
    ///////////////////////////////////////////

    /**
     * Format a message from the teacher to students
     * that will be displayed on the view.php page
     *
     * @uses $DB
     * @param stdclass $message
     * @return xxx
     * @todo Finish documenting this function
     */
    function message($message) {
        global $DB;
        static $started_list = false;

        $output = '';

        if ($started_list==false) {
            $started_list = true; // only do this once
            $text = get_string('messagefromyourteacher', 'mod_reader');
            $output .= html_writer::tag('h2', $text, array('class' => 'ReadingReportTitle'));
        }

        $params = array('class' => 'readermessage');
        if ($message->timemodified > (time() - ( 48 * 60 * 60))) {
            // a new message (modified within the last 48 hours)
            $params['class'] .= ' recentmessage';
        }
        $output .= html_writer::start_tag('div', $params);

        $text = format_text($message->messagetext, $message->messageformat);
        $output .= html_writer::tag('div', $text, array('class' => 'readermessagetext'));

        // add link to teacher's profile
        if ($user = $DB->get_record('user', array('id' => $message->teacherid))) {
            $params = array('id' => $message->teacherid, 'course' => $this->reader->course->id);
            $params = array('href' => new moodle_url('/user/view.php', $params), 'onclick' => "this.target='_blank'");
            $text = html_writer::tag('a', fullname($user, true), $params);
            $output .= html_writer::tag('div', $text, array('class' => 'readermessageteacher'));

        }

        $output .= html_writer::end_tag('div');
        return $output;
    }

    ///////////////////////////////////////////
    // format reading rates
    ///////////////////////////////////////////

    /**
     * Format reading rates for display on the view.php page
     *
     * @return HTML string
     */
    public function rates() {
        $output = '';
        if ($rates = $this->reader->get_rates()) {
            foreach ($rates as $rate) {
                $output .= $this->rate($rate);
            }
        }
        if ($output) {


            $table = new html_table();
            $table->attributes['class'] = 'generaltable RatesTable';

            $table->data[] = new html_table_row(array(
                new html_table_cell(get_string('recommendedreadingrates', 'mod_reader'))
            ));
            $table->data[] = new html_table_row(array(
                new html_table_cell($output)
            ));

            // make sure we get the required row classs in Moodle >= 2.9
            if (property_exists($table, 'caption')) {
                if (empty($table->rowclasses)) {
                    $table->rowclasses = array();
                }
                $table->rowclasses[0] = 'r0';
            }

            $output = html_writer::table($table);
        }
        return $output;
    }

    /**
     * Format a single reading rate for display on the view.php page
     *
     * @param object $rate
     * @return HTML string
     */
    public function rate($rate) {
        switch ($rate->type) {
            case mod_reader::RATE_MAX_QUIZ_ATTEMPT: $type = 'maxquizattemptrate'; break;
            case mod_reader::RATE_MIN_QUIZ_ATTEMPT: $type = 'minquizattemptrate'; break;
            case mod_reader::RATE_MAX_QUIZ_FAILURE: $type = 'maxquizfailurerate'; break;
        }
        $text = $type.'text';
        $output = '';
        $output .= html_writer::tag('b', get_string($type, 'mod_reader'));
        $output .= html_writer::empty_tag('br');
        $output .= get_string($text, 'mod_reader', $this->rate_duration($rate));
        $output .= $this->rate_actions($rate);
        $output = html_writer::tag('p', $output);
        return $output;
    }

    /**
     * Format a rate duration for display on the view.php page
     *
     * @param object $rate
     * @return HTML string
     */
    public function rate_duration($rate) {
        switch (true) {
            case ($rate->attempts==1 && $rate->duration > 0): $duration = 'rateoneinduration'; break;
            case ($rate->attempts > 1 && $rate->duration > 0): $duration = 'ratemanyinduration'; break;
            case ($rate->attempts > 1 && $rate->duration == 0): $duration = 'ratemanyconsecutively'; break;
            default: return ''; // shouldn't happen
        }
        $a = (object)array(
            'attempts' => $rate->attempts,
            'duration' => format_time($rate->duration)
        );
        return get_string($duration, 'mod_reader', $a);
    }

    /**
     * Format a set of rate actions for display on the view.php page
     *
     * @param object $rate
     * @return HTML string
     */
    public function rate_actions($rate) {
        $actions = array();
        $types = array(
            mod_reader::ACTION_DELAY_QUIZZES => 'actiondelayquizzestext',
            mod_reader::ACTION_BLOCK_QUIZZES => 'actionblockquizzestext',
            mod_reader::ACTION_EMAIL_STUDENT => 'actionemailstudenttext',
            mod_reader::ACTION_EMAIL_TEACHER => 'actionemailteachertext'
        );
        foreach ($types as $type => $name) {
            if ($rate->action & $type) {
                $actions[] = get_string($name, 'mod_reader');
            }
        }
        if (count($actions)) {
            return html_writer::alist($actions, array(), 'ul');
        } else {
            return '';
        }
    }

    ///////////////////////////////////////////
    // format footer
    ///////////////////////////////////////////

    public function footer() {
        $output = '';
        $output .= $this->credits();
        $output .= parent::footer();
        return $output;
    }

    /**
     * credits
     */
    public function credits() {
        $params = array('src' => new moodle_url('/mod/reader/img/credit.jpg'), 'class' => 'readercredits');
        if ($version = $this->version()) {
            $version = json_encode($version);
            $params['onclick'] = "alert($version);";
        }
        $img = html_writer::empty_tag('img', $params);
        $img = html_writer::tag('center', $img);
        return $img;
    }

    /**
     * version
     */
    public function version() {
        if ($this->reader->can_viewversion()) {
            $output = array();

            $search = '/(\d{4})(\d{2})(\d{2})(\d{2})/';
            $replace = '$1-$2-$3 ($4)';

            if ($version = get_config('', 'version')) {
                $release = get_config('', 'release');
                $output[] = "Moodle:  ($version)  $release";
            }

            $plugin = 'mod_reader';
            if ($version = get_config($plugin, 'version')) {
                $release = preg_replace($search, $replace, $version);
                $output[] = get_string('pluginname', $plugin).":  ($version)  $release";
            }

            $plugin = 'qtype_ordering';
            if ($version = get_config($plugin, 'version')) {
                $release = preg_replace($search, $replace, $version);
                $output[] = get_string('pluginname', $plugin).":  ($version)  $release";
            }

            return implode("\n", $output);
        }
        return '';
    }

    /**
     * notavailable
     *
     * @param string $plugin name
     */
    public function notavailable($plugin) {
        switch (true) {

            case ($this->reader->available):
                $msg = '';
                break;

            case ($this->reader->can_viewbooks()==false):
                $msg = get_string('reader:viewbooks', $plugin);
                $msg = get_string('nopermissions', 'error', $msg);
                break;

            case ($this->reader->subnet && ! address_in_subnet(getremoteaddr(), $this->reader->subnet)):
                $msg = get_string('subneterror', 'quiz');
                break;

            case ($this->reader->availablefrom && $this->reader->availablefrom > $this->reader->time):
                $msg = userdate($this->reader->availablefrom);
                $msg = get_string('availablenotyet', $plugin, $msg);
                break;

            case ($this->reader->availableuntil && $this->reader->availableuntil < $this->reader->time):
                $msg = userdate($this->reader->availableuntil);
                $msg = get_string('availablenolonger', $plugin, $msg);
                break;

            default:
                $msg = ''; // reader is open and not closed, and user can view books
        }

        if ($msg) {
            $url = new moodle_url('/course/view.php', array('id' => $this->reader->course->id));
            $msg .= html_writer::tag('p', $this->continue_button($url));
            $msg = $this->box($msg, 'generalbox', 'notice');
        }

        return $msg;
    }

    /**
     * readonly
     *
     * @param string $plugin
     */
    public function readonly($plugin) {
        if ($this->reader->readonly==false) {
            return '';
        }
        $msg = '';
        switch (true) {
            case ($this->reader->readonlyuntil && $this->reader->readonlyuntil > $this->reader->time):
                $msg = userdate($this->reader->readonlyuntil);
                $msg = get_string('readonlyuntildate', $plugin, $msg);
                $msg = html_writer::tag('p', $msg);
                break;
            case ($this->reader->readonlyfrom && $this->reader->readonlyfrom < $this->reader->time):
                $msg = userdate($this->reader->readonlyfrom);
                $msg = get_string('readonlysincedate', $plugin, $msg);
                $msg = html_writer::tag('p', $msg);
                break;
        }
        $title = get_string('readonlymode', $plugin);
        $msg = html_writer::tag('p', get_string('readonlymode_desc', $plugin)).$msg;
        return html_writer::tag('h2',  $title, array('class' => 'ReadingReportTitle')).
               html_writer::tag('div', $msg,   array('class' => 'readermessage'));
    }
}

