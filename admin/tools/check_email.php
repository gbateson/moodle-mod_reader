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
require_once($CFG->dirroot.'/mod/reader/admin/tools/lib.php');
require_once($CFG->dirroot.'/mod/reader/admin/tools/renderer.php');
require_once($CFG->dirroot.'/mod/reader/locallib.php');

$id  = optional_param('id',  0, PARAM_INT);
$tab = optional_param('tab', 0, PARAM_INT);
$tool = substr(basename($SCRIPT), 0, -4);

require_login(SITEID);
require_capability('moodle/site:config', reader_get_context(CONTEXT_SYSTEM));

if ($id) {
    $cm = get_coursemodule_from_id('reader', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $reader = $DB->get_record('reader', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $cm = null;
    $course = null;
    $reader = null;
}
$reader = mod_reader::create($reader, $cm, $course);

// set page url
$params = array('id' => $id, 'tab' => $tab);
$PAGE->set_url(new moodle_url("/mod/reader/admin/tools/$tool.php", $params));

// set page title
$title = get_string($tool, 'mod_reader');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');

$output = $PAGE->get_renderer('mod_reader', 'admin_tools');
$output->init($reader);

echo $output->header();
echo $output->tabs();
echo $output->box_start();

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

reader_print_all_done();
reader_print_continue($id, $tab);

echo $output->box_end();
echo $output->footer();
