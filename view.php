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
    if (! $cm = get_coursemodule_from_id('reader', $id)) {
        throw new reader_exception('Course Module ID was incorrect');
    }
    if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
        throw new reader_exception('Course is misconfigured');
    }
    if (! $reader = $DB->get_record('reader', array('id' => $cm->instance))) {
        throw new reader_exception('Course module is incorrect');
    }
} else {
    if (! $reader = $DB->get_record('reader', array('id' => $a))) {
        throw new reader_exception('Course module is incorrect');
    }
    if (! $course = $DB->get_record('course', array('id' => $reader->course))) {
        throw new reader_exception('Course is misconfigured');
    }
    if (! $cm = get_coursemodule_from_instance('reader', $reader->id, $course->id)) {
        throw new reader_exception('Course Module ID was incorrect');
    }
}

require_login($course->id);

add_to_log($course->id, 'reader', 'view personal page', "view.php?id=$id", "$cm->instance");

$contextmodule = reader_get_context(CONTEXT_MODULE, $cm->id);

if (isset($_SESSION['SESSION']->reader_lasttime) && $_SESSION['SESSION']->reader_lasttime < (time() - 300)) {
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

if ($reader->shuffleanswers == 0) {
    $DB->set_field('reader', 'shuffleanswers', 1, array('id' => $reader->id));
    $reader->shuffleanswers = 1;
}

// Initialize $PAGE, compute blocks
$PAGE->set_url('/mod/reader/view.php', array('id' => $cm->id));

$title = $course->shortname . ': ' . format_string($reader->name);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();

//Check time [open/close]
$timenow = time();
if (! empty($reader->timeopen) && $reader->timeopen > $timenow) {
    $msg = get_string('notopenyet', 'reader', userdate($reader->timeopen));
} else if (! empty($reader->timeclose) && $reader->timeclose < $timenow) {
    $msg = get_string('alreadyclosed', 'reader', userdate($reader->timeclose));
} else {
    $msg = '';
}
if ($msg) {
    $url = new moodle_url('/course/view.php', array('id' => $course->id));
    $msg .= html_writer::tag('p', $OUTPUT->continue_button($url));
    echo $OUTPUT->box($msg, 'generalbox', 'notice');
    echo $OUTPUT->footer();
    exit;
}

echo '<script type="text/javascript" src="js/ajax.js"></script>';

$alreadyansweredbooksid = array();

if (has_capability('mod/reader:manage', $contextmodule)) {
    require_once ('tabs.php');
} else {
/// Check subnet access
    if ($reader->subnet && !address_in_subnet(getremoteaddr(), $reader->subnet)) {
        throw new reader_exception(get_string('subneterror', 'quiz'), 'view.php?id='.$id);
    }
}

$leveldata = reader_get_stlevel_data($reader);

echo $OUTPUT->box_start('generalbox');

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
    $from   = '{reader_attempts} ra LEFT JOIN {reader_books} rb ON ra.quizid = rb.quizid';
    $where  = 'ra.userid= ? and ra.timefinish <= ?';
    $params = array($USER->id, $reader->ignoredate);
    if ($attempts = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY ra.timefinish", $params)) {
        foreach ($attempts as $attempt) {
            if (empty($attempt->bookimage)) {
                continue; // shouldn't happen !!
            }
            if ($attempt->passed == 'true' || $attempt->passed == 'TRUE') {
                $src = new moodle_url('/mod/reader/images.php/reader/images/'.$attempt->bookimage);
                $bookcoversinprevterm .= html_writer::empty_tag('img', array('src' => $src, 'border' => 0, 'alt' => $attempt->bookname, 'height' => 150, 'width' => 100));
            }
        }
    }
}

$bookcoversinthisterm = '';
$lastattemptdate = 0;

if (list($attemptdata, $summaryattemptdata) = reader_get_student_attempts($USER->id, $reader)) {
    foreach ($attemptdata as $attemptdata_) {

        $lastattemptdate = $attemptdata_['timefinish']; // fixing postgress problem

        $alreadyansweredbooksid[] = $attemptdata_['quizid'];

        if ($reader->bookcovers == 1 && $attemptdata_['status'] == 'correct') {
            $bookcoversinthisterm .= '<img src="'.$CFG->wwwroot.'/mod/reader/images.php/reader/images/'.$attemptdata_['image'].'" border="0" alt="'.$attemptdata_['booktitle'].'" height="150" width="100" /> ';
        }

        if ($attemptdata_['statustext'] == 'Passed' || $attemptdata_['statustext'] == 'Credit'){
            $totalwords += $attemptdata_['words'];
            $totalwordscount++;
            $showwords = $attemptdata_['words'];
        } else {
            $showwords = '';
        }

        if ($reader->pointreport == 1) {
            if ($reader->reportwordspoints != 1) {
                $table->data[] = array(date('d M Y', $attemptdata_['timefinish']),
                                            $attemptdata_['booktitle'],
                                            $attemptdata_['booklevel'].'[RL' .$attemptdata_['bookdiff'].']',
                                            //$attemptdata_['words'],
                                            $showwords,
                                            $attemptdata_['bookpercent'],
                                            $attemptdata_['totalpoints']);
            } else {  //without words
                $table->data[] = array(date('d M Y', $attemptdata_['timefinish']),
                                            $attemptdata_['booktitle'],
                                            $attemptdata_['booklevel'].'[RL'.$attemptdata_['bookdiff'].']',
                                            $attemptdata_['bookpercent'],
                                            $attemptdata_['totalpoints']);
            }
        } else {
            if ($reader->reportwordspoints == 2) {  //points and words
                $table->data[] = array(date('d M Y', $attemptdata_['timefinish']),
                                            $attemptdata_['booktitle'],
                                            $attemptdata_['booklevel'].'[RL'.$attemptdata_['bookdiff'].']',
                                            $attemptdata_['statustext'],
                                            //$attemptdata_['words'],
                                            $showwords,
                                            $attemptdata_['bookpoints'],
                                            $attemptdata_['totalpoints']);
            } else if ($reader->reportwordspoints == 1) {  //points only
                $table->data[] = array(date('d M Y', $attemptdata_['timefinish']),
                                            $attemptdata_['booktitle'],
                                            $attemptdata_['booklevel'].'[RL'.$attemptdata_['bookdiff'].']',
                                            $attemptdata_['statustext'],
                                            $attemptdata_['bookpoints'],
                                            $attemptdata_['totalpoints']);
            } else if ($reader->reportwordspoints == 0) {  //words only
                $table->data[] = array(date('d M Y', $attemptdata_['timefinish']),
                                            $attemptdata_['booktitle'],
                                            $attemptdata_['booklevel'].'[RL'.$attemptdata_['bookdiff'].']',
                                            $attemptdata_['statustext'],
                                            //$attemptdata_['words'],
                                            $showwords,
                                            $totalwords);
            }
        }
    }
}

$select = 'ra.id AS raid, ra.timefinish, ra.userid, ra.attempt, ra.percentgrade, ra.id, ra.quizid, ra.sumgrades, ra.passed, '.
          'rb.name, rb.publisher, rb.level, rb.length, rb.image, rb.difficulty, rb.words, rb.id AS rbid';
$from   = '{reader_attempts} ra LEFT JOIN {reader_books} rb ON rb.quizid = ra.quizid';
$where  = 'ra.preview != ? and ra.userid = ?';
$params = array(1, $USER->id);
if (! $studentattempts_p = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params)) {
    $studentattempts_p = array();
}

$select = 'ra.id AS raid, ra.timefinish, ra.userid, ra.attempt, ra.percentgrade, ra.id, ra.quizid, ra.sumgrades, ra.passed, '.
          'rb.name, rb.publisher, rb.level, rb.length, rb.image, rb.difficulty, rb.words, rb.id as rbid';
$from   = '{reader_attempts} ra LEFT JOIN {reader_noquiz} rb ON rb.quizid = ra.quizid';
$where  = 'ra.preview = ? and ra.userid = ?';
$params = array(1, $USER->id);
if (! $studentattempts_n = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params)) {
    $studentattempts_n = array();
}

$studentattempts = array_merge($studentattempts_p, $studentattempts_n);

foreach ($studentattempts as $studentattempt) {
    if (strtolower($studentattempt->passed) == 'true'){
        $totalwordsall += $studentattempt->words;
        $totalwordscountall++;
    }
}

if ($bookcoversinprevterm) {
    // display book covers from previous term
    echo $OUTPUT->heading(get_string('booksreadinpreviousterms', 'reader'), 2);
    echo html_writer::tag('p', $bookcoversinprevterm);

    // detect incorrect quizzes from previous term
    // and display a link to them if any are found
    $select = 'userid= ? AND timefinish <= ? AND passed <> ?';
    $params = array($USER->id, $reader->ignoredate, 'true');
    if ($DB->record_exists_select('reader_attempts', $select, $params)) {

        $url = new moodle_url('/mod/reader/showincorrectquizzes.php', array('id' => $id, 'uid' => $USER->id));
        $action = new popup_action('click', $url, 'bookcoversinprevterm', array('height' => 440, 'width' => 700));
        $text = get_string('incorrectbooksreadinpreviousterms', 'reader');
        echo html_writer::tag('p', $OUTPUT->action_link($url, $text, $action, array('title' => $text)));
    }
}

if ($bookcoversinprevterm && $bookcoversinthisterm) {
    echo html_writer::empty_tag('hr'); // separator
}

if ($bookcoversinthisterm) {
    // display book covers from this term
    echo $OUTPUT->heading(get_string('booksreadthisterm', 'reader'), 2);
    echo html_writer::tag('p', $bookcoversinthisterm);
}

echo '<br /><table width="100%"><tr><td><h2><span style="background-color:orange">'.get_string('readingreportfor', 'reader').": {$USER->firstname} {$USER->lastname} </span></h2>";
if (isset($_SESSION['SESSION']->reader_changetostudentview) && $_SESSION['SESSION']->reader_changetostudentview > 0) {
    $params = array('a' => 'admin', 'id' => $id, 'act' => 'reports');
    if (isset($_SESSION['SESSION']->reader_changetostudentviewlink)) {
        // NOTE: "reader_changetostudentviewlink" is set in "admin.php" to something like this:
        // grid={$grid}&searchtext={$searchtext}&page={$page}&sort={$sort}&orderby={$orderby}
        parse_str($_SESSION['SESSION']->reader_changetostudentviewlink, $more_params);
        $params = array_merge($params, $more_params);
    }
    $url = new moodle_url('/mod/reader/admin.php', $params);
    echo '</td><td width="50%" align="right"><small><span style="text-align: right;">';
    echo '<a href="'.$url.'">'.get_string('returntostudentlist', 'reader').'</a>';
    echo '</span></small>';
}
echo "</td></tr></table>";
if (! empty($table->data)) {
    echo '<center>'.html_writer::table($table).'</center>';
} else {
    print_string('nodata', 'reader');
}

if ($reader->wordsprogressbar) {
    echo '<table width="800" cellpadding="5" cellspacing="1" class="generaltable boxaligncenter"><tr>';
    echo '<th width="500" style="text-align:right;font-weight:lighter;">'.get_string('totalwords', 'reader').': '.$totalwords.'</th>';
    echo '<th style="text-align:right;font-weight:lighter;">'.get_string('totalwordsall', 'reader').": ".$totalwordsall.'</th>';
    echo '</tr></table>';

    echo '<table width="820" cellpadding="0" cellspacing="0" class="generaltable boxaligncenter"><tr><td align="center">';
    if ($progressimage = reader_get_goal_progress($totalwords, $reader)) {
        echo '<table width="820px"><tr><td align="center"><div style="position:relative;z-index:5;height:100px;width:850px;">'.$progressimage.'</div>';
        echo '</td></tr><tr><td>&nbsp;<b>&nbsp;'.get_string('in1000sofwords', 'reader').'</b></td></tr></table>';
    }
    echo '</td></tr></table>';
}

//if ($reader->nextlevel == $leveldata['onthislevel']) {
//    $displaymore = "";
//} else {
//    $displaymore = " more ";
//}

echo "<h3>".get_string('yourcurrentlevel', 'reader').": {$leveldata['studentlevel']}</h3>";

$promoteinfo = $DB->get_record('reader_levels', array('userid' => $USER->id, 'readerid' => $reader->id));
if ($promoteinfo->nopromote == 1) {
    if ($promoteinfo->promotionstop == $leveldata['studentlevel']) {
        print_string('pleaseaskyourinstructor', 'reader');
    } else {
        print_string('yourteacherhasstopped', 'reader');
    }

    print_string('youcantakeasmanyquizzesasyouwant', 'reader', $leveldata['studentlevel']);

    if ($leveldata['onprevlevel'] <= 0) {
        $quizcount = 'no';
    } else {
        $quizcount = $leveldata['onprevlevel'];
    }
    if ($leveldata['onprevlevel'] == 1) { $quiztext = 'quiz'; } else { $quiztext = 'quizzes'; }
    print_string('youmayalsotake', 'reader', $quizcount);
    echo '{$quiztext} '.get_string('atlevel', 'reader').' '.($leveldata['studentlevel'] - 1).' ';

} else if ($reader->levelcheck == 1) {

    if ($leveldata['onthislevel'] == 1) {
        print_string('youmusttakequiz', 'reader', $leveldata['onthislevel']);
    } else {
        print_string('youmusttakequizzes', 'reader', $leveldata['onthislevel']);
    }
    print_string('atlevelbeforebeingpromoted', 'reader', $leveldata['studentlevel']);

    if ($leveldata['onprevlevel'] <= 0) {
        $quizcount = 'no';
    } else {
        $quizcount = $leveldata['onprevlevel'];
    }
    if ($leveldata['onprevlevel'] == 1) { $quiztext = "quiz"; } else { $quiztext = "quizzes"; }

    if (($leveldata['studentlevel'] - 1) >= 0) {

        if ($leveldata['onprevlevel'] > 0 && $leveldata['onnextlevel'] <= 0) {
            $quiznextlevelso = 'but';
        } else {
            $quiznextlevelso = 'and';
        }
        print_string('youmayalsotake', 'reader', $quizcount);
        echo "{$quiztext} ".get_string('atlevel', 'reader')." ".($leveldata['studentlevel'] - 1)." ";
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
    echo $quiznextlevelso.get_string('andnextmore', 'reader', $quizcount).$quiztext.get_string('atlevel', 'reader'). ' ' . ($leveldata['studentlevel'] + 1 .'.');
} else if ($reader->levelcheck == 0) {
    print_string('butyoumaytakequizzes', 'reader');
}

//if ($reader->attemptsofday == 0) {
//    $reader->attemptsofday = 5;
//}
if ($reader->attemptsofday > 0) { // && $_SESSION['SESSION']->reader_teacherview != "teacherview"
    //$lastttempt = $DB->get_record_sql('SELECT * FROM {reader_attempts} WHERE reader= ? and userid= ?  ORDER by timefinish DESC',array($reader->id, $USER->id));
    //$lastttempt = $DB->get_record_sql('SELECT * FROM {reader_attempts} ra, {reader} r WHERE ra.userid= ?  and r.id=ra.reader and r.course= ?  ORDER by ra.timefinish DESC', array($USER->id, $course->id));
    $cleartime = $lastattemptdate + ($reader->attemptsofday * 24 * 60 * 60);
    $cleartime = reader_forcedtimedelay_check ($cleartime, $reader, $leveldata['studentlevel'], $lastattemptdate);
    $time = time();
    if ($time > $cleartime) {
        $showform = true;
        echo '<span style="background-color:#00CC00">&nbsp;&nbsp;'.get_string('youcantakeaquiznow', 'reader').'&nbsp;&nbsp;</span>';
    } else {
        $showform = false;
        $approvetime = $cleartime - time();
        echo '<span style="background-color:#FF9900">&nbsp;&nbsp;'.get_string('youcantakeaquizafter', 'reader').' '.reader_nicetime2($approvetime).'&nbsp;&nbsp;</span>';

    }
} else {
    $showform = true;
}

echo '<h3>'.get_string('messagefromyourteacher', 'reader').'</h3>';

//print_simple_box_start('center', '700px', '#ffffff', 10);

$messages = $DB->get_records_sql('SELECT * FROM {reader_messages} WHERE instance = ?', array($cm->instance));

if (count($messages) > 0 && !empty($messages)) {
    $usergroupsarray = array(0);
    $studentgroups = groups_get_user_groups($course->id, $USER->id);
    foreach ($studentgroups as $studentgroup) {
        foreach ($studentgroup as $studentgroup_) {
            $usergroupsarray[] = $studentgroup_;
        }
    }
    //$messages = $DB->get_records_sql('SELECT * FROM {reader_messages} WHERE instance = ?', array($cm->instance));
    foreach ($messages as $message) {
        $forgroupsarray = explode (',', $message->users);
        $showmessage = false;
        $bgcolor  = '';

        foreach ($forgroupsarray as $forgroupsarray_) {
            if (in_array($forgroupsarray_, $usergroupsarray)) {
                $showmessage = true;
            }
        }

        if ($message->timemodified > (time() - ( 48 * 60 * 60))) {
            $bgcolor = 'bgcolor="#CCFFCC"';
        }

        if ($showmessage) {
            echo '<table width="100%"><tr><td align="right"><table cellspacing="0" cellpadding="0" class="forumpost blogpost blog" '.$bgcolor.' width="90%">';
            echo '<tr><td align="left"><div style="margin-left: 10px;margin-right: 10px;">'."\n";
            echo format_text($message->text);
            echo '<div style="text-align:right"><small>';
            $teacherdata = $DB->get_record('user', array('id' => $message->teacherid));
            echo "<a href=\"{$CFG->wwwroot}/user/view.php?id={$message->teacherid}&amp;course={$course->id}\">".fullname($teacherdata, true)."</a>";
            echo '</small></div>';
            echo '</div></td></tr></table></td></tr></table>'."\n\n";
        }
    }
}

echo '<h3>'.get_string('selectthebookthatyouwant', 'reader').':</h3>';

$publisherform  = array('id='.$id.'&publisher=Select Publisher' => get_string('selectpublisher', 'reader'));
$publisherform2 = array('id='.$id.'&publisher=Select Publisher' => get_string('selectpublisher', 'reader'));
$seriesform     = array(get_string('selectseries', 'reader'));
$levelsform     = array(get_string('selectlevel', 'reader'));
$booksform      = array();

$alreadyansweredbooksid = array();
$leveldata              = reader_get_stlevel_data($reader);
$promoteinfo            = $DB->get_record('reader_levels', array('userid' => $USER->id, 'readerid' => $reader->id));
if ((isset($_SESSION['SESSION']->reader_teacherview) && $_SESSION['SESSION']->reader_teacherview == "teacherview") || $reader->levelcheck == 0) {
    $levels = range(0, 15);
} else {
    $levels = array();
    if ($leveldata['onthislevel'] > 0) {
        $levels[] = $leveldata['studentlevel'];
    }
    if ($leveldata['onprevlevel'] > 0 && ($leveldata['studentlevel'] - 1) >= 0) {
        $levels[] = ($leveldata['studentlevel'] - 1);
    }
    if ($leveldata['onnextlevel'] > 0) {
        $levels[] = ($leveldata['studentlevel'] + 1);
    }
}
$allowdifficultysql = implode(',', $levels);

$alreadyansweredbookssametitle = array();
if (list($attemptdata, $summaryattemptdata) = reader_get_student_attempts($USER->id, $reader, true, true)) {
    foreach ($attemptdata as $attemptdata_) {
        reader_set_attempt_result ($attemptdata_['id'], $reader);  //insert result
        $alreadyansweredbooksid[] = $attemptdata_['quizid'];
        if (! empty($attemptdata_['sametitle'])) {
            $alreadyansweredbookssametitle[] = $attemptdata_['sametitle'];
        }
    }
}

$publishers = $DB->get_records ('reader_books', NULL, 'publisher');
foreach ($publishers as $publisher_) {
    $publisherform['id='.$id.'&publisher='.$publisher_->publisher] = $publisher_->publisher;
}

foreach ($publisherform as $key => $value) {
    $needtousepublisher = false;
    if ($allowdifficultysql) {
        if ($reader->bookinstances == 1) {
            $books = $DB->get_records_sql("SELECT * FROM {reader_books} rb INNER JOIN {reader_book_instances} ib ON ib.bookid = rb.id WHERE ib.readerid = ? and rb.publisher= ? and rb.hidden='0' and rb.private IN(0, ? ) and ib.difficulty IN( ".$allowdifficultysql." ) ORDER BY rb.name", array($reader->id, $value, $reader->id));
        } else {
            $books = $DB->get_records_sql("SELECT * FROM {reader_books} WHERE publisher= ? and hidden='0' and private IN(0, ? ) and difficulty IN( ".$allowdifficultysql." ) ORDER BY name", array($value, $reader->id));
        }
        foreach ($books as $books_) {
            if (! empty($books_->quizid)) {
                if ($reader->bookinstances == 1) {
                    if (! in_array($books_->bookid, $alreadyansweredbooksid)) {
                        $needtousepublisher = true;
                    }
                } else {
                    if (! in_array($books_->id, $alreadyansweredbooksid)) {
                        $needtousepublisher = true;
                    }
                }

                if ($showform) {
                    if (! empty($books_->sametitle) && is_array($alreadyansweredbookssametitle)) {
                        if ($reader->bookinstances == 1) {
                            if (! in_array($books_->sametitle, $alreadyansweredbookssametitle)) {
                                $needtousepublisher = true; break;
                            }
                        } else {
                            if (! in_array($books_->sametitle, $alreadyansweredbookssametitle)) {
                                $needtousepublisher = true; break;
                            }
                        }
                    } else {
                        if ($reader->bookinstances == 1) {
                            $needtousepublisher = true;
                            break;
                        } else {
                            $needtousepublisher = true;
                            break;
                        }
                    }
                }
            }
        }
    }
    if ($needtousepublisher) {
        $publisherform2['id='.$id.'&publisher='.$value] = $value;
    }
}
unset($publisherform);
$publisherform = $publisherform2;

if ($attempt = $DB->get_record('reader_attempts', array('reader' => $cm->instance, 'userid' => $USER->id, 'timefinish' => 0))) {
    $showform = false;

    $timelimit = 60 * $reader->timelimit;

    if ($timelimit < (time() - $attempt->timestart)) {
        $showform = true;
        $attempt->timemodified = time();
        $attempt->timefinish   = time();
        $attempt->passed       = 'false';
        $attempt->percentgrade      = 0;
        $attempt->sumgrades    = '0';
        $attempt->bookrating   = 0;
        $DB->update_record('reader_attempts', $attempt);
    } else {
        $bookname = $DB->get_field('reader_books', 'name', array('quizid' => $attempt->quizid));
        print_string('pleasecompletequiz', 'reader', $bookname);
        //quiz/attempt.php?attempt=12910&page=1#q0
        if (empty($_SESSION['SESSION']->reader_lastattemptpage)) {
            $url = $CFG->wwwroot.'/mod/reader/quiz/attempt.php?attempt='.$attempt->id.'&page=1#q0';
        } else {
            $url = $CFG->wwwroot.'/mod/reader/quiz/attempt.php?'.$_SESSION['SESSION']->reader_lastattemptpage;
        }
        echo ' <a href="'.$url.'">'.get_string('complete', 'reader').'</a>';
    }
}

if (isset($_SESSION['SESSION']->reader_changetostudentview)) {
    if ($showform == false && $_SESSION['SESSION']->reader_changetostudentview > 0) {
        echo '<br />'.get_string('thisblockunavailable', 'reader').'<br />';
        $showform == true;
    }
}

if ($showform && has_capability('mod/reader:attemptreaders', $contextmodule)) {

    echo '<script type="text/javascript">'."\n";
    echo '//<![CDATA['."\n";
    echo 'function validateForm(form) {'."\n";
    echo '    return (form && form.book && isChosen(form.book));'."\n";
    echo '}'."\n";
    echo 'function isChosen(select) {'."\n";
    echo '    if (select.selectedIndex == -1) {'."\n";
    echo '        alert("Please choose book!");'."\n";
    echo '        return false;'."\n";
    echo '    } else {'."\n";
    echo '        return true;'."\n";
    echo '    }'."\n";
    echo '}'."\n";
    echo '//]]>'."\n";
    echo '</script>'."\n";

    echo '<form action="quiz/startattempt.php?id='.$id.'" method="post" id="mform1">';
    echo '<center><table width="600px">';
    echo '<tr><td width="200px">'.get_string('publisherseries', 'reader').'</td><td width="10px"></td><td width="200px"></td></tr>';
    echo '<tr><td valign="top">';
    echo '<select name="publisher" id="id_publisher" onchange="request(\'view_get_bookslist.php?ajax=true&\' + this.options[this.selectedIndex].value,\'selectthebook\'); return false;">';
    foreach ($publisherform as $publisherformkey => $publisherformvalue) {
        echo '<option value="'.$publisherformkey.'" ';
        if ($publisherformvalue == $publisher) { echo 'selected="selected"'; }
        echo ' >'.$publisherformvalue.'</option>';
    }
    echo '</select>';
    echo '</td><td valign="top">';

    echo '</td><td valign="top"><div id="selectthebook">';

    echo '</div></td></tr>';
    echo '<tr><td colspan="3" align="center">';

    echo '</td></tr>';
    echo '<tr><td colspan="3" align="center"><input type="button" value="Take quiz" onclick="if (validateForm(this.form)) {this.form.submit() };" /> <input value="'.get_string('returntocoursepage', 'reader').'" onclick="location.href=\''.$CFG->wwwroot.'/course/view.php?id='.$course->id.'\'" type="button" /></td></tr>';
    echo '</table>';
    echo '</form></center>';

} else if (! $DB->get_record('reader_attempts', array('reader' => $cm->instance, 'userid' => $USER->id, 'timefinish' => 0))) {
    print_string('pleasewait', 'reader');
}

if (isset($_SESSION['SESSION']->reader_changetostudentview) && $_SESSION['SESSION']->reader_changetostudentview > 0) {
    $_SESSION['SESSION']->reader_lastuser    = $USER->id;
    $_SESSION['SESSION']->reader_page        = 'view';
    $_SESSION['SESSION']->reader_lasttime    = time();
    $_SESSION['SESSION']->reader_lastuserfrom = $_SESSION['SESSION']->reader_changetostudentview;

    if ($USER = $DB->get_record('user', array('id' => $_SESSION['SESSION']->reader_changetostudentview))) {
        unset($_SESSION['SESSION']->reader_changetostudentview);
        $_SESSION['SESSION']->reader_teacherview = 'teacherview';
    }
}

echo $OUTPUT->box_end();

print ('<center><img src="img/credit.jpg" height="40px"></center>');

echo $OUTPUT->footer();
