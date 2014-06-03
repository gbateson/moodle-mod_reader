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
 * mod/reader/classes/event/quiz_started.php
 *
 * @package    mod_reader
 * @copyright  2014 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.6
 */

namespace mod_reader\event;

/** prevent direct access to this script */
defined('MOODLE_INTERNAL') || die();

/**
 * The quiz_started event class.
 *
 * @package    mod_reader
 * @copyright  2014 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.6
 */
class quiz_started extends \core\event\base {

    /**
     * Init method
     */
    protected function init() {
        $this->data['objecttable'] = 'reader';
        parent::init();
    }

    /**
     * Returns localised event name
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventquizstarted', 'mod_reader');
    }

    /**
     * Returns description of this event
     *
     * @return string
     */
    public function get_description() {
        return get_string('eventquizstarted_desc', 'mod_reader', $this);
    }

    /**
     * Returns relevant URL
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/reader/view.php', array('id' => $this->objectid));
    }

    /**
     * Return the legacy event log data
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        return array($this->courseid, 'reader', 'OLD_quiz_started', 'view.php?id='.$this->objectid, $this->other['readerid'], $this->contextinstanceid);
    }

    /**
     * Custom validation
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();
    }
}
