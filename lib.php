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
defined('MOODLE_INTERNAL') || die();

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

////////////////////////////////////////////////////////////////////////////////
// Editing API
////////////////////////////////////////////////////////////////////////////////

/**
 * reader_add_instance
 *
 * @uses $CFG
 * @uses $DB
 * @uses $USER
 * @param xxx $reader
 * @param xxx $mform
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_add_instance(stdclass $reader, $mform) {
    global $CFG, $DB, $USER;

    $reader->timemodified = time();

    $reader->password = $reader->requirepassword;
    unset($reader->requirepassword);

    $reader->subnet = $reader->requiresubnet;
    unset($reader->requiresubnet);

    // add reader record to database
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
 * @uses $USER
 * @param xxx $reader
 * @param xxx $mform
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_update_instance(stdclass $reader, $mform) {

    global $CFG, $DB;

    $reader->timemodified = time();
    $reader->id = $reader->instance;

    $reader->password = $reader->requirepassword;
    unset($reader->requirepassword);

    $reader->subnet = $reader->requiresubnet;
    unset($reader->requiresubnet);

    // update "stoplevel" field in "reader_levels" table
    if (isset($reader->stoplevel) && $reader->stoplevel) {
        if (isset($reader->stoplevelforce) && $reader->stoplevelforce) {
            $DB->set_field('reader_levels', 'stoplevel', $reader->stoplevel, array('readerid' => $reader->id));
        }
    }

    // update reader record in database
    $DB->update_record('reader', $reader);

    // update calendar events
    reader_update_events_wrapper($reader);

    // recalculate grades, if goal or maxgrade have changed
    $goal = $mform->get_originalvalue('goal', $reader->goal);
    $maxgrade = $mform->get_originalvalue('maxgrade', $reader->maxgrade);
    if ($goal==$reader->goal && $maxgrade==$reader->maxgrade) {
        $grades = null; // new or unchanged settings
    } else {
        $grades = reader_get_grades($reader);
    }

    // update gradebook item
    reader_grade_item_update($reader, $grades);

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
        if ($ids = $DB->get_records('reader_attempts', array('readerid' => $id), 'id', 'id,readerid')) {
            $ids = array_keys($ids);
            $DB->delete_records_list('reader_attempt_questions', 'attemptid', $ids);
            $DB->delete_records_list('reader_attempts', 'id',  $ids);
            unset($ids);
        }
        $params = array('readerid' => $id);
        $DB->delete_records('reader_book_instances',    $params);
        $DB->delete_records('reader_cheated_log',       $params);
        $DB->delete_records('reader_rates',            $params);
        $DB->delete_records('reader_grades',            $params);
        $DB->delete_records('reader_goals',             $params);
        $DB->delete_records('reader_levels',            $params);
        $DB->delete_records('reader_messages',          $params);
        $DB->delete_records('reader_strict_users_list', $params);
        $DB->delete_records('reader', array('id' => $id));
    }

    return $result;
}

/**
 * Returns all other caps used in the module
 *
 * @return array
 */
function reader_get_extra_capabilities() {
    return array('moodle/site:accessallgroups');
}

////////////////////////////////////////////////////////////////////////////////
// Grades API
////////////////////////////////////////////////////////////////////////////////

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

    if (empty($reader->goal) || empty($reader->maxgrade)) {
        return array();
    }

    // get grade ids (so we can re-use them)
    $params = array('readerid' => $reader->id);
    if ($userid) {
        $params['userid'] = $userid;
    }
    if ($gradeids = $DB->get_records('reader_grades', $params, 'id', 'id,readerid,userid')) {
        $gradeids = array_keys($gradeids);
    } else {
        $gradeids = array();
    }

    // build SQL to aggregate reader_attempts
    if ($reader->wordsorpoints==0) {
        $select = 'rb.words';
    } else {
        $select = 'rb.points';
    }
    $select = 'ra.userid, '.
              'ra.readerid, '.
              "($reader->maxgrade * (SUM($select) / $reader->goal)) AS rawgrade, ".
              'MAX(timefinish) AS datesubmitted, MAX(timemodified) AS dategraded';
    $from   = '{reader_attempts} ra '.
              'JOIN {reader_books} rb ON ra.bookid = rb.id';
    $where  = 'ra.readerid = ? AND (ra.passed = ? OR ra.passed = ?) AND '.
              'ra.deleted = ? AND ra.preview = ? AND ra.timefinish >= ?';
    $group  = 'ra.userid, ra.readerid';
    $params = array($reader->id, 'true', 'credit', 0, 0, $reader->ignoredate);

    if ($userid) {
        $where .= ' AND ra.userid = ?';
        $params[] = $userid;
    }

    // fetch grades (= reader_attempts aggregates)
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
        $DB->delete_records_select('reader_grades', "id $select", $params);
    }

    return $grades;
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
 * reader_grade_item_update
 *
 * @uses $CFG
 * @uses $DB
 * @uses $USER
 * @param xxx $reader
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
    if ($reader->maxgrade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $reader->maxgrade;
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
 * Delete grade item for given reader
 *
 * @param object $reader object
 * @return object grade_item
 */
function reader_grade_item_delete($reader) {
    return grade_update('mod/reader', $reader->course, 'mod', 'reader', $reader->id, 0, null, array('deleted' => 1));
}

/**
 * reader_scale_used_anywhere
 *
 * @param xxx $scaleid
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_scale_used_anywhere($scaleid) {
    return false;
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
        return round($reader->maxgrade * min(1, $rawgrade / $reader->goal), $precision);
    }
}


////////////////////////////////////////////////////////////////////////////////
// Reset Course API
////////////////////////////////////////////////////////////////////////////////

/**
 * reader_reset_course_form_definition
 *
 * @param xxx $mform (passed by reference)
 * @todo Finish documenting this function
 */
function reader_reset_course_form_definition(&$mform) {
    $plugin = 'mod_reader';
    $mform->addElement('header', 'readerheader', get_string('modulenameplural', $plugin));

    $name = 'ignoredate';
    $label = get_string($name, $plugin);
    $elementname = 'reset_reader_'.$name;
    $mform->addElement('checkbox', $elementname, $label);
    $mform->addHelpButton($elementname, $name, $plugin);
    $mform->setDefault($elementname, 1);

    $name = 'deleteallattempts';
    $label = get_string($name, $plugin);
    $elementname = 'reset_reader_'.$name;
    $mform->addElement('checkbox', $elementname, $label);
    $mform->addHelpButton($elementname, $name, $plugin);

    $name = 'deleterates';
    $label = get_string($name, $plugin);
    $elementname = 'reset_reader_'.$name;
    $mform->addElement('checkbox', $elementname, $label);
    $mform->addHelpButton($elementname, $name, $plugin);
    $mform->disabledIf($elementname, 'reset_reader_deleteallattempts', 'notchecked');

    $name = 'deletegoals';
    $label = get_string($name, $plugin);
    $elementname = 'reset_reader_'.$name;
    $mform->addElement('checkbox', $elementname, $label);
    $mform->addHelpButton($elementname, $name, $plugin);
    $mform->disabledIf($elementname, 'reset_reader_deleteallattempts', 'notchecked');

    $name = 'deletemessages';
    $label = get_string($name, $plugin);
    $elementname = 'reset_reader_'.$name;
    $mform->addElement('checkbox', $elementname, $label);
    $mform->addHelpButton($elementname, $name, $plugin);
    $mform->disabledIf($elementname, 'reset_reader_deleteallattempts', 'notchecked');
}

/**
 * reader_reset_course_form_defaults
 *
 * @param xxx $course
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_reset_course_form_defaults($course) {
    return array('reset_reader_ignoredate'        => 1,
                 'reset_reader_deleteallattempts' => 0,
                 'reset_reader_deleterates'      => 0,
                 'reset_reader_deletegoals'       => 0,
                 'reset_reader_deletemessages'    => 0);
}

/**
 * reader_reset_userdata
 *
 * @uses   $DB
 * @param  object representing reset form $data
 * @return array $status
 * @todo Finish documenting this function
 */
function reader_reset_userdata($data) {
    global $DB;
    $status = array();

    $deleteallattempts = (empty($data->reset_reader_deleteallattempts) ? false : true);
    $deleterates      = (empty($data->reset_reader_deleterates)      ? false : true);
    $deletegoals       = (empty($data->reset_reader_deletegoals)       ? false : true);
    $deletemessages    = (empty($data->reset_reader_deletemessages)    ? false : true);

    // get date to use as "ignoredate" for Reader activities in this course
    if (empty($data->reset_reader_ignoredate)) {
        $ignoredate = 0;
    } else {
        $ignoredate = $data->reset_start_date;
    }

    if ($deleteallattempts || $deleterates || $deletegoals || $deletemessages || $ignoredate) {
        $readers = $DB->get_records('reader', array('course' => $data->courseid), 'id', 'id,course');
    } else {
        $readers = false;
    }

    if ($readers) {
        foreach ($readers as $reader) {
            if ($deleteallattempts) {
                if ($ids = $DB->get_records('reader_attempts', array('readerid' => $reader->id), 'id', 'id,readerid')) {
                    $ids = array_keys($ids);
                    $DB->delete_records_list('reader_attempt_questions', 'attemptid', $ids);
                    $DB->delete_records_list('reader_attempts', 'id',  $ids);
                    unset($ids);
                }
            }
            $params = array('readerid' => $reader->id);
            if ($deleteallattempts) {
                $DB->delete_records('reader_cheated_log',       $params);
                $DB->delete_records('reader_grades',            $params);
                $DB->delete_records('reader_levels',            $params);
                $DB->delete_records('reader_strict_users_list', $params);
            }
            if ($deleterates) {
                $DB->delete_records('reader_rates', $params);
            }
            if ($deletegoals) {
                $DB->delete_records('reader_goals', $params);
            }
            if ($deletemessages) {
                $DB->delete_records('reader_messages', $params);
            }
            if ($ignoredate) {
                $DB->set_field('reader', 'ignoredate', $ignoredate, array('id' => $reader->id));
            }
        }

        // if grade reset was not requested via the form,
        // it must be done here
        if (empty($data->reset_gradebook_grades)) {
            reader_reset_gradebook($data->courseid, 'reset');
        }

        $plugin = 'mod_reader';
        $pluginname =  get_string('modulenameplural', $plugin);

        if ($deleteallattempts) {
            $status[] = array('component' => $pluginname, 'item' => get_string('deleteallattempts', $plugin), 'error' => false);
        }
        if ($deleterates) {
            $status[] = array('component' => $pluginname, 'item' => get_string('deleterates', $plugin), 'error' => false);
        }
        if ($deletegoals) {
            $status[] = array('component' => $pluginname, 'item' => get_string('deletegoals', $plugin), 'error' => false);
        }
        if ($deletemessages) {
            $status[] = array('component' => $pluginname, 'item' => get_string('deletemessages', $plugin), 'error' => false);
        }
        if ($ignoredate) {
            $status[] = array('component' => $pluginname, 'item' => get_string('ignoredate', $plugin), 'error' => false);
        }
    }

    return $status;
}

/**
 * Removes all grades from gradebook
 *
 * @global stdClass
 * @global object
 * @param int $courseid
 * @param string optional type
 */
function reader_reset_gradebook($courseid, $type='') {
    global $DB;
    $sql = 'SELECT r.*, r.course as courseid, cm.idnumber as cmidnumber '.
             'FROM {reader} r, {course_modules} cm, {modules} m '.
            'WHERE m.name = ? AND m.id = cm.module AND cm.instance = r.id AND r.course = ?';
    if ($readers = $DB->get_records_sql($sql, array('reader', $courseid))) {
        foreach ($readers as $reader) {
            reader_grade_item_update($reader, $type);
        }
    }
}

////////////////////////////////////////////////////////////////////////////////
// Reports API
////////////////////////////////////////////////////////////////////////////////

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
 * Given a course and a time, this module should find recent activity
 * that has occurred in reader activities and print it out.
 * The output appears on the course page in the "Recent activity" block
 *
 * @uses $CFG
 * @uses $DB
 * @uses $OUTPUT
 * @param stdclass $course
 * @param boolean  $viewfullnames
 * @param integer  $timestart
 * @return boolean TRUE if there was output, otherwise FALSE
 */
function reader_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $DB, $OUTPUT;

    //TODO: use timestamp in approved field instead of these constants
    if (! defined('READER_RECENT_ACTIVITY_LIMIT')) {
        define('READER_RECENT_ACTIVITY_LIMIT', 20);
    }
    if (! defined('READER_RECENT_ACTIVITY_TEXTLENGTH')) {
        define('READER_RECENT_ACTIVITY_TEXTLENGTH', 16);
    }

    // for testing, subtract one year from the start time
    //$timestart -= (52 * WEEKSECS);

    $ids = array();
    $modinfo = get_fast_modinfo($course);
    foreach ($modinfo->cms as $cm) {
        if ($cm->modname=='reader' && $cm->uservisible) {
            $cmids[$cm->instance] = $cm->id;
        }
    }
    if (empty($cmids)) {
        return false;
    }

    if (class_exists('user_picture')) {
        // Moodle >= 2.6
        $userfields = user_picture::fields('u', null, 'useruserid');
    } else {
        // Moodle <= 2.5
        $userfields ='u.firstname,u.lastname,u.picture,u.imagealt,u.email';
    }

    $context = reader_get_context(CONTEXT_COURSE, $course->id);
    if ($students = get_users_by_capability($context, 'mod/reader:viewbooks', 'u.id,u.id', 'u.id', '', '', 0, '', false)) {
        $students = array_keys($students);
        if ($managers = get_users_by_capability($context, 'mod/reader:viewreports', 'u.id,u.id', 'u.id', '', '', 0, '', false)) {
            $managers = array_keys($managers);
            $students = array_diff($students, $managers);
        }
    }
    if (empty($students)) {
        return false;
    }
    list($userfilter, $userparams) = $DB->get_in_or_equal($students);
    unset($students, $managers);

    $select = 'ra.*, rb.publisher, rb.level, rb.name AS bookname, '.$userfields;
    $from   = '{reader_attempts} ra '.
              'JOIN {reader_books} rb ON ra.bookid = rb.id '.
              'JOIN {user} u ON ra.userid = u.id';
    list($where, $params) = $DB->get_in_or_equal(array_keys($cmids));
    $where  = "ra.readerid $where AND u.id $userfilter AND ra.timemodified > ? AND ra.deleted <> ? AND rb.hidden <> ?";
    $params = array_merge($params, $userparams, array($timestart, 1, 1));
    $order  = 'ra.readerid, u.lastname, u.firstname, ra.timemodified DESC';

    $attempts = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $order", $params);
    if (empty($attempts)) {
        return false;
    }

    // start "reader_recent_activity" div
    echo html_writer::start_tag('div', array('class' => 'reader_recent_activity'));

    // heading
    $text = get_string('newreaderattempts', 'mod_reader').':';
    echo html_writer::tag('h3', $text);

    $count = 0;
    $currentuserid = 0;
    $currentreaderid = 0;
    $countattempts = count($attempts);
    $dateformat = get_string('strftimerecent');

    foreach ($attempts as $attempt) {
        $userid = $attempt->userid;
        $readerid = $attempt->readerid;

        if ($currentreaderid==$readerid && $currentuserid==$userid) {
            // same reader and user - do nothing
        } else {
            // new reader or user, so ...
            if ($currentuserid) {
                echo html_writer::end_tag('ul'); // finish booklist
                echo html_writer::end_tag('li'); // finish user
            }
            if ($currentreaderid != $readerid) {
                if ($currentreaderid) {
                    echo html_writer::end_tag('ul'); // finish userlist
                    echo html_writer::end_tag('li'); // finish reader
                } else {
                    // start readerlist
                    echo html_writer::start_tag('ul', array('class' => 'readerlist'));
                }

                // start this reader
                echo html_writer::start_tag('li');

                // link to reader
                $cmid = $cmids[$readerid];
                $href = new moodle_url('/mod/reader/view.php', array('id' => $cmid));
                $text = format_string($modinfo->cms[$cmid]->name);
                $text = html_writer::tag('a', $text, array('href' => $href));
                $text = get_string('modulename', 'mod_reader').': '.$text;
                echo $text;

                // start userlist
                echo html_writer::start_tag('ul', array('class' => 'userlist'));
                $currentreaderid = $readerid;
            }

            // start user
            echo html_writer::start_tag('li');

            // link to user
            $href = new moodle_url('/user/view.php', array('id' => $userid, 'course' => $course->id));
            $text = fullname($attempt, $viewfullnames);

            $text = html_writer::tag('a', $text, array('href' => $href));
            echo get_string('user').': '.$text;

            // start booklist
            echo html_writer::start_tag('ul', array('class' => 'booklist'));
            $currentuserid = $userid;
        }

        // attempt date and book name
        $text = $attempt->bookname;
        if (reader_textlib('strlen', $text) > READER_RECENT_ACTIVITY_TEXTLENGTH) {
            $text = reader_textlib('substr', $text, 0, READER_RECENT_ACTIVITY_TEXTLENGTH - 3).'...';
        }
        $text = userdate($attempt->timemodified, $dateformat).': '.$text;
        echo html_writer::tag('li', $text); // a single book

        // finish here if the LIMIT has been reached (but don't leave an orphan attempt)
        $count++;
        if ($count >= READER_RECENT_ACTIVITY_LIMIT && $countattempts > ($count + 1)) {
            break;
        }
    }

    if ($currentreaderid) {
        echo html_writer::end_tag('ul'); // finish book list
        echo html_writer::end_tag('li'); // finish user
        echo html_writer::end_tag('ul'); // finish user list
        echo html_writer::end_tag('li'); // finish reader
        echo html_writer::end_tag('ul'); // finish reader list
    }

    if ($countattempts > $count) {
        $href = new moodle_url('/mod/reader/admin/reports.php', array('id' => $cmid));
        $text = ($countattempts - $count);
        $text = get_string('morenewattempts', 'mod_reader', $text);
        $text = html_writer::tag('a', $text, array('href' => $href));
        $text = html_writer::tag('div', $text, array('class' => 'activityhead'));
        $text = html_writer::tag('div', $text, array('class' => 'head'));
        echo $text;
    }

    // end "reader_recent_activity" div
    echo html_writer::end_tag('div');

    return true;
}

/**
 * This function  returns activity for all readers in a course since a given time.
 * It is initiated from the "Full report of recent activity" link in the "Recent Activity" block.
 * Using the "Advanced Search" page (cousre/recent.php?id=99&advancedfilter=1),
 * results may be restricted to a particular course module, user or group
 *
 * This function is called from: {@link course/recent.php}
 *
 * @param array(object) $activities sequentially indexed array of course module objects
 * @param integer $index length of the $activities array
 * @param integer $timestart start date, as a UNIX date
 * @param integer $courseid id in the "course" table
 * @param integer $coursemoduleid id in the "course_modules" table
 * @param integer $userid id in the "users" table (default = 0)
 * @param integer $groupid id in the "groups" table (default = 0)
 * @return void adds items into $activities and increments $index
 *     for each reader attempt, an $activity object is appended
 *     to the $activities array and the $index is incremented
 *     $activity->type : module type (always "reader")
 *     $activity->defaultindex : index of this object in the $activities array
 *     $activity->instance : id in the "reader" table;
 *     $activity->name : name of this reader
 *     $activity->section : section number in which this reader appears in the course
 *     $activity->content : array(object) containing information about reader attempts to be printed by {@link print_recent_mod_activity()}
 *         $activity->content->attemptid : id in the "reader_quiz_attempts" table
 *         $activity->content->attempt : the number of this attempt at this quiz by this user
 *         $activity->content->score : the score for this attempt
 *         $activity->content->timestart : the server time at which this attempt started
 *         $activity->content->timefinish : the server time at which this attempt finished
 *     $activity->user : object containing user information
 *         $activity->user->userid : id in the "user" table
 *         $activity->user->fullname : the full name of the user (see {@link lib/moodlelib.php}::{@link fullname()})
 *         $activity->user->picture : $record->picture;
 *     $activity->timestamp : the time that the content was recorded in the database
 */
function reader_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $coursemoduleid=0, $userid=0, $groupid=0) {
    global $CFG, $DB, $USER;

    // CONTRIB-4025 don't allow students to see each other's scores
    $coursecontext = reader_get_context(CONTEXT_COURSE, $courseid);
    if (! has_capability('mod/reader:viewbooks', $coursecontext)) {
        return; // can't view recent activity
    }
    if (! has_capability('mod/reader:viewreports', $coursecontext)) {
        $userid = $USER->id; // force this user only (e.g. student)
    }

    // we want to detect Moodle >= 2.4
    // method_exists('course_modinfo', 'get_used_module_names')
    // method_exists('cm_info', 'get_module_type_name')
    // method_exists('cm_info', 'is_user_access_restricted_by_capability')

    $reflector = new ReflectionFunction('get_fast_modinfo');
    if ($reflector->getNumberOfParameters() >= 3) {
        // Moodle >= 2.4 has 3rd parameter ($resetonly)
        $modinfo = get_fast_modinfo($courseid);
        $course  = $modinfo->get_course();
    } else {
        // Moodle <= 2.3
        $course = $DB->get_record('course', array('id' => $courseid));
        $modinfo = get_fast_modinfo($course);
    }
    $cms = $modinfo->get_cms();

    $readers = array(); // readerid => cmid
    $users   = array(); // cmid => array(userids)

    foreach ($cms as $cmid => $cm) {
        if ($cm->modname=='reader' && ($coursemoduleid==0 || $coursemoduleid==$cmid)) {
            // save mapping from readerid => coursemoduleid
            $readers[$cm->instance] = $cmid;
            // initialize array of users who have recently attempted this Reader
            $users[$cmid] = array();
        } else {
            // we are not interested in this mod
            unset($cms[$cmid]);
        }
    }

    if (empty($readers)) {
        return; // no readers
    }

    if (class_exists('user_picture')) {
        // Moodle >= 2.6
        $userfields = user_picture::fields('u', null, 'useruserid');
    } else {
        // Moodle <= 2.5
        $userfields ='u.firstname,u.lastname,u.picture,u.imagealt,u.email';
    }

    $select = 'ra.*, (ra.timemodified - ra.timestart) AS duration, '.
              'rb.publisher, rb.level, rb.name AS bookname, rb.difficulty, '.$userfields;
    $from   = '{reader_attempts} ra '.
              'JOIN {user} u ON ra.userid = u.id '.
              'JOIN {reader_books} rb ON ra.bookid = rb.id';
    list($where, $params) = $DB->get_in_or_equal(array_keys($readers));
    $where  = 'ra.readerid '.$where;
    $order  = 'ra.userid, ra.attempt';

    if ($groupid) {
        // restrict search to a users from a particular group
        $from   .= ', {groups_members} gm';
        $where  .= ' AND ra.userid = gm.userid AND gm.id = ?';
        $params[] = $groupid;
    }
    if ($userid) {
        // restrict search to a single user
        $where .= ' AND ra.userid = ?';
        $params[] = $userid;
    }
    if ($timestart) {
        $where .= ' AND ra.timemodified > ?';
        $params[] = $timestart;
    }

    if (! $attempts = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $order", $params)) {
        return; // no recent attempts at these readers
    }

    $userfields = str_replace('u.', '', $userfields);
    $userfields = explode(',', $userfields);
    $userfields = preg_grep('/^[a-z]+$/', $userfields);

    foreach (array_keys($attempts) as $attemptid) {
        $attempt = &$attempts[$attemptid];

        if (! array_key_exists($attempt->readerid, $readers)) {
            continue; // invalid readerid - shouldn't happen !!
        }

        $cmid = $readers[$attempt->readerid];
        $userid = $attempt->userid;
        if (! array_key_exists($userid, $users[$cmid])) {
            $users[$cmid][$userid] = (object)array(
                'id' => $userid,
                'userid' => $userid,
                'attempts' => array()
            );
            foreach ($userfields as $userfield) {
                $users[$cmid][$userid]->$userfield = $attempt->$userfield;
            }
        }
        // add this attempt by this user at this course module
        $users[$cmid][$userid]->attempts[$attempt->attempt] = &$attempt;
    }

    foreach ($cms as $cmid => $cm) {
        if (empty($users[$cmid])) {
            continue;
        }
        // add an activity object for each user's attempts at this reader
        foreach ($users[$cmid] as $userid => $user) {

            // get index of last (=most recent) attempt
            $max_unumber = max(array_keys($user->attempts));

            $options = array('context' => $cm->context);
            if (method_exists($cm, 'get_formatted_name')) {
                $name = $cm->get_formatted_name($options);
            } else {
                $name = format_string($cm->name, true,  $options);
            }

            $activities[$index++] = (object)array(
                'type' => 'reader',
                'cmid' => $cmid,
                'name' => $name,
                'user' => $user,
                'attempts'  => $user->attempts,
                'timestamp' => $user->attempts[$max_unumber]->timemodified
            );
        }
    }
}

/**
 * Print single activity item prepared by {@see reader_get_recent_mod_activity()}
 *
 * This function is called from: {@link course/recent.php}
 *
 * @param object $activity an object created by {@link get_recent_mod_activity()}
 * @param integer $courseid id in the "course" table
 * @param boolean $detail
 *         true : print a link to the reader activity
 *         false : do no print a link to the reader activity
 * @param xxx $modnames
 * @param xxx $viewfullnames
 * @return no return value is required
 */
function reader_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
    global $CFG, $OUTPUT;
    require_once($CFG->dirroot.'/mod/reader/locallib.php');

    static $dateformat = null;
    if (is_null($dateformat)) {
        $dateformat = get_string('strftimerecentfull');
    }

    $table = new html_table();
    $table->cellpadding = 3;
    $table->cellspacing = 0;

    if ($detail) {
        $row = new html_table_row();

        $cell = new html_table_cell('&nbsp;', array('width'=>15));
        $row->cells[] = $cell;

        // activity icon and link to activity
        $src = $OUTPUT->pix_url('icon', $activity->type);
        $img = html_writer::empty_tag('img', array('src'=>$src, 'class'=>'icon', 'alt'=>$activity->name));

        // link to activity
        $href = new moodle_url('/mod/reader/view.php', array('id' => $activity->cmid));
        $link = html_writer::link($href, $activity->name);

        $cell = new html_table_cell("$img $link");
        $cell->colspan = 9;
        $row->cells[] = $cell;

        $table->data[] = $row;
    }


    $row = new html_table_row();

    // set rowspan to (number of attempts) + 1
    $rowspan = count($activity->attempts) + 1;

    $cell = new html_table_cell('&nbsp;', array('width'=>15));
    $cell->rowspan = $rowspan;
    $row->cells[] = $cell;

    $picture = $OUTPUT->user_picture($activity->user, array('courseid'=>$courseid));
    $cell = new html_table_cell($picture, array('width'=>35, 'valign'=>'top', 'class'=>'forumpostpicture'));
    $cell->rowspan = $rowspan;
    $row->cells[] = $cell;

    $href = new moodle_url('/user/view.php', array('id'=>$activity->user->userid, 'course'=>$courseid));
    $cell = new html_table_cell(html_writer::link($href, fullname($activity->user)));
    $cell->colspan = 8;
    $row->cells[] = $cell;

    $table->data[] = $row;

    foreach ($activity->attempts as $attempt) {
        if (empty($attempt->duration)) {
            $duration = '&nbsp;';
        } else {
            $duration = '('.format_time($attempt->duration).')';
        }

        $href = new moodle_url('/mod/reader/admin/report.php', array('id'=>$attempt->id));
        $link = html_writer::link($href, userdate($attempt->timemodified, $dateformat));

        switch ($attempt->passed) {
            case 'true':
                $passed = get_string('passed', 'mod_reader');
                $class = 'passed';
                break;
            case 'credit':
                $passed = get_string('credit', 'mod_reader');
                $class = 'passed';
                break;
            case 'credit':
            default:
                $passed = get_string('failed', 'mod_reader');
                $class = 'failed';
        }
        $passed = html_writer::tag('span', $passed, array('class' => $class));

        $readinglevel = get_string('readinglevelshort', 'mod_reader', $attempt->difficulty);

        $table->data[] = new html_table_row(array(
            new html_table_cell($attempt->publisher),
            new html_table_cell($attempt->level),
            new html_table_cell($attempt->bookname),
            new html_table_cell($readinglevel),
            new html_table_cell($attempt->percentgrade.'%'),
            new html_table_cell($passed),
            new html_table_cell($link),
            new html_table_cell($duration)
        ));
    }

    echo html_writer::table($table);
}

/*
 * For the given list of courses, this function creates an HTML report
 * of which Reader activities have been completed and which have not

 * This function is called from: {@link course/lib.php}
 *
 * @param array(object) $courses records from the "course" table
 * @param array(array(string)) $htmlarray array, indexed by courseid, of arrays, indexed by module name (e,g, "reader), of HTML strings
 *     each HTML string shows a list of the following information about each open Reader in the course
 *         Reader name and link to the activity  + open/close dates, if any
 *             for teachers:
 *                 how many students have attempted/completed the Reader
 *             for students:
 *                 which Readers have been completed
 *                 which Readers have not been completed yet
 *                 the time remaining for incomplete Readers
 * @return no return value is required, but $htmlarray may be updated
 */
function reader_print_overview($courses, &$htmlarray) {
    global $CFG, $DB, $USER;
    require_once($CFG->dirroot.'/mod/reader/locallib.php');

    if (! isset($courses) || ! is_array($courses) || ! count($courses)) {
        return; // no courses
    }

    if (! $readers = get_all_instances_in_courses('reader', $courses)) {
        return; // no readers
    }

    $str = null;
    $now = time();
    foreach ($readers as $reader) {

        // check this reader is open, and is not yet closed
        if ($reader->timeopen > $now || $reader->timeclose < $now) {
            continue;
        }

        // cache some lang strings (first time only)
        if ($str===null) {
            $str = (object)array(
                'modulename'   => get_string('modulename', 'mod_reader'),
                'countactive'  => get_string('countactive', 'mod_reader'),
                'duedate'      => get_string('duedate', 'scorm'), // OR assign(ment)
                'attempts'     => get_string('attempts', 'mod_reader'),
                'averagegrade' => get_string('gradeaverage', 'quiz'),
                'dateformat'   => get_string('strftimedaydatetime'),
                'credit'       => get_string('credit', 'mod_reader'),
                'failed'       => get_string('failed', 'mod_reader'),
                'goal'         => get_string('goal', 'mod_reader'),
                'grade'        => get_string('grade'),
                'passed'       => get_string('passed', 'mod_reader'),
                'points'       => get_string('points', 'mod_reader'),
                'reader'       => get_string('modulename', 'mod_reader'),
                'status'       => get_string('status'),
                'timeclose'    => get_string('availabletodate', 'data'),
                'timeopen'     => get_string('availablefromdate', 'data'),
                'words'        => get_string('words', 'mod_reader')
            );
        }

        // start main div for this Reader activity
        $html = html_writer::start_tag('div', array('class' => 'overview'));

        // Reader activity name
        $params = array('href'  => new moodle_url('/mod/reader/view.php', array('id' => $reader->coursemodule)),
                        'title' => $str->reader,
                        'class' => ($reader->visible ? '' : 'dimmed'));
        $text = format_string($reader->name);
        $text = $str->modulename.': '.html_writer::tag('a', $text, $params);
        $html .= html_writer::tag('div', $text, array('class' => 'name'));

        // date/time open
        //if ($reader->timeopen) {
        //    $text = $str->timeopen.': '.userdate($reader->timeopen, $str->dateformat);
        //    $html .= html_writer::tag('div', $text, array('class' => 'info'));
        //}

        // date/time close
        if ($reader->timeclose) {
            $text = $str->duedate.': '.userdate($reader->timeclose, $str->dateformat);
            $html .= html_writer::tag('div', $text, array('class' => 'info'));
        }

        // details of attempts and grades
        $modulecontext = reader_get_context(CONTEXT_MODULE, $reader->coursemodule);
        if (has_capability('mod/reader:viewreports', $modulecontext)) {
            // manager: show class grades stats
            // attempted: 99, passed: 99 failed: 99
            if ($students = get_users_by_capability($modulecontext, 'mod/reader:viewbooks', 'u.id,u.id', 'u.id', '', '', 0, '', false)) {
                $sumgrade = 0;
                $countactive = 0;
                $countpassed = 0;
                $countcredit = 0;
                $countfailed = 0;
                $countattempts = 0;
                $countstudents = count($students);
                // search reader_attempts for aggregate totals for each student
                list($where, $params) = $DB->get_in_or_equal(array_keys($students));
                $select = 'userid, '.
                          'SUM(CASE WHEN passed = ? THEN 1 ELSE 0 END) AS countpassed, '.
                          'SUM(CASE WHEN passed = ? THEN 1 ELSE 0 END) AS countcredit, '.
                          'SUM(CASE WHEN passed = ? THEN 1 ELSE 0 END) AS countfailed, '.
                          'SUM(percentgrade) AS sumgrade, '.
                          'COUNT(*) AS countattempts';
                array_unshift($params, 'true', 'credit', 'false');
                $from   = '{reader_attempts}';
                $where  = 'userid '.$where.' AND readerid = ?';
                array_push($params, $reader->id);
                if ($attempts = $DB->get_records_sql("SELECT $select FROM $from WHERE $where GROUP BY userid", $params)) {
                    $attempted = count($attempts);
                    foreach ($attempts as $attempt) {
                        $countactive++;
                        $sumgrade += $attempt->sumgrade;
                        $countpassed += $attempt->countpassed;
                        $countcredit += $attempt->countcredit;
                        $countfailed += $attempt->countfailed;
                        $countattempts += $attempt->countattempts;
                    }
                }
                unset($attempts);
                unset($students);

                if ($countactive) {
                    $info = array();
                    if ($countpassed) {
                        $info[] = $str->passed.': '.number_format($countpassed);
                    }
                    if ($countcredit) {
                        $info[] = $str->credit.': '.number_format($countcredit);
                    }
                    if ($countfailed) {
                        $info[] = $str->failed.': '.number_format($countfailed);
                    }
                    $info = implode(', ', $info);
                    $info = array(
                        $str->countactive.': '.$countactive.'/'.$countstudents,
                        $str->attempts.': '.number_format($countattempts)." ($info)",
                        $str->averagegrade.': '.round($sumgrade / $countattempts, 1).'%'
                    );
                    $info = html_writer::alist($info);
                    $html .= html_writer::tag('div', $info, array('class' => 'info'));
                }
            }
        } else {
            // student: show grade and status
            if ($grade = reader_get_grades($reader, $USER->id)) {
                $grade = $grade[$USER->id];
                if ($reader->goal) {
                    $text = $str->goal.': '.number_format($reader->goal);
                    if ($reader->wordsorpoints==0) {
                        $text .= ' '.$str->words;
                    } else {
                        $text .= ' '.$str->points;
                    }
                    $html .= html_writer::tag('div', $text, array('class' => 'info'));
                }
                if ($reader->maxgrade) {
                    $text = round(100 * ($grade->rawgrade / $reader->maxgrade)).'%';
                }  else {
                    $text = number_format($grade->rawgrade);
                    if ($reader->wordsorpoints==0) {
                        $text .= ' '.$str->words;
                    } else {
                        $text .= ' '.$str->points;
                    }
                }
                $text = $str->grade.': '.$text;
                $html .= html_writer::tag('div', $text, array('class' => 'info'));
            }
        }
        $html .= html_writer::end_tag('div');

        if (empty($htmlarray[$reader->course]['reader'])) {
            $htmlarray[$reader->course]['reader'] = $html;
        } else {
            $htmlarray[$reader->course]['reader'] .= $html;
        }
    }
}

/*
 * This function defines what log actions will be selected from the Moodle logs
 * and displayed for course -> report -> activity module -> HotPot -> View OR All actions
 *
 * Note: This is not used by new logging system. Events with
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
 * Note: This is not used by new logging system.
 *       Events with crud = ('c' || 'u' || 'd')
 *       and edulevel = LEVEL_PARTICIPATING
 *       will be considered as post action.
 *
 * @return array(string) of text strings used to log HotPot post actions
 */
function reader_get_post_actions() {
    return array('attemptsubmitted,');
}

////////////////////////////////////////////////////////////////////////////////
// Navigation API
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

    if (reader_can('viewreports', $cm->id, $USER->id)) {
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

    if (reader_can('manageattempts', $cm->id, $USER->id)) {
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
    if (reader_can('managebooks', $PAGE->cm->id, $USER->id)) {
        require_once($CFG->dirroot.'/mod/reader/admin/books/renderer.php');
        require_once($CFG->dirroot.'/mod/reader/admin/books/download/downloader.php');

        //////////////////////////
        // Books sub-menu
        //////////////////////////

        $type = navigation_node::TYPE_SETTING;

        // books node
        $key = 'readerbooks';
        $text = get_string('books', 'mod_reader');
        $node = new navigation_node(array('type'=>$type, 'key'=>$key, 'text'=>$text));

        // edit (site) node
        $tab = mod_reader_admin_books_renderer::TAB_BOOKS_EDITSITE;
        $mode = 'editsite';
        $params = array('id' => $PAGE->cm->id, 'tab' => $tab, 'mode' => $mode);
        $url = new moodle_url('/mod/reader/admin/books.php', $params);
        $key = 'books'.$mode;
        $text = get_string($key, 'mod_reader');
        $icon = new pix_icon('t/edit', '');
        reader_navigation_add_node($node, $type, $mode, $text, $url, $icon);

        // edit (course) node
        $tab = mod_reader_admin_books_renderer::TAB_BOOKS_EDITCOURSE;
        $mode = 'editcourse';
        $params = array('id' => $PAGE->cm->id, 'tab' => $tab, 'mode' => $mode);
        $url = new moodle_url('/mod/reader/admin/books.php', $params);
        $key = 'books'.$mode;
        $text = get_string($key, 'mod_reader');
        $icon = new pix_icon('t/edit', '');
        reader_navigation_add_node($node, $type, $key, $text, $url, $icon);

        // download (with quizzes) node
        $tab = mod_reader_admin_books_renderer::TAB_BOOKS_DOWNLOAD_WITH;
        $mode = 'download';
        $type = reader_downloader::BOOKS_WITH_QUIZZES;
        $params = array('id' => $PAGE->cm->id, 'tab' => $tab, 'mode' => $mode, 'type' => $type);
        $url = new moodle_url('/mod/reader/admin/books.php', $params);
        $key = $mode.'bookswithquizzes';
        $text = get_string($key, 'mod_reader');
        $icon = new pix_icon('t/download', '');
        reader_navigation_add_node($node, $type, $key, $text, $url, $icon);

        // download (without quizzes) node
        $tab = mod_reader_admin_books_renderer::TAB_BOOKS_DOWNLOAD_WITHOUT;
        $mode = 'download';
        $type = reader_downloader::BOOKS_WITHOUT_QUIZZES;
        $params = array('id' => $PAGE->cm->id, 'tab' => $tab, 'mode' => $mode, 'type' => $type);
        $url = new moodle_url('/mod/reader/admin/books.php', $params);
        $key = $mode.'bookswithoutquizzes';
        $text = get_string($key, 'mod_reader');
        $icon = new pix_icon('t/download', '');
        reader_navigation_add_node($node, $type, $key, $text, $url, $icon);

        $nodes[] = $node;
    }

    // create user nodes
    if (reader_can('manageusers', $PAGE->cm->id, $USER->id)) {
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

    // create user Tools
    if (reader_can('managetools', $PAGE->cm->id, $USER->id) || reader_can('managebooks', $PAGE->cm->id, $USER->id)) {
        require_once($CFG->dirroot.'/mod/reader/admin/tools/renderer.php');

        //////////////////////////
        // Tools sub-menu
        //////////////////////////

        $type = navigation_node::TYPE_SETTING;
        $icon = new pix_icon('i/navigationitem', '');

        $key    = 'readertools';
        $text   = get_string('tools', 'mod_reader');
        $params = array('id' => $PAGE->cm->id, 'tab' => mod_reader_renderer::TAB_TOOLS);

        // show ALL tools in the navigation menu
        // (probably there are too many tools)
        $showalltools = true;

        if ($showalltools) {
            $node = new navigation_node(array('type'=>$type, 'key'=>$key, 'text'=>$text));
            $files = mod_reader_admin_tools_renderer::get_files();
            foreach ($files as $text => $file) {
                $url = new moodle_url($file, $params);
                $key = 'tools'.$text;
                $text = get_string($text, 'mod_reader');
                reader_navigation_add_node($node, $type, $key, $text, $url, $icon);
            }
        } else {
            $url = new moodle_url('/mod/reader/admin/tools.php', $params);
            $node = new navigation_node(array('type'=>$type, 'key'=>$key, 'text'=>$text, 'action' => $url));
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

////////////////////////////////////////////////////////////////////////////////
// Utilities API
////////////////////////////////////////////////////////////////////////////////

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

    // check time that Reader usage stats were last updated
    $time = time();
    $name = 'last_update';
    if ($update = get_config('mod_reader', $name)) {
        $update += (4 * WEEKSECS); // next update
        $send_usage_stats = ($update <= $time);
    } else {
        $send_usage_stats = true; // first time
    }

    // prevent sending of Reader usage stats from developer/test sites
    if (preg_match('/^https?:\/\/localhost/', $CFG->dirroot) && debugging('', DEBUG_DEVELOPER)) {
        $send_usage_stats = false;
    }

    // send usage stats, if necessary
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

            // extract ids of books for which updates are available
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

            // download and install any updated book data
            // if (count($readerids)) {

            //     // get download and renderer classes
            //     require_once($CFG->dirroot.'/mod/reader/locallib.php');
            //     require_once($CFG->dirroot.'/mod/reader/admin/books/download/lib.php');
            //     require_once($CFG->dirroot.'/mod/reader/admin/books/download/renderer.php');

            //     $type = reader_downloader::BOOKS_WITH_QUIZZES;
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
 * reader_supports
 *
 * @param   integer  $feature a FEATURE_xxx constant
 * @return  boolean  TRUE if reader supports $feature, otherwise FALSE
 */
function reader_supports($feature) {
    switch($feature) {
        case FEATURE_GRADE_HAS_GRADE     : return true;
        case FEATURE_GRADE_OUTCOMES      : return true;
        case FEATURE_COMPLETION_HAS_RULES: return true;
        default: return null;
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
 * reader_can
 *
 * @param   string   $capability
 * @param   integer  $cmid
 * @param   integer  $userid
 * @return  boolean  TRUE if current user has $capability
 */
function reader_can($capability, $cmid, $userid) {
    $context = reader_get_context(CONTEXT_MODULE, $cmid);
    return has_capability("mod/reader:$capability", $context, $userid);
}

////////////////////////////////////////////////////////////////////////////////
// Version-independent access to Moodle core API
////////////////////////////////////////////////////////////////////////////////

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
    AA-Change points (xxx xxx to xxx)
    AA-Change points individual (xxx xxx to xxx)
    AA-Change Student Goal (xxx)
    AA-changeallcurrentlevel userid: xxx, currentlevel=xxx
    AA-changeallstartlevel userid: xxx, startlevel=xxx
    AA-cheated
    AA-excel
    AA-goal userid: xxx, goal=xxx
    AA-Mass changes difficulty (xxx to xxx)
    AA-Mass changes points (xxx to xxx)
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
    view (OLD=view personal page)
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
            case 'quizrateset':           $eventname = 'quiz_delay_set';        break;
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

////////////////////////////////////////////////////////////////////////////////
// Reader module speciic functions
////////////////////////////////////////////////////////////////////////////////

/**
 * reader_available_sql
 *
 * @param xxx $cmid
 * @param xxx $reader
 * @param xxx $userid
 * @param xxx $hasquiz (TRUE  : require quizid > 0,
 *                      FALSE : require quizid == 0,
 *                      NULL  : require quizid >= 0)
 * @return array($from, $where, $params)
 * @todo Finish documenting this function
 */
function reader_available_sql($cmid, $reader, $userid, $hasquiz=null) {

    // we don't need any checks for teachers and admins
    if (reader_can('viewallbooks', $cmid, $userid)) {
        $from = '{reader_books} rb';
        if ($hasquiz===true) {
            $where = 'rb.quizid > ?';
        } else if ($hasquiz===false) {
            $where = 'rb.quizid = ?';
        } else {
            $where = 'rb.quizid >= ?';
        }
        $where .= ' AND rb.hidden = ? AND rb.level <> ?';
        $params = array(0, 0, 99);
        if ($reader->bookinstances) {
            $from .= ' JOIN {reader_book_instances} rbi ON rb.id = rbi.bookid';
            $where .= ' AND rbi.readerid = ?';
            $params[] = $reader->id;
        }
        return array($from, $where, $params);
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
    if ($reader->levelcheck == 0) {
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

/**
 * reader_get_level_data
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
    $params = array($USER->id, $reader->id, 0, max($reader->ignoredate, $level->time));

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

    // if this is the highest allowed level,
    // then disable the "allowpromotion" switch
    if ($level->allowpromotion) {
        if ($level->stoplevel > 0 && $level->stoplevel <= $level->currentlevel) {
            $DB->set_field('reader_levels', 'allowpromotion', 0, array('readerid' => $reader->id, 'userid' => $USER->id));
            $level->allowpromotion = 0;
        }
    }

    // promote this student, if required
    if ($level->allowpromotion) {
        if ($reader->thislevel > 0 && $reader->thislevel <= $count['this']) {
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
    } else {
        // if promotion is not allowed, let the student
        // read any number of books at the current level
        $count['this'] = 0;
    }

    // prepare level data
    $leveldata = array(
        'promotiondate'  => $level->time,
        'currentlevel'   => $level->currentlevel,                // current level of this user
        'prevlevel'      => $reader->prevlevel - $count['prev'], // number of quizzes allowed at previous level
        'thislevel'      => $reader->thislevel - $count['this'], // number of quizzes allowed at current level
        'nextlevel'      => $reader->nextlevel - $count['next'], // number of quizzes allowed at next level
        'stoplevel'      => $level->stoplevel,
        'allowpromotion' => $level->allowpromotion
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
function reader_get_user_attempts($readerid, $userid, $status='finished', $includepreviews=false) {
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
 * @return stdClass record from reader_attempts
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
 * reader_repaginate - used by "reader_create_attempt"
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
 * reader_get_student_attempts
 *
 * @uses $CFG
 * @param xxx $reader
 * @param xxx $grades (optional, default=NULL)
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
              'rb.name, rb.publisher, rb.level, rb.points, rb.image, rb.difficulty, rb.words, rb.sametitle';
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

    // these are the grand totals for ALL attempts
    $totals = array();
    $totals['correct']       = 0;
    $totals['incorrect']     = 0;
    $totals['totalpoints']   = 0;
    $totals['countattempts'] = 0;
    $totals['startlevel']    = $level->startlevel;
    $totals['currentlevel']  = $level->currentlevel;

    $totals['points']        = 0; // points awarded for an individual attempt
    $totals['bookpercent']   = 0; // percent awarded for an individual attempt
    $totals['bookmaxgrade']  = 0; // grade awarded for an individual attempt

    $bookid = null;
    $bookpoints = 0;
    $bookdifficulty = 0;

    foreach ($attempts as $attempt) {

        if ($bookid || $bookid==$attempt->bookid) {
            // same book as previous attempt - do nothing
        } else {
            $bookpoints = reader_get_reader_points($reader, $attempt->bookid);
            $bookdifficulty = reader_get_reader_difficulty($reader, $attempt->bookid);
        }

        $totals['countattempts']++;
        if ($attempt->passed == 'true' || $attempt->passed == 'TRUE') {
            $statustext = 'Passed';
            $status = 'correct';
            $totals['points'] = $bookpoints;
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
            $totals['bookpercent']  = round($attempt->percentgrade).'%';
            $totals['bookmaxgrade'] = $totalgrade * $bookpoints;
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
                                          'points'        => $bookpoints,
                                          'publisher'     => $attempt->publisher,
                                          'booklevel'     => $attempt->level,
                                          'bookdiff'      => $bookdifficulty,
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
        if ($returndata[$attemptid]['booklevel']==99) {
            continue; // allow multiple "Extra points"
        }
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
 * reader_get_reader_points
 *
 * @uses $DB
 * @param xxx $reader
 * @param xxx $bookid
 * @param xxx $points (optional, default=0)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_reader_points($reader, $bookid, $points=0) {
    global $DB;

    // "Course-specific quiz selection" is enabled for this reader activity
    if ($reader->bookinstances) {
        if ($instance = $DB->get_record('reader_book_instances', array('readerid' => $reader->id, 'bookid' => $bookid))) {
            return $instance->points;
        }
    }

    // if we already know the points for this book, then use that
    if ($points) {
        return $points;
    }

    // get the book points from the "reader_books" table
    if ($book = $DB->get_record('reader_books', array('id' => $bookid))) {
        return $book->points;
    }

    return 0; // shouldn't happen !!
}

/**
 * Obtains the automatic completion state for this reader
 * based on the conditions in reader settings.
 *
 * @param  object  $course record from "course" table
 * @param  object  $cm     record from "course_modules" table
 * @param  integer $userid id from "user" table
 * @param  bool    $type   of comparison (or/and; used as return value if there are no conditions)
 * @return mixed   TRUE if completed, FALSE if not, or $type if no conditions are set
 */
function reader_get_completion_state($course, $cm, $userid, $type) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/mod/reader/locallib.php');

    // set default return $state
    $state = $type;

    // get the reader record
    if ($reader = $DB->get_record('reader', array('id' => $cm->instance))) {

        $fields = array('completionpass', 'completiontotalwords');
        foreach ($fields as $field) {

            if (empty($reader->$field)) {
                continue;
            }

            switch ($field) {
                case 'completionpass':
                    require_once($CFG->dirroot.'/lib/gradelib.php');
                    $params = array('courseid'     => $course->id,
                                    'itemtype'     => 'mod',
                                    'itemmodule'   => 'reader',
                                    'iteminstance' => $cm->instance);
                    $grade = false;
                    if ($grade_item = grade_item::fetch($params)) {
                        $grades = grade_grade::fetch_users_grades($grade_item, array($userid), false);
                        if (isset($grades[$userid])) {
                            $grade = $grades[$userid];
                        }
                    }
                    $state = ($grade && $grade->is_passed());
                    break;

                case 'completiontotalwords':
                    $select = 'SUM(rb.words)';
                    $from   = '{reader_attempts} ra LEFT JOIN {reader_books} rb ON ra.bookid = rb.id';
                    $where  = 'readerid = ? AND deleted = ? AND passed = ? AND rb.words IS NOT NULL';
                    $params = array($reader->id, 0, 'true');
                    if ($sum = $DB->get_field_sql("SELECT $select FROM $from WHERE $where", $params)) {
                        $state = ($sum > $reader->completiontotalwords);
                    } else {
                        $state = false;
                    }
                    break;
            }

            // finish early if possible
            if ($type==COMPLETION_AND && $state==false) {
                return false;
            }
            if ($type==COMPLETION_OR && $state) {
                return true;
            }
        }
    }

    return $state;
}
