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
 * mod/reader/admin/tools/fix_missingquizzes.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

// try setting this to true to download missing quizzes
define('READER_DOWNLOAD_MISSING_QUIZZES', false);

/** Include required files */
require_once('../../../../config.php');

$id  = optional_param('id',  0, PARAM_INT);
$tab = optional_param('tab', 0, PARAM_INT);

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
$title = get_string('fix_missingquizzes', 'reader');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo $OUTPUT->box_start();

echo '<script type="text/javascript">'."\n";
echo "//<![CDATA[\n";
echo "function showhide_list(img) {\n";
echo "    var obj = img.nextSibling;\n";
echo "    if (obj) {\n";
echo "        if (obj.style.display=='none') {\n";
echo "            obj.style.display = '';\n";
echo "            var pix = 'minus';\n";
echo "        } else {\n";
echo "            obj.style.display = 'none';\n";
echo "            var pix = 'plus';\n";
echo "        }\n";
echo "        img.alt = 'switch_' + pix;\n";
echo "        img.src = img.src.replace(new RegExp('switch_[a-z]+'), 'switch_' + pix);\n";
echo "    }\n";
echo "}\n";
echo "function showhide_lists() {\n";
echo "    var img = document.getElementsByTagName('img');\n";
echo "    if (img) {\n";
echo "        var targetsrc = new RegExp('switch_(minus|plus)');\n";
echo "        var i_max = img.length;\n";
echo "        for (var i=0; i<=i_max; i++) {\n";
echo "            if (img[i].src.match(targetsrc)) {\n";
echo "                showhide_list(img[i]);\n";
echo "            }\n";
echo "        }\n";
echo "    }\n";
echo "}\n";
echo "if (window.addEventListener) {\n";
echo "    window.addEventListener('load', showhide_lists, false);\n";
echo "} else if (window.attachEvent) {\n";
echo "    window.attachEvent('onload', showhide_lists);\n";
echo "} else {\n";
echo "    // window['onload'] = showhide_lists;\n";
echo "}\n";
echo "//]]>\n";
echo '</script>'."\n";

$reader_usecourse = get_config('reader', 'usecourse');
$course = $DB->get_record('course', array('id' => $reader_usecourse));
$rebuild_course_cache = false;

// ================================
// fix duplicate books
// ================================

$src = $OUTPUT->pix_url('t/switch_minus');
$img = html_writer::empty_tag('img', array('src' => $src, 'onclick' => 'showhide_list(this)', 'alt' => 'switch_minus'));

// extract all duplicate (i.e. same publisher and name) books
$publisher_level_name = $DB->sql_concat('publisher', "'_'", 'level', "'_'", 'name');
$select = "$publisher_level_name AS publisher_level_name, publisher, level, name, COUNT(*) AS countbooks";
$from   = '{reader_books}';
$groupby = 'publisher, level, name HAVING COUNT(*) > 1';
$params = array();
if ($duplicates = $DB->get_records_sql("SELECT $select FROM $from GROUP BY $groupby", $params)) {

    // cache quiz module id
    $quizmoduleid = $DB->get_field('modules', 'id', array('name' => 'quiz'));

    // reduce duplicate books to a single book
    $publisher = '';
    foreach ($duplicates as $duplicate) {

        $select = 'rb.id, rb.publisher, rb.level, rb.name, rb.quizid, q.id AS associated_quizid';
        $from   = '{reader_books} rb LEFT JOIN {quiz} q ON rb.quizid = q.id';
        $where  = 'rb.publisher = :publisher AND rb.level = :level AND rb.name = :bookname';
        $params = array('publisher' => $duplicate->publisher, 'level' => $duplicate->level, 'bookname' => $duplicate->name);
        if ($books = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params)) {

            $mainbookid = 0;
            $mainquizid = 0;
            foreach ($books as $book) {
                if ($mainbookid==0 && $book->associated_quizid) {
                    if ($book->id) {
                        $mainbookid = $book->id;
                    }
                }
                if ($mainquizid==0 || $DB->get_field('course_modules', 'visible', array('module' => $quizmoduleid, 'instance' => $book->quizid))) {
                    if ($book->quizid) {
                        $mainquizid = $book->quizid;
                    }
                }
            }
            if ($mainbookid==0) {
                continue; // try next duplicate
                // one day we could be ambitious and download the missing quiz ...
                $book = reset($books);
                $mainbookid = $book->id;
                $mainquizid = $book->quizid;
                // fetch quiz for this book ?
            }
            foreach ($books as $book) {
                if ($book->id==$mainbookid && $book->quizid==$mainquizid) {
                    continue;
                }

                if ($publisher != $book->publisher) {
                    if ($publisher=='') {
                        echo "<div><b>The following duplicate books were fixed:</b> $img";
                        echo '<ul>'; // start publisher list
                    } else {
                        echo '</ul></li>'; // finish book list
                    }
                    // start book list for this publisher
                    echo "<li><b>$book->publisher</b> $img";
                    echo '<ul>';
                    $publisher = $book->publisher;
                }
                echo "<li><b>$book->name</b> (bookid=$book->id) $img<ul>";

                if ($mainquizid && $mainquizid != $book->quizid) {
                    echo "<li>fix references to duplicate quiz (quizid $book->quizid =&gt; $mainquizid)</li>";
                    reader_adjust_quiz_ids($mainquizid, $book->quizid);
                    if ($cmid = $DB->get_field('course_modules', 'id', array('module' => $quizmoduleid, 'instance' => $book->quizid))) {
                        echo '<li><span style="color: red;">DELETED</span> '."Duplicate quiz (course module id=$cmid, quiz id=$book->quizid)".'</li>';
                        reader_remove_coursemodule($cmid);
                        $rebuild_course_cache = true;
                    }
                    $book->quizid = $mainquizid;
                }

                if ($mainbookid && $mainbookid != $book->id) {
                    // adjust all references to the duplicate book
                    echo "<li>remove references to duplicate book</li>";
                    $DB->set_field('reader_book_instances', 'bookid', $mainbookid, array('bookid' => $book->id));

                    // now we can delete this book (because it is a duplicate)
                    echo "<li>remove duplicate book</li>";
                    $DB->delete_records('reader_books', array('id' => $book->id));
                }

                echo '</ul></li>';
            }
        }
    }
    if ($publisher) {
        echo '</ul></li></ul></div>';
    }
}

// ================================
// fix books with non-unique quizid
// ================================

// extract books with non-unique quizid
$select = 'rb.*, q.name AS quizname, q.course AS quizcourseid';
$from   = '{reader_books} rb LEFT JOIN {quiz} q ON rb.quizid = q.id';
$where  = 'quizid IN (SELECT quizid FROM {reader_books} GROUP BY quizid HAVING COUNT(*) > 1)';
$orderby = 'rb.quizid';

if ($books = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", null, 'quizid')) {
    echo '<ul>';

    $quizid = 0;
    foreach ($books as $book) {

        // generate expected section name
        $sectionname = $book->publisher;
        if ($book->level=='' || $book->level=='--') {
            // do nothing
        } else {
            $sectionname .= ' - '.$book->level;
        }

        // check that this is the right quiz
        // i.e. a quiz with the expected name
        // in the expected section of the expected course

        $select = 'cm.*';
        $from   = '{course_modules} cm '.
                  'LEFT JOIN {course_sections} cs ON cm.section = cs.id '.
                  'LEFT JOIN {modules} m ON cm.module = m.id '.
                  'LEFT JOIN {quiz} q ON cm.instance = q.id ';
        $where  = 'cs.course = ? AND cs.name = ? AND m.name = ? AND q.name = ?';
        $params = array($book->quizcourseid, $sectionname, 'quiz', $book->quizname);
        $orderby = 'cm.visible DESC, cm.added DESC'; // i.e. most recent visible quiz
        if ($cms = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $orderby", $params)) {

            $cm = reset($cms); // there should be only one, but the first one will do
            if ($cm->instance==$book->quizid) {
                // this is the expected quiz for this book
            } else if ($bookid = $DB->get_field('reader_books', 'id', array('quizid' => $cm->instance))) {
                echo "<li>CANNOT fix quizid for &quot;$book->name&quot; (book id=$book->id): quiz id=$book->quizid =&gt; $cm->instance</li>";
            } else {
                echo "<li>Fixing quizid in &quot;$book->name&quot; (book id=$book->id): quiz id=$book->quizid =&gt; $cm->instance</li>";
                $DB->set_field('reader_books', 'quizid', $cm->instance, array('id' => $book->id));
            }
        } else {
            echo '<li><span style="color:red;">ERROR</span> '."Missing quiz for &quot;$book->name&quot; (book id=$book->id): quiz id=$book->quizid</li>";
        }
    }
    echo '</ul>';
}

// ================================
// fix duplicate quizzes
// ================================

$section_quizname = $DB->sql_concat('cm.section', "'_'", 'q.name');
$select = "$section_quizname AS section_quizname, ".
          'cm.section AS sectionid, cm.module AS moduleid, '.
          'q.name AS quizname, COUNT(*) AS countquizzes';
$from   = '{course_modules} cm '.
          'LEFT JOIN {modules} m ON cm.module = m.id '.
          'LEFT JOIN {quiz} q ON cm.instance = q.id ';
$where  = 'cm.course = ?';
$groupby = 'cm.section, q.name HAVING COUNT(*) > 1';
$params = array($reader_usecourse); // course id

// extract all duplicate (i.e. same section and name) quizzes in main reader course
if ($duplicates = $DB->get_records_sql("SELECT $select FROM $from WHERE $where GROUP BY $groupby", $params)) {
    echo '<ul>';

    foreach ($duplicates as $duplicate) {
        echo '<li>Merging duplicates for quiz: '.$duplicate->quizname.'<ul>';

        $maincm = null;

        $select = 'cm.*';
        $from   = '{course_modules} cm '.
                  'LEFT JOIN {quiz} q ON cm.instance = q.id ';
        $where  = 'cm.course = ? AND cm.section = ? AND cm.module = ? AND q.name = ?';
        $params = array($reader_usecourse, $duplicate->sectionid, $duplicate->moduleid, $duplicate->quizname);
        $orderby = 'cm.visible DESC, cm.added DESC'; // most recent visible activity will be first

        if ($cms = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $orderby", $params)) {
            foreach ($cms as $cm) {
                if (is_null($maincm)) {
                    $maincm = $cm; // the main quiz activity
                } else {
                    // transfer all quiz data to mainquizid
                    echo "<li>transferring quiz data (quiz id $cm->instance =&gt; $maincm->instance)</li>";
                    reader_adjust_quiz_ids($maincm->instance, $cm->instance);
                    echo '<li><span style="color: red;">DELETED</span> '."Duplicate quiz (course module id=$cm->id, quiz id=$cm->instance)".'</li>';
                    reader_remove_coursemodule($cm->id);
                    $rebuild_course_cache = true;
                }
            }
        }
        if ($maincm && $maincm->visible==0) {
            echo '<li>Make quiz visible '."(course module id=$maincm->id, quiz id=$maincm->instance)".'</li>';
            set_coursemodule_visible($maincm->id, 1);
        }
        echo '</ul></li>';
    }
    echo '</ul>';
}

// ================================
// fix missing quizzes
// ================================

$select = 'rb.id, rb.publisher, rb.series, rb.name, rb.quizid, q.course AS quizcourse';
$from   = '{reader_books} rb LEFT JOIN {quiz} q ON rb.quizid = q.id';
$where  = 'q.course IS NULL';
$orderby = 'rb.publisher, rb.name';
$params = array();
if ($books = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $orderby", $params)) {

    $publisher = '';
    $publishers = array();

    $started_list = false;

    foreach ($books as $book) {
        $mainquiz = false;

        // locate other quizzes, if any, for this book
        $params = array('course' => $course->id, 'name' => $book->name);
        $orderby = 'timemodified';
        if ($quizzes = $DB->get_records('quiz', $params, $orderby)) {

            foreach ($quizzes as $quiz) {
                if (! $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, true)) {
                    continue; // invalid quizid - shouldn't happen
                }
                if (! $section = $DB->get_record('course_sections', array('course' => $cm->course, 'section' => $cm->sectionnum))) {
                    continue; // invalid sectionnum - shouldn't happen
                }
                $sectionname = '';
                if ($sectionname=='' && isset($section->name)) {
                    $sectionname = trim(strip_tags($section->name));
                }
                if ($sectionname=='' && isset($section->summary)) {
                    $sectionname = trim(strip_tags($section->summary));
                }
                if ($sectionname==$book->publisher) {
                    $mainquiz = $quiz;
                    break; // exact match for publisher
                }
                $strlen = min(strlen($sectionname), strlen($book->publisher));
                if (substr($sectionname, 0, $strlen)==substr($book->publisher, 0, $strlen)) {
                    $mainquiz = $quiz;
                    // a partial match for the publisher
                    // keep going to see if there is a better match later on
                }
            }
        }

        if ($mainquiz) {
            if ($publisher != $book->publisher) {
                if ($publisher=='') {
                    echo "<div><b>The following books had their quiz reference fixed</b> $img";
                    echo '<ul>'; // start publisher list
                } else {
                    echo '</ul></li>'; // finish book list
                }
                // start book list for this publisher
                echo "<li><b>$book->publisher</b> $img<ul>";
                $publisher = $book->publisher;
            }
            echo "<li><b>$book->name</b> (bookid=$book->id, quizid=$book->quizid =&gt; $mainquiz->id)</li>";
            reader_adjust_quiz_ids($mainquiz->id, $book->quizid);
        } else {
            if (empty($publishers[$book->publisher])) {
                $publishers[$book->publisher] = array();
            }
            $publishers[$book->publisher][] = $book;
        }
    }
    if ($publisher) {
        echo '</ul></li></ul></div>';
    }

    if (count($publishers)) {
        echo "<div><b>The following books were missing quizzes:</b> $img<ul>";
        foreach ($publishers as $publisher => $books) {
            echo "<li><b>$publisher</b> (".count($books)." books) $img<ul>";
            foreach ($books as $book) {
                if (READER_DOWNLOAD_MISSING_QUIZZES) {
                    echo '<li>How do we download quiz for '."$book->name (id=$book->id, quizid=$book->quizid)".'</li>';
                } else {
                    echo '<li><span style="color: red;">DELETED BOOK</span> '."$book->name (id=$book->id, quizid=$book->quizid)".'</li>';
                    $DB->delete_records('readerview_evaluations', array('bookid' => $book->id));
                    $DB->delete_records('reader_book_instances', array('bookid' => $book->id));
                    $DB->delete_records('reader_books', array('id' => $book->id));
                }
            }
            echo '</ul></li>';
        }
        echo '</ul></div>';
    }

}

// ================================
// fix attempts with no quiz/book
// ================================

$quizids = array(); // $old => $new

$select = 'ra.*';
$from   = '{reader_attempts} ra LEFT JOIN {quiz} q ON ra.quizid = q.id';
$where  = 'q.id IS NULL';
$orderby = 'ra.id';
$params = array();

if ($attempts = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $orderby", $params)) {
    foreach ($attempts as $attempt) {

        if (isset($quizids[$attempt->quizid])) {
            continue; // we have already found this quiz id
        }

        // look for category names for questions in this quiz
        $LIKE_defaultcategory = $DB->sql_like('qc.name', ':defaultfor', false, false);
        $select = 'rqi.id AS rqi_id, q.id AS questionid, qc.id as categoryid, qc.name AS categoryname';
        $from   = '{reader_question_instances} rqi '.
                  'LEFT JOIN {question} q ON rqi.question = q.id '.
                  'LEFT JOIN {question_categories} qc ON q.category = qc.id';
        $where  = "rqi.quiz = :quizid AND qc.id IS NOT NULL AND $LIKE_defaultcategory";
        $params = array('quizid' => $attempt->quizid, 'defaultfor' => 'Default for%');

        if ($categories = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY qc.name", $params)) {
            foreach ($categories as $category) {

                // search in quizzes and books
                $name = preg_replace('/^Default for */i', '', $category->categoryname);
                $name = str_replace('Logain', 'Logan', $name); // fix bodged category name
                if (($quizid = $DB->get_field('reader_books', 'quizid', array('name' => $name))) || ($quizid = $DB->get_field('quiz', 'id', array('name' => $name, 'course' => $reader_usecourse)))) {
                    $quizids[$attempt->quizid] = $quizid;
                }
            }
        }
    }
}
if (count($quizids)) {
    foreach ($quizids as $oldid => $newid) {
        echo "<p>Fix orphan quiz attempt (quiz id $oldid =&gt; $newid)</p>";
        reader_adjust_quiz_ids($newid, $oldid);
    }
}

if ($rebuild_course_cache) {
    echo 'Re-building course cache ... ';
    rebuild_course_cache($course->id);
}

echo html_writer::tag('p', 'All done');
if ($id) {
    $href = new moodle_url('/mod/reader/admin/tools.php', array('id' => $id, 'tab' => $tab));
} else {
    $href = new moodle_url($CFG->wwwroot.'/');
}
echo html_writer::tag('p', html_writer::tag('a', 'Click here to continue', array('href' => $href)));

echo $OUTPUT->box_end();
echo $OUTPUT->footer();

/**
 * reader_adjust_quiz_ids
 *
 * @uses $DB
 * @param xxx $newid
 * @param xxx $oldid
 * @todo Finish documenting this function
 */
function reader_adjust_quiz_ids($newid, $oldid) {
    global $DB;
    // adjust all references to the non-existant/duplicate quiz
    $DB->set_field('reader_books',              'quizid', $newid, array('quizid' => $oldid));
    $DB->set_field('reader_attempts',           'quizid', $newid, array('quizid' => $oldid));
    $DB->set_field('reader_cheated_log',        'quizid', $newid, array('quizid' => $oldid));
    $DB->set_field('reader_conflicts',          'quizid', $newid, array('quizid' => $oldid));
    //$DB->set_field('reader_deleted_attempts',   'quizid', $newid, array('quizid' => $oldid));
    //$DB->set_field('reader_question_instances', 'quiz',   $newid, array('quiz'   => $oldid));
}

/**
 * reader_remove_coursemodule
 *
 * @param integer $cmid
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_remove_coursemodule($cmid) {
    global $CFG, $DB;

    // get course module - with sectionnum :-)
    if (! $cm = get_coursemodule_from_id('', $cmid, 0, true)) {
        print_error('invalidcoursemodule');
    }

    $libfile = $CFG->dirroot.'/mod/'.$cm->modname.'/lib.php';
    if (! file_exists($libfile)) {
        notify("$cm->modname lib.php not accessible ($libfile)");
    }
    require_once($libfile);

    $deleteinstancefunction = $cm->modname.'_delete_instance';
    if (! function_exists($deleteinstancefunction)) {
        notify("$cm->modname delete function not found ($deleteinstancefunction)");
    }

    // copied from 'course/mod.php'
    if (! $deleteinstancefunction($cm->instance)) {
        notify("Could not delete the $cm->modname (instance id=$cm->instance)");
    }
    if (function_exists('course_delete_module')) {
        // Moodle >= 2.5
        if (! course_delete_module($cm->id)) {
            notify("Could not delete the $cm->modname (coursemodule, id=$cm->id)");
        }
    } else {
        // Moodle <= 2.4
        if (! delete_course_module($cm->id)) {
            notify("Could not delete the $cm->modname (coursemodule, id=$cm->id)");
        }
        if (! $sectionid = $DB->get_field('course_sections', 'id', array('course' => $cm->course, 'section' => $cm->sectionnum))) {
            notify("Could not get section id (course id=$cm->course, section num=$cm->sectionnum)");
        }
        if (! delete_mod_from_section($cm->id, $sectionid)) {
            notify("Could not delete the $cm->modname (id=$cm->id) from that section (id=$sectionid)");
        }
    }

    reader_add_to_log($cm->course, 'course', 'delete mod', "view.php?id=$cm->course", "$cm->modname $cm->instance", $cm->id);

    $rebuild_course_cache = true;
    return $rebuild_course_cache;
}
