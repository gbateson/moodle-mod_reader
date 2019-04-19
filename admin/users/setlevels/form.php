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
 * mod/reader/admin/users/setlevels/form.php
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
require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * mod_reader_admin_users_setlevels_form
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_users_setlevels_form extends moodleform {

    const MAX_LEVEL = 10;

    /**
     * definition
     *
     * @todo Finish documenting this function
     */
    function definition() {
        global $DB;

        $reader = $this->_customdata;
        $course = $this->_customdata->course;
        $mform  = $this->_form;
        $plugin = 'mod_reader';

        // levels menu
        $leveloptions = array();
        for ($i=0; $i<=self::MAX_LEVEL; $i++) {
            if ($i==0) {
                $leveloptions[$i] = get_string('none');
            } else {
                $leveloptions[$i] = get_string('leveli', $plugin, $i);
            }
        }

        $name = 'setlevels_description';
        $mform->addElement('static', $name, '', get_string($name, $plugin));

        if ($groupoptions = $this->get_group_options($course, true)) {
            $name = 'group';
            $label = get_string($name);
            $mform->addElement('select', $name, $label, $groupoptions);
            $mform->setType($name, PARAM_INT);
        }

        $name = 'startlevel';
        $label = get_string($name, $plugin);
        $elements = array(
            $mform->createElement('select', 'level', '', $leveloptions),
            $mform->createElement('checkbox', 'enabled', ''),
            $mform->createElement('static', 'enable', '', get_string('enable')),
        );
        $mform->addElement('group', $name, $label, $elements, ' ');
        $mform->disabledIf($name.'[level]', $name.'[enabled]');
        $mform->setType($name, PARAM_INT);

        $name = 'currentlevel';
        $label = get_string($name, $plugin);
        $elements = array(
            $mform->createElement('select', 'level', '', $leveloptions),
            $mform->createElement('checkbox', 'enabled', ''),
            $mform->createElement('static', 'enable', '', get_string('enable')),
        );
        $mform->addElement('group', $name, $label, $elements, ' ');
        $mform->disabledIf($name.'[level]', $name.'[enabled]');
        $mform->setType($name, PARAM_INT);

        $name = 'stoplevel';
        $label = get_string($name, $plugin);
        $elements = array(
            $mform->createElement('select', 'level', '', $leveloptions),
            $mform->createElement('checkbox', 'enabled', ''),
            $mform->createElement('static', 'enable', '', get_string('enable')),
        );
        $mform->addElement('group', $name, $label, $elements, ' ');
        $mform->disabledIf($name.'[level]', $name.'[enabled]');
        $mform->setType($name, PARAM_INT);

        $name = 'allowpromotion';
        $label = get_string($name, $plugin);
        $elements = array(
            $mform->createElement('selectyesno', 'allow', ''),
            $mform->createElement('checkbox', 'enabled', ''),
            $mform->createElement('static', 'enable', '', get_string('enable')),
        );
        $mform->addElement('group', $name, $label, $elements, ' ');
        $mform->disabledIf($name.'[allow]', $name.'[enabled]');
        $mform->setType($name, PARAM_INT);

        $this->add_action_buttons();
    }

    /**
     * add_header
     *
     * @param object  $mform
     * @param string  $component
     * @param string  $name of string
     * @param boolean $expanded (optional, default=TRUE)
     * @return void, but will update $mform
     */
    protected function add_header($mform, $component, $name, $expanded=true) {
        $label = get_string($name, $component);
        $mform->addElement('header', $name, $label);
        if (method_exists($mform, 'setExpanded')) {
            $mform->setExpanded($name, $expanded);
        }
    }

    /**
     * get_group_options
     *
     * @param  object   $course
     * @param  boolean  $all, TRUE if "All" option allowed, FALSE otherwise
     * @return mixed   array of groups used in this course, or FALSE if no groups are available
     */
    function get_group_options($course, $all=false) {
        if (empty($course->groupmode)) {
            return false;
        }
        $groups = groups_get_all_groups($course->id);
        if (empty($groups)) {
            return false;
        }
        $groupoptions = array();
        if ($all) {
            $groupoptions[0] = get_string('all');
        }
        foreach ($groups as $groupid => $group) {
            $groupoptions[$groupid] = $group->name;
        }
        return $groupoptions;
    }
}
