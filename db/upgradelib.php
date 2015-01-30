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
 * xmldb_reader_add_attempts_bookid
 *
 * @uses $DB
 * @param $dbman
 * @param $fixquizid
 * @todo Finish documenting this function
 */
function xmldb_reader_add_attempts_bookid($dbman, $fixquizid=false) {
    global $DB;

    //////////////////////////////////////////////////
    // fix the "quizid" field in the "reader_attempts"
    // and the "reader_deleted_attempts" tables
    //////////////////////////////////////////////////
    // it currently contains an "id" from "reader_books"
    // so we create a new "bookid" field, copy "quizid",
    // then set correct "quizid", and remove "bookid"

    // define reader attempts table
    $tablenames = array('reader_attempts', 'reader_deleted_attempts');
    foreach ($tablenames as $tablename) {
        $table = new xmldb_table($tablename);

        if (! $dbman->table_exists($table)) {
            continue; // shouldn't happen !!
        }

        // add/update quizid/bookid field and index
        $fieldnames = array('quizid', 'bookid');
        foreach ($fieldnames as $fieldname) {

            $field = new xmldb_field($fieldname, XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'userid');
            $index = new xmldb_index($fieldname.'_key', XMLDB_INDEX_NOTUNIQUE, array($fieldname));

            if ($dbman->index_exists($table, $index)) {
                $dbman->drop_index($table, $index);
            }

            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_type($table, $field);
            } else {
                $dbman->add_field($table, $field);
            }

            if (! $dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        // synchronize "quizid" and "bookid"

        // specify $join and $set fields
        if ($fixquizid) {
            // copy "quizid" to "bookid", then unset "quizid"
            $DB->execute('UPDATE {'.$tablename.'} SET bookid = quizid');
            $DB->execute('UPDATE {'.$tablename.'} SET quizid = 0');
            // transfer correct "quizid" from "reader_books" table
            $join = 'ra.bookid = rb.id';
            $set1 = 'quizid';
            if ($tablename=='reader_deleted_attempts') {
                $set2 = '0';
            } else {
                $set2 = 'rb.quizid';
            }
            $where = 'ra.bookid > 0';
        } else {
            // transfer "bookid" from "reader_books" table
            $join = 'ra.quizid = rb.quizid';
            $set1 = 'bookid';
            $set2 = 'rb.id';
            $where = 'ra.quizid > 0';
        }

        // Note: syntax for UPDATE with JOIN depends on DB type
        switch ($DB->get_dbfamily()) {
            case 'mysql':
                $DB->execute('UPDATE {'.$tablename.'} ra JOIN {reader_books} rb ON '.$join.' SET ra.'.$set1.' = '.$set2.' WHERE '.$where);
                break;
            case 'mssql': // not tested
                $DB->execute('UPDATE ra SET '.$set1.' = '.$set2.' FROM {'.$tablename.'} ra JOIN {reader_books} rb ON '.$join.' WHERE '.$where);
                break;
            case 'oracle': // not tested
                $select = 'SELECT '.$set2.' FROM {reader_books} rb WHERE '.$join.' AND '.$where;
                $DB->execute('UPDATE {'.$tablename.'} ra SET ra.'.$set1.' = ('.$select.') AND EXISTS ('.$select.')');
                break;
            case 'postgres': // not tested
                $DB->execute('UPDATE {'.$tablename.'} ra SET '.$set1.' = '.$set2.' FROM {reader_books} rb WHERE '.$join.' AND '.$where);
                break;
            default:
                $DB->execute('UPDATE {'.$tablename.'} ra SET ra.'.$set1.' = (SELECT '.$set2.' FROM {reader_books} rb WHERE '.$join.') WHERE '.$where);
        }
    }
}

/**
 * xmldb_reader_check_stale_files
 *
 * @uses $FULLME
 * @uses $OUTPUT
 * @todo Finish documenting this function
 */
function xmldb_reader_check_stale_files() {
    global $FULLME, $OUTPUT;

    $dirpath = dirname(dirname(__FILE__));
    $filenames = array(
        // moved to "js" folder
        'ajax.js', 'hide.js', 'jstimer.php', 'protect_js.php', 'quiz.js',
        // moved to "pix" folder
        'ajax-loader.gif', 'closed.gif', 'open.gif', 'pw.png',
        // moved to "quiz" folder
        'accessrules.php', 'attempt.php', 'attemptlib.php',
        'processattempt.php', 'startattempt.php', 'summary.php',
        // moved to "tools" folder
        'checkemail.php', 'fixslashesinnames.php',
        // replaced by "admin/books/download"
        'dlquizzes_form.php', 'dlquizzes_process.php',
        'dlquizzes.php', 'dlquizzesnoq.php',
        'updatecheck.php', 'lib',
        // replaced by "admin/books/download/renderer.php"
        'admin/books/download.php',
        // replaced by "admin/books/download/ajax.js.php"
        'admin/books/download.js.php',
        // replaced by "admin/users/setdelays"
        'admin/users/setdelay',
    );

    $stalefilenames = array();
    foreach ($filenames as $filename) {

        $filepath = $dirpath.'/'.$filename;
        $exists = file_exists($filepath);

        if ($exists && xmldb_reader_rm($filepath)) {
            $exists = false; // successfully deleted
        }

        if ($exists) {
            $stalefilenames[] = $filename;
        }
    }

    if (count($stalefilenames)) {
        // based on "upgrade_stale_php_files_page()" (in 'admin/renderer.php')

        $a = (object)array('dirpath'=>$dirpath, 'filelist'=>html_writer::alist($stalefilenames));
        $message = format_text(get_string('upgradestalefilesinfo', 'mod_reader', $a), FORMAT_MARKDOWN);

        $button = $OUTPUT->single_button($FULLME, get_string('reload'), 'get');
        $button = html_writer::tag('div', $button, array('class' => 'buttons'));

        $output = '';
        $output .= $OUTPUT->heading(get_string('upgradestalefiles', 'mod_reader'));
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
    $interactive = xmldb_reader_interactive();

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

                    if ($interactive) {
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
                    }

                    if ($mainquizid && $book->quizid && $mainquizid != $book->quizid) {
                        if ($interactive) {
                            echo "<li>fix references to duplicate quiz (quizid $book->quizid =&gt; $mainquizid)</li>";
                        }
                        xmldb_reader_fix_quiz_ids($mainquizid, $book->quizid);
                        if ($cm = $DB->get_record('course_modules', array('module' => $quizmoduleid, 'instance' => $book->quizid))) {
                            if ($keepoldquizzes) {
                                if ($cm->visible==1) {
                                    if ($interactive) {
                                        echo '<li>Hide duplicate quiz '."(course module id=$cm->id, quiz id=$cm->instance)".'</li>';
                                    }
                                    set_coursemodule_visible($cm->id, 0);
                                    $rebuild_course_cache = true;
                                }
                            } else {
                                if ($interactive) {
                                    echo '<li><span style="color: red;">DELETED</span> '."Duplicate quiz (course module id=$cm->id, quiz id=$book->quizid)".'</li>';
                                }
                                xmldb_reader_remove_coursemodule($cm->id);
                                $rebuild_course_cache = true;
                            }
                        }
                        $book->quizid = $mainquizid;
                    }

                    if ($mainbookid && $mainbookid != $book->id) {
                        // adjust all references to the duplicate book
                        if ($interactive) {
                            echo "<li>remove references to duplicate book</li>";
                        }
                        $DB->set_field('reader_book_instances', 'bookid', $mainbookid, array('bookid' => $book->id));

                        // now we can delete this book (because it is a duplicate)
                        if ($interactive) {
                            echo "<li>remove duplicate book</li>";
                        }
                        $DB->delete_records('reader_books', array('id' => $book->id));
                    }

                    if ($interactive) {
                        echo '</ul></li>';
                    }
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
    $interactive = xmldb_reader_interactive();

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
            if ($interactive) {
                echo '<li>Merging duplicate quizzes: '.$duplicate->quizname.'<ul>';
            }

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
                        if ($interactive) {
                            echo "<li>transferring quiz data (quiz id $cm->instance =&gt; $maincm->instance)</li>";
                        }
                        xmldb_reader_fix_quiz_ids($maincm->instance, $cm->instance);
                        // hide or delete the duplicate quiz
                        if ($keepoldquizzes) {
                            if ($cm->visible==1) {
                                if ($interactive) {
                                    echo '<li>Hide duplicate quiz '."(course module id=$cm->id, quiz id=$cm->instance)".'</li>';
                                }
                                set_coursemodule_visible($cm->id, 0);
                                $rebuild_course_cache = true;
                            }
                        } else {
                            if ($interactive) {
                                echo '<li><span style="color: red;">DELETED</span> '."Duplicate quiz (course module id=$cm->id, quiz id=$cm->instance)".'</li>';
                            }
                            xmldb_reader_remove_coursemodule($cm->id);
                            $rebuild_course_cache = true;
                        }
                    }
                }
            }
            if ($maincm && $maincm->visible==0) {
                if ($interactive) {
                    echo '<li>Make quiz visible '."(course module id=$maincm->id, quiz id=$maincm->instance)".'</li>';
                }
                set_coursemodule_visible($maincm->id, 1);
                $rebuild_course_cache = true;
            }
            if ($interactive) {
                echo '</ul></li>';
            }
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

    $dbman = $DB->get_manager();

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
        if ($dbman->table_exists($tablename)) {
            if ($newid==0) {
                $DB->delete_records($tablename, array($fieldname => $oldid));
            } else {
                $DB->set_field($tablename, $fieldname, $newid, array($fieldname => $oldid));
            }
        }
    }
}

/**
 * xmldb_reader_remove_coursemodule
 *
 * @param integer $cmid_or_instanceid
 * @param integer $modname (optional, default="")
 * @return xxx
 * @todo Finish documenting this function
 */
function xmldb_reader_remove_coursemodule($cmid_or_instanceid, $modname='') {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/course/lib.php');
    require_once($CFG->dirroot.'/mod/reader/lib.php');

    // get course module - with sectionnum :-)
    if ($modname) {
        if (! $cm = get_coursemodule_from_instance($modname, $cmid_or_instanceid, 0, true)) {
            throw new moodle_exception(get_string('invalidmodulename', 'error', "$modname (id=$cmid_or_instanceid)"));
        }
    } else {
        if (! $cm = get_coursemodule_from_id('', $cmid_or_instanceid, 0, true)) {
            throw new moodle_exception(get_string('invalidmoduleid', 'error', $cmid_or_instanceid));
        }
    }

    if (function_exists('course_delete_module')) {
        // Moodle >= 2.5
        course_delete_module($cm->id);
    } else {
        // Moodle <= 2.4
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
    }

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

    if ($courseid = get_config('mod_reader', 'reader_usecourse')) { // old config name
        $courseids[] = $courseid;
    } else if ($courseid = get_config('mod_reader', 'usecourse')) { // new config name
        $courseids[] = $courseid;
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
        $js .= "        for (var i=0; i<i_max; i++) {\n";
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

        $dbman = $DB->get_manager();
        if ($use_quiz_slots = $dbman->table_exists('quiz_slots')) {
            // Moodle >= 2.7
            $quiz_question_instances = 'quiz_slots';
            $quizfield     = 'quizid';
            $questionfield = 'questionid';
        } else {
            // Moodle <= 2.6
            $quiz_question_instances = 'quiz_question_instances';
            $quizfield     = 'quiz';
            $questionfield = 'question';
        }

        if (xmldb_reader_interactive()) {
            $bar = new progress_bar('readerfixinstances', 500, true);
        } else {
            $bar = false;
        }
        $strupdating = 'Checking Reader question instances'; // get_string('fixinstances', 'mod_reader');
        $i = 0; // record counter

        // loop through answer records
        foreach ($rs as $reader_question_instance) {
            $i++; // increment record count

            // apply for more script execution time (3 mins)
            upgrade_set_timeout();

            // TODO: check $reader_question_instance->quiz and $reader_question_instance->question is a valid combination
            $params = array($quizfield => $reader_question_instance->quiz, $questionfield => $reader_question_instance->question);
            if ($DB->record_exists($quiz_question_instances, $params)) {
                $params = array($questionfield => $reader_question_instance->question);
                if ($instances = $DB->get_records($quiz_question_instances, $params)) {
                    foreach ($instances as $instance) {

                        $select = "id = ? AND course $courseselect";
                        $params = array($instance->$quizfield);
                        $params = array_merge($params, $courseparams);

                        if ($DB->record_exists_select('quiz', $select, $params)) {
                            $DB->set_field('reader_question_instances', 'quiz', $instance->$quizfield, array('id' => $reader_question_instance->id));
                        }
                    }
                }
            }

            // update progress bar
            if ($bar) {
                $bar->update($i, $i_max, $strupdating.": ($i/$i_max)");
            }
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

    $quizmoduleid = 0;
    $interactive = xmldb_reader_interactive();

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

            if ($quizmoduleid==0) {
                $quizmoduleid = $DB->get_field('modules', 'id', array('name' => 'quiz'));
            }

            $cmids = explode(',', $section->sequence);
            $cmids = array_filter($cmids); // remove blanks

            $quizids = array();
            foreach ($cmids as $cmid) {
                if ($cm = $DB->get_record('course_modules', array('id' => $cmid))) {
                    if ($cm->module==$quizmoduleid) {
                        $quizids[] = $cm->instance;
                    }
                }
            }

            $sectionname = '';
            $sectionnames = array();
            if (count($quizids)) {
                list($select, $params) = $DB->get_in_or_equal($quizids);
                if ($books = $DB->get_records_select('reader_books', "quizid $select", $params)) {
                    foreach ($books as $book) {
                        $sectionname = $book->publisher;
                        if ($book->level=='' || $book->level=='--' || $book->level=='No Level') {
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
                    if ($interactive) {
                        if ($started_box==false) {
                            $started_box = true;
                            xmldb_reader_box_start('The following course sections were adjusted');
                        }
                    }
                    if ($count==1) {
                        if ($interactive) {
                            echo html_writer::tag('li', "Reset section name: $section->name => $sectionname");
                        }
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
                        if ($interactive) {
                            echo html_writer::start_tag('li');
                            echo 'Quizzes for books by multiple publishers / levels found in section: '.$section->name;
                            echo html_writer::alist($sectionnames);
                            echo html_writer::end_tag('li');
                        }
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

    $interactive = xmldb_reader_interactive();

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
              'AND rb.publisher <> ? '.
              'AND q.id  IS NOT NULL '.
              'AND cm.id IS NOT NULL '.
              'AND m.id  IS NOT NULL '.
              'AND cs.id IS NOT NULL '.
              'AND ('.$wrongquizname.' OR '.$wrongsectionname.')';
    $params = array('quiz', 'Extra Points', '', '--');
    $orderby = 'rb.publisher,rb.level,rb.name';

    // Note - you could store bookquizids as a config setting:
    // $bookquizids = get_config('mod_reader', 'bookquizids');
    // $bookquizids = unserialize($bookquizids);
    // set_config('bookquizids', serialize($bookquizids), 'mod_reader');
    // unset_config('bookquizids', 'mod_reader');

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
            if ($book->level=='' || $book->level=='--' || $book->level=='No Level') {
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

            // has user told us to use default quizid for each book found?
            $usedefaultquizid = optional_param('usedefaultquizid', 0, PARAM_INT);

            // has user told us which quiz to use for this book?
            $quizidparamname = 'bookquizid'.$book->id;
            $quizid = optional_param($quizidparamname, null, PARAM_INT);

            if (empty($quiz) && $quizid===null) {
                // offer form to select quizid
                $where = $DB->sql_like('q.name', '?').' AND cm.id IS NOT NULL AND cs.id IS NOT NULL AND m.id IS NOT NULL';
                $params = array('quiz', "$book->name%");
                if ($quizzes = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $orderby", $params)) {
                    // build select list (sectionname -> quiznames)

                    if ($interactive && ! $usedefaultquizid) {
                        // params for "select" button urls
                        $params = array(
                            'confirmupgrade' => optional_param('confirmupgrade', 0, PARAM_INT),
                            'confirmrelease' => optional_param('confirmrelease', 0, PARAM_INT),
                            'confirmplugincheck' => optional_param('confirmplugincheck', 0, PARAM_INT),
                        );

                        $table = new html_table();
                        $table->head = array(get_string('sectionname', 'mod_reader'),
                                             get_string('quizname', 'mod_reader'),
                                             get_string('select'));
                        $table->align = array('left', 'left', 'center');

                        // add candidate quizzes to the table
                        foreach ($quizzes as $quiz) {

                            // create button url with this quiz id
                            $params[$quizidparamname] = $quiz->id;
                            $url = new moodle_url('/admin/index.php', $params);
                            $button = $OUTPUT->single_button($url, get_string('selectthisquiz', 'mod_reader'), 'get');
                            $table->data[] = new html_table_row(array($quiz->sectionname, $quiz->name, $button));
                        }
                        unset($params[$quizidparamname]);

                        // create button to always use default quiz
                        $params['usedefaultquizid'] = 1;
                        $url = new moodle_url('/admin/index.php', $params);
                        $button = $OUTPUT->single_button($url, get_string('usedefaultquizid', 'mod_reader'), 'get');
                        $table->data[] = new html_table_row(array('', '', $button));

                        $message = get_string('fixwrongquizidinfo', 'mod_reader');
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
                        $output .= $OUTPUT->heading(get_string('fixwrongquizid', 'mod_reader', $params));
                        $output .= $OUTPUT->box($message, 'generalbox', 'notice');
                        $output .= $OUTPUT->footer();

                        echo $output;
                        die;
                    }

                    // get id of first quiz
                    $quizid = key($quizzes);
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

            if (count($msg) && $interactive) {
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
    // i.e. one that is not an id in the "question_usages" table
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
    global $CFG, $DB;
    require_once($CFG->dirroot.'/mod/reader/lib.php');

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

    $interactive = xmldb_reader_interactive();

    // fetch quiz record, if necessary
    if (empty($quizzes[$attempt->quizid])) {
        if (! $quizzes[$attempt->quizid] = $DB->get_record('quiz', array('id' => $attempt->quizid))) {
            // shouldn't happen - but we can continue if we create a dummy quiz record ...
            $quizzes[$attempt->quizid] = (object)array('id' => $attempt->quizid,
                                                       'name' => "Invalid quizid = $attempt->quizid",
                                                       'preferredbehaviour' => 'deferredfeedback');
        }
    }

    $dbman = $DB->get_manager();
    if ($dbman->table_exists('question_usages')) {
        // Moodle >= 2.1

        // cache readermoduleid
        if ($readermoduleid===null) {
            $readermoduleid = $DB->get_field('modules', 'id', array('name' => 'reader'));
        }

        $table = new xmldb_table('reader_attempts');
        if ($dbman->field_exists($table, 'readerid')) {
            $readerid = 'readerid'; // new name
        } else {
            $readerid = 'reader';   // old name
        }

        // fetch context, if necessary
        if (empty($contexts[$attempt->$readerid])) {
            if ($cm = $DB->get_record('course_modules', array('module' => $readermoduleid, 'instance' => $attempt->$readerid))) {
                $contexts[$attempt->$readerid] = reader_get_context(CONTEXT_MODULE, $cm->id);
            } else {
                // shouldn't happen - the reader has been deleted but the attempt remains ?
                // let's see if any other attempts at this reader have a valid uniqueids
                $select = 'ra.id, ra.uniqueid, qu.id AS questionusageid, qu.contextid, qu.preferredbehaviour';
                $from   = '{reader_attempts} ra LEFT JOIN {question_usages} qu ON ra.uniqueid = qu.id';
                $where  = 'ra.readerid = ? AND qu.id IS NOT NULL';
                $params = array($attempt->$readerid);
                if ($records = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params)) {
                    // we can get the contextid from the other "question_usage" records
                    $record = reset($records); // i.e. first record
                    $contexts[$attempt->$readerid] = (object)array('id' => $record->contextid);
                } else {
                    // otherwise use the system context - should never happen !!
                    $contexts[$attempt->$readerid] = reader_get_context(CONTEXT_SYSTEM);
                }
            }
        }

        // create question_usage record for this attempt
        $question_usage = (object)array(
            'contextid' => $contexts[$attempt->$readerid]->id,
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
    if ($interactive) {
        if ($started_box==false) {
            $started_box = true;
            echo xmldb_reader_box_start('The following reader attempts had their uniqueids fixed');
        }
        echo html_writer::tag('li', $quizzes[$attempt->quizid]->name.": OLD: $olduniqueid => NEW: $newuniqueid");
    }
}

/**
 * xmldb_reader_fix_nonunique_quizids
 *
 * @todo Finish documenting this function
 */
function xmldb_reader_fix_nonunique_quizids() {
    global $DB, $OUTPUT;

    $missingquizids = array();
    $interactive = xmldb_reader_interactive();

    // extract books with non-unique quizid
    $select = 'rb.*, q.name AS quizname, q.course AS quizcourseid';
    $from   = '{reader_books} rb LEFT JOIN {quiz} q ON rb.quizid = q.id';
    $where  = 'quizid IN (SELECT quizid FROM {reader_books} GROUP BY quizid HAVING COUNT(*) > 1)';
    $orderby = 'rb.quizid';

    if ($books = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", null, 'quizid')) {
        if ($interactive) {
            echo xmldb_reader_box_start('The following books have a non-unique quizid');
        }

        $quizid = 0;
        foreach ($books as $book) {

            // generate expected section name
            $sectionname = $book->publisher;
            if ($book->level=='' || $book->level=='--' || $book->level=='No Level') {
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
                    if ($interactive) {
                        echo "<li>CANNOT fix quizid for &quot;$book->name&quot; (book id=$book->id): quiz id=$book->quizid =&gt; $cm->instance</li>";
                    }
                } else {
                    if ($interactive) {
                        echo "<li>Fixing quizid in &quot;$book->name&quot; (book id=$book->id): quiz id=$book->quizid =&gt; $cm->instance</li>";
                    }
                    $DB->set_field('reader_books', 'quizid', $cm->instance, array('id' => $book->id));
                }
            } else {
                if ($interactive) {
                    echo '<li><span style="color:red;">ERROR</span> '."Missing quiz for &quot;$book->name&quot; (book id=$book->id): quiz id=$book->quizid</li>";
                }
                $missingquizids[$book->id] = $book->quizid;
            }
        }
        xmldb_reader_box_end();
    }

    $fixmissingquizzes = optional_param('fixmissingquizzes', null, PARAM_INT);
    if (count($missingquizids)) {

        // if this is not an interactive upgrade (i.e. a CLI upgrade)
        // then assume enable $fixmissingquizzes and continue
        if ($interactive==false) {
            $fixmissingquizzes = 1;
        }

        if ($fixmissingquizzes===null || $fixmissingquizzes===false || $fixmissingquizzes==='') {

            $message = get_string('fixmissingquizzesinfo', 'mod_reader');
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
            $output .= $OUTPUT->heading(get_string('fixmissingquizzes', 'mod_reader'));
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
    $readercfg = get_config('mod_reader');

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
                    'login' => $readercfg->serverusername,
                    'password' => $readercfg->serverpassword);
    $url = new moodle_url($readercfg->serverurl.'/', $params);

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
                    'login' => $readercfg->serverusername,
                    'password' => $readercfg->serverpassword);
    $url = new moodle_url($readercfg->serverurl.'/', $params);
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
        if ($level=='' || $level=='--' || $book->level=='No Level') {
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
        $url = new moodle_url($readercfg->serverurl.'/getfile.php', $params);
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
        if ($interactive) {
            echo $OUTPUT->box_start('generalbox', 'notice');
            echo html_writer::tag('h3', "$sectionname: $name");
        }
        restore_create_questions($restore, $tempfile);
        reader_xmldb_restore_questions($restore, $xml, $cm->instance);
        if ($interactive) {
            echo $OUTPUT->box_end();
        }

        xmldb_reader_rm($tempdir);
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
    global $CFG, $DB;
    require_once($CFG->dirroot.'/course/lib.php');

    if (file_exists($CFG->dirroot.'/lib/coursecatlib.php')) {
        require_once($CFG->dirroot.'/lib/coursecatlib.php');
    }

    // check the course has not already been created
    $coursename = 'Reader Quizzes';
    if ($targetcourse = $DB->get_records('course', array('shortname' => $coursename))) {
        return reset($targetcourse);
    }

    // disable warnings about upgrade running
    $upgraderunning = $CFG->upgraderunning;
    $CFG->upgraderunning = false;

    // get list of course categories
    if (class_exists('coursecat')) {
        $category_list = coursecat::make_categories_list();
    } else { // Moodle <= 2.4
        $category_list = array();
        $category_parents = array();
        make_categories_list($category_list, $category_parents);
    }

    // get the first valid $category_id
    $category_id = key($category_list);

    $targetcourse = (object)array(
        'category'      => $category_id, // crucial !!
        'fullname'      => $coursename,
        'shortname'     => $coursename,
        'name'          => $coursename,
        'summary'       => '',
        'summaryformat' => FORMAT_PLAIN, // plain text
        'format'        => 'topics',
        'newsitems'     => 0,
        'startdate'     => time(),
        'visible'       => 0, // hidden
        'numsections'   => $numsections
    );

    // create new course
    $targetcourse = create_course($targetcourse);

    // re-enable warnings about rebuild_course_cache
    $CFG->upgraderunning = $upgraderunning;

    if ($targetcourse) {
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
        $image_file_url = new moodle_url($readercfg->serverurl.'/getfile_quiz_image.php', $params);
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
    global $CFG, $DB;
    require_once($CFG->dirroot.'/mod/reader/lib.php');

    $summary = $DB->sql_compare_text('summary');
    $sequence = $DB->sql_compare_text('sequence');

    $select = 'course = ? AND (name = ? OR '.$summary.' = ?)';
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
                  ' AND (summary IS NULL OR '.$summary.' = ?)'.
                  ' AND (sequence IS NULL OR '.$sequence.' = ?)';
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

    $dbman = $DB->get_manager();
    if ($use_quiz_slots = $dbman->table_exists('quiz_slots')) {
        // Moodle >= 2.7
        $quiz_question_instances = 'quiz_slots';
        $quizfield     = 'quizid';
        $questionfield = 'questionid';
        $gradefield    = 'maxmark';
        $page = $DB->get_field('quiz_slots', 'page', array($quizfield, $quizid));
        $sort = $DB->get_field('quiz_slots', 'sort', array($quizfield, $quizid));
        $page = ($page ? $page : 1);
        $sort = ($sort ? $sort : 0) + 1;
    } else {
        // Moodle <= 2.6
        $quiz_question_instances = 'quiz_question_instances';
        $quizfield     = 'quiz';
        $questionfield = 'question';
        $gradefield    = 'grade';
    }

    // map old question id onto new question id
    $questionids = reader_xmldb_get_questionids($xml, $restore);

    // map old question id onto question grade
    $questiongrades = reader_xmldb_get_questiongrades($xml);

    $sumgrades = 0;
    foreach ($questionids as $oldid => $newid) {
        $question_instance = (object)array(
            $quizfield     => $quizid,
            $questionfield => $newid,
            $gradefield    => $questiongrades[$oldid],
        );
        if ($use_quiz_slots) {
            $question_instance->page = $page;
            $question_instance->sort = $sort++;
        }
        $sumgrades += $question_instance->$gradefield;
        $DB->insert_record($quiz_question_instances, $question_instance);
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

    $keepoldquizzes = get_config('mod_reader', 'keepoldquizzes');
    $courseids = xmldb_reader_quiz_courseids();
    $interactive = xmldb_reader_interactive();

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
                if ($interactive) {
                    echo html_writer::tag('div', "Re-building course cache: $course->shortname ... ", array('class' => 'notifysuccess'));
                }
                rebuild_course_cache($course->id, true); // $clearonly must be set to true
            }
        }
    }
}

/**
 * xmldb_reader_get_question_categories
 *
 * @todo Finish documenting thi function
 */
function xmldb_reader_get_question_categories() {
    global $CFG, $DB, $OUTPUT;
    require_once($CFG->dirroot.'/mod/reader/lib.php');

    // get contexts for quizzes in courses where Reader quizzes are stored
    $courseids = xmldb_reader_quiz_courseids();
    $select = array();
    $params = array();
    foreach ($courseids as $courseid) {
        if ($coursecontext  = reader_get_context(CONTEXT_COURSE, $courseid)) {
            array_push($select, '((contextlevel = ? AND path = ?) OR (contextlevel = ? AND '.$DB->sql_like('path', '?').'))');
            array_push($params, CONTEXT_COURSE, $coursecontext->path, CONTEXT_MODULE, $coursecontext->path.'/%');
        }
    }

    // check we found some contexts
    if (! $select = implode(' OR ', $select)) {
        return false; // no Reader quizzes - unusual ?!
    }

    // get reader course activity contexts
    if (! $modulecontexts = $DB->get_records_select('context', $select, $params)) {
        return false; // shouldn't happen !!
    }

    list($select, $params) = $DB->get_in_or_equal(array_keys($modulecontexts));
    if (! $categories = $DB->get_records_select('question_categories', 'contextid '.$select, $params)) {
        return false; // shouldn't happen !!
    }

    return $categories;
}

/**
 * xmldb_reader_fix_question_categories
 *
 * @todo Finish documenting thi function
 */
function xmldb_reader_fix_question_categories() {
    global $CFG, $DB, $OUTPUT;
    require_once($CFG->dirroot.'/mod/reader/lib.php');

    $interactive = xmldb_reader_interactive();

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
                if ($interactive) {
                    if ($started_box==false) {
                        $started_box = true;
                        echo xmldb_reader_box_start('The following reader question instances were fixed');
                    }
                    $msg = '<span style="color: red;">DELETE</span> '.count($instanceids).' duplicate question instance(s) (id IN '.implode(', ', $instanceids).')';
                    echo html_writer::tag('li', $msg);
                }
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

        if ($interactive) {
            echo xmldb_reader_box_start('The following reader questions were fixed');
            $msg = '<span style="color: brown;">RESET</span> parent ids on '.count($questions).' questions (id  IN '.implode(', ', array_keys($questions)).')';
            echo html_writer::tag('li', $msg);
        }

        list($select, $params) = $DB->get_in_or_equal(array_keys($questions));
        $DB->set_field_select('question', 'parent', 0, 'id '.$select, $params);

        if ($interactive) {
            xmldb_reader_box_end();
        }
    }

    // cache contextlevel for each context used in these categories
    $contextlevel = array();

    // get question categories for Reader course activities

    $started_box = false;
    if ($categories = xmldb_reader_get_question_categories()) {
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

            $move_to_quiz_context = false;
            if ($keep) {
                if (empty($contextlevel[$category->contextid])) {
                    $contextlevel[$category->contextid] = $DB->get_field('context', 'contextlevel', array('id' => $category->contextid));
                }
                if ($contextlevel[$category->contextid]==CONTEXT_COURSE) {
                    $move_to_quiz_context = true;
                }
            }

            if ($move_to_quiz_context) {
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
                if ($category->name=='ordering' || $category->name=='ORDERING' || $category->name=='ORDER') {
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

            if ($msg && $interactive) {
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

    $interactive = xmldb_reader_interactive();

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
        if ($interactive) {
            $bar = new progress_bar('readerfixordering', 500, true);
        } else {
            $bar = false;
        }
        $strupdating = 'Fixing duplicate Reader attempts'; // get_string('fixattempts', 'mod_reader');
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

                if ($interactive) {
                    if ($started_box==false) {
                        $started_box = true;
                        xmldb_reader_box_start('The following duplicate attempts were deleted');
                    }
                    $msg = "Deleted attempt ($attempt->percentgrade% ".($attempt->passed=='true' ? 'PASS' : 'FAIL').") ".
                           "by $attempt->firstname $attempt->lastname ".
                           "at $attempt->bookname ".
                           "($attempt->publisher - $attempt->level)";
                    echo html_writer::tag('li', $msg);
                }

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
            if ($bar) {
                $bar->update($i, $i_max, $strupdating.": ($i/$i_max) $strdeleted: $countdeleted");
            }
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
    $interactive = xmldb_reader_interactive();

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

                    if ($interactive) {
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
                    }

                    $ids = array_keys($records);
                    $id = array_shift($ids); // keep the first one

                    $DB->delete_records_list($questiontable, 'id', $ids);
                    if ($interactive) {
                        echo html_writer::tag('li', "question id=$id: ".count($ids)." duplicate(s) removed");
                    }
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

    $interactive = xmldb_reader_interactive();

    $dbman = $DB->get_manager();
    $use_quiz_slots = $dbman->table_exists('quiz_slots');

    // get categories for question used in Reader module quizzes
    if ($categories = xmldb_reader_get_question_categories()) {
        $started_box = false;
        foreach ($categories as $category) {

            // remove :MULTICHOICE: questions that have no correct answer
            // q1 is parent, q2 is (bad) child with no "=" in questiontext
            $select = 'q1.id, q1.questiontext, q1.qtype, MIN(q2.id) AS badid';
            $from   = '{question} q1 '.
                      'RIGHT JOIN {question} q2 ON q1.id = q2.parent';
            $where  = 'q1.category = ? '.
                      'AND q1.qtype = ? AND q2.qtype = ? '.
                      'AND '.$DB->sql_like('q2.questiontext', '?'). // LIKE
                      'AND '.$DB->sql_like('q2.questiontext', '?', false, false, true); // NOT LIKE
            $params = array($category->id, 'multianswer', 'multichoice', '%:MULTICHOICE:%', '%=%');
            $groupby = 'q2.parent';

            $started_category = false;
            if ($questions = $DB->get_records_sql("SELECT $select FROM $from WHERE $where GROUP BY $groupby", $params)) {
                $context = $DB->get_record('context',         array('id' => $category->contextid));
                $cm      = $DB->get_record('course_modules',  array('id' => $context->instanceid));
                $section = $DB->get_record('course_sections', array('id' => $cm->section));
                $quiz    = $DB->get_record('quiz',            array('id' => $cm->instance));
                foreach ($questions as $question) {

                    if ($interactive) {
                        if ($started_box==false) {
                            $started_box = true;
                            xmldb_reader_box_start('The following multichoice question gaps had no correct answer and were removed');
                        }
                        if ($started_category==false) {
                            $started_category = true;
                            echo '<li>';
                            echo '<b>Section:</b> '.strip_tags($section->name).'<br />';
                            echo '<b>Quiz:</b> '.strip_tags($quiz->name).'<br />';
                            echo '<b>Category:</b> '.strip_tags($category->name);
                            echo '<ul>';
                        }
                    }

                    $ids = array($question->id, $question->badid);
                    if ($multianswer = $DB->get_record('question_multianswer', array('question' => $question->id))) {
                        $sequence = explode(',', $multianswer->sequence);
                        $sequence = array_filter($sequence); // remove blanks
                        if (in_array($question->badid, $sequence)) {
                            $ids = array_merge($ids, $sequence);
                            $ids = array_unique($ids);
                        } else {
                            // $badid is not used in this multianswer, so we can delete the $badid only
                            $ids = array($question->badid);
                        }
                    }
                    $DB->delete_records_list('question', 'id', $ids);
                    $DB->delete_records_list('question', 'parent', $ids);
                    $DB->delete_records_list('question_multianswer', 'question', $ids);
                    if ($use_quiz_slots) {
                        $DB->delete_records_list('quiz_slots', 'questionid', $ids);
                    } else {
                        $DB->delete_records_list('quiz_question_instances', 'question', $ids);
                    }
                    $DB->delete_records_list('reader_question_instances', 'question', $ids);

                    // print these question ids
                    if ($interactive) {
                        if (count($ids) > 1) {
                            echo '<li>Delete whole question</li>';
                        }
                        echo '<li><b>'.strip_tags($question->questiontext).'</b> ('.$question->id.')<ul>';
                        echo '<li>'.implode('</li><li>', $ids).'</li></ul></li>';
                    }
                }
                unset($context, $cm, $section, $quiz, $ids, $sequence);
            }
            if ($started_category==true) {
                echo '</ul></li>';
            }
        }
        if ($started_box) {
            xmldb_reader_box_end();
        }
        unset($categories[$category->id]);
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

        if ($interactive) {
            $bar = new progress_bar('readerfixmultichoice', 500, true);
        } else {
            $bar = false;
        }
        $strupdating = 'Fixing Reader multichoice questions'; // get_string('fixmultichoice', 'mod_reader');
        $i = 0; // record counter

        $started_box = false;

        // loop through questions
        foreach ($rs as $question) {
            $i++; // increment record count

            // apply for more script execution time (3 mins)
            upgrade_set_timeout();

            // CAST sequence to CHAR so it can be compared
            $sequence = $DB->sql_compare_text('sequence');

            if ($DB->sql_regex_supported()) {
                $select = $sequence.' '.$DB->sql_regex().' ?';
                $params = array('(^|,)'.$question->id.'(,|$)');
            } else {
                $select = array($sequence.' = ?',
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
                $parentquestionid = 0;
                $select = 'parent = ? AND qtype = ? AND name = ? AND timecreated = ?';
                $params = array(0, 'multianswer', $question->name, $question->timecreated);
                if ($parentquestions = $DB->get_records_select('question', $select, $params, 'timecreated, id')) {
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
                                if ($interactive) {
                                    echo "<li>Add question (id = $question->id) as answer for multianswer parent question (id = $parentquestion->id)</li>";
                                }

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
                $msg = "<li>Set parent for question (id=$question->id): 0 =&gt; $parentquestionid $parentquestion->name</li>";
            } else {
                $msg = '<li><span style="color: red">OOPS</span> Could not locate parent for question: '.$question->id.'</li>';
            }

            // display $msg and update progress bar
            if ($bar) {
                echo $msg;
                $bar->update($i, $i_max, $strupdating.": ($i/$i_max)");
            }
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
 * xmldb_reader_fix_book_times
 *
 * @todo Finish documenting this function
 */
function xmldb_reader_fix_book_times() {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    $tablenames = array('reader_books', 'reader_noquiz');
    foreach ($tablenames as $tablename) {

        if (! $dbman->table_exists($tablename)) {
            continue;
        }
        if (! $books = $DB->get_records($tablename, array('time' => 0))) {
            continue;
        }

        // define image file(s) to search for
        $imagefiles = array();
        foreach ($books as $book) {
            $imagefiles[] = $book->image;
            if (substr($book->image, 0, 1)=='-') {
                // this image doesn't have the expected publisher code prefix
                // so we add an alternative "tidy" image file name
                $imagefiles[] = substr($book->image, 1);
            }
        }

        // set times, if possible
        foreach ($imagefiles as $imagefile) {
            $imagefile = $CFG->dataroot.'/reader/images/'.$imagefile;
            if (file_exists($imagefile)) {
                $DB->set_field($tablename, 'time', filemtime($imagefile), array('id' => $book->id));
            }
        }
    }
}

/**
 * xmldb_reader_fix_extrapoints
 *
 * @todo Finish documenting this function
 */
function xmldb_reader_fix_extrapoints() {
    global $DB;

    $interactive = xmldb_reader_interactive();

    // cache the timemodified
    $time = time();

    // define publisher name (also used as section name)
    $publisher = get_string('extrapoints', 'mod_reader');
    $level = '99';
    $old_publisher = 'Extra_Points';

    // cache quiz module id
    $quizmodule = $DB->get_record('modules', array('name' => 'quiz'));

    // get/create Reader quizzes course record
    $courseids = xmldb_reader_quiz_courseids();
    if ($courseid = array_shift($courseids)) {
        $course = $DB->get_record('course', array('id' => $courseid));
    } else {
        $course = reader_xmldb_get_targetcourse();
    }

    // reset legacy book publisher names and section names
    $tables = array('reader_books' => 'publisher', 'course_sections' => 'name');
    foreach ($tables as $table => $field) {
        $DB->set_field($table, $field, $publisher, array($field => $old_publisher));
    }

    // reset legacy point descriptions (upper/lower case difference is intentional)
    $oldnames = array('0.5 Points', 'One Point', 'Two points', 'Three points', 'Four points', 'Five points');
    foreach ($oldnames as $i => $oldname) {
        $newname = get_string('extrapoints'.$i, 'mod_reader');
        $DB->set_field('reader_books', 'name', $newname, array('name' => $oldname));
        $DB->set_field('quiz', 'name', $newname, array('course' => $course->id, 'name' => $oldname));
    }

    // assume we won't need to reset the course cache
    $rebuild_course_cache = false;

    // get / create course section (name = $publisher)
    $sectionnum = reader_xmldb_get_sectionnum($course, $publisher);

    $i_max = 5; // maximum number of extra points
    for ($i=0; $i<=$i_max; $i++) {

        // set quiz / book name
        $name = get_string('extrapoints'.$i, 'mod_reader');

        // get / create quiz's course_module record
        $select = 'cm.*, q.name AS quizname';
        $from   = '{course_modules} cm '.
                  'INNER JOIN {course_sections} cs ON cm.section  = cs.id '.
                  'INNER JOIN {quiz} q             ON cm.instance = q.id ';
        $where  = 'cm.course = ? AND cm.module = ? AND cs.section = ? AND q.name = ?';
        $params = array($course->id, $quizmodule->id, $sectionnum, $name);

        if ($cm = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params)) {
            // remove any (recent) duplicates
            $cmids = array_keys($cm);
            $i_max = count($cmids) - 1;
            for ($i=$i_max; $i>0; $i--) {
                xmldb_reader_remove_coursemodule($cmids[$i]);
                $rebuild_course_cache = true;
            }
            // get (oldest) quiz activity
            $cm = reset($cm);
        } else {
            $cm = reader_xmldb_get_newquiz($course->id, $sectionnum, $quizmodule, $name);
            $rebuild_course_cache = true;
        }

        // create new book
        $book = (object)array(
            'publisher'  => $publisher,
            'level'      => $level,
            'difficulty' => '99',
            'name'       => $name,
            'words'      => 1000 * pow(2, $i-1), // 500, 1000, 2000, 4000, 8000, ...
            'fiction'    => 'f',
            'quizid'     => $cm->instance,
            'image'      => ($i==0 ? '0.5' : "$i").($i==1 ? 'point' : 'points').'.jpg',
            'length'     => ($i==0 ? '0.5' : "$i.0"),
            'time'       => $time,
        );

        // should we add a token question too?
        // maybe just set quiz's "sumgrades" field?

        $params = array('publisher' => $publisher, 'level' => $level, 'name' => $name);
        if ($book->id = $DB->get_field('reader_books', 'id', $params)) {
            $DB->update_record('reader_books', $book);
        } else {
            $book->id = $DB->insert_record('reader_books', $book);
        }
    }

    if ($rebuild_course_cache) {
        if ($interactive) {
            echo html_writer::tag('div', "Re-building course cache: $course->shortname ... ", array('class' => 'notifysuccess'));
        }
        rebuild_course_cache($course->id, true); // $clearonly must be set to true
    }
}

/**
 * xmldb_reader_fix_orphans
 * remove records orphaned by deleted reader activities
 *
 * @todo Finish documenting this function
 */
function xmldb_reader_fix_orphans() {
    global $DB;
    $tables = array(
        array('reader_attempts',          'reader',    'reader', 'id'),
        array('reader_book_instances',    'readerid',  'reader', 'id'),
        array('reader_cheated_log',       'readerid',  'reader', 'id'),
        array('reader_delays',            'readerid',  'reader', 'id'),
        array('reader_grades',            'reader',    'reader', 'id'),
        array('reader_goals',             'readerid',  'reader', 'id'),
        array('reader_levels',            'readerid',  'reader', 'id'),
        array('reader_messages',          'readerid',  'reader', 'id'),
        array('reader_strict_users_list', 'readerid',  'reader', 'id'),
        array('reader_attempt_questions', 'attemptid', 'reader_attempts', 'id'),
    );
    foreach ($tables as $table) {
        list($table1, $field1, $table2, $field2) = $table;
        $select = 't1.id, t1.'.$field1;
        $from   = '{'.$table1.'} t1 LEFT JOIN {'.$table2.'} t2 ON t1.'.$field1.' = t2.'.$field2;
        $where  = 't2.'.$field2.' IS NULL';
        $order  = 't1.id';
        if ($records = $DB->get_records_sql("SELECT $select FROM $from WHERE $where ORDER BY $order")) {
            $DB->delete_records_list($table1, 'id',  array_keys($records));
        }
    }
}

/**
 * xmldb_reader_fix_slots
 *
 * @uses $CFG
 * @uses $DB
 * @todo Finish documenting this function
 */
function xmldb_reader_fix_slots() {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/mod/reader/lib.php');

    // check we have Moodle >= 2.2
    $dbman = $DB->get_manager();
    if ($dbman->field_exists('question_attempts', 'questionusageid')) {

        if ($quizids = $DB->get_records_select_menu('reader_books', null, null, 'quizid', 'id,quizid')) {
            $quizids = array_unique($quizids);
            $interactive = xmldb_reader_interactive();

            xmldb_reader_fix_slots_quizattempts($interactive, $quizids);
            xmldb_reader_fix_slots_readerattempts($interactive, $quizids);
        }
    }
}

/**
 * xmldb_reader_fix_slots_quizattempts
 *
 * @uses  $DB
 * @param boolean $interactive
 * @param array   $quizids
 */
function xmldb_reader_fix_slots_quizattempts($interactive, $quizids) {
    global $DB;

    // get attempts at these quizzes
    list($select, $params) = $DB->get_in_or_equal($quizids);
    if ($i_max = $DB->count_records_select('quiz_attempts', "quiz $select", $params)) {
        $rs = $DB->get_recordset_select('quiz_attempts', "quiz $select", $params, 'quiz');
    } else {
        $rs = false;
    }

    if ($rs) {
        $i = 0; // record counter
        if ($interactive) {
            $bar = new progress_bar('readerfixquizslots', 500, true);
        } else {
            $bar = false;
        }
        $strupdating = 'Fixing faulty question slots in Quiz attempts'; // get_string('fixattempts', 'mod_reader');

        // loop through attempts
        foreach ($rs as $attempt) {
            $i++; // increment record count

            // apply for more script execution time (3 mins)
            upgrade_set_timeout();

            if ($slots = $attempt->layout) {
                $slots = explode(',', $slots);
                $slots = array_filter($slots);
                $slots = array_unique($slots);
                list($select, $params) = $DB->get_in_or_equal($slots);
                $select = "questionusageid = ? AND slot $select";
                array_unshift($params, $attempt->uniqueid);
                $slots = $DB->get_records_select_menu('question_attempts', $select, $params, 'slot', 'id,slot');
            }
            if (empty($slots)) {
                // no valid slots - shoudln't happen !!
                $DB->delete_records('quiz_attempts', array('id' => $attempt->id));
            } else {
                $slots = implode(',0,', $slots).',0';
                if ($slots==$attempt->layout) {
                    // this is what we hope for and expect
                } else {
                    // update $attempt->layout in $DB
                    $DB->set_field('quiz_attempts', 'layout', $slots, array('id' => $attempt->id));
                }
            }
            // update progress bar
            if ($bar) {
                $bar->update($i, $i_max, $strupdating.": ($i/$i_max)");
            }
        }
        $rs->close();
    }
}

/**
 * xmldb_reader_fix_slots_readerattempts
 *
 * @uses  $DB
 * @param boolean $interactive
 * @param array   $quizids
 */
function xmldb_reader_fix_slots_readerattempts($interactive, $quizids) {
    global $DB;

    // get all attempts at questions in categories with an invalid contextid
    list($where, $params) = $DB->get_in_or_equal($quizids);
    $select = 'qa.id, ra.quizid, '.
              'q.id AS questionid, q.name AS questionname, '.
              'qc.id AS categoryid, qc.name AS categoryname, qc.contextid';
    $from   = '{question_attempts} qa '.
              'JOIN {reader_attempts} ra ON qa.questionusageid = ra.uniqueid '.
              'JOIN {question} q ON qa.questionid = q.id '.
              'JOIN {question_categories} qc ON q.category = qc.id '.
              'LEFT JOIN {context} ctx ON qc.contextid = ctx.id';
    $where  = 'ra.quizid '.$where.' AND ctx.id IS NULL';
    $order  = 'ra.quizid, qc.id, q.id';

    // get reader attempts at these quizzes
    if ($i_max = $DB->count_records_sql("SELECT COUNT(*) FROM $from WHERE $where", $params)) {
        $rs = $DB->get_recordset_sql("SELECT $select FROM $from WHERE $where ORDER BY $order", $params);
    } else {
        $rs = false;
    }

    if ($rs) {
        if ($interactive) {
            $bar = new progress_bar('readerfixquestioncategorycontexts', 500, true);
        } else {
            $bar = false;
        }
        $strupdating = 'Fixing faulty contexts in Quiz question categories'; // get_string('fixattempts', 'mod_reader');
        $i = 0; // record counter

        $cm = null;
        $quizid = 0;
        $questionid = 0;
        $categoryid = 0;
        $quizcontext = null;

        // loop through attempts
        foreach ($rs as $attempt) {
            $i++; // increment record count

            // apply for more script execution time (3 mins)
            upgrade_set_timeout();

            if ($quizid && $quizid==$attempt->quizid) {
                // same quiz - do nothing
            } else {
                // new quiz - get context
                $quizid = $attempt->quizid;
                if ($cm = get_coursemodule_from_instance('quiz', $quizid)) {
                    $quizcontext = reader_get_context(CONTEXT_MODULE, $cm->id);
                }
                if (empty($cm) || empty($quizcontext)) {
                    $cm = null;
                    $quizcontext = null;
                    $DB->delete_records('reader_attempts', array('quizid' => $quizid));
                    $DB->delete_records('quiz_question_instances', array('quiz' => $quizid));
                    $DB->delete_records('reader_question_instances', array('quiz' => $quizid));
                }
            }

            if ($cm && $quizcontext) {
                if ($questionid && $questionid==$attempt->questionid) {
                    // same question - do nothing
                } else {
                    // new question - try to fix id
                    $questionid = $attempt->questionid;
                    $select = 'q.id, q.category, q.name, q.qtype';
                    $from   = '{question} q JOIN {question_categories} qc ON q.category = qc.id';
                    $where  = 'q.id <> ? AND q.name = ? AND qc.id <> ? AND qc.name = ? AND qc.contextid = ?';
                    $params = array($attempt->questionid, $attempt->questionname,
                                    $attempt->categoryid, $attempt->categoryname, $quizcontext->id);
                    if ($newquestion = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params)) {
                        $newquestion = reset($newquestion); // should only be one !!
                        switch ($newquestion->qtype) {
                            case 'multianswer': xmldb_reader_fix_slots_multianswer($questionid, $newquestion->id); break;
                            case 'multichoice': xmldb_reader_fix_slots_multichoice($questionid, $newquestion->id); break;
                        }
                        $DB->set_field('question_attempts', 'questionid', $newquestion->id, array('questionid' => $questionid));
                        $DB->set_field('quiz_question_instances', 'question', $newquestion->id, array('question' => $questionid));
                        $DB->set_field('reader_question_instances', 'question', $newquestion->id, array('question' => $questionid));
                    } else if ($categoryid && $categoryid==$attempt->categoryid) {
                        // same category - do nothing
                    } else {
                        // new category - try to fix contextid
                        $categoryid = $attempt->categoryid;
                        $DB->set_field('question_categories', 'contextid', $quizcontext->id, array('id' => $categoryid));
                    }
                }
            }

            // update progress bar
            if ($bar) {
                $bar->update($i, $i_max, $strupdating.": ($i/$i_max)");
            }
        }
        $rs->close();
    }
}

/**
 * xmldb_reader_fix_slots_multianswer
 *
 * @uses  $DB
 * @param integer $oldquestionid
 * @param integer $newquestionid
 */
function xmldb_reader_fix_slots_multianswer($oldquestionid, $newquestionid) {
    global $DB;

    if (! $oldsequence = $DB->get_field('question_multianswer', 'sequence', array('question' => $oldquestionid))) {
        return false; // shouldn't happen
    }
    if (! $newsequence = $DB->get_field('question_multianswer', 'sequence', array('question' => $newquestionid))) {
        return false; // shouldn't happen
    }

    $oldsequence = explode(',', $oldsequence);
    $newsequence = explode(',', $newsequence);

    list($select, $params) = $DB->get_in_or_equal($oldsequence);
    if (! $oldsubquestions = $DB->get_records_select_menu('question', "id $select", $params, 'id', 'id,name')) {
        return false; // shouldn't happen
    }

    list($select, $params) = $DB->get_in_or_equal($newsequence);
    if (! $newsubquestions = $DB->get_records_select_menu('question', "id $select", $params, 'id', 'id,name')) {
        return false; // shouldn't happen
    }

    $i = 0;
    foreach ($oldsubquestions as $oldid => $oldname) {
        $i++;
        foreach ($newsubquestions as $newid => $newname) {
            if ($oldname==$newname) {
                // convert step data for subquestion $i
                $dataname = '_sub'.$i.'_order';
                $answerids = xmldb_reader_fix_slots_answerids($oldid, $newid);
                xmldb_reader_fix_slots_stepdata($oldquestionid, $dataname, $answerids);
                // remove $newid/$newname, to ensure it is only used once
                unset($newsubquestions[$newid]);
                break;
            }
        }
    }
}

/**
 * xmldb_reader_fix_slots_multichoice
 *
 * @uses  $DB
 * @param integer $oldquestionid
 * @param integer $newquestionid
 */
function xmldb_reader_fix_slots_multichoice($oldquestionid, $newquestionid) {
    $dataname = '_order';
    $answerids = xmldb_reader_fix_slots_answerids($oldquestionid, $newquestionid);
    xmldb_reader_fix_slots_stepdata($oldquestionid, $dataname, $answerids);
}

/**
 * xmldb_reader_fix_slots_answerids
 * map old answer ids onto new answer ids
 *
 * @uses  $DB
 * @param integer $oldquestionid
 * @param integer $newquestionid
 */
function xmldb_reader_fix_slots_answerids($oldquestionid, $newquestionid) {
    global $DB;
    $answerids = array();
    if ($oldanswers = $DB->get_records_menu('question_answers', array('question' => $oldquestionid), 'id', 'id,answer')) {
        if ($newanswers = $DB->get_records_menu('question_answers', array('question' => $newquestionid), 'id', 'id,answer')) {
            foreach ($oldanswers as $oldanswerid => $oldanswer) {
                foreach ($newanswers as $newanswerid => $newanswer) {
                    if ($oldanswer==$newanswer) {
                        $answerids[$oldanswerid] = $newanswerid;
                        unset($newanswers[$newanswerid]);
                        break;
                    }
                }
            }
        }
    }
    return $answerids;
}

/**
 * xmldb_reader_fix_slots_stepdata
 *
 * @uses  $DB
 * @param integer $questionid
 * @param string  $dataname
 * @param array   $answerids
 */
function xmldb_reader_fix_slots_stepdata($oldquestionid, $dataname, $answerids) {
    global $DB;

    // extract all data for steps in attempts at this question
    $select = 'qasd.id, qasd.attemptstepid, qasd.name, qasd.value, '.
              'qas.id AS qas_id, qas.questionattemptid, qas.sequencenumber, qas.state, qas.fraction, qas.userid, '.
              'qa.id AS qa_id, qa.questionusageid, qa.slot, qa.questionid';
    $from   = '{question_attempt_step_data} qasd '.
              'JOIN {question_attempt_steps} qas ON qasd.attemptstepid = qas.id '.
              'JOIN {question_attempts} qa ON qas.questionattemptid = qa.id';
    $where  = 'qa.questionid = ? AND qasd.name = ?';
    $params = array($oldquestionid, $dataname);
    if ($datas = $DB->get_records_sql("SELECT $select FROM $from WHERE $where", $params)) {
        foreach ($datas as $data) {
            $ids = explode(',', $data->value);
            foreach ($ids as $i => $id) {
                if (array_key_exists($id, $answerids)) {
                    $ids[$i] = $answerids[$id];
                } else {
                    $ids[$i] = 0;
                }
            }
            $ids = array_filter($ids);
            $ids = implode(',', $ids);
            if ($ids==$data->value) {
                // do nothing
            } else {
                $DB->set_field('question_attempt_step_data', 'value', $ids, array('id' => $data->id));
            }
        }
    }
}

/**
 * xmldb_reader_merge_tables
 *
 * @param object $dbman (passed by reference)
 * @param string $oldname
 * @param string $newname
 * @param array  $fields array($name => $value)
 * @param string $unique field name (optional)
 * @todo Finish documenting this function
 */
function xmldb_reader_merge_tables(&$dbman, $oldname, $newname, $fields, $unique='') {
    global $DB;

    $oldtable = new xmldb_table($oldname);
    $newtable = new xmldb_table($newname);
    if ($dbman->table_exists($oldtable) && $dbman->table_exists($newtable)) {

        if ($i_max = $DB->count_records_sql('SELECT COUNT(*) FROM {'.$oldname.'}')) {
            $rs = $DB->get_recordset_sql('SELECT * FROM {'.$oldname.'}');
        } else {
            $rs = false;
        }

        if ($rs) {
            $i = 0; // record counter
            if (xmldb_reader_interactive()) {
                $bar = new progress_bar('readermergetable'.$oldname, 500, true);
            } else {
                $bar = false;
            }
            $a = (object)array('new' => $newname, 'old' => $oldname);
            $strupdating = get_string('mergingtables', 'mod_reader', $a);

            // loop through answer records
            foreach ($rs as $record) {
                $i++; // increment record count

                foreach ($fields as $name => $value) {
                    $record->$name = $value;
                }

                // save the old id
                $id = $record->id;

                if ($unique=='' || empty($record->$unique) || ! $DB->record_exists($newname, array($unique => $record->$unique))) {
                    unset($record->id);
                    $record->id = $DB->insert_record($newname, $record);
                }

                // we can delete the old record now
                $DB->delete_records($oldname, array('id' => $id));

                // update progress bar
                if ($bar) {
                    $bar->update($i, $i_max, $strupdating.": ($i/$i_max)");
                }
            }
            $rs->close();
        }

        // now we can remove the old table
        $dbman->drop_table($oldtable);
    }
}

/**
 * xmldb_reader_box_end
 *
 * @todo Finish documenting this function
 */
function xmldb_reader_interactive() {
    if (defined('STDIN') && defined('CLI_SCRIPT') && CLI_SCRIPT) {
        // we could check $GLOBALS['interactive']
        // which is set in "admin/cli/upgrade.php"
        // but that assumes "non-interactive=false"
        // whereas we want to assume "non-interactive"
        //$options = array('non-interactive' => true);
        //list($options, $more) = cli_get_params($options);
        //return empty($options['non-interactive']);
        return false; // disable interactivity on CLI
    } else {
        return true; // assume browser-initiated update
    }
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

/**
 * xmldb_reader_move_images
 *
 * @todo Finish documenting this function
 */
function xmldb_reader_move_images() {
    global $CFG;

    // create "reader" folder within Moodle data folder
    make_upload_directory('reader');

    $courseids = xmldb_reader_quiz_courseids();
    if ($courseid = reset($courseids)) {

        $oldname = $CFG->dataroot."/$courseid/images";
        $newname = $CFG->dataroot.'/reader/images';

        // move "images" folder to new location
        if (file_exists($newname)) {
            // do nothing
        } else if (file_exists($oldname)) {
            @rename($oldname, $newname);
        }

        // remove old "images" folder (if necessary)
        if (file_exists($oldname)) {
            xmldb_reader_rm($oldname);
        }

        // remove old "script.txt" file (if necessary)
        $oldname = $CFG->dirroot.'/blocks/readerview/script.txt';
        if (file_exists($oldname)) {
            @unlink($oldname);
        }
    }
}

/**
 * xmldb_reader_rm
 *
 * @param string  $target
 * @todo Finish documenting this function
 */
function xmldb_reader_rm($target) {
    $ok = true;
    switch (true) {
        case empty($target):
            break;
        case is_link($target): // unusual !!
        case is_file($target):
            $ok = @unlink($target);
            break;
        case is_dir($target):
            $dir = dir($target);
            while(false !== ($item = $dir->read())) {
                if ($item=='.' || $item=='..') {
                    continue;
                }
                $ok = $ok && xmldb_reader_rm($target.DIRECTORY_SEPARATOR.$item);
            }
            $dir->close();
            $ok = $ok && @rmdir($target);
            break;
    }
    return $ok;

    // alternatively ...
    //if ($items = glob($target.'/*')) {
    //    foreach($items as $item) {
    //        switch (true) {
    //            case is_file($item): unlink($item); break;
    //            case is_dir($item): xmldb_reader_rm($item); break;
    //        }
    //    }
    //}
    //return rmdir($target);
}

/**
 * xmldb_reader_fix_config_names
 *
 * @param boolean  $fix_config
 * @todo Finish documenting this function
 */
function xmldb_reader_fix_config_names() {
    global $DB;
    $reader = get_config('reader');
    $reader = get_object_vars($reader);
    if ($mod_reader = get_config('mod_reader')) {
        $mod_reader = get_object_vars($mod_reader);
        foreach ($reader as $name => $value) {
            if (! array_key_exists($name, $mod_reader)) {
                set_config($name, $value, 'mod_reader');
            }
            unset_config($name, 'reader');
        }
    }
}

/**
 * xmldb_reader_migrate_logs
 *
 * @todo Finish documenting this function
 */
function xmldb_reader_migrate_logs($dbman) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/mod/reader/lib.php');

    if (function_exists('get_log_manager')) {
        $interactive = xmldb_reader_interactive();

        if ($loglegacy = get_config('loglegacy', 'logstore_legacy')) {
            set_config('loglegacy', 0, 'logstore_legacy');
        }

        $legacy_log_tablename = 'log';
        $legacy_log_table = new xmldb_table($legacy_log_tablename);

        $standard_log_tablename = 'logstore_standard_log';
        $standard_log_table = new xmldb_table($standard_log_tablename);

        if ($dbman->table_exists($legacy_log_table) && $dbman->table_exists($standard_log_table)) {

            $select = 'module = ?';
            $params = array('reader');

            if ($time = $DB->get_field($standard_log_tablename, 'MAX(timecreated)', array('component' => 'reader'))) {
                $select .= ' AND time > ?';
                $params[] = $time;
            } else if ($time = $DB->get_field($standard_log_tablename, 'MIN(timecreated)', array())) {
                $select .= ' AND time > ?';
                $params[] = $time;
            }

            if ($count = $DB->count_records_select($legacy_log_tablename, $select, $params)) {
                $rs = $DB->get_recordset_select($legacy_log_tablename, $select, $params);
            } else {
                $rs = false;
            }

            if ($rs) {
                if ($interactive) {
                    $i = 0;
                    $bar = new progress_bar('readermigratelogs', 500, true);
                }
                $strupdating = get_string('migratinglogs', 'mod_reader');
                foreach ($rs as $log) {
                    upgrade_set_timeout(); // 3 mins
                    reader_add_to_log($log->course,
                                      $log->module,
                                      $log->action,
                                      $log->url,
                                      $log->info,
                                      $log->cmid,
                                      $log->userid);
                    if ($interactive) {
                        $i++;
                        $bar->update($i, $count, $strupdating.": ($i/$count)");
                    }
                }
                $rs->close();
            }
        }

        // reset loglegacy config setting
        if ($loglegacy) {
            set_config('loglegacy', $loglegacy, 'logstore_legacy');
        }
    }
}

/*
 * reader_xmldb_drop_indexes
 *
 * @param  object $dbman
 * @param  object $table
 * @param  array  $indexes
 * @return void, but may add indexes
 */
function reader_xmldb_drop_indexes($dbman, $table, $indexes) {
    foreach ($indexes as $index => $fields) {
        foreach ($fields as $field) {
            if ($dbman->field_exists($table, $field)) {
                $index = new xmldb_index($index, XMLDB_INDEX_NOTUNIQUE, array($field));
                if ($dbman->index_exists($table, $index)) {
                    $dbman->drop_index($table, $index);
                }
            }
        }
    }
}

/*
 * reader_xmldb_add_indexes
 *
 * @param  object $dbman
 * @param  object $table
 * @param  array  $indexes
 * @return void, but may add indexes
 */
function reader_xmldb_add_indexes($dbman, $table, $indexes) {
    foreach ($indexes as $index => $fields) {
        foreach ($fields as $field) {
            if ($dbman->field_exists($table, $field)) {
                $index = new xmldb_index($index, XMLDB_INDEX_NOTUNIQUE, array($field));
                if (! $dbman->index_exists($table, $index)) {
                    $dbman->add_index($table, $index);
                }
            }
        }
    }
}

/*
 * reader_xmldb_update_field
 *
 * @param  object $dbman
 * @param  object $table
 * @param  array  $fields
 * @param  array  $indexes (optional, default=array())
 * @return void, but may add indexes
 */
function reader_xmldb_update_fields($dbman, $table, $fields, $indexes=array()) {

    // if necessary, remove indexes on fields to be updated
    reader_xmldb_drop_indexes($dbman, $table, $indexes);

    foreach ($fields as $newname => $field) {

        $oldexists = $dbman->field_exists($table, $field);
        $newexists = $dbman->field_exists($table, $newname);

        if ($field->getName()==$newname) {
            // same field name - do nothing
        } else {
            // different field names
            if ($oldexists) {
                if ($newexists) {
                    $dbman->drop_field($table, $field);
                    $oldexists = false;
                } else {
                    $dbman->rename_field($table, $field, $newname);
                    $newexists = true;
                }
            }
            $field->setName($newname);
        }
        xmldb_reader_fix_previous_field($dbman, $table, $field);
        if ($newexists) {
            $dbman->change_field_type($table, $field);
        } else {
            $dbman->add_field($table, $field);
        }
    }

    // if necessary, restore indexes on updated fields
    reader_xmldb_add_indexes($dbman, $table, $indexes);
}

/**
 * xmldb_reader_fix_sumgrades
 *
 * @todo Finish documenting this function
 */
function xmldb_reader_fix_sumgrades($dbman) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/mod/reader/lib.php');

    $interactive = xmldb_reader_interactive();

    $sql = "SELECT quizid, layout FROM {reader_attempts} GROUP BY quizid, layout";
    if ($count = $DB->count_records_sql("SELECT COUNT(*) FROM ($sql) temptable", array())) {
        $rs = $DB->get_recordset_sql($sql, array());
    } else {
        $rs = false;
    }

    if ($rs) {

        if ($interactive) {
            $i = 0;
            $bar = new progress_bar('fixsumgrades', 500, true);
        }
        $use_quiz_slots = $dbman->table_exists('quiz_slots');
        $strupdating = get_string('fixingsumgrades', 'mod_reader');
        $sql_compare_text_layout = $DB->sql_compare_text('layout');

        $quiz = null;
        $readerids = array();
        foreach ($rs as $quizidlayout) {
            upgrade_set_timeout(); // 3 mins

            $quizid = $quizidlayout->quizid;
            $layout = $quizidlayout->layout;

            if ($quiz===null || $quiz===false || $quiz->id != $quizid) {
                $quiz = $DB->get_record('quiz', array('id' => $quizid));
                if (isset($quiz->questions)) {
                    $quiz->questions = explode(',', $quiz->questions);
                    $quiz->questions = array_filter($quiz->questions);
                    $quiz->questions = array_values($quiz->questions);
                }
            }

            // get slots in this quiz layout
            $slots = explode(',', $layout);
            $slots = array_filter($slots);
            $slots = array_values($slots);

            // convert slots to question ids (Moodle <= 2.6)
            if (isset($quiz->questions)) {
                foreach ($slots as $i => $slot) {
                    if (array_key_exists($slot, $quiz->questions)) {
                        $slots[$i] = $quiz->questions[$slot];
                    } else {
                        $slots[$i] = false;
                    }
                }
                $slots = array_filter($slots);
                $slots = array_values($slots);
            }

            // sanity check on slots
            if (empty($slots)) {
                $select = 'quizid = ? AND '.$sql_compare_text_layout.' = ?';
                $DB->delete_records_select('reader_attempts', $select, array($quizid, $layout));
                $sumgrades = 0;
            } else {
                // calculate actual sumgrades value for these slots / questions
                if ($use_quiz_slots) {
                    // Moodle >= 2.7
                    $table = 'quiz_slots';
                    $field = 'SUM(maxmark)';
                    list($select, $params) = $DB->get_in_or_equal($slots);
                    $select = "slot $select AND quizid = ?";
                    $params[] = $quizid;
                } else {
                    // Moodle <= 2.6
                    $table = 'quiz_question_instances';
                    $field = 'SUM(grade)';
                    list($select, $params) = $DB->get_in_or_equal($slots);
                    $select = "question $select AND quiz = ?";
                    $params[] = $quizid;
                }
                $sumgrades = $DB->get_field_select($table, $field, $select, $params);
            }

            // force sumgrades value (cannot be null)
            $sumgrades = ($sumgrades ? $sumgrades : 0);
            $DB->set_field('quiz', 'sumgrades', $sumgrades, array('id' => $quizid));

            // sanity check on sumgrades
            if ($sumgrades==0) {
                echo "Remove attempts for $quiz->name (id=$quiz->id)<br />";
                $select = 'quizid = ? AND '.$sql_compare_text_layout.' = ?';
                $DB->delete_records_select('reader_attempts', $select, array($quizid, $layout));
            } else {
                // fix attempts with incorrect sumgrades
                $select = 'quizid = ? AND '.$sql_compare_text_layout.' = ? AND ROUND(sumgrades / percentgrade * 100) <> ?';
                $params = array($quizid, $layout, $sumgrades);
                if ($attempts = $DB->get_records_select('reader_attempts', $select, $params)) {
                    foreach ($attempts as $attempt) {
                        $readerids[$attempt->readerid] = true;
                        $percentgrade = round($attempt->sumgrades / $sumgrades * 100);
                        $DB->set_field('reader_attempts', 'percentgrade', $percentgrade, array('id' => $attempt->id));
                    }
                }
            }

            if ($interactive) {
                $i++;
                $bar->update($i, $count, $strupdating.": ($i/$count)");
            }
        }
        $rs->close();

        foreach ($readerids as $readerid) {
            $reader = $DB->get_record('reader', array('id' => $readerid));
            reader_update_grades($reader);
        }
    }
}

