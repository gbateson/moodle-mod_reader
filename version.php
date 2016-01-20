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
 * mod/reader/version.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

// prevent direct access to this script
defined('MOODLE_INTERNAL') || die();

if (empty($CFG)) {
    global $CFG;
}

// Moodle <= 2.6
if (isset($CFG->release)) {
    $moodle_26 = version_compare($CFG->release, '2.6.99', '<=');
} else if (isset($CFG->yui3version)) {
    $moodle_26 = version_compare($CFG->yui3version, '3.13.99', '<=');
} else {
    $moodle_26 = false;
}

if ($moodle_26) {
    $plugin = new stdClass();
}

$plugin->cron      = 3600;
$plugin->component = 'mod_reader';
$plugin->maturity  = MATURITY_BETA; // ALPHA=50, BETA=100, RC=150, STABLE=200
$plugin->requires  = 2010112400;    // Moodle 2.0
$plugin->version   = 2016012024;
$plugin->release   = '2016-01-20 (24)';

if (defined('ANY_VERSION')) {
    $plugin->dependencies = array('qtype_ordering' => ANY_VERSION);
} else {
    // Moodle <= 2.1 : do our own dependency check
    if (isset($CFG) && ! file_exists($CFG->dirroot.'/question/type/ordering')) {
        // EITHER installing new site: upgrade_plugins() in "lib/upgradelib.php"
        // OR admin just logged in: moodle_needs_upgrading() in "lib/moodlelib.php"
        throw new moodle_exception('requireqtypeordering', 'reader', new moodle_url('/admin/index.php'), $CFG->dirroot);
    }
}

if ($moodle_26) {
    $module = clone($plugin);
}
