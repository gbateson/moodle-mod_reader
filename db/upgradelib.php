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
 * mod/reader/db/upgradelib.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die;

/**
 * xmldb_reader_check_files
 *
 * @uses $FULLME the full url, including query string, of this page
 * @uses $OUTPUT
 * @return void, but may pause the update if stale files are found
 */
function xmldb_reader_check_stale_files() {
    global $FULLME, $OUTPUT;

    $dirpath = dirname(__DIR__);
    $filenames = array(
        // moved to "js" folder
        'ajax.js', 'hide.js', 'jstimer.php', 'protect_js.php', 'quiz.js',
        // moved to "pix" folder
        'ajax-loader.gif', 'closed.gif', 'open.gif', 'pw.png',
        // moved to "quiz" folder
        'accessrules.php', 'attempt.php', 'attemptlib.php',
        'processattempt.php', 'startattempt.php', 'summary.php',
        // moved to "utilities" folder
        'checkemail.php', 'fixslashesinnames.php'
    );

    $stalefilenames = array();
    foreach ($filenames as $filename) {

        $filepath = $dirpath.'/'.$filename;
        $exists = file_exists($filepath);

        if ($exists && is_writable($filepath) && @unlink($filepath)) {
            $exists = false; // successfully deleted
        }

        if ($exists) {
            $stalefilenames[] = $filename;
        }
    }

    if (count($stalefilenames)) {
        // based on "upgrade_stale_php_files_page()" (in 'admin/renderer.php')

        $a = (object)array('dirpath'=>$dirpath, 'filelist'=>html_writer::alist($stalefilenames));
        $message = format_text(get_string('upgradestalefilesinfo', 'reader', $a), FORMAT_MARKDOWN);

        $button = $OUTPUT->single_button($FULLME, get_string('reload'), 'get');
        $button = html_writer::tag('div', $button, array('class' => 'buttons'));

        $output = '';
        $output .= $OUTPUT->heading(get_string('upgradestalefiles', 'reader'));
        $output .= $OUTPUT->box($message.$button, 'generalbox', 'notice');
        $output .= $OUTPUT->footer();

        echo $output;
        die;
    }
}

/**
 * xmldb_reader_fix_previous_field
 *
 * @param xxx $dbman
 * @param xmldb_table $table
 * @param xmldb_field $field (passed by reference)
 * @return void, but may update $field->previous
 */
function xmldb_reader_fix_previous_field($dbman, $table, &$field) {
    $previous = $field->getPrevious();
    if (empty($previous) || $dbman->field_exists($table, $previous)) {
        // $previous field exists - do nothing
    } else {
        // $previous field does not exist, so remove it
        $field->setPrevious(null);
    }
}

/**
 * xmldb_reader_fix_duplicate_books
 *
 * @param xxx $course record
 * @param boolean $keepoldquizzes
 * @return boolean $rebuild_course_cache
 * @todo Finish documenting this function
 */
function xmldb_reader_fix_duplicate_books($course, $keepoldquizzes) {
    global $DB, $OUTPUT;
    $rebuild_course_cache = false;

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
                            echo xmldb_reader_showhide_js();
                            echo '<div><b>The following duplicate books were fixed:</b> '.xmldb_reader_showhide_img();
                            echo '<ul>'; // start publisher list
                        } else {
                            echo '</ul></li>'; // finish book list
                        }
                        // start book list for this publisher
                        echo '<li><b>$book->publisher</b> '.xmldb_reader_showhide_img();
                        echo '<ul>';
                        $publisher = $book->publisher;
                    }
                    echo "<li><b>$book->name</b> (bookid=$book->id) ".xmldb_reader_showhide_img()."<ul>";

                    if ($mainquizid && $mainquizid != $book->quizid) {
                        echo "<li>fix references to duplicate quiz (quizid $book->quizid =&gt; $mainquizid)</li>";
                        xmldb_reader_fix_quiz_ids($mainquizid, $book->quizid);
                        if ($cm = $DB->get_record('course_modules', array('module' => $quizmoduleid, 'instance' => $book->quizid))) {
                            if ($keepoldquizzes) {
                                if ($cm->visible==1) {
                                    echo '<li>Hide duplicate quiz '."(course module id=$cm->id, quiz id=$cm->instance)".'</li>';
                                    set_coursemodule_visible($cm->id, 0);
                                    $rebuild_course_cache = true;
                                }
                            } else {
                                echo '<li><span style="color: red;">DELETED</span> '."Duplicate quiz (course module id=$cm->id, quiz id=$book->quizid)".'</li>';
                                xmldb_reader_remove_coursemodule($cm->id);
                                $rebuild_course_cache = true;
                            }
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

    return $rebuild_course_cache;
}

/**
 * xmldb_reader_fix_duplicate_quizzes
 *
 * @param object $course record
 * @param boolean $keepoldquizzes
 * @todo Finish documenting this function
 */
function xmldb_reader_fix_duplicate_quizzes($course, $keepoldquizzes) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/course/lib.php');

    $rebuild_course_cache = false;

    $section_quizname = $DB->sql_concat('cm.section', "'_'", 'q.name');
    $select = "$section_quizname AS section_quizname, ".
              'cm.section AS sectionid, cm.module AS moduleid, '.
              'q.name AS quizname, COUNT(*) AS countquizzes';
    $from   = '{course_modules} cm '.
              'LEFT JOIN {modules} m ON cm.module = m.id '.
              'LEFT JOIN {quiz} q ON cm.instance = q.id ';
    $where  = 'cm.course = ?';
    $groupby = 'cm.section, q.name HAVING COUNT(*) > 1';
    $params = array($course->id); // course id

    // extract all duplicate (i.e. same section and name) quizzes in main reader course
    if ($duplicates = $DB->get_records_sql("SELECT $select FROM $from WHERE $where GROUP BY $groupby", $params)) {
        echo xmldb_reader_showhide_js();
        echo '<div><b>The following duplicate quizzes were fixed:</b> '.xmldb_reader_showhide_img();
        echo '<ul>';

        foreach ($duplicates as $duplicate) {
            echo '<li>Merging duplicate quizzes: '.$duplicate->quizname.'<ul>';

            $maincm = null;

            $select = 'cm.*';
            $from   = '{course_modules} cm '.
                      'LEFT JOIN {quiz} q ON cm.instance = q.id ';
            $where  = 'cm.course = ? AND cm.section = ? AND cm.module = ? AND q.name = ?';
            $params = array($course->id, $duplicate->sectionid, $duplicate->moduleid, $duplicate->quizname);
            $orderby = 'cm.visible DESC, cm.added DESC'; // most recent visible activity will be first

            if ($cms = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $orderby", $params)) {
                foreach ($cms as $cm) {
                    if (is_null($maincm)) {
                        $maincm = $cm; // the main quiz activity
                    } else {
                        // transfer all quiz data to mainquizid
                        echo "<li>transferring quiz data (quiz id $cm->instance =&gt; $maincm->instance)</li>";
                        xmldb_reader_fix_quiz_ids($maincm->instance, $cm->instance);
                        // hide or delete the duplicate quiz
                        if ($keepoldquizzes) {
                            if ($cm->visible==1) {
                                echo '<li>Hide duplicate quiz '."(course module id=$cm->id, quiz id=$cm->instance)".'</li>';
                                set_coursemodule_visible($cm->id, 0);
                                $rebuild_course_cache = true;
                            }
                        } else {
                            echo '<li><span style="color: red;">DELETED</span> '."Duplicate quiz (course module id=$cm->id, quiz id=$cm->instance)".'</li>';
                            xmldb_reader_remove_coursemodule($cm->id);
                            $rebuild_course_cache = true;
                        }
                    }
                }
            }
            if ($maincm && $maincm->visible==0) {
                echo '<li>Make quiz visible '."(course module id=$maincm->id, quiz id=$maincm->instance)".'</li>';
                set_coursemodule_visible($maincm->id, 1);
                $rebuild_course_cache = true;
            }
            echo '</ul></li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    return $rebuild_course_cache;
}

/**
 * xmldb_reader_fix_quiz_ids
 *
 * @uses $DB
 * @param xxx $newid
 * @param xxx $oldid
 * @todo Finish documenting this function
 */
function xmldb_reader_fix_quiz_ids($newid, $oldid) {
    global $DB;

    $fields = array(
        // $tablename => $fieldname
        'reader_books'              => 'quizid',
        'reader_attempts'           => 'quizid',
        'reader_cheated_log'        => 'quizid',
        'reader_conflicts'          => 'quizid',
        'reader_deleted_attempts'   => 'quizid',
        'reader_noquiz'             => 'quizid',
        'reader_question_instances' => 'quiz'
    );

    foreach ($fields as $tablename => $fieldname) {
        if ($newid==0) {
            $DB->delete_records($tablename, array($fieldname => $oldid));
        } else {
            $DB->set_field($tablename, $fieldname, $newid, array($fieldname => $oldid));
        }
    }
}

/**
 * xmldb_reader_remove_coursemodule
 *
 * @param integer $cmid
 * @return xxx
 * @todo Finish documenting this function
 */
function xmldb_reader_remove_coursemodule($cmid) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/course/lib.php');

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
    if (! delete_course_module($cm->id)) {
        notify("Could not delete the $cm->modname (coursemodule, id=$cm->id)");
    }
    if (! $sectionid = $DB->get_field('course_sections', 'id', array('course' => $cm->course, 'section' => $cm->sectionnum))) {
        notify("Could not get section id (course id=$cm->course, section num=$cm->sectionnum)");
    }
    if (! delete_mod_from_section($cm->id, $sectionid)) {
        notify("Could not delete the $cm->modname (id=$cm->id) from that section (id=$sectionid)");
    }

    add_to_log($cm->course, 'course', 'delete mod', "view.php?id=$cm->course", "$cm->modname $cm->instance", $cm->id);

    $rebuild_course_cache = true;
    return $rebuild_course_cache;
}

/**
 * xmldb_reader_quiz_courseids
 *
 * @return array $courseids containing Reader module quizzes
 * @todo Finish documenting this function
 */
function xmldb_reader_quiz_courseids() {
    global $DB;

    $courseids = array();
    if ($courseid = get_config('reader', 'reader_usecourse')) {
        $courseids[] = $courseid;
    }
    $select = 'SELECT DISTINCT usecourse FROM {reader} WHERE usecourse IS NOT NULL AND usecourse > ?';
    $select = "id IN ($select) AND visible = ?";
    $params = array(0, 0);
    if ($courses = $DB->get_records_select('course', $select, $params, 'id', 'id,visible')) {
        $courseids = array_merge($courseids, array_keys($courses));
        $courseids = array_unique($courseids);
        sort($courseids);
    }
    return $courseids;
}

/**
 * xmldb_reader_showhide_js
 *
 * @todo Finish documenting this function
 */
function xmldb_reader_showhide_js() {
    static $done = false;

    $js = '';
    if ($done==false) {
        $done = true;
        $js .= '<script type="text/javascript">'."\n";
        $js .= "//<![CDATA[\n";
        $js .= "function showhide_list(img) {\n";
        $js .= "    var obj = img.nextSibling;\n";
        $js .= "    if (obj) {\n";
        $js .= "        if (obj.style.display=='none') {\n";
        $js .= "            obj.style.display = '';\n";
        $js .= "            var pix = 'minus';\n";
        $js .= "        } else {\n";
        $js .= "            obj.style.display = 'none';\n";
        $js .= "            var pix = 'plus';\n";
        $js .= "        }\n";
        $js .= "        img.alt = 'switch_' + pix;\n";
        $js .= "        img.src = img.src.replace(new RegExp('switch_[a-z]+'), 'switch_' + pix);\n";
        $js .= "    }\n";
        $js .= "}\n";
        $js .= "function showhide_lists() {\n";
        $js .= "    var img = document.getElementsByTagName('img');\n";
        $js .= "    if (img) {\n";
        $js .= "        var targetsrc = new RegExp('switch_(minus|plus)');\n";
        $js .= "        var i_max = img.length;\n";
        $js .= "        for (var i=0; i<=i_max; i++) {\n";
        $js .= "            if (img[i].src.match(targetsrc)) {\n";
        $js .= "                showhide_list(img[i]);\n";
        $js .= "            }\n";
        $js .= "        }\n";
        $js .= "    }\n";
        $js .= "}\n";
        $js .= "if (window.addEventListener) {\n";
        $js .= "    window.addEventListener('load', showhide_lists, false);\n";
        $js .= "} else if (window.attachEvent) {\n";
        $js .= "    window.attachEvent('onload', showhide_lists);\n";
        $js .= "} else {\n";
        $js .= "    // window['onload'] = showhide_lists;\n";
        $js .= "}\n";
        $js .= "//]]>\n";
        $js .= '</script>'."\n";
    }
    return $js;
}

/**
 * xmldb_reader_showhide_img
 *
 * @todo Finish documenting this function
 */
function xmldb_reader_showhide_img() {
    global $OUTPUT;
    static $img = '';
    if ($img=='') {
        $src = $OUTPUT->pix_url('t/switch_minus');
        $img = html_writer::empty_tag('img', array('src' => $src, 'onclick' => 'showhide_list(this)', 'alt' => 'switch_minus'));
    }
    return $img;
}

/**
 * xmldb_reader_fix_question_instances
 *
 * @return array $courseids containing Reader module quizzes
 * @todo Finish documenting this function
 */
function xmldb_reader_fix_question_instances() {
    global $DB;

    $courseid = get_config('reader', 'reader_usecourse');

    $select = 'rqi.*, qz.id AS quizid, qn.id AS questionid, rb.id AS bookid';
    $from   = '{reader_question_instances} rqi '.
              'LEFT JOIN {quiz} qz ON rqi.quiz = qz.id '.
              'LEFT JOIN {question} qn ON rqi.question = qn.id '.
              'LEFT JOIN {reader_books} rb ON rqi.quiz = rb.quizid';
    $where  = 'qz.id IS NULL OR qn.id IS NULL OR rb.id IS NULL';

    if ($instances = $DB->get_records_sql("SELECT $select FROM $from WHERE $where")) {
        foreach ($instances as $instance) {
            if (empty($instance->quizid)) {
                // no such quiz, so remove all references to this quiz
                xmldb_reader_fix_quiz_ids(0, $instance->quiz);
            }
            $DB->delete_records('reader_question_instances', array('id' => $instance->id));
        }
    }

    $i_max = 0;
    $rs = false;

    if ($courseid) {
        if ($i_max = $DB->count_records_sql('SELECT COUNT(*) FROM {reader_question_instances}')) {
            $rs = $DB->get_recordset_sql('SELECT * FROM {reader_question_instances}');
        }
    }

    if ($rs) {
        $i = 0; // record counter
        $bar = new progress_bar('readerfixinstances', 500, true);
        $strupdating = 'Checking Reader question instances'; // get_string('fixinstances', 'reader');

        // loop through answer records
        foreach ($rs as $instance) {
            $i++; // increment record count

            // apply for more script execution time (3 mins)
            upgrade_set_timeout();

            // TODO: check $instance->quiz and $instance->question is a valid combination
            if ($DB->record_exists('quiz_question_instances', array('quiz' => $instance->quiz, 'question' => $instance->question))) {
                if ($quiz_question_instances = $DB->get_records('quiz_question_instances', array('question' => $instance->question))) {
                    foreach ($quiz_question_instances as $quiz_question_instance) {
                        if ($DB->record_exists('quiz', array('id' => $quiz_question_instance->quiz, 'course' => $courseid))) {
                            $DB->set_field('reader_question_instances', 'quiz', $quiz_question_instance->quiz, array('id' => $instance->id));
                        }
                    }
                }
            }

            // update progress bar
            $bar->update($i, $i_max, $strupdating.": ($i/$i_max)");
        }
        $rs->close();
    }

    // get all distinct quizids from books
    // foreach (quiz) check every question has an entry in the instances table
}

/**
 * xmldb_reader_fix_nameless_books
 *
 * @todo Finish documenting this function
 */
function xmldb_reader_fix_nameless_books() {
    global $DB;
    $select = 'rb.id, rb.quizid, rb.name AS bookname, q.name AS quizname';
    $from   = '{reader_books} rb LEFT JOIN {quiz} q ON rb.quizid = q.id';
    $where  = 'rb.name = ? AND rb.quizid <> ? AND q.id IS NOT NULL';
    $params = array('', 0);
    if ($books = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params)) {
        foreach ($books as $book) {
            $DB->set_field('reader_books', 'name', $book->quizname, array('id' => $book->id));
        }
    }
}

/**
 * xmldb_reader_fix_slashes
 *
 * @todo Finish documenting this function
 */
function xmldb_reader_fix_slashes() {
    global $DB;

    $tables = array(
        'log' => array('action'),
        'quiz' => array('name'),
        'reader_books' => array('publisher', 'name'),
        'question_categories' => array('name', 'info'),
    );

    foreach ($tables as $table => $fields) {
        foreach ($fields as $field) {
            $update = '{'.$table.'}';
            $set    = "$field = REPLACE($field, '\\\\', '')";
            $where  = $DB->sql_like($field, '?');
            $params = array('%\\\\%');
            if ($table=='log' && $field=='action') {
                $where = 'module = ? AND '.$where;
                $params = array('reader', 'view attempt:%');
            }
            $DB->execute("UPDATE $update SET $set WHERE $where", $params);
        }
    }
}

/**
 * xmldb_reader_fix_wrong_quizids
 *
 * @todo Finish documenting this function
 */
function xmldb_reader_fix_wrong_quizids() {
    global $DB, $OUTPUT;

    // extract books with wrong quizid
    $select = 'rb.id, rb.publisher, rb.level, rb.name, rb.quizid, q.name AS quizname, cs.name AS sectionname, q.course AS courseid';
    $from   = '{reader_books} rb '.
              'LEFT JOIN {quiz} q ON rb.quizid = q.id '.
              'LEFT JOIN {course_modules} cm ON q.id = cm.instance '.
              'LEFT JOIN {course_sections} cs ON cs.course = cm.course AND cs.id = cm.section '.
              'LEFT JOIN {modules} m ON m.id = cm.module';
    $where  = 'm.name = ? '.
              'AND q.id IS NOT NULL '.
              'AND cm.id IS NOT NULL '.
              'AND m.id IS NOT NULL '.
              'AND cs.id IS NOT NULL '.
              'AND (rb.name <> q.name OR '.$DB->sql_concat('rb.publisher', "' - '", 'rb.level').' <> cs.name)';
    $params = array('quiz');
    $orderby = 'rb.publisher,rb.level,rb.name';

    if ($books = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $orderby", $params)) {

        foreach ($books as $book) {
            $sectionname = $book->publisher;
            if ($book->level) {
                $sectionname .= ' - '.$book->level;
            }
            $select = 'q.id, q.name, cs.name AS sectionname';
            $from   = '{quiz} q '.
                      'LEFT JOIN {course_modules} cm ON q.id = cm.instance '.
                      'LEFT JOIN {course_sections} cs ON cs.course = cm.course AND cs.id = cm.section '.
                      'LEFT JOIN {modules} m ON m.id = cm.module AND m.name = ?';
            $where  = 'q.name = ? AND cs.name = ?';
            $params = array('quiz', $book->name, $sectionname);
            $orderby = 'cm.visible DESC, cm.added DESC';

            if ($quiz = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $orderby", $params, 0, 1)) {
                $quiz = reset($quiz);
                if ($book->quizid != $quiz->id) {
                    echo html_writer::tag('li', "Reset quiz id for $sectionname: $book->name ($book->quizid => $quiz->id)");
                    $DB->set_field('reader_books', 'quizid', $quiz->id, array('id' => $book->id));
                }
            }
        }
    }
}

/**
 * xmldb_reader_fix_slashes
 *
 * @todo Finish documenting this function
 */
function xmldb_reader_fix_nonunique_quizids() {
    global $DB, $OUTPUT;

    $missingquizids = array();

    // extract books with non-unique quizid
    $select = 'rb.*, q.name AS quizname, q.course AS quizcourseid';
    $from   = '{reader_books} rb LEFT JOIN {quiz} q ON rb.quizid = q.id';
    $where  = 'quizid IN (SELECT quizid FROM {reader_books} GROUP BY quizid HAVING COUNT(*) > 1)';
    $orderby = 'rb.quizid';

    if ($books = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", null, 'quizid')) {
        echo xmldb_reader_showhide_js();
        echo $OUTPUT->box_start('generalbox', 'notice');
        echo '<div>The following books have a non-unique quizid: '.xmldb_reader_showhide_img();
        echo '<ul>';

        $quizid = 0;
        foreach ($books as $book) {

            // generate expected section name
            $sectionname = $book->publisher;
            if ($book->level) {
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
                $missingquizids[$book->id] = $book->quizid;
            }
        }
        echo '</ul>';
        echo '</div>';
        echo $OUTPUT->box_end();
    }

    $fixmissingquizzes = optional_param('fixmissingquizzes', null, PARAM_INT);
    if (count($missingquizids)) {

        if ($fixmissingquizzes===null || $fixmissingquizzes===false || $fixmissingquizzes==='') {

            $message = get_string('fixmissingquizzesinfo', 'reader');
            $message = format_text($message, FORMAT_MARKDOWN);

            $params = array(
                'confirmupgrade' => optional_param('confirmupgrade', 0, PARAM_INT),
                'confirmrelease' => optional_param('confirmrelease', 0, PARAM_INT),
                'confirmplugincheck' => optional_param('confirmplugincheck', 0, PARAM_INT),
            );

            $params['fixmissingquizzes'] = 0;
            $no = new moodle_url('/admin/index.php', $params);

            $params['fixmissingquizzes'] = 1;
            $yes = new moodle_url('/admin/index.php', $params);

            $buttons = $OUTPUT->single_button($no, get_string('no'), 'get').
                       $OUTPUT->single_button($yes, get_string('yes'), 'get');
            $buttons = html_writer::tag('div', $buttons, array('class' => 'buttons'));

            $output = '';
            $output .= $OUTPUT->heading(get_string('fixmissingquizzes', 'reader'));
            $output .= $OUTPUT->box($message.$buttons, 'generalbox', 'notice');
            $output .= $OUTPUT->footer();

            echo $output;
            die;
        }
    }

    if ($fixmissingquizzes) {
        reader_install_missingquizzes($books);
    }
}

/**
 * reader_install_missingquizzes
 *
 * @param xxx $books
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_install_missingquizzes($books) {
    global $CFG, $DB, $OUTPUT;

    require_once($CFG->dirroot.'/mod/reader/lib.php');
    require_once($CFG->dirroot.'/mod/reader/lib/pclzip/pclzip.lib.php');
    require_once($CFG->dirroot.'/mod/reader/lib/backup/restorelib.php');
    require_once($CFG->dirroot.'/mod/reader/lib/backup/backuplib.php');
    require_once($CFG->dirroot.'/mod/reader/lib/backup/lib.php');
    //require_once($CFG->dirroot.'/mod/reader/lib/questionlib.php');
    require_once($CFG->dirroot.'/mod/reader/lib/question/restorelib.php');

    // get reader config data
    $readercfg = get_config('reader');

    $params = array('a' => 'publishers',
                    'login' => $readercfg->reader_serverlogin,
                    'password' => $readercfg->reader_serverpassword);
    $url = new moodle_url($readercfg->reader_serverlink.'/', $params);

    if(! $xml = reader_file($url)) {
        return false; // shouldn't happen
    }
    if (! $xml = xmlize($xml)) {
        return false; // shouldn't happen
    }

    $itemids = array();
    foreach ($xml['myxml']['#']['item'] as $item) {

        // sanity check on downloaded values
        if (! isset($item['@']['publisher'])) {
            continue; // shouldn't happen !!
        }
        if (! isset($item['@']['level'])) {
            continue; // shouldn't happen !!
        }
        if (! isset($item['@']['id'])) {
            continue; // shouldn't happen !!
        }
        if (! isset($item['#'])) {
            continue; // shouldn't happen !!
        }

        $publisher = $item['@']['publisher'];
        $level     = $item['@']['level'];
        $itemid    = $item['@']['id'];
        $name      = $item['#'];

        foreach ($books as $bookid => $book) {
            if ($book->publisher==$publisher) {
                if ($book->level==$level) {
                    if ($book->name==$name) {
                        $books[$bookid]->itemid = $itemid;
                        $itemids[$itemid] = $bookid;
                        break;
                    }
                }
            }
        }
    }

    // set download url
    $params = array('a' => 'quizzes',
                    'login' => $readercfg->reader_serverlogin,
                    'password' => $readercfg->reader_serverpassword);
    $url = new moodle_url($readercfg->reader_serverlink.'/', $params);
    // http://moodlereader.net/quizbank

    // download quiz data and convert to array
    $params = array('password' => '', // $password
                    'quiz'     => array_keys($itemids),
                    'upload'   =>'true');
    $xml = reader_file($url, $params);
    $xml = xmlize($xml);

    // cache the quiz module info
    $quizmodule = $DB->get_record('modules', array('name' => 'quiz'));

    // search string to detect "Test101" question category
    $test101 = '/<QUESTION_CATEGORY>(.*?)<NAME>Default for Test101<\/NAME>(.*?)<\/QUESTION_CATEGORY>\s*/s';

    // course where the new quizzes will be put
    if ($targetcourseid = $readercfg->reader_usecourse) {
        $targetcourse = $DB->get_record('course', array('id' => $targetcourseid));
    } else {
        $targetcourse = null; // will be created later, if necessary
    }

    // $restore object and temporary dir/file
    $restore = null;
    $tempdir = '';
    $tempfile = '';

    $i =0 ;
    foreach ($xml['myxml']['#']['item'] as $item) {
        if ($i++ >= 3) {
            break;
        }

        // sanity check on expected fields
        if (! isset($item['@']['publisher'])) {
            continue; // shouldn't happen !!
        }
        if (! isset($item['@']['level'])) {
            continue; // shouldn't happen !!
        }
        if (! isset($item['@']['title'])) {
            continue; // shouldn't happen !!
        }

        $publisher = $item['@']['publisher'];
        $level     = $item['@']['level'];
        $name      = $item['@']['title'];
        $itemid    = $item['@']['id'];

        if (! isset($itemids[$itemid])) {
            continue; // shouldn't happen !!
        }
        $bookid = $itemids[$itemid];

        // create course if necessary (first time only)
        if ($targetcourseid==0) {
            $targetcourse = reader_xmldb_get_targetcourse();
            $targetcourseid = $targetcourse->id;
        }

        // create restore object (first time only)
        if ($restore===null) {
            $restore = reader_xmldb_get_restore_object($targetcourse);
        }

        // set temporary file names
        $tempdir = reader_xmldb_get_tempdir($restore);
        $restore->file = $tempdir.'/moodle.zip';
        $tempfile = $tempdir.'/moodle.xml';

        // create section if necessary
        $sectionname = $publisher;
        if ($level) {
            $sectionname .= ' - '.$level;
        }
        $sectionnum = reader_xmldb_get_sectionnum($targetcourse, $sectionname);

        // create new quiz and update this book
        $cm = reader_xmldb_get_newquiz($targetcourseid, $sectionnum, $quizmodule, $name);
        $DB->set_field('reader_books', 'quizid', $cm->instance, array('id' => $bookid));

        // download questions for this quiz
        $params = array('getid' => $itemid, 'pass' => ''); // $pass
        $url = new moodle_url($readercfg->reader_serverlink.'/getfile.php', $params);
        $xml = reader_file($url);
        $xml = preg_replace($test101, '', $xml); // remove "Test101" question category (if any)

        // write xml to temporary file
        $fp = fopen($tempfile, 'w');
        fwrite($fp, $xml);
        fclose($fp);

        // "backup" $tempfile to moodle.zip file
        backup_zip($restore);

        // get any images that are used in the  quiz questions
        $xml = xmlize($xml);
        reader_xmldb_get_quiz_images($readercfg, $xml, $targetcourseid);

        // setup restore object to restore this quiz
        $restore->mods['quiz']->instances = array(
            $itemid => (object)array('restore' => 1, 'userinfo' => 0, 'restored_as_course_module' => $cm->id)
        );

        // initialize the $QTYPES array (first time only)
        reader_xmldb_init_qtypes();

        // create and restore_questions for this quiz
        echo $OUTPUT->box_start('generalbox', 'notice');
        echo html_writer::tag('h3', "$sectionname: $name");
        restore_create_questions($restore, $tempfile);
        reader_xmldb_restore_questions($restore, $xml, $cm->instance);
        echo $OUTPUT->box_end();

        reader_remove_directory($tempdir);
    }

    return true;
}

/**
 * reader_xmldb_get_restore_object
 *
 * @uses $CFG
 * @uses $DB
 * @param xxx $targetcourse
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_xmldb_get_restore_object($targetcourse) {
    global $CFG, $DB;

    $restore = (object)array(
        'backup_unique_code'     => time(),
        'backup_name'            => 'moodle.zip',
        'restoreto'              => 1,
        'metacourse'             => 0,
        'users'                  => 0,
        'groups'                 => 0,
        'logs'                   => 0,
        'user_files'             => 0,
        'course_files'           => 0,
        'site_files'             => 0,
        'messages'               => 0,
        'blogs'                  => 0,
        'restore_gradebook_history' => 0,
        'course_id'              => $targetcourse->id,
        'course_shortname'       => $targetcourse->shortname,
        'restore_restorecatto'   => 0,
        'deleting'               => '',
        'original_wwwroot'       => $CFG->wwwroot,
        'backup_version'         => 2008030300,
        'course_startdateoffset' => 0,
        'restore_restorecatto'   => $targetcourse->category,
        'rolesmapping'           => array(),
        'mods'                   => array(),
    );

    if ($modules = $DB->get_records('modules')) {
        foreach ($modules as $module) {
            $restore->mods[$module->name] = (object)array(
                'restore'   => ($module->name=='quiz' ? 1 : 0),
                'userinfo'  => 0,
                'granular'  => ($module->name=='quiz' ? 1 : 0),
                'instances' => array()
            );
        }
    }

    return $restore;
}

/**
 * reader_xmldb_get_tempdir
 *
 * @uses $CFG
 * @param xxx $restore
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_xmldb_get_tempdir($restore) {
    global $CFG;
    $tempdir = '/temp/backup/'.$restore->backup_unique_code;
    make_upload_directory($tempdir);
    return $CFG->dataroot.$tempdir;
}

/**
 * reader_xmldb_get_targetcourse
 *
 * @param xxx $numsections (optional, default=1)
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_xmldb_get_targetcourse($numsections=1) {
    global $CFG;
    require_once($CFG->dirroot.'/course/lib.php');

    // get the first valid $category_id
    $category_list = array();
    $category_parents = array();
    make_categories_list($category_list, $category_parents);
    list($category_id, $category_name) = each($category_list);

    $targetcourse = (object)array(
        'category'      => $category_id, // crucial !!
        'fullname'      => 'Reader Quizzes',
        'shortname'     => 'Reader Quizzes',
        'name'          => 'Reader Quizzes',
        'summary'       => '',
        'summaryformat' => FORMAT_PLAIN, // plain text
        'format'        => 'topics',
        'newsitems'     => 0,
        'startdate'     => time(),
        'visible'       => 0, // hidden
        'numsections'   => $numsections
    );

    if ($targetcourse = create_course($targetcourse)) {
        return $targetcourse;
    } else {
        return false;
    }
}

/**
 * reader_xmldb_get_quiz_images
 *
 * @uses $CFG
 * @param xxx $readercfg
 * @param xxx $xml
 * @param xxx $targetcourseid
 * @todo Finish documenting this function
 */
function reader_xmldb_get_quiz_images($readercfg, $xml, $targetcourseid) {
    global $CFG;

    $images = array();

    // extract $images from $xml
    if (isset($xml['MOODLE_BACKUP']['#']['COURSE']['0']['#']['QUESTION_CATEGORIES']['0']['#']['QUESTION_CATEGORY'])) {
        $categories = &$xml['MOODLE_BACKUP']['#']['COURSE']['0']['#']['QUESTION_CATEGORIES']['0']['#']['QUESTION_CATEGORY'];
        $c = 0;
        while (isset($categories["$c"]['#']['QUESTIONS']['0']['#'])) {
            $questions = &$categories["$c"]['#']['QUESTIONS']['0']['#'];
            $q = 0;
            while (isset($questions['QUESTION']["$q"]['#'])) {
                $question = &$questions['QUESTION']["$q"]['#'];
                if (isset($question['IMAGE']['0']['#'])) {
                    if ($image = $question['IMAGE']['0']['#']) {
                        $images[] = $image;
                    }
                }
                unset($question);
                $q++;
            }
            unset($questions);
            $c++;
        }
        unset($categories);
    }

    // download $images, if any
    foreach ($images as $image) {
        $basename = basename($image);
        $dirname = dirname($image);
        $dirname = trim($dirname, '/');
        $dirname = ltrim($dirname, './');
        if ($dirname) {
            $dirname = 'reader/images/'.$dirname;
            make_upload_directory($dirname);
            $dirname .= '/';
        }

        $params = array('imagelink' => urlencode($image));
        $image_file_url = new moodle_url($readercfg->reader_serverlink.'/getfile_quiz_image.php', $params);
        $image_contents = file_get_contents($image_file_url);

        if ($fp = @fopen($CFG->dataroot.'/'.$dirname.$basename, 'w+')) {
            @fwrite($fp, $image_contents);
            @fclose($fp);
        }
    }
}

/**
 * reader_xmldb_get_sectionnum
 *
 * @uses $DB
 * @param xxx $targetcourse (passed by reference)
 * @param xxx $sectionname
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_xmldb_get_sectionnum(&$targetcourse, $sectionname) {
    global $DB;

    $select = 'course = ? AND (name = ? OR summary = ?)';
    $params = array($targetcourse->id, $sectionname, $sectionname);
    if ($coursesections = $DB->get_records_select('course_sections', $select, $params, 'section', '*', 0, 1)) {
        $coursesection = reset($coursesections); // first section with the target name
        $sectionnum = $coursesection->section;
    } else {
        $sectionnum = 0;
    }

    // reuse an empty section, if possible
    if ($sectionnum==0) {
        $select = 'course = ? AND section > ?'.
                  ' AND (name IS NULL OR name = ?)'.
                  ' AND (summary IS NULL OR summary = ?)'.
                  ' AND (sequence IS NULL OR sequence = ?)';
        $params = array($targetcourse->id, 0, '', '', '');

        if ($coursesections = $DB->get_records_select('course_sections', $select, $params, 'section', '*', 0, 1)) {
            $coursesection = reset($coursesections);
            $sectionnum = $coursesection->section;
            $coursesection->name = $sectionname;
            $DB->update_record('course_sections', $coursesection);
        }
    }

    // create a new section, if necessary
    if ($sectionnum==0) {
        $sql = 'SELECT MAX(section) FROM {course_sections} WHERE course = ?';
        if ($sectionnum = $DB->get_field_sql($sql, array($targetcourse->id))) {
            $sectionnum ++;
        } else {
            $sectionnum = 1;
        }
        $coursesection = (object)array(
            'course'        => $targetcourse->id,
            'section'       => $sectionnum,
            'name'          => $sectionname,
            'summary'       => '',
            'summaryformat' => FORMAT_HTML,

        );
        $coursesection->id = $DB->insert_record('course_sections', $coursesection);
    }

    if ($sectionnum > reader_get_numsections($targetcourse)) {
        reader_set_numsections($targetcourse, $sectionnum);
    }

    return $sectionnum;
}

/**
 * reader_xmldb_get_newquiz
 *
 * @uses $DB
 * @uses $USER
 * @param xxx $targetcourseid
 * @param xxx $sectionnum
 * @param xxx $quizmodule
 * @param xxx $quizname
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_xmldb_get_newquiz($targetcourseid, $sectionnum, $quizmodule, $quizname) {
    global $CFG, $DB, $USER;
    require_once($CFG->dirroot.'/course/lib.php');

    // disable warnings about rebuild_course_cache();
    $upgraderunning = $CFG->upgraderunning;
    $CFG->upgraderunning = false;

    $sumgrades = 0;
    $newquiz = (object)array(
        // standard Quiz fields
        'name'          => $quizname,
        'intro'         => ' ',
        'visible'       => 1,
        'introformat'   => FORMAT_HTML, // =1
        'timeopen'      => 0,
        'timeclose'     => 0,
        'preferredbehaviour' => 'deferredfeedback',
        'attempts'      => 0,
        'attemptonlast' => 1,
        'grademethod'   => 1,
        'decimalpoints' => 2,
        'questionsperpage' => 0,
        'shufflequestions' => 0,
        'shuffleanswers' => 1,
        'timemodified'  => time(),
        'timelimit'     => 0,
        'subnet'        => '',
        'quizpassword'  => '',
        'delay1'        => 0,
        'delay2'        => 0,
        'questions'     => '0,',

        // feedback fields
        'feedbacktext'          => array_fill(0, 5, array('text' => '', 'format' => 0)),
        'feedbackboundarycount' => 0,
        'feedbackboundaries'    => array(0 => 0, -1 => 11),

        // these fields may not be necessary in Moodle 2.x
        'sumgrades'     => 0, // reset after adding questions
        'grade'         => 100,
        'adaptive'      => 1,
        'penaltyscheme' => 1,
        'popup'         => 0,

        // standard fields for adding a new cm
        'course'        => $targetcourseid,
        'section'       => $sectionnum,
        'module'        => $quizmodule->id,
        'modulename'    => 'quiz',
        'add'           => 'quiz',
        'update'        => 0,
        'return'        => 0,
        'cmidnumber'    => '',
        'groupmode'     => 0,
        'MAX_FILE_SIZE' => 10485760,
    );

    //$newquiz->instance = quiz_add_instance($newquiz);
    if (! $newquiz->instance = $DB->insert_record('quiz', $newquiz)) {
        return false;
    }
    if (! $newquiz->coursemodule = add_course_module($newquiz) ) { // $mod
        throw new reader_exception('Could not add a new course module');
    }
    $newquiz->id = $newquiz->coursemodule; // $cmid

    if (function_exists('course_add_cm_to_section')) {
        $sectionid = course_add_cm_to_section($targetcourseid, $newquiz->coursemodule, $sectionnum);
    } else {
        $sectionid = add_mod_to_section($newquiz);
    }
    if (! $sectionid) {
        throw new reader_exception('Could not add the new course module to that section');
    }
    if (! $DB->set_field('course_modules', 'section',  $sectionid, array('id' => $newquiz->coursemodule))) {
        throw new reader_exception('Could not update the course module with the correct section');
    }

    // if the section is hidden, we should also hide the new quiz activity
    if (! isset($newquiz->visible)) {   // We get the section's visible field status
        $newquiz->visible = $DB->get_field('course_sections', 'visible', array('id' => $sectionid));
    }
    set_coursemodule_visible($newquiz->coursemodule, $newquiz->visible);

    // Trigger mod_updated event with information about this module.
    $event = (object)array(
        'courseid'   => $newquiz->course,
        'cmid'       => $newquiz->coursemodule,
        'modulename' => $newquiz->modulename,
        'name'       => $newquiz->name,
        'userid'     => $USER->id
    );
    events_trigger('mod_updated', $event);

    // re-enable warnings about rebuild_course_cache
    $CFG->upgraderunning = $upgraderunning;

    return $newquiz;
}

/**
 * reader_xmldb_restore_questions
 *
 * @uses $DB
 * @param xxx $restore
 * @param xxx $xml
 * @param xxx $quizid
 * @todo Finish documenting this function
 */
function reader_xmldb_restore_questions($restore, $xml, $quizid) {
    global $DB;

    // map old question id onto new question id
    $questionids = reader_xmldb_get_questionids($xml, $restore);

    // map old question id onto question grade
    $questiongrades = reader_xmldb_get_questiongrades($xml);

    $sumgrades = 0;
    foreach ($questionids as $oldid => $newid) {
        $question_instance = (object)array(
            'quiz'     => $quizid,
            'question' => $newid,
            'grade'    => $questiongrades[$oldid],
        );
        $sumgrades += $question_instance->grade;
        $DB->insert_record('quiz_question_instances', $question_instance);
    }
    $DB->set_field('quiz', 'sumgrades', $sumgrades, array('id' => $quizid));
    $DB->set_field('quiz', 'questions', implode(',', $questionids).',0', array('id' => $quizid));
}

/**
 * reader_xmldb_get_questionids
 *
 * @param xxx $xml
 * @param xxx $restore
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_xmldb_get_questionids($xml, $restore) {
    $questionids = array();    // map old question id onto new question id
    if (isset($xml['MOODLE_BACKUP']['#']['COURSE']['0']['#']['MODULES']['0']['#']['MOD']['0']['#']['QUESTIONS']['0']['#'])) {
        $oldids = $xml['MOODLE_BACKUP']['#']['COURSE']['0']['#']['MODULES']['0']['#']['MOD']['0']['#']['QUESTIONS']['0']['#'];
    } else {
        $oldids = ''; // shouldn't happen !!
    }
    $oldids = explode(',', $oldids);
    $oldids = array_filter($oldids); // remove blanks

    foreach ($oldids as $oldid) {
        if ($newid = backup_getid($restore->backup_unique_code, 'question', $oldid)) {
            $questionids[$oldid] = $newid->new_id;
        }
    }
    return $questionids;
}

/**
 * reader_xmldb_get_questiongrades
 *
 * @param xxx $xml
 * @return xxx
 * @todo Finish documenting this function
 */
function reader_xmldb_get_questiongrades($xml) {
    $questiongrades = array(); // map old question id onto question grade
    if (isset($xml['MOODLE_BACKUP']['#']['COURSE']['0']['#']['MODULES']['0']['#']['MOD']['0']['#']['QUESTION_INSTANCES'])) {
        $instances = $xml['MOODLE_BACKUP']['#']['COURSE']['0']['#']['MODULES']['0']['#']['MOD']['0']['#']['QUESTION_INSTANCES']['0']['#']['QUESTION_INSTANCE'];
    } else {
        $instances = array(); // shouldn't happen !!
    }
    foreach ($instances as $instance) {
        $oldid = $instance['#']['QUESTION']['0']['#'];
        $grade = $instance['#']['GRADE']['0']['#'];
        $questiongrades[$oldid] = $grade;
    }
    return $questiongrades;
}


/**
 * reader_xmldb_init_qtypes
 *
 * @todo Finish documenting this function
 */
function reader_xmldb_init_qtypes() {
    global $CFG, $QTYPES, $QTYPE_MANUAL, $QTYPE_EXCLUDE_FROM_RANDOM;
    static $init = true;

    if ($init) {
        $init = false; // only do this once

        require_once($CFG->dirroot.'/mod/reader/lib/question/type/multianswer/questiontype.php');
        require_once($CFG->dirroot.'/mod/reader/lib/question/type/multichoice/questiontype.php');
        require_once($CFG->dirroot.'/mod/reader/lib/question/type/ordering/questiontype.php');
        require_once($CFG->dirroot.'/mod/reader/lib/question/type/truefalse/questiontype.php');
        require_once($CFG->dirroot.'/mod/reader/lib/question/type/random/questiontype.php');
        require_once($CFG->dirroot.'/mod/reader/lib/question/type/match/questiontype.php');
        require_once($CFG->dirroot.'/mod/reader/lib/question/type/description/questiontype.php');

        $QTYPES = array(
            'multianswer' => new back_multianswer_qtype(),
            'multichoice' => new back_multichoice_qtype(),
            'ordering'    => new back_ordering_qtype(),
            'truefalse'   => new back_truefalse_qtype(),
            'random'      => new back_random_qtype(),
            'match'       => new back_match_qtype(),
            'description' => new back_description_qtype()
        );

        $QTYPE_MANUAL = array();
        $QTYPE_EXCLUDE_FROM_RANDOM = array();

        foreach ($QTYPES as $name => $qtype) {
            if (method_exists($qtype, 'is_manual_graded') && $qtype->is_manual_graded()) {
                $QTYPE_MANUAL[] = "'$name'";
            }
            if (method_exists($qtype, 'is_usable_by_random') && ! $qtype->is_usable_by_random()) {
                $QTYPE_EXCLUDE_FROM_RANDOM[] = "'$name'";
            }
        }

        $QTYPE_MANUAL = implode(',', $QTYPE_MANUAL);
        $QTYPE_EXCLUDE_FROM_RANDOM = implode(',', $QTYPE_EXCLUDE_FROM_RANDOM);
    }
}
