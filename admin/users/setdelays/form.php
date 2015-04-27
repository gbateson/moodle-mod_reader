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
 * mod/reader/admin/users/setdelays_form.php
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
require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * mod_reader_admin_users_setdelays_form
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_users_setdelays_form extends moodleform {

    const MAX_LEVEL = 10;
    const ADD_INCREMENT = 5;

    protected $delays = null;
    protected $countleveldelays = 0;
    protected $countgroupdelays = 0;

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
    function mod_reader_admin_users_setdelays_form($action=null, $reader=null, $method='post', $target='', $attributes=null, $editable=true) {
        $this->fetch_delays($reader);
        parent::moodleform($action, $reader, $method, $target, $attributes, $editable);
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
        $buttontext = get_string('addmoredelays', $plugin);

        // cache level options menu
        $leveloptions = array();
        for ($i=1; $i<=self::MAX_LEVEL; $i++) {
            $leveloptions[$i] = get_string('leveldelay', $plugin, $i);
        }

        // cache duration options array
        $durationoptions = array('optional' => 1, 'defaultunit' => 3600);

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, ($course->groupmode==0 ? 'alllevels' : 'allgroupsandlevels'));
        //-----------------------------------------------------------------------------

        $name = 'defaultdelay';
        $label = get_string($name, $plugin);
        $mform->addElement('duration', $name, $label, $durationoptions);

        //-----------------------------------------------------------------------------
        $this->add_header($mform, $plugin, 'specificlevels');
        //-----------------------------------------------------------------------------

        $name = 'leveldelay';
        $elements   = array(
            $mform->createElement('select',   $name.'[level]', '', $leveloptions),
            $mform->createElement('duration', $name.'[delay]', '', $durationoptions),
        );
        $elements = array(
            $mform->createElement('group', 'leveldelays', '', $elements, ' ', false)
        );

        $options = array(
            $name.'[level]'    => array('type' => PARAM_INT),
            $name.'[number]'   => array('type' => PARAM_FLOAT),
            $name.'[timeunit]' => array('type' => PARAM_INT),
            $name.'[enabled]'  => array('type' => PARAM_INT),
        );

        $count = $this->get_countleveldelays();
        $count = $this->repeat_elements($elements, $count, $options,
                                        'countleveldelays', 'addleveldelays',
                                        self::ADD_INCREMENT, $buttontext, true);

        // we must set the disabled conditions ourselves
        // because $this->repeat_elements(), in "lib/formslib.php",
        // does not adjust the name of the "dependentOn" form element
        for ($i=0; $i<$count; $i++) {
            $dependenton = $name."[delay][$i][enabled]";
            $mform->disabledIf($name."[level][$i]",    $dependenton);
            $mform->disabledIf($name."[delay][$i][number]",   $dependenton);
            $mform->disabledIf($name."[delay][$i][timeunit]", $dependenton);
        }

        // remove addleveldelays button, if it is not necessary
        if ($count >= self::MAX_LEVEL) {
            $mform->removeElement('addleveldelays');
        }

        // remove any superfluous dependencies on leveldelay[enabled]
        if (isset($mform->_dependencies[$name.'[delay][enabled]'])) {
            unset($mform->_dependencies[$name.'[delay][enabled]']);
        }

        if ($groupoptions = $this->get_group_options($course)) {
            //-----------------------------------------------------------------------------
            $this->add_header($mform, $plugin, 'specificgroups');
            //-----------------------------------------------------------------------------

            $name = 'groupdelay';
            $leveloptions = array(0 => get_string('defaultdelay', $plugin)) + $leveloptions;

            $elements = array(
                $mform->createElement('select',   $name.'[groupid]', '', $groupoptions),
                $mform->createElement('select',   $name.'[level]',   '', $leveloptions),
                $mform->createElement('duration', $name.'[delay]',   '', $durationoptions),
            );
            $elements = array(
                $mform->createElement('group', 'groupdelays', '', $elements, ' ', false)
            );

            $options = array(
                $name.'[groupid]'  => array('type' => PARAM_INT),
                $name.'[level]'    => array('type' => PARAM_INT),
                $name.'[number]'   => array('type' => PARAM_FLOAT),
                $name.'[timeunit]' => array('type' => PARAM_INT),
                $name.'[enabled]'  => array('type' => PARAM_INT),
            );

            $count = $this->get_countgroupdelays();
            $count = $this->repeat_elements($elements, $count, $options,
                                            'countgroupdelays', 'addgroupdelays',
                                            self::ADD_INCREMENT, $buttontext, true);

            // we must set the disabled conditions ourselves
            // because $this->repeat_elements(), in "lib/formslib.php",
            // does not adjust the name of the "dependentOn" form element
            for ($i=0; $i<$count; $i++) {
                $dependenton = $name."[delay][$i][enabled]";
                $mform->disabledIf($name."[groupid][$i]",         $dependenton);
                $mform->disabledIf($name."[level][$i]",           $dependenton);
                $mform->disabledIf($name."[delay][$i][number]",   $dependenton);
                $mform->disabledIf($name."[delay][$i][timeunit]", $dependenton);
            }

            // remove any superfluous dependencies on groupdelay[enabled]
            if (isset($mform->_dependencies['groupdelay[delay][enabled]'])) {
                unset($mform->_dependencies['groupdelay[delay][enabled]']);
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
    function set_data($delays) {
        // $delays[$groupid][$level] = $delay;
        $data = array();
        $l = 0; // level index
        $g = 0; // group index
        foreach ($delays as $groupid => $levels) {
            foreach ($levels as $level => $delay) {
                if ($groupid==0 && $level==0) {
                    $name = 'defaultdelay';
                    $data[$name] = $delay;
                } else if ($groupid==0) {
                    $name = 'leveldelay';
                    if (empty($data[$name])) {
                        $data[$name] = array('level' => array(), 'delay' => array());
                    }
                    $data[$name]['level'][$l] = $level;
                    $data[$name]['delay'][$l] = $delay;
                    $l++;
                } else {
                    $name = 'groupdelay';
                    if (empty($data[$name])) {
                        $data[$name] = array('groupid' => array(), 'level' => array(), 'delay' => array());
                    }
                    $data[$name]['groupid'][$g] = $groupid;
                    $data[$name]['level'][$g] = $level;
                    $data[$name]['delay'][$g] = $delay;
                    $g++;
                }
            }
        }
        parent::set_data($data);
    }

    /**
     * get_next_delay
     *
     * @param integer $groupid
     * @param integer $level
     * @return void, but may update $groupid and $level
     */
    function get_next_delay(&$currentgroupid, &$currentlevel) {
        if (isset($this->delays[$currentgroupid][$currentlevel])) {
            $delay = $this->delays[$currentgroupid][$currentlevel];
        } else {
            $delay = 0;
        }
        foreach ($this->delays as $groupid => $levels) {
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
                return $delay;
            }
        }
        return $delay;
    }

    /**
     * set_duration_values
     *
     * @param object  $mform
     * @param string  $name of form element
     * @param integer $seconds
     * @return void, but may update $mform
     */
    function set_duration_values($mform, $name, $seconds) {
        if (! $mform->elementExists($name)) {
            return false;
        }
        $duration = $mform->getElement($name);
        if (! $duration->getType()=='duration') {
            return false;
        }

        list($number, $timeunit) = $duration->seconds_to_unit($seconds);

        $elements = $duration->getElements();
        foreach ($elements as $element) {
            switch ($element->getName()) {
                case 'number':   $element->setValue($number);   break;
                case 'timeunit': $element->setValue($timeunit); break;
                case 'enabled':  ($number==0 ? 0 : 1);          break;
            }
        }
        $duration->setElements($elements);
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
     * fetch_delays
     *
     * @uses $DB
     * @param object $reader
     * @return void, but may update $this->delays, $this->coutleveldelays, and $this->countgroupdelays
     */
    function fetch_delays($reader) {
        global $DB;

        $this->delays = array();
        $this->countleveldelays = 0;
        $this->countgroupdelays = 0;

        if ($records = $DB->get_records('reader_delays', array('readerid' => $reader->id), 'groupid, level')) {
            foreach ($records as $id => $record) {
                if (empty($this->delays[$record->groupid])) {
                    $this->delays[$record->groupid] = array();
                }
                if ($record->groupid) {
                    $this->countgroupdelays++;
                } else {
                    $this->countleveldelays++;
                }
                $this->delays[$record->groupid][$record->level] = $record->delay;
            }
        }
    }

    /**
     * get_delays
     */
    function get_delays() {
        return $this->delays;
    }

    /**
     * get_countleveldelays
     */
    function get_countleveldelays() {
        return $this->countleveldelays;
    }

    /**
     * get_countgroupdelays
     */
    function get_countgroupdelays() {
        return $this->countgroupdelays;
    }
}
