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
 * mod/reader/admin/users/export.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once('../../../../../config.php');
require_once($CFG->dirroot.'/mod/reader/admin/users/export_form.php');

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

$mform = new mod_reader_admin_users_export_form();
if ($data = $mform->get_submitted_data()) {
    $filename = $data->filename;

    $select = 'ra.*, u.username, rb.image, rl.currentlevel';
    $from   = '{reader_attempts} ra '.
              'JOIN {user} u ON ra.userid = u.id '.
              'JOIN {reader_books} rb ON ra.quizid = rb.quizid '.
              'JOIN {reader_levels} rl ON ra.userid = rl.userid';
    $where  = 'reader = ?';
    $order  = 'ra.userid, ra.quizid, ra.timefinish, ra.uniqueid DESC';
    $params = array($reader->id);

    if ($attempts = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $order", $params)) {

        header('Content-Type: text/plain; filename="'.$filename.'"');

        $userid = 0;
        $quizid = 0;
        $timefinish = 0;

        foreach ($attempts as $attempt) {

            // ignore lower uniqueids with same userid/quizid/timefinish
            if ($attempt->userid==$userid && $attempt->quizid==$quizid && $attempt->timefinish==$timefinish) {
                continue;
            }

            $userid = $attempt->userid;
            $quizid = $attempt->quizid;
            $timefinish = $attempt->timefinish;

            echo $attempt->username.','.
                 $attempt->uniqueid.','.
                 $attempt->attempt.','.
                 $attempt->sumgrades.','.
                 $attempt->percentgrade.','.
                 $attempt->bookrating.','.
                 $attempt->ip.','.
                 $attempt->image.','.
                 $attempt->timefinish.','.
                 $attempt->passed.','.
                 $attempt->percentgrade.','.
                 $attempt->currentlevel."\n";
        }
    }
}
