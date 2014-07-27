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
    /** @var array options to be used with date_time_selector fields in this activity */
    public static $datefieldoptions = array('optional' => true, 'step' => 1);

    /**
     * definition
     *
     * @uses $CFG
     * @uses $COURSE
     * @uses $DB
     * @todo Finish documenting this function
     */
    function definition() {

        global $COURSE, $CFG, $DB;

        $plugin = 'mod_reader';
        $config = get_config($plugin);

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
        // Time limit.
        $name = 'timelimit';
        $mform->addElement('duration', $name, get_string($name, 'quiz'), array('optional' => true));
        $mform->addHelpButton($name, $name, 'quiz');
        if (isset($config->timelimit)) {
            $mform->setDefault($name, $config->timelimit);
        }
        if (isset($config->adv_timelimit)) {
            $mform->setAdvanced($name, $config->adv_timelimit);
        }

        //-----------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('settings', $plugin));
        //-----------------------------------------------------------------------------
        $name = 'pointreport';
        $label = get_string('full'.$name, $plugin);
        $mform->addElement('selectyesno', $name, $label);

        $mform->addElement('select', 'bookinstances', get_string('bookinstances', $plugin), array('0'=>'No', '1'=>'Yes'));
        $mform->addElement('select', 'levelcheck', get_string('levelrestrictionfeature', $plugin), array('0'=>'No', '1'=>'Yes'));
        $mform->addElement('select', 'reportwordspoints', get_string('reportwordspoints', $plugin), array('0'=>'Show Word Count only', '1'=>'Show Points only', '2'=>'Show both Points and Word Count'));
        $mform->addElement('select', 'wordsprogressbar', get_string('wordsprogressbar', $plugin), array('0'=>'Hide', '1'=>'Show'));
        $elements=array(
            $mform->createElement('text', 'percentforreading', get_string('percentcorrectforbook', $plugin), array('size'=>'10')),
            $mform->createElement('static', 'description', '', '%')
        );
        $mform->addGroup($elements, 'elementsgroup', get_string('percentcorrectforbook', $plugin), ' ', false);
        $mform->addElement('select', 'questionmark', get_string('questionmark', $plugin), array('0'=>'No', '1'=>'yes'));
        $mform->addElement('select', 'bookcovers', get_string('bookcovers', $plugin), array('0'=>'No', '1'=>'yes'));
        $mform->addElement('select', 'checkbox', get_string('checkbox', $plugin), array('0'=>'No', '1'=>'yes'));
        $mform->addElement('text', 'goal', get_string('totalpointsgoal', $plugin), array('size'=>'10'));
        $mform->addElement('text', 'nextlevel', get_string('nextlevel', $plugin), array('size'=>'10'));
        $mform->addElement('text', 'quizpreviouslevel', get_string('selectquizzes', $plugin), array('size'=>'10'));
        $mform->addElement('text', 'quiznextlevel', get_string('quiznextlevel', $plugin), array('size'=>'10'));
        $mform->addElement('text', 'promotionstop', get_string('nopromotion', $plugin), array('size'=>'10'));
        //$mform->setHelpButton('promotionstop', array('promotionstop', get_string('nopromotion', $plugin), $plugin));
        $ignorform=array(
            $mform->createElement('date_selector', 'ignoredate', '', array('startyear'=>2002, 'stopyear'=>date('Y', time()), 'applydst'=>true)),
            $mform->createElement('static', 'description', '', get_string('ignor_2', $plugin))
        );
        $mform->addGroup($ignorform, 'ignor', get_string('ignor_1', $plugin), ' ', false);

        $options = get_courses();
        unset($options[SITEID]);
        foreach ($options as $option) {
            $options[$option->id] = $option->fullname;
        }
        $mform->addElement('select', 'usecourse', get_string('selectcourse', $plugin), $options);

        //-----------------------------------------------------------------------------
        $mform->addElement('header', 'security', get_string('security', 'form'));
        //-----------------------------------------------------------------------------

        $elements=array(
            $mform->createElement('radio', 'checkip', '', get_string('off', $plugin), 0),
            $mform->createElement('radio', 'checkip', '', get_string('anywhere', $plugin), 1),
            $mform->createElement('radio', 'checkip', '', get_string('computers', $plugin), 2)
        );
        $mform->addGroup($elements, 'elements', get_string('securitymeasures', $plugin), array(' '), false);

        $mform->addElement('selectyesno', 'popup', get_string('popup', 'quiz'));
        //$mform->setHelpButton('popup', array('popup', get_string('popup', 'quiz'), 'quiz'));
        if (isset($config->adv_popup)) {
            $mform->setAdvanced('popup', $config->adv_popup);
        }
        if (isset($config->popup)) {
            $mform->setDefault('popup', $config->popup);
        }

        $mform->addElement('passwordunmask', 'password', get_string('requirepassword', 'quiz'));
        $mform->setType('password', PARAM_TEXT);
        //$mform->setHelpButton('password', array('requirepassword', get_string('requirepassword', 'quiz'), 'quiz'));

        if (isset($config->adv_password)) {
            $mform->setAdvanced('password', $config->adv_password);
        }
        if (isset($config->password)) {
            $mform->setDefault('password', $config->password);
        }

        $mform->addElement('text', 'subnet', get_string('requiresubnet', 'quiz'));
        $mform->setType('subnet', PARAM_TEXT);
        //$mform->setHelpButton('subnet', array('requiresubnet', get_string('requiresubnet', 'quiz'), 'quiz'));
        if (isset($config->adv_subnet)) {
            $mform->setAdvanced('subnet', $config->adv_subnet);
        }
        if (isset($config->subnet)) {
            $mform->setDefault('subnet', $config->subnet);
        }

        $mform->addElement('select', 'individualstrictip', get_string('individualstrictip', $plugin), array('0'=>'No', '1'=>'yes'));

        $mform->addElement('select', 'sendmessagesaboutcheating', get_string('sendmessagesaboutcheating', $plugin), array('0'=>'No', '1'=>'yes'));

        $mform->addElement('textarea', 'cheated_message', stripslashes(get_string('cheated_message', $plugin)), 'rows="5" cols="50"');
        $mform->addElement('textarea', 'not_cheated_message', get_string('not_cheated_message', $plugin), 'rows="5" cols="50"');

//-------------------------------------------------------------------------------

        $mform->setType('subnet', PARAM_TEXT);
        //$mform->setHelpButton('subnet', array('requiresubnet', get_string('requiresubnet', 'quiz'), 'quiz'));

        //-----
        $mform->setDefault('timelimit', $config->quiztimelimit);
        $mform->setDefault('percentforreading', $config->percentforreading);
        $mform->setDefault('nextlevel', $config->quiznextlevel);
        $mform->addRule('nextlevel', null, 'required', null, 'client');
        $mform->setDefault('quizpreviouslevel', $config->quizpreviouslevel);
        $mform->addRule('quizpreviouslevel', null, 'required', null, 'client');
        $mform->setDefault('quiznextlevel', $config->quizonnextlevel);
        $mform->addRule('quiznextlevel', null, 'required', null, 'client');

        $mform->setDefault('pointreport', $config->pointreport);
        $mform->setDefault('questionmark', $config->questionmark);
        $mform->setDefault('bookcovers', $config->bookcovers);
        if (isset($config->goal)) {
            $mform->setDefault('goal', $config->goal);
        }
        if (isset($config->checkip)) {
            $mform->setDefault('checkip', $config->checkip);
        }
        $mform->setDefault('levelcheck', $config->levelcheck);

        $mform->setDefault('reportwordspoints', $config->reportwordspoints);
        $mform->setDefault('wordsprogressbar', $config->wordsprogressbar);
        $mform->setDefault('sendmessagesaboutcheating', $config->sendmessagesaboutcheating);
        $mform->setDefault('cheated_message', $config->cheated_message);
        $mform->setDefault('not_cheated_message', $config->not_cheated_message);

        if ($config->usecourse == 0) {
            $mform->setDefault('usecourse', $COURSE->id);
        } else {
            $mform->setDefault('usecourse', $config->usecourse);
        }

        //if ($mform->timelimit != 0 || !isset($mform->timelimit)) {
            $mform->setDefault('timelimitenable', 1);
        //}

        // Moodle >= 2.5 requires each field to have its "type" set
        $fields = array('percentforreading', 'goal', 'nextlevel', 'quizpreviouslevel', 'quiznextlevel', 'promotionstop');
        foreach ($fields as $field) {
            $mform->setType($field, PARAM_INT);
        }

//-------------------------------------------------------------------------------
        // add standard elements, common to all modules
        $this->standard_coursemodule_elements();
//-------------------------------------------------------------------------------
        // add standard buttons, common to all modules
        $this->add_action_buttons();
    }
}

