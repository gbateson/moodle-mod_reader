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
 * mod/reader/admin/users/setlevels/renderer.php
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
require_once($CFG->dirroot.'/mod/reader/admin/users/renderer.php');

/**
 * mod_reader_admin_users_setlevels_renderer
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_users_setlevels_renderer extends mod_reader_admin_users_renderer {

    public $mode = 'setlevels';

    /**
     * get_tab
     *
     * @return integer tab id
     */
    public function get_tab() {
        return self::TAB_USERS_SETLEVELS;
    }

    /**
     * render_page
     *
     * @return string HTML output to display navigation tabs
     */
    public function render_page() {
        global $CFG;
        require_once($CFG->dirroot.'/mod/reader/admin/users/setlevels/form.php');

        if ($cancel = optional_param('cancel', '', PARAM_ALPHA)) {
            $data = null;
            $action = '';
        } else {
            $data = data_submitted();
            $action = optional_param('action', '', PARAM_ALPHA);
        }

        // process incoming data, if necessary
        if ($data) {
            $userids = $this->get_group_userids($data);
            $this->set_level_field($userids, $data, 'startlevel', 'level');
            $this->set_level_field($userids, $data, 'currentlevel', 'level');
            $this->set_level_field($userids, $data, 'stoplevel', 'level');
            $this->set_level_field($userids, $data, 'allowpromotion', 'allow');
        }

        // set the url for the form
        $url = $this->page->url;
        $params = $url->params();
        $params['id'] = $this->reader->cm->id;
        $params['tab'] = $this->get_tab();
        $params['mode'] = mod_reader::get_mode('admin/users');
        $url->params($params);

        // initialize the form
        $form = new mod_reader_admin_users_setlevels_form($url->out(false), $this->reader);

        // display the form
        $form->display();
    }

    function get_group_userids($data) {
        global $DB, $USER;

        if (empty($data->group)) {
            return false;
        }

        $groupid = $data->group;
        $courseid = $this->reader->course->id;
        $groupmode = $this->reader->course->groupmode;

        // check selected group is in current course
        if (! $DB->record_exists('groups', array('id' => $groupid, 'courseid' => $courseid))) {
            return false;
        }

        if ($groupmode==SEPARATEGROUPS) {
            $context = reader_get_context(CONTEXT_COURSE, $courseid);
            if (has_capability('moodle/site:accessallgroups', $context)) {
                $groupmode = VISIBLEGROUPS; // user can access all groups
            }
        }

        // check selected group is visible to this user
        if ($groupmode==SEPARATEGROUPS) {
            if (! $DB->record_exists('groups_members', array('groupid' => $groupid, 'userid' => $USER->id))) {
                return false;
            }
        }

        if (! $users = $DB->get_records_menu('groups_members', array('groupid' => $groupid), 'userid', 'id, userid')) {
            return false;
        }

        return array_values($users);
    }

    function set_level_field($userids, $data, $fieldname, $subfieldname) {
        global $DB;

        if (empty($data->$fieldname)) {
            return false;
        }
        $field = $data->$fieldname;
        if (empty($field['enabled'])) {
            return false;
        }
        if (empty($field[$subfieldname])) {
            $value = 0;
        } else {
            $value = clean_param($field[$subfieldname], PARAM_INT);
        }
        if (empty($userids)) {
            $DB->set_field('reader_levels', $fieldname, $value, array('readerid' => $this->reader->id));
        } else {
            $update = '{reader_levels}';
            list($where, $params) = $DB->get_in_or_equal($userids);
            array_unshift($params, $value, $this->reader->id);
            $DB->execute("UPDATE {reader_levels} SET $fieldname = ? WHERE readerid = ? AND userid $where", $params);
        }
    }
}
