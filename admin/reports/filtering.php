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
 * mod/reader/admin/reports/filtering.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/**
 * Filtering for Reader reports
 *
 * @package   mod-reader
 * @copyright 2013 Gordon Bateson <gordon.bateson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// get parent class

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die();

/** Include required files */
require_once($CFG->dirroot.'/mod/reader/admin/filtering.php');

/**
 * reader_admin_filtering
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_admin_reports_filtering extends reader_admin_filtering {

    /**
     * get_field
     * reader version of standard function
     *
     * @param xxx $fieldname
     * @param xxx $advanced
     * @return xxx
     */
    public function get_field($fieldname, $advanced)  {
        global $CFG, $DB;

        $default = $this->get_default_value($fieldname);
        switch ($fieldname) {

            case 'realname':

                $template = '';
                if (! empty($CFG->fullnamedisplay)) {
                    // The template used to display names to students.
                    $template .= $CFG->fullnamedisplay.' ';
                }
                if (! empty($CFG->alternativefullnameformat)) {
                    // The template used to display names to managers and teachers.
                    $template .= $CFG->alternativefullnameformat.' ';
                }

                // Get array of name fields.
                if (class_exists('\core_user\fields')) {
                    // Moodle >= 3.11
                    $names = array();
                    foreach (\core_user\fields::get_name_fields() as $field) {
                        $names[$field] = $field;
                    }
                } else if (function_exists('get_all_user_name_fields')) {
                    // Moodle >= 2.6
                    $names = get_all_user_name_fields();
                } else {
                    // Moodle <= 2.5
                    $names = array('firstname', 'lastname');
                    $names = array_combine($names, $names);
                }

                if (empty($template) || is_numeric(strpos($template, 'language'))) {
                    // The default template for the current language.
                    $template .= get_string('fullnamedisplay', null, $names);
                }

                // Remove non-alphabetic chars from $template.
                $template = str_replace('language', '', $template);
                $template = preg_replace('/[^a-z]+/', ' ', $template);

                // Convert $template to an array.
                $template = explode(' ', $template);
                $template = array_filter($template);
                $template = array_unique($template);

                // Filter out names that are not used in the template.
                $names = array_intersect($template, array_keys($names));

                // Get SQL for concatenating names.
                $names = explode(',', implode(",' ',", $names));
                $names = call_user_func_array(array($DB, 'sql_concat'), $names);

                $label = get_string('fullname');
                return new reader_admin_filter_text($fieldname, $label, $advanced, $names, $default, 'where');
                break;

            case 'lastname':
            case 'firstname':
            case 'username':
                $label = get_string($fieldname);
                return new reader_admin_filter_text($fieldname, $label, $advanced, $fieldname, $default, 'where');
                break;

            case 'duration':
                $label = get_string($fieldname, 'mod_reader');
                return new reader_admin_filter_duration($fieldname, $label, $advanced, $fieldname, $default, 'having');
                break;

            case 'grade':
                $label = get_string($fieldname);
                return new reader_admin_filter_number($fieldname, $label, $advanced, $fieldname, $default, 'having');

            case 'passed':
                $label = get_string($fieldname, 'mod_reader');
                $options = array(0 => get_string('failedshort', 'mod_reader').' - '.get_string('failed', 'mod_reader'),
                                 1 => get_string('passedshort', 'mod_reader').' - '.get_string('passed', 'mod_reader'));
                return new reader_admin_filter_simpleselect($fieldname, $label, $advanced, $fieldname, $options, $default, 'where');

            case 'cheated':
                $label = get_string($fieldname, 'mod_reader');
                $options = array(0 => get_string('no'),
                                 1 => get_string('yes').' - '.get_string('cheated', 'mod_reader'));
                return new reader_admin_filter_simpleselect($fieldname, $label, $advanced, $fieldname, $options, $default, 'where');

            case 'credit':
                $label = get_string($fieldname, 'mod_reader');
                $options = array(0 => get_string('no'),
                                 1 => get_string('yes').' - '.get_string('credit', 'mod_reader'));
                return new reader_admin_filter_simpleselect($fieldname, $label, $advanced, $fieldname, $options, $default, 'where');

            default:
                // other fields (e.g. from user record)
                die("Unknown filter field: $fieldname");
                return parent::get_field($fieldname, $advanced);
        }
    }
}

/**
 * reader_admin_reports_options
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_admin_reports_options extends reader_admin_options {

    /**#@+
    * values for report $usertype
    *
    * @const integer
    */
    const USERS_ENROLLED_WITH    = 0;
    const USERS_ENROLLED_WITHOUT = 1;
    const USERS_ENROLLED_ALL     = 2;
    const USERS_ALL_WITH         = 3;
    /**#@-*/

    /**#@+
    * values for report $booktype
    *
    * @const integer
    */
    const BOOKS_AVAILABLE_WITH    = 0;
    const BOOKS_AVAILABLE_WITHOUT = 1;
    const BOOKS_AVAILABLE_ALL     = 2;
    const BOOKS_ALL_WITH          = 3;
    /**#@-*/

    /**#@+
    * values for report $termtype
    *
    * @const integer
    */
    const THIS_TERM = 0;
    const ALL_TERMS = 1;
    /**#@-*/

    /**
     * add_field_usertype
     *
     * @param object $mform
     * @param string $name of field i.e. "add_field_usertype"
     * @param mixed  $default value for this $field
     */
    protected function add_field_usertype($mform, $name, $default) {
        $label = get_string('usertype', 'mod_reader');
        $options = array(self::USERS_ENROLLED_WITH    => get_string('usersenrolledwith',    'mod_reader'),
                         self::USERS_ENROLLED_WITHOUT => get_string('usersenrolledwithout', 'mod_reader'),
                         self::USERS_ENROLLED_ALL     => get_string('usersenrolledall',     'mod_reader'),
                         self::USERS_ALL_WITH         => get_string('usersallwith',         'mod_reader'));
        $this->add_select_autosubmit($mform, $name, $label, $options, $default);
    }

    /**
     * get_sql_usertype
     *
     * @param string $name of field i.e. "usertype"
     * @param object $value
     */
    protected function get_sql_usertype($name, $value) {
        return null;
    }

    /**
     * add_field_booktype
     *
     * @param object $mform
     * @param string $name of field i.e. "add_field_booktype"
     * @param mixed  $default value for this $field
     */
    protected function add_field_booktype($mform, $name, $default) {
        $label = get_string('booktype', 'mod_reader');
        $options = array(self::BOOKS_AVAILABLE_WITH    => get_string('booksavailablewith',    'mod_reader'),
                         self::BOOKS_AVAILABLE_WITHOUT => get_string('booksavailablewithout', 'mod_reader'),
                         self::BOOKS_AVAILABLE_ALL     => get_string('booksavailableall',     'mod_reader'));
                         // self::BOOKS_ALL_WITH       => get_string('booksallwith',          'mod_reader')
        $this->add_select_autosubmit($mform, $name, $label, $options, $default);
    }

    /**
     * get_sql_booktype
     *
     * @param string $name of field i.e. "booktype"
     * @param object $value
     */
    protected function get_sql_booktype($name, $value) {
        return null;
    }

    /**
     * add_field_termtype
     *
     * @param object $mform
     * @param string $name of field i.e. "add_field_termtype"
     * @param mixed  $default value for this $field
     */
    protected function add_field_termtype($mform, $name, $default) {
        $label = get_string('termtype', 'mod_reader');
        $options = array(self::THIS_TERM => get_string('thisterm', 'mod_reader'),
                         self::ALL_TERMS => get_string('allterms', 'mod_reader'));
        $this->add_select_autosubmit($mform, $name, $label, $options, $default);
    }

    /**
     * get_sql_termtype
     *
     * @param string $name of field i.e. "termtype"
     * @param object $value
     */
    protected function get_sql_termtype($name, $value) {
        return null;
    }
}
