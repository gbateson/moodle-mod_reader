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

$id        = optional_param('id', 0, PARAM_INT);
$a         = optional_param('a', NULL, PARAM_CLEAN);
$v         = optional_param('v', NULL, PARAM_CLEAN);
$publisher = optional_param('publisher', NULL, PARAM_CLEAN);
$level     = optional_param('level', NULL, PARAM_CLEAN);
$series    = optional_param('series', NULL, PARAM_CLEAN);
$likebook  = optional_param('likebook', NULL, PARAM_CLEAN);

if ($id) {
    $cm = get_coursemodule_from_id('reader', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
    $reader = $DB->get_record('reader', array('id'=>$cm->instance), '*', MUST_EXIST);
    //$a = $reader->id;
} else {
    $reader = $DB->get_record('reader', array('id'=>$a), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('reader', $reader->id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
    $id = $cm->id;
}

require_login($course->id, true, $cm);

reader_add_to_log($course->id, 'reader', 'view personal page', "view.php?id=$id", "$cm->instance");

$contextmodule = reader_get_context(CONTEXT_MODULE, $cm->id);
$timenow = time();

if (isset($_SESSION['SESSION']->reader_lasttime) && $_SESSION['SESSION']->reader_lasttime < ($timenow - 300)) {
    $unset = true;
} else if (isset($_SESSION['SESSION']->reader_page) && $_SESSION['SESSION']->reader_page == 'view') {
    $unset = true;
} else {
    $unset = false;
}
if ($unset) {
    unset ($_SESSION['SESSION']->reader_page);
    unset ($_SESSION['SESSION']->reader_lasttime);
    unset ($_SESSION['SESSION']->reader_lastuser);
    unset ($_SESSION['SESSION']->reader_lastuserfrom);
}

// Initialize $PAGE, compute blocks
$PAGE->set_url('/mod/reader/view.php', array('id' => $cm->id));

$title = $course->shortname . ': ' . format_string($reader->name);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

// create full object to represent this reader actvity
$reader = mod_reader::create($reader, $cm, $course);
$output = $PAGE->get_renderer('mod_reader');
$output->init($reader);

echo $output->header();

// preferred time and date format for this page
$timeformat = 'h:i A';  // 1:45 PM
$dateformat = 'jS M Y'; // 2nd Jun 2013

//Check access restrictions (permissions, IP, time open/close)
if (has_capability('mod/reader:viewreports', $contextmodule)) {
    echo $output->tabs();
    $msg = ''; // teacher can always view this page
} else if (! has_capability('mod/reader:viewbooks', $contextmodule)) {
    $msg = get_string('nopermissions', 'error', get_string('reader:viewreports', 'mod_reader'));
} else if (! empty($reader->subnet) && ! address_in_subnet(getremoteaddr(), $reader->subnet)) {
    $msg = get_string('subneterror', 'quiz');
} else if (! empty($reader->timeopen) && $reader->timeopen > $timenow) {
    $msg = get_string('notopenyet', 'mod_reader', userdate($reader->timeopen));
} else if (! empty($reader->timeclose) && $reader->timeclose < $timenow) {
    $msg = get_string('alreadyclosed', 'mod_reader', userdate($reader->timeclose));
} else {
    $msg = ''; // reader is open and not closed, and user can view books
}

if ($msg) {
    $url = new moodle_url('/course/view.php', array('id' => $course->id));
    $msg .= html_writer::tag('p', $output->continue_button($url));
    echo $output->box($msg, 'generalbox', 'notice');
    echo $output->footer();
    reader_change_to_teacherview();
    exit;
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

$table = new html_table();

if ($reader->pointreport == 1) {
    if ($reader->reportwordspoints != 1) {  //1 - only points
        $table->head = array('Date', 'Book Title', 'Level', 'Words', 'Percent Correct', 'Total Points');
        $table->align = array('left', 'left', 'left', 'center', 'center', 'center');
    } else {
        $table->head = array('Date', 'Book Title', 'Level', 'Percent Correct', 'Total Points');
        $table->align = array('left', 'left', 'left', 'center', 'center');
    }
} else {
    if ($reader->reportwordspoints == 2) {  //points and words
        $table->head = array('Date', 'Book Title', 'Level', 'Status', 'Words', 'Points This Book', 'Total Points');
        $table->align = array('left', 'left', 'left', 'center', 'center', 'center', 'center');
    } else if ($reader->reportwordspoints == 1) {  //points only
        $table->head = array('Date', 'Book Title', 'Level', 'Status', 'Points This Book', 'Total Points');
        $table->align = array('left', 'left', 'left', 'center', 'center', 'center');
    } else if ($reader->reportwordspoints == 0) {  //words only
        $table->head = array('Date', 'Book Title', 'Level', 'Status', 'Words', 'Total words');
        $table->align = array('left', 'left', 'left', 'center', 'center', 'center');
    }
}

$table->width = '800';

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
            if ($attempt->passed == 'true' || $attempt->passed == 'TRUE') {
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

$bookcoversinthisterm = '';
$lastattemptdate = 0;

list($attempts, $summaryattempts) = reader_get_student_attempts($USER->id, $reader);
if (count($attempts)) {

    foreach ($attempts as $attempt) {

        if ($promotiondate) {
            if ($lastattemptdate==0) { // first attempt
                if ($attempt['timefinish'] > $promotiondate) {
                    reader_view_promotiondate($table, $leveldata, $promotiondate, $timeformat, $dateformat);
                }
            } else { // not the first attempt
                if ($lastattemptdate < $promotiondate && $attempt['timefinish'] > $promotiondate) {
                    reader_view_promotiondate($table, $leveldata, $promotiondate, $timeformat, $dateformat);
                }
            }
        }
        $lastattemptdate = $attempt['timefinish'];

        $alreadyansweredbooksid[] = $attempt['quizid'];

        if ($reader->bookcovers == 1 && $attempt['status'] == 'correct') {
            if ($CFG->slasharguments) {
                $src = new moodle_url('/mod/reader/images.php/reader/images/'.$attempt['image']);
            } else {
                $params = array('file' => '/reader/images/'.$attempt['image']);
                $src = new moodle_url('/mod/reader/images.php', $params);
            }
            $params = array('src' => $src, 'border' => 0, 'alt' => $attempt['booktitle'], 'height' => 150, 'width' => 100);
            $bookcoversinthisterm .= html_writer::empty_tag('img', $params).' ';
        }

        if ($attempt['statustext'] == 'Passed' || $attempt['statustext'] == 'Credit'){
            $totalwords += $attempt['words'];
            $totalwordscount++;
            $showwords = $attempt['words'];
        } else {
            $showwords = '';
        }

        if ($reader->pointreport == 1) {
            // hide status or points
            if ($reader->reportwordspoints == 1) {
                // points
                $table->data[] = array(date($dateformat, $attempt['timefinish']),
                                            $attempt['booktitle'],
                                            $attempt['booklevel'].'[RL'.$attempt['bookdiff'].']',
                                            $attempt['bookpercent'],
                                            $attempt['totalpoints']);
            } else {
                // words
                $table->data[] = array(date($dateformat, $attempt['timefinish']),
                                            $attempt['booktitle'],
                                            $attempt['booklevel'].'[RL' .$attempt['bookdiff'].']',
                                            //$attempt['words'],
                                            $showwords,
                                            $attempt['bookpercent'],
                                            $attempt['totalpoints']);
            }
        } else {
            // show status or points
            if ($reader->reportwordspoints == 2) {  //points and words
                $table->data[] = array(date($dateformat, $attempt['timefinish']),
                                            $attempt['booktitle'],
                                            $attempt['booklevel'].'[RL'.$attempt['bookdiff'].']',
                                            $attempt['statustext'],
                                            //$attempt['words'],
                                            $showwords,
                                            $attempt['bookpoints'],
                                            $attempt['totalpoints']);
            } else if ($reader->reportwordspoints == 1) {  //points only
                $table->data[] = array(date($dateformat, $attempt['timefinish']),
                                            $attempt['booktitle'],
                                            $attempt['booklevel'].'[RL'.$attempt['bookdiff'].']',
                                            $attempt['statustext'],
                                            $attempt['bookpoints'],
                                            $attempt['totalpoints']);
            } else if ($reader->reportwordspoints == 0) {  //words only
                $table->data[] = array(date($dateformat, $attempt['timefinish']),
                                            $attempt['booktitle'],
                                            $attempt['booklevel'].'[RL'.$attempt['bookdiff'].']',
                                            $attempt['statustext'],
                                            //$attempt['words'],
                                            $showwords,
                                            $totalwords);
            }
        }
    }
    if ($promotiondate && $attempt['timefinish'] < $promotiondate) {
        reader_view_promotiondate($table, $leveldata, $promotiondate, $timeformat, $dateformat);
    }
}

$select = 'ra.id, ra.userid, ra.bookid, ra.quizid, ra.preview, ra.deleted, ra.passed, rb.words';
$from   = '{reader_attempts} ra LEFT JOIN {reader_books} rb ON ra.bookid = rb.id';
$where  = 'ra.userid = ? AND ra.preview = ? AND ra.deleted = ? AND ra.passed = ?';
$params = array($USER->id, 0, 0, 'true');
if ($studentattempts = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params)) {
    foreach ($studentattempts as $studentattempt) {
        $totalwordsall += $studentattempt->words;
        $totalwordscountall++;
    }
}
unset($studentattempts);

if ($bookcoversinprevterm) {
    // display book covers from previous term
    echo $output->heading(get_string('booksreadinpreviousterms', 'mod_reader'), 2);
    echo html_writer::tag('p', $bookcoversinprevterm);

    // detect incorrect quizzes from previous term
    $select = 'ra.id AS attemptid, ra.quizid AS quizid, ra.timefinish, rb.name AS bookname';
    $from   = '{reader_attempts} ra LEFT JOIN {reader_books} rb ON ra.bookid = rb.id';
    $where  = 'ra.userid = ? AND ra.preview = ? AND ra.deleted = ? AND ra.passed <> ? AND ra.timefinish <= ?';
    $params = array(0, 0, $USER->id, 'true', $reader->ignoredate);
    if ($attempts = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY timefinish", $params)) {
        $text = get_string('incorrectbooksreadinpreviousterms', 'mod_reader');
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
    echo $output->heading(get_string('booksreadthisterm', 'mod_reader'), 2);
    echo html_writer::tag('p', $bookcoversinthisterm);
}

echo '<table width="100%"><tr><td>';
echo '<h2><span class="readingreporttitle">'.get_string('readingreportfor', 'mod_reader', fullname($USER))."</span></h2>";
if (isset($_SESSION['SESSION']->reader_changetostudentview) && $_SESSION['SESSION']->reader_changetostudentview > 0) {
    $params = array('a' => 'admin', 'id' => $id, 'act' => 'reports');
    if (isset($_SESSION['SESSION']->reader_changetostudentviewlink)) {
        // NOTE: "reader_changetostudentviewlink" is set in "admin.php" to something like this:
        // gid={$gid}&searchtext={$searchtext}&page={$page}&sort={$sort}&orderby={$orderby}
        parse_str($_SESSION['SESSION']->reader_changetostudentviewlink, $more_params);
        $params = array_merge($params, $more_params);
    }
    $url = new moodle_url('/mod/reader/admin.php', $params);
    echo '</td><td width="50%" align="right"><small><span style="text-align: right;">';
    echo '<a href="'.$url.'">'.get_string('returntostudentlist', 'mod_reader').'</a>';
    echo '</span></small>';
}
echo "</td></tr></table>";

if ($reader->levelcheck == 1) {
    echo reader_view_blockgraph($reader, $leveldata, $dateformat);
}

if (! empty($table->data)) {
    echo '<center>'.html_writer::table($table).'</center>';
} else {
    //echo '<center>'.get_string('nodata', 'mod_reader').'</center>';
}

if ($reader->wordsprogressbar) {
    echo '<table width="800" cellpadding="5" cellspacing="1" class="generaltable boxaligncenter"><tr>';
    echo '<th width="500" style="text-align:right;font-weight:lighter;">'.get_string('totalwords', 'mod_reader').': '.$totalwords.'</th>';
    echo '<th style="text-align:right;font-weight:lighter;">'.get_string('totalwordsall', 'mod_reader').": ".$totalwordsall.'</th>';
    echo '</tr></table>';

    echo '<table width="820" cellpadding="0" cellspacing="0" class="generaltable boxaligncenter"><tr><td align="center">';
    if ($progressimage = reader_get_goal_progress($totalwords, $reader)) {
        echo '<table width="820px"><tr><td align="center"><div style="position:relative;z-index:5;height:100px;width:850px;">'.$progressimage.'</div>';
        echo '</td></tr><tr><td>&nbsp;<b>&nbsp;'.get_string('in1000sofwords', 'mod_reader').'</b></td></tr></table>';
    }
    echo '</td></tr></table>';
}

//if ($reader->nextlevel == $leveldata['onthislevel']) {
//    $displaymore = "";
//} else {
//    $displaymore = " more ";
//}

echo '<h3>'.get_string('yourcurrentlevel', 'mod_reader').': '.$leveldata['currentlevel'].'</h3>';

$promoteinfo = $DB->get_record('reader_levels', array('userid' => $USER->id, 'readerid' => $reader->id));
if ($promoteinfo->nopromote == 1) {
    if ($promoteinfo->promotionstop == $leveldata['currentlevel']) {
        print_string('pleaseaskyourinstructor', 'reader');
    } else {
        print_string('yourteacherhasstopped', 'reader');
    }

    print_string('youcantakeasmanyquizzesasyouwant', 'reader', $leveldata['currentlevel']);

    if ($leveldata['onprevlevel'] <= 0) {
        $quizcount = 'no';
    } else {
        $quizcount = $leveldata['onprevlevel'];
    }
    if ($leveldata['onprevlevel'] == 1) { $quiztext = 'quiz'; } else { $quiztext = 'quizzes'; }
    print_string('youmayalsotake', 'reader', $quizcount);
    echo '{$quiztext} '.get_string('atlevel', 'mod_reader').' '.($leveldata['currentlevel'] - 1).' ';

} else if ($reader->levelcheck == 1) {

    if ($leveldata['onthislevel'] == 1) {
        print_string('youmusttakequiz', 'reader', $leveldata['onthislevel']);
    } else {
        print_string('youmusttakequizzes', 'reader', $leveldata['onthislevel']);
    }
    print_string('atlevelbeforebeingpromoted', 'reader', $leveldata['currentlevel']);

    if ($leveldata['onprevlevel'] <= 0) {
        $quizcount = 'no';
    } else {
        $quizcount = $leveldata['onprevlevel'];
    }
    if ($leveldata['onprevlevel'] == 1) { $quiztext = "quiz"; } else { $quiztext = "quizzes"; }

    if (($leveldata['currentlevel'] - 1) >= 0) {

        if ($leveldata['onprevlevel'] > 0 && $leveldata['onnextlevel'] <= 0) {
            $quiznextlevelso = 'but';
        } else {
            $quiznextlevelso = 'and';
        }
        print_string('youmayalsotake', 'reader', $quizcount);
        echo "{$quiztext} ".get_string('atlevel', 'mod_reader')." ".($leveldata['currentlevel'] - 1)." ";
    } else {
        print_string('youcantake', 'reader');
    }

    if ($leveldata['onnextlevel'] <= 0) {
        $quizcount = 'no';
    } else {
        $quizcount = $leveldata['onnextlevel'];
    }

    if (! isset($quiznextlevelso)) {
        $quiznextlevelso = "";
    }

    if ($leveldata['onnextlevel'] == 1) {
        $quiztext = ' quiz '; } else { $quiztext = ' quizzes ';
    }
    echo $quiznextlevelso.get_string('andnextmore', 'mod_reader', $quizcount).$quiztext.get_string('atlevel', 'mod_reader'). ' ' . ($leveldata['currentlevel'] + 1 .'.');
} else if ($reader->levelcheck == 0) {
    print_string('butyoumaytakequizzes', 'reader');
}

$showform = true;
if ($attempt = $DB->get_record('reader_attempts', array('reader' => $cm->instance, 'userid' => $USER->id, 'timefinish' => 0))) {
    if ($reader->timelimit < ($timenow - $attempt->timestart)) {
        $showform = true;
        $attempt->timemodified = $timenow;
        $attempt->timefinish   = $timenow;
        $attempt->passed       = 'false';
        $attempt->percentgrade = 0;
        $attempt->sumgrades    = '0';
        $attempt->bookrating   = 0;
        $DB->update_record('reader_attempts', $attempt);
    } else {
        $showform = false;
        $bookname = $DB->get_field('reader_books', 'name', array('quizid' => $attempt->quizid));
        print_string('pleasecompletequiz', 'reader', $bookname);
        if (empty($_SESSION['SESSION']->reader_lastattemptpage)) {
            $url = $CFG->wwwroot.'/mod/reader/quiz/attempt.php?attempt='.$attempt->id.'&page=1#q0';
        } else {
            $url = $CFG->wwwroot.'/mod/reader/quiz/attempt.php?'.$_SESSION['SESSION']->reader_lastattemptpage;
        }
        echo ' <a href="'.$url.'">'.get_string('complete', 'mod_reader').'</a>';
    }
} else if ($delay = $reader->get_delay()) { // && $_SESSION['SESSION']->reader_teacherview != "teacherview"
    if ($timenow < ($lastattemptdate + $delay)) {
        $showform = false;
        $msg = userdate($lastattemptdate + $delay);
        $msg = get_string('youcantakeaquizafter', 'mod_reader', $msg);
        $msg = html_writer::tag('span', $msg, array('class' => 'delayon'));
        echo html_writer::tag('div', $msg);
    }
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

    $started_list = false;
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


        if ($message->timemodified > ($timenow - ( 48 * 60 * 60))) {
            $bgcolor = 'bgcolor="#CCFFCC"';
        } else {
            $bgcolor  = '';
        }

        if ($showmessage) {
            if ($started_list==false) {
                $started_list = true; // only do this once
                echo '<h3>'.get_string('messagefromyourteacher', 'mod_reader').'</h3>';
            }
            echo '<table width="100%"><tr><td align="right"><table cellspacing="0" cellpadding="0" class="forumpost blogpost blog" '.$bgcolor.' width="90%">';
            echo '<tr><td align="left"><div style="margin-left: 10px;margin-right: 10px;">'."\n";
            echo format_text($message->messagetext, $message->messageformat);
            echo '<div style="text-align:right"><small>';
            $teacherdata = $DB->get_record('user', array('id' => $message->teacherid));
            echo "<a href=\"{$CFG->wwwroot}/user/view.php?id={$message->teacherid}&amp;course={$course->id}\">".fullname($teacherdata, true)."</a>";
            echo '</small></div>';
            echo '</div></td></tr></table></td></tr></table>'."\n\n";
        }
    }
}

if (isset($_SESSION['SESSION']->reader_changetostudentview)) {
    if ($showform == false && $_SESSION['SESSION']->reader_changetostudentview > 0) {
        echo '<br />'.get_string('thisblockunavailable', 'mod_reader').'<br />';
        $showform == true;
    }
}

if ($showform && has_capability('mod/reader:viewbooks', $contextmodule)) {
    echo '<h3>'.get_string('searchforthebookthatyouwant', 'mod_reader').':</h3>';
    echo reader_search_books($id, $reader, $USER->id, true, 'takequiz');

    echo '<h3>'.get_string('selectthebookthatyouwant', 'mod_reader').':</h3>';
    echo reader_available_books($id, $reader, $USER->id, 'takequiz');

    $url = new moodle_url('/course/view.php', array('id' => $course->id));
    $btn = $output->single_button($url, get_string('returntocoursepage', 'mod_reader'), 'get');
    echo html_writer::tag('div', $btn, array('style' => 'clear: both; padding: 12px;'));
}

echo html_writer::tag('div', '', array('style'=>'clear:both;'));

echo $output->box_end();
print ('<center><img src="img/credit.jpg" height="40px"></center>');
echo $output->footer();

reader_change_to_teacherview();

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

    // max attempts allowed at each difficulty level
    $prevmax = $reader->quizpreviouslevel;
    $thismax = $reader->nextlevel;
    $nextmax = $reader->quiznextlevel;

    // num of attempts allowed at each difficulty level
    $prevallow = $leveldata['onprevlevel'];
    $thisallow = $leveldata['onthislevel'];
    $nextallow = $leveldata['onnextlevel'];

    // num of attempts completed at each difficulty level
    $prevdone = $prevmax - $prevallow;
    $thisdone = $thismax - $thisallow;
    $nextdone = $nextmax - $nextallow;

    // images
    $previmg = html_writer::empty_tag('img', array('src'=>new moodle_url('/mod/reader/pix/progress/lm1.jpg'), 'border'=>0, 'alt'=>'lm1', 'height'=>16, 'width'=>28, 'style'=>'margin:0 4px 0 0'));
    $thisimg = html_writer::empty_tag('img', array('src'=>new moodle_url('/mod/reader/pix/progress/l.jpg'), 'border'=>0, 'alt'=>'l', 'height'=>16, 'width'=>28, 'style'=>'margin:0 4px 0 0'));
    $nextimg = html_writer::empty_tag('img', array('src'=>new moodle_url('/mod/reader/pix/progress/lp1.jpg'), 'border'=>0, 'alt'=>'lp1', 'height'=>16, 'width'=>28, 'style'=>'margin:0 4px 0 0'));
    $spacer  = html_writer::empty_tag('img', array('src'=>new moodle_url('/mod/reader/pix/progress/spacer.jpg'), 'border'=>0, 'alt'=>'space', 'height'=>26, 'width'=>28, 'style'=>'margin:0 4px 0 0'));
    $done    = html_writer::empty_tag('img', array('src'=>new moodle_url('/mod/reader/pix/progress/done.jpg'), 'border'=>0, 'alt'=>'done', 'height'=>26, 'width'=>28, 'style'=>'margin:0 4px 0 0'));
    $notyet  = html_writer::empty_tag('img', array('src'=>new moodle_url('/mod/reader/pix/progress/notyet.jpg'), 'border'=>0, 'alt'=>'notyet', 'height'=>26, 'width'=>28, 'style'=>'margin:0 4px 0 0'));

    // generate $output
    $output  = '';

    $i_max = max($prevmax, $thismax, $nextmax);
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

    if ($output) {
        // prepend heading
        $date   = date($dateformat, $leveldata['promotiondate']);
        $since  = ($leveldata['currentlevel']==0 ? 'sincedate' : 'sincepromotion');
        $params = array('class' => 'noverticalpadding', 'style' => 'margin: 6px auto; padding: 6px auto;');
        $output = html_writer::tag('h3', get_string('quizzespassedtable', 'mod_reader', $leveldata['currentlevel']), $params).
                  html_writer::tag('p', get_string($since, 'mod_reader', $date), $params).
                  $output;

        // append images as bar titles
        $output .= ($prevallow < 0 ? '' : $previmg);
        $output .= ($thisallow < 0 ? '' : $thisimg);
        $output .= ($nextallow < 0 ? '' : $nextimg);

        // put output in its own DIV floated to the right of the page
        $output = html_writer::tag('div', $output, array('style'=>'float: right; margin-right: 50px; text-align: center;'));
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
