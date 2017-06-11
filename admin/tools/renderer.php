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
 * mod/reader/admin/tools/renderer.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die();

/** Include required files */
require_once($CFG->dirroot.'/mod/reader/admin/renderer.php');

/**
 * mod_reader_admin_tools_renderer
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_tools_renderer extends mod_reader_admin_renderer {

    public $tab = 'tools';

    /**
     * render_page
     */
    public function render_page() {
        $id = optional_param('id', 0, PARAM_INT);
        $tab = optional_param('tab', 0, PARAM_INT);

        // get string manager
        $strman = get_string_manager();

        echo html_writer::start_tag('ol', array('class' => 'readertools'));

        $files = self::get_files();
        foreach ($files as $text => $file) {

            $href = new moodle_url($file, array('id' => $id, 'tab' => $tab));
            $desc = '';
            if ($strman->string_exists($text.'_desc', 'reader')) {
                $desc = get_string($text.'_desc', 'mod_reader');
                $desc = format_text($desc, FORMAT_MARKDOWN);
            }
            if ($strman->string_exists($text, 'reader')) {
                $text = get_string($text, 'mod_reader');
            }
            $text = html_writer::tag('a', $text, array('href' => $href));

            echo html_writer::start_tag('li', array('class' => 'readertool'));
            if ($text) {
                $params = array('class' => 'readertooltext');
                echo html_writer::tag('span', $text);
            }
            if ($text && $desc) {
                echo html_writer::empty_tag('br');
            }
            if ($desc) {
                $params = array('class' => 'readertooldesc');
                echo html_writer::tag('span', $desc, $params);
            }
            echo html_writer::end_tag('li');
        }

        echo html_writer::end_tag('ol');
        echo html_writer::tag('div', '', array('style' => 'clear: both;'));
    }

    static public function get_files() {
        global $CFG;
        $files = array();
        $dirname = '/mod/reader/admin/tools';
        $dirpath = $CFG->dirroot.$dirname;
        $items = new DirectoryIterator($dirpath);
        foreach ($items as $item) {
            if ($item->isDot() || substr($item, 0, 1)=='.' || trim($item)=='') {
                continue;
            }
            if ($item=='index.php' || $item=='lib.php' || $item=='renderer.php') {
                continue;
            }
            if ($item->isFile()) {
                $name = substr($item, 0, strrpos($item, '.'));
                if (self::is_available($name)) {
                    $files[$name] = "$dirname/$item"; // convert $item to string
                }
            }
        }
        ksort($files);
        return $files;
    }

    static public function is_available($toolname) {
        global $DB, $PAGE;
        switch ($toolname) {
            case 'check_email':
            case 'export_reader_tables':
            case 'move_quizzes':
            case 'print_cheatsheet':
                $capability = 'mod/reader:managebooks'; // teacher
                $context = $PAGE->context;
                break;

            case 'find_faultyquizzes':
            case 'fix_bookcovers':
            case 'fix_bookinstances':
            case 'fix_missingquizzes':
            case 'fix_questioncategories':
            case 'fix_slashesinnames':
            case 'fix_wrongattempts':
                $capability = 'mod/reader:managetools'; // manager
                $context = $PAGE->context;
                break;

            case 'fix_coursesections':
                if ($courseid = $DB->get_field('reader', 'usecourse', array('id' => $PAGE->cm->instance))) {
                    // use the course id specified in the Reader acitivty
                } else {
                    $courseid = get_config('mod_reader', 'usecourse');
                }
                $capability = 'moodle/course:manageactivities';
                $context = mod_reader::context(CONTEXT_COURSE, $courseid);
                break;

            case 'add_phpdoc':
            case 'fix_installxml':
            case 'import_reader_tables':
            case 'redo_upgrade':
            case 'run_readercron':
            case 'sort_strings':
            default:
                $capability = 'moodle/site:config'; // administrator (developer)
                $context = mod_reader::context(CONTEXT_COURSE, SITEID);
                break;
        }
        return has_capability($capability, $context);
    }
}
