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
defined('MOODLE_INTERNAL') || die();

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
    const TAB_USERS_SETLEVELS   = 51;
    const TAB_USERS_SETGOALS    = 52;
    const TAB_USERS_SETRATES    = 53;
    const TAB_USERS_SETMESSAGE  = 54;
    const TAB_USERS_IMPORT      = 55;
    const TAB_USERS_EXPORT      = 56;
    /**#@-*/

    public $tab = 'users';

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
        return self::TAB_USERS_SETLEVELS;
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

            foreach (self::get_standard_modes() as $mode) {
                $tab = constant('self::TAB_USERS_'.strtoupper($mode));
                $params = array('id' => $cmid, 'tab' => $tab, 'mode' => $mode);
                $url = new moodle_url('/mod/reader/admin/users.php', $params);
                $tabs[] = new tabobject($tab, $url, get_string($mode, 'mod_reader'));
            }
        }
        return $this->attach_tabs_subtree(parent::get_tabs(), parent::TAB_USERS, $tabs);
    }

    /**
     * get_standard_modes
     *
     * @param object $reader (optional, default=null)
     * @return string HTML output to display navigation tabs
     */
    static public function get_standard_modes($reader=null) {
        return array('setlevels', 'setgoals', 'setrates', 'setmessage', 'import', 'export');
    }
}
