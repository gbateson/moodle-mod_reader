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

$plugin = 'mod_reader';
$config = reader_get_config_defaults();

// Introductory explanation that all the settings are defaults for the add quiz form.
$name = 'configintro';
$setting = new admin_setting_heading($name, '', get_string($name, $plugin));
$settings->add($setting);

// quiztimelimit
$name = 'quiztimelimit';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
if (class_exists('admin_setting_configduration') && method_exists('admin_setting_configduration', 'set_advanced_flag_options')) {
    // Moodle >= 2.6
    $default = array('v' => $config->$name, 'u' => 1, 'adv' => false);
    $setting = new admin_setting_configduration("$plugin/$name", $text, $help, $default, 60);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, !empty($default['adv']));
} else {
    // Moodle <= 2.5
    $default = array('value' => $config->$name, 'adv' => false);
    $setting = new admin_setting_configtext_with_advanced("$plugin/$name", $text, $help, $default, PARAM_INT);
}
$settings->add($setting);

// editingteacherrole
$name = 'editingteacherrole';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $config->$name, 'adv' => false);
$options = array(
    0 => get_string('allownot'),
    1 => get_string('allow')
);
$setting = new admin_setting_configselect_with_advanced("$plugin/$name", $text, $help, $default, $options);
$settings->add($setting);

// pointreport
$name = 'pointreport';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $config->$name, 'adv' => false);
$options = array(
    0 => get_string('no'),
    1 => get_string('yes')
);
$setting = new admin_setting_configselect_with_advanced("$plugin/$name", $text, $help, $default, $options);
$settings->add($setting);

// levelcheck
$name = 'levelcheck';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $config->$name, 'adv' => false);
$options = array(
    0 => get_string('no'),
    1 => get_string('yes')
);
$setting = new admin_setting_configselect_with_advanced("$plugin/$name", $text, $help, $default, $options);
$settings->add($setting);

// checkbox
$name = 'checkbox';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $config->$name, 'adv' => false);
$options = array(
    0 => get_string('no'),
    1 => get_string('yes')
);
$setting = new admin_setting_configselect_with_advanced("$plugin/$name", $text, $help, $default, $options);
$settings->add($setting);

// percentforreading
$name = 'percentforreading';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $config->$name, 'adv' => false);
$setting = new admin_setting_configtext_with_advanced("$plugin/$name", $text, $help, $default, PARAM_INT);
$settings->add($setting);

// questionmark
$name = 'questionmark';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $config->$name, 'adv' => false);
$options = array(
    0 => get_string('no'),
    1 => get_string('yes')
);
$setting = new admin_setting_configselect_with_advanced("$plugin/$name", $text, $help, $default, $options);
$settings->add($setting);

// bookcovers
$name = 'bookcovers';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $config->$name, 'adv' => false);
$options = array(
    0 => get_string('no'),
    1 => get_string('yes')
);
$setting = new admin_setting_configselect_with_advanced("$plugin/$name", $text, $help, $default, $options);
$settings->add($setting);

// reportwordspoints
$name = 'reportwordspoints';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $config->$name, 'adv' => false);
$options = array(
    0 => 'Show Word Count only',
    1 => 'Show Points only',
    2 => 'Show both Points and Word Count'
);
$setting = new admin_setting_configselect_with_advanced("$plugin/$name", $text, $help, $default, $options);
$settings->add($setting);

// wordsprogressbar
$name = 'wordsprogressbar';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $config->$name, 'adv' => false);
$options = array(
    0 => get_string('hide'),
    1 => get_string('show')
);
$setting = new admin_setting_configselect_with_advanced("$plugin/$name", $text, $help, $default, $options);
$settings->add($setting);

// nextlevel
$name = 'nextlevel';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $config->$name, 'adv' => false);
$setting = new admin_setting_configtext_with_advanced("$plugin/$name", $text, $help, $default, PARAM_INT);
$settings->add($setting);

// quizpreviouslevel
$name = 'quizpreviouslevel';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $config->$name, 'adv' => false);
$setting = new admin_setting_configtext_with_advanced("$plugin/$name", $text, $help, $default, PARAM_INT);
$settings->add($setting);

// quiznextlevel
$name = 'quiznextlevel';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $config->$name, 'adv' => false);
$setting = new admin_setting_configtext_with_advanced("$plugin/$name", $text, $help, $default, PARAM_INT);
$settings->add($setting);

// usecourse
$name = 'usecourse';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $config->$name, 'adv' => false);
$options = array(0 => 'Current course');
foreach (get_courses() as $course) {
    $options[$course->id] = $course->fullname;
}
$setting = new admin_setting_configselect_with_advanced("$plugin/$name", $text, $help, $default, $options);
$settings->add($setting);

// update
$name = 'update';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $config->$name, 'adv' => false);
$options = array(
    0 => get_string('no'),
    1 => get_string('yes')
);
$setting = new admin_setting_configselect_with_advanced("$plugin/$name", $text, $help, $default, $options);
$settings->add($setting);

// sendmessagesaboutcheating
$name = 'sendmessagesaboutcheating';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $config->$name, 'adv' => false);
$options = array(
    0 => get_string('no'),
    1 => get_string('yes')
);
$setting = new admin_setting_configselect_with_advanced("$plugin/$name", $text, $help, $default, $options);
$settings->add($setting);

// cheated_message
$name = 'cheated_message';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = $config->$name;
$setting = new admin_setting_configtextarea("$plugin/$name", $text, $help, $default);
$settings->add($setting);

// not_cheated_message
$name = 'not_cheated_message';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = $config->$name;
$setting = new admin_setting_configtextarea("$plugin/$name", $text, $help, $default);
$settings->add($setting);

// serverlink
$name = 'serverlink';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => 'http://moodlereader.net/quizbank', 'adv' => false);
$setting = new admin_setting_configtext_with_advanced("$plugin/$name", $text, $help, $default, PARAM_TEXT);
$settings->add($setting);

// serverlogin
$name = 'serverlogin';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => '', 'adv' => false);
$setting = new admin_setting_configtext_with_advanced("$plugin/$name", $text, $help, $default, PARAM_TEXT);
$settings->add($setting);

// serverpassword
$name = 'serverpassword';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => '', 'adv' => false);
$setting = new admin_setting_configtext_with_advanced("$plugin/$name", $text, $help, $default, PARAM_TEXT);
$settings->add($setting);

// keepoldquizzes
$name = 'keepoldquizzes';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => 0, 'adv' => false);
$options = array(
    0 => get_string('no'),
    1 => get_string('yes')
);
$setting = new admin_setting_configselect_with_advanced("$plugin/$name", $text, $help, $default, $options);
$settings->add($setting);

// reclaim some memory - but don't touch $settings !
unset($config, $name, $value, $text, $help, $default, $defaults, $options, $plugin, $setting);
