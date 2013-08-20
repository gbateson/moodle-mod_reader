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
 * Filter attempts for reports on a Reader activity
 *
 * @package   mod-reader
 * @copyright 2013 Gordon Bateson <gordon.bateson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// get parent class
require_once($CFG->dirroot.'/user/filters/select.php');

/**
 * reader_report_filter_passed
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_report_filter_passed extends user_filter_simpleselect {

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table filed name
     */
    function reader_report_filter_passed($name, $label, $advanced, $field) {
        $options = array('true'  => get_string('passedshort', 'reader').' - '.get_string('passed', 'reader'),
                         'false' => get_string('failedshort', 'reader').' - '.get_string('failed', 'reader'));
        parent::user_filter_simpleselect($name, $label, $advanced, $field, $options);
    }

    /**
     * Returns the condition to be used with SQL
     *
     * @param array $data filter settings
     * @return array sql string and $params
     */
    function get_sql_filter($data) {
        static $counter = 0;
        $name = 'ex_passed'.$counter++;

        $value = $data['value'];
        $field = $this->_field;
        if ($value == '') {
            return array();
        }
        return array("$field = :$name", array($name => $value));
    }
}
