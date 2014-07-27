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
        'quiztimelimit'      => '900', // 900 secs = 15 mins
        'pointreport'        => '0',
        'percentforreading'  => '60',
        'questionmark'       => '0',
        'quiznextlevel'      => '6',
        'quizpreviouslevel'  => '3',
        'quizonnextlevel'    => '1',
        'bookcovers'         => '1',
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

    $readercfg = get_config('mod_reader');
    if ($readercfg==null) {
        $readercfg = new stdClass();
    }
    foreach ($defaults as $name => $value) {
        if (! isset($readercfg->$name)) {
            set_config($name, $value, 'mod_reader');
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

    // delete expired messages
    $select = 'timefinish > ? && timefinish < ?';
    $params = array(0, time());
    $DB->delete_records_select('reader_messages', $select, $params);

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
                reader_add_to_log(1, 'reader', 'Cron', '', "Double entries found!! reader_question_instances; quiz: {$publishersquizze->quizid}; question: {$answersgrade_->question}");
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
    $from   = '{reader_attempts} ra JOIN {reader_books} rb ON ra.bookid = rb.id';
    $where  = 'ra.userid = ? AND ra.reader = ? AND ra.deleted = ? AND ra.timefinish > ?';
    $params = array($USER->id, $reader->id, 0, $reader->ignoredate);

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
 * @param integer $attemptnumber
 * @param integer $bookid
 * @param boolean $adduniqueid (optional, default = false)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_create_attempt($reader, $attemptnumber, $book, $adduniqueid=false, $booktable='reader_books') {
    global $CFG, $DB, $USER;

    if (is_numeric($book)) {
        $book = $DB->get_record($booktable, array('id' => $book));
    }

    if (empty($book)) {
        return false; // invalid $bookid or $book->quizid
    }

    $dbman = $DB->get_manager();
    $use_quiz_slots = $dbman->table_exists('quiz_slots');

    $params = array('reader' => $reader->id, 'userid' => $USER->id, 'attempt' => ($attemptnumber - 1));
    if ($attemptnumber > 1 && $reader->attemptonlast && ($attempt = $DB->get_record('reader_attempts', $params))) {
        // do nothing - we will build on previous attempt
    } else {
        // we are not building on last attempt so create a new attempt

        // save the list of question ids (for use in quiz/attemptlib.php)
        if ($use_quiz_slots) {
            // Moodle >= 2.7
            if ($reader->questions = $DB->get_records_menu('quiz_slots', array('quizid' => $book->quizid), 'page,slot', 'id,questionid')) {
                $reader->questions = array_values($reader->questions);
                $reader->questions = array_filter($reader->questions);
                $reader->questions = implode(',', $reader->questions);
            }
        } else {
            // Moodle <= 2.6
            $reader->questions = $DB->get_field('quiz', 'questions', array('id' => $book->quizid));
        }
        if ($reader->questions===false) {
            $reader->questions = ''; // shouldn't happen !!
        }

        $attempt = (object)array(
            'reader'  => $reader->id,
            'userid'  => $USER->id,
            'bookid'  => $book->id,
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

    $attempt->ip = getremoteaddr();

    if ($adduniqueid) {
        $attempt->uniqueid = reader_get_new_uniqueid($reader->context->id, $book->quizid);
    }

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
            if ($use_quiz_slots) {
                // Moodle >= 2.7
                $params = array('quizid' => $book->quizid, 'questionid' => $questionid);
                $grade = $DB->get_field('quiz_slots', 'maxmark', $params);
            } else {
                // Moodle <= 2.6
                $params = array('quiz' => $book->quizid, 'question' => $questionid);
                $grade = $DB->get_field('quiz_question_instances', 'grade', $params);
            }
        }
        $instance = (object)array(
            'quiz'     => $book->quizid,
            'question' => $questionid,
            'grade'    => (empty($grade) ? 0 : round($grade))
        );
        if (! $instance->id = $DB->insert_record('reader_question_instances', $instance)) {
            // could not insert new instance - shouldn't happen !!
        }
    }

    return $attempt;
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
    $questions = explode(',', $layout);
    $questions = array_filter($questions); // remove blanks
    if ($shuffle) {
        srand((float)microtime() * 1000000); // for php < 4.2
        shuffle($questions);
    }
    $i = 1;
    $layout = '';
    foreach ($questions as $question) {
        if ($perpage && $i > $perpage) {
            $layout .= '0,';
            $i = 1;
        }
        $layout .= $question.',';
        $i++;
    }
    return $layout.'0';
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
    $params['hidden'] = 0;

    return grade_update('mod/quiz', $reader->course, 'mod', 'reader', $reader->id, 0, $grades, $params);
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
 * reader_get_student_attempts
 *
 * @uses $CFG
 * @uses $COURSE
 * @uses $DB
 * @param xxx $userid
 * @param xxx $reader
 * @param xxx $allreaders (optional, default=false)
 * @param xxx $booklist (optional, default=false)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_student_attempts($userid, $reader, $allreaders = false, $booklist = false) {
    global $DB;

    if ($booklist) {
        $ignoredate = 0;
    } else {
        $ignoredate = $reader->ignoredate;
    }

    $select = 'ra.id, ra.uniqueid, ra.reader, ra.userid, ra.bookid, ra.quizid, ra.attempt, ra.deleted, '.
              'ra.sumgrades, ra.percentgrade, ra.passed, ra.checkbox, ra.timefinish, ra.preview, ra.bookrating, '.
              'rb.name, rb.publisher, rb.level, rb.length, rb.image, rb.difficulty, rb.words, rb.sametitle';
    $from   = '{reader_attempts} ra LEFT JOIN {reader_books} rb ON ra.bookid = rb.id';
    $where  = 'ra.userid = :userid AND ra.deleted = :deleted AND ra.timefinish > :ignoredate AND ra.preview = :preview';
    $order  = 'ra.timefinish';
    $params = array('userid'=>$userid, 'deleted' => 0, 'ignoredate'=>$ignoredate, 'preview' => 0);
    if (! $allreaders) {
        $where .= ' AND ra.reader = :readerid';
        $params['readerid'] = $reader->id;
    }
    if (! $attempts = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $order", $params)) {
        $attempts = array();
    }

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
            $totals['points'] = reader_get_reader_length($reader, $attempt->bookid);
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

        if (isset($bookpercentmaxgrade[$attempt->bookid])) {
            list($totals['bookpercent'], $totals['bookmaxgrade']) = $bookpercentmaxgrade[$attempt->bookid];
        } else {
            $totalgrade = 0;
            $answersgrade = $DB->get_records ('reader_question_instances', array('quiz' => $attempt->quizid)); // Count Grades (TotalGrade)
            foreach ($answersgrade as $answersgrade_) {
                $totalgrade += $answersgrade_->grade;
            }
            //$totals['bookpercent']  = round(($attempt->sumgrades/$totalgrade) * 100, 2).'%';
            $totals['bookpercent']  = $attempt->percentgrade.'%';
            $totals['bookmaxgrade'] = $totalgrade * reader_get_reader_length($reader, $attempt->bookid);
            $bookpercentmaxgrade[$attempt->bookid] = array($totals['bookpercent'], $totals['bookmaxgrade']);
        }

        if ($attempt->preview == 1) {
            $statustext = 'Credit';
        }

        // get best attemptid for this quiz
        if (empty($bestattemptids[$attempt->bookid])) {
            $bestattemptid = 0;
        } else {
            $bestattemptid = $bestattemptids[$attempt->bookid];
        }
        if ($bestattemptid==0 || $returndata[$bestattemptid]['percentgrade'] < $attempt->percentgrade) {
            $bestattemptids[$attempt->bookid] = $attempt->id;
        }

        $returndata[$attempt->id] = array('id'            => $attempt->id,
                                          'bookid'        => $attempt->bookid,
                                          'quizid'        => $attempt->quizid,
                                          'timefinish'    => $attempt->timefinish,
                                          'booktitle'     => $attempt->name,
                                          'image'         => $attempt->image,
                                          'words'         => $attempt->words,
                                          'booklength'    => reader_get_reader_length($reader, $attempt->bookid),
                                          'publisher'     => $attempt->publisher,
                                          'booklevel'     => $attempt->level,
                                          'bookdiff'      => reader_get_reader_difficulty($reader, $attempt->bookid),
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
    if ($items = glob($dir.'/*')) {
        foreach($items as $item) {
            switch (true) {
                case is_file($item): unlink($item); break;
                case is_dir($item) : reader_remove_directory($item); break;
            }
        }
    }
    return rmdir($dir);
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

    //$params = array('userid' => $USER->id, 'readerid' => $reader->id);
    //if (! $levels = $DB->get_record('reader_levels', $params)) {
    //    $levels = (object)array(
    //        'userid'        => $USER->id,
    //        'startlevel'    => 0,
    //        'currentlevel'  => 0,
    //        'readerid'      => $reader->id,
    //        'promotionstop' => $reader->promotionstop,
    //        'time'          => time(),
    //    );
    //    $levels->id = $DB->insert_record('reader_levels', $levels);
    //    $levels = $DB->get_record('reader_levels', $params);
    //}

    $params = array('userid' => $USER->id, 'readerid' => $reader->id);
    if ($record = $DB->get_record('reader_levels', $params)) {
        $goal = $record->goal;
        $currentlevel = $record->currentlevel;
    } else {
        $goal = 0;
        $currentlevel = 0;
    }

    if (! $goal) {
        if ($records = $DB->get_records('reader_goals', array('readerid' => $reader->id))) {
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
        $goal = $reader->goal;
    }

    if ($progress > $goal) {
        $goalchecker = $progress;
    } else {
        $goalchecker = $goal;
    }
    if ($goalchecker <= 50000) {
        $img = 5;
        $bgcolor = "#00FFFF";
    } else if ($goalchecker <= 100000) {
        $img = 10;
        $bgcolor = "#FF00FF";
    } else if ($goalchecker <= 500000) {
        $img = 50;
        $bgcolor = "#FFFF00";
    } else {
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

    if ($goal) {
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
 * reader_format_delay
 *
 * @param xxx $seconds
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_format_delay($seconds) {

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
 * reader_copy_to_quizattempt
 *
 * @uses $DB
 * @param xxx $readerattempt
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_copy_to_quizattempt($readerattempt) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/mod/quiz/attemptlib.php');

    // clear out any attempts which may block the creation of the new quiz_attempt record
    $DB->delete_records('quiz_attempts', array('quiz' => $readerattempt->quizid,
                                               'userid' => $readerattempt->userid,
                                               'attempt' => $readerattempt->attempt));
    $DB->delete_records('quiz_attempts', array('uniqueid' => $readerattempt->uniqueid));

    // ensure uniqueid is unique
    //if ($DB->record_exists('quiz_attempts', array('uniqueid' => $readerattempt->uniqueid))) {
    //    $cm = get_coursemodule_from_instance('quiz', $readerattempt->quizid);
    //    $context = reader_get_context(CONTEXT_MODULE, $cm->id);
    //    if ($uniqueid = reader_get_new_uniqueid($context->id, $readerattempt->quizid)) {
    //        $readerattempt->uniqueid = $uniqueid;
    //        $params = array('id' => $readerattempt->id);
    //        $DB->set_field('reader_attempts', 'uniqueid', $uniqueid, $params);
    //    }
    //}

    // determine "state" of attempt
    // see "quiz/engines/states.php"
    $state = '';
    $timecheckstate = 0;
    if ($readerattempt->timefinish) {
        if (defined('quiz_attempt::FINISHED')) {
            $state = quiz_attempt::FINISHED; // 'finished'
            $timecheckstate = $readerattempt->timefinish;
        }
    } else {
        if (defined('quiz_attempt::IN_PROGRESS')) {
            $state = quiz_attempt::IN_PROGRESS; // 'inprogress'
            $timecheckstate = $readerattempt->timemodified;
        }
    }

    // replace faulty question category contexts
    // with the quiz's course module context
    if ($layout = $readerattempt->layout) {
        $layout = explode(',', $layout);
        $layout = array_filter($layout);
        $layout = array_unique($layout);
        // "layout" is a comma-separated list of slot numbers

        // get quiz context
        $cm = get_coursemodule_from_instance('quiz', $readerattempt->quizid);
        $quizcontext = reader_get_context(CONTEXT_MODULE, $cm->id);

        // get question ids used in this attempt
        list($select, $params) = $DB->get_in_or_equal($layout);
        $select = "questionusageid = ? AND slot $select";
        array_unshift($params, $readerattempt->uniqueid);
        if ($questionids = $DB->get_records_select_menu('question_attempts', $select, $params, 'slot', 'id,questionid')) {
            $questionids = array_unique($questionids);

            // get question category ids used by questions in this attempt
            list($select, $params) = $DB->get_in_or_equal($questionids);
            if ($categoryids = $DB->get_records_select_menu('question', "id $select", $params, 'id', 'id,category')) {
                $categoryids = array_unique($categoryids);

                // get context ids used by question categories in this attempt
                list($select, $params) = $DB->get_in_or_equal($categoryids);
                if ($contextids = $DB->get_records_select_menu('question_categories', "id $select", $params, 'id', 'id,contextid')) {
                    $contextids = array_unique($contextids);

                    // check context ids (used in question categories) are valid
                    foreach ($contextids as $contextid) {
                        if (! $DB->record_exists('context', array('id' => $contextid))) {
                            $DB->set_field('question_categories', 'contextid', $quizcontext->id, array('contextid' => $contextid));
                        }
                    }
                }
            }
        }
    }

    // set up new "quiz_attempt" record
    $quizattempt = (object)array(
        'quiz'                 => $readerattempt->quizid,
        'userid'               => $readerattempt->userid,
        'attempt'              => $readerattempt->attempt,
        'uniqueid'             => $readerattempt->uniqueid,
        'layout'               => $readerattempt->layout,
        'currentpage'          => 0,
        'preview'              => 0,
        'state'                => $state,
        'timestart'            => $readerattempt->timestart,
        'timefinish'           => $readerattempt->timefinish,
        'timemodified'         => $readerattempt->timemodified,
        'timecheckstate'       => $timecheckstate,
        'sumgrades'            => $readerattempt->sumgrades,
        'needsupgradetonewqe'  => 0
    );

    // return id of new "quiz_attempt" record (or false)
    return $DB->insert_record('quiz_attempts', $quizattempt);
}

/**
 * reader_get_context
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
        return get_context_instance($contextlevel, $instanceid);
    }
}

/**
 * reader_textlib
 *
 * a wrapper method to offer consistent API for textlib class
 * in Moodle 2.0 - 2.1, $textlib is first initiated, then called
 * in Moodle 2.2 - 2.5, we use only static methods of the "textlib" class
 * in Moodle >= 2.2, we use only static methods of the "core_text" class
 *
 * @param string $method
 * @param mixed any extra params that are required by the textlib $method
 * @return result from the textlib $method
 * @todo Finish documenting this function
 */
function reader_textlib() {
    if (class_exists('core_text')) {
        // Moodle >= 2.6
        $textlib = 'core_text';
    } else if (method_exists('textlib', 'textlib')) {
        // Moodle 2.0 - 2.1
        $textlib = textlib_get_instance();
    } else {
        // Moodle 2.3 - 2.5
        $textlib = 'textlib';
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
    if (is_numeric($course)) {
        $course = $DB->get_record('course', array('id' => $course));
    }
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
    if (is_numeric($course)) {
        $course = $DB->get_record('course', array('id' => $course));
    }
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
 * @param mixed $recursive (optional, default = true)
 * @return either an array of form values or the $default value
 */
function reader_optional_param_array($name, $default, $type, $recursive=true) {

    switch (true) {
        case isset($_POST[$name]): $param = $_POST[$name]; break;
        case isset($_GET[$name]) : $param = $_GET[$name]; break;
        default: return $default; // param not found
    }

    if (is_array($param) && function_exists('clean_param_array')) {
        return clean_param_array($param, $type, $recursive);
    }

    // not an array (or Moodle <= 2.1)
    return clean_param($param, $type);
}

/**
 * reader_add_to_log
 */
function reader_add_to_log($courseid, $module, $action, $url='', $info='', $cm=0, $user=0) {
    if (function_exists('get_log_manager')) {
        $manager = get_log_manager();
        $manager->legacy_add_to_log($courseid, $module, $action, $url, $info, $cm, $user);
    } else if (function_exists('add_to_log')) {
        add_to_log($courseid, $module, $action, $url, $info, $cm, $user);
    }
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
 * reader_can_accessallgroups
 *
 * @param xxx $cmid
 * @param xxx $userid
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_can_accessallgroups($userid) {
    static $can_accessallgroups = null;
    if ($can_accessallgroups===null) {
        $context = reader_get_context(CONTEXT_SYSTEM);
        $can_accessallgroups = has_capability('moodle/site:accessallgroups', $context, $userid);
    }
    return $can_accessallgroups;
}

/**
 * reader_can_addinstance
 *
 * @param xxx $cmid
 * @param xxx $userid
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_can_addinstance($cmid, $userid) {
    static $can_addinstance = null;
    if ($can_addinstance===null) {
        $context = reader_get_context(CONTEXT_MODULE, $cmid);
        $can_addinstance = has_capability('mod/reader:addinstance', $context, $userid);
    }
    return $can_addinstance;
}

/**
 * reader_can_manageattempts
 *
 * @param xxx $cmid
 * @param xxx $userid
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_can_manageattempts($cmid, $userid) {
    static $can_manageattempts = null;
    if ($can_manageattempts===null) {
        $context = reader_get_context(CONTEXT_MODULE, $cmid);
        $can_manageattempts = has_capability('mod/reader:manageattempts', $context, $userid);
    }
    return $can_manageattempts;
}

/**
 * reader_can_managebooks
 *
 * @param xxx $cmid
 * @param xxx $userid
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_can_managebooks($cmid, $userid) {
    static $can_managebooks = null;
    if ($can_managebooks===null) {
        $context = reader_get_context(CONTEXT_MODULE, $cmid);
        $can_managebooks = has_capability('mod/reader:managebooks', $context, $userid);
    }
    return $can_managebooks;
}

/**
 * reader_can_managequizzes
 *
 * @param xxx $cmid
 * @param xxx $userid
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_can_managequizzes($cmid, $userid) {
    static $can_managequizzes = null;
    if ($can_managequizzes===null) {
        $context = reader_get_context(CONTEXT_MODULE, $cmid);
        $can_managequizzes = has_capability('mod/reader:managequizzes', $context, $userid);
    }
    return $can_managequizzes;
}

/**
 * reader_can_manageusers
 *
 * @param xxx $cmid
 * @param xxx $userid
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_can_manageusers($cmid, $userid) {
    static $can_manageusers = null;
    if ($can_manageusers===null) {
        $context = reader_get_context(CONTEXT_MODULE, $cmid);
        $can_manageusers = has_capability('mod/reader:manageusers', $context, $userid);
    }
    return $can_manageusers;
}

/**
 * reader_can_viewreports
 *
 * @param xxx $cmid
 * @param xxx $userid
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_can_viewreports($cmid, $userid) {
    static $can_viewreports = null;
    if ($can_viewreports===null) {
        $context = reader_get_context(CONTEXT_MODULE, $cmid);
        $can_viewreports = has_capability('mod/reader:viewreports', $context, $userid);
    }
    return $can_viewreports;
}

/**
 * reader_can_attemptreader
 *
 * @param xxx $cmid
 * @param xxx $userid
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_can_attemptreader($cmid, $userid) {
    static $can_attemptreader = null;
    if ($can_attemptreader===null) {
        $context = reader_get_context(CONTEXT_MODULE, $cmid);
        $can_attemptreader = has_capability('mod/reader:viewbooks', $context, $userid);
    }
    return $can_attemptreader;
}

/**
 * reader_available_sql
 *
 * @param xxx $cmid
 * @param xxx $reader
 * @param xxx $userid
 * @param xxx $noquiz
 * @return array($from, $where, $params)
 * @todo Finish documenting this function
 */
function reader_available_sql($cmid, $reader, $userid, $noquiz=false) {

    if ($noquiz) {
        return array('{reader_books} rb', 'rb.quizid = ? AND rb.hidden = ? AND rb.level <> ?', array(0, 0, 99));
    }

    // a teacher / admin can always access all the books
    if (reader_can_addinstance($cmid, $userid)) {
        return array('{reader_books} rb', 'rb.quizid > ? AND rb.hidden = ? AND rb.level <> ?', array(0, 0, 99));
    }

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
                  'WHERE ra.userid = ? AND ra.deleted <> ? AND rb.id IS NOT NULL AND rb.quizid > ?';

    // "sametitle" values for books whose quizzes this user has already attempted
    $sametitles = 'SELECT DISTINCT rb.sametitle '.
                  'FROM {reader_attempts} ra LEFT JOIN {reader_books} rb ON ra.bookid = rb.id '.
                  'WHERE ra.userid = ? AND ra.deleted <> ? AND rb.id IS NOT NULL AND rb.sametitle <> ?';

    $from   = '{reader_books} rb';
    $where  = "rb.id NOT IN ($recordids) AND (rb.sametitle = ? OR rb.sametitle NOT IN ($sametitles)) AND hidden = ? AND level <> ?";
    $sqlparams = array($userid, 1, 0, '', $userid, 1, '', 0, 99);

    if ($reader->bookinstances) {
        $from  .= ' JOIN {reader_book_instances} rbi ON rbi.bookid = rb.id';
        $where .= ' AND rbi.readerid = ?';
        $sqlparams[] = $reader->id;
    }


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
            $where .= " AND rbi.difficulty IN ($levels)";
        } else {
            $where .= " AND rb.difficulty IN ($levels)";
        }
    }

    return array($from, $where, $sqlparams);
}

/**
 * reader_valid_genres
 *
 * @param string $genre (optional, default='') a comma-separated list of genre codes to be expanded
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

    // if no genre is requested, return whole list of valid genre codes
    if ($genre=='') {
        return $validgenres;
    }

    // a genre code (list) has been given, so expand the codes to full descriptions
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
 * @param xxx $action
 * @param xxx $from
 * @param xxx $where
 * @param xxx $sqlparams
 * @param xxx $count (passed by reference)
 * @param xxx $record (passed by reference)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_available_publishers($cmid, $action, $from, $where, $sqlparams, &$count, &$record) {
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
 * reader_available_levels
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
function reader_available_levels($publisher, $cmid, $action, $from, $where, $sqlparams, &$count, &$record) {
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
 * reader_available_bookids
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
function reader_available_bookids($publisher, $level, $cmid, $action, $from, $where, $sqlparams, &$count, &$record) {
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
 * reader_available_books
 *
 * @param xxx $cmid
 * @param xxx $reader
 * @param xxx $userid
 * @param xxx $action
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_available_books($cmid, $reader, $userid, $action='') {
    global $DB, $OUTPUT;
    $output = '';

    // get parameters passed from browser
    $publisher = optional_param('publisher', null, PARAM_CLEAN); // book publisher
    $level     = optional_param('level',     null, PARAM_CLEAN); // book level
    $bookid    = optional_param('bookid',    null, PARAM_INT  ); // book id
    $action    = optional_param('action', $action, PARAM_CLEAN);

    // get SQL $from and $where statements to extract available books
    $noquiz = ($action=='noquiz' || $action=='awardbookpoints');
    list($from, $where, $sqlparams) = reader_available_sql($cmid, $reader, $userid, $noquiz);

    if ($publisher===null) {

        $count = 0;
        $record = null;
        $output .= reader_available_publishers($cmid, $action, $from, $where, $sqlparams, $count, $record);

        if ($count==0 || $count > 1) {
            return $output;
        }

        // otherwise, there is just one publisher, so continue and show the levels
        $publisher = $record->publisher;
    }

    if ($level===null) {

        $count = 0;
        $record = null;
        $output .= reader_available_levels($publisher, $cmid, $action, $from, $where, $sqlparams, $count, $record);

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
        $output .= reader_available_bookids($publisher, $level, $cmid, $action, $from, $where, $sqlparams, $count, $record);

        if ($count==0 || $count > 1) {
            return $output;
        }

        // otherwise there is just one book, so continue and show the book name
        $bookid = $record->id;
    }

    if ($book===null) {
        $params = array('id' => $bookid);
        if ($noquiz) {
            $params['quizid'] = 0;
        }
        $book = $DB->get_record('reader_books', $params);
    }

    if ($action=='takequiz' && reader_can_attemptreader($cmid, $userid)) {
        $params = array('id' => $cmid, 'book' => $bookid);
        $url = new moodle_url('/mod/reader/quiz/startattempt.php', $params);

        $params = array('class' => 'singlebutton readerquizbutton');
        $output .= $OUTPUT->single_button($url, get_string('takequizfor', 'mod_reader', $book->name), 'get', $params);

        list($cheatsheeturl, $strcheatsheet) = reader_cheatsheet_init($action);
        if ($cheatsheeturl) {
            if ($level && $level != '--') {
                $publisher .= ' - '.$level;
            }
            $output .= reader_cheatsheet_link($cheatsheeturl, $strcheatsheet, $publisher, $book);
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
 * reader_search_books
 *
 * @param xxx $cmid
 * @param xxx $reader
 * @param xxx $userid
 * @param xxx $showform (optional, default=false)
 * @param xxx $action (optional, default='')
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_search_books($cmid, $reader, $userid, $showform=false, $action='') {
    global $CFG, $DB, $OUTPUT;
    $output = '';

    // get parameters passed from form
    $searchpublisher  = optional_param('searchpublisher',    '', PARAM_CLEAN);
    $searchlevel      = optional_param('searchlevel',        '', PARAM_CLEAN);
    $searchname       = optional_param('searchname',         '', PARAM_CLEAN);
    $searchgenre      = optional_param('searchgenre',        '', PARAM_CLEAN);
    $searchdifficulty = optional_param('searchdifficulty',   -1, PARAM_INT);
    $search           = optional_param('search',              0, PARAM_INT);
    $action           = optional_param('action',        $action, PARAM_CLEAN);

    // get SQL $from and $where statements to extract available books
    list($from, $where, $sqlparams) = reader_available_sql($cmid, $reader, $userid);

    if ($showform) {
        $target_div = 'searchresultsdiv';
        $target_url = "'view_books.php?id=$cmid'".
                      "+'&search=1'". // so we can detect incoming search results
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
        list($cheatsheeturl, $strcheatsheet) = reader_cheatsheet_init($action);

        // search for available books that match  the search criteria
        $select = 'rb.id, rb.publisher, rb.level, rb.name, rb.genre';
        if ($reader->bookinstances) {
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
                get_string('genre', 'block_readerview'),
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
                $row = array(
                    $book->publisher,
                    (($book->level=='' || $book->level=='--') ? '' : $book->level),
                    $book->name,
                    reader_valid_genres($book->genre),
                    $book->difficulty
                );

                if ($action=='takequiz') {
                    // construct url to start attempt at quiz
                    $params = array('id' => $cmid, 'book' => $book->id);
                    $url = new moodle_url('/mod/reader/quiz/startattempt.php', $params);

                    // construct button to start attempt at quiz
                    $params = array('class' => 'singlebutton readerquizbutton');
                    $button = $OUTPUT->single_button($url, get_string('takethisquiz', 'mod_reader'), 'get', $params);

                    $row[] = $button;
                }

                // add cheat sheet link, if required
                if ($cheatsheeturl) {
                    $row[] = reader_cheatsheet_link($cheatsheeturl, $strcheatsheet, $publisher, $book);
                }

                // add this row to the table
                $table->data[] = new html_table_row($row);
            }

            // create the HTML for the table of search results
            if (count($table->data)) {
                $searchresults .= html_writer::table($table);
            }
        } else {
            $searchresults .= html_writer::tag('p', get_string('nosearchresults', 'mod_reader'));
        }
    }
    $output .= html_writer::tag('div', $searchresults, array('id' => 'searchresultsdiv'));

    return $output;
}

/**
 * reader_available_users
 *
 * @param xxx $cmid
 * @param xxx $reader
 * @param xxx $userid
 * @param xxx $action
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_available_users($cmid, $reader, $userid, $action='') {
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
            if ($has_groups) {
                $prefix = $strgrouping.': ';
            } else {
                $prefix = '';
            }
            if ($members = groups_get_grouping_members($gid)) {
                $options["grouping$gid"] = $prefix.format_string($grouping->name).' ('.count($members).' users)';
            }
        }

        foreach ($groups as $gid => $group) {
            if ($members = groups_get_members($gid)) {
                if ($has_groupings) {
                    $prefix = $strgroup.': ';
                } else {
                    $prefix = '';
                }
                $options["group$gid"] = $prefix.format_string($group->name).' ('.count($members).' users)';
            }
        }

        $count = count($options);

        if ($count==1) {
            $gid = 0;
        } else if ($count==1) {
            list($gid, $option) = each($options);
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
                if ($gids && array_key_exists($gid, $gids) && ($members = groups_get_grouping_members($gid))) {
                    $userids = array_keys($members);
                }
            } else {
                $gids = groups_get_all_groups($reader->course);
                $gid = intval(substr($gid, 5));
                if ($gids && array_key_exists($gid, $gids) && ($members = groups_get_members($gid))) {
                    $userids = array_keys($members);
                }
            }
        } else if ($gid=='' || $gid=='all') {
            if ($userids = $DB->get_records('reader_attempts', array('reader' => $reader->id), 'userid', 'DISTINCT userid')) {
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

                reader_format_users_fullname($users);
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
 * reader_cheatsheet_init
 *
 * @param xxx $action
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_cheatsheet_init($action) {
    global $CFG;

    $cheatsheeturl = '';
    $strcheatsheet = '';

    // if there is a "cheatsheet" script, make it available (for developer site admins only)
    if ($action=='takequiz' && has_capability('moodle/site:config', reader_get_context(CONTEXT_SYSTEM))) {
        if (file_exists($CFG->dirroot.'/mod/reader/admin/tools/print_cheatsheet.php')) {
            $cheatsheeturl = $CFG->wwwroot.'/mod/reader/admin/tools/print_cheatsheet.php';
            $strcheatsheet = get_string('cheatsheet', 'mod_reader');
        }
    }

    return array($cheatsheeturl, $strcheatsheet);
}

/**
 * reader_cheatsheet_link
 *
 * @param xxx $cheatsheeturl
 * @param xxx $strcheatsheet
 * @param xxx $publisher
 * @param xxx $book
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_cheatsheet_link($cheatsheeturl, $strcheatsheet, $publisher, $book) {
    $url = new moodle_url($cheatsheeturl, array('publishers' => $publisher, 'books' => $book->id));
    $params = array('href' => $url, 'onclick' => "this.target='cheatsheet'; return true;");
    return html_writer::tag('small', html_writer::tag('a', $strcheatsheet, $params));
}

/**
 * reader_format_users_fullname
 *
 * @param string $users (passed by reference)
 * @return void but may update firstname and lastname values in $users array
 * @todo Finish documenting this function
 */
function reader_format_users_fullname(&$users) {
    foreach ($users as $user) {
        $user->firstname = preg_replace('/\b[a-z]/e', 'strtoupper("$0")', strtolower($user->firstname));
        $user->lastname = strtoupper($user->lastname);
    }
}

/**
 * reader_get_new_uniqueid
 *
 * @param integer $contextid
 * @param integer $quizid
 * @param string $defaultbehavior (optional, default='deferredfeedback')
 * @param string $modulename (optional, default='reader')
 * @return integer (unique) id from "question_usages" or "question_attempts"
 * @todo Finish documenting this function
 */
function reader_get_new_uniqueid($contextid, $quizid, $defaultbehavior='deferredfeedback', $modulename='reader') {
    global $DB;
    static $tablename = null;

    // set name of table whose "id" will be used as the "uniqueid"
    //     Moodle == 2.0 : question_attempts
    //     Moodle >= 2.1 : question_usages

    if ($tablename===null) {
        $dbman = $DB->get_manager();
        switch (true) {

            // Moodle >= 2.1
            case $dbman->table_exists('question_usages'):
                $tablename = 'question_usages';
                break;

            // Moodle == 2.0
            case $dbman->table_exists('question_attempts') && $dbman->field_exists('question_attempts', 'modulename'):
                $tablename = 'question_attempts';
                break;

            default: $tablename = ''; // shouldn't happen !!
        }
    }

    // Moodle >= 2.1
    if ($tablename=='question_usages') {
        if (! $behaviour = $DB->get_field('quiz', 'preferredbehaviour', array('id' => $quizid))) {
            $behaviour = $defaultbehavior;
        }
        $record = (object)array('contextid' => $contextid,
                                'component' => 'mod_'.$modulename,
                                'preferredbehaviour' => $behaviour);
        return $DB->insert_record($tablename, $record);
    }

    // Moodle 2.0
    if ($record=='question_attempts') {
        $question_attempt = (object)array('modulename' => $modulename);
        return $DB->insert_record($tablename, $record);
    }

    return 0; // shouldn't happen !!
}

////////////////////////////////////////////////////////////////////////////////
// Navigation API                                                             //
////////////////////////////////////////////////////////////////////////////////

/**
 * Extends the global navigation tree by adding reader nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the reader module instance
 * @param stdclass $course
 * @param stdclass $module
 * @param cm_info  $cm
 */
function reader_extend_navigation(navigation_node $readernode, stdclass $course, stdclass $module, cm_info $cm) {
    global $CFG, $DB, $USER;

    if (reader_can_viewreports($cm->id, $USER->id)) {
        require_once($CFG->dirroot.'/mod/reader/locallib.php');

        //////////////////////////
        // Reports sub-menu
        //////////////////////////

        $icon = new pix_icon('i/report', '');
        $type = navigation_node::TYPE_SETTING;

        $label = get_string('reports');
        $node = $readernode->add($label, null, $type, null, null, $icon);

        //$modes = array('usersummary', 'userdetailed', 'groupsummary', 'booksummary', 'bookdetailed');
        $modes = mod_reader::get_modes('admin/reports', 'filters');
        foreach ($modes as $mode) {
            $url = new moodle_url('/mod/reader/admin/reports.php', array('id' => $cm->id, 'mode' => $mode));
            $label = get_string('report'.$mode, 'mod_reader');
            $node->add($label, $url, $type, null, null, $icon);
        }
    }

    if (reader_can_manageattempts($cm->id, $USER->id)) {
        require_once($CFG->dirroot.'/mod/reader/locallib.php');

        //////////////////////////
        // Attempts sub-menu
        //////////////////////////

        $icon = new pix_icon('t/grades', '');
        $type = navigation_node::TYPE_SETTING;

        $label = get_string('attempts', 'mod_reader');
        $node = $readernode->add($label, null, $type, null, null, $icon);

        $actions = array('deleteattempts', 'awardextrapoints', 'detectcheating');
        foreach ($actions as $action) {
            $params = array('id' => $cm->id, 'action' => $action);
            $url = new moodle_url('/mod/reader/admin/attempts.php', $params);
            $label = get_string($action, 'mod_reader');
            $node->add($label, $url, $type, null, null, $icon);
        }
    }
}

/**
 * Extends the settings navigation with the Reader settings

 * This function is called when the context for the page is a reader module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav {@link settings_navigation}
 * @param navigation_node $readernode {@link navigation_node}
 */
function reader_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $readernode) {
    global $CFG, $PAGE, $USER;

    $nodes = array();

    // create book nodes
    if (reader_can_managebooks($PAGE->cm->id, $USER->id)) {
        require_once($CFG->dirroot.'/mod/reader/admin/books/renderer.php');
        require_once($CFG->dirroot.'/mod/reader/admin/books/download/downloader.php');

        //////////////////////////
        // Books sub-menu
        //////////////////////////

        $type = navigation_node::TYPE_SETTING;

        // books node
        $key    = 'readerbooks';
        $text   = get_string('books', 'mod_reader');
        $node   = new navigation_node(array('type'=>$type, 'key'=>$key, 'text'=>$text));

        // edit node
        $tab = mod_reader_admin_books_renderer::TAB_BOOKS_EDIT;
        $mode = 'edit';
        $params = array('id' => $PAGE->cm->id, 'tab' => $tab, 'mode' => $mode);
        $url = new moodle_url('/mod/reader/admin/books.php', $params);
        $key = 'editbookdetails';
        $text = get_string($mode, 'mod_reader');
        $icon = new pix_icon('t/edit', '');
        reader_navigation_add_node($node, $type, $key, $text, $url, $icon);

        // download (with quizzes) node
        $tab = mod_reader_admin_books_renderer::TAB_BOOKS_DOWNLOAD_WITH;
        $mode = 'download';
        $type = reader_downloader::BOOKS_WITH_QUIZZES;
        $params = array('id' => $PAGE->cm->id, 'tab' => $tab, 'mode' => $mode, 'type' => $type);
        $url = new moodle_url('/mod/reader/admin/books.php', $params);
        $key = 'downloadbookswithquizzes';
        $text = get_string($key, 'mod_reader');
        $icon = new pix_icon('t/download', '');
        reader_navigation_add_node($node, $type, $key, $text, $url, $icon);

        // download (without quizzes) node
        $tab = mod_reader_admin_books_renderer::TAB_BOOKS_DOWNLOAD_WITHOUT;
        $mode = 'download';
        $type = reader_downloader::BOOKS_WITHOUT_QUIZZES;
        $params = array('id' => $PAGE->cm->id, 'tab' => $tab, 'mode' => $mode, 'type' => $type);
        $url = new moodle_url('/mod/reader/admin/books.php', $params);
        $key = 'downloadbookswithoutquizzes';
        $text = get_string($key, 'mod_reader');
        $icon = new pix_icon('t/download', '');
        reader_navigation_add_node($node, $type, $key, $text, $url, $icon);

        $nodes[] = $node;
    }

    // create quiz nodes
    if (reader_can_managequizzes($PAGE->cm->id, $USER->id)) {
        require_once($CFG->dirroot.'/mod/reader/admin/quizzes/renderer.php');

        //////////////////////////
        // Quizzes sub-menu
        //////////////////////////

        $type = navigation_node::TYPE_SETTING;
        $icon = new pix_icon('i/navigationitem', '');

        $key    = 'readerquizzes';
        $text   = get_string('modulenameplural', 'quiz');
        $node   = new navigation_node(array('type'=>$type, 'key'=>$key, 'text'=>$text));

        foreach (mod_reader_admin_quizzes_renderer::get_standard_modes() as $mode) {
            $tab = constant('mod_reader_admin_quizzes_renderer::TAB_QUIZZES_'.strtoupper($mode));
            $params = array('id' => $PAGE->cm->id, 'tab' => $tab, 'mode' => $mode);
            $url = new moodle_url('/mod/reader/admin/quizzes.php', $params);
            $key = 'quizzes'.$mode;
            $text = get_string($mode, 'mod_reader');
            reader_navigation_add_node($node, $type, $key, $text, $url, $icon);
        }

        $nodes[] = $node;
    }

    // create user nodes
    if (reader_can_manageusers($PAGE->cm->id, $USER->id)) {
        require_once($CFG->dirroot.'/mod/reader/admin/users/renderer.php');

        //////////////////////////
        // Users sub-menu
        //////////////////////////

        $type = navigation_node::TYPE_SETTING;
        $icon = new pix_icon('i/navigationitem', '');

        $key    = 'readerusers';
        $text   = get_string('users');
        $node   = new navigation_node(array('type'=>$type, 'key'=>$key, 'text'=>$text));

        foreach (mod_reader_admin_users_renderer::get_standard_modes() as $mode) {
            $tab = constant('mod_reader_admin_users_renderer::TAB_USERS_'.strtoupper($mode));
            $params = array('id' => $PAGE->cm->id, 'tab' => $tab, 'mode' => $mode);
            $url = new moodle_url('/mod/reader/admin/users.php', $params);
            $key = 'users'.$mode;
            $text = get_string($mode, 'mod_reader');
            reader_navigation_add_node($node, $type, $key, $text, $url, $icon);
        }

        $nodes[] = $node;
    }

    // add new nodes
    if (count($nodes)) {

        // We want to add the new nodes after the Edit settings node,
        // and before the locally assigned roles node.

        // detect Moodle >= 2.2 (it has an easy way to do what we want)
        if (method_exists($readernode, 'get_children_key_list')) {

            // in Moodle >= 2.2, we can locate the "Edit settings" node
            // by its key and use that as the "beforekey" for the new nodes
            $keys = $readernode->get_children_key_list();
            $i = array_search('modedit', $keys);
            if ($i===false) {
                $i = 0;
            } else {
                $i = ($i + 1);
            }
            if (array_key_exists($i, $keys)) {
                $beforekey = $keys[$i];
            } else {
                $beforekey = null;
            }
            foreach ($nodes as $node) {
                $readernode->add_node($node, $beforekey);
            }

        } else {
            // in Moodle 2.0 - 2.1, we don't have the $beforekey functionality,
            // so instead, we create a new collection of child nodes by copying
            // the current child nodes one by one and inserting our news nodes
            // after the node whose plain url ends with "/course/modedit.php"
            // Note: this would also work on Moodle >= 2.2, but is obviously
            // rather a hack and not the way things should to be done
            $found = false;
            $children = new navigation_node_collection();
            $max_i = ($readernode->children->count() - 1);
            foreach ($readernode->children as $i => $child) {
                $children->add($child);
                if ($found==false) {
                    $action = $child->action->out_omit_querystring();
                    if (($i==$max_i) || substr($action, -19)=='/course/modedit.php') {
                        $found = true;
                        foreach ($nodes as $node) {
                            $children->add($node);
                        }
                    }
                }
            }
            $readernode->children = $children;
        }
    }
}

/**
 * reader_navigation_add_node
 *
 * a wrapper method to offer consistent API to add navigation nodes
 * in Moodle 2.0 and 2.1, we use $node->children->add() method
 * in Moodle >= 2.1, we use the $node->add_node() method instead
 *
 * @param navigation_node $node
 * @param string $text
 * @param moodle_url $action
 * @param string $key
 * @param int $type one of navigation_node::TYPE_xxx
 * @param pix_icon $icon
 * @todo Finish documenting this function
 */
function reader_navigation_add_node(navigation_node $node, $type, $key, $text, $action, $icon) {
    if (method_exists($node, 'add_node')) {
        // Moodle >= 2.1
        $node->add_node(new navigation_node(array('type'=>$type, 'key'=>$key, 'text'=>$text, 'action'=>$action, 'icon'=>$icon)));
    } else {
        // Moodle = 2.0
        $node->children->add(new navigation_node(array('type'=>$type, 'key'=>$key, 'text'=>$text, 'action'=>$action, 'icon'=>$icon)));
    }
}

/**
 * reader_change_to_teacherview
 *
 * @todo Finish documenting this function
 */
function reader_change_to_teacherview() {
    global $DB, $USER;
    $unset = false;
    if (isset($_SESSION['SESSION']->reader_page)) {
        $unset = ($_SESSION['SESSION']->reader_page == 'view');
    }
    if (isset($_SESSION['SESSION']->reader_lasttime)) {
        $unset = ($_SESSION['SESSION']->reader_lasttime < (time() - 300));
    }
    if ($unset) {
        // in admin.php, remove settings coming from view.php
        unset($_SESSION['SESSION']->reader_page);
        unset($_SESSION['SESSION']->reader_lasttime);
        unset($_SESSION['SESSION']->reader_lastuser);
        unset($_SESSION['SESSION']->reader_lastuserfrom);
    }
    if (isset($_SESSION['SESSION']->reader_changetostudentview)) {
        // in view.php, prepare settings going to admin.php
        if ($userid = $_SESSION['SESSION']->reader_changetostudentview) {
            $_SESSION['SESSION']->reader_lastuser = $USER->id;
            $_SESSION['SESSION']->reader_page     = 'view';
            $_SESSION['SESSION']->reader_lasttime = time();
            $_SESSION['SESSION']->reader_lastuserfrom = $userid;
            if ($USER = $DB->get_record('user', array('id' => $userid))) {
                $_SESSION['SESSION']->reader_teacherview = 'teacherview';
                unset($_SESSION['SESSION']->reader_changetostudentview);
                unset($_SESSION['SESSION']->reader_changetostudentviewlink);
            }
        }
    }
}

/**
 * reader_change_to_studentview
 *
 * @param object  $context
 * @param integer $userid
 * @param string  $link
 * @param string  $location
 * @todo Finish documenting this function
 */
function reader_change_to_studentview($userid, $link, $location) {
    global $DB, $USER;
    // in admin.php, prepare settings going to view.php
    $_SESSION['SESSION']->reader_changetostudentview = $USER->id;
    $_SESSION['SESSION']->reader_changetostudentviewlink = $link;
    $_SESSION['USER'] = $DB->get_record('user', array('id' => $userid));
    unset($_SESSION['SESSION']->reader_teacherview);
    header("Location: $location");
    // script will terminate here
}
