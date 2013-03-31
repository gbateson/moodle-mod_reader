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
 * mod/reader/lib.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die;



define('READER_GRADEHIGHEST', '1');
define('READER_GRADEAVERAGE', '2');
define('READER_ATTEMPTFIRST', '3');
define('READER_ATTEMPTLAST',  '4');
define('READER_REVIEW_OPEN',   0x3c00fc0);
define('READER_REVIEW_CLOSED', 0x3c03f000);
define('READER_REVIEW_SCORES', 2*0x1041);
define('READER_STATE_DURING', 'during');
define('READER_REVIEW_IMMEDIATELY',     0x3c003f);
define('READER_REVIEW_FEEDBACK',        4*0x1041);
define('READER_REVIEW_GENERALFEEDBACK', 32*0x1041);

/**
 * reader_get_config_defaults
 *
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_config_defaults() {
    $defaults = array(
        'quiztimeout'        => '15',
        'pointreport'        => '0',
        'percentforreading'  => '60',
        'questionmark'       => '0',
        'quiznextlevel'      => '6',
        'quizpreviouslevel'  => '3',
        'quizonnextlevel'    => '1',
        'bookcovers'         => '1',
        'attemptsofday'      => '0',
        'usecourse'          => '0',
        'iptimelimit'        => '0',
        'levelcheck'         => '1',
        'reportwordspoints'  => '0',
        'wordsprogressbar'   => '1',
        'checkbox'           => '0',
        'sendmessagesaboutcheating' => '1',
        'editingteacherrole' => '1',
        'update'             => '1',
        'last_update'        => '1',
        'update_interval'    => '604800',
        'cheated_message'    => "We are sorry to say that the MoodleReader program has discovered ".
                                "that you have probably cheated when you took the above quiz.  ".
                                "'Cheating' means that you either helped another person to take the quiz ".
                                "or that you received help from someone else to take the quiz.  ".
                                "Both people have been marked 'cheated'\n\n".
                                "Sometimes the computer makes mistakes.  ".
                                "If you honestly did not receive help and did not help someone else, ".
                                "then please inform your teacher and your points will be restored.\n\n".
                                "--The MoodleReader Module Manager",
        'not_cheated_message' => "We are happy to inform you that your points for the above quiz have been restored.  ".
                                 "We apologize for the mistake!\n\n".
                                 "--The MoodleReader Module Manager",
        'serverlink'         => 'http://moodlereader.net/quizbank',
        'serverlogin'        => '',
        'serverpassword'     => ''
    );

    $readercfg = get_config('reader');
    foreach ($defaults as $name => $value) {
        $name = 'reader_'.$name;
        if (! isset($readercfg->$name)) {
            set_config($name, $value, 'reader');
            $readercfg->$name = $value;
        }
    }
    return $readercfg;
}

$readercfg = reader_get_config_defaults();

/**
 * reader_add_instance
 *
 * @uses $CFG
 * @uses $DB
 * @uses $USER
 * @param xxx $reader
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_add_instance($reader) {

    global $CFG, $USER, $DB;
    $reader->timemodified = time();

    $reader->id = $DB->insert_record('reader', $reader);
    //print_r ($reader);
    //die;

    //No promotion after level
    if (isset($reader->promotionstop)) {
        $allstudents = $DB->get_records('reader_levels', array('readerid' => $reader->id));
        foreach ($allstudents as $allstudents_) {
            $DB->set_field('reader_levels',  'promotionstop',  $reader->promotionstop, array('id' => $allstudents_->id));
        }
    }

    return $reader->id;
}

/**
 * reader_update_instance
 *
 * @uses $CFG
 * @uses $DB
 * @param xxx $reader
 * @param xxx $id
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_update_instance($reader, $id) {
    global $CFG, $DB;

    $reader->timemodified = time();
    $reader->id = $reader->instance;

    # May have to add extra stuff in here #

    //No promotion after level
    if (isset($reader->promotionstop)) {
        $allstudents = $DB->get_records('reader_levels', array('readerid' => $reader->id));
        foreach ($allstudents as $allstudents_) {
            $DB->set_field('reader_levels',  'promotionstop',  $reader->promotionstop, array('id' => $allstudents_->id));
        }
    }

    return $DB->update_record('reader', $reader);

}

/**
 * reader_submit_instance
 *
 * @uses $CFG
 * @param xxx $reader
 * @param xxx $id
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_submit_instance($reader, $id) {
    global $CFG;
    return true;
}

/**
 * reader_delete_instance
 *
 * @uses $CFG
 * @uses $DB
 * @param xxx $id
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_delete_instance($id) {
    global $CFG,$DB;

    if (! $reader = $DB->get_record('reader', array('id' => $id))) {
        return false;
    }

    $result = true;

    # Delete any dependent records here #

    if (! $DB->delete_records('reader', array('id' => $reader->id))) {
        $result = false;
    }

    return $result;
}

/**
 * reader_user_outline
 *
 * @param xxx $course
 * @param xxx $user
 * @param xxx $mod
 * @param xxx $reader
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_user_outline($course, $user, $mod, $reader) {
    return $return;
}

/**
 * reader_user_complete
 *
 * @param xxx $course
 * @param xxx $user
 * @param xxx $mod
 * @param xxx $reader
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_user_complete($course, $user, $mod, $reader) {
    return true;
}

/**
 * reader_print_recent_activity
 *
 * @uses $CFG
 * @param xxx $course
 * @param xxx $isteacher
 * @param xxx $timestart
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_print_recent_activity($course, $isteacher, $timestart) {
    global $CFG;

    return false;  //  True if anything was printed, otherwise false
}

/**
 * reader_cron
 *
 * @uses $CFG
 * @uses $DB
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_cron() {
    global $CFG,$DB;

    $textmessages = $DB->get_records('reader_messages');

    foreach ($textmessages as $textmessage) {
        $before = $textmessage->timebefore - time();

        if ($before <= 0) {
            $DB->delete_records('reader_messages', array('id' => $textmessage->id));
        }
    }

    //Check questions list

    $publishersquizzes = $DB->get_records('reader_books');

    foreach ($publishersquizzes as $publishersquizze) {
        $quizdata = $DB->get_record('quiz', array('id' => $publishersquizze->quizid));
        if (empty($quizdata)) {
            $quizdata = (object)array('questions' => '');
        }
        $questions = explode(',', $quizdata->questions);
        $answersgrade = $DB->get_records('reader_question_instances', array('quiz' => $publishersquizze->id));
        $doublecheck = array();
        foreach ($answersgrade as $answersgrade_) {
            if (! in_array($answersgrade_->question, $questions)) {
                $DB->delete_records('reader_question_instances', array('quiz' => $publishersquizze->id, 'question' => $answersgrade_->question));
                $editedquizzes[$publishersquizze->id] = $publishersquizze->quizid;
            }
            if (! in_array($answersgrade_->question, $doublecheck)) {
                $doublecheck[] = $answersgrade_->question;
            } else {
                add_to_log(1, 'reader', 'Cron', '', "Double entries found!! reader_question_instances; quiz: {$publishersquizze->id}; question: {$answersgrade_->question}");
            }
        }
    }

    $publishersquizzes = $DB->get_records('reader_books');

    foreach ($publishersquizzes as $publishersquizze) {
        if (strstr($publishersquizze->name, "\'")) {
            $DB->set_field('reader_books',  'name', stripslashes($publishersquizze->name), array('id' => $publishersquizze->id));
            echo '..reader title updating: '.$publishersquizze->name."\n";
        }
    }

    return true;
}

/**
 * reader_grades
 *
 * @param xxx $readerid
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_grades($readerid) {
   return null;
}

/**
 * reader_get_participants
 *
 * @param xxx $readerid
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_participants($readerid) {
    return false;
}

/**
 * reader_get_user_attempt_unfinished
 *
 * @param xxx $readerid
 * @param xxx $userid
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_user_attempt_unfinished($readerid, $userid) {
    $attempts = reader_get_user_attempts($readerid, $userid, 'unfinished', true);
    if ($attempts) {
        return array_shift($attempts);
    } else {
        return false;
    }
}

/**
 * reader_get_stlevel_data
 *
 * @uses $CFG
 * @uses $DB
 * @uses $USER
 * @param xxx $reader
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_stlevel_data($reader) {
    global $USER, $CFG,$DB;

    $counter['countlevel'] = 0;
    $counter['prevlevel'] = 0;
    $counter['nextlevel'] = 0;

    if (! $studentlevel = $DB->get_record('reader_levels', array('userid' => $USER->id, 'readerid' => $reader->id))) {
        $createlevel = new object;
        $createlevel->userid = $USER->id;
        $createlevel->startlevel = 0;
        $createlevel->currentlevel = 0;
        $createlevel->readerid = $reader->id;
        $createlevel->promotionstop = $reader->promotionstop;
        $createlevel->time = time();
        $DB->insert_record('reader_levels', $createlevel);
        $studentlevel = $DB->get_record('reader_levels', array('userid' => $USER->id, 'readerid' => $reader->id));
    }

    $attemptsofbook = $DB->get_records_sql('SELECT ra.*,rp.difficulty,rp.id as rpid FROM {reader_attempts} ra INNER JOIN {reader_books} rp ON rp.quizid = ra.quizid WHERE ra.userid= ?  AND ra.reader= ?  AND ra.timefinish> ?  ORDER BY ra.timemodified', array($USER->id, $reader->id, $reader->ignoredate));

    foreach ($attemptsofbook as $attemptsofbook_) {
        if (reader_get_reader_difficulty($reader, $attemptsofbook_->rpid) == $studentlevel->currentlevel) {
            if (strtolower($attemptsofbook_->passed) == 'true') {
                $counter['countlevel'] += 1;
            }
        }

        if (($studentlevel->time < $attemptsofbook_->timefinish) && (reader_get_reader_difficulty($reader, $attemptsofbook_->rpid) == ($studentlevel->currentlevel + 1))) {
            $counter['nextlevel'] += 1;
        }

        if ($studentlevel->currentlevel >= $studentlevel->startlevel) {
            if (($studentlevel->time < $attemptsofbook_->timefinish) && (reader_get_reader_difficulty($reader, $attemptsofbook_->rpid) == ($studentlevel->currentlevel - 1))) {
                $counter['prevlevel'] += 1;
            }
        } else {
            $counter['prevlevel'] = -1;
        }
    }

    if ($studentlevel->promotionstop > 0 && $studentlevel->promotionstop == $studentlevel->currentlevel) {
        $DB->set_field('reader_levels',  "nopromote",  1, array('readerid' => $reader->id,  'userid' => $USER->id));
        $studentlevel->nopromote = 1;
    }

    if ($studentlevel->nopromote == 1) {
        $counter['countlevel'] = 1;
    }

    if ($counter['countlevel'] >= $reader->nextlevel) {
        $studentlevel->currentlevel += 1;
        $DB->set_field('reader_levels',  'currentlevel',  $studentlevel->currentlevel, array('readerid' => $reader->id,  'userid' => $USER->id));
        $DB->set_field('reader_levels', 'time', time(), array('readerid' => $reader->id, 'userid' => $USER->id));
        $counter['countlevel'] = 0;
        $counter['prevlevel'] = 0;
        $counter['nextlevel'] = 0;
        echo '<script type="text/javascript">'."\n";
        echo '//<![CDATA['."\n";
        echo 'alert("Congratulations!!  You have been promoted to Level '.$studentlevel->currentlevel.'!");'."\n";
        echo '//]]>'."\n";
        echo '</script>';
    }



    $leveldata['studentlevel'] = $studentlevel->currentlevel;
    $leveldata['onthislevel'] = $reader->nextlevel - $counter['countlevel'];
    if ($counter['prevlevel'] != -1) {
        $leveldata['onprevlevel'] = $reader->quizpreviouslevel - $counter['prevlevel'];
    } else {
        $leveldata['onprevlevel'] = -1;
    }
    $leveldata['onnextlevel'] = $reader->quiznextlevel - $counter['nextlevel'];

    return $leveldata;
}

/**
 * reader_get_user_attempts
 *
 * @uses $DB
 * @param xxx $readerid
 * @param xxx $userid
 * @param xxx $status (optional, default='finished')
 * @param xxx $includepreviews (optional, default=false)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_user_attempts($readerid, $userid, $status = 'finished', $includepreviews = false) {
    global $DB;

    $select = 'reader = ? AND userid = ?';
    $params = array($readerid, $userid);

    switch ($status) {
        case 'finished':
            $select .= ' AND timefinish > ?';
            $params[] = 0;
            break;
        case 'unfinished':
            $select .= ' AND timefinish = ?';
            $params[] = 0;
            break;
        case 'all': break; // do nothing
    }

    if ($includepreviews==false) {
        $select .= ' AND preview = ?';
        $params[] = 0;
    }

    if ($attempts = $DB->get_records_select('reader_attempts', $select, $params, 'attempt ASC')) {
        return $attempts;
    } else {
        return array();
    }
}

/**
 * reader_create_attempt
 *
 * @uses $CFG
 * @uses $DB
 * @uses $USER
 * @param xxx $reader
 * @param xxx $attemptnumber
 * @param xxx $bookid
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_create_attempt($reader, $attemptnumber, $bookid) {
    global $USER, $CFG,$DB;

    $book = $DB->get_record('reader_books', array('id' => $bookid));

    if (empty($book) || empty($book->quizid)) {
        die('Oops, no $book or $book->quizid');
        return false; // invalid $bookid or $book->quizid
    }

    $params = array('reader' => $reader->id,  'userid' => $USER->id,  'attempt' => ($attemptnumber - 1));
    if ($attemptnumber > 1 && $reader->attemptonlast && ($attempt = $DB->get_record('reader_attempts', $params))) {
        // do nothing - we will build on previous attempt
    } else {
        // we are not building on last attempt so create a new attempt

        // save the list of question ids (for use in quiz/attemptlib.php)
        if (! $reader->questions = $DB->get_field('quiz', 'questions', array('id' => $book->quizid))) {
            $reader->questions = ''; // shouldn't happen !!
        }

        $attempt = (object)array(
            'reader'  => $reader->id,
            'userid'  => $USER->id,
            'quizid'  => $book->quizid,
            'preview' => 0,
            'layout' => reader_repaginate($reader->questions, $reader->questionsperpage)
        );
    }

    $time = time();
    $attempt->attempt      = $attemptnumber;
    $attempt->sumgrades    = 0.0;
    $attempt->timestart    = $time;
    $attempt->timefinish   = 0;
    $attempt->timemodified = $time;
    //$attempt->uniqueid = reader_new_attempt_uniqueid();

    $questionids = explode (',', $attempt->layout);
    $questionids = array_filter($questionids); // remove blanks

    // get ids of question instances already exist
    list($select, $params) = $DB->get_in_or_equal($questionids);

    $select = "question $select AND quiz = ?";
    array_push($params, $book->quizid);

    if ($instances = $DB->get_records_select('reader_question_instances', $select, $params)) {
        foreach ($instances as $instance) {
            $i = array_search($instance->question, $questionids);
            if (is_numeric($i)) {
                unset($questionids[$i]);
            }
        }
    }

    // any remaining $questionids do not already have a
    // "reader_question_instances" record, so we create one
    foreach ($questionids as $questionid) {
        if (empty($book->quizid)) {
            $grade = $DB->get_field('question', 'defaultgrade', array('id' => $questionid));
        } else {
            $params = array('quiz' => $book->quizid,  'question' => $questionid);
            $grade = $DB->get_field('quiz_question_instances', 'grade', $params);
        }
        $instance = (object)array(
            'quiz'     => $book->quizid,
            'question' => $questionid,
            'grade'    => (empty($grade) ? 0 : round($grade))
        );
        if (! $attempt->id = $DB->insert_record('reader_question_instances', $instance)) {
            // could not insert new attempt - shouldn't happen !!
        }
    }

    return $attempt;
}

/**
 * reader_delete_attempt
 *
 * @uses $DB
 * @param xxx $attempt
 * @param xxx $reader
 * @todo Finish documenting this function
 */
function reader_delete_attempt($attempt, $reader) {
    global $DB;

    if (is_numeric($attempt)) {
        if (! $attempt = $DB->get_record('reader_attempts', array('id' => $attempt))) {
            return;
        }
    }

    if ($attempt->reader != $reader->id) {
        debugging("Trying to delete attempt $attempt->id which belongs to reader $attempt->reader " .
                "but was passed reader $reader->id.");
        return;
    }

    $DB->delete_records('reader_attempts', array('id' => $attempt->id));
    delete_attempt($attempt->uniqueid);

    // Search reader_attempts for other instances by this user.
    // If none, then delete record for this reader, this user from reader_grades
    // else recalculate best grade

    $userid = $attempt->userid;
    if (! record_exists('reader_attempts', 'userid', $userid, 'reader', $reader->id)) {
        $DB->delete_records('reader_grades', array('userid' => $userid,'reader' => $reader->id));
    } else {
        reader_save_best_grade($reader, $userid);
    }

    reader_update_grades($reader, $userid);
}

/**
 * reader_save_best_grade
 *
 * @uses $DB
 * @uses $USER
 * @param xxx $reader
 * @param xxx $userid (optional, default=null)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_save_best_grade($reader, $userid = null) {
    global $USER,$DB;

    if (empty($userid)) {
        $userid = $USER->id;
    }
    // Get all the attempts made by the user
    if (! $attempts = reader_get_user_attempts($reader->id, $userid)) {
        notify('Could not find any user attempts');
        return false;
    }
    // Calculate the best grade
    $bestgrade = reader_calculate_best_grade($reader, $attempts);
    $bestgrade = reader_rescale_grade($bestgrade, $reader);
    // Save the best grade in the database
    if ($grade = $DB->get_record('reader_grades', array('reader' => $reader->id,  'userid' => $userid))) {
        $grade->grade = $bestgrade;
        $grade->timemodified = time();
        if (! $DB->update_record('reader_grades', $grade)) {
            notify('Could not update best grade');
            return false;
        }
    } else {
        $grade->reader = $reader->id;
        $grade->userid = $userid;
        $grade->grade = $bestgrade;
        $grade->timemodified = time();
        if (! $DB->insert_record('reader_grades', $grade)) {
            notify('Could not insert new best grade');
            return false;
        }
    }

    reader_update_grades($reader, $userid);
    return true;
}

/**
 * reader_update_grades
 *
 * @uses $CFG
 * @uses $DB
 * @param xxx $reader (optional, default=null)
 * @param xxx $userid (optional, default=0)
 * @param xxx $nullifnone (optional, default=true)
 * @todo Finish documenting this function
 */
function reader_update_grades($reader=null, $userid=0, $nullifnone=true) {
    global $CFG,$DB;
    if (! function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->dirroot.'/lib/gradelib.php');
    }
    if ($reader != null) {
        if ($grades = reader_get_user_grades($reader, $userid)) {
            reader_grade_item_update($reader, $grades);
        } else if ($userid and $nullifnone) {
            $grade = new object();
            $grade->userid   = $userid;
            $grade->rawgrade = NULL;
            reader_grade_item_update($reader, $grade);
        }

    } else {
        $sql = "SELECT a.*, cm.idnumber as cmidnumber, a.course as courseid
                  FROM {reader} a, {course_modules} cm, {modules} m
                 WHERE m.name='reader' AND m.id=cm.module AND cm.instance=a.id";

        if ($rs = $DB->get_recordset_sql($sql)) {
          foreach ($rs as $reader) {
              if ($reader->grade != 0) {
                  reader_update_grades($reader, 0, false);
              } else {
                  reader_grade_item_update($reader);
              }
          }
          $rs->close();
        }
    }
}

/**
 * reader_calculate_best_grade
 *
 * @param xxx $reader
 * @param xxx $attempts
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_calculate_best_grade($reader, $attempts) {

    switch ($reader->grademethod) {

        case READER_ATTEMPTFIRST:
            foreach ($attempts as $attempt) {
                return $attempt->sumgrades;
            }
            break;

        case READER_ATTEMPTLAST:
            foreach ($attempts as $attempt) {
                $final = $attempt->sumgrades;
            }
            return $final;

        case READER_GRADEAVERAGE:
            $sum = 0;
            $count = 0;
            foreach ($attempts as $attempt) {
                $sum += $attempt->sumgrades;
                $count++;
            }
            return (float)$sum/$count;

        default:
        case READER_GRADEHIGHEST:
            $max = 0;
            foreach ($attempts as $attempt) {
                if ($attempt->sumgrades > $max) {
                    $max = $attempt->sumgrades;
                }
            }
            return $max;
    }
}

/**
 * reader_rescale_grade
 *
 * @param xxx $rawgrade
 * @param xxx $reader
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_rescale_grade($rawgrade, $reader) {
    if ($reader->sumgrades) {
        return round($rawgrade*$reader->grade/$reader->sumgrades, $reader->decimalpoints);
    } else {
        return 0;
    }
}

/**
 * reader_get_user_grades
 *
 * @uses $CFG
 * @uses $DB
 * @param xxx $reader
 * @param xxx $userid (optional, default=0)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_user_grades($reader, $userid=0) {
    global $CFG,$DB;

    $user = $userid ? "AND u.id = $userid" : "";

    $sql = "SELECT u.id, u.id AS userid, g.grade AS rawgrade, g.timemodified AS dategraded, MAX(a.timefinish) AS datesubmitted
            FROM {$CFG->prefix}user u, {$CFG->prefix}reader_grades g, {$CFG->prefix}reader_attempts a
            WHERE u.id = g.userid AND g.reader = {$reader->id} AND a.reader = g.reader AND u.id = a.userid
                  $user
            GROUP BY u.id, g.grade, g.timemodified";

    return $DB->get_records_sql($sql);
}

/**
 * reader_grade_item_update
 *
 * @uses $CFG
 * @param xxx $reader
 * @param xxx $grades (optional, default=NULL)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_grade_item_update($reader, $grades=NULL) {
    global $CFG;
    if (! function_exists('grade_update')) { //workaround for buggy PHP versions
        require_once($CFG->dirroot.'/lib/gradelib.php');
    }

    if (array_key_exists('cmidnumber', $reader)) { //it may not be always present
        $params = array('itemname'=>$reader->name, 'idnumber'=>$reader->cmidnumber);
    } else {
        $params = array('itemname'=>$reader->name);
    }

    if ($reader->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $reader->grade;
        $params['grademin']  = 0;

    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    $is_closed = ($reader->review & READER_REVIEW_SCORES & READER_REVIEW_CLOSED);
    $is_open   = ($reader->review & READER_REVIEW_SCORES & READER_REVIEW_OPEN);

    if (! $is_closed && ! $is_open) {
        $params['hidden'] = 1;
    } else if ( $is_closed && ! $is_open) {
        if ($reader->timeclose) {
            $params['hidden'] = $reader->timeclose;
        } else {
            $params['hidden'] = 1;
        }
    } else {
        // a) both open and closed enabled
        // b) open enabled, closed disabled - we can not "hide after", grades are kept visible even after closing
        $params['hidden'] = 0;
    }

    return grade_update('mod/quiz', $reader->course, 'mod', 'reader', $reader->id, 0, $grades, $params);
}

/**
 * reader_repaginate
 *
 * @param xxx $layout
 * @param xxx $perpage
 * @param xxx $shuffle (optional, default=false)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_repaginate($layout, $perpage, $shuffle=false) {
    $layout = preg_replace('/,+/',',', $layout);
    $layout = str_replace(',0', '', $layout); // remove existing page breaks
    $questions = explode(',', $layout);
    if ($shuffle) {
        srand((float)microtime() * 1000000); // for php < 4.2
        shuffle($questions);
    }
    $i = 1;
    $layout = '';
    foreach ($questions as $question) {
        if ($perpage and $i > $perpage) {
            $layout .= '0,';
            $i = 1;
        }
        $layout .= $question.',';
        $i++;
    }
    return $layout.'0';
}

/**
 * reader_questions_on_page
 *
 * @param xxx $layout
 * @param xxx $page
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_questions_on_page($layout, $page) {
    $pages = explode(',0', $layout);
    return trim($pages[$page], ',');
}

/**
 * reader_questions_in_reader
 *
 * @param xxx $layout
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_questions_in_reader($layout) {
    return str_replace(',0', '', $layout);
}

/**
 * reader_number_of_pages
 *
 * @param xxx $layout
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_number_of_pages($layout) {
    return substr_count($layout, ',0');
}

/**
 * reader_print_navigation_panel
 *
 * @param xxx $page
 * @param xxx $pages
 * @todo Finish documenting this function
 */
function reader_print_navigation_panel($page, $pages) {
    echo '<div class="pagingbar">';
    echo '<span class="title">' . get_string('page') . ':</span>';

    echo ($page + 1) . '('.$pages.')';

    if ($page < $pages - 1) {
        // Print next link
        $strnext = get_string('next');
    }
    echo '</div>';
}

/**
 * reader_first_questionnumber
 *
 * @uses $CFG
 * @uses $DB
 * @param xxx $readerlayout
 * @param xxx $pagelayout
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_first_questionnumber($readerlayout, $pagelayout) {
    // this works by finding all the questions from the readerlayout that
    // come before the current page and then adding up their lengths.
    global $CFG,$DB;
    $start = strpos($readerlayout, ','.$pagelayout.',')-2;
    if ($start > 0) {
        $prevlist = substr($readerlayout, 0, $start);
        return $DB->get_field_sql('SELECT sum(length)+1 FROM {question}
         WHERE id IN (?)',array($prevlist));
    } else {
        return 1;
    }
}

/**
 * reader_get_renderoptions
 *
 * @param xxx $reviewoptions
 * @param xxx $state
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_renderoptions($reviewoptions, $state) {
    $options = new stdClass;

    // Show the question in readonly (review) mode if the question is in
    // the closed state
    $options->readonly = question_state_is_closed($state);

    // Show feedback once the question has been graded (if allowed by the reader)
    $options->feedback = question_state_is_graded($state) && ($reviewoptions & READER_REVIEW_FEEDBACK & READER_REVIEW_IMMEDIATELY);

    // Show validation only after a validation event
    $options->validation = QUESTION_EVENTVALIDATE === $state->event;

    // Show correct responses in readonly mode if the reader allows it
    $options->correct_responses = $options->readonly && ($reviewoptions & READER_REVIEW_ANSWERS & READER_REVIEW_IMMEDIATELY);

    // Show general feedback if the question has been graded and the reader allows it.
    $options->generalfeedback = question_state_is_graded($state) && ($reviewoptions & READER_REVIEW_GENERALFEEDBACK & READER_REVIEW_IMMEDIATELY);

    // Show overallfeedback once the attempt is over.
    $options->overallfeedback = false;

    // Always show responses and scores
    $options->responses = true;
    $options->scores = true;
    $options->readerstate = READER_STATE_DURING;

    return $options;
}

/**
 * reader_questions_in_quiz
 *
 * @param xxx $layout
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_questions_in_quiz($layout) {
    return str_replace(',0', '', $layout);
}

/**
 * reader_scale_used
 *
 * @param xxx $readerid
 * @param xxx $scaleid
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_scale_used($readerid,$scaleid) {
    $return = false;

    return $return;
}

/**
 * reader_make_table_headers
 *
 * @uses $CFG
 * @uses $USER
 * @param xxx $titlesarray
 * @param xxx $orderby
 * @param xxx $sort
 * @param xxx $link
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_make_table_headers($titlesarray, $orderby, $sort, $link) {

global $USER, $CFG;

    if ($orderby == 'ASC') {
        $columndir    = 'DESC';
        $columndirimg = 'down';
    } else {
        $columndir    = 'ASC';
        $columndirimg = 'up';
    }

    foreach ($titlesarray as $titlesarraykey => $titlesarrayvalue) {
        if ($sort != $titlesarrayvalue) {
            $columnicon = '';
        } else {
            $url = new moodle_url('/theme/image.php', array('theme' => $CFG->theme, 'image' => "t/$columndirimg", 'rev' =>$CFG->themerev));
            $columnicon = ' <img src="'.$url.'" alt="" />';
        }
        if (! empty($titlesarrayvalue)) {
            $table->head[] = "<a href=\"".$link."&sort=$titlesarrayvalue&orderby=$columndir\">$titlesarraykey</a>$columnicon";
        } else {
            $table->head[] = $titlesarraykey;
        }
    }

    return $table->head;

}

/**
 * reader_sort_table_data
 *
 * @uses $CFG
 * @uses $USER
 * @param xxx $data
 * @param xxx $columns
 * @param xxx $sortdirection
 * @param xxx $sortcolumn
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_sort_table_data($data, $columns, $sortdirection, $sortcolumn) {

    global $USER, $CFG;

    $finaldata = array();
    if (empty($data)) {
        return $finaldata;
    }

    $sortindex = 0; // default is first column
    if ($sortcolumn) {
        $i = 0;
        foreach ($columns as $column) {
            if ($column == $sortcolumn) {
                $sortindex = $i;
            }
            $i++;
        }
    }

    $i = 0;
    $sorted = array();
    foreach ($data as $datakey => $datavalue) {
        $key = '';
        if (isset($datavalue[$sortindex])) {
            if (is_array($datavalue[$sortindex])) {
                $key = $datavalue[$sortindex][1];
            } else {
                $key = $datavalue[$sortindex];
            }
        }

        for ($j=0; $j < count($datavalue); $j++) {
            if (is_array($datavalue[$j])) {
                $sorted["$key"][$i][$j] = $datavalue[$j][0];
            } else {
                $sorted["$key"][$i][$j] = $datavalue[$j];
            }
        }
        $i++;
    }

    if (empty($sortdirection) || $sortdirection=='ASC') {
        ksort($sorted);
    } else {
        krsort($sorted);
    }

    reset($sorted);
    foreach (array_keys($sorted) as $key) {
        foreach (array_keys($sorted[$key]) as $i) {
            $finaldata[] = array_values($sorted[$key][$i]);
        }
    }

    return $finaldata;
}

/**
 * reader_question_preview_button
 *
 * @uses $CFG
 * @uses $COURSE
 * @param xxx $quiz
 * @param xxx $question
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_question_preview_button($quiz, $question) {
    global $CFG, $COURSE;
    if (! question_has_capability_on($question, 'use', $question->category)){
        return '';
    }
    $strpreview = get_string('previewquestion', 'quiz');
    $quizorcourseid = $quiz->id?('&amp;quizid=' . $quiz->id):('&amp;courseid=' .$COURSE->id);
    return link_to_popup_window('/question/preview.php?id=' . $question->id . $quizorcourseid, 'questionpreview',
            "<img src=\"{$CFG->wwwroot}/theme/image.php?theme={$CFG->theme}&image=preview&rev={$CFG->themerev}\" class=\"iconsmall\" alt=\"$strpreview\" />",
            0, 0, $strpreview, QUESTION_PREVIEW_POPUP_OPTIONS, true);
}

/**
 * reader_get_student_attempts
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $DB
 * @uses $bookpercentmaxgrade
 * @param xxx $userid
 * @param xxx $reader
 * @param xxx $allreaders (optional, default=false)
 * @param xxx $booklist (optional, default=false)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_student_attempts($userid, $reader, $allreaders = false, $booklist = false) {
    global $CFG, $COURSE, $DB, $bookpercentmaxgrade;

    if ($booklist) {
        $reader->ignoredate = 0;
    }

    $select = 'ra.timefinish,ra.userid,ra.attempt,ra.percentgrade,ra.id,ra.quizid,ra.sumgrades,ra.passed,ra.checkbox,ra.preview,'.
              'rp.name,rp.publisher,rp.level,rp.length,rp.image,rp.difficulty,rp.words,rp.sametitle,rp.id as rpid';
    $from   = '{reader_attempts} ra LEFT JOIN {reader_books} rp ON rp.quizid = ra.quizid';
    $where  = 'ra.preview != 1 AND ra.userid= :userid AND ra.timefinish > :readerignoredate';
    $params = array('userid'=>$userid, 'readerignoredate'=>$reader->ignoredate);
    if (! $allreaders) {
        $where .= ' AND ra.reader = :readerid';
        $params['readerid'] = $reader->id;
    }
    if (! $attempts_p = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY ra.timefinish", $params)) {
        $attempts_p = array();
    }

    $select = 'ra.timefinish,ra.userid,ra.attempt,ra.percentgrade,ra.id,ra.quizid,ra.sumgrades,ra.passed,ra.checkbox,ra.preview,'.
              'rp.name,rp.publisher,rp.level,rp.length,rp.image,rp.difficulty,rp.words,rp.sametitle,rp.id as rpid';
    $from   = '{reader_attempts} ra LEFT JOIN {reader_noquiz} rp ON rp.quizid = ra.quizid';
    $where  = 'ra.preview = 1 AND ra.userid= :userid AND ra.timefinish > :readerignoredate';
    $params = array('userid'=>$userid, 'readerignoredate'=>$reader->ignoredate);
    if (! $allreaders) {
        $where .= ' AND ra.reader = :readerid';
        $params['readerid'] = $reader->id;
    }
    if (! $attempts_n = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY ra.timefinish", $params)) {
        $attempts_n = array();
    }

    $attempts = array_merge($attempts_p, $attempts_n);
    $attempts = array_filter($attempts); // remove blanks

    $level = $DB->get_record('reader_levels', array('userid' => $userid,  'readerid' => $reader->id));
    if (empty($level)) {
        $level = (object)array('currentlevel' => 0, 'startlevel' => 0);
    }

    $returndata = array();
    $bestattemptids = array();

    $totals = array();
    $totals['correct']       = 0;
    $totals['incorrect']     = 0;
    $totals['totalpoints']   = 0;
    $totals['countattempts'] = 0;
    $totals['startlevel']    = $level->startlevel;
    $totals['currentlevel']  = $level->currentlevel;

    foreach ($attempts as $attempt) {

        $totals['countattempts']++;
        if ($attempt->passed == 'true' || $attempt->passed == 'TRUE') {
            $statustext = 'Passed';
            $status = 'correct';
            $totals['points'] = reader_get_reader_length($reader, $attempt->rpid);
            $totals['correct']++;
        } else {
            if($attempt->passed=='cheated') {
                $statustext = '<span style="color:red">Cheated</span>';
            } else {
                $statustext = 'Not Passed';
            }
            $status = 'incorrect';
            $totals['points'] = 0;
            $totals['incorrect']++;
        }
        $totals['totalpoints'] += round($totals['points'], 2);

        if (isset($bookpercentmaxgrade[$attempt->quizid])) {
            list($totals['bookpercent'], $totals['bookmaxgrade']) = $bookpercentmaxgrade[$attempt->quizid];
        } else {
            $totalgrade = 0;
            $answersgrade = $DB->get_records ('reader_question_instances', array('quiz' => $attempt->quizid)); // Count Grades (TotalGrade)
            foreach ($answersgrade as $answersgrade_) {
                $totalgrade += $answersgrade_->grade;
            }
            //$totals['bookpercent']  = round(($attempt->sumgrades/$totalgrade) * 100, 2).'%';
            $totals['bookpercent']  = $attempt->percentgrade.'%';
            $totals['bookmaxgrade'] = $totalgrade * reader_get_reader_length($reader, $attempt->rpid);
            $bookpercentmaxgrade[$attempt->quizid] = array($totals['bookpercent'], $totals['bookmaxgrade']);
        }

        if ($attempt->preview == 1) {
            $statustext = 'Credit';
        }

        // get best attemptid for this quiz
        if (empty($bestattemptids[$attempt->quizid])) {
            $bestattemptid = 0;
        } else {
            $bestattemptid = $bestattemptids[$attempt->quizid];
        }
        if ($bestattemptid==0 || $returndata[$bestattemptid]['percentgrade'] < $attempt->percentgrade) {
            $bestattemptids[$attempt->quizid] = $attempt->id;
        }

        $returndata[$attempt->id] = array('id'            => $attempt->id,
                                          'quizid'        => $attempt->quizid,
                                          'timefinish'    => $attempt->timefinish,
                                          'booktitle'     => $attempt->name,
                                          'image'         => $attempt->image,
                                          'words'         => $attempt->words,
                                          'booklength'    => reader_get_reader_length($reader, $attempt->rpid),
                                          'publisher'     => $attempt->publisher,
                                          'booklevel'     => $attempt->level,
                                          'bookdiff'      => reader_get_reader_difficulty($reader, $attempt->rpid),
                                          'percentgrade'  => $attempt->percentgrade,
                                          'passed'        => $attempt->passed,
                                          'checkbox'      => $attempt->checkbox,
                                          'sametitle'     => $attempt->sametitle,
                                          'userlevel'     => $level->currentlevel,
                                          'status'        => $status,
                                          'statustext'    => $statustext,
                                          'bookpoints'    => $totals['points'],
                                          'bookpercent'   => $totals['bookpercent'],
                                          'bookmaxgrade'  => $totals['bookmaxgrade'],
                                          'totalpoints'   => $totals['totalpoints'],
                                          'startlevel'    => $level->startlevel,
                                          'currentlevel'  => $level->currentlevel);
    }

    // remove attempts that are not the best
    foreach (array_keys($returndata) as $attemptid) {
        if (! in_array($attemptid, $bestattemptids)) {
            unset($returndata[$attemptid]);
        }
    }

    return array($returndata, $totals);

}

/**
 * reader_print_group_select_box
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $grid
 * @param xxx $courseid
 * @param xxx $link
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_print_group_select_box($courseid, $link) {
    global $CFG, $COURSE, $grid;

    $groups = groups_get_all_groups ($courseid);

    if ($groups) {
        echo '<table style="width:100%"><tr><td align="right">';
        echo '<form action="" method="post" id="mform_gr"><select onchange="document.getElementById(\'mform_gr\').action = document.getElementById(\'mform_gr\').level.options[document.getElementById(\'mform_gr\').level.selectedIndex].value;document.getElementById(\'mform_gr\').submit(); return true;" name="level" id="id_level">';
        echo '<option value="'.$link.'&grid=0">'.get_string('allgroups', 'reader').'</option>';
        foreach ($groups as $groupkey => $groupvalue) {
            echo '<option value="'.$link.'&grid='.$groupkey.'" ';
            if ($groupkey == $grid) { echo 'selected="selected"'; }
                echo ' >'.$groupvalue->name.'</option>';
        }
        echo '</select></form>';
        echo '</td></tr></table>';
    }
}

/**
 * reader_get_pages
 *
 * @uses $CFG
 * @uses $COURSE
 * @param xxx $table
 * @param xxx $page
 * @param xxx $perpage
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_pages($table, $page, $perpage) {
    global $CFG, $COURSE;

    $totalcount = count ($table);
    $startrec  = $page * $perpage;
    $finishrec = $startrec + $perpage;

    if (empty($table)) {
        $table = array();
    }
    $viewtable = array();
    foreach ($table as $key => $value) {
        if ($key >= $startrec && $key < $finishrec) {
            $viewtable[] = $value;
        }
    }

    return array($totalcount, $viewtable, $startrec, $finishrec, $page);
}

/**
 * reader_user_link_t
 *
 * @uses $CFG
 * @uses $COURSE
 * @param xxx $userdata
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_user_link_t($userdata) {
    global $CFG, $COURSE;

    if (empty($userdata->userid)) {
        $userdata->userid = $userdata->id;
    }

    return array('<a href="'.$CFG->wwwroot.'/user/view.php?id='.$userdata->userid.'&course='.$COURSE->id.'">'.$userdata->username.'</a>', $userdata->username);
}

/**
 * reader_fullname_link_viewasstudent
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $act
 * @uses $id
 * @param xxx $userdata
 * @param xxx $link
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_fullname_link_viewasstudent($userdata, $link) {
    global $CFG, $COURSE, $id,$act;

    if (! isset($userdata->userid)) {
        $userdata->userid = $userdata->id;
    }

    return array('<a href="?a=admin&id='.$id.'&act='.$act.'&viewasstudent='.$userdata->id.'&'.$link.'">'.$userdata->firstname.' '.$userdata->lastname.'</a>', $userdata->firstname.' '.$userdata->lastname);
}

/**
 * reader_fullname_link_t
 *
 * @uses $CFG
 * @uses $COURSE
 * @param xxx $userdata
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_fullname_link_t($userdata) {
    global $CFG, $COURSE;

    if (! $userdata->userid) {
        $userdata->userid = $userdata->id;
    }

    return array('<a href="'.$CFG->wwwroot.'/user/view.php?id='.$userdata->userid.'&course='.$COURSE->id.'">'.$userdata->firstname.' '.$userdata->lastname.'</a>', $userdata->firstname.' '.$userdata->lastname);
}

/**
 * reader_select_perpage
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $_SESSION
 * @uses $book
 * @param xxx $id
 * @param xxx $act
 * @param xxx $sort
 * @param xxx $orderby
 * @param xxx $grid
 * @todo Finish documenting this function
 */
function reader_select_perpage($id, $act, $sort, $orderby, $grid) {
    global $CFG, $COURSE, $_SESSION, $book;

    $pages = array(30,60,100,200,500);

    echo '<table style="width:100%"><tr><td align="right"><form action="admin.php?a=admin&id='.$id.'" method="get"  id="chooseperpage" class="popupform">';
    echo 'Perpage <select id="choose_perpage" name="perpage" onchange="self.location=document.getElementById(\'chooseperpage\').perpage.options[document.getElementById(\'chooseperpage\').perpage.selectedIndex].value;">';

    foreach ($pages as $page) {
        if ($book) {
          echo '<option value="admin.php?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&orderby='.$orderby.'&book='.$book.'&grid='.$grid.'&perpage='.$page.'" ';
        } else {
          echo '<option value="admin.php?a=admin&id='.$id.'&act='.$act.'&sort='.$sort.'&orderby='.$orderby.'&grid='.$grid.'&perpage='.$page.'" ';
        }
        if ($_SESSION['SESSION']->reader_perpage == $page) {
            echo ' selected="selected" ';
        }
        echo '>'.$page.'</option>';
    }

    echo '</select></form></td></tr></table>';
}

/**
 * reader_print_search_form
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $OUTPUT
 * @uses $_SESSION
 * @uses $book
 * @uses $searchtext
 * @param xxx $id
 * @param xxx $act
 * @todo Finish documenting this function
 */
function reader_print_search_form($id, $act) {
    global $CFG, $COURSE, $_SESSION, $searchtext, $book;

    $searchtext = str_replace('\"', '"', $searchtext);

    echo '<table style="width:100%"><tr><td align="right">';
    if ($book) {
      echo '<form action="admin.php?a=admin&id='.$id.'&act='.$act.'&book='.$book.'" method="post" id="mform1">';
    } else {
      echo '<form action="admin.php?a=admin&id='.$id.'&act='.$act.'" method="post" id="mform1">';
    }
    echo '<input type="text" name="searchtext" value=\''.$searchtext.'\' style="width:120px;" />';
    echo '<input type="submit" name="submit" value="'.get_string('search', 'reader').'" />';
    echo '</form>';
    $options            = array();
    //$options["a"]       = $a;
    $options["act"]     = $act;
    $options["id"]    = $id;
    if ($searchtext) {
        global $OUTPUT;
        echo $OUTPUT->single_button(new moodle_url('admin.php', $options), get_string('showall', 'reader'), 'post', $options);
    }
    echo '</td></tr></table>';
}

/**
 * reader_check_search_text
 *
 * @param xxx $searchtext
 * @param xxx $coursestudent
 * @param xxx $book (optional, default=false)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_check_search_text($searchtext, $coursestudent, $book=false) {

    $searchtext = trim($searchtext);
    if ($searchtext=='') {
        return true; // no search string, so everything matches
    }

    if (strstr($searchtext, '"')) {
        $texts = str_replace('\"', '"', $searchtext);
        $texts = explode('"', $searchtext);
    } else {
        $texts = explode(' ', $searchtext);
    }
    array_filter($texts); // remove blanks

    foreach ($texts as $text) {
        $text = strtolower($text);

        if ($coursestudent) {
            $username  = strtolower($coursestudent->username);
            $firstname = strtolower($coursestudent->firstname);
            $lastname  = strtolower($coursestudent->lastname);
            if (strstr($username, $text) || strstr("$firstname $lastname", $text)) {
                return true;
            }
        }

        if ($book) {
            if (is_array($book)) {
                $booktitle = strtolower($book['booktitle']);
                $booklevel = strtolower($book['booklevel']);
                $publisher = strtolower($book['publisher']);
            } else {
                $booktitle = strtolower($book->name);
                $level     = strtolower($book->level);
                $publisher = strtolower($book->publisher);
            }

            if (strpos($booktitle, $text)===false && strpos($booklevel, $text)==false || strpos($publisher, $text)==false) {
                // do nothing
            } else {
                return true;
            }
        }
    }

    return false; // no part of the searchtext matched user or book details
}

/**
 * reader_check_search_text_quiz
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $_SESSION
 * @param xxx $searchtext
 * @param xxx $book
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_check_search_text_quiz($searchtext, $book) {

    $searchtext = trim($searchtext);
    if ($searchtext=='') {
        return true; // no search string, so everything matches
    }

    if (strstr($searchtext, '"')) {
        $texts = str_replace('\"', '"', $searchtext);
        $texts = explode('"', $searchtext);
    } else {
        $texts = explode(' ', $searchtext);
    }
    array_filter($texts); // remove blanks

    foreach ($texts as $text) {
        $text = strtolower($text);
        if ($book) {
            if (is_array($book)) {
                $booktitle = strtolower($book['booktitle']);
                $booklevel = strtolower($book['booklevel']);
                $publisher = strtolower($book['publisher']);
            } else {
                $booktitle = strtolower($book->name);
                $level     = strtolower($book->level);
                $publisher = strtolower($book->publisher);
            }

            if (strpos($booktitle, $text)===false && strpos($booklevel, $text)==false || strpos($publisher, $text)==false) {
                // do nothing
            } else {
                return true;
            }
        }
    }
    return false;
}

/**
 * reader_selectlevel_form
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $_SESSION
 * @uses $act
 * @uses $grid
 * @uses $id
 * @uses $orderby
 * @uses $page
 * @uses $sort
 * @param xxx $userid
 * @param xxx $leveldata
 * @param xxx $level
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_selectlevel_form($userid, $leveldata, $level) {
    global $CFG, $COURSE, $_SESSION, $id, $act, $grid, $sort, $orderby, $page;

    if (! isset($leveldata)) {
        $leveldata = new stdClass();
    }
    if (! isset($leveldata->$level)) {
        $leveldata->$level = 0;
    }

    $levels = array(0,1,2,3,4,5,6,7,8,9,10,12,13,14);

    $patch = $userid."_".$level;

    $string = '<div id="changelevels'.$patch.'">';

    $string .= '<select id="choose_levels'.$patch.'" name="levels'.$patch.'" onchange="request(\'admin.php?ajax=true&\' + this.options[this.selectedIndex].value,\'changelevels'.$patch.'\'); return false;">';

    foreach ($levels as $levels_) {
        $string .= '<option value="admin.php?a=admin&id='.$id.'&act='.$act.'&changelevel='.$levels_.'&userid='.$userid.'&slevel='.$level.'" ';
        if ($levels_ == $leveldata->$level) {
            $string .= ' selected="selected" ';
        }
        $string .= '>'.$levels_.'</option>';
    }

    $string .= '</select></div>';

    return $string;
}

/**
 * reader_promotion_stop_box
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $_SESSION
 * @uses $act
 * @uses $grid
 * @uses $id
 * @uses $orderby
 * @uses $page
 * @uses $sort
 * @param xxx $userid
 * @param xxx $data
 * @param xxx $field
 * @param xxx $rand
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_promotion_stop_box($userid, $data, $field, $rand) {
    global $CFG, $COURSE, $_SESSION, $id, $act, $grid, $sort, $orderby, $page;

    $levels = array(0,1,2,3,4,5,6,7,8,9,10,12,99);
    $patch = "_stoppr_".$rand."_".$userid;

    $string = '<div id="changepromote'.$patch.'">';
    $string .= '<select id="choose_promote'.$patch.'" name="promote'.$patch.'" onchange="request(\'admin.php?ajax=true&\' + this.options[this.selectedIndex].value,\'changepromote'.$patch.'\'); return false;">';

    foreach ($levels as $levels_) {
        $string .= '<option value="a=admin&id='.$id.'&act='.$act.'&'.$field.'='.$levels_.'&userid='.$userid.'" ';
        if ($levels_ == $data->$field) {
            $string .= ' selected="selected" ';
        }
        $string .= '>'.$levels_.'</option>';
    }

    $string .= '</select></div>';

    return $string;
}

/**
 * reader_goal_box
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $DB
 * @uses $_SESSION
 * @uses $act
 * @uses $grid
 * @uses $id
 * @uses $orderby
 * @uses $page
 * @uses $sort
 * @param xxx $userid
 * @param xxx $dataoflevel
 * @param xxx $field
 * @param xxx $rand
 * @param xxx $reader
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_goal_box($userid, $dataoflevel, $field, $rand, $reader) {
    global $CFG, $COURSE, $_SESSION, $id, $act, $grid, $sort, $orderby, $page,$DB;

    $goal = 0;

    if (! empty($dataoflevel->goal)) {
        $goal = $dataoflevel->goal;
    }

    if (empty($goal)) {
        $data = $DB->get_records('reader_goal', array('readerid' => $reader->id));
        foreach ($data as $data_) {
            if (! empty($data_->groupid)) {
                if (! groups_is_member($data_->groupid, $userid)) {
                    $noneed = true;
                }
            }
            if (! empty($data_->level)) {
                if ($dataoflevel->currentlevel != $data_->level) {
                    $noneed = true;
                }
            }
            if (! $noneed) {
                $goal = $data_->goal;
            }
        }
    }
    if (empty($goal) && !empty($reader->goal)) {
        $goal = $reader->goal;
    }

    if (empty($reader->wordsorpoints) || $reader->wordsorpoints == "words") {
        $levels = array(0,5000,6000,7000,8000,9000,10000,12500,15000,20000,25000,30000,35000,40000,45000,50000,60000,70000,80000,90000,100000,125000,150000,175000,200000,250000,300000,350000,400000,450000,500000);
        if (! in_array($goal, $levels) && !empty($goal)) {
            for ($i=0; $i<count($levels); $i++) {
                if ($goal < $levels[$i+1] && $goal > $levels[$i]) {
                    $levels2[] = $goal;
                    $levels2[] = $levels[$i];
                } else {
                    $levels2[] = $levels[$i];
                }
            }
            $levels = $levels2;
        }
    } else {
        $levels = array(0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15);
    }

    $patch = "_goal_".$rand."_".$userid;

    $string = '<div id="changepromote'.$patch.'">';
    $string .= '<select id="choose_promote'.$patch.'" name="promote'.$patch.'" onchange="request(\'admin.php?ajax=true&\' + this.options[this.selectedIndex].value,\'changepromote'.$patch.'\'); return false;">';

    foreach ($levels as $levels_) {
        $string .= '<option value="a=admin&id='.$id.'&act='.$act.'&set'.$field.'='.$levels_.'&userid='.$userid.'" ';
        if (! empty($dataoflevel->$field)) {
			if ($levels_ == $dataoflevel->$field) {
				$string .= ' selected="selected" ';
			}
        } else {
			if ($levels_ == $goal) {
				$string .= ' selected="selected" ';
			}
        }
        $string .= '>'.$levels_.'</option>';
    }

    $string .= '</select></div>';

    return $string;
}

/**
 * reader_yes_no_box
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $_SESSION
 * @uses $act
 * @uses $grid
 * @uses $id
 * @uses $orderby
 * @uses $page
 * @uses $sort
 * @param xxx $userid
 * @param xxx $data
 * @param xxx $field
 * @param xxx $rand
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_yes_no_box($userid, $data, $field, $rand) {
    global $CFG, $COURSE, $_SESSION, $id, $act, $grid, $sort, $orderby, $page;

    $levels[0] = "Promo";
    $levels[1] = "NoPromo";

    $patch = "_yesno_".$rand."_".$userid;

    $string = '<div id="promoteyesno'.$patch.'">';
    $string .= '<select id="choose_yesnopromote'.$patch.'" name="yesnopromote'.$patch.'" onchange="request(\'admin.php?ajax=true&\' + this.options[this.selectedIndex].value,\'promoteyesno'.$patch.'\'); return false;">';

    foreach ($levels as $key => $levels_) {
        $string .= '<option value="admin.php?a=admin&id='.$id.'&act='.$act.'&'.$field.'='.$key.'&userid='.$userid.'" ';
        if ($key == $data->$field) {
            $string .= ' selected="selected" ';
        }
        $string .= '>'.$levels_.'</option>';
    }

    $string .= '</select></div>';

    return $string;
}

/**
 * reader_selectip_form
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $DB
 * @uses $_SESSION
 * @uses $act
 * @uses $grid
 * @uses $id
 * @uses $orderby
 * @uses $page
 * @uses $sort
 * @param xxx $userid
 * @param xxx $reader
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_selectip_form($userid, $reader) {
    global $CFG, $COURSE, $_SESSION, $id, $act, $grid, $sort, $orderby, $page,$DB;

    $levels = array(0=>"No",1=>"Yes");

    $data = $DB->get_record('reader_strict_users_list', array('readerid' => $reader->id,  'userid' => $userid));

    $patch = $userid."_ip_".$reader->id;

    $string = '<div id="selectip'.$patch.'">';
    $string .= '<select id="choose_ips'.$patch.'" name="ips'.$patch.'" onchange="request(\'admin.php?ajax=true&\' + this.options[this.selectedIndex].value,\'selectip'.$patch.'\'); return false;">';

    foreach ($levels as $key => $value) {
        $string .= '<option value="admin.php?a=admin&id='.$id.'&act='.$act.'&setip=1&userid='.$userid.'&needip='.$key.'" ';
        if ($key == $data->needtocheckip) {
            $string .= ' selected="selected" ';
        }
        $string .= '>'.$value.'</option>';
    }

    $string .= '</select></div>';

    return $string;
}

/**
 * reader_select_difficulty_form
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $DB
 * @uses $_SESSION
 * @uses $act
 * @uses $grid
 * @uses $id
 * @uses $orderby
 * @uses $page
 * @uses $sort
 * @param xxx $difficulty
 * @param xxx $bookid
 * @param xxx $reader
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_select_difficulty_form($difficulty, $bookid, $reader) {
    global $CFG, $COURSE, $_SESSION, $id, $act, $grid, $sort, $orderby, $page,$DB;

    $levels = array(0,1,2,3,4,5,6,7,8,9,10,12,13,14);

    $patch = $bookid."_".$difficulty;

    $string = '<div id="difficulty_'.$patch.'">';

    $string .= '<select id="choose_difficulty_'.$patch.'" name="difficulty_'.$patch.'" onchange="request(\'admin.php?ajax=true&\' + this.options[this.selectedIndex].value,\'difficulty_'.$patch.'\'); return false;">';

    foreach ($levels as $levels_) {
        $string .= '<option value="admin.php?a=admin&id='.$id.'&act='.$act.'&difficulty='.$levels_.'&bookid='.$bookid.'&slevel='.$difficulty.'" ';
        if ($levels_ == $difficulty) {
            $string .= ' selected="selected" ';
        }
        $string .= '>'.$levels_.'</option>';
    }

    $string .= '</select></div>';

    return $string;
}

/**
 * reader_select_length_form
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $_SESSION
 * @uses $act
 * @uses $grid
 * @uses $id
 * @uses $orderby
 * @uses $page
 * @uses $sort
 * @param xxx $length
 * @param xxx $bookid
 * @param xxx $reader
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_select_length_form($length, $bookid, $reader) {
    global $CFG, $COURSE, $_SESSION, $id, $act, $grid, $sort, $orderby, $page;

    //$levels = array(0.50,0.60,0.70,0.80,0.90,1.00,1.10,1.20,1.30,1.40,1.50,1.60,1.70,1.80,1.90,2.00);
    $levels = array(0.50,0.60,0.70,0.80,0.90,1.00,1.10,1.20,1.30,1.40,1.50,1.60,1.70,1.80,1.90,2.00,3.00,4.00,5.00,6.00,7.00,8.00,9.00,10.00,15,20,25,30,35,40,45,50,55,60,65,70,75,80,85,90,95,100,110,120,130,140,150,160,170,175,180,190,200,225,250,275,300,350,400);

    $patch = $bookid."_".$length;

    $string = '<div id="length_'.$patch.'">';

    $string .= '<select id="choose_length_'.$patch.'" name="length_'.$patch.'" onchange="request(\'admin.php?ajax=true&\' + this.options[this.selectedIndex].value,\'length_'.$patch.'\'); return false;">';

    foreach ($levels as $levels_) {
        $string .= '<option value="admin.php?a=admin&id='.$id.'&act='.$act.'&length='.$levels_.'&bookid='.$bookid.'&slevel='.$length.'" ';
        if ($levels_ == $length) {
            $string .= ' selected="selected" ';
        }
        $string .= '>'.$levels_.'</option>';
    }

    $string .= '</select></div>';

    return $string;
}

/**
 * reader_set_attempt_result
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $DB
 * @uses $USER
 * @param xxx $attemptid
 * @param xxx $reader
 * @todo Finish documenting this function
 */
function reader_set_attempt_result($attemptid, $reader) {
    global $CFG, $COURSE, $USER,$DB;

    $attemptdata = $DB->get_record('reader_attempts', array('id' => $attemptid));

    //if (! $attemptdata->percentgrade && $attemptdata->percentgrade != 0) {
    if (! $attemptdata->percentgrade) {
        $bookdata = $DB->get_record('reader_books', array('quizid' => $attemptdata->quizid));
        if (empty($bookdata)) {
            $bookdata = (object)array('id' => $attemptdata->quizid);
        }
        $totalgrade = 0;
        $answersgrade = $DB->get_records('reader_question_instances', array('quiz' => $attemptdata->quizid)); // Count Grades (TotalGrade)
        if (empty($answersgrade)) {
            $answersgrade = array();
        }
        foreach ($answersgrade as $answersgrade_) {
            $totalgrade += $answersgrade_->grade;
        }

        if (empty($attemptdata->sumgrades) || empty($totalgrade)) {
            $percentgrade = 0;
        } else {
            $percentgrade = round(($attemptdata->sumgrades/$totalgrade) * 100, 0);
        }

        if ($percentgrade >= $reader->percentforreading) {
            $passed = "true";
            $passedlog = "Passed";
        } else {
            $passed = "false";
            $passedlog = "Failed";
        }

        if (! $DB->get_record('log', array('userid' => $USER->id,  'course' => $COURSE->id,  'info' => "readerID {$reader->id}; reader quiz {$bookdata->id}; {$percentgrade}/{$passedlog}"))) {
            $logaction = 'view attempt: '.substr($bookdata->name, 0, 26); // 40 char limit
            $loginfo   = "readerID {$reader->id}; ".
                         "reader quiz {$bookdata->id}; ".
                         "{$percentgrade}/{$passedlog}";
            add_to_log($COURSE->id, 'reader', $logaction, "view.php?id={$attemptid}", $loginfo);
        }

        $DB->set_field('reader_attempts',  "percentgrade",  $percentgrade, array('id' => $attemptid));
        $DB->set_field('reader_attempts',  "passed",  $passed, array('id' => $attemptid));
    }
}

/**
 * reader_makexml
 *
 * @param xxx $xmlarray
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_makexml($xmlarray) {
    $xml = "";
    foreach ($xmlarray as $xmlarray_) {
        $xml .= $xmlarray_;
    }
    return $xml;
}

/**
 * reader_file
 *
 * @param xxx $url
 * @param xxx $post (optional, default=false)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_file($url, $post = false) {
    $postdata = "";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if ($post) {
        curl_setopt($ch, CURLOPT_POST, 1);

        foreach ($post as $key => $value) {
          if (! is_array($value)) {
              $postdata .= $key.'='.$value.'&';
          } else {
            foreach ($value as $key2 => $value2) {
                if (! is_array($value2)) {
                    $postdata .= $key.'['.$key2.']='.$value2.'&';
                } else {
                    foreach ($value2 as $key3 => $value3) {
                        $postdata .= $key.'['.$key2.']['.$key3.']='.$value3.'&';
                    }
                }
            }
          }
        }
        //echo $postdata;
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
    }
    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

/**
 * reader_remove_directory
 *
 * @param xxx $dir
 * @todo Finish documenting this function
 */
function reader_remove_directory($dir) {
    if ($objs = glob($dir."/*")) {
        foreach($objs as $obj) {
            is_dir($obj) ? reader_remove_directory($obj) : unlink($obj);
        }
    }
    rmdir($dir);
}

/**
 * reader_curlfile
 *
 * @param xxx $url
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_curlfile($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //curl_setopt($ch, CURLOPT_REFERER, trackback_url(false));
    $result = curl_exec($ch);
    curl_close($ch);

    if (! empty($result)) {
        return explode('\n', $result);
    } else {
        return false;
    }
}

/**
 * reader_debug_speed_check
 *
 * @uses $CFG
 * @uses $USER
 * @uses $dbstimebegin
 * @uses $dbstimelast
 * @uses $debugandspeedforadminreport
 * @param xxx $name
 * @todo Finish documenting this function
 */
function reader_debug_speed_check($name) {
    global $CFG, $USER, $debugandspeedforadminreport, $dbstimebegin, $dbstimelast;

    if(! $dbstimebegin) {
        list($msec,$sec)=explode(chr(32),microtime());
        $dbstimebegin = $sec+$msec;
    } else {
        list($msec,$sec)=explode(chr(32),microtime());
        $dbstimenow = $sec+$msec;
        $debugandspeedforadminreport .= $name . " " . round($dbstimenow - $dbstimebegin,4) . "sec ";
        if ($dbstimelast) {
            $debugandspeedforadminreport .= " (".round($dbstimenow - $dbstimelast,4).") <br />";
        } else {
            $debugandspeedforadminreport .= "<br />";
        }
        $dbstimelast = $dbstimenow;
    }
}

/**
 * reader_order_object
 *
 * @param xxx $array
 * @param xxx $key
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_order_object($array, $key) {
    $tmp = array();
    foreach($array as $akey => $array2) {
        $tmp[$akey] = $array2->$key;
    }
    sort($tmp, SORT_NUMERIC);
    $tmp2 = array();
    $tmp_size = count($tmp);
    foreach($tmp as $key => $value) {
        $tmp2[$key] = $array[$key];
    }
    return $tmp2;
}

/**
 * reader_get_goal_progress
 *
 * @uses $CFG
 * @uses $DB
 * @uses $USER
 * @param xxx $progress
 * @param xxx $reader
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_goal_progress($progress, $reader) {
    global $CFG, $USER,$DB;

    if (! $progress) {
        $progress = 0;
    }

    if ($dataofuserlevels = $DB->get_record('reader_levels', array('userid' => $USER->id,  'readerid' => $reader->id))) {
        if (! empty($dataofuserlevels->goal)) {
            $goal = $dataofuserlevels->goal;
        }
    }
    if (empty($goal)) {
        $data = $DB->get_records('reader_goal', array('readerid' => $reader->id));
        foreach ($data as $data_) {
            if (! empty($data_->groupid)) {
                if (! groups_is_member($data_->groupid, $USER->id)) {
                    $noneed = true;
                }
            }
            if (! empty($data_->level)) {
                if ($dataofuserlevels->currentlevel != $data_->level) {
                    $noneed = true;
                }
            }
            if (! $noneed) {
                $goal = $data_->goal;
            }
        }
    }
    if (empty($goal) || !empty($reader->goal)) {
        $goal = $reader->goal;
    }

        $goalchecker = $goal;
        if ($progress > $goal) {
            $goalchecker = $progress;
        }
        if ($goalchecker <= 50000) {
            $img = 5;
            $bgcolor = "#00FFFF";
        }
        else if ($goalchecker <= 100000) {
            $img = 10;
            $bgcolor = "#FF00FF";
        }
        else if ($goalchecker <= 500000) {
            $img = 50;
            $bgcolor = "#FFFF00";
        }
        else {
            $img = 100;
            $bgcolor = "#0000FF";
        }
        if ($goal > 1000000) {
            $goal = 1000000;
        }
        if ($progress > 1000000) {
            $progress = 1000000;
        }
        $currentpositiongoal = $goal / ($img * 10000);
        $currentpositiongoalpix = round($currentpositiongoal * 800);
        if ($currentpositiongoalpix > 800) {
            $currentpositiongoalpix = 800;
        }

        $currentposition = $progress / ($img * 10000);
        $currentpositionpix = round($currentposition * 800);
        if ($currentpositionpix > 800) {
            $currentpositionpix = 800;
        }
        $currentpositionpix += 8;

        $returntext = '<style  type="text/css" >
<!--
#ScoreBoxDiv
{
position:absolute;
left:5px; top:34px;
width:824px;
height:63px;
background-color: '.$bgcolor.' ;
z-index:5;
}
img.color
{
position:absolute;
top:40px;
left:10px;
z-index:20;
clip: rect(0px '.$currentpositionpix.'px 100px 0px);
}
img.mark
{
position:absolute;
top:47px;
left:'.($currentpositionpix+10).'px;
z-index:20;
}
img.grey
{
position:absolute;
top:40px;
left:10px;
z-index:15;
}
img.goal
{
position:absolute;
top:26px;
left:'.$currentpositiongoalpix.'px;
z-index:40;
}
-->

</style>
<div id="ScoreBoxDiv" class="ScoreBoxDiv"> &nbsp;&nbsp;&nbsp;&nbsp;</div>
<img class="color" src="'.$CFG->wwwroot.'/mod/reader/img/colorscale800px'.$img.'.png">
<img class="grey" src="'.$CFG->wwwroot.'/mod/reader/img/colorscale800px'.$img.'gs.png">
<img class="mark" src="'.$CFG->wwwroot.'/mod/reader/img/now.png">
';

        if (! empty($goal)) {
            $returntext .= '<img class="goal" src="'.$CFG->wwwroot.'/mod/reader/img/goal.png">';
        }

        return $returntext;
    //}
}

/**
 * reader_get_reader_difficulty
 *
 * @uses $DB
 * @param xxx $reader
 * @param xxx $bookid
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_reader_difficulty($reader, $bookid) {
    global $DB;
    if ($reader->bookinstances == 1) {
        if (! $data = $DB->get_record('reader_book_instances', array('readerid' => $reader->id,  'bookid' => $bookid))) {
            if (empty($data)) {
                return 0;
            }
            $data = $DB->get_record('reader_books', array('id' => $bookid));
            return $data->difficulty;
        } else {
            return $data->difficulty;
        }
    } else {
        $data = $DB->get_record('reader_books', array('id' => $bookid));
        if (empty($data)) {
            return 0;
        }
        return $data->difficulty;
    }
}

/**
 * reader_get_reader_length
 *
 * @uses $DB
 * @param xxx $reader
 * @param xxx $bookid
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_reader_length($reader, $bookid) {
    global $DB;
    if ($reader->bookinstances == 1) {
        $data = $DB->get_record('reader_book_instances', array('readerid' => $reader->id,  'bookid' => $bookid));
        if (! empty($data->length)) {
            return $data->length;
        } else {
            $data = $DB->get_record('reader_books', array('id' => $bookid));
            if (empty($data)) {
                return 0;
            }
            return $data->length;
        }
    } else {
        $data = $DB->get_record('reader_books', array('id' => $bookid));
        if (empty($data)) {
            return 0;
        }
        return $data->length;
    }
}

/**
 * reader_ra_checkbox
 *
 * @uses $CFG
 * @uses $USER
 * @uses $act
 * @uses $excel
 * @uses $id
 * @param xxx $data
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_ra_checkbox($data) {
    global $act, $id, $CFG, $USER, $excel;
    $checked = '';

    if ($excel) {
      if ($data['checkbox'] == 1) {
        return 'yes';
      } else {
        return 'no';
      }
    }

    if ($data['checkbox'] == 1) {
        $checked = 'checked';
    }
    return '<input type="checkbox" name="checkattempt" value="1" '.$checked.' onclick="if(this.checked) { request(\'admin.php?ajax=true&id='.$id.'&act='.$act.'&checkattempt='.$data['id'].'&checkattemptvalue=1\',\'atcheck_'.$data['id'].'\'); } else { request(\'admin.php?ajax=true&id='.$id.'&act='.$act.'&checkattempt='.$data['id'].'&checkattemptvalue=0\',\'atcheck_'.$data['id'].'\'); }" ><div id="atcheck_'.$data['id'].'"></div>';
}

/**
 * reader_groups_get_user_groups
 *
 * @uses $CFG
 * @uses $DB
 * @uses $USER
 * @param xxx $userid (optional, default=0)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_groups_get_user_groups($userid=0) {
    global $CFG, $USER, $DB;

    if (empty($userid)) {
        $userid = $USER->id;
    }

    $select = 'g.id, gg.groupingid';
    $from =   '{groups} g '.
              'JOIN {groups_members} gm ON gm.groupid = g.id '.
              'LEFT JOIN {groupings_groups} gg ON gg.groupid = g.id';
    $where  = 'gm.userid = ?';
    $params = array($userid);

    if (! $rs = $DB->get_recordset_sql("SELECT $select FROM $from WHERE $where", $params)) {
        return array('0' => array());
    }

    $result    = array();
    $allgroups = array();

    foreach ($rs as $group) {
        $allgroups[$group->id] = $group->id;
        if (is_null($group->groupingid)) {
            continue;
        }
        if (! array_key_exists($group->groupingid, $result)) {
            $result[$group->groupingid] = array();
        }
        $result[$group->groupingid][$group->id] = $group->id;
    }
    $rs->close();

    $result['0'] = array_keys($allgroups); // all groups

    return $result;
}

/**
 * reader_nicetime
 *
 * @param xxx $unix_date
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_nicetime($unix_date) {
    if(empty($unix_date)) {
        return "No date provided";
    }

    $periods         = array("second", "minute", "hour", "day", "week", "month", "year", "decade");
    $lengths         = array("60","60","24","7","4.35","12","10");

    $now             = time();

    if($now > $unix_date) {
        $difference     = $now - $unix_date;
        $tense         = "";

    } else {
        $difference     = $unix_date - $now;
        $tense         = "";
    }

    for($j = 0; $difference >= $lengths[$j] && $j < count($lengths)-1; $j++) {
        $difference /= $lengths[$j];
    }

    $difference = round($difference);

    if($difference != 1) {
        $periods[$j].= "s";
    }

    $textr = "$difference $periods[$j] {$tense} ";

    if ($j == 3) {
        $unix_date = $unix_date - $difference * 24 * 60 * 60;
        if($now > $unix_date) {
            $difference     = $now - $unix_date;
            $tense         = "";

        } else {
            $difference     = $unix_date - $now;
            $tense         = "";
        }

        for($j = 0; $difference >= $lengths[$j] && $j < count($lengths)-1; $j++) {
            $difference /= $lengths[$j];
        }

        $difference = round($difference);

        if($difference != 1) {
            $periods[$j].= "s";
        }

        $textr .= " $difference $periods[$j] {$tense}";
    }

    return $textr;
}

/**
 * reader_nicetime2
 *
 * @param xxx $session_time
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_nicetime2($session_time) {
    $time_difference = $session_time ;

    //echo $time_difference."!!";

    $seconds = $time_difference ;
    $minutes = round($time_difference / 60);
    $hours   = round($time_difference / 3600);
    $days    = round($time_difference / 86400);
    $weeks   = round($time_difference / 604800);
    $months  = round($time_difference / 2419200);
    $years   = round($time_difference / 29030400);

    if ($seconds <= 60) { // Seconds
        $text .= "$seconds seconds ";
    } else if ($minutes <= 60) { //Minutes
        if ($minutes==1) {
            $text .= "one minute ";
        } else {
            $text .= "$minutes minutes ";
        }
    } else if($hours <=24) {//Hours
        if($hours==1) {
            $text .= "one hour ";
        } else {
            $text .= "$hours hours ";
        }
    } else if($days <= 7) { //Days
        if($days==1) {
            $text .= "one day ";
        } else {
            $text .= "$days days ";
        }
    } else if($weeks <= 4) { //Weeks
        if($weeks==1) {
            $text .= "one week ";
        } else {
            $text .= "$weeks weeks ";
        }
    } else if($months <=12) { //Months
        if($months==1) {
            $text .= "one month ";
        } else {
            $text .= "$months months ";
        }
    } else { //Years
        if($years==1) {
            $text .= "one year ago";
        } else {
            $text .= "$years years ";
        }
    }

    return $text;
}

/**
 * reader_forcedtimedelay_check
 *
 * @uses $DB
 * @uses $USER
 * @uses $course
 * @param xxx $cleartime
 * @param xxx $reader
 * @param xxx $studentlevel
 * @param xxx $lasttime
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_forcedtimedelay_check($cleartime, $reader, $studentlevel, $lasttime) {
    global $USER, $course,$DB;

    $data = $DB->get_record('reader_forcedtimedelay', array('readerid' => $reader->id,  'level' => 99,  'groupid' => 0));

    if ($data2 = $DB->get_record('reader_forcedtimedelay', array('readerid' => $reader->id,  'level' => $studentlevel,  'groupid' => 0))) {
        $data = $data2;
    }

    if ($usergroups = groups_get_all_groups($course->id, $USER->id)){
        foreach ($usergroups as $group){
            $data = $DB->get_record('reader_forcedtimedelay', array('readerid' => $reader->id,  'level' => $studentlevel,  'groupid' => $group->id));
        }
    }

    //echo $data->delay + $lasttime."??";

    if ($data->delay) {
        return $data->delay + $lasttime;
    } else {
        return $cleartime;
    }
}

/**
 * reader_put_to_quiz_attempt
 *
 * @uses $DB
 * @param xxx $attemptid
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_put_to_quiz_attempt($attemptid) {
  global $DB;

  if ($data = $DB->get_record('reader_attempts', array('id' => $attemptid))) {
    if ($datapub = $DB->get_record('reader_books', array('quizid' => $data->quizid))) {
      //$lastattemptid = $DB->get_field_sql('SELECT uniqueid FROM {quiz_attempts} ORDER BY uniqueid DESC LIMIT 1');
      //$lastattemptid + 1;

      $add = array();
      $add['uniqueid']             = $data->uniqueid;
      $add['quiz']                 = $datapub->quizid;
      $add['userid']               = $data->userid;
      $add['attempt']              = $data->attempt;
      $add['sumgrades']            = $data->sumgrades;
      $add['timestart']            = $data->timestart;
      $add['timefinish']           = $data->timefinish;
      $add['timemodified']         = $data->timemodified;
      $add['layout']               = $data->layout;
      $add['preview']              = 0;
      $add['needsupgradetonewqe']  = 0;

      $DB->delete_records('quiz_attempts', array('uniqueid' => $data->uniqueid));

      $id = $DB->insert_record('quiz_attempts', $add);
    } else
      return false;
  } else
    return false;

}

/**
 * context
 *
 * a wrapper method to offer consistent API to get contexts
 * in Moodle 2.0 and 2.1, we use reader_get_context() function
 * in Moodle >= 2.2, we use static context_xxx::instance() method
 *
 * @param integer $contextlevel
 * @param integer $instanceid (optional, default=0)
 * @param int $strictness (optional, default=0 i.e. IGNORE_MISSING)
 * @return required context
 * @todo Finish documenting this function
 */
function reader_get_context($contextlevel, $instanceid=0, $strictness=0) {
    if (class_exists('context_helper')) {
        // use call_user_func() to prevent syntax error in PHP 5.2.x
        // return $classname::instance($instanceid, $strictness);
        $class = context_helper::get_class_for_level($contextlevel);
        return call_user_func(array($class, 'instance'), $instanceid, $strictness);
    } else {
        return reader_get_context($contextlevel, $instanceid);
    }
}

/**
 * textlib
 *
 * a wrapper method to offer consistent API for textlib class
 * in Moodle 2.0 and 2.1, $textlib is first initiated, then called.
 * in Moodle >= 2.2, we use only static methods of the "textlib" class.
 *
 * @param string $method
 * @param mixed any extra params that are required by the textlib $method
 * @return result from the textlib $method
 * @todo Finish documenting this function
 */
function reader_textlib() {
    if (method_exists('textlib', 'textlib')) {
        $textlib = textlib_get_instance();
    } else {
        $textlib = 'textlib'; // Moodle >= 2.2
    }
    $args = func_get_args();
    $method = array_shift($args);
    $callback = array($textlib, $method);
    return call_user_func_array($callback, $args);
}

/**
 * reader_get_numsections
 *
 * a wrapper method to offer consistent API for $course->numsections
 * in Moodle 2.0 - 2.3, "numsections" is a field in the "course" table
 * in Moodle >= 2.4, "numsections" is in the "course_format_options" table
 *
 * @uses $DB
 * @param object $course
 * @return integer $numsections
 */
function reader_get_numsections($course) {
    global $DB;
    if (empty($course) || empty($course->id)) {
        return 0;
    }
    if (isset($course->numsections)) {
        return $course->numsections; // Moodle >= 2.3
    } else {
        return $DB->get_field('course_format_options', 'value', array('courseid' => $id, 'format' => $course->format, 'name' => 'numsections'));
    }
}

/**
 * reader_set_numsections
 *
 * a wrapper method to offer consistent API for $course->numsections
 * in Moodle 2.0 - 2.3, "numsections" is a field in the "course" table
 * in Moodle >= 2.4, "numsections" is in the "course_format_options" table
 *
 * ================================================================
 * NOTE: maybe we should check function_exists('course_get_format')
 * in Moodle 2.4, and if it exists, use that to set "numsections"
 * ================================================================
 *
 * @uses $DB
 * @param object $course
 * @param integer $numsections
 * @return void, but may update "course" or "course_format_options" table
 */
function reader_set_numsections($course, $numsections) {
    global $DB;
    if (empty($course) || empty($course->id)) {
        return false;
    }
    if (isset($course->numsections)) {
        return $DB->set_field('course', 'numsections', $numsections, array('id' => $course->id));
    } else {
        return $DB->set_field('course_format_options', 'value', $numsections, array('courseid' => $course->id, 'format' => $course->format));
    }
}

/**
 * reader_optional_param_array
 *
 * a wrapper method to offer consistent API for getting array parameters
 *
 * @param string $name the name of the parameter
 * @param mixed $default
 * @param mixed $type one of the PARAM_xxx constants
 * @return either an array of form values or the $default value
 */
function reader_optional_param_array($name, $default, $type) {
    $optional_param_array   = 'optional_param';
    if (function_exists('optional_param_array')) {
        switch (true) {
            case (isset($_POST[$name]) && is_array($_POST[$name])): $optional_param_array = 'optional_param_array'; break;
            case (isset($_GET[$name])  && is_array($_GET[$name])) : $optional_param_array = 'optional_param_array'; break;
        }
    }
    return $optional_param_array($name, $default, $type);
}
