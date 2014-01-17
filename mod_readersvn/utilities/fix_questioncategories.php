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
 * mod/reader/utilities/fix_wrongattempts.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot.'/mod/reader/lib.php');

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
$title = 'Fix question categories';
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo $OUTPUT->box_start();

// get reader course id
if (! $reader_usecourse = get_config('reader', 'usecourse')) {
    $params = array('fullname' => 'Reader Quizzes', 'visible' => 0);
    if (! $reader_usecourse = $DB->get_field('course', 'id', $params)) {
        // look for external "usecourse" in Reader activities
        $select = 'r.id, r.course, r.usecourse, c.id AS courseid, c.visible';
        $from   = '{reader} r LEFT JOIN {course} c ON r.usecourse = c.id';
        $where  = 'r.usecourse IS NOT NULL AND r.course <> r.usecourse AND c.id IS NOT NULL AND c.visible=0';
        $reader_usecourses = array();
        if ($readers = $DB->get_records_sql("SELECT $select FROM $from WHERE $where")) {
            $reader = reset($readers);
            $reader_usecourse = $reader->usecourse;
        } else {
            die('Oops, could not determine readercourse id');
        }
    }
}

// get reader course and context
$coursecontext  = reader_get_context(CONTEXT_COURSE, $reader_usecourse);
$readercourse   = $DB->get_record('course', array('id' => $reader_usecourse));

// get reader course activity contexts
$select         = '(contextlevel = ? AND path = ?) OR (contextlevel = ? AND '.$DB->sql_like('path', '?').')';
$params         = array(CONTEXT_COURSE, $coursecontext->path, CONTEXT_MODULE, $coursecontext->path.'/%');
$modulecontexts = $DB->get_records_select('context', $select, $params);

$endlist = '';

// first we tidy up the reader_question_instances table
$select  = 'question, COUNT(*)';
$from    = '{reader_question_instances}';
$groupby = 'question HAVING COUNT(*) > 1';
$params  = array();
if ($duplicates = $DB->get_records_sql("SELECT $select FROM $from GROUP BY $groupby")) {
    echo '<ul>';
    foreach ($duplicates as $duplicate) {
        if ($instances = $DB->get_records('reader_question_instances', array('question' => $duplicate->question), 'id')) {
            $instanceids = array_keys($instances);
            $instanceid = array_shift($instanceids); // keep this one :-)
            list($select, $params) = $DB->get_in_or_equal($instanceids);
            $DB->delete_records_select('reader_question_instances', 'id '.$select, $params);
            echo '<li><span style="color: red;">DELETE</span> '.count($instanceids).' duplicate question instance(s) (id IN '.implode(', ', $instanceids).')</li>';
        }
    }
    echo '</ul>';
}

// unset all missing parent question ids
// (the "parent" question is the old version of a question that was edited)

$select = 'q1.id, q1.parent';
$from   = '{question} q1 LEFT JOIN {question} q2 ON q1.parent = q2.id';
$where  = 'q1.parent > 0 AND q2.id IS NULL';
if ($questions = $DB->get_records_sql("SELECT $select FROM $from WHERE $where")) {
    list($select, $params) = $DB->get_in_or_equal(array_keys($questions));
    echo '<ul><li><span style="color: brown;">RESET</span> parent ids on '.count($questions).' questions (id  IN '.implode(', ', array_keys($questions)).')</li></ul>';
    $DB->set_field_select('question', 'parent', 0, 'id '.$select, $params);
}

// get question categories for Reader course activities

list($select, $params) = $DB->get_in_or_equal(array_keys($modulecontexts));
if ($categories = $DB->get_records_select('question_categories', 'contextid '.$select, $params)) {

    foreach ($categories as $category) {

        $msg = '';

        // count random and non-random questions
        $random = 0;
        $nonrandom = 0;
        if ($questions = $DB->get_records('question', array('category' => $category->id))) {
            foreach ($questions as $question) {
                if ($question->qtype=='random') {
                    $random++;
                } else {
                    $nonrandom++;
                }
            }
        }

        if ($nonrandom) {
            // category contains at least one non-random quiz
        } else if ($random) {
            // category contains only "random" questions, check if they are used or not
            list($select, $params) = $DB->get_in_or_equal(array_keys($questions));
            if ($DB->count_records_select('reader_question_instances', 'question '.$select, $params)) {
                // at least one questions is used in at least one reader quiz
            } else {
                // questions are NOT used in any reader quizzes
                $DB->delete_records_select('question', 'id '.$select, $params);
                $msg .= '<li><span style="color: red;">DELETE</span> '.$random.' unusable random questions ('.implode(', ', array_keys($questions)).') from category (id='.$category->id.')</li>';
            }
        }

        if ($DB->record_exists('question_categories', array('parent' => $category->id))) {
            $keep = true;  // a parent category
        } else if (substr($category->name, 0, 11)=='Default for') {
            $keep = true;  // an empty parent category
        } else if ($DB->get_records('question', array('category' => $category->id))) {
            $keep = true;  // category contains questions
        } else {
            $keep = false; // empty category
        }

        if ($keep && $category->contextid==$coursecontext->id) {
            // this category is in a course context, but it should NOT be
            // let's see if we can move the questions to a quiz context
            if ($questions = $DB->get_records('question', array('category' => $category->id))) {
                list($select, $params) = $DB->get_in_or_equal(array_keys($questions));
                if ($instances = $DB->get_records_select('reader_question_instances', 'question '.$select, $params)) {
                    // these questions are used in Reader quizzes
                } else if ($instances = $DB->get_records_select('quiz_question_instances', 'question '.$select, $params)) {
                    // these questions are used in Moodle quizzes
                    $quizids = array();
                    foreach ($instances as $instance) {
                        $quizids[$instance->quiz] = true;
                    }
                    $quizids = array_keys($quizids);
                    if (count($quizids)==1) {
                        // move questions to this quiz's context
                        $quizid = reset($quizids);
                        if (! $cm = get_coursemodule_from_instance('quiz', $quizid)) {
                            $msg .= '<li><span style="color: red;">OOPS</span> course module record not found for quizid='.$quizid.'</li>';
                        } else if (! $quizcontext = reader_get_context(CONTEXT_MODULE, $cm->id)) {
                            $msg .= '<li><span style="color: red;">OOPS</span> context record not found for cm id='.$cm->id.'</li>';
                        } else {
                            $DB->set_field('question_categories', 'parent', 0, array('id' => $category->id));
                            $DB->set_field('question_categories', 'contextid', $quizcontext->id, array('id' => $category->id));
                            $msg .= '<li><span style="color: green;">MOVED</span> '.count($questions).' active questions ('.implode(', ', array_keys($questions)).') to new context (id='.$quizcontext->id.', quiz name='.$cm->name.')</li>';
                        }
                    } else {
                        // questions are used by several quizzes
                        $msg .= '<li><span style="color: red;">COULD NOT MOVE</span> '.count($questions).' active questions ('.implode(', ', array_keys($questions)).') because they are used in more than one quiz</li>';
                    }
                } else {
                    // these questions are not used in any quizzes so we can delete them
                    list($select, $params) = $DB->get_in_or_equal(array_keys($questions));
                    $DB->delete_records_select('question', 'id '.$select, $params);
                    $msg .= '<li><span style="color: red;">DELETE</span> '.count($questions).' unused non-random questions ('.implode(', ', array_keys($questions)).') from category (id='.$category->id.')</li>';
                    $keep = false;
                }
            }
        }

        if ($keep) {
            // remove slashes from category name
            if (strpos($category->name, '\\') !== false) {
                $msg .= '<li><span style="color: brown;">FIX</span> slashes in category name: '.$category->name.'</li>';
                $DB->set_field('question_categories', 'name', stripslashes($category->name), array('id' => $category->id));
            }
            // fix case of category name
            if ($category->name=='ordering' || $category->name=='ORDERING' || $category->name=='ORDER') {
                $msg .= '<li><span style="color: brown;">FIX</span> category name: '.$category->name.' =&gt; Ordering</li>';
                $DB->set_field('question_categories', 'name', 'Ordering', array('id' => $category->id));
            }
            // remove slashes from category info
            if (strpos($category->info, '\\') !== false) {
                $msg .= '<li><span style="color: brown;">FIX</span> slashes in category info: '.$category->info.'</li>';
                $DB->set_field('question_categories', 'info', stripslashes($category->info), array('id' => $category->id));
            }
        } else {
            // delete this category
            $msg .= '<li><span style="color: red;">DELETE</span> empty category: '.$category->name.' (id='.$category->id.')</li>';
            $DB->delete_records('question_categories', array('id' => $category->id));
        }

        if ($endlist=='') {
            echo '<ul>';
            $endlist = '</ul>';
        }
        echo $msg;
    }
}

echo $endlist;

echo html_writer::tag('p', 'All done');
echo html_writer::tag('p', html_writer::tag('a', 'Click here to continue', array('href' => $CFG->wwwroot.'/mod/reader/utilities/index.php')));

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
