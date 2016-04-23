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
 * mod/reader/admin/users/export_form.php
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
 * mod_reader_admin_users_export_form
 *
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 * @package    mod
 * @subpackage reader
 */
class mod_reader_admin_users_export_form extends moodleform {

    /**
     * constructor
     *
     * @param mixed $action the action attribute (=url) for the form
     * @todo Finish documenting this function
     */
    function __construct($action=null) {
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
        global $course, $reader;

        // search string to match one or more punctuation chars
        $search = '/['.preg_quote(' !"#$%&\'()*+,-./:;<=>?@[\]^_`{¦}~', '/').']+/';

        $title = $course->shortname; //.' '.$reader->name;
        $title = strip_tags(format_string($title, true));
        $filename = clean_filename($title).'_attempts';
        $filename = preg_replace($search, '_', $filename).'.txt';

        $this->_form->addElement('text', 'filename', get_string('filename', 'mod_reader'), array('size' => 40));
        $this->_form->setDefault('filename', $filename);
        $this->_form->setType('filename', PARAM_FILE);

        $this->add_action_buttons(false, get_string('usersexport', 'mod_reader'));
    }
}
