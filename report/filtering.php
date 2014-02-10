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
require_once($CFG->dirroot.'/mod/reader/report/filters/date.php');
require_once($CFG->dirroot.'/mod/reader/report/filters/select.php');
require_once($CFG->dirroot.'/mod/reader/report/filters/simpleselect.php');
require_once($CFG->dirroot.'/mod/reader/report/filters/text.php');

require_once($CFG->dirroot.'/mod/reader/report/filters/duration.php');
require_once($CFG->dirroot.'/mod/reader/report/filters/group.php');
require_once($CFG->dirroot.'/mod/reader/report/filters/number.php');

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
     * reader version of standard function
     *
     * @param xxx $fieldname
     * @param xxx $advanced
     * @return xxx
     */
    function get_field($fieldname, $advanced)  {
        global $DB;

        $default = $this->get_default_value($fieldname);
        switch ($fieldname) {

            case 'realname':
                $label = get_string('fullname');
                return new reader_report_filter_text($fieldname, $label, $advanced, $DB->sql_fullname(), $default, 'where');
                break;

            case 'lastname':
            case 'firstname':
            case 'username':
                $label = get_string($fieldname);
                return new reader_report_filter_text($fieldname, $label, $advanced, $fieldname, $default, 'where');
                break;

            default:
                // other fields (e.g. from user record)
                die("Unknown filter field: $fieldname");
                return parent::get_field($fieldname, $advanced);
        }
    }

    /**
     * get_default_value
     *
     * @param string $fieldname
     * @return array sql string and $params
     */
    function get_default_value($fieldname) {
        $default = get_user_preferences('reader_'.$fieldname, '');
        $rawdata = data_submitted();
        if ($rawdata && isset($rawdata->$fieldname) && ! is_array($rawdata->$fieldname)) {
            $default = optional_param($fieldname, $default, PARAM_ALPHANUM);
        }
        return $default;
    }

    /**
     * Returns sql statement based on active filters
     * @param string $extra sql
     * @param array named params (optional, default = null) recommended prefix "ex"
     * @param string $type of sql (optional, default = "filter") "filter", "where" or "having"
     * @return array sql string and $params
     */
    function get_sql($extra='', array $params=null, $type='filter') {
        global $SESSION;

        $sqls = array();
        if ($extra) {
            $sqls[] = $extra;
        }
        if ($params===null) {
            $params = array();
        }

        $method = 'get_sql_'.$type;

        if (! empty($SESSION->user_filtering)) {
            foreach ($SESSION->user_filtering as $fieldname => $conditions) {
                if (! array_key_exists($fieldname, $this->_fields)) {
                    continue; // filter not used
                }
                $field = $this->_fields[$fieldname];
                if (! method_exists($field, $method)) {
                    continue; // no $type sql for this $field
                }
                foreach ($conditions as $condition) {
                    list($s, $p) = $field->$method($condition);
                    if ($s) {
                        $sqls[] = $s;
                        $params += $p;
                    }
                }
            }
        }

        $sqls = implode(' AND ', $sqls);
        return array($sqls, $params);
    }

    /**
     * Returns sql WHERE and HAVING statements based on active user filters
     * @param string $extra sql
     * @param array named params (optional, default = null) recommended prefix "ex"
     * @return array ($wherefilter, $havingfilter, $params)
     */
    function get_sql_filter($extra='', array $params=null) {
        list($wherefilter, $whereparams) = $this->get_sql_where($extra, $params);
        list($havingfilter, $havingparams) = $this->get_sql_having($extra, $params);

        // remove empty " AND " conditions at start, middle and end of filter
        $search = array('/^(?: AND )+/', '/(<= AND )(?: AND )+/', '/(?: AND )+$/');

        $wherefilter = preg_replace($search, '', $wherefilter);
        $havingfilter = preg_replace($search, '', $havingfilter);

        if ($whereparams || $havingparams) {
            if ($params===null) {
                $params = array();
            }
            if ($whereparams) {
                $params += $whereparams;
            }
            if ($havingparams) {
                $params += $havingparams;
            }
        }

        return array($wherefilter, $havingfilter, $params);
    }

    /**
     * Returns sql WHERE statement based on active user filters
     * @param string $extra sql
     * @param array named params (optional, default = null) recommended prefix "ex"
     * @return array ($sql, $params)
     */
    function get_sql_where($extra='', array $params=null) {
        return $this->get_sql($extra, $params, 'where');
    }

    /**
     * Returns sql HAVING statement based on active filters
     * @param string $extra sql
     * @param array named params (recommended prefix ex)
     * @return array ($sql, $params)
     */
    function get_sql_having($extra='', array $params=null) {
        return $this->get_sql($extra, $params, 'having');
    }

    /*
     * uniqueid
     *
     * @param string $type
     * @return string $uniqueid
     */
    static function uniqueid($type) {
        static $types = array();
        if (isset($types[$type])) {
            $types[$type] ++;
        } else {
            $types[$type] = 0;
        }
        return $types[$type];
    }
}
