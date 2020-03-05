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
 * mod/reader/admin/tools/fix_slashesinnames.php
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

require_login(SITEID);

$id  = optional_param('id',  0, PARAM_INT);
$tab = optional_param('tab', 0, PARAM_INT);
$tool = substr(basename($SCRIPT), 0, -4);

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
$reader->req('managetools');

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

$startedlist = false;
if ($books = $DB->get_records ('reader_books')) {
    foreach ($books as $book) {
        if (strstr($book->name, "\'")) {
            $DB->set_field('reader_books',  'name', stripslashes($book->name), array('id'=>$book->id));
            if ($startedlist==false) {
                $startedlist = true;
                echo "<ul>\n";
            }
            echo '<li>..reader title updating: '.$book->name."</li>\n";
        }
    }
}

if (! $reader_usecourse = get_config('mod_reader', 'usecourse')) {
    $params = array('fullname' => 'Reader Quizzes', 'visible' => 0);
    if (! $reader_usecourse = $DB->get_field('course', 'id', $params)) {
        // look for external "usecourse" in Reader activities
        $select = 'r.id, r.course, r.usecourse, c.id AS courseid, c.visible';
        $from   = '{reader} r LEFT JOIN {course} c ON r.usecourse = c.id';
        $where  = 'r.usecourse IS NOT NULL AND r.course <> r.usecourse AND c.id IS NOT NULL AND c.visible = ?';
        $params = array(0);
        $reader_usecourses = array();
        if ($readers = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params)) {
            $reader = reset($readers);
            $reader_usecourse = $reader->usecourse;
        }
    }
}

// get reader course activity contexts
$modulecontexts = null;
if ($reader_usecourse) {
    if ($coursecontext  = reader_get_context(CONTEXT_COURSE, $reader_usecourse)) {
        $select         = '(contextlevel = ? AND path = ?) OR (contextlevel = ? AND '.$DB->sql_like('path', '?').')';
        $params         = array(CONTEXT_COURSE, $coursecontext->path, CONTEXT_MODULE, $coursecontext->path.'/%');
        $modulecontexts = $DB->get_records_select('context', $select, $params);
    }
}

// get question categories for Reader course activities
if (is_array($modulecontexts) && count($modulecontexts)) {
    list($select, $params) = $DB->get_in_or_equal(array_keys($modulecontexts));
    $categories = $DB->get_records_select('question_categories', 'contextid '.$select, $params);
} else {
    $categories = null;
}

// check Reader question categories
if (is_array($categories) && count($categories)) {
    foreach ($categories as $category) {
        $msg = '';
        // remove slashes from category name
        if (strpos($category->name, '\\') !== false) {
            $msg = '<span style="color: brown;">FIX</span> slashes in category name: '.$category->name.'';
            $DB->set_field('question_categories', 'name', stripslashes($category->name), array('id' => $category->id));
        }
        // fix case of category name
        if ($category->name=='ordering' || $category->name=='ORDERING' || $category->name=='ORDER') {
            $msg = '<span style="color: brown;">FIX</span> category name: '.$category->name.' =&gt; Ordering';
            $DB->set_field('question_categories', 'name', 'Ordering', array('id' => $category->id));
        }
        // remove slashes from category info
        if (strpos($category->info, '\\') !== false) {
            $msg = '<span style="color: brown;">FIX</span> slashes in category info: '.$category->info.'';
            $DB->set_field('question_categories', 'info', stripslashes($category->info), array('id' => $category->id));
        }
        if ($msg) {
            if ($startedlist==false) {
                $startedlist = true;
                echo "<ul>\n";
            }
            echo "<li>..$msg</li>\n";
        }
    }
}

if ($startedlist) {
    echo "</ul>\n";
} else {
    echo "<p>no slashes found in book titles</p>\n";
}

reader_print_all_done();
reader_print_continue($id, $tab);

echo $output->box_end();
echo $output->footer();
