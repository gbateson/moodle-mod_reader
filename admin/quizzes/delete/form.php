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
 * mod/reader/admin/quizzes/delete/form.php
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
 * mod_reader_admin_quizzes_delete_form
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_quizzes_delete_form extends moodleform {

    /**
     * constructor
     *
     * @param mixed $action the action attribute (=url) for the form
     * @todo Finish documenting this function
     */
    function mod_reader_admin_quizzes_delete_form($action=null) {
        parent::moodleform($action);
    }

    /**
     * definition
     *
     * @todo Finish documenting this function
     */
    function definition() {
        global $course, $reader;

        $this->_form->addElement('text', 'publisher', get_string('publisher', 'mod_reader'), array('size' => 40));
        $this->_form->setType('publisher', PARAM_TEXT);

        $this->_form->addElement('text', 'level', get_string('level', 'mod_reader'), array('size' => 40));
        $this->_form->setType('level', PARAM_TEXT);

        $this->_form->addElement('text', 'difficulty', get_string('difficulty', 'mod_reader'), array('size' => 40));
        $this->_form->setType('difficulty', PARAM_INT);

        $this->_form->addElement('text', 'words', get_string('words', 'mod_reader'), array('size' => 40));
        $this->_form->setType('words', PARAM_INT);

        $this->_form->addElement('text', 'genre', get_string('genre', 'block_readerview'), array('size' => 40));
        $this->_form->setType('genre', PARAM_TEXT);

        $this->_form->addElement('text', 'image', get_string('image', 'mod_reader'), array('size' => 40));
        $this->_form->setType('image', PARAM_TEXT);

        $this->_form->addElement('text', 'hidden', get_string('hidden', 'mod_reader'), array('size' => 40));
        $this->_form->setType('hidden', PARAM_INT);

        $this->add_action_buttons(false, get_string('delete', 'mod_reader'));
    }
}
