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
 * mod/reader/admin/tools/redo_upgrade.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once('../../../../config.php');

$id  = optional_param('id',  0, PARAM_INT);
$tab = optional_param('tab', 0, PARAM_INT);

require_login(SITEID);
if (class_exists('context_system')) {
    $context = context_system::instance();
} else {
    $context = get_context_instance(CONTEXT_SYSTEM);
}
require_capability('moodle/site:config', $context);

// $SCRIPT is set by initialise_fullme() in 'lib/setuplib.php'
// it is the path below $CFG->wwwroot of this script
$PAGE->set_url($CFG->wwwroot.$SCRIPT);

// set title
$title = get_string('redo_upgrade', 'mod_reader');
$PAGE->set_title($title);
$PAGE->set_heading($title);
$PAGE->set_pagelayout('admin');

echo $OUTPUT->header();
echo $OUTPUT->box_start();

$dateformat = 'jS M Y'; // for date() function

if ($version = optional_param('version', 0, PARAM_INT)) {

    // format version
    if (preg_match('/(\d{4})(\d{2})(\d{2})(\d{2})/', "$version", $match)) {
        $yy = $match[1];
        $mm = $match[2];
        $dd = $match[3];
        $vv = intval($match[4]);
        $text = date($dateformat, mktime(0,0,0,$mm,$dd,$yy)).($vv==0 ? '' : " ($vv)");
    } else {
        $text = ''; // shouldn't happen !!
    }

    // reset the Reader module version
    $dbman = $DB->get_manager();
    if ($dbman->field_exists('modules', 'version')) {
        // Moodle <= 2.5
        $params = array('name' => 'reader');
        $DB->set_field('modules', 'version', $version - 1, $params);
    } else if ($dbman->table_exists('config_plugins')) {
        // Moodle >= 2.6
        $params = array('plugin' => 'mod_reader', 'name' => 'version');
        $DB->set_field('config_plugins', 'value', $version - 1, $params);
        // force Moodle to refetch versions
        if (isset($CFG->allversionshash)) {
            unset_config('allversionshash');
        }
    }

    // report
    echo html_writer::tag('p', "Reader module version set to just before $version - $text");

    // link to upgrade page
    $href = new moodle_url('/admin/index.php', array('confirmplugincheck' => 1, 'cache'=>0));
    echo html_writer::tag('p', html_writer::tag('a', 'Click here to continue', array('href' => $href)));

} else { // no $version given, so offer a form to select $version

    // start form
    echo html_writer::start_tag('form', array('action' => $FULLME, 'method' => 'post'));
    echo html_writer::start_tag('div');

    // extract and format versions from Reader upgrade script
    $contents = file_get_contents($CFG->dirroot.'/mod/reader/db/upgrade.php');
    preg_match_all('/(?<=\$newversion = )(\d{4})(\d{2})(\d{2})(\d{2})(?=;)/', $contents, $matches);
    $i_max = count($matches[0]);
    for ($i=0; $i<$i_max; $i++) {
        $version = $matches[0][$i];
        $yy = $matches[1][$i];
        $mm = $matches[2][$i];
        $dd = $matches[3][$i];
        $vv = intval($matches[4][$i]);
        $versions[$version] = date($dateformat, mktime(0,0,0,$mm,$dd,$yy)).($vv==0 ? '' : " ($vv)");
    }
    krsort($versions);

    // add form elements
    echo get_string('version').' '.html_writer::select($versions, 'version').' ';
    echo html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('go')));

    // finish form
    echo html_writer::end_tag('div');
    echo html_writer::end_tag('form');
}

echo $OUTPUT->box_end();
echo $OUTPUT->footer();
