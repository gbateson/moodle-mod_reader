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
 * mod/reader/admin/tools/redo_upgrade.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once('../../../../config.php');
require_once($CFG->dirroot.'/course/lib.php');
require_once($CFG->dirroot.'/mod/reader/lib.php');

$id  = optional_param('id',  0, PARAM_INT);
$tab = optional_param('tab', 0, PARAM_INT);

require_login(SITEID);
if (class_exists('context_system')) {
    $context = context_system::instance();
} else {
    $context = get_context_instance(CONTEXT_SYSTEM);
}
require_capability('moodle/site:config', $context);

// $SCRIPT is set by initialise_fullme() in 'lib/setuplib.php'
// it is the path below $CFG->wwwroot of this script
$PAGE->set_url($CFG->wwwroot.$SCRIPT);

// set title
$title = get_string('import_reader_tables', 'reader');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');

$time = time();
echo $OUTPUT->header();
echo $OUTPUT->box_start();

$confirm          = optional_param('confirm',          0, PARAM_INT);
$deletecourses    = optional_param('deletecourses',    0, PARAM_INT);
$deletecategories = optional_param('deletecategories', 0, PARAM_INT);

$studentroleid  = $DB->get_field('role',    'id', array('shortname' => 'student'));
$teacherroleid  = $DB->get_field('role',    'id', array('shortname' => 'teacher'));
$readermoduleid = $DB->get_field('modules', 'id', array('name'      => 'reader'));

print_import_tables_form($deletecourses, $deletecategories);

if (! $confirm) {
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
    die;
}

if ($courses = $DB->get_records('course')) {
    asort($courses);
}
if ($readers = $DB->get_records('reader')) {
    asort($readers);
}

if ($categories = $DB->get_records('course_categories')) {
    asort($categories);
    $defaultcategoryid = key($categories);
} else {
    $defaultcategoryid = 0;
}

if ($courses && $deletecourses) {
    $started_list = false;
    foreach ($courses as $course) {

        if ($course->id==SITEID) { // $course->category==0
            continue;
        }

        if ($started_list==false) {
            $started_list = true;
            echo html_writer::start_tag('ul');
        }

        echo html_writer::start_tag('li');
        echo html_writer::tag('span', get_string('coursedeleted', '', $course->shortname));

        delete_course($course, false);

        echo html_writer::end_tag('li');
    }

    if ($started_list==true) {
        echo html_writer::end_tag('ul');
        fix_course_sortorder();
    }
}
unset($courses);

if ($categories && $deletecategories) {

    $started_list = false;
    foreach ($categories as $category) {

        if ($category->id==$defaultcategoryid) {
            continue;
        }

        if ($started_list==false) {
            $started_list = true;
            echo html_writer::start_tag('ul');
        }

        echo html_writer::start_tag('li');
        echo html_writer::tag('span', get_string('deletingcourse', '', get_string('coursecategory').': '.$course->shortname));

        if (class_exists('coursecat')) {
            // Moodle >= 2.5
            $category = coursecat::get($category->id);
            $courses = $category->delete_full(false);
            $categoryname = $category->get_formatted_name();
        } else {
            // Moodle <= 2.4
            $courses = category_delete_full($category, false);
            $categoryname = format_string($category->name);
        }
        foreach($courses as $course) {
            echo $OUTPUT->notification(get_string('coursedeleted', '', $course->shortname), 'notifysuccess');
        }
        echo $OUTPUT->notification(get_string('coursecategorydeleted', '', $categoryname), 'notifysuccess');

        echo html_writer::end_tag('li');
    }

    if ($started_list==true) {
        echo html_writer::end_tag('ul');
    }
}
unset($categories);

// create courses used in "reader" table
if ($readers) {

    $courseids = array();
    foreach ($readers as $reader) {
        if ($reader->usecourse) {
            $courseids[$reader->usecourse] = false;
        }
        if ($reader->course) {
            $courseids[$reader->course] = true;
        }
    }
    ksort($courseids);

    $started_list = false;
    foreach ($courseids as $courseid => $visible) {

        if ($DB->record_exists('course', array('id' => $courseid))) {
            continue;
        }

        if ($started_list==false) {
            $started_list = true;
            echo html_writer::start_tag('ul');
        }

        echo html_writer::start_tag('li');
        echo html_writer::tag('span', get_string('addinganew', '', get_string('course')." ($courseid)"));

        // setup new course
        $coursename = sprintf('Course %02d', $courseid);
        $course = (object)array(
            'id'            => $courseid,
            'category'      => $defaultcategoryid, // crucial !!
            'sortorder'     => 10000 + $courseid,
            'fullname'      => $coursename,
            'shortname'     => $coursename,
            'summary'       => $coursename,
            'summaryformat' => FORMAT_PLAIN, // plain text
            'format'        => 'topics',
            'newsitems'     => 0,
            'startdate'     => time(),
            'visible'       => $visible,
            'numsections'   => 1
        );

        // create new course
        $raw = db_raw_record('course', $course);
        if (! $DB->insert_record_raw('course', $raw, false, false, true)) {
            continue; // could not add course - shouldn't happen !!
        }
        if (array_key_exists('numsections', $raw)) {
            // Moodle <= 2.3
        } else {
            // Moodle >= 2.4
            $params = array('courseid' => $course->id, 'format' => $course->format);
            $DB->set_field('course_format_options', 'value', $course->numsections, $params);
        }

        $context = reader_get_context(CONTEXT_COURSE, $course->id);

        if (function_exists('course_get_format')) {
            course_get_format($course->id)->update_course_format_options($course);
            $course = course_get_format($course->id)->get_course();
        } else {
            $course = $DB->get_record('course', array('id' => $course->id));
        }

        blocks_add_default_course_blocks($course);
        if (function_exists('course_create_sections_if_missing')) {
            // Moodle >= 2.4
            course_create_sections_if_missing($course, 0);
        } else {
            // Moodle <= 2.3
            $section = new stdClass();
            $section->course        = $course->id;   // Create a default section.
            $section->section       = 0;
            $section->summaryformat = FORMAT_HTML;
            $DB->insert_record('course_sections', $section);
        }
        fix_course_sortorder();

        if (class_exists('cache_helper')) {
            // Moodle >= 2.4
            cache_helper::purge_by_event('changesincourse');
        } else {
            // Moodle <= 2.3
            if ($course->restrictmodules) {
                if (isset($course->allowedmodules)) {
                    $allowedmods = $CFG->allowedmodules;
                } else if (isset($CFG->defaultallowedmodules)) {
                    $allowedmods = $CFG->defaultallowedmodules;
                } else {
                    $allowedmods = false;
                }
                if ($allowedmods) {
                    update_restricted_mods($course, $allowedmods);
                }
            }
        }
        if (method_exists($context, 'mark_dirty')) {
            // Moodle >= 2.2
            $context->mark_dirty();
        } else {
            // Moodle <= 2.1
            mark_context_dirty($context->path);
        }
        save_local_role_names($course->id, (array)$course);
        enrol_course_updated(true, $course, $course);

        if (function_exists('events_trigger_legacy')) {
           events_trigger_legacy('course_created', $course);
        } else {
            events_trigger('course_created', $course);
        }

        echo html_writer::end_tag('li');
    }
    if ($started_list==true) {
        echo html_writer::end_tag('ul');
    }

    // create reader activities
    $readermoduleid = 0;
    $started_list = false;
    foreach ($readers as $reader) {

        if ($readermoduleid==0) {
            $readermoduleid = $DB->get_field('modules', 'id', array('name' => 'reader'));
        }

        $params = array('module' => $readermoduleid, 'course' => $reader->course, 'instance' => $reader->id);
        if ($DB->record_exists('course_modules', $params)) {
            continue;
        }

        if ($started_list==false) {
            $started_list = true;
            echo html_writer::start_tag('ul');
        }

        echo html_writer::start_tag('li');
        echo html_writer::tag('span', get_string('addinganew', '', get_string('pluginname', 'reader').": $reader->name"));

        $sql = "SELECT MAX(section) FROM {course_sections} WHERE course = ?";
        if (! $sectionnum = $DB->get_field_sql($sql, array($reader->course))) {
            $sectionnum = 0; // shouldn't happen !!
        }

        // standard fields for adding a new cm
        $reader->instance      = $reader->id;
        $reader->section       = $sectionnum;
        $reader->module        = $readermoduleid;
        $reader->modulename    = 'reader';
        $reader->add           = 'reader';
        $reader->update        = 0;
        $reader->return        = 0;
        $reader->cmidnumber    = '';
        $reader->groupmode     = 0;
        $reader->MAX_FILE_SIZE = 10485760; // 10 GB

        if (! $reader->coursemodule = add_course_module($reader) ) { // $mod
            throw new moodle_exception('Could not add a new course module');
        }
        $reader->id = $reader->coursemodule; // $cmid
        if (function_exists('course_add_cm_to_section')) {
            // Moodle >= 2.4
            $sectionid = course_add_cm_to_section($reader->course, $reader->coursemodule, $sectionnum);
        } else {
            // Moodle <= 2.3
            $sectionid = add_mod_to_section($reader);
        }
        if (! $sectionid) {
            throw new moodle_exception('Could not add the new course module to that section');
        }
        if (! $DB->set_field('course_modules', 'section',  $sectionid, array('id' => $reader->coursemodule))) {
            throw new moodle_exception('Could not update the course module with the correct section');
        }

        // if the section is hidden, we should also hide the new reader activity
        if (! isset($reader->visible)) {
            $reader->visible = $DB->get_field('course_sections', 'visible', array('id' => $sectionid));
        }
        set_coursemodule_visible($reader->coursemodule, $reader->visible);

        // Trigger mod_updated event with information about this module.
        $event = (object)array(
            'courseid'   => $reader->course,
            'cmid'       => $reader->coursemodule,
            'modulename' => $reader->modulename,
            'name'       => $reader->name,
            'userid'     => $USER->id
        );
        events_trigger('mod_updated', $event);

        echo html_writer::end_tag('li');
    }
    if ($started_list==true) {
        echo html_writer::end_tag('ul');
    }
}
unset($readers);

// get name of books table
$dbman = $DB->get_manager();
switch (true) {
    case $dbman->table_exists('reader_publisher'): $booktable = 'reader_publisher'; break;
    case $dbman->table_exists('reader_books'): $booktable = 'reader_books'; break;
    default: $booktable = '';
}

$courseids = array();

// create quizzes used in $booktable
$started_list = false;
if ($books = $DB->get_records($booktable)) {

    $readercourseid = 0;
    $quizmoduleid = 0;
    foreach ($books as $book) {

        if ($DB->record_exists('quiz', array('id' => $book->quizid))) {
            continue;
        }

        if ($started_list==false) {
            $started_list = true;
            echo html_writer::start_tag('ul');
        }
        echo html_writer::start_tag('li');
        echo html_writer::tag('span', 'Adding new quiz for '.$book->name);

        if ($readercourseid==0) {
            if ($readercourseid = get_config('reader', 'usecourse')) {
                if (! $DB->record_exists('course', array('id' => $readercourseid))) {
                    $readercourseid = 0;
                }
            }
        }
        if ($readercourseid==0) {
            $coursename = get_string('defaultcoursename', 'reader');
            $namefields = array('shortname', 'fullname');
            foreach ($namefields as $namefield) {
                if ($readercourseid = $DB->get_records('course', array($namefield => $coursename), 'visible ASC', "id,$namefield")) {
                    $readercourseid = key($readercourseid); // id of first (hidden) course with required coursename
                } else {
                    $readercourseid = 0;
                }
                if ($readercourseid) {
                    break;
                }
            }
        }
        if ($readercourseid==0) {
            // setup new course
            $coursename = get_string('defaultcoursename', 'reader');
            $course = (object)array(
                'category'      => $defaultcategoryid, // crucial !!
                'fullname'      => $coursename,
                'shortname'     => $coursename,
                'summary'       => '',
                'summaryformat' => FORMAT_PLAIN, // plain text
                'format'        => 'topics',
                'newsitems'     => 0,
                'startdate'     => time(),
                'visible'       => 0, // hidden
                'numsections'   => 1
            );

            // create new course
            $course = create_course($course);
            $readercourseid = $course->id;
            set_config('usecourse', $readercourseid, 'reader');
        }

        $courseids[$readercourseid] = true;

        if ($quizmoduleid==0) {
            $quizmoduleid = $DB->get_field('modules', 'id', array('name' => 'quiz'));
        }

        // get section for this publisher - level

        if ($book->level=='' || $book->level=='--' || $book->level=='No Level') {
            $sectionname = $book->publisher;
        } else {
            $sectionname = $book->publisher.' - '.$book->level;
        }

        $summary = $DB->sql_compare_text('summary'); // for MSSQL
        $select = 'course = ? AND (name = ? OR '.$summary.' = ?)';
        $params = array($readercourseid, $sectionname, $sectionname);
        if ($sections = $DB->get_records_select('course_sections', $select, $params, 'section', '*', 0, 1)) {
            $section = reset($sections);
            $sectionnum = $section->section;
        } else {
            $sql = "SELECT MAX(section) FROM {course_sections} WHERE course = ?";
            if ($sectionnum = $DB->get_field_sql($sql, array($readercourseid))) {
                $sectionnum ++;
            } else {
                $sectionnum = 1;
            }
            $section = (object)array(
                'course'        => $readercourseid,
                'section'       => $sectionnum,
                'name'          => $sectionname,
                'summary'       => '',
                'summaryformat' => FORMAT_HTML,
            );
            $section->id = $DB->insert_record('course_sections', $section);
        }

        // create a new quiz

        $quiz = (object)array(
            // standard Quiz fields
            'id'              => $book->quizid,
            'name'            => $book->name,
            'intro'           => ' ',
            'visible'         => 1,
            'introformat'     => FORMAT_HTML, // =1
            'timeopen'        => 0,
            'timeclose'       => 0,
            'preferredbehaviour' => 'deferredfeedback',
            'attempts'        => 0,
            'attemptonlast'   => 1,
            'grademethod'     => 1,
            'decimalpoints'   => 2,
            'reviewattempt'   => 0,
            'reviewcorrectness' => 0,
            'reviewmarks'       => 0,
            'reviewspecificfeedback' => 0,
            'reviewgeneralfeedback'  => 0,
            'reviewrightanswer'      => 0,
            'reviewoverallfeedback'  => 0,
            'questionsperpage' => 0,
            'shufflequestions' => 0,
            'shuffleanswers'  => 1,
            'questions'       => '0,',
            'sumgrades'       => 0, // reset after adding questions
            'grade'           => 100,
            'timecreated'     => time(),
            'timemodified'    => time(),
            'timelimit'       => 0,
            'overduehandling' => '',
            'graceperiod'     => 0,
            'quizpassword'    => '', // should be "password" ?
            'subnet'          => '',
            'browsersecurity' => '',
            'delay1'          => 0,
            'delay2'          => 0,
            'showuserpicture' => 0,
            'showblocks'      => 0,
            'navmethod'       => '',

            // feedback fields (for "quiz_feedback" table)
            //'feedbacktext'    => array_fill(0, 5, array('text' => '', 'format' => 0)),
            //'feedbackboundaries' => array(0 => 0, -1 => 11),
            //'feedbackboundarycount' => 0,

            // these fields may not be necessary in Moodle 2.x
            'adaptive'      => 1,
            'penaltyscheme' => 1,
            'popup'         => 0,

            // standard fields for adding a new cm
            'course'        => $readercourseid,
            'section'       => $sectionnum,
            'module'        => $quizmoduleid,
            'instance'      => $book->quizid,
            'modulename'    => 'quiz',
            'add'           => 'quiz',
            'update'        => 0,
            'return'        => 0,
            'cmidnumber'    => '',
            'groupmode'     => 0,
            'MAX_FILE_SIZE' => 10485760, // 10 GB
        );

        //$quiz->instance = quiz_add_instance($quiz);
        $raw = db_raw_record('quiz', $quiz);
        if (! $DB->insert_record_raw('quiz', $raw, false, false, true)) {
            throw new moodle_exception('Could not add a new quiz record');
        }
        if (! $quiz->coursemodule = add_course_module($quiz) ) { // $mod
            throw new moodle_exception('Could not add a new course module');
        }
        $quiz->id = $quiz->coursemodule; // $cmid
        if (function_exists('course_add_cm_to_section')) {
            // Moodle >= 2.4
            $sectionid = course_add_cm_to_section($readercourseid, $quiz->coursemodule, $sectionnum);
        } else {
            // Moodle <= 2.3
            $sectionid = add_mod_to_section($quiz);
        }
        if (! $sectionid) {
            throw new moodle_exception('Could not add the new course module to that section');
        }
        if (! $DB->set_field('course_modules', 'section',  $sectionid, array('id' => $quiz->coursemodule))) {
            throw new moodle_exception('Could not update the course module with the correct section');
        }

        // if the section is hidden, we should also hide the new quiz activity
        if (! isset($quiz->visible)) {
            $quiz->visible = $DB->get_field('course_sections', 'visible', array('id' => $sectionid));
        }
        set_coursemodule_visible($quiz->coursemodule, $quiz->visible);

        // Trigger mod_updated event with information about this module.
        $event = (object)array(
            'courseid'   => $quiz->course,
            'cmid'       => $quiz->coursemodule,
            'modulename' => $quiz->modulename,
            'name'       => $quiz->name,
            'userid'     => $USER->id
        );
        if (function_exists('events_trigger_legacy')) {
            events_trigger_legacy('mod_updated', $event);
        } else {
            events_trigger('mod_updated', $event);
        }

        echo html_writer::end_tag('li');
    }
    if ($started_list==true) {
        echo html_writer::end_tag('ul');
    }
}

$courseids = array_keys($courseids);
foreach ($courseids as $courseid) {
    // rebuild_course_cache (needed for Moodle 2.0)
    rebuild_course_cache($courseid, true);
}

// create questions used in "reader_question_instances" table
if ($instances = $DB->get_records('reader_question_instances')) {
    foreach ($instances as $instance) {
        // create a new question instance
    }
}

// create users used in "reader_attempts"
if ($attempts = $DB->get_records('reader_attempts', null, 'reader,userid')) {

    $admins = get_admins();

    $firsttime = true;
    $started_list = false;
    $started_uniqieids = false;
    foreach ($attempts as $attempt) {

        if ($firsttime) {
            $reader = null;
        }

        if ($user = $DB->get_record('user', array('id' => $attempt->userid))) {
            $newuser = false;
        } else {
            $newuser = true;
            $user = (object)array(
                'id'           => $attempt->userid,
                'firstaccess'  => 0,
                'lastlogin'    => 0,
                'timecreated'  => $time,
            );
        }

        if (! array_key_exists($attempt->userid, $admins)) {
            $user->username = sprintf('user%04d',   $attempt->userid);
            $user->password = md5($user->username.$CFG->passwordsaltmain);
        }

        $user->firstname    = sprintf('First', $attempt->userid);
        $user->lastname     = sprintf('LAST %04d',  $attempt->userid);
        $user->email        = $user->username.'@localhost';
        $user->city         = 'Kanazawa';
        $user->country      = 'JP';
        $user->description  = 'Hi';
        $user->imagealt     = '';
        $user->confirmed    = 1;
        $user->timemodified = $time;
        $user->mnethostid   = $CFG->mnet_localhost_id;

        if ($newuser) {
            $raw = db_raw_record('user', $user);
            if (! $DB->insert_record_raw('user', $raw, false, false, true)) {
                continue; // could not add user - should not happen !!
            }
        } else {
            if (! $DB->update_record('user', $user)) {
                continue; // could not update user - should not happen !!
            }
        }

        $firsttime = true;
        if ($reader && $reader->id==$attempt->reader) {
            // same $reader, $course, $coursecontext, $enrol
        } else if (! $reader = $DB->get_record('reader', array('id' => $attempt->reader))) {
            continue; // no such reader - shouldn't happen !!
        } else if (! $cm = $DB->get_record('course_modules', array('module' => $readermoduleid, 'instance' => $reader->id))) {
            continue; // no such reader - shouldn't happen !!
        } else if (! $modulecontext = reader_get_context(CONTEXT_MODULE, $cm->id)) {
            continue; // no such context - shouldn't happen !!
        } else if (! $course = $DB->get_record('course', array('id' => $reader->course))) {
            continue; // no such course - shouldn't happen !!
        } else if (! $coursecontext = reader_get_context(CONTEXT_COURSE, $course->id)) {
            continue; // no such context - shouldn't happen !!
        } else if (! $enrol = reader_get_enrol_record($course->id, $studentroleid, $USER->id, $time)) {
            continue; // could not fetch or create enrol record !!
        }
        $firsttime = false;

        $enroluser = false;
        if (reader_create_role_assignment($coursecontext->id, $studentroleid, $attempt->userid, $time)) {
            $enroluser = true;
        }
        if (reader_create_user_enrolment($enrol->id, $attempt->userid, $time)) {
            $enroluser = true;
        }
        $newuniqieid = reader_create_uniqueid($attempt->uniqueid, $modulecontext->id);

        if ($started_list==false && ($newuser || $enroluser || $newuniqieid)) {
            $started_list = true;
            echo html_writer::start_tag('ul');
        }
        if ($newuser) {
            if ($started_uniqieids==true) {
                $started_uniqieids = false;
                echo html_writer::end_tag('li');
            }
            echo html_writer::tag('li', 'Add new user '.$attempt->userid);
        }
        if ($enroluser) {
            if ($started_uniqieids==true) {
                $started_uniqieids = false;
                echo html_writer::end_tag('li');
            }
            echo html_writer::tag('li', 'Enrol student '.$attempt->userid.' in course '.$reader->course);
        }
        if ($newuniqieid) {
            if ($started_uniqieids==false) {
                $started_uniqieids = true;
                echo html_writer::start_tag('li').'Add uniqueid record(s):';
            }
            echo ' '.$attempt->uniqueid;
        }
    }
    if ($started_uniqieids==true) {
        $started_uniqieids = false;
        echo html_writer::end_tag('li');
    }
    if ($started_list==true) {
        echo html_writer::end_tag('ul');
    }
}

// create groups used in "reader_goals"
if ($goals = $DB->get_records('reader_goals', null, 'readerid')) {

    $firsttime = true;
    $started_list = false;
    foreach ($goals as $goal) {

        if ($firsttime) {
            $reader = null;
        }

        $firsttime = true;
        if ($reader && $reader->id==$goal->readerid) {
            // same $reader, $course, $enrol
        } else if (! $reader = $DB->get_record('reader', array('id' => $goal->readerid))) {
            continue; // shouldn't happen !!
        } else if (! $course = $DB->get_record('course', array('id' => $reader->course))) {
            continue; // shouldn't happen !!
        } else if (! $enrol = reader_get_enrol_record($course->id, $studentroleid, $USER->id, $time)) {
            continue;
        }
        $firsttime = false;

        if (reader_create_group($course->id, $goal->groupid, $time)) {
            if ($started_list==false) {
                $started_list = true;
                echo html_writer::start_tag('ul');
            }
            echo html_writer::tag('li', 'Add new group '.$goal->groupid.' in course '.$course->id);

            // set $course groupmode if necessary
            if ($course->groupmode==NOGROUPS) {
                $course->groupmode = VISIBLEGROUPS;
                $DB->update_record('course', $course);
            }
        }

        // to this group, we add all users enrolled as students in this course
        if ($userids = $DB->get_records_menu('user_enrolments', array('enrolid' => $enrol->id), '', 'id,userid')) {
            foreach ($userids as $userid) {
                $params = array('groupid' => $goal->groupid, 'userid' => $userid);
                if (! $DB->record_exists('groups_members', $params)) {
                    $params['timeadded'] = $time;
                    $params['id'] = $DB->insert_record('groups_members', $params);
                }
            }
        }
    }
    if ($started_list==true) {
        echo html_writer::end_tag('ul');
    }
}

// enrol teachers used in "reader_messages"
if ($messages = $DB->get_records('reader_messages', null, 'readerid,teacherid')) {

    $firsttime = true;
    $started_list = false;
    foreach ($messages as $message) {
        $newteacher = false;

        if ($firsttime) {
            $reader = null;
        }

        $firsttime = true;
        if ($reader && $reader->id==$message->readerid) {
            // same $reader, $course, $coursecontext, $enrol
        } else if (! $reader = $DB->get_record('reader', array('id' => $message->readerid))) {
            continue; // shouldn't happen !!
        } else if (! $course = $DB->get_record('course', array('id' => $reader->course))) {
            continue; // shouldn't happen !!
        } else if (! $coursecontext = reader_get_context(CONTEXT_COURSE, $course->id)) {
            continue; // shouldn't happen !!
        } else if (! $enrol = reader_get_enrol_record($course->id, $teacherroleid, $USER->id, $time)) {
            continue; // could not create enrol record !!
        }
        $firsttime = false;

        $newteacher = false;
        if (reader_create_role_assignment($coursecontext->id, $teacherroleid, $message->teacherid, $time)) {
            $newteacher = true;
        }
        if (reader_create_user_enrolment($enrol->id, $message->teacherid, $time)) {
            $newteacher = true;
        }

        if ($newteacher) {
            if ($started_list==false) {
                $started_list = true;
                echo html_writer::start_tag('ul');
            }
            echo html_writer::tag('li', 'Enrol teacher '.$message->teacherid.' in course '.$reader->course);
        }
    }
    if ($started_list==true) {
        echo html_writer::end_tag('ul');
    }
}

// return to Reader tools index page
if ($id) {
    $href = new moodle_url('/mod/reader/admin/tools.php', array('id' => $id, 'tab' => $tab));
} else {
    $href = new moodle_url($CFG->wwwroot.'/');
}
echo html_writer::tag('p', html_writer::tag('a', 'Click here to continue', array('href' => $href)));

echo $OUTPUT->box_end();
echo $OUTPUT->footer();

///////////////////////////////////////////////////////////////////
// functions
///////////////////////////////////////////////////////////////////

/*
 * db_raw_record
 *
 * @param string $table
 * @param string $table
 * @return array
 */
function db_raw_record($table, $record) {
    global $DB;

    $columns = $DB->get_columns($table);
    $cleaned = array();

    foreach ($record as $field => $value) {
        if (isset($columns[$field])) {
            $type = $columns[$field]->meta_type;
            switch (true) {

                case ($field==='id' || is_bool($value)):
                    $value = (int)$value;
                    break;

                case $value==='':
                    if ($type == 'I' || $type == 'F' || $type == 'N') {
                        $value = 0;
                    }
                    break;

                default:
                    if (is_float($value) && ($type == 'C' || $type == 'X')) {
                        $value = "$value";
                    }
            }
            $cleaned[$field] = $value;
        }
    }
    return $cleaned;
}

/*
 * print_import_tables_form
 *
 * @param integer $deletecourses
 * @param integer $deletecategories
 * @return void
 */
function print_import_tables_form($deletecourses, $deletecategories) {
    global $CFG;

    // start form
    $params = array('method' => 'post', 'action' => $CFG->wwwroot.'/mod/reader/admin/tools/import_reader_tables.php');
    echo html_writer::start_tag('form', $params);
    echo html_writer::start_tag('div');

    // hidden "confirm" field
    $params = array('type' => 'hidden', 'name' => 'confirm', 'value' => '1');
    echo html_writer::empty_tag('input', $params);

    // delete courses
    $params = array('type' => 'checkbox', 'name' => 'deletecourses', 'value' => '1');
    if ($deletecourses) {
        $params['checked'] = 'checked';
    }
    echo html_writer::empty_tag('input', $params).' ';
    echo get_string('deletecourses', 'reader');
    echo html_writer::empty_tag('br');

    // delete categories
    $params = array('type' => 'checkbox', 'name' => 'deletecategories', 'value' => '1');
    if ($deletecategories) {
        $params['checked'] = 'checked';
    }
    echo html_writer::empty_tag('input', $params).' ';
    echo get_string('deletecategories', 'reader');
    echo html_writer::empty_tag('br');

    // submit button
    echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('importreadertables', 'reader')));

    // finish form
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('form');
}

/*
 * reader_get_enrol_record
 *
 * @param integer $courseid
 * @param integer $roleid
 * @param integer $userid modifierid for new enrol record
 * @param integer $time
 * @return object or boolean (FALSE)
 */
function reader_get_enrol_record($courseid, $roleid, $userid, $time) {
    global $DB;

    if ($enrol = $DB->get_record('enrol', array('enrol' => 'manual', 'courseid' => $courseid, 'roleid' => $roleid))) {
        return $enrol;
    }

    // create new $enrol record for $roleid in this $course
    $enrol = (object)array(
        'enrol'        => 'manual',
        'courseid'     => $courseid,
        'roleid'       => $roleid,
        'modifierid'   => $userid,
        'timecreated'  => $time,
        'timemodified' => $time
    );

    if ($enrol->id = $DB->insert_record('enrol', $enrol)) {
        return $enrol;
    }

    // could not create enrol record !!
    return false;
}

/*
 * reader_create_role_assignment
 *
 * @param integer $contextid
 * @param integer $roleid
 * @param integer $userid to be assigned a role
 * @param integer $time
 * @return boolean TRUE  if a new role_assignment was created, FALSE otherwise
 */
function reader_create_role_assignment($contextid, $roleid, $userid, $time) {
    global $DB, $USER;
    $params = array('roleid' => $roleid, 'contextid' => $contextid, 'userid' => $userid);
    if ($DB->record_exists('role_assignments', $params)) {
        return false;
    } else {
        // add new role for user in this course
        $params['modifierid'] = $USER->id;
        $params['timemodified'] = $time;
        return $DB->insert_record('role_assignments', $params, false);
    }
}

/*
 * reader_create_role_assignment
 *
 * @param integer $enrolid
 * @param integer $userid to be enrolled
 * @param integer $time
 * @return boolean TRUE if a new role_assignment was created, FALSE otherwise
 */
function reader_create_user_enrolment($enrolid, $userid, $time) {
    global $DB, $USER;
    $params = array('enrolid' => $enrolid, 'userid' => $userid);
    if ($DB->record_exists('user_enrolments', $params)) {
        return false;
    } else {
        // enrol user in this course
        $params['modifierid'] = $USER->id;
        $params['timecreated'] = $time;
        $params['timemodified'] = $time;
        return $DB->insert_record('user_enrolments', $params, false);
    }
}

/*
 * reader_create_uniqueid
 *
 * @param integer $uniqueid
 * @param integer $contextid
 * @param string  $component (optional, default="mod_reader")
 * @param string  $behavior  (optional, default="deferredfeedback")
 * @return boolean TRUE if a new group was created, FALSE otherwise
 */
function reader_create_uniqueid($uniqueid, $contextid, $component='mod_reader', $behavior='deferredfeedback') {
    global $DB;

    static $table = '';
    if ($table=='') {
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('question_usages')) {
            $table = 'question_usages'; // Moodle >= 2.1
        } else {
            $table = 'question_attempts'; // Moodle 2.0
        }
    }

    if ($DB->record_exists($table, array('id' => $uniqueid))) {
        return false;
    }

    $record = (object)array(
        'id'         => $uniqueid,
        'contextid'  => $contextid,
        'component'  => $component,
        'modulename' => substr($component, 4), // Moodle 2.0
        'preferredbehavior' => $behavior,
    );

    $raw = db_raw_record($table, $record);
    return $DB->insert_record_raw($table, $raw, false, false, true);
}

/*
 * reader_create_group
 *
 * @param integer $courseid
 * @param integer $groupid
 * @param integer $time
 * @return boolean TRUE if a new group was created, FALSE otherwise
 */
function reader_create_group($courseid, $groupid, $time) {
    global $DB;

    if ($DB->record_exists('groups', array('id' => $groupid))) {
        return false;
    }

    $groupname = sprintf('Group %02d', $groupid);
    $group = (object)array(
        'id'           => $groupid,
        'courseid'     => $courseid,
        'name'         => $groupname,
        'description'  => $groupname,
        'descriptionformat' => FORMAT_PLAIN,
        'enrolmentkey' => '',
        'timecreated'  => $time,
        'tiemodified'  => $time
    );

    $raw = db_raw_record('groups', $group);
    return $DB->insert_record_raw('groups', $raw, false, false, true);
}
