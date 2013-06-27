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
 * mod/reader/admin.php
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
require_once($CFG->dirroot.'/lib/excellib.class.php');
require_once($CFG->dirroot.'/lib/tablelib.php');
require_once($CFG->dirroot.'/question/editlib.php');

$id                     = optional_param('id', 0, PARAM_INT);
$a                      = optional_param('a', NULL, PARAM_CLEAN);
$act                    = optional_param('act', NULL, PARAM_CLEAN);
$quizzesid               = optional_param('quizzesid', NULL, PARAM_CLEAN);
$publisher              = optional_param('publisher', NULL, PARAM_CLEAN);
$publisherex            = optional_param('publisherex', NULL, PARAM_CLEAN);
$difficulty             = optional_param('difficulty', NULL, PARAM_CLEAN);
$todifficulty           = optional_param('todifficulty', NULL, PARAM_CLEAN);
$difficultyex           = optional_param('difficultyex', NULL, PARAM_CLEAN);
$level                  = optional_param('level', NULL, PARAM_CLEAN);
$tolevel                = optional_param('tolevel', NULL, PARAM_CLEAN);
$topublisher            = optional_param('topublisher', NULL, PARAM_CLEAN);
$levelex                = optional_param('levelex', NULL, PARAM_CLEAN);
$length                 = optional_param('length', NULL, PARAM_CLEAN);
$tolength               = optional_param('tolength', NULL, PARAM_CLEAN);
$gid                    = optional_param('gid', NULL, PARAM_CLEAN);
$excel                  = optional_param('excel', NULL, PARAM_CLEAN);
$del                    = optional_param('del', NULL, PARAM_CLEAN);
$attemptid              = optional_param('attemptid', NULL, PARAM_CLEAN);
$restoreattemptid       = optional_param('restoreattemptid', NULL, PARAM_CLEAN);
$upassword              = optional_param('upassword', NULL, PARAM_CLEAN);
$groupid                = optional_param('groupid', 0, PARAM_INT);
$activehours            = optional_param('activehours', NULL, PARAM_CLEAN);
$text                   = optional_param('text', NULL, PARAM_CLEAN);
$bookid                 = reader_optional_param_array('bookid', NULL, PARAM_CLEAN);
$deletebook             = optional_param('deletebook', 0, PARAM_INT);
$deleteallattempts      = optional_param('deleteallattempts', 0, PARAM_INT);
$editmessage            = optional_param('editmessage', 0, PARAM_INT);
$deletemessage          = optional_param('deletemessage', 0, PARAM_INT);
$hidebooks              = optional_param('hidebooks', 0, PARAM_ALPHA);
$unhidebooks            = optional_param('unhidebooks', 0, PARAM_ALPHA);
$sort                   = optional_param('sort', 'username', PARAM_CLEAN);
$orderby                = optional_param('orderby', 'ASC', PARAM_CLEAN);
$dir                    = optional_param('dir', 'ASC', PARAM_ALPHA);
$slevel                 = optional_param('slevel', 0, PARAM_ALPHA);
$page                   = optional_param('page', 0, PARAM_INT);
$perpage                = optional_param('perpage', 0, PARAM_INT);
$userid                 = optional_param('userid', 0, PARAM_INT);
$changelevel            = optional_param('changelevel', NULL, PARAM_CLEAN);
$searchtext             = optional_param('searchtext', NULL, PARAM_CLEAN);
$needip                 = optional_param('needip', NULL, PARAM_CLEAN);
$setip                  = optional_param('setip', NULL, PARAM_CLEAN);
$nopromote              = optional_param('nopromote', NULL, PARAM_CLEAN);
$promotionstop          = optional_param('promotionstop', NULL, PARAM_CLEAN);
$private                = optional_param('private', 0, PARAM_INT);
$ajax                   = optional_param('ajax', NULL, PARAM_CLEAN);
$changeallstartlevel    = optional_param('changeallstartlevel', -1, PARAM_INT);
$changeallcurrentlevel  = optional_param('changeallcurrentlevel', -1, PARAM_INT);
$changeallcurrentgoal   = optional_param('changeallcurrentgoal', NULL, PARAM_CLEAN);
$changeallpromo         = optional_param('changeallpromo', NULL, PARAM_CLEAN);
$changeallstoppromo     = optional_param('changeallstoppromo', -1, PARAM_INT);
$userimagename          = optional_param('userimagename', NULL, PARAM_CLEAN);
$award                  = optional_param('award', NULL, PARAM_CLEAN);
$student                = optional_param('student', NULL, PARAM_CLEAN);
$useonlythiscourse      = optional_param('useonlythiscourse', NULL, PARAM_CLEAN);
$ipmask                 = optional_param('ipmask', 3, PARAM_CLEAN);
$fromtime               = optional_param('fromtime', 86400, PARAM_CLEAN);
$maxtime                = optional_param('maxtime', 1800, PARAM_CLEAN);
$cheated                = optional_param('cheated', NULL, PARAM_CLEAN);
$uncheated              = optional_param('uncheated', NULL, PARAM_CLEAN);
$findcheated            = optional_param('findcheated', NULL, PARAM_CLEAN);
$separategroups         = optional_param('separategroups', NULL, PARAM_CLEAN);
$levelall               = optional_param('levelall', NULL, PARAM_CLEAN);
$levelc                 = optional_param('levelc', NULL, PARAM_CLEAN);
$wordsorpoints          = optional_param('wordsorpoints', NULL, PARAM_CLEAN);
$setgoal                = optional_param('setgoal', NULL, PARAM_CLEAN);
$wordscount             = optional_param('wordscount', NULL, PARAM_CLEAN);
$viewasstudent          = optional_param('viewasstudent', NULL, PARAM_CLEAN);
$booksratingbest        = optional_param('booksratingbest', NULL, PARAM_CLEAN);
$booksratinglevel       = optional_param('booksratinglevel', NULL, PARAM_CLEAN);
//$booksratinglevel       = optional_param('booksratinglevel');
$booksratingterm        = optional_param('booksratingterm', NULL, PARAM_CLEAN);
$booksratingwithratings = optional_param('booksratingwithratings', NULL, PARAM_CLEAN);
$booksratingshow        = optional_param('booksratingshow', NULL, PARAM_CLEAN);
$quiz                   = reader_optional_param_array('quiz', NULL, PARAM_CLEAN);
$sametitlekey           = optional_param('sametitlekey', NULL, PARAM_CLEAN);
$sametitleid            = optional_param('sametitleid', NULL, PARAM_CLEAN);
$wordstitlekey          = optional_param('wordstitlekey', NULL, PARAM_CLEAN);
$wordstitleid           = optional_param('wordstitleid', NULL, PARAM_CLEAN);
$leveltitlekey          = optional_param('leveltitlekey', NULL, PARAM_CLEAN);
$leveltitleid           = optional_param('leveltitleid', NULL, PARAM_CLEAN);
$publishertitlekey      = optional_param('publishertitlekey', NULL, PARAM_CLEAN);
$publishertitleid       = optional_param('publishertitleid', NULL, PARAM_CLEAN);
$checkattempt           = optional_param('checkattempt', NULL, PARAM_CLEAN);
$checkattemptvalue      = optional_param('checkattemptvalue', 0, PARAM_INT);
$book                   = optional_param('book', 0, PARAM_INT);
$noquizuserid           = optional_param('noquizuserid', NULL, PARAM_CLEAN);
$withoutdayfilter       = optional_param('withoutdayfilter', NULL, PARAM_CLEAN);
$numberofsections       = optional_param('numberofsections', NULL, PARAM_CLEAN);
$ct                     = optional_param('ct', NULL, PARAM_CLEAN);
$adjustscoresupbooks    = reader_optional_param_array('adjustscoresupbooks', NULL, PARAM_CLEAN);
$adjustscoresaddpoints  = optional_param('adjustscoresaddpoints', NULL, PARAM_CLEAN);
$adjustscoresupall      = optional_param('adjustscoresupall', NULL, PARAM_CLEAN);
$adjustscorespand       = optional_param('adjustscorespand', NULL, PARAM_CLEAN);
$adjustscorespby        = optional_param('adjustscorespby', NULL, PARAM_CLEAN);
$sctionoption           = optional_param('sctionoption', NULL, PARAM_CLEAN);
$studentuserid          = optional_param('studentuserid', 0, PARAM_INT);
$studentusername        = optional_param('studentusername', NULL, PARAM_CLEAN);
$bookquiznumber         = optional_param('bookquiznumber', 0, PARAM_INT);

$readercfg = get_config('reader');

if (isset($_SESSION['SESSION']->reader_changetostudentview) && $_SESSION['SESSION']->reader_changetostudentview > 0) {
    if ($USER =  $DB->get_record('user', array('id'=>$_SESSION['SESSION']->reader_changetostudentview))) {
        unset($_SESSION['SESSION']->reader_changetostudentview);
        $_SESSION['SESSION']->reader_teacherview = 'teacherview';
    }
}
if ((isset($_SESSION['SESSION']->reader_page) && $_SESSION['SESSION']->reader_page == 'view') || (isset($_SESSION['SESSION']->reader_lasttime) && $_SESSION['SESSION']->reader_lasttime < (time() - 300))) {
    unset($_SESSION['SESSION']->reader_page);
    unset($_SESSION['SESSION']->reader_lasttime);
    unset($_SESSION['SESSION']->reader_lastuser);
    unset($_SESSION['SESSION']->reader_lastuserfrom);
}

if (($ct || $ct == 0) && $ct != "") {
    $_SESSION['SESSION']->reader_values_ct = $ct;
} else if (isset($_SESSION['SESSION']->reader_values_ct)) {
    $ct = $_SESSION['SESSION']->reader_values_ct;
}
//echo $ct.'!';

//if (isset($ct)) {
//    $_SESSION['SESSION']->reader_values_ct = $ct;
//    echo 'USED!';
//} else {
//    $ct = $_SESSION['SESSION']->reader_values_ct;
//    echo 'SET!';
//}
//if (isset($gid)) {
//    $_SESSION['SESSION']->reader_values_gid = $gid;
//} else {
//    $gid = $_SESSION['SESSION']->reader_values_gid;
//}

if (($gid || $gid == 0) && $gid != "") {
    $_SESSION['SESSION']->reader_values_gid = $gid;
} else if (isset($_SESSION['SESSION']->reader_values_gid)) {
    $gid = $_SESSION['SESSION']->reader_values_gid;
}

$row = 3; // Start row for Excel file

if (! empty($searchtext)) {
    $perpage = 1000;
} else if (isset($_SESSION['SESSION']->reader_perpage) && $_SESSION['SESSION']->reader_perpage == 1000) {
    $_SESSION['SESSION']->reader_perpage = 30;
}

if (! isset($_SESSION['SESSION']->reader_perpage)) {
    if (! $perpage) {
        $_SESSION['SESSION']->reader_perpage = 30;
    } else {
        $_SESSION['SESSION']->reader_perpage = $perpage;
    }
}

if (! $perpage) {
    $perpage = $_SESSION['SESSION']->reader_perpage;
} else {
    $_SESSION['SESSION']->reader_perpage = $perpage;
}

if ($id) {
    if (! $cm = get_coursemodule_from_id('reader', $id)) {
        throw new reader_exception('Course Module ID was incorrect');
    }
    if (! $course = $DB->get_record('course', array('id'=>$cm->course))) {
        throw new reader_exception('Course is misconfigured');
    }
    if (! $reader = $DB->get_record('reader', array('id'=> $cm->instance))) {
        throw new reader_exception('Course module is incorrect');
    }
} else {
    if (! $reader = $DB->get_record('reader', array('id'=> $a))) {
        throw new reader_exception('Course module is incorrect');
    }
    if (! $course = $DB->get_record('course', array('id'=> $reader->course))) {
        throw new reader_exception('Course is misconfigured');
    }
    if (! $cm = get_coursemodule_from_instance('reader', $reader->id, $course->id)) {
        throw new reader_exception('Course Module ID was incorrect');
    }
}

require_login($course->id);

add_to_log($course->id, 'reader', 'admin area', 'admin.php?id='.$id, $cm->instance);

if ($act == 'fullreports' && $ct == 0) {
    $reader->ignoredate = 0;
}

$context = reader_get_context(CONTEXT_COURSE, $course->id);
$contextmodule = reader_get_context(CONTEXT_MODULE, $cm->id);

// preferred time and date format for this page
$timeformat = 'H:i';   // 13:45
$dateformat = 'd M Y'; // 02 Jun 2013

// check $deletebook is valid
if ($deletebook) {
    $deletequiz = $DB->get_field('reader_books', 'quizid', array('id' => $deletebook));
}
if (empty($deletebook) || empty($deletequiz)) {
    $deletebook = $deletequiz = 0;
}

// we limit the number of students to 400,
// otherwise the reports run out of memory

$coursestudents = get_enrolled_users($context, '', $gid);
$coursestudents = array_slice($coursestudents, 0, 400, true);

if (has_capability('mod/reader:manage', $contextmodule) && $quizzesid) {
    if (empty($publisher) && ($publisherex == '0')) {
        error('Please choose publisher', 'admin.php?a=admin&id='.$id.'&act=addquiz');
    }
    else if (! isset($difficulty) && $difficulty != 0 && $difficultyex != 0 && !$difficultyex) {
        error('Please choose Reading Level', 'admin.php?a=admin&id='.$id.'&act=addquiz');
    }
    else if (! isset($level) && ($levelex == '0')) {
        error('Please choose level', 'admin.php?a=admin&id='.$id.'&act=addquiz');
    }

    if ($_FILES['userimage']) {
        if (is_uploaded_file($_FILES['userimage']['tmp_name'])) {
            $ext = substr($_FILES['userimage']['name'], 1 + strrpos($_FILES['userimage']['name'], '.'));
            if (in_array(strtolower($ext), array('jpg', 'jpeg', 'gif'))) {
                if (! make_upload_directory('reader/images')) {
                    //return false;
                }
                if (file_exists($CFG->dataroot.'/reader/images/'.$_FILES['userimage']['name'])) {
                    list($imgname, $imgtype) = explode('.', $_FILES['userimage']['name']);
                    $newimagename = $imgname.rand(9999,9999999);
                    $newimagename .= '.'.$ext;
                    $newimagename = strtolower($newimagename);
                } else {
                    $newimagename = $_FILES['userimage']['name'];
                }
                @move_uploaded_file($_FILES['userimage']['tmp_name'], $CFG->dataroot.'/reader/images/'.$newimagename);
            }
        }
    }

    if ($userimagename) {
        $newimagename = $userimagename;
    }

    foreach ($quizzesid as $quizzesid_) {
        $newquiz = new object;
        if (! $publisher) {
            $newquiz->publisher = $publisherex;
        } else {
            $newquiz->publisher = $publisher;
        }

        if (! isset($difficulty) && $difficulty != 0) {
            $newquiz->difficulty = $difficultyex;
        } else {
            $newquiz->difficulty = $difficulty;
        }

        if (! isset($level)) {
            $newquiz->level = $levelex;
        } else {
            $newquiz->level = $level;
        }

        if ($length) {
            $newquiz->length = $length;
        }

        if ($newimagename) {
            $newquiz->image = $newimagename;
        }

        if ($wordscount) {
            $newquiz->words = $wordscount;
        }

        $quizdata = $DB->get_record('quiz', array('id' => $quizzesid_));

        $newquiz->name = $quizdata->name;

        $newquiz->quizid = $quizzesid_;
        $newquiz->private = $private;

        $DB->insert_record('reader_books', $newquiz);
    }

    $message_forteacher = '<center><h3>'.get_string('quizzesadded', 'reader').'</h3></center><br /><br />';

    add_to_log($course->id, 'reader', 'AA-Quizzes Added', 'admin.php?id='.$id, $cm->instance);
}

if (has_capability('mod/reader:deletereaderattempts', $contextmodule) && $act == 'viewattempts' && $attemptid) {
    //if (authenticate_user_login($USER->username, $upassword)) {
        $readerattempt = $DB->get_record('reader_attempts', array('id' => $attemptid));
        // make sure "uniqueid" is in fact unique
        $DB->delete_records('reader_deleted_attempts', array('uniqueid' => $readerattempt->uniqueid));
        // transfer attempt to "deleted_attempts" table
        unset($readerattempt->id);
        $DB->insert_record('reader_deleted_attempts', $readerattempt);
        $DB->delete_records('reader_attempts', array('id' => $attemptid));
        add_to_log($course->id, 'reader', 'AA-reader_deleted_attempts', 'admin.php?id='.$id, $cm->instance);
    //}
}

if (has_capability('mod/reader:deletereaderattempts', $contextmodule) && $act == 'viewattempts' && $bookquiznumber) {
    if (empty($studentuserid)) {
        $data = $DB->get_record('user', array('username' => $studentusername));
        $studentuserid = $data->id;
    }

    if (! empty($studentuserid)) {
        $readerattempt = $DB->get_record('reader_deleted_attempts', array('userid' => $studentuserid, 'quizid' => $bookquiznumber));
        unset($readerattempt->id);
        $DB->insert_record('reader_attempts', $readerattempt);
        $DB->delete_records('reader_deleted_attempts', array('userid' => $studentuserid, 'quizid' => $bookquiznumber));
        add_to_log($course->id, 'reader', 'AA-reader_restore_attempts', 'admin.php?id='.$id, $cm->instance);
    }
}

if (has_capability('mod/reader:manage', $contextmodule) && $text && $activehours) {
    $message = new object;

    foreach ($groupid as $groupkey => $groupvalue) {
        $message->users .= $groupvalue.',';
    }

    $message->users = substr($message->users,0,-1);

    $message->instance = $cm->instance;
    $message->teacherid = $USER->id;
    $message->text = $text;
    $message->timebefore = time() + ($activehours * 60 * 60);
    $message->timemodified = time();

    if ($editmessage) {
        $message->id = $editmessage;
        $DB->update_record('reader_messages', $message);
    } else {
        $DB->insert_record('reader_messages', $message);
    }

    add_to_log($course->id, 'reader', 'AA-Message Added', 'admin.php?id='.$id, $cm->instance);
}

if (has_capability('mod/reader:manage', $contextmodule) && $deletemessage) {
    $DB->delete_records('reader_messages', array('id' => $deletemessage));

    add_to_log($course->id, 'reader', 'AA-Message Deleted', 'admin.php?id='.$id, $cm->instance);
}

if (has_capability('mod/reader:manage', $contextmodule) && $checkattempt && $ajax == 'true') {
    $DB->set_field('reader_attempts',  'checkbox',  $checkattemptvalue, array('id' => $checkattempt));
    die;
}

if (has_capability('mod/reader:manage', $contextmodule) && $bookid) {
    if ($hidebooks) {
        foreach ($bookid as $bookidkey => $bookidvalue) {
            $DB->set_field('reader_books',  'hidden',  1, array('id' => $bookidkey));
        }
    }
    if ($unhidebooks) {
        foreach ($bookid as $bookidkey => $bookidvalue) {
            $DB->set_field('reader_books',  'hidden',  0, array('id' => $bookidkey));
        }
    }

    add_to_log($course->id, 'reader', 'AA-Books status changed', 'admin.php?id='.$id, $cm->instance);
}

if (has_capability('mod/reader:manage', $contextmodule) && $deletequiz && $deleteallattempts) {
    $DB->delete_records('reader_attempts', array('quizid' => $deletequiz, 'reader' => $reader->id));

    add_to_log($course->id, 'reader', 'AA-Attempts Deleted', 'admin.php?id='.$id, $cm->instance);
}

if (has_capability('mod/reader:manage', $contextmodule) && $deletebook && $deletequiz) {
    if ($DB->count_records('reader_attempts', array('quizid' => $deletequiz, 'reader' => $reader->id)) == 0) {
        $DB->delete_records('reader_books', array('id' => $deletebook));
    } else {
        $needdeleteattemptsfirst = $DB->get_records_sql('SELECT * FROM {reader_attempts} WHERE quizid= ?  and reader= ?  ORDER BY timefinish', array($deletequiz, $reader->id));
    }
    add_to_log($course->id, 'reader', 'AA-Book Deleted', 'admin.php?id='.$id, $cm->instance);
}

if (has_capability('mod/reader:manage', $contextmodule) && $ajax == 'true' && isset($sametitlekey)) {
    $DB->set_field('reader_books',  'sametitle',  $sametitlekey, array('id' => $sametitleid));
    echo $sametitlekey;
    die;
}

if (has_capability('mod/reader:manage', $contextmodule) && $ajax == 'true' && isset($wordstitlekey)) {
    $DB->set_field('reader_books',  'words',  $wordstitlekey, array('id' => $wordstitleid));
    echo $wordstitlekey;
    die;
}

if (has_capability('mod/reader:manage', $contextmodule) && $ajax == 'true' && isset($publishertitlekey)) {
    $DB->set_field('reader_books',  'publisher',  $publishertitlekey, array('id' => $publishertitleid));
    echo $publishertitlekey;
    die;
}

if (has_capability('mod/reader:manage', $contextmodule) && $ajax == 'true' && isset($leveltitlekey)) {
    $DB->set_field('reader_books',  'level',  $leveltitlekey, array('id' => $leveltitleid));
    echo $leveltitlekey;
    die;
}

if (has_capability('mod/reader:manage', $contextmodule) && ($changelevel || $changelevel == 0) && $slevel) {
    if ($DB->get_record('reader_levels', array('userid' => $userid, 'readerid' => $reader->id))) {
        $DB->set_field('reader_levels',  $slevel,  $changelevel, array('userid' => $userid,  'readerid' => $reader->id));
        $DB->set_field('reader_levels', 'time', time(), array('userid' => $userid, 'readerid' => $reader->id));
    } else {
        $data = new object;
        $data->userid = $userid;
        $data->startlevel = $changelevel;
        $data->currentlevel = $changelevel;
        $data->readerid = $reader->id;
        $data->time = time();

        $DB->insert_record('reader_levels', $data);
    }
    add_to_log($course->id, 'reader', substr("AA-Student Level Changed ({$userid} {$slevel} to {$changelevel})", 0, 39), 'admin.php?id='.$id, $cm->instance);
    if ($ajax == 'true') {
        $studentlevel = $DB->get_record('reader_levels', array('userid' => $userid,  'readerid' => $reader->id));
        echo reader_selectlevel_form($userid, $studentlevel, $slevel);
        //echo "set {$changelevel}";
        die;
    }
}

if (has_capability('mod/reader:manage', $contextmodule) && $sctionoption == 'massdifficultychange' && (isset($difficulty) || $difficulty == 0) && (isset($todifficulty) || $todifficulty == 0) && isset($publisher)) {
    if ($reader->bookinstances == 0) {
        $DB->set_field('reader_books',  'difficulty',  $todifficulty, array('difficulty' => $difficulty,  'publisher' => $publisher));
    } else {
        $data = $DB->get_records('reader_books', array('publisher' => $publisher));
        foreach ($data as $key => $value) {
            $difficultystring .= $value->id.',';
        }
        $difficultystring = substr($difficultystring, 0, -1);
        $DB->execute('UPDATE {reader_book_instances} SET difficulty = ? WHERE difficulty = ? and readerid = ? and bookid IN (?)', array($todifficulty,$difficulty,$reader->id,$difficultystring));
    }
    add_to_log($course->id, 'reader', substr("AA-Mass changes difficulty ({$difficulty} to {$todifficulty})", 0, 39), 'admin.php?id='.$id, $cm->instance);
}

if (has_capability('mod/reader:manage', $contextmodule) && $level && $tolevel && $publisher) {
    $DB->set_field('reader_books',  'level',  $tolevel, array('level' => $level,  'publisher' => $publisher));
    add_to_log($course->id, 'reader', substr("AA-Mass changes level ({$level} to {$tolevel})", 0, 39), 'admin.php?id='.$id, $cm->instance);
}

if (has_capability('mod/reader:manage', $contextmodule) && $topublisher && $publisher) {
    $DB->set_field('reader_books',  'publisher',  $topublisher, array('publisher' => $publisher));
    add_to_log($course->id, 'reader', substr("AA-Mass changes publisher ({$publisher} to {$topublisher})", 0, 39), 'admin.php?id='.$id, $cm->instance);
}

if (has_capability('mod/reader:manage', $contextmodule) && $length && $tolength && $publisher) {
    if ($reader->bookinstances == 0) {
        $DB->set_field('reader_books',  'length',  $tolength, array('length' => $length,  'publisher' => $publisher));
    } else {
        $data = $DB->get_records('reader_books', array('publisher' => $publisher));
        foreach ($data as $key => $value) {
            $lengthstring .= $value->id.',';
        }
        $lengthstring = substr($lengthstring, 0, -1);
        $DB->execute('UPDATE {reader_book_instances} SET length = ? WHERE length = ? and readerid = ? and bookid IN (?)', array($tolength,$length,$reader->id,$lengthstring));
    }
    add_to_log($course->id, 'reader', substr("AA-Mass changes length ({$length} to {$tolength})", 0, 39), 'admin.php?id='.$id, $cm->instance);
}



if (has_capability('mod/reader:manage', $contextmodule) && $act == 'changereaderlevel' && ($difficulty || $difficulty == 0) && empty($length)) {
  if ($reader->bookinstances == 0) {
    $params = array('id' => $bookid);
    if ($DB->get_record('reader_books', $params)) {
        $DB->set_field('reader_books',  'difficulty',  $difficulty, $params);
    }
  } else {
    $params = array('readerid' => $reader->id, 'bookid' => $bookid);
    if ($DB->get_record('reader_book_instances', $params)) {
        $DB->set_field('reader_book_instances',  'difficulty',  $difficulty, $params);
    }
    add_to_log($course->id, 'reader', substr("AA-Change difficulty individual ({$bookid} {$slevel} to {$difficulty})", 0, 39), 'admin.php?id='.$id, $cm->instance);
  }
  if ($ajax == 'true') {
      $book = $DB->get_record('reader_books', array('id' => $bookid));
      echo reader_select_difficulty_form(reader_get_reader_difficulty($reader, $bookid), $book->id, $reader);
      die;
  }
}



if (has_capability('mod/reader:manage', $contextmodule) && $act == 'changereaderlevel' && $length) {
  if ($reader->bookinstances == 0) {
    if ($DB->get_record('reader_books', array('id' => $bookid))) {
        $DB->set_field('reader_books',  'length',  $length, array('id' => $bookid));
    }
    add_to_log($course->id, 'reader', substr("AA-Change length ({$bookid} {$slevel} to {$length})",0,39), 'admin.php?id='.$id, $cm->instance);
  } else {
    if ($DB->get_record('reader_book_instances', array('readerid' => $reader->id, 'bookid' => $bookid))) {
        $DB->set_field('reader_book_instances',  'length',  $length, array('readerid' => $reader->id,  'bookid' => $bookid));
    }
    add_to_log($course->id, 'reader', substr("AA-Change length individual ({$bookid} {$slevel} to {$length})",0,39), 'admin.php?id='.$id, $cm->instance);
  }
  if ($ajax == 'true') {
      $book = $DB->get_record('reader_books', array('id' => $bookid));
      echo reader_select_length_form(reader_get_reader_length($reader, $bookid), $book->id, $reader);
      die;
  }
}



if (has_capability('mod/reader:viewstudentreaderscreens', $contextmodule) && $viewasstudent > 1) {
    $_SESSION['SESSION']->reader_changetostudentview = $USER->id;
    $_SESSION['SESSION']->reader_changetostudentviewlink = "gid={$gid}&searchtext={$searchtext}&page={$page}&sort={$sort}&orderby={$orderby}";
    $USER = $DB->get_record('user', array('id' => $viewasstudent));
    unset($_SESSION['SESSION']->reader_teacherview);
    header("Location: view.php?a=quizzes&id=".$id);
}



if (has_capability('mod/reader:manage', $contextmodule) && $act == 'studentslevels' && $setgoal) {
    if ($data = $DB->get_record('reader_levels', array('userid' => $userid, 'readerid' => $reader->id))) {
        $DB->set_field('reader_levels',  'goal',  $setgoal, array('id' => $data->id));
    } else {
        $data = new object;
        $data->userid = $userid;
        $data->startlevel = 0;
        $data->currentlevel = 0;
        $data->readerid = $reader->id;
        $data->goal = $setgoal;
        $data->time = time();

        $DB->insert_record('reader_levels', $data);
    }
    add_to_log($course->id, 'reader', "AA-Change Student Goal ({$setgoal})", 'admin.php?id='.$id, $cm->instance);
    if ($ajax == 'true') {
        $data = $DB->get_record('reader_levels', array('id' => $data->id));
        echo reader_goal_box($userid, $data, 'goal', 3, $reader);
        die;
    }
}



if (has_capability('mod/reader:manage', $contextmodule) && isset($nopromote) && $userid) {
    if ($DB->get_record('reader_levels', array('userid' => $userid, 'readerid' => $reader->id))) {
        $DB->set_field('reader_levels',  'nopromote',  $nopromote, array('userid' => $userid,  'readerid' => $reader->id));
    }
    add_to_log($course->id, 'reader', substr("AA-Student NoPromote Changed ({$userid} set to {$nopromote})",0,39), 'admin.php?id='.$id, $cm->instance);
    if ($ajax == 'true') {
        $studentlevel = $DB->get_record('reader_levels', array('userid' => $userid,  'readerid' => $reader->id));
        echo reader_yes_no_box($userid, $studentlevel, 'nopromote', 1);
        die;
    }
}



if (has_capability('mod/reader:manage', $contextmodule) && isset($promotionstop) && $userid) {
    if ($DB->get_record('reader_levels', array('userid' => $userid, 'readerid' => $reader->id))) {
        $DB->set_field('reader_levels',  'promotionstop',  $promotionstop, array('userid' => $userid,  'readerid' => $reader->id));
    }
    add_to_log($course->id, 'reader', substr("AA-Student Promotion Stop Changed ({$userid} set to {$promotionstop})",0,39), 'admin.php?id='.$id, $cm->instance);
    if ($ajax == 'true') {
        //echo "set {$promotionstop}";
        $studentlevel = $DB->get_record('reader_levels', array('userid' => $userid,  'readerid' => $reader->id));
        echo reader_promotion_stop_box($userid, $studentlevel, 'promotionstop', 2);
        die;
    }
}



if (has_capability('mod/reader:manage', $contextmodule) && $setip) {
    if ($DB->get_record('reader_strict_users_list', array('userid' => $userid, 'readerid' => $reader->id))) {
        $DB->set_field('reader_strict_users_list',  'needtocheckip',  $needip, array('userid' => $userid,  'readerid' => $reader->id));
    } else {
        $data = new object;
        $data->userid = $userid;
        $data->readerid = $reader->id;
        $data->needtocheckip = $needip;

        $DB->insert_record('reader_strict_users_list', $data);
    }
    add_to_log($course->id, 'reader', substr("AA-Student check ip Changed ({$userid} {$needip})",0,39), 'admin.php?id='.$id, $cm->instance);
    if ($ajax == 'true') {
        echo reader_selectip_form($userid, $reader);
        die;
    }
}



if (has_capability('mod/reader:manage', $contextmodule) && $changeallstartlevel >= 0) {
    foreach ($coursestudents as $coursestudent) {
        if ($DB->get_record('reader_levels', array('userid' => $coursestudent->id, 'readerid' => $reader->id))) {
            $DB->set_field('reader_levels',  'startlevel',  $changeallstartlevel, array('userid' => $coursestudent->id,  'readerid' => $reader->id));
        } else {
            $data = new object;
            $data->startlevel = $changeallstartlevel;
            $data->currentlevel = $changeallstartlevel;
            $data->userid = $coursestudent->id;
            $data->readerid = $reader->id;
            $data->time = time();

            $DB->insert_record('reader_levels', $data);
        }
        add_to_log($course->id, 'reader', substr("AA-changeallstartlevel userid: {$coursestudent->id}, startlevel={$changeallstartlevel}",0,39), 'admin.php?id='.$id, $cm->instance);
    }
}

if (has_capability('mod/reader:manage', $contextmodule) &&  $changeallcurrentlevel >= 0) {
    foreach ($coursestudents as $coursestudent) {
        if ($DB->get_record('reader_levels', array('userid' => $coursestudent->id, 'readerid' => $reader->id))) {
            $DB->set_field('reader_levels',  'currentlevel',  $changeallcurrentlevel, array('userid' => $coursestudent->id,  'readerid' => $reader->id));
        } else {
            $data = new object;
            $data->startlevel = $changeallcurrentlevel;
            $data->currentlevel = $changeallcurrentlevel;
            $data->userid = $coursestudent->id;
            $data->readerid = $reader->id;
            $data->time = time();

            $DB->insert_record('reader_levels', $data);
        }
        add_to_log($course->id, 'reader', substr("AA-changeallcurrentlevel userid: {$coursestudent->id}, currentlevel={$changeallcurrentlevel}",0,39), 'admin.php?id='.$id, $cm->instance);
    }
}



if (has_capability('mod/reader:manage', $contextmodule) && $changeallpromo) {
    foreach ($coursestudents as $coursestudent) {
        if ($DB->get_record('reader_levels', array('userid' => $coursestudent->id, 'readerid' => $reader->id))) {
            if (strtolower($changeallpromo) == 'promo') {$nopromote = 0;} else {$nopromote = 1;}
            $DB->set_field('reader_levels',  'nopromote',  $nopromote, array('userid' => $coursestudent->id,  'readerid' => $reader->id));
        }
        add_to_log($course->id, 'reader', substr("AA-Student Promotion Stop Changed ({$coursestudent->id} set to {$promotionstop})",0,39), 'admin.php?id='.$id, $cm->instance);
    }
}

if (has_capability('mod/reader:manage', $contextmodule) && $changeallstoppromo >= 0 && $gid) {
    foreach ($coursestudents as $coursestudent) {
        if ($DB->get_record('reader_levels', array('userid' => $coursestudent->id, 'readerid' => $reader->id))) {
            $DB->set_field('reader_levels',  'promotionstop',  $changeallstoppromo, array('userid' => $coursestudent->id,  'readerid' => $reader->id));
        }
        add_to_log($course->id, 'reader', substr("AA-Student NoPromote Changed ({$coursestudent->id} set to {$changeallstoppromo})",0,39), 'admin.php?id='.$id, $cm->instance);
    }
}



if (has_capability('mod/reader:manage', $contextmodule) && $changeallcurrentgoal) {
    foreach ($coursestudents as $coursestudent) {
        if ($data = $DB->get_record('reader_levels', array('userid' => $coursestudent->id, 'readerid' => $reader->id))) {
            $DB->set_field('reader_levels',  'goal',  $changeallcurrentgoal, array('id' => $data->id));
        } else {
            $data = new object;
            $data->userid = $coursestudent->id;
            $data->startlevel = 0;
            $data->currentlevel = 0;
            $data->readerid = $reader->id;
            $data->goal = $changeallcurrentgoal;
            $data->time = time();

            $DB->insert_record('reader_levels', $data);
        }
        add_to_log($course->id, 'reader', substr("AA-goal userid: {$coursestudent->id}, goal={$changeallcurrentgoal}",0,39), 'admin.php?id='.$id, $cm->instance);
    }
}

if (has_capability('mod/reader:manage', $contextmodule) && $act == 'awardextrapoints' && $award && $student) {
    $useridold = $USER->id;
    if ($bookdata = $DB->get_record('reader_books', array('name' => $award))) {
        foreach ($student as $student_) {

            $select = 'MAX(attempt)';
            $from   = '{reader_attempts}';
            $where  = 'reader = ? AND userid = ? AND timefinish > ? AND preview != ?';
            $params = array($reader->id, $student_, 0, 1);

            if($attemptnumber = $DB->get_field_sql("SELECT $select FROM $from WHERE $where", $params)) {
                $attemptnumber += 1;
            } else {
                $attemptnumber = 1;
            }

            $USER->id = $student_;

            $attempt = reader_create_attempt($reader, $attemptnumber, $bookdata->id);
            $attempt->ip = getremoteaddr();
            // Save the attempt
            if (! $attempt->id = $DB->insert_record('reader_attempts', $attempt)) {
                throw new reader_exception('Could not create new attempt');
            }

            $totalgrade = 0;
            $answersgrade = $DB->get_records('reader_question_instances', array('quiz' => $bookdata->quizid)); // Count Grades (TotalGrade)
            foreach ($answersgrade as $answersgrade_) {
                $totalgrade += $answersgrade_->grade;
            }

            $attemptnew               = new object;
            $attemptnew->id           = $attempt->id;
            $attemptnew->sumgrades    = $totalgrade;
            $attemptnew->percentgrade      = 100;
            $attemptnew->passed       = 'true';

            //if ($reader->attemptsofday != 0) {
                $attemptnew->timefinish   = time() - $reader->attemptsofday * 3600 * 24;
                $attemptnew->timecreated  = time() - $reader->attemptsofday * 3600 * 24;
                $attemptnew->timemodified = time() - $reader->attemptsofday * 3600 * 24;
            //}

            $DB->update_record('reader_attempts', $attemptnew);
            add_to_log($course->id, 'reader', "AWP (userid: {$student_}; set: {$award})", 'admin.php?id='.$id, $cm->instance);
        }
    }
    $USER->id = $useridold;
}



if (has_capability('mod/reader:manage', $contextmodule) && $cheated) {
    list($cheated1, $cheated2) = explode('_', $cheated);
    $DB->set_field('reader_attempts',  'passed',  'cheated', array('id' => $cheated1));
    $DB->set_field('reader_attempts',  'passed',  'cheated', array('id' => $cheated2));
    add_to_log($course->id, 'reader', 'AA-cheated', 'admin.php?id='.$id, $cm->instance);

    $userid1 = $DB->get_record('reader_attempts', array('id' => $cheated1));
    $userid2 = $DB->get_record('reader_attempts', array('id' => $cheated2));

    $data = new object;
    $data->byuserid  = $USER->id;
    $data->userid1   = $userid1->userid;
    $data->userid2   = $userid2->userid;
    $data->attempt1  = $cheated1;
    $data->attempt2  = $cheated2;
    $data->courseid  = $course->id;
    $data->readerid  = $reader->id;
    $data->quizid    = $userid1->quizid;
    $data->status    = 'cheated';
    $data->date      = time();

    //print_r($data);

    $DB->insert_record('reader_cheated_log', $data);

    if ($reader->sendmessagesaboutcheating == 1) {
        $user1 = $DB->get_record('user', array('id' => $userid1->userid));
        $user2 = $DB->get_record('user', array('id' => $userid2->userid));
        email_to_user($user1,get_admin(),'Cheated notice',$reader->cheated_message);
        email_to_user($user2,get_admin(),'Cheated notice',$reader->cheated_message);
    }
}



if (has_capability('mod/reader:manage', $contextmodule) && $uncheated) {
    list($cheated1, $cheated2) = explode('_', $uncheated);
    $DB->set_field('reader_attempts',  'passed',  'true', array('id' => $cheated1));
    $DB->set_field('reader_attempts',  'passed',  'true', array('id' => $cheated2));
    add_to_log($course->id, 'reader', "AA-set passed (uncheated)", 'admin.php?id='.$id, $cm->instance);

    $userid1 = $DB->get_record('reader_attempts', array('id' => $cheated1));
    $userid2 = $DB->get_record('reader_attempts', array('id' => $cheated2));

    $data = new object;
    $data->byuserid  = $USER->id;
    $data->userid1   = $userid1->userid;
    $data->userid2   = $userid2->userid;
    $data->attempt1  = $cheated1;
    $data->attempt2  = $cheated2;
    $data->courseid  = $course->id;
    $data->readerid  = $reader->id;
    $data->quizid    = $userid1->quizid;
    $data->status    = 'passed';
    $data->date      = time();

    $DB->insert_record('reader_cheated_log', $data);

    if ($reader->sendmessagesaboutcheating == 1) {
        $user1 = $DB->get_record('user', array('id' => $userid1->userid));
        $user2 = $DB->get_record('user', array('id' => $userid2->userid));
        email_to_user($user1,get_admin(),'Points restored notice',$reader->not_cheated_message);
        email_to_user($user2,get_admin(),'Points restored notice',$reader->not_cheated_message);
    }
}



if (has_capability('mod/reader:manage', $contextmodule) && $act == 'setgoal') {
    if ($wordsorpoints) {
        $DB->set_field('reader',  'wordsorpoints',  $wordsorpoints, array('id' => $reader->id));
    }
    if (! $levelall) {
        if (empty($levelc)) {
            $levelc = array();
        }
        $value = 0;
        foreach ($levelc as $key => $value) {
            if ($value) {
                $data              = new stdClass();
                $data->groupid     = (empty($separategroups) ? 0 : $separategroups);
                $data->readerid    = $reader->id;
                $data->level       = $key;
                $data->goal        = $value;
                $data->changedate  = time();
                $dataid = $DB->insert_record('reader_goal', $data);
            }
        }
        add_to_log($course->id, 'reader', "AA-wordsorpoints goal=$value", 'admin.php?id='.$id, $cm->instance);
    } else {
        $DB->delete_records('reader_goal', array('readerid' => $reader->id));
        if ($separategroups) {
            $data              = new object;
            $data->groupid     = $separategroups;
            $data->readerid    = $reader->id;
            $data->level       = 0;
            $data->goal        = $levelall;
            $data->changedate  = time();
            $DB->insert_record('reader_goal', $data);
        } else {
            $DB->set_field('reader', 'goal', $levelall);
        }
        add_to_log($course->id, 'reader', "AA-wordsorpoints goal=$levelall", 'admin.php?id='.$id, $cm->instance);
    }
}

if (has_capability('mod/reader:manage', $contextmodule) && $act == 'setbookinstances' && is_array($quiz)) {
    $DB->delete_records('reader_book_instances', array('readerid' => $reader->id));
    foreach ($quiz as $quiz_) {
        $oldbookdata = $DB->get_record('reader_books', array('id' => $quiz_));
        $data           = new object;
        $data->readerid = $reader->id;
        $data->bookid   = $quiz_;
        $data->difficulty   = $oldbookdata->difficulty;
        $data->length   = $oldbookdata->length;
        //print_r($data);
        $DB->insert_record('reader_book_instances', $data);
    }
}

if (has_capability('mod/reader:manage', $contextmodule) && $act == 'forcedtimedelay' && is_array($levelc)) {
    $DB->delete_records('reader_forcedtimedelay', array('readerid' => $reader->id, 'groupid' => $separategroups));
    foreach ($levelc as $key => $value) {
      if ($value != 0) {
        $data             = new object;
        $data->readerid   = $reader->id;
        $data->groupid    = $separategroups;
        $data->level      = $key;
        $data->delay      = $value;
        $data->changedate = time();
        $DB->insert_record('reader_forcedtimedelay', $data);
      }
    }
}

if (has_capability('mod/reader:manage', $contextmodule) && $book && is_array($noquizuserid)) {
    foreach ($noquizuserid as $key => $value) {
      if ($value != 0) {
        $lastattemptid = $DB->get_field_sql('SELECT uniqueid FROM {reader_attempts} ORDER BY uniqueid DESC');
        $data               = new object;
        $data->uniqueid     = $lastattemptid + 1;
        $data->reader       = $reader->id;
        $data->userid       = $value;
        $data->attempt      = 1;
        $data->sumgrades    = 1;
        $data->passed       = 'true';
        $data->percentgrade      = 100;
        $data->timestart    = time();
        $data->timefinish   = time();
        $data->timemodified = time();
        $data->layout       = '0,';
        $data->preview      = 1;
        $data->quizid       = $book;
        $data->bookrating   = 1;
        $data->ip           = $_SERVER['REMOTE_ADDR'];

        $DB->insert_record('reader_attempts', $data);
      }
    }

    $noquizreport = 'Done';

    unset($book);
}

if ((has_capability('mod/reader:downloadquizzesfromthereaderquizdatabase', $contextmodule)) && $numberofsections && $act == 'changenumberofsectionsinquiz') {
    if ($reader->usecourse) {
        $DB->set_field('course',  'numsections',  $numberofsections, array('id' => $reader->usecourse));
    }
}

if ($act == 'adjustscores' && !empty($adjustscoresaddpoints) && !empty($adjustscoresupbooks)) {
    foreach ($adjustscoresupbooks as $key => $value) {
        $data = $DB->get_record('reader_attempts', array('id' => $value));
        $newpoint = $data->percentgrade + $adjustscoresaddpoints;
        $passed = (($newpoint >= $reader->percentforreading) ? 'true' : 'false');
        $attempt = new stdClass();
        $attempt->passed  = $passed;
        $attempt->percentgrade = $newpoint;
        $attempt->id      = $value;
        $DB->update_record('reader_attempts', $attempt);
    }

    $adjustscorestext = 'Done';
}

if ($act == 'adjustscores' && !empty($adjustscoresupall) && !empty($adjustscorespand) && !empty($adjustscorespby)) {
    $dataarr = $DB->get_records_sql('SELECT * FROM {reader_attempts} WHERE percentgrade < ? AND percentgrade > ? AND quizid = ?', array($adjustscorespand, $adjustscoresupall, $book));

    foreach ($dataarr as $ida) {
        $data = $DB->get_record('reader_attempts', array('id' => $ida->id));
        $newpoint = $data->percentgrade + $adjustscorespby;
        $passed = (($newpoint >= $reader->percentforreading) ? 'true' : 'false');
        $attempt = new object;
        $attempt->passed  = $passed;
        $attempt->percentgrade = $newpoint;
        $attempt->id      = $ida->id;
        $DB->update_record('reader_attempts', $attempt);
    }
    $adjustscorestext = 'Done';
}

/// Print the page header

$navigation = build_navigation('', $cm);

if ($excel) {
    $workbook = new MoodleExcelWorkbook('-');
    $worksheet = $workbook->add_worksheet('report');

    $formatbold = $workbook->add_format();
    $formatbold->set_bold(1);

    $formatdate = $workbook->add_format();
    $formatdate->set_num_format(get_string('log_excel_date_format'));

    if (empty($gid)) {
        $grname = 'all';
    } else {
        $grname = groups_get_group_name($gid);
    }

    $exceldata['time'] = date($dateformat); // was 'd.M.Y'
    $exceldata['course_shotname'] = str_replace(' ', '-', $course->shortname);
    $exceldata['groupname'] = str_replace(' ', '-', $grname);

    if ($act == 'exportstudentrecords') {
        $filename = $COURSE->shortname.'_attempts.txt';
        header('Content-Type: text/plain; filename="'.$filename.'"');
        $workbook->send($filename);
    } else {
        $filename = 'report_'.$exceldata['time'].'_'.$exceldata['course_shotname'].'_'.$exceldata['groupname'].'.xls';
        $workbook->send($filename);
    }
    add_to_log($course->id, 'reader', 'AA-excel', 'admin.php?id='.$id, $cm->instance);
}

// Initialize $PAGE, compute blocks
$PAGE->set_url('/mod/reader/admin.php', array('id' => $cm->id));

$title = $course->shortname . ': ' . format_string($reader->name);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

if (! $excel) {
    //print_header_simple(format_string($reader->name), "", $navigation, "", "", true, update_module_button($cm->id, $course->id, get_string('modulename', 'reader')), navmenu($course, $cm));
    echo $OUTPUT->header();

    echo '<script type="text/javascript" src="js/ajax.js"></script>'."\n";
    echo '<script type="application/x-javascript" src="js/jquery-1.4.2.min.js"></script>'."\n";
}

$alreadyansweredbooksid = array();

if (has_capability('mod/reader:manage', $contextmodule)) {
    if (! $excel) {
        require_once('tabs.php');
    }
} else {
    die;
}

if (! $excel) {
    echo $OUTPUT->box_start('generalbox');
}

if (isset($message_forteacher)) {
    echo $message_forteacher;
}

if (! $excel) {
    $menu = array(
        'readerreports' => array(
            new reader_menu_item('reportquiztoreader', 'readerviewreports', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'reports')),
            new reader_menu_item('fullreportquiztoreader', 'readerviewreports', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'fullreports')),
            new reader_menu_item('summaryreportbyclassgroup', 'readerviewreports', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'reportbyclass')),
            new reader_menu_item('summaryreportbybooktitle', 'readerviewreports', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'summarybookreports')),
            new reader_menu_item('fullreportbybooktitle', 'readerviewreports', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'fullbookreports')),
        ),
        'quizmanagement' => array(
            // new reader_menu_item($displaystring, $capability, $scriptname, $scriptparams)
            new reader_menu_item('addquiztoreader', 'addcoursequizzestoreaderquizzes', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'addquiz')),
            new reader_menu_item('uploadquiztoreader', 'downloadquizzesfromthereaderquizdatabase', 'dlquizzes.php', array('id'=>$id)),
            new reader_menu_item('uploaddatanoquizzes', 'downloadquizzesfromthereaderquizdatabase', 'dlquizzesnoq.php', array('id'=>$id)),
            new reader_menu_item('updatequizzes', 'manage', 'updatecheck.php', array('id'=>$id, 'checker'=>1)),
            new reader_menu_item('editquiztoreader', 'deletequizzes', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'editquiz')),
            new reader_menu_item('setbookinstances', 'selectquizzestomakeavailabletostudents', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'setbookinstances')),
            new reader_menu_item('forcedtimedelay', 'forcedtimedelay', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'forcedtimedelay')),
            new reader_menu_item('changenumberofsectionsinquiz', 'downloadquizzesfromthereaderquizdatabase', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'changenumberofsectionsinquiz')),
        ),
        'attemptscoremanagement' => array(
            new reader_menu_item('viewattempts', 'viewanddeleteattempts', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'viewattempts')),
            new reader_menu_item('awardextrapoints', 'awardextrapoints', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'awardextrapoints')),
            new reader_menu_item('assignpointsbookshavenoquizzes', 'changestudentslevelsandpromote', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'assignpointsbookshavenoquizzes')),
            new reader_menu_item('adjustscores', 'manage', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'adjustscores')),
            new reader_menu_item('checksuspiciousactivity', 'checklogsforsuspiciousactivity', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'checksuspiciousactivity')),
            new reader_menu_item('viewlogsuspiciousactivity', 'readerviewreports', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'viewlogsuspiciousactivity')),
        ),
        'booklevelmanagement' => array(
            // new reader_menu_item($displaystring, $capability, $scriptname, $scriptparams)
            new reader_menu_item('changereaderlevel', 'changereadinglevelorlengthfactor', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'changereaderlevel')),
            //new reader_menu_item('createcoversets_t', 'createcoversetsbypublisherlevel', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'makepix_t')),
            //new reader_menu_item('createcoversets_l', 'createcoversetsbypublisherlevel', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'makepix_l')),
            new reader_menu_item('bookratingslevel', 'readerviewreports', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'bookratingslevel')),
        ),
        'studentmanagement' => array(
            // new reader_menu_item($displaystring, $capability, $scriptname, $scriptparams)
            new reader_menu_item('setgoal', 'setgoal', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'setgoal')),
            new reader_menu_item('studentslevels', 'changestudentslevelsandpromote', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'studentslevels')),
            new reader_menu_item('sendmessage', 'sendmessage', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'sendmessage')),
            new reader_menu_item('exportstudentrecords', 'userdbmanagement', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'exportstudentrecords', 'excel'=>1)),
            new reader_menu_item('importstudentrecord', 'userdbmanagement', 'admin.php', array('a'=>'admin', 'id'=>$id, 'act'=>'importstudentrecord')),
        ),
    );
    $menu = new reader_menu($menu);
    echo $menu->out($contextmodule);

    echo '<br /><hr />';

    echo html_writer::start_tag('form', array('method'   => 'get',
                                              'onsubmit' => "this.target='_top'; return true;",
                                              'action'   => $CFG->wwwroot.'/course/mod.php'));
    echo html_writer::start_tag('div');
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'update', 'value' => $cm->id));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'return', 'value' => 'true'));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
    echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => 'Change the main Reader settings'));
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('form');

    if ($readercfg->update == 1) {
        if (time() - $readercfg->last_update > $readercfg->update_interval) {
          echo $OUTPUT->box_start('generalbox');
          $days = round((time() - $readercfg->last_update) / (24 * 3600));
          print_string('needtocheckupdates', 'reader', $days);
          echo ' <a href="updatecheck.php?id='.$id.'">YES</a> / <a href="admin.php?a=admin&id='.$id.'">NO</a></center>';
          echo $OUTPUT->box_end();
        }
    }
}

$options = array(
    'a'       => $a,
    'id'      => $id,
    'act'     => $act,
    'sort'    => $sort,
    'orderby' => $orderby,
    'gid'     => $gid,
    'ct'      => $ct,
    'excel'   => 1,
    'searchtext' => $searchtext,
);

if ($act == 'addquiz' && has_capability('mod/reader:addcoursequizzestoreaderquizzes', $contextmodule)) {
    if (! $quizzesid) {
        if ($quizdata  = get_all_instances_in_course('quiz', $DB->get_record('course', array('id' => $reader->usecourse)), NULL, true)) {
        //if ($quizdata  = get_records('quiz')) {
            $existdata['publisher'][0]  = get_string('selectalreadyexist', 'reader');
            $existdata['difficulty'][0] = get_string('selectalreadyexist', 'reader');
            $existdata['level'][0]      = get_string('selectalreadyexist', 'reader');

            if ($publishers = $DB->get_records('reader_books')) {
                foreach ($publishers as $publishers_) {
                    $existdata['publisher'][$publishers_->publisher] = $publishers_->publisher;
                    $existdata['difficulty'][$publishers_->difficulty] = $publishers_->difficulty;
                    $existdata['level'][$publishers_->level] = $publishers_->level;
                    $existdata['quizid'][$publishers_->quizid] = $publishers_->quizid;
                }
            }
            $quizzesarray = array();
            foreach ($quizdata as $quizdata_) {
                if (! in_array($quizdata_->id, $existdata['quizid'])) {
                    $quizzesarray[$quizdata_->id] = $quizdata_->name;
                }
            }

            if ($quizzesarray) {

                echo $OUTPUT->box_start('generalbox');

                echo '<h2>'.get_string('selectquizzes', 'reader').'</h2><br />';

                echo '<form action="admin.php?a=admin&id='.$id.'" method="post" enctype="multipart/form-data">';
                echo '<table style="width:100%">';
                echo '<tr><td width="120px">';
                print_string('publisher', 'reader');
                echo '</td><td width="120px">';
                echo '<input type="text" name="publisher" value="" />';
                echo '</td><td width="160px">';
                if ($existdata['publisher']) {
                    echo '<select name="publisherex">';
                    foreach ($existdata['publisher'] as $key => $value) {
                        echo '<option value="'.$key.'">'.$value.'</option>';
                    }
                    echo '</select>';
                }
                echo '</td><td rowspan="5">';
                echo '<select size="10" multiple="multiple" name="quizzesid[]">';
                foreach ($quizzesarray as $key => $value) {
                    echo '<option value="'.$key.'">'.$value.'</option>';
                }
                echo '</select>';
                echo '</td></tr>';
                echo '<tr><td>';
                print_string('level', 'reader');
                echo '</td><td>';
                echo '<input type="text" name="level" value="" />';
                echo '</td><td>';
                if ($existdata['level']) {
                    echo '<select name="levelex">';
                    foreach ($existdata['level'] as $key => $value) {
                        echo '<option value="'.$key.'">'.$value.'</option>';
                    }
                    echo '</select>';
                }
                echo '</td></tr>';
                echo '<tr><td>';
                print_string('readinglevel', 'reader');
                echo '</td><td>';
                echo '<input type="text" name="difficulty" value="" />';
                echo '</td><td>';
                if ($existdata['difficulty']) {
                    echo '<select name="difficultyex">';
                    foreach ($existdata['difficulty'] as $key => $value) {
                        echo '<option value="'.$key.'">'.$value.'</option>';
                    }
                    echo '</select>';
                }
                echo '</td></tr>';
                echo '<tr><td>';
                print_string('lengthex11', 'reader');
                echo '</td><td>';
                echo '<input type="text" name="length" value="1" />';
                echo '</td><td>';
                echo '</td></tr>';
                echo '<tr><td>';
                print_string('image', 'reader');
                echo '</td><td colspan="2">';
                echo '<input name="userimage" type="file" />';
                echo '</td></tr>';
                echo '<tr><td>';
                print_string('ifimagealreadyexists', 'reader');
                echo '</td><td colspan="2">';
                echo '<input type="text" name="userimagename" value="" />';
                echo '</td></tr>';
                echo '<tr><td>';
                print_string('wordscount', 'reader');
                echo '</td><td colspan="2">';
                echo '<input type="text" name="wordscount" value="" />';
                echo '</td></tr>';
                echo '<tr><td>';
                print_string('private', 'reader');
                echo '</td><td colspan="2">';
                echo '<select name="private">';
                echo '<option value="0">No</option>';
                echo '<option value="'.$reader->id.'">Yes</option>';
                echo '</select>';
                echo '</td></tr>';
                echo '<tr align="center"><td colspan="4" height="60px"><input type="submit" name="submit" value="Add" /></td></tr>';
                echo '</table>';
                echo '</form>';
                echo $OUTPUT->box_end();

            } else {
                notice(get_string('noquizzesfound', 'reader'));
            }

        }
    }

} else if ($act == 'editquiz' && has_capability('mod/reader:deletequizzes', $contextmodule)) {
    if ($sort == 'username') {
        $sort = 'title';
    }
    $table = new html_table();

    $titles = array(''=>'', 'Title'=>'title', 'Publisher'=>'publisher', 'Level'=>'level', 'Reading Level'=>'rlevel', 'Length'=>'length', 'Times Quiz Taken'=>'qtaken', 'Average Points'=>'apoints', 'Options'=>'');

    $params = array('a' => 'admin', 'id' => $id, 'act' => $act);
    reader_make_table_headers($table, $titles, $orderby, $sort, $params);
    $table->align = array('center', 'left', 'left', 'center', 'center', 'center', 'center', 'center', 'center');
    $table->width = '100%';

    $books = $DB->get_records('reader_books');

    foreach ($books as $book) {

        $totalgrade = 0;
        $totalpointsaverage = 0;
        $correctpoints = 0;

        $answersgrade = $DB->get_records('reader_question_instances', array('quiz' => $book->quizid));
        foreach ($answersgrade as $answersgrade_) {
            $totalgrade += $answersgrade_->grade;
        }

        $i = 0;

        $params = array('quizid' => $book->quizid, 'reader' => $reader->id);
        if ($readerattempts = $DB->get_records('reader_attempts', $params)) {
            foreach ($readerattempts as $readerattempt) {
                $i++;
                if ($totalgrade==0) {
                    $totalpoints = 0;
                } else {
                    $totalpoints = round(($readerattempt->sumgrades / $totalgrade) * 100, 2);
                }
                $totalpointsaverage += $totalpoints;
                if ($totalpoints >= $reader->percentforreading) {
                    $correctpoints += 1;
                }
            }
        }
        if ($i==0) {
            $averagepoints = 0;
        } else {
            $averagepoints = round($totalpointsaverage / $i);
        }
        $timesoftaken = $i;

        if ($book->hidden == 1) {
            $book->name = '<font color="#666666">'.$book->name.' - hidden</font>';
        }

        $deletelink = '<a href="admin.php?a=admin&id='.$id.'&act=editquiz&deletebook='.$book->id.'" onclick="if(confirm(\'Delete this book?\')) return true; else return false;">Delete</a>';
        $table->data[] = new html_table_row(array(
            '<input type="checkbox" name="bookid['.$book->id.']" />',
            $book->name,
            $book->publisher,
            $book->level,
            reader_get_reader_difficulty($reader, $book->id),
            reader_get_reader_length($reader, $book->id),
            $timesoftaken,
            $averagepoints.'%',
            $deletelink
        ));
    }

    reader_sort_table($table, $titles, $orderby, $sort);

    if (! isset($needdeleteattemptsfirst)) {
        echo '<form action="admin.php?a=admin&id='.$id.'&act=editquiz" method="post">';
        if (isset($table) && count($table->data)) {
            echo html_writer::table($table);
        }
        echo '<input type="button" value="Select all" onclick="checkall();" /> <input type="button" value="Deselect all" onclick="checknone();" /><br /><br />';
        echo '<input type="submit" value="Hide Books" name="hidebooks" /> <input type="submit" value="UnHide Books" name="unhidebooks" />';
        echo '</form>';
    } else {
        unset($table);
        $options['excel'] = 0;

        $table = new html_table();
        $table->head = array('Id', 'Date', 'User', 'SumGrades', 'Status');
        $table->align = array('center', 'left', 'left', 'center', 'center');
        $table->width = '80%';

        foreach ($needdeleteattemptsfirst as $readerattempt) {
            if ($readerattempt->timefinish >= $reader->ignoredate) {
                $status = 'active';
            } else {
                $status = 'inactive';
            }

            $userdata = $DB->get_record('user', array('id' => $userid));

            $table->data[] = new html_table_row(array(
                $readerattempt->id,
                date($dateformat, $readerattempt->timefinish), // was 'd M Y'
                fullname($userdata),
                round($readerattempt->sumgrades, 2),
                $status
            ));
        }

        echo '<center><h3>'.get_string('needdeletethisattemptstoo', 'reader').':</h3>';

        if (count($table->data)) {
            echo html_writer::table($table);
        }

        echo '<form action="admin.php?a=admin&id='.$id.'&act=editquiz&deletebook='.$deletebook.'" method="post">';
        echo '<input type="hidden" name="deleteallattempts" value="1" />';
        echo '<input type="submit" value="Delete" />';
        echo '</form>';
        echo $OUTPUT->single_button(new moodle_url('admin.php',$options), get_string('cancel'), 'post', $options);
        echo '</center>';
    }

} else if ($act == 'reports' && has_capability('mod/reader:readerviewreports', $contextmodule)) {
    $table = new html_table();

    $titles = array(
        'Image'                => '',
        'Username'             => 'username',
        'Fullname<br />Click to view screen' => 'fullname',
        'Start level'          => 'startlevel',
        'Current level'        => 'currentlevel',
        'Taken Quizzes'        => 'tquizzes',
        'Passed<br />Quizzes'  => 'cquizzes',
        'Failed<br />Quizzes'  => 'iquizzes',
        'Total Points'         => 'totalpoints',
        'Total words<br />this term' => 'totalwordsthisterm',
        'Total words<br />all terms' => 'totalwordsallterms'
    );

    $params = array('a' => 'admin', 'id' => $id, 'act' => 'reports', 'gid' => $gid, 'searchtext' => $searchtext, 'page' => $page);
    reader_make_table_headers($table, $titles, $orderby, $sort, $params);
    $table->align = array('center', 'left', 'left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center');
    $table->width = '100%';

    if ($excel) {
        $worksheet->set_row(0, 24); // set row height
        $worksheet->write_string(0, 0, 'Summary Report by Student', $formatbold);

        $worksheet->set_row(1, 24); // set row height
        $worksheet->write_string(1, 0, 'Date: '.$exceldata['time'].'; Course name: '.$exceldata['course_shotname'].'; Group: '.$exceldata['groupname']);

        $c = 0;
        $worksheet->set_row(2, 24); // set row height
        $worksheet->write_string(2, $c++, 'Username', $formatbold);
        $worksheet->write_string(2, $c++, 'Fullname', $formatbold);
        $worksheet->write_string(2, $c++, 'Groups', $formatbold);
        $worksheet->write_string(2, $c++, 'Start level', $formatbold);
        $worksheet->write_string(2, $c++, 'Current level', $formatbold);
        $worksheet->write_string(2, $c++, 'Taken Quizzes', $formatbold);
        $worksheet->write_string(2, $c++, 'Passed Quizzes', $formatbold);
        $worksheet->write_string(2, $c++, 'Failed Quizzes', $formatbold);
        $worksheet->write_string(2, $c++, 'Total Points', $formatbold);
        $worksheet->write_string(2, $c++, 'Total words this term', $formatbold);
        $worksheet->write_string(2, $c++, 'Total words all terms', $formatbold);
    }

    if (! $gid) {
        $gid = NULL;
    }

    $groupnames = array();
    foreach ($coursestudents as $coursestudent) {
        $groupnames[$coursestudent->username] = array();
        if (reader_check_search_text($searchtext, $coursestudent)) {

            $picture = $OUTPUT->user_picture($coursestudent,array($course->id, true, 0, true));
            if ($excel) {
                if ($usergroups = groups_get_all_groups($course->id, $coursestudent->id)){
                    foreach ($usergroups as $group){
                        $groupnames[$coursestudent->username][] = $group->name;
                    }
                }
            }

            // count words in attempts
            $totalwords = array('thisterm' => 0,
                                'allterms' => 0);
            if ($readerattempts = $DB->get_records('reader_attempts', array('userid' => $coursestudent->id))) {
                foreach ($readerattempts as $readerattempt) {
                    if (strtolower($readerattempt->passed) == 'true') {
                        if ($readerattempt->preview == 0) {
                            $tablename = 'reader_books';
                        } else {
                            $tablename = 'reader_noquiz';
                        }
                        if ($books = $DB->get_records($tablename, array('quizid' => $readerattempt->quizid))) {
                            if ($book = array_shift($books)) {
                                $totalwords['allterms'] += $book->words;
                                if ($readerattempt->reader==$reader->id && $reader->ignoredate < $readerattempt->timefinish) {
                                    $totalwords['thisterm'] += $book->words;
                                }
                            }
                        }
                    }
                }
            }

            $usernamelink = reader_username_link($coursestudent, $course->id, $excel);
            if (has_capability('mod/reader:viewstudentreaderscreens', $contextmodule)) {
                $fullnamelink = reader_fullname_link_viewasstudent($coursestudent, $id, $excel);
            } else {
                $fullnamelink = reader_fullname_link($coursestudent, $course->id, $excel);
            }
            if ($readerattempt = reader_get_student_attempts($coursestudent->id, $reader)) {
                $table->data[] = new html_table_row(array(
                    $picture,
                    $usernamelink,
                    $fullnamelink,
                    $readerattempt[1]['startlevel'],
                    $readerattempt[1]['currentlevel'],
                    $readerattempt[1]['countattempts'],
                    $readerattempt[1]['correct'],
                    $readerattempt[1]['incorrect'],
                    $readerattempt[1]['totalpoints'],
                    $totalwords['thisterm'],
                    $totalwords['allterms']
                ));
            } else {
                $table->data[] = new html_table_row(array($picture, $usernamelink, $fullnamelink, 0,0,0,0,0,0,0));
            }
        }
    }

    reader_sort_table($table, $titles, $orderby, $sort);

    if ($excel) {
        foreach ($table->data as $r => $row) {
            $c = 0; // column number

            $username = strip_tags($row->cells[1]->text);
            $worksheet->write_string(3 + $r, $c++, $username);

            $fullname = strip_tags($row->cells[2]->text);
            $worksheet->write_string(3 + $r, $c++, $fullname);

            $groupname = implode(',', $groupnames[$username]);
            $worksheet->write_string(3 + $r, $c++, $groupname);

            $worksheet->write_number(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_number(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_number(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_number(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_number(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
        }
        $workbook->close();
        die;
    }

    echo '<table style="width:100%"><tr><td align="right">';
    echo $OUTPUT->single_button(new moodle_url('admin.php',$options), get_string('downloadexcel', 'reader'), 'post', $options);
    echo '</td></tr></table>';

    reader_print_search_form();

    $groups = groups_get_all_groups($course->id);

    if ($groups) {
        reader_print_group_select_box($course->id, 'admin.php?a=admin&id='.$id.'&act=reports&sort='.$sort.'&orderby='.$orderby);
    }

    reader_select_perpage($id, $act, $sort, $orderby, $gid);
    list($totalcount, $table->data, $startrec, $finishrec, $options['page']) = reader_get_pages($table->data, $page, $perpage);
    //print_paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $OUTPUT->render($pagingbar);

    if (isset($table) && count($table->data)) {
        echo html_writer::table($table);
    }

    //print_paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $OUTPUT->render($pagingbar);

} else if ($act == 'fullreports' && has_capability('mod/reader:readerviewreports', $contextmodule)) {
    $table = new html_table();

    $titles = array();
    $table->align = array();

    $titles['Image'] = '';
    $titles['Username'] = 'username';
    $titles['Fullname'] = 'fullname'; // <br />Click to view screen'
    array_push($table->align, 'center', 'left', 'left');

    if ($reader->checkbox == 1) {
        $titles['Check'] = '';
        array_push($table->align, 'center');
    }

    $titles['Date'] ='date';
    $titles['S-Level'] = 'slevel';
    $titles['B-Level'] = 'blevel';
    $titles['Title'] = 'title';
    $titles['Score'] = '';
    $titles['P/F/C'] = '';
    array_push($table->align, 'center', 'center', 'center', 'left', 'center', 'center');

    if ($reader->wordsorpoints == 'words') {
        $titles['Words'] = '';
        $titles['Total Words'] = '';
        array_push($table->align, 'center', 'center');
    } else {
        $titles['Points'] = '';
        $titles['Length'] = '';
        $titles['Total Points'] = '';
        array_push($table->align, 'center', 'center', 'center');
    }

    // '?a=admin&id='.$id.'&act=fullreports&gid='.$gid.'&searchtext='.$searchtext.'&page='.$page.'&ct='.$ct
    $params = array('a' => 'admin', 'id' => $id, 'act' => 'fullreports', 'gid' => $gid, 'searchtext' => $searchtext, 'page' => $page, 'ct' => $ct);
    reader_make_table_headers($table, $titles, $orderby, $sort, $params);
    $table->width = '100%';

    if ($excel) {
        $worksheet->set_row(0, 24); // set row height
        $worksheet->write_string(0, 0, 'Full Report by Student', $formatbold);

        $worksheet->set_row(1, 24); // set row height
        $worksheet->write_string(1, 0, 'Date: '.$exceldata['time'].'; Course name: '.$exceldata['course_shotname'].'; Group: '.$exceldata['groupname']);

        $c = 0; // column number
        $worksheet->set_row(2, 24); // set row height

        $worksheet->set_column($c, $c, 20); // set col width
        $worksheet->write_string(2, $c++, 'Username', $formatbold);

        $worksheet->set_column($c, $c, 20); // set col width
        $worksheet->write_string(2, $c++, 'Fullname', $formatbold);

        $worksheet->set_column($c, $c, 20); // set col width
        $worksheet->write_string(2, $c++, 'Groups', $formatbold);

        if ($reader->checkbox == 1) {
            $worksheet->write_string(2, $c++, 'Check', $formatbold);
        }

        $worksheet->write_string(2, $c++, 'Date', $formatbold);
        $worksheet->write_string(2, $c++, 'S-Level', $formatbold);
        $worksheet->write_string(2, $c++, 'B-Level', $formatbold);

        $worksheet->set_column($c, $c, 30); // set col width
        $worksheet->write_string(2, $c++, 'Title', $formatbold);

        $worksheet->write_string(2, $c++, 'Score', $formatbold);
        $worksheet->write_string(2, $c++, 'P/F/C', $formatbold);

        if ($reader->wordsorpoints == 'words') {
            $worksheet->write_string(2, $c++, 'Words', $formatbold);
            $worksheet->write_string(2, $c++, 'Total Words', $formatbold);
        } else {
            $worksheet->write_string(2, $c++, 'Points', $formatbold);
            $worksheet->write_string(2, $c++, 'Length', $formatbold);
            $worksheet->write_string(2, $c++, 'Total Points', $formatbold);
        }
    }

    if (! $gid) {
        $gid = NULL;
    }

    $groupnames = array();
    foreach ($coursestudents as $coursestudent) {
        $groupnames[$coursestudent->username] = array();

        $picture = $OUTPUT->user_picture($coursestudent, array($course->id, true, 0, true));
        $totable['first'] = true;

        if ($excel) {
            if ($usergroups = groups_get_all_groups($course->id, $coursestudent->id)){
                foreach ($usergroups as $group){
                    $groupnames[$coursestudent->username][] = $group->name;
                }
            }
        }

        list($readerattempts, $summaryattemptdata) = reader_get_student_attempts($coursestudent->id, $reader);
        if (empty($readerattempts)) {
            continue;
        }

        $totalwords = 0;
        foreach ($readerattempts as $readerattempt) {
            if (isset($ct) && $ct == 1 && $readerattempt['timefinish'] < $reader->ignoredate) {
                continue;
            }

            $timefinish = date($dateformat, $readerattempt['timefinish']); // was 'Y/m/d'

            if ($totable['first'] || $sort == 'slevel' || $sort == 'blevel' || $sort == 'title' || $sort == 'date' || $excel) {
                $showuser = true;
            } else {
                $showuser = false;
            }

            $strpassed = reader_format_passed($readerattempt['passed']);

            if ($reader->wordsorpoints == 'words') {
                if (reader_check_search_text($searchtext, $coursestudent, $readerattempt)) {

                    if ($strpassed=='P') { // passed
                        $totalwords +=  $readerattempt['words'];
                    }

                    if ($showuser) {
                        $linkusername = reader_username_link($coursestudent, $course->id, $excel);
                        if (has_capability('mod/reader:viewstudentreaderscreens', $contextmodule)) {
                            $linkfullname = reader_fullname_link_viewasstudent($coursestudent, $id, $excel);
                        } else {
                            $linkfullname = reader_fullname_link($coursestudent, $course->id, $excel);
                        }
                        $totable['first'] = false;
                    } else {
                        $picture = '';
                        $linkusername = '';
                        $linkfullname = '';
                    }
                    // build $cells
                    $cells = array();

                    array_push($cells,
                        $picture,
                        $linkusername,
                        $linkfullname
                    );
                    if ($reader->checkbox == 1) {
                        array_push($cells, reader_ra_checkbox($readerattempt));
                    }
                    array_push($cells,
                        $timefinish,
                        $readerattempt['userlevel'],
                        $readerattempt['bookdiff'],
                        $readerattempt['booktitle'],
                        $readerattempt['percentgrade'].'%',
                        $strpassed,
                        (is_numeric($readerattempt['words']) ? number_format($readerattempt['words']) : $readerattempt['words']),
                        (is_numeric($totalwords) ? number_format($totalwords) : $totalwords)
                    );
                    $table->data[] = new html_table_row($cells);
                }
            } else {
                if (reader_check_search_text($searchtext, $coursestudent, $readerattempt)) {
                    if ($showuser) {
                        $linkusername = reader_username_link($coursestudent, $course->id, $excel);
                        if (has_capability('mod/reader:viewstudentreaderscreens', $contextmodule)) {
                            $linkfullname = reader_fullname_link_viewasstudent($coursestudent, $id, $excel);
                        } else {
                            $linkfullname = reader_fullname_link($coursestudent, $course->id, $excel);
                        }
                        $totable['first'] = false;
                    } else {
                        $picture = '';
                        $linkusername = '';
                        $linkfullname = '';
                    }

                    $cells = array();
                    array_push($cells,
                        $picture,
                        $linkusername,
                        $linkfullname
                    );
                    if ($reader->checkbox == 1) {
                        array_push($cells, reader_ra_checkbox($readerattempt));
                    }
                    array_push($cells,
                        $timefinish,
                        $readerattempt['userlevel'],
                        $readerattempt['bookdiff'],
                        $readerattempt['booktitle'],
                        $readerattempt['percentgrade'].'%',
                        $strpassed,
                        $readerattempt['bookpoints'],
                        $readerattempt['booklength'],
                        $readerattempt['totalpoints']
                    );
                    $table->data[] = new html_table_row($cells);
                }
            }
        }
    } // end foreach $readerattempts

    if ($sort == 'slevel' || $sort == 'blevel' || $sort == 'title' || $sort == 'date') {
        reader_sort_table($table, $titles, $orderby, $sort);
    }

    if ($excel) {
        foreach ($table->data as $r => $row) {
            $c = 0; // column number

            $username = strip_tags($row->cells[1]->text);
            $worksheet->write_string(3 + $r, $c++, $username);

            $fullname = strip_tags($row->cells[2]->text);
            $worksheet->write_string(3 + $r, $c++, $fullname);

            $groupname = implode(',', $groupnames[$username]);
            $worksheet->write_string(3 + $r, $c++, $groupname);

            if ($reader->checkbox == 1) {
                $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
            }

            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text, $formatdate);
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text); // S-level
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text); // B-level
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text); // Title
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text); // Score
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text); // P/F/C

            if ($reader->wordsorpoints == 'words') {
                $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text); // Words
                $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text); // Total Words
            } else {
                $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text); // Points
                $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text); // Length
                $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text); // Total Points
            }
        }
        $workbook->close();
        die;
    }

    echo '<table style="width:100%"><tr><td align="right">';
    echo $OUTPUT->single_button(new moodle_url('admin.php',$options), get_string('downloadexcel', 'reader'), 'post', $options);
    echo '</td></tr></table>';

    reader_print_search_form();

    $groups = groups_get_all_groups($course->id);

    if ($groups) {
        reader_print_group_select_box($course->id, 'admin.php?a=admin&id='.$id.'&act=fullreports&sort='.$sort.'&orderby='.$orderby.'&ct='.$ct);
    }

    echo '<div style="text-align:right"><form action="" method="post" id="mform_ct"><select onchange="document.getElementById(\'mform_ct\').action = document.getElementById(\'mform_ct\').level.options[document.getElementById(\'mform_ct\').level.selectedIndex].value;document.getElementById(\'mform_ct\').submit(); return true;" name="level" id="id_level"><option value="admin.php?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&gid='.$gid.'&orderby='.$orderby.'&ct=0" ';
    if (empty($ct)) {
        echo ' selected="selected" ';
    }
    echo ' >All terms</option><option value="admin.php?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&gid='.$gid.'&orderby='.$orderby.'&ct=1" ';
    if (! empty($ct)) {
        echo ' selected="selected" ';
    }
    echo ' >Current term</option></select></form></div>';

    reader_select_perpage($id, $act, $sort, $orderby, $gid);
    list($totalcount, $table->data, $startrec, $finishrec, $options['page']) = reader_get_pages($table->data, $page, $perpage);
    //print_paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&ct={$ct}&amp;");
    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&ct={$ct}&amp;");
    echo $OUTPUT->render($pagingbar);

    if (isset($table) && count($table->data)) {
        echo html_writer::table($table);
    }

    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&ct={$ct}&amp;");
    echo $OUTPUT->render($pagingbar);

} else if ($act == 'summarybookreports' && has_capability('mod/reader:readerviewreports', $contextmodule)) {
    if ($sort == 'username') {
        $sort = 'title';
    }
    $table = new html_table();
    $titles = array('Title'=>'title', 'Publisher'=>'publisher', 'Level'=>'level', 'Reading Level'=>'rlevel', 'Length'=>'length', 'Times Quiz Taken'=>'qtaken', 'Average Points'=>'apoints', 'Passed'=>'passed', 'Failed'=>'failed', 'Pass Rate'=>'prate');

    $params = array('a' => 'admin', 'id' => $id, 'act' => 'summarybookreports', 'gid' => $gid, 'searchtext' => $searchtext, 'page' => $page);
    reader_make_table_headers($table, $titles, $orderby, $sort, $params);
    $table->align = array('left', 'left', 'center', 'center', 'center', 'center', 'center', 'center', 'center', 'center');
    $table->width = '100%';

    if ($excel) {
        $worksheet->set_row(0, 24); // set row height
        $worksheet->write_string(0, 0, 'Summary Report by Book Title', $formatbold);

        $worksheet->set_row(1, 24); // set row height
        $worksheet->write_string(1, 0, 'Date: '.$exceldata['time'].'; Course name: '.$exceldata['course_shotname'].'; Group: '.$exceldata['groupname']);

        $c = 0;
        $worksheet->set_row(2, 24); // set row height
        $worksheet->write_string(2, $c++, 'Title', $formatbold);
        $worksheet->write_string(2, $c++, 'Publisher', $formatbold);
        $worksheet->write_string(2, $c++, 'Level', $formatbold);
        $worksheet->write_string(2, $c++, 'Reading Level', $formatbold);
        $worksheet->write_string(2, $c++, 'Length', $formatbold);
        $worksheet->write_string(2, $c++, 'Times Quiz Taken', $formatbold);
        $worksheet->write_string(2, $c++, 'Average Points', $formatbold);
        $worksheet->write_string(2, $c++, 'Passed', $formatbold);
        $worksheet->write_string(2, $c++, 'Failed', $formatbold);
        $worksheet->write_string(2, $c++, 'Pass Rate', $formatbold);
    }

    $select = 'hidden = ? AND private IN (0, ?)';
    $params = array(0, $reader->id);
    if ($books = $DB->get_records_select('reader_books', $select, $params)) {
        foreach ($books as $book) {
            if (reader_check_search_text($searchtext, '', $book)) {
                $totalgrade = 0;
                $totalpointsaverage = 0;
                $correctpoints = 0;
                $i = 0;
                if ($readerattempts = $DB->get_records('reader_attempts', array('quizid' => $book->quizid))) {
                    foreach ($readerattempts as $readerattempt) {
                        $i++;
                        $totalpointsaverage += $readerattempt->percentgrade;
                        if (strtolower($readerattempt->passed) == 'true') {
                            $correctpoints += 1;
                        }
                    }
                }
                if ($i) {
                  $averagepoints = round($totalpointsaverage / $i);
                  $prate         = round(($correctpoints/$i) * 100);
                } else {
                  $averagepoints = 0;
                  $prate         = 0;
                }

                $timesoftaken = $i;
                $params = array('b' => $book->id, 'id' => $id, 'q'=> $book->quizid, 'b' => $book->id);
                $bookreportlink = html_writer::tag('a', $book->name, array('href' => new moodle_url('/mod/reader/report.php', $params)));
                $table->data[] = new html_table_row(array(
                    $bookreportlink,
                    $book->publisher,
                    $book->level,
                    reader_get_reader_difficulty($reader, $book->id),
                    reader_get_reader_length($reader, $book->id),
                    $timesoftaken,
                    $averagepoints.'%',
                    $correctpoints,
                    ($timesoftaken - $correctpoints),
                    $prate.'%')
                );
            }
        }
    }

    reader_sort_table($table, $titles, $orderby, $sort);

    if ($excel) {
        foreach ($table->data as $r => $row) {
            $c = 0;
            $worksheet->write_string(3 + $r, $c, strip_tags($row->cells[$c++]->text));
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
        }
        $workbook->close();
        die;
    }

    echo '<table style="width:100%"><tr><td align="right">';
    echo $OUTPUT->single_button(new moodle_url('admin.php',$options), get_string('downloadexcel', 'reader'), 'post', $options);
    echo '</td></tr></table>';

    reader_print_search_form();

    reader_select_perpage($id, $act, $sort, $orderby, $gid);
    list($totalcount, $table->data, $startrec, $finishrec, $options['page']) = reader_get_pages($table->data, $page, $perpage);
    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $OUTPUT->render($pagingbar);

    if (isset($table) && count($table->data)) {
        echo html_writer::table($table);
    }

    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $OUTPUT->render($pagingbar);

} else if ($act == 'fullbookreports' && has_capability('mod/reader:readerviewreports', $contextmodule)) {
    if ($sort == 'username') {
        $sort = 'title';
    }
    $table = new html_table();

    $titles = array('Title'=>'title', 'Publisher'=>'publisher', 'Level'=>'level', 'Reading Level'=>'rlevel', 'Student Name'=>'sname', 'Student ID'=>'studentid', 'Passed/Failed'=>'');

    if ($excel) {
        $worksheet->set_row(0, 24); // set row height
        $worksheet->write_string(0, 0, 'Full Report by Book Title', $formatbold);

        $worksheet->set_row(1, 24); // set row height
        $worksheet->write_string(1, 0, 'Date: '.$exceldata['time'].'; Course name: '.$exceldata['course_shotname'].'; Group: '.$exceldata['groupname']);

        $c = 0;
        $worksheet->set_row(2, 24); // set row height

        $worksheet->set_column($c, $c, 30); // set col width
        $worksheet->write_string(2, $c++, 'Title', $formatbold);

        $worksheet->set_column($c, $c, 20); // set width
        $worksheet->write_string(2, $c++, 'Publisher', $formatbold);

        $worksheet->write_string(2, $c++, 'Level', $formatbold);
        $worksheet->write_string(2, $c++, 'Reading Level', $formatbold);

        $worksheet->set_column($c, $c, 20); // set width
        $worksheet->write_string(2, $c++, 'Student Name', $formatbold);

        $worksheet->write_string(2, $c++, 'Student ID', $formatbold);
        $worksheet->write_string(2, $c++, 'Passed/Failed', $formatbold);
    }

    $params = array('a' => 'admin', 'id' => $id, 'act' => 'fullbookreports', 'gid' => $gid, 'searchtext' => $searchtext, 'page' => $page);
    reader_make_table_headers($table, $titles, $orderby, $sort, $params);
    $table->align = array('left', 'left', 'center', 'center', 'left', 'left', 'center');
    $table->width = '100%';

    if (! $books = $DB->get_records('reader_books')) {
        $books = array();
    }

    $groupuserfilter = '';
    if ($gid) {
        $groupuserids = array();
        if ($groupusers = groups_get_members($gid)) {
            foreach ($groupusers as $groupuser) {
                $groupuserids[] = $groupuser->id;
            }
        }
        if ($groupuserids = implode(',', $groupuserids)) {
            $groupuserfilter = ' AND ra.userid IN ('.$groupuserids.')';
        }
    }

    foreach ($books as $book) {
        if (reader_check_search_text($searchtext, '', $book)) {
            $totalgrade = 0;

            $select = 'ra.*, u.username, u.firstname, u.lastname';
            $from   = '{reader_attempts} ra INNER JOIN {user} u ON u.id = ra.userid';
            $where  = 'ra.quizid= ? AND ra.reader= ?'.$groupuserfilter;
            $params = array($book->quizid, $reader->id);
            if (! $readerattempts = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY ra.userid", $params)) {
                $readerattempts = array();
            }

            $params = array('id' => $id, 'q' => $book->quizid, 'b' => $book->id);
            $report = new moodle_url('/mod/reader/report.php', $params);

            foreach ($readerattempts as $readerattempt) {
                $table->data[] = new html_table_row(array(
                    html_writer::tag('a', $book->name, array('href' => $report)),
                    $book->publisher,
                    $book->level,
                    reader_get_reader_difficulty($reader, $book->id),
                    reader_fullname_link($readerattempt, $course->id, $excel),
                    reader_username_link($readerattempt, $course->id, $excel),
                    $readerattempt->passed));
            }
        }
    }

    reader_sort_table($table, $titles, $orderby, $sort);

    if ($excel) {
        foreach ($table->data as $r => $row) {
            $c = 0;
            $worksheet->write_string(3 + $r, $c, strip_tags($row->cells[$c++]->text));
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_string(3 + $r, $c, strip_tags($row->cells[$c++]->text));
            $worksheet->write_string(3 + $r, $c, strip_tags($row->cells[$c++]->text));
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
        }
        $workbook->close();
        die;
    }

    echo '<table style="width:100%"><tr><td align="right">';
    echo $OUTPUT->single_button(new moodle_url('admin.php',$options), get_string('downloadexcel', 'reader'), 'post', $options);
    echo '</td></tr></table>';

    reader_print_search_form();

    $groups = groups_get_all_groups($course->id);

    if ($groups) {
        reader_print_group_select_box($course->id, 'admin.php?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&orderby='.$orderby);
    }

    reader_select_perpage($id, $act, $sort, $orderby, $gid);
    list($totalcount, $table->data, $startrec, $finishrec, $options['page']) = reader_get_pages($table->data, $page, $perpage);
    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $OUTPUT->render($pagingbar);

    if (isset($table) && count($table->data)) {
        echo html_writer::table($table);
    }

    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $OUTPUT->render($pagingbar);

} else if ($act == 'viewattempts' && has_capability('mod/reader:viewanddeleteattempts', $contextmodule)) {

    $table = new html_table();

    if (! $searchtext && !$gid) {
      echo "<center><h2><font color=\"red\">".get_string('pleasespecifyyourclassgroup', 'reader').'</font></h2></center>';
    } else {
        if (has_capability('mod/reader:deletereaderattempts', $contextmodule)) {
            $titles = array('Username'=>'username', 'Fullname'=>'fullname', 'Book Name'=>'bname', 'AttemptID'=>'attemptid', 'Score'=>'score', 'P/F/C'=>'', 'Finishtime'=>'timefinish', 'Option' => '');
        } else {
            $titles = array('Username'=>'username', 'Fullname'=>'fullname', 'Book Name'=>'bname', 'AttemptID'=>'attemptid', 'Score'=>'score', 'P/F/C'=>'', 'Finishtime'=>'timefinish');
        }

        $params = array('a' => 'admin', 'id' => $id, 'act' => 'viewattempts', 'gid' => $gid, 'searchtext' => $searchtext, 'page' => $page);
        reader_make_table_headers($table, $titles, $orderby, $sort, $params);
        $table->align = array('left', 'left', 'left', 'center', 'center', 'center', 'center', 'center');
        $table->width = '100%';

        if ($excel) {
            $worksheet->set_row(0, 24); // set row height
            $worksheet->write_string(0, 0, 'View and Delete Attempts', $formatbold);

            //$worksheet->set_row(1, 24); // set row height
            //$worksheet->write_string(1, 0, 'Date: '.$exceldata['time'].'; Course name: '.$exceldata['course_shotname'].'; Group: '.$exceldata['groupname']);

            $c = 0;
            $worksheet->set_row(2, 24); // set row height
            $worksheet->write_string(2, $c++, 'Username', $formatbold);
            $worksheet->write_string(2, $c++, 'Fullname', $formatbold);
            $worksheet->write_string(2, $c++, 'Book Name', $formatbold);
            $worksheet->write_string(2, $c++, 'AttemptID', $formatbold);
            $worksheet->write_string(2, $c++, 'Score', $formatbold);
            $worksheet->write_string(2, $c++, 'Finishtime', $formatbold);
            $worksheet->write_string(2, $c++, 'P/F/C', $formatbold);
        }

        $select = 'ra.id,ra.timefinish,ra.userid,ra.attempt,ra.percentgrade,ra.passed,'.
                  'rb.name,rb.publisher,rb.level,'.
                  'u.username,u.firstname,u.lastname';
        $from   = '{reader_attempts} ra '.
                  'LEFT JOIN {user} u ON ra.userid = u.id '.
                  'LEFT JOIN {reader_books} rb ON ra.quizid = rb.quizid';
        $where  = '';
        $params = null;

        if ($searchtext) {
            if (strstr($searchtext, '"')) {
                $texts = explode('"', str_replace('\"', '"', $searchtext));
            } else {
                $texts = explode(' ', $searchtext);
            }
            $where  = array();
            foreach ($texts as $text) {
                if ($text && strlen($text) > 3) {
                    $where[] = "u.username LIKE '%$text%'";
                    $where[] = "u.firstname LIKE '%{$text}%'";
                    $where[] = "u.lastname LIKE '%{$text}%'";
                    $where[] = "rb.name LIKE '%{$text}%'";
                    $where[] = "rb.level LIKE '%{$text}%'";
                    $where[] = "rb.publisher LIKE '%{$text}%'";
                }
            }
            $where = implode(' OR ', $where);
        } else if ($gid) {
            $groupuserids = array();
            $groupusers = groups_get_members($gid);
            foreach ($groupusers as $groupuser) {
                $groupuserids[] = $groupuser->id;
            }
            if ($groupuserids = implode(',', $groupuserids)) {
                $where = 'ra.userid IN ('.$groupuserids.')';
            }
        }
        if ($where) {
            if ($orderby=='' || strtoupper($orderby)=='ASC') {
                $ASC_DESC = ' ASC';
            } else {
                $ASC_DESC = ' DESC';
            }
            switch ($sort) {
                case 'username'   : $where .= " ORDER BY u.username $ASC_DESC"; break;
                case 'fullname'   : $where .= " ORDER BY u.firstname $ASC_DESC, u.lastname $ASC_DESC"; break;
                case 'bname'      : $where .= " ORDER BY rb.name $ASC_DESC"; break;
                case 'attemptid'  : $where .= " ORDER BY ra.id $ASC_DESC"; break;
                case 'score'      : $where .= " ORDER BY ra.percentgrade $ASC_DESC"; break;
                case 'timefinish' : $where .= " ORDER BY ra.timefinish $ASC_DESC"; break;
                default           : $where .= " ORDER BY u.username $ASC_DESC"; break;
            }
            $limitfrom = 0;
            $limitnum = 1000;
            $readerattempts = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params, $limitfrom, $limitnum);
        } else {
            $readerattempts = false;
        }

        if (! $readerattempts) {
            $readerattempts = array();
        }

        $can_deleteattempts = has_capability('mod/reader:deletereaderattempts', $contextmodule);

        foreach ($readerattempts as $readerattempt) {
            $attemptbooktime = date($dateformat, $readerattempt->timefinish); // was 'Y/m/d'

            $strpassed = reader_format_passed($readerattempt->passed);
            $cells = array(
                reader_username_link($readerattempt, $course->id, $excel),
                reader_fullname_link($readerattempt, $course->id, $excel),
                $readerattempt->name,
                $readerattempt->attempt,
                $readerattempt->percentgrade.'%',
                $strpassed,
                $attemptbooktime
            );
            if ($can_deleteattempts) {
                $params = array('a' => 'admin', 'id' => $id, 'act' => 'viewattempts',
                                'page' => $page, 'sort' => $sort, 'orderby' => $orderby,
                                'attemptid' => $readerattempt->id);
                $alert = 'Quiz attempt';
                if ($fullname = implode(' ', array($readerattempt->firstname, $readerattempt->lastname))) {
                    $alert .= " by '$fullname'"; // user's full name
                }
                if ($readerattempt->name) {
                    $alert .= " at '$readerattempt->name'"; // book name
                }
                $alert .= ' has been deleted';
                $params = array('href' => new moodle_url('/mod/reader/admin.php', $params), 'onclick' => 'alert("'.$alert.'")');
                array_push($cells, html_writer::tag('a', 'Delete', $params));
            }
            $table->data[] = new html_table_row($cells);
        }

        reader_sort_table($table, $titles, $orderby, $sort);

        if ($excel) {
            foreach ($table->data as $row) {
                $c = 0;
                $worksheet->write_string(3 + $r, $c, strip_tags($row->cells[$c++]->text));
                $worksheet->write_string(3 + $r, $c, strip_tags($row->cells[$c++]->text));
                $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
                $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
                $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
                $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
                $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
            }
            $workbook->close();
            die;
        }

        echo '<table style="width:100%"><tr><td align="right">';
        echo $OUTPUT->single_button(new moodle_url('admin.php',$options), get_string('downloadexcel', 'reader'), 'post', $options);
        echo '</td></tr></table>';
    }
    reader_print_search_form();

    $groups = groups_get_all_groups($course->id);

    if ($groups) {
        reader_print_group_select_box($course->id, 'admin.php?a=admin&id='.$id.'&act=viewattempts&sort='.$sort.'&orderby='.$orderby);
    }

    reader_select_perpage($id, $act, $sort, $orderby, $gid);
    list($totalcount, $table->data, $startrec, $finishrec, $options['page']) = reader_get_pages($table->data, $page, $perpage);
    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $OUTPUT->render($pagingbar);

    if (isset($table) && count($table->data)) {
        echo html_writer::table($table);
    }

    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $OUTPUT->render($pagingbar);

    if (has_capability('mod/reader:deletequizzes', $contextmodule)) {
      echo '<form action="?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&orderby='.$orderby.'&gid='.$gid.'" method="post"><div> ';
      echo ' <div style="margin:20px 0;font-size:16px;">'.get_string('restoredeletedattempt', 'reader').'</div>';
      echo '<div style="float:left;width:200px;">'.get_string('studentuserid', 'reader').'</div>';
      echo '<div style="float:left;width:200px;"><input type="text" name="studentuserid" value="" style="width:120px;" /></div><div style="clear:both;"></div>';
      echo '<div>or</div>';
      echo '<div style="float:left;width:200px;">'.get_string('studentusername', 'reader').'</div>';
      echo '<div style="float:left;width:200px;"><input type="text" name="studentusername" value="" style="width:120px;" /></div><div style="clear:both;"></div>';
      echo '<div style="float:left;width:200px;">'.get_string('bookquiznumber', 'reader').'</div>';
      echo '<div style="float:left;width:200px;"><input type="text" name="bookquiznumber" value="" style="width:120px;" /></div><div style="clear:both;"></div>';
      //echo ' <input type="hidden" name="" value="" />';
      echo ' <input type="submit" name="submit" value="Restore" />';
      echo '</div></form>';
    }

  //}

} else if ($act == 'studentslevels' && has_capability('mod/reader:changestudentslevelsandpromote', $contextmodule)) {

    $table = new html_table();

    $titles = array('Image'=>'', 'Username'=>'username', 'Fullname<br />Click to view screen'=>'fullname', 'Start level'=>'startlevel', 'Current level'=>'currentlevel', 'NoPromote'=>'nopromote', 'Stop Promo At'=>'promotionstops', 'Goal'=>'goal');

    if ($reader->individualstrictip == 1) {
        $titles['Restrict IP'] = '';
    }

    $params = array('a' => 'admin', 'id' => $id, 'act' => $act, 'gid' => $gid, 'searchtext' => $searchtext, 'page' => $page);
    reader_make_table_headers($table, $titles, $orderby, $sort, $params);
    $table->align = array('center', 'left', 'left', 'center', 'center', 'center', 'center', 'center', 'center');
    $table->width = '100%';

    if (! $gid) {
        $gid = NULL;
    }

    foreach ($coursestudents as $coursestudent) {
        if (reader_check_search_text($searchtext, $coursestudent)) {
            $readerlevel = $DB->get_record('reader_levels', array('userid' => $coursestudent->id, 'readerid' => $reader->id));
            $picture = $OUTPUT->user_picture($coursestudent,array($course->id, true, 0, true));

            if (empty($readerlevel)) {
                $readerlevel = (object)array(
                    'id'            => 0,
                    'userid'        => $coursestudent->id,
                    'readerid'      => $reader->id,
                    'startlevel'    => 0,
                    'currentlevel'  => 0,
                    'nopromote'     => 0,
                    'promotionstop' => 0,
                    'goal'          => null,
                    'time'          => time()
                );
            }

            if (has_capability('mod/reader:viewstudentreaderscreens', $contextmodule)) {
                $linkfullname = reader_fullname_link_viewasstudent($coursestudent, $id, $excel);
            } else {
                $linkfullname = reader_fullname_link($coursestudent, $course->id, $excel);
            }

            $cells = array(
                $picture,
                reader_username_link($coursestudent, $course->id, $excel),
                $linkfullname,
                reader_selectlevel_form($coursestudent->id, $readerlevel, 'startlevel'),
                reader_selectlevel_form($coursestudent->id, $readerlevel, 'currentlevel'),
                reader_yes_no_box($coursestudent->id, $readerlevel, 'nopromote', 1),
                reader_promotion_stop_box($coursestudent->id, $readerlevel, 'promotionstop', 2),
                reader_goal_box($coursestudent->id, $readerlevel, 'goal', 3, $reader)
            );
            if ($reader->individualstrictip == 1) {
                $cells[] = reader_selectip_form($coursestudent->id, $reader);
            }
            $table->data[] = new html_table_row($cells);
        }
    }

    reader_sort_table($table, $titles, $orderby, $sort);

    if ($gid) {
        $levels = array(0,1,2,3,4,5,6,7,8,9,10);
        echo '<form action="?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&orderby='.$orderby.'&gid='.$gid.'" method="post"><div> ';
        print_string('changestartlevel', 'reader');
        echo ' <select name="changeallstartlevel">';
        foreach ($levels as $value) {
            echo '<option value="'.$value.'">'.$value.'</option>';
        }
        echo '</select> ';
        //echo ' <input type="hidden" name="" value="" />';
        echo ' <input type="submit" name="submit" value="Change" />';
        echo '</div></form>';

        $levels = array(0,1,2,3,4,5,6,7,8,9,10,11,12,13,14);
        echo '<form action="?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&orderby='.$orderby.'&gid='.$gid.'" method="post"><div> ';
        print_string('changecurrentlevel', 'reader');
        echo ' <select name="changeallcurrentlevel">';
        foreach ($levels as $value) {
            echo '<option value="'.$value.'">'.$value.'</option>';
        }
        echo '</select> ';
        //echo ' <input type="hidden" name="" value="" />';
        echo ' <input type="submit" name="submit" value="Change" />';
        echo '</div></form>';

        //Points goal for all students
        $levels = array(1,2,3,4,5,6,7,8,9,10,11,12,13,14,15);
        echo '<form action="?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&orderby='.$orderby.'&gid='.$gid.'" method="post"><div> ';
        print_string('setuniformgoalinpoints', 'reader');
        echo ' <select name="changeallcurrentgoal">';
        foreach ($levels as $value) {
            echo '<option value="'.$value.'">'.$value.'</option>';
        }
        echo '</select> ';
        //echo ' <input type="hidden" name="" value="" />';
        echo ' <input type="submit" name="submit" value="Change" />';
        echo '</div></form>';

        //Words goal for all students
        $levels = array(0,5000,6000,7000,8000,9000,10000,12500,15000,20000,25000,30000,35000,40000,45000,50000,55000,60000,65000,70000,75000,80000,85000,90000,95000,100000,125000,150000,175000,200000,250000,300000,350000,400000,450000,500000);
        if (! in_array($reader->goal, $levels) && !empty($reader->goal)) {
            for ($i=0; $i<count($levels); $i++) {
                if ($reader->goal < $levels[$i+1] && $reader->goal > $levels[$i]) {
                    $levels2[] = $reader->goal;
                    $levels2[] = $levels[$i];
                } else {
                    $levels2[] = $levels[$i];
                }
            }
            $levels = $levels2;
        }
        echo '<form action="?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&orderby='.$orderby.'&gid='.$gid.'" method="post"><div> ';
        print_string('setuniformgoalinwords', 'reader');
        echo ' <select name="changeallcurrentgoal">';
        foreach ($levels as $value) {
            echo '<option value="'.$value.'">'.$value.'</option>';
        }
        echo '</select> ';
        //echo ' <input type="hidden" name="" value="" />';
        echo ' <input type="submit" name="submit" value="Change" />';
        echo '</div></form>';

        //"NoPromo"/"Promo" for all students
        $levels = array('Promo', 'NoPromo');
        echo '<form action="?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&orderby='.$orderby.'&gid='.$gid.'" method="post"><div> ';
        print_string('changeallto', 'reader');
        echo ' <select name="changeallpromo">';
        foreach ($levels as $value) {
            echo '<option value="'.$value.'">'.$value.'</option>';
        }
        echo '</select> ';
        //echo ' <input type="hidden" name="" value="" />';
        echo ' <input type="submit" name="submit" value="Change" />';
        echo '</div></form>';

        //Stop Promo for all students
        $levels = array(0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15);
        echo '<form action="?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&orderby='.$orderby.'&gid='.$gid.'" method="post"><div> ';
        print_string('changeallstoppromoto', 'reader');
        echo ' <select name="changeallstoppromo">';
        foreach ($levels as $value) {
            echo '<option value="'.$value.'">'.$value.'</option>';
        }
        echo '</select> ';
        //echo ' <input type="hidden" name="" value="" />';
        echo ' <input type="submit" name="submit" value="Change" />';
        echo '</div></form>';
    }

    reader_print_search_form();

    $groups = groups_get_all_groups($course->id);

    if ($groups) {
        reader_print_group_select_box($course->id, 'admin.php?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&orderby='.$orderby);
    }

    reader_select_perpage($id, $act, $sort, $orderby, $gid);
    list($totalcount, $table->data, $startrec, $finishrec, $options['page']) = reader_get_pages($table->data, $page, $perpage);
    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $OUTPUT->render($pagingbar);

    if (isset($table) && count($table->data)) {
        echo html_writer::table($table);
    }

    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $OUTPUT->render($pagingbar);

} else if ($act == 'changereaderlevel' && has_capability('mod/reader:changereadinglevelorlengthfactor', $contextmodule)) {
    //$reader->bookinstances = 1;
    $table = new html_table();

    if ($reader->bookinstances == 1) {
      $titles = array('Title'=>'title', 'Publisher'=>'publisher', 'Level'=>'level', 'Reading Level'=>'readinglevel', 'Length'=>'length');
    } else {
      $titles = array('Title'=>'title', 'Publisher'=>'publisher', 'Level'=>'level', 'Words'=>'words', 'Reading Level'=>'readinglevel', 'Length'=>'length');
    }

    $params = array('a' => 'admin', 'id' => $id, 'act' => $act, 'gid' => $gid, 'searchtext' => $searchtext, 'page' => $page, 'publisher' => $publisher);
    reader_make_table_headers($table, $titles, $orderby, $sort, $params);
    if ($reader->bookinstances == 1) {
      $table->align = array('left', 'left', 'left', 'center', 'center');
    } else {
      $table->align = array('left', 'left', 'left', 'center', 'center', 'center', 'center');
    }
    $table->width = '100%';

    if ($publisher && $level) {
        $perpage = 1000;
    }

    if ($reader->bookinstances == 0) {
        $books = $DB->get_records('reader_books', array('hidden' => 0));
    } else {
        $books = $DB->get_records_sql('SELECT * FROM {reader_book_instances} ib INNER JOIN {reader_books} rp ON rp.id = ib.bookid AND ib.readerid= ? ', array($reader->id));
    }

    $totalgrade = 0;
    $totalpointsaverage = 0;
    $correctpoints = 0;

    foreach ($books as $book) {
      if (reader_check_search_text_quiz($searchtext, $book)) {
        if ((empty($publisher) || $publisher == $book->publisher) && (empty($level) || $level == $book->level)) {

            $has_capability = has_capability('mod/reader:manage', $contextmodule);
            $wordstitle     = reader_ajax_textbox_title($has_capability, $book, 'words', $id, $act);
            $leveltitle     = reader_ajax_textbox_title($has_capability, $book, 'level', $id, $act);
            $publishertitle = reader_ajax_textbox_title($has_capability, $book, 'publisher', $id, $act);

            $difficultyform = trim(reader_select_difficulty_form(reader_get_reader_difficulty($reader, $book->id), $book->id, $reader));
            $lengthform = trim(reader_select_length_form(reader_get_reader_difficulty($reader, $book->id), $book->id, $reader));

            if ($reader->bookinstances == 1) {
                $table->data[] = new html_table_row(array($book->name, $publishertitle, $leveltitle, $difficultyform, $lengthform));
            } else {
                $table->data[] = new html_table_row(array($book->name, $publishertitle, $leveltitle, $wordstitle, $difficultyform, $lengthform));
            }
        }
      }
    }

    if ($sort == 'username') {
        $sort = 'title';
    }

    reader_sort_table($table, $titles, $orderby, $sort);

    $publishers = $DB->get_records('reader_books', null, 'publisher', 'DISTINCT publisher');

    $publisherform = array();
    $publisherform[$CFG->wwwroot.'/mod/reader/admin.php?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&orderby='.$orderby] = 'Select Publisher';
    foreach ($publishers as $publisher_) {
        $publisherform[$CFG->wwwroot.'/mod/reader/admin.php?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&orderby='.$orderby.'&publisher='.$publisher_->publisher] = $publisher_->publisher;
    }

    if ($publisher) {
        print_string('massrename', 'reader');
        echo ':';
    }

    echo '<form action="" method="get"  id="publisherselect"><div>';

    echo '<select id="publisher_select" name="publisher" onchange="self.location=document.getElementById(\'publisherselect\').publisher.options[document.getElementById(\'publisherselect\').publisher.selectedIndex].value;">';
    foreach ($publisherform as $key => $value) {
        echo '<option value="'.$key.'" ';
        if ($publisher == $value) {
            echo ' selected="selected" ';
        }
        echo '>'.$value.'</option>';
    }
    echo '</select>';

    if ($publisher) {
        $levelform[$CFG->wwwroot.'/mod/reader/admin.php?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&orderby='.$orderby.'&publisher='.$publisher] = 'Select Level';
        $levels = $DB->get_records('reader_books', array('publisher' => $publisher), 'level');
        foreach ($levels as $levels_) {
            if (! $level || $levels_->level == $level) {
                $level_[$levels_->level] = $levels_->id;
                $difficulty_[$levels_->difficulty] = $levels_->id;
                $length_[$levels_->length] = $levels_->id;
            }
            $levelform[$CFG->wwwroot.'/mod/reader/admin.php?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&orderby='.$orderby.'&publisher='.$publisher.'&level='.$levels_->level] = $levels_->level;
        }

        echo '<select id="level_select" name="level" onchange="self.location=document.getElementById(\'publisherselect\').level.options[document.getElementById(\'publisherselect\').level.selectedIndex].value;">';
        foreach ($levelform as $key => $value) {
            echo '<option value="'.$key.'" ';
            if ($level == $value) {
                echo ' selected="selected" ';
            }
            echo '>'.$value.'</option>';
        }
        echo '</select>';
    }

    echo '</div></form>';

    if ($publisher) {
        if ($reader->bookinstances == 1) {
            unset($difficulty_,$length_);
            $data = $DB->get_records_sql('SELECT ib.difficulty as ibdifficulty,ib.length as iblength FROM {reader_books} rp INNER JOIN {reader_book_instances} ib ON ib.bookid = rp.id WHERE ib.readerid= ?  and rp.publisher = ? ', array($reader->id, $publisher));
            foreach ($data as $data_) {
                $difficulty_[$data_->ibdifficulty] = $data_->bookid;
                $length_[$data_->iblength] = $data_->bookid;
            }
        }

        echo '<form action="" method="post"><div> ';
        print_string('changepublisherfrom', 'reader');
        echo ' <select name="publisher">';
        $pubto = array();
        foreach ($publishers as $key => $value) {
          if (! in_array($value->publisher, $pubto)) {
            $pubto[] = $value->publisher;
            echo '<option value="'.$value->publisher.'" ';
            if ($publisher == $value->publisher) {
                echo ' selected="selected" ';
            }
            echo '>'.$value->publisher.'</option>';
          }
        }
        echo '</select> ';
        print_string('to', 'reader');
        echo ' <input type="text" name="topublisher" />';
        echo ' <input type="submit" name="submit" value="Change" />';

        echo '</div></form>';

        echo '<form action="" method="post"><div> ';
        print_string('changelevelfrom', 'reader');
        echo ' <select name="level">';
        foreach ($level_ as $key => $value) {
            echo '<option value="'.$key.'" ';
            if ($level == $key) {
                echo ' selected="selected" ';
            }
            echo '>'.$key.'</option>';
        }
        echo '</select> ';
        print_string('to', 'reader');
        echo ' <input type="text" name="tolevel" />';
        echo ' <input type="submit" name="submit" value="Change" />';

        echo '</div></form>';

        echo '<form action="" method="post"><div> ';
        //$lengtharray = array(0.50,0.60,0.70,0.80,0.90,1.00,1.10,1.20,1.30,1.40,1.50,1.60,1.70,1.80,1.90,2.00);
        $lengtharray = array(0.50,0.60,0.70,0.80,0.90,1.00,1.10,1.20,1.30,1.40,1.50,1.60,1.70,1.80,1.90,2.00,3.00,4.00,5.00,6.00,7.00,8.00,9.00,10.00,15,20,25,30,35,40,45,50,55,60,65,70,75,80,85,90,95,100,110,120,130,140,150,160,170,175,180,190,200,225,250,275,300,350,400);
        print_string('changelengthfrom', 'reader');
        echo ' <select name="length">';
        ksort($length_);
        reset($length_);
        foreach ($length_ as $key => $value) {
            echo '<option value="'.$key.'" ';
            if ($length == $key) {
                echo ' selected="selected" ';
            }
            echo '>'.$key.'</option>';
        }
        echo '</select> ';
        print_string('to', 'reader');
        echo ' <select name="tolength">';
        foreach ($lengtharray as $value) {
            echo '<option value="'.$value.'">'.$value.'</option>';
        }
        echo '</select>';
        echo ' <input type="submit" name="submit" value="Change" />';

        echo '</div></form>';

        echo '<form action="" method="post"><div> ';
        $difficultyarray = array(0,1,2,3,4,5,6,7,8,9,10,11,12);
        print_string('changedifficultyfrom', 'reader');
        echo ' <select name="difficulty">';
        ksort($difficulty_);
        reset($difficulty_);
        foreach ($difficulty_ as $key => $value) {
            echo '<option value="'.$key.'" ';
            if ($difficulty == $key) {
                echo ' selected="selected" ';
            }
            echo '>'.$key.'</option>';
        }
        echo '</select> ';
        print_string('to', 'reader');
        echo ' <select name="todifficulty">';
        foreach ($difficultyarray as $value) {
            echo '<option value="'.$value.'">'.$value.'</option>';
        }
        echo '</select>';
        echo ' <input type="submit" name="submit" value="Change" />';
        echo ' <input type="hidden" name="sctionoption" value="massdifficultychange" />';

        echo '</div></form>';
    }

    list($totalcount, $table->data, $startrec, $finishrec, $options['page']) = reader_get_pages($table->data, $page, $perpage);
    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&publisher={$publisher}&amp;");
    echo $OUTPUT->render($pagingbar);
    if (isset($table) && count($table->data)) {
        echo html_writer::table($table);
    }

    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&publisher={$publisher}&amp;");
    echo $OUTPUT->render($pagingbar);

} else if ($act == 'sendmessage' && has_capability('mod/reader:sendmessage', $contextmodule)) {

    /**
     * mod_reader_message_form
     *
     * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     * @since      Moodle 2.0
     * @package    mod
     * @subpackage reader
     */
    class mod_reader_message_form extends moodleform {

        /**
         * definition
         *
         * @uses $CFG
         * @uses $course
         * @uses $editmessage
         * @todo Finish documenting this function
         */
        function definition() {
            global $CFG, $DB, $course, $editmessage;

            $mform    = &$this->_form;

            $groups = groups_get_all_groups($course->id);
            $grouparray = array('0' => 'All Course students');

            foreach ($groups as $group) {
                $grouparray[$group->id] = $group->name;
            }

            $timearray = array('168' => '1 Week', '240' => '10 Days', '336' => '2 Weeks', '504' => '3 Weeks', '1000000' => 'Indefinite');

            $mform->addElement('select', 'groupid', 'Group', $grouparray, 'size="5" multiple');
            $mform->addElement('select', 'activehours', 'Active Time (Hours)', $timearray);
            $mform->addElement('textarea', 'text', 'Text', 'wrap="virtual" rows="10" cols="70"');

            if ($editmessage) {
                if ($message = $DB->get_record('reader_messages', array('id' => $editmessage))) {
                    $mform->setDefault('text', $message->text);
                    $mform->addElement('hidden', 'editmessage', $editmessage);
                }
            }

            $this->add_action_buttons(false, $submitlabel='Send');
        }
    }
    $mform = new mod_reader_message_form("admin.php?a=admin&id={$id}&act=sendmessage");
    $mform->display();

    echo 'Current Messages:';

    $textmessages = $DB->get_records_sql('SELECT * FROM {reader_messages} where teacherid = ? and instance = ? ORDER BY timemodified DESC', array($USER->id, $cm->instance));

    foreach ($textmessages as $textmessage) {
        $before = $textmessage->timebefore - time();

        $forgroupsarray = explode(',', $textmessage->users);

        $forgroup = "";
        $bgcolor  = '';

        foreach ($forgroupsarray as $forgroupsarray_) {
            if ($forgroupsarray_ == 0) {
                $forgroup .= 'All, ';
            } else {
                $forgroup .= groups_get_group_name($forgroupsarray_).', ';
            }
        }

        $forgroup = substr($forgroup, 0, -2);

        if ($textmessage->timemodified > (time() - ( 48 * 60 * 60))) {
            $bgcolor = 'bgcolor="#CCFFCC"';
        }

        echo '<table width="100%"><tr><td align="right"><table cellspacing="0" cellpadding="0" class="forumpost blogpost blog" '.$bgcolor.' width="90%">';
        echo '<tr><td align="left"><div style="margin-left: 10px;margin-right: 10px;">'."\n";
        echo format_text($textmessage->text);
        echo '<div style="text-align:right"><small>';
        echo round($before/(60 * 60 * 24), 2).' Days; ';
        echo 'Added: '.date("$dateformat $timeformat", $textmessage->timemodified).'; '; // was 'd M Y H:i'
        echo 'Group: '. $forgroup.'; ';
        echo '<a href="admin.php?a=admin&id='.$id.'&act=sendmessage&editmessage='.$textmessage->id.'">Edit</a> / <a href="admin.php?a=admin&id='.$id.'&act=sendmessage&deletemessage='.$textmessage->id.'">Delete</a>';
        echo '</small></div>';
        echo '</div></td></tr></table></td></tr></table>'."\n\n";
    }

} else if ($act == 'makepix_t' && has_capability('mod/reader:createcoversetsbypublisherlevel', $contextmodule)) {
    $allbooks = $DB->get_records_sql('SELECT * FROM {reader_books}  where hidden = 0 ORDER BY publisher ASC, level ASC');
    $prehtml = "<img width='110'  height='160'  border='0' src='{$CFG->wwwroot}/file.php/reader/images/";
    $posthtml = '/> ';
    $nowpub = "";
    $nowlevel = "";
    $cellcount = -1;
    echo "<table border='0'";
    foreach ($allbooks as $thisbook) {
        if (($thisbook->publisher != $nowpub) || ($thisbook->level != $nowlevel) ){
            //close the row unless the row was closed because the last row was full
            if ($cellcount > 0) {
                echo "</td></tr>";
            }
            $nowpub = $thisbook->publisher;
            $nowlevel = $thisbook->level ;
            echo "<tr ><td colspan='6' align='left'><font size=+2>$nowpub &nbsp; $nowlevel</font></td> </tr>";
            //let's make the cpu rest for 1 sec between sets so that the server isn't overloaded with requests
            echo "<tr valign='top' align='center'>";
            sleep(1);
            $cellcount = 0;
        }
        $thistitle = $thisbook->name;
        if ($cellcount > 5) {
            $cellcount = 1;
            echo "</td></tr> <tr valign='top' align='center'><td $prehtml" . "$thisbook->image" . "'$posthtml" . "  <br />  $thistitle  </td>";
        } else {
            $cellcount++;
            echo "<td>" . "$prehtml" . "$thisbook->image". "'$posthtml". "<br />  $thistitle </td>";
        }

    }
    echo "</tr></table>";

} else if ($act == 'makepix_l' && has_capability('mod/reader:createcoversetsbypublisherlevel', $contextmodule)) {
    $allbooks = $DB->get_records_sql('SELECT * FROM {reader_books}  where hidden = 0 ORDER BY difficulty, publisher, level, name');
    $prehtml = "<img width='110'  height='160'  border='0' src='{$CFG->wwwroot}/file.php/reader/images/";
    $posthtml = '/> ';
    $nowpub = "";
    $nowlevel = "";
    $nowdiff = '999';
    $cellcount = -1;
    echo "<table border='0'";
    foreach ($allbooks as $thisbook) {

        if (($thisbook->publisher != $nowpub) || ($thisbook->level != $nowlevel) ){
            if($nowdiff != $thisbook->difficulty) {
                echo "<tr><td bgcolor='#999999' colspan ='6'><font size='5'>Reading Level: " . "$thisbook->difficulty" . "</font></td></tr>";
                $nowdiff = $thisbook->difficulty;
            }
            //close the row unless the row was closed because the last row was full
            if ($cellcount > 0) {
                echo "</td></tr>";
            }
            $nowpub = $thisbook->publisher;
            $nowlevel = $thisbook->level ;
            echo "<tr ><td colspan='6' align='left'><font size=+2>$nowpub &nbsp; $nowlevel</font></td> </tr>";
            //let's make the cpu rest for 1 sec between sets so that the server isn't overloaded with requests
            echo "<tr valign='top' align='center'>";
            sleep(1);
            $cellcount = 0;
        }
        $thistitle = $thisbook->name;
        if ($cellcount > 5) {
            $cellcount = 1;
            echo "</td></tr> <tr valign='top' align='center'><td $prehtml" . "$thisbook->image" . "'$posthtml" . "  <br />  $thistitle  </td>";
        } else {
            $cellcount++;
            echo '<td>' . "$prehtml" . "$thisbook->image". "'$posthtml". "<br />  $thistitle </td>";
        }

    }
    echo "</tr></table>";

} else if ($act == 'awardextrapoints' && has_capability('mod/reader:awardextrapoints', $contextmodule)) {
    $table = new html_table();

    $groups = groups_get_all_groups($course->id);

    $_SESSION['SESSION']->reader_perpage = 1000;

    if ($groups) {
        reader_print_group_select_box($course->id, 'admin.php?a=admin&id='.$id.'&act=awardextrapoints&sort='.$sort.'&orderby='.$orderby);
    }

    if ($gid) {
      if ($award && $student) {
        echo "<center><h2><font color=\"red\">Done</font></h2></center>";
      } else {
        echo '<form action="" method="post"><table width="100%"><tr><td align="right"><input type="button" value="Select all" onclick="checkall();" /> <input type="button" value="Deselect all" onclick="uncheckall();" /></td></tr></table>';
        $titles = array('Image'=>'', 'Username'=>'username', 'Fullname'=>'fullname', 'Select Students'=>'');

        $params = array('a' => 'admin', 'id' => $id, 'act' => 'awardextrapoints', 'gid' => $gid);
        reader_make_table_headers($table, $titles, $orderby, $sort, $params);
        $table->align = array('center', 'left', 'left', 'center');
        $table->width = '100%';

        foreach ($coursestudents as $coursestudent) {
            $picture = $OUTPUT->user_picture($coursestudent,array($course->id, true, 0, true));
            $table->data[] = new html_table_row(array(
                $picture,
                reader_username_link($coursestudent, $course->id, $excel),
                reader_fullname_link($coursestudent, $course->id, $excel),
                '<input type="checkbox" name="student[]" value="'.$coursestudent->id.'" />'));
        }

        reader_sort_table($table, $titles, $orderby, $sort);

        if (isset($table) && count($table->data)) {
            echo html_writer::table($table);
        }
        //fixed by Tom 4 July 2010
        $awardpoints = array('0.5 pt/500 Words' => '0.5 points', '1 pt/1000 Words' => 'One point', '2 pts/2000 Words' => 'Two points', '3 pts/4000 Words' => 'Three points', '4 Pts/8000 Words' => 'Four points', '5 Pts/16000 Words' => 'Five points');
        echo '<center><select id="Award_point" name="award">';
        foreach ($awardpoints as $key => $value) {
            echo '<option value="'.$value.'">'.$key.'</option>';
        }
        echo '</select>';

        echo '<input type="submit" name="submit" value="GO!" /></center></form>';
      }
    } else {
        echo "<center><h2><font color=\"red\">".get_string('pleasespecifyyourclassgroup', 'reader')."</font></h2></center>";
    }

} else if ($act == 'checksuspiciousactivity' && has_capability('mod/reader:checklogsforsuspiciousactivity', $contextmodule)) {

    $table = new html_table();

    echo '<form action="admin.php?a='.$a.'&id='.$id.'&act='.$act.'" method="post">';
    echo get_string('checkonlythiscourse', 'reader').' <input type="checkbox" name="useonlythiscourse" value="yes" checked /><br />';
    echo get_string('withoutdayfilter', 'reader').' <input type="checkbox" name="withoutdayfilter" value="yes" /><br />';
    echo get_string('selectipmask', 'reader').' <select id="ip_mask" name="ipmask"><br />';
    $ipmaskselect = array('2' => 'xxx.xxx.', '3' => 'xxx.xxx.xxx.');
    foreach ($ipmaskselect as $key => $value) {
        echo '<option value="'.$key.'"';
        if ($key == $ipmask) {
            echo ' selected="selected" ';
        }
        echo '>'.$value.'</option>';
    }
    echo '</select><br />';
    //echo 'Other ip mask <input type="text" name="ipmaskother" value="" />';
    echo get_string('fromthistime', 'reader').' <select id="from_time" name="fromtime">';
//change by Tom 28 June 2010
    $fromtimeselect = array('86400' => '1 day',
                            '604800' => '1 week',
                            '2419200' => '1 month',
                            '5270400' => '2 months',
                            '7862400' => '3 months');
    foreach ($fromtimeselect as $key => $value) {
        echo '<option value="'.$key.'"';
        if ($key == $fromtime) {
            echo ' selected="selected" ';
        }
        echo '>'.$value.'</option>';
    }
    echo '</select><br />';
    echo get_string('maxtimebetweenquizzes', 'reader').' <select id="max_time" name="maxtime">';
    $fromtimeselect = array('900' => '15 minutes',
                            '1800' => '30 minutes',
                            '2700' => '45 minutes',
                            '3600' => '1 hour',
                            '10800' => '3 hours',
                            '21600' => '6 hours',
                            '43200' => '12 hours',
                            '86400' => '24 hours');
    foreach ($fromtimeselect as $key => $value) {
        echo '<option value="'.$key.'"';
        if ($key == $maxtime) {
            echo ' selected="selected" ';
        }
        echo '>'.$value.'</option>';
    }
    echo '</select><br />';
    echo '<input type="submit" name="findcheated" value="Go" /><br />';
    echo '</form>';

    if ($findcheated) {
        $allips = array();

        $order='l.time DESC';

        if ($useonlythiscourse) {
            $usecoursesql = "course = '{$course->id}' AND";
        }
        if ($fromtime) {
            $fromtimesql = "time > '".(time() - $fromtime)."' AND";
        }

        $select = " {$usecoursesql} {$fromtimesql} module = 'reader' and info LIKE 'readerID%; reader quiz%; %/%' ";
        $countsql = (strlen($select) > 0) ? ' WHERE '. $select : '';
        $totalcount = $DB->count_records_sql("SELECT COUNT(*) FROM {log} l $countsql");
        if ($logtext = $DB->get_records_sql("SELECT * FROM {log} l $countsql")) {
            foreach ($logtext as $logtext_) {
                if (preg_match("!reader quiz (.*?); !si",$logtext_->info,$quizid)) {
                    $quizid=$quizid[1];
                }
                if ($quizid) {
                    $allips[$quizid][$logtext_->id] = $logtext_->ip;
                }
            }
        }

        //print_r($allips);

        $comparearr = array();
        foreach ($allips as $quize => $val) {
            $checkerarray = $val;
            foreach ($val as $resultid => $resultip) {
                unset($checkerarray[$resultid]);
                //echo "$quize, $resultid, $resultip<br /><br />";
                list($ip1,$ip2,$ip3,$ip4) = explode('.',$resultip);
                if ($ipmask == 2) {
                    $ipmaskcheck = '$ip1.$ip2';
                } else {
                    $ipmaskcheck = '$ip1.$ip2.$ip3';
                }
                while (list($rid, $rip) = each($checkerarray)) {
                    if (address_in_subnet($rip, $ipmaskcheck)) {
                        $comparearr[$quize][$resultid] = $resultip;
                        $comparearr[$quize][$rid]      = $resultip;
                    }
                }
                reset($checkerarray);
            }
        }

        foreach ($comparearr as $key => $value) {
            if (count($value) <= 1) {
                unset($comparearr[$key]);
            }
        }

        $compare = array();
        foreach ($comparearr as $key => $value) {
          $f = 0;
          $countofarray = count($value);
          foreach ($value as $key1 => $value1) {
            if ($f > 0) {
              $compare[$key][$fkey]['ip2']       = $value1;
              $compare[$key][$fkey]['id2']       = $key1;

              if ($f < $countofarray - 1) {
                $compare[$key][$key1]['ip']        = $value1;
                $fkey = $key1;
              }
            } else {
              $compare[$key][$key1]['ip']        = $value1;
              $fkey = $key1;
            }
            $f++;
          }
        }

        $titles = array('Book'=>'book', 'Username 1'=>'username1', 'Username 2'=>'username2', 'IP 1'=>'', 'IP 2'=>'', 'Time 1'=>'', 'Time 2'=>'', 'Time period'=>'', 'Log text'=>'');

        $params = array('a' => 'admin', 'id' => $id, 'act' => $act);
        $table->head  = reader_make_table_headers($table, $titles, $orderby, $sort, $params);
        $table->align = array("left", "left", "left", "center", "center", "center", "center", "center", "left");
        $table->width = "100%";

        foreach ($compare as $bookid => $result) {
          foreach ($result as $key => $data) {
            if ($logtext[$key]->userid != $logtext[$data['id2']]->userid) {
              $diff = $logtext[$key]->time - $logtext[$data['id2']]->time;
              if ($diff < 0) {
                $diff = (int)substr($diff, 1);
              }
              if ($maxtime > $diff || $withoutdayfilter == 'yes') {
                $bookdata  = $DB->get_record('reader_books', array('id' => $bookid));
                $user1dta  = $DB->get_record('user', array('id' => $logtext[$key]->userid));
                $user2data = $DB->get_record('user', array('id' => $logtext[$data['id2']]->userid));
                if ($diff < 3600) {
                    $diffstring = round($diff/60)." minutes";
                } else {
                    $diffstring = round($diff/3600)." hours";
                }

                $raid1 = (int)str_replace("view.php?id=", "", $logtext[$key]->url);
                $raid2 = (int)str_replace("view.php?id=", "", $logtext[$data['id2']]->url);

                $readerattempt[1] = $DB->get_record('reader_attempts', array('id' => $raid1));
                $readerattempt[2] = $DB->get_record('reader_attempts', array('id' => $raid2));

                if ($readerattempt[1]->id && $readerattempt[2]->id) {

                    $cheatedstring = '';
                    $cheatedstring .= '<script type="text/javascript">'."\n";
                    $cheatedstring .= '//<![CDATA['."\n";
                    $cheatedstring .= '$(document).ready(function() {'."\n";
                    $cheatedstring .= '    $("#cheated-link-'.$readerattempt[1]->id.'_'.$readerattempt[2]->id.'").click(function() {'."\n";
                    $cheatedstring .= '        if(confirm("Cheated ?")) {'."\n";
                    $cheatedstring .= '            $.post("admin.php", { a: "'.$a.'", id: "'.$id.'", act: "'.$act.'", useonlythiscourse: "'.$useonlythiscourse.'",ipmask: "'.$ipmask.'", fromtime: "'.$fromtime.'", maxtime: "'.$maxtime.'", cheated: "'.$readerattempt[1]->id.'_'.$readerattempt[2]->id.'" } );'."\n";
                    $cheatedstring .= '            $("#cheated-div-'.$readerattempt[1]->id.'_'.$readerattempt[2]->id.'").html("done");'."\n";
                    $cheatedstring .= '            return false;'."\n";
                    $cheatedstring .= '        } else {'."\n";
                    $cheatedstring .= '            return false;'."\n";
                    $cheatedstring .= '        }'."\n";
                    $cheatedstring .= '    });'."\n";
                    $cheatedstring .= '});'."\n";
                    $cheatedstring .= '//]]>'."\n";
                    $cheatedstring .= '</script>'."\n";
                    $cheatedstring .= '<div id="cheated-div-'.$readerattempt[1]->id.'_'.$readerattempt[2]->id.'">'."\n";
                    $cheatedstring .= '<a href="#" id="cheated-link-'.$readerattempt[1]->id.'_'.$readerattempt[2]->id.'">cheated</a>'."\n";
                    $cheatedstring .= '</div>'."\n";

                    //echo $cheatedstring ;

                    if ($readerattempt[1]->passed != "cheated") {
                        if (strstr(strtolower($logtext[$key]->info), 'passed')) {
                            $logstatus[1] = 'passed';
                        } else {
   //change by Tom 28 June 2010
                            $logstatus[1] = 'failed';
                        }
                    } else {
                        $logstatus[1] = '<font color="red">cheated</font>';

                        $cheatedstring = '';
                        $cheatedstring .= '<script type="text/javascript">'."\n";
                        $cheatedstring .= '//<![CDATA['."\n";
                        $cheatedstring .= '$(document).ready(function() {'."\n";
                        $cheatedstring .= '    $("#cheated-link-'.$readerattempt[1]->id.'_'.$readerattempt[2]->id.'").click(function() {'."\n";
                        $cheatedstring .= '        if(confirm("Set passed ?")) {'."\n";
                        $cheatedstring .= '            $.post("admin.php", { a: "'.$a.'", id: "'.$id.'", act: "'.$act.'", useonlythiscourse: "'.$useonlythiscourse.'",ipmask: "'.$ipmask.'", fromtime: "'.$fromtime.'", maxtime: "'.$maxtime.'", uncheated: "'.$readerattempt[1]->id.'_'.$readerattempt[2]->id.'" } );'."\n";
                        $cheatedstring .= '            $("#cheated-div-'.$readerattempt[1]->id.'_'.$readerattempt[2]->id.'").html("done");'."\n";
                        $cheatedstring .= '            return false;'."\n";
                        $cheatedstring .= '        } else {'."\n";
                        $cheatedstring .= '            return false;'."\n";
                        $cheatedstring .= '        }'."\n";
                        $cheatedstring .= '    });'."\n";
                        $cheatedstring .= '});'."\n";
                        $cheatedstring .= '//]]>'."\n";
                        $cheatedstring .= '</script>'."\n";
                        $cheatedstring .= '<div id="cheated-div-'.$readerattempt[1]->id.'_'.$readerattempt[2]->id.'">'."\n";
                        $cheatedstring .= '<a href="#" id="cheated-link-'.$readerattempt[1]->id.'_'.$readerattempt[2]->id.'">Set passed</a>'."\n";
                        $cheatedstring .= '</div>'."\n";
                    }

                    if ($readerattempt[1]->passed != "cheated") {
                        if (strstr(strtolower($logtext[$data['id2']]->info), 'passed')) {
                            $logstatus[2] = 'passed';
                        } else {
    //change by Tom 28 June 2010
                            $logstatus[2] = 'failed';
                        }
                    } else {
                        $logstatus[2] = '<font color="red">cheated</font>';
                    }
                    if (! has_capability('mod/reader:checklogsforsuspiciousactivity', $contextmodule)) {
                        $cheatedstring = '';
                    }

                    $usergroups  = reader_groups_get_user_groups($user1dta->id);
                    $groupsuser1 = groups_get_group_name($usergroups[0][0]);

                    $usergroups  = reader_groups_get_user_groups($user2data->id);
                    $groupsuser2 = groups_get_group_name($usergroups[0][0]);

                    $table->data[] = new html_table_row(array($bookdata->name."<br />".$cheatedstring,
                                                            "<a href=\"{$CFG->wwwroot}/user/view.php?id={$logtext[$key]->userid}&course={$course->id}\">{$user1dta->username} ({$user1dta->firstname} {$user1dta->lastname}; group: {$groupsuser1})</a><br />".$logstatus[1],
                    "<a href=\"{$CFG->wwwroot}/user/view.php?id={$logtext[$data['id2']]->userid}&course={$course->id}\">{$user2data->username} ({$user2data->firstname} {$user2data->lastname}; group: {$groupsuser2})</a><br />".$logstatus[2],
                    link_to_popup_window("{$CFG->wwwroot}/iplookup/index.php?ip={$data['ip']}&amp;user={$logtext[$key]->userid}", $data['ip'], 440, 700, null, null, true),
                    link_to_popup_window("{$CFG->wwwroot}/iplookup/index.php?ip={$data['ip2']}&amp;user={$logtext[$data['id2']]->userid}", $data['ip2'], 440, 700, null, null, true),
                    date("D d F $timeformat", $logtext[$key]->time),
                    date("D d F $timeformat", $logtext[$data['id2']]->time),
                    $diffstring,
                    $logtext[$key]->info."<br />".$logtext[$data['id2']]->info));
                }
              }
            }
          }
        }

        reader_sort_table($table, $titles, $orderby, $sort);

        if (isset($table) && count($table->data)) {
            echo html_writer::table($table);
        }

        //echo $totalcount;
        //print_r($quizzes);
    }

} else if ($act == 'reportbyclass' && has_capability('mod/reader:readerviewreports', $contextmodule)) {
    $groups = groups_get_all_groups($course->id);

    $table = new html_table();

    $titles = array(
        'Group name'=>'groupname',
        'Students with<br /> no quizzes'=>'noquizzes',
        'Students with<br /> quizzes'=>'quizzes',
        'Percent with<br /> quizzes'=>'quizzes',
        'Average Taken<br /> Quizzes'=>'takenquizzes',
        'Average Passed<br /> Quizzes'=>'passedquizzes',
        'Average Failed<br /> Quizzes'=>'failedquizzes',
        'Average total<br /> points'=>'totalpoints',
        'Average words<br /> this term'=>'averagewordsthisterm',
        'Average words<br /> all terms'=>'averagewordsallterms'
    );

    $params = array('a' => 'admin', 'id' => $id, 'act' => $act, 'gif' => $gid, 'searchtext' => $searchtext, 'page' => $page, 'fromtime' => $fromtime);
    reader_make_table_headers($table, $titles, $orderby, $sort, $params);
    $table->align = array("left", "center", "center", "center", "center", "center", "center", "center", "center");
    $table->width = "100%";

    if ($excel) {
        $worksheet->set_row(0, 24); // set row height
        $worksheet->write_string(0, 0, 'Summary Report by Class Group', $formatbold);

        $worksheet->set_row(1, 24); // set row height
        $worksheet->write_string(1, 0, 'Date: '.$exceldata['time'].'; Course name: '.$exceldata['course_shotname']);

        $c = 0;
        $worksheet->set_row(2, 24); // set row height
        $worksheet->write_string(2, $c++, 'Group name', $formatbold);
        $worksheet->write_string(2, $c++, 'Students with no quizzes', $formatbold);
        $worksheet->write_string(2, $c++, 'Students with quizzes', $formatbold);
        $worksheet->write_string(2, $c++, 'Percent with Quizzes', $formatbold);
        $worksheet->write_string(2, $c++, 'Average Taken Quizzes', $formatbold);
        $worksheet->write_string(2, $c++, 'Average Passed Quizzes', $formatbold);
        $worksheet->write_string(2, $c++, 'Average Failed Quizzes', $formatbold);
        $worksheet->write_string(2, $c++, 'Average total points', $formatbold);
        $worksheet->write_string(2, $c++, 'Average words this term', $formatbold);
        $worksheet->write_string(2, $c++, 'Average words all terms', $formatbold);
    }

    foreach ($groups as $group) {
        unset($data);
        $data = array();
        $data['percentgrade']         = 0;
        $data['averagetaken']         = 0;
        $data['averagepassed']        = 0;
        $data['averagepoints']        = 0;
        $data['averagefailed']        = 0;
        $data['withquizzes']          = 0;
        $data['withoutquizzes']       = 0;
        $data['averagewordsthisterm'] = 0;
        $data['averagewordsallterms'] = 0;

        $coursestudents = get_enrolled_users($context, NULL, $group->id);
        foreach ($coursestudents as $coursestudent) {

            $select = 'userid= ? AND reader= ? AND timestart > ?';
            $params = array($coursestudent->id, $reader->id, $reader->ignoredate);
            if ($readerattempts = $DB->get_records_select('reader_attempts', $select, $params)) {

                $data['averagetaken'] += count($readerattempts);
                foreach ($readerattempts as $readerattempt) {

                    if (strtolower($readerattempt->passed) == 'true') {
                        $data['averagepassed']++;
                        if ($bookdata = $DB->get_record('reader_books', array('quizid' => $readerattempt->quizid))) {
                            $data['averagepoints'] += reader_get_reader_length($reader, $bookdata->id);
                            $data['averagewordsthisterm'] += $bookdata->words;
                        }
                    } else {
                        $data['averagefailed']++;
                    }
                }
                $data['withquizzes'] ++;
            } else {
                $data['withoutquizzes'] ++;
            }

            if ($readerattempts = $DB->get_records('reader_attempts', array('userid' => $coursestudent->id))) {
                foreach ($readerattempts as $readerattempt) {
                    if (strtolower($readerattempt->passed) == 'true') {
                        if ($books = $DB->get_records('reader_books', array('quizid' => $readerattempt->quizid))) {
                            if ($book = array_shift($books)) {
                                $data['averagewordsallterms'] += $book->words;
                            }
                        }
                    }
                }
            }
        }
        if (! $count = count($coursestudents)) {
            $count = 1; // prevent "divide by zero" errors below
        }
        $table->data[] = new html_table_row(array(
            $group->name,
            $data['withoutquizzes'],
            $data['withquizzes'],
            round($data['withquizzes'] / $count * 100, 1) ."%",
            round($data['averagetaken'] / $count, 1),
            round($data['averagepassed'] / $count, 1),
            round($data['averagefailed'] / $count, 1),
            round($data['averagepoints'] / $count ,1),
            round($data['averagewordsthisterm'] / $count),
            round($data['averagewordsallterms'] / $count)
        ));
    }

    reader_sort_table($table, $titles, $orderby, $sort);

    if ($excel) {
        foreach ($table->data as $r => $row) {
            $c = 0;
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_number(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_number(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_number(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_number(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_number(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
            $worksheet->write_string(3 + $r, $c, $row->cells[$c++]->text);
        }
        $workbook->close();
        die;
    }

    echo '<table style="width:100%"><tr><td align="right">';
    echo $OUTPUT->single_button(new moodle_url('admin.php', $options), get_string('downloadexcel', 'reader'), 'post', $options);
    echo '</td></tr></table>';

    echo '<table style="width:100%"><tr><td align="right">';
    echo '<form action="#" id="getfromdate" class="popupform"><select name="fromtime" onchange="self.location=document.getElementById(\'getfromdate\').fromtime.options[document.getElementById(\'getfromdate\').fromtime.selectedIndex].value;"><option value="admin.php?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&orderby='.$orderby.'&gid='.$gid.'&perpage='.$page.'&fromtime=0"';
    if ($fromtime == 86400 || !$fromtime) {
        echo ' selected="selected" ';
    }
    echo '>All time</option><option value="admin.php?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&orderby='.$orderby.'&gid='.$gid.'&perpage='.$page.'&fromtime='.$reader->ignoredate.'"';
    if ($fromtime > 86400) {
        echo ' selected="selected" ';
    }
    echo '>Current Term</option></select></form>';
    echo '</td></tr></table>';

    reader_select_perpage($id, $act, $sort, $orderby, $gid);
    list($totalcount, $table->data, $startrec, $finishrec, $options['page']) = reader_get_pages($table->data, $page, $perpage);
    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&fromtime={$fromtime}&amp;");
    echo $OUTPUT->render($pagingbar);

    if (isset($table) && count($table->data)) {
        echo html_writer::table($table);
    }

    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&fromtime={$fromtime}&amp;");
    echo $OUTPUT->render($pagingbar);

} else if ($act == 'setgoal' && has_capability('mod/reader:setgoal', $contextmodule)) {

    /**
     * reader_setgoal_form
     *
     * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     * @since      Moodle 2.0
     * @package    mod
     * @subpackage reader
     */
    class reader_setgoal_form extends moodleform {

        /**
         * definition
         *
         * @uses $CFG
         * @uses $COURSE
         * @uses $DB
         * @uses $course
         * @uses $reader
         * @todo Finish documenting this function
         */
        function definition() {
            global $COURSE, $CFG, $DB, $course, $reader;

            $mform    = &$this->_form;
            $mform->addElement('header', 'setgoal', get_string('setgoal', 'reader'));
            $mform->addElement('select', 'wordsorpoints', get_string('wordsorpoints', 'reader'), array('points' => get_string('points', 'reader'), 'words' => get_string('words', 'reader')));
            $groups = array('0' => get_string('allparticipants', 'reader'));
            if ($usergroups = groups_get_all_groups($course->id)){
                foreach ($usergroups as $group){
                    $groups[$group->id] = $group->name;
                }
                $mform->addElement('select', 'separategroups', get_string('separategroups', 'reader'), $groups);
            }
            $mform->addElement('text', 'levelall', get_string('all', 'reader'), array('size'=>'10'));
            for($i=1; $i<=10; $i++) {
                $mform->addElement('text', 'levelc['.$i.']', $i, array('size'=>'10'));
            }

            if ($data = $DB->get_records('reader_goal', array('readerid' => $reader->id))) {
                foreach ($data as $data_) {
                    if (empty($data_->level)){
                        $mform->setDefault('levelall', $data_->goal);
                    } else {
                        $mform->setDefault('levelc['.$data_->level.']', $data_->goal);
                    }
                    if ($data_->groupid) {
                        $mform->setDefault('separategroups', $data_->groupid);
                    }
                    if ($data_->goal < 100) {
                        $mform->setDefault('wordsorpoints', 'points');
                    } else {
                        $mform->setDefault('wordsorpoints', 'words');
                    }
                }
            }
            else if ($reader->goal) {
                $mform->setDefault('levelall', $reader->goal);
                if ($reader->goal < 100) {
                    $mform->setDefault('wordsorpoints', 'points');
                } else {
                    $mform->setDefault('wordsorpoints', 'words');
                }
            }
            $this->add_action_buttons(false, $submitlabel="Save");
        }
    }
    $mform = new reader_setgoal_form('admin.php?a='.$a.'&id='.$id.'&act='.$act);
    $mform->display();

} else if ($act == 'forcedtimedelay' && has_capability('mod/reader:forcedtimedelay', $contextmodule)) {

    /**
     * reader_forcedtimedelay_form
     *
     * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     * @since      Moodle 2.0
     * @package    mod
     * @subpackage reader
     */
    class reader_forcedtimedelay_form extends moodleform {

        /**
         * definition
         *
         * @uses $CFG
         * @uses $COURSE
         * @uses $DB
         * @uses $course
         * @uses $reader
         * @todo Finish documenting this function
         */
        function definition() {
            global $COURSE, $CFG, $DB, $course, $reader;

            if ($default = $DB->get_record('reader_forcedtimedelay', array('readerid' => $reader->id,  'level' => 99))) {
              if ($default->delay) {
                $defdelaytime = round($default->delay / 3600);
              }
            } else {
                $defdelaytime = $reader->attemptsofday * 24;
            }

            $dtimes = array(0=>'Default ('.$defdelaytime.')', 1=>'Without delay', 14400=>4, 28800=>8, 43200=>12, 57600=>16, 86400=>24, 129600=>36, 172800=>48, 259200=>72, 345600=>96, 432000=>120);

            $mform    = &$this->_form;
            $mform->addElement('header', 'forcedtimedelay', get_string('forcedtimedelay', 'reader')." (hours)");
            $groups = array('0' => get_string('allparticipants', 'reader'));
            if ($usergroups = groups_get_all_groups($course->id)){
                foreach ($usergroups as $group){
                    $groups[$group->id] = $group->name;
                }
                $mform->addElement('select', 'separategroups', get_string('separategroups', 'reader'), $groups);
            }
            $mform->addElement('select', 'levelc[99]', get_string('all', 'reader'), $dtimes);
            for($i=1; $i<=10; $i++) {
                $mform->addElement('select', 'levelc['.$i.']', $i, $dtimes);
            }

            /* SET default */
            $data = $DB->get_records("reader_forcedtimedelay", array('readerid' => $reader->id));
            foreach ($data as $data_) {
                if ($data_->level == 99) {
                    $mform->setDefault('levelall', $data_->delay);
                } else {
                    $mform->setDefault('levelc['.$data_->level.']', $data_->delay);
                }
            }

            $this->add_action_buttons(false, $submitlabel="Save");
        }
    }
    $mform = new reader_forcedtimedelay_form('admin.php?a='.$a.'&id='.$id.'&act='.$act);
    $mform->display();

} else if ($act == 'bookratingslevel' && has_capability('mod/reader:readerviewreports', $contextmodule)) {
    $table = new html_table();

    echo '<form action="admin.php?a='.$a.'&id='.$id.'&act='.$act.'" method="post">';
    echo get_string('best', 'reader').' <select id="booksratingbest" name="booksratingbest">';
    $fromselect = array('5' => "5", '10' => "10", '25' => "25", '50' => "50", '0' => "All");
    foreach ($fromselect as $key => $value) {
        echo '<option value="'.$key.'"';
        if ($key == $booksratingbest) {
            echo ' selected="selected" ';
        }
        echo '>'.$value.'</option>';
    }
    echo '</select><br />';

    echo get_string('showlevel', 'reader').' <select id="booksratinglevel" name="booksratinglevel"><br />';
    $fromselect = array('0' => "0", '1' => "1", '2' => "2", '3' => "3", '4' => "4", '5' => "5", '6' => "6", '7' => "7", '8' => "8", '9' => "9", '10' => "10", '11' => "11", '12' => "12", '13' => "13", '14' => "14", '15' => "15", '99' => "All");
    foreach ($fromselect as $key => $value) {
        echo '<option value="'.$key.'"';
        if ($key == $booksratinglevel) {
            echo ' selected="selected" ';
        }
        echo '>'.$value.'</option>';
    }
    echo '</select><br />';
    //echo 'Other ip mask <input type="text" name="ipmaskother" value="" />';
    echo get_string('term', 'reader').' <select id="booksratingterm" name="booksratingterm">';
    $fromselect = array('0' => "All terms", $reader->ignoredate => "Current");
    foreach ($fromselect as $key => $value) {
        echo '<option value="'.$key.'"';
        if ($key == $booksratingterm) {
            echo ' selected="selected" ';
        }
        echo '>'.$value.'</option>';
    }
    echo '</select><br />';
    echo get_string('onlybookswithmorethan', 'reader').' <select id="booksratingwithratings" name="booksratingwithratings">';
    $fromselect = array('0' => "0", '5' => "5", '10' => "10", '25' => "25", '50' => "50");
    foreach ($fromselect as $key => $value) {
        echo '<option value="'.$key.'"';
        if ($key == $booksratingwithratings) {
            echo ' selected="selected" ';
        }
        echo '>'.$value.'</option>';
    }
    echo '</select> '.get_string('ratings', 'reader').':<br />';
    echo '<input type="submit" name="booksratingshow" value="Go" /><br />';
    echo '</form>';

    if ($booksratingshow) {
        if ($booksratinglevel == 99) {
            $findallbooks = $DB->get_records('reader_books');
        } else {
            $findallbooks = $DB->get_records_sql('SELECT * FROM {reader_books} WHERE difficulty = ?', array($booksratinglevel));
        }

        $data = array();
        foreach ($findallbooks as $findallbook) {
            $findallattempts = $DB->get_records_sql('SELECT * FROM {reader_attempts} WHERE timefinish >= ? and quizid = ?', array($booksratingterm, $findallbook->id));
            $ratingsummary = "";
            $contof = 0;
            foreach ($findallattempts as $findallattempt) {
                $ratingsummary += $findallattempt->bookrating;
                $contof++;
            }
            $findallbook->ratingsummary = $ratingsummary;
            $findallbook->ratingcount = $contof;
            if ($contof==0) {
                $findallbook->ratingaverage = 0;
            } else {
                $findallbook->ratingaverage = round((($ratingsummary/$contof)*3.3)+0.1,1);
            }
            if ($contof >= $booksratingwithratings) {
                $data[] = $findallbook;
            }
        }
        $data = reader_order_object($data, "ratingaverage");

        $titles = array('Book Title'=>'booktitle', 'Publisher'=>'publisher', 'R. Level'=>'level', 'Avg Rating'=>'avrating', 'No. of Ratings'=>'nrating');

        $params = array('a' => 'admin', 'id' => $id, 'act' => 'booksratingbest',
                        'booksratingshow'  => 'Go',
                        'booksratingterm'  => $booksratingterm,
                        'booksratinglevel' => $booksratinglevel,
                        'booksratingwithratings' => $booksratingwithratings);
        reader_make_table_headers($table, $titles, $orderby, $sort, $params);
        $table->align = array("left", "left", "center", "center", "center");
        $table->width = "100%";

        //echo $booksratingbest;

        if ($booksratingbest) {
            foreach ($data as $data_) {
                $datares[$data_->id][0] = $data_->id;
                $datares[$data_->id][1] = $data_->name;
                $datares[$data_->id][2] = $data_->publisher;
                $datares[$data_->id][3] = $data_->ratingaverage;
                $datares[$data_->id][4] = $data_->ratingcount;
            }
            $i=0;
            unset($data);
            foreach ($datares as $datares_) {
              $i++;
              if ($i<=$booksratingbest) {
                $data[$datares_[0]]->id = $datares_[0];
                $data[$datares_[0]]->name = $datares_[1];
                $data[$datares_[0]]->publisher = $datares_[2];
                $data[$datares_[0]]->ratingaverage = $datares_[3];
                $data[$datares_[0]]->ratingcount = $datares_[4];
              }
            }
        }

        foreach ($data as $data_) {
            $table->data[] = new html_table_row(array(
                $data_->name,
                $data_->publisher,
                reader_get_reader_difficulty($reader, $data_->id),
                $data_->ratingaverage,
                $data_->ratingcount));
        }
        reader_sort_table($table, $titles, $orderby, $sort);

        if (isset($table) && count($table->data)) {
            echo html_writer::table($table);
        }
    }

} else if ($act == 'setbookinstances' && has_capability('mod/reader:selectquizzestomakeavailabletostudents', $contextmodule)) {

        reader_setbookinstances($id, $reader);

} else if ($act == 'viewlogsuspiciousactivity' && has_capability('mod/reader:readerviewreports', $contextmodule)) {
    $table = new html_table();

    $titles = array('Image'=>'', 'By Username'=>'byusername', 'Student 1'=>'student1', 'Student 2'=>'student2', 'Quiz'=>'quiz', 'Status'=>'status', 'Date'=>'date');

    $params = array('a' => 'admin', 'id' => $id, 'act' => $act, 'gid' => $gid, 'page' => $page);
    reader_make_table_headers($table, $titles, $orderby, $sort, $params);
    $table->align = array("center", "left", "left", "left", "left", "center", "center");
    $table->width = "100%";

    if ($excel) {
        $worksheet->set_row(0, 24); // set row height
        $worksheet->write_string(0, 0, 'View log of suspicious activity', $formatbold);

        $worksheet->set_row(1, 24); // set row height
        $worksheet->write_string(1, 0, 'Date: '.$exceldata['time'].'; Course name: '.$exceldata['course_shotname'].'; ');

        $c = 0;
        $worksheet->set_row(2, 24); // set row height
        $worksheet->write_string(2, $c++, 'By Username', $formatbold);
        $worksheet->write_string(2, $c++, 'Student 1', $formatbold);
        $worksheet->write_string(2, $c++, 'Student 2', $formatbold);
        $worksheet->write_string(2, $c++, 'Quiz', $formatbold);
        $worksheet->write_string(2, $c++, 'Status', $formatbold);
        $worksheet->write_string(2, $c++, 'Date', $formatbold);
    }

    if (! $gid) {
        $gid = NULL;
    }

    $cheatedlogs = $DB->get_records('reader_cheated_log', array('readerid' => $reader->id));

    foreach ($cheatedlogs as $cheatedlog) {
        $cheatedstring = '';
        if ($cheatedlog->status == "cheated") {
            $cheatedstring = ' <a href="admin.php?a='.$a.'&id='.$id.'&act='.$act.'&gid='.$gid.'&page='.$page.'&sort='.$sort.'&orderby='.$orderby.'&uncheated='.$cheatedlog->attempt1.'_'.$cheatedlog->attempt2.'" onclick="if(confirm(\'Set passed ?\')) return true; else return false;">Set passed</a>';
        }

        $byuser  = $DB->get_record('user', array('id' => $cheatedlog->byuserid));
        $user1   = $DB->get_record('user', array('id' => $cheatedlog->userid1));
        $user2   = $DB->get_record('user', array('id' => $cheatedlog->userid2));
        $quiz    = $DB->get_record('reader_books', array('quizid' => $cheatedlog->quizid));

        $picture = $OUTPUT->user_picture($byuser,array($course->id, true, 0, true));
        $table->data[] = new html_table_row(array(
            $picture,
            reader_fullname_link($byuser, $course->id, $excel),
            reader_fullname_link($user1, $course->id, $excel),
            reader_fullname_link($user2, $course->id, $excel),
            $quiz->name,
            $cheatedlog->status.$cheatedstring,
            date($dateformat, $cheatedlog->date) // was 'd M Y'
            ));
    }

    reader_sort_table($table, $titles, $orderby, $sort);

    if ($excel) {
        foreach ($table->data as $r => $row) {
            $c = 0;
            $worksheet->write_string(3 + $r, $c, (string) $row->cells[$c++]->text);
            $worksheet->write_string(3 + $r, $c, (string) $row->cells[$c++]->text);
            $worksheet->write_string(3 + $r, $c, (string) $row->cells[$c++]->text);
            $worksheet->write_number(3 + $r, $c, (string) $row->cells[$c++]->text);
            $worksheet->write_number(3 + $r, $c, (string) $row->cells[$c++]->text);
            $worksheet->write_number(3 + $r, $c, (string) $row->cells[$c++]->text);
        }
    }

    if ($excel) {
        $workbook->close();
        die;
    }

    echo '<table style="width:100%"><tr><td align="right">';
    echo $OUTPUT->single_button(new moodle_url('admin.php',$options), get_string('downloadexcel', 'reader'), 'post', $options);
    echo '</td></tr></table>';

    reader_select_perpage($id, $act, $sort, $orderby, $gid);
    list($totalcount, $table->data, $startrec, $finishrec, $options['page']) = reader_get_pages($table->data, $page, $perpage);
    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $OUTPUT->render($pagingbar);

    if (isset($table) && count($table->data)) {
        echo html_writer::table($table);
    }

    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $OUTPUT->render($pagingbar);

} else if ($act == 'exportstudentrecords' && has_capability('mod/reader:userdbmanagement', $contextmodule)) {

    $users = array();
    $books = array();
    $levels = array();

    // get all attempts
    $sortfields = 'userid,quizid,timefinish,uniqueid DESC';
    $readerattempts = $DB->get_records('reader_attempts', array('reader' => $reader->id), $sortfields);

    // prune the attempts
    $userid = 0;
    $quizid = 0;
    $timefinish = 0;
    foreach($readerattempts as $readerattempt) {
        // remove lower uniqueids with same userid/quizid/timefinish
        if ($readerattempt->userid==$userid && $readerattempt->quizid==$quizid && $readerattempt->timefinish==$timefinish) {
            unset($readerattempts[$readerattempt->id]);
        } else {
            $userid = $readerattempt->userid;
            $quizid = $readerattempt->quizid;
            $timefinish = $readerattempt->timefinish;
        }
    }

    foreach($readerattempts as $readerattempt) {
        $userid = $readerattempt->userid;
        $quizid = $readerattempt->quizid;

        if (empty($users[$userid])) {
            if ($user = $DB->get_record('user', array('id' => $userid))) {
                $users[$userid] = $user;
            } else {
                $users[$userid] = (object)array('id' => 0, 'username' => '');
            }
        }
        if (empty($levels[$userid])) {
            if ($records = $DB->get_records('reader_levels', array('userid' => $userid))) {
                $levels[$userid] = reset($records);
            } else {
                $levels[$userid] = (object)array('currentlevel' => 0);
            }

        }
        if (empty($books[$quizid])) {
            if ($records = $DB->get_records('reader_books', array('quizid' => $quizid))) {
                $books[$quizid] = reset($records); // there may be several !!
            } else {
                $books[$quizid] = (object)array('image' => '');
            }
        }

        if (! headers_sent()) {
            $filename = $COURSE->shortname.'_attempts.txt';
            header('Content-Type: text/plain; filename="'.$filename.'"');
        }

        echo $users[$userid]->username.','.
             $readerattempt->uniqueid.','.
             $readerattempt->attempt.','.
             $readerattempt->sumgrades.','.
             $readerattempt->percentgrade.','.
             $readerattempt->bookrating.','.
             $readerattempt->ip.','.
             $books[$quizid]->image.','.
             $readerattempt->timefinish.','.
             $readerattempt->passed.','.
             $readerattempt->percentgrade.','.
             $levels[$userid]->currentlevel.
             "\n";
    }
    die;

} else if ($act == 'importstudentrecord' && has_capability('mod/reader:userdbmanagement', $contextmodule)) {

    /**
     * mod_reader_importstudentrecord_form
     *
     * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     * @since      Moodle 2.0
     * @package    mod
     * @subpackage reader
     */
    class mod_reader_importstudentrecord_form extends moodleform {

        /**
         * definition
         *
         * @todo Finish documenting this function
         */
        function definition() {
            $this->_form->addElement('filepicker', 'importstudentrecorddata', get_string('file'));
            $this->add_action_buttons(false, get_string('upload'));
        }
    }
    $mform = new mod_reader_importstudentrecord_form("admin.php?a=admin&id={$id}&act=importstudentrecord");
    if ($lines = $mform->get_file_content('importstudentrecorddata')) {

        $lines = preg_replace('/[\r\n]+/', '\n', $lines);
        $lines = explode('\n', $lines);

        if ($lines) {
            echo "File was uploaded <br />\n";
        }

        // get the next unique id
        if ($uniqueid = $DB->get_field_sql('SELECT MAX(uniqueid) FROM {reader_attempts}')) {
            $uniqueid ++;
        } else {
            $uniqueid = 1;
        }

        $userid = 0;
        $bookid = 0;
        foreach ($lines as $line) {


            // skip empty lines
            $line = trim($line);
            if ($line=='') {
                continue;
            }

            // make sure we have exactly 11 commas (=12 columns)
            if (substr_count($line, ',') <> 11) {
                echo 'SKIP line: '.$line.html_writer::empty_tag('br');
                continue; // unexpected format !!
            }

            $values = array();
            list($values['username'],
                 $values['uniqueid'],
                 $values['attempt'],
                 $values['sumgrades'],
                 $values['percentgrade'],
                 $values['bookrating'],
                 $values['ip'],
                 $values['image'],
                 $values['timefinish'],
                 $values['passed'],
                 $values['percentgrade'],
                 $values['currentlevel']) = explode(',', $line);

            if (! $username = $values['username']) {
                continue; // empty username !!
            }
            if (! $image = $values['image']) {
                continue; // empty image !!
            }

            if (empty($userdata[$username])) {
                if ($user = $DB->get_record('user', array('username' => $username))) {
                    $users[$username] = $user;
                } else {
                    $users[$username] = (object)array('id' => 0); // no such user ?!
                    echo "User name not found: $username".html_writer::empty_tag('br');
                }
            }

            if (empty($users[$username]->id)) {
                continue;
            }

            if (empty($books[$image])) {
                if ($book = $DB->get_record('reader_books', array('image' => $image))) {
                    $book->quiz = $DB->get_record('quiz', array('id' => $book->quizid));
                    if (empty($book->quiz)) {
                        // shouldn't happen - but we can continue with a dummy quiz record ...
                        $book->quiz = (object)array('preferredbehaviour' => 'deferredfeedback');
                    }
                    $books[$image] = $book;
                }
            }
            if (empty($books[$image])) {
                $books[$image] = (object)array('id' => 0, 'quizid' => 0); // no such book ?!
                echo "Book not found: $image".html_writer::empty_tag('br');
            }

            if (empty($books[$image]->id) || empty($books[$image]->quizid)) {
                continue;
            }

            $sameuser = ($userid && $userid==$users[$username]->id);
            $samebook = ($sameuser && $bookid && $bookid==$books[$image]->id);


            if ($samebook==false) {

                if ($bookid) {
                    echo html_writer::end_tag('ul'); // end attempts
                    echo html_writer::end_tag('li'); // end book
                }

                if ($sameuser==false) {
                    if ($userid==0) {
                        echo html_writer::start_tag('ul'); // start users
                    } else {
                        echo html_writer::end_tag('ul'); // end books
                        echo html_writer::end_tag('li'); // end user
                    }
                    echo html_writer::start_tag('li'); // start user
                    $fullname = fullname($users[$username]).' (username='.$username.', id='.$users[$username]->id.')';
                    echo html_writer::tag('span', $fullname, array('class' => 'importusername'));
                    $userid = $users[$username]->id;
                    $bookid = 0; // force new book list
                }

                if ($bookid==0) {
                    echo html_writer::start_tag('ul'); // start books
                }

                echo html_writer::start_tag('li'); // start book
                echo html_writer::tag('span', $books[$image]->name, array('class' => 'importbookname'));
                echo html_writer::start_tag('ul'); // start attempt list
                $bookid = $books[$image]->id;
            }

            echo html_writer::start_tag('li'); // start attempt

            $strpassed = reader_format_passed($values['passed'], true);
            $timefinish = userdate($values['timefinish'])." ($strpassed)";
            echo html_writer::tag('span', $timefinish, array('class' => 'importattempttime')).' ';

            $question_usage = (object)array(
                'contextid' => $contextmodule->id,
                'component' => 'mod_reader',
                'preferredbehaviour' => $books[$image]->quiz->preferredbehaviour
            );

            $readerattempt = (object)array(
                // the "uniqueid" field is in fact an "id" from the "question_usages" table
                'uniqueid'      => $DB->insert_record('question_usages', $question_usage),
                'reader'        => $reader->id,
                'userid'        => $users[$username]->id,
                'attempt'       => $values['attempt'],
                'sumgrades'     => $values['sumgrades'],
                'percentgrade'  => $values['percentgrade'],
                'passed'        => $values['passed'],
                'checkbox'      => 0,
                'timestart'     => $values['timefinish'],
                'timefinish'    => $values['timefinish'],
                'timemodified'  => $values['timefinish'],
                'layout'        => 0, // $values['layout']
                'preview'       => 0,
                'quizid'        => $books[$image]->quizid,
                'bookrating'    => $values['bookrating'],
                'ip'            => $values['ip'],
            );

            $params = array('userid' => $users[$username]->id, 'quizid' => $books[$image]->quizid, 'timefinish' => $values['timefinish']);
            if ($DB->record_exists('reader_attempts', $params)) {
                echo html_writer::tag('span', 'skipped', array('class' => 'importskipped'));
            } else if ($DB->insert_record('reader_attempts', $readerattempt)) {
                echo html_writer::tag('span', 'added', array('class' => 'importsuccess'));
            } else {
                echo html_writer::tag('span', 'failed', array('class' => 'importfailed'));
                print_object($readerattempt);
            }
            echo html_writer::end_tag('li'); // end attempt
        }

        if ($bookid) {
            echo html_writer::end_tag('ul'); // end attempt
            echo html_writer::end_tag('li'); // end book
        }
        if ($userid) {
            echo html_writer::end_tag('ul'); // end books
            echo html_writer::end_tag('li'); // end user
            echo html_writer::end_tag('ul'); // end users
        }
        echo 'Done';
    } else {
        $mform->display();
    }

} else if ($act == 'changenumberofsectionsinquiz' && has_capability('mod/reader:manage', $contextmodule)) {
    if ($numberofsections) {
        echo "<h2>Done</h2>";
    }

    /**
     * mod_reader_changenumberofsectionsinquiz_form
     *
     * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     * @since      Moodle 2.0
     * @package    mod
     * @subpackage reader
     */
    class mod_reader_changenumberofsectionsinquiz_form extends moodleform {

        /**
         * definition
         *
         * @uses $CFG
         * @uses $DB
         * @uses $course
         * @todo Finish documenting this function
         */
        function definition() {
            global $CFG, $DB, $course;

            $mform    = &$this->_form;

            $mform->addElement('header', 'setgoal', get_string('changenumberofsectionsinquiz', 'reader'));
            $mform->addElement('text', 'numberofsections', '', array('size'=>'10'));

            $this->add_action_buttons(false, $submitlabel="Save");
        }
    }
    $mform = new mod_reader_changenumberofsectionsinquiz_form("admin.php?a=admin&id={$id}&act=changenumberofsectionsinquiz");
    $mform->display();

} else if ($act == 'assignpointsbookshavenoquizzes' && has_capability('mod/reader:changestudentslevelsandpromote', $contextmodule)) {
    $table = new html_table();

    $titles = array('<input type="button" value="Select all" onclick="checkall();" />'=>'', 'Image'=>'', 'Username'=>'username', 'Fullname<br />Click to view screen'=>'fullname', 'Current level'=>'currentlevel', 'Total words<br /> this term'=>'totalwordsthisterm', 'Total words<br /> all terms'=>'totalwordsallterms');

    $params = array('a' => 'admin', 'id' => $id, 'act' => $act, 'gid' => $gid, 'book' => $book, 'searchtext' => $searchtext, 'page' => $page);
    reader_make_table_headers($table, $titles, $orderby, $sort, $params);
    $table->align = array("center", "center", "left", "left", "center", "center", "center");
    $table->width = "100%";

    if (! $gid) {
        $gid = NULL;
    }

    $groupnames = array();
    foreach ($coursestudents as $coursestudent) {
      $groupnames[$coursestudent->username] = array();
      if (reader_check_search_text($searchtext, $coursestudent)) {
        $picture = $OUTPUT->user_picture($coursestudent,array($course->id, true, 0, true));

        if ($excel) {
            if ($usergroups = groups_get_all_groups($course->id, $coursestudent->id)){
                foreach ($usergroups as $group){
                    $groupnames[$coursestudent->username][] = $group->name;
                }
            }
        }

        unset($data);
        $data['totalwordsthisterm'] = 0;
        $data['totalwordsallterms'] = 0;

        if ($attempts = $DB->get_records_sql('SELECT * FROM {reader_attempts} WHERE userid= ?  and reader= ?  and timefinish > ? ', array($coursestudent->id, $reader->id, $reader->ignoredate))) {
            foreach ($attempts as $attempt) {
                if (strtolower($attempt->passed) == 'true') {
                    if ($bookdata = $DB->get_record('reader_books', array('quizid' => $attempt->quizid))) {
                        $data['totalwordsthisterm'] += $bookdata->words;
                    }
                }
            }
        }

        if ($attempts = $DB->get_records_sql('SELECT * FROM {reader_attempts} WHERE userid= ? ', array($coursestudent->id))) {
            foreach ($attempts as $attempt) {
                if (strtolower($attempt->passed) == 'true') {
                    if ($bookdata = $DB->get_record('reader_books', array('quizid' => $attempt->quizid))) {
                        $data['totalwordsallterms'] += $bookdata->words;
                    }
                }
            }
        }

        if ($readerattempt = reader_get_student_attempts($coursestudent->id, $reader)) {
            if (has_capability('mod/reader:viewstudentreaderscreens', $contextmodule)) {
                $link = reader_fullname_link_viewasstudent($coursestudent, $id, $excel);
            } else {
                $link = reader_fullname_link($coursestudent, $course->id, $excel);
            }

            $table->data[] = new html_table_row(array(
                '<input type="checkbox" name="noquizuserid[]" value="'.$coursestudent->id.'" />',
                $picture,
                reader_username_link($coursestudent, $course->id, $excel),
                $link,
                $readerattempt[1]['currentlevel'],
                $data['totalwordsthisterm'],
                $data['totalwordsallterms']
            ));
        } else {
            if (has_capability('mod/reader:viewstudentreaderscreens', $contextmodule)) {
                $link = reader_fullname_link_viewasstudent($coursestudent, $id, $excel);
            } else {
                $link = reader_fullname_link($coursestudent, $course->id, $excel);
            }

            $table->data[] = new html_table_row(array(
                '<input type="checkbox" name="noquizuserid[]" value="'.$coursestudent->id.'" />',
                $picture,
                reader_username_link($coursestudent, $course->id, $excel),
                $link,
                $readerattempt[1]['currentlevel'],
                0,0));
        }
      }
    }

    reader_sort_table($table, $titles, $orderby, $sort);

    reader_print_search_form();

    $groups = groups_get_all_groups($course->id);

    if ($groups) {
        reader_print_group_select_box($course->id, 'admin.php?a=admin&id='.$id.'&act='.$act.'&book='.$book.'&sort='.$sort.'&orderby='.$orderby);
    }

    echo '<form method="post" action="?a=admin&id='.$id.'&act='.$act.'&book='.$book.'"> <input type="submit" name="setnoquiz" value="Submit" />';

    $publisherform = array("id=".$id.'&publisher=Select Publisher' => get_string('selectpublisher', 'reader'));

    if (isset($noquizreport)) {
        echo '<center><h3>'.$noquizreport.'</h3></center>';
    }

    $publishers = $DB->get_records('reader_noquiz', null, 'publisher', 'DISTINCT publisher');
    foreach ($publishers as $publisher_) {
        $publisherkey = "id=".$id."&publisher=".$publisher_->publisher;
        $publisherform[$publisherkey] = $publisher_->publisher;
    }
    echo '<script type="text/javascript">'."\n";
    echo '//<![CDATA['."\n";
    echo 'function validateForm(form) {'."\n";
    echo '    return isChosen(form.book);'."\n";
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

    echo '<center><table width="600px">';
    echo '<tr><td width="200px">'.get_string('publisherseries', 'reader').'</td><td width="10px"></td><td width="200px"></td></tr>';
    echo '<tr><td valign="top">';
    echo '<select name="publisher" id="id_publisher" onchange="request(\'view_books_noquiz.php?ajax=true&\' + this.options[this.selectedIndex].value,\'selectthebook\'); return false;">';
    foreach ($publisherform as $publisherformkey => $publisherformvalue) {
        echo '<option value="'.$publisherformkey.'" ';
        if ($publisherformvalue == $publisher) {
            echo 'selected="selected"';
        }
        echo ' >'.$publisherformvalue.'</option>';
    }
    echo '</select>';
    echo '</td><td valign="top">';

    echo '</td><td valign="top"><div id="selectthebook">';

    echo '</div></td></tr>';
    echo '<tr><td colspan="3" align="center">';

    echo '</td></tr>';
    echo '</table>';
    echo '</center>';
    //echo '</form></center>';

    reader_select_perpage($id, $act, $sort, $orderby, $gid);
    list($totalcount, $table->data, $startrec, $finishrec, $options['page']) = reader_get_pages($table->data, $page, $perpage);
    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&book={$book}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $OUTPUT->render($pagingbar);

    if (isset($table) && count($table->data)) {
        echo html_writer::table($table);
    }

    echo '</form>';

    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&book={$book}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $OUTPUT->render($pagingbar);

} else if ($act == 'adjustscores' && has_capability('mod/reader:manage', $contextmodule)) {

    $table = new html_table();

    if ($sort == 'username') {
        $sort = 'title';
    }

    $titles = array('' => '',
                    'Full Name'  => 'username',
                    'Title'      => 'title',
                    'Publisher'  => 'publisher',
                    'Level'      => 'level',
                    'Reading Level' => 'rlevel',
                    'Reading level' => 'rlevel',
                    'Score'      => 'score',
                    'P/F/C'      => '',
                    'Finishtime' => 'finishtime',
                    'Option'     => '');

    $params = array('a' => 'admin', 'id' => $id, 'act' => $act, 'searchtext' => $searchtext);
    reader_make_table_headers($table, $titles, $orderby, $sort, $params);

    $table->align = array('left', 'left', 'left', 'left', 'center', 'center', 'center', 'center', 'center', 'center', 'center');
    $table->width = '100%';

//    [publisher] => id=3456&publisher=Cambridge
//    [level] => Starter
//    [book] => 435

    if (is_int($book) && $book >= 1) {
        $bookdata = $DB->get_record('reader_books', array('id'=>$book));
        $quizdata = $DB->get_record('quiz', array('id'=>$bookdata->quizid));
    }

    $readerattempts = $DB->get_records('reader_attempts', array('quizid' => $bookdata->quizid, 'reader' => $reader->id));
    foreach ($readerattempts as $readerattempt) {
        $userdata = $DB->get_record('user', array('id'=>$readerattempt->userid));
        $table->data[] = new html_table_row(array(
            html_writer::empty_tag('input', array('type'=>'checkbox', 'name'=>'adjustscoresupbooks[]', 'value'=>$readerattempt->id)),
            fullname($userdata),
            html_writer::link(new moodle_url('/mod/reader/report.php', array('id'=>$id, 'q'=>$bookdata->quizid, 'mode'=>'analysis', 'b'=>$bookdata->id)), $bookdata->name),
            $bookdata->publisher,
            $bookdata->level,
            reader_get_reader_difficulty($reader, $bookdata->id),
            $bookdata->difficulty,
            round($readerattempt->percentgrade).'%',
            reader_format_passed($readerattempt->passed),
            date($dateformat, $readerattempt->timemodified), // was 'd-M-Y',
            '' // deleted
        ));
    }

    reader_sort_table($table, $titles, $orderby, $sort);

    $publisherform = array();

    $publisherkey = 'id='.$id.'&publisher=Select_Publisher';
    $publisherform[$publisherkey] = get_string('selectpublisher', 'reader');

    if ($records = $DB->get_records('reader_books', null, 'publisher')) {
        foreach ($records as $record) {
            $publisherkey = 'id='.$id.'&publisher='.$record->publisher;
            $publisherform[$publisherkey] = $record->publisher;
        }
    }

    $onchange = "request('view_books.php?ajax=true&action=$act&' + this.options[this.selectedIndex].value,'bookleveldiv'); return false;";
    $select = '<select name="publisher" id="id_publisher" onchange="'.$onchange.'">';
    foreach ($publisherform as $publisherkey => $publishername) {
        $select .= '<option value="'.$publisherkey.'" ';
        if ($publishername == $publisher) {
            $select .=  'selected="selected"';
        }
        $select .= ' >'.$publishername.'</option>';
    }
    $select .= '</select>';

    $submit = html_writer::empty_tag('input', array('type'=>'submit', 'value'=>'Select quiz'));

    $alink  = new moodle_url('/mod/reader/admin.php', array('id'=>$id, 'act'=>$act, 'a'=>'admin'));

    if (isset($adjustscorestext)) {
        echo html_writer::tag('p', $adjustscorestext);
    }

    $output  = '';
    $output .= html_writer::start_tag('table', array('style'=>'width:100%'));
    $output .= html_writer::start_tag('tr');
    $output .= html_writer::start_tag('td', array('align'=>'right'));

    $output .= html_writer::start_tag('form', array('action'=>$alink, 'method'=>'post', 'id'=>'mform1'));
    $output .= html_writer::start_tag('center');
    $output .= html_writer::start_tag('table', array('width'=>'600px'));

    $output .= html_writer::start_tag('tr');
    $output .= html_writer::tag('td', get_string('publisherseries', 'reader'), array('width'=>'200px'));
    $output .= html_writer::tag('td', '', array('width'=>'10px'));
    $output .= html_writer::tag('td', '', array('width'=>'200px'));
    $output .= html_writer::end_tag('tr');

    $output .= html_writer::start_tag('tr');
    $output .= html_writer::tag('td', $select, array('valign'=>'top'));
    $output .= html_writer::tag('td', html_writer::tag('div', '', array('id'=>'bookleveldiv')), array('valign'=>'top'));
    $output .= html_writer::tag('td', html_writer::tag('div', '', array('id'=>'bookiddiv')), array('valign'=>'top'));
    $output .= html_writer::end_tag('tr');

    $output .= html_writer::start_tag('tr');
    $output .= html_writer::tag('td', '', array('colspan'=>3, 'align'=>'center'));
    $output .= html_writer::end_tag('tr');

    $output .= html_writer::start_tag('tr');
    $output .= html_writer::tag('td', html_writer::empty_tag('input', array('type'=>'submit', 'value'=>'Select quiz')), array('colspan'=>3, 'align'=>'center'));
    $output .= html_writer::end_tag('tr');

    $output .= html_writer::end_tag('table');
    $output .= html_writer::end_tag('form');
    $output .= html_writer::end_tag('center');

    $output .= html_writer::end_tag('td');
    $output .= html_writer::end_tag('tr');
    $output .= html_writer::end_tag('table');

    $alink  = new moodle_url('/mod/reader/admin.php', array('id'=>$id, 'act'=>$act, 'book'=>$book, 'a'=>'admin'));

    $output .= html_writer::start_tag('form', array('action'=>$alink, 'method'=>'post'));
    $output .= html_writer::start_tag('div', array('style'=>'20px 0;'));

    $output .= html_writer::start_tag('table');
    $output .= html_writer::start_tag('tr');
    $output .= html_writer::tag('td', 'Update selected adding', array('width'=>'180px;'));
    $output .= html_writer::start_tag('td', array('width'=>'60px;'));
    $output .= html_writer::empty_tag('input', array('type'=>'text', 'name'=>'adjustscoresaddpoints', 'value'=>'', 'style'=>'width:60px;'));
    $output .= html_writer::end_tag('td');
    $output .= html_writer::tag('td', 'points', array('width'=>'70px;'));
    $output .= html_writer::start_tag('td');
    $output .= html_writer::empty_tag('input', array('type'=>'submit', 'value'=>get_string('add')));
    $output .= html_writer::end_tag('td');
    $output .= html_writer::end_tag('tr');
    $output .= html_writer::end_tag('table');

    $output .= html_writer::start_tag('table');
    $output .= html_writer::start_tag('tr');
    $output .= html_writer::tag('td', 'Update all > ', array('width'=>'100px;'));
    $output .= html_writer::start_tag('td', array('width'=>'60px;'));
    $output .= html_writer::empty_tag('input', array('type'=>'text', 'name'=>'adjustscoresupall', 'value'=>'', 'style'=>'width:50px;'));
    $output .= html_writer::end_tag('td');
    $output .= html_writer::tag('td', 'points and < ', array('width'=>'90px;'));
    $output .= html_writer::start_tag('td', array('width'=>'60px;'));
    $output .= html_writer::empty_tag('input', array('type'=>'text', 'name'=>'adjustscorespand', 'value'=>'', 'style'=>'width:50px;'));
    $output .= html_writer::end_tag('td');
    $output .= html_writer::tag('td', 'points by ', array('width'=>'90px;'));
    $output .= html_writer::start_tag('td', array('width'=>'60px;'));
    $output .= html_writer::empty_tag('input', array('type'=>'text', 'name'=>'adjustscorespby', 'value'=>'', 'style'=>'width:50px;'));
    $output .= html_writer::end_tag('td');
    $output .= html_writer::tag('td', 'points', array('width'=>'70px;'));
    $output .= html_writer::start_tag('td');
    $output .= html_writer::empty_tag('input', array('type'=>'submit', 'value'=>get_string('add')));
    $output .= html_writer::end_tag('td');
    $output .= html_writer::end_tag('tr');
    $output .= html_writer::end_tag('table');

    $output .= html_writer::end_tag('div');

    if (count($table->data)) {
        $output .= html_writer::table($table);
    }

    $output .= html_writer::end_tag('form');

    echo $output;

}

echo $OUTPUT->box_end();
echo $OUTPUT->footer();


// ============================
// functions and classes
// ============================

/**
 * reader_menu
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class reader_menu {
    protected $sections = array();

    /**
     * __construct
     *
     * @param xxx $sections
     * @todo Finish documenting this function
     */
    public function __construct($sections) {
        $this->sections = $sections;
    }

    /**
     * out
     *
     * @param xxx $context
     * @return xxx
     * @todo Finish documenting this function
     */
    public function out($context) {
        $out = ''; // '<h3>'.get_string('menu', 'reader').':</h3><ul>';
        foreach ($this->sections as $sectionname => $items) {
            $out .= '<li><b>'.get_string($sectionname, 'reader').'</b><ul>';
            foreach ($items as $item) {
                $out .= '<li>'.$item->out($context).'</li>';
            }
            $out .= '</ul></li>';
        }
        $out .= '</ul>';
        return $out;
    }
}

/**
 * reader_menu_item
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class reader_menu_item {

    protected $displaystring = '';
    protected $capability    = '';
    protected $scriptname    = '';
    protected $scriptparams  = array();
    protected $fullme        = null;

    /**
     * __construct
     *
     * @param xxx $displaystring
     * @param xxx $capability
     * @param xxx $scriptname
     * @param xxx $scriptparams
     * @todo Finish documenting this function
     */
    public function __construct($displaystring, $capability, $scriptname, $scriptparams) {
        $this->displaystring = $displaystring;
        $this->capability    = $capability;
        $this->scriptname    = $scriptname;
        $this->scriptparams  = $scriptparams;
    }

    /**
     * get_fullme
     *
     * @uses $FULLME
     * @return xxx
     * @todo Finish documenting this function
     */
    protected function get_fullme() {
        global $FULLME;
        if (is_null($this->fullme)) {
            $strpos = strpos($FULLME, '?');
            if ($strpos===false) {
                $strpos = strlen($FULLME);
            }
            $url = substr($FULLME, 0, $strpos);
            $values = substr($FULLME, $strpos + 1);
            $values = explode('&', $values);
            $values = array_filter($values); // remove blanks
            $params = array();
            foreach ($values as $value) {
                if (strpos($value, '=')==false) {
                    continue;
                }
                list($name, $value) = explode('=', $value, 2);
                $params[$name] = $value;
            }
            $this->fullme = new moodle_url($url, $params);
        }
        return $this->fullme;
    }

    /**
     * out
     *
     * @param xxx $context
     * @return xxx
     * @todo Finish documenting this function
     */
    public function out($context) {
        $out = '';
        if (has_capability('mod/reader:'.$this->capability, $context)) {
            $out = get_string($this->displaystring, 'reader');
            $url = new moodle_url('/mod/reader/'.$this->scriptname, $this->scriptparams);
            if ($url->compare($this->get_fullme(), URL_MATCH_PARAMS)) {
                // current page - do not convert to link
            } else {
                $out = '<a href="'.$url.'">'.$out.'</a>';
            }
        }
        return $out;
    }
}

/**
 * reader_ajax_textbox_title
 *
 * @param xxx $has_capability
 * @param xxx $book
 * @param xxx $type : "words", "level" or "publisher"
 * @param xxx $text
 * @param xxx $id
 * @param xxx $act
 * @todo Finish documenting this function
 */
function reader_ajax_textbox_title($has_capability, $book, $type, $id, $act) {
    if ($has_capability) {
        $divid = $type.'title_'.$book->id;
        $inputid = $type.'title_input_'.$book->id;
        $onkeyup = "if(event.keyCode=='13') {request('admin.php?ajax=true&id={$id}&act={$act}&{$type}titleid={$book->id}&{$type}titlekey='+document.getElementById('$inputid').value,'$divid');return false;}";
        $title = '';
        $title .= html_writer::start_tag('div', array('id' => $divid));
        $title .= html_writer::empty_tag('input', array('type' => 'text', 'id' => $inputid, 'name' => $type.'title', 'value' => $book->$type, 'onkeyup' => $onkeyup));
        $title .= html_writer::end_tag('div');
    } else {
        $title = $book->$type;
    }
    return $title;
}

/**
 * reader_setbookinstances
 *
 * @param xxx $id
 * @param xxx $reader
 * @todo Finish documenting this function
 */
function reader_setbookinstances($cmid, $reader) {
    global $CFG, $DB, $OUTPUT;

    if ($reader->bookinstances == 0) {
        echo '<div>'.get_string('coursespecificquizselection', 'reader').'</div>';
    }

    $currentbooks = array();
    if ($books = $DB->get_records('reader_book_instances', array('readerid' => $reader->id), 'id', 'id, bookid, readerid')) {
        foreach ($books as $book) {
            $currentbooks[$book->bookid] = true;
        }
    }

    $publishers = array();
    if ($books = $DB->get_records('reader_books', array(), 'publisher, name', 'id, publisher, level, name')) {
        foreach ($books as $book) {
            if (empty($publishers[$book->publisher])) {
                $publishers[$book->publisher] = array();
            }
            if (empty($publishers[$book->publisher][$book->level])) {
                $publishers[$book->publisher][$book->level] = array();
            }
            $book->checked = isset($currentbooks[$book->id]);
            $publishers[$book->publisher][$book->level][$book->id] = $book;
        }
    }
    unset($currentbooks, $books, $book);

    $uniqueid = 0;
    $uniqueids = array();

    $checked = new stdClass();
    $checked->publishers = array();
    $checked->levels     = array();

    foreach ($publishers as $publisher => $levels) {
        foreach ($levels as $level => $bookids) {
            foreach ($bookids as $bookid => $bookname) {
                $uniqueid++;
                $uniqueids[$bookid] = $uniqueid;

                if (empty($checked->publishers[$publisher])) {
                    $checked->publishers[$publisher] = array();
                }
                $checked->publishers[$publisher][] = $uniqueid;

                if (empty($checked->levels[$publisher])) {
                    $checked->levels[$publisher] = array();
                }
                if (empty($checked->levels[$publisher][$level])) {
                    $checked->levels[$publisher][$level] = array();
                }
                $checked->levels[$publisher][$level][] = $uniqueid;
            }
        }
    }

    echo $OUTPUT->box_start('generalbox');
    require_once('js/hide.js');

    echo '<script type="text/javascript">'."\n";
    echo '//<![CDATA['."\n";
    echo 'function setChecked(obj,from,to) {'."\n";
    echo '    for (var i=from; i<=to; i++) {'."\n";
    echo     '    if (document.getElementById("quiz_" + i)) {'."\n";
    echo '            document.getElementById("quiz_" + i).checked = obj.checked;'."\n";
    echo '        }'."\n";
    echo '    }'."\n";
    echo '}'."\n";
    echo '//]]>'."\n";
    echo '</script>'."\n";

    echo '<form action="admin.php?a=admin&id='.$cmid.'&act=setbookinstances" method="post" id="mform1">';
    echo '<div style="width:600px">';
    echo '<a href="#" onclick="expandall();return false;">Show All</a>';
    echo ' / ';
    echo '<a href="#" onclick="collapseall();return false;">Hide All</a>';
    echo '<br />';

    //vivod
    $count = 0;

    $submitonclick = array();
    $submitonclicktop = array();

    if (! empty($publishers)) {
        foreach ($publishers as $publisher => $levels) {
            $count++;
            echo '<br /><a href="#" onclick="toggle(\'comments_'.$count.'\');return false">
                  <span id="comments_'.$count.'indicator"><img src="'.$CFG->wwwroot.'/mod/reader/pix/open.gif" alt="Opened folder" /></span></a> ';
            echo ' <b>'.$publisher.' &nbsp;</b>';

            echo '<span id="comments_'.$count.'"><input type="checkbox" name="installall['.$count.']" onclick="setChecked(this,'.$checked->publishers[$publisher][0].','.end($checked->publishers[$publisher]).')" value="" /><span id="seltext_'.$count.'">Select All</span>';

            $topsubmitonclick = $count;
            foreach ($levels as $level => $bookids) {
                $count++;

                echo '<div style="padding-left:40px;padding-top:10px;padding-bottom:10px;"><a href="#" onclick="toggle(\'comments_'.$count.'\');return false">
                      <span id="comments_'.$count.'indicator"><img src="'.$CFG->wwwroot.'/mod/reader/pix/open.gif" alt="Opened folder" /></span></a> ';

                echo '<b>'.$level.' &nbsp;</b>';
                echo '<span id="comments_'.$count.'"><input type="checkbox" name="installall['.$count.']" onclick="setChecked(this,'.$checked->levels[$publisher][$level][0].','.end($checked->levels[$publisher][$level]).')" value="" /><span id="seltext_'.$count.'">Select All</span>';

                foreach ($bookids as $bookid => $book) {
                    echo '<div style="padding-left:20px;"><input type="checkbox" name="quiz[]" id="quiz_'.$uniqueids[$bookid].'" value="'.$bookid.'"';
                    if ($book->checked) {
                        echo ' checked="checked"';
                        $submitonclick[$count] = 1;
                        $submitonclicktop[$topsubmitonclick] = 1;
                    }
                    echo ' /> &nbsp; '.$book->name.'</div>';
                }
                echo '</span></div>';
            }
            echo '</span>';
        }

        echo '<div style="margin-top:40px;margin-left:200px;"><input type="submit" name="showquizzes" value="Show Students Selected Quizzes" /></div>';
    }

    echo '<input type="hidden" name="step" value="1" />';

    echo '</div>';
    echo '</form>';

    echo '<script type="text/javascript">'."\n";
    echo '//<![CDATA['."\n";

    echo 'var vh_numspans = '.$count.';'."\n";
    echo 'collapseall();'."\n";

    foreach ($submitonclicktop as $key => $value) {
        echo 'expand("comments_'.$key.'");'."\n";
    }
    foreach ($submitonclick as $key => $value) {
        echo 'expand("comments_'.$key.'");'."\n";
    }

    echo '//]]>'."\n";
    echo '</script>'."\n";

    echo $OUTPUT->box_end();
}

/**
 * reader_datetime_selector
 *
 * @param xxx $name
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_datetime_selector($name, $value, $disabled) {
    $output = '';

    $year  = array_combine(range(1970, 2020), range(1970, 2020));
    $month = array_combine(range(1, 12), range(1, 12));
    $day   = array_combine(range(1, 31), range(1, 31));
    $hour  = range(0, 23);
    $min   = range(0, 59);

    // convert months to month names
    foreach ($month as $m) {
        $month[$m] = userdate(gmmktime(12,0,0,$m,15,2000), "%B");
    }

    // convert hours to double-digits
    foreach ($hour as $h) {
        $hour[$h] = sprintf('%02d', $h);
    }

    // convert minutes to double-digits
    foreach ($min as $m) {
        $min[$m] = sprintf('%02d', $m);
    }

    $defaultvalue = ($value==0 ? time() : $value);
    $fields = array('year' => '%Y',  'month' => '%m', 'day' => '%d', 'hour' => '%H', 'min'  => '%M');
    foreach ($fields as $field => $fmt) {

        $selected = intval(gmstrftime($fmt, $defaultvalue));
        $output .= html_writer::select($$field,  $name.'_'.$field,  $selected, '', array('disabled' => $disabled));

        // add separator, if necessary
        switch ($field) {
            case 'day': $output .= ' &nbsp; '; break;
            case 'hour': $output .= ':'; break;
        }
    }

    // javascript to toggle "disable" property of select elements
    $onchange = 'var obj = document.getElementsByTagName("select");'.
                'if (obj) {'.
                    'var i_max = obj.length;'.
                    'for (var i=0; i<i_max; i++) {'.
                        'if (obj[i].id && obj[i].id.indexOf("menu'.$name.'_")==0) {'.
                            'obj[i].disabled = this.checked;'.
                        '}'.
                    '}'.
                '}';

    // add "disabled" checkbox
    $params = array('id'   => 'id_'.$name.'_disabled',
                    'name' => $name.'_disabled',
                    'type' => 'checkbox',
                    'value' => 1,
                    'onchange' => $onchange);
    if ($disabled) {
        $params['checked'] = 'checked';
    }
    $output .= html_writer::empty_tag('input', $params);
    $output .= get_string('disable');

    return $output;
}

/**
 * reader_grade_selector
 *
 * @param xxx $name
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_grade_selector($name, $value) {
    $grades = range(0, 100);
    foreach ($grades as $g) {
        $grades[$g] = "$g %";
    }
    $grades = array('' => '') + $grades;
    return html_writer::select($grades, $name, $value, '');
}

/**
 * reader_duration_selector
 *
 * @param xxx $name
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_duration_selector($name, $value) {

    $duration = array_combine(range(0, 50, 10), range(0, 50, 10)) +
                array_combine(range(1*60, 5*60, 60), range(1, 5)) +
                array_combine(range(10*60, 15*60, 300), range(10, 15, 5));

    foreach ($duration as $num => $text) {
        if ($num < 60) {
            if ($text==1) {
                $text .= ' second';
            } else {
                $text .= ' seconds';
            }
        } else if ($num <= 3600) {
            if ($text==1) {
                $text .= ' minute';
            } else {
                $text .= ' minutes';
            }
        } else {
            if ($text==1) {
                $text .= ' hour';
            } else {
                $text .= ' hours';
            }
        }
        $duration[$num] = $text;
    }
    $duration = array('' => '') + $duration;
    return html_writer::select($duration, $name, $value, '');
}
