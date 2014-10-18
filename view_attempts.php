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
 * mod/reader/view_attempts.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once('../../config.php');
require_once($CFG->dirroot.'/mod/reader/locallib.php');

$id = optional_param('id', 0, PARAM_INT); // course module id

$cm = get_coursemodule_from_id('reader', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course),   '*', MUST_EXIST);
$reader = $DB->get_record('reader', array('id' => $cm->instance), '*', MUST_EXIST);

require_course_login($course, true, $cm);
$reader = mod_reader::create($reader, $cm, $course);

$redirect = false;
if ($reader->can_viewreports()) {
    if ($bookid = optional_param('bookid', 0, PARAM_INT)) {
        if ($quizid = $DB->get_field('reader_books', 'quizid', array('id' => $bookid))) {
            if ($cm = get_coursemodule_from_instance('quiz', $quizid)) {
                if ($attempts = $DB->get_records('reader_attempts', array('quizid' => $quizid))) {
                    foreach ($attempts as $attempt){
                        $quizattemptid = reader_copy_to_quizattempt($attempt);
                    }
                }
                $params = array('id' => $cm->id,
                                'mode' => 'responses',
                                'attempts' => 'all_with',
                                'qtext'    => 1, // show question text
                                'resp'     => 1, // show response
                                'stateinprogress' => 1,
                                'stateoverdue'    => 1,
                                'statefinished'   => 1,
                                'stateabandoned'  => 1);
                $redirect = new moodle_url('/mod/quiz/report.php', $params);
            } else {
                die('oops, no $cm for quizid: '.$quizid);
            }
        }
    }
    if ($attemptid = optional_param('attemptid', 0, PARAM_INT)) {
        $params = array('id' => $attemptid, 'reader' => $reader->id);
        if ($attempt = $DB->get_record('reader_attempts', $params)) {
            if ($cm = get_coursemodule_from_instance('quiz', $attempt->quizid)) {
                if ($quizattemptid = reader_copy_to_quizattempt($attempt)) {
                    $params = array('attempt' => $quizattemptid);
                    $redirect = new moodle_url('/mod/quiz/review.php', $params);
                }
            }
        }
    }
}

if ($redirect) {
    redirect($redirect);
}

