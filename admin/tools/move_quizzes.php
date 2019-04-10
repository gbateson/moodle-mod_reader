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
 * mod/reader/admin/tools/move_quizzes.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once('../../../../config.php');
require_once($CFG->dirroot.'/mod/reader/admin/tools/lib.php');
require_once($CFG->dirroot.'/mod/reader/admin/tools/renderer.php');
require_once($CFG->dirroot.'/mod/reader/locallib.php');

require_login(SITEID);

$id  = optional_param('id',  0, PARAM_INT);
$tab = optional_param('tab', 0, PARAM_INT);
$tool = substr(basename($SCRIPT), 0, -4);

if ($id) {
    $cm = get_coursemodule_from_id('reader', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $reader = $DB->get_record('reader', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $cm = null;
    $course = null;
    $reader = null;
}

$reader = mod_reader::create($reader, $cm, $course);
$reader->req('managebooks');

// set page url
$params = array('id' => $id, 'tab' => $tab);
$PAGE->set_url(new moodle_url("/mod/reader/admin/tools/$tool.php", $params));

// set page title
$title = get_string($tool, 'mod_reader');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');

$output = $PAGE->get_renderer('mod_reader', 'admin_tools');
$output->init($reader);

echo $output->header();
echo $output->tabs();
echo $output->box_start();

$action = optional_param('action', '', PARAM_ALPHA);
switch ($action) {
    case 'move' : reader_move_quizzes($reader); break;
}
reader_print_quizzes_form($reader, $action);

reader_print_continue($id, $tab);

echo $output->box_end();
echo $output->footer();

// ================================
// functions only below this line
// ================================

/**
 * reader_get_quizzes_sql
 *
 * @param xxx $courseid
 * @todo Finish documenting this function
 */
function reader_get_quizzes_sql($courseid) {
    // SQL to extract Reader quizzes in the current course
    $select = 'rb.id AS bookid, rb.publisher, rb.level, rb.name, '.
              'q.id AS quizid, q.course AS courseid, '.
              'cm.id AS cmid, '.
              'm.id AS moduleid, '.
              'ctx.id AS contextid, '.
              'cs.id AS sectionid, cs.section AS sectionnum';
    $from   = '{reader_books} rb '.
              'JOIN {quiz} q ON q.id = rb.quizid '.
              'JOIN {course_modules} cm ON cm.instance = q.id '.
              'JOIN {modules} m ON m.name = ? AND m.id = cm.module '.
              'JOIN {context} ctx ON ctx.contextlevel = ? AND ctx.instanceid = cm.id '.
              'JOIN {course_sections} cs ON cs.id = cm.section';
    $where  = 'rb.quizid > ? AND q.course = ?';
    $params = array('quiz', CONTEXT_MODULE, 0, $courseid);
    return array($select, $from, $where, $params);
}

/**
 * reader_move_quizzes
 *
 * @uses $DB
 * @param xxx $course
 * @param xxx $reader
 * @todo Finish documenting this function
 */
function reader_move_quizzes($reader) {
    global $DB, $USER;

    $oldcourseid = $reader->course->id;
    $newcourseid = $reader->quizzes_course_id();
    $newcoursecontext = $reader->quizzes_course_context();
    if ($reader->can('manageactivities', 'moodle/course', $newcoursecontext)) {
        // extract Reader quizzes in the current course
        list($select, $from, $where, $params) = reader_get_quizzes_sql($oldcourseid);
        $records = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY publisher,level", $params);
    } else {
        $records = false; // shouldn't happen !!
    }

    // get info about Reader quizzes in the current course
    $moved = 0;
    if ($records) {

        $params = array('courseid' => $newcourseid, 'depth' => 1);
        $newgradecategoryid = $DB->get_field('grade_categories', 'id', $params);

        if (empty($newgradecategoryid)) {
            $newgradecategoryid = 0;
            $newgradesortorder  = 0;
        } else {
            $params = array('categoryid' => $newgradecategoryid);
            $newgradesortorder = $DB->get_field('grade_items', 'MAX(sortorder)', $params);
        }

        if (empty($newsortorder)) {
            $newsortorder = 1;
        } else {
            $newsortorder++;
        }

        foreach ($records as $record) {

            // ensure there is a section in the $newcourseid for this quiz
            if ($newsection = reader_get_section($newcourseid, $record)) {

                // transfer quiz
                $params = array('id' => $record->quizid);
                $DB->set_field('quiz', 'course', $newcourseid, $params);

                // transfer course module
                $params = array('id' => $record->cmid);
                $DB->set_field('course_modules', 'course', $newcourseid, $params);
                $DB->set_field('course_modules', 'section', $newsection->id, $params);

                // transfer context
                $params = array('instanceid' => $record->cmid,
                                'contextlevel' => CONTEXT_MODULE);
                $path  = $newcoursecontext->path.'/'.$reader->context->id;
                $depth = $newcoursecontext->depth + 1;
                $DB->set_field('context', 'path',  $path,  $params);
                $DB->set_field('context', 'depth', $depth, $params);

                // transfer grade item
                $params = array('itemtype' => 'mod',
                                'itemmodule' => 'quiz',
                                'iteminstance' => $record->quizid);
                if (empty($newgradecategoryid)) {
                    $DB->delete_records('grade_items', $params);
                } else {
                    $DB->set_field('grade_items', 'courseid',   $newcourseid,         $params);
                    $DB->set_field('grade_items', 'categoryid', $newgradecategoryid,  $params);
                    $DB->set_field('grade_items', 'sortorder',  $newgradesortorder++, $params);
                }

                // remove from $oldsection
                $params = array('id' => $record->sectionid);
                if ($oldsection = $DB->get_record('course_sections', $params)) {
                    $sequence = $oldsection->sequence;
                    $sequence = explode(',', $sequence);
                    $sequence = array_filter($sequence);
                    $i = array_search($record->cmid, $sequence);
                    if (is_numeric($i)) {
                        unset($sequence[$i]);
                    }
                    $oldsection->sequence = implode(',', $sequence);
                    // remove section name, if section is now empty
                    if ($oldsection->sequence=='') {
                        $oldsection->name = '';
                        $oldsection->summary = '';
                    }
                    $DB->update_record('course_sections', $oldsection);
                }

                // add to $newsection
                $sequence = $newsection->sequence;
                $sequence = explode(',', $sequence);
                $sequence = array_filter($sequence);
                $sequence[] = $record->cmid;
                $newsection->sequence = implode(',', $sequence);
                $DB->update_record('course_sections', $newsection);
            }

            if (class_exists('\\core\\event\\course_module_created')) {
                // Moodle >= 2.6
                \core\event\course_module_created::create_from_cm((object)array(
                    'id'       => $record->cmid,
                    'modname'  => 'quiz',
                    'instance' => $record->quizid,
                    'name'     => $record->name,
                ))->trigger();
            } else {
                // Trigger mod_created event with information about this module.
                $event = (object)array(
                    'courseid'   => $newcourseid,
                    'cmid'       => $record->cmid,
                    'modulename' => 'quiz',
                    'name'       => $record->name,
                    'userid'     => $USER->id
                );
                if (function_exists('events_trigger_legacy')) {
                    // Moodle 2.6 - 3.0 ... so not used here anymore
                    events_trigger_legacy('mod_created', $event);
                } else {
                    // Moodle <= 2.5
                    events_trigger('mod_created', $event);
                }
            }

            $moved ++;
        }

        // rebuild_course caches
        rebuild_course_cache($oldcourseid, true);
        rebuild_course_cache($newcourseid, true);
    }

    if ($moved) {
        html_writer::tag('p', get_string('movedquizzes', 'mod_reader', $moved));
    }

    reader_print_all_done();
}

/**
 * reader_get_section
 *
 * @uses $DB
 * @param xxx $courseid
 * @param xxx $book
 * @todo Finish documenting this function
 */
function reader_get_section($courseid, $book) {
    global $DB;

    // some DBs (e.g. MSSQL) cannot compare TEXT fields
    // so we must CAST them to something else (e.g. CHAR)
    $summary = $DB->sql_compare_text('summary');
    $sequence = $DB->sql_compare_text('sequence');

    if ($book->level=='' || $book->level=='--' || $book->level=='No Level') {
        $sectionname = $book->publisher;
    } else {
        $sectionname = $book->publisher.' - '.$book->level;
    }

    // use section with the correct name, if available
    $select = 'course = ? AND (name = ? OR '.$summary.' = ?)';
    $params = array($courseid, $sectionname, $sectionname);
    if ($section = $DB->get_records_select('course_sections', $select, $params, 'section', '*', 0, 1)) {
        $section = reset($section);
        $section->name = $sectionname;
        $DB->update_record('course_sections', $section);
        return $section;
    }

    // reuse an empty section, if available
    $select = 'course = ? AND section > ?'.
              ' AND (name IS NULL OR name = ?)'.
              ' AND (summary IS NULL OR '.$summary.' = ?)'.
              ' AND (sequence IS NULL OR '.$sequence.' = ?)';
    $params = array($courseid, 0, '', '', '');
    if ($section = $DB->get_records_select('course_sections', $select, $params, 'section', '*', 0, 1)) {
        $section = reset($section);
        $section->name = $sectionname;
        $DB->update_record('course_sections', $section);
        return $section;
    }

    // create a new section, if necessary
    $sql = "SELECT MAX(section) FROM {course_sections} WHERE course = ?";
    if ($sectionnum = $DB->get_field_sql($sql, array($courseid))) {
        $sectionnum ++;
    } else {
        $sectionnum = 1;
    }
    $section = (object)array(
        'course'        => $courseid,
        'section'       => $sectionnum,
        'name'          => $sectionname,
        'summary'       => '',
        'summaryformat' => FORMAT_HTML,
        'sequence'      => ''
    );
    $section->id = $DB->insert_record('course_sections', $section);

    if ($sectionnum > reader_get_numsections($courseid)) {
        reader_set_numsections($courseid, $sectionnum);
    }

    return $section;
}

/**
 * reader_print_quizzes_form
 *
 * @param xxx $reader
 * @param xxx $action
 * @todo Finish documenting this function
 */
function reader_print_quizzes_form($reader, $action) {
    global $DB, $PAGE;

    // get SQL to extract Reader quizzes in the current course
    list($select, $from, $where, $params) = reader_get_quizzes_sql($reader->course->id);
    if (! $DB->record_exists_sql("SELECT $select FROM $from WHERE $where", $params)) {
        echo html_writer::tag('p', get_string('noquizzesfound', 'mod_reader'));
        return false;
    }

    if (! $reader->can('manageactivities', 'moodle/course', $reader->quizzes_course_context())) {
        $str = format_string($reader->course->fullname);
        $str = get_string('cannotaccesscourse', 'mod_reader', $str);
        echo html_writer::tag('p', $str);
        return false;
    }

    // start form
    $params = array('method' => 'post', 'action' => $PAGE->url);
    echo html_writer::start_tag('form', $params);
    echo html_writer::start_tag('div');

    // default $action
    if (empty($action)) {
        $action = 'move';
    }

    // prompt
    echo get_string('chooseaction', 'mod_reader').' ';
    echo html_writer::empty_tag('br');

    // actions
    $actions = array('move');
    foreach ($actions as $a) {
        $params = array('type' => 'radio', 'name' => 'action', 'value' => $a);
        if ($action==$a) {
            $params['checked'] = 'checked';
        }
        echo html_writer::empty_tag('input', $params).' ';
        echo get_string($a.'_quizzes', 'mod_reader');
        echo html_writer::empty_tag('br');
    }

    // submit button
    echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('go')));

    // finish form
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('form');
}
