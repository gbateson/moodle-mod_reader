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
     * generate sql to select books that this user is currently allowed to attempt
     *
     * @param boolean $noquiz TRUE books that have no associated quiz, FALSE otherwise
     * @return array (string $from, string $where, array $params)
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
        $recordids  = 'SELECT ra.bookid '.
                      'FROM {reader_attempts} ra '.
                      'WHERE ra.userid = ? AND ra.deleted = ?';

        // "sametitle" values for books whose quizzes this user has already attempted
        $sametitles = 'SELECT DISTINCT rb.sametitle '.
                      'FROM {reader_attempts} ra LEFT JOIN {reader_books} rb ON ra.bookid = rb.id '.
                      'WHERE ra.userid = ? AND ra.deleted = ? AND rb.id IS NOT NULL AND rb.sametitle <> ?';

        $from       = '{reader_books}';
        $where      = "id NOT IN ($recordids) AND level <> ? AND (sametitle = ? OR sametitle NOT IN ($sametitles)) AND hidden = ?";
        $params     = array($userid, 0, '99', '', $userid, 0, '', 0);

        $levels = array();
        if (isset($_SESSION['SESSION']->reader_teacherview) && $_SESSION['SESSION']->reader_teacherview == 'teacherview') {
            // do nothing - this is a teacher
        } else if ($this->reader->levelcheck == 0) {
            // do nothing - level checking is disabled
        } else {
            // a student with level-checking enabled
            $leveldata = reader_get_level_data($this->reader, $userid);
            if ($leveldata['thislevel'] > 0 && $leveldata['currentlevel'] >= 0) {
                $levels[] = $leveldata['currentlevel'];
            }
            if ($leveldata['prevlevel'] > 0 && $leveldata['currentlevel'] >= 1) {
                $levels[] = ($leveldata['currentlevel'] - 1);
            }
            if ($leveldata['nextlevel'] > 0) {
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

        return array($from, $where, $params);
    }

    /**
     * available_items
     *
     * @param xxx $action
     * @return xxx
     * @todo Finish documenting this function
     */
    public function available_items($action='') {
        global $CFG;

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
        $noquiz = ($type==reader_downloader::BOOKS_WITHOUT_QUIZZES);
        list($from, $where, $params) = $this->available_sql($noquiz);

        $output = '';
        switch (true) {
            case ($publisher===null || $publisher=='' || optional_param('go', null, PARAM_CLEAN)):
                $output .= $this->request_js();
                $output .= html_writer::start_tag('div', array('id' => 'publishers'));
                $output .= $this->available_publishers($action, $from, $where, $params, $type, $publisher, $level, $bookid);
                $output .= html_writer::end_tag('div');
                break;

            case ($level===null || $level==''):
                $output .= html_writer::start_tag('div', array('id' => 'levels'));
                $output .= $this->available_levels($action, $from, $where, $params, $type, $publisher, $level, $bookid);
                $output .= html_writer::end_tag('div');
                break;

            case ($bookid===null || $bookid==0) :
                $output .= html_writer::start_tag('div', array('id' => 'books'));
                $output .= $this->available_books($action, $from, $where, $params, $type, $publisher, $level, $bookid);
                $output .= html_writer::end_tag('div');
                break;

            default:
                $output .= $this->available_book($action, $from, $where, $params, $type, $publisher, $level, $bookid);
        }
        return $output;
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
     * @param xxx $publisher (optional, default="")
     * @param xxx $level (optional, default="")
     * @param xxx $bookid (optional, default=0)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function available_publishers($action, $sqlfrom, $sqlwhere, $sqlparams, $type, $publisher='', $level='', $bookid=0) {
        global $DB;
        $output = '';

        $select = 'publisher, COUNT(*) AS countbooks';
        if ($records = $DB->get_records_sql("SELECT $select FROM $sqlfrom WHERE $sqlwhere GROUP BY publisher ORDER BY publisher", $sqlparams)) {
            $count = count($records);
        } else {
            $count = 0;
        }

        // publisher title
        $output .= html_writer::tag('div', get_string('publisher', 'mod_reader'), array('class' => 'selecteditemhdr'));

        // publisher list
        if ($count==0) {
            if ($this->reader->can_managebooks()) {
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
            $url = $this->available_items_url('/mod/reader/view_books.php', $params);

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
            $output .= $this->available_levels($action, $sqlfrom, $sqlwhere, $sqlparams, $type, $publisher, $level, $bookid);
        }
        $output .= html_writer::end_tag('div');

        return $output;
    }

    /**
     * available_levels
     *
     * @param xxx $action
     * @param xxx $from
     * @param xxx $where
     * @param xxx $sqlparams
     * @param xxx $publisher
     * @param xxx $level (optional, default = "")
     * @param xxx $bookid (optional, default = 0)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function available_levels($action, $sqlfrom, $sqlwhere, $sqlparams, $type, $publisher, $level='', $bookid=0) {
        global $DB;
        $output = '';

        $select = "level, COUNT(*) AS countbooks, ROUND(SUM(difficulty) / COUNT(*), 0) AS average_difficulty";
        $where  = $sqlwhere.' AND publisher = ?';
        $params = array_merge($sqlparams, array($publisher));

        if ($records = $DB->get_records_sql("SELECT $select FROM $sqlfrom WHERE $where GROUP BY level ORDER BY average_difficulty", $params)) {
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
            $output .= $this->available_books($action, $sqlfrom, $sqlwhere, $sqlparams, $type, $publisher, $level, $bookid);
        }
        $output .= html_writer::end_tag('div');

        return $output;
    }

    /**
     * available_books
     *
     * @param xxx $action
     * @param xxx $from
     * @param xxx $where
     * @param xxx $sqlparams
     * @param xxx $publisher
     * @param xxx $level (optional, default = 0)
     * @return xxx
     * @todo Finish documenting this function
     */
    public function available_books($action, $sqlfrom, $sqlwhere, $sqlparams, $type, $publisher, $level, $bookid=0) {
        global $DB;
        $output = '';

        $select = '*';
        $where  = $sqlwhere.' AND publisher = ? AND level = ?';
        $params = array_merge($sqlparams, array($publisher, $level));

        if ($records = $DB->get_records_sql("SELECT $select FROM $sqlfrom WHERE $where ORDER BY name", $params)) {
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
            $url = $this->available_items_url('/mod/reader/view_books.php', $params);

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
            $output .= $this->available_book($action, $sqlfrom, $sqlwhere, $sqlparams, $type, $publisher, $level, $bookid);
        }
        $output .= html_writer::end_tag('div');

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
    public function available_book($action, $sqlfrom, $sqlwhere, $sqlparams, $type, $publisher, $level, $bookid) {
        global $DB;
        $output = '';

        $select = 'id, publisher, level, name, words';
        $where  = $sqlwhere.' AND publisher = ? AND level = ? AND id = ?';
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
        $src = $PAGE->theme->pix_url('i/ajaxloader', 'core')->out();

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
                'stoplevel' => $this->stoplevel,
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
        if ($level->stoplevel > 0 && $level->stoplevel <= $level->currentlevel) {
            $DB->set_field('reader_levels', 'nopromote', 1, array('readerid' => $this->id, 'userid' => $USER->id));
            $level->nopromote = 1;
        }

        if ($level->nopromote==1) {
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
            'currentlevel'  => $level->currentlevel,                      // current level of this user
            'prevlevel'   => $this->prevlevel - $count['prev'], // number of quizzes allowed at previous level
            'thislevel'   => $this->thislevel         - $count['this'], // number of quizzes allowed at current level
            'nextlevel'   => $this->nextlevel     - $count['next']  // number of quizzes allowed at next level
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
                $tabs[$tab] = new tabobject($tab, $url, get_string('books', 'mod_reader'));
            }
            if ($this->reader->can_managequizzes()) {
                $tab = self::TAB_QUIZZES;
                $url = new moodle_url('/mod/reader/admin/quizzes.php', array('id' => $cmid, 'tab' => $tab));
                $tabs[$tab] = new tabobject($tab, $url, get_string('quizzes', 'mod_reader'));
            }
            if ($this->reader->can_manageusers()) {
                $tab = self::TAB_USERS;
                $url = new moodle_url('/mod/reader/admin/users.php', array('id' => $cmid, 'tab' => $tab));
                $tabs[$tab] = new tabobject($tab, $url, get_string('users', 'mod_reader'));
            }
            if ($this->reader->can_managetools()) {
                $tab = self::TAB_TOOLS;
                $url = new moodle_url('/mod/reader/admin/tools.php', array('id' => $cmid, 'tab' => $tab));
                $tabs[$tab] = new tabobject($tab, $url, get_string('tools', 'mod_reader'));
            }
            if ($this->reader->can_managetools()) {
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
}
