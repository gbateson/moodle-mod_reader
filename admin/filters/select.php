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
 * reader_admin_filter_select
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_admin_filter_select extends user_filter_select {

    var $_type = '';

    /**
     * Constructor
     *
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table field name
     * @param mixed $default (optional, default = null)
     * @param string $type (optional, default = "")
     */
    function __construct($name, $label, $advanced, $field, $options, $default=null, $type='') {
        global $SESSION;

        // Ensure current value for this field is in $options.
        // This is to prevent warnings when switching courses.
        // For example, the current "group" maybe a group
        // from a different course, that is not in $options
        if (isset($SESSION->user_filtering[$name])) {
            $values = $SESSION->user_filtering[$name];
            $keys = array_reverse(array_keys($values));
            foreach ($keys as $key) {
                if (! array_key_exists('value', $values[$key])) {
                    continue; // shouldn't happen !!
                }
                if (! array_key_exists($values[$key]['value'], $options)) {
                    unset($SESSION->user_filtering[$name][$key]);
                }
            }
        }

        // normal setup using parent constructor
        parent::user_filter_select($name, $label, $advanced, $field, $options);

        // special fields for this class
        $this->_default = $default;
        $this->_type    = $type;
    }

    /**
     * get_sql
     *
     * @param array $data
     * @param string $type ("where" or "having")
     * @return xxx
     */
    function get_sql($data, $type)  {
        if ($this->_type==$type) {
            return $this->get_sql_filter($data);
        } else {
            return array('', array());
        }
    }

    /**
     * get_sql_where
     *
     * @param xxx $data
     * @return xxx
     */
    function get_sql_where($data)  {
        return $this->get_sql($data, 'where');
    }

    /**
     * get_sql_having
     *
     * @param xxx $data
     * @return xxx
     */
    function get_sql_having($data)  {
        return $this->get_sql($data, 'having');
    }
}
