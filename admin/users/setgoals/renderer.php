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
 * mod/reader/admin/users/setgoals/renderer.php
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
 * mod_reader_admin_users_setgoals_renderer
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_users_setgoals_renderer extends mod_reader_admin_users_renderer {

    public $mode = 'setgoals';

    /**
     * get_tab
     *
     * @return integer tab id
     */
    public function get_tab() {
        return self::TAB_USERS_SETGOALS;
    }

    /**
     * render_page
     *
     * @return string HTML output to display navigation tabs
     */
    public function render_page() {
        global $CFG, $DB, $PAGE, $USER;
        require_once($CFG->dirroot.'/mod/reader/admin/users/setgoals/form.php');

        if ($cancel = optional_param('cancel', '', PARAM_ALPHA)) {
            $data = null;
            $action = '';
        } else {
            $data = data_submitted();
            $action = optional_param('action', '', PARAM_ALPHA);
        }

        // process incoming data, if necessary
        if ($data) { //  && empty($data->addlevelgoals) && empty($data->addgroupgoals)
            if ($ids = $DB->get_records('reader_goals', array('readerid' => $this->reader->id), 'id', 'id, readerid')) {
                $ids = array_keys($ids);
            } else {
                $ids = array();
            }
            if (isset($data->defaultgoal)) {
                $goal = $data->defaultgoal['goal'];
                $enabled = $data->defaultgoal['enabled'];
                $this->add_goal($ids, $enabled, 1, $goal);
            }
            if (isset($data->levelgoal['goal'])) {
                foreach ($data->levelgoal['goal'] as $i => $goal) {
                    $level = $data->levelgoal['level'][$i];
                    $enabled = $data->levelgoal['enabled'][$i];
                    $this->add_goal($ids, $enabled, 1, $goal, 1, $level);
                }
            }
            if (isset($data->groupgoal['goal'])) {
                foreach ($data->groupgoal['goal'] as $i => $goal) {
                    $level = $data->groupgoal['level'][$i];
                    $groupid = $data->groupgoal['groupid'][$i];
                    $enabled = $data->groupgoal['enabled'][$i];
                    $this->add_goal($ids, $enabled, 1, $goal, 0, $level, 1, $groupid);
                }
            }
            if ($ids) {
                // delete any unsed $ids from reader_goals
                $DB->delete_records_list('reader_goals', 'id', $ids);
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
        $form = new mod_reader_admin_users_setgoals_form($url->out(false), $this->reader);

        // populate the form fields
        $form->set_data($form->get_goals());

        // populate the form, if necessary

        $form->display();
    }

    function add_goal(&$ids, $enabled, $requiregoal=0, $goal=0, $requirelevel=0, $level=0, $requiregroupid=0, $groupid=0) {
        global $DB;

        // clean incoming data
        $goal    = clean_param($goal,    PARAM_INT);
        $level   = clean_param($level,   PARAM_INT);
        $groupid = clean_param($groupid, PARAM_INT);
        $enabled = clean_param($enabled, PARAM_INT);

        // verify incoming data
        if (! $enabled) {
            return false;
        }
        if ($requiregoal && ! $goal) {
            return false;
        }
        if ($requirelevel && ! $level) {
            return false;
        }
        if ($requiregroupid && ! $groupid) {
            return false;
        }

        // add/update "reader_goals" record
        $goal = (object)array(
            'readerid' => $this->reader->id,
            'groupid'  => $groupid,
            'level'    => $level,
            'goal'     => $goal,
            'timemodified' => time()
        );
        if ($id = array_shift($ids)) {
            $goal->id = $id;
            $DB->update_record('reader_goals', $goal);
        } else {
            $goal->id = $DB->insert_record('reader_goals', $goal);
        }
    }
}
