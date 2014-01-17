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
 * mod/reader/utilities/index.php
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

$id   = optional_param('id',   0, PARAM_INT); // course module id
$r    = optional_param('r',    0, PARAM_INT); // reader id

if ($id) {
    $cm = get_coursemodule_from_id('reader', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $reader = $DB->get_record('reader', array('id' => $cm->instance), '*', MUST_EXIST);
    $r = $reader->id;
} else {
    $reader = $DB->get_record('reader', array('id' => $r), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('reader', $reader->id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $id = $cm->id;
}

require_login($course, true, $cm);

add_to_log($course->id, 'reader', 'Download Quizzes', "admin/download.php?id=$id", "$cm->instance");

// Initialize $PAGE, compute blocks
$PAGE->set_url('/mod/reader/admin/index.php', array('id' => $cm->id));

// set title
$title = $course->shortname.': '.format_string($reader->name).': '.get_string('adminarea', 'reader');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$reader = mod_reader::create($reader, $cm, $course);

echo $OUTPUT->header();
echo $OUTPUT->box_start();

$links = array();

if ($reader->can_manageremotesites()) {
    $url = new moodle_url('/mod/reader/admin/download.php', array('id' => $cm->id));
    $links[] = html_writer::link($url, get_string('reader:manageremotesites', 'reader'));
}

if ($reader->can_managequizzes()) {
    $url = new moodle_url('/mod/reader/admin/index.php', array('id' => $cm->id));
    $links[] = html_writer::link($url, get_string('reader:managequizzes', 'reader'));
}

if ($reader->can_managebooks()) {
    $url = new moodle_url('/mod/reader/admin/index.php', array('id' => $cm->id));
    $links[] = html_writer::link($url, get_string('reader:managebooks', 'reader'));
}

if ($reader->can_manageusers()) {
    $url = new moodle_url('/mod/reader/admin/index.php', array('id' => $cm->id));
    $links[] = html_writer::link($url, get_string('reader:manageusers', 'reader'));
}

if ($reader->can_manageattempts()) {
    $url = new moodle_url('/mod/reader/admin/index.php', array('id' => $cm->id));
    $links[] = html_writer::link($url, get_string('reader:manageattempts', 'reader'));
}

if ($reader->can_viewreports()) {
    $url = new moodle_url('/mod/reader/report.php', array('id' => $cm->id));
    $links[] = html_writer::link($url, get_string('reader:viewreports', 'reader'));
}

if (count($links)) {
    echo html_writer::alist($links);
} else {
    echo redirect($reader->view_url());
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
