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
 * mod/reader/admin/users/setrates/renderer.php
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
require_once($CFG->dirroot.'/mod/reader/locallib.php');
require_once($CFG->dirroot.'/mod/reader/admin/users/renderer.php');

/**
 * mod_reader_admin_users_setrates_renderer
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_users_setrates_renderer extends mod_reader_admin_users_renderer {

    public $mode = 'setrates';

    /**
     * get_tab
     *
     * @return integer tab id
     */
    public function get_tab() {
        return self::TAB_USERS_SETRATES;
    }

    /**
     * render_page
     *
     * @return string HTML output to display navigation tabs
     */
    public function render_page() {
        global $CFG, $DB, $PAGE, $USER;
        require_once($CFG->dirroot.'/mod/reader/admin/users/setrates/form.php');

        if ($cancel = optional_param('cancel', '', PARAM_ALPHA)) {
            $data = null;
        } else {
            $data = data_submitted();
        }

        // process incoming data, if necessary
        if ($data) {

            if ($ids = $DB->get_records('reader_rates', array('readerid' => $this->reader->id), 'id', 'id, readerid')) {
                $ids = array_keys($ids);
            } else {
                $ids = array();
            }

            $actiontypes = array(
                mod_reader::ACTION_DELAY_QUIZZES,
                mod_reader::ACTION_BLOCK_QUIZZES,
                mod_reader::ACTION_EMAIL_STUDENT,
                mod_reader::ACTION_EMAIL_TEACHER
            );

            // add default rates
            $name = 'defaultrates';
            if (isset($data->$name) && is_array($data->$name)) {
                $rates = $data->$name;
                $this->add_rate($ids, $rates, mod_reader::RATE_MAX_QUIZ_ATTEMPT, $actiontypes);
                $this->add_rate($ids, $rates, mod_reader::RATE_MIN_QUIZ_ATTEMPT, $actiontypes);
                $this->add_rate($ids, $rates, mod_reader::RATE_MAX_QUIZ_FAILURE, $actiontypes);
            }

            // add level-specific rates
            $name = 'levelrates';
            if (isset($data->$name) && is_array($data->$name)) {
                $rates = $data->$name;
                foreach (array_keys($rates['level']) as $i) {
                    $this->add_rate($ids, $rates, $i, $actiontypes);
                }
            }

            // add group-specific rates
            $name = 'grouprates';
            if (isset($data->$name) && is_array($data->$name)) {
                $rates = $data->$name;
                foreach (array_keys($rates['groupid']) as $i) {
                    $this->add_rate($ids, $rates, $i, $actiontypes);
                }
            }

            // delete any unsed $ids from reader_rates
            if ($ids) {
                $DB->delete_records_list('reader_rates', 'id', $ids);
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
        $form = new mod_reader_admin_users_setrates_form($url->out(false), $this->reader);

        // populate the form fields
        $form->set_data($form->get_rates());

        // display the form
        $form->display();
    }

    /**
     * add_rate
     *
     * @uses $DB
     * @param xxx $ids (passed by reference)
     * @param xxx $rates
     * @param xxx $i
     * @param xxx $actiontypes
     * @todo Finish documenting this function
     */
    function add_rate(&$ids, $rates, $i, $actiontypes) {
        global $DB;

        // default values
        $groupid  = 0;
        $level    = 0;
        $type     = 0;
        $attempts = 0;
        $duration = 0;
        $action   = 0;

        if (array_key_exists('groupid', $rates)) {
            if (array_key_exists($i, $rates['groupid'])) {
                $groupid = $rates['groupid'][$i];
                $groupid = intval($groupid);
            }
        }

        if (array_key_exists('level', $rates)) {
            if (array_key_exists($i, $rates['level'])) {
                $level = $rates['level'][$i];
                $level = intval($level);
            }
        }

        if (array_key_exists('type', $rates)) {
            if (array_key_exists($i, $rates['type'])) {
                $type = $rates['type'][$i];
                $type = intval($type);
            }
        } else {
            $type = $i; // defaultrates
        }

        if (array_key_exists('attempts', $rates)) {
            if (array_key_exists($i, $rates['attempts'])) {
                $attempts = $rates['attempts'][$i];
                $attempts = intval($attempts);
            }
        }

        if (array_key_exists('duration', $rates)) {
            if (array_key_exists($i, $rates['duration'])) {
                $duration = $rates['duration'][$i];
                $duration = intval($duration['timeunit'] * $duration['number']);
            }
        }

        if (array_key_exists('action', $rates)) {
            $action = 0;
            foreach ($actiontypes as $actiontype) {
                if (empty($rates['action'][$actiontype])) {
                    continue;
                }
                if (empty($rates['action'][$actiontype][$i])) {
                    continue;
                }
                $action += $actiontype; // i.e. bit-wise AND
            }
        }

        if ($attempts && $duration && $action) {
            $rate = (object)array(
                'readerid' => $this->reader->id,
                'groupid'  => $groupid,
                'level'    => $level,
                'type'     => $type,
                'attempts' => $attempts,
                'duration' => $duration,
                'action'   => $action,
                'timemodified' => time()
            );
            if ($id = array_shift($ids)) {
                $rate->id = $id;
                $DB->update_record('reader_rates', $rate);
            } else {
                $rate->id = $DB->insert_record('reader_rates', $rate);
            }
        }
    }
}
