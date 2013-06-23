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
 * mod/reader/report.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once('../../config.php');
require_once($CFG->dirroot.'/mod/reader/lib.php');

$id = optional_param('id', 0, PARAM_INT); // cm id
$q  = optional_param('q',  0, PARAM_INT); // quiz id
$b  = optional_param('b',  0, PARAM_INT); // reader_books id

$cm = get_coursemodule_from_id('reader', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
$reader = $DB->get_record('reader', array('id'=>$cm->instance), '*', MUST_EXIST);
require_course_login($course, true, $cm);

$PAGE->set_url('/mod/reader/report.php', array('id' => $id, 'b' => $b, 'q' => $q));

$title = get_string('report');
$PAGE->set_title($title);
$PAGE->set_heading($title);

if ($q) {
    $book = $DB->get_record('reader_books', array('quizid' => $q));
} else if ($b) {
    $book = $DB->get_record('reader_books', array('id' => $b));
} else {
    $book = false;
}

if ($book) {
    if ($quizid = $book->quizid) {
        if ($cm = get_coursemodule_from_instance('quiz', $quizid)) {
            if ($attempts = $DB->get_records('reader_attempts', array('quizid' => $quizid))) {
                foreach ($attempts as $attempt) {
                    reader_copy_to_quizattempt($attempt);
                }
                $report = new moodle_url('/mod/quiz/report.php', array('id' => $cm->id, 'mode' => 'responses'));
                redirect($report);
            }
        }
    }
}

echo $OUTPUT->header();
echo '<h1>No attempts found</h1>';
echo $OUTPUT->footer();
