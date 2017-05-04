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
 * @copyright  2013 xxx (xxx@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once('../../../config.php');
require_once($CFG->dirroot.'/mod/reader/lib.php');
require_once($CFG->dirroot.'/mod/reader/quiz/accessrules.php');
require_once($CFG->dirroot.'/mod/reader/quiz/attemptlib.php');
require_once($CFG->dirroot.'/question/engine/lib.php');

// Get submitted parameters.
$attemptid = required_param('attempt', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);

$readerattempt = reader_attempt::create($attemptid);
$timenow = time();

$page = $readerattempt->force_page_number_into_range($page);
$PAGE->set_url($readerattempt->attempt_url(0, $page));

// Check login.
require_login($readerattempt->get_course(), false, $readerattempt->get_cm());

// Check that this attempt belongs to this user.
if ($readerattempt->get_userid() != $USER->id) {
    if ($readerattempt->has_capability('mod/reader:viewreports')) {
        redirect($readerattempt->review_url(0, $page));
    } else {
        throw new moodle_reader_exception($readerattempt->get_readerquiz(), 'notyourattempt');
    }
}

navigation_node::override_active_url($readerattempt->start_attempt_url());

// If the attempt is already closed, send them to the review page.

if ($readerattempt->is_finished()) {
    redirect($readerattempt->review_url(0, $page));
}

// set renderer (mod_quiz_renderer)
$output = $PAGE->get_renderer('mod_quiz');

// Check the access rules.
$accessmanager = $readerattempt->get_access_manager($readerattempt->get_readerquiz(), $timenow);
$messages = $accessmanager->prevent_access();
$accessmanager->do_password_check($readerattempt->is_preview_user());

$logaction = 'view attempt: '.substr($readerattempt->readerquiz->book->name, 0, 26); // 40 char limit
$loginfo   = 'readerID '.$readerattempt->get_readerid().'; reader quiz '.$readerattempt->get_quizid().'; page: '.($page + 1);
reader_add_to_log($readerattempt->get_courseid(), 'reader', $logaction, 'view.php?id='.$readerattempt->get_cmid(), $readerattempt->get_readerid(), $readerattempt->get_cmid());

// Get the list of questions needed by this page.
$slots = $readerattempt->get_slots($page);

// Check.
if (empty($slots)) {
    throw new moodle_reader_exception($readerattempt->get_readerquiz(), 'noquestionsfound');
}

// Update attempt page, redirecting the user if $page is not valid.
if (! $readerattempt->set_currentpage($page)) {
    redirect($readerattempt->attempt_url(null, $readerattempt->get_currentpage()));
}


// Initialise the JavaScript.
$headtags = $readerattempt->get_html_head_contributions($page);
$PAGE->requires->js_init_call('M.mod_quiz.init_attempt_form', null, false, reader_get_js_module());

// Arrange for the navigation to be displayed.
$headtags = $readerattempt->get_html_head_contributions($page);
$PAGE->set_heading($readerattempt->get_course()->fullname);
$PAGE->set_title(format_string($readerattempt->get_reader_name()));

//echo $OUTPUT->header();

if ($readerattempt->is_last_page($page)) {
    $nextpage = -1;
} else {
    $nextpage = $page + 1;
}

//$PAGE->requires->js_init_call('M.mod_quiz.init_attempt_form', null, false, reader_get_js_module());

$js = 'RDR = new Object();';

$js .= "RDR.AddCssText = function(txt) {\n";
$js .= "    var obj = document.createElement('style');\n";
$js .= "    obj.setAttribute('type', 'text/css');\n";
$js .= "    if (obj.styleSheet) {\n";
$js .= "        obj.styleSheet.cssText = txt;\n";
$js .= "    } else {\n";
$js .= "        obj.appendChild(document.createTextNode(txt));\n";
$js .= "    }\n";
$js .= "    document.getElementsByTagName('head')[0].appendChild(obj);\n";
$js .= "}\n";

$js .= "RDR.AddCssUrl = function(url) {\n";
$js .= "    var obj = document.createElement('link');\n";
$js .= "    obj.setAttribute('rel', 'stylesheet');\n";
$js .= "    obj.setAttribute('type', 'text/css');\n";
$js .= "    obj.setAttribute('href', url);\n";
$js .= "    document.getElementsByTagName('head')[0].appendChild(obj);\n";
$js .= "}\n";

$js .= "RDR.timer = new Object();\n";
$js .= "RDR.timer.PadNumber = function(time) {\n";
$js .= "   return ((time < 10 ? '0' : '') + time);\n";
$js .= "}\n";
$js .= "RDR.timer.UpdateTimer = function(secs) {\n";
$js .= "    var days = Math.floor(secs / 86400);\n";
$js .= "    secs -= (days * 86400);\n";
$js .= "    var hours = Math.floor(secs / 3600);\n";
$js .= "    secs -= (hours * 3600);\n";
$js .= "    var mins = Math.floor(secs / 60);\n";
$js .= "    secs -= (mins * 60);\n";
$js .= "    var str = '';\n";
$js .= "    if (days > 0) {\n";
$js .= "        str += days + ' days ';\n";
$js .= "    }\n";
$js .= "    if (hours > 0) {\n";
$js .= "        str += RDR.timer.PadNumber(hours) + ':';\n";
$js .= "    }\n";
$js .= "    str += RDR.timer.PadNumber(mins) + ':' + RDR.timer.PadNumber(secs);\n";
$js .= "    document.getElementById('readerquiztimer').innerHTML = str;\n";
$js .= "}\n";
$js .= "RDR.timer.ShowTimer = function() {\n";
$js .= "    RDR.timer.timeelapsed++;\n";
$js .= "    var timeremaining = RDR.timer.timeallowed - RDR.timer.timeelapsed;\n";
$js .= "    if (timeremaining <= 0) {\n";
$js .= "        clearInterval(RDR.timer.timer);\n";
$js .= "        var obj = document.getElementById('responseform');\n";
$js .= "        if (obj) {\n";
$js .= "            if (obj.elements && obj.elements['timeup']) {\n";
$js .= "                obj.elements['timeup'].value = 1;\n";
$js .= "            }\n";
$js .= "            if (obj.submit) {\n";
$js .= "                obj.submit();\n";
$js .= "            }\n";
$js .= "            obj = null;\n";
$js .= "        }\n";
$js .= "    } else {\n";
$js .= "        RDR.timer.UpdateTimer(timeremaining);\n";
$js .= "    }\n";
$js .= "}\n";
$js .= "RDR.timer.CreateTimer = function() {\n";
$js .= "    RDR.AddCssText('".
               '#page-mod-reader-quiz-attempt #readerquiztimer {'.
                   'position: fixed;'.
                   'width: 117px;'.
                   'height: 54px;'.
                   'bottom: 0;'.
                   'z-index: 1000;'.
                   'background-color: #ffffff;'.
                   'border: 1px solid #dddddd;'.
                   'font-size: 27px;'.
                   'text-align: center;'.
                   'padding-top: 20px;'.
               '}'.
            "');\n";
$js .= "    var obj = document.createElement('div');\n";
$js .= "    obj.setAttribute('id', 'readerquiztimer');\n";
$js .= "    document.body.appendChild(obj);\n";
$js .= "}\n";
$js .= "RDR.timer.StartTimer = function(timeallowed) {\n";
$js .= "   RDR.timer.CreateTimer();\n";
$js .= "   RDR.timer.timeelapsed = 0;\n";
$js .= "   RDR.timer.timeallowed = timeallowed;\n";
$js .= "   RDR.timer.timer = setInterval(RDR.timer.ShowTimer, 1000);\n";
$js .= "}\n";

if ($readerattempt->readerquiz->reader->timelimit > 0) {
    $totaltimertime = $readerattempt->readerquiz->reader->timelimit;
    $totaltimertime -= ($timenow - $readerattempt->attempt->timestart);
    if ($totaltimertime < 0) {
        $totaltimertime = 0;
    }
    $js .= "RDR.timer.StartTimer($totaltimertime)\n";
}

if ($readerattempt->readerquiz->reader->questionscores==0) {
    $js .= "RDR.AddCssText('#page-mod-reader-quiz-attempt div.info div.grade {display: none;}');\n";
}

if ($js) {
    $PAGE->requires->js_init_code($js, true);
}

$_SESSION['SESSION']->reader_lastattemptpage = $_SERVER['QUERY_STRING'];

echo $output->attempt_page($readerattempt, $page, $accessmanager, $messages, $slots, $id, $nextpage);
