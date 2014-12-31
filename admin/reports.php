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
 * mod/reader/admin/reports.php
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

$id   = optional_param('id',    0, PARAM_INT);   // course module id
$r    = optional_param('r',     0, PARAM_INT);   // reader id
$mode = mod_reader::get_mode('admin/reports', 'filters');

$action = optional_param('action', '', PARAM_ALPHA);
$download = optional_param('download', '', PARAM_ALPHA);

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

require_course_login($course, true, $cm);

$reader = mod_reader::create($reader, $cm, $course);
$PAGE->set_url($reader->reports_url());

$title = format_string($reader->name).': '.get_string('reports').': '.get_string('report'.$mode, 'mod_reader');
$PAGE->set_title($title);
$PAGE->set_heading($title);

// load the appropriate renderer, table and filter class for this report $mode
require_once($CFG->dirroot.'/mod/reader/admin/reports/'.$mode.'/renderer.php');
require_once($CFG->dirroot.'/mod/reader/admin/reports/'.$mode.'/tablelib.php');
require_once($CFG->dirroot.'/mod/reader/admin/reports/'.$mode.'/filtering.php');

// create the renderer for this report
$output = $PAGE->get_renderer('mod_reader', 'admin_reports_'.$mode);

////////////////////////////////////////////////////////////////////////////////
// Output starts here                                                         //
////////////////////////////////////////////////////////////////////////////////

if ($reader->can_viewreports()) {
    echo $output->render_report($reader, $action, $download);
} else if (mod_reader::is_loggedinas()) {
    echo $output->render_logout($reader);
} else {
    require_capability('mod/reader:viewreports', $reader->context);
}

