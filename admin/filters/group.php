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
 * mod/reader/admin/filters/group.php
 *
 * @package    mod
 * @subpackage reader
 * @copyright  2013 Gordon Bateson (gordon.bateson@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.0
 */

/**
 * Filter attempts for reports on a Reader activity
 *
 * @package   mod-reader
 * @copyright 2013 Gordon Bateson <gordon.bateson@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// get parent class

/** Prevent direct access to this script */
defined('MOODLE_INTERNAL') || die();

/** Include required files */
require_once($CFG->dirroot.'/user/filters/select.php');

/**
 * reader_admin_filter_group
 *
 * @copyright 2013 Gordon Bateson
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 2.0
 */
class reader_admin_filter_group extends reader_admin_filter_select {

    /**
     * Constructor
     * @param string $name the name of the filter instance
     * @param boolean $advanced advanced form element flag
     * @param mixed $default (optional, default = null)
     * @param string $type (optional, default = "")
     */
    function __construct($filtername, $advanced, $default=null, $type='') {
        global $DB, $reader;

        $label = '';
        $options = array();

        $strgroup = get_string('group', 'group');
        $strgrouping = get_string('grouping', 'group');

        if ($groupings = groups_get_all_groupings($reader->course->id)) {
            $label = $strgrouping;
            $has_groupings = true;
        } else {
            $has_groupings = false;
            $groupings = array();
        }

        if ($groups = groups_get_all_groups($reader->course->id)) {
            if ($label) {
                $label .= ' / ';
            }
            $label .= $strgroup;
            $has_groups = true;
        } else {
            $has_groups = false;
            $groups = array();
        }

        foreach ($groupings as $gid => $grouping) {
            if ($has_groups) {
                $prefix = $strgrouping.': ';
            } else {
                $prefix = '';
            }
            if ($count = mod_reader::count_grouping_members($gid)) {
                $options["grouping$gid"] = $prefix.format_string($grouping->name).' ('.$count.')';
            }
        }

        foreach ($groups as $gid => $group) {
            if ($count = mod_reader::count_group_members($gid)) {
                if ($has_groupings) {
                    $prefix = $strgroup.': ';
                } else {
                    $prefix = '';
                }
                $options["group$gid"] = $prefix.format_string($group->name).' ('.$count.')';
            }
        }

        parent::__construct($filtername, $label, $advanced, $filtername, $options, $default, $type);
    }

    /**
     * setupForm
     *
     * @param xxx $mform (passed by reference)
     */
    function setupForm(&$mform)  {
        // only setup the select element if it has any options
        if (count($this->_options)) {
            parent::setupForm($mform);
        }
    }

    /**
     * get_sql_filter
     *
     * @param xxx $data
     * @return xxx
     */
    function get_sql_filter($data)  {
        global $DB, $reader;

        $filter = '';
        $params = array();

        if (($value = $data['value']) && ($operator = $data['operator'])) {

            $userids = array();
            if (substr($value, 0, 5)=='group') {
                if (substr($value, 5, 3)=='ing') {
                    $gids = groups_get_all_groupings($reader->course->id);
                    $gid = intval(substr($value, 8));
                    if ($gids && array_key_exists($gid, $gids)) {
                        $userids = mod_reader::get_grouping_userids($gid);
                    }
                } else {
                    $gids = groups_get_all_groups($reader->course->id);
                    $gid = intval(substr($value, 5));
                    if ($gids && array_key_exists($gid, $gids)) {
                        $userids = mod_reader::get_group_userids($gid);
                    }
                }
            }

            if (count($userids)) {
                switch($operator) {
                    case 1: // is equal to
                        list($filter, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, '', true);
                        break;
                    case 2: // isn't equal to
                        list($filter, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, '', false);
                        break;
                }
                if ($filter) {
                    $filter = 'u.id '.$filter;
                }
            }
        }

        return array($filter, $params);
    }
}

