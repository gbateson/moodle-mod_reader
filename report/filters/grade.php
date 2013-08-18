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
 * reader_report_filter_grade
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_report_filter_grade extends user_filter_select {

    /**
     * get_sql_filter_attempts
     *
     * @param xxx $data
     * @return xxx
     */
    function get_sql_filter_attempts($data)  {
        static $counter = 0;
        $name = 'ex_grade'.$counter++;

        $filter = '';
        $params = array();
        if (($value = $data['value']) && ($operator = $data['operator'])) {
            $field = 'gg.rawgrade';
            switch($operator) {
                case 1: // less than
                    $filter = $field.'>:'.$name;
                    $params[$name] = $value;
                    break;
                case 2: // equal to
                    $filter = $field.'=:'.$name;
                    $params[$name] = $value;
                    break;
                case 3: // greater than
                    $filter = $field.'>:'.$name;
                    $params[$name] = $value;
                    break;
            }
        }
        return array($filter, $params);
    }
}
