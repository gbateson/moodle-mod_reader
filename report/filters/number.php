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
 * reader_report_filter_number
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_report_filter_number extends user_filter_select {

    var $_type = '';

    /**
     * Constructor
     *
     * @param string $name the name of the filter instance
     * @param string $label the label of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param string $field user table field name
     * @param mixed $default (optional, default = null)
     */
    function __construct($name, $label, $advanced, $field, $default=null, $type='') {
        parent::user_filter_type($name, $label, $advanced);

        $this->_type    = $type;
        $this->_field   = $field;
        $this->_default = $default;
    }

    /**
     * Returns an array of comparison operators
     * @return array of comparison operators
     */
    function get_operators() {
        return array(0 => get_string('isanyvalue','filters'),
                     1 => get_string('islessthan', 'reader'),
                     2 => get_string('isequalto','filters'),
                     3 => get_string('isgreaterthan', 'reader'));
    }

    /**
     * setupForm
     *
     * @param xxx $mform (passed by reference)
     */
    function setupForm(&$mform)  {
        $objs = array(
            $mform->createElement('select', $this->_name.'_op', null, $this->get_operators()),
            $mform->createElement('text', $this->_name, null, array('size' => '3'))
        );
        $mform->addElement('group', $this->_name.'_grp', $this->_label, $objs, '', false);
        $mform->disabledIf($this->_name, $this->_name.'_op', 'eq', 0);

        $mform->setType($this->_name.'_op', PARAM_INT);
        $mform->setType($this->_name, PARAM_INT);

        if (! is_null($this->_default)) {
            $mform->setDefault($this->_name, $this->_default);
        }

        if ($this->_advanced) {
            $mform->setAdvanced($this->_name.'_grp');
        }
    }

    /**
     * Retrieves data from the form data
     * @param object $formdata data submitted with the form
     * @return mixed array filter data or false when filter not set
     */
    function check_data($formdata) {
        $field    = $this->_field;
        $operator = $field.'_op';
        if (isset($formdata->$field) && isset($formdata->$operator)) {
            if (is_numeric($formdata->$field) && is_numeric($formdata->$operator)) {
                if ($formdata->$operator > 0) { // 0 = any value
                    return array('operator' => (int)$formdata->$operator, 'value' => (int)$formdata->$field);
                }
            }
        }
        return false;
    }

    /**
     * get_sql
     *
     * @param array $data
     * @param string $type ("where" or "having")
     * @return xxx
     */
    function get_sql($data, $type)  {
        $filter = '';
        $params = array();
        $counter = reader_report_filtering_uniqueid('ex_num_'.$type);

        if ($this->_type==$type) {
            $name = 'ex_num_'.$type.'_'.$counter;
            if (array_key_exists('value', $data) && array_key_exists('operator', $data)) {
                $field = $this->_field;
                switch($data['operator']) {
                    case 1: // less than
                        $filter = $field.' < :'.$name;
                        $params[$name] = $data['value'];
                        break;
                    case 2: // equal to
                        $filter = $field.' = :'.$name;
                        $params[$name] = $data['value'];
                        break;
                    case 3: // greater than
                        $filter = $field.' > :'.$name;
                        $params[$name] = $data['value'];
                        break;
                }
            }
        }

        return array($filter, $params);
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

    /**
     * Returns a human friendly description of the filter used as label.
     * @param array $data filter settings
     * @return string active filter label
     */
    function get_label($data) {
        $operator  = $data['operator'];
        $value     = $data['value'];
        $operators = $this->get_operators();

        if (empty($operator)) {
            return '';
        }

        $a = (object)array(
            'label'    => $this->_label,
            'value'    => '"'.s($value).'"',
            'operator' => $operators[$operator]
        );

        return get_string('selectlabel', 'filters', $a);
    }
}
