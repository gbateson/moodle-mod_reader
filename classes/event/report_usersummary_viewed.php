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
 * mod/reader/classes/event/report_usersummary_viewed.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/**
 * mod/reader/classes/event/report_usersummary_viewed.php
 *
 * @package    mod_reader
 * @copyright  2014 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.6
 */

namespace mod_reader\event;

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die();

/**
 * The report_usersummary_viewed event class.
 *
 * @package    mod_reader
 * @copyright  2014 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.6
 */
class report_usersummary_viewed extends base {

    /**
     * Return the legacy event name
     *
     * @return array
     */
    public static function get_legacy_eventname() {
        return 'reportusersummaryviewed';
    }

    /**
     * Init method
     */
    protected function init() {
        $this->data['objecttable'] = 'reader';
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }
}
