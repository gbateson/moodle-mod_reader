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
 * mod/reader/admin/users/renderer.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die;

/** Include required files */
require_once($CFG->dirroot.'/mod/reader/admin/users/renderer.php');

/**
 * mod_reader_admin_users_import_renderer
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_users_import_renderer extends mod_reader_admin_users_renderer {

    /**
     * mode_import
     *
     * @return string formatted html output
     */
    public function mode_import() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/mod/reader/admin/users/import/form.php');

        $url = $this->page->url;
        $params = $url->params();
        $params['id'] = $this->reader->cm->id;
        $params['tab'] = $this->get_tab();
        $params['mode'] = mod_reader::get_mode('admin/users');
        $url->params($params);

        $mform = new mod_reader_admin_users_import_form($url->out(false));

        if ($lines = $mform->get_file_content('import')) {
            $lines = preg_split('/[\r\n]+/s', $lines);

            echo html_writer::tag('p', get_string('fileuploaded', 'reader'));

            // cache useful strings
            $str = (object)array(
                'skipped' => get_string('skipped', 'reader'),
                'success' => get_string('success'),
                'error'   => get_string('error')
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
                if (substr_count($line, ',') != 11) {
                    echo get_string('skipline', 'reader', $line).html_writer::empty_tag('br');
                    continue; // unexpected format - shouldn't happen !!
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

                $attempt = (object)array(
                    // the "uniqueid" field is in fact an "id" from the "question_usages" table
                    'uniqueid'      => reader_get_new_uniqueid($this->reader->context->id, $books[$image]->quizid),
                    'reader'        => $this->reader->id,
                    'userid'        => $users[$username]->id,
                    'bookid'        => $books[$image]->id,
                    'quizid'        => $books[$image]->quizid,
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
                    'bookrating'    => $values['bookrating'],
                    'ip'            => $values['ip'],
                );

                $params = array('userid' => $users[$username]->id, 'quizid' => $books[$image]->quizid, 'timefinish' => $values['timefinish']);
                if ($DB->record_exists('reader_attempts', $params)) {
                    echo html_writer::tag('span', $str->skipped, array('class' => 'importskipped'));
                } else if ($DB->insert_record('reader_attempts', $attempt)) {
                    echo html_writer::tag('span', $str->success, array('class' => 'importsuccess'));
                } else {
                    echo html_writer::tag('span', $str->failure, array('class' => 'importfailure'));
                    print_object($attempt);
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
        } else {
            $mform->display();
        }
    }
}
