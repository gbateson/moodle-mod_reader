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
 * mod/reader/view_loginas.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once('../../config.php');
require_once($CFG->dirroot.'/mod/reader/locallib.php');
require_once($CFG->dirroot.'/mod/reader/renderer.php');

$tab = mod_reader_renderer::TAB_REPORTS;
$mode = 'usersummary';

$id       = optional_param('id',      0, PARAM_INT); // course module id
$r        = optional_param('r',       0, PARAM_INT); // reader id
$userid   = optional_param('userid',  0, PARAM_INT); // user id
$tab      = optional_param('tab',     $tab, PARAM_INT); // navigation tab id
$mode     = optional_param('mode',    $mode, PARAM_ALPHA); // navigation mode

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

$url = new moodle_url('/mod/reader/view_loginas.php', array('id' => $id, 'userid' => $userid));
$PAGE->set_url($url);

require_course_login($course, true, $cm);

reader_add_to_log($course->id, 'reader', 'loginas', 'view.php?id='.$cm->id, $reader->id, $cm->id);

if (mod_reader::is_loggedinas()) {
    require_sesskey();
    require_logout();
    if ($userid) {
        // from admin/reports.php (teacher) to view.php (student)
        $params = array('id' => $id, 'tab' => $tab, 'mode' => $mode, 'userid' => $userid);
        $url = new moodle_url('/mod/reader/view_loginas.php', $params);
    } else {
        // from view.php (student) to admin/reports.php (teacher)
        $params = array('id' => $id, 'tab' => $tab, 'mode' => $mode);
        $url = new moodle_url('/mod/reader/admin/reports.php', $params);
    }
    redirect($url);
}

$context = reader_get_context(CONTEXT_COURSE, $course->id);
require_capability('mod/reader:manageusers', $context);

// Login as this user and return to course home page.
mod_reader::loginas($userid, $context);

$url = new moodle_url('/mod/reader/view.php', array('id' => $id));
redirect($url);
