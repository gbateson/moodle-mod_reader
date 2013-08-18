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
 * Filtering for Reader reports
 *
 * @package   mod-reader
 * @copyright 2013 Gordon Bateson <gordon.bateson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// get parent class
require_once($CFG->dirroot.'/user/filters/lib.php');

// get child classes
require_once($CFG->dirroot.'/mod/reader/report/filters/duration.php');
require_once($CFG->dirroot.'/mod/reader/report/filters/grade.php');
require_once($CFG->dirroot.'/mod/reader/report/filters/group.php');
require_once($CFG->dirroot.'/mod/reader/report/filters/number.php');
require_once($CFG->dirroot.'/mod/reader/report/filters/status.php');

/**
 * reader_report_filtering
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_report_filtering extends user_filtering {

    /**
     * get_field
     *
     * @param xxx $fieldname
     * @param xxx $advanced
     * @return xxx
     */
    function get_field($fieldname, $advanced)  {
        // reader version of standard function

        $default = get_user_preferences('reader_'.$fieldname, '');
        $rawdata = data_submitted();
        if ($rawdata && isset($rawdata->$fieldname) && ! is_array($rawdata->$fieldname)) {
            $default = optional_param($fieldname, $default, PARAM_ALPHANUM);
        }
        unset($rawdata);

        switch ($fieldname) {
            case 'group':
            case 'grouping':
                return new reader_report_filter_group($fieldname, $advanced, $default);
            case 'grade':
                $label = get_string('grade');
                return new reader_report_filter_grade($fieldname, $label, $advanced, $default);
            case 'timemodified':
                $label = get_string('time', 'quiz');
                return new user_filter_date($fieldname, $label, $advanced, $fieldname);
            case 'status':
                return new reader_report_filter_status($fieldname, $advanced, $default);
            case 'duration':
                $label = get_string('duration', 'reader');
                return new reader_report_filter_duration($fieldname, $label, $advanced, $default);
            case 'score':
                $label = get_string('score', 'quiz');
                return new reader_report_filter_number($fieldname, $label, $advanced, $default);
            default:
                // other fields (e.g. from user record)
                return parent::get_field($fieldname, $advanced);
        }
    }

    /**
     * Returns sql where statement based on active user filters
     * @param string $extra sql
     * @param array named params (recommended prefix ex)
     * @return array sql string and $params
     */
    function get_sql_filter($extra='', array $params=null) {
        list($filter, $params) = parent::get_sql_filter($extra, $params);

        // remove empty " AND " conditions at start, middle and end of filter
        $search = array('/^(?: AND )+/', '/(<= AND )(?: AND )+/', '/(?: AND )+$/');
        $filter = preg_replace($search, '', $filter);

        return array($filter, $params);
    }

    /**
     * Returns sql where statement based on active user filters
     *
     * @param string $extra sql
     * @param array named params (recommended prefix ex)
     * @return array sql string and $params
     */
    function get_sql_filter_attempts($extra='', $params=null) {
        global $SESSION;

        $filters = array();
        if ($extra) {
            $filters[] = $extra;
        }
        if (is_null($params)) {
            $params = array();
        } else if (! is_array($params)) {
            $params = (array)$params;
        }

        if (! empty($SESSION->user_filtering)) {
            foreach ($SESSION->user_filtering as $fieldname=>$fielddata) {

                if (! array_key_exists($fieldname, $this->_fields)) {
                    continue;
                }

                $field = $this->_fields[$fieldname];
                if (! method_exists($field, 'get_sql_filter_attempts')) {
                    continue;
                }

                foreach($fielddata as $data) {
                    list($f, $p) = $field->get_sql_filter_attempts($data);
                    if ($f) {
                        $filters[] = $f;
                        $params = array_merge($params, $p);
                    }
                }
            }
        }

        $filter = implode(' AND ', $filters);
        return array($filter, $params);
    }
}
