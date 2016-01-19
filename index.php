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
 * mod/reader/index.php
 * Display a list of Reader activities with links to Reader reports.
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');

$id = required_param('id', PARAM_INT);   // course

if (! $course = $DB->get_record('course', array('id' => $id))) {
    print_error('invalidcourseid');
}

require_course_login($course);

reader_add_to_log($course->id, 'reader', 'view all', 'index.php?id='.$course->id);

$PAGE->set_url('/mod/reader/index.php', array('id' => $course->id));
$PAGE->set_title($course->fullname);
$PAGE->set_heading($course->shortname);
$PAGE->navbar->add(get_string('modulenameplural', 'mod_reader'));

// Output starts here

echo $OUTPUT->header();

// Get all the appropriate data

if (! $readers = get_all_instances_in_course('reader', $course)) {
    echo $OUTPUT->heading(get_string('noreaders', 'mod_reader'), 2);
    echo $OUTPUT->continue_button(new moodle_url('/course/view.php', array('id' => $course->id)));
    echo $OUTPUT->footer();
    die();
}

// get list of reader ids
$readerids = array();
foreach ($readers as $reader) {
    $readerids[] = $reader->id;
}

// get total number of attempts, users and details for these readers
if (has_capability('mod/reader:viewreports', $PAGE->context)) {
    $show_aggregates = true;
    $single_user = false;
} else {
    $show_aggregates = true;
    $single_user = true;
}

if ($show_aggregates) {
    $params = array();
    $select = 'ra.readerid IN ('.implode(',', $readerids).')';
    $tables = '{reader_attempts} ra';
    $fields = 'ra.readerid, COUNT(ra.id) AS attemptcount, COUNT(DISTINCT ra.userid) AS usercount, ROUND(SUM(ra.percentgrade) / COUNT(ra.percentgrade), 0) AS averagescore, MAX(ra.percentgrade) AS maxscore';
    if ($single_user) {
        // restrict results to this user only
        $select .= ' AND ra.userid=:userid';
        $params['userid'] = $USER->id;
    }
    $aggregates = $DB->get_records_sql("SELECT $fields FROM $tables WHERE $select GROUP BY ra.readerid", $params);
} else {
    $aggregates = array();
}

$usesections = course_format_uses_sections($course->format);
if ($usesections) {
    if (method_exists('course_modinfo', 'get_section_info_all')) {
        // Moodle >= 2.3
        $modinfo = get_fast_modinfo($course);
        $sections = $modinfo->get_section_info_all();
    } else {
        // Moodle 2.0 - 2.2
        $sections = get_all_sections($course->id);
    }
}

// Print the list of instances (your module will probably extend this)

$strsectionname = get_string('sectionname', 'format_'.$course->format);
$strname        = get_string('name');
$strhighest     = get_string('gradehighest', 'quiz');
$straverage     = get_string('gradeaverage', 'quiz');
$strattempts    = get_string('attempts', 'quiz');

$table = new html_table();

if ($usesections) {
    $table->head  = array($strsectionname, $strname, $strhighest, $straverage, $strattempts);
    $table->align = array('center', 'left', 'center', 'center', 'left');
} else {
    $table->head  = array($strname, $strhighest, $straverage, $strattempts);
    $table->align = array('left', 'center', 'center', 'left');
}

foreach ($readers as $reader) {
    $row = new html_table_row();

    if ($usesections) {
        $text = get_section_name($course, $sections[$reader->section]);
        $row->cells[] = new html_table_cell($text);
    }

    if ($reader->visible) {
        $class = '';
    } else {
        $class = 'dimmed';
    }

    $href = new moodle_url('/mod/reader/view.php', array('id' => $reader->coursemodule));
    $params = array('href' => $href, 'class' => $class);

    $text = html_writer::tag('a', $reader->name, $params);
    $row->cells[] = new html_table_cell($text);

    if (empty($aggregates[$reader->id]) || empty($aggregates[$reader->id]->attemptcount)) {
        $row->cells[] = new html_table_cell('0'); // average score
        $row->cells[] = new html_table_cell('0'); // max score
        $row->cells[] = new html_table_cell('&nbsp;'); // reports
    } else {
        $href = new moodle_url('/mod/reader/admin/reports.php', array('id' => $reader->coursemodule, 'tab' => '3'));
        $params = array('href' => $href, 'class' => $class);

        $text = html_writer::tag('a', $aggregates[$reader->id]->maxscore, $params);
        $row->cells[] = new html_table_cell($text);

        $text = html_writer::tag('a', $aggregates[$reader->id]->averagescore, $params);
        $row->cells[] = new html_table_cell($text);

        $text = get_string('reader:viewreports', 'mod_reader', $aggregates[$reader->id]->usercount);
        $text = html_writer::tag('a', $text, $params);
        $row->cells[] = new html_table_cell($text);
    }

    $table->data[] = $row;
}

echo $OUTPUT->heading(get_string('modulenameplural', 'mod_reader'), 2);
echo html_writer::table($table);

// Finish the page
echo $OUTPUT->footer();
