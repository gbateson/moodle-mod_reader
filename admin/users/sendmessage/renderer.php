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
require_once($CFG->dirroot.'/mod/reader/admin/users/renderer.php');

/**
 * mod_reader_admin_users_sendmessage_renderer
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_users_sendmessage_renderer extends mod_reader_admin_users_renderer {

    /**
     * render_page
     *
     * @return string HTML output to display navigation tabs
     */
    public function render_page() {
        global $CFG, $DB, $PAGE, $USER;
        require_once($CFG->dirroot.'/mod/reader/admin/users/sendmessage/form.php');

        if ($cancel = optional_param('cancel', '', PARAM_ALPHA)) {
            $data = null;
            $action = '';
            $messageid = 0;
        } else {
            $data = data_submitted();
            $action = optional_param('action', '', PARAM_ALPHA);
            $messageid = optional_param('messageid', 0,  PARAM_INT);
        }

        // delete messages, if required
        if ($messageid && $action=='deletemessage') {
            $params = array('id' => $messageid, 'readerid' => $PAGE->cm->instance);
            $DB->delete_records('reader_messages', $params);
        }

        // add new message, if required
        if ($data) {

            // clean "groupids"
            if (empty($data->groupids)) {
                $data->groupids = '';
            } else {
                $data->groupids = array_filter($data->groupids, 'intval');
                $data->groupids = implode(',', $data->groupids);
            }

            // clean "timefinish"
            if (empty($data->timefinish) || empty($data->timefinish['enabled'])) {
                $data->timefinish = 0;
            } else {
                $data->timefinish = array_filter($data->timefinish, 'intval');
                $data->timefinish = mktime($data->timefinish['hour'],
                                           $data->timefinish['minute'],
                                           0, // seconds
                                           $data->timefinish['month'],
                                           $data->timefinish['day'],
                                           $data->timefinish['year']);
            }

            // prepare "reader_message" record
            $message = (object)array(
                'id'            => $messageid,
                'readerid'      => $PAGE->cm->instance,
                'teacherid'     => $USER->id,
                'groupids'      => $data->groupids,
                'messagetext'   => $data->message['text'],
                'messageformat' => $data->message['format'],
                'timefinish'    => $data->timefinish,
                'timemodified'  => time(),
            );

            // add/update "reader_message" record
            if ($message->id) {
                $DB->update_record('reader_messages', $message);
            } else {
                $message->id = $DB->insert_record('reader_messages', $message);
            }
        }

        // set the url for the form
        $url = $this->page->url;
        $params = $url->params();
        $params['id'] = $this->reader->cm->id;
        $params['tab'] = $this->get_tab();
        $params['mode'] = mod_reader::get_mode('admin/users');
        $url->params($params);

        // initialize the form
        $mform = new mod_reader_admin_users_sendmessage_form($url->out(false));

        // populate the form, if necessary
        if ($messageid && $action=='editmessage') {
            $params = array('id' => $messageid, 'readerid' => $PAGE->cm->instance);
            if ($message = $DB->get_record('reader_messages', $params)) {
                $mform->set_data($message);
            }
        } else {
            $mform->clear_all_values();
        }

        $mform->display();
    }
}

