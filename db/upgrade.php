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
 * mod/reader/db/upgrade.php
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
 * xmldb_reader_upgrade
 *
 * @uses $CFG
 * @uses $DB
 * @uses $OUTPUT
 * @param xxx $oldversion
 * @return xxx
 * @todo Finish documenting this function
 */
function xmldb_reader_upgrade($oldversion) {
    global $CFG, $DB, $OUTPUT;
    $result = true;

    $dbman = $DB->get_manager();

    require_once($CFG->dirroot.'/mod/reader/db/upgradelib.php');

    $newversion = 2013033101;
    if ($result && $oldversion < $newversion) {

        // in "reader" table, add "introformat" field after "intro" field
        $table = new xmldb_table('reader');
        $field = new xmldb_field('introformat', XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '1', 'intro');

        // remove previous field, if it doesn't exist
        xmldb_reader_fix_previous_field($dbman, $table, $field);

        // add/update field
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        } else {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013033104;
    if ($result && $oldversion < $newversion) {

        // rename tables "reader_publisher" and "reader_individual_books"
        $tables = array('reader_publisher'=>'reader_books', 'reader_individual_books'=>'reader_book_instances');
        foreach ($tables as $oldname => $newname) {
            $oldname = new xmldb_table($oldname);
            if ($dbman->table_exists($oldname)) {
                if ($dbman->table_exists($newname)) {
                    $dbman->drop_table($oldname);
                } else {
                    $dbman->rename_table($oldname, $newname);
                }
            }
        }

        // rename "individualbooks" field in "reader" table
        $table = new xmldb_table('reader');
        $field = new xmldb_field('individualbooks', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, '0', 'wordsprogressbar');
        $newname = 'bookinstances';

        if ($dbman->field_exists($table, $field)) {
            xmldb_reader_fix_previous_field($dbman, $table, $field);
            $dbman->change_field_type($table, $field);
            if ($field->getName() != $newname) {
                $dbman->rename_field($table, $field, $newname);
            }
        }

        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013033105;
    if ($result && $oldversion < $newversion && false) {

        ////////////////////////////////////////////////////////
        // fix the "quizid" field in the "reader_attempts" table
        ////////////////////////////////////////////////////////
        // it currently contains an id from "reader_books"
        // so we create a new "bookid" field, copy "quizid",
        // then set correct "quizid", and remove "bookid"

        $table = new xmldb_table('reader_attempts');
        $field = new xmldb_field('bookid', XMLDB_TYPE_INTEGER, '11');
        $index = new xmldb_index('bookid_key', XMLDB_INDEX_NOTUNIQUE, array('bookid'));

        // add "bookid" field and index
        if (! $dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        if (! $dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // copy "bookid" to "quizid"
        $DB->execute('UPDATE {reader_attempts} SET bookid = quizid');
        $DB->execute('UPDATE {reader_attempts} SET quizid = 0');

        // transfer correct "quizid" from "reader_books" table
        // Note: syntax for UPDATE with JOIN depends on DB type
        switch ($DB->get_dbfamily()) {
            case 'mysql':
                $DB->execute('UPDATE {reader_attempts} ra JOIN {reader_books} rb ON ra.bookid = rb.id SET ra.quizid = rb.quizid');
                break;
            case 'mssql': // not tested
                $DB->execute('UPDATE ra SET quizid = rb.quizid FROM {reader_attempts} ra JOIN {reader_books} rb ON ra.bookid = rb.id');
                break;
            case 'oracle': // not tested
                $select = 'SELECT rb.quizid FROM {reader_books} rb WHERE ra.bookid = rb.id';
                $DB->execute('UPDATE {reader_attempts} ra SET ra.quizid = ('.$select.') AND EXISTS ('.$select.')');
                break;
            case 'postgres': // not tested
                $DB->execute('UPDATE {reader_attempts} ra SET quizid = rb.quizid FROM {reader_books} rb WHERE ra.bookid = rb.id');
                break;
            default:
                $DB->execute('UPDATE {reader_attempts} ra SET ra.quizid = (SELECT rb.quizid FROM {reader_books} rb WHERE ra.bookid = rb.id)');
        }

        // drop "bookid" index and field
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013033106;
    if ($result && $oldversion < $newversion) {
        xmldb_reader_check_stale_files();
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013033107;
    if ($result && $oldversion < $newversion) {
        // fix incorrectly set version of "readerview" block (it is one digit too long !)
        $badversion = '20120119101';
        $goodversion = '2012011910';
        if ($dbman->field_exists('block', 'version')) {
            // Moodle <= 2.5
            $select = 'name = ? AND version = ?';
            $params = array('readerview', $badversion);
            $DB->set_field_select('block', 'version', $goodversion, $select, $params);
        } else if ($dbman->table_exists('config_plugins')) {
            // Moodle >= 2.6
            $select = 'plugin = ? AND name = ? AND value = ?';
            $params = array('block_readerview', 'version', $badversion);
            $DB->set_field_select('config_plugins', 'value', $goodversion, $select, $params);
        }
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013040400;
    if ($result && $oldversion < $newversion) {
        xmldb_reader_move_images();
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013040900;
    if ($result && $oldversion < $newversion) {

        // remove backup_ids table
        $table = new xmldb_table('backup_ids');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // create reader_backup_ids table
        $table = new xmldb_table('reader_backup_ids');
        if (! $dbman->table_exists($table)) {
            $table->add_field('id',          XMLDB_TYPE_INTEGER, '10',     XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('backup_code', XMLDB_TYPE_INTEGER, '12',     XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
            $table->add_field('table_name',  XMLDB_TYPE_CHAR,    '30',     null,           XMLDB_NOTNULL);
            $table->add_field('old_id',      XMLDB_TYPE_INTEGER, '10',     XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
            $table->add_field('new_id',      XMLDB_TYPE_INTEGER, '10',     XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0');
            $table->add_field('info',        XMLDB_TYPE_TEXT,    'medium', null,           XMLDB_NOTNULL);

            // Add keys to table reader_backup_ids
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

            // Add indexes to table reader_backup_ids
            $table->add_index('readbackids_bactabold_uix', XMLDB_INDEX_UNIQUE, array('backup_code', 'table_name', 'old_id'));

            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013041701;
    if ($result && $oldversion < $newversion) {

        // unset all missing parent question ids
        // (the "parent" question is the old version of a question that was edited)

        $select = 'q1.id, q1.parent';
        $from   = '{question} q1 LEFT JOIN {question} q2 ON q1.parent = q2.id';
        $where  = 'q1.parent > 0 AND q2.id IS NULL';
        if ($questions = $DB->get_records_sql("SELECT $select FROM $from WHERE $where")) {
            list($select, $params) = $DB->get_in_or_equal(array_keys($questions));
            $DB->set_field_select('question', 'parent', 0, 'id '.$select, $params);
        }
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013041703;
    if ($result && $oldversion < $newversion) {

        $tables = array(
            // $tablename => $fields
            'reader' => array(
                // change name of "ignordate" field to "ignoredate"
                'ignoredate' => new xmldb_field('ignordate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'attemptsofday'),
            ),
            'reader_attempts' => array(
                // change name of "persent/percent" field to "percentgrade" and change type from CHAR to INTEGER
                'percentgrade' => array(
                    new xmldb_field('persent', XMLDB_TYPE_FLOAT, '6,2', null, XMLDB_NOTNULL, null, '0', 'sumgrades'),
                    new xmldb_field('percent', XMLDB_TYPE_FLOAT, '6,2', null, XMLDB_NOTNULL, null, '0', 'sumgrades')
                )
            ),
        );

        foreach ($tables as $tablename => $fields) {
            $table = new xmldb_table($tablename);
            foreach ($fields as $newfieldname => $newfields) {
                if (is_object($newfields)) {
                    $newfields = array($newfields);
                }
                foreach ($newfields as $field) {
                    if ($dbman->field_exists($table, $field)) {
                        xmldb_reader_fix_previous_field($dbman, $table, $field);
                        $type = $field->getType();
                        $default = $field->getDefault();
                        $oldfieldname = $field->getName();
                        if ($field->getNotNull()) {
                            $DB->set_field_select($tablename, $oldfieldname, $default, "$oldfieldname IS NULL");
                        }
                        if ($type==XMLDB_TYPE_INTEGER || $type==XMLDB_TYPE_FLOAT || $type==XMLDB_TYPE_NUMBER) {
                            $DB->set_field_select($tablename, $oldfieldname, $default, "$oldfieldname = ''");
                        }
                        $dbman->change_field_type($table, $field);
                        if ($oldfieldname != $newfieldname) {
                            $dbman->rename_field($table, $field, $newfieldname);
                        }
                    }
                }
            }
        }
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013042300;
    if ($result && $oldversion < $newversion) {

        // tidy all courses used to store reader quizzes
        $courseids = xmldb_reader_quiz_courseids();

        foreach ($courseids as $courseid) {
            $rebuild_course_cache = false;

            // move section summary to section name, if necessary
            if ($sections = $DB->get_records_select('course_sections', 'course = ? AND section > ?', array($courseid, 0))) {
                foreach ($sections as $section) {

                    $sectionname = trim(strip_tags($section->name));
                    $sectionsummary = trim(strip_tags($section->summary));

                    if ($sectionname=='') {
                        $sectionname = $sectionsummary;
                    }
                    if ($section->sequence=='') {
                        $sectionname = ''; // empty sections don't need a "name"
                    }

                    // update the section "name", if necessary
                    if ($sectionname != $section->name) {
                        $DB->set_field('course_sections', 'name', $sectionname, array('id' => $section->id));
                        $rebuild_course_cache = true;
                    }

                    // if section "name" is set, we can remove the contents of the "summary" field
                    if ($sectionname && ($sectionname==$sectionsummary || ($sectionsummary=='' && $section->summary))) {
                        $DB->set_field('course_sections', 'summary', '', array('id' => $section->id));
                        $rebuild_course_cache = true;
                    }
                }
            }

            if ($rebuild_course_cache) {
                rebuild_course_cache($courseid, true); // $clearonly must be set to true
            }
        }
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013051200;
    if ($result && $oldversion < $newversion) {

        // force all text fields to be long text - the default for Moodle 2.3+
        $tables = array(
            'reader' => array(
                new xmldb_field('intro',   XMLDB_TYPE_TEXT, 'long', null, XMLDB_NOTNULL),
                new xmldb_field('cheated', XMLDB_TYPE_TEXT, 'long', null, XMLDB_NOTNULL),
                new xmldb_field('not',     XMLDB_TYPE_TEXT, 'long', null, XMLDB_NOTNULL),
            ),
            'reader_attempts' => array(
                new xmldb_field('layout',  XMLDB_TYPE_TEXT, 'long', null, XMLDB_NOTNULL),
            ),
            'reader_backup_ids' => array(
                new xmldb_field('info',    XMLDB_TYPE_TEXT, 'long', null, XMLDB_NOTNULL),
            ),
            'reader_deleted_attempts' => array(
                new xmldb_field('layout',  XMLDB_TYPE_TEXT, 'long', null, XMLDB_NOTNULL),
            ),
            'reader_messages' => array(
                new xmldb_field('text',    XMLDB_TYPE_TEXT, 'long', null, XMLDB_NOTNULL),
            )
        );

        foreach ($tables as $tablename => $fields) {
            $table = new xmldb_table($tablename);
            foreach ($fields as $field) {
                if ($dbman->table_exists($table) && $dbman->field_exists($table, $field)) {
                    $fieldname = $field->getName();
                    $DB->set_field_select($tablename, $fieldname, '', "$fieldname IS NULL");
                    $dbman->change_field_type($table, $field);
                }
            }
        }

        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013052100;
    if ($result && $oldversion < $newversion) {

        $strupdating = 'Updating ordering questions for Reader module'; // get_string('fixordering', 'reader');

        $select = 'qa.question AS questionid, COUNT(*) AS countanswers, SUM(qa.fraction) AS sumanswers';
        $from   = '{question_answers} qa LEFT JOIN {question} q ON qa.question = q.id';
        $where  = 'q.qtype = ?';
        $params = array('ordering');

        // we expect the "fraction" field of the answers to contain each answer's order number (1, 2, 3, ...)
        // therefore if we total the fractions, we should get the Fibonacci sum for the number of answers
        // e.g. 2 answers -> 3, 3 answers -> 6, 4 answers -> 10, 5 answers -> 15
        // if "x" is the number of answers, then the Fibonacci sum can be calculated as (((x + 1) / 2) * x)
        $groupby = "qa.question HAVING (((COUNT(*) + 1) / 2) * COUNT(*)) <> SUM(qa.fraction)";

         // this might be faster, but doesn't catch all the wrongly ordered answers
        // $groupby = "qa.question HAVING COUNT(*) >= SUM(qa.fraction)";

        $sql = "SELECT $select FROM $from WHERE $where GROUP BY $groupby";
        if ($i_max = $DB->count_records_sql("SELECT COUNT(*) FROM ($sql) unorderedquestions", $params)) {
            $rs = $DB->get_recordset_sql($sql, $params);
        } else {
            $rs = false;
        }

        if ($rs) {
            $i = 0; // record counter
            $bar = new progress_bar('readerfixordering', 500, true);

            // loop through answer records
            foreach ($rs as $question) {
                $i++; // increment record count

                // apply for more script execution time (3 mins)
                upgrade_set_timeout();

                if ($answers = $DB->get_records('question_answers', array('question' => $question->questionid), 'id', 'id,fraction')) {

                    $fraction = 0;
                    foreach ($answers as $answer) {
                        $fraction++;
                        if ($fraction != $answer->fraction) {
                            $DB->set_field('question_answers', 'fraction', floatval($fraction), array('id' => $answer->id));
                        }
                    }
                }

                // update progress bar
                $bar->update($i, $i_max, $strupdating.": ($i/$i_max)");
            }
            $rs->close();
        }

        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }


    $newversion = 2013052900;
    if ($result && $oldversion < $newversion) {

        // get previously saved "keepoldquizzes" setting
        // (usually there won't be one, "get_config()" will return false)
        $keepoldquizzes = get_config('reader', 'keepoldquizzes');

        // if necessary, get default "keepoldquizzes" setting
        if ($keepoldquizzes===null || $keepoldquizzes===false || $keepoldquizzes==='') {
            if ($DB->record_exists_select('reader', 'id > ?', array(0))) {
                $keepoldquizzes = optional_param('keepoldquizzes', null, PARAM_INT);
            } else {
                $keepoldquizzes = 0; // disable on sites not using the Reader module
            }
        }

        // if this is not an interactive upgrade (i.e. a CLI upgrade) and
        // $keepoldquizzes is not set, then assume it is disabled and continue
        if ($keepoldquizzes===null || $keepoldquizzes===false || $keepoldquizzes==='') {
            if (xmldb_reader_interactive()==false) {
                $keepoldquizzes = 0;
            }
        }

        // if this is the first time to set "keepoldquizzes", then check with user
        if ($keepoldquizzes===null || $keepoldquizzes===false || $keepoldquizzes==='') {

            $message = get_string('upgradeoldquizzesinfo', 'reader');
            $message = format_text($message, FORMAT_MARKDOWN);

            $params = array(
                'confirmupgrade' => optional_param('confirmupgrade', 0, PARAM_INT),
                'confirmrelease' => optional_param('confirmrelease', 0, PARAM_INT),
                'confirmplugincheck' => optional_param('confirmplugincheck', 0, PARAM_INT),
            );

            $params['keepoldquizzes'] = 0;
            $no = new moodle_url('/admin/index.php', $params);

            $params['keepoldquizzes'] = 1;
            $yes = new moodle_url('/admin/index.php', $params);

            $buttons = $OUTPUT->single_button($no, get_string('no'), 'get').
                       $OUTPUT->single_button($yes, get_string('yes'), 'get');
            $buttons = html_writer::tag('div', $buttons, array('class' => 'buttons'));

            $output = '';
            $output .= $OUTPUT->heading(get_string('keepoldquizzes', 'reader'));
            $output .= $OUTPUT->box($message.$buttons, 'generalbox', 'notice');
            $output .= $OUTPUT->footer();

            echo $output;
            die;
        }

        // save this value of the 'keepoldquizzes' config setting
        set_config('keepoldquizzes', $keepoldquizzes, 'reader');

        // fix duplicate books and quizzes
        xmldb_reader_fix_duplicates();

        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013060800;
    if ($result && $oldversion < $newversion) {
        xmldb_reader_fix_nameless_books();
        xmldb_reader_fix_question_instances();
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013061400;
    if ($result && $oldversion < $newversion) {
        xmldb_reader_fix_slashes();
        xmldb_reader_fix_wrong_sectionnames();
        xmldb_reader_fix_duplicates();
        xmldb_reader_fix_wrong_quizids();
        xmldb_reader_fix_question_categories();
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013061500;
    if ($result && $oldversion < $newversion) {
        xmldb_reader_fix_duplicate_attempts();
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }


    $newversion = 2013061601;
    if ($result && $oldversion < $newversion) {

        // Define index "sametitle_key" (not unique) to be added to "reader_books" table
        $table = new xmldb_table('reader_books');
        $index = new xmldb_index('sametitle_key', XMLDB_INDEX_NOTUNIQUE, array('sametitle'));
        if (! $dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define index "genre_key" (not unique) to be added to "reader_books" table
        $table = new xmldb_table('reader_books');
        $index = new xmldb_index('genre_key', XMLDB_INDEX_NOTUNIQUE, array('genre'));
        if (! $dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Change precision of field "percentgrade" on table "reader_attempts" to (6, 2)
        $table = new xmldb_table('reader_attempts');
        $field = new xmldb_field('percentgrade', XMLDB_TYPE_NUMBER, '6, 2', null, null, null, null, 'sumgrades');
        xmldb_reader_fix_previous_field($dbman, $table, $field);
        $dbman->change_field_precision($table, $field);

        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013061801;
    if ($result && $oldversion < $newversion) {
        xmldb_reader_fix_multichoice_questions();
        xmldb_reader_fix_duplicate_questions($dbman);
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013062100;
    if ($result && $oldversion < $newversion) {
        xmldb_reader_fix_wrong_quizids();
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013062600;
    if ($result && $oldversion < $newversion) {
        xmldb_reader_fix_uniqueids($dbman);
        //xmldb_reader_fix_nonunique_quizids();
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013070300;
    if ($result && $oldversion < $newversion) {
        xmldb_reader_fix_multichoice_questions();
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013072300;
    if ($result && $oldversion < $newversion) {

        // add "time" field to "reader_noquiz" table
        $table = new xmldb_table('reader_noquiz');
        $field = new xmldb_field('time', XMLDB_TYPE_INTEGER, '11', null, null, null, null, 'maxtime');

        if (! $dbman->field_exists($table, $field)) {
            xmldb_reader_fix_previous_field($dbman, $table, $field);
            $dbman->add_field($table, $field);
        }

        // set "time" field to time of most recent update
        xmldb_reader_fix_book_times();

        // reader savepoint reached
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013080100;
    if ($result && $oldversion < $newversion) {
        update_capabilities('mod/reader');
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013101500;
    if ($result && $oldversion < $newversion) {
        xmldb_reader_fix_extrapoints();
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013121107;
    if ($result && $oldversion < $newversion) {
        $readercfg = get_config('reader');
        $vars = get_object_vars($readercfg);
        foreach ($vars as $oldname => $value) {
            if (substr($oldname, 0, 7)=='reader_') {
                unset_config($oldname, 'reader');
                $newname = substr($oldname, 7);
                if (isset($readercfg->$newname)) {
                    // do nothing
                } else {
                    set_config($newname, $value, 'reader');
                }
            }
        }
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013121209;
    if ($result && $oldversion < $newversion) {
        xmldb_reader_check_stale_files();
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    //$newversion = 2013xxxx00;
    //if ($result && $oldversion < $newversion) {
    //    xmldb_reader_merge_tables($dbman, 'reader_noquiz', 'reader_books');
    //    xmldb_reader_merge_tables($dbman, 'reader_deleted_attempts', 'reader_attempts');
    //}

    return $result;
}
