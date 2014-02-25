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
 * mod/reader/view_books.php
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

$id        = optional_param('id',      0, PARAM_INT); // course module id
$a         = optional_param('a',       0, PARAM_INT); // reader id
$search    = optional_param('search',  0, PARAM_INT); // search requested ?
$onlypub   = optional_param('onlypub', 0, PARAM_INT); // show only book names

if ($id) {
    $cm = get_coursemodule_from_id('reader', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
    $reader = $DB->get_record('reader', array('id'=>$cm->instance), '*', MUST_EXIST);
    $a = $reader->id;
} else {
    $reader = $DB->get_record('reader', array('id'=>$a), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('reader', $reader->id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
    $id = $cm->id;
}

require_course_login($course, true, $cm);

add_to_log($course->id, 'reader', 'Ajax get list of books', "view.php?id=$id", "$cm->instance");

if ($mode = optional_param('mode', '', PARAM_ALPHA)) {

    // load mod_reader class
    require_once($CFG->dirroot.'/mod/reader/locallib.php');
    $reader = mod_reader::create($reader, $cm, $course);
    $mode = mod_reader::validate_mode($mode);

    // create the appropriate renderer class for this report $mode
    require_once($CFG->dirroot.'/mod/reader/admin/reports/'.$mode.'/renderer.php');
    $output = $PAGE->get_renderer('mod_reader', 'admin_reports_'.$mode);
    $output->init($reader);

    // send $output to browser
    echo $output->available_items();
    exit;
}

// if we are a teacher logged in as a student, then fix the $USER object
if (isset($_SESSION['SESSION']->reader_lastuser) && $_SESSION['SESSION']->reader_lastuser > 0) {
    $_SESSION['SESSION']->reader_teacherview = 'studentview';
    $USER = $DB->get_record('user', array('id' => $_SESSION['SESSION']->reader_lastuser));
}

if ($search) {
    echo reader_search_books($id, $reader, $USER->id);
} else {
    echo reader_available_books($id, $reader, $USER->id);
}

// not sure what this does ... maybe switches the student mode back to teacher mode ?
if (isset($_SESSION['SESSION']->reader_lastuser) && $_SESSION['SESSION']->reader_lastuser > 0) {
    $USER = $DB->get_record('user', array('id' => $_SESSION['SESSION']->reader_lastuserfrom));
}
