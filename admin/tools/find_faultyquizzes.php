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
 * mod/reader/admin/tools/fix_wrongattempts.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once('../../../../config.php');
require_once($CFG->dirroot.'/mod/reader/lib.php');
require_once($CFG->dirroot.'/mod/reader/admin/tools/lib.php');

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
$title = get_string('find_faultyquizzes', 'reader');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo $OUTPUT->box_start();

// get reader course ids
$modulecontexts = array();
$courseids = reader_quiz_courseids();
foreach ($courseids as $courseid) {
    // get reader course and context
    $coursecontext = reader_get_context(CONTEXT_COURSE, $courseid);
    $readercourse  = $DB->get_record('course', array('id' => $courseid));

    // get reader course activity contexts
    $select = '(contextlevel = ? AND path = ?) OR (contextlevel = ? AND '.$DB->sql_like('path', '?').')';
    $params = array(CONTEXT_COURSE, $coursecontext->path, CONTEXT_MODULE, $coursecontext->path.'/%');
    if ($contexts = $DB->get_records_select('context', $select, $params)) {
        $modulecontexts += $contexts;
    }
}

$endlist = '';

// get question categories for Reader course activities

list($select, $params) = $DB->get_in_or_equal(array_keys($modulecontexts));
if ($categories = $DB->get_records_select('question_categories', 'contextid '.$select, $params)) {

    // search for quizzes with no correct answer
    $no_correct_answer = array();
    foreach ($categories as $category) {
        if ($questions = $DB->get_records('question', array('category' => $category->id))) {
            foreach ($questions as $question) {
                if ($question->qtype=='description' || ($question->qtype=='multichoice' && $question->parent)) {
                    continue;
                }
                if (! $correct = reader_get_correct_answer($question, $questions)) {
                    $no_correct_answer[] = $question->id;
                }
            }
        }
    }

    if ($count = count($no_correct_answer)) {
        $editicon = $OUTPUT->pix_icon('t/edit', '');
        $quizmoduleid = $DB->get_field('modules', 'id', array('name' => 'quiz'));
        list($where, $params) = $DB->get_in_or_equal($no_correct_answer);
        $select = 'qtn.*, ctx.id AS ctxid, cm.id AS cmid, '.
                  'qz.id AS quizid, qz.name AS quizname, '.
                  'rb.id AS bookid, rb.publisher AS bookpublisher, rb.level AS booklevel, rb.name AS bookname';
        $from   = '{question} qtn '.
                  'LEFT JOIN {question_categories} qc ON qc.id = qtn.category '.
                  'LEFT JOIN {context} ctx ON ctx.id = qc.contextid '.
                  'LEFT JOIN {course_modules} cm ON cm.id = ctx.instanceid '.
                  'LEFT JOIN {quiz} qz ON qz.id = cm.instance '.
                  'LEFT JOIN {reader_books} rb ON rb.quizid = qz.id';
        $where  = 'ctx.contextlevel = ? AND cm.module = ? AND qtn.id '.$where;
        $sortby = 'rb.publisher, rb.level, rb.name, qz.name, qtn.name';
        array_unshift($params, CONTEXT_MODULE, $quizmoduleid);
        if ($questions = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $sortby", $params)) {
            if ($endlist=='') {
                echo '<ul>';
                $endlist = '</ul>';
            }

            echo '<li><span style="color: red;">Oops</span> ';
            if (count($questions)==1) {
                echo 'this question has';
            } else {
                echo 'these questions have';
            }
            echo ' no correct answer:<ul class="publishers">';

            $publisher = null;
            $booklevel = null;
            $bookid    = null;
            foreach ($questions as $question) {
                if ($bookid===null || $bookid != $question->bookid) {
                    if ($bookid) {
                        echo '</ul></li>';
                    }
                    if ($booklevel===null || $booklevel != $question->booklevel) {
                        if ($booklevel) {
                            echo '</ul></li>';
                        }
                        if ($publisher===null || $publisher != $question->bookpublisher) {
                            if ($publisher) {
                                echo '</ul></li>';
                            }
                            if ($publisher = $question->bookpublisher) {
                                echo '<li class="publisher">PUBLISHER: '.$publisher.'<ul class="levels">';
                            }
                        }
                        if ($booklevel = $question->booklevel) {
                            echo '<li class="level">LEVEL: '.$booklevel.'<ul class="books">';
                        }
                    }
                    if ($bookid = $question->bookid) {
                        echo '<li class="book">BOOK : '.$question->bookname.'<br />';
                        $href = $CFG->wwwroot.'/question/edit.php?cmid='.$question->cmid;
                        echo '<a href="'.$href.'" target="_blank">QUIZ</a> : '.$question->quizname;
                        echo '<ul class="questions">';
                    }
                }
                if ($question->cmid) {
                    // question edit page
                    $href = $CFG->wwwroot.'/question/question.php?cmid='.$question->cmid.'&id='.$question->id;
                    $name = '<a href="'.$href.'" target="_blank">QUESTION</a> : '.$question->name.'<br />';
                } else {
                    // orphaned question - shouldn't happen !!
                    $name = 'QUESTION: (id='.$question->id.') '.$question->name;
                }
                echo '<li class="question">'.$name.'</li>';
            }
            if ($bookid) {
                echo '</ul></li>';
            }
            if ($booklevel) {
                echo '</ul></li>';
            }
            if ($publisher) {
                echo '</ul></li>';
            }
            echo '</ul></li>';
        }
    }
}

echo $endlist;

echo html_writer::tag('p', 'All done');
if ($id) {
    $href = new moodle_url('/mod/reader/admin/tools.php', array('id' => $id, 'tab' => $tab));
} else {
    $href = new moodle_url($CFG->wwwroot.'/');
}
echo html_writer::tag('p', html_writer::tag('a', 'Click here to continue', array('href' => $href)));

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
