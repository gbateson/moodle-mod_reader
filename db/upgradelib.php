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

/**
 * xmldb_reader_check_files
 *
 * @uses $FULLME the full url, including query string, of this page
 * @uses $OUTPUT
 * @return void, but may pause the update if stale files are found
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die;

/**
 * xmldb_reader_check_stale_files
 *
 * @uses $FULLME
 * @uses $OUTPUT
 * @todo Finish documenting this function
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
    global $CFG, $DB, $OUTPUT;
    require_once($CFG->dirroot.'/course/lib.php');

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

            $select = 'rb.id, rb.publisher, rb.level, rb.name, rb.quizid, '.
                      'q.id AS associated_quizid, q.name AS associated_quizname';
            $from   = '{reader_books} rb LEFT JOIN {quiz} q ON rb.quizid = q.id ';
            $where  = 'rb.publisher = :publisher AND rb.level = :level AND rb.name = :bookname';
            $params = array('publisher' => $duplicate->publisher, 'level' => $duplicate->level, 'bookname' => $duplicate->name);
            $orderby = 'rb.publisher, rb.level, rb.name, rb.time';

            // get the duplicate books (and associated quizzes)
            if ($books = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $orderby", $params)) {

                $mainbookid = 0;
                $mainquizid = 0;
                $mainquizadded = 0;
                $mainquizvisible = 0;

                foreach ($books as $book) {
                    if ($book->associated_quizid && $book->name==$book->associated_quizname) {
                        $mainbookid = $book->id;
                        if ($cm = $DB->get_record('course_modules', array('module' => $quizmoduleid, 'instance' => $book->quizid))) {
                            if ($mainquizid==0 || $mainquizvisible < $cm->visible || $mainquizadded < $cm->added) {
                                $mainquizid = $cm->instance;
                                $mainquizadded = $cm->added;
                                $mainquizvisible = $cm->visible;
                            }
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
                            // start publisher list
                            xmldb_reader_box_start('The following duplicate books were fixed');
                        } else {
                            echo '</ul></li>'; // finish book list
                        }
                        // start book list for this publisher
                        echo "<li><b>$book->publisher</b> ".xmldb_reader_showhide_img();
                        echo '<ul>';
                        $publisher = $book->publisher;
                    }
                    echo "<li><b>$book->name</b> (bookid=$book->id) ".xmldb_reader_showhide_img()."<ul>";

                    if ($mainquizid && $book->quizid && $mainquizid != $book->quizid) {
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
                                if ($book->quizid==1 || $book->quizid==22 || $book->quizid==1464) {
                                    die('Oops we are trying to delete one of the Cinderella quizzes'. " (quiz id = $book->quizid)");
                                }
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
            echo '</ul></li>';
            xmldb_reader_box_end();
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
    global $CFG, $DB, $OUTPUT;
    require_once($CFG->dirroot.'/course/lib.php');

    $rebuild_course_cache = false;

    $section_quizname = $DB->sql_concat('cm.section', "'_'", 'q.name');
    $select = "$section_quizname AS section_quizname, ".
              'cm.section AS sectionid, cm.module AS moduleid, '.
              'q.name AS quizname, COUNT(*) AS countquizzes';
    $from   = '{course_modules} cm '.
              'LEFT JOIN {modules} m ON cm.module = m.id '.
              'LEFT JOIN {quiz} q ON cm.instance = q.id ';
    $where  = 'm.name = ? AND cm.course = ?';
    $groupby = 'cm.section, q.name HAVING COUNT(*) > 1';
    $params = array('quiz', $course->id);

    // extract all duplicate (i.e. same section and name) quizzes in main reader course
    if ($duplicates = $DB->get_records_sql("SELECT $select FROM $from WHERE $where GROUP BY $groupby", $params)) {
        xmldb_reader_box_start('The following duplicate quizzes were fixed');
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
        xmldb_reader_box_end();
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

    // sanity check on $oldid
    if ($oldid===null || $oldid===false || $oldid===0 || $oldid==='' || $oldid==='0') {
        return false;
    }

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

    if ($courseid = get_config('reader', 'reader_usecourse')) { // old config name
        $courseids[] = $courseid;
    } else if ($courseid = get_config('reader', 'usecourse')) { // new config name
        $courseids[] = $courseid;
    }

    // $select = 'SELECT DISTINCT usecourse FROM {reader} WHERE usecourse IS NOT NULL AND usecourse > ?';
    $select = 'SELECT DISTINCT q.course FROM {reader_books} rb LEFT JOIN {quiz} q ON rb.quizid = q.id WHERE q.id IS NOT NULL';
    $select = "id IN ($select) AND visible = ?";
    $params = array($courseid, 0);
    if ($courses = $DB->get_records_select('course', $select, $params, 'id', 'id,visible')) {
        $courseids = array_merge($courseids, array_keys($courses));
        $courseids = array_unique($courseids);
        sort($courseids);
    }

    return $courseids;
}

/**
 * xmldb_reader_showhide_start_js
 *
 * @todo Finish documenting this function
 */
function xmldb_reader_showhide_start_js() {
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
        $js .= "function showhide_lists(forcehide) {\n";
        $js .= "    var img = document.getElementsByTagName('img');\n";
        $js .= "    if (img) {\n";
        $js .= "        var targetsrc = new RegExp('switch_(minus'+(forcehide ? '' : '|plus')+')');\n";
        $js .= "        var i_max = img.length;\n";
        $js .= "        for (var i=0; i<=i_max; i++) {\n";
        $js .= "            if (img[i].src.match(targetsrc)) {\n";
        $js .= "                showhide_list(img[i]);\n";
        $js .= "            }\n";
        $js .= "        }\n";
        $js .= "    }\n";
        $js .= "}\n";
        $js .= "//]]>\n";
        $js .= '</script>'."\n";
    }
    return $js;
}

/**
 * xmldb_reader_showhide_end_js
 *
 * @todo Finish documenting this function
 */
function xmldb_reader_showhide_end_js() {
    $js = '';
    $js .= '<script type="text/javascript">'."\n";
    $js .= "//<![CDATA[\n";
    $js .= "showhide_lists(true);\n"; // force hide
    $js .= "//]]>\n";
    $js .= '</script>'."\n";
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
 * @todo Finish documenting this function
 */
function xmldb_reader_fix_question_instances() {
    global $DB;

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

    $courseids = xmldb_reader_quiz_courseids();

    if (count($courseids)) {
        list($courseselect, $courseparams) = $DB->get_in_or_equal($courseids);
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

                        $select = "id = ? AND course $courseselect";
                        $params = array($quiz_question_instance->quiz);
                        $params = array_merge($params, $courseparams);

                        if ($DB->record_exists_select('quiz', $select, $params)) {
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
 * xmldb_reader_fix_wrong_sectionnames
 *
 * @todo Finish documenting this function
 */
function xmldb_reader_fix_wrong_sectionnames() {
    global $DB, $OUTPUT;

    $courseids = xmldb_reader_quiz_courseids();
    foreach ($courseids as $courseid) {

        if (! $course = $DB->get_record('course', array('id' => $courseid))) {
            continue;
        }
        if (! $sections = $DB->get_records('course_sections', array('course' => $courseid))) {
            continue;
        }

        $started_box = false;
        $rebuild_course_cache = false;
        foreach ($sections as $sectionid => $section) {

            if ($section->section==0) {
                continue; // ignore intro section
            }
            if ($section->sequence=='') {
                continue; // ignore empty section
            }

            $cmids = explode(',', $section->sequence);
            $cmids = array_filter($cmids); // remove blanks

            $quizids = array();
            foreach ($cmids as $cmid) {
                $cm = get_coursemodule_from_id('', $cmid);
                if ($cm->modname=='quiz') {
                    $quizids[] = $cm->instance;
                }
            }

            $sectionname = '';
            $sectionnames = array();
            if (count($quizids)) {
                list($select, $params) = $DB->get_in_or_equal($quizids);
                if ($books = $DB->get_records_select('reader_books', "quizid $select", $params)) {
                    foreach ($books as $book) {
                        $sectionname = $book->publisher;
                        if ($book->level=='' || $book->level=='--') {
                            // do nothing
                        } else {
                            $sectionname .= ' - '.$book->level;
                        }
                        if (empty($sectionnames[$sectionname])) {
                            $sectionnames[$sectionname] = array();
                        }
                        $sectionnames[$sectionname][] = $book->name;
                    }
                }
            }

            if ($count = count($sectionnames)) {
                if ($count==1 && $section->name==$sectionname) {
                    // good - we only found the expected sectionname
                } else {
                    // oops - at least one unexpected quiz found
                    if ($started_box==false) {
                        $started_box = true;
                        xmldb_reader_box_start('The following course sections were adjusted');
                    }
                    if ($count==1) {
                        echo html_writer::tag('li', "Reset section name: $section->name => $sectionname");
                        $DB->set_field('course_sections', 'name', $sectionname, array('id' => $sectionid));
                        $rebuild_course_cache = true;
                    } else {
                        foreach ($sectionnames as $sectionname => $books) {
                            sort($books);
                            $count = count($books);
                            $sectionnames[$sectionname] = "$sectionname ($count books)";
                            $sectionnames[$sectionname] .= html_writer::alist($books);
                        }
                        $sectionnames = array_values($sectionnames);
                        echo html_writer::start_tag('li');
                        echo 'Quizzes for books by multiple publishers / levels found in section: '.$section->name;
                        echo html_writer::alist($sectionnames);
                        echo html_writer::end_tag('li');
                    }
                }
            }
        }

        if ($started_box==true) {
            xmldb_reader_box_end();
        }

        if ($rebuild_course_cache) {
            echo html_writer::tag('div', "Re-building course cache: $course->shortname ... ", array('class' => 'notifysuccess'));
            rebuild_course_cache($courseid, true); // $clearonly must be set to true
        }
    }
}

/**
 * xmldb_reader_fix_wrong_quizids
 *
 * @uses $CFG
 * @uses $DB
 * @uses $SESSION
 *
 * @todo Finish documenting this function
 */
function xmldb_reader_fix_wrong_quizids() {
    global $DB, $OUTPUT, $SESSION;

    // SQL to detect unexpected section name for a book
    $sectionname = $DB->sql_concat('rb.publisher', "' - '", 'rb.level');
    $sectionname = "(CASE WHEN (rb.level IS NULL OR rb.level = ? OR rb.level = ?) THEN rb.publisher ELSE $sectionname END)";
    $wrongsectionname = "cs.name <> $sectionname";

    // SQL to detect unexpected quiz name for a book
    $quizname = $DB->sql_concat("'%'", 'rb.name', "'%'");
    $wrongquizname = str_replace('???', $quizname, $DB->sql_like('q.name', '???', false, false, true));

    // this should leave $wrongquizname looking something like this ...
    // $wrongquizname = "q.name NOT LIKE CONCAT('%', rb.name, '%')"

    // extract books with wrong (but valid) quizid
    $select = 'rb.id, rb.publisher, rb.level, rb.name, rb.quizid, '.
              'q.name AS quizname, q.course AS courseid, '.
              'cs.name AS sectionname';
    $from   = '{reader_books} rb '.
              'LEFT JOIN {quiz} q ON rb.quizid = q.id '.
              'LEFT JOIN {course_modules} cm ON cm.instance = q.id '.
              'LEFT JOIN {course_sections} cs ON cs.id = cm.section '.
              'LEFT JOIN {modules} m ON m.id = cm.module';
    $where  = 'm.name = ? '.
              'AND q.id  IS NOT NULL '.
              'AND cm.id IS NOT NULL '.
              'AND m.id  IS NOT NULL '.
              'AND cs.id IS NOT NULL '.
              'AND ('.$wrongquizname.' OR '.$wrongsectionname.')';
    $params = array('quiz', '', '--');
    $orderby = 'rb.publisher,rb.level,rb.name';

    // Note - you could store bookquizids as a config setting:
    // $bookquizids = get_config('reader', 'bookquizids');
    // $bookquizids = unserialize($bookquizids);
    // set_config('bookquizids', serialize($bookquizids), 'reader');
    // unset_config('bookquizids', 'reader');

    // get list of books with manually fixed quiz ids
    if (isset($SESSION->bookquizids)) {
        $bookquizids = unserialize($SESSION->bookquizids);
    } else {
        $bookquizids = array();
    }

    // exclude any book ids that have already been fixed manually
    if (count($bookquizids)) {
        $bookids = array_keys($bookquizids);
        list($filter, $bookids) = $DB->get_in_or_equal($bookids, SQL_PARAMS_QM, 'param', false); // NOT IN (...)
        $where = "$where AND rb.id $filter";
        $params = array_merge($params, $bookids);
    }

    $started_box = false;
    if ($books = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $orderby", $params)) {

        foreach ($books as $book) {
            $sectionname = $book->publisher;
            if ($book->level=='' || $book->level=='--') {
                // do nothing
            } else {
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
                $quiz = reset($quiz); // most recent, visible quiz in expected section
            }

            // check if the user has told us which quiz to use for this book
            $quizidparamname = 'bookquizid'.$book->id;
            $quizid = optional_param($quizidparamname, null, PARAM_INT);

            if (empty($quiz) && $quizid===null) {
                // offer form to select quizid
                $where = $DB->sql_like('q.name', '?').' AND cm.id IS NOT NULL AND cs.id IS NOT NULL AND m.id IS NOT NULL';
                $params = array('quiz', "$book->name%");
                if ($quizzes = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $orderby", $params)) {
                    // build select list (sectionname -> quiznames)

                    // params for "select" button urls
                    $params = array(
                        'confirmupgrade' => optional_param('confirmupgrade', 0, PARAM_INT),
                        'confirmrelease' => optional_param('confirmrelease', 0, PARAM_INT),
                        'confirmplugincheck' => optional_param('confirmplugincheck', 0, PARAM_INT),
                    );

                    $table = new html_table();
                    $table->head = array(get_string('sectionname', 'reader'),
                                         get_string('quizname', 'reader'),
                                         get_string('select'));
                    $table->align = array('left', 'left', 'center');

                    // add candidate quizzes to the table
                    foreach ($quizzes as $quiz) {

                        // create button url with this quiz id
                        $params[$quizidparamname] = $quiz->id;
                        $url = new moodle_url('/admin/index.php', $params);

                        $table->data[] = new html_table_row(array(
                            $quiz->sectionname,
                            $quiz->name,
                            $OUTPUT->single_button($url, get_string('selectthisquiz', 'reader'), 'get')
                        ));
                    }

                    $message = get_string('fixwrongquizidinfo', 'reader');
                    $message = format_text($message, FORMAT_MARKDOWN);
                    $message .= html_writer::table($table);

                    // close the HTML box, if necessary
                    if ($started_box==true) {
                        $started_box==false;
                        xmldb_reader_box_end();
                    }

                    // params for "fixwrongquizid" message (book name and id)
                    $params = (object)array('name' => "$sectionname: $book->name", 'id' => $book->id);

                    $output = '';
                    $output .= $OUTPUT->heading(get_string('fixwrongquizid', 'reader', $params));
                    $output .= $OUTPUT->box($message, 'generalbox', 'notice');
                    $output .= $OUTPUT->footer();

                    echo $output;
                    die;
                }
            }

            $msg = array();
            if (empty($quiz)) {

                // update the cached array mapping $book->id => $quizid mapping
                $bookquizids[$book->id] = $quizid;
                $SESSION->bookquizids = serialize($bookquizids);

                if ($quizid) {
                    if ($quiz = $DB->get_record('quiz', array('id' => $quizid), 'id,name')) {
                        $msg[] = "Found quiz for $sectionname: $book->name (quiz id = $book->quizid)";
                    } else {
                        $msg[] = "OOPS, could not locate quiz for $sectionname: $book->name (quiz id = $book->quizid)";
                    }
                } else { // $quizid==0 so user wants to skip this book
                    $msg[] = "Restting of quiz for $sectionname: $book->name (book id=$book->id) was skipped";
                }
            }

            if ($quiz) {
                if ($book->quizid != $quiz->id) {
                    $msg[] = "Reset quiz for $sectionname: $book->name (quiz id $book->quizid => $quiz->id)";
                    $DB->set_field('reader_books', 'quizid', $quiz->id, array('id' => $book->id));
                }
            }

            if (count($msg)) {
                if ($started_box==false) {
                    $started_box = true;
                    xmldb_reader_box_start('The quiz id for the following books was fixed');
                }
                echo html_writer::tag('li', implode('</li><li>', $msg));
            }
        }
        if ($started_box==true) {
            xmldb_reader_box_end();
        }
    }
}

/**
 * xmldb_reader_fix_uniqueids
 *
 * @param xxx $dbman (passed by reference)
 * @todo Finish documenting this function
 */
function xmldb_reader_fix_uniqueids(&$dbman) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/mod/reader/lib.php');

    $started_box = false;

    $contexts = array();
    $quizzes = array();

    // extract all attempts with duplicate uniqueid - there should be none of these
    if ($duplicates = $DB->get_records_sql("SELECT uniqueid FROM {reader_attempts} GROUP BY uniqueid HAVING COUNT(*) > 1")) {
        foreach ($duplicates as $duplicate) {
            if ($attempts = get_records('reader_attempts', array('uniqueid' => $duplicate->uniqueid), 'timestart')) {
                array_shift($attempts); // remove earliest attempt
                foreach ($attempts as $attempt) {
                    xmldb_reader_fix_uniqueid($dbman, $contexts, $quizzes, $attempt, $started_box);
                }
            }
        }
    }

    // extract reader_attempts with invalid unqueid
    // i.e. one that is not am id in the "question_usages" table
    if ($dbman->table_exists('question_usages')) { // Moodle >= 2.1
        $select = 'ra.*, qu.id AS questionusageid';
        $from   = '{reader_attempts} ra LEFT JOIN {question_usages} qu ON ra.uniqueid = qu.id';
        $where  = 'ra.uniqueid < ? OR qu.id IS NULL';
        $params = array(0);
    } else if ($dbman->table_exists('question_attempts')) { // Moodle 2.0
        $select = 'ra.*, qa.id AS questionusageid';
        $from   = '{reader_attempts} ra LEFT JOIN {question_attempts} qa ON ra.uniqueid = qa.id';
        $where  = 'ra.uniqueid < ? OR qa.id IS NULL';
        $params = array(0);
    }
    if ($attempts = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params)) {
        foreach ($attempts as $attempt) {
            xmldb_reader_fix_uniqueid($dbman, $contexts, $quizzes, $attempt, $started_box);
        }
    }

    if ($started_box==true) {
        xmldb_reader_box_end();
    }
}

/**
 * xmldb_reader_fix_uniqueid
 *
 * @param xxx $dbman (passed by reference)
 * @param array $course (passed by reference) $readerid => $context record from "contexts" table
 * @param array $quizzes (passed by reference) $quizid => $quiz record from "quiz" table
 * @param object $attempt (passed by reference) record from "reader_attempts" table
 * @param boolean $started_box (passed by reference)
 * @return void, but will modify $attempt->uniqueid
 *               and update "reader_attempts" and "question_usages" tables in DB
 * @todo Finish documenting this function
 */
function xmldb_reader_fix_uniqueid(&$dbman, &$contexts, &$quizzes, &$attempt, &$started_box) {
    global $DB;

    static $uniqueid = null;
    static $readermoduleid = null;

    // get next available (negative) temporary $uniqueid
    if ($uniqueid===null) {
        if ($uniqueid = $DB->get_field_sql('SELECT MIN(uniqueid) FROM {reader_attempts}')) {
            $uniqueid = min(0, $uniqueid) - 1;
        } else {
            $uniqueid = -1;
        }
    }

    $dbman = $DB->get_manager();
    if ($dbman->table_exists('question_usages')) {
        // Moodle >= 2.1

        // cache readermoduleid
        if ($readermoduleid===null) {
            $readermoduleid = $DB->get_field('modules', 'id', array('name' => 'reader'));
        }

        // fetch context, if necessary
        if (empty($contexts[$attempt->reader])) {
            if ($cm = $DB->get_record('course_modules', array('module' => $readermoduleid, 'instance' => $attempt->reader))) {
                $contexts[$attempt->reader] = reader_get_context(CONTEXT_MODULE, $cm->id);
            } else {
                // shouldn't happen - the reader has been deleted but the attempt remains ?
                // let's see if any other attempts at this reader have a valid uniqueids
                $select = 'ra.id, ra.uniqueid, qu.id AS questionusageid, qu.contextid, qu.preferredbehaviour';
                $from   = '{reader_attempts} ra LEFT JOIN {question_usages} qu ON ra.uniqueid = qu.id';
                $where  = 'ra.reader = ? AND qu.id IS NOT NULL';
                $params = array($attempt->reader);
                if ($records = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params)) {
                    // we can get the contextid from the other "question_usage" records
                    $record = reset($records); // i.e. first record
                    $contexts[$attempt->reader] = (object)array('id' => $record->contextid);
                } else {
                    // otherwise use the system context - should never happen !!
                    $contexts[$attempt->reader] = reader_get_context(CONTEXT_SYSTEM);
                }
            }
        }

        // fetch quiz record, if necessary
        if (empty($quizzes[$attempt->quizid])) {
            if (! $quizzes[$attempt->quizid] = $DB->get_record('quiz', array('id' => $attempt->quizid))) {
                // shouldn't happen - but we can continue if we create a dummy quiz record ...
                $quizzes[$attempt->quizid] = (object)array('id' => $attempt->quizid,
                                                           'name' => "Invalid quizid = $attempt->quizid",
                                                           'preferredbehaviour' => 'deferredfeedback');
            }
        }

        // create question_usage record for this attempt
        $question_usage = (object)array(
            'contextid' => $contexts[$attempt->reader]->id,
            'component' => 'mod_reader',
            'preferredbehaviour' => $quizzes[$attempt->quizid]->preferredbehaviour
        );
        $newuniqueid = $DB->insert_record('question_usages', $question_usage);
        $olduniqueid = $attempt->uniqueid;

    } else if ($dbman->table_exists('question_attempts') && $dbman->field_exists('question_attempts', 'modulename')) {
        // Moodle 2.0
        $question_attempt = (object)array('modulename' => 'reader');
        $newuniqueid = $DB->insert_record('question_attempts', $question_attempt);
        $olduniqueid = $attempt->uniqueid;
    }

    // if any reader_attempt record is already using the $newuniqueid
    // then give it a unique but temporary negative $uniqueid,
    // so the uniqueness of the "uniqueid" field is preserved
    $DB->set_field('reader_attempts', 'uniqueid', $uniqueid--, array('uniqueid' => $newuniqueid));

    // update attempt record
    $attempt->uniqueid = $newuniqueid;
    $DB->set_field('reader_attempts', 'uniqueid', $attempt->uniqueid, array('id' => $attempt->id));

    // tell the user what just happened
    if ($started_box==false) {
        $started_box = true;
        echo xmldb_reader_box_start('The following reader attempts had their uniqueids fixed');
    }
    echo html_writer::tag('li', $quizzes[$attempt->quizid]->name.": OLD: $olduniqueid => NEW: $newuniqueid");
}

/**
 * xmldb_reader_fix_nonunique_quizids
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
        echo xmldb_reader_box_start('The following books have a non-unique quizid');

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
                $missingquizids[$book->id] = $book->quizid;
            }
        }
        xmldb_reader_box_end();
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

    // remove the "reader_" prefix from the config settings, if necessary
    $vars = get_object_vars($readercfg);
    foreach ($vars as $oldname => $value) {
        if (substr($oldname, 0, 7)=='reader_') {
            unset($readercfg->$oldname);
            $newname = substr($oldname, 7);
            $readercfg->$newname = $value;
        }
    }

    $params = array('a' => 'publishers',
                    'login' => $readercfg->serverlogin,
                    'password' => $readercfg->serverpassword);
    $url = new moodle_url($readercfg->serverlink.'/', $params);

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
                    'login' => $readercfg->serverlogin,
                    'password' => $readercfg->serverpassword);
    $url = new moodle_url($readercfg->serverlink.'/', $params);
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
    if ($targetcourseid = $readercfg->usecourse) {
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
        if ($level=='' || $level=='--') {
            // do nothing
        } else {
            $sectionname .= ' - '.$level;
        }
        $sectionnum = reader_xmldb_get_sectionnum($targetcourse, $sectionname);

        // create new quiz and update this book
        $cm = reader_xmldb_get_newquiz($targetcourseid, $sectionnum, $quizmodule, $name);
        $DB->set_field('reader_books', 'quizid', $cm->instance, array('id' => $bookid));

        // download questions for this quiz
        $params = array('getid' => $itemid, 'pass' => ''); // $pass
        $url = new moodle_url($readercfg->serverlink.'/getfile.php', $params);
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
        $image_file_url = new moodle_url($readercfg->serverlink.'/getfile_quiz_image.php', $params);
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

/**
 * xmldb_reader_fix_duplicates
 *
 * @todo Finish documenting this function
 */
function xmldb_reader_fix_duplicates() {
    global $DB;

    $keepoldquizzes = get_config('reader', 'keepoldquizzes');
    $courseids = xmldb_reader_quiz_courseids();

    foreach ($courseids as $courseid) {
        if ($course = $DB->get_record('course', array('id' => $courseid))) {
            $rebuild_course_cache = false;
            if (xmldb_reader_fix_duplicate_books($course, $keepoldquizzes)) {
                $rebuild_course_cache = true;
            }
            if (xmldb_reader_fix_duplicate_quizzes($course, $keepoldquizzes)) {
                $rebuild_course_cache = true;
            }
            if ($rebuild_course_cache) {
                echo html_writer::tag('div', "Re-building course cache: $course->shortname ... ", array('class' => 'notifysuccess'));
                rebuild_course_cache($course->id, true); // $clearonly must be set to true
            }
        }
    }
}

/**
 * xmldb_reader_fix_question_categories
 *
 * @todo Finish documenting thi function
 */
function xmldb_reader_fix_question_categories() {
    global $CFG, $DB, $OUTPUT;
    require_once($CFG->dirroot.'/mod/reader/lib.php');

    // get contexts for quizzes in of courses where Reader quizzes are stored
    $courseids = xmldb_reader_quiz_courseids();
    $select = array();
    $params = array();
    foreach ($courseids as $courseid) {
        if ($coursecontext  = reader_get_context(CONTEXT_COURSE, $courseid)) {
            array_push($select, '((contextlevel = ? AND path = ?) OR (contextlevel = ? AND '.$DB->sql_like('path', '?').'))');
            array_push($params, CONTEXT_COURSE, $coursecontext->path, CONTEXT_MODULE, $coursecontext->path.'/%');
        }
    }

    // check we found some quizzes
    if (! $select = implode(' OR ', $select)) {
        return true; // no Reader quizzes - unusual ?!
    }

    // get reader course activity contexts
    if (! $modulecontexts = $DB->get_records_select('context', $select, $params)) {
        return false; // shouldn't happen !!
    }

    // first we tidy up the reader_question_instances table
    $select  = 'question, COUNT(*)';
    $from    = '{reader_question_instances}';
    $groupby = 'question HAVING COUNT(*) > 1';
    $params  = array();
    if ($duplicates = $DB->get_records_sql("SELECT $select FROM $from GROUP BY $groupby", $params)) {
        $started_box = false;
        foreach ($duplicates as $duplicate) {
            if ($instances = $DB->get_records('reader_question_instances', array('question' => $duplicate->question), 'id')) {
                $instanceids = array_keys($instances);
                $instanceid = array_shift($instanceids); // keep this one :-)
                list($select, $params) = $DB->get_in_or_equal($instanceids);
                $DB->delete_records_select('reader_question_instances', 'id '.$select, $params);
                if ($started_box==false) {
                    $started_box = true;
                    echo xmldb_reader_box_start('The following reader question instances were fixed');
                }
                $msg = '<span style="color: red;">DELETE</span> '.count($instanceids).' duplicate question instance(s) (id IN '.implode(', ', $instanceids).')';
                echo html_writer::tag('li', $msg);
            }
        }
        if ($started_box==true) {
            xmldb_reader_box_end();
        }
    }

    // unset all missing parent question ids
    // (the "parent" question is the old version of a question that was edited)

    $select = 'q1.id, q1.parent';
    $from   = '{question} q1 LEFT JOIN {question} q2 ON q1.parent = q2.id';
    $where  = 'q1.parent > 0 AND q2.id IS NULL';
    if ($questions = $DB->get_records_sql("SELECT $select FROM $from WHERE $where")) {

        echo xmldb_reader_box_start('The following reader questions were fixed');

        $msg = '<span style="color: brown;">RESET</span> parent ids on '.count($questions).' questions (id  IN '.implode(', ', array_keys($questions)).')';
        echo html_writer::tag('li', $msg);

        list($select, $params) = $DB->get_in_or_equal(array_keys($questions));
        $DB->set_field_select('question', 'parent', 0, 'id '.$select, $params);

        xmldb_reader_box_end();
    }

    // get question categories for Reader course activities

    $started_box = false;

    list($select, $params) = $DB->get_in_or_equal(array_keys($modulecontexts));
    if ($categories = $DB->get_records_select('question_categories', 'contextid '.$select, $params)) {

        foreach ($categories as $category) {

            $msg = '';

            // search and replace strings to fix "ordering" instructions
            $search = '/(?<=Put the following events )("[^"]*")\s*/';
            $replace = 'from $1 ';

            // count random and non-random questions
            $random = 0;
            $nonrandom = 0;
            if ($questions = $DB->get_records('question', array('category' => $category->id))) {
                foreach ($questions as $question) {
                    if ($question->qtype=='random') {
                        $random++;
                    } else {
                        $nonrandom++;
                        if ($question->qtype=='ordering') {
                            $update = 0;
                            $question->questiontext = preg_replace($search, $replace, $question->questiontext, -1, $update);
                            if ($update) {
                                $DB->set_field('question', 'questiontext', $question->questiontext, array('id' => $question->id));
                            }
                        }
                    }
                }
            }

            if ($nonrandom) {
                // category contains at least one non-random quiz
            } else if ($random) {
                // category contains only "random" questions, check if they are used or not
                list($select, $params) = $DB->get_in_or_equal(array_keys($questions));
                if ($DB->count_records_select('reader_question_instances', 'question '.$select, $params)) {
                    // at least one questions is used in at least one reader quiz
                } else if ($DB->count_records_select('quiz_question_instances', 'question '.$select, $params)) {
                    // at least one questions is used in at least one non-reader quiz
                } else {
                    // questions are NOT used in any quizzes
                    $DB->delete_records_select('question', 'id '.$select, $params);
                    $msg .= '<li><span style="color: red;">DELETE</span> '.$random.' unused random questions ('.implode(', ', array_keys($questions)).') from category '.$category->name.' (id='.$category->id.')</li>';
                }
            }

            if ($DB->record_exists('question_categories', array('parent' => $category->id))) {
                $keep = true;  // a parent category
            } else if (substr($category->name, 0, 11)=='Default for') {
                $keep = true;  // an empty parent category
            } else if ($DB->get_records('question', array('category' => $category->id))) {
                $keep = true;  // category contains questions
            } else {
                $keep = false; // empty category
            }

            if ($keep && $category->contextid==$coursecontext->id) {
                // this category is in a course context, but it should NOT be
                // let's see if we can move the questions to a quiz context
                if ($questions = $DB->get_records('question', array('category' => $category->id))) {
                    list($select, $params) = $DB->get_in_or_equal(array_keys($questions));
                    if ($instances = $DB->get_records_select('reader_question_instances', 'question '.$select, $params)) {
                        // these questions are used in Reader quizzes
                    } else if ($instances = $DB->get_records_select('quiz_question_instances', 'question '.$select, $params)) {
                        // these questions are used in Moodle quizzes
                        $quizids = array();
                        foreach ($instances as $instance) {
                            $quizids[$instance->quiz] = true;
                        }
                        $quizids = array_keys($quizids);
                        if (count($quizids)==1) {
                            // move questions to this quiz's context
                            $quizid = reset($quizids);
                            if (! $cm = get_coursemodule_from_instance('quiz', $quizid)) {
                                $msg .= '<li><span style="color: red;">OOPS</span> course module record not found for quizid='.$quizid.'</li>';
                            } else if (! $quizcontext = reader_get_context(CONTEXT_MODULE, $cm->id)) {
                                $msg .= '<li><span style="color: red;">OOPS</span> context record not found for cm id='.$cm->id.'</li>';
                            } else {
                                $DB->set_field('question_categories', 'parent', 0, array('id' => $category->id));
                                $DB->set_field('question_categories', 'contextid', $quizcontext->id, array('id' => $category->id));
                                $msg .= '<li><span style="color: green;">MOVED</span> '.count($questions).' active questions ('.implode(', ', array_keys($questions)).') to new context (id='.$quizcontext->id.', quiz name='.$cm->name.')</li>';
                            }
                        } else {
                            // questions are used by several quizzes
                            $msg .= '<li><span style="color: red;">COULD NOT MOVE</span> '.count($questions).' active questions ('.implode(', ', array_keys($questions)).') because they are used in more than one quiz</li>';
                        }
                    } else {
                        // these questions are not used in any quizzes so we can delete them
                        list($select, $params) = $DB->get_in_or_equal(array_keys($questions));
                        $DB->delete_records_select('question', 'id '.$select, $params);
                        $msg .= '<li><span style="color: red;">DELETE</span> '.count($questions).' unused non-random questions ('.implode(', ', array_keys($questions)).') from category '.$category->name.' (id='.$category->id.')</li>';
                        $keep = false;
                    }
                }
            }

            if ($keep) {
                // remove slashes from category name
                if (strpos($category->name, '\\') !== false) {
                    $msg .= '<li><span style="color: brown;">FIX</span> slashes in category name: '.$category->name.' (id='.$category->id.')</li>';
                    $DB->set_field('question_categories', 'name', stripslashes($category->name), array('id' => $category->id));
                }
                // fix case of category name
                if ($category->name=='ordering' || $category->name=='ORDERING') {
                    $msg .= '<li><span style="color: brown;">FIX</span> category name: '.$category->name.' =&gt; Ordering (id='.$category->id.')</li>';
                    $DB->set_field('question_categories', 'name', 'Ordering', array('id' => $category->id));
                }
                // remove slashes from category info
                if (strpos($category->info, '\\') !== false) {
                    $msg .= '<li><span style="color: brown;">FIX</span> slashes in category info: '.$category->info.' (id='.$category->id.')</li>';
                    $DB->set_field('question_categories', 'info', stripslashes($category->info), array('id' => $category->id));
                }
            } else {
                // delete this category
                $msg .= '<li><span style="color: red;">DELETE</span> empty category: '.$category->name.' (id='.$category->id.')</li>';
                $DB->delete_records('question_categories', array('id' => $category->id));
            }

            if ($msg) {
                if ($started_box==false) {
                    $started_box = true;
                    xmldb_reader_box_start('The following reader question categories were fixed');
                }
                echo $msg;
            }
        }
    }

    if ($started_box==true) {
        xmldb_reader_box_end();
    }
}

/**
 * xmldb_reader_fix_duplicate_attempts
 *
 * @todo Finish documenting this function
 */
function xmldb_reader_fix_duplicate_attempts() {
    global $CFG, $DB, $OUTPUT;

    if ($i_max = $DB->count_records_sql("SELECT COUNT(*) FROM {reader_attempts}")) {
        $select = 'ra.*, rb.publisher, rb.level, rb.name AS bookname, rb.sametitle, u.firstname, u.lastname';
        $from   = '{reader_attempts} ra LEFT JOIN {reader_books} rb ON ra.quizid = rb.quizid LEFT JOIN {user} u ON ra.userid = u.id';
        $quizid = '(CASE WHEN rb.sametitle <> :sametitle THEN rb.sametitle ELSE ra.quizid END)';
        $orderby = "ra.userid ASC, $quizid ASC, ra.percentgrade DESC, ra.timestart DESC, ra.attempt DESC";
        $params = array('sametitle' => '');
        $rs = $DB->get_recordset_sql("SELECT $select FROM $from ORDER BY $orderby", $params);
    } else {
        $rs = false;
    }

    if ($rs) {
        $i = 0; // record counter
        $bar = new progress_bar('readerfixordering', 500, true);
        $strupdating = 'Fixing duplicate Reader attempts'; // get_string('fixattempts', 'reader');
        $strdeleted = get_string('deleted');

        $started_box = false;

        // loop through attempts
        $userid = 0;
        $quizid = 0;
        $sametitle = '';
        $countdeleted = 0;
        foreach ($rs as $attempt) {
            $i++; // increment record count

            // apply for more script execution time (3 mins)
            upgrade_set_timeout();

            if ($userid && $userid==$attempt->userid && (($sametitle && $sametitle==$attempt->sametitle) OR ($quizid && $quizid==$attempt->quizid))) {
                // this is not the most recent, best attempt, so delete it

                // remove attempt
                $DB->delete_records('reader_attempts', array('id' => $attempt->id));
                $countdeleted++;

                if ($started_box==false) {
                    $started_box = true;
                    xmldb_reader_box_start('The following duplicate attempts were deleted');
                }
                $msg = "Deleted attempt ($attempt->percentgrade% ".($attempt->passed=='true' ? 'PASS' : 'FAIL').") ".
                       "by $attempt->firstname $attempt->lastname ".
                       "at $attempt->bookname ".
                       "($attempt->publisher - $attempt->level)";
                echo html_writer::tag('li', $msg);

                // make sure "uniqueid" is in fact unique in the "reader_deleted_attempts" table
                // if it isn't, there will be "non-unique key" errors from the database server
                $DB->delete_records('reader_deleted_attempts', array('uniqueid' => $attempt->uniqueid));

                // add attempt to "deleted_attempts" table
                unset($attempt->id);
                $DB->insert_record('reader_deleted_attempts', $attempt);
            }

            $userid = $attempt->userid;
            $quizid = $attempt->quizid;
            $sametitle = $attempt->sametitle;

            // update progress bar
            $bar->update($i, $i_max, $strupdating.": ($i/$i_max) $strdeleted: $countdeleted");
        }
        $rs->close();

        if ($started_box==true) {
            xmldb_reader_box_end();
        }
    }
}

/**
 * xmldb_reader_fix_duplicate_questions
 *
 * @param xxx $dbman (passed by reference)
 * @todo Finish documenting this function
 */
function xmldb_reader_fix_duplicate_questions(&$dbman) {
    global $CFG, $DB, $OUTPUT;

    $table = '';
    $started_box = false;

    $questiontables = array('match', 'multianswer', 'multichoice', 'ordering', 'multianswer', 'shortanswer', 'truefalse');
    foreach ($questiontables as $questiontable) {

        switch (true) {
            // Moodle >= 2.5
            case $dbman->table_exists('qtype_'.$questiontable.'_options'):
                $questiontable = 'qtype_'.$questiontable.'_options';
                $questionfield = 'questionid';
                break;

            // Moodle <= 2.4
            case $dbman->table_exists('question_'.$questiontable):
                $questiontable = 'question_'.$questiontable;
                $questionfield = 'question';
                break;

            // table does not exist - shouldn't happen !!
            default: continue;
        }

        $select  = $questionfield.', COUNT(*) AS countrecords';
        $from    = '{'.$questiontable.'}';
        $groupby = $questionfield.' HAVING COUNT(*) > 1';

        if ($duplicates = $DB->get_records_sql("SELECT $select FROM $from GROUP BY $groupby ")) {
            foreach ($duplicates as $duplicate) {

                // get duplicate records
                if ($records = $DB->get_records($questiontable, array($questionfield => $duplicate->$questionfield), 'id,'.$questionfield)) {

                    if ($started_box==false) {
                        $started_box = true;
                        xmldb_reader_box_start('The following duplicate question options were deleted');
                    }

                    if ($table=='' || $table != $questiontable) {
                        if ($table) {
                            echo html_writer::end_tag('ul');
                            echo html_writer::end_tag('li');
                        }
                        echo html_writer::start_tag('li')."TABLE: $questiontable";
                        echo html_writer::start_tag('ul');
                        $table = $questiontable;
                    }

                    $ids = array_keys($records);
                    $id = array_shift($ids); // keep the first one

                    $DB->delete_records_list($questiontable, 'id', $ids);
                    echo html_writer::tag('li', "question id=$id: ".count($ids)." duplicate(s) removed");
                }

                // remove duplicate answers from "question_answer" table
                $select  = $DB->sql_concat('question', "'_'", 'answer').' AS question_answer, COUNT(*) AS countrecords';
                $from    = '{question_answers}';
                $where   = 'question = ?';
                $params  = array($duplicate->$questionfield);
                $groupby = 'question, answer HAVING COUNT(*) > 1';

                if ($records = $DB->get_records_sql("SELECT $select FROM $from WHERE $where GROUP BY $groupby", $params)) {

                    $qtype = $DB->get_field('question', 'qtype', array('id' => $duplicate->$questionfield));
                    foreach ($records as $record) {

                        $strpos = strpos($record->question_answer, '_');
                        $questionid = substr($record->question_answer, 0, $strpos);
                        $answertext = substr($record->question_answer, $strpos + 1);
                        if ($answers = $DB->get_records_select('question_answers', 'question = ? AND answer = ?', array($questionid, $answertext))) {

                            $answerids = array_keys($answers);
                            $answerid = array_shift($answerids); // usually, we want to keep the first duplicate answer

                            switch ($qtype) {
                                case 'multichoice':
                                    if ($record = $DB->get_record('question_multichoice', array('question' => $questionid))) {
                                        $answers = explode(',', $record->answers);
                                        $answers = array_diff($answers, $answerids);
                                        $answers = implode(',', $answers);
                                        if ($answers != $record->answers) {
                                            $DB->set_field('question_multichoice', 'answers', $answers);
                                        }
                                    }
                                    break;

                                case 'ordering':
                                    // do nothing
                                    break;

                                case 'truefalse':
                                    if ($record = $DB->get_record('question_truefalse', array('question' => $questionid))) {
                                        $answerids = array_keys($answers);
                                        $answerids = array_diff($answerids, array($record->trueanswer, $record->falseanswer));
                                    }
                                    break;

                                case '': // shouldn't happen !!
                                    break;

                                default:
                                    echo "Oops - when removing duplicate question answers, we got an unrecognized question type: $qtype";
                                    die;
                            }

                            // delete the (remaining) duplicate answers
                            if (count($answerids)) {
                                $DB->delete_records_list('question_answers', 'id', $answerids);
                            }
                        }
                    }
                }
            }
        }
    }
    if ($started_box==true) {
        echo html_writer::end_tag('ul');
        echo html_writer::end_tag('li');
        xmldb_reader_box_end();
    }
}

/**
 * xmldb_reader_fix_multichoice_questions
 *
 * @todo Finish documenting this function
 */
function xmldb_reader_fix_multichoice_questions() {
    global $DB;

    // remove :MULTICHOICE: questions that have no correct answer
    $select = 'q1.id, q1.questiontext, q1.qtype, MIN(q2.id) AS badid';
    $from   = 'mdl_question q1 '.
              'RIGHT JOIN mdl_question q2 ON q1.id = q2.parent';
    $where  = 'q1.qtype = ? AND q2.qtype = ? '.
              'AND '.$DB->sql_like('q2.questiontext', '?'). // LIKE
              'AND '.$DB->sql_like('q2.questiontext', '?', false, false, true); // NOT LIKE
    $params = array('multianswer', 'multichoice', '%:MULTICHOICE:%', '%=%');
    $groupby = 'q2.parent';

    $started_box = false;
    if ($questions = $DB->get_records_sql("SELECT $select FROM $from WHERE $where GROUP BY $groupby", $params)) {
        foreach ($questions as $question) {
            $ids = array($question->id, $question->badid);
            if ($multianswer = $DB->get_record('question_multianswer', array('question' => $question->id))) {
                $DB->delete_records('question_multianswer', array('id' => $multianswer->id));
                $ids = array_merge($ids, explode(',', $multianswer->sequence));
                $ids = array_filter($ids); // remove blanks
                $ids = array_unique($ids); // remove duplicates
            }
            $DB->delete_records_list('question', 'id', $ids);
            $DB->delete_records_list('question', 'parent', $ids);
            $DB->delete_records_list('quiz_question_instances', 'question', $ids);
            $DB->delete_records_list('reader_question_instances', 'question', $ids);
            if ($started_box==false) {
                $started_box = true;
                xmldb_reader_box_start('The following multichoice question gaps had no correct answer and were removed');
            }
            echo '<li><b>'.strip_tags($question->questiontext).'</b><ul>';
            echo '<li>'.implode('</li><li>', $ids).'</li></ul></li>';
        }
    }
    if ($started_box) {
        xmldb_reader_box_end();
    }

    // locate parents for orphan ":MULTICHOICE:" questions
    $select = 'qtype = ? AND parent = ? AND '.$DB->sql_like('questiontext', '?');
    $params = array('multichoice', 0, '%:MULTICHOICE:%');

    $count = 0;
    $rs = false;

    if ($i_max = $DB->count_records_select('question', $select, $params)) {
        $rs = $DB->get_recordset_select('question', $select, $params, 'id', 'id, category, qtype, name, questiontext, timecreated');
    } else {
        $rs = false;
    }

    if ($rs) {

        $i = 0; // record counter
        $bar = new progress_bar('readerfixmultichoice', 500, true);
        $strupdating = 'Fixing Reader multichoice questions'; // get_string('fixmultichoice', 'reader');

        $started_box = false;

        // loop through questions
        foreach ($rs as $question) {
            $i++; // increment record count

            // apply for more script execution time (3 mins)
            upgrade_set_timeout();

            if ($DB->sql_regex_supported()) {
                $select = 'sequence '.$DB->sql_regex().' ?';
                $params = array('(^|,)'.$question->id.'(,|$)');
            } else {
                $select = array('sequence = ?',
                                $DB->sql_like('sequence', '?', false, false),  // start
                                $DB->sql_like('sequence', '?', false, false),  // middle
                                $DB->sql_like('sequence', '?', false, false)); // end
                $select = '('.implode(' OR ', $select).')';
                $params = array("$question->id", "$question->id,%", "%,$question->id,%", "%,$question->id");
            }

            if ($started_box==false) {
                $started_box = true;
                xmldb_reader_box_start('The following multichoice questions were fixed');
            }

            if ($multianswer_options = $DB->get_records_select('question_multianswer', $select, $params, 'question', 'id,question')) {
                $multianswer_option = reset($multianswer_options);
                $parentquestionid = $multianswer_option->question;
            } else {
                // get potential parent records
                $select = 'parent = ? AND qtype = ? AND name = ? AND timecreated = ?';
                $params = array(0, 'multianswer', $question->name, $question->timecreated);
                if ($parentquestions = $DB->get_records_select('question', $select, $params, 'timecreated, id')) {
                    $parentquestionid = 0;
                    foreach ($parentquestions as $parentquestion) {
                        switch (true) {
                            case ($parentquestionid==0):
                                // FIRST record (our #3 choice)

                            case ($parentquestion->id > $question->id && $parentquestion->id < $parentquestionid):
                                // LOWEST id ABOVE question id (our #2 choice)

                            case ($parentquestion->id < $question->id && ($parentquestion->id > $parentquestionid || $parentquestionid > $question->id)):
                                // HIGHEST id BELOW question id (our #1 choice)

                                $parentquestionid = $parentquestion->id;
                                break;
                        }
                    }

                    // move preferred parent to start of $parentquestions array
                    $parentquestion = $parentquestions[$parentquestionid];
                    unset($parentquestions[$parentquestionid]);
                    $parentquestions = array($parentquestionid => $parentquestion) + $parentquestions;

                    $parentquestionid = 0;
                    foreach ($parentquestions as $parentquestion) {

                        $count_answers = 0; // the number of answers required by this parent question
                        if (! preg_match('/\{\#[0-9]+\}/', $parentquestion->questiontext, $matches)) {
                            continue; // shouldn't happen !!
                        }
                        $count_answers = count($matches);

                        // get/create the multichoice options record for this parent question
                        //     there should only be one such record,
                        //     by just in case, we allow for duplicates
                        if ($multianswer_options = $DB->get_records('question_multianswer', array('question' => $parentquestion->id))) {
                            // do nothing
                        } else {
                            // add new question_multianswer record
                            $multianswer_option = (object)array(
                                'question' => $parentquestion->id,
                                'sequence'  => '',
                            );
                            if (! $multianswer_option->id = $DB->insert_record('question_multianswer', $multianswer_option)) {
                                // could not add record - this shouldn't happen !!
                            }
                            $multianswer_options = array($multianswer_option->id => $multianswer_option);
                        }

                        foreach ($multianswer_options as $multianswer_option) {

                            $answerquestionids = explode(',', $multianswer_option->sequence);
                            $answerquestionids = array_filter($answerquestionids); // remove blanks

                            // remove ids of questions that don't exist
                            foreach ($answerquestionids as $a => $answerquestionid) {
                                if (! $DB->record_exists('question', array('id' => $answerquestionid))) {
                                    $answerquestionids[$a] = false; // invalid answer id
                                }
                            }
                            $answerquestionids = array_filter($answerquestionids); // remove blanks

                            // add this question as a valid answer for the parent question
                            if (count($answerquestionids) < $count_answers) {
                                // add this question to $multianswer_option->sequence
                                $answerquestionids[] = $question->id;
                                $multianswer_option->sequence = implode(',', $answerquestionids);
                                $DB->update_record('question_multianswer', $multianswer_option);
                                $parentquestionid = $parentquestion->id;
                                echo "<li>Add question (id = $question->id) as answer for multianswer parent question (id = $parentquestion->id)</li>";

                                // todo: make sure answers for this question are present
                                break;
                            }
                        }
                    }
                }
            }
            if ($parentquestionid) {
                $parentquestion = $DB->get_record('question', array('id' => $parentquestionid));
                $DB->set_field('question', 'parent', $parentquestionid, array('id' => $question->id));
                echo "<li>Set parent for question (id=$question->id): 0 =&gt; $parentquestionid $parentquestion->name</li>";
            } else {
                echo '<li><span style="color: red">OOPS</span> Could not locate parent for question: '.$question->id.'</li>';
            }

            // update progress bar
            $bar->update($i, $i_max, $strupdating.": ($i/$i_max)");
        }

        if ($started_box==true) {
            echo html_writer::end_tag('ul');
            echo html_writer::end_tag('li');
            xmldb_reader_box_end();
        }
    }

    // get all distinct quizids from books
    // foreach (quiz) check every question has an entry in the instances table
}

/**
 * xmldb_reader_box_start
 *
 * @param string $msg
 * @todo Finish documenting this function
 */
function xmldb_reader_box_start($msg) {
    global $OUTPUT;
    echo xmldb_reader_showhide_start_js();
    echo $OUTPUT->box_start('generalbox', 'notice');
    echo html_writer::start_tag('div');
    echo html_writer::tag('b', $msg).': '.xmldb_reader_showhide_img();
    echo html_writer::start_tag('ul');
}

/**
 * xmldb_reader_box_end
 *
 * @todo Finish documenting this function
 */
function xmldb_reader_box_end() {
    global $OUTPUT;
    echo html_writer::end_tag('ul');
    echo html_writer::end_tag('div');
    echo $OUTPUT->box_end();
    echo xmldb_reader_showhide_end_js();
}
