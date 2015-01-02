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
        'wordsorpoints'      => '0',
        'minpassgrade'       => '60',
        'questionmark'       => '0',
        'thislevel'          => '6',
        'nextlevel'          => '1',
        'prevlevel'          => '3',
        'bookcovers'         => '1',
        'usecourse'          => '0',
        'iptimelimit'        => '0',
        'levelcheck'         => '1',
        'wordsorpoints'      => '0',
        'showprogressbar'    => '1',
        'checkbox'           => '0',
        'notifycheating'     => '1',
        'editingteacherrole' => '1',
        'update'             => '1',
        'last_update'        => '0',
        'update_interval'    => '604800',
        'cheatedmessage'     => get_string('cheatedmessagedefault', 'mod_reader'),
        'clearedmessage'     => get_string('clearedmessagedefault', 'mod_reader'),
        'serverurl'          => 'http://moodlereader.net/quizbank',
        'serverusername'     => '',
        'serverpassword'     => ''
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

    $reader->password = $reader->requirepassword;
    unset($reader->requirepassword);

    $reader->subnet = $reader->requiresubnet;
    unset($reader->requiresubnet);

    $reader->id = $DB->insert_record('reader', $reader);

    // update calendar events
    reader_update_events_wrapper($reader);

    // update gradebook item
    reader_grade_item_update($reader);

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

    $reader->password = $reader->requirepassword;
    unset($reader->requirepassword);

    $reader->subnet = $reader->requiresubnet;
    unset($reader->requiresubnet);

    // update "stoplevel" field in "reader_levels" table
    if (isset($reader->stoplevel)) {
        $DB->set_field('reader_levels', 'stoplevel', $reader->stoplevel, array('readerid' => $reader->id));
    }

    $DB->update_record('reader', $reader);

    // update calendar events
    reader_update_events_wrapper($reader);

    // update gradebook item
    reader_grade_item_update($reader);

    return $reader->id;
}

/**
 * Update calendar events for a single Reader activity
 * This function is intended to be called just after
 * a Reader activity has been created or edited.
 *
 * @param xxx $reader
 */
function reader_update_events_wrapper($reader) {
    global $DB;
    if ($eventids = $DB->get_records('event', array('modulename'=>'reader', 'instance'=>$reader->id), 'id', 'id')) {
        $eventids = array_keys($eventids);
    } else {
        $eventids = array();
    }
    reader_update_events($reader, $eventids, true);
}

/**
 * reader_update_events
 *
 * @param xxx $reader (passed by reference)
 * @param xxx $eventids (passed by reference)
 * @param xxx $delete
 */
function reader_update_events(&$reader, &$eventids, $delete) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/calendar/lib.php');

    static $stropens = '';
    static $strcloses = '';
    static $maxduration = null;

    // check to see if this user is allowed
    // to manage calendar events in this course
    $capability = 'moodle/calendar:manageentries';
    if (has_capability($capability, reader_get_context(CONTEXT_SYSTEM))) {
        $can_manage_events = true; // site admin
    } else if (has_capability($capability, reader_get_context(CONTEXT_COURSE, $reader->course))) {
        $can_manage_events = true; // course admin/teacher
    } else {
        $can_manage_events = false; // not allowed to add/edit calendar events !!
    }

    // don't check calendar capabiltiies
    // whwne adding or updating events
    $checkcapabilties = false;

    // cache text strings and max duration (first time only)
    if (is_null($maxduration)) {
        $maxeventlength = get_config('mod_reader', 'maxeventlength');
        if ($maxeventlength===null) {
            $maxeventlength = 5; // 5 days is default
        }
        // set $maxduration (secs) from $maxeventlength (days)
        $maxduration = $maxeventlength * 24 * 60 * 60;

        $stropens = get_string('quizopen', 'mod_quiz');
        $strcloses = get_string('quizclose', 'mod_quiz');
    }

    // array to hold events for this reader
    $events = array();

    // only setup calendar events,
    // if this user is allowed to
    if ($can_manage_events) {

        // set duration
        if ($reader->timeclose && $reader->timeopen) {
            $duration = max(0, $reader->timeclose - $reader->timeopen);
        } else {
            $duration = 0;
        }

        if ($duration > $maxduration) {
            // long duration, two events
            $events[] = (object)array(
                'name' => $reader->name.' ('.$stropens.')',
                'eventtype' => 'open',
                'timestart' => $reader->timeopen,
                'timeduration' => 0
            );
            $events[] = (object)array(
                'name' => $reader->name.' ('.$strcloses.')',
                'eventtype' => 'close',
                'timestart' => $reader->timeclose,
                'timeduration' => 0
            );
        } else if ($duration) {
            // short duration, just a single event
            if ($duration < DAYSECS) {
                // less than a day (1:07 p.m.)
                $fmt = get_string('strftimetime');
            } else if ($duration < WEEKSECS) {
                // less than a week (Thu, 13:07)
                $fmt = get_string('strftimedaytime');
            } else if ($duration < YEARSECS) {
                // more than a week (2 Feb, 13:07)
                $fmt = get_string('strftimerecent');
            } else {
                // more than a year (Thu, 2 Feb 2012, 01:07 pm)
                $fmt = get_string('strftimerecentfull');
            }
            $events[] = (object)array(
                'name' => $reader->name.' ('.userdate($reader->timeopen, $fmt).' - '.userdate($reader->timeclose, $fmt).')',
                'eventtype' => 'open',
                'timestart' => $reader->timeopen,
                'timeduration' => $duration,
            );
        } else if ($reader->timeopen) {
            // only an open date
            $events[] = (object)array(
                'name' => $reader->name.' ('.$stropens.')',
                'eventtype' => 'open',
                'timestart' => $reader->timeopen,
                'timeduration' => 0,
            );
        } else if ($reader->timeclose) {
            // only a closing date
            $events[] = (object)array(
                'name' => $reader->name.' ('.$strcloses.')',
                'eventtype' => 'close',
                'timestart' => $reader->timeclose,
                'timeduration' => 0,
            );
        }
    }

    // cache description and visiblity (saves doing it twice for long events)
    if (empty($reader->intro)) {
        $description = '';
    } else {
        $description = $reader->intro;
    }
    $visible = instance_is_visible('reader', $reader);

    foreach ($events as $event) {
        $event->groupid = 0;
        $event->userid = 0;
        $event->courseid = $reader->course;
        $event->modulename = 'reader';
        $event->instance = $reader->id;
        $event->description = $description;
        $event->visible = $visible;
        if (count($eventids)) {
            $event->id = array_shift($eventids);
            $calendarevent = calendar_event::load($event->id);
            $calendarevent->update($event, $checkcapabilties);
        } else {
            calendar_event::create($event, $checkcapabilties);
        }
    }

    // delete surplus events, if required
    // (no need to check capabilities here)
    if ($delete) {
        while (count($eventids)) {
            $id = array_shift($eventids);
            $event = calendar_event::load($id);
            $event->delete();
        }
    }
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
    global $DB;
    $result = true;

    if ($reader = $DB->get_record('reader', array('id' => $id))) {
        if ($attempts = $DB->get_records('reader_attempts', array('readerid' => $id), 'id', 'id,reader')) {
            $ids = array_keys($attempts);
            $DB->delete_records_list('reader_attempt_questions', 'attemptid', $ids);
            $DB->delete_records_list('reader_attempts', 'id',  $ids);
            unset($ids);
        }
        unset($attempts);
        $DB->delete_records('reader_book_instances',    array('readerid' => $id));
        $DB->delete_records('reader_cheated_log',       array('readerid' => $id));
        $DB->delete_records('reader_delays',            array('readerid' => $id));
        $DB->delete_records('reader_grades',            array('readerid' => $id));
        $DB->delete_records('reader_goals',             array('readerid' => $id));
        $DB->delete_records('reader_levels',            array('readerid' => $id));
        $DB->delete_records('reader_messages',          array('readerid' => $id));
        $DB->delete_records('reader_strict_users_list', array('readerid' => $id));
        $DB->delete_records('reader', array('id' => $id));
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
    return '';
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

/*
 * This function defines what log actions will be selected from the Moodle logs
 * and displayed for course -> report -> activity module -> HotPot -> View OR All actions
 *
 * Note: This is not used by new logging system. Event with
 *       crud = 'r' and edulevel = LEVEL_PARTICIPATING will
 *       be considered as view action.
 *
 * @return array(string) of text strings used to log HotPot view actions
 */
function reader_get_view_actions() {
    return array('view', 'index');
}

/*
 * This function defines what log actions will be selected from the Moodle logs
 * and displayed for course -> report -> activity module -> Hot Potatoes Quiz -> Post OR All actions
 *
 * Note: This is not used by new logging system. Event with
 *       crud = ('c' || 'u' || 'd') and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array(string) of text strings used to log HotPot post actions
 */
function reader_get_post_actions() {
    return array('attemptsubmitted,');
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
    global $CFG, $DB, $PAGE;

    // delete expired messages
    $select = 'timefinish > ? AND timefinish < ?';
    $params = array(0, time());
    $DB->delete_records_select('reader_messages', $select, $params);

    $time = time();
    $name = 'last_update';
    $send_usage_stats = false;
    if ($last_update = get_config('mod_reader', $name)) {
        if (($last_update + (4 * WEEKSECS)) <= $time) {
            set_config($name, $time, 'mod_reader');
            $send_usage_stats = true;
        }
    }

    if ($send_usage_stats) {
        set_config($name, $time, 'mod_reader');

        // get remotesite classes
        require_once($CFG->dirroot.'/mod/reader/admin/books/download/remotesite.php');
        require_once($CFG->dirroot.'/mod/reader/admin/books/download/remotesite/moodlereadernet.php');

        // create an object to represent main download site (moodlereader.net)
        $remotesite = new reader_remotesite_moodlereadernet(get_config('mod_reader', 'serverurl'),
                                                            get_config('mod_reader', 'serverusername'),
                                                            get_config('mod_reader', 'serverpassword'));

        if ($results = $remotesite->send_usage_stats()) {

            // $results is actually an object, but we can
            // loop through the properties using foreach

            // $readerids = array();
            // foreach ($results as $itemid => $image) {
            //     list($action, $image) = explode('::', $image, 2);
            //     if ($action=='UPDATE') {
            //         if ($books = $DB->get_records('reader_books', array('image' => $image))) {
            //             list($where, $params) = $DB->get_in_or_equal(array_keys($books));
            //             $select = 'r.usecourse, MIN(r.id) as minreaderid';
            //             $from   = '{reader_book_instances} rbi '.
            //                       'JOIN {reader} r ON rbi.readerid = r.id';
            //             $where  = 'rbi.bookid '.$where;
            //             $group  = 'r.usecourse';
            //             if ($instances = $DB->get_records_sql("SELECT $select FROM $from WHERE $where GROUP BY $group", $params)) {
            //                 foreach ($instances as $instance) {
            //                     $readerid = $instance->minreaderid;
            //                     if (empty($itemids[$readerid])) {
            //                         $readerids[$readerid] = array();
            //                     }
            //                     $readerids[$readerid][] = $itemid;
            //                 }
            //             }
            //         }
            //     }
            // }

            // if (count($readerids)) {

            //     // get download and renderer classes
            //     require_once($CFG->dirroot.'/mod/reader/locallib.php');
            //     require_once($CFG->dirroot.'/mod/reader/admin/books/download/lib.php');
            //     require_once($CFG->dirroot.'/mod/reader/admin/books/download/renderer.php');

            //     $type   = reader_downloader::BOOKS_WITH_QUIZZES;
            //     foreach ($readerids as $readerid => $itemids) {

            //         $reader = $DB->get_record('reader', array('id' => $readerid));
            //         $cm     = get_coursemodule_from_instance('reader', $reader->id);
            //         $course = $DB->get_record('course', array('id' => $reader->course));
            //         $reader = mod_reader::create($reader, $cm, $course);

            //         $output = $PAGE->get_renderer('mod_reader', 'admin_books_download');
            //         $output->init($reader);

            //         $downloader = new reader_downloader($output);
            //         $downloader->add_remotesite($remotesite);
            //         $downloader->add_selected_itemids($type, $itemids);
            //     }
            // }
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
            'userid'         => $userid,
            'readerid'       => $reader->id,
            'startlevel'     => 0,
            'currentlevel'   => 0,
            'allowpromotion' => 1,
            'stoplevel'      => $reader->stoplevel,
            'goal'           => $reader->goal,
            'time'           => time(),
        );
        if (! $level->id = $DB->insert_record('reader_levels', $level)) {
            // oops record could not be added - shouldn't happen !!
        }
    }

    $select = 'ra.*, rb.difficulty, rb.id AS bookid';
    $from   = '{reader_attempts} ra JOIN {reader_books} rb ON ra.bookid = rb.id';
    $where  = 'ra.userid = ? AND ra.readerid = ? AND ra.deleted = ? AND ra.timefinish > ?';
    $params = array($USER->id, $reader->id, 0, $reader->ignoredate);

    if ($attempts = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY ra.timemodified", $params)) {
        foreach ($attempts as $attempt) {

            $difficulty = reader_get_reader_difficulty($reader, $attempt->bookid, $attempt->difficulty);
            switch (true) {

                case ($difficulty == ($level->currentlevel - 1)):
                    // previous level
                    if ($level->currentlevel < $level->startlevel) {
                        $count['prev'] = -1;
                    } else if ($level->time < $attempt->timefinish) {
                        $count['prev'] += 1;
                    }
                    break;

                case ($difficulty == $level->currentlevel):
                    // current level
                    if (strtolower($attempt->passed)=='true') {
                        $count['this'] += 1;
                    }
                    break;

                case ($difficulty == ($level->currentlevel + 1)):
                    // next level
                    if ($level->time < $attempt->timefinish) {
                        $count['next'] += 1;
                    }
                    break;
            }
        }
    }

    // if this is the highest allowed level, then disable the "allowpromotion" switch
    if ($level->stoplevel > 0 && $level->stoplevel <= $level->currentlevel) {
        $DB->set_field('reader_levels', 'allowpromotion', 0, array('readerid' => $reader->id, 'userid' => $USER->id));
        $level->allowpromotion = 0;
    }

    if ($level->allowpromotion==0) {
        $count['this'] = 1;
    }

    // promote this student, if they have done enough quizzes at this level
    if ($count['this'] >= $reader->thislevel) {
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
        'currentlevel'  => $level->currentlevel,                // current level of this user
        'prevlevel'   => $reader->prevlevel - $count['prev'], // number of quizzes allowed at previous level
        'thislevel'   => $reader->thislevel - $count['this'], // number of quizzes allowed at current level
        'nextlevel'   => $reader->nextlevel - $count['next']  // number of quizzes allowed at next level
    );
    if ($level->currentlevel==0 || $count['prev'] == -1) {
        $leveldata['prevlevel'] = -1;
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

    $select = 'readerid = ? AND userid = ?';
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

    $params = array('readerid' => $reader->id, 'userid' => $USER->id, 'attempt' => ($attemptnumber - 1));
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
            'readerid' => $reader->id,
            'userid'   => $USER->id,
            'bookid'   => $book->id,
            'quizid'   => $book->quizid,
            'preview'  => 0,
            'layout'   => reader_repaginate($reader->questions)
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
 * @param xxx $perpage (optional, default=1)
 * @param xxx $shuffle (optional, default=false)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_repaginate($layout, $perpage=1, $shuffle=false) {
    $questions = explode(',', $layout);
    $questions = array_filter($questions); // remove blanks
    if ($shuffle) {
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
    if ($grade = $DB->get_record('reader_grades', array('readerid' => $reader->id, 'userid' => $userid))) {
        $grade->grade = $bestgrade;
        $grade->timemodified = time();
        if (! $DB->update_record('reader_grades', $grade)) {
            notify('Could not update best grade');
            return false;
        }
    } else {
        $grade = stdClass();
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
    require_once($CFG->dirroot.'/lib/gradelib.php');

    if ($reader===null) {

        // set up sql strings
        $strupdating = get_string('updatinggrades', 'mod_reader');
        $select = 'r.*, cm.idnumber AS cmidnumber';
        $from   = '{reader} r, {course_modules} cm, {modules} m';
        $where  = 'r.id = cm.instance AND cm.module = m.id AND m.name = ?';
        $params = array('reader');

        // get previous record index (if any)
        $configname = 'update_grades';
        $configvalue = get_config('mod_reader', $configname);
        if (is_numeric($configvalue)) {
            $i_min = intval($configvalue);
        } else {
            $i_min = 0;
        }

        if ($i_max = $DB->count_records_sql("SELECT COUNT('x') FROM $from WHERE $where", $params)) {
            if ($rs = $DB->get_recordset_sql("SELECT $select FROM $from WHERE $where", $params)) {
                if (defined('CLI_SCRIPT') && CLI_SCRIPT) {
                    $bar = false;
                } else {
                    $bar = new progress_bar('readerupgradegrades', 500, true);
                }
                $i = 0;
                foreach ($rs as $reader) {

                    // update grade
                    if ($i >= $i_min) {
                        upgrade_set_timeout(); // apply for more time (3 mins)
                        reader_update_grades($reader, $userid, $nullifnone);
                    }

                    // update progress bar
                    $i++;
                    if ($bar) {
                        $bar->update($i, $i_max, $strupdating.": ($i/$i_max)");
                    }

                    // update record index
                    if ($i > $i_min) {
                        set_config($configname, $i, 'mod_reader');
                    }
                }
                $rs->close();
            }
        }

        // delete the record index
        unset_config($configname, 'mod_reader');

        return; // finish here
    }

    // sanity check on $reader->id
    if (! isset($reader->id)) {
        return false;
    }

    if ($grades = reader_get_grades($reader, $userid)) {
        reader_grade_item_update($reader, $grades);

    } else if ($userid && $nullifnone) {
        // no grades for this user, but we must force the creation of a "null" grade record
        reader_grade_item_update($reader, (object)array('userid'=>$userid, 'rawgrade'=>null));

    } else {
        // no grades and no userid
        reader_grade_item_update($reader);
    }
}

/**
 * reader_reset_gradebook
 *
 * @param xxx $courseid
 * @param xxx $type (optional, default = "")
 * @return void
 * @todo Finish documenting this function
 */
function reader_reset_gradebook($courseid, $type='') {
    global $DB;
    $select = 'q.*, cm.idnumber as cmidnumber, q.course as courseid';
    $from   = '{modules} m '.
              'JOIN {course_modules} cm ON m.id = cm.module '.
              'JOIN {reader} q ON cm.instance = q.id';
    $where  = 'm.name = ? AND cm.course = ?';
    $params = array('reader', $courseid);
    if ($readers = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params)) {
        foreach ($readers as $reader) {
            $DB->delete_records('reader_attempts', array('readerid' => $id));
            $DB->delete_records('reader_grades',   array('readerid' => $id));
            reader_grade_item_update($reader, 'reset');
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
    if (empty($reader->sumgrades)) {
        return 0;
    } else {
        $precision = ($reader->wordsorpoints==0 ? 0 : 1);
        return round($rawgrade * $reader->grade / $reader->sumgrades, $precision);
    }
}

/**
 * reader_get_grades
 *
 * @uses $CFG
 * @uses $DB
 * @param xxx $reader
 * @param xxx $userid (optional, default=0)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_grades($reader, $userid=0) {
    global $DB;

    // $select = 'readerid = ?';
    // $params = array($reader->id);
    // $sort   = 'userid';
    // $fields = 'userid, rawgrade, datesubmitted, dategraded';

    // if ($userid) {
    //     $select .= ' AND userid = ?';
    //     $params[] = $userid;
    // }

    // if ($grades = $DB->get_records_select('reader_grades', $select, $params, $sort, $fields)) {
    //     return $grades;
    // }

    // no reader_grade records found, so let's
    // create them by aggregating the reader_attempts

    if ($reader->wordsorpoints==0) {
        $select = 'rb.words';
    } else {
        $select = 'rb.points';
    }
    $select = 'ra.userid, '.
              'ra.readerid, '.
              'SUM('.$select.') AS rawgrade, '.
              'MAX(timefinish) AS datesubmitted, '.
              'MAX(timemodified) AS dategraded';
    $from   = '{reader_attempts} ra '.
              'JOIN {reader_books} rb ON ra.bookid = rb.id';
    $where  = 'ra.readerid = ? AND ra.passed = ? AND ra.deleted = ? AND ra.preview = ? AND ra.timefinished >= ?';
    $group  = 'ra.userid, ra.readerid';
    $params = array($reader->id, 0, 0, 'true', $reader->ignoredate);

    if ($userid) {
        $where .= ' AND ra.userid = ?';
        $params[] = $userid;
    }

    if ($gradeids = $DB->get_records('reader_grades', array('readerid' => $reader->id), 'id', 'id,readerid')) {
        $gradeids = array_keys($gradeids);
    } else {
        $gradeids = array();
    }

    if ($grades = $DB->get_records_sql("SELECT $select FROM $from WHERE $where GROUP BY $group", $params)) {
        foreach ($grades as $grade) {
            // reuse grade ids if possible
            if (count($gradeids)) {
                $grade->id = array_shift($gradeids);
                $DB->update_record('reader_grades', $grade);
                unset($grade->id);
            } else {
                unset($grade->id);
                $DB->insert_record('reader_grades', $grade);
            }

            unset($grade->readerid);
            $grades[$grade->userid] = $grade;
        }
    } else {
        $grades = array();
    }

    // remove unused grade ids - usually there shouldn't be any !!
    if (count($gradeids)) {
        list($select, $params) = $DB->get_in_or_equal($gradeids);
        $DB->delete_records_select('reader_grades', $select, $params);
    }

    return $grades;
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
    require_once($CFG->dirroot.'/lib/gradelib.php');

    $params = array(
        'itemname' => $reader->name
    );
    if ($grades==='reset') {
        $params['reset'] = true;
        $grades = null;
    }
    if (isset($reader->cmidnumber)) {
        //cmidnumber may not be always present
        $params['idnumber'] = $reader->cmidnumber;
    }
    if ($reader->goal > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $reader->goal;
        $params['grademin']  = 0;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
        // Note: when adding a new activity, a gradeitem will *not*
        // be created in the grade book if gradetype==GRADE_TYPE_NONE
        // A gradeitem will be created later if gradetype changes to GRADE_TYPE_VALUE
        // However, the gradeitem will *not* be deleted if the activity's
        // gradetype changes back from GRADE_TYPE_VALUE to GRADE_TYPE_NONE
        // Therefore, we force the removal of empty gradeitems
        $params['deleted'] = true;
    }
    return grade_update('mod/reader', $reader->course, 'mod', 'reader', $reader->id, 0, $grades, $params);
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

    $select = 'ra.id, ra.uniqueid, ra.readerid, ra.userid, ra.bookid, ra.quizid, ra.attempt, ra.deleted, '.
              'ra.sumgrades, ra.percentgrade, ra.passed, ra.checkbox, ra.timefinish, ra.preview, ra.bookrating, '.
              'rb.name, rb.publisher, rb.level, rb.length, rb.image, rb.difficulty, rb.words, rb.sametitle';
    $from   = '{reader_attempts} ra LEFT JOIN {reader_books} rb ON ra.bookid = rb.id';
    $where  = 'ra.userid = :userid AND ra.deleted = :deleted AND ra.timefinish > :ignoredate AND ra.preview = :preview';
    $order  = 'ra.timefinish';
    $params = array('userid'=>$userid, 'deleted' => 0, 'ignoredate'=>$ignoredate, 'preview' => 0);
    if (! $allreaders) {
        $where .= ' AND ra.readerid = :readerid';
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
                                          'words'         => intval($attempt->words),
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

    $params = array('userid' => $USER->id, 'readerid' => $reader->id);
    if ($record = $DB->get_record('reader_levels', $params)) {
        $goal = $record->goal;
        $currentlevel = $record->currentlevel;
    } else {
        $goal = 0;
        $currentlevel = 0;
        $record = (object)array(
            'userid'         => $USER->id,
            'readerid'       => $reader->id,
            'startlevel'     => 0,
            'currentlevel'   => $currentlevel,
            'stoplevel'      => $reader->stoplevel,
            'allowpromotion' => 1,
            'goal'           => $goal,
            'time'           => time(),
        );
        $record->id = $DB->insert_record('reader_levels', $record);
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
    if ($goal > 1000000) {
        $goal = 1000000;
    }

    if (! $progress) {
        $progress = 0;
    }
    if ($progress > 1000000) {
        $progress = 1000000;
    }

    if ($goal > $progress) {
        $max = $goal;
    } else {
        $max = $progress;
    }

    switch (true) {
        case ($max <= 50000):
            $max = 5;
            $bgcolor = '#00FFFF'; // bright blue
            break;
        case ($max <= 100000):
            $max = 10;
            $bgcolor = '#FF00FF'; // bright purple
            break;
        case ($max <= 250000):
            $max = 25;
            $bgcolor = '#FFFF00'; // yellow
            break;
        case ($max <= 500000):
            $max = 50;
            $bgcolor = '#00FF00'; // green
            break;
        default:
            $max = 100;
            $bgcolor = '#0000FF'; // blue
    }

    $goalpix = $goal / ($max * 10000);
    if ($goalpix > 1) {
        $goalpix = 800;
    } else {
        $goalpix = round($goalpix * 800);
    }

    $markpix = $progress / ($max * 10000);
    if ($markpix > 1) {
        $markpix = 800;
    } else {
        $markpix = round($markpix * 800);
    }
    $markpix += 8;

    $html = '';
    $html .= '<style type="text/css" >'."\n";
    $html .= '#ScoreBoxDiv {'."\n";
    $html .= '    position: absolute;'."\n";
    $html .= '    height:   63px;'."\n";
    $html .= '    left:     5px;'."\n";
    $html .= '    top:      34px;'."\n";
    $html .= '    width:    826px;'."\n";
    $html .= '    z-index:  5;'."\n";
    $html .= '    background-color: '.$bgcolor.' ;'."\n";
    $html .= '}'."\n";
    $html .= 'img.grey {'."\n";
    $html .= '    position: absolute;'."\n";
    $html .= '    left:     10px;'."\n";
    $html .= '    top:      40px;'."\n";
    $html .= '    z-index:  15;'."\n";
    $html .= '}'."\n";
    $html .= 'img.color {'."\n";
    $html .= '    position: absolute;'."\n";
    $html .= '    left:     10px;'."\n";
    $html .= '    top:      40px;'."\n";
    $html .= '    z-index:  20;'."\n";
    $html .= '    clip:     rect(0px '.$markpix.'px 100px 0px);'."\n";
    $html .= '}'."\n";
    $html .= 'img.mark {'."\n";
    $html .= '    position: absolute;'."\n";
    $html .= '    left:     '.($markpix + 10).'px;'."\n";
    $html .= '    top:      47px;'."\n";
    $html .= '    z-index:  20;'."\n";
    $html .= '}'."\n";
    $html .= 'img.goal {'."\n";
    $html .= '    position: absolute;'."\n";
    $html .= '    left:     '.$goalpix.'px;'."\n";
    $html .= '    top:      26px;'."\n";
    $html .= '    z-index:  40;'."\n";
    $html .= '}'."\n";
    $html .= '</style>'."\n";

    $params = array('id' => 'ScoreBoxDiv', 'class' => 'ScoreBoxDiv');
    $html .= html_writer::tag('div', '&nbsp;&nbsp;&nbsp;&nbsp;', $params);

    $url = new moodle_url("/mod/reader/img/colorscale800px{$max}.png");
    $html .= html_writer::img($url, '', array('class' => 'color'));

    $url  = new moodle_url("/mod/reader/img/colorscale800px{$max}gs.png");
    $html .= html_writer::img($url,  '', array('class' => 'grey'));

    $url  = new moodle_url('/mod/reader/img/now.png');
    $html .= html_writer::img($url,  '', array('class' => 'mark'));

    if ($goal) {
        $url  = new moodle_url('/mod/reader/img/goal.png');
        $html .= html_writer::img($url, '', array('class' => 'goal'));
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
 * reader_format_passed
 *
 * @param string $passed
 * @param boolean $fulltext (optional, default=false)
 * @return string
 * @todo Finish documenting this function
 */
function reader_format_passed($passed, $fulltext=false) {
    $passed = strtolower($passed);
    if ($fulltext) {
        switch ($passed) {
            case 'true': return 'Passed'; break;
            case 'false': return 'Failed'; break;
            case 'cheated': return 'Cheated'; break;
        }
    } else {
        switch ($passed) {
            case 'true': return 'P'; break;
            case 'false': return 'F'; break;
            case 'cheated': return 'C'; break;
        }
    }
    return $passed; // shouldn't happen !!
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
 *
 * @param integer $courseid
 * @param string  $module name e.g. "reader"
 * @param string  $action
 * @param string  $url (optional, default='')
 * @param string  $info (optional, default='') often a reader id
 * @param string  $cmid (optional, default=0)
 * @param integer $userid (optional, default=0)
 *
 **************************
    AA-Attempts Deleted
    AA-Book Deleted
    AA-Books status changed
    AA-Change difficulty individual (xxx xxx to xxx)
    AA-Change length (xxx xxx to xxx)
    AA-Change length individual (xxx xxx to xxx)
    AA-Change Student Goal (xxx)
    AA-changeallcurrentlevel userid: xxx, currentlevel=xxx
    AA-changeallstartlevel userid: xxx, startlevel=xxx
    AA-cheated
    AA-excel
    AA-goal userid: xxx, goal=xxx
    AA-Mass changes difficulty (xxx to xxx)
    AA-Mass changes length (xxx to xxx)
    AA-Mass changes level (xxx to xxx)
    AA-Mass changes publisher (xxx to xxx)
    AA-Message Added
    AA-Message Deleted
    AA-Quizzes Added
    AA-reader_deleted_attempts
    AA-reader_restore_attempts
    AA-set passed (uncheated)
    AA-Student check ip Changed (xxx xxx)
    AA-Student Level Changed (xxx xxx to xxx)
    AA-Student NoPromote Changed (xxx set to xxx)
    AA-Student Promotion Stop Changed (xxx set to xxx)
    AA-wordsorpoints goal=xxx
    admin area
    Admin users
    Ajax get list of books
    Ajax get list of users
    attempt
    AWP (userid: xxx; set: xxx->words)
    AWP (userid: xxx; set: xxx)
    Cron
    delete mod
    finish attempt:
    index
    Reader admin index
    view attempt
    view personal page
 **************************
 */
function reader_add_to_log($courseid, $module, $action, $url='', $info='', $cmid=0, $userid=0) {
    global $DB, $PAGE;

    // detect new event API (Moodle >= 2.6)
    if (function_exists('get_log_manager')) {

        // map old $action to new $eventname
        switch ($action) {
            case 'attemptadded':          $eventname = 'attempt_added';         break;
            case 'attemptdeleted':        $eventname = 'attempt_deleted';       break;
            case 'attemptedited':         $eventname = 'attempt_edited';        break;
            case 'attemptsubmitted':      $eventname = 'attempt_submitted';     break;
            case 'bookadded':             $eventname = 'book_added';            break;
            case 'bookdeleted':           $eventname = 'book_deleted';          break;
            case 'bookedited':            $eventname = 'book_edited';           break;
            case 'booksdownloaded':       $eventname = 'books_downloaded';      break;
            case 'course_module_added':   $eventname = 'course_module_added';   break;
            case 'course_module_deleted': $eventname = 'course_module_deleted'; break;
            case 'course_module_edited':  $eventname = 'course_module_edited';  break;
            case 'course_module_viewed':  $eventname = 'course_module_viewed';  break;
            case 'cronrun':               $eventname = 'cron_run';              break;
            case 'downloadsviewed':       $eventname = 'downloads_viewed';      break;
            case 'messageadded':          $eventname = 'message_added';         break;
            case 'messagedeleted':        $eventname = 'message_deleted';       break;
            case 'messageedited':         $eventname = 'message_edited';        break;
            case 'quizadded':             $eventname = 'quiz_added';            break;
            case 'quizdelayset':          $eventname = 'quiz_delay_set';        break;
            case 'quizdeleted':           $eventname = 'quiz_deleted';          break;
            case 'quizedited':            $eventname = 'quiz_edited';           break;
            case 'quizfinished':          $eventname = 'quiz_finished';         break;
            case 'quizselected':          $eventname = 'quiz_selected';         break;
            case 'quizstarted':           $eventname = 'quiz_started';          break;
            case 'reportbookdetailed':    $eventname = 'report_bookdetailed_viewed'; break;
            case 'reportbooksummary':     $eventname = 'report_booksummary_viewed';  break;
            case 'reportgroups':          $eventname = 'report_groups_viewed';       break;
            case 'reportuserdetailed':    $eventname = 'report_userdetailed_viewed'; break;
            case 'reportusersummary':     $eventname = 'report_usersummary_viewed';  break;
            case 'toolrun':               $eventname = 'tool_run';              break;
            case 'usergoalset':           $eventname = 'user_goal_set';         break;
            case 'userlevelset':          $eventname = 'user_level_set';        break;
            case 'usersexported':         $eventname = 'users_exported';        break;
            case 'usersimported':         $eventname = 'users_imported';        break;
            case 'view':                  $eventname = 'course_module_viewed';  break;
            case 'index':                 // legacy $action
            case 'view all':              $eventname = 'course_module_instance_list_viewed'; break;
            default: $eventname = $action;
        }

        $classname = '\\mod_reader\\event\\'.$eventname;
        if (class_exists($classname)) {

            $context = null;
            $course = null;
            $reader = null;
            $params = null;
            $objectid = 0;

            if ($action=='index' || $action=='view all') {
                // course context
                if (isset($PAGE->course) && $PAGE->course->id==$courseid) {
                    // normal Moodle use
                    $context  = $PAGE->context;
                    $course   = $PAGE->course;
                } else if ($courseid) {
                    // Moodle upgrade
                    $context  = reader_get_context(CONTEXT_COURSE, $courseid);
                    $course   = $DB->get_record('course', array('id' => $courseid));
                }
                if ($context) {
                    $params = array('context' => $context);
                }
            } else {
                // course module context
                if (isset($PAGE->cm) && $PAGE->cm->id==$cmid) {
                    // normal Moodle use
                    $objectid = $PAGE->cm->instance;
                    $context  = $PAGE->context;
                    $course   = $PAGE->course;
                    $reader   = $PAGE->activityrecord;
                } else if ($cmid) {
                    // Moodle upgrade
                    $objectid = $DB->get_field('course_modules', 'instance', array('id' => $cmid));
                    $context  = reader_get_context(CONTEXT_MODULE, $cmid);
                    $course   = $DB->get_record('course', array('id' => $courseid));
                    $reader   = $DB->get_record('reader', array('id' => $objectid));
                }
                if ($context && $objectid) {
                    $params = array('context' => $context, 'objectid' => $objectid);
                }
            }

            if ($params) {
                if ($userid) {
                    $params['relateduserid'] = $userid;
                }
                // use call_user_func() to prevent syntax error in PHP 5.2.x
                $event = call_user_func(array($classname, 'create'), $params);
                if ($course) {
                    $event->add_record_snapshot('course', $course);
                }
                if ($reader) {
                    $event->add_record_snapshot('reader', $reader);
                }
                $event->trigger();
            }
        }

    } else if (function_exists('add_to_log')) {
        // Moodle <= 2.5
        add_to_log($courseid, $module, $action, $url, $info, $cmid, $userid);
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
            if ($leveldata['prevlevel'] > 0 && $leveldata['currentlevel'] >= 1) {
                $levels[] = ($leveldata['currentlevel'] - 1);
            }
            if ($leveldata['thislevel'] > 0 && $leveldata['currentlevel'] >= 0) {
                $levels[] = $leveldata['currentlevel'];
            }
            if ($leveldata['nextlevel'] > 0) {
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
                    (empty($book->genre) ? '' : reader_valid_genres($book->genre)),
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
            if ($userids = $DB->get_records('reader_attempts', array('readerid' => $reader->id), 'userid', 'DISTINCT userid')) {
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
    // cancel teacherview
    unset($_SESSION['SESSION']->reader_teacherview);
    // prepare settings going to view.php
    $_SESSION['SESSION']->reader_changetostudentview = $USER->id;
    $_SESSION['SESSION']->reader_changetostudentviewlink = $link;
    $_SESSION['USER'] = $DB->get_record('user', array('id' => $userid));
    header("Location: $location");
    // script will terminate here
}

/**
 * reader_supports
 *
 * @param   integer  $feature a FEATURE_xxx constant
 * @return  boolean  TRUE if reader supports $feature, otherwise FALSE
 */
function reader_supports($feature) {
    switch($feature) {
        case FEATURE_GRADE_HAS_GRADE: return true;
        case FEATURE_GRADE_OUTCOMES:  return true;
        default: return null;
    }
}
