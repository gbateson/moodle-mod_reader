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
 * mod/reader/quiz/startattempt.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once('../../../config.php');
require_once($CFG->dirroot.'/mod/reader/quiz/accessrules.php');
require_once($CFG->dirroot.'/mod/reader/quiz/attemptlib.php');
require_once($CFG->dirroot.'/mod/reader/lib.php');
require_once($CFG->dirroot.'/question/engine/lib.php');

// get main config setting for mReader site
$mreadersiteid = get_config('mod_reader', 'mreadersiteid');

// Get submitted parameters.
$id = required_param('id', PARAM_INT); // "course_modules" id
$book = required_param('book', PARAM_INT); // "reader_books" id
$page = optional_param('page', 0, PARAM_INT); // Page to jump to in the attempt.

$cm = get_coursemodule_from_id('reader', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
$reader = $DB->get_record('reader', array('id'=>$cm->instance), '*', MUST_EXIST);

require_course_login($course, true, $cm);

// get SQL to verify user can access this book ($hasquiz = true)
list($from, $where, $sqlparams) = reader_available_sql($cm->id, $reader, $USER->id, true);

// add book id to SQL search conditions
$where = "rb.id = ? AND $where";
array_unshift($sqlparams, $book);

// check the user can access the requested book
if (! $DB->record_exists_sql("SELECT rb.* FROM $from WHERE $where", $sqlparams)) {
    echo get_string('quiznotavailable', 'mod_reader');
    die;
}

$readerquiz = reader_quiz::create($reader->id, $USER->id, $book);

// This script should only ever be posted to, so set page URL to the view page.
$PAGE->set_url($readerquiz->view_url());

// Check login and sesskey.
//require_login($readerquiz->get_courseid(), false, $readerquiz->get_cm());
//require_sesskey();
$PAGE->set_pagelayout('base');

$accessmanager = $readerquiz->get_access_manager(time());
$messages = $accessmanager->prevent_access();

$accessmanager->do_password_check($readerquiz->is_preview_user());

$title = get_string('likebook', 'mod_reader');
if ($accessmanager->securewindow_required($readerquiz->is_preview_user())) {
    $accessmanager->setup_secure_page($readerquiz->get_course()->shortname . ': ' . format_string($readerquiz->get_quiz_name()), '');
} else if ($accessmanager->safebrowser_required($readerquiz->is_preview_user())) {
    $PAGE->set_title($readerquiz->get_course()->shortname . ': ' .format_string($readerquiz->get_quiz_name()));
    $PAGE->set_heading($readerquiz->get_course()->fullname);
    $PAGE->set_cacheable(false);
    //echo $OUTPUT->header();
} else {
    $PAGE->navbar->add($title);
    $PAGE->set_title(format_string($readerquiz->get_reader_name()));
    $PAGE->set_heading($readerquiz->get_course()->fullname);
    //echo $OUTPUT->header();
}

// if no questions have been set up yet redirect to edit.php
if (! $readerquiz->has_questions()) {
    redirect($readerquiz->edit_url());
}

// Look for an existing attempt.
$attempts = reader_get_user_attempts($reader->id, $USER->id, 'all');
$lastattempt = end($attempts);

// If an in-progress attempt exists, check password then redirect to it.
if ($lastattempt && ! $lastattempt->timefinish) {
    redirect($readerquiz->attempt_url($lastattempt->id, $page));
}

// Get number for the next or unfinished attempt
$lastattempt = false;
$attemptnumber = 1;
$shuffleanswers = 1;

// Create the new attempt and initialize the question sessions
$attempt = reader_create_attempt($reader, $attemptnumber, $book);

$quba = question_engine::make_questions_usage_by_activity('mod_reader', $readerquiz->get_context());
$quba->set_preferred_behaviour('deferredfeedback');

if ($mreadersiteid) {
    // questions will be set up on mreader.org
    $attempt->layout = '';

    // $quba->insert_questions_usage_by_activity(), in "question/engine/datalib.php",
    // will fail if there are no questions, so we use the code below to get usage id
    $usage = (object)array('contextid' => $quba->get_owning_context()->id,
                           'component' => $quba->get_owning_component(),
                           'preferredbehaviour' => $quba->get_preferred_behaviour());
    $usage->id = $DB->insert_record('question_usages', $usage);
    $quba->set_id_from_database($usage->id);

} else {
    // setup  questions on local Moodle site

    if ($lastattempt && $reader->attemptonlast) {
        // Starting a subsequent attempt in each attempt builds on last mode.
        // NOTE: looks like this should never happen for a Reader quiz

        $oldquba = question_engine::load_questions_usage_by_activity($lastattempt->uniqueid);

        $slots = array();
        foreach ($oldquba->get_attempt_iterator() as $oldslot => $oldqa) {
            $newslot = $quba->add_question($oldqa->get_question(), $oldqa->get_max_mark());
            $quba->start_question_based_on($newslot, $oldqa);
            $slots[$oldslot] = $newslot;
        }

        // Update attempt layout.
        $layout = array();
        foreach (explode(',', $lastattempt->layout) as $oldslot) {
            if ($oldslot == 0) {
                $layout[] = 0;
            } else {
                $layout[] = $slots[$oldslot];
            }
        }
        $attempt->layout = implode(',', $layout);

    } else {
        // Set up questions for a new attempt on this Moodle site

        // Fully load all the questions in this reader.
        $readerquiz->preload_questions();
        $readerquiz->load_questions();

        // Add them all to the $quba.
        $slots = array();
        $questionsinuse = array_keys($readerquiz->get_questions());

        foreach ($readerquiz->get_questions() as $qid => $questiondata) {
            if ($questiondata->qtype == 'random') {
                $question = question_bank::get_qtype('random')->choose_other_question($questiondata, $questionsinuse, $shuffleanswers);
                if (is_null($question)) {
                    throw new moodle_exception('notenoughrandomquestions', 'reader', $readerquiz->view_url(), $questiondata);
                }
            } else {
                $questiondata->options->shuffleanswers = true; // always
                $question = question_bank::make_question($questiondata);
            }

            $slots[$qid] = $quba->add_question($question, $questiondata->maxmark);
            $questionsinuse[] = $question->id;
        }

        // Start all the questions.
        $quba->start_all_questions(new question_variant_pseudorandom_no_repeats_strategy($attemptnumber), time());

        // convert question ids to slot numbers in attempt layout
        $layout = array();
        foreach (explode(',', $attempt->layout) as $qid) {
            if ($qid == 0) {
                $layout[] = 0;
            } else {
                $layout[] = $slots[$qid];
            }
        }
        $attempt->layout = implode(',', $layout);
    }

    question_engine::save_questions_usage_by_activity($quba);
}

$attempt->uniqueid = $quba->get_id(); // a new id in the "question_usages" table
$attempt->id = $DB->insert_record('reader_attempts', $attempt);

// Log the new attempt (using Moodle events API, if available).
reader_add_to_log($course->id, 'reader', 'attempt', 'review.php?attempt='.$attempt->id, $reader->id, $cm->id);

// Redirect to the attempt page
if ($mreadersiteid) {
    redirect($readerquiz->mreader_attempt_url($attempt->id));
} else {
    redirect($readerquiz->attempt_url($attempt->id, $page));
}
