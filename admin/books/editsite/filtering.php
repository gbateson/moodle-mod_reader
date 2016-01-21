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
 * Filtering for Reader books edit
 *
 * @package   mod-reader
 * @copyright 2013 Gordon Bateson <gordon.bateson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// get parent class
require_once($CFG->dirroot.'/mod/reader/admin/books/filtering.php');

/**
 * reader_admin_books_editsite_filtering
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_admin_books_editsite_filtering extends reader_admin_books_filtering {
    /**
     * get_field
     * reader version of standard function
     *
     * @param xxx $fieldname
     * @param xxx $advanced
     * @return xxx
     */
    function get_field($fieldname, $advanced)  {
        global $output;

        $default = $this->get_default_value($fieldname);
        switch ($fieldname) {

            case 'hidden':
                $label = get_string($fieldname, 'mod_reader');
                $options = array(0 => get_string('show'), 1 => get_string('hide'));
                return new reader_admin_filter_simpleselect($fieldname, $label, $advanced, $fieldname, $options, $default, 'where');

            case 'genre':
                $label = get_string($fieldname, 'mod_reader');
                $options = mod_reader_renderer::valid_genres();
                return new reader_admin_filter_simpleselect($fieldname, $label, $advanced, $fieldname, $options, $default, 'where');

            case 'quiz':
                $label = get_string('modulename', 'mod_quiz');
                $options = array(0 => get_string('no'), 1 => get_string('yes'));
                return new reader_admin_filter_simpleselect($fieldname, $label, $advanced, $fieldname, $options, $default, 'having');

            case 'attempts':
                $label = get_string($fieldname, 'mod_reader');
                return new reader_admin_filter_number($fieldname, $label, $advanced, $fieldname, $default, 'having');

            case 'available':
                $label = get_string($fieldname, 'mod_reader');
                $options = array(0 => get_string('no'), 1 => get_string('yes'));
                return new reader_admin_filter_simpleselect($fieldname, $label, $advanced, $fieldname, $options, $default, 'having');

            default:
                // other fields (e.g. from user record)
                return parent::get_field($fieldname, $advanced);
        }
    }
}

/**
 * reader_admin_books_editsite_options
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_admin_books_editsite_options extends reader_admin_books_options {
}
