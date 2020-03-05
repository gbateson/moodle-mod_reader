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
 * mod/reader/admin/tools/lib.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die();

/**
 * reader_get_correct_answer
 *
 * @param xxx $question (passed by reference)
 * @param xxx $questions (passed by reference)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_correct_answer(&$question, &$questions) {
    global $DB;

    // watch out for question table names:
    // Moodle <= 2.4: question_match AND question_match_sub (question)
    // Moodle >= 2.5: qtype_match_options AND qtype_match_subquestion (questionid)

    switch ($question->qtype) {

        case 'match':
            // e.g. Cambridge - Level 1: Inspector Logan
            $correct = array();

            list($table, $field) = reader_get_question_options_table($question->qtype);
            if ($records = $DB->get_records($table, array($field => $question->id))) {
                $record = reset($records); // should only be one, but just in case ...
                list($table, $field) = reader_get_question_options_table($question->qtype, true);
                if (empty($record->subquestions)) {
                    // Moodle >= 2.5
                    $select = "$field = $question->id AND questiontext <> ''";
                } else {
                    // Moodle <= 2.4
                    $select = "id IN ($record->subquestions) AND questiontext <> ''";
                }
                if ($subquestions = $DB->get_records_select($table, $select)) {
                    foreach ($subquestions as $subquestion) {
                        $correct[] = html_writer::tag('span', $subquestion->questiontext, array('class' => 'matchquestion')).' '.
                                     html_writer::tag('span', '=>',                       array('class' => 'matcharrow')).' '.
                                     html_writer::tag('span', $subquestion->answertext,   array('class' => 'matchanswer'));
                    }
                }
            }

            $correct = html_writer::alist($correct, array('class' => 'match'));
            break;

        case 'multianswer':
            // e.g. Cambridge - Level 1: Blood Diamonds
            $correct = array();

            list($table, $field) = reader_get_question_options_table($question->qtype);
            if ($records = $DB->get_records($table, array($field => $question->id))) {
                $record = reset($records); // should only be one - but sometimes there are duplicates
                $sequence = explode(',', $record->sequence);
                foreach ($sequence as $questionid) {
                    if (empty($questions[$questionid])) {
                        continue; // shouldn't happen
                    }
                    // {:MULTICHOICE:~=Kirkpatrick ~Shepherd ~Sophie Lafon ~Van Delft}
                    $correct[] = preg_replace('/^.*=([^=~}]*).*$/', '$1', $questions[$questionid]->questiontext);
                }
            }
            switch (count($correct)) {
                case 0:  $correct = ''; break;
                case 1:  $correct = array_shift($correct); break;
                default: $correct = html_writer::alist($correct, array('class' => 'multianswers'));
            }
            break;

        case 'multichoice':
            $correct = array();
            if ($answers = $DB->get_records_select('question_answers', 'question = ? AND fraction >= ?', array($question->id, 1))) {
                foreach ($answers as $answer) {
                    $correct[] = $answer->answer;
                }
            }
            switch (count($correct)) {
                case 0:  $correct = ''; break;
                case 1:  $correct = array_shift($correct); break;
                default: $correct = html_writer::alist($correct, array('class' => 'multichoice'), 'ol');
            }
            break;

        case 'ordering':
            $correct = array();
            if ($answers = $DB->get_records_select('question_answers', 'question = ?', array($question->id), 'fraction')) {
                foreach ($answers as $answer) {
                    $correct[] = $answer->answer;
                }
            }
            $prefix = array();
            foreach ($correct as $a => $answer) {
                $prefix[$a] = reader_ordering_answer_prefix($correct, $a, $answer);
            }
            foreach ($correct as $a => $answer) {
                $correct[$a] = html_writer::tag('u', substr($answer, 0, $prefix[$a])).substr($answer, $prefix[$a]);
            }
            $correct = html_writer::alist($correct, array('class' => 'ordering'), 'ol');
            break;

        case 'truefalse':
            if ($correct = $DB->get_records('question_answers', array('question' => $question->id), 'fraction DESC')) {
                $correct = reset($correct);
                $correct = $correct->answer;
            } else {
                $correct = ''; // shouldn't happen !!
            }

            break;

        case 'shortanswer':
            $correct = array();
            if ($answers = $DB->get_records_select('question_answers', 'question = ?', array($question->id), 'fraction')) {
                foreach ($answers as $answer) {
                    if (empty($answer->fraction)) {
                        continue;
                    }
                    $correct[] = $answer->answer;
                }
            }
            switch (count($correct)) {
                case 0:  $correct = ''; break;
                case 1:  $correct = html_writer::tag('div', reset($correct), array('class' => 'shortanswer')); break;
                default: $correct = html_writer::alist($correct, array('class' => 'shortanswer'), 'ul');
            }
            break;

        case 'numerical':
            $correct = array();
            if ($answers = $DB->get_records_select('question_answers', 'question = ?', array($question->id), 'fraction')) {
                foreach ($answers as $answer) {
                    if (empty($answer->fraction)) {
                        continue;
                    }
                    $params = array('question' => $question->id, 'answer' => $answer->id);
                    if ($tolerance = $DB->get_field('question_numerical', 'tolerance', $params)) {
                        $answer->answer = $answer->answer." (tolerance: $tolerance)";
                    }
                    $correct[] = $answer->answer;
                }
            }
            switch (count($correct)) {
                case 0:  $correct = ''; break;
                case 1:  $correct = array_shift($correct); break;
                default: $correct = html_writer::alist($correct, array('class' => 'numerical'), 'ol');
            }
            break;

        default:
            $correct = 'unknown qtype: '.$question->qtype.' id='.$question->id;
    }

    return $correct;
}

/**
 * reader_get_question_options_table
 *
 * @param xxx $type
 * @param xxx $sub (optional, default=false)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_get_question_options_table($type, $sub=false) {
    global $DB;

    // we need the db manager to detect the names of question options tables
    $dbman = $DB->get_manager();

    switch (true) {

        // from Moodle 2.5, the table names start to look like this
        case $dbman->table_exists('qtype_'.$type.'_options'):
            if ($sub) {
                $table = 'qtype_'.$type.'_subquestions';
            } else {
                $table = 'qtype_'.$type.'_options';
            }
            $field = 'questionid';
            break;

        // Moodle <= 2.4
        case $dbman->table_exists('question_'.$type):
            if ($sub) {
                $table = 'question_'.$type.'_sub';
            } else {
                $table = 'question_'.$type;
            }
            $field = 'question';
            break;

        default:
            $table = '';
            $field = '';
    }

    return array($table, $field);
}

/**
 * reader_ordering_answer_prefix
 *
 * @param xxx $correct
 * @param xxx $thisindex
 * @param xxx $thisanswer
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_ordering_answer_prefix($correct, $thisindex, $thisanswer) {
    $strlen = 0;
    foreach ($correct as $a => $answer) {
        if ($a==$thisindex) {
            continue;
        }
        $i_max = min(strlen($thisanswer), strlen($answer));
        for ($i=0; $i<$i_max; $i++) {
            if ($answer[$i] != $thisanswer[$i]) {
                break;
            }
        }
        // $i is the position of the last identical char
        $strlen = max($strlen, $i);
    }

    // get next space after $strlen
    if ($strlen = strpos($thisanswer, ' ', $strlen)) {
        return $strlen;
    } else {
        return strlen($thisanswer);
    }
}

/**
 * reader_quiz_courseids
 *
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_quiz_courseids() {
    global $DB;

    $courseids = array();

    if ($courseid = get_config('mod_reader', 'reader_usecourse')) { // old config name
        if ($DB->record_exists('course', array('id' => $courseid))) {
            $courseids[] = $courseid;
        }
    }
    if ($courseid = get_config('mod_reader', 'usecourse')) { // new config name
        if ($DB->record_exists('course', array('id' => $courseid))) {
            $courseids[] = $courseid;
        }
    }

    // $select = 'SELECT DISTINCT usecourse FROM {reader} WHERE usecourse IS NOT NULL AND usecourse > ?';
    $select = 'SELECT DISTINCT q.course FROM {reader_books} rb LEFT JOIN {quiz} q ON rb.quizid = q.id WHERE q.id IS NOT NULL';
    $select = "id IN ($select)"; // AND visible = ?
    if ($courses = $DB->get_records_select('course', $select, null, 'id', 'id,visible')) {
        $courseids = array_merge($courseids, array_keys($courses));
        $courseids = array_unique($courseids);
        sort($courseids);
    }

    return $courseids;
}

/**
 * reader_curlfile
 *
 * @param xxx $url
 * @return xxx
 * @todo Finish documenting this function
 *       use "download_file_content()" instead of curl
 *       mod/reader/admin/books/download/remotesite.php
 */
function reader_curlfile($url) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    //curl_setopt($ch, CURLOPT_REFERER, trackback_url(false));

    $result = curl_exec($ch);
    curl_close($ch);

    if (empty($result)) {
        return false;
    } else {
        return explode('\n', $result);
    }
}

/**
 * reader_reset_timeout
 *
 * @param xxx $moretime (optional, default=300)
 * @todo Finish documenting this function
 */
function reader_reset_timeout($moretime=300) {
    static $timeout = 0;
    $time = time();
    if ($timeout < $time) {
        $timeout = ($time + round($moretime * 0.9));
        set_time_limit($moretime);
    }
}

/**
 * reader_print_all_done
 *
 * @todo Finish documenting this function
 */
function reader_print_all_done() {
    echo html_writer::tag('p', get_string('alldone', 'mod_reader'));
}

/**
 * reader_print_continue
 *
 * @todo Finish documenting this function
 */
function reader_print_continue($id, $tab) {
    if ($id) {
        $href = new moodle_url('/mod/reader/admin/tools.php', array('id' => $id, 'tab' => $tab));
    } else {
        $href = new moodle_url('/');
    }
    echo html_writer::tag('p', html_writer::tag('a', 'Click here to continue', array('href' => $href)));
}
