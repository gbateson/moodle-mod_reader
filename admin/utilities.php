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
 * mod/reader/admin/utilities.php
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
require_once($CFG->dirroot.'/mod/reader/admin/utilities/renderer.php');

$id     = optional_param('id',     0,  PARAM_INT); // course module id
$r      = optional_param('r',      0,  PARAM_INT); // reader id

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

add_to_log($course->id, 'reader', 'Admin users', "admin/utilities.php?id=$id", "$cm->instance");

// Initialize $PAGE, compute blocks
$PAGE->set_url('/mod/reader/admin/utilities.php', array('id' => $cm->id));

$title = $course->shortname . ': ' . format_string($reader->name);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

$output = $PAGE->get_renderer('mod_reader', 'admin_utilities');
$output->init($reader);

echo $output->header();
echo $output->tabs();
echo $output->box_start('generalbox', 'notice');

// get path to this directory
$dirname = dirname($SCRIPT).'/utilities';
$dirpath = $CFG->dirroot.$dirname;

echo html_writer::start_tag('ul');

$files = array();
$items = new DirectoryIterator($dirpath);
foreach ($items as $item) {
    if ($item->isDot() || substr($item, 0, 1)=='.') {
        continue;
    }
    if ($item=='index.php' || $item=='lib.php' || $item=='renderer.php') {
        continue;
    }
    if ($item->isFile()) {
        $files[] = "$item"; // convert $item to string
    }
}
sort($files);
foreach ($files as $file) {
    $href = $CFG->wwwroot.$dirname.'/'.$file;
    echo html_writer::tag('li', html_writer::tag('a', $file, array('href' => $href)))."\n";
}

echo html_writer::end_tag('ul');

echo $output->box_end();
echo $output->footer();
