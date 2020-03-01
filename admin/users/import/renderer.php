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
 * mod/reader/admin/users/import/renderer.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die();

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

    public $mode = 'import';

    /**
     * get_tab
     *
     * @return integer tab id
     */
    public function get_tab() {
        return self::TAB_USERS_IMPORT;
    }

    /**
     * render_page
     *
     * @return string formatted html output
     */
    public function render_page() {
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

            echo html_writer::tag('p', get_string('fileuploaded', 'mod_reader'));

            // cache useful strings
            $str = (object)array(
                'skipped' => get_string('skipped', 'mod_reader'),
                'success' => get_string('success'),
                'error'   => get_string('error')
            );

            // initialize field names
            if (preg_match('/^username,[a-z,]+$/', $lines[0])) {
                $fields = explode(',', array_shift($lines));
            } else {
                $fields = array(
                    // default fields names (until Feb 2020)
                    'username', 'uniqueid', 'attempt', 'sumgrades',
                    'percentgrade', 'bookrating', 'ip', 'image',
                    'timefinish', 'passed', 'percentgrade', 'currentlevel'
                );
            }
            $countfields = count($fields);

            // initialize cache of users
            $users = array();

            // initialize current user/book id
            $userid = 0;
            $bookid = 0;

            // process $lines
            foreach ($lines as $i => $line) {

                // skip empty lines
                $line = trim($line);
                if ($line=='') {
                    continue;
                }

                $line = explode(',', $line);

                // make sure we have exact number of fields
                if (count($line) != $countfields) {
                    echo get_string('skipline', 'mod_reader', ($i + 1)).html_writer::empty_tag('br');
                    continue; // unexpected format - shouldn't happen !!
                }

                // extract fields
                $values = array();
                foreach ($fields as $i => $field) {
                    $values[$field] = $line[$i];
                }

                if (! $username = $values['username']) {
                    continue; // empty username !!
                }
                if (! $image = $values['image']) {
                    continue; // empty image !!
                }

                if (empty($users[$username])) {
                    if ($user = $DB->get_record('user', array('username' => $username))) {
                        $users[$username] = $user;
                    } else {
                        $users[$username] = (object)array('id' => 0); // no such user ?!
                        echo get_string('usernamenotfound', 'mod_reader', $username).html_writer::empty_tag('br');
                    }
                }

                if (empty($users[$username]->id)) {
                    continue;
                }

                if (empty($books[$image])) {
                    $books[$image] = $DB->get_record('reader_books', array('image' => $image));
                }
                if (empty($books[$image])) {
                    $books[$image] = (object)array(
                        'id' => -1,
                        'quizid' => 0,
                        'name' => get_string('booknotfound', 'mod_reader', $image)
                    );
                    if ($bookid) {
                        echo html_writer::end_tag('ul'); // end attempts
                        echo html_writer::end_tag('li'); // end book
                    }
                    echo html_writer::start_tag('li', array('class' => 'importbook')); // start book
                    echo html_writer::tag('span', $books[$image]->name, array('class' => 'importbookname'));
                    echo html_writer::start_tag('ul', array('class' => 'importattempts')); // start attempt list
                    $bookid = -1;
                }

                if (empty($books[$image]->id) || $books[$image]->id < 0 || empty($books[$image]->quizid)) {
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
                            echo html_writer::start_tag('ul', array('class' => 'importusers')); // start users
                        } else {
                            echo html_writer::end_tag('ul'); // end books
                            echo html_writer::end_tag('li'); // end user
                        }
                        echo html_writer::start_tag('li', array('class' => 'importuser')); // start user
                        $fullname = fullname($users[$username]).' (username='.$username.', id='.$users[$username]->id.')';
                        echo html_writer::tag('span', $fullname, array('class' => 'importusername'));
                        $userid = $users[$username]->id;
                        $bookid = 0; // force new book list
                    }

                    if ($bookid==0) {
                        echo html_writer::start_tag('ul', array('class' => 'importbooks')); // start books
                    }

                    echo html_writer::start_tag('li', array('class' => 'importbook')); // start book
                    echo html_writer::tag('span', $books[$image]->name, array('class' => 'importbookname'));
                    echo html_writer::start_tag('ul', array('class' => 'importattempts')); // start attempt list
                    $bookid = $books[$image]->id;
                }

                echo html_writer::start_tag('li', array('class' => 'importattempt')); // start attempt

                // format "timefinish" message
                switch (true) {

                    case empty($values['passed']):
                    case $values['passed'] == '0':
                    case $values['passed'] == 'false':
                        $values['passed'] = 0;
                        $strpassed = get_string('failed', 'mod_reader');
                        break;

                    case '1':
                    case 'true':
                        $values['passed'] = 1;
                        $strpassed = get_string('passed', 'mod_reader');
                        break;

                    case 'cheated':
                        $values['passed'] = 0;
                        $values['cheated'] = 1;
                        $strpassed = get_string('credit', 'mod_reader');
                        break;

                    default:
                        $strpassed = $values['passed'];
                }
                $timefinish = userdate($values['timefinish'])." ($strpassed)";
                echo html_writer::tag('span', $timefinish, array('class' => 'importattempttime')).' ';

                if (empty($values['state'])) {
                    $values['state'] = (empty($values['timefinish']) ? 'inprogress' : 'finished');
                }

                $attempt = (object)array(
                    // the "uniqueid" field is in fact an "id" from the "question_usages" table
                    'uniqueid'      => reader_get_new_uniqueid($this->reader->context->id, $books[$image]->quizid),
                    'readerid'      => $this->reader->id,
                    'userid'        => $users[$username]->id,
                    'bookid'        => $books[$image]->id,
                    'quizid'        => $books[$image]->quizid,
                    'attempt'       => (empty($values['attempt']) ? 0 : $values['attempt']),
                    'layout'        => (empty($values['layout']) ? '' : $values['layout']),
                    'state'         => (empty($values['state']) ? '' : $values['state']),
                    'currentpage'   => (empty($values['currentpage']) ? 0 : $values['currentpage']),
                    'sumgrades'     => (empty($values['sumgrades']) ? 0 : $values['sumgrades']),
                    'percentgrade'  => (empty($values['percentgrade']) ? 0 : $values['percentgrade']),
                    'passed'        => (empty($values['passed']) ? 0 : 1),
                    'credit'        => (empty($values['credit']) ? 0 : 1),
                    'cheated'       => (empty($values['cheated']) ? 0 : 1),
                    'deleted'       => (empty($values['deleted']) ? 0 : 1),
                    'timestart'     => (empty($values['timestart']) ? 0 : $values['timestart']),
                    'timefinish'    => (empty($values['timefinish']) ? 0 : $values['timefinish']),
                    'timemodified'  => (empty($values['timemodified']) ? 0 : $values['timemodified']),
                    'bookrating'    => (empty($values['bookrating']) ? 0 : $values['bookrating']),
                    'ip'            => (empty($values['ip']) ? '' : $values['ip']),
                );

                $params = array('userid' => $attempt->userid,
                                'quizid' => $attempt->quizid,
                                'timefinish' => $attempt->timefinish,
                                'deleted' => $attempt->deleted);
                if ($DB->record_exists('reader_attempts', $params)) {
                    echo html_writer::tag('span', $str->skipped, array('class' => 'alert-info'));
                } else if ($DB->insert_record('reader_attempts', $attempt)) {
                    echo html_writer::tag('span', $str->success, array('class' => 'alert-success'));
                } else {
                    echo html_writer::tag('span', $str->failure, array('class' => 'alert-danger'));
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
