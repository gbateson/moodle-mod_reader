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
 * mod/reader/dlquizzes_form.php
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

/**
 * reader_uploadbooks_form
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class reader_uploadbooks_form extends moodleform {

    /**
     * definition
     *
     * @uses $CFG
     * @uses $DB
     * @uses $OUTPUT
     * @uses $course
     * @uses $id
     * @uses $passprefix
     * @uses $password
     * @uses $publishers
     * @uses $quiz
     * @uses $quizzes
     * @uses $reader
     * @uses $readercfg
     * @uses $readercourseexist
     * @uses $removequizzes
     * @uses $second
     * @uses $step
     * @todo Finish documenting this function
     */
    function definition() {
        global $CFG, $DB, $OUTPUT,
               $course, $id, $passprefix, $password, $publishers, $quiz, $quizzes,
               $reader, $readercfg, $readercourseexist, $removequizzes, $second, $step;

        $mform    = &$this->_form;

        echo $OUTPUT->box_start('generalbox');
        echo get_string('quizzesmustbeinstalled', 'reader');
        echo $OUTPUT->box_end();

        $mform->addElement('header','general', get_string('select_course', 'reader'));

        if ($readercfg->reader_usecourse) {
            $puttocourse = $readercfg->reader_usecourse;
        }
        if ($reader->usecourse) {
            $puttocourse = $reader->usecourse;
        }

        if (empty($puttocourse)) {
            $readercourseexist = $DB->get_record('course', array('shortname' => "Reader"));
            $puttocourse = $readercourseexist->id;
        }

        if (empty($puttocourse)) { //$selectcourseform[0] = 'Create new course';
            $selectcourseform[0] = 'Create new course';
        }

        $courses = get_courses();

        foreach ($courses as $course) {
            if ($course->id != 1) {
                $selectcourseform[$course->id] = $course->fullname;
            }
        }

        $mform->addElement('select', 'courseid', get_string('use_this_course', 'reader'), $selectcourseform);

        if (empty($puttocourse)) {
            $mform->setDefault('courseid', 0);
        } else {
            $mform->setDefault('courseid', $puttocourse);
        }

        if (empty($puttocourse)) {
            $puttocourse = $course->id;
        }

        $sections = $DB->get_records('course_sections', array('course' => $puttocourse));

        $tocourse = $DB->get_record('course', array('id' => $puttocourse));
        $t = 0;
        $selectorsection = '<select name="selectorsection">';
        foreach ($sections as $section) {
            if ($t <= $tocourse->numsections) {
                $sectionname = trim(strip_tags($section->name));
                if (empty($sectionname)) {
                    $sectionname = 'Section '.$section->section;
                } else {
                    $sectionname = preg_replace('/\s+/s', ' ', $sectionname);
                }
                if (strlen($sectionname) > 25) {
                    $sectionname = substr($sectionname, 0, 25).'...';
                }
                $selectorsection .= '<option value="'.$section->section.'">'.$sectionname.'</option>';
                $selectsectionform[$section->section] = $sectionname;
            }
            $t++;
        }
        $selectorsection .= '</select>';

        $html = '';
        $html .= '<div style="clear:both;"></div><div style="padding:20px">';
        $html .= '<div><input type="radio" name="sectionchoosing" value="1"/> '.get_string('s_sectiontothebottom', 'reader').'</div>';
        $html .= '<div><input type="radio" name="sectionchoosing" value="2" checked="checked" /> '.get_string('s_sectiontoseparate', 'reader').'</div>';
        $html .= '<div style="float: left; width: 25px;"><input type="radio" name="sectionchoosing" value="3" id="sectionradio" /></div>';
        $html .= '<div style="float:left;padding-right:20px;">'.get_string('s_sectiontothissection', 'reader').'</div>';
        $html .= '<div id="loadersection" style="padding-left:20px;display:none;"><img src="img/zoomloader.gif" width="16" height="16" alt="" /></div>';
        $html .= '<div style="clear:both;"></div>';
        $mform->addElement('html', $html);

        $mform->addElement('select', 'section', '', $selectsectionform);

        //Quizzes ID
        foreach ($quiz as $key => $value) {
            if (isset($removequizzes) && is_array($removequizzes)) {
                if (! in_array($value, $removequizzes)) {
                    $mform->addElement('hidden', 'quiz['.$key.']', $value);
                }
            } else {
                $mform->addElement('hidden', 'quiz['.$key.']', $value);
            }
        }

        //Passwords form
        //if (! isset($checkincorrect)) {
        foreach ($publishers as $key => $value) {
            foreach ($value as $key2 => $value2) {
                if ($value2['pass'] == 'true') {
                    $mform->addElement('hidden', 'password['.$key.']['.$key2.']', $password[$key][$key2]);
                }
            }
        }
        //}

        $this->add_action_buttons($cancel = false, get_string('install_quizzes', 'reader'));
    }
}
