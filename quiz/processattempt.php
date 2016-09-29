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
// (so as to make sure students don't get penalized for slow processing on this page).
$timenow = time();

// Get submitted parameters.
$attemptid     = required_param('attempt', PARAM_INT);
$thispage      = optional_param('thispage',      0,     PARAM_INT);
$nextpage      = optional_param('nextpage',      0,     PARAM_INT);
$previous      = optional_param('previous',      false, PARAM_BOOL);
$next          = optional_param('next',          false, PARAM_BOOL);
$finishattempt = optional_param('finishattempt', false, PARAM_BOOL);
$timeup        = optional_param('timeup',        false, PARAM_BOOL);
$scrollpos     = optional_param('scrollpos',     '',    PARAM_RAW);
$likebook      = optional_param('likebook',      null,  PARAM_CLEAN);

$transaction = $DB->start_delegated_transaction();
$attemptobj = reader_attempt::create($attemptid);

// We treat automatically closed attempts just like normally closed attempts
if ($timeup) {
    $finishattempt = true;
}

// Set $nexturl now.
if ($finishattempt) {
    $nexturl = $attemptobj->view_url();
} else {
    if ($next) {
        $page = $nextpage;
    } else {
        $page = $thispage;
        if ($previous && $page > 0) {
            $page--;
        }
    }
    if ($page == -1) {
        $nexturl = $attemptobj->summary_url();
    } else {
        $nexturl = $attemptobj->attempt_url(0, $page);
        if ($scrollpos !== '') {
            $nexturl->param('scrollpos', $scrollpos);
        }
    }
}

// Check login.
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());
require_sesskey();

// Check that this attempt belongs to this user.
if ($attemptobj->get_userid() != $USER->id) {
    throw new moodle_reader_exception($attemptobj->get_reader(), 'notyourattempt');
}

if (isset($likebook)) {
    $attemptobj->set_rating($likebook);
}

// If the attempt is already closed, send them to the review page.
if ($attemptobj->is_finished()) {
//    throw new moodle_reader_exception($attemptobj->get_reader(), 'attemptalreadyclosed', null, $attemptobj->review_url());
}

if ($finishattempt) {
    $attemptobj->finish_attempt($timenow);
} else {
    // process the responses for this page
    try {
        $attemptobj->process_all_actions($timenow);
    } catch (question_out_of_sequence_exception $e) {
        print_error('submissionoutofsequencefriendlymessage', 'question', $attemptobj->attempt_url(0, $thispage));
    }
}

$transaction->allow_commit();

if ($finishattempt) {
    // Note: we can only update_grades AFTER $transaction has been committed
    reader_update_grades($attemptobj->get_reader(), $attemptobj->get_userid());
}

redirect($nexturl);
