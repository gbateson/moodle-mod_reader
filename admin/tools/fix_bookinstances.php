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
 * mod/reader/admin/tools/fix_wrongattempts.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once('../../../../config.php');

$id  = optional_param('id',  0, PARAM_INT);
$tab = optional_param('tab', 0, PARAM_INT);

require_login(SITEID);
if (class_exists('context_system')) {
    $context = context_system::instance();
} else {
    $context = get_context_instance(CONTEXT_SYSTEM);
}
require_capability('moodle/site:config', $context);

// $SCRIPT is set by initialise_fullme() in 'lib/setuplib.php'
// it is the path below $CFG->wwwroot of this script
$PAGE->set_url($CFG->wwwroot.$SCRIPT);

// set title
$title = get_string('fix_bookinstances', 'reader');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo $OUTPUT->box_start();

$courseid = 0;
if ($books = $DB->get_records('reader_books', null, 'publisher,series,level,name')) {
    if ($readers = $DB->get_records('reader', null, 'course')) {

        $publisher = '';
        foreach ($readers as $reader) {

            if ($courseid && $courseid==$reader->course) {
               // do nothing
            } else if ($course = $DB->get_record('course', array('id' => $reader->course))) {
                if ($publisher) {
                    echo '</ul></li>'; // finish previous publisher
                }
                if ($courseid) {
                    echo '</ul>'; // finish previous course
                }
                $courseid = $reader->course;
                $publisher = '';
                $series = '';
                $level = '';
            } else {
                continue; // shouldn't happen !!
            }
            if (! $instances = $DB->get_records_menu('reader_book_instances', array('readerid' => $reader->id), '', 'id,bookid')) {
                $instances = array();
            }

            foreach ($books as $book) {
                if (in_array($book->id, $instances)) {
                    continue; // book instance already exists
                }
                if ($book->publisher===$publisher && $book->series===$series && $book->level===$level) {
                    // do nothing
                } else {
                    if ($publisher=='') {
                        $href = new moodle_url('/course/view.php', array('id' => $course->id));
                        $link = html_writer::tag('a', $course->shortname, array('href' => $href, 'target' => '_blank'));
                        echo html_writer::tag('h3', 'Adding book instances to course: '.$link).'<ul>';
                    } else {
                        echo '</ul></li>'; // finish previous publisher
                    }
                    $publisher = $book->publisher;
                    $series    = $book->series;
                    $level     = $book->level;
                    echo '<li>'.$publisher.(empty($series) ? '' : " $series").(empty($level) ? '' : " - $level").'<ul>';
                }
                echo '<li>'.$book->name.' ... '; // start book instance
                $instance = (object)array(
                    'readerid'   => $reader->id,
                    'bookid'     => $book->id,
                    'difficulty' => $book->difficulty,
                    'length'     => $book->length
                );
                if ($instance->id = $DB->insert_record('reader_book_instances', $instance)) {
                    echo '<span style="color: green;">OK</span></li>'; // finish book instance
                } else {
                    echo '<span style="color: red;">OOPS: </span>'.'Could not add new book instance'.'</li></ul></ul>';
                    die;
                }
            }
        }
        if ($publisher) {
            echo '</ul></li>'; // finish previous publisher
        }
    }
}

if ($courseid) {
    echo '</ul>';
}

echo html_writer::tag('p', 'All done');
if ($id) {
    $href = new moodle_url('/mod/reader/admin/tools.php', array('id' => $id, 'tab' => $tab));
} else {
    $href = new moodle_url($CFG->wwwroot.'/');
}
echo html_writer::tag('p', html_writer::tag('a', 'Click here to continue', array('href' => $href)));

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
