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
 * mod/reader/admin/tools/check_email.php
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

$id  = optional_param('id',  0, PARAM_INT);
$tab = optional_param('tab', 0, PARAM_INT);

require_login(SITEID);
require_capability('moodle/site:config', reader_get_context(CONTEXT_SYSTEM));

// $SCRIPT is set by initialise_fullme() in 'lib/setuplib.php'
// it is the path below $CFG->wwwroot of this script
$PAGE->set_url($CFG->wwwroot.$SCRIPT);

$title = get_string('check_email', 'mod_reader');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo $OUTPUT->box_start();

$admin = get_admin(); // the main admin user
if (! $user = $DB->get_record('user', array('username' => 'gueststudent'))) {
    $user = $admin;
}

$subject = get_string('check_email', 'mod_reader');
$message = get_string('welcometocourse', 'moodle', get_string('modulename', 'mod_reader'));

email_to_user($user, $admin, $subject, $message);
echo '<p>'.get_string('sentemailmoodle', 'mod_reader', $user).'</p>';

mail($user->email, $subject, $message);
echo '<p>'.get_string('sentemailphp', 'mod_reader', $user).'</p>';

echo html_writer::tag('p', 'All done');
if ($id) {
    $href = new moodle_url('/mod/reader/admin/tools.php', array('id' => $id, 'tab' => $tab));
} else {
    $href = new moodle_url($CFG->wwwroot.'/');
}
echo html_writer::tag('p', html_writer::tag('a', 'Click here to continue', array('href' => $href)));

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
