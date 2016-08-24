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
 * mod/reader/mod_form.php
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
require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/reader/lib.php');

/**
 * mod_reader_mod_form
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_mod_form extends moodleform_mod {

    /** size of numeric text boxes */
    const TEXT_NUM_SIZE = 10;

    /**
     * definition
     *
     * @uses $CFG
     * @uses $COURSE
     * @uses $DB
     * @todo Finish documenting this function
     */
    public function definition() {

        global $COURSE, $CFG, $DB, $PAGE;

        $plugin = 'mod_reader';
        $config = get_config($plugin);
        $strman = get_string_manager();

        $dateoptions = array('optional' => true);
        $textoptions = array('size' => self::TEXT_NUM_SIZE);

        $mform = $this->_form;

        //-----------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));
        //-----------------------------------------------------------------------------
        $name = 'name';
        $label = get_string($name);
        $mform->addElement('text', $name, $label, array('size'=>'64'));
        $mform->setType($name, (empty($CFG->formatstringstriptags) ? PARAM_CLEANHTML : PARAM_TEXT));
        $mform->addRule($name, null, 'required', null, 'client');

        $label = get_string('summary');
        if (method_exists($this, 'standard_intro_elements')) {
            $this->standard_intro_elements($label); // Moodle >= 2.9
        } else {
            $this->add_intro_editor(false, $label); // Moodle <= 2.8
        }

        //-----------------------------------------------------------------------------
        $mform->addElement('header', 'timinghdr', get_string('timing', 'form'));
        //-----------------------------------------------------------------------------

        $name = 'timeopen';
        $label = get_string('quizopen', 'quiz');
        $mform->addElement('date_time_selector', $name, $label, $dateoptions);
        if ($strman->string_exists('quizopenclose', 'quiz')) {
            $mform->addHelpButton($name, 'quizopenclose', 'quiz');
        }
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);

        $name = 'timeclose';
        $label = get_string('quizclose', 'quiz');
        $mform->addElement('date_time_selector', $name, $label, $dateoptions);
        if ($strman->string_exists('quizopenclose', 'quiz')) {
            $mform->addHelpButton($name, 'quizopenclose', 'quiz');
        }
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);

        $name = 'timelimit';
        $label = get_string($name, 'quiz');
        $mform->addElement('duration', $name, $label, array('optional' => true, 'step' => 60));
        $mform->addHelpButton($name, $name, 'quiz');
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);

        //-----------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('mainpagesettings', $plugin));
        //-----------------------------------------------------------------------------

        $name = 'bookcovers';
        $label = get_string($name, $plugin);
        $mform->addElement('selectyesno', $name, $label);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'showprogressbar';
        $label = get_string($name, $plugin);
        $mform->addElement('selectyesno', $name, $label);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'showpercentgrades';
        $label = get_string($name, $plugin);
        $mform->addElement('selectyesno', $name, $label);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'wordsorpoints';
        $label = get_string($name, $plugin);
        $options = array(0 => get_string('showwordcount', $plugin),
                         1 => get_string('showpoints', $plugin),
                         2 => get_string('showpointsandwordcount', $plugin));
        $mform->addElement('select', $name, $label, $options);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, $plugin);

        //-----------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('promotionsettings', $plugin));
        //-----------------------------------------------------------------------------

        $name = 'goal';
        $label = get_string('totalpointsgoal', $plugin);
        $mform->addElement('text', $name, $label, $textoptions);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, 'totalpointsgoal', $plugin);

        $name = 'minpassgrade';
        $label = get_string($name, $plugin);
        $mform->addElement('text', $name, $label, $textoptions);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'levelcheck';
        $label = get_string($name, $plugin);
        $mform->addElement('selectyesno', $name, $label);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'prevlevel';
        $label = get_string($name, $plugin);
        $mform->addElement('text', $name, $label, $textoptions);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addRule($name, null, 'required', null, 'client');
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'thislevel';
        $label = get_string($name, $plugin);
        $mform->addElement('text', $name, $label, $textoptions);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addRule($name, null, 'required', null, 'client');
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'nextlevel';
        $label = get_string($name, $plugin);
        $mform->addElement('text', $name, $label, $textoptions);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addRule($name, null, 'required', null, 'client');
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'stoplevel';
        $label = get_string($name, $plugin);
        $elements = array();
        $elements[] = $mform->createElement('text', $name, '', $textoptions);
        $elements[] = $mform->createElement('checkbox', $name.'force', '', get_string($name.'force', $plugin));
        $mform->addGroup($elements, $name.'elements', $label, ' ', false);
        //$this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->setType($name, PARAM_INT);
        $mform->setType($name.'force', PARAM_INT);
        $mform->addHelpButton($name.'elements', $name, $plugin);

        //-----------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('reportsettings', $plugin));
        //-----------------------------------------------------------------------------

        $name = 'ignoredate';
        $label = get_string($name, $plugin);
        $options = array('startyear' => 2002,
                         'stopyear'  => date('Y', time()),
                         'applydst'  => true);
        $mform->addElement('date_selector', $name, $label, $options);
        $mform->addHelpButton($name, $name, $plugin);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);

        $name = 'showreviewlinks';
        $label = get_string($name, $plugin);
        $mform->addElement('selectyesno', $name, $label);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'checkbox';
        $label = get_string($name, $plugin);
        $mform->addElement('selectyesno', $name, $label);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, $plugin);

        //-----------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('readerquizsettings', $plugin));
        //-----------------------------------------------------------------------------

        $name = 'usecourse';
        $label = get_string($name, $plugin);
        $options = get_courses();
        unset($options[SITEID]);
        foreach ($options as $option) {
            $options[$option->id] = $option->fullname;
        }
        $mform->addElement('select', $name, $label, $options);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT, $COURSE->id);
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'questionscores';
        $label = get_string($name, $plugin);
        $mform->addElement('selectyesno', $name, $label);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'bookinstances';
        $label = get_string($name, $plugin);
        $mform->addElement('selectyesno', $name, $label);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, $plugin);

        //-----------------------------------------------------------------------------
        $mform->addElement('header', 'security', get_string('security', 'form'));
        //-----------------------------------------------------------------------------

        $name = 'popup';
        $label = get_string($name, $plugin);
        $mform->addElement('selectyesno', $name, $label);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, $plugin);
        //$mform->addHelpButton($name, 'browsersecurity', 'quiz');

        $name = 'requirepassword';
        $label = get_string($name, 'quiz');
        $mform->addElement('passwordunmask', $name, $label);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_TEXT);
        $mform->addHelpButton($name, $name, 'quiz');

        $name = 'requiresubnet';
        $label = get_string($name, 'quiz');
        $mform->addElement('text', $name, $label);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_TEXT);
        $mform->addHelpButton($name, $name, 'quiz');

        // this setting just duplicates checkcheating?
        //$name = 'uniqueip';
        //$label = get_string($name, $plugin);
        //$mform->addElement('selectyesno', $name, $label);
        //$this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        //$mform->addHelpButton($name, $name, $plugin);

        $name = 'checkcheating';
        $label = get_string($name, $plugin);
        $options = array(0 => get_string('off', $plugin),
                         1 => get_string('anywhere', $plugin),
                         2 => get_string('adjoiningcomputers', $plugin));
        $mform->addElement('select', $name, $label, $options);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'notifycheating';
        $label = get_string($name, $plugin);
        $mform->addElement('selectyesno', $name, $label);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'cheatedmessage';
        $label = get_string($name, $plugin);
        $options = array('rows' => 5, 'cols' => 50);
        $mform->addElement('textarea', $name, $label, $options);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_TEXT);
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'clearedmessage';
        $label = get_string($name, $plugin);
        $options = array('rows' => 5, 'cols' => 50);
        $mform->addElement('textarea', $name, $label, $options);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_TEXT);
        $mform->addHelpButton($name, $name, $plugin);

        //-----------------------------------------------------------------------------
        $mform->addElement('header', 'gradeshdr', get_string('grades', 'grades'));
        //-----------------------------------------------------------------------------

        $name = 'maxgrade';
        $label = get_string($name, $plugin);
        $mform->addElement('text', $name, $label, $textoptions);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, $plugin);

        // Note: the min pass grade field must be called "gradepass" so that
        // it is recognized by edit_module_post_actions() in "course/modlib.php"
        // Also, FEATURE_GRADE_HAS_GRADE must be enabled in "mod/reader/lib.php"
        $name = 'gradepass';
        $label = get_string($name, 'grades');
        $mform->addElement('text', $name, $label, $textoptions);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, 'grades');
        $mform->disabledIf($name, 'maxgrade', 'eq', '');
        $mform->disabledIf($name, 'maxgrade', 'eq', '0');

        // Note: the grade category field must be called "gradecat" so that
        // it is recognized by edit_module_post_actions() in "course/modlib.php"
        // Also, FEATURE_GRADE_HAS_GRADE must be enabled in "mod/reader/lib.php"
        $name = 'gradecat';
        $label = get_string('gradecategoryonmodform', 'grades');
        $options = grade_get_categories_menu($PAGE->course->id);
        $mform->addElement('select', $name, $label, $options);
        $mform->addHelpButton($name, 'gradecategoryonmodform', 'grades');
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->disabledIf($name, 'maxgrade', 'eq', '');
        $mform->disabledIf($name, 'maxgrade', 'eq', '0');

        //-----------------------------------------------------------------------------
        // add standard elements, common to all modules
        //-----------------------------------------------------------------------------
        $this->standard_coursemodule_elements();

        //-----------------------------------------------------------------------------
        // add standard buttons, common to all modules
        //-----------------------------------------------------------------------------
        $this->add_action_buttons();
    }

    /**
     * data_preprocessing
     *
     * @param $toform (passed by reference)
     * @todo Finish documenting this function
     */
    public function data_preprocessing(&$toform) {

        // enable timelimit, if necessary
        if (isset($toform['timelimit']) && $toform['timelimit'] > 0) {
            $toform['timelimitenable'] = 1;
        }

        // "password" and "subnet" fields are different in form
        // to stop browsers that remember passwords from getting confused
        if (isset($toform['password'])) {
            $toform['requirepassword'] = $toform['password'];
            unset($toform['password']);
        }
        if (isset($toform['subnet'])) {
            $toform['requiresubnet'] = $toform['subnet'];
            unset($toform['subnet']);
        }
    }

    /**
     * set_type_default_advanced
     *
     * @param $mform
     * @param $config
     * @param $name of field
     * @param $type PARAM_xxx constant value
     * @param $default (optional, default = null)
     * @todo Finish documenting this function
     */
    private function set_type_default_advanced($mform, $config, $name, $type, $default=null) {
        $mform->setType($name, $type);
        if (isset($config->$name)) {
            $mform->setDefault($name, $config->$name);
        } else if ($default) {
            $mform->setDefault($name, $default);
        }
        $adv_name = 'adv'.$name;
        if (isset($config->$adv_name)) {
            $mform->setAdvanced($name, $config->$adv_name);
        }
    }

    /**
     * return a field value from the original record
     * this function is useful to see if a value has changed
     *
     * @param string the $field name
     * @param mixed the $default value (optional, default=null)
     * @return mixed the field value if it exists, $default otherwise
     */
    public function get_originalvalue($field, $default=null) {
        if (isset($this->current->$field)) {
            return $this->current->$field;
        } else {
            return $default;
        }
    }

    /**
     * Display module-specific activity completion rules.
     * Part of the API defined by moodleform_mod
     * @return array Array of string IDs of added items, empty array if none
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $plugin = 'mod_reader';

        // array of elements names to be returned by this method
        $names = array();

        // add "grade passed" completion condition
        $name = 'completionpass';
        $label = get_string($name, $plugin);
        $mform->addElement('checkbox', $name, '', $label);
        $mform->setType($name, PARAM_INT);
        $mform->setDefault($name, 0);
        $names[] = $name;

        // add "reading total" completion condition
        $name = 'completiontotalwords';
        $label = get_string($name, $plugin);
        $mform->addElement('text', $name, $label, array('size' => self::TEXT_NUM_SIZE));
        $mform->addHelpButton($name, $name, $plugin);
        $mform->setType($name, PARAM_INT);
        $mform->setDefault($name, 0);
        $names[] = $name;

        return $names;
    }

    /**
     * Called during validation. Indicates whether a module-specific completion rule is selected.
     *
     * @param array $data Input data (not yet validated)
     * @return bool True if one or more rules is enabled, false if none are.
     */
    public function completion_rule_enabled($data) {
        if (empty($data['completionpass']) && empty($data['completiontotalwords'])) {
            return false;
        } else {
            return true;
        }
    }
}

