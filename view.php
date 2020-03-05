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
 * mod/reader/view.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once('../../config.php');
require_once($CFG->dirroot.'/mod/reader/lib.php');
require_once($CFG->dirroot.'/mod/reader/locallib.php');
require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/question/editlib.php');

// get main mReader setting
$mreadersiteid = get_config('mod_reader', 'mreadersiteid');

$id        = optional_param('id', 0, PARAM_INT); // course module id
$r         = optional_param('r',  0, PARAM_INT); // reader id
$v         = optional_param('v', NULL, PARAM_CLEAN);
$publisher = optional_param('publisher', NULL, PARAM_CLEAN);
$level     = optional_param('level', NULL, PARAM_CLEAN);
$likebook  = optional_param('likebook', NULL, PARAM_CLEAN);

if ($id) {
    $cm = get_coursemodule_from_id('reader', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $reader = $DB->get_record('reader', array('id' => $cm->instance), '*', MUST_EXIST);
    $r = $reader->id;
} else {
    $reader = $DB->get_record('reader', array('id' => $r), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('reader', $reader->id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $id = $cm->id;
}

// Initialize $PAGE, compute blocks
$PAGE->set_url('/mod/reader/view.php', array('id' => $cm->id));

require_login($course->id, true, $cm);
reader_add_to_log($course->id, 'reader', 'view', 'view.php?id='.$cm->id, $reader->id, $cm->id);

$contextmodule = reader_get_context(CONTEXT_MODULE, $cm->id);
$timenow = time();

if (isset($SESSION->reader_lasttime) && $SESSION->reader_lasttime < ($timenow - 300)) {
    $unset = true;
} else if (isset($SESSION->reader_page) && $SESSION->reader_page == 'view') {
    $unset = true;
} else {
    $unset = false;
}
if ($unset) {
    unset ($SESSION->reader_page);
    unset ($SESSION->reader_lasttime);
    unset ($SESSION->reader_lastuser);
    unset ($SESSION->reader_lastuserfrom);
}

$title = $course->shortname . ': ' . format_string($reader->name);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

// create full object to represent this reader actvity
$reader = mod_reader::create($reader, $cm, $course);

// create renderer
$plugin = 'mod_reader';
$output = $PAGE->get_renderer($plugin);
$output->init($reader);

echo $output->header();

// preferred time and date format for this page
$timeformat = 'h:i A';  // 1:45 PM
$dateformat = 'jS M Y'; // 2nd Jun 2013

//Check access restrictions (permissions, IP, time open/close)
if ($reader->available==false) {
    echo $output->notavailable($plugin);
    echo $output->footer();
    exit;
}

if ($reader->can_viewreports()) {
    echo $output->tabs();
}

$url = new moodle_url('/mod/reader/js/ajax.js');
echo html_writer::tag('script', '', array('type' => 'text/javascript', 'src' => $url));

$alreadyansweredbooksid = array();

$leveldata = reader_get_level_data($reader);
if ($reader->levelcheck==0) {
    $promotiondate = 0;
} else {
    $promotiondate = $leveldata['promotiondate'];
}

echo $output->box_start('generalbox');

$plugin = $plugin;
$table = new html_table();
$table->attributes['class'] = 'generaltable AttemptsTable';

$table->head = array();
$table->head[] = get_string('date');
$table->head[] = get_string('booktitle',       $plugin);
$table->head[] = get_string('level',           $plugin);
$table->head[] = get_string('difficultyshort', $plugin); // RL
$table->head[] = get_string('status');
$table->align = array('left', 'left', 'left', 'center', 'center');

if ($reader->showpercentgrades) {
    $table->head[] = get_string('grade');
    $table->align[] = 'center';
}

switch ($reader->wordsorpoints) {
    case 2:
        $table->head[] = get_string('words', $plugin);
        $table->head[] = get_string('points', $plugin);
        $table->head[] = get_string('totalpoints', $plugin);
        array_push($table->align, 'center', 'center', 'center');
        break;
    case 1:
        $table->head[] = get_string('points', $plugin);
        $table->head[] = get_string('totalpoints', $plugin);
        array_push($table->align, 'center', 'center');
        break;
    case 0:
    default:
        $table->head[] = get_string('words', $plugin);
        $table->head[] = get_string('totalwords', $plugin);
        array_push($table->align, 'center', 'center');
        $reader->wordsorpoints = 0; // force default
        break;
}

$totalpoints         = 0;
$correctpoints       = 0;
$totalwords          = 0;
$totalwordscount     = 0;
$totalwordsall       = 0;
$totalwordscountall  = 0;

$bookcovers = '';

$bookcoversinprevterm = '';

if ($reader->bookcovers == 1) {
    $select = 'ra.*, rb.name AS bookname, rb.image AS bookimage';
    $from   = '{reader_attempts} ra LEFT JOIN {reader_books} rb ON ra.bookid = rb.id';
    $where  = 'ra.userid = ? AND ra.deleted = ? AND ra.timefinish <= ?';
    $params = array($USER->id, 0, $reader->ignoredate);
    if ($attempts = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY ra.timefinish", $params)) {
        foreach ($attempts as $attempt) {
            if (empty($attempt->bookimage)) {
                continue; // shouldn't happen !!
            }
            if ($attempt->passed) {
                if ($CFG->slasharguments) {
                    $src = new moodle_url('/mod/reader/images.php/reader/images/'.$attempt->bookimage);
                } else {
                    $params = array('file' => '/reader/images/'.$attempt->bookimage);
                    $src = new moodle_url('/mod/reader/images.php', $params);
                }
                $bookcoversinprevterm .= html_writer::empty_tag('img', array('src' => $src, 'border' => 0, 'alt' => $attempt->bookname, 'height' => 150, 'width' => 100));
            }
        }
    }
}

$select = 'ra.id, ra.userid, ra.bookid, ra.quizid, ra.passed, ra.credit, ra.cheated, ra.deleted, rb.words';
$from   = '{reader_attempts} ra LEFT JOIN {reader_books} rb ON ra.bookid = rb.id';
$where  = 'ra.userid = ? AND ra.deleted = ? AND ra.cheated = ? AND (ra.credit = ? OR ra.passed = ?)';
$params = array($USER->id, 0, 0, 1, 1);
if ($studentattempts = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params)) {
    foreach ($studentattempts as $studentattempt) {
        $totalwordsall += $studentattempt->words;
        $totalwordscountall++;
    }
}
unset($studentattempts);

$bookcoversinthisterm = '';
$lastattemptdate = 0;

list($attempts, $summaryattempts) = reader_get_student_attempts($USER->id, $reader);
if (count($attempts)) {

    foreach ($attempts as $attempt) {

        if ($promotiondate) {
            if ($lastattemptdate==0) { // first attempt
                if ($promotiondate < $attempt['timefinish']) {
                    reader_view_promotiondate($table, $leveldata, $promotiondate, $timeformat, $dateformat);
                }
            } else { // not the first attempt
                if ($promotiondate >= $lastattemptdate && $promotiondate < $attempt['timefinish']) {
                    reader_view_promotiondate($table, $leveldata, $promotiondate, $timeformat, $dateformat);
                }
            }
        }

        $lastattemptdate = $attempt['timefinish'];
        $alreadyansweredbooksid[] = $attempt['quizid'];

        if ($reader->bookcovers == 1 && $attempt['passed']) {
            if ($CFG->slasharguments) {
                $src = new moodle_url('/mod/reader/images.php/reader/images/'.$attempt['image']);
            } else {
                $params = array('file' => '/reader/images/'.$attempt['image']);
                $src = new moodle_url('/mod/reader/images.php', $params);
            }
            $params = array('src' => $src, 'border' => 0, 'alt' => $attempt['booktitle'], 'height' => 150, 'width' => 100);
            $bookcoversinthisterm .= html_writer::empty_tag('img', $params).' ';
        }

        if ($attempt['deleted'] || $attempt['cheated'] || ($attempt['credit']==0 && $attempt['passed']==0)) {
            $attempt['bookwords'] = '';
        } else {
            $totalwordscount++;
            $totalwords += $attempt['words'];
            $attempt['bookwords'] = number_format($attempt['words']);
        }

        $cells = array(
            new html_table_cell(date($dateformat, $attempt['timefinish'])),
            new html_table_cell($attempt['booktitle']),
            new html_table_cell($attempt['booklevel']),
            new html_table_cell($attempt['bookdiff']),
            new html_table_cell($attempt['statustext']),
        );
        if ($reader->showpercentgrades == 1) {
            $cells[] = new html_table_cell($attempt['bookpercent']);
        }
        switch ($reader->wordsorpoints) {
            case 2: $cells[] = new html_table_cell($attempt['bookwords']);
                    $cells[] = new html_table_cell($attempt['bookpoints']);
                    $cells[] = new html_table_cell($attempt['totalpoints']);
                    break;
            case 1: $cells[] = new html_table_cell($attempt['bookpoints']);
                    $cells[] = new html_table_cell($attempt['totalpoints']);
                    break;
            case 0: $cells[] = new html_table_cell($attempt['bookwords']);
                    $cells[] = new html_table_cell(number_format($totalwords));
                    break;
        }
        $table->data[] = new html_table_row($cells);
    }

    // check if student was promoted after most recent attempt
    if ($promotiondate && $promotiondate >= $lastattemptdate) {
        reader_view_promotiondate($table, $leveldata, $promotiondate, $timeformat, $dateformat);
    }

    // add row showing total words read so far
    reader_view_readingtotals($table, $totalwords, $totalwordsall);
}

if ($bookcoversinprevterm) {
    // display book covers from previous term
    echo $output->heading(get_string('booksreadinpreviousterms', $plugin), 2, array('class' => 'BooksRead'));
    echo html_writer::tag('p', $bookcoversinprevterm);

    // detect incorrect quizzes from previous term
    $select = 'ra.id AS attemptid, ra.quizid AS quizid, ra.timefinish, rb.name AS bookname';
    $from   = '{reader_attempts} ra LEFT JOIN {reader_books} rb ON ra.bookid = rb.id';
    $where  = 'ra.userid = ? AND ra.deleted = ? AND cheated = ? AND ra.credit = ? AND ra.passed = ? AND ra.timefinish <= ?';
    $params = array(0, 0, $USER->id, 0, 0, 0, 0, $reader->ignoredate);
    if ($attempts = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY timefinish", $params)) {
        $text = get_string('incorrectbooksreadinpreviousterms', $plugin);
        $onclick = "var obj = document.getElementById('readerfailedbooklist');".
                   "if (obj) {".
                       "if (obj.style.display=='none') {".
                           "obj.style.display = '';".
                       "} else {".
                           "obj.style.display = 'none';".
                       "}".
                   "}";
        echo html_writer::tag('p', html_writer::tag('a', $text, array('onclick' => $onclick)));
        echo html_writer::start_tag('ul', array('id' => 'readerfailedbooklist', 'style' => 'display: none;'));
        foreach ($attempts as $attempt) {
            $timefinish =  userdate($attempt->timefinish, get_string('strftimedate'));
            echo html_writer::tag('li',$timefinish.': '.$attempt->bookname);
        }
        echo html_writer::end_tag('ul');
    }
}

if ($bookcoversinprevterm && $bookcoversinthisterm) {
    echo html_writer::empty_tag('hr'); // separator
}

if ($bookcoversinthisterm) {
    // display book covers from this term
    echo $output->heading(get_string('booksreadthisterm', $plugin), 2, array('class' => 'BooksRead'));
    echo html_writer::tag('p', $bookcoversinthisterm);
}

$title = get_string('readingreportfor', $plugin, fullname($USER));
if (class_exists('\core\session\manager')) {
    $is_loggedinas = \core\session\manager::is_loggedinas();
} else {
    $is_loggedinas = session_is_loggedinas();
}
if ($is_loggedinas) {
    $params = array('id' => $id, 'sesskey' => sesskey());
    $params = array('href' => new moodle_url('/mod/reader/view_loginas.php', $params));
    $text = html_writer::tag('a', get_string('returntoreports', $plugin), $params);
    $title .= html_writer::tag('div', $text, array('class' => 'returntoreports'));
}
$params = array('class' => 'ReadingReportTitle');
echo html_writer::tag('h2', $title, $params);

if (count($table->data)) {
    echo html_writer::table($table);
} else {
    $text = get_string('getstarted', $plugin);
    $text = format_text($text, FORMAT_MARKDOWN);
    echo html_writer::tag('div', $text, array('class' => 'getstarted'));
}

// show progress bar, if necessary
if ($reader->showprogressbar) {
    echo $output->progressbar($totalwords);
}

// show promotion criteria nad reading restrictions, if necessary
if ($reader->levelcheck) {

    $table = new html_table();
    $table->attributes['class'] = 'generaltable PromotionTable';

    $table->data[] = new html_table_row(array(
        new html_table_cell(get_string('yourcurrentlevel', $plugin, $leveldata['currentlevel'])),
        new html_table_cell(reader_view_blockgraph($reader, $leveldata, $dateformat))
    ));

    // stretch blockgraph cell across two rows and align center
    $table->data[0]->cells[1]->rowspan = 2;

    // make sure we get the required row classs in Moodle >= 2.9
    if (property_exists($table, 'caption')) {
        if (empty($table->rowclasses)) {
            $table->rowclasses = array();
        }
        $table->rowclasses[0] = 'r0';
    }

    $list = array();

    if ($leveldata['allowpromotion']) {

        // current level
        if ($leveldata['thislevel'] > 0) {
            $params = (object)array('count' => $leveldata['thislevel'],
                                    'level' => $leveldata['currentlevel']);
            $type = ($leveldata['thislevel'] == 1 ? 'single' : 'plural');
            $list[] = get_string('youmustpass'.$type, $plugin, $params);
        } else if ($leveldata['thislevel']==0) {
            $list[] = get_string('youcannottake', $plugin, $leveldata['currentlevel']);
        }

        // previous level
        if ($leveldata['prevlevel'] > 0) {
            $params = (object)array('count' => $leveldata['prevlevel'],
                                    'level' => $leveldata['currentlevel'] - 1);
            $type = ($leveldata['prevlevel'] == 1 ? 'single' : 'plural');
            $list[] = get_string('youcantake'.$type, $plugin, $params);
        } else if ($leveldata['prevlevel']==0) {
            $list[] = get_string('youcannottake', $plugin, $leveldata['currentlevel'] - 1);
        }

        // next level
        if ($leveldata['nextlevel'] > 0) {
            $params = (object)array('count' => $leveldata['nextlevel'],
                                    'level' => $leveldata['currentlevel'] + 1);
            $type = ($leveldata['nextlevel'] == 1 ? 'single' : 'plural');
            $list[] = get_string('youcantake'.$type, $plugin, $params);
        } else if ($leveldata['nextlevel']==0) {
            $list[] = get_string('youcannottake', $plugin, $leveldata['currentlevel'] + 1);
        }

    } else if ($leveldata['currentlevel']==$leveldata['stoplevel']) {
        // stopped automatically - "stoplevel" reached
        $list[] = get_string('youcantakeunlimited', $plugin, $leveldata['currentlevel']);
        $list[] = get_string('pleaseaskyourinstructor', $plugin);

    } else {
        // stopped by teacher - "allowpromote" disabled
        $list[] = get_string('promotionnotallowed', $plugin);
        $list[] = get_string('youcantakeunlimited', $plugin, $leveldata['currentlevel']);
    }

    $table->data[] = new html_table_row(array(
        new html_table_cell(html_writer::alist($list))
    ));

    echo html_writer::table($table);
}

if ($reader->readonly) {
    echo $output->readonly($plugin);
    $showform = false;
} else {
    // get previously unfinished attempts by this user at quizzes in this Reader

    //$params = array('readerid' => $cm->instance, 'userid' => $USER->id, 'timefinish' => 0);
    //if ($attempts = $DB->get_records('reader_attempts', $params, 'timestart DESC')) {
    //    foreach ($attempts as $attempt) {
    //        $timefinish = ($attempt->timestart + $reader->timelimit);
    //        if ($timefinish < $timenow) {
    //            $attempt->timemodified = $timenow;
    //            $attempt->timefinish   = $timefinish;
    //            $attempt->passed       = 0; // timed out
    //            $DB->update_record('reader_attempts', $attempt);
    //            unset($attempts[$attempt->id]);
    //        }
    //    }
    //    $attempt = end($attempts); // most recent unfinished attempt
    //} else {
    //    $attempt = false; // i.e. there are no unfinished attempts
    //}

    $select = 'readerid = ? AND userid = ? AND deleted = ? AND '.
              '(timefinish = ? OR state IS NULL OR state = ? OR state = ?)';
    $params = array($cm->instance, $USER->id, 0, 0, '', 'inprogress');
    if ($attempts = $DB->get_records_select('reader_attempts', $select, $params, 'timestart DESC')) {
        foreach ($attempts as $attempt) {

            if (empty($attempt->state)) {
                $state = 'inprogress';
            } else {
                $state = $attempt->state;
            } 
            if (empty($attempt->timefinish)) {
                $timefinish = 0;
            } else {
                $timefinish = $attempt->timefinish;
            }

            $deadline = ($attempt->timestart + $reader->timelimit);

            if ($state == 'inprogress') {
                if ($deadline < $timenow) {
                    $timefinish = $deadline;
                    $state = 'abandoned';
                } else {                
                    $timefinish = 0;
                }
            } else {
                if ($timefinish == 0) {
                    $timefinish = min($deadline, $timenow);
                }
            }

            $update = false;
            if ($state != $attempt->state) {
                $update = true;
                $attempt->state = $state;
            }
            if ($timefinish != $attempt->timefinish) {
                $update = true;
                $attempt->timefinish = $timefinish;
            }
            if ($update) {
                $attempt->timemodified = $timenow; 
                $DB->update_record('reader_attempts', $attempt);
            }
            if ($state == 'inprogress') {
                $attempts[$attempt->id] = $attempt;
            } else {
                unset($attempts[$attempt->id]);
            }
        }
        while (count($attempts) > 1) {
            $attempt = array_pop($attempts);
            $attempt->state = 'abandoned';
            $attempt->timefinished = $timenow;
            $attempt->timemodified = $timenow;
            $DB->update_record('reader_attempts', $attempt);
        }
        $attempt = end($attempts); // most recent unfinished attempt
    } else {
        $attempt = false; // i.e. there are no unfinished attempts
    }

    $title = '';
    $msg = '';
    if ($attempt) {
        $title = get_string('quizattemptinprogress', $plugin);
        $name = $DB->get_field('reader_books', 'name', array('id' => $attempt->bookid));
        if ($mreadersiteid) {
            $msg = $reader->mreader_attempt_url($attempt->id);
        } else if (empty($SESSION->reader_lastattemptpage)) {
            $params = array('attempt' => $attempt->id, 'page' => 1);
            $msg = new moodle_url('/mod/reader/quiz/attempt.php', $params, 'q0');
        } else {
            $msg = new moodle_url('/mod/reader/quiz/attempt.php?'.$SESSION->reader_lastattemptpage);
        }
        $msg = html_writer::tag('a', $name, array('href' => $msg));
        $msg = html_writer::tag('li', $msg);
        $msg = html_writer::tag('ul', $msg);
        $msg = get_string('completequizattempt', $plugin, $name).$msg;
    } else if ($delay = $reader->get_delay()) {
        $title = get_string('delayineffect', $plugin);
        $msg = userdate($timenow + $delay);
        $msg = get_string('youcantakeaquizafter', $plugin, $msg);
    }

    if ($msg) {
        echo html_writer::tag('h2',  $title, array('class' => 'ReadingReportTitle'));
        echo html_writer::tag('div', $msg,   array('class' => 'readermessage'));
        $showform = false;
    } else {
        $showform = true;
    }

    echo $output->rates();
}


$select = 'readerid = ? AND timefinish = ? OR timefinish > ?';
$params = array($cm->instance, 0, $timenow);
if ($messages = $DB->get_records_select('reader_messages', $select, $params)) {

    $mygroupids = array();
    $groupings = groups_get_user_groups($course->id, $USER->id);
    foreach ($groupings as $groupingid => $groupids) {
        foreach ($groupids as $groupid) {
            $mygroupids[] = $groupid;
        }
    }

    foreach ($messages as $message) {
        $groupids = explode (',', $message->groupids);
        $groupids = array_filter($groupids);
        if (empty($groupids)) {
            $showmessage = true; // all groups
        } else {
            $showmessage = false;
            foreach ($groupids as $groupid) {
                if (in_array($groupid, $mygroupids)) {
                    $showmessage = true;
                }
            }
        }
        if ($showmessage) {
            echo $output->message($message);
        }
    }
}

//if (isset($SESSION->reader_changetostudentview)) {
//    if ($showform == false && $SESSION->reader_changetostudentview > 0) {
//        echo '<br />'.get_string('thisblockunavailable', $plugin).'<br />';
//        $showform == true;
//    }
//}

if ($showform && has_capability('mod/reader:viewbooks', $contextmodule)) {
    echo '<h3>'.get_string('searchforabook', $plugin).':</h3>';
    echo $output->search_form($USER->id, true, 'takequiz');

    echo '<h3>'.get_string('selectabook', $plugin).':</h3>';
    echo $output->books_menu($id, $reader, $USER->id, 'takequiz');

    $url = new moodle_url('/course/view.php', array('id' => $course->id));
    $btn = $output->single_button($url, get_string('returntocoursepage', $plugin), 'get');
    echo html_writer::tag('div', $btn, array('style' => 'clear: both; padding: 12px;'));
}

echo html_writer::tag('div', '', array('style'=>'clear:both;'));

echo $output->box_end();
echo $output->footer();

/**
 * reader_view_blockgraph
 *
 * @param xxx $reader
 * @param xxx $leveldata
 * @param xxx $dateformat
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_view_blockgraph($reader, $leveldata, $dateformat) {
    $output  = '';

    // max attempts allowed at each difficulty level
    $prevmax = $reader->prevlevel;
    $thismax = $reader->thislevel;
    $nextmax = $reader->nextlevel;

    // num of attempts allowed at each difficulty level
    $prevallow = $leveldata['prevlevel'];
    $thisallow = $leveldata['thislevel'];
    $nextallow = $leveldata['nextlevel'];

    // num of attempts completed at each difficulty level
    $prevdone = ($prevmax - $prevallow);
    $thisdone = ($thismax - $thisallow);
    $nextdone = ($nextmax - $nextallow);

    // determine maximum number of squares required
    $i_max = max($prevmax, $thismax, $nextmax);
    if ($i_max) {

        // determine maximum height of a square
        if ($i_max < 8) {
            $style = '';
            $height = 0;
        } else {
            $height = max(2, round(132/$i_max));
            $style = 'height: '.$height.'px;';
        }

        // cache html for each type of square
        $previmg = html_writer::tag('div', get_string('readinglevelshort', 'mod_reader', $leveldata['currentlevel'] - 1), array('class' => 'squarebase squarelevel'));
        $thisimg = html_writer::tag('div', get_string('readinglevelshort', 'mod_reader', $leveldata['currentlevel'] + 0), array('class' => 'squarebase squarelevel'));
        $nextimg = html_writer::tag('div', get_string('readinglevelshort', 'mod_reader', $leveldata['currentlevel'] + 1), array('class' => 'squarebase squarelevel'));
        $spacer  = html_writer::tag('div', '', array('class' => 'squarebase squarespacer', 'style' => $style));
        $done    = html_writer::tag('div', '', array('class' => 'squarebase squaredone',   'style' => $style));
        $notyet  = html_writer::tag('div', '', array('class' => 'squarebase squarenotyet', 'style' => $style));

        // heading
        $text = date($dateformat, $leveldata['promotiondate']);
        if ($leveldata['currentlevel']==0) {
            $text = get_string('booksreadsincedate', 'mod_reader', $text);
        } else {
            $text = get_string('booksreadsincepromotion', 'mod_reader', $text);
        }
        $output .= html_writer::tag('div', $text, array('class' => 'PromotionGraphTitle'));

        // start graph DIV
        $params = array('class' => 'PromotionGraphSquares');
        if ($height) {
            $params['style'] = 'line-height: '.($height + 1).'px;';
        }
        $output .= html_writer::start_tag('div', $params);

        // generate HTML for each row of squares
        for ($i = $i_max; $i > 0; $i--) {

            // previous level
            if ($prevallow < 0) {
                // this level is disabled - do nothing
            } else if ($i > $prevdone && $i <= $prevmax) {
                $output .= $notyet;
            } else if ($i > $prevdone ) {
                $output .= $spacer;
            } else {
                $output .= $done;
            }

            // current level
            if ($thisallow < 0) {
                // this level is disabled - do nothing
            } else if ($i > $thisdone && $i <= $thismax) {
                $output .= $notyet;
            } else if ($i > $thisdone ) {
                $output .= $spacer;
            } else {
                $output .= $done;
            }

            // next level
            if ($nextallow < 0) {
                // this level is disabled - do nothing
            } else if ($i > $nextdone && $i <= $nextmax) {
                $output .= $notyet;
            } else if ($i > $nextdone ) {
                $output .= $spacer;
            } else {
                $output .= $done;
            }

            $output .= html_writer::empty_tag('br');
        }

        // append row of bar titles
        $output .= ($prevallow < 0 ? '' : $previmg);
        $output .= ($thisallow < 0 ? '' : $thisimg);
        $output .= ($nextallow < 0 ? '' : $nextimg);

        // end graph DIV
        $output .= html_writer::end_tag('div');
    }

    return $output;
}

/**
 * reader_view_promotiondate
 *
 * @param xxx $table (passed by reference)
 * @param xxx $leveldata
 * @param xxx $promotiondate
 * @param xxx $timeformat
 * @param xxx $dateformat
 * @todo Finish documenting this function
 */
function reader_view_promotiondate(&$table, $leveldata, $promotiondate, $timeformat, $dateformat) {
    // format the "You were promoted ..." message
    $params = (object)array(
        'level' => $leveldata['currentlevel'],
        'time'  => date($timeformat, $promotiondate),
        'date'  => date($dateformat, $promotiondate)
    );
    $cell = new html_table_cell(get_string('youwerepromoted', 'mod_reader', $params));

    // convert cell to single header header cell spanning all columns
    $cell->header = true;
    $cell->colspan = count($table->head);

    // add table row containing this single cell
    $table->data[] = new html_table_row(array($cell));
}

/**
 * reader_view_readingtotals
 *
 * @param xxx $table (passed by reference)
 * @param xxx $totalwords
 * @param xxx $totalwordsall
 * @todo Finish documenting this function
 */
function reader_view_readingtotals(&$table, $totalwordsthisterm, $totalwordsallterms) {

    if ($totalwordsallterms > $totalwordsthisterm) {

        $names = array('totalwordsthisterm' => $totalwordsthisterm,
                       'totalwordsallterms' => $totalwordsallterms);

        foreach ($names as $name => $total) {

            $name = get_string($name, 'mod_reader');
            $name = new html_table_cell($name);
            $name->header = true;
            $name->colspan = (count($table->head) - 1);
            $name->style = 'text-align: right;';

            $total = number_format($total);
            $total = new html_table_cell($total);
            $total->style = 'text-align: center;';

            $table->data[] = new html_table_row(array($name, $total));
        }
    }
}
