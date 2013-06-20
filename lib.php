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
        'cheated_message'    => 'We are sorry to say that the MoodleReader program has discovered '.
                                'that you have probably cheated when you took the above quiz. '.
                                "'Cheating' means that you either helped another person to take the quiz ".
                                'or that you received help from someone else to take the quiz. '.
                                "Both people have been marked 'cheated'\n\n".
                                'Sometimes the computer makes mistakes. '.
                                'If you honestly did not receive help and did not help someone else, '.
                                'then please inform your teacher and your points will be restored.'."\n\n".
                                '--The MoodleReader Module Manager',
        'not_cheated_message' => 'We are happy to inform you that your points for the above quiz have been restored. '.
                                 'We apologize for the mistake!'."\n\n".
                                 '--The MoodleReader Module Manager',
        'serverlink'          => 'http://moodlereader.net/quizbank',
        'serverlogin'         => '',
        'serverpassword'      => ''
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

    global $CFG, $DB, $USER;
    $reader->timemodified = time();

    $reader->id = $DB->insert_record('reader', $reader);
    //print_r ($reader);
    //die;

    //No promotion after level
    if (isset($reader->promotionstop)) {
        $allstudents = $DB->get_records('reader_levels', array('readerid' => $reader->id));
        foreach ($allstudents as $allstudents_) {
            $DB->set_field('reader_levels', 'promotionstop', $reader->promotionstop, array('id' => $allstudents_->id));
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
            $DB->set_field('reader_levels', 'promotionstop', $reader->promotionstop, array('id' => $allstudents_->id));
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
    global $CFG, $DB;

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

    return false; // True if anything was printed, otherwise false
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
    global $CFG, $DB;

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
        $answersgrade = $DB->get_records('reader_question_instances', array('quiz' => $publishersquizze->quizid));
        $doublecheck = array();
        foreach ($answersgrade as $answersgrade_) {
            if (! in_array($answersgrade_->question, $questions)) {
                $DB->delete_records('reader_question_instances', array('quiz' => $publishersquizze->quizid, 'question' => $answersgrade_->question));
                $editedquizzes[$publishersquizze->id] = $publishersquizze->quizid;
            }
            if (! in_array($answersgrade_->question, $doublecheck)) {
                $doublecheck[] = $answersgrade_->question;
            } else {
                add_to_log(1, 'reader', 'Cron', '', "Double entries found!! reader_question_instances; quiz: {$publishersquizze->quizid}; question: {$answersgrade_->question}");
            }
        }
    }

    $publishersquizzes = $DB->get_records('reader_books');

    foreach ($publishersquizzes as $publishersquizze) {
        if (strstr($publishersquizze->name, "\'")) {
            $DB->set_field('reader_books', 'name', stripslashes($publishersquizze->name), array('id' => $publishersquizze->id));
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
 * reader_get_level_data
 *
 * @uses $CFG
 * @uses $DB
 * @uses $USER
 * @param xxx $reader
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_level_data($reader, $userid=0) {
    global $CFG, $DB, $USER;

    // initialize count of quizzes taken at "prev", "this" and "next" levels
    //     Note that for "prev" and "next" we count ANY attempt
    //     but for "this" level, we only count PASSED attempts
    $count = array('prev' => 0, 'this' => 0, 'next' => 0);

    if ($userid==0) {
        $userid = $USER->id;
    }

    if (! $level = $DB->get_record('reader_levels', array('userid' => $userid, 'readerid' => $reader->id))) {
        $level = (object)array(
            'userid'        => $userid,
            'readerid'      => $reader->id,
            'startlevel'    => 0,
            'currentlevel'  => 0,
            'nopromote'     => 0,
            'promotionstop' => $reader->promotionstop,
            'goal'          => 0,
            'time'          => time(),
        );
        if (! $level->id = $DB->insert_record('reader_levels', $level)) {
            // oops record could not be added - shouldn't happen !!
        }
    }

    $select = 'ra.*, rb.difficulty, rb.id AS bookid';
    $from   = '{reader_attempts} ra INNER JOIN {reader_books} rb ON rb.quizid = ra.quizid';
    $where  = 'ra.userid= ? AND ra.reader= ? AND ra.timefinish > ?';
    $params = array($USER->id, $reader->id, $reader->ignoredate);

    if ($attempts = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY ra.timemodified", $params)) {
        foreach ($attempts as $attempt) {

            $difficulty = reader_get_reader_difficulty($reader, $attempt->bookid, $attempt->difficulty);
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
        $DB->set_field('reader_levels', 'nopromote', 1, array('readerid' => $reader->id, 'userid' => $USER->id));
        $level->nopromote = 1;
    }

    if ($level->nopromote==1) {
        $count['this'] = 1;
    }

    // promote this student, if they have done enough quizzes at this level
    if ($count['this'] >= $reader->nextlevel) {
        $level->currentlevel += 1;
        $level->time = time();
        $DB->update_record('reader_levels', $level);

        $count['this'] = 0;
        $count['prev'] = 0;
        $count['next'] = 0;

        echo '<script type="text/javascript">'."\n";
        echo '//<![CDATA['."\n";
        echo 'alert("Congratulations!! You have been promoted to Level '.$level->currentlevel.'!");'."\n";
        echo '//]]>'."\n";
        echo '</script>';
    }

    // prepare level data
    $leveldata = array(
        'promotiondate' => $level->time,
        'currentlevel'  => $level->currentlevel,                        // current level of this user
        'onprevlevel'   => $reader->quizpreviouslevel - $count['prev'], // number of quizzes allowed at previous level
        'onthislevel'   => $reader->nextlevel         - $count['this'], // number of quizzes allowed at current level
        'onnextlevel'   => $reader->quiznextlevel     - $count['next']  // number of quizzes allowed at next level
    );
    if ($level->currentlevel==0 || $count['prev'] == -1) {
        $leveldata['onprevlevel'] = -1;
    }

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
    global $CFG, $DB, $USER;

    $book = $DB->get_record('reader_books', array('id' => $bookid));

    if (empty($book) || empty($book->quizid)) {
        die('Oops, no $book or $book->quizid');
        return false; // invalid $bookid or $book->quizid
    }

    $params = array('reader' => $reader->id, 'userid' => $USER->id, 'attempt' => ($attemptnumber - 1));
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
            'layout'  => reader_repaginate($reader->questions, $reader->questionsperpage)
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

    if (count($questionids)) {
        // get ids of question instances that already exist
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
    }

    // any remaining $questionids do not already have a
    // "reader_question_instances" record, so we create one
    foreach ($questionids as $questionid) {
        if (empty($book->quizid)) {
            $grade = $DB->get_field('question', 'defaultgrade', array('id' => $questionid));
        } else {
            $params = array('quiz' => $book->quizid, 'question' => $questionid);
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
    global $DB, $USER;

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
    if ($grade = $DB->get_record('reader_grades', array('reader' => $reader->id, 'userid' => $userid))) {
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
    global $CFG, $DB;
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
        $select = 'a.*, cm.idnumber as cmidnumber, a.course as courseid';
        $from   = '{reader} a, {course_modules} cm, {modules} m';
        $where  = 'm.name = ? AND m.id = cm.module AND cm.instance = a.id';
        $params = array('reader');
        if ($rs = $DB->get_recordset_sql("SELECT $select FROM $from WHERE $where", $params)) {
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
    global $DB;
    $select = 'u.id, u.id AS userid, '.
              'rg.grade AS rawgrade, rg.timemodified AS dategraded, '.
              'MAX(ra.timefinish) AS datesubmitted';
    $from   = '{user} u, {reader_grades} rg, {reader_attempts} ra';
    $where  = 'u.id = rg.userid AND rg.reader = ? AND ra.reader = rg.reader AND u.id = ra.userid';
    $groupby = 'u.id, rg.grade, rg.timemodified';
    $params = array($reader->id);
    if ($userid) {
        $select .= ' AND u.id = ?';
        $params[] = $userid;
    }
    return $DB->get_records_sql("SELECT $select FROM $from WHERE $where GROUP BY $groupby", $params);
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
    $text = html_writer::tag('span', get_string('page').':', array('class' => 'title'));
    $text .= ($page + 1).' ('.$pages.')';
    return html_writer::tag('div', $text, array('class' => 'pagingbar'));
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
    global $CFG, $DB;
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
 * @param xxx $orderby "ASC" or "DESC"
 * @param xxx $sort name of a table column
 * @param xxx $link
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_make_table_headers(&$table, $headers, $orderby, $sort, $params) {
    global $CFG;

    if ($orderby == 'ASC') {
        $direction = 'DESC';
        $directionimg = 'down';
    } else {
        $direction = 'ASC';
        $directionimg = 'up';
    }

    $table->head = array();
    foreach ($headers as $text => $columnname) {
        $header = $text;

        if ($columnname) {

            // append sort icon
            if ($sort == $columnname) {
                $imgparams = array('theme' => $CFG->theme, 'image' => "t/$directionimg", 'rev' => $CFG->themerev);
                $header .= ' '.html_writer::empty_tag('img', array('src' => new moodle_url('/theme/image.php', $imgparams), 'alt' => ''));
            }

            // convert $header to link
            $params['sort'] = $columnname;
            $params['orderby'] = $direction;
            $header = html_writer::tag('a', $header, array('href' => new moodle_url('/mod/reader/admin.php', $params)));
        }

        // add header to table
        $table->head[] = $header;
    }
}

/**
 * reader_sort_table_data
 *
 * @param xxx $table
 * @param xxx $columns
 * @param xxx $sortdirection
 * @param xxx $sortcolumn
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_sort_table(&$table, $columns, $sortdirection, $sortcolumn) {

    if (empty($table->data)) {
        return; // nothing to do
    }

    $sortindex = 0; // default is first column
    if ($sortcolumn) {
        $i = 0;
        foreach ($columns as $text => $columnname) {
            if ($columnname == $sortcolumn) {
                $sortindex = $i;
            }
            $i++;
        }
    }

    $values = array();
    foreach ($table->data as $r => $row) {
        $values[$r] = strip_tags($row->cells[$sortindex]->text);
    }

    if (empty($sortdirection) || $sortdirection=='ASC') {
        asort($values);
    } else {
        arsort($values);
    }

    $data = array();
    foreach (array_keys($values) as $r) {
        $data[] = $table->data[$r];
    }
    $table->data = $data;
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

    $params = array('id' => $question->id);
    if (isset($quiz->id) && $quiz->id) {
        $params['quizid'] = $quiz->id;
    } else {
        $params['courseid'] = $COURSE->id;
    }
    $link = new moodle_url('/question/preview.php', $params);

    $params = array('theme' => $CFG->theme, 'image' => 'preview', 'rev' => $CFG->themerev);
    $src = new moodle_url('/theme/image.php', $params);

    $strpreview = get_string('previewquestion', 'quiz');
    $img = html_writer::empty_tag('img', array('src' => $src, 'class' => 'iconsmall', 'alt' => $strpreview));

    return link_to_popup_window($link, 'questionpreview', $img, 0, 0, $strpreview, QUESTION_PREVIEW_POPUP_OPTIONS, true);
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

    $select = 'ra.id,ra.timefinish,ra.userid,ra.attempt,ra.percentgrade,ra.quizid,ra.sumgrades,ra.passed,ra.checkbox,ra.preview,'.
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

    $select = 'ra.id,ra.timefinish,ra.userid,ra.attempt,ra.percentgrade,ra.quizid,ra.sumgrades,ra.passed,ra.checkbox,ra.preview,'.
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

    $level = $DB->get_record('reader_levels', array('userid' => $userid, 'readerid' => $reader->id));
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
                $statustext = html_writer::tag('span', 'Cheated', array('style' => 'color:red'));
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
 * @uses $gid
 * @param xxx $courseid
 * @param xxx $link
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_print_group_select_box($courseid, $link) {
    global $CFG, $COURSE, $gid;

    $groups = groups_get_all_groups ($courseid);

    if ($groups) {
        echo '<table style="width:100%"><tr><td align="right">';
        echo '<form action="" method="post" id="mform_gr">';
        echo '<select name="gid" id="id_gid">';
        echo '<option value="0">'.get_string('allgroups', 'reader').'</option>';
        foreach ($groups as $groupid => $group) {
            if ($groupid == $gid) {
                $selected = ' selected="selected"';
            } else {
                $selected = '';
            }
            echo '<option value="'.$groupid.'"'.$selected.'>'.$group->name.'</option>';
        }
        echo '</select>';
        echo '<input type="submit" id="form_gr_submit" value="'.get_string('go').'" />';
        echo '</form>';
        echo '</td></tr></table>'."\n";

        // javascript to submit group form automatically and hide "Go" button
        echo '<script type="text/javascript">'."\n";
        echo "//<![CDATA[\n";
        echo "var obj = document.getElementById('id_gid');\n";
        echo "if (obj) {\n";
        echo "    obj.onchange = new Function('this.form.submit(); return true;');\n";
        echo "}\n";
        echo "var obj = document.getElementById('form_gr_submit');\n";
        echo "if (obj) {\n";
        echo "    obj.style.display = 'none';\n";
        echo "}\n";
        echo "obj = null;\n";
        echo "//]]>\n";
        echo "</script>\n";
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
 * reader_username_link
 *
 * @uses $CFG
 * @uses $COURSE
 * @param xxx $userdata
 * @param xxx $courseid
 * @param xxx $nolink (optional, default = false)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_username_link($userdata, $courseid, $nolink=false) {
    $username = $userdata->username;
    if ($nolink) {
        return $username; // e.g. for excel
    }
    if (isset($userdata->userid)) {
        $userid = $userdata->userid;
    } else {
        $userid = $userdata->id;
    }
    $params = array('id' => $userid, 'course' => $courseid);
    $params = array('href' => new moodle_url('/user/view.php', $params));
    return html_writer::tag('a', $username, $params);
}

/**
 * reader_fullname_link_viewasstudent
 *
 * @param xxx $userdata
 * @param xxx $id
 * @param xxx $nolink (optional, default=false)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_fullname_link_viewasstudent($userdata, $id, $nolink=false) {
    $fullname = $userdata->firstname.' '.$userdata->lastname;
    if ($nolink) {
        return $fullname;
    }
    if (isset($userdata->userid)) {
        $userid = $userdata->userid;
    } else {
        $userid = $userdata->id;
    }
    $params = array('id' => $id, 'viewasstudent' => $userid);
    $params = array('href' => new moodle_url('/mod/reader/admin.php', $params));
    return html_writer::tag('a', $fullname, $params);
}

/**
 * reader_fullname_link
 *
 * @uses $CFG
 * @uses $COURSE
 * @param xxx $userdata
 * @param xxx $courseid
 * @param xxx $nolink (optional, default=false)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_fullname_link($userdata, $courseid, $nolink=false) {
    $fullname = $userdata->firstname.' '.$userdata->lastname;
    if ($nolink) {
        return $fullname;
    }
    if (isset($userdata->userid)) {
        $userid = $userdata->userid;
    } else {
        $userid = $userdata->id;
    }
    $params = array('id' => $userid, 'course' => $courseid);
    $params = array('href' => new moodle_url('/user/view.php', $params));
    return html_writer::tag('a', $fullname, $params);
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
 * @param xxx $gid
 * @todo Finish documenting this function
 */
function reader_select_perpage($id, $act, $sort, $orderby, $gid) {
    global $CFG, $COURSE, $_SESSION;

    echo '<table style="width:100%"><tr><td align="right">';

    $params = array('action' => new moodle_url('/mod/reader/admin.php'), 'method' => 'get', 'class' => 'popupform');
    echo html_writer::start_tag('form', $params);

    $params = array('a' => 'admin',  'id'  => $id,
                    'act'  => $act,  'gid' => $gid,
                    'sort' => $sort, 'orderby' => $orderby,
                    'book' => optional_param('book', '', PARAM_CLEAN));
    foreach ($params as $name => $value) {
        echo html_writer::empty_tag('input', array('type' => 'hidden', 'name' => $name, 'value' => $value));
    }

    echo 'Perpage ';

    echo html_writer::start_tag('select', array('id' => 'id_perpage', 'name' => 'perpage'));

    $perpages = array(30, 60, 100, 200, 500);
    foreach ($perpages as $perpage) {

        $params = array('value' => $perpage);
        if ($_SESSION['SESSION']->reader_perpage == $perpage) {
            $params['selected'] = 'selected';
        }
        echo html_writer::tag('option', $perpage, $params);
    }

    echo html_writer::end_tag('select');

    $params = array('type' => 'submit', 'id' => 'id_perpage_submit', 'name' => 'perpage_submit', 'value' => get_string('go'));
    echo html_writer::empty_tag('input', $params);

    echo html_writer::end_tag('form');
    echo '</td></tr></table>';

    // javascript to submit perpage form automatically and hide "Go" button
    echo '<script type="text/javascript">'."\n";
    echo "//<![CDATA[\n";
    echo "var obj = document.getElementById('id_perpage');\n";
    echo "if (obj) {\n";
    echo "    obj.onchange = new Function('this.form.submit(); return true;');\n";
    echo "}\n";
    echo "var obj = document.getElementById('id_perpage_submit');\n";
    echo "if (obj) {\n";
    echo "    obj.style.display = 'none';\n";
    echo "}\n";
    echo "obj = null;\n";
    echo "//]]>\n";
    echo "</script>\n";
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
function reader_print_search_form($id='', $act='', $book='') {
    global $OUTPUT;

    $id = optional_param('id', 0, PARAM_INT);
    $act = optional_param('act', NULL, PARAM_CLEAN);
    $book = optional_param('book', NULL, PARAM_CLEAN);
    $searchtext = optional_param('searchtext', NULL, PARAM_CLEAN);
    $searchtext = str_replace('\\"', '"', $searchtext);

    $output = '';

    $params = array('a' => 'admin', 'id' => $id, 'act' => $act, 'book' => $book);
    $action = new moodle_url('/mod/reader/admin.php', $params);

    $params = array('action' => $action, 'method' => 'post', 'id' => 'mform1');
    $output .= html_writer::start_tag('form', $params);

    $params = array('type' => 'text', 'name' => 'searchtext', 'value' => $searchtext, 'style' => 'width:120px;');
    $output .= html_writer::empty_tag('input', $params);

    $params = array('type' => 'submit', 'name' => 'submit', 'value' => get_string('search', 'reader'));
    $output .= html_writer::empty_tag('input', $params);

    $output .= html_writer::end_tag('form');

    if ($searchtext) {
        $params = array('id' => $id, 'act' => $act);
        $output .= $OUTPUT->single_button(new moodle_url('/mod/reader/admin.php', $params), get_string('showall', 'reader'), 'post', $params);
    }

    echo '<table style="width:100%"><tr><td align="right">'.$output.'</td></tr></table>';
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
                $booklevel = strtolower($book->level);
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
 * @uses $gid
 * @uses $id
 * @uses $orderby
 * @uses $page
 * @uses $sort
 * @param xxx $userid
 * @param xxx $readerlevel
 * @param xxx $leveltype
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_selectlevel_form($userid, $readerlevel, $leveltype) {
    global $id, $act, $gid, $sort, $orderby, $page;

    if (empty($readerlevel)) {
        $readerlevel = new stdClass();
    }
    if (! isset($readerlevel->$leveltype)) {
        $readerlevel->$leveltype = 0;
    }

    $patch = $userid.'-'.$leveltype;

    $output = '';
    $output .= html_writer::start_tag('div', array('id' => 'changelevels'.$patch));
    $output .= '<select id="choose_levels'.$patch.'" name="levels'.$patch.'" onchange="request(\'admin.php?ajax=true&\' + this.options[this.selectedIndex].value,\'changelevels'.$patch.'\'); return false;">';

    $levels = range(0, 14);
    foreach ($levels as $level) {
        $params = array('a' => 'admin', 'id' => $id, 'act' => $act,
                        'changelevel' => $level, 'userid' => $userid,
                        'slevel' => $leveltype);
        $params = array('value' => new moodle_url('/mod/reader/admin.php', $params));
        if ($level == $readerlevel->$leveltype) {
            $params['selected'] = 'selected';
        }
        $output .= html_writer::tag('option', $level, $params);
    }
    $output .= html_writer::end_tag('select');
    $output .= html_writer::end_tag('div');

    return $output;
}

/**
 * reader_promotion_stop_box
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $_SESSION
 * @uses $act
 * @uses $gid
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
    global $CFG, $COURSE, $_SESSION, $id, $act, $gid, $sort, $orderby, $page;

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
 * @uses $gid
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
    global $CFG, $COURSE, $DB, $_SESSION, $id, $act, $gid, $sort, $orderby, $page;

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
            $i_max = count($levels) - 1;
            for ($i=0; $i<=$i_max; $i++) {
                if ($i < $i_max && $goal < $levels[$i+1] && $goal > $levels[$i]) {
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
 * @uses $gid
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
    global $CFG, $COURSE, $_SESSION, $id, $act, $gid, $sort, $orderby, $page;

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
 * @uses $gid
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
    global $CFG, $COURSE, $DB, $_SESSION, $id, $act, $gid, $sort, $orderby, $page;

    $levels = array(0=>'No',1=>'Yes');

    $data = $DB->get_record('reader_strict_users_list', array('readerid' => $reader->id, 'userid' => $userid));

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
 * @uses $gid
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
    global $CFG, $COURSE, $DB, $_SESSION, $id, $act, $gid, $sort, $orderby, $page;

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
 * @uses $gid
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
    global $CFG, $COURSE, $_SESSION, $id, $act, $gid, $sort, $orderby, $page;

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
 * reader_makexml
 *
 * @param xxx $xmlarray
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_makexml($xml) {
    return implode('', $xml);
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

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    if ($post) {
        $postfields = array();
        foreach ($post as $key1 => $value1) {
            if (is_array($value1)) {
                foreach ($value1 as $key2 => $value2) {
                    if (is_array($value2)) {
                        foreach ($value2 as $key3 => $value3) {
                            $postfields[] = $key1.'['.$key2.']['.$key3.']='.$value3;
                        }
                    } else {
                        $postfields[] = $key1.'['.$key2.']='.$value2;
                    }
                }
            } else {
                $postfields[] = $key1.'='.$value1;
            }
        }
        if ($postfields = implode('&', $postfields)) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        }
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
    global $CFG, $DB, $USER;

    if (! $progress) {
        $progress = 0;
    }

    $goal = $DB->get_field('reader_levels', 'goal', array('userid' => $USER->id, 'readerid' => $reader->id));

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

    $html = '';
    $html .= '<style type="text/css" >'."\n";
    $html .= '#ScoreBoxDiv {'."\n";
    $html .= '    position:absolute;'."\n";
    $html .= '    left:5px; top:34px;'."\n";
    $html .= '    width:824px;'."\n";
    $html .= '    height:63px;'."\n";
    $html .= '    background-color: '.$bgcolor.' ;'."\n";
    $html .= '    z-index:5;'."\n";
    $html .= '}'."\n";
    $html .= 'img.color {'."\n";
    $html .= '    position:absolute;'."\n";
    $html .= '    top:40px;'."\n";
    $html .= '    left:10px;'."\n";
    $html .= '    z-index:20;'."\n";
    $html .= '    clip: rect(0px '.$currentpositionpix.'px 100px 0px);'."\n";
    $html .= '}'."\n";
    $html .= 'img.mark {'."\n";
    $html .= '    position:absolute;'."\n";
    $html .= '    top:47px;'."\n";
    $html .= '    left:'.($currentpositionpix+10).'px;'."\n";
    $html .= '    z-index:20;'."\n";
    $html .= '}'."\n";
    $html .= 'img.grey {'."\n";
    $html .= '    position:absolute;'."\n";
    $html .= '    top:40px;'."\n";
    $html .= '    left:10px;'."\n";
    $html .= '    z-index:15;'."\n";
    $html .= '}'."\n";
    $html .= 'img.goal {'."\n";
    $html .= '    position:absolute;'."\n";
    $html .= '    top:26px;'."\n";
    $html .= '    left:'.$currentpositiongoalpix.'px;'."\n";
    $html .= '    z-index:40;'."\n";
    $html .= '}'."\n";
    $html .= '</style>'."\n";
    $html .= '<div id="ScoreBoxDiv" class="ScoreBoxDiv"> &nbsp;&nbsp;&nbsp;&nbsp;</div>'."\n";
    $html .= '<img class="color" src="'.$CFG->wwwroot.'/mod/reader/img/colorscale800px'.$img.'.png">'."\n";
    $html .= '<img class="grey" src="'.$CFG->wwwroot.'/mod/reader/img/colorscale800px'.$img.'gs.png">'."\n";
    $html .= '<img class="mark" src="'.$CFG->wwwroot.'/mod/reader/img/now.png">'."\n";

    if (! empty($goal)) {
        $html .= '<img class="goal" src="'.$CFG->wwwroot.'/mod/reader/img/goal.png">';
    }

    return $html;
}

/**
 * reader_get_reader_difficulty
 *
 * @uses $DB
 * @param xxx $reader
 * @param xxx $bookid
 * @param xxx $difficulty (optional, default=0)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_reader_difficulty($reader, $bookid, $difficulty=0) {
    global $DB;

    // "Course-specific quiz selection" is enabled for this reader activity
    if ($reader->bookinstances) {
        if ($instance = $DB->get_record('reader_book_instances', array('readerid' => $reader->id, 'bookid' => $bookid))) {
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
 * reader_get_reader_length
 *
 * @uses $DB
 * @param xxx $reader
 * @param xxx $bookid
 * @param xxx $length (optional, default=0)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_reader_length($reader, $bookid, $length=0) {
    global $DB;

    // "Course-specific quiz selection" is enabled for this reader activity
    if ($reader->bookinstances) {
        if ($instance = $DB->get_record('reader_book_instances', array('readerid' => $reader->id, 'bookid' => $bookid))) {
            return $instance->length;
        }
    }

    // if we already know the length for this book, then use that
    if ($length) {
        return $length;
    }

    // get the book length from the "reader_books" table
    if ($book = $DB->get_record('reader_books', array('id' => $bookid))) {
        return $book->length;
    }

    return 0; // shouldn't happen !!
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
    global $act, $excel, $id;

    if ($excel) {
        return ($data['checkbox']==1 ? 'yes' : 'no');
    }

    $target_id = 'atcheck_'.$data['id'];
    $target_url = "'admin.php?'+'ajax=true&id=$id&act=$act&checkattempt=".$data['id']."&checkattemptvalue='+(this.checked ? 1 : 0)";

    $params = array('type'    => 'checkbox',
                    'name'    => 'checkattempt',
                    'value'   => '1',
                    'onclick' => "request($target_url,'$target_id')");

    if ($data['checkbox'] == 1) {
        $params['checked'] = 'checked';
    }

    // create checkbox INPUT element and target DIV
    return html_writer::empty_tag('input', $params).
           html_writer::tag('div', '', array('id' => $target_id));
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
    global $CFG, $DB, $USER;

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

    $periods = array("second", "minute", "hour", "day", "week", "month", "year", "decade");
    $lengths = array("60","60","24","7","4.35","12","10");

    $now = time();

    if($now > $unix_date) {
        $difference = $now - $unix_date;
        $tense = "";

    } else {
        $difference = $unix_date - $now;
        $tense = "";
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
            $difference = $now - $unix_date;
            $tense = "";

        } else {
            $difference = $unix_date - $now;
            $tense = "";
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
 * @param xxx $seconds
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_nicetime2($seconds) {

    $minutes = round($seconds / 60);
    $hours   = round($seconds / 3600);
    $days    = round($seconds / 86400);
    $weeks   = round($seconds / 604800);
    $months  = round($seconds / 2419200);
    $years   = round($seconds / 29030400);

    switch (true) {
        case ($seconds <= 60): $text = ($seconds==1 ? 'one second' : "$seconds seconds"); break;
        case ($minutes <= 60): $text = ($minutes==1 ? 'one minute' : "$minutes minutes"); break;
        case ($hours   <= 24): $text = ($hours==1   ? 'one hour'   : "$hours hours"    ); break;
        case ($days    <= 7) : $text = ($days==1    ? 'one day'    : "$days days"      ); break;
        case ($weeks   <= 4) : $text = ($weeks==1   ? 'one week'   : "$weeks weeks"    ); break;
        case ($months  <=12) : $text = ($months==1  ? 'one month'  : "$months months"  ); break;
        default:               $text = ($years==1   ? 'one year'   : "$years years "   );
    }

    return "$text ";
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
    global $DB, $USER, $course;

    $data = false;

    if (isset($reader->id)) {
        if ($usergroups = groups_get_all_groups($course->id, $USER->id)){
            foreach ($usergroups as $group) {
                if (isset($group->id)) {
                    $params = array('readerid' => $reader->id, 'level' => $studentlevel, 'groupid' => $group->id);
                    $data = $DB->get_record('reader_forcedtimedelay', $params);
                }
            }
        }
        if (empty($data)) {
            $params = array('readerid' => $reader->id, 'level' => $studentlevel, 'groupid' => 0);
            $data = $DB->get_record('reader_forcedtimedelay', $params);
        }
        if (empty($data)) {
            $params = array('readerid' => $reader->id, 'level' => 99, 'groupid' => 0);
            $data = $DB->get_record('reader_forcedtimedelay', $params);
        }
    }

    if (empty($data->delay)) {
        return $cleartime; // no delay $data found in database
    }

    return $data->delay + $lasttime;
}

/**
 * reader_copy_to_quizattempt
 *
 * @uses $DB
 * @param xxx $readerattempt
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_copy_to_quizattempt($readerattempt) {
    global $DB;
    $DB->delete_records('quiz_attempts', array('uniqueid' => $readerattempt->uniqueid));
    $quizattempt = (object)array(
        'uniqueid'             => $readerattempt->uniqueid,
        'quiz'                 => $readerattempt->quizid,
        'userid'               => $readerattempt->userid,
        'attempt'              => $readerattempt->attempt,
        'sumgrades'            => $readerattempt->sumgrades,
        'timestart'            => $readerattempt->timestart,
        'timefinish'           => $readerattempt->timefinish,
        'timemodified'         => $readerattempt->timemodified,
        'layout'               => $readerattempt->layout,
        'preview'              => 0,
        'needsupgradetonewqe'  => 0
    );
    if ($quizattempt->id = $DB->insert_record('quiz_attempts', $quizattempt)) {
        return true;
    } else {
        return false;
    }
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
    if ($course && isset($course->id)) {
        if (isset($course->numsections)) {
            return $course->numsections; // Moodle >= 2.3
        }
        if (isset($course->format)) {
            return $DB->get_field('course_format_options', 'value', array('courseid' => $course->id, 'format' => $course->format, 'name' => 'numsections'));
        }
    }
    return 0; // shouldn't happen !!
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
    $optional_param_array = 'optional_param';
    if (function_exists('optional_param_array')) {
        switch (true) {
            case (isset($_POST[$name]) && is_array($_POST[$name])): $optional_param_array = 'optional_param_array'; break;
            case (isset($_GET[$name])  && is_array($_GET[$name])) : $optional_param_array = 'optional_param_array'; break;
        }
    }
    return $optional_param_array($name, $default, $type);
}

/**
 * Exception for reporting error in Reader module
 */
class reader_exception extends moodle_exception {
    /**
     * Constructor
     * @param string $debuginfo some detailed information
     */
    function __construct($debuginfo=null) {
        parent::__construct('error', 'reader', '', null, $debuginfo);
    }
}

/**
 * reader_available_sql
 *
 * @param xxx $reader
 * @param xxx $userid
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_available_sql($reader, $userid) {

    // we want to get a list of all available books
    // a book is available if it satisfies the following conditions:
    // (1) the book is not hidden
    // (2) the quiz for the book has NEVER been attempted before by this user
    // (3) EITHER the book has an empty "sametitle" field
    //     OR the "sametitle" field is different from that of any books whose quizzes this user has taken before
    // (4) EITHER the reader activity's "levelcheck" field is empty
    //     OR the level of the book is one of the levels this user is currently allowed to take in this reader

    // "id" values of books whose quizzes this user has already attempted
    $recordids  = 'SELECT rb.id '.
                  'FROM {reader_attempts} ra LEFT JOIN {reader_books} rb ON ra.quizid = rb.quizid '.
                  'WHERE ra.userid = ? AND rb.id IS NOT NULL';

    // "sametitle" values for books whose quizzes this user has already attempted
    $sametitles = 'SELECT DISTINCT rb.sametitle '.
                  'FROM {reader_attempts} ra LEFT JOIN {reader_books} rb ON ra.quizid = rb.quizid '.
                  'WHERE ra.userid = ? AND rb.id IS NOT NULL AND rb.sametitle <> ?';

    $from   = '{reader_books}';
    $where = "id NOT IN ($recordids) AND (sametitle = ? OR sametitle NOT IN ($sametitles)) AND hidden = ?";
    $sqlparams = array($userid, '', $userid, '', 0);

    $levels = array();
    if (isset($_SESSION['SESSION']->reader_teacherview) && $_SESSION['SESSION']->reader_teacherview == 'teacherview') {
        // do nothing - this is a teacher
    } else if ($reader->levelcheck == 0) {
        // do nothing - level checking is disabled
    } else {
        // a student with level-checking enabled
        $leveldata = reader_get_level_data($reader, $userid);
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
        if ($reader->bookinstances) {
            // we are maintaining a list of book difficulties for each course, so we must check "reader_books_instances"
            $from  .= ' rb LEFT JOIN {reader_book_instances} rbi ON rbi.bookid = rb.id AND rbi.readerid = '.$reader->id;
            $where .= " AND ((rbi.id IS NULL AND rb.difficulty IN ($levels)) OR (rbi.id IS NOT NULL AND rbi.difficulty IN ($levels)))";
        } else {
            $where .= " AND difficulty IN ($levels)";
        }
    }

    return array($from, $where, $sqlparams);
}

/**
 * reader_valid_genres
 *
 * @param string $genre (optional, default='') a comma-separated list of genre codes
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_valid_genres($genre='') {

    $validgenres = array(
        'all' => "All Genres",
        'ad' => "Adventure",
        'bi' => "Biography",
        'cl' => "Classics",
        'ch' => "Children's literature",
        'co' => "Comedy",
        'cu' => "Culture",
        'ge' => "Geography/Environment",
        'ho' => "Horror",
        'hi' => "Historical",
        'hu' => "Human interest",
        'li' => "Literature in Translation",
        'mo' => "Movies",
        'mu' => "Murder Mystery",
        'ro' => "Romance",
        'sc' => "Science fiction",
        'sh' => "Short stories",
        'te' => "Technology & Science",
        'th' => "Thriller",
        'ch' => "Children's literature",
        'yo' => "Young life, adventure"
    );

    if ($genre=='') {
        return $validgenres;
    }

    $genre = explode(',', $genre);
    $genre = array_flip($genre);
    $genre = array_intersect_key($validgenres, $genre);
    $genre = implode(', ', $genre);
    return $genre;
}

/**
 * reader_available_genres
 *
 * @param xxx $from
 * @param xxx $where
 * @param xxx $sqlparams
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_available_genres($from, $where, $sqlparams) {
    global $DB;

    // a list of valid genres ($code => $text)
    $genres = array();

    // skip NULL and empty genre fields
    $where = "genre IS NOT NULL AND genre <> ? AND $where";
    array_unshift($sqlparams, '');

    if ($records = $DB->get_records_sql("SELECT DISTINCT genre FROM $from WHERE $where", $sqlparams)) {

        $genres = array_keys($records);
        $genres = array_filter($genres); // remove blanks
        $genres = implode(',', $genres); // some books have a comma-separated list of genres
        $genres = explode(',', $genres); // so we need to implode and then explode the list
        $genres = array_unique($genres); // remove duplicates
        sort($genres);

        // extract only the required valid genres
        $genres = array_flip($genres);
        $genres = array_intersect_key(reader_valid_genres(), $genres);

        // sort the values (but maintain keys)
        asort($genres);
    }

    return $genres;
}

/**
 * reader_available_publishers
 *
 * @param xxx $cmid
 * @param xxx $from
 * @param xxx $where
 * @param xxx $sqlparams
 * @param xxx $count (passed by reference)
 * @param xxx $record (passed by reference)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_available_publishers($cmid, $from, $where, $sqlparams, &$count, &$record) {
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
        //$output .= html_writer::tag('p', 'Choose a publisher');

        $target_div = 'bookleveldiv';
        $target_url = "'view_get_bookslist.php?id=$cmid&publisher='+escape(this.options[this.selectedIndex].value)";

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

        $output .= html_writer::end_tag('select');
        $output .= html_writer::tag('div', '', array('id' => $target_div));
    }

    return $output;
}

/**
 * reader_available_levels
 *
 * @param xxx $publisher
 * @param xxx $cmid
 * @param xxx $from
 * @param xxx $where
 * @param xxx $sqlparams
 * @param xxx $count (passed by reference)
 * @param xxx $record (passed by reference)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_available_levels($publisher, $cmid, $from, $where, $sqlparams, &$count, &$record) {
    global $DB;
    $output = '';

    $where .= ' AND publisher = ?';
    array_push($sqlparams, $publisher);

    $select = "level, COUNT(*) AS countbooks, ROUND(SUM(difficulty) / COUNT(*)) AS average_difficulty";
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
        $target_url = "'view_get_bookslist.php?id=$cmid&publisher=$publisher&level='+escape(this.options[this.selectedIndex].value)";

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

        $output .= html_writer::end_tag('select');
        $output .= html_writer::tag('div', '', array('id' => $target_div));
    }

    return $output;
}

/**
 * reader_available_bookids
 *
 * @param xxx $publisher
 * @param xxx $level
 * @param xxx $cmid
 * @param xxx $from
 * @param xxx $where
 * @param xxx $sqlparams
 * @param xxx $count (passed by reference)
 * @param xxx $record (passed by reference)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_available_bookids($publisher, $level, $cmid, $from, $where, $sqlparams, &$count, &$record) {
    global $DB;
    $output = '';

    $where .= " AND publisher = ? AND level = ?";
    array_push($sqlparams, $publisher, $level);

    $select = '*';
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
        $target_url = "'view_get_bookslist.php?id=$cmid&publisher=$publisher&level=$level&bookid='+this.options[this.selectedIndex].value";

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
        $output .= html_writer::tag('div', '', array('id' => $target_div, 'style' => 'float: left; margin: 0px 9px;'));
    }

    return $output;
}

/**
 * reader_available_books
 *
 * @param xxx $cmid
 * @param xxx $reader
 * @param xxx $userid
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_available_books($cmid, $reader, $userid) {
    global $DB, $OUTPUT;
    $output = '';

    // get SQL $from and $where statements to extract available books
    list($from, $where, $sqlparams) = reader_available_sql($reader, $userid);

    // get parameters passed from browser
    $publisher = optional_param('publisher', null, PARAM_CLEAN); // book publisher
    $level     = optional_param('level',     null, PARAM_CLEAN); // book level
    $bookid    = optional_param('bookid',    null, PARAM_INT  ); // book id

    if ($publisher===null) {

        $count = 0;
        $record = null;
        $output .= reader_available_publishers($cmid, $from, $where, $sqlparams, $count, $record);

        if ($count==0 || $count > 1) {
            return $output;
        }

        // otherwise, there is just one publisher, so continue and show the levels
        $level = $record->publisher;
    }

    if ($level===null) {

        $count = 0;
        $record = null;
        $output .= reader_available_levels($publisher, $cmid, $from, $where, $sqlparams, $count, $record);

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
        $output .= reader_available_bookids($publisher, $level, $cmid, $from, $where, $sqlparams, $count, $record);

        if ($count==0 || $count > 1) {
            return $output;
        }

        // otherwise there is just one book, so continue and show the book name
        $bookid = $record->id;
    }

    if ($book===null) {
        $book = $DB->get_record('reader_books', array('id' => $bookid));
    }

    $params = array('id' => $cmid, 'book' => $bookid);
    $url = new moodle_url('/mod/reader/quiz/startattempt.php', $params);

    $params = array('class' => 'singlebutton readerquizbutton');
    $output .= $OUTPUT->single_button($url, get_string('takequizfor', 'reader', $book->name), 'get', $params);

    return $output;
}

/**
 * reader_search_books
 *
 * @param xxx $cmid
 * @param xxx $reader
 * @param xxx $userid
 * @param xxx $showform (optional, default=false)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_search_books($cmid, $reader, $userid, $showform=false) {
    global $CFG, $DB, $OUTPUT;
    $output = '';

    // get parameters passed from form
    $searchpublisher  = optional_param('searchpublisher',  '', PARAM_CLEAN);
    $searchlevel      = optional_param('searchlevel',      '', PARAM_CLEAN);
    $searchname       = optional_param('searchname',       '', PARAM_CLEAN);
    $searchgenre      = optional_param('searchgenre',      '', PARAM_CLEAN);
    $searchdifficulty = optional_param('searchdifficulty', -1, PARAM_INT);
    $search           = optional_param('search',            0, PARAM_INT);

    // get SQL $from and $where statements to extract available books
    list($from, $where, $sqlparams) = reader_available_sql($reader, $userid);

    if ($showform) {
        $target_div = 'searchresultsdiv';
        $target_url = "'view_get_bookslist.php?id=$cmid'".
                      "+'&search=1'". // so we can detect incoming search results
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
            'action' => new moodle_url('/mod/reader/view.php', array('id' => $cmid)),
            'onsubmit' => "request($target_url, '$target_div'); return false;"
        );
        $output .= html_writer::start_tag('form', $params);

        $table = new html_table();
        $table->align = array('right', 'left');

        $table->rowclasses[0] = 'advanced'; // publisher
        $table->rowclasses[1] = 'advanced'; // level
        $table->rowclasses[3] = 'advanced'; // genre
        $table->rowclasses[4] = 'advanced'; // difficulty

        $table->data[] = new html_table_row(array(
            html_writer::tag('b', get_string('publisher', 'reader').':'),
            html_writer::empty_tag('input', array('type' => 'text', 'name' => 'searchpublisher', 'value' => $searchpublisher))
        ));
        $table->data[] = new html_table_row(array(
            html_writer::tag('b', get_string('level', 'reader').':'),
            html_writer::empty_tag('input', array('type' => 'text', 'name' => 'searchlevel', 'value' => $searchlevel))
        ));
        $table->data[] = new html_table_row(array(
            html_writer::tag('b', get_string('booktitle', 'reader').':'),
            html_writer::empty_tag('input', array('type' => 'text', 'name' => 'searchname', 'value' => $searchname))
        ));

        // get list of valid and available genres ($code => $text)
        $genres = reader_available_genres($from, $where, $sqlparams);
        $genres = array('' => get_string('none')) + $genres;

        // add the "genre" drop-down list
        $table->data[] = new html_table_row(array(
            html_writer::tag('b', get_string('genre', 'block_readerview').':'),
            html_writer::select($genres, 'searchgenre', $searchgenre, '')
        ));

        // can this user view all levels of books in this reader activity?
        if (isset($_SESSION['SESSION']->reader_teacherview) && $_SESSION['SESSION']->reader_teacherview == 'teacherview') {
            // this is a teacher
            $alllevels = true;
        } else if ($reader->levelcheck == 0) {
            // no level checking
            $alllevels = true;
        } else {
            $alllevels = false;
        }

        // create list of RL's (reading levels) this user can attempt
        $levels = array();
        if ($alllevels) {
            if ($reader->bookinstances) {
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
            $leveldata = reader_get_level_data($reader, $userid);
            if ($leveldata['onprevlevel'] > 0 && $leveldata['currentlevel'] >= 1) {
                $levels[] = ($leveldata['currentlevel'] - 1);
            }
            if ($leveldata['onthislevel'] > 0 && $leveldata['currentlevel'] >= 0) {
                $levels[] = $leveldata['currentlevel'];
            }
            if ($leveldata['onnextlevel'] > 0) {
                $levels[] = ($leveldata['currentlevel'] + 1);
            }
        }

        // make each $levels key the same as the value
        // and then prepend the (-1 => "none") key & value
        $levels = array_combine($levels, $levels);
        $levels = array(-1 => get_string('none')) + $levels;

        // add the "RL" (reading level) drop-down list
        $table->data[] = new html_table_row(array(
            html_writer::tag('b', get_string('difficulty', 'reader').':'),
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

        $cheatsheet = '';
        $strcheatsheet = '';

        // if there is a "cheatsheet" script, make it available (for developer site admins only)
        if (has_capability('moodle/site:config', get_context_instance(CONTEXT_SYSTEM))) {
            if (file_exists($CFG->dirroot.'/mod/reader/utilities/print_cheatsheet.php')) {
                $cheatsheet = $CFG->wwwroot.'/mod/reader/utilities/print_cheatsheet.php';
                $strcheatsheet = get_string('cheatsheet', 'reader');
            }
        }

        // search for available books that match  the search criteria
        $select = 'id, publisher, level, name, genre, difficulty';
        if ($books = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $sqlparams)) {

            $table = new html_table();

            // add table headers - one per column
            $table->head = array(
                get_string('publisher', 'reader'),
                get_string('level', 'reader'),
                get_string('booktitle', 'reader')." (".count($books)." books)",
                get_string('genre', 'block_readerview'),
                get_string('difficulty', 'reader'),
                '&nbsp;'
            );

            // add extra column for "cheatsheet" links, if required
            if ($cheatsheet) {
                $table->head[] = html_writer::tag('small', $strcheatsheet);
            }

            // add one row for each book in the search results
            foreach ($books as $book) {

                // format publisher- level
                $publisher = $book->publisher.(($book->level=='' | $book->level=='--') ? '' : ' - '.$book->level);

                // construct url to start attempt at quiz
                $params = array('id' => $cmid, 'book' => $book->id);
                $url = new moodle_url('/mod/reader/quiz/startattempt.php', $params);

                // construct button to start attempt at quiz
                $params = array('class' => 'singlebutton readerquizbutton');
                $button = $OUTPUT->single_button($url, get_string('takethisquiz', 'reader'), 'get', $params);

                // add cells to this row of the table
                $row = array(
                    $book->publisher,
                    (($book->level=='' || $book->level=='--') ? '' : $book->level),
                    $book->name,
                    reader_valid_genres($book->genre),
                    $book->difficulty,
                    $button
                );

                // add cheat sheet link, if required
                if ($cheatsheet) {
                    $url = new moodle_url($cheatsheet, array('publishers' => $publisher, 'books' => $book->id));
                    $params = array('href' => $url, 'onclick' => "this.target='cheatsheet'; return true;");
                    $row[] = html_writer::tag('small', html_writer::tag('a', $strcheatsheet, $params));
                }

                // add this row to the table
                $table->data[] = new html_table_row($row);
            }

            // create the HTML for the table of search results
            if (count($table->data)) {
                $searchresults .= html_writer::table($table);
            }
        } else {
            $searchresults .= html_writer::tag('p', get_string('nosearchresults', 'reader'));
        }
    }
    $output .= html_writer::tag('div', $searchresults, array('id' => 'searchresultsdiv'));

    return $output;
}
