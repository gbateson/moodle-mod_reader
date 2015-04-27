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
 * mod/reader/admin/users/setdelays/renderer.php
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
 * mod_reader_admin_users_setdelays_renderer
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_users_setdelays_renderer extends mod_reader_admin_users_renderer {

    /**
     * render_page
     *
     * @return string HTML output to display navigation tabs
     */
    public function render_page() {
        global $CFG, $DB, $PAGE, $USER;
        require_once($CFG->dirroot.'/mod/reader/admin/users/setdelays/form.php');

        if ($cancel = optional_param('cancel', '', PARAM_ALPHA)) {
            $data = null;
            $action = '';
        } else {
            $data = data_submitted();
            $action = optional_param('action', '', PARAM_ALPHA);
        }

        // process incoming data, if necessary
        if ($data) { //  && empty($data->addleveldelays) && empty($data->addgroupdelays)
            if ($ids = $DB->get_records('reader_delays', array('readerid' => $this->reader->id), 'id', 'id, readerid')) {
                $ids = array_keys($ids);
            } else {
                $ids = array();
            }
            if (isset($data->defaultdelay)) {
                $this->add_delay(array_shift($ids), $data->defaultdelay);
            }
            if (isset($data->leveldelay['delay'])) {
                foreach ($data->leveldelay['delay'] as $i => $delay) {
                    if ($level = $data->leveldelay['level'][$i]) {
                        $this->add_delay(array_shift($ids), $delay, $level);
                    }
                }
            }
            if (isset($data->groupdelay['delay'])) {
                foreach ($data->groupdelay['delay'] as $i => $delay) {
                    if ($groupid = $data->groupdelay['groupid'][$i]) {
                        $level = $data->groupdelay['level'][$i];
                        $this->add_delay(array_shift($ids), $delay, $level, $groupid);
                    }
                }
            }
            if ($ids) {
                // delete any unsed $ids from reader_delays
                $DB->delete_records_list('reader_delays', 'id', $ids);
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
        $form = new mod_reader_admin_users_setdelays_form($url->out(false), $this->reader);

        // populate the form fields
        $form->set_data($form->get_delays());

        // populate the form, if necessary

        $form->display();
    }

    function add_delay($id, $delay, $level=0, $groupid=0) {
        global $DB;
        if (empty($delay['enabled'])) {
            return false;
        }
        $delay = (object)array(
            'readerid' => $this->reader->id,
            'groupid'  => $groupid,
            'level'    => $level,
            'delay'    => intval($delay['timeunit'] * $delay['number']),
            'timemodified' => time()
        );
        if ($id) {
            $delay->id = $id;
            $DB->update_record('reader_delays', $delay);
        } else {
            $delay->id = $DB->insert_record('reader_delays', $delay);
        }
    }
}
