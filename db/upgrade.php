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
 * @param xxx $oldversion
 * @return xxx
 * @todo Finish documenting this function
 */
function xmldb_reader_upgrade($oldversion) {
    global $CFG, $DB;
    $result = true;

    $dbman = $DB->get_manager();

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

    $newversion = 2013033102;
    if ($result && $oldversion < $newversion) {
        update_capabilities('mod/reader');
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013033104;
    if ($result && $oldversion < $newversion) {

        // rename tables "reader_publisher" and "reader_individual_books"
        $tables = array('reader_publisher'=>'reader_books', 'reader_individual_books'=>'reader_book_instances');
        foreach ($tables as $oldname => $newname) {
            $oldtable = new xmldb_table($oldname);
            if ($dbman->table_exists($oldname)) {
                if ($dbman->table_exists($newname)) {
                    $dbman->drop_table($oldtable);
                } else {
                    $dbman->rename_table($oldtable, $newname);
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
    if ($result && $oldversion < $newversion) {
        xmldb_reader_check_stale_files();
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013033106;
    if ($result && $oldversion < $newversion) {
        // fix incorrectly set version of "readerview" block (it is one digit too long !)
        $DB->set_field('block', 'version', 2012011910, array('name'=>'readerview', 'version'=>'20120119101'));
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013033107;
    if ($result && $oldversion < $newversion) {

        if ($courseid = get_config('reader', 'reader_usecourse')) {
            $rebuild_course_cache = false;

            if ($sections = $DB->get_records_select('course_sections', "course = ? AND section > ?", array($courseid, 0))) {
                foreach ($sections as $section) {

                    $sectionname = '';
                    if ($sectionname=='') {
                        $sectionname = trim(strip_tags($section->name));
                    }
                    if ($sectionname=='') {
                        $sectionname = trim(strip_tags($section->summary));
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
                    if ($sectionname && $section->summary) {
                        $DB->set_field('course_sections', 'summary', '', array('id' => $section->id));
                        $rebuild_course_cache = true;
                    }
                }
            }

            if ($rebuild_course_cache) {
                rebuild_course_cache($courseid);
            }
        }
        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013033108;
    if ($result && $oldversion < $newversion) {

        // remove slashes from reader log records
        $update = '{log}';
        $set    = "action = REPLACE(action, '\\\\', '')";
        $where  = "module='reader' AND action LIKE 'view attempt:%'";
        $DB->execute("UPDATE $update SET $set WHERE $where");

        // remove slashes from book names
        $update = '{reader_books}';
        $set    = "name = REPLACE(name, '\\\\', '')";
        $where  = "name LIKE '%\\\\%'";
        $DB->execute("UPDATE $update SET $set WHERE $where");

        // remove slashes from question categories
        $update = '{question_categories}';
        $set    = "name = REPLACE(name, '\\\\', '')";
        $where  = "name LIKE '%\\\\%'";
        $DB->execute("UPDATE $update SET $set WHERE $where");

        upgrade_mod_savepoint(true, "$newversion", 'reader');
    }

    $newversion = 2013033109;
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
                    new xmldb_field('persent', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '0', 'sumgrades'),
                    new xmldb_field('percent', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '0', 'sumgrades')
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
                        if ($field->getNotNull()) {
                            $default = $field->getDefault();
                            $oldfieldname = $field->getName();
                            $DB->set_field_select($tablename, $oldfieldname, $default, "$oldfieldname IS NULL");
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

    $newversion = 2013040400;
    if ($result && $oldversion < $newversion) {

        // create "reader" folder within Moodle data folder
        make_upload_directory('reader');

        // set new/old location for Reader "images" folder
        $courseid = get_config('reader', 'reader_usecourse');
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
            remove_dir($oldname, false); // in "lib/moodlelib.php"
        }

        // remove old "script.txt" file (if necessary)
        $oldname = $CFG->dirroot.'/blocks/readerview/script.txt';
        if (file_exists($oldname)) {
            @unlink($oldname);
        }
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


    return $result;
}

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
