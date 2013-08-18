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
require_once($CFG->dirroot.'/mod/reader/report/filters/number.php');

/**
 * reader_report_filter_duration
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_report_filter_duration extends reader_report_filter_number {

    /**
     * setupForm
     *
     * @param xxx $mform (passed by reference)
     */
    function setupForm(&$mform)  {
        $objs = array(
            $mform->createElement('select', $this->_name.'_op', null, $this->get_operators()),
            $mform->createElement('duration', $this->_name, null, array('optional'=>0, 'defaultunit'=>1))
        );
        $mform->addElement('group', $this->_name.'_grp', $this->_label, $objs, '', false);
        $mform->disabledIf($this->_name.'_grp', $this->_name.'_op', 'eq', 0);

        $mform->setType($this->_name.'_op', PARAM_INT);
        $mform->setType($this->_name.'[number]', PARAM_INT);
        $mform->setType($this->_name.'[timeunit]', PARAM_INT);

        if (!is_null($this->_default)) {
            $mform->setDefault($this->_name, $this->_default);
        }

        if ($this->_advanced) {
            $mform->setAdvanced($this->_name.'_grp');
        }
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
            'value'    => '"'.s(format_time($value)).'"',
            'operator' => $operators[$operator]
        );

        return get_string('selectlabel', 'filters', $a);
    }

    /**
     * get_sql_filter_attempts
     *
     * @param xxx $data
     * @return xxx
     */
    function get_sql_filter_attempts($data)  {
        static $counter = 0;
        $name = 'ex_duration'.$counter++;

        $filter = '';
        $params = array();
        if (($value = $data['value']) && ($operator = $data['operator'])) {
            $field = '(timemodified - timestart)'; // $this->_name;
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
