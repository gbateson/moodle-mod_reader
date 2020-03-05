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
 * mod/reader/admin/index.php
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
require_once($CFG->dirroot.'/mod/reader/admin/renderer.php');

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
$reader = mod_reader::create($reader, $cm, $course);

reader_add_to_log($course->id, 'reader', 'Reader admin index', 'admin/index.php?id='.$cm->id, $reader->id, $cm->id);

// Initialize $PAGE, compute blocks
$PAGE->set_url('/mod/reader/admin/index.php', array('id' => $cm->id));

// set title
$title = $course->shortname.': '.format_string($reader->name).': '.get_string('adminarea', 'mod_reader');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$output = $PAGE->get_renderer('mod_reader', 'admin');
$output->init($reader);

echo $output->header();
echo $output->tabs();
echo $output->box_start('generalbox', 'notice');

$link = new moodle_url('/mod/reader/admin/index.php', array('id' => $id));
$link = html_writer::link($link, get_string('continue'));
echo $link;

echo $output->box_end();
echo $output->footer();
