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
 * mod/reader/utilities/fix_slashesinnames.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot.'/mod/reader/lib.php');

require_login(SITEID);
require_capability('moodle/site:config', reader_get_context(CONTEXT_SYSTEM));

// $SCRIPT is set by initialise_fullme() in 'lib/setuplib.php'
// it is the path below $CFG->wwwroot of this script
$PAGE->set_url($CFG->wwwroot.$SCRIPT);

$title = get_string('fixslashesinnames', 'reader');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo $OUTPUT->box_start();

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

if ($startedlist) {
    echo "</ul>\n";
} else {
    echo "<p>no slashes found in book titles</p>\n";
}
echo html_writer::tag('p', html_writer::tag('a', 'Click here to continue', array('href' => $CFG->wwwroot.'/mod/reader/utilities/index.php')));

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
