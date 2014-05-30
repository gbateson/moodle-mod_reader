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
defined('MOODLE_INTERNAL') || die;

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
    const TAB_QUIZZES   = 5;
    const TAB_USERS     = 6;
    const TAB_TOOLS = 7;
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

    /**
     * available_sql
     *
     * @param xxx $noquiz
     * @return xxx
     * @todo Finish documenting this function
     */
    public function available_sql($noquiz=false) {
        global $USER;

        if ($noquiz) {
            return array('{reader_books}', 'quizid = ? AND hidden = ?', array(0, 0)); // $from, $where, $params
        }

        // a teacher / admin can always access all the books
        if ($this->reader->can_managebooks()) {
            return array('{reader_books}', 'quizid > ? AND hidden = ?', array(0, 0)); // $from, $where, $params
        }

        $userid = $USER->id;

        // we want to get a list of all books available to this user
        // a book is available if it satisfies the following conditions:
        // (1) the book is not hidden
        // (2) the quiz for the book has NEVER been attempted before by this user
        // (3) EITHER the book has an empty "sametitle" field
        //     OR the "sametitle" field is different from that of any books whose quizzes this user has taken before
        // (4) EITHER the reader activity's "levelcheck" field is empty
        //     OR the level of the book is one of the levels this user is currently allowed to take in this reader

        // "id" values of books whose quizzes this user has already attempted
        $recordids  = 'SELECT rb.id '.
                      'FROM {reader_attempts} ra LEFT JOIN {reader_books} rb ON ra.bookid = rb.id '.
                      'WHERE ra.userid = ? AND ra.deleted = ? AND rb.id IS NOT NULL';

        // "sametitle" values for books whose quizzes this user has already attempted
        $sametitles = 'SELECT DISTINCT rb.sametitle '.
                      'FROM {reader_attempts} ra LEFT JOIN {reader_books} rb ON ra.bookid = rb.id '.
                      'WHERE ra.userid = ? AND ra.deleted = ? AND rb.id IS NOT NULL AND rb.sametitle <> ?';

        $from       = '{reader_books}';
        $where      = "id NOT IN ($recordids) AND (sametitle = ? OR sametitle NOT IN ($sametitles)) AND hidden = ?";
        $sqlparams = array($userid, 0, '', $userid, 0, '', 0);

        $levels = array();
        if (isset($_SESSION['SESSION']->reader_teacherview) && $_SESSION['SESSION']->reader_teacherview == 'teacherview') {
            // do nothing - this is a teacher
        } else if ($this->reader->levelcheck == 0) {
            // do nothing - level checking is disabled
        } else {
            // a student with level-checking enabled
            $leveldata = reader_get_level_data($this->reader, $userid);
            if ($leveldata['onthislevel'] > 0 && $leveldata['currentlevel'] >= 0) {
                $levels[] = $leveldata['currentlevel'];
            }
            if ($leveldata['onprevlevel'] > 0 && $leveldata['currentlevel'] >= 1) {
                $levels[] = ($leveldata['currentlevel'] - 1);
            }
            if ($leveldata['onnextlevel'] > 0) {
                $levels[] = ($leveldata['currentlevel'] + 1);
            }
            if (empty($levels)) {
                $levels[] = 0; // user can't take any more quizzes - shouldn't happen !!
            }
        }

        if ($levels = implode(',', $levels)) {
            if ($this->reader->bookinstances) {
                // we are maintaining a list of book difficulties for each course, so we must check "reader_books_instances"
                $from  .= ' rb LEFT JOIN {reader_book_instances} rbi ON rbi.bookid = rb.id AND rbi.readerid = '.$this->reader->id;
                $where .= " AND ((rbi.id IS NULL AND rb.difficulty IN ($levels)) OR (rbi.id IS NOT NULL AND rbi.difficulty IN ($levels)))";
            } else {
                $where .= " AND difficulty IN ($levels)";
            }
        }

        return array($from, $where, $sqlparams);
    }

    /**
     * available_items
     *
     * @param xxx $action
     * @return xxx
     * @todo Finish documenting this function
     */
    public function available_items($action='') {

        // get parameters passed from browser
        $publisher = optional_param('publisher', null, PARAM_CLEAN); // book publisher
        $level     = optional_param('level',     null, PARAM_CLEAN); // book level
        $bookid    = optional_param('bookid',    null, PARAM_INT  ); // book id
        $action    = optional_param('action', $action, PARAM_CLEAN);

        // get SQL $from and $where statements to extract available books
        $noquiz = ($action=='noquiz' || $action=='awardbookpoints');
        list($from, $where, $sqlparams) = $this->available_sql($noquiz);

        switch (true) {
            case ($publisher===null || $publisher===''):
                $output = '';
                $output .= $this->request_js();
                $output .= html_writer::start_tag('div', array('id' => 'publishers'));
                $output .= $this->available_publishers($action, $from, $where, $sqlparams);
                $output .= html_writer::end_tag('div');
                return $output;

            case ($level===null || $level===''):
                return $this->available_levels($publisher, $action, $from, $where, $sqlparams);

            case ($bookid===null || $bookid===0):
                return $this->available_books($publisher, $level, $action, $from, $where, $sqlparams);

            default:
                return $this->available_book($bookid, $action, $from, $where, $sqlparams);
        }
    }

    /**
     * available_items_url
     *
     * @param xxx $url
     * @param xxx $params
     * @return xxx
     * @todo Finish documenting this function
     */
    public function available_items_url($url, $params) {
        $url = new moodle_url($url, $params);
        $url = "$url"; // convert to string
        if (substr($url, -1)=='=') {
            // Moodle <= 2.4
            $url = substr($url, 0 ,-1);
        }
        return "'$url='+escape(this.options[this.selectedIndex].value)";
    }

    /**
     * available_publishers
     *
     * @param xxx $action
     * @param xxx $from
     * @param xxx $where
     * @param xxx $sqlparams
     * @return xxx
     * @todo Finish documenting this function
     */
    public function available_publishers($action, $from, $where, $sqlparams) {
        global $DB;
        $output = '';

        $select = 'publisher, COUNT(*) AS countbooks';
        if ($records = $DB->get_records_sql("SELECT $select FROM $from WHERE $where GROUP BY publisher ORDER BY publisher", $sqlparams)) {
            $count = count($records);
        } else {
            $count = 0;
        }

        $output .= html_writer::tag('div', get_string('publisher', 'reader'), array('class' => 'selecteditemhdr'));

        if ($count==0) {
            if ($this->reader->can_managebooks()) {
                $output .= get_string('nobooksfound', 'reader');
            } else {
                $output .= get_string('nobooksinlist', 'reader');
            }

        } else if ($count==1) {
            $record = reset($records);
            $output .= html_writer::tag('div', $record->publisher, array('class' => 'selecteditemtxt'));

            $output .= html_writer::start_tag('div', array('id' => 'levels'));
            $output .= $this->available_levels($record->publisher, $action, $from, $where, $sqlparams);
            $output .= html_writer::end_tag('div');

        } else if ($count > 1) {
            $params = array('action'    => $action,
                            'mode'      => $this->mode,
                            'id'        => $this->reader->cm->id,
                            'publisher' => ''); // will be added by javascript
            $url = $this->available_items_url('/mod/reader/view_books.php', $params);

            $params = array('id' => 'id_publisher',
                            'name' => 'publisher',
                            'size' => min(10, count($records)),
                            'onchange' => "request($url, 'levels')");
            $output .= html_writer::start_tag('select', $params);

            foreach ($records as $record) {
                $output .= html_writer::tag('option', "$record->publisher ($record->countbooks books)", array('value' => $record->publisher));
            }
            $record = null;

            $output .= html_writer::end_tag('select');
            $output .= html_writer::tag('div', '', array('id' => 'levels'));
        }

        return $output;
    }

    /**
     * available_levels
     *
     * @param xxx $publisher
     * @param xxx $action
     * @param xxx $from
     * @param xxx $where
     * @param xxx $sqlparams
     * @return xxx
     * @todo Finish documenting this function
     */
    public function available_levels($publisher, $action, $from, $where, $sqlparams) {
        global $DB;
        $output = '';

        $where .= ' AND publisher = ?';
        array_push($sqlparams, $publisher);

        $select = "level, COUNT(*) AS countbooks, ROUND(SUM(difficulty) / COUNT(*), 0) AS average_difficulty";
        if ($records = $DB->get_records_sql("SELECT $select FROM $from WHERE $where GROUP BY level ORDER BY average_difficulty", $sqlparams)) {
            $count = count($records);
        } else {
            $count = 0;
        }

        $selecteditemhdr = html_writer::tag('div', get_string('level', 'reader'), array('class' => 'selecteditemhdr'));

        if ($count==0) {
            $output .= 'Sorry, there are currently no books for you by '.$publisher;

        } else if ($count==1) {
            $record = reset($records);
            if ($record->level=='' || $record->level=='--' || $record->level=='No Level') {
                // do nothing
            } else {
                $output .= $selecteditemhdr;
                $output .= html_writer::tag('div', $record->level, array('class' => 'selecteditemtxt'));
            }
            $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'level', 'value' => $record->level));

            $output .= html_writer::start_tag('div', array('id' => 'books'));
            $output .= $this->available_books($publisher, $record->level, $action, $from, $where, $sqlparams);
            $output .= html_writer::end_tag('div');

        } else if ($count > 1) {
            $output .= $selecteditemhdr;

            $params = array('action'    => $action,
                            'mode'      => $this->mode,
                            'id'        => $this->reader->cm->id,
                            'publisher' => $publisher,
                            'level'     => ''); // will be added by javascript
            $url = $this->available_items_url('/mod/reader/view_books.php', $params);

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
                $output .= html_writer::tag('option', "$displaylevel ($record->countbooks books)", array('value' => $record->level));
            }
            $record = null;

            $output .= html_writer::end_tag('select');
            $output .= html_writer::tag('div', '', array('id' => 'books'));
        }

        return $output;
    }

    /**
     * available_books
     *
     * @param xxx $publisher
     * @param xxx $level
     * @param xxx $action
     * @param xxx $from
     * @param xxx $where
     * @param xxx $sqlparams
     * @return xxx
     * @todo Finish documenting this function
     */
    public function available_books($publisher, $level, $action, $from, $where, $sqlparams) {
        global $DB;
        $output = '';

        $select = '*';
        $where .= " AND publisher = ? AND level = ?";
        array_push($sqlparams, $publisher, $level);

        if ($records = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY name", $sqlparams)) {
            $count = count($records);
        } else {
            $count = 0;
        }

        $output .= html_writer::tag('div', get_string('book', 'reader'), array('class' => 'selecteditemhdr'));

        if ($count==0) {
            $output .= 'Sorry, there are currently no books for you by '.$publisher;
            $output .= (($level=='' || $level=='--') ? '' : " ($level)");

        } else if ($count==1) {
            $record = reset($records); // just one book found
            $output .= html_writer::tag('div', $record->name, array('class' => 'selecteditemtxt'));
            $output .= html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'bookid', 'value' => $record->id));

            $output .= html_writer::start_tag('div', array('id' => 'bookid', 'style' => 'clear: both;'));
            $output .= $this->available_book($record, $action, $from, $where, $sqlparams);
            $output .= html_writer::end_tag('div');

        } else if ($count > 1) {

            $params = array('action'    => $action,
                            'mode'      => $this->mode,
                            'id'        => $this->reader->cm->id,
                            'publisher' => $publisher,
                            'level'     => $level,
                            'bookid'    => ''); // will be added by javascript
            $url = $this->available_items_url('/mod/reader/view_books.php', $params);

            $params = array('id' => 'id_bookid',
                            'name' => 'bookid',
                            'size' => min(10, count($records)),
                            'onchange' => "request($url, 'bookid')");
            $output .= html_writer::start_tag('select', $params);

            foreach ($records as $record) {
                $output .= html_writer::tag('option', "[RL-$record->difficulty] $record->name", array('value' => $record->id));
            }

            $output .= html_writer::end_tag('select');
            $output .= html_writer::tag('div', '', array('id' => 'bookid', 'style' => 'clear: both;'));
        }

        return $output;
    }

    /**
     * available_book
     *
     * @param xxx $bookid
     * @param xxx $action
     * @param xxx $from
     * @param xxx $where
     * @param xxx $sqlparams
     * @return xxx
     * @todo Finish documenting this function
     */
    public function available_book($book, $action, $from, $where, $sqlparams) {
        global $DB;
        $output = '';

        $select = 'id, publisher, level, name, words';
        $where .= " AND id = ?";
        array_push($sqlparams, (is_int($book) ? $book : $book->id));

        if ($record = $DB->get_record_sql("SELECT $select FROM $from WHERE $where", $sqlparams)) {
            $params = array('type' => 'hidden', 'name' => 'bookid', 'value' => $record->id);
            $output .= html_writer::empty_tag('input', $params);
            $output .= "$record->name (".number_format($record->words)." words)";
        }

        return $output;
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

        global $CFG;
        $src = $CFG->wwwroot.'/mod/reader/pix/ajax-loader.gif';

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

        $js .= "    if (req) {\n";
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

    /**
     * reader_get_level_data
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
                'userid'        => $userid,
                'readerid'      => $this->id,
                'startlevel'    => 0,
                'currentlevel'  => 0,
                'nopromote'     => 0,
                'promotionstop' => $this->promotionstop,
                'goal'          => 0,
                'time'          => time(),
            );
            if (! $level->id = $DB->insert_record('reader_levels', $level)) {
                // oops record could not be added - shouldn't happen !!
            }
        }

        $select = 'ra.*, rb.difficulty, rb.id AS bookid';
        $from   = '{reader_attempts} ra INNER JOIN {reader_books} rb ON ra.bookid = rb.id';
        $where  = 'ra.userid= ? AND ra.reader= ? AND ra.timefinish > ?';
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
                        if (strtolower($attempt->passed)=='true') {
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

        // if this is the highest allowed level, then enable the "nopromote" switch
        if ($level->promotionstop > 0 && $level->promotionstop <= $level->currentlevel) {
            $DB->set_field('reader_levels', 'nopromote', 1, array('readerid' => $this->id, 'userid' => $USER->id));
            $level->nopromote = 1;
        }

        if ($level->nopromote==1) {
            $count['this'] = 1;
        }

        // promote this student, if they have done enough quizzes at this level
        if ($count['this'] >= $this->nextlevel) {
            $level->currentlevel += 1;
            $level->time = time();
            $DB->update_record('reader_levels', $level);

            $count['this'] = 0;
            $count['prev'] = 0;
            $count['next'] = 0;

            echo '<script type="text/javascript">'."\n";
            echo '//<![CDATA['."\n";
            echo 'alert("'.addslashes_js(get_string('youhavebeenpromoted', 'reader'. $level->currentlevel)).'");'."\n";
            echo '//]]>'."\n";
            echo '</script>';
        }

        // prepare level data
        $leveldata = array(
            'promotiondate' => $level->time,
            'currentlevel'  => $level->currentlevel,                      // current level of this user
            'onprevlevel'   => $this->quizpreviouslevel - $count['prev'], // number of quizzes allowed at previous level
            'onthislevel'   => $this->nextlevel         - $count['this'], // number of quizzes allowed at current level
            'onnextlevel'   => $this->quiznextlevel     - $count['next']  // number of quizzes allowed at next level
        );
        if ($level->currentlevel==0 || $count['prev'] == -1) {
            $leveldata['onprevlevel'] = -1;
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
            }
            if ($this->reader->can_managebooks()) {
                $tab = self::TAB_BOOKS;
                $url = new moodle_url('/mod/reader/admin/books.php', array('id' => $cmid, 'tab' => $tab));
                $tabs[$tab] = new tabobject($tab, $url, get_string('books', 'reader'));
            }
            if ($this->reader->can_managequizzes()) {
                $tab = self::TAB_QUIZZES;
                $url = new moodle_url('/mod/reader/admin/quizzes.php', array('id' => $cmid, 'tab' => $tab));
                $tabs[$tab] = new tabobject($tab, $url, get_string('quizzes', 'reader'));
            }
            if ($this->reader->can_manageusers()) {
                $tab = self::TAB_USERS;
                $url = new moodle_url('/mod/reader/admin/users.php', array('id' => $cmid, 'tab' => $tab));
                $tabs[$tab] = new tabobject($tab, $url, get_string('users', 'reader'));
            }
            if ($this->reader->can_managetools()) {
                $tab = self::TAB_TOOLS;
                $url = new moodle_url('/mod/reader/admin/tools.php', array('id' => $cmid, 'tab' => $tab));
                $tabs[$tab] = new tabobject($tab, $url, get_string('tools', 'reader'));
            }
            if ($this->reader->can_managetools()) {
                $tab = self::TAB_ADMINAREA;
                $url = new moodle_url('/mod/reader/admin.php', array('id' => $cmid, 'tab' => $tab, 'a' => 'admin'));
                $tabs[$tab] = new tabobject($tab, $url, get_string('adminarea', 'reader'));
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
}
