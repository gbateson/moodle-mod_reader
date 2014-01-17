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
 * mod/reader/showincorrectquizzes.php
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

$id = optional_param('id', 0, PARAM_INT); // course module id
$uid = optional_param('uid', 0, PARAM_INT); // user id

if (! $cm = get_coursemodule_from_id('reader', $id)) {
    throw new reader_exception('Course Module ID was incorrect');
}
if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
    throw new reader_exception('Course is misconfigured');
}
if (! $reader = $DB->get_record('reader', array('id' => $cm->instance))) {
    throw new reader_exception('Course module is incorrect');
}

require_login($course->id);

add_to_log($course->id, 'reader', 'show incorrect quizzes', "showincorrectquizzes.php?id=$id", "$cm->instance");

$select = 'ra.id AS attemptid, ra.quizid AS quizid, rb.name AS bookname';
$from   = '{reader_attempts} ra LEFT JOIN {reader_books} rb ON ra.quizid=rb.quizid';
$where  = 'ra.userid= ? AND ra.timefinish <= ? AND ra.passed <> ?';
$params = array($uid, $reader->ignoredate, 'true');

$booknames = array();
if ($attempts = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY timefinish", $params)) {
    foreach ($attempts as $attempt) {
        $booknames[] = $attempt->bookname;
    }
}
$booknames = array_filter($booknames); // remove blanks

if (empty($booknames)) {
    echo '<p>'.get_string('noincorrectquizzes', 'reader').'</p>';
} else {
    echo '<ul><li>'.implode('</li><li>', $booknames).'</li></ul>';
}
