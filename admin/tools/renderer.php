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
defined('MOODLE_INTERNAL') || die;

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
        global $CFG, $SCRIPT;

        $id = optional_param('id', 0, PARAM_INT);
        $tab = optional_param('tab', 0, PARAM_INT);

        // get string manager
        $strman = get_string_manager();

        // get path to this directory
        $dirname = dirname($SCRIPT).'/tools';
        $dirpath = $CFG->dirroot.$dirname;

        echo html_writer::start_tag('ol', array('class' => 'readertools'));

        $files = array();
        $items = new DirectoryIterator($dirpath);
        foreach ($items as $item) {
            if ($item->isDot() || substr($item, 0, 1)=='.' || trim($item)=='') {
                continue;
            }
            if ($item=='index.php' || $item=='lib.php' || $item=='renderer.php') {
                continue;
            }
            if ($item->isFile()) {
                $files[] = "$item"; // convert $item to string
            }
        }
        sort($files);
        foreach ($files as $file) {

            $href = new moodle_url($dirname.'/'.$file, array('id' => $id, 'tab' => $tab));
            $text = substr($file, 0, strrpos($file, '.'));
            $desc = '';
            if ($strman->string_exists($text.'desc', 'reader')) {
                $desc = get_string($text.'desc', 'mod_reader');
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
}
