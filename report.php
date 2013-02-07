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

$b = optional_param('b', 0, PARAM_INT);

if (! empty($b)) {
    if ($data = $DB->get_records('reader_attempts', array('quizid' => $b))) {
        if ($datapub = $DB->get_record('reader_books', array('id' => $b))) {
            $quizid = $datapub->quizid;
        }
        while (list($key,$value) = each($data)) {
            reader_put_to_quiz_attempt($value->id);
        }
    }
}

if (! empty($quizid)) {
    if ($cm = get_coursemodule_from_instance('quiz', $quizid)) {
        $quiz_report = new moodle_url('/mod/quiz/report.php', array('id' => $cm->id, 'mode' => 'responses'));
        echo '<script type="text/javascript">',"\n";
        echo '//<![CDATA['."\n";
        echo 'top.location.href="'.$quiz_report.'";'."\n";
        echo '//]]>'."\n";
        echo '</script>';
    }
} else {
    echo '<h1>No attempts found</h1>';
}
