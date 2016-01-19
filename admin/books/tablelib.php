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
 * Create a table to display attempts at a Reader activity
 *
 * @package   mod-reader
 * @copyright 2013 Gordon Bateson <gordon.bateson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// get parent class
require_once($CFG->dirroot.'/mod/reader/admin/tablelib.php');

/**
 * reader_admin_books_table
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_admin_books_table extends reader_admin_table {

    protected $maintable = '';

    const MAX_DIFFICULTY = 99;
    const MAX_WORDS      = 999999;
    const MAX_POINTS     = 99;

    ////////////////////////////////////////////////////////////////////////////////
    // functions to format, display and handle action settings                    //
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * setting_error_msg
     *
     * @param integer $value
     * @param integer $min
     * @param integer $max
     * @return xxx
     */
    public function setting_error_msg($value, $min, $max) {
        if ($value >= 0 && $value <= $max) {
            return ''; // no problem  :-)
        } else {
            $a = (object)array('min' => 0, 'max' => number_format($max));
            return ' '.html_writer::tag('span', get_string('valueoutofrange', 'mod_reader', $a), array('class' => 'error'));
        }
    }

    /**
     * display_action_settings_setdifficulty
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_setdifficulty($action) {
        $value = optional_param($action, 0, PARAM_FLOAT);
        $options = range(0, 15);
        $settings = '';
        $settings .= html_writer::select($options, $action, $value, '', $this->display_action_onclickchange($action, 'onchange'));
        $settings .= $this->setting_error_msg($value, 0, self::MAX_DIFFICULTY);
        return $this->display_action_settings($action, $settings);
    }

    /**
     * display_action_settings_setwords
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_setwords($action) {
        $value = optional_param($action, 0, PARAM_INT);
        $settings = '';
        $params = array('type' => 'text', 'name' => $action, 'value' => $value, 'size' => 6);
        $params += $this->display_action_onclickchange($action, 'onchange');
        $settings .= html_writer::empty_tag('input', $params);
        $settings .= $this->setting_error_msg($value, 0, self::MAX_WORDS);
        return $this->display_action_settings($action, $settings);
    }

    /**
     * display_action_settings_setpoints
     *
     * @param string $action
     * @return xxx
     */
    public function display_action_settings_setpoints($action) {
        $value = optional_param($action, 0, PARAM_INT);
        $settings = '';
        $params = array('type' => 'text', 'name' => $action, 'value' => $value, 'size' => 6);
        $params += $this->display_action_onclickchange($action, 'onchange');
        $settings .= html_writer::empty_tag('input', $params);
        $settings .= $this->setting_error_msg($value, 0, self::MAX_POINTS);
        return $this->display_action_settings($action, $settings);
    }

    /**
     * execute_action_setdifficulty
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_setdifficulty($action) {
        return $this->execute_action_setvalue($action, 'difficulty', 0, self::MAX_DIFFICULTY);
    }

    /**
     * execute_action_setwords
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_setwords($action) {
        return $this->execute_action_setvalue($action, 'words', 0, self::MAX_WORDS);
    }

    /**
     * execute_action_setpoints
     *
     * @param string $action
     * @return xxx
     */
    public function execute_action_setpoints($action) {
        return $this->execute_action_setvalue($action, 'points', 0, self::MAX_POINTS, PARAM_FLOAT);
    }

    /**
     * execute_action_setvalue
     *
     * @param string  $action
     * @param string  $field
     * @param integer $min
     * @param integer $max
     * @param integer $type (optional, default=PARAM_INT)
     * @return xxx
     */
    public function execute_action_setvalue($action, $field, $min, $max, $type=PARAM_INT) {
        $value = optional_param($action, 0, $type);
        if ($value < $min || $value > $max || empty($this->maintable)) {
            return false;
        } else {
            return $this->execute_action_update('id', $this->maintable, $field, $value);
        }
    }
}
