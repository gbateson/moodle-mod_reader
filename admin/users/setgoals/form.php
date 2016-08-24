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
 * mod/reader/admin/users/setgoals_form.php
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
 * mod_reader_admin_users_setgoals_form
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_users_setgoals_form extends moodleform {

    const MAX_LEVEL = 10;
    const ADD_INCREMENT = 5;

    protected $goals = null;
    protected $countlevelgoals = 0;
    protected $countgroupgoals = 0;

    /**
     * constructor
     *
     * @param mixed    $action
     * @param object   $reader a reader object
     * @param string   $method 'get' or 'post'
     * @param string   $target frame for form submission.
     * @param mixed    $attributes
     * @param boolean  $editable
     * @todo Finish documenting this function
     */
    function __construct($action=null, $reader=null, $method='post', $target='', $attributes=null, $editable=true) {
        $this->fetch_goals($reader);
        if (method_exists('moodleform', '__construct')) {
            // Moodle >= 3.1
            parent::__construct($action, $reader, $method, $target, $attributes, $editable);
        } else {
            // Moodle <= 3.0
            parent::moodleform($action, $reader, $method, $target, $attributes, $editable);
        }
    }

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

        // cache commonly used strings
        $plugin = 'mod_reader';
        $buttontext = get_string('addmoregoals', $plugin);

        // cache level options menu
        $leveloptions = array();
        for ($i=1; $i<=self::MAX_LEVEL; $i++) {
            $leveloptions[$i] = get_string('levelgoal', $plugin, $i);
        }

        // cache goal options array
        $goaloptions = array('size' => 8);

        $name = 'setgoals_description';
        $mform->addElement('static', $name, '', get_string($name, $plugin));

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'defaultgoals');
        //-----------------------------------------------------------------------------

        $name = 'defaultgoal';
        $label = get_string($name, $plugin);
        $elements   = array(
            $mform->createElement('text',     'goal',    '', $goaloptions),
            $mform->createElement('checkbox', 'enabled', ''),
            $mform->createElement('static',   'enable',  '', get_string('enable')),
        );
        $mform->addElement('group', $name, $label, $elements, ' ');

        $mform->disabledIf($name.'[goal]', $name.'[enabled]');
        $mform->setType($name.'[goal]', PARAM_INT);
        $mform->setType($name.'[enabled]', PARAM_INT);

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'levelgoals');
        //-----------------------------------------------------------------------------

        $name = 'levelgoal';
        $elements   = array(
            $mform->createElement('select',   $name.'[level]',   '', $leveloptions),
            $mform->createElement('text',     $name.'[goal]',    '', $goaloptions),
            $mform->createElement('checkbox', $name.'[enabled]', ''),
            $mform->createElement('static',   $name.'[enable]', '', get_string('enable')),
        );
        $elements = array(
            $mform->createElement('group', 'levelgoals', '', $elements, ' ', false)
        );

        $options = array(
            $name.'[level]'   => array('type' => PARAM_INT),
            $name.'[goal]'    => array('type' => PARAM_INT),
            $name.'[enabled]' => array('type' => PARAM_INT),
        );

        $count = $this->get_countlevelgoals();
        $count = $this->repeat_elements($elements, $count, $options,
                                        'countlevelgoals', 'addlevelgoals',
                                        self::ADD_INCREMENT, $buttontext, true);

        // we must set the disabled conditions ourselves
        // because $this->repeat_elements(), in "lib/formslib.php",
        // does not adjust the name of the "dependentOn" form element
        for ($i=0; $i<$count; $i++) {
            $dependenton = $name."[enabled][$i]";
            $mform->disabledIf($name."[level][$i]", $dependenton);
            $mform->disabledIf($name."[goal][$i]",  $dependenton);
            $mform->setType($name."[goal][$i]", PARAM_INT);
            $mform->setType($name."[enabled][$i]", PARAM_INT);
        }

        // remove addlevelgoals button, if it is not necessary
        if ($count >= self::MAX_LEVEL) {
            $mform->removeElement('addlevelgoals');
        }

        if ($groupoptions = $this->get_group_options($course)) {
            //-----------------------------------------------------------------------------
            $this->add_header($mform, $plugin, 'groupgoals');
            //-----------------------------------------------------------------------------

            $name = 'groupgoal';
            $leveloptions = array(0 => get_string('defaultgoal', $plugin)) + $leveloptions;

            $elements = array(
                $mform->createElement('select',   $name.'[groupid]', '', $groupoptions),
                $mform->createElement('select',   $name.'[level]',   '', $leveloptions),
                $mform->createElement('text',     $name.'[goal]',    '', $goaloptions),
                $mform->createElement('checkbox', $name.'[enabled]', ''),
                $mform->createElement('static',   $name.'[enable]', '', get_string('enable')),
            );
            $elements = array(
                $mform->createElement('group', 'groupgoals', '', $elements, ' ', false)
            );

            $options = array(
                $name.'[groupid]' => array('type' => PARAM_INT),
                $name.'[level]'   => array('type' => PARAM_INT),
                $name.'[goal]'    => array('type' => PARAM_INT),
                $name.'[enabled]' => array('type' => PARAM_INT),
            );

            $count = $this->get_countgroupgoals();
            $count = $this->repeat_elements($elements, $count, $options,
                                            'countgroupgoals', 'addgroupgoals',
                                            self::ADD_INCREMENT, $buttontext, true);

            // we must set the disabled conditions ourselves
            // because $this->repeat_elements(), in "lib/formslib.php",
            // does not adjust the name of the "dependentOn" form element
            for ($i=0; $i<$count; $i++) {
                $dependenton = $name."[enabled][$i]";
                $mform->disabledIf($name."[groupid][$i]", $dependenton);
                $mform->disabledIf($name."[level][$i]",   $dependenton);
                $mform->disabledIf($name."[goal][$i]",    $dependenton);
                $mform->setType($name."[goal][$i]", PARAM_INT);
                $mform->setType($name."[enabled][$i]", PARAM_INT);
            }
        }

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
     * set_data
     *
     * @param stdClass|array $default_values object or array of default values
     */
    function set_data($goals) {
        // $goals[$groupid][$level] = $goal;
        $data = array();
        $l = 0; // level index
        $g = 0; // group index
        foreach ($goals as $groupid => $levels) {
            foreach ($levels as $level => $goal) {
                $enabled = ($goal > 0);
                if ($groupid==0 && $level==0) {
                    $name = 'defaultgoal';
                    $data[$name] = array('goal'    => $goal,
                                         'enabled' => $enabled);
                } else if ($groupid==0) {
                    $name = 'levelgoal';
                    if (empty($data[$name])) {
                        $data[$name] = array('level'   => array(),
                                             'goal'    => array(),
                                             'enabled' => array());
                    }
                    $data[$name]['level'][$l]   = $level;
                    $data[$name]['goal'][$l]    = $goal;
                    $data[$name]['enabled'][$l] = $enabled;
                    $l++;
                } else {
                    $name = 'groupgoal';
                    if (empty($data[$name])) {
                        $data[$name] = array('groupid' => array(),
                                             'level'   => array(),
                                             'goal'    => array(),
                                             'enabled' => array());
                    }
                    $data[$name]['groupid'][$g] = $groupid;
                    $data[$name]['level'][$g]   = $level;
                    $data[$name]['goal'][$g]    = $goal;
                    $data[$name]['enabled'][$g] = $enabled;
                    $g++;
                }
            }
        }
        parent::set_data($data);
    }

    /**
     * get_next_goal
     *
     * @param integer $groupid
     * @param integer $level
     * @return void, but may update $groupid and $level
     */
    function get_next_goal(&$currentgroupid, &$currentlevel) {
        if (isset($this->goals[$currentgroupid][$currentlevel])) {
            $goal = $this->goals[$currentgroupid][$currentlevel];
        } else {
            $goal = 0;
        }
        foreach ($this->goals as $groupid => $levels) {
            if ($groupid < $currentgroupid) {
                continue; // skip previous group
            }
            if ($groupid > $currentgroupid) {
                $currentlevel = -1; // reset level
            }
            foreach (array_keys($levels) as $level) {
                if ($groupid==$currentgroupid && $level<=$currentlevel) {
                    continue; // skip previous and current level
                }
                $currentgroup = $groupid;
                $currentlevel = $level;
                return $goal;
            }
        }
        return $goal;
    }

    /**
     * get_group_options
     *
     * @param  object  $course
     * @return mixed   array of groups used in this course, or FALSE if no groups are available
     */
    function get_group_options($course) {
        if (empty($course->groupmode)) {
            return false;
        }
        $groups = groups_get_all_groups($course->id);
        if (empty($groups)) {
            return false;
        }
        $groupoptions = array();
        foreach ($groups as $groupid => $group) {
            $groupoptions[$groupid] = $group->name;
        }
        return $groupoptions;
    }

    /**
     * fetch_goals
     *
     * @uses $DB
     * @param object $reader
     * @return void, but may update $this->goals, $this->coutlevelgoals, and $this->countgroupgoals
     */
    function fetch_goals($reader) {
        global $DB;

        $this->goals = array();
        $this->countlevelgoals = 0;
        $this->countgroupgoals = 0;

        if ($records = $DB->get_records('reader_goals', array('readerid' => $reader->id), 'groupid, level')) {
            foreach ($records as $id => $record) {
                if (empty($this->goals[$record->groupid])) {
                    $this->goals[$record->groupid] = array();
                }
                if ($record->groupid) {
                    $this->countgroupgoals++;
                } else {
                    $this->countlevelgoals++;
                }
                $this->goals[$record->groupid][$record->level] = $record->goal;
            }
        }
    }

    /**
     * get_goals
     */
    function get_goals() {
        return $this->goals;
    }

    /**
     * get_countlevelgoals
     */
    function get_countlevelgoals() {
        return $this->countlevelgoals;
    }

    /**
     * get_countgroupgoals
     */
    function get_countgroupgoals() {
        return $this->countgroupgoals;
    }
}
