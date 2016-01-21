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
 * mod/reader/admin/users/setrates_form.php
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
require_once($CFG->dirroot.'/mod/reader/locallib.php');

/**
 * mod_reader_admin_users_setrates_form
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_users_setrates_form extends moodleform {

    /**
     * maximum number of attempts in rate definitions
     */
    const MAX_ATTEMPTS  = 100;

    /**
     * maximum allowde reader level
     */
    const MAX_LEVEL     = 10;

    /**
     * number of new rate definitions added to the settings screen at once
     */
    const ADD_INCREMENT = 1;

    protected $rates = array();
    protected $countlevelrates = 0;
    protected $countgrouprates = 0;

    protected $RATE_TYPES = array(
        mod_reader::RATE_MAX_QUIZ_ATTEMPT => 'maxquizattemptrate',
        mod_reader::RATE_MIN_QUIZ_ATTEMPT => 'minquizattemptrate',
        mod_reader::RATE_MAX_QUIZ_FAILURE => 'maxquizfailurerate'
    );

    protected $ACTION_TYPES = array(
        mod_reader::ACTION_DELAY_QUIZZES => 'delayquizattempts', // temporarily delay
        mod_reader::ACTION_BLOCK_QUIZZES => 'blockquizattempts', // permanently block
        mod_reader::ACTION_EMAIL_STUDENT => 'sendemailtostudent',
        mod_reader::ACTION_EMAIL_TEACHER => 'sendemailtoteacher'
    );

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
    function mod_reader_admin_users_setrates_form($action=null, $reader=null, $method='post', $target='', $attributes=null, $editable=true) {
        $this->fetch_rates($reader);
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

        // cache options for form fields
        $leveloptions = array();
        for ($i=1; $i<=self::MAX_LEVEL; $i++) {
            $leveloptions[$i] = get_string('leveli', $plugin, $i);
        }


        $rateoptions = array();
        foreach ($this->RATE_TYPES as $type => $name) {
            $rateoptions[$type] = get_string($name, $plugin);
        }

        $durationoptions = array('optional' => 0, 'defaultunit' => 3600);

        $attemptsoptions = array();
        for ($i=0; $i<=self::MAX_ATTEMPTS; $i++) {
            if ($i==1) {
                $attemptsoptions[$i] = get_string('oneattempt', $plugin);
            } else {
                $attemptsoptions[$i] = get_string('numattempts', $plugin, $i);
            }
        }

        $actionoptions = array();
        foreach ($this->ACTION_TYPES as $type => $name) {
            $actionoptions[$type] = get_string($name, $plugin);
        }

        // cache language strings
        $str = (object)array(
            'rategroup'  => get_string('rategroup',  $plugin),
            'ratelevel'  => get_string('ratelevel',  $plugin),
            'ratetype'   => get_string('ratetype',   $plugin),
            'rate'       => get_string('rate',       $plugin),
            'attempts'   => get_string('attempts',   $plugin),
            'induration' => get_string('induration', $plugin),
            'duration'   => get_string('duration',   $plugin),
            'rateaction' => get_string('rateaction', $plugin)
        );
        $str->duration = " $str->duration "; // add white space

        $name = 'setrates_description';
        $mform->addElement('static', $name, '', get_string($name, $plugin));

        //-----------------------------------------------------------------------------
        $name = 'defaultrates';
        $this->add_header($mform, $plugin, $name);
        //-----------------------------------------------------------------------------

        foreach ($rateoptions as $type => $text) {
            $elements = array();
            $elements[] = $mform->createElement('select',   $name."[attempts][$type]", '', $attemptsoptions);
            $elements[] = $mform->createElement('static',   '', '', $str->induration);
            $elements[] = $mform->createElement('duration', $name."[duration][$type]", '', $durationoptions);
            $this->define_action_elements($elements, $mform, $name, $actionoptions, $type);
            $mform->addGroup($elements, $this->RATE_TYPES[$type], $text, null, false);

            $mform->setType($name."[attempts][$type]",           PARAM_INT);
            $mform->setType($name."[duration][$type][number]",   PARAM_FLOAT);
            $mform->setType($name."[duration][$type][timeunit]", PARAM_INT);
            $this->set_action_param_types($mform, $name, $actionoptions, $type);
            $mform->addHelpButton($this->RATE_TYPES[$type], $this->RATE_TYPES[$type], $plugin);
        }

        //-----------------------------------------------------------------------------
        // level rates
        //-----------------------------------------------------------------------------

        $this->repeat_rate_elements(
            $mform, 'levelrates', $plugin, $str,
            null, $leveloptions, $rateoptions,
            $attemptsoptions, $durationoptions, $actionoptions
        );

        //-----------------------------------------------------------------------------
        // group rates
        //-----------------------------------------------------------------------------

        if ($groupoptions = $this->get_group_options($course)) {
            $leveloptions = array(0 => get_string('alllevels', $plugin)) + $leveloptions;
            $this->repeat_rate_elements(
                $mform, 'grouprates', $plugin, $str,
                $groupoptions, $leveloptions, $rateoptions,
                $attemptsoptions, $durationoptions, $actionoptions
            );
        }

        $this->add_action_buttons();
    }

    /**
     * define_action_elements
     *
     * @param object $mform
     * @param string $name
     * @param array  $options
     * @param array  $elements
     */
    protected function define_action_elements(&$elements, $mform, $name, $actionoptions, $i=null, $separator=null) {
        if ($separator===null) {
            $separator = html_writer::empty_tag('br');
        }
        foreach ($actionoptions as $type => $text) {
            $elementname = $name."[action][$type]";
            if ($i===null) {
                $elementname = $name."[action][$type]";
            } else {
                $elementname = $name."[action][$type][$i]";
            }
            if ($separator) {
                $elements[] = $mform->createElement('static', '', '', $separator);
            }
            $elements[] = $mform->createElement('checkbox', $elementname, '', $text);
        }
    }

    /**
     * set_action_param_types
     *
     * @param object  $mform
     * @param string  $name
     * @param array   $actionoptions
     * @param integer $i
     */
    protected function set_action_param_types($mform, $name, $actionoptions, $i) {
        foreach ($actionoptions as $type => $text) {
            $mform->setType($name."[action][$type][$i]", PARAM_INT);
        }
    }

    /**
     * set_action_data
     *
     * @param array   $data
     * @param string  $name
     * @param object  $rate
     * @param integer $i
     */
    protected function set_action_data(&$data, $name, $rate, $i) {
        foreach (array_keys($this->ACTION_TYPES) as $type) {
            if (! isset($data[$name]['action'][$type])) {
                $data[$name]['action'][$type] = array();
            }
            $data[$name]['action'][$type][$i] = (($rate->action & $type) ? 1 : 0);
        }
    }

    /**
     * set_data
     *
     * @param stdClass|array $default_values object or array of default values
     */
    public function set_data($rates) {

        $data = array();
        $l = 0; // level index
        $g = 0; // group index
        foreach ($rates as $id => $rate) {

            $groupid = $rate->groupid;
            $level = $rate->level;

            if ($groupid==0 && $level==0) {
                $name = 'defaultrates';
                if (empty($data[$name])) {
                    $data[$name] = array('attempts' => array(), 'duration' => array(), 'action' => array());
                }
                $type = $rate->type;
                $data[$name]['attempts'][$type] = $rate->attempts;
                $data[$name]['duration'][$type] = $rate->duration;
                $this->set_action_data($data, $name, $rate, $type);

            } else if ($groupid==0) {
                $name = 'levelrates';
                if (empty($data[$name])) {
                    $data[$name] = array('level' => array(), 'type' => array(), 'attempts' => array(), 'duration' => array(), 'action' => array());
                }
                $data[$name]['level'][$l]    = $level;
                $data[$name]['type'][$l]     = $rate->type;
                $data[$name]['attempts'][$l] = $rate->attempts;
                $data[$name]['duration'][$l] = $rate->duration;
                $this->set_action_data($data, $name, $rate, $l);
                $l++;
            } else {
                $name = 'grouprates';
                if (empty($data[$name])) {
                    $data[$name] = array('groupid' => array(), 'level' => array(), 'type' => array(), 'attempts' => array(), 'duration' => array(), 'action' => array());
                }
                $data[$name]['groupid'][$g]  = $groupid;
                $data[$name]['level'][$g]    = $level;
                $data[$name]['type'][$g]     = $rate->type;
                $data[$name]['attempts'][$g] = $rate->attempts;
                $data[$name]['duration'][$g] = $rate->duration;
                $this->set_action_data($data, $name, $rate, $g);
                $g++;
            }
        }
        parent::set_data($data);
    }

    /**
     * get_rates
     */
    public function get_rates() {
        return $this->rates;
    }

    /**
     * get_group_options
     *
     * @param  object  $course
     * @return mixed   array of groups used in this course, or FALSE if no groups are available
     */
    protected function get_group_options($course) {
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
     * fetch_rates
     *
     * @uses $DB
     * @param object $reader
     * @return void, but may update $this->rates, $this->coutlevelrates, and $this->countgrouprates
     */
    protected function fetch_rates($reader) {
        global $DB;
        if ($rates = $DB->get_records('reader_rates', array('readerid' => $reader->id), 'groupid, level')) {
            foreach ($rates as $rate) {
                if ($rate->groupid) {
                    $this->countgrouprates++;
                } else if ($rate->level) {
                    $this->countlevelrates++;
                }
                $this->rates[] = $rate;
            }
        }
    }

    /**
     * get_countlevelrates
     */
    protected function get_countlevelrates() {
        return $this->countlevelrates;
    }

    /**
     * get_countgrouprates
     */
    protected function get_countgrouprates() {
        return $this->countgrouprates;
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
        $mform->addElement('header', $name.'section', $label);
        if (method_exists($mform, 'setExpanded')) {
            $mform->setExpanded($name.'section', $expanded);
        }
    }

    /**
     * repeat_rate_elements
     *
     * @param object  $mform
     * @param string  $name
     * @param string  $plugin
     * @param object  $str
     * @param array   $groupoptions
     * @param array   $leveloptions
     * @param array   $rateoptions
     * @param array   $attemptsoptions
     * @param array   $durationoptions
     * @param array   $actionoptions
     * @return void, but will update $mform
     */
    protected function repeat_rate_elements($mform, $name, $plugin, $str,
                                            $groupoptions, $leveloptions, $rateoptions,
                                            $attemptsoptions, $durationoptions, $actionoptions) {

        $this->add_header($mform, $plugin, $name);

        $rate = array(
            $mform->createElement('select', $name.'[attempts]', '', $attemptsoptions),
            $mform->createElement('static', '', '', $str->induration),
            $mform->createElement('duration', $name.'[duration]', '', $durationoptions),
        );

        $action = array();
        $this->define_action_elements($action, $mform, $name, $actionoptions, null, '');

        $elements = array();
        if ($groupoptions) {
            $elements[] = $mform->createElement('select', $name.'[groupid]', $str->rategroup, $groupoptions);
        }
        $elements[] = $mform->createElement('select', $name.'[level]',  $str->ratelevel,  $leveloptions);
        $elements[] = $mform->createElement('select', $name.'[type]',   $str->ratetype,   $rateoptions);
        $elements[] = $mform->createElement('group',  $name.'[rate]',   $str->rate,       $rate,   null,    false);
        $elements[] = $mform->createElement('group',  $name.'[action]', $str->rateaction, $action, '<br />', false);

        if (self::ADD_INCREMENT==1) {
            $buttontext = get_string('addonemorerate', $plugin);
        } else {
            $buttontext = get_string('addmorerates', $plugin);
        }

        $count = 'get_count'.$name; // get_countlevelrates OR get_grouplevelrates
        $count = $this->$count();
        $count = $this->repeat_elements($elements, $count, array(), 'count'.$name, 'add'.$name, self::ADD_INCREMENT, $buttontext, true);

        // remove addlevelrates button, if it is not necessary
        //if ($count >= self::MAX_LEVEL) {
        //    $mform->removeElement('add'.$name);
        //}

        for ($i=0; $i<$count; $i++) {
            if ($groupoptions) {
                $mform->setType($name."[groupid][$i]", PARAM_INT);
                $mform->addHelpButton($name."[groupid][$i]", 'rategroup', $plugin);
            }
            $mform->setType($name."[level][$i]", PARAM_INT);
            $mform->setType($name."[type][$i]", PARAM_INT);
            $mform->setType($name."[attempts][$i]", PARAM_INT);
            $mform->setType($name."[duration][$i][number]", PARAM_FLOAT);
            $mform->setType($name."[duration][$i][timeunit]", PARAM_INT);
            $this->set_action_param_types($mform, $name, $actionoptions, $i);

            $mform->addHelpButton($name."[level][$i]",  'ratelevel',  $plugin);
            $mform->addHelpButton($name."[type][$i]",   'ratetype',   $plugin);
            $mform->addHelpButton($name."[rate][$i]",   'rate',       $plugin);
            $mform->addHelpButton($name."[action][$i]", 'rateaction', $plugin);
        }
    }
}
