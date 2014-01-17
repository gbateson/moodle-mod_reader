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
 * mod/reader/loadsectionoption.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/** Include required files */
require_once('../../config.php');
require_once($CFG->dirroot.'/mod/reader/lib.php');

$id = optional_param('id', 0, PARAM_INT);

require_login($id);

$course = $DB->get_record('course', array('id'=>$id));
if ($sections = $DB->get_records('course_sections', array('course' => $id))) {

    $numsections = reader_get_numsections($course);

    if ($course->format=='weeks' || $course->format=='weekscss') {
        $sectiontype = 'week';
    } else if ($course->format=='topics') {
        $sectiontype = 'topic';
    } else {
        $sectiontype = 'section';
    }

    foreach ($sections as $section) {
        if ($section->section <= $numsections) {
            $sectionname = strip_tags($section->name);
            $sectionname = preg_replace('/\s+/s', ' ', $sectionname);
            $sectionname = trim($sectionname);
            if ($sectionname=='') {
                $sectionname = get_string($sectiontype).' '.$section->section;
            }
            if (strlen($sectionname) > 25) {
                $sectionname = substr($sectionname, 0, 25).'...';
            }
            echo '<option value="'.$section->section.'">'.$sectionname.'</option>';
        }
    }
}
