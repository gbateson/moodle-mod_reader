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
 * mod/reader/quiz/attempt.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/**
 * mod/reader/quiz/attempt.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 xxx (xxx@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once(dirname(__FILE__).'/../../../config.php');
require_once($CFG->dirroot.'/mod/reader/quiz/attemptlib.php');
require_once($CFG->dirroot.'/mod/reader/lib.php');
require_once($CFG->dirroot.'/mod/reader/quiz/accessrules.php');
require_once($CFG->dirroot.'/question/engine/lib.php');

// Get submitted parameters.
$attemptid = required_param('attempt', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);

$attemptobj = reader_attempt::create($attemptid);

$PAGE->set_url($attemptobj->attempt_url(0, $page));

// Check login.
require_login($attemptobj->get_course(), false, $attemptobj->get_cm());

// Check that this attempt belongs to this user.
if ($attemptobj->get_userid() != $USER->id) {
    if ($attemptobj->has_capability('mod/reader:viewreports')) {
        redirect($attemptobj->review_url(0, $page));
    } else {
        throw new moodle_reader_exception($attemptobj->get_readerobj(), 'notyourattempt');
    }
}

navigation_node::override_active_url($attemptobj->start_attempt_url());

// If the attempt is already closed, send them to the review page.

if ($attemptobj->is_finished()) {
    redirect($attemptobj->review_url(0, $page));
}

// Check the access rules.
$output = $PAGE->get_renderer('mod_quiz');
$accessmanager = $attemptobj->get_access_manager(time());
$messages = $accessmanager->prevent_access();

$pagetext = $page + 1;
$logaction = 'view attempt: '.substr($attemptobj->readerobj->book->name, 0, 26); // 40 char limit
$loginfo   = "readerID {$attemptobj->readerobj->reader->id}; ".
             "reader quiz {$attemptobj->readerobj->book->id}; ".
             "page: {$pagetext}";
add_to_log($attemptobj->readerobj->course->id, 'reader', $logaction, "view.php?id=$id", $loginfo);

// Get the list of questions needed by this page.
$slots = $attemptobj->get_slots($page);

// Check.
if (empty($slots)) {
    throw new moodle_reader_exception($attemptobj->get_readerobj(), 'noquestionsfound');
}

// Initialise the JavaScript.
$headtags = $attemptobj->get_html_head_contributions($page);
$PAGE->requires->js_init_call('M.mod_quiz.init_attempt_form', null, false, reader_get_js_module());

// Arrange for the navigation to be displayed.
$headtags = $attemptobj->get_html_head_contributions($page);
$PAGE->set_heading($attemptobj->get_course()->fullname);
$PAGE->set_title(format_string($attemptobj->get_reader_name()));

//echo $OUTPUT->header();

if ($attemptobj->is_last_page($page)) {
    $nextpage = -1;
} else {
    $nextpage = $page + 1;
}

//print_r ($page);
//die;

//print_r ($attemptobj);

$accessmanager->show_attempt_timer_if_needed($attemptobj->get_attempt(), time());

if ($attemptobj->readerobj->reader->timelimit > 0) {
    //print_r ($attemptobj);
    //echo $attemptobj->readerobj->reader->timelimit."/";
    //echo $attemptobj->attempt->timestart;
    $totaltimertime = $attemptobj->readerobj->reader->timelimit * 60 - (time() - $attemptobj->attempt->timestart);
    if ($totaltimertime < 0) $totaltimertime = 0; {
        echo '<script type="text/javascript">'."\n";
        echo '//<![CDATA['."\n";

        echo "var timeminuse = 0;\n";
        echo "var totaltime  = $totaltimertime;\n";

        echo 'function showDiv() {'."\n";
        echo '    timeminuse = timeminuse + 1;'."\n";
        echo '    var timer = totaltime - timeminuse;'."\n";
        echo '    if (timer >= 0) {'."\n";
        echo '        UpdateTimer(timer);'."\n";
        echo '    }'."\n";
        echo '}'."\n";

        echo 'function UpdateTimer(Seconds) {'."\n";
        echo '    var Days = Math.floor(Seconds / 86400);'."\n";
        echo '    Seconds -= Days * 86400;'."\n";
        echo '    var Hours = Math.floor(Seconds / 3600);'."\n";
        echo '    Seconds -= Hours * (3600);'."\n";
        echo '    var Minutes = Math.floor(Seconds / 60);'."\n";
        echo '    Seconds -= Minutes * (60);'."\n";
        echo '    var TimeStr = ((Days > 0) ? Days + " days " : "") + ((Hours > 0) ? LeadingZero(Hours) + ":" : "") + LeadingZero(Minutes) + ":" + LeadingZero(Seconds);'."\n";
        echo '    document.getElementById("fixededit").innerHTML = TimeStr;'."\n";
        echo '}'."\n";

        echo 'function LeadingZero(Time) {'."\n";
        echo '   return (Time < 10) ? "0" + Time : + Time;'."\n";
        echo '}'."\n";

        echo 'var timer = setInterval(showDiv, 1000);'."\n";
        echo '//]]>'."\n";
        echo '</script>'."\n";

        echo '<style type="text/css">'."\n";
        echo '#fixededit {'."\n";
        echo '    position    : fixed;'."\n";
        echo '    width       : 117px;'."\n";
        echo '    height      : 54px;'."\n";
        echo '    bottom      : 0;'."\n";
        echo '    z-index     : 1000;'."\n";
        echo '    background-color: #ffffff;'."\n";
        echo '    border      : 1px solid #dddddd;'."\n";
        echo '    font-size   : 27px;'."\n";
        echo '    text-align  : center;'."\n";
        echo '    padding-top : 20px;'."\n";
        echo '}'."\n";
        echo '</style>'."\n";
        echo html_writer::tag('div', '', array('id' => 'fixededit'));
    }
}

$_SESSION['SESSION']->reader_lastattemptpage = $_SERVER['QUERY_STRING'];

echo $output->attempt_page($attemptobj, $page, $accessmanager, $messages, $slots, $id, $nextpage);

//print_r ($_SESSION['SESSION']);
//echo $OUTPUT->footer();
