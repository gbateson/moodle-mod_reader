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
 * mod/reader/admin/renderer.php
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
require_once($CFG->dirroot.'/mod/reader/renderer.php');

/**
 * mod_reader_download_renderer
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_renderer extends mod_reader_renderer {

    public $actions = array();

    /**
     * heading_action
     *
     * @param string  $action
     * @param string  $type of action: "quizzes", "books", "users", "attempts"
     * @return string formatted heading for this $action and $type
     */
    public function heading_action($action, $type) {
        if ($action) {
            $heading = $type.$action;
        } else {
            $heading = 'reader:manage'.$type;
        }
        return $this->heading(get_string($heading, 'reader'));
    }

    /**
     * list_actions
     *
     * @param integer course_modules $id of current Reader activity
     * @param string  $type of action: "quizes", "books", "users", "attempts"
     * @return string formatted list of links to user actions
     */
    public function list_actions($cmid, $type) {
        $links = array();
        foreach ($this->actions as $action) {
            $params = array('id' => $cmid, 'action' => $action);
            $href = new moodle_url("/mod/reader/admin/$type.php", $params);
            $text = get_string($type.$action, 'reader');
            $links[] = html_writer::link($href, $text);
        }
        return html_writer::alist($links);
    }

    /**
     * continue_button
     *
     * @param integer course_modules $id of current Reader activity
     * @return formatted link to return to admin index
     */
    public function continue_button($id) {
        $url = new moodle_url('/mod/reader/admin/index.php', array('id' => $id));
        return parent::continue_button($url);
    }
}
