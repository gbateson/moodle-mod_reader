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
defined('MOODLE_INTERNAL') || die;

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

    /**
     * definition
     *
     * @uses $CFG
     * @uses $COURSE
     * @uses $DB
     * @todo Finish documenting this function
     */
    public function definition() {

        global $COURSE, $CFG, $DB;

        $plugin = 'mod_reader';
        $config = get_config($plugin);

        $dateoptions = array('optional' => true);
        $textoptions = array('size'=>'10');

        $mform = $this->_form;

        //-----------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));
        //-----------------------------------------------------------------------------
        $name = 'name';
        $mform->addElement('text', $name, get_string($name), array('size'=>'64'));
        $mform->setType($name, (empty($CFG->formatstringstriptags) ? PARAM_CLEANHTML : PARAM_TEXT));
        $mform->addRule($name, null, 'required', null, 'client');

        $this->add_intro_editor(false, get_string('summary'));

        //-----------------------------------------------------------------------------
        $mform->addElement('header', 'timinghdr', get_string('timing', 'form'));
        //-----------------------------------------------------------------------------

        $name = 'timeopen';
        $mform->addElement('date_time_selector', $name, get_string('quizopen', 'quiz'), $dateoptions);
        $mform->addHelpButton($name, 'quizopenclose', 'quiz');
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);

        $name = 'timeclose';
        $mform->addElement('date_time_selector', $name, get_string('quizclose', 'quiz'), $dateoptions);
        $mform->addHelpButton($name, 'quizopenclose', 'quiz');
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);

        $name = 'timelimit';
        $mform->addElement('duration', $name, get_string($name, 'quiz'), array('optional' => true, 'step' => 60));
        $mform->addHelpButton($name, $name, 'quiz');
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);

        //-----------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('mainpagesettings', $plugin));
        //-----------------------------------------------------------------------------

        $name = 'bookcovers';
        $mform->addElement('selectyesno', $name, get_string($name, $plugin));
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'wordsprogressbar';
        $mform->addElement('selectyesno', $name, get_string($name, $plugin));
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'pointreport';
        $label = get_string($name, $plugin);
        $mform->addElement('selectyesno', $name, $label);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, $plugin);

        //-----------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('promotionsettings', $plugin));
        //-----------------------------------------------------------------------------

        $name = 'levelcheck';
        $mform->addElement('selectyesno', $name, get_string($name, $plugin));
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'percentforreading';
        $mform->addElement('text', $name, get_string($name, $plugin), $textoptions);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'goal';
        $mform->addElement('text', $name, get_string('totalpointsgoal', $plugin), $textoptions);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, 'totalpointsgoal', $plugin);

        $name = 'nextlevel';
        $mform->addElement('text', $name, get_string($name, $plugin), $textoptions);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addRule($name, null, 'required', null, 'client');
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'quizpreviouslevel';
        $mform->addElement('text', $name, get_string($name, $plugin), $textoptions);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addRule($name, null, 'required', null, 'client');
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'quiznextlevel';
        $mform->addElement('text', $name, get_string($name, $plugin), $textoptions);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addRule($name, null, 'required', null, 'client');
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'promotionstop';
        $mform->addElement('text', $name, get_string($name, $plugin), $textoptions);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, $plugin);

        //-----------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('reportsettings', $plugin));
        //-----------------------------------------------------------------------------

        $name = 'ignoredate';
        $options = array('startyear' => 2002,
                         'stopyear'  => date('Y', time()),
                         'applydst'  => true);
        $mform->addElement('date_selector', $name, get_string($name, $plugin), $options);
        $mform->addHelpButton($name, $name, $plugin);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);

        $name = 'reportwordspoints';
        $options = array(0 => get_string('showwordcount', $plugin),
                         1 => get_string('showpoints', $plugin),
                         2 => get_string('showpointsandwordcount', $plugin));
        $mform->addElement('select', $name, get_string($name, $plugin), $options);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'questionmark';
        $mform->addElement('selectyesno', $name, get_string($name, $plugin));
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'checkbox';
        $mform->addElement('selectyesno', $name, get_string($name, $plugin));
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, $plugin);

        //-----------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('readerquizsettings', $plugin));
        //-----------------------------------------------------------------------------

        $name = 'usecourse';
        $options = get_courses();
        unset($options[SITEID]);
        foreach ($options as $option) {
            $options[$option->id] = $option->fullname;
        }
        $mform->addElement('select', $name, get_string($name, $plugin), $options);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT, $COURSE->id);
        $mform->addHelpButton($name, $name, $plugin);

        $name = 'bookinstances';
        $mform->addElement('selectyesno', $name, get_string($name, $plugin));
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, $name, $plugin);

        //-----------------------------------------------------------------------------
        $mform->addElement('header', 'security', get_string('security', 'form'));
        //-----------------------------------------------------------------------------

        $name = 'checkip';
        $options = array(0 => get_string('off', $plugin),
                         1 => get_string('anywhere', $plugin),
                         2 => get_string('adjoiningcomputers', $plugin));
        $mform->addElement('select', $name, get_string($name, $plugin), $options);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        //$mform->addHelpButton($name, $name, $plugin);

        $name = 'popup';
        $mform->addElement('selectyesno', $name, get_string($name, 'quiz'));
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        $mform->addHelpButton($name, 'browsersecurity', 'quiz');
        //$mform->addHelpButton($name, $name, $plugin);

        $name = 'requirepassword';
        $mform->addElement('passwordunmask', $name, get_string($name, 'quiz'));
        $this->set_type_default_advanced($mform, $config, $name, PARAM_TEXT);
        $mform->addHelpButton($name, $name, 'quiz');

        $name = 'requiresubnet';
        $mform->addElement('text', $name, get_string($name, 'quiz'));
        $this->set_type_default_advanced($mform, $config, $name, PARAM_TEXT);
        $mform->addHelpButton($name, $name, 'quiz');

        $name = 'individualstrictip';
        $mform->addElement('selectyesno', $name, get_string($name, $plugin));
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        //$mform->addHelpButton($name, $name, $plugin);

        $name = 'sendmessagesaboutcheating';
        $mform->addElement('selectyesno', $name, get_string($name, $plugin));
        $this->set_type_default_advanced($mform, $config, $name, PARAM_INT);
        //$mform->addHelpButton($name, $name, $plugin);

        $name = 'cheated_message';
        $options = array('rows' => 5, 'cols' => 50);
        $mform->addElement('textarea', $name, stripslashes(get_string($name, $plugin)), $options);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_TEXT);
        //$mform->addHelpButton($name, $name, $plugin);

        $name = 'not_cheated_message';
        $options = array('rows' => 5, 'cols' => 50);
        $mform->addElement('textarea', $name, stripslashes(get_string($name, $plugin)), $options);
        $this->set_type_default_advanced($mform, $config, $name, PARAM_TEXT);
        //$mform->addHelpButton($name, $name, $plugin);

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
}

