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
 * mod/reader/quiz/processattempt.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once('../../../config.php');
require_once($CFG->dirroot.'/mod/reader/lib.php');
require_once($CFG->dirroot.'/mod/reader/quiz/accessrules.php');
require_once($CFG->dirroot.'/mod/reader/quiz/attemptlib.php');

// Remember the current time as the time any responses were submitted
// (so as to make sure students don't get penalized for slow processing on this page)
$timenow = time();

// Get submitted parameters.
$attemptid = required_param('attempt', PARAM_INT);
$next = optional_param('next', false, PARAM_BOOL);
$thispage = optional_param('thispage', 0, PARAM_INT);
$nextpage = optional_param('nextpage', 0, PARAM_INT);
$finishattempt = optional_param('finishattempt', 0, PARAM_BOOL);
$timeup = optional_param('timeup', 0, PARAM_BOOL); // True if form was submitted by timer.
$scrollpos = optional_param('scrollpos', '', PARAM_RAW);
$likebook = optional_param('likebook', NULL, PARAM_CLEAN);

$transaction = $DB->start_delegated_transaction();
$attemptobj = reader_attempt::create($attemptid);

// Set $nexturl now.
if ($next) {
    $page = $nextpage;
} else {
    $page = $thispage;
}
if ($page == -1) {
    $nexturl = $attemptobj->summary_url();
} else {
    $nexturl = $attemptobj->attempt_url(0, $page);
    if ($scrollpos !== '') {
        $nexturl->param('scrollpos', $scrollpos);
    }
}

// We treat automatically closed attempts just like normally closed attempts
if ($timeup) {
    $finishattempt = 1;
}

// Check login.
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());
require_sesskey();

// Check that this attempt belongs to this user.
if ($attemptobj->get_userid() != $USER->id) {
    throw new moodle_reader_exception($attemptobj->get_readerobj(), 'notyourattempt');
}

if (isset($likebook)) {
    $attemptobj->set_rating($likebook);
}

// If the attempt is already closed, send them to the review page.
if ($attemptobj->is_finished()) {
    throw new moodle_reader_exception($attemptobj->get_readerobj(), 'attemptalreadyclosed', null, $attemptobj->review_url());
}

// Don't log - we will end with a redirect to a page that is logged.

if (! $finishattempt) {
    // Just process the responses for this page and go to the next page.
    try {
        $attemptobj->process_all_actions($timenow);
    } catch (question_out_of_sequence_exception $e) {
        print_error('submissionoutofsequencefriendlymessage', 'question',
                $attemptobj->attempt_url(0, $thispage));
    }
    $transaction->allow_commit();
    redirect($nexturl);
}

// Otherwise, we have been asked to finish attempt, so do that.

// Update the reader attempt record.
$attemptobj->finish_attempt($timenow);

// Send the user to the review page.
$transaction->allow_commit();

redirect($CFG->wwwroot.'/mod/reader/view.php?id='.$attemptobj->get_cmid());
