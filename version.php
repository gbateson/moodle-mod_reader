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

if (! defined('ANY_VERSION')) {
    // Moodle <= 2.1 : do our own dependency check
    if (isset($CFG) && ! file_exists($CFG->dirroot.'/question/type/ordering')) {
        // EITHER installing new site: upgrade_plugins() in "lib/upgradelib.php"
        // OR admin just logged in: moodle_needs_upgrading() in "lib/moodlelib.php"
        throw new moodle_exception('requireqtypeordering', 'reader', new moodle_url('/admin/index.php'), $CFG->dirroot);
    }
}

$cro = 3600;
$com = 'mod_reader';
$mat = MATURITY_BETA; // ALPHA=50, BETA=100, RC=150, STABLE=200
$ver = 2014042662;
$req = 2010112400; // Moodle 2.0;
$rel = '2014-04-26 (62)';
$dep = array('qtype_ordering' => (defined('ANY_VERSION') ? ANY_VERSION : 'any'));

if (isset($plugin) && is_object($plugin)) {
    // Moodle >= 2.5
    $plugin->cron      = $cro;
    $plugin->component = $com;
    $plugin->maturity  = $mat;
    $plugin->requires  = $req;
    $plugin->release   = $rel;
    $plugin->version   = $ver;
    $plugin->dependencies = $dep;
} else {
    // Moodle <= 2.4
    $module->cron      = $cro;
    $module->component = $com;
    $module->maturity  = $mat;
    $module->requires  = $req;
    $module->release   = $rel;
    $module->version   = $ver;
    $module->dependencies = $dep;
}

unset($cro, $com, $mat, $ver, $req, $rel, $dep);
