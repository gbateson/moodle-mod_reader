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
 * mod/reader/utilities/fix_wrongattempts.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');

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
$title = 'Fix wrong attempts';
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');

$reader_usecourse = get_config('reader', 'usecourse');

echo $OUTPUT->header();
echo $OUTPUT->box_start();

$select = 'ra.*, q.name AS quizname';
$from = '{reader_attempts} ra LEFT JOIN {quiz} q ON ra.quizid = q.id';
$sql = "SELECT $select FROM $from ORDER BY ra.userid, ra.timestart";

$user = false;
$limitfrom = 0;
$limitnum = 1000;
while ($attempts = $DB->get_records_sql($sql, null, $limitfrom, $limitnum)) {

    foreach ($attempts as $attempt) {

        $select = 'userid = ? AND time > ? AND time < ? AND module = ? AND action LIKE ?';
        $params = array($attempt->userid, $attempt->timestart, $attempt->timestart + MINSECS, 'reader', 'view attempt:%');
        if (! $logs = $DB->get_records_select('log', $select, $params, 'time', '*', 0, 1)) {
            continue; // no logs of this attempt - shouldn't happen !!
        }

        $log = reset($logs); // first log record
        $bookname = trim(substr($log->action, 13));
        $bookname = stripslashes($bookname);

        if ($bookname==$attempt->quizname) {
            continue; // this is the expected bookname
        }

        $quizid = 0; // correct value for $attempt->quizid

        // log has unexpected book name, so let's try and fix it

        // probably we can get the correct book id from $log->info
        $search = '/readerID ([0-9]+); reader (?:quiz|book) ([0-9]+)/';
        if (preg_match($search, $log->info, $matches)) {
            list($match, $readerid, $bookid) = $matches;
            if ($book = $DB->get_record('reader_books', array('id' => $bookid, 'name' => $bookname))) {
                $quizid = $book->quizid;
            }
        }

        // otherwise, we may be able to just look up the book name
        // (but watch out because several names are duplicated)
        if ($quizid==0) {
            if ($books = $DB->get_records('reader_books', array('name' => $bookname))) {
                if (count($books) > 1) {
                    echo '<li><span style="color: red;"></span> More than one possible book found for '.$bookname.'</li>';
                    print_object($books);
                } else {
                    $book = reset($books);
                    $quizid = $book->quizid;
                }
            }
        }

        // if we found the correct $quizid for this $bookname
        // we can fix the record in the "reader_attempts" table
        if ($quizid) {

            // print user details if necessary
            if (empty($user) || $user->id != $attempt->userid) {
                if (empty($user)) {
                    echo '<ul>';       // start list of users
                } else {
                    echo '</ul></li>'; // finish previous user
                }
                $user = $DB->get_record('user', array('id' => $attempt->userid));
                echo '<li><b>'.fullname($user).'</b>';
                echo '<ul>'; // start list of fixed attempts
            }

            // report fixed attempt
            echo "<li>Fix attempt (id=$attempt->id): book name '$attempt->quizname' =&gt; '$bookname' (quiz id $attempt->quizid -&gt; $quizid)</li>";
            $DB->set_field('reader_attempts', 'quizid', $quizid, array('id' => $attempt->id));
        }
    }
    $limitfrom += $limitnum;
}

if ($user) {
    echo '</ul></li></ul>';
}

echo html_writer::tag('p', 'All done');
echo html_writer::tag('p', html_writer::tag('a', 'Click here to continue', array('href' => $CFG->wwwroot.'/mod/reader/utilities/index.php')));

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
