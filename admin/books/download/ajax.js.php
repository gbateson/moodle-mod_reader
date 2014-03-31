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
 * mod/taskchain/admin/books/download/ajax.js.php
 *
 * @package    mod
 * @subpackage taskchain
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

if (! headers_sent()) {
    header('Content-type: application/javascript');
}

// tell Moodle we are an ajax script
// this prevents full screen errors
define('AJAX_SCRIPT', true);

/** Include required files */
require_once('../../../../../config.php');
require_once($CFG->dirroot.'/mod/reader/lib.php');
require_once($CFG->dirroot.'/mod/reader/admin/books/download/downloader.php');
require_once($CFG->dirroot.'/mod/reader/admin/books/download/renderer.php');

if ($id = optional_param('id', 0, PARAM_INT)) { // course module id
    $cm = get_coursemodule_from_id('reader', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $reader = $DB->get_record('reader', array('id' => $cm->instance), '*', MUST_EXIST);
    $r = $reader->id;
} else if ($r = optional_param('r', 0, PARAM_INT)) { // reader id
    $reader = $DB->get_record('reader', array('id' => $r), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('reader', $reader->id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $id = $cm->id;
} else {
    die('No input parameters');
}

// ensure user is logged in
require_login($course, true, $cm);

// ensure user is allowed to manage reader remote sites
$context = reader_get_context(CONTEXT_MODULE, $cm->id);
if (! has_capability('mod/reader:manageremotesites', $context)) {
    json_encode('No permissions');
    die;
}

// create renderer object
$output = $PAGE->get_renderer('mod_reader', 'admin_books_download');

// create an object to handle the downloading of data from remote sites
$downloader = new reader_downloader($course, $cm, $reader, $output);

echo json_encode(array(
    'targetcategoryelement' => $output->category_list($downloader),
    'targetcourseelement'   => $output->course_list($downloader),
    'targetsectionelement'  => $output->section_list($downloader),
));
