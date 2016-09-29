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
defined('MOODLE_INTERNAL') || die();

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
    require_once($CFG->dirroot.'/mod/reader/db/upgradelib.php');

    $result = true;

    // cache the plugin name, as it is used often
    $plugin = 'mod_reader';

    $dbman = $DB->get_manager();

    $interactive = xmldb_reader_interactive();

    // fix config names
    if ($oldversion <= 2014070487) {
        xmldb_reader_fix_config_names();
    }

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

        // rename tables (OLD => NEW)
        xmldb_reader_rename_table($dbman, 'reader_publisher', 'reader_books');
        xmldb_reader_rename_table($dbman, 'reader_individual_books', 'reader_book_instances');

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
    if ($result && $oldversion < $newversion) {
        xmldb_reader_add_attempts_bookid($dbman, true);
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
                new xmldb_field('intro', XMLDB_TYPE_TEXT, 'long', null, XMLDB_NOTNULL),
                new xmldb_field('cheated_message', XMLDB_TYPE_TEXT, 'long', null, XMLDB_NOTNULL),
                new xmldb_field('not_cheated_message', XMLDB_TYPE_TEXT, 'long', null, XMLDB_NOTNULL),
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
        if ($i_max = $DB->count_records_sql("SELECT COUNT(*) FROM ($sql) temptable", $params)) {
            $rs = $DB->get_recordset_sql($sql, $params);
        } else {
            $rs = false;
        }

        if ($rs) {
            $strupdating = get_string('fixordering', $plugin);
            if ($interactive) {
                $bar = new progress_bar('readerfixordering', 500, true);
            } else {
                $bar = false;
            }
            $i = 0; // record counter

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
                if ($bar) {
                    $bar->update($i, $i_max, $strupdating.": ($i/$i_max)");
                }
            }
            $rs->close();
        }

        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }


    $newversion = 2013052900;
    if ($result && $oldversion < $newversion) {

        // get previously saved "keepoldquizzes" setting
        // (usually there won't be one, "get_config()" will return false)
        $keepoldquizzes = get_config($plugin, 'keepoldquizzes');

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

            $message = get_string('upgradeoldquizzesinfo', $plugin);
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
            $output .= $OUTPUT->heading(get_string('keepoldquizzes', $plugin));
            $output .= $OUTPUT->box($message.$buttons, 'generalbox', 'notice');
            $output .= $OUTPUT->footer();

            echo $output;
            die;
        }

        // save this value of the 'keepoldquizzes' config setting
        set_config('keepoldquizzes', $keepoldquizzes, $plugin);

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

        if ($dbman->table_exists($table)) {
            if (! $dbman->field_exists($table, $field)) {
                xmldb_reader_fix_previous_field($dbman, $table, $field);
                $dbman->add_field($table, $field);
            }
        }

        // set "time" field to time of most recent update
        xmldb_reader_fix_book_times();

        // reader savepoint reached
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013101500;
    if ($result && $oldversion < $newversion) {
        xmldb_reader_fix_extrapoints();
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013121107;
    if ($result && $oldversion < $newversion) {
        $readercfg = get_config($plugin);
        $vars = get_object_vars($readercfg);
        foreach ($vars as $oldname => $value) {
            if (substr($oldname, 0, 7)=='reader_') {
                unset_config($oldname, $plugin);
                $newname = substr($oldname, 7);
                if (isset($readercfg->$newname)) {
                    // do nothing
                } else {
                    set_config($newname, $value, $plugin);
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

    $newversion = 2014032045;
    if ($result && $oldversion < $newversion) {

        // remove reader_backup_ids table
        $table = new xmldb_table('reader_backup_ids');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        // add "bookid" field to "reader_attempts" table
        // add "bookid" field to "reader_deleted_attempts" table
        xmldb_reader_add_attempts_bookid($dbman);

        // add "deleted" field to "reader_attempts" table
        $table = new xmldb_table('reader_attempts');
        $field = new xmldb_field('deleted', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'attempt');
        if (! $dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // merge "reader_noquiz" into "reader_books" tables
        xmldb_reader_merge_tables($dbman, 'reader_noquiz', 'reader_books', array('quizid' => 0));

        // merge "reader_deleted_attempts" and "reader_attempts" tables
        xmldb_reader_merge_tables($dbman, 'reader_deleted_attempts', 'reader_attempts', array('deleted' => 1), 'uniqueid');
    }

    $newversion = 2014032646;
    if ($result && $oldversion < $newversion) {

        // rename table "reader_forcedtimedelay" and "reader_goal"
        // rename "changedate" field and add index on "readerid"
        $tablenames = array('reader_forcedtimedelay'=>'reader_delays', 'reader_goal'=>'reader_goals');
        foreach ($tablenames as $oldtablename => $newtablename) {

            // rename table
            xmldb_reader_rename_table($dbman, $oldtablename, $newtablename);

            // rename "changedate" field to "timemodified"
            $table = new xmldb_table($newtablename);
            $field = new xmldb_field('changedate', XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED);
            $newfieldname = 'timemodified';
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_type($table, $field);
                $dbman->rename_field($table, $field, $newfieldname);
            }

            // add non-unique index "readerid_key"
            $index = new xmldb_index('readerid_key', XMLDB_INDEX_NOTUNIQUE, array('readerid'));
            if (! $dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }
    }

    $newversion = 2014033048;
    if ($result && $oldversion < $newversion) {
        xmldb_reader_check_stale_files();
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2014040250;
    if ($result && $oldversion < $newversion) {
        // rename fields in "reader_messages" table
        $table = new xmldb_table('reader_messages');
        $fields = array(
            // $newfieldname => $field (old name)
            'readerid'   => new xmldb_field('instance',   XMLDB_TYPE_INTEGER, '11',   null, XMLDB_NOTNULL),
            'message'    => new xmldb_field('text',       XMLDB_TYPE_TEXT,    'long', null, XMLDB_NOTNULL),
            'groupids'   => new xmldb_field('users',      XMLDB_TYPE_CHAR,    '255',  null, XMLDB_NOTNULL),
            'timefinish' => new xmldb_field('timebefore', XMLDB_TYPE_INTEGER, '11',   null, XMLDB_NOTNULL)
        );
        foreach ($fields as $newfieldname => $field) {
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_type($table, $field);
                $dbman->rename_field($table, $field, $newfieldname);
            }
        }

        // add non-unique indexes on "readerid" and "timefinish" fields
        $indexes = array(
            new xmldb_index('readerid_key', XMLDB_INDEX_NOTUNIQUE, array('readerid')),
            new xmldb_index('timefinish_key', XMLDB_INDEX_NOTUNIQUE, array('timefinish')),
        );
        foreach ($indexes as $index) {
            if (! $dbman->index_exists($table, $index)) {
                $dbman->add_index($table, $index);
            }
        }

        // set "finishtime" to zero for indefinitely displayed messages
        $select = 'timefinish > ?';
        $params = array(time() + (1000 * 60 * 60));
        $DB->set_field_select('reader_messages', 'timefinish', 0, $select, $params);

        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2014040452;
    if ($result && $oldversion < $newversion) {

        // adjust fields in "reader_messages" table
        $table = new xmldb_table('reader_messages');

        // rename "message" field to "messageformat"
        $field = new xmldb_field('message', XMLDB_TYPE_TEXT, 'long', null, XMLDB_NOTNULL);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
            $dbman->rename_field($table, $field, 'messagetext');
        }

        // add "messageformat" field
        $field = new xmldb_field('messageformat', XMLDB_TYPE_INTEGER, '4', null, null, null, '1', 'message');
        if (! $dbman->field_exists($table, $field)) {
            xmldb_reader_fix_previous_field($dbman, $table, $field);
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2014041455;
    if ($oldversion < $newversion) {

        // increase length of ip field to handle ipv6 addresses
        $table = new xmldb_table('reader_attempts');
        $field = new xmldb_field('ip', XMLDB_TYPE_CHAR, '45');

        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2014052876;
    if ($result && $oldversion < $newversion) {
        update_capabilities('mod/reader');
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2014070487;
    if ($result && $oldversion < $newversion) {
        // required only to update config names
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2014070688;
    if ($result && $oldversion < $newversion) {
        // remove table "reader_conflicts"
        $tables = array('reader_conflicts', 'reader_check_question_id');
        foreach ($tables as $table) {
            $table = new xmldb_table($table);
            if ($dbman->table_exists($table)) {
                $dbman->drop_table($table);
            }
        }
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2014071189;
    if ($result && $oldversion < $newversion) {
        // convert timelimit from minutes to seconds
        $DB->execute('UPDATE {reader} SET timelimit = timelimit * ?', array(60));
        if ($value = get_config($plugin, 'quiztimeout')) {
            unset_config('quiztimeout', $plugin);
            set_config('quiztimelimit', $value * 60, $plugin);
        }
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2014071290;
    if ($result && $oldversion < $newversion) {

        $readermoduleid = $DB->get_field('modules', 'id', array('name' => 'reader'));

        if ($records = $DB->get_records('reader')) {
            foreach ($records as $record) {

                // move "attemptsofday" info to "reader_delays" table
                if (isset($record->attemptsofday)) {
                    $delay = $record->attemptsofday * 24 * 60 * 60;
                    $params = array('readerid' => $record->id, 'groupid' => 0, 'level' => 0);
                    if ($DB->record_exists('reader_delays', $params)) {
                        $DB->set_field('reader_delays', 'delay', $delay, $params);
                    } else {
                        $params['delay'] = $delay;
                        $params['timemodified'] = time();
                        $params = (object)$params;
                        $DB->insert_record('reader_delays', $params);
                    }
                }
            }
        }

        // modify fields in "reader" table
        $table = new xmldb_table('reader');

        // remove fields from "reader" table
        $fields = array('attemptsofday', 'delay1', 'delay2', 'optionflags', 'penaltyscheme');
        foreach ($fields as $field) {
            $field = new xmldb_field($field);
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }

        // rename field "secmeass" (security measures) to "checkcheating"
        $field = new xmldb_field('secmeass', XMLDB_TYPE_INTEGER, '4', null, null, null, '0');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'checkcheating');
        }

        // create reader_attempt_questions table (replaces "reader_check_question_id")
        $table = new xmldb_table('reader_attempt_questions');
        if (! $dbman->table_exists($table)) {
            $table->add_field('id',           XMLDB_TYPE_INTEGER, '11', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid',       XMLDB_TYPE_INTEGER, '11');
            $table->add_field('attemptid',    XMLDB_TYPE_INTEGER, '11');
            $table->add_field('questionid',   XMLDB_TYPE_INTEGER, '11');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '11');

            // Add index on primary key
            $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

            // Add indexes to table reader_attempt_questions
            $table->add_index('readatteques_use_ix', XMLDB_INDEX_NOTUNIQUE, array('userid'));
            $table->add_index('readatteques_que_ix', XMLDB_INDEX_NOTUNIQUE, array('questionid'));
            $table->add_index('readatteques_att_ix', XMLDB_INDEX_NOTUNIQUE, array('attemptid'));
            $table->add_index('readatteques_tim_ix', XMLDB_INDEX_NOTUNIQUE, array('timemodified'));

            $dbman->create_table($table);
        }

        // add index on field "level" in table "reader_delays"
        $table = new xmldb_table('reader_delays');
        $index = new xmldb_index('readdela_lev_ix', XMLDB_INDEX_NOTUNIQUE, array('level'));
        if (! $dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2014072892;
    if ($result && $oldversion < $newversion) {
        xmldb_reader_fix_orphans();
        xmldb_reader_fix_slots();
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2014081294;
    if ($result && $oldversion < $newversion) {
        // delete unused fields in "reader" table
        $table = new xmldb_table('reader');
        $fields = array(
            'attempts', 'attemptonlast', 'grademethod', 'decimalpoints',
            'questionsperpage', 'shufflequestions', 'shuffleanswers',
            'questions', 'sumgrades', 'grade', 'review',
            'delaylevel0', 'delaylevel1', 'delaylevel2',
            'delaylevel3', 'delaylevel4', 'delaylevel5',
        );
        foreach ($fields as $field) {
            $field = new xmldb_field($field);
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2014101213;
    if ($result && $oldversion < $newversion) {

        // convert wordsorpoints field values to integers
        $table = 'reader';
        $field = 'wordsorpoints';

        // convert "wordspoints" values from char to integer ('words' => 0, 'points' => 1)
        $fields = $DB->get_columns($table);
        if (isset($fields[$field])) {
            if ($fields[$field]->meta_type=='C') { // 'C' is a char/string field
                $params = array('words', 0, 1);
                $DB->execute('UPDATE {'.$table.'} SET '.$field.' = (CASE WHEN '.$field.' = ? THEN ? ELSE ? END)', $params);
            }
        }

        // fix fields in the "reader" table
        $table = new xmldb_table('reader');
        $fields = array(

            // convert wordsorpoints to integer
            new xmldb_field('wordsorpoints',             XMLDB_TYPE_INTEGER, '4', null, null, null, '0'),

            // fix length of yes/no fields
            new xmldb_field('levelcheck',                XMLDB_TYPE_INTEGER, '4', null, null, null, '1'),
            new xmldb_field('reportwordspoints',         XMLDB_TYPE_INTEGER, '4', null, null, null, '0'),
            new xmldb_field('wordsprogressbar',          XMLDB_TYPE_INTEGER, '4', null, null, null, '1'),
            new xmldb_field('bookinstances',             XMLDB_TYPE_INTEGER, '4', null, null, null, '0'),
            new xmldb_field('sendmessagesaboutcheating', XMLDB_TYPE_INTEGER, '4', null, null, null, '1'),
            new xmldb_field('checkbox',                  XMLDB_TYPE_INTEGER, '4', null, null, null, '0'),

            // restore fields "timeopen" and "timeclose"
            new xmldb_field('timeopen',  XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'introformat'),
            new xmldb_field('timeclose', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'timeopen'),

            // fix default value for timelimit (900 secs = 15 mins)
            new xmldb_field('timelimit', XMLDB_TYPE_INTEGER, '10', null, null, null, '900', 'timeclose')
        );
        foreach ($fields as $field) {
            xmldb_reader_fix_previous_field($dbman, $table, $field);
            if ($dbman->field_exists($table, $field)) {
                $dbman->change_field_type($table, $field);
            } else {
                $dbman->add_field($table, $field);
            }
        }

        // move "course_modules" availablefrom/until to "reader" timeopen/close
        $readermoduleid = $DB->get_field('modules', 'id', array('name' => 'reader'));
        if ($records = $DB->get_records('course_modules', array('module' => $readermoduleid))) {
            foreach ($records as $record) {
                if (isset($record->availablefrom) && $record->availablefrom) {
                    $DB->set_field('reader', 'timeopen', $record->availablefrom, array('id' => $record->instance));
                    $DB->set_field('course_modules', 'availablefrom', 0, array('id' => $record->id));
                }
                if (isset($record->availableuntil) && $record->availableuntil) {
                    $DB->set_field('reader', 'timeclose', $record->availableuntil, array('id' => $record->instance));
                    $DB->set_field('course_modules', 'availableuntil', 0, array('id' => $record->id));
                }
            }
        }

        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2014101716;
    if ($result && $oldversion < $newversion) {
        xmldb_reader_check_stale_files();
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2014103119;
    if ($result && $oldversion < $newversion) {
        unset_config('quizonnextlevel', 'mod_reader');
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2014110220;
    if ($result && $oldversion < $newversion) {

        // rename fields in "reader" table
        $tables = array(
            'reader' => array(
                'uniqueip'        => new xmldb_field('individualstrictip',  XMLDB_TYPE_INTEGER, '4',  null, null, null, '0',  'popup'),
                'minpassgrade'    => new xmldb_field('percentforreading',   XMLDB_TYPE_INTEGER, '4',  null, null, null, '60', 'uniqueip'),
                'thislevel'       => new xmldb_field('nextlevel',           XMLDB_TYPE_INTEGER, '4',  null, null, null, '6',  'minpassgrade'),
                'nextlevel'       => new xmldb_field('quiznextlevel',       XMLDB_TYPE_INTEGER, '4',  null, null, null, '1',  'thislevel'),
                'prevlevel'       => new xmldb_field('quizpreviouslevel',   XMLDB_TYPE_INTEGER, '4',  null, null, null, '3',  'nextlevel'),
                'stoplevel'       => new xmldb_field('promotionstop',       XMLDB_TYPE_INTEGER, '4',  null, null, null, '99', 'prevlevel'),
                'wordsorpoints'   => new xmldb_field('reportwordspoints',   XMLDB_TYPE_INTEGER, '4',  null, null, null, '0',  'levelcheck'),
                'showprogressbar' => new xmldb_field('wordsprogressbar',    XMLDB_TYPE_INTEGER, '4',  null, null, null, '1',  'wordsorpoints'),
                'checkcheating'   => new xmldb_field('checkip',             XMLDB_TYPE_INTEGER, '4',  null, null, null, '1',  'showprogressbar'),
                'notifycheating'  => new xmldb_field('sendmessagesaboutcheating', XMLDB_TYPE_INTEGER, '4', null, null, null, '1', 'bookinstances'),
                'cheatedmessage'  => new xmldb_field('cheated_message',     XMLDB_TYPE_TEXT, 'long',  null, null, null, null, 'checkcheating'),
                'clearedmessage'  => new xmldb_field('not_cheated_message', XMLDB_TYPE_TEXT, 'long',  null, null, null, null, 'cheatedmessage'),
            ),
            'reader_levels' => array(
                'readerid'        => new xmldb_field('readerid',            XMLDB_TYPE_INTEGER, '11', null, null, null, '0',  'userid'),
                'startlevel'      => new xmldb_field('startlevel',          XMLDB_TYPE_INTEGER, '4',  null, null, null, '0',  'readerid'),
                'currentlevel'    => new xmldb_field('currentlevel',        XMLDB_TYPE_INTEGER, '4',  null, null, null, '0',  'startlevel'),
                'stoplevel'       => new xmldb_field('promotionstop',       XMLDB_TYPE_INTEGER, '4',  null, null, null, '99', 'currentlevel'),
            ),
        );
        foreach ($tables as $table => $fields) {
            $table = new xmldb_table($table);
            if ($table->getName()=='reader_levels') {
                $indexes = array('readleve_rea_ix' => array('readerid'),
                                 'readleve_sta_ix' => array('startlevel'),
                                 'readleve_cur_ix' => array('currentlevel'));
            } else {
                $indexes = array();;
            }
            reader_xmldb_update_fields($dbman, $table, $fields, $indexes);
        }

        // rename Reader config settings
        $readercfg = get_config($plugin);
        $configs = array(
            'individualstrictip'  => 'uniqueip',
            'percentforreading'   => 'minpassgrade',
            'nextlevel'           => 'thislevel',
            'quiznextlevel'       => 'nextlevel',
            'quizpreviouslevel'   => 'prevlevel',
            'promotionstop'       => 'stoplevel',
            'reportwordspoints'   => 'wordsorpoints',
            'wordsprogressbar'    => 'showprogressbar',
            'checkip'             => 'checkcheating',
            'sendmessagesaboutcheating' => 'notifycheating',
            'cheated_message'     => 'cheatedmessage',
            'not_cheated_message' => 'clearedmessage',
            'serverlink'          => 'serverurl',
            'serverlogin'         => 'serverusername',
        );
        foreach ($configs as $oldname => $newname) {
            if (isset($readercfg->$oldname)) {
                if (isset($readercfg->$newname)) {
                    // do nothing
                } else {
                    unset_config($oldname, $plugin);
                    $value = $readercfg->$oldname;
                    set_config($newname, $value, $plugin);
                }
            }
        }

        // remove obsolete Reader config settings
        unset_config('update', $plugin);

        // remove obsolete Reader fields
        $table = new xmldb_table('reader');
        $fields = array('reportwordspoints');
        foreach ($fields as $field) {
            $field = new xmldb_field($field);
            if ($dbman->field_exists($table, $field)) {
                $dbman->drop_field($table, $field);
            }
        }
    }

    $newversion = 2014120526;
    if ($oldversion < $newversion) {
        xmldb_reader_migrate_logs($dbman);
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2014121127;
    if ($oldversion < $newversion) {
        if (function_exists('get_log_manager')) {
            if ($dbman->table_exists('log')) {
                // fix incorrect legacy actions in log table
                // remove "OLD_" prefix, and all underscores
                // e.g. OLD_attempt_added => attemptadded
                $update = '{log}';
                $set    = 'action = REPLACE(REPLACE(action, ?, ?), ?, ?)';
                $where  = 'module = ? AND '.$DB->sql_like('action', '?');
                $params = array('OLD_', '', '_', '', 'reader', 'OLD_%');
                $DB->execute("UPDATE $update SET $set WHERE $where", $params);
            }
        }
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2014121529;
    if ($oldversion < $newversion) {
        // rename "nopromote" to "allowpromotion"
        // add switch values, i.e. (0 ? 1 : 0)
        $tablename = 'reader_levels';
        $newname = 'allowpromotion';
        $oldname = 'nopromote';
        $table = new xmldb_table($tablename);
        $field = new xmldb_field($oldname, XMLDB_TYPE_INTEGER, '4',  null, null, null, '1', 'stoplevel');
        if ($dbman->field_exists($table, $field)) {
            xmldb_reader_fix_previous_field($dbman, $table, $field);
            $dbman->change_field_type($table, $field);
            $dbman->rename_field($table, $field, $newname);
            $DB->execute('UPDATE {'.$tablename.'} SET '.$newname.' = (CASE WHEN '.$newname.' = ? THEN ? ELSE ? END)', array(0, 1, 0));
       }
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2015011842;
    if ($oldversion < $newversion) {

        $table = new xmldb_table('reader_grades');
        $fields = array(
            'readerid'      => new xmldb_field('reader',        XMLDB_TYPE_INTEGER, '11', null, null, null, '0', 'id'),
            'rawgrade'      => new xmldb_field('grade',         XMLDB_TYPE_FLOAT,   null, null, null, null, '0', 'userid'),
            'datesubmitted' => new xmldb_field('datesubmitted', XMLDB_TYPE_INTEGER, '11', null, null, null, '0', 'rawgrade'),
            'dategraded'    => new xmldb_field('timemodified',  XMLDB_TYPE_INTEGER, '11', null, null, null, '0', 'datesubmitted'),
        );
        $indexes = array('readgrad_use_ix' => array('userid'),
                         'readgrad_rea_ix' => array('reader', 'readerid'));
        reader_xmldb_update_fields($dbman, $table, $fields, $indexes);

        $table = new xmldb_table('reader_attempts');
        $fields = array('readerid' => new xmldb_field('reader', XMLDB_TYPE_INTEGER, '11', null, null, null, '0', 'uniqueid'));
        $indexes = array('readatte_rea_ix' => array('reader', 'readerid'));
        reader_xmldb_update_fields($dbman, $table, $fields, $indexes);

        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2015012144;
    if ($oldversion < $newversion) {
        // remove obsolete config settings
        $configs = array('editingteacherrole', 'iptimelimit', 'update', 'update_interval');
        foreach ($configs as $config) {
            $reader_config = 'reader_'.$config;
            if (isset($CFG->$reader_config)) {
                unset_config($reader_config);
            }
            unset_config($config, $plugin);
        }
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2015012345;
    if ($oldversion < $newversion) {
        // add "showpercentgrades" field to "reader" table
        $table = new xmldb_table('reader');
        $fields = array('showpercentgrades' => new xmldb_field('pointreport', XMLDB_TYPE_INTEGER, '2', null, null, null, '0', 'showprogressbar'));
        reader_xmldb_update_fields($dbman, $table, $fields);
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2015012653;
    if ($oldversion < $newversion) {
        require_once($CFG->dirroot.'/mod/reader/lib.php');
        $table = new xmldb_table('reader');
        $fields = array('maxgrade' => new xmldb_field('maxgrade', XMLDB_TYPE_INTEGER, '10,5', null, null, null, '0', 'goal'));
        reader_xmldb_update_fields($dbman, $table, $fields);
        reader_update_grades(); // all Reader activities !!
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2015012956;
    if ($oldversion < $newversion) {
        xmldb_reader_fix_sumgrades($dbman);
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2015062484;
    if ($oldversion < $newversion) {
        // in "reader" table, rename "questionmark" field to "questionscores", and add "reviewlinks" field
        $table = new xmldb_table('reader');
        $fields = array(
            'questionscores' => new xmldb_field('questionmark',   XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, null, null, '0', 'stoplevel'),
            'showreviewlinks' => new xmldb_field('showreviewlinks', XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, null, null, '0', 'showpercentgrades')
        );
        reader_xmldb_update_fields($dbman, $table, $fields);
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2015063086;
    if ($oldversion < $newversion) {
        xmldb_reader_fix_orphan_bookattempts();
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2015100994;
    if ($oldversion < $newversion) {
        update_capabilities('mod/reader');
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2015102103;
    if ($oldversion < $newversion) {

        // fix non-integer word counts
        if ($DB->sql_regex_supported()) {
            $select = 'words '.$DB->sql_regex(false).' ?'; // i.e. NOT REGEXP
            $params = array('^[0-9]+$');
            if ($books = $DB->get_records_select('reader_books', $select, $params, 'id', 'id,words')) {
                foreach ($books as $book) {
                    if ($book->words = trim($book->words)) {
                        $book->words = intval($book->words);
                    } else {
                        $book->words = 0;
                    }
                    $DB->set_field('reader_books', 'words', $book->words, array('id' => $book->id));
                }
            }
        }

        // rename and convert fields
        $tables = array(
            'reader_books' => array(
                'difficulty'  => new xmldb_field('difficulty', XMLDB_TYPE_INTEGER, '4',    null, null, null, '99'),
                'points'      => new xmldb_field('length',     XMLDB_TYPE_NUMBER,  '4, 2', null, null, null, '0'),
                'words'       => new xmldb_field('words',      XMLDB_TYPE_INTEGER, '6',    null, null, null, '0'),
            ),
            'reader_book_instances' => array(
                'difficulty'  => new xmldb_field('difficulty', XMLDB_TYPE_INTEGER, '4',    null, null, null, '99'),
                'points'      => new xmldb_field('length',     XMLDB_TYPE_NUMBER,  '4, 2', null, null, null, '0'),
                'words'       => new xmldb_field('words',      XMLDB_TYPE_INTEGER, '6',    null, null, null, '0'),
            ),
        );

        foreach ($tables as $table => $fields) {
            if ($table=='reader_books') {
                $indexes = array('readpubl_dif_ix' => array('difficulty'),
                                 'readgrad_len_ix' => array('length'));
            } else {
                $indexes = array();
            }
            $table = new xmldb_table($table);
            reader_xmldb_update_fields($dbman, $table, $fields, $indexes);
        }
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2016011920;
    if ($oldversion < $newversion) {

        // rename table "reader_delays" to "reader_rates"
        xmldb_reader_rename_table($dbman, 'reader_delays', 'reader_rates');

        // remove old indexes on "reader_rates" table
        $table = new xmldb_table('reader_rates');
        $indexes = array('readforc_rea' => array('readerid'),
                         'readforc_gro' => array('groupid'),
                         'readforc_lev' => array('level'),
                         'readdela_rea' => array('readerid'),
                         'readdela_gro' => array('groupid'),
                         'readdela_lev' => array('level'));
        reader_xmldb_drop_indexes($dbman, $table, $indexes);

        // add/rename fields in "reader_rates" table
        $fields = array(
            'level'    => new xmldb_field('level',    XMLDB_TYPE_INTEGER,  '4',  null, XMLDB_NOTNULL, null, '0',  'groupid'),
            'type'     => new xmldb_field('type',     XMLDB_TYPE_INTEGER,  '4',  null, XMLDB_NOTNULL, null, '0',  'level'),
            'attempts' => new xmldb_field('attempts', XMLDB_TYPE_INTEGER,  '6',  null, XMLDB_NOTNULL, null, '1',  'type'),
            'duration' => new xmldb_field('delay',    XMLDB_TYPE_INTEGER, '11',  null, XMLDB_NOTNULL, null, '0',  'attempts'),
            'action'   => new xmldb_field('action',   XMLDB_TYPE_INTEGER,  '4',  null, XMLDB_NOTNULL, null, '0',  'duration')
        );
        reader_xmldb_update_fields($dbman, $table, $fields);

        // add new indexes on "reader_rates" table
        $table = new xmldb_table('reader_rates');
        $indexes = array('readrate_rea' => array('readerid'),
                         'readrate_gro' => array('groupid'),
                         'readrate_lev' => array('level'));
        reader_xmldb_add_indexes($dbman, $table, $indexes);

        // remove empty rates left over from delays table
        $select = "groupid = ? AND level = ? AND duration = ?";
        $DB->delete_records_select('reader_rates', $select, array(0, 0, 0));

        // set default "action" for pre-existing "reader_rates" records
        $DB->set_field('reader_rates', 'action', 1, array('action' => 0));

        // add completion fields on "reader" table
        $table = new xmldb_table('reader');
        $fields = array(
            'completionpass' =>       new xmldb_field('completionpass',       XMLDB_TYPE_INTEGER,  '1', null, XMLDB_NOTNULL, null, 0, 'clearedmessage'),
            'completiontotalwords' => new xmldb_field('completiontotalwords', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, 0, 'completionpass')
        );
        reader_xmldb_update_fields($dbman, $table, $fields);

        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2016041638;
    if ($result && $oldversion < $newversion) {
        update_capabilities('mod/reader');
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2016092954;
    if ($result && $oldversion < $newversion) {

        $i_max = 0;
        $rs = false;
        if ($params = xmldb_reader_get_question_categories()) {
            list($where, $params) = $DB->get_in_or_equal(array_keys($params));

            $select = 'id, category, questiontext';
            $from   = '{question}';
            $where  = "category $where AND (".$DB->sql_like('questiontext', '?').
                                            ' OR '.$DB->sql_like('questiontext', '?').
                                            ' OR '.$DB->sql_like('questiontext', '?').
                                            ' OR '.$DB->sql_like('questiontext', '?').
                                            ' OR '.$DB->sql_like('questiontext', '?').
                                            ' OR '.$DB->sql_like('questiontext', '?').
                                            ' OR '.$DB->sql_like('questiontext', '?').')';
            array_push($params, '%<script%', '%<style%', '%<xml%',
                                '%<link%',   '%<meta%',  '%<pre%',
                                '%&lt;!--%');
            $sql = "SELECT $select FROM $from WHERE $where";
            if ($i_max = $DB->count_records_sql("SELECT COUNT(*) FROM ($sql) temptable", $params)) {
                $rs = $DB->get_recordset_sql($sql, $params);
            }
        }

        if ($rs) {
            $strupdating = get_string('fixquestiontext', $plugin);
            if ($interactive) {
                $bar = new progress_bar('fixquestiontext', 500, true);
            } else {
                $bar = false;
            }
            $i = 0; // record counter

            // regular expressions to detect unwantedtags in questions text
            // - <script> ... </script>
            // - <style> ... </style>
            // - <!-- ... -->
            // - <pre> and </pre>
            $search = array('/\s*<(script|style|xml)\b[^>]*>.*?<\/\1>/is',
                            '/\s*(&lt;)!--.*?--(&gt;)/s',
                            '/\s*<\/?(link|meta|pre)\b[^>]*>/i');

            // loop through answer records
            foreach ($rs as $question) {
                $i++; // increment record count

                // apply for more script execution time (3 mins)
                upgrade_set_timeout();

                // remove unwanted tags from question text
                $questiontext = preg_replace($search, '', $question->questiontext);
                $DB->set_field('question', 'questiontext', $questiontext, array('id' => $question->questionid));

                // update progress bar
                if ($bar) {
                    $bar->update($i, $i_max, $strupdating.": ($i/$i_max)");
                }
            }
            $rs->close();
        }

        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    return $result;
}
