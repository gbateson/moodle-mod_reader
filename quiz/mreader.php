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
 * mod/reader/mreader/return.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once('../../../config.php');
require_once($CFG->dirroot.'/mod/reader/locallib.php');
require_once($CFG->dirroot.'/mod/reader/renderer.php');
require_once($CFG->dirroot.'/lib/resourcelib.php');

// load Quiz module library, if available (Moodle >= 2.3)
if (file_exists($CFG->dirroot.'/mod/quiz/attemptlib.php')) {
    require_once($CFG->dirroot.'/mod/quiz/attemptlib.php');
}

// load mreader library, if needed
if ($mreadersiteid = get_config('mod_reader', 'mreadersiteid')) {
    require_once($CFG->dirroot.'/mod/reader/quiz/mreaderlib.php');
}

if ($id = optional_param('id', 0, PARAM_INT)) {
    $cm = get_coursemodule_from_id('reader', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $reader = $DB->get_record('reader', array('id' => $cm->instance), '*', MUST_EXIST);
    $r = $reader->id;
} else if ($r = optional_param('r',  0, PARAM_INT)) {
    $reader = $DB->get_record('reader', array('id' => $r), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('reader', $reader->id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $id = $cm->id;
}

if ($customid = optional_param('custom_id', 0, PARAM_INT)) {
    $attemptid = $customid; // returned from mreader.org
} else {
    $attemptid = optional_param('attempt', 0, PARAM_INT);
}

if ($attemptid) {
    $attempt = $DB->get_record('reader_attempts', array('id' => $attemptid), '*', MUST_EXIST);
    $reader = $DB->get_record('reader', array('id' => $attempt->readerid), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('reader', $reader->id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $id = $cm->id;
    $r = $reader->id;
} else {
    $attempt = false;
}

// make sure user is logged in
require_login($course, true, $cm);

// intialize reader object
$reader = mod_reader::create($reader, $cm, $course);

// make sure this user can view books in this Reader activity
$reader->req('viewbooks');

reader_add_to_log($course->id, 'reader', 'mReader', 'quiz/mreader.php?id='.$cm->id, $reader->id, $cm->id);

if ($customid) {
    $url = new moodle_url($FULLME);
    $token = $url->get_param('token');
    $url->remove_params(array('token'));

    $mreader = new reader_site_mreader($attempt);
    if (! $token = $mreader->generate_token($url)) {
        // invalid token
        die('Invalid token');
    }
    ;
    if (! $DB->record_exists('reader_books', array('id' => $attempt->bookid, 'image' => $url->get_param('book')))) {
        // invalid book image
        die('Invalid book image');
    }
    if (! $DB->record_exists('reader_users', array('userid' => $USER->id, 'uniqueid' => $uniqueid = $url->get_param('uname')))) {
        // invalid uniqueid
        die('Invalid uniqueid');
    }
    $inprogress = true;
    if (defined('quiz_attempt::IN_PROGRESS') && $attempt->state == quiz_attempt::IN_PROGRESS) {
        // Moodle >= 2.3
        $inprogress = true;
    } else if ($attempt->timefinish==0) {
        // Moodle <= 2.2
        $inprogress = true;
    }
    if ($inprogress) {
        $attempt->sumgrades = 100;
        $attempt->percentgrade = $url->get_param('grade');
        $DB->update_record('reader_attempts', $attempt);
        redirect($mreader->processattempt_url());
    }
} else if ($attemptid) {
    // set page title
    $title = $DB->get_field('reader_books', 'name', array('id' => $attempt->bookid));
    $title = get_string('takequizfor', 'mod_reader', $title);

    // Initialize $PAGE, compute blocks
    $PAGE->set_title($title);
    $PAGE->set_heading($course->fullname);
    $PAGE->set_pagelayout('popup');
    $PAGE->set_url('/mod/reader/quiz/mreader.php', array('id' => $cm->id, 'attempt' => $attemptid));

    // setup IFRAME
    $mreader = new reader_site_mreader($attempt);
    $url = $mreader->start_url();
    $clicktoopen = html_writer::link($url, $title);
    $mimetype = resourcelib_guess_url_mimetype($url);
    $iframe = resourcelib_embed_general($url, $title, $clicktoopen, $mimetype);

    echo $OUTPUT->header($reader, $cm);
    echo $OUTPUT->heading($title, '3');
    echo $OUTPUT->box($iframe);
    echo $OUTPUT->footer();
    die;
}

redirect($reader->view_url());
