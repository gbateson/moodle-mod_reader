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
 * mod/reader/quiz/summary.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/**
 * This page prints a summary of a quiz attempt before it is submitted.
 *
 * @package    mod
 * @subpackage quiz
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Include required files */
require_once(dirname(__FILE__).'/../../../config.php');
require_once($CFG->dirroot.'/mod/reader/lib.php');
require_once($CFG->dirroot.'/mod/reader/quiz/accessrules.php');
require_once($CFG->dirroot.'/mod/reader/quiz/attemptlib.php');

$attemptid = required_param('attempt', PARAM_INT); // The attempt to summarise.

$PAGE->set_url('/mod/reader/quiz/summary.php', array('attempt' => $attemptid));

$attemptobj = reader_attempt::create($attemptid);

// Check login.
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());

// If this is not our own attempt, display an error.
if ($attemptobj->get_userid() != $USER->id) {
    print_error('notyourattempt', 'quiz', $attemptobj->view_url());
}

// If the attempt is already closed, redirect them to the review page.
if ($attemptobj->is_finished()) {
    redirect($attemptobj->review_url());
}

// Check access.
$accessmanager = $attemptobj->get_access_manager(time());
$messages = $accessmanager->prevent_access();
$output = $PAGE->get_renderer('mod_quiz');

$accessmanager->do_password_check($attemptobj->is_preview_user());

$displayoptions = $attemptobj->get_display_options(false);

// Print the page header
if (empty($attemptobj->get_quiz()->showblocks)) {
    $PAGE->blocks->show_only_fake_blocks();
}

$title = get_string('likebook', 'reader');
if ($accessmanager->securewindow_required($attemptobj->is_preview_user())) {
    $accessmanager->setup_secure_page($attemptobj->get_course()->shortname . ': ' .
            format_string($attemptobj->get_quiz_name()), '');
} else if ($accessmanager->safebrowser_required($attemptobj->is_preview_user())) {
    $PAGE->set_title($attemptobj->get_course()->shortname . ': ' .
            format_string($attemptobj->get_quiz_name()));
    $PAGE->set_heading($attemptobj->get_course()->fullname);
    $PAGE->set_cacheable(false);
    echo $OUTPUT->header();
} else {
    $PAGE->navbar->add($title);
    $PAGE->set_title(format_string($attemptobj->get_reader_name()));
    $PAGE->set_heading($attemptobj->get_course()->fullname);
    echo $OUTPUT->header();
}

// Print heading.
echo $OUTPUT->heading(format_string($attemptobj->get_reader_name()));
echo $OUTPUT->heading($title, 3);

//print_r ($attemptobj);
//print_r ($displayoptions);

//echo $output->summary_page($attemptobj, $displayoptions);

//echo '<form method="post" action='processattempt.php'><div><input value="Submit all and finish" id="single_button4e9bafc1437fa" type="submit"><input name="attempt" value="5" type="hidden"><input name="finishattempt" value="1" type="hidden"><input name="timeup" value="0" type="hidden"><input name="slots" value="" type="hidden"><input name="sesskey" value="OMFyfrDQMO" type="hidden"></div></form>';

echo $OUTPUT->box_start('generalbox');

echo get_string('likebook', 'reader');

echo "<br /><br />";

echo '<form action="processattempt.php" method="post">';

echo '<input type="radio" name="likebook" value="3" id="like-3"> <label for="like-3" style="cursor:pointer;">'.get_string('itwasgreat', 'reader').'.</label><br />';
echo '<input type="radio" name="likebook" value="2" id="like-2"> <label for="like-2" style="cursor:pointer;">'.get_string('itwasokay', 'reader').'.</label><br />';
echo '<input type="radio" name="likebook" value="1" id="like-1"> <label for="like-1" style="cursor:pointer;">'.get_string('itwasso', 'reader').'.</label><br />';
echo '<input type="radio" name="likebook" value="0" id="like-0"> <label for="like-0" style="cursor:pointer;">'.get_string('ididntlikeitatall', 'reader').'.</label><br /><br />';

echo '<input name="attempt" value="'.$attemptid.'" type="hidden">';
echo '<input name="finishattempt" value="1" type="hidden">';
echo '<input name="timeup" value="0" type="hidden">';
echo '<input name="slots" value="" type="hidden">';
echo '<input name="sesskey" value="'.sesskey().'" type="hidden">';

echo '<center><input type="submit" value="Ok"></center>';

echo '</form>';

echo $OUTPUT->box_end();

// Finish the page
$accessmanager->show_attempt_timer_if_needed($attemptobj->get_attempt(), time());
echo $OUTPUT->footer();
