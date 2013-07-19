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
 * mod/reader/admin/download.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once('../../../config.php');
require_once($CFG->dirroot.'/mod/reader/admin/lib.php');
require_once($CFG->dirroot.'/mod/reader/admin/renderer.php');

$id   = optional_param('id',   0, PARAM_INT); // course module id
$r    = optional_param('r',    0, PARAM_INT); // reader id
$type = optional_param('type', 1, PARAM_INT); // 0=books without quizzes, 1=books with quizzes

$selectedpublishers = reader_optional_param_array('publishers', array(), PARAM_CLEAN);
$selectedlevels     = reader_optional_param_array('levels',     array(), PARAM_CLEAN);
$selecteditemids    = reader_optional_param_array('itemids',    array(), PARAM_CLEAN);

if ($id) {
    $cm = get_coursemodule_from_id('reader', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $reader = $DB->get_record('reader', array('id' => $cm->instance), '*', MUST_EXIST);
    $r = $reader->id;
} else {
    $reader = $DB->get_record('reader', array('id' => $r), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('reader', $reader->id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $id = $cm->id;
}

require_login($course, true, $cm);

add_to_log($course->id, 'reader', 'Download Quizzes', "admin/download.php?id=$id", "$cm->instance");

// Initialize $PAGE, compute blocks
$PAGE->set_url('/mod/reader/admin/download.php', array('id' => $cm->id, 'type' => $type));

$title = $course->shortname . ': ' . format_string($reader->name);
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

$output = $PAGE->get_renderer('mod_reader_download');

switch ($type) {
    case reader_downloader::BOOKS_WITH_QUIZZES: $str = 'uploadquiztoreader'; break;
    case reader_downloader::BOOKS_WITHOUT_QUIZZES: $str = 'uploaddatanoquizzes'; break;
}

echo $output->header();
echo $output->heading(get_string($str, 'reader'));

// create an object to represent main download site (moodlereader.net)
$remotesite = new reader_remotesite_moodlereadernet(get_config('reader', 'serverlink'),
                                                    get_config('reader', 'serverlogin'),
                                                    get_config('reader', 'serverpassword'));

// create an object to handle the downloading of data from remote sites
$downloader = new reader_downloader($course, $cm, $reader, $output);

// register the known remote sites with the downloader
$downloader->add_remotesite($remotesite);

// get a list of items that have already been downloaded
// from the remote site and are stored in the Moodle DB
$downloader->get_downloaded_items($type);

// download the list(s) of available items from each remote site
$downloader->add_available_items($type, $selecteditemids);

// if any itemids have been selected for download, check that
// they are valid, and expand selected publishers and levels
$downloader->check_selected_itemids($selectedpublishers,
                                    $selectedlevels,
                                    $selecteditemids);

// download selected any itemids from the remote site(s)
// and add them to this Moodle site
$downloader->add_selected_itemids($type, $selecteditemids);

// if any downloadable items were found on the remote sites,
// show them in a selectable, hierarchical menu
if ($downloader->has_available_items()) {

    echo $output->box_start('generalbox', 'notice');
    echo $output->form_start();

    echo $output->search_box();
    echo $output->showhide_menu($downloader->has_updated_items());

    echo $output->available_lists($downloader);

    echo $output->form_end();
    echo $output->box_end();

} else if (get_config('reader', 'serverlink') && get_config('reader', 'serverlogin')) {
    echo $output->heading(get_string('nodownloaditems', 'reader'), '3');
} else {
    echo $output->box_start('generalbox', 'notice');
    $link = new moodle_url('/admin/settings.php', array('section' => 'modsettingreader'));
    $link = html_writer::link($link, get_string('settings'));
    echo html_writer::tag('p', get_string('remotesitenotaccessible', 'reader'));
    echo html_writer::tag('p', get_string('definelogindetails', 'reader', $link));
    echo $output->box_end();
}

echo $output->footer();
