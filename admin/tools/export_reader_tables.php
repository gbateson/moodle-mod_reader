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
 * mod/reader/admin/tools/redo_upgrade.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once('../../../../config.php');
require_once($CFG->dirroot.'/mod/reader/admin/tools/lib.php');
require_once($CFG->dirroot.'/mod/reader/admin/tools/renderer.php');
require_once($CFG->dirroot.'/mod/reader/locallib.php');

$id  = optional_param('id',  0, PARAM_INT);
$tab = optional_param('tab', 0, PARAM_INT);
$tool = substr(basename($SCRIPT), 0, -4);

require_login(SITEID);
require_capability('moodle/site:config', reader_get_context(CONTEXT_SYSTEM));

if ($id) {
    $cm = get_coursemodule_from_id('reader', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $reader = $DB->get_record('reader', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    $cm = null;
    $course = null;
    $reader = null;
}
$reader = mod_reader::create($reader, $cm, $course);

// set page url
$params = array('id' => $id, 'tab' => $tab);
$PAGE->set_url(new moodle_url("/mod/reader/admin/tools/$tool.php", $params));

// set page title
$title = get_string($tool, 'mod_reader');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');

if (! headers_sent()) {
    $filename = 'reader.tables.txt';
    $ext = substr($filename, strrpos($filename, '.')+1);
    if ($ext=='txt' || $ext=='sql') {
        $type = 'text/plain';
    } else {
        $type = "application/$ext";
    }
    header('Cache-Control: public');
    header('Content-Description: File Transfer');
    header("Content-Disposition: attachment; filename=$filename");
    header("Content-Type: $type");
    header('Content-Transfer-Encoding: binary');
}

// get name of books table
$dbman = $DB->get_manager();

echo reader_export_db_header();
foreach ($DB->get_tables() as $table) {
    if (preg_match('/reader/', $table)) {
        $columns = $DB->get_columns($table);
        echo reader_export_table_header($table);
        echo reader_export_table_columns($columns);
        echo reader_export_table_keys($table);
        echo reader_export_table_footer($table);
        echo reader_export_table_data($table, $columns);
    }
}
echo reader_export_db_footer();
die;

/////////////////////////////////////////////////
// functions
/////////////////////////////////////////////////

function reader_export_db_header() {
    global $CFG, $DB;

    $mysqlversion = $DB->get_server_info();
    $mysqlversion = $mysqlversion['version'];

    $moodlerelease = $CFG->release;

    if (array_key_exists('version', $DB->get_columns('modules'))) {
        // Moodle <= 2.6
        $readerversion = $DB->get_field('modules', 'version', array('name' => 'reader'));
    } else {
        // Moodle >= 2.7
        $readerversion = $DB->get_field('config_plugins', 'value', array('plugin' => 'mod_reader', 'name' => 'version'));
    }
    $readerrelease = preg_replace('/^([0-9]{4})([0-9]{2})([0-9]{2})([0-9]{2})$/', '$1.$2.$3 ($4)', $readerversion);

    $output = '';
    $output .= "-- Dump of Moodle Reader tables\n";
    $output .= "-- Dump started".date(' \o\n Y-m-d \a\t H:i:s')."\n";
    $output .= "--\n";
    $output .= "-- Host: $CFG->dbhost	Database: $CFG->dbname\n";
    $output .= "-- Moodle: $moodlerelease\n";
    $output .= "-- Reader: $readerrelease\n";
    $output .= "-- ------------------------------------------------------\n";
    $output .= "-- Server version	$mysqlversion\n";
    $output .= "\n";
    $output .= "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n";
    $output .= "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n";
    $output .= "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n";
    $output .= "/*!40101 SET NAMES utf8 */;\n";
    $output .= "/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;\n";
    $output .= "/*!40103 SET TIME_ZONE='+00:00' */;\n";
    $output .= "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n";
    $output .= "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n";
    $output .= "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n";
    $output .= "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n";
    $output .= "\n";
    $output .= "--\n";
    $output .= "-- set Reader module version\n";
    $output .= "--\n";
    $output .= "SET @dbname = DATABASE();\n";
    $output .= "\n";
    $output .= "SELECT COUNT(*)\n";
    $output .= "  INTO @modules\n";
    $output .= "  FROM information_schema.columns \n";
    $output .= " WHERE table_schema = @dbname\n";
    $output .= "   AND table_name = 'mdl_modules'\n";
    $output .= "   AND column_name = 'version';\n";
    $output .= "\n";
    $output .= "SET @table = (CASE @modules WHEN 1 THEN 'mdl_modules' ELSE 'mdl_config_plugins' END);\n";
    $output .= "SET @field = (CASE @modules WHEN 1 THEN 'version' ELSE 'value' END);\n";
    $output .= "SET @where = (CASE @modules WHEN 1 THEN \"name = 'reader'\" ELSE \"plugin = 'mod_reader' AND name = 'version'\" END);\n";
    $output .= "SET @value = \"'$readerversion'\";\n";
    $output .= "SET @sql = CONCAT('UPDATE ', @table,' SET ', @field, ' = ', @value, ' WHERE ', @where);\n";
    $output .= "\n";
    $output .= "PREPARE set_reader_version FROM @sql;\n";
    $output .= "EXECUTE set_reader_version;\n";
    $output .= "DEALLOCATE PREPARE set_reader_version;\n";
    $output .= "\n";
    $output .= "SET @dbname  = NULL;\n";
    $output .= "SET @field   = NULL;\n";
    $output .= "SET @sql     = NULL;\n";
    $output .= "SET @table   = NULL;\n";
    $output .= "SET @modules = NULL;\n";
    $output .= "SET @value   = NULL;\n";
    $output .= "SET @where   = NULL;\n";
    $output .= "\n";
    return $output;
}

function reader_export_db_footer() {
    $output = '';
    $output .= "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;\n";
    $output .= "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n";
    $output .= "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n";
    $output .= "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;\n";
    $output .= "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n";
    $output .= "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n";
    $output .= "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n";
    $output .= "/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;\n";
    $output .= "-- Dump completed".date(' \o\n Y-m-d \a\t H:i:s')."\n";
    $output .= "\n";
    return $output;
}

function reader_export_table_header($table) {
    $output = '';
    $output .= "--\n";
    $output .= "-- Create table `mdl_$table`\n";
    $output .= "--\n";
    $output .= "\n";
    $output .= "DROP TABLE IF EXISTS `mdl_$table`;\n";
    $output .= "/*!40101 SET @saved_cs_client = @@character_set_client */;\n";
    $output .= "/*!40101 SET character_set_client = utf8 */;\n";
    $output .= "CREATE TABLE `mdl_$table` (\n";
    return $output;
}

function reader_export_table_columns($columns) {
    global $DB;
    $output = '';
    if (method_exists($DB, 'get_dbcollation')) {
        $collation = $DB->get_dbcollation();
    } else {
        $collation = 'utf8_general_ci';
    }
    foreach ($columns as $column) {
        $output .= "  `$column->name`";
        switch ($column->meta_type) {
            case 'X':
                $output .= " $column->type";
                break;
            case 'N':
                $output .= (empty($column->max_length) ? ' double' : " $column->type($column->max_length,2)"); break;
                break;
            case 'R':
            case 'I':
                $column->max_length = min($column->max_length, 10);
            default:
                $output .= " $column->type($column->max_length)";
        }
        if ($column->meta_type=='C' || $column->meta_type=='X') {
            $output .= " COLLATE $collation";
        }
        if ($column->unsigned) {
            $output .= ' unsigned';
            $column->not_null = true;
        }
        if ($column->not_null) {
            $output .= ' NOT NULL';
        }
        if ($column->has_default) {
            $output .= " DEFAULT '".$column->default_value."'";
        }
        if ($column->auto_increment) {
            $output .= ' AUTO_INCREMENT';
        }
        $output .= ",\n";
    }
    return $output;
}

function reader_export_table_keys($table) {
    global $CFG, $DB;
    $keys = array();
    $keys[] = "  PRIMARY KEY (`id`)";
    if ($indexes = $DB->get_indexes($table)) {
        foreach ($indexes as $index) {
            // http://docs.moodle.org/dev/XMLDB_key_and_index_naming
            // [$CFG->prefix][tablename_abbreviated]_[columnames_abbreviated]_object_type
            $name = 'mdl_';
            $parts = explode('_', $table);
            foreach ($parts as $part) {
                $name .= substr($part, 0, 4);
            }
            $name .= '_';
            foreach ($index['columns'] as $column) {
                $name .= substr($column, 0, 3);
            }
            $name .= '_';
            if (empty($index['unique'])) {
                $name .= 'ix';
                $KEY = 'KEY';
            } else {
                $name .= 'uix';
                $KEY = 'UNIQUE KEY';
            }
            $keys[] = "  $KEY `$name` (`".implode('`,`', $index['columns'])."`)";
        }
    }
    return implode(",\n", $keys)."\n";
}

function reader_export_table_footer($table) {
    global $CFG, $DB;
    $output = '';
    $engine = $DB->get_dbengine();
    if (method_exists($DB, 'get_dbcollation')) {
        $collation = $DB->get_dbcollation();
    } else {
        $collation = 'utf8_general_ci';
    }
    $comment = reader_export_table_comment($table);
    if ($maxid = $DB->get_field($table, 'MAX(id)', array())) {
        $maxid ++;
    } else {
        $maxid = 1;
    }
    $output .= ") ENGINE=$engine AUTO_INCREMENT=$maxid DEFAULT CHARSET=utf8 COLLATE=$collation COMMENT='$comment';\n";
    $output .= "/*!40101 SET character_set_client = @saved_cs_client */;\n";
    $output .= "\n";
    return $output;
}

function reader_export_table_comment($table) {
    global $CFG, $DB;
    $select = 'table_comment';
    $from   = 'INFORMATION_SCHEMA.TABLES';
    $where  = "table_schema='$CFG->dbname' AND table_name='$CFG->prefix$table'";
    return $DB->get_field_sql("SELECT $select FROM $from WHERE $where", null);
}

function reader_export_table_data($table, $columns) {
    global $DB;

    // http://evertpot.com/escaping-mysql-strings-with-no-connection-available/
    $replacements = array(
        "\x00" => '\x00',
        "\n"   => '\n',
        "\r"   => '\r',
        "\\"   => '\\\\',
        "'"    => "\'",
        '"'    => '\"',
        "\x1a" => '\x1a'
    );

    $output = '';
    $values = array();
    if ($rs = $DB->get_recordset_sql('SELECT * FROM {'.$table.'}')) {
        foreach ($rs as $record) {
            $value = array();
            foreach ($columns as $column) {
                $name = $column->name;
                if (property_exists($record, $name)) {
                    if (is_null($record->$name)) {
                        $value[] = 'NULL';
                    } else switch ($column->meta_type) {
                        /**
                         * lib/dml/database_column_info.php
                         * R - counter (integer primary key)
                         * I - integers
                         * N - numbers (floats)
                         * C - characters and strings
                         * X - texts
                         * B - binary blobs
                         * L - boolean (1 bit)
                         * T - timestamp - unsupported
                         * D - date - unsupported
                         */
                        case 'I': // Integer
                        case 'N': // Number (floats)
                        case 'R': // counteR ("id" field)
                            $value[] = $record->$name;
                            break;
                        case 'C': // Char
                        case 'X': // teXt
                            $value[] = "'".strtr($record->$name, $replacements)."'";
                            break;
                        default:
                            echo 'Unknown meta-type';
                            print_object($column);
                            die;
                    }
                }
            }
            if ($value = implode(',', $value)) {
                $values[] = '  ('.$value.')';
            }
        }
    }
    if ($values = implode(",\n", $values)) {
        $output .= "--\n";
        $output .= "-- Insert data for table `mdl_$table`\n";
        $output .= "--\n";
        $output .= "\n";
        $output .= "LOCK TABLES `mdl_$table` WRITE;\n";
        $output .= "/*!40000 ALTER TABLE `mdl_$table` DISABLE KEYS */;\n";
        $output .= "INSERT INTO `mdl_$table` VALUES\n".$values."\n;\n";
        $output .= "/*!40000 ALTER TABLE `mdl_$table` ENABLE KEYS */;\n";
        $output .= "UNLOCK TABLES;\n";
        $output .= "\n";
    }
    return $output;
}
