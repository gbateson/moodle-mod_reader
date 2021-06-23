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
require_once($CFG->dirroot.'/mod/reader/adminlib.php');
require_once($CFG->dirroot.'/mod/reader/locallib.php');
require_once($CFG->dirroot.'/mod/reader/renderer.php');

//print_object($_POST);
//print_object($_GET);
//die;

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/lib/excellib.class.php');
require_once($CFG->dirroot.'/lib/tablelib.php');
require_once($CFG->dirroot.'/question/editlib.php');

$id                     = optional_param('id', 0, PARAM_INT);
$r                      = optional_param('r',  0, PARAM_INT);
$a                      = optional_param('a', null, PARAM_CLEAN);
$act                    = optional_param('act', null, PARAM_CLEAN);
$quizzesid              = reader_optional_param_array('quizzesid', null, PARAM_CLEAN);
$publisher              = optional_param('publisher', null, PARAM_CLEAN);
$publisherex            = optional_param('publisherex', null, PARAM_CLEAN);
$difficulty             = optional_param('difficulty', null, PARAM_CLEAN);
$todifficulty           = optional_param('todifficulty', null, PARAM_CLEAN);
$difficultyex           = optional_param('difficultyex', null, PARAM_CLEAN);
$level                  = optional_param('level', null, PARAM_CLEAN);
$tolevel                = optional_param('tolevel', null, PARAM_CLEAN);
$topublisher            = optional_param('topublisher', null, PARAM_CLEAN);
$levelex                = optional_param('levelex', null, PARAM_CLEAN);
$points                 = optional_param('points', null, PARAM_CLEAN);
$topoints               = optional_param('topoints', null, PARAM_CLEAN);
$gid                    = optional_param('gid', null, PARAM_CLEAN);
$excel                  = optional_param('excel', null, PARAM_CLEAN);
$del                    = optional_param('del', null, PARAM_CLEAN);
$attemptid              = optional_param('attemptid', null, PARAM_CLEAN);
$restoreattemptid       = optional_param('restoreattemptid', null, PARAM_CLEAN);
$upassword              = optional_param('upassword', null, PARAM_CLEAN);
$groupids               = reader_optional_param_array('groupids', 0, PARAM_INT);
$activehours            = optional_param('activehours', null, PARAM_CLEAN);
$messagetext            = optional_param('messagetext', null, PARAM_CLEAN);
$messageformat          = optional_param('messageformat', 0, PARAM_INT);
$bookid                 = reader_optional_param_array('bookid', null, PARAM_CLEAN);
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
$changelevel            = optional_param('changelevel', null, PARAM_CLEAN);
$searchtext             = optional_param('searchtext', null, PARAM_CLEAN);
$needip                 = optional_param('needip', null, PARAM_CLEAN);
$setip                  = optional_param('setip', null, PARAM_CLEAN);
$allowpromotion         = optional_param('allowpromotion', null, PARAM_CLEAN);
$stoplevel              = optional_param('stoplevel', null, PARAM_CLEAN);
$ajax                   = optional_param('ajax', null, PARAM_CLEAN);
$changeallstartlevel    = optional_param('changeallstartlevel', null, PARAM_INT);
$changeallcurrentlevel  = optional_param('changeallcurrentlevel', null, PARAM_INT);
$changeallstoplevel     = optional_param('changeallstoplevel', null, PARAM_INT);
$changeallpromotion     = optional_param('changeallpromotion', null, PARAM_INT);
$changeallgoal          = optional_param('changeallgoal', null, PARAM_INT);
$userimagename          = optional_param('userimagename', null, PARAM_CLEAN);
$award                  = optional_param('award', null, PARAM_CLEAN);
$student                = reader_optional_param_array('student', null, PARAM_CLEAN);
$useonlythiscourse      = optional_param('useonlythiscourse', null, PARAM_CLEAN);
$ipmask                 = optional_param('ipmask', 3, PARAM_CLEAN);
$fromtime               = optional_param('fromtime', 86400, PARAM_CLEAN);
$maxtime                = optional_param('maxtime', 1800, PARAM_CLEAN);
$cheated                = optional_param('cheated', null, PARAM_CLEAN);
$uncheated              = optional_param('uncheated', null, PARAM_CLEAN);
$findcheated            = optional_param('findcheated', null, PARAM_CLEAN);
$separategroups         = optional_param('separategroups', null, PARAM_CLEAN);
$levelall               = optional_param('levelall', null, PARAM_CLEAN);
$levelc                 = reader_optional_param_array('levelc', null, PARAM_CLEAN);
$wordsorpoints          = optional_param('wordsorpoints', null, PARAM_INT);
$setgoal                = optional_param('setgoal', null, PARAM_CLEAN);
$wordscount             = optional_param('wordscount', null, PARAM_CLEAN);
$viewasstudent          = optional_param('viewasstudent', null, PARAM_CLEAN);
$booksratingbest        = optional_param('booksratingbest', null, PARAM_CLEAN);
$booksratinglevel       = optional_param('booksratinglevel', null, PARAM_CLEAN);
//$booksratinglevel       = optional_param('booksratinglevel');
$booksratingterm        = optional_param('booksratingterm', null, PARAM_CLEAN);
$booksratingwithratings = optional_param('booksratingwithratings', null, PARAM_CLEAN);
$booksratingshow        = optional_param('booksratingshow', null, PARAM_CLEAN);
$quiz                   = reader_optional_param_array('quiz', null, PARAM_CLEAN);
$sametitlekey           = optional_param('sametitlekey', null, PARAM_CLEAN);
$sametitleid            = optional_param('sametitleid', null, PARAM_CLEAN);
$wordstitlekey          = optional_param('wordstitlekey', null, PARAM_CLEAN);
$wordstitleid           = optional_param('wordstitleid', null, PARAM_CLEAN);
$leveltitlekey          = optional_param('leveltitlekey', null, PARAM_CLEAN);
$leveltitleid           = optional_param('leveltitleid', null, PARAM_CLEAN);
$publishertitlekey      = optional_param('publishertitlekey', null, PARAM_CLEAN);
$publishertitleid       = optional_param('publishertitleid', null, PARAM_CLEAN);
$checkattempt           = optional_param('checkattempt', null, PARAM_CLEAN);
$checkattemptvalue      = optional_param('checkattemptvalue', 0, PARAM_INT);
$book                   = optional_param('book', 0, PARAM_INT);
$noquizuserid           = reader_optional_param_array('noquizuserid', null, PARAM_CLEAN);
$withoutdayfilter       = optional_param('withoutdayfilter', null, PARAM_CLEAN);
$numberofsections       = optional_param('numberofsections', null, PARAM_CLEAN);
$ct                     = optional_param('ct', null, PARAM_CLEAN);
$adjustscoresupbooks    = reader_optional_param_array('adjustscoresupbooks', null, PARAM_CLEAN);
$adjustscoresaddpoints  = optional_param('adjustscoresaddpoints', null, PARAM_CLEAN);
$adjustscoresupall      = optional_param('adjustscoresupall', null, PARAM_CLEAN);
$adjustscorespand       = optional_param('adjustscorespand', null, PARAM_CLEAN);
$adjustscorespby        = optional_param('adjustscorespby', null, PARAM_CLEAN);
$sctionoption           = optional_param('sctionoption', null, PARAM_CLEAN);
$studentuserid          = optional_param('studentuserid', 0, PARAM_INT);
$studentusername        = optional_param('studentusername', null, PARAM_CLEAN);
$bookquiznumber         = optional_param('bookquiznumber', 0, PARAM_INT);

$tab = optional_param('tab', 0, PARAM_INT);

$readercfg = get_config('mod_reader');

reader_change_to_teacherview();

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

require_login($course->id, true, $cm);
$reader = mod_reader::create($reader, $cm, $course);

reader_add_to_log($course->id, 'reader', 'admin area', 'admin.php?id='.$cm->id, $cm->instance, $cm->id);

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

if (has_capability('mod/reader:addinstance', $contextmodule) && $quizzesid) {
    $params = array('a' => 'admin', 'id' => $id, 'act' => 'addquiz');
    $continue_url = new moodle_url('/mod/reader/admin.php', $params);
    if (empty($publisher) && ($publisherex == '0')) {
        print_error('choosepublisher', 'mod_reader', $continue_url);
    }
    else if (! isset($difficulty) && $difficulty != 0 && $difficultyex != 0 && ! $difficultyex) {
        print_error('choosedifficulty', 'mod_reader', $continue_url);
    }
    else if (! isset($level) && ($levelex == '0')) {
        print_error('chooselevel', 'mod_reader', $continue_url);
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
        $newquiz = new stdClass();
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

        if ($points) {
            $newquiz->points = $points;
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

        $DB->insert_record('reader_books', $newquiz);
    }

    $message_forteacher = '<center><h3>'.get_string('quizzesadded', 'mod_reader').'</h3></center><br /><br />';

    reader_add_to_log($course->id, 'reader', 'AA-Quizzes Added', 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
}

if (has_capability('mod/reader:manageattempts', $contextmodule) && $act == 'viewattempts') {
    if ($attemptid) {
        $DB->set_field('reader_attempts', 'deleted', 1, array('id' => $attemptid));
        reader_add_to_log($course->id, 'reader', 'AA-reader_deleted_attempts', 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
    }
    if ($bookquiznumber) {
        if ($studentuserid==0) {
            $studentuserid = $DB->get_field('user', 'id', array('username' => $studentusername));
        }
        $DB->set_field('reader_attempts', 'deleted', 0, array('userid' => $studentuserid, 'quizid' => $bookquiznumber));
        reader_add_to_log($course->id, 'reader', 'AA-reader_restore_attempts', 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
    }
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $messagetext) {
    $message = (object)array(
        'readerid'      => $cm->instance,
        'teacherid'     => $USER->id,
        'groupids'      => implode(',', $groupids),
        'messagetext'   => $messagetext,
        'messageformat' => $messageformat
    );
    if ($activehours) {
        $message->timefinish = time() + ($activehours * 60 * 60);
    } else {
        $message->timefinish = 0; // i.e. display indefinitely
    }
    $message->timemodified = time();

    if ($editmessage) {
        $message->id = $editmessage;
        $DB->update_record('reader_messages', $message);
    } else {
        $DB->insert_record('reader_messages', $message);
    }

    reader_add_to_log($course->id, 'reader', 'AA-Message Added', 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $deletemessage) {
    $DB->delete_records('reader_messages', array('id' => $deletemessage));

    reader_add_to_log($course->id, 'reader', 'AA-Message Deleted', 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $checkattempt && $ajax == 'true') {
    $DB->set_field('reader_attempts',  'checkbox',  $checkattemptvalue, array('id' => $checkattempt));
    die;
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $bookid) {
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

    reader_add_to_log($course->id, 'reader', 'AA-Books status changed', 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $deletequiz && $deleteallattempts) {
    $DB->delete_records('reader_attempts', array('quizid' => $deletequiz, 'readerid' => $reader->id));
    reader_add_to_log($course->id, 'reader', 'AA-Attempts Deleted', 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $deletebook && $deletequiz) {
    $params = array('quizid' => $deletequiz, 'readerid' => $reader->id, 'deleted' => 0);
    if ($DB->count_records('reader_attempts', $params) == 0) {
        $DB->delete_records('reader_books', array('id' => $deletebook));
        $DB->delete_records('reader_attempts', $params); // remove deleted attempts
    } else {
        $needdeleteattemptsfirst = $DB->get_records('reader_attempts', $params, 'timefinish');
    }
    reader_add_to_log($course->id, 'reader', 'AA-Book Deleted', 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $ajax == 'true' && isset($sametitlekey)) {
    $DB->set_field('reader_books',  'sametitle',  $sametitlekey, array('id' => $sametitleid));
    echo $sametitlekey;
    die;
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $ajax == 'true' && isset($wordstitlekey)) {
    $DB->set_field('reader_books',  'words',  $wordstitlekey, array('id' => $wordstitleid));
    echo $wordstitlekey;
    die;
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $ajax == 'true' && isset($publishertitlekey)) {
    $DB->set_field('reader_books',  'publisher',  $publishertitlekey, array('id' => $publishertitleid));
    echo $publishertitlekey;
    die;
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $ajax == 'true' && isset($leveltitlekey)) {
    $DB->set_field('reader_books',  'level',  $leveltitlekey, array('id' => $leveltitleid));
    echo $leveltitlekey;
    die;
}

if (has_capability('mod/reader:addinstance', $contextmodule) && ($changelevel || $changelevel == 0) && $slevel) {
    $params = array('userid' => $userid, 'readerid' => $reader->id);
    if ($studentlevel = $DB->get_record('reader_levels', $params)) {
        $studentlevel->time = time();
        $studentlevel->$slevel = $changelevel;
        $DB->update_record('reader_levels', $studentlevel);
    } else {
        $studentlevel = (object)array(
            'userid'         => $userid,
            'startlevel'     => $changelevel,
            'currentlevel'   => $changelevel,
            'readerid'       => $reader->id,
            'allowpromotion' => 1,
            'stoplevel'      => 99,
            'goal'           => 0,
            'time'           => time()
        );
        $studentlevel->id = $DB->insert_record('reader_levels', $studentlevel);
    }
    reader_add_to_log($course->id, 'reader', substr("AA-Student Level Changed ({$userid} {$slevel} to {$changelevel})", 0, 39), 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
    if ($ajax == 'true') {
        echo reader_level_menu($userid, $studentlevel, $slevel);
        die;
    }
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $sctionoption == 'massdifficultychange' && (isset($difficulty) || $difficulty == 0) && (isset($todifficulty) || $todifficulty == 0) && isset($publisher)) {
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
    reader_add_to_log($course->id, 'reader', substr("AA-Mass changes difficulty ({$difficulty} to {$todifficulty})", 0, 39), 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $level && $tolevel && $publisher) {
    $DB->set_field('reader_books',  'level',  $tolevel, array('level' => $level,  'publisher' => $publisher));
    reader_add_to_log($course->id, 'reader', substr("AA-Mass changes level ({$level} to {$tolevel})", 0, 39), 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $topublisher && $publisher) {
    $DB->set_field('reader_books',  'publisher',  $topublisher, array('publisher' => $publisher));
    reader_add_to_log($course->id, 'reader', substr("AA-Mass changes publisher ({$publisher} to {$topublisher})", 0, 39), 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $points && $topoints && $publisher) {
    if ($reader->bookinstances == 0) {
        $DB->set_field('reader_books',  'points',  $topoints, array('points' => $points,  'publisher' => $publisher));
    } else {
        $data = $DB->get_records('reader_books', array('publisher' => $publisher));
        foreach ($data as $key => $value) {
            $pointsstring .= $value->id.',';
        }
        $pointsstring = substr($pointsstring, 0, -1);
        $DB->execute('UPDATE {reader_book_instances} SET points = ? WHERE points = ? and readerid = ? and bookid IN (?)', array($topoints,$points,$reader->id,$pointsstring));
    }
    reader_add_to_log($course->id, 'reader', substr("AA-Mass changes points ({$points} to {$topoints})", 0, 39), 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $act == 'changereaderlevel' && ($difficulty || $difficulty == 0) && empty($points)) {
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
    reader_add_to_log($course->id, 'reader', substr("AA-Change difficulty individual ({$bookid} {$slevel} to {$difficulty})", 0, 39), 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
  }
  if ($ajax == 'true') {
      $book = $DB->get_record('reader_books', array('id' => $bookid));
      echo reader_difficulty_menu(reader_get_reader_difficulty($reader, $bookid), $book->id, $reader);
      die;
  }
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $act == 'changereaderlevel' && $points) {
  if ($reader->bookinstances == 0) {
    if ($DB->get_record('reader_books', array('id' => $bookid))) {
        $DB->set_field('reader_books',  'points',  $points, array('id' => $bookid));
    }
    reader_add_to_log($course->id, 'reader', substr("AA-Change points ({$bookid} {$slevel} to {$points})",0,39), 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
  } else {
    if ($DB->get_record('reader_book_instances', array('readerid' => $reader->id, 'bookid' => $bookid))) {
        $DB->set_field('reader_book_instances',  'points',  $points, array('readerid' => $reader->id,  'bookid' => $bookid));
    }
    reader_add_to_log($course->id, 'reader', substr("AA-Change points individual ({$bookid} {$slevel} to {$points})",0,39), 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
  }
  if ($ajax == 'true') {
      $book = $DB->get_record('reader_books', array('id' => $bookid));
      echo reader_points_menu(reader_get_reader_points($reader, $bookid), $book->id, $reader);
      die;
  }
}

if (has_capability('mod/reader:manageattempts', $contextmodule) && $viewasstudent) {
    $link = "gid={$gid}&searchtext={$searchtext}&page={$page}&sort={$sort}&orderby={$orderby}";
    $location = "view.php?a=quizzes&id={$id}";
    reader_change_to_studentview($viewasstudent, $link, $location);
    // script will finish here
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $act == 'studentslevels' && $setgoal) {
    $params = array('userid' => $userid, 'readerid' => $reader->id);
    if ($studentlevel = $DB->get_record('reader_levels', $params)) {
        $studentlevel->goal = $setgoal;
        $DB->update_record('reader_levels',  $studentlevel);
    } else {
        $studentlevel = (object)array(
            'userid'         => $userid,
            'startlevel'     => 0,
            'currentlevel'   => 0,
            'readerid'       => $reader->id,
            'allowpromotion' => 1,
            'stoplevel'      => 99,
            'goal'           => $setgoal,
            'time'           => time()
        );
        $studentlevel->id = $DB->insert_record('reader_levels', $studentlevel);
    }
    reader_add_to_log($course->id, 'reader', "AA-Change Student Goal ({$setgoal})", 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
    if ($ajax == 'true') {
        echo reader_goals_menu($userid, $studentlevel, 'goal', 3, $reader);
        die;
    }
}

if (has_capability('mod/reader:addinstance', $contextmodule) && isset($allowpromotion) && $userid) {
    $params = array('userid' => $userid, 'readerid' => $reader->id);
    if ($studentlevel = $DB->get_record('reader_levels', $params)) {
        $studentlevel->allowpromotion = $allowpromotion;
        $DB->update_record('reader_levels',  $studentlevel);
    } else {
        $studentlevel = (object)array(
            'userid'         => $userid,
            'startlevel'     => 0,
            'currentlevel'   => 0,
            'readerid'       => $reader->id,
            'allowpromotion' => $allowpromotion,
            'stoplevel'      => 99,
            'goal'           => 0,
            'time'           => time()
        );
        $studentlevel->id = $DB->insert_record('reader_levels', $studentlevel);
    }
    reader_add_to_log($course->id, 'reader', substr("AA-Student AllowPromotion Changed ({$userid} set to {$allowpromotion})",0,39), 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
    if ($ajax == 'true') {
        echo reader_promo_menu($userid, $studentlevel, 'allowpromotion', 1);
        die;
    }
}

if (has_capability('mod/reader:addinstance', $contextmodule) && isset($stoplevel) && $userid) {
    $params = array('userid' => $userid, 'readerid' => $reader->id);
    if ($studentlevel = $DB->get_record('reader_levels', $params)) {
        $studentlevel->stoplevel = $stoplevel;
        $DB->update_record('reader_levels', $studentlevel);
    } else {
        $studentlevel = (object)array(
            'userid'         => $userid,
            'startlevel'     => 0,
            'currentlevel'   => 0,
            'readerid'       => $reader->id,
            'allowpromotion' => 1,
            'stoplevel'      => $stoplevel,
            'goal'           => 0,
            'time'           => time()
        );
        $studentlevel->id = $DB->insert_record('reader_levels', $level);
    }
    reader_add_to_log($course->id, 'reader', substr("AA-Student Promotion Stop Changed ({$userid} set to {$stoplevel})",0,39), 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
    if ($ajax == 'true') {
        echo reader_promotionstop_menu($userid, $studentlevel, 'stoplevel', 2);
        die;
    }
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $setip) {
    $params = array('userid' => $userid, 'readerid' => $reader->id);
    if ($users_list = $DB->get_record('reader_strict_users_list', $params)) {
        $users_list->needtocheckip = $needip;
        $DB->update_record('reader_strict_users_list', $users_list);
    } else {
        $users_list = (object)array(
            'userid' => $userid,
            'readerid' => $reader->id,
            'needtocheckip' => $needip
        );
        $users_list->id = $DB->insert_record('reader_strict_users_list', $users_list);
    }
    reader_add_to_log($course->id, 'reader', substr("AA-Student check ip Changed ({$userid} {$needip})",0,39), 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
    if ($ajax == 'true') {
        echo reader_ip_menu($userid, $reader);
        die;
    }
}

if (has_capability('mod/reader:addinstance', $contextmodule) && isset($changeallstartlevel)) {
    foreach ($coursestudents as $coursestudent) {
        $params = array('userid' => $coursestudent->id, 'readerid' => $reader->id);
        if ($studentlevel = $DB->get_record('reader_levels', $params)) {
            $studentlevel->startlevel = $changeallstartlevel;
            $DB->update_record('reader_levels', $studentlevel);
        } else {
            $studentlevel = (object)array(
                'readerid'       => $reader->id,
                'userid'         => $coursestudent->id,
                'startlevel'     => $changeallstartlevel,
                'currentlevel'   => $changeallstartlevel,
                'stoplevel'      => 99,
                'allowpromotion' => 1,
                'goal'           => $reader->goal,
                'time'           => time()
            );
            $studentlevel->id = $DB->insert_record('reader_levels', $studentlevel);
        }
        reader_add_to_log($course->id, 'reader', substr("AA-changeallstartlevel userid: {$coursestudent->id}, startlevel={$changeallstartlevel}",0,39), 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
    }
}

if (has_capability('mod/reader:addinstance', $contextmodule) &&  isset($changeallcurrentlevel)) {
    foreach ($coursestudents as $coursestudent) {
        $params = array('userid' => $coursestudent->id, 'readerid' => $reader->id);
        if ($studentlevel = $DB->get_record('reader_levels', $params)) {
            $studentlevel->currentlevel = $changeallcurrentlevel;
            $DB->update_record('reader_levels', $studentlevel);
        } else {
            $studentlevel = (object)array(
                'readerid'       => $reader->id,
                'userid'         => $coursestudent->id,
                'startlevel'     => $changeallcurrentlevel,
                'currentlevel'   => $changeallcurrentlevel,
                'stoplevel'      => 99,
                'allowpromotion' => 1,
                'goal'           => $reader->goal,
                'time'           => time()
            );
            $studentlevel->id = $DB->insert_record('reader_levels', $studentlevel);
        }
        reader_add_to_log($course->id, 'reader', substr("AA-changeallcurrentlevel userid: {$coursestudent->id}, currentlevel={$changeallcurrentlevel}",0,39), 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
    }
}

if (has_capability('mod/reader:addinstance', $contextmodule) && isset($changeallpromotion)) {
    foreach ($coursestudents as $coursestudent) {
        $params = array('userid' => $coursestudent->id, 'readerid' => $reader->id);
        if ($studentlevel = $DB->get_record('reader_levels', $params)) {
            $studentlevel->allowpromotion = $changeallpromotion;
            $DB->update_record('reader_levels', $studentlevel);
        } else {
            $studentlevel = (object)array(
                'readerid'       => $reader->id,
                'userid'         => $coursestudent->id,
                'startlevel'     => 0,
                'currentlevel'   => 0,
                'stoplevel'      => 99,
                'allowpromotion' => $changeallpromotion,
                'goal'           => $reader->goal,
                'time'           => time()
            );
            $studentlevel->id = $DB->insert_record('reader_levels', $studentlevel);
        }
        reader_add_to_log($course->id, 'reader', substr("AA-Student Promotion Stop Changed ({$coursestudent->id} set to {$stoplevel})",0,39), 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
    }
}

if (has_capability('mod/reader:addinstance', $contextmodule) && isset($changeallstoplevel) && $gid) {
    foreach ($coursestudents as $coursestudent) {
        $params = array('userid' => $coursestudent->id, 'readerid' => $reader->id);
        if ($studentlevel = $DB->get_record('reader_levels', $params)) {
            $studentlevel->stoplevel = $changeallstoplevel;
            $DB->update_record('reader_levels', $studentlevel);
        } else {
            $studentlevel = (object)array(
                'userid'         => $coursestudent->id,
                'startlevel'     => 0,
                'currentlevel'   => 0,
                'readerid'       => $reader->id,
                'allowpromotion' => 1,
                'stoplevel'      => $changeallstoplevel,
                'goal'           => 0,
                'time'           => time()
            );
            $studentlevel->id = $DB->insert_record('reader_levels', $studentlevel);
        }
        reader_add_to_log($course->id, 'reader', substr("AA-Student AllowPromotion Changed ({$coursestudent->id} set to {$changeallstoplevel})",0,39), 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
    }
}

if (has_capability('mod/reader:addinstance', $contextmodule) && isset($changeallgoal)) {
    foreach ($coursestudents as $coursestudent) {
        $params = array('userid' => $coursestudent->id, 'readerid' => $reader->id);
        if ($studentlevel = $DB->get_record('reader_levels', $params)) {
            $studentlevel->goal = $changeallgoal;
            $DB->update_record('reader_levels',  $studentlevel);
        } else {
            $studentlevel = (object)array(
                'userid'         => $coursestudent->id,
                'startlevel'     => 0,
                'currentlevel'   => 0,
                'readerid'       => $reader->id,
                'allowpromotion' => 1,
                'stoplevel'      => 99,
                'goal'           => $changeallgoal,
                'time'           => time()
            );
            $studentlevel->id = $DB->insert_record('reader_levels', $studentlevel);
        }
        reader_add_to_log($course->id, 'reader', substr("AA-goal userid: {$coursestudent->id}, goal={$changeallgoal}",0,39), 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
    }
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $act == 'awardextrapoints' && $award && $student) {
    $useridold = $USER->id;
    if ($bookdata = $DB->get_record('reader_books', array('name' => $award))) {
        foreach ($student as $s) {

            $select = 'MAX(attempt)';
            $from   = '{reader_attempts}';
            $where  = 'readerid = ? AND userid = ? AND timefinish > ? AND credit <> ?';
            $params = array($reader->id, $s, 0, 1);

            if($attemptnumber = $DB->get_field_sql("SELECT $select FROM $from WHERE $where", $params)) {
                $attemptnumber += 1;
            } else {
                $attemptnumber = 1;
            }

            $USER = $s;

            $attempt = reader_create_attempt($reader, $attemptnumber, $bookdata->id, true);

            // Save the attempt
            if (! $attempt->id = $DB->insert_record('reader_attempts', $attempt)) {
                throw new reader_exception('Could not create new attempt');
            }

            $totalgrade = 0;
            $answersgrade = $DB->get_records('reader_question_instances', array('quiz' => $bookdata->quizid)); // Count Grades (TotalGrade)
            foreach ($answersgrade as $answersgrade_) {
                $totalgrade += $answersgrade_->grade;
            }

            $attempt->sumgrades    = $totalgrade;
            $attempt->percentgrade = 100;
            $attempt->passed       = 1;
            $attempt->credit       = 0;
            $attempt->cheated      = 0;
            $attempt->deleted      = 0;

            $time = time() - $reader->get_delay($s->id);
            $attempt->timefinish   = $time;
            $attempt->timecreated  = $time;
            $attempt->timemodified = $time;

            $DB->update_record('reader_attempts', $attempt);
            reader_add_to_log($course->id, 'reader', "AWP (userid: {$s}; set: {$award})", 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
        }
    }
    $USER->id = $useridold;
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $cheated) {
    list($cheated1, $cheated2) = explode('_', $cheated);
    $DB->set_field('reader_attempts',  'cheated',  1, array('id' => $cheated1));
    $DB->set_field('reader_attempts',  'cheated',  1, array('id' => $cheated2));
    reader_add_to_log($course->id, 'reader', 'AA-cheated', 'admin.php?id='.$cm->id, $cm->instance, $cm->id);

    $userid1 = $DB->get_record('reader_attempts', array('id' => $cheated1));
    $userid2 = $DB->get_record('reader_attempts', array('id' => $cheated2));

    $data = new stdClass();
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

    if ($reader->notifycheating == 1) {
        $user1 = $DB->get_record('user', array('id' => $userid1->userid));
        $user2 = $DB->get_record('user', array('id' => $userid2->userid));
        email_to_user($user1,get_admin(),'Cheated notice',$reader->cheatedmessage);
        email_to_user($user2,get_admin(),'Cheated notice',$reader->cheatedmessage);
    }
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $uncheated) {
    list($cheated1, $cheated2) = explode('_', $uncheated);
    $DB->set_field('reader_attempts',  'cheated',  0, array('id' => $cheated1));
    $DB->set_field('reader_attempts',  'cheated',  0, array('id' => $cheated2));
    reader_add_to_log($course->id, 'reader', "AA-set passed (uncheated)", 'admin.php?id='.$cm->id, $cm->instance, $cm->id);

    $userid1 = $DB->get_record('reader_attempts', array('id' => $cheated1));
    $userid2 = $DB->get_record('reader_attempts', array('id' => $cheated2));

    $data = new stdClass();
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

    if ($reader->notifycheating == 1) {
        $user1 = $DB->get_record('user', array('id' => $userid1->userid));
        $user2 = $DB->get_record('user', array('id' => $userid2->userid));
        email_to_user($user1,get_admin(),'Points restored notice',$reader->clearedmessage);
        email_to_user($user2,get_admin(),'Points restored notice',$reader->clearedmessage);
    }
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $act == 'setgoal') {
    $DB->set_field('reader', 'wordsorpoints', $wordsorpoints, array('id' => $reader->id));

    if ($levelall) {
        $DB->delete_records('reader_goals', array('readerid' => $reader->id));
        if ($separategroups) {
            $data              = new stdClass();
            $data->groupid     = $separategroups;
            $data->readerid    = $reader->id;
            $data->level       = 0;
            $data->goal        = $levelall;
            $data->timemodified  = time();
            $DB->insert_record('reader_goals', $data);
        } else {
            $DB->set_field('reader', 'goal', $levelall);
        }
        reader_add_to_log($course->id, 'reader', "AA-wordsorpoints goal=$levelall", 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
    } else {
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
                $data->timemodified  = time();
                $dataid = $DB->insert_record('reader_goals', $data);
            }
        }
        reader_add_to_log($course->id, 'reader', "AA-wordsorpoints goal=$value", 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
    }
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $act == 'setbookinstances' && is_array($quiz)) {
    $DB->delete_records('reader_book_instances', array('readerid' => $reader->id));
    foreach ($quiz as $quiz_) {
        $oldbookdata = $DB->get_record('reader_books', array('id' => $quiz_));
        $data           = new stdClass();
        $data->readerid = $reader->id;
        $data->bookid   = $quiz_;
        $data->difficulty   = $oldbookdata->difficulty;
        $data->points   = $oldbookdata->points;
        //print_r($data);
        $DB->insert_record('reader_book_instances', $data);
    }
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $act == 'forcedtimedelay' && is_array($levelc)) {
    $DB->delete_records('reader_rates', array('readerid' => $reader->id, 'groupid' => $separategroups));
    foreach ($levelc as $key => $value) {
      if ($value) {
        $data             = new stdClass();
        $data->readerid   = $reader->id;
        $data->groupid    = (empty($separategroups) ? 0 : $separategroups);
        $data->level      = $key;
        $data->delay      = $value;
        $data->timemodified = time();
        $DB->insert_record('reader_rates', $data);
      }
    }
}

if (has_capability('mod/reader:addinstance', $contextmodule) && $book && is_array($noquizuserid)) {
    if (is_int($book) && $book > 0) {
        $quizid = $DB->get_field('reader_books', 'quizid', array('id' => $book));
        foreach ($noquizuserid as $key => $value) {
            if ($value) {
                $readerattempt = (object)array(
                    'uniqueid'     => reader_get_new_uniqueid($contextmodule->id, $quizid),
                    'readerid'     => $reader->id,
                    'userid'       => $value,
                    'bookid'       => $book,
                    'quizid'       => $quizid,
                    'attempt'      => 1,
                    'deleted'      => 0,
                    'sumgrades'    => 1,
                    'passed'       => 1,
                    'credit'       => 1,
                    'cheated'      => 0,
                    'deleted'      => 0,
                    'percentgrade' => 100,
                    'timestart'    => time(),
                    'timefinish'   => time(),
                    'timemodified' => time(),
                    'layout'       => '0,',
                    'bookrating'   => 1,
                    'ip'           => $_SERVER['REMOTE_ADDR'],
                );
                $readerattempt->id = $DB->insert_record('reader_attempts', $readerattempt);
            }
        }
    }
    $noquizreport = 'Done';
    unset($book);
}

if ((has_capability('mod/reader:managebooks', $contextmodule)) && $numberofsections && $act == 'changenumberofsectionsinquiz') {
    switch (true) {
        case (isset($reader->usecourse) && $reader->usecourse > 0):
            mod_reader::set_numsections($reader->usecourse, $numberofsections);
            break;
        case (isset($readercfg->usecourse) && $readercfg->usecourse > 0):
            mod_reader::set_numsections($readercfg->usecourse, $numberofsections);
            break;
    }
}

if ($act == 'adjustscores' && !empty($adjustscoresaddpoints) && !empty($adjustscoresupbooks)) {
    foreach ($adjustscoresupbooks as $attemptid) {
        $params = array('id' => $attemptid);
        if ($attempt = $DB->get_record('reader_attempts', $params)) {
            $percentgrade = ($attempt->percentgrade + $adjustscoresaddpoints);
            $DB->set_field('reader_attempts', 'percentgrade', $percentgrade, $params);
            $passed = (($percentgrade < $reader->minpassgrade) ? 0 : 1);
            $DB->set_field('reader_attempts', 'passed', $passed, $params);
        }
    }
    $adjustscorestext = 'Done';
}

if ($act == 'adjustscores' && !empty($adjustscoresupall) && !empty($adjustscorespand) && !empty($adjustscorespby)) {
    $select = 'percentgrade < ? AND percentgrade > ? AND quizid = ? AND deleted = ?';
    $params = array($adjustscorespand, $adjustscoresupall, $book, 0);
    if ($attempts = $DB->get_records_sql('reader_attempts', $select, $params)) {
        foreach ($attempts as $attempt) {
            $attempt->percentgrade = ($attempt->percentgrade + $adjustscorespby);
            $attempt->passed = (($attempt->percentgrade < $reader->minpassgrade) ? 0 : 1);
            $DB->update_record('reader_attempts', $attempt);
        }
    }
    $adjustscorestext = 'Done';
}

/// Print the page header

if ($excel && class_exists('XMLWriter')) {
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
    reader_add_to_log($course->id, 'reader', 'AA-excel', 'admin.php?id='.$cm->id, $cm->instance, $cm->id);
} else {
    $excel = null;
}

// Initialize $PAGE, compute blocks
$PAGE->set_url('/mod/reader/admin.php', array('id' => $cm->id));

$title = $course->shortname . ': ' . format_string($reader->name);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

$output = $PAGE->get_renderer('mod_reader');
$output->init($reader);

if (! $excel) {
    echo $output->header();

    echo '<script type="text/javascript" src="js/ajax.js"></script>'."\n";
    echo '<script type="application/x-javascript" src="js/jquery-1.4.2.min.js"></script>'."\n";
}

$alreadyansweredbooksid = array();

if (has_capability('mod/reader:viewreports', $contextmodule)) {
    if (! $excel) {
        echo $output->tabs();
        //require_once('tabs.php');
    }
} else {
    die;
}

if (! $excel) {
    echo $output->box_start('generalbox');
}

if (isset($message_forteacher)) {
    echo $message_forteacher;
}

if (! $excel) {
    $menu = array(
        'readerreports' => array(
            // new reader_menu_item($displaystring, $capability, $scriptname, $scriptparams)
            new reader_menu_item('reportquiztoreader', 'viewreports', 'admin.php', array('a' => 'admin', 'id' => $id, 'tab' => $tab, 'act' => 'reports')),
            new reader_menu_item('fullreportquiztoreader', 'viewreports', 'admin.php', array('a' => 'admin', 'id' => $id, 'tab' => $tab, 'act' => 'fullreports')),
            new reader_menu_item('summaryreportbyclassgroup', 'viewreports', 'admin.php', array('a' => 'admin', 'id' => $id, 'tab' => $tab, 'act' => 'reportbyclass')),
            new reader_menu_item('summaryreportbybooktitle', 'viewreports', 'admin.php', array('a' => 'admin', 'id' => $id, 'tab' => $tab, 'act' => 'summarybookreports')),
            new reader_menu_item('fullreportbybooktitle', 'viewreports', 'admin.php', array('a' => 'admin', 'id' => $id, 'tab' => $tab, 'act' => 'fullbookreports')),
            //new reader_menu_item('reportquiztoreader', 'viewreports', 'admin/reports.php', array('id' => $id, 'tab' => 31, 'mode' => 'usersummary')),
            //new reader_menu_item('fullreportquiztoreader', 'viewreports','admin/reports.php', array('id' => $id, 'tab' => 32, 'mode' => 'userdetailed')),
            //new reader_menu_item('summaryreportbyclassgroup', 'viewreports', 'admin/reports.php', array('id' => $id, 'tab' => 33, 'mode' => 'groupsummary')),
            //new reader_menu_item('summaryreportbybooktitle', 'viewreports', 'admin/reports.php', array('id' => $id, 'tab' => 34, 'mode' => 'booksummary')),
            //new reader_menu_item('fullreportbybooktitle', 'viewreports', 'admin/reports.php', array('id' => $id, 'tab' => 35, 'mode' => 'bookdetailed')),
        ),
        'quizmanagement' => array(
            new reader_menu_item('addquiztoreader', 'managebooks', 'admin.php', array('a' => 'admin', 'id' => $id, 'tab' => $tab, 'act' => 'addquiz')),
            //new reader_menu_item('uploadquiztoreader', 'managebooks', 'dlquizzes.php', array('id' => $id)),
            //new reader_menu_item('uploaddatanoquizzes', 'managebooks', 'dlquizzesnoq.php', array('id' => $id)),
            //new reader_menu_item('updatequizzes', 'managebooks', 'updatecheck.php', array('id' => $id, 'checker' => 1)),
            new reader_menu_item('updatequizzes', 'managebooks', 'admin/books.php', array('id' => $id, 'tab' => 43, 'mode' => 'download', 'type' => 1)),
            new reader_menu_item('uploadquiztoreader', 'managebooks', 'admin/books.php', array('id' => $id, 'tab' => 43, 'mode' => 'download', 'type' => 1)),
            new reader_menu_item('uploaddatanoquizzes', 'managebooks', 'admin/books.php', array('id' => $id, 'tab' => 44, 'mode' => 'download', 'type' => 0)),
            new reader_menu_item('editquiztoreader', 'managebooks', 'admin.php', array('a' => 'admin', 'id' => $id, 'tab' => $tab, 'act' => 'editquiz')),
            new reader_menu_item('setbookinstances', 'managebooks', 'admin.php', array('a' => 'admin', 'id' => $id, 'tab' => $tab, 'act' => 'setbookinstances')),
            //new reader_menu_item('forcedtimedelay', 'managebooks', 'admin.php', array('a' => 'admin', 'id' => $id, 'tab' => $tab, 'act' => 'forcedtimedelay')),
            new reader_menu_item('forcedtimedelay', 'managebooks', 'admin/users.php', array('id' => $id, 'tab' => 53, 'mode' => 'setrates')),
            new reader_menu_item('changenumberofsectionsinquiz', 'managebooks', 'admin.php', array('a' => 'admin', 'id' => $id, 'tab' => $tab, 'act' => 'changenumberofsectionsinquiz')),
        ),
        'attemptscoremanagement' => array(
            //new reader_menu_item('viewattempts', 'manageusers', 'admin.php', array('a' => 'admin', 'id' => $id, 'act' => 'viewattempts')),
            //new reader_menu_item('awardextrapoints', 'manageusers', 'admin.php', array('a' => 'admin', 'id' => $id, 'act' => 'awardextrapoints')),
            //new reader_menu_item('assignpointsbookshavenoquizzes', 'manageusers', 'admin.php', array('a' => 'admin', 'id' => $id, 'act' => 'assignpointsbookshavenoquizzes')),
            new reader_menu_item('viewattempts', 'manageusers', 'admin/reports.php', array('id' => $id, 'tab' => 32, 'mode' => 'userdetailed')),
            new reader_menu_item('awardextrapoints', 'manageusers', 'admin/reports.php', array('id' => $id, 'tab' => 31, 'mode' => 'usersummary')),
            new reader_menu_item('assignpointsbookshavenoquizzes', 'manageusers', 'admin/reports.php', array('id' => $id, 'tab' => 31, 'mode' => 'usersummary')),
            new reader_menu_item('adjustscores', 'manageusers', 'admin.php', array('a' => 'admin', 'id' => $id, 'tab' => $tab, 'act' => 'adjustscores')),
            new reader_menu_item('checksuspiciousactivity', 'manageattempts', 'admin.php', array('a' => 'admin', 'id' => $id, 'tab' => $tab, 'act' => 'checksuspiciousactivity')),
            new reader_menu_item('viewlogsuspiciousactivity', 'manageattempts', 'admin.php', array('a' => 'admin', 'id' => $id, 'tab' => $tab, 'act' => 'viewlogsuspiciousactivity')),
        ),
        'booklevelmanagement' => array(
            // new reader_menu_item($displaystring, $capability, $scriptname, $scriptparams)
            new reader_menu_item('changereaderlevel', 'managebooks', 'admin.php', array('a' => 'admin', 'id' => $id, 'tab' => $tab, 'act' => 'changereaderlevel')),
            //new reader_menu_item('createcoversets_t', 'managebooks', 'admin.php', array('a' => 'admin', 'id' => $id, 'tab' => $tab, 'act' => 'makepix_t')),
            //new reader_menu_item('createcoversets_l', 'managebooks', 'admin.php', array('a' => 'admin', 'id' => $id, 'tab' => $tab, 'act' => 'makepix_l')),
            new reader_menu_item('bookratingslevel', 'managebooks', 'admin.php', array('a' => 'admin', 'id' => $id, 'tab' => $tab, 'act' => 'bookratingslevel')),
        ),
        'studentmanagement' => array(
            // new reader_menu_item($displaystring, $capability, $scriptname, $scriptparams)
            new reader_menu_item('setgoal', 'manageusers', 'admin.php', array('a' => 'admin', 'id' => $id, 'tab' => $tab, 'act' => 'setgoal')),
            new reader_menu_item('studentslevels', 'manageusers', 'admin.php', array('a' => 'admin', 'id' => $id, 'tab' => $tab, 'act' => 'studentslevels')),
            new reader_menu_item('setmessage', 'manageusers', 'admin.php', array('a' => 'admin', 'id' => $id, 'tab' => $tab, 'act' => 'setmessage')),
            //new reader_menu_item('exportstudentrecords', 'manageusers', 'admin.php', array('a' => 'admin', 'id' => $id, 'tab' => $tab, 'act' => 'exportstudentrecords', 'excel' => 1)),
            //new reader_menu_item('importstudentrecord', 'manageusers', 'admin.php', array('a' => 'admin', 'id' => $id, 'tab' => $tab, 'act' => 'importstudentrecord')),
            new reader_menu_item('exportstudentrecords', 'manageusers', 'admin/users.php', array('id' => $id, 'tab' => 56, 'mode' => 'export')),
            new reader_menu_item('importstudentrecord', 'manageusers', 'admin/users.php', array('id' => $id, 'tab' => 55, 'mode' => 'import')),

        ),
    );
    $menu = new reader_menu($menu);
    echo $menu->out($contextmodule);

    // the "Edit" button has been moved to the nagivation tags
    //echo '<br /><hr />';

    //echo html_writer::start_tag('form', array('method'   => 'get',
    //                                          'onsubmit' => "this.target='_top'; return true;",
    //                                          'action'   => $CFG->wwwroot.'/course/mod.php'));
    //echo html_writer::start_tag('div');
    //echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'update', 'value' => $cm->id));
    //echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'return', 'value' => 'true'));
    //echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()));
    //echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => 'Change the main Reader settings'));
    //echo html_writer::end_tag('div');
    //echo html_writer::end_tag('form');

    // disable update check on this page, because this check is done on downloads page
    //if ($readercfg->update == 1) {
    //    if (time() - $readercfg->last_update > $readercfg->update_interval) {
    //      echo $output->box_start('generalbox');
    //      $days = round((time() - $readercfg->last_update) / (24 * 3600));
    //      print_string('needtocheckupdates', 'reader', $days);
    //      echo ' <a href="admin/books.php?id='.$id.'">YES</a> / <a href="admin.php?a=admin&id='.$id.'">NO</a></center>';
    //      echo $output->box_end();
    //    }
    //}
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

if ($act == 'addquiz' && has_capability('mod/reader:managebooks', $contextmodule)) {
    if (! $quizzesid) {
        if ($quizdata  = get_all_instances_in_course('quiz', $DB->get_record('course', array('id' => $reader->usecourse)), null, true)) {
        //if ($quizdata  = get_records('quiz')) {
            $existdata['publisher'][0]  = get_string('selectalreadyexist', 'mod_reader');
            $existdata['difficulty'][0] = get_string('selectalreadyexist', 'mod_reader');
            $existdata['level'][0]      = get_string('selectalreadyexist', 'mod_reader');

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

                echo $output->box_start('generalbox');

                echo '<h2>'.get_string('addquiztoreader', 'mod_reader').'</h2><br />';

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
                print_string('pointsex11', 'reader');
                echo '</td><td>';
                echo '<input type="text" name="points" value="1" />';
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
                echo '<tr align="center"><td colspan="4" height="60px"><input type="submit" name="submit" value="Add" /></td></tr>';
                echo '</table>';
                echo '</form>';
                echo $output->box_end();

            } else {
                notice(get_string('noquizzesfound', 'mod_reader'));
            }

        }
    }

} else if ($act == 'editquiz' && has_capability('mod/reader:managebooks', $contextmodule)) {
    if ($sort == 'username') {
        $sort = 'title';
    }
    $table = new html_table();

    $titles = array(''                 => '',
                    'Title'            => 'title',
                    'Publisher'        => 'publisher',
                    'Level'            => 'level',
                    'Reading Level'    => 'rlevel',
                    'Length'           => 'points',
                    'Times Quiz Taken' => 'qtaken',
                    'Average Points'   => 'apoints',
                    'Options'          => '');

    $params = array('a' => 'admin', 'id' => $id, 'act' => $act, 'tab' => $tab);
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

        $params = array('quizid' => $book->quizid, 'readerid' => $reader->id, 'deleted' => 0);
        if ($readerattempts = $DB->get_records('reader_attempts', $params)) {
            foreach ($readerattempts as $readerattempt) {
                $i++;
                if ($totalgrade==0) {
                    $totalpoints = 0;
                } else {
                    $totalpoints = round(($readerattempt->sumgrades / $totalgrade) * 100, 2);
                }
                $totalpointsaverage += $totalpoints;
                if ($totalpoints >= $reader->minpassgrade) {
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
            reader_get_reader_points($reader, $book->id),
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

        echo '<center><h3>'.get_string('needdeletethisattemptstoo', 'mod_reader').':</h3>';

        if (count($table->data)) {
            echo html_writer::table($table);
        }

        echo '<form action="admin.php?a=admin&id='.$id.'&act=editquiz&deletebook='.$deletebook.'" method="post">';
        echo '<input type="hidden" name="deleteallattempts" value="1" />';
        echo '<input type="submit" value="Delete" />';
        echo '</form>';
        echo $output->single_button(new moodle_url('admin.php',$options), get_string('cancel'), 'post', $options);
        echo '</center>';
    }

} else if ($act == 'reports' && has_capability('mod/reader:viewreports', $contextmodule)) {
    $table = new html_table();

    $titles = array('Image'                => '',
                    'Username'             => 'username',
                    'Fullname<br />Click to view screen' => 'fullname',
                    'Start level'          => 'startlevel',
                    'Current level'        => 'currentlevel',
                    'Taken Quizzes'        => 'tquizzes',
                    'Passed<br />Quizzes'  => 'cquizzes',
                    'Failed<br />Quizzes'  => 'iquizzes',
                    'Total Points'         => 'totalpoints',
                    'Total words<br />this term' => 'totalwordsthisterm',
                    'Total words<br />all terms' => 'totalwordsallterms');

    $params = array('a' => 'admin', 'id' => $id, 'act' => 'reports', 'gid' => $gid, 'searchtext' => $searchtext, 'page' => $page, 'tab' => $tab);
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
        $gid = null;
    }

    $groupnames = array();
    foreach ($coursestudents as $coursestudent) {
        $groupnames[$coursestudent->username] = array();
        if (reader_check_search_text($searchtext, $coursestudent)) {

            $picture = $output->user_picture($coursestudent,array($course->id, true, 0, true));
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
            if ($readerattempts = $DB->get_records('reader_attempts', array('userid' => $coursestudent->id, 'deleted' => 0))) {
                foreach ($readerattempts as $readerattempt) {
                    if ($readerattempt->passed) {
                        if ($books = $DB->get_records('reader_books', array('id' => $readerattempt->bookid))) {
                            if ($book = array_shift($books)) {
                                $totalwords['allterms'] += $book->words;
                                if ($readerattempt->readerid==$reader->id && $reader->ignoredate < $readerattempt->timefinish) {
                                    $totalwords['thisterm'] += $book->words;
                                }
                            }
                        }
                    }
                }
            }

            $usernamelink = reader_username_link($coursestudent, $course->id, $excel);
            if (has_capability('mod/reader:manageattempts', $contextmodule)) {
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

    if (class_exists('XMLWriter')) {
        echo '<table style="width:100%"><tr><td align="right">';
        echo $output->single_button(new moodle_url('admin.php',$options), get_string('downloadexcel', 'mod_reader'), 'post', $options);
        echo '</td></tr></table>';
    }

    reader_print_search_form();

    $groups = groups_get_all_groups($course->id);

    if ($groups) {
        reader_print_group_select_box($course->id, 'admin.php?a=admin&id='.$id.'&act=reports&sort='.$sort.'&orderby='.$orderby);
    }

    reader_select_perpage($id, $act, $sort, $orderby, $gid);
    list($totalcount, $table->data, $startrec, $finishrec, $options['page']) = reader_get_pages($table->data, $page, $perpage);
    //print_paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $output->render($pagingbar);

    if (isset($table) && count($table->data)) {
        echo html_writer::table($table);
    }

    //print_paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $output->render($pagingbar);

} else if ($act == 'fullreports' && has_capability('mod/reader:viewreports', $contextmodule)) {
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

    if ($reader->wordsorpoints == 0) {
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
    $params = array('a' => 'admin', 'id' => $id, 'act' => 'fullreports', 'gid' => $gid, 'searchtext' => $searchtext, 'page' => $page, 'ct' => $ct, 'tab' => $tab);
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

        if ($reader->wordsorpoints == 0) {
            $worksheet->write_string(2, $c++, 'Words', $formatbold);
            $worksheet->write_string(2, $c++, 'Total Words', $formatbold);
        } else {
            $worksheet->write_string(2, $c++, 'Points', $formatbold);
            $worksheet->write_string(2, $c++, 'Length', $formatbold);
            $worksheet->write_string(2, $c++, 'Total Points', $formatbold);
        }
    }

    if (! $gid) {
        $gid = null;
    }

    $groupnames = array();
    foreach ($coursestudents as $coursestudent) {
        $groupnames[$coursestudent->username] = array();

        $picture = $output->user_picture($coursestudent, array($course->id, true, 0, true));
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

            if ($totable['first'] || $sort == 'slevel' || $sort == 'blevel' || $sort == 'title' || $sort == 'date' || $excel) {
                $showuser = true;
            } else {
                $showuser = false;
            }

            if ($reader->wordsorpoints == 0) {
                if (reader_check_search_text($searchtext, $coursestudent, $readerattempt)) {

                    if ($readerattempt['deleted']==0 && $readerattempt['cheated']==0) {
                        if ($readerattempt['passed'] || $readerattempt['credit']) {
                            $totalwords +=  $readerattempt['words'];
                        }
                    }

                    if ($showuser) {
                        $linkusername = reader_username_link($coursestudent, $course->id, $excel);
                        if (has_capability('mod/reader:manageattempts', $contextmodule)) {
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
                        $readerattempt['timefinish'],
                        $readerattempt['userlevel'],
                        $readerattempt['bookdiff'],
                        $readerattempt['booktitle'],
                        $readerattempt['percentgrade'].'%',
                        $readerattempt['statustext'],
                        (is_numeric($readerattempt['words']) ? number_format($readerattempt['words']) : $readerattempt['words']),
                        (is_numeric($totalwords) ? number_format($totalwords) : $totalwords)
                    );
                    $table->data[] = new html_table_row($cells);
                }
            } else {
                if (reader_check_search_text($searchtext, $coursestudent, $readerattempt)) {
                    if ($showuser) {
                        $linkusername = reader_username_link($coursestudent, $course->id, $excel);
                        if (has_capability('mod/reader:manageattempts', $contextmodule)) {
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
                        $readerattempt['timefinish'],
                        $readerattempt['userlevel'],
                        $readerattempt['bookdiff'],
                        $readerattempt['booktitle'],
                        $readerattempt['percentgrade'].'%',
                        $readerattempt['statustext'],
                        $readerattempt['bookpoints'],
                        $readerattempt['points'],
                        $readerattempt['totalpoints']
                    );
                    $table->data[] = new html_table_row($cells);
                }
            }
        }
    } // end foreach $readerattempts

    if ($sort == 'slevel' || $sort == 'blevel' || $sort == 'title' || $sort == 'date') {
        // do nothing - these are valid sort fields
    } else {
        $sort = ''; // we particularly want to avoid sorting by "username"
    }
    reader_sort_table($table, $titles, $orderby, $sort, array('date' => $dateformat));

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

            if ($reader->wordsorpoints == 0) {
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
    echo $output->single_button(new moodle_url('admin.php',$options), get_string('downloadexcel', 'mod_reader'), 'post', $options);
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
    echo $output->render($pagingbar);

    if (isset($table) && count($table->data)) {
        echo html_writer::table($table);
    }

    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&ct={$ct}&amp;");
    echo $output->render($pagingbar);

} else if ($act == 'summarybookreports' && has_capability('mod/reader:viewreports', $contextmodule)) {
    if ($sort == 'username') {
        $sort = 'title';
    }
    $table = new html_table();
    $titles = array('Title'            => 'title',
                    'Publisher'        => 'publisher',
                    'Level'            => 'level',
                    'Reading Level'    => 'rlevel',
                    'Length'           => 'points',
                    'Times Quiz Taken' => 'qtaken',
                    'Average Points'   => 'apoints',
                    'Passed'           => 'passed',
                    'Failed'           => 'failed',
                    'Pass Rate'        => 'prate');

    $params = array('a' => 'admin', 'id' => $id, 'act' => 'summarybookreports', 'gid' => $gid, 'searchtext' => $searchtext, 'page' => $page, 'tab' => $tab);
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

    $select = 'hidden = ?';
    $params = array(0);
    if ($books = $DB->get_records_select('reader_books', $select, $params)) {
        foreach ($books as $book) {
            if (reader_check_search_text($searchtext, '', $book)) {
                $totalgrade = 0;
                $totalpointsaverage = 0;
                $correctpoints = 0;
                $i = 0;
                $params = array('quizid' => $book->quizid, 'deleted' => 0);
                if ($readerattempts = $DB->get_records('reader_attempts', $params)) {
                    foreach ($readerattempts as $readerattempt) {
                        $i++;
                        $totalpointsaverage += $readerattempt->percentgrade;
                        if ($readerattempt->passed) {
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
                $bookreportlink = html_writer::tag('a', $book->name, array('href' => new moodle_url('/mod/reader/admin/reports.php', $params)));
                $table->data[] = new html_table_row(array(
                    $bookreportlink,
                    $book->publisher,
                    $book->level,
                    reader_get_reader_difficulty($reader, $book->id),
                    reader_get_reader_points($reader, $book->id),
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
    echo $output->single_button(new moodle_url('admin.php',$options), get_string('downloadexcel', 'mod_reader'), 'post', $options);
    echo '</td></tr></table>';

    reader_print_search_form();

    reader_select_perpage($id, $act, $sort, $orderby, $gid);
    list($totalcount, $table->data, $startrec, $finishrec, $options['page']) = reader_get_pages($table->data, $page, $perpage);
    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $output->render($pagingbar);

    if (isset($table) && count($table->data)) {
        echo html_writer::table($table);
    }

    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $output->render($pagingbar);

} else if ($act == 'fullbookreports' && has_capability('mod/reader:viewreports', $contextmodule)) {
    if ($sort == 'username') {
        $sort = 'title';
    }
    $table = new html_table();

    $titles = array('Title'         => 'title',
                    'Publisher'     => 'publisher',
                    'Level'         => 'level',
                    'Reading Level' => 'rlevel',
                    'Student Name'  => 'sname',
                    'Student ID'    => 'studentid',
                    'Passed/Failed' => '');

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

    $params = array('a' => 'admin', 'id' => $id, 'act' => 'fullbookreports', 'gid' => $gid, 'searchtext' => $searchtext, 'page' => $page, 'tab' => $tab);
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
            $where  = 'ra.quizid= ? AND ra.readerid= ?'.$groupuserfilter.' AND ra.deleted = ?';
            $params = array($book->quizid, $reader->id, 0);
            if (! $readerattempts = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY ra.userid", $params)) {
                $readerattempts = array();
            }

            $params = array('id' => $id, 'q' => $book->quizid, 'b' => $book->id);
            $report = new moodle_url('/mod/reader/admin/reports.php', $params);

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
    echo $output->single_button(new moodle_url('admin.php',$options), get_string('downloadexcel', 'mod_reader'), 'post', $options);
    echo '</td></tr></table>';

    reader_print_search_form();

    $groups = groups_get_all_groups($course->id);

    if ($groups) {
        reader_print_group_select_box($course->id, 'admin.php?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&orderby='.$orderby);
    }

    reader_select_perpage($id, $act, $sort, $orderby, $gid);
    list($totalcount, $table->data, $startrec, $finishrec, $options['page']) = reader_get_pages($table->data, $page, $perpage);
    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $output->render($pagingbar);

    if (isset($table) && count($table->data)) {
        echo html_writer::table($table);
    }

    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $output->render($pagingbar);

} else if ($act == 'viewattempts' && has_capability('mod/reader:manageattempts', $contextmodule)) {

    $table = new html_table();

    if (! $searchtext && ! $gid) {
      echo "<center><h2><font color=\"red\">".get_string('pleasespecifyyourclassgroup', 'mod_reader').'</font></h2></center>';
    } else {

        if (has_capability('mod/reader:manageattempts', $contextmodule)) {
            $titles = array('Username'   => 'username',
                            'Fullname'   => 'fullname',
                            'Book Name'  => 'bname',
                            'AttemptID'  => 'attemptid',
                            'Score'      => 'score',
                            'P/F/C'      => '',
                            'Finishtime' => 'timefinish',
                            'Option'     => '');
        } else {
            $titles = array('Username'   => 'username',
                            'Fullname'   => 'fullname',
                            'Book Name'  => 'bname',
                            'AttemptID'  => 'attemptid',
                            'Score'      => 'score',
                            'P/F/C'      => '',
                            'Finishtime' => 'timefinish');
        }

        $params = array('a' => 'admin', 'id' => $id, 'act' => 'viewattempts', 'gid' => $gid, 'searchtext' => $searchtext, 'page' => $page, 'tab' => $tab);
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

        $select = 'ra.id, ra.userid, ra.attempt, ra.percentgrade, '.
                  'ra.passed, ra.credit, ra.cheated, ra.deleted, ra.timefinish, '.
                  'rb.name, rb.publisher, rb.level, '.
                  'u.username ,u.firstname, u.lastname';
        $from   = '{reader_attempts} ra '.
                  'LEFT JOIN {user} u ON ra.userid = u.id '.
                  'LEFT JOIN {reader_books} rb ON ra.bookid = rb.id';
        $where  = 'ra.deleted = 0';
        $params = null;

        if ($searchtext) {
            if (strstr($searchtext, '"')) {
                $texts = explode('"', str_replace('\"', '"', $searchtext));
            } else {
                $texts = explode(' ', $searchtext);
            }
            foreach ($texts as $i => $text) {
                if ($text && strlen($text) > 3) {
                    $texts[$i] = "u.username LIKE '%$text%'";
                    $texts[$i] = "u.firstname LIKE '%{$text}%'";
                    $texts[$i] = "u.lastname LIKE '%{$text}%'";
                    $texts[$i] = "rb.name LIKE '%{$text}%'";
                    $texts[$i] = "rb.level LIKE '%{$text}%'";
                    $texts[$i] = "rb.publisher LIKE '%{$text}%'";
                } else {
                    $texts[$i] = '';
                }
            }
            $texts = array_filter($texts);
            if ($texts = implode(') OR (', $texts)) {
                $where .= " AND ($texts)";
            }
        } else if ($gid) {
            $groupuserids = array();
            $groupusers = groups_get_members($gid);
            foreach ($groupusers as $groupuser) {
                $groupuserids[] = $groupuser->id;
            }
            if ($groupuserids = implode(',', $groupuserids)) {
                $where .= " AND ra.userid IN ($groupuserids)";
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

        $can_deleteattempts = has_capability('mod/reader:manageattempts', $contextmodule);

        foreach ($readerattempts as $readerattempt) {
            $cells = array(
                reader_username_link($readerattempt, $course->id, $excel),
                reader_fullname_link($readerattempt, $course->id, $excel),
                $readerattempt->name,
                $readerattempt->attempt,
                $readerattempt->percentgrade.'%',
                $readerattempt->statustext,
                $readerattempt->timefinish
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

        reader_sort_table($table, $titles, $orderby, $sort, array('timefinish' => $dateformat));

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
        echo $output->single_button(new moodle_url('admin.php',$options), get_string('downloadexcel', 'mod_reader'), 'post', $options);
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
    echo $output->render($pagingbar);

    if (isset($table) && count($table->data)) {
        echo html_writer::table($table);
    }

    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $output->render($pagingbar);

    if (has_capability('mod/reader:managebooks', $contextmodule)) {
      echo '<form action="?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&orderby='.$orderby.'&gid='.$gid.'" method="post"><div> ';
      echo ' <div style="margin:20px 0;font-size:16px;">'.get_string('restoredeletedattempt', 'mod_reader').'</div>';
      echo '<div style="float:left;width:200px;">'.get_string('studentuserid', 'mod_reader').'</div>';
      echo '<div style="float:left;width:200px;"><input type="text" name="studentuserid" value="" style="width:120px;" /></div><div style="clear:both;"></div>';
      echo '<div>or</div>';
      echo '<div style="float:left;width:200px;">'.get_string('studentusername', 'mod_reader').'</div>';
      echo '<div style="float:left;width:200px;"><input type="text" name="studentusername" value="" style="width:120px;" /></div><div style="clear:both;"></div>';
      echo '<div style="float:left;width:200px;">'.get_string('bookquiznumber', 'mod_reader').'</div>';
      echo '<div style="float:left;width:200px;"><input type="text" name="bookquiznumber" value="" style="width:120px;" /></div><div style="clear:both;"></div>';
      //echo ' <input type="hidden" name="" value="" />';
      echo ' <input type="submit" name="submit" value="Restore" />';
      echo '</div></form>';
    }

  //}

} else if ($act == 'studentslevels' && has_capability('mod/reader:manageusers', $contextmodule)) {

    $table = new html_table();

    $titles = array('Image'          => '',
                    'Username'       => 'username',
                    'Fullname<br />Click to view screen' => 'fullname',
                    'Start level'    => 'startlevel',
                    'Current level'  => 'currentlevel',
                    'AllowPromotion' => 'allowpromotion',
                    'Stop Promo At'  => 'promotionstops',
                    'Goal'           => 'goal');

    if ($reader->uniqueip == 1) {
        $titles['Restrict IP'] = '';
    }

    $params = array('a' => 'admin', 'id' => $id, 'act' => $act, 'gid' => $gid, 'searchtext' => $searchtext, 'page' => $page, 'tab' => $tab);
    reader_make_table_headers($table, $titles, $orderby, $sort, $params);
    $table->align = array('center', 'left', 'left', 'center', 'center', 'center', 'center', 'center', 'center');
    $table->width = '100%';

    if (! $gid) {
        $gid = null;
    }

    foreach ($coursestudents as $coursestudent) {
        if (reader_check_search_text($searchtext, $coursestudent)) {

            $params = array('userid' => $coursestudent->id, 'readerid' => $reader->id);
            if ($studentlevel = $DB->get_record('reader_levels', $params)) {
                // do nothing
            } else {
                $studentlevel = (object)array(
                    'id'             => 0,
                    'userid'         => $coursestudent->id,
                    'readerid'       => $reader->id,
                    'startlevel'     => 0,
                    'currentlevel'   => 0,
                    'allowpromotion' => 1,
                    'stoplevel'      => 99,
                    'goal'           => 0,
                    'time'           => time()
                );
                $studentlevel->id = $DB->insert_record('reader_levels', $studentlevel);
            }

            $picture = $output->user_picture($coursestudent, array($course->id, true, 0, true));
            if (has_capability('mod/reader:manageattempts', $contextmodule)) {
                $linkfullname = reader_fullname_link_viewasstudent($coursestudent, $id, $excel);
            } else {
                $linkfullname = reader_fullname_link($coursestudent, $course->id, $excel);
            }

            $cells = array(
                $picture,
                reader_username_link($coursestudent, $course->id, $excel),
                $linkfullname,
                reader_level_menu($coursestudent->id, $studentlevel, 'startlevel'),
                reader_level_menu($coursestudent->id, $studentlevel, 'currentlevel'),
                reader_promo_menu($coursestudent->id, $studentlevel, 'allowpromotion', 1),
                reader_promotionstop_menu($coursestudent->id, $studentlevel, 'stoplevel', 2),
                reader_goals_menu($coursestudent->id, $studentlevel, 'goal', 3, $reader)
            );
            if ($reader->uniqueip == 1) {
                $cells[] = reader_ip_menu($coursestudent->id, $reader);
            }
            $table->data[] = new html_table_row($cells);
        }
    }

    reader_sort_table($table, $titles, $orderby, $sort);

    if ($gid) {

        // startlevel for all students
        $levels = array(0,1,2,3,4,5,6,7,8,9,10,11,12,13,14);
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

        // currentlevel for all students
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

        // points reading goal for all students
        $levels = array(1,2,3,4,5,6,7,8,9,10,11,12,13,14,15);
        echo '<form action="?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&orderby='.$orderby.'&gid='.$gid.'" method="post"><div> ';
        print_string('setuniformgoalinpoints', 'reader');
        echo ' <select name="changeallgoal">';
        foreach ($levels as $value) {
            echo '<option value="'.$value.'">'.$value.'</option>';
        }
        echo '</select> ';
        //echo ' <input type="hidden" name="" value="" />';
        echo ' <input type="submit" name="submit" value="Change" />';
        echo '</div></form>';

        // words reading goal for all students
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
        echo ' <select name="changeallgoal">';
        foreach ($levels as $value) {
            echo '<option value="'.$value.'">'.$value.'</option>';
        }
        echo '</select> ';
        //echo ' <input type="hidden" name="" value="" />';
        echo ' <input type="submit" name="submit" value="Change" />';
        echo '</div></form>';

        // allowpromotion for all students
        $levels = array(0 => get_string('disallowpromotion', 'mod_reader'),
                        1 => get_string('allowpromotion',    'mod_reader'));
        echo '<form action="?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&orderby='.$orderby.'&gid='.$gid.'" method="post"><div> ';
        print_string('changeallto', 'reader');
        echo ' <select name="changeallpromotion">';
        foreach ($levels as $value) {
            echo '<option value="'.$value.'">'.$value.'</option>';
        }
        echo '</select> ';
        //echo ' <input type="hidden" name="" value="" />';
        echo ' <input type="submit" name="submit" value="Change" />';
        echo '</div></form>';

        // stoplevel for all students
        $levels = array(0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15);
        echo '<form action="?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&orderby='.$orderby.'&gid='.$gid.'" method="post"><div> ';
        print_string('changeallstoplevelto', 'reader');
        echo ' <select name="changeallstoplevel">';
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
    echo $output->render($pagingbar);

    if (isset($table) && count($table->data)) {
        echo html_writer::table($table);
    }

    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $output->render($pagingbar);

} else if ($act == 'changereaderlevel' && has_capability('mod/reader:managebooks', $contextmodule)) {
    //$reader->bookinstances = 1;
    $table = new html_table();

    if ($reader->bookinstances == 1) {
        $titles = array('Title'         => 'title',
                        'Publisher'     => 'publisher',
                        'Level'         => 'level',
                        'Reading Level' => 'readinglevel',
                        'Length'        => 'points');
    } else {
        $titles = array('Title'         => 'title',
                        'Publisher'     => 'publisher',
                        'Level'         => 'level',
                        'Words'         => 'words',
                        'Reading Level' => 'readinglevel',
                        'Length'        => 'points');
    }

    $params = array('a' => 'admin', 'id' => $id, 'act' => $act, 'gid' => $gid, 'searchtext' => $searchtext, 'page' => $page, 'publisher' => $publisher, 'tab' => $tab);
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

            $has_capability = has_capability('mod/reader:addinstance', $contextmodule);
            $wordstitle     = reader_ajax_textbox_title($has_capability, $book, 'words', $id, $act);
            $leveltitle     = reader_ajax_textbox_title($has_capability, $book, 'level', $id, $act);
            $publishertitle = reader_ajax_textbox_title($has_capability, $book, 'publisher', $id, $act);

            $difficultyform = trim(reader_difficulty_menu(reader_get_reader_difficulty($reader, $book->id), $book->id, $reader));
            $pointsform = trim(reader_points_menu(reader_get_reader_difficulty($reader, $book->id), $book->id, $reader));

            if ($reader->bookinstances == 1) {
                $table->data[] = new html_table_row(array($book->name, $publishertitle, $leveltitle, $difficultyform, $pointsform));
            } else {
                $table->data[] = new html_table_row(array($book->name, $publishertitle, $leveltitle, $wordstitle, $difficultyform, $pointsform));
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
                $points_[$levels_->points] = $levels_->id;
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
            unset($difficulty_,$points_);
            $data = $DB->get_records_sql('SELECT ib.difficulty as ibdifficulty,ib.points as ibpoints FROM {reader_books} rp INNER JOIN {reader_book_instances} ib ON ib.bookid = rp.id WHERE ib.readerid= ?  and rp.publisher = ? ', array($reader->id, $publisher));
            foreach ($data as $data_) {
                $difficulty_[$data_->ibdifficulty] = $data_->bookid;
                $points_[$data_->ibpoints] = $data_->bookid;
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
        //$pointsarray = array(0.50,0.60,0.70,0.80,0.90,1.00,1.10,1.20,1.30,1.40,1.50,1.60,1.70,1.80,1.90,2.00);
        $pointsarray = array(0.50,0.60,0.70,0.80,0.90,1.00,1.10,1.20,1.30,1.40,1.50,1.60,1.70,1.80,1.90,2.00,3.00,4.00,5.00,6.00,7.00,8.00,9.00,10.00,15,20,25,30,35,40,45,50,55,60,65,70,75,80,85,90,95,100,110,120,130,140,150,160,170,175,180,190,200,225,250,275,300,350,400);
        print_string('changepointsfrom', 'reader');
        echo ' <select name="points">';
        ksort($points_);
        reset($points_);
        foreach ($points_ as $key => $value) {
            echo '<option value="'.$key.'" ';
            if ($points == $key) {
                echo ' selected="selected" ';
            }
            echo '>'.$key.'</option>';
        }
        echo '</select> ';
        print_string('to', 'reader');
        echo ' <select name="topoints">';
        foreach ($pointsarray as $value) {
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
    echo $output->render($pagingbar);
    if (isset($table) && count($table->data)) {
        echo html_writer::table($table);
    }

    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&publisher={$publisher}&amp;");
    echo $output->render($pagingbar);

} else if ($act == 'setmessage' && has_capability('mod/reader:manageusers', $contextmodule)) {

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

            $mform = &$this->_form;

            $groups = self::get_all_groups($course->id);
            $hours = array('0'   => 'Indefinite',
                           '168' => '1 Week',
                           '240' => '10 Days',
                           '336' => '2 Weeks',
                           '504' => '3 Weeks');

            $mform->addElement('select',   'groupids',    'Group', $groups, 'size="5" multiple');
            $mform->addElement('select',   'activehours', 'Active Time (Hours)', $hours);
            $mform->addElement('textarea', 'messagetext', 'Text', 'wrap="virtual" rows="10" cols="70"');
            $mform->addElement('select',   'messageformat', 'Format', format_text_menu());
            $mform->addElement('hidden',   'editmessage',  0);

            $mform->setType('groupids',      PARAM_INT);
            $mform->setType('activehours',   PARAM_INT);
            $mform->setType('messagetext',   PARAM_RAW);
            $mform->setType('messageformat', PARAM_INT);
            $mform->setType('editmessage',   PARAM_INT);

            if ($editmessage) {
                if ($message = $DB->get_record('reader_messages', array('id' => $editmessage))) {
                    if ($activehours = $message->timefinish) {
                        $activehours = round(($activehours - time()) / (60 * 60));
                        foreach (array_reverse(array_keys($hours)) as $hour) {
                            $hour = intval($hour);
                            if ($activehours >= $hour) {
                                $activehours = $hour;
                                break;
                            }
                        }
                    }
                    $mform->setDefault('groupids',      $message->groupids);
                    $mform->setDefault('activehours',   $activehours);
                    $mform->setDefault('messagetext',   $message->messagetext);
                    $mform->setDefault('messageformat', $message->messageformat);
                    $mform->setDefault('editmessage',   $editmessage);
                }
            }

            $submitlabel = get_string('savechanges');
            $this->add_action_buttons(false, $submitlabel);
        }

        /**
         * get_all_groups
         */
        static function get_all_groups($courseid) {
            if ($groups = groups_get_all_groups($courseid, 0, 0, 'id,name')) {
                foreach ($groups as $groupid => $group) {
                    $groups[$groupid] = $group->name;
                }
                asort($groups);
            } else {
                $groups = array();
            }
            $groups = array(0 => get_string('all')) + $groups;
            return $groups;
        }

    }

    $mform = new mod_reader_message_form("admin.php?a=admin&id={$id}&act=setmessage");
    $mform->display();

    $params = array('readerid' => $cm->instance, 'teacherid' => $USER->id);
    if ($messages = $DB->get_records('reader_messages', $params, 'timemodified DESC')) {

        $groups = mod_reader_message_form::get_all_groups($course->id);

        echo 'Current Messages:';
        foreach ($messages as $message) {

            if ($groupnames = $message->groupids) {
                $groupnames = explode(',', $groupnames);
                foreach ($groupnames as $g => $gid) {
                    if (array_key_exists($gid, $groups)) {
                        $groupnames[$g] = $groups[$gid];
                    } else {
                        $groupnames[$g] = ''; // shouldn't happen !!
                    }
                }
                $groupnames = array_filter($groupnames);
                $groupnames = implode(', ', $groupnames);
            }
            if (empty($groupnames)) {
                $groupnames = get_string('all');
            }

            if ($message->timemodified > (time() - ( 48 * 60 * 60))) {
                $bgcolor = 'bgcolor="#CCFFCC"';
            } else {
                $bgcolor = '';
            }

            echo '<table width="100%"><tr><td align="right"><table cellspacing="0" cellpadding="0" class="forumpost blogpost blog" '.$bgcolor.' width="90%">';
            echo '<tr><td align="left"><div style="margin-left: 10px;margin-right: 10px;">'."\n";
            echo format_text($message->messagetext, $message->messageformat);
            echo '<div style="text-align:right"><small>';
            if ($message->timefinish) {
                $time = $message->timefinish - time();
                echo round($time / (60 * 60 * 24), 2).' Days; ';
            } else {
                echo 'Indefinitely; ';
            }
            echo 'Added: '.date("$dateformat $timeformat", $message->timemodified).'; '; // was 'd M Y H:i'
            echo 'Group: '. $groupnames.'; ';
            echo '<a href="admin.php?a=admin&id='.$id.'&act=setmessage&editmessage='.$message->id.'">Edit</a> / <a href="admin.php?a=admin&id='.$id.'&act=setmessage&deletemessage='.$message->id.'">Delete</a>';
            echo '</small></div>';
            echo '</div></td></tr></table></td></tr></table>'."\n\n";
        }
    }

} else if ($act == 'makepix_t' && has_capability('mod/reader:managebooks', $contextmodule)) {
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

} else if ($act == 'makepix_l' && has_capability('mod/reader:managebooks', $contextmodule)) {
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

} else if ($act == 'awardextrapoints' && has_capability('mod/reader:manageattempts', $contextmodule)) {
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
        $titles = array('Image' => '',
                        'Username' => 'username',
                        'Fullname' => 'fullname',
                        'Select Students' => '');

        $params = array('a' => 'admin', 'id' => $id, 'act' => 'awardextrapoints', 'gid' => $gid, 'tab' => $tab);
        reader_make_table_headers($table, $titles, $orderby, $sort, $params);
        $table->align = array('center', 'left', 'left', 'center');
        $table->width = '100%';

        foreach ($coursestudents as $coursestudent) {
            $picture = $output->user_picture($coursestudent,array($course->id, true, 0, true));
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
        echo "<center><h2><font color=\"red\">".get_string('pleasespecifyyourclassgroup', 'mod_reader')."</font></h2></center>";
    }

} else if ($act == 'checksuspiciousactivity' && has_capability('mod/reader:manageusers', $contextmodule)) {

    $table = new html_table();

    echo '<form action="admin.php?a='.$a.'&id='.$id.'&act='.$act.'" method="post">';
    echo get_string('checkonlythiscourse', 'mod_reader').' <input type="checkbox" name="useonlythiscourse" value="yes" checked /><br />';
    echo get_string('withoutdayfilter', 'mod_reader').' <input type="checkbox" name="withoutdayfilter" value="yes" /><br />';
    echo get_string('selectipmask', 'mod_reader').' <select id="ip_mask" name="ipmask"><br />';
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
    echo get_string('fromthistime', 'mod_reader').' <select id="from_time" name="fromtime">';
//change by Tom 28 June 2010
    $fromtimeselect = array(DAYSECS        => '1 day',
                           (DAYSECS * 2)   => '2 days',
                           (DAYSECS * 3)   => '3 days',
                           (DAYSECS * 4)   => '4 days',
                           (WEEKSECS)      => '1 week',
                           (WEEKSECS * 2)  => '2 weeks',
                           (WEEKSECS * 4)  => '1 month',
                           (WEEKSECS * 8)  => '2 months',
                           (WEEKSECS * 13) => '3 months',
                           (WEEKSECS * 26) => '6 months',
                           (YEARSECS)      => '1 year',
                           (YEARSECS * 2)  => '2 years',
                           (YEARSECS * 3)  => '3 years');
    foreach ($fromtimeselect as $key => $value) {
        echo '<option value="'.$key.'"';
        if ($key == $fromtime) {
            echo ' selected="selected" ';
        }
        echo '>'.$value.'</option>';
    }
    echo '</select><br />';
    echo get_string('maxtimebetweenquizzes', 'mod_reader').' <select id="max_time" name="maxtime">';
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
        $quizids = array();

        $order='l.time DESC';

        $where  = "module = 'reader' AND info LIKE 'readerID%; reader quiz%; %/%'";
        if ($useonlythiscourse) {
            $where .= " AND course = '$course->id'";
        }
        if ($fromtime) {
            $where .= " AND time > '".(time() - $fromtime)."'";
        }
        if ($logs = $DB->get_records_sql("SELECT * FROM {log} WHERE $where")) {
            foreach ($logs as $logid => $log) {
                if (preg_match('/reader quiz ([0-9]+); /si', $log->info, $quizid)) {
                    $quizid = $quizid[1];
                    if (empty($quizids[$quizid])) {
                        $quizids[$quizid] = array();
                    }
                    $quizids[$quizid][$logid] = $log->ip;
                }
            }
        }

        $comparequizids = array();
        foreach ($quizids as $quizid => $logids) {

            // $logids holds the ids of all attempts at this quiz
            // within the given time frame

            $comparelogids = $logids;
            foreach ($logids as $logid => $ip) {

                // remove this log from the comparison array
                unset($comparelogids[$logid]);

                list($ip1, $ip2, $ip3, $ip4) = explode('.',$ip);
                if ($ipmask == 2) {
                    $compareipmask = "$ip1.$ip2";
                } else {
                    $compareipmask = "$ip1.$ip2.$ip3";
                }

                foreach ($comparelogids as $comparelogid => $compareip) {
                    if (address_in_subnet($compareip, $compareipmask)) {
                        if (empty($comparequizids[$quizid])) {
                            $comparequizids[$quizid] = array();
                        }
                        $comparequizids[$quizid][$logid] = $ip;
                        $comparequizids[$quizid][$comparelogid] = $ip;
                    }
                }
            }
        }

        $compare = array();
        foreach ($comparequizids as $quizid => $logids) {
            if (count($logids) >= 2) {
                $prevlogid = 0;
                $compare[$quizid] = array();
                foreach ($logids as $logid => $ip) {
                    if ($prevlogid) {
                        $compare[$quizid][$prevlogid]['ip2'] = $ip;
                        $compare[$quizid][$prevlogid]['id2'] = $logid;
                    }
                    $prevlogid = $logid;
                    $compare[$quizid][$prevlogid] = array();
                    $compare[$quizid][$prevlogid]['ip'] = $ip;
                }
                if ($prevlogid) {
                    unset($compare[$quizid][$prevlogid]);
                }
            }
        }

        $titles = array('Book'        => 'book',
                        'Username 1'  => 'username1',
                        'Username 2'  => 'username2',
                        'IP 1'        => '',
                        'IP 2'        => '',
                        'Time 1'      => 'time1',
                        'Time 2'      => 'time2',
                        'Time period' => '',
                        'Log text'    => '');

        $params = array('a' => 'admin', 'id' => $id, 'act' => $act, 'tab' => $tab);
        $table->head  = reader_make_table_headers($table, $titles, $orderby, $sort, $params);
        $table->align = array("left", "left", "left", "center", "center", "center", "center", "center", "left");
        $table->width = "100%";

        foreach ($compare as $quizid => $logids) {
          foreach ($logids as $logid => $data) {
            if (! array_key_exists('id2', $data)) {
                print_object($data);
                die;
            }
            $logid2 = $data['id2'];
            if ($logs[$logid]->userid != $logs[$logid2]->userid) {
              $diff = $logs[$logid]->time - $logs[$logid2]->time;
              if ($diff < 0) {
                  $diff = (int)substr($diff, 1);
              }
              if ($maxtime > $diff || $withoutdayfilter == 'yes') {
                $bookdata  = $DB->get_record('reader_books', array('id' => $quizid));
                $user1dta  = $DB->get_record('user', array('id' => $logs[$logid]->userid));
                $user2data = $DB->get_record('user', array('id' => $logs[$data['id2']]->userid));
                if ($diff < 3600) {
                    $diffstring = round($diff/60)." minutes";
                } else {
                    $diffstring = round($diff/3600)." hours";
                }

                $raid1 = (int)str_replace("view.php?id=", "", $logs[$logid]->url);
                $raid2 = (int)str_replace("view.php?id=", "", $logs[$data['id2']]->url);

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

                    if ($readerattempt[1]->cheated==0) {
                        if (strstr(strtolower($logs[$logid]->info), 'passed')) {
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

                    if ($readerattempt[1]->cheated==0) {
                        if (strstr(strtolower($logs[$data['id2']]->info), 'passed')) {
                            $logstatus[2] = 'passed';
                        } else {
    //change by Tom 28 June 2010
                            $logstatus[2] = 'failed';
                        }
                    } else {
                        $logstatus[2] = '<font color="red">cheated</font>';
                    }
                    if (! has_capability('mod/reader:manageusers', $contextmodule)) {
                        $cheatedstring = '';
                    }

                    $usergroups  = reader_groups_get_user_groups($user1dta->id);
                    $groupsuser1 = groups_get_group_name($usergroups[0][0]);

                    $usergroups  = reader_groups_get_user_groups($user2data->id);
                    $groupsuser2 = groups_get_group_name($usergroups[0][0]);

                    $table->data[] = new html_table_row(array($bookdata->name."<br />".$cheatedstring,
                                                            "<a href=\"{$CFG->wwwroot}/user/view.php?id={$logs[$logid]->userid}&course={$course->id}\">{$user1dta->username} ({$user1dta->firstname} {$user1dta->lastname}; group: {$groupsuser1})</a><br />".$logstatus[1],
                    "<a href=\"{$CFG->wwwroot}/user/view.php?id={$logs[$data['id2']]->userid}&course={$course->id}\">{$user2data->username} ({$user2data->firstname} {$user2data->lastname}; group: {$groupsuser2})</a><br />".$logstatus[2],
                    link_to_popup_window("{$CFG->wwwroot}/iplookup/index.php?ip={$data['ip']}&amp;user={$logs[$logid]->userid}", $data['ip'], 440, 700, null, null, true),
                    link_to_popup_window("{$CFG->wwwroot}/iplookup/index.php?ip={$data['ip2']}&amp;user={$logs[$data['id2']]->userid}", $data['ip2'], 440, 700, null, null, true),
                    $logs[$logid]->time,          // time1
                    $logs[$data['id2']]->time,  // time2
                    $diffstring,
                    $logs[$logid]->info."<br />".$logs[$data['id2']]->info));
                }
              }
            }
          }
        }

        reader_sort_table($table, $titles, $orderby, $sort, array('time1' => "D d F $timeformat", 'time2' => "D d F $timeformat", ));

        if (isset($table) && count($table->data)) {
            echo html_writer::table($table);
        }

        //echo $totalcount;
        //print_r($quizzes);
    }

} else if ($act == 'reportbyclass' && has_capability('mod/reader:viewreports', $contextmodule)) {
    $groups = groups_get_all_groups($course->id);

    $table = new html_table();

    $titles = array('Group name' => 'groupname',
                    'Students with<br /> no quizzes' => 'noquizzes',
                    'Students with<br /> quizzes' => 'quizzes',
                    'Percent with<br /> quizzes' => 'quizzes',
                    'Average Taken<br /> Quizzes' => 'takenquizzes',
                    'Average Passed<br /> Quizzes' => 'passedquizzes',
                    'Average Failed<br /> Quizzes' => 'failedquizzes',
                    'Average total<br /> points' => 'totalpoints',
                    'Average words<br /> this term' => 'averagewordsthisterm',
                    'Average words<br /> all terms' => 'averagewordsallterms');

    $params = array('a' => 'admin', 'id' => $id, 'act' => $act, 'gif' => $gid, 'searchtext' => $searchtext, 'page' => $page, 'fromtime' => $fromtime, 'tab' => $tab);
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

        $coursestudents = get_enrolled_users($context, null, $group->id);
        foreach ($coursestudents as $coursestudent) {

            $select = 'userid = ? AND readerid = ? AND timestart > ? AND deleted = ?';
            $params = array($coursestudent->id, $reader->id, $reader->ignoredate, 0);
            if ($readerattempts = $DB->get_records_select('reader_attempts', $select, $params)) {

                $data['averagetaken'] += count($readerattempts);
                foreach ($readerattempts as $readerattempt) {

                    if ($readerattempt->passed) {
                        $data['averagepassed']++;
                        if ($bookdata = $DB->get_record('reader_books', array('quizid' => $readerattempt->quizid))) {
                            $data['averagepoints'] += reader_get_reader_points($reader, $bookdata->id);
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

            $select = 'userid= ? AND deleted = ?';
            $params = array($coursestudent->id, 0);
            if ($readerattempts = $DB->get_records_select('reader_attempts', $select, $params)) {
                foreach ($readerattempts as $readerattempt) {
                    if ($readerattempt->passed) {
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
    echo $output->single_button(new moodle_url('admin.php', $options), get_string('downloadexcel', 'mod_reader'), 'post', $options);
    echo '</td></tr></table>';

    echo '<table style="width:100%"><tr><td align="right">';
    echo '<form action="#" id="getfromdate" class="popupform"><select name="fromtime" onchange="self.location=document.getElementById(\'getfromdate\').fromtime.options[document.getElementById(\'getfromdate\').fromtime.selectedIndex].value;"><option value="admin.php?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&orderby='.$orderby.'&gid='.$gid.'&perpage='.$page.'&fromtime=0"';
    if ($fromtime == 86400 || ! $fromtime) {
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
    echo $output->render($pagingbar);

    if (isset($table) && count($table->data)) {
        echo html_writer::table($table);
    }

    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&fromtime={$fromtime}&amp;");
    echo $output->render($pagingbar);

} else if ($act == 'setgoal' && has_capability('mod/reader:manageusers', $contextmodule)) {

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
            $mform->addElement('header', 'setgoal', get_string('setgoal', 'mod_reader'));
            $mform->addElement('select', 'wordsorpoints', get_string('wordsorpoints', 'mod_reader'), array(0 => get_string('words', 'mod_reader'), 1 => get_string('points', 'mod_reader')));
            $groups = array('0' => get_string('allparticipants', 'mod_reader'));
            if ($usergroups = groups_get_all_groups($course->id)){
                foreach ($usergroups as $group){
                    $groups[$group->id] = $group->name;
                }
                $mform->addElement('select', 'separategroups', get_string('separategroups', 'mod_reader'), $groups);
            }
            $mform->addElement('text', 'levelall', get_string('all', 'mod_reader'), array('size' => '10'));
            $mform->setType('levelall', PARAM_INT);
            for($i=1; $i<=10; $i++) {
                $name = 'levelc['.$i.']';
                $mform->addElement('text', $name, $i, array('size' => '10'));
                $mform->setType($name, PARAM_INT);
            }

            if ($data = $DB->get_records('reader_goals', array('readerid' => $reader->id))) {
                foreach ($data as $data_) {
                    if (empty($data_->level)){
                        $mform->setDefault('levelall', $data_->goal);
                    } else {
                        $mform->setDefault('levelc['.$data_->level.']', $data_->goal);
                    }
                    if ($data_->groupid) {
                        $mform->setDefault('separategroups', $data_->groupid);
                    }
                }
            } else if ($reader->goal) {
                $mform->setDefault('levelall', $reader->goal);
                if ($reader->goal < 100) {
                    $mform->setDefault('wordsorpoints', 1); // points
                } else {
                    $mform->setDefault('wordsorpoints', 0); // words
                }
            }
            $this->add_action_buttons(false, $submitlabel="Save");
        }
    }
    $mform = new reader_setgoal_form('admin.php?a='.$a.'&id='.$id.'&act='.$act);
    $mform->display();

} else if ($act == 'forcedtimedelay' && has_capability('mod/reader:manageusers', $contextmodule)) {

    /**
     * reader_rates_form
     *
     * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     * @since      Moodle 2.0
     * @package    mod
     * @subpackage reader
     */
    class reader_rates_form extends moodleform {

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

            if ($default = $DB->get_record('reader_rates', array('readerid' => $reader->id,  'groupid' => 0, 'level' => 0))) {
                $defaultdelay = $default->delay;
            } else {
                $defaultdelay = 0;
            }

            $dtimes = array(0 => 'Default ('.$defaultdelay.')', 1 => 'Without delay', 14400 => 4, 28800 => 8, 43200 => 12, 57600 => 16, 86400 => 24, 129600 => 36, 172800 => 48, 259200 => 72, 345600 => 96, 432000 => 120);

            $mform    = &$this->_form;
            $mform->addElement('header', 'forcedtimedelay', get_string('forcedtimedelay', 'mod_reader')." (hours)");
            $groups = array('0' => get_string('allparticipants', 'mod_reader'));
            if ($usergroups = groups_get_all_groups($course->id)){
                foreach ($usergroups as $group){
                    $groups[$group->id] = $group->name;
                }
                $mform->addElement('select', 'separategroups', get_string('separategroups', 'mod_reader'), $groups);
            }
            $mform->addElement('select', 'levelc[99]', get_string('all', 'mod_reader'), $dtimes);
            for($i=1; $i<=10; $i++) {
                $mform->addElement('select', 'levelc['.$i.']', $i, $dtimes);
            }

            /* SET default */
            if ($rates = $DB->get_records("reader_rates", array('readerid' => $reader->id))) {
                foreach ($rates as $delay) {
                    if ($delay->level == 99) {
                        $mform->setDefault('levelall', $delay->delay);
                    } else {
                        $mform->setDefault('levelc['.$delay->level.']', $delay->delay);
                    }
                }
            }

            $this->add_action_buttons(false, $submitlabel="Save");
        }
    }
    $mform = new reader_rates_form('admin.php?a='.$a.'&id='.$id.'&act='.$act);
    $mform->display();

} else if ($act == 'bookratingslevel' && has_capability('mod/reader:viewreports', $contextmodule)) {
    $table = new html_table();

    echo '<form action="admin.php?a='.$a.'&id='.$id.'&act='.$act.'" method="post">';
    echo get_string('best', 'mod_reader').' <select id="booksratingbest" name="booksratingbest">';
    $fromselect = array('5' => "5", '10' => "10", '25' => "25", '50' => "50", '0' => "All");
    foreach ($fromselect as $key => $value) {
        echo '<option value="'.$key.'"';
        if ($key == $booksratingbest) {
            echo ' selected="selected" ';
        }
        echo '>'.$value.'</option>';
    }
    echo '</select><br />';

    echo get_string('showlevel', 'mod_reader').' <select id="booksratinglevel" name="booksratinglevel"><br />';
    $fromselect = array('0' => '0', '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6', '7' => '7', '8' => '8', '9' => '9', '10' => '10', '11' => '11', '12' => '12', '13' => '13', '14' => '14', '15' => '15', '99' => 'All');
    foreach ($fromselect as $key => $value) {
        echo '<option value="'.$key.'"';
        if ($key == $booksratinglevel) {
            echo ' selected="selected" ';
        }
        echo '>'.$value.'</option>';
    }
    echo '</select><br />';
    //echo 'Other ip mask <input type="text" name="ipmaskother" value="" />';
    echo get_string('termtype', 'mod_reader').' <select id="booksratingterm" name="booksratingterm">';
    $fromselect = array('0' => "All terms", $reader->ignoredate => "Current");
    foreach ($fromselect as $key => $value) {
        echo '<option value="'.$key.'"';
        if ($key == $booksratingterm) {
            echo ' selected="selected" ';
        }
        echo '>'.$value.'</option>';
    }
    echo '</select><br />';
    echo get_string('onlybookswithmorethan', 'mod_reader').' <select id="booksratingwithratings" name="booksratingwithratings">';
    $fromselect = array('0' => "0", '5' => "5", '10' => "10", '25' => "25", '50' => "50");
    foreach ($fromselect as $key => $value) {
        echo '<option value="'.$key.'"';
        if ($key == $booksratingwithratings) {
            echo ' selected="selected" ';
        }
        echo '>'.$value.'</option>';
    }
    echo '</select> '.get_string('ratings', 'mod_reader').':<br />';
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

        $titles = array('Book Title' => 'booktitle',
                        'Publisher'  => 'publisher',
                        'R. Level'   => 'level',
                        'Avg Rating' => 'avrating',
                        'No. of Ratings' => 'nrating');

        $params = array('a' => 'admin', 'id' => $id, 'act' => 'booksratingbest', 'tab' => $tab,
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

} else if ($act == 'setbookinstances' && has_capability('mod/reader:managebooks', $contextmodule)) {

        reader_setbookinstances($id, $reader);

} else if ($act == 'viewlogsuspiciousactivity' && has_capability('mod/reader:viewreports', $contextmodule)) {
    $table = new html_table();

    $titles = array('Image'       => '',
                    'By Username' => 'byusername',
                    'Student 1'   => 'student1',
                    'Student 2'   => 'student2',
                    'Quiz'        => 'quiz',
                    'Status'      => 'status',
                    'Date'        => 'date');

    $params = array('a' => 'admin', 'id' => $id, 'act' => $act, 'gid' => $gid, 'page' => $page, 'tab' => $tab);
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
        $gid = null;
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

        $picture = $output->user_picture($byuser,array($course->id, true, 0, true));
        $table->data[] = new html_table_row(array(
            $picture,
            reader_fullname_link($byuser, $course->id, $excel),
            reader_fullname_link($user1, $course->id, $excel),
            reader_fullname_link($user2, $course->id, $excel),
            $quiz->name,
            $cheatedlog->status.$cheatedstring,
            $cheatedlog->date
            ));
    }

    reader_sort_table($table, $titles, $orderby, $sort, array('date' => $dateformat));

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
    echo $output->single_button(new moodle_url('admin.php',$options), get_string('downloadexcel', 'mod_reader'), 'post', $options);
    echo '</td></tr></table>';

    reader_select_perpage($id, $act, $sort, $orderby, $gid);
    list($totalcount, $table->data, $startrec, $finishrec, $options['page']) = reader_get_pages($table->data, $page, $perpage);
    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $output->render($pagingbar);

    if (isset($table) && count($table->data)) {
        echo html_writer::table($table);
    }

    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $output->render($pagingbar);

} else if ($act == 'exportstudentrecords' && has_capability('mod/reader:manageusers', $contextmodule)) {

    $users = array();
    $books = array();
    $levels = array();

    // get all attempts for this Reader (excluding deleted attempts)
    $sortfields = 'userid,quizid,timefinish,uniqueid DESC';
    $readerattempts = $DB->get_records('reader_attempts', array('readerid' => $reader->id, 'deleted' => 0), $sortfields);

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
            header('Content-Disposition: attachment; filename="'.$filename.'"');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        }

        echo $users[$userid]->username.','.
             $readerattempt->uniqueid.','. // this will have no meaning on the import site ?!
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

} else if ($act == 'importstudentrecord' && has_capability('mod/reader:manageusers', $contextmodule)) {

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
                $books[$image] = $DB->get_record('reader_books', array('image' => $image));
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

            $strpassed = reader_format_passed($values, true);
            $timefinish = userdate($values['timefinish'])." ($strpassed)";
            echo html_writer::tag('span', $timefinish, array('class' => 'importattempttime')).' ';

            $readerattempt = (object)array(
                // the "uniqueid" field is in fact an "id" from the "question_usages" table
                'uniqueid'      => reader_get_new_uniqueid($contextmodule->id, $books[$image]->quizid),
                'readerid'      => $reader->id,
                'userid'        => $users[$username]->id,
                'bookid'        => $books[$image]->id,
                'quizid'        => $books[$image]->quizid,
                'attempt'       => $values['attempt'],
                'deleted'       => 0,
                'sumgrades'     => $values['sumgrades'],
                'percentgrade'  => $values['percentgrade'],
                'passed'        => $values['passed'],
                'checkbox'      => 0,
                'timestart'     => $values['timefinish'],
                'timefinish'    => $values['timefinish'],
                'timemodified'  => $values['timefinish'],
                'layout'        => 0, // $values['layout']
                'credit'        => 0,
                'bookrating'    => $values['bookrating'],
                'ip'            => $values['ip'],
            );

            $params = array('userid' => $users[$username]->id, 'quizid' => $books[$image]->quizid, 'timefinish' => $values['timefinish'], 'deleted' => 0);
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

} else if ($act == 'changenumberofsectionsinquiz' && has_capability('mod/reader:addinstance', $contextmodule)) {
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

            $mform->addElement('header', 'setgoal', get_string('changenumberofsectionsinquiz', 'mod_reader'));
            $mform->addElement('text', 'numberofsections', '', array('size' => '10'));
            $mform->setType('numberofsections', PARAM_INT);

            $this->add_action_buttons(false, $submitlabel="Save");
        }
    }
    $mform = new mod_reader_changenumberofsectionsinquiz_form("admin.php?a=admin&id={$id}&act=changenumberofsectionsinquiz");
    $mform->display();

} else if ($act == 'assignpointsbookshavenoquizzes' && has_capability('mod/reader:manageusers', $contextmodule)) {
    $table = new html_table();

    $titles = array('<input type="button" value="Select all" onclick="checkall();" />' => '',
                    'Image' => '',
                    'Username' => 'username',
                    'Fullname<br />Click to view screen' => 'fullname',
                    'Current level' => 'currentlevel',
                    'Total words<br /> this term' => 'totalwordsthisterm',
                    'Total words<br /> all terms' => 'totalwordsallterms');

    $params = array('a' => 'admin', 'id' => $id, 'act' => $act, 'gid' => $gid, 'book' => $book, 'searchtext' => $searchtext, 'page' => $page, 'tab' => $tab);
    reader_make_table_headers($table, $titles, $orderby, $sort, $params);
    $table->align = array("center", "center", "left", "left", "center", "center", "center");
    $table->width = "100%";

    if (! $gid) {
        $gid = null;
    }

    $groupnames = array();
    foreach ($coursestudents as $coursestudent) {
        $groupnames[$coursestudent->username] = array();
        if (reader_check_search_text($searchtext, $coursestudent)) {
            $picture = $output->user_picture($coursestudent,array($course->id, true, 0, true));

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

            $select = 'userid = ? AND readerid = ? AND timefinish > ? AND deleted = ?';
            $params = array($coursestudent->id, $reader->id, $reader->ignoredate, 0);
            if ($attempts = $DB->get_records_select('reader_attempts', $select, $params)) {
                foreach ($attempts as $attempt) {
                    if (strtolower($attempt->passed) == 'true') {
                        if ($bookdata = $DB->get_record('reader_books', array('quizid' => $attempt->quizid))) {
                            $data['totalwordsthisterm'] += $bookdata->words;
                        }
                    }
                }
            }

            $select = 'userid= ? AND deleted = ?';
            $params = array($coursestudent->id, 0);
            if ($attempts = $DB->get_records_select('reader_attempts', $select, $params)) {
                foreach ($attempts as $attempt) {
                    if (strtolower($attempt->passed) == 'true') {
                        if ($bookdata = $DB->get_record('reader_books', array('quizid' => $attempt->quizid))) {
                            $data['totalwordsallterms'] += $bookdata->words;
                        }
                    }
                }
            }

            if ($readerattempt = reader_get_student_attempts($coursestudent->id, $reader)) {
                if (has_capability('mod/reader:manageattempts', $contextmodule)) {
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
                if (has_capability('mod/reader:manageattempts', $contextmodule)) {
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

    echo html_writer::start_tag('form', array('action' => new moodle_url('/mod/reader/admin.php'), 'method' => 'post'));

    echo html_writer::start_tag('div');
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'a', 'value' => 'admin'));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'id', 'value' => $id));
    echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'act', 'value' => $act));
    if ($book) {
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => 'book', 'value' => $book));
    }
    echo html_writer::end_tag('div');

    echo reader_available_books($id, $reader, $USER->id, 'noquiz');

    if (isset($table) && count($table->data)) {
        echo html_writer::table($table);
    }
    echo html_writer::end_tag('form');

    reader_select_perpage($id, $act, $sort, $orderby, $gid);
    list($totalcount, $table->data, $startrec, $finishrec, $options['page']) = reader_get_pages($table->data, $page, $perpage);
    $pagingbar = new paging_bar($totalcount, $page, $perpage, "admin.php?a=admin&id={$id}&act={$act}&book={$book}&sort={$sort}&orderby={$orderby}&gid={$gid}&amp;");
    echo $output->render($pagingbar);


} else if ($act == 'adjustscores' && has_capability('mod/reader:addinstance', $contextmodule)) {

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

    $params = array('a' => 'admin', 'id' => $id, 'act' => $act, 'searchtext' => $searchtext, 'tab' => $tab);
    reader_make_table_headers($table, $titles, $orderby, $sort, $params);

    $table->align = array('left', 'left', 'left', 'left', 'center', 'center', 'center', 'center', 'center', 'center', 'center');
    $table->width = '100%';

//    [publisher] => id=3456&publisher=Cambridge
//    [level] => Starter
//    [book] => 435

    if (is_int($book) && $book >= 1) {
        $bookdata = $DB->get_record('reader_books', array('id' => $book));
        $quizdata = $DB->get_record('quiz', array('id' => $bookdata->quizid));
        $readerattempts = $DB->get_records('reader_attempts', array('quizid' => $bookdata->quizid, 'readerid' => $reader->id, 'deleted' => 0));
        foreach ($readerattempts as $readerattempt) {
            $userdata = $DB->get_record('user', array('id' => $readerattempt->userid));
            $table->data[] = new html_table_row(array(
                html_writer::empty_tag('input', array('type' => 'checkbox', 'name' => 'adjustscoresupbooks[]', 'value' => $readerattempt->id)),
                fullname($userdata),
                html_writer::link(new moodle_url('/mod/reader/admin/reports.php', array('id' => $id, 'q' => $bookdata->quizid, 'mode' => 'analysis', 'b' => $bookdata->id)), $bookdata->name),
                $bookdata->publisher,
                $bookdata->level,
                reader_get_reader_difficulty($reader, $bookdata->id),
                $bookdata->difficulty,
                round($readerattempt->percentgrade).'%',
                reader_format_passed($readerattempt),
                $readerattempt->timemodified,
                '' // deleted
            ));
        }
    }

    reader_sort_table($table, $titles, $orderby, $sort, array('finishtime' => $dateformat));

    $publisherform = array();

    $publisherkey = 'id='.$id.'&publisher=Select_Publisher';
    $publisherform[$publisherkey] = get_string('selectpublisher', 'mod_reader');

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

    $submit = html_writer::empty_tag('input', array('type' => 'submit', 'value' => 'Select quiz'));

    $alink  = new moodle_url('/mod/reader/admin.php', array('id' => $id, 'act' => $act, 'a' => 'admin', 'tab' => $tab));

    if (isset($adjustscorestext)) {
        echo html_writer::tag('p', $adjustscorestext);
    }

    echo html_writer::start_tag('table', array('style' => 'width:100%'));
    echo html_writer::start_tag('tr');
    echo html_writer::start_tag('td', array('align' => 'right'));

    echo html_writer::start_tag('form', array('action' => $alink, 'method' => 'post', 'id' => 'mform1'));
    echo html_writer::start_tag('center');
    echo html_writer::start_tag('table', array('width' => '600px'));

    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', get_string('publisher', 'mod_reader'), array('width' => '200px'));
    echo html_writer::tag('td', '', array('width' => '10px'));
    echo html_writer::tag('td', '', array('width' => '200px'));
    echo html_writer::end_tag('tr');

    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', $select, array('valign' => 'top'));
    echo html_writer::tag('td', html_writer::tag('div', '', array('id' => 'bookleveldiv')), array('valign' => 'top'));
    echo html_writer::tag('td', html_writer::tag('div', '', array('id' => 'bookiddiv')), array('valign' => 'top'));
    echo html_writer::end_tag('tr');

    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', '', array('colspan' => 3, 'align' => 'center'));
    echo html_writer::end_tag('tr');

    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', html_writer::empty_tag('input', array('type' => 'submit', 'value' => 'Select quiz')), array('colspan' => 3, 'align' => 'center'));
    echo html_writer::end_tag('tr');

    echo html_writer::end_tag('table');
    echo html_writer::end_tag('form');
    echo html_writer::end_tag('center');

    echo html_writer::end_tag('td');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('table');

    $alink  = new moodle_url('/mod/reader/admin.php', array('id' => $id, 'act' => $act, 'book' => $book, 'a' => 'admin', 'tab' => $tab));

    echo html_writer::start_tag('form', array('action' => $alink, 'method' => 'post'));
    echo html_writer::start_tag('div', array('style' => '20px 0;'));

    echo html_writer::start_tag('table');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', 'Update selected adding', array('width' => '180px;'));
    echo html_writer::start_tag('td', array('width' => '60px;'));
    echo html_writer::empty_tag('input', array('type' => 'text', 'name' => 'adjustscoresaddpoints', 'value' => '', 'style' => 'width:60px;'));
    echo html_writer::end_tag('td');
    echo html_writer::tag('td', 'points', array('width' => '70px;'));
    echo html_writer::start_tag('td');
    echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('add')));
    echo html_writer::end_tag('td');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('table');

    echo html_writer::start_tag('table');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', 'Update all > ', array('width' => '100px;'));
    echo html_writer::start_tag('td', array('width' => '60px;'));
    echo html_writer::empty_tag('input', array('type' => 'text', 'name' => 'adjustscoresupall', 'value' => '', 'style' => 'width:50px;'));
    echo html_writer::end_tag('td');
    echo html_writer::tag('td', 'points and < ', array('width' => '90px;'));
    echo html_writer::start_tag('td', array('width' => '60px;'));
    echo html_writer::empty_tag('input', array('type' => 'text', 'name' => 'adjustscorespand', 'value' => '', 'style' => 'width:50px;'));
    echo html_writer::end_tag('td');
    echo html_writer::tag('td', 'points by ', array('width' => '90px;'));
    echo html_writer::start_tag('td', array('width' => '60px;'));
    echo html_writer::empty_tag('input', array('type' => 'text', 'name' => 'adjustscorespby', 'value' => '', 'style' => 'width:50px;'));
    echo html_writer::end_tag('td');
    echo html_writer::tag('td', 'points', array('width' => '70px;'));
    echo html_writer::start_tag('td');
    echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('add')));
    echo html_writer::end_tag('td');
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('table');

    echo html_writer::end_tag('div');

    if (count($table->data)) {
        echo html_writer::table($table);
    }

    echo html_writer::end_tag('form');
}

echo $output->box_end();
echo $output->footer();
