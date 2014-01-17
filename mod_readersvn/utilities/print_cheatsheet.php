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
 * mod/reader/utilities/print_cheatsheet.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot.'/mod/reader/lib.php');

require_login(SITEID);
if (class_exists('context_system')) {
    $context = context_system::instance();
} else {
    $context = get_context_instance(CONTEXT_SYSTEM);
}
require_capability('moodle/site:config', $context);

// $SCRIPT is set by initialise_fullme() in 'lib/setuplib.php'
// it is the path below $CFG->wwwroot of this script
$PAGE->set_url($CFG->wwwroot.$SCRIPT);

// set title
$title = 'Print cheat sheet';
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo $OUTPUT->box_start();

reader_print_style();

$selected_publishers = reader_optional_param_array('publishers', array(), PARAM_TEXT);
$selected_publishers = (array)$selected_publishers;
array_walk($selected_publishers, 'strip_tags');

// start form
echo html_writer::start_tag('form', array('action' => '', 'method' => 'post'));

// select publisher - level
$publisher_level = $DB->sql_concat('publisher', "' - '", 'level');
$publisher_level = "(CASE WHEN (level IS NULL OR level = '' OR level = '--') THEN publisher ELSE $publisher_level END) AS publisher_level";

$select  = $publisher_level.', publisher, level, ROUND(SUM(difficulty) / COUNT(*)) AS average_difficulty';
$from    = '{reader_books}';
$where   = 'publisher <> ?';
$params  = array('Extra Points');
$groupby = 'publisher, level';
$orderby = 'publisher, average_difficulty, level';
if ($publishers = $DB->get_records_sql("SELECT $select FROM $from WHERE $where GROUP BY $groupby ORDER BY $orderby", $params)) {
    foreach ($publishers as $p => $publisher) {
        $publishers[$p] = "[RL $publisher->average_difficulty] $p";
    }
    $count = count($publishers);
    if ($count > 1) {
        $params = array('multiple' => 'multiple', 'size' => min(10, $count));
    } else {
        $params = null;
    }
    echo html_writer::select($publishers, 'publishers[]', $selected_publishers, null, $params);
}

$selected_books = reader_optional_param_array('books', array(), PARAM_TEXT);
$selected_books = (array)$selected_books;
array_walk($selected_books, 'strip_tags');

// select book
if (count($selected_publishers)) {
    $select = array();
    $params = array();
    foreach ($selected_publishers as $publisher) {
        if ($pos = strrpos($publisher, ' - ')) {
            $level = trim(substr($publisher, $pos + 2));
            $publisher = trim(substr($publisher, 0, $pos ));
            $select[] = "publisher = ? AND level = ?";
            array_push($params, $publisher, $level);
        } else {
            $select[] = "publisher = ? AND (level = ? OR level = ?)";
            array_push($params, $publisher, '', '--');
        }
    }

    $select = '('.implode(') OR (', $select).')';
    if ($books = $DB->get_records_select('reader_books', $select, $params, 'publisher,level,name')) {
        foreach ($books as $book) {
            $books[$book->id] = $book->name;
        }
    }
    $count = count($books);
    if ($count > 1) {
        $params = array('multiple' => 'multiple', 'size' => min(10, $count));
    } else {
        $params = null;
    }
    echo html_writer::select($books, 'books[]', $selected_books, null, $params);
}

echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('go')));
echo html_writer::end_tag('form');

if (count($selected_books)) {
    list($select, $params) = $DB->get_in_or_equal($selected_books);
    $sort = 'publisher,series,level,name';
    $fields = 'id,publisher,series,level,name,quizid';
    if ($books = $DB->get_records_select('reader_books', 'id '.$select, $params, $sort, $fields)) {
        reader_print_books($books);
    }
}

echo html_writer::tag('p', 'All done');
echo html_writer::tag('p', html_writer::tag('a', 'Click here to continue', array('href' => $CFG->wwwroot.'/mod/reader/utilities/index.php')));

echo $OUTPUT->box_end();
echo $OUTPUT->footer();

///////////////////////////////////////////////////////////////////////////////
// functions only below this line
///////////////////////////////////////////////////////////////////////////////

function reader_print_style() {
    $style = '';
    $style .= "p {\n";
    $style .= "    clear: both;\n";
    $style .= "}\n";
    $style .= "ul.publishers {\n";
    $style .= "    clear: both;\n";
    $style .= "}\n";
    $style .= "li.publisher {\n";
    $style .= "    clear: both;\n";
    $style .= "}\n";
    $style .= "li.publisher b {\n";
    $style .= "    font-size: 1.4;\n";
    $style .= "}\n";
    $style .= "ul.books {\n";
    $style .= "    clear: both;\n";
    $style .= "}\n";
    $style .= "li.book {\n";
    $style .= "    clear: both;\n";
    $style .= "    padding-top: 12px;\n";
    $style .= "}\n";
    $style .= "li.book b {\n";
    $style .= "    font-size: 1.3;\n";
    $style .= "}\n";
    $style .= "ul.categories {\n";
    $style .= "    clear: both;\n";
    $style .= "}\n";
    $style .= "li.category {\n";
    $style .= "    clear: both;\n";
    $style .= "    padding-top: 12px;\n";
    $style .= "}\n";
    $style .= "li.category b {\n";
    $style .= "    font-size: 1.2;\n";
    $style .= "}\n";
    $style .= "dl.questions {\n";
    $style .= "    clear: both;\n";
    $style .= "    padding: 6px 0px;\n";
    $style .= "    width: 600px;\n";
    $style .= "}\n";
    $style .= "dt.questiontext {\n";
    $style .= "    border-top: dashed 1px #999999;\n";
    $style .= "    clear: left;\n";
    $style .= "    float: left;\n";
    $style .= "    font-size: 1.1;\n";
    $style .= "    margin: 0px;\n";
    $style .= "    padding: 6px 0px;\n";
    $style .= "    width: 300px;\n";
    $style .= "}\n";
    $style .= "dd.correctanswer {\n";
    $style .= "    border-top: dashed 1px #999999;\n";
    $style .= "    clear: right;\n";
    $style .= "    color: #00CC00;\n";
    $style .= "    float: left;\n";
    $style .= "    font-weight: bold;\n";
    $style .= "    margin: 0px;\n";
    $style .= "    padding: 6px 0px;\n";
    $style .= "    width: 300px;\n";
    $style .= "}\n";
    $style .= "dd.correctanswer ol {\n";
    $style .= "    color: #000000;\n";
    $style .= "}\n";
    $style .= "ul.match li span.matchquestion {\n";
    $style .= "}\n";
    $style .= "ul.match li span.matcharrow {\n";
    $style .= "    color: black;\n";
    $style .= "    font-weight: normal;\n";
    $style .= "}\n";
    $style .= "ul.match li span.matchanswer {\n";
    $style .= "}\n";
    $style .= "ul.match,\n";
    $style .= "ul.multianswers,\n";
    $style .= "ol.ordering {\n";
    $style .= "    margin: 0px;\n";
    $style .= "    padding: 0px;\n";
    $style .= "}\n";
    $style .= "ul.match li,\n";
    $style .= "ul.multianswers li,\n";
    $style .= "ol.ordering li {\n";
    $style .= "    margin-left: 18px;\n";
    $style .= "    padding: 0px;\n";
    $style .= "}\n";
    $style .= ".dark {\n";
    $style .= "    background-color: #dddddd;\n";
    $style .= "}\n";
    $style .= ".light {\n";
    $style .= "    background-color: #eeeeee;\n";
    $style .= "}\n";
    echo html_writer::tag('style', $style, array('type' => 'text/css'));
}

function reader_print_books($books) {
    global $DB;

    // search string for extraneous text in "Who said ..." question text
    $who_said_search = array('Who did they say it to?',
                             'Who is it said to?',
                             'Who said this to who?',
                             'Who said this?',
                             'Who says this?',
                             'Who was it said to?',
                             '_ said this to _.',
                             '_ said this to _',
                             '_ says this to _',
                             '_', '"');
    $who_said_search = array_map('preg_quote', $who_said_search);
    $who_said_search[] = '\{[^}]*\}';
    $who_said_search[] = 'Who did \S+ say this to\?';
    $who_said_search = '/(('.implode(')|(', $who_said_search).'))\s*/i';
    $who_said_search = str_replace(' ', ' +', $who_said_search); // allow for variable whitespace
    $who_said_search = str_replace('_', '_+', $who_said_search); // and any number of underscores

    $publisher_level = '';
    foreach ($books as $book) {

        $book->publisher_level = $book->publisher;
        if ($book->level) {
            $book->publisher_level .= ' - '.$book->level;
        }

        if ($publisher_level=='' || $publisher_level != $book->publisher_level) {
            if ($publisher_level=='') {
                echo html_writer::start_tag('ul', array('class' => 'publishers')); // start publisher list
            } else {
                echo html_writer::end_tag('ul');   // finish book list
                echo html_writer::end_tag('li');   // finish publisher
            }
            echo html_writer::start_tag('li', array('class' => 'publisher'));     // start publisher
            echo 'PUBLISHER: '.html_writer::tag('b', $book->publisher_level);
            echo html_writer::start_tag('ul', array('class' => 'books'));         // start book list
        }

        // set current publisher and level
        $publisher_level = $book->publisher_level;

        echo html_writer::start_tag('li', array('class' => 'book'));              // start book
        echo 'BOOK: '.html_writer::tag('b', $book->name);

        $href = new moodle_url('/mod/quiz/view.php', array('q' => $book->quizid));
        $params = array('href' => $href, 'onclick' => 'this.target="_blank"');
        echo ' &nbsp; '; // white space
        echo html_writer::tag('a', get_string('modulename', 'quiz'), $params);

        // set these just in case something goes wrong
        $params = array();
        $categoryids = '=0';

        // select all questions in categories used by this $book's quiz
        if ($questionids = $DB->get_records_sql("SELECT DISTINCT question FROM {quiz_question_instances} WHERE quiz = ?", array($book->quizid))) {
            list($select, $params) = $DB->get_in_or_equal(array_keys($questionids));
            if ($categoryids = $DB->get_records_select('question', "id $select", $params, 'id', 'DISTINCT category')) {
                list($categoryids, $params) = $DB->get_in_or_equal(array_keys($categoryids));
            }
        }
        $select = 'q.*, qc.name AS categoryname';
        $from   = '{question} q LEFT JOIN {question_categories} qc ON q.category = qc.id ';
        $where  = "q.category $categoryids AND q.hidden = ? AND q.qtype <> ?";
        array_push($params, 0, 'random');

        $qtype = '';
        $dark = true;
        $category = 0;
        $who_said = false;
        if ($questions = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY q.category,q.qtype,q.questiontext", $params)) {
            foreach ($questions as $question) {
                if ($question->qtype=='description' || ($question->qtype=='multichoice' && $question->parent)) {
                    continue;
                }

                $questiontext = preg_replace('/\{#[0-9]+\}/', '___', $question->questiontext);
                $questiontext = preg_replace('/\s*<[^>]*>\s*/', ' ', $questiontext); // strip_tags

                if ($category==0 || $category != $question->category) {
                    $who_said = false;
                    $categoryname = strtolower($question->categoryname);
                    if (strpos($categoryname, 'default')===false) {
                        if (strpos($categoryname, 'who')===false || preg_match('/WhoDoes|WhoDid|WhoInStory|WhoWas/i', $categoryname)) {
                            // do nothing
                        } else {
                            $who_said = true;
                        }
                    }
                }

                // tidy up "Who said" questions
                if ($who_said) {
                    $questiontext = preg_replace($who_said_search, '', $questiontext);
                    if (! $questiontext = trim($questiontext)) {
                        $questiontext = preg_replace($who_said_search, '', $question->name);
                    }
                }

                if ($category==0 || $category != $question->category) {
                    if ($category==0) {
                        echo html_writer::start_tag('ul', array('class' => 'categories')); // start category list
                    } else {
                        echo html_writer::end_tag('dl'); // finish question list
                        echo html_writer::end_tag('li'); // finish category
                    }
                    echo html_writer::start_tag('li', array('class' => 'category')); // start category
                    echo 'QUESTION CATEGORY: '.html_writer::tag('b', $question->categoryname);
                    if ($who_said) {
                        echo ' - Who said this? Who was it said to?';
                    }
                    echo html_writer::start_tag('dl', array('class' => 'questions')); // start question list
                }
                $category = $question->category;

                // toggle dark bg color switch
                $dark = ! $dark;

                echo html_writer::tag('dt', trim($questiontext), array('class' => 'questiontext '.($dark ? 'dark' : 'light')));

                $correct = reader_get_correct_answer($question, $questions);
                echo html_writer::tag('dd', $correct, array('class' => 'correctanswer '.($dark ? 'dark' : 'light')));

            }
        }

        if ($category) {
            echo html_writer::end_tag('dl'); // finish question list
            echo html_writer::end_tag('li'); // finish category
            echo html_writer::end_tag('ul'); // finish catgory list
        }

        echo html_writer::end_tag('li'); // finish book
    }

    if ($publisher_level) {
        echo html_writer::end_tag('ul'); // finish book list
        echo html_writer::end_tag('li'); // finish publisher
        echo html_writer::end_tag('ul'); // finish publisher list
    }
}

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
                    $select = "id IN ($subquestions) AND questiontext <> ''";
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
            list($table, $field) = reader_get_question_options_table($question->qtype);
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
            list($table, $field) = reader_get_question_options_table($question->qtype);
            if ($records = $DB->get_records_sql('SELECT trueanswer FROM {'.$table.'} WHERE '.$field.' = ?', array($question->id))) {
                $correct = $DB->get_field_select('question_answers', 'answer', 'id = ?', array(key($records)));
            } else {
                $correct = ''; // shouldn't happen !!
            }

            break;

        default:
            $correct = $question->qtype.' id='.$question->id;
    }

    return $correct;
}

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

function report_microtime($msg='') {
    static $a = 0;
    if ($a==0) {
        $a = microtime();
    } else {
        $b = microtime();
        list($a_dec, $a_sec) = explode(' ', $a);
        list($b_dec, $b_sec) = explode(' ', $b);
        $duration = $b_sec - $a_sec + $b_dec - $a_dec;
        $duration = sprintf('%0.3f', $duration);
        if ($msg) {
            $msg = "$msg: ";
        }
        echo "<li>$msg$duration seconds and counting</li>";
    }
}
