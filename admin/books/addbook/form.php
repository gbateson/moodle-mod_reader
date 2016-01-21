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
 * mod/reader/admin/books/addbook_form.php
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
 * mod_reader_admin_books_addbook_form
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_books_addbook_form extends moodleform {

    /**
     * constructor
     *
     * @param mixed $action the action attribute (=url) for the form
     * @todo Finish documenting this function
     */
    function mod_reader_admin_books_addbook_form($action=null) {
        if (method_exists('moodleform', '__construct')) {
            // Moodle >= 3.1
            parent::__construct($action);
        } else {
            // Moodle <= 3.0
            parent::moodleform($action);
        }
    }

    /**
     * definition
     *
     * @todo Finish documenting this function
     */
    function definition() {
        $plugin = 'mod_reader';
        $mform = &$this->_form;

        $text_options = array('size' => 40);
        $number_options = array('size' => 6);

        $error_required = get_string('required');
        $error_integer  = get_string('err_regex_integer', $plugin);
        $error_decimal  = get_string('err_regex_float', $plugin);

        $name = 'booktitle';
        $label = get_string($name, $plugin);
        $mform->addElement('text', $name, $label, $text_options);
        $mform->setType($name, PARAM_TEXT);
        $mform->addRule($name, $error_required, 'required', null, 'client');

        $name = 'publisher';
        $label = get_string($name, $plugin);
        $mform->addElement('text', $name, $label, $text_options);
        $mform->setType($name, PARAM_TEXT);
        $mform->addRule($name, $error_required, 'required', null, 'client');

        $name = 'level';
        $label = get_string($name, $plugin);
        $mform->addElement('text', $name, $label, $text_options);
        $mform->setType($name, PARAM_TEXT);

        $name = 'image';
        $label = get_string($name, $plugin);
        $options = array ('maxbytes' => 0, // $CFG->maxbytes
                          'subdirs'  => 0,
                          'maxfiles' => 1,
                          'accepted_types' => 'web_image');
        $mform->addElement('filemanager', $name, $label, '', $options);

        $name = 'genre';
        $label = get_string($name, $plugin);
        $options = mod_reader_renderer::valid_genres();
        unset($options['all']);
        $mform->addElement('select', $name, $label, $options, array('multiple' => 'multiple', 'size' => 5));
        $mform->setType($name, PARAM_TEXT);

        $name = 'difficulty';
        $label = get_string($name, $plugin);
        $mform->addElement('select', $name, $label, range(0, 15));
        $mform->setType($name, PARAM_INT);

        $name = 'words';
        $label = get_string($name, $plugin);
        $mform->addElement('text', $name, $label, $number_options);
        $mform->setType($name, PARAM_INT);
        $mform->addRule($name, $error_required, 'required', null, 'client');
        $mform->addRule($name, $error_integer, 'regex', '/^[0-9]+$/', 'client');

        $name = 'points';
        $label = get_string($name, $plugin);
        $mform->addElement('text', $name, $label, $number_options);
        $mform->setType($name, PARAM_FLOAT);
        $mform->addRule($name, $error_required, 'required', null, 'client');
        $mform->addRule($name, $error_decimal, 'regex', '/^[0-9]+(\.[0-9]+)?$/', 'client');

        $name = 'hidden';
        $label = get_string($name, $plugin);
        $mform->addElement('selectyesno', $name, $label);
        $mform->setType($name, PARAM_INT);

        $this->add_action_buttons(false, get_string('add', 'mod_reader'));
    }
}
