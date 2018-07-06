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
defined('MOODLE_INTERNAL') || die();

$plugin = 'mod_reader';
$defaults = (object)array(
    // default settings for Reader activities in courses
    'availablefrom'      => '0',
    'availableuntil'     => '0',
    'readonlyfrom'       => '0',
    'readonlyuntil'      => '0',
    'timelimit'          => '900', // 900 secs = 15 mins
    'bookcovers'         => '1',
    'showprogressbar'    => '1',
    'showpercentgrades'  => '0',
    'showreviewlinks'    => '0',
    'wordsorpoints'      => '0',
    'minpassgrade'       => '60',
    'goal'               => '0',
    'maxgrade'           => '0',
    'levelcheck'         => '1',
    'prevlevel'          => '3',
    'thislevel'          => '6',
    'nextlevel'          => '1',
    'stoplevel'          => '0',
    'ignoredate'         => '0',
    'questionscores'     => '0',
    'checkbox'           => '0',
    'usecourse'          => '0',
    'bookinstance'       => '0',
    'popup'              => '0',
    'requirepassword'    => '0',
    'requiresubnet'      => '0',
    'checkcheating'      => '0',
    'notifycheating'     => '1',
    'cheatedmessage'     => get_string('cheatedmessagedefault', 'mod_reader'),
    'clearedmessage'     => get_string('clearedmessagedefault', 'mod_reader'),

    // site wide settings (i.e. all courses use the same settings)

    // settings to download from quiz bank on MoodleReader.net
    'serverurl'          => 'http://moodlereader.net/quizbank',
    'serverusername'     => '',
    'serverpassword'     => '',
    'keepoldquizzes'     => '0',
    'keeplocalbookdifficulty' => '0',

    // settings to access API and take quizzes online at mReader.org
    'mreaderurl'         => 'https://mreader.org',
    'mreadersiteid'      => '',
    'mreadersitekey'     => '',

    'last_update'        => '0' // maintained by "reader_cron()" in "mod/reader/lib.php"
);

// cache commonly used options
$yesno = array(
    0 => get_string('no'),
    1 => get_string('yes')
);

// Introductory explanation that all the settings are defaults when adding a new Reader activity.
$name = 'configintro';
$setting = new admin_setting_heading($name, '', get_string($name, $plugin));
$settings->add($setting);

// timelimit for Reader quizzes
$name = 'timelimit';
$text = get_string($name, 'quiz');
$help = get_string('config'.$name, 'quiz');
if (class_exists('admin_setting_configduration') && method_exists('admin_setting_configduration', 'set_advanced_flag_options')) {
    // Moodle >= 2.6
    $default = array('v' => $defaults->$name, 'u' => 1, 'adv' => false);
    $setting = new admin_setting_configduration("$plugin/$name", $text, $help, $default, 60);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, !empty($default['adv']));
} else {
    // Moodle <= 2.5
    $default = array('value' => $defaults->$name, 'adv' => false);
    $setting = new admin_setting_configtext_with_advanced("$plugin/$name", $text, $help, $default, PARAM_INT);
}
$settings->add($setting);

// bookcovers
$name = 'bookcovers';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $defaults->$name, 'adv' => false);
$setting = new admin_setting_configselect_with_advanced("$plugin/$name", $text, $help, $default, $yesno);
$settings->add($setting);

// showprogressbar
$name = 'showprogressbar';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $defaults->$name, 'adv' => false);
$setting = new admin_setting_configselect_with_advanced("$plugin/$name", $text, $help, $default, $yesno);
$settings->add($setting);

// showpercentgrades
$name = 'showpercentgrades';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $defaults->$name, 'adv' => false);
$setting = new admin_setting_configselect_with_advanced("$plugin/$name", $text, $help, $default, $yesno);
$settings->add($setting);

// wordsorpoints
$name = 'wordsorpoints';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $defaults->$name, 'adv' => false);
$options = array(
    0 => 'Show Word Count only',
    1 => 'Show Points only',
    2 => 'Show both Points and Word Count'
);
$setting = new admin_setting_configselect_with_advanced("$plugin/$name", $text, $help, $default, $options);
$settings->add($setting);

// minpassgrade
// goal
// maxgrade
// levelcheck
// prevlevel
// thislevel
// nextlevel
// stoplevel
$names = array('minpassgrade', 'goal', 'maxgrade', 'levelcheck', 'prevlevel', 'thislevel', 'nextlevel', 'stoplevel');
foreach ($names as $name) {
    if ($name=='levelcheck') {
        $text = get_string($name, $plugin);
        $help = get_string('config'.$name, $plugin);
        $default = array('value' => $defaults->$name, 'adv' => false);
        $setting = new admin_setting_configselect_with_advanced("$plugin/$name", $text, $help, $default, $yesno);
        $settings->add($setting);
    } else {
        $text = get_string($name, $plugin);
        $help = get_string('config'.$name, $plugin);
        $default = array('value' => $defaults->$name, 'adv' => false);
        $setting = new admin_setting_configtext_with_advanced("$plugin/$name", $text, $help, $default, PARAM_INT);
        $settings->add($setting);
    }
}

// ignoredate

// showreviewlinks
$name = 'showreviewlinks';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $defaults->$name, 'adv' => false);
$setting = new admin_setting_configselect_with_advanced("$plugin/$name", $text, $help, $default, $yesno);
$settings->add($setting);

// checkbox
$name = 'checkbox';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $defaults->$name, 'adv' => false);
$setting = new admin_setting_configselect_with_advanced("$plugin/$name", $text, $help, $default, $yesno);
$settings->add($setting);

// usecourse
$name = 'usecourse';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $defaults->$name, 'adv' => false);
$options = array(0 => 'Current course');
foreach (get_courses() as $course) {
    $options[$course->id] = $course->fullname;
}
$setting = new admin_setting_configselect_with_advanced("$plugin/$name", $text, $help, $default, $options);
$settings->add($setting);

// questionscores
$name = 'questionscores';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $defaults->$name, 'adv' => false);
$setting = new admin_setting_configselect_with_advanced("$plugin/$name", $text, $help, $default, $yesno);
$settings->add($setting);

// bookinstances
// popup
// requirepassword
// requiresubnet

// checkcheating
$name = 'checkcheating';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $defaults->$name, 'adv' => false);
$setting = new admin_setting_configselect_with_advanced("$plugin/$name", $text, $help, $default, $yesno);
$settings->add($setting);

// notifycheating
$name = 'notifycheating';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = array('value' => $defaults->$name, 'adv' => false);
$setting = new admin_setting_configselect_with_advanced("$plugin/$name", $text, $help, $default, $yesno);
$settings->add($setting);

// cheatedmessage (Moodle >= 2.3)
if (method_exists('admin_setting_configtextarea', 'set_advanced_flag_options')) {
    $name = 'cheatedmessage';
    $text = get_string($name, $plugin);
    $help = get_string('config'.$name, $plugin);
    $default = $defaults->$name;
    $setting = new admin_setting_configtextarea("$plugin/$name", $text, $help, $default);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);
}

// clearedmessage (Moodle >= 2.3)
if (method_exists('admin_setting_configtextarea', 'set_advanced_flag_options')) {
    $name = 'clearedmessage';
    $text = get_string($name, $plugin);
    $help = get_string('config'.$name, $plugin);
    $default = $defaults->$name;
    $setting = new admin_setting_configtextarea("$plugin/$name", $text, $help, $default);
    $setting->set_advanced_flag_options(admin_setting_flag::ENABLED, false);
    $settings->add($setting);
}

$name = 'mreadersettings';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$setting = new admin_setting_heading("$plugin/$name", $text, $help);
$settings->add($setting);

// mReader API url
$name = 'mreaderurl';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = $defaults->$name;
$setting = new admin_setting_configtext("$plugin/$name", $text, $help, $default, PARAM_TEXT);
$settings->add($setting);

// mReader API site id
$name = 'mreadersiteid';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = $defaults->$name;
$setting = new admin_setting_configtext("$plugin/$name", $text, $help, $default, PARAM_TEXT);
$settings->add($setting);

// mReader API site key
$name = 'mreadersitekey';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = $defaults->$name;
$setting = new admin_setting_configpasswordunmask("$plugin/$name", $text, $help, $default, PARAM_TEXT);
$settings->add($setting);

$name = 'serversettings';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$setting = new admin_setting_heading("$plugin/$name", $text, $help);
$settings->add($setting);

// serverurl
$name = 'serverurl';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = $defaults->$name;
$setting = new admin_setting_configtext("$plugin/$name", $text, $help, $default, PARAM_TEXT);
$settings->add($setting);

// serverusername
$name = 'serverusername';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = $defaults->$name;
$setting = new admin_setting_configtext("$plugin/$name", $text, $help, $default, PARAM_TEXT);
$settings->add($setting);

// serverpassword
$name = 'serverpassword';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = $defaults->$name;
$setting = new admin_setting_configpasswordunmask("$plugin/$name", $text, $help, $default, PARAM_TEXT);
$settings->add($setting);

// keepoldquizzes
$name = 'keepoldquizzes';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = $defaults->$name;
$setting = new admin_setting_configselect("$plugin/$name", $text, $help, $default, $yesno);
$settings->add($setting);

// keeplocalbookdifficulty
$name = 'keeplocalbookdifficulty';
$text = get_string($name, $plugin);
$help = get_string('config'.$name, $plugin);
$default = $defaults->$name;
$setting = new admin_setting_configselect("$plugin/$name", $text, $help, $default, $yesno);
$settings->add($setting);

// reclaim some memory - but don't touch $settings !
unset($name, $value, $text, $help, $course, $default, $defaults, $options, $plugin, $setting, $yesno);
