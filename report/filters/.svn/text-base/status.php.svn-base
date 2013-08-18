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
 * reader_report_filter_status
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_report_filter_status extends user_filter_select {
    /**
     * Constructor
     *
     * @param string $name the name of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param mixed $default option
     */
    function __construct($name, $advanced, $default=null) {
        $label = get_string($name, 'reader');
        $options = reader::available_statuses_list();
        parent::__construct($name, $label, $advanced, '', $options, $default);
    }

    /**
     * get_sql_filter
     *
     * @param xxx $data
     * @return xxx
     */
    function get_sql_filter($data)  {
        // this field type doesn't affect the selection of users
        return array('', array());
    }

    /**
     * get_sql_filter_attempts
     *
     * @param xxx $data
     * @return xxx
     */
    function get_sql_filter_attempts($data)  {
        static $counter = 0;
        $name = 'ex_status'.$counter++;

        $filter = '';
        $params = array();
        if (($value = $data['value']) && ($operator = $data['operator'])) {
            switch($operator) {
                case 1: // is equal to
                    $filter = 'status=:'.$name;
                    $params[$name] = $value;
                    break;
                case 2: // isn't equal to
                    $filter = 'status<>:'.$name;
                    $params[$name] = $value;
                    break;
            }
        }
        return array($filter, $params);
    }
}
