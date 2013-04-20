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
 * mod/reader/report.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once('../../config.php');
require_once($CFG->dirroot.'/mod/reader/lib.php');

$quizid = 0;
if ($b = optional_param('b', 0, PARAM_INT)) {
    $book = $DB->get_record('reader_books', array('id' => $b));
    if ($readerattempts = $DB->get_records('reader_attempts', array('quizid' => $book->quizid))) {
        foreach ($readerattempts as $readerattempt) {
            reader_copy_to_quizattempt($readerattempt);
        }
    }
    $quizid = $book->quizid;
}

if ($quizid) {
    if ($cm = get_coursemodule_from_instance('quiz', $quizid)) {
        $quiz_report = new moodle_url('/mod/quiz/report.php', array('id' => $cm->id, 'mode' => 'responses'));
        echo '<script type="text/javascript">',"\n";
        echo '//<![CDATA['."\n";
        echo 'top.location.href="'.$quiz_report.'";'."\n";
        echo '//]]>'."\n";
        echo '</script>';
    }
} else {
    $OUTPUT->header;
    echo '<h1>No attempts found</h1>';
    $OUTPUT->footer;
}
