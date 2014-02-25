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
 * mod/reader/admin/users/renderer.php
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
 * mod_reader_admin_users_renderer
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_users_renderer extends mod_reader_admin_renderer {

    /**#@+
     * tab ids
     *
     * @var integer
     */
    const TAB_USERS_SETGOALS    = 51;
    const TAB_USERS_SETLEVELS   = 52;
    const TAB_USERS_SENDMESSAGE = 53;
    const TAB_USERS_IMPORT      = 54;
    const TAB_USERS_EXPORT      = 55;
    /**#@-*/

    public $modes = array('setgoals', 'setlevels', 'sendmessage', 'import', 'export');
    public $actions = array('setgoals', 'setlevels', 'sendmessage', 'import', 'export');

    /**
     * get_my_tab
     *
     * @return integer tab id
     */
    public function get_my_tab() {
        return self::TAB_USERS;
    }

    /**
     * get_default_tab
     *
     * @return integer tab id
     */
    public function get_default_tab() {
        return self::TAB_USERS_SETGOALS;
    }

    /**
     * get_tabs
     *
     * @return string HTML output to display navigation tabs
     */
    public function get_tabs() {
        $tabs = array();
        $cmid = $this->reader->cm->id;
        if ($this->reader->can_manageusers()) {

            foreach ($this->modes as $mode) {
                $tab = constant('self::TAB_USERS_'.strtoupper($mode));
                $params = array('id' => $cmid, 'tab' => $tab, 'mode' => $mode);
                $url = new moodle_url('/mod/reader/admin/users.php', $params);
                $tabs[] = new tabobject($tab, $url, get_string($mode, 'reader'));
            }
        }
        return $this->attach_tabs_subtree(parent::get_tabs(), parent::TAB_USERS, $tabs);
    }

    /**
     * heading_action_users
     *
     * @param string  $action
     * @return string formatted heading for this $action
     */
    public function heading_action_users($action) {
        return $this->heading_action($action, 'users');
    }

    /**
     * list_actions_users
     *
     * @param integer $cmid of current Reader activity
     * @return string formatted list of links to user actions
     */
    public function list_actions_users($cmid) {
        return $this->list_actions($cmid, 'users');
    }

    /**
     * action_export
     *
     * @param integer $readerid of current Reader activity
     * @return string formatted html output
     */
    public function action_export($cmid) {
        global $CFG;
        require_once($CFG->dirroot.'/mod/reader/admin/users/export_form.php');

        $params = array('id' => $cmid, 'action' => 'export');
        $url = new moodle_url('/mod/reader/admin/users/export.php', $params);
        $mform = new mod_reader_admin_users_export_form($url);
        $mform->display();
    }

    /**
     * action_import
     *
     * @param integer $cmid of current Reader activity
     * @return string formatted html output
     */
    public function action_import($cmid) {
        global $CFG;
        require_once($CFG->dirroot.'/mod/reader/admin/users/import_form.php');

        $params = array('id' => $cmid, 'action' => 'import');
        $url = new moodle_url('/mod/reader/admin/users/import.php', $params);
        $mform = new mod_reader_admin_users_import_form($url);
        $mform->display();
    }
}
