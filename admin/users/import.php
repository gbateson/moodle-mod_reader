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
 * mod/reader/admin/users/import.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once('../../../.../config.php');
require_once($CFG->dirroot.'/mod/reader/admin/users/import_form.php');

$id     = optional_param('id',     0,  PARAM_INT); // course module id
$r      = optional_param('r',      0,  PARAM_INT); // reader id
$action = optional_param('action', '', PARAM_ALPHA);

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
require_capability('mod/reader:manageusers', reader_get_context(CONTEXT_MODULE, $cm->id));

$mform = new mod_reader_admin_users_import_form();

if ($lines = $mform->get_file_content('import')) {
    $lines = preg_split('/[\r\n]+/s', $lines);

    echo html_writer::tag('p', get_string('fileuploaded', 'reader'));

    // cache useful strings
    $str = (object)array(
        'skipped' => get_string('skipped', 'reader'),
        'skipped' => get_string('success'),
        'skipped' => get_string('error')
    );

    // initialize current user/book id
    $userid = 0;
    $bookid = 0;

    // process $lines
    foreach ($lines as $line) {


        // skip empty lines
        $line = trim($line);
        if ($line=='') {
            continue;
        }

        // make sure we have exactly 11 commas (=12 columns)
        if (substr_count($line, ',') <> 11) {
            echo get_string('skipline', 'reader', $line).html_writer::empty_tag('br');
            continue; // unexpected format !!
        }

        // extract fields
        $values = array();
        list($values['username'],
             $values['uniqueid'],
             $values['attempt'],
             $values['sumgrades'],
             $values['percentgrade'],
             $values['bookrating'],
             $values['ip'],
             $values['image'],
             $values['timefinish'],
             $values['passed'],
             $values['percentgrade'],
             $values['currentlevel']) = explode(',', $line);

        if (! $username = $values['username']) {
            continue; // empty username !!
        }
        if (! $image = $values['image']) {
            continue; // empty image !!
        }

        if (empty($userdata[$username])) {
            if ($user = $DB->get_record('user', array('username' => $username))) {
                $users[$username] = $user;
            } else {
                $users[$username] = (object)array('id' => 0); // no such user ?!
                echo get_string('usernamenotfound', 'reader', $username).html_writer::empty_tag('br');
            }
        }

        if (empty($users[$username]->id)) {
            continue;
        }

        if (empty($books[$image])) {
            $books[$image] = $DB->get_record('reader_books', array('image' => $image));
        }
        if (empty($books[$image])) {
            $books[$image] = (object)array('id' => 0, 'quizid' => 0); // no such book ?!
            echo get_string('booknotfound', 'reader', $image).html_writer::empty_tag('br');
        }

        if (empty($books[$image]->id) || empty($books[$image]->quizid)) {
            continue;
        }

        $sameuser = ($userid && $userid==$users[$username]->id);
        $samebook = ($sameuser && $bookid && $bookid==$books[$image]->id);

        if ($samebook==false) {

            if ($bookid) {
                echo html_writer::end_tag('ul'); // end attempts
                echo html_writer::end_tag('li'); // end book
            }

            if ($sameuser==false) {
                if ($userid==0) {
                    echo html_writer::start_tag('ul'); // start users
                } else {
                    echo html_writer::end_tag('ul'); // end books
                    echo html_writer::end_tag('li'); // end user
                }
                echo html_writer::start_tag('li'); // start user
                $fullname = fullname($users[$username]).' (username='.$username.', id='.$users[$username]->id.')';
                echo html_writer::tag('span', $fullname, array('class' => 'importusername'));
                $userid = $users[$username]->id;
                $bookid = 0; // force new book list
            }

            if ($bookid==0) {
                echo html_writer::start_tag('ul'); // start books
            }

            echo html_writer::start_tag('li'); // start book
            echo html_writer::tag('span', $books[$image]->name, array('class' => 'importbookname'));
            echo html_writer::start_tag('ul'); // start attempt list
            $bookid = $books[$image]->id;
        }

        echo html_writer::start_tag('li'); // start attempt

        $strpassed = reader_format_passed($values['passed'], true);
        $timefinish = userdate($values['timefinish'])." ($strpassed)";
        echo html_writer::tag('span', $timefinish, array('class' => 'importattempttime')).' ';

        $readerattempt = (object)array(
            // the "uniqueid" field is in fact an "id" from the "question_usages" table
            'uniqueid'      => reader_get_new_uniqueid($contextmodule->id, $books[$image]->quizid),
            'reader'        => $reader->id,
            'userid'        => $users[$username]->id,
            'attempt'       => $values['attempt'],
            'sumgrades'     => $values['sumgrades'],
            'percentgrade'  => $values['percentgrade'],
            'passed'        => $values['passed'],
            'checkbox'      => 0,
            'timestart'     => $values['timefinish'],
            'timefinish'    => $values['timefinish'],
            'timemodified'  => $values['timefinish'],
            'layout'        => 0, // $values['layout']
            'preview'       => 0,
            'quizid'        => $books[$image]->quizid,
            'bookrating'    => $values['bookrating'],
            'ip'            => $values['ip'],
        );

        $params = array('userid' => $users[$username]->id, 'quizid' => $books[$image]->quizid, 'timefinish' => $values['timefinish']);
        if ($DB->record_exists('reader_attempts', $params)) {
            echo html_writer::tag('span', $str->skipped, array('class' => 'importskipped'));
        } else if ($DB->insert_record('reader_attempts', $readerattempt)) {
            echo html_writer::tag('span', $str->success, array('class' => 'importsuccess'));
        } else {
            echo html_writer::tag('span', $str->failure, array('class' => 'importfailure'));
            print_object($readerattempt);
        }
        echo html_writer::end_tag('li'); // end attempt
    }

    if ($bookid) {
        echo html_writer::end_tag('ul'); // end attempt
        echo html_writer::end_tag('li'); // end book
    }
    if ($userid) {
        echo html_writer::end_tag('ul'); // end books
        echo html_writer::end_tag('li'); // end user
        echo html_writer::end_tag('ul'); // end users
    }
    echo 'Done';

}

