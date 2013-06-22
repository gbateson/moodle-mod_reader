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
 * mod/reader/settings.php
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
require_once($CFG->dirroot.'/mod/reader/lib.php');

$pagetitle = get_string('modulename', 'reader');

$readercfg = get_config('reader');

$readersettings = new admin_settingpage('modsettingreader', $pagetitle, 'moodle/site:config');

// Introductory explanation that all the settings are defaults for the add quiz form.
$readersettings->add(new admin_setting_heading('configintro', '', get_string('configintro', 'reader')));

// quiztimeout
$name = 'reader/quiztimeout';
$text = get_string('quiztimeout', 'reader');
$help = get_string('configquiztimeout', 'reader');
$default = array('value' => $readercfg->quiztimeout, 'fix' => false);
$readersettings->add(new admin_setting_configtext_with_advanced($name, $text, $help, $default, PARAM_INT));

// editingteacherrole
$name = 'reader/editingteacherrole';
$text = get_string('editingteacherrole', 'reader');
$help = get_string('configeditingteacherrole', 'reader');
$default = array('value' => $readercfg->editingteacherrole, 'fix' => false);
$options = array(
    0 => get_string('allownot'),
    1 => get_string('allow')
);
$readersettings->add(new admin_setting_configselect_with_advanced($name, $text, $help, $default, $options));

// pointreport
$name = 'reader/pointreport';
$text = get_string('pointreport', 'reader');
$help = get_string('configpointreport', 'reader');
$default = array('value' => $readercfg->pointreport, 'fix' => false);
$options = array(
    0 => get_string('no'),
    1 => get_string('yes')
);
$readersettings->add(new admin_setting_configselect_with_advanced($name, $text, $help, $default, $options));

// levelcheck
$name = 'reader/levelcheck';
$text = get_string('levelcheck', 'reader');
$help = get_string('configlevelcheck', 'reader');
$default = array('value' => $readercfg->levelcheck, 'fix' => false);
$options = array(
    0 => get_string('no'),
    1 => get_string('yes')
);
$readersettings->add(new admin_setting_configselect_with_advanced($name, $text, $help, $default, $options));

// checkbox
$name = 'reader/checkbox';
$text = get_string('checkbox', 'reader');
$help = get_string('configcheckbox', 'reader');
$default = array('value' => $readercfg->checkbox, 'fix' => false);
$options = array(
    0 => get_string('no'),
    1 => get_string('yes')
);
$readersettings->add(new admin_setting_configselect_with_advanced($name, $text, $help, $default, $options));

// percentforreading
$name = 'reader/percentforreading';
$text = get_string('percentforreading', 'reader');
$help = get_string('configpercentforreading', 'reader');
$default = array('value' => $readercfg->percentforreading, 'fix' => false);
$readersettings->add(new admin_setting_configtext_with_advanced($name, $text, $help, $default, PARAM_INT));

// questionmark
$name = 'reader/questionmark';
$text = get_string('questionmark', 'reader');
$help = get_string('configquestionmark', 'reader');
$default = array('value' => $readercfg->questionmark, 'fix' => false);
$options = array(
    0 => get_string('no'),
    1 => get_string('yes')
);
$readersettings->add(new admin_setting_configselect_with_advanced($name, $text, $help, $default, $options));

// bookcovers
$name = 'reader/bookcovers';
$text = get_string('bookcovers', 'reader');
$help = get_string('configbookcovers', 'reader');
$default = array('value' => $readercfg->bookcovers, 'fix' => false);
$options = array(
    0 => get_string('no'),
    1 => get_string('yes')
);
$readersettings->add(new admin_setting_configselect_with_advanced($name, $text, $help, $default, $options));

// reportwordspoints
$name = 'reader/reportwordspoints';
$text = get_string('reportwordspoints', 'reader');
$help = get_string('configreportwordspoints', 'reader');
$default = array('value' => $readercfg->reportwordspoints, 'fix' => false);
$options = array(
    0 => 'Show Word Count only',
    1 => 'Show Points only',
    2 => 'Show both Points and Word Count'
);
$readersettings->add(new admin_setting_configselect_with_advanced($name, $text, $help, $default, $options));

// wordsprogressbar
$name = 'reader/wordsprogressbar';
$text = get_string('wordsprogressbar', 'reader');
$help = get_string('configwordsprogressbar', 'reader');
$default = array('value' => $readercfg->wordsprogressbar, 'fix' => false);
$options = array(
    0 => get_string('hide'),
    1 => get_string('show')
);
$readersettings->add(new admin_setting_configselect_with_advanced($name, $text, $help, $default, $options));

// attemptsofday
$name = 'reader/attemptsofday';
$text = get_string('attemptsofday', 'reader');
$help = get_string('configattemptsofday', 'reader');
$default = array('value' => $readercfg->attemptsofday, 'fix' => false);
$options = array(
    0 => 'Off',
    1 => '1',
    2 => '2',
    3 => '3'
);
$readersettings->add(new admin_setting_configselect_with_advanced($name, $text, $help, $default, $options));

// quiznextlevel
$name = 'reader/quiznextlevel';
$text = get_string('quiznextlevel', 'reader');
$help = get_string('configquiznextlevel', 'reader');
$default = array('value' => $readercfg->quiznextlevel, 'fix' => false);
$readersettings->add(new admin_setting_configtext_with_advanced($name, $text, $help, $default, PARAM_INT));

// quizpreviouslevel
$name = 'reader/quizpreviouslevel';
$text = get_string('quizpreviouslevel', 'reader');
$help = get_string('configquizpreviouslevel', 'reader');
$default = array('value' => $readercfg->quizpreviouslevel, 'fix' => false);
$readersettings->add(new admin_setting_configtext_with_advanced($name, $text, $help, $default, PARAM_INT));

// quizonnextlevel
$name = 'reader/quizonnextlevel';
$text = get_string('quizonnextlevel', 'reader');
$help = get_string('configquizonnextlevel', 'reader');
$default = array('value' => $readercfg->quizonnextlevel, 'fix' => false);
$readersettings->add(new admin_setting_configtext_with_advanced($name, $text, $help, $default, PARAM_INT));

// usecourse
$name = 'reader/usecourse';
$text = get_string('usecourse', 'reader');
$help = get_string('configusecourse', 'reader');
$default = array('value' => $readercfg->usecourse, 'fix' => false);
$options = array(0 => 'Current course');
foreach (get_courses() as $course) {
    $options[$course->id] = $course->fullname;
}
$readersettings->add(new admin_setting_configselect_with_advanced($name, $text, $help, $default, $options));

// update
$name = 'reader/update';
$text = get_string('update', 'reader');
$help = get_string('configupdate', 'reader');
$default = array('value' => $readercfg->update, 'fix' => false);
$options = array(
    0 => get_string('no'),
    1 => get_string('yes')
);
$readersettings->add(new admin_setting_configselect_with_advanced($name, $text, $help, $default, $options));

// sendmessagesaboutcheating
$name = 'reader/sendmessagesaboutcheating';
$text = get_string('sendmessagesaboutcheating', 'reader');
$help = get_string('configsendmessagesaboutcheating', 'reader');
$default = array('value' => $readercfg->sendmessagesaboutcheating, 'fix' => false);
$options = array(
    0 => get_string('no'),
    1 => get_string('yes')
);
$readersettings->add(new admin_setting_configselect_with_advanced($name, $text, $help, $default, $options));

// cheated_message
$name = 'reader/cheated_message';
$text = get_string('cheated_message', 'reader');
$help = get_string('configcheated_message', 'reader');
$default = $readercfg->cheated_message;
$readersettings->add(new admin_setting_configtextarea($name, $text, $help, $default));

// not_cheated_message
$name = 'reader/not_cheated_message';
$text = get_string('not_cheated_message', 'reader');
$help = get_string('confignot_cheated_message', 'reader');
$default = $readercfg->not_cheated_message;
$readersettings->add(new admin_setting_configtextarea($name, $text, $help, $default));

// serverlink
$name = 'reader/serverlink';
$text = get_string('serverlink', 'reader');
$help = get_string('configserverlink', 'reader');
$default = array('value' => 'http://moodlereader.net/quizbank', 'fix' => false);
$readersettings->add(new admin_setting_configtext_with_advanced($name, $text, $help, $default, PARAM_TEXT));

// serverlogin
$name = 'reader/serverlogin';
$text = get_string('serverlogin', 'reader');
$help = get_string('configserverlogin', 'reader');
$default = array('value' => '', 'fix' => false);
$readersettings->add(new admin_setting_configtext_with_advanced($name, $text, $help, $default, PARAM_TEXT));

// serverpassword
$name = 'reader/serverpassword';
$text = get_string('serverpassword', 'reader');
$help = get_string('configserverpassword', 'reader');
$default = array('value' => '', 'fix' => false);
$readersettings->add(new admin_setting_configtext_with_advanced($name, $text, $help, $default, PARAM_TEXT));

// keepoldquizzes
$name = 'reader/keepoldquizzes';
$text = get_string('keepoldquizzes', 'reader');
$help = get_string('configkeepoldquizzes', 'reader');
$default = array('value' => 0, 'fix' => false);
$options = array(
    0 => get_string('no'),
    1 => get_string('yes')
);
$readersettings->add(new admin_setting_configselect_with_advanced($name, $text, $help, $default, $options));

// add these settings
$ADMIN->add('modsettings', $readersettings);

// reclaim some memory
unset($name, $text, $help, $default, $paramtype, $options, $readersettings);

// remove standard settings
$settings = null;
