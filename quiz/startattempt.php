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
require_once(dirname(__FILE__).'/../../../config.php');
require_once($CFG->dirroot.'/mod/reader/quiz/attemptlib.php');
require_once($CFG->dirroot.'/mod/reader/lib.php');
require_once($CFG->dirroot.'/question/engine/lib.php');

// Get submitted parameters.
$id   = required_param('id', PARAM_INT); // "course_modules" id
$book = required_param('book', PARAM_INT); // "reader_books" id
$page = optional_param('page', 0, PARAM_INT); // Page to jump to in the attempt.

if (! $cm = get_coursemodule_from_id('reader', $id)) {
    print_error('invalidcoursemodule');
}
if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
    print_error('coursemisconf');
}
if (! $reader = $DB->get_record('reader', array('id' => $cm->instance))) {
    print_error('invalidcoursemodule');
}

require_login($course, true, $cm);

$readerobj = reader::create($reader->id, $USER->id, $book);
// This script should only ever be posted to, so set page URL to the view page.
$PAGE->set_url($readerobj->view_url());

// Check login and sesskey.
//require_login($readerobj->get_courseid(), false, $readerobj->get_cm());
//require_sesskey();
$PAGE->set_pagelayout('base');

// if no questions have been set up yet redirect to edit.php
if (! $readerobj->has_questions()) {
    redirect($readerobj->edit_url());
}

// Look for an existing attempt.
$attempts = reader_get_user_attempts($reader->id, $USER->id, 'all');
$lastattempt = end($attempts);

// If an in-progress attempt exists, check password then redirect to it.
if ($lastattempt && !$lastattempt->timefinish) {
    redirect($readerobj->attempt_url($lastattempt->id, $page));
}

// Get number for the next or unfinished attempt
$lastattempt = false;
$attemptnumber = 1;

$quba = question_engine::make_questions_usage_by_activity('mod_reader', $readerobj->get_context());
$quba->set_preferred_behaviour('deferredfeedback');

// Create the new attempt and initialize the question sessions
$attempt = reader_create_attempt($reader, $attemptnumber, $book);

if (! ($reader->attemptonlast && $lastattempt)) {
    // Starting a normal, new, reader attempt.

    // Fully load all the questions in this reader.
    $readerobj->preload_questions();
    $readerobj->load_questions();

    // Add them all to the $quba.
    $idstoslots = array();
    $questionsinuse = array_keys($readerobj->get_questions());

    foreach ($readerobj->get_questions() as $i => $questiondata) {
        if ($questiondata->qtype != 'random') {
            if (! $reader->shuffleanswers) {
                $questiondata->options->shuffleanswers = false;
            }
            $question = question_bank::make_question($questiondata);

        } else {
            $question = question_bank::get_qtype('random')->choose_other_question(
                    $questiondata, $questionsinuse, $reader->shuffleanswers);
            if (is_null($question)) {
                throw new moodle_exception('notenoughrandomquestions', 'reader',
                        $readerobj->view_url(), $questiondata);
            }
        }

        $idstoslots[$i] = $quba->add_question($question, $questiondata->maxmark);
        $questionsinuse[] = $question->id;
    }

    // Start all the questions.
    $variantoffset = $attemptnumber;

    $quba->start_all_questions(
            new question_variant_pseudorandom_no_repeats_strategy($variantoffset),
            time());

    // Update attempt layout.
    $newlayout = array();
    foreach (explode(',', $attempt->layout) as $qid) {
        if ($qid != 0) {
            $newlayout[] = $idstoslots[$qid];
        } else {
            $newlayout[] = 0;
        }
    }
    $attempt->layout = implode(',', $newlayout);
} else {
    // Starting a subsequent attempt in each attempt builds on last mode.

    $oldquba = question_engine::load_questions_usage_by_activity($lastattempt->uniqueid);

    $oldnumberstonew = array();
    foreach ($oldquba->get_attempt_iterator() as $oldslot => $oldqa) {
        $newslot = $quba->add_question($oldqa->get_question(), $oldqa->get_max_mark());

        $quba->start_question_based_on($newslot, $oldqa);

        $oldnumberstonew[$oldslot] = $newslot;
    }

    // Update attempt layout.
    $newlayout = array();
    foreach (explode(',', $lastattempt->layout) as $oldslot) {
        if ($oldslot != 0) {
            $newlayout[] = $oldnumberstonew[$oldslot];
        } else {
            $newlayout[] = 0;
        }
    }
    $attempt->layout = implode(',', $newlayout);
}

// Save the attempt in the database.
//$transaction = $DB->start_delegated_transaction();
question_engine::save_questions_usage_by_activity($quba);
$attempt->uniqueid = $quba->get_id();
$attempt->id = $DB->insert_record('reader_attempts', $attempt);

// Log the new attempt.
    add_to_log($course->id, 'reader', 'attempt', 'review.php?attempt=' . $attempt->id,
            $id, $book);

// Trigger event
$eventdata = new stdClass();
$eventdata->component = 'mod_reader';
$eventdata->attemptid = $attempt->id;
$eventdata->timestart = $attempt->timestart;
$eventdata->userid    = $attempt->userid;
$eventdata->readerid  = $id;
$eventdata->cmid      = $book;
$eventdata->courseid  = $readerobj->get_courseid();
events_trigger('reader_attempt_started', $eventdata);

//$transaction->allow_commit();

// Redirect to the attempt page.
redirect($readerobj->attempt_url($attempt->id, $page));
